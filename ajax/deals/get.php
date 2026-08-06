<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

if (!hasPermission('view_deals')) sendResponse(false, 'Forbidden: Insufficient permissions');

try {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT d.*, c.display_name as customer_name, l.display_name as lead_name, u.name as assigned_to_name FROM deals d LEFT JOIN customers c ON d.customer_id = c.id LEFT JOIN leads l ON d.lead_id = l.id LEFT JOIN users u ON d.assigned_to = u.id WHERE d.id = ?");
        $stmt->execute([$_GET['id']]);
        $deal = $stmt->fetch();
        if ($deal) {
            // Get deal products
            $pStmt = $pdo->prepare("SELECT * FROM deal_products WHERE deal_id = ?");
            $pStmt->execute([$_GET['id']]);
            $deal['products'] = $pStmt->fetchAll();
            sendResponse(true, '', $deal);
        } else {
            sendResponse(false, 'Deal not found');
        }
    } else {
        $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;
        $sql = "SELECT d.*, c.display_name as customer_name, l.display_name as lead_name, u.name as assigned_to_name FROM deals d LEFT JOIN customers c ON d.customer_id = c.id LEFT JOIN leads l ON d.lead_id = l.id LEFT JOIN users u ON d.assigned_to = u.id WHERE d.is_archived = ? ORDER BY d.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$archived]);
        $deals = $stmt->fetchAll();
        sendResponse(true, '', $deals);
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
