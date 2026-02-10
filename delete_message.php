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
$user_id = $_SESSION['user_id'];

if (empty($message_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message ID required']);
    exit;
}

try {
    // Verify user owns the message
    $stmt = $pdo->prepare("SELECT user_id, attachment FROM messages WHERE id = :message_id");
    $stmt->execute(['message_id' => $message_id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit;
    }
    
    if ($message['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only delete your own messages']);
        exit;
    }

    // Soft delete the message (mark as deleted but keep in database)
    $stmt = $pdo->prepare("UPDATE messages SET is_deleted = 1, deleted_at = NOW() WHERE id = :message_id");
    $stmt->execute(['message_id' => $message_id]);

    // Delete the attached file from server
    if (!empty($message['attachment'])) {
        $file_path = __DIR__ . '/uploads/chat_attachments/' . $message['attachment'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Error deleting message $message_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete message']);
}
?>