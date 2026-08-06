<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (!hasPermission('view_deals')) {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

// Fetch deals with linked customer, lead, and user details
$sql = "SELECT d.*, 
               c.display_name as customer_display_name, c.company as customer_company,
               l.display_name as lead_display_name,
               COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as assigned_to_name
        FROM deals d 
        LEFT JOIN customers c ON d.customer_id = c.id 
        LEFT JOIN leads l ON d.lead_id = l.id 
        LEFT JOIN users u ON d.assigned_to = u.id 
        WHERE d.is_archived = 0 
        ORDER BY d.created_at DESC";
$deals = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Stage configurations with colors and soft badge styles
$stages = [
    'qualification' => ['title' => 'Qualification', 'color' => '#3b82f6', 'badge' => 'badge-soft-info', 'icon' => 'check-circle'],
    'value_proposition' => ['title' => 'Value Proposition', 'color' => '#8b5cf6', 'badge' => 'badge-soft-primary', 'icon' => 'file-text'],
    'proposal_quote' => ['title' => 'Proposal / Quote', 'color' => '#f59e0b', 'badge' => 'badge-soft-warning', 'icon' => 'file-spreadsheet'],
    'negotiation_review' => ['title' => 'Negotiation', 'color' => '#06b6d4', 'badge' => 'badge-soft-info', 'icon' => 'sliders'],
    'closed_won' => ['title' => 'Closed Won', 'color' => '#10b981', 'badge' => 'badge-soft-success', 'icon' => 'trophy'],
    'closed_lost' => ['title' => 'Closed Lost', 'color' => '#ef4444', 'badge' => 'badge-soft-danger', 'icon' => 'x-circle']
];

// Metrics Calculations
$openDeals = array_filter($deals, fn($d) => !in_array($d['stage'], ['closed_won', 'closed_lost']));
$totalPipelineValue = array_sum(array_column($openDeals, 'value'));
$wonDeals = array_filter($deals, fn($d) => $d['stage'] === 'closed_won');
$totalWonValue = array_sum(array_column($wonDeals, 'value'));
$winRate = (count($deals) > 0) ? round((count($wonDeals) / count($deals)) * 100, 1) : 0;
?>

<div class="container-fluid px-0">
    <!-- Header & Breadcrumb -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Deals', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Deals & Sales Pipeline</h2>
            <p class="text-muted small mb-0">Interactive stage stepper, multi-currency values, and close rates.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- View Mode Switcher -->
            <div class="btn-group p-1 bg-white border rounded-pill shadow-sm" role="group">
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 active" id="gridModeBtn" onclick="switchViewMode('grid')">
                    <i data-lucide="layout-grid" class="lucide-icon me-1"></i> Grid
                </button>
                <button type="button" class="btn btn-sm btn-light rounded-pill px-3 text-secondary" id="tableModeBtn" onclick="switchViewMode('table')">
                    <i data-lucide="list" class="lucide-icon me-1"></i> List
                </button>
            </div>
            
            <?php if (hasPermission('create_deals')): ?>
                <a href="<?php echo BASE_URL; ?>/deals/create" class="btn btn-primary rounded-pill px-4 shadow-sm ms-2">
                    <i data-lucide="plus" class="lucide-icon me-1"></i> New Deal
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Executive KPI Strip -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Open Pipeline Value', 'USD ' . number_format($totalPipelineValue, 2), 'trending-up', 'primary', count($openDeals) . ' active deals'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Closed Won Revenue', 'USD ' . number_format($totalWonValue, 2), 'trophy', 'success', count($wonDeals) . ' won opportunities'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Opportunities', count($deals), 'briefcase', 'info', 'All time deals'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Win Conversion Rate', $winRate . '%', 'pie-chart', 'warning', 'Won vs total deals'); ?>
        </div>
    </div>

    <!-- CONCEPT 1: HORIZONTAL STAGE STEPPER BAR -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-uppercase text-muted fw-bold small" style="letter-spacing: 0.05em; font-family: 'Outfit', sans-serif;">
                    <i data-lucide="git-commit" class="lucide-icon me-1 text-primary"></i> Pipeline Stage Stepper
                </span>
                <span class="text-muted small" id="activeStageLabel">Showing: <strong>All Stages</strong></span>
            </div>

            <!-- Stepper Tabs Row -->
            <div class="d-flex align-items-center gap-2 overflow-auto pb-1 flex-nowrap" id="stepperTabsRow" style="scrollbar-width: thin;">
                <!-- All Stages Tab -->
                <button type="button" class="btn btn-sm btn-primary rounded-3 py-2.5 px-3 d-flex flex-column align-items-start stage-tab active" data-stage="all" onclick="selectStageTab('all')">
                    <div class="d-flex align-items-center justify-content-between w-100 gap-2 mb-1">
                        <span class="fw-bold text-truncate" style="font-size: 0.85rem;">All Stages</span>
                        <span class="badge bg-white text-dark rounded-pill"><?php echo count($deals); ?></span>
                    </div>
                    <div class="small opacity-85" style="font-size: 0.725rem;">
                        USD <?php echo number_format($totalPipelineValue + $totalWonValue, 2); ?>
                    </div>
                </button>

                <!-- Individual Stage Tabs -->
                <?php foreach ($stages as $stageKey => $stageInfo): ?>
                    <?php 
                        $stageDeals = array_filter($deals, fn($d) => $d['stage'] === $stageKey);
                        $stageSum = array_sum(array_column($stageDeals, 'value'));
                    ?>
                    <button type="button" class="btn btn-sm btn-light border rounded-3 py-2.5 px-3 d-flex flex-column align-items-start stage-tab" data-stage="<?php echo $stageKey; ?>" onclick="selectStageTab('<?php echo $stageKey; ?>')">
                        <div class="d-flex align-items-center justify-content-between w-100 gap-2 mb-1">
                            <span class="fw-bold text-truncate text-dark" style="font-size: 0.85rem;">
                                <span class="d-inline-block rounded-circle me-1" style="width: 8px; height: 8px; background-color: <?php echo $stageInfo['color']; ?>;"></span>
                                <?php echo htmlspecialchars($stageInfo['title']); ?>
                            </span>
                            <span class="badge rounded-pill <?php echo $stageInfo['badge']; ?>"><?php echo count($stageDeals); ?></span>
                        </div>
                        <div class="text-muted small" style="font-size: 0.725rem;">
                            USD <?php echo number_format($stageSum, 2); ?>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i data-lucide="search" class="lucide-icon text-muted"></i></span>
                        <input type="text" id="dealSearchInput" class="form-control border-start-0 bg-light" placeholder="Search by deal title, customer, or quote number..." onkeyup="filterDealsData()">
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-3 ms-auto text-md-end">
                    <span class="text-muted small" id="dealCountResult">Showing <?php echo count($deals); ?> opportunities</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 1. FOCUSED DEAL CARD GRID VIEW -->
    <div id="gridContainer" class="row g-3 mb-4">
        <?php if (empty($deals)): ?>
            <div class="col-12 text-center py-5">
                <?php renderEmptyState('No Deals Found', 'There are no active deals in the system. Click "New Deal" to add an opportunity.', 'briefcase', BASE_URL . '/deals/create', 'Create Deal'); ?>
            </div>
        <?php else: ?>
            <?php foreach ($deals as $d): ?>
                <?php 
                    $custName = $d['customer_display_name'] ?: $d['customer_company'] ?: $d['lead_display_name'] ?: 'General Prospect';
                    $ownerName = $d['assigned_to_name'] ?: 'Unassigned';
                    $stageInfo = $stages[$d['stage']] ?? ['title' => ucfirst($d['stage']), 'badge' => 'badge-soft-secondary', 'color' => '#64748b'];
                    $dueDateText = $d['due_date'] ? date('M j, Y', strtotime($d['due_date'])) : null;
                    $isOverdue = ($d['due_date'] && strtotime($d['due_date']) < time() && !in_array($d['stage'], ['closed_won', 'closed_lost']));
                ?>
                <div class="col-12 col-md-6 col-xl-4 deal-card-item" data-stage="<?php echo $d['stage']; ?>" data-title="<?php echo htmlspecialchars(strtolower($d['title'] . ' ' . $custName . ' ' . ($d['quote_number'] ?? ''))); ?>">
                    <div class="card border shadow-sm rounded-3 h-100 bg-white hover-shadow transition-all" style="border-left: 4px solid <?php echo $stageInfo['color']; ?> !important;">
                        <div class="card-body p-3.5 d-flex flex-column">
                            
                            <!-- Stage Badge & Quote Number -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge rounded-pill <?php echo $stageInfo['badge']; ?>">
                                    <?php echo htmlspecialchars($stageInfo['title']); ?>
                                </span>
                                <?php if ($d['quote_number']): ?>
                                    <span class="badge badge-soft-secondary small"><?php echo htmlspecialchars($d['quote_number']); ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Deal Title -->
                            <h5 class="fw-bold text-dark mb-1 lh-sm" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;">
                                <a href="#" class="text-decoration-none text-dark hover-primary" onclick="viewDealModal(<?php echo $d['id']; ?>); return false;">
                                    <?php echo htmlspecialchars($d['title']); ?>
                                </a>
                            </h5>

                            <!-- Customer Name -->
                            <div class="text-muted small mb-3">
                                <i data-lucide="building" class="lucide-icon me-1 text-muted"></i>
                                <?php echo htmlspecialchars($custName); ?>
                            </div>

                            <!-- Value & Target Date Block -->
                            <div class="p-2.5 rounded-3 bg-light mb-3 mt-auto border border-dashed">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="text-muted d-block" style="font-size: 0.725rem;">Deal Value</span>
                                        <span class="fw-bold text-primary" style="font-size: 1.1rem; font-family: 'Outfit', sans-serif;">
                                            <?php echo htmlspecialchars($d['currency']) . ' ' . number_format($d['value'], 2); ?>
                                        </span>
                                    </div>
                                    <?php if ($dueDateText): ?>
                                        <div class="text-end">
                                            <span class="text-muted d-block" style="font-size: 0.725rem;">Target Close</span>
                                            <span class="small fw-semibold <?php echo $isOverdue ? 'text-danger' : 'text-dark'; ?>">
                                                <i data-lucide="calendar" class="lucide-icon me-0.5"></i> <?php echo $dueDateText; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Card Footer: Owner Avatar & Quick Actions -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle avatar-brand d-flex align-items-center justify-content-center shadow-sm" title="Owner: <?php echo htmlspecialchars($ownerName); ?>" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                        <?php echo strtoupper(substr($ownerName, 0, 1)); ?>
                                    </div>
                                    <span class="text-muted small" style="font-size: 0.8rem;"><?php echo htmlspecialchars($ownerName); ?></span>
                                </div>

                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-light border-0 rounded-circle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i data-lucide="more-vertical" class="lucide-icon text-muted"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 text-small">
                                        <li><a class="dropdown-item py-2" href="#" onclick="viewDealModal(<?php echo $d['id']; ?>); return false;"><i data-lucide="eye" class="lucide-icon me-2"></i> View Details</a></li>
                                        <?php if (hasPermission('edit_deals')): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li class="dropdown-header small text-muted">Move Stage</li>
                                            <?php foreach ($stages as $sk => $si): ?>
                                                <?php if ($sk !== $d['stage']): ?>
                                                    <li><a class="dropdown-item py-1.5 small" href="#" onclick="updateDealStage(<?php echo $d['id']; ?>, '<?php echo $sk; ?>'); return false;"><?php echo htmlspecialchars($si['title']); ?></a></li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 2. TABLE LIST VIEW (Hidden by Default) -->
    <div id="tableContainer" class="card border-0 shadow-sm rounded-3 d-none mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="dealsTable" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Quote / Deal Title</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Customer / Prospect</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Value</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Stage</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Target Date</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Owner</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deals as $d): ?>
                            <?php 
                                $custName = $d['customer_display_name'] ?: $d['customer_company'] ?: $d['lead_display_name'] ?: 'Prospect';
                                $badgeClass = $stages[$d['stage']]['badge'] ?? 'badge-soft-secondary';
                                $stageTitle = $stages[$d['stage']]['title'] ?? ucfirst($d['stage']);
                            ?>
                            <tr class="deal-table-row" data-stage="<?php echo $d['stage']; ?>" data-title="<?php echo htmlspecialchars(strtolower($d['title'] . ' ' . $custName)); ?>">
                                <td class="py-3 px-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($d['title']); ?></div>
                                    <?php if ($d['quote_number']): ?>
                                        <span class="badge badge-soft-secondary small mt-0.5"><?php echo htmlspecialchars($d['quote_number']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($custName); ?></td>
                                <td class="py-3 px-4 fw-bold text-primary"><?php echo htmlspecialchars($d['currency']) . ' ' . number_format($d['value'], 2); ?></td>
                                <td class="py-3 px-4"><span class="badge rounded-pill <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($stageTitle); ?></span></td>
                                <td class="py-3 px-4 text-muted small"><?php echo $d['due_date'] ? date('M j, Y', strtotime($d['due_date'])) : '-'; ?></td>
                                <td class="py-3 px-4 text-muted small"><?php echo htmlspecialchars($d['assigned_to_name'] ?: 'Unassigned'); ?></td>
                                <td class="py-3 px-4 text-end">
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8rem; font-family: 'Outfit', sans-serif;">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 text-small">
                                            <li><a class="dropdown-item py-2 d-flex align-items-center gap-2" href="#" onclick="viewDealModal(<?php echo $d['id']; ?>); return false;"><i data-lucide="eye" class="lucide-icon text-muted"></i> View Details</a></li>
                                            <?php if (hasPermission('edit_deals')): ?>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li class="dropdown-header small text-muted">Move Stage</li>
                                                <?php foreach ($stages as $sk => $si): ?>
                                                    <?php if ($sk !== $d['stage']): ?>
                                                        <li><a class="dropdown-item py-1.5 small" href="#" onclick="updateDealStage(<?php echo $d['id']; ?>, '<?php echo $sk; ?>'); return false;"><?php echo htmlspecialchars($si['title']); ?></a></li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Quick Deal Detail Modal -->
<div class="modal fade" id="dealDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div>
                    <span class="badge badge-soft-primary mb-1" id="modalDealQuoteNum">QT-XXXX</span>
                    <h4 class="modal-title fw-bold text-dark" id="modalDealTitle" style="font-family: 'Outfit', sans-serif;">Deal Title</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3">
                            <span class="text-muted small d-block">Deal Value</span>
                            <h5 class="fw-bold text-primary mb-0" id="modalDealValue">$0.00</h5>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3">
                            <span class="text-muted small d-block">Current Stage</span>
                            <h6 class="fw-bold text-dark mb-0" id="modalDealStage">Qualification</h6>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3">
                            <span class="text-muted small d-block">Target Close Date</span>
                            <h6 class="fw-bold text-dark mb-0" id="modalDealDueDate">-</h6>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-2" style="font-family: 'Outfit', sans-serif;">Itemized Products & Line Items</h6>
                <div class="table-responsive rounded-3 border mb-3">
                    <table class="table table-sm align-middle mb-0" id="modalProductsTable">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th class="py-2 px-3 small text-muted">Product / Description</th>
                                <th class="py-2 px-3 small text-muted">Qty</th>
                                <th class="py-2 px-3 small text-muted">Price</th>
                                <th class="py-2 px-3 small text-muted text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody id="modalProductsBody">
                            <tr><td colspan="4" class="text-center py-3 text-muted small">No line items</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="mb-3">
                    <h6 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Notes & Context</h6>
                    <p class="text-muted small mb-0" id="modalDealDesc">No notes provided.</p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4 gap-2">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentSelectedStage = 'all';

function selectStageTab(stage) {
    currentSelectedStage = stage;
    
    // Update Stepper Buttons Active Styling
    document.querySelectorAll('.stage-tab').forEach(btn => {
        if (btn.getAttribute('data-stage') === stage) {
            btn.classList.add('btn-primary', 'active');
            btn.classList.remove('btn-light', 'border');
            const titleSpan = btn.querySelector('.fw-bold');
            if (titleSpan) titleSpan.classList.remove('text-dark');
        } else {
            btn.classList.remove('btn-primary', 'active');
            btn.classList.add('btn-light', 'border');
            const titleSpan = btn.querySelector('.fw-bold');
            if (titleSpan) titleSpan.classList.add('text-dark');
        }
    });

    const labelMap = {
        'all': 'All Stages',
        'qualification': 'Qualification',
        'value_proposition': 'Value Proposition',
        'proposal_quote': 'Proposal / Quote',
        'negotiation_review': 'Negotiation / Review',
        'closed_won': 'Closed Won',
        'closed_lost': 'Closed Lost'
    };
    
    const activeLabel = document.getElementById('activeStageLabel');
    if (activeLabel) {
        activeLabel.innerHTML = 'Showing: <strong>' + (labelMap[stage] || stage) + '</strong>';
    }

    filterDealsData();
}

function switchViewMode(mode) {
    const grid = document.getElementById('gridContainer');
    const table = document.getElementById('tableContainer');
    const gBtn = document.getElementById('gridModeBtn');
    const tBtn = document.getElementById('tableModeBtn');

    if (mode === 'grid') {
        grid.classList.remove('d-none');
        table.classList.add('d-none');
        gBtn.classList.add('btn-primary', 'active');
        gBtn.classList.remove('btn-light', 'text-secondary');
        tBtn.classList.remove('btn-primary', 'active');
        tBtn.classList.add('btn-light', 'text-secondary');
    } else {
        grid.classList.add('d-none');
        table.classList.remove('d-none');
        tBtn.classList.add('btn-primary', 'active');
        tBtn.classList.remove('btn-light', 'text-secondary');
        gBtn.classList.remove('btn-primary', 'active');
        gBtn.classList.add('btn-light', 'text-secondary');
    }
}

function filterDealsData() {
    const query = document.getElementById('dealSearchInput').value.toLowerCase().trim();
    let visibleCount = 0;

    // Filter Grid Cards
    document.querySelectorAll('.deal-card-item').forEach(card => {
        const title = card.getAttribute('data-title') || '';
        const stage = card.getAttribute('data-stage') || '';

        const matchesQuery = !query || title.includes(query);
        const matchesStage = (currentSelectedStage === 'all') || (stage === currentSelectedStage);

        if (matchesQuery && matchesStage) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Filter Table Rows
    document.querySelectorAll('.deal-table-row').forEach(row => {
        const title = row.getAttribute('data-title') || '';
        const stage = row.getAttribute('data-stage') || '';

        const matchesQuery = !query || title.includes(query);
        const matchesStage = (currentSelectedStage === 'all') || (stage === currentSelectedStage);

        if (matchesQuery && matchesStage) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    const countRes = document.getElementById('dealCountResult');
    if (countRes) {
        countRes.textContent = 'Showing ' + visibleCount + ' opportunities';
    }
}

function updateDealStage(dealId, newStage) {
    fetch('<?php echo BASE_URL; ?>/ajax/deals/stage', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ deal_id: dealId, stage: newStage })
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

function viewDealModal(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/deals/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const d = res.data;
            document.getElementById('modalDealQuoteNum').textContent = d.quote_number || ('DEAL #' + d.id);
            document.getElementById('modalDealTitle').textContent = d.title;
            document.getElementById('modalDealValue').textContent = (d.currency || 'USD') + ' ' + parseFloat(d.value).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('modalDealStage').textContent = d.stage.replace('_', ' ').toUpperCase();
            document.getElementById('modalDealDueDate').textContent = d.due_date ? d.due_date : 'Not set';
            document.getElementById('modalDealDesc').textContent = d.description || 'No additional notes.';

            const tbody = document.getElementById('modalProductsBody');
            if (d.products && d.products.length > 0) {
                tbody.innerHTML = d.products.map(p => `
                    <tr>
                        <td class="py-2 px-3 fw-medium">${p.product_name || 'Product'}<br><span class="text-muted small">${p.description || ''}</span></td>
                        <td class="py-2 px-3">${p.quantity}</td>
                        <td class="py-2 px-3">$${parseFloat(p.price).toFixed(2)}</td>
                        <td class="py-2 px-3 text-end fw-bold">$${parseFloat(p.total).toFixed(2)}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted small">No line items attached</td></tr>';
            }

            new bootstrap.Modal(document.getElementById('dealDetailModal')).show();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
