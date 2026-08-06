<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
header('Content-Type: application/json');

if ($_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access Denied: Only administrators can deactivate users']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

if ($id === intval($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own logged-in account']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE users SET is_archived = 1 WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'User deactivated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
