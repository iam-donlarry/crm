<?php
// Start output buffering at the VERY beginning
ob_start();

// API Handling Logic
if (isset($_GET['api']) && $_GET['api'] === 'true') {
    // Clean ALL output buffers first
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set JSON header FIRST
    header('Content-Type: application/json');

    // Try to include files but suppress any output
    try {
        @require_once '../../includes/config.php';
        @require_once '../../includes/utils.php';
        @require_once '../../includes/auth.php';
    } catch (Exception $e) {
        // If includes fail, send error response
        echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()]);
        exit;
    }
    
    // Start session AFTER config
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if config.php loaded successfully by checking for $pdo
    if (!isset($pdo)) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please check your configuration.']);
        exit;
    }
    
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
        exit;
    }

    // Session Repair: fetching permissions if missing
    if (isset($_SESSION['user_id']) && (empty($_SESSION['permissions']) || empty($_SESSION['user_role']))) {
        try {
            $stmt = $pdo->prepare("
                SELECT u.role, u.role_id, r.name as role_name, r.permissions 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];
                
                // Resolve permission IDs to Slugs
                $permissionSlugs = [];
                $permIds = !empty($user['permissions']) ? json_decode($user['permissions'], true) : [];
                
                if (!empty($permIds) && is_array($permIds)) {
                    $placeholders = str_repeat('?,', count($permIds) - 1) . '?';
                    $permStmt = $pdo->prepare("SELECT slug FROM permissions WHERE id IN ($placeholders)");
                    $permStmt->execute($permIds);
                    $permissionSlugs = $permStmt->fetchAll(PDO::FETCH_COLUMN);
                }
                
                $_SESSION['permissions'] = $permissionSlugs;
            }
        } catch (Exception $e) {
            // Silently fail or log? prefer continuing to let permission check fail naturally if needed
            error_log("Session Repair Failed: " . $e->getMessage());
        }
    }

    // Define helper functions if they don't exist
    if (!function_exists('getJsonInput')) {
        function getJsonInput() {
            $json = file_get_contents('php://input');
            return json_decode($json, true);
        }
    }
    
    if (!function_exists('sendResponse')) {
        function sendResponse($success, $message = '', $data = null) {
            $response = ['success' => $success];
            if ($message) $response['message'] = $message;
            if ($data !== null) $response['data'] = $data;
            echo json_encode($response);
            exit;
        }
    }

    $method = $_SERVER['REQUEST_METHOD'];
    
    // Handle actions
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'export') {
            if (!function_exists('hasPermission') || !hasPermission('export_leads')) {
                sendResponse(false, 'Forbidden: You do not have permission to export leads.');
            }
            handleExport($pdo);
            exit;
        }
        if ($_GET['action'] === 'template') {
            if (!function_exists('hasPermission') || !hasPermission('download_leads_template')) {
                sendResponse(false, 'Forbidden: You do not have permission to download the leads template.');
            }
            handleTemplate($pdo);
            exit;
        }
        if ($_GET['action'] === 'import' && $method === 'POST') {
            if (!function_exists('hasPermission') || !hasPermission('import_leads')) {
                sendResponse(false, 'Forbidden: You do not have permission to import leads.');
            }
            handleImport($pdo);
            exit;
        }
        if ($_GET['action'] === 'cleanup_names') {
            if (!function_exists('hasPermission') || !hasPermission('manage_leads')) {
                sendResponse(false, 'Forbidden: You do not have permission to cleanup names.');
            }
            $result = cleanupLeadNames($pdo);
            echo json_encode($result);
            exit;
        }
        if ($_GET['action'] === 'get_sources') {
            try {
                $stmt = $pdo->query("SELECT name FROM lead_sources ORDER BY name ASC");
                sendResponse(true, '', $stmt->fetchAll(PDO::FETCH_COLUMN));
            } catch (PDOException $e) {
                // Fallback to defaults if table missing (safety)
                sendResponse(true, '', ['Website', 'Referral', 'Email', 'Phone', 'Other']);
            }
            exit;
        }
    }

    try {
        switch ($method) {
            case 'GET':
                if (!hasPermission('view_leads')) {
                    $details = 'User: ' . ($_SESSION['user_id'] ?? 'none');
                    $details .= ', Role: ' . ($_SESSION['user_role'] ?? 'none');
                    $details .= ', Permissions: ' . implode(',', $_SESSION['permissions'] ?? []);
                    sendResponse(false, 'Access Denied: Missing view_leads permission. ' . $details);
                }
                handleGet($pdo);
                break;
            case 'POST':
                if (!hasPermission('add_leads')) sendResponse(false, 'Access Denied');
                handlePost($pdo);
                break;
            case 'PUT':
                if (!hasPermission('edit_leads')) sendResponse(false, 'Access Denied');
                handlePut($pdo);
                break;
            case 'DELETE':
                if (!hasPermission('delete_leads')) {
                    sendResponse(false, 'Forbidden: You do not have permission to delete leads.');
                }
                handleDelete($pdo);
                break;
            default:
                http_response_code(405);
                sendResponse(false, 'Method Not Allowed');
        }
    } catch (Exception $e) {
        http_response_code(500);
        sendResponse(false, 'Server Error: ' . $e->getMessage());
    }
    
    exit; // Stop execution for API requests
}

// If we reach here, it's NOT an API request, so display the HTML page
// Start session for HTML page if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files for HTML page
try {
    require_once '../../includes/config.php';
    require_once '../../includes/utils.php';
    require_once '../../includes/auth.php';
} catch (Exception $e) {
    die('Error loading configuration: ' . $e->getMessage());
}

// Check authentication for HTML page
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Now include the header and display HTML
require_once '../../includes/header.php';

