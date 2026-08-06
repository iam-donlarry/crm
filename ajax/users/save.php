<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
header('Content-Type: application/json');

if (!hasPermission('edit_users') && $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$id = intval($data['id'] ?? 0);
$firstName = trim($data['first_name'] ?? '');
$lastName = trim($data['last_name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$roleId = intval($data['role_id'] ?? 0);
$password = $data['password'] ?? '';

if (empty($firstName) || empty($lastName) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'First Name, Last Name, and Email are required.']);
    exit();
}

try {
    if ($id > 0) {
        // Edit existing user
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role_id = ?, password = ? WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $email, $phone, $roleId ?: null, $hash, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role_id = ? WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $email, $phone, $roleId ?: null, $id]);
        }
        echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
    } else {
        // Create new user
        if (empty($password)) {
            $password = 'Password123!';
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, role_id, password, is_archived) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$firstName, $lastName, $email, $phone, $roleId ?: null, $hash]);
        echo json_encode(['success' => true, 'message' => 'New staff member added successfully!']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
