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

// Fetch parent details
$parent_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username, id_number FROM users WHERE id = :parent_id");
$stmt->execute(['parent_id' => $parent_id]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Get parent's initials for avatar
$username = htmlspecialchars($parent['username']);
$initials = strtoupper(substr($username, 0, 2));

// Fetch child/children linked to this parent
$stmt = $pdo->prepare("SELECT u.id, u.username, u.email FROM users u 
                       INNER JOIN parent_child_links pcl ON u.id = pcl.child_id 
                       WHERE pcl.parent_id = :parent_id");
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
    
    $child_name = htmlspecialchars($selected_child['username']);
    
    // Set date range for attendance (default to current month)
    $current_month = date('Y-m');
    $selected_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
    
    // Validate month format
    if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
        $selected_month = $current_month;
    }
    
    // Calculate start and end dates for the selected month
    $start_date = $selected_month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    
    // Fetch attendance records for the selected child and month
    $stmt = $pdo->prepare("
        SELECT 
            class_date, 
            status, 
            subject, 

            remarks 
        FROM attendance 
    WHERE user_id = :child_id 
    AND class_date BETWEEN :start_date AND :end_date
    ORDER BY class_date DESC
    ");
    $stmt->execute([
        'child_id' => $selected_child_id,
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate attendance statistics

    $present_count = 0;
    $absent_count = 0;
    $late_count = 0;
    $total_sessions = count($attendance_records);
    
    foreach ($attendance_records as $record) {
        if ($record['status'] == 'present') {
            $present_count++;
        } elseif ($record['status'] == 'absent') {
            $absent_count++;
        } elseif ($record['status'] == 'late') {
            $late_count++;
        }
    }

    // Calculate attendance rate
    $attendance_rate = $total_sessions > 0 ? round(($present_count / $total_sessions) * 100, 1) : 0;

    // Get list of available months with attendance data for dropdown
    $stmt = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(class_date, '%Y-%m') as month 
        FROM attendance 
    WHERE user_id = :child_id 
        ORDER BY month DESC
    ");
    $stmt->execute(['child_id' => $selected_child_id]);
    $available_months = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Define status colors
$status_colors = [
    'present' => 'bg-green-100 text-green-800',
    'absent' => 'bg-red-100 text-red-800',
    'late' => 'bg-yellow-100 text-yellow-800',
    'excused' => 'bg-blue-100 text-blue-800'
];


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - NovaTech FET College</title>
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
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: bold;
            font-size: 0.875rem;
        }
        .attendance-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }
        .calendar-day {
            padding: 0.5rem;
            border-radius: 0.25rem;
            text-align: center;
            min-height: 60px;
        }
        .day-header {
            font-weight: bold;
            background-color: #f3f4f6;
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
                        <h3 class="font-semibold"><?php echo $username; ?></h3>
                        <p class="text-gold text-sm">Parent Portal</p>
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
                        <?php foreach ($children as $child): ?>
                        <a href="attendance.php?child_id=<?php echo $child['id']; ?>" class="block w-full text-left text-sm text-white hover:bg-white hover:bg-opacity-10 p-2 rounded child-option">
                            <?php echo htmlspecialchars($child['username']); ?>
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
                <a href="child-schedule.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'child-schedule.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-3"></i><span>Class Schedule</span>
                </a>
                <a href="exam-results.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'exam-results.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-clipboard-check mr-3"></i><span>Exam Results</span>
                </a>
                <a href="attendance.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-user-check mr-3"></i><span>Attendance</span>
                </a>
                
                <a href="package-info.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'package-info.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-box mr-3"></i><span>Package Details</span>
                </a>
                <a href="settings.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'parent-settings.php' ? 'border-b-2 border-gold' : ''; ?>">
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
                    <h1 class="text-xl font-bold text-navy">Attendance Records</h1>
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
                                <span class="text-navy"><?php echo $username; ?></span>
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
                        <h2 class="text-2xl font-bold text-navy mb-2">Attendance: <?php echo $child_name; ?></h2>
                        <p class="text-gray-600">View attendance records and statistics</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <form method="GET" class="flex items-center">
                            <input type="hidden" name="child_id" value="<?php echo $selected_child_id; ?>">
                            <label for="month" class="mr-2 text-gray-700">Select Month:</label>
                            <select name="month" id="month" onchange="this.form.submit()" class="border border-gray-300 rounded-md p-2">
                                <?php foreach ($available_months as $month): ?>
                                <option value="<?php echo $month['month']; ?>" <?php echo ($selected_month == $month['month']) ? 'selected' : ''; ?>>
                                    <?php echo date('F Y', strtotime($month['month'] . '-01')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Attendance Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calendar-check text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Total Sessions</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo $total_sessions; ?></p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Present</h3>
                    <p class="text-3xl font-bold text-green-600"><?php echo $present_count; ?></p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Absent</h3>
                    <p class="text-3xl font-bold text-red-600"><?php echo $absent_count; ?></p>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Attendance Rate</h3>
                    <p class="text-3xl font-bold text-yellow-600"><?php echo $attendance_rate; ?>%</p>
                </div>
            </div>

            <!-- Attendance Chart -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 dashboard-card">
                <h2 class="text-xl font-bold text-navy mb-6">Attendance Overview</h2>
                <div class="h-64">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>

            <!-- Attendance Calendar View -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 dashboard-card">
                <h2 class="text-xl font-bold text-navy mb-6">Calendar View - <?php echo date('F Y', strtotime($start_date)); ?></h2>
                <div class="attendance-calendar mb-6">
                    <!-- Day headers -->
                    <div class="calendar-day day-header">Sun</div>
                    <div class="calendar-day day-header">Mon</div>
                    <div class="calendar-day day-header">Tue</div>
                    <div class="calendar-day day-header">Wed</div>
                    <div class="calendar-day day-header">Thu</div>
                    <div class="calendar-day day-header">Fri</div>
                    <div class="calendar-day day-header">Sat</div>
                    
                    <!-- Empty days at the start of the month -->
                    <?php
                    $first_day = date('w', strtotime($start_date));
                    for ($i = 0; $i < $first_day; $i++) {
                        echo '<div class="calendar-day bg-gray-100"></div>';
                    }
                    
                    // Days of the month
                    $days_in_month = date('t', strtotime($start_date));
                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $current_date = $selected_month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $day_attendance = array_filter($attendance_records, function($record) use ($current_date) {
                            return $record['date'] == $current_date;
                        });
                        
                        $status_class = 'bg-gray-100';
                        $status_text = '';
                        $status_icon = '';
                        
                        if (!empty($day_attendance)) {
                            $statuses = array_column($day_attendance, 'status');
                            if (in_array('absent', $statuses)) {
                                $status_class = 'bg-red-100';
                                $status_text = 'Absent';
                                $status_icon = 'times-circle';
                            } elseif (in_array('late', $statuses)) {
                                $status_class = 'bg-yellow-100';
                                $status_text = 'Late';
                                $status_icon = 'clock';
                            } else {
                                $status_class = 'bg-green-100';
                                $status_text = 'Present';
                                $status_icon = 'check-circle';
                            }
                        }
                        
                        echo "<div class='calendar-day $status_class'>";
                        echo "<div class='font-bold'>$day</div>";
                        if (!empty($status_icon)) {
                            echo "<div class='text-xs mt-1'><i class='fas fa-$status_icon mr-1'></i> $status_text</div>";
                        }
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Detailed Attendance Records -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 dashboard-card">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-navy">Detailed Records</h2>
                    <span class="text-sm text-gray-600"><?php echo count($attendance_records); ?> records found</span>
                </div>
                
                <?php if (count($attendance_records) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>

                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($attendance_records as $record): 
                                $status = $record['status'];
                                $status_class = isset($status_colors[$status]) ? $status_colors[$status] : 'bg-gray-100 text-gray-800';

                                $day_of_week = date('l', strtotime($record['date']));
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-navy"><?php echo date('M j, Y', strtotime($record['date'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $day_of_week; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $session_label; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($record['subject']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo !empty($record['remarks']) ? htmlspecialchars($record['remarks']) : '—'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600">No attendance records found for <?php echo $child_name; ?> in <?php echo date('F Y', strtotime($start_date)); ?>.</p>
                </div>
                <?php endif; ?>
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
        
        // Attendance Chart
        <?php if (!$no_children && $total_sessions > 0): ?>
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    data: [<?php echo $present_count; ?>, <?php echo $absent_count; ?>, <?php echo $late_count; ?>],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)'
                    ],
                    borderColor: [
                        'rgba(34, 197, 94, 1)',
                        'rgba(239, 68, 68, 1)',
                        'rgba(245, 158, 11, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.raw + ' sessions';
                                return label;
                            }
                        }
                    }
                }
            }
        });
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
                exam: 'clipboard-check',
                attendance: 'user-check'
            };
            return icons[type] || 'bell';
        }

        // Map notification types to colors
        function getNotificationColor(type) {
            const colors = {
                general: 'gray-600',
                assignment: 'blue-600',
                live_lesson: 'green-600',
                exam: 'purple-600',
                attendance: 'yellow-600'
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