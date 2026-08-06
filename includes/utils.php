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
    // If customerId is provided, update address
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
    } 
    // Otherwise create new customer
    else {
        // Validation: Need at least Name or Company
        if (empty($contactData['first_name']) && empty($contactData['company'])) {
            return null; // Cannot create
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
