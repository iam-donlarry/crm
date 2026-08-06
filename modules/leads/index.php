<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (!hasPermission('view_leads')) {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

// Fetch leads list with robust user name selection
$sql = "SELECT l.*, c.display_name as customer_name, 
               COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as assigned_to_name 
        FROM leads l 
        LEFT JOIN customers c ON l.customer_id = c.id 
        LEFT JOIN users u ON l.assigned_to = u.id 
        WHERE l.is_archived = 0 
        ORDER BY l.created_at DESC";
$leads = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Fetch users for lead assignment modal
$userSql = "SELECT id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''), name, email) as name FROM users WHERE is_archived = 0 ORDER BY name ASC";
$users = $pdo->query($userSql)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Leads', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Leads Pipeline</h2>
            <p class="text-muted small mb-0">Track inbound inquiries, lead status qualification, and conversion to customers.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (hasPermission('add_leads')): ?>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#leadModal" onclick="resetLeadForm()">
                    <i data-lucide="plus" class="lucide-icon me-1"></i> Add Lead
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Leads', count($leads), 'funnel', 'primary'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $newCount = count(array_filter($leads, fn($l) => $l['status'] === 'new'));
                renderStatCard('New Prospects', $newCount, 'star', 'warning', 'Awaiting initial contact'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $qualCount = count(array_filter($leads, fn($l) => $l['status'] === 'qualified'));
                renderStatCard('Qualified Leads', $qualCount, 'check2-circle', 'info', 'Ready for proposal'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $convCount = count(array_filter($leads, fn($l) => $l['status'] === 'converted'));
                renderStatCard('Converted Clients', $convCount, 'trophy', 'success', 'Converted'); 
            ?>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="background: #f8fafc;">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" class="lucide-icon text-muted"></i></span>
                    <input type="text" id="searchFilter" class="form-control border-start-0" placeholder="Search leads..." onkeyup="filterLeads()">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="leadsTable" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Lead Code</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Lead Name</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Company</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Status</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Expected Value</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Assigned To</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leads)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted small">
                                    <i data-lucide="inbox" class="lucide-icon d-block mb-2 text-muted mx-auto"></i>
                                    No leads found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leads as $l): ?>
                                <?php
                                    $statusBadge = [
                                        'new' => 'badge-soft-warning',
                                        'contacted' => 'badge-soft-info',
                                        'qualified' => 'badge-soft-primary',
                                        'converted' => 'badge-soft-success',
                                        'lost' => 'badge-soft-danger'
                                    ][$l['status']] ?? 'badge-soft-secondary';
                                ?>
                                <tr>
                                    <td class="py-3 px-4"><span class="badge badge-soft-secondary"><?php echo htmlspecialchars($l['lead_code'] ?? 'N/A'); ?></span></td>
                                    <td class="py-3 px-4 fw-semibold text-dark"><?php echo htmlspecialchars($l['display_name']); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($l['company'] ?: '-'); ?></td>
                                    <td class="py-3 px-4"><span class="badge rounded-pill <?php echo $statusBadge; ?>"><?php echo ucfirst($l['status']); ?></span></td>
                                    <td class="py-3 px-4 fw-semibold text-dark"><?php echo htmlspecialchars($l['currency']) . ' ' . number_format($l['expected_value'], 2); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($l['assigned_to_name'] ?: 'Unassigned'); ?></td>
                                    <td class="py-3 px-4 text-end">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8rem; font-family: 'Outfit', sans-serif;">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 text-small">
                                                <?php if ($l['status'] !== 'converted' && hasPermission('convert_leads')): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-success d-flex align-items-center gap-2" href="#" onclick="convertLead(<?php echo $l['id']; ?>); return false;">
                                                            <i data-lucide="refresh-cw" class="lucide-icon text-success"></i> Convert to Customer
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (hasPermission('edit_leads')): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="#" onclick="editLead(<?php echo $l['id']; ?>); return false;">
                                                            <i data-lucide="edit-3" class="lucide-icon text-muted"></i> Edit Lead
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

<!-- Lead Modal Form -->
<div class="modal fade" id="leadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark" id="leadModalTitle" style="font-family: 'Outfit', sans-serif;">Add New Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="leadForm" onsubmit="saveLead(event)">
                <div class="modal-body p-4">
                    <input type="hidden" id="leadId" name="id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">First Name</label>
                            <input type="text" id="leadFirstName" name="first_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Last Name</label>
                            <input type="text" id="leadLastName" name="last_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Company Name</label>
                            <input type="text" id="leadCompany" name="company" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" id="leadEmail" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="text" id="leadPhone" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Lead Status</label>
                            <select id="leadStatus" name="status" class="form-select">
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="qualified">Qualified</option>
                                <option value="converted">Converted</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Expected Value</label>
                            <input type="number" step="0.01" id="leadValue" name="expected_value" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Assigned To</label>
                            <select id="leadAssignedTo" name="assigned_to" class="form-select">
                                <option value="">Select Representative</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Notes & Context</label>
                            <textarea id="leadNotes" name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4 gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Save Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetLeadForm() {
    document.getElementById('leadForm').reset();
    document.getElementById('leadId').value = '';
    document.getElementById('leadModalTitle').textContent = 'Add New Lead';
}

function saveLead(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('leadForm')).entries());
    fetch('<?php echo BASE_URL; ?>/ajax/leads/save', {
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
            showToast(res.message, 'danger');
        }
    });
}

function editLead(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/leads/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const l = res.data;
            document.getElementById('leadId').value = l.id;
            document.getElementById('leadFirstName').value = l.first_name || '';
            document.getElementById('leadLastName').value = l.last_name || '';
            document.getElementById('leadCompany').value = l.company || '';
            document.getElementById('leadEmail').value = l.email || '';
            document.getElementById('leadPhone').value = l.phone || '';
            document.getElementById('leadStatus').value = l.status || 'new';
            document.getElementById('leadValue').value = l.expected_value || 0;
            document.getElementById('leadAssignedTo').value = l.assigned_to || '';
            document.getElementById('leadNotes').value = l.notes || '';
            document.getElementById('leadModalTitle').textContent = 'Edit Lead';
            new bootstrap.Modal(document.getElementById('leadModal')).show();
        }
    });
}

function convertLead(id) {
    if (!confirm('Convert this lead to a Customer account and Deal opportunity?')) return;
    fetch('<?php echo BASE_URL; ?>/ajax/leads/convert', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lead_id: id })
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

function filterLeads() {
    const val = document.getElementById('searchFilter').value.toLowerCase();
    document.querySelectorAll('#leadsTable tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
