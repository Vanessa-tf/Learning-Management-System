<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page if not admin
    header('Location: login.php');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "novatech_db";
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Fetch total users count (students + financiers + teachers + content developers)
$totalUsersQuery = "SELECT 
    (SELECT COUNT(*) FROM students) + 
    (SELECT COUNT(*) FROM financiers) + 
    (SELECT COUNT(*) FROM users WHERE role = 'teacher') + 
    (SELECT COUNT(*) FROM content_developers) as total_users";
$totalUsersResult = $conn->query($totalUsersQuery);
$totalUsers = $totalUsersResult->fetch_assoc()['total_users'];
// Fetch active subscriptions count
$activeSubsQuery = "SELECT COUNT(*) as active_subs FROM students WHERE subscription_status = 'active'";
$activeSubsResult = $conn->query($activeSubsQuery);
$activeSubs = $activeSubsResult->fetch_assoc()['active_subs'];
// Fetch monthly revenue
$monthlyRevenueQuery = "SELECT SUM(amount) as monthly_revenue FROM payments 
                       WHERE status = 'Completed' 
                       AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                       AND YEAR(created_at) = YEAR(CURRENT_DATE())";
$monthlyRevenueResult = $conn->query($monthlyRevenueQuery);
$monthlyRevenueRow = $monthlyRevenueResult->fetch_assoc();
$monthlyRevenue = $monthlyRevenueRow['monthly_revenue'] ? $monthlyRevenueRow['monthly_revenue'] : 0;
// Fetch recent user activity (students only since financiers don't have created_at)
$recentActivityQuery = "SELECT 
    'student' as user_type, first_name, surname, created_at 
    FROM students 
    ORDER BY created_at DESC 
    LIMIT 5";
$recentActivityResult = $conn->query($recentActivityQuery);
// Fetch pending approvals count
$pendingApprovalsQuery = "SELECT COUNT(*) as pending_count FROM support_cases WHERE status = 'Open'";
$pendingApprovalsResult = $conn->query($pendingApprovalsQuery);
$pendingApprovals = $pendingApprovalsResult->fetch_assoc()['pending_count'];
// Fetch tutor requests count for dashboard
$tutorRequestsQuery = "SELECT COUNT(*) as tutor_requests_count FROM tutor_requests";
$tutorRequestsResult = $conn->query($tutorRequestsQuery);
$tutorRequestsCount = $tutorRequestsResult->fetch_assoc()['tutor_requests_count'];
// Fetch pending tutor requests count
$pendingTutorRequestsQuery = "SELECT COUNT(*) as pending_tutor_requests FROM tutor_requests WHERE status = 'pending'";
$pendingTutorRequestsResult = $conn->query($pendingTutorRequestsQuery);
$pendingTutorRequests = $pendingTutorRequestsResult->fetch_assoc()['pending_tutor_requests'];
// Fetch revenue trends data (last 30 days) - Enhanced version from admin_analytics.php
$revenueTrendsQuery = "SELECT DATE(created_at) as date, SUM(amount) as revenue 
                     FROM payments 
                     WHERE status = 'Completed' 
                     AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                     GROUP BY DATE(created_at) 
                     ORDER BY date";
$revenueTrendsResult = $conn->query($revenueTrendsQuery);
$revenueLabels = [];
$revenueData = [];
while ($row = $revenueTrendsResult->fetch_assoc()) {
    $revenueLabels[] = date('M d', strtotime($row['date']));
    $revenueData[] = floatval($row['revenue']);
}
// If no revenue data, create sample data for the chart
if (empty($revenueData)) {
    $revenueLabels = ['Jan 01', 'Jan 02', 'Jan 03', 'Jan 04', 'Jan 05'];
    $revenueData = [0, 0, 0, 0, 0];
}
// Fetch package distribution data - Enhanced version from admin_analytics.php
$packageDistributionQuery = "SELECT package_selected as package, COUNT(*) as count 
                     FROM students 
                     WHERE package_selected IS NOT NULL 
                     GROUP BY package_selected";
