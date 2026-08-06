<?php
// Standard JSON Response Helper
function sendResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Get JSON Input Helper
function getJsonInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    return $input;
}

// Method Check Helper
function requireMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        http_response_code(405); // Method Not Allowed
        sendResponse(false, 'Method Not Allowed');
    }
}

function generateUniqueDisplayName($pdo, $firstName, $lastName, $company) {
    $baseName = '';
    if (!empty($company)) {
        $baseName = $company;
    } elseif (!empty($firstName)) {
        $baseName = trim($firstName . ' ' . $lastName);
    } else {
        $baseName = 'Customer';
    }

    $displayName = $baseName;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE display_name = ?");
        $stmt->execute([$displayName]);
        if ($stmt->fetchColumn() == 0) {
            return $displayName;
        }
        $displayName = $baseName . ' ' . $counter;
        $counter++;
    }
}

function createOrUpdateCustomer($pdo, $customerId, $contactData, $addressData, $assignedTo = null) {
    if ($customerId) {
        $fields = [];
        $values = [];
        
        foreach ($addressData as $key => $val) {
            $fields[] = "$key = ?";
            $values[] = $val;
        }
        
        if (!empty($fields)) {
            $values[] = $customerId;
            $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        }
        return $customerId;
    } else {
        if (empty($contactData['first_name']) && empty($contactData['company'])) {
            return null;
        }

        $code = 'CUS-' . strtoupper(substr(uniqid(), -5));
        $displayName = generateUniqueDisplayName($pdo, $contactData['first_name'], $contactData['last_name'], $contactData['company']);
        
        $sql = "INSERT INTO customers (customer_code, first_name, last_name, company, email, phone, address, city, state, zip, country, display_name, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $code,
            $contactData['first_name'] ?? '',
            $contactData['last_name'] ?? '',
            $contactData['company'] ?? '',
            $contactData['email'] ?? '',
            $contactData['phone'] ?? '',
            $addressData['address'] ?? '',
            $addressData['city'] ?? '',
            $addressData['state'] ?? '',
            $addressData['zip'] ?? '',
            $addressData['country'] ?? '',
            $displayName,
            $assignedTo ?? $_SESSION['user_id']
        ]);
        
        return $pdo->lastInsertId();
    }
}

/**
 * Create In-App Notification and optionally dispatch Email Alert
 */
function createNotification($pdo, $userId, $title, $message, $link = null, $type = 'info', $sendEmail = true) {
    if (empty($userId)) return false;

    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, type, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$userId, $title, $message, $link, $type]);

        if ($sendEmail) {
            require_once __DIR__ . '/mailer.php';
            $userStmt = $pdo->prepare("SELECT email, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''), name, email) as name FROM users WHERE id = ? AND is_archived = 0");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($user && !empty($user['email'])) {
                sendCRMEmailNotification($user['email'], $user['name'], $title, $title, '<p>' . nl2br(htmlspecialchars($message)) . '</p>', 'View in CRM', $link ?: '/crm/dashboard');
            }
        }
        return true;
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Unread Notification Count
 */
function getUnreadNotificationCount($pdo, $userId) {
    if (empty($userId)) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() ?: 0;
}

/**
 * Fetch Recent User Notifications
 */
function getUserNotifications($pdo, $userId, $limit = 8) {
    if (empty($userId)) return [];
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . intval($limit));
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
