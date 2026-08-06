<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (!hasPermission('view_customers')) {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

// Fetch initial customer data with robust user selection
$stmt = $pdo->query("SELECT c.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as assigned_to_name FROM customers c LEFT JOIN users u ON c.assigned_to = u.id WHERE c.is_archived = 0 ORDER BY c.created_at DESC");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users list for modal assignment
$userStmt = $pdo->query("SELECT id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''), name, email) as name FROM users WHERE is_archived = 0 ORDER BY name ASC");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-0">
    <!-- Breadcrumb & Page Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Customers', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Customers Directory</h2>
            <p class="text-muted small mb-0">Manage customer accounts, contact details, and organization assignments.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (hasPermission('add_customers')): ?>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetCustomerForm()">
                    <i data-lucide="plus" class="lucide-icon me-1"></i> Add Customer
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Customers', count($customers), 'people', 'primary', 'Active database'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $corporateCount = count(array_filter($customers, fn($c) => !empty($c['company'])));
                renderStatCard('Corporate Clients', $corporateCount, 'building', 'info', 'Company linked'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $individualCount = count($customers) - $corporateCount;
                renderStatCard('Individual Clients', $individualCount, 'person', 'success', 'Direct contacts'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Active Status', '100%', 'check-circle', 'warning', 'Archived excluded'); ?>
        </div>
    </div>

    <!-- Table Container -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="p-3 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2" style="background: #f8fafc;">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" class="lucide-icon text-muted"></i></span>
                    <input type="text" id="searchFilter" class="form-control border-start-0" placeholder="Search customers..." onkeyup="filterCustomers()">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="customersTable" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Code</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Customer Name</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Company</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Email</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Phone</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Assigned To</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted small">
                                    <i data-lucide="inbox" class="lucide-icon d-block mb-2 text-muted mx-auto"></i>
                                    No customers found in database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td class="py-3 px-4"><span class="badge badge-soft-secondary"><?php echo htmlspecialchars($c['customer_code'] ?? 'N/A'); ?></span></td>
                                    <td class="py-3 px-4 fw-semibold text-dark"><?php echo htmlspecialchars($c['display_name']); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($c['company'] ?: '-'); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($c['email'] ?: '-'); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($c['phone'] ?: '-'); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($c['assigned_to_name'] ?: 'Unassigned'); ?></td>
                                    <td class="py-3 px-4 text-end">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8rem; font-family: 'Outfit', sans-serif;">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 text-small">
                                                <?php if (hasPermission('edit_customers')): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="#" onclick="editCustomer(<?php echo $c['id']; ?>); return false;">
                                                            <i data-lucide="edit-3" class="lucide-icon text-muted"></i> Edit Customer
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (hasPermission('delete_customers')): ?>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2" href="#" onclick="archiveCustomer(<?php echo $c['id']; ?>); return false;">
                                                            <i data-lucide="trash-2" class="lucide-icon text-danger"></i> Archive Customer
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

<!-- Customer Modal Form -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark" id="customerModalTitle" style="font-family: 'Outfit', sans-serif;">Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="customerForm" onsubmit="saveCustomer(event)">
                <div class="modal-body p-4">
                    <input type="hidden" id="customerId" name="id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">First Name</label>
                            <input type="text" id="custFirstName" name="first_name" class="form-control" placeholder="John">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Last Name</label>
                            <input type="text" id="custLastName" name="last_name" class="form-control" placeholder="Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Company Name</label>
                            <input type="text" id="custCompany" name="company" class="form-control" placeholder="Acme Inc">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" id="custEmail" name="email" class="form-control" placeholder="john@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="text" id="custPhone" name="phone" class="form-control" placeholder="+234 ...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Assigned Account Manager</label>
                            <select id="custAssignedTo" name="assigned_to" class="form-select">
                                <option value="">Select Staff</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Street Address</label>
                            <textarea id="custAddress" name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">City</label>
                            <input type="text" id="custCity" name="city" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">State</label>
                            <input type="text" id="custState" name="state" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Country</label>
                            <input type="text" id="custCountry" name="country" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4 gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetCustomerForm() {
    document.getElementById('customerForm').reset();
    document.getElementById('customerId').value = '';
    document.getElementById('customerModalTitle').textContent = 'Add New Customer';
}

function saveCustomer(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('customerForm'));
    const data = Object.fromEntries(formData.entries());

    fetch('<?php echo BASE_URL; ?>/ajax/customers/save', {
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

function editCustomer(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/customers/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const c = res.data;
            document.getElementById('customerId').value = c.id;
            document.getElementById('custFirstName').value = c.first_name || '';
            document.getElementById('custLastName').value = c.last_name || '';
            document.getElementById('custCompany').value = c.company || '';
            document.getElementById('custEmail').value = c.email || '';
            document.getElementById('custPhone').value = c.phone || '';
            document.getElementById('custAddress').value = c.address || '';
            document.getElementById('custCity').value = c.city || '';
            document.getElementById('custState').value = c.state || '';
            document.getElementById('custCountry').value = c.country || '';
            document.getElementById('custAssignedTo').value = c.assigned_to || '';
            document.getElementById('customerModalTitle').textContent = 'Edit Customer';
            new bootstrap.Modal(document.getElementById('customerModal')).show();
        }
    });
}

function archiveCustomer(id) {
    if (!confirm('Are you sure you want to archive this customer?')) return;
    fetch('<?php echo BASE_URL; ?>/ajax/customers/delete', {
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

function filterCustomers() {
    const val = document.getElementById('searchFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#customersTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
