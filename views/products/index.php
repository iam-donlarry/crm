<?php
require_once '../../includes/config.php';
require_once '../../includes/utils.php';
require_once '../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
            if (!hasPermission('export_products')) {
                sendResponse(false, 'Forbidden: You do not have permission to export products.');
                exit;
            }
            handleExport($pdo);
            exit;
        }
        if ($_GET['action'] === 'template') {
            if (!hasPermission('download_products_template')) {
                sendResponse(false, 'Forbidden: You do not have permission to download the products template.');
                exit;
            }
            handleTemplate($pdo);
            exit;
        }
        if ($_GET['action'] === 'import' && $method === 'POST') {
            if (!hasPermission('import_products')) {
                sendResponse(false, 'Forbidden: You do not have permission to import products.');
                exit;
            }
            handleImport($pdo);
            exit;
        }
    }

    switch ($method) {
        case 'GET':
            if (!hasPermission('view_products')) sendResponse(false, 'Access Denied');
            handleGet($pdo);
            break;
        case 'POST':
            if (!hasPermission('add_products')) sendResponse(false, 'Access Denied');
            handlePost($pdo);
            break;
        case 'PUT':
            if (!hasPermission('edit_products')) sendResponse(false, 'Access Denied');
            handlePut($pdo);
            break;
        case 'DELETE':
            if (!hasPermission('delete_products')) {
                sendResponse(false, 'Forbidden: You do not have permission to delete products.');
            }
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    }
    exit;
}


function handleGet($pdo) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    try {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            if ($product) sendResponse(true, '', $product);
            else sendResponse(false, 'Product not found');
        } else {
            $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;
            $stmt = $pdo->prepare("SELECT * FROM products WHERE is_archived = ? ORDER BY id DESC");
            $stmt->execute([$archived]);
            $products = $stmt->fetchAll();
            sendResponse(true, '', $products);
        }
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

/* PHP API Logic Updates */

function handlePost($pdo) {
    $data = getJsonInput();
    // empty() returns true for 0, so check name specifically. Price is optional now (defaults to 0)
    if (empty($data['name'])) {
        sendResponse(false, 'Product Name is required');
    }

    try {
    // Generate Code
        $code = strtoupper(substr(base_convert(sha1(uniqid(mt_rand(), true)), 16, 36), 0, 5));
        
        $sql = "INSERT INTO products (name, code, unit, description, price, currency) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['name'],
            $code,
            $data['unit'] ?? '',
            $data['description'] ?? '',
            $data['price'] ?? 0,
            $data['currency'] ?? 'NGN'
        ]);
        sendResponse(true, 'Product created', ['id' => $pdo->lastInsertId(), 'code' => $code]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) sendResponse(false, 'Product Code collision, please try again');
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}


function handlePut($pdo) {
    $data = getJsonInput();
    if (empty($data['id'])) sendResponse(false, 'Product ID required');

    $fields = [];
    $values = [];
    $allowed = ['name', 'unit', 'description', 'price', 'currency', 'is_archived'];

    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }

    if (empty($fields)) sendResponse(false, 'No fields to update');
    
    $values[] = $data['id'];
    $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        sendResponse(true, 'Product updated');
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}





function handleExport($pdo) {
    if (!hasPermission('export_products')) {
        sendResponse(false, 'Forbidden: You do not have permission to export products.');
        exit;
    }
    ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Code', 'Name', 'Description', 'Unit', 'Price', 'Currency']);

    $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
    
    $where = ["is_archived = 0"];
    $params = [];
    
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "id IN ($placeholders)";
        $params = array_map('intval', $ids);
    }
    
    $sql = "SELECT id, code, name, description, unit, price, currency FROM products WHERE " . implode(" AND ", $where) . " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function handleTemplate($pdo) {
    if (!hasPermission('download_products_template')) {
        sendResponse(false, 'Forbidden: You do not have permission to download the products template.');
        exit;
    }
    ob_clean();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="products_template.csv"');

    $output = fopen('php://output', 'w');
    // Headers: ID, Code, Name, Description, Unit, Price, Currency
    fputcsv($output, ['ID', 'Code', 'Name', 'Description', 'Unit', 'Price', 'Currency']);
    fclose($output);
    exit;
}

