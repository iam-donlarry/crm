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
    if (!hasPermission('edit_deals')) sendResponse(false, 'Forbidden');
} else {
    if (!hasPermission('create_deals')) sendResponse(false, 'Forbidden');
}

$title = trim($input['title'] ?? $_POST['title'] ?? '');
$rawCustomerId = $input['customer_id'] ?? $_POST['customer_id'] ?? null;
$rawLeadId = $input['lead_id'] ?? $_POST['lead_id'] ?? null;
$rawAssignedTo = $input['assigned_to'] ?? $_POST['assigned_to'] ?? null;

$customerId = !empty($rawCustomerId) ? intval($rawCustomerId) : null;
$leadId = !empty($rawLeadId) ? intval($rawLeadId) : null;
$assignedTo = !empty($rawAssignedTo) ? intval($rawAssignedTo) : $_SESSION['user_id'];

$value = floatval($input['value'] ?? $_POST['value'] ?? 0);
$stage = $input['stage'] ?? $_POST['stage'] ?? 'qualification';
$currency = $input['currency'] ?? $_POST['currency'] ?? 'USD';
$exchangeRate = floatval($input['exchange_rate'] ?? $_POST['exchange_rate'] ?? 1.0);

$rawValidUntil = trim($input['valid_until'] ?? $_POST['valid_until'] ?? '');
$rawDueDate = trim($input['due_date'] ?? $_POST['due_date'] ?? '');
$validUntil = !empty($rawValidUntil) ? $rawValidUntil : null;
$dueDate = !empty($rawDueDate) ? $rawDueDate : null;

$description = trim($input['description'] ?? $_POST['description'] ?? '');
$termsConditions = trim($input['terms_conditions'] ?? $_POST['terms_conditions'] ?? '');
$products = $input['products'] ?? [];

if (empty($title)) {
    sendResponse(false, 'Deal title is required');
}

try {
    $pdo->beginTransaction();

    if ($id) {
        $sql = "UPDATE deals SET title=?, customer_id=?, lead_id=?, value=?, stage=?, currency=?, exchange_rate=?, valid_until=?, due_date=?, description=?, terms_conditions=?, assigned_to=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $customerId, $leadId, $value, $stage, $currency, $exchangeRate, $validUntil, $dueDate, $description, $termsConditions, $assignedTo, $id]);
        $dealId = $id;
    } else {
        $quoteNumber = 'QT-' . strtoupper(substr(uniqid(), -5));
        $sql = "INSERT INTO deals (title, quote_number, customer_id, lead_id, value, stage, currency, exchange_rate, valid_until, due_date, description, terms_conditions, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $quoteNumber, $customerId, $leadId, $value, $stage, $currency, $exchangeRate, $validUntil, $dueDate, $description, $termsConditions, $assignedTo, $_SESSION['user_id']]);
        $dealId = $pdo->lastInsertId();
    }

    // Save line item products if provided
    if (!empty($products) && is_array($products)) {
        $delStmt = $pdo->prepare("DELETE FROM deal_products WHERE deal_id = ?");
        $delStmt->execute([$dealId]);

        $insStmt = $pdo->prepare("INSERT INTO deal_products (deal_id, product_id, product_name, description, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($products as $p) {
            $prodId = !empty($p['product_id']) ? intval($p['product_id']) : null;
            $insStmt->execute([
                $dealId,
                $prodId,
                $p['product_name'] ?? '',
                $p['description'] ?? '',
                $p['quantity'] ?? 1,
                $p['price'] ?? 0.00,
                ($p['quantity'] ?? 1) * ($p['price'] ?? 0.00)
            ]);
        }
    }

    $pdo->commit();
    sendResponse(true, 'Deal saved successfully', ['id' => $dealId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
