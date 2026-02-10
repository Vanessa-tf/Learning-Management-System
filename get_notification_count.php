<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$count = getUnreadNotificationCount($conn, $_SESSION['user_id']);

echo json_encode(['count' => $count]);
?>