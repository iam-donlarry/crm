<?php
// API Handling Logic
if (isset($_GET['api']) && $_GET['api'] === 'true') {
    require_once '../../includes/config.php';
    require_once '../../includes/utils.php';
    require_once '../../includes/auth.php';

    header('Content-Type: application/json');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        sendResponse(false, 'Unauthorized');
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'export') {
            if (!hasPermission('export_deals')) {
                sendResponse(false, 'Forbidden: You do not have permission to export deals.');
                exit;
            }
            handleExport($pdo);
            exit;
        }
        if ($_GET['action'] === 'template') {
            if (!hasPermission('download_deals_template')) {
                sendResponse(false, 'Forbidden: You do not have permission to download the deals template.');
                exit;
            }
            handleTemplate($pdo);
            exit;
        }
        if ($_GET['action'] === 'import' && $method === 'POST') {
            if (!hasPermission('import_deals')) {
                sendResponse(false, 'Forbidden: You do not have permission to import deals.');
                exit;
            }
            handleImport($pdo);
            exit;
        }
        if ($_GET['action'] === 'documents') {
            handleDocuments($pdo, $method);
            exit;
        }
    }

    switch ($method) {
        case 'GET':
            if (!hasPermission('view_deals')) sendResponse(false, 'Access Denied');
            handleGet($pdo);
            break;
        case 'POST':
            if (!hasPermission('create_deals')) sendResponse(false, 'Access Denied');
            handlePost($pdo);
            break;
        case 'PUT':
            if (!hasPermission('edit_deals')) sendResponse(false, 'Access Denied');
            handlePut($pdo);
            break;
        case 'DELETE':
            if (!hasPermission('delete_deals')) {
                sendResponse(false, 'Forbidden: You do not have permission to delete deals.');
            }
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            sendResponse(false, 'Method Not Allowed');
    }
    exit;
}

