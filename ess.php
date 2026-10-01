<?php
// /ess.php
require_once __DIR__ . '/includes/ess/ess_auth.php';

essRequireLogin();

$current_page    = $_GET['page']    ?? 'dashboard';
$current_subpage = $_GET['subpage'] ?? '';

// Human-readable page title
$page_title = ucwords(str_replace(['-', '_'], ' ', $current_subpage ?: $current_page));

$account  = essGetAccount($pdo);
$employee = essGetEmployee($pdo);

if (!$account || !$employee) {
    essLogout();
    header('Location: ess_login.php');
    exit;
}

$unread = essGetUnreadCount($pdo, $employee['id']);

// ============================================================
// ROUTING — modular + safe
// ============================================================
$ess_modules_dir = __DIR__ . '/modules/ess/';

// Whitelist of valid pages and their default subpages
$ess_routes = [
    'dashboard' => ['default' => 'dashboard'],
    'my-info'   => ['default' => 'my-profile',  'subpages' => ['my-profile', 'my-employment', 'my-documents']],
    'time'      => ['default' => 'attendance',  'subpages' => ['attendance', 'timesheet', 'leave', 'overtime', 'schedule']],
    'comp'      => ['default' => 'payslip',     'subpages' => ['payslip', 'benefits', 'claims']],
    'requests'  => ['default' => 'requests',    'subpages' => ['requests', 'document-requests']],
    'growth'    => ['default' => 'performance', 'subpages' => ['performance', 'training']],
    'comm'      => ['default' => 'announcements','subpages' => ['announcements', 'notifications']],
];

// Resolve the file to include
$file_to_include = null;

if ($current_page === 'dashboard') {
    // Top-level dashboard
    $file_to_include = $ess_modules_dir . 'dashboard.php';
} elseif (isset($ess_routes[$current_page])) {
    // Subpage-based module
    $route = $ess_routes[$current_page];
    if ($current_subpage && in_array($current_subpage, $route['subpages'] ?? [])) {
        $file_to_include = $ess_modules_dir . $current_subpage . '.php';
    } else {
        $file_to_include = $ess_modules_dir . $route['default'] . '.php';
        $current_subpage = $route['default']; // normalize
    }
} else {
    // Unknown page → fallback to dashboard
    $file_to_include = $ess_modules_dir . 'dashboard.php';
}

// Final safety check
if (!file_exists($file_to_include)) {
    $file_to_include = $ess_modules_dir . 'dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>ESS — <?= htmlspecialchars($page_title) ?> | Freight HR 1</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="unique-budget-dashboard ess-layout">
        <div class="floating-bg">
            <div class="floating-circle circle-1"></div>
            <div class="floating-circle circle-2"></div>
            <div class="floating-circle circle-3"></div>
            <div class="floating-square square-1"></div>
            <div class="floating-square square-2"></div>
        </div>

        <?php include __DIR__ . '/includes/ess/ess_sidebar.php'; ?>

        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>

        <main class="unique-main" style="margin-left: <?= isset($_COOKIE['ess_sidebar']) && $_COOKIE['ess_sidebar'] == 'collapsed' ? '100px' : '320px' ?>;">
            <?php include __DIR__ . '/includes/ess/ess_header.php'; ?>

            <div class="dashboard-content page-transition">
                <?php include $file_to_include; ?>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
    // ============================================================
    // Auto-scroll sidebar to keep active item visible
    // ============================================================
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.querySelector('.sidebar-nav-container')
                       || document.querySelector('.sidebar-content');
        const active    = document.querySelector('.ess-sidebar .nav-item.active')
                       || document.querySelector('.ess-sidebar .submenu-item.active');

        if (container && active) {
            setTimeout(function () {
                const top = active.offsetTop - (container.clientHeight / 2) + (active.clientHeight / 2);
                container.scrollTo({ top: Math.max(0, top), behavior: 'auto' });
            }, 50);
        }
    });
    </script>
</body>
</html>