function handleImport($pdo) {
    if (!hasPermission('import_products')) {
        sendResponse(false, 'Forbidden: You do not have permission to import products.');
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
        $stmt = $pdo->prepare("INSERT INTO products (name, code, description, unit, price, currency) VALUES (?, ?, ?, ?, ?, ?)");
        
        while (($row = fgetcsv($file)) !== false) {
             // 0=ID, 1=Code, 2=Name, 3=Desc, 4=Unit, 5=Price, 6=Currency
            if (count($row) < 3) continue;

            $name = $row[2] ?? 'Unknown Product';
            $code = strtoupper(substr(base_convert(sha1(uniqid(mt_rand(), true)), 16, 36), 0, 5));
            
            $desc = $row[3] ?? '';
            $unit = $row[4] ?? '';
            $price = floatval($row[5] ?? 0);
            $currency = $row[6] ?? 'NGN';

            $stmt->execute([$name, $code, $desc, $unit, $price, $currency]);
            $successCount++;
        }
        $pdo->commit();
        sendResponse(true, "Imported $successCount products successfully");
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
        $stmt = $pdo->prepare("UPDATE products SET is_archived = 1 WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, 'Product archived');
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

require_once '../../includes/header.php';
?>

<!-- Add/Edit Product Modal Moved to Top -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalTitle">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="productForm">
                    <input type="hidden" id="productId">
                    
                    <div class="mb-3">
                        <label for="productName" class="form-label">Product Name *</label>
                        <input type="text" class="form-control" id="productName" required>
                    </div>

                    <div class="mb-3">
                        <label for="productDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="productDescription" rows="3"></textarea>
                    </div>

                        <div class="col-md-6 mb-3">
                            <label for="productUnit" class="form-label">Unit</label>
                            <input type="text" class="form-control" id="productUnit" placeholder="e.g. Kg, Pcs, Box">
                        </div>

                    <div class="mb-3">
                        <label for="productPrice" class="form-label">Price *</label>
                        <div class="input-group">
                            <select class="form-select" id="productCurrency" style="max-width: 100px;">
                                <option value="NGN">₦ (NGN)</option>
                                <option value="USD">$ (USD)</option>
                                <option value="GBP">£ (GBP)</option>
                                <option value="EUR">€ (EUR)</option>
                            </select>
                            <input type="number" step="0.01" class="form-control" id="productPrice">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="editProductBtn" onclick="enableEditMode()">Edit</button>
                <button type="button" class="btn btn-primary d-none" id="saveProductBtn">Save Product</button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h2><i class="bi bi-box-seam"></i> Products</h2>
                <div>
                     <button class="btn btn-outline-secondary" id="toggleSelectionMode" onclick="toggleSelectionMode()">
                        <i class="bi bi-check2-square"></i> Select
                    </button>
                     <button class="btn btn-primary" id="addProductBtn">
                        <i class="bi bi-plus-lg"></i> Add Product
                    </button>
                    <button onclick="openDateRangeModal()" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-calendar3"></i> <span id="dateRangeLabel">Date Range</span>
                    </button>
                    <button class="btn btn-outline-secondary ms-2" id="toggleArchiveBtn" onclick="toggleArchiveView()">
                        <i class="bi bi-archive"></i> Show Archived
                    </button>
                    <button onclick="exportProducts()" class="btn btn-outline-success ms-2">
                        <i class="bi bi-file-earmark-arrow-down"></i> Export
                    </button>
                    <button onclick="downloadProductsTemplate()" class="btn btn-outline-info ms-2">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Template
                    </button>
                    <button onclick="document.getElementById('importFile').click()" class="btn btn-outline-primary ms-2">
                        <i class="bi bi-file-earmark-arrow-up"></i> Import
                    </button>
                    <input type="file" id="importFile" accept=".csv" style="display: none;" onchange="importProducts(this)">
                </div>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Product List</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <!-- Bulk Actions Toolbar -->
                        <div id="bulkActionsToolbar" class="card shadow mb-3 d-none">
                            <div class="card-body py-2 d-flex justify-content-between align-items-center bg-light border-bottom">
                                <div>
                                    <span class="fw-bold text-primary"><span id="selectedCount">0</span> selected</span>
                                </div>
                                <div class="btn-group gap-2">
                                    <button class="btn btn-outline-success btn-sm" onclick="bulkExportProducts()">
                                        <i class="bi bi-file-earmark-arrow-down"></i> Export Selected
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="bulkArchiveProducts()">
                                        <i class="bi bi-archive"></i> Archive Selected
                                    </button>
                                </div>
                            </div>
                        </div>

                        <table class="table table-hover align-middle selection-mode-off" id="productsTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="table-checkbox-col">
                                        <input type="checkbox" class="form-check-input" id="selectAllProducts" onclick="toggleSelectAllProducts()">
                                    </th>
                                    <th style="cursor: pointer;" onclick="sortProducts('id')">S/N <i class="bi bi-arrow-down-up sort-icon" id="sort-id"></i></th>
                                    <th style="cursor: pointer;" onclick="sortProducts('code')">Code <i class="bi bi-arrow-down-up sort-icon" id="sort-code"></i></th>
                                    <th style="cursor: pointer;" onclick="sortProducts('name')">Name <i class="bi bi-arrow-down-up sort-icon" id="sort-name"></i></th>
                                    <th style="cursor: pointer;" onclick="sortProducts('description')">Description <i class="bi bi-arrow-down-up sort-icon" id="sort-description"></i></th>
                                    <th style="cursor: pointer;" onclick="sortProducts('price')">Price <i class="bi bi-arrow-down-up sort-icon" id="sort-price"></i></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody">
                                <!-- Products will be loaded here via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <div id="paginationContainer" class="d-flex justify-content-end mt-3 me-3"></div>
                </div>
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

<script>
// Products Management Module
const ProductsManager = {
    apiUrl: '?api=true',

    // Get all products
    async getAll() {
        try {
            const response = await fetch(this.apiUrl);
            return (await response.json()).data || [];
        } catch (e) { return []; }
    },

    // Get product by ID
    async getById(id) {
        try {
            const response = await fetch(`${this.apiUrl}&id=${id}`);
            return (await response.json()).data || null;
        } catch (e) { return null; }
    },

    // Create new product
    async create(productData) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(productData)
            });
            return await response.json();
        } catch (e) { return {success: false, message: e.message}; }
    },

    // Update product
    async update(id, productData) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({...productData, id})
            });
            return await response.json();
        } catch (e) { return {success: false, message: e.message}; }
    },

    // Delete product
    async delete(id) {
        try {
            const response = await fetch(`${this.apiUrl}&id=${id}`, { method: 'DELETE' });
            return await response.json();
        } catch (e) { return {success: false, message: e.message}; }
    }
};

