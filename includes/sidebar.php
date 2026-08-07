<?php
/**
 * Sidebar Navigation Component
 * Ghost White Sidebar with Permission-Driven Menu Visibility
 */
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'user';
$permissions = $_SESSION['permissions'] ?? [];

function isNavActive($path, $currentUri) {
    if ($path === '/dashboard' && ($currentUri === '/crm/' || $currentUri === '/crm/dashboard' || strpos($currentUri, 'index.php') !== false)) {
        return true;
    }
    return (strpos($currentUri, $path) !== false);
}
?>
<div class="sidebar p-3 d-flex flex-column" id="appSidebar">
    <!-- Brand Header -->
    <div class="d-flex align-items-center gap-3 px-2 py-2 mb-4">
        <img src="<?php echo BASE_URL; ?>/assets/images/bbsllogo.webp" alt="Logo" style="height: 38px; width: auto;" class="brand-logo-img">
        <div class="sidebar-text">
            <h6 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; letter-spacing: -0.02em;">
                <?php echo htmlspecialchars(APP_NAME); ?>
            </h6>
            <span class="text-muted small" style="font-size: 0.725rem; letter-spacing: 0.05em; font-weight: 500;">ENTERPRISE CRM</span>
        </div>
    </div>

    <!-- Main Navigation Section -->
    <div class="px-2 pt-1 pb-2 text-uppercase text-muted fw-bold sidebar-text" style="font-size: 0.675rem; letter-spacing: 0.08em;">
        Main Menu
    </div>

    <ul class="nav nav-pills flex-column mb-auto gap-2" style="font-family: 'Outfit', sans-serif;">
        <?php if (hasPermission('view_dashboard')): ?>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/dashboard" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/dashboard', $currentUri) ? 'active' : ''; ?>">
                <i data-lucide="layout-dashboard" class="lucide-icon me-3"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('view_leads') || hasPermission('view_team_leads')): ?>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/leads" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/leads', $currentUri) ? 'active' : ''; ?>">
                <i data-lucide="filter" class="lucide-icon me-3"></i>
                <span class="sidebar-text">Leads</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('view_deals') || hasPermission('view_team_deals')): ?>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/deals" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/deals', $currentUri) ? 'active' : ''; ?>">
                <i data-lucide="briefcase" class="lucide-icon me-3"></i>
                <span class="sidebar-text">Deals Pipeline</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('view_customers') || hasPermission('view_team_customers')): ?>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/customers" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/customers', $currentUri) ? 'active' : ''; ?>">
                <i data-lucide="users" class="lucide-icon me-3"></i>
                <span class="sidebar-text">Customers</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('view_activities')): ?>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/tasks" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/tasks', $currentUri) ? 'active' : ''; ?>">
                <i data-lucide="check-square" class="lucide-icon me-3"></i>
                <span class="sidebar-text">Tasks & Calendar</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('view_products')): ?>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/products" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/products', $currentUri) ? 'active' : ''; ?>">
                <i data-lucide="box" class="lucide-icon me-3"></i>
                <span class="sidebar-text">Products Catalog</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasPermission('view_reports') || hasPermission('view_statistics')): ?>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/reports" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/reports', $currentUri) ? 'active' : ''; ?>">
                <i data-lucide="bar-chart-3" class="lucide-icon me-3"></i>
                <span class="sidebar-text">Performance Reports</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/notifications" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/notifications', $currentUri) ? 'active' : ''; ?>">
                <i data-lucide="bell" class="lucide-icon me-3"></i>
                <span class="sidebar-text">Notifications Center</span>
            </a>
        </li>

        <?php if (hasPermission('view_settings') || hasPermission('view_users') || hasPermission('manage_roles')): ?>
            <li class="px-2 pt-4 pb-2 text-uppercase text-muted fw-bold sidebar-text" style="font-size: 0.675rem; letter-spacing: 0.08em;">
                Administration
            </li>
            <?php if (hasPermission('view_settings')): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/settings" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/settings', $currentUri) ? 'active' : ''; ?>">
                    <i data-lucide="settings" class="lucide-icon me-3"></i>
                    <span class="sidebar-text">System Settings</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('view_users')): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/users" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/users', $currentUri) ? 'active' : ''; ?>">
                    <i data-lucide="user-cog" class="lucide-icon me-3"></i>
                    <span class="sidebar-text">User Directory & Staff</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('manage_roles') || hasPermission('view_users')): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/roles" class="nav-link text-secondary d-flex align-items-center py-2.5 px-3.5 rounded-3 <?php echo isNavActive('/roles', $currentUri) ? 'active' : ''; ?>">
                    <i data-lucide="shield-check" class="lucide-icon me-3"></i>
                    <span class="sidebar-text">Roles & Permissions</span>
                </a>
            </li>
            <?php endif; ?>
        <?php endif; ?>
    </ul>

    <hr class="border-secondary opacity-15 my-4">

    <!-- User Account Footer Badge -->
    <div class="px-2 py-1 d-flex align-items-center">
        <div class="rounded-circle avatar-brand text-white fw-bold d-flex align-items-center justify-content-center me-2.5 shadow-sm" style="width: 38px; height: 38px; font-size: 0.95rem;">
            <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
        </div>
        <div class="sidebar-text overflow-hidden">
            <strong class="d-block text-truncate small text-dark" style="font-family: 'Outfit', sans-serif;">
                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
            </strong>
            <span class="text-muted" style="font-size: 0.725rem; font-weight: 500;">
                <?php echo htmlspecialchars($_SESSION['role_name'] ?? $_SESSION['user_role'] ?? 'Staff'); ?>
            </span>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.lucide) {
            lucide.createIcons();
        }
    });
</script>