function handleDocuments($pdo, $method) {
    if ($method === 'GET') {
        $dealId = isset($_GET['deal_id']) ? intval($_GET['deal_id']) : 0;
        if (!$dealId) {
            sendResponse(false, 'Deal ID required');
            return;
        }
        try {
             $stmt = $pdo->prepare("SELECT * FROM documents WHERE deal_id = ? AND is_archived = 0 ORDER BY created_at DESC");
             $stmt->execute([$dealId]);
             sendResponse(true, '', $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            sendResponse(false, 'Database Error: ' . $e->getMessage());
        }
    } elseif ($method === 'POST') {
        $data = getJsonInput();
        if (empty($data['deal_id']) || empty($data['type']) || empty($data['document_number'])) {
            sendResponse(false, 'Missing required fields');
            return;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO documents (deal_id, type, document_number, content_snapshot) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['deal_id'],
                $data['type'],
                $data['document_number'],
                $data['content_snapshot'] ?? null
            ]);
            sendResponse(true, 'Document Saved');
        } catch (PDOException $e) {
             sendResponse(false, 'Database Error: ' . $e->getMessage());
        }
    } elseif ($method === 'DELETE') {
        if (!hasPermission('archive_documents')) {
            sendResponse(false, 'Forbidden: You do not have permission to archive documents.');
            return;
        }
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id) {
            sendResponse(false, 'Document ID required');
            return;
        }
        try {
            $stmt = $pdo->prepare("UPDATE documents SET is_archived = 1 WHERE id = ?");
            $stmt->execute([$id]);
            sendResponse(true, 'Document Archived');
        } catch (PDOException $e) {
            sendResponse(false, 'Database Error');
        }
    }
}

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

    // If id is present, get single deal
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    try {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM deals WHERE id = ? AND is_archived = 0");
            $stmt->execute([$id]);
            $deal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($deal) {
                // Fetch line items
                $stmt = $pdo->prepare("
                    SELECT dp.*, p.code, p.unit 
                    FROM deal_products dp
                    LEFT JOIN products p ON dp.product_id = p.id 
                    WHERE dp.deal_id = ?
                ");
                $stmt->execute([$id]);
                $deal['lineItems'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Fetch Linked Customer Address if exists
                if (!empty($deal['customer_id'])) {
                     $cStmt = $pdo->prepare("SELECT address, city, state, zip, country FROM customers WHERE id = ?");
                     $cStmt->execute([$deal['customer_id']]);
                     $customerData = $cStmt->fetch(PDO::FETCH_ASSOC);
                     if ($customerData) {
                         $deal = array_merge($deal, $customerData);
                     }
                }
                
                // Parse stage notes if stored as JSON/Text (not in schema yet, but handled in frontend logic?)
                // Assuming stage notes are handled differently or just part of description for now based on current DB schema.
                
                sendResponse(true, '', $deal);
            } else {
                sendResponse(false, 'Deal not found');
            }
        } else {
             $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;

             // Visibility Logic
             $where = ["d.is_archived = ?"];
             $params = [$archived];

             // Visibility Logic
            $isAdmin = hasPermission('system_admin');
            $hasTeamView = hasPermission('view_team_deals');
            $onlyMine = isset($_GET['only_mine']) && $_GET['only_mine'] === 'true';
            
            if ($onlyMine || (!$isAdmin && !$hasTeamView)) {
                 $where[] = "d.assigned_to = ?";
                 $params[] = $_SESSION['user_id'];
             }

             // Join with leads, users and original owners
             $sql = "SELECT d.*, l.company as lead_company, l.first_name as lead_name, l.customer_id as lead_customer_id, c.customer_code, CONCAT(c.first_name, ' ', c.last_name) as customer_name, CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name, CONCAT(u2.first_name, ' ', u2.last_name) as created_by_name 
                     FROM deals d 
                     LEFT JOIN leads l ON d.lead_id = l.id
                     LEFT JOIN customers c ON d.customer_id = c.id
                     LEFT JOIN users u ON d.assigned_to = u.id 
                     LEFT JOIN users u2 ON d.created_by = u2.id
                     WHERE " . implode(" AND ", $where);
            
            // Append Order By
            $sql .= " ORDER BY d.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendResponse(true, '', $deals);
        }
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handlePost($pdo) {
    $data = getJsonInput();
    if (empty($data['title']) || !isset($data['value']) || empty($data['stage'])) {
        sendResponse(false, 'Title, Value, and Stage are required');
        return;
    }

    try {
        $pdo->beginTransaction();

        $lead_id = !empty($data['lead_id']) && $data['lead_id'] !== 'null' ? $data['lead_id'] : null;
        $customer_id = !empty($data['customer_id']) && $data['customer_id'] !== 'null' ? $data['customer_id'] : null;

        $sql = "INSERT INTO deals (title, lead_id, customer_id, value, stage, valid_until, status, assigned_to, created_by, currency, exchange_rate, description, terms_conditions, stage_notes, due_date, is_dual_currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['title'],
            $lead_id,
            $customer_id,
            $data['value'],
            $data['stage'],
            $data['valid_until'] ?? null,
            $data['status'] ?? 'active',
            $data['assigned_to'] ?? $_SESSION['user_id'],
            $_SESSION['user_id'],
            $data['currency'] ?? 'NGN',
            $data['exchange_rate'] ?? 1.0,
            $data['description'] ?? '',
            $data['terms_conditions'] ?? '',
            $data['stage_notes'] ?? '',
            $data['due_date'] ?? null,
            $data['is_dual_currency'] ?? 0
        ]);
        
        $dealId = $pdo->lastInsertId();
        
        // Handle Customer Address Sync
        if (!empty($data['customer_id']) || !empty($data['lead_id']) || !empty($data['address'])) {
             $addressData = [
                'address' => $data['address'] ?? '',
                'city' => $data['city'] ?? '',
                'state' => $data['state'] ?? '',
                'zip' => $data['zip'] ?? '',
                'country' => $data['country'] ?? ''
            ];
             
             $targetCustomerId = $customer_id;
             if (!$targetCustomerId && $lead_id) {
                 $lStmt = $pdo->prepare("SELECT customer_id FROM leads WHERE id = ?");
                 $lStmt->execute([$lead_id]);
                 $targetCustomerId = $lStmt->fetchColumn();
             }

             if ($targetCustomerId) {
                 createOrUpdateCustomer($pdo, $targetCustomerId, [], $addressData);
             }
        }

        // Handle Line Items
        if (!empty($data['lineItems']) && is_array($data['lineItems'])) {
            $sql = "INSERT INTO deal_products (deal_id, product_id, product_name, description, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            foreach ($data['lineItems'] as $item) {
                $stmt->execute([
                    $dealId,
                    !empty($item['productId']) ? $item['productId'] : null, // Handle manual items
                    $item['productName'] ?? 'Unknown Item',
                    $item['description'] ?? '',
                    $item['quantity'] ?? 1,
                    $item['price'] ?? 0,
                    $item['total'] ?? 0
                ]);
            }
        }

        $pdo->commit();
        sendResponse(true, 'Deal created', ['id' => $dealId]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handlePut($pdo) {
    $data = getJsonInput();
    if (empty($data['id'])) {
        sendResponse(false, 'Deal ID required');
        return;
    }

    // Check Assign Permission if changing owner
    if (isset($data['assigned_to'])) {
        // Fetch current to see if it's actually changing
        $stmt = $pdo->prepare("SELECT assigned_to FROM deals WHERE id = ?");
        $stmt->execute([$data['id']]);
        $currentOwner = $stmt->fetchColumn();
        
        if ($currentOwner != $data['assigned_to'] && !hasPermission('assign_deals')) {
            sendResponse(false, 'Forbidden: You do not have permission to re-assign deals.');
            return;
        }
    }

    $fields = [];
    $values = [];
    $allowed = ['title', 'lead_id', 'customer_id', 'value', 'stage', 'valid_until', 'status', 'assigned_to', 'currency', 'exchange_rate', 'description', 'terms_conditions', 'stage_notes', 'due_date', 'is_dual_currency', 'is_archived'];

    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $val = $data[$field];
            if (($field === 'lead_id' || $field === 'customer_id') && ($val === '' || $val === 'null')) {
                $val = null;
            }
            $fields[] = "$field = ?";
            $values[] = $val;
        }
    }

    if (empty($fields) && !isset($data['lineItems'])) {
        sendResponse(false, 'No fields to update');
        return;
    }
    
    try {
        $pdo->beginTransaction();

        if (!empty($fields)) {
            $values[] = $data['id'];
            $sql = "UPDATE deals SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
        }

        // Handle Customer Address Update
        if (!empty($data['customer_id']) || !empty($data['lead_id']) || !empty($data['address'])) {
            $addressData = [
                'address' => $data['address'] ?? '',
                'city' => $data['city'] ?? '',
                'state' => $data['state'] ?? '',
                'zip' => $data['zip'] ?? '',
                'country' => $data['country'] ?? ''
            ];
            
            $targetCustomerId = !empty($data['customer_id']) && $data['customer_id'] !== 'null' ? $data['customer_id'] : null;
            if (!$targetCustomerId && !empty($data['lead_id']) && $data['lead_id'] !== 'null') {
                $lStmt = $pdo->prepare("SELECT customer_id FROM leads WHERE id = ?");
                $lStmt->execute([$data['lead_id']]);
                $targetCustomerId = $lStmt->fetchColumn();
            }

            if ($targetCustomerId) {
                 createOrUpdateCustomer($pdo, $targetCustomerId, [], $addressData);
            }
        }

        // Handle Line Items (Full sync: Delete all and re-insert)
        if (isset($data['lineItems']) && is_array($data['lineItems'])) {
            $stmt = $pdo->prepare("DELETE FROM deal_products WHERE deal_id = ?");
            $stmt->execute([$data['id']]);

            $sql = "INSERT INTO deal_products (deal_id, product_id, product_name, description, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            foreach ($data['lineItems'] as $item) {
                 $stmt->execute([
                    $data['id'],
                    !empty($item['productId']) ? $item['productId'] : null,
                    $item['productName'] ?? 'Unknown Item',
                    $item['description'] ?? '',
                    $item['quantity'] ?? 1,
                    $item['price'] ?? 0,
                    $item['total'] ?? 0
                ]);
            }
        }

        $pdo->commit();
        sendResponse(true, 'Deal updated');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handleDelete($pdo) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id) sendResponse(false, 'ID required');

    try {
        $stmt = $pdo->prepare("UPDATE deals SET is_archived = 1 WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, 'Deal archived');
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handleExport($pdo) {
    if (!hasPermission('export_deals')) {
        sendResponse(false, 'Forbidden: You do not have permission to export deals.');
        exit;
    }
    ob_clean(); // Clean buffer
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="deals_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    // Headers: Address, City, State/Province, Zip/Postal Code, Deal Title, Value, Stage, Owner, Valid Until, Status, Created
    fputcsv($output, ['Address', 'City', 'State/Province', 'Zip/Postal Code', 'Deal Title', 'Value', 'Stage', 'Owner', 'Valid Until', 'Status', 'Created']);

    $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
    
    $where = ["d.is_archived = 0"];
    $params = [];
    
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "d.id IN ($placeholders)";
        $params = array_map('intval', $ids);
    }
    
    $sql = "SELECT 
                COALESCE(lc.address, dc.address) as address,
                COALESCE(lc.city, dc.city) as city,
                COALESCE(lc.state, dc.state) as state,
                COALESCE(lc.zip, dc.zip) as zip,
                d.title, CONCAT(COALESCE(d.currency, 'USD'), ' ', d.value) as val_with_currency, d.stage,
                CONCAT(u.first_name, ' ', u.last_name) as owner_name,
                d.valid_until, d.status, d.created_at
            FROM deals d
            LEFT JOIN leads l ON d.lead_id = l.id
            LEFT JOIN customers lc ON l.customer_id = lc.id
            LEFT JOIN customers dc ON d.customer_id = dc.id
            LEFT JOIN users u ON d.assigned_to = u.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY d.created_at DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function handleTemplate($pdo) {
    if (!hasPermission('download_deals_template')) {
        sendResponse(false, 'Forbidden: You do not have permission to download the deals template.');
        exit;
    }
    ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="deals_template.csv"');

    $output = fopen('php://output', 'w');
    // Headers: Address, City, State, Zip, Title, Value, Stage, Owner, Valid Until, Status
    fputcsv($output, ['Address', 'City', 'State', 'Zip', 'Title', 'Value', 'Stage', 'Owner', 'Valid Until', 'Status']);
    fclose($output);
    exit;
}

function handleImport($pdo) {
    if (!hasPermission('import_deals')) {
        sendResponse(false, 'Forbidden: You do not have permission to import deals.');
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
    $errors = [];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO deals (title, value, stage, status, currency, valid_until, assigned_to, created_by, exchange_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $updateCus = $pdo->prepare("UPDATE deals SET customer_id = ? WHERE id = ?");
        
        while (($row = fgetcsv($file, 0, $delimiter)) !== false) {
            // Expected indices: Address(0), City(1), State(2), Zip(3), Title(4), Value(5), Stage(6), Owner(7), Valid Until(8), Status(9)
            if (empty($row) || count($row) < 5) continue; 
            
            $address = $row[0] ?? '';
            $city = $row[1] ?? '';
            $state = $row[2] ?? '';
            $zip = $row[3] ?? '';
            $title = !empty($row[4]) ? trim($row[4]) : 'Untitled Deal';
            $valueStr = trim($row[5] ?? '0');
            $stage = !empty($row[6]) ? trim($row[6]) : 'value_proposition';
            $validUntil = !empty($row[8]) ? trim($row[8]) : null;
            $rawStatus = !empty($row[9]) ? trim($row[9]) : 'active';

            // Parse currency from value (e.g. "NGN 5000.00")
            $currency = 'USD';
            $value = 0;
            if (preg_match('/^([A-Z]{3})\s+([0-9.-]+)$/i', $valueStr, $matches)) {
                $currency = strtoupper($matches[1]);
                $value = floatval($matches[2]);
            } else {
                $value = floatval($valueStr);
            }

            $validStatuses = ['active', 'inactive'];
            $status = in_array(strtolower($rawStatus), $validStatuses) ? strtolower($rawStatus) : 'active';

            // If we have address/city, we should probably Create/Link a customer
            $customerId = null;
            if (!empty($address) || !empty($city)) {
                $addressData = [
                    'address' => $address,
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip,
                    'country' => ''
                ];
                $contactData = [
                    'first_name' => 'Imported',
                    'last_name' => 'Customer',
                    'company' => $title
                ];
                $customerId = createOrUpdateCustomer($pdo, null, $contactData, $addressData);
            }

            $stmt->execute([
                $title,
                $value,
                strtolower($stage),
                $status,
                $currency,
                $validUntil,
                $_SESSION['user_id'], // Assign to current user
                $_SESSION['user_id'], // Original Owner (created_by)
                1.0 // Default exchange rate
            ]);
            
            if ($customerId) {
                $dealId = $pdo->lastInsertId();
                $updateCus->execute([$customerId, $dealId]);
            }

            $successCount++;
        }
        $pdo->commit();
        sendResponse(true, "Imported $successCount deals successfully");
    } catch (Exception $e) {
        $pdo->rollBack();
        sendResponse(false, 'Import failed: ' . $e->getMessage());
    }
    fclose($file);
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">


    <!-- Deals Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="m-0 font-weight-bold text-primary">Sales Pipeline</h6>
            <div class="ms-2 d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="toggleSelectionMode" onclick="toggleSelectionMode()">
                    <i class="bi bi-check2-square"></i> Select
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="openMyAssignmentsModal()">
                    <i class="bi bi-person-workspace"></i> My Assigned
                </button>
                <button onclick="openAddDealModal()" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Create New Deal
                </button>
                 <button onclick="exportDeals()" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-arrow-down"></i> Export CSV
                 </button>
                 <button onclick="downloadDealsTemplate()" class="btn btn-outline-info btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Download Template
                 </button>
                 <button onclick="document.getElementById('importFile').click()" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
                 </button>
                 <input type="file" id="importFile" accept=".csv" style="display: none;" onchange="importDeals(this)">
            </div>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <!-- Filters -->
            <div class="d-flex flex-nowrap gap-2 overflow-auto pb-2">
                <div style="min-width: 200px;">
                    <input type="text" class="form-control" id="searchDeals" placeholder="Search deals...">
                </div>
                <div style="min-width: 150px;">
                    <select class="form-select" id="filterStage">
                        <option value="">All Stages</option>
                        <option value="value_proposition">1. Value Proposition</option>
                        <option value="proposal_quote">2. Proposal / Quote</option>
                        <option value="negotiation_review">3. Negotiation / Review</option>
                        <option value="qualification">4. Qualification</option>
                        <option value="closed_won">5. Closed Won</option>
                        <option value="closed_lost">5. Closed Lost</option>
                    </select>
                </div>
                <div style="min-width: 150px;">
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <!-- Deal Owner Filter -->
                <div style="min-width: 150px;">
                     <select class="form-select" id="filterOwner">
                        <option value="">All Deal Owners</option>
                     </select>
                </div>
                <!-- Toggle Archive View -->
                <div style="min-width: 160px;">
                     <button class="btn btn-outline-secondary w-100" id="toggleArchiveBtn" onclick="toggleArchiveView()">
                        <i class="bi bi-archive"></i> Show Archived
                     </button>
                </div>
                <div style="min-width: 150px;">
                     <button class="btn btn-outline-secondary w-100" onclick="openDateRangeModal()">
                        <i class="bi bi-calendar3"></i> <span id="dateRangeLabel">Date Range</span>
                     </button>
                </div>
                <div>
                    <button class="btn btn-secondary w-100" onclick="resetFilters()">
                        <i class="bi bi-x-circle"></i> Reset
                    </button>
                </div>
            </div>

                        <!-- Bulk Actions Toolbar -->
                        <div id="bulkActionsToolbar" class="card shadow mb-3 d-none">
                            <div class="card-body py-2 d-flex justify-content-between align-items-center bg-light border-bottom">
                                <div>
                                    <span class="fw-bold text-primary"><span id="selectedCount">0</span> selected</span>
                                </div>
                                <div class="btn-group gap-2">
                                    <button class="btn btn-outline-success btn-sm" onclick="bulkExportDeals()">
                                        <i class="bi bi-file-earmark-arrow-down"></i> Export Selected
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="bulkConvertToCustomer()">
                                        <i class="bi bi-person-check"></i> Convert to Customer
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="bulkArchiveDeals()">
                                        <i class="bi bi-archive"></i> Archive Selected
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive selection-mode-off" id="dealsTableContainer">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;" class="table-checkbox-col">
                                            <input type="checkbox" class="form-check-input" id="selectAllDeals" onclick="toggleSelectAllDeals()">
                                        </th>
                                        <th style="cursor: pointer;" onclick="sortDeals('title')">Deal Title <i class="bi bi-arrow-down-up sort-icon" id="sort-title"></i></th>
                            <th style="cursor: pointer;" onclick="sortDeals('value')">Value <i class="bi bi-arrow-down-up sort-icon" id="sort-value"></i></th>
            <th style="cursor: pointer;" onclick="sortDeals('stage')">Stage <i class="bi bi-arrow-down-up sort-icon" id="sort-stage"></i></th>
            <th style="cursor: pointer;" onclick="sortDeals('assigned_to_name')">Deal Owner <i class="bi bi-arrow-down-up sort-icon" id="sort-assigned_to_name"></i></th>
            <th style="cursor: pointer;" onclick="sortDeals('created_by_name')">Original Owner <i class="bi bi-arrow-down-up sort-icon" id="sort-created_by_name"></i></th>
            <th style="cursor: pointer;" onclick="sortDeals('valid_until')">Valid Until <i class="bi bi-arrow-down-up sort-icon" id="sort-valid_until"></i></th>
                            <th style="cursor: pointer;" onclick="sortDeals('status')">Status <i class="bi bi-arrow-down-up sort-icon" id="sort-status"></i></th>
                            <th style="cursor: pointer;" onclick="sortDeals('created_at')">Created <i class="bi bi-arrow-down-up sort-icon" id="sort-created_at"></i></th>
                            <th>Actions</th>
                        </tr>
                        </tr>
                    </thead>
                    <tbody id="dealsTableBody">
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="paginationContainer" class="d-flex justify-content-end mt-3 me-3"></div>
            </div>
</div>

<!-- Date Range Modal -->
<div class="modal fade" id="dateRangeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Date Range</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary text-start" onclick="applyDateRange('this_week')">This Week</button>
                    <button class="btn btn-outline-primary text-start" onclick="applyDateRange('last_week')">Last Week</button>
                    <button class="btn btn-outline-primary text-start" onclick="applyDateRange('this_month')">This Month</button>
                    <button class="btn btn-outline-primary text-start" onclick="applyDateRange('last_month')">Last Month</button>
                    <button class="btn btn-outline-primary text-start" onclick="applyDateRange('this_year')">This Year</button>
                    <button class="btn btn-outline-primary text-start" onclick="applyDateRange('last_year')">Last Year</button>
                    <button class="btn btn-outline-secondary text-start" onclick="applyDateRange('all')">All Time</button>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
</div>

<!-- Add/Edit Deal Modal -->
<div class="modal fade" id="dealModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl"> <!-- Changed to modal-xl for more space -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Deal</h5>
                <div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <form id="dealForm">
                    <input type="hidden" id="dealId">
                    <input type="hidden" id="exchangeRate" value="1.0">
                    
                     <div class="row">
                        <div class="col-lg-8">
                            <!-- Deal Basic Info -->
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Deal Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Deal Title *</label>
                                            <input type="text" class="form-control" id="dealTitle" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Source Type</label>
                                            <div class="btn-group w-100 mb-2" role="group">
                                                <input type="radio" class="btn-check" name="dealSourceType" id="sourceTypeLead" value="lead" checked onchange="toggleSourceType()">
                                                <label class="btn btn-outline-primary" for="sourceTypeLead">Lead</label>

                                                <input type="radio" class="btn-check" name="dealSourceType" id="sourceTypeCustomer" value="customer" onchange="toggleSourceType()">
                                                <label class="btn btn-outline-primary" for="sourceTypeCustomer">Customer</label>
                                            </div>
                                            
                                            <div id="leadSelectWrapper">
                                                 <select class="form-select" id="dealLead">
                                                    <option value="">Select Lead...</option>
                                                </select>
                                            </div>
                                            <div id="customerSelectWrapper" class="d-none">
                                                <select class="form-select" id="dealCustomer">
                                                    <option value="">Select Customer...</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <h6 class="border-bottom pb-1 mb-2">Address Information</h6>
                                        </div>
                                        <div class="col-md-12 mb-2">
                                            <label class="form-label">Address</label>
                                            <input type="text" class="form-control" id="dealAddress">
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">City</label>
                                            <input type="text" class="form-control" id="dealCity">
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">State</label>
                                            <input type="text" class="form-control" id="dealState">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Zip Code</label>
                                            <input type="text" class="form-control" id="dealZip">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Country</label>
                                            <input type="text" class="form-control" id="dealCountry">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                             <label class="form-label">Expected Value *</label>
                                             <div class="input-group">
                                                <span class="input-group-text currency-symbol">$</span>
                                                <input type="number" class="form-control" id="dealValue" step="0.01" required>
                                             </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Valid Until</label>
                                            <input type="date" class="form-control" id="dealCloseDate">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Due Date</label>
                                            <input type="date" class="form-control" id="dealDueDate">
                                        </div>
                                    </div>
                                     <div class="row mb-3">
                                          <div class="col-md-4">
                                            <label class="form-label">Currency</label>
                                            <select class="form-select" id="dealCurrency" onchange="updateCurrencySymbols()">
                                                <option value="NGN" data-symbol="₦">₦ (NGN)</option>
                                                <option value="USD" data-symbol="$">$ (USD)</option>
                                                <option value="GBP" data-symbol="£">£ (GBP)</option>
                                                <option value="EUR" data-symbol="€">€ (EUR)</option>
                                            </select>
                                        </div>
                                     </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" id="dealDescription" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Terms & Conditions</label>
                                        <textarea class="form-control" id="dealTerms" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Line Items with Full Features -->
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Line Items / Products</h6>
                                </div>
                                <div class="card-body">
                                    <!-- Dual Currency Section -->
                                    <div class="mb-4 p-3 border rounded bg-light">
                                        <div class="row align-items-center">
                                            <div class="col-md-7 d-flex align-items-center gap-3">
                                                <input class="form-check-input" type="checkbox" id="isDualCurrency" onchange="toggleDualCurrency()" 
                                                       style="width: 1.5rem; height: 1.5rem; border: 2.5px solid #0d6efd; border-radius: 0.25rem; cursor: pointer; margin: 0;">
                                                <label class="fw-bold mb-0" for="isDualCurrency" style="cursor: pointer; font-size: 1rem;">
                                                    Dual Currency Mode
                                                </label>
                                                <small id="customRateIndicator" class="text-muted d-none">
                                                    <i class="bi bi-info-circle"></i> Custom rates
                                                </small>
                                            </div>
                                            <div class="col-md-5 d-flex align-items-end">
                                                <button type="button" class="btn btn-outline-secondary w-100" id="exchangeRateBtn" onclick="openExchangeRateModal()">
                                                    <i class="bi bi-currency-exchange"></i> Set Exchange Rate
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6 d-none" id="secondaryCurrencyDiv">
                                            <label class="form-label">Secondary Currency</label>
                                            <select class="form-select" id="secCurrencySelect" onchange="calculateTotals()">
                                                <option value="NGN">₦ (NGN)</option>
                                                <option value="USD">$ (USD)</option>
                                                <option value="GBP">£ (GBP)</option>
                                                <option value="EUR">€ (EUR)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="noProductsWarning" class="alert alert-warning d-none mb-3">
                                        <i class="bi bi-exclamation-triangle"></i> No products available. 
                                        <a href="/crm/views/products/">Create products first</a>
                                    </div>

                                    <!-- Compact Exchange Rate Info -->
                                    <div class="alert d-flex align-items-center justify-content-between py-2 mb-3" style="background: linear-gradient(135deg, #12b1b0 0%, #0d8f8e 100%); border: none; margin-bottom: 1rem !important;">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-currency-exchange text-white me-2" style="font-size: 1.2rem;"></i>
                                            <small class="text-white mb-0 fw-bold">Exchange Rate:</small>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-white"><span id="baseRateSymbol">$</span>1 = <span id="exchRateSymbol">₦</span><span id="defaultRateDisplay">1,550.00</span></strong>
                                            <small class="text-white-50 d-block" id="rateStatus" style="font-size: 0.7rem;">Default Rate</small>
                                        </div>
                                    </div>

                                    <table class="table table-bordered table-responsive" id="dealProductsTable">
                                        <thead>
                                            <tr>
                                                <th width="20%">Product Name</th>
                                                <th width="20%">Description</th>
                                                <th width="10%">Qty</th>
                                                <th width="15%">Unit Price (<span class="currency-symbol">$</span>)</th>
                                                <th width="15%">Total (<span class="currency-symbol">$</span>)</th>
                                                <th width="10%" class="secondary-currency-col d-none">Unit Price (<span class="sec-currency-code">GBP</span>)</th>
                                                <th width="12%" class="secondary-currency-col d-none">Total (<span class="sec-currency-code">GBP</span>)</th>
                                                <th width="5%"></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                                <td class="fw-bold"><span id="dealSubtotal">$0.00</span></td>
                                                <td class="secondary-currency-col d-none"></td>
                                                <td class="secondary-currency-col d-none fw-bold"><span id="secondarySubtotalDisplay">0.00</span></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" class="text-end fw-bold">VAT (7.5%):</td>
                                                <td><span id="dealVat">$0.00</span></td>
                                                <td class="secondary-currency-col d-none"></td>
                                                <td class="secondary-currency-col d-none"><span id="secondaryVatDisplay">0.00</span></td>
                                                <td></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="4" class="text-end fw-bold">Total (Inc. VAT):</td>
                                                <td class="fw-bold text-primary"><span id="dealProductsTotal">$0.00</span></td>
                                                <td class="secondary-currency-col d-none"></td>
                                                <td class="secondary-currency-col d-none fw-bold text-primary"><span id="secondaryTotalDisplay">0.00</span></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="addDealProduct()">
                                        <i class="bi bi-plus"></i> Add Item
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Pipeline -->
                        <div class="col-lg-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Pipeline Stage</h6>
                                </div>
                                <div class="card-body">
                                    <select class="form-select mb-3" id="dealStage" onchange="updateStageInfo()">
                                        <option value="value_proposition" data-desc="Define the unique value your solution provides to the client.">1. Value Proposition</option>
                                        <option value="proposal_quote" data-desc="Present the proposal or quote to the client.">2. Proposal / Quote</option>
                                        <option value="negotiation_review" data-desc="Negotiate terms and review details.">3. Negotiation / Review</option>
                                        <option value="qualification" data-desc="Confirm budget, authority, need, and timing.">4. Qualification</option>
                                        <option value="closed_won" data-desc="Deal successfully closed.">5. Closed Won</option>
                                        <option value="closed_lost" data-desc="Deal lost to competition or other reasons.">5. Closed Lost</option>
                                    </select>
                                    <div class="progress mb-3" style="height: 20px;">
                                        <div class="progress-bar" id="stageProgress" style="width: 11%">11%</div>
                                    </div>
                                    <div id="stageDescription" class="text-muted small mb-3"></div>
                                    <label class="form-label">Stage Notes</label>
                                    <textarea class="form-control mb-3" id="stageNotes" rows="4"></textarea>
                                    <button type="button" class="btn btn-primary btn-sm w-100 mb-2" onclick="moveToNextStage()">
                                        <i class="bi bi-arrow-right-circle"></i> Move to Next Stage
                                    </button>
                                </div>
                            </div>

                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Deal Status</h6>
                                </div>
                                <div class="card-body">
                                    <select class="form-select mb-3" id="dealStatus">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    
                                     <!-- Assign Deal Modal -->
                                    <!-- Assign Deal Modal Moved to Root -->

<!-- Print Options -->
                                    <div class="dropdown mt-3" id="printBtn" style="display: none;">
                                        <button class="btn btn-outline-secondary w-100 dropdown-toggle" type="button" id="printDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-printer"></i> Print Document
                                        </button>
                                        <ul class="dropdown-menu w-100" aria-labelledby="printDropdown">
                                            <li><a class="dropdown-item" href="#" onclick="openPrintModal('QUOTE')">Print Quote</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="openPrintModal('INVOICE')">Print Invoice</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="openPrintModal('PROPOSAL')">Print Proposal</a></li>
                                        </ul>
                                    </div>

                                    <!-- Documents History Section -->
                                    <div class="mt-4">
                                        <h6 class="border-bottom pb-2">Deal Documents History</h6>
                                        <div id="dealDocumentsList" style="max-height: 150px; overflow-y: auto;">
                                            <!-- Populated by loadDealDocuments -->
                                            <p class="text-muted">No documents yet.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="editDealBtn" onclick="enableEditMode()">Edit</button>
                <button type="button" class="btn btn-primary d-none" id="saveDealBtn" onclick="saveDeal()">Save Deal</button>
            </div>
        </div>
    </div>
</div>

<!-- Print Modal -->
<div class="modal fade" id="printModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-printer"></i> Print Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <hr>
                <div id="printPreview" style="background: white; padding: 40px; min-height: 400px;">
                    <!-- Print content will be generated here -->
                </div>
                <!-- Hidden input to store generated info -->
                <input type="hidden" id="generatedDocNum" value="">
                <input type="hidden" id="generatedDocType" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printDocument()">
                    <i class="bi bi-printer-fill"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Exchange Rate Modal -->
<div class="modal fade" id="exchangeRateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-currency-exchange"></i> Exchange Rate Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3"><i class="bi bi-info-circle"></i> Custom rates for this deal only</p>
                <div class="mb-3">
                    <label class="form-label">$ to ₦</label>
                    <input type="number" class="form-control" id="rateNGN" step="0.01" min="0" placeholder="1550">
                    <small class="text-muted">$1 = ? ₦</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">$ to £</label>
                    <input type="number" class="form-control" id="rateGBP" step="0.0001" min="0" placeholder="0.79">
                    <small class="text-muted">$1 = ? £</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">$ to €</label>
                    <input type="number" class="form-control" id="rateEUR" step="0.0001" min="0" placeholder="0.92">
                    <small class="text-muted">$1 = ? €</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="resetRatesToDefault()">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset to Default
                </button>
                <button type="button" class="btn btn-primary" onclick="applyCustomRates()" data-bs-dismiss="modal">
                    <i class="bi bi-check-lg"></i> Apply Rates
                </button>
            </div>
        </div>
    </div>
</div>

<!-- My Assignments Modal (identical to Leads) -->
<div class="modal fade" id="myAssignmentsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-workspace"></i> My Active Assignments</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="assignmentTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link" id="myLeadsTab" onclick="location.href='../leads/'">Go to My Leads</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#assignedDeals">My Deals</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="assignedDeals">
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

<!-- Assign Deal Modal -->
<div class="modal fade" id="assignDealModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Deal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignDealForm">
                    <input type="hidden" id="assignDealId">
                    <div class="mb-3">
                        <label for="assignDealUserSelect" class="form-label">Select User</label>
                        <select class="form-select" id="assignDealUserSelect" required>
                            <option value="">Loading...</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveDealAssignment()">Assign</button>
            </div>
        </div>
    </div>
</div>

<script>
const DealsManager = {
    apiUrl: '?api=true',

    async getAll() {
        try {
            const response = await fetch(this.apiUrl);
            return (await response.json()).data || [];
        } catch (e) { return []; }
    },

    async create(data) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            return await response.json();
        } catch (e) { return {success: false, message: e.message}; }
    },

    async update(id, data) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({...data, id})
            });
            return await response.json();
        } catch (e) { return {success: false, message: e.message}; }
    },

    async delete(id) {
        try {
            const response = await fetch(`${this.apiUrl}&id=${id}`, { method: 'DELETE' });
            return await response.json();
        } catch (e) { return {success: false, message: e.message}; }
    }
};

