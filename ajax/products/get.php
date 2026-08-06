<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

if (!hasPermission('view_products')) sendResponse(false, 'Forbidden');

try {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $product = $stmt->fetch();
        if ($product) sendResponse(true, '', $product);
        else sendResponse(false, 'Product not found');
    } else {
        $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;
        $stmt = $pdo->prepare("SELECT * FROM products WHERE is_archived = ? ORDER BY name ASC");
        $stmt->execute([$archived]);
        $products = $stmt->fetchAll();
        sendResponse(true, '', $products);
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
