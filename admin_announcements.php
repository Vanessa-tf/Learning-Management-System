<?php
include(__DIR__ . "/includes/db.php");

// AUTO-DELETE EXPIRED ANNOUNCEMENTS - ADD THIS RIGHT HERE
try {
    $cleanup_stmt = $pdo->prepare("DELETE FROM announcements WHERE expires_at IS NOT NULL AND expires_at < NOW()");
    $cleanup_stmt->execute();
} catch (Exception $e) {
    // Silent fail - don't interrupt the page if cleanup fails
}
// END OF AUTO-DELETE COD

// Get the actual admin user ID safely
try {
    $admin_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' OR role = 'administrator' LIMIT 1");
    $admin_user = $admin_stmt->fetch(PDO::FETCH_ASSOC);
    $admin_id = $admin_user['id'] ?? null;
    
    if (!$admin_id) {
        $user_stmt = $pdo->query("SELECT id FROM users LIMIT 1");
        $any_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        $admin_id = $any_user['id'] ?? 1;
    }
} catch (Exception $e) {
    $admin_id = 1;
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        $success_message = "Announcement deleted successfully!";
    } catch (Exception $e) {
        $error_message = "Error deleting announcement: " . $e->getMessage();
    }
}

// Handle form submission for new announcements
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

if (isset($_POST['edit_id'])) {
    // Handle edit/update
    $edit_id = (int)$_POST['edit_id'];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $target_audience = $_POST['target_audience'] ?? [];
    $scheduled_for = !empty($_POST['scheduled_for']) ? $_POST['scheduled_for'] : null;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $action = $_POST['action'] ?? 'draft';
    
    if (!empty($title) && !empty($message) && !empty($target_audience)) {
        try {
            $is_published = $action === 'publish' ? 1 : 0;
            
            $stmt = $pdo->prepare("
                UPDATE announcements 
                SET title = :title, message = :message, target_audience = :target_audience, 
                    scheduled_for = :scheduled_for, expires_at = :expires_at, is_published = :is_published, updated_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':title' => $title,
                ':message' => $message,
                ':target_audience' => json_encode($target_audience),
                ':scheduled_for' => $scheduled_for,
                ':expires_at' => $expires_at,
                ':is_published' => $is_published,
                ':id' => $edit_id
            ]);
                $success_message = $is_published ? "Announcement updated and published successfully!" : "Announcement updated and saved as draft!";
                
            } catch (Exception $e) {
                $error_message = "Error updating announcement: " . $e->getMessage();
            }
        } else {
            $error_message = "Please fill in all required fields and select at least one target audience.";
        }
    } else {
    // Handle new announcement creation
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $target_audience = $_POST['target_audience'] ?? [];
    $scheduled_for = !empty($_POST['scheduled_for']) ? $_POST['scheduled_for'] : null;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $action = $_POST['action'] ?? 'draft';
    
    if (!empty($title) && !empty($message) && !empty($target_audience)) {
        try {
            $is_published = $action === 'publish' ? 1 : 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, message, target_audience, scheduled_for, expires_at, is_published, created_by) 
                VALUES (:title, :message, :target_audience, :scheduled_for, :expires_at, :is_published, :admin_id)
            ");
            
            $stmt->execute([
                ':title' => $title,
                ':message' => $message,
                ':target_audience' => json_encode($target_audience),
                ':scheduled_for' => $scheduled_for,
                ':expires_at' => $expires_at,
                ':is_published' => $is_published,
                ':admin_id' => $admin_id
            ]);
                
                $success_message = $is_published ? "Announcement published successfully!" : "Announcement saved as draft!";
                
            } catch (Exception $e) {
                $error_message = "Error creating announcement: " . $e->getMessage();
            }
        } else {
            $error_message = "Please fill in all required fields and select at least one target audience.";
        }
    }
}

// Check if we're editing an announcement
$editing_announcement = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = :id");
    $stmt->execute([':id' => $edit_id]);
    $editing_announcement = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all announcements
$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a 
    LEFT JOIN users u ON a.created_by = u.id 
    ORDER BY a.created_at DESC
