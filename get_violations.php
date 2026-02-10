<?php
// get_violations.php - Fetch violation details for a quiz attempt
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

// Check if user is teacher
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$attempt_id = $_GET['attempt_id'] ?? 0;

if (!$attempt_id) {
    echo json_encode(['error' => 'Invalid attempt ID']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT * FROM quiz_violations 
        WHERE attempt_id = ? 
        ORDER BY logged_at ASC
    ");
    
    $stmt->execute([$attempt_id]);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($violations);
    
} catch (Exception $e) {
    error_log("Error fetching violations: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>