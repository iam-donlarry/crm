<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// API Handling
if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'export') {
            if (!hasPermission('export_customers')) {
                sendResponse(false, 'Forbidden: You do not have permission to export customers.');
                exit;
            }
            handleExport($pdo);
            exit;
        }
        if ($_GET['action'] === 'template') {
            if (!hasPermission('download_customers_template')) {
                sendResponse(false, 'Forbidden: You do not have permission to download the customers template.');
                exit;
            }
            handleTemplate($pdo);
            exit;
        }
        if ($_GET['action'] === 'import' && $method === 'POST') {
            if (!hasPermission('import_customers')) {
                sendResponse(false, 'Forbidden: You do not have permission to import customers.');
                exit;
            }
            handleImport($pdo);
            exit;
        }
    }

    switch ($method) {
        case 'GET':
            if (!hasPermission('view_customers')) sendResponse(false, 'Access Denied');
            handleGet($pdo);
            break;
        case 'POST':
            if (!hasPermission('add_customers')) sendResponse(false, 'Access Denied');
            handlePost($pdo);
            break;
        case 'PUT':
            if (!hasPermission('edit_customers')) sendResponse(false, 'Access Denied');
            handlePut($pdo);
            break;
        case 'DELETE':
            if (!hasPermission('delete_customers')) {
                sendResponse(false, 'Forbidden: You do not have permission to delete customers.');
            }
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    }
    exit;
}

// --- API Functions ---

function handleGet($pdo) {
    try {
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $customer = $stmt->fetch();
            if ($customer) sendResponse(true, '', $customer);
            else sendResponse(false, 'Customer not found');
        } else {
            $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;
            
            // Visibility Logic
            $where = ["is_archived = ?"];
            $params = [$archived];

            $sql = "SELECT * FROM customers WHERE " . implode(" AND ", $where) . " ORDER BY created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll();
            sendResponse(true, '', $customers);
        }
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}



function handlePost($pdo) {
    $data = getJsonInput();
    if (empty($data['first_name']) && empty($data['company'])) {
        sendResponse(false, 'First Name or Company Name is required');
    }

    try {
        // Generate Customer Code (e.g. CUS-1001) if not provided
        $code = 'CUS-' . strtoupper(substr(uniqid(), -5));
        // Ideally we'd use auto-increment ID but we need it before insert or update after insert.
        // Let's stick to unique string for now to avoid race conditions easily.
        
        $sql = "INSERT INTO customers (customer_code, first_name, last_name, email, phone, company, address, city, state, zip, country, notes, display_name, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $code,
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['email'] ?? '',
            $data['phone'] ?? '',
            $data['company'] ?? '',
            $data['address'] ?? '',
            $data['city'] ?? '',
            $data['state'] ?? '',
            $data['zip'] ?? '',
            $data['country'] ?? '',
            $data['notes'] ?? '',
            generateUniqueDisplayName($pdo, $data['first_name'] ?? '', $data['last_name'] ?? '', $data['company'] ?? ''),
            $data['assigned_to'] ?? $_SESSION['user_id']
        ]);
        sendResponse(true, 'Customer added successfully', ['id' => $pdo->lastInsertId(), 'code' => $code]);
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handlePut($pdo) {
    $data = getJsonInput();
    if (empty($data['id'])) sendResponse(false, 'Customer ID required');

    $fields = [];
    $values = [];
    $allowed = ['first_name', 'last_name', 'email', 'phone', 'company', 'address', 'city', 'state', 'zip', 'country', 'notes', 'is_archived'];

    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }

    if (empty($fields)) sendResponse(false, 'No fields to update');
    
    $values[] = $data['id'];
    $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        sendResponse(true, 'Customer updated successfully');
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}


