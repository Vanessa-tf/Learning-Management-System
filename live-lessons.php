<?php
session_start();
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

// Set timezone to UTC to match database
date_default_timezone_set('UTC');

// Check if user is logged in and has 'student' role
check_session();
if ($_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

// Fetch user details
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT first_name, package_selected FROM students WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("User $user_id not found in students table");
        session_destroy();
        header("Location: login.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching student $user_id: " . $e->getMessage());
    session_destroy();
    header("Location: login.php");
    exit;
}

// Get user's initials for avatar
$username = htmlspecialchars($user['first_name']);
$initials = strtoupper(substr($username, 0, 2));

// Define package features
$package_features = [
    'Basic' => ['subjects' => 1, 'features' => ['courses', 'past_papers']],
    'Standard' => ['subjects' => 2, 'features' => ['courses', 'past_papers', 'live_lessons', 'progress_tracking']],
    'Premium' => ['subjects' => 4, 'features' => ['courses', 'past_papers', 'live_lessons', 'progress_tracking', 'mock_exams', 'social_forums']]
];
$package = $user['package_selected'] ?? 'Basic';
$max_subjects = $package_features[$package]['subjects'] ?? 1;
$features = $package_features[$package]['features'] ?? ['courses', 'past_papers'];

// Check if live_lessons feature is available
if (!in_array('live_lessons', $features)) {
    error_log("User $user_id attempted to access live lessons without permission");
    header("Location: student-dashboard.php");
    exit;
}

// Fetch enrolled courses
$courses = [];
try {
    $stmt = $pdo->prepare("SELECT e.course_id, c.course_name FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.user_id = :user_id LIMIT :max_subjects");
    $stmt->bindValue('user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue('max_subjects', $max_subjects, PDO::PARAM_INT);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching enrollments for user $user_id: " . $e->getMessage());
}

// Fetch teachers for each enrolled course to get the correct Teams links
$subject_teams_links = [];
foreach ($courses as $course) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id as teacher_id, c.course_name 
            FROM student_teacher_assignments sta 
            JOIN users u ON sta.teacher_id = u.id 
            JOIN courses c ON sta.course_id = c.id 
            WHERE sta.student_id = ? AND sta.course_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $course['course_id']]);
        $teacher_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($teacher_data) {
            $subject_code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', substr($course['course_name'], 0, 6)));
            $subject_teams_links[$course['course_id']] = "https://teams.microsoft.com/l/team/19%3a" . $subject_code . "_" . $teacher_data['teacher_id'] . "%40thread.tacv2/conversations?groupId=" . $subject_code . "&tenantId=novatech";
        }
    } catch (PDOException $e) {
        error_log("Error fetching teacher for course {$course['course_id']}: " . $e->getMessage());
    }
}