function exportDeals() {
    window.location.href = window.location.pathname + '?api=true&action=export';
}

function downloadDealsTemplate() {
    window.location.href = window.location.pathname + '?api=true&action=template';
}

async function importDeals(input) {
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
            alert(result.message);
            loadDeals(); // Reload table
        } else {
            alert('Import Error: ' + result.message);
        }
    } catch (e) {
        alert('Import failed: ' + e.message);
    }
    input.value = ''; // Reset input
}

let dealModal;
let cachedDeals = [];
let cachedProducts = [];

// Pagination State
let currentPage = 1;
const itemsPerPage = 15;

// New Global Variables for Advanced Features
let currentDealId = '';
let currentSymbol = '$';
let currencyRates = { usd: 1.00, gbp: 0.79, ngn: 1550.00, eur: 0.92 };
let defaultRates = { usd: 1.00, gbp: 0.79, ngn: 1550.00, eur: 0.92 };
let usingCustomRates = false;

const stageDescriptions = {
    value_proposition: "Define the unique value your solution provides to the client.",
    proposal_quote: "Create proposal and generate quote with pricing details.",
    negotiation_review: "Negotiate terms, handle objections, and conduct reviews.",
    qualification: "Final qualifications - credit check, contract signing, payment setup.",
    closed_won: "Deal successfully closed and won!",
    closed_lost: "Deal was lost. Document learnings for future improvement."
};
const stageOrder = ['value_proposition', 'proposal_quote', 'negotiation_review', 'qualification', 'closed_won', 'closed_lost'];