function handleExport($pdo) {
    if (!hasPermission('export_customers')) {
        sendResponse(false, 'Forbidden: You do not have permission to export customers.');
        exit;
    }
    ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customers_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Code', 'First Name', 'Last Name', 'Company', 'Email', 'Phone', 'Address', 'City', 'State', 'Zip', 'Country', 'Notes']);

    $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
    
    $where = ["is_archived = 0"];
    $params = [];
    
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "id IN ($placeholders)";
        $params = array_map('intval', $ids);
    }
    
    $sql = "SELECT id, customer_code, first_name, last_name, company, email, phone, address, city, state, zip, country, notes FROM customers WHERE " . implode(" AND ", $where) . " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function handleTemplate($pdo) {
    if (!hasPermission('download_customers_template')) {
        sendResponse(false, 'Forbidden: You do not have permission to download the customers template.');
        exit;
    }
    ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customers_template.csv"');

    $output = fopen('php://output', 'w');
    // Headers: ID, Code, First Name, Last Name, Company, Email, Phone, Address, City, State, Zip, Country, Notes
    fputcsv($output, ['ID', 'Code', 'First Name', 'Last Name', 'Company', 'Email', 'Phone', 'Address', 'City', 'State', 'Zip', 'Country', 'Notes']);
    fclose($output);
    exit;
}

