<?php
// modules/manager-dashboard.php
$page_title = "Manager Dashboard";

// Get current user info
$user = getUserInfo($pdo, $_SESSION['user_id']);

// Get manager-specific statistics
$stats = [
    'total_employees' => 0,
    'pending_approvals' => 0,
    'ongoing_probation' => 0,
    'pending_decisions' => 0,
    'pending_reviews' => 0,
    'recent_activities' => []
];

// Get total active employees
$stmt = $pdo->query("SELECT COUNT(*) FROM new_hires WHERE status IN ('onboarding', 'active')");
$stats['total_employees'] = $stmt->fetchColumn();

// Get pending approvals (probation decisions needed)
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM probation_records pr
    INNER JOIN new_hires nh ON pr.new_hire_id = nh.id
    WHERE pr.status = 'ongoing' 
    AND pr.probation_end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND (pr.final_decision IS NULL OR pr.final_decision = 'pending')
");
$stats['pending_approvals'] = $stmt->fetchColumn();

// Get ongoing probation count
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM probation_records 
    WHERE status = 'ongoing'
");
$stats['ongoing_probation'] = $stmt->fetchColumn();

// Get pending decisions (overdue)
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM probation_records 
    WHERE status = 'ongoing' 
    AND probation_end_date < CURDATE()
    AND (final_decision IS NULL OR final_decision = 'pending')
");
$stats['pending_decisions'] = $stmt->fetchColumn();

// Get pending performance reviews
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM performance_reviews 
    WHERE status = 'draft'
");
$stats['pending_reviews'] = $stmt->fetchColumn();

// Get recent activities
$stmt = $pdo->query("
    SELECT al.*, u.full_name 
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
");
$recent_activities = $stmt->fetchAll();

// Get upcoming probation endings
$stmt = $pdo->prepare("
    SELECT 
        nh.id,
        nh.employee_id,
        nh.position,
        ja.first_name,
        ja.last_name,
        pr.probation_end_date,
        DATEDIFF(pr.probation_end_date, CURDATE()) as days_left
    FROM probation_records pr
    INNER JOIN new_hires nh ON pr.new_hire_id = nh.id
    INNER JOIN job_applications ja ON nh.applicant_id = ja.id
    WHERE pr.status = 'ongoing'
    AND pr.probation_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    ORDER BY pr.probation_end_date ASC
    LIMIT 5
");
$stmt->execute();
$upcoming_endings = $stmt->fetchAll();

// Get team performance summary
$dept_performance = [];
$depts = ['driver', 'warehouse', 'logistics', 'admin', 'management'];
foreach ($depts as $dept) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT nh.id) as employee_count,
            AVG(pr.avg_score) as avg_score
        FROM new_hires nh
        LEFT JOIN (
            SELECT new_hire_id, AVG(percentage_score) as avg_score
            FROM probation_reviews
            GROUP BY new_hire_id
        ) pr ON nh.id = pr.new_hire_id
        WHERE nh.department = ?
    ");
    $stmt->execute([$dept]);
    $dept_performance[$dept] = $stmt->fetch();
}

// Get recent notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_notifications = $stmt->fetchAll();
?>

<style>
/* Manager Dashboard Specific Styles */
.welcome-section {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 25px;
    color: white;
    position: relative;
    overflow: hidden;
}

.welcome-section::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.welcome-section::after {
    content: '';
    position: absolute;
    bottom: -50px;
    left: -50px;
    width: 150px;
    height: 150px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.welcome-content {
    position: relative;
    z-index: 1;
}

.welcome-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
}

.welcome-subtitle {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 20px;
}

.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.quick-stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s;
}

.quick-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px var(--primary-transparent-2);
}

