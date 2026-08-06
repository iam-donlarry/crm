<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

$input = getJsonInput();
$id = $input['id'] ?? $_POST['id'] ?? null;

if ($id) {
    if (!hasPermission('edit_products')) sendResponse(false, 'Forbidden');
} else {
    if (!hasPermission('add_products')) sendResponse(false, 'Forbidden');
}

$name = trim($input['name'] ?? $_POST['name'] ?? '');
$code = trim($input['code'] ?? $_POST['code'] ?? '');
$unit = trim($input['unit'] ?? $_POST['unit'] ?? '');
$price = floatval($input['price'] ?? $_POST['price'] ?? 0);
$currency = $input['currency'] ?? $_POST['currency'] ?? 'USD';
$category = trim($input['category'] ?? $_POST['category'] ?? 'General');
$stock = intval($input['stock'] ?? $_POST['stock'] ?? 0);
$description = trim($input['description'] ?? $_POST['description'] ?? '');
$status = $input['status'] ?? $_POST['status'] ?? 'active';

if (empty($name)) sendResponse(false, 'Product name is required');

try {
    if ($id) {
        $sql = "UPDATE products SET name=?, code=?, unit=?, price=?, currency=?, category=?, stock=?, description=?, status=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $code, $unit, $price, $currency, $category, $stock, $description, $status, $id]);
        sendResponse(true, 'Product updated successfully', ['id' => $id]);
    } else {
        if (empty($code)) $code = 'PRD-' . strtoupper(substr(uniqid(), -5));
        $sql = "INSERT INTO products (code, name, unit, price, currency, category, stock, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code, $name, $unit, $price, $currency, $category, $stock, $description, $status]);
        $newId = $pdo->lastInsertId();
        sendResponse(true, 'Product created successfully', ['id' => $newId]);
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
