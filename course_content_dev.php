<?php
// course_content_dev.php - Enhanced with real-time database synchronization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'models/Course.php';

requireRole('content');

$course = new Course();
$flash = null;

// Handle AJAX requests for real-time updates
if (isset($_GET['action']) && $_GET['action'] === 'get_updates') {
    header('Content-Type: application/json');
    
    $user_id = $_SESSION['user_id'];
    $all_courses = $course->getAll();
    
    $all_content = [];
    foreach ($all_courses as $courseItem) {
        $content = $course->getCourseContent($courseItem['id']);
        $all_content[$courseItem['id']] = [
            'course' => $courseItem,
            'content' => $content
        ];
    }
    
    echo json_encode([
        'success' => true,
        'courses' => $all_courses,
        'content' => $all_content,
        'timestamp' => time()
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create course content
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        // Validate required fields
        if (empty($_POST['course_id'])) {
            $flash = ['text' => 'Please select a course', 'type' => 'error'];
        } elseif (empty($_POST['title'])) {
            $flash = ['text' => 'Please enter a title', 'type' => 'error'];
        } else {
            // Handle file upload
            $file_url = trim($_POST['url'] ?? '');
            
            if (!empty($_FILES['content_file']['name'])) {
                $upload_dir = 'uploads/course_materials/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['content_file']['name'], PATHINFO_EXTENSION);
                $file_name = time() . '_' . uniqid() . '.' . $file_extension;
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['content_file']['tmp_name'], $target_file)) {
                    $file_url = $target_file;
                } else {
                    $flash = ['text' => 'Failed to upload file', 'type' => 'error'];
                }
            }
            
            if (!$flash) {
                $content_type = $_POST['content_type'] ?? 'lesson';
                
                $contentData = [
                    'course_id' => (int)$_POST['course_id'],
                    'content_type' => $content_type,
                    'title' => trim($_POST['title']),
                    'description' => trim($_POST['description'] ?? ''),
                    'url' => $file_url,
                    'order_index' => (int)($_POST['order_index'] ?? 1),
                    'quiz_content' => $content_type === 'quiz' ? trim($_POST['quiz_content'] ?? '[]') : '',
                    'quiz_settings' => null,
                    'open_date' => !empty($_POST['open_date']) ? $_POST['open_date'] : null,
                    'close_date' => !empty($_POST['close_date']) ? $_POST['close_date'] : null
                ];
                
                // Debug: Log the data being sent
                error_log("Creating content with data: " . json_encode($contentData));
                
                $result = $course->createContent($contentData);
                
                // Debug: Log the result
                error_log("Create content result: " . json_encode($result));
                
                if ($result['success']) {
                    // Log the action to admin_logs for tracking
                    try {
                        $conn = new PDO("mysql:host=localhost;dbname=novatech_db", "root", "");
                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, target_type, target_id, details) 
                                                   VALUES (:admin_id, :action_type, :target_type, :target_id, :details)");
                        $logStmt->execute([
                            ':admin_id' => $_SESSION['user_id'],
                            ':action_type' => 'create',
                            ':target_type' => 'course_content',
                            ':target_id' => $result['content_id'],
                            ':details' => json_encode([
                                'course_id' => $contentData['course_id'],
                                'title' => $contentData['title'],
                                'type' => $contentData['content_type']
                            ])
                        ]);
                    } catch (Exception $e) {
                        error_log("Failed to log action: " . $e->getMessage());
                    }
                    
                    // If it's a quiz, redirect to edit_quiz.php
                    if ($content_type === 'quiz') {
                        header("Location: edit_quiz.php?id=" . $result['content_id'] . "&new=1");
                        exit;
                    }
                }
                
                $flash = ['text' => $result['success'] ? 'Content created successfully!' : ($result['error'] ?? 'Unknown error occurred'), 'type' => $result['success'] ? 'success' : 'error'];
            }
        }
    }
    
    // Update course content
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        // Handle file upload for update
        $file_url = trim($_POST['url'] ?? '');
        
        if (!empty($_FILES['content_file']['name'])) {
            $upload_dir = 'uploads/course_materials/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['content_file']['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['content_file']['tmp_name'], $target_file)) {
                $file_url = $target_file;
            }
        }
        
        $contentData = [
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description']),
            'url' => $file_url,
            'order_index' => (int)($_POST['order_index'] ?? 1),
            'quiz_content' => trim($_POST['quiz_content'] ?? ''),
            'open_date' => !empty($_POST['open_date']) ? $_POST['open_date'] : null,
            'close_date' => !empty($_POST['close_date']) ? $_POST['close_date'] : null
        ];
        
        $result = $course->updateContent($_POST['content_id'], $contentData);
        
        if ($result['success']) {
            // Log the action
            try {
                $conn = new PDO("mysql:host=localhost;dbname=novatech_db", "root", "");
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, target_type, target_id, details) 
                                           VALUES (:admin_id, :action_type, :target_type, :target_id, :details)");
                $logStmt->execute([
                    ':admin_id' => $_SESSION['user_id'],
                    ':action_type' => 'update',
                    ':target_type' => 'course_content',
                    ':target_id' => $_POST['content_id'],
                    ':details' => json_encode([
                        'title' => $contentData['title'],
                        'changes' => array_keys($contentData)
                    ])
                ]);
            } catch (Exception $e) {
                error_log("Failed to log action: " . $e->getMessage());
            }
        }
        
        $flash = ['text' => $result['success'] ? 'Content updated successfully!' : $result['error'], 'type' => $result['success'] ? 'success' : 'error'];
    }
    
    // Delete course content
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $result = $course->deleteContent($_POST['content_id']);
        
        if ($result['success']) {
            // Log the action
            try {
                $conn = new PDO("mysql:host=localhost;dbname=novatech_db", "root", "");
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $logStmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action_type, target_type, target_id, details) 
                                           VALUES (:admin_id, :action_type, :target_type, :target_id, :details)");
                $logStmt->execute([
                    ':admin_id' => $_SESSION['user_id'],
                    ':action_type' => 'delete',
                    ':target_type' => 'course_content',
                    ':target_id' => $_POST['content_id'],
                    ':details' => json_encode(['deleted_at' => date('Y-m-d H:i:s')])
                ]);
            } catch (Exception $e) {
                error_log("Failed to log action: " . $e->getMessage());
            }
        }
        
        $flash = ['text' => $result['success'] ? 'Content deleted successfully!' : $result['error'], 'type' => $result['success'] ? 'success' : 'error'];
    }
}

