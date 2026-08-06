<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');
if (!hasPermission('view_leads')) sendResponse(false, 'Forbidden: Insufficient permissions');

try {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT l.*, c.display_name as customer_name FROM leads l LEFT JOIN customers c ON l.customer_id = c.id WHERE l.id = ?");
        $stmt->execute([$_GET['id']]);
        $lead = $stmt->fetch();
        if ($lead) {
            // Fetch lead products
            $pStmt = $pdo->prepare("SELECT lp.*, p.name as product_name, p.code as product_code FROM lead_products lp JOIN products p ON lp.product_id = p.id WHERE lp.lead_id = ?");
            $pStmt->execute([$_GET['id']]);
            $lead['products'] = $pStmt->fetchAll();
            sendResponse(true, '', $lead);
        } else {
            sendResponse(false, 'Lead not found');
        }
    } else {
        $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;
        $sql = "SELECT l.*, c.display_name as customer_name, u.name as assigned_to_name FROM leads l LEFT JOIN customers c ON l.customer_id = c.id LEFT JOIN users u ON l.assigned_to = u.id WHERE l.is_archived = ? ORDER BY l.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$archived]);
        $leads = $stmt->fetchAll();
        sendResponse(true, '', $leads);
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
