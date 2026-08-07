<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/utils.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requirePermission('view_dashboard');

require_once __DIR__ . '/includes/header.php';

// Fetch Summary Metrics
$totalLeads = $pdo->query("SELECT COUNT(*) FROM leads WHERE is_archived = 0")->fetchColumn();
$dealsWon = $pdo->query("SELECT COUNT(*) FROM deals WHERE stage = 'closed_won' AND is_archived = 0")->fetchColumn();
$pendingTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND is_archived = 0")->fetchColumn();

// Native creation currency separation for Won Revenue
$wonUsdRevenue = $pdo->query("SELECT SUM(value) FROM deals WHERE stage = 'closed_won' AND is_archived = 0 AND (UPPER(currency) = 'USD' OR currency = '$')")->fetchColumn() ?: 0;
$wonNgnRevenue = $pdo->query("SELECT SUM(value) FROM deals WHERE stage = 'closed_won' AND is_archived = 0 AND (UPPER(currency) LIKE '%NGN%' OR currency = '₦')")->fetchColumn() ?: 0;

// Chart 1: Lead Conversion Funnel Data
$leadFunnelRaw = $pdo->query("SELECT status, COUNT(*) as count FROM leads WHERE is_archived = 0 GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$leadFunnelData = [
    'New Prospects' => $leadFunnelRaw['new'] ?? 0,
    'Contacted' => $leadFunnelRaw['contacted'] ?? 0,
    'Qualified' => $leadFunnelRaw['qualified'] ?? 0,
    'Converted' => $leadFunnelRaw['converted'] ?? 0,
    'Lost' => $leadFunnelRaw['lost'] ?? 0
];

// Chart 2: Deal Pipeline Value by Stage Data
$dealPipelineRaw = $pdo->query("SELECT stage, SUM(value) as total_val FROM deals WHERE is_archived = 0 GROUP BY stage")->fetchAll(PDO::FETCH_KEY_PAIR);
$dealPipelineData = [
    'Qualification' => $dealPipelineRaw['qualification'] ?? 0,
    'Value Prop' => $dealPipelineRaw['value_proposition'] ?? 0,
    'Proposal' => $dealPipelineRaw['proposal_quote'] ?? 0,
    'Negotiation' => $dealPipelineRaw['negotiation_review'] ?? 0,
    'Closed Won' => $dealPipelineRaw['closed_won'] ?? 0,
    'Closed Lost' => $dealPipelineRaw['closed_lost'] ?? 0
];

