<?php
// modules/probation-evaluations.php
$page_title = "Probation Evaluations";

// Simple log function (if not defined elsewhere)
if (!function_exists('simpleLog')) {
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
}

// Get current user info (supervisor)
$user = getUserInfo($pdo, $_SESSION['user_id']);
$supervisor_id = $user['id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$review_stage_filter = isset($_GET['review_stage']) ? $_GET['review_stage'] : 'all';

// Get departments this supervisor oversees (if any)
// For now, we'll show all, but in real system you'd filter by supervisor's teams
$dept_condition = ""; // Add supervisor-specific logic here

// Get all probationary employees that need evaluation
$query = "
    SELECT 
        pr.id as probation_id,
        pr.new_hire_id,
        pr.probation_start_date,
        pr.probation_end_date,
        pr.status as probation_status,
        nh.employee_id,
        nh.position,
        nh.department,
        nh.supervisor_id,
        ja.first_name,
        ja.last_name,
        ja.photo_path,
        ja.application_number,
        
        -- Evaluation status
        (SELECT COUNT(*) FROM probation_reviews WHERE probation_record_id = pr.id) as review_count,
        (SELECT review_phase FROM probation_reviews WHERE probation_record_id = pr.id ORDER BY created_at DESC LIMIT 1) as last_review_phase,
        (SELECT status FROM probation_reviews WHERE probation_record_id = pr.id ORDER BY created_at DESC LIMIT 1) as last_review_status,
        (SELECT percentage_score FROM probation_reviews WHERE probation_record_id = pr.id ORDER BY created_at DESC LIMIT 1) as last_score,
        
        -- Check if final review exists
        (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') as final_review_id,
        
        -- Days until probation ends
        DATEDIFF(pr.probation_end_date, CURDATE()) as days_remaining,
        
        -- Overdue flag
        CASE 
            WHEN pr.probation_end_date < CURDATE() AND 
                 (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') IS NULL 
            THEN 1 ELSE 0 
        END as is_overdue,
        
        -- Alert flag (within 7 days)
        CASE 
            WHEN pr.probation_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND
                 (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') IS NULL
            THEN 1 ELSE 0 
        END as is_ending_soon,
        
        -- Incident count
        (SELECT COUNT(*) FROM probation_incidents WHERE probation_record_id = pr.id) as incident_count,
        (SELECT COUNT(*) FROM probation_incidents WHERE probation_record_id = pr.id AND severity IN ('major', 'critical')) as serious_incidents,
        
        -- Attendance summary (last 90 days)
        (SELECT COUNT(*) FROM attendance WHERE employee_id = nh.id AND status = 'absent' AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) as absent_days,
        (SELECT COUNT(*) FROM attendance WHERE employee_id = nh.id AND status = 'late' AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) as late_days,
        
        -- Warning count
        (SELECT COUNT(*) FROM probation_incidents WHERE probation_record_id = pr.id AND incident_type = 'warning') as warning_count
        
    FROM probation_records pr
    INNER JOIN new_hires nh ON pr.new_hire_id = nh.id
    INNER JOIN job_applications ja ON nh.applicant_id = ja.id
    WHERE pr.status IN ('ongoing', 'extended')
";

$params = [];

// Filter by supervisor (if applicable)
// $query .= " AND (nh.supervisor_id = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))";
// $params[] = $supervisor_id;
// $params[] = $supervisor_id;

// Status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'pending') {
        $query .= " AND (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') IS NULL";
    } elseif ($status_filter === 'submitted') {
        $query .= " AND (SELECT status FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') = 'submitted'";
    } elseif ($status_filter === 'completed') {
        $query .= " AND (SELECT status FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') = 'acknowledged'";
    } elseif ($status_filter === 'overdue') {
        $query .= " AND pr.probation_end_date < CURDATE() AND (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') IS NULL";
    } elseif ($status_filter === 'ending_soon') {
        $query .= " AND pr.probation_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') IS NULL";
    }
}

// Review stage filter
if ($review_stage_filter !== 'all') {
    if ($review_stage_filter === '30_day') {
        $query .= " AND DATEDIFF(pr.probation_end_date, pr.probation_start_date) >= 30 AND (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = '30_day') IS NULL";
    } elseif ($review_stage_filter === '60_day') {
        $query .= " AND DATEDIFF(pr.probation_end_date, pr.probation_start_date) >= 60 AND (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = '60_day') IS NULL";
    } elseif ($review_stage_filter === 'final') {
        $query .= " AND (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') IS NULL";
    }
}

// Department filter
if (!empty($department_filter)) {
    $query .= " AND nh.department = ?";
    $params[] = $department_filter;
}

// Search filter
if (!empty($search_filter)) {
    $query .= " AND (ja.first_name LIKE ? OR ja.last_name LIKE ? OR nh.employee_id LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY 
    CASE 
        WHEN pr.probation_end_date < CURDATE() AND (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') IS NULL THEN 0
        WHEN pr.probation_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final') IS NULL THEN 1
        ELSE 2
    END,
    pr.probation_end_date ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$probation_employees = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => count($probation_employees),
    'pending' => 0,
    'submitted' => 0,
    'completed' => 0,
    'overdue' => 0,
    'ending_soon' => 0,
    '30_day_due' => 0,
    '60_day_due' => 0,
    'final_due' => 0
];

foreach ($probation_employees as $emp) {
    if ($emp['is_overdue']) $stats['overdue']++;
    if ($emp['is_ending_soon']) $stats['ending_soon']++;
    
    if ($emp['final_review_id']) {
        if ($emp['last_review_status'] == 'submitted') {
            $stats['submitted']++;
        } elseif ($emp['last_review_status'] == 'acknowledged') {
            $stats['completed']++;
        }
    } else {
        $stats['pending']++;
        
        // Check which reviews are due
        $days_on_probation = (strtotime($emp['probation_end_date']) - strtotime($emp['probation_start_date'])) / (60 * 60 * 24);
        $days_elapsed = (time() - strtotime($emp['probation_start_date'])) / (60 * 60 * 24);
        
        if ($days_on_probation >= 30 && $days_elapsed >= 30 && $emp['review_count'] == 0) {
            $stats['30_day_due']++;
        }
        if ($days_on_probation >= 60 && $days_elapsed >= 60 && $emp['review_count'] <= 1) {
            $stats['60_day_due']++;
        }
        if ($days_elapsed >= $days_on_probation * 0.8) { // Final review due when 80% through
            $stats['final_due']++;
        }
    }
}

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department FROM new_hires WHERE department IS NOT NULL ORDER BY department");
$departments = $stmt->fetchAll();

// Get KPIs by department for the evaluation form
$kpis_by_dept = [];
$depts = ['driver', 'warehouse', 'logistics', 'admin', 'management'];
foreach ($depts as $dept) {
    $stmt = $pdo->prepare("
        SELECT * FROM probation_kpis 
        WHERE department = ? AND is_active = 1 
        ORDER BY sort_order
    ");
    $stmt->execute([$dept]);
    $kpis_by_dept[$dept] = $stmt->fetchAll();
}

// Behavioral criteria (same for all)
$behavioral_criteria = [
    ['name' => 'Communication', 'description' => 'Effectively communicates with team and supervisors'],
    ['name' => 'Teamwork', 'description' => 'Collaborates well with others'],
    ['name' => 'Initiative', 'description' => 'Takes proactive approach to tasks'],
    ['name' => 'Professional Conduct', 'description' => 'Demonstrates professionalism and respect'],
    ['name' => 'Adaptability', 'description' => 'Adapts to changing situations and feedback']
];

// Handle form submission (save draft or submit)
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_draft':
            case 'submit_evaluation':
                $probation_id = $_POST['probation_id'] ?? 0;
                $review_phase = $_POST['review_phase'] ?? 'final';
                $review_date = $_POST['review_date'] ?? date('Y-m-d');
                
                // KPI ratings
                $kpi_ratings = $_POST['kpi_rating'] ?? [];
                $kpi_comments = $_POST['kpi_comment'] ?? [];
                
                // Behavioral ratings
                $behavioral_ratings = $_POST['behavioral_rating'] ?? [];
                $behavioral_comments = $_POST['behavioral_comment'] ?? [];
                
                // Overall assessment
                $overall_assessment = $_POST['overall_assessment'] ?? '';
                $strengths = $_POST['strengths'] ?? '';
                $weaknesses = $_POST['weaknesses'] ?? '';
                $recommendation = $_POST['recommendation'] ?? '';
                
                // Extension fields
                $extension_duration = $_POST['extension_duration'] ?? 30;
                $improvement_areas = $_POST['improvement_areas'] ?? '';
                
                // Termination fields
                $termination_reason = $_POST['termination_reason'] ?? '';
                
                // Calculate scores
                $total_kpi_score = 0;
                $kpi_count = count($kpi_ratings);
                foreach ($kpi_ratings as $rating) {
                    $total_kpi_score += intval($rating);
                }
                $avg_kpi_score = $kpi_count > 0 ? $total_kpi_score / $kpi_count : 0;
                
                $total_behavioral_score = 0;
                $behavioral_count = count($behavioral_ratings);
                foreach ($behavioral_ratings as $rating) {
                    $total_behavioral_score += intval($rating);
                }
                $avg_behavioral_score = $behavioral_count > 0 ? $total_behavioral_score / $behavioral_count : 0;
                
                // Weighted score (70% KPI, 30% Behavior)
                $weighted_score = ($avg_kpi_score * 0.7) + ($avg_behavioral_score * 0.3);
                $percentage_score = ($weighted_score / 5) * 100;
                
                $status = ($_POST['action'] == 'submit_evaluation') ? 'submitted' : 'draft';
                
                try {
                    $pdo->beginTransaction();
                    
                    // Insert probation review
                    $stmt = $pdo->prepare("
                        INSERT INTO probation_reviews 
                        (probation_record_id, review_phase, review_date, reviewer_id, 
                         overall_score, max_score, percentage_score, strengths, weaknesses, 
                         improvement_areas, recommendation, status, created_at)
                        VALUES (?, ?, ?, ?, ?, 5, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $probation_id,
                        $review_phase,
                        $review_date,
                        $supervisor_id,
                        $weighted_score,
                        $percentage_score,
                        $strengths,
                        $weaknesses,
                        $improvement_areas,
                        $recommendation,
                        $status
                    ]);
                    
                    $review_id = $pdo->lastInsertId();
                    
                    // Insert KPI results
                    foreach ($kpi_ratings as $kpi_id => $rating) {
                        $comment = $kpi_comments[$kpi_id] ?? '';
                        $stmt = $pdo->prepare("
                            INSERT INTO probation_kpi_results 
                            (probation_record_id, kpi_id, review_phase, rating, comments, evaluated_by, evaluated_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $probation_id,
                            $kpi_id,
                            $review_phase,
                            $rating,
                            $comment,
                            $supervisor_id
                        ]);
                    }
                    
                    // Log activity
                    simpleLog($pdo, $supervisor_id, 'probation_evaluation', 
                        "Submitted {$review_phase} evaluation for probation ID: {$probation_id}");
                    
                    $pdo->commit();
                    
                    $message = "Evaluation " . ($status == 'submitted' ? 'submitted' : 'saved as draft') . " successfully!";
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error saving evaluation: " . $e->getMessage();
                }
                break;
        }
    }
}
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

.header-actions {
    display: flex;
    gap: 10px;
}

/* Stats Grid */
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
    transition: all 0.3s;
    border-left: 5px solid;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
}