");
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count announcements by status
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(is_published) as published,
        SUM(NOT is_published) as drafts
    FROM announcements
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Admin - NovaTech</title>
    <link rel="icon" type="image/png" sizes="128x128" href="Images/ChatGPT Image Nov 8, 2025, 11_25_13 AM.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #1e3a6c;
            --gold: #fbbf24;
            --beige: #f5f5dc;
        }
        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; }
        .bg-navy { background-color: var(--navy); }
        .bg-gold { background-color: var(--gold); }
        .bg-beige { background-color: var(--beige); }
        .text-navy { color: var(--navy); }
        .text-gold { color: var(--gold); }
        
        .dashboard-card { 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
        }
        .dashboard-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15); 
        }
        
        .audience-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin: 0.125rem;
        }
        .audience-teacher { background-color: #dbeafe; color: #1e40af; }
        .audience-student { background-color: #dcfce7; color: #166534; }
        .audience-parent { background-color: #fef3c7; color: #92400e; }
        .audience-developer { background-color: #f3e8ff; color: #7c3aed; }
        
        .sidebar { transition: all 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
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
        <li><a href="admin_analytics.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-chart-line mr-3"></i> Analytics & Reports</a></li>
        <li><a href="admin_announcements.php" class="flex items-center p-2 rounded-lg active-nav-item"><i class="fas fa-bullhorn mr-3"></i> Announcements</a></li>
        <li><a href="admin_settings.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-cog mr-3"></i> Settings</a></li>
        <li><a href="logout.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10"><i class="fas fa-sign-out-alt mr-3"></i> Logout</a></li>
    </ul>
</nav>
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
                    <h1 class="text-xl font-bold text-navy">
                        <?= $editing_announcement ? 'Edit Announcement' : 'Announcements Management' ?>
                    </h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="text-navy">
                                <i class="fas fa-bell"></i>
                            </button>
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

        <!-- Main Content Area -->
        <main class="container mx-auto px-6 py-8">
            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Announcements</p>
                            <p class="text-2xl font-bold text-navy"><?= $stats['total'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-bullhorn text-blue-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Published</p>
                            <p class="text-2xl font-bold text-green-600"><?= $stats['published'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Drafts</p>
                            <p class="text-2xl font-bold text-orange-600"><?= $stats['drafts'] ?? 0 ?></p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-edit text-orange-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Create/Edit Announcement Form -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-navy mb-6">
                        <?= $editing_announcement ? 'Edit Announcement' : 'Create New Announcement' ?>
                    </h2>
                    <form method="POST">
                        <?php if ($editing_announcement): ?>
                            <input type="hidden" name="edit_id" value="<?= $editing_announcement['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                                <input type="text" name="title" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent"
                                       placeholder="Enter announcement title"
                                       value="<?= $editing_announcement ? htmlspecialchars($editing_announcement['title']) : '' ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                                <textarea name="message" rows="5" required
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent"
                                          placeholder="Type your announcement message here..."><?= $editing_announcement ? htmlspecialchars($editing_announcement['message']) : '' ?></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Target Audience *</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <?php
                                    $targets = ['teacher', 'student', 'parent', 'developer'];
                                    $current_targets = $editing_announcement ? json_decode($editing_announcement['target_audience'], true) : [];
                                    foreach ($targets as $target):
                                    ?>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="target_audience[]" value="<?= $target ?>" 
                                                   class="rounded border-gray-300 text-navy focus:ring-navy"
                                                   <?= $editing_announcement && in_array($target, $current_targets) ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm"><?= ucfirst($target) ?>s</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Schedule (Optional)</label>
                                <input type="datetime-local" name="scheduled_for"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent"
                                       value="<?= $editing_announcement && $editing_announcement['scheduled_for'] ? date('Y-m-d\TH:i', strtotime($editing_announcement['scheduled_for'])) : '' ?>">
                                <p class="text-xs text-gray-500 mt-1">Leave empty to publish immediately</p>
                            </div>
                            <div>
    <label class="block text-sm font-medium text-gray-700 mb-2">Expiration Date (Optional)</label>
    <input type="datetime-local" name="expires_at"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent"
           value="<?= $editing_announcement && $editing_announcement['expires_at'] ? date('Y-m-d\TH:i', strtotime($editing_announcement['expires_at'])) : '' ?>">
    <p class="text-xs text-gray-500 mt-1">Leave empty for no automatic deletion</p>
</div>
							
                            <div class="flex space-x-3 pt-4">
                                <?php if ($editing_announcement): ?>
                                    <a href="admin_announcements.php" 
                                       class="flex-1 bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600 transition font-medium text-center">
                                        <i class="fas fa-times mr-2"></i>Cancel
                                    </a>
                                <?php endif; ?>
                                <button type="submit" name="action" value="draft" 
                                        class="<?= $editing_announcement ? 'flex-1' : 'flex-1' ?> bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600 transition font-medium">
                                    <i class="fas fa-save mr-2"></i><?= $editing_announcement ? 'Update Draft' : 'Save as Draft' ?>
                                </button>
                                <button type="submit" name="action" value="publish" 
                                        class="<?= $editing_announcement ? 'flex-1' : 'flex-1' ?> bg-navy text-white py-2 px-4 rounded-lg hover:bg-blue-800 transition font-medium">
                                    <i class="fas fa-paper-plane mr-2"></i><?= $editing_announcement ? 'Update & Publish' : 'Publish Now' ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Announcements List -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-navy mb-6">Recent Announcements</h2>
                    <div class="space-y-4 max-h-[600px] overflow-y-auto">
                        <?php if (empty($announcements)): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-bullhorn text-4xl mb-3 text-gray-300"></i>
                                <p>No announcements yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): 
                                $targets = json_decode($announcement['target_audience'], true);
                            ?>
                                <div class="border border-gray-200 rounded-lg p-4 bg-white hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($announcement['title']) ?></h3>
                                        <span class="text-xs px-2 py-1 rounded-full <?= $announcement['is_published'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= $announcement['is_published'] ? 'Published' : 'Draft' ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars($announcement['message']) ?></p>
                                    
                                    <div class="flex flex-wrap gap-1 mb-3">
                                        <?php foreach ($targets as $target): ?>
                                            <span class="audience-badge audience-<?= $target ?>">
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
                                       
                                        <div class="flex space-x-2">
                                            <?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?>
                                            <div class="flex space-x-1">
                                                <a href="admin_announcements.php?edit_id=<?= $announcement['id'] ?>" 
                                                   class="text-blue-600 hover:text-blue-800" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="admin_announcements.php?delete_id=<?= $announcement['id'] ?>" 
                                                   class="text-red-600 hover:text-red-800" 
                                                   onclick="return confirm('Are you sure you want to delete this announcement?')"
                                                   title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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

        // Auto-hide messages
        setTimeout(() => {
            const messages = document.querySelectorAll('.bg-red-100,);
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