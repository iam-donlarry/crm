<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized');
}

if (!hasPermission('archive_deals') && !hasPermission('delete_deals')) {
    sendResponse(false, 'Forbidden: Insufficient permissions');
}

$input = getJsonInput();
$id = !empty($input['id'] ?? $_GET['id'] ?? null) ? intval($input['id'] ?? $_GET['id']) : 0;

if ($id <= 0) {
    sendResponse(false, 'Deal ID is required');
}

try {
    $stmt = $pdo->prepare("UPDATE deals SET is_archived = 1 WHERE id = ?");
    $stmt->execute([$id]);

    sendResponse(true, 'Opportunity archived successfully');
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
