<?php
// live_lessons_cont_dev.php - Fixed to use courses table properly
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'models/LiveLessons.php';
require_once 'models/Course.php';


requireRole('content');

$liveLesson = new LiveLesson();
$course = new Course();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $data = [
                'course_id' => (int)$_POST['course_id'],
                'lesson_name' => trim($_POST['lesson_name']),
                'date' => $_POST['date'],
                'start_time' => $_POST['start_time'],
                'end_time' => $_POST['end_time'],
                'meeting_platform' => $_POST['meeting_platform'] ?? 'teams'
            ];
            $result = $liveLesson->create($data);
            $message = $result['success'] ? 'Lesson created successfully!' : $result['error'];
            break;
            
        case 'upload_recording':
            $lessonId = (int)$_POST['lesson_id'];
            $recordingLink = trim($_POST['recording_link']);
            $result = $liveLesson->updateRecordingLink($lessonId, $recordingLink);
            $message = $result ? 'Recording uploaded successfully!' : 'Failed to upload recording';
            break;
            
        case 'delete':
            $lessonId = (int)$_POST['lesson_id'];
            $result = $liveLesson->delete($lessonId);
            $message = $result['success'] ? 'Lesson deleted successfully!' : $result['error'];
            break;
    }
}

// Get data for display
$upcomingLessons = $liveLesson->getUpcoming();
$pastLessons = $liveLesson->getPast(['limit' => 10]);
$todaysLessons = $liveLesson->getTodaysLessons();

// Get courses from courses table (not course_content)
$all_courses = $course->getAll();

// Calculate statistics
$totalUpcoming = count($upcomingLessons);
$totalPast = count($pastLessons);
$totalRegistrations = 0;
$avgRating = 0;

// User details
$display_name = ($_SESSION['first_name'] ?? 'Content') . ' ' . ($_SESSION['last_name'] ?? 'Developer');
$first_name = $_SESSION['first_name'] ?? 'Content';
$last_name = $_SESSION['last_name'] ?? 'Developer';
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
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
            --purple: #7c3aed;
        }
        body { font-family: 'Poppins', sans-serif; }
        .bg-navy { background-color: var(--navy); }
        .bg-gold { background-color: var(--gold); }
        .bg-beige { background-color: var(--beige); }
        .bg-purple { background-color: var(--purple); }
        .text-navy { color: var(--navy); }
        .lesson-card { 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            border-radius: 1rem;
        }
        .lesson-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); 
        }
        .sidebar { transition: all 0.3s ease; }
        .status-live { animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; z-index: 1000; height: 100vh; }
            .sidebar.active { left: 0; }
            .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); z-index: 999; }
            .overlay.active { display: block; }
        }
    </style>
</head>
<body class="bg-beige">
    <?php if (isset($message)): ?>
    <div id="message" class="fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <script>
        setTimeout(() => {
            document.getElementById('message').style.display = 'none';
        }, 3000);
    </script>
    <?php endif; ?>

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
                        <span class="text-white font-bold"><?php echo $initials; ?></span>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?php echo $display_name; ?></h3>
                        <p class="text-sm mt-1">Content Developer</p>
                    </div>
                </div>
            </div>
            <nav>
                <ul class="space-y-2">
                    <li><a href="content_dashboard.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-tachometer-alt mr-3"></i><span>Dashboard</span></a></li>
                    <li><a href="course_content_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-book mr-3"></i><span>Courses</span></a></li>
                    <li><a href="mock_exams_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-file-alt mr-3"></i><span>Mock Exams</span></a></li>
                    <li><a href="study_materials_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-book-open mr-3"></i><span>Study Materials</span></a></li>
                    <li><a href="past_papers_cont_dev.php" class="flex items-center p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition"><i class="fas fa-file-pdf mr-3"></i><span>Past Papers</span></a></li>
                    <li><a href="live_lessons_cont_dev.php" class="flex items-center p-2 rounded-lg bg-purple text-white"><i class="fas fa-chalkboard-teacher mr-3"></i><span>Live Lessons</span></a></li>
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
                    <h1 class="text-xl font-bold text-navy">Live Lessons Management</h1>
                    <div class="flex items-center space-x-4">
                        <button class="bg-purple text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition" onclick="openCreateModal()">
                            <i class="fas fa-plus mr-2"></i>Schedule Lesson
                        </button>
                        <div class="relative">
    <a href="notifications_cont_dev.php" class="text-navy relative">
        <i class="fas fa-bell"></i>
        <span id="notificationDot" class="absolute top-[-5px] right-[-5px] w-3 h-3 bg-red-500 rounded-full"></span>
    </a>
