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

    $action = $_GET['action'] ?? null;
    $method = $_SERVER['REQUEST_METHOD'];

    // Router
    if ($action === 'roles') {
        handleRoles($pdo, $method);
    } elseif ($action === 'currencies') {
        handleCurrencies($pdo, $method);
    } elseif ($action === 'app_settings') {
        handleAppSettings($pdo, $method);
    } elseif ($action === 'permissions') {
        handlePermissions($pdo, $method);
    } elseif ($action === 'lead_sources') {
        handleLeadSources($pdo, $method);
    } else {
        // Default settings get
        if ($method === 'GET') {
            handleGetSettings($pdo);
        } else {
            http_response_code(400);
            sendResponse(false, 'Invalid Action');
        }
    }
    exit;
}

// --- Roles API Logic ---
function handleRoles($pdo, $method) {
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT * FROM roles WHERE is_archived = 0 ORDER BY is_system DESC, name");
            $roles = $stmt->fetchAll();
            foreach ($roles as &$role) {
                $decoded = json_decode($role['permissions'], true);
                $role['permissions'] = is_array($decoded) ? $decoded : [];
                $role['is_system'] = (bool)$role['is_system'];
                $role['is_default'] = (bool)$role['is_default'];
            }
            sendResponse(true, '', $roles);
        } catch (PDOException $e) {
            sendResponse(false, 'DB Error');
        }
    } elseif ($method === 'DELETE' || ($method === 'POST' && isset($_GET['delete']) && $_GET['delete'] === 'true')) {
        if (!hasPermission('manage_roles')) sendResponse(false, 'Access Denied');
        $id = $_GET['id'] ?? null;
        if (!$id) sendResponse(false, 'ID required');
        
        try {
            // Check if it's a system role
            $stmt = $pdo->prepare("SELECT is_system FROM roles WHERE id = ?");
            $stmt->execute([$id]);
            $role = $stmt->fetch();
            
            if ($role && $role['is_system']) {
                sendResponse(false, 'Cannot delete system role');
            }
            
            $stmt = $pdo->prepare("UPDATE roles SET is_archived = 1 WHERE id = ?");
            $stmt->execute([$id]);
            sendResponse(true, 'Role Archived');
        } catch (PDOException $e) {
            sendResponse(false, 'DB Error');
        }
    } elseif ($method === 'POST') {
        if (!hasPermission('manage_roles')) sendResponse(false, 'Access Denied');
        $data = getJsonInput();
        
        // Check if update (ID present)
        if (isset($data['id'])) {
            try {
                // Determine existing values to support partial updates
                $stmt = $pdo->prepare("SELECT name, description, permissions FROM roles WHERE id = ?");
                $stmt->execute([$data['id']]);
                $existing = $stmt->fetch();
                
                if (!$existing) sendResponse(false, 'Role not found');

                $name = $data['name'] ?? $existing['name'];
                $description = $data['description'] ?? $existing['description'];
                // Ensure permissions are saved as a clean JSON array
                $perms = isset($data['permissions']) ? $data['permissions'] : json_decode($existing['permissions'], true);
                $permissions = json_encode(array_values(is_array($perms) ? $perms : []));

                $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ?, permissions = ? WHERE id = ?");
                $stmt->execute([$name, $description, $permissions, $data['id']]);
                sendResponse(true, 'Role Updated');
            } catch (PDOException $e) {
                sendResponse(false, 'DB Error: ' . $e->getMessage());
            }
        } else {
            // Create
            if (empty($data['name'])) sendResponse(false, 'Role Name required');
            try {
                $stmt = $pdo->prepare("INSERT INTO roles (name, description, permissions, is_system, is_default) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $data['name'],
                    $data['description'] ?? '',
                    json_encode(array_values($data['permissions'] ?? [])),
                    $data['is_system'] ?? 0,
                    $data['is_default'] ?? 0
                ]);
                sendResponse(true, 'Role Created', ['id' => $pdo->lastInsertId()]);
            } catch (PDOException $e) {
                sendResponse(false, 'DB Error: ' . $e->getMessage());
            }
        }
    } else {
        sendResponse(false, 'Method Not Allowed');
    }
}

// --- Permissions API Logic ---
function handlePermissions($pdo, $method) {
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT id, slug, name, description, category FROM permissions ORDER BY category, id");
            $permissions = $stmt->fetchAll();
            sendResponse(true, '', $permissions);
        } catch (PDOException $e) {
            sendResponse(false, 'DB Error: ' . $e->getMessage());
        }
    } else {
        sendResponse(false, 'Method Not Allowed');
    }
}

// --- Settings API Logic ---
function handleCurrencies($pdo, $method) {
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT * FROM currencies");
            $currencies = $stmt->fetchAll();
            sendResponse(true, '', $currencies);
        } catch (PDOException $e) {
            sendResponse(false, 'DB Error');
        }
    } elseif ($method === 'POST' || $method === 'PUT') {
        if (!hasPermission('manage_currencies')) sendResponse(false, 'Access Denied');
        $data = getJsonInput();
        if (isset($data['currencies']) && is_array($data['currencies'])) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE currencies SET exchange_rate = ? WHERE code = ?");
                foreach ($data['currencies'] as $curr) {
                    $stmt->execute([$curr['rate'], $curr['code']]);
                }
                $pdo->commit();
                sendResponse(true, 'Rates Updated');
            } catch (Exception $e) {
                $pdo->rollBack();
                sendResponse(false, 'Update Failed');
            }
        }
    }
}

