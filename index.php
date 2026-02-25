<?php
// index.php
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$current_subpage = isset($_GET['subpage']) ? $_GET['subpage'] : '';
$page_title = ucfirst(str_replace('-', ' ', $current_subpage ?: $current_page));

// Get user info
$user = getUserInfo($pdo, $_SESSION['user_id']);
$role = $user['role'];

// Get HR stats
$stats = getHRStats($pdo, $_SESSION['user_id']);
$unread_notifications = getUnreadNotificationCount($pdo, $_SESSION['user_id']);
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
        <!-- Floating Background Elements -->
        <div class="floating-bg">
            <div class="floating-circle circle-1"></div>
            <div class="floating-circle circle-2"></div>
            <div class="floating-circle circle-3"></div>
            <div class="floating-square square-1"></div>
            <div class="floating-square square-2"></div>
        </div>

        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Mobile Menu Button -->
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Main Content -->
        <main class="unique-main" style="margin-left: <?php echo isset($_COOKIE['sidebar']) && $_COOKIE['sidebar'] == 'collapsed' ? '100px' : '320px'; ?>">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>

            <!-- Page Content -->
            <div class="dashboard-content page-transition">
                <?php
                if ($current_subpage) {
                    // Handle subpages
                    $subpage_file = "modules/{$current_page}/{$current_subpage}.php";
                    if (file_exists($subpage_file)) {
                        include $subpage_file;
                    } else {
                        // Fallback to main module page
                        $module_file = "modules/{$current_page}.php";
                        if (file_exists($module_file)) {
                            include $module_file;
                        } else {
                            include 'modules/dashboard.php';
                        }
                    }
                } else {
                    // Handle main pages
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
    // Initialize submenu states based on current page
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-expand submenu if on a subpage
        const currentSubpage = '<?php echo $current_subpage; ?>';
        if (currentSubpage) {
            const module = '<?php echo $current_page; ?>';
            const submenu = document.getElementById(module + '-submenu');
            if (submenu) {
                submenu.style.display = 'block';
            }
        }
    });
    </script>
</body>
</html>