$packageDistributionResult = $conn->query($packageDistributionQuery);
$subscriptionLabels = [];
$subscriptionData = [];
$subscriptionColors = [];
while ($row = $packageDistributionResult->fetch_assoc()) {
    $subscriptionLabels[] = $row['package'];
    $subscriptionData[] = intval($row['count']);
    // Assign colors based on package type
    switch($row['package']) {
        case 'Basic':
            $subscriptionColors[] = 'rgb(16, 185, 129)'; // Green
            break;
        case 'Standard':
            $subscriptionColors[] = 'rgb(59, 130, 246)'; // Blue
            break;
        case 'Premium':
            $subscriptionColors[] = 'rgb(139, 92, 246)'; // Purple
            break;
        default:
            $subscriptionColors[] = 'rgb(156, 163, 175)'; // Gray
    }
}
// If no package data, create sample data
if (empty($subscriptionData)) {
    $subscriptionLabels = ['Basic', 'Standard', 'Premium'];
    $subscriptionData = [0, 0, 0];
    $subscriptionColors = [
        'rgb(16, 185, 129)',
        'rgb(59, 130, 246)',
        'rgb(139, 92, 246)'
    ];
}
// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NovaTech FET College</title>
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
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .status-pending { background-color: #fef3c7; color: #d97706; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #dc2626; }
        .status-completed { background-color: #dbeafe; color: #1e40af; }
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
                        <h3 class="font-semibold"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></h3>
                        <p class="text-sm mt-1 text-gold">System Administrator</p>
                        <p class="text-xs mt-1 opacity-75"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                    </div>
                </div>
            </div>
            <!-- ADMIN SIDEBAR NAVIGATION -->
            <nav>
             <ul class="space-y-2">
    <li><a href="admin_dashboard.php" class="flex items-center p-2 rounded-lg active-nav-item"><i class="fas fa-tachometer-alt mr-3"></i> Dashboard</a></li>
    <li><a href="user_management.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-users mr-3"></i> User Management</a></li>
	<li><a href="master-timetable.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-calendar-alt mr-3"></i> Master Timetable</a></li>
    <li><a href="admin_Course_Content.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-book mr-3"></i> Course & Content</a></li>
    <li><a href="package_management.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-box mr-3"></i> Package Management</a></li>
    <li><a href="admin_support_cases.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-headset mr-3"></i> Support Cases</a></li>
	<li><a href="admin_communications.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-envelope mr-3"></i> NovaTechMail</a></li>
    <li><a href="admin_analytics.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-chart-line mr-3"></i> Analytics & Reports</a></li>
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
                    <h1 class="text-xl font-bold text-navy">Admin Dashboard</h1>
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <div class="relative">
                            <button class="text-navy">
                                <i class="fas fa-bell"></i>
                                <span class="absolute top-[-5px] right-[-5px] w-3 h-3 "></span>
                            </button>
                        </div>
                        <!-- Admin Profile -->
                        <div class="hidden md:block">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-2">
                                    <span class="text-navy font-bold text-sm">AD</span>
                                </div>
                                <span class="text-navy"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <!-- Dashboard Content -->
        <main class="container mx-auto px-6 py-8">
            <!-- Dashboard Overview Section -->
            <div id="dashboard-section">
                <!-- Welcome Banner - Plain White -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8 text-navy dashboard-card">
                    <div class="flex flex-col md:flex-row items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold mb-2">Welcome to Admin Dashboard</h2>
                            <p class="opacity-90">System overview and key metrics at a glance</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <span class="bg-green-100 text-green-800 text-sm px-3 py-1 rounded-full">
                                <i class="fas fa-shield-alt mr-1"></i>Admin Access
                            </span>
                        </div>
                    </div>
                </div>
                <!-- Key Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Users -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Total Users</p>
                                <p class="text-2xl font-bold text-navy" data-metric="total-users"><?= $totalUsers ?></p>
                                <p class="text-sm text-green-600 metric-trend">
                                    <i class="fas fa-arrow-up mr-1"></i>All Platforms
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Active Subscriptions -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Active Subscriptions</p>
                                <p class="text-2xl font-bold text-navy" data-metric="active-subs"><?= $activeSubs ?></p>
                                <p class="text-sm text-green-600 metric-trend">
                                    <i class="fas fa-arrow-up mr-1"></i>Active
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-crown text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Monthly Revenue -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Monthly Revenue</p>
                                <p class="text-2xl font-bold text-navy" data-metric="monthly-revenue">R<?= number_format($monthlyRevenue, 2) ?></p>
                                <p class="text-sm text-green-600 metric-trend">
                                    <i class="fas fa-arrow-up mr-1"></i>Current Month
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-coins text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Tutor Requests -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Tutor Requests</p>
                                <p class="text-2xl font-bold text-navy" data-metric="tutor-requests"><?= $tutorRequestsCount ?></p>
                                <p class="text-sm text-orange-600 metric-trend">
                                    <i class="fas fa-clock mr-1"></i><?= $pendingTutorRequests ?> Pending
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chalkboard-teacher text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Main Dashboard Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Column -->
                    <div class="space-y-8">
                        <!-- Recent User Activity -->
                        <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold text-navy">Recent User Activity</h2>
                                <a href="user_management.php" class="text-gold hover:underline">View All Users</a>
                            </div>
                            <div class="space-y-4">
                                <?php
                                if ($recentActivityResult->num_rows > 0) {
                                    while($row = $recentActivityResult->fetch_assoc()) {
                                        $timeAgo = time_elapsed_string($row['created_at']);
                                        $userType = $row['user_type'];
                                        $userName = $row['first_name'] . ' ' . $row['surname'] . ' (Student)';
                                        $iconClass = 'fa-user-plus';
                                        $iconColor = 'green';
                                        echo '
                                        <div class="flex items-center p-3 border border-gray-200 rounded-lg hover:shadow-md transition">
                                            <div class="w-10 h-10 bg-'.$iconColor.'-100 rounded-lg flex items-center justify-center mr-3">
                                                <i class="fas '.$iconClass.' text-'.$iconColor.'-600"></i>
                                            </div>
                                            <div class="flex-grow">
                                                <h3 class="font-medium text-navy">New Student Registration</h3>
                                                <p class="text-sm text-gray-600">' . $userName . '</p>
                                            </div>
                                            <span class="text-sm text-gray-500">' . $timeAgo . '</span>
                                        </div>';
                                    }
                                } else {
                                    echo '<p class="text-gray-600">No recent user activity</p>';
                                }
                                ?>
                            </div>
                        </div>
                        <!-- Revenue Analytics Chart -->
                        <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                            <h2 class="text-xl font-bold text-navy mb-6">Revenue Analytics (30 Days)</h2>
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- Right Column -->
                    <div class="space-y-8">
                        <!-- Pending Approvals -->
                        <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold text-navy">Pending Approvals</h2>
                                <span class="bg-red-100 text-red-800 text-xs py-1 px-2 rounded-full"><?= $pendingApprovals ?> items</span>
                            </div>
                            <div class="space-y-4">
                                <div class="p-3 border border-gray-200 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="font-medium text-navy text-sm">Support Cases</h3>
                                        <span class="bg-yellow-100 text-yellow-800 text-xs py-1 px-2 rounded-full">Support</span>
                                    </div>
                                    <p class="text-xs text-gray-600 mb-2">Open support cases requiring attention</p>
                                    <div class="flex space-x-2">
                                        <a href="admin_support_cases.php" class="text-blue-600 text-xs font-medium hover:underline">Review</a>
                                    </div>
                                </div>
                                <div class="p-3 border border-gray-200 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="font-medium text-navy text-sm">Tutor Requests</h3>
                                        <span class="bg-purple-100 text-purple-800 text-xs py-1 px-2 rounded-full">Tutoring</span>
                                    </div>
                                    <p class="text-xs text-gray-600 mb-2">Pending tutor requests awaiting teacher approval</p>
                                    <div class="flex space-x-2">
                                        <a href="#" class="text-blue-600 text-xs font-medium hover:underline">View Requests</a>
                                    </div>
                                </div>
                                <!-- Additional pending items would be dynamically loaded here -->
                            </div>
                        </div>
                        <!-- Subscription Distribution -->
                        <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                            <h2 class="text-xl font-bold text-navy mb-6">Package Distribution</h2>
                            <div class="chart-container">
                                <canvas id="subscriptionChart"></canvas>
                            </div>
                        </div>
                    </div>
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

        // Initialize Charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing charts...');
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
                console.log('Revenue chart data:', {
                    labels: <?= json_encode($revenueLabels) ?>,
                    data: <?= json_encode($revenueData) ?>
                });
                try {
                    new Chart(revenueCtx, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($revenueLabels) ?>,
                            datasets: [{
                                label: 'Daily Revenue (R)',
                                data: <?= json_encode($revenueData) ?>,
                                backgroundColor: 'rgba(245, 158, 11, 0.8)',
                                borderColor: 'rgb(245, 158, 11)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return 'R' + value.toLocaleString();
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            }
                        }
                    });
                    console.log('Revenue chart initialized successfully');
                } catch (error) {
                    console.error('Error initializing revenue chart:', error);
                }
            } else {
                console.error('Revenue chart canvas not found');
            }
            // Subscription Chart
            const subscriptionCtx = document.getElementById('subscriptionChart');
            if (subscriptionCtx) {
                console.log('Subscription chart data:', {
                    labels: <?= json_encode($subscriptionLabels) ?>,
                    data: <?= json_encode($subscriptionData) ?>,
                    colors: <?= json_encode($subscriptionColors) ?>
                });
                try {
                    new Chart(subscriptionCtx, {
                        type: 'pie',
                        data: {
                            labels: <?= json_encode($subscriptionLabels) ?>,
                            datasets: [{
                                data: <?= json_encode($subscriptionData) ?>,
                                backgroundColor: <?= json_encode($subscriptionColors) ?>,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20
                                    }
                                }
                            }
                        }
                    });
                    console.log('Subscription chart initialized successfully');
                } catch (error) {
                    console.error('Error initializing subscription chart:', error);
                }
            } else {
                console.error('Subscription chart canvas not found');
            }
        });
    </script>
</body>
</html>
<?php
// Helper function to format time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>