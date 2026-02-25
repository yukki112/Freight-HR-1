<?php
// Start output buffering at the VERY FIRST LINE - NO SPACES OR CHARACTERS BEFORE THIS
ob_start();

// modules/recruitment/interview-feedback.php
$page_title = "Interview Feedback & Ranking";

// Include required files
require_once 'config/mail_config.php';

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$job_filter = isset($_GET['job_id']) ? $_GET['job_id'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

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

function getScoreBadgeClass($score) {
    if ($score >= 90) return 'score-excellent';
    if ($score >= 80) return 'score-good';
    if ($score >= 70) return 'score-average';
    if ($score >= 60) return 'score-fair';
    return 'score-poor';
}

function getScoreLabel($score) {
    if ($score >= 90) return 'Excellent';
    if ($score >= 80) return 'Good';
    if ($score >= 70) return 'Average';
    if ($score >= 60) return 'Fair';
    return 'Poor';
}

// Get all candidates with their scores and feedback
$query = "
    SELECT 
        ja.id,
        ja.application_number,
        ja.first_name,
        ja.last_name,
        ja.email,
        ja.phone,
        ja.photo_path,
        ja.status,
        ja.final_status,
        ja.final_interview_score,
        ja.applied_at,
        
        jp.id as job_posting_id,
        jp.title as position_title,
        jp.job_code,
        jp.department,
        
        se.screening_score,
        se.screening_notes as screening_feedback,
        se.evaluated_by as screening_evaluator,
        se.evaluation_date as screening_date,
        
        pe.id as panel_evaluation_id,
        pe.total_score as panel_total,
        pe.max_score as panel_max,
        pe.final_percentage as panel_score,
        pe.recommendation as panel_recommendation,
        pe.strengths as panel_strengths,
        pe.weaknesses as panel_weaknesses,
        pe.overall_comments as panel_comments,
        pe.submitted_at as panel_date,
        
        fi.id as final_interview_id,
        fi.final_score as final_score,
        fi.recommendation as final_recommendation,
        fi.strengths as final_strengths,
        fi.weaknesses as final_weaknesses,
        fi.overall_comments as final_comments,
        fi.status as final_status_detail,
        fi.submitted_at as final_date,
        
        u1.full_name as screening_evaluator_name,
        u2.full_name as panel_evaluator_name,
        u3.full_name as final_evaluator_name,
        
        -- Calculate weighted overall score (Screening 30%, Panel 40%, Final 30%)
        ROUND(
            COALESCE(se.screening_score, 0) * 0.3 + 
            COALESCE(pe.final_percentage, 0) * 0.4 + 
            COALESCE(fi.final_score, 0) * 0.3, 2
        ) as overall_score,
        
        -- Rank within same job posting
        ROW_NUMBER() OVER (
            PARTITION BY ja.job_posting_id 
            ORDER BY (
                COALESCE(se.screening_score, 0) * 0.3 + 
                COALESCE(pe.final_percentage, 0) * 0.4 + 
                COALESCE(fi.final_score, 0) * 0.3
            ) DESC
        ) as rank_position
        
    FROM job_applications ja
    LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
    LEFT JOIN screening_evaluations se ON ja.id = se.applicant_id
    LEFT JOIN panel_evaluations pe ON ja.id = pe.applicant_id AND pe.status = 'submitted'
    LEFT JOIN final_interviews fi ON ja.id = fi.applicant_id AND fi.status = 'completed'
    LEFT JOIN users u1 ON se.evaluated_by = u1.id
    LEFT JOIN users u2 ON pe.panel_id = u2.id
    LEFT JOIN users u3 ON fi.interviewer_id = u3.id
    WHERE 1=1
";

$params = [];

// Status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'screened') {
        $query .= " AND se.id IS NOT NULL";
    } elseif ($status_filter === 'panel_evaluated') {
        $query .= " AND pe.id IS NOT NULL";
    } elseif ($status_filter === 'final_evaluated') {
        $query .= " AND fi.id IS NOT NULL";
    } elseif ($status_filter === 'ranked') {
        $query .= " AND (se.id IS NOT NULL OR pe.id IS NOT NULL OR fi.id IS NOT NULL)";
    }
}