function exportProducts() {
    window.location.href = window.location.pathname + '?api=true&action=export';
}

function downloadProductsTemplate() {
    window.location.href = window.location.pathname + '?api=true&action=template';
}

async function importProducts(input) {
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
            loadProducts();
        } else {
            alert('Import Error: ' + result.message);
        }
    } catch (e) {
        alert('Import failed: ' + e.message);
    }
    input.value = '';
}

// UI Functions
let productModal;

document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    
    // Initialize Modal
    const modalEl = document.getElementById('productModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        productModal = new bootstrap.Modal(modalEl);
    }

    // Bind Events
    document.getElementById('addProductBtn')?.addEventListener('click', openAddProductModal);
    document.getElementById('saveProductBtn')?.addEventListener('click', saveProduct);
});

function openAddProductModal() {
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    
    // Enable fields for new entry
    toggleFormFields(false);
    
    // Show Save, Hide Edit
    document.getElementById('saveProductBtn').classList.remove('d-none');
    document.getElementById('editProductBtn').classList.add('d-none');
    
    document.getElementById('productModalTitle').innerText = 'Add Product';
    
    // Ensure modal is initialized
    if (!productModal) {
        const modalEl = document.getElementById('productModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            productModal = new bootstrap.Modal(modalEl);
        }
    }
    
    if (productModal) {
        productModal.show();
    } else {
        console.error('Bootstrap Modal not initialized');
        alert('Could not open modal. Please refresh the page.');
    }
}

