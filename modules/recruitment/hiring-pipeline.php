<?php
// Start output buffering at the VERY FIRST LINE - NO SPACES OR CHARACTERS BEFORE THIS
ob_start();

// modules/recruitment/hiring-pipeline.php
$page_title = "Hiring Pipeline";

// Include required files
require_once 'config/mail_config.php';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'pipeline'; // pipeline, kanban, or timeline

// Simple log function
function simpleLog($pdo, $user_id, $action, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Helper Functions
 */
function getApplicantPhoto($applicant) {
    if (!empty($applicant['photo_path']) && file_exists($applicant['photo_path'])) {
        return htmlspecialchars($applicant['photo_path']);
    }
    return null;
}

// Get all applicants with their current status and pipeline information
$query = "
    SELECT 
        ja.*,
        jp.id as job_posting_id,
        jp.title as job_title,
        jp.job_code,
        jp.department,
        se.screening_score,
        se.qualification_match,
        se.screening_result,
        (SELECT COUNT(*) FROM interviews WHERE applicant_id = ja.id) as interview_count,
        (SELECT COUNT(*) FROM final_interviews WHERE applicant_id = ja.id) as final_interview_count,
        (SELECT MAX(interview_date) FROM interviews WHERE applicant_id = ja.id) as last_interview_date,
        (SELECT MAX(interview_date) FROM final_interviews WHERE applicant_id = ja.id) as last_final_interview_date,
        (SELECT COUNT(*) FROM applicant_documents WHERE applicant_id = ja.id) as document_count,
        (SELECT COUNT(*) FROM applicant_documents WHERE applicant_id = ja.id AND verified = 1) as verified_docs,
        DATEDIFF(CURDATE(), DATE(ja.applied_at)) as days_since_applied,
        CASE 
            WHEN ja.status = 'new' THEN 1
            WHEN ja.status = 'in_review' THEN 2
            WHEN ja.status = 'shortlisted' THEN 3
            WHEN ja.status = 'interviewed' THEN 4
            WHEN ja.final_status = 'final_interview' THEN 5
            WHEN ja.status = 'offered' THEN 6
            WHEN ja.status = 'hired' THEN 7
            WHEN ja.status = 'rejected' THEN 8
            ELSE 9
        END as status_order,
        CASE
            WHEN ja.final_status = 'final_interview' THEN 'final_interview'
            ELSE ja.status
        END as pipeline_status
    FROM job_applications ja
    LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
    LEFT JOIN screening_evaluations se ON ja.id = se.applicant_id
    WHERE 1=1
";

$params = [];

// Status filter - handle pipeline status
if ($status_filter !== 'all') {
    if ($status_filter === 'final_interview') {
        $query .= " AND ja.final_status = 'final_interview'";
    } else {
        $query .= " AND ja.status = ?";
        $params[] = $status_filter;
    }
}

// Department filter
if (!empty($department_filter)) {
    $query .= " AND jp.department = ?";
    $params[] = $department_filter;
}

// Search filter
if (!empty($search_filter)) {
    $query .= " AND (ja.first_name LIKE ? OR ja.last_name LIKE ? OR ja.application_number LIKE ? OR ja.email LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Date filters
if (!empty($date_from)) {
    $query .= " AND DATE(ja.applied_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(ja.applied_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY status_order, ja.applied_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

// Get statistics for each pipeline stage
$stats = [
    'new' => 0,
    'in_review' => 0,
    'shortlisted' => 0,
    'interviewed' => 0,
    'final_interview' => 0,
    'offered' => 0,
    'hired' => 0,
    'rejected' => 0,
    'total' => count($applicants)
];

foreach ($applicants as $app) {
    if ($app['final_status'] == 'final_interview') {
        $stats['final_interview']++;
    } elseif (isset($stats[$app['status']])) {
        $stats[$app['status']]++;
    }
}

// Get conversion metrics
$conversion = [
    'application_to_review' => $stats['total'] > 0 ? round((($stats['in_review'] + $stats['shortlisted'] + $stats['interviewed'] + $stats['final_interview'] + $stats['offered'] + $stats['hired']) / $stats['total']) * 100) : 0,
    'review_to_shortlist' => ($stats['in_review'] + $stats['shortlisted'] + $stats['interviewed'] + $stats['final_interview'] + $stats['offered'] + $stats['hired']) > 0 ? 
        round((($stats['shortlisted'] + $stats['interviewed'] + $stats['final_interview'] + $stats['offered'] + $stats['hired']) / ($stats['in_review'] + $stats['shortlisted'] + $stats['interviewed'] + $stats['final_interview'] + $stats['offered'] + $stats['hired'])) * 100) : 0,
    'shortlist_to_interview' => ($stats['shortlisted'] + $stats['interviewed'] + $stats['final_interview'] + $stats['offered'] + $stats['hired']) > 0 ? 
        round((($stats['interviewed'] + $stats['final_interview'] + $stats['offered'] + $stats['hired']) / ($stats['shortlisted'] + $stats['interviewed'] + $stats['final_interview'] + $stats['offered'] + $stats['hired'])) * 100) : 0,
    'interview_to_final' => ($stats['interviewed'] + $stats['final_interview'] + $stats['offered'] + $stats['hired']) > 0 ? 
        round((($stats['final_interview'] + $stats['offered'] + $stats['hired']) / ($stats['interviewed'] + $stats['final_interview'] + $stats['offered'] + $stats['hired'])) * 100) : 0,
    'final_to_offer' => ($stats['final_interview'] + $stats['offered'] + $stats['hired']) > 0 ? 
        round((($stats['offered'] + $stats['hired']) / ($stats['final_interview'] + $stats['offered'] + $stats['hired'])) * 100) : 0,
    'offer_to_hire' => ($stats['offered'] + $stats['hired']) > 0 ? round(($stats['hired'] / ($stats['offered'] + $stats['hired'])) * 100) : 0,
    'overall_hire_rate' => $stats['total'] > 0 ? round(($stats['hired'] / $stats['total']) * 100) : 0
];

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department FROM job_postings WHERE department IS NOT NULL ORDER BY department");
$departments = $stmt->fetchAll();

// Pipeline stage configuration
$pipeline_config = [
    'new' => [
        'label' => 'New Applications',
        'icon' => 'fas fa-star',
        'color' => '#3498db',
        'bg' => '#3498db20',
        'text' => '#3498db',
        'progress' => 10,
        'description' => 'Fresh applications waiting for review'
    ],
    'in_review' => [
        'label' => 'Under Review',
        'icon' => 'fas fa-search',
        'color' => '#f39c12',
        'bg' => '#f39c1220',
        'text' => '#f39c12',
        'progress' => 25,
        'description' => 'Applications being screened'
    ],
    'shortlisted' => [
        'label' => 'Shortlisted',
        'icon' => 'fas fa-check-circle',
        'color' => '#27ae60',
        'bg' => '#27ae6020',
        'text' => '#27ae60',
        'progress' => 40,
        'description' => 'Candidates selected for interview'
    ],
    'interviewed' => [
        'label' => 'Interviewed',
        'icon' => 'fas fa-calendar-check',
        'color' => '#9b59b6',
        'bg' => '#9b59b620',
        'text' => '#9b59b6',
        'progress' => 55,
        'description' => 'Initial/Technical interviews completed'
    ],
    'final_interview' => [
        'label' => 'Final Interview',
        'icon' => 'fas fa-user-tie',
        'color' => '#e67e22',
        'bg' => '#e67e2220',
        'text' => '#e67e22',
        'progress' => 70,
        'description' => 'Candidates in final interview stage'
    ],
    'offered' => [
        'label' => 'Job Offered',
        'icon' => 'fas fa-file-signature',
        'color' => '#f1c40f',
        'bg' => '#f1c40f20',
        'text' => '#f1c40f',
        'progress' => 85,
        'description' => 'Job offers sent, awaiting response'
    ],
    'hired' => [
        'label' => 'Hired',
        'icon' => 'fas fa-user-check',
        'color' => '#2ecc71',
        'bg' => '#2ecc7120',
        'text' => '#2ecc71',
        'progress' => 100,
        'description' => 'Successfully hired'
    ],
    'rejected' => [
        'label' => 'Rejected',
        'icon' => 'fas fa-user-times',
        'color' => '#e74c3c',
        'bg' => '#e74c3c20',
        'text' => '#e74c3c',
        'progress' => 0,
        'description' => 'Applications not moving forward'
    ]
];
?>

<!-- ==================== STYLES ==================== -->
<style>
:root {
    --primary: #0e4c92;
    --primary-dark: #0a3a70;
    --primary-light: #4086e4;
    --primary-transparent: rgba(14, 76, 146, 0.1);
    --primary-transparent-2: rgba(14, 76, 146, 0.2);
    --success: #27ae60;
    --warning: #f39c12;
    --danger: #e74c3c;
    --info: #3498db;
    --purple: #9b59b6;
    --orange: #e67e22;
    --yellow: #f1c40f;
    --dark: #2c3e50;
    --gray: #64748b;
    --light-gray: #f8fafd;
    --border: #eef2f6;
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
    flex-wrap: wrap;
    gap: 15px;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title h1 {
    font-size: 24px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.page-title i {
    font-size: 28px;
    color: var(--primary);
    background: var(--primary-transparent);
    padding: 12px;
    border-radius: 15px;
}

/* View Toggle */
.view-toggle {
    display: flex;
    gap: 10px;
    background: var(--light-gray);
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
    color: var(--gray);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.view-option:hover {
    background: white;
    color: var(--primary);
    box-shadow: 0 5px 10px rgba(0,0,0,0.05);
}

.view-option.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 5px 10px rgba(0,0,0,0.05);
}

/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    box-shadow: 0 20px 40px var(--primary-transparent-2);
}

.stat-card-modern.primary { border-left-color: var(--info); }
.stat-card-modern.warning { border-left-color: var(--warning); }
.stat-card-modern.success { border-left-color: var(--success); }
.stat-card-modern.purple { border-left-color: var(--purple); }
.stat-card-modern.orange { border-left-color: var(--orange); }
.stat-card-modern.yellow { border-left-color: var(--yellow); }
.stat-card-modern.green { border-left-color: #2ecc71; }
.stat-card-modern.danger { border-left-color: var(--danger); }

.stat-icon-modern {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon-modern.primary { background: var(--info)20; color: var(--info); }
.stat-icon-modern.warning { background: var(--warning)20; color: var(--warning); }
.stat-icon-modern.success { background: var(--success)20; color: var(--success); }
.stat-icon-modern.purple { background: var(--purple)20; color: var(--purple); }
.stat-icon-modern.orange { background: var(--orange)20; color: var(--orange); }
.stat-icon-modern.yellow { background: var(--yellow)20; color: var(--yellow); }
.stat-icon-modern.green { background: #2ecc7120; color: #2ecc71; }
.stat-icon-modern.danger { background: var(--danger)20; color: var(--danger); }

.stat-content-modern {
    flex: 1;
}

.stat-label-modern {
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 5px;
    font-weight: 500;
}

.stat-value-modern {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
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
    color: var(--dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.funnel-title i {
    color: var(--primary);
}

.funnel-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 10px;
    align-items: end;
    margin-bottom: 25px;
}

.funnel-item {
    text-align: center;
}

.funnel-bar {
    height: 150px;
    background: linear-gradient(180deg, var(--light-gray) 0%, var(--border) 100%);
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
    background: linear-gradient(180deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 15px 15px 0 0;
    transition: height 0.5s ease;
}

.funnel-fill.new { background: linear-gradient(180deg, var(--info) 0%, #5faee3 100%); }
.funnel-fill.in_review { background: linear-gradient(180deg, var(--warning) 0%, #f5b041 100%); }
.funnel-fill.shortlisted { background: linear-gradient(180deg, var(--success) 0%, #52be80 100%); }
.funnel-fill.interviewed { background: linear-gradient(180deg, var(--purple) 0%, #af7ac5 100%); }
.funnel-fill.final_interview { background: linear-gradient(180deg, var(--orange) 0%, #eb984e 100%); }
.funnel-fill.offered { background: linear-gradient(180deg, var(--yellow) 0%, #f4d03f 100%); }
.funnel-fill.hired { background: linear-gradient(180deg, #2ecc71 0%, #58d68d 100%); }
.funnel-fill.rejected { background: linear-gradient(180deg, var(--danger) 0%, #ec7063 100%); }

.funnel-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
}

.funnel-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
}

.funnel-percent {
    font-size: 11px;
    color: var(--gray);
}

/* Conversion Metrics */
.conversion-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.conversion-card {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.conversion-icon {
    width: 45px;
    height: 45px;
    background: var(--primary-transparent);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 20px;
}

.conversion-info {
    flex: 1;
}

.conversion-label {
    font-size: 11px;
    color: var(--gray);
    margin-bottom: 3px;
}

.conversion-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
}

.conversion-trend {
    font-size: 10px;
    margin-top: 3px;
}

.trend-up { color: var(--success); }
.trend-down { color: var(--danger); }

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
    color: var(--dark);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-title i {
    color: var(--primary);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-item label {
    font-size: 11px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-item input,
.filter-item select {
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 13px;
    transition: all 0.3s;
    background: white;
}

.filter-item input:focus,
.filter-item select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

.filter-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Buttons */
.btn {
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px var(--primary-transparent-2);
}

.btn-secondary {
    background: var(--light-gray);
    color: var(--primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.btn-sm {
    padding: 5px 12px;
    font-size: 12px;
}

/* Pipeline View */
.pipeline-container {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    padding: 10px 0 20px;
    min-height: 600px;
}

.pipeline-column {
    min-width: 260px;
    max-width: 260px;
    background: var(--light-gray);
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

.pipeline-header.new { border-bottom-color: var(--info); }
.pipeline-header.in_review { border-bottom-color: var(--warning); }
.pipeline-header.shortlisted { border-bottom-color: var(--success); }
.pipeline-header.interviewed { border-bottom-color: var(--purple); }
.pipeline-header.final_interview { border-bottom-color: var(--orange); }
.pipeline-header.offered { border-bottom-color: var(--yellow); }
.pipeline-header.hired { border-bottom-color: #2ecc71; }
.pipeline-header.rejected { border-bottom-color: var(--danger); }

.pipeline-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 13px;
    color: var(--dark);
}

.pipeline-title i {
    width: 16px;
}

.pipeline-count {
    background: white;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    color: var(--gray);
}

.pipeline-cards {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 500px;
    overflow-y: auto;
    padding-right: 5px;
}

.pipeline-card {
    background: white;
    border-radius: 15px;
    padding: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    cursor: pointer;
    border: 1px solid var(--border);
}

.pipeline-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px var(--primary-transparent);
    border-color: var(--primary);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.card-avatar {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    object-fit: cover;
    border: 2px solid white;
    box-shadow: 0 2px 5px var(--primary-transparent);
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 12px;
}

.photo-fallback-card {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 12px;
}

.card-name {
    flex: 1;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.card-position {
    font-size: 11px;
    color: var(--gray);
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.card-meta {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: var(--gray);
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--border);
}

.card-score {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 6px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.score-high { background: var(--success)20; color: var(--success); }
.score-medium { background: var(--warning)20; color: var(--warning); }
.score-low { background: var(--danger)20; color: var(--danger); }

/* Kanban View */
.kanban-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.kanban-column {
    background: var(--light-gray);
    border-radius: 20px;
    padding: 20px;
}

.kanban-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.kanban-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 15px;
    color: var(--dark);
}

.kanban-cards {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 500px;
    overflow-y: auto;
    padding-right: 5px;
}

.kanban-card {
    background: white;
    border-radius: 15px;
    padding: 15px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    border: 1px solid var(--border);
}

.kanban-card:hover {
    transform: translateX(5px);
    box-shadow: 0 8px 20px var(--primary-transparent);
    border-color: var(--primary);
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
    padding-left: 40px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border);
}

.timeline-item {
    position: relative;
    padding-bottom: 25px;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -25px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--primary);
    border: 2px solid white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    z-index: 1;
}

.timeline-date {
    font-size: 12px;
    color: var(--gray);
    margin-bottom: 8px;
}

.timeline-content {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 15px;
}

.timeline-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.3s;
}

.timeline-row:last-child {
    border-bottom: none;
}

.timeline-row:hover {
    background: white;
    border-radius: 10px;
}

/* Status Badge */
.status-badge {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

/* Progress Bar */
.progress-container {
    background: var(--border);
    border-radius: 6px;
    height: 4px;
    overflow: hidden;
    margin: 8px 0;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 6px;
    transition: width 0.3s ease;
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
    backdrop-filter: blur(5px);
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 25px;
    max-width: 450px;
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
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border);
}

.modal-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close {
    font-size: 22px;
    cursor: pointer;
    color: var(--gray);
    transition: color 0.3s;
}

.modal-close:hover {
    color: var(--danger);
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 13px;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

/* Responsive */
@media (max-width: 1200px) {
    .funnel-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
    }
}

@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .funnel-grid {
        grid-template-columns: repeat(2, 1fr);
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
    
    .page-header-unique {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<!-- ==================== HTML CONTENT ==================== -->

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-title">
        <i class="fas fa-chart-line"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div class="view-toggle">
        <a href="?page=recruitment&subpage=hiring-pipeline&view=pipeline<?php 
            echo !empty($status_filter) ? '&status=' . $status_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($department_filter) ? '&department=' . $department_filter : ''; 
            echo !empty($date_from) ? '&date_from=' . $date_from : ''; 
            echo !empty($date_to) ? '&date_to=' . $date_to : ''; 
        ?>" class="view-option <?php echo $view_mode == 'pipeline' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> Pipeline
        </a>
        <a href="?page=recruitment&subpage=hiring-pipeline&view=kanban<?php 
            echo !empty($status_filter) ? '&status=' . $status_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($department_filter) ? '&department=' . $department_filter : ''; 
            echo !empty($date_from) ? '&date_from=' . $date_from : ''; 
            echo !empty($date_to) ? '&date_to=' . $date_to : ''; 
        ?>" class="view-option <?php echo $view_mode == 'kanban' ? 'active' : ''; ?>">
            <i class="fas fa-columns"></i> Kanban
        </a>
        <a href="?page=recruitment&subpage=hiring-pipeline&view=timeline<?php 
            echo !empty($status_filter) ? '&status=' . $status_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($department_filter) ? '&department=' . $department_filter : ''; 
            echo !empty($date_from) ? '&date_from=' . $date_from : ''; 
            echo !empty($date_to) ? '&date_to=' . $date_to : ''; 
        ?>" class="view-option <?php echo $view_mode == 'timeline' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Timeline
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-container">
    <div class="stat-card-modern primary">
        <div class="stat-icon-modern primary">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">New</span>
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
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Final Interview</span>
            <span class="stat-value-modern"><?php echo $stats['final_interview']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern yellow">
        <div class="stat-icon-modern yellow">
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
</div>

<!-- Recruitment Funnel -->
<div class="funnel-section">
    <div class="funnel-title">
        <i class="fas fa-filter"></i> Hiring Funnel
    </div>
    
    <div class="funnel-grid">
        <?php 
        $funnel_stages = ['new', 'in_review', 'shortlisted', 'interviewed', 'final_interview', 'offered', 'hired', 'rejected'];
        foreach ($funnel_stages as $stage): 
            $count = $stats[$stage] ?? 0;
            $percentage = $stats['total'] > 0 ? round(($count / $stats['total']) * 100) : 0;
        ?>
        <div class="funnel-item">
            <div class="funnel-bar">
                <div class="funnel-fill <?php echo $stage; ?>" style="height: <?php echo $percentage; ?>%"></div>
            </div>
            <div class="funnel-label"><?php echo $pipeline_config[$stage]['label']; ?></div>
            <div class="funnel-value"><?php echo $count; ?></div>
            <div class="funnel-percent"><?php echo $percentage; ?>%</div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Conversion Metrics -->
    <div class="conversion-grid">
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Review Rate</div>
                <div class="conversion-value"><?php echo $conversion['application_to_review']; ?>%</div>
                <div class="conversion-trend trend-up">
                    <i class="fas fa-arrow-up"></i> Apps reviewed
                </div>
            </div>
        </div>
        
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Shortlist Rate</div>
                <div class="conversion-value"><?php echo $conversion['review_to_shortlist']; ?>%</div>
                <div class="conversion-trend <?php echo $conversion['review_to_shortlist'] > 50 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas <?php echo $conversion['review_to_shortlist'] > 50 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i> 
                    Quality candidates
                </div>
            </div>
        </div>
        
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Interview Rate</div>
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
                <div class="conversion-label">Final Interview Rate</div>
                <div class="conversion-value"><?php echo $conversion['interview_to_final']; ?>%</div>
                <div class="conversion-trend <?php echo $conversion['interview_to_final'] > 50 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas <?php echo $conversion['interview_to_final'] > 50 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                    Moving to final
                </div>
            </div>
        </div>
        
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Offer Rate</div>
                <div class="conversion-value"><?php echo $conversion['final_to_offer']; ?>%</div>
                <div class="conversion-trend <?php echo $conversion['final_to_offer'] > 60 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas <?php echo $conversion['final_to_offer'] > 60 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                    Selection rate
                </div>
            </div>
        </div>
        
        <div class="conversion-card">
            <div class="conversion-icon">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="conversion-info">
                <div class="conversion-label">Acceptance Rate</div>
                <div class="conversion-value"><?php echo $conversion['offer_to_hire']; ?>%</div>
                <div class="conversion-trend <?php echo $conversion['offer_to_hire'] > 80 ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas <?php echo $conversion['offer_to_hire'] > 80 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                    Offer acceptance
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
        <i class="fas fa-filter"></i> Filter Pipeline
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="recruitment">
        <input type="hidden" name="subpage" value="hiring-pipeline">
        <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Stages</option>
                    <option value="new" <?php echo $status_filter == 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="in_review" <?php echo $status_filter == 'in_review' ? 'selected' : ''; ?>>In Review</option>
                    <option value="shortlisted" <?php echo $status_filter == 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                    <option value="interviewed" <?php echo $status_filter == 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                    <option value="final_interview" <?php echo $status_filter == 'final_interview' ? 'selected' : ''; ?>>Final Interview</option>
                    <option value="offered" <?php echo $status_filter == 'offered' ? 'selected' : ''; ?>>Offered</option>
                    <option value="hired" <?php echo $status_filter == 'hired' ? 'selected' : ''; ?>>Hired</option>
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
                <input type="text" name="search" placeholder="Name or Application #" value="<?php echo htmlspecialchars($search_filter); ?>">
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
            <a href="?page=recruitment&subpage=hiring-pipeline&view=<?php echo $view_mode; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Apply
            </button>
        </div>
    </form>
</div>

<!-- View Content -->
<?php if ($view_mode == 'pipeline'): ?>
    <!-- Pipeline View -->
    <div class="pipeline-container">
        <?php 
        $pipeline_stages = ['new', 'in_review', 'shortlisted', 'interviewed', 'final_interview', 'offered', 'hired', 'rejected'];
        foreach ($pipeline_stages as $stage_key): 
            $stage_config = $pipeline_config[$stage_key];
            $stage_applicants = array_filter($applicants, function($app) use ($stage_key) {
                if ($stage_key == 'final_interview') {
                    return $app['final_status'] == 'final_interview';
                }
                return $app['status'] == $stage_key;
            });
        ?>
        <div class="pipeline-column">
            <div class="pipeline-header <?php echo $stage_key; ?>">
                <div class="pipeline-title">
                    <i class="<?php echo $stage_config['icon']; ?>" style="color: <?php echo $stage_config['color']; ?>"></i>
                    <span><?php echo $stage_config['label']; ?></span>
                </div>
                <div class="pipeline-count"><?php echo count($stage_applicants); ?></div>
            </div>
            
            <div class="pipeline-cards">
                <?php foreach ($stage_applicants as $app): 
                    $photoPath = getApplicantPhoto($app);
                    $firstName = $app['first_name'] ?? '';
                    $lastName = $app['last_name'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
                    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                ?>
                <div class="pipeline-card" onclick="window.location.href='?page=recruitment&subpage=applicant-profiles&id=<?php echo $app['id']; ?>'">
                    <div class="card-header">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fullName); ?>"
                                 class="card-avatar"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                 loading="lazy">
                            <div class="photo-fallback-card" style="display: none;"><?php echo $initials; ?></div>
                        <?php else: ?>
                            <div class="photo-fallback-card">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        <div class="card-name"><?php echo htmlspecialchars($fullName); ?></div>
                    </div>
                    
                    <div class="card-position">
                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($app['job_title'] ?? $app['position_applied'] ?? 'General Application'); ?>
                    </div>
                    
                    <?php if ($app['screening_score']): ?>
                    <div style="margin: 5px 0;">
                        <span class="card-score <?php 
                            if ($app['screening_score'] >= 80) echo 'score-high';
                            elseif ($app['screening_score'] >= 60) echo 'score-medium';
                            else echo 'score-low';
                        ?>">
                            <i class="fas fa-star"></i> <?php echo $app['screening_score']; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card-meta">
                        <span><i class="fas fa-clock"></i> <?php echo $app['days_since_applied']; ?>d</span>
                        <?php if ($app['document_count'] > 0): ?>
                        <span><i class="fas fa-file"></i> <?php echo $app['verified_docs']; ?>/<?php echo $app['document_count']; ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-calendar"></i> <?php echo $app['interview_count'] + $app['final_interview_count']; ?> int</span>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $stage_config['progress']; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($stage_applicants)): ?>
                <div style="text-align: center; padding: 30px; color: var(--gray); background: white; border-radius: 12px;">
                    <i class="<?php echo $stage_config['icon']; ?>" style="font-size: 24px; opacity: 0.3;"></i>
                    <p style="margin-top: 8px; font-size: 12px;">No applicants</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($view_mode == 'kanban'): ?>
    <!-- Kanban View -->
    <div class="kanban-container">
        <?php 
        $kanban_stages = ['new', 'in_review', 'shortlisted', 'interviewed', 'final_interview', 'offered', 'hired'];
        foreach ($kanban_stages as $stage_key): 
            $stage_config = $pipeline_config[$stage_key];
            $stage_applicants = array_filter($applicants, function($app) use ($stage_key) {
                if ($stage_key == 'final_interview') {
                    return $app['final_status'] == 'final_interview';
                }
                return $app['status'] == $stage_key;
            });
            
            // Sort by score
            usort($stage_applicants, function($a, $b) {
                return ($b['screening_score'] ?? 0) <=> ($a['screening_score'] ?? 0);
            });
        ?>
        <div class="kanban-column">
            <div class="kanban-header">
                <div class="kanban-title">
                    <i class="<?php echo $stage_config['icon']; ?>" style="color: <?php echo $stage_config['color']; ?>"></i>
                    <span><?php echo $stage_config['label']; ?></span>
                </div>
                <span class="pipeline-count"><?php echo count($stage_applicants); ?></span>
            </div>
            
            <div class="kanban-cards">
                <?php foreach ($stage_applicants as $app): 
                    $photoPath = getApplicantPhoto($app);
                    $firstName = $app['first_name'] ?? '';
                    $lastName = $app['last_name'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
                    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                ?>
                <div class="kanban-card">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fullName); ?>"
                                 class="card-avatar"
                                 style="width: 35px; height: 35px;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                 loading="lazy">
                            <div class="photo-fallback-card" style="width: 35px; height: 35px; display: none;"><?php echo $initials; ?></div>
                        <?php else: ?>
                            <div class="photo-fallback-card" style="width: 35px; height: 35px;">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        <div style="flex: 1;">
                            <h4 style="margin: 0; font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($fullName); ?></h4>
                            <span class="status-badge" style="background: <?php echo $stage_config['bg']; ?>; color: <?php echo $stage_config['color']; ?>; margin-top: 3px; display: inline-block;">
                                <i class="<?php echo $stage_config['icon']; ?>"></i> <?php echo $stage_config['label']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <p style="margin: 5px 0; font-size: 12px; color: var(--gray);">
                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($app['job_title'] ?? $app['position_applied'] ?? 'General Application'); ?>
                    </p>
                    
                    <p style="margin: 5px 0; font-size: 11px; color: var(--gray);">
                        <i class="fas fa-hashtag"></i> #<?php echo $app['application_number']; ?> • 
                        <i class="fas fa-clock"></i> <?php echo $app['days_since_applied']; ?> days
                    </p>
                    
                    <?php if ($app['screening_score']): ?>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $app['screening_score']; ?>%"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--gray);">
                        <span>Screening</span>
                        <span><?php echo $app['screening_score']; ?>%</span>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 5px; margin-top: 12px;">
                        <a href="?page=recruitment&subpage=applicant-profiles&id=<?php echo $app['id']; ?>" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center; padding: 5px;">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($stage_applicants)): ?>
                <div style="text-align: center; padding: 40px; color: var(--gray); background: white; border-radius: 12px;">
                    <i class="<?php echo $stage_config['icon']; ?>" style="font-size: 32px; opacity: 0.3;"></i>
                    <p style="margin-top: 10px;">No applicants</p>
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
                    <span style="margin-left: 8px; color: var(--primary); font-size: 11px;"><?php echo count($day_apps); ?> applications</span>
                </div>
                <div class="timeline-content">
                    <?php foreach ($day_apps as $app): 
                        $photoPath = getApplicantPhoto($app);
                        $firstName = $app['first_name'] ?? '';
                        $lastName = $app['last_name'] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
                        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                        $stage_key = ($app['final_status'] == 'final_interview') ? 'final_interview' : $app['status'];
                        $stage_config = $pipeline_config[$stage_key] ?? $pipeline_config['new'];
                    ?>
                    <div class="timeline-row" onclick="window.location.href='?page=recruitment&subpage=applicant-profiles&id=<?php echo $app['id']; ?>'">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fullName); ?>"
                                 class="card-avatar"
                                 style="width: 35px; height: 35px;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                 loading="lazy">
                            <div class="photo-fallback-card" style="width: 35px; height: 35px; display: none;"><?php echo $initials; ?></div>
                        <?php else: ?>
                            <div class="photo-fallback-card" style="width: 35px; height: 35px;">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="font-size: 13px;"><?php echo htmlspecialchars($fullName); ?></strong>
                                    <span style="margin-left: 8px; font-size: 11px; color: var(--gray);">#<?php echo $app['application_number']; ?></span>
                                </div>
                                <span class="status-badge" style="background: <?php echo $stage_config['bg']; ?>; color: <?php echo $stage_config['color']; ?>;">
                                    <i class="<?php echo $stage_config['icon']; ?>"></i> 
                                    <?php echo $stage_config['label']; ?>
                                </span>
                            </div>
                            <div style="margin-top: 3px; font-size: 12px; color: var(--gray);">
                                <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($app['job_title'] ?? $app['position_applied'] ?? 'General Application'); ?>
                                <?php if ($app['screening_score']): ?>
                                • <span class="card-score <?php 
                                    if ($app['screening_score'] >= 80) echo 'score-high';
                                    elseif ($app['screening_score'] >= 60) echo 'score-medium';
                                    else echo 'score-low';
                                ?>" style="padding: 2px 6px;"><?php echo $app['screening_score']; ?>%</span>
                                <?php endif; ?>
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

<!-- JavaScript for image error handling -->
<script>
function handleImageError(img) {
    if (img.getAttribute('data-error-handled') === 'true') return;
    img.setAttribute('data-error-handled', 'true');
    
    const parent = img.parentNode;
    const fallback = document.createElement('div');
    fallback.className = 'photo-fallback-card';
    fallback.style.width = img.width + 'px';
    fallback.style.height = img.height + 'px';
    fallback.textContent = img.getAttribute('data-initials') || '?';
    
    parent.replaceChild(fallback, img);
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
</script>

<?php
// End output buffering and flush
ob_end_flush();
?>