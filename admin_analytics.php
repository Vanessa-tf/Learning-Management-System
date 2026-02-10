<?php
// session_start(); // Commented out for now
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Hardcode admin_id for testing without login
$admin_id = 1; // Assuming admin ID 1 exists; adjust as needed

$success_message = '';
$error_message = '';

// Fetch statistics
$stats = [];
try {
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) as total_students FROM students");
    $stats['total_students'] = $stmt->fetchColumn();

    // Total teachers
    $stmt = $pdo->query("SELECT COUNT(*) as total_teachers FROM users WHERE role = 'teacher'");
    $stats['total_teachers'] = $stmt->fetchColumn();

    // Total parents/financiers
    $stmt = $pdo->query("SELECT COUNT(*) as total_parents FROM financiers WHERE role = 'Parent'");
    $stats['total_parents'] = $stmt->fetchColumn();

    // Total revenue (lifetime)
    $stmt = $pdo->query("SELECT SUM(amount) as total_revenue FROM payments WHERE status = 'Completed'");
    $stats['total_revenue'] = $stmt->fetchColumn() ?? 0;

    // Active subscriptions
    $stmt = $pdo->query("SELECT COUNT(*) as active_subscriptions FROM students WHERE subscription_status = 'active'");
    $stats['active_subscriptions'] = $stmt->fetchColumn();

    // Expired subscriptions
    $stmt = $pdo->query("SELECT COUNT(*) as expired_subscriptions FROM students WHERE subscription_status = 'expired'");
    $stats['expired_subscriptions'] = $stmt->fetchColumn();

    // Total courses
    $stmt = $pdo->query("SELECT COUNT(*) as total_courses FROM courses");
    $stats['total_courses'] = $stmt->fetchColumn();

    // Total announcements
    $stmt = $pdo->query("SELECT COUNT(*) as total_announcements FROM announcements");
    $stats['total_announcements'] = $stmt->fetchColumn();

    // Total study groups
    $stmt = $pdo->query("SELECT COUNT(*) as total_study_groups FROM study_groups");
    $stats['total_study_groups'] = $stmt->fetchColumn();

    // Total live lessons
    $stmt = $pdo->query("SELECT COUNT(*) as total_live_lessons FROM live_lessons");
    $stats['total_live_lessons'] = $stmt->fetchColumn();

    // Total quiz attempts
    $stmt = $pdo->query("SELECT COUNT(*) as total_quiz_attempts FROM lockdown_quiz_attempts");
    $stats['total_quiz_attempts'] = $stmt->fetchColumn();

    // Package distribution
    $stmt = $pdo->query("SELECT package_selected, COUNT(*) as count FROM students WHERE package_selected IS NOT NULL GROUP BY package_selected");
    $package_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stats['premium_users'] = $package_stats['Premium'] ?? 0;
    $stats['standard_users'] = $package_stats['Standard'] ?? 0;
    $stats['basic_users'] = $package_stats['Basic'] ?? 0;

} catch (Exception $e) {
    $error_message = "Error fetching statistics: " . $e->getMessage();
}

// Fetch data for charts
$user_growth = [];
$revenue_trends = [];
$course_popularity = [];
$package_distribution = [];
$student_engagement = [];

