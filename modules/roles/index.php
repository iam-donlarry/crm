<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

// Access Control
requirePermission('manage_roles');

require_once __DIR__ . '/../../includes/header.php';

// Fetch all active roles with counts
$roles = $pdo->query("
    SELECT r.*, 
           COUNT(DISTINCT u.id) as user_count,
           COUNT(DISTINCT rp.permission_id) as perm_count
    FROM roles r
    LEFT JOIN users u ON u.role_id = r.id AND u.is_archived = 0
    LEFT JOIN role_permissions rp ON rp.role_id = r.id
    WHERE r.is_archived = 0
    GROUP BY r.id
    ORDER BY r.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all permissions grouped by category
$permissionsRaw = $pdo->query("SELECT * FROM permissions ORDER BY category, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$permissionsByCategory = [];
foreach ($permissionsRaw as $p) {
    $cat = $p['category'] ?: 'General';
    $permissionsByCategory[$cat][] = $p;
}

$totalRoles = count($roles);
$systemRolesCount = count(array_filter($roles, fn($r) => $r['is_system'] == 1));
$customRolesCount = $totalRoles - $systemRolesCount;
$totalStaffCount = array_sum(array_column($roles, 'user_count'));
?>

<div class="container-fluid px-0">
    <!-- Header & Breadcrumb -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Roles & Permissions', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Roles & Access Control</h2>
            <p class="text-muted small mb-0">Manage employee security roles, clone permissions, and configure access levels across all CRM modules.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#roleOffcanvas" onclick="openCreateRoleModal()">
                <i data-lucide="shield-plus" class="lucide-icon me-1"></i> Create Security Role
            </button>
        </div>
    </div>

    <!-- Quick Stats Strip -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Configured Roles', $totalRoles, 'shield-check', 'primary', 'Active system & custom roles'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Custom Roles', $customRolesCount, 'user-cog', 'info', 'Organization specific'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Assigned Staff', $totalStaffCount, 'users', 'success', 'Active user accounts'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Permission Catalog', count($permissionsRaw), 'key', 'warning', 'Granular control keys'); ?>
        </div>
    </div>

    <!-- Roles Table Card (Rows Layout) -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <!-- Table Filter Toolbar -->
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="background: #f8fafc;">
                <div class="input-group input-group-sm" style="max-width: 340px;">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" class="lucide-icon text-muted"></i></span>
                    <input type="text" id="roleTableSearch" class="form-control border-start-0" placeholder="Search security roles..." onkeyup="filterRolesTable()">
                </div>
                <span class="text-muted small fw-medium" id="roleCountLabel">Showing <?php echo $totalRoles; ?> security roles</span>
            </div>

            <!-- Table Rows -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="rolesTable" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Role / Security Title</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Description</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Staff Assigned</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Granted Permissions</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Role Type</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roles)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted small">
                                    <i data-lucide="shield-off" class="lucide-icon d-block mb-2 text-muted mx-auto" style="width: 32px; height: 32px;"></i>
                                    No security roles found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($roles as $r): ?>
                                <tr class="role-table-row">
                                    <td class="py-3 px-4">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <div class="rounded-circle avatar-brand text-white fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                <i data-lucide="shield-check" class="lucide-icon" style="width: 18px; height: 18px;"></i>
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block role-name-text"><?php echo htmlspecialchars($r['name']); ?></span>
                                                <span class="text-muted small" style="font-size: 0.725rem;">Role ID: #<?php echo $r['id']; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-muted small" style="max-width: 280px;">
                                        <?php echo htmlspecialchars($r['description'] ?: 'Standard system role permissions'); ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="badge badge-soft-primary rounded-pill px-3 py-1.5 fw-semibold">
                                            <i data-lucide="users" class="lucide-icon me-1" style="width: 14px; height: 14px;"></i>
                                            <?php echo $r['user_count']; ?> Staff
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="badge badge-soft-warning rounded-pill px-3 py-1.5 fw-semibold">
                                            <i data-lucide="key" class="lucide-icon me-1" style="width: 14px; height: 14px;"></i>
                                            <?php echo $r['perm_count']; ?> Keys
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($r['is_system']): ?>
                                            <span class="badge badge-soft-success rounded-pill px-2.5 py-1">System Default</span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-secondary rounded-pill px-2.5 py-1">Custom Role</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-end">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8rem; font-family: 'Outfit', sans-serif;">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 text-small">
                                                <li>
                                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="#" onclick="editRole(<?php echo $r['id']; ?>); return false;">
                                                        <i data-lucide="edit-3" class="lucide-icon text-muted"></i> Edit Permissions
                                                    </a>
                                                </li>
                                                <?php if (!$r['is_system']): ?>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2" href="#" onclick="deleteRole(<?php echo $r['id']; ?>, '<?php echo addslashes($r['name']); ?>'); return false;">
                                                            <i data-lucide="trash-2" class="lucide-icon text-danger"></i> Archive Role
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

