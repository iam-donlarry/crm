<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

if (!hasPermission('view_deals') && !hasPermission('view_team_deals')) {
    sendResponse(false, 'Forbidden: Access Denied');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    sendResponse(false, 'Deal ID required');
}

try {
    // Fetch parent deal details
    $stmt = $pdo->prepare("SELECT d.*, 
                                  c.display_name as customer_name, c.company as customer_company, c.address as customer_address, c.city as customer_city, c.state as customer_state, c.zip as customer_zip, c.country as customer_country,
                                  l.display_name as lead_name, l.company as lead_company,
                                  COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as assigned_to_name
                           FROM deals d
                           LEFT JOIN customers c ON d.customer_id = c.id
                           LEFT JOIN leads l ON d.lead_id = l.id
                           LEFT JOIN users u ON d.assigned_to = u.id
                           WHERE d.id = ? AND d.is_archived = 0");
    $stmt->execute([$id]);
    $deal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deal) {
        sendResponse(false, 'Deal not found');
    }

    // Fetch line items from deal_products table
    $itemStmt = $pdo->prepare("SELECT dp.*, p.code as product_code, p.unit as product_unit
                               FROM deal_products dp
                               LEFT JOIN products p ON dp.product_id = p.id
                               WHERE dp.deal_id = ?
                               ORDER BY dp.id ASC");
    $itemStmt->execute([$id]);
    $deal['lineItems'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(true, 'Deal retrieved successfully', $deal);
} catch (PDOException $e) {
    sendResponse(false, 'Database Error: ' . $e->getMessage());
}
