<?php
session_start();
require_once 'includes/config.php';

// Handle login session from JavaScript
// Handle login session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['email']) || !isset($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit;
    }

    $email = $data['email'];
    $password = $data['password'];

    try {
        // Fetch user by email with role details
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.password, u.role, u.role_id, r.name as role_name, r.permissions 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ? AND u.is_archived = 0
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify user and password
        // Note: For migration support, we check both hashed and plain text (temporarily)
        // In production, ALWAYS used hashed.
        if ($user && ($password === $user['password'] || password_verify($password, $user['password']))) {
            
            // Re-hash password if it was plain text (auto-migrate security)
            if ($password === $user['password']) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $user['id']]);
            }

            // Resolve permission IDs to Slugs
            $permissionSlugs = [];
            $permIds = !empty($user['permissions']) ? json_decode($user['permissions'], true) : [];
            
            if (!empty($permIds)) {
                $placeholders = str_repeat('?,', count($permIds) - 1) . '?';
                $permStmt = $pdo->prepare("SELECT slug FROM permissions WHERE id IN ($placeholders)");
                $permStmt->execute($permIds);
                $permissionSlugs = $permStmt->fetchAll(PDO::FETCH_COLUMN);
            }

            // Set Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['user_role'] = $user['role']; // Keep legacy role string for backward compatibility
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['permissions'] = $permissionSlugs;
            
            echo json_encode([
                'success' => true, 
                'user' => [
                    'id' => $user['id'],
                    'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role'],
                    'role_id' => $user['role_id'],
                    'permissions' => $permissionSlugs,
                    'email' => $user['email']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        }

    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}
?>
