<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$firstName = trim($data['first_name'] ?? '');
$lastName = trim($data['last_name'] ?? '');
$phone = trim($data['phone'] ?? '');

$currentPassword = $data['current_password'] ?? '';
$newPassword = $data['new_password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

if (empty($firstName) || empty($lastName)) {
    echo json_encode(['success' => false, 'message' => 'First Name and Last Name are required.']);
    exit();
}

try {
    // 1. If password update is requested
    if (!empty($newPassword)) {
        if (empty($currentPassword)) {
            echo json_encode(['success' => false, 'message' => 'Please provide your current password to set a new password.']);
            exit();
        }

        if ($newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'New password and confirmation password do not match.']);
            exit();
        }

        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
            exit();
        }

        // Verify current password
        $userStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userRow || !password_verify($currentPassword, $userRow['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit();
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, password = ? WHERE id = ?");
        $updateStmt->execute([$firstName, $lastName, $phone, $newHash, $userId]);
    } else {
        // Profile update without password change
        $updateStmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
        $updateStmt->execute([$firstName, $lastName, $phone, $userId]);
    }

    // Update Session user name
    $fullName = trim($firstName . ' ' . $lastName);
    $_SESSION['user_name'] = $fullName;

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
