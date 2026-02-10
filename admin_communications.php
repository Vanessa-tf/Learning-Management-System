<?php
session_start();
require_once 'send-email.php';

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "novatech_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize active section
$active_section = isset($_GET['section']) ? $_GET['section'] : 'compose';

// Handle form submission for sending emails
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_email') {
        $to_emails = $_POST['to_emails'];
        $subject = $_POST['subject'];
        $message = $_POST['message'];
        
        // Convert comma-separated emails to array
        $email_array = array_map('trim', explode(',', $to_emails));
        
        $success_count = 0;
        $error_count = 0;
        $sent_emails = [];
        
        foreach ($email_array as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Preserve line breaks in the message
                $formatted_message = nl2br(htmlspecialchars($message));
                
                $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #1e3a6c;'>NovaTech FET College</h2>
                    <div style='margin: 20px 0; padding: 15px; background-color: #f9f9f9; border-radius: 5px; line-height: 1.6;'>
                        {$formatted_message}
                    </div>
                    <hr>
                    <p style='font-size: 12px; color: #666;'>This is an automated message from NovaTech FET College. Please do not reply to this email.</p>
                </div>";
                
                if (sendEmail($email, $subject, $body)) {
                    $success_count++;
                    $sent_emails[] = $email;
                    
                    // Save to sent items
                    $stmt = $conn->prepare("INSERT INTO sent_emails (to_email, subject, message, sent_by, sent_at) VALUES (?, ?, ?, 'admin', NOW())");
                    $stmt->bind_param("sss", $email, $subject, $message);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $error_count++;
                }
            } else {
                $error_count++;
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['message'] = "Successfully sent {$success_count} email(s) to: " . implode(', ', $sent_emails);
            $_SESSION['message_class'] = "bg-green-100 border-green-400 text-green-700";
        } else {
            $_SESSION['message'] = "Failed to send emails. Please try again.";
            $_SESSION['message_class'] = "bg-red-100 border-red-400 text-red-700";
        }
        
        header('Location: admin_communications.php?section=sent');
        exit;
    }
    elseif ($_POST['action'] === 'save_draft') {
        $to_emails = $_POST['to_emails'];
        $subject = $_POST['subject'];
        $message = $_POST['message'];
        $draft_id = isset($_POST['draft_id']) ? $_POST['draft_id'] : null;
        
        if ($draft_id) {
            // Update existing draft
            $stmt = $conn->prepare("UPDATE draft_emails SET to_email = ?, subject = ?, message = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssi", $to_emails, $subject, $message, $draft_id);
        } else {
            // Create new draft
            $stmt = $conn->prepare("INSERT INTO draft_emails (to_email, subject, message, created_by, created_at) VALUES (?, ?, ?, 'admin', NOW())");
            $stmt->bind_param("sss", $to_emails, $subject, $message);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Draft saved successfully!";
            $_SESSION['message_class'] = "bg-green-100 border-green-400 text-green-700";
        } else {
            $_SESSION['message'] = "Failed to save draft. Please try again.";
            $_SESSION['message_class'] = "bg-red-100 border-red-400 text-red-700";
        }
        $stmt->close();
        
        header('Location: admin_communications.php?section=drafts');
        exit;
    }
    elseif ($_POST['action'] === 'delete_draft') {
        $draft_id = $_POST['draft_id'];
        
        $stmt = $conn->prepare("DELETE FROM draft_emails WHERE id = ?");
        $stmt->bind_param("i", $draft_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Draft deleted successfully!";
            $_SESSION['message_class'] = "bg-green-100 border-green-400 text-green-700";
        } else {
            $_SESSION['message'] = "Failed to delete draft. Please try again.";
            $_SESSION['message_class'] = "bg-red-100 border-red-400 text-red-700";
        }
        $stmt->close();
        
        header('Location: admin_communications.php?section=drafts');
        exit;
    }
}