try {
    // User growth (students over time - last 30 days)
    $stmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count 
                         FROM students 
                         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                         GROUP BY DATE(created_at) 
                         ORDER BY date");
    $user_growth = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenue trends (last 30 days)
    $stmt = $pdo->query("SELECT DATE(created_at) as date, SUM(amount) as revenue 
                         FROM payments 
                         WHERE status = 'Completed' 
                         AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                         GROUP BY DATE(created_at) 
                         ORDER BY date");
    $revenue_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Course popularity (enrollments)
    $stmt = $pdo->query("SELECT c.course_name, COUNT(e.id) as enrollments 
                         FROM courses c 
                         LEFT JOIN enrollments e ON c.id = e.course_id 
                         GROUP BY c.id 
                         ORDER BY enrollments DESC 
                         LIMIT 10");
    $course_popularity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Package distribution
    $stmt = $pdo->query("SELECT package_selected as package, COUNT(*) as count 
                         FROM students 
                         WHERE package_selected IS NOT NULL 
                         GROUP BY package_selected");
    $package_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Student engagement (quiz attempts per course)
    $stmt = $pdo->query("SELECT c.course_name, COUNT(lqa.id) as quiz_attempts 
                         FROM courses c 
                         LEFT JOIN lockdown_quiz_attempts lqa ON c.id = lqa.course_id 
                         GROUP BY c.id 
                         ORDER BY quiz_attempts DESC 
                         LIMIT 8");
    $student_engagement = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Error fetching chart data: " . $e->getMessage();
}

// Pagination for recent activities
$activities_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $activities_per_page;

// Fetch recent activities with pagination
$recent_activities = [];
$total_activities = 0;
try {
    // Get total count
    $stmt = $pdo->query("SELECT COUNT(*) FROM (
                         SELECT 'Payment' as type, created_at
                         FROM payments 
                         WHERE status = 'Completed' 
                         UNION ALL
                         SELECT 'New Student' as type, created_at
                         FROM students 
                         UNION ALL
                         SELECT 'Quiz' as type, submitted_at as created_at
                         FROM lockdown_quiz_attempts
                         ) as combined");
    $total_activities = $stmt->fetchColumn();
    
    // Fetch paginated activities
    $stmt = $pdo->prepare("SELECT * FROM (
                         SELECT 'Payment' as type, CONCAT('Payment received - R', amount) as message, created_at, 
                         (SELECT CONCAT(first_name, ' ', surname) FROM students WHERE id = p.student_id) as user_name
                         FROM payments p 
                         WHERE status = 'Completed' 
                         UNION ALL
                         SELECT 'New Student' as type, CONCAT('New student registered') as message, created_at,
                         CONCAT(first_name, ' ', surname) as user_name
                         FROM students 
                         UNION ALL
                         SELECT 'Quiz' as type, CONCAT('Quiz completed - Score: ', COALESCE(score, 0)) as message, submitted_at as created_at,
                         (SELECT CONCAT(first_name, ' ', surname) FROM students WHERE id = lqa.user_id) as user_name
                         FROM lockdown_quiz_attempts lqa
                         ) as combined
                         ORDER BY created_at DESC 
                         LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $activities_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error fetching recent activities: " . $e->getMessage();
}

// Calculate total pages
$total_pages = ceil($total_activities / $activities_per_page);

// Fetch support cases summary
$support_stats = [];
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM support_cases GROUP BY status");
    $support_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $error_message = "Error fetching support stats: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - NovaTech FET College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
        .dashboard-card { 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            backdrop-filter: blur(10px);
        }
        .dashboard-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15); 
        }
        .sidebar { transition: all 0.3s ease; }
        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }
        .metric-trend { animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
        .notification-dot {
            animation: bounce 1s infinite;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        .active-nav-item {
            background-color: var(--gold) !important;
            color: var(--navy) !important;
        }
        
        /* Download button animations */
        #downloadPdfBtn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* Smooth transitions */
        button, a {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Progress bar animation */
        #progressBar {
            transition: width 0.3s ease;
        }
        
        /* Loading spinner */
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Notification animation */
        .notification {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 1rem;
            gap: 0.5rem;
        }
        
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            text-decoration: none;
            color: #374151;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .pagination a:hover {
            background-color: #f3f4f6;
        }
        
        .pagination .current {
            background-color: #1e3a6c;
            color: white;
            border-color: #1e3a6c;
        }
        
        .pagination .disabled {
            color: #9ca3af;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-beige">
    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Sidebar -->
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
            
            <!-- Admin Profile -->
            <div class="mb-8 p-4 bg-white bg-opacity-10 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gold rounded-full flex items-center justify-center mr-3">
                        <span class="text-navy font-bold">AD</span>
                    </div>
                    <div>
                        <h3 class="font-semibold">Admin Panel</h3>
                        <p class="text-sm mt-1 text-gold">System Administrator</p>
                    </div>
                </div>
            </div>
            
            <!-- ADMIN SIDEBAR NAVIGATION -->
            <nav>
                <ul class="space-y-2">
                    <li><a href="admin_dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-tachometer-alt mr-3"></i> Dashboard</a></li>
                    <li><a href="user_management.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-users mr-3"></i> User Management</a></li>
					<li><a href="master-timetable.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-calendar-alt mr-3"></i> Master Timetable</a></li>
                    <li><a href="admin_Course_Content.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-book mr-3"></i> Course & Content</a></li>
                    <li><a href="package_management.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-box mr-3"></i> Package Management</a></li>
					<li><a href="admin_support_cases.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-headset mr-3"></i> Support Cases</a></li>
					<li><a href="admin_communications.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10" onclick="showSection('communications', this)"><i class="fas fa-envelope mr-3"></i> NovaTechMail</a></li>
                    <li><a href="analytics_reports.php" class="flex items-center p-2 rounded-lg active-nav-item"><i class="fas fa-chart-line mr-3"></i> Analytics & Reports</a></li>
                    <li><a href="admin_announcements.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-bullhorn mr-3"></i> Announcements</a></li>
                    <li><a href="admin_settings.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-cog mr-3"></i> Settings</a></li>
                    <li><a href="logout.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-sign-out-alt mr-3"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="md:ml-64">
        <!-- Top Navigation -->
        <header class="bg-white shadow-md relative z-50">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <button class="text-navy md:hidden" id="menuButton">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="text-xl font-bold text-navy">Analytics & Reports Management</h1>
                    <div class="flex items-center space-x-4">
                        <!-- Admin Profile -->
                        <div class="hidden md:block">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-2">
                                    <span class="text-navy font-bold text-sm">AD</span>
                                </div>
                                <span class="text-navy">System Admin</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="container mx-auto px-6 py-8">
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
			
			<!-- Enhanced Download Section -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-navy">Analytics & Reports</h1>
                    <p class="text-gray-600 mt-1">Comprehensive overview of platform performance</p>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3">
                    <!-- PDF Download Button -->
                    <button id="downloadPdfBtn" 
                            class="bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg flex items-center transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-file-pdf mr-2"></i>
                        <span>Download PDF Report</span>
                        <div id="pdfLoading" class="hidden ml-2">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Download Progress Bar (Hidden by default) -->
            <div id="downloadProgress" class="hidden mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-blue-700">Preparing your report...</span>
                        <span id="progressPercent" class="text-sm font-medium text-blue-700">0%</span>
                    </div>
                    <div class="w-full bg-blue-200 rounded-full h-2">
                        <div id="progressBar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <p class="text-xs text-blue-600 mt-2">This may take a few moments. Please don't close this page.</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Students</p>
                            <p class="text-2xl font-bold text-navy"><?php echo $stats['total_students']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-blue-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Teachers</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $stats['total_teachers']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-chalkboard-teacher text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Parents</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo $stats['total_parents']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-friends text-purple-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Revenue</p>
                            <p class="text-2xl font-bold text-gold">R<?php echo number_format($stats['total_revenue'], 2); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-coins text-yellow-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Active Subscriptions</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $stats['active_subscriptions']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Courses</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_courses']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-book text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Study Groups</p>
                            <p class="text-2xl font-bold text-indigo-600"><?php echo $stats['total_study_groups']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-indigo-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Quiz Attempts</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo $stats['total_quiz_attempts']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-tasks text-red-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- User Growth Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <h2 class="text-xl font-bold text-navy mb-4">Student Growth (30 Days)</h2>
                    <canvas id="userGrowthChart"></canvas>
                </div>

                <!-- Revenue Trends Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <h2 class="text-xl font-bold text-navy mb-4">Revenue Trends (30 Days)</h2>
                    <canvas id="revenueTrendsChart"></canvas>
                </div>

                <!-- Course Popularity Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <h2 class="text-xl font-bold text-navy mb-4">Course Popularity</h2>
                    <canvas id="coursePopularityChart"></canvas>
                </div>

                <!-- Package Distribution Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <h2 class="text-xl font-bold text-navy mb-4">Package Distribution</h2>
                    <canvas id="packageDistributionChart"></canvas>
                </div>

                <!-- Student Engagement Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <h2 class="text-xl font-bold text-navy mb-4">Student Engagement (Quiz Attempts)</h2>
                    <canvas id="studentEngagementChart"></canvas>
                </div>

                <!-- Support Cases Status -->
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <h2 class="text-xl font-bold text-navy mb-4">Support Cases Status</h2>
                    <canvas id="supportStatusChart"></canvas>
                </div>
            </div>

            <!-- Recent Activities Table with Pagination -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <h2 class="text-xl font-bold text-navy p-6 border-b border-gray-200">Recent System Activities</h2>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-max">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recent_activities as $activity): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?php echo $activity['type'] === 'Payment' ? 'bg-green-100 text-green-800' : 
                                              ($activity['type'] === 'Enrollment' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'); ?>">
                                        <?php echo htmlspecialchars($activity['type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($activity['message']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_activities)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No recent activities</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination p-4 border-t border-gray-200">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo $current_page - 1; ?>" class="prev">
                                <i class="fas fa-chevron-left mr-1"></i> Previous
                            </a>
                        <?php else: ?>
                            <span class="disabled">
                                <i class="fas fa-chevron-left mr-1"></i> Previous
                            </span>
                        <?php endif; ?>
                        
                        <?php
                        // Calculate start and end pages for pagination
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Show first page if not in range
                        if ($start_page > 1) {
                            echo '<a href="?page=1">1</a>';
                            if ($start_page > 2) echo '<span class="disabled">...</span>';
                        }
                        
                        // Show page numbers
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="current">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . '">' . $i . '</a>';
                            }
                        }
                        
                        // Show last page if not in range
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) echo '<span class="disabled">...</span>';
                            echo '<a href="?page=' . $total_pages . '">' . $total_pages . '</a>';
                        }
                        ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?>" class="next">
                                Next <i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled">
                                Next <i class="fas fa-chevron-right ml-1"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile sidebar toggle
        const menuButton = document.getElementById('menuButton');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const closeSidebar = document.getElementById('closeSidebar');

        if (menuButton && sidebar && overlay) {
            menuButton.addEventListener('click', () => {
                sidebar.classList.add('active');
                overlay.classList.add('active');
            });
            
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
            
            closeSidebar.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }

        // Enhanced Download Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const downloadPdfBtn = document.getElementById('downloadPdfBtn');
            const pdfLoading = document.getElementById('pdfLoading');
            const downloadProgress = document.getElementById('downloadProgress');
            const progressBar = document.getElementById('progressBar');
            const progressPercent = document.getElementById('progressPercent');

            // PDF Download with Progress Simulation
            if (downloadPdfBtn) {
                downloadPdfBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Show loading state
                    pdfLoading.classList.remove('hidden');
                    downloadPdfBtn.disabled = true;
                    downloadProgress.classList.remove('hidden');
                    
                    // Simulate progress
                    let progress = 0;
                    const progressInterval = setInterval(() => {
                        progress += Math.random() * 15;
                        if (progress > 90) progress = 90;
                        
                        progressBar.style.width = progress + '%';
                        progressPercent.textContent = Math.round(progress) + '%';
                        
                        if (progress >= 90) {
                            clearInterval(progressInterval);
                        }
                    }, 200);
                    
                    // Create and trigger download
                    setTimeout(() => {
                        // Create a hidden iframe for download
                        const iframe = document.createElement('iframe');
                        iframe.style.display = 'none';
                        iframe.src = 'generate_analytics_pdf.php';
                        document.body.appendChild(iframe);
                        
                        // Complete progress
                        progressBar.style.width = '100%';
                        progressPercent.textContent = '100%';
                        
                        // Reset button state after a delay
                        setTimeout(() => {
                            pdfLoading.classList.add('hidden');
                            downloadPdfBtn.disabled = false;
                            downloadProgress.classList.add('hidden');
                            progressBar.style.width = '0%';
                            progressPercent.textContent = '0%';
                            
                            // Show success message
                            showNotification('Report downloaded successfully!', 'success');
                            
                            // Remove iframe
                            setTimeout(() => {
                                if (document.body.contains(iframe)) {
                                    document.body.removeChild(iframe);
                                }
                            }, 1000);
                        }, 1000);
                        
                    }, 1500);
                });
            }

            // Notification System
            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                const bgColor = type === 'success' ? 'bg-green-500' : 
                               type === 'error' ? 'bg-red-500' : 
                               'bg-blue-500';
                
                notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 z-50 notification`;
                notification.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
                        <span>${message}</span>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                // Remove after 4 seconds
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (document.body.contains(notification)) {
                            document.body.removeChild(notification);
                        }
                    }, 300);
                }, 4000);
            }
        });

        // Charts
        document.addEventListener('DOMContentLoaded', function() {
            // User Growth Chart
            const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
            new Chart(userGrowthCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($user_growth, 'date')); ?>,
                    datasets: [{
                        label: 'New Students',
                        data: <?php echo json_encode(array_column($user_growth, 'count')); ?>,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Revenue Trends Chart
            const revenueTrendsCtx = document.getElementById('revenueTrendsChart').getContext('2d');
            new Chart(revenueTrendsCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($revenue_trends, 'date')); ?>,
                    datasets: [{
                        label: 'Daily Revenue (R)',
                        data: <?php echo json_encode(array_column($revenue_trends, 'revenue')); ?>,
                        backgroundColor: 'rgb(245, 158, 11)'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Course Popularity Chart
            const coursePopularityCtx = document.getElementById('coursePopularityChart').getContext('2d');
            new Chart(coursePopularityCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($course_popularity, 'course_name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($course_popularity, 'enrollments')); ?>,
                        backgroundColor: [
                            // CAT - Green
                            'rgb(16, 185, 129)',
                            // Physical Science - Red
                            'rgb(239, 68, 68)',
                            // Mathematics - Blue
                            'rgb(59, 130, 246)',
                            // English - Orange
                            'rgb(249, 115, 22)',
                            // Other courses keep the original colors
                            'rgb(139, 92, 246)',
                            'rgb(14, 165, 233)',
                            'rgb(236, 72, 153)'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });

            // Package Distribution Chart
            const packageDistributionCtx = document.getElementById('packageDistributionChart').getContext('2d');
            new Chart(packageDistributionCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($package_distribution, 'package')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($package_distribution, 'count')); ?>,
                        backgroundColor: [
                            'rgb(16, 185, 129)', // Basic - Green
                            'rgb(59, 130, 246)', // Standard - Blue
                            'rgb(139, 92, 246)'  // Premium - Purple
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });

            // Student Engagement Chart
            const studentEngagementCtx = document.getElementById('studentEngagementChart').getContext('2d');
            new Chart(studentEngagementCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($student_engagement, 'course_name')); ?>,
                    datasets: [{
                        label: 'Quiz Attempts',
                        data: <?php echo json_encode(array_column($student_engagement, 'quiz_attempts')); ?>,
                        backgroundColor: 'rgb(236, 72, 153)'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Support Cases Status Chart
            const supportStatusCtx = document.getElementById('supportStatusChart').getContext('2d');
            new Chart(supportStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Open', 'In Progress', 'Resolved', 'Closed'],
                    datasets: [{
                        data: [
                            <?php echo $support_stats['Open'] ?? 0; ?>,
                            <?php echo $support_stats['In Progress'] ?? 0; ?>,
                            <?php echo $support_stats['Resolved'] ?? 0; ?>,
                            <?php echo $support_stats['Closed'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            'rgb(239, 68, 68)',    // Open - Red
                            'rgb(245, 158, 11)',   // In Progress - Yellow
                            'rgb(16, 185, 129)',   // Resolved - Green
                            'rgb(59, 130, 246)'    // Closed - Blue
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });
        });

        // Auto-hide messages
        setTimeout(() => {
            const messages = document.querySelectorAll('.bg-red-100, .bg-green-100');
            messages.forEach(msg => {
                msg.style.transition = 'all 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => {
                    msg.style.display = 'none';
                }, 50000);
            });
        }, 500000);
    </script>
</body>
</html>