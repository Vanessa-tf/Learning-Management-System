<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include(__DIR__ . "/includes/db.php");

// Pagination settings
$cases_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $cases_per_page;

// Filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_priority = $_GET['priority'] ?? '';
$filter_role = $_GET['role'] ?? '';
$filter_category = $_GET['category'] ?? '';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];

if (!empty($filter_status)) {
    $where_conditions[] = "c.status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($filter_priority)) {
    $where_conditions[] = "c.priority = :priority";
    $params[':priority'] = $filter_priority;
}

if (!empty($filter_role)) {
    $where_conditions[] = "c.logged_by_role = :role";
    $params[':role'] = $filter_role;
}

if (!empty($filter_category)) {
    $where_conditions[] = "c.category = :category";
    $params[':category'] = $filter_category;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get total number of cases for pagination
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total_cases 
    FROM support_cases c 
    $where_clause
");
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_cases = $count_stmt->fetch(PDO::FETCH_ASSOC)['total_cases'];
$total_pages = ceil($total_cases / $cases_per_page);

// Ensure current page is within valid range
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// Get all support cases including parent, student, teacher, and content developer cases with pagination and filters
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        CASE 
            WHEN c.logged_by_role = 'teacher' THEN u.first_name
            WHEN c.logged_by_role = 'content_developer' THEN cd.first_name
            WHEN c.logged_by_role = 'parent' THEN 
                CASE 
                    WHEN s.financier_name IS NOT NULL AND s.financier_name != '' 
                    THEN SUBSTRING_INDEX(s.financier_name, ' ', 1)
                    ELSE 'Parent'
                END
            ELSE s.first_name
        END as first_name,
        CASE 
            WHEN c.logged_by_role = 'teacher' THEN u.last_name
            WHEN c.logged_by_role = 'content_developer' THEN cd.last_name
            WHEN c.logged_by_role = 'parent' THEN 
                CASE 
                    WHEN s.financier_name IS NOT NULL AND s.financier_name != '' 
                    THEN SUBSTRING_INDEX(SUBSTRING_INDEX(s.financier_name, ' ', 2), ' ', -1)
                    ELSE 'User'
                END
            ELSE s.surname
        END as last_name,
        c.logged_by_role
    FROM support_cases c 
    LEFT JOIN students s ON c.student_id = s.id 
    LEFT JOIN users u ON c.teacher_id = u.id 
    LEFT JOIN content_developers cd ON (c.logged_by_role = 'content_developer')
    $where_clause
    ORDER BY c.created_at DESC
    LIMIT :limit OFFSET :offset
");

// Bind parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $cases_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for dashboard
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_cases,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_cases,
        SUM(CASE WHEN status = 'in progress' THEN 1 ELSE 0 END) as in_progress_cases,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_cases,
        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_cases,
        SUM(CASE WHEN logged_by_role = 'parent' THEN 1 ELSE 0 END) as parent_cases,
        SUM(CASE WHEN logged_by_role = 'teacher' THEN 1 ELSE 0 END) as teacher_cases,
        SUM(CASE WHEN logged_by_role = 'student' THEN 1 ELSE 0 END) as student_cases,
        SUM(CASE WHEN logged_by_role = 'content_developer' THEN 1 ELSE 0 END) as content_developer_cases
    FROM support_cases
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get unique values for filter dropdowns
$statuses_stmt = $pdo->query("SELECT DISTINCT status FROM support_cases ORDER BY status");
$statuses = $statuses_stmt->fetchAll(PDO::FETCH_COLUMN);

$priorities_stmt = $pdo->query("SELECT DISTINCT priority FROM support_cases ORDER BY priority");
$priorities = $priorities_stmt->fetchAll(PDO::FETCH_COLUMN);

$roles_stmt = $pdo->query("SELECT DISTINCT logged_by_role FROM support_cases ORDER BY logged_by_role");
$roles = $roles_stmt->fetchAll(PDO::FETCH_COLUMN);

$categories_stmt = $pdo->query("SELECT DISTINCT category FROM support_cases ORDER BY category");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle case status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $case_id = $_POST['case_id'];
    $new_status = $_POST['status'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    try {
        // Update case status and set status_updated_at timestamp
        // Reset last_viewed_by_teacher so teacher sees there's an update
        $update_stmt = $pdo->prepare("
            UPDATE support_cases 
            SET status = ?, admin_notes = ?, updated_at = NOW(), last_viewed_by_teacher = NULL
            WHERE id = ?
        ");
        $update_stmt->execute([$new_status, $admin_notes, $case_id]);
        
$case_stmt = $pdo->prepare("
    SELECT sc.*, 
           s.first_name as student_first_name, 
           s.surname as student_surname, 
           s.email as student_email,
           s.financier_name as parent_name,
           f.id as parent_id, 
           u.id as teacher_id, 
           cd.id as content_developer_id
    FROM support_cases sc
    LEFT JOIN students s ON sc.student_id = s.id
    LEFT JOIN financiers f ON (sc.logged_by_role = 'parent' AND sc.student_id = f.student_id)
    LEFT JOIN users u ON sc.teacher_id = u.id
    LEFT JOIN content_developers cd ON sc.logged_by_role = 'content_developer'
    WHERE sc.id = ?
");
        $case_stmt->execute([$case_id]);
        $case = $case_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create notification for the appropriate user based on who logged the case
        if ($case) {
            $notification_message = "Your support case #{$case['case_number']} status has been updated to: {$new_status}";
            
            if ($case['logged_by_role'] === 'parent' && $case['parent_id']) {
                // For parent cases, notify the parent
                $notification_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, message, created_at)
                    VALUES (?, 'support_case', ?, NOW())
                ");
                $notification_stmt->execute([$case['parent_id'], $notification_message]);
            } elseif ($case['logged_by_role'] === 'teacher' && $case['teacher_id']) {
                // For teacher cases, notify the teacher
                $notification_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, message, created_at)
                    VALUES (?, 'support_case', ?, NOW())
                ");
                $notification_stmt->execute([$case['teacher_id'], $notification_message]);
            } elseif ($case['logged_by_role'] === 'content_developer' && $case['content_developer_id']) {
                // For content developer cases, notify the content developer
                $notification_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, message, created_at)
                    VALUES (?, 'support_case', ?, NOW())
                ");
                $notification_stmt->execute([$case['content_developer_id'], $notification_message]);
            } elseif ($case['logged_by_role'] === 'student' && $case['student_id']) {
                // For student cases, we would notify the student (implementation depends on student notification system)
              
            }
        }
        
        $success = "Case status updated successfully!";
        
        // Redirect to avoid form resubmission
        header("Location: admin_support_cases.php?page=" . $current_page . "&status=" . urlencode($filter_status) . "&priority=" . urlencode($filter_priority) . "&role=" . urlencode($filter_role) . "&category=" . urlencode($filter_category) . "&success=1");
        exit;
        
    } catch (Exception $e) {
        $error = "Error updating case: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Cases - Admin - NovaTech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #1e3a6c;
            --gold: #fbbf24;
            --beige: #f5f5dc;
        }
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #f8fafc;
        }
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
        
        /* Status and Priority Badges */
        .priority-urgent { 
            background-color: #fef2f2; 
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .priority-high { 
            background-color: #fffbeb; 
            color: #d97706;
            border: 1px solid #fed7aa;
        }
        
        .priority-medium { 
            background-color: #eff6ff; 
            color: #1d4ed8;
            border: 1px solid #dbeafe;
        }
        
        .priority-low { 
            background-color: #f0fdf4; 
            color: #059669;
            border: 1px solid #bbf7d0;
        }
        
        .status-open { 
            background-color: #fffbeb; 
            color: #d97706;
            border: 1px solid #fed7aa;
        }
        
        .status-in-progress { 
            background-color: #eff6ff; 
            color: #1d4ed8;
            border: 1px solid #dbeafe;
        }
        
        .status-resolved { 
            background-color: #f0fdf4; 
            color: #059669;
            border: 1px solid #bbf7d0;
        }
        
        .status-closed { 
            background-color: #f8fafc; 
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        /* Role Badges */
        .badge-parent {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        .badge-teacher {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .badge-student {
            background-color: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb;
        }
        
        .badge-content-developer {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--navy) 0%, #2d4ba0 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.15);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }

        /* Filter Styles */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .filter-select {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: white;
            color: #374151;
            font-size: 0.875rem;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(30, 58, 108, 0.1);
        }

        .filter-button {
            background: var(--navy);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .filter-button:hover {
            background: #1e2a4a;
        }

        .clear-filters {
            background: #6b7280;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .clear-filters:hover {
            background: #4b5563;
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .pagination-button {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .pagination-button:hover:not(.disabled) {
            background: var(--navy);
            color: white;
            border-color: var(--navy);
        }

        .pagination-button.active {
            background: var(--navy);
            color: white;
            border-color: var(--navy);
        }

        .pagination-button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-info {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0 1rem;
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
                    <li><a href="admin_support_cases.php" class="flex items-center p-2 rounded-lg active-nav-item"><i class="fas fa-headset mr-3"></i> Support Cases</a></li>
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
        <!-- Top Navigation -->
        <header class="bg-white shadow-md relative z-50">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <button class="text-navy md:hidden" id="menuButton">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="text-xl font-bold text-navy">Support Cases Management</h1>
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
            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= $success ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Cases</p>
                            <p class="text-2xl font-bold text-navy"><?= $stats['total_cases'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-headset text-blue-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Open Cases</p>
                            <p class="text-2xl font-bold text-orange-600"><?= $stats['open_cases'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-orange-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">In Progress</p>
                            <p class="text-2xl font-bold text-blue-600"><?= $stats['in_progress_cases'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-spinner text-blue-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Urgent Priority</p>
                            <p class="text-2xl font-bold text-red-600"><?= $stats['urgent_cases'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <h3 class="text-lg font-bold text-navy mb-4">Filter Cases</h3>
                <form method="GET" class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= $status ?>" <?= $filter_status === $status ? 'selected' : '' ?>>
                                    <?= ucfirst($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Priority</label>
                        <select name="priority" class="filter-select">
                            <option value="">All Priorities</option>
                            <?php foreach ($priorities as $priority): ?>
                                <option value="<?= $priority ?>" <?= $filter_priority === $priority ? 'selected' : '' ?>>
                                    <?= ucfirst($priority) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">User Role</label>
                        <select name="role" class="filter-select">
                            <option value="">All Roles</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role ?>" <?= $filter_role === $role ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $role)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category ?>" <?= $filter_category === $category ? 'selected' : '' ?>>
                                    <?= ucfirst($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="filter-button">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <a href="admin_support_cases.php" class="clear-filters">
                            <i class="fas fa-times mr-2"></i>Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Cases Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <h2 class="text-xl font-bold text-navy">All Support Cases</h2>
                        <div class="flex items-center space-x-4">
                            <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                                Showing <?= count($cases) ?> of <?= $total_cases ?> cases
                                <?php if ($filter_status || $filter_priority || $filter_role || $filter_category): ?>
                                    (Filtered)
                                <?php endif; ?>
                            </span>
                            <a href="admin_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition text-sm">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full min-w-max">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Person</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (!empty($cases)): ?>
                                <?php 
                                $processed_cases = [];
                                foreach ($cases as $case): 
                                    // Skip duplicate cases by checking case_number
                                    if (in_array($case['case_number'], $processed_cases)) {
                                        continue;
                                    }
                                    $processed_cases[] = $case['case_number'];
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-mono font-bold text-navy"><?= $case['case_number'] ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $role_badge = match($case['logged_by_role']) {
                                                'parent' => '<span class="badge-parent px-2 py-1 rounded-full text-xs font-medium"><i class="fas fa-users mr-1"></i>Parent</span>',
                                                'teacher' => '<span class="badge-teacher px-2 py-1 rounded-full text-xs font-medium"><i class="fas fa-chalkboard-teacher mr-1"></i>Teacher</span>',
                                                'content_developer' => '<span class="badge-content-developer px-2 py-1 rounded-full text-xs font-medium"><i class="fas fa-code mr-1"></i>Content Developer</span>',
                                                default => '<span class="badge-student px-2 py-1 rounded-full text-xs font-medium"><i class="fas fa-user-graduate mr-1"></i>Student</span>'
                                            };
                                            echo $role_badge;
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-navy font-bold text-xs">
                                                        <?= strtoupper(substr($case['first_name'] ?? '', 0, 1) . substr($case['last_name'] ?? '', 0, 1)) ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($case['first_name'] . ' ' . $case['last_name']) ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500"><?= ucfirst(str_replace('_', ' ', $case['logged_by_role'])) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900 max-w-xs truncate">
                                                <?= htmlspecialchars($case['subject']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">
                                                <?= $case['category'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="priority-<?= strtolower($case['priority']) ?> px-2 py-1 rounded-full text-xs font-medium">
                                                <?= $case['priority'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="status-<?= strtolower(str_replace(' ', '-', $case['status'])) ?> px-2 py-1 rounded-full text-xs font-medium">
                                                <?= $case['status'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('M j, Y', strtotime($case['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php
                                            $view_page = match($case['logged_by_role']) {
                                                'parent' => 'admin_view_parent_case.php',
                                                'teacher' => 'admin_view_teacher_case.php',
                                                'content_developer' => 'admin_view_content_case.php',
                                                default => 'admin_view_student_case.php'
                                            };
                                            ?>
                                            <a href="<?= $view_page ?>?id=<?= $case['id'] ?>" 
                                               class="bg-navy text-white px-3 py-2 rounded-lg hover:bg-blue-800 transition text-sm inline-flex items-center mr-2">
                                                <i class="fas fa-eye mr-1.5"></i>View
                                            </a>
                                            <button onclick="openStatusModal(<?= $case['id'] ?>, '<?= $case['status'] ?>', '<?= $case['case_number'] ?>')" 
                                                    class="bg-gold text-navy px-3 py-2 rounded-lg hover:bg-yellow-400 transition text-sm inline-flex items-center">
                                                <i class="fas fa-edit mr-1.5"></i>Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                                        <p class="text-lg">No Support Cases Found</p>
                                        <p class="text-sm text-gray-400">
                                            <?php if ($filter_status || $filter_priority || $filter_role || $filter_category): ?>
                                                No cases match your current filters. <a href="admin_support_cases.php" class="text-navy hover:underline">Clear filters</a> to see all cases.
                                            <?php else: ?>
                                                There are no support cases in the system yet.
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="p-6 border-t border-gray-200">
                    <div class="pagination">
                        <!-- First Page -->
                        <a href="?page=1&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&role=<?= urlencode($filter_role) ?>&category=<?= urlencode($filter_category) ?>" 
                           class="pagination-button <?= $current_page == 1 ? 'disabled' : '' ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        
                        <!-- Previous Page -->
                        <a href="?page=<?= $current_page - 1 ?>&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&role=<?= urlencode($filter_role) ?>&category=<?= urlencode($filter_category) ?>" 
                           class="pagination-button <?= $current_page == 1 ? 'disabled' : '' ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                        
                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="?page=<?= $i ?>&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&role=<?= urlencode($filter_role) ?>&category=<?= urlencode($filter_category) ?>" 
                               class="pagination-button <?= $i == $current_page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <!-- Next Page -->
                        <a href="?page=<?= $current_page + 1 ?>&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&role=<?= urlencode($filter_role) ?>&category=<?= urlencode($filter_category) ?>" 
                           class="pagination-button <?= $current_page == $total_pages ? 'disabled' : '' ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        
                        <!-- Last Page -->
                        <a href="?page=<?= $total_pages ?>&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&role=<?= urlencode($filter_role) ?>&category=<?= urlencode($filter_category) ?>" 
                           class="pagination-button <?= $current_page == $total_pages ? 'disabled' : '' ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                        
                        <!-- Page Info -->
                        <span class="pagination-info">
                            Page <?= $current_page ?> of <?= $total_pages ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-bold text-navy mb-4">Update Case Status</h2>
            <form id="statusForm" method="POST">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" id="case_id" name="case_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Case Number</label>
                    <p id="caseNumberDisplay" class="text-lg font-semibold text-navy"></p>
                </div>
                
                <div class="mb-4">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="status" class="w-full p-2 border border-gray-300 rounded" required>
                        <option value="Open">Open</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="admin_notes" class="block text-sm font-medium text-gray-700 mb-2">Admin Notes</label>
                    <textarea name="admin_notes" id="admin_notes" rows="4" class="w-full p-2 border border-gray-300 rounded" placeholder="Add any notes or comments..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeStatusModal()" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 transition">Cancel</button>
                    <button type="submit" class="bg-navy text-white px-4 py-2 rounded hover:bg-blue-800 transition">Update Status</button>
                </div>
            </form>
        </div>
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

        // Status Modal Functions
        const statusModal = document.getElementById('statusModal');
        const closeBtn = document.getElementsByClassName('close')[0];

        function openStatusModal(caseId, currentStatus, caseNumber) {
            document.getElementById('case_id').value = caseId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('caseNumberDisplay').textContent = caseNumber;
            statusModal.style.display = 'block';
        }

        function closeStatusModal() {
            statusModal.style.display = 'none';
        }

        closeBtn.onclick = closeStatusModal;

        window.onclick = function(event) {
            if (event.target == statusModal) {
                closeStatusModal();
            }
        }

        // Auto-hide messages
        setTimeout(() => {
            const messages = document.querySelectorAll('.bg-green-100');
            messages.forEach(msg => {
                if (msg.style.display !== 'none') {
                    msg.style.opacity = '0';
                    setTimeout(() => msg.style.display = 'none', 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>