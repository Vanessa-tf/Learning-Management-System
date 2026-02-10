<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'models/StudyMaterial.php';
require_once 'models/MockExams.php';
require_once 'models/LiveLessons.php';
require_once 'models/Course.php';

// Ensure user is a Content Developer
requireRole('content');

// Initialize models
$studyMaterial = new StudyMaterial();
$mockExam = new MockExam();
$liveLesson = new LiveLesson();
$course = new Course();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle Study Material Upload
    if (isset($_POST['guide_title']) && !empty($_FILES['guide_file']['name'])) {
        $guideData = [
            'course_id' => (int)$_POST['course_id'],
            'title' => trim($_POST['guide_title']),
            'description' => trim($_POST['guide_description']),
            'category' => $_POST['category'] ?? 'Study Guide',
            'status' => $_POST['status'] ?? 'published'
        ];
        
        $result = $studyMaterial->create($guideData, $_FILES['guide_file']);
        
        if ($result['success']) {
            $flash = ['text' => 'Study guide uploaded successfully!', 'type' => 'success'];
        } else {
            $flash = ['text' => $result['error'], 'type' => 'error'];
        }
    }
    
    // Handle announcement dismissal
    if (isset($_POST['dismiss_announcement'])) {
        $announcement_id = (int)$_POST['dismiss_announcement'];
        if (!isset($_SESSION['dismissed_announcements'])) {
            $_SESSION['dismissed_announcements'] = [];
        }
        $_SESSION['dismissed_announcements'][] = $announcement_id;
    }
}