.stat-card.primary { border-left-color: #3498db; }
.stat-card.warning { border-left-color: #f39c12; }
.stat-card.danger { border-left-color: #e74c3c; }
.stat-card.success { border-left-color: #27ae60; }
.stat-card.purple { border-left-color: #9b59b6; }
.stat-card.orange { border-left-color: #e67e22; }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.primary { background: #3498db20; color: #3498db; }
.stat-icon.warning { background: #f39c1220; color: #f39c12; }
.stat-icon.danger { background: #e74c3c20; color: #e74c3c; }
.stat-icon.success { background: #27ae6020; color: #27ae60; }
.stat-icon.purple { background: #9b59b620; color: #9b59b6; }
.stat-icon.orange { background: #e67e2220; color: #e67e22; }

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 5px;
    font-weight: 500;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

/* Alert Cards */
.alert-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.alert-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    border-left: 5px solid;
}

.alert-card.warning { border-left-color: #f39c12; }
.alert-card.danger { border-left-color: #e74c3c; }
.alert-card.info { border-left-color: #3498db; }

.alert-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.alert-icon.warning { background: #f39c1220; color: #f39c12; }
.alert-icon.danger { background: #e74c3c20; color: #e74c3c; }
.alert-icon.info { background: #3498db20; color: #3498db; }

.alert-content {
    flex: 1;
}

.alert-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 3px;
}

.alert-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark);
}

.alert-link {
    font-size: 12px;
    color: var(--primary);
    text-decoration: none;
    cursor: pointer;
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

.btn-success {
    background: #27ae60;
    color: white;
}

.btn-warning {
    background: #f39c12;
    color: white;
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-sm {
    padding: 5px 12px;
    font-size: 12px;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow-x: auto;
    margin-bottom: 25px;
}

.evaluation-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.evaluation-table th {
    text-align: left;
    padding: 15px 10px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
    background: white;
}

.evaluation-table td {
    padding: 15px 10px;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
    vertical-align: middle;
}

.evaluation-table tr {
    transition: all 0.3s;
    cursor: pointer;
}

.evaluation-table tr:hover {
    background: var(--light-gray);
}

.evaluation-table tr.urgent-row {
    background: #fff3f3;
}

.evaluation-table tr.warning-row {
    background: #fff9e6;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    object-fit: cover;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}

.employee-info div {
    min-width: 150px;
}

/* Status Badge */
.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.status-badge.pending { 
    background: #f39c1220; 
    color: #f39c12; 
    border: 1px solid #f39c1240;
}
.status-badge.submitted { 
    background: #3498db20; 
    color: #3498db; 
    border: 1px solid #3498db40;
}
.status-badge.completed { 
    background: #27ae6020; 
    color: #27ae60; 
    border: 1px solid #27ae6040;
}
.status-badge.overdue { 
    background: #e74c3c20; 
    color: #e74c3c; 
    border: 1px solid #e74c3c40;
}

/* Stage Badge */
.stage-badge {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
    background: #3498db20;
    color: #3498db;
    border: 1px solid #3498db40;
}

/* Action Buttons Container */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
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
    overflow-y: auto;
    padding: 20px;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 25px;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
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
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
}

.modal-header h3 {
    font-size: 20px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close {
    font-size: 24px;
    cursor: pointer;
    color: var(--gray);
    transition: color 0.3s;
}

.modal-close:hover {
    color: var(--danger);
}

/* Employee Summary Section */
.employee-summary {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 15px;
}

.summary-item {
    background: white;
    border-radius: 12px;
    padding: 12px;
    text-align: center;
}

.summary-label {
    font-size: 11px;
    color: var(--gray);
    margin-bottom: 5px;
}

.summary-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
}

.summary-value.warning {
    color: #e74c3c;
}

.summary-value.success {
    color: #27ae60;
}

/* KPI Section */
.kpi-section {
    background: white;
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: var(--primary);
}

.kpi-table {
    width: 100%;
    border-collapse: collapse;
}

.kpi-table th {
    text-align: left;
    padding: 10px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    border-bottom: 1px solid var(--border);
}

.kpi-table td {
    padding: 10px;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
}

.kpi-table tr:last-child td {
    border-bottom: none;
}

.kpi-table input[type="number"],
.kpi-table select,
.kpi-table textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    transition: all 0.3s;
}

.kpi-table select {
    width: 120px;
}

.kpi-table textarea {
    min-height: 60px;
    resize: vertical;
}

.kpi-table input:focus,
.kpi-table select:focus,
.kpi-table textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

/* Score Display */
.score-display {
    display: flex;
    align-items: center;
    gap: 30px;
    margin: 20px 0;
    padding: 25px;
    background: linear-gradient(135deg, var(--primary-transparent) 0%, rgba(14, 76, 146, 0.05) 100%);
    border-radius: 15px;
}

.score-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    flex-shrink: 0;
}

.score-value {
    font-size: 42px;
    font-weight: 700;
    color: var(--primary);
    line-height: 1;
}

.score-label {
    font-size: 12px;
    color: var(--gray);
}

.score-details {
    flex: 1;
}

.score-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px dashed var(--border);
}

.score-row:last-child {
    border-bottom: none;
}

.score-row .label {
    color: var(--gray);
    font-size: 14px;
}

.score-row .value {
    font-weight: 600;
    color: var(--dark);
    font-size: 16px;
}

/* Recommendation Section */
.recommendation-section {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 20px;
    margin: 20px 0;
}

.recommendation-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin: 15px 0;
}

.recommendation-option {
    background: white;
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.recommendation-option:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px var(--primary-transparent);
}

.recommendation-option.selected {
    border-color: var(--primary);
    background: var(--primary-transparent);
}

.recommendation-option .title {
    font-weight: 600;
    font-size: 15px;
    margin-bottom: 5px;
}

.recommendation-option .desc {
    font-size: 11px;
    color: var(--gray);
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

/* Alert Messages */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .recommendation-options {
        grid-template-columns: 1fr;
    }
    
    .score-display {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header-unique {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- ==================== HTML CONTENT ==================== -->

<!-- Alert Messages -->
<?php if ($message): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo $error; ?>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-title">
        <i class="fas fa-clipboard-check"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="exportList()">
            <i class="fas fa-download"></i> Export List
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card primary">
        <div class="stat-icon primary">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total on Probation</div>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
        </div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Pending Evaluation</div>
            <div class="stat-value"><?php echo $stats['pending']; ?></div>
        </div>
    </div>
    
    <div class="stat-card danger">
        <div class="stat-icon danger">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Overdue</div>
            <div class="stat-value"><?php echo $stats['overdue']; ?></div>
        </div>
    </div>
    
    <div class="stat-card orange">
        <div class="stat-icon orange">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Ending Soon</div>
            <div class="stat-value"><?php echo $stats['ending_soon']; ?></div>
        </div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Submitted</div>
            <div class="stat-value"><?php echo $stats['submitted']; ?></div>
        </div>
    </div>
    
    <div class="stat-card purple">
        <div class="stat-icon purple">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?php echo $stats['completed']; ?></div>
        </div>
    </div>
</div>

<!-- Alert Section -->
<div class="alert-section">
    <?php if ($stats['overdue'] > 0): ?>
    <div class="alert-card danger">
        <div class="alert-icon danger">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Overdue Evaluations</div>
            <div class="alert-value"><?php echo $stats['overdue']; ?> employees</div>
            <a href="#" class="alert-link" onclick="applyFilter('overdue')">Evaluate Now →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['ending_soon'] > 0): ?>
    <div class="alert-card warning">
        <div class="alert-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Ending Within 7 Days</div>
            <div class="alert-value"><?php echo $stats['ending_soon']; ?> employees</div>
            <a href="#" class="alert-link" onclick="applyFilter('ending_soon')">Review →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['final_due'] > 0): ?>
    <div class="alert-card info">
        <div class="alert-icon info">
            <i class="fas fa-flag"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Final Reviews Due</div>
            <div class="alert-value"><?php echo $stats['final_due']; ?> employees</div>
            <a href="#" class="alert-link" onclick="applyFilter('final')">Complete →</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Evaluations
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="probation-evaluations">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    <option value="ending_soon" <?php echo $status_filter == 'ending_soon' ? 'selected' : ''; ?>>Ending Soon</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Review Stage</label>
                <select name="review_stage">
                    <option value="all" <?php echo $review_stage_filter == 'all' ? 'selected' : ''; ?>>All Stages</option>
                    <option value="30_day" <?php echo $review_stage_filter == '30_day' ? 'selected' : ''; ?>>30-Day Review</option>
                    <option value="60_day" <?php echo $review_stage_filter == '60_day' ? 'selected' : ''; ?>>60-Day Review</option>
                    <option value="final" <?php echo $review_stage_filter == 'final' ? 'selected' : ''; ?>>Final Review</option>
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
                <input type="text" name="search" placeholder="Name or Employee ID" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=probation-evaluations" class="btn btn-secondary btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Apply
            </button>
        </div>
    </form>
</div>

<!-- Main Table -->
<div class="table-container">
    <table class="evaluation-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Position/Dept</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Days Left</th>
                <th>Review Stage</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($probation_employees as $emp): 
                $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
                $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
                $photoPath = !empty($emp['photo_path']) && file_exists('../../' . $emp['photo_path']) ? '../../' . $emp['photo_path'] : null;
                
                // Determine status
                if ($emp['is_overdue']) {
                    $status_class = 'overdue';
                    $status_text = 'Overdue';
                } elseif ($emp['final_review_id']) {
                    if ($emp['last_review_status'] == 'submitted') {
                        $status_class = 'submitted';
                        $status_text = 'Submitted';
                    } elseif ($emp['last_review_status'] == 'acknowledged') {
                        $status_class = 'completed';
                        $status_text = 'Completed';
                    } else {
                        $status_class = 'pending';
                        $status_text = 'Pending';
                    }
                } else {
                    $status_class = 'pending';
                    $status_text = 'Pending';
                }
                
                // Determine review stage
                if ($emp['final_review_id']) {
                    $stage_text = 'Final Review';
                } elseif ($emp['review_count'] == 0) {
                    $stage_text = 'Initial Review';
                } elseif ($emp['review_count'] == 1) {
                    $stage_text = 'Mid-term Review';
                } else {
                    $stage_text = 'Final Review Due';
                }
                
                $row_class = '';
                if ($emp['is_overdue']) $row_class = 'urgent-row';
                elseif ($emp['is_ending_soon']) $row_class = 'warning-row';
            ?>
            <tr class="<?php echo $row_class; ?>">
                <td>
                    <div class="employee-info">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fullName); ?>"
                                 class="table-avatar"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                 loading="lazy">
                            <div class="table-avatar" style="display: none;"><?php echo $initials; ?></div>
                        <?php else: ?>
                            <div class="table-avatar"><?php echo $initials; ?></div>
                        <?php endif; ?>
                        <div>
                            <strong><?php echo htmlspecialchars($fullName); ?></strong>
                            <div style="font-size: 11px; color: var(--gray); margin-top: 2px;"><?php echo $emp['employee_id'] ?: 'No ID'; ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 500;"><?php echo htmlspecialchars($emp['position']); ?></div>
                    <div style="font-size: 11px; color: var(--gray); margin-top: 2px;"><?php echo ucfirst($emp['department']); ?></div>
                </td>
                <td><?php echo date('M d, Y', strtotime($emp['probation_start_date'])); ?></td>
                <td><?php echo date('M d, Y', strtotime($emp['probation_end_date'])); ?></td>
                <td>
                    <?php if ($emp['days_remaining'] > 0): ?>
                        <span style="font-weight: 600; <?php echo $emp['days_remaining'] <= 7 ? 'color: #e74c3c;' : ''; ?>">
                            <?php echo $emp['days_remaining']; ?> days
                        </span>
                    <?php else: ?>
                        <span style="color: #e74c3c; font-weight: 600;">Overdue</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="stage-badge">
                        <?php echo $stage_text; ?>
                    </span>
                    <?php if ($emp['last_score']): ?>
                    <div style="font-size: 10px; color: var(--gray); margin-top: 3px;">
                        Last Score: <?php echo round($emp['last_score']); ?>%
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo $status_text; ?>
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <?php if (!$emp['final_review_id'] || $emp['last_review_status'] == 'draft'): ?>
                        <button class="btn btn-primary btn-sm" onclick="openEvaluationModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
                            <i class="fas fa-clipboard-check"></i> Evaluate
                        </button>
                        <?php elseif ($emp['last_review_status'] == 'submitted'): ?>
                        <button class="btn btn-secondary btn-sm" onclick="viewEvaluation(<?php echo $emp['probation_id']; ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php else: ?>
                        <button class="btn btn-success btn-sm" disabled>
                            <i class="fas fa-check"></i> Completed
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($probation_employees)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 60px;">
                    <i class="fas fa-clipboard-list" style="font-size: 48px; color: var(--gray); opacity: 0.3;"></i>
                    <p style="margin-top: 15px; color: var(--dark);">No probation evaluations found</p>
                    <p style="color: var(--gray); font-size: 13px;">No employees match your current filters.</p>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Evaluation Modal -->
<div id="evaluationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check"></i> Probation Evaluation Form</h3>
            <span class="modal-close" onclick="closeEvaluationModal()">&times;</span>
        </div>
        
        <form method="POST" action="" id="evaluationForm">
            <input type="hidden" name="action" id="form_action" value="save_draft">
            <input type="hidden" name="probation_id" id="probation_id" value="">
            <input type="hidden" name="review_phase" id="review_phase" value="final">
            <input type="hidden" name="review_date" value="<?php echo date('Y-m-d'); ?>">
            
            <!-- Employee Summary (Read-only) -->
            <div class="employee-summary">
                <h4 style="margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-circle" style="color: var(--primary);"></i>
                    Employee Summary
                </h4>
                
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <div id="modal_avatar" style="width: 70px; height: 70px; border-radius: 15px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; font-weight: 600;"></div>
                    <div>
                        <h3 id="modal_name" style="margin: 0 0 5px 0; font-size: 20px;"></h3>
                        <p id="modal_position" style="margin: 0; color: var(--gray); font-size: 14px;"></p>
                    </div>
                </div>
                
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Start Date</div>
                        <div class="summary-value" id="modal_start_date"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">End Date</div>
                        <div class="summary-value" id="modal_end_date"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Days Left</div>
                        <div class="summary-value" id="modal_days_left"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Department</div>
                        <div class="summary-value" id="modal_department"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Attendance</div>
                        <div class="summary-value" id="modal_attendance"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Incidents</div>
                        <div class="summary-value" id="modal_incidents"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Warnings</div>
                        <div class="summary-value" id="modal_warnings"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Supervisor</div>
                        <div class="summary-value" id="modal_supervisor">You</div>
                    </div>
                </div>
            </div>
            
            <!-- KPI Ratings Section -->
            <div class="kpi-section" id="kpi_section">
                <div class="section-title">
                    <i class="fas fa-chart-line"></i>
                    KPI Performance Ratings
                </div>
                
                <table class="kpi-table">
                    <thead>
                        <tr>
                            <th>KPI</th>
                            <th>Target</th>
                            <th>Rating (1-5)</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody id="kpi_rows">
                        <!-- Will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <!-- Behavioral Assessment -->
            <div class="kpi-section">
                <div class="section-title">
                    <i class="fas fa-users"></i>
                    Behavioral Assessment
                </div>
                
                <table class="kpi-table">
                    <thead>
                        <tr>
                            <th>Criteria</th>
                            <th>Rating (1-5)</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($behavioral_criteria as $index => $criteria): ?>
                        <tr>
                            <td>
                                <strong><?php echo $criteria['name']; ?></strong>
                                <div style="font-size: 11px; color: var(--gray);"><?php echo $criteria['description']; ?></div>
                            </td>
                            <td style="width: 150px;">
                                <select name="behavioral_rating[<?php echo $index; ?>]" class="behavioral-rating" required onchange="updateScores()">
                                    <option value="">Select Rating</option>
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Satisfactory</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </td>
                            <td>
                                <textarea name="behavioral_comment[<?php echo $index; ?>]" rows="2" placeholder="Optional comments..."></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Score Display (Auto-calculated) -->
            <div class="score-display" id="score_display">
                <div class="score-circle">
                    <span class="score-value" id="final_score">0.0</span>
                    <span class="score-label">Final Score</span>
                </div>
                <div class="score-details">
                    <div class="score-row">
                        <span class="label">KPI Average:</span>
                        <span class="value" id="kpi_avg">0.0</span>
                    </div>
                    <div class="score-row">
                        <span class="label">Behavioral Average:</span>
                        <span class="value" id="behavioral_avg">0.0</span>
                    </div>
                    <div class="score-row">
                        <span class="label">Weighted Score (70% KPI / 30% Behavior):</span>
                        <span class="value" id="weighted_score">0.0</span>
                    </div>
                    <div class="score-row">
                        <span class="label">Percentage:</span>
                        <span class="value" id="percentage">0%</span>
                    </div>
                </div>
            </div>
            
            <!-- Overall Assessment -->
            <div class="kpi-section">
                <div class="section-title">
                    <i class="fas fa-pen"></i>
                    Overall Performance Summary
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Strengths <span style="color: var(--danger);">*</span></label>
                    <textarea name="strengths" rows="3" placeholder="What are the employee's key strengths?" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 10px;"></textarea>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Areas for Improvement <span style="color: var(--danger);">*</span></label>
                    <textarea name="weaknesses" rows="3" placeholder="What areas need improvement?" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 10px;"></textarea>
                </div>
                
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Overall Assessment <span style="color: var(--danger);">*</span></label>
                    <textarea name="overall_assessment" rows="4" placeholder="Provide a comprehensive assessment of the employee's performance during probation..." required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 10px;"></textarea>
                </div>
            </div>
            
            <!-- Recommendation Section -->
            <div class="recommendation-section">
                <div class="section-title">
                    <i class="fas fa-gavel"></i>
                    Recommendation <span style="color: var(--danger);">*</span>
                </div>
                
                <div class="recommendation-options">
                    <div class="recommendation-option confirm" onclick="selectRecommendation('confirm')">
                        <div class="title"><i class="fas fa-check-circle" style="color: #27ae60;"></i> Confirm Employment</div>
                        <div class="desc">Employee meets all requirements</div>
                    </div>
                    <div class="recommendation-option extend" onclick="selectRecommendation('extend')">
                        <div class="title"><i class="fas fa-hourglass-half" style="color: #f39c12;"></i> Extend Probation</div>
                        <div class="desc">Need more time to assess</div>
                    </div>
                    <div class="recommendation-option terminate" onclick="selectRecommendation('terminate')">
                        <div class="title"><i class="fas fa-ban" style="color: #e74c3c;"></i> Terminate Employment</div>
                        <div class="desc">Does not meet requirements</div>
                    </div>
                    <div class="recommendation-option promote" onclick="selectRecommendation('promote')">
                        <div class="title"><i class="fas fa-arrow-up" style="color: #9b59b6;"></i> Promote & Confirm</div>
                        <div class="desc">Exceptional performance</div>
                    </div>
                </div>
                
                <input type="hidden" name="recommendation" id="selected_recommendation" value="">
                
                <!-- Extension Fields -->
                <div id="extension_fields" style="display: none; margin-top: 20px; padding: 15px; background: white; border-radius: 10px;">
                    <h4 style="margin: 0 0 15px 0; color: #f39c12;">Extension Details</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Extension Duration</label>
                            <select name="extension_duration" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 8px;">
                                <option value="30">30 days</option>
                                <option value="45">45 days</option>
                                <option value="60">60 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Improvement Areas</label>
                            <textarea name="improvement_areas" rows="2" placeholder="Specify areas requiring improvement..." style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 8px;"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Termination Fields -->
                <div id="termination_fields" style="display: none; margin-top: 20px; padding: 15px; background: white; border-radius: 10px;">
                    <h4 style="margin: 0 0 15px 0; color: #e74c3c;">Termination Details</h4>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Reason for Termination <span style="color: var(--danger);">*</span></label>
                        <textarea name="termination_reason" rows="3" placeholder="Provide detailed reason for termination..." style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 8px;"></textarea>
                    </div>
                </div>
                
                <!-- Promotion Fields -->
                <div id="promotion_fields" style="display: none; margin-top: 20px; padding: 15px; background: white; border-radius: 10px;">
                    <h4 style="margin: 0 0 15px 0; color: #9b59b6;">Promotion Details</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Recommended Position</label>
                            <input type="text" name="promotion_position" placeholder="e.g., Senior Driver" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Justification</label>
                            <textarea name="promotion_justification" rows="2" placeholder="Why does this employee deserve promotion?" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 8px;"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEvaluationModal()">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="saveDraft()">
                    <i class="fas fa-save"></i> Save Draft
                </button>
                <button type="button" class="btn btn-success" onclick="submitEvaluation()">
                    <i class="fas fa-paper-plane"></i> Submit to HR
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript -->
<script>
let currentEmployee = null;

function applyFilter(type) {
    const url = new URL(window.location.href);
    if (type === 'overdue') {
        url.searchParams.set('status', 'overdue');
    } else if (type === 'ending_soon') {
        url.searchParams.set('status', 'ending_soon');
    } else if (type === 'final') {
        url.searchParams.set('review_stage', 'final');
    }
    window.location.href = url.toString();
}

function openEvaluationModal(employee) {
    currentEmployee = employee;
    
    // Set basic info
    document.getElementById('probation_id').value = employee.probation_id;
    document.getElementById('modal_name').textContent = employee.first_name + ' ' + employee.last_name;
    document.getElementById('modal_position').textContent = employee.position + ' • ' + employee.department;
    document.getElementById('modal_start_date').textContent = new Date(employee.probation_start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    document.getElementById('modal_end_date').textContent = new Date(employee.probation_end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    document.getElementById('modal_days_left').textContent = employee.days_remaining > 0 ? employee.days_remaining + ' days' : 'Overdue';
    document.getElementById('modal_department').textContent = employee.department.toUpperCase();
    document.getElementById('modal_attendance').textContent = (employee.absent_days || 0) + ' absent, ' + (employee.late_days || 0) + ' late';
    document.getElementById('modal_incidents').textContent = employee.incident_count || 0;
    document.getElementById('modal_warnings').textContent = employee.warning_count || 0;
    
    // Set avatar
    const initials = (employee.first_name[0] + employee.last_name[0]).toUpperCase();
    document.getElementById('modal_avatar').textContent = initials;
    
    // Load KPIs based on department
    loadKPIs(employee.department);
    
    // Reset form
    document.getElementById('selected_recommendation').value = '';
    document.querySelectorAll('.recommendation-option').forEach(opt => opt.classList.remove('selected'));
    document.getElementById('extension_fields').style.display = 'none';
    document.getElementById('termination_fields').style.display = 'none';
    document.getElementById('promotion_fields').style.display = 'none';
    
    // Reset all ratings
    document.querySelectorAll('.kpi-rating, .behavioral-rating').forEach(select => select.value = '');
    document.querySelectorAll('textarea').forEach(textarea => textarea.value = '');
    
    // Reset scores
    updateScores();
    
    // Show modal
    document.getElementById('evaluationModal').classList.add('active');
}

function closeEvaluationModal() {
    document.getElementById('evaluationModal').classList.remove('active');
}

function loadKPIs(department) {
    // This would normally load from PHP, but for now we'll use static data
    const kpiData = {
        'driver': [
            { name: 'On-time Delivery Rate', target: '95%', id: 1 },
            { name: 'Safety Compliance', target: '100%', id: 2 },
            { name: 'Accident Record', target: '0', id: 3 },
            { name: 'Customer Complaints', target: '0', id: 4 },
            { name: 'Attendance & Punctuality', target: '95%', id: 5 }
        ],
        'warehouse': [
            { name: 'Picking Accuracy', target: '98%', id: 6 },
            { name: 'Processing Speed', target: '90%', id: 7 },
            { name: 'Inventory Error Rate', target: '<2%', id: 8 },
            { name: 'Safety Compliance', target: '100%', id: 9 },
            { name: 'Team Cooperation', target: 'Good', id: 10 }
        ],
        'logistics': [
            { name: 'Route Optimization', target: '90%', id: 11 },
            { name: 'Dispatch Accuracy', target: '98%', id: 12 },
            { name: 'Communication', target: 'Good', id: 13 },
            { name: 'Problem Resolution', target: '85%', id: 14 },
            { name: 'Documentation', target: '98%', id: 15 }
        ],
        'admin': [
            { name: 'Task Completion Rate', target: '95%', id: 16 },
            { name: 'Accuracy of Work', target: '98%', id: 17 },
            { name: 'Responsiveness', target: '90%', id: 18 },
            { name: 'Attendance', target: '95%', id: 19 },
            { name: 'Initiative', target: 'Good', id: 20 }
        ],
        'management': [
            { name: 'Team Performance', target: '85%', id: 21 },
            { name: 'Decision Making', target: 'Good', id: 22 },
            { name: 'Process Improvement', target: '2', id: 23 },
            { name: 'Communication', target: 'Good', id: 24 },
            { name: 'Leadership', target: 'Good', id: 25 }
        ]
    };
    
    const kpis = kpiData[department] || kpiData['driver'];
    const tbody = document.getElementById('kpi_rows');
    tbody.innerHTML = '';
    
    kpis.forEach((kpi) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <strong>${kpi.name}</strong>
                <input type="hidden" name="kpi_id[${kpi.id}]" value="${kpi.id}">
            </td>
            <td style="width: 80px;"><span style="font-weight: 600;">${kpi.target}</span></td>
            <td style="width: 150px;">
                <select name="kpi_rating[${kpi.id}]" class="kpi-rating" onchange="updateScores()" required>
                    <option value="">Select Rating</option>
                    <option value="1">1 - Poor</option>
                    <option value="2">2 - Below Average</option>
                    <option value="3">3 - Satisfactory</option>
                    <option value="4">4 - Good</option>
                    <option value="5">5 - Excellent</option>
                </select>
            </td>
            <td>
                <textarea name="kpi_comment[${kpi.id}]" rows="2" placeholder="Optional comments..."></textarea>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function updateScores() {
    // Calculate KPI average
    const kpiRatings = document.querySelectorAll('.kpi-rating');
    let kpiTotal = 0;
    let kpiCount = 0;
    
    kpiRatings.forEach(select => {
        if (select.value) {
            kpiTotal += parseInt(select.value);
            kpiCount++;
        }
    });
    
    const kpiAvg = kpiCount > 0 ? (kpiTotal / kpiCount).toFixed(1) : 0;
    
    // Calculate Behavioral average
    const behavioralRatings = document.querySelectorAll('.behavioral-rating');
    let behavioralTotal = 0;
    let behavioralCount = 0;
    
    behavioralRatings.forEach(select => {
        if (select.value) {
            behavioralTotal += parseInt(select.value);
            behavioralCount++;
        }
    });
    
    const behavioralAvg = behavioralCount > 0 ? (behavioralTotal / behavioralCount).toFixed(1) : 0;
    
    // Calculate weighted score (70% KPI, 30% Behavior)
    const weightedScore = ((kpiAvg * 0.7) + (behavioralAvg * 0.3)).toFixed(1);
    const percentage = Math.round((weightedScore / 5) * 100);
    
    // Update display
    document.getElementById('kpi_avg').textContent = kpiAvg;
    document.getElementById('behavioral_avg').textContent = behavioralAvg;
    document.getElementById('weighted_score').textContent = weightedScore;
    document.getElementById('percentage').textContent = percentage + '%';
    document.getElementById('final_score').textContent = weightedScore;
}

function selectRecommendation(type) {
    document.querySelectorAll('.recommendation-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    
    document.querySelector(`.recommendation-option.${type}`).classList.add('selected');
    document.getElementById('selected_recommendation').value = type;
    
    // Show relevant fields
    document.getElementById('extension_fields').style.display = type === 'extend' ? 'block' : 'none';
    document.getElementById('termination_fields').style.display = type === 'terminate' ? 'block' : 'none';
    document.getElementById('promotion_fields').style.display = type === 'promote' ? 'block' : 'none';
}

function saveDraft() {
    document.getElementById('form_action').value = 'save_draft';
    document.getElementById('evaluationForm').submit();
}

function submitEvaluation() {
    if (!document.getElementById('selected_recommendation').value) {
        alert('Please select a recommendation');
        return;
    }
    
    if (confirm('Are you sure you want to submit this evaluation? You will not be able to edit it after submission.')) {
        document.getElementById('form_action').value = 'submit_evaluation';
        document.getElementById('evaluationForm').submit();
    }
}

function viewEvaluation(id) {
    alert('View evaluation details for ID: ' + id);
}

function exportList() {
    alert('Exporting evaluation list...');
}

// Add event listeners for real-time score updates
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('kpi-rating') || e.target.classList.contains('behavioral-rating')) {
        updateScores();
    }
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});
</script>