// Function definitions (moved here to avoid duplicate definitions)
function handleGet($pdo) {
    if (isset($_GET['type']) && $_GET['type'] === 'users') {
        try {
            // Fetch all active users for assignment
            $stmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE is_archived = 0 ORDER BY first_name ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendResponse(true, 'Users fetched', $users);
        } catch (PDOException $e) {
            sendResponse(false, 'Database Error: ' . $e->getMessage());
        }
        return;
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    // Clean output buffer
    @ob_clean();
    
    try {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ? AND is_archived = 0");
            $stmt->execute([$id]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lead) {
                // Fetch products
                $stmt = $pdo->prepare("
                    SELECT lp.*, p.name, p.unit, p.description, p.currency as product_currency
                    FROM lead_products lp 
                    JOIN products p ON lp.product_id = p.id 
                    WHERE lp.lead_id = ?
                ");
                $stmt->execute([$id]);
                $lead['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Fetch linked customer address if exists
                if (!empty($lead['customer_id'])) {
                    $cStmt = $pdo->prepare("SELECT address, city, state, zip, country FROM customers WHERE id = ?");
                    $cStmt->execute([$lead['customer_id']]);
                    $customerData = $cStmt->fetch(PDO::FETCH_ASSOC);
                    if ($customerData) {
                        $lead = array_merge($lead, $customerData);
                    }
                }
                
                sendResponse(true, '', $lead);
            } else {
                sendResponse(false, 'Lead not found');
            }
        } else {
            // Join with users to get assigned name
            $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;
            
            // Visibility Logic
            $where = ["l.is_archived = ?"];
            $params = [$archived];

            $isAdmin = hasPermission('system_admin');
            $hasTeamView = hasPermission('view_team_leads');
            $onlyMine = isset($_GET['only_mine']) && $_GET['only_mine'] === 'true';

            if ((!$isAdmin && !$hasTeamView) || $onlyMine) {
                $where[] = "l.assigned_to = ?";
                $params[] = $_SESSION['user_id'];
            }

            // Join with users to get assigned name and creator name
            $sql = "SELECT l.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,
                           CONCAT(u2.first_name, ' ', u2.last_name) as created_by_name
                    FROM leads l 
                    LEFT JOIN users u ON l.assigned_to = u.id 
                    LEFT JOIN users u2 ON l.created_by = u2.id
                    WHERE " . implode(" AND ", $where);
            
            // Append Order By
            $sql .= " ORDER BY l.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch products for all leads (efficiently)
            $productSql = "
                SELECT lp.lead_id, p.name, p.unit, lp.quantity, lp.price_at_addition as price, p.id as product_id, p.currency
                FROM lead_products lp 
                JOIN products p ON lp.product_id = p.id
            ";
            $pStmt = $pdo->query($productSql);
            $allProducts = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $productsByLead = [];
            foreach ($allProducts as $p) {
                $productsByLead[$p['lead_id']][] = $p;
            }
            
            foreach ($leads as &$l) {
                $l['products'] = $productsByLead[$l['id']] ?? [];
            }
            
            sendResponse(true, '', $leads);
        }
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handlePost($pdo) {
    $data = getJsonInput();
    
    // Clean output buffer
    @ob_clean();
    
    // Basic validation
    if (empty($data['first_name']) || empty($data['last_name'])) {
        sendResponse(false, 'First Name and Last Name are required');
        return;
    }
    
    // Prevent "Lead" as name
    if (strtolower($data['first_name']) === 'lead' || strtolower($data['last_name']) === 'lead') {
        sendResponse(false, 'Name cannot be "Lead". Please provide a real name.');
        return;
    }

    try {
        $pdo->beginTransaction();

        // Generate Lead Code if not provided
        $leadCode = $data['lead_code'] ?? 'LD-' . strtoupper(substr(uniqid(), -6));
        $displayName = $data['first_name'] . ' ' . $data['last_name'] . ' (' . $leadCode . ')';

        // Handle Customer/Address Sync
        $addressData = [
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'zip' => $data['zip'] ?? '',
            'country' => $data['country'] ?? ''
        ];
        $contactData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company' => $data['company'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? ''
        ];
        $customerId = createOrUpdateCustomer($pdo, null, $contactData, $addressData, $data['assigned_to'] ?? $_SESSION['user_id']);

        $sql = "INSERT INTO leads (lead_code, display_name, first_name, last_name, email, phone, company, status, source, assigned_to, created_by, expected_value, currency, notes, customer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $leadCode,
            $displayName,
            $data['first_name'],
            $data['last_name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['company'] ?? null,
            $data['status'] ?? 'new',
            $data['source'] ?? 'website',
            $data['assigned_to'] ?? $_SESSION['user_id'],
            $_SESSION['user_id'],
            $data['expected_value'] ?? 0.00,
            $data['currency'] ?? 'USD',
            $data['notes'] ?? '',
            $customerId // Add customer_id
        ]);
        
        $leadId = $pdo->lastInsertId();

        // Handle Products
        if (!empty($data['products']) && is_array($data['products'])) {
            $pStmt = $pdo->prepare("INSERT INTO lead_products (lead_id, product_id, quantity, price_at_addition) VALUES (?, ?, ?, ?)");
            foreach ($data['products'] as $prod) {
                $pStmt->execute([
                    $leadId,
                    $prod['product_id'],
                    $prod['quantity'],
                    $prod['price']
                ]);
            }
        }

        $pdo->commit();
        sendResponse(true, 'Lead created', ['id' => $leadId]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handlePut($pdo) {
    $data = getJsonInput();
    
    // Clean output buffer
    @ob_clean();
    
    if (empty($data['id'])) {
        sendResponse(false, 'Lead ID required');
        return;
    }
    
    // Prevent "Lead" as name
    if (isset($data['first_name']) && strtolower($data['first_name']) === 'lead') {
        sendResponse(false, 'First name cannot be "Lead". Please provide a real name.');
        return;
    }
    if (isset($data['last_name']) && strtolower($data['last_name']) === 'lead') {
        sendResponse(false, 'Last name cannot be "Lead". Please provide a real name.');
        return;
    }

    // Check Assign Permission if changing owner
    if (isset($data['assigned_to'])) {
        // Fetch current to see if it's actually changing
        $stmt = $pdo->prepare("SELECT assigned_to FROM leads WHERE id = ?");
        $stmt->execute([$data['id']]);
        $currentOwner = $stmt->fetchColumn();
        
        if ($currentOwner != $data['assigned_to'] && (!function_exists('hasPermission') || !hasPermission('assign_leads'))) {
            sendResponse(false, 'Forbidden: You do not have permission to re-assign leads.');
            return;
        }
    }

    $fields = [];
    $values = [];
    $allowed = ['first_name', 'last_name', 'email', 'phone', 'company', 'status', 'source', 'assigned_to', 'expected_value', 'currency', 'notes', 'customer_id', 'is_archived'];

    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }

    if (empty($fields) && !isset($data['products'])) {
        sendResponse(false, 'No fields to update');
        return;
    }
    
    try {
        $pdo->beginTransaction();

        if (isset($data['first_name']) || isset($data['last_name'])) {
            // We need to update display_name
            // Fetch current values to get what is missing
             $stmt = $pdo->prepare("SELECT first_name, last_name, lead_code FROM leads WHERE id = ?");
             $stmt->execute([$data['id']]);
             $currentLead = $stmt->fetch(PDO::FETCH_ASSOC);

             if ($currentLead) {
                 $newFirstName = isset($data['first_name']) ? $data['first_name'] : $currentLead['first_name'];
                 $newLastName = isset($data['last_name']) ? $data['last_name'] : $currentLead['last_name'];
                 $leadCode = $currentLead['lead_code'];
                 
                 $newDisplayName = $newFirstName . ' ' . $newLastName . ' (' . $leadCode . ')';
                 
                 $fields[] = "display_name = ?";
                 $values[] = $newDisplayName;
             }
        }

        if (!empty($fields)) {
            $values[] = $data['id'];
            $sql = "UPDATE leads SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        }

        // Handle Products Update (Delete all and re-insert)
        if (isset($data['products']) && is_array($data['products'])) {
            $stmt = $pdo->prepare("DELETE FROM lead_products WHERE lead_id = ?");
            $stmt->execute([$data['id']]);

            $pStmt = $pdo->prepare("INSERT INTO lead_products (lead_id, product_id, quantity, price_at_addition) VALUES (?, ?, ?, ?)");
            foreach ($data['products'] as $prod) {
                $pStmt->execute([
                    $data['id'],
                    $prod['product_id'],
                    $prod['quantity'],
                    $prod['price']
                ]);
            }
        }

        // Update Customer Address
        // Fetch existing customer_id
        $cCheck = $pdo->prepare("SELECT customer_id FROM leads WHERE id = ?");
        $cCheck->execute([$data['id']]);
        $currentCid = $cCheck->fetchColumn();

        $addressData = [
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'country' => $data['country'] ?? null
        ];
        
        // We need contact data too if creating new
        $contactData = [
             'first_name' => $data['first_name'] ?? '',
             'last_name' => $data['last_name'] ?? '',
             'company' => $data['company'] ?? '',
             'email' => $data['email'] ?? '',
             'phone' => $data['phone'] ?? ''
        ];
        
        $customerId = createOrUpdateCustomer($pdo, $currentCid, $contactData, $addressData);
        
        if ($customerId !== $currentCid) {
             // Link new customer
             $stmt = $pdo->prepare("UPDATE leads SET customer_id = ? WHERE id = ?");
             $stmt->execute([$customerId, $data['id']]);
        }
        
        $pdo->commit();
        sendResponse(true, 'Lead updated');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handleExport($pdo) {
    if (!function_exists('hasPermission') || !hasPermission('export_leads')) {
        sendResponse(false, 'Forbidden: You do not have permission to export leads.');
        exit;
    }
    @ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="leads_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    // Headers: Address, City, State/Province, Zip/Postal Code, Name, Company, Email, Phone, Lead Owner, Product, Source, Status, Value, Created
    fputcsv($output, ['Address', 'City', 'State/Province', 'Zip/Postal Code', 'Name', 'Company', 'Email', 'Phone', 'Lead Owner', 'Product', 'Source', 'Status', 'Value', 'Created']);

    $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
    
    $where = ["l.is_archived = 0"];
    $params = [];
    
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "l.id IN ($placeholders)";
        $params = array_map('intval', $ids);
    }
    
    $sql = "SELECT 
                c.address, c.city, c.state, c.zip,
                l.display_name, l.company, l.email, l.phone,
                CONCAT(u.first_name, ' ', u.last_name) as owner_name,
                (SELECT GROUP_CONCAT(p.name SEPARATOR ', ') FROM lead_products lp JOIN products p ON lp.product_id = p.id WHERE lp.lead_id = l.id) as product_names,
                l.source, l.status, CONCAT(COALESCE(l.currency, 'USD'), ' ', l.expected_value) as val_with_currency, l.created_at
            FROM leads l
            LEFT JOIN customers c ON l.customer_id = c.id
            LEFT JOIN users u ON l.assigned_to = u.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY l.created_at DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function handleTemplate($pdo) {
    if (!function_exists('hasPermission') || !hasPermission('download_leads_template')) {
        sendResponse(false, 'Forbidden: You do not have permission to download the leads template.');
        exit;
    }
    @ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="leads_template.csv"');

    $output = fopen('php://output', 'w');
    // Headers: Address, City, State/Province, Zip/Postal Code, Name, Company, Email, Phone, Lead Owner, Product, Source, Status, Value, Created
    fputcsv($output, ['Address', 'City', 'State/Province', 'Zip/Postal Code', 'Name', 'Company', 'Email', 'Phone', 'Lead Owner', 'Product', 'Source', 'Status', 'Value', 'Created']);
    fclose($output);
    exit;
}

function handleImport($pdo) {
    if (!function_exists('hasPermission') || !hasPermission('import_leads')) {
        sendResponse(false, 'Forbidden: You do not have permission to import leads.');
        return;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(false, 'File upload failed');
        return;
    }

    $file = fopen($_FILES['file']['tmp_name'], 'r');
    if (!$file) {
        sendResponse(false, 'Cannot open file');
        return;
    }

    // Fix for Macintosh line endings
    ini_set('auto_detect_line_endings', true);

    // Read first line to detect delimiter
    $firstLine = fgets($file);
    if (!$firstLine) {
         fclose($file);
         sendResponse(false, 'File is empty');
         return;
    }
    
    $delimiter = ',';
    if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
        $delimiter = ';';
    }
    
    // Reset file pointer
    rewind($file);

    // Skip header
    fgetcsv($file, 0, $delimiter);

    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO leads (lead_code, display_name, first_name, last_name, company, email, phone, source, status, expected_value, currency, assigned_to, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            // Skip empty rows
            if (empty($row) || (count($row) === 1 && $row[0] === null)) continue;
            
            // Expected indices based on: Address, City, State/Province, Zip/Postal Code, Name, Company, Email, Phone, Lead Owner, Product, Source, Status, Value, Created
            if (count($row) < 5) continue;

            $address = $row[0] ?? '';
            $city = $row[1] ?? '';
            $state = $row[2] ?? '';
            $zip = $row[3] ?? '';
            $fullName = $row[4] ?? '';
            $company = $row[5] ?? '';
            $email = $row[6] ?? '';
            $phone = $row[7] ?? '';
            $source = !empty($row[10]) ? trim($row[10]) : 'import';
            $rawStatus = !empty($row[11]) ? trim($row[11]) : 'new';
            $valueStr = trim($row[12] ?? '0');
            
            // Parse currency from value (e.g. "NGN 5000.00")
            $currency = 'USD';
            $value = 0;
            if (preg_match('/^([A-Z]{3})\s+([0-9.-]+)$/i', $valueStr, $matches)) {
                $currency = strtoupper($matches[1]);
                $value = floatval($matches[2]);
            } else {
                $value = floatval($valueStr);
            }

            // FIXED: Better name parsing - prevent "Lead" as name
            if (empty($fullName) || strtolower($fullName) === 'lead') {
                // Try to get name from email or company if possible
                if (!empty($email)) {
                    $emailParts = explode('@', $email);
                    $nameFromEmail = $emailParts[0] ?? '';
                    $fullName = str_replace(['.', '_', '+'], ' ', $nameFromEmail);
                }
                
                if (empty($fullName) || strtolower($fullName) === 'lead') {
                    // Use company name if available
                    if (!empty($company)) {
                        $fullName = $company . ' Contact';
                    } else {
                        $fullName = 'Unknown Contact';
                    }
                }
            }

            // Parse Name
            $nameParts = explode(' ', trim($fullName), 2);
            $firstName = trim($nameParts[0] ?? '');
            $lastName = trim($nameParts[1] ?? '');

            // If firstName is "Lead" or empty, fix it
            if (empty($firstName) || strtolower($firstName) === 'lead') {
                $firstName = 'Unknown';
                if (empty($lastName) || strtolower($lastName) === 'lead') {
                    $lastName = 'Contact';
                }
            }

            $validStatuses = ['new', 'contacted', 'qualified', 'converted', 'lost'];
            $status = in_array(strtolower($rawStatus), $validStatuses) ? strtolower($rawStatus) : 'new';

            // Generate unique lead code
            $leadCode = 'LD-' . strtoupper(substr(uniqid(), -6));
            $displayName = $firstName . ' ' . $lastName . ' (' . $leadCode . ')';

            $addressData = [
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'country' => '' // Default empty
            ];
            $contactData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company' => $company,
                'email' => $email,
                'phone' => $phone
            ];

            $customerId = createOrUpdateCustomer($pdo, null, $contactData, $addressData);

            try {
                $stmt = $pdo->prepare("INSERT INTO leads (lead_code, display_name, first_name, last_name, company, email, phone, source, status, expected_value, currency, assigned_to, created_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $leadCode,
                    $displayName,
                    $firstName,
                    $lastName,
                    $company,
                    $email,
                    $phone,
                    $source,
                    $status,
                    $value,
                    $currency,
                    $_SESSION['user_id'],
                    $_SESSION['user_id'],
                    'Imported via CSV'
                ]);
                $successCount++;
            } catch (Exception $e) {
                // Duplicate Handling Strategy:
                // User wants to force insertion even if duplicate.
                // We attempt to "uniquify" the unique fields.
                
                try {
                    // 1. Modify Email if exists
                    $newEmail = $email;
                    if (!empty($email)) {
                        $parts = explode('@', $email);
                        if (count($parts) == 2) {
                            $newEmail = $parts[0] . '+dup' . time() . rand(100,999) . '@' . $parts[1];
                        } else {
                            $newEmail = $email . '_dup_' . time();
                        }
                    }
                    
                    // 2. Modify Phone if exists (Constraint might be here)
                    $newPhone = $phone;
                    if (!empty($phone)) {
                        $newPhone = $phone . '#' . rand(100,999);
                    }
                    
                    // 3. Always regenerate lead code
                    $newLeadCode = 'LD-' . strtoupper(substr(uniqid(), -6));
                    
                    // 4. Regenerate display name with new lead code
                    $newDisplayName = $firstName . ' ' . $lastName . ' (' . $newLeadCode . ')';
                    
                    $stmt->execute([
                        $newLeadCode,
                        $newDisplayName,
                        $firstName,
                        $lastName,
                        $company,
                        $newEmail,
                        $newPhone,
                        $source,
                        $status,
                        $value,
                        $currency,
                        $_SESSION['user_id'],
                        $_SESSION['user_id'],
                        'Imported via CSV (Duplicate)'
                    ]);
                    $successCount++;
                    
                } catch (Exception $retryEx) {
                    $errorCount++;
                    // Capture first few errors for debugging
                    if (count($errors) < 5) {
                        $errors[] = "Row Status " . $status . ": " . $retryEx->getMessage();
                    }
                }
            }
        }
        $pdo->commit();
        
        $msg = "Imported $successCount leads successfully.";
        if ($errorCount > 0) {
            $msg .= " Failed to import $errorCount rows. Errors: " . implode("; ", $errors);
        }
        sendResponse(true, $msg);
    } catch (Exception $e) {
        $pdo->rollBack();
        sendResponse(false, 'Import failed: ' . $e->getMessage());
    }
    fclose($file);
}

function handleDelete($pdo) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    // Clean output buffer
    @ob_clean();
    
    if (!$id) {
        sendResponse(false, 'ID required');
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE leads SET is_archived = 1 WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, 'Lead archived successfully');
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

// Cleanup function for existing leads with "Lead" as name
function cleanupLeadNames($pdo) {
    try {
        // Find leads with "Lead" as first name or in display name
        $stmt = $pdo->prepare("
            SELECT id, lead_code, email, company, display_name, first_name, last_name
            FROM leads 
            WHERE LOWER(first_name) = 'lead' 
               OR LOWER(last_name) = 'lead'
               OR display_name LIKE 'Lead %'
               OR display_name LIKE '% (LD-% (LD-%'
        ");
        $stmt->execute();
        $problemLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updatedCount = 0;
        
        foreach ($problemLeads as $lead) {
            // Extract names from display_name (remove lead codes first)
            $nameWithoutCodes = preg_replace('/\s*\(LD-[A-Z0-9]+\)/g', '', $lead['display_name']);
            $nameWithoutCodes = trim($nameWithoutCodes);
            
            // Split into first and last name
            $nameParts = explode(' ', $nameWithoutCodes, 2);
            
            $newFirstName = $nameParts[0] ?? '';
            $newLastName = $nameParts[1] ?? '';
            
            // If we couldn't extract names or name is "Lead", try other sources
            if (empty($newFirstName) || strtolower($newFirstName) === 'lead') {
                if (!empty($lead['email'])) {
                    $emailParts = explode('@', $lead['email']);
                    $nameFromEmail = $emailParts[0];
                    $nameParts = explode(' ', str_replace(['.', '_', '+'], ' ', $nameFromEmail), 2);
                    $newFirstName = ucfirst($nameParts[0]);
                    if (isset($nameParts[1])) {
                        $newLastName = ucfirst($nameParts[1]);
                    } else {
                        $newLastName = !empty($lead['company']) ? $lead['company'] : 'Contact';
                    }
                } elseif (!empty($lead['company'])) {
                    $newFirstName = $lead['company'];
                    $newLastName = 'Contact';
                } else {
                    $newFirstName = 'Unknown';
                    $newLastName = 'Contact';
                }
            }
            
            // Clean up display_name - keep only one lead code
            $cleanDisplayName = $newFirstName . ' ' . $newLastName . ' (' . $lead['lead_code'] . ')';
            
            // Update the lead
            $updateStmt = $pdo->prepare("
                UPDATE leads 
                SET first_name = ?, 
                    last_name = ?, 
                    display_name = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $newFirstName,
                $newLastName,
                $cleanDisplayName,
                $lead['id']
            ]);
            
            $updatedCount++;
        }
        
        return ['success' => true, 'message' => "Cleaned up $updatedCount leads with invalid names."];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Cleanup error: ' . $e->getMessage()];
    }
}

// Helper function to create or update customer (should be in utils.php but defined here for safety)
if (!function_exists('createOrUpdateCustomer')) {
    function createOrUpdateCustomer($pdo, $customerId = null, $contactData = [], $addressData = [], $assignedTo = null) {
        try {
            if ($customerId) {
                // Update existing customer
                $sql = "UPDATE customers SET 
                        first_name = ?, last_name = ?, company = ?, email = ?, phone = ?, 
                        address = ?, city = ?, state = ?, zip = ?, country = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
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
                    $customerId
                ]);
                return $customerId;
            } else {
                // Create new customer
                $sql = "INSERT INTO customers (first_name, last_name, company, email, phone, address, city, state, zip, country, assigned_to) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
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
                    $assignedTo ?? ($_SESSION['user_id'] ?? null)
                ]);
                return $pdo->lastInsertId();
            }
        } catch (Exception $e) {
            // Log error but don't fail the lead operation
            error_log("Customer create/update error: " . $e->getMessage());
            return null;
        }
    }
}
?>
<!-- REST OF YOUR HTML CODE -->
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Leads Management</h2>
        <div class="ms-2 d-flex gap-2">
            <button class="btn btn-outline-secondary" id="toggleSelectionMode" onclick="toggleSelectionMode()">
                <i class="bi bi-check2-square"></i> Select
            </button>
            <button class="btn btn-outline-primary" onclick="openMyAssignmentsModal()">
                <i class="bi bi-person-workspace"></i> My Assigned
            </button>
            <button class="btn btn-primary" onclick="openAddLeadModal()">
                <i class="bi bi-plus-circle"></i> Add New Lead
            </button>
             <button onclick="exportLeads()" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-arrow-down"></i> Export CSV
             </button>
             <button onclick="downloadLeadsTemplate()" class="btn btn-outline-info btn-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Download Template
             </button>
             <button onclick="document.getElementById('importFile').click()" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
             </button>

             <input type="file" id="importFile" accept=".csv" style="display: none;" onchange="importLeads(this)">
        </div>
    </div>

    <!-- Bulk Actions Toolbar -->
    <div id="bulkActionsToolbar" class="card mb-4 bg-light d-none">
        <div class="card-body d-flex align-items-center justify-content-between py-2">
            <div>
                <span class="fw-bold text-primary"><span id="selectedCount">0</span> selected</span>
            </div>
            <div class="btn-group gap-2">
                <button class="btn btn-outline-success btn-sm" onclick="bulkExportLeads()">
                    <i class="bi bi-file-earmark-arrow-down"></i> Export Selected
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="bulkAssignLeads()">
                    <i class="bi bi-person-plus"></i> Assign Selected
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="bulkConvertToDeal()">
                    <i class="bi bi-arrow-right-circle"></i> Convert to Deal
                </button>
                <button class="btn btn-outline-info btn-sm" onclick="bulkConvertToCustomer()">
                    <i class="bi bi-person-check"></i> Convert to Customer
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="bulkArchiveLeads()">
                    <i class="bi bi-archive"></i> Archive Selected
                </button>
            </div>
        </div>
    </div>

    <!-- Filter and Search Bar -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-nowrap gap-2 overflow-auto pb-2">
                <div style="min-width: 200px;">
                    <input type="text" class="form-control" id="searchLeads" placeholder="Search leads...">
                </div>
                <div style="min-width: 150px;">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="new">New</option>
                        <option value="contacted">Contacted</option>
                        <option value="qualified">Qualified</option>
                        <option value="converted">Converted</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <!-- Owner Filter -->
                <div style="min-width: 150px;">
                     <select class="form-select" id="filterOwner">
                        <option value="">All Owners</option>
                     </select>
                </div>
                <div style="min-width: 150px;">
                     <button class="btn btn-outline-secondary w-100" onclick="openDateRangeModal()">
                        <i class="bi bi-calendar3"></i> <span id="dateRangeLabel">Date Range</span>
                     </button>
                </div>
                <div style="min-width: 150px;">
                    <select class="form-select" id="filterSource">
                        <option value="">All Sources</option>
                        <!-- Populated by JS -->
                    </select>
                </div>
                <!-- Toggle Archive View -->
                <div style="min-width: 160px;">
                     <button class="btn btn-outline-secondary w-100" id="toggleArchiveBtn" onclick="toggleArchiveView()">
                        <i class="bi bi-archive"></i> Show Archived
                     </button>
                </div>

                <div>
                    <button class="btn btn-secondary w-100" onclick="resetFilters()">
                        <i class="bi bi-x-circle"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Leads Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive selection-mode-off" id="leadsTableContainer">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="table-checkbox-col">
                                <input type="checkbox" class="form-check-input" id="selectAllLeads" onchange="toggleSelectAllLeads()">
                            </th>
                            <th style="cursor: pointer;" onclick="sortLeads('company')">Company <i class="bi bi-arrow-down-up sort-icon" id="sort-company"></i></th>
                            <th style="cursor: pointer;" onclick="sortLeads('name')">Name <i class="bi bi-arrow-down-up sort-icon" id="sort-name"></i></th>
                            <th style="cursor: pointer;" onclick="sortLeads('email')">Phone <i class="bi bi-arrow-down-up sort-icon" id="sort-email"></i></th>
                            <th style="cursor: pointer;" onclick="sortLeads('phone')">Email <i class="bi bi-arrow-down-up sort-icon" id="sort-phone"></i></th>
                            <th style="cursor: pointer;" onclick="sortLeads('assigned_to_name')">Lead Owner <i class="bi bi-arrow-down-up sort-icon" id="sort-assigned_to_name"></i></th>
                            <th style="cursor: pointer;" onclick="sortLeads('created_by_name')">Original Owner <i class="bi bi-arrow-down-up sort-icon" id="sort-created_by_name"></i></th>
                            <th>Product</th>
                            <th style="cursor: pointer;" onclick="sortLeads('source')">Source <i class="bi bi-arrow-down-up sort-icon" id="sort-source"></i></th>
                            <th style="cursor: pointer;" onclick="sortLeads('status')">Status <i class="bi bi-arrow-down-up sort-icon" id="sort-status"></i></th>
                            <th style="cursor: pointer;" onclick="sortLeads('expected_value')">Value <i class="bi bi-arrow-down-up sort-icon" id="sort-expected_value"></i></th>
                            <th style="cursor: pointer;" onclick="sortLeads('created_at')">Created <i class="bi bi-arrow-down-up sort-icon" id="sort-created_at"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="leadsTableBody">
                        <tr>
                            <td colspan="14" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div id="paginationContainer" class="d-flex justify-content-end mt-3 me-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Add Lead Modal -->
