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
$dealScope = getRecordScopeCondition('d.assigned_to', 'view_team_deals');
$sql = "SELECT d.*, 
               c.display_name as customer_display_name, c.company as customer_company,
               l.display_name as lead_display_name,
               COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as assigned_to_name
        FROM deals d 
        LEFT JOIN customers c ON d.customer_id = c.id 
        LEFT JOIN leads l ON d.lead_id = l.id 
        LEFT JOIN users u ON d.assigned_to = u.id 
        WHERE d.is_archived = 0 AND {$dealScope}
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
$products = $pdo->query("SELECT id, name, code, price, description FROM products WHERE is_archived = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch active currencies from DB (ordered: base first, then others)
$dbCurrencies = $pdo->query("SELECT code, name, symbol, exchange_rate, is_base FROM currencies WHERE status = 'active' ORDER BY is_base DESC, code ASC")->fetchAll(PDO::FETCH_ASSOC);

// Build a quick lookup keyed by code for use in PHP & JS
$currencyMap = [];
foreach ($dbCurrencies as $cur) {
    $currencyMap[$cur['code']] = $cur;
}


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
        <div class="d-flex align-items-center gap-2 flex-wrap">
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

            <?php if (hasPermission('download_deals_template')): ?>
                <a href="<?php echo BASE_URL; ?>/assets/templates/deals_template.csv" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm" download>
                    <i data-lucide="download" class="lucide-icon me-1"></i> Template
                </a>
            <?php endif; ?>
            <?php if (hasPermission('export_deals')): ?>
                <button type="button" class="btn btn-outline-primary rounded-pill px-3 shadow-sm" onclick="exportTableToCSV('dealsTable', 'deals_export.csv')">
                    <i data-lucide="download-cloud" class="lucide-icon me-1"></i> Export CSV
                </button>
            <?php endif; ?>
            
            <?php if (hasPermission('create_deals')): ?>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm ms-1" data-bs-toggle="offcanvas" data-bs-target="#dealOffcanvas" onclick="resetDealForm()">
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
                    $isClosed = in_array($d['stage'], ['closed_won', 'closed_lost']);
                    $isOverdue = ($d['due_date'] && strtotime($d['due_date']) < time() && !$isClosed);
                    $hasNextStage = isset($nextStageMap[$d['stage']]) && !$isClosed;
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
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li class="dropdown-header small text-muted">Print Client Documents</li>
                                        <li><a class="dropdown-item py-1.5 small text-primary" href="<?php echo BASE_URL; ?>/modules/deals/print.php?id=<?php echo $d['id']; ?>&type=quote" target="_blank"><i data-lucide="printer" class="lucide-icon me-2"></i> Official Quotation</a></li>
                                        <li><a class="dropdown-item py-1.5 small text-success" href="<?php echo BASE_URL; ?>/modules/deals/print.php?id=<?php echo $d['id']; ?>&type=invoice" target="_blank"><i data-lucide="file-text" class="lucide-icon me-2"></i> Commercial Invoice</a></li>
                                        <li><a class="dropdown-item py-1.5 small text-info" href="<?php echo BASE_URL; ?>/modules/deals/print.php?id=<?php echo $d['id']; ?>&type=proposal" target="_blank"><i data-lucide="file-spreadsheet" class="lucide-icon me-2"></i> Business Proposal</a></li>
                                        <?php if (hasPermission('edit_deals') && !$isClosed): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item py-2" href="#" onclick="editDeal(<?php echo $d['id']; ?>); return false;"><i data-lucide="edit-3" class="lucide-icon me-2"></i> Edit Deal</a></li>
                                            <li class="dropdown-header small text-muted mt-1">Direct Stage Move</li>
                                            <?php foreach ($stages as $sk => $si): ?>
                                                <?php if ($sk !== $d['stage']): ?>
                                                    <li><a class="dropdown-item py-1.5 small" href="#" onclick="updateDealStage(<?php echo $d['id']; ?>, '<?php echo $sk; ?>', '<?php echo htmlspecialchars(addslashes($d['title'])); ?>'); return false;"><?php echo htmlspecialchars($si['title']); ?></a></li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if (hasPermission('archive_deals') || hasPermission('delete_deals')): ?>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li><a class="dropdown-item py-2 text-danger" href="#" onclick="archiveDeal(<?php echo $d['id']; ?>); return false;"><i data-lucide="trash-2" class="lucide-icon me-2 text-danger"></i> Archive Deal</a></li>
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
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li class="dropdown-header small text-muted">Print Client Documents</li>
                                            <li><a class="dropdown-item py-1.5 small text-primary d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>/modules/deals/print.php?id=<?php echo $d['id']; ?>&type=quote" target="_blank"><i data-lucide="printer" class="lucide-icon"></i> Official Quotation</a></li>
                                            <li><a class="dropdown-item py-1.5 small text-success d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>/modules/deals/print.php?id=<?php echo $d['id']; ?>&type=invoice" target="_blank"><i data-lucide="file-text" class="lucide-icon"></i> Commercial Invoice</a></li>
                                            <li><a class="dropdown-item py-1.5 small text-info d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>/modules/deals/print.php?id=<?php echo $d['id']; ?>&type=proposal" target="_blank"><i data-lucide="file-spreadsheet" class="lucide-icon"></i> Business Proposal</a></li>
                                            <?php if (hasPermission('edit_deals') && !$isClosed): ?>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li><a class="dropdown-item py-2 d-flex align-items-center gap-2" href="#" onclick="editDeal(<?php echo $d['id']; ?>); return false;"><i data-lucide="edit-3" class="lucide-icon text-muted"></i> Edit Deal</a></li>
                                                <?php if (isset($nextStageMap[$d['stage']])): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 text-primary fw-semibold d-flex align-items-center gap-2" href="#" onclick="advanceDealStage(<?php echo $d['id']; ?>, '<?php echo $d['stage']; ?>', '<?php echo htmlspecialchars(addslashes($d['title'])); ?>', <?php echo $d['value']; ?>, '<?php echo $d['currency_symbol']; ?>'); return false;">
                                                            <i data-lucide="arrow-right-circle" class="lucide-icon text-primary"></i> Advance to <?php echo htmlspecialchars($nextStageMap[$d['stage']]['title']); ?>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
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
                                                <li class="dropdown-header small text-muted mt-1">Direct Stage Move</li>
                                                <?php foreach ($stages as $sk => $si): ?>
                                                    <?php if ($sk !== $d['stage']): ?>
                                                        <li><a class="dropdown-item py-1.5 small" href="#" onclick="updateDealStage(<?php echo $d['id']; ?>, '<?php echo $sk; ?>', '<?php echo htmlspecialchars(addslashes($d['title'])); ?>'); return false;"><?php echo htmlspecialchars($si['title']); ?></a></li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <?php if (hasPermission('archive_deals') || hasPermission('delete_deals')): ?>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li><a class="dropdown-item py-2 text-danger" href="#" onclick="archiveDeal(<?php echo $d['id']; ?>); return false;"><i data-lucide="trash-2" class="lucide-icon me-2 text-danger"></i> Archive Deal</a></li>
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
    <div class="offcanvas-header border-0 text-white" style="background: linear-gradient(135deg, #12b1b0 0%, #0d8584 100%);">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <i data-lucide="briefcase" class="lucide-icon" style="width: 18px; height: 18px;"></i>
                <span class="text-white opacity-75 small fw-semibold text-uppercase">Deal Management</span>
            </div>
            <h5 class="offcanvas-title fw-bold text-white mb-0" id="dealOffcanvasTitle" style="font-family: 'Outfit', sans-serif;">New Opportunity / Deal</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <!-- Standalone form element (no wrapping — inputs reference via form='dealForm') -->
    <form id="dealForm" onsubmit="saveDeal(event)" style="display:none;"></form>
    <div class="offcanvas-body">
        <input type="hidden" id="dealId" name="id" form="dealForm">
        <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-semibold">Deal Title <span class="text-danger">*</span></label>
                    <input type="text" id="dealTitleInput" name="title" class="form-control" placeholder="e.g. Enterprise CRM Software License" form="dealForm" required>
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
                    <label class="form-label small fw-semibold">Deal Value (Calculated / Expected) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" id="dealValue" name="value" class="form-control" placeholder="0.00" form="dealForm" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Deal Primary Currency</label>
                    <select id="dealCurrency" name="currency" class="form-select" onchange="updateCurrencySymbol()">
                        <option value="USD" selected>USD ($)</option>
                        <option value="NGN">NGN (₦)</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="EUR">EUR (€)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Pipeline Stage</label>
                    <select id="dealStage" name="stage" class="form-select" onchange="updateStageProgress()">
                        <?php foreach ($stages as $sk => $si): ?>
                            <option value="<?php echo $sk; ?>"><?php echo htmlspecialchars($si['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Stage Completion Progress</label>
                    <div class="progress mt-1" style="height: 22px; border-radius: 8px;">
                        <div class="progress-bar bg-primary fw-bold small" id="stageProgressBar" style="width: 17%;">17%</div>
                    </div>
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
                
                <!-- Line Items Breakdown Section -->
                <div class="col-12 mt-3">
                    <div class="card border shadow-none rounded-3">
                        <div class="card-header bg-light py-3 d-flex align-items-center justify-content-between">
                            <h6 class="m-0 fw-bold text-dark"><i data-lucide="package" class="lucide-icon me-1 text-primary"></i> Line Items / Products Breakdown</h6>
                            <button type="button" class="btn btn-sm btn-outline-info rounded-pill" onclick="openPrintModal()" id="printBtn">
                                <i data-lucide="printer" class="lucide-icon me-1"></i> Print Quote/Proposal
                            </button>
                        </div>
                        <div class="card-body p-3">
                            <!-- Dual Currency Toolbar -->
                            <div class="mb-3 border rounded-3 overflow-hidden">
                                <!-- Toggle Row -->
                                <div class="p-3 bg-light d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <input class="form-check-input" type="checkbox" id="dualCurrencyCheck" onchange="toggleDualCurrency()" style="width: 1.2rem; height: 1.2rem; cursor: pointer;">
                                        <label class="fw-bold mb-0 text-dark small" for="dualCurrencyCheck" style="cursor: pointer;">Dual Currency Mode</label>
                                        <span class="badge rounded-pill" id="dealRateBadge" style="background:#12b1b0; color:white; font-size:0.7rem; display:none;">Deal-Specific Rate Active</span>
                                    </div>
                                    <div id="secondaryCurrencyDiv" class="d-none">
                                        <select class="form-select form-select-sm" id="secCurrencySelect" onchange="onSecCurrencyChange()" style="min-width:180px;">
                                            <?php foreach ($dbCurrencies as $cur): ?>
                                                <?php if (!$cur['is_base']): ?>
                                                    <option value="<?php echo $cur['code']; ?>" 
                                                            data-rate="<?php echo $cur['exchange_rate']; ?>"
                                                            data-db-rate="<?php echo $cur['exchange_rate']; ?>"
                                                            data-symbol="<?php echo htmlspecialchars($cur['symbol']); ?>"
                                                            data-name="<?php echo htmlspecialchars($cur['name']); ?>">
                                                        <?php echo $cur['code']; ?> (<?php echo htmlspecialchars($cur['symbol']); ?>) &mdash; <?php echo htmlspecialchars($cur['name']); ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Deal-Specific Rate Editor (shown when dual currency is ON) -->
                                <div id="dealRateEditorDiv" class="d-none border-top p-3" style="background:#f8fffe;">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-12 mb-1">
                                            <small class="fw-semibold text-muted text-uppercase" style="font-size:0.7rem; letter-spacing:0.05em;">
                                                <i data-lucide="settings-2" class="lucide-icon me-1" style="width:12px;height:12px;"></i>
                                                Deal-Specific Exchange Rate
                                            </small>
                                            <div class="text-muted" style="font-size:0.75rem;">
                                                This rate is saved with <strong>this deal only</strong> and does not affect other deals or the global currency table.
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <label class="form-label small fw-semibold mb-1">1 <span id="rateFromCode">USD</span> =</label>
                                        </div>
                                        <div class="col">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text fw-bold" id="rateSecSymbol">₦</span>
                                                <input type="number" id="dealSpecificRateInput" class="form-control" 
                                                       placeholder="e.g. 1550.00" step="0.01" min="0.0001"
                                                       oninput="applyDealSpecificRate()" form="dealForm"
                                                       style="font-size:0.9rem; font-weight:600;">
                                                <span class="input-group-text text-muted small" id="rateSecCode">NGN</span>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold" 
                                                    onclick="resetToDbRate()"
                                                    style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; font-size:0.8rem;">
                                                <i data-lucide="rotate-ccw" class="lucide-icon me-1" style="width:13px;height:13px;"></i>Reset to DB
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rate Info Banner (dynamic, lives inside the toolbar card) -->
                                <div class="d-flex align-items-center justify-content-between py-2 px-3" id="rateBanner" style="background: linear-gradient(135deg, #12b1b0 0%, #0d8584 100%);">
                                    <div class="d-flex align-items-center text-white gap-2">
                                        <i data-lucide="banknote" class="lucide-icon" style="width:16px;height:16px;"></i>
                                        <small class="fw-semibold mb-0" id="defaultRateDisplay">Dual currency off — enable above to see rates</small>
                                    </div>
                                    <small class="text-white opacity-75" id="rateStatus" style="font-size: 0.7rem;">—</small>
                                </div>
                            </div>

                            <!-- Line Items Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-2" id="itemsTable" style="font-size: 0.85rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="30%">Product Name</th>
                                            <th width="25%">Details / Specifications</th>
                                            <th width="10%">Qty</th>
                                            <th width="15%">Unit Price (<span id="primaryCurrencyCode">USD</span>)</th>
                                            <th width="15%">Total (<span id="primaryCurrencyCodeTotal">USD</span>)</th>
                                            <th width="15%" class="secondary-currency-col d-none">Unit Price (<span class="sec-currency-code">NGN</span>)</th>
                                            <th width="15%" class="secondary-currency-col d-none">Total (<span class="sec-currency-code">NGN</span>)</th>
                                            <th width="5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                            <td class="fw-bold"><span id="subtotalDisplay">$0.00</span></td>
                                            <td class="secondary-currency-col d-none"></td>
                                            <td class="secondary-currency-col d-none fw-bold"><span id="secondarySubtotalDisplay">₦0.00</span></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4" class="text-end fw-bold text-muted">VAT (7.5%):</td>
                                            <td><span id="vatDisplay">$0.00</span></td>
                                            <td class="secondary-currency-col d-none"></td>
                                            <td class="secondary-currency-col d-none text-muted"><span id="secondaryVatDisplay">₦0.00</span></td>
                                            <td></td>
                                        </tr>
                                        <tr class="table-light">
                                            <td colspan="4" class="text-end fw-bold text-primary fs-6">Total (Inc. VAT):</td>
                                            <td class="fw-bold text-primary fs-6"><span id="totalDisplay">$0.00</span></td>
                                            <td class="secondary-currency-col d-none"></td>
                                            <td class="secondary-currency-col d-none fw-bold text-primary fs-6"><span id="secondaryTotalDisplay">₦0.00</span></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill mt-1" onclick="addItem()">
                                <i data-lucide="plus" class="lucide-icon me-1"></i> Add Product Row
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Stage Notes / Progress Comments</label>
                    <textarea id="dealStageNotes" name="stage_notes" class="form-control" rows="2" placeholder="Notes on client requirements, objections, or stage progress..."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Description / Scope of Work</label>
                    <textarea id="dealDescription" name="description" class="form-control" rows="3" placeholder="Provide notes, requirement details, or proposal notes..." form="dealForm"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Terms &amp; Conditions</label>
                    <textarea id="dealTerms" name="terms_conditions" class="form-control" rows="2" placeholder="Standard payment and delivery terms..." form="dealForm"></textarea>
                </div>
            </div>
    </div>
    <div class="offcanvas-footer gap-2 d-flex justify-content-end border-top p-3" style="background: #fff;">
        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="offcanvas">Cancel</button>
        <button type="button" class="btn btn-primary rounded-pill px-4" onclick="document.getElementById('dealForm').dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}))">
            <i data-lucide="save" class="lucide-icon me-1"></i> Save Opportunity
        </button>
    </div>
</div>

<!-- 50% WIDTH READ-ONLY OFFCANVAS SLIDER FOR VIEWING DEAL DETAILS -->
<div class="offcanvas offcanvas-end offcanvas-half" id="dealViewOffcanvas" tabindex="-1" aria-labelledby="dealViewOffcanvasLabel">
    <div class="offcanvas-header border-0 text-white" style="background: linear-gradient(135deg, #12b1b0 0%, #0d8584 100%);">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i data-lucide="eye" class="lucide-icon" style="width: 18px; height: 18px;"></i>
                <span class="text-white opacity-75 small fw-semibold text-uppercase">Opportunity Details</span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge rounded-pill bg-white text-dark fw-semibold" id="viewDealQuoteNum">QT-XXXX</span>
                <span class="badge rounded-pill" id="viewDealStageBadge" style="background: rgba(255,255,255,0.25); color: white; border: 1px solid rgba(255,255,255,0.4);">Qualification</span>
            </div>
            <h5 class="offcanvas-title fw-bold text-white mt-1 mb-0" id="viewDealTitle" style="font-family: 'Outfit', sans-serif;">Deal Details</h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="#" id="viewPrintBtn" target="_blank" class="btn btn-sm rounded-pill" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                <i data-lucide="printer" class="lucide-icon me-1"></i> Print Client Document
            </a>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>
    <div class="offcanvas-body p-4" style="overflow-y: auto;">
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 border">
                    <span class="text-muted small d-block">Original Opportunity Value</span>
                    <h4 class="fw-bold text-primary mb-0" id="viewDealValue">$0.00</h4>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-light rounded-3 border">
                    <span class="text-muted small d-block">Target Completion Date</span>
                    <h5 class="fw-bold text-dark mb-0" id="viewDealDueDate">-</h5>
                </div>
            </div>
            <div class="col-md-6">
                <span class="text-muted small d-block fw-semibold">Linked Customer</span>
                <span class="fw-bold text-dark" id="viewDealCustomer">-</span>
            </div>
            <div class="col-md-6">
                <span class="text-muted small d-block fw-semibold">Linked Lead</span>
                <span class="fw-bold text-dark" id="viewDealLead">-</span>
            </div>
            <div class="col-md-6">
                <span class="text-muted small d-block fw-semibold">Account Manager / Staff</span>
                <span class="fw-bold text-dark" id="viewDealAssignedTo">-</span>
            </div>
            <div class="col-md-6">
                <span class="text-muted small d-block fw-semibold">Quote Validity Until</span>
                <span class="fw-bold text-dark" id="viewDealValidUntil">-</span>
            </div>
            <div class="col-12 mt-2">
                <span class="text-muted small d-block fw-semibold mb-1">Pipeline Stage Completion</span>
                <div class="progress" style="height: 22px; border-radius: 8px;">
                    <div class="progress-bar bg-primary fw-bold small" id="viewDealProgressBar" style="width: 17%;">17%</div>
                </div>
            </div>
        </div>

        <!-- Line Items Breakdown Section in View -->
        <div class="card border shadow-none rounded-3 mb-4">
            <div class="card-header bg-light py-3">
                <h6 class="m-0 fw-bold text-dark"><i data-lucide="package" class="lucide-icon me-1 text-primary"></i> Line Items / Products Breakdown</h6>
            </div>
            <div class="card-body p-3">
                <div id="viewDualCurrencyInfo" class="alert alert-info py-2 px-3 mb-3 small d-none">
                    <i data-lucide="banknote" class="lucide-icon me-1"></i> Dual Currency Mode Enabled (Secondary Currency Converted Rates)
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" id="viewItemsTable" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Product Name</th>
                                <th>Details</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                <td class="text-end fw-bold" id="viewSubtotalDisplay">$0.00</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end fw-bold text-muted">VAT (7.5%):</td>
                                <td class="text-end text-muted" id="viewVatDisplay">$0.00</td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="4" class="text-end fw-bold text-primary fs-6">Grand Total (Inc. VAT):</td>
                                <td class="text-end fw-bold text-primary fs-6" id="viewTotalDisplay">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <h6 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Stage Notes / Progress Comments</h6>
            <div class="p-3 bg-light rounded-3 text-muted small" id="viewDealStageNotes">No stage notes recorded.</div>
        </div>

        <div class="mb-3">
            <h6 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Description / Scope of Work</h6>
            <div class="p-3 bg-light rounded-3 text-muted small" id="viewDealDescription">No description provided.</div>
        </div>

        <div class="mb-3">
            <h6 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">Terms & Conditions</h6>
            <div class="p-3 bg-light rounded-3 text-muted small" id="viewDealTerms">Standard payment and delivery terms.</div>
        </div>
    </div>
    <div class="offcanvas-footer gap-2 d-flex justify-content-between border-top p-3">
        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="offcanvas">Close</button>
        <?php if (hasPermission('edit_deals')): ?>
            <button type="button" class="btn btn-primary rounded-pill px-4" id="viewEditTriggerBtn" onclick="triggerEditFromView()"><i data-lucide="edit-3" class="lucide-icon me-1"></i> Edit Opportunity</button>
        <?php endif; ?>
    </div>
</div>

<!-- EXCHANGE RATE MODAL -->
<div class="modal fade" id="exchangeRateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-dark"><i data-lucide="refresh-cw" class="lucide-icon me-2 text-primary"></i> Exchange Rate Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">Custom conversion rates for this specific opportunity:</p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">USD to NGN Rate</label>
                    <input type="number" class="form-control" id="rateNGN" step="0.01" min="0" placeholder="1550.00">
                    <small class="text-muted">1 USD = ? NGN</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">USD to GBP Rate</label>
                    <input type="number" class="form-control" id="rateGBP" step="0.0001" min="0" placeholder="0.79">
                    <small class="text-muted">1 USD = ? GBP</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">USD to EUR Rate</label>
                    <input type="number" class="form-control" id="rateEUR" step="0.0001" min="0" placeholder="0.92">
                    <small class="text-muted">1 USD = ? EUR</small>
                </div>
            </div>
            <div class="modal-footer border-top gap-2">
                <button type="button" class="btn btn-light rounded-pill px-3" onclick="resetRatesToDefault()">Reset Defaults</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="applyCustomRates()" data-bs-dismiss="modal">Apply Custom Rates</button>
            </div>
        </div>
    </div>
</div>

<!-- PRINT DOCUMENT MODAL -->
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-dark"><i data-lucide="printer" class="lucide-icon me-2 text-primary"></i> Print Quotation / Business Proposal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row align-items-center mb-3 g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Select Document Type</label>
                        <select class="form-select" id="docType" onchange="updatePrintPreview()">
                            <option value="quote" selected>Official Quotation</option>
                            <option value="proposal">Business Proposal</option>
                            <option value="invoice">Commercial Invoice</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <button type="button" class="btn btn-success rounded-pill px-4" onclick="printDocument()">
                            <i data-lucide="printer" class="lucide-icon me-1"></i> Print / Save as PDF
                        </button>
                    </div>
                </div>
                <hr>
                <div id="printPreview" class="border rounded-3 p-4 bg-white shadow-sm" style="min-height: 450px;">
                    <!-- Live generated preview -->
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
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

const cachedProducts = <?php echo json_encode($products ?? []); ?>;

// Currency rates loaded from DB — keyed by currency code
const dbCurrencyRates = <?php echo json_encode(array_column($dbCurrencies, null, 'code')); ?>;

let currentSymbol = '$';
let currentPrimaryCode = 'USD';
let usingCustomRates = false;
let currentDealData = null;

// Per-deal override rate (set when editing a saved deal that has its own exchange_rate)
// This takes precedence over the DB default for that deal session only.
let dealOverrideRate = null;

const stageOrder = ['qualification', 'value_proposition', 'proposal_quote', 'negotiation_review', 'closed_won', 'closed_lost'];
const stageProgressMap = {
    'qualification': 17,
    'value_proposition': 33,
    'proposal_quote': 50,
    'negotiation_review': 75,
    'closed_won': 100,
    'closed_lost': 0
};

document.addEventListener('DOMContentLoaded', function() {
    updateCurrencySymbol();
    updateStageProgress();
});

function addItem() {
    const tbody = document.querySelector('#itemsTable tbody');
    if (!tbody) return;
    
    const tr = document.createElement('tr');
    const isDualCurrency = document.getElementById('dualCurrencyCheck') ? document.getElementById('dualCurrencyCheck').checked : false;
    const secondaryDisplayClass = isDualCurrency ? '' : 'd-none';
    
    const productOptions = cachedProducts.map(product => {
        const code = product.code || ''; 
        const displayName = code ? `${product.name} (${code})` : product.name;
        return `<option value="${product.id}" data-name="${escapeHtml(product.name)}" data-price="${product.price}" data-description="${escapeHtml(product.description || '')}">${escapeHtml(displayName)}</option>`;
    }).join('');

    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm product-select" onchange="selectProductFromDropdown(this)" required>
                <option value="">-- Select Product --</option>
                ${productOptions}
            </select>
            <input type="hidden" class="product-name-hidden">
        </td>
        <td><textarea class="form-control form-control-sm product-details" rows="2" readonly style="resize: none; background-color: #f8f9fa; font-size: 0.8rem;"></textarea></td>
        <td><input type="number" class="form-control form-control-sm qty-input" value="1" min="1" step="1" onchange="calculateRow(this)" onkeyup="calculateRow(this)"></td>
        <td><input type="number" class="form-control form-control-sm product-price" step="0.01" onchange="calculateRow(this)" onkeyup="calculateRow(this)" style="background-color: #ffffff;"></td>
        <td><input type="text" class="form-control form-control-sm total-input fw-semibold" readonly style="background-color: #f8f9fa;"></td>
        <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control form-control-sm sec-unit-price" readonly style="background-color: #f8f9fa;"></td>
        <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control form-control-sm sec-total fw-semibold" readonly style="background-color: #f8f9fa;"></td>
        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-xs p-1" onclick="this.closest('tr').remove(); calculateTotals()"><i data-lucide="trash-2" style="width: 14px; height: 14px;"></i></button></td>
    `;
    tbody.appendChild(tr);
    if (window.lucide) lucide.createIcons();
}

function addItemWithData(item) {
    const tbody = document.querySelector('#itemsTable tbody');
    if (!tbody) return;
    const tr = document.createElement('tr');
    const isDualCurrency = document.getElementById('dualCurrencyCheck') ? document.getElementById('dualCurrencyCheck').checked : false;
    const secondaryDisplayClass = isDualCurrency ? '' : 'd-none';

    const productOptions = cachedProducts.map(product => {
        const code = product.code || ''; 
        const displayName = code ? `${product.name} (${code})` : product.name;
        const selected = (item.product_id && item.product_id == product.id) || (item.productId && item.productId == product.id) || (item.product_name && item.product_name === product.name) ? 'selected' : '';
        return `<option value="${product.id}" data-name="${escapeHtml(product.name)}" data-price="${product.price}" data-description="${escapeHtml(product.description || '')}" ${selected}>${escapeHtml(displayName)}</option>`;
    }).join('');

    const prodName = item.product_name || item.productName || '';
    const itemDesc = item.description || '';
    const qty = item.quantity || 1;
    const price = parseFloat(item.price || 0).toFixed(2);
    const total = parseFloat(item.total || (qty * price)).toFixed(2);
    
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm product-select" onchange="selectProductFromDropdown(this)">
                <option value="">-- Select Product --</option>
                ${productOptions}
            </select>
            <input type="hidden" class="product-name-hidden" value="${escapeHtml(prodName)}">
        </td>
        <td><textarea class="form-control form-control-sm product-details" rows="2" readonly style="resize: none; background-color: #f8f9fa; font-size: 0.8rem;">${escapeHtml(itemDesc)}</textarea></td>
        <td><input type="number" class="form-control form-control-sm qty-input" value="${qty}" min="1" step="1" onchange="calculateRow(this)" onkeyup="calculateRow(this)"></td>
        <td><input type="number" class="form-control form-control-sm product-price" value="${price}" step="0.01" onchange="calculateRow(this)" onkeyup="calculateRow(this)" style="background-color: #ffffff;"></td>
        <td><input type="text" class="form-control form-control-sm total-input fw-semibold" value="${total}" readonly style="background-color: #f8f9fa;"></td>
        <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control form-control-sm sec-unit-price" readonly style="background-color: #f8f9fa;"></td>
        <td class="secondary-currency-col ${secondaryDisplayClass}"><input type="text" class="form-control form-control-sm sec-total fw-semibold" readonly style="background-color: #f8f9fa;"></td>
        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-xs p-1" onclick="this.closest('tr').remove(); calculateTotals()"><i data-lucide="trash-2" style="width: 14px; height: 14px;"></i></button></td>
    `;
    tbody.appendChild(tr);
    if (window.lucide) lucide.createIcons();
    calculateRow(tr.querySelector('.qty-input'));
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function selectProductFromDropdown(selectElement) {
    const row = selectElement.closest('tr');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    
    if (!selectedOption.value) {
        row.querySelector('.product-name-hidden').value = '';
        row.querySelector('.product-details').value = '';
        row.querySelector('.product-price').value = '';
        row.querySelector('.total-input').value = '0.00';
        calculateTotals();
        return;
    }
    
    const productName = selectedOption.getAttribute('data-name');
    const productPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
    const productDescription = selectedOption.getAttribute('data-description');
    
    row.querySelector('.product-name-hidden').value = productName;
    row.querySelector('.product-details').value = productDescription || '';
    row.querySelector('.product-price').value = productPrice.toFixed(2);
    
    calculateRow(row.querySelector('.product-price'));
}

function updateCurrencySymbol() {
    const select = document.getElementById('dealCurrency');
    if (!select) return;
    currentPrimaryCode = select.value || 'USD';
    
    // Get symbol from DB rates if available, else fallback
    const dbEntry = dbCurrencyRates[currentPrimaryCode];
    currentSymbol = dbEntry ? (dbEntry.symbol || '$') : '$';
    
    const primaryCodeElements = document.querySelectorAll('#primaryCurrencyCode, #primaryCurrencyCodeTotal');
    primaryCodeElements.forEach(el => el.textContent = currentPrimaryCode);
    
    calculateTotals();
}

function toggleDualCurrency() {
    const check = document.getElementById('dualCurrencyCheck');
    const div = document.getElementById('secondaryCurrencyDiv');
    const rateEditor = document.getElementById('dealRateEditorDiv');
    const secondaryCols = document.querySelectorAll('.secondary-currency-col');
    
    if (check && check.checked) {
        if (div) div.classList.remove('d-none');
        if (rateEditor) rateEditor.classList.remove('d-none');
        secondaryCols.forEach(col => col.classList.remove('d-none'));
        onSecCurrencyChange(); // fill rate input and banner
    } else {
        if (div) div.classList.add('d-none');
        if (rateEditor) rateEditor.classList.add('d-none');
        secondaryCols.forEach(col => col.classList.add('d-none'));
        const rateDisplay = document.getElementById('defaultRateDisplay');
        const rateStatus = document.getElementById('rateStatus');
        if (rateDisplay) rateDisplay.textContent = 'Dual currency off — enable above to see rates';
        if (rateStatus) rateStatus.textContent = '—';
    }
    calculateTotals();
}

// Called when secondary currency dropdown changes — clears override and fills rate input from DB
function onSecCurrencyChange() {
    const sel = document.getElementById('secCurrencySelect');
    if (!sel || sel.selectedIndex < 0) return;
    
    // Clear deal-specific override when user switches currency
    dealOverrideRate = null;
    const badge = document.getElementById('dealRateBadge');
    if (badge) badge.style.display = 'none';
    
    const opt = sel.options[sel.selectedIndex];
    const code = opt.value;
    const rate = parseFloat(opt.getAttribute('data-rate')) || 1.0;
    const sym = opt.getAttribute('data-symbol') || code;
    const name = opt.getAttribute('data-name') || code;
    
    // Fill the rate input with the DB rate for this currency
    const rateInput = document.getElementById('dealSpecificRateInput');
    if (rateInput) rateInput.value = rate.toFixed(4);
    
    // Update the 'from code' label and secondary labels
    const fromCode = document.getElementById('rateFromCode');
    const secSym = document.getElementById('rateSecSymbol');
    const secCode = document.getElementById('rateSecCode');
    if (fromCode) fromCode.textContent = currentPrimaryCode;
    if (secSym) secSym.textContent = sym;
    if (secCode) secCode.textContent = code;
    
    // Update the rate banner
    updateRateBanner(rate, sym, name, 'Live DB Rate');
    
    // Update column header labels
    document.querySelectorAll('.sec-currency-code').forEach(el => el.textContent = code);
    
    calculateTotals();
}

// Called live as user types into the deal-specific rate input
function applyDealSpecificRate() {
    const rateInput = document.getElementById('dealSpecificRateInput');
    const rate = parseFloat(rateInput ? rateInput.value : 0);
    if (!rate || rate <= 0) return;
    
    // Store as the active override for this deal
    dealOverrideRate = rate;
    
    // Update the selected option's data-rate so updateSecondaryCurrencyTotal uses it
    const sel = document.getElementById('secCurrencySelect');
    if (sel && sel.selectedIndex >= 0) {
        const opt = sel.options[sel.selectedIndex];
        opt.setAttribute('data-rate', rate);
        const sym = opt.getAttribute('data-symbol') || opt.value;
        const name = opt.getAttribute('data-name') || opt.value;
        updateRateBanner(rate, sym, name, 'Deal-Specific Rate');
    }
    
    // Show 'Deal-Specific Rate Active' badge
    const badge = document.getElementById('dealRateBadge');
    if (badge) badge.style.display = '';
    
    calculateTotals();
}

// Reset the rate input back to the DB default for the selected currency
function resetToDbRate() {
    const sel = document.getElementById('secCurrencySelect');
    if (!sel || sel.selectedIndex < 0) return;
    
    const opt = sel.options[sel.selectedIndex];
    const dbRate = parseFloat(opt.getAttribute('data-db-rate')) || 1.0;
    const sym = opt.getAttribute('data-symbol') || opt.value;
    const name = opt.getAttribute('data-name') || opt.value;
    
    // Restore data-rate to DB value and clear override
    opt.setAttribute('data-rate', dbRate);
    dealOverrideRate = null;
    
    const rateInput = document.getElementById('dealSpecificRateInput');
    if (rateInput) rateInput.value = dbRate.toFixed(4);
    
    updateRateBanner(dbRate, sym, name, 'Live DB Rate');
    
    const badge = document.getElementById('dealRateBadge');
    if (badge) badge.style.display = 'none';
    
    calculateTotals();
}

// Helper: update the rate banner text
function updateRateBanner(rate, sym, name, label) {
    const rateDisplay = document.getElementById('defaultRateDisplay');
    const rateStatus = document.getElementById('rateStatus');
    if (rateDisplay) rateDisplay.textContent = `1 ${currentPrimaryCode} = ${sym}${rate.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 4})}`;
    if (rateStatus) rateStatus.textContent = `${label} • ${name}`;
}

function calculateRow(input) {
    if (!input) return;
    const tr = input.closest('tr');
    if (!tr) return;
    const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
    const price = parseFloat(tr.querySelector('.product-price').value) || 0;
    const total = qty * price;
    tr.querySelector('.total-input').value = total.toFixed(2);
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
        const totalInput = tr.querySelector('.total-input');
        if (totalInput) {
            subtotal += parseFloat(totalInput.value) || 0;
        }
    });
    
    const vat = subtotal * 0.075;
    const total = subtotal + vat;
    
    const subEl = document.getElementById('subtotalDisplay');
    const vatEl = document.getElementById('vatDisplay');
    const totEl = document.getElementById('totalDisplay');
    const dealValInput = document.getElementById('dealValue');

    if (subEl) subEl.innerText = currentSymbol + ' ' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (vatEl) vatEl.innerText = currentSymbol + ' ' + vat.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (totEl) totEl.innerText = currentSymbol + ' ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    if (dealValInput) {
        dealValInput.value = total.toFixed(2);
    }
    
    const dualCurrencyCheck = document.getElementById('dualCurrencyCheck');
    if (dualCurrencyCheck && dualCurrencyCheck.checked) {
        updateSecondaryCurrencyTotal(subtotal, vat, total);
    }
}

function updateSecondaryCurrencyTotal(primarySubtotal, primaryVat, primaryTotal) {
    const secCurrencySelect = document.getElementById('secCurrencySelect');
    if (!secCurrencySelect || secCurrencySelect.selectedIndex < 0) return;

    const opt = secCurrencySelect.options[secCurrencySelect.selectedIndex];
    const selectedCode = opt.value;
    // Rate from DB (stored in data-rate attribute)
    const conversionRate = parseFloat(opt.getAttribute('data-rate')) || 1.0;
    const currencySymbol = opt.getAttribute('data-symbol') || selectedCode;

    document.querySelectorAll('.sec-currency-code').forEach(el => el.textContent = selectedCode);

    // Update per-row secondary values
    document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
        const primaryPriceInput = tr.querySelector('.product-price');
        const primaryTotalInput = tr.querySelector('.total-input');
        const secUnitPriceInput = tr.querySelector('.sec-unit-price');
        const secTotalInput = tr.querySelector('.sec-total');
        
        if (primaryPriceInput && primaryTotalInput) {
            const primaryPrice = parseFloat(primaryPriceInput.value) || 0;
            const primaryTotal = parseFloat(primaryTotalInput.value) || 0;
            
            const secondaryUnitPrice = primaryPrice * conversionRate;
            const secondaryTotal = primaryTotal * conversionRate;
            
            if (secUnitPriceInput) {
                secUnitPriceInput.value = currencySymbol + ' ' + secondaryUnitPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            if (secTotalInput) {
                secTotalInput.value = currencySymbol + ' ' + secondaryTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }
    });
    
    const secondarySubtotal = primarySubtotal * conversionRate;
    const secondaryVat = primaryVat * conversionRate;
    const secondaryTotal = primaryTotal * conversionRate;
    
    const secSubEl = document.getElementById('secondarySubtotalDisplay');
    const secVatEl = document.getElementById('secondaryVatDisplay');
    const secTotalEl = document.getElementById('secondaryTotalDisplay');

    if (secSubEl) secSubEl.textContent = currencySymbol + ' ' + secondarySubtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (secVatEl) secVatEl.textContent = currencySymbol + ' ' + secondaryVat.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (secTotalEl) secTotalEl.textContent = currencySymbol + ' ' + secondaryTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function openExchangeRateModal() {
    document.getElementById('rateGBP').value = currencyRates.gbp;
    document.getElementById('rateNGN').value = currencyRates.ngn;
    document.getElementById('rateEUR').value = currencyRates.eur;
    
    const modal = new bootstrap.Modal(document.getElementById('exchangeRateModal'));
    modal.show();
}

function applyCustomRates() {
    const gbp = parseFloat(document.getElementById('rateGBP').value);
    const ngn = parseFloat(document.getElementById('rateNGN').value);
    const eur = parseFloat(document.getElementById('rateEUR').value);
    
    let updated = false;
    if (!isNaN(gbp) && gbp > 0) { currencyRates.gbp = gbp; updated = true; }
    if (!isNaN(ngn) && ngn > 0) { currencyRates.ngn = ngn; updated = true; }
    if (!isNaN(eur) && eur > 0) { currencyRates.eur = eur; updated = true; }
    
    if (updated) {
        usingCustomRates = true;
        updateRateIndicator();
        calculateTotals();
    }
}

function resetRatesToDefault() {
    currencyRates.gbp = defaultRates.gbp;
    currencyRates.ngn = defaultRates.ngn;
    currencyRates.eur = defaultRates.eur;
    
    document.getElementById('rateGBP').value = defaultRates.gbp;
    document.getElementById('rateNGN').value = defaultRates.ngn;
    document.getElementById('rateEUR').value = defaultRates.eur;
    
    usingCustomRates = false;
    updateRateIndicator();
    calculateTotals();
}

function updateRateIndicator() {
    const indicator = document.getElementById('customRateIndicator');
    const rateDisplay = document.getElementById('defaultRateDisplay');
    const rateStatus = document.getElementById('rateStatus');
    
    if (usingCustomRates) {
        if (indicator) indicator.classList.remove('d-none');
        if (rateStatus) rateStatus.textContent = 'Custom Rate';
    } else {
        if (indicator) indicator.classList.add('d-none');
        if (rateStatus) rateStatus.textContent = 'Default Rate';
    }
    
    if (rateDisplay) {
        rateDisplay.textContent = currencyRates.ngn.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

function updateStageProgress() {
    const stageSelect = document.getElementById('dealStage');
    if (!stageSelect) return;
    const stage = stageSelect.value;
    const progress = stageProgressMap[stage] !== undefined ? stageProgressMap[stage] : 17;
    const progressBar = document.getElementById('stageProgressBar');
    if (progressBar) {
        progressBar.style.width = progress + '%';
        progressBar.textContent = progress + '%';
        progressBar.className = 'progress-bar fw-bold small';
        if (stage === 'closed_won') progressBar.classList.add('bg-success');
        else if (stage === 'closed_lost') progressBar.classList.add('bg-danger');
        else progressBar.classList.add('bg-primary');
    }
}

function openPrintModal() {
    const dealIdInput = document.getElementById('dealId');
    const dealId = dealIdInput ? dealIdInput.value : (currentViewDealId || 0);
    const docTypeSelect = document.getElementById('docType');
    const type = docTypeSelect ? docTypeSelect.value : 'quote';

    if (dealId && parseInt(dealId) > 0) {
        window.open('<?php echo BASE_URL; ?>/modules/deals/print.php?id=' + dealId + '&type=' + type, '_blank');
    } else {
        showToast('Please save the deal first before generating printable documents.', 'warning');
    }
}

function updatePrintPreview() {
    const docType = document.getElementById('docType').value;
    const preview = document.getElementById('printPreview');
    
    const title = document.getElementById('dealTitleInput').value || 'Untitled Opportunity';
    const description = document.getElementById('dealDescription').value || 'No description notes provided.';
    const terms = document.getElementById('dealTerms').value || 'Standard payment and delivery terms apply.';
    const validUntil = document.getElementById('dealValidUntil').value || 'Not specified';
    const dueDate = document.getElementById('dealDueDate').value || 'Not specified';
    const totalDisplay = document.getElementById('totalDisplay').textContent;
    const subtotalDisplay = document.getElementById('subtotalDisplay').textContent;
    const vatDisplay = document.getElementById('vatDisplay').textContent;
    
    // Customer Info
    const custSelect = document.getElementById('dealCustomerId');
    let custName = 'Valued Client';
    if (custSelect && custSelect.selectedIndex > 0) {
        custName = custSelect.options[custSelect.selectedIndex].text;
    }
    
    let lineItemsHTML = '';
    document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
        const prodSelect = tr.querySelector('.product-select');
        const prodName = tr.querySelector('.product-name-hidden').value || (prodSelect && prodSelect.selectedIndex > 0 ? prodSelect.options[prodSelect.selectedIndex].text : 'N/A');
        const qty = tr.querySelector('.qty-input').value || 0;
        const price = tr.querySelector('.product-price').value || 0;
        const lineTotal = tr.querySelector('.total-input').value || 0;
        
        if (prodName && prodName !== 'N/A') {
            lineItemsHTML += `
                <tr>
                    <td style="padding: 10px; border: 1px solid #dee2e6;">${escapeHtml(prodName)}</td>
                    <td style="padding: 10px; text-align: center; border: 1px solid #dee2e6;">${qty}</td>
                    <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6;">${currentSymbol}${parseFloat(price).toLocaleString('en-US', {minimumFractionDigits:2})}</td>
                    <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6;"><strong>${currentSymbol}${parseFloat(lineTotal).toLocaleString('en-US', {minimumFractionDigits:2})}</strong></td>
                </tr>
            `;
        }
    });

    let documentTitle = 'OFFICIAL QUOTATION';
    let documentIntro = 'Please find below our formal commercial quotation for your requirements:';
    let documentColor = '#2563eb';
    
    if (docType === 'proposal') {
        documentTitle = 'BUSINESS PROPOSAL';
        documentIntro = 'We are pleased to submit this business proposal for your consideration:';
        documentColor = '#059669';
    } else if (docType === 'invoice') {
        documentTitle = 'COMMERCIAL INVOICE';
        documentIntro = 'Please find below the invoice for products and services provided:';
        documentColor = '#dc2626';
    }

    const currentDate = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

    preview.innerHTML = `
        <div style="max-width: 800px; margin: 0 auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid ${documentColor}; padding-bottom: 15px; margin-bottom: 25px;">
                <div>
                    <h2 style="margin: 0; color: ${documentColor}; font-weight: 700; text-transform: uppercase;">${documentTitle}</h2>
                    <span style="color: #666; font-size: 0.9rem;">Customer: <strong>${escapeHtml(custName)}</strong></span>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 0.85rem; color: #666; display: block;">Date: ${currentDate}</span>
                    <span style="font-size: 0.85rem; color: #666; display: block;">Valid Until: ${validUntil}</span>
                </div>
            </div>
            
            <div style="background-color: #f8fafc; padding: 15px; border-radius: 8px; border-left: 4px solid ${documentColor}; margin-bottom: 25px;">
                <h4 style="margin: 0 0 8px 0; color: #1e293b;">${escapeHtml(title)}</h4>
                <p style="margin: 0; color: #475569; font-size: 0.9rem;">${escapeHtml(documentIntro)}</p>
            </div>
            
            ${lineItemsHTML ? `
            <div style="margin-bottom: 25px;">
                <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                    <thead>
                        <tr style="background-color: ${documentColor}; color: white; font-size: 0.85rem;">
                            <th style="padding: 10px; text-align: left; border: 1px solid ${documentColor};">Item / Product</th>
                            <th style="padding: 10px; text-align: center; border: 1px solid ${documentColor}; width: 80px;">Qty</th>
                            <th style="padding: 10px; text-align: right; border: 1px solid ${documentColor}; width: 130px;">Unit Price</th>
                            <th style="padding: 10px; text-align: right; border: 1px solid ${documentColor}; width: 130px;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.85rem;">
                        ${lineItemsHTML}
                    </tbody>
                    <tfoot style="font-size: 0.9rem;">
                        <tr>
                            <td colspan="3" style="padding: 10px; text-align: right; border: 1px solid #dee2e6;"><strong>Subtotal:</strong></td>
                            <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6;"><strong>${subtotalDisplay}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="padding: 10px; text-align: right; border: 1px solid #dee2e6; color: #64748b;">VAT (7.5%):</td>
                            <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6; color: #64748b;">${vatDisplay}</td>
                        </tr>
                        <tr style="background-color: #f1f5f9;">
                            <td colspan="3" style="padding: 10px; text-align: right; border: 1px solid #cbd5e1; font-weight: 700;">TOTAL (INC. VAT):</td>
                            <td style="padding: 10px; text-align: right; border: 1px solid #cbd5e1; font-weight: 700; color: ${documentColor}; font-size: 1rem;">${totalDisplay}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            ` : '<div class="alert alert-warning mb-4">No line items added to this opportunity.</div>'}
            
            <div style="margin-bottom: 20px;">
                <h5 style="margin: 0 0 5px 0; color: #334155; font-size: 0.95rem;">Scope & Terms</h5>
                <p style="margin: 0; color: #64748b; font-size: 0.85rem; white-space: pre-wrap;">${escapeHtml(description)}</p>
                <p style="margin: 10px 0 0 0; color: #64748b; font-size: 0.85rem; white-space: pre-wrap;"><strong>Terms:</strong> ${escapeHtml(terms)}</p>
            </div>
        </div>
    `;
}

function printDocument() {
    try {
        const printContents = document.getElementById('printPreview').innerHTML;
        const printWindow = window.open('', '_blank', 'width=950,height=750');
        if (!printWindow) {
            showToast('Print popup was blocked by browser. Please allow popups.', 'warning');
            return;
        }
        printWindow.document.write(`
            <html>
                <head>
                    <title>Print Document</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                </head>
                <body onload="window.print(); window.close();" style="padding: 40px;">
                    ${printContents}
                </body>
            </html>
        `);
        printWindow.document.close();
    } catch(e) {
        console.error('Print Error:', e);
    }
}

function resetDealForm() {
    document.getElementById('dealForm').reset();
    document.getElementById('dealId').value = '';
    document.getElementById('dealOffcanvasTitle').textContent = 'New Opportunity / Deal';
    document.querySelector('#itemsTable tbody').innerHTML = '';
    dealOverrideRate = null;
    
    // Reset rate input and badge
    const rateInput = document.getElementById('dealSpecificRateInput');
    const badge = document.getElementById('dealRateBadge');
    if (badge) badge.style.display = 'none';
    
    // Restore DB rates on all options
    const secSel = document.getElementById('secCurrencySelect');
    if (secSel) {
        Array.from(secSel.options).forEach(opt => {
            const dbRate = opt.getAttribute('data-db-rate');
            if (dbRate) opt.setAttribute('data-rate', dbRate);
        });
        if (secSel.selectedIndex >= 0 && rateInput) {
            rateInput.value = parseFloat(secSel.options[secSel.selectedIndex].getAttribute('data-db-rate') || 1).toFixed(4);
        }
    }
    
    // Reset rate banner
    const rateDisplay = document.getElementById('defaultRateDisplay');
    const rateStatus = document.getElementById('rateStatus');
    if (rateDisplay) rateDisplay.textContent = 'Dual currency off — enable above to see rates';
    if (rateStatus) rateStatus.textContent = '—';
    
    addItem();
    updateCurrencySymbol();
    updateStageProgress();
}

function saveDeal(e) {
    e.preventDefault();
    
    const lineItems = [];
    document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
        const select = tr.querySelector('.product-select');
        const prodId = select ? select.value : null;
        const prodName = tr.querySelector('.product-name-hidden').value;
        const desc = tr.querySelector('.product-details').value;
        const qty = tr.querySelector('.qty-input').value;
        const price = tr.querySelector('.product-price').value;
        const total = tr.querySelector('.total-input').value;
        
        if (prodName || desc || qty || price) {
            lineItems.push({
                productId: prodId,
                productName: prodName,
                description: desc,
                quantity: qty,
                price: price,
                total: total
            });
        }
    });

    const isDual = document.getElementById('dualCurrencyCheck') ? (document.getElementById('dualCurrencyCheck').checked ? 1 : 0) : 0;
    const secCurrSelect = document.getElementById('secCurrencySelect');
    const secCurr = secCurrSelect ? secCurrSelect.value : 'NGN';
    
    let rate = 1.0;
    const secCurrOpt = secCurrSelect ? secCurrSelect.options[secCurrSelect.selectedIndex] : null;
    if (secCurrOpt) {
        // Use dealOverrideRate if set (deal-specific), otherwise read from option's data-rate (DB default)
        rate = dealOverrideRate !== null ? dealOverrideRate : (parseFloat(secCurrOpt.getAttribute('data-rate')) || 1.0);
    }

    const data = {
        id: document.getElementById('dealId').value || null,
        title: document.getElementById('dealTitleInput').value,
        customer_id: document.getElementById('dealCustomerId').value || null,
        lead_id: document.getElementById('dealLeadId').value || null,
        value: document.getElementById('dealValue').value || 0,
        currency: document.getElementById('dealCurrency').value || 'USD',
        stage: document.getElementById('dealStage').value || 'qualification',
        due_date: document.getElementById('dealDueDate').value || null,
        valid_until: document.getElementById('dealValidUntil').value || null,
        assigned_to: document.getElementById('dealAssignedTo').value || null,
        description: document.getElementById('dealDescription').value || '',
        terms_conditions: document.getElementById('dealTerms').value || '',
        stage_notes: document.getElementById('dealStageNotes').value || '',
        is_dual_currency: isDual,
        exchange_rate: rate,
        lineItems: lineItems
    };

    fetch('<?php echo BASE_URL; ?>/ajax/deals/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast(res.message || 'Deal saved successfully', 'success');
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(res.message || 'Save failed', 'danger');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error processing deal save request', 'danger');
    });
}

