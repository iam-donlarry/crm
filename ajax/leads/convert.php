<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) sendResponse(false, 'Unauthorized');

if (!hasPermission('convert_leads')) sendResponse(false, 'Forbidden: Insufficient permissions');

$input = getJsonInput();
$leadId = $input['lead_id'] ?? $_POST['lead_id'] ?? null;

if (!$leadId) sendResponse(false, 'Lead ID is required');

try {
    $pdo->beginTransaction();

    // Fetch lead details
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();

    if (!$lead) {
        $pdo->rollBack();
        sendResponse(false, 'Lead not found');
    }

    $customerId = $lead['customer_id'];

    // Create Customer if lead is not linked to existing customer
    if (!$customerId) {
        $customerCode = 'CUS-' . strtoupper(substr(uniqid(), -5));
        $cStmt = $pdo->prepare("INSERT INTO customers (customer_code, display_name, first_name, last_name, company, email, phone, notes, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $cStmt->execute([
            $customerCode,
            $lead['display_name'],
            $lead['first_name'],
            $lead['last_name'],
            $lead['company'],
            $lead['email'],
            $lead['phone'],
            $lead['notes'],
            $lead['assigned_to']
        ]);
        $customerId = $pdo->lastInsertId();
    }

    // Mark Lead as converted
    $uStmt = $pdo->prepare("UPDATE leads SET status = 'converted', customer_id = ? WHERE id = ?");
    $uStmt->execute([$customerId, $leadId]);

    // Create Opportunity / Deal if value exists
    $dealTitle = 'Deal - ' . $lead['display_name'];
    $dStmt = $pdo->prepare("INSERT INTO deals (title, lead_id, customer_id, value, stage, currency, assigned_to, created_by) VALUES (?, ?, ?, ?, 'qualification', ?, ?, ?)");
    $dStmt->execute([
        $dealTitle,
        $leadId,
        $customerId,
        $lead['expected_value'] ?? 0.00,
        $lead['currency'] ?? 'USD',
        $lead['assigned_to'] ?? $_SESSION['user_id'],
        $_SESSION['user_id']
    ]);
    $dealId = $pdo->lastInsertId();

    $pdo->commit();
    sendResponse(true, 'Lead converted successfully', [
        'customer_id' => $customerId,
        'deal_id' => $dealId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendResponse(false, 'Conversion failed: ' . $e->getMessage());
}