function handleImport($pdo) {
    if (!hasPermission('import_customers')) {
        sendResponse(false, 'Forbidden: You do not have permission to import customers.');
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

    fgetcsv($file); // Skip header

    $successCount = 0;
    $pdo->beginTransaction();
    try {
         $stmt = $pdo->prepare("INSERT INTO customers (customer_code, first_name, last_name, company, email, phone, address, city, state, zip, country, notes, display_name, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        while (($row = fgetcsv($file)) !== false) {
             // 0=ID, 1=Code, 2=First, 3=Last, 4=Comp, 5=Email, 6=Phone, 7=Addr, 8=City, 9=State, 10=Zip, 11=Country, 12=Notes
            if (count($row) < 3) continue;

            $code = 'CUS-' . strtoupper(substr(uniqid(), -5)); // Generate new code
            $firstName = $row[2] ?? '';
            $lastName = $row[3] ?? '';
            $company = $row[4] ?? '';
            $email = $row[5] ?? '';
            $phone = $row[6] ?? '';
            $address = $row[7] ?? '';
            $city = $row[8] ?? '';
            $state = $row[9] ?? '';
            $zip = $row[10] ?? '';
            $country = $row[11] ?? '';
            $notes = $row[12] ?? '';



             if (empty($firstName) && empty($company)) continue;

             $displayName = generateUniqueDisplayName($pdo, $firstName, $lastName, $company);

            $stmt->execute([$code, $firstName, $lastName, $company, $email, $phone, $address, $city, $state, $zip, $country, $notes, $displayName, (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null)]);
            $successCount++;
        }
        $pdo->commit();
        sendResponse(true, "Imported $successCount customers successfully");
    } catch (Exception $e) {
        $pdo->rollBack();
        sendResponse(false, 'Import failed: ' . $e->getMessage());
    }
    fclose($file);
}

function handleDelete($pdo) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id) sendResponse(false, 'ID required');

    try {
        $stmt = $pdo->prepare("UPDATE customers SET is_archived = 1 WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, 'Customer archived successfully');
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h2><i class="bi bi-people-fill"></i> Customers</h2>
        <div>
            <button class="btn btn-outline-secondary" id="toggleSelectionMode" onclick="toggleSelectionMode()">
                <i class="bi bi-check2-square"></i> Select
            </button>
             <button class="btn btn-primary" onclick="openCustomerModal()">
                <i class="bi bi-person-plus"></i> Add Customer
            </button>
             <button onclick="openDateRangeModal()" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-calendar3"></i> <span id="dateRangeLabel">Date Range</span>
             </button>
            <button class="btn btn-outline-secondary ms-2" id="toggleArchiveBtn" onclick="toggleArchiveView()">
                <i class="bi bi-archive"></i> Show Archived
            </button>
            <button onclick="exportCustomers()" class="btn btn-outline-success ms-2">
                <i class="bi bi-file-earmark-arrow-down"></i> Export
            </button>
            <button onclick="downloadCustomersTemplate()" class="btn btn-outline-info ms-2">
                <i class="bi bi-file-earmark-spreadsheet"></i> Template
            </button>
            <button onclick="document.getElementById('importFile').click()" class="btn btn-outline-primary ms-2">
                <i class="bi bi-file-earmark-arrow-up"></i> Import
            </button>
            <input type="file" id="importFile" accept=".csv" style="display: none;" onchange="importCustomers(this)">
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive selection-mode-off" id="customersTableContainer">
                <!-- Bulk Actions Toolbar -->
                <div id="bulkActionsToolbar" class="card shadow mb-3 d-none">
                    <div class="card-body py-2 d-flex justify-content-between align-items-center bg-light border-bottom">
                        <div>
                            <span class="fw-bold text-primary"><span id="selectedCount">0</span> selected</span>
                        </div>
                        <div>
                            <button class="btn btn-danger btn-sm" onclick="bulkArchiveCustomers()">
                                <i class="bi bi-archive"></i> Archive Selected
                            </button>
                        </div>
                    </div>
                </div>

                <table class="table table-hover align-middle" id="customersTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="table-checkbox-col">
                                <input type="checkbox" class="form-check-input" id="selectAllCustomers" onclick="toggleSelectAllCustomers()">
                            </th>
                            <th style="cursor: pointer;" onclick="sortCustomers('first_name')">Name <i class="bi bi-arrow-down-up sort-icon" id="sort-first_name"></i></th>
                            <th style="cursor: pointer;" onclick="sortCustomers('company')">Company <i class="bi bi-arrow-down-up sort-icon" id="sort-company"></i></th>
                            <th style="cursor: pointer;" onclick="sortCustomers('email')">Email <i class="bi bi-arrow-down-up sort-icon" id="sort-email"></i></th>
                            <th style="cursor: pointer;" onclick="sortCustomers('phone')">Phone <i class="bi bi-arrow-down-up sort-icon" id="sort-phone"></i></th>
                            <th style="cursor: pointer;" onclick="sortCustomers('city')">Location <i class="bi bi-arrow-down-up sort-icon" id="sort-city"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customersTableBody">
                        <!-- Customers will be loaded here via JavaScript -->
                    </tbody>
                </table>
                <div id="paginationContainer" class="d-flex justify-content-end mt-3 me-3"></div>
            </div>
        </div>
    </div>
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

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalTitle">Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="customerForm">
                    <input type="hidden" id="customerId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company</label>
                        <input type="text" class="form-control" id="company">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone">
                        </div>
                    </div>
                    
                    <h6 class="mt-4 mb-3 border-bottom pb-2">Address Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-control" id="address">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" id="city">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">State/Province</label>
                            <input type="text" class="form-control" id="state">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Zip/Postal Code</label>
                            <input type="text" class="form-control" id="zip">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" id="country">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="editCustomerBtn" onclick="enableEditMode()">Edit</button>
                <button type="button" class="btn btn-primary d-none" id="saveCustomerBtn" onclick="saveCustomer()">Save Customer</button>
            </div>
        </div>
    </div>
</div>

<script>
let customerModal;
let cachedCustomers = [];
let currentPage = 1;
const itemsPerPage = 15;
let currentSortState = { column: 'created_at', direction: 'desc' };
let currentDateRange = 'all';
let viewingArchived = false;

document.addEventListener('DOMContentLoaded', () => {
    customerModal = new bootstrap.Modal(document.getElementById('customerModal'));
    loadCustomers();
});



async function loadCustomers() {
    try {
        const response = await fetch(`?api=true&archived=${viewingArchived}&_=${new Date().getTime()}`);
        const result = await response.json();
        
        if (result.success) {
            cachedCustomers = result.data;
            filterCustomers();
            updateArchiveToggleUI();
        } else {
            console.error('Failed to load customers:', result.message);
        }
    } catch (e) {
        console.error('Network error:', e);
    }
}

function openDateRangeModal() {
    new bootstrap.Modal(document.getElementById('dateRangeModal')).show();
}

function applyDateRange(range) {
    currentDateRange = range;
    
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
    
    const modalEl = document.getElementById('dateRangeModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    
    filterCustomers(); 
}

function checkDateRange(customer) {
    if (currentDateRange === 'all') return true;
    const dateString = customer.created_at; // Assuming customers have created_at
    // If undefined in DB (schema), then we can't filter.
    // The previous view_file of customers/index.php line 144: SELECT ..., created_at FROM customers?
    // Start of file showed: SELECT id, customer_code, ..., notes FROM customers.
    // Line 63 showed: SELECT * FROM customers ORDER BY created_at DESC.
    // So 'created_at' IS in the FetchAll result in handleGet line 64.
    if (!dateString) return true; 
    
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

function filterCustomers() {
     const filtered = cachedCustomers.filter(c => checkDateRange(c));
     
     // Sort
     filtered.sort((a, b) => {
        let valA = a[currentSortState.column];
        let valB = b[currentSortState.column];
        
        // Handle nulls
        if (valA === null || valA === undefined) valA = '';
        if (valB === null || valB === undefined) valB = '';

        if (currentSortState.column === 'first_name' || currentSortState.column === 'last_name') { // Combined name sort
            valA = (a.first_name + ' ' + a.last_name).toLowerCase();
            valB = (b.first_name + ' ' + b.last_name).toLowerCase();
        } else if (typeof valA === 'string') {
            valA = valA.toLowerCase();
            valB = valB.toLowerCase();
        } else if (typeof valA === 'number') {
             // numbers are fine
        }

        if (valA < valB) return currentSortState.direction === 'asc' ? -1 : 1;
        if (valA > valB) return currentSortState.direction === 'asc' ? 1 : -1;
        return 0;
    });
    
    renderCustomers(filtered);
}

function renderPagination(totalItems) {
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const container = document.getElementById('paginationContainer');
    const info = document.getElementById('paginationInfo');
    if (!container) return;

    if (totalPages <= 1) {
        container.innerHTML = '';
        if (info) info.innerHTML = '';
        return;
    }

    let html = '<nav><ul class="pagination mb-0">';
    
    // Previous
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="changePage(${currentPage - 1})">Previous</button>
             </li>`;

    // Page Numbers (Simple version: show all or small range. For now, simple)
    // To avoid too many buttons, let's show: 1, ... prev, current, next, ... last
    // But for 15 items/page and ~100 items, simple loop is fine. 
    // Let's implement smart window if pages > 7
    
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

    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);
    if (info) info.innerHTML = `Showing ${startItem} to ${endItem} of ${totalItems} customers`;
}

function changePage(page) {
    const totalItems = cachedCustomers.filter(c => checkDateRange(c)).length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    filterCustomers(); // re-runs filter, sort, slice, render
}

function sortCustomers(column, keepDirection = false) {
    if (!cachedCustomers) return;

    if (!keepDirection) {
        if (currentSortState.column === column) {
            currentSortState.direction = currentSortState.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSortState.column = column;
            currentSortState.direction = 'asc';
        }
    }
    
    // Update Icons
    document.querySelectorAll('.sort-icon').forEach(icon => {
        icon.className = 'bi bi-arrow-down-up sort-icon text-muted';
    });
    const currentIcon = document.getElementById(`sort-${column}`);
    if (currentIcon) {
        currentIcon.className = `bi bi-arrow-${currentSortState.direction === 'asc' ? 'up' : 'down'} sort-icon text-primary`;
    }

    filterCustomers();
}

function exportCustomers() {
    window.location.href = window.location.pathname + '?api=true&action=export';
}

function downloadCustomersTemplate() {
    window.location.href = window.location.pathname + '?api=true&action=template';
}

async function importCustomers(input) {
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
            loadCustomers();
        } else {
            alert('Import Error: ' + result.message);
        }
    } catch (e) {
        alert('Import failed: ' + e.message);
    }
    input.value = '';
}

function toggleSelectAllCustomers() {
    const isChecked = document.getElementById('selectAllCustomers').checked;
    document.querySelectorAll('.customer-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateBulkActionUI();
}

function updateBulkActionUI() {
    const selected = document.querySelectorAll('.customer-checkbox:checked');
    const toolbar = document.getElementById('bulkActionsToolbar');
    const selectAll = document.getElementById('selectAllCustomers');
    const totalCheckboxes = document.querySelectorAll('.customer-checkbox');
    
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

async function bulkArchiveCustomers() {
    const selected = Array.from(document.querySelectorAll('.customer-checkbox:checked')).map(cb => cb.value);
    
    if (selected.length === 0) return;
    
    if (confirm(`Are you sure you want to archive ${selected.length} customers?`)) {
        let successCount = 0;
        
        for (const id of selected) {
            try {
                const result = await fetch(`?api=true&action=delete&id=${id}`, { method: 'DELETE' }).then(r=>r.json());
                if (result.success) successCount++;
            } catch(e) { console.error(e); }
        }
        
        if (successCount > 0) {
            alert(`${successCount} customers archived.`);
            loadCustomers();
            updateBulkActionUI();
        } else {
            alert('Failed to archive selected customers.');
        }
    }
}

let selectionModeActive = false;

function toggleSelectionMode() {
    selectionModeActive = !selectionModeActive;
    const container = document.getElementById('customersTableContainer');
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
        document.getElementById('selectAllCustomers').checked = false;
        document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = false);
        updateBulkActionUI();
    }
}

function bulkExportCustomers() {
    const selected = Array.from(document.querySelectorAll('.customer-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;
    window.location.href = `?api=true&action=export&ids=${selected.join(',')}`;
}




function toggleSelectAllCustomers() {
    const isChecked = document.getElementById('selectAllCustomers').checked;
    document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = isChecked);
    updateBulkActionUI();
}

function updateBulkActionUI() {
    const selected = document.querySelectorAll('.customer-checkbox:checked');
    const toolbar = document.getElementById('bulkActionsToolbar');
    const selectAll = document.getElementById('selectAllCustomers');
    const totalCheckboxes = document.querySelectorAll('.customer-checkbox');

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

function renderCustomers(customers) {
    const tbody = document.getElementById('customersTableBody');
    
    // Pagination Logic
    const totalItems = customers.length;
    renderPagination(totalItems);
    
    const start = (currentPage - 1) * itemsPerPage;
    const paginatedCustomers = customers.slice(start, start + itemsPerPage);
    
    // Reset Bulk Toolbar
    document.getElementById('selectAllCustomers').checked = false;
    updateBulkActionUI();

    if (paginatedCustomers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No customers found</td></tr>';
        return;
    }
    
    tbody.innerHTML = paginatedCustomers.map(c => `
        <tr style="cursor: pointer;" onclick="if(!window.getSelection().toString()) viewCustomer(${c.id})">
            <td onclick="event.stopPropagation()" class="table-checkbox-col">
                <input type="checkbox" class="form-check-input customer-checkbox" value="${c.id}" onchange="updateBulkActionUI()">
            </td>
            <td>
                <div class="fw-bold">${escapeHtml(c.first_name || '')} ${escapeHtml(c.last_name || '')}</div>
                <small class="text-muted"><span class="badge bg-light text-dark border">${c.customer_code || 'ID:'+c.id}</span></small>
            </td>
            <td>${escapeHtml(c.company || '-')}</td>
            <td><a href="mailto:${escapeHtml(c.email)}" onclick="event.stopPropagation()">${escapeHtml(c.email)}</a></td>
            <td>${escapeHtml(c.phone || '-')}</td>
            <td>${escapeHtml(c.city || '')}${c.country ? ', ' + escapeHtml(c.country) : ''}</td>
            <td>
                ${viewingArchived ? 
                    `<button class="btn btn-sm btn-success" title="Restore" onclick="event.stopPropagation(); restoreCustomer(${c.id})">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>` 
                    : 
                    `<button class="btn btn-sm btn-outline-warning" title="Archive" onclick="event.stopPropagation(); archiveCustomer(${c.id})">
                        <i class="bi bi-archive"></i>
                    </button>`
                }
            </td>
        </tr>
    `).join('');
}

function openCustomerModal() {
    document.getElementById('customerForm').reset();
    document.getElementById('customerId').value = '';
    
    // Enable fields for new entry
    toggleFormFields(false);
    
    // Show Save, Hide Edit
    document.getElementById('saveCustomerBtn').classList.remove('d-none');
    document.getElementById('editCustomerBtn').classList.add('d-none');
    
    document.getElementById('customerModalTitle').innerText = 'Add Customer';
    customerModal.show();
}

function toggleFormFields(disabled) {
    const form = document.getElementById('customerForm');
    const inputs = form.querySelectorAll('input, textarea');
    inputs.forEach(input => input.disabled = disabled);
}

function enableEditMode() {
    toggleFormFields(false);
    document.getElementById('saveCustomerBtn').classList.remove('d-none');
    document.getElementById('editCustomerBtn').classList.add('d-none');
    document.getElementById('customerModalTitle').innerText = 'Edit Customer';
}

async function viewCustomer(id) {
    try {
        const response = await fetch(`?api=true&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const c = result.data;
            document.getElementById('customerId').value = c.id;
            document.getElementById('firstName').value = c.first_name || '';
            document.getElementById('lastName').value = c.last_name || '';
            document.getElementById('company').value = c.company || '';
            document.getElementById('email').value = c.email || '';
            document.getElementById('phone').value = c.phone || '';
            document.getElementById('address').value = c.address || '';
            document.getElementById('city').value = c.city || '';
            document.getElementById('state').value = c.state || '';
            document.getElementById('zip').value = c.zip || '';
            document.getElementById('country').value = c.country || '';
            document.getElementById('notes').value = c.notes || '';
            
            // Set to View Mode (Read Only)
            toggleFormFields(true);
            document.getElementById('saveCustomerBtn').classList.add('d-none');
            document.getElementById('editCustomerBtn').classList.remove('d-none');
            document.getElementById('customerModalTitle').innerText = 'View Customer';
            
            customerModal.show();
        }
    } catch (e) {
        alert('Error fetching customer details');
    }
}

async function saveCustomer() {
    const id = document.getElementById('customerId').value;
    const data = {
        first_name: document.getElementById('firstName').value,
        last_name: document.getElementById('lastName').value,
        company: document.getElementById('company').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        address: document.getElementById('address').value,
        city: document.getElementById('city').value,
        state: document.getElementById('state').value,
        zip: document.getElementById('zip').value,
        country: document.getElementById('country').value,
        notes: document.getElementById('notes').value
    };
    
    if (!data.first_name && !data.company) {
        alert('Please enter at least a First Name or Company Name');
        return;
    }
    
    const method = id ? 'PUT' : 'POST';
    if (id) data.id = id;
    
    try {
        const response = await fetch('?api=true', {
            method: method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        if (result.success) {
            customerModal.hide();
            loadCustomers();
        } else {
            alert(result.message);
        }
    } catch (e) {
        alert('Action failed');
    }
}

async function archiveCustomer(id) {
    if (confirm('Are you sure you want to archive this customer?')) {
        try {
            const result = await fetch(`?api=true&action=delete&id=${id}`, { method: 'DELETE' }).then(r=>r.json());
            if (result.success) {
                loadCustomers();
            } else {
                alert(result.message);
            }
        } catch(e) { alert('Error archiving'); }
    }
}

async function restoreCustomer(id) {
    if (confirm('Are you sure you want to restore this customer?')) {
        try {
            const result = await fetch('?api=true', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, is_archived: 0 })
            }).then(r => r.json());

            if (result.success) {
                alert('Customer restored successfully');
                loadCustomers();
            } else {
                alert(result.message);
            }
        } catch(e) { alert('Error restoring customer'); }
    }
}

function updateArchiveToggleUI() {
    const btn = document.getElementById('toggleArchiveBtn');
    if (btn) {
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
    loadCustomers();
}

function escapeHtml(text) {
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<style>
.selection-mode-off .table-checkbox-col {
    display: none !important;
}
</style>

<?php include '../../includes/footer.php'; ?>