// Job filter
if (!empty($job_filter)) {
    $query .= " AND ja.job_posting_id = ?";
    $params[] = $job_filter;
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

$query .= " ORDER BY jp.title, rank_position";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// Get job postings for filter
$stmt = $pdo->query("SELECT id, job_code, title FROM job_postings WHERE status = 'published' ORDER BY title");
$job_postings = $stmt->fetchAll();

// Get statistics
$stats = [];

// Total candidates evaluated
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ja.id) 
    FROM job_applications ja
    LEFT JOIN screening_evaluations se ON ja.id = se.applicant_id
    LEFT JOIN panel_evaluations pe ON ja.id = pe.applicant_id
    LEFT JOIN final_interviews fi ON ja.id = fi.applicant_id
    WHERE se.id IS NOT NULL OR pe.id IS NOT NULL OR fi.id IS NOT NULL
");
$stmt->execute();
$stats['evaluated'] = $stmt->fetchColumn();

// Average scores
$stmt = $pdo->prepare("
    SELECT 
        AVG(se.screening_score) as avg_screening,
        AVG(pe.final_percentage) as avg_panel,
        AVG(fi.final_score) as avg_final
    FROM job_applications ja
    LEFT JOIN screening_evaluations se ON ja.id = se.applicant_id
    LEFT JOIN panel_evaluations pe ON ja.id = pe.applicant_id
    LEFT JOIN final_interviews fi ON ja.id = fi.applicant_id
");
$stmt->execute();
$avg_scores = $stmt->fetch();
$stats['avg_screening'] = round($avg_scores['avg_screening'] ?? 0);
$stats['avg_panel'] = round($avg_scores['avg_panel'] ?? 0);
$stats['avg_final'] = round($avg_scores['avg_final'] ?? 0);

// Top performers (score > 85)
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM (
        SELECT ja.id,
            (COALESCE(se.screening_score, 0) * 0.3 + 
             COALESCE(pe.final_percentage, 0) * 0.4 + 
             COALESCE(fi.final_score, 0) * 0.3) as overall
        FROM job_applications ja
        LEFT JOIN screening_evaluations se ON ja.id = se.applicant_id
        LEFT JOIN panel_evaluations pe ON ja.id = pe.applicant_id
        LEFT JOIN final_interviews fi ON ja.id = fi.applicant_id
        HAVING overall > 85
    ) as top
");
$stmt->execute();
$stats['top_performers'] = $stmt->fetchColumn();

// Total feedback entries
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM screening_evaluations) +
        (SELECT COUNT(*) FROM panel_evaluations WHERE status = 'submitted') +
        (SELECT COUNT(*) FROM final_interviews WHERE status = 'completed') as total
");
$stmt->execute();
$stats['total_feedback'] = $stmt->fetchColumn() ?: 0;
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
    --dark: #2c3e50;
    --gray: #64748b;
    --light-gray: #f8fafd;
    --border: #eef2f6;
    
    --excellent: #27ae60;
    --good: #2ecc71;
    --average: #f39c12;
    --fair: #e67e22;
    --poor: #e74c3c;
}

/* Page Header */
.page-header {
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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border: 1px solid var(--border);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-label {
    display: block;
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 5px;
    font-weight: 500;
}

.stat-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.stat-small {
    font-size: 12px;
    color: var(--gray);
    margin-top: 5px;
}

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
    padding: 10px 20px;
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

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px var(--primary-transparent-2);
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
    padding: 6px 12px;
    font-size: 12px;
}

