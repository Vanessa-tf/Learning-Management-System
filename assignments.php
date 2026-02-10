<?php
// assignments.php - Teacher Dashboard filtered by assigned subjects
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';

// Check if teacher is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// Helper function to calculate deadline urgency
function getDeadlineUrgency($deadline) {
    if (!$deadline) return null;
    
    $now = new DateTime();
    $deadlineDate = new DateTime($deadline);
    $diff = $now->diff($deadlineDate);
    
    $hoursRemaining = ($diff->days * 24) + $diff->h;
    
    if ($deadlineDate < $now) {
        return [
            'status' => 'overdue', 
            'class' => 'bg-red-100 border-red-500 text-red-800', 
            'icon' => 'exclamation-triangle', 
            'message' => 'OVERDUE'
        ];
    } elseif ($hoursRemaining <= 24) {
        return [
            'status' => 'critical', 
            'class' => 'bg-orange-100 border-orange-500 text-orange-800', 
            'icon' => 'exclamation-circle', 
            'message' => $hoursRemaining . 'h remaining'
        ];
    } elseif ($hoursRemaining <= 72) {
        return [
            'status' => 'warning', 
            'class' => 'bg-yellow-100 border-yellow-500 text-yellow-800', 
            'icon' => 'clock', 
            'message' => $diff->days . 'd ' . $diff->h . 'h remaining'
        ];
    } else {
        return [
            'status' => 'ok', 
            'class' => 'bg-green-100 border-green-500 text-green-800', 
            'icon' => 'check-circle', 
            'message' => $diff->days . ' days remaining'
        ];
    }
}

$teacher_id = $_SESSION['user_id'];

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// ====== GET TEACHER'S ASSIGNED COURSES ======
// This is the KEY part - get only the courses this teacher teaches
$teacher_courses_query = "SELECT DISTINCT course_id FROM student_teacher_assignments WHERE teacher_id = :teacher_id";
$teacher_courses_stmt = $conn->prepare($teacher_courses_query);
$teacher_courses_stmt->execute([':teacher_id' => $teacher_id]);
$teacher_course_ids = $teacher_courses_stmt->fetchAll(PDO::FETCH_COLUMN);

// If teacher has no courses assigned, show empty state
if (empty($teacher_course_ids)) {
    $teacher_course_ids = [0]; // Use 0 to ensure no results are returned
}

// Convert to comma-separated string for SQL IN clause
$course_ids_string = implode(',', array_map('intval', $teacher_course_ids));
// ====== END OF COURSE FILTER ======

// ====== DOWNLOAD HANDLER ======
if (isset($_GET['action']) && $_GET['action'] === 'download_material' && isset($_GET['id'])) {
    require_once 'models/StudyMaterial.php';
    $materialId = (int)$_GET['id'];
    $studyMaterial = new StudyMaterial();
    $material = $studyMaterial->getById($materialId);
    
    // Check if material exists, is for teachers, AND is for a course this teacher teaches
    if ($material && 
        ($material['target_audience'] === 'teachers' || $material['target_audience'] === 'both') &&
        in_array($material['course_id'], $teacher_course_ids)) {
        
        $filePath = $material['file_path'];
        $possiblePaths = [
            $filePath,
            __DIR__ . '/' . $filePath,
            __DIR__ . '/uploads/study_materials/' . $material['file_name'],
            'uploads/study_materials/' . $material['file_name']
        ];
        
        $validPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $validPath = $path;
                break;
            }
        }
        
        if ($validPath && file_exists($validPath)) {
            $studyMaterial->recordDownload($materialId, $_SESSION['user_id']);
            
            $fileExt = strtolower(pathinfo($material['file_name'], PATHINFO_EXTENSION));
            $contentTypes = [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'txt' => 'text/plain',
                'zip' => 'application/zip'
            ];
            
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            header('Content-Type: ' . ($contentTypes[$fileExt] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . basename($material['file_name']) . '"');
            header('Content-Length: ' . filesize($validPath));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Expires: 0');
            
            readfile($validPath);
            exit();
        } else {
            header('HTTP/1.0 404 Not Found');
            echo 'File not found on server.';
            exit();
        }
    } else {
        header('HTTP/1.0 404 Not Found');
        echo 'Study material not found or you do not have permission to access this file.';
        exit();
    }
}
// ====== END OF DOWNLOAD HANDLER ======