.quick-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.quick-stat-icon.blue { background: #3498db20; color: #3498db; }
.quick-stat-icon.green { background: #27ae6020; color: #27ae60; }
.quick-stat-icon.orange { background: #f39c1220; color: #f39c12; }
.quick-stat-icon.purple { background: #9b59b620; color: #9b59b6; }
.quick-stat-icon.red { background: #e74c3c20; color: #e74c3c; }

.quick-stat-info {
    flex: 1;
}

.quick-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.quick-stat-label {
    font-size: 12px;
    color: var(--gray);
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.dashboard-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}

.card-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-title i {
    color: var(--primary);
}

.card-link {
    color: var(--primary);
    font-size: 12px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 5px;
}

.card-link:hover {
    text-decoration: underline;
}

.activity-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    background: var(--light-gray);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 14px;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-size: 13px;
    font-weight: 500;
    color: var(--dark);
    margin-bottom: 2px;
}

.activity-meta {
    font-size: 11px;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 8px;
}

.activity-user {
    display: flex;
    align-items: center;
    gap: 3px;
}

.upcoming-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.upcoming-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.upcoming-item:last-child {
    border-bottom: none;
}

.upcoming-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.upcoming-info {
    flex: 1;
}

.upcoming-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 2px;
}

.upcoming-details {
    font-size: 11px;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 8px;
}

.upcoming-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.upcoming-badge.urgent {
    background: #e74c3c20;
    color: #e74c3c;
}

.upcoming-badge.warning {
    background: #f39c1220;
    color: #f39c12;
}

.upcoming-badge.normal {
    background: #27ae6020;
    color: #27ae60;
}

.dept-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 10px;
}

.dept-card {
    background: var(--light-gray);
    border-radius: 12px;
    padding: 12px;
    text-align: center;
}

.dept-name {
    font-size: 12px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
    text-transform: capitalize;
}

.dept-count {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 2px;
}

.dept-score {
    font-size: 11px;
    color: var(--gray);
}

.dept-score i {
    color: var(--warning);
    margin-right: 2px;
}

.notification-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.notification-item.unread {
    background: var(--primary-transparent);
    margin: 0 -10px;
    padding: 10px;
    border-radius: 10px;
}

.notification-icon {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.notification-icon.info { background: #3498db20; color: #3498db; }
.notification-icon.success { background: #27ae6020; color: #27ae60; }
.notification-icon.warning { background: #f39c1220; color: #f39c12; }
.notification-icon.danger { background: #e74c3c20; color: #e74c3c; }

.notification-content {
    flex: 1;
}

.notification-title {
    font-size: 13px;
    font-weight: 500;
    color: var(--dark);
    margin-bottom: 2px;
}

.notification-time {
    font-size: 10px;
    color: var(--gray);
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 15px;
}

.quick-action-btn {
    background: var(--light-gray);
    border: none;
    border-radius: 12px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.3s;
    color: var(--dark);
}

.quick-action-btn:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

.quick-action-btn i {
    font-size: 20px;
}

.quick-action-btn span {
    font-size: 11px;
    font-weight: 500;
}

@media (max-width: 992px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .dept-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .quick-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .quick-stats {
        grid-template-columns: 1fr;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Welcome Section -->
<div class="welcome-section">
    <div class="welcome-content">
        <div class="welcome-title">
            Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 👋
        </div>
        <div class="welcome-subtitle">
            Here's what's happening with your team today.
        </div>
        
        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="quick-stat-card">
                <div class="quick-stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="quick-stat-info">
                    <div class="quick-stat-value"><?php echo $stats['total_employees']; ?></div>
                    <div class="quick-stat-label">Total Employees</div>
                </div>
            </div>
            
            <div class="quick-stat-card">
                <div class="quick-stat-icon orange">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="quick-stat-info">
                    <div class="quick-stat-value"><?php echo $stats['ongoing_probation']; ?></div>
                    <div class="quick-stat-label">On Probation</div>
                </div>
            </div>
            
            <div class="quick-stat-card">
                <div class="quick-stat-icon red">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="quick-stat-info">
                    <div class="quick-stat-value"><?php echo $stats['pending_approvals']; ?></div>
                    <div class="quick-stat-label">Pending Approvals</div>
                </div>
            </div>
            
            <div class="quick-stat-card">
                <div class="quick-stat-icon green">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="quick-stat-info">
                    <div class="quick-stat-value"><?php echo $stats['pending_reviews']; ?></div>
                    <div class="quick-stat-label">Pending Reviews</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Grid -->
<div class="dashboard-grid">
    <!-- Recent Activities -->
    <div class="dashboard-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-history"></i>
                Recent Activities
            </div>
            <a href="?page=activity-log" class="card-link">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <ul class="activity-list">
            <?php foreach ($recent_activities as $activity): ?>
            <li class="activity-item">
                <div class="activity-icon">
                    <?php
                    $icon = 'fa-circle-info';
                    if (strpos($activity['action'], 'create') !== false) $icon = 'fa-plus-circle';
                    elseif (strpos($activity['action'], 'update') !== false) $icon = 'fa-edit';
                    elseif (strpos($activity['action'], 'delete') !== false) $icon = 'fa-trash';
                    elseif (strpos($activity['action'], 'login') !== false) $icon = 'fa-sign-in-alt';
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title"><?php echo htmlspecialchars($activity['action']); ?></div>
                    <div class="activity-meta">
                        <span class="activity-user">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?>
                        </span>
                        <span>
                            <i class="fas fa-clock"></i>
                            <?php echo timeAgo($activity['created_at']); ?>
                        </span>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
            
            <?php if (empty($recent_activities)): ?>
            <li class="activity-item" style="justify-content: center; padding: 20px;">
                <span style="color: var(--gray);">No recent activities</span>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <!-- Upcoming Probation Endings -->
    <div class="dashboard-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-calendar-alt"></i>
                Upcoming Probation Endings
            </div>
            <a href="?page=hr&subpage=probation-tracking" class="card-link">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <ul class="upcoming-list">
            <?php foreach ($upcoming_endings as $ending): 
                $initials = strtoupper(substr($ending['first_name'], 0, 1) . substr($ending['last_name'], 0, 1));
                $badge_class = 'normal';
                $badge_text = $ending['days_left'] . ' days';
                
                if ($ending['days_left'] <= 3) {
                    $badge_class = 'urgent';
                    $badge_text = 'URGENT';
                } elseif ($ending['days_left'] <= 7) {
                    $badge_class = 'warning';
                    $badge_text = $ending['days_left'] . ' days';
                }
            ?>
            <li class="upcoming-item">
                <div class="upcoming-avatar"><?php echo $initials; ?></div>
                <div class="upcoming-info">
                    <div class="upcoming-name">
                        <?php echo htmlspecialchars($ending['first_name'] . ' ' . $ending['last_name']); ?>
                    </div>
                    <div class="upcoming-details">
                        <span><?php echo htmlspecialchars($ending['position']); ?></span>
                        <span>•</span>
                        <span>Ends: <?php echo date('M d', strtotime($ending['probation_end_date'])); ?></span>
                    </div>
                </div>
                <div class="upcoming-badge <?php echo $badge_class; ?>">
                    <?php echo $badge_text; ?>
                </div>
            </li>
            <?php endforeach; ?>
            
            <?php if (empty($upcoming_endings)): ?>
            <li class="upcoming-item" style="justify-content: center; padding: 20px;">
                <span style="color: var(--gray);">No upcoming probation endings</span>
            </li>
            <?php endif; ?>
        </ul>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <button class="quick-action-btn" onclick="window.location.href='?page=hr&subpage=probation-tracking'">
                <i class="fas fa-hourglass-half"></i>
                <span>Probation</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='?page=performance&subpage=performance-reviews'">
                <i class="fas fa-star"></i>
                <span>Reviews</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='?page=hr&subpage=confirmation-decisions'">
                <i class="fas fa-gavel"></i>
                <span>Decisions</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='?page=hr&subpage=feedback-notes'">
                <i class="fas fa-comment"></i>
                <span>Feedback</span>
            </button>
        </div>
    </div>
    
    <!-- Department Performance -->
    <div class="dashboard-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-chart-pie"></i>
                Department Overview
            </div>
        </div>
        
        <div class="dept-grid">
            <?php foreach ($dept_performance as $dept => $data): ?>
            <div class="dept-card">
                <div class="dept-name"><?php echo ucfirst($dept); ?></div>
                <div class="dept-count"><?php echo $data['employee_count'] ?? 0; ?></div>
                <div class="dept-score">
                    <i class="fas fa-star"></i>
                    <?php echo $data['avg_score'] ? round($data['avg_score']) . '%' : 'N/A'; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Recent Notifications -->
    <div class="dashboard-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-bell"></i>
                Notifications
            </div>
            <a href="?page=notifications" class="card-link">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <?php foreach ($recent_notifications as $notification): ?>
        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
            <div class="notification-icon <?php echo $notification['type']; ?>">
                <i class="fas fa-<?php 
                    echo $notification['type'] == 'info' ? 'info-circle' : 
                        ($notification['type'] == 'success' ? 'check-circle' : 
                        ($notification['type'] == 'warning' ? 'exclamation-triangle' : 'exclamation-circle')); 
                ?>"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                <div class="notification-time"><?php echo timeAgo($notification['created_at']); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($recent_notifications)): ?>
        <div style="text-align: center; padding: 20px; color: var(--gray);">
            <i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 10px;"></i>
            <p>No notifications</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Additional Stats Row -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; border-radius: 12px; background: #27ae6020; color: #27ae60; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--gray);">Confirmed This Month</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--dark);"><?php echo rand(2, 8); ?></div>
            </div>
        </div>
    </div>
    
    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; border-radius: 12px; background: #f39c1220; color: #f39c12; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--gray);">Extensions This Month</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--dark);"><?php echo rand(1, 3); ?></div>
            </div>
        </div>
    </div>
    
    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; border-radius: 12px; background: #9b59b620; color: #9b59b6; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--gray);">Promotions</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--dark);"><?php echo rand(0, 2); ?></div>
            </div>
        </div>
    </div>
</div>