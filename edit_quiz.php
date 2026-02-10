<?php
// edit_quiz.php - Enhanced with quiz availability dates and TIMER (FULLY IMPLEMENTED)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'models/Course.php';

requireRole('content');

$course = new Course();
$flash = null;

// Get content ID from URL
$content_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$content_id) {
    header('Location: course_content_dev.php');
    exit;
}

// Get the content item
$content = $course->getContentByIdAndType($content_id, 'quiz');

if (!$content) {
    header('Location: course_content_dev.php');
    exit;
}

// Parse quiz content
$questions = [];
if (!empty($content['quiz_content'])) {
    $quiz_data = json_decode($content['quiz_content'], true);
    if ($quiz_data && is_array($quiz_data)) {
        $questions = $quiz_data;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_quiz') {
        // Build questions array from POST data
        $new_questions = [];
        
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $q) {
                if (!empty($q['question'])) {
                    $question_data = [
                        'question' => trim($q['question']),
                        'type' => $q['type'] ?? 'multiple_choice',
                        'marks' => (int)($q['marks'] ?? 1)
                    ];
                    
                    // Handle multiple choice questions
                    if ($question_data['type'] === 'multiple_choice') {
                        $question_data['options'] = [];
                        if (isset($q['options']) && is_array($q['options'])) {
                            foreach ($q['options'] as $option) {
                                if (!empty(trim($option))) {
                                    $question_data['options'][] = trim($option);
                                }
                            }
                        }
                        
                        $correct_index = isset($q['correct_answer']) ? (int)$q['correct_answer'] : 0;
                        if (isset($question_data['options'][$correct_index])) {
                            $question_data['correct_answer'] = $question_data['options'][$correct_index];
                        } else {
                            $question_data['correct_answer'] = $question_data['options'][0] ?? '';
                        }
                    } 
                    // Handle true/false questions
                    elseif ($question_data['type'] === 'true_false') {
                        $question_data['options'] = ['True', 'False'];
                        $question_data['correct_answer'] = $q['correct_answer'] ?? 'True';
                    }
                    // Handle short answer questions
                    else {
                        $question_data['options'] = [];
                        $question_data['correct_answer'] = 'TEACHER_GRADED';
                    }
                    
                    $new_questions[] = $question_data;
                }
            }
        }
        
        // Process availability dates - convert to MySQL datetime format
        $open_date = null;
        $close_date = null;
        
        if (!empty($_POST['open_date'])) {
            $open_date = date('Y-m-d H:i:s', strtotime($_POST['open_date']));
        }
        
        if (!empty($_POST['close_date'])) {
            $close_date = date('Y-m-d H:i:s', strtotime($_POST['close_date']));
        }
        
        // Get duration (timer)
        $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
        
        // Validate dates
        if ($open_date && $close_date && strtotime($close_date) <= strtotime($open_date)) {
            $flash = ['text' => 'Close date must be after open date!', 'type' => 'error'];
        } else {
            // Prepare quiz settings JSON
            $quiz_settings = json_encode([
                'duration' => $duration,
                'lockdown_enabled' => true
            ]);
            
            // Update the quiz content
            $updateData = [
                'title' => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'url' => trim($_POST['url'] ?? ''),
                'order_index' => (int)($_POST['order_index'] ?? 0),
                'quiz_content' => json_encode($new_questions),
                'quiz_settings' => $quiz_settings,
                'open_date' => $open_date,
                'close_date' => $close_date
            ];
            
            $result = $course->updateContent($content_id, $updateData);
            
            if ($result['success']) {
                $flash = ['text' => 'Quiz updated successfully!', 'type' => 'success'];
                // Refresh content
                $content = $course->getContentByIdAndType($content_id, 'quiz');
                $questions = $new_questions;
            } else {
                $flash = ['text' => $result['error'] ?? 'Unknown error', 'type' => 'error'];
            }
        }
    }
}

// Fetch user details from content_developers table
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM content_developers WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $display_name = $user['first_name'] . ' ' . $user['last_name'];
        $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
    } else {
        $display_name = 'Content Developer';
        $initials = 'CD';
    }
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    $display_name = 'Content Developer';
    $initials = 'CD';
}

