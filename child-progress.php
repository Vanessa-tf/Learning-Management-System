<?php
session_start();
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");
// Check if user is logged in and has 'parent' role
check_session();
if ($_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit;
}
// Initialize all variables with default values
$parent_name = 'Parent';
$initials = 'P';
$children = [];
$selected_user_id = null;
$child_name = '';
$no_children = true;
$courses = [];
$exercises = [];
$mock_exams = [];
$study_sessions = [];
$attendance_records = [];
$total_exercises = 0;
$avg_score = 0;
$total_mock_exams = 0;
$avg_mock_score = 0;
$overall_progress = 0;
$error_message = '';
// Fetch parent details from financiers table
$parent_id = $_SESSION['user_id'];
try {
    // Get the financier record
    $stmt = $pdo->prepare("SELECT f.*, s.first_name as student_first, s.surname as student_surname, s.financier_name
                           FROM financiers f 
                           INNER JOIN students s ON f.student_id = s.id 
                           WHERE f.id = :parent_id");
    $stmt->execute(['parent_id' => $parent_id]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$parent) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
    // Get parent's name from the financier record's associated student's financier_name
    $parent_name = !empty($parent['financier_name']) ? $parent['financier_name'] : 'Parent';
    if (!empty($parent_name)) {
        $name_parts = explode(' ', $parent_name);
        $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : substr($parent_name, 1, 1)));
    }
    // Fetch child/children linked to this parent
    $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.surname, s.email 
                           FROM students s 
                           INNER JOIN financiers f ON s.id = f.student_id 
                           WHERE f.id = :parent_id");
    $stmt->execute(['parent_id' => $parent_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $no_children = empty($children);
    $selected_user_id = $_GET['user_id'] ?? ($children[0]['id'] ?? null);
    if (!$no_children) {
        $selected_child = array_filter($children, function($child) use ($selected_user_id) {
            return $child['id'] == $selected_user_id;
        });
        $selected_child = reset($selected_child);
        $child_name = htmlspecialchars($selected_child['first_name'] . ' ' . $selected_child['surname']);
        $user_id = $selected_child['id'];
        // Fetch enrolled courses
        $stmt = $pdo->prepare("SELECT e.course_id, e.course_name, e.progress, e.lessons_remaining, c.course_name as actual_course_name
                               FROM enrollments e 
                               JOIN courses c ON e.course_id = c.id 
                               WHERE e.user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Fetch course content (lessons/quizzes) as exercises
        $exercises = [];
        if (!empty($courses)) {
            $course_ids = array_column($courses, 'course_id');
            if (!empty($course_ids)) {
                $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
                $stmt = $pdo->prepare("SELECT cc.*, c.course_name 
                                       FROM course_content cc 
                                       JOIN courses c ON cc.course_id = c.id 
                                       WHERE cc.course_id IN ($placeholders) 
                                       AND cc.content_type IN ('lesson', 'quiz')
                                       ORDER BY cc.order_index LIMIT 10");
                $stmt->execute($course_ids);
                $content_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($content_items as $item) {
                    // For real data, we don't have a score or completed_at field in course_content.
                    // We'll need to infer completion or create a separate tracking table.
                    // For now, we'll show the content but without scores/completion dates.
                    $exercises[] = [
                        'title' => $item['title'],
                        'course_name' => $item['course_name'],
                        'score' => null, // No score available for generic course content
                        'completed_at' => null // No completion date available
                    ];
                }
            }
        }
        // Fetch mock exams attempts
        $mock_exams = [];
        $stmt = $pdo->prepare("SELECT mea.*, me.title, me.course_id, c.course_name
                               FROM mock_exam_attempts mea
                               JOIN mock_exams me ON mea.exam_id = me.id
                               JOIN courses c ON me.course_id = c.id
                               WHERE mea.user_id = :user_id
                               ORDER BY mea.completed_at DESC");
        $stmt->execute(['user_id' => $user_id]);
        $mock_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Calculate stats
        $total_exercises = count($exercises);
        $avg_score = 0;
        if (!empty($exercises)) {
            $scores = array_filter(array_column($exercises, 'score'), function($score) { return $score !== null; });
            if (!empty($scores)) {
                $avg_score = array_sum($scores) / count($scores);
            }
        }
        $total_mock_exams = count($mock_exams);
        $avg_mock_score = 0;
        if (!empty($mock_exams)) {
            $mock_scores = array_column($mock_exams, 'score');
            $avg_mock_score = array_sum($mock_scores) / count($mock_scores);
        }
        $overall_progress = empty($courses) ? 0 : array_sum(array_column($courses, 'progress')) / count($courses);
        // Study sessions - Since we don't have a dedicated table, we'll leave it empty or fetch from another source if available.
        // For now, leaving it empty as per the requirement to remove dummy data.
        $study_sessions = []; // Real data would require a new table like 'study_sessions'
        // Attendance - Fetch live lessons attended by the student
        $attendance_records = [];
        $stmt = $pdo->prepare("SELECT ll.*, c.course_name
                               FROM live_lessons ll
                               JOIN courses c ON ll.course_id = c.id
                               WHERE ll.course_id IN (SELECT course_id FROM enrollments WHERE user_id = :user_id)
                               AND ll.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                               ORDER BY ll.date DESC LIMIT 10");
        $stmt->execute(['user_id' => $user_id]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lessons as $lesson) {
            // For real attendance, we'd need a lesson_attendance table linking student_id, lesson_id, and status.
            // Since it doesn't exist, we'll assume 'Present' for simplicity, or leave it as unknown.
            // This is a limitation of the current schema.
            $attendance_records[] = [
                'lesson_name' => $lesson['lesson_name'],
                'date' => $lesson['date'],
                'start_time' => $lesson['start_time'],
                'status' => 'Unknown' // Placeholder since we lack an attendance tracking table
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Database error in child-progress.php: " . $e->getMessage());
    $error_message = "Unable to load progress data. Please try again later. Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child's Progress - NovaTech FET College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .dashboard-card:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); }
        .sidebar { transition: all 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
        .progress-bar { transition: width 1.5s ease-in-out; }
        .score-excellent { background-color: #dcfce7; color: #166534; }
        .score-good { background-color: #fef3c7; color: #92400e; }
        .score-needs-work { background-color: #fee2e2; color: #991b1b; }
        .filter-tabs { display: flex; border-bottom: 2px solid #e5e7eb; margin-bottom: 1rem; }
        .filter-tab { padding: 0.5rem 1rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s ease; }
        .filter-tab.active { border-bottom-color: var(--gold); color: var(--navy); font-weight: 600; }
        .filter-tab:hover { background-color: #f9fafb; }
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
                        <h3 class="font-semibold"><?php echo htmlspecialchars($parent_name); ?></h3>
                        <p class="text-gold text-sm">Parent Portal</p>
                        <?php if (!empty($parent['role'])): ?>
                        <p class="text-white text-xs opacity-80"><?php echo htmlspecialchars($parent['role']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <nav class="space-y-2">
                <a href="parent_dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'parent_dashboard.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-home mr-3"></i><span>Dashboard</span>
                </a>
                <a href="child-progress.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'child-progress.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-chart-line mr-3"></i><span>Child's Progress</span>
                </a>
                <a href="child-courses.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'child-courses.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-book-open mr-3"></i><span>Enrolled Subjects</span>
                </a>
                <a href="child-timetable.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'child-timetable.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-3"></i><span>Class Timetable</span>
                </a>
                <a href="exam-results.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'exam-results.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-clipboard-check mr-3"></i><span>Exam Results</span>
                </a>
				<a href="parent-messages.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'parent-messages.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-envelope mr-3"></i><span>Messages</span>
                </a>
                <a href="parent_log_case.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-user-check mr-3"></i><span>Log Cases</span>
                </a>
                
                <a href="package-info.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'package-info.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-box mr-3"></i><span>Package Details</span>
                </a>
                <a href="parent_settings.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'parent-settings.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-cog mr-3"></i><span>Settings</span>
                </a>
                <a href="logout.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-sign-out-alt mr-3"></i><span>Logout</span>
                </a>
            </nav>
        </div>
    </div>
    <!-- Main Content -->
    <div class="md:ml-64">
        <header class="bg-white shadow-md">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <button class="text-navy md:hidden" id="menuButton"><i class="fas fa-bars"></i></button>
                    <div>
                        <h1 class="text-xl font-bold text-navy">Child's Progress</h1>
                        <?php if (!$no_children): ?>
                        <p class="text-sm text-gray-600">Monitoring <?php echo $child_name; ?>'s academic performance</p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center space-x-4">
                        <?php if (count($children) > 1): ?>
                        <select id="childSelect" class="border border-gray-300 rounded px-3 py-1 text-sm">
                            <?php foreach ($children as $child): ?>
                            <option value="<?php echo $child['id']; ?>" <?php echo $child['id'] == $selected_user_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['surname']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <div class="hidden md:block">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-2">
                                    <span class="text-navy font-bold text-sm"><?php echo $initials; ?></span>
                                </div>
                                <span class="text-navy"><?php echo htmlspecialchars($parent_name); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <main class="container mx-auto px-6 py-8">
            <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-8">
                <p><?php echo $error_message; ?></p>
            </div>
            <?php endif; ?>
            <?php if ($no_children): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                <i class="fas fa-user-plus text-6xl text-gold mb-4"></i>
                <h2 class="text-2xl font-bold text-navy mb-4">No Children Linked</h2>
                <p class="text-gray-600 mb-6">Your parent account is not currently linked to any student accounts.</p>
                <a href="#" class="bg-navy text-white font-bold py-2 px-6 rounded-lg hover:bg-opacity-90 transition">Contact Administrator</a>
            </div>
            <?php else: ?>
            <!-- Progress Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-chart-line text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Overall Progress</p>
                            <p class="text-2xl font-bold text-navy"><?php echo round($overall_progress); ?>%</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-tasks text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Learning Items</p>
                            <p class="text-2xl font-bold text-navy"><?php echo $total_exercises; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-clipboard-check text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Mock Exams</p>
                            <p class="text-2xl font-bold text-navy"><?php echo $total_mock_exams; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gold bg-opacity-20 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-star text-gold text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Average Score</p>
                            <p class="text-2xl font-bold text-navy"><?php echo round($avg_score); ?>%</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Subject Progress -->
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <h2 class="text-xl font-bold text-navy mb-6">Subject Progress</h2>
                    <div class="space-y-6">
                        <?php if (!empty($courses)): ?>
                            <?php foreach ($courses as $course): ?>
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="font-semibold text-navy"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                    <span class="text-sm text-gold font-bold"><?php echo $course['progress']; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-gold h-3 rounded-full progress-bar" style="width: <?php echo $course['progress']; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Progress: <?php echo $course['progress']; ?>%</span>
                                    <span><?php echo $course['lessons_remaining']; ?> lessons remaining</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-600 text-center py-4">No enrolled courses found.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Performance Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <h2 class="text-xl font-bold text-navy mb-6">Performance Trends</h2>
                    <div class="h-64">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
            <!-- Recent Activities Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 dashboard-card">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-navy">Recent Activities</h2>
                    <div class="filter-tabs">
                        <div class="filter-tab active" data-filter="all">All</div>
                        <div class="filter-tab" data-filter="exercises">Learning Items</div>
                        <div class="filter-tab" data-filter="exams">Mock Exams</div>
                    </div>
                </div>
                <div id="exerciseActivities" class="space-y-4">
                    <h3 class="font-semibold text-navy mb-4">Recent Learning Activities</h3>
                    <?php if (empty($exercises)): ?>
                    <p class="text-gray-600 text-center py-4">No learning activities completed yet.</p>
                    <?php else: ?>
                    <?php foreach (array_slice($exercises, 0, 5) as $exercise): ?>
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:shadow-md transition">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-pencil-alt text-blue-600"></i>
                            </div>
                            <div>
                                <h4 class="font-medium text-navy"><?php echo htmlspecialchars($exercise['course_name']); ?></h4>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($exercise['title'] ?? 'Learning Activity'); ?></p>
                                <?php if ($exercise['completed_at']): ?>
                                <p class="text-xs text-gray-500"><?php echo date('M d, Y h:i A', strtotime($exercise['completed_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <?php if ($exercise['score'] !== null): ?>
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-bold <?php echo $exercise['score'] >= 80 ? 'score-excellent' : ($exercise['score'] >= 60 ? 'score-good' : 'score-needs-work'); ?>">
                                <?php echo $exercise['score']; ?>%
                            </span>
                            <?php else: ?>
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-800">N/A</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="examActivities" class="space-y-4 mt-8">
                    <h3 class="font-semibold text-navy mb-4">Recent Mock Exams</h3>
                    <?php if (empty($mock_exams)): ?>
                    <p class="text-gray-600 text-center py-4">No mock exams completed yet.</p>
                    <?php else: ?>
                    <?php foreach (array_slice($mock_exams, 0, 5) as $exam): ?>
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:shadow-md transition">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-clipboard-check text-purple-600"></i>
                            </div>
                            <div>
                                <h4 class="font-medium text-navy"><?php echo htmlspecialchars($exam['title']); ?></h4>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($exam['course_name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('M d, Y h:i A', strtotime($exam['completed_at'])); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-bold <?php echo $exam['score'] >= 80 ? 'score-excellent' : ($exam['score'] >= 60 ? 'score-good' : 'score-needs-work'); ?>">
                                <?php echo number_format($exam['score'], 1); ?>%
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Study Time and Attendance -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Study Time Tracking -->
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <h2 class="text-xl font-bold text-navy mb-6">Study Activity (Last 15 Days)</h2>
                    <?php if (empty($study_sessions)): ?>
                    <p class="text-gray-600 text-center py-8">No study activity data available.</p>
                    <?php else: ?>
                    <div class="h-48">
                        <canvas id="studyTimeChart"></canvas>
                    </div>
                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-600">
                            Total: <?php echo array_sum(array_column($study_sessions, 'daily_minutes')); ?> minutes
                            | Average: <?php echo round(array_sum(array_column($study_sessions, 'daily_minutes')) / max(1, count($study_sessions))); ?> min/day
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Recent Attendance -->
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <h2 class="text-xl font-bold text-navy mb-6">Recent Class Attendance</h2>
                    <?php if (empty($attendance_records)): ?>
                    <p class="text-gray-600 text-center py-8">No attendance records available.</p>
                    <?php else: ?>
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        <?php foreach ($attendance_records as $record): ?>
                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                            <div>
                                <h4 class="font-medium text-navy text-sm"><?php echo htmlspecialchars($record['lesson_name']); ?></h4>
                                <p class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($record['date'])); ?> at <?php echo date('h:i A', strtotime($record['start_time'])); ?></p>
                            </div>
                            <span class="inline-block px-2 py-1 rounded-full text-xs font-bold <?php echo $record['status'] == 'Present' ? 'bg-green-100 text-green-800' : ($record['status'] == 'Absent' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                <?php echo $record['status'] ?? 'Unknown'; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    <script>
        // Mobile sidebar toggle
        const menuButton = document.getElementById('menuButton');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        if (menuButton) {
            menuButton.addEventListener('click', () => {
                sidebar.classList.add('active');
                overlay.classList.add('active');
            });
        }
        if (closeSidebar) {
            closeSidebar.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }
        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }
        // Child selection dropdown
        const childSelect = document.getElementById('childSelect');
        if (childSelect) {
            childSelect.addEventListener('change', function() {
                window.location.href = 'child-progress.php?user_id=' + this.value;
            });
        }
        // Filter tabs functionality
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                const filter = this.getAttribute('data-filter');
                const exerciseDiv = document.getElementById('exerciseActivities');
                const examDiv = document.getElementById('examActivities');
                if (filter === 'all') {
                    exerciseDiv.style.display = 'block';
                    examDiv.style.display = 'block';
                } else if (filter === 'exercises') {
                    exerciseDiv.style.display = 'block';
                    examDiv.style.display = 'none';
                } else if (filter === 'exams') {
                    exerciseDiv.style.display = 'none';
                    examDiv.style.display = 'block';
                }
            });
        });
        // Animate progress bars
        document.querySelectorAll('.progress-bar').forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0';
            setTimeout(() => { bar.style.width = width; }, 500);
        });
        // Performance Chart
        <?php if (!$no_children && !empty($exercises)): ?>
        const performanceCtx = document.getElementById('performanceChart');
        if (performanceCtx) {
            <?php
            $recent_exercises = array_slice($exercises, 0, 10);
            $chartData = [];
            foreach ($recent_exercises as $exercise) {
                if ($exercise['score'] !== null && $exercise['completed_at']) {
                    $chartData[] = [
                        'x' => date('M d', strtotime($exercise['completed_at'])),
                        'y' => (float) $exercise['score']
                    ];
                }
            }
            ?>
            const exerciseData = <?php echo json_encode(array_reverse($chartData)); ?>;
            new Chart(performanceCtx.getContext('2d'), {
                type: 'line',
                data: {
                    datasets: [{
                        label: 'Activity Scores',
                        data: exerciseData,
                        borderColor: '#fbbf24',
                        backgroundColor: 'rgba(251, 191, 36, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        x: {
                            type: 'category'
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Score: ' + context.parsed.y + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>
        // Study Time Chart
        <?php if (!$no_children && !empty($study_sessions)): ?>
        const studyTimeCtx = document.getElementById('studyTimeChart');
        if (studyTimeCtx) {
            <?php
            $reversed_sessions = array_reverse($study_sessions);
            $labels = array_map(function($s) { return date('M d', strtotime($s['study_date'])); }, $reversed_sessions);
            $minutes = array_column($reversed_sessions, 'daily_minutes');
            ?>
            const studyData = {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Minutes Studied',
                    data: <?php echo json_encode($minutes); ?>,
                    backgroundColor: '#1e3a8a',
                    borderColor: '#1e40af',
                    borderWidth: 1
                }]
            };
            new Chart(studyTimeCtx.getContext('2d'), {
                type: 'bar',
                data: studyData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' min';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const hours = Math.floor(context.parsed.y / 60);
                                    const minutes = context.parsed.y % 60;
                                    return hours > 0 ? `${hours}h ${minutes}m` : `${minutes} minutes`;
                                }
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>