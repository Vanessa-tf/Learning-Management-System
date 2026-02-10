<?php
// edit_mock_exam.php - Enhanced with exam availability dates
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'models/MockExams.php';

requireRole('content');

// Helper function for deadline urgency
function getDeadlineUrgency($deadline) {
    if (!$deadline) return null;
    
    $now = new DateTime();
    $deadlineDate = new DateTime($deadline);
    $diff = $now->diff($deadlineDate);
    
    $hoursRemaining = ($diff->days * 24) + $diff->h;
    
    if ($deadlineDate < $now) {
        return [
            'status' => 'overdue', 
            'class' => 'bg-red-100 border-red-500 text-red-800', 
            'icon' => 'exclamation-triangle', 
            'message' => 'OVERDUE'
        ];
    } elseif ($hoursRemaining <= 24) {
        return [
            'status' => 'critical', 
            'class' => 'bg-orange-100 border-orange-500 text-orange-800', 
            'icon' => 'exclamation-circle', 
            'message' => $hoursRemaining . 'h remaining'
        ];
    } elseif ($hoursRemaining <= 72) {
        return [
            'status' => 'warning', 
            'class' => 'bg-yellow-100 border-yellow-500 text-yellow-800', 
            'icon' => 'clock', 
            'message' => $diff->days . 'd ' . $diff->h . 'h remaining'
        ];
    } else {
        return [
            'status' => 'ok', 
            'class' => 'bg-green-100 border-green-500 text-green-800', 
            'icon' => 'check-circle', 
            'message' => $diff->days . ' days remaining'
        ];
    }
}

$mockExam = new MockExam();
$flash = null;

// Get exam ID from URL
$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$exam_id) {
    header('Location: mock_exams_cont_dev.php');
    exit;
}

// Get the exam
$exam = $mockExam->getById($exam_id);

if (!$exam) {
    header('Location: mock_exams_cont_dev.php');
    exit;
}

