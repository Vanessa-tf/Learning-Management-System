<?php
// course-content.php - COMPLETE FIXED VERSION WITH PROPER QUIZ/EXAM LOGIC
session_start();
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

// Check if user is logged in and has 'student' role
check_session();
if ($_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

// Fetch user details from students table
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT first_name FROM students WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching student: " . $e->getMessage());
    session_destroy();
    header("Location: login.php");
    exit;
}

// Get user's initials for avatar
$username = htmlspecialchars($user['first_name']);
$initials = strtoupper(substr($username, 0, 2));

// Define package features
$package_features = [
    'Basic' => [
        'subjects' => 1,
        'features' => ['courses', 'past_papers']
    ],
    'Standard' => [
        'subjects' => 2,
        'features' => ['courses', 'past_papers', 'live_lessons', 'progress_tracking', 'mock_exams']
    ],
    'Premium' => [
        'subjects' => 4,
        'features' => ['courses', 'past_papers', 'live_lessons', 'progress_tracking', 'mock_exams', 'social_forums']
    ]
];
$package = $_SESSION['package_selected'] ?? 'Basic';
$features = $package_features[$package]['features'] ?? ['courses', 'past_papers'];

// Check if courses feature is available
if (!in_array('courses', $features)) {
    error_log("User $user_id attempted to access courses without permission");
    header("Location: student-dashboard.php");
    exit;
}

// Fetch enrolled courses for sidebar display
$courses = [];
try {
    $stmt = $pdo->prepare("SELECT course_name FROM enrollments WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching enrollments: " . $e->getMessage());
}

// Fetch course name from URL and verify enrollment
$course = isset($_GET['course']) ? trim($_GET['course']) : '';
if (empty($course)) {
    error_log("No course parameter provided");
    header("Location: my-courses.php");
    exit;
}

