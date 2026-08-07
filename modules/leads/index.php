<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (!hasPermission('view_leads')) {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

// Fetch initial lead data with data ownership scoping
$leadScope = getRecordScopeCondition('l.assigned_to', 'view_team_leads');
$stmt = $pdo->query("
    SELECT l.*, 
           COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as assigned_to_name 
    FROM leads l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    WHERE l.is_archived = 0 AND {$leadScope}
    ORDER BY l.created_at DESC
");
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users for assignment dropdown
$userStmt = $pdo->query("SELECT id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''), name, email) as name FROM users WHERE is_archived = 0 ORDER BY name ASC");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-0">
    <!-- Header & Breadcrumbs -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Leads', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Inbound Leads Directory</h2>
            <p class="text-muted small mb-0">Capture, track, and convert sales prospects into active pipeline opportunities.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (hasPermission('download_leads_template')): ?>
                <a href="<?php echo BASE_URL; ?>/assets/templates/leads_template.csv" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm" download>
                    <i data-lucide="download" class="lucide-icon me-1"></i> Template
                </a>
            <?php endif; ?>
            <?php if (hasPermission('export_leads')): ?>
                <button type="button" class="btn btn-outline-primary rounded-pill px-3 shadow-sm" onclick="exportTableToCSV('leadsTable', 'leads_export.csv')">
                    <i data-lucide="download-cloud" class="lucide-icon me-1"></i> Export CSV
                </button>
            <?php endif; ?>
            <?php if (hasPermission('add_leads')): ?>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#leadOffcanvas" onclick="resetLeadForm()">
                    <i data-lucide="plus" class="lucide-icon me-1"></i> Add Lead
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Prospects', count($leads), 'funnel', 'primary', 'Active database'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $newCount = count(array_filter($leads, fn($l) => $l['status'] === 'new'));
                renderStatCard('New Leads', $newCount, 'clock-history', 'warning', 'Awaiting first contact'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $qualifiedCount = count(array_filter($leads, fn($l) => $l['status'] === 'qualified'));
                renderStatCard('Qualified Leads', $qualifiedCount, 'check-circle', 'info', 'High intent prospects'); 
            ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $convertedCount = count(array_filter($leads, fn($l) => $l['status'] === 'converted'));
                renderStatCard('Converted Deals', $convertedCount, 'trophy', 'success', 'Successfully closed'); 
            ?>
        </div>
    </div>

    <!-- Table Container -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="p-3 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2" style="background: #f8fafc;">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" class="lucide-icon text-muted"></i></span>
                    <input type="text" id="searchFilter" class="form-control border-start-0" placeholder="Search leads by name or company..." onkeyup="filterLeads()">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="leadsTable" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Code</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Lead Name</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Company</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Status</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Est. Value</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Source</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Assigned To</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leads)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted small">
                                    <i data-lucide="inbox" class="lucide-icon d-block mb-2 text-muted mx-auto"></i>
                                    No leads found in database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leads as $l): ?>
                                <?php
                                    $badge = [
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
                                    <td class="py-3 px-4"><span class="badge rounded-pill <?php echo $badge; ?>"><?php echo ucfirst($l['status']); ?></span></td>
                                    <td class="py-3 px-4 fw-bold text-primary"><?php echo htmlspecialchars($l['currency']) . ' ' . number_format($l['expected_value'], 2); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($l['source'] ?: 'Direct'); ?></td>
                                    <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($l['assigned_to_name'] ?: 'Unassigned'); ?></td>
                                    <td class="py-3 px-4 text-end">
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8rem; font-family: 'Outfit', sans-serif;">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 text-small">
                                                <?php if (hasPermission('edit_leads')): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="#" onclick="editLead(<?php echo $l['id']; ?>); return false;">
                                                            <i data-lucide="edit-3" class="lucide-icon text-muted"></i> Edit Prospect Details
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (hasPermission('convert_leads') && $l['status'] !== 'converted'): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-primary d-flex align-items-center gap-2" href="#" onclick="convertLead(<?php echo $l['id']; ?>); return false;">
                                                            <i data-lucide="arrow-right-left" class="lucide-icon text-primary"></i> Convert to Opportunity
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (hasPermission('archive_leads') || hasPermission('delete_leads')): ?>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2" href="#" onclick="archiveLead(<?php echo $l['id']; ?>); return false;">
                                                            <i data-lucide="trash-2" class="lucide-icon text-danger"></i> Archive Lead
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
            <div class="card-footer bg-white border-top p-3" id="leadsPagination"></div>
        </div>
    </div>
</div>

<!-- 50% WIDTH OFFCANVAS SLIDER FOR NEW / EDIT LEAD -->
<div class="offcanvas offcanvas-end offcanvas-half" id="leadOffcanvas" tabindex="-1" aria-labelledby="leadOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold text-dark" id="leadOffcanvasTitle" style="font-family: 'Outfit', sans-serif;">Add New Inbound Lead</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form id="leadForm" onsubmit="saveLead(event)">
        <div class="offcanvas-body p-4">
            <input type="hidden" id="leadId" name="id">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">First Name</label>
                    <input type="text" id="leadFirstName" name="first_name" class="form-control" placeholder="Jane">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Last Name</label>
                    <input type="text" id="leadLastName" name="last_name" class="form-control" placeholder="Smith">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Company Name</label>
                    <input type="text" id="leadCompany" name="company" class="form-control" placeholder="Acme Global">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Email Address</label>
                    <input type="email" id="leadEmail" name="email" class="form-control" placeholder="jane@example.com">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Phone Number</label>
                    <input type="text" id="leadPhone" name="phone" class="form-control" placeholder="+234 ...">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Lead Source</label>
                    <select id="leadSource" name="source" class="form-select">
                        <option value="Website">Website</option>
                        <option value="Referral">Referral</option>
                        <option value="Social Media">Social Media</option>
                        <option value="Cold Call">Cold Call</option>
                        <option value="Event / Expo">Event / Expo</option>
                        <option value="Direct / Other">Direct / Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Estimated Value</label>
                    <input type="number" step="0.01" id="leadExpectedValue" name="expected_value" class="form-control" placeholder="2500.00">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Currency</label>
                    <select id="leadCurrency" name="currency" class="form-select">
                        <option value="USD" selected>USD ($)</option>
                        <option value="NGN">NGN (₦)</option>
                        <option value="GBP">GBP (£)</option>
                    </select>
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
                    <label class="form-label small fw-semibold">Assigned Sales Rep</label>
                    <select id="leadAssignedTo" name="assigned_to" class="form-select">
                        <option value="">Select Staff</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Notes / Requirements</label>
                    <textarea id="leadNotes" name="notes" class="form-control" rows="3" placeholder="Additional prospect context..."></textarea>
                </div>
            </div>
        </div>
        <div class="offcanvas-footer gap-2 d-flex justify-content-end">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="offcanvas">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4">Save Prospect</button>
        </div>
    </form>
</div>

<script>
function resetLeadForm() {
    document.getElementById('leadForm').reset();
    document.getElementById('leadId').value = '';
    document.getElementById('leadOffcanvasTitle').textContent = 'Add New Inbound Lead';
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
            showToast(res.message || 'Save failed', 'danger');
        }
    })
    .catch(err => showToast('Error processing request', 'danger'));
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
            document.getElementById('leadSource').value = l.source || 'Website';
            document.getElementById('leadExpectedValue').value = l.expected_value || 0;
            document.getElementById('leadCurrency').value = l.currency || 'USD';
            document.getElementById('leadStatus').value = l.status || 'new';
            document.getElementById('leadAssignedTo').value = l.assigned_to || '';
            document.getElementById('leadNotes').value = l.notes || '';
            document.getElementById('leadOffcanvasTitle').textContent = 'Edit Inbound Lead';
            new bootstrap.Offcanvas(document.getElementById('leadOffcanvas')).show();
        }
    });
}

async function convertLead(id) {
    const confirmed = await window.appConfirm({
        title: 'Convert Lead to Opportunity',
        message: 'Are you sure you want to convert this lead into an active Pipeline Opportunity?',
        confirmText: 'Yes, Convert Lead',
        cancelText: 'Cancel',
        isDanger: false
    });
    if (!confirmed) return;

    fetch('<?php echo BASE_URL; ?>/ajax/leads/convert', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lead_id: id })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast(res.message, 'success');
            setTimeout(() => window.location.href = '<?php echo BASE_URL; ?>/deals', 600);
        } else {
            showToast(res.message, 'danger');
        }
    });
}

async function archiveLead(id) {
    const confirmed = await window.appConfirm({
        title: 'Archive Lead',
        message: 'Are you sure you want to archive this lead? This action can be reversed by an administrator.',
        confirmText: 'Yes, Archive Lead',
        cancelText: 'Cancel',
        isDanger: true
    });
    if (!confirmed) return;

    fetch('<?php echo BASE_URL; ?>/ajax/leads/delete', {
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

document.addEventListener('DOMContentLoaded', function() {
    initTablePagination('leadsTable', 'leadsPagination', 'searchFilter', 25);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
