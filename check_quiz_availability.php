<?php
// check_quiz_availability.php - Enhanced API to check if student can take a quiz
session_start();
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

header('Content-Type: application/json');

// Check if user is logged in
check_session();

if ($_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$content_id = isset($_GET['content_id']) ? (int)$_GET['content_id'] : 0;

if (!$content_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid content ID']);
    exit;
}

try {
    // First, check the quiz availability dates
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            open_date,
            close_date,
            CASE 
                WHEN open_date IS NOT NULL AND NOW() < open_date THEN 'not_yet_open'
                WHEN close_date IS NOT NULL AND NOW() > close_date THEN 'closed'
                ELSE 'date_available'
            END as date_status
        FROM course_content
        WHERE id = :content_id 
        AND content_type = 'quiz'
    ");
    $stmt->execute(['content_id' => $content_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['error' => 'Quiz not found']);
        exit;
    }
    
    // Check date availability first
    if ($quiz['date_status'] === 'not_yet_open') {
        $open_date = new DateTime($quiz['open_date']);
        $current_date = new DateTime();
        $interval = $current_date->diff($open_date);
        
        $days = $interval->days;
        $hours = $interval->h;
        $minutes = $interval->i;
        
        if ($days > 0) {
            $time_until = $days . ' day' . ($days > 1 ? 's' : '');
        } elseif ($hours > 0) {
            $time_until = $hours . ' hour' . ($hours > 1 ? 's' : '');
        } else {
            $time_until = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }
        
        echo json_encode([
            'available' => false,
            'can_take' => false,
            'reason' => 'not_yet_open',
            'message' => "This quiz is not yet available. It will open in {$time_until}.",
            'opens_at' => $open_date->format('F j, Y \a\t g:i A'),
            'opens_timestamp' => $open_date->getTimestamp()
        ]);
        exit;
    }
    
    if ($quiz['date_status'] === 'closed') {
        $close_date = new DateTime($quiz['close_date']);
        
        echo json_encode([
            'available' => false,
            'can_take' => false,
            'reason' => 'closed',
            'message' => "This quiz has closed and is no longer available.",
            'closed_at' => $close_date->format('F j, Y \a\t g:i A')
        ]);
        exit;
    }
    
    // Now check if student has taken this quiz before (30-day retake policy)
    $stmt = $pdo->prepare("
        SELECT 
            id,
            score,
            total_questions,
            submitted_at,
            DATEDIFF(NOW(), submitted_at) as days_since_attempt,
            DATE_ADD(submitted_at, INTERVAL 30 DAY) as next_available_date,
            30 - DATEDIFF(NOW(), submitted_at) as days_until_retake
        FROM lockdown_quiz_attempts
        WHERE user_id = :user_id 
        AND content_id = :content_id
        AND submitted_at IS NOT NULL
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute([
        'user_id' => $user_id,
        'content_id' => $content_id
    ]);
    $last_attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$last_attempt) {
        // Never taken before - check if close date is approaching
        $urgency_message = '';
        if (!empty($quiz['close_date'])) {
            $close_date = new DateTime($quiz['close_date']);
            $current_date = new DateTime();
            $interval = $current_date->diff($close_date);
            $days_remaining = (int)$interval->format('%r%a');
            
            if ($days_remaining <= 1) {
                $urgency_message = "⚠️ This quiz closes soon! Complete it before " . $close_date->format('M j, Y g:i A');
            } elseif ($days_remaining <= 3) {
                $urgency_message = "This quiz closes in {$days_remaining} days.";
            }
        }
        
        echo json_encode([
            'available' => true,
            'can_take' => true,
            'message' => 'Quiz is available',
            'first_attempt' => true,
            'urgency_message' => $urgency_message,
            'close_date' => !empty($quiz['close_date']) ? (new DateTime($quiz['close_date']))->format('F j, Y \a\t g:i A') : null
        ]);
        exit;
    }
    
    $days_since = $last_attempt['days_since_attempt'];
    
    if ($days_since >= 30) {
        // Can retake - but check close date
        $can_retake = true;
        $retake_message = 'Quiz is available for retake';
        
        if (!empty($quiz['close_date'])) {
            $close_date = new DateTime($quiz['close_date']);
            $current_date = new DateTime();
            
            if ($current_date >= $close_date) {
                $can_retake = false;
                $retake_message = 'The retake period has expired as the quiz has closed.';
            } else {
                $interval = $current_date->diff($close_date);
                $days_remaining = (int)$interval->format('%r%a');
                
                if ($days_remaining <= 1) {
                    $retake_message = "Quiz closes soon! Complete your retake before " . $close_date->format('M j, Y g:i A');
                }
            }
        }
        
        echo json_encode([
            'available' => $can_retake,
            'can_take' => $can_retake,
            'message' => $retake_message,
            'first_attempt' => false,
            'last_attempt_date' => date('M j, Y', strtotime($last_attempt['submitted_at'])),
            'last_score' => $last_attempt['score'],
            'total_questions' => $last_attempt['total_questions'],
            'close_date' => !empty($quiz['close_date']) ? (new DateTime($quiz['close_date']))->format('F j, Y \a\t g:i A') : null
        ]);
    } else {
        // Cannot retake yet - still in 30-day waiting period
        $days_until_retake = 30 - $days_since;
        $last_attempt_date = date('F j, Y', strtotime($last_attempt['submitted_at']));
        $next_available_date = date('F j, Y', strtotime($last_attempt['next_available_date']));
        
        // Check if the close date will pass before retake is available
        $retake_possible = true;
        $additional_message = '';
        
        if (!empty($quiz['close_date'])) {
            $close_date = new DateTime($quiz['close_date']);
            $next_retake_date = new DateTime($last_attempt['next_available_date']);
            
            if ($next_retake_date >= $close_date) {
                $retake_possible = false;
                $additional_message = " Note: This quiz will close on " . $close_date->format('F j, Y') . ", which is before your retake becomes available.";
            }
        }
        
        echo json_encode([
            'available' => false,
            'can_take' => false,
            'reason' => 'retake_cooldown',
            'message' => "You last took this quiz on {$last_attempt_date}. You can retake it on {$next_available_date}.{$additional_message}",
            'days_until_retake' => $days_until_retake,
            'last_attempt_date' => $last_attempt_date,
            'next_available_date' => $next_available_date,
            'last_score' => $last_attempt['score'],
            'total_questions' => $last_attempt['total_questions'],
            'retake_will_be_possible' => $retake_possible
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error checking quiz availability: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to check quiz availability']);
}
?>