<?php
header('Content-Type: application/json');
session_start();
include(__DIR__ . "/includes/db.php");

if ($_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$course_ids = isset($_GET['course_ids']) ? explode(',', $_GET['course_ids']) : [];
$course_ids = array_filter($course_ids, 'is_numeric');

if (empty($course_ids)) {
    echo json_encode([]);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
    $query = "
        SELECT sg.id, sg.course_id, sg.name, sg.description, sg.members_count, sg.last_active, c.course_name,
               (SELECT COUNT(*) FROM group_members gm WHERE gm.study_group_id = sg.id AND gm.user_id = ?) AS is_member
        FROM study_groups sg
        JOIN courses c ON sg.course_id = c.id
        WHERE sg.course_id IN ($placeholders)
        ORDER BY sg.last_active DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    foreach ($course_ids as $index => $course_id) {
        $stmt->bindValue($index + 2, $course_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($groups);
} catch (PDOException $e) {
    error_log("Error fetching study groups for user $user_id: " . $e->getMessage());
    echo json_encode(['error' => 'Error fetching groups']);
}
?>