// Chart 3: Leads by Acquisition Source Data
$leadSourceRows = $pdo->query("SELECT COALESCE(NULLIF(source, ''), 'Direct / Other') as source_name, COUNT(*) as count FROM leads WHERE is_archived = 0 GROUP BY source_name ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);
$leadSourceLabels = array_map(fn($r) => ucfirst($r['source_name']), $leadSourceRows);
$leadSourceCounts = array_column($leadSourceRows, 'count');
if (empty($leadSourceLabels)) {
    $leadSourceLabels = ['Website', 'Referral', 'Social Media', 'Direct / Other'];
    $leadSourceCounts = [0, 0, 0, 0];
}

// Fetch Recent Leads & Deals
$recentLeads = $pdo->query("SELECT display_name, company, status, created_at FROM leads WHERE is_archived = 0 ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recentDeals = $pdo->query("SELECT title, value, stage, currency FROM deals WHERE is_archived = 0 ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Soft Badge Mappings
$leadBadges = [
    'new' => 'badge-soft-warning',
    'contacted' => 'badge-soft-info',
    'qualified' => 'badge-soft-primary',
    'converted' => 'badge-soft-success',
    'lost' => 'badge-soft-danger'
];

$dealBadges = [
    'qualification' => 'badge-soft-info',
    'value_proposition' => 'badge-soft-primary',
    'proposal_quote' => 'badge-soft-warning',
    'negotiation_review' => 'badge-soft-info',
    'closed_won' => 'badge-soft-success',
    'closed_lost' => 'badge-soft-danger'
];
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-0">
    <!-- Hero Banner -->
    <div class="card border-0 shadow-sm bg-white mb-4" style="border-left: 5px solid #12b1b0 !important; border-radius: 14px;">
        <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1 text-dark fw-bold" style="font-family: 'Outfit', sans-serif;">
                    Hello, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>! 👋
                </h4>
                <p class="text-muted mb-0 small">
                    <i data-lucide="calendar" class="lucide-icon me-1"></i> Today is <?php echo date('l, jS F Y'); ?>. Here's your executive pipeline breakdown.
                </p>
            </div>
            <div class="d-none d-md-flex gap-2">
                <?php if (hasPermission('add_leads')): ?>
                    <a href="<?php echo BASE_URL; ?>/leads" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                        <i data-lucide="plus" class="lucide-icon me-1"></i> New Lead
                    </a>
                <?php endif; ?>
                <?php if (hasPermission('view_deals')): ?>
                    <a href="<?php echo BASE_URL; ?>/deals" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                        <i data-lucide="briefcase" class="lucide-icon me-1"></i> Pipeline
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Executive Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Leads', number_format($totalLeads), 'funnel', 'primary', 'Active prospects'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Deals Won', number_format($dealsWon), 'trophy', 'success', 'Closed opportunities'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Pending Tasks', number_format($pendingTasks), 'list-check', 'info', 'Action items'); ?>
        </div>

        <!-- Won Revenue Card with Vertical Line Split for USD and NGN -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase text-muted fw-bold small" style="font-size: 0.75rem;">Won Revenue</span>
                    <div class="rounded-circle bg-light p-2 text-warning d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i data-lucide="trophy" class="lucide-icon text-warning"></i>
                    </div>
                </div>
                
                <!-- Vertical Split Layout for USD and NGN -->
                <div class="row g-0 align-items-center mt-2">
                    <!-- Left Side: USD Won Revenue -->
                    <div class="col-6 pe-2 border-end border-secondary-subtle">
                        <span class="text-muted small fw-medium d-block mb-1" style="font-size: 0.725rem;">USD ($)</span>
                        <span class="fw-bold text-dark d-block text-truncate" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;" title="$<?php echo number_format($wonUsdRevenue, 2); ?>">
                            $<?php echo number_format($wonUsdRevenue, 2); ?>
                        </span>
                    </div>
                    
                    <!-- Right Side: NGN Won Revenue -->
                    <div class="col-6 ps-3">
                        <span class="text-muted small fw-medium d-block mb-1" style="font-size: 0.725rem;">NGN (₦)</span>
                        <span class="fw-bold text-dark d-block text-truncate" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;" title="₦<?php echo number_format($wonNgnRevenue, 2); ?>">
                            ₦<?php echo number_format($wonNgnRevenue, 2); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS SECTION: Lead Funnel, Deal Pipeline, Lead Source -->
    <div class="row g-4 mb-4">
        <!-- 1. Lead Conversion Funnel Chart -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom p-3.5 d-flex align-items-center gap-2">
                    <i data-lucide="filter" class="lucide-icon text-primary"></i>
                    <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Lead Conversion Funnel</h6>
                </div>
                <div class="card-body p-3 d-flex align-items-center justify-content-center">
                    <div style="width: 100%; height: 260px;">
                        <canvas id="leadFunnelChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Deal Pipeline Value Chart -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom p-3.5 d-flex align-items-center gap-2">
                    <i data-lucide="trending-up" class="lucide-icon text-primary"></i>
                    <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Deal Pipeline Value ($)</h6>
                </div>
                <div class="card-body p-3 d-flex align-items-center justify-content-center">
                    <div style="width: 100%; height: 260px;">
                        <canvas id="dealPipelineChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Lead Source Breakdown Chart -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom p-3.5 d-flex align-items-center gap-2">
                    <i data-lucide="pie-chart" class="lucide-icon text-primary"></i>
                    <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Leads by Source</h6>
                </div>
                <div class="card-body p-3 d-flex align-items-center justify-content-center">
                    <div style="width: 100%; height: 260px;">
                        <canvas id="leadSourceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENT ACTIVITY TABLES: Recent Leads & Recent Deals -->
    <div class="row g-4 mb-4">
        <!-- Recent Leads Table -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom p-3.5 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i data-lucide="users" class="lucide-icon text-primary"></i>
                        <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Recent Inbound Leads</h6>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/leads" class="btn btn-sm btn-light border rounded-pill px-3 text-secondary shadow-sm" style="font-size: 0.775rem;">View All Leads</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-family: 'Outfit', sans-serif;">
                            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                <tr>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Lead Name</th>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Company</th>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentLeads)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted small">
                                            <i data-lucide="inbox" class="lucide-icon d-block mb-2 text-muted mx-auto"></i>
                                            No recent leads found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentLeads as $rl): ?>
                                        <?php $badge = $leadBadges[$rl['status']] ?? 'badge-soft-secondary'; ?>
                                        <tr>
                                            <td class="py-3 px-4 fw-semibold text-dark"><?php echo htmlspecialchars($rl['display_name']); ?></td>
                                            <td class="py-3 px-4 text-muted"><?php echo htmlspecialchars($rl['company'] ?: '-'); ?></td>
                                            <td class="py-3 px-4"><span class="badge rounded-pill <?php echo $badge; ?>"><?php echo ucfirst($rl['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Opportunities Table -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom p-3.5 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i data-lucide="briefcase" class="lucide-icon text-primary"></i>
                        <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Recent Opportunities</h6>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/deals" class="btn btn-sm btn-light border rounded-pill px-3 text-secondary shadow-sm" style="font-size: 0.775rem;">View Pipeline</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-family: 'Outfit', sans-serif;">
                            <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                <tr>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Deal Title</th>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Value</th>
                                    <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Stage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentDeals)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted small">
                                            <i data-lucide="inbox" class="lucide-icon d-block mb-2 text-muted mx-auto"></i>
                                            No recent opportunities found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentDeals as $rd): ?>
                                        <?php 
                                            $badge = $dealBadges[$rd['stage']] ?? 'badge-soft-secondary'; 
                                            $currSymbol = (strpos(strtoupper($rd['currency']), 'NGN') !== false || strpos($rd['currency'], '₦') !== false) ? '₦' : '$';
                                        ?>
                                        <tr>
                                            <td class="py-3 px-4 fw-semibold text-dark"><?php echo htmlspecialchars($rd['title']); ?></td>
                                            <td class="py-3 px-4 fw-bold text-primary"><?php echo $currSymbol . ' ' . number_format($rd['value'], 2); ?></td>
                                            <td class="py-3 px-4"><span class="badge rounded-pill <?php echo $badge; ?>"><?php echo ucfirst(str_replace('_', ' ', $rd['stage'])); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fontFamily = "'Outfit', sans-serif";

    // 1. Lead Conversion Funnel Bar Chart
    const ctxFunnel = document.getElementById('leadFunnelChart').getContext('2d');
    new Chart(ctxFunnel, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($leadFunnelData)); ?>,
            datasets: [{
                label: 'Leads Count',
                data: <?php echo json_encode(array_values($leadFunnelData)); ?>,
                backgroundColor: ['#f59e0b', '#06b6d4', '#12b1b0', '#10b981', '#ef4444'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0, font: { family: fontFamily } } },
                x: { ticks: { font: { family: fontFamily, size: 11 } } }
            }
        }
    });

    // 2. Deal Pipeline Value Chart
    const ctxPipeline = document.getElementById('dealPipelineChart').getContext('2d');
    new Chart(ctxPipeline, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($dealPipelineData)); ?>,
            datasets: [{
                label: 'Pipeline Value',
                data: <?php echo json_encode(array_values($dealPipelineData)); ?>,
                backgroundColor: '#12b1b0',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    ticks: { 
                        font: { family: fontFamily },
                        callback: function(value) { return '$' + value.toLocaleString(); }
                    } 
                },
                x: { ticks: { font: { family: fontFamily, size: 10 } } }
            }
        }
    });

    // 3. Lead Source Doughnut Chart
    const ctxSource = document.getElementById('leadSourceChart').getContext('2d');
    new Chart(ctxSource, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($leadSourceLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($leadSourceCounts); ?>,
                backgroundColor: ['#12b1b0', '#3b82f6', '#8b5cf6', '#f59e0b', '#ec4899', '#64748b'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: fontFamily, size: 11 }, boxWidth: 12 } }
            },
            cutout: '68%'
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