// Parse questions
$questions = [];
if (!empty($exam['questions'])) {
    if (is_string($exam['questions'])) {
        $questions = json_decode($exam['questions'], true) ?? [];
    } else {
        $questions = $exam['questions'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_exam') {
        // Build questions array from POST data
        $new_questions = [];
        
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $q) {
                if (!empty($q['question'])) {
                    $question_data = [
                        'question' => trim($q['question']),
                        'type' => $q['type'] ?? 'multiple_choice',
                        'options' => [],
                        'correct_answer' => $q['correct_answer'] ?? '',
                        'marks' => (int)($q['marks'] ?? 1)
                    ];
                    
                    if (isset($q['options']) && is_array($q['options'])) {
                        foreach ($q['options'] as $option) {
                            if (!empty(trim($option))) {
                                $question_data['options'][] = trim($option);
                            }
                        }
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
        
        // Validate dates
        if ($open_date && $close_date && strtotime($close_date) <= strtotime($open_date)) {
            $flash = ['text' => 'Close date must be after open date!', 'type' => 'error'];
        } else {
            // Update the exam
            $updateData = [
                'title' => trim($_POST['title']),
                'description' => trim($_POST['description']),
                'duration' => (int)$_POST['duration'],
                'marking_type' => $_POST['marking_type'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'questions' => $new_questions,
                'marking_deadline' => !empty($_POST['marking_deadline']) ? $_POST['marking_deadline'] : null,
                'open_date' => $open_date,
                'close_date' => $close_date
            ];
            
            $result = $mockExam->update($exam_id, $updateData);
            
            if ($result['success']) {
                $flash = ['text' => 'Mock exam updated successfully!', 'type' => 'success'];
                // Refresh exam data
                $exam = $mockExam->getById($exam_id);
                $questions = $new_questions;
            } else {
                $flash = ['text' => $result['error'], 'type' => 'error'];
            }
        }
    }
}

$user_id = $_SESSION['user_id'];
$display_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$initials = strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1));

// Get deadline status if exists
$deadline_status = null;
if (!empty($exam['marking_deadline'])) {
    $deadline_status = getDeadlineUrgency($exam['marking_deadline']);
}

// Format dates for datetime-local input (HTML5 format: YYYY-MM-DDTHH:MM)
$open_date_value = '';
$close_date_value = '';

if (!empty($exam['open_date'])) {
    $open_date_value = date('Y-m-d\TH:i', strtotime($exam['open_date']));
}

if (!empty($exam['close_date'])) {
    $close_date_value = date('Y-m-d\TH:i', strtotime($exam['close_date']));
}

// Get current server time for comparison
$current_time = time();
$open_timestamp = !empty($exam['open_date']) ? strtotime($exam['open_date']) : null;
$close_timestamp = !empty($exam['close_date']) ? strtotime($exam['close_date']) : null;

// Determine exam status
$exam_status = 'available';
$status_message = '';

if ($open_timestamp && $current_time < $open_timestamp) {
    $exam_status = 'not_yet_open';
    $status_message = 'Exam will open on ' . date('M j, Y \a\t g:i A', $open_timestamp);
} elseif ($close_timestamp && $current_time > $close_timestamp) {
    $exam_status = 'closed';
    $status_message = 'Exam closed on ' . date('M j, Y \a\t g:i A', $close_timestamp);
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
    <title>Edit Mock Exam - <?php echo htmlspecialchars($exam['title']); ?></title>
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
                    <a href="mock_exams_cont_dev.php" class="text-gray-600 hover:text-navy">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Mock Exams
                    </a>
                    <div class="border-l border-gray-300 h-6"></div>
                    <h1 class="text-xl font-bold text-navy">Edit Mock Exam</h1>
                </div>
                <div class="flex items-center space-x-4">
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

        <?php if ($deadline_status): ?>
        <!-- Deadline Status Banner -->
        <div class="mb-6 p-4 rounded-lg border-l-4 <?php echo $deadline_status['class']; ?>">
            <div class="flex items-center">
                <i class="fas fa-<?php echo $deadline_status['icon']; ?> text-2xl mr-3"></i>
                <div>
                    <h4 class="font-bold">
                        Marking Deadline: <?php echo date('M j, Y g:i A', strtotime($exam['marking_deadline'])); ?>
                    </h4>
                    <p class="text-sm"><?php echo $deadline_status['message']; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" id="examForm">
            <input type="hidden" name="action" value="save_exam">
            
            <!-- Exam Info -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-file-alt text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-navy">Exam Information</h2>
                        <p class="text-sm text-gray-600">Course: <?php echo htmlspecialchars($exam['course_name']); ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Exam Title *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($exam['title']); ?>" class="w-full p-2 border border-gray-300 rounded-lg" required>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="2" class="w-full p-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($exam['description']); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes) *</label>
                        <input type="number" name="duration" value="<?php echo $exam['duration']; ?>" min="1" class="w-full p-2 border border-gray-300 rounded-lg" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Marking Type</label>
                        <select name="marking_type" id="marking_type_select" class="w-full p-2 border border-gray-300 rounded-lg" onchange="toggleMarkingDeadline()">
                            <option value="auto" <?php echo $exam['marking_type'] === 'auto' ? 'selected' : ''; ?>>Auto</option>
                            <option value="teacher" <?php echo $exam['marking_type'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        </select>
                    </div>
                    
                    <div id="marking_deadline_container" style="display: <?php echo $exam['marking_type'] === 'teacher' ? 'block' : 'none'; ?>;" class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-clock mr-1"></i>
                            Marking Deadline 
                            <span class="text-xs text-gray-500">(When should marking be completed?)</span>
                        </label>
                        <input type="datetime-local" 
                               name="marking_deadline" 
                               id="marking_deadline_input" 
                               value="<?php echo !empty($exam['marking_deadline']) ? date('Y-m-d\TH:i', strtotime($exam['marking_deadline'])) : ''; ?>" 
                               class="w-full p-2 border border-gray-300 rounded-lg">
                        
                        <div class="mt-2 flex items-start gap-2">
                            <i class="fas fa-info-circle text-blue-500 mt-1"></i>
                            <p class="text-xs text-gray-600">
                                Set a deadline to help teachers prioritize marking. Teachers will see urgent notifications as the deadline approaches.
                            </p>
                        </div>
                        
                        <?php if ($deadline_status): ?>
                        <div class="mt-3 p-3 rounded-lg border-l-4 <?php echo $deadline_status['class']; ?>">
                            <div class="flex items-center text-sm">
                                <i class="fas fa-<?php echo $deadline_status['icon']; ?> mr-2"></i>
                                <strong>Current Status:</strong>&nbsp;<?php echo $deadline_status['message']; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-2">
                            <button type="button" onclick="setDeadlinePreset(7)" class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded mr-2 hover:bg-blue-200">
                                +7 days
                            </button>
                            <button type="button" onclick="setDeadlinePreset(14)" class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded mr-2 hover:bg-blue-200">
                                +14 days
                            </button>
                            <button type="button" onclick="setDeadlinePreset(30)" class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200">
                                +30 days
                            </button>
                        </div>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" <?php echo $exam['is_active'] ? 'checked' : ''; ?> class="mr-2">
                            <span class="text-sm font-medium text-gray-700">Active (visible to students)</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Exam Availability Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-clock text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-navy">Exam Availability</h2>
                        <p class="text-sm text-gray-600">Set when students can access this exam</p>
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
                        <i class="fas fa-info-circle mr-2"></i>Current Exam Status:
                    </h3>
                    <div class="mb-3">
                        <?php if ($exam_status === 'available'): ?>
                            <span class="status-badge status-available">
                                <i class="fas fa-check-circle mr-2"></i>Currently Available to Students
                            </span>
                        <?php elseif ($exam_status === 'not_yet_open'): ?>
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
                        <p class="text-sm text-gray-600">Total: <span id="questionCount"><?php echo count($questions); ?></span> question(s)</p>
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
                    <div class="question-card border border-gray-200 rounded-lg p-4" data-question-index="<?php echo $index; ?>">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="font-semibold text-navy">Question <?php echo $index + 1; ?></h3>
                            <button type="button" onclick="removeQuestion(this)" class="text-red-600 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Question Text *</label>
                                <textarea name="questions[<?php echo $index; ?>][question]" rows="2" class="w-full p-2 border border-gray-300 rounded-lg" required><?php echo htmlspecialchars($q['question']); ?></textarea>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Question Type</label>
                                    <select name="questions[<?php echo $index; ?>][type]" class="w-full p-2 border border-gray-300 rounded-lg question-type-select" onchange="toggleOptionsSection(this)">
                                        <option value="multiple_choice" <?php echo ($q['type'] ?? 'multiple_choice') === 'multiple_choice' ? 'selected' : ''; ?>>Multiple Choice</option>
                                        <option value="true_false" <?php echo ($q['type'] ?? '') === 'true_false' ? 'selected' : ''; ?>>True/False</option>
                                        <option value="short_answer" <?php echo ($q['type'] ?? '') === 'short_answer' ? 'selected' : ''; ?>>Short Answer</option>
                                        <option value="essay" <?php echo ($q['type'] ?? '') === 'essay' ? 'selected' : ''; ?>>Essay</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Marks</label>
                                    <input type="number" name="questions[<?php echo $index; ?>][marks]" value="<?php echo $q['marks'] ?? 1; ?>" min="1" class="w-full p-2 border border-gray-300 rounded-lg">
                                </div>
                            </div>
                            
                            <div class="options-section <?php echo ($q['type'] ?? 'multiple_choice') === 'multiple_choice' ? '' : 'hidden'; ?>">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Options</label>
                                <div class="space-y-2">
                                    <?php 
                                    $options = $q['options'] ?? ['', '', '', ''];
                                    for ($i = 0; $i < 4; $i++): 
                                    ?>
                                    <input type="text" name="questions[<?php echo $index; ?>][options][]" value="<?php echo htmlspecialchars($options[$i] ?? ''); ?>" placeholder="Option <?php echo $i + 1; ?>" class="w-full p-2 border border-gray-300 rounded-lg">
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer</label>
                                <input type="text" name="questions[<?php echo $index; ?>][correct_answer]" value="<?php echo htmlspecialchars($q['correct_answer'] ?? ''); ?>" placeholder="Enter correct answer (e.g., 0 for first option in MCQ)" class="w-full p-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-4">
                <a href="mock_exams_cont_dev.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="bg-purple text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-save mr-2"></i>Save Exam
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
                    <p class="text-xs text-gray-600 mt-2">Students can access this exam anytime</p>
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

            preview.innerHTML = html;
        }

        // Validate dates on submit
        document.getElementById('examForm').addEventListener('submit', function(e) {
            const openDate = document.getElementById('open_date').value;
            const closeDate = document.getElementById('close_date').value;
            
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
        });

        function toggleMarkingDeadline() {
            const markingType = document.getElementById('marking_type_select').value;
            const deadlineContainer = document.getElementById('marking_deadline_container');
            const deadlineInput = document.getElementById('marking_deadline_input');
            
            if (markingType === 'teacher') {
                deadlineContainer.style.display = 'block';
                
                // Set default deadline if empty (7 days from now)
                if (!deadlineInput.value) {
                    const now = new Date();
                    now.setDate(now.getDate() + 7);
                    now.setHours(23, 59, 0, 0);
                    deadlineInput.value = now.toISOString().slice(0, 16);
                }
            } else {
                deadlineContainer.style.display = 'none';
                deadlineInput.value = '';
            }
        }

        function setDeadlinePreset(days) {
            const deadlineInput = document.getElementById('marking_deadline_input');
            const now = new Date();
            now.setDate(now.getDate() + days);
            now.setHours(23, 59, 0, 0);
            deadlineInput.value = now.toISOString().slice(0, 16);
        }

        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const emptyMessage = container.querySelector('.text-center');
            if (emptyMessage) {
                emptyMessage.remove();
            }

            const questionCard = document.createElement('div');
            questionCard.className = 'question-card border border-gray-200 rounded-lg p-4';
            questionCard.dataset.questionIndex = questionIndex;
            
            questionCard.innerHTML = `
                <div class="flex justify-between items-start mb-3">
                    <h3 class="font-semibold text-navy">Question ${questionIndex + 1}</h3>
                    <button type="button" onclick="removeQuestion(this)" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Question Text *</label>
                        <textarea name="questions[${questionIndex}][question]" rows="2" class="w-full p-2 border border-gray-300 rounded-lg" required></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Question Type</label>
                            <select name="questions[${questionIndex}][type]" class="w-full p-2 border border-gray-300 rounded-lg question-type-select" onchange="toggleOptionsSection(this)">
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True/False</option>
                                <option value="short_answer">Short Answer</option>
                                <option value="essay">Essay</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Marks</label>
                            <input type="number" name="questions[${questionIndex}][marks]" value="1" min="1" class="w-full p-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                    
                    <div class="options-section">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Options</label>
                        <div class="space-y-2">
                            <input type="text" name="questions[${questionIndex}][options][]" placeholder="Option 1" class="w-full p-2 border border-gray-300 rounded-lg">
                            <input type="text" name="questions[${questionIndex}][options][]" placeholder="Option 2" class="w-full p-2 border border-gray-300 rounded-lg">
                            <input type="text" name="questions[${questionIndex}][options][]" placeholder="Option 3" class="w-full p-2 border border-gray-300 rounded-lg">
                            <input type="text" name="questions[${questionIndex}][options][]" placeholder="Option 4" class="w-full p-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer</label>
                        <input type="text" name="questions[${questionIndex}][correct_answer]" placeholder="Enter correct answer" class="w-full p-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
            `;
            
            container.appendChild(questionCard);
            questionIndex++;
            updateQuestionNumbers();
            updateQuestionCount();
        }

        function removeQuestion(button) {
            if (confirm('Remove this question?')) {
                button.closest('.question-card').remove();
                updateQuestionNumbers();
                updateQuestionCount();
                
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

        function updateQuestionCount() {
            const count = document.querySelectorAll('.question-card').length;
            document.getElementById('questionCount').textContent = count;
        }

        function toggleOptionsSection(select) {
            const questionCard = select.closest('.question-card');
            const optionsSection = questionCard.querySelector('.options-section');
            
            if (select.value === 'multiple_choice') {
                optionsSection.classList.remove('hidden');
            } else {
                optionsSection.classList.add('hidden');
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleMarkingDeadline();
        });
    </script>
</body>
</html>