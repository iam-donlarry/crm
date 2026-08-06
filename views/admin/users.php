<?php
// API Handling Logic
if (isset($_GET['api']) && $_GET['api'] === 'true') {
    require_once '../../includes/config.php';
    require_once '../../includes/utils.php';
    require_once '../../includes/auth.php';

    header('Content-Type: application/json');

    // Check Authentication (Basic Session Check)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        sendResponse(false, 'Unauthorized');
    }

    // Check Authorization (Simple Admin check for mutations)
    $isAdmin = hasPermission('system_admin'); // Keep for generic admin checks if needed
    $method = $_SERVER['REQUEST_METHOD'];

    // Handle Requests
    switch ($method) {
        case 'GET':
            $requestedId = isset($_GET['id']) ? intval($_GET['id']) : null;
            $isSelf = $requestedId && $requestedId == $_SESSION['user_id'];
            if (!$isSelf && !hasPermission('view_users')) sendResponse(false, 'Access Denied');
            handleGet($pdo);
            break;
        case 'POST':
            if (!hasPermission('add_users')) sendResponse(false, 'Access Denied');
            handlePost($pdo);
            break;
        case 'PUT':
            handlePut($pdo); // Self-update allowed, admin update others
            break;
        case 'DELETE':
            if (!hasPermission('delete_users')) {
                sendResponse(false, 'Forbidden: You do not have permission to delete users.');
            }
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            sendResponse(false, 'Method Not Allowed');
    }
    exit;
}

