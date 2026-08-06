<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

if (!hasPermission('view_tasks')) sendResponse(false, 'Forbidden');

try {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT t.*, u.name as assigned_to_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.id = ?");
        $stmt->execute([$_GET['id']]);
        $task = $stmt->fetch();
        if ($task) sendResponse(true, '', $task);
        else sendResponse(false, 'Task not found');
    } else {
        $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;
        $sql = "SELECT t.*, u.name as assigned_to_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.is_archived = ? ORDER BY t.due_date ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$archived]);
        $tasks = $stmt->fetchAll();
        sendResponse(true, '', $tasks);
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