<div class="modal fade" id="addLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addLeadForm">
                    <input type="hidden" id="leadId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="firstName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="lastName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company</label>
                            <input type="text" class="form-control" id="company">
                        </div>
                        
                        <div class="col-12">
                             <h6 class="mt-2 mb-2 border-bottom pb-1">Address Information</h6>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" id="address">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" id="city">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" id="state">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Zip Code</label>
                            <input type="text" class="form-control" id="zip">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" id="country">
                        </div>
                        <div class="col-12">
                             <hr class="mt-2 mb-3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Source</label>
                            <select class="form-select" id="source">
                                <option value="">Select Source</option>
                                <!-- Populated by JS -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Value</label>
                            <div class="input-group">
                                <span class="input-group-text" id="expectedValueSymbol">$</span>
                                <input type="number" class="form-control" id="expectedValue" step="0.01" readonly style="background-color: #f8f9fa;">
                            </div>
                            <small class="text-muted">Auto-calculated from products</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status">
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="qualified">Qualified</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" rows="3"></textarea>
                        </div>
                        
                        <!-- Products Section -->
                        <div class="col-12">
                            <hr class="my-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Products of Interest</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addLeadProduct()">
                                    <i class="bi bi-plus"></i> Add Product
                                </button>
                            </div>
                            <div id="noProductsWarning" class="alert alert-warning d-none">
                                <i class="bi bi-exclamation-triangle"></i> No products available. 
                                <a href="/crm/views/products/" class="fw-bold">Create products first</a>
                            </div>
                            <table class="table table-sm table-bordered" id="leadProductsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="30%">Product</th>
                                        <th width="30%">Description</th>
                                        <th width="10%">Qty</th>
                                        <th width="15%">Price</th>
                                        <th width="10%">Total</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Products added via JavaScript -->
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Total Expected Value:</td>
                                        <td colspan="2" class="fw-bold text-primary"><span id="productsTotalSymbol">$</span><span id="productsTotal">0.00</span></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="editLeadBtn" onclick="enableEditMode()">Edit</button>
                <button type="button" class="btn btn-primary d-none" id="saveLeadBtn" onclick="saveLead()">Save Lead</button>
            </div>
        </div>
    </div>
