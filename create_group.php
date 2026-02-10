<?php
header('Content-Type: application/json');
session_start();
include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

if ($_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $course_id = $_POST['course_id'] ?? '';
    $group_name = trim($_POST['group_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Fetch enrolled courses
    $stmt = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $enrolled_course_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'course_id');

    // Validate inputs
    if (empty($course_id) || !in_array($course_id, $enrolled_course_ids)) {
        $errors[] = "Please select a valid enrolled course.";
    }
    if (empty($group_name)) {
        $errors[] = "Group name is required.";
    }
    if (strlen($group_name) > 255) {
        $errors[] = "Group name must be 255 characters or less.";
    }
    if (strlen($description) > 1000) {
        $errors[] = "Description must be 1000 characters or less.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO study_groups (course_id, name, description, members_count, last_active)
                VALUES (:course_id, :name, :description, 1, NOW())
            ");
            $stmt->execute([
                'course_id' => $course_id,
                'name' => $group_name,
                'description' => $description
            ]);

            $group_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO group_members (study_group_id, user_id, joined_at) VALUES (:group_id, :user_id, NOW())");
            $stmt->execute(['group_id' => $group_id, 'user_id' => $user_id]);

            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (:user_id, 'study_group', :message, NOW())");
            $stmt->execute([
                'user_id' => $user_id,
                'message' => "You created the study group '$group_name'."
            ]);

            $pdo->commit();
            echo json_encode(['success' => true, 'group_id' => $group_id]);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error creating study group for user $user_id: " . $e->getMessage());
            $errors[] = "An error occurred while creating the group.";
        }
    }
}

echo json_encode(['error' => implode(', ', $errors)]);
?>