function handleLeadSources($pdo, $method) {
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT * FROM lead_sources ORDER BY name ASC");
            sendResponse(true, '', $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            sendResponse(false, 'DB Error');
        }
    } elseif ($method === 'POST') {
        if (!hasPermission('edit_settings')) sendResponse(false, 'Access Denied');
        $data = getJsonInput();
        if (empty($data['name'])) sendResponse(false, 'Name required');
        
        try {
            $stmt = $pdo->prepare("INSERT INTO lead_sources (name) VALUES (?)");
            $stmt->execute([trim($data['name'])]);
            sendResponse(true, 'Source added', ['id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) sendResponse(false, 'Source already exists');
            sendResponse(false, 'DB Error: ' . $e->getMessage());
        }
    } elseif ($method === 'DELETE') {
        if (!hasPermission('edit_settings')) sendResponse(false, 'Access Denied');
        $id = $_GET['id'] ?? 0;
        if (!$id) sendResponse(false, 'ID required');
        
        try {
            $stmt = $pdo->prepare("DELETE FROM lead_sources WHERE id = ?");
            $stmt->execute([$id]);
            sendResponse(true, 'Source deleted');
        } catch (PDOException $e) {
            sendResponse(false, 'DB Error');
        }
    }
}

function handleGetSettings($pdo) {
    if (!hasPermission('view_settings')) sendResponse(false, 'Access Denied');
    try {
        $stmt = $pdo->query("SELECT * FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        sendResponse(true, '', $settings);
    } catch (PDOException $e) {
        sendResponse(false, 'DB Error');
    }
}

function handleAppSettings($pdo, $method) {
    if ($method === 'POST') {
        if (!hasPermission('edit_settings')) sendResponse(false, 'Access Denied');
        $data = getJsonInput();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            foreach ($data as $key => $value) {
                $stmt->execute([$key, $value, $value]);
            }
            $pdo->commit();
            sendResponse(true, 'Settings Saved');
        } catch (Exception $e) {
            $pdo->rollBack();
            sendResponse(false, 'Save Failed');
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-gear"></i> Settings</h1>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab">
            <i class="bi bi-shield-lock"></i> Roles & Permissions
        </button>
    </li>
    <li class="nav-item" role="presentation">
            <i class="bi bi-sliders"></i> System Settings
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="sources-tab" data-bs-toggle="tab" data-bs-target="#sources" type="button" role="tab">
            <i class="bi bi-list-ul"></i> Lead Sources
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="settingsTabContent">

    <!-- Lead Sources Tab -->
    <div class="tab-pane fade" id="sources" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Manage Lead Sources</h5>
                <button class="btn btn-primary btn-sm" onclick="addLeadSource()">
                    <i class="bi bi-plus-lg"></i> Add Source
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-group" id="leadSourcesList">
                            <li class="list-group-item text-center">Loading...</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Permissions Tab -->
    <div class="tab-pane fade show active" id="permissions" role="tabpanel">
        <style>
            /* Professional UI Styles */
            .settings-container {
                display: flex;
                gap: 2rem;
                align-items: flex-start;
            }
            .roles-sidebar {
                flex: 0 0 300px;
                position: sticky;
                top: 20px;
            }
            .permissions-content {
                flex: 1;
                min-width: 0;
            }
            
            /* Role List Items */
            .role-item {
                border: 1px solid #e9ecef;
                border-radius: 8px;
                margin-bottom: 0.5rem;
                padding: 1rem;
                cursor: pointer;
                transition: all 0.2s ease;
                background: white;
                position: relative;
                overflow: hidden;
            }
            .role-item:hover {
                border-color: #dee2e6;
                transform: translateY(-2px);
                box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            }
            .role-item.active {
                border-color: #0d6efd;
                background-color: #f8f9fa;
                box-shadow: 0 4px 12px rgba(13, 110, 253, 0.1);
            }
            .role-item.active::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                background: #0d6efd;
            }
            .permission-group {
                background: white;
                border-radius: 12px;
                border: 1px solid #e9ecef;
                margin-bottom: 1.5rem;
                overflow: hidden;
            }
            .permission-group-header {
                background: #f8f9fa;
                padding: 1rem 1.5rem;
                border-bottom: 1px solid #e9ecef;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .permission-group-header h6 {
                margin: 0;
                font-weight: 600;
                color: #495057;
                font-size: 0.95rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .permission-list {
                padding: 0.5rem 0;
            }
            .permission-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0.75rem 1.5rem;
                border-bottom: 1px solid #f1f3f5;
                transition: background 0.15s;
            }
            .permission-item:last-child {
                border-bottom: none;
            }
            .permission-item:hover {
                background: #fcfcfc;
            }
            .permission-info h6 {
                margin: 0 0 0.25rem 0;
                font-size: 0.95rem;
                color: #212529;
            }
            .permission-info small {
                color: #6c757d;
                font-size: 0.85rem;
            }
            .empty-state {
                text-align: center;
                padding: 4rem 2rem;
                background: white;
                border-radius: 12px;
                border: 2px dashed #e9ecef;
            }
            .empty-state-icon {
                font-size: 3rem;
                color: #dee2e6;
                margin-bottom: 1rem;
            }
            .category-selector {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
                margin-bottom: 1rem;
            }
            .category-btn {
                border: 1px solid #dee2e6;
                background: white;
                padding: 0.375rem 0.75rem;
                border-radius: 20px;
                font-size: 0.85rem;
                transition: all 0.2s;
            }
            .category-btn.active {
                background: #0d6efd;
                color: white;
                border-color: #0d6efd;
            }
            .category-btn:hover:not(.active) {
                background: #f8f9fa;
            }
            
            /* Inline editing styles */
            .edit-role-input {
                background: rgba(255, 255, 255, 0.9);
                border: 1px solid #0d6efd !important;
                border-radius: 4px;
                box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
                transition: all 0.2s ease;
            }
            .edit-role-input:focus {
                outline: none;
                box-shadow: 0 0 0 0.3rem rgba(13, 110, 253, 0.35);
            }
            .role-item.editing {
                border-color: #0d6efd;
                background-color: rgba(13, 110, 253, 0.05);
            }
            .role-item .btn-link {
                opacity: 0.6;
                transition: opacity 0.2s ease;
            }
            .role-item:hover .btn-link {
                opacity: 1;
            }
            .role-item .btn-link:hover {
                opacity: 1;
                transform: scale(1.1);
            }
        </style>

        <div class="row">
            <!-- Left Sidebar: Roles -->
            <div class="col-lg-4 mb-4">
                <div class="roles-sidebar">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-bold text-dark">CRM Roles <span class="badge bg-secondary-subtle text-secondary ms-2" id="roleCount" style="font-size: 0.8rem;">0</span></h5>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                            <i class="bi bi-plus-lg"></i> New Role
                        </button>
                    </div>
                    
                    <div class="alert alert-info border-0 shadow-sm mb-3">
                        <small class="d-block"><strong>Tip:</strong> Click on a role to manage its permissions. System roles cannot be modified.</small>
                    </div>
                    
                    <div id="rolesList" class="d-flex flex-column gap-2">
                        <!-- Roles loaded via JS -->
                    </div>
                </div>
            </div>
            
            <!-- Right Content: Permissions -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center sticky-top" style="z-index: 10;">
                        <div>
                            <h5 class="mb-0 fw-bold" id="selectedRoleTitle">Select a Role</h5>
                            <span class="text-muted small" id="selectedRoleDesc">Manage access control and privileges</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadDefaultRolePermissions()" id="loadDefaultsBtn" disabled>
                                <i class="bi bi-download"></i> Load Defaults
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="resetRolePermissions()" id="resetPermissionsBtn" disabled>
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </button>
                            <button type="button" class="btn btn-success btn-sm px-4" onclick="savePermissions()" id="savePermissionsBtn" disabled>
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body p-4 bg-light">
                        <div id="noRoleSelected" class="empty-state">
                            <i class="bi bi-shield-check empty-state-icon"></i>
                            <h5 class="text-dark">No Role Selected</h5>
                            <p class="text-muted mb-0">Select a role from the left sidebar to view and configure its permissions.</p>
                        </div>
                        
                        <div id="permissionsLoading" class="text-center py-5" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading permissions...</span>
                            </div>
                        </div>
                        
                        <div id="permissionsContainer" style="display: none;">
                            <div class="mb-4">
                                <h6 class="text-dark mb-3">Filter by Category:</h6>
                                <div class="category-selector" id="categorySelector">
                                    <!-- Categories loaded dynamically -->
                                </div>
                            </div>
                            
                            <div id="permissionsCategories">
                                <!-- Permission categories loaded here -->
                            </div>
                            
                            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mt-3">
                                <i class="bi bi-info-circle-fill text-info me-3 fs-4"></i>
                                <div>
                                    <strong>Permission Guidelines:</strong>
                                    <div class="small">Permissions are cumulative. Users with higher-level roles inherit permissions from lower roles. Changes are applied immediately upon saving.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Settings Tab -->
    <div class="tab-pane fade" id="system" role="tabpanel">
        <!-- Company Information Row -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-building"></i> Company Information</h5>
                        <button type="button" class="btn btn-primary btn-sm" onclick="saveCompanySettings()">
                            <i class="bi bi-save"></i> Save Company Info
                        </button>
                    </div>
                    <div class="card-body">
                        <form id="companySettingsForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" class="form-control" id="company_name" placeholder="e.g. BBSL Solutions" value="<?php echo htmlspecialchars($globalSettings['company_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Address</label>
                                    <textarea class="form-control" id="company_address" rows="1" placeholder="e.g. 123 Business Way, Lagos, Nigeria"><?php echo htmlspecialchars($globalSettings['company_address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Application Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="appSettingsForm">
                            <div class="mb-3">
                                <label class="form-label">Default Currency</label>
                                <select class="form-select" id="defaultCurrency">
                                    <option value="NGN">Nigerian Naira (NGN)</option>
                                    <option value="USD">US Dollar (USD)</option>
                                    <option value="EUR">Euro (EUR)</option>
                                    <option value="GBP">British Pound (GBP)</option>
                                </select>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" onclick="saveAppSettings()">
                                    <i class="bi bi-save"></i> Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">Email Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="emailSettingsForm">
                            <div class="mb-3">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" id="smtpHost" value="smtp.gmail.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">SMTP Port</label>
                                <input type="number" class="form-control" id="smtpPort" value="587">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email From</label>
                                <input type="email" class="form-control" id="emailFrom" value="noreply@yourcrm.com">
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" onclick="saveEmailSettings()">
                                    <i class="bi bi-save"></i> Save Email Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Currency Conversion Section -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-currency-exchange"></i> Currency Conversion Rates</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Set exchange rates for currency conversion. Base currency is NGN.</p>
                        
                        <form id="currencyRatesForm">
                            <div class="row">
                                <!-- NGN (Base Currency) -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><i class="bi bi-cash"></i> Nigerian Naira (NGN)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₦</span>
                                        <input type="number" class="form-control" id="ngn_rate" value="1.00" step="0.01" readonly>
                                        <span class="input-group-text">Base</span>
                                    </div>
                                    <small class="text-muted">Base currency (always 1.00)</small>
                                </div>
                                
                                <!-- USD -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><i class="bi bi-currency-dollar"></i> US Dollar (USD)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="usd_rate" value="0.000645" step="0.000001" min="0" oninput="updateConversionPreview()">
                                        <span class="input-group-text">per ₦1</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Conversion Preview -->
                            <div class="alert alert-info mt-3">
                                <h6 class="mb-3"><i class="bi bi-calculator"></i> Conversion Preview</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>₦100,000 NGN =</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <span id="preview_usd">$64.50 USD</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span id="preview_gbp">£51.00 GBP</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 justify-content-end mt-3">
                                <button type="button" class="btn btn-outline-secondary" onclick="resetDefaultRates()">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset to Defaults
                                </button>
                                <button type="button" class="btn btn-primary" onclick="saveCurrencyRates()">
                                    <i class="bi bi-save"></i> Save Rates
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addRoleForm">
                    <div class="mb-3">
                        <label class="form-label">Role Name</label>
                        <input type="text" class="form-control" id="roleName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="roleDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Base on existing role (optional)</label>
                        <select class="form-select" id="roleTemplate" onchange="if(this.value && !document.getElementById('roleName').value) document.getElementById('roleName').value = this.options[this.selectedIndex].text;">
                            <option value="">-- Start from scratch --</option>
                            <option value="admin">System Administrator</option>
                            <option value="sales_manager">Sales Manager</option>
                            <option value="sales_rep">Sales Representative</option>
                            <option value="marketing">Marketing Professional</option>
                            <option value="support">Customer Support Agent</option>
                            <option value="analyst">Analyst (Read-Only)</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addNewRole()">Create Role</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Lead Source Modal -->
<div class="modal fade" id="addSourceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Lead Source</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addSourceForm" onsubmit="event.preventDefault(); submitLeadSource();">
                    <div class="mb-3">
                        <label class="form-label">Source Name</label>
                        <input type="text" class="form-control" id="sourceName" required placeholder="e.g. LinkedIn, Webinar">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitLeadSource()">Add Source</button>
            </div>
        </div>
    </div>
</div>

<script>
// Backend Integration for Settings (Roles & Currencies)

// Application state
let currentRoles = [];
let selectedRoleId = null;
let permissionChanges = {};
let allPermissions = [];
let activeCategory = 'all';

// Default role permission templates
const roleTemplates = {
    'admin': {
        name: 'System Administrator',
        description: 'Has complete, unrestricted access to all features, settings, and data.',
        permissions: [58] // system_admin
    },
    'sales_manager': {
        name: 'Sales Manager',
        description: 'Oversees the sales team, pipeline, and performance. Needs team-wide visibility.',
        permissions: [1, 2, 3, 4, 5, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 21, 22, 23, 24, 25, 29, 30, 31, 32, 43, 44, 45, 46, 55, 61, 62, 63, 64, 65, 66]
    },
    'sales_rep': {
        name: 'Sales Representative',
        description: 'Primary user. Manages own leads, customers, opportunities, and activities.',
        permissions: [1, 2, 8, 9, 10, 13, 14, 15, 16, 21, 22, 23, 29, 30, 31, 43, 47, 48]
    },
    'marketing': {
        name: 'Marketing Professional',
        description: 'Focuses on lead generation and nurturing.',
        permissions: [1, 8, 9, 10, 11, 13, 14, 15, 16, 43, 61]
    },
    'support': {
        name: 'Customer Support Agent',
        description: 'Manages post-sale customer interactions.',
        permissions: [1, 13, 14, 15, 16, 17, 29, 30, 31, 47, 48]
    },
    'analyst': {
        name: 'Analyst (Read-Only)',
        description: 'Needs to analyze data across the organization but should not make changes.',
        permissions: [1, 2, 3, 8, 13, 17, 21, 29, 43, 47, 55]
    }
};

// === ROLES ===
async function loadRoles() {
    const list = document.getElementById('rolesList');
    list.innerHTML = '<div class="text-center p-3"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading roles...</div>';
    
    try {
        const response = await fetch('?api=true&action=roles');
        const data = await response.json();
        
        if (data.success) {
            currentRoles = data.data;
            renderRolesList(data.data);
        } else {
            list.innerHTML = `<div class="alert alert-danger p-2 m-2">Error: ${data.message || 'Unknown error'}</div>`;
        }
    } catch (e) {
        console.error(e);
        list.innerHTML = '<div class="alert alert-danger p-2 m-2">Connection failed</div>';
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderRolesList(roles) {
    const list = document.getElementById('rolesList');
    if (!list) return;
    
    list.innerHTML = '';
    
    // Update count
    const countBadge = document.getElementById('roleCount');
    if (countBadge) countBadge.textContent = roles.length;
    
    // Check if roles array is empty
    if (roles.length === 0) {
        list.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-shield-slash empty-state-icon"></i>
                <h5 class="text-dark">No Roles Found</h5>
                <p class="text-muted mb-3">Create your first role to get started.</p>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                    <i class="bi bi-plus-lg"></i> Create First Role
                </button>
            </div>
        `;
        return;
    }
    
    // Sort roles: system first, then custom
    const sortedRoles = [...roles].sort((a, b) => {
        if (a.is_system && !b.is_system) return -1;
        if (!a.is_system && b.is_system) return 1;
        const nameA = a.name || '';
        const nameB = b.name || '';
        return nameA.localeCompare(nameB);
    });
    
    sortedRoles.forEach(role => {
        const item = document.createElement('div');
        item.className = `role-item ${selectedRoleId === role.id ? 'active' : ''}`;
        item.onclick = () => selectRole(role.id);
        
        // Ensure permissions is an array
        if (role.permissions && typeof role.permissions === 'string') {
            try {
                role.permissions = JSON.parse(role.permissions);
            } catch (e) {
                role.permissions = [];
            }
        } else if (!Array.isArray(role.permissions)) {
            role.permissions = [];
        }
        
        const badge = role.is_system ? 
            '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2" style="font-size: 0.7rem;">System</span>' : 
            '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 mt-1" style="font-size: 0.7rem;">Custom</span>';
        
        // Add edit button for custom roles
        const actionButtons = role.is_system ? '' : 
            `<div class="d-flex gap-1">
                <button class="btn btn-sm btn-link text-primary p-0" 
                        title="Edit Name" 
                        onclick="event.stopPropagation(); enableRoleNameEditing(${role.id})">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-link text-danger p-0" 
                        title="Delete Role" 
                        onclick="event.stopPropagation(); deleteRole(${role.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`;
        
        const roleName = role.name || 'Untitled Role';
        const roleDesc = role.description || 'No description provided';

        item.dataset.roleId = role.id; // Add data attribute for robust selection
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="mb-0 fw-bold text-dark">${escapeHtml(roleName)}</h6>
                    ${badge}
                </div>
                ${actionButtons}
            </div>
            <p class="text-muted small mb-0 mt-2">${escapeHtml(roleDesc)}</p>
            <div class="mt-2 d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    <i class="bi bi-shield-check"></i> ${role.permissions.length} permissions
                </small>
                ${role.is_default ? '<small class="badge bg-info-subtle text-info border border-info-subtle px-2">Default</small>' : ''}
            </div>
        `;
        list.appendChild(item);
    });
}

// Inline role name editing functions
function enableRoleNameEditing(roleId) {
    console.log('Attempting to edit role:', roleId); // Debug Log
    
    // Use loose equality for ID matching to handle string/int differences
    const role = currentRoles.find(r => r.id == roleId);
    if (!role) {
        console.error('Role not found in currentRoles state:', roleId);
        return;
    }
    if (role.is_system) {
        console.warn('Cannot edit system role');
        return;
    }
    
    // Find the role item using the data attribute
    const selector = `.role-item[data-role-id="${roleId}"]`;
    const item = document.querySelector(selector);
    console.log('DOM Selector used:', selector, 'Found:', item); // Debug Log
    
    if (item) {
        const titleElement = item.querySelector('h6');
        if (titleElement) {
            const originalName = titleElement.textContent;
            titleElement.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <input type="text" 
                           class="form-control form-control-sm border-0 p-1 bg-transparent edit-role-input" 
                           value="${escapeHtml(originalName)}" 
                           style="width: auto; min-width: 150px;"
                           onkeypress="handleRoleNameKeypress(event, ${roleId})"
                           onblur="saveRoleNameChange(${roleId}, this.value)"
                           onclick="event.stopPropagation()">
                    <button class="btn btn-sm btn-link text-success p-0" 
                            onclick="saveRoleNameChange(${roleId}, this.previousElementSibling.value); event.stopPropagation()"
                            title="Save">
                        <i class="bi bi-check"></i>
                    </button>
                    <button class="btn btn-sm btn-link text-danger p-0" 
                            onclick="cancelRoleNameEdit(${roleId}, '${escapeHtml(originalName)}'); event.stopPropagation()"
                            title="Cancel">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            `;
            
            // Focus on the input
            const input = item.querySelector('.edit-role-input');
            if (input) {
                input.focus();
                input.select();
            }
        } else {
             console.error('Title element (h6) not found inside role item');
        }
    } else {
        console.error('DOM Element for role not found. Ensure renderRolesList is setting dataset.roleId correctly.');
        // Fallback or alert user
        alert('Error: Could not find role element on page. Please refresh.');
    }
}

function handleRoleNameKeypress(event, roleId) {
    if (event.key === 'Enter') {
        event.preventDefault();
        saveRoleNameChange(roleId, event.target.value);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        const role = currentRoles.find(r => r.id == roleId);
        if (role) {
            cancelRoleNameEdit(roleId, role.name);
        }
    }
}

async function saveRoleNameChange(roleId, newName) {
    const trimmedName = newName.trim();
    // Use loose equality for ID matching (string vs int)
    const role = currentRoles.find(r => r.id == roleId);
    
    if (!role || role.is_system) return;
    
    if (!trimmedName) {
        alert('Role name cannot be empty');
        cancelRoleNameEdit(roleId, role.name);
        return;
    }
    
    if (trimmedName === role.name) {
        // No change, just cancel edit
        cancelRoleNameEdit(roleId, role.name);
        return;
    }
    
    // Check for duplicate names
    const duplicate = currentRoles.find(r => 
        r.id !== roleId && 
        r.name.toLowerCase() === trimmedName.toLowerCase() &&
        !r.is_archived
    );
    
    if (duplicate) {
        alert('A role with this name already exists');
        cancelRoleNameEdit(roleId, role.name);
        return;
    }
    
    try {
        const response = await fetch('?api=true&action=roles', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id: roleId,
                name: trimmedName,
                description: role.description,
                permissions: role.permissions
            })
        });
        
        const data = await response.json();
        if (data.success) {
            // Update local state
            role.name = trimmedName;
            
            // Update the UI
            if (selectedRoleId == roleId) {
                // Update the main title if this role is selected
                const titleInput = document.getElementById('edit_role_name');
                if (titleInput) {
                    titleInput.value = trimmedName;
                }
            }
            
            // Re-render the roles list
            renderRolesList(currentRoles);
            
            // Show success message
            if (typeof showToast === 'function') {
                showToast('Role name updated successfully', 'success');
            } else {
                alert('Role name updated successfully!');
            }
        } else {
            alert('Error updating role: ' + data.message);
            cancelRoleNameEdit(roleId, role.name);
        }
    } catch (e) {
        console.error('Error updating role name:', e);
        alert('Network error updating role');
        cancelRoleNameEdit(roleId, role.name);
    }
}

function cancelRoleNameEdit(roleId, originalName) {
    renderRolesList(currentRoles);
}

async function addNewRole() {
    const nameInput = document.getElementById('roleName');
    const name = nameInput.value.trim();
    const desc = document.getElementById('roleDescription').value.trim();
    const template = document.getElementById('roleTemplate').value;
    
    if (!name) {
        nameInput.focus();
        nameInput.classList.add('is-invalid');
        setTimeout(() => nameInput.classList.remove('is-invalid'), 3000);
        return;
    }
    
    let permissions = [];
    if (template && roleTemplates[template]) {
        permissions = roleTemplates[template].permissions;
    }
    
    try {
        const response = await fetch('?api=true&action=roles', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                name: name,
                description: desc,
                permissions: permissions,
                is_system: 0,
                is_default: 0
            })
        });
        
        const data = await response.json();
        if (data.success) {
            const modalEl = document.getElementById('addRoleModal');
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            modal.hide();
            
            // Reset form
            document.getElementById('roleName').value = '';
            document.getElementById('roleDescription').value = '';
            document.getElementById('roleTemplate').value = '';
            
            // Reload roles list
            await loadRoles();
            
            // Auto-select the new role if we have the ID
            if (data.data && data.data.id) {
                setTimeout(() => selectRole(data.data.id), 500);
            }
            
            // Optional: show a success toast
            if (typeof showToast === 'function') {
                showToast('Role created successfully', 'success');
            } else {
                alert('Role created successfully!');
            }
        } else {
            alert(data.message || 'Error creating role');
        }
    } catch (e) {
        console.error('Role creation error:', e);
        alert('Network Error or invalid response');
    }
}