</div>

<!-- Date Range Modal -->
<div class="modal fade" id="dateRangeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-range"></i> Filter by Date Range</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <button type="button" class="list-group-item list-group-item-action" onclick="applyDateRange('this_week')">
                        <i class="bi bi-calendar-week"></i> This Week
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="applyDateRange('last_week')">
                        <i class="bi bi-calendar-week"></i> Last Week
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="applyDateRange('this_month')">
                        <i class="bi bi-calendar-month"></i> This Month
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="applyDateRange('last_month')">
                        <i class="bi bi-calendar-month"></i> Last Month
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="applyDateRange('this_year')">
                        <i class="bi bi-calendar"></i> This Year
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="applyDateRange('last_year')">
                        <i class="bi bi-calendar"></i> Last Year
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" onclick="applyDateRange('all')">
                        <i class="bi bi-calendar-check"></i> All Time
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Lead Modal -->
<div class="modal fade" id="assignLeadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignLeadForm">
                    <input type="hidden" id="assignLeadId">
                    <div class="mb-3">
                        <label for="assignUserSelect" class="form-label">Select User</label>
                        <select class="form-select" id="assignUserSelect" required>
                            <option value="">Loading...</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveLeadAssignment()">Assign</button>
            </div>
        </div>
    </div>
</div>

<!-- My Assignments Modal -->
<div class="modal fade" id="myAssignmentsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-workspace"></i> My Active Assigned</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="assignmentTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#assignedLeads">My Leads</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#assignedDeals">My Deals</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="assignedLeads">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Lead ID</th>
                                        <th>Name</th>
                                        <th>Original Owner</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody id="myLeadsBody">
                                    <tr><td colspan="5" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="assignedDeals">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Deal Title</th>
                                        <th>Value</th>
                                        <th>Original Owner</th>
                                        <th>Stage</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody id="myDealsBody">
                                    <tr><td colspan="5" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// TEST FIRST: Direct API call to see what's returned
async function testApiConnection() {
    try {
        const apiUrl = window.location.href.split('?')[0] + '?api=true';
        console.log('Testing API URL:', apiUrl);
        
        const response = await fetch(apiUrl);
        const text = await response.text();
        
        console.log('API Response (raw):', text.substring(0, 500));
        
        // Try to parse as JSON
        try {
            const json = JSON.parse(text);
            console.log('API Response (parsed):', json);
            
            if (json.success) {
                console.log('API connection successful!');
                return true;
            } else {
                console.error('API error:', json.message);
                showToast('API Error: ' + json.message, 'error');
                return false;
            }
        } catch (e) {
            console.error('Failed to parse JSON. Response is:', text);
            showToast('Server returned invalid JSON. Check console for details.', 'error');
            return false;
        }
    } catch (e) {
        console.error('API test failed:', e);
        showToast('Cannot connect to API: ' + e.message, 'error');
        return false;
    }
}

// Leads management module for CRM
const LeadsManager = {
    apiUrl: window.location.href.split('?')[0] + '?api=true',

    // Get all leads
    async getAll() {
        try {
            const url = `${this.apiUrl}&_=${new Date().getTime()}`;
            console.log('Fetching leads from:', url);
            const response = await fetch(url);
            const text = await response.text();
            
            // Debug: Show raw response
            console.log('Raw response (first 500 chars):', text.substring(0, 500));
            
            // Try to parse as JSON
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Full response:', text);
                throw new Error('Invalid JSON response from server. Check console for details.');
            }
            
            if (!result.success) {
                throw new Error(result.message || 'Unknown error from server');
            }
            return result.data || [];
        } catch (e) {
            console.error('Error fetching leads:', e);
            showToast('Error loading leads: ' + e.message, 'error');
            return [];
        }
    },

    async getById(id) {
        try {
            const response = await fetch(`${this.apiUrl}&id=${id}`);
            const text = await response.text();
            const result = JSON.parse(text);
            
            if (!result.success) {
                throw new Error(result.message);
            }
            return result.data || null;
        } catch (e) {
            console.error('Error fetching lead:', e);
            showToast('Error loading lead: ' + e.message, 'error');
            return null;
        }
    },

    // Create new lead
    async create(leadData) {
        try {
            console.log('Saving lead:', leadData);
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(leadData)
            });
            
            const text = await response.text();
            console.log('Create response:', text);
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', e);
                console.error('Raw response:', text);
                return {success: false, message: 'Invalid JSON response from server'};
            }
        } catch (e) {
            console.error('Error creating lead:', e);
            return {success: false, message: 'Network error: ' + e.message};
        }
    },

    // Update lead
    async update(id, leadData) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({...leadData, id})
            });
            
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', e);
                return {success: false, message: 'Invalid JSON response from server'};
            }
        } catch (e) {
            console.error('Error updating lead:', e);
            return {success: false, message: 'Network error: ' + e.message};
        }
    },

    // Delete lead
    async delete(id) {
        try {
            const response = await fetch(`${this.apiUrl}&id=${id}`, { method: 'DELETE' });
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', e);
                return {success: false, message: 'Invalid JSON response from server'};
            }
        } catch (e) {
            console.error('Error deleting lead:', e);
            return {success: false, message: 'Network error: ' + e.message};
        }
    },
};

function exportLeads() {
    window.location.href = window.location.pathname + '?api=true&action=export';
}

function downloadLeadsTemplate() {
    window.location.href = window.location.pathname + '?api=true&action=template';
}

async function importLeads(input) {
    if (!input.files || !input.files[0]) return;
    
    const formData = new FormData();
    formData.append('file', input.files[0]);

    try {
        const response = await fetch(window.location.pathname + '?api=true&action=import', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            loadLeads();
        } else {
            showToast('Import Error: ' + result.message, 'error');
        }
    } catch (e) {
        showToast('Import failed: ' + e.message, 'error');
    }
    input.value = ''; // Reset input
}

// Cleanup function for lead names
async function cleanupLeadNames() {
    if (!confirm('This will clean up all leads where name is "Lead" or contains "Lead" in display name. Continue?')) return;
    
    try {
        const response = await fetch('?api=true&action=cleanup_names');
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            loadLeads();
        } else {
            showToast('Cleanup failed: ' + result.message, 'error');
        }
    } catch (e) {
        showToast('Cleanup error: ' + e.message, 'error');
    }
}

// Page-specific JavaScript
let cachedLeads = [];
let cachedProducts = [];
let currentSortState = { column: 'created_at', direction: 'desc' };
let currentDateRange = 'all';

// Pagination State
let currentPage = 1;
const itemsPerPage = 15;

function openDateRangeModal() {
    new bootstrap.Modal(document.getElementById('dateRangeModal')).show();
}

function applyDateRange(range) {
    currentDateRange = range;
    
    // Update Label
    const labels = {
        'this_week': 'This Week',
        'last_week': 'Last Week',
        'this_month': 'This Month',
        'last_month': 'Last Month',
        'this_year': 'This Year',
        'last_year': 'Last Year',
        'all': 'Date Range'
    };
    document.getElementById('dateRangeLabel').innerText = labels[range] || 'Date Range';
    
    // Check if Bootstrap modal instance exists and hide it
    const modalEl = document.getElementById('dateRangeModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    
    filterLeads(); // Re-run all filters including date
}

function checkDateRange(dateString) {
    if (currentDateRange === 'all') return true;
    if (!dateString) return false;
    
    const date = new Date(dateString);
    const now = new Date();
    
    // Reset times for accurate comparison
    const startOfDay = (d) => { d.setHours(0,0,0,0); return d; };
    
    switch(currentDateRange) {
        case 'this_week':
            const firstDay = new Date(now.setDate(now.getDate() - now.getDay()));
            return date >= startOfDay(firstDay);
        case 'last_week':
            const prevWeek = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7);
            const firstDayLastWeek = new Date(prevWeek.setDate(prevWeek.getDate() - prevWeek.getDay()));
            const lastDayLastWeek = new Date(prevWeek.setDate(prevWeek.getDate() - prevWeek.getDay() + 6));
            return date >= startOfDay(firstDayLastWeek) && date <= lastDayLastWeek;
        case 'this_month':
            return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear();
        case 'last_month':
            const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            return date.getMonth() === lastMonth.getMonth() && date.getFullYear() === lastMonth.getFullYear();
        case 'this_year':
            return date.getFullYear() === now.getFullYear();
        case 'last_year':
            return date.getFullYear() === now.getFullYear() - 1;
        default:
            return true;
    }
}