function handleGet($pdo) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $role = isset($_GET['role']) ? $_GET['role'] : null;

    try {
        if ($id) {
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, role, staff_id, image, created_at FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if ($user) {
                sendResponse(true, 'User fetched', $user);
            } else {
                sendResponse(false, 'User not found');
            }
        } elseif (isset($_GET['type']) && $_GET['type'] === 'roles') {
            $stmt = $pdo->query("SELECT id, name FROM roles WHERE is_archived = 0 ORDER BY name ASC");
            $roles = $stmt->fetchAll();
            sendResponse(true, 'Roles fetched', $roles);
        } else {
            // Updated query to include role_id and role name from roles table
            $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.role, u.role_id, r.name as role_name, u.staff_id, u.created_at 
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.is_archived = ?";
            
            $archived = isset($_GET['archived']) && $_GET['archived'] === 'true' ? 1 : 0;
            $params = [$archived];
            if ($role) {
                $sql .= " AND u.role = ?";
                $params[] = $role;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();
            sendResponse(true, 'Users fetched', $users);
        }
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handlePost($pdo) {
    $data = getJsonInput();
    
    if (empty($data['email']) || empty($data['password'])) {
        sendResponse(false, 'Email and Password are required');
    }

    try {
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Prepare Name (concat for backward compatibility if needed, or just rely on new cols)
        // We will fill the 'name' column as "First Last"
        $first = $data['first_name'] ?? '';
        $last = $data['last_name'] ?? '';

        $sql = "INSERT INTO users (first_name, last_name, email, password, role, role_id, phone, staff_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $first,
            $last,
            $data['email'],
            $passwordHash,
            $data['role_name'] ?? 'user', // Legacy role string
            $data['role_id'] ?? null,
            $data['phone'] ?? null,
            $data['staff_id'] ?? null
        ]);

        sendResponse(true, 'User created successfully', ['id' => $pdo->lastInsertId()]);

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { 
            sendResponse(false, 'Email or Staff ID already exists');
        }
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handlePut($pdo) {
    $data = getJsonInput();
    
    $targetId = isset($data['id']) ? intval($data['id']) : $_SESSION['user_id'];
    
    // Authorization Check: Admin can edit anyone, User can only edit themselves
    // Also allow anyone with 'edit_users' permission to edit others
    if (!hasPermission('edit_users') && $targetId !== $_SESSION['user_id']) {
        sendResponse(false, 'Access Denied');
    }

    $fields = [];
    $values = [];

    if (isset($data['first_name'])) { $fields[] = "first_name = ?"; $values[] = $data['first_name']; }
    if (isset($data['last_name'])) { $fields[] = "last_name = ?"; $values[] = $data['last_name']; }

    if (isset($data['email'])) { $fields[] = "email = ?"; $values[] = $data['email']; }
    if (isset($data['phone'])) { $fields[] = "phone = ?"; $values[] = $data['phone']; }
    if (isset($data['staff_id'])) { $fields[] = "staff_id = ?"; $values[] = $data['staff_id']; }
    if (isset($data['image'])) { $fields[] = "image = ?"; $values[] = $data['image']; }
    if (isset($data['is_archived'])) { $fields[] = "is_archived = ?"; $values[] = $data['is_archived']; }
    
    if ((isset($data['role']) || isset($data['role_id'])) && hasPermission('manage_roles')) {
        if (isset($data['role_name'])) {
            $fields[] = "role = ?"; 
            $values[] = $data['role_name'];
        }
        if (isset($data['role_id'])) {
             $fields[] = "role_id = ?"; 
             $values[] = $data['role_id'];
        }
    }
    
    if (isset($data['password']) && !empty($data['password'])) {
        $fields[] = "password = ?";
        $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $values[] = $targetId;
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        sendResponse(true, 'User updated successfully');
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

function handleDelete($pdo) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$id) sendResponse(false, 'ID required');

    if ($id === $_SESSION['user_id']) {
        sendResponse(false, 'Cannot delete your own account');
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET is_archived = 1 WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, 'User archived successfully');
    } catch (PDOException $e) {
        sendResponse(false, 'Database Error: ' . $e->getMessage());
    }
}

require_once '../../includes/header.php';

// Auth check handled by frontend CRMAuth
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-people"></i> User Management</h1>
    <div>
        <button class="btn btn-primary" onclick="openAddUserModal()">
            <i class="bi bi-person-plus"></i> Add User
        </button>
        <button class="btn btn-outline-secondary ms-2" id="toggleArchiveBtn" onclick="toggleArchiveView()">
            <i class="bi bi-archive"></i> Show Archived
        </button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Staff I.D</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr>
                        <td colspan="6" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId" name="id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Staff ID</label>
                        <input type="text" class="form-control" id="staffId" name="staff_id">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" id="userEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span id="passwordOptional" style="display:none;">(Leave blank to keep current)</span></label>
                        <input type="password" class="form-control" id="userPassword" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select class="form-select" id="userRole" name="role" required>
                            <option value="">Loading...</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="editUserBtn" onclick="enableEditMode()">Edit</button>
                <button type="button" class="btn btn-primary d-none" id="saveUserBtn" onclick="saveUser()">Save User</button>
            </div>
        </div>
    </div>
</div>

<script>
const UserManager = {
    apiUrl: '?api=true',
    async getAll() {
        try {
            const response = await fetch(this.apiUrl);
            return (await response.json()).data || [];
        } catch (e) { return []; }
    },
    async getRoles() {
        try {
            const response = await fetch(`${this.apiUrl}&type=roles`);
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

let userModal;
let availableRoles = [];
let viewingArchived = false;

document.addEventListener('DOMContentLoaded', async () => {
    loadUsers();
    await loadRoles();
    userModal = new bootstrap.Modal(document.getElementById('addUserModal'));
});

function openAddUserModal() {
    loadRoles(); // Refresh roles list
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    
    // Enable fields
    toggleFormFields(false);
    
    // Button States
    document.getElementById('saveUserBtn').classList.remove('d-none');
    document.getElementById('editUserBtn').classList.add('d-none');
    
    document.getElementById('userPassword').required = true;
    document.getElementById('passwordOptional').style.display = 'none';
    
    document.getElementById('userModalTitle').innerText = 'Add New User';
    userModal.show();
}

function toggleFormFields(disabled) {
    const form = document.getElementById('userForm');
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => input.disabled = disabled);
}

function enableEditMode() {
    toggleFormFields(false);
    document.getElementById('saveUserBtn').classList.remove('d-none');
    document.getElementById('editUserBtn').classList.add('d-none');
    document.getElementById('userModalTitle').innerText = 'Edit User';
}

async function loadRoles() {
    availableRoles = await UserManager.getRoles();
    const select = document.getElementById('userRole');
    select.innerHTML = '<option value="">Select Role</option>';
    availableRoles.forEach(role => {
        select.innerHTML += `<option value="${role.id}">${role.name}</option>`;
    });
}

async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';
    
    try {
        const response = await fetch(`${UserManager.apiUrl}&archived=${viewingArchived}`);
        const users = (await response.json()).data || [];
        renderUsers(users);
        updateArchiveToggleUI();
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading users</td></tr>';
    }
}

function renderUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No users found.</td></tr>';
        return;
    }
    
    tbody.innerHTML = users.map(u => `
        <tr>
            <td>
                <div class="fw-bold">${u.first_name || ''} ${u.last_name || ''}</div>
                <!-- <div class="small text-muted"></div> -->
            </td>
            <td>${u.email}</td>
            <td><span class="badge bg-secondary">${u.role_name || u.role}</span></td>
            <td>${u.staff_id || '-'}</td>
            <td>${u.created_at ? new Date(u.created_at).toLocaleDateString() : '-'}</td>
            <td>
                ${viewingArchived ? 
                    `<button class="btn btn-sm btn-success" title="Restore" onclick="restoreUser(${u.id})"><i class="bi bi-arrow-counterclockwise"></i></button>`
                    :
                    `<button class="btn btn-sm btn-outline-primary" onclick="viewUser(${u.id})"><i class="bi bi-eye"></i></button>
                     <button class="btn btn-sm btn-outline-warning" title="Archive" onclick="archiveUser(${u.id})"><i class="bi bi-archive"></i></button>`
                }
            </td>
        </tr>
    `).join('');
}

