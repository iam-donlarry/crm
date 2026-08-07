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
    sendResponse(false, 'Forbidden: Insufficient permissions to manage roles');
}

$input = getJsonInput();
$id = !empty($input['id'] ?? $_POST['id'] ?? null) ? intval($input['id'] ?? $_POST['id']) : 0;
$name = trim($input['name'] ?? $_POST['name'] ?? '');
$description = trim($input['description'] ?? $_POST['description'] ?? '');
$cloneFromRoleId = !empty($input['clone_from_role_id'] ?? $_POST['clone_from_role_id'] ?? null) ? intval($input['clone_from_role_id'] ?? $_POST['clone_from_role_id']) : 0;
$permissionIds = $input['permission_ids'] ?? $_POST['permission_ids'] ?? [];

if (!is_array($permissionIds)) {
    $permissionIds = [];
}

if (empty($name)) {
    sendResponse(false, 'Role name is required');
}

try {
    $pdo->beginTransaction();

    // If cloning permissions from another role and no permissions explicitly passed
    if ($cloneFromRoleId > 0 && empty($permissionIds)) {
        $cloneStmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
        $cloneStmt->execute([$cloneFromRoleId]);
        $permissionIds = $cloneStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($permissionIds)) {
            $rStmt = $pdo->prepare("SELECT permissions FROM roles WHERE id = ?");
            $rStmt->execute([$cloneFromRoleId]);
            $rawPerms = $rStmt->fetchColumn();
            $permissionIds = json_decode($rawPerms, true) ?: [];
        }
    }

    $permissionIds = array_unique(array_map('intval', $permissionIds));
    $jsonPermissions = json_encode(array_values($permissionIds));

    if ($id > 0) {
        // Check if role is system role (prevent changing system role names if restricted)
        $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ?, permissions = ? WHERE id = ?");
        $stmt->execute([$name, $description, $jsonPermissions, $id]);
        $roleId = $id;
    } else {
        $stmt = $pdo->prepare("INSERT INTO roles (name, description, is_system, is_default, permissions, is_archived) VALUES (?, ?, 0, 0, ?, 0)");
        $stmt->execute([$name, $description, $jsonPermissions]);
        $roleId = $pdo->lastInsertId();
    }

    // Sync role_permissions junction table
    $delStmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $delStmt->execute([$roleId]);

    if (!empty($permissionIds)) {
        $insStmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($permissionIds as $pId) {
            if ($pId > 0) {
                $insStmt->execute([$roleId, $pId]);
            }
        }
    }

    $pdo->commit();
    sendResponse(true, $id > 0 ? 'Role updated successfully!' : 'New security role created successfully!', ['id' => $roleId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
