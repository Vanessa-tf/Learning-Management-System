<?php
session_start();
include(__DIR__ . "/includes/db.php");

header('Content-Type: application/json');

$group_id = $_GET['group_id'] ?? 0;
$last_id = $_GET['last_id'] ?? 0;

if (empty($group_id)) {
    error_log("Invalid group_id in get_messages.php: $group_id");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid group ID']);
    exit;
}

try {
    // Verify user is a member of the group
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE study_group_id = :group_id AND user_id = :user_id");
    $stmt->execute(['group_id' => $group_id, 'user_id' => $_SESSION['user_id']]);
    if ($stmt->fetchColumn() == 0) {
        error_log("User {$_SESSION['user_id']} is not a member of group $group_id");
        http_response_code(403);
        echo json_encode(['error' => 'Not a member of this group']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT 
            m.id, 
            m.message, 
            m.attachment, 
            m.file_name, 
            m.created_at, 
            m.edited_at,
            m.is_deleted,
            m.deleted_at,
            s.first_name AS username, 
            s.id AS user_id
        FROM messages m
        JOIN students s ON m.user_id = s.id
        WHERE m.study_group_id = :group_id AND m.id > :last_id
        ORDER BY m.created_at ASC
    ");
    $stmt->execute(['group_id' => $group_id, 'last_id' => $last_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Fetched " . count($messages) . " messages for group_id=$group_id, last_id=$last_id");
    echo json_encode($messages);
} catch (PDOException $e) {
    error_log("Error fetching messages for group $group_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch messages: ' . $e->getMessage()]);
}
?>