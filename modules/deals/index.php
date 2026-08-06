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

// Normalize native currency tags
foreach ($deals as &$d) {
    $curr = strtoupper(trim($d['currency'] ?? 'USD'));
    if (strpos($curr, 'NGN') !== false || strpos($curr, '₦') !== false) {
        $d['native_currency'] = 'NGN';
        $d['currency_symbol'] = '₦';
    } else {
        $d['native_currency'] = 'USD';
        $d['currency_symbol'] = '$';
    }
}
unset($d);

// Separate open and won deals by native creation currency (No artificial exchange conversion)
$openUsdDeals = array_filter($deals, fn($d) => !in_array($d['stage'], ['closed_won', 'closed_lost']) && $d['native_currency'] === 'USD');
$openNgnDeals = array_filter($deals, fn($d) => !in_array($d['stage'], ['closed_won', 'closed_lost']) && $d['native_currency'] === 'NGN');

$openUsdSum = array_sum(array_column($openUsdDeals, 'value'));
$openNgnSum = array_sum(array_column($openNgnDeals, 'value'));

$wonUsdDeals = array_filter($deals, fn($d) => $d['stage'] === 'closed_won' && $d['native_currency'] === 'USD');
$wonNgnDeals = array_filter($deals, fn($d) => $d['stage'] === 'closed_won' && $d['native_currency'] === 'NGN');

$wonUsdSum = array_sum(array_column($wonUsdDeals, 'value'));
$wonNgnSum = array_sum(array_column($wonNgnDeals, 'value'));

// Fetch dropdown options for form
$customers = $pdo->query("SELECT id, display_name, company FROM customers WHERE is_archived = 0 ORDER BY display_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$leads = $pdo->query("SELECT id, display_name, company FROM leads WHERE is_archived = 0 ORDER BY display_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''), name, email) as name FROM users WHERE is_archived = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Stage configurations with colors and soft badge styles
$stages = [
    'qualification' => ['title' => 'Qualification', 'color' => '#3b82f6', 'badge' => 'badge-soft-info'],
    'value_proposition' => ['title' => 'Value Proposition', 'color' => '#8b5cf6', 'badge' => 'badge-soft-primary'],
    'proposal_quote' => ['title' => 'Proposal / Quote', 'color' => '#f59e0b', 'badge' => 'badge-soft-warning'],
    'negotiation_review' => ['title' => 'Negotiation', 'color' => '#06b6d4', 'badge' => 'badge-soft-info'],
    'closed_won' => ['title' => 'Closed Won', 'color' => '#10b981', 'badge' => 'badge-soft-success'],
    'closed_lost' => ['title' => 'Closed Lost', 'color' => '#ef4444', 'badge' => 'badge-soft-danger']
];

$nextStageMap = [
    'qualification' => ['key' => 'value_proposition', 'title' => 'Value Proposition'],
    'value_proposition' => ['key' => 'proposal_quote', 'title' => 'Proposal / Quote'],
    'proposal_quote' => ['key' => 'negotiation_review', 'title' => 'Negotiation'],
    'negotiation_review' => ['key' => 'closed_won', 'title' => 'Closed Won']
];
?>

