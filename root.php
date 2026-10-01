<?php
ob_start(); 
require_once 'includes/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$current_page    = isset($_GET['page'])    ? $_GET['page']    : 'dashboard';
$current_subpage = isset($_GET['subpage']) ? $_GET['subpage'] : '';
$page_title      = ucfirst(str_replace('-', ' ', $current_subpage ?: $current_page));

$user = getUserInfo($pdo, $_SESSION['user_id']);
$role = $user['role'] ?? 'customer';

if ($role === 'manager' && $current_page === 'dashboard') {
    $current_page = 'manager-dashboard';
}

$stats = getHRStats($pdo, $_SESSION['user_id'] ?? 0);
$unread_notifications = getUnreadNotificationCount($pdo, $_SESSION['user_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>HR 1 - Freight Management <?php echo $page_title ? ' - ' . $page_title : ''; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="unique-budget-dashboard">
        <div class="floating-bg">
            <div class="floating-circle circle-1"></div>
            <div class="floating-circle circle-2"></div>
            <div class="floating-circle circle-3"></div>
            <div class="floating-square square-1"></div>
            <div class="floating-square square-2"></div>
        </div>

        <?php 
        if ($role === 'manager') {
            include 'includes/sidebar-manager.php';
        } else {
            include 'includes/sidebar.php';
        }
        ?>

        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>

        <main class="unique-main" style="margin-left: <?php echo isset($_COOKIE['sidebar']) && $_COOKIE['sidebar'] == 'collapsed' ? '100px' : '320px'; ?>">
            <?php include 'includes/header.php'; ?>

            <div class="dashboard-content page-transition">
                <?php
                // ============================================================
                // CORE HR — explicit routing (4 submodules)
                // ============================================================
                if ($current_page === 'core-hr') {
                    $core_files = [
                        'employee-master-data' => 'modules/core-hr/employee-master-data.php',
                        'employment-info'      => 'modules/core-hr/employment-info.php',
                        'employment-history'   => 'modules/core-hr/employment-history.php',
                        'hr-configuration'     => 'modules/core-hr/hr-configuration.php',
                    ];

                    $core_sub = $current_subpage ?: 'employee-master-data';
                    if (isset($core_files[$core_sub]) && file_exists($core_files[$core_sub])) {
                        include $core_files[$core_sub];
                    } else {
                        // Fallback
                        if (file_exists('modules/core-hr/employee-master-data.php')) {
                            include 'modules/core-hr/employee-master-data.php';
                        } else {
                            echo '<div style="padding:40px;text-align:center;color:#64748b;">';
                            echo '<h2>Core HR module is being set up.</h2>';
                            echo '<p>Please create the module files under <code>modules/core-hr/</code>.</p>';
                            echo '</div>';
                        }
                    }
                }
                // ============================================================
                // EMPLOYEE RECORDS MANAGEMENT (ERM) — explicit routing
                // ============================================================
                elseif ($current_page === 'erm') {
                    $erm_files = [
                        'erm-dashboard'    => 'modules/erm/erm-dashboard.php',
                        'erm-personnel'    => 'modules/erm/erm-personnel.php',
                        'erm-upload'       => 'modules/erm/erm-upload.php',
                        'erm-verification' => 'modules/erm/erm-verification.php',
                        'erm-expiration'   => 'modules/erm/erm-expiration.php',
                        'erm-archives'     => 'modules/erm/erm-archives.php',
                        'erm-audit'        => 'modules/erm/erm-audit.php',
                    ];

                    $erm_sub = $current_subpage ?: 'erm-dashboard';
                    if (isset($erm_files[$erm_sub]) && file_exists($erm_files[$erm_sub])) {
                        include $erm_files[$erm_sub];
                    } else {
                        // Fallback
                        if (file_exists('modules/erm/erm-dashboard.php')) {
                            include 'modules/erm/erm-dashboard.php';
                        } else {
                            echo '<div style="padding:40px;text-align:center;color:#64748b;">';
                            echo '<h2>Employee Records module is being set up.</h2>';
                            echo '<p>Please create the module files under <code>modules/erm/</code>.</p>';
                            echo '</div>';
                        }
                    }
                }
                // ============================================================
                // ESS ADMIN — explicit routing (5 submodules)
                // ============================================================
                elseif ($current_page === 'ess-admin') {
                    $ess_files = [
                        'ess-accounts'      => 'modules/admin-ess/ess-accounts.php',
                        'ess-announcements' => 'modules/admin-ess/ess-announcements.php',
                        'ess-requests'      => 'modules/admin-ess/ess-requests.php',
                        'ess-documents'     => 'modules/admin-ess/ess-documents.php',
                        'ess-settings'      => 'modules/admin-ess/ess-settings.php',
                    ];

                    $ess_sub = $current_subpage ?: 'ess-accounts';
                    if (isset($ess_files[$ess_sub]) && file_exists($ess_files[$ess_sub])) {
                        include $ess_files[$ess_sub];
                    } else {
                        // Fallback
                        if (file_exists('modules/admin-ess/ess-accounts.php')) {
                            include 'modules/admin-ess/ess-accounts.php';
                        } else {
                            echo '<div style="padding:40px;text-align:center;color:#64748b;">';
                            echo '<h2>ESS Admin module is being set up.</h2>';
                            echo '<p>Please create the module files under <code>modules/admin-ess/</code>.</p>';
                            echo '</div>';
                        }
                    }
                }
                // Generic routing
                elseif ($current_subpage) {
                    $subpage_file = "modules/{$current_page}/{$current_subpage}.php";
                    if (file_exists($subpage_file)) {
                        include $subpage_file;
                    } else {
                        $module_file = "modules/{$current_page}.php";
                        if (file_exists($module_file)) {
                            include $module_file;
                        } else {
                            include 'modules/dashboard.php';
                        }
                    }
                }
                else {
                    $page_file = "modules/{$current_page}.php";
                    if (file_exists($page_file)) {
                        include $page_file;
                    } else {
                        include 'modules/dashboard.php';
                    }
                }
                ?>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentSubpage = '<?php echo $current_subpage; ?>';
        if (currentSubpage) {
            const submenu = document.getElementById('<?php echo $current_page; ?>-submenu');
            if (submenu) submenu.style.display = 'block';
        }
    });
    </script>
</body>
</html>