// Get user info
if (!isset($_SESSION['user_id']) || !isset($_SESSION['first_name']) || !isset($_SESSION['last_name'])) {
    header('Location: logout.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$display_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$initials = strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1));

// Get dashboard data - use courses table for dropdown
$all_courses = $course->getAll();
$recent_materials = $studyMaterial->getAll(['limit' => 5]);
$recent_exams = $mockExam->getAll(['limit' => 5]);
$upcoming_lessons = $liveLesson->getUpcoming(['limit' => 3]);

// Fetch announcements for content developers
try {
    // Get published announcements that target developers or all users
    $announcements_stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name 
        FROM announcements a 
        LEFT JOIN users u ON a.created_by = u.id 
        WHERE a.is_published = 1 
        AND (a.target_audience LIKE '%developer%' OR a.target_audience LIKE '%all%')
        AND (a.scheduled_for IS NULL OR a.scheduled_for <= NOW())
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $announcements_stmt->execute();
    $all_announcements = $announcements_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process target audience for each announcement
    foreach ($all_announcements as &$announcement) {
        $announcement['targets'] = json_decode($announcement['target_audience'], true);
    }
    unset($announcement);
    
} catch (Exception $e) {
    $all_announcements = [];
    error_log("Error fetching announcements: " . $e->getMessage());
}

// Filter announcements to show only undismissed ones in the banner
$dismissed_announcements = $_SESSION['dismissed_announcements'] ?? [];
$banner_announcements = array_filter($all_announcements, function($announcement) use ($dismissed_announcements) {
    return !in_array($announcement['id'], $dismissed_announcements);
});

// For the announcements card, show all announcements
$announcements = $all_announcements;

// Calculate dashboard stats
$dashboard_stats = [
    'total_materials' => count($studyMaterial->getAll()),
    'total_exams' => count($mockExam->getAll()),
    'total_courses' => count($all_courses),
    'total_announcements' => count($announcements)
];

// Format upcoming lessons
foreach ($upcoming_lessons as &$lesson) {
    $lesson_date = $lesson['date'];
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    if ($lesson_date == $today) {
        $lesson['day'] = 'Today';
    } elseif ($lesson_date == $tomorrow) {
        $lesson['day'] = 'Tomorrow';
    } else {
        $lesson['day'] = date('M d', strtotime($lesson_date));
    }

    $lesson['time'] = date('h:i A', strtotime($lesson['start_time'])) . ' - ' . date('h:i A', strtotime($lesson['end_time']));
}
unset($lesson);

$theme = $_SESSION['theme'] ?? 'light';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Developer Dashboard - NovaTech FET College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #1e3a6c;
            --gold: #fbbf24;
            --beige: #f5f5dc;
            --purple: #7c3aed;
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --dark-text: #e2e8f0;
            --dark-green: #065f46;
        }
        body { font-family: 'Poppins', sans-serif; }
        .bg-navy { background-color: var(--navy); }
        .bg-gold { background-color: var(--gold); }
        .bg-beige { background-color: var(--beige); }
        .bg-purple { background-color: var(--purple); }
        .text-navy { color: var(--navy); }
        .dashboard-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 1rem;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="light"] {
            --bg-primary: var(--beige);
            --bg-secondary: white;
            --text-primary: var(--navy);
        }
        
        [data-theme="dark"] {
            --bg-primary: var(--dark-bg);
            --bg-secondary: var(--dark-card);
            --text-primary: var(--dark-text);
        }
        
        [data-theme="dark"] body { background-color: var(--dark-bg); color: var(--dark-text); }
        [data-theme="dark"] .bg-beige { background-color: var(--dark-bg); }
        [data-theme="dark"] .bg-white { background-color: var(--dark-card); }
        [data-theme="dark"] .text-navy { color: var(--dark-text); }
        [data-theme="dark"] .bg-purple { background-color: var(--dark-green); }
        
        .sidebar { transition: all 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
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

        /* Announcement Styles */
        .announcement-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin: 0.125rem;
        }
        .announcement-developer { background-color: #f3e8ff; color: #7c3aed; }
        .announcement-teacher { background-color: #dbeafe; color: #1e40af; }
        .announcement-student { background-color: #dcfce7; color: #166534; }
        .announcement-parent { background-color: #fef3c7; color: #92400e; }
        
        /* Scrollable Announcements */
        .announcements-scroll-container {
            max-height: 400px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }
        
        .announcements-scroll-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .announcements-scroll-container::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 3px;
        }
        
        .announcements-scroll-container::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 3px;
        }
        
        .announcements-scroll-container::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
        
        /* Announcement Banner Styles */
        .announcement-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-left: 5px solid var(--gold);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(251, 191, 36, 0); }
            100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0); }
        }
        
        .dismiss-btn {
            transition: all 0.3s ease;
        }
        
        .dismiss-btn:hover {
            transform: scale(1.1);
            background-color: rgba(255, 255, 255, 0.2);
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
                <button class="text-white md:hidden" id="closeSidebar"><i class="fas fa-times"></i></button>
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
                    <li><a href="content_dashboard.php" class="flex items-center p-2 rounded-lg bg-purple text-white"><i class="fas fa-tachometer-alt mr-3"></i><span>Dashboard</span></a></li>
                    <li><a href="course_content_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-book mr-3"></i><span>Courses</span></a></li>
                    <li><a href="mock_exams_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-file-alt mr-3"></i><span>Mock Exams</span></a></li>
                    <li><a href="study_materials_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-book-open mr-3"></i><span>Study Materials</span></a></li>
                    <li><a href="past_papers_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-file-pdf mr-3"></i><span>Past Papers</span></a></li>
                    <li><a href="settings_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-cog mr-3"></i><span>Settings</span></a></li>
                    <li><a href="logout.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-sign-out-alt mr-3"></i><span>Logout</span></a></li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="md:ml-64">
        <header class="bg-white shadow-md">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <button class="text-navy md:hidden" id="menuButton"><i class="fas fa-bars"></i></button>
                    <h1 class="text-xl font-bold text-navy">Content Developer Dashboard</h1>
                    <div class="flex items-center space-x-4">
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

        <main class="container mx-auto px-6 py-8 pt-28 md:pt-8">
            <?php if (isset($flash)): ?>
            <div class="mb-4 p-4 rounded-lg <?php echo $flash['type'] === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                <?php echo htmlspecialchars($flash['text']); ?>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 dashboard-card">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-navy mb-2">Welcome back, <?php echo htmlspecialchars($display_name); ?>!</h2>
                        
                    </div>
                    <div class="mt-4 md:mt-0">
                        <a href="#upload-section" class="bg-purple text-white font-bold py-2 px-6 rounded-lg hover:bg-indigo-700 transition inline-block">Upload New Material</a>
                    </div>
                </div>
            </div>

            <!-- Announcement Banners -->
            <?php if (!empty($banner_announcements)): ?>
                <?php foreach ($banner_announcements as $banner_announcement): ?>
                <div class="announcement-banner rounded-xl shadow-lg p-6 mb-8 text-white relative overflow-hidden">
                    <div class="absolute inset-0 bg-black opacity-10"></div>
                    <div class="relative z-10">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-bullhorn text-gold text-xl mr-3"></i>
                                    <h2 class="text-xl font-bold"><?php echo htmlspecialchars($banner_announcement['title']); ?></h2>
                                </div>
                                <p class="text-white text-lg mb-2"><?php echo htmlspecialchars($banner_announcement['message']); ?></p>
                                <div class="flex items-center text-sm opacity-90">
                                    <span class="mr-4"><i class="far fa-clock mr-1"></i> <?php echo date('M j, Y g:i A', strtotime($banner_announcement['created_at'])); ?></span>
                                  
                                </div>
                            </div>
                            <form method="POST" class="ml-4">
                                <input type="hidden" name="dismiss_announcement" value="<?php echo $banner_announcement['id']; ?>">
                                <button type="submit" class="dismiss-btn w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center text-white hover:bg-opacity-30 transition">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-alt text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-navy"><?php echo $dashboard_stats['total_exams']; ?></h3>
                    <p class="text-gray-600">Mock Exams</p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-book text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-navy"><?php echo $dashboard_stats['total_materials']; ?></h3>
                    <p class="text-gray-600">Study Materials</p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chalkboard-teacher text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-navy"><?php echo $dashboard_stats['total_courses']; ?></h3>
                    <p class="text-gray-600">Courses</p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card text-center">
                    <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-bullhorn text-yellow-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-navy"><?php echo $dashboard_stats['total_announcements']; ?></h3>
                    <p class="text-gray-600">Announcements</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-8">
                    <!-- Upload Section -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card" id="upload-section">
                        <h2 class="text-xl font-bold text-navy mb-6">Upload New Learning Material</h2>
                        <div class="flex border-b mb-6">
                            <button class="py-2 px-4 font-medium text-navy border-b-2 border-purple focus:outline-none active-tab" data-tab="study-guide">
                                Study Guide
                            </button>
                            <button class="py-2 px-4 font-medium text-gray-500 hover:text-navy focus:outline-none" data-tab="mock-exam">
                                Mock Exam
                            </button>
                        </div>

                        <!-- Study Guide Upload Form -->
                        <div id="study-guide-form" class="tab-content">
                            <form action="content_dashboard.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                                    <select name="course_id" class="w-full p-2 border border-gray-300 rounded-lg" required>
                                        <option value="">Select a Course</option>
                                        <?php foreach ($all_courses as $course_item): ?>
                                        <option value="<?php echo $course_item['id']; ?>"><?php echo htmlspecialchars($course_item['course_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Guide Title</label>
                                    <input type="text" name="guide_title" placeholder="e.g., Algebra Study Guide" class="w-full p-2 border border-gray-300 rounded-lg" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea name="guide_description" rows="3" placeholder="Describe what this study guide covers..." class="w-full p-2 border border-gray-300 rounded-lg"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                    <select name="category" class="w-full p-2 border border-gray-300 rounded-lg">
                                        <option value="Study Guide">Study Guide</option>
                                        <option value="Notes">Class Notes</option>
                                        <option value="Reference">Reference Material</option>
                                        <option value="Exercises">Exercises</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload File (min 10 MB)</label>
                                    <input type="file" name="guide_file" accept=".pdf,.doc,.docx,.ppt,.pptx" class="w-full p-2 border border-gray-300 rounded-lg" required>
                                </div>
                                <div class="pt-4">
                                    <button type="submit" class="w-full bg-purple text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 transition">
                                        Upload Study Guide
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Mock Exam Form -->
                        <div id="mock-exam-form" class="tab-content hidden">
                            <p class="text-gray-600 text-center py-4">Use the Mock Exams page to create detailed exams with questions.</p>
                            <a href="mock_exams_cont_dev.php" class="block w-full bg-purple text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 transition text-center">
                                Go to Mock Exams
                            </a>
                        </div>
                    </div>

                    <!-- Recent Uploads -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <h2 class="text-xl font-bold text-navy mb-6">Your Recent Uploads</h2>
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-navy mb-3">Mock Exams</h3>
                            <?php if (empty($recent_exams)): ?>
                            <p class="text-gray-600">No mock exams yet.</p>
                            <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($recent_exams as $exam): ?>
                                <div class="p-3 border border-gray-200 rounded-lg hover:shadow-md transition">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-medium text-navy"><?php echo htmlspecialchars($exam['title']); ?></h4>
                                            <p class="text-sm text-gray-600">Course ID: <?php echo htmlspecialchars($exam['course_id']); ?></p>
                                            <p class="text-xs text-gray-500">Uploaded: <?php echo date('M d, Y', strtotime($exam['created_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-navy mb-3">Study Materials</h3>
                            <?php if (empty($recent_materials)): ?>
                            <p class="text-gray-600">No study materials yet.</p>
                            <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($recent_materials as $material): ?>
                                <div class="p-3 border border-gray-200 rounded-lg hover:shadow-md transition">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-medium text-navy"><?php echo htmlspecialchars($material['title']); ?></h4>
                                            <p class="text-sm text-gray-600">Course ID: <?php echo htmlspecialchars($material['course_id']); ?></p>
                                            <p class="text-xs text-gray-500">Uploaded: <?php echo date('M d, Y', strtotime($material['created_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    <!-- Announcements Section -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <h2 class="text-xl font-bold text-navy mb-6">Latest Announcements</h2>
                        <?php if (empty($announcements)): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-bullhorn text-4xl mb-3 text-gray-300"></i>
                                <p>No announcements yet</p>
                                <p class="text-sm mt-2">Check back later for updates</p>
                            </div>
                        <?php else: ?>
                            <div class="announcements-scroll-container">
                                <div class="space-y-4">
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="border border-gray-200 rounded-lg p-4 bg-white hover:shadow-md transition-shadow">
                                            <div class="flex justify-between items-start mb-2">
                                                <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($announcement['title']) ?></h3>
                                                <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800">
                                                    Published
                                                </span>
                                            </div>
                                            
                                            <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($announcement['message']) ?></p>
                                            
                                            <div class="flex flex-wrap gap-1 mb-3">
                                                <?php foreach ($announcement['targets'] as $target): ?>
                                                    <span class="announcement-badge announcement-<?= $target ?>">
                                                        <i class="fas fa-<?= 
                                                            $target === 'teacher' ? 'chalkboard-teacher' : 
                                                            ($target === 'student' ? 'user-graduate' : 
                                                            ($target === 'parent' ? 'users' : 'code')) 
                                                        ?> mr-1"></i>
                                                        <?= ucfirst($target) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <div class="flex justify-between items-center text-xs text-gray-500">
                                                
                                                <span>
                                                    <?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="mt-4 pt-4 border-t">
                            
                        </div>
                    </div>

                    <!-- Upcoming Live Lessons -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <h2 class="text-xl font-bold text-navy mb-6">Upcoming Live Lessons</h2>
                        <?php if (empty($upcoming_lessons)): ?>
                        <p class="text-gray-600">No upcoming lessons.</p>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($upcoming_lessons as $lesson): ?>
                            <div class="p-3 border border-gray-200 rounded-lg">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-medium text-navy"><?php echo htmlspecialchars($lesson['lesson_name']); ?></h3>
                                    <span class="bg-blue-100 text-blue-800 text-xs py-1 px-2 rounded-full"><?php echo $lesson['day']; ?></span>
                                </div>
                                <p class="text-sm text-gray-600"><i class="far fa-clock mr-2"></i><?php echo $lesson['time']; ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="mt-4 pt-4 border-t">
                            <a href="live_lessons_cont_dev.php" class="text-purple hover:text-indigo-700 font-medium flex items-center">
                                <i class="fas fa-plus mr-2"></i> Schedule New Lesson
                            </a>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                        <h2 class="text-xl font-bold text-navy mb-6">Quick Actions</h2>
                        <div class="space-y-3">
                            <a href="course_content_dev.php" class="block p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-book-open text-blue-600"></i>
                                    </div>
                                    <span class="font-medium text-navy">Manage Courses</span>
                                </div>
                            </a>
                            <a href="analytics_cont_dev.php" class="block p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-chart-bar text-purple-600"></i>
                                    </div>
                                    <span class="font-medium text-navy">View Analytics</span>
                                </div>
                            </a>
                            <a href="settings_cont_dev.php" class="block p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-cog text-green-600"></i>
                                    </div>
                                    <span class="font-medium text-navy">Settings</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile sidebar toggle
        const menuButton = document.getElementById('menuButton');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        menuButton.addEventListener('click', toggleSidebar);
        closeSidebar.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Tab switching
        const tabs = document.querySelectorAll('[data-tab]');
        const tabContents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and contents
                tabs.forEach(t => t.classList.remove('border-purple', 'text-navy'));
                tabs.forEach(t => t.classList.add('text-gray-500'));
                tabContents.forEach(content => content.classList.add('hidden'));

                // Add active class to clicked tab
                tab.classList.remove('text-gray-500');
                tab.classList.add('border-purple', 'text-navy');

                // Show corresponding content
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(`${tabId}-form`).classList.remove('hidden');
            });
        });

        // Notification Widget Functionality
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
            
            // Load notifications if dropdown is being opened
            if (dropdown.classList.contains('active')) {
                loadNotifications();
            }
        }

        function loadNotifications() {
            fetch('api/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    displayNotifications(data);
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    document.getElementById('notificationsList').innerHTML = `
                        <div class="p-4 text-center text-red-500">
                            <i class="fas fa-exclamation-triangle mb-2"></i>
                            <p>Failed to load notifications</p>
                        </div>
                    `;
                });
        }

        function displayNotifications(notifications) {
            const notificationsList = document.getElementById('notificationsList');
            const unreadCount = notifications.filter(n => !n.is_read).length;
            
            // Update badge
            const badge = document.getElementById('notificationBadge');
            if (unreadCount > 0) {
                badge.textContent = unreadCount;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
            
            // Display notifications
            if (notifications.length === 0) {
                notificationsList.innerHTML = `
                    <div class="p-8 text-center text-gray-500">
                        <i class="fas fa-bell-slash text-2xl mb-2"></i>
                        <p>No notifications</p>
                    </div>
                `;
                return;
            }
            
            notificationsList.innerHTML = notifications.map(notification => `
                <div class="notification-item ${notification.is_read ? '' : 'unread'}" onclick="markAsRead(${notification.id})">
                    <div class="flex items-start space-x-3">
                        <div class="notification-icon ${getNotificationIconClass(notification.type)}">
                            <i class="${getNotificationIcon(notification.type)}"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-900">${notification.title}</h4>
                            <p class="text-sm text-gray-600 mt-1">${notification.message}</p>
                            <p class="text-xs text-gray-500 mt-2">${formatTime(notification.created_at)}</p>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function getNotificationIcon(type) {
            const icons = {
                'announcement': 'fas fa-bullhorn',
                'assignment': 'fas fa-tasks',
                'grade': 'fas fa-chart-line',
                'system': 'fas fa-cog',
                'default': 'fas fa-bell'
            };
            return icons[type] || icons.default;
        }

        function getNotificationIconClass(type) {
            const classes = {
                'announcement': 'bg-blue-100 text-blue-600',
                'assignment': 'bg-green-100 text-green-600',
                'grade': 'bg-purple-100 text-purple-600',
                'system': 'bg-gray-100 text-gray-600',
                'default': 'bg-yellow-100 text-yellow-600'
            };
            return classes[type] || classes.default;
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 7) return `${diffDays}d ago`;
            return date.toLocaleDateString();
        }

        function markAsRead(notificationId) {
            fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            })
            .catch(error => console.error('Error marking as read:', error));
        }

        function markAllAsRead() {
            fetch('api/mark_all_notifications_read.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadNotifications();
                    }
                })
                .catch(error => console.error('Error marking all as read:', error));
        }

        function filterNotifications(filter) {
            const tabs = document.querySelectorAll('.filter-tab');
            tabs.forEach(tab => {
                if (tab.getAttribute('data-filter') === filter) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
            
            // Reload notifications with filter
            fetch(`api/get_notifications.php?filter=${filter}`)
                .then(response => response.json())
                .then(data => {
                    displayNotifications(data);
                })
                .catch(error => {
                    console.error('Error loading filtered notifications:', error);
                });
        }

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const widget = document.getElementById('notificationWidget');
            if (!widget.contains(event.target)) {
                document.getElementById('notificationDropdown').classList.remove('active');
            }
        });

        // Load initial notification count
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
        });
    </script>
</body>
</html>