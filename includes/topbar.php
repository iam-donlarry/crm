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
</script>
