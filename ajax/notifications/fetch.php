<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

$userId = $_SESSION['user_id'];
$unreadCount = getUnreadNotificationCount($pdo, $userId);
$notifications = getUserNotifications($pdo, $userId, 10);

sendResponse(true, 'Notifications fetched', [
    'unread_count' => $unreadCount,
    'notifications' => $notifications
]);