document.addEventListener('DOMContentLoaded', () => {
    loadDeals();
    
    // Initialize Modal
    const modalEl = document.getElementById('dealModal');
    if (modalEl) {
        dealModal = new bootstrap.Modal(modalEl);
    }
    
    // Load Leads for Select
    loadLeadsForSelect();
    loadCustomersForSelect();

    // Filters
    document.getElementById('searchDeals').addEventListener('input', filterDeals);
    document.getElementById('filterStage').addEventListener('change', filterDeals);
    document.getElementById('filterStatus').addEventListener('change', filterDeals);
    document.getElementById('filterOwner').addEventListener('change', filterDeals);

    // Initial Load of Products
    loadProductsDropdown();
    loadOwnersDropdown();

    // Currency Change Listener
    const currencyEl = document.getElementById('dealCurrency');
    if (currencyEl) {
        currencyEl.addEventListener('change', updateCurrencySymbols);
    }

    // Auto-populate Address Listeners
    if (document.getElementById('dealLead')) {
        document.getElementById('dealLead').addEventListener('change', (e) => populateAddress('lead', e.target));
    }
    if (document.getElementById('dealCustomer')) {
        document.getElementById('dealCustomer').addEventListener('change', (e) => populateAddress('customer', e.target));
    }
});

async function loadProductsDropdown() {
    try {
        const response = await fetch('../../views/products/?api=true');
        const result = await response.json();
        
        if (result.success && Array.isArray(result.data)) {
            cachedProducts = result.data;
        } else {
            cachedProducts = [];
        }
    } catch (e) {
        console.error('Error fetching products:', e);
        cachedProducts = [];
    }
    
    const warning = document.getElementById('noProductsWarning');
    if (warning) {
        warning.classList.toggle('d-none', cachedProducts.length > 0);
    }
}

// Global state for viewing archives
let viewingArchived = false;

