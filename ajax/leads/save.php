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
    if (!hasPermission('edit_leads')) sendResponse(false, 'Forbidden');
} else {
    if (!hasPermission('add_leads')) sendResponse(false, 'Forbidden');
}

$firstName = trim($input['first_name'] ?? $_POST['first_name'] ?? '');
$lastName = trim($input['last_name'] ?? $_POST['last_name'] ?? '');
$company = trim($input['company'] ?? $_POST['company'] ?? '');
$email = trim($input['email'] ?? $_POST['email'] ?? '');
$phone = trim($input['phone'] ?? $_POST['phone'] ?? '');
$status = $input['status'] ?? $_POST['status'] ?? 'new';
$source = $input['source'] ?? $_POST['source'] ?? '';
$expectedValue = floatval($input['expected_value'] ?? $_POST['expected_value'] ?? 0);
$currency = $input['currency'] ?? $_POST['currency'] ?? 'USD';
$notes = trim($input['notes'] ?? $_POST['notes'] ?? '');

$rawAssignedTo = $input['assigned_to'] ?? $_POST['assigned_to'] ?? null;
$rawCustomerId = $input['customer_id'] ?? $_POST['customer_id'] ?? null;

$assignedTo = !empty($rawAssignedTo) ? intval($rawAssignedTo) : $_SESSION['user_id'];
$customerId = !empty($rawCustomerId) ? intval($rawCustomerId) : null;

if (empty($firstName) && empty($company)) {
    sendResponse(false, 'First Name or Company Name is required');
}

try {
    $displayName = generateUniqueDisplayName($pdo, $firstName, $lastName, $company);

    if ($id) {
        $sql = "UPDATE leads SET display_name=?, first_name=?, last_name=?, company=?, email=?, phone=?, status=?, source=?, expected_value=?, currency=?, notes=?, assigned_to=?, customer_id=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$displayName, $firstName, $lastName, $company, $email, $phone, $status, $source, $expectedValue, $currency, $notes, $assignedTo, $customerId, $id]);
        sendResponse(true, 'Lead updated successfully', ['id' => $id]);
    } else {
        $leadCode = 'LD-' . strtoupper(substr(uniqid(), -5));
        $createdBy = $_SESSION['user_id'];
        $sql = "INSERT INTO leads (customer_id, lead_code, display_name, first_name, last_name, email, phone, company, status, source, expected_value, currency, notes, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customerId, $leadCode, $displayName, $firstName, $lastName, $email, $phone, $company, $status, $source, $expectedValue, $currency, $notes, $assignedTo, $createdBy]);
        $newId = $pdo->lastInsertId();
        
        if ($assignedTo && $assignedTo != $_SESSION['user_id']) {
            createNotification($pdo, $assignedTo, 'New Lead Assigned', "You were assigned a new lead: {$displayName}", '/crm/leads', 'primary', true);
        }

        sendResponse(true, 'Lead created successfully', ['id' => $newId]);
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
