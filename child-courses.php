<?php
session_start();
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

// Check if user is logged in and has 'parent' role
check_session();
if ($_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit;
}

// Fetch parent details from financiers table and get names from students table
$parent_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT f.*, s.financier_name, s.financier_relationship 
                           FROM financiers f 
                           INNER JOIN students s ON f.student_id = s.id 
                           WHERE f.id = :parent_id");
    $stmt->execute(['parent_id' => $parent_id]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$parent) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
    
    // Get parent's full name from the students table (financier_name field)
    $parent_name = !empty($parent['financier_name']) ? $parent['financier_name'] : 'Parent';
    $initials = '';
    if (!empty($parent_name)) {
        $name_parts = explode(' ', $parent_name);
        $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : substr($name_parts[0], 1, 1)));
    } else {
        $initials = 'P';
    }
    
    // Fetch child/children linked to this parent
    $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.surname, s.email 
                           FROM students s 
                           INNER JOIN financiers f ON s.id = f.student_id 
                           WHERE f.id = :parent_id");
    $stmt->execute(['parent_id' => $parent_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no children linked, show message
    if (empty($children)) {
        $no_children = true;
        $courses = [];
    } else {
        $no_children = false;
        // For simplicity, we'll focus on the first child.
        $selected_child = $children[0];
        $child_id = $selected_child['id'];
        $child_name = htmlspecialchars($selected_child['first_name'] . ' ' . $selected_child['surname']);
        
        // Fetch child's enrolled courses
        $stmt = $pdo->prepare("SELECT c.id as course_id, c.course_name, e.progress, e.lessons_remaining 
                               FROM enrollments e 
                               INNER JOIN courses c ON e.course_id = c.id 
                               WHERE e.user_id = :child_id");
        $stmt->execute(['child_id' => $child_id]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "Unable to load course data. Please try again later.";
}

// Subject details for icons and colors
$subject_details = [
    'Mathematics' => ['icon' => 'fa-calculator', 'color' => 'blue'],
    'Physical Science' => ['icon' => 'fa-atom', 'color' => 'red'],
    'English' => ['icon' => 'fa-book', 'color' => 'yellow'],
    'CAT' => ['icon' => 'fa-computer', 'color' => 'green']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrolled Subjects - NovaTech FET College</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy: #1e3a6c;
            --gold: #fbbf24;
            --beige: #f5f5dc;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--beige); }
        .bg-navy { background-color: var(--navy); }
        .bg-gold { background-color: var(--gold); }
        .text-navy { color: var(--navy); }
        .text-gold { color: var(--gold); }
        .dashboard-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
        .sidebar { transition: all 0.3s ease; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
        .progress-bar { transition: width 1s ease-in-out; }
    </style>
</head>
<body class="bg-beige">
    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Sidebar Navigation -->
    <div class="sidebar bg-navy text-white w-64 fixed h-screen overflow-y-auto" id="sidebar">
        <div class="p-6">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center">
                    <img src="Images/ChatGPT Image Sep 15, 2025, 08_43_22 PM.png" alt="NovaTech Logo" class="h-10 w-auto"/>
                    <span class="ml-3 text-xl font-bold">NovaTech FET <span class="text-gold">College</span></span>
                </div>
                <button class="text-white md:hidden" id="closeSidebar"><i class="fas fa-times"></i></button>
            </div>
            <div class="mb-8 p-4 bg-white bg-opacity-10 rounded-lg">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gold rounded-full flex items-center justify-center mr-3">
                        <span class="text-navy font-bold"><?php echo $initials; ?></span>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?php echo htmlspecialchars($parent_name); ?></h3>
                        <p class="text-gold text-sm">Parent Portal</p>
                        <?php if (!empty($parent['financier_relationship'])): ?>
                        <p class="text-white text-xs opacity-80"><?php echo htmlspecialchars($parent['financier_relationship']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <nav class="space-y-2">
                <a href="parent_dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'parent_dashboard.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-home mr-3"></i><span>Dashboard</span>
                </a>
                <a href="child-progress.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'child-progress.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-chart-line mr-3"></i><span>Child's Progress</span>
                </a>
                <a href="child-courses.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'child-courses.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-book-open mr-3"></i><span>Enrolled Subjects</span>
                </a>
                <a href="child-timetable.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'child-timetable.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-3"></i><span>Class Timetable</span>
                </a>
                <a href="exam-results.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'exam-results.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-clipboard-check mr-3"></i><span>Exam Results</span>
                </a>
				<a href="parent-messages.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'parent-messages.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-envelope mr-3"></i><span>Messages</span>
                </a>
                <a href="parent_log_case.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-user-check mr-3"></i><span>Log Cases</span>
                </a>
                
                <a href="package-info.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'package-info.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-box mr-3"></i><span>Package Details</span>
                </a>
                <a href="parent_settings.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'parent-settings.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-cog mr-3"></i><span>Settings</span>
                </a>
                <a href="logout.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-sign-out-alt mr-3"></i><span>Logout</span>
                </a>
            </nav>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="md:ml-64">
        <!-- Top Navigation -->
        <header class="bg-white shadow-md">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <button class="text-navy md:hidden" id="menuButton"><i class="fas fa-bars"></i></button>
                    <h1 class="text-xl font-bold text-navy">Enrolled Subjects</h1>
                    <div class="flex items-center space-x-4">
                        <div class="hidden md:block">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-2">
                                    <span class="text-navy font-bold text-sm"><?php echo $initials; ?></span>
                                </div>
                                <span class="text-navy"><?php echo htmlspecialchars($parent_name); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Dashboard Content -->
        <main class="container mx-auto px-6 py-8">
            <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-8">
                <p><?php echo $error_message; ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($no_children): ?>
            <div class="bg-white rounded-xl shadow-lg p-8 mb-8 text-center">
                <i class="fas fa-user-plus text-6xl text-gold mb-4"></i>
                <h2 class="text-2xl font-bold text-navy mb-4">No Children Linked</h2>
                <p class="text-gray-600 mb-6">Your parent account is not currently linked to any student accounts. Please contact the school administrator to link your child's account.</p>
                <a href="contact.php" class="bg-navy text-white font-bold py-2 px-6 rounded-lg hover:bg-opacity-90 transition">Contact Administrator</a>
            </div>
            <?php else: ?>
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-navy"><?php echo $child_name; ?>'s Subjects</h2>
                <p class="text-gray-600">Tracking progress across <?php echo count($courses); ?> subjects</p>
            </div>

            <!-- Progress Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php foreach ($courses as $course): 
                    $subject_info = $subject_details[$course['course_name']] ?? ['icon' => 'fa-book', 'color' => 'gray'];
                ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition duration-200">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-<?php echo $subject_info['color']; ?>-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas <?php echo $subject_info['icon']; ?> text-<?php echo $subject_info['color']; ?>-600 text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg text-navy"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                <p class="text-gray-600 text-sm">In Progress</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-<?php echo $subject_info['color']; ?>-600">
                                <?php echo $course['progress']; ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>Progress</span>
                            <span><?php echo $course['progress']; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-<?php echo $subject_info['color']; ?>-600 h-2 rounded-full progress-bar" 
                                 data-width="<?php echo $course['progress']; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600">
                        <?php echo $course['lessons_remaining']; ?> lessons left
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Detailed Progress Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-xl font-bold text-navy mb-4">Detailed Progress</h3>
                
                <?php if (empty($courses)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-book-open text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-navy mb-2">No Subjects Enrolled</h3>
                        <p class="text-gray-600">No subjects found for <?php echo htmlspecialchars($child_name); ?></p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($courses as $course): 
                            $subject_info = $subject_details[$course['course_name']] ?? ['icon' => 'fa-book', 'color' => 'gray'];
                        ?>
                        <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold text-navy"><?php echo htmlspecialchars($course['course_name']); ?></h4>
                                <span class="text-<?php echo $subject_info['color']; ?>-600 font-bold"><?php echo $course['progress']; ?>% Complete</span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600 mb-2">
                                <span><?php echo intval($course['progress'] / 5); ?>/20 Topics</span>
                                <span><?php echo $course['lessons_remaining']; ?> lessons remaining</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-<?php echo $subject_info['color']; ?>-600 h-2 rounded-full progress-bar" 
                                     data-width="<?php echo $course['progress']; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Mobile sidebar toggle
        const menuButton = document.getElementById('menuButton');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        menuButton.addEventListener('click', () => {
            sidebar.classList.add('active');
            overlay.classList.add('active');
        });
        closeSidebar.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });

        // Animate progress bars
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.progress-bar').forEach(bar => {
                const width = bar.getAttribute('data-width');
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                    bar.style.transition = 'width 1s ease-in-out';
                }, 100);
            });
        });
    </script>
</body>
</html>