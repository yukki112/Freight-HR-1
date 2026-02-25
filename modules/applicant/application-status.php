<?php
// modules/applicant/application-status.php
$page_title = "Application Status Tracking";

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search_filter = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$view_mode = $_GET['view'] ?? 'pipeline'; // pipeline, kanban, or timeline

// Get all applicants with their current status
$query = "
    SELECT 
        a.*,
        jp.title as job_title,
        jp.job_code,
        jp.department,
        se.screening_score,
        se.qualification_match,
        se.screening_result,
        (SELECT COUNT(*) FROM interviews WHERE applicant_id = a.id) as interview_count,
        (SELECT MAX(interview_date) FROM interviews WHERE applicant_id = a.id) as last_interview_date,
        (SELECT COUNT(*) FROM applicant_documents WHERE applicant_id = a.id) as document_count,
        (SELECT COUNT(*) FROM applicant_documents WHERE applicant_id = a.id AND verified = 1) as verified_docs,
        DATEDIFF(CURDATE(), DATE(a.applied_at)) as days_since_applied,
        CASE 
            WHEN a.status = 'new' THEN 1
            WHEN a.status = 'in_review' THEN 2
            WHEN a.status = 'shortlisted' THEN 3
            WHEN a.status = 'interviewed' THEN 4
            WHEN a.status = 'offered' THEN 5
            WHEN a.status = 'hired' THEN 6
            WHEN a.status = 'rejected' THEN 7
            ELSE 8
        END as status_order
    FROM job_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN screening_evaluations se ON a.id = se.applicant_id
    WHERE 1=1
";

$params = [];

// Status filter
if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
}

// Department filter
if (!empty($department_filter)) {
    $query .= " AND jp.department = ?";
    $params[] = $department_filter;
}

