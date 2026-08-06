<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized');
}

if (!hasPermission('delete_customers')) {
    sendResponse(false, 'Forbidden: Insufficient permissions');
}

$input = getJsonInput();
$id = $input['id'] ?? $_GET['id'] ?? null;

if (!$id) {
    sendResponse(false, 'Customer ID is required');
}

try {
    $stmt = $pdo->prepare("UPDATE customers SET is_archived = 1 WHERE id = ?");
    $stmt->execute([$id]);
    sendResponse(true, 'Customer archived successfully');
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
