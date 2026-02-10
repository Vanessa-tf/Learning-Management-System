<?php
// ajax/create_quiz_attempt.php - Create quiz attempt via AJAX
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../models/Course.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'create_attempt') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'];
$content_id = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;

if ($content_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid content ID']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get course content to verify it exists and get course_id
    $course = new Course();
    $content = $course->getContentByIdAndType($content_id, 'quiz');
    
    if (!$content) {
        echo json_encode(['success' => false, 'error' => 'Quiz not found']);
        exit;
    }
    
    // Check if there's already an active attempt
    $stmt = $pdo->prepare("
        SELECT id FROM lockdown_quiz_attempts 
        WHERE user_id = :user_id 
        AND content_id = :content_id 
        AND submitted_at IS NULL
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([
        'user_id' => $user_id,
        'content_id' => $content_id
    ]);
    $existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_attempt) {
        echo json_encode([
            'success' => true,
            'attempt_id' => $existing_attempt['id']
        ]);
        exit;
    }
    
    // Get total questions for the quiz
    $questions = [];
    if (!empty($content['quiz_content'])) {
        $quiz_data = json_decode($content['quiz_content'], true);
        if ($quiz_data && is_array($quiz_data)) {
            $questions = $quiz_data;
        }
    }
    
    // Create new attempt
    $stmt = $pdo->prepare("
        INSERT INTO lockdown_quiz_attempts 
        (user_id, content_id, course_id, score, total_questions, percentage, violations, created_at)
        VALUES 
        (:user_id, :content_id, :course_id, 0, :total_questions, 0, 0, NOW())
    ");
    
    $stmt->execute([
        'user_id' => $user_id,
        'content_id' => $content_id,
        'course_id' => $content['course_id'],
        'total_questions' => count($questions)
    ]);
    
    $attempt_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'attempt_id' => $attempt_id
    ]);
    
} catch (PDOException $e) {
    error_log("Error creating quiz attempt: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>