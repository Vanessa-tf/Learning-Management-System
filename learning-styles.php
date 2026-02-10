<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

// Check if user is logged in and has 'student' role
check_session();
if ($_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle learning style assessment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $learning_style = $_POST['learning_style'] ?? '';
    $confidence_level = $_POST['confidence_level'] ?? 0;
    
    if (in_array($learning_style, ['visual', 'auditory', 'reading_writing', 'kinesthetic'])) {
        try {
            // Check if user already has a learning style recorded
            $stmt = $pdo->prepare("SELECT id FROM student_learning_styles WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $user_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE student_learning_styles SET learning_style = :style, confidence_level = :confidence, updated_at = NOW() WHERE user_id = :user_id");
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO student_learning_styles (user_id, learning_style, confidence_level, created_at) VALUES (:user_id, :style, :confidence, NOW())");
            }
            
            $stmt->execute([
                'user_id' => $user_id,
                'style' => $learning_style,
                'confidence' => $confidence_level
            ]);
            
            $_SESSION['success_message'] = "Learning style updated successfully!";
        } catch (PDOException $e) {
            error_log("Error saving learning style: " . $e->getMessage());
            $_SESSION['error_message'] = "Error saving learning style. Please try again.";
        }
    }
    
    header("Location: learning-styles.php");
    exit;
}

// Fetch user's current learning style
$current_style = null;
$confidence_level = 0;
try {
    $stmt = $pdo->prepare("SELECT learning_style, confidence_level FROM student_learning_styles WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $current_style = $result['learning_style'];
        $confidence_level = $result['confidence_level'];
    }
} catch (PDOException $e) {
    error_log("Error fetching learning style: " . $e->getMessage());
}

