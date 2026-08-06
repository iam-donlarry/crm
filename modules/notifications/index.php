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

$userId = $_SESSION['user_id'];

// Fetch all notifications for the current user
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate metrics
$totalCount = count($notifications);
$unreadCount = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));
$dealAlertsCount = count(array_filter($notifications, fn($n) => strpos(strtolower($n['title']), 'deal') !== false || strpos(strtolower($n['title']), 'won') !== false));
$assignmentCount = count(array_filter($notifications, fn($n) => strpos(strtolower($n['title']), 'assigned') !== false || strpos(strtolower($n['title']), 'lead') !== false));
?>

<div class="container-fluid px-0">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <?php renderBreadcrumb([['label' => 'Notifications', 'url' => '']]); ?>
            <h2 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">Notifications Center</h2>
            <p class="text-muted small mb-0">View, track, and manage system alerts, lead assignments, deal advances, and task reminders.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-3.5 shadow-sm btn-sm" onclick="markAllNotificationsReadPage()">
                <i data-lucide="check-check" class="lucide-icon me-1 text-secondary"></i> Mark All as Read
            </button>
        </div>
    </div>

    <!-- Quick Executive Metrics Strip -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Total Alerts', number_format($totalCount), 'bell', 'primary', 'All time notifications'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Unread Alerts', number_format($unreadCount), 'bell-ring', 'warning', 'Requires attention'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Deal Updates', number_format($dealAlertsCount), 'trophy', 'success', 'Pipeline & revenue alerts'); ?>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <?php renderStatCard('Assignments', number_format($assignmentCount), 'user-check', 'info', 'Lead & task notifications'); ?>
        </div>
    </div>

    <!-- Notifications Table Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-0">
            <!-- Filter Header -->
            <div class="p-3 border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2" style="background: #f8fafc;">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-white border-end-0"><i data-lucide="search" class="lucide-icon text-muted"></i></span>
                    <input type="text" id="searchFilter" class="form-control border-start-0" placeholder="Search notifications by title or text...">
                </div>
            </div>

            <!-- Table Responsive -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="notificationsTable" style="font-family: 'Outfit', sans-serif;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem; width: 50px;">Status</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Notification Details</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Category</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold" style="font-size: 0.75rem;">Received</th>
                            <th class="py-3 px-4 text-uppercase text-muted fw-bold text-end" style="font-size: 0.75rem;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted small">
                                    <i data-lucide="inbox" class="lucide-icon d-block mb-2 text-muted mx-auto" style="width: 32px; height: 32px;"></i>
                                    No notifications found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                                <?php 
                                    $isUnread = ($n['is_read'] == 0);
                                    $rowBg = $isUnread ? 'style="background-color: #f0fdfa;"' : '';
                                    
                                    // Determine icon & badge by title/type
                                    $titleLower = strtolower($n['title']);
                                    if (strpos($titleLower, 'won') !== false || strpos($titleLower, '🎉') !== false) {
                                        $icon = 'trophy';
                                        $badgeClass = 'badge-soft-success';
                                        $catName = 'Deal Won';
                                    } else if (strpos($titleLower, 'lead') !== false) {
                                        $icon = 'filter';
                                        $badgeClass = 'badge-soft-primary';
                                        $catName = 'Leads';
                                    } else if (strpos($titleLower, 'task') !== false) {
                                        $icon = 'check-square';
                                        $badgeClass = 'badge-soft-info';
                                        $catName = 'Tasks';
                                    } else if (strpos($titleLower, 'lost') !== false) {
                                        $icon = 'x-circle';
                                        $badgeClass = 'badge-soft-danger';
                                        $catName = 'Deal Lost';
                                    } else {
                                        $icon = 'bell';
                                        $badgeClass = 'badge-soft-secondary';
                                        $catName = 'Pipeline';
                                    }
                                ?>
                                <tr <?php echo $rowBg; ?>>
                                    <td class="py-3 px-4 text-center">
                                        <?php if ($isUnread): ?>
                                            <span class="d-inline-block rounded-circle bg-danger" style="width: 9px; height: 9px;" title="Unread"></span>
                                        <?php else: ?>
                                            <span class="d-inline-block rounded-circle bg-secondary opacity-25" style="width: 9px; height: 9px;" title="Read"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="d-flex align-items-start gap-2.5">
                                            <div class="rounded-circle p-2 bg-light d-flex align-items-center justify-content-center mt-0.5" style="width: 32px; height: 32px;">
                                                <i data-lucide="<?php echo $icon; ?>" class="lucide-icon text-primary" style="width: 16px; height: 16px;"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0.5 text-dark fw-bold" style="font-size: 0.925rem; font-family: 'Outfit', sans-serif;">
                                                    <?php echo htmlspecialchars($n['title']); ?>
                                                </h6>
                                                <p class="text-muted mb-0 small lh-sm">
                                                    <?php echo htmlspecialchars($n['message']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="badge rounded-pill <?php echo $badgeClass; ?>"><?php echo $catName; ?></span>
                                    </td>
                                    <td class="py-3 px-4 text-muted small">
                                        <i data-lucide="clock" class="lucide-icon me-1 text-muted"></i>
                                        <?php echo date('M j, Y • g:i A', strtotime($n['created_at'])); ?>
                                    </td>
                                    <td class="py-3 px-4 text-end">
                                        <?php if (!empty($n['link'])): ?>
                                            <a href="<?php echo htmlspecialchars($n['link']); ?>" class="btn btn-sm btn-light border rounded-pill px-3 shadow-sm" style="font-size: 0.775rem;" onclick="markSingleNotificationReadPage(<?php echo $n['id']; ?>)">
                                                View Details <i data-lucide="arrow-right" class="lucide-icon ms-1"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Card Footer Pagination -->
            <div class="card-footer bg-white border-top p-3" id="notificationsPagination"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initTablePagination('notificationsTable', 'notificationsPagination', 'searchFilter', 25);
});

function markSingleNotificationReadPage(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/notifications/mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    });
}

function markAllNotificationsReadPage() {
    fetch('<?php echo BASE_URL; ?>/ajax/notifications/mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast('All notifications marked as read', 'success');
            setTimeout(() => window.location.reload(), 500);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