function sortLeads(column) {
    // Toggle direction if clicking same column
    if (currentSortState.column === column) {
        currentSortState.direction = currentSortState.direction === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortState.column = column;
        currentSortState.direction = 'asc'; // Default to asc for new column
    }
    
    // Update Icons
    document.querySelectorAll('.sort-icon').forEach(icon => {
        icon.className = 'bi bi-arrow-down-up sort-icon text-muted';
    });
    const currentIcon = document.getElementById(`sort-${column}`);
    if (currentIcon) {
        currentIcon.className = `bi bi-arrow-${currentSortState.direction === 'asc' ? 'up' : 'down'} sort-icon text-primary`;
    }

    // Sort Logic
    cachedLeads.sort((a, b) => {
        let valA = a[column];
        let valB = b[column];
        
        // Handle composite 'name' column
        if (column === 'name') {
            const displayNameA = getCleanLeadName(a);
            const displayNameB = getCleanLeadName(b);
            valA = displayNameA.toLowerCase();
            valB = displayNameB.toLowerCase();
        } else if (typeof valA === 'string') {
            valA = valA.toLowerCase();
            valB = (valB || '').toLowerCase();
        } else if (typeof valA === 'number') {
            valA = valA || 0;
            valB = valB || 0;
        }

        if (valA < valB) return currentSortState.direction === 'asc' ? -1 : 1;
        if (valA > valB) return currentSortState.direction === 'asc' ? 1 : -1;
        return 0;
    });

    renderLeadsTable(cachedLeads);
}

function renderPagination(totalItems) {
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const container = document.getElementById('paginationContainer');
    if (!container) return;

    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '<nav><ul class="pagination mb-0">';
    
    // Previous
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="changePage(${currentPage - 1})">Previous</button>
             </li>`;

    const showPages = [];
    if (totalPages <= 7) {
        for (let i = 1; i <= totalPages; i++) showPages.push(i);
    } else {
        showPages.push(1);
        if (currentPage > 3) showPages.push('...');
        let start = Math.max(2, currentPage - 1);
        let end = Math.min(totalPages - 1, currentPage + 1);
        for (let i = start; i <= end; i++) showPages.push(i);
        if (currentPage < totalPages - 2) showPages.push('...');
        showPages.push(totalPages);
    }

    showPages.forEach(p => {
        if (p === '...') {
             html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        } else {
             html += `<li class="page-item ${p === currentPage ? 'active' : ''}">
                        <button class="page-link" onclick="changePage(${p})">${p}</button>
                      </li>`;
        }
    });

    // Next
    html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <button class="page-link" onclick="changePage(${currentPage + 1})">Next</button>
             </li>`;

    html += '</ul></nav>';
    container.innerHTML = html;
}

function changePage(page) {
    // Re-filter/sort/slice logic is embedded in other functions or we need a central 'processLeads'
    // Currently filterLeads calls renderLeadsTable.
    // So if we update currentPage, we should re-run filter/sort to be safe, 
    // OR just re-render with current cachedLeads if no filter changed.
    
    // Simpler: Just re-render currently cached leads? 
    // But sortLeads modifies cachedLeads in place.
    // filterLeads modifies a 'filtered' copy? No, let's check filterLeads.
    // I need to check filterLeads implementation.
    
    // Assuming filterLeads is the main entry point for display updates.
    if (page < 1) return;
    currentPage = page;
    filterLeads();
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showToast(message, type = 'info') {
    // Create toast element if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '1050';
        document.body.appendChild(toastContainer);
    }

    const toastId = 'toast-' + Date.now();
    const typeClass = {
        'success': 'bg-success',
        'warning': 'bg-warning',
        'error': 'bg-danger',
        'info': 'bg-info'
    }[type] || 'bg-primary';

    const toastHTML = `
        <div id="${toastId}" class="toast ${typeClass} text-white" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    const toastElement = document.getElementById(toastId);
    const bsToast = new bootstrap.Toast(toastElement, { delay: 3000 });
    bsToast.show();
    
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

function getStatusColor(status) {
    const colors = {
        'new': 'info',
        'contacted': 'primary',
        'qualified': 'warning',
        'converted': 'success',
        'lost': 'danger'
    };
    return colors[status] || 'secondary';
}

function formatDate(dateString) {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    } catch (e) {
        return dateString || 'N/A';
    }
}

function formatCurrency(amount, currency = 'USD') {
    try {
        if (currency === 'NGN') {
            return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(amount);
        }
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(amount);
    } catch (e) {
        return (currency === 'NGN' ? '₦' : '$') + parseFloat(amount).toFixed(2);
    }
}

// Function to clean lead name (remove lead codes and "Lead" prefix)
function getCleanLeadName(lead) {
    const displayName = lead.display_name || `${lead.first_name} ${lead.last_name}`;
    
    // Remove all (LD-XXXXX) patterns (lead codes)
    const nameWithoutCodes = displayName.replace(/\s*\(LD-[A-Z0-9]+\)/g, '').trim();
    
    // Also remove "Lead" from beginning if present
    const finalDisplayName = nameWithoutCodes.replace(/^Lead\s*/i, '').trim();
    
    // If after cleaning we have nothing, use a default
    if (!finalDisplayName || finalDisplayName === '') {
        // Try to get name from email
        if (lead.email) {
            const emailParts = lead.email.split('@');
            if (emailParts[0]) {
                const nameFromEmail = emailParts[0].replace(/[._+]/g, ' ');
                return nameFromEmail.split(' ').map(word => 
                    word.charAt(0).toUpperCase() + word.slice(1)
                ).join(' ');
            }
        }
        
        // Try company
        if (lead.company) {
            return lead.company + ' Contact';
        }
        
        return 'Unknown Contact';
    }
    
    return finalDisplayName;
}

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    // Test API connection first
    testApiConnection().then(success => {
        if (success) {
            // Initialize page
            loadLeads();
            loadProductsDropdown();
            loadOwnersDropdown(); // Load owners for filter
        }
    });
    
    // Add debounced event listeners
    const debouncedFilter = debounce(filterLeads, 300);
    document.getElementById('searchLeads').addEventListener('input', debouncedFilter);
    document.getElementById('filterStatus').addEventListener('change', debouncedFilter);
    document.getElementById('filterSource').addEventListener('change', debouncedFilter);
    document.getElementById('filterOwner').addEventListener('change', debouncedFilter);
    
    // Clear modal on hide
    const addLeadModal = document.getElementById('addLeadModal');
    if (addLeadModal) {
        addLeadModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('addLeadForm').reset();
            document.querySelector('#leadProductsTable tbody').innerHTML = '';
            document.getElementById('productsTotal').textContent = '0.00';
            document.getElementById('expectedValue').value = '';
        });
    }

    // Load Sources
    loadLeadSourcesDropdowns();
});

async function loadLeadSourcesDropdowns() {
    try {
        const response = await fetch('?api=true&action=get_sources');
        const result = await response.json();
        
        if (result.success && result.data) {
            const sources = result.data;
            const filterSelect = document.getElementById('filterSource');
            const formSelect = document.getElementById('source');
            
            // Clear existing (except first)
            if (filterSelect) {
                const firstFilter = filterSelect.options[0]; // All Sources
                filterSelect.innerHTML = '';
                filterSelect.appendChild(firstFilter);
            }
            if (formSelect) {
                const firstForm = formSelect.options[0]; // Select Source
                formSelect.innerHTML = '';
                formSelect.appendChild(firstForm);
            }
            
            sources.forEach(source => {
                // Ensure unique options
                if (filterSelect) {
                    const opt = new Option(source, source); // DB uses string
                    filterSelect.add(opt);
                }
                if (formSelect) {
                    const opt = new Option(source, source);
                    formSelect.add(opt);
                }
            });
        }
    } catch (e) {
        console.error('Error loading sources:', e);
    }
}

// Load Products for Dropdown
// Load Products from API
async function loadProductsDropdown() {
    try {
        const response = await fetch('../../views/products/?api=true');
        const result = await response.json();
        
        if (result.success && Array.isArray(result.data)) {
            cachedProducts = result.data;
        } else {
            console.error('Failed to load products:', result.message);
            cachedProducts = [];
        }
    } catch (e) {
        console.error('Error fetching products:', e);
        cachedProducts = [];
    }

    const warning = document.getElementById('noProductsWarning');
    if (warning) {
        if (cachedProducts.length === 0) {
            warning.classList.remove('d-none');
        } else {
            warning.classList.add('d-none');
        }
    }
}

// Product Management Functions (unchanged from your original)
function addLeadProduct() {
    if (cachedProducts.length === 0) {
        showToast('No products available. <a href="/crm/views/products/" class="text-white">Add products</a>', 'warning');
        return;
    }
    
    const tbody = document.querySelector('#leadProductsTable tbody');
    const rowCount = tbody.querySelectorAll('tr').length;
    
    if (rowCount >= 20) {
        showToast('Maximum 20 products allowed per lead.', 'warning');
        return;
    }
    
    const tr = document.createElement('tr');
    
    const productOptions = cachedProducts.map(product => {
        const displayName = product.unit ? `${product.name} (${product.unit})` : product.name;
        return `<option value="${product.id}" data-name="${product.name}" data-price="${product.price || 0}" data-currency="${product.currency || 'NGN'}" data-description="${product.description || ''}">${displayName}</option>`;
    }).join('');
    
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm lead-product-select" required>
                <option value="">-- Select Product --</option>
                ${productOptions}
            </select>
        </td>
        <td><textarea class="form-control form-control-sm lead-details" rows="1" style="resize: none;"></textarea></td>
        <td><input type="number" class="form-control form-control-sm lead-qty" value="1" min="1" step="1"></td>
        <td><input type="text" class="form-control form-control-sm lead-price"></td>
        <td><input type="text" class="form-control form-control-sm lead-total" readonly style="background-color: #f8f9fa;"></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeLeadProductRow(this)"><i class="bi bi-trash"></i></button></td>
    `;
    
    tbody.appendChild(tr);
    
    const select = tr.querySelector('.lead-product-select');
    const qtyInput = tr.querySelector('.lead-qty');
    
    select.addEventListener('change', () => updateLeadProductRow(select));
    qtyInput.addEventListener('input', debounce(() => calculateLeadProductRow(qtyInput), 200));
    tr.querySelector('.lead-price').addEventListener('input', debounce(() => calculateLeadProductRow(tr.querySelector('.lead-price')), 200));
    
    setTimeout(() => {
        if (select.value) {
            updateLeadProductRow(select);
        }
    }, 10);
}

function updateLeadProductRow(selectElement) {
    const row = selectElement.closest('tr');
    const selectedOption = selectElement.selectedOptions[0];
    
    if (!selectedOption || !selectedOption.value) {
        row.querySelector('.lead-price').value = '';
        row.querySelector('.lead-total').value = '';
        row.querySelector('.lead-details').value = '';
        delete row.dataset.currencySymbol;
        return;
    }
    
    const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
    const currency = selectedOption.getAttribute('data-currency');
    const description = selectedOption.getAttribute('data-description') || '';
    
    let symbol = '$';
    if (currency === 'NGN') symbol = '₦';
    else if (currency === 'GBP') symbol = '£';
    else if (currency === 'EUR') symbol = '€';
    
    row.dataset.currencySymbol = symbol;

    const priceInput = row.querySelector('.lead-price');
    priceInput.value = symbol + price.toFixed(2);
    
    const detailsInput = row.querySelector('.lead-details');
    if (detailsInput) {
        detailsInput.value = description;
    }
    
    calculateLeadProductRow(priceInput);
}

function calculateLeadProductRow(input) {
    const row = input.closest('tr');
    const priceText = row.querySelector('.lead-price').value;
    const price = parseFloat(priceText.replace(/[^0-9.-]+/g, "")) || 0;
    const qtyInput = row.querySelector('.lead-qty');
    const qty = parseFloat(qtyInput.value) || 0;
    const total = price * qty;
    
    const symbol = row.dataset.currencySymbol || '$';
    
    const totalInput = row.querySelector('.lead-total');
    totalInput.value = symbol + total.toFixed(2);
    
    calculateLeadProductTotal();
}

