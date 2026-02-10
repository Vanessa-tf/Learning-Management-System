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
    error_log("User $user_id attempted to access lesson archive without permission");
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

// Handle search query
$search_results = [];
$search_query = '';
$course_id = '';
$date = '';
$today = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['search']) || isset($_GET['course_id']) || isset($_GET['date']))) {
    $search_query = trim($_GET['search'] ?? '');
    $course_id = $_GET['course_id'] ?? '';
    $date = trim($_GET['date'] ?? '');

    try {
        $sql = "
            SELECT ll.id, ll.course_id, ll.lesson_name, ll.date, ll.start_time, ll.end_time, ll.recording_link, c.course_name AS course_display
            FROM live_lessons ll
            JOIN courses c ON ll.course_id = c.id
        ";
        $params = [];
        $conditions = [];

        $course_ids = array_column($courses, 'course_id');
        if (!empty($course_ids)) {
            $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
            $conditions[] = "ll.course_id IN ($placeholders)";
            $params = array_merge($params, $course_ids);
        } else {
            $search_results = [];
        }

        $conditions[] = "ll.date < ?"; // Only past lessons
        $params[] = $today;

        $conditions[] = "ll.recording_link IS NOT NULL AND ll.recording_link != ''"; // Only lessons with recordings

        if (!empty($search_query)) {
            $conditions[] = "(ll.lesson_name LIKE ?)";
            $params[] = "%$search_query%";
        }

        if (!empty($course_id) && in_array($course_id, array_column($courses, 'course_id'))) {
            $conditions[] = "ll.course_id = ?";
            $params[] = $course_id;
        }

        if (!empty($date)) {
            $conditions[] = "ll.date = ?";
            $params[] = $date;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY ll.date DESC, ll.start_time ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format results for display (SAST)
        foreach ($search_results as &$lesson) {
            $lesson_datetime = new DateTime("{$lesson['date']} {$lesson['start_time']}", new DateTimeZone('UTC'));
            $lesson_datetime->setTimezone(new DateTimeZone('Africa/Johannesburg'));
            $lesson['display_date'] = $lesson_datetime->format('M d, Y');
            $lesson['time'] = $lesson_datetime->format('h:i A') . ' - ' . (new DateTime("{$lesson['date']} {$lesson['end_time']}", new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Africa/Johannesburg'))->format('h:i A');
        }
    } catch (PDOException $e) {
        error_log("Error searching lesson archive for user $user_id: " . $e->getMessage());
        $search_results = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson Archive - NovaTech FET College</title>
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
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
        .notification-dot { position: absolute; top: -5px; right: -5px; width: 12px; height: 12px; background-color: #ef4444; border-radius: 50%; }
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
                <a href="live-lessons.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'live-lessons.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-video mr-3"></i><span>Live Lessons & Recordings</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('progress_tracking', $features)): ?>
                <a href="progress-tracking.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'progress-tracking.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-chart-line mr-3"></i><span>Progress Tracking</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('social_forums', $features)): ?>
                <a href="study-groups.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'study-groups.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-users mr-3"></i><span>Social Forums</span>
                </a>
                <?php endif; ?>
                <a href="schedule.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-3"></i><span>Timetable</span>
                </a>
                <a href="settings.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'border-b-2 border-gold' : ''; ?>">
                    <i class="fas fa-cog mr-3"></i><span>Settings</span>
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
                    <h1 class="text-xl font-bold text-navy">Lesson Archive</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button id="notificationButton" class="text-navy relative">
                                <i class="fas fa-bell text-xl"></i>
                                <span id="notificationDot" class="notification-dot hidden"></span>
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

        <!-- Lesson Archive Content -->
        <main class="container mx-auto px-6 py-8">
            <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-navy">Lesson Archive</h2>
                    <a href="live-lessons.php" class="text-gold hover:underline">Back to Live Lessons</a>
                </div>

                <!-- Search Form -->
                <div class="mb-6 p-4 border border-gray-200 rounded-lg">
                    <h3 class="text-lg font-semibold text-navy mb-4">Search Recorded Lessons</h3>
                    <form method="GET" class="space-y-4">
                        <div>
                            <label for="search" class="block text-sm font-medium text-navy">Search by Lesson Name</label>
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search_query); ?>" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-gold" placeholder="e.g., Algebra Introduction">
                        </div>
                        <div>
                            <label for="course_id" class="block text-sm font-medium text-navy">Course</label>
                            <select name="course_id" id="course_id" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo htmlspecialchars($course['course_id']); ?>" <?php echo $course_id == $course['course_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="date" class="block text-sm font-medium text-navy">Date</label>
                            <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($date); ?>" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-gold">
                        </div>
                        <button type="submit" class="bg-navy text-white py-2 px-4 rounded-lg hover:bg-opacity-90 transition">Search</button>
                    </form>
                </div>

                <!-- Search Results -->
                <?php if (empty($search_results) && (!empty($search_query) || !empty($course_id) || !empty($date))): ?>
                    <p class="text-gray-600">No recorded lessons found matching your criteria.</p>
                <?php elseif (empty($search_results)): ?>
                    <p class="text-gray-600">Enter search criteria to find recorded lessons.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($search_results as $index => $lesson): ?>
                            <div class="flex justify-between items-center p-3 border border-gray-200 rounded-lg hover:shadow-md transition">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-video text-gray-800"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-navy"><?php echo htmlspecialchars($lesson['lesson_name']); ?> - <?php echo htmlspecialchars($lesson['course_display']); ?></h3>
                                        <p class="text-sm text-gray-600">
                                            <i class="far fa-clock mr-2"></i><?php echo $lesson['display_date']; ?>, <?php echo $lesson['time']; ?> (SAST)
                                        </p>
                                    </div>
                                </div>
                                <div>
                                    <?php if (!empty($lesson['recording_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($lesson['recording_link']); ?>" class="bg-navy text-white text-sm py-1 px-3 rounded-lg hover:bg-opacity-90 transition" target="_blank">View Recording</a>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-sm">Recording unavailable</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
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
    </script>
</body>
</html>