<div class="container-fluid px-0">
    <!-- Header & Breadcrumb -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Deals', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Deals & Sales Pipeline</h2>
            <p class="text-muted small mb-0">Track original USD ($) and NGN (₦) opportunities separately without artificial conversions.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- Native Currency Filter Switcher Pill -->
            <div class="btn-group p-1 bg-white border rounded-pill shadow-sm" role="group" title="Filter deals by native creation currency">
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 active" id="currAllBtn" onclick="filterCurrencyDisplay('ALL')">
                    All Currencies
                </button>
                <button type="button" class="btn btn-sm btn-light rounded-pill px-3 text-secondary" id="currUsdBtn" onclick="filterCurrencyDisplay('USD')">
                    <i data-lucide="dollar-sign" class="lucide-icon me-0.5"></i> USD ($) Only
                </button>
                <button type="button" class="btn btn-sm btn-light rounded-pill px-3 text-secondary" id="currNgnBtn" onclick="filterCurrencyDisplay('NGN')">
                    <i data-lucide="banknote" class="lucide-icon me-0.5"></i> NGN (₦) Only
                </button>
            </div>

            <!-- View Mode Switcher -->
            <div class="btn-group p-1 bg-white border rounded-pill shadow-sm ms-1" role="group">
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 active" id="gridModeBtn" onclick="switchViewMode('grid')">
                    <i data-lucide="layout-grid" class="lucide-icon me-1"></i> Grid
                </button>
                <button type="button" class="btn btn-sm btn-light rounded-pill px-3 text-secondary" id="tableModeBtn" onclick="switchViewMode('table')">
                    <i data-lucide="list" class="lucide-icon me-1"></i> List
                </button>
            </div>
            
            <?php if (hasPermission('create_deals')): ?>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm ms-2" data-bs-toggle="offcanvas" data-bs-target="#dealOffcanvas" onclick="resetDealForm()">
                    <i data-lucide="plus" class="lucide-icon me-1"></i> New Deal
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Executive KPI Strip: Consolidated Vertical Split Cards for USD and NGN -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Open Pipeline Value (Vertical Split) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase text-muted fw-bold small" style="font-size: 0.75rem;">Open Pipeline Value</span>
                    <div class="rounded-circle bg-light p-2 text-primary d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i data-lucide="trending-up" class="lucide-icon text-primary"></i>
                    </div>
                </div>
                <div class="row g-0 align-items-center mt-2">
                    <div class="col-6 pe-2 border-end border-secondary-subtle">
                        <span class="text-muted small fw-medium d-block mb-1" style="font-size: 0.725rem;">USD ($)</span>
                        <span class="fw-bold text-dark d-block text-truncate" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;" title="$<?php echo number_format($openUsdSum, 2); ?>">
                            $<?php echo number_format($openUsdSum, 2); ?>
                        </span>
                        <span class="text-muted small d-block mt-0.5" style="font-size: 0.7rem;"><?php echo count($openUsdDeals); ?> deals</span>
                    </div>
                    <div class="col-6 ps-3">
                        <span class="text-muted small fw-medium d-block mb-1" style="font-size: 0.725rem;">NGN (₦)</span>
                        <span class="fw-bold text-dark d-block text-truncate" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;" title="₦<?php echo number_format($openNgnSum, 2); ?>">
                            ₦<?php echo number_format($openNgnSum, 2); ?>
                        </span>
                        <span class="text-muted small d-block mt-0.5" style="font-size: 0.7rem;"><?php echo count($openNgnDeals); ?> deals</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Closed Won Revenue (Vertical Split) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase text-muted fw-bold small" style="font-size: 0.75rem;">Closed Won Revenue</span>
                    <div class="rounded-circle bg-light p-2 text-success d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i data-lucide="trophy" class="lucide-icon text-success"></i>
                    </div>
                </div>
                <div class="row g-0 align-items-center mt-2">
                    <div class="col-6 pe-2 border-end border-secondary-subtle">
                        <span class="text-muted small fw-medium d-block mb-1" style="font-size: 0.725rem;">USD ($)</span>
                        <span class="fw-bold text-dark d-block text-truncate" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;" title="$<?php echo number_format($wonUsdSum, 2); ?>">
                            $<?php echo number_format($wonUsdSum, 2); ?>
                        </span>
                        <span class="text-muted small d-block mt-0.5" style="font-size: 0.7rem;"><?php echo count($wonUsdDeals); ?> won</span>
                    </div>
                    <div class="col-6 ps-3">
                        <span class="text-muted small fw-medium d-block mb-1" style="font-size: 0.725rem;">NGN (₦)</span>
                        <span class="fw-bold text-dark d-block text-truncate" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;" title="₦<?php echo number_format($wonNgnSum, 2); ?>">
                            ₦<?php echo number_format($wonNgnSum, 2); ?>
                        </span>
                        <span class="text-muted small d-block mt-0.5" style="font-size: 0.7rem;"><?php echo count($wonNgnDeals); ?> won</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Total Opportunities -->
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Opportunities', count($deals), 'briefcase', 'info', 'All time deals'); ?>
        </div>

        <!-- Card 4: Win Conversion Rate -->
        <div class="col-12 col-sm-6 col-xl-3">
            <?php 
                $winRate = (count($deals) > 0) ? round((count($wonUsdDeals) + count($wonNgnDeals)) / count($deals) * 100, 1) : 0;
                renderStatCard('Win Conversion Rate', $winRate . '%', 'pie-chart', 'warning', 'Won vs total deals'); 
            ?>
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
                        $<?php echo number_format($openUsdSum + $wonUsdSum, 0); ?> | ₦<?php echo number_format($openNgnSum + $wonNgnSum, 0); ?>
                    </div>
                </button>

                <!-- Individual Stage Tabs -->
                <?php foreach ($stages as $stageKey => $stageInfo): ?>
                    <?php 
                        $stageDeals = array_filter($deals, fn($d) => $d['stage'] === $stageKey);
                        $sUsdSum = array_sum(array_column(array_filter($stageDeals, fn($d) => $d['native_currency'] === 'USD'), 'value'));
                        $sNgnSum = array_sum(array_column(array_filter($stageDeals, fn($d) => $d['native_currency'] === 'NGN'), 'value'));
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
                            $<?php echo number_format($sUsdSum, 0); ?> | ₦<?php echo number_format($sNgnSum, 0); ?>
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
            <div class="col-12 text-center py-5 text-muted">
                <i data-lucide="briefcase" class="lucide-icon d-block mx-auto mb-2 opacity-50" style="width: 48px; height: 48px;"></i>
                <h5>No Opportunities Found</h5>
                <p class="small mb-3">Click "New Deal" to register a sales opportunity.</p>
            </div>
        <?php else: ?>
            <?php foreach ($deals as $d): ?>
                <?php 
                    $custName = $d['customer_display_name'] ?: $d['customer_company'] ?: $d['lead_display_name'] ?: 'General Prospect';
                    $ownerName = $d['assigned_to_name'] ?: 'Unassigned';
                    $stageInfo = $stages[$d['stage']] ?? ['title' => ucfirst($d['stage']), 'badge' => 'badge-soft-secondary', 'color' => '#64748b'];
                    $dueDateText = $d['due_date'] ? date('M j, Y', strtotime($d['due_date'])) : null;
                    $isOverdue = ($d['due_date'] && strtotime($d['due_date']) < time() && !in_array($d['stage'], ['closed_won', 'closed_lost']));
                    $hasNextStage = isset($nextStageMap[$d['stage']]);
                    $nextStageObj = $nextStageMap[$d['stage']] ?? null;
                    $currBadgeClass = ($d['native_currency'] === 'NGN') ? 'badge-soft-info' : 'badge-soft-primary';
                ?>
                <div class="col-12 col-md-6 col-xl-4 deal-card-item" data-stage="<?php echo $d['stage']; ?>" data-currency="<?php echo $d['native_currency']; ?>" data-title="<?php echo htmlspecialchars(strtolower($d['title'] . ' ' . $custName . ' ' . ($d['quote_number'] ?? ''))); ?>">
                    <div class="card border shadow-sm rounded-3 h-100 bg-white hover-shadow transition-all" style="border-left: 4px solid <?php echo $stageInfo['color']; ?> !important;">
                        <div class="card-body p-3.5 d-flex flex-column">
                            
                            <!-- Stage Badge, Native Currency Badge & Quote Number -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="badge rounded-pill <?php echo $stageInfo['badge']; ?>">
                                        <?php echo htmlspecialchars($stageInfo['title']); ?>
                                    </span>
                                    <span class="badge rounded-pill <?php echo $currBadgeClass; ?>">
                                        <?php echo $d['native_currency']; ?>
                                    </span>
                                </div>
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

                            <!-- Native Creation Deal Value & Target Date Block -->
                            <div class="py-2 mb-3 mt-auto">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="text-muted d-block" style="font-size: 0.725rem;">Original Deal Value</span>
                                        <span class="fw-bold text-primary d-block" style="font-size: 1.15rem; font-family: 'Outfit', sans-serif;">
                                            <?php echo $d['currency_symbol'] . ' ' . number_format($d['value'], 2); ?>
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

                            <!-- 1-Click Stage Advancer Action Bar -->
                            <?php if (hasPermission('edit_deals')): ?>
                                <div class="mb-3 d-flex gap-2">
                                    <?php if ($hasNextStage): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1 d-flex align-items-center justify-content-center gap-1 shadow-sm" style="font-size: 0.775rem;" onclick="advanceDealStage(<?php echo $d['id']; ?>, '<?php echo $d['stage']; ?>', '<?php echo htmlspecialchars(addslashes($d['title'])); ?>', <?php echo $d['value']; ?>, '<?php echo $d['currency_symbol']; ?>')">
                                            <span>Advance to <strong><?php echo htmlspecialchars($nextStageObj['title']); ?></strong></span>
                                            <i data-lucide="arrow-right" class="lucide-icon" style="width: 14px; height: 14px;"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!in_array($d['stage'], ['closed_won', 'closed_lost'])): ?>
                                        <button type="button" class="btn btn-sm btn-light border text-success rounded-circle d-flex align-items-center justify-content-center" title="Mark Closed Won" style="width: 32px; height: 32px;" onclick="markDealWon(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['title'])); ?>', <?php echo $d['value']; ?>, '<?php echo $d['currency_symbol']; ?>')">
                                            <i data-lucide="trophy" class="lucide-icon text-success" style="width: 16px; height: 16px;"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light border text-danger rounded-circle d-flex align-items-center justify-content-center" title="Mark Closed Lost" style="width: 32px; height: 32px;" onclick="markDealLost(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['title'])); ?>')">
                                            <i data-lucide="x-circle" class="lucide-icon text-danger" style="width: 16px; height: 16px;"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

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
                                            <li><a class="dropdown-item py-2" href="#" onclick="editDeal(<?php echo $d['id']; ?>); return false;"><i data-lucide="edit-3" class="lucide-icon me-2"></i> Edit Deal</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li class="dropdown-header small text-muted">Direct Stage Move</li>
                                            <?php foreach ($stages as $sk => $si): ?>
                                                <?php if ($sk !== $d['stage']): ?>
                                                    <li><a class="dropdown-item py-1.5 small" href="#" onclick="updateDealStage(<?php echo $d['id']; ?>, '<?php echo $sk; ?>', '<?php echo htmlspecialchars(addslashes($d['title'])); ?>'); return false;"><?php echo htmlspecialchars($si['title']); ?></a></li>
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
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Original Value</th>
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
                            <tr class="deal-table-row" data-stage="<?php echo $d['stage']; ?>" data-currency="<?php echo $d['native_currency']; ?>" data-title="<?php echo htmlspecialchars(strtolower($d['title'] . ' ' . $custName)); ?>">
                                <td class="py-3 px-4">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($d['title']); ?></div>
                                    <?php if ($d['quote_number']): ?>
                                        <span class="badge badge-soft-secondary small mt-0.5"><?php echo htmlspecialchars($d['quote_number']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($custName); ?></td>
                                <td class="py-3 px-4">
                                    <span class="fw-bold text-primary me-1"><?php echo $d['currency_symbol'] . ' ' . number_format($d['value'], 2); ?></span>
                                    <span class="badge rounded-pill <?php echo ($d['native_currency'] === 'NGN') ? 'badge-soft-info' : 'badge-soft-primary'; ?>"><?php echo $d['native_currency']; ?></span>
                                </td>
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
                                                <li><a class="dropdown-item py-2 d-flex align-items-center gap-2" href="#" onclick="editDeal(<?php echo $d['id']; ?>); return false;"><i data-lucide="edit-3" class="lucide-icon text-muted"></i> Edit Deal</a></li>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <?php if (isset($nextStageMap[$d['stage']])): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-primary fw-semibold d-flex align-items-center gap-2" href="#" onclick="advanceDealStage(<?php echo $d['id']; ?>, '<?php echo $d['stage']; ?>', '<?php echo htmlspecialchars(addslashes($d['title'])); ?>', <?php echo $d['value']; ?>, '<?php echo $d['currency_symbol']; ?>'); return false;">
                                                            <i data-lucide="arrow-right-circle" class="lucide-icon text-primary"></i> Advance to <?php echo htmlspecialchars($nextStageMap[$d['stage']]['title']); ?>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (!in_array($d['stage'], ['closed_won', 'closed_lost'])): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-success d-flex align-items-center gap-2" href="#" onclick="markDealWon(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['title'])); ?>', <?php echo $d['value']; ?>, '<?php echo $d['currency_symbol']; ?>'); return false;">
                                                            <i data-lucide="trophy" class="lucide-icon text-success"></i> Mark Closed Won
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2" href="#" onclick="markDealLost(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['title'])); ?>'); return false;">
                                                            <i data-lucide="x-circle" class="lucide-icon text-danger"></i> Mark Closed Lost
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <li class="dropdown-header small text-muted mt-1">Direct Stage Move</li>
                                                <?php foreach ($stages as $sk => $si): ?>
                                                    <?php if ($sk !== $d['stage']): ?>
                                                        <li><a class="dropdown-item py-1.5 small" href="#" onclick="updateDealStage(<?php echo $d['id']; ?>, '<?php echo $sk; ?>', '<?php echo htmlspecialchars(addslashes($d['title'])); ?>'); return false;"><?php echo htmlspecialchars($si['title']); ?></a></li>
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

<!-- 50% WIDTH OFFCANVAS SLIDER FOR NEW / EDIT DEAL -->
<div class="offcanvas offcanvas-end offcanvas-half" id="dealOffcanvas" tabindex="-1" aria-labelledby="dealOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold text-dark" id="dealOffcanvasTitle" style="font-family: 'Outfit', sans-serif;">New Opportunity / Deal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form id="dealForm" onsubmit="saveDeal(event)">
        <div class="offcanvas-body p-4">
            <input type="hidden" id="dealId" name="id">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-semibold">Deal Title <span class="text-danger">*</span></label>
                    <input type="text" id="dealTitleInput" name="title" class="form-control" placeholder="e.g. Enterprise CRM Software License" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Linked Customer</label>
                    <select id="dealCustomerId" name="customer_id" class="form-select">
                        <option value="">Select Customer (Optional)</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['display_name'] . ($c['company'] ? ' (' . $c['company'] . ')' : '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Linked Inbound Lead</label>
                    <select id="dealLeadId" name="lead_id" class="form-select">
                        <option value="">Select Lead (Optional)</option>
                        <?php foreach ($leads as $l): ?>
                            <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['display_name'] . ($l['company'] ? ' (' . $l['company'] . ')' : '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Deal Value <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" id="dealValue" name="value" class="form-control" placeholder="5000.00" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Deal Creation Currency</label>
                    <select id="dealCurrency" name="currency" class="form-select">
                        <option value="USD" selected>USD ($)</option>
                        <option value="NGN">NGN (₦)</option>
                        <option value="GBP">GBP (£)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Pipeline Stage</label>
                    <select id="dealStage" name="stage" class="form-select">
                        <?php foreach ($stages as $sk => $si): ?>
                            <option value="<?php echo $sk; ?>"><?php echo htmlspecialchars($si['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Target Close Date</label>
                    <input type="date" id="dealDueDate" name="due_date" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Quote Validity Until</label>
                    <input type="date" id="dealValidUntil" name="valid_until" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Account Manager / Assigned Staff</label>
                    <select id="dealAssignedTo" name="assigned_to" class="form-select">
                        <option value="">Select Staff</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Description / Scope of Work</label>
                    <textarea id="dealDescription" name="description" class="form-control" rows="3" placeholder="Provide notes, requirement details, or proposal notes..."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Terms & Conditions</label>
                    <textarea id="dealTerms" name="terms_conditions" class="form-control" rows="2" placeholder="Standard payment and delivery terms..."></textarea>
                </div>
            </div>
        </div>
        <div class="offcanvas-footer gap-2 d-flex justify-content-end">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="offcanvas">Cancel</button>
            <button type="submit" class="btn btn-primary rounded-pill px-4">Save Opportunity</button>
        </div>
    </form>
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
                            <span class="text-muted small d-block">Original Deal Value</span>
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
let currentSelectedCurrencyFilter = 'ALL';

function filterCurrencyDisplay(curr) {
    currentSelectedCurrencyFilter = curr;
    const allBtn = document.getElementById('currAllBtn');
    const usdBtn = document.getElementById('currUsdBtn');
    const ngnBtn = document.getElementById('currNgnBtn');

    [allBtn, usdBtn, ngnBtn].forEach(btn => {
        btn.classList.remove('btn-primary', 'active');
        btn.classList.add('btn-light', 'text-secondary');
    });

    if (curr === 'USD') {
        usdBtn.classList.add('btn-primary', 'active');
        usdBtn.classList.remove('btn-light', 'text-secondary');
    } else if (curr === 'NGN') {
        ngnBtn.classList.add('btn-primary', 'active');
        ngnBtn.classList.remove('btn-light', 'text-secondary');
    } else {
        allBtn.classList.add('btn-primary', 'active');
        allBtn.classList.remove('btn-light', 'text-secondary');
    }

    filterDealsData();
}

function resetDealForm() {
    document.getElementById('dealForm').reset();
    document.getElementById('dealId').value = '';
    document.getElementById('dealOffcanvasTitle').textContent = 'New Opportunity / Deal';
}

function saveDeal(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('dealForm')).entries());
    fetch('<?php echo BASE_URL; ?>/ajax/deals/save', {
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

function editDeal(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/deals/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const d = res.data;
            document.getElementById('dealId').value = d.id;
            document.getElementById('dealTitleInput').value = d.title || '';
            document.getElementById('dealCustomerId').value = d.customer_id || '';
            document.getElementById('dealLeadId').value = d.lead_id || '';
            document.getElementById('dealValue').value = d.value || 0;
            document.getElementById('dealCurrency').value = d.currency || 'USD';
            document.getElementById('dealStage').value = d.stage || 'qualification';
            document.getElementById('dealDueDate').value = d.due_date || '';
            document.getElementById('dealValidUntil').value = d.valid_until || '';
            document.getElementById('dealAssignedTo').value = d.assigned_to || '';
            document.getElementById('dealDescription').value = d.description || '';
            document.getElementById('dealTerms').value = d.terms_conditions || '';
            document.getElementById('dealOffcanvasTitle').textContent = 'Edit Opportunity';
            new bootstrap.Offcanvas(document.getElementById('dealOffcanvas')).show();
        }
    });
}

async function advanceDealStage(id, currentStage, dealTitle, dealValue, currencySymbol) {
    const stageFlow = {
        'qualification': { next: 'value_proposition', title: 'Value Proposition' },
        'value_proposition': { next: 'proposal_quote', title: 'Proposal / Quote' },
        'proposal_quote': { next: 'negotiation_review', title: 'Negotiation' },
        'negotiation_review': { next: 'closed_won', title: 'Closed Won' }
    };

    const nextInfo = stageFlow[currentStage];
    if (!nextInfo) return;

    const confirmed = await window.appConfirm({
        title: 'Advance Opportunity Stage',
        message: `Are you sure you want to advance "${dealTitle}" to the next stage: ${nextInfo.title}?`,
        confirmText: `Advance to ${nextInfo.title}`,
        cancelText: 'Cancel',
        isDanger: false
    });

    if (confirmed) {
        executeStageUpdate(id, nextInfo.next);
    }
}

async function markDealWon(id, dealTitle, dealValue, currencySymbol) {
    const formattedVal = (currencySymbol || '$') + ' ' + parseFloat(dealValue).toLocaleString('en-US', { minimumFractionDigits: 2 });
    const confirmed = await window.appConfirm({
        title: 'Mark Opportunity as Closed Won',
        message: `Are you sure you want to close "${dealTitle}" as WON (${formattedVal})?`,
        confirmText: 'Yes, Mark Closed Won',
        cancelText: 'Cancel',
        isDanger: false
    });

    if (confirmed) {
        executeStageUpdate(id, 'closed_won');
    }
}

async function markDealLost(id, dealTitle) {
    const confirmed = await window.appConfirm({
        title: 'Mark Opportunity as Closed Lost',
        message: `Are you sure you want to mark "${dealTitle}" as LOST?`,
        confirmText: 'Yes, Mark Closed Lost',
        cancelText: 'Cancel',
        isDanger: true
    });

    if (confirmed) {
        executeStageUpdate(id, 'closed_lost');
    }
}

async function updateDealStage(id, targetStage, dealTitle) {
    const stageTitles = {
        'qualification': 'Qualification',
        'value_proposition': 'Value Proposition',
        'proposal_quote': 'Proposal / Quote',
        'negotiation_review': 'Negotiation',
        'closed_won': 'Closed Won',
        'closed_lost': 'Closed Lost'
    };

    const targetTitle = stageTitles[targetStage] || targetStage;
    const isDanger = (targetStage === 'closed_lost');

    const confirmed = await window.appConfirm({
        title: 'Move Opportunity Stage',
        message: `Are you sure you want to move "${dealTitle || 'this opportunity'}" to stage: ${targetTitle}?`,
        confirmText: `Move to ${targetTitle}`,
        cancelText: 'Cancel',
        isDanger: isDanger
    });

    if (confirmed) {
        executeStageUpdate(id, targetStage);
    }
}

function executeStageUpdate(id, stage) {
    fetch('<?php echo BASE_URL; ?>/ajax/deals/stage', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ deal_id: id, stage: stage })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast(res.message || 'Stage updated successfully', 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(res.message || 'Failed to update stage', 'danger');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Server error updating stage', 'danger');
    });
}

function selectStageTab(stage) {
    currentSelectedStage = stage;
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

    document.querySelectorAll('.deal-card-item').forEach(card => {
        const title = card.getAttribute('data-title') || '';
        const stage = card.getAttribute('data-stage') || '';
        const curr = card.getAttribute('data-currency') || '';

        const matchesQuery = !query || title.includes(query);
        const matchesStage = (currentSelectedStage === 'all') || (stage === currentSelectedStage);
        const matchesCurr = (currentSelectedCurrencyFilter === 'ALL') || (curr === currentSelectedCurrencyFilter);

        if (matchesQuery && matchesStage && matchesCurr) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    document.querySelectorAll('.deal-table-row').forEach(row => {
        const title = row.getAttribute('data-title') || '';
        const stage = row.getAttribute('data-stage') || '';
        const curr = row.getAttribute('data-currency') || '';

        const matchesQuery = !query || title.includes(query);
        const matchesStage = (currentSelectedStage === 'all') || (stage === currentSelectedStage);
        const matchesCurr = (currentSelectedCurrencyFilter === 'ALL') || (curr === currentSelectedCurrencyFilter);

        row.style.display = (matchesQuery && matchesStage && matchesCurr) ? '' : 'none';
    });

    const resLabel = document.getElementById('dealCountResult');
    if (resLabel) resLabel.textContent = 'Showing ' + visibleCount + ' opportunities';
}

function viewDealModal(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/deals/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const d = res.data;
            document.getElementById('modalDealQuoteNum').textContent = d.quote_number || 'QT-GENERIC';
            document.getElementById('modalDealTitle').textContent = d.title;
            
            const val = parseFloat(d.value || 0);
            const curr = (d.currency || 'USD').toUpperCase();
            const symbol = (curr.includes('NGN') || curr.includes('₦')) ? '₦' : '$';

            document.getElementById('modalDealValue').textContent = symbol + ' ' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + curr + ')';
            document.getElementById('modalDealStage').textContent = d.stage ? d.stage.replace('_', ' ').toUpperCase() : 'QUALIFICATION';
            document.getElementById('modalDealDueDate').textContent = d.due_date || '-';
            document.getElementById('modalDealDesc').textContent = d.description || 'No description notes provided.';
            
            new bootstrap.Modal(document.getElementById('dealDetailModal')).show();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
