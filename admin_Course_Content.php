<?php
require_once 'includes/config.php';

// Get admin info
$admin_id = $_SESSION['user_id'] ?? 1;
$admin_name = ($_SESSION['first_name'] ?? 'Admin') . ' ' . ($_SESSION['last_name'] ?? '');

// Create uploads directory if not exists
$uploadDir = 'uploads/course_content/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Fetch courses - PDO VERSION
$courses = [];
try {
    $courses_result = $conn->query("SELECT * FROM courses ORDER BY course_name");
    $courses = $courses_result->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching courses: " . $e->getMessage());
}

// Fetch all content with course information - PDO VERSION
$all_content = [];
try {
    $content_result = $conn->query("
        SELECT cc.*, c.course_name 
        FROM course_content cc
        JOIN courses c ON cc.course_id = c.id
        ORDER BY cc.id DESC
    ");
    $all_content = $content_result->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching content: " . $e->getMessage());
}

// Fetch study materials - PDO VERSION
$study_materials = [];
try {
    $materials_result = $conn->query("
        SELECT sm.*, c.course_name 
        FROM study_materials sm
        JOIN courses c ON sm.course_id = c.id
        ORDER BY sm.created_at DESC
    ");
    $study_materials = $materials_result->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching materials: " . $e->getMessage());
}

// Fetch past papers - PDO VERSION
$past_papers = [];
try {
    $papers_result = $conn->query("
        SELECT ep.*, c.course_name 
        FROM exam_papers ep
        LEFT JOIN courses c ON ep.course_id = c.id
        ORDER BY ep.year DESC, ep.uploaded_at DESC
    ");
    $past_papers = $papers_result->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching papers: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_course'])) {
        $course_name = trim($_POST['course_name']);
        try {
            $stmt = $conn->prepare("INSERT INTO courses (course_name) VALUES (?)");
            if ($stmt->execute([$course_name])) {
                $success_message = "Course added successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success_message));
                exit;
            }
        } catch (PDOException $e) {
            $error_message = "Error adding course: " . $e->getMessage();
        }
    }

    if (isset($_POST['add_content'])) {
        $course_id = $_POST['course_id'];
        $content_type = $_POST['content_type'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $url = trim($_POST['url'] ?? '');
        $order_index = intval($_POST['order_index'] ?? 0);
        $file_path = null;

        // Handle PDF upload
        if ($content_type === 'pdf' && isset($_FILES['content_file']) && $_FILES['content_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['content_file'];
            $allowedTypes = ['application/pdf'];
            $maxSize = 20 * 1024 * 1024; // 20MB

            if (!in_array($file['type'], $allowedTypes)) {
                $error_message = "Only PDF files are allowed.";
            } elseif ($file['size'] > $maxSize) {
                $error_message = "File size exceeds 20MB limit.";
            } else {
                $filename = uniqid() . '_' . basename($file['name']);
                $file_path = $uploadDir . $filename;
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    $error_message = "Failed to upload file.";
                }
            }
        }

        if (!isset($error_message)) {
            $final_url = $file_path ?: $url;

            try {
                $stmt = $conn->prepare("
                    INSERT INTO course_content (course_id, content_type, title, description, url, order_index) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                if ($stmt->execute([$course_id, $content_type, $title, $description, $final_url, $order_index])) {
                    // Get course name for notification
                    $course_stmt = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
                    $course_stmt->execute([$course_id]);
                    $course = $course_stmt->fetch(PDO::FETCH_ASSOC);
                    $course_name = $course['course_name'] ?? 'Unknown Course';
                    
                    $message = "Admin added new " . ucfirst($content_type) . ": '$title' to $course_name";
                    notifyAllContentDevelopers($conn, 'content_update', $message);
                    
                    $success_message = "Content added successfully!";
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success_message));
                    exit;
                }
            } catch (PDOException $e) {
                $error_message = "Error adding content: " . $e->getMessage();
                // Clean up uploaded file if DB fails
                if ($file_path && file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
    }

    if (isset($_POST['delete_content'])) {
        $content_id = $_POST['content_id'];
        
        try {
            // Get content info before deletion
            $info_stmt = $conn->prepare("
                SELECT cc.title, cc.content_type, c.course_name 
                FROM course_content cc 
                JOIN courses c ON cc.course_id = c.id 
                WHERE cc.id = ?
            ");
            $info_stmt->execute([$content_id]);
            $content_info = $info_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete content
            $stmt = $conn->prepare("DELETE FROM course_content WHERE id = ?");
            if ($stmt->execute([$content_id])) {
                if ($content_info) {
                    $message = "Admin deleted " . ucfirst($content_info['content_type']) . ": '" . $content_info['title'] . "' from " . $content_info['course_name'];
                    notifyAllContentDevelopers($conn, 'content_delete', $message);
                }
                $success_message = "Content deleted successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success_message));
                exit;
            }
        } catch (PDOException $e) {
            $error_message = "Error deleting content: " . $e->getMessage();
        }
    }

    if (isset($_POST['delete_material'])) {
        $material_id = $_POST['material_id'];
        
        try {
            // Get material info
            $stmt = $conn->prepare("
                SELECT sm.file_path, sm.title, c.course_name, sm.uploaded_by 
                FROM study_materials sm 
                JOIN courses c ON sm.course_id = c.id 
                WHERE sm.id = ?
            ");
            $stmt->execute([$material_id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete material
            $delete_stmt = $conn->prepare("DELETE FROM study_materials WHERE id = ?");
            if ($delete_stmt->execute([$material_id])) {
                if ($material && file_exists($material['file_path'])) {
                    unlink($material['file_path']);
                }
                if ($material && $material['uploaded_by']) {
                    $message = "Admin deleted your study material: '" . $material['title'] . "' from " . $material['course_name'];
                    sendNotification($material['uploaded_by'], 'content_delete', $message);
                }
                $success_message = "Study material deleted successfully!";
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success_message));
                exit;
            }
        } catch (PDOException $e) {
            $error_message = "Error deleting material: " . $e->getMessage();
        }
    }
}

if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

function getContentTypeClass($type) {
    return match($type) {
        'lesson' => 'bg-blue-100 text-blue-800',
        'video' => 'bg-purple-100 text-purple-800',
        'pdf' => 'bg-red-100 text-red-800',
        'quiz' => 'bg-green-100 text-green-800',
        default => 'bg-gray-100 text-gray-800'
    };
}
function getContentTypeIcon($type) {
    return match($type) {
        'lesson' => 'fa-book-open',
        'video' => 'fa-video',
        'pdf' => 'fa-file-pdf',
        'quiz' => 'fa-question-circle',
        default => 'fa-file'
    };
}
function getCategoryClass($category) {
    return match($category) {
        'study_guide' => 'bg-blue-100 text-blue-800',
        'reference' => 'bg-purple-100 text-purple-800',
        'worksheet' => 'bg-green-100 text-green-800',
        'notes' => 'bg-yellow-100 text-yellow-800',
        'answer_key' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800'
    };
}
?>
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
        .dashboard-card { transition: transform 0.3s ease, box-shadow 0.3s ease; backdrop-filter: blur(10px); }
        .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15); }
        .sidebar { transition: all 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
        .active-nav-item { background-color: var(--gold) !important; color: var(--navy) !important; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0, 0, 0, 0.5); 
            z-index: 1000; 
            align-items: center; 
            justify-content: center; 
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 0.5rem;
            width: 100%;
            max-width: 42rem;
            max-height: 90vh;
            overflow-y: auto;
        }
        .tab-button {
            cursor: pointer;
            padding: 1rem 1.5rem;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }
        .tab-button.active {
            border-bottom-color: var(--gold);
            color: var(--gold);
        }
        .tab-button:hover:not(.active) {
            border-bottom-color: #ddd;
        }
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
                <div class="w-12 h-12 bg-gold rounded-full flex items-center justify-center mr-3">
                    <span class="text-navy font-bold">AD</span>
                </div>
                <div>
                    <h3 class="font-semibold">Admin Panel</h3>
                    <p class="text-sm mt-1 text-gold">System Administrator</p>
                </div>
            </div>
        </div>
        <nav>
            <ul class="space-y-2">
                <li><a href="admin_dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-tachometer-alt mr-3"></i> Dashboard</a></li>
                <li><a href="user_management.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-users mr-3"></i> User Management</a></li>
				<li><a href="master-timetable.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-calendar-alt mr-3"></i> Master Timetable</a></li>
                <li><a href="courseContent_admin.php" class="flex items-center p-2 rounded-lg active-nav-item"><i class="fas fa-book mr-3"></i> Course & Content</a></li>
                <li><a href="package_management.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-box mr-3"></i> Package Management</a></li>
                <li><a href="admin_support_cases.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-headset mr-3"></i> Support Cases</a></li>
			    <li><a href="admin_communications.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10" onclick="showSection('communications', this)"><i class="fas fa-envelope mr-3"></i> NovaTechMail</a></li>
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
    <!-- Header -->
    <header class="bg-white shadow-md relative z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <button class="text-navy md:hidden" id="menuButton"><i class="fas fa-bars"></i></button>
                <h1 class="text-xl font-bold text-navy">Course & Content Management</h1>
                <div class="flex items-center space-x-4">
                    <!-- Real-time Notification Widget -->
                    <div class="relative" id="notificationWidget">
                        <button onclick="toggleNotifications()" class="relative p-2 text-navy hover:text-gold transition">
                            <i class="fas fa-bell text-2xl"></i>
                            <span id="notificationBadge" class="notification-badge" style="display: none;">0</span>
                        </button>
                        <div id="notificationDropdown" class="notification-dropdown">
                            <div class="p-4 border-b border-gray-200 bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <h3 class="font-bold text-navy">Notifications</h3>
                                    <button onclick="markAllAsRead()" class="text-sm text-gold hover:text-yellow-600 font-semibold">
                                        Mark all read
                                    </button>
                                </div>
                                <div class="flex space-x-2 mt-3">
                                    <button onclick="filterNotifications('all')" class="filter-tab active" data-filter="all">
                                        All
                                    </button>
                                    <button onclick="filterNotifications('unread')" class="filter-tab" data-filter="unread">
                                        Unread
                                    </button>
                                </div>
                            </div>
                            <div id="notificationsList" class="divide-y divide-gray-200">
                                <div class="p-8 text-center text-gray-500">
                                    <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                    <p>Loading notifications...</p>
                                </div>
                            </div>
                            <div class="p-3 border-t border-gray-200 bg-gray-50 text-center">
                                <a href="notifications_cont_dev.php" class="text-sm text-navy hover:text-gold font-semibold">
                                    View all notifications
                                </a>
                            </div>
                        </div>
                    </div>
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
    <main class="container mx-auto px-6 py-8">
        <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 space-y-4 lg:space-y-0">
            <div>
                <h1 class="text-3xl font-bold text-navy mb-2">Course & Content Management</h1>
                <p class="text-gray-600">Manage courses, lessons, study materials, and assessments</p>
            </div>
            <div class="flex space-x-3">
                <button class="bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 transition" onclick="openCourseModal()">
                    <i class="fas fa-plus mr-2"></i>Add Course
                </button>
                <button class="bg-gold text-navy font-bold py-3 px-6 rounded-lg hover:bg-yellow-500 transition" onclick="openContentModal()">
                    <i class="fas fa-plus mr-2"></i>Add Content
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-navy">Total Courses</h3>
                        <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo count($courses); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-navy">Course Content</h3>
                        <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo count($all_content); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-book-open text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-navy">Study Materials</h3>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo count($study_materials); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-alt text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-navy">Past Papers</h3>
                        <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo count($past_papers); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-pdf text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subject Filter -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <label for="subjectFilter" class="font-medium text-navy">Filter by Subject:</label>
                <select id="subjectFilter" class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gold">
                    <option value="">All Subjects</option>
                    <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button onclick="clearSubjectFilter()" class="text-sm text-gray-600 hover:text-navy underline">Clear</button>
            </div>
        </div>

        <!-- Courses Overview -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-navy mb-6">Courses Overview</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($courses as $course): ?>
                <?php
                $content_count = 0;
                foreach ($all_content as $content) {
                    if ($content['course_id'] == $course['id']) {
                        $content_count++;
                    }
                }
                ?>
                <div class="border-2 border-gray-200 rounded-xl p-6 hover:border-gold transition">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-navy"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-book text-blue-600"></i>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-file-alt mr-2"></i><?php echo $content_count; ?> content items
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tabbed Content Section -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="border-b border-gray-200">
                <div class="flex overflow-x-auto">
                    <div class="tab-button active" onclick="openTab('all-content')">All Content</div>
                    <div class="tab-button" onclick="openTab('lessons')">Lessons</div>
                    <div class="tab-button" onclick="openTab('videos')">Videos</div>
                    <div class="tab-button" onclick="openTab('quizzes')">Quizzes</div>
                    <div class="tab-button" onclick="openTab('materials')">Study Materials</div>
                    <div class="tab-button" onclick="openTab('papers')">Past Papers</div>
                </div>
            </div>
            <div class="p-6">
                <!-- All Content Tab -->
                <div id="all-content" class="tab-content active">
                    <h2 class="text-2xl font-bold text-navy mb-6">All Course Content</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-left">Title</th>
                                    <th class="py-3 px-6 text-left">Course</th>
                                    <th class="py-3 px-6 text-left">Type</th>
                                    <th class="py-3 px-6 text-left">Order</th>
                                    <th class="py-3 px-6 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm font-light">
                                <?php if (empty($all_content)): ?>
                                <tr><td colspan="5" class="py-4 px-6 text-center">No content found</td></tr>
                                <?php else: ?>
                                <?php foreach ($all_content as $content): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50" data-course-id="<?php echo $content['course_id']; ?>">
                                    <td class="py-3 px-6 text-left">
                                        <div class="flex items-center">
                                            <i class="fas <?php echo getContentTypeIcon($content['content_type']); ?> mr-3 text-gray-400"></i>
                                            <span><?php echo htmlspecialchars($content['title']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($content['course_name']); ?></td>
                                    <td class="py-3 px-6 text-left">
                                        <span class="px-3 py-1 rounded-full text-xs <?php echo getContentTypeClass($content['content_type']); ?>">
                                            <?php echo ucfirst($content['content_type']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-6 text-left"><?php echo $content['order_index']; ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <div class="flex item-center justify-center">
                                            <button class="w-4 mr-2 transform hover:text-purple-500 hover:scale-110" onclick="editContent(<?php echo htmlspecialchars(json_encode($content)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="w-4 mr-2 transform hover:text-red-500 hover:scale-110" onclick="confirmDeleteContent(<?php echo $content['id']; ?>)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Lessons Tab -->
                <div id="lessons" class="tab-content">
                    <h2 class="text-2xl font-bold text-navy mb-6">Lessons</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php 
                        $lessons = array_filter($all_content, fn($c) => $c['content_type'] === 'lesson');
                        if (empty($lessons)): 
                        ?>
                        <p class="text-gray-500 col-span-full">No lessons found</p>
                        <?php else: ?>
                        <?php foreach ($lessons as $lesson): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition" data-course-id="<?php echo $lesson['course_id']; ?>">
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-3 py-1 rounded-full text-xs bg-blue-100 text-blue-800"><?php echo htmlspecialchars($lesson['course_name']); ?></span>
                                <i class="fas fa-book-open text-blue-600"></i>
                            </div>
                            <h3 class="font-bold text-navy mb-2"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars(substr($lesson['description'] ?? '', 0, 100)) . (strlen($lesson['description'] ?? '') > 100 ? '...' : ''); ?></p>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500">Order: <?php echo $lesson['order_index']; ?></span>
                                <div class="space-x-2">
                                    <button class="text-blue-600 hover:underline text-xs" onclick="editContent(<?php echo htmlspecialchars(json_encode($lesson)); ?>)">Edit</button>
                                    <button class="text-red-600 hover:underline text-xs" onclick="confirmDeleteContent(<?php echo $lesson['id']; ?>)">Delete</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Videos Tab -->
                <div id="videos" class="tab-content">
                    <h2 class="text-2xl font-bold text-navy mb-6">Video Lessons</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php 
                        $videos = array_filter($all_content, fn($c) => $c['content_type'] === 'video');
                        if (empty($videos)): 
                        ?>
                        <p class="text-gray-500 col-span-full">No videos found</p>
                        <?php else: ?>
                        <?php foreach ($videos as $video): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition" data-course-id="<?php echo $video['course_id']; ?>">
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-3 py-1 rounded-full text-xs bg-purple-100 text-purple-800"><?php echo htmlspecialchars($video['course_name']); ?></span>
                                <i class="fas fa-video text-purple-600"></i>
                            </div>
                            <h3 class="font-bold text-navy mb-2"><?php echo htmlspecialchars($video['title']); ?></h3>
                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars(substr($video['description'] ?? '', 0, 100)) . (strlen($video['description'] ?? '') > 100 ? '...' : ''); ?></p>
                            <?php if (!empty($video['url'])): ?>
                            <a href="<?php echo htmlspecialchars($video['url']); ?>" target="_blank" class="text-blue-600 hover:underline text-xs block mb-2">
                                <i class="fas fa-external-link-alt mr-1"></i>View Video
                            </a>
                            <?php endif; ?>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500">Order: <?php echo $video['order_index']; ?></span>
                                <div class="space-x-2">
                                    <button class="text-blue-600 hover:underline text-xs" onclick="editContent(<?php echo htmlspecialchars(json_encode($video)); ?>)">Edit</button>
                                    <button class="text-red-600 hover:underline text-xs" onclick="confirmDeleteContent(<?php echo $video['id']; ?>)">Delete</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Quizzes Tab -->
                <div id="quizzes" class="tab-content">
                    <h2 class="text-2xl font-bold text-navy mb-6">Quizzes & Assessments</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php 
                        $quizzes = array_filter($all_content, fn($c) => $c['content_type'] === 'quiz');
                        if (empty($quizzes)): 
                        ?>
                        <p class="text-gray-500 col-span-full">No quizzes found</p>
                        <?php else: ?>
                        <?php foreach ($quizzes as $quiz): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition" data-course-id="<?php echo $quiz['course_id']; ?>">
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-3 py-1 rounded-full text-xs bg-green-100 text-green-800"><?php echo htmlspecialchars($quiz['course_name']); ?></span>
                                <i class="fas fa-question-circle text-green-600"></i>
                            </div>
                            <h3 class="font-bold text-navy mb-2"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars(substr($quiz['description'] ?? '', 0, 100)) . (strlen($quiz['description'] ?? '') > 100 ? '...' : ''); ?></p>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500">Passing: <?php echo $quiz['passing_percentage'] ?? '0'; ?>%</span>
                                <div class="space-x-2">
                                    <button class="text-blue-600 hover:underline text-xs" onclick="editContent(<?php echo htmlspecialchars(json_encode($quiz)); ?>)">Edit</button>
                                    <button class="text-red-600 hover:underline text-xs" onclick="confirmDeleteContent(<?php echo $quiz['id']; ?>)">Delete</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Study Materials Tab -->
                <div id="materials" class="tab-content">
                    <h2 class="text-2xl font-bold text-navy mb-6">Study Materials</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                    <th class="py-3 px-6 text-left">Title</th>
                                    <th class="py-3 px-6 text-left">Course</th>
                                    <th class="py-3 px-6 text-left">Category</th>
                                    <th class="py-3 px-6 text-left">Type</th>
                                    <th class="py-3 px-6 text-left">Downloads</th>
                                    <th class="py-3 px-6 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 text-sm font-light">
                                <?php if (empty($study_materials)): ?>
                                <tr><td colspan="6" class="py-4 px-6 text-center">No study materials found</td></tr>
                                <?php else: ?>
                                <?php foreach ($study_materials as $material): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50" data-course-id="<?php echo $material['course_id']; ?>">
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($material['title']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($material['course_name']); ?></td>
                                    <td class="py-3 px-6 text-left">
                                        <span class="px-3 py-1 rounded-full text-xs <?php echo getCategoryClass($material['category']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $material['category'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-6 text-left"><?php echo strtoupper($material['file_type']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo $material['download_count']; ?></td>
                                    <td class="py-3 px-6 text-center">
                                        <div class="flex item-center justify-center">
                                            <button class="w-4 mr-2 transform hover:text-blue-500 hover:scale-110" onclick="viewMaterial('<?php echo htmlspecialchars($material['file_path']); ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="w-4 mr-2 transform hover:text-red-500 hover:scale-110" onclick="confirmDeleteMaterial(<?php echo $material['id']; ?>)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Past Papers Tab -->
                <div id="papers" class="tab-content">
                    <h2 class="text-2xl font-bold text-navy mb-6">Past Papers</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if (empty($past_papers)): ?>
                        <p class="text-gray-500 col-span-full">No past papers found</p>
                        <?php else: ?>
                        <?php foreach ($past_papers as $paper): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-lg transition" data-course-id="<?php echo $paper['course_id'] ?? 0; ?>">
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-3 py-1 rounded-full text-xs bg-orange-100 text-orange-800"><?php echo $paper['year']; ?></span>
                                <i class="fas fa-file-pdf text-red-600 text-xl"></i>
                            </div>
                            <h3 class="font-bold text-navy mb-2"><?php echo htmlspecialchars($paper['title']); ?></h3>
                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($paper['course_name'] ?? 'General'); ?></p>
                            <p class="text-xs text-gray-500 mb-3"><?php echo htmlspecialchars(substr($paper['description'] ?? '', 0, 80)) . (strlen($paper['description'] ?? '') > 80 ? '...' : ''); ?></p>
                            <div class="flex justify-between items-center">
                                <a href="<?php echo htmlspecialchars($paper['file_link']); ?>" target="_blank" class="text-blue-600 hover:underline text-xs">
                                    <i class="fas fa-download mr-1"></i>Download
                                </a>
                                <button class="text-red-600 hover:underline text-xs" onclick="alert('Delete functionality for papers')">Delete</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Course Modal -->
<div class="modal" id="courseModal">
    <div class="modal-content">
        <div class="bg-navy text-white p-4 rounded-t-lg flex justify-between items-center">
            <h3 class="text-lg font-bold">Add New Course</h3>
            <button class="text-white" onclick="closeCourseModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="course_name">Course Name</label>
                <input type="text" id="course_name" name="course_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gold" required placeholder="e.g., Mathematics, Physical Science">
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50" onclick="closeCourseModal()">Cancel</button>
                <button type="submit" name="add_course" class="px-4 py-2 bg-gold text-navy font-bold rounded-md hover:bg-yellow-500">Add Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Content Modal -->
<div class="modal" id="contentModal">
    <div class="modal-content">
        <div class="bg-navy text-white p-4 rounded-t-lg flex justify-between items-center">
            <h3 class="text-lg font-bold" id="contentModalTitle">Add Course Content</h3>
            <button class="text-white" onclick="closeContentModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6">
            <input type="hidden" name="content_id" id="content_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="course_id">Course</label>
                    <select id="course_id" name="course_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gold" required>
                        <option value="">Select a course</option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="content_type">Content Type</label>
                    <select id="content_type" name="content_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gold" required>
                        <option value="lesson">Lesson</option>
                        <option value="video">Video</option>
                        <option value="pdf">PDF Document</option>
                        <option value="quiz">Quiz</option>
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="title">Title</label>
                <input type="text" id="title" name="title" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gold" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="description">Description</label>
                <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gold"></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div id="pdfUploadField" class="hidden">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="content_file">PDF Upload</label>
                    <input type="file" id="content_file" name="content_file" accept=".pdf" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gold">
                    <p class="text-xs text-gray-500 mt-1">Only PDF files (max 20MB)</p>
                </div>
                <div id="urlField">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="url">URL (Optional)</label>
                    <input type="url" id="url" name="url" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gold" placeholder="https://...">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="order_index">Order</label>
                    <input type="number" id="order_index" name="order_index" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-gold">
                </div>
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50" onclick="closeContentModal()">Cancel</button>
                <button type="submit" name="add_content" class="px-4 py-2 bg-gold text-navy font-bold rounded-md hover:bg-yellow-500">Save Content</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Content Modal -->
<div class="modal" id="deleteContentModal">
    <div class="modal-content">
        <div class="bg-red-600 text-white p-4 rounded-t-lg flex justify-between items-center">
            <h3 class="text-lg font-bold">Confirm Deletion</h3>
            <button class="text-white" onclick="closeDeleteContentModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <p class="text-gray-700 mb-4">Are you sure you want to delete this content? This action cannot be undone.</p>
            <form method="POST" id="deleteContentForm">
                <input type="hidden" name="content_id" id="delete_content_id">
                <div class="flex justify-end space-x-4">
                    <button type="button" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50" onclick="closeDeleteContentModal()">Cancel</button>
                    <button type="submit" name="delete_content" class="px-4 py-2 bg-red-600 text-white font-bold rounded-md hover:bg-red-700">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Material Modal -->
<div class="modal" id="deleteMaterialModal">
    <div class="modal-content">
        <div class="bg-red-600 text-white p-4 rounded-t-lg flex justify-between items-center">
            <h3 class="text-lg font-bold">Confirm Deletion</h3>
            <button class="text-white" onclick="closeDeleteMaterialModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <p class="text-gray-700 mb-4">Are you sure you want to delete this study material? This action cannot be undone.</p>
            <form method="POST" id="deleteMaterialForm">
                <input type="hidden" name="material_id" id="delete_material_id">
                <div class="flex justify-end space-x-4">
                    <button type="button" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50" onclick="closeDeleteMaterialModal()">Cancel</button>
                    <button type="submit" name="delete_material" class="px-4 py-2 bg-red-600 text-white font-bold rounded-md hover:bg-red-700">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentCourseFilter = null;

// Toggle PDF upload vs URL based on content type
document.getElementById('content_type').addEventListener('change', function() {
    const pdfField = document.getElementById('pdfUploadField');
    const urlField = document.getElementById('urlField');
    if (this.value === 'pdf') {
        pdfField.classList.remove('hidden');
        urlField.classList.add('hidden');
    } else {
        pdfField.classList.add('hidden');
        urlField.classList.remove('hidden');
    }
});

// Subject filter
document.getElementById('subjectFilter').addEventListener('change', function() {
    currentCourseFilter = this.value ? parseInt(this.value) : null;
    applyContentFilter();
});

function clearSubjectFilter() {
    document.getElementById('subjectFilter').value = '';
    currentCourseFilter = null;
    applyContentFilter();
}

function applyContentFilter() {
    const elements = document.querySelectorAll(
        '#all-content tbody tr[data-course-id], ' +
        '#lessons > div[data-course-id], ' +
        '#videos > div[data-course-id], ' +
        '#quizzes > div[data-course-id], ' +
        '#materials tbody tr[data-course-id], ' +
        '#papers > div[data-course-id]'
    );

    elements.forEach(el => {
        if (currentCourseFilter === null) {
            el.style.display = '';
        } else {
            const elCourseId = parseInt(el.getAttribute('data-course-id'));
            el.style.display = (elCourseId === currentCourseFilter) ? '' : 'none';
        }
    });
}

// Tab functionality
function openTab(tabName) {
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => button.classList.remove('active'));
    event.currentTarget.classList.add('active');
    applyContentFilter(); // Re-apply filter when switching tabs
}

// Modal functions
function openCourseModal() {
    document.getElementById('courseModal').classList.add('active');
    document.getElementById('overlay').classList.add('active');
}
function closeCourseModal() {
    document.getElementById('courseModal').classList.remove('active');
    document.getElementById('overlay').classList.remove('active');
}
function openContentModal() {
    document.getElementById('contentModal').classList.add('active');
    document.getElementById('overlay').classList.add('active');
}
function closeContentModal() {
    document.getElementById('contentModal').classList.remove('active');
    document.getElementById('overlay').classList.remove('active');
    resetContentForm();
}
function resetContentForm() {
    document.getElementById('content_id').value = '';
    document.getElementById('contentModalTitle').textContent = 'Add Course Content';
    document.getElementById('course_id').value = '';
    document.getElementById('content_type').value = 'lesson';
    document.getElementById('title').value = '';
    document.getElementById('description').value = '';
    document.getElementById('url').value = '';
    document.getElementById('order_index').value = '1';
    document.getElementById('pdfUploadField').classList.add('hidden');
    document.getElementById('urlField').classList.remove('hidden');
}
function editContent(content) {
    document.getElementById('contentModalTitle').textContent = 'Edit Course Content';
    document.getElementById('content_id').value = content.id;
    document.getElementById('course_id').value = content.course_id;
    document.getElementById('content_type').value = content.content_type;
    document.getElementById('title').value = content.title;
    document.getElementById('description').value = content.description || '';
    document.getElementById('url').value = content.url || '';
    document.getElementById('order_index').value = content.order_index;
    // Show correct field
    if (content.content_type === 'pdf') {
        document.getElementById('pdfUploadField').classList.remove('hidden');
        document.getElementById('urlField').classList.add('hidden');
    } else {
        document.getElementById('pdfUploadField').classList.add('hidden');
        document.getElementById('urlField').classList.remove('hidden');
    }
    openContentModal();
}
function confirmDeleteContent(contentId) {
    document.getElementById('delete_content_id').value = contentId;
    document.getElementById('deleteContentModal').classList.add('active');
    document.getElementById('overlay').classList.add('active');
}
function closeDeleteContentModal() {
    document.getElementById('deleteContentModal').classList.remove('active');
    document.getElementById('overlay').classList.remove('active');
}
function confirmDeleteMaterial(materialId) {
    document.getElementById('delete_material_id').value = materialId;
    document.getElementById('deleteMaterialModal').classList.add('active');
    document.getElementById('overlay').classList.add('active');
}
function closeDeleteMaterialModal() {
    document.getElementById('deleteMaterialModal').classList.remove('active');
    document.getElementById('overlay').classList.remove('active');
}
function viewMaterial(filePath) {
    window.open(filePath, '_blank');
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
        material_upload: { icon: 'fa-file-upload', bgColor: 'bg-blue-100', textColor: 'text-blue-600' },
        upload: { icon: 'fa-upload', bgColor: 'bg-blue-100', textColor: 'text-blue-600' },
        approval: { icon: 'fa-check-circle', bgColor: 'bg-green-100', textColor: 'text-green-600' }
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
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startNotificationChecking);
} else {
    startNotificationChecking();
}
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
    document.getElementById('overlay').classList.remove('active');
    closeCourseModal();
    closeContentModal();
    closeDeleteContentModal();
    closeDeleteMaterialModal();
});
</script>
</body>
</html>