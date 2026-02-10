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

// Fetch parent details from financiers table
$parent_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT f.*, s.first_name as student_first, s.surname as student_surname, s.financier_name, s.financier_relationship 
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

// Fetch child/children linked to this parent (from students table via financiers)
$stmt = $pdo->prepare("SELECT s.id, s.first_name, s.surname, s.email 
                       FROM students s 
                       INNER JOIN financiers f ON s.id = f.student_id 
                       WHERE f.id = :parent_id");
$stmt->execute(['parent_id' => $parent_id]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected child (if any)
$selected_child_id = isset($_GET['child_id']) ? $_GET['child_id'] : (isset($children[0]['id']) ? $children[0]['id'] : null);

// If no children linked, show message
if (empty($children)) {
    $no_children = true;
} else {
    $no_children = false;
    
    // Find selected child details
    $selected_child = null;
    foreach ($children as $child) {
        if ($child['id'] == $selected_child_id) {
            $selected_child = $child;
            break;
        }
    }
    
    // If no child selected, default to first child
    if (!$selected_child) {
        $selected_child = $children[0];
        $selected_child_id = $selected_child['id'];
    }
    
    $child_name = htmlspecialchars($selected_child['first_name'] . ' ' . $selected_child['surname']);
    
    // Get the subjects the child is enrolled in
    $stmt = $pdo->prepare("
        SELECT c.id, c.course_name 
        FROM enrollments e 
        INNER JOIN courses c ON e.course_id = c.id 
        WHERE e.user_id = :child_id
    ");
    $stmt->execute(['child_id' => $selected_child_id]);
    $enrolled_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch exam results for the selected child from mock_exam_attempts
    $stmt = $pdo->prepare("
        SELECT 
            mea.id,
            mea.score,
            me.title,
            c.course_name as subject,
            mea.completed_at as date_taken,
            'Mock Exam' as exam_type,
            100 as total_marks,
            (mea.score) as percentage,
           CASE 
    WHEN mea.score >= 80 THEN 'A'
    WHEN mea.score >= 40 THEN 'P'
    ELSE 'F'
END as grade,
            '' as comments
        FROM mock_exam_attempts mea
        INNER JOIN mock_exams me ON mea.exam_id = me.id
        INNER JOIN courses c ON me.course_id = c.id
        WHERE mea.user_id = :child_id
        ORDER BY mea.completed_at DESC
    ");
    $stmt->execute(['child_id' => $selected_child_id]);
    $exam_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all available subjects from exam results AND enrolled subjects for filter
    $subjects_from_results = [];
    foreach ($exam_results as $result) {
        if (!in_array($result['subject'], $subjects_from_results)) {
            $subjects_from_results[] = $result['subject'];
        }
    }
    
    // Also include enrolled subjects that might not have exam results yet
    foreach ($enrolled_subjects as $subject) {
        if (!in_array($subject['course_name'], $subjects_from_results)) {
            $subjects_from_results[] = $subject['course_name'];
        }
    }
    
    // Filter results by subject if requested
    $selected_subject = isset($_GET['subject']) ? $_GET['subject'] : 'all';
    if ($selected_subject != 'all') {
        $filtered_results = [];
        foreach ($exam_results as $result) {
            if ($result['subject'] == $selected_subject) {
                $filtered_results[] = $result;
            }
        }
        $exam_results = $filtered_results;
    }
    
    // Calculate statistics - fix lowest score calculation
    $total_exams = count($exam_results);
    $average_score = 0;
    $highest_score = 0;
    $lowest_score = 0;
    
    if ($total_exams > 0) {
        $sum = 0;
        $lowest_score = 100; // Start with maximum possible score
        
        foreach ($exam_results as $result) {
            $score = floatval($result['percentage']);
            $sum += $score;
            
            if ($score > $highest_score) {
                $highest_score = $score;
            }
            
            if ($score < $lowest_score) {
                $lowest_score = $score;
            }
        }
        
        $average_score = round($sum / $total_exams, 1);
        
        // If no exams were found after filtering, reset lowest score
        if ($total_exams === 0) {
            $lowest_score = 0;
        }
    }
} // THIS CLOSING BRACE WAS MISSING - ADDED TO FIX THE ERROR

// Prepare data for performance trend chart - FIXED VERSION
$chart_data = [];
if ($total_exams > 0) {
    // Instead of multi-line, let's use a bar chart showing performance by subject and date
    $subject_performance = [];
    
    // Organize data by subject and date
    foreach ($exam_results as $result) {
        $subject = $result['subject'];
        $date = date('M j', strtotime($result['date_taken']));
        $score = floatval($result['percentage']);
        
        if (!isset($subject_performance[$subject])) {
            $subject_performance[$subject] = [];
        }
        
        $subject_performance[$subject][$date] = $score;
    }
    
    // Get all unique dates across all subjects
    $all_dates = [];
    foreach ($exam_results as $result) {
        $date = date('M j', strtotime($result['date_taken']));
        if (!in_array($date, $all_dates)) {
            $all_dates[] = $date;
        }
    }
    
    // Sort dates chronologically
    usort($all_dates, function($a, $b) {
        return strtotime($a) - strtotime($b);
    });
    
    // Prepare datasets for each subject
    $colors = ['#fbbf24', '#1e3a6c', '#10b981', '#ef4444', '#8b5cf6', '#f97316', '#06b6d4'];
    $color_index = 0;
    
    foreach ($subject_performance as $subject => $scores) {
        $dataset = [
            'label' => $subject,
            'data' => [],
            'backgroundColor' => $colors[$color_index % count($colors)],
            'borderColor' => $colors[$color_index % count($colors)],
            'borderWidth' => 1
        ];
        
        foreach ($all_dates as $date) {
            $dataset['data'][] = isset($scores[$date]) ? $scores[$date] : null;
        }
        
        $chart_data[] = $dataset;
        $color_index++;
    }
    
    $chart_labels = $all_dates;
}

// Define grade colors
$grade_colors = [
    'A' => 'text-green-600 bg-green-100',
    'P' => 'text-blue-600 bg-blue-100',
    'F' => 'text-red-600 bg-red-100'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results - NovaTech FET College</title>
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
        .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
        .sidebar { transition: all 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
        .progress-bar { transition: width 1s ease-in-out; }
        .notification-dot { position: absolute; top: -5px; right: -5px; width: 12px; height: 12px; background-color: #ef4444; border-radius: 50%; }
        .child-selector { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .child-selector.active { max-height: 200px; }
        .chapter-list { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .chapter-list.active { max-height: 500px; }
        .grade-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: bold;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="bg-beige">
    <!-- Overlay for mobile sidebar -->
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
                
                <?php if (!$no_children && count($children) > 1): ?>
                <!-- Child Selector -->
                <div class="mt-4">
                    <button class="flex items-center justify-between w-full text-left text-sm text-white bg-white bg-opacity-10 rounded p-2" id="childSelector">
                        <span>Viewing: <?php echo $child_name; ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div id="childList" class="child-selector bg-white bg-opacity-5 rounded mt-2">
                        <?php foreach ($children as $child): 
                            $child_full_name = htmlspecialchars($child['first_name'] . ' ' . $child['surname']);
                        ?>
                        <a href="exam-results.php?child_id=<?php echo $child['id']; ?>" class="block w-full text-left text-sm text-white hover:bg-white hover:bg-opacity-10 p-2 rounded child-option">
                            <?php echo $child_full_name; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
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
        <!-- Top Navigation -->
        <header class="bg-white shadow-md">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <button class="text-navy md:hidden" id="menuButton"><i class="fas fa-bars"></i></button>
                    <h1 class="text-xl font-bold text-navy">Exam Results</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button id="notificationButton" class="text-navy relative">
                                <i class="fas fa-bell"></i>
                                <span id="notificationDot" class="notification-dot hidden"></span>
                            </button>
                            <!-- Notification Dropdown -->
                            <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto">
                                <div class="p-4 border-b">
                                    <h3 class="text-lg font-semibold text-navy">Notifications</h3>
                                </div>
                                <div id="notificationList" class="divide-y">
                                    <!-- Notifications will be injected here -->
                                </div>
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
                                <span class="text-navy"><?php echo htmlspecialchars($parent_name); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="container mx-auto px-6 py-8">
            <?php if ($no_children): ?>
            <!-- No Children Linked -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-8 text-center">
                <i class="fas fa-user-plus text-6xl text-gold mb-4"></i>
                <h2 class="text-2xl font-bold text-navy mb-4">No Children Linked</h2>
                <p class="text-gray-600 mb-6">Your parent account is not currently linked to any student accounts. Please contact the school administrator to link your child's account.</p>
                <a href="#" class="bg-navy text-white font-bold py-2 px-6 rounded-lg hover:bg-opacity-90 transition">Contact Administrator</a>
            </div>
            <?php else: ?>
            
            <!-- Page Header -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 dashboard-card">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-navy mb-2">Exam Results: <?php echo $child_name; ?></h2>
                        <p class="text-gray-600">View performance across all subjects and exams</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <form method="GET" class="flex items-center">
                            <input type="hidden" name="child_id" value="<?php echo $selected_child_id; ?>">
                            <label for="subject" class="mr-2 text-gray-700">Filter by Subject:</label>
                            <select name="subject" id="subject" onchange="this.form.submit()" class="border border-gray-300 rounded-md p-2">
                                <option value="all" <?php echo $selected_subject == 'all' ? 'selected' : ''; ?>>All Subjects</option>
                                <?php foreach ($subjects_from_results as $subject): ?>
                                <option value="<?php echo htmlspecialchars($subject); ?>" <?php echo $selected_subject == $subject ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Performance Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chart-line text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Average Score</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo $average_score; ?>%</p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-trophy text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Highest Score</h3>
                    <p class="text-3xl font-bold text-green-600"><?php echo $highest_score; ?>%</p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Lowest Score</h3>
                    <p class="text-3xl font-bold text-red-600"><?php echo $lowest_score; ?>%</p>
                </div>
            </div>
<!-- Pass Code System Explanation -->
            <div class="bg-white rounded-xl shadow-lg p-4 dashboard-card mb-6">
                <h2 class="text-lg font-bold text-navy mb-4">Grading System</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center p-4 border border-green-200 rounded-lg bg-green-50">
                        <div class="pass-code text-green-600 bg-green-100 mx-auto mb-2">A</div>
                        <h3 class="font-semibold text-green-700">Achieved</h3>
                        <p class="text-sm text-gray-600">80% and above</p>
                        <p class="text-xs text-gray-500 mt-1">Excellent performance</p>
                    </div>
                    <div class="text-center p-4 border border-blue-200 rounded-lg bg-blue-50">
                        <div class="pass-code text-blue-600 bg-blue-100 mx-auto mb-2">P</div>
                        <h3 class="font-semibold text-blue-700">Passed</h3>
                        <p class="text-sm text-gray-600">40% - 79%</p>
                        <p class="text-xs text-gray-500 mt-1">Satisfactory performance</p>
                    </div>
                    <div class="text-center p-4 border border-red-200 rounded-lg bg-red-50">
                        <div class="pass-code text-red-600 bg-red-100 mx-auto mb-2">F</div>
                        <h3 class="font-semibold text-red-700">Failed</h3>
                        <p class="text-sm text-gray-600">Below 40%</p>
                        <p class="text-xs text-gray-500 mt-1">Needs improvement</p>
                    </div>
                </div>
            </div>

            <!-- Exam Results Table -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 dashboard-card">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-navy">Exam Results</h2>
                    <span class="text-sm text-gray-600"><?php echo $total_exams; ?> exams recorded</span>
                </div>
                
                <?php if ($total_exams > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exam Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comments</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($exam_results as $result): 
                                $grade = $result['grade'];
                                $grade_class = isset($grade_colors[$grade]) ? $grade_colors[$grade] : 'bg-gray-100 text-gray-800';
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-navy"><?php echo htmlspecialchars($result['subject']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($result['exam_type']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($result['title']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($result['date_taken'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo $result['score']; ?>/<?php echo $result['total_marks']; ?> 
                                        (<?php echo $result['percentage']; ?>%)
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="grade-badge <?php echo $grade_class; ?>">
                                        <?php echo $grade; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo !empty($result['comments']) ? htmlspecialchars($result['comments']) : 'No comments'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600">
                        <?php if ($selected_subject != 'all'): ?>
                        No exam results found for <?php echo $child_name; ?> in <?php echo htmlspecialchars($selected_subject); ?>.
                        <?php else: ?>
                        No exam results found for <?php echo $child_name; ?>.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Performance Chart -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 dashboard-card">
                <h2 class="text-xl font-bold text-navy mb-6">Performance Trend</h2>
                <div class="h-96">
                    <canvas id="performanceChart"></canvas>
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
        
        // Child selector toggle
        const childSelector = document.getElementById('childSelector');
        if (childSelector) {
            childSelector.addEventListener('click', () => {
                const childList = document.getElementById('childList');
                childList.classList.toggle('active');
                
                const icon = childSelector.querySelector('i');
                if (childList.classList.contains('active')) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            });
        }
		
		// Performance Chart - IMPROVED VERSION
		<?php if (!$no_children && $total_exams > 0 && isset($chart_data) && !empty($chart_data)): ?>
		const ctx = document.getElementById('performanceChart').getContext('2d');
		const performanceChart = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: <?php echo json_encode($chart_labels); ?>,
				datasets: <?php echo json_encode($chart_data); ?>
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					y: {
						beginAtZero: true,
						min: 0,
						max: 100,
						ticks: {
							callback: function(value) {
								return value + '%';
							}
						},
						title: {
							display: true,
							text: 'Score (%)'
						},
						grid: {
							color: 'rgba(0, 0, 0, 0.1)'
						}
					},
					x: {
						title: {
							display: true,
							text: 'Exam Date'
						},
						grid: {
							display: false
						}
					}
				},
				plugins: {
					legend: {
						display: true,
						position: 'top',
						labels: {
							boxWidth: 12,
							padding: 15
						}
					},
					tooltip: {
						callbacks: {
							label: function(context) {
								return context.dataset.label + ': ' + context.raw + '%';
							}
						}
					},
					title: {
						display: true,
						text: 'Exam Performance by Subject',
						font: {
							size: 16
						}
					}
				},
				interaction: {
					intersect: false,
					mode: 'index'
				},
				// Group bars by date (clustered bar chart)
				datasets: {
					bar: {
						barPercentage: 0.7,
						categoryPercentage: 0.8
					}
				}
			}
		});
		<?php else: ?>
		// Show message if no chart data
		document.getElementById('performanceChart').parentElement.innerHTML = `
			<div class="text-center py-8">
				<i class="fas fa-chart-line text-4xl text-gray-300 mb-4"></i>
				<p class="text-gray-600">No data available for performance trend chart.</p>
				<p class="text-sm text-gray-500 mt-2">Exam results will appear here once available.</p>
			</div>
		`;
		<?php endif; ?>

        // Notification handling
        const notificationButton = document.getElementById('notificationButton');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationList = document.getElementById('notificationList');
        const notificationDot = document.getElementById('notificationDot');

        // Toggle dropdown
        notificationButton.addEventListener('click', () => {
            notificationDropdown.classList.toggle('hidden');
            // Mark notifications as read when dropdown is opened
            if (!notificationDropdown.classList.contains('hidden')) {
                markNotificationsAsRead();
            }
        });

        // Fetch notifications
        function fetchNotifications() {
            fetch('notifications_api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching notifications:', data.error);
                        return;
                    }
                    notificationList.innerHTML = '';
                    let unreadCount = 0;

                    if (data.length === 0) {
                        notificationList.innerHTML = '<p class="p-4 text-sm text-gray-600">No notifications</p>';
                    } else {
                        data.forEach(notification => {
                            if (!notification.is_read) unreadCount++;
                            const notificationItem = document.createElement('div');
                            notificationItem.className = 'p-4 hover:bg-gray-50 cursor-pointer';
                            notificationItem.innerHTML = `
                                <div class="flex items-start">
                                    <i class="fas fa-${getNotificationIcon(notification.type)} text-${getNotificationColor(notification.type)} mr-3 mt-1"></i>
                                    <div>
                                        <p class="text-sm text-gray-800">${notification.message}</p>
                                        <p class="text-xs text-gray-500">${new Date(notification.created_at).toLocaleString()}</p>
                                    </div>
                                </div>
                            `;
                            notificationList.appendChild(notificationItem);
                        });
                    }

                    // Update notification dot visibility
                    notificationDot.classList.toggle('hidden', unreadCount === 0);
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                });
        }

        // Map notification types to icons
        function getNotificationIcon(type) {
            const icons = {
                general: 'bell',
                assignment: 'file-alt',
                live_lesson: 'video',
                exam: 'clipboard-check'
            };
            return icons[type] || 'bell';
        }

        // Map notification types to colors
        function getNotificationColor(type) {
            const colors = {
                general: 'gray-600',
                assignment: 'blue-600',
                live_lesson: 'green-600',
                exam: 'purple-600'
            };
            return colors[type] || 'gray-600';
        }

        // Mark notifications as read
        function markNotificationsAsRead() {
            fetch('mark_notifications_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
                .then(() => {
                    notificationDot.classList.add('hidden');
                })
                .catch(error => {
                    console.error('Error marking notifications as read:', error);
                });
        }

        // Poll for notifications every 30 seconds
        fetchNotifications();
        setInterval(fetchNotifications, 30000);
    </script>
</body>
</html>