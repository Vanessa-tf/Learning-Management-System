<?php
session_start();
include(__DIR__ . "/includes/db.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$message_id = $_POST['message_id'] ?? 0;
$new_message = trim($_POST['message'] ?? '');
$user_id = $_SESSION['user_id'];

if (empty($message_id) || empty($new_message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message ID and content required']);
    exit;
}

try {
    // Verify user owns the message
    $stmt = $pdo->prepare("SELECT user_id FROM messages WHERE id = :message_id");
    $stmt->execute(['message_id' => $message_id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit;
    }
    
    if ($message['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only edit your own messages']);
        exit;
    }

    // Update the message
    $stmt = $pdo->prepare("UPDATE messages SET message = :message, edited_at = NOW() WHERE id = :message_id");
    $stmt->execute([
        'message' => $new_message,
        'message_id' => $message_id
    ]);

    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Error editing message $message_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to edit message']);
}
?>