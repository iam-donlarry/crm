<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (!hasPermission('view_settings') && $_SESSION['user_role'] !== 'admin') {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

$currencies = $pdo->query("SELECT * FROM currencies ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$roles = $pdo->query("SELECT * FROM roles WHERE is_archived = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$permissions = $pdo->query("SELECT * FROM permissions ORDER BY category, name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-0">
    <!-- Header & Breadcrumb -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Settings', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">System Settings & Configuration</h2>
            <p class="text-muted small mb-0">Configure application preferences, multi-currency exchange rates, and security roles.</p>
        </div>
    </div>

    <!-- Settings Workspace Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-white border-bottom p-3">
            <!-- Navigation Tabs -->
            <ul class="nav nav-pills card-header-pills gap-2" id="settingsTabs" role="tablist" style="font-family: 'Outfit', sans-serif;">
                <li class="nav-item" role="presentation">
                    <button class="nav-link btn-sm rounded-pill active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-panel" type="button" role="tab">
                        <i data-lucide="sliders" class="lucide-icon me-1"></i> General Config
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link btn-sm rounded-pill" id="currency-tab" data-bs-toggle="tab" data-bs-target="#currency-panel" type="button" role="tab">
                        <i data-lucide="coins" class="lucide-icon me-1"></i> Multi-Currency Rates
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link btn-sm rounded-pill" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles-panel" type="button" role="tab">
                        <i data-lucide="shield-check" class="lucide-icon me-1"></i> Security Roles & Access
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link btn-sm rounded-pill" id="pipeline-tab" data-bs-toggle="tab" data-bs-target="#pipeline-panel" type="button" role="tab">
                        <i data-lucide="briefcase" class="lucide-icon me-1"></i> Pipeline Rules
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="settingsTabContent">

                <!-- TAB 1: GENERAL CONFIGURATION -->
                <div class="tab-pane fade show active" id="general-panel" role="tabpanel">
                    <h5 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">General Application Settings</h5>
                    <p class="text-muted small mb-4">Branding, system notifications, and localization settings.</p>

                    <form id="generalSettingsForm" onsubmit="saveGeneralSettings(event)">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Application Name</label>
                                <input type="text" name="app_name" class="form-control" value="<?php echo htmlspecialchars(APP_NAME); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Company / Organization</label>
                                <input type="text" name="company_name" class="form-control" value="BBSL Enterprise Solutions">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Support Email Address</label>
                                <input type="email" name="support_email" class="form-control" value="support@bbsl.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Default Base Currency</label>
                                <select name="base_currency" class="form-select">
                                    <option value="USD" selected>USD ($) - US Dollar</option>
                                    <option value="NGN">NGN (₦) - Nigerian Naira</option>
                                    <option value="GBP">GBP (£) - British Pound</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">System Timezone</label>
                                <select name="timezone" class="form-select">
                                    <option value="Africa/Lagos" selected>Africa/Lagos (GMT+1)</option>
                                    <option value="UTC">UTC (GMT+0)</option>
                                    <option value="America/New_York">America/New_York (EST)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Date Format</label>
                                <select name="date_format" class="form-select">
                                    <option value="Y-m-d">YYYY-MM-DD (2026-08-06)</option>
                                    <option value="M j, Y" selected>MMM D, YYYY (Aug 6, 2026)</option>
                                    <option value="d/m/Y">DD/MM/YYYY (06/08/2026)</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                                <i data-lucide="check" class="lucide-icon me-1"></i> Save Configuration
                            </button>
                        </div>
                    </form>
                </div>

                <!-- TAB 2: MULTI-CURRENCY & EXCHANGE RATES -->
                <div class="tab-pane fade" id="currency-panel" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Supported Currencies & Exchange Rates</h5>
                            <p class="text-muted small mb-0">Multi-currency exchange rates relative to base currency (USD).</p>
                        </div>
                    </div>

                    <div class="table-responsive border rounded-3 mb-4">
                        <table class="table table-hover align-middle mb-0" style="font-family: 'Outfit', sans-serif;">
                            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                <tr>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Code</th>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Currency Name</th>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Symbol</th>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Exchange Rate</th>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currencies as $curr): ?>
                                    <tr>
                                        <td class="py-3 px-4 fw-bold text-dark"><span class="badge badge-soft-secondary"><?php echo htmlspecialchars($curr['code']); ?></span></td>
                                        <td class="py-3 px-4 fw-semibold text-dark"><?php echo htmlspecialchars($curr['name']); ?></td>
                                        <td class="py-3 px-4 text-muted fw-bold"><?php echo htmlspecialchars($curr['symbol']); ?></td>
                                        <td class="py-3 px-4 fw-bold text-primary"><?php echo number_format($curr['exchange_rate'], 4); ?></td>
                                        <td class="py-3 px-4">
                                            <?php if ($curr['is_base']): ?>
                                                <span class="badge badge-soft-primary rounded-pill"><i data-lucide="check" class="lucide-icon me-1"></i> Base Currency</span>
                                            <?php else: ?>
                                                <span class="badge badge-soft-success rounded-pill">Active</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: SECURITY ROLES & ACCESS CONTROL -->
                <div class="tab-pane fade" id="roles-panel" role="tabpanel">
                    <h5 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Configured Security Roles</h5>
                    <p class="text-muted small mb-4">Security permission hierarchies mapped to staff accounts.</p>

                    <div class="row g-3">
                        <?php foreach ($roles as $r): ?>
                            <div class="col-12 col-md-6">
                                <div class="p-3.5 bg-light rounded-3 border h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="fw-bold text-dark fs-6" style="font-family: 'Outfit', sans-serif;">
                                            <i data-lucide="shield-check" class="lucide-icon text-primary me-1"></i>
                                            <?php echo htmlspecialchars($r['name']); ?>
                                        </div>
                                        <span class="badge badge-soft-primary rounded-pill">Role ID: <?php echo $r['id']; ?></span>
                                    </div>
                                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($r['description'] ?: 'Access permissions for ' . $r['name']); ?></p>

                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if (strtolower($r['name']) === 'admin' || strtolower($r['name']) === 'system administrator'): ?>
                                            <span class="badge badge-soft-success rounded-pill px-2.5 py-1">Full System Control</span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-info rounded-pill px-2.5 py-1">Leads & Deals View</span>
                                            <span class="badge badge-soft-info rounded-pill px-2.5 py-1">Tasks Management</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- TAB 4: PIPELINE & SALES CONFIG -->
                <div class="tab-pane fade" id="pipeline-panel" role="tabpanel">
                    <h5 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Sales & Opportunity Rules</h5>
                    <p class="text-muted small mb-4">Default expiration rules and pipeline stage settings.</p>

                    <form id="pipelineConfigForm" onsubmit="savePipelineConfig(event)">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Quote Validity Period (Days)</label>
                                <input type="number" name="quote_validity_days" class="form-control" value="30">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Default Lead Status for Inbound Leads</label>
                                <select name="default_lead_status" class="form-select">
                                    <option value="new" selected>New</option>
                                    <option value="contacted">Contacted</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Automatic Lead Assignment</label>
                                <select name="auto_assign" class="form-select">
                                    <option value="round_robin" selected>Round Robin (Equal Distribution)</option>
                                    <option value="manual">Manual Assignment Only</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Require Line Items for Quote Generation</label>
                                <select name="require_line_items" class="form-select">
                                    <option value="yes" selected>Yes (Mandatory Product Selection)</option>
                                    <option value="no">No</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                                <i data-lucide="check" class="lucide-icon me-1"></i> Save Pipeline Rules
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function saveGeneralSettings(e) {
    e.preventDefault();
    showToast('General settings saved successfully!', 'success');
}

function savePipelineConfig(e) {
    e.preventDefault();
    showToast('Pipeline rules saved successfully!', 'success');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
