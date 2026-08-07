<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!hasPermission('view_reports') && !hasPermission('view_statistics')) {
    header("Location: " . BASE_URL . "/dashboard");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

// Sales Team Representative Performance Metrics Query (Aggregated Subqueries to prevent Cartesian Product Multiplication)
$performanceStmt = $pdo->query("
    SELECT 
        u.id,
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as rep_name,
        u.email,
        COALESCE(l.total_leads, 0) as total_leads,
        COALESCE(d.total_deals, 0) as total_deals,
        COALESCE(d.won_deals, 0) as won_deals,
        COALESCE(d.won_usd_revenue, 0) as won_usd_revenue,
        COALESCE(d.won_ngn_revenue, 0) as won_ngn_revenue
    FROM users u
    LEFT JOIN (
        SELECT assigned_to, COUNT(*) as total_leads 
        FROM leads 
        WHERE is_archived = 0 AND assigned_to IS NOT NULL 
        GROUP BY assigned_to
    ) l ON l.assigned_to = u.id
    LEFT JOIN (
        SELECT 
            assigned_to,
            COUNT(*) as total_deals,
            COUNT(CASE WHEN stage = 'closed_won' THEN 1 END) as won_deals,
            SUM(CASE WHEN stage = 'closed_won' AND (UPPER(currency) = 'USD' OR currency = '$' OR currency IS NULL OR currency = '') THEN value ELSE 0 END) as won_usd_revenue,
            SUM(CASE WHEN stage = 'closed_won' AND (UPPER(currency) LIKE '%NGN%' OR currency = '₦') THEN value ELSE 0 END) as won_ngn_revenue
        FROM deals
        WHERE is_archived = 0 AND assigned_to IS NOT NULL
        GROUP BY assigned_to
    ) d ON d.assigned_to = u.id
    WHERE u.is_archived = 0
    ORDER BY won_usd_revenue DESC, won_ngn_revenue DESC, won_deals DESC
");
$repPerformance = $performanceStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Summary Statistics
$totalTeamWonUsd = array_sum(array_column($repPerformance, 'won_usd_revenue'));
$totalTeamWonNgn = array_sum(array_column($repPerformance, 'won_ngn_revenue'));

$totalTeamDeals = array_sum(array_column($repPerformance, 'total_deals'));
$totalTeamWonDeals = array_sum(array_column($repPerformance, 'won_deals'));
$avgTeamWinRate = ($totalTeamDeals > 0) ? round(($totalTeamWonDeals / $totalTeamDeals) * 100, 1) : 0;
$topRepName = !empty($repPerformance) ? $repPerformance[0]['rep_name'] : 'N/A';

// Prepare Chart Data
$repNames = array_column($repPerformance, 'rep_name');
$repUsdRevenues = array_column($repPerformance, 'won_usd_revenue');
$repNgnRevenues = array_column($repPerformance, 'won_ngn_revenue');
$repWinRates = array_map(function($r) {
    return ($r['total_deals'] > 0) ? round(($r['won_deals'] / $r['total_deals']) * 100, 1) : 0;
}, $repPerformance);
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-0">
    <!-- Header & Breadcrumb -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Reports', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Sales Team Performance Reports</h2>
            <p class="text-muted small mb-0">Evaluate representative productivity, deal close rates, and revenue contributions natively in USD ($) and NGN (₦).</p>
        </div>
    </div>

    <!-- Executive KPI Strip -->
    <div class="row g-3 mb-4">
        <!-- Closed Revenue Card with Vertical Line Split for USD and NGN -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase text-muted fw-bold small" style="font-size: 0.75rem;">Closed Revenue</span>
                    <div class="rounded-circle bg-light p-2 text-success d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i data-lucide="trophy" class="lucide-icon text-success"></i>
                    </div>
                </div>
                
                <!-- Vertical Split Layout for USD and NGN -->
                <div class="row g-0 align-items-center mt-2">
                    <!-- Left Side: USD Won Revenue -->
                    <div class="col-6 pe-2 border-end border-secondary-subtle">
                        <span class="text-muted small fw-medium d-block mb-1" style="font-size: 0.725rem;">USD ($)</span>
                        <span class="fw-bold text-dark d-block text-truncate" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;" title="$<?php echo number_format($totalTeamWonUsd, 2); ?>">
                            $<?php echo number_format($totalTeamWonUsd, 2); ?>
                        </span>
                    </div>
                    
                    <!-- Right Side: NGN Won Revenue -->
                    <div class="col-6 ps-3">
                        <span class="text-muted small fw-medium d-block mb-1" style="font-size: 0.725rem;">NGN (₦)</span>
                        <span class="fw-bold text-dark d-block text-truncate" style="font-family: 'Outfit', sans-serif; font-size: 1.05rem;" title="₦<?php echo number_format($totalTeamWonNgn, 2); ?>">
                            ₦<?php echo number_format($totalTeamWonNgn, 2); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Average Win Rate', $avgTeamWinRate . '%', 'pie-chart', 'primary', 'Team average close rate'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Top Representative', htmlspecialchars($topRepName), 'star', 'warning', 'Leader by revenue'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Sales Representatives', count($repPerformance), 'users', 'info', 'Active account managers'); ?>
        </div>
    </div>

    <!-- Performance Charts Row -->
    <div class="row g-4 mb-4">
        <!-- 1. Revenue Closed by Representative Bar Chart -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom p-3.5 d-flex align-items-center gap-2">
                    <i data-lucide="bar-chart-3" class="lucide-icon text-primary"></i>
                    <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Revenue Closed by Representative ($ / ₦)</h6>
                </div>
                <div class="card-body p-3 d-flex align-items-center justify-content-center">
                    <div style="width: 100%; height: 280px;">
                        <canvas id="repRevenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Win Conversion Rate % Chart -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom p-3.5 d-flex align-items-center gap-2">
                    <i data-lucide="target" class="lucide-icon text-primary"></i>
                    <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Win Conversion Rate (%) by Representative</h6>
                </div>
                <div class="card-body p-3 d-flex align-items-center justify-content-center">
                    <div style="width: 100%; height: 280px;">
                        <canvas id="repWinRateChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaderboard Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom p-3.5 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i data-lucide="award" class="lucide-icon text-primary"></i>
                <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Representative Performance Leaderboard</h6>
            </div>
            <span class="text-muted small">Sorted by Native Won Revenue</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Sales Representative</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Leads Assigned</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Total Opportunities</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Deals Won</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Revenue Closed ($ / ₦)</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Win Conversion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($repPerformance)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted small">No performance data recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($repPerformance as $rep): ?>
                                <?php 
                                    $winRate = ($rep['total_deals'] > 0) ? round(($rep['won_deals'] / $rep['total_deals']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td class="py-3 px-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle avatar-brand d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.9rem;">
                                                <?php echo strtoupper(substr($rep['rep_name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="fw-semibold text-dark d-block"><?php echo htmlspecialchars($rep['rep_name']); ?></span>
                                                <span class="text-muted small"><?php echo htmlspecialchars($rep['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4"><span class="badge badge-soft-info rounded-pill"><?php echo number_format($rep['total_leads']); ?></span></td>
                                    <td class="py-3 px-4 text-muted"><?php echo number_format($rep['total_deals']); ?></td>
                                    <td class="py-3 px-4"><span class="badge badge-soft-success rounded-pill"><?php echo number_format($rep['won_deals']); ?></span></td>
                                    <td class="py-3 px-4">
                                        <div class="fw-bold text-primary">$<?php echo number_format($rep['won_usd_revenue'], 2); ?></div>
                                        <div class="text-muted small">₦<?php echo number_format($rep['won_ngn_revenue'], 2); ?></div>
                                    </td>
                                    <td class="py-3 px-4 text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px; max-width: 90px; background-color: #e2e8f0;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $winRate; ?>%;" aria-valuenow="<?php echo $winRate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <span class="fw-bold small text-dark" style="min-width: 45px;"><?php echo $winRate; ?>%</span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fontFamily = "'Outfit', sans-serif";

    // 1. Revenue Closed Chart (Grouped USD vs NGN Bar Chart)
    const ctxRevenue = document.getElementById('repRevenueChart').getContext('2d');
    new Chart(ctxRevenue, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($repNames); ?>,
            datasets: [
                {
                    label: 'USD ($)',
                    data: <?php echo json_encode($repUsdRevenues); ?>,
                    backgroundColor: '#12b1b0',
                    borderRadius: 6
                },
                {
                    label: 'NGN (₦)',
                    data: <?php echo json_encode($repNgnRevenues); ?>,
                    backgroundColor: '#3b82f6',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { position: 'top', labels: { font: { family: fontFamily, size: 11 }, boxWidth: 12 } } 
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    ticks: { 
                        font: { family: fontFamily },
                        callback: function(v) { return v.toLocaleString(); }
                    } 
                },
                x: { ticks: { font: { family: fontFamily, size: 11 } } }
            }
        }
    });

    // 2. Win Rate Chart
    const ctxWinRate = document.getElementById('repWinRateChart').getContext('2d');
    new Chart(ctxWinRate, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($repNames); ?>,
            datasets: [{
                label: 'Win Rate (%)',
                data: <?php echo json_encode($repWinRates); ?>,
                backgroundColor: '#10b981',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { 
                    beginAtZero: true, 
                    max: 100,
                    ticks: { 
                        font: { family: fontFamily },
                        callback: function(v) { return v + '%'; }
                    } 
                },
                x: { ticks: { font: { family: fontFamily, size: 11 } } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
