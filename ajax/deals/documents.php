<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!hasPermission('view_documents') && !hasPermission('view_deals')) {
        sendResponse(false, 'Forbidden');
    }
    $dealId = isset($_GET['deal_id']) ? intval($_GET['deal_id']) : 0;
    if (!$dealId) {
        sendResponse(false, 'Deal ID required');
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE deal_id = ? AND is_archived = 0 ORDER BY created_at DESC");
        $stmt->execute([$dealId]);
        sendResponse(true, 'Documents fetched', $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
} elseif ($method === 'POST') {
    if (!hasPermission('upload_documents') && !hasPermission('edit_deals') && !hasPermission('create_deals')) {
        sendResponse(false, 'Forbidden');
    }
    $data = getJsonInput();
    if (empty($data['deal_id']) || empty($data['type']) || empty($data['document_number'])) {
        sendResponse(false, 'Missing required fields: deal_id, type, document_number');
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO documents (deal_id, type, document_number, content_snapshot) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            intval($data['deal_id']),
            $data['type'],
            $data['document_number'],
            $data['content_snapshot'] ?? null
        ]);
        sendResponse(true, 'Document Snapshot Saved', ['id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
} else {
    sendResponse(false, 'Method Not Allowed');
}
