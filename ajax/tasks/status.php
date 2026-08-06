<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

if (!hasPermission('edit_tasks')) sendResponse(false, 'Forbidden');

$input = getJsonInput();
$taskId = $input['task_id'] ?? $_POST['task_id'] ?? null;
$status = $input['status'] ?? $_POST['status'] ?? null;

$validStatuses = ['pending', 'completed', 'cancelled'];

if (!$taskId || !$status || !in_array($status, $validStatuses)) {
    sendResponse(false, 'Invalid task ID or status');
}

try {
    $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
    $stmt->execute([$status, $taskId]);
    sendResponse(true, 'Task status updated', ['task_id' => $taskId, 'status' => $status]);
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
