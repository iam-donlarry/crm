<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

requirePermission('view_users');

require_once __DIR__ . '/../../includes/header.php';

$users = $pdo->query("
    SELECT u.*, 
           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as name, 
           r.name as role_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    WHERE u.is_archived = 0 
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$roles = $pdo->query("SELECT id, name FROM roles WHERE is_archived = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$adminCount = count(array_filter($users, fn($u) => strtolower($u['role_name'] ?? $u['role']) === 'admin' || strtolower($u['role_name'] ?? $u['role']) === 'system administrator'));
?>

<div class="container-fluid px-0">
    <!-- Header & Breadcrumb -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Users', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">User & Staff Directory</h2>
            <p class="text-muted small mb-0">Manage employee accounts, security roles, and system access rights.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (hasPermission('add_users')): ?>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#userOffcanvas" onclick="resetUserForm()">
                    <i data-lucide="user-plus" class="lucide-icon me-1"></i> Add Staff Member
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats Strip -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Staff Members', count($users), 'users', 'primary'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Administrators', $adminCount, 'shield-check', 'warning', 'Full system access'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Defined Roles', count($roles) ?: 3, 'user-cog', 'info', 'Permission levels'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Account Status', '100% Active', 'check-circle', 'success', 'Archived excluded'); ?>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="background: #f8fafc;">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" class="lucide-icon text-muted"></i></span>
                    <input type="text" id="searchFilter" class="form-control border-start-0" placeholder="Search staff members..." onkeyup="filterUsers()">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="usersTable" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">User / Staff</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Email Address</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Phone</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Assigned Role</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Joined</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted small">
                                    <i data-lucide="inbox" class="lucide-icon d-block mb-2 text-muted mx-auto"></i>
                                    No staff accounts found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="py-3 px-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle avatar-brand d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                <?php echo strtoupper(substr($u['name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="fw-semibold text-dark d-block"><?php echo htmlspecialchars($u['name']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($u['phone'] ?: '-'); ?></td>
                                    <td class="py-3 px-4"><span class="badge badge-soft-primary rounded-pill"><?php echo htmlspecialchars($u['role_name'] ?: $u['role']); ?></span></td>
                                    <td class="py-3 px-4 text-muted small"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                    <td class="py-3 px-4 text-end">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8rem; font-family: 'Outfit', sans-serif;">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 text-small">
                                                <?php if (hasPermission('edit_users')): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="#" onclick="editUser(<?php echo $u['id']; ?>); return false;">
                                                            <i data-lucide="edit-3" class="lucide-icon text-muted"></i> Edit Account / Role
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (hasPermission('archive_users') && intval($u['id']) !== intval($_SESSION['user_id'])): ?>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2" href="#" onclick="deactivateUser(<?php echo $u['id']; ?>); return false;">
                                                            <i data-lucide="user-x" class="lucide-icon text-danger"></i> Deactivate Account
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 50% WIDTH OFFCANVAS SLIDER FOR NEW / EDIT USER STAFF -->
<div class="offcanvas offcanvas-end offcanvas-half" id="userOffcanvas" tabindex="-1" aria-labelledby="userOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold text-dark" id="userOffcanvasTitle" style="font-family: 'Outfit', sans-serif;">Add New Staff Member</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form id="userForm" onsubmit="saveUser(event)">
        <div class="offcanvas-body p-4">
            <input type="hidden" id="usrId" name="id">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" id="usrFirstName" name="first_name" class="form-control" placeholder="John" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" id="usrLastName" name="last_name" class="form-control" placeholder="Doe" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" id="usrEmail" name="email" class="form-control" placeholder="john@company.com" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Phone Number</label>
                    <input type="text" id="usrPhone" name="phone" class="form-control" placeholder="+234 ...">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Security Role</label>
                    <select id="usrRoleId" name="role_id" class="form-select">
                        <option value="">Select Security Role</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Password <span class="text-muted fw-normal small">(Leave blank if editing)</span></label>
                    <input type="password" id="usrPassword" name="password" class="form-control" placeholder="••••••••">
                </div>
            </div>
        </div>
        <div class="offcanvas-footer gap-2 d-flex justify-content-end">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="offcanvas">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4">Save Staff Account</button>
        </div>
    </form>
</div>

<script>
function resetUserForm() {
    document.getElementById('userForm').reset();
    document.getElementById('usrId').value = '';
    document.getElementById('userOffcanvasTitle').textContent = 'Add New Staff Member';
}

function saveUser(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('userForm')).entries());
    fetch('<?php echo BASE_URL; ?>/ajax/users/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(res.message || 'Save failed', 'danger');
        }
    })
    .catch(err => showToast('Error processing request', 'danger'));
}

function editUser(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/users/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const u = res.data;
            document.getElementById('usrId').value = u.id;
            document.getElementById('usrFirstName').value = u.first_name || '';
            document.getElementById('usrLastName').value = u.last_name || '';
            document.getElementById('usrEmail').value = u.email || '';
            document.getElementById('usrPhone').value = u.phone || '';
            document.getElementById('usrRoleId').value = u.role_id || '';
            document.getElementById('usrPassword').value = '';
            document.getElementById('userOffcanvasTitle').textContent = 'Edit Staff Member';
            new bootstrap.Offcanvas(document.getElementById('userOffcanvas')).show();
        }
    });
}

async function deactivateUser(id) {
    const confirmed = await window.appConfirm({
        title: 'Deactivate Staff Account',
        message: 'Are you sure you want to deactivate this staff account? The user will no longer be able to sign in.',
        confirmText: 'Yes, Deactivate Account',
        cancelText: 'Cancel',
        isDanger: true
    });
    if (!confirmed) return;

    fetch('<?php echo BASE_URL; ?>/ajax/users/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast(res.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(res.message, 'danger');
        }
    });
}

function filterUsers() {
    const val = document.getElementById('searchFilter').value.toLowerCase();
    document.querySelectorAll('#usersTable tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
