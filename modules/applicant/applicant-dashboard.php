<?php
// modules/applicant/applicant-dashboard.php
$page_title = "Applicant Dashboard";

// Get applicant statistics from job_applications table
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
        SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review,
        SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
        SUM(CASE WHEN status = 'interviewed' THEN 1 ELSE 0 END) as interviewed,
        SUM(CASE WHEN status = 'offered' THEN 1 ELSE 0 END) as offered,
        SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM job_applications
");
$applicant_stats = $stmt->fetch();

// Get applicants by position from job_applications table
$stmt = $pdo->query("
    SELECT 
        jp.title as position_title, 
        COUNT(ja.id) as count 
    FROM job_applications ja
    LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
    GROUP BY jp.title 
    ORDER BY count DESC 
    LIMIT 5
");
$positions = $stmt->fetchAll();

// If no positions with job titles, get from the job_applications table directly
if (empty($positions) || !isset($positions[0]['position_title']) || empty($positions[0]['position_title'])) {
    $stmt = $pdo->query("
        SELECT 
            'Various Positions' as position_title, 
            COUNT(*) as count 
        FROM job_applications 
        WHERE job_posting_id IS NULL
    ");
    $other_positions = $stmt->fetch();
    
    // Also get individual counts by job_posting_id for those with jobs
    $stmt = $pdo->query("
        SELECT 
            CONCAT('Job #', job_posting_id) as position_title,
            COUNT(*) as count 
        FROM job_applications 
        WHERE job_posting_id IS NOT NULL
        GROUP BY job_posting_id
        ORDER BY count DESC 
        LIMIT 5
    ");
    $positions = $stmt->fetchAll();
    
    // If there are applications without job postings, add them
    if ($other_positions && $other_positions['count'] > 0) {
        $positions[] = $other_positions;
    }
}

// Function to get recent applicants from job_applications with photos
function getRecentApplicantsFromJobApps($pdo, $limit = 5) {
    $stmt = $pdo->prepare("
        SELECT ja.*, 
               jp.title as job_title,
               jp.job_code
        FROM job_applications ja
        LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
        ORDER BY ja.applied_at DESC 
        LIMIT " . intval($limit)
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// Helper function to get applicant photo or fallback to initials
function getApplicantPhoto($applicant) {
    if (!empty($applicant['photo_path']) && file_exists($applicant['photo_path'])) {
        return htmlspecialchars($applicant['photo_path']);
    }
    return null;
}

// Get recent applicants
$recent = getRecentApplicantsFromJobApps($pdo, 5);
?>

<style>
:root {
    --primary-color: #0e4c92;
    --primary-light: #1e5ca8;
    --primary-dark: #0a3a70;
    --primary-transparent: rgba(14, 76, 146, 0.1);
    --primary-transparent-2: rgba(14, 76, 146, 0.2);
}

/* Stats Grid Styles */
.stats-grid-unique {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card-unique {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.03);
}

.stat-card-unique:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(14, 76, 146, 0.15);
}

.stat-card-unique.budget { background: linear-gradient(135deg, #0e4c92 0%, #1e5ca8 100%); }
.stat-card-unique.expenses { background: linear-gradient(135deg, #0e4c92 0%, #2a6abc 100%); }
.stat-card-unique.remaining { background: linear-gradient(135deg, #0e4c92 0%, #3578d0 100%); }
.stat-card-unique.savings { background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%); }

.stat-icon-3d {
    width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.2);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.stat-content {
    flex: 1;
}

.stat-label {
    display: block;
    font-size: 14px;
    color: rgba(255,255,255,0.8);
    margin-bottom: 5px;
    font-weight: 500;
}

.stat-value {
    display: block;
    font-size: 32px;
    font-weight: 700;
    color: white;
    line-height: 1.2;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

/* Recent Expenses Card */
.recent-expenses-unique {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.expenses-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.expenses-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.expenses-header h2 i {
    color: #0e4c92 !important;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
}

.unique-table {
    width: 100%;
    border-collapse: collapse;
}

.unique-table th {
    text-align: left;
    padding: 12px 15px;
    background: #f8fafd;
    color: #64748b;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.unique-table td {
    padding: 15px;
    border-bottom: 1px solid #eef2f6;
    color: #2c3e50;
    font-size: 14px;
}

/* Applicant Photo Styles */
.applicant-photo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 8px rgba(14, 76, 146, 0.2);
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}

.applicant-photo[src=""] {
    opacity: 0.8;
}

.applicant-photo:not([src]), 
.applicant-photo[src=""], 
.applicant-photo[src="null"] {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
}

.applicant-photo-error {
    display: none;
}

.category-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.table-action {
    color: #94a3b8;
    font-size: 16px;
    transition: all 0.3s ease;
    padding: 8px;
    border-radius: 8px;
}

.table-action:hover {
    color: #0e4c92;
    background: rgba(14, 76, 146, 0.1);
}

/* Activity Timeline */
.activity-timeline {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.timeline-header {
    margin-bottom: 20px;
}

.timeline-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.timeline-header h3 i {
    color: #0e4c92 !important;
}

.savings-bar {
    height: 8px;
    background: #eef2f6;
    border-radius: 4px;
    overflow: hidden;
}

.savings-progress {
    height: 100%;
    background: linear-gradient(90deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* Buttons */
.add-expense-btn {
    background: #f8fafd;
    color: #0e4c92;
    padding: 8px 16px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.add-expense-btn:hover {
    background: #0e4c92;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(14, 76, 146, 0.3);
}

.submit-btn {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-block;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.3);
}

/* Status Colors - Using your original colors */
.status-new { 
    background: #3498db20; 
    color: #3498db; 
}
.status-in_review { 
    background: #f39c1220; 
    color: #f39c12; 
}
.status-shortlisted { 
    background: #27ae6020; 
    color: #27ae60; 
}
.status-interviewed { 
    background: #9b59b620; 
    color: #9b59b6; 
}
.status-offered { 
    background: #e67e2220; 
    color: #e67e22; 
}
.status-hired { 
    background: #2ecc7120; 
    color: #2ecc71; 
}
.status-rejected { 
    background: #e74c3c20; 
    color: #e74c3c; 
}

/* Quick action buttons with primary color */
.quick-actions {
    display: flex;
    gap: 12px;
    margin-top: 25px;
    flex-wrap: wrap;
    justify-content: flex-start;
}

.quick-action-btn {
    background: #f8fafd;
    color: #0e4c92;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.quick-action-btn:hover {
    background: #0e4c92;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(14, 76, 146, 0.3);
}

.quick-action-btn.primary {
    background: #0e4c92;
    color: white;
}

.quick-action-btn.primary:hover {
    background: #0a3a70;
    box-shadow: 0 5px 15px rgba(14, 76, 146, 0.4);
}

/* Links with primary color */
a {
    color: #0e4c92;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid-unique {
        grid-template-columns: 1fr;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

/* Image error handling */
.img-error-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
}
</style>

<!-- JavaScript for handling image errors -->
<script>
function handleImageError(img) {
    // Don't process if already handled
    if (img.getAttribute('data-error-handled') === 'true') return;
    
    // Mark as handled
    img.setAttribute('data-error-handled', 'true');
    
    // Get the initials from data attribute or generate from name
    const firstName = img.getAttribute('data-first-name') || '';
    const lastName = img.getAttribute('data-last-name') || '';
    const initials = (firstName.charAt(0) + lastName.charAt(0)).toUpperCase() || '?';
    
    // Create fallback element
    const parent = img.parentNode;
    const fallback = document.createElement('div');
    fallback.className = 'img-error-fallback';
    fallback.textContent = initials;
    
    // Replace image with fallback
    parent.replaceChild(fallback, img);
}
</script>

<div class="stats-grid-unique">
    <div class="stat-card-unique budget">
        <div class="stat-icon-3d">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Total Applicants</span>
            <span class="stat-value"><?php echo number_format($applicant_stats['total'] ?? 0); ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique expenses">
        <div class="stat-icon-3d">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">New Applicants</span>
            <span class="stat-value"><?php echo number_format($applicant_stats['new'] ?? 0); ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique remaining">
        <div class="stat-icon-3d">
            <i class="fas fa-search"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">In Review</span>
            <span class="stat-value"><?php echo number_format($applicant_stats['in_review'] ?? 0); ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique savings">
        <div class="stat-icon-3d">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Shortlisted</span>
            <span class="stat-value"><?php echo number_format($applicant_stats['shortlisted'] ?? 0); ?></span>
        </div>
    </div>
</div>

<div class="stats-grid-unique" style="margin-top: -10px;">
    <div class="stat-card-unique remaining">
        <div class="stat-icon-3d">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Interviewed</span>
            <span class="stat-value"><?php echo number_format($applicant_stats['interviewed'] ?? 0); ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique savings">
        <div class="stat-icon-3d">
            <i class="fas fa-file-signature"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Offered</span>
            <span class="stat-value"><?php echo number_format($applicant_stats['offered'] ?? 0); ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique budget">
        <div class="stat-icon-3d">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Hired</span>
            <span class="stat-value"><?php echo number_format($applicant_stats['hired'] ?? 0); ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique expenses">
        <div class="stat-icon-3d">
            <i class="fas fa-user-times"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Rejected</span>
            <span class="stat-value"><?php echo number_format($applicant_stats['rejected'] ?? 0); ?></span>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Recent Applicants List -->
    <div class="recent-expenses-unique">
        <div class="expenses-header">
            <h2><i class="fas fa-clock" style="margin-right: 8px;"></i> Recent Applicants</h2>
            <a href="?page=applicant&subpage=applicant-profiles" class="add-expense-btn">
                <i class="fas fa-list"></i> View All
            </a>
        </div>
        
        <?php if (empty($recent)): ?>
        <div style="text-align: center; padding: 60px 20px; color: #95a5a6;">
            <i class="fas fa-users" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
            <p style="font-size: 16px; font-weight: 500;">No applicants yet</p>
            <p style="font-size: 13px; margin-top: 10px;">When candidates apply, they will appear here</p>
            <a href="?page=recruitment&subpage=job-posting" class="add-expense-btn" style="margin-top: 20px; padding: 10px 20px;">
                <i class="fas fa-plus"></i> Create Job Posting
            </a>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="unique-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Position</th>
                        <th>Applied</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $applicant): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php 
                                $photoPath = getApplicantPhoto($applicant);
                                $firstName = $applicant['first_name'] ?? '';
                                $lastName = $applicant['last_name'] ?? '';
                                $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
                                $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                                
                                if ($photoPath): ?>
                                    <img src="<?php echo $photoPath; ?>" 
                                         alt="<?php echo htmlspecialchars($fullName); ?>"
                                         class="applicant-photo"
                                         onerror="handleImageError(this)"
                                         data-first-name="<?php echo htmlspecialchars($firstName); ?>"
                                         data-last-name="<?php echo htmlspecialchars($lastName); ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="applicant-photo" style="display: flex; align-items: center; justify-content: center;">
                                        <?php echo $initials; ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($fullName); ?></strong>
                                    <?php if (!empty($applicant['email'])): ?>
                                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                        <?php echo htmlspecialchars($applicant['email']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($applicant['job_title'])): ?>
                                <span style="font-weight: 500;"><?php echo htmlspecialchars($applicant['job_title']); ?></span>
                                <?php if (!empty($applicant['job_code'])): ?>
                                <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($applicant['job_code']); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #64748b;">General Application</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($applicant['applied_at'])): ?>
                            <span style="font-size: 13px; font-weight: 500;"><?php echo date('M d, Y', strtotime($applicant['applied_at'])); ?></span>
                            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                <?php echo timeAgo($applicant['applied_at']); ?>
                            </div>
                            <?php else: ?>
                            <span style="color: #94a3b8;">Unknown</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $status = $applicant['status'] ?? 'new';
                            $status_class = 'status-' . str_replace('_', '-', $status);
                            $status_labels = [
                                'new' => 'New',
                                'in_review' => 'In Review',
                                'shortlisted' => 'Shortlisted',
                                'interviewed' => 'Interviewed',
                                'offered' => 'Offered',
                                'hired' => 'Hired',
                                'rejected' => 'Rejected'
                            ];
                            $status_label = $status_labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                            ?>
                            <span class="category-badge <?php echo $status_class; ?>" style="font-weight: 600;">
                                <?php echo $status_label; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?page=applicant&subpage=applicant-profiles&id=<?php echo $applicant['id']; ?>" class="table-action" title="View Details">
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
    
    <!-- Applicants by Position -->
    <div class="activity-timeline">
        <div class="timeline-header">
            <h3><i class="fas fa-chart-pie" style="margin-right: 8px;"></i> Applicants by Position</h3>
        </div>
        
        <?php if (empty($positions) || ($applicant_stats['total'] ?? 0) == 0): ?>
        <div style="text-align: center; padding: 40px 20px; color: #95a5a6;">
            <i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
            <p>No position data available</p>
        </div>
        <?php else: ?>
            <?php foreach ($positions as $pos): 
                $position_title = $pos['position_title'] ?? 'Unspecified Position';
                $count = $pos['count'] ?? 0;
                $percentage = $applicant_stats['total'] > 0 ? ($count / $applicant_stats['total']) * 100 : 0;
            ?>
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 14px; font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($position_title); ?></span>
                    <span style="font-size: 14px; font-weight: 700; color: #0e4c92;"><?php echo $count; ?> applicant<?php echo $count != 1 ? 's' : ''; ?></span>
                </div>
                <div class="savings-bar">
                    <div class="savings-progress" style="width: <?php echo $percentage; ?>%"></div>
                </div>
                <div style="font-size: 11px; color: #64748b; margin-top: 5px; text-align: right;">
                    <?php echo number_format($percentage, 1); ?>% of total
                </div>
            </div>
            <?php endforeach; ?>
            
            <hr style="margin: 25px 0 20px; border: none; border-top: 1px solid #eef2f6;">
            
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 13px; color: #64748b;">Total Positions:</span>
                    <span style="font-weight: 700; color: #2c3e50; margin-left: 5px;"><?php echo count($positions); ?></span>
                </div>
                <div>
                    <span style="font-size: 13px; color: #64748b;">Total Applicants:</span>
                    <span style="font-weight: 700; color: #0e4c92; margin-left: 5px;"><?php echo number_format($applicant_stats['total'] ?? 0); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 25px;">
            <a href="?page=recruitment&subpage=job-posting" class="submit-btn" style="text-align: center; display: block; text-decoration: none; width: 100%;">
                <i class="fas fa-plus-circle"></i> Create New Job Posting
            </a>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <a href="?page=applicant&subpage=applicant-profiles&action=new" class="quick-action-btn primary">
        <i class="fas fa-user-plus"></i> Add New Applicant
    </a>
    <a href="?page=applicant&subpage=document-verification" class="quick-action-btn">
        <i class="fas fa-file-signature"></i> Verify Documents
    </a>
    <a href="?page=applicant&subpage=applicant-communication" class="quick-action-btn">
        <i class="fas fa-envelope"></i> Bulk Email
    </a>
    <a href="?page=applicant&subpage=application-status" class="quick-action-btn">
        <i class="fas fa-chart-pie"></i> Reports
    </a>
    <a href="?page=recruitment&subpage=interview-schedule" class="quick-action-btn">
        <i class="fas fa-calendar-alt"></i> Schedule Interview
    </a>
</div>