// Get teacher information
$teacher_name = 'Teacher';
$initials = 'T';
if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
    $teacher_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
    $initials = strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1));
} else {
    try {
        $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = :id AND role = 'teacher'");
        $stmt->execute([':id' => $teacher_id]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($teacher && !empty($teacher['first_name'])) {
            $teacher_name = trim($teacher['first_name'] . ' ' . ($teacher['last_name'] ?? ''));
            $initials = strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'] ?? 'T', 0, 1));
            $_SESSION['first_name'] = $teacher['first_name'];
            $_SESSION['last_name'] = $teacher['last_name'] ?? '';
        }
    } catch (Exception $e) {
        error_log("Error fetching teacher data: " . $e->getMessage());
    }
}

// Get ONLY the courses this teacher teaches
try {
    $courses_query = "SELECT * FROM courses WHERE id IN ($course_ids_string) ORDER BY course_name";
    $courses_stmt = $conn->query($courses_query);
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    $courses = [];
}

// Handle AJAX requests - ALL FILTERED BY TEACHER'S COURSES
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    try {
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        
        // Build course filter for queries
        $course_filter = "AND c.id IN ($course_ids_string)";
        if ($course_id > 0 && in_array($course_id, $teacher_course_ids)) {
            $course_filter = "AND c.id = $course_id";
        }
        
        switch ($_GET['action']) {
            case 'get_past_papers':
                $query = "SELECT ep.*, c.course_name 
                         FROM exam_papers ep
                         LEFT JOIN courses c ON ep.course_id = c.id
                         WHERE c.id IN ($course_ids_string)";
                if ($course_id > 0 && in_array($course_id, $teacher_course_ids)) {
                    $query .= " AND ep.course_id = $course_id";
                }
                $query .= " ORDER BY ep.year DESC, ep.title";
                $stmt = $conn->query($query);
                $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($papers);
                exit();
                
            case 'get_quizzes':
                $query = "SELECT cc.*, c.course_name,
                         COUNT(DISTINCT lqa.id) as attempt_count,
                         COUNT(DISTINCT lqa.user_id) as unique_students,
                         AVG(lqa.percentage) as avg_score,
                         SUM(CASE WHEN lqa.violations >= 2 THEN 1 ELSE 0 END) as high_violation_count,
                         MAX(lqa.submitted_at) as last_attempt_date
                         FROM course_content cc
                         LEFT JOIN courses c ON cc.course_id = c.id
                         LEFT JOIN lockdown_quiz_attempts lqa ON cc.id = lqa.content_id
                         WHERE cc.content_type = 'quiz' AND c.id IN ($course_ids_string)";
                if ($course_id > 0 && in_array($course_id, $teacher_course_ids)) {
                    $query .= " AND cc.course_id = $course_id";
                }
                $query .= " GROUP BY cc.id ORDER BY c.course_name, cc.title";
                $stmt = $conn->query($query);
                $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($quizzes as &$quiz) {
                    if (!empty($quiz['quiz_content'])) {
                        $questions = json_decode($quiz['quiz_content'], true);
                        $total_marks = 0;
                        if (is_array($questions)) {
                            foreach ($questions as $q) {
                                $total_marks += ($q['marks'] ?? 1);
                            }
                        }
                        $quiz['total_marks'] = $total_marks;
                    } else {
                        $quiz['total_marks'] = 0;
                    }
                }
                echo json_encode($quizzes);
                exit();
                
            case 'get_mock_exams':
                $query = "SELECT me.*, c.course_name,
                         COUNT(DISTINCT mea.id) as attempt_count,
                         COUNT(DISTINCT mea.user_id) as unique_students,
                         AVG(mea.score) as avg_score,
                         SUM(CASE WHEN mea.marking_status = 'pending' THEN 1 ELSE 0 END) as pending_marking
                         FROM mock_exams me
                         LEFT JOIN courses c ON me.course_id = c.id
                         LEFT JOIN mock_exam_attempts mea ON me.id = mea.exam_id
                         WHERE c.id IN ($course_ids_string)";
                if ($course_id > 0 && in_array($course_id, $teacher_course_ids)) {
                    $query .= " AND me.course_id = $course_id";
                }
                $query .= " GROUP BY me.id ORDER BY me.created_at DESC";
                $stmt = $conn->query($query);
                $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($exams as &$exam) {
                    $questions = !empty($exam['questions']) ? json_decode($exam['questions'], true) : [];
                    $exam['question_count'] = is_array($questions) ? count($questions) : 0;
                    
                    $total_marks = 0;
                    $mcq_count = 0;
                    $short_answer_count = 0;
                    if (is_array($questions)) {
                        foreach ($questions as $q) {
                            $total_marks += (int)($q['marks'] ?? 1);
                            if ($q['type'] === 'multiple_choice') {
                                $mcq_count++;
                            } else {
                                $short_answer_count++;
                            }
                        }
                    }
                    $exam['total_marks'] = $total_marks;
                    $exam['mcq_count'] = $mcq_count;
                    $exam['short_answer_count'] = $short_answer_count;
                }
                echo json_encode($exams);
                exit();
                
            case 'get_study_materials':
                $query = "SELECT sm.*, c.course_name
                         FROM study_materials sm
                         LEFT JOIN courses c ON sm.course_id = c.id
                         WHERE sm.status = 'published' 
                         AND (sm.target_audience = 'teachers' OR sm.target_audience = 'both')
                         AND c.id IN ($course_ids_string)";
                if ($course_id > 0 && in_array($course_id, $teacher_course_ids)) {
                    $query .= " AND sm.course_id = $course_id";
                }
                $query .= " ORDER BY sm.created_at DESC";
                $stmt = $conn->query($query);
                $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($materials as &$material) {
                    $bytes = $material['file_size'];
                    if ($bytes >= 1048576) {
                        $material['formatted_size'] = round($bytes / 1048576, 1) . ' MB';
                    } elseif ($bytes >= 1024) {
                        $material['formatted_size'] = round($bytes / 1024, 1) . ' KB';
                    } else {
                        $material['formatted_size'] = $bytes . ' B';
                    }
                }
                echo json_encode($materials);
                exit();
        }
    } catch (PDOException $e) {
        error_log("Database error in AJAX request: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
        exit();
    }
}

// Get statistics - FILTERED BY TEACHER'S COURSES
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM exam_papers WHERE course_id IN ($course_ids_string)");
    $total_papers = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM course_content WHERE content_type = 'quiz' AND course_id IN ($course_ids_string)");
    $total_quizzes = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM mock_exams WHERE is_active = 1 AND course_id IN ($course_ids_string)");
    $total_exams = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM lockdown_quiz_attempts WHERE course_id IN ($course_ids_string)");
    $total_submissions = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $total_papers = 0;
    $total_quizzes = 0;
    $total_exams = 0;
    $total_submissions = 0;
}