</div>
                        <div class="hidden md:block">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-purple rounded-full flex items-center justify-center mr-2">
                                    <span class="text-white font-bold text-sm"><?php echo $initials; ?></span>
                                </div>
                                <span class="text-navy"><?php echo explode(' ', $display_name)[0]; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="container mx-auto px-6 py-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-calendar text-blue-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy"><?php echo $totalUpcoming; ?></h3>
                    <p class="text-gray-600">Upcoming</p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy"><?php echo $totalPast; ?></h3>
                    <p class="text-gray-600">Completed</p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-users text-purple-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy"><?php echo count($all_courses); ?></h3>
                    <p class="text-gray-600">Active Courses</p>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-navy"><?php echo count($todaysLessons); ?></h3>
                    <p class="text-gray-600">Today</p>
                </div>
            </div>

            <!-- Today's Lessons Alert -->
            <?php if (!empty($todaysLessons)): ?>
            <div class="bg-orange-100 border-l-4 border-orange-500 p-4 mb-8 rounded-r-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-orange-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-orange-700">
                            <strong>Today's Lessons:</strong> You have <?php echo count($todaysLessons); ?> lesson(s) scheduled for today.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Upcoming Lessons -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <h2 class="text-xl font-bold text-navy mb-6">Scheduled Lessons</h2>
                
                <?php if (empty($upcomingLessons)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-calendar-times text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">No upcoming lessons scheduled</p>
                    <button onclick="openCreateModal()" class="mt-4 bg-purple text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        Schedule Your First Lesson
                    </button>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($upcomingLessons as $lesson): 
                        $lessonDate = new DateTime($lesson['date']);
                        $startTime = new DateTime($lesson['start_time']);
                        $endTime = new DateTime($lesson['end_time']);
                        $today = new DateTime();
                        $isToday = $lessonDate->format('Y-m-d') === $today->format('Y-m-d');
                        $dayDisplay = $isToday ? 'Today' : $lessonDate->format('M d, Y');
                    ?>
                    <div class="border border-gray-200 rounded-lg p-4 lesson-card <?php echo $isToday ? 'border-orange-400 bg-orange-50' : ''; ?>">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex-1">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h3 class="text-lg font-bold text-navy"><?php echo htmlspecialchars($lesson['lesson_name']); ?></h3>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($lesson['course_name']); ?></p>
                                    </div>
                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                                        <?php echo $dayDisplay; ?>
                                        <?php if ($isToday): ?>
                                        <span class="status-live ml-1">🔴 Today</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <div class="text-xs text-gray-500">Start Time</div>
                                        <div class="font-semibold text-navy"><?php echo $startTime->format('h:i A'); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">End Time</div>
                                        <div class="font-semibold text-navy"><?php echo $endTime->format('h:i A'); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Platform</div>
                                        <div class="font-semibold text-navy">
                                            <?php if (strpos($lesson['link'], 'teams.microsoft.com') !== false): ?>
                                                <i class="fab fa-microsoft text-blue-600"></i> Teams
                                            <?php elseif (strpos($lesson['link'], 'zoom.us') !== false): ?>
                                                <i class="fas fa-video text-blue-600"></i> Zoom
                                            <?php else: ?>
                                                <i class="fab fa-google text-green-600"></i> Meet
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <div class="text-xs text-gray-500 mb-1">Meeting Link</div>
                                    <div class="flex items-center justify-between">
                                        <code class="text-sm bg-white px-2 py-1 rounded border flex-1 mr-2 truncate">
                                            <?php echo htmlspecialchars($lesson['link']); ?>
                                        </code>
                                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($lesson['link']); ?>')" 
                                                class="bg-gray-200 text-gray-700 px-3 py-1 rounded hover:bg-gray-300 transition text-sm">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 lg:mt-0 lg:ml-6 flex flex-col space-y-2">
                                <a href="<?php echo htmlspecialchars($lesson['link']); ?>" target="_blank" 
                                   class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm text-center">
                                    <i class="fas fa-external-link-alt mr-1"></i> Join
                                </a>
                                <button onclick="deleteLesson(<?php echo $lesson['id']; ?>)" 
                                        class="bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition text-sm">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Past Lessons -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-navy mb-6">Past Lessons & Recordings</h2>
                
                <?php if (empty($pastLessons)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-history text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">No past lessons yet</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($pastLessons as $lesson): 
                        $lessonDate = new DateTime($lesson['date']);
                    ?>
                    <div class="border border-gray-200 rounded-lg p-4 lesson-card">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h3 class="text-lg font-bold text-navy"><?php echo htmlspecialchars($lesson['lesson_name']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($lesson['course_name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $lessonDate->format('M d, Y'); ?></p>
                            </div>
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">Completed</span>
                        </div>
                        
                        <?php if ($lesson['recording_link']): ?>
                        <div class="mt-3">
                            <a href="<?php echo htmlspecialchars($lesson['recording_link']); ?>" target="_blank" 
                               class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
                                <i class="fas fa-play mr-1"></i> Watch Recording
                            </a>
                        </div>
                        <?php else: ?>
                        <button onclick="openUploadModal(<?php echo $lesson['id']; ?>)" 
                                class="mt-3 bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition text-sm">
                            <i class="fas fa-upload mr-1"></i> Upload Recording
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Lesson Modal -->
    <div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-lg mx-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-navy">Schedule New Lesson</h2>
                <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Course *</label>
                    <select name="course_id" required class="w-full p-2 border border-gray-300 rounded-lg">
                        <option value="">Select a Course</option>
                        <?php foreach ($all_courses as $courseItem): ?>
                        <option value="<?php echo $courseItem['id']; ?>"><?php echo htmlspecialchars($courseItem['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lesson Title *</label>
                    <input type="text" name="lesson_name" required class="w-full p-2 border border-gray-300 rounded-lg" placeholder="e.g., Quadratic Equations Masterclass">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>" class="w-full p-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
                        <select name="meeting_platform" class="w-full p-2 border border-gray-300 rounded-lg">
                            <option value="teams">Teams</option>
                            <option value="zoom">Zoom</option>
                            <option value="meet">Meet</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                        <input type="time" name="start_time" required class="w-full p-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                        <input type="time" name="end_time" required class="w-full p-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                
                <div class="flex space-x-2 pt-4">
                    <button type="button" onclick="closeCreateModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-purple text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition">Schedule Lesson</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload Recording Modal -->
    <div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-lg mx-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-navy">Upload Recording</h2>
                <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="upload_recording">
                <input type="hidden" name="lesson_id" id="upload_lesson_id">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recording Link *</label>
                    <input type="url" name="recording_link" required class="w-full p-2 border border-gray-300 rounded-lg" placeholder="https://...">
                    <p class="text-xs text-gray-500 mt-1">Enter URL where recording is hosted</p>
                </div>
                
                <div class="flex space-x-2 pt-4">
                    <button type="button" onclick="closeUploadModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" class="flex-1 bg-purple text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition">Upload</button>
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
            
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }

        function openCreateModal() {
            document.getElementById('createModal').classList.remove('hidden');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
        }

        function openUploadModal(lessonId) {
            document.getElementById('upload_lesson_id').value = lessonId;
            document.getElementById('uploadModal').classList.remove('hidden');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
        }

        function deleteLesson(lessonId) {
            if (confirm('Delete this lesson?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="lesson_id" value="${lessonId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const button = event.target.closest('button');
                const originalContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check text-green-600"></i>';
                setTimeout(() => {
                    button.innerHTML = originalContent;
                }, 2000);
            });
        }
    </script>
</body>
</html>