try {
    // Verify enrollment and get course_id
    $stmt = $pdo->prepare("
        SELECT e.course_id, c.course_name 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE e.user_id = :user_id AND c.course_name = :course_name
    ");
    $stmt->execute(['user_id' => $user_id, 'course_name' => $course]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$enrollment) {
        error_log("User $user_id not enrolled in course: $course");
        header("Location: my-courses.php");
        exit;
    }

    // Fetch course content with availability dates AND quiz attempt status
    $stmt = $pdo->prepare("
        SELECT 
            cc.id, 
            cc.content_type, 
            cc.title, 
            cc.description, 
            cc.url, 
            cc.order_index, 
            cc.open_date, 
            cc.close_date,
            cc.passing_percentage,
            cc.quiz_content,
            lqa.id as attempt_id,
            lqa.submitted_at,
            lqa.grading_status,
            lqa.final_score,
            lqa.score,
            lqa.final_percentage,
            lqa.percentage
        FROM course_content cc
        LEFT JOIN (
            SELECT content_id, id, submitted_at, grading_status, final_score, score, final_percentage, percentage
            FROM lockdown_quiz_attempts
            WHERE user_id = :user_id AND submitted_at IS NOT NULL
            AND id IN (
                SELECT MAX(id) FROM lockdown_quiz_attempts 
                WHERE user_id = :user_id2 
                GROUP BY content_id
            )
        ) lqa ON cc.id = lqa.content_id AND cc.content_type = 'quiz'
        WHERE cc.course_id = :course_id
        ORDER BY cc.order_index ASC
    ");
    $stmt->execute([
        'course_id' => $enrollment['course_id'],
        'user_id' => $user_id,
        'user_id2' => $user_id
    ]);
    $content = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch study materials for this course
    $stmt = $pdo->prepare("
        SELECT 
            id, title, description, category, file_name, file_type, file_size,
            download_count, created_at
        FROM study_materials 
        WHERE course_id = :course_id 
        AND status = 'published'
        AND (target_audience = 'students' OR target_audience = 'both')
        ORDER BY created_at DESC
    ");
    $stmt->execute(['course_id' => $enrollment['course_id']]);
    $study_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch taken quizzes (completed attempts only)
    $stmt = $pdo->prepare("
        SELECT 
            cc.id as quiz_id,
            cc.title as quiz_title,
            cc.description,
            cc.passing_percentage,
            lqa.id as attempt_id,
            lqa.score,
            lqa.final_score,
            lqa.total_marks,
            lqa.submitted_at as completed_at,
            lqa.grading_status,
            lqa.percentage,
            lqa.final_percentage,
            TIMESTAMPDIFF(SECOND, lqa.created_at, lqa.submitted_at) as time_taken,
            CASE 
                WHEN COALESCE(lqa.final_percentage, lqa.percentage) >= COALESCE(cc.passing_percentage, 50) THEN 'pass'
                ELSE 'fail'
            END as performance_category
        FROM course_content cc
        INNER JOIN lockdown_quiz_attempts lqa ON lqa.content_id = cc.id 
            AND lqa.user_id = :user_id
            AND lqa.submitted_at IS NOT NULL
        WHERE cc.course_id = :course_id
        AND cc.content_type = 'quiz'
        ORDER BY lqa.submitted_at DESC
    ");
    $stmt->execute([
        'user_id' => $user_id,
        'course_id' => $enrollment['course_id']
    ]);
    $taken_quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch Mock Exams with date-based locking
    $mock_exams = [];
    if (in_array('mock_exams', $features)) {
        $stmt = $pdo->prepare("
            SELECT 
                me.id,
                me.title,
                me.description,
                me.duration,
                me.marking_type,
                me.start_date,
                me.is_active,
                me.questions,
                me.open_date,
                me.close_date,
                me.allow_retakes,
                mea.id as attempt_id,
                mea.score,
                mea.final_score,
                mea.completed_at,
                mea.start_time,
                mea.marking_status,
                mea.is_locked
            FROM mock_exams me
            LEFT JOIN (
                SELECT exam_id, id, score, final_score, completed_at, start_time, marking_status, is_locked
                FROM mock_exam_attempts
                WHERE user_id = :user_id
                AND id IN (
                    SELECT MAX(id) FROM mock_exam_attempts 
                    WHERE user_id = :user_id2 
                    GROUP BY exam_id
                )
            ) mea ON me.id = mea.exam_id
            WHERE me.course_id = :course_id 
            AND me.is_active = 1
            ORDER BY me.start_date ASC, me.created_at DESC
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'user_id2' => $user_id,
            'course_id' => $enrollment['course_id']
        ]);
        $mock_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process mock exams data with date-based locking
        foreach ($mock_exams as &$exam) {
            $questions = json_decode($exam['questions'], true);
            $exam['total_questions'] = is_array($questions) ? count($questions) : 0;
            
            // Determine exam availability status based on dates
            $current_datetime = new DateTime('now', new DateTimeZone('Africa/Johannesburg'));
            $open_date = !empty($exam['open_date']) ? new DateTime($exam['open_date'], new DateTimeZone('Africa/Johannesburg')) : null;
            $close_date = !empty($exam['close_date']) ? new DateTime($exam['close_date'], new DateTimeZone('Africa/Johannesburg')) : null;
            
            // Priority 1: Check if exam is completed
            if ($exam['completed_at']) {
                if ($exam['marking_status'] === 'pending') {
                    $exam['exam_status'] = 'awaiting_marking';
                    $exam['percentage'] = 0;
                    $exam['formatted_date'] = date('M j, Y g:i A', strtotime($exam['completed_at']));
                } else {
                    // Graded or auto-graded
                    $exam['exam_status'] = 'completed';
                    $final_score = $exam['final_score'] ?? $exam['score'] ?? 0;
                    $exam['percentage'] = $final_score;
                    $exam['formatted_date'] = date('M j, Y g:i A', strtotime($exam['completed_at']));
                }
            }
            // Priority 2: Check date availability
            elseif ($open_date && $current_datetime < $open_date) {
                $exam['exam_status'] = 'not_yet_open';
                $exam['percentage'] = 0;
                $exam['formatted_date'] = date('M j, Y g:i A', strtotime($exam['open_date']));
                
                // Calculate time until opening
                $interval = $current_datetime->diff($open_date);
                $days = $interval->days;
                $hours = $interval->h;
                $minutes = $interval->i;
                
                if ($days > 0) {
                    $exam['time_until'] = $days . ' day' . ($days > 1 ? 's' : '');
                } elseif ($hours > 0) {
                    $exam['time_until'] = $hours . ' hour' . ($hours > 1 ? 's' : '');
                } else {
                    $exam['time_until'] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
                }
            }
            elseif ($close_date && $current_datetime > $close_date) {
                $exam['exam_status'] = 'closed';
                $exam['percentage'] = 0;
                $exam['formatted_date'] = date('M j, Y g:i A', strtotime($exam['close_date']));
            }
            else {
                // Exam is available
                if ($exam['start_time'] && !$exam['completed_at']) {
                    $exam['exam_status'] = 'in_progress';
                } else {
                    $exam['exam_status'] = 'available';
                }
                $exam['percentage'] = 0;
                
                if ($open_date) {
                    $exam['formatted_date'] = date('M j, Y', strtotime($exam['open_date']));
                } else {
                    $exam['formatted_date'] = date('M j, Y', strtotime($exam['start_date']));
                }
                
                // Check if closing soon
                if ($close_date) {
                    $interval = $current_datetime->diff($close_date);
                    $days_remaining = (int)$interval->format('%r%a');
                    
                    if ($days_remaining <= 3 && $days_remaining >= 0) {
                        $exam['closing_soon'] = true;
                        $exam['days_remaining'] = $days_remaining;
                    }
                }
            }
        }
        unset($exam);
    }

} catch (PDOException $e) {
    error_log("Error fetching course content for course $course: " . $e->getMessage());
    header("Location: my-courses.php");
    exit;
}

// Format file sizes for study materials
foreach ($study_materials as &$material) {
    $bytes = $material['file_size'];
    if ($bytes >= 1048576) {
        $material['formatted_file_size'] = round($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        $material['formatted_file_size'] = round($bytes / 1024, 1) . ' KB';
    } else {
        $material['formatted_file_size'] = $bytes . ' B';
    }
}
unset($material);

// Format data for taken quizzes
foreach ($taken_quizzes as &$quiz) {
    // Use final percentage if graded, otherwise use auto percentage
    $display_percentage = $quiz['grading_status'] === 'graded' 
        ? ($quiz['final_percentage'] ?? $quiz['percentage']) 
        : $quiz['percentage'];
    
    $quiz['display_percentage'] = $display_percentage;
    
    if (!empty($quiz['time_taken'])) {
        $seconds = $quiz['time_taken'];
        $minutes = floor($seconds / 60);
        $remaining_seconds = $seconds % 60;
        $quiz['formatted_time'] = $minutes > 0 ? "{$minutes}m {$remaining_seconds}s" : "{$remaining_seconds}s";
    } else {
        $quiz['formatted_time'] = 'N/A';
    }
}
unset($quiz);

// Get active tab from URL parameter
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'lessons';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course); ?> Content - NovaTech FET College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #1e3a6c;
            --gold: #fbbf24;
            --beige: #f5f5dc;
        }
        body { font-family: 'Poppins', sans-serif; }
        .bg-navy { background-color: var(--navy); }
        .bg-gold { background-color: var(--gold); }
        .bg-beige { background-color: var(--beige); }
        .text-navy { color: var(--navy); }
        .text-gold { color: var(--gold); }
        .border-gold { border-color: var(--gold); }
        .dashboard-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
        .content-item { transition: all 0.3s ease; }
        .content-item:hover { transform: translateX(4px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .sidebar { transition: all 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
        .notification-dot { position: absolute; top: -5px; right: -5px; width: 12px; height: 12px; background-color: #ef4444; border-radius: 50%; }
        
        .tab-button {
            transition: all 0.3s ease;
            position: relative;
        }
        .tab-button::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background-color: var(--gold);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .tab-button.active::after {
            transform: scaleX(1);
        }
        .tab-button.active {
            color: var(--gold);
            font-weight: 600;
        }
        
        @keyframes fillProgress {
            from { width: 0%; }
            to { width: var(--progress-width); }
        }
        .progress-bar {
            animation: fillProgress 1s ease-out;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .shake-animation:hover {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes lockPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .lock-icon {
            animation: lockPulse 2s infinite;
        }
        
        .quiz-locked, .exam-locked {
            opacity: 0.6;
            background-color: #f3f4f6;
            cursor: not-allowed;
        }
        
        .quiz-available, .exam-available {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #7c3aed;
        }
        
        .quiz-closing-soon, .exam-closing-soon {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
        }
        
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="bg-beige">
    <div class="overlay" id="overlay"></div>
    
    <!-- Sidebar Navigation -->
    <div class="sidebar bg-navy text-white w-64 fixed h-screen overflow-y-auto" id="sidebar">
        <div class="p-6">
            <div class="flex items-center justify-between mb-10">
                <div class="flex items-center">
                    <img src="Images/ChatGPT Image Sep 15, 2025, 08_43_22 PM.png" alt="NovaTech Logo" class="h-10 w-auto"/>
                    <span class="ml-3 text-xl font-bold">NovaTech FET <span class="text-gold">College</span></span>
                </div>
                <button class="text-white md:hidden" id="closeSidebar"><i class="fas fa-times"></i></button>
            </div>
            <div class="mb-8 p-4 bg-white bg-opacity-10 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gold rounded-full flex items-center justify-center mr-3">
                        <span class="text-navy font-bold"><?php echo $initials; ?></span>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?php echo $username; ?></h3>
                        <p class="text-gold text-sm"><?php echo !empty($courses) ? implode(', ', array_column($courses, 'course_name')) : 'No courses enrolled'; ?></p>
                    </div>
                </div>
            </div>
            <nav class="space-y-2">
                <a href="student-dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-home mr-3"></i><span>Dashboard</span>
                </a>
                <?php if (in_array('courses', $features)): ?>
                <a href="my-courses.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-book-open mr-3"></i><span>My Subjects</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('past_papers', $features)): ?>
                <a href="past-papers.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-file-alt mr-3"></i><span>Past Papers</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('live_lessons', $features)): ?>
                <a href="live-lessons.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-video mr-3"></i><span>Live Lessons</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('progress_tracking', $features)): ?>
                <a href="progress-tracking.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-chart-line mr-3"></i><span>Progress Tracking</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('social_forums', $features)): ?>
                <a href="study-groups.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-users mr-3"></i><span>Social Chatroom</span>
                </a>
                <?php endif; ?>
                <a href="schedule.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-calendar-alt mr-3"></i><span>Timetable</span>
                </a>
                <a href="log_case.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-life-ring mr-3"></i><span>Log Cases</span>
                </a>
                <a href="settings.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-cog mr-3"></i><span>My Profile</span>
                </a>
                <a href="logout.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-sign-out-alt mr-3"></i><span>Logout</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="md:ml-64">
        <!-- Top Navigation -->
        <header class="bg-white shadow-md">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <button class="text-navy md:hidden" id="menuButton"><i class="fas fa-bars"></i></button>
                    <h1 class="text-xl font-bold text-navy"><?php echo htmlspecialchars($course); ?> Content</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button id="notificationButton" class="text-navy relative">
                                <i class="fas fa-bell"></i>
                                <span id="notificationDot" class="notification-dot hidden"></span>
                            </button>
                            <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto">
                                <div class="p-4 border-b">
                                    <h3 class="text-lg font-semibold text-navy">Notifications</h3>
                                </div>
                                <div id="notificationList" class="divide-y"></div>
                                <div class="p-4 border-t text-center">
                                    <a href="notifications.php" class="text-gold hover:underline text-sm">View All Notifications</a>
                                </div>
                            </div>
                        </div>
                        <div class="hidden md:block">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-2">
                                    <span class="text-navy font-bold text-sm"><?php echo $initials; ?></span>
                                </div>
                                <span class="text-navy"><?php echo $username; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Course Content -->
        <main class="container mx-auto px-6 py-8">
            <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-navy"><?php echo htmlspecialchars($course); ?></h2>
                    <a href="my-courses.php" class="text-gold hover:underline">Back to Subjects</a>
                </div>
                
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="flex space-x-8">
                        <button onclick="switchTab('lessons')" class="tab-button <?php echo $active_tab === 'lessons' ? 'active' : ''; ?> text-gray-600 py-4 px-2 font-medium text-sm">
                            <i class="fas fa-book-open mr-2"></i>Lessons
                        </button>
                        <button onclick="switchTab('materials')" class="tab-button <?php echo $active_tab === 'materials' ? 'active' : ''; ?> text-gray-600 py-4 px-2 font-medium text-sm">
                            <i class="fas fa-file-download mr-2"></i>Study Materials
                        </button>
                        <button onclick="switchTab('quiz-history')" class="tab-button <?php echo $active_tab === 'quiz-history' ? 'active' : ''; ?> text-gray-600 py-4 px-2 font-medium text-sm">
                            <i class="fas fa-chart-line mr-2"></i>Quiz History
                        </button>
                        <?php if (in_array('mock_exams', $features)): ?>
                        <button onclick="switchTab('mock-exams')" class="tab-button <?php echo $active_tab === 'mock-exams' ? 'active' : ''; ?> text-gray-600 py-4 px-2 font-medium text-sm">
                            <i class="fas fa-clipboard-check mr-2"></i>Mock Exams
                            <?php if (!empty($mock_exams)): ?>
                            <span class="ml-1 bg-purple-600 text-white text-xs px-2 py-0.5 rounded-full"><?php echo count($mock_exams); ?></span>
                            <?php endif; ?>
                        </button>
                        <?php endif; ?>
                    </nav>
                </div>

                <?php if (empty($content) && empty($study_materials) && empty($taken_quizzes) && empty($mock_exams)): ?>
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-folder-open text-6xl mb-4 opacity-50"></i>
                        <p class="text-lg mb-2">No content available for this course yet.</p>
                        <p class="text-sm">Check back later or <a href="contact-support.php" class="text-gold hover:underline">contact support</a></p>
                    </div>
                <?php else: ?>
                
                    <!-- Lessons Tab -->
                    <div id="lessons-tab" class="tab-content <?php echo $active_tab === 'lessons' ? 'active' : ''; ?>">
                        <?php if (!empty($content)): ?>
                        <div class="space-y-4">
                            <?php foreach ($content as $item): 
                                // Initialize variables
                                $icon = 'fa-file-alt';
                                $bg_color = 'bg-gray-100';
                                $text_color = 'text-gray-600';
                                $type_label = 'Content';
                                $link = '';
                                $target = '';
                                $item_class = '';
                                $status_badge = '';
                               
                                // Determine content type styling
                                switch ($item['content_type']) {
                                    case 'video':
                                        $icon = 'fa-video';
                                        $bg_color = 'bg-red-100';
                                        $text_color = 'text-red-600';
                                        $type_label = 'Video';
                                        $link = !empty($item['url']) ? htmlspecialchars($item['url']) : '';
                                        $target = 'target="_blank"';
                                        break;
                                    case 'pdf':
                                        $icon = 'fa-file-pdf';
                                        $bg_color = 'bg-orange-100';
                                        $text_color = 'text-orange-600';
                                        $type_label = 'PDF';
                                        $link = !empty($item['url']) ? htmlspecialchars($item['url']) : '';
                                        $target = 'target="_blank"';
                                        break;
                                    case 'quiz':
                                        $icon = 'fa-question-circle';
                                        $bg_color = 'bg-purple-100';
                                        $text_color = 'text-purple-600';
                                        $type_label = 'Quiz';
                                        $link = 'take_quiz.php?id=' . $item['id'];
                                        $target = '';
                                       
                                        // Check quiz availability based on dates
                                        $current_datetime = new DateTime('now', new DateTimeZone('Africa/Johannesburg'));
                                        $open_date = !empty($item['open_date']) ? new DateTime($item['open_date'], new DateTimeZone('Africa/Johannesburg')) : null;
                                        $close_date = !empty($item['close_date']) ? new DateTime($item['close_date'], new DateTimeZone('Africa/Johannesburg')) : null;
                                       
                                        // Check if quiz has been submitted (NO RETAKES)
                                        if ($item['submitted_at']) {
                                            $item_class = 'quiz-locked';
                                            
                                            if ($item['grading_status'] === 'pending') {
                                                $status_badge = '
                                                    <div class="mt-3 p-3 bg-yellow-50 rounded-lg border-2 border-yellow-300">
                                                        <div class="flex items-center mb-2">
                                                            <i class="fas fa-hourglass-half pulse-animation text-yellow-600 text-2xl mr-3"></i>
                                                            <div>
                                                                <p class="font-bold text-yellow-800 text-sm">Quiz Submitted</p>
                                                                <p class="text-xs text-gray-600">Your teacher is marking your quiz</p>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center text-xs text-gray-700 mt-2 pt-2 border-t border-gray-300">
                                                            <i class="fas fa-calendar-check mr-2 text-yellow-600"></i>
                                                            <span class="font-medium">Submitted:</span>
                                                            <span class="ml-2">' . date('M j, Y g:i A', strtotime($item['submitted_at'])) . '</span>
                                                        </div>
                                                        <p class="text-xs text-gray-600 mt-2 italic">
                                                            <i class="fas fa-info-circle mr-1"></i>Quiz is locked until marking is complete
                                                        </p>
                                                    </div>';
                                            } else {
                                                // Quiz has been graded
                                                $display_percentage = $item['grading_status'] === 'graded' 
                                                    ? ($item['final_percentage'] ?? $item['percentage']) 
                                                    : $item['percentage'];
                                                
                                                $passing_percentage = $item['passing_percentage'] ?? 50;
                                                $has_passed = $display_percentage >= $passing_percentage;
                                                
                                                $status_badge = '
                                                    <div class="mt-3 p-3 bg-' . ($has_passed ? 'green' : 'red') . '-50 rounded-lg border-2 border-' . ($has_passed ? 'green' : 'red') . '-300">
                                                        <div class="flex items-center mb-2">
                                                            <i class="fas fa-' . ($has_passed ? 'check' : 'times') . '-circle text-' . ($has_passed ? 'green' : 'red') . '-600 text-2xl mr-3"></i>
                                                            <div>
                                                                <p class="font-bold text-' . ($has_passed ? 'green' : 'red') . '-800 text-sm">Quiz ' . ($has_passed ? 'Passed' : 'Failed') . '</p>
                                                                <p class="text-xs text-gray-600">Score: ' . number_format($display_percentage, 1) . '% (Required: ' . $passing_percentage . '%)</p>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center text-xs text-gray-700 mt-2 pt-2 border-t border-gray-300">
                                                            <i class="fas fa-calendar-check mr-2 text-' . ($has_passed ? 'green' : 'red') . '-600"></i>
                                                            <span class="font-medium">Completed:</span>
                                                            <span class="ml-2">' . date('M j, Y g:i A', strtotime($item['submitted_at'])) . '</span>
                                                        </div>
                                                        <p class="text-xs text-gray-600 mt-2 italic">
                                                            <i class="fas fa-lock mr-1"></i>No retakes allowed - results are final
                                                        </p>
                                                    </div>';
                                            }
                                        }
                                        // Check date-based availability
                                        elseif ($open_date && $current_datetime < $open_date) {
                                            $item_class = 'quiz-locked';
                                            $interval = $current_datetime->diff($open_date);
                                            $days = $interval->days;
                                            $hours = $interval->h;
                                            $minutes = $interval->i;
                                            
                                            if ($days > 0) {
                                                $time_until = $days . ' day' . ($days > 1 ? 's' : '');
                                            } elseif ($hours > 0) {
                                                $time_until = $hours . ' hour' . ($hours > 1 ? 's' : '');
                                            } else {
                                                $time_until = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
                                            }
                                            
                                            $open_formatted = $open_date->format('M j, Y g:i A');
                                            $status_badge = '
                                                <div class="mt-3 p-3 bg-gray-100 rounded-lg border-2 border-gray-300">
                                                    <div class="flex items-center mb-2">
                                                        <i class="fas fa-lock lock-icon text-red-600 text-2xl mr-3"></i>
                                                        <div>
                                                            <p class="font-bold text-red-700 text-sm">Quiz Locked</p>
                                                            <p class="text-xs text-gray-600">Opens in ' . $time_until . '</p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center text-xs text-gray-700 mt-2 pt-2 border-t border-gray-300">
                                                        <i class="fas fa-calendar-alt mr-2 text-blue-600"></i>
                                                        <span class="font-medium">Opens:</span>
                                                        <span class="ml-2 countdown-timer text-blue-700">' . $open_formatted . '</span>
                                                    </div>
                                                </div>';
                                        }
                                        elseif ($close_date && $current_datetime > $close_date) {
                                            $item_class = 'quiz-locked';
                                            $closed_date_formatted = $close_date->format('M j, Y g:i A');
                                            $status_badge = '
                                                <div class="mt-3 p-3 bg-red-50 rounded-lg border-2 border-red-300">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-lock text-red-600 text-2xl mr-3"></i>
                                                        <div>
                                                            <p class="font-bold text-red-700 text-sm">Quiz Closed</p>
                                                            <p class="text-xs text-gray-600">Closed on ' . $closed_date_formatted . '</p>
                                                        </div>
                                                    </div>
                                                </div>';
                                        }
                                        else {
                                            // Quiz is available
                                            $item_class = 'quiz-available';
                                            
                                            if ($close_date) {
                                                $interval = $current_datetime->diff($close_date);
                                                $days_remaining = (int)$interval->format('%r%a');
                                                
                                                if ($days_remaining == 0) {
                                                    $item_class = 'quiz-closing-soon shake-animation';
                                                    $quiz_status_message = 'Closes Today at ' . $close_date->format('g:i A');
                                                    $urgency_class = 'bg-red-50 border-red-400';
                                                    $urgency_icon = 'fa-exclamation-triangle animate-pulse';
                                                    $icon_color = 'text-red-600';
                                                } elseif ($days_remaining == 1) {
                                                    $item_class = 'quiz-closing-soon';
                                                    $quiz_status_message = 'Closes Tomorrow at ' . $close_date->format('g:i A');
                                                    $urgency_class = 'bg-orange-50 border-orange-300';
                                                    $urgency_icon = 'fa-exclamation-circle';
                                                    $icon_color = 'text-orange-600';
                                                } elseif ($days_remaining >= 2 && $days_remaining <= 3) {
                                                    $quiz_status_message = 'Closes in ' . $days_remaining . ' days';
                                                    $urgency_class = 'bg-yellow-50 border-yellow-300';
                                                    $urgency_icon = 'fa-hourglass-half';
                                                    $icon_color = 'text-yellow-600';
                                                } else {
                                                    $quiz_status_message = 'Available until ' . $close_date->format('M j, Y');
                                                    $urgency_class = 'bg-green-50 border-green-300';
                                                    $urgency_icon = 'fa-unlock';
                                                    $icon_color = 'text-green-600';
                                                }
                                                
                                                $close_formatted = $close_date->format('M j, Y g:i A');
                                                $status_badge = '
                                                    <div class="mt-3 p-3 ' . $urgency_class . ' rounded-lg border-2">
                                                        <div class="flex items-center mb-2">
                                                            <i class="fas ' . $urgency_icon . ' ' . $icon_color . ' text-2xl mr-3"></i>
                                                            <div>
                                                                <p class="font-bold text-gray-800 text-sm">' . $quiz_status_message . '</p>
                                                                <p class="text-xs text-gray-600">Complete before deadline</p>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center text-xs text-gray-700 mt-2 pt-2 border-t border-gray-300">
                                                            <i class="fas fa-calendar-times mr-2 text-red-600"></i>
                                                            <span class="font-medium">Closes:</span>
                                                            <span class="ml-2 countdown-timer text-red-700">' . $close_formatted . '</span>
                                                        </div>
                                                    </div>';
                                            }
                                        }
                                        break;
                                    case 'lesson':
                                        $icon = 'fa-book-open';
                                        $bg_color = 'bg-blue-100';
                                        $text_color = 'text-blue-600';
                                        $type_label = 'Lesson';
                                        $link = !empty($item['url']) ? htmlspecialchars($item['url']) : '';
                                        $target = 'target="_blank"';
                                        break;
                                }
                                ?>
                               
                                <div class="content-item flex justify-between items-center p-4 border border-gray-200 rounded-lg hover:border-gold transition <?php echo $item_class; ?>">
                                    <div class="flex items-center flex-1">
                                        <div class="w-12 h-12 <?php echo $bg_color; ?> rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                                            <i class="fas <?php echo $icon; ?> <?php echo $text_color; ?> text-xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-navy text-lg"><?php echo htmlspecialchars($item['title']); ?></h3>
                                            <?php if (!empty($item['description'])): ?>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars(substr($item['description'], 0, 120)); ?><?php echo strlen($item['description']) > 120 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                           
                                            <?php if ($item['content_type'] === 'quiz'): ?>
                                                <?php echo $status_badge; ?>
                                            <?php else: ?>
                                                <span class="inline-block mt-2 text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">
                                                    <?php echo $type_label; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <?php if (!empty($link)): ?>
                                            <?php if ($item['content_type'] === 'quiz'): ?>
                                                <?php if ($item['submitted_at']): ?>
                                                    <?php if ($item['grading_status'] === 'pending'): ?>
                                                        <button disabled class="bg-gray-400 text-white px-6 py-2 rounded-lg cursor-not-allowed inline-flex items-center opacity-60">
                                                            <i class="fas fa-clock mr-2"></i> Awaiting Marking
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="view_quiz_feedback.php?attempt_id=<?php echo $item['attempt_id']; ?>" class="bg-navy text-white px-6 py-2 rounded-lg hover:bg-opacity-90 transition inline-flex items-center">
                                                            <i class="fas fa-eye mr-2"></i> View Results
                                                        </a>
                                                    <?php endif; ?>
                                                <?php elseif ($open_date && $current_datetime < $open_date): ?>
                                                    <button disabled class="bg-gray-400 text-white px-6 py-2 rounded-lg cursor-not-allowed inline-flex items-center opacity-60">
                                                        <i class="fas fa-lock mr-2 lock-icon"></i> Locked
                                                    </button>
                                                <?php elseif ($close_date && $current_datetime > $close_date): ?>
                                                    <button disabled class="bg-gray-400 text-white px-6 py-2 rounded-lg cursor-not-allowed inline-flex items-center opacity-60">
                                                        <i class="fas fa-ban mr-2"></i> Closed
                                                    </button>
                                                <?php else: ?>
                                                    <a href="<?php echo $link; ?>" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition inline-flex items-center">
                                                        <i class="fas fa-play mr-2"></i> Start Quiz
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                            <a href="<?php echo $link; ?>" <?php echo $target; ?> class="bg-navy text-white px-6 py-2 rounded-lg hover:bg-opacity-90 transition inline-flex items-center">
                                                <i class="fas fa-external-link-alt mr-2"></i>View
                                            </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm italic">Not available</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-book-open text-6xl mb-4 opacity-50"></i>
                            <p class="text-lg mb-2">No lessons available yet.</p>
                            <p class="text-sm">Check back later for new content</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Study Materials Tab -->
                    <div id="materials-tab" class="tab-content <?php echo $active_tab === 'materials' ? 'active' : ''; ?>">
                        <?php if (!empty($study_materials)): ?>
                        <div class="space-y-4">
                            <?php foreach ($study_materials as $material): ?>
                                <?php
                                $fileExt = strtolower($material['file_type']);
                                $icon = 'fa-file';
                                $bg_color = 'bg-gray-100';
                                $text_color = 'text-gray-600';
                                
                                switch ($fileExt) {
                                    case 'pdf':
                                        $icon = 'fa-file-pdf';
                                        $bg_color = 'bg-red-100';
                                        $text_color = 'text-red-600';
                                        break;
                                    case 'doc':
                                    case 'docx':
                                        $icon = 'fa-file-word';
                                        $bg_color = 'bg-blue-100';
                                        $text_color = 'text-blue-600';
                                        break;
                                    case 'ppt':
                                    case 'pptx':
                                        $icon = 'fa-file-powerpoint';
                                        $bg_color = 'bg-orange-100';
                                        $text_color = 'text-orange-600';
                                        break;
                                }
                                ?>
                                <div class="content-item flex justify-between items-center p-4 border border-gray-200 rounded-lg hover:border-gold transition">
                                    <div class="flex items-center flex-1">
                                        <div class="w-12 h-12 <?php echo $bg_color; ?> rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                                            <i class="fas <?php echo $icon; ?> <?php echo $text_color; ?> text-xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-navy"><?php echo htmlspecialchars($material['title']); ?></h3>
                                            <?php if (!empty($material['description'])): ?>
                                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars(substr($material['description'], 0, 120)); ?><?php echo strlen($material['description']) > 120 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                            <div class="flex items-center mt-2 text-xs text-gray-500">
                                                <span class="mr-3 flex items-center">
                                                    <i class="fas <?php echo $icon; ?> mr-1"></i>
                                                    <?php echo strtoupper($material['file_type']); ?>
                                                </span>
                                                <span class="mr-3 flex items-center">
                                                    <i class="fas fa-hdd mr-1"></i>
                                                    <?php echo $material['formatted_file_size']; ?>
                                                </span>
                                                <span class="flex items-center">
                                                    <i class="fas fa-download mr-1"></i>
                                                    <?php echo $material['download_count']; ?> downloads
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <a href="download_study_material.php?id=<?php echo $material['id']; ?>" 
                                           class="bg-navy text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition inline-flex items-center text-sm">
                                            <i class="fas fa-download mr-1"></i> Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-file-download text-6xl mb-4 opacity-50"></i>
                            <p class="text-lg mb-2">No study materials available yet.</p>
                            <p class="text-xs text-sm">Check back later for resources</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quiz History Tab -->
                    <div id="quiz-history-tab" class="tab-content <?php echo $active_tab === 'quiz-history' ? 'active' : ''; ?>">
                        <?php if (!empty($taken_quizzes)): ?>
                        <div class="space-y-4">
                            <?php foreach ($taken_quizzes as $quiz): ?>
                                <?php
                                $passing_percentage = $quiz['passing_percentage'] ?? 50;
                                $has_passed = $quiz['display_percentage'] >= $passing_percentage;
                                ?>
                            <div class="content-item border-2 border-gray-200 rounded-lg p-4 hover:border-indigo-300 transition">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center flex-1">
                                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                            <i class="fas fa-clipboard-check text-purple-600 text-xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-navy"><?php echo htmlspecialchars($quiz['quiz_title']); ?></h3>
                                            <div class="flex items-center gap-4 mt-2 text-sm flex-wrap">
                                                <span class="<?php echo $has_passed ? 'text-green-600' : 'text-red-600'; ?> font-bold">
                                                    <i class="fas fa-<?php echo $has_passed ? 'check' : 'times'; ?>-circle mr-1"></i>
                                                    <?php echo number_format($quiz['display_percentage'], 1); ?>%
                                                </span>
                                                <span class="text-gray-600">
                                                    <i class="fas fa-star mr-1"></i>
                                                    <?php echo $quiz['grading_status'] === 'graded' ? ($quiz['final_score'] ?? $quiz['score']) : $quiz['score']; ?>/<?php echo $quiz['total_marks']; ?> marks
                                                </span>
                                                <span class="text-gray-600">
                                                    <i class="fas fa-clock mr-1"></i><?php echo $quiz['formatted_time']; ?>
                                                </span>
                                                <span class="text-gray-600">
                                                    <i class="fas fa-calendar mr-1"></i><?php echo date('M j, Y', strtotime($quiz['completed_at'])); ?>
                                                </span>
                                            </div>
                                            <?php if ($quiz['grading_status'] === 'pending'): ?>
                                            <p class="text-xs text-yellow-600 mt-2">
                                                <i class="fas fa-hourglass-half mr-1"></i>Awaiting teacher marking
                                            </p>
                                            <?php elseif ($quiz['grading_status'] === 'graded'): ?>
                                            <p class="text-xs text-green-600 mt-2">
                                                <i class="fas fa-check-circle mr-1"></i>Marked by teacher
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <a href="view_quiz_feedback.php?attempt_id=<?php echo $quiz['attempt_id']; ?>" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition inline-flex items-center text-sm ml-4">
                                        <i class="fas fa-eye mr-2"></i>View Details
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-chart-line text-6xl mb-4 opacity-50"></i>
                            <p class="text-lg mb-2">No quiz attempts yet.</p>
                            <p class="text-sm">Complete quizzes to see your history here</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Mock Exams Tab -->
                    <?php if (in_array('mock_exams', $features)): ?>
                    <div id="mock-exams-tab" class="tab-content <?php echo $active_tab === 'mock-exams' ? 'active' : ''; ?>">
                        <?php if (!empty($mock_exams)): ?>
                        <div class="space-y-4">
                            <?php foreach ($mock_exams as $exam): ?>
                                <?php
                                $status_class = '';
                                $status_icon = '';
                                $status_text = '';
                                $button_class = '';
                                $button_text = '';
                                $button_icon = '';
                                $button_link = '';
                                $button_disabled = false;
                                $status_badge_html = '';
                                $exam_item_class = '';

                                switch ($exam['exam_status']) {
                                    case 'not_yet_open':
                                        $status_class = 'bg-blue-100 text-blue-700';
                                        $status_icon = 'fa-clock';
                                        $status_text = 'Not Yet Available';
                                        $button_class = 'bg-gray-400 cursor-not-allowed';
                                        $button_text = 'Locked';
                                        $button_icon = 'fa-lock';
                                        $button_disabled = true;
                                        $exam_item_class = 'exam-locked';
                                        
                                        $status_badge_html = '
                                            <div class="mt-3 p-3 bg-gray-100 rounded-lg border-2 border-gray-300">
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-lock lock-icon text-red-600 text-2xl mr-3"></i>
                                                    <div>
                                                        <p class="font-bold text-red-700 text-sm">Exam Locked</p>
                                                        <p class="text-xs text-gray-600">Opens in ' . $exam['time_until'] . '</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center text-xs text-gray-700 mt-2 pt-2 border-t border-gray-300">
                                                    <i class="fas fa-calendar-alt mr-2 text-blue-600"></i>
                                                    <span class="font-medium">Opens:</span>
                                                    <span class="ml-2 countdown-timer text-blue-700">' . $exam['formatted_date'] . '</span>
                                                </div>
                                            </div>';
                                        break;
                                        
                                    case 'closed':
                                        $status_class = 'bg-red-100 text-red-700';
                                        $status_icon = 'fa-lock';
                                        $status_text = 'Closed';
                                        $button_class = 'bg-gray-400 cursor-not-allowed';
                                        $button_text = 'Closed';
                                        $button_icon = 'fa-ban';
                                        $button_disabled = true;
                                        $exam_item_class = 'exam-locked';
                                        
                                        $status_badge_html = '
                                            <div class="mt-3 p-3 bg-red-50 rounded-lg border-2 border-red-300">
                                                <div class="flex items-center">
                                                    <i class="fas fa-lock text-red-600 text-2xl mr-3"></i>
                                                    <div>
                                                        <p class="font-bold text-red-700 text-sm">Exam Closed</p>
                                                        <p class="text-xs text-gray-600">Closed on ' . $exam['formatted_date'] . '</p>
                                                    </div>
                                                </div>
                                            </div>';
                                        break;

                                    case 'awaiting_marking':
                                        $status_class = 'bg-yellow-100 text-yellow-700 pulse-animation';
                                        $status_icon = 'fa-hourglass-half';
                                        $status_text = 'Awaiting Marking';
                                        $button_class = 'bg-gray-400 cursor-not-allowed';
                                        $button_text = 'Awaiting Marking';
                                        $button_icon = 'fa-clock';
                                        $button_disabled = true;
                                        $exam_item_class = 'exam-awaiting-marking';
                                        
                                        $status_badge_html = '
                                            <div class="mt-3 p-3 bg-yellow-50 rounded-lg border-2 border-yellow-300">
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-hourglass-half pulse-animation text-yellow-600 text-2xl mr-3"></i>
                                                    <div>
                                                        <p class="font-bold text-yellow-800 text-sm">Exam Submitted</p>
                                                        <p class="text-xs text-gray-600">Your teacher is marking your exam</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center text-xs text-gray-700 mt-2 pt-2 border-t border-gray-300">
                                                    <i class="fas fa-calendar-check mr-2 text-yellow-600"></i>
                                                    <span class="font-medium">Submitted:</span>
                                                    <span class="ml-2">' . $exam['formatted_date'] . '</span>
                                                </div>
                                            </div>';
                                        break;
                                        
                                    case 'available':
                                        $status_class = 'bg-green-100 text-green-700 pulse-animation';
                                        $status_icon = 'fa-play-circle';
                                        $status_text = 'Available Now';
                                        $button_class = 'bg-green-600 hover:bg-green-700';
                                        $button_text = 'Start Exam';
                                        $button_icon = 'fa-play';
                                        $button_link = 'mock-exams.php?exam_id=' . $exam['id'];
                                        $exam_item_class = 'exam-available';
                                        
                                        // Check if closing soon
                                        if (isset($exam['closing_soon']) && $exam['closing_soon']) {
                                            $exam_item_class = 'exam-closing-soon shake-animation';
                                            $urgency_class = 'bg-orange-50 border-orange-300';
                                            $urgency_icon = 'fa-exclamation-triangle animate-pulse';
                                            $urgency_text_color = 'text-orange-800';
                                            $icon_color = 'text-orange-600';
                                            
                                            if ($exam['days_remaining'] == 0) {
                                                $urgency_message = 'Closes Today!';
                                            } else {
                                                $urgency_message = 'Closes in ' . $exam['days_remaining'] . ' day' . ($exam['days_remaining'] > 1 ? 's' : '');
                                            }
                                            
                                            $status_badge_html = '
                                                <div class="mt-3 p-3 ' . $urgency_class . ' rounded-lg border-2">
                                                    <div class="flex items-center mb-2">
                                                        <i class="fas ' . $urgency_icon . ' ' . $icon_color . ' text-2xl mr-3"></i>
                                                        <div>
                                                            <p class="font-bold ' . $urgency_text_color . ' text-sm">' . $urgency_message . '</p>
                                                            <p class="text-xs text-gray-600">Complete before deadline</p>
                                                        </div>
                                                    </div>
                                                </div>';
                                        } else {
                                            $status_badge_html = '
                                                <div class="mt-3 p-3 bg-green-50 rounded-lg border-2 border-green-300">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-unlock text-green-600 text-2xl mr-3"></i>
                                                        <div>
                                                            <p class="font-bold text-green-800 text-sm">Exam Available</p>
                                                            <p class="text-xs text-gray-600">Ready to take anytime</p>
                                                        </div>
                                                    </div>
                                                </div>';
                                        }
                                        break;
                                        
                                    case 'in_progress':
                                        $status_class = 'bg-orange-100 text-orange-700 pulse-animation';
                                        $status_icon = 'fa-hourglass-half';
                                        $status_text = 'In Progress';
                                        $button_class = 'bg-orange-600 hover:bg-orange-700';
                                        $button_text = 'Continue Exam';
                                        $button_icon = 'fa-arrow-right';
                                        $button_link = 'mock-exams.php?exam_id=' . $exam['id'];
                                        
                                        $status_badge_html = '
                                            <div class="mt-3 p-3 bg-orange-50 rounded-lg border-2 border-orange-300">
                                                <div class="flex items-center">
                                                    <i class="fas fa-hourglass-half pulse-animation text-orange-600 text-2xl mr-3"></i>
                                                    <div>
                                                        <p class="font-bold text-orange-800 text-sm">Exam In Progress</p>
                                                        <p class="text-xs text-gray-600">Continue where you left off</p>
                                                    </div>
                                                </div>
                                            </div>';
                                        break;

                                    case 'completed':
                                        $status_class = 'bg-purple-100 text-purple-700';
                                        $status_icon = 'fa-check-circle';
                                        $status_text = 'Completed';
                                        $button_class = 'bg-navy hover:bg-opacity-90';
                                        $button_text = 'View Results';
                                        $button_icon = 'fa-eye';
                                        $button_link = 'student_mock_exam_results.php?attempt_id=' . $exam['attempt_id'];
                                        
                                        $status_badge_html = '
                                            <div class="mt-3 p-3 bg-purple-50 rounded-lg border-2 border-purple-300">
                                                <div class="flex items-center">
                                                    <i class="fas fa-check-circle text-purple-600 text-2xl mr-3"></i>
                                                    <div>
                                                        <p class="font-bold text-purple-800 text-sm">Exam Completed</p>
                                                        <p class="text-xs text-gray-600">View your detailed results</p>
                                                    </div>
                                                </div>
                                            </div>';
                                        break;
                                }
                                ?>
                               
                                <div class="content-item border-2 border-gray-200 rounded-lg p-4 hover:border-indigo-300 transition <?php echo $exam_item_class; ?>">
                                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                        <div class="flex items-start flex-1">
                                            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                                                <i class="fas fa-file-alt text-indigo-600 text-xl"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-2 flex-wrap">
                                                    <h3 class="font-semibold text-navy text-lg"><?php echo htmlspecialchars($exam['title']); ?></h3>
                                                    <span class="inline-flex items-center gap-1 text-xs px-3 py-1 rounded-full font-semibold <?php echo $status_class; ?>">
                                                        <i class="fas <?php echo $status_icon; ?>"></i>
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </div>
                                               
                                                <?php if (!empty($exam['description'])): ?>
                                                <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars(substr($exam['description'], 0, 150)); ?><?php echo strlen($exam['description']) > 150 ? '...' : ''; ?></p>
                                                <?php endif; ?>
                                               
                                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
                                                    <div class="bg-gray-50 rounded-lg p-2">
                                                        <div class="text-xs text-gray-500 mb-1">Duration</div>
                                                        <div class="text-sm font-bold text-navy flex items-center">
                                                            <i class="fas fa-clock mr-1 text-gray-400"></i>
                                                            <?php echo $exam['duration']; ?> min
                                                        </div>
                                                    </div>
                                                    <div class="bg-gray-50 rounded-lg p-2">
                                                        <div class="text-xs text-gray-500 mb-1">Questions</div>
                                                        <div class="text-sm font-bold text-navy flex items-center">
                                                            <i class="fas fa-list mr-1 text-gray-400"></i>
                                                            <?php echo $exam['total_questions']; ?>
                                                        </div>
                                                    </div>
                                                    <div class="bg-gray-50 rounded-lg p-2">
                                                        <div class="text-xs text-gray-500 mb-1">Marking</div>
                                                        <div class="text-sm font-bold text-navy text-xs">
                                                            <?php echo $exam['marking_type'] === 'auto' ? 'Automatic' : 'Teacher Marked'; ?>
                                                        </div>
                                                    </div>
                                                    <div class="bg-gray-50 rounded-lg p-2">
                                                        <div class="text-xs text-gray-500 mb-1"><?php echo $exam['exam_status'] === 'completed' ? 'Completed' : 'Available'; ?></div>
                                                        <div class="text-xs font-bold text-navy">
                                                            <?php echo $exam['formatted_date']; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!empty($status_badge_html)): ?>
                                                    <?php echo $status_badge_html; ?>
                                                <?php endif; ?>
                                               
                                                <?php if ($exam['exam_status'] === 'completed'): ?>
                                                <div class="mt-3 pt-3 border-t border-gray-200">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-sm text-gray-600">Your Score:</span>
                                                        <span class="text-lg font-bold <?php echo $exam['percentage'] >= 70 ? 'text-green-600' : ($exam['percentage'] >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                                            <?php echo number_format($exam['percentage'], 1); ?>%
                                                        </span>
                                                    </div>
                                                    <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden mt-2">
                                                        <div class="progress-bar h-full <?php echo $exam['percentage'] >= 70 ? 'bg-green-500' : ($exam['percentage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500'); ?> rounded-full"
                                                             style="--progress-width: <?php echo $exam['percentage']; ?>%; width: <?php echo $exam['percentage']; ?>%;">
                                                        </div>
                                                    </div>
                                                    <p class="text-xs text-gray-500 mt-2 italic">
                                                        <i class="fas fa-info-circle mr-1"></i>No retakes allowed - results are final
                                                    </p>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                       
                                        <div class="flex flex-col gap-2 lg:ml-4">
                                            <?php if ($button_disabled): ?>
                                                <button disabled class="<?php echo $button_class; ?> text-white px-6 py-2 rounded-lg inline-flex items-center justify-center text-sm opacity-60">
                                                    <i class="fas <?php echo $button_icon; ?> mr-2 <?php echo $exam['exam_status'] === 'not_yet_open' ? 'lock-icon' : ''; ?>"></i><?php echo $button_text; ?>
                                                </button>
                                            <?php else: ?>
                                                <a href="<?php echo $button_link; ?>" class="<?php echo $button_class; ?> text-white px-6 py-2 rounded-lg transition inline-flex items-center justify-center text-sm">
                                                    <i class="fas <?php echo $button_icon; ?> mr-2"></i><?php echo $button_text; ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-clipboard-check text-6xl mb-4 opacity-50"></i>
                            <p class="text-lg mb-2">No mock exams available yet.</p>
                            <p class="text-sm">Check back later for upcoming exams</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
        // Mobile sidebar toggle
        document.getElementById('menuButton').addEventListener('click', function() {
            document.getElementById('sidebar').classList.add('active');
            document.getElementById('overlay').classList.add('active');
        });
        
        document.getElementById('closeSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        });
        
        document.getElementById('overlay').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        });

        // Notification dropdown toggle
        document.getElementById('notificationButton').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('hidden');
        });
        
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const button = document.getElementById('notificationButton');
            if (!dropdown.contains(e.target) && !button.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });

            // Show selected tab content
            const selectedTab = document.getElementById(tabName + '-tab');
            if (selectedTab) {
                selectedTab.classList.add('active');
            }

            // Add active class to clicked button
            event.target.closest('.tab-button').classList.add('active');

            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'lessons';
            
            // Find and click the appropriate tab button
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                const buttonText = button.textContent.toLowerCase();
                if (buttonText.includes(tab.replace('-', ' '))) {
                    button.click();
                }
            });
        });
    </script>
</body>
</html>