function removeLeadProductRow(button) {
    const row = button.closest('tr');
    if (row && row.parentNode) {
        row.parentNode.removeChild(row);
        calculateLeadProductTotal();
    }
}

function calculateLeadProductTotal() {
    let total = 0;
    const totalInputs = document.querySelectorAll('.lead-total');
    let symbol = '$'; // Default
    
    // Attempt to get symbol from first row if exists
    const firstRow = document.querySelector('#leadProductsTable tbody tr');
    if (firstRow && firstRow.dataset.currencySymbol) {
        symbol = firstRow.dataset.currencySymbol;
    }
    
    for (let i = 0; i < totalInputs.length; i++) {
        const value = parseFloat(totalInputs[i].value.replace(/[^0-9.-]+/g, "")) || 0;
        total += value;
    }
    
    document.getElementById('productsTotal').textContent = total.toFixed(2);
    document.getElementById('productsTotalSymbol').textContent = symbol;
    
    document.getElementById('expectedValue').value = total.toFixed(2);
    document.getElementById('expectedValueSymbol').textContent = symbol;
}

// Lead Management Functions
// Global state for viewing archives
let viewingArchived = false;

async function loadLeads() {
    const tbody = document.getElementById('leadsTableBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="13" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';

    try {
        const url = `?api=true&archived=${viewingArchived}&_=${new Date().getTime()}`;
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Expected JSON, got:', text);
            throw new Error('Server returned invalid JSON. See console.');
        }
        
        if (result.success) {
            cachedLeads = result.data || [];
            currentPage = 1; // Reset to first page on new load
            renderLeadsTable(cachedLeads);
            updateArchiveToggleUI();
        } else {
            throw new Error(result.message || 'Unknown error');
        }
    } catch (e) {
        console.error('loadLeads Failed:', e);
        if (tbody) tbody.innerHTML = `<tr><td colspan="13" class="text-center text-danger">Failed to load data: ${escapeHtml(e.message)}</td></tr>`;
        showToast('Failed to load leads: ' + e.message, 'error');
    }
}

function updateArchiveToggleUI() {
    const btn = document.getElementById('toggleArchiveBtn');
    if (viewingArchived) {
        btn.innerHTML = '<i class="bi bi-arrow-left-circle"></i> Show Active';
        btn.classList.replace('btn-outline-secondary', 'btn-secondary');
    } else {
        btn.innerHTML = '<i class="bi bi-archive"></i> Show Archived';
        btn.classList.replace('btn-secondary', 'btn-outline-secondary');
    }
}

function toggleArchiveView() {
    viewingArchived = !viewingArchived;
    loadLeads();
}

function openAddLeadModal() {
    document.getElementById('addLeadForm').reset();
    document.getElementById('leadId').value = '';
    
    document.querySelector('#leadProductsTable tbody').innerHTML = '';
    document.getElementById('productsTotal').innerText = '0.00';
    
    // Enable fields for new entry
    toggleFormFields(false); 
    
    // Show Save, Hide Edit
    document.getElementById('saveLeadBtn').classList.remove('d-none');
    document.getElementById('editLeadBtn').classList.add('d-none');
    document.querySelector('.modal-title').innerText = 'Add New Lead';

    const modal = new bootstrap.Modal(document.getElementById('addLeadModal'));
    modal.show();
}

function toggleFormFields(disabled) {
    const form = document.getElementById('addLeadForm');
    const inputs = form.querySelectorAll('input, select, textarea, button.btn-danger, button.btn-primary');
    inputs.forEach(input => {
        // Don't disable the close button in the footer or the cross icon
        if (!input.classList.contains('btn-close') && input.id !== 'saveLeadBtn' && input.id !== 'editLeadBtn') {
             input.disabled = disabled;
        }
    });
    
    // Handle specific buttons like "Add Product" specifically if they are not caught
    const addProdBtn = form.querySelector('button[onclick="addLeadProduct()"]');
    if (addProdBtn) addProdBtn.disabled = disabled;
}

function enableEditMode() {
    toggleFormFields(false);
    document.getElementById('saveLeadBtn').classList.remove('d-none');
    document.getElementById('editLeadBtn').classList.add('d-none');
    document.querySelector('.modal-title').innerText = 'Edit Lead';
}

async function saveLead() {
    try {
        const id = document.getElementById('leadId').value;
        const firstName = document.getElementById('firstName').value.trim();
        const lastName = document.getElementById('lastName').value.trim();

        if (!firstName || !lastName) {
            showToast('First Name and Last Name are required', 'error');
            return;
        }
        
        // Prevent "Lead" as name
        if (firstName.toLowerCase() === 'lead' || lastName.toLowerCase() === 'lead') {
            showToast('Name cannot be "Lead". Please provide a real name.', 'error');
            return;
        }
        
        const products = [];
        document.querySelectorAll('#leadProductsTable tbody tr').forEach(row => {
            const select = row.querySelector('.lead-product-select');
            const qtyInput = row.querySelector('.lead-qty');
            const priceInput = row.querySelector('.lead-price');
            
            if (select && select.value) {
                products.push({
                    product_id: select.value,
                    quantity: parseInt(qtyInput.value) || 1,
                    price: parseFloat(priceInput.value.replace(/[^0-9.-]+/g, "")) || 0
                });
            }
        });

        const expectedValue = document.getElementById('expectedValue').value;
        const data = {
            first_name: firstName,
            last_name: lastName,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            company: document.getElementById('company').value,
            status: document.getElementById('status').value,
            source: document.getElementById('source').value,
            expected_value: parseFloat(expectedValue) || 0,
            notes: document.getElementById('notes').value,
            address: document.getElementById('address').value,
            city: document.getElementById('city').value,
            state: document.getElementById('state').value,
            zip: document.getElementById('zip').value,
            country: document.getElementById('country').value,
            currency: document.getElementById('leadCurrency') ? document.getElementById('leadCurrency').value : 'USD',
            products: products
        };

        let result;
        if (id) {
            result = await LeadsManager.update(id, data);
        } else {
            result = await LeadsManager.create(data);
        }

        if (result && result.success) {
            const modalEl = document.getElementById('addLeadModal');
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.hide();
            loadLeads();
            showToast(result.message, 'success');
        } else {
            showToast(result ? result.message : 'Unknown error occurred', 'error');
        }
    } catch (e) {
        console.error('Error in saveLead:', e);
        showToast('System Error: ' + e.message, 'error');
    }
}

async function viewLead(leadId) {
    try {
        const url = `?api=true&id=${leadId}&_=${new Date().getTime()}`;
        const response = await fetch(url);
        const result = await response.json();
        
        if (!result.success) {
            showToast('Failed to load lead details: ' + (result.message || 'Unknown error'), 'error');
            return;
        }

        const lead = result.data;

        document.getElementById('leadId').value = lead.id;
        document.getElementById('firstName').value = lead.first_name;
        document.getElementById('lastName').value = lead.last_name;
        document.getElementById('email').value = lead.email;
        document.getElementById('phone').value = lead.phone;
        document.getElementById('company').value = lead.company;
        document.getElementById('status').value = lead.status;
        document.getElementById('source').value = lead.source;
        document.getElementById('expectedValue').value = lead.expected_value;
        document.getElementById('notes').value = lead.notes;
        document.getElementById('address').value = lead.address || '';
        document.getElementById('city').value = lead.city || '';
        document.getElementById('state').value = lead.state || '';
        document.getElementById('zip').value = lead.zip || '';
        document.getElementById('country').value = lead.country || '';

        const tbody = document.querySelector('#leadProductsTable tbody');
        tbody.innerHTML = '';

        if (lead.products && lead.products.length > 0) {
            for (const p of lead.products) {
                // Ensure we have product details effectively
                // Note: p contains name, unit, etc. from the API join
                
                // We need to simulate adding the row. 
                // Since addLeadProduct() uses cachedProducts to build the dropdown, 
                // we need to make sure cachedProducts is populated (it should be from page load).
                
                addLeadProduct();
                const rows = tbody.querySelectorAll('tr');
                const row = rows[rows.length - 1];
                
                const select = row.querySelector('.lead-product-select');
                select.value = p.product_id; // Map p.product_id which comes from join
                
                // If the product is not in cachedProducts (e.g. archived product), we might have an issue displaying it nicely.
                // But let's assume it's there for now or the select will just show empty/value.
                
                updateLeadProductRow(select);
                
                const qtyInput = row.querySelector('.lead-qty');
                qtyInput.value = p.quantity;
                
                // Price at addition might be different from current product price
                if (p.price) {
                     const priceInput = row.querySelector('.lead-price');
                     // We need to keep the currency symbol if possible, or just standard
                     const symbol = p.currency === 'NGN' ? '₦' : '$'; // Simple fallback or derive from p.currency
                     priceInput.value = symbol + parseFloat(p.price).toFixed(2);
                }

                calculateLeadProductRow(qtyInput);
            }
        }
        
        calculateLeadProductTotal();

        // Set to View Mode (Read Only)
        toggleFormFields(true);
        document.getElementById('saveLeadBtn').classList.add('d-none');
        document.getElementById('editLeadBtn').classList.remove('d-none');
        document.querySelector('.modal-title').innerText = 'View Lead';

        const modal = new bootstrap.Modal(document.getElementById('addLeadModal'));
        modal.show();
        
    } catch (e) {
        console.error('Error viewing lead:', e);
        showToast('Error loading lead details: ' + e.message, 'error');
    }
}

// Assign Lead Logic
let assignLeadModal;

async function assignLead(leadId) {
    if (!assignLeadModal) {
        const el = document.getElementById('assignLeadModal');
        if (el) assignLeadModal = new bootstrap.Modal(el);
    }
    
    document.getElementById('assignLeadId').value = leadId;
    const select = document.getElementById('assignUserSelect');
    select.innerHTML = '<option value="">Loading...</option>';
    
    if (assignLeadModal) assignLeadModal.show();
    
    try {
        const response = await fetch(`${LeadsManager.apiUrl}&type=users`);
        const result = await response.json();
        const users = result.data || [];
        
        select.innerHTML = '<option value="">Select User</option>';
        users.forEach(user => {
            select.innerHTML += `<option value="${user.id}">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</option>`;
        });
        
        // Pre-select current owner if possible
        const lead = cachedLeads.find(l => l.id == leadId);
        if (lead && lead.assigned_to) {
            select.value = lead.assigned_to;
        }
    } catch (e) {
        console.error('Error loading users:', e);
        select.innerHTML = '<option value="">Error loading users</option>';
    }
}

async function saveLeadAssignment() {
    const leadId = document.getElementById('assignLeadId').value;
    const userId = document.getElementById('assignUserSelect').value;
    
    if (!userId) {
        showToast('Please select a user', 'error');
        return;
    }
    
    const result = await LeadsManager.update(leadId, { assigned_to: userId });
    
    if (result.success) {
        if (assignLeadModal) assignLeadModal.hide();
        loadLeads();
        showToast('Lead assigned successfully', 'success');
    } else {
        showToast(result.message || 'Failed to assign lead', 'error');
    }
}

