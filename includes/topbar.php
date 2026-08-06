<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom static-top shadow-sm px-3">
    <div class="container-fluid px-0">
        <div class="d-flex align-items-center">
            <button class="btn btn-light rounded-circle me-2 p-2 border-0" id="sidebarToggle" onclick="toggleSidebar()" title="Toggle Sidebar Navigation">
                <i class="bi bi-list fs-4 text-dark"></i>
            </button>
            <h5 class="mb-0 fw-bold text-dark d-none d-sm-block" style="font-family: 'Outfit', sans-serif; letter-spacing: -0.01em;">
                <?php echo htmlspecialchars(APP_NAME); ?>
            </h5>
        </div>

        <ul class="navbar-nav ms-auto align-items-center gap-2">
            <!-- Notification Bell Dropdown -->
            <li class="nav-item dropdown me-2">
                <a class="nav-link text-dark p-2 position-relative d-flex align-items-center justify-content-center rounded-circle hover-bg-light" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i data-lucide="bell" class="lucide-icon text-secondary" style="width: 20px; height: 20px;"></i>
                    <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size: 0.65rem;">
                        0
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 p-0 text-small" aria-labelledby="notificationDropdown" style="width: 320px; max-height: 420px; overflow-y: auto;">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
                        <h6 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">Notifications</h6>
                        <button type="button" class="btn btn-sm btn-link text-primary text-decoration-none p-0 small" onclick="markAllNotificationsRead()">Mark all as read</button>
                    </div>
                    <div id="notificationList" class="list-group list-group-flush">
                        <div class="text-center py-4 text-muted small">
                            <i data-lucide="inbox" class="lucide-icon d-block mb-1 text-muted mx-auto"></i> No new notifications
                        </div>
                    </div>
                </div>
            </li>

            <!-- User Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center text-dark fw-semibold px-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="rounded-circle avatar-brand text-white fw-bold d-flex align-items-center justify-content-center me-2" style="width: 36px; height: 36px; font-size: 0.9rem;">
                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="d-none d-md-block text-start me-1" style="line-height: 1.2;">
                        <span class="d-block small fw-bold text-dark" style="font-family: 'Outfit', sans-serif; font-size: 0.875rem;">
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                        </span>
                        <span class="text-muted" style="font-size: 0.725rem; font-weight: 500;">
                            <?php echo htmlspecialchars($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? 'Staff'); ?>
                        </span>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 text-small" aria-labelledby="userDropdown">
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>/profile">
                            <i data-lucide="user" class="lucide-icon text-muted"></i> My Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i data-lucide="log-out" class="lucide-icon text-danger"></i> Sign out
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('appSidebar');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('sidebarOverlay');

    if (window.innerWidth < 768) {
        if (sidebar) sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
    } else {
        if (sidebar) sidebar.classList.toggle('sidebar-collapsed');
        if (mainContent) mainContent.classList.toggle('sidebar-collapsed');
    }
}

function fetchNotifications() {
    fetch('<?php echo BASE_URL; ?>/ajax/notifications/fetch.php')
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                const badge = document.getElementById('notificationBadge');
                const list = document.getElementById('notificationList');
                const count = res.data.unread_count || 0;
                
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }

                if (res.data.notifications && res.data.notifications.length > 0) {
                    list.innerHTML = res.data.notifications.map(n => `
                        <a href="${n.link || '#'}" class="list-group-item list-group-item-action p-3 border-bottom ${n.is_read == 0 ? 'bg-light-subtle fw-semibold' : ''}" onclick="markNotificationRead(${n.id})">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="small text-dark fw-bold" style="font-family: 'Outfit', sans-serif;">${escapeHtml(n.title)}</span>
                                <span class="text-muted" style="font-size: 0.675rem;">${formatTimeAgo(n.created_at)}</span>
                            </div>
                            <p class="mb-0 text-muted small lh-sm" style="font-size: 0.775rem;">${escapeHtml(n.message)}</p>
                        </a>
                    `).join('');
                } else {
                    list.innerHTML = `
                        <div class="text-center py-4 text-muted small">
                            <i data-lucide="inbox" class="lucide-icon d-block mb-1 text-muted mx-auto"></i> No new notifications
                        </div>`;
                }
                if (window.lucide) lucide.createIcons();
            }
        }).catch(err => console.error(err));
}

function markNotificationRead(id) {
    fetch('<?php echo BASE_URL; ?>/ajax/notifications/mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).then(() => fetchNotifications());
}

function markAllNotificationsRead() {
    fetch('<?php echo BASE_URL; ?>/ajax/notifications/mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    }).then(() => fetchNotifications());
}

function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatTimeAgo(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffSec = Math.floor((now - date) / 1000);
    if (diffSec < 60) return 'Just now';
    if (diffSec < 3600) return Math.floor(diffSec / 60) + 'm ago';
    if (diffSec < 86400) return Math.floor(diffSec / 3600) + 'h ago';
    return Math.floor(diffSec / 86400) + 'd ago';
}

document.addEventListener('DOMContentLoaded', function() {
    fetchNotifications();
    setInterval(fetchNotifications, 30000);
});
</script>