// Fetch user details for sidebar
try {
    $stmt = $pdo->prepare("SELECT first_name, package_selected FROM students WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $username = htmlspecialchars($user['first_name']);
    $initials = strtoupper(substr($username, 0, 2));
} catch (PDOException $e) {
    error_log("Error fetching student details: " . $e->getMessage());
}

// Learning style descriptions and recommendations
$learning_styles_info = [
    'visual' => [
        'name' => 'Visual Learner',
        'icon' => 'fas fa-eye',
        'color' => 'text-blue-600',
        'bg_color' => 'bg-blue-100',
        'description' => 'You learn best through visual aids like diagrams, charts, and videos.',
        'recommendations' => [
            'Watch video explanations',
            'Use mind maps and diagrams',
            'Color-code your notes',
            'Utilize flashcards with images'
        ]
    ],
    'auditory' => [
        'name' => 'Auditory Learner',
        'icon' => 'fas fa-assistive-listening-systems',
        'color' => 'text-green-600',
        'bg_color' => 'bg-green-100',
        'description' => 'You learn best through listening and verbal explanations.',
        'recommendations' => [
            'Listen to recorded lectures',
            'Participate in group discussions',
            'Use text-to-speech for reading',
            'Explain concepts aloud to yourself'
        ]
    ],
    'reading_writing' => [
        'name' => 'Reading/Writing Learner',
        'icon' => 'fas fa-book',
        'color' => 'text-purple-600',
        'bg_color' => 'bg-purple-100',
        'description' => 'You learn best through reading and writing activities.',
        'recommendations' => [
            'Take detailed notes',
            'Rewrite information in your own words',
            'Create summaries and outlines',
            'Read textbooks and articles'
        ]
    ],
    'kinesthetic' => [
        'name' => 'Kinesthetic Learner',
        'icon' => 'fas fa-hands',
        'color' => 'text-orange-600',
        'bg_color' => 'bg-orange-100',
        'description' => 'You learn best through hands-on activities and physical experiences.',
        'recommendations' => [
            'Use interactive simulations',
            'Take frequent breaks to move around',
            'Create physical models or demonstrations',
            'Apply concepts through practical exercises'
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Styles - NovaTech FET College</title>
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
        .learning-style-card { transition: all 0.3s ease; }
        .learning-style-card:hover { transform: translateY(-5px); }
        .learning-style-card.selected { border-color: var(--gold); border-width: 2px; }
        .sidebar { transition: all 0.3s ease; }
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
                        <p class="text-gold text-sm">Student</p>
                    </div>
                </div>
            </div>
            <nav class="space-y-2">
                <a href="student-dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-home mr-3"></i><span>Dashboard</span>
                </a>
                <a href="learning-styles.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white border-b-2 border-gold">
                    <i class="fas fa-brain mr-3"></i><span>Learning Style</span>
                </a>
                <a href="my-courses.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-book-open mr-3"></i><span>My Subjects</span>
                </a>
                <a href="past-papers.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-file-alt mr-3"></i><span>Past Papers</span>
                </a>
                <a href="live-lessons.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-video mr-3"></i><span>Live Lessons</span>
                </a>
                <a href="progress-tracking.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-chart-line mr-3"></i><span>Progress Tracking</span>
                </a>
                <a href="study-groups.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-users mr-3"></i><span>Social Chatroom</span>
                </a>
                <a href="schedule.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-calendar-alt mr-3"></i><span>Timetable</span>
                </a>
                <a href="settings.php" class="flex items-center p-3 rounded-lg hover:bg-white hover:bg-opacity-10 transition text-white">
                    <i class="fas fa-cog mr-3"></i><span>My Profile</span>
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
                    <h1 class="text-xl font-bold text-navy">Learning Styles Assessment</h1>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-gold rounded-full flex items-center justify-center mr-2">
                                <span class="text-navy font-bold text-sm"><?php echo $initials; ?></span>
                            </div>
                            <span class="text-navy"><?php echo $username; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Learning Styles Content -->
        <main class="container mx-auto px-6 py-8">
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Assessment Section -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <h2 class="text-2xl font-bold text-navy mb-4">Discover Your Learning Style</h2>
                        <p class="text-gray-600 mb-6">Understanding your learning style helps us personalize your educational experience for better results.</p>
                        
                        <form id="learningStyleForm" method="POST">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <?php foreach ($learning_styles_info as $key => $style): ?>
                                <div class="learning-style-card border-2 border-gray-200 rounded-lg p-4 cursor-pointer <?php echo $current_style === $key ? 'selected border-gold' : ''; ?>" 
                                     data-style="<?php echo $key; ?>">
                                    <div class="flex items-center mb-3">
                                        <div class="w-12 h-12 <?php echo $style['bg_color']; ?> rounded-lg flex items-center justify-center mr-3">
                                            <i class="<?php echo $style['icon']; ?> <?php echo $style['color']; ?> text-lg"></i>
                                        </div>
                                        <h3 class="font-semibold text-navy"><?php echo $style['name']; ?></h3>
                                    </div>
                                    <p class="text-sm text-gray-600"><?php echo $style['description']; ?></p>
                                    <div class="mt-3">
                                        <?php foreach ($style['recommendations'] as $rec): ?>
                                        <div class="flex items-center text-xs text-gray-500 mb-1">
                                            <i class="fas fa-check text-gold mr-2"></i>
                                            <span><?php echo $rec; ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" name="learning_style" id="selectedStyle" value="<?php echo $current_style; ?>">
                            
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-navy mb-2">How confident are you about this learning style?</label>
                                <div class="flex items-center space-x-4">
                                    <span class="text-sm text-gray-600">Not sure</span>
                                    <input type="range" name="confidence_level" id="confidenceLevel" 
                                           min="0" max="100" value="<?php echo $confidence_level; ?>" 
                                           class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                    <span class="text-sm text-gray-600">Very confident</span>
                                </div>
                                <div class="text-center mt-2">
                                    <span id="confidenceValue" class="text-gold font-semibold"><?php echo $confidence_level; ?>%</span>
                                </div>
                            </div>
                            
                            <button type="submit" class="bg-gold text-navy font-bold py-3 px-6 rounded-lg hover:bg-yellow-500 transition w-full">
                                Save Learning Style
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Current Style Info -->
                <div>
                    <?php if ($current_style): ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 sticky top-8">
                        <h3 class="text-xl font-bold text-navy mb-4">Your Learning Style</h3>
                        <div class="text-center mb-6">
                            <div class="w-20 h-20 <?php echo $learning_styles_info[$current_style]['bg_color']; ?> rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="<?php echo $learning_styles_info[$current_style]['icon']; ?> <?php echo $learning_styles_info[$current_style]['color']; ?> text-3xl"></i>
                            </div>
                            <h4 class="font-bold text-navy text-lg"><?php echo $learning_styles_info[$current_style]['name']; ?></h4>
                            <p class="text-gray-600 text-sm mt-2">Confidence: <?php echo $confidence_level; ?>%</p>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h5 class="font-semibold text-navy mb-2">Personalized Tips:</h5>
                            <ul class="text-sm text-gray-600 space-y-2">
                                <?php foreach ($learning_styles_info[$current_style]['recommendations'] as $tip): ?>
                                <li class="flex items-start">
                                    <i class="fas fa-lightbulb text-gold mr-2 mt-1"></i>
                                    <span><?php echo $tip; ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 text-center">
                        <i class="fas fa-graduation-cap text-gray-300 text-6xl mb-4"></i>
                        <h3 class="text-lg font-semibold text-navy mb-2">No Learning Style Set</h3>
                        <p class="text-gray-600 text-sm">Complete the assessment to get personalized learning recommendations.</p>
                    </div>
                    <?php endif; ?>
                </div>
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

        // Learning style selection
        document.querySelectorAll('.learning-style-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.learning-style-card').forEach(c => {
                    c.classList.remove('selected', 'border-gold');
                    c.classList.add('border-gray-200');
                });
                
                this.classList.add('selected', 'border-gold');
                this.classList.remove('border-gray-200');
                
                document.getElementById('selectedStyle').value = this.dataset.style;
            });
        });

        // Update confidence level display
        const confidenceSlider = document.getElementById('confidenceLevel');
        const confidenceValue = document.getElementById('confidenceValue');
        
        confidenceSlider.addEventListener('input', function() {
            confidenceValue.textContent = this.value + '%';
        });
    </script>
</body>
</html>