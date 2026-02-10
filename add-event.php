<?php
session_start();
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

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
    'Basic' => [
        'subjects' => 1,
        'features' => ['courses', 'past_papers']
    ],
    'Standard' => [
        'subjects' => 2,
        'features' => ['courses', 'past_papers', 'live_lessons', 'progress_tracking']
    ],
    'Premium' => [
        'subjects' => 4,
        'features' => ['courses', 'past_papers', 'live_lessons', 'progress_tracking', 'mock_exams', 'social_forums']
    ]
];
$package = $user['package_selected'] ?? 'Basic';
$max_subjects = $package_features[$package]['subjects'] ?? 1;
$features = $package_features[$package]['features'] ?? ['courses', 'past_papers'];

// Fetch enrolled courses for the form
$courses = [];
try {
    $stmt = $pdo->prepare("SELECT course_id, course_name FROM enrollments WHERE user_id = :user_id LIMIT :max_subjects");
    $stmt->bindValue('user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue('max_subjects', $max_subjects, PDO::PARAM_INT);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching enrollments for user $user_id: " . $e->getMessage());
    $courses = [];
}

// Handle form submission
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $_POST['course_id'] ?? '';
    $day_of_week = $_POST['day_of_week'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $event_type = $_POST['event_type'] ?? '';
    $teams_link = $_POST['teams_link'] ?? '';

    // Validate inputs
    if (empty($course_id) || !in_array($course_id, array_column($courses, 'course_id'))) {
        $errors[] = "Please select a valid enrolled course.";
    }
    if (!in_array($day_of_week, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])) {
        $errors[] = "Please select a valid day of the week.";
    }
    if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $start_time)) {
        $errors[] = "Please enter a valid start time (HH:MM).";
    }
    if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $end_time)) {
        $errors[] = "Please enter a valid end time (HH:MM).";
    }
    if (strtotime($end_time) <= strtotime($start_time)) {
        $errors[] = "End time must be after start time.";
    }
    if (!in_array($event_type, ['lecture', 'tutorial', 'lab', 'exam', 'other'])) {
        $errors[] = "Please select a valid event type.";
    }
    if (!empty($teams_link) && !filter_var($teams_link, FILTER_VALIDATE_URL)) {
        $errors[] = "Please enter a valid Teams link or leave it blank.";
    }

    // Get course_name for the selected course_id
    $course_name = '';
    foreach ($courses as $course) {
        if ($course['course_id'] == $course_id) {
            $course_name = $course['course_name'];
            break;
        }
    }

    // Insert event if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO custom_events (user_id, course_id, course_name, day_of_week, start_time, end_time, event_type, teams_link)
                VALUES (:user_id, :course_id, :course_name, :day_of_week, :start_time, :end_time, :event_type, :teams_link)
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'course_id' => $course_id,
                'course_name' => $course_name,
                'day_of_week' => $day_of_week,
                'start_time' => $start_time . ':00',
                'end_time' => $end_time . ':00',
                'event_type' => $event_type,
                'teams_link' => $teams_link ?: null
            ]);
            $success = "Event added successfully!";
            header("Location: schedule.php");
            exit;
        } catch (PDOException $e) {
            error_log("Error adding event for user $user_id: " . $e->getMessage());
            $errors[] = "An error occurred while adding the event. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Event - NovaTech FET College</title>
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
                <a href="student-dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-home mr-3"></i><span>Dashboard</span>
                </a>
                <?php if (in_array('courses', $features)): ?>
                <a href="my-courses.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-book-open mr-3"></i><span>My Subjects</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('past_papers', $features)): ?>
                <a href="past-papers.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-file-alt mr-3"></i><span>Past Papers</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('live_lessons', $features)): ?>
                <a href="live-lessons.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-video mr-3"></i><span>Live Lessons</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('progress_tracking', $features)): ?>
                <a href="progress-tracking.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-chart-line mr-3"></i><span>Progress Tracking</span>
                </a>
                <?php endif; ?>
                <?php if (in_array('social_forums', $features)): ?>
                <a href="study-groups.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-users mr-3"></i><span>Social Forums</span>
                </a>
                <?php endif; ?>
                <a href="schedule.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white border-b-2 border-gold">
                    <i class="fas fa-calendar-alt mr-3"></i><span>Timetable</span>
                </a>
                <a href="settings.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
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
                    <button class="text-navy md:hidden" id="menuButton"><i class="fas fa-bars text-xl"></i></button>
                    <h1 class="text-xl font-bold text-navy">Add Event</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button id="notificationButton" class="text-navy relative">
                                <i class="fas fa-bell text-xl"></i>
                                <span id="notificationDot" class="notification-dot hidden"></span>
                            </button>
                            <!-- Notification Dropdown -->
                            <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto">
                                <div class="p-4 border-b">
                                    <h3 class="text-lg font-semibold text-navy">Notifications</h3>
                                </div>
                                <div id="notificationList" class="divide-y">
                                    <!-- Notifications will be injected here -->
                                </div>
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

        <!-- Add Event Form -->
        <main class="container mx-auto px-6 py-4">
            <div class="bg-white rounded-xl shadow-lg p-6 dashboard-card">
                <h2 class="text-lg font-bold text-navy mb-4">Add New Event</h2>
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
                        <ul class="list-disc pl-5">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="bg-green-100 text-green-700 p-4 rounded mb-4">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($courses)): ?>
                    <p class="text-gray-600 text-sm">You are not enrolled in any courses. <a href="enroll.php" class="text-gold hover:underline">Enroll now</a> to add events.</p>
                <?php else: ?>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="course_id" class="block text-sm font-medium text-navy">Course</label>
                            <select name="course_id" id="course_id" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-gold focus:border-gold">
                                <option value="">Select a course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['course_id']; ?>" <?php echo isset($_POST['course_id']) && $_POST['course_id'] == $course['course_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                <?php endforeachទ: endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="day_of_week" class="block text-sm font-medium text-navy">Day of Week</label>
                            <select name="day_of_week" id="day_of_week" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-gold focus:border-gold">
                                <option value="">Select a day</option>
                                <?php
                                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                foreach ($days as $day): ?>
                                    <option value="<?php echo $day; ?>" <?php echo isset($_POST['day_of_week']) && $_POST['day_of_week'] == $day ? 'selected' : ''; ?>>
                                        <?php echo $day; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="start_time" class="block text-sm font-medium text-navy">Start Time (HH:MM)</label>
                            <input type="time" name="start_time" id="start_time" value="<?php echo isset($_POST['start_time']) ? htmlspecialchars($_POST['start_time']) : ''; ?>" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-gold focus:border-gold" required>
                        </div>
                        <div>
                            <label for="end_time" class="block text-sm font-medium text-navy">End Time (HH:MM)</label>
                            <input type="time" name="end_time" id="end_time" value="<?php echo isset($_POST['end_time']) ? htmlspecialchars($_POST['end_time']) : ''; ?>" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-gold focus:border-gold" required>
                        </div>
                        <div>
                            <label for="event_type" class="block text-sm font-medium text-navy">Event Type</label>
                            <select name="event_type" id="event_type" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-gold focus:border-gold">
                                <option value="">Select event type</option>
                                <?php
                                $event_types = ['lecture', 'tutorial', 'lab', 'exam', 'other'];
                                foreach ($event_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo isset($_POST['event_type']) && $_POST['event_type'] == $type ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="teams_link" class="block text-sm font-medium text-navy">Teams Link (Optional)</label>
                            <input type="url" name="teams_link" id="teams_link" value="<?php echo isset($_POST['teams_link']) ? htmlspecialchars($_POST['teams_link']) : ''; ?>" class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-gold focus:border-gold" placeholder="https://teams.microsoft.com/l/meetup-join/...">
                        </div>
                        <div class="flex justify-end space-x-2">
                            <a href="schedule.php" class="px-4 py-2 bg-gray-300 text-navy rounded-md hover:bg-gray-400">Cancel</a>
                            <button type="submit" class="px-4 py-2 bg-gold text-navy rounded-md hover:bg-yellow-400">Add Event</button>
                        </div>
                    </form>
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

        // Toggle dropdown
        notificationButton.addEventListener('click', () => {
            notificationDropdown.classList.toggle('hidden');
            if (!notificationDropdown.classList.contains('hidden')) {
                markNotificationsAsRead();
            }
        });

        // Fetch notifications
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
                                        <p class="text-xs text-gray-500">${new Date(notification.created_at).toLocaleString()}</p>
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

        // Map notification types to icons
        function getNotificationIcon(type) {
            const icons = {
                general: 'bell',
                assignment: 'file-alt',
                live_lesson: 'video',
                exam: 'clipboard-check'
            };
            return icons[type] || 'bell';
        }

        // Map notification types to colors
        function getNotificationColor(type) {
            const colors = {
                general: 'gray-600',
                assignment: 'blue-600',
                live_lesson: 'green-600',
                exam: 'purple-600'
            };
            return colors[type] || 'gray-600';
        }

        // Mark notifications as read
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

        // Poll for notifications every 30 seconds
        fetchNotifications();
        setInterval(fetchNotifications, 30000);
    </script>
</body>
</html>