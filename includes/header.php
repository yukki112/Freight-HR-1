<?php
// includes/header.php
require_once 'includes/notification_functions.php';

$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$current_subpage = isset($_GET['subpage']) ? $_GET['subpage'] : '';
$page_title = ucfirst(str_replace('-', ' ', $current_subpage ?: $current_page));

// Get user information
$user = getUserInfo($pdo, $_SESSION['user_id']);
$role = $user['role'];
$full_name = $user['full_name'] ?? 'User';
$first_name = explode(' ', $full_name)[0];

// Get HR stats - expanded for new modules
$hr_stats = getHRStats($pdo, $_SESSION['user_id']);

// Get notifications with modules
$notifications = getUserNotificationsWithModules($pdo, $_SESSION['user_id'], 10);
$unread_count = getUnreadNotificationCount($pdo, $_SESSION['user_id']);
$module_counts = getUnreadCountByModule($pdo, $_SESSION['user_id']);

// Check for API updates (run occasionally)
if (rand(1, 10) == 1) { // 10% chance to check on each page load
    $new_api_positions = checkForAPIUpdates($pdo);
}

// Get recent API imports
$recent_imports = getRecentAPIImports($pdo, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR 1 Freight Management - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Header Styles */
        .unique-header {
            background: white;
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(14,76,146,0.05);
            margin-bottom: 30px;
            position: relative;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .page-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-dot {
            width: 10px;
            height: 10px;
            background: #0e4c92;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .page-indicator h2 {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
        }

        .date-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 20px;
            font-size: 13px;
            color: #7f8c8d;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-container {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
            font-size: 14px;
        }

        .search-container input {
            width: 300px;
            padding: 12px 15px 12px 45px;
            border: 1px solid rgba(14,76,146,0.1);
            border-radius: 30px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-container input:focus {
            outline: none;
            border-color: #0e4c92;
            box-shadow: 0 0 0 4px rgba(14,76,146,0.1);
        }

        .search-shortcut {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: #f1f3f4;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 11px;
            color: #7f8c8d;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .action-btn {
            width: 45px;
            height: 45px;
            border: none;
            background: #f8f9fa;
            border-radius: 15px;
            color: #2c3e50;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn:hover {
            background: #0e4c92;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(14,76,146,0.3);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid white;
        }

        .pulse {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Process Flow Indicators */
        .process-flow {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-left: 15px;
            padding: 5px 12px;
            background: rgba(14,76,146,0.05);
            border-radius: 20px;
            font-size: 12px;
        }

        .process-step {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #7f8c8d;
        }

        .process-step.active {
            color: #0e4c92;
            font-weight: 500;
        }

        .process-step i {
            font-size: 10px;
        }

        .process-arrow {
            color: #bdc3c7;
            font-size: 10px;
        }

        /* Notification Center Styles */
        .notification-center {
            position: fixed;
            top: 100px;
            right: 30px;
            width: 400px;
            background: white;
            border-radius: 25px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
        }

        .notification-center.active {
            display: block;
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from { transform: translateX(20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .notification-header {
            padding: 20px;
            border-bottom: 1px solid rgba(14,76,146,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 18px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mark-read-btn {
            background: none;
            border: none;
            color: #0e4c92;
            font-size: 12px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .mark-read-btn:hover {
            background: rgba(14,76,146,0.1);
        }

        .notification-tabs {
            display: flex;
            gap: 5px;
            padding: 15px 20px 0;
            border-bottom: 1px solid rgba(14,76,146,0.1);
            flex-wrap: wrap;
        }

        .notification-tab {
            padding: 8px 15px;
            background: none;
            border: none;
            border-radius: 20px;
            font-size: 13px;
            color: #7f8c8d;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .notification-tab:hover {
            background: #f8f9fa;
            color: #2c3e50;
        }

        .notification-tab.active {
            background: rgba(14,76,146,0.1);
            color: #0e4c92;
            font-weight: 500;
        }

        .notification-tab-badge {
            background: #e74c3c;
            color: white;
            font-size: 9px;
            padding: 2px 5px;
            border-radius: 10px;
            margin-left: 5px;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }

        .notification-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            border-radius: 16px;
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: 5px;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: rgba(14,76,146,0.02);
            border-left: 3px solid #0e4c92;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-icon.info {
            background: rgba(14,76,146,0.1);
            color: #0e4c92;
        }

        .notification-icon.success {
            background: rgba(39,174,96,0.1);
            color: #27ae60;
        }

        .notification-icon.warning {
            background: rgba(243,156,18,0.1);
            color: #f39c12;
        }

        .notification-icon.danger {
            background: rgba(231,76,60,0.1);
            color: #e74c3c;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-module {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 12px;
            background: #f8f9fa;
            color: #7f8c8d;
        }

        .notification-message {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .notification-time {
            font-size: 10px;
            color: #95a5a6;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .notification-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(14,76,146,0.1);
            text-align: center;
        }

        .view-all-link {
            color: #0e4c92;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }

        .view-all-link:hover {
            text-decoration: underline;
        }

        /* API Alert */
        .api-alert {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(14,76,146,0.15);
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 1000;
            animation: slideUp 0.3s;
            border-left: 4px solid #3498db;
        }

        .api-alert-icon {
            width: 40px;
            height: 40px;
            background: rgba(52,152,219,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
            font-size: 20px;
        }

        .api-alert-content {
            flex: 1;
        }

        .api-alert-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 3px;
        }

        .api-alert-text {
            font-size: 12px;
            color: #7f8c8d;
        }

        .api-alert-close {
            background: none;
            border: none;
            color: #95a5a6;
            cursor: pointer;
            padding: 5px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .api-alert-close:hover {
            background: #f8f9fa;
            color: #e74c3c;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            backdrop-filter: blur(5px);
            padding: 20px;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 30px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            position: relative;
            animation: modalPop 0.3s;
        }

        @keyframes modalPop {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 30px;
            height: 30px;
            background: rgba(14, 76, 146, 0.1);
            border: none;
            border-radius: 10px;
            color: #0e4c92;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-close:hover {
            background: #0e4c92;
            color: white;
            transform: rotate(90deg);
        }
    </style>
</head>
<body>
<header class="unique-header">
    <div class="header-left">
        <div class="page-indicator">
            <div class="page-dot"></div>
            <h2>
                <?php 
                if ($current_subpage) {
                    echo ucfirst(str_replace('-', ' ', $current_subpage));
                } else {
                    echo $page_title;
                }
                ?>
            </h2>
        </div>
        
        <!-- Process Flow Indicator based on current page -->
        <?php if ($current_page == 'applicant'): ?>
        
        <?php elseif ($current_page == 'recruitment'): ?>
       
        <?php endif; ?>
        
        <div class="date-badge">
            <i class="far fa-calendar"></i>
            <span><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>

    <div class="header-right">
        <!-- Search Bar -->
        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" placeholder="Search applicants, employees..." id="searchInput">
            <div class="search-shortcut">⌘K</div>
        </div>

        <!-- Header Actions -->
        <div class="header-actions">
            <button class="action-btn" onclick="showNotificationCenter()">
                <i class="far fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                <span class="notification-badge pulse"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </button>
            <button class="action-btn" onclick="showQuickActions()">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>
</header>

<!-- Notification Center -->
<div class="notification-center" id="notificationCenter">
    <div class="notification-header">
        <h3>
            <i class="far fa-bell" style="color: #0e4c92;"></i>
            Notifications
            <?php if ($unread_count > 0): ?>
            <span class="notification-badge" style="position: relative; top: 0; right: 0; display: inline-block; margin-left: 10px;"><?php echo $unread_count; ?> new</span>
            <?php endif; ?>
        </h3>
        <button class="mark-read-btn" onclick="markAllAsRead()">
            <i class="far fa-check-circle"></i> Mark all as read
        </button>
    </div>

    <div class="notification-tabs">
        <button class="notification-tab active" onclick="filterNotifications('all')" id="tab-all">
            All
            <span class="notification-tab-badge"><?php echo $unread_count; ?></span>
        </button>
        <?php foreach ($module_counts as $module => $data): ?>
            <?php if ($data['count'] > 0): ?>
            <button class="notification-tab" onclick="filterNotifications('<?php echo $module; ?>')" id="tab-<?php echo $module; ?>">
                <i class="fas <?php echo $data['icon']; ?>" style="color: <?php echo $data['color']; ?>;"></i>
                <?php echo $data['count']; ?>
            </button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="notification-list" id="notificationList">
        <?php if (empty($notifications)): ?>
        <div style="text-align: center; padding: 40px 20px; color: #95a5a6;">
            <i class="far fa-bell-slash" style="font-size: 40px; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
            <p>No notifications yet</p>
        </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
            <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>" data-module="<?php echo $notif['module'] ?? 'system'; ?>" onclick="handleNotificationClick(<?php echo $notif['id']; ?>, '<?php echo $notif['link']; ?>')">
                <div class="notification-icon <?php echo $notif['type']; ?>">
                    <?php
                    $icon = 'fa-info-circle';
                    if ($notif['type'] == 'success') $icon = 'fa-check-circle';
                    if ($notif['type'] == 'warning') $icon = 'fa-exclamation-triangle';
                    if ($notif['type'] == 'danger') $icon = 'fa-exclamation-circle';
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">
                        <?php echo htmlspecialchars($notif['title']); ?>
                        <span class="notification-module">
                            <?php echo ucfirst($notif['module'] ?? 'System'); ?>
                        </span>
                    </div>
                    <div class="notification-message">
                        <?php echo htmlspecialchars($notif['message']); ?>
                    </div>
                    <div class="notification-time">
                        <i class="far fa-clock"></i>
                        <?php echo timeAgo($notif['created_at']); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="notification-footer">
        <a href="?page=notifications" class="view-all-link">View All Notifications</a>
    </div>
</div>

<!-- API Alert (shown when new positions are available) -->
<?php if (!empty($recent_imports)): ?>
<div class="api-alert" id="apiAlert">
    <div class="api-alert-icon">
        <i class="fas fa-cloud-download-alt"></i>
    </div>
    <div class="api-alert-content">
        <div class="api-alert-title">API Updates Available</div>
        <div class="api-alert-text">
            <?php 
            $latest = $recent_imports[0];
            echo htmlspecialchars($latest['description']);
            ?>
        </div>
    </div>
    <button class="api-alert-close" onclick="document.getElementById('apiAlert').remove()">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php endif; ?>

<!-- Quick Actions Modal -->
<div class="modal" id="quickActionsModal">
    <div class="modal-content" style="max-width: 400px;">
        <button class="modal-close" onclick="hideQuickActions()">
            <i class="fas fa-times"></i>
        </button>
        <h2 style="font-size: 20px; margin-bottom: 20px;">Quick Actions</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <button class="action-btn" style="width: 100%; padding: 15px; flex-direction: column; gap: 10px; height: auto;" onclick="location.href='?page=applicant&subpage=applicant-profiles&action=new'">
                <i class="fas fa-user-plus" style="font-size: 24px;"></i>
                <span>New Applicant</span>
            </button>
            <button class="action-btn" style="width: 100%; padding: 15px; flex-direction: column; gap: 10px; height: auto;" onclick="location.href='?page=recruitment&subpage=job-posting&action=new'">
                <i class="fas fa-briefcase" style="font-size: 24px;"></i>
                <span>Post Job</span>
            </button>
            <button class="action-btn" style="width: 100%; padding: 15px; flex-direction: column; gap: 10px; height: auto;" onclick="location.href='?page=applicant&subpage=screening-evaluation'">
                <i class="fas fa-clipboard-check" style="font-size: 24px;"></i>
                <span>Screen Applicants</span>
            </button>
            <button class="action-btn" style="width: 100%; padding: 15px; flex-direction: column; gap: 10px; height: auto;" onclick="location.href='?page=recruitment&subpage=interview-scheduling&action=new'">
                <i class="fas fa-calendar-plus" style="font-size: 24px;"></i>
                <span>Schedule Interview</span>
            </button>
            <button class="action-btn" style="width: 100%; padding: 15px; flex-direction: column; gap: 10px; height: auto;" onclick="location.href='?page=recruitment&subpage=interview-panel'">
                <i class="fas fa-users-cog" style="font-size: 24px;"></i>
                <span>Panel Evaluation</span>
            </button>
            <button class="action-btn" style="width: 100%; padding: 15px; flex-direction: column; gap: 10px; height: auto;" onclick="location.href='?page=recruitment&subpage=final-selection'">
                <i class="fas fa-trophy" style="font-size: 24px;"></i>
                <span>Final Selection</span>
            </button>
            <button class="action-btn" style="width: 100%; padding: 15px; flex-direction: column; gap: 10px; height: auto;" onclick="location.href='?page=recognition&subpage=employee-month&action=nominate'">
                <i class="fas fa-award" style="font-size: 24px;"></i>
                <span>Nominate Employee</span>
            </button>
            <button class="action-btn" style="width: 100%; padding: 15px; flex-direction: column; gap: 10px; height: auto;" onclick="location.href='?page=user-management&action=new'">
                <i class="fas fa-users-cog" style="font-size: 24px;"></i>
                <span>Add User</span>
            </button>
        </div>
    </div>
</div>

<script>
// Notification Center Functions
function showNotificationCenter() {
    const center = document.getElementById('notificationCenter');
    center.classList.toggle('active');
}

function hideNotificationCenter() {
    document.getElementById('notificationCenter').classList.remove('active');
}

function filterNotifications(module) {
    // Update active tab
    document.querySelectorAll('.notification-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.getElementById(`tab-${module}`).classList.add('active');
    
    // Filter notifications
    const items = document.querySelectorAll('.notification-item');
    items.forEach(item => {
        if (module === 'all' || item.dataset.module === module) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function markAllAsRead() {
    fetch('ajax/mark_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ all: true })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function handleNotificationClick(id, link) {
    // Mark as read
    fetch('ajax/mark_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && link) {
            window.location.href = link;
        } else if (link) {
            window.location.href = link;
        }
    });
}

// Quick Actions Functions
function showQuickActions() {
    document.getElementById('quickActionsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function hideQuickActions() {
    document.getElementById('quickActionsModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close notification center when clicking outside
document.addEventListener('click', function(event) {
    const center = document.getElementById('notificationCenter');
    const bellBtn = document.querySelector('.action-btn');
    
    if (center.classList.contains('active') && 
        !center.contains(event.target) && 
        !bellBtn.contains(event.target)) {
        center.classList.remove('active');
    }
});

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideNotificationCenter();
        hideQuickActions();
    }
});

// Search functionality
document.getElementById('searchInput')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        const searchTerm = this.value;
        window.location.href = `?page=search&q=${encodeURIComponent(searchTerm)}`;
    }
});

// Auto-hide API alert after 10 seconds
setTimeout(() => {
    const alert = document.getElementById('apiAlert');
    if (alert) {
        alert.style.animation = 'slideUp 0.3s reverse';
        setTimeout(() => alert.remove(), 300);
    }
}, 10000);
</script>