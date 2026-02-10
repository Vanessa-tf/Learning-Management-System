<?php
header('Content-Type: application/json');
session_start();
include(__DIR__ . "/includes/db.php");

if ($_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$group_id) {
    echo json_encode(['error' => 'Invalid group ID']);
    exit;
}

try {
    // Verify user is a member of the group
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE study_group_id = :group_id AND user_id = :user_id");
    $stmt->execute(['group_id' => $group_id, 'user_id' => $user_id]);
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['error' => 'You are not a member of this group']);
        exit;
    }

    // Fetch group details
    $stmt = $pdo->prepare("
        SELECT sg.id, sg.name, c.course_name
        FROM study_groups sg
        JOIN courses c ON sg.course_id = c.id
        WHERE sg.id = :group_id
    ");
    $stmt->execute(['group_id' => $group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) {
        echo json_encode(['error' => 'Group not found']);
        exit;
    }

    // Fetch messages
    $stmt = $pdo->prepare("
        SELECT m.id, m.message, m.created_at, s.first_name AS username
        FROM messages m
        JOIN students s ON m.user_id = s.id
        WHERE m.study_group_id = :group_id
        ORDER BY m.created_at ASC
    ");
    $stmt->execute(['group_id' => $group_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'id' => $group['id'],
        'name' => $group['name'],
        'course_name' => $group['course_name'],
        'messages' => $messages
    ]);
} catch (PDOException $e) {
    error_log("Error fetching chat for group $group_id: " . $e->getMessage());
    echo json_encode(['error' => 'Error fetching chat']);
}
?>