// Handle loading draft for editing
if (isset($_GET['load_draft'])) {
    $draft_id = $_GET['load_draft'];
    $stmt = $conn->prepare("SELECT * FROM draft_emails WHERE id = ?");
    $stmt->bind_param("i", $draft_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $draft = $result->fetch_assoc();
    $stmt->close();
    
    if ($draft) {
        $active_section = 'compose';
    }
}

// Fetch user data for the address book
$users = [];

// Students
$students_query = "SELECT email, CONCAT(first_name, ' ', surname) as name, 'student' as role FROM students WHERE email IS NOT NULL AND email != ''";
$result = $conn->query($students_query);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Teachers
$teachers_query = "SELECT email, CONCAT(first_name, ' ', last_name) as name, 'teacher' as role FROM users WHERE role = 'teacher' AND email IS NOT NULL AND email != ''";
$result = $conn->query($teachers_query);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Content Developers
$developers_query = "SELECT email, CONCAT(first_name, ' ', last_name) as name, 'developer' as role FROM content_developers WHERE email IS NOT NULL AND email != ''";
$result = $conn->query($developers_query);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Parents/Financiers
$parents_query = "SELECT DISTINCT financier_email as email, financier_name as name, 'parent' as role FROM students WHERE financier_email IS NOT NULL AND financier_email != ''";
$result = $conn->query($parents_query);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Fetch sent emails
$sent_emails = [];
$sent_query = "SELECT * FROM sent_emails WHERE sent_by = 'admin' ORDER BY sent_at DESC LIMIT 50";
$result = $conn->query($sent_query);
while ($row = $result->fetch_assoc()) {
    $sent_emails[] = $row;
}

// Fetch draft emails
$draft_emails = [];
$draft_query = "SELECT * FROM draft_emails WHERE created_by = 'admin' ORDER BY created_at DESC LIMIT 50";
$result = $conn->query($draft_query);
while ($row = $result->fetch_assoc()) {
    $draft_emails[] = $row;
}

// Counts for sidebar
$sent_count = count($sent_emails);
$drafts_count = count($draft_emails);
$students_count = count(array_filter($users, function($user) { return $user['role'] === 'student'; }));
$teachers_count = count(array_filter($users, function($user) { return $user['role'] === 'teacher'; }));
$parents_count = count(array_filter($users, function($user) { return $user['role'] === 'parent'; }));
$developers_count = count(array_filter($users, function($user) { return $user['role'] === 'developer'; }));
$all_users_count = count($users);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovaTechMail - Admin Communications</title>
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
        
        /* Gmail-like styling */
        .email-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .email-sidebar {
            border-right: 1px solid #e0e0e0;
        }
        
        .email-list-item {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }
        
        .email-list-item:hover {
            background-color: #f9f9f9;
            cursor: pointer;
        }
        
        .compose-btn {
            background: var(--gold);
            color: var(--navy);
            border-radius: 24px;
            padding: 12px 24px;
            font-weight: 600;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }
        
        .compose-btn:hover {
            background: #f59e0b;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .sidebar-item {
            padding: 12px 16px;
            border-radius: 0 24px 24px 0;
            margin-right: 8px;
            transition: background-color 0.2s;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sidebar-item:hover, .sidebar-item.active {
            background-color: #f1f3f4;
        }
        
        .sidebar-item.active {
            font-weight: 600;
            color: var(--navy);
            background-color: #e8f0fe;
        }
		.active-nav-item {
            background-color: var(--gold) !important;
            color: var(--navy) !important;
        }
        
        .count-badge {
            background: var(--navy);
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
        }
        
        .user-chip {
            display: inline-flex;
            align-items: center;
            background: #e8f0fe;
            color: var(--navy);
            border-radius: 16px;
            padding: 4px 12px;
            margin: 2px;
            font-size: 14px;
        }
        
        .user-chip .remove {
            margin-left: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
        }
        
        .draft-actions {
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .email-list-item:hover .draft-actions {
            opacity: 1;
        }

        /* Mobile responsive improvements */
        @media (max-width: 768px) {
            .email-sidebar {
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .sidebar-item {
                border-radius: 24px;
                margin: 4px 0;
            }
            
            .compose-btn {
                padding: 10px 20px;
                font-size: 14px;
            }
            
            .user-chip {
                font-size: 12px;
                padding: 3px 8px;
            }
            
            .grid-cols-mobile {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
            
            .address-book-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .email-container {
                margin: 0 -1rem;
                border-radius: 0;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .action-buttons button {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .sidebar-item {
                padding: 10px 12px;
                font-size: 14px;
            }
            
            .count-badge {
                font-size: 10px;
                padding: 1px 6px;
                min-width: 16px;
            }
            
            .user-grid {
                grid-template-columns: 1fr;
            }
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
                    <li><a href="admin_communications.php" class="flex items-center p-2 rounded-lg active-nav-item"><i class="fas fa-envelope mr-3"></i> NovaTechMail</a></li>
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
            <div class="container mx-auto px-4 sm:px-6 py-4">
                <div class="flex items-center justify-between">
                    <button class="text-navy md:hidden" id="menuButton">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="text-lg sm:text-xl font-bold text-navy">NovaTechMail - Admin Communications</h1>
                    <div class="flex items-center space-x-4">
                        <!-- Admin Profile -->
                        <div class="hidden sm:block">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-2">
                                    <span class="text-navy font-bold text-sm">AD</span>
                                </div>
                                <span class="text-navy text-sm">System Admin</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 sm:px-6 py-6">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="mb-6 border px-4 py-3 rounded <?php echo $_SESSION['message_class']; ?>" role="alert">
                    <?php echo $_SESSION['message']; ?>
                </div>
                <?php 
                unset($_SESSION['message']);
                unset($_SESSION['message_class']);
                ?>
            <?php endif; ?>

            <div class="email-container">
                <div class="flex flex-col lg:flex-row">
                    <!-- Sidebar -->
                    <div class="email-sidebar w-full lg:w-1/5 p-4 bg-gray-50">
                        <button class="compose-btn w-full flex items-center justify-center mb-6" onclick="showSection('compose')">
                            <i class="fas fa-edit mr-2"></i> Compose
                        </button>
                        
                        <div class="space-y-1">
                            <div class="sidebar-item <?php echo $active_section === 'sent' ? 'active' : ''; ?>" onclick="showSection('sent')">
                                <div class="flex items-center">
                                    <i class="fas fa-paper-plane mr-3"></i> Sent
                                </div>
                                <span class="count-badge"><?php echo $sent_count; ?></span>
                            </div>
                            <div class="sidebar-item <?php echo $active_section === 'drafts' ? 'active' : ''; ?>" onclick="showSection('drafts')">
                                <div class="flex items-center">
                                    <i class="fas fa-edit mr-3"></i> Drafts
                                </div>
                                <span class="count-badge"><?php echo $drafts_count; ?></span>
                            </div>
                        </div>
                        
                        <div class="mt-8">
                            <h3 class="font-semibold text-gray-700 mb-3 text-sm lg:text-base">User Groups</h3>
                            <div class="space-y-1">
                                <div class="sidebar-item <?php echo $active_section === 'students' ? 'active' : ''; ?>" onclick="showSection('students')">
                                    <div class="flex items-center">
                                        <i class="fas fa-user-graduate mr-3"></i> <span class="hidden sm:inline">Students</span>
                                    </div>
                                    <span class="count-badge"><?php echo $students_count; ?></span>
                                </div>
                                <div class="sidebar-item <?php echo $active_section === 'teachers' ? 'active' : ''; ?>" onclick="showSection('teachers')">
                                    <div class="flex items-center">
                                        <i class="fas fa-chalkboard-teacher mr-3"></i> <span class="hidden sm:inline">Teachers</span>
                                    </div>
                                    <span class="count-badge"><?php echo $teachers_count; ?></span>
                                </div>
                                <div class="sidebar-item <?php echo $active_section === 'parents' ? 'active' : ''; ?>" onclick="showSection('parents')">
                                    <div class="flex items-center">
                                        <i class="fas fa-users mr-3"></i> <span class="hidden sm:inline">Parents</span>
                                    </div>
                                    <span class="count-badge"><?php echo $parents_count; ?></span>
                                </div>
                                <div class="sidebar-item <?php echo $active_section === 'developers' ? 'active' : ''; ?>" onclick="showSection('developers')">
                                    <div class="flex items-center">
                                        <i class="fas fa-code mr-3"></i> <span class="hidden sm:inline">Developers</span>
                                    </div>
                                    <span class="count-badge"><?php echo $developers_count; ?></span>
                                </div>
                                <div class="sidebar-item <?php echo $active_section === 'all_users' ? 'active' : ''; ?>" onclick="showSection('all_users')">
                                    <div class="flex items-center">
                                        <i class="fas fa-users mr-3"></i> <span class="hidden sm:inline">All Users</span>
                                    </div>
                                    <span class="count-badge"><?php echo $all_users_count; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Main Content Area -->
                    <div class="w-full lg:w-4/5 p-4 sm:p-6">
                        <!-- Compose Section -->
                        <div id="compose-section" class="section <?php echo $active_section === 'compose' ? 'active' : ''; ?>">
                            <h2 class="text-xl sm:text-2xl font-bold text-navy mb-6">
                                <?php echo isset($draft) ? 'Edit Draft' : 'Compose New Message'; ?>
                            </h2>
                            
                            <form method="POST" class="space-y-6" id="compose-form">
                                <input type="hidden" name="action" value="<?php echo isset($draft) ? 'save_draft' : 'send_email'; ?>">
                                <?php if (isset($draft)): ?>
                                    <input type="hidden" name="draft_id" value="<?php echo $draft['id']; ?>">
                                <?php endif; ?>
                                
                                <!-- Recipients -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">To</label>
                                    <div class="border rounded-lg p-3 min-h-12" id="recipients-container">
                                        <input type="text" id="email-input" placeholder="Type email addresses or select from address book" class="w-full border-0 focus:ring-0 focus:outline-none text-sm sm:text-base">
                                    </div>
                                    <input type="hidden" name="to_emails" id="to_emails" value="<?php echo isset($draft) ? htmlspecialchars($draft['to_email']) : ''; ?>">
                                    <p class="text-xs text-gray-500 mt-1">Separate multiple emails with commas</p>
                                    
                                    <!-- Address Book -->
                                    <div class="mt-4 border rounded-lg p-4 bg-gray-50">
                                        <h3 class="font-medium text-gray-700 mb-3 text-sm sm:text-base">Address Book - Quick Select</h3>
                                        <div class="flex flex-wrap gap-2 mb-3">
                                            <button type="button" onclick="selectUserGroup('students')" class="px-2 sm:px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs sm:text-sm hover:bg-blue-200">All Students</button>
                                            <button type="button" onclick="selectUserGroup('teachers')" class="px-2 sm:px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs sm:text-sm hover:bg-green-200">All Teachers</button>
                                            <button type="button" onclick="selectUserGroup('parents')" class="px-2 sm:px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs sm:text-sm hover:bg-purple-200">All Parents</button>
                                            <button type="button" onclick="selectUserGroup('developers')" class="px-2 sm:px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs sm:text-sm hover:bg-yellow-200">All Developers</button>
                                            <button type="button" onclick="selectUserGroup('all')" class="px-2 sm:px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs sm:text-sm hover:bg-gray-200">All Users</button>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 max-h-60 overflow-y-auto address-book-grid">
                                            <?php foreach ($users as $user): ?>
                                                <div class="flex items-center p-2 border rounded hover:bg-white cursor-pointer" onclick="addRecipient('<?php echo $user['email']; ?>', '<?php echo $user['name']; ?> (<?php echo $user['role']; ?>)')">
                                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3 flex-shrink-0">
                                                        <span class="text-blue-600 text-xs font-bold">
                                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-medium truncate"><?php echo $user['name']; ?></p>
                                                        <p class="text-xs text-gray-500 truncate"><?php echo $user['email']; ?> • <?php echo ucfirst($user['role']); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Subject -->
                                <div>
                                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                                    <input type="text" id="subject" name="subject" required 
                                           value="<?php echo isset($draft) ? htmlspecialchars($draft['subject']) : ''; ?>" 
                                           class="w-full border rounded-lg px-3 sm:px-4 py-2 focus:ring-2 focus:ring-gold focus:border-transparent text-sm sm:text-base">
                                </div>
                                
                                <!-- Message -->
                                <div>
                                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                                    <textarea id="message" name="message" rows="10" required 
                                              class="w-full border rounded-lg px-3 sm:px-4 py-2 focus:ring-2 focus:ring-gold focus:border-transparent text-sm sm:text-base" 
                                              placeholder="Type your message here..."><?php echo isset($draft) ? htmlspecialchars($draft['message']) : ''; ?></textarea>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex justify-end space-x-3 action-buttons">
                                    <button type="button" onclick="saveAsDraft()" class="px-4 sm:px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm sm:text-base">
                                        Save Draft
                                    </button>
                                    <?php if (isset($draft)): ?>
                                        <button type="submit" name="action" value="send_email" class="bg-gold text-navy font-bold py-2 px-4 sm:px-6 rounded-lg hover:bg-yellow-500 transition text-sm sm:text-base">
                                            <i class="fas fa-paper-plane mr-2"></i> Send Now
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="bg-gold text-navy font-bold py-2 px-4 sm:px-6 rounded-lg hover:bg-yellow-500 transition text-sm sm:text-base">
                                            <i class="fas fa-paper-plane mr-2"></i> Send
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Sent Section -->
                        <div id="sent-section" class="section <?php echo $active_section === 'sent' ? 'active' : ''; ?>">
                            <h2 class="text-xl sm:text-2xl font-bold text-navy mb-6">Sent Messages</h2>
                            <?php if (empty($sent_emails)): ?>
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                    <i class="fas fa-paper-plane text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No Sent Messages</h3>
                                    <p class="text-gray-500">You haven't sent any emails yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($sent_emails as $email): ?>
                                        <div class="email-list-item p-4 border rounded-lg">
                                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                                                <div class="flex-1">
                                                    <h3 class="font-medium text-navy text-sm sm:text-base"><?php echo htmlspecialchars($email['subject']); ?></h3>
                                                    <p class="text-xs sm:text-sm text-gray-600">To: <?php echo htmlspecialchars($email['to_email']); ?></p>
                                                </div>
                                                <span class="text-xs text-gray-500 sm:text-right"><?php echo date('M j, Y g:i A', strtotime($email['sent_at'])); ?></span>
                                            </div>
                                            <div class="text-gray-700 mt-2 text-sm whitespace-pre-line"><?php echo htmlspecialchars($email['message']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Drafts Section -->
                        <div id="drafts-section" class="section <?php echo $active_section === 'drafts' ? 'active' : ''; ?>">
                            <h2 class="text-xl sm:text-2xl font-bold text-navy mb-6">Drafts</h2>
                            <?php if (empty($draft_emails)): ?>
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                    <i class="fas fa-edit text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No Drafts</h3>
                                    <p class="text-gray-500">You haven't saved any drafts yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($draft_emails as $draft): ?>
                                        <div class="email-list-item p-4 border rounded-lg">
                                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                                                <div class="flex-1">
                                                    <h3 class="font-medium text-navy text-sm sm:text-base"><?php echo htmlspecialchars($draft['subject']); ?></h3>
                                                    <p class="text-xs sm:text-sm text-gray-600">To: <?php echo htmlspecialchars($draft['to_email']); ?></p>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <span class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($draft['created_at'])); ?></span>
                                                    <div class="draft-actions flex space-x-2">
                                                        <a href="?section=compose&load_draft=<?php echo $draft['id']; ?>" class="text-blue-600 hover:underline text-xs sm:text-sm">Edit</a>
                                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this draft?');">
                                                            <input type="hidden" name="action" value="delete_draft">
                                                            <input type="hidden" name="draft_id" value="<?php echo $draft['id']; ?>">
                                                            <button type="submit" class="text-red-600 hover:underline text-xs sm:text-sm">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-gray-700 mt-2 text-sm whitespace-pre-line"><?php echo htmlspecialchars($draft['message']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- User Groups Sections -->
                        <div id="students-section" class="section <?php echo $active_section === 'students' ? 'active' : ''; ?>">
                            <h2 class="text-xl sm:text-2xl font-bold text-navy mb-6">Students</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 user-grid">
                                <?php 
                                $student_users = array_filter($users, function($user) {
                                    return $user['role'] === 'student';
                                });
                                ?>
                                <?php foreach ($student_users as $user): ?>
                                    <div class="border rounded-lg p-3 sm:p-4 hover:bg-gray-50">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                                <span class="text-blue-600 font-bold text-xs sm:text-sm"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-sm sm:text-base truncate"><?php echo $user['name']; ?></p>
                                                <p class="text-xs sm:text-sm text-gray-600 truncate"><?php echo $user['email']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div id="teachers-section" class="section <?php echo $active_section === 'teachers' ? 'active' : ''; ?>">
                            <h2 class="text-xl sm:text-2xl font-bold text-navy mb-6">Teachers</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 user-grid">
                                <?php 
                                $teacher_users = array_filter($users, function($user) {
                                    return $user['role'] === 'teacher';
                                });
                                ?>
                                <?php foreach ($teacher_users as $user): ?>
                                    <div class="border rounded-lg p-3 sm:p-4 hover:bg-gray-50">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-green-100 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                                <span class="text-green-600 font-bold text-xs sm:text-sm"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-sm sm:text-base truncate"><?php echo $user['name']; ?></p>
                                                <p class="text-xs sm:text-sm text-gray-600 truncate"><?php echo $user['email']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div id="parents-section" class="section <?php echo $active_section === 'parents' ? 'active' : ''; ?>">
                            <h2 class="text-xl sm:text-2xl font-bold text-navy mb-6">Parents</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 user-grid">
                                <?php 
                                $parent_users = array_filter($users, function($user) {
                                    return $user['role'] === 'parent';
                                });
                                ?>
                                <?php foreach ($parent_users as $user): ?>
                                    <div class="border rounded-lg p-3 sm:p-4 hover:bg-gray-50">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                                <span class="text-purple-600 font-bold text-xs sm:text-sm"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-sm sm:text-base truncate"><?php echo $user['name']; ?></p>
                                                <p class="text-xs sm:text-sm text-gray-600 truncate"><?php echo $user['email']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div id="developers-section" class="section <?php echo $active_section === 'developers' ? 'active' : ''; ?>">
                            <h2 class="text-xl sm:text-2xl font-bold text-navy mb-6">Content Developers</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 user-grid">
                                <?php 
                                $developer_users = array_filter($users, function($user) {
                                    return $user['role'] === 'developer';
                                });
                                ?>
                                <?php foreach ($developer_users as $user): ?>
                                    <div class="border rounded-lg p-3 sm:p-4 hover:bg-gray-50">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                                                <span class="text-yellow-600 font-bold text-xs sm:text-sm"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-sm sm:text-base truncate"><?php echo $user['name']; ?></p>
                                                <p class="text-xs sm:text-sm text-gray-600 truncate"><?php echo $user['email']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div id="all_users-section" class="section <?php echo $active_section === 'all_users' ? 'active' : ''; ?>">
                            <h2 class="text-xl sm:text-2xl font-bold text-navy mb-6">All Users</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 user-grid">
                                <?php foreach ($users as $user): ?>
                                    <div class="border rounded-lg p-3 sm:p-4 hover:bg-gray-50">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center mr-3 flex-shrink-0 
                                                <?php 
                                                if ($user['role'] === 'student') echo 'bg-blue-100';
                                                elseif ($user['role'] === 'teacher') echo 'bg-green-100';
                                                elseif ($user['role'] === 'parent') echo 'bg-purple-100';
                                                else echo 'bg-yellow-100';
                                                ?>">
                                                <span class="font-bold text-xs sm:text-sm
                                                    <?php 
                                                    if ($user['role'] === 'student') echo 'text-blue-600';
                                                    elseif ($user['role'] === 'teacher') echo 'text-green-600';
                                                    elseif ($user['role'] === 'parent') echo 'text-purple-600';
                                                    else echo 'text-yellow-600';
                                                    ?>">
                                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-sm sm:text-base truncate"><?php echo $user['name']; ?></p>
                                                <p class="text-xs sm:text-sm text-gray-600 truncate"><?php echo $user['email']; ?></p>
                                                <span class="inline-block mt-1 px-2 py-1 text-xs rounded-full 
                                                    <?php 
                                                    if ($user['role'] === 'student') echo 'bg-blue-100 text-blue-800';
                                                    elseif ($user['role'] === 'teacher') echo 'bg-green-100 text-green-800';
                                                    elseif ($user['role'] === 'parent') echo 'bg-purple-100 text-purple-800';
                                                    else echo 'bg-yellow-100 text-yellow-800';
                                                    ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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

        // Email functionality
        const recipients = [];
        const emailInput = document.getElementById('email-input');
        const recipientsContainer = document.getElementById('recipients-container');
        const toEmailsField = document.getElementById('to_emails');
        
        // Initialize recipients from draft if exists
        <?php if (isset($draft) && !empty($draft['to_email'])): ?>
            const draftEmails = '<?php echo $draft['to_email']; ?>'.split(',');
            draftEmails.forEach(email => {
                if (email.trim()) {
                    addRecipient(email.trim());
                }
            });
        <?php endif; ?>
        
        function updateRecipientsField() {
            toEmailsField.value = recipients.join(',');
        }
        
        function addRecipient(email, displayText = null) {
            if (recipients.includes(email)) return;
            
            recipients.push(email);
            
            const chip = document.createElement('div');
            chip.className = 'user-chip';
            chip.innerHTML = `
                ${displayText || email}
                <span class="remove" onclick="removeRecipient('${email}', this)">×</span>
            `;
            
            recipientsContainer.insertBefore(chip, emailInput);
            updateRecipientsField();
        }
        
        function removeRecipient(email, element) {
            const index = recipients.indexOf(email);
            if (index > -1) {
                recipients.splice(index, 1);
            }
            element.parentElement.remove();
            updateRecipientsField();
        }
        
        // Handle email input
        emailInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const email = this.value.trim();
                if (email && isValidEmail(email)) {
                    addRecipient(email);
                    this.value = '';
                }
            }
        });
        
        function isValidEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
        
        // Section navigation
        function showSection(sectionName) {
            // Hide all sections
            const sections = document.querySelectorAll('.section');
            sections.forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            const selectedSection = document.getElementById(sectionName + '-section');
            if (selectedSection) {
                selectedSection.classList.add('active');
            }
            
            // Update sidebar active state
            const sidebarItems = document.querySelectorAll('.sidebar-item');
            sidebarItems.forEach(item => {
                item.classList.remove('active');
            });
            
            // Set active sidebar item
            const activeSidebarItem = document.querySelector(`.sidebar-item[onclick="showSection('${sectionName}')"]`);
            if (activeSidebarItem) {
                activeSidebarItem.classList.add('active');
            }
            
            // Update URL without reloading page
            const url = new URL(window.location);
            url.searchParams.set('section', sectionName);
            window.history.pushState({}, '', url);
            
            // Close sidebar on mobile
            if (window.innerWidth < 1024) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        }
        
        // User group selection
        function selectUserGroup(group) {
            const users = <?php echo json_encode($users); ?>;
            let groupEmails = [];
            let groupNames = [];
            
            switch(group) {
                case 'students':
                    groupEmails = users.filter(u => u.role === 'student').map(u => u.email);
                    groupNames = users.filter(u => u.role === 'student').map(u => u.name + ' (student)');
                    break;
                case 'teachers':
                    groupEmails = users.filter(u => u.role === 'teacher').map(u => u.email);
                    groupNames = users.filter(u => u.role === 'teacher').map(u => u.name + ' (teacher)');
                    break;
                case 'parents':
                    groupEmails = users.filter(u => u.role === 'parent').map(u => u.email);
                    groupNames = users.filter(u => u.role === 'parent').map(u => u.name + ' (parent)');
                    break;
                case 'developers':
                    groupEmails = users.filter(u => u.role === 'developer').map(u => u.email);
                    groupNames = users.filter(u => u.role === 'developer').map(u => u.name + ' (developer)');
                    break;
                case 'all':
                    groupEmails = users.map(u => u.email);
                    groupNames = users.map(u => u.name + ' (' + u.role + ')');
                    break;
            }
            
            // Clear current recipients
            recipients.length = 0;
            document.querySelectorAll('.user-chip').forEach(chip => chip.remove());
            
            // Add group recipients
            groupEmails.forEach((email, index) => {
                addRecipient(email, groupNames[index]);
            });
            
            // Show compose section
            showSection('compose');
        }
        
        // Save draft functionality
        function saveAsDraft() {
            const form = document.getElementById('compose-form');
            const formData = new FormData(form);
            formData.set('action', 'save_draft');
            
            fetch('admin_communications.php', {
                method: 'POST',
                body: formData
            }).then(response => {
                window.location.href = 'admin_communications.php?section=drafts';
            });
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Show the active section based on URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const section = urlParams.get('section') || 'compose';
            showSection(section);
        });
    </script>
</body>
</html>