// Fetch mock exams with marking deadlines - FILTERED
$mock_deadline_query = "
    SELECT 
        me.id,
        me.title,
        me.marking_deadline,
        c.course_name,
        c.id as course_id,
        COUNT(DISTINCT mea.id) as pending_count
    FROM mock_exams me
    JOIN courses c ON me.course_id = c.id
    LEFT JOIN mock_exam_attempts mea ON me.id = mea.exam_id AND mea.marking_status = 'pending'
    WHERE me.marking_deadline IS NOT NULL 
    AND c.id IN ($course_ids_string)
    GROUP BY me.id, me.title, me.marking_deadline, c.course_name, c.id
    HAVING pending_count > 0
    ORDER BY me.marking_deadline ASC";

try {
    $mock_deadline_stmt = $conn->query($mock_deadline_query);
    $mocks_with_deadlines = [];

    while ($mock = $mock_deadline_stmt->fetch(PDO::FETCH_ASSOC)) {
        $mock['urgency'] = getDeadlineUrgency($mock['marking_deadline']);
        $mocks_with_deadlines[] = $mock;
    }
    
    usort($mocks_with_deadlines, function($a, $b) {
        $order = ['overdue' => 0, 'critical' => 1, 'warning' => 2, 'ok' => 3];
        return ($order[$a['urgency']['status']] ?? 999) - ($order[$b['urgency']['status']] ?? 999);
    });
} catch (PDOException $e) {
    error_log("Error fetching marking deadlines: " . $e->getMessage());
    $mocks_with_deadlines = [];
}