async function deleteRole(roleId) {
    // Use loose equality for ID matching
    const role = currentRoles.find(r => r.id == roleId);
    if (!role) return;
    
    if (role.is_system) {
        alert('System roles cannot be deleted.');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete the role "${role.name}"? This action cannot be undone.`)) return;
    
    try {
        // Use POST with delete param to avoid server blocks on DELETE verb
        const response = await fetch(`?api=true&action=roles&id=${roleId}&delete=true`, {
            method: 'POST'
        });
        const data = await response.json();
        if (data.success) {
            loadRoles();
            if (selectedRoleId == roleId) {
                selectedRoleId = null;
                document.getElementById('noRoleSelected').style.display = 'block';
                document.getElementById('permissionsContainer').style.display = 'none';
            }
        } else {
            alert(data.message);
        }
    } catch (e) {
        alert('Error deleting role');
    }
}

// === PERMISSIONS ===
async function loadAllPermissions() {
    try {
        const response = await fetch('?api=true&action=permissions');
        const data = await response.json();
        
        if (data.success) {
            allPermissions = data.data;
            renderCategorySelector();
        } else {
            console.error('Failed to load permissions:', data.message);
            allPermissions = [];
        }
    } catch (e) {
        console.error('Failed to load permissions:', e);
        allPermissions = [];
    }
}

function renderCategorySelector() {
    const selector = document.getElementById('categorySelector');
    if (!selector) return;
    
    // Get unique categories
    const categories = ['all', ...new Set(allPermissions.map(p => p.category))];
    
    selector.innerHTML = categories.map(category => `
        <button type="button" class="category-btn ${activeCategory === category ? 'active' : ''}" 
                onclick="setActiveCategory('${category}')">
            ${category === 'all' ? 'All Categories' : category}
        </button>
    `).join('');
}

function setActiveCategory(category) {
    activeCategory = category;
    renderCategorySelector();
    
    if (selectedRoleId) {
        populatePermissions(selectedRoleId);
    }
}

// Select a role to manage permissions
async function selectRole(roleId) {
    selectedRoleId = roleId;
    const role = currentRoles.find(r => r.id === roleId);
    if (!role) return;
    
    // Update active state in sidebar
    renderRolesList(currentRoles);
    
    // Update Header - Editable for custom roles
    const isSystemRole = role.is_system;
    const titleContainer = document.getElementById('selectedRoleTitle');
    const descContainer = document.getElementById('selectedRoleDesc');

    if (isSystemRole) {
        titleContainer.innerHTML = `<span class="text-primary">${escapeHtml(role.name)}</span> Permissions`;
        descContainer.textContent = role.description || 'System defined role';
    } else {
        titleContainer.innerHTML = `
            <div class="d-flex flex-column gap-1">
                <input type="text" class="form-control form-control-lg fw-bold border-0 p-0 bg-transparent text-primary" 
                       id="edit_role_name" value="${escapeHtml(role.name)}" placeholder="Role Name"
                       onchange="document.getElementById('savePermissionsBtn').disabled = false;">
                <input type="text" class="form-control form-control-sm border-0 p-0 bg-transparent text-muted" 
                       id="edit_role_desc" value="${escapeHtml(role.description || '')}" placeholder="Add a description..."
                       onchange="document.getElementById('savePermissionsBtn').disabled = false;">
            </div>
        `;
        descContainer.textContent = ''; // Hidden when editing
    }

    document.getElementById('noRoleSelected').style.display = 'none';
    
    // Show loading
    document.getElementById('permissionsLoading').style.display = 'block';
    document.getElementById('permissionsContainer').style.display = 'none';
    
    // Reset changes
    permissionChanges = {};
    
    // Load permissions if not already loaded
    if (allPermissions.length === 0) {
        await loadAllPermissions();
    }
    
    // Populate permissions
    populatePermissions(roleId);
    
    // Hide loading, show container
    document.getElementById('permissionsLoading').style.display = 'none';
    document.getElementById('permissionsContainer').style.display = 'block';
    
    // Enable/disable buttons
    const saveBtn = document.getElementById('savePermissionsBtn');
    const loadDefaultsBtn = document.getElementById('loadDefaultsBtn');
    const resetBtn = document.getElementById('resetPermissionsBtn');
    
    if (isSystemRole) {
        loadDefaultsBtn.disabled = true;
        resetBtn.disabled = true;
        saveBtn.disabled = true;
    } else {
        loadDefaultsBtn.disabled = false;
        resetBtn.disabled = false;
        saveBtn.disabled = false;
    }
}

// Populate permissions with new grouped layout
function populatePermissions(roleId) {
    const container = document.getElementById('permissionsCategories');
    if (!container) return;
    container.innerHTML = '';
    
    const role = currentRoles.find(r => r.id === roleId);
    if (!role) return;
    
    // Filter permissions by active category
    const filteredPermissions = allPermissions.filter(perm => 
        activeCategory === 'all' || perm.category === activeCategory
    );
    
    // Group by category
    const categories = {};
    filteredPermissions.forEach(perm => {
        if (!categories[perm.category]) categories[perm.category] = [];
        categories[perm.category].push(perm);
    });
    
    Object.keys(categories).forEach(category => {
        const groupDiv = document.createElement('div');
        groupDiv.className = 'permission-group'; 
        
        let permissionsHTML = '';
        categories[category].forEach(perm => {
            // Use loose comparison (==) to handle potential string/int mismatches from DB/JSON
            const hasPermission = role.permissions && role.permissions.some(p => p == perm.id);
            const isDisabled = role.is_system;
            const isChecked = permissionChanges.hasOwnProperty(perm.id) ? 
                permissionChanges[perm.id] : hasPermission;
            
            permissionsHTML += `
                <div class="permission-item">
                    <div class="permission-info">
                        <h6>${perm.name}</h6>
                        <small>${perm.description}</small>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" 
                               style="width: 3em; height: 1.5em;"
                               id="perm_${perm.id}" 
                               ${isChecked ? 'checked' : ''}
                               ${isDisabled ? 'disabled' : ''}
                               onchange="togglePermission(${perm.id}, this.checked)">
                    </div>
                </div>
            `;
        });
        
        groupDiv.innerHTML = `
            <div class="permission-group-header">
                <h6>${category}</h6>
                <small class="text-muted">${categories[category].length} permissions</small>
            </div>
            <div class="permission-list">
                ${permissionsHTML}
            </div>
        `;
        container.appendChild(groupDiv);
    });
}

function togglePermission(permissionId, isGranted) {
    if (!selectedRoleId) return;
    const role = currentRoles.find(r => r.id === selectedRoleId);
    if (!role || role.is_system) return;
    
    permissionChanges[permissionId] = isGranted;
    document.getElementById('savePermissionsBtn').disabled = false;
}

async function savePermissions() {
    if (!selectedRoleId) return;
    // Check if anything changed (btn is enabled)
    if (document.getElementById('savePermissionsBtn').disabled) return;
    
    const role = currentRoles.find(r => r.id === selectedRoleId);
    if (!role) return;

    // Apply changes to current permissions
    let updatedPermissions = [...(role.permissions || [])];
    
    for (const [permId, isGranted] of Object.entries(permissionChanges)) {
        const id = parseInt(permId);
        const hasPermission = updatedPermissions.includes(id);
        
        if (isGranted && !hasPermission) {
            updatedPermissions.push(id);
        } else if (!isGranted && hasPermission) {
            updatedPermissions = updatedPermissions.filter(p => p !== id);
        }
    }

    // Get updated name/desc if custom role
    const isSystemRole = role.is_system;
    const nameInput = document.getElementById('edit_role_name');
    const descInput = document.getElementById('edit_role_desc');
    
    const name = !isSystemRole && nameInput ? nameInput.value : role.name;
    const description = !isSystemRole && descInput ? descInput.value : role.description;

    try {
        const response = await fetch('?api=true&action=roles', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id: role.id,
                name: name,
                description: description,
                permissions: updatedPermissions
            })
        });

        const data = await response.json();
        
        if (data.success) {
            // Update local state
            role.name = name;
            role.description = description;
            role.permissions = updatedPermissions;
            currentRoles = currentRoles.map(r => r.id === role.id ? role : r);
            
            alert('Settings saved successfully');
            permissionChanges = {};
            document.getElementById('savePermissionsBtn').disabled = true;
            
            // Refresh the view
            renderRolesList(currentRoles);
            populatePermissions(selectedRoleId);
        } else {
            alert('Failed to save: ' + data.message);
        }
    } catch (e) {
        alert('Network Error');
    }
}

function resetRolePermissions() {
    if (!selectedRoleId) return;
    
    if (confirm('Reset all permissions for this role? This will clear all current permissions.')) {
        const role = currentRoles.find(r => r.id === selectedRoleId);
        if (role) {
            role.permissions = [];
            permissionChanges = {};
            populatePermissions(selectedRoleId);
            document.getElementById('savePermissionsBtn').disabled = false;
        }
    }
}

function loadDefaultRolePermissions() {
    if (!selectedRoleId) return;
    
    const role = currentRoles.find(r => r.id === selectedRoleId);
    if (!role) return;
    
    const roleName = role.name.toLowerCase();
    let templateKey = null;
    
    // Find matching template
    if (roleName.includes('sales') && roleName.includes('manager')) {
        templateKey = 'sales_manager';
    } else if (roleName.includes('sales') && (roleName.includes('rep') || roleName.includes('representative'))) {
        templateKey = 'sales_rep';
    } else if (roleName.includes('market')) {
        templateKey = 'marketing';
    } else if (roleName.includes('support') || roleName.includes('service')) {
        templateKey = 'support';
    } else if (roleName.includes('analyst') || roleName.includes('read')) {
        templateKey = 'analyst';
    }
    
    if (templateKey && roleTemplates[templateKey]) {
        if (confirm(`Load default permissions for "${roleTemplates[templateKey].name}"?`)) {
            role.permissions = roleTemplates[templateKey].permissions;
            permissionChanges = {};
            populatePermissions(selectedRoleId);
            document.getElementById('savePermissionsBtn').disabled = false;
        }
    } else {
        alert('No default template found for this role name. Please select a template manually.');
    }
}

// === CURRENCIES ===
async function loadCurrencies() {
    try {
        const response = await fetch('?api=true&action=currencies');
        const data = await response.json();
        
        if (data.success) {
            const currencies = data.data;
            const gbp = currencies.find(c => c.code === 'GBP');
            const usd = currencies.find(c => c.code === 'USD');
            
            if (gbp) document.getElementById('gbp_rate').value = gbp.exchange_rate;
            if (usd) document.getElementById('usd_rate').value = usd.exchange_rate;
            
            updateConversionPreview();
        }
    } catch(e) { console.error(e); }
}

async function saveCurrencyRates() {
    const gbp = document.getElementById('gbp_rate').value;
    const usd = document.getElementById('usd_rate').value;
    
    if (!gbp || !usd || parseFloat(gbp) <= 0 || parseFloat(usd) <= 0) {
        alert('Please enter valid exchange rates.');
        return;
    }
    
    try {
        const response = await fetch('?api=true&action=currencies', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                currencies: [
                    {code: 'GBP', rate: gbp},
                    {code: 'USD', rate: usd},
                    {code: 'NGN', rate: 1.00}
                ]
            })
        });
        const data = await response.json();
        if (data.success) {
            alert('Currency rates saved successfully!');
        } else {
            alert('Error saving rates: ' + data.message);
        }
    } catch(e) { 
        alert('Network error saving rates');
        console.error(e);
    }
}

// === SYSTEM SETTINGS ===
async function saveAppSettings() {
    const defaultCurrency = document.getElementById('defaultCurrency').value;
    try {
        const response = await fetch('?api=true&action=app_settings', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({defaultCurrency})
        });
        const data = await response.json();
        if (data.success) {
            alert('Application settings saved successfully!');
        } else {
            alert('Error saving settings');
        }
    } catch(e) { 
        alert('Network error');
        console.error(e);
    }
}

async function saveCompanySettings() {
    const company_name = document.getElementById('company_name').value;
    const company_address = document.getElementById('company_address').value;
    try {
        const response = await fetch('?api=true&action=app_settings', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({company_name, company_address})
        });
        const data = await response.json();
        if (data.success) {
            alert('Company settings saved successfully!');
            // Update global settings object if it exists to reflect changes immediately
            if (window.CRM_APP_SETTINGS) {
                window.CRM_APP_SETTINGS.company_name = company_name;
                window.CRM_APP_SETTINGS.company_address = company_address;
            }
        } else {
            alert('Error saving company settings');
        }
    } catch(e) { 
        alert('Network error');
        console.error(e);
    }
}

async function saveEmailSettings() {
    const smtpHost = document.getElementById('smtpHost').value;
    const smtpPort = document.getElementById('smtpPort').value;
    const emailFrom = document.getElementById('emailFrom').value;
    
    if (!smtpHost || !smtpPort || !emailFrom) {
        alert('Please fill all email settings fields.');
        return;
    }
    
    try {
        const response = await fetch('?api=true&action=app_settings', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({smtpHost, smtpPort, emailFrom})
        });
        const data = await response.json();
        if (data.success) {
            alert('Email settings saved successfully!');
        } else {
            alert('Error saving email settings');
        }
    } catch(e) { 
        alert('Network error');
        console.error(e);
    }
}

function resetDefaultRates() {
    if (confirm('Reset currency rates to defaults?')) {
        document.getElementById('gbp_rate').value = '0.00051';
        document.getElementById('usd_rate').value = '0.000645';
        updateConversionPreview();
    }
}

function updateConversionPreview() {
    const gbpRate = parseFloat(document.getElementById('gbp_rate').value) || 0.00051;
    const usdRate = parseFloat(document.getElementById('usd_rate').value) || 0.000645;
    
    document.getElementById('preview_gbp').textContent = `£${(100000 * gbpRate).toFixed(2)} GBP`;
    document.getElementById('preview_usd').textContent = `$${(100000 * usdRate).toFixed(2)} USD`;
}

// === LEAD SOURCES ===
async function loadLeadSources() {
    const list = document.getElementById('leadSourcesList');
    list.innerHTML = '<li class="list-group-item text-center">Loading...</li>';
    
    try {
        const response = await fetch('?api=true&action=lead_sources');
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            list.innerHTML = '';
            data.data.forEach(source => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                li.innerHTML = `
                    <span>${source.name}</span>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteLeadSource(${source.id}, '${source.name}')">
                        <i class="bi bi-trash"></i>
                    </button>
                `;
                list.appendChild(li);
            });
        } else {
            list.innerHTML = '<li class="list-group-item text-center text-muted">No sources found. Add one above.</li>';
        }
    } catch (e) {
        list.innerHTML = `<li class="list-group-item text-danger">Error loading sources: ${e.message}</li>`;
    }
}

function addLeadSource() {
    const modalEl = document.getElementById('addSourceModal');
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById('sourceName').value = '';
    document.getElementById('sourceName').classList.remove('is-invalid');
    modal.show();
    
    // Auto-focus input
    modalEl.addEventListener('shown.bs.modal', function () {
        document.getElementById('sourceName').focus();
    }, {once: true});
}

async function submitLeadSource() {
    const nameInput = document.getElementById('sourceName');
    const name = nameInput.value.trim();
    
    if (!name) {
        nameInput.classList.add('is-invalid');
        return;
    }
    
    try {
        const response = await fetch('?api=true&action=lead_sources', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({name: name})
        });
        const data = await response.json();
        
        if (data.success) {
            // Close modal
            const modalEl = document.getElementById('addSourceModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            
            loadLeadSources();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) {
        alert('Network error: ' + e.message);
    }
}

async function deleteLeadSource(id, name) {
    if (!confirm(`Are you sure you want to delete "${name}"? Existing leads with this source will keep the text value, but it will be removed from the list.`)) return;
    
    try {
        const response = await fetch(`?api=true&action=lead_sources&id=${id}`, {
            method: 'DELETE'
        });
        const data = await response.json();
        
        if (data.success) {
            loadLeadSources();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) {
        alert('Network error: ' + e.message);
    }
}

// Initial Load
document.addEventListener('DOMContentLoaded', () => {
    loadRoles();
    loadCurrencies();
    
    // Lazy load sources when tab is shown
    document.getElementById('sources-tab').addEventListener('shown.bs.tab', function (e) {
        loadLeadSources();
    });
    
    // Add event listener for modal shown to reset form
    const addRoleModal = document.getElementById('addRoleModal');
    if (addRoleModal) {
        addRoleModal.addEventListener('shown.bs.modal', function() {
            document.getElementById('roleName').focus();
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>