function toggleFormFields(disabled) {
    const form = document.getElementById('productForm');
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => input.disabled = disabled);
}

function enableEditMode() {
    toggleFormFields(false);
    document.getElementById('saveProductBtn').classList.remove('d-none');
    document.getElementById('editProductBtn').classList.add('d-none');
    document.getElementById('productModalTitle').innerText = 'Edit Product';
}

let cachedProducts = [];
let currentSortState = { column: 'id', direction: 'desc' };
let currentDateRange = 'all';

// Pagination State
let currentPage = 1;
const itemsPerPage = 15;

let viewingArchived = false;

async function loadProducts() {
    const tbody = document.getElementById('productsTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center">Loading...</td></tr>';
    
    try {
        const response = await fetch(`${ProductsManager.apiUrl}&archived=${viewingArchived}`);
        cachedProducts = (await response.json()).data || [];
        currentPage = 1; // Reset to page 1
        filterProducts();
        updateArchiveToggleUI();
    } catch (e) {
         tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading products</td></tr>';
    }
}

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
    
    const modalEl = document.getElementById('dateRangeModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    
    filterProducts(); 
}

function checkDateRange(product) {
    // Products table doesn't have created_at usually displayed but it might exist in DB?
    // Let's assume the API returns it. If not, this filter won't work well.
    // The previous view_file of products/index.php doesn't explicitly show created_at in the table, but it likely exists in the object.
    // Let's check cachedProducts structure if possible or assume logic.
    // Wait, the API returns SELECT * FROM products unless specified.
    // The schema likely has created_at? 
    // If created_at is missing, we can't filter.
    // Assuming it exists or we fallback to 'id' as proxy? No, bad idea.
    // Let's try created_at.
    
    if (currentDateRange === 'all') return true;
    const dateString = product.created_at || product.date_created; // Try common names
    if (!dateString) return true; // If no date, include it or exclude it? Include to prevent disappearing items if date is missing.
    
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

function filterProducts() {
     const filtered = cachedProducts.filter(p => checkDateRange(p));
     
     // Sort
     filtered.sort((a, b) => {
        let valA = a[currentSortState.column];
        let valB = b[currentSortState.column];
        
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
    
    displayProducts(filtered);
}

function sortProducts(column, keepDirection = false) {
    if (!cachedProducts) return;

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

    filterProducts();
}

function toggleSelectAllProducts() {
    const isChecked = document.getElementById('selectAllProducts').checked;
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.checked = isChecked;
    });
    updateBulkActionUI();
}

function updateBulkActionUI() {
    const selected = document.querySelectorAll('.product-checkbox:checked');
    const toolbar = document.getElementById('bulkActionsToolbar');
    const selectAll = document.getElementById('selectAllProducts');
    const totalCheckboxes = document.querySelectorAll('.product-checkbox');
    
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

async function bulkArchiveProducts() {
    const selected = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
    
    if (selected.length === 0) return;
    
    if (confirm(`Are you sure you want to archive ${selected.length} products?`)) {
        let successCount = 0;
        
        for (const id of selected) {
            const result = await ProductsManager.delete(id);
            if (result.success) successCount++;
        }
        
        if (successCount > 0) {
            alert(`${successCount} products archived.`);
            loadProducts();
        } else {
            alert('Failed to archive selected products.');
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
    filterProducts();
}

function displayProducts(products) {
    const tbody = document.getElementById('productsTableBody');
    
    // Reset Bulk Toolbar
    document.getElementById('selectAllProducts').checked = false;
    updateBulkActionUI();
    
    // Pagination
    const totalItems = products.length;
    renderPagination(totalItems);

    const start = (currentPage - 1) * itemsPerPage;
    const paginatedProducts = products.slice(start, start + itemsPerPage);

    if (paginatedProducts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No products found</td></tr>';
        return;
    }

    tbody.innerHTML = paginatedProducts.map((p, index) => {
        let symbol = '$'; // Default
        if (p.currency === 'GBP') symbol = '£';
        else if (p.currency === 'NGN') symbol = '₦';
        else if (p.currency === 'EUR') symbol = '€';
        
        return `
        <tr style="cursor: pointer;" onclick="if(!window.getSelection().toString()) viewProduct(${p.id})">
            <td onclick="event.stopPropagation()" class="table-checkbox-col">
                <input type="checkbox" class="form-check-input product-checkbox" value="${p.id}" onchange="updateBulkActionUI()">
            </td>
            <td>${index + 1}</td>
            <td><span class="badge bg-secondary">${p.code || '-'}</span></td>
            <td>${escapeHtml(p.name)}</td>
            <td>${escapeHtml(p.description || '-')}</td>
            <td>${symbol}${parseFloat(p.price).toFixed(2)}</td>
            <td>
                ${viewingArchived ? 
                    `<button class="btn btn-sm btn-success" title="Restore" onclick="event.stopPropagation(); restoreProduct(${p.id})"><i class="bi bi-arrow-counterclockwise"></i></button>`
                    :
                    `<button class="btn btn-sm btn-outline-warning" title="Archive" onclick="event.stopPropagation(); archiveProduct(${p.id})"><i class="bi bi-archive"></i></button>`
                }
            </td>
        </tr>
    `;
    }).join('');
}

async function saveProduct() {
    const id = document.getElementById('productId').value;
    const data = {
        name: document.getElementById('productName').value,
        unit: document.getElementById('productUnit').value,
        price: document.getElementById('productPrice').value,
        currency: document.getElementById('productCurrency').value,
        description: document.getElementById('productDescription').value
    };

    if (!data.name) {
        alert('Name is required');
        return;
    }
    
    // Default price to 0 if not provided
    if (!data.price) {
        data.price = 0;
    }

    submitProduct(id, data);
}

async function submitProduct(id, data) {
    let result;
    // Add loading state to button
    const btn = document.getElementById('saveProductBtn');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Saving...';

    if (id) {
        result = await ProductsManager.update(id, data);
    } else {
        result = await ProductsManager.create(data);
    }

    btn.disabled = false;
    btn.innerText = originalText;

    if (result.success) {
        productModal.hide();
        loadProducts();
        // showToast(result.message, 'success'); 
    } else {
        alert(result.message);
        // showToast(result.message, 'error');
    }
}

async function viewProduct(id) {
    const products = await ProductsManager.getAll();
    const product = products.find(p => p.id == id);
    if (!product) {
        alert('Product not found');
        return;
    }

    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productUnit').value = product.unit || '';
    document.getElementById('productPrice').value = product.price;
    document.getElementById('productCurrency').value = product.currency || 'NGN';
    document.getElementById('productDescription').value = product.description || '';
    
    // Set to View Mode (Read Only)
    toggleFormFields(true);
    document.getElementById('saveProductBtn').classList.add('d-none');
    document.getElementById('editProductBtn').classList.remove('d-none');
    document.getElementById('productModalTitle').innerText = 'View Product';
    
    if (!productModal) {
         const modalEl = document.getElementById('productModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            productModal = new bootstrap.Modal(modalEl);
        }
    }
    
    if (productModal) {
        productModal.show();
    }
}

async function archiveProduct(id) {
    if (confirm('Are you sure you want to archive this product?')) {
        const result = await ProductsManager.delete(id);
        if (result.success) {
            loadProducts();
        } else {
            alert('Error: ' + result.message);
        }
    }
}

async function restoreProduct(id) {
    if (confirm('Are you sure you want to restore this product?')) {
        const result = await ProductsManager.update(id, { is_archived: 0 });
        if (result.success) {
            loadProducts();
        } else {
            alert('Error: ' + result.message);
        }
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
    loadProducts();
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
let selectionModeActive = false;

function toggleSelectionMode() {
    selectionModeActive = !selectionModeActive;
    const container = document.getElementById('productsTable');
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
        document.getElementById('selectAllProducts').checked = false;
        document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = false);
        updateBulkActionUI();
    }
}

function bulkExportProducts() {
    const selected = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;
    window.location.href = `?api=true&action=export&ids=${selected.join(',')}`;
}
</script>

<style>
.selection-mode-off .table-checkbox-col {
    display: none;
}
</style>

<?php include '../../includes/footer.php'; ?>