/* Ranking Table */
.ranking-container {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.ranking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.ranking-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

.ranking-title i {
    color: var(--primary);
}

.job-selector {
    display: flex;
    gap: 10px;
    align-items: center;
}

.job-selector select {
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 13px;
    background: white;
}

.ranking-table {
    width: 100%;
    border-collapse: collapse;
}

.ranking-table th {
    background: var(--light-gray);
    padding: 15px 12px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.ranking-table td {
    padding: 15px 12px;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
    font-size: 13px;
    vertical-align: middle;
}

.ranking-table tr:hover td {
    background: var(--light-gray);
}

.rank-badge {
    display: inline-block;
    width: 30px;
    height: 30px;
    line-height: 30px;
    text-align: center;
    border-radius: 50%;
    font-weight: 700;
    font-size: 14px;
}

.rank-1 {
    background: gold;
    color: #000;
}

.rank-2 {
    background: silver;
    color: #000;
}

.rank-3 {
    background: #cd7f32;
    color: #fff;
}

.score-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 12px;
    text-align: center;
    min-width: 70px;
}

.score-excellent {
    background: var(--excellent)20;
    color: var(--excellent);
}

.score-good {
    background: var(--good)20;
    color: var(--good);
}

.score-average {
    background: var(--average)20;
    color: var(--average);
}

.score-fair {
    background: var(--fair)20;
    color: var(--fair);
}

.score-poor {
    background: var(--poor)20;
    color: var(--poor);
}

/* Feedback Cards */
.feedback-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.feedback-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.feedback-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.applicant-photo {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    object-fit: cover;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 18px;
}

.applicant-info {
    flex: 1;
}

.applicant-info h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 3px;
}

.applicant-info p {
    font-size: 12px;
    color: var(--gray);
    margin: 2px 0;
}

.applicant-info i {
    width: 14px;
    color: var(--primary);
}

.rank-position {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: var(--primary-transparent);
    color: var(--primary);
    font-weight: 700;
    font-size: 18px;
}

/* Score Summary */
.score-summary {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding: 10px;
    background: var(--light-gray);
    border-radius: 12px;
}

.score-item {
    text-align: center;
    flex: 1;
}

.score-item .label {
    font-size: 10px;
    color: var(--gray);
    margin-bottom: 3px;
}

.score-item .value {
    font-size: 16px;
    font-weight: 700;
}

/* Feedback Sections */
.feedback-section {
    margin: 15px 0;
    padding: 12px;
    background: var(--light-gray);
    border-radius: 12px;
}

.feedback-section h4 {
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.feedback-section h4 i {
    color: var(--primary);
}

.feedback-text {
    font-size: 12px;
    color: var(--gray);
    line-height: 1.5;
    margin: 0;
    white-space: pre-line;
}

.recommendation-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.recommendation-hire {
    background: var(--success)20;
    color: var(--success);
}

.recommendation-final {
    background: var(--info)20;
    color: var(--info);
}

.recommendation-hold {
    background: var(--warning)20;
    color: var(--warning);
}

.recommendation-reject {
    background: var(--danger)20;
    color: var(--danger);
}

/* Stage Tabs */
.stage-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.stage-tab {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    background: var(--light-gray);
    color: var(--gray);
    border: none;
}

.stage-tab:hover {
    background: var(--primary-transparent);
    color: var(--primary);
}

.stage-tab.active {
    background: var(--primary);
    color: white;
}

.stage-tab.screening.active { background: var(--info); }
.stage-tab.panel.active { background: var(--purple); }
.stage-tab.final.active { background: var(--orange); }

/* Evaluator Info */
.evaluator-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed var(--border);
    font-size: 11px;
    color: var(--gray);
}