<!-- 50% WIDTH OFFCANVAS SLIDER FOR CREATE & EDIT ROLE (Takes half screen) -->
<div class="offcanvas offcanvas-end" id="roleOffcanvas" tabindex="-1" aria-labelledby="roleOffcanvasLabel" data-bs-backdrop="true" data-bs-scroll="false" style="display: none; position: fixed; top: 0; right: 0; height: 100vh; width: 50%; max-width: 720px; z-index: 1080; overflow: auto; background: #fff;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold text-dark" id="roleOffcanvasTitle" style="font-family: 'Outfit', sans-serif;">Create Security Role</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form id="roleForm" onsubmit="saveRole(event)">
        <div class="offcanvas-body p-4">
            <input type="hidden" id="roleId" name="id">

            <!-- Basic Role Information -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Role Name <span class="text-danger">*</span></label>
                    <input type="text" id="roleName" name="name" class="form-control" placeholder="e.g. Regional Sales Director" required>
                </div>

                <!-- Clone Dropdown Container (ONLY visible when creating a NEW role) -->
                <div class="col-md-6" id="cloneRoleContainer">
                    <label class="form-label small fw-semibold">Clone Permissions From</label>
                    <select id="cloneFromRole" name="clone_from_role_id" class="form-select" onchange="handleCloneSelect(this.value)">
                        <option value="">Select Role to Copy Permissions</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?> (<?php echo $r['perm_count']; ?> perms)</option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-muted small" style="font-size: 0.725rem;">Copies all active permissions into matrix.</span>
                </div>

                <div class="col-12" id="descContainer">
                    <label class="form-label small fw-semibold">Role Description</label>
                    <input type="text" id="roleDescription" name="description" class="form-control" placeholder="Brief explanation of staff duties and access scope...">
                </div>
            </div>

            <!-- Permission Matrix Subheader & Bulk Controls -->
            <div class="d-flex justify-content-between align-items-center mb-3 pt-3 border-top">
                <div>
                    <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Permission Matrix by Category</h6>
                    <span class="text-muted small">Toggle access rights for each module category below.</span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" onclick="toggleAllPermissions(true)">Select All</button>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" onclick="toggleAllPermissions(false)">Deselect All</button>
                </div>
            </div>

            <!-- Permission matrix will be rendered into this container when the offcanvas opens -->
            <div id="permissionMatrixContainer" class=""></div>

            <style>
                /* Scope styles to the permission matrix inside this offcanvas */
                #permissionMatrixContainer .accordion-button {
                    padding-left: 1rem;
                    padding-right: 1rem;
                    gap: 0.75rem;
                }

                #permissionMatrixContainer .accordion-item .accordion-body {
                    padding-left: 0.75rem;
                    padding-right: 0.75rem;
                }

                #permissionMatrixContainer .form-check {
                    padding: 0.75rem 0.9rem;
                }

                /* Move the checkbox slightly away from the left edge */
                #permissionMatrixContainer .form-check-input {
                    margin-left: 0.25rem;
                    margin-top: 0.25rem;
                }

                /* Give the All/None buttons a small left margin so they're not flush */
                #permissionMatrixContainer .accordion-button .btn-xs {
                    margin-left: 0.5rem;
                }

                /* Ensure badges have breathing room */
                #permissionMatrixContainer .badge {
                    margin-right: 0.5rem;
                }
            </style>

            <!-- Expose permissions data for client-side rendering (render only when needed) -->
            <script>
                window.PERMISSIONS_BY_CATEGORY = <?php echo json_encode($permissionsByCategory, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
                window.PERMISSIONS_RENDERED = false;

                function renderPermissionMatrix() {
                    if (window.PERMISSIONS_RENDERED) return;
                    const container = document.getElementById('permissionMatrixContainer');
                    if (!container) return;
                    const cats = window.PERMISSIONS_BY_CATEGORY || {};
                    let catIdx = 0;
                    const frag = document.createElement('div');
                    frag.className = 'accordion';

                    for (const [cat, perms] of Object.entries(cats)) {
                        catIdx++;
                        const item = document.createElement('div');
                        item.className = 'accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm';

                        const headingId = `heading_${catIdx}`;
                        const collapseId = `collapse_${catIdx}`;

                        item.innerHTML = `
                            <h2 class="accordion-header" id="${headingId}">
                                <button class="accordion-button py-3 px-3.5 bg-light fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="true" aria-controls="${collapseId}">
                                    <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                        <span><i data-lucide="shield" class="lucide-icon me-2 text-primary"></i> ${escapeHtml(cat)} Permissions</span>
                                        <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation()">
                                            <span class="badge badge-soft-secondary rounded-pill">${perms.length} keys</span>
                                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0.5" style="font-size: 0.7rem;" onclick="toggleCategory('${escapeJs(cat)}', true)">All</button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0.5" style="font-size: 0.7rem;" onclick="toggleCategory('${escapeJs(cat)}', false)">None</button>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="${collapseId}" class="accordion-collapse collapse show" aria-labelledby="${headingId}">
                                <div class="accordion-body p-3">
                                    <div class="row g-2" id="perms_row_${catIdx}"></div>
                                </div>
                            </div>
                        `;

                        frag.appendChild(item);

                        const permsRow = item.querySelector(`#perms_row_${catIdx}`);
                        perms.forEach(p => {
                            const col = document.createElement('div');
                            col.className = 'col-12 col-md-6';
                            const slug = escapeHtml(p.slug || '');
                            col.innerHTML = `
                                <div class="form-check p-2 rounded hover-bg-light border border-light">
                                    <input class="form-check-input perm-checkbox cat-perm-${escapeHtml(cat)}" type="checkbox" name="permission_ids[]" value="${p.id}" id="perm_${p.id}">
                                    <label class="form-check-label d-block cursor-pointer ms-1" for="perm_${p.id}">
                                        <strong class="d-block text-dark small">${escapeHtml(p.name)}</strong>
                                        <span class="text-muted" style="font-size: 0.75rem;">${escapeHtml(p.description || slug)}</span>
                                    </label>
                                </div>
                            `;
                            permsRow.appendChild(col);
                        });
                    }

                    container.appendChild(frag);
                    if (window.lucide && window.lucide.replace) window.lucide.replace();
                    window.PERMISSIONS_RENDERED = true;
                }

                function escapeHtml(str) {
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function escapeJs(str) {
                    return String(str).replace(/'/g, "\\'");
                }
            </script>

        </div>
        <div class="offcanvas-footer border-top p-3 gap-2 d-flex justify-content-end bg-light">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="offcanvas">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">Save Security Role</button>
        </div>
    </form>
</div>

<script>
function filterRolesTable() {
    const query = document.getElementById('roleTableSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#rolesTable tbody tr.role-table-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (text.includes(query)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('roleCountLabel').textContent = `Showing ${visibleCount} security roles`;
}

function openCreateRoleModal() {
    resetRoleForm();
    document.getElementById('roleOffcanvasTitle').textContent = 'Create New Security Role';
    document.getElementById('cloneRoleContainer').style.display = 'block'; // Show clone dropdown ONLY when creating
    
    const descCol = document.getElementById('descContainer');
    descCol.className = 'col-12';

    // Render permission matrix on first open to keep it out of page layout
    if (typeof renderPermissionMatrix === 'function') renderPermissionMatrix();

    const offcanvasEl = document.getElementById('roleOffcanvas');
    offcanvasEl.style.display = 'flex';
    const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
    bsOffcanvas.show();
}

function resetRoleForm() {
    document.getElementById('roleForm').reset();
    document.getElementById('roleId').value = '';
    toggleAllPermissions(false);
}

function toggleAllPermissions(checked) {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = checked);
}

function toggleCategory(cat, checked) {
    document.querySelectorAll('.cat-perm-' + CSS.escape(cat)).forEach(cb => cb.checked = checked);
}

function handleCloneSelect(roleId) {
    if (!roleId) return;
    if (typeof renderPermissionMatrix === 'function') renderPermissionMatrix();
    fetch('<?php echo BASE_URL; ?>/ajax/roles/get?id=' + roleId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data && data.data.permission_ids) {
                toggleAllPermissions(false);
                data.data.permission_ids.forEach(pId => {
                    const cb = document.getElementById('perm_' + pId);
                    if (cb) cb.checked = true;
                });
                if (window.showToast) window.showToast(`Cloned ${data.data.permission_ids.length} permissions into matrix.`, 'info');
            }
        });
}

function editRole(id) {
    resetRoleForm();
    document.getElementById('roleOffcanvasTitle').textContent = 'Edit Security Role Permissions';
    document.getElementById('cloneRoleContainer').style.display = 'none'; // HIDE clone dropdown when editing
    
    const descCol = document.getElementById('descContainer');
    descCol.className = 'col-12';

    fetch('<?php echo BASE_URL; ?>/ajax/roles/get?id=' + id)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const r = data.data;
                document.getElementById('roleId').value = r.id;
                document.getElementById('roleName').value = r.name;
                document.getElementById('roleDescription').value = r.description || '';

                if (r.permission_ids) {
                    r.permission_ids.forEach(pId => {
                        const cb = document.getElementById('perm_' + pId);
                        if (cb) cb.checked = true;
                    });
                }

                // Ensure permission matrix is rendered before showing
                if (typeof renderPermissionMatrix === 'function') renderPermissionMatrix();

                const offcanvasEl = document.getElementById('roleOffcanvas');
                offcanvasEl.style.display = 'flex';
                const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
                bsOffcanvas.show();
            } else {
                if (window.showToast) window.showToast(data.message || 'Failed to fetch role details', 'error');
            }
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const offcanvasEl = document.getElementById('roleOffcanvas');
    if (offcanvasEl) {
        // Ensure the offcanvas is a child of <body> so Bootstrap can position it
        if (offcanvasEl.parentNode !== document.body) {
            document.body.appendChild(offcanvasEl);
        }

        // Keep it hidden in the layout until Bootstrap shows it
        offcanvasEl.style.display = 'none';

        // When Bootstrap opens the offcanvas, ensure it's visible and scrollable
        offcanvasEl.addEventListener('show.bs.offcanvas', function () {
            offcanvasEl.style.display = 'flex';
        });

        // When hidden, remove it from the flow again
        offcanvasEl.addEventListener('hidden.bs.offcanvas', function () {
            offcanvasEl.style.display = 'none';
        });
    }
});