function editDeal(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/deals/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const d = res.data;
            if (d.stage === 'closed_won' || d.stage === 'closed_lost') {
                showToast('Closed deals (Won or Lost) cannot be edited.', 'warning');
                return;
            }
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
            document.getElementById('dealStageNotes').value = d.stage_notes || '';
            
            const dualCheck = document.getElementById('dualCurrencyCheck');
            if (dualCheck) {
                dualCheck.checked = (parseInt(d.is_dual_currency) === 1);
                toggleDualCurrency();
            }
            
            // Deal-specific exchange rate: loaded from saved deal, displayed in rate input
            if (d.exchange_rate && parseFloat(d.exchange_rate) > 0) {
                dealOverrideRate = parseFloat(d.exchange_rate);
                
                // Fill the rate input with the deal's own rate
                const rateInput = document.getElementById('dealSpecificRateInput');
                if (rateInput) rateInput.value = dealOverrideRate.toFixed(4);
                
                // Apply the override to the currently selected secondary currency option
                const secSel = document.getElementById('secCurrencySelect');
                if (secSel && secSel.selectedIndex >= 0) {
                    const opt = secSel.options[secSel.selectedIndex];
                    opt.setAttribute('data-rate', dealOverrideRate);
                    const sym = opt.getAttribute('data-symbol') || opt.value;
                    const name = opt.getAttribute('data-name') || opt.value;
                    updateRateBanner(dealOverrideRate, sym, name, 'Deal-Specific Rate');
                    
                    // Also update secondary labels
                    const secSym = document.getElementById('rateSecSymbol');
                    const secCode = document.getElementById('rateSecCode');
                    const fromCode = document.getElementById('rateFromCode');
                    if (secSym) secSym.textContent = sym;
                    if (secCode) secCode.textContent = opt.value;
                    if (fromCode) fromCode.textContent = d.currency || 'USD';
                }
                
                // Show the 'Deal-Specific Rate Active' badge
                const badge = document.getElementById('dealRateBadge');
                if (badge) badge.style.display = '';
            } else {
                dealOverrideRate = null;
                const badge = document.getElementById('dealRateBadge');
                if (badge) badge.style.display = 'none';
            }

            // Populate Line Items Table
            const tbody = document.querySelector('#itemsTable tbody');
            tbody.innerHTML = '';
            
            if (d.lineItems && d.lineItems.length > 0) {
                d.lineItems.forEach(item => addItemWithData(item));
            } else {
                addItem();
            }
            
            updateCurrencySymbol();
            updateStageProgress();

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

async function archiveDeal(id) {
    const confirmed = await window.appConfirm({
        title: 'Archive Opportunity',
        message: 'Are you sure you want to archive this deal? It will be removed from your active pipeline view.',
        confirmText: 'Yes, Archive Deal',
        cancelText: 'Cancel',
        isDanger: true
    });

    if (confirmed) {
        fetch('<?php echo BASE_URL; ?>/ajax/deals/archive', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                showToast(res.message || 'Deal archived successfully', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                showToast(res.message || 'Failed to archive deal', 'danger');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Server error archiving deal', 'danger');
        });
    }
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

let currentViewDealId = null;

function triggerEditFromView() {
    if (!currentViewDealId) return;
    const viewCanvasEl = document.getElementById('dealViewOffcanvas');
    const viewCanvas = bootstrap.Offcanvas.getInstance(viewCanvasEl);
    if (viewCanvas) viewCanvas.hide();
    setTimeout(() => editDeal(currentViewDealId), 350);
}

function viewDealModal(id) {
    currentViewDealId = id;
    fetch('<?php echo BASE_URL; ?>/ajax/deals/get?id=' + id)
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            const d = res.data;
            currentDealData = d;
            
            document.getElementById('viewDealQuoteNum').textContent = d.quote_number || 'QT-GENERIC';
            document.getElementById('viewDealTitle').textContent = d.title;
            
            const stageTitle = d.stage ? d.stage.replace('_', ' ').toUpperCase() : 'QUALIFICATION';
            document.getElementById('viewDealStageBadge').textContent = stageTitle;
            
            const curr = (d.currency || 'USD').toUpperCase();
            const symbol = (curr.includes('NGN') || curr.includes('₦')) ? '₦' : '$';
            const val = parseFloat(d.value || 0);

            document.getElementById('viewDealValue').textContent = symbol + ' ' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + curr + ')';
            document.getElementById('viewDealDueDate').textContent = d.due_date || '-';
            document.getElementById('viewDealValidUntil').textContent = d.valid_until || '-';
            
            document.getElementById('viewDealCustomer').textContent = d.customer_name || d.customer_company || '-';
            document.getElementById('viewDealLead').textContent = d.lead_name || d.lead_company || '-';
            document.getElementById('viewDealAssignedTo').textContent = d.assigned_to_name || 'Unassigned';
            
            const progress = stageProgressMap[d.stage] !== undefined ? stageProgressMap[d.stage] : 17;
            const pBar = document.getElementById('viewDealProgressBar');
            if (pBar) {
                pBar.style.width = progress + '%';
                pBar.textContent = progress + '%';
            }

            // Populate Line Items Breakdown Table
            const tbody = document.querySelector('#viewItemsTable tbody');
            tbody.innerHTML = '';
            
            let subtotal = 0;
            if (d.lineItems && d.lineItems.length > 0) {
                d.lineItems.forEach(item => {
                    const prodName = item.product_name || item.productName || 'Product Item';
                    const itemDesc = item.description || '';
                    const qty = parseFloat(item.quantity || 1);
                    const price = parseFloat(item.price || 0);
                    const total = parseFloat(item.total || (qty * price));
                    subtotal += total;
                    
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="fw-semibold text-dark">${escapeHtml(prodName)}</td>
                        <td class="text-muted small">${escapeHtml(itemDesc)}</td>
                        <td class="text-center">${qty}</td>
                        <td class="text-end">${symbol} ${price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                        <td class="text-end fw-bold">${symbol} ${total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-3">No line items breakdown recorded for this opportunity.</td></tr>`;
            }
            
            const vat = subtotal * 0.075;
            const grandTotal = subtotal + vat;
            
            document.getElementById('viewSubtotalDisplay').textContent = symbol + ' ' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('viewVatDisplay').textContent = symbol + ' ' + vat.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('viewTotalDisplay').textContent = symbol + ' ' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            const dualInfo = document.getElementById('viewDualCurrencyInfo');
            if (dualInfo) {
                if (parseInt(d.is_dual_currency) === 1) dualInfo.classList.remove('d-none');
                else dualInfo.classList.add('d-none');
            }

            const viewPrintBtn = document.getElementById('viewPrintBtn');
            if (viewPrintBtn) {
                viewPrintBtn.href = '<?php echo BASE_URL; ?>/modules/deals/print.php?id=' + id + '&type=quote';
            }

            document.getElementById('viewDealStageNotes').textContent = d.stage_notes || 'No stage notes recorded.';
            document.getElementById('viewDealDescription').textContent = d.description || 'No description notes provided.';
            document.getElementById('viewDealTerms').textContent = d.terms_conditions || 'Standard payment and delivery terms apply.';
            
            const isClosed = (d.stage === 'closed_won' || d.stage === 'closed_lost');
            const editBtn = document.getElementById('viewEditTriggerBtn');
            if (editBtn) {
                if (isClosed) {
                    editBtn.classList.add('d-none');
                } else {
                    editBtn.classList.remove('d-none');
                }
            }

            new bootstrap.Offcanvas(document.getElementById('dealViewOffcanvas')).show();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