// Fetch upcoming live lessons for enrolled courses
$upcoming_lessons = [];
try {
    $course_ids = array_column($courses, 'course_id');
    if (!empty($course_ids)) {
        $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT ll.id, ll.course_id, ll.lesson_name, ll.date, ll.start_time, ll.end_time, ll.link, ll.recording_link, c.course_name AS course_display
            FROM live_lessons ll
            JOIN courses c ON ll.course_id = c.id
            WHERE ll.course_id IN ($placeholders)
            AND (ll.date > CURDATE() OR (ll.date = CURDATE() AND ll.end_time > CURTIME()))
            AND ll.date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY ll.date ASC, ll.start_time ASC
            LIMIT 5
        ");
        $stmt->execute($course_ids);
        $upcoming_lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching live lessons for user $user_id: " . $e->getMessage());
}

// Format live lessons for display (SAST, UTC+2)
foreach ($upcoming_lessons as &$lesson) {
    $lesson_datetime = new DateTime("{$lesson['date']} {$lesson['start_time']}", new DateTimeZone('UTC'));
    $lesson_datetime->setTimezone(new DateTimeZone('Africa/Johannesburg'));
    $lesson['date_display'] = $lesson_datetime->format('D, M d');
    $lesson['time_display'] = $lesson_datetime->format('h:i A') . ' - ' . 
                              (new DateTime("{$lesson['date']} {$lesson['end_time']}", new DateTimeZone('UTC')))
                              ->setTimezone(new DateTimeZone('Africa/Johannesburg'))->format('h:i A');
}

// Fetch notifications for the student
$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, type, message, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching notifications for student $user_id: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Lessons - NovaTech FET College</title>
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
        .dashboard-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .dashboard-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
        .sidebar { transition: all 0.3s ease; }
        .notification-dot { position: absolute; top: -5px; right: -5px; width: 12px; height: 12px; background-color: #ef4444; border-radius: 50%; }
        .subject-card { transition: all 0.3s ease; }
        .subject-card:hover { transform: scale(1.02); }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
    </style>
</head>
<body class="bg-beige">
    <!-- Overlay for mobile sidebar -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar Navigation -->
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
                    <div class="w-12 h-12 bg-gold rounded-full flex items-center justify-center mr-3">
                        <span class="text-navy font-bold"><?php echo $initials; ?></span>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?php echo $username; ?></h3>
                        <p class="text-gold text-sm"><?php echo !empty($courses) ? implode(', ', array_column($courses, 'course_name')) : 'No courses enrolled'; ?></p>
                    </div>
                </div>
            </div>
            <nav class="space-y-2">
                <a href="student-dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'student-dashboard.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-home mr-3"></i><span>Dashboard</span>
                </a>
                <?php if (in_array('courses', $features)): ?>
                <a href="my-courses.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'my-courses.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-book-open mr-3"></i><span>My Subjects</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('past_papers', $features)): ?>
                <a href="past-papers.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'past-papers.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-file-alt mr-3"></i><span>Past Papers</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('live_lessons', $features)): ?>
                <a href="live-lessons.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white border-b-2 border-gold">
                    <i class="fas fa-video mr-3"></i><span>Live Lessons</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('progress_tracking', $features)): ?>
                <a href="progress-tracking.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'progress-tracking.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-chart-line mr-3"></i><span>Progress Tracking</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('social_forums', $features)): ?>
                <a href="study-groups.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'study-groups.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-users mr-3"></i><span>Social Chatroom</span>
                </a>
				<a href="student-messages.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'student-messages.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-envelope mr-3"></i><span>Messages</span>
                </a>
                <?php endif; ?>
                <a href="schedule.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-3"></i><span>Timetable</span>
                </a>
                <a href="log_case.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'log_case.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-life-ring mr-3"></i><span>Log Cases</span>
                </a>
                <a href="settings.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-cog mr-3"></i><span>My Profile</span>
                </a>
                <a href="logout.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'logout.php' ? 'border-b-2 border-gold' : ''; ?>">
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
                    <button class="text-navy md:hidden" id="menuButton"><i class="fas fa-bars text-xl"></i></button>
                    <h1 class="text-xl font-bold text-navy">Live Lessons</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button id="notificationButton" class="text-navy relative">
                                <i class="fas fa-bell text-xl"></i>
                                <span id="notificationDot" class="notification-dot <?php echo hasUnreadNotifications($notifications) ? '' : 'hidden'; ?>"></span>
                            </button>
                            <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto">
                                <div class="p-4 border-b">
                                    <h3 class="text-lg font-semibold text-navy">Notifications</h3>
                                </div>
                                <div id="notificationList" class="divide-y"></div>
                                <div class="p-4 border-t text-center">
                                    <a href="notifications.php" class="text-gold hover:underline text-sm">View All Notifications</a>
                                </div>
                            </div>
                        </div>
                        <div class="hidden md:flex items-center">
                            <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-2">
                                <span class="text-navy font-bold text-sm"><?php echo $initials; ?></span>
                            </div>
                            <span class="text-navy font-medium"><?php echo $username; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Live Lessons Content -->
        <main class="container mx-auto px-6 py-8">
            <!-- Upcoming Lessons Section -->
            <?php if (!empty($upcoming_lessons)): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card mb-8">
                <h2 class="text-xl font-bold text-navy mb-4">Upcoming Live Lessons</h2>
                <div class="space-y-4">
                    <?php foreach ($upcoming_lessons as $lesson): ?>
                    <div class="flex justify-between items-center p-4 border border-gray-200 rounded-lg hover:shadow-md transition">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-video text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-navy"><?php echo htmlspecialchars($lesson['lesson_name']); ?></h3>
                                <p class="text-sm text-gray-600">
                                    <i class="far fa-clock mr-2"></i><?php echo $lesson['date_display']; ?>, <?php echo $lesson['time_display']; ?> (SAST)
                                </p>
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-book mr-2"></i><?php echo htmlspecialchars($lesson['course_display']); ?>
                                </p>
                            </div>
                        </div>
                        <div>
                            <?php if (!empty($lesson['link'])): ?>
                                <a href="<?php echo htmlspecialchars($lesson['link']); ?>" class="bg-navy text-white text-sm py-2 px-4 rounded-lg hover:bg-opacity-90 transition" target="_blank">
                                    <i class="fas fa-external-link-alt mr-1"></i> Join Lesson
                                </a>
                            <?php else: ?>
                                <span class="text-gray-500 text-sm">Link not available</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Subjects Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-navy">My Subjects</h2>
                </div>
                
                <?php if (empty($courses)): ?>
                    <p class="text-gray-600">You are not enrolled in any subjects. <a href="enroll.php" class="text-gold hover:underline">Enroll now</a> to access live lessons.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($courses as $course): ?>
                            <div class="subject-card bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-6 shadow-sm">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 bg-navy rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-book text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-navy"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-sm text-gray-600 mb-2">Teams Link for this subject:</p>
                                    <?php if (isset($subject_teams_links[$course['course_id']])): ?>
                                        <a href="<?php echo htmlspecialchars($subject_teams_links[$course['course_id']]); ?>" 
                                           class="inline-flex items-center bg-blue-600 text-white text-sm py-2 px-4 rounded-lg hover:bg-blue-700 transition" 
                                           target="_blank">
                                            <i class="fab fa-microsoft mr-2"></i> Join Teams
                                        </a>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-500">Teams link not available</p>
                                        <p class="text-xs text-gray-400">Contact your teacher for access</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex space-x-2 mt-4">
                                    <a href="schedule.php?subject=<?php echo $course['course_id']; ?>" 
                                       class="flex-1 text-center bg-white border border-navy text-navy text-sm py-2 px-3 rounded-lg hover:bg-navy hover:text-white transition">
                                        <i class="fas fa-calendar-alt mr-1"></i> Timetable
                                    </a>
                                    <a href="my-courses.php?subject=<?php echo $course['course_id']; ?>" 
                                       class="flex-1 text-center bg-white border border-navy text-navy text-sm py-2 px-3 rounded-lg hover:bg-navy hover:text-white transition">
                                        <i class="fas fa-book-open mr-1"></i> Content
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Actions -->
            <div class="mt-8 grid grid-cols-1 <?php echo in_array('social_forums', $features) ? 'md:grid-cols-2' : 'md:grid-cols-1'; ?> gap-6">
                <?php if (in_array('social_forums', $features)): ?>
                <!-- Study Groups Card - Only visible for Premium package -->
                <a href="study-groups.php" class="bg-white rounded-xl shadow-lg p-6 dashboard-card text-center hover:shadow-xl transition">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Study Groups</h3>
                    <p class="text-sm text-gray-600">Collaborate with classmates in study groups</p>
                    <div class="mt-2">
                        <span class="inline-block bg-gold text-navy text-xs px-2 py-1 rounded-full font-semibold">Premium Feature</span>
                    </div>
                </a>
                <?php endif; ?>
                
                <!-- Past Papers Card - Available for all packages with past_papers feature -->
                <?php if (in_array('past_papers', $features)): ?>
                <a href="past-papers.php" class="bg-white rounded-xl shadow-lg p-6 dashboard-card text-center hover:shadow-xl transition">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-alt text-purple-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Past Papers</h3>
                    <p class="text-sm text-gray-600">Access previous exam papers for practice</p>
                </a>
                <?php endif; ?>
            </div>
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

        // Notification handling
        const notificationButton = document.getElementById('notificationButton');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationList = document.getElementById('notificationList');
        const notificationDot = document.getElementById('notificationDot');

        notificationButton.addEventListener('click', () => {
            notificationDropdown.classList.toggle('hidden');
            if (!notificationDropdown.classList.contains('hidden')) {
                markNotificationsAsRead();
            }
        });

        function fetchNotifications() {
            fetch('notifications_api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching notifications:', data.error);
                        return;
                    }
                    notificationList.innerHTML = '';
                    let unreadCount = 0;

                    if (data.length === 0) {
                        notificationList.innerHTML = '<p class="p-4 text-sm text-gray-600">No notifications</p>';
                    } else {
                        data.forEach(notification => {
                            if (!notification.is_read) unreadCount++;
                            const notificationItem = document.createElement('div');
                            notificationItem.className = 'p-4 hover:bg-gray-50 cursor-pointer';
                            notificationItem.innerHTML = `
                                <div class="flex items-start">
                                    <i class="fas fa-${getNotificationIcon(notification.type)} text-${getNotificationColor(notification.type)} mr-3 mt-1"></i>
                                    <div>
                                        <p class="text-sm text-gray-800">${notification.message}</p>
                                        <p class="text-xs text-gray-500">${new Date(notification.created_at).toLocaleString('en-ZA', { timeZone: 'Africa/Johannesburg' })}</p>
                                    </div>
                                </div>
                            `;
                            notificationList.appendChild(notificationItem);
                        });
                    }

                    notificationDot.classList.toggle('hidden', unreadCount === 0);
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                });
        }

        function getNotificationIcon(type) {
            const icons = {
                general: 'bell',
                assignment: 'file-alt',
                live_lesson: 'video',
                exam: 'clipboard-check',
                study_group: 'users'
            };
            return icons[type] || 'bell';
        }

        function getNotificationColor(type) {
            const colors = {
                general: 'gray-600',
                assignment: 'blue-600',
                live_lesson: 'green-600',
                exam: 'purple-600',
                study_group: 'yellow-600'
            };
            return colors[type] || 'gray-600';
        }

        function markNotificationsAsRead() {
            fetch('mark_notifications_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
                .then(() => {
                    notificationDot.classList.add('hidden');
                })
                .catch(error => {
                    console.error('Error marking notifications as read:', error);
                });
        }

        fetchNotifications();
        setInterval(fetchNotifications, 30000);

        // Close dropdown when clicking outside
        document.addEventListener('click', (event) => {
            if (!notificationButton.contains(event.target) && !notificationDropdown.contains(event.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to check for unread notifications
function hasUnreadNotifications($notifications) {
    foreach ($notifications as $notification) {
        if (!$notification['is_read']) {
            return true;
        }
    }
    return false;
}
?>