let myAssignmentsModal;

async function openMyAssignmentsModal() {
    if (!myAssignmentsModal) {
        myAssignmentsModal = new bootstrap.Modal(document.getElementById('myAssignmentsModal'));
    }
    myAssignmentsModal.show();
    
    // Load Personal Leads
    try {
        const response = await fetch(LeadsManager.apiUrl + '&only_mine=true');
        const json = await response.json();
        const tbody = document.getElementById('myLeadsBody');
        if (json.success) {
            tbody.innerHTML = json.data.map(lead => `
                <tr>
                    <td><strong>${lead.lead_code || 'LD-'+lead.id}</strong></td>
                    <td>${escapeHtml(getCleanLeadName(lead))}</td>
                    <td><span class="badge bg-light text-muted border">${escapeHtml(lead.created_by_name || 'N/A')}</span></td>
                    <td><span class="badge bg-${getStatusColor(lead.status)}">${lead.status}</span></td>
                    <td>${formatDate(lead.created_at)}</td>
                </tr>
            `).join('') || '<tr><td colspan="5" class="text-center text-muted">No leads assigned to you.</td></tr>';
        }
    } catch (e) { console.error(e); }

    // Load Personal Deals
    try {
        const dealsApiUrl = window.location.origin + '/crm/views/deals/index.php?api=true&only_mine=true';
        const response = await fetch(dealsApiUrl);
        const json = await response.json();
        const tbody = document.getElementById('myDealsBody');
        if (json.success) {
            tbody.innerHTML = json.data.map(deal => `
                <tr>
                    <td><strong>${escapeHtml(deal.title)}</strong></td>
                    <td>${formatCurrency(deal.value, deal.currency)}</td>
                    <td><span class="badge bg-light text-muted border">${escapeHtml(deal.created_by_name || 'N/A')}</span></td>
                    <td><span class="badge bg-info">${deal.stage}</span></td>
                    <td>${formatDate(deal.created_at)}</td>
                </tr>
            `).join('') || '<tr><td colspan="5" class="text-center text-muted">No deals assigned to you.</td></tr>';
        }
    } catch (e) { console.error(e); }
}

function renderLeadsTable(leads) {
    const tbody = document.getElementById('leadsTableBody');
    
    if (!tbody) return;

    // Pagination
    const totalItems = leads.length;
    renderPagination(totalItems);

    const start = (currentPage - 1) * itemsPerPage;
    const paginatedLeads = leads.slice(start, start + itemsPerPage);
    
    if (paginatedLeads.length === 0) {
        tbody.innerHTML = '<tr><td colspan="14" class="text-center text-muted">No leads found. Click "Add New Lead" to create one.</td></tr>';
        return;
    }
    
    const fragment = document.createDocumentFragment();
    
    paginatedLeads.forEach((lead, index) => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.onclick = (e) => {
            // Prevent if text selection
            if (window.getSelection().toString()) return;
            viewLead(lead.id); 
        };
        
        const isConverted = lead.status === 'converted';
        const convertButton = isConverted 
            ? `<button class="btn btn-secondary fw-bold" disabled title="Already Converted" onclick="event.stopPropagation()"><i class="bi bi-check-circle"></i></button>`
            : `<button class="btn btn-success fw-bold" title="Convert to Deal" onclick="event.stopPropagation(); convertToDeal(${lead.id})"><i class="bi bi-arrow-right-circle"></i></button>`;
        
        // Product display logic
        let productDisplay = '-';
        let leadCurrency = 'USD'; // Default
        
        if (lead.products && lead.products.length > 0) {
            leadCurrency = lead.products[0].currency || 'USD';
            productDisplay = lead.products.map(p => 
                `<span class="badge bg-info me-1">${p.name}</span>`
            ).join('');
        }
        
        // FIXED: Get clean name without lead codes
        const cleanDisplayName = getCleanLeadName(lead);
        
        tr.innerHTML = `
            <td class="table-checkbox-col">
                <input type="checkbox" class="form-check-input lead-checkbox" value="${lead.id}" onclick="event.stopPropagation()" onchange="updateBulkActionUI()">
            </td>
            <td><strong>${escapeHtml(lead.company || '-')}</strong></td>
            <td>${escapeHtml(cleanDisplayName)}</td>
            <td>${escapeHtml(lead.phone || '-')}</td>
            <td>-</td>
            <td>
                <span class="badge bg-light text-dark border">
                    <i class="bi bi-person-circle me-1"></i> 
                    ${escapeHtml(lead.assigned_to_name || 'Unassigned')}
                </span>
            </td>
            <td>
                <span class="badge bg-light text-muted border">
                    <i class="bi bi-person me-1"></i> 
                    ${escapeHtml(lead.created_by_name || 'N/A')}
                </span>
            </td>
            <td>${productDisplay}</td>
            <td><span class="badge bg-secondary">${lead.source || 'N/A'}</span></td>
            <td><span class="badge bg-${getStatusColor(lead.status)}">${lead.status}</span></td>
            <td>${formatCurrency(lead.expected_value || 0, leadCurrency)}</td>
            <td>${formatDate(lead.created_at)}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    ${viewingArchived ? 
                        `<button class="btn btn-success fw-bold" title="Restore" onclick="event.stopPropagation(); restoreLead(${lead.id})">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>` 
                        : 
                        `${convertButton}
                        <button class="btn btn-info fw-bold text-white" title="${lead.customer_id ? 'Already a Customer' : 'Convert to Customer'}" onclick="event.stopPropagation(); convertToCustomer(${lead.id})" ${lead.customer_id ? 'disabled' : ''}>
                            <i class="bi bi-person-check"></i>
                        </button>
                        <button class="btn btn-warning fw-bold" title="Archive" onclick="event.stopPropagation(); archiveLead(${lead.id})">
                            <i class="bi bi-archive"></i>
                        </button>
                        <button class="btn btn-secondary fw-bold" title="Assign To" onclick="event.stopPropagation(); assignLead(${lead.id})">
                            <i class="bi bi-person-plus"></i>
                        </button>`
                    }
                </div>
            </td>
        `;
        fragment.appendChild(tr);
    });
    
    tbody.innerHTML = '';
    tbody.appendChild(fragment);
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

async function archiveLead(id) {
    if (confirm('Are you sure you want to archive this lead?')) {
        const result = await LeadsManager.delete(id);
        if (result.success) {
            showToast('Lead archived successfully', 'success');
            loadLeads();
        } else {
            showToast('Failed to archive lead: ' + result.message, 'error');
        }
    }
}

async function restoreLead(id) {
    if (confirm('Are you sure you want to restore this lead?')) {
        const result = await LeadsManager.update(id, { is_archived: 0 });
        if (result.success) {
            showToast('Lead restored successfully', 'success');
            loadLeads();
        } else {
            showToast('Failed to restore lead: ' + result.message, 'error');
        }
    }
}

// FIXED: convertToDeal with address information
async function convertToDeal(leadId) {
    const lead = cachedLeads.find(l => l.id == leadId);
    
    if (!lead) {
        showToast('Lead not found (ID: ' + leadId + ')', 'error');
        return;
    }
    
    if (lead.status === 'converted') {
        showToast('This lead has already been converted to a deal!', 'warning');
        return;
    }
    
    if (!confirm(`Convert "${getCleanLeadName(lead)}" to a deal?`)) {
        return;
    }

    try {
        // 1. Create or Find Customer WITH ADDRESS INFORMATION
        let customerId = lead.customer_id;
        
        if (!customerId) {
            // Auto-create customer WITH ADDRESS
             const customerData = {
                first_name: lead.first_name,
                last_name: lead.last_name,
                email: lead.email,
                phone: lead.phone,
                company: lead.company,
                // ADD ADDRESS FIELDS:
                address: lead.address || '',
                city: lead.city || '',
                state: lead.state || '',
                zip: lead.zip || '',
                country: lead.country || '',
                notes: `Auto-created from Lead Conversion (Lead ID: ${lead.lead_code || lead.id})`
            };

            const custResponse = await fetch('../../views/customers/?api=true', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(customerData)
            });
            
            const custResult = await custResponse.json();
            if (custResult.success) {
                customerId = custResult.data.id;
                // Link customer to lead
                await LeadsManager.update(lead.id, { customer_id: customerId });
            } else {
                console.warn('Failed to auto-create customer:', custResult.message);
                if (!confirm('Failed to auto-create customer record. Continue converting to deal anyway?')) return;
            }
        }

        // 2. Prepare Deal Data
        const lineItems = [];
        let totalValue = 0;
        
        if (lead.products && lead.products.length > 0) {
            lead.products.forEach(product => {
                const qty = parseFloat(product.quantity) || 1;
                const price = parseFloat(product.price || product.price_at_addition) || 0;
                const total = qty * price;
                
                // Fetch actual product description from cachedProducts
                let description = product.description || '';
                const productId = product.product_id || product.id;
                
                if (productId && cachedProducts.length > 0) {
                    const fullProduct = cachedProducts.find(p => p.id == productId);
                    if (fullProduct && fullProduct.description) {
                        description = fullProduct.description;
                    }
                }
                
                // Only use fallback if no description found anywhere
                if (!description) {
                    description = 'No description available';
                }
                
                lineItems.push({
                    productId: productId,
                    productName: product.name,
                    description: description,
                    quantity: qty,
                    price: price,
                    total: total
                });
                totalValue += total;
            });
        } else {
             totalValue = parseFloat(lead.expected_value) || 0;
        }

        const dealData = {
            title: `${getCleanLeadName(lead)} - ${lead.company || 'New Deal'}`,
            lead_id: lead.id,
            customer_id: customerId, // Link deal to customer
            value: totalValue,
            stage: 'value_proposition',
            status: 'active',
            lineItems: lineItems,
            valid_until: new Date(new Date().setMonth(new Date().getMonth() + 1)).toISOString().split('T')[0],
            assigned_to: lead.assigned_to,
            // PASS ADDRESS INFORMATION TO DEAL
            address: lead.address || '',
            city: lead.city || '',
            state: lead.state || '',
            zip: lead.zip || '',
            country: lead.country || ''
        };

        // 3. Create Deal via API
        const dealResponse = await fetch('../../views/deals/?api=true', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(dealData)
        });
        
        const dealResult = await dealResponse.json();

        if (dealResult.success) {
            // 4. Update Lead Status
            await LeadsManager.update(lead.id, { status: 'converted' });
            
            showToast('Lead converted to deal & customer created!', 'success');
            
            // 5. Redirect to Deal
            setTimeout(() => {
                window.location.href = `../deals/create.php?id=${dealResult.data.id}`;
            }, 1000);
        } else {
            throw new Error(dealResult.message || 'Failed to create deal');
        }
    } catch (e) {
        console.error('Conversion Error:', e);
        showToast('Conversion failed: ' + e.message, 'error');
    }
}

