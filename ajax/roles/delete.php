<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized');
}

if (!hasPermission('manage_roles') && $_SESSION['user_role'] !== 'admin') {
    sendResponse(false, 'Forbidden: Insufficient permissions');
}

$input = getJsonInput();
$id = !empty($input['id'] ?? $_GET['id'] ?? null) ? intval($input['id'] ?? $_GET['id']) : 0;

if ($id <= 0) {
    sendResponse(false, 'Role ID is required');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        sendResponse(false, 'Role not found');
    }

    if ($role['is_system'] == 1) {
        sendResponse(false, 'System default roles cannot be deleted.');
    }

    // Check if staff users are assigned to this role
    $userCountStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ? AND is_archived = 0");
    $userCountStmt->execute([$id]);
    $userCount = $userCountStmt->fetchColumn();

    if ($userCount > 0) {
        sendResponse(false, "Cannot delete role: $userCount staff member(s) are currently assigned to this role. Re-assign them first.");
    }

    $updateStmt = $pdo->prepare("UPDATE roles SET is_archived = 1 WHERE id = ?");
    $updateStmt->execute([$id]);

    sendResponse(true, 'Role archived successfully');
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
