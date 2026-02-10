<?php
// api/mark_notification_read.php

include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$notificationId = $data['notification_id'] ?? null;

if (!$notificationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Notification ID required']);
    exit;
}

$notificationHelper = new NotificationHelper($db);
$result = $notificationHelper->markAsRead($notificationId, $_SESSION['user_id']);

echo json_encode(['success' => $result]);
?>

---FILE_SEPARATOR---

<?php
// api/mark_all_notifications_read.php

include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$notificationHelper = new NotificationHelper($db);
$result = $notificationHelper->markAllAsRead($_SESSION['user_id']);

echo json_encode(['success' => $result]);
?>

---FILE_SEPARATOR---

<?php
// api/get_notification_count.php

include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['count' => 0]);
    exit;
}

header('Content-Type: application/json');

$notificationHelper = new NotificationHelper($db);
$count = $notificationHelper->getUnreadCount($_SESSION['user_id']);

echo json_encode(['count' => $count]);
?>

---FILE_SEPARATOR---

<?php
// api/get_notifications.php - Fetch notifications via AJAX

include(__DIR__ . "/includes/db.php");
include(__DIR__ . "/includes/functions.php");

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$filter = $_GET['filter'] ?? 'all';
$limit = $_GET['limit'] ?? 50;

$notificationHelper = new NotificationHelper($db);
$notifications = $notificationHelper->getNotifications($_SESSION['user_id'], $limit, $filter);
$unreadCount = $notificationHelper->getUnreadCount($_SESSION['user_id']);

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unreadCount
]);
?>