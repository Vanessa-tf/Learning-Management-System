<?php
header('Content-Type: application/json');
session_start();
include(__DIR__ . "/includes/db.php");

if ($_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

if (!$group_id) {
    echo json_encode(['error' => 'Invalid group ID']);
    exit;
}

try {
    // Check if user is already a member
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE study_group_id = :group_id AND user_id = :user_id");
    $stmt->execute(['group_id' => $group_id, 'user_id' => $user_id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['error' => 'You are already a member of this group']);
        exit;
    }

    // Add user to group
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO group_members (study_group_id, user_id, joined_at) VALUES (:group_id, :user_id, NOW())");
    $stmt->execute(['group_id' => $group_id, 'user_id' => $user_id]);

    $stmt = $pdo->prepare("UPDATE study_groups SET members_count = members_count + 1, last_active = NOW() WHERE id = :group_id");
    $stmt->execute(['group_id' => $group_id]);

    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (:user_id, 'study_group', :message, NOW())");
    $stmt->execute([
        'user_id' => $user_id,
        'message' => "You joined a study group."
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'group_id' => $group_id]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error joining group $group_id for user $user_id: " . $e->getMessage());
    echo json_encode(['error' => 'Error joining group']);
}
?>