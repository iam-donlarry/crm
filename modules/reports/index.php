<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';

// Sales Team Representative Performance Metrics Query
$performanceStmt = $pdo->query("
    SELECT 
        u.id,
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.name, u.email) as rep_name,
        u.email,
        COUNT(DISTINCT l.id) as total_leads,
        COUNT(DISTINCT d.id) as total_deals,
        COUNT(DISTINCT CASE WHEN d.stage = 'closed_won' THEN d.id END) as won_deals,
        COALESCE(SUM(CASE WHEN d.stage = 'closed_won' THEN d.value ELSE 0 END), 0) as won_revenue
    FROM users u
    LEFT JOIN leads l ON l.assigned_to = u.id AND l.is_archived = 0
    LEFT JOIN deals d ON d.assigned_to = u.id AND d.is_archived = 0
    WHERE u.is_archived = 0
    GROUP BY u.id, u.first_name, u.last_name, u.name, u.email
    ORDER BY won_revenue DESC, won_deals DESC
");
$repPerformance = $performanceStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Summary Statistics
$totalTeamRevenue = array_sum(array_column($repPerformance, 'won_revenue'));
$totalTeamDeals = array_sum(array_column($repPerformance, 'total_deals'));
$totalTeamWonDeals = array_sum(array_column($repPerformance, 'won_deals'));
$avgTeamWinRate = ($totalTeamDeals > 0) ? round(($totalTeamWonDeals / $totalTeamDeals) * 100, 1) : 0;
$topRepName = !empty($repPerformance) ? $repPerformance[0]['rep_name'] : 'N/A';

// Prepare Chart Data
$repNames = array_column($repPerformance, 'rep_name');
$repRevenues = array_column($repPerformance, 'won_revenue');
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
            <p class="text-muted small mb-0">Evaluate representative productivity, deal close rates, and revenue contributions.</p>
        </div>
    </div>

    <!-- Executive KPI Strip -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Closed Revenue', 'USD ' . number_format($totalTeamRevenue, 2), 'trophy', 'success', 'Total won value'); ?>
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
                    <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Revenue Closed by Representative ($)</h6>
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
            <span class="text-muted small">Sorted by Won Revenue</span>
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
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Revenue Closed ($)</th>
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
                                    <td class="py-3 px-4 fw-bold text-primary">USD <?php echo number_format($rep['won_revenue'], 2); ?></td>
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

    // 1. Revenue Closed Chart
    const ctxRevenue = document.getElementById('repRevenueChart').getContext('2d');
    new Chart(ctxRevenue, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($repNames); ?>,
            datasets: [{
                label: 'Revenue Closed ($)',
                data: <?php echo json_encode($repRevenues); ?>,
                backgroundColor: '#12b1b0',
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
                    ticks: { 
                        font: { family: fontFamily },
                        callback: function(v) { return '$' + v.toLocaleString(); }
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