function saveRole(e) {
    e.preventDefault();
    const form = document.getElementById('roleForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Collect array of checked permission IDs
    const checkedPerms = Array.from(document.querySelectorAll('.perm-checkbox:checked')).map(cb => parseInt(cb.value));
    data.permission_ids = checkedPerms;

    fetch('<?php echo BASE_URL; ?>/ajax/roles/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(resData => {
        if (resData.success) {
            if (window.showToast) window.showToast(resData.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            if (window.showToast) window.showToast(resData.message || 'Save failed', 'error');
        }
    })
    .catch(err => {
        if (window.showToast) window.showToast('Network error while saving role', 'error');
    });
}

async function deleteRole(id, name) {
    if (window.appConfirm) {
        const confirmed = await window.appConfirm({
            title: 'Archive Security Role',
            message: `Are you sure you want to archive the role "${name}"? Staff members assigned to this role must be re-assigned first.`,
            confirmText: 'Yes, Archive Role',
            cancelText: 'Cancel',
            isDanger: true
        });
        if (!confirmed) return;
    } else if (!confirm(`Are you sure you want to archive "${name}"?`)) {
        return;
    }

    fetch('<?php echo BASE_URL; ?>/ajax/roles/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (window.showToast) window.showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            if (window.showToast) window.showToast(data.message || 'Failed to archive role', 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
