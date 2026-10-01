<?php
// modules/applicant/shortlisted-candidates.php
$page_title = "Shortlisted Candidates";

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['schedule_selected'])) {
        $selected_candidates = $_POST['selected_candidates'] ?? [];
        $interview_date = $_POST['interview_date'] ?? '';
        $interview_time = $_POST['interview_time'] ?? '';
        $interview_type = $_POST['interview_type'] ?? 'initial';
        
        if (!empty($selected_candidates) && !empty($interview_date)) {
            $success_count = 0;
            foreach ($selected_candidates as $applicant_id) {
                // Create interview schedule
                $stmt = $pdo->prepare("
                    INSERT INTO interviews 
                    (applicant_id, interview_date, interview_time, interview_type, status, created_by, created_at) 
                    VALUES (?, ?, ?, ?, 'scheduled', ?, NOW())
                ");
                if ($stmt->execute([$applicant_id, $interview_date, $interview_time, $interview_type, $_SESSION['user_id']])) {
                    $success_count++;
                    
                    // Update applicant status to interviewed
                    $stmt2 = $pdo->prepare("UPDATE job_applications SET status = 'interviewed' WHERE id = ?");
                    $stmt2->execute([$applicant_id]);
                    
                    // Log activity
                    logActivity($pdo, $_SESSION['user_id'], 'schedule_interview', "Scheduled interview for applicant #$applicant_id");
                }
            }
            
            if ($success_count > 0) {
                $success_message = "Successfully scheduled interviews for $success_count candidate(s)!";
            }
        }
    } elseif (isset($_POST['move_to_interview'])) {
        $applicant_id = $_POST['applicant_id'];
        
        // Update applicant status to interviewed
        $stmt = $pdo->prepare("UPDATE job_applications SET status = 'interviewed' WHERE id = ?");
        if ($stmt->execute([$applicant_id])) {
            logActivity($pdo, $_SESSION['user_id'], 'move_to_interview', "Moved applicant #$applicant_id to interview stage");
            $success_message = "Candidate moved to interview stage successfully!";
        }
    } elseif (isset($_POST['reject_candidate'])) {
        $applicant_id = $_POST['applicant_id'];
        $rejection_reason = $_POST['rejection_reason'] ?? '';
        
        // Update applicant status to rejected
        $stmt = $pdo->prepare("UPDATE job_applications SET status = 'rejected', notes = CONCAT(IFNULL(notes, ''), '\n', ?) WHERE id = ?");
        $notes = "[" . date('Y-m-d H:i') . "] Rejected from shortlist: " . $rejection_reason;
        if ($stmt->execute([$notes, $applicant_id])) {
            logActivity($pdo, $_SESSION['user_id'], 'reject_candidate', "Rejected applicant #$applicant_id from shortlist");
            $success_message = "Candidate rejected successfully!";
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search_filter = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'shortlisted_date';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Get shortlisted candidates
$query = "
    SELECT 
        a.*,
        jp.title as job_title,
        jp.job_code,
        jp.department,
        jp.salary_min,
        jp.salary_max,
        se.screening_score,
        se.qualification_match,
        se.screening_notes,
        se.evaluation_date,
        se.screening_result,
        u.full_name as evaluator_name,
        CASE 
            WHEN a.status = 'shortlisted' THEN 'For Interview'
            WHEN a.status = 'interviewed' THEN 'Interview Scheduled'
            WHEN a.status = 'offered' THEN 'Job Offered'
            WHEN a.status = 'hired' THEN 'Hired'
            ELSE 'Pending'
        END as shortlist_status,
        DATEDIFF(CURDATE(), DATE(a.updated_at)) as days_in_shortlist
    FROM job_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN screening_evaluations se ON a.id = se.applicant_id
    LEFT JOIN users u ON se.evaluated_by = u.id
    WHERE a.status IN ('shortlisted', 'interviewed', 'offered')
";

$params = [];

// Status filter
if ($status_filter === 'for_interview') {
    $query .= " AND a.status = 'shortlisted'";
} elseif ($status_filter === 'interview_scheduled') {
    $query .= " AND a.status = 'interviewed'";
} elseif ($status_filter === 'offered') {
    $query .= " AND a.status = 'offered'";
} elseif ($status_filter === 'hired') {
    $query .= " AND a.status = 'hired'";
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

// Date filters (based on when they were shortlisted - using updated_at)
if (!empty($date_from)) {
    $query .= " AND DATE(a.updated_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(a.updated_at) <= ?";
    $params[] = $date_to;
}

// Sorting
$query .= " ORDER BY ";
switch ($sort_by) {
    case 'name':
        $query .= "a.first_name $sort_order, a.last_name $sort_order";
        break;
    case 'score':
        $query .= "se.screening_score $sort_order";
        break;
    case 'position':
        $query .= "jp.title $sort_order";
        break;
    case 'shortlisted_date':
    default:
        $query .= "a.updated_at $sort_order";
        break;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// Get interview counts for each candidate
foreach ($candidates as &$candidate) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM interviews WHERE applicant_id = ? AND status = 'scheduled'");
    $stmt->execute([$candidate['id']]);
    $candidate['interview_count'] = $stmt->fetchColumn();
}

// Get statistics
$stats = [];

// Total shortlisted
$stmt = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'shortlisted'");
$stats['shortlisted'] = $stmt->fetchColumn();

// Total interviewed
$stmt = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'interviewed'");
$stats['interviewed'] = $stmt->fetchColumn();

// Total offered
$stmt = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'offered'");
$stats['offered'] = $stmt->fetchColumn();

// Total hired
$stmt = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'hired'");
$stats['hired'] = $stmt->fetchColumn();

// Average screening score of shortlisted
$stmt = $pdo->query("
    SELECT AVG(se.screening_score) 
    FROM screening_evaluations se
    JOIN job_applications a ON se.applicant_id = a.id
    WHERE a.status IN ('shortlisted', 'interviewed', 'offered', 'hired')
");
$stats['avg_score'] = round($stmt->fetchColumn() ?: 0);

// By department
$stmt = $pdo->query("
    SELECT jp.department, COUNT(*) as total
    FROM job_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    WHERE a.status IN ('shortlisted', 'interviewed', 'offered', 'hired')
    GROUP BY jp.department
    ORDER BY total DESC
");
$dept_stats = $stmt->fetchAll();

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department FROM job_postings WHERE department IS NOT NULL ORDER BY department");
$departments = $stmt->fetchAll();

// Get upcoming interviews for quick view
$stmt = $pdo->query("
    SELECT i.*, a.first_name, a.last_name, a.application_number, a.photo_path, jp.title as job_title
    FROM interviews i
    JOIN job_applications a ON i.applicant_id = a.id
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    WHERE i.status = 'scheduled' AND i.interview_date >= CURDATE()
    ORDER BY i.interview_date ASC, i.interview_time ASC
    LIMIT 5
");
$upcoming_interviews = $stmt->fetchAll();

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

/* Stats Cards */
.stats-grid-unique {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card-unique {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.03);
}

.stat-card-unique:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(14, 76, 146, 0.15);
}

.stat-icon-3d {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.2);
}

.stat-content {
    flex: 1;
}

.stat-label {
    display: block;
    font-size: 13px;
    color: #64748b;
    margin-bottom: 5px;
    font-weight: 500;
}

.stat-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1.2;
}

.stat-small {
    font-size: 13px;
    color: #64748b;
    margin-top: 5px;
}

/* Upcoming Interviews */
.upcoming-section {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.upcoming-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.upcoming-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.upcoming-header h3 i {
    color: #0e4c92;
}

.interview-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
}

.interview-card {
    background: #f8fafd;
    border-radius: 15px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
    border: 1px solid #eef2f6;
}

.interview-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.1);
}

.interview-date {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    border-radius: 12px;
    padding: 10px;
    min-width: 60px;
    text-align: center;
}

.interview-date .day {
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
}

.interview-date .month {
    font-size: 11px;
    opacity: 0.9;
}

.interview-info {
    flex: 1;
}

.interview-info h4 {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 3px 0;
}

.interview-info p {
    font-size: 12px;
    color: #64748b;
    margin: 2px 0;
}

.interview-info i {
    color: #0e4c92;
    width: 14px;
}

/* Avatar/Photo Styles for Interview Cards */
.interview-photo-small {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 8px rgba(14, 76, 146, 0.2);
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}

.photo-fallback-small {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}

/* Avatar/Photo Styles for Table */
.applicant-photo-medium {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 8px rgba(14, 76, 146, 0.2);
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

.applicant-photo-medium[src=""], 
.applicant-photo-medium:not([src]) {
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-fallback-medium {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
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
    margin-top: 15px;
}

/* Bulk Actions */
.bulk-actions {
    background: white;
    border-radius: 20px;
    padding: 15px 20px;
    margin-bottom: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.bulk-actions select {
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    min-width: 150px;
}

.bulk-actions input[type="date"],
.bulk-actions input[type="time"] {
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
}

.bulk-actions .btn-primary {
    padding: 10px 20px;
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow-x: auto;
}

.unique-table {
    width: 100%;
    border-collapse: collapse;
}

.unique-table th {
    text-align: left;
    padding: 15px;
    background: #f8fafd;
    color: #64748b;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: background 0.3s;
}

.unique-table th:hover {
    background: #eef2f6;
}

.unique-table th i {
    margin-left: 5px;
    font-size: 12px;
    color: #0e4c92;
}

.unique-table td {
    padding: 15px;
    border-bottom: 1px solid #eef2f6;
    color: #2c3e50;
    font-size: 14px;
    vertical-align: middle;
}

.unique-table tr:hover td {
    background: #f8fafd;
}

/* Status Badges */
.category-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.badge-success {
    background: #27ae6020;
    color: #27ae60;
}

.badge-warning {
    background: #f39c1220;
    color: #f39c12;
}

.badge-danger {
    background: #e74c3c20;
    color: #e74c3c;
}

.badge-info {
    background: #3498db20;
    color: #3498db;
}

.badge-purple {
    background: #9b59b620;
    color: #9b59b6;
}

.badge-secondary {
    background: #95a5a620;
    color: #7f8c8d;
}

/* Score Badge */
.score-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
}

.score-high {
    background: #27ae6020;
    color: #27ae60;
}

.score-medium {
    background: #f39c1220;
    color: #f39c12;
}

.score-low {
    background: #e74c3c20;
    color: #e74c3c;
}

/* Checkbox */
.checkbox-column {
    width: 40px;
    text-align: center;
}

.checkbox-column input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #0e4c92;
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
    padding: 8px 16px;
    font-size: 13px;
}

.btn-success {
    background: #27ae60;
    color: white;
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
}

.btn-success:hover {
    background: #219a52;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
}

.btn-danger {
    background: #e74c3c;
    color: white;
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
}

.btn-danger:hover {
    background: #c0392b;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
}

.btn-info {
    background: #3498db;
    color: white;
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
}

.btn-info:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
}

.btn-group {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

/* Alert Messages */
.alert-success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid #c3e6cb;
}

/* Days indicator */
.days-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    background: #f8fafd;
    color: #64748b;
}

.days-badge i {
    color: #f39c12;
}

/* Action Menu */
.action-menu {
    position: relative;
    display: inline-block;
}

.action-menu-content {
    display: none;
    position: absolute;
    right: 0;
    background: white;
    min-width: 200px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border-radius: 12px;
    padding: 8px 0;
    z-index: 100;
}

.action-menu:hover .action-menu-content {
    display: block;
}

.action-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    color: #2c3e50;
    text-decoration: none;
    font-size: 13px;
    transition: all 0.3s;
}

