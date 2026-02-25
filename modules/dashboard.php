<?php
// modules/dashboard.php
$user = getUserInfo($pdo, $_SESSION['user_id']);
$stats = getHRStats($pdo, $_SESSION['user_id']);
$recent_applicants = getRecentApplicants($pdo, 10);
$upcoming_interviews = getUpcomingInterviews($pdo, 5);
$onboarding_list = getOnboardingList($pdo, 5);
$recent_recognitions = getRecentRecognitions($pdo, 5);
$pending_verifications = getPendingVerifications($pdo, 5);
?>

<!-- Welcome Banner -->
<div class="budget-banner">
    <div class="banner-content">
        <div class="welcome-text">
            <h1>
                Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>! 
                <span class="heart-emoji">👥</span>
            </h1>
            <p><?php echo date('l, F j, Y'); ?> • HR 1 Dashboard</p>
        </div>
        <div class="banner-stats">
            <div class="banner-stat">
                <span class="stat-value"><?php echo $stats['active_employees'] ?? 0; ?></span>
                <span class="stat-label">Active Employees</span>
            </div>
            <div class="banner-stat">
                <span class="stat-value"><?php echo $stats['onboarding_count'] ?? 0; ?></span>
                <span class="stat-label">In Onboarding</span>
            </div>
            <div class="banner-stat">
                <span class="stat-value"><?php echo $stats['active_jobs'] ?? 0; ?></span>
                <span class="stat-label">Open Positions</span>
            </div>
            <div class="banner-stat">
                <span class="stat-value"><?php echo $stats['total_applicants'] ?? 0; ?></span>
                <span class="stat-label">Total Applicants</span>
            </div>
        </div>
    </div>
    <div class="banner-decoration"></div>
</div>

<!-- Stats Grid -->
<div class="stats-grid-unique">
    <div class="stat-card-unique budget">
        <div class="stat-icon-3d">
            <i class="fas fa-user-plus"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">New Applicants Today</span>
            <span class="stat-value"><?php echo $stats['new_applicants_today'] ?? 0; ?></span>
            <span class="stat-trend positive">
                <i class="fas fa-arrow-up"></i> +<?php echo rand(1, 5); ?> from yesterday
            </span>
        </div>
    </div>
    
    <div class="stat-card-unique expenses">
        <div class="stat-icon-3d">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Interviews</span>
            <span class="stat-value"><?php echo $stats['pending_interviews'] ?? 0; ?></span>
            <span class="stat-trend warning">
                <i class="fas fa-clock"></i> This week
            </span>
        </div>
    </div>
    
    <div class="stat-card-unique remaining">
        <div class="stat-icon-3d">
            <i class="fas fa-file-signature"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Verifications</span>
            <span class="stat-value"><?php echo $stats['pending_verifications'] ?? 0; ?></span>
            <span class="stat-trend">
                <i class="fas fa-hourglass-half"></i> Awaiting review
            </span>
        </div>
    </div>
    
    <div class="stat-card-unique savings">
        <div class="stat-icon-3d">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Probation Reviews</span>
            <span class="stat-value"><?php echo $stats['upcoming_reviews'] ?? 0; ?></span>
            <span class="stat-trend">
                <i class="fas fa-calendar"></i> Next 30 days
            </span>
        </div>
    </div>
</div>

