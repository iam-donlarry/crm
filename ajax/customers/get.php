<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized');
}

if (!hasPermission('view_customers')) {
    sendResponse(false, 'Forbidden: Insufficient permissions');
}

try {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $customer = $stmt->fetch();
        if ($customer) sendResponse(true, '', $customer);
        else sendResponse(false, 'Customer not found');
    } else {
        $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;
        $sql = "SELECT * FROM customers WHERE is_archived = ? ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$archived]);
        $customers = $stmt->fetchAll();
        sendResponse(true, '', $customers);
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