$user_id = $_SESSION['user_id'];
$display_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$initials = strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1));

// Get all courses (from courses table)
$all_courses = $course->getAll();

// Get all course content grouped by course
$all_content = [];
foreach ($all_courses as $courseItem) {
    $content = $course->getCourseContent($courseItem['id']);
    $all_content[$courseItem['id']] = [
        'course' => $courseItem,
        'content' => $content
    ];
}

// Calculate statistics
$total_courses = count($all_courses);
$total_content = 0;
foreach ($all_content as $data) {
    $total_content += count($data['content']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Content - NovaTech FET College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .course-card { 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            border-radius: 1rem;
        }
        .course-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); 
        }
        .sidebar { transition: all 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }

        /* Real-time sync indicator */
        .sync-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 12px 20px;
            border-radius: 50px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sync-indicator.syncing {
            background: #fef3c7;
            border: 2px solid var(--gold);
        }

        .sync-indicator.synced {
            background: #dcfce7;
            border: 2px solid #22c55e;
        }

        .sync-indicator.error {
            background: #fee2e2;
            border: 2px solid #ef4444;
        }

        .sync-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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

        /* Content update highlight */
        .content-updated {
            animation: highlightUpdate 2s ease-out;
        }

        @keyframes highlightUpdate {
            0% { background-color: #fef3c7; }
            100% { background-color: transparent; }
        }
    </style>
</head>
<body class="bg-beige">
    <div class="overlay" id="overlay"></div>
    
    <!-- Real-time Sync Indicator -->
    <div class="sync-indicator" id="syncIndicator">
        <div class="sync-dot bg-green-500"></div>
        <span class="text-sm font-medium text-gray-700">Synced</span>
    </div>
    
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
                    <li><a href="content_dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-tachometer-alt mr-3"></i><span>Dashboard</span></a></li>
                    <li><a href="course_content_dev.php" class="flex items-center p-2 rounded-lg bg-purple text-white"><i class="fas fa-book mr-3"></i><span>Courses</span></a></li>
                    <li><a href="mock_exams_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-file-alt mr-3"></i><span>Mock Exams</span></a></li>
                    <li><a href="study_materials_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-book-open mr-3"></i><span>Study Materials</span></a></li>
                    <li><a href="past_papers_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-file-pdf mr-3"></i><span>Past Papers</span></a></li>
                    <li><a href="analytics_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-chart-bar mr-3"></i><span>Analytics</span></a></li>
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
                    <h1 class="text-xl font-bold text-navy">Course Content Management</h1>
                    <div class="flex items-center space-x-4">
                        <button class="bg-purple text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition" onclick="openCreateModal()">
                            <i class="fas fa-plus mr-2"></i>Add Content
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

        <main class="container mx-auto px-6 py-8">
            <?php if ($flash): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $flash['type'] === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                <?php echo htmlspecialchars($flash['text']); ?>
            </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8" id="summaryCards">
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-book text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy" id="totalCourses"><?php echo $total_courses; ?></h3>
                    <p class="text-gray-600">Courses</p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-list text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy" id="totalContent"><?php echo $total_content; ?></h3>
                    <p class="text-gray-600">Content Items</p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-users text-purple-600 text-xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy" id="totalEnrollments"><?php echo array_sum(array_column($all_courses, 'enrollment_count')); ?></h3>
                    <p class="text-gray-600">Total Enrollments</p>
                </div>
            </div>

            <!-- Courses with Content -->
            <div class="space-y-6" id="coursesContainer">
                <?php if (empty($all_courses)): ?>
                <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                    <div class="text-gray-400 mb-4"><i class="fas fa-book text-6xl"></i></div>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Courses Found</h3>
                    <p class="text-gray-500">Contact your administrator to set up courses.</p>
                </div>
                <?php else: ?>
                <?php foreach ($all_content as $course_id => $data): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 course-card" data-course-id="<?php echo $course_id; ?>">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-navy"><?php echo htmlspecialchars($data['course']['course_name']); ?></h3>
                            <p class="text-sm text-gray-600">Course ID: <?php echo $course_id; ?></p>
                        </div>
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 text-xs rounded-full" data-content-count="<?php echo count($data['content']); ?>">
                            <?php echo count($data['content']); ?> items
                        </span>
                    </div>
                    
                    <?php if (empty($data['content'])): ?>
                    <div class="text-center py-6 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 mb-3">No content yet for this course</p>
                        <button onclick="openCreateModal(<?php echo $course_id; ?>)" class="bg-purple text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition text-sm">
                            <i class="fas fa-plus mr-2"></i>Add First Content
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="space-y-2 content-list" data-course-id="<?php echo $course_id; ?>">
                        <?php foreach ($data['content'] as $content): ?>
                        <div class="p-3 border border-gray-200 rounded-lg flex justify-between items-center hover:bg-gray-50" data-content-id="<?php echo $content['id']; ?>">
                            <div class="flex items-center flex-1">
                                <div class="w-8 h-8 bg-gray-100 rounded flex items-center justify-center mr-3">
                                    <?php
                                    $icon = 'fa-file';
                                    if ($content['content_type'] === 'video') $icon = 'fa-video';
                                    elseif ($content['content_type'] === 'pdf') $icon = 'fa-file-pdf';
                                    elseif ($content['content_type'] === 'quiz') $icon = 'fa-question-circle';
                                    ?>
                                    <i class="fas <?php echo $icon; ?> text-gray-600 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium text-navy content-title"><?php echo htmlspecialchars($content['title']); ?></h4>
                                    <p class="text-xs text-gray-500"><?php echo ucfirst($content['content_type']); ?> • Order: <?php echo $content['order_index']; ?></p>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <?php if (!empty($content['url'])): ?>
                                <a href="<?php echo htmlspecialchars($content['url']); ?>" target="_blank" class="text-blue-600 hover:text-blue-700 px-2 py-1">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <?php endif; ?>
                                <button onclick="editContent(<?php echo $content['id']; ?>, <?php echo $course_id; ?>)" class="text-gray-600 hover:text-gray-700 px-2 py-1">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteContent(<?php echo $content['id']; ?>)" class="text-red-600 hover:text-red-700 px-2 py-1">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 pt-4 border-t">
                        <button onclick="openCreateModal(<?php echo $course_id; ?>)" class="text-purple hover:text-indigo-700 font-medium text-sm">
                            <i class="fas fa-plus mr-2"></i>Add More Content
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Content Modal -->
    <div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-navy">Add Course Content</h2>
                <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="create">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Course *</label>
                    <select name="course_id" id="create_course_id" class="w-full p-2 border border-gray-300 rounded-lg" required>
                        <option value="">Select a Course</option>
                        <?php foreach ($all_courses as $courseItem): ?>
                        <option value="<?php echo $courseItem['id']; ?>"><?php echo htmlspecialchars($courseItem['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Content Type *</label>
                    <select name="content_type" class="w-full p-2 border border-gray-300 rounded-lg">
                        <option value="lesson">Lesson</option>
                        <option value="video">Video</option>
                        <option value="pdf">PDF</option>
                        <option value="quiz">Quiz</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" name="title" class="w-full p-2 border border-gray-300 rounded-lg" placeholder="Content title" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full p-2 border border-gray-300 rounded-lg" placeholder="Brief description"></textarea>
                </div>
                
                <div class="quiz-field hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quiz Questions</label>
                    <textarea name="quiz_content" rows="5" class="w-full p-2 border border-gray-300 rounded-lg" placeholder="Enter quiz questions (one per line or separated by double newlines)"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload PDF File</label>
                    <input type="file" name="content_file" accept=".pdf,.doc,.docx" class="w-full p-2 border border-gray-300 rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Upload a PDF/DOC file or use URL below</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Or Enter URL</label>
                    <input type="url" name="url" class="w-full p-2 border border-gray-300 rounded-lg" placeholder="https://...">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Open Date (Optional)</label>
                    <input type="datetime-local" name="open_date" class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Close Date (Optional)</label>
                    <input type="datetime-local" name="close_date" class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Order Index</label>
                    <input type="number" name="order_index" value="1" min="1" class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                
                <div class="flex space-x-2 pt-4">
                    <button type="button" onclick="closeCreateModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-purple text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition">Add Content</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Content Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-navy">Edit Content</h2>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="content_id" id="edit_content_id">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" name="title" id="edit_title" class="w-full p-2 border border-gray-300 rounded-lg" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="edit_description" rows="3" class="w-full p-2 border border-gray-300 rounded-lg"></textarea>
                </div>
                
                <div class="quiz-field hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quiz Questions</label>
                    <textarea name="quiz_content" id="edit_quiz_content" rows="5" class="w-full p-2 border border-gray-300 rounded-lg" placeholder="Enter quiz questions (one per line or separated by double newlines)"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Upload New PDF File</label>
                    <input type="file" name="content_file" accept=".pdf,.doc,.docx" class="w-full p-2 border border-gray-300 rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Leave empty to keep existing file</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Or Update URL</label>
                    <input type="url" name="url" id="edit_url" class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Open Date (Optional)</label>
                    <input type="datetime-local" name="open_date" id="edit_open_date" class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Close Date (Optional)</label>
                    <input type="datetime-local" name="close_date" id="edit_close_date" class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Order Index</label>
                    <input type="number" name="order_index" id="edit_order_index" min="1" class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                
                <div class="flex space-x-2 pt-4">
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-purple text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
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

let allContent = <?php echo json_encode($all_content); ?>;
let syncInterval;
let lastSyncTimestamp = Date.now();

// Real-time synchronization functions
function updateSyncIndicator(status, message) {
    const indicator = document.getElementById('syncIndicator');
    const dot = indicator.querySelector('.sync-dot');
    const text = indicator.querySelector('span');
    
    indicator.className = 'sync-indicator ' + status;
    text.textContent = message;
    
    if (status === 'syncing') {
        dot.className = 'sync-dot bg-yellow-500';
    } else if (status === 'synced') {
        dot.className = 'sync-dot bg-green-500';
    } else if (status === 'error') {
        dot.className = 'sync-dot bg-red-500';
    }
}

function syncWithDatabase() {
    updateSyncIndicator('syncing', 'Syncing...');
    
    fetch('?action=get_updates&t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updatePageContent(data);
                lastSyncTimestamp = data.timestamp;
                updateSyncIndicator('synced', 'Synced');
            } else {
                updateSyncIndicator('error', 'Sync failed');
            }
        })
        .catch(error => {
            console.error('Sync error:', error);
            updateSyncIndicator('error', 'Sync failed');
        });
}

function updatePageContent(data) {
    // Update statistics
    const totalCourses = data.courses.length;
    let totalContent = 0;
    let totalEnrollments = 0;
    
    for (const courseId in data.content) {
        totalContent += data.content[courseId].content.length;
    }
    
    data.courses.forEach(course => {
        totalEnrollments += parseInt(course.enrollment_count) || 0;
    });
    
    document.getElementById('totalCourses').textContent = totalCourses;
    document.getElementById('totalContent').textContent = totalContent;
    document.getElementById('totalEnrollments').textContent = totalEnrollments;
    
    // Update course content
    const coursesContainer = document.getElementById('coursesContainer');
    
    for (const courseId in data.content) {
        const courseData = data.content[courseId];
        const courseCard = document.querySelector(`[data-course-id="${courseId}"]`);
        
        if (courseCard) {
            // Update content count badge
            const badge = courseCard.querySelector('[data-content-count]');
            if (badge) {
                const newCount = courseData.content.length;
                const oldCount = parseInt(badge.getAttribute('data-content-count'));
                
                if (newCount !== oldCount) {
                    badge.textContent = newCount + ' items';
                    badge.setAttribute('data-content-count', newCount);
                    badge.classList.add('content-updated');
                    setTimeout(() => badge.classList.remove('content-updated'), 2000);
                }
            }
            
            // Update content list
            updateContentList(courseId, courseData.content);
        }
    }
    
    // Update global content reference
    allContent = data.content;
}

function updateContentList(courseId, contentItems) {
    const contentList = document.querySelector(`.content-list[data-course-id="${courseId}"]`);
    if (!contentList) return;
    
    // Get current content IDs
    const currentIds = Array.from(contentList.querySelectorAll('[data-content-id]'))
        .map(el => el.getAttribute('data-content-id'));
    
    const newIds = contentItems.map(item => item.id.toString());
    
    // Check for changes
    const hasChanges = JSON.stringify(currentIds.sort()) !== JSON.stringify(newIds.sort());
    
    if (hasChanges) {
        // Rebuild content list
        contentList.innerHTML = '';
        
        contentItems.forEach(content => {
            const contentElement = createContentElement(content, courseId);
            contentList.appendChild(contentElement);
        });
    } else {
        // Update existing items
        contentItems.forEach(content => {
            const contentElement = contentList.querySelector(`[data-content-id="${content.id}"]`);
            if (contentElement) {
                const titleElement = contentElement.querySelector('.content-title');
                if (titleElement && titleElement.textContent !== content.title) {
                    titleElement.textContent = content.title;
                    contentElement.classList.add('content-updated');
                    setTimeout(() => contentElement.classList.remove('content-updated'), 2000);
                }
            }
        });
    }
}

function createContentElement(content, courseId) {
    const div = document.createElement('div');
    div.className = 'p-3 border border-gray-200 rounded-lg flex justify-between items-center hover:bg-gray-50';
    div.setAttribute('data-content-id', content.id);
    
    const icons = {
        'video': 'fa-video',
        'pdf': 'fa-file-pdf',
        'quiz': 'fa-question-circle',
        'lesson': 'fa-file'
    };
    
    const icon = icons[content.content_type] || 'fa-file';
    
    div.innerHTML = `
        <div class="flex items-center flex-1">
            <div class="w-8 h-8 bg-gray-100 rounded flex items-center justify-center mr-3">
                <i class="fas ${icon} text-gray-600 text-sm"></i>
            </div>
            <div class="flex-1">
                <h4 class="font-medium text-navy content-title">${escapeHtml(content.title)}</h4>
                <p class="text-xs text-gray-500">${content.content_type.charAt(0).toUpperCase() + content.content_type.slice(1)} • Order: ${content.order_index}</p>
            </div>
        </div>
        <div class="flex space-x-2">
            ${content.url ? `<a href="${escapeHtml(content.url)}" target="_blank" class="text-blue-600 hover:text-blue-700 px-2 py-1"><i class="fas fa-external-link-alt"></i></a>` : ''}
            <button onclick="editContent(${content.id}, ${courseId})" class="text-gray-600 hover:text-gray-700 px-2 py-1"><i class="fas fa-edit"></i></button>
            <button onclick="deleteContent(${content.id})" class="text-red-600 hover:text-red-700 px-2 py-1"><i class="fas fa-trash"></i></button>
        </div>
    `;
    
    return div;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Start auto-sync every 10 seconds
function startAutoSync() {
    syncInterval = setInterval(syncWithDatabase, 10000);
}

// Stop auto-sync
function stopAutoSync() {
    if (syncInterval) {
        clearInterval(syncInterval);
    }
}

// Modal functions
function openCreateModal(courseId = null) {
    if (courseId) {
        document.getElementById('create_course_id').value = courseId;
    }
    const quizField = document.querySelector('#createModal .quiz-field');
    const contentTypeSelect = document.querySelector('#createModal [name="content_type"]');
    quizField.classList.toggle('hidden', contentTypeSelect.value !== 'quiz');
    contentTypeSelect.addEventListener('change', () => {
        quizField.classList.toggle('hidden', contentTypeSelect.value !== 'quiz');
    });
    document.getElementById('createModal').classList.remove('hidden');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function editContent(contentId, courseId) {
    const content = allContent[courseId].content.find(c => c.id == contentId);
    if (content) {
        if (content.content_type === 'quiz') {
            window.location.href = 'edit_quiz.php?id=' + contentId;
            return;
        }
        
        document.getElementById('edit_content_id').value = content.id;
        document.getElementById('edit_title').value = content.title;
        document.getElementById('edit_description').value = content.description || '';
        document.getElementById('edit_url').value = content.url || '';
        document.getElementById('edit_order_index').value = content.order_index;
        
        // Set date fields if available
        if (content.open_date) {
            document.getElementById('edit_open_date').value = formatDateTimeLocal(content.open_date);
        }
        if (content.close_date) {
            document.getElementById('edit_close_date').value = formatDateTimeLocal(content.close_date);
        }
        
        const quizField = document.querySelector('#editModal .quiz-field');
        quizField.classList.add('hidden');
        
        document.getElementById('editModal').classList.remove('hidden');
    }
}

function formatDateTimeLocal(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function deleteContent(contentId) {
    if (confirm('Delete this content item? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="content_id" value="${contentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Notification Widget JavaScript
let currentFilter = 'all';
let notificationCheckInterval;

function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('active');
    
    if (dropdown.classList.contains('active')) {
        loadNotifications();
    }
}

document.addEventListener('click', function(event) {
    const widget = document.getElementById('notificationWidget');
    if (widget && !widget.contains(event.target)) {
        document.getElementById('notificationDropdown').classList.remove('active');
    }
});

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

function handleNotificationClick(notificationId, isRead) {
    if (!isRead) {
        markAsRead(notificationId);
    }
}

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

function updateNotificationCount() {
    fetch('api/get_notification_count.php')
        .then(response => response.json())
        .then(data => {
            updateNotificationBadge(data.count);
        })
        .catch(error => console.error('Error:', error));
}

function startNotificationChecking() {
    updateNotificationCount();
    notificationCheckInterval = setInterval(updateNotificationCount, 30000);
}

function stopNotificationChecking() {
    if (notificationCheckInterval) {
        clearInterval(notificationCheckInterval);
    }
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        startAutoSync();
        startNotificationChecking();
    });
} else {
    startAutoSync();
    startNotificationChecking();
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    stopAutoSync();
    stopNotificationChecking();
});

// Refresh page content after form submission
if (window.performance && performance.navigation.type === performance.navigation.TYPE_RELOAD) {
    setTimeout(syncWithDatabase, 500);
}
    </script>
</body>
</html>