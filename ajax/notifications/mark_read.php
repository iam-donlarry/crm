<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

$input = getJsonInput();
$notificationId = $input['id'] ?? $_POST['id'] ?? null;
$userId = $_SESSION['user_id'];

if ($notificationId) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
} else {
    // Mark all as read
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}

sendResponse(true, 'Notifications updated');