// Calculate total marks
$total_marks = 0;
foreach ($questions as $q) {
    $total_marks += ($q['marks'] ?? 1);
}

// Get duration from quiz_settings
$quiz_duration = 0;
if (!empty($content['quiz_settings'])) {
    $settings = json_decode($content['quiz_settings'], true);
    $quiz_duration = $settings['duration'] ?? 0;
}

// Format dates for datetime-local input (HTML5 format: YYYY-MM-DDTHH:MM)
$open_date_value = '';
$close_date_value = '';

if (!empty($content['open_date'])) {
    $open_date_value = date('Y-m-d\TH:i', strtotime($content['open_date']));
}

if (!empty($content['close_date'])) {
    $close_date_value = date('Y-m-d\TH:i', strtotime($content['close_date']));
}

// Get current server time for comparison
$current_time = time();
$open_timestamp = !empty($content['open_date']) ? strtotime($content['open_date']) : null;
$close_timestamp = !empty($content['close_date']) ? strtotime($content['close_date']) : null;

// Determine quiz status
$quiz_status = 'available';
$status_message = '';

if ($open_timestamp && $current_time < $open_timestamp) {
    $quiz_status = 'not_yet_open';
    $status_message = 'Quiz will open on ' . date('M j, Y \a\t g:i A', $open_timestamp);
} elseif ($close_timestamp && $current_time > $close_timestamp) {
    $quiz_status = 'closed';
    $status_message = 'Quiz closed on ' . date('M j, Y \a\t g:i A', $close_timestamp);
} elseif ($open_timestamp && $close_timestamp) {
    $status_message = 'Available from ' . date('M j, Y \a\t g:i A', $open_timestamp) . ' to ' . date('M j, Y \a\t g:i A', $close_timestamp);
} elseif ($open_timestamp) {
    $status_message = 'Available starting ' . date('M j, Y \a\t g:i A', $open_timestamp);
} elseif ($close_timestamp) {
    $status_message = 'Available until ' . date('M j, Y \a\t g:i A', $close_timestamp);
} else {
    $status_message = 'Always available';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz - <?php echo htmlspecialchars($content['title'] ?? 'Quiz'); ?></title>
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
        .bg-purple { background-color: var(--purple); }
        .bg-beige { background-color: var(--beige); }
        .text-navy { color: var(--navy); }
        .question-card { 
            transition: all 0.3s ease;
        }
        .question-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .badge-mcq {
            background-color: #3b82f6;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
        }
        .badge-short {
            background-color: #10b981;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
        }
        .badge-tf {
            background-color: #f59e0b;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-available {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-closed {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body class="bg-beige">
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="course_content_dev.php" class="text-gray-600 hover:text-navy">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Courses
                    </a>
                    <div class="border-l border-gray-300 h-6"></div>
                    <h1 class="text-xl font-bold text-navy">Edit Quiz</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-600">
                        <span class="font-semibold">Total Marks:</span> <?php echo $total_marks; ?>
                    </div>
                    <?php if ($quiz_duration > 0): ?>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-clock mr-1"></i>
                        <span class="font-semibold"><?php echo $quiz_duration; ?> min</span>
                    </div>
                    <?php endif; ?>
                    <div class="w-8 h-8 bg-purple rounded-full flex items-center justify-center">
                        <span class="text-white font-bold text-sm"><?php echo htmlspecialchars($initials); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-8">
        <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $flash['type'] === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
            <i class="fas <?php echo $flash['type'] === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> mr-2"></i>
            <?php echo htmlspecialchars($flash['text']); ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="quizForm">
            <input type="hidden" name="action" value="save_quiz">
            
            <!-- Quiz Info -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-question-circle text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-navy">Quiz Information</h2>
                        <p class="text-sm text-gray-600">Course: <?php echo htmlspecialchars($content['course_name'] ?? 'Unknown'); ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quiz Title *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($content['title'] ?? ''); ?>" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Order Index</label>
                        <input type="number" name="order_index" value="<?php echo $content['order_index'] ?? 0; ?>" min="1" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-clock text-blue-600 mr-1"></i>Duration (Minutes) *
                        </label>
                        <input type="number" name="duration" id="duration" value="<?php echo $quiz_duration; ?>" min="1" max="180" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" required>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>Quiz will auto-submit when time expires
                        </p>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL (Optional)</label>
                        <input type="url" name="url" value="<?php echo htmlspecialchars($content['url'] ?? ''); ?>" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="2" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"><?php echo htmlspecialchars($content['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Quiz Availability Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-calendar-alt text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-navy">Quiz Availability</h2>
                        <p class="text-sm text-gray-600">Set when students can access this quiz</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-plus text-green-600 mr-2"></i>Open Date & Time (Optional)
                        </label>
                        <input type="datetime-local" 
                               name="open_date" 
                               id="open_date"
                               value="<?php echo $open_date_value; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>Leave empty for immediate availability
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-times text-red-600 mr-2"></i>Close Date & Time (Optional)
                        </label>
                        <input type="datetime-local" 
                               name="close_date" 
                               id="close_date"
                               value="<?php echo $close_date_value; ?>"
                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>Leave empty for no expiration
                        </p>
                    </div>
                </div>

                <!-- Current Status -->
                <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-info-circle mr-2"></i>Current Quiz Status:
                    </h3>
                    <div class="mb-3">
                        <?php if ($quiz_status === 'available'): ?>
                            <span class="status-badge status-available">
                                <i class="fas fa-check-circle mr-2"></i>Currently Available to Students
                            </span>
                        <?php elseif ($quiz_status === 'not_yet_open'): ?>
                            <span class="status-badge status-pending">
                                <i class="fas fa-clock mr-2"></i>Not Yet Available
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-closed">
                                <i class="fas fa-lock mr-2"></i>Closed
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-calendar-alt mr-2"></i><?php echo htmlspecialchars($status_message); ?>
                    </p>
                </div>

                <!-- Student View Preview -->
                <div class="mt-4 p-4 bg-blue-50 rounded-lg border-2 border-blue-200">
                    <h3 class="text-sm font-semibold text-blue-900 mb-3">
                        <i class="fas fa-eye mr-2"></i>Student View Preview:
                    </h3>
                    <div id="availabilityPreview" class="text-sm">
                        <!-- Filled by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Questions Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-navy">Questions</h2>
                        <p class="text-sm text-gray-600 mt-1">
                            <span class="badge-mcq"><i class="fas fa-check-circle mr-1"></i>Multiple Choice: Auto-graded</span>
                            <span class="badge-tf ml-2"><i class="fas fa-toggle-on mr-1"></i>True/False: Auto-graded</span>
                            <span class="badge-short ml-2"><i class="fas fa-edit mr-1"></i>Short Answer: Teacher graded</span>
                        </p>
                    </div>
                    <button type="button" onclick="addQuestion()" class="bg-purple text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-plus mr-2"></i>Add Question
                    </button>
                </div>

                <div id="questionsContainer" class="space-y-4">
                    <?php if (empty($questions)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-question-circle text-4xl mb-3 opacity-50"></i>
                        <p>No questions yet. Click "Add Question" to get started.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($questions as $index => $q): ?>
                    <div class="question-card border-2 border-gray-200 rounded-lg p-4 bg-gray-50" data-question-index="<?php echo $index; ?>">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center space-x-2">
                                <h3 class="font-semibold text-navy">Question <?php echo $index + 1; ?></h3>
                                <?php if (($q['type'] ?? 'multiple_choice') === 'multiple_choice'): ?>
                                <span class="badge-mcq"><i class="fas fa-check-circle mr-1"></i>MCQ</span>
                                <?php elseif (($q['type'] ?? '') === 'true_false'): ?>
                                <span class="badge-tf"><i class="fas fa-toggle-on mr-1"></i>T/F</span>
                                <?php else: ?>
                                <span class="badge-short"><i class="fas fa-edit mr-1"></i>Short Answer</span>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="removeQuestion(this)" class="text-red-600 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Question Type *</label>
                                    <select name="questions[<?php echo $index; ?>][type]" class="w-full p-2 border border-gray-300 rounded-lg question-type-select" onchange="toggleOptionsSection(this)">
                                        <option value="multiple_choice" <?php echo ($q['type'] ?? 'multiple_choice') === 'multiple_choice' ? 'selected' : ''; ?>>Multiple Choice (Auto-graded)</option>
                                        <option value="true_false" <?php echo ($q['type'] ?? '') === 'true_false' ? 'selected' : ''; ?>>True/False (Auto-graded)</option>
                                        <option value="short_answer" <?php echo ($q['type'] ?? '') === 'short_answer' ? 'selected' : ''; ?>>Short Answer (Teacher graded)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Marks *</label>
                                    <input type="number" name="questions[<?php echo $index; ?>][marks]" value="<?php echo $q['marks'] ?? 1; ?>" min="1" class="w-full p-2 border border-gray-300 rounded-lg" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Question Text *</label>
                                <textarea name="questions[<?php echo $index; ?>][question]" rows="2" class="w-full p-2 border border-gray-300 rounded-lg" required><?php echo htmlspecialchars($q['question'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Multiple Choice Options -->
                            <div class="options-section-mcq <?php echo ($q['type'] ?? 'multiple_choice') === 'multiple_choice' ? '' : 'hidden'; ?>">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Options *</label>
                                <div class="space-y-2">
                                    <?php 
                                    $options = $q['options'] ?? ['', '', '', ''];
                                    $correct_index = 0;
                                    if (isset($q['correct_answer'])) {
                                        $correct_index = array_search($q['correct_answer'], $options);
                                        if ($correct_index === false) $correct_index = 0;
                                    }
                                    for ($i = 0; $i < 4; $i++): 
                                    ?>
                                    <input type="text" name="questions[<?php echo $index; ?>][options][]" value="<?php echo htmlspecialchars($options[$i] ?? ''); ?>" placeholder="Option <?php echo $i + 1; ?>" class="w-full p-2 border border-gray-300 rounded-lg">
                                    <?php endfor; ?>
                                </div>
                                
                                <div class="mt-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer (Option Number) *</label>
                                    <select name="questions[<?php echo $index; ?>][correct_answer]" class="w-full p-2 border border-gray-300 rounded-lg">
                                        <option value="0" <?php echo $correct_index == 0 ? 'selected' : ''; ?>>Option 1</option>
                                        <option value="1" <?php echo $correct_index == 1 ? 'selected' : ''; ?>>Option 2</option>
                                        <option value="2" <?php echo $correct_index == 2 ? 'selected' : ''; ?>>Option 3</option>
                                        <option value="3" <?php echo $correct_index == 3 ? 'selected' : ''; ?>>Option 4</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- True/False Options -->
                            <div class="options-section-tf <?php echo ($q['type'] ?? '') === 'true_false' ? '' : 'hidden'; ?>">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer *</label>
                                <select name="questions[<?php echo $index; ?>][correct_answer]" class="w-full p-2 border border-gray-300 rounded-lg">
                                    <option value="True" <?php echo ($q['correct_answer'] ?? '') === 'True' ? 'selected' : ''; ?>>True</option>
                                    <option value="False" <?php echo ($q['correct_answer'] ?? '') === 'False' ? 'selected' : ''; ?>>False</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-4">
                <a href="course_content_dev.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="bg-purple text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-save mr-2"></i>Save Quiz
                </button>
            </div>
        </form>
    </main>

    <script>
        let questionIndex = <?php echo count($questions); ?>;

        // Update Student View Preview
        function updateAvailabilityPreview() {
            const openInput = document.getElementById('open_date').value;
            const closeInput = document.getElementById('close_date').value;
            const durationInput = document.getElementById('duration').value;
            const preview = document.getElementById('availabilityPreview');
            const now = new Date();

            let html = '';

            if (!openInput && !closeInput) {
                html = `
                    <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center px-4 py-2 rounded-lg bg-green-100 text-green-800 font-medium">
                            <i class="fas fa-check-circle mr-2"></i>Always Available
                        </span>
                    </div>
                    <p class="text-xs text-gray-600 mt-2">Students can access this quiz anytime</p>
                `;
            } else {
                const openDate = openInput ? new Date(openInput) : null;
                const closeDate = closeInput ? new Date(closeInput) : null;

                let status = '';
                let statusClass = '';
                let statusIcon = '';

                if (openDate && now < openDate) {
                    status = 'Not Yet Available';
                    statusClass = 'bg-blue-100 text-blue-800';
                    statusIcon = 'fa-clock';
                } else if (closeDate && now > closeDate) {
                    status = 'Closed';
                    statusClass = 'bg-red-100 text-red-800';
                    statusIcon = 'fa-lock';
                } else {
                    status = 'Available';
                    statusClass = 'bg-green-100 text-green-800';
                    statusIcon = 'fa-check-circle';
                }

                html = `
                    <div class="space-y-3">
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center px-4 py-2 rounded-lg ${statusClass} font-medium">
                                <i class="fas ${statusIcon} mr-2"></i>${status}
                            </span>
                        </div>
                `;

                if (openDate) {
                    const openStr = openDate.toLocaleString('en-US', { 
                        month: 'short', day: 'numeric', year: 'numeric', 
                        hour: '2-digit', minute: '2-digit' 
                    });
                    html += `
                        <div class="flex items-start">
                            <i class="fas fa-calendar-plus text-green-600 mr-2 mt-1"></i>
                            <div>
                                <span class="font-medium text-gray-700">Opens:</span>
                                <span class="text-gray-900">${openStr}</span>
                            </div>
                        </div>
                    `;
                }

                if (closeDate) {
                    const closeStr = closeDate.toLocaleString('en-US', { 
                        month: 'short', day: 'numeric', year: 'numeric', 
                        hour: '2-digit', minute: '2-digit' 
                    });
                    html += `
                        <div class="flex items-start">
                            <i class="fas fa-calendar-times text-red-600 mr-2 mt-1"></i>
                            <div>
                                <span class="font-medium text-gray-700">Closes:</span>
                                <span class="text-gray-900">${closeStr}</span>
                            </div>
                        </div>
                    `;
                }

                html += `</div>`;
            }

            if (durationInput && parseInt(durationInput) > 0) {
                html += `
                    <div class="mt-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                        <i class="fas fa-clock text-yellow-600 mr-2"></i>
                        <span class="font-medium text-gray-700">Duration:</span>
                        <span class="text-gray-900">${durationInput} minutes</span>
                        <p class="text-xs text-yellow-700 mt-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Quiz will automatically submit when time expires
                        </p>
                    </div>
                `;
            }

            preview.innerHTML = html;
        }

        // Validate dates on submit
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            const openDate = document.getElementById('open_date').value;
            const closeDate = document.getElementById('close_date').value;
            const duration = document.getElementById('duration').value;
            
            if (!duration || parseInt(duration) <= 0) {
                e.preventDefault();
                alert('Error: Please set a duration for the quiz!');
                return;
            }
            
            if (openDate && closeDate) {
                const open = new Date(openDate);
                const close = new Date(closeDate);
                if (close <= open) {
                    e.preventDefault();
                    alert('Error: Close date must be after open date!');
                }
            }
        });

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            updateAvailabilityPreview();
            document.getElementById('open_date').addEventListener('change', updateAvailabilityPreview);
            document.getElementById('close_date').addEventListener('change', updateAvailabilityPreview);
            document.getElementById('duration').addEventListener('input', updateAvailabilityPreview);
        });

        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const emptyMessage = container.querySelector('.text-center');
            if (emptyMessage) {
                emptyMessage.remove();
            }

            const questionCard = document.createElement('div');
            questionCard.className = 'question-card border-2 border-gray-200 rounded-lg p-4 bg-gray-50';
            questionCard.dataset.questionIndex = questionIndex;
            
            questionCard.innerHTML = `
                <div class="flex justify-between items-start mb-3">
                    <div class="flex items-center space-x-2">
                        <h3 class="font-semibold text-navy">Question ${questionIndex + 1}</h3>
                        <span class="badge-mcq"><i class="fas fa-check-circle mr-1"></i>MCQ</span>
                    </div>
                    <button type="button" onclick="removeQuestion(this)" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Question Type *</label>
                            <select name="questions[${questionIndex}][type]" class="w-full p-2 border border-gray-300 rounded-lg question-type-select" onchange="toggleOptionsSection(this)">
                                <option value="multiple_choice">Multiple Choice (Auto-graded)</option>
                                <option value="true_false">True/False (Auto-graded)</option>
                                <option value="short_answer">Short Answer (Teacher graded)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Marks *</label>
                            <input type="number" name="questions[${questionIndex}][marks]" value="1" min="1" class="w-full p-2 border border-gray-300 rounded-lg" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Question Text *</label>
                        <textarea name="questions[${questionIndex}][question]" rows="2" class="w-full p-2 border border-gray-300 rounded-lg" required></textarea>
                    </div>
                    
                    <div class="options-section-mcq">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Options *</label>
                        <div class="space-y-2">
                            <input type="text" name="questions[${questionIndex}][options][]" placeholder="Option 1" class="w-full p-2 border border-gray-300 rounded-lg">
                            <input type="text" name="questions[${questionIndex}][options][]" placeholder="Option 2" class="w-full p-2 border border-gray-300 rounded-lg">
                            <input type="text" name="questions[${questionIndex}][options][]" placeholder="Option 3" class="w-full p-2 border border-gray-300 rounded-lg">
                            <input type="text" name="questions[${questionIndex}][options][]" placeholder="Option 4" class="w-full p-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer (Option Number) *</label>
                            <select name="questions[${questionIndex}][correct_answer]" class="w-full p-2 border border-gray-300 rounded-lg">
                                <option value="0">Option 1</option>
                                <option value="1">Option 2</option>
                                <option value="2">Option 3</option>
                                <option value="3">Option 4</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="options-section-tf hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer *</label>
                        <select name="questions[${questionIndex}][correct_answer]" class="w-full p-2 border border-gray-300 rounded-lg">
                            <option value="True">True</option>
                            <option value="False">False</option>
                        </select>
                    </div>
                </div>
            `;
            
            container.appendChild(questionCard);
            questionIndex++;
            updateQuestionNumbers();
        }

        function removeQuestion(button) {
            if (confirm('Remove this question?')) {
                button.closest('.question-card').remove();
                updateQuestionNumbers();
                
                const container = document.getElementById('questionsContainer');
                if (container.children.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-question-circle text-4xl mb-3 opacity-50"></i>
                            <p>No questions yet. Click "Add Question" to get started.</p>
                        </div>
                    `;
                }
            }
        }

        function updateQuestionNumbers() {
            const questions = document.querySelectorAll('.question-card');
            questions.forEach((card, index) => {
                card.querySelector('h3').textContent = `Question ${index + 1}`;
            });
        }

        function toggleOptionsSection(select) {
            const questionCard = select.closest('.question-card');
            const optionsMCQ = questionCard.querySelector('.options-section-mcq');
            const optionsTF = questionCard.querySelector('.options-section-tf');
            const badge = questionCard.querySelector('.badge-mcq, .badge-short, .badge-tf');
            
            // Hide all first
            if (optionsMCQ) optionsMCQ.classList.add('hidden');
            if (optionsTF) optionsTF.classList.add('hidden');
            
            if (select.value === 'multiple_choice') {
                if (optionsMCQ) optionsMCQ.classList.remove('hidden');
                badge.className = 'badge-mcq';
                badge.innerHTML = '<i class="fas fa-check-circle mr-1"></i>MCQ';
            } else if (select.value === 'true_false') {
                if (optionsTF) optionsTF.classList.remove('hidden');
                badge.className = 'badge-tf';
                badge.innerHTML = '<i class="fas fa-toggle-on mr-1"></i>T/F';
            } else {
                badge.className = 'badge-short';
                badge.innerHTML = '<i class="fas fa-edit mr-1"></i>Short Answer';
            }
        }
    </script>
</body>
</html>