async function loadDeals() {
    const tbody = document.getElementById('dealsTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';

    try {
        const url = `?api=true&archived=${viewingArchived}&_=${new Date().getTime()}`;
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            cachedDeals = result.data;
            currentPage = 1; // Reset to page 1
            // Recalculate stages counts or updates here if needed
            renderDeals(cachedDeals);
            updateArchiveToggleUI();
        } else {
             tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load deals</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading deals</td></tr>';
    }
}

function updateArchiveToggleUI() {
    const btn = document.getElementById('toggleArchiveBtn');
    if (btn) { // Ensure the button exists before trying to modify it
        if (viewingArchived) {
            btn.innerHTML = '<i class="bi bi-arrow-left-circle"></i> Show Active';
            btn.classList.replace('btn-outline-secondary', 'btn-secondary');
        } else {
            btn.innerHTML = '<i class="bi bi-archive"></i> Show Archived';
            btn.classList.replace('btn-secondary', 'btn-outline-secondary');
        }
    }
}

function toggleArchiveView() {
    viewingArchived = !viewingArchived;
    loadDeals();
}

// Bulk Actions Logic
function toggleSelectAllDeals() {
    const isChecked = document.getElementById('selectAllDeals').checked;
    document.querySelectorAll('.deal-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateBulkActionUI();
}

function updateBulkActionUI() {
    const selected = document.querySelectorAll('.deal-checkbox:checked');
    const toolbar = document.getElementById('bulkActionsToolbar');
    const selectAll = document.getElementById('selectAllDeals');
    const totalCheckboxes = document.querySelectorAll('.deal-checkbox');
    
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

async function bulkArchiveDeals() {
    const selected = Array.from(document.querySelectorAll('.deal-checkbox:checked')).map(cb => cb.value);
    
    if (selected.length === 0) return;
    
    if (confirm(`Are you sure you want to archive ${selected.length} deals?`)) {
        let successCount = 0;
        
        for (const id of selected) {
            const result = await DealsManager.delete(id);
            if (result.success) successCount++;
        }
        
        if (successCount > 0) {
            alert(`${successCount} deals archived.`); // Using alert for now as showToast isn't guaranteed global from utils without verifying
            loadDeals();
            // Reset UI handled by loadDeals -> renderDeals
        } else {
            alert('Failed to archive selected deals.');
        }
    }
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
    if (page < 1) return;
    currentPage = page;
    filterDeals();
}

function renderDeals(deals) {
    const tableBody = document.getElementById('dealsTableBody');
    tableBody.innerHTML = '';
    
    // Bulk Actions Logic
    document.getElementById('selectAllDeals').checked = false;
    updateBulkActionUI();

    // Pagination
    const totalItems = deals.length;
    renderPagination(totalItems);

    const start = (currentPage - 1) * itemsPerPage;
    const paginatedDeals = deals.slice(start, start + itemsPerPage);

    if (paginatedDeals.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No deals found</td></tr>';
        return;
    }

    paginatedDeals.forEach(deal => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.onclick = (e) => {
             // Prevent if text selection
            if (window.getSelection().toString()) return;
            viewDeal(deal.id); 
        };
        
        let sourceInfo = 'Unknown Source';
        if (deal.customer_id) sourceInfo = `${deal.customer_name || 'Customer'}`;
        else if (deal.lead_id) sourceInfo = `${deal.lead_name || 'Lead'}`;

        tr.innerHTML = `
            <td class="table-checkbox-col" onclick="event.stopPropagation()">
                <input type="checkbox" class="form-check-input deal-checkbox" value="${deal.id}" onchange="updateBulkActionUI()">
            </td>
            <td>
                <div class="fw-bold text-primary">${deal.title}</div>
            </td>
            <td>${formatCurrency(deal.value, deal.currency)}</td>
            <td><span class="badge bg-${getStageColor(deal.stage)}">${formatStage(deal.stage)}</span></td>
            <td>
                <span class="badge bg-light text-dark border">
                    <i class="bi bi-person-circle me-1"></i> 
                    ${escapeHtml(deal.assigned_to_name || 'Unassigned')}
                </span>
            </td>
            <td>
                <span class="badge bg-light text-muted border">
                    <i class="bi bi-person me-1"></i> 
                    ${escapeHtml(deal.created_by_name || 'N/A')}
                </span>
            </td>
            <td>${formatDate(deal.valid_until)}</td>
            <td>
                <span class="badge ${deal.status === 'active' ? 'bg-success' : 'bg-secondary'}">${deal.status}</span>
            </td>
            <td>${new Date(deal.created_at).toLocaleDateString()}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    ${viewingArchived ? 
                        `<button class="btn btn-success fw-bold" title="Restore" onclick="event.stopPropagation(); restoreDeal(${deal.id})">
                             <i class="bi bi-arrow-counterclockwise"></i>
                         </button>`
                        :
                        `<button class="btn btn-warning fw-bold" title="Archive" onclick="event.stopPropagation(); archiveDeal(${deal.id})">
                             <i class="bi bi-archive"></i>
                         </button>
                    <button class="btn btn-secondary fw-bold" title="Assign To" onclick="event.stopPropagation(); assignDeal(${deal.id})">
                         <i class="bi bi-person-plus"></i>
                    </button>`
                    }
                </div>
            </td>
        `;
        tableBody.appendChild(tr);
    });
}

async function loadLeadsForSelect() {
    // Assuming a similar API exists for leads or we fetch from leads view
    try {
        const response = await fetch(`../leads/index.php?api=true&_=${new Date().getTime()}`); 
        const result = await response.json();
        if (result.success) {
            const select = document.getElementById('dealLead');
            select.innerHTML = '<option value="">Select Lead...</option>';
            result.data.forEach(lead => {
                const opt = document.createElement('option');
                opt.value = lead.id;
                opt.textContent = `${lead.first_name} ${lead.last_name} (${lead.company})`;
                // Store address data
                opt.dataset.address = lead.address || '';
                opt.dataset.city = lead.city || '';
                opt.dataset.state = lead.state || '';
                opt.dataset.zip = lead.zip || '';
                opt.dataset.country = lead.country || '';
                select.appendChild(opt);
            });
        }
    } catch (e) { console.error('Error loading leads', e); }
}

async function loadCustomersForSelect() {
    try {
        const response = await fetch(`../customers/index.php?api=true&_=${new Date().getTime()}`); 
        const result = await response.json();
        if (result.success) {
            const select = document.getElementById('dealCustomer');
            select.innerHTML = '<option value="">Select Customer...</option>';
            result.data.forEach(customer => {
                const opt = document.createElement('option');
                opt.value = customer.id;
                opt.textContent = `${customer.first_name} ${customer.last_name} (${customer.company || customer.customer_code})`;
                // Store address data
                opt.dataset.address = customer.address || '';
                opt.dataset.city = customer.city || '';
                opt.dataset.state = customer.state || '';
                opt.dataset.zip = customer.zip || '';
                opt.dataset.country = customer.country || '';
                select.appendChild(opt);
            });
        }
    } catch (e) { console.error('Error loading customers', e); }
}

function populateAddress(type, select) {
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return;

    document.getElementById('dealAddress').value = opt.dataset.address || '';
    document.getElementById('dealCity').value = opt.dataset.city || '';
    document.getElementById('dealState').value = opt.dataset.state || '';
    document.getElementById('dealZip').value = opt.dataset.zip || '';
    document.getElementById('dealCountry').value = opt.dataset.country || '';
}

function toggleSourceType() {
    const type = document.querySelector('input[name="dealSourceType"]:checked').value;
    const leadWrapper = document.getElementById('leadSelectWrapper');
    const customerWrapper = document.getElementById('customerSelectWrapper');
    
    if (type === 'lead') {
        leadWrapper.classList.remove('d-none');
        customerWrapper.classList.add('d-none');
        document.getElementById('dealCustomer').value = '';
    } else {
        leadWrapper.classList.add('d-none');
        customerWrapper.classList.remove('d-none');
        document.getElementById('dealLead').value = '';
    }
}

function formatStage(stage) {
    const stages = {
        'value_proposition': 'Value Proposition',
        'proposal_quote': 'Proposal',
        'negotiation_review': 'Negotiation',
        'qualification': 'Qualification',
        'closed_won': 'Won',
        'closed_lost': 'Lost'
    };
    return stages[stage] || stage;
}

function getStageColor(stage) {
    const colors = {
        'value_proposition': 'info',
        'proposal_quote': 'primary',
        'negotiation_review': 'warning',
        'qualification': 'secondary',
        'closed_won': 'success',
        'closed_lost': 'danger'
    };
    return colors[stage] || 'secondary';
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

function formatCurrency(amount, currency = 'NGN') {
    if (currency === 'NGN') {
        return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(amount);
    }
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(amount);
}


async function loadOwnersDropdown() {
    try {
        const response = await fetch('index.php?api=true&type=users');
        const result = await response.json();
        const select = document.getElementById('filterOwner');
        if (result.success && select) {
             while (select.options.length > 1) select.remove(1);
             result.data.forEach(user => {
                 const opt = document.createElement('option');
                 opt.value = user.id;
                 opt.textContent = `${user.first_name} ${user.last_name}`;
                 select.appendChild(opt);
             });
        }
    } catch(e) {}
}

function filterDeals() {
    const ownerFilter = document.getElementById('filterOwner') ? document.getElementById('filterOwner').value : '';

    const search = document.getElementById('searchDeals').value.toLowerCase();
    const stage = document.getElementById('filterStage').value;
    const status = document.getElementById('filterStatus').value;

    const filtered = cachedDeals.filter(deal => {
        const matchesSearch = (deal.title && deal.title.toLowerCase().includes(search)) || 
                              (deal.lead_name && deal.lead_name.toLowerCase().includes(search)) || 
                              (deal.customer_name && deal.customer_name.toLowerCase().includes(search)) ||
                              (deal.lead_company && deal.lead_company.toLowerCase().includes(search));
        const matchesStage = stage ? deal.stage === stage : true;
        const matchesStatus = status ? deal.status === status : true;
        const matchesDate = checkDateRange(deal.created_at);
        const matchesOwner = !ownerFilter || (deal.assigned_to && deal.assigned_to == ownerFilter);

        return matchesSearch && matchesStage && matchesStatus && matchesOwner && matchesDate;
    });

     // Sort filtered results based on currentSortState
     const sortedFiltered = filtered.sort((a, b) => {
        let valA = a[currentSortState.column];
        let valB = b[currentSortState.column];
        
        // Handle names for joined columns
        if (currentSortState.column === 'lead_name') {
            valA = (a.lead_first_name + ' ' + a.lead_last_name).toLowerCase();
            valB = (b.lead_first_name + ' ' + b.lead_last_name).toLowerCase();
        } else if (column === 'assigned_to_name') {
             valA = (a.assigned_to_name || '').toLowerCase();
             valB = (b.assigned_to_name || '').toLowerCase();
        } else if (column === 'created_by_name') {
             valA = (a.created_by_name || '').toLowerCase();
             valB = (b.created_by_name || '').toLowerCase();
        }
        
        if (typeof valA === 'string') {
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

    renderDeals(sortedFiltered);
}

function resetFilters() {
    document.getElementById('searchDeals').value = '';
    document.getElementById('filterStage').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterOwner').value = '';
    renderDeals(cachedDeals);
}


// --- Advanced Deal Logic Ported from create.php ---

function updateCurrencySymbols() {
    const select = document.getElementById('dealCurrency');
    const option = select.options[select.selectedIndex];
    currentSymbol = option.getAttribute('data-symbol');
    
    // Update primary currency code in table headers
    const currencyCode = option.text.split('(')[0].trim(); // Extract code logic might vary but textContent is typically "USD", "GBP" etc or "USD ($)"
    
    // The select options in HTML were: value="NGN" selected>₦ (NGN)"
    // We should parse or just use value.
    const code = select.value;
    
    // Update symbols in table
    document.querySelectorAll('.currency-symbol').forEach(el => el.textContent = currentSymbol);
    
    calculateTotals();
}

function toggleDualCurrency() {
    const isChecked = document.getElementById('isDualCurrency').checked;
    const customRateIndicator = document.getElementById('customRateIndicator');
    const exchangeRateBtn = document.getElementById('exchangeRateBtn');
    const secDiv = document.getElementById('secondaryCurrencyDiv');
    const secondaryCols = document.querySelectorAll('.secondary-currency-col');

    if (isChecked) {
        customRateIndicator.classList.remove('d-none');
        exchangeRateBtn.classList.remove('d-none');
        secDiv.classList.remove('d-none');
        secondaryCols.forEach(col => col.classList.remove('d-none'));
    } else {
        customRateIndicator.classList.add('d-none');
        exchangeRateBtn.classList.add('d-none');
        secDiv.classList.add('d-none');
        secondaryCols.forEach(col => col.classList.add('d-none'));
    }
    calculateTotals();
}

function updateStageInfo() {
    const stage = document.getElementById('dealStage').value;
    const stageIndex = stageOrder.indexOf(stage);
    let progress = 0;
    
    if (stage === 'closed_won') {
        progress = 100;
    } else if (stage === 'closed_lost') {
        progress = 0;
    } else {
        const activeStages = stageOrder.filter(s => s !== 'closed_won' && s !== 'closed_lost');
        const activeIndex = activeStages.indexOf(stage);
        progress = ((activeIndex + 1) / activeStages.length) * 100;
    }
    
    const progressBar = document.getElementById('stageProgress');
    progressBar.style.width = progress + '%';
    progressBar.textContent = Math.round(progress) + '%';
    
    progressBar.className = 'progress-bar';
    if (stage === 'closed_won') progressBar.classList.add('bg-success');
    else if (stage === 'closed_lost') progressBar.classList.add('bg-danger');
    else progressBar.classList.add('bg-primary');
    
    document.getElementById('stageDescription').textContent = stageDescriptions[stage];
}

function moveToNextStage() {
    const currentStage = document.getElementById('dealStage').value;
    const currentIndex = stageOrder.indexOf(currentStage);
    
    if (currentIndex < 3) { // Up to qualification
        const nextStage = stageOrder[currentIndex + 1];
        document.getElementById('dealStage').value = nextStage;
        updateStageInfo();
        showToast('Moved to next stage: ' + stageDescriptions[nextStage], 'success');
    } else {
        showToast('Use "Mark as Won" or "Mark as Lost" to close the deal', 'info');
    }
}

// --- Product & Calculation Logic ---

function addDealProduct() {
    if (cachedProducts.length === 0) {
        showToast('No products available. Create products first.', 'warning');
        return;
    }
    hidePrintButton();
    
    const tbody = document.querySelector('#dealProductsTable tbody');
    const tr = document.createElement('tr');
    
    // Check if dual currency is active to show/hide columns immediately
    const isDualCurrency = document.getElementById('isDualCurrency').checked;
    const secondaryDisplayClass = isDualCurrency ? '' : 'd-none';

    const productOptions = cachedProducts.map(product => {
         const displayName = product.unit ? `${product.name} (${product.unit})` : product.name;
         // Note: Assuming create.php used 'price' but index.php might use it differently. Standardizing.
         return `<option value="${product.id}" data-name="${product.name}" data-price="${product.price}" data-currency="${product.currency || 'NGN'}" data-description="${product.description || ''}">${displayName}</option>`;
    }).join('');

    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm deal-product-select" onchange="selectProductFromDropdown(this)" required>
                <option value="">-- Select Product --</option>
                ${productOptions}
            </select>
            <input type="hidden" class="product-name-hidden">
        </td>
        <td><textarea class="form-control form-control-sm deal-details" rows="1" style="resize: none;">${''}</textarea></td>
        <td><input type="number" class="form-control form-control-sm deal-qty" value="1" min="1" onchange="calculateDealProductRow(this)"></td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text product-currency-symbol">$</span>
                <input type="number" class="form-control form-control-sm deal-price" step="0.01" oninput="calculateDealProductRow(this)">
            </div>
        </td>
        <td><input type="text" class="form-control form-control-sm deal-total" readonly style="background-color: #f8f9fa;"></td>
        
        <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control form-control-sm sec-unit-price" readonly style="background-color: #f8f9fa;"></td>
        <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control form-control-sm sec-total" readonly style="background-color: #f8f9fa;"></td>
        
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeDealProductRow(this)"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
}

function selectProductFromDropdown(selectElement) {
    const row = selectElement.closest('tr');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    
    if (!selectedOption.value) {
        // Clear fields
        row.querySelector('.deal-details').value = '';
        row.querySelector('.deal-price').value = '';
        row.querySelector('.deal-total').value = '';
        row.querySelector('.product-currency-symbol').textContent = '$';
        return;
    }
    
    const productName = selectedOption.getAttribute('data-name');
    const productPrice = parseFloat(selectedOption.getAttribute('data-price'));
    const productCurrency = selectedOption.getAttribute('data-currency');
    const productDescription = selectedOption.getAttribute('data-description');
    
    // Map code to symbol
    let symbol = '$';
    if (productCurrency === 'NGN') symbol = '₦';
    else if (productCurrency === 'GBP') symbol = '£';
    else if (productCurrency === 'EUR') symbol = '€';
    
    row.querySelector('.product-name-hidden').value = productName; // For backup/display
    row.querySelector('.deal-details').value = productDescription || 'No description';
    row.querySelector('.deal-price').value = productPrice.toFixed(2);
    row.querySelector('.product-currency-symbol').textContent = symbol;
    
    calculateDealProductRow(row.querySelector('.deal-price'));
}

function calculateDealProductRow(input) {
    const tr = input.closest('tr');
    const qty = parseFloat(tr.querySelector('.deal-qty').value) || 0;
    const price = parseFloat(tr.querySelector('.deal-price').value) || 0;
    const total = qty * price;
    
    tr.querySelector('.deal-total').value = total.toFixed(2);
    calculateTotals();
}

function removeDealProductRow(btn) {
    btn.closest('tr').remove();
    hidePrintButton();
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    document.querySelectorAll('.deal-total').forEach(input => {
        subtotal += parseFloat(input.value) || 0;
    });
    
    // VAT 7.5%
    const vat = subtotal * 0.075;
    const total = subtotal + vat;
    
    document.getElementById('dealSubtotal').innerText = currentSymbol + ' ' + subtotal.toFixed(2);
    document.getElementById('dealVat').innerText = currentSymbol + ' ' + vat.toFixed(2);
    document.getElementById('dealProductsTotal').innerText = currentSymbol + ' ' + total.toFixed(2);
    
    // Update main Deal Value field
    document.getElementById('dealValue').value = total.toFixed(2);
    
    // Dual Currency check
    const isDual = document.getElementById('isDualCurrency').checked;
    if (isDual) {
        updateSecondaryCurrencyTotal(subtotal, vat, total);
    }
}

function updateSecondaryCurrencyTotal(primarySubtotal, primaryVat, primaryTotal) {
    const secCurrencySelect = document.getElementById('secCurrencySelect');
    if (!secCurrencySelect) return; 

    // Getting rate directly from manual input or predefined map?
    // create.php logic used a mix. Let's stick to the manual input if active or default map.
    
    const selectedCurrencyCode = secCurrencySelect.value; // NGN, GBP...
    
    let conversionRate = 1.00;
    let currencySymbol = '$';
    
    // Generic Rate Lookup (Base USD)
    const getRate = (code) => {
        if (code === 'USD') return 1.00;
        if (code === 'NGN') return currencyRates.ngn || 1550.00;
        if (code === 'GBP') return currencyRates.gbp || 0.79;
        if (code === 'EUR') return currencyRates.eur || 0.92;
        return 1.00;
    };
    
    // Calculate Target Rate
    // Formula: Rate = TargetBaseRate / SourceBaseRate
    // Example: Pri=USD(1), Sec=NGN(1550) -> Rate = 1550/1 = 1550
    // Example: Pri=NGN(1550), Sec=USD(1) -> Rate = 1/1550 = 0.000645
    
    // 1. Get Primary Currency Code
    const primaryCode = document.getElementById('dealCurrency') ? document.getElementById('dealCurrency').value : 'NGN';
    
    // 2. Get Rates relative to USD Base
    const primaryRate = getRate(primaryCode);
    const secondaryRate = getRate(selectedCurrencyCode);
    
    // 3. Calculate Cross Rate
    conversionRate = secondaryRate / primaryRate;
    
    // Set Symbol
    if (selectedCurrencyCode === 'GBP') currencySymbol = '£';
    else if (selectedCurrencyCode === 'NGN') currencySymbol = '₦';
    else if (selectedCurrencyCode === 'USD') currencySymbol = '$';
    else if (selectedCurrencyCode === 'EUR') currencySymbol = '€';
    
    // Update hidden exchange rate field for saving
    const rateInput = document.getElementById('exchangeRate');
    if (rateInput) rateInput.value = conversionRate;
    
    // Update Rate Display Card
    // Update Rate Display Card
    const rateDisplay = document.getElementById('defaultRateDisplay');
    const rateStatus = document.getElementById('rateStatus');
    const symbolDisplay = document.getElementById('exchRateSymbol');
    const baseSymbolDisplay = document.getElementById('baseRateSymbol');
    
    if (symbolDisplay) symbolDisplay.textContent = currencySymbol;
    if (baseSymbolDisplay) baseSymbolDisplay.textContent = currentSymbol;
    
    if (rateDisplay) {
        // Format to standard 2 decimals or more if needed
        rateDisplay.textContent = conversionRate.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 4});
    }
    
    if (rateStatus) {
        if (usingCustomRates) {
            rateStatus.textContent = 'Custom Rate';
            rateStatus.className = 'text-warning d-block fw-bold'; // Highlight custom rate
        } else {
            rateStatus.textContent = 'Default Rate';
            rateStatus.className = 'text-white-50 d-block';
        }
    }
    
    document.querySelectorAll('.sec-currency-code').forEach(el => el.textContent = selectedCurrencyCode);

    try {
        const rows = document.querySelectorAll('#dealProductsTable tbody tr');
        rows.forEach(tr => {
            // Safely parse primary values (remove commas just in case, though usually clean)
            const priceVal = tr.querySelector('.deal-price').value.toString().replace(/,/g, '');
            const totalVal = tr.querySelector('.deal-total').value.toString().replace(/,/g, '');
            
            const primaryPrice = parseFloat(priceVal) || 0;
            const primaryTotal = parseFloat(totalVal) || 0;
            
            const secPrice = primaryPrice * conversionRate;
            const secTotal = primaryTotal * conversionRate;
            
            const secPriceInput = tr.querySelector('.sec-unit-price');
            const secTotalInput = tr.querySelector('.sec-total');
            
            // Format with commas for display
            if (secPriceInput) secPriceInput.value = currencySymbol + secPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            if (secTotalInput) secTotalInput.value = currencySymbol + secTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        });
        
        // Update footer secondary totals
        const secSubtotal = primarySubtotal * conversionRate;
        const secVat = primaryVat * conversionRate;
        const secGrandTotal = primaryTotal * conversionRate;
        
        document.getElementById('secondarySubtotalDisplay').innerText = currencySymbol + secSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('secondaryVatDisplay').innerText = currencySymbol + secVat.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('secondaryTotalDisplay').innerText = currencySymbol + secGrandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    } catch (e) {
        console.error('Calculation Error:', e);
        showToast('Error calculating totals: ' + e.message, 'error');
    }
}

// --- Exchange Rate Modal Logic ---

function openExchangeRateModal() {
    document.getElementById('rateGBP').value = currencyRates.gbp;
    document.getElementById('rateNGN').value = currencyRates.ngn;
    document.getElementById('rateEUR').value = currencyRates.eur;
    
    const modal = new bootstrap.Modal(document.getElementById('exchangeRateModal'));
    modal.show();
}

function applyCustomRates() {
    const gbp = parseFloat(document.getElementById('rateGBP').value);
    const ngn = parseFloat(document.getElementById('rateNGN').value);
    const eur = parseFloat(document.getElementById('rateEUR').value);
    
    if (gbp > 0) currencyRates.gbp = gbp;
    if (ngn > 0) currencyRates.ngn = ngn;
    if (eur > 0) currencyRates.eur = eur;
    
    usingCustomRates = true; // Set flag
    calculateTotals(); // Re-calc with new rates
}

function resetRatesToDefault() {
    currencyRates = {...defaultRates};
    usingCustomRates = false; // Reset flag
    calculateTotals();
}

// --- Print Modal Logic ---

// function openPrintModal(type = 'QUOTE') {
//     // Hide the underlying Deal Form so Preview stands alone - TEMPORARILY DISABLED FOR DEBUGGING
//     // if (typeof dealModal !== 'undefined') dealModal.hide();

//     const printModalEl = document.getElementById('printModal');
//     if (!printModalEl) {
//         console.error('Print modal element not found!');
//         alert('Error: Print modal template missing.');
//         return;
//     }
//     const modal = bootstrap.Modal.getOrCreateInstance(printModalEl);
    
//     // Restore Deal Form when Print Modal is closed
//     // printModalEl.addEventListener('hidden.bs.modal', function () {
//     //     if (typeof dealModal !== 'undefined') dealModal.show();
//     // }, { once: true });

//     modal.show();
    
//     // Small delay to ensure modal is in DOM
//     setTimeout(() => {
//         generatePrintContent(type);
//     }, 100);
// }

function openPrintModal(type = 'QUOTE') {
    console.log('Opening Print Modal for:', type);
    
    // 1. Get print modal element
    const printModalEl = document.getElementById('printModal');
    if (!printModalEl) {
        alert('Critical Error: Print Modal Element Missing from DOM');
        return;
    }

    // 2. Initialize Bootstrap Modal
    const modal = bootstrap.Modal.getOrCreateInstance(printModalEl);
    
    // 3. Set content BEFORE showing to avoid blank flash or if show fails visually
    const preview = document.getElementById('printPreview');
    if (preview) preview.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div><p>Generating Preview...</p></div>';

    // 4. Show Modal (Keep Deal Modal open behind it for now to avoid state loss/backdrop glitch)
    // We will increase z-index of print modal just in case
    printModalEl.style.zIndex = 1060; // Higher than standard 1055
    modal.show();

    // 5. Generate content with slight delay to ensure rendering
    setTimeout(() => {
        try {
            generatePrintContent(type);
        } catch (e) {
            console.error('Error triggering generation:', e);
            if (preview) preview.innerHTML = '<div class="alert alert-danger">Failed to generate preview: ' + e.message + '</div>';
        }
    }, 200);
}

const numberToWords = (n) => {
    try {
        const string = n.toString(), units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'], tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'], scales = ['', 'Thousand', 'Million', 'Billion', 'Trillion', 'Quadrillion', 'Quintillion', 'Sextillion', 'Septillion', 'Octillion', 'Nonillion'];
        let start, end, chunks, chunksLen, chunk, ints, i, word, words;
        
        if (parseInt(string) === 0) return 'Zero';
        
        start = string.length;
        chunks = [];
        while (start > 0) {
            end = start;
            chunks.push(string.slice((start = Math.max(0, start - 3)), end));
        }
        chunksLen = chunks.length;
        if (chunksLen > scales.length) return '';
        words = [];
        for (i = 0; i < chunksLen; i++) {
            chunk = parseInt(chunks[i]);
            if (chunk) {
                ints = chunks[i].split('').reverse().map(parseFloat);
                if (ints[1] === 1) ints[0] += 10;
                if ((word = scales[i])) words.push(word);
                if ((word = units[ints[0]])) words.push(word);
                if ((word = tens[ints[1]])) words.push(word);
                if ((word = units[ints[2]])) words.push(word + ' Hundred');
            }
        }
        return words.reverse().join(' ');
    } catch (e) {
        console.error('NumberToWords Error:', e);
        return n.toString(); // Fallback
    }
}

function generatePrintContent(type = 'QUOTE') {
    try {
        console.log('Generating print content for type:', type);
        
        const preview = document.getElementById('printPreview');
        if (!preview) {
             console.error('Print preview element not found');
             return;
        }

        // Check global variables/elements for secondary currency
        const isDual = document.getElementById('isDualCurrency') ? document.getElementById('isDualCurrency').checked : false;
        
        const title = document.getElementById('dealTitle').value || 'Untitled Deal';
        const description = document.getElementById('dealDescription').value || ''; 
        const terms = document.getElementById('dealTerms').value || '';
        
        // Totals - safely get elements
        const getElText = (id) => document.getElementById(id) ? document.getElementById(id).textContent : '0.00';
        
        const totalStr = getElText('dealProductsTotal');
        const subtotalStr = getElText('dealSubtotal');
        const vatStr = getElText('dealVat');
        const secSubtotalStr = getElText('secondarySubtotalDisplay');
        const secVatStr = getElText('secondaryVatDisplay');
        const secTotalStr = getElText('secondaryTotalDisplay');

        // Parse numeric value for words
        const totalClean = parseFloat(totalStr.replace(/[^0-9.-]+/g, "")) || 0;
        const secTotalClean = parseFloat(secTotalStr.replace(/[^0-9.-]+/g, "")) || 0;
        
        // Ensure currentSymbol is defined (fallback to $)
        const symbol = (typeof currentSymbol !== 'undefined' && currentSymbol) ? currentSymbol : '$';

        let totalInWords = numberToWords(Math.floor(totalClean)) + ' ' + (symbol === '$' ? 'Dollars' : symbol);
        
        // Secondary Words
        const secCodeEl = document.getElementById('secCurrencySelect');
        const secCode = secCodeEl ? secCodeEl.value : 'NGN';
        
        let secCurrencyName = secCode;
        if (secCode === 'NGN') secCurrencyName = 'Naira';
        else if (secCode === 'GBP') secCurrencyName = 'Pounds';
        else if (secCode === 'EUR') secCurrencyName = 'Euros';
        else if (secCode === 'NGN') secCurrencyName = 'Naira';

        let secTotalInWords = numberToWords(Math.floor(secTotalClean)) + ' ' + secCurrencyName;

        let lineItemsHTML = '';
        const rows = document.querySelectorAll('#dealProductsTable tbody tr');
        
        rows.forEach(tr => {
            const nameSelect = tr.querySelector('.deal-product-select');
            const productName = nameSelect && nameSelect.options && nameSelect.options[nameSelect.selectedIndex] 
                ? nameSelect.options[nameSelect.selectedIndex].text 
                : '';
                
            if (!productName || productName.includes('-- Select')) return; 

            const details = tr.querySelector('.deal-details').value || '';
            const qty = tr.querySelector('.deal-qty').value || 0;
            const price = tr.querySelector('.deal-price').value || 0;
            const lineTotal = tr.querySelector('.deal-total').value || 0;
            const secPriceVal = tr.querySelector('.sec-unit-price') ? tr.querySelector('.sec-unit-price').value : 0; 
            const secTotalVal = tr.querySelector('.sec-total') ? tr.querySelector('.sec-total').value : 0;

            lineItemsHTML += `
                <tr>
                    <td style="padding: 5px; border: 1px solid #333;">${productName}</td>
                    <td style="padding: 5px; border: 1px solid #333; font-size: 11px;">${details}</td>
                    <td style="padding: 5px; border: 1px solid #333; text-align: center;">${qty}</td>
                    <td style="padding: 5px; border: 1px solid #333; text-align: right;">${symbol}${price}</td>
                    <td style="padding: 5px; border: 1px solid #333; text-align: right;"><strong>${symbol}${lineTotal}</strong></td>
                    ${isDual ? `<td style="padding: 5px; border: 1px solid #333; text-align: right;">${secPriceVal}</td>` : ''}
                    ${isDual ? `<td style="padding: 5px; border: 1px solid #333; text-align: right;"><strong>${secTotalVal}</strong></td>` : ''}
                </tr>
            `;
        });
        
        // Dynamic Data
        const termLabels = {
            'QUOTE': { num: 'Quote Number', date: 'Quote Date' },
            'INVOICE': { num: 'Invoice Number', date: 'Invoice Date' },
            'PROPOSAL': { num: 'Proposal Number', date: 'Proposal Date' }
        };
        
        // Default to Quote if unknown
        const labels = termLabels[type] || termLabels['QUOTE'];
        
        // Generate Document Number based on Type, Year and ID
        const yearShort = new Date().getFullYear().toString().substr(-2);
        // Prefix: QUO, INV, PRO, QTN (for Quotation if favored?)
        let prefix = 'QUO';
        if(type === 'INVOICE') prefix = 'INV';
        if(type === 'PROPOSAL') prefix = 'PRO';
        
        const dealIdSafe = (typeof currentDealId !== 'undefined' && currentDealId) ? currentDealId : '000';
        const docNum = `${prefix}/${yearShort}/${String(dealIdSafe).replace(/[^0-9]/g, '').padStart(4, '0')}`;
    
    // Store for saving
    const docNumInput = document.getElementById('generatedDocNum');
    if(docNumInput) docNumInput.value = docNum;
    const docTypeInput = document.getElementById('generatedDocType');
    if(docTypeInput) docTypeInput.value = type;

    const dateStr = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        
        // Expiry Date (Valid Until) logic
        const closeDateEl = document.getElementById('dealCloseDate');
        const closeDateVal = closeDateEl ? closeDateEl.value : null;
        const expiryDate = closeDateVal ? new Date(closeDateVal).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : 'Open';

        // Customer / Bill To Info (Try to get from DOM or inputs)
        let billToName = "Customer";
        let billToCompany = "";
        
        const sourceTypeCust = document.getElementById('sourceTypeCustomer');
        if (sourceTypeCust && sourceTypeCust.checked) {
            const sel = document.getElementById('dealCustomer');
            billToName = sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].text : "Customer";
        } else {
            const sel = document.getElementById('dealLead');
            billToName = sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].text : "Lead";
        }

        const showTotals = type !== 'PROPOSAL';

        preview.innerHTML = `
            <div style="font-family: 'Arial Narrow', Arial, sans-serif; color: #000; font-size: 14px; line-height: 1.5; position: relative; min-height: 800px;">
                
                <!-- WATERMARK -->
                <div style="position: absolute; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 100px; color: rgba(0,0,0,0.08); white-space: nowrap; z-index: 0; pointer-events: none; font-weight: bold; border: 5px solid rgba(0,0,0,0.08); padding: 10px 40px; border-radius: 20px;">
                    ${type}
                </div>

                <!-- HEADER -->
                <div style="position: relative; z-index: 1; display: flex; justify-content: space-between; margin-bottom: 25px;">
                    <div style="width: 55%;">
                        <img src="../../assets/images/bbsllogo.webp" alt="Baye Business Solutions" style="max-height: 70px; margin-bottom: 10px; display: block;">
                        <h3 style="margin: 0; color: #12b1b0; font-size: 20px;">Baye Business Solutions Limited</h3>
                        35 Awoniyi Elemo Street, Off Lateef Salami Street<br>
                        Off Murtala Muhammed International Airport Road<br>
                        Ajao Estate, Lagos.<br>
                        <strong>Company ID:</strong> RC:1275907<br>
                        <strong>Tax ID:</strong> 18124995-0001<br>
                        <strong>VAT ID:</strong> 100021275907
                    </div>
                    <div style="width: 40%; text-align: right;">
                        <table style="width: 100%; margin-top: 10px; border-collapse: collapse;">
                            <tr><td style="text-align: right; padding: 2px;"><strong>${labels.num}:</strong></td><td style="text-align: right; padding: 2px;">${docNum}</td></tr>
                            <tr><td style="text-align: right; padding: 2px;"><strong>${labels.date}:</strong></td><td style="text-align: right; padding: 2px;">${dateStr}</td></tr>
                            <tr><td style="text-align: right; padding: 2px;"><strong>Expiry Date:</strong></td><td style="text-align: right; padding: 2px;">${expiryDate}</td></tr>
                        </table>
                        <div style="margin-top: 15px; text-align: left; border: 1px solid #ccc; padding: 15px;">
                            <strong>Bill To:</strong><br>
                            <span style="font-size: 16px; font-weight: bold;">${billToName}</span><br>
                            ${billToCompany}
                        </div>
                    </div>
                </div>

                <h4 style="border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 15px; font-size: 18px;">${title}</h4>

                <!-- PRODUCTS TABLE -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;">
                    <thead>
                        <tr style="background: #12b1b0; color: white;">
                            <th style="padding: 8px; border: 1px solid #333; text-align: left;">Product</th>
                            <th style="padding: 8px; border: 1px solid #333; text-align: left;">Details</th>
                            <th style="padding: 8px; border: 1px solid #333; text-align: center;">Qty</th>
                            <th style="padding: 8px; border: 1px solid #333; text-align: right;">Unit Price (${symbol})</th>
                            <th style="padding: 8px; border: 1px solid #333; text-align: right;">Total (${symbol})</th>
                            ${isDual ? `<th style="padding: 8px; border: 1px solid #333; text-align: right;">Unit (${secCode === 'NGN' ? '₦' : (secCode === 'USD' ? '$' : (secCode === 'GBP' ? '£' : (secCode === 'EUR' ? '€' : secCode)))})</th>` : ''}
                            ${isDual ? `<th style="padding: 8px; border: 1px solid #333; text-align: right;">Total (${secCode === 'NGN' ? '₦' : (secCode === 'USD' ? '$' : (secCode === 'GBP' ? '£' : (secCode === 'EUR' ? '€' : secCode)))})</th>` : ''}
                        </tr>
                    </thead>
                    <tbody>
                        ${lineItemsHTML}
                    </tbody>
                    ${showTotals ? `
                    <tfoot>
                         <tr>
                            <td colspan="4" style="text-align: right; padding: 8px; border: 1px solid #333;"><strong>Subtotal:</strong></td>
                            <td style="text-align: right; padding: 8px; border: 1px solid #333;"><strong>${subtotalStr}</strong></td>
                            ${isDual ? `<td style="text-align: right; padding: 8px; border: 1px solid #333;">-</td>` : ''}
                            ${isDual ? `<td style="text-align: right; padding: 8px; border: 1px solid #333;"><strong>${secSubtotalStr}</strong></td>` : ''}
                        </tr>
                         <tr>
                            <td colspan="4" style="text-align: right; padding: 8px; border: 1px solid #333;"><strong>VAT (7.5%):</strong></td>
                            <td style="text-align: right; padding: 8px; border: 1px solid #333;"><strong>${vatStr}</strong></td>
                            ${isDual ? `<td style="text-align: right; padding: 8px; border: 1px solid #333;">-</td>` : ''}
                            ${isDual ? `<td style="text-align: right; padding: 8px; border: 1px solid #333;"><strong>${secVatStr}</strong></td>` : ''}
                        </tr>
                         <tr style="background-color: #f0f0f0;">
                            <td colspan="4" style="text-align: right; padding: 8px; border: 1px solid #333;"><strong>Total (Inc. VAT):</strong></td>
                            <td style="text-align: right; padding: 8px; border: 1px solid #333;"><strong>${totalStr}</strong></td>
                            ${isDual ? `<td style="text-align: right; padding: 8px; border: 1px solid #333;">-</td>` : ''}
                            ${isDual ? `<td style="text-align: right; padding: 8px; border: 1px solid #333;"><strong>${secTotalStr}</strong></td>` : ''}
                        </tr>
                    </tfoot>
                    ` : ''}
                </table>
                
                ${showTotals ? `
                <div style="margin-bottom: 25px; font-size: 13px; font-weight: bold;">
                    Amount in Words: ${totalInWords} Only.<br>
                    ${isDual ? `Amount in Words (${secCode}): ${secTotalInWords} Only.` : ''}
                </div>
                ` : ''}

                <!-- TERMS -->
                ${terms ? `
                <div style="margin-top: 15px; font-size: 12px; color: #333;">
                    <strong>Terms & Conditions:</strong><br>
                    ${terms}
                </div>` : ''}
                
                <!-- BANK DETAILS -->
                <div style="border-top: 2px solid #333; padding-top: 15px; display: flex; font-size: 12px; margin-top: 25px;">
                    <div style="width: 48%; padding-right: 2%;">
                        <h5 style="margin: 0 0 8px 0; text-decoration: underline; font-size: 13px;">NAIRA ACCOUNT DETAILS</h5>
                        <strong>ACCOUNT NAME:</strong> Baye Business Solutions Limited<br>
                        <strong>BANK NAME:</strong> Access Bank Plc<br>
                        <strong>BANK BRANCH:</strong> Palm Avenue, Mushin, Lagos.<br>
                        <strong>ACCOUNT NUMBER:</strong> 0701772961 (NGN)
                    </div>
                    <div style="width: 48%; border-left: 1px solid #ccc; padding-left: 2%;">
                        <h5 style="margin: 0 0 8px 0; text-decoration: underline; font-size: 13px;">DOLLAR ACCOUNT DETAILS</h5>
                        <strong>ACCOUNT NAME:</strong> Baye Business Solutions Limited<br>
                        <strong>BANK NAME:</strong> Access Bank Plc<br>
                        <strong>BANK BRANCH:</strong> 56 Palm Avenue, Mushin, Lagos.<br>
                        <strong>ACCOUNT NUMBER:</strong> 1497195057 (USD)<br>
                        <strong>SORT CODE:</strong> 044150518 <br>
                        <strong>SWIFTCODE:</strong> ABNGNGLA<br>
                        <strong>INTERMEDIARY BANK:</strong> Citibank New York, New York, N.Y 100043<br>
                        <strong>INTERMEDIARY SWIFT:</strong> CITIUS33<br>
                        <strong>ROUTING NUMBER:</strong> 021000089
                    </div>
                </div>

                <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #666; border-top: 1px solid #eee; padding-top: 15px;">
                    <strong>Emails:</strong> info@bayebusinesssolutions.com, bayebusinesssolutions@outlook.com | <strong>Website:</strong> www.bayebusinesssolutions.com<br>
                    Baye Business Solutions Limited | RC:1275907
                </div>
            </div>
        `;
    } catch(e) {
        console.error('Error generating print content:', e);
        const preview = document.getElementById('printPreview');
        if(preview) {
             preview.innerHTML = `<div class="alert alert-danger">Error rendering preview: ${e.message}</div>`;
        }
    }
}

async function printDocument() {
    // Save the document record first
    const docNum = document.getElementById('generatedDocNum').value;
    const type = document.getElementById('generatedDocType').value;
    const dealId = currentDealId ? currentDealId.replace(/[^0-9]/g, '') : null;
    
    if (docNum && dealId) {
        try {
            await fetch('?api=true&action=documents', {
                 method: 'POST',
                 headers: {'Content-Type': 'application/json'},
                 body: JSON.stringify({
                     deal_id: dealId,
                     type: type,
                     document_number: docNum,
                     // Optional contentsnapshot could be passed here if we want to save HTML
                 })
            });
            // Refresh documents list if deal modal is open (it's hidden but we can refresh cache/UI)
            if (dealId) loadDealDocuments(dealId);
        } catch(e) {
            console.error('Failed to save document record', e);
        }
    }
    
    // Trigger Print
    const content = document.getElementById('printPreview').innerHTML;
    const originalContent = document.body.innerHTML;

    document.body.innerHTML = content;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload(); // Reload to restore event listeners and state
}

async function loadDealDocuments(dealId) {
    const listContainer = document.getElementById('dealDocumentsList');
    if (!listContainer) return;
    
    listContainer.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading history...</div>';
    
    try {
        const res = await fetch(`?api=true&action=documents&deal_id=${dealId}`);
        const result = await res.json();
        
        if (result.success && result.data.length > 0) {
            let html = '<ul class="list-group">';
            result.data.forEach(doc => {
                 html += `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${doc.type}</strong> - ${doc.document_number}
                            <br><small class="text-muted">${new Date(doc.created_at).toLocaleString()}</small>
                        </div>
                        <span class="badge bg-secondary">Saved</span>
                    </li>
                 `;
            });
            html += '</ul>';
            listContainer.innerHTML = html;
        } else {
            listContainer.innerHTML = '<p class="text-muted">No documents generated yet.</p>';
        }
    } catch(e) {
        listContainer.innerHTML = '<p class="text-danger">Failed to load documents.</p>';
    }
}

// --- CRUD Actions ---

async function saveDeal() {
    const id = document.getElementById('dealId').value;
    
    const lineItems = [];
    document.querySelectorAll('#dealProductsTable tbody tr').forEach(tr => {
        const select = tr.querySelector('.deal-product-select');
        const productId = select.value;
        const productName = select.options[select.selectedIndex]?.getAttribute('data-name'); // fallback
        const desc = tr.querySelector('.deal-details').value;
        const qty = tr.querySelector('.deal-qty').value;
        const price = tr.querySelector('.deal-price').value;
        const total = tr.querySelector('.deal-total').value;
        
        if (productId) {
            lineItems.push({
                productId: productId,
                productName: productName,
                description: desc,
                quantity: qty,
                price: price,
                total: total
            });
        }
    });

    const data = {
        title: document.getElementById('dealTitle').value,
        lead_id: document.querySelector('input[name="dealSourceType"]:checked').value === 'lead' ? document.getElementById('dealLead').value : null,
        customer_id: document.querySelector('input[name="dealSourceType"]:checked').value === 'customer' ? document.getElementById('dealCustomer').value : null,
        value: document.getElementById('dealValue').value,
        currency: document.getElementById('dealCurrency').value,
        is_dual_currency: document.getElementById('isDualCurrency').checked ? 1 : 0,
        exchange_rate: document.getElementById('exchangeRate').value,
        description: document.getElementById('dealDescription').value,
        terms_conditions: document.getElementById('dealTerms').value,
        stage_notes: document.getElementById('stageNotes').value,
        stage: document.getElementById('dealStage').value,
        valid_until: document.getElementById('dealCloseDate').value,
        due_date: document.getElementById('dealDueDate').value,
        status: document.getElementById('dealStatus').value,
        status: document.getElementById('dealStatus').value,
        address: document.getElementById('dealAddress').value,
        city: document.getElementById('dealCity').value,
        state: document.getElementById('dealState').value,
        zip: document.getElementById('dealZip').value,
        country: document.getElementById('dealCountry').value,
        lineItems: lineItems
    };
    
    if (!data.title || !data.value || !data.stage) {
        showToast('Please fill in all required fields', 'warning'); // Changed to warning for consistency
        return;
    }

    let result;
    if (id) {
        result = await DealsManager.update(id, data);
    } else {
        result = await DealsManager.create(data);
    }

    if (result.success) {
        // Refresh table and cache
        await loadDeals();
        
        showToast('Deal saved successfully', 'success');
        
        // Switch to View Mode using the ID (existing or new)
        const savedId = id ? id : (result.data && result.data.id ? result.data.id : null);
        
        if (savedId) {
            viewDeal(savedId);
        }

        // Refresh dropdowns to show updated addresses for next deal
        loadCustomersForSelect();
        loadLeadsForSelect();
    } else {
        showToast('Error saving deal: ' + result.message, 'error');
    }
}

async function viewDeal(id) {
    let deal = cachedDeals.find(d => d.id == id);
    if (!deal) return; 
    
    try {
        const response = await fetch(`?api=true&id=${id}`);
        const result = await response.json();
        if (result.success) deal = result.data;
    } catch(e) {}
    
    currentDealId = 'DEAL-' + String(deal.id).padStart(3, '0'); 
    
    loadDealDocuments(deal.id);
    
    // Populate Form Fields
    document.getElementById('dealId').value = deal.id;
    document.getElementById('dealTitle').value = deal.title;
    
    if (deal.customer_id) {
        document.getElementById('sourceTypeCustomer').checked = true;
        document.getElementById('dealCustomer').value = deal.customer_id;
    } else {
        document.getElementById('sourceTypeLead').checked = true;
        document.getElementById('dealLead').value = deal.lead_id || '';
    }
    toggleSourceType();
    document.getElementById('dealValue').value = deal.value;
    document.getElementById('dealStage').value = deal.stage;
    document.getElementById('dealCloseDate').value = deal.valid_until || '';
    
    document.getElementById('dealCurrency').value = deal.currency || 'NGN';
    document.getElementById('dealDueDate').value = deal.due_date || '';
    document.getElementById('dealDescription').value = deal.description || '';
    document.getElementById('dealTerms').value = deal.terms_conditions || '';
    document.getElementById('stageNotes').value = deal.stage_notes || '';
    document.getElementById('dealStatus').value = deal.status || 'active';
    
    document.getElementById('dealAddress').value = deal.address || '';
    document.getElementById('dealCity').value = deal.city || '';
    document.getElementById('dealState').value = deal.state || '';
    document.getElementById('dealZip').value = deal.zip || '';
    document.getElementById('dealCountry').value = deal.country || '';
    
    const isDual = deal.is_dual_currency == 1;
    document.getElementById('isDualCurrency').checked = isDual;
    toggleDualCurrency();
    
    const savedRate = parseFloat(deal.exchange_rate) || 1.0;
    document.getElementById('exchangeRate').value = savedRate;
    
    // Heuristic to restore secondary currency and rate state
    if (isDual && savedRate !== 1.0) {
        let predictedCurrency = 'NGN';
        if (savedRate > 100) predictedCurrency = 'NGN';
        else if (savedRate < 0.85) predictedCurrency = 'GBP'; 
        else if (savedRate < 1.0) predictedCurrency = 'EUR';
        
        const secSelect = document.getElementById('secCurrencySelect');
        if (secSelect) secSelect.value = predictedCurrency;
        
        // Update global rates to match saved
        if (predictedCurrency === 'NGN') currencyRates.ngn = savedRate;
        if (predictedCurrency === 'GBP') currencyRates.gbp = savedRate;
        if (predictedCurrency === 'EUR') currencyRates.eur = savedRate;
        
        // Check if it deviates significantly from default to flag as custom
        const def = defaultRates[predictedCurrency.toLowerCase()] || 1.0;
        if (Math.abs(savedRate - def) > 0.001) {
            usingCustomRates = true;
        } else {
             usingCustomRates = false;
        }
    } else {
        usingCustomRates = false;
    }
    
    updateStageInfo();
    updateCurrencySymbols();

    showPrintButton();
    attachUnsavedChangesListeners();

    const tbody = document.querySelector('#dealProductsTable tbody');
    tbody.innerHTML = '';
    
    if (deal.lineItems && deal.lineItems.length > 0) {
        deal.lineItems.forEach(item => {
             const tr = document.createElement('tr');
             
            const isDualCurrency = document.getElementById('isDualCurrency').checked;
            const secondaryDisplayClass = isDualCurrency ? '' : 'd-none';
            
            const productOptions = cachedProducts.map(product => {
                const displayName = product.unit ? `${product.name} (${product.unit})` : product.name;
                const selected = product.id == item.product_id ? 'selected' : '';
                return `<option value="${product.id}" data-name="${product.name}" data-price="${product.price}" data-description="${product.description}" ${selected}>${displayName}</option>`;
            }).join('');

            tr.innerHTML = `
                <td>
                    <select class="form-select form-select-sm deal-product-select" onchange="selectProductFromDropdown(this)" required>
                        <option value="">-- Select --</option>
                        ${productOptions}
                    </select>
                     <input type="hidden" class="product-name-hidden" value="${item.product_name}">
                </td>
                <td><textarea class="form-control form-control-sm deal-details" rows="1" readonly style="resize: none; background-color: #f8f9fa;">${item.description || ''}</textarea></td>
                <td><input type="number" class="form-control form-control-sm deal-qty" value="${item.quantity}" min="1" onchange="calculateDealProductRow(this)"></td>
                <td><input type="number" class="form-control form-control-sm deal-price" value="${item.price}" readonly style="background-color: #f8f9fa;"></td>
                <td><input type="text" class="form-control form-control-sm deal-total" value="${item.total}" readonly style="background-color: #f8f9fa;"></td>
                
                <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control form-control-sm sec-unit-price" readonly style="background-color: #f8f9fa;"></td>
                <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control form-control-sm sec-total" readonly style="background-color: #f8f9fa;"></td>
                
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeDealProductRow(this)"><i class="bi bi-trash"></i></button></td>
            `;
            tbody.appendChild(tr);
        });
        calculateTotals();
    } else {
        document.getElementById('dealSubtotal').innerText = '0.00';
        document.getElementById('dealVat').innerText = '0.00';
        document.getElementById('dealProductsTotal').innerText = '0.00';
    }
    
    // Set to View Mode
    toggleFormFields(true);
    document.getElementById('saveDealBtn').classList.add('d-none');
    document.getElementById('editDealBtn').classList.remove('d-none');
    document.getElementById('modalTitle').innerText = 'View Deal';

    dealModal.show();
}

function openAddDealModal() {
    document.getElementById('dealForm').reset();
    document.getElementById('dealId').value = '';
    
    // Reset source Selection
    document.getElementById('sourceTypeLead').checked = true;
    toggleSourceType();
    currentDealId = '';
    
    document.getElementById('dealCurrency').value = (window.CRM_APP_SETTINGS && window.CRM_APP_SETTINGS.currency) ? window.CRM_APP_SETTINGS.currency : 'NGN';
    document.getElementById('isDualCurrency').checked = false;
    toggleDualCurrency();
    updateCurrencySymbols();
    
    updateStageInfo();
    hidePrintButton();

    document.querySelector('#dealProductsTable tbody').innerHTML = '';
    document.getElementById('dealSubtotal').innerText = '0.00';
    document.getElementById('dealVat').innerText = '0.00';
    document.getElementById('dealProductsTotal').innerText = '0.00';
    
    // Default to Edit Mode for new deal
    toggleFormFields(false);
    document.getElementById('saveDealBtn').classList.remove('d-none');
    document.getElementById('editDealBtn').classList.add('d-none');
    document.getElementById('modalTitle').innerText = 'Add New Deal';

    dealModal.show();
}

function toggleFormFields(disabled) {
    const form = document.getElementById('dealForm');
    const inputs = form.querySelectorAll('input, select, textarea, button.btn-danger, button.btn-outline-secondary, button.btn-secondary, button.btn-sm');
    
    inputs.forEach(input => {
        // Exclude modal footer buttons and certain controls if needed
        if (!input.classList.contains('btn-close') && 
            input.id !== 'saveDealBtn' && 
            input.id !== 'editDealBtn' &&
            !input.closest('.modal-footer')) {
             input.disabled = disabled;
        }
    });
    
    // Force enable print drop down
    const printBtn = document.getElementById('printDropdown');
    if (printBtn) printBtn.disabled = false;
}

function enableEditMode() {
    toggleFormFields(false);
    document.getElementById('saveDealBtn').classList.remove('d-none');
    document.getElementById('editDealBtn').classList.add('d-none');
    document.getElementById('modalTitle').innerText = 'Edit Deal';
}

async function archiveDeal(id) {
    if (confirm('Are you sure you want to archive this deal?')) {
        const result = await DealsManager.delete(id);
        if (result.success) {
            showToast('Deal archived', 'success');
            loadDeals();
        } else {
            showToast(result.message, 'error');
        }
    }
}

async function restoreDeal(id) {
    if (confirm('Are you sure you want to restore this deal?')) {
        const result = await DealsManager.update(id, { is_archived: 0 });
        if (result.success) {
            showToast('Deal restored successfully', 'success');
            loadDeals();
        } else {
            showToast('Failed to restore deal: ' + result.message, 'error');
        }
    }
}


// Assign Deal Logic
let assignDealModal;

let myAssignmentsModal;

async function openMyAssignmentsModal() {
    if (!myAssignmentsModal) {
        myAssignmentsModal = new bootstrap.Modal(document.getElementById('myAssignmentsModal'));
    }
    myAssignmentsModal.show();
    
    // Load Personal Deals
    try {
        const response = await fetch('?api=true&only_mine=true');
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

async function assignDeal(dealId) {
    if (!assignDealModal) {
        const el = document.getElementById('assignDealModal');
        if (el) assignDealModal = new bootstrap.Modal(el);
    }
    
    document.getElementById('assignDealId').value = dealId;
    const select = document.getElementById('assignDealUserSelect');
    select.innerHTML = '<option value="">Loading...</option>';
    
    if (assignDealModal) assignDealModal.show();
    
    try {
        const response = await fetch(`${DealsManager.apiUrl}&type=users`);
        const result = await response.json();
        const users = result.data || [];
        
        select.innerHTML = '<option value="">Select User</option>';
        users.forEach(user => {
            select.innerHTML += `<option value="${user.id}">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</option>`;
        });
        
        // Pre-select current owner
        const deal = cachedDeals.find(d => d.id == dealId);
        if (deal && deal.assigned_to) {
            select.value = deal.assigned_to;
        }
    } catch (e) {
        console.error('Error loading users:', e);
        select.innerHTML = '<option value="">Error loading users</option>';
    }
}

async function saveDealAssignment() {
    const dealId = document.getElementById('assignDealId').value;
    const userId = document.getElementById('assignDealUserSelect').value;
    
    if (!userId) {
        alert('Please select a user');
        return;
    }
    
    const result = await DealsManager.update(dealId, { assigned_to: userId });
    
    if (result.success) {
        if (assignDealModal) assignDealModal.hide();
        loadDeals();
        // alert('Deal assigned successfully'); // Optional toast
    } else {
        alert(result.message);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}


function hidePrintButton() {
    const btn = document.getElementById('printBtn');
    if (btn) btn.style.display = 'none';
}

function showPrintButton() {
    const btn = document.getElementById('printBtn');
    if (btn) btn.style.display = 'inline-block';
}

function attachUnsavedChangesListeners() {
    const form = document.getElementById('dealForm');
    if (!form || form.getAttribute('data-listeners-attached')) return;
    
    form.addEventListener('input', function(e) {
        // Ignore internal or read-only fields if needed, but generally any input means change
        if (e.target.readOnly) return; 
        hidePrintButton();
    });
    
    form.addEventListener('change', function(e) {
         if (e.target.readOnly) return;
         hidePrintButton();
    });
    
    form.setAttribute('data-listeners-attached', 'true');
}
// Utility Functions
let currentSortState = { column: 'created_at', direction: 'desc' };
let currentDateRange = 'all';

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
    
    filterDeals(); 
}

function checkDateRange(dateString) {
    if (currentDateRange === 'all') return true;
    if (!dateString) return false;
    
    const date = new Date(dateString);
    const now = new Date();
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

let selectionModeActive = false;

function toggleSelectionMode() {
    selectionModeActive = !selectionModeActive;
    const container = document.getElementById('dealsTableContainer');
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
        document.getElementById('selectAllDeals').checked = false;
        document.querySelectorAll('.deal-checkbox').forEach(cb => cb.checked = false);
        updateBulkActionUI();
    }
}

function sortDeals(column) {
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
    
    filterDeals();
}


function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    const bgClass = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : (type === 'warning' ? 'bg-warning' : 'bg-info'));
    
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white ${bgClass} border-0 show`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    toastContainer.appendChild(toastEl);
    setTimeout(() => toastEl.remove(), 3000);
}

function createToastContainer() {
    const div = document.createElement('div');
    div.id = 'toastContainer';
    div.className = 'toast-container position-fixed top-0 end-0 p-3';
    document.body.appendChild(div);
    return div;
}

</script>

<script>
async function saveAsCustomer(leadId, dealId) {
    if (!leadId) {
        showToast('No lead associated with this deal', 'error');
        return;
    }

    if (!confirm('Save this deal\\'s contact as a permanent Customer?')) return;

    try {
        // Fetch Lead Details first
        const leadResponse = await fetch(`../../views/leads/index.php?api=true&id=${leadId}`);
        const leadResult = await leadResponse.json();
        
        if (!leadResult.success || !leadResult.data) {
            throw new Error('Could not fetch lead details');
        }
        
        const lead = leadResult.data;

        if (lead.customer_id) {
            showToast('This lead is already a customer.', 'warning');
            return;
        }
        
        const customerData = {
            first_name: lead.first_name,
            last_name: lead.last_name,
            email: lead.email,
            phone: lead.phone,
            company: lead.company,
            notes: `Saved from Deal ID: ${dealId}`
        };

        const response = await fetch('../../views/customers/?api=true', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(customerData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Customer saved successfully!', 'success');
        } else {
            showToast('Failed to save customer: ' + result.message, 'error');
        }
    } catch (e) {
        showToast('Error saving customer: ' + e.message, 'error');
    }
}

function bulkArchiveDeals() {
    const selected = Array.from(document.querySelectorAll('.deal-checkbox:checked')).map(cb => cb.value);
    
    if (selected.length === 0) return;
    
    if (confirm(`Are you sure you want to archive ${selected.length} deals?`)) {
        (async () => {
            let count = 0;
            for (const id of selected) {
                const result = await DealsManager.delete(id);
                if (result.success) count++;
            }
            if (count > 0) {
                showToast(`${count} deals archived.`, 'success');
                loadDeals();
                updateBulkActionUI();
            } else {
                showToast('Failed to archive selected deals.', 'error');
            }
        })();
    }
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

function bulkExportDeals() {
    const selected = Array.from(document.querySelectorAll('.deal-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;
    window.location.href = `?api=true&action=export&ids=${selected.join(',')}`;
}

async function bulkConvertToCustomer() {
    const selected = Array.from(document.querySelectorAll('.deal-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;

    if (!confirm(`Convert contact information of ${selected.length} deals to permanent customers?`)) return;

    let successCount = 0;
    for (const id of selected) {
        const deal = cachedDeals.find(d => d.id == id);
        if (deal && deal.lead_id) {
            try {
                await saveAsCustomer(deal.lead_id, id); 
                successCount++;
            } catch(e) { console.error(e); }
        }
    }
    showToast(`${successCount} bulk conversions initiated.`, 'info');
    loadDeals();
    updateBulkActionUI();
}

function updateBulkActionUI() {
    const selected = document.querySelectorAll('.deal-checkbox:checked');
    const toolbar = document.getElementById('bulkActionsToolbar');
    const selectAll = document.getElementById('selectAllDeals');
    const totalCheckboxes = document.querySelectorAll('.deal-checkbox');

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
</script>

<style>
/* Selection Mode Styles */
.selection-mode-off .table-checkbox-col {
    display: none;
}
</style>

<?php require_once '../../includes/footer.php'; ?>

