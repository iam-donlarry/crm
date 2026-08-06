<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthenticated users to login
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isLoginPage = (strpos($requestUri, 'login') !== false || basename($_SERVER['PHP_SELF']) === 'login.php' || basename($_SERVER['PHP_SELF']) === 'login_handler.php');

if (!isset($_SESSION['user_id']) && !$isLoginPage) {
    header("Location: " . BASE_URL . "/login");
    exit();
}

// Fetch System Settings for Global Use
$globalSettings = [];
$userPermissions = $_SESSION['permissions'] ?? [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $globalSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        // Silent fallback
    }
}
$globalSettings = array_merge([
    'currency' => 'NGN',
    'app_name' => APP_NAME
], $globalSettings);

// Include UI Components
require_once __DIR__ . '/../components/stat-card.php';
require_once __DIR__ . '/../components/breadcrumb.php';
require_once __DIR__ . '/../components/button.php';
require_once __DIR__ . '/../components/table.php';
require_once __DIR__ . '/../components/empty-state.php';
require_once __DIR__ . '/../components/modal.php';
require_once __DIR__ . '/../components/pagination.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="icon" type="image/webp" href="<?php echo BASE_URL; ?>/assets/images/bbsllogo.webp">
    
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Vendor CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    
    <!-- Lucide Icons Library -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        window.CRM_APP_SETTINGS = <?php echo json_encode($globalSettings); ?>;
        window.CRM_USER_PERMISSIONS = <?php echo json_encode($userPermissions); ?>;
        window.BASE_URL = "<?php echo BASE_URL; ?>";

        document.addEventListener('DOMContentLoaded', function() {
            if (window.lucide) {
                lucide.createIcons();
            }
        });
    </script>
    
    <style>
        :root {
            --primary-color: #12b1b0;
            --primary-hover: #0d8584;
            --primary-light: #eef2ff;
            --secondary-color: #64748b;
            --topbar-height: 64px;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 76px;
            --body-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--body-bg);
            color: #0f172a;
            margin: 0;
            overflow-x: hidden;
        }

        /* Lucide Icon Sizing */
        .lucide-icon {
            width: 20px;
            height: 20px;
            stroke-width: 2px;
            display: inline-block;
            vertical-align: middle;
        }

        /* Nav Pills Brand Color Overrides */
        .nav-pills .nav-link.active,
        .nav-pills .show > .nav-link {
            background-color: var(--primary-color) !important;
            color: #ffffff !important;
            font-weight: 600;
        }

        .nav-pills .nav-link {
            color: #475569;
            font-weight: 500;
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        .bg-primary {
            background-color: var(--primary-color) !important;
        }

        .border-primary {
            border-color: var(--primary-color) !important;
        }

        /* Half-screen 50% Offcanvas Drawer Styling & High Z-Index */
        .offcanvas,
        .offcanvas-half,
        .offcanvas-end {
            width: 50% !important;
            max-width: 50% !important;
            min-width: 50% !important;
            z-index: 1200 !important;
            border-left: 1px solid var(--border-color);
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.15);
        }

        .offcanvas-backdrop {
            z-index: 1150 !important;
        }

        .offcanvas-header {
            background-color: #ffffff;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        .offcanvas-footer {
            background-color: #ffffff;
            border-top: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        @media (max-width: 768px) {
            .offcanvas,
            .offcanvas-half,
            .offcanvas-end {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 100% !important;
            }
        }

        /* Sidebar Styling - Ghost White with Brand Color (#12b1b0) Active Pill */
        .sidebar {
            position: fixed;
            top: var(--topbar-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--topbar-height));
            background: #f8fafc; /* Ghost White */
            color: #0f172a;
            z-index: 900;
            overflow-y: auto;
            overflow-x: hidden;
            border-right: 1px solid var(--border-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar .nav-link {
            color: #475569;
            font-weight: 500;
            font-size: 0.925rem;
            transition: all 0.2s ease;
        }

        .sidebar .nav-link .lucide-icon {
            stroke: #64748b;
            transition: stroke 0.2s ease;
        }

        .sidebar .nav-link:hover {
            color: var(--primary-color);
            background: rgba(18, 177, 176, 0.08);
        }

        .sidebar .nav-link:hover .lucide-icon {
            stroke: var(--primary-color);
        }

        .sidebar .nav-link.active {
            color: #ffffff !important;
            background: var(--primary-color) !important; /* Brand Teal (#12b1b0) */
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(18, 177, 176, 0.25);
        }

        .sidebar .nav-link.active .lucide-icon {
            stroke: #ffffff !important;
        }

        /* Collapsed Sidebar Mode (Icon-Only) */
        .sidebar.sidebar-collapsed {
            width: var(--sidebar-collapsed-width) !important;
        }

        .sidebar.sidebar-collapsed .sidebar-text,
        .sidebar.sidebar-collapsed hr,
        .sidebar.sidebar-collapsed .dropdown-toggle::after {
            display: none !important;
        }

        .sidebar.sidebar-collapsed .nav-link {
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .sidebar.sidebar-collapsed .nav-link .lucide-icon {
            margin-right: 0 !important;
        }

        .main-content-area.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed-width) !important;
        }

        /* Topbar Positioning */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1100;
            height: var(--topbar-height);
            background: #ffffff !important;
            border-bottom: 1px solid var(--border-color);
        }

        /* Main Content Wrapper */
        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content-area {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 24px;
            padding-top: calc(var(--topbar-height) + 24px);
            transition: margin-left 0.3s ease-in-out;
        }

        /* Cards & Buttons */
        .card {
            border: 1px solid var(--border-color);
            border-radius: 14px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }

        .btn-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: var(--primary-hover) !important;
            border-color: var(--primary-hover) !important;
        }

        .btn-outline-primary {
            color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color) !important;
            color: #ffffff !important;
        }

        /* Expense App / BayePay Soft-Tint Table Badges */
        .badge {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.35em 0.75em;
            letter-spacing: 0.02em;
        }

        .badge-soft-primary {
            background-color: #e6f7f7 !important;
            color: #12b1b0 !important;
            border: 1px solid #b2ecec !important;
        }

        .badge-soft-success {
            background-color: #ecfdf5 !important;
            color: #047857 !important;
            border: 1px solid #a7f3d0 !important;
        }

        .badge-soft-warning {
            background-color: #fffbeb !important;
            color: #b45309 !important;
            border: 1px solid #fde68a !important;
        }

        .badge-soft-danger {
            background-color: #fef2f2 !important;
            color: #b91c1c !important;
            border: 1px solid #fecaca !important;
        }

        .badge-soft-info {
            background-color: #e0f2fe !important;
            color: #0369a1 !important;
            border: 1px solid #bae6fd !important;
        }

        .badge-soft-secondary {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            border: 1px solid #e2e8f0 !important;
        }

        /* User Avatar Brand Styling */
        .avatar-brand {
            background-color: #12b1b0 !important;
            color: #ffffff !important;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            box-shadow: 0 2px 6px rgba(18, 177, 176, 0.25);
        }

        /* Mobile Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.4);
            z-index: 850;
            backdrop-filter: blur(4px);
        }

        .sidebar-overlay.show {
            display: block;
        }

        @media (max-width: 768px) {
            .main-content-area {
                margin-left: 0 !important;
                padding: 16px;
                padding-top: calc(var(--topbar-height) + 16px);
            }
            .sidebar {
                transform: translateX(-100%);
                top: 0;
                bottom: 0;
                height: 100vh;
                z-index: 1200;
            }
            .sidebar.show {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Topbar Header -->
    <?php require_once __DIR__ . '/topbar.php'; ?>

    <!-- Mobile Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="main-wrapper">
        <!-- Sidebar Component -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area Start -->
        <div class="main-content-area" id="mainContent">
            <div class="content">