async function saveUser() {
    const id = document.getElementById('userId').value;
    const roleSelect = document.getElementById('userRole');
    const selectedRoleOption = roleSelect.options[roleSelect.selectedIndex];
    
    const data = {
        first_name: document.getElementById('firstName').value,
        last_name: document.getElementById('lastName').value,
        staff_id: document.getElementById('staffId').value,
        email: document.getElementById('userEmail').value,
        role_id: roleSelect.value,
        role_name: selectedRoleOption.text,
        password: document.getElementById('userPassword').value
    };
    
    if (!data.first_name || !data.last_name || !data.email || !data.role_id || (!id && !data.password)) {
        alert('Missing required fields');
        return;
    }
    
    let result;
    if (id) {
        result = await UserManager.update(id, data);
    } else {
        result = await UserManager.create(data);
    }
    
    if (result.success) {
        userModal.hide();
        loadUsers();
    } else {
        alert(result.message);
    }
}

async function viewUser(id) {
    await loadRoles(); // Ensure latest roles are loaded
    const users = await UserManager.getAll();
    const user = users.find(u => u.id == id);
    if (!user) return;
    
    document.getElementById('userId').value = user.id;
    document.getElementById('firstName').value = user.first_name || '';
    document.getElementById('lastName').value = user.last_name || '';
    document.getElementById('staffId').value = user.staff_id || '';
    document.getElementById('userEmail').value = user.email;
    
    // Set role dropdown - prefer role_id, fallback to finding by name if legacy
    if (user.role_id) {
        document.getElementById('userRole').value = user.role_id;
    } else if (user.role) {
        // Fallback for legacy users without role_id
        // Try to match role name to an ID
        const matchingRole = availableRoles.find(r => r.name.toLowerCase() === user.role.toLowerCase());
        if (matchingRole) {
            document.getElementById('userRole').value = matchingRole.id;
        }
    }
    
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = false;
    document.getElementById('passwordOptional').style.display = 'inline';
    
    // View Mode Init
    toggleFormFields(true);
    document.getElementById('saveUserBtn').classList.add('d-none');
    document.getElementById('editUserBtn').classList.remove('d-none');
    document.getElementById('userModalTitle').innerText = 'View User';
    
    userModal.show();
}

async function archiveUser(id) {
    if (confirm('Are you sure you want to archive this user?')) {
        const res = await UserManager.delete(id); // Maps to delete endpoint which now archives
        if (res.success) {
            loadUsers();
        } else {
            alert(res.message);
        }
    }
}

async function restoreUser(id) {
    if (confirm('Are you sure you want to restore this user?')) {
        const res = await UserManager.update(id, { is_archived: 0 });
        if (res.success) {
            alert('User restored successfully');
            loadUsers();
        } else {
            alert(res.message);
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
    loadUsers();
}
</script>

<?php require_once '../../includes/footer.php'; ?>