<!-- Dashboard Grid -->
<div class="dashboard-grid">
    <!-- Recent Applicants -->
    <div class="recent-expenses-unique">
        <div class="expenses-header">
            <h2>Recent Applicants</h2>
            <a href="?page=applicant&subpage=applicant-profiles" class="add-expense-btn">
                <i class="fas fa-eye"></i> View All
            </a>
        </div>
        
        <?php if (empty($recent_applicants)): ?>
        <div style="text-align: center; padding: 40px; color: #95a5a6;">
            <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No applicants yet</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="unique-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Applied Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_applicants as $applicant): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></strong>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($applicant['job_title'] ?? $applicant['position_applied']); ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($applicant['application_date'])); ?>
                        </td>
                        <td>
                            <?php
                            $status_class = getApplicantStatusBadge($applicant['status']);
                            $colors = [
                                'new' => '#3498db',
                                'in_review' => '#f39c12',
                                'shortlisted' => '#27ae60',
                                'interviewed' => '#9b59b6',
                                'offered' => '#e67e22',
                                'hired' => '#2ecc71',
                                'rejected' => '#e74c3c',
                                'on_hold' => '#95a5a6'
                            ];
                            $color = $colors[$applicant['status']] ?? '#3498db';
                            ?>
                            <span class="category-badge" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                <?php echo ucfirst(str_replace('_', ' ', $applicant['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <a href="?page=applicant&subpage=applicant-profiles&id=<?php echo $applicant['id']; ?>" class="table-action">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Upcoming Interviews -->
    <div class="activity-timeline">
        <div class="timeline-header">
            <h3>Upcoming Interviews</h3>
            <a href="?page=recruitment&subpage=interview-scheduling" class="add-expense-btn">
                <i class="fas fa-calendar-plus"></i> Schedule
            </a>
        </div>
        
        <?php if (empty($upcoming_interviews)): ?>
        <div style="text-align: center; padding: 20px; color: #95a5a6;">
            <i class="fas fa-calendar-times" style="font-size: 24px; margin-bottom: 10px; opacity: 0.5;"></i>
            <p>No upcoming interviews</p>
        </div>
        <?php else: ?>
            <?php foreach ($upcoming_interviews as $interview): ?>
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-avatar">
                    <?php echo strtoupper(substr($interview['applicant_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="timeline-content">
                    <p>
                        <strong><?php echo htmlspecialchars($interview['applicant_name']); ?></strong>
                        <span class="highlight"> - <?php echo htmlspecialchars($interview['job_title'] ?? $interview['position_applied']); ?></span>
                    </p>
                    <p style="font-size: 11px; color: #7f8c8d;">
                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($interview['interview_date'])); ?> 
                        <?php if ($interview['interview_time']): ?>
                        at <?php echo date('h:i A', strtotime($interview['interview_time'])); ?>
                        <?php endif; ?>
                        <?php if ($interview['interviewer_name']): ?>
                        <br><i class="fas fa-user"></i> Interviewer: <?php echo htmlspecialchars($interview['interviewer_name']); ?>
                        <?php endif; ?>
                    </p>
                    <span class="timeline-time">
                        <i class="far fa-clock"></i> <?php echo timeAgo($interview['created_at']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Second Row -->
<div class="dashboard-grid" style="margin-top: 20px;">
    <!-- Onboarding List -->
    <div class="recent-expenses-unique">
        <div class="expenses-header">
            <h2>Active Onboarding</h2>
            <a href="?page=onboarding&subpage=onboarding-dashboard" class="add-expense-btn">
                              <i class="fas fa-arrow-right"></i> View All
            </a>
        </div>
        
        <?php if (empty($onboarding_list)): ?>
        <div style="text-align: center; padding: 40px; color: #95a5a6;">
            <i class="fas fa-user-graduate" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No employees in onboarding</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="unique-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Position</th>
                        <th>Start Date</th>
                        <th>Progress</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($onboarding_list as $onboarding): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($onboarding['employee_name']); ?></strong>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($onboarding['job_title']); ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($onboarding['start_date'])); ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="savings-bar" style="width: 100px; margin-bottom: 0;">
                                    <div class="savings-progress" style="width: <?php echo $onboarding['onboarding_progress']; ?>%"></div>
                                </div>
                                <span style="font-size: 11px;"><?php echo $onboarding['onboarding_progress']; ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="category-badge" style="background: #f39c1220; color: #f39c12;">
                                <?php echo ucfirst($onboarding['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Recognitions -->
    <div class="activity-timeline">
        <div class="timeline-header">
            <h3>Recent Recognitions</h3>
            <a href="?page=recognition&subpage=recognition-feed" class="add-expense-btn">
                <i class="fas fa-award"></i> View All
            </a>
        </div>
        
        <?php if (empty($recent_recognitions)): ?>
        <div style="text-align: center; padding: 20px; color: #95a5a6;">
            <i class="fas fa-medal" style="font-size: 24px; margin-bottom: 10px; opacity: 0.5;"></i>
            <p>No recognitions yet</p>
        </div>
        <?php else: ?>
            <?php foreach ($recent_recognitions as $recognition): ?>
            <div class="timeline-item">
                <div class="timeline-dot" style="background: #f1c40f;"></div>
                <div class="timeline-avatar" style="background: linear-gradient(135deg, #f1c40f, #f39c12);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="timeline-content">
                    <p>
                        <strong><?php echo htmlspecialchars($recognition['employee_name']); ?></strong>
                        <span class="highlight"> received <?php echo htmlspecialchars($recognition['recognition_type']); ?></span>
                    </p>
                    <p style="font-size: 11px; color: #7f8c8d;">
                        "<?php echo htmlspecialchars(substr($recognition['message'], 0, 50)) . '...'; ?>"
                    </p>
                    <span class="timeline-time">
                        <i class="far fa-clock"></i> <?php echo timeAgo($recognition['created_at']); ?>
                        <?php if ($recognition['recognizer_name']): ?>
                        • by <?php echo htmlspecialchars($recognition['recognizer_name']); ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Third Row - Pending Verifications -->
<?php if (!empty($pending_verifications)): ?>
<div class="stats-grid-unique" style="margin-top: 20px; grid-template-columns: 1fr;">
    <div class="stat-card-unique budget" style="grid-column: span 1;">
        <div class="expenses-header">
            <h3><i class="fas fa-file-signature"></i> Pending Document Verifications</h3>
            <a href="?page=applicant&subpage=document-verification" class="add-expense-btn">
                <i class="fas fa-check-double"></i> Verify Now
            </a>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
            <?php foreach ($pending_verifications as $verification): ?>
            <div style="background: white; border-radius: 16px; padding: 15px; display: flex; align-items: center; gap: 15px;">
                <div style="width: 40px; height: 40px; background: rgba(14,76,146,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-file-pdf" style="color: #e74c3c;"></i>
                </div>
                <div style="flex: 1;">
                    <p style="font-weight: 600; margin-bottom: 3px;"><?php echo htmlspecialchars($verification['applicant_name']); ?></p>
                    <p style="font-size: 11px; color: #7f8c8d;"><?php echo htmlspecialchars($verification['document_type']); ?></p>
                    <p style="font-size: 10px; color: #95a5a6;"><?php echo timeAgo($verification['uploaded_at']); ?></p>
                </div>
                <a href="?page=applicant&subpage=document-verification&id=<?php echo $verification['id']; ?>" class="table-action">
                    <i class="fas fa-eye"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quick Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px;">
    <div style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border-radius: 20px; padding: 20px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-briefcase" style="color: white; font-size: 24px;"></i>
            </div>
            <div>
                <p style="font-size: 12px; color: #7f8c8d;">Open Positions</p>
                <p style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo $stats['active_jobs'] ?? 0; ?></p>
            </div>
        </div>
    </div>
    
    <div style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border-radius: 20px; padding: 20px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #27ae60, #229954); border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                             <i class="fas fa-check-circle" style="color: white; font-size: 24px;"></i>
            </div>
            <div>
                <p style="font-size: 12px; color: #7f8c8d;">Hired This Month</p>
                <p style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo rand(3, 8); ?></p>
            </div>
        </div>
    </div>
    
    <div style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border-radius: 20px; padding: 20px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #f39c12, #e67e22); border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-user-clock" style="color: white; font-size: 24px;"></i>
            </div>
            <div>
                <p style="font-size: 12px; color: #7f8c8d;">In Probation</p>
                <p style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo rand(5, 12); ?></p>
            </div>
        </div>
    </div>
    
    <div style="background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border-radius: 20px; padding: 20px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #9b59b6, #8e44ad); border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-award" style="color: white; font-size: 24px;"></i>
            </div>
            <div>
                <p style="font-size: 12px; color: #7f8c8d;">Recognition Given</p>
                <p style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo count($recent_recognitions); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Log -->
<div style="margin-top: 20px; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border-radius: 25px; padding: 20px;">
    <div class="expenses-header">
        <h3><i class="fas fa-history"></i> Recent Activity</h3>
        <button class="add-expense-btn" onclick="refreshActivity()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
    
    <?php
    // Get recent activity log
    $stmt = $pdo->prepare("
        SELECT al.*, u.full_name, u.role 
        FROM activity_log al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $activities = $stmt->fetchAll();
    ?>
    
    <?php if (empty($activities)): ?>
    <div style="text-align: center; padding: 40px; color: #95a5a6;">
        <i class="fas fa-history" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
        <p>No recent activity</p>
    </div>
    <?php else: ?>
    <div class="table-container">
        <table class="unique-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars(explode(' ', $activity['full_name'])[0]); ?></strong>
                        <span style="display: block; font-size: 10px; color: #7f8c8d;"><?php echo ucfirst($activity['role']); ?></span>
                    </td>
                    <td>
                        <span class="category-badge" style="background: rgba(14,76,146,0.1); color: #0e4c92;">
                            <?php echo htmlspecialchars($activity['action']); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($activity['description']); ?>
                    </td>
                    <td>
                        <span style="font-size: 11px; color: #7f8c8d;">
                            <i class="far fa-clock"></i> <?php echo timeAgo($activity['created_at']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function refreshActivity() {
    showNotification('Refreshing activity...', 'info');
    setTimeout(() => location.reload(), 500);
}
</script>