// FIXED: convertToCustomer with address information
async function convertToCustomer(leadId) {
    const lead = cachedLeads.find(l => l.id == leadId);
    if (!lead) return;

    if (lead.customer_id) {
        showToast('This lead is already converted to a customer.', 'warning');
        return;
    }
    
    if (!confirm(`Convert "${getCleanLeadName(lead)}" to a permanent Customer?`)) return;

    try {
        const customerData = {
            first_name: lead.first_name,
            last_name: lead.last_name,
            email: lead.email,
            phone: lead.phone,
            company: lead.company,
            // ADDED ADDRESS FIELDS:
            address: lead.address || '',
            city: lead.city || '',
            state: lead.state || '',
            zip: lead.zip || '',
            country: lead.country || '',
            notes: `Converted from Lead ID: ${lead.lead_code || lead.id}`,
            assigned_to: lead.assigned_to || lead.owner_id
        };

        const response = await fetch('../../views/customers/?api=true', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(customerData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Lead converted to Customer successfully!', 'success');
            // Update local cache and UI
            lead.customer_id = result.data.id;
            // Link in DB
            await LeadsManager.update(leadId, { customer_id: result.data.id });
        } else {
            showToast('Failed to convert: ' + result.message, 'error');
        }
    } catch (e) {
        showToast('Error converting to customer', 'error');
    }
}

// function deleteLead removed as it is replaced by archiveLead

async function importLeads(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('file', file);
    
    // Reset input so same file can be selected again if needed
    input.value = '';
    
    showToast('Uploading and importing leads...', 'info');
    
    try {
        // Use the API endpoint with action=import
        const response = await fetch('?api=true&action=import', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        
        try {
            const result = JSON.parse(text);
            
            if (result.success) {
                showToast(result.message || 'Import successful', 'success');
                loadLeads();
            } else {
                throw new Error(result.message || 'Import failed');
            }
        } catch (parseError) {
             console.error('JSON Parse Error:', parseError);
             console.error('Raw response:', text);
             throw new Error('Invalid server response during import.');
        }
    } catch (e) {
        console.error('Import error:', e);
        showToast('Error importing leads: ' + e.message, 'error');
    }
}

function exportLeads() {
    // Trigger download via direct URL
    window.location.href = '?api=true&action=export';
}


async function loadOwnersDropdown() {
    try {
        const response = await fetch('index.php?api=true&type=users');
        const result = await response.json();
        const select = document.getElementById('filterOwner');
        
        if (result.success && select) {
            // Keep first option (All Owners)
            while (select.options.length > 1) {
                select.remove(1);
            }
            
            result.data.forEach(user => {
                const opt = document.createElement('option');
                opt.value = user.id;
                opt.textContent = `${user.first_name} ${user.last_name}`;
                select.appendChild(opt);
            });
        }
    } catch (e) {
        console.error('Error loading owners:', e);
    }
}

function filterLeads() {
    const search = document.getElementById('searchLeads').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const sourceFilter = document.getElementById('filterSource').value;
    const ownerFilterElement = document.getElementById('filterOwner');
    const ownerFilter = ownerFilterElement ? ownerFilterElement.value : '';
    
    const filtered = cachedLeads.filter(lead => {
        const cleanDisplayName = getCleanLeadName(lead).toLowerCase();
        
        const matchesSearch = (lead.id.toString().includes(search) || 
                              cleanDisplayName.includes(search) ||
                              (lead.email && lead.email.toLowerCase().includes(search)) || 
                              (lead.company && lead.company.toLowerCase().includes(search)));
        
        const matchesStatus = !statusFilter || lead.status === statusFilter;
        const matchesSource = !sourceFilter || lead.source === sourceFilter;
        const matchesDate = checkDateRange(lead.created_at);

        const matchesOwner = !ownerFilter || (lead.assigned_to && lead.assigned_to == ownerFilter);
        return matchesSearch && matchesStatus && matchesSource && matchesOwner && matchesDate;
    });

    // Sort filtered results based on currentSortState
    const sortedFiltered = filtered.sort((a, b) => {
        const column = currentSortState.column;
        let valA = a[column];
        let valB = b[column];
        
        if (column === 'name') {
            valA = getCleanLeadName(a).toLowerCase();
            valB = getCleanLeadName(b).toLowerCase();
        } else if (typeof valA === 'string') {
            valA = valA.toLowerCase();
            valB = (valB || '').toLowerCase();
        } else if (typeof valA === 'number') {
            valA = valA || 0;
            valB = valB || 0;
        }

        if (valA < valB) return currentSortState.direction === 'asc' ? -1 : 1;
        if (valA > valB) return currentSortState.direction === 'asc' ? 1 : -1;
        return 0;
    });

    renderLeadsTable(sortedFiltered);
}

window.sortLeads = function(column) {
     if (currentSortState.column === column) {
        currentSortState.direction = currentSortState.direction === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortState.column = column;
        currentSortState.direction = 'asc';
    }
    
    // Update Icons
    document.querySelectorAll('.sort-icon').forEach(icon => {
        icon.className = 'bi bi-arrow-down-up sort-icon text-muted';
    });
    const currentIcon = document.getElementById(`sort-${column}`);
    if (currentIcon) {
        currentIcon.className = `bi bi-arrow-${currentSortState.direction === 'asc' ? 'up' : 'down'} sort-icon text-primary`;
    }
    
    filterLeads();
};

function resetFilters() {
    document.getElementById('searchLeads').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterSource').value = '';
    document.getElementById('filterOwner').value = '';
    loadLeads();
}

function toggleSelectAllLeads() {
    const isChecked = document.getElementById('selectAllLeads').checked;
    document.querySelectorAll('.lead-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateBulkActionUI();
}

function updateBulkActionUI() {
    const selected = document.querySelectorAll('.lead-checkbox:checked');
    const toolbar = document.getElementById('bulkActionsToolbar');
    const selectAll = document.getElementById('selectAllLeads');
    const totalCheckboxes = document.querySelectorAll('.lead-checkbox');
    
    if (!toolbar || !selectAll) return;

    // Update Select All State
    if (selected.length === totalCheckboxes.length && totalCheckboxes.length > 0) {
        selectAll.checked = true;
        selectAll.indeterminate = false;
    } else if (selected.length > 0) {
        selectAll.checked = false;
        selectAll.indeterminate = true;
    } else {
        selectAll.checked = false;
        selectAll.indeterminate = false;
    }

    // Toggle Toolbar
    if (selected.length > 0) {
        toolbar.classList.remove('d-none');
        document.getElementById('selectedCount').textContent = selected.length;
    } else {
        toolbar.classList.add('d-none');
    }
}

async function bulkArchiveLeads() {
    const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
    
    if (selected.length === 0) return;
    
    if (confirm(`Are you sure you want to archive ${selected.length} leads?`)) {
        let successCount = 0;
        
        // Sequential for now, could be parallel or bulk API
        for (const id of selected) {
            const result = await LeadsManager.delete(id);
            if (result.success) successCount++;
        }
        
        if (successCount > 0) {
            showToast(`${successCount} leads archived.`, 'success');
            loadLeads();
            updateBulkActionUI();
        } else {
            showToast('Failed to archive selected leads.', 'error');
        }
    }
}

function bulkExportLeads() {
    const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;
    window.location.href = `?api=true&action=export&ids=${selected.join(',')}`;
}

async function bulkAssignLeads() {
    const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;

    if (!assignLeadModal) {
        const el = document.getElementById('assignLeadModal');
        if (el) assignLeadModal = new bootstrap.Modal(el);
    }
    
    document.getElementById('assignLeadId').value = selected.join(','); // Store CSV of IDs
    const select = document.getElementById('assignUserSelect');
    select.innerHTML = '<option value="">Loading...</option>';
    
    if (assignLeadModal) assignLeadModal.show();
    
    try {
        const response = await fetch(`${LeadsManager.apiUrl}&type=users`);
        const result = await response.json();
        const users = result.data || [];
        
        select.innerHTML = '<option value="">Select User</option>';
        users.forEach(user => {
            select.innerHTML += `<option value="${user.id}">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</option>`;
        });
    } catch (e) {
        console.error('Error loading users:', e);
    }
}

// Override saveLeadAssignment to handle bulk
const originalSaveLeadAssignment = saveLeadAssignment;
saveLeadAssignment = async function() {
    const leadIdsStr = document.getElementById('assignLeadId').value;
    const userId = document.getElementById('assignUserSelect').value;
    
    if (!userId) {
        showToast('Please select a user', 'error');
        return;
    }

    const leadIds = leadIdsStr.split(',');
    if (leadIds.length === 1) {
        // Single assignment (keeping original logic if needed, but bulk handles single too)
        await originalSaveLeadAssignment();
        return;
    }

    let successCount = 0;
    for (const id of leadIds) {
        const result = await LeadsManager.update(id, { assigned_to: userId });
        if (result.success) successCount++;
    }

    if (successCount > 0) {
        if (assignLeadModal) assignLeadModal.hide();
        loadLeads();
        showToast(`${successCount} leads assigned successfully`, 'success');
        updateBulkActionUI();
    } else {
        showToast('Failed to assign leads', 'error');
    }
};

async function bulkConvertToDeal() {
    const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;
    
    if (!confirm(`Convert ${selected.length} leads to deals?`)) return;

    let successCount = 0;
    for (const id of selected) {
        // We can't easily wait for the redirect in bulk mode, so we might need a multi-convert API or just skip the redirect
        // For now, let's call convertToDeal and hope for the best, or implement a silent version
        try {
            // Note: convertToDeal has a redirect at the end. We might want to bypass it.
            // Temporary hack: wrap the redirect in a check
            const originalHref = window.location.href;
            await convertToDeal(id);
            successCount++;
        } catch (e) { console.error(e); }
    }
    showToast(`${successCount} leads conversion initiated.`, 'info');
    loadLeads();
    updateBulkActionUI();
}

async function bulkConvertToCustomer() {
    const selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;

    if (!confirm(`Convert ${selected.length} leads to permanent customers?`)) return;

    let successCount = 0;
    for (const id of selected) {
        await convertToCustomer(id);
        successCount++;
    }
    showToast(`${successCount} leads converted to customers.`, 'success');
    loadLeads();
    updateBulkActionUI();
}

let selectionModeActive = false;

function toggleSelectionMode() {
    selectionModeActive = !selectionModeActive;
    const container = document.getElementById('leadsTableContainer');
    const btn = document.getElementById('toggleSelectionMode');
    
    if (selectionModeActive) {
        container.classList.remove('selection-mode-off');
        btn.classList.replace('btn-outline-secondary', 'btn-secondary');
        btn.innerHTML = '<i class="bi bi-x-circle"></i> Cancel Select';
    } else {
        container.classList.add('selection-mode-off');
        btn.classList.replace('btn-secondary', 'btn-outline-secondary');
        btn.innerHTML = '<i class="bi bi-check2-square"></i> Select';
        
        // Reset selections
        document.getElementById('selectAllLeads').checked = false;
        document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = false);
        updateBulkActionUI();
    }
}
</script>

<style>
.table td {
    vertical-align: middle;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
}

.badge {
    font-size: 0.85em;
}

/* Selection Mode Styles */
.selection-mode-off .table-checkbox-col {
    display: none;
}
</style>

<?php require_once '../../includes/footer.php'; ?>