.evaluator-info i {
    color: var(--primary);
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

/* Responsive */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .feedback-container {
        grid-template-columns: 1fr;
    }
    
    .ranking-table {
        font-size: 12px;
    }
    
    .ranking-table th,
    .ranking-table td {
        padding: 10px 8px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<!-- ==================== HTML CONTENT ==================== -->

<!-- Page Header -->
<div class="page-header">
    <div class="page-title">
        <i class="fas fa-comment-dots"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div>
        <span class="stat-small" style="background: var(--primary-transparent); padding: 8px 16px; border-radius: 30px;">
            <i class="fas fa-chart-line"></i> Total Feedback: <?php echo $stats['total_feedback']; ?>
        </span>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Evaluated Candidates</span>
            <span class="stat-value"><?php echo $stats['evaluated']; ?></span>
            <div class="stat-small">
                <i class="fas fa-check-circle" style="color: var(--success);"></i> With feedback
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Avg Screening</span>
            <span class="stat-value"><?php echo $stats['avg_screening']; ?>%</span>
            <div class="stat-small">
                <i class="fas fa-chart-line"></i> Initial evaluation
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-clipboard-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Avg Panel</span>
            <span class="stat-value"><?php echo $stats['avg_panel']; ?>%</span>
            <div class="stat-small">
                <i class="fas fa-users"></i> Panel evaluation
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Avg Final</span>
            <span class="stat-value"><?php echo $stats['avg_final']; ?>%</span>
            <div class="stat-small">
                <i class="fas fa-trophy"></i> Final interview
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-crown"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Top Performers</span>
            <span class="stat-value"><?php echo $stats['top_performers']; ?></span>
            <div class="stat-small">
                <i class="fas fa-arrow-up" style="color: var(--success);"></i> Score >85%
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Feedback & Rankings
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="recruitment">
        <input type="hidden" name="subpage" value="interview-feedback">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Candidates</option>
                    <option value="screened" <?php echo $status_filter == 'screened' ? 'selected' : ''; ?>>Screened</option>
                    <option value="panel_evaluated" <?php echo $status_filter == 'panel_evaluated' ? 'selected' : ''; ?>>Panel Evaluated</option>
                    <option value="final_evaluated" <?php echo $status_filter == 'final_evaluated' ? 'selected' : ''; ?>>Final Evaluated</option>
                    <option value="ranked" <?php echo $status_filter == 'ranked' ? 'selected' : ''; ?>>Ranked</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Position</label>
                <select name="job_id">
                    <option value="">All Positions</option>
                    <?php foreach ($job_postings as $job): ?>
                    <option value="<?php echo $job['id']; ?>" <?php echo $job_filter == $job['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($job['title'] . ' (' . $job['job_code'] . ')'); ?>
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
            <a href="?page=recruitment&subpage=interview-feedback" class="btn btn-secondary btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Apply
            </button>
        </div>
    </form>
</div>

<!-- Ranking Table by Position -->
<?php 
// Group candidates by job posting for ranking display
$grouped_by_job = [];
foreach ($candidates as $candidate) {
    $job_id = $candidate['job_posting_id'];
    if (!isset($grouped_by_job[$job_id])) {
        $grouped_by_job[$job_id] = [
            'title' => $candidate['position_title'],
            'code' => $candidate['job_code'],
            'candidates' => []
        ];
    }
    $grouped_by_job[$job_id]['candidates'][] = $candidate;
}
?>

<?php foreach ($grouped_by_job as $job_id => $job_data): ?>
<?php if (!empty($job_data['candidates'])): ?>
<div class="ranking-container">
    <div class="ranking-header">
        <div class="ranking-title">
            <i class="fas fa-trophy"></i>
            <?php echo htmlspecialchars($job_data['title']); ?> (<?php echo $job_data['code']; ?>)
        </div>
        <div class="job-selector">
            <span class="stat-small"><?php echo count($job_data['candidates']); ?> candidates</span>
        </div>
    </div>
    
    <table class="ranking-table">
        <thead>
            <tr>
                <th>Rank</th>
                <th>Candidate</th>
                <th>Application #</th>
                <th>Screening</th>
                <th>Panel</th>
                <th>Final</th>
                <th>Overall</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($job_data['candidates'] as $candidate): 
                $overall_class = getScoreBadgeClass($candidate['overall_score'] ?? 0);
                $overall_label = getScoreLabel($candidate['overall_score'] ?? 0);
            ?>
            <tr>
                <td>
                    <?php if ($candidate['rank_position'] <= 3): ?>
                    <span class="rank-badge rank-<?php echo $candidate['rank_position']; ?>">
                        <?php echo $candidate['rank_position']; ?>
                    </span>
                    <?php else: ?>
                    <span style="font-weight: 600;">#<?php echo $candidate['rank_position']; ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <?php 
                        $photoPath = getApplicantPhoto($candidate);
                        $fullName = $candidate['first_name'] . ' ' . $candidate['last_name'];
                        $initials = strtoupper(substr($candidate['first_name'] ?? '', 0, 1) . substr($candidate['last_name'] ?? '', 0, 1)) ?: '?';
                        ?>
                        
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($fullName); ?>" style="width: 35px; height: 35px; border-radius: 8px; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 35px; height: 35px; border-radius: 8px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <strong><?php echo htmlspecialchars($fullName); ?></strong>
                        </div>
                    </div>
                </td>
                <td>
                    <span style="font-size: 12px; color: var(--gray);"><?php echo $candidate['application_number']; ?></span>
                </td>
                <td>
                    <?php if ($candidate['screening_score']): ?>
                    <span class="score-badge <?php echo getScoreBadgeClass($candidate['screening_score']); ?>">
                        <?php echo $candidate['screening_score']; ?>%
                    </span>
                    <?php else: ?>
                    <span style="color: var(--gray);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($candidate['panel_score']): ?>
                    <span class="score-badge <?php echo getScoreBadgeClass($candidate['panel_score']); ?>">
                        <?php echo $candidate['panel_score']; ?>%
                    </span>
                    <?php else: ?>
                    <span style="color: var(--gray);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($candidate['final_score']): ?>
                    <span class="score-badge <?php echo getScoreBadgeClass($candidate['final_score']); ?>">
                        <?php echo $candidate['final_score']; ?>%
                    </span>
                    <?php else: ?>
                    <span style="color: var(--gray);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="score-badge <?php echo $overall_class; ?>">
                        <?php echo $candidate['overall_score'] ?? 0; ?>%
                    </span>
                    <div style="font-size: 10px; color: var(--gray); margin-top: 2px;"><?php echo $overall_label; ?></div>
                </td>
                <td>
                    <?php 
                    $status_text = 'New';
                    $status_color = 'var(--info)';
                    
                    if ($candidate['final_status_detail'] == 'completed') {
                        $status_text = 'Final Done';
                        $status_color = 'var(--orange)';
                    } elseif ($candidate['panel_evaluation_id']) {
                        $status_text = 'Panel Done';
                        $status_color = 'var(--purple)';
                    } elseif ($candidate['screening_score']) {
                        $status_text = 'Screened';
                        $status_color = 'var(--info)';
                    }
                    ?>
                    <span style="color: <?php echo $status_color; ?>; font-weight: 500; font-size: 11px;">
                        <?php echo $status_text; ?>
                    </span>
                </td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick="viewFeedback(<?php echo htmlspecialchars(json_encode($candidate)); ?>)">
                        <i class="fas fa-eye"></i> Feedback
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endforeach; ?>

<!-- Feedback Cards View -->
<div style="margin-top: 30px;">
    <div class="filter-title">
        <i class="fas fa-comments"></i> Detailed Feedback
    </div>
    
    <!-- Stage Tabs -->
    <div class="stage-tabs">
        <button class="stage-tab active" onclick="filterStage('all')">All Feedback</button>
        <button class="stage-tab screening" onclick="filterStage('screening')">Screening</button>
        <button class="stage-tab panel" onclick="filterStage('panel')">Panel Evaluation</button>
        <button class="stage-tab final" onclick="filterStage('final')">Final Interview</button>
    </div>
    
    <div class="feedback-container">
        <?php foreach ($candidates as $candidate): 
            $hasScreening = !empty($candidate['screening_feedback']);
            $hasPanel = !empty($candidate['panel_strengths']) || !empty($candidate['panel_weaknesses']) || !empty($candidate['panel_comments']);
            $hasFinal = !empty($candidate['final_strengths']) || !empty($candidate['final_weaknesses']) || !empty($candidate['final_comments']);
            
            if (!$hasScreening && !$hasPanel && !$hasFinal) continue;
            
            $photoPath = getApplicantPhoto($candidate);
            $fullName = $candidate['first_name'] . ' ' . $candidate['last_name'];
            $initials = strtoupper(substr($candidate['first_name'] ?? '', 0, 1) . substr($candidate['last_name'] ?? '', 0, 1)) ?: '?';
            $overall_class = getScoreBadgeClass($candidate['overall_score'] ?? 0);
        ?>
        <div class="feedback-card" data-stage="<?php 
            echo $hasFinal ? 'final' : ($hasPanel ? 'panel' : ($hasScreening ? 'screening' : 'all')); 
        ?>">
            <div class="card-header">
                <?php if ($photoPath): ?>
                    <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="applicant-photo">
                <?php else: ?>
                    <div class="applicant-photo">
                        <?php echo $initials; ?>
                    </div>
                <?php endif; ?>
                
                <div class="applicant-info">
                    <h3><?php echo htmlspecialchars($fullName); ?></h3>
                    <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($candidate['position_title'] ?? 'General Application'); ?></p>
                    <p><i class="fas fa-hashtag"></i> <?php echo $candidate['application_number']; ?></p>
                </div>
                
                <div class="rank-position">
                    #<?php echo $candidate['rank_position']; ?>
                </div>
            </div>
            
            <!-- Score Summary -->
            <div class="score-summary">
                <div class="score-item">
                    <div class="label">Screening</div>
                    <div class="value <?php echo getScoreBadgeClass($candidate['screening_score'] ?? 0); ?>">
                        <?php echo $candidate['screening_score'] ?? '—'; ?>
                    </div>
                </div>
                <div class="score-item">
                    <div class="label">Panel</div>
                    <div class="value <?php echo getScoreBadgeClass($candidate['panel_score'] ?? 0); ?>">
                        <?php echo $candidate['panel_score'] ?? '—'; ?>
                    </div>
                </div>
                <div class="score-item">
                    <div class="label">Final</div>
                    <div class="value <?php echo getScoreBadgeClass($candidate['final_score'] ?? 0); ?>">
                        <?php echo $candidate['final_score'] ?? '—'; ?>
                    </div>
                </div>
                <div class="score-item">
                    <div class="label">Overall</div>
                    <div class="value <?php echo $overall_class; ?>">
                        <?php echo $candidate['overall_score'] ?? 0; ?>
                    </div>
                </div>
            </div>
            
            <!-- Screening Feedback -->
            <?php if ($hasScreening): ?>
            <div class="feedback-section screening-stage">
                <h4><i class="fas fa-search" style="color: var(--info);"></i> Screening Feedback</h4>
                <?php if ($candidate['screening_feedback']): ?>
                <p class="feedback-text"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($candidate['screening_feedback'])); ?></p>
                <?php endif; ?>
                
                <?php if ($candidate['screening_evaluator_name']): ?>
                <div class="evaluator-info">
                    <i class="fas fa-user-check"></i> Evaluated by: <?php echo htmlspecialchars($candidate['screening_evaluator_name']); ?>
                    <?php if ($candidate['screening_date']): ?>
                    • <?php echo date('M d, Y', strtotime($candidate['screening_date'])); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Panel Evaluation Feedback -->
            <?php if ($hasPanel): ?>
            <div class="feedback-section panel-stage">
                <h4><i class="fas fa-clipboard-check" style="color: var(--purple);"></i> Panel Evaluation</h4>
                
                <?php if ($candidate['panel_recommendation']): ?>
                <div style="margin-bottom: 10px;">
                    <span class="recommendation-badge recommendation-<?php echo $candidate['panel_recommendation']; ?>">
                        <i class="fas fa-<?php 
                            echo $candidate['panel_recommendation'] == 'hire' ? 'check-circle' : 
                                ($candidate['panel_recommendation'] == 'final_interview' ? 'arrow-right' : 
                                ($candidate['panel_recommendation'] == 'hold' ? 'pause-circle' : 'times-circle')); 
                        ?>"></i>
                        <?php echo ucfirst(str_replace('_', ' ', $candidate['panel_recommendation'])); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($candidate['panel_strengths']): ?>
                <p class="feedback-text"><strong>✓ Strengths:</strong> <?php echo nl2br(htmlspecialchars($candidate['panel_strengths'])); ?></p>
                <?php endif; ?>
                
                <?php if ($candidate['panel_weaknesses']): ?>
                <p class="feedback-text"><strong>⚠ Areas for Improvement:</strong> <?php echo nl2br(htmlspecialchars($candidate['panel_weaknesses'])); ?></p>
                <?php endif; ?>
                
                <?php if ($candidate['panel_comments']): ?>
                <p class="feedback-text"><strong>📝 Comments:</strong> <?php echo nl2br(htmlspecialchars($candidate['panel_comments'])); ?></p>
                <?php endif; ?>
                
                <?php if ($candidate['panel_evaluator_name']): ?>
                <div class="evaluator-info">
                    <i class="fas fa-users"></i> Panel: <?php echo htmlspecialchars($candidate['panel_evaluator_name']); ?>
                    <?php if ($candidate['panel_date']): ?>
                    • <?php echo date('M d, Y', strtotime($candidate['panel_date'])); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Final Interview Feedback -->
            <?php if ($hasFinal): ?>
            <div class="feedback-section final-stage">
                <h4><i class="fas fa-user-tie" style="color: var(--orange);"></i> Final Interview</h4>
                
                <?php if ($candidate['final_recommendation']): ?>
                <div style="margin-bottom: 10px;">
                    <span class="recommendation-badge recommendation-<?php echo $candidate['final_recommendation']; ?>">
                        <i class="fas fa-<?php echo $candidate['final_recommendation'] == 'hire' ? 'check-circle' : 'times-circle'; ?>"></i>
                        <?php echo ucfirst($candidate['final_recommendation']); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($candidate['final_strengths']): ?>
                <p class="feedback-text"><strong>✓ Strengths:</strong> <?php echo nl2br(htmlspecialchars($candidate['final_strengths'])); ?></p>
                <?php endif; ?>
                
                <?php if ($candidate['final_weaknesses']): ?>
                <p class="feedback-text"><strong>⚠ Areas for Improvement:</strong> <?php echo nl2br(htmlspecialchars($candidate['final_weaknesses'])); ?></p>
                <?php endif; ?>
                
                <?php if ($candidate['final_comments']): ?>
                <p class="feedback-text"><strong>📝 Comments:</strong> <?php echo nl2br(htmlspecialchars($candidate['final_comments'])); ?></p>
                <?php endif; ?>
                
                <?php if ($candidate['final_evaluator_name']): ?>
                <div class="evaluator-info">
                    <i class="fas fa-user-tie"></i> Interviewer: <?php echo htmlspecialchars($candidate['final_evaluator_name']); ?>
                    <?php if ($candidate['final_date']): ?>
                    • <?php echo date('M d, Y', strtotime($candidate['final_date'])); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Score Details -->
            <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid var(--border); font-size: 11px; color: var(--gray); display: flex; justify-content: space-between;">
                <span><i class="fas fa-calendar"></i> Applied: <?php echo date('M d, Y', strtotime($candidate['applied_at'])); ?></span>
                <span><i class="fas fa-chart-line"></i> Overall: <?php echo $candidate['overall_score'] ?? 0; ?>%</span>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty(array_filter($candidates, function($c) { 
            return !empty($c['screening_feedback']) || !empty($c['panel_strengths']) || !empty($c['final_strengths']); 
        }))): ?>
        <div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: var(--gray);">
            <i class="fas fa-comment-slash" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
            <h3>No Feedback Found</h3>
            <p>No evaluation feedback has been submitted yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Feedback Detail Modal -->
<div id="feedbackModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-comment-dots" style="color: var(--primary);"></i> Complete Feedback</h3>
            <span class="modal-close" onclick="closeFeedbackModal()">&times;</span>
        </div>
        
        <div id="feedbackModalContent">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<script>
let currentStage = 'all';

function filterStage(stage) {
    currentStage = stage;
    
    // Update tab styles
    document.querySelectorAll('.stage-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Filter cards
    document.querySelectorAll('.feedback-card').forEach(card => {
        const cardStage = card.getAttribute('data-stage');
        if (stage === 'all' || cardStage === stage) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function viewFeedback(candidate) {
    const hasScreening = candidate.screening_feedback;
    const hasPanel = candidate.panel_strengths || candidate.panel_weaknesses || candidate.panel_comments;
    const hasFinal = candidate.final_strengths || candidate.final_weaknesses || candidate.final_comments;
    
    let html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <h2 style="font-size: 20px; margin-bottom: 5px;">${candidate.first_name} ${candidate.last_name}</h2>
            <p style="color: var(--gray);">${candidate.position_title || 'General Application'} • Rank #${candidate.rank_position}</p>
        </div>
    `;
    
    // Screening Feedback
    if (hasScreening) {
        html += `
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px; margin-bottom: 15px;">
                <h4 style="margin: 0 0 10px; color: var(--info);"><i class="fas fa-search"></i> Screening Feedback</h4>
                ${candidate.screening_feedback ? `<p><strong>Notes:</strong> ${candidate.screening_feedback.replace(/\n/g, '<br>')}</p>` : ''}
                ${candidate.screening_evaluator_name ? `
                <div style="margin-top: 10px; font-size: 12px; color: var(--gray);">
                    <i class="fas fa-user"></i> ${candidate.screening_evaluator_name} • ${new Date(candidate.screening_date).toLocaleDateString()}
                </div>
                ` : ''}
            </div>
        `;
    }
    
    // Panel Feedback
    if (hasPanel) {
        html += `
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px; margin-bottom: 15px;">
                <h4 style="margin: 0 0 10px; color: var(--purple);"><i class="fas fa-clipboard-check"></i> Panel Evaluation</h4>
                ${candidate.panel_recommendation ? `
                <div style="margin-bottom: 10px;">
                    <span class="recommendation-badge recommendation-${candidate.panel_recommendation}">
                        ${candidate.panel_recommendation.replace('_', ' ')}
                    </span>
                </div>
                ` : ''}
                ${candidate.panel_strengths ? `<p><strong>✓ Strengths:</strong> ${candidate.panel_strengths.replace(/\n/g, '<br>')}</p>` : ''}
                ${candidate.panel_weaknesses ? `<p><strong>⚠ Areas:</strong> ${candidate.panel_weaknesses.replace(/\n/g, '<br>')}</p>` : ''}
                ${candidate.panel_comments ? `<p><strong>📝 Comments:</strong> ${candidate.panel_comments.replace(/\n/g, '<br>')}</p>` : ''}
                ${candidate.panel_evaluator_name ? `
                <div style="margin-top: 10px; font-size: 12px; color: var(--gray);">
                    <i class="fas fa-users"></i> ${candidate.panel_evaluator_name} • ${new Date(candidate.panel_date).toLocaleDateString()}
                </div>
                ` : ''}
            </div>
        `;
    }
    
    // Final Feedback
    if (hasFinal) {
        html += `
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px; margin-bottom: 15px;">
                <h4 style="margin: 0 0 10px; color: var(--orange);"><i class="fas fa-user-tie"></i> Final Interview</h4>
                ${candidate.final_recommendation ? `
                <div style="margin-bottom: 10px;">
                    <span class="recommendation-badge recommendation-${candidate.final_recommendation}">
                        ${candidate.final_recommendation}
                    </span>
                </div>
                ` : ''}
                ${candidate.final_strengths ? `<p><strong>✓ Strengths:</strong> ${candidate.final_strengths.replace(/\n/g, '<br>')}</p>` : ''}
                ${candidate.final_weaknesses ? `<p><strong>⚠ Areas:</strong> ${candidate.final_weaknesses.replace(/\n/g, '<br>')}</p>` : ''}
                ${candidate.final_comments ? `<p><strong>📝 Comments:</strong> ${candidate.final_comments.replace(/\n/g, '<br>')}</p>` : ''}
                ${candidate.final_evaluator_name ? `
                <div style="margin-top: 10px; font-size: 12px; color: var(--gray);">
                    <i class="fas fa-user-tie"></i> ${candidate.final_evaluator_name} • ${new Date(candidate.final_date).toLocaleDateString()}
                </div>
                ` : ''}
            </div>
        `;
    }
    
    html += `
        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button class="btn btn-secondary" onclick="closeFeedbackModal()">Close</button>
        </div>
    `;
    
    document.getElementById('feedbackModalContent').innerHTML = html;
    document.getElementById('feedbackModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeFeedbackModal() {
    document.getElementById('feedbackModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFeedbackModal();
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('feedbackModal');
    if (event.target == modal) {
        closeFeedbackModal();
    }
}
</script>

<?php
// End output buffering and flush
ob_end_flush();
?>