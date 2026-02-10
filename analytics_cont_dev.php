<?php
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'models/Analytics.php';

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Role check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content') {
    header('Location: login.php');
    exit();
}

// Initialize Analytics
$analytics = new Analytics();

// Get current content developer ID from session
$content_developer_id = $_SESSION['user_id'];

// Get analytics data
$dashboardStats = $analytics->getDashboardStats($content_developer_id);
$topMaterials = $analytics->getTopMaterials($content_developer_id, 5);
$monthlyStats = $analytics->getMonthlyStats($content_developer_id);
$developerStats = $analytics->getContentDeveloperStats($content_developer_id);
$materialsByCategory = $analytics->getMaterialsByCategory($content_developer_id);

// Prepare monthly data for charts
$monthlyData = [];
$currentDate = new DateTime();
for ($i = 11; $i >= 0; $i--) {
    $date = clone $currentDate;
    $date->sub(new DateInterval('P'.$i.'M'));
    $monthKey = $date->format('Y-m');
    $monthLabel = $date->format('M');
    
    $monthlyData[] = [
        'month' => $monthLabel,
        'materials' => $monthlyStats[$monthKey]['materials'] ?? 0,
        'exams' => $monthlyStats[$monthKey]['exams'] ?? 0
    ];
}

// User details
$display_name = ($_SESSION['first_name'] ?? 'Content') . ' ' . ($_SESSION['last_name'] ?? 'Developer');
$first_name = $_SESSION['first_name'] ?? 'Content';
$last_name = $_SESSION['last_name'] ?? 'Developer';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Content Developer Dashboard - NovaTech FET College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --navy: #1e3a6c;
            --gold: #fbbf24;
            --beige: #f5f5dc;
            --purple: #7c3aed;
        }
        body { font-family: 'Poppins', sans-serif; }
        .bg-navy { background-color: var(--navy); }
        .bg-gold { background-color: var(--gold); }
        .bg-beige { background-color: var(--beige); }
        .bg-purple { background-color: var(--purple); }
        .text-navy { color: var(--navy); }
        .analytics-card { 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            border-radius: 1rem;
        }
        .analytics-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); 
        }
        .sidebar { transition: all 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { 
                position: fixed; 
                left: -300px; 
                z-index: 1000; 
                height: 100vh; 
            }
            .sidebar.active { left: 0; }
            .overlay { 
                display: none; 
                position: fixed; 
                top: 0; 
                left: 0; 
                right: 0; 
                bottom: 0; 
                background-color: rgba(0, 0, 0, 0.5); 
                z-index: 999; 
            }
            .overlay.active { display: block; }
        }

        /* Notification Widget Styles */
        .notification-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            width: 400px;
            max-height: 500px;
            overflow-y: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            margin-top: 10px;
        }

        .notification-dropdown.active {
            display: block;
        }

        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: #f9fafb;
        }

        .notification-item.unread {
            background-color: #fef3c7;
            border-left: 3px solid var(--gold);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: bold;
            min-width: 18px;
            text-align: center;
        }

        .filter-tab {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #6b7280;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-tab:hover {
            background: #e5e7eb;
        }

        .filter-tab.active {
            background: var(--gold);
            color: var(--navy);
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-beige">
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
            <div class="mb-8 p-4 bg-white bg-opacity-10 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple rounded-full flex items-center justify-center mr-3">
                        <span class="text-white font-bold"><?php echo htmlspecialchars($initials); ?></span>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?php echo htmlspecialchars($display_name); ?></h3>
                        <p class="text-sm mt-1">Content Developer</p>
                    </div>
                </div>
            </div>
            <nav>
                <ul class="space-y-2">
                    <li><a href="content_dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-tachometer-alt mr-3"></i><span>Dashboard</span></a></li>
                    <li><a href="course_content_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-book mr-3"></i><span>Courses</span></a></li>
                    <li><a href="mock_exams_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-file-alt mr-3"></i><span>Mock Exams</span></a></li>
                    <li><a href="study_materials_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-book-open mr-3"></i><span>Study Materials</span></a></li>
                    <li><a href="past_papers_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-file-pdf mr-3"></i><span>Past Papers</span></a></li>
                    
                    <li><a href="analytics_cont_dev.php" class="flex items-center p-2 rounded-lg bg-purple text-white"><i class="fas fa-chart-bar mr-3"></i><span>Analytics</span></a></li>
                    <li><a href="settings_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-cog mr-3"></i><span>Settings</span></a></li>
                    <li><a href="logout.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-sign-out-alt mr-3"></i><span>Logout</span></a></li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="md:ml-64">
        <!-- Header -->
        <header class="bg-white shadow-md">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <button class="text-navy md:hidden" id="menuButton"><i class="fas fa-bars"></i></button>
                    <h1 class="text-xl font-bold text-navy">Analytics & Performance Dashboard</h1>
                    <div class="flex items-center space-x-4">
                        <button class="bg-purple text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition text-sm">
                            <i class="fas fa-download mr-2"></i>Export Report
                        </button>
                        
                        <!-- Real-time Notification Widget -->
                        <div class="relative" id="notificationWidget">
                            <!-- Bell Icon with Badge -->
                            <button onclick="toggleNotifications()" class="relative p-2 text-navy hover:text-gold transition">
                                <i class="fas fa-bell text-2xl"></i>
                                <span id="notificationBadge" class="notification-badge" style="display: none;">0</span>
                            </button>

                            <!-- Notification Dropdown -->
                            <div id="notificationDropdown" class="notification-dropdown">
                                <!-- Header -->
                                <div class="p-4 border-b border-gray-200 bg-gray-50">
                                    <div class="flex justify-between items-center">
                                        <h3 class="font-bold text-navy">Notifications</h3>
                                        <button onclick="markAllAsRead()" class="text-sm text-gold hover:text-yellow-600 font-semibold">
                                            Mark all read
                                        </button>
                                    </div>
                                    <!-- Filter Tabs -->
                                    <div class="flex space-x-2 mt-3">
                                        <button onclick="filterNotifications('all')" class="filter-tab active" data-filter="all">
                                            All
                                        </button>
                                        <button onclick="filterNotifications('unread')" class="filter-tab" data-filter="unread">
                                            Unread
                                        </button>
                                    </div>
                                </div>

                                <!-- Notifications List -->
                                <div id="notificationsList" class="divide-y divide-gray-200">
                                    <!-- Notifications will be loaded here -->
                                    <div class="p-8 text-center text-gray-500">
                                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                        <p>Loading notifications...</p>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="p-3 border-t border-gray-200 bg-gray-50 text-center">
                                    <a href="notifications_cont_dev.php" class="text-sm text-navy hover:text-gold font-semibold">
                                        View all notifications
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="hidden md:block">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-purple rounded-full flex items-center justify-center mr-2">
                                    <span class="text-white font-bold text-sm"><?php echo htmlspecialchars($initials); ?></span>
                                </div>
                                <span class="text-navy"><?php echo htmlspecialchars(explode(' ', $display_name)[0]); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-6 py-8">
            <!-- Personal Performance Summary -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl shadow-lg p-6 mb-8 text-white">
                <h2 class="text-2xl font-bold mb-4">Your Performance Summary</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $developerStats['total_uploads']; ?></div>
                        <p class="text-purple-100">Total Content Created</p>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo number_format($developerStats['total_downloads']); ?></div>
                        <p class="text-purple-100">Total Downloads</p>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $developerStats['recent_activity_count']; ?></div>
                        <p class="text-purple-100">Content This Month</p>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold">
                            <?php echo $developerStats['most_popular_material'] ? $developerStats['most_popular_material']['download_count'] : '0'; ?>
                        </div>
                        <p class="text-purple-100">Most Popular Item Downloads</p>
                    </div>
                </div>
            </div>

            <!-- Key Metrics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 analytics-card text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-book-open text-blue-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy"><?php echo $dashboardStats['total_materials']; ?></h3>
                    <p class="text-gray-600 text-sm">Study Materials</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 analytics-card text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-download text-green-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy"><?php echo number_format($dashboardStats['total_downloads']); ?></h3>
                    <p class="text-gray-600 text-sm">Total Downloads</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 analytics-card text-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-file-alt text-purple-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy"><?php echo $dashboardStats['total_exams']; ?></h3>
                    <p class="text-gray-600 text-sm">Mock Exams</p>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 analytics-card text-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-graduation-cap text-yellow-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy"><?php echo $dashboardStats['total_courses']; ?></h3>
                    <p class="text-gray-600 text-sm">Active Courses</p>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="bg-white rounded-xl shadow-lg p-6 analytics-card mb-8">
                <h3 class="text-lg font-bold text-navy mb-4">Monthly Content Creation Trends</h3>
                <canvas id="contentTrendChart" height="100"></canvas>
            </div>

            <!-- Top Materials and Categories -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-xl shadow-lg p-6 analytics-card">
                    <h3 class="text-lg font-bold text-navy mb-4">Your Top Performing Materials</h3>
                    <?php if (empty($topMaterials)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-chart-line text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">No materials uploaded yet</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($topMaterials as $index => $material): ?>
                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                            <div class="flex items-center">
                                <span class="w-6 h-6 bg-purple-600 rounded-full text-white text-xs flex items-center justify-center mr-3">
                                    <?php echo $index + 1; ?>
                                </span>
                                <div>
                                    <h4 class="font-medium text-navy text-sm"><?php echo htmlspecialchars($material['title']); ?></h4>
                                    <p class="text-xs text-gray-600"><?php echo htmlspecialchars($material['course_name'] ?? 'No Course'); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-navy text-sm"><?php echo number_format($material['download_count']); ?></div>
                                <div class="text-xs text-gray-500">downloads</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Materials by Category -->
                <div class="bg-white rounded-xl shadow-lg p-6 analytics-card">
                    <h3 class="text-lg font-bold text-navy mb-4">Materials by Category</h3>
                    <?php if (empty($materialsByCategory)): ?>
                    <p class="text-gray-500 text-center py-8">No data available</p>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($materialsByCategory as $category): ?>
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($category['category']); ?></span>
                                <span class="text-sm text-gray-600"><?php echo $category['count']; ?> items</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-purple-600 h-2 rounded-full" style="width: <?php echo ($category['count'] / max(1, array_sum(array_column($materialsByCategory, 'count')))) * 100; ?>%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1"><?php echo number_format($category['downloads']); ?> downloads</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile sidebar toggle
        const menuButton = document.getElementById('menuButton');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('overlay');
        
        if (menuButton && sidebar && overlay) {
            menuButton.addEventListener('click', () => {
                sidebar.classList.add('active');
                overlay.classList.add('active');
            });
            
            document.getElementById('closeSidebar').addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
            
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }

        // Content Creation Trend Chart
        const contentTrendCtx = document.getElementById('contentTrendChart').getContext('2d');
        new Chart(contentTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthlyData, 'month')); ?>,
                datasets: [
                    {
                        label: 'Study Materials',
                        data: <?php echo json_encode(array_column($monthlyData, 'materials')); ?>,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Mock Exams',
                        data: <?php echo json_encode(array_column($monthlyData, 'exams')); ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Notification Widget JavaScript
        let currentFilter = 'all';
        let notificationCheckInterval;

        // Toggle notification dropdown
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
            
            if (dropdown.classList.contains('active')) {
                loadNotifications();
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const widget = document.getElementById('notificationWidget');
            if (widget && !widget.contains(event.target)) {
                document.getElementById('notificationDropdown').classList.remove('active');
            }
        });

        // Load notifications via AJAX
        function loadNotifications() {
            fetch(`api/get_notifications.php?filter=${currentFilter}&limit=10`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayNotifications(data.notifications);
                        updateNotificationBadge(data.unread_count);
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    document.getElementById('notificationsList').innerHTML = `
                        <div class="p-8 text-center text-red-500">
                            <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                            <p>Failed to load notifications</p>
                        </div>
                    `;
                });
        }

        // Display notifications in the list
        function displayNotifications(notifications) {
            const listElement = document.getElementById('notificationsList');
            
            if (notifications.length === 0) {
                listElement.innerHTML = `
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-3 text-gray-400"></i>
                        <p>No notifications</p>
                    </div>
                `;
                return;
            }
            
            listElement.innerHTML = notifications.map(notification => {
                const icon = getNotificationIcon(notification.type);
                const timeAgo = formatTimeAgo(notification.created_at);
                const unreadClass = notification.is_read ? '' : 'unread';
                
                return `
                    <div class="notification-item ${unreadClass}" onclick="handleNotificationClick(${notification.notification_id}, ${notification.is_read})">
                        <div class="flex items-start space-x-3">
                            <div class="notification-icon ${icon.bgColor}">
                                <i class="fas ${icon.icon} ${icon.textColor}"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm ${notification.is_read ? 'text-gray-600' : 'text-navy font-semibold'}">
                                    ${escapeHtml(notification.message)}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="far fa-clock mr-1"></i>${timeAgo}
                                </p>
                            </div>
                            ${!notification.is_read ? '<div class="flex-shrink-0"><div class="w-2 h-2 bg-gold rounded-full"></div></div>' : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Get icon based on notification type
        function getNotificationIcon(type) {
            const icons = {
                content_upload: { icon: 'fa-upload', bgColor: 'bg-blue-100', textColor: 'text-blue-600' },
                content_update: { icon: 'fa-sync', bgColor: 'bg-green-100', textColor: 'text-green-600' },
                content_edit: { icon: 'fa-edit', bgColor: 'bg-yellow-100', textColor: 'text-yellow-600' },
                content_delete: { icon: 'fa-trash', bgColor: 'bg-red-100', textColor: 'text-red-600' },
                upload: { icon: 'fa-upload', bgColor: 'bg-blue-100', textColor: 'text-blue-600' },
                edit: { icon: 'fa-edit', bgColor: 'bg-yellow-100', textColor: 'text-yellow-600' },
                delete: { icon: 'fa-trash', bgColor: 'bg-red-100', textColor: 'text-red-600' },
                add: { icon: 'fa-plus-circle', bgColor: 'bg-green-100', textColor: 'text-green-600' },
                approval: { icon: 'fa-check-circle', bgColor: 'bg-green-100', textColor: 'text-green-600' },
                student_activity: { icon: 'fa-users', bgColor: 'bg-indigo-100', textColor: 'text-indigo-600' }
            };
            return icons[type] || { icon: 'fa-bell', bgColor: 'bg-gray-100', textColor: 'text-gray-600' };
        }

        // Format timestamp to relative time
        function formatTimeAgo(timestamp) {
            const now = new Date();
            const time = new Date(timestamp);
            const diff = Math.floor((now - time) / 1000);
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
            if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
            if (diff < 604800) return `${Math.floor(diff / 86400)} days ago`;
            
            return time.toLocaleDateString();
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Handle notification click
        function handleNotificationClick(notificationId, isRead) {
            if (!isRead) {
                markAsRead(notificationId);
            }
        }

        // Mark single notification as read
        function markAsRead(notificationId) {
            fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                    updateNotificationCount();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Mark all as read
        function markAllAsRead() {
            fetch('api/mark_all_notifications_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                    updateNotificationCount();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Filter notifications
        function filterNotifications(filter) {
            currentFilter = filter;
            
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.dataset.filter === filter) {
                    tab.classList.add('active');
                }
            });
            
            loadNotifications();
        }

        // Update notification badge
        function updateNotificationBadge(count) {
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        // Update notification count
        function updateNotificationCount() {
            fetch('api/get_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    updateNotificationBadge(data.count);
                })
                .catch(error => console.error('Error:', error));
        }

        // Auto-refresh notification count every 30 seconds
        function startNotificationChecking() {
            updateNotificationCount();
            notificationCheckInterval = setInterval(updateNotificationCount, 30000);
        }

        // Stop checking
        function stopNotificationChecking() {
            if (notificationCheckInterval) {
                clearInterval(notificationCheckInterval);
            }
        }

        // Start checking when page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startNotificationChecking);
        } else {
            startNotificationChecking();
        }

        // Stop checking when page unloads
        window.addEventListener('beforeunload', stopNotificationChecking);
    </script>
</body>
</html>