.action-menu-item:hover {
    background: #f8fafd;
    color: #0e4c92;
}

.action-menu-item i {
    width: 18px;
    color: #0e4c92;
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

/* Image error handling */
.img-error-fallback-medium,
.interview-img-error-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    font-weight: 600;
}

.img-error-fallback-medium {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    font-size: 16px;
}

.interview-img-error-fallback {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    font-size: 16px;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .interview-cards {
        grid-template-columns: 1fr;
    }
    
    .form-row {
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
    const isInterview = img.classList.contains('interview-photo-small');
    
    // Create fallback element
    const parent = img.parentNode;
    const fallback = document.createElement('div');
    fallback.className = isInterview ? 'interview-img-error-fallback' : 'img-error-fallback-medium';
    fallback.textContent = initials;
    
    // Replace image with fallback
    parent.replaceChild(fallback, img);
}

function handleInterviewImageError(img) {
    handleImageError(img);
}
</script>

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-title">
        <i class="fas fa-check-circle"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div>
        <a href="?page=applicant&subpage=shortlisted-candidates&status=for_interview" class="btn-secondary btn-sm <?php echo $status_filter == 'for_interview' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> For Interview (<?php echo $stats['shortlisted']; ?>)
        </a>
        <a href="?page=applicant&subpage=shortlisted-candidates&status=interview_scheduled" class="btn-secondary btn-sm <?php echo $status_filter == 'interview_scheduled' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i> Interview Scheduled (<?php echo $stats['interviewed']; ?>)
        </a>
        <a href="?page=applicant&subpage=shortlisted-candidates&status=offered" class="btn-secondary btn-sm <?php echo $status_filter == 'offered' ? 'active' : ''; ?>">
            <i class="fas fa-file-signature"></i> Offered (<?php echo $stats['offered']; ?>)
        </a>
        <a href="?page=applicant&subpage=shortlisted-candidates&status=hired" class="btn-secondary btn-sm <?php echo $status_filter == 'hired' ? 'active' : ''; ?>">
            <i class="fas fa-user-check"></i> Hired (<?php echo $stats['hired']; ?>)
        </a>
    </div>
</div>

<?php if (isset($success_message)): ?>
<div class="alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid-unique">
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Total Shortlisted</span>
            <span class="stat-value"><?php echo array_sum([$stats['shortlisted'], $stats['interviewed'], $stats['offered'], $stats['hired']]); ?></span>
            <div class="stat-small">
                <span style="color: #f39c12;"><i class="fas fa-clock"></i> <?php echo $stats['shortlisted']; ?> pending</span>
            </div>
        </div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Avg. Screening Score</span>
            <span class="stat-value"><?php echo $stats['avg_score']; ?>%</span>
            <div class="stat-small">
                <span style="color: #27ae60;"><i class="fas fa-arrow-up"></i> Top performers</span>
            </div>
        </div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Interview Rate</span>
            <span class="stat-value"><?php 
                $total = array_sum([$stats['shortlisted'], $stats['interviewed'], $stats['offered'], $stats['hired']]);
                $rate = $total > 0 ? round(($stats['interviewed'] / $total) * 100) : 0;
                echo $rate; ?>%
            </span>
            <div class="stat-small">
                <span><i class="fas fa-user-check"></i> <?php echo $stats['interviewed']; ?> scheduled</span>
            </div>
        </div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-trophy"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Conversion Rate</span>
            <span class="stat-value"><?php 
                $conversion = $total > 0 ? round(($stats['hired'] / $total) * 100) : 0;
                echo $conversion; ?>%
            </span>
            <div class="stat-small">
                <span style="color: #27ae60;"><i class="fas fa-check"></i> <?php echo $stats['hired']; ?> hired</span>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Interviews Section -->
<?php if (!empty($upcoming_interviews)): ?>
<div class="upcoming-section">
    <div class="upcoming-header">
        <h3><i class="fas fa-calendar-alt"></i> Upcoming Interviews</h3>
        <a href="?page=recruitment&subpage=interview-scheduling" class="btn-secondary btn-sm">
            <i class="fas fa-plus"></i> Schedule New
        </a>
    </div>
    <div class="interview-cards">
        <?php foreach ($upcoming_interviews as $interview): 
            $photoPath = getApplicantPhoto($interview);
            $firstName = $interview['first_name'] ?? '';
            $lastName = $interview['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
        ?>
        <div class="interview-card">
            <?php if ($photoPath): ?>
                <img src="<?php echo $photoPath; ?>" 
                     alt="<?php echo htmlspecialchars($fullName); ?>"
                     class="interview-photo-small"
                     onerror="handleInterviewImageError(this)"
                     data-initials="<?php echo $initials; ?>"
                     loading="lazy">
            <?php else: ?>
                <div class="photo-fallback-small">
                    <?php echo $initials; ?>
                </div>
            <?php endif; ?>
            
            <div class="interview-info">
                <h4><?php echo htmlspecialchars($fullName); ?></h4>
                <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($interview['job_title'] ?? 'General Application'); ?></p>
                <p><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($interview['interview_time'])); ?> on <?php echo date('M d, Y', strtotime($interview['interview_date'])); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Shortlisted Candidates
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="applicant">
        <input type="hidden" name="subpage" value="shortlisted-candidates">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Shortlisted</option>
                    <option value="for_interview" <?php echo $status_filter == 'for_interview' ? 'selected' : ''; ?>>For Interview</option>
                    <option value="interview_scheduled" <?php echo $status_filter == 'interview_scheduled' ? 'selected' : ''; ?>>Interview Scheduled</option>
                    <option value="offered" <?php echo $status_filter == 'offered' ? 'selected' : ''; ?>>Job Offered</option>
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
            
            <div class="filter-item">
                <label>Sort By</label>
                <select name="sort_by">
                    <option value="shortlisted_date" <?php echo $sort_by == 'shortlisted_date' ? 'selected' : ''; ?>>Shortlisted Date</option>
                    <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name</option>
                    <option value="score" <?php echo $sort_by == 'score' ? 'selected' : ''; ?>>Screening Score</option>
                    <option value="position" <?php echo $sort_by == 'position' ? 'selected' : ''; ?>>Position</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Order</label>
                <select name="sort_order">
                    <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                </select>
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=applicant&subpage=shortlisted-candidates" class="btn-secondary">
                <i class="fas fa-times"></i> Clear Filters
            </a>
            <button type="submit" class="btn-primary">
                <i class="fas fa-search"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Bulk Actions Form -->
<form method="POST" id="bulkForm">
    <div class="bulk-actions">
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
            <label for="selectAll">Select All</label>
        </div>
        
        <select name="interview_type">
            <option value="initial">Initial Interview</option>
            <option value="technical">Technical Interview</option>
            <option value="hr">HR Interview</option>
            <option value="final">Final Interview</option>
        </select>
        
        <input type="date" name="interview_date" required placeholder="Interview Date">
        <input type="time" name="interview_time" placeholder="Interview Time">
        
        <button type="submit" name="schedule_selected" class="btn-primary" onclick="return confirmBulkSchedule()">
            <i class="fas fa-calendar-plus"></i> Schedule Selected
        </button>
        
        <span style="margin-left: auto; color: #64748b; font-size: 13px;">
            Total: <strong><?php echo count($candidates); ?></strong> candidates
        </span>
    </div>

    <!-- Candidates Table -->
    <div class="table-container">
        <table class="unique-table">
            <thead>
                <tr>
                    <th class="checkbox-column">
                        <input type="checkbox" id="selectAllHeader" onclick="toggleSelectAll()">
                    </th>
                    <th onclick="sortTable('name')">Candidate <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('position')">Position <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('score')">Screening Score <i class="fas fa-sort"></i></th>
                    <th>Qualification Match</th>
                    <th onclick="sortTable('shortlisted_date')">Shortlisted Date <i class="fas fa-sort"></i></th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($candidates)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 60px 20px; color: #95a5a6;">
                        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>No shortlisted candidates found</p>
                        <p style="font-size: 13px; margin-top: 5px;">Candidates who pass screening will appear here</p>
                        <a href="?page=applicant&subpage=screening-evaluation" class="btn-primary btn-sm" style="margin-top: 10px;">
                            <i class="fas fa-clipboard-check"></i> Go to Screening
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($candidates as $candidate): 
                        $photoPath = getApplicantPhoto($candidate);
                        $firstName = $candidate['first_name'] ?? '';
                        $lastName = $candidate['last_name'] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
                        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                    ?>
                    <tr>
                        <td class="checkbox-column">
                            <?php if ($candidate['status'] == 'shortlisted'): ?>
                            <input type="checkbox" name="selected_candidates[]" value="<?php echo $candidate['id']; ?>" class="candidate-checkbox">
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php if ($photoPath): ?>
                                    <img src="<?php echo $photoPath; ?>" 
                                         alt="<?php echo htmlspecialchars($fullName); ?>"
                                         class="applicant-photo-medium"
                                         onerror="handleImageError(this)"
                                         data-initials="<?php echo $initials; ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="photo-fallback-medium">
                                        <?php echo $initials; ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($fullName); ?></strong>
                                    <div style="font-size: 11px; color: #64748b;">#<?php echo $candidate['application_number']; ?></div>
                                    <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($candidate['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($candidate['job_title'])): ?>
                                <strong><?php echo htmlspecialchars($candidate['job_title']); ?></strong>
                                <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($candidate['job_code']); ?></div>
                                <div style="font-size: 11px; color: #64748b; text-transform: capitalize;"><?php echo $candidate['department']; ?></div>
                            <?php else: ?>
                                <span style="color: #64748b;">General Application</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($candidate['screening_score'] !== null): ?>
                                <?php
                                $score_class = 'score-high';
                                if ($candidate['screening_score'] < 40) $score_class = 'score-low';
                                elseif ($candidate['screening_score'] < 70) $score_class = 'score-medium';
                                ?>
                                <span class="score-badge <?php echo $score_class; ?>">
                                    <?php echo $candidate['screening_score']; ?>/100
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($candidate['qualification_match'] !== null): ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-weight: 600;"><?php echo $candidate['qualification_match']; ?>%</span>
                                    <div style="width: 60px; height: 6px; background: #eef2f6; border-radius: 3px;">
                                        <div style="width: <?php echo $candidate['qualification_match']; ?>%; height: 100%; background: linear-gradient(90deg, #0e4c92, #4086e4); border-radius: 3px;"></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span style="color: #94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span><?php echo date('M d, Y', strtotime($candidate['updated_at'])); ?></span>
                            <div class="days-badge" style="margin-top: 5px;">
                                <i class="fas fa-clock"></i> <?php echo $candidate['days_in_shortlist']; ?> days
                            </div>
                        </td>
                        <td>
                            <?php
                            $status_class = 'badge-warning';
                            $status_text = $candidate['shortlist_status'];
                            
                            if ($candidate['status'] == 'interviewed') {
                                $status_class = 'badge-info';
                            } elseif ($candidate['status'] == 'offered') {
                                $status_class = 'badge-purple';
                            } elseif ($candidate['status'] == 'hired') {
                                $status_class = 'badge-success';
                            }
                            ?>
                            <span class="category-badge <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                            
                            <?php if ($candidate['interview_count'] > 0): ?>
                            <div style="font-size: 11px; color: #0e4c92; margin-top: 5px;">
                                <i class="fas fa-calendar-check"></i> <?php echo $candidate['interview_count']; ?> interview(s)
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <?php if ($candidate['status'] == 'shortlisted'): ?>
                                <button type="button" class="btn-success btn-sm" onclick="openScheduleModal(<?php echo $candidate['id']; ?>, '<?php echo htmlspecialchars($fullName); ?>')">
                                    <i class="fas fa-calendar-plus"></i> Schedule
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($candidate['status'] == 'interviewed'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="applicant_id" value="<?php echo $candidate['id']; ?>">
                                    <button type="submit" name="move_to_interview" class="btn-info btn-sm" onclick="return confirm('Mark as ready for job offer?')">
                                        <i class="fas fa-arrow-right"></i> Move to Offer
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <a href="?page=applicant&subpage=applicant-profiles&id=<?php echo $candidate['id']; ?>" class="btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                
                                <div class="action-menu">
                                    <button class="btn-secondary btn-sm">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="action-menu-content">
                                        <a href="?page=recruitment&subpage=interview-scheduling&applicant_id=<?php echo $candidate['id']; ?>" class="action-menu-item">
                                            <i class="fas fa-calendar-alt"></i> Schedule Interview
                                        </a>
                                        <a href="?page=applicant&subpage=screening-evaluation&evaluate=<?php echo $candidate['id']; ?>" class="action-menu-item">
                                            <i class="fas fa-clipboard-check"></i> View Evaluation
                                        </a>
                                        <a href="#" class="action-menu-item" onclick="openRejectModal(<?php echo $candidate['id']; ?>, '<?php echo htmlspecialchars($fullName); ?>')">
                                            <i class="fas fa-times-circle" style="color: #e74c3c;"></i> Reject
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<!-- Schedule Modal (for single candidate) -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus" style="color: #0e4c92;"></i> Schedule Interview</h3>
            <span class="modal-close" onclick="closeScheduleModal()">&times;</span>
        </div>
        <form method="POST" id="scheduleForm">
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
            
            <div class="form-row">
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

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle" style="color: #e74c3c;"></i> Reject Candidate</h3>
            <span class="modal-close" onclick="closeRejectModal()">&times;</span>
        </div>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="applicant_id" id="reject_applicant_id">
            
            <div class="form-group">
                <label>Candidate</label>
                <input type="text" id="reject_candidate_name" readonly disabled style="background: #f8fafd;">
            </div>
            
            <div class="form-group">
                <label>Reason for Rejection</label>
                <textarea name="rejection_reason" rows="4" required placeholder="Please provide reason for rejection..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" name="reject_candidate" class="btn-danger">Reject Candidate</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.candidate-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function confirmBulkSchedule() {
    const checkboxes = document.querySelectorAll('.candidate-checkbox:checked');
    const interviewDate = document.querySelector('input[name="interview_date"]').value;
    
    if (checkboxes.length === 0) {
        alert('Please select at least one candidate');
        return false;
    }
    
    if (!interviewDate) {
        alert('Please select an interview date');
        return false;
    }
    
    return confirm(`Schedule interviews for ${checkboxes.length} candidate(s)?`);
}

function sortTable(column) {
    const url = new URL(window.location.href);
    const params = url.searchParams;
    
    let order = params.get('sort_order') === 'ASC' ? 'DESC' : 'ASC';
    params.set('sort_by', column);
    params.set('sort_order', order);
    
    window.location.href = url.toString();
}

// Schedule Modal Functions
function openScheduleModal(id, name) {
    document.getElementById('schedule_applicant_id').value = id;
    document.getElementById('schedule_candidate_name').value = name;
    document.getElementById('scheduleModal').style.display = 'flex';
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
}

// Reject Modal Functions
function openRejectModal(id, name) {
    document.getElementById('reject_applicant_id').value = id;
    document.getElementById('reject_candidate_name').value = name;
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const scheduleModal = document.getElementById('scheduleModal');
    const rejectModal = document.getElementById('rejectModal');
    
    if (event.target == scheduleModal) {
        scheduleModal.style.display = 'none';
    }
    if (event.target == rejectModal) {
        rejectModal.style.display = 'none';
    }
}

// Update header checkbox state based on individual checkboxes
document.querySelectorAll('.candidate-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.candidate-checkbox');
        const checkedCount = document.querySelectorAll('.candidate-checkbox:checked').length;
        
        selectAll.checked = checkedCount === checkboxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    });
});
</script>