<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

$input = getJsonInput();
$id = !empty($input['id'] ?? $_POST['id'] ?? null) ? intval($input['id'] ?? $_POST['id']) : null;

if ($id) {
    if (!hasPermission('edit_activities') && !hasPermission('edit_tasks')) sendResponse(false, 'Forbidden');
} else {
    if (!hasPermission('add_activities') && !hasPermission('add_tasks')) sendResponse(false, 'Forbidden');
}

$title = trim($input['title'] ?? $_POST['title'] ?? '');
$description = trim($input['description'] ?? $_POST['description'] ?? '');
$type = $input['type'] ?? $_POST['type'] ?? 'todo';
$status = $input['status'] ?? $_POST['status'] ?? 'pending';
$priority = $input['priority'] ?? $_POST['priority'] ?? 'medium';

$rawDueDate = trim($input['due_date'] ?? $_POST['due_date'] ?? '');
$dueDate = !empty($rawDueDate) ? $rawDueDate : null;

$rawRelType = trim($input['related_to_type'] ?? $_POST['related_to_type'] ?? '');
$relatedToType = !empty($rawRelType) ? $rawRelType : null;

$rawRelId = $input['related_to_id'] ?? $_POST['related_to_id'] ?? null;
$relatedToId = !empty($rawRelId) ? intval($rawRelId) : null;

$rawAssignedTo = $input['assigned_to'] ?? $_POST['assigned_to'] ?? null;
$assignedTo = !empty($rawAssignedTo) ? intval($rawAssignedTo) : $_SESSION['user_id'];

if (empty($title)) sendResponse(false, 'Task title is required');

try {
    if ($id) {
        $sql = "UPDATE tasks SET title=?, description=?, type=?, status=?, priority=?, due_date=?, related_to_type=?, related_to_id=?, assigned_to=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $type, $status, $priority, $dueDate, $relatedToType, $relatedToId, $assignedTo, $id]);
        sendResponse(true, 'Task updated successfully', ['id' => $id]);
    } else {
        $sql = "INSERT INTO tasks (title, description, type, status, priority, due_date, related_to_type, related_to_id, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $type, $status, $priority, $dueDate, $relatedToType, $relatedToId, $assignedTo]);
        $newId = $pdo->lastInsertId();
        
        if ($assignedTo && $assignedTo != $_SESSION['user_id']) {
            createNotification($pdo, $assignedTo, 'New Task Assigned', "You were assigned a new task: {$title}", '/crm/tasks', 'info', true);
        }

        sendResponse(true, 'Task created successfully', ['id' => $newId]);
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
