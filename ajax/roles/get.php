<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized');
}

if (!hasPermission('manage_roles') && !hasPermission('view_users') && $_SESSION['user_role'] !== 'admin') {
    sendResponse(false, 'Forbidden: Insufficient permissions');
}

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_archived = 0");
        $stmt->execute([$id]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            sendResponse(false, 'Role not found');
        }

        // Fetch assigned permission IDs from role_permissions table
        $permStmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
        $permStmt->execute([$id]);
        $permissionIds = $permStmt->fetchAll(PDO::FETCH_COLUMN);

        // Fallback to JSON if role_permissions has no entries
        if (empty($permissionIds) && !empty($role['permissions'])) {
            $permissionIds = json_decode($role['permissions'], true) ?: [];
        }

        $role['permission_ids'] = array_map('intval', $permissionIds);
        sendResponse(true, 'Role fetched successfully', $role);
    } else {
        $stmt = $pdo->query("
            SELECT r.*, 
                   COUNT(DISTINCT u.id) as user_count,
                   COUNT(DISTINCT rp.permission_id) as perm_count
            FROM roles r
            LEFT JOIN users u ON u.role_id = r.id AND u.is_archived = 0
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            WHERE r.is_archived = 0
            GROUP BY r.id
            ORDER BY r.name ASC
        ");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendResponse(true, 'Roles fetched successfully', $roles);
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
