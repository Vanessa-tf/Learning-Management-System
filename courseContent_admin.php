<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course & Content Management - NovaTech FET College</title>
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
                    <div class="w-10 h-10 bg-gold rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-graduation-cap text-navy"></i>
                    </div>
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
                    <li><a href="course_management.php" class="flex items-center p-2 rounded-lg active-nav-item"><i class="fas fa-book mr-3"></i> Course & Content</a></li>
                    <li><a href="package_management.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-box mr-3"></i> Package Management</a></li>
                    <li><a href="admin_support_cases.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-credit-card mr-3"></i> Support Cases</a></li>
                    <li><a href="admin_communications.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-chart-line mr-3"></i> NovaTechMail  </a></li>
                    <li><a href="admin_analytics.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-server mr-3"></i> Analytics & Reports</a></li>
                    <li><a href="announcements.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-bullhorn mr-3"></i> Announcements</a></li>
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
                    <h1 class="text-xl font-bold text-navy">Course & Content Management</h1>
                    <div class="flex items-center space-x-4">
                        <!-- Notifications -->
                        <div class="relative">
                            <button class="text-navy">
                                <i class="fas fa-bell"></i>
                                <span class="absolute top-[-5px] right-[-5px] w-3 h-3 bg-red-500 rounded-full notification-dot"></span>
                            </button>
                        </div>
                        <!-- Quick Actions Dropdown -->
                        <div class="relative">
                            <button class="text-navy">
                                <i class="fas fa-plus-circle"></i>
                            </button>
                        </div>
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
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-navy">Course & Content Management</h1>
                <button class="bg-gold text-navy font-bold py-2 px-6 rounded-lg hover:bg-yellow-500 transition">
                    <i class="fas fa-plus mr-2"></i>Add New Content
                </button>
            </div>

            <!-- Subject Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-navy">Mathematics</h3>
                            <p class="text-sm text-gray-600">158 materials</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calculator text-blue-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-navy">Physical Sciences</h3>
                            <p class="text-sm text-gray-600">142 materials</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-atom text-green-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-navy">English</h3>
                            <p class="text-sm text-gray-600">96 materials</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-book text-purple-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-navy">CAT</h3>
                            <p class="text-sm text-gray-600">73 materials</p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-laptop text-yellow-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Management Tabs -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="border-b border-gray-200 mb-6">
                    <nav class="flex space-x-8">
                        <button class="border-b-2 border-gold text-gold py-2 px-1 font-medium">All Content</button>
                        <button class="text-gray-500 hover:text-gray-700 py-2 px-1">Pending Approval</button>
                        <button class="text-gray-500 hover:text-gray-700 py-2 px-1">Past Papers</button>
                        <button class="text-gray-500 hover:text-gray-700 py-2 px-1">Study Guides</button>
                        <button class="text-gray-500 hover:text-gray-700 py-2 px-1">Mock Exams</button>
                    </nav>
                </div>

                <!-- Content List (Expanded based on description) -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-file-pdf text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-navy">Grade 12 Math - Quadratic Functions</h3>
                                <p class="text-sm text-gray-600">Uploaded by Dr. Smith • Mathematics • Sept 20, 2024</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="bg-green-100 text-green-800 text-xs py-1 px-2 rounded-full">Approved</span>
                            <button class="text-blue-600 hover:underline">Edit</button>
                            <button class="text-red-600 hover:underline">Remove</button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-file-video text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-navy">Physical Sciences - Chemical Bonding Lecture</h3>
                                <p class="text-sm text-gray-600">Uploaded by Prof. Johnson • Physical Sciences • Oct 1, 2025</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="bg-yellow-100 text-yellow-800 text-xs py-1 px-2 rounded-full">Pending</span>
                            <button class="text-green-600 hover:underline">Approve</button>
                            <button class="text-red-600 hover:underline">Reject</button>
                            <button class="text-blue-600 hover:underline">Edit</button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-file-alt text-purple-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-navy">English - Essay Writing Guide</h3>
                                <p class="text-sm text-gray-600">Uploaded by Ms. Lee • English • Sept 15, 2024</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="bg-green-100 text-green-800 text-xs py-1 px-2 rounded-full">Approved</span>
                            <button class="text-blue-600 hover:underline">Edit</button>
                            <button class="text-red-600 hover:underline">Remove</button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-file-code text-yellow-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-navy">CAT - Programming Basics Mock Exam</h3>
                                <p class="text-sm text-gray-600">Uploaded by Mr. Patel • CAT • Oct 5, 2025</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="bg-yellow-100 text-yellow-800 text-xs py-1 px-2 rounded-full">Pending</span>
                            <button class="text-green-600 hover:underline">Approve</button>
                            <button class="text-red-600 hover:underline">Reject</button>
                            <button class="text-blue-600 hover:underline">Edit</button>
                        </div>
                    </div>
                    <!-- Add more items as needed for demonstration -->
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
    </script>
</body>
</html>