// Get teacher's subject names for display
$teacher_subjects = array_map(function($course) {
    return $course['course_name'];
}, $courses);
$subjects_display = !empty($teacher_subjects) ? implode(', ', $teacher_subjects) : 'No subjects assigned';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments & Assessments - NovaTech Teacher Portal</title>
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
        .dashboard-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
        .sidebar { transition: all 0.3s ease; }
        .tab-button { 
            padding: 0.75rem 1.5rem; 
            border-radius: 0.5rem; 
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .tab-button.active { 
            background: linear-gradient(135deg, var(--navy) 0%, #2563eb 100%);
            color: white;
        }
        .tab-button:not(.active):hover {
            background-color: rgba(30, 58, 108, 0.1);
        }
        .badge-excellent { background-color: #d4edda; color: #155724; }
        .badge-good { background-color: #d1ecf1; color: #0c5460; }
        .badge-average { background-color: #fff3cd; color: #856404; }
        .badge-poor { background-color: #f8d7da; color: #721c24; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
    </style>
</head>
<body class="bg-beige">
    <div class="overlay" id="overlay"></div>
    <!-- Sidebar -->
    <!-- Sidebar Navigation -->
    <div class="sidebar bg-navy text-white w-64 fixed h-screen overflow-y-auto" id="sidebar">
        <div class="p-6">
            <div class="flex items-center justify-between mb-10">
                <div class="flex items-center">
                    <img src="Images/ChatGPT Image Sep 15, 2025, 08_43_22 PM.png" alt="NovaTech Logo" class="h-10 w-auto"/>
                    <span class="ml-3 text-xl font-bold">NovaTech FET <span class="text-gold">College</span></span>
                </div>
                <button class="text-white md:hidden" id="closeSidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mb-8 p-4 bg-white bg-opacity-10 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gold rounded-full flex items-center justify-center mr-3">
                        <span class="text-navy font-bold"><?php echo $initials; ?></span>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?php echo $teacher_name; ?></h3>
                        <p class="text-gold text-sm">Teacher</p>
                    </div>
                </div>
            </div>
            <nav class="space-y-2">
                <a href="teacher_dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'teacher_dashboard.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-tachometer-alt mr-3"></i><span>Dashboard</span>
                </a>
                <a href="my-students.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'my-students.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-users mr-3"></i><span>My Students</span>
                </a>
                <a href="my-subjects.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'my-subjects.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-book mr-3"></i><span>My Subjects</span>
                </a>
				<a href="teacher-schedule.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'teacher-schedule.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-calendar mr-3"></i><span>Timetable</span>
                </a>
                <a href="teacher-live-lessons.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'teacher-live-lessons.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-video mr-3"></i><span>Live Lessons</span>
                </a>
                <a href="assignments.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white border-b-2 border-gold">
                    <i class="fas fa-check-square mr-3"></i><span>Assignments</span>
                </a>
                <a href="tutor-requests.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-chalkboard-teacher mr-3"></i><span>Tutor Requests</span>
                </a>
                <a href="teacher-messages.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'teacher-messages.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-envelope mr-3"></i><span>Messages</span>
                </a>
                <a href="teacher-settings.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'teacher-settings.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-cog mr-3"></i><span>Settings</span>
                </a>
                <a href="logout.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'logout.php' ? 'border-b-2 border-gold' : ''; ?>">
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
                    <button class="text-navy md:hidden" id="menuButton">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="text-xl font-bold text-navy">Assignments & Assessments</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="text-navy">
                                <i class="fas fa-bell"></i>
                                <span class="absolute top-[-5px] right-[-5px] w-3 h-3 bg-red-500 rounded-full"></span>
                            </button>
                        </div>
                        <div class="hidden md:block">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-2">
                                    <span class="text-navy font-bold text-sm"><?php echo htmlspecialchars($initials); ?></span>
                                </div>
                                <span class="text-navy"><?php echo htmlspecialchars(explode(' ', $teacher_name)[0]); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Dashboard Content -->
        <main class="container mx-auto px-6 py-8">
            <?php if (count($teacher_course_ids) === 1 && $teacher_course_ids[0] === 0): ?>
            <!-- No Courses Assigned Message -->
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 mb-6 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-3xl mr-4"></i>
                    <div>
                        <h3 class="font-bold text-lg">No Subjects Assigned</h3>
                        <p>You have not been assigned to teach any subjects yet. Please contact the administrator.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            
            <!-- Page Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 dashboard-card">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-navy mb-2">
                            <i class="fas fa-clipboard-list text-gold mr-2"></i>
                            My Assignments & Assessments
                        </h2>
                        <p class="text-gray-600">Manage content for: <span class="font-semibold text-gold"><?php echo htmlspecialchars($subjects_display); ?></span></p>
                    </div>
                    <button onclick="refreshData()" class="mt-4 md:mt-0 bg-gold text-navy font-semibold py-2 px-6 rounded-lg hover:bg-yellow-500 transition inline-flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i> Refresh
                    </button>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium mb-1">Past Papers</p>
                            <h3 class="text-3xl font-bold text-navy" id="totalPapers"><?php echo $total_papers; ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium mb-1">Active Quizzes</p>
                            <h3 class="text-3xl font-bold text-navy" id="totalQuizzes"><?php echo $total_quizzes; ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-question-circle text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium mb-1">Mock Exams</p>
                            <h3 class="text-3xl font-bold text-navy" id="totalExams"><?php echo $total_exams; ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clipboard-check text-red-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium mb-1">Quiz Submissions</p>
                            <h3 class="text-3xl font-bold text-navy" id="totalSubmissions"><?php echo $total_submissions; ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mock Exam Marking Deadlines -->
            <?php if (!empty($mocks_with_deadlines)): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-navy">
                        <i class="fas fa-hourglass-half text-gold mr-2"></i>
                        Mock Exam Marking Deadlines
                    </h2>
                    <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-semibold">
                        <?php echo count($mocks_with_deadlines); ?> pending
                    </span>
                </div>
                
                <div class="space-y-3">
                    <?php foreach ($mocks_with_deadlines as $mock): ?>
                    <div class="border-l-4 <?php echo $mock['urgency']['class']; ?> p-4 rounded-lg hover:shadow-md transition">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold">
                                        <i class="fas fa-file-alt mr-1"></i>MOCK EXAM
                                    </span>
                                    <h4 class="font-semibold text-navy"><?php echo htmlspecialchars($mock['title']); ?></h4>
                                </div>
                                <p class="text-sm text-gray-600 mb-2">
                                    <i class="fas fa-book mr-1"></i><?php echo htmlspecialchars($mock['course_name']); ?>
                                </p>
                                <div class="flex items-center gap-4 text-sm flex-wrap">
                                    <span>
                                        <i class="fas fa-calendar mr-1"></i>
                                        <strong>Deadline:</strong> <?php echo date('M j, Y g:i A', strtotime($mock['marking_deadline'])); ?>
                                    </span>
                                    <span class="px-2 py-1 bg-gray-100 rounded">
                                        <i class="fas fa-users mr-1"></i>
                                        <?php echo $mock['pending_count']; ?> pending
                                    </span>
                                    <span class="font-semibold">
                                        <i class="fas fa-<?php echo $mock['urgency']['icon']; ?> mr-1"></i>
                                        <?php echo $mock['urgency']['message']; ?>
                                    </span>
                                </div>
                            </div>
                            <a href="view_mock_exam_results.php?id=<?php echo $mock['id']; ?>&filter=pending" 
                               class="ml-4 bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition font-medium inline-flex items-center whitespace-nowrap">
                                <i class="fas fa-pen mr-2"></i>Grade Now
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="w-full md:w-64">
                        <label class="block text-navy font-semibold mb-2">
                            <i class="fas fa-filter mr-2"></i>Filter by Subject
                        </label>
                        <select class="w-full border border-gray-300 rounded-lg px-4 py-2 text-navy focus:outline-none focus:border-gold" id="courseFilter">
                            <option value="0">My Subjects (<?php echo count($courses); ?>)</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <a href="view_quiz_submissions.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition inline-flex items-center">
                        <i class="fas fa-eye mr-2"></i>View All Quiz Submissions (<?php echo $total_submissions; ?>)
                    </a>
                </div>
            </div>
            
            <!-- Content Tabs -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex flex-wrap gap-2 mb-6 border-b pb-4">
                    <button class="tab-button active" data-tab="papers">
                        <i class="fas fa-file-alt mr-2"></i>Past Papers
                    </button>
                    <button class="tab-button" data-tab="quizzes">
                        <i class="fas fa-question-circle mr-2"></i>Quizzes
                    </button>
                    <button class="tab-button" data-tab="exams">
                        <i class="fas fa-clipboard-check mr-2"></i>Mock Exams
                    </button>
                   
                </div>
                
                <div id="tab-content">
                    <div class="tab-pane active" id="papers-tab">
                        <div id="papersContent">
                            <div class="text-center py-8">
                                <i class="fas fa-spinner fa-spin text-gold text-3xl"></i>
                                <p class="text-gray-600 mt-2">Loading papers...</p>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane hidden" id="quizzes-tab">
                        <div id="quizzesContent">
                            <div class="text-center py-8">
                                <i class="fas fa-spinner fa-spin text-gold text-3xl"></i>
                                <p class="text-gray-600 mt-2">Loading quizzes...</p>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane hidden" id="exams-tab">
                        <div id="examsContent">
                            <div class="text-center py-8">
                                <i class="fas fa-spinner fa-spin text-gold text-3xl"></i>
                                <p class="text-gray-600 mt-2">Loading exams...</p>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane hidden" id="materials-tab">
                        <div id="materialsContent">
                            <div class="text-center py-8">
                                <i class="fas fa-spinner fa-spin text-gold text-3xl"></i>
                                <p class="text-gray-600 mt-2">Loading resources...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        // Mobile sidebar toggle
        const menuButton = document.getElementById('menuButton');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        if (menuButton && sidebar && overlay) {
            menuButton.addEventListener('click', () => {
                sidebar.classList.add('active');
                overlay.classList.add('active');
            });
            closeSidebar.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }
        
        // Tab switching
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                const tabName = button.dataset.tab;
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.add('hidden'));
                document.getElementById(tabName + '-tab').classList.remove('hidden');
            });
        });
        
        $(document).ready(function() {
            loadPastPapers();
            loadQuizzes();
            loadMockExams();
            loadStudyMaterials();
            updateStats();
            $('#courseFilter').on('change', function() {
                refreshData();
            });
        });
        
        // All the load functions remain the same - they now get filtered data from server
        function loadPastPapers() {
            const courseId = $('#courseFilter').val();
            $.ajax({
                url: 'assignments.php?action=get_past_papers&course_id=' + courseId,
                type: 'GET',
                dataType: 'json',
                success: function(papers) {
                    if (papers.length === 0) {
                        $('#papersContent').html('<div class="text-center py-12"><i class="fas fa-file-alt text-gray-400 text-6xl mb-4"></i><h4 class="text-gray-600 text-lg">No Past Papers Found</h4><p class="text-gray-500">No past papers for your subjects yet</p></div>');
                        return;
                    }
                    let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
                    papers.forEach(function(paper) {
                        const uploadDate = new Date(paper.uploaded_at).toLocaleDateString();
                        html += `
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-file-pdf text-red-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-navy">${paper.title}</h4>
                                            <span class="text-xs text-gray-500">${paper.course_name || 'N/A'}</span>
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">${paper.year}</span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">${paper.description || 'No description'}</p>
                                <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                                    <span><i class="fas fa-calendar mr-1"></i>${uploadDate}</span>
                                </div>
                                <div class="flex gap-2">
                                    <a href="${paper.file_link}" class="flex-1 bg-blue-500 text-white py-2 px-3 rounded-lg hover:bg-blue-600 transition text-sm text-center" target="_blank">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                    <a href="${paper.file_link}" download class="bg-green-500 text-white py-2 px-3 rounded-lg hover:bg-green-600 transition text-sm" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    $('#papersContent').html(html);
                },
                error: function() {
                    $('#papersContent').html('<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-3xl mb-2"></i><p>Error loading past papers</p></div>');
                }
            });
        }
        
        function loadQuizzes() {
            const courseId = $('#courseFilter').val();
            $.ajax({
                url: 'assignments.php?action=get_quizzes&course_id=' + courseId,
                type: 'GET',
                dataType: 'json',
                success: function(quizzes) {
                    if (quizzes.length === 0) {
                        $('#quizzesContent').html('<div class="text-center py-12"><i class="fas fa-question-circle text-gray-400 text-6xl mb-4"></i><h4 class="text-gray-600 text-lg">No Quizzes Found</h4><p class="text-gray-500">No quizzes for your subjects yet</p></div>');
                        return;
                    }
                    let html = '<div class="space-y-4">';
                    quizzes.forEach(function(quiz) {
                        const avgScore = quiz.avg_score ? parseFloat(quiz.avg_score).toFixed(1) + '%' : 'N/A';
                        const performanceClass = getPerformanceClass(quiz.avg_score);
                        const hasViolations = quiz.high_violation_count > 0;
                        html += `
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h4 class="font-bold text-navy text-lg mb-1">${quiz.title}</h4>
                                        <p class="text-sm text-gray-600">${quiz.course_name || 'N/A'}</p>
                                        ${quiz.description ? `<p class="text-sm text-gray-500 mt-1">${quiz.description}</p>` : ''}
                                    </div>
                                    ${hasViolations ? '<span class="px-3 py-1 bg-orange-100 text-orange-800 text-xs rounded-full"><i class="fas fa-exclamation-triangle mr-1"></i>Security Alerts</span>' : ''}
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-3">
                                    <div class="bg-blue-50 p-2 rounded text-center">
                                        <div class="text-xs text-blue-600">Attempts</div>
                                        <div class="font-bold text-blue-900">${quiz.attempt_count || 0}</div>
                                    </div>
                                    <div class="bg-purple-50 p-2 rounded text-center">
                                        <div class="text-xs text-purple-600">Students</div>
                                        <div class="font-bold text-purple-900">${quiz.unique_students || 0}</div>
                                    </div>
                                    <div class="bg-green-50 p-2 rounded text-center">
                                        <div class="text-xs text-green-600">Avg Score</div>
                                        <div class="font-bold text-green-900">${avgScore}</div>
                                    </div>
                                    <div class="bg-orange-50 p-2 rounded text-center">
                                        <div class="text-xs text-orange-600">Violations</div>
                                        <div class="font-bold text-orange-900">${quiz.high_violation_count || 0}</div>
                                    </div>
                                    <div class="bg-gray-50 p-2 rounded text-center">
                                        <div class="text-xs text-gray-600">Total Marks</div>
                                        <div class="font-bold text-gray-900">${quiz.total_marks || 'N/A'}</div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="preview_quiz.php?id=${quiz.id}" class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition text-sm inline-flex items-center">
                                        <i class="fas fa-file-alt mr-1"></i>View Quiz
                                    </a>
                                    <a href="view_quiz_submissions.php?quiz_id=${quiz.id}" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition text-sm inline-flex items-center">
                                        <i class="fas fa-eye mr-1"></i>View Submissions (${quiz.attempt_count || 0})
                                    </a>
                                    ${hasViolations ? `<a href="view_quiz_submissions.php?quiz_id=${quiz.id}&violations=high" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition text-sm inline-flex items-center">
                                        <i class="fas fa-shield-alt mr-1"></i>Security Review
                                    </a>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    $('#quizzesContent').html(html);
                    let totalSubmissions = 0;
                    quizzes.forEach(q => totalSubmissions += parseInt(q.attempt_count || 0));
                    $('#totalSubmissions').text(totalSubmissions);
                },
                error: function() {
                    $('#quizzesContent').html('<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-3xl mb-2"></i><p>Error loading quizzes</p></div>');
                }
            });
        }
        
        function loadMockExams() {
            const courseId = $('#courseFilter').val();
            $.ajax({
                url: 'assignments.php?action=get_mock_exams&course_id=' + courseId,
                type: 'GET',
                dataType: 'json',
                success: function(exams) {
                    if (exams.length === 0) {
                        $('#examsContent').html('<div class="text-center py-12"><i class="fas fa-clipboard-check text-gray-400 text-6xl mb-4"></i><h4 class="text-gray-600 text-lg">No Mock Exams Found</h4><p class="text-gray-500">No mock exams for your subjects yet</p></div>');
                        return;
                    }
                    let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
                    exams.forEach(function(exam) {
                        const avgScore = exam.avg_score ? parseFloat(exam.avg_score).toFixed(1) + '%' : 'N/A';
                        const performanceClass = getPerformanceClass(exam.avg_score);
                        const isActive = exam.is_active == 1;
                        const hasPendingMarking = exam.pending_marking > 0;
                        html += `
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h4 class="font-bold text-navy text-lg">${exam.title}</h4>
                                        <span class="text-xs text-gray-500">${exam.course_name || 'N/A'}</span>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <span class="px-2 py-1 ${isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'} text-xs rounded-full text-center">
                                            ${isActive ? 'Active' : 'Inactive'}
                                        </span>
                                        ${hasPendingMarking ? `<span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full text-center">
                                            <i class="fas fa-clock mr-1"></i>${exam.pending_marking} Pending
                                        </span>` : ''}
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">${exam.description || 'No description'}</p>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3">
                                    <div class="bg-blue-50 p-2 rounded text-center">
                                        <div class="text-xs text-blue-600">Duration</div>
                                        <div class="font-bold text-blue-900">${exam.duration} min</div>
                                    </div>
                                    <div class="bg-purple-50 p-2 rounded text-center">
                                        <div class="text-xs text-purple-600">Questions</div>
                                        <div class="font-bold text-purple-900">${exam.question_count || 0}</div>
                                    </div>
                                    <div class="bg-green-50 p-2 rounded text-center">
                                        <div class="text-xs text-green-600">Attempts</div>
                                        <div class="font-bold text-green-900">${exam.attempt_count || 0}</div>
                                    </div>
                                    <div class="bg-gray-50 p-2 rounded text-center">
                                        <div class="text-xs text-gray-600">Total Marks</div>
                                        <div class="font-bold text-gray-900">${exam.total_marks || 0}</div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mb-3 text-xs">
                                    <div>
                                        <span class="text-gray-600">MCQ:</span>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full ml-1">${exam.mcq_count || 0}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Short Answer:</span>
                                        <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full ml-1">${exam.short_answer_count || 0}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Avg:</span>
                                        <span class="px-2 py-1 rounded-full badge-${performanceClass} ml-1">${avgScore}</span>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="preview_mock_exam.php?id=${exam.id}" class="bg-purple-500 text-white px-3 py-2 rounded-lg hover:bg-purple-600 transition text-sm inline-flex items-center">
                                        <i class="fas fa-file-alt mr-1"></i>View Exam
                                    </a>
                                    <a href="view_mock_exam_results.php?id=${exam.id}" class="bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 transition text-sm inline-flex items-center">
                                        <i class="fas fa-chart-bar mr-1"></i>View Results (${exam.attempt_count || 0})
                                    </a>
                                    ${hasPendingMarking ? `<a href="view_mock_exam_results.php?id=${exam.id}&filter=pending" class="bg-orange-500 text-white px-3 py-2 rounded-lg hover:bg-orange-600 transition text-sm inline-flex items-center">
                                        <i class="fas fa-pen mr-1"></i>Mark (${exam.pending_marking})
                                    </a>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    $('#examsContent').html(html);
                },
                error: function() {
                    $('#examsContent').html('<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-3xl mb-2"></i><p>Error loading mock exams</p></div>');
                }
            });
        }
        
        function loadStudyMaterials() {
            const courseId = $('#courseFilter').val();
            $.ajax({
                url: 'assignments.php?action=get_study_materials&course_id=' + courseId,
                type: 'GET',
                dataType: 'json',
                success: function(materials) {
                    if (materials.length === 0) {
                        $('#materialsContent').html('<div class="text-center py-12"><i class="fas fa-book-open text-gray-400 text-6xl mb-4"></i><h4 class="text-gray-600 text-lg">No Teacher Resources Found</h4><p class="text-gray-500">No resources for your subjects yet</p></div>');
                        return;
                    }
                    let html = '<div class="grid grid-cols-1 md:grid-cols-3 gap-6">';
                    materials.forEach(function(material) {
                        const uploadDate = new Date(material.created_at).toLocaleDateString();
                        const iconClass = getFileIcon(material.file_type);
                        const downloadUrl = 'assignments.php?action=download_material&id=' + material.id;
                        html += `
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition">
                                <div class="flex items-start mb-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="${iconClass}"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-navy text-sm">${material.title}</h4>
                                        <span class="text-xs text-gray-500">${material.course_name || 'N/A'}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-600 mb-2 line-clamp-2">${material.description || 'No description'}</p>
                                <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                                    <span><i class="fas fa-download mr-1"></i>${material.download_count || 0}</span>
                                    <span>${material.formatted_size}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs mb-3">
                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full">${formatCategory(material.category)}</span>
                                    <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full">
                                        <i class="fas fa-chalkboard-teacher mr-1"></i>${formatAudience(material.target_audience)}
                                    </span>
                                </div>
                                <a href="${downloadUrl}" class="block bg-green-500 text-white py-2 px-3 rounded-lg hover:bg-green-600 transition text-sm text-center">
                                    <i class="fas fa-download mr-1"></i>Download
                                </a>
                            </div>
                        `;
                    });
                    html += '</div>';
                    $('#materialsContent').html(html);
                },
                error: function() {
                    $('#materialsContent').html('<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-circle text-3xl mb-2"></i><p>Error loading study materials</p></div>');
                }
            });
        }
        
        function getPerformanceClass(score) {
            if (!score) return 'poor';
            const numScore = parseFloat(score);
            if (numScore >= 75) return 'excellent';
            if (numScore >= 60) return 'good';
            if (numScore >= 50) return 'average';
            return 'poor';
        }
        
        function getFileIcon(fileType) {
            const type = fileType.toLowerCase();
            if (type === 'pdf') return 'fas fa-file-pdf text-red-600';
            if (type === 'doc' || type === 'docx') return 'fas fa-file-word text-blue-600';
            if (type === 'ppt' || type === 'pptx') return 'fas fa-file-powerpoint text-orange-600';
            if (type === 'xls' || type === 'xlsx') return 'fas fa-file-excel text-green-600';
            return 'fas fa-file text-gray-600';
        }
        
        function formatCategory(category) {
            return category.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }
        
        function formatAudience(audience) {
            if (audience === 'teachers') return 'Teachers';
            if (audience === 'both') return 'All';
            return 'Students';
        }
        
        function updateStats() {
            $.when(
                $.get('assignments.php?action=get_past_papers'),
                $.get('assignments.php?action=get_quizzes'),
                $.get('assignments.php?action=get_mock_exams')
            ).done(function(papers, quizzes, exams) {
                $('#totalPapers').text(papers[0].length);
                $('#totalQuizzes').text(quizzes[0].length);
                $('#totalExams').text(exams[0].filter(e => e.is_active == 1).length);
            });
        }
        
        function refreshData() {
            loadPastPapers();
            loadQuizzes();
            loadMockExams();
            loadStudyMaterials();
            updateStats();
        }
    </script>
</body>
</html>