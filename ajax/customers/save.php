<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized');
}

$input = getJsonInput();
$id = !empty($input['id'] ?? $_POST['id'] ?? null) ? intval($input['id'] ?? $_POST['id']) : null;

if ($id) {
    if (!hasPermission('edit_customers')) sendResponse(false, 'Forbidden: Cannot edit customer');
} else {
    if (!hasPermission('add_customers')) sendResponse(false, 'Forbidden: Cannot add customer');
}

$firstName = trim($input['first_name'] ?? $_POST['first_name'] ?? '');
$lastName = trim($input['last_name'] ?? $_POST['last_name'] ?? '');
$company = trim($input['company'] ?? $_POST['company'] ?? '');
$email = trim($input['email'] ?? $_POST['email'] ?? '');
$phone = trim($input['phone'] ?? $_POST['phone'] ?? '');
$address = trim($input['address'] ?? $_POST['address'] ?? '');
$city = trim($input['city'] ?? $_POST['city'] ?? '');
$state = trim($input['state'] ?? $_POST['state'] ?? '');
$zip = trim($input['zip'] ?? $_POST['zip'] ?? '');
$country = trim($input['country'] ?? $_POST['country'] ?? '');
$notes = trim($input['notes'] ?? $_POST['notes'] ?? '');

$rawAssignedTo = $input['assigned_to'] ?? $_POST['assigned_to'] ?? null;
$assignedTo = !empty($rawAssignedTo) ? intval($rawAssignedTo) : $_SESSION['user_id'];

if (empty($firstName) && empty($company)) {
    sendResponse(false, 'Either First Name or Company Name is required');
}

try {
    if ($id) {
        $sql = "UPDATE customers SET first_name=?, last_name=?, company=?, email=?, phone=?, address=?, city=?, state=?, zip=?, country=?, notes=?, assigned_to=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$firstName, $lastName, $company, $email, $phone, $address, $city, $state, $zip, $country, $notes, $assignedTo, $id]);
        sendResponse(true, 'Customer updated successfully', ['id' => $id]);
    } else {
        $customerCode = 'CUS-' . strtoupper(substr(uniqid(), -5));
        $displayName = generateUniqueDisplayName($pdo, $firstName, $lastName, $company);
        
        $sql = "INSERT INTO customers (customer_code, display_name, first_name, last_name, company, email, phone, address, city, state, zip, country, notes, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customerCode, $displayName, $firstName, $lastName, $company, $email, $phone, $address, $city, $state, $zip, $country, $notes, $assignedTo]);
        $newId = $pdo->lastInsertId();
        sendResponse(true, 'Customer created successfully', ['id' => $newId]);
    }
} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage());
}