// Search filter
if (!empty($search_filter)) {
    $query .= " AND (a.first_name LIKE ? OR a.last_name LIKE ? OR a.application_number LIKE ? OR a.email LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Date filters
if (!empty($date_from)) {
    $query .= " AND DATE(a.applied_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(a.applied_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY status_order, a.applied_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

// Get statistics for each status
$stats = [
    'new' => 0,
    'in_review' => 0,
    'shortlisted' => 0,
    'interviewed' => 0,
    'offered' => 0,
    'hired' => 0,
    'rejected' => 0,
    'total' => count($applicants)
];

foreach ($applicants as $app) {
    if (isset($stats[$app['status']])) {
        $stats[$app['status']]++;
    }
}

// Get conversion metrics
$conversion = [
    'application_to_review' => $stats['total'] > 0 ? round(($stats['in_review'] / $stats['total']) * 100) : 0,
    'review_to_shortlist' => $stats['in_review'] > 0 ? round(($stats['shortlisted'] / $stats['in_review']) * 100) : 0,
    'shortlist_to_interview' => $stats['shortlisted'] > 0 ? round(($stats['interviewed'] / $stats['shortlisted']) * 100) : 0,
    'interview_to_offer' => $stats['interviewed'] > 0 ? round(($stats['offered'] / $stats['interviewed']) * 100) : 0,
    'offer_to_hire' => $stats['offered'] > 0 ? round(($stats['hired'] / $stats['offered']) * 100) : 0,
    'overall_hire_rate' => $stats['total'] > 0 ? round(($stats['hired'] / $stats['total']) * 100) : 0
];

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department FROM job_postings WHERE department IS NOT NULL ORDER BY department");
$departments = $stmt->fetchAll();

// Get recent activity
$stmt = $pdo->query("
    SELECT al.*, u.full_name as user_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.action LIKE '%applicant%' OR al.action LIKE '%status%'
    ORDER BY al.created_at DESC
    LIMIT 10
");
$activities = $stmt->fetchAll();

// Status configuration
$status_config = [
    'new' => [
        'label' => 'New Applications',
        'icon' => 'fas fa-star',
        'color' => '#3498db',
        'bg' => '#3498db20',
        'text' => '#3498db',
        'progress' => 10
    ],
    'in_review' => [
        'label' => 'Under Review',
        'icon' => 'fas fa-search',
        'color' => '#f39c12',
        'bg' => '#f39c1220',
        'text' => '#f39c12',
        'progress' => 30
    ],
    'shortlisted' => [
        'label' => 'Shortlisted',
        'icon' => 'fas fa-check-circle',
        'color' => '#27ae60',
        'bg' => '#27ae6020',
        'text' => '#27ae60',
        'progress' => 50
    ],
    'interviewed' => [
        'label' => 'Interviewed',
        'icon' => 'fas fa-calendar-check',
        'color' => '#9b59b6',
        'bg' => '#9b59b620',
        'text' => '#9b59b6',
        'progress' => 70
    ],
    'offered' => [
        'label' => 'Job Offered',
        'icon' => 'fas fa-file-signature',
        'color' => '#e67e22',
        'bg' => '#e67e2220',
        'text' => '#e67e22',
        'progress' => 85
    ],
    'hired' => [
        'label' => 'Hired',
        'icon' => 'fas fa-user-check',
        'color' => '#2ecc71',
        'bg' => '#2ecc7120',
        'text' => '#2ecc71',
        'progress' => 100
    ],
    'rejected' => [
        'label' => 'Rejected',
        'icon' => 'fas fa-user-times',
        'color' => '#e74c3c',
        'bg' => '#e74c3c20',
        'text' => '#e74c3c',
        'progress' => 0
    ]
];

// Helper function to get applicant photo or fallback to initials
function getApplicantPhoto($applicant) {
    if (!empty($applicant['photo_path']) && file_exists($applicant['photo_path'])) {
        return htmlspecialchars($applicant['photo_path']);
    }
    return null;
}
?>

<style>
:root {
    --primary-color: #0e4c92;
    --primary-light: #1e5ca8;
    --primary-dark: #0a3a70;
    --primary-transparent: rgba(14, 76, 146, 0.1);
    --primary-transparent-2: rgba(14, 76, 146, 0.2);
    --success-color: #27ae60;
    --warning-color: #f39c12;
    --danger-color: #e74c3c;
    --info-color: #3498db;
    --purple-color: #9b59b6;
    --orange-color: #e67e22;
}

/* Page Header */
.page-header-unique {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title h1 {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.page-title i {
    font-size: 28px;
    color: #0e4c92;
    background: rgba(14, 76, 146, 0.1);
    padding: 12px;
    border-radius: 15px;
}

/* View Toggle */
.view-toggle {
    display: flex;
    gap: 10px;
    background: #f8fafd;
    padding: 5px;
    border-radius: 15px;
}

.view-option {
    padding: 8px 16px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    color: #64748b;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.view-option:hover {
    background: white;
    color: #0e4c92;
    box-shadow: 0 5px 10px rgba(0,0,0,0.05);
}

.view-option.active {
    background: white;
    color: #0e4c92;
    box-shadow: 0 5px 10px rgba(0,0,0,0.05);
}

/* Stats Cards - New Layout */
.stats-container {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card-modern {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border-left: 5px solid;
}

.stat-card-modern:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(14, 76, 146, 0.15);
}

.stat-card-modern.primary { border-left-color: #3498db; }
.stat-card-modern.warning { border-left-color: #f39c12; }
.stat-card-modern.success { border-left-color: #27ae60; }
.stat-card-modern.purple { border-left-color: #9b59b6; }
.stat-card-modern.orange { border-left-color: #e67e22; }
.stat-card-modern.green { border-left-color: #2ecc71; }
.stat-card-modern.danger { border-left-color: #e74c3c; }

.stat-icon-modern {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon-modern.primary { background: #3498db20; color: #3498db; }
.stat-icon-modern.warning { background: #f39c1220; color: #f39c12; }
.stat-icon-modern.success { background: #27ae6020; color: #27ae60; }
.stat-icon-modern.purple { background: #9b59b620; color: #9b59b6; }
.stat-icon-modern.orange { background: #e67e2220; color: #e67e22; }
.stat-icon-modern.green { background: #2ecc7120; color: #2ecc71; }
.stat-icon-modern.danger { background: #e74c3c20; color: #e74c3c; }

.stat-content-modern {
    flex: 1;
}

.stat-label-modern {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 5px;
    font-weight: 500;
}

.stat-value-modern {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1.2;
}

/* Funnel Section */
.funnel-section {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.funnel-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.funnel-title i {
    color: #0e4c92;
}

.funnel-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 15px;
    align-items: end;
}

.funnel-item {
    text-align: center;
}

.funnel-bar {
    height: 150px;
    background: linear-gradient(180deg, #f8fafd 0%, #eef2f6 100%);
    border-radius: 15px 15px 0 0;
    position: relative;
    margin-bottom: 10px;
    overflow: hidden;
}

.funnel-fill {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    background: linear-gradient(180deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 15px 15px 0 0;
    transition: height 0.5s ease;
}

.funnel-label {
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
}

.funnel-value {
    font-size: 18px;
    font-weight: 700;
    color: #0e4c92;
}

.funnel-percent {
    font-size: 12px;
    color: #64748b;
}

/* Conversion Metrics */
.conversion-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.conversion-card {
    background: #f8fafd;
    border-radius: 15px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.conversion-icon {
    width: 45px;
    height: 45px;
    background: rgba(14, 76, 146, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0e4c92;
    font-size: 20px;
}

.conversion-info {
    flex: 1;
}

.conversion-label {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 5px;
}

.conversion-value {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
}

.conversion-trend {
    font-size: 11px;
    margin-top: 3px;
}

.trend-up { color: #27ae60; }
.trend-down { color: #e74c3c; }

/* Filter Section */
.filter-section {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.filter-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-title i {
    color: #0e4c92;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-item label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-item input,
.filter-item select {
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.filter-item input:focus,
.filter-item select:focus {
    outline: none;
    border-color: #0e4c92;
    box-shadow: 0 0 0 3px rgba(14, 76, 146, 0.1);
}

.filter-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Pipeline View */
.pipeline-container {
    display: flex;
    gap: 20px;
    overflow-x: auto;
    padding: 10px 0 20px;
    min-height: 600px;
}

.pipeline-column {
    min-width: 280px;
    background: #f8fafd;
    border-radius: 20px;
    padding: 15px;
}

.pipeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid;
}

.pipeline-header.new { border-bottom-color: #3498db; }
.pipeline-header.in_review { border-bottom-color: #f39c12; }
.pipeline-header.shortlisted { border-bottom-color: #27ae60; }
.pipeline-header.interviewed { border-bottom-color: #9b59b6; }
.pipeline-header.offered { border-bottom-color: #e67e22; }
.pipeline-header.hired { border-bottom-color: #2ecc71; }
.pipeline-header.rejected { border-bottom-color: #e74c3c; }

.pipeline-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 14px;
    color: #2c3e50;
}

.pipeline-title i {
    width: 20px;
}

.pipeline-count {
    background: white;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}

.pipeline-cards {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.pipeline-card {
    background: white;
    border-radius: 15px;
    padding: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid #eef2f6;
}

.pipeline-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(14, 76, 146, 0.15);
    border-color: #0e4c92;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

/* Avatar/Photo Styles */
.card-avatar {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 5px rgba(14, 76, 146, 0.2);
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.card-avatar[src=""], 
.card-avatar:not([src]) {
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-fallback-card {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.card-name {
    flex: 1;
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.card-position {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 10px;
}

.card-meta {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #94a3b8;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #eef2f6;
}

.card-score {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.score-high { background: #27ae6020; color: #27ae60; }
.score-medium { background: #f39c1220; color: #f39c12; }
.score-low { background: #e74c3c20; color: #e74c3c; }

/* Kanban View */
.kanban-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.kanban-column {
    background: #f8fafd;
    border-radius: 20px;
    padding: 20px;
}

.kanban-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.kanban-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 16px;
}

.kanban-cards {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.kanban-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.kanban-card:hover {
    transform: translateX(5px);
    box-shadow: 0 10px 25px rgba(14, 76, 146, 0.15);
}

/* Timeline View */
.timeline-container {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.timeline {
    position: relative;
    padding-left: 50px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #eef2f6;
}

.timeline-item {
    position: relative;
    padding-bottom: 30px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -30px;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #0e4c92;
    border: 3px solid white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    z-index: 1;
}

.timeline-date {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 5px;
}

.timeline-content {
    background: #f8fafd;
    border-radius: 15px;
    padding: 20px;
}

.timeline-content h4 {
    margin: 0 0 10px 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.timeline-content p {
    margin: 5px 0;
    font-size: 13px;
    color: #64748b;
}

/* Status Badge */
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* Progress Bar */
.progress-container {
    background: #eef2f6;
    border-radius: 10px;
    height: 6px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #0e4c92, #4086e4);
    border-radius: 10px;
    transition: width 0.3s ease;
}

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    padding: 10px 20px;
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

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.3);
}

.btn-secondary {
    background: #f8fafd;
    color: #0e4c92;
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-secondary:hover {
    background: #0e4c92;
    color: white;
    border-color: #0e4c92;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 30px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #eef2f6;
}

.modal-header h3 {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close {
    font-size: 24px;
    cursor: pointer;
    color: #64748b;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #e74c3c;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #0e4c92;
    box-shadow: 0 0 0 3px rgba(14, 76, 146, 0.1);
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

/* Image error handling */
.img-error-fallback-card {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    border-radius: 10px;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    font-weight: 600;
    font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .funnel-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .conversion-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .kanban-container {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- JavaScript for handling image errors -->
<script>
function handleImageError(img) {
    // Don't process if already handled
    if (img.getAttribute('data-error-handled') === 'true') return;
    
    // Mark as handled
    img.setAttribute('data-error-handled', 'true');
    
    // Get the initials from data attribute
    const initials = img.getAttribute('data-initials') || '?';
    
    // Create fallback element
    const parent = img.parentNode;
    const fallback = document.createElement('div');
    fallback.className = 'img-error-fallback-card';
    fallback.textContent = initials;
    
    // Replace image with fallback
    parent.replaceChild(fallback, img);
}
</script>

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-title">
        <i class="fas fa-chart-line"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div class="view-toggle">
        <a href="?page=applicant&subpage=application-status&view=pipeline<?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>" class="view-option <?php echo $view_mode == 'pipeline' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> Pipeline
        </a>
        <a href="?page=applicant&subpage=application-status&view=kanban<?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>" class="view-option <?php echo $view_mode == 'kanban' ? 'active' : ''; ?>">
            <i class="fas fa-columns"></i> Kanban
        </a>
        <a href="?page=applicant&subpage=application-status&view=timeline<?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?>" class="view-option <?php echo $view_mode == 'timeline' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Timeline
        </a>
    </div>
</div>

<!-- Statistics Cards - New Layout -->
<div class="stats-container">
    <div class="stat-card-modern primary">
        <div class="stat-icon-modern primary">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">New Applications</span>
            <span class="stat-value-modern"><?php echo $stats['new']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern warning">
        <div class="stat-icon-modern warning">
            <i class="fas fa-search"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">In Review</span>
            <span class="stat-value-modern"><?php echo $stats['in_review']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern success">
        <div class="stat-icon-modern success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Shortlisted</span>
            <span class="stat-value-modern"><?php echo $stats['shortlisted']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern purple">
        <div class="stat-icon-modern purple">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Interviewed</span>
            <span class="stat-value-modern"><?php echo $stats['interviewed']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern orange">
        <div class="stat-icon-modern orange">
            <i class="fas fa-file-signature"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Offered</span>
            <span class="stat-value-modern"><?php echo $stats['offered']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern green">
        <div class="stat-icon-modern green">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Hired</span>
            <span class="stat-value-modern"><?php echo $stats['hired']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern danger">
        <div class="stat-icon-modern danger">
            <i class="fas fa-user-times"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Rejected</span>
            <span class="stat-value-modern"><?php echo $stats['rejected']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern" style="border-left-color: #0e4c92;">
        <div class="stat-icon-modern" style="background: rgba(14, 76, 146, 0.1); color: #0e4c92;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Total Applications</span>
            <span class="stat-value-modern"><?php echo $stats['total']; ?></span>
        </div>
    </div>
</div>

<!-- Recruitment Funnel -->
<div class="funnel-section">
    <div class="funnel-title">
        <i class="fas fa-filter"></i> Recruitment Funnel
    </div>
    
    <div class="funnel-grid">
        <div class="funnel-item">
            <div class="funnel-bar">
                <div class="funnel-fill" style="height: <?php echo $stats['total'] > 0 ? ($stats['new'] / $stats['total']) * 100 : 0; ?>%"></div>
            </div>
            <div class="funnel-label">New</div>
            <div class="funnel-value"><?php echo $stats['new']; ?></div>
            <div class="funnel-percent"><?php echo $stats['total'] > 0 ? round(($stats['new'] / $stats['total']) * 100) : 0; ?>%</div>
        </div>
        
        <div class="funnel-item">
            <div class="funnel-bar">
                <div class="funnel-fill" style="height: <?php echo $stats['total'] > 0 ? ($stats['in_review'] / $stats['total']) * 100 : 0; ?>%"></div>
            </div>
            <div class="funnel-label">Review</div>
            <div class="funnel-value"><?php echo $stats['in_review']; ?></div>
            <div class="funnel-percent"><?php echo $stats['total'] > 0 ? round(($stats['in_review'] / $stats['total']) * 100) : 0; ?>%</div>
        </div>
        
        <div class="funnel-item">
            <div class="funnel-bar">
                <div class="funnel-fill" style="height: <?php echo $stats['total'] > 0 ? ($stats['shortlisted'] / $stats['total']) * 100 : 0; ?>%"></div>
            </div>
            <div class="funnel-label">Shortlist</div>
            <div class="funnel-value"><?php echo $stats['shortlisted']; ?></div>
            <div class="funnel-percent"><?php echo $stats['total'] > 0 ? round(($stats['shortlisted'] / $stats['total']) * 100) : 0; ?>%</div>
        </div>
        
        <div class="funnel-item">
            <div class="funnel-bar">
                <div class="funnel-fill" style="height: <?php echo $stats['total'] > 0 ? ($stats['interviewed'] / $stats['total']) * 100 : 0; ?>%"></div>
            </div>
            <div class="funnel-label">Interview</div>
            <div class="funnel-value"><?php echo $stats['interviewed']; ?></div>
            <div class="funnel-percent"><?php echo $stats['total'] > 0 ? round(($stats['interviewed'] / $stats['total']) * 100) : 0; ?>%</div>
        </div>
        
        <div class="funnel-item">
            <div class="funnel-bar">
                <div class="funnel-fill" style="height: <?php echo $stats['total'] > 0 ? ($stats['offered'] / $stats['total']) * 100 : 0; ?>%"></div>
            </div>
            <div class="funnel-label">Offer</div>
            <div class="funnel-value"><?php echo $stats['offered']; ?></div>
            <div class="funnel-percent"><?php echo $stats['total'] > 0 ? round(($stats['offered'] / $stats['total']) * 100) : 0; ?>%</div>
        </div>
        
        <div class="funnel-item">
            <div class="funnel-bar">
                <div class="funnel-fill" style="height: <?php echo $stats['total'] > 0 ? ($stats['hired'] / $stats['total']) * 100 : 0; ?>%"></div>
            </div>
            <div class="funnel-label">Hired</div>
            <div class="funnel-value"><?php echo $stats['hired']; ?></div>
            <div class="funnel-percent"><?php echo $stats['total'] > 0 ? round(($stats['hired'] / $stats['total']) * 100) : 0; ?>%</div>
        </div>
    </div>
    
    <!-- Conversion Metrics -->
    <div class="conversion-grid">
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Application → Review</div>
                <div class="conversion-value"><?php echo $conversion['application_to_review']; ?>%</div>
                <div class="conversion-trend trend-up">
                    <i class="fas fa-arrow-up"></i> Moving forward
                </div>
            </div>
        </div>
        
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Review → Shortlist</div>
                <div class="conversion-value"><?php echo $conversion['review_to_shortlist']; ?>%</div>
                <div class="conversion-trend <?php echo $conversion['review_to_shortlist'] > 50 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas <?php echo $conversion['review_to_shortlist'] > 50 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i> 
                    <?php echo $conversion['review_to_shortlist'] > 50 ? 'Good selection' : 'Needs review'; ?>
                </div>
            </div>
        </div>
        
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Shortlist → Interview</div>
                <div class="conversion-value"><?php echo $conversion['shortlist_to_interview']; ?>%</div>
                <div class="conversion-trend <?php echo $conversion['shortlist_to_interview'] > 70 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas <?php echo $conversion['shortlist_to_interview'] > 70 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                    Interview scheduling
                </div>
            </div>
        </div>
        
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Interview → Offer</div>
                <div class="conversion-value"><?php echo $conversion['interview_to_offer']; ?>%</div>
                <div class="conversion-trend <?php echo $conversion['interview_to_offer'] > 50 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas <?php echo $conversion['interview_to_offer'] > 50 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                    Offer rate
                </div>
            </div>
        </div>
        
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Offer → Hire</div>
                <div class="conversion-value"><?php echo $conversion['offer_to_hire']; ?>%</div>
                <div class="conversion-trend <?php echo $conversion['offer_to_hire'] > 80 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas <?php echo $conversion['offer_to_hire'] > 80 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                    Acceptance rate
                </div>
            </div>
        </div>
        
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Overall Hire Rate</div>
                <div class="conversion-value"><?php echo $conversion['overall_hire_rate']; ?>%</div>
                <div class="conversion-trend trend-up">
                    <i class="fas fa-check"></i> Final conversion
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Applications
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="applicant">
        <input type="hidden" name="subpage" value="application-status">
        <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="new" <?php echo $status_filter == 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="in_review" <?php echo $status_filter == 'in_review' ? 'selected' : ''; ?>>In Review</option>
                    <option value="shortlisted" <?php echo $status_filter == 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                    <option value="interviewed" <?php echo $status_filter == 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                    <option value="offered" <?php echo $status_filter == 'offered' ? 'selected' : ''; ?>>Offered</option>
                    <option value="hired" <?php echo $status_filter == 'hired' ? 'selected' : ''; ?>>Hired</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Department</label>
                <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['department']; ?>" <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                        <?php echo ucfirst($dept['department']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, Email, or Application #" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
            
            <div class="filter-item">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="filter-item">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=applicant&subpage=application-status&view=<?php echo $view_mode; ?>" class="btn-secondary">
                <i class="fas fa-times"></i> Clear Filters
            </a>
            <button type="submit" class="btn-primary">
                <i class="fas fa-search"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- View Content -->
<?php if ($view_mode == 'pipeline'): ?>
    <!-- Pipeline View -->
    <div class="pipeline-container">
        <?php foreach ($status_config as $status_key => $config): 
            $status_applicants = array_filter($applicants, function($app) use ($status_key) {
                return $app['status'] == $status_key;
            });
        ?>
        <div class="pipeline-column">
            <div class="pipeline-header <?php echo $status_key; ?>">
                <div class="pipeline-title">
                    <i class="<?php echo $config['icon']; ?>" style="color: <?php echo $config['color']; ?>"></i>
                    <span><?php echo $config['label']; ?></span>
                </div>
                <div class="pipeline-count"><?php echo count($status_applicants); ?></div>
            </div>
            
            <div class="pipeline-cards">
                <?php foreach ($status_applicants as $app): 
                    $photoPath = getApplicantPhoto($app);
                    $firstName = $app['first_name'] ?? '';
                    $lastName = $app['last_name'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
                    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                ?>
                <div class="pipeline-card" onclick="window.location.href='?page=applicant&subpage=applicant-profiles&id=<?php echo $app['id']; ?>'">
                    <div class="card-header">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fullName); ?>"
                                 class="card-avatar"
                                 onerror="handleImageError(this)"
                                 data-initials="<?php echo $initials; ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="photo-fallback-card">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        <div class="card-name"><?php echo htmlspecialchars($fullName); ?></div>
                    </div>
                    
                    <div class="card-position">
                        <?php echo htmlspecialchars($app['job_title'] ?? $app['position_applied'] ?? 'General Application'); ?>
                    </div>
                    
                    <?php if ($app['screening_score']): ?>
                    <div style="margin: 8px 0;">
                        <span class="card-score <?php 
                            if ($app['screening_score'] >= 70) echo 'score-high';
                            elseif ($app['screening_score'] >= 40) echo 'score-medium';
                            else echo 'score-low';
                        ?>">
                            <i class="fas fa-star"></i> Score: <?php echo $app['screening_score']; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card-meta">
                        <span><i class="fas fa-clock"></i> <?php echo $app['days_since_applied']; ?>d</span>
                        <?php if ($app['document_count'] > 0): ?>
                        <span><i class="fas fa-file"></i> <?php echo $app['verified_docs']; ?>/<?php echo $app['document_count']; ?></span>
                        <?php endif; ?>
                        <?php if ($app['interview_count'] > 0): ?>
                        <span><i class="fas fa-calendar"></i> <?php echo $app['interview_count']; ?> int</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($status_key == 'in_review' && !$app['screening_score']): ?>
                    <div style="margin-top: 10px;">
                        <a href="?page=applicant&subpage=screening-evaluation&evaluate=<?php echo $app['id']; ?>" class="btn-primary btn-sm" style="width: 100%; text-align: center;">
                            <i class="fas fa-clipboard-check"></i> Evaluate
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($status_key == 'shortlisted' && $app['interview_count'] == 0): ?>
                    <div style="margin-top: 10px;">
                        <button class="btn-primary btn-sm" style="width: 100%;" onclick="event.stopPropagation(); openScheduleModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($fullName); ?>')">
                            <i class="fas fa-calendar-plus"></i> Schedule
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($status_applicants)): ?>
                <div style="text-align: center; padding: 30px; color: #95a5a6; background: white; border-radius: 15px;">
                    <i class="<?php echo $config['icon']; ?>" style="font-size: 24px; opacity: 0.3;"></i>
                    <p style="margin-top: 10px; font-size: 13px;">No applicants</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($view_mode == 'kanban'): ?>
    <!-- Kanban View -->
    <div class="kanban-container">
        <?php foreach ($status_config as $status_key => $config): 
            $status_applicants = array_filter($applicants, function($app) use ($status_key) {
                return $app['status'] == $status_key;
            });
            
            // Sort by score if available
            usort($status_applicants, function($a, $b) {
                return ($b['screening_score'] ?? 0) <=> ($a['screening_score'] ?? 0);
            });
        ?>
        <div class="kanban-column">
            <div class="kanban-header">
                <div class="kanban-title">
                    <i class="<?php echo $config['icon']; ?>" style="color: <?php echo $config['color']; ?>"></i>
                    <span><?php echo $config['label']; ?></span>
                </div>
                <span class="pipeline-count"><?php echo count($status_applicants); ?></span>
            </div>
            
            <div class="kanban-cards">
                <?php foreach ($status_applicants as $app): 
                    $photoPath = getApplicantPhoto($app);
                    $firstName = $app['first_name'] ?? '';
                    $lastName = $app['last_name'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
                    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                ?>
                <div class="kanban-card">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fullName); ?>"
                                 class="card-avatar"
                                 style="width: 40px; height: 40px;"
                                 onerror="handleImageError(this)"
                                 data-initials="<?php echo $initials; ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="photo-fallback-card" style="width: 40px; height: 40px; font-size: 16px;">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        <div style="flex: 1;">
                            <h4 style="margin: 0; font-size: 15px; font-weight: 600;"><?php echo htmlspecialchars($fullName); ?></h4>
                            <span class="status-badge" style="background: <?php echo $config['bg']; ?>; color: <?php echo $config['color']; ?>; margin-top: 3px; display: inline-block;">
                                <i class="<?php echo $config['icon']; ?>"></i> <?php echo $config['label']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <p style="margin: 5px 0; font-size: 13px; color: #64748b;">
                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($app['job_title'] ?? $app['position_applied'] ?? 'General Application'); ?>
                    </p>
                    
                    <p style="margin: 5px 0; font-size: 12px; color: #94a3b8;">
                        <i class="fas fa-hashtag"></i> #<?php echo $app['application_number']; ?> • 
                        <i class="fas fa-clock"></i> <?php echo $app['days_since_applied']; ?> days ago
                    </p>
                    
                    <?php if ($app['screening_score']): ?>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $app['screening_score']; ?>%"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 11px; color: #64748b;">
                        <span>Screening Score</span>
                        <span><?php echo $app['screening_score']; ?>%</span>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 8px; margin-top: 15px;">
                        <a href="?page=applicant&subpage=applicant-profiles&id=<?php echo $app['id']; ?>" class="btn-secondary btn-sm" style="flex: 1; text-align: center;">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <?php if ($status_key == 'shortlisted' && $app['interview_count'] == 0): ?>
                        <button class="btn-primary btn-sm" style="flex: 1;" onclick="openScheduleModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($fullName); ?>')">
                            <i class="fas fa-calendar-plus"></i> Schedule
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($status_applicants)): ?>
                <div style="text-align: center; padding: 40px; color: #95a5a6; background: white; border-radius: 15px;">
                    <i class="<?php echo $config['icon']; ?>" style="font-size: 32px; opacity: 0.3;"></i>
                    <p style="margin-top: 10px;">No applicants in this stage</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <!-- Timeline View -->
    <div class="timeline-container">
        <div class="timeline">
            <?php 
            $grouped_by_date = [];
            foreach ($applicants as $app) {
                $date = date('Y-m-d', strtotime($app['applied_at']));
                if (!isset($grouped_by_date[$date])) {
                    $grouped_by_date[$date] = [];
                }
                $grouped_by_date[$date][] = $app;
            }
            krsort($grouped_by_date);
            
            foreach ($grouped_by_date as $date => $day_apps): 
            ?>
            <div class="timeline-item">
                <div class="timeline-date">
                    <strong><?php echo date('F d, Y', strtotime($date)); ?></strong>
                    <span style="margin-left: 10px; color: #0e4c92;"><?php echo count($day_apps); ?> applications</span>
                </div>
                <div class="timeline-content">
                    <?php foreach ($day_apps as $app): 
                        $photoPath = getApplicantPhoto($app);
                        $firstName = $app['first_name'] ?? '';
                        $lastName = $app['last_name'] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
                        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                    ?>
                    <div style="display: flex; align-items: center; gap: 15px; padding: 10px; border-bottom: 1px solid #eef2f6; cursor: pointer;" onclick="window.location.href='?page=applicant&subpage=applicant-profiles&id=<?php echo $app['id']; ?>'">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fullName); ?>"
                                 class="card-avatar"
                                 style="width: 40px; height: 40px;"
                                 onerror="handleImageError(this)"
                                 data-initials="<?php echo $initials; ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="photo-fallback-card" style="width: 40px; height: 40px; font-size: 16px;">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong><?php echo htmlspecialchars($fullName); ?></strong>
                                    <span style="margin-left: 10px; font-size: 12px; color: #64748b;">#<?php echo $app['application_number']; ?></span>
                                </div>
                                <span class="status-badge" style="background: <?php echo $status_config[$app['status']]['bg']; ?>; color: <?php echo $status_config[$app['status']]['color']; ?>;">
                                    <i class="<?php echo $status_config[$app['status']]['icon']; ?>"></i> 
                                    <?php echo $status_config[$app['status']]['label']; ?>
                                </span>
                            </div>
                            <div style="margin-top: 5px; font-size: 13px; color: #64748b;">
                                <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($app['job_title'] ?? $app['position_applied'] ?? 'General Application'); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Schedule Modal -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus" style="color: #0e4c92;"></i> Schedule Interview</h3>
            <span class="modal-close" onclick="closeScheduleModal()">&times;</span>
        </div>
        <form method="POST" action="?page=applicant&subpage=shortlisted-candidates" id="scheduleForm">
            <input type="hidden" name="applicant_id" id="schedule_applicant_id">
            
            <div class="form-group">
                <label>Candidate</label>
                <input type="text" id="schedule_candidate_name" readonly disabled style="background: #f8fafd;">
            </div>
            
            <div class="form-group">
                <label>Interview Type</label>
                <select name="interview_type" required>
                    <option value="initial">Initial Interview</option>
                    <option value="technical">Technical Interview</option>
                    <option value="hr">HR Interview</option>
                    <option value="final">Final Interview</option>
                </select>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="interview_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Time</label>
                    <input type="time" name="interview_time" required>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeScheduleModal()">Cancel</button>
                <button type="submit" name="schedule_selected" class="btn-primary">Schedule Interview</button>
            </div>
        </form>
    </div>
</div>

<script>
function openScheduleModal(id, name) {
    document.getElementById('schedule_applicant_id').value = id;
    document.getElementById('schedule_candidate_name').value = name;
    document.getElementById('scheduleModal').style.display = 'flex';
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('scheduleModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>