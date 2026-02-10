<?php
session_start();
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

// Set timezone
date_default_timezone_set('Africa/Johannesburg');

// Check if user is logged in and has 'parent' role
check_session();
if ($_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit;
}

// Fetch parent details from financiers table and get names from students table
$parent_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT f.*, s.financier_name, s.financier_relationship 
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

    // Get parent's full name from the students table (financier_name field)
    $parent_name = !empty($parent['financier_name']) ? $parent['financier_name'] : 'Parent';
    $initials = '';
    if (!empty($parent_name)) {
        $name_parts = explode(' ', $parent_name);
        $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : substr($name_parts[0], 1, 1)));
    } else {
        $initials = 'P';
    }

    // Fetch child/children linked to this parent
    $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.surname, s.email, s.package_selected
                           FROM students s 
                           INNER JOIN financiers f ON s.id = f.student_id 
                           WHERE f.id = :parent_id");
    $stmt->execute(['parent_id' => $parent_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no children linked, set flag
    if (empty($children)) {
        $no_children = true;
        $child_name = '';
        $schedule = [];
        $enrolled_courses = [];
    } else {
        $no_children = false;
        // For simplicity, focus on the first child
        $selected_child = $children[0];
        $child_id = $selected_child['id'];
        $child_name = htmlspecialchars($selected_child['first_name'] . ' ' . $selected_child['surname']);
        $child_package = $selected_child['package_selected'];

        // Fetch child's enrolled courses from enrollments table
        $stmt = $pdo->prepare("SELECT course_id, course_name FROM enrollments WHERE user_id = :child_id");
        $stmt->execute(['child_id' => $child_id]);
        $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $enrolled_course_ids = array_column($enrolled_courses, 'course_id');
        
        // Safety check for empty enrolled courses
        if (empty($enrolled_course_ids)) {
            $enrolled_course_ids = [0]; // Use 0 to avoid SQL error
            $schedule = [];
        } else {
            $placeholders = implode(',', array_fill(0, count($enrolled_course_ids), '?'));
            
            // Fetch timetable (lectures AND tutor sessions) - UPDATED to include tutor sessions
            $stmt = $pdo->prepare("
                SELECT s.course_id, s.course_name, s.day_of_week, s.start_time, s.end_time, 
                       s.event_type, s.teams_link, 'fixed' AS event_source, NULL as is_tutor_session,
                       u.first_name as teacher_first_name, u.last_name as teacher_last_name
                FROM schedules s
                LEFT JOIN student_teacher_assignments sta ON s.course_id = sta.course_id
                LEFT JOIN users u ON sta.teacher_id = u.id
                WHERE s.course_id IN ($placeholders) AND s.event_type = 'lecture'
                GROUP BY s.id
                UNION
                SELECT t.course_id, c.course_name, t.scheduled_day as day_of_week, 
                       t.scheduled_start_time as start_time, t.scheduled_end_time as end_time, 
                       'tutor' as event_type, t.teams_link, 'tutor' AS event_source, 
                       1 as is_tutor_session,
                       u.first_name as teacher_first_name, u.last_name as teacher_last_name
                FROM tutor_requests t
                JOIN courses c ON t.course_id = c.id
                LEFT JOIN users u ON t.assigned_tutor_id = u.id
                WHERE t.student_id = ? AND t.status = 'approved' AND t.course_id IN ($placeholders)
                ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time
            ");
            $stmt->execute(array_merge($enrolled_course_ids, [$child_id], $enrolled_course_ids));
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (PDOException $e) {
    error_log("Database error in child-timetable.php: " . $e->getMessage());
    $error_message = "Unable to load schedule data. Please try again later.";
}

// Function to get pastel color for course - UPDATED to include tutor sessions
function getCourseColor($course_name, $event_source) {
    if ($event_source === 'tutor') {
        return 'pastel-grey';
    }
    
    $colors = [
        'Mathematics' => 'pastel-blue',
        'CAT' => 'pastel-green', 
        'English' => 'pastel-yellow',
        'Physical Science' => 'pastel-red'
    ];
    return $colors[$course_name] ?? 'pastel-grey';
}

// Function to get border color for course - UPDATED to include tutor sessions
function getCourseBorderColor($course_name, $event_source) {
    if ($event_source === 'tutor') {
        return 'border-gray-300';
    }
    
    $colors = [
        'Mathematics' => 'border-blue-300',
        'CAT' => 'border-green-300',
        'English' => 'border-yellow-300',
        'Physical Science' => 'border-red-300'
    ];
    return $colors[$course_name] ?? 'border-gray-300';
}

// Function to get event type display name
function getEventTypeDisplay($event_source, $event_type) {
    if ($event_source === 'tutor') {
        return 'TUTOR';
    }
    return 'LECTURE';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedule - NovaTech FET College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #1e3a6c;
            --gold: #fbbf24;
            --beige: #f5f5dc;
            --pastel-blue: #dbeafe;
            --pastel-green: #dcfce7;
            --pastel-yellow: #fef9c3;
            --pastel-red: #fee2e2;
            --pastel-grey: #f3f4f6;
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
        .timetable-cell { min-height: 100px; border: 1px solid #e5e7eb; padding: 0.5rem; font-size: 0.75rem; }
        .timetable-header { background-color: var(--navy); color: white; }
        .pastel-blue { background-color: var(--pastel-blue); border-color: #93c5fd; }
        .pastel-green { background-color: var(--pastel-green); border-color: #86efac; }
        .pastel-yellow { background-color: var(--pastel-yellow); border-color: #fde047; }
        .pastel-red { background-color: var(--pastel-red); border-color: #fca5a5; }
        .pastel-grey { background-color: var(--pastel-grey); border-color: #d1d5db; }
        .progress-bar { transition: width 1s ease-in-out; }
        .notification-dot { position: absolute; top: -5px; right: -5px; width: 12px; height: 12px; background-color: #ef4444; border-radius: 50%; }
        .child-selector { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .child-selector.active { max-height: 200px; }
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
                        <?php if (!empty($parent['financier_relationship'])): ?>
                        <p class="text-white text-xs opacity-80"><?php echo htmlspecialchars($parent['financier_relationship']); ?></p>
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
                            $full_name = htmlspecialchars($child['first_name'] . ' ' . $child['surname']);
                        ?>
                        <button class="block w-full text-left text-sm text-white hover:bg-white hover:bg-opacity-10 p-2 rounded child-option" 
                                data-child-id="<?php echo $child['id']; ?>" data-child-name="<?php echo $full_name; ?>">
                            <?php echo $full_name; ?>
                        </button>
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
                    <h1 class="text-xl font-bold text-navy">Class Timetable</h1>
                    <div class="flex items-center space-x-4">
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

        <!-- Page Content -->
        <main class="container mx-auto px-6 py-8">
            <?php if (isset($error_message)): ?>
            <!-- Error Message -->
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-8">
                <p><?php echo $error_message; ?></p>
            </div>
            <?php endif; ?>

            <?php if ($no_children): ?>
            <!-- No Children Linked -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-8 text-center">
                <i class="fas fa-user-plus text-6xl text-gold mb-4"></i>
                <h2 class="text-2xl font-bold text-navy mb-4">No Children Linked</h2>
                <p class="text-gray-600 mb-6">Your parent account is not currently linked to any student accounts. Please contact the school administrator to link your child's account.</p>
                <a href="contact.php" class="bg-navy text-white font-bold py-2 px-6 rounded-lg hover:bg-opacity-90 transition">Contact Administrator</a>
            </div>
            <?php else: ?>

            <!-- Header Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex flex-col lg:flex-row items-start justify-between mb-6">
                    <div class="mb-4 lg:mb-0">
                        <h2 class="text-2xl font-bold text-navy mb-2"><?php echo $child_name; ?>'s Class Timetable</h2>
                        <p class="text-gray-600">
                            <?php echo $child_package; ?> Package • 
                            Viewing weekly lecture schedule for enrolled subjects
                        </p>
                    </div>
                    <div class="text-sm text-gray-600">
                        Total: <?php echo count($schedule); ?> sessions scheduled
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-day text-blue-500 text-2xl mr-3"></i>
                            <div>
                                <p class="text-blue-700 font-bold text-xl"><?php echo count($schedule); ?></p>
                                <p class="text-blue-600 text-sm">Weekly Sessions</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-book text-green-500 text-2xl mr-3"></i>
                            <div>
                                <p class="text-green-700 font-bold text-xl"><?php echo count($enrolled_courses); ?></p>
                                <p class="text-green-600 text-sm">Enrolled Subjects</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-chalkboard-teacher text-purple-500 text-2xl mr-3"></i>
                            <div>
                                <p class="text-purple-700 font-bold text-xl"><?php echo $child_package; ?></p>
                                <p class="text-purple-600 text-sm">Package Level</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-user-graduate text-gray-500 text-2xl mr-3"></i>
                            <div>
                                <?php 
                                $tutor_sessions = array_filter($schedule, function($event) {
                                    return $event['event_source'] === 'tutor';
                                });
                                ?>
                                <p class="text-gray-700 font-bold text-xl"><?php echo count($tutor_sessions); ?></p>
                                <p class="text-gray-600 text-sm">Tutor Sessions</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekly Timetable - UPDATED to include tutor sessions -->
            <div class="bg-white rounded-xl shadow-lg p-4 dashboard-card mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-navy">Weekly Schedule</h2>
                </div>
                
                <?php if (empty($enrolled_courses)): ?>
                    <p class="text-gray-600 text-sm">No courses enrolled. Please enroll in courses to see the timetable.</p>
                <?php elseif (empty($schedule)): ?>
                    <p class="text-gray-600 text-sm">No timetable entries available for enrolled subjects (<?php echo implode(', ', array_column($enrolled_courses, 'course_name')); ?>).</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-200">
                            <thead>
                                <tr class="timetable-header">
                                    <th class="timetable-cell text-center py-2">Time</th>
                                    <th class="timetable-cell text-center">Monday</th>
                                    <th class="timetable-cell text-center">Tuesday</th>
                                    <th class="timetable-cell text-center">Wednesday</th>
                                    <th class="timetable-cell text-center">Thursday</th>
                                    <th class="timetable-cell text-center">Friday</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $time_slots = [
                                    '08:00' => '08:00 - 09:30',
                                    '09:00' => '09:00 - 10:30',
                                    '10:00' => '10:00 - 11:30', 
                                    '11:00' => '11:00 - 12:30',
                                    '13:00' => '13:00 - 14:30',
                                    '14:00' => '14:00 - 15:30'
                                ];
                                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                                
                                foreach ($time_slots as $start_time => $time_range): ?>
                                <tr>
                                    <td class="timetable-cell border-r text-center font-semibold bg-gray-50"><?php echo $time_range; ?></td>
                                    <?php foreach ($days as $day): ?>
                                        <td class="timetable-cell">
                                            <?php foreach ($schedule as $event): ?>
                                                <?php if ($event['day_of_week'] == $day && date('H:i', strtotime($event['start_time'])) == $start_time): ?>
                                                    <?php
                                                    $color_class = getCourseColor($event['course_name'], $event['event_source']);
                                                    $border_class = getCourseBorderColor($event['course_name'], $event['event_source']);
                                                    $event_type_display = getEventTypeDisplay($event['event_source'], $event['event_type']);
                                                    ?>
                                                    <div class="mb-2 p-2 rounded border-l-4 <?php echo $color_class; ?> <?php echo $border_class; ?>">
                                                        <strong><?php echo htmlspecialchars($event['course_name']); ?></strong><br>
                                                        <small class="font-medium"><?php echo $event_type_display; ?></small><br>
                                                        <?php if ($event['teacher_first_name']): ?>
                                                            <small>with <?php echo htmlspecialchars($event['teacher_first_name'] . ' ' . $event['teacher_last_name']); ?></small><br>
                                                        <?php endif; ?>
                                                        <small><?php echo date('h:i A', strtotime($event['start_time'])) . ' - ' . date('h:i A', strtotime($event['end_time'])); ?></small>
                                                        <!-- Teams link REMOVED for parents -->
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Classes Section -->
            <div class="bg-white rounded-xl shadow-lg p-4 dashboard-card">
                <h2 class="text-lg font-bold text-navy mb-4">Upcoming Sessions This Week</h2>
                <?php
                $today = date('l');
                $upcoming_classes = array_filter($schedule, function($event) use ($today) {
                    $event_day_index = array_search($event['day_of_week'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
                    $today_index = array_search($today, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
                    return $event_day_index >= $today_index;
                });
                
                if (empty($upcoming_classes)): ?>
                    <p class="text-gray-600 text-sm">No upcoming sessions this week.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($upcoming_classes as $class): ?>
                            <?php
                            $color_class = getCourseColor($class['course_name'], $class['event_source']);
                            $border_class = getCourseBorderColor($class['course_name'], $class['event_source']);
                            $event_type_display = getEventTypeDisplay($class['event_source'], $class['event_type']);
                            ?>
                            <div class="border-l-4 pl-4 py-3 <?php echo $color_class; ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-1">
                                            <span class="font-semibold text-navy mr-2"><?php echo htmlspecialchars($class['course_name']); ?></span>
                                            <span class="text-xs bg-white px-2 py-1 rounded border <?php echo $border_class; ?>">
                                                <?php echo $event_type_display; ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-calendar-day mr-1"></i><?php echo $class['day_of_week']; ?> • 
                                            <i class="fas fa-clock mr-1"></i><?php echo date('h:i A', strtotime($class['start_time'])) . ' - ' . date('h:i A', strtotime($class['end_time'])); ?>
                                        </p>
                                        <?php if ($class['teacher_first_name']): ?>
                                            <p class="text-xs text-gray-500">
                                                <i class="fas fa-user mr-1"></i>with <?php echo htmlspecialchars($class['teacher_first_name'] . ' ' . $class['teacher_last_name']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

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
            this.classList.remove('active');
        });

        // Child selector toggle
        const childSelector = document.getElementById('childSelector');
        if (childSelector) {
            childSelector.addEventListener('click', function() {
                const childList = document.getElementById('childList');
                childList.classList.toggle('active');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-up');
            });

            // Child selection
            document.querySelectorAll('.child-option').forEach(option => {
                option.addEventListener('click', function() {
                    const childId = this.getAttribute('data-child-id');
                    const childName = this.getAttribute('data-child-name');
                    
                    // Update the selector display
                    childSelector.querySelector('span').textContent = 'Viewing: ' + childName;
                    
                    // Close the dropdown
                    document.getElementById('childList').classList.remove('active');
                    childSelector.querySelector('i').classList.remove('fa-chevron-up');
                    childSelector.querySelector('i').classList.add('fa-chevron-down');
                    
                    // Reload page with new child
                    window.location.href = 'child-timetable.php?child_id=' + childId;
                });
            });
        }

        // Auto-hide dropdown when clicking elsewhere
        document.addEventListener('click', function(event) {
            const childSelector = document.getElementById('childSelector');
            const childList = document.getElementById('childList');
            
            if (childSelector && childList && !childSelector.contains(event.target) && !childList.contains(event.target)) {
                childList.classList.remove('active');
                const icon = childSelector.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            }
        });

        // Refresh timetable every 5 minutes to get updates
        setInterval(function() {
            console.log('Timetable auto-refresh check - ' + new Date().toLocaleTimeString());
        }, 300000); // 5 minutes
    </script>
</body>
</html>