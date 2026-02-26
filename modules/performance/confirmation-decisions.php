<?php
// Start output buffering at the VERY FIRST LINE
ob_start();

// modules/hr/confirmation-decisions.php
$page_title = "Confirmation Decisions";

// Include required files
require_once 'includes/config.php';
require_once 'config/mail_config.php';

// Get current user ID from session
$current_user_id = $_SESSION['user_id'] ?? 1;

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$decision_filter = isset($_GET['decision']) ? $_GET['decision'] : 'all';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

// Simple log function
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

/**
 * Helper Functions
 */
function getEmployeePhoto($employee) {
    if (!empty($employee['photo_path']) && file_exists('../../' . $employee['photo_path'])) {
        return '../../' . htmlspecialchars($employee['photo_path']);
    }
    return null;
}

function getDecisionBadge($decision) {
    $badges = [
        'confirm' => ['label' => 'Confirm', 'color' => '#27ae60', 'bg' => '#27ae6020'],
        'extend' => ['label' => 'Extend', 'color' => '#f39c12', 'bg' => '#f39c1220'],
        'terminate' => ['label' => 'Terminate', 'color' => '#e74c3c', 'bg' => '#e74c3c20'],
        'promote' => ['label' => 'Promote', 'color' => '#9b59b6', 'bg' => '#9b59b620'],
        'pending' => ['label' => 'Pending', 'color' => '#7f8c8d', 'bg' => '#7f8c8d20']
    ];
    return $badges[$decision] ?? $badges['pending'];
}

function getScoreClass($score) {
    if ($score >= 85) return 'score-excellent';
    if ($score >= 75) return 'score-good';
    if ($score >= 60) return 'score-average';
    return 'score-poor';
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'make_decision':
                // Process final decision
                $employee_id = $_POST['employee_id'] ?? 0;
                $probation_id = $_POST['probation_id'] ?? 0;
                $final_decision = $_POST['final_decision'] ?? '';
                $decision_notes = $_POST['decision_notes'] ?? '';
                $hr_comments = $_POST['hr_comments'] ?? '';
                $decision_date = $_POST['decision_date'] ?? date('Y-m-d');
                
                // Debug - log received values
                error_log("Decision Form Submitted - Employee ID: $employee_id, Probation ID: $probation_id, Decision: $final_decision");
                
                // Extension specific fields
                $extension_days = isset($_POST['extension_days']) ? intval($_POST['extension_days']) : 30;
                $improvement_plan = $_POST['improvement_plan'] ?? '';
                
                // Promotion specific fields
                $new_position = $_POST['new_position'] ?? '';
                $new_department = $_POST['new_department'] ?? '';
                $new_salary = $_POST['new_salary'] ?? 0;
                $new_salary_grade = $_POST['new_salary_grade'] ?? '';
                
                // Validate required fields
                if (empty($employee_id) || empty($probation_id) || empty($final_decision)) {
                    $error = "Missing required fields. Please select a decision and ensure employee is selected.";
                    error_log("Validation failed - employee_id: $employee_id, probation_id: $probation_id, decision: $final_decision");
                    break;
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Check if probation record exists
                    $check = $pdo->prepare("SELECT * FROM probation_records WHERE id = ?");
                    $check->execute([$probation_id]);
                    $record = $check->fetch();
                    
                    if (!$record) {
                        throw new Exception("Probation record not found");
                    }
                    
                    // Update probation record
                    $status = ($final_decision == 'confirm' || $final_decision == 'promote') ? 'completed' : 
                             (($final_decision == 'extend') ? 'extended' : 'terminated');
                    
                    $update = $pdo->prepare("
                        UPDATE probation_records 
                        SET status = ?,
                            final_decision = ?,
                            decision_date = ?,
                            decision_notes = ?,
                            hr_notes = ?,
                            decision_made_by = ?
                        WHERE id = ?
                    ");
                    $update->execute([
                        $status,
                        $final_decision,
                        $decision_date,
                        $decision_notes,
                        $hr_comments,
                        $current_user_id,
                        $probation_id
                    ]);
                    
                    // Update employee status based on decision
                    if ($final_decision == 'confirm' || $final_decision == 'promote') {
                        // Confirm as regular employee
                        $emp_update = $pdo->prepare("
                            UPDATE new_hires 
                            SET employment_status = 'regular',
                                status = 'active'
                            WHERE id = ?
                        ");
                        $emp_update->execute([$employee_id]);
                        
                        // If promotion, update position
                        if ($final_decision == 'promote' && !empty($new_position)) {
                            $promote_update = $pdo->prepare("
                                UPDATE new_hires 
                                SET position = ?,
                                    department = COALESCE(?, department)
                                WHERE id = ?
                            ");
                            $promote_update->execute([$new_position, $new_department ?: null, $employee_id]);
                            
                            simpleLog($pdo, $current_user_id, 'promote_employee', 
                                "Promoted employee ID: $employee_id to $new_position");
                        }
                        
                    } elseif ($final_decision == 'extend') {
                        // Extend probation
                        $new_end_date = date('Y-m-d', strtotime("+$extension_days days", strtotime($decision_date)));
                        
                        $extend_update = $pdo->prepare("
                            UPDATE probation_records 
                            SET probation_end_date = ?,
                                extended_days = ?,
                                extension_reason = ?
                            WHERE id = ?
                        ");
                        $extend_update->execute([$new_end_date, $extension_days, $improvement_plan, $probation_id]);
                        
                        // Update new_hires probation end date
                        $nh_update = $pdo->prepare("
                            UPDATE new_hires 
                            SET probation_end_date = ?
                            WHERE id = ?
                        ");
                        $nh_update->execute([$new_end_date, $employee_id]);
                        
                    } elseif ($final_decision == 'terminate') {
                        // Terminate employment
                        $term_update = $pdo->prepare("
                            UPDATE new_hires 
                            SET status = 'terminated',
                                employment_status = 'terminated'
                            WHERE id = ?
                        ");
                        $term_update->execute([$employee_id]);
                    }
                    
                    $pdo->commit();
                    
                    simpleLog($pdo, $current_user_id, 'make_decision', 
                        "Made $final_decision decision for employee ID: $employee_id");
                    
                    $message = "Decision recorded successfully!";
                    
                    // Redirect to refresh the page and show updated data
                    header("Location: ?page=hr&subpage=confirmation-decisions&success=1");
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error recording decision: " . $e->getMessage();
                    error_log("Decision error: " . $e->getMessage());
                }
                break;
                
            case 'batch_decision':
                // Process batch decisions
                $employee_ids = $_POST['employee_ids'] ?? [];
                $batch_decision = $_POST['batch_decision'] ?? '';
                $batch_notes = $_POST['batch_notes'] ?? '';
                
                if (!empty($employee_ids) && $batch_decision) {
                    $success_count = 0;
                    $fail_count = 0;
                    
                    foreach ($employee_ids as $emp_id) {
                        try {
                            // Get probation_id
                            $get = $pdo->prepare("SELECT id FROM probation_records WHERE new_hire_id = ?");
                            $get->execute([$emp_id]);
                            $prob = $get->fetch();
                            
                            if ($prob) {
                                $status = ($batch_decision == 'confirm') ? 'completed' : 
                                         (($batch_decision == 'extend') ? 'extended' : 'terminated');
                                
                                $update = $pdo->prepare("
                                    UPDATE probation_records 
                                    SET status = ?,
                                        final_decision = ?,
                                        decision_date = ?,
                                        decision_notes = ?,
                                        decision_made_by = ?
                                    WHERE id = ?
                                ");
                                $update->execute([
                                    $status,
                                    $batch_decision,
                                    date('Y-m-d'),
                                    $batch_notes,
                                    $current_user_id,
                                    $prob['id']
                                ]);
                                
                                if ($batch_decision == 'confirm') {
                                    $emp_update = $pdo->prepare("
                                        UPDATE new_hires 
                                        SET employment_status = 'regular'
                                        WHERE id = ?
                                    ");
                                    $emp_update->execute([$emp_id]);
                                } elseif ($batch_decision == 'terminate') {
                                    $emp_update = $pdo->prepare("
                                        UPDATE new_hires 
                                        SET status = 'terminated'
                                        WHERE id = ?
                                    ");
                                    $emp_update->execute([$emp_id]);
                                }
                                
                                $success_count++;
                            }
                        } catch (Exception $e) {
                            $fail_count++;
                        }
                    }
                    
                    simpleLog($pdo, $current_user_id, 'batch_decision', 
                        "Batch $batch_decision: $success_count succeeded, $fail_count failed");
                    
                    $message = "Batch decision completed: $success_count succeeded, $fail_count failed";
                } else {
                    $error = "Please select employees and a decision type";
                }
                break;
                
            case 'generate_report':
                // Generate decision report
                $report_type = $_POST['report_type'] ?? 'summary';
                $date_from = $_POST['date_from'] ?? date('Y-m-01');
                $date_to = $_POST['date_to'] ?? date('Y-m-d');
                $department = $_POST['report_department'] ?? '';
                
                simpleLog($pdo, $current_user_id, 'generate_report', 
                    "Generated $report_type decision report for $date_from to $date_to");
                
                $message = "Report generated successfully!";
                break;
        }
    }
}

// Check for success parameter in URL (after redirect)
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Decision recorded successfully!";
}

// Get all employees who have completed or are ending probation
$query = "
    SELECT 
        nh.id as employee_id,
        nh.employee_id as employee_code,
        nh.position,
        nh.department,
        nh.start_date,
        nh.probation_end_date,
        nh.employment_status,
        nh.status as employee_status,
        nh.hire_date,
        nh.supervisor_id,
        ja.id as applicant_id,
        ja.first_name,
        ja.last_name,
        ja.email,
        ja.phone,
        ja.photo_path,
        ja.application_number,
        jp.id as job_posting_id,
        jp.title as job_title,
        jp.job_code,
        CONCAT(ja.first_name, ' ', ja.last_name) as full_name,
        UPPER(LEFT(ja.first_name, 1)) as first_initial,
        UPPER(LEFT(ja.last_name, 1)) as last_initial,
        
        -- Probation record
        pr.id as probation_id,
        pr.probation_start_date,
        pr.probation_end_date as record_end_date,
        pr.status as probation_status,
        pr.final_decision,
        pr.decision_date,
        pr.decision_notes,
        pr.extended_days,
        pr.extension_reason,
        
        -- Statistics
        (SELECT COUNT(*) FROM probation_incidents WHERE probation_record_id = pr.id) as incident_count,
        (SELECT COUNT(*) FROM probation_incidents WHERE probation_record_id = pr.id AND severity IN ('major', 'critical')) as serious_incidents,
        (SELECT COUNT(*) FROM probation_reviews WHERE probation_record_id = pr.id) as review_count,
        (SELECT AVG(percentage_score) FROM probation_reviews WHERE probation_record_id = pr.id) as avg_score,
        (SELECT MAX(percentage_score) FROM probation_reviews WHERE probation_record_id = pr.id) as best_score,
        
        -- Attendance summary (from attendance table)
        (SELECT COUNT(*) FROM attendance WHERE employee_id = nh.id AND status = 'absent' AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) as absent_days,
        (SELECT COUNT(*) FROM attendance WHERE employee_id = nh.id AND status = 'late' AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) as late_days,
        
        -- Warnings
        (SELECT COUNT(*) FROM probation_incidents WHERE probation_record_id = pr.id AND incident_type = 'warning') as warning_count,
        
        -- Supervisor info
        u.full_name as supervisor_name,
        u.id as supervisor_user_id,
        
        -- Decision status
        CASE 
            WHEN pr.final_decision IS NOT NULL AND pr.final_decision != 'pending' THEN 'decided'
            WHEN pr.probation_end_date < CURDATE() THEN 'overdue'
            WHEN pr.probation_end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'ending_soon'
            ELSE 'ongoing'
        END as decision_status,
        
        -- Days since probation ended
        DATEDIFF(CURDATE(), pr.probation_end_date) as days_since_end,
        
        -- Has decision flag
        CASE WHEN pr.final_decision IS NOT NULL AND pr.final_decision != 'pending' THEN 1 ELSE 0 END as has_decision
        
    FROM new_hires nh
    INNER JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
    LEFT JOIN users u ON nh.supervisor_id = u.id
    INNER JOIN probation_records pr ON nh.id = pr.new_hire_id
    WHERE nh.status IN ('onboarding', 'active')
        AND (pr.status = 'ongoing' OR pr.status = 'extended')
";

$params = [];

// Status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'pending') {
        $query .= " AND (pr.final_decision IS NULL OR pr.final_decision = 'pending')";
    } elseif ($status_filter === 'decided') {
        $query .= " AND pr.final_decision IS NOT NULL AND pr.final_decision != 'pending'";
    } elseif ($status_filter === 'overdue') {
        $query .= " AND pr.probation_end_date < CURDATE() AND (pr.final_decision IS NULL OR pr.final_decision = 'pending')";
    } elseif ($status_filter === 'ending_soon') {
        $query .= " AND pr.probation_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    }
}

// Decision filter
if ($decision_filter !== 'all') {
    if ($decision_filter === 'pending') {
        $query .= " AND (pr.final_decision IS NULL OR pr.final_decision = 'pending')";
    } else {
        $query .= " AND pr.final_decision = ?";
        $params[] = $decision_filter;
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

// Date filters (probation end date)
if (!empty($date_from)) {
    $query .= " AND pr.probation_end_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND pr.probation_end_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY 
    CASE 
        WHEN pr.probation_end_date < CURDATE() AND (pr.final_decision IS NULL OR pr.final_decision = 'pending') THEN 0
        WHEN pr.probation_end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND (pr.final_decision IS NULL OR pr.final_decision = 'pending') THEN 1
        ELSE 2
    END,
    pr.probation_end_date ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pending_decisions = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => count($pending_decisions),
    'pending' => 0,
    'decided' => 0,
    'overdue' => 0,
    'ending_soon' => 0,
    'confirm' => 0,
    'extend' => 0,
    'terminate' => 0,
    'promote' => 0
];

foreach ($pending_decisions as $emp) {
    if ($emp['has_decision']) {
        $stats['decided']++;
        $decision = $emp['final_decision'];
        if (isset($stats[$decision])) {
            $stats[$decision]++;
        }
    } else {
        $stats['pending']++;
    }
    
    if ($emp['decision_status'] == 'overdue') {
        $stats['overdue']++;
    } elseif ($emp['decision_status'] == 'ending_soon') {
        $stats['ending_soon']++;
    }
}

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department FROM new_hires WHERE department IS NOT NULL ORDER BY department");
$departments = $stmt->fetchAll();

// Decision configuration
$decision_config = [
    'confirm' => [
        'label' => 'Confirm Regular',
        'icon' => 'fas fa-check-circle',
        'color' => '#27ae60',
        'bg' => '#27ae6020',
        'text' => '#27ae60',
        'description' => 'Confirm as regular employee'
    ],
    'extend' => [
        'label' => 'Extend Probation',
        'icon' => 'fas fa-hourglass-half',
        'color' => '#f39c12',
        'bg' => '#f39c1220',
        'text' => '#f39c12',
        'description' => 'Extend probation period'
    ],
    'terminate' => [
        'label' => 'Terminate',
        'icon' => 'fas fa-ban',
        'color' => '#e74c3c',
        'bg' => '#e74c3c20',
        'text' => '#e74c3c',
        'description' => 'Terminate employment'
    ],
    'promote' => [
        'label' => 'Promote & Confirm',
        'icon' => 'fas fa-arrow-up',
        'color' => '#9b59b6',
        'bg' => '#9b59b620',
        'text' => '#9b59b6',
        'description' => 'Promote and confirm as regular'
    ],
    'pending' => [
        'label' => 'Pending',
        'icon' => 'fas fa-clock',
        'color' => '#7f8c8d',
        'bg' => '#7f8c8d20',
        'text' => '#7f8c8d',
        'description' => 'Awaiting decision'
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
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
.stat-card-modern.danger { border-left-color: var(--danger); }
.stat-card-modern.purple { border-left-color: var(--purple); }
.stat-card-modern.orange { border-left-color: var(--orange); }
.stat-card-modern.gray { border-left-color: var(--gray); }

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
.stat-icon-modern.danger { background: var(--danger)20; color: var(--danger); }
.stat-icon-modern.purple { background: var(--purple)20; color: var(--purple); }
.stat-icon-modern.orange { background: var(--orange)20; color: var(--orange); }
.stat-icon-modern.gray { background: var(--gray)20; color: var(--gray); }

.stat-content-modern {
    flex: 1;
}

.stat-label-modern {
    font-size: 12px;
    color: var(--gray);
    margin-bottom: 5px;
    font-weight: 500;
}

.stat-value-modern {
    font-size: 24px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

/* Alert Cards */
.alert-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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

.alert-card.warning { border-left-color: var(--warning); }
.alert-card.danger { border-left-color: var(--danger); }
.alert-card.info { border-left-color: var(--info); }
.alert-card.success { border-left-color: var(--success); }

.alert-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.alert-icon.warning { background: var(--warning)20; color: var(--warning); }
.alert-icon.danger { background: var(--danger)20; color: var(--danger); }
.alert-icon.info { background: var(--info)20; color: var(--info); }
.alert-icon.success { background: var(--success)20; color: var(--success); }

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
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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

.btn-success {
    background: var(--success);
    color: white;
}

.btn-warning {
    background: var(--warning);
    color: white;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-info {
    background: var(--info);
    color: white;
}

.btn-purple {
    background: var(--purple);
    color: white;
}

/* Decision Cards Grid */
.decision-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.decision-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid var(--border);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.decision-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
    border-color: var(--primary);
}

.decision-card.pending {
    border-left: 5px solid var(--gray);
}

.decision-card.overdue {
    border-left: 5px solid var(--danger);
}

.decision-card.ending-soon {
    border-left: 5px solid var(--warning);
}

.decision-card.decided {
    opacity: 0.9;
}

.urgent-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--danger);
    color: white;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 3px;
}

.warning-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--warning);
    color: white;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.card-avatar {
    width: 55px;
    height: 55px;
    border-radius: 15px;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 5px 15px var(--primary-transparent);
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 20px;
}

.card-info {
    flex: 1;
}

.card-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 4px;
}

.card-position {
    font-size: 13px;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 4px;
}

.card-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: var(--light-gray);
    color: var(--gray);
}

.score-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.score-excellent { background: #27ae6020; color: #27ae60; }
.score-good { background: #2ecc7120; color: #2ecc71; }
.score-average { background: #f39c1220; color: #f39c12; }
.score-poor { background: #e74c3c20; color: #e74c3c; }

.card-body {
    margin-bottom: 15px;
}

.probation-info {
    background: var(--light-gray);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.probation-icon {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    background: var(--primary-transparent);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.probation-details {
    flex: 1;
}

.probation-label {
    font-size: 11px;
    color: var(--gray);
    margin-bottom: 2px;
}

.probation-value {
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
}

.card-meta-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-top: 12px;
}

.meta-item {
    background: var(--light-gray);
    border-radius: 10px;
    padding: 8px;
    text-align: center;
}

.meta-label {
    font-size: 9px;
    color: var(--gray);
    margin-bottom: 3px;
    text-transform: uppercase;
}

.meta-value {
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
}

.meta-value.warning {
    color: var(--warning);
}

.meta-value.danger {
    color: var(--danger);
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.decision-badge {
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

/* Table View */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow-x: auto;
}

.decision-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.decision-table th {
    text-align: left;
    padding: 12px 10px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
}

.decision-table td {
    padding: 12px 10px;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
}

.decision-table tr {
    transition: all 0.3s;
    cursor: pointer;
}

.decision-table tr:hover {
    background: var(--light-gray);
}

.decision-table tr.pending-row {
    background: #f8f9fa;
}

.decision-table tr.overdue-row {
    background: #fff3f3;
}

.decision-table tr.ending-soon-row {
    background: #fff9e6;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-avatar {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    object-fit: cover;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 10px 55px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    background: var(--light-gray);
    color: var(--dark);
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.action-btn.primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
}

.action-btn.success {
    background: var(--success);
    color: white;
}

.action-btn.warning {
    background: var(--warning);
    color: white;
}

.action-btn.danger {
    background: var(--danger);
    color: white;
}

.action-btn.purple {
    background: var(--purple);
    color: white;
}

.action-btn.info {
    background: var(--info);
    color: white;
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
    max-width: 700px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.modal-lg {
    max-width: 800px;
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

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
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
    gap: 15px;
    margin-bottom: 15px;
}

.readonly-field {
    background: var(--light-gray);
    padding: 12px;
    border-radius: 10px;
    border: 1px solid var(--border);
    font-size: 14px;
}

.readonly-field strong {
    color: var(--dark);
}

.readonly-field span {
    color: var(--gray);
    margin-left: 10px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin: 15px 0;
}

.stat-box {
    background: white;
    border-radius: 10px;
    padding: 12px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.stat-box .value {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark);
}

.stat-box .label {
    font-size: 10px;
    color: var(--gray);
    margin-top: 3px;
}

.decision-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin: 15px 0;
}

.decision-option {
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.decision-option:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px var(--primary-transparent);
}

.decision-option.selected {
    border-color: var(--primary);
    background: var(--primary-transparent);
}

.decision-option .title {
    font-weight: 600;
    font-size: 15px;
    margin-bottom: 5px;
}

.decision-option .desc {
    font-size: 11px;
    color: var(--gray);
}

.modal-footer {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 25px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

/* Batch Selection */
.batch-selection {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 15px;
}

.employee-checkbox {
    display: flex;
    align-items: center;
    padding: 8px;
    border-bottom: 1px solid var(--border);
}

.employee-checkbox:last-child {
    border-bottom: none;
}

.employee-checkbox input[type="checkbox"] {
    width: auto;
    margin-right: 10px;
}

.employee-checkbox label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    font-weight: normal;
    font-size: 13px;
}

.checkbox-avatar {
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
    .stats-container {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .stats-container {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .alert-section {
        grid-template-columns: 1fr;
    }
    
    .decision-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header-unique {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .view-toggle {
        width: 100%;
        justify-content: space-between;
    }
    
    .view-option {
        flex: 1;
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .decision-options {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
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
        <i class="fas fa-gavel"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div class="view-toggle">
        <a href="?page=hr&subpage=confirmation-decisions&view=dashboard<?php 
            echo !empty($status_filter) ? '&status=' . $status_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($department_filter) ? '&department=' . $department_filter : ''; 
        ?>" class="view-option <?php echo $view_mode == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a href="?page=hr&subpage=confirmation-decisions&view=list<?php 
            echo !empty($status_filter) ? '&status=' . $status_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($department_filter) ? '&department=' . $department_filter : ''; 
        ?>" class="view-option <?php echo $view_mode == 'list' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i> List View
        </a>
        <a href="?page=hr&subpage=confirmation-decisions&view=summary<?php 
        ?>" class="view-option <?php echo $view_mode == 'summary' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> Summary
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-container">
    <div class="stat-card-modern gray">
        <div class="stat-icon-modern gray">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Pending Decisions</span>
            <span class="stat-value-modern"><?php echo $stats['pending']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern success">
        <div class="stat-icon-modern success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Confirmed</span>
            <span class="stat-value-modern"><?php echo $stats['confirm']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern warning">
        <div class="stat-icon-modern warning">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Extended</span>
            <span class="stat-value-modern"><?php echo $stats['extend']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern danger">
        <div class="stat-icon-modern danger">
            <i class="fas fa-ban"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Terminated</span>
            <span class="stat-value-modern"><?php echo $stats['terminate']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern purple">
        <div class="stat-icon-modern purple">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Promoted</span>
            <span class="stat-value-modern"><?php echo $stats['promote']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern danger">
        <div class="stat-icon-modern danger">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Overdue</span>
            <span class="stat-value-modern"><?php echo $stats['overdue']; ?></span>
        </div>
    </div>
</div>

<!-- Alert Section -->
<div class="alert-section">
    <?php if ($stats['pending'] > 0): ?>
    <div class="alert-card warning">
        <div class="alert-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Pending Decisions</div>
            <div class="alert-value"><?php echo $stats['pending']; ?> employees</div>
            <a href="?page=hr&subpage=confirmation-decisions&view=<?php echo $view_mode; ?>&status=pending" class="alert-link">Review Now →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['overdue'] > 0): ?>
    <div class="alert-card danger">
        <div class="alert-icon danger">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Overdue Decisions</div>
            <div class="alert-value"><?php echo $stats['overdue']; ?> employees</div>
            <a href="?page=hr&subpage=confirmation-decisions&view=<?php echo $view_mode; ?>&status=overdue" class="alert-link">Take Action →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['ending_soon'] > 0): ?>
    <div class="alert-card info">
        <div class="alert-icon info">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Ending This Week</div>
            <div class="alert-value"><?php echo $stats['ending_soon']; ?> employees</div>
            <a href="?page=hr&subpage=confirmation-decisions&view=<?php echo $view_mode; ?>&status=ending_soon" class="alert-link">View →</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Decisions
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="hr">
        <input type="hidden" name="subpage" value="confirmation-decisions">
        <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="decided" <?php echo $status_filter == 'decided' ? 'selected' : ''; ?>>Decided</option>
                    <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    <option value="ending_soon" <?php echo $status_filter == 'ending_soon' ? 'selected' : ''; ?>>Ending Soon</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Decision</label>
                <select name="decision">
                    <option value="all" <?php echo $decision_filter == 'all' ? 'selected' : ''; ?>>All Decisions</option>
                    <option value="pending" <?php echo $decision_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirm" <?php echo $decision_filter == 'confirm' ? 'selected' : ''; ?>>Confirm</option>
                    <option value="extend" <?php echo $decision_filter == 'extend' ? 'selected' : ''; ?>>Extend</option>
                    <option value="terminate" <?php echo $decision_filter == 'terminate' ? 'selected' : ''; ?>>Terminate</option>
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
                <label>Date From</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="filter-item">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name or Employee ID" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=hr&subpage=confirmation-decisions&view=<?php echo $view_mode; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Apply
            </button>
        </div>
    </form>
</div>

<!-- View Content -->
<?php if ($view_mode == 'dashboard'): ?>
    <!-- Dashboard Grid View -->
    <div class="decision-grid">
        <?php foreach ($pending_decisions as $emp): 
            $fullName = $emp['full_name'];
            $initials = $emp['first_initial'] . $emp['last_initial'];
            $photoPath = getEmployeePhoto($emp);
            
            $avg_score = $emp['avg_score'] ? round($emp['avg_score']) : 'N/A';
            $score_class = $avg_score != 'N/A' ? getScoreClass($avg_score) : '';
            
            $decision_badge = getDecisionBadge($emp['final_decision'] ?? 'pending');
            
            $card_class = '';
            if (!$emp['has_decision']) {
                if ($emp['decision_status'] == 'overdue') {
                    $card_class = 'overdue';
                } elseif ($emp['decision_status'] == 'ending_soon') {
                    $card_class = 'ending-soon';
                } else {
                    $card_class = 'pending';
                }
            }
        ?>
        <div class="decision-card <?php echo $card_class; ?>" onclick="openDecisionModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
            
            <?php if ($emp['decision_status'] == 'overdue'): ?>
            <div class="urgent-badge">
                <i class="fas fa-exclamation-circle"></i> OVERDUE
            </div>
            <?php elseif ($emp['decision_status'] == 'ending_soon'): ?>
            <div class="warning-badge">
                <i class="fas fa-clock"></i> Ending Soon
            </div>
            <?php endif; ?>
            
            <div class="card-header">
                <?php if ($photoPath): ?>
                    <img src="<?php echo $photoPath; ?>" 
                         alt="<?php echo htmlspecialchars($fullName); ?>"
                         class="card-avatar"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                         loading="lazy">
                    <div class="card-avatar" style="display: none;"><?php echo $initials; ?></div>
                <?php else: ?>
                    <div class="card-avatar">
                        <?php echo $initials; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card-info">
                    <div class="card-name"><?php echo htmlspecialchars($fullName); ?></div>
                    <div class="card-position">
                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($emp['position'] ?? $emp['job_title']); ?>
                    </div>
                    <div>
                        <span class="card-badge">
                            <i class="fas fa-hashtag"></i> <?php echo $emp['employee_code'] ?: 'No ID'; ?>
                        </span>
                        <span class="card-badge" style="margin-left: 5px;">
                            <i class="fas fa-building"></i> <?php echo ucfirst($emp['department']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="probation-info">
                    <div class="probation-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="probation-details">
                        <div class="probation-label">Probation Period</div>
                        <div class="probation-value">
                            <?php echo date('M d, Y', strtotime($emp['probation_start_date'])); ?> - 
                            <?php echo date('M d, Y', strtotime($emp['probation_end_date'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="value <?php echo $score_class; ?>"><?php echo $avg_score; ?>%</div>
                        <div class="label">Avg Score</div>
                    </div>
                    <div class="stat-box">
                        <div class="value"><?php echo $emp['incident_count'] ?? 0; ?></div>
                        <div class="label">Incidents</div>
                    </div>
                    <div class="stat-box">
                        <div class="value <?php echo $emp['warning_count'] > 0 ? 'danger' : ''; ?>">
                            <?php echo $emp['warning_count'] ?? 0; ?>
                        </div>
                        <div class="label">Warnings</div>
                    </div>
                    <div class="stat-box">
                        <div class="value <?php echo $emp['absent_days'] > 5 ? 'warning' : ''; ?>">
                            <?php echo $emp['absent_days'] ?? 0; ?>
                        </div>
                        <div class="label">Absences</div>
                    </div>
                </div>
                
                <?php if ($emp['has_decision']): ?>
                <div style="margin-top: 10px; text-align: center;">
                    <span class="decision-badge" style="background: <?php echo $decision_badge['bg']; ?>; color: <?php echo $decision_badge['color']; ?>;">
                        <i class="<?php echo $decision_config[$emp['final_decision']]['icon'] ?? 'fas fa-check'; ?>"></i>
                        Decided: <?php echo $decision_badge['label']; ?>
                    </span>
                    <?php if ($emp['decision_date']): ?>
                    <div style="font-size: 10px; color: var(--gray); margin-top: 3px;">
                        on <?php echo date('M d, Y', strtotime($emp['decision_date'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card-footer">
                <span class="status-badge" style="background: <?php echo $decision_badge['bg']; ?>; color: <?php echo $decision_badge['color']; ?>;">
                    <i class="<?php echo $decision_config[$emp['final_decision'] ?? 'pending']['icon']; ?>"></i>
                    <?php echo $decision_badge['label']; ?>
                </span>
                
                <?php if (!$emp['has_decision']): ?>
                <span class="decision-badge" style="background: var(--primary-transparent); color: var(--primary);">
                    <i class="fas fa-gavel"></i> Click to Decide
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($pending_decisions)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 20px;">
            <i class="fas fa-check-circle" style="font-size: 48px; color: var(--gray); opacity: 0.3;"></i>
            <h3 style="margin-top: 15px; color: var(--dark);">No Pending Decisions</h3>
            <p style="color: var(--gray);">All probation decisions have been processed.</p>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($view_mode == 'list'): ?>
    <!-- List View -->
    <div class="table-container">
        <table class="decision-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Position/Dept</th>
                    <th>Probation End</th>
                    <th>Score</th>
                    <th>Incidents</th>
                    <th>Warnings</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_decisions as $emp): 
                    $fullName = $emp['full_name'];
                    $initials = $emp['first_initial'] . $emp['last_initial'];
                    $photoPath = getEmployeePhoto($emp);
                    
                    $avg_score = $emp['avg_score'] ? round($emp['avg_score']) : 'N/A';
                    $score_class = $avg_score != 'N/A' ? getScoreClass($avg_score) : '';
                    
                    $decision_badge = getDecisionBadge($emp['final_decision'] ?? 'pending');
                    
                    $row_class = '';
                    if (!$emp['has_decision']) {
                        if ($emp['decision_status'] == 'overdue') {
                            $row_class = 'overdue-row';
                        } elseif ($emp['decision_status'] == 'ending_soon') {
                            $row_class = 'ending-soon-row';
                        } else {
                            $row_class = 'pending-row';
                        }
                    }
                ?>
                <tr class="<?php echo $row_class; ?>" onclick="openDecisionModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
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
                                <div style="font-size: 11px; color: var(--gray);"><?php echo $emp['employee_code'] ?: 'No ID'; ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?php echo htmlspecialchars($emp['position'] ?? $emp['job_title']); ?></div>
                        <div style="font-size: 11px; color: var(--gray);"><?php echo ucfirst($emp['department']); ?></div>
                    </td>
                    <td>
                        <?php echo date('M d, Y', strtotime($emp['probation_end_date'])); ?>
                        <?php if ($emp['days_since_end'] > 0 && !$emp['has_decision']): ?>
                        <br><span style="font-size: 10px; color: var(--danger);"><?php echo $emp['days_since_end']; ?> days overdue</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="score-badge <?php echo $score_class; ?>">
                            <?php echo $avg_score; ?>%
                        </span>
                    </td>
                    <td>
                        <span class="<?php echo $emp['incident_count'] > 3 ? 'danger' : ''; ?>">
                            <?php echo $emp['incident_count'] ?? 0; ?>
                        </span>
                        <?php if ($emp['serious_incidents'] > 0): ?>
                        <br><span style="font-size: 10px; color: var(--danger);"><?php echo $emp['serious_incidents']; ?> serious</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="<?php echo $emp['warning_count'] > 0 ? 'danger' : ''; ?>">
                            <?php echo $emp['warning_count'] ?? 0; ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge" style="background: <?php echo $decision_badge['bg']; ?>; color: <?php echo $decision_badge['color']; ?>;">
                            <i class="<?php echo $decision_config[$emp['final_decision'] ?? 'pending']['icon']; ?>"></i>
                            <?php echo $decision_badge['label']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!$emp['has_decision']): ?>
                        <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); openDecisionModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
                            <i class="fas fa-gavel"></i> Decide
                        </button>
                        <?php else: ?>
                        <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); viewDecision(<?php echo $emp['probation_id']; ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($pending_decisions)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <i class="fas fa-check-circle" style="font-size: 32px; color: var(--gray); opacity: 0.3;"></i>
                        <p style="margin-top: 10px;">No decisions found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($view_mode == 'summary'): ?>
    <!-- Summary View -->
    <div class="filter-section">
        <div class="filter-title">
            <i class="fas fa-chart-bar"></i> Decision Summary
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <!-- Decision Distribution -->
            <div style="background: var(--light-gray); border-radius: 15px; padding: 20px;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px;">Decision Distribution</h3>
                <div style="height: 200px; display: flex; align-items: flex-end; gap: 15px;">
                    <?php 
                    $decisions = ['confirm', 'extend', 'terminate', 'promote', 'pending'];
                    $max_count = max($stats['confirm'], $stats['extend'], $stats['terminate'], $stats['promote'], $stats['pending']) ?: 1;
                    foreach ($decisions as $dec):
                        $count = $stats[$dec] ?? 0;
                        $height = ($count / $max_count) * 180;
                        if ($height < 30) $height = 30;
                        $color = $decision_config[$dec]['color'] ?? '#7f8c8d';
                    ?>
                    <div style="flex: 1; text-align: center;">
                        <div style="height: <?php echo $height; ?>px; background: <?php echo $color; ?>; border-radius: 8px 8px 0 0;"></div>
                        <div style="margin-top: 5px; font-size: 12px; font-weight: 600;"><?php echo $count; ?></div>
                        <div style="font-size: 11px; color: var(--gray);"><?php echo ucfirst($dec); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Department Summary -->
            <div style="background: var(--light-gray); border-radius: 15px; padding: 20px;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px;">By Department</h3>
                <?php
                $dept_stats = [];
                foreach ($pending_decisions as $emp) {
                    $dept = $emp['department'] ?? 'other';
                    if (!isset($dept_stats[$dept])) {
                        $dept_stats[$dept] = ['total' => 0, 'pending' => 0];
                    }
                    $dept_stats[$dept]['total']++;
                    if (!$emp['has_decision']) {
                        $dept_stats[$dept]['pending']++;
                    }
                }
                ?>
                <table style="width: 100%;">
                    <?php foreach ($dept_stats as $dept => $data): ?>
                    <tr>
                        <td style="padding: 8px 0;"><?php echo ucfirst($dept); ?></td>
                        <td style="padding: 8px 0; text-align: center;"><?php echo $data['total']; ?> total</td>
                        <td style="padding: 8px 0; text-align: right;">
                            <span style="background: var(--warning); color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;">
                                <?php echo $data['pending']; ?> pending
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <!-- Score Distribution -->
            <div style="background: var(--light-gray); border-radius: 15px; padding: 20px; grid-column: 1/-1;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px;">Score Distribution</h3>
                <?php
                $score_ranges = [
                    'excellent' => ['min' => 85, 'max' => 100, 'count' => 0, 'color' => '#27ae60'],
                    'good' => ['min' => 75, 'max' => 84, 'count' => 0, 'color' => '#2ecc71'],
                    'average' => ['min' => 60, 'max' => 74, 'count' => 0, 'color' => '#f39c12'],
                    'poor' => ['min' => 0, 'max' => 59, 'count' => 0, 'color' => '#e74c3c']
                ];
                
                foreach ($pending_decisions as $emp) {
                    $score = $emp['avg_score'];
                    if ($score) {
                        if ($score >= 85) $score_ranges['excellent']['count']++;
                        elseif ($score >= 75) $score_ranges['good']['count']++;
                        elseif ($score >= 60) $score_ranges['average']['count']++;
                        else $score_ranges['poor']['count']++;
                    }
                }
                
                $max_score_count = max(array_column($score_ranges, 'count')) ?: 1;
                ?>
                
                <div style="display: flex; align-items: flex-end; gap: 20px; height: 150px; margin-bottom: 10px;">
                    <?php foreach ($score_ranges as $range): ?>
                    <div style="flex: 1; text-align: center;">
                        <div style="height: <?php echo ($range['count'] / $max_score_count) * 120; ?>px; 
                                    background: <?php echo $range['color']; ?>; 
                                    border-radius: 8px 8px 0 0;"></div>
                        <div style="margin-top: 5px; font-size: 14px; font-weight: 600;"><?php echo $range['count']; ?></div>
                        <div style="font-size: 11px; color: var(--gray);"><?php echo $range['min']; ?>-<?php echo $range['max']; ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="action-buttons">
    <button class="action-btn primary" onclick="openBatchDecisionModal()">
        <i class="fas fa-layer-group"></i> Batch Decision
    </button>
    <button class="action-btn success" onclick="openReportModal()">
        <i class="fas fa-file-pdf"></i> Generate Report
    </button>
    <button class="action-btn info" onclick="openReminderModal()">
        <i class="fas fa-bell"></i> Send Reminders
    </button>
    <button class="action-btn" onclick="exportToExcel()">
        <i class="fas fa-file-excel"></i> Export
    </button>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Decision Modal -->
<div id="decisionModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-gavel"></i> Make Confirmation Decision</h3>
            <span class="modal-close" onclick="closeDecisionModal()">&times;</span>
        </div>
        
        <form method="POST" action="" id="decisionForm" onsubmit="return validateDecisionForm()">
            <input type="hidden" name="action" value="make_decision">
            <input type="hidden" name="employee_id" id="decision_employee_id" value="">
            <input type="hidden" name="probation_id" id="decision_probation_id" value="">
            <input type="hidden" name="final_decision" id="selected_decision" value="">
            
            <!-- Employee Information (Read-only) -->
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px;">Employee Information</h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <div class="readonly-field">
                        <strong>Name:</strong> <span id="emp_name"></span>
                    </div>
                    <div class="readonly-field">
                        <strong>ID:</strong> <span id="emp_id"></span>
                    </div>
                    <div class="readonly-field">
                        <strong>Position:</strong> <span id="emp_position"></span>
                    </div>
                    <div class="readonly-field">
                        <strong>Department:</strong> <span id="emp_dept"></span>
                    </div>
                    <div class="readonly-field">
                        <strong>Supervisor:</strong> <span id="emp_supervisor"></span>
                    </div>
                    <div class="readonly-field">
                        <strong>Start Date:</strong> <span id="emp_start_date"></span>
                    </div>
                    <div class="readonly-field">
                        <strong>Probation End:</strong> <span id="emp_probation_end"></span>
                    </div>
                    <div class="readonly-field">
                        <strong>Final Score:</strong> <span id="emp_score"></span>
                    </div>
                </div>
                
                <!-- Stats Summary -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 10px;">
                    <div class="stat-box">
                        <div class="value" id="stat_incidents">0</div>
                        <div class="label">Incidents</div>
                    </div>
                    <div class="stat-box">
                        <div class="value" id="stat_warnings">0</div>
                        <div class="label">Warnings</div>
                    </div>
                    <div class="stat-box">
                        <div class="value" id="stat_absences">0</div>
                        <div class="label">Absences</div>
                    </div>
                    <div class="stat-box">
                        <div class="value" id="stat_late">0</div>
                        <div class="label">Late Days</div>
                    </div>
                </div>
            </div>
            
            <!-- Decision Selection -->
            <div class="form-group">
                <label>Select Decision <span style="color: var(--danger);">*</span></label>
                <div class="decision-options" id="decision_options">
                    <div class="decision-option" onclick="selectDecision('confirm')" id="opt_confirm">
                        <div class="title"><i class="fas fa-check-circle" style="color: #27ae60;"></i> Confirm Employment</div>
                        <div class="desc">Convert to regular employee, activate benefits</div>
                    </div>
                    <div class="decision-option" onclick="selectDecision('extend')" id="opt_extend">
                        <div class="title"><i class="fas fa-hourglass-half" style="color: #f39c12;"></i> Extend Probation</div>
                        <div class="desc">Extend probation period, require improvement plan</div>
                    </div>
                    <div class="decision-option" onclick="selectDecision('terminate')" id="opt_terminate">
                        <div class="title"><i class="fas fa-ban" style="color: #e74c3c;"></i> Terminate Employment</div>
                        <div class="desc">End employment, initiate exit process</div>
                    </div>
                    <div class="decision-option" onclick="selectDecision('promote')" id="opt_promote">
                        <div class="title"><i class="fas fa-arrow-up" style="color: #9b59b6;"></i> Promote & Confirm</div>
                        <div class="desc">Promote to new position and confirm as regular</div>
                    </div>
                </div>
            </div>
            
            <!-- Decision Date -->
            <div class="form-row">
                <div class="form-group">
                    <label>Decision Date</label>
                    <input type="date" name="decision_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <!-- Extension Fields (shown when extend selected) -->
            <div id="extension_fields" style="display: none;">
                <div class="form-group">
                    <label>Extension Period</label>
                    <select name="extension_days">
                        <option value="30">30 days (1 month)</option>
                        <option value="45">45 days</option>
                        <option value="60">60 days (2 months)</option>
                        <option value="90">90 days (3 months)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Improvement Plan Required</label>
                    <textarea name="improvement_plan" rows="3" placeholder="Outline specific areas for improvement..."></textarea>
                </div>
            </div>
            
            <!-- Promotion Fields (shown when promote selected) -->
            <div id="promotion_fields" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <label>New Position</label>
                        <input type="text" name="new_position" placeholder="e.g., Senior Driver">
                    </div>
                    <div class="form-group">
                        <label>New Department</label>
                        <select name="new_department">
                            <option value="">Same Department</option>
                            <option value="driver">Driver</option>
                            <option value="warehouse">Warehouse</option>
                            <option value="logistics">Logistics</option>
                            <option value="admin">Admin</option>
                            <option value="management">Management</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>New Salary</label>
                        <input type="number" name="new_salary" placeholder="0.00" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Salary Grade</label>
                        <input type="text" name="new_salary_grade" placeholder="e.g., Grade 5">
                    </div>
                </div>
            </div>
            
            <!-- Decision Notes -->
            <div class="form-group">
                <label>Decision Notes / Justification <span style="color: var(--danger);">*</span></label>
                <textarea name="decision_notes" rows="3" placeholder="Explain the reasoning for this decision..." required></textarea>
            </div>
            
            <!-- HR Comments -->
            <div class="form-group">
                <label>HR Comments (Internal)</label>
                <textarea name="hr_comments" rows="2" placeholder="Additional HR notes..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDecisionModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Record Decision</button>
            </div>
        </form>
    </div>
</div>

<!-- Batch Decision Modal -->
<div id="batchDecisionModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group"></i> Batch Decision</h3>
            <span class="modal-close" onclick="closeBatchDecisionModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="batch_decision">
            
            <div class="form-group">
                <label>Select Employees <span style="color: var(--danger);">*</span></label>
                <div class="batch-selection">
                    <?php 
                    $pending_only = array_filter($pending_decisions, function($emp) {
                        return !$emp['has_decision'];
                    });
                    
                    foreach ($pending_only as $emp): 
                        $fullName = $emp['full_name'];
                        $initials = $emp['first_initial'] . $emp['last_initial'];
                    ?>
                    <div class="employee-checkbox">
                        <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['employee_id']; ?>" id="batch_<?php echo $emp['employee_id']; ?>">
                        <label for="batch_<?php echo $emp['employee_id']; ?>">
                            <span class="checkbox-avatar"><?php echo $initials; ?></span>
                            <div>
                                <strong><?php echo $fullName; ?></strong><br>
                                <small><?php echo $emp['position'] ?? $emp['job_title']; ?> | Ends: <?php echo date('M d', strtotime($emp['probation_end_date'])); ?></small>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($pending_only)): ?>
                    <p style="text-align: center; color: var(--gray); padding: 20px;">No pending decisions</p>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-sm" onclick="selectAllBatch()">Select All</button>
                    <button type="button" class="btn btn-sm" onclick="deselectAllBatch()">Deselect All</button>
                </div>
            </div>
            
            <div class="form-group">
                <label>Batch Decision <span style="color: var(--danger);">*</span></label>
                <select name="batch_decision" required>
                    <option value="">Select Decision</option>
                    <option value="confirm">Confirm All Selected</option>
                    <option value="extend">Extend All Selected</option>
                    <option value="terminate">Terminate All Selected</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Batch Notes (will be applied to all)</label>
                <textarea name="batch_notes" rows="3" placeholder="Notes for all selected employees..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBatchDecisionModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" <?php echo empty($pending_only) ? 'disabled' : ''; ?>>Apply Batch Decision</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Modal -->
<div id="reportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-pdf"></i> Generate Report</h3>
            <span class="modal-close" onclick="closeReportModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="generate_report">
            
            <div class="form-group">
                <label>Report Type</label>
                <select name="report_type">
                    <option value="summary">Summary Report</option>
                    <option value="detailed">Detailed Decision Report</option>
                    <option value="pending">Pending Decisions Only</option>
                    <option value="decided">Decisions Made</option>
                    <option value="overdue">Overdue Decisions</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo date('Y-m-01'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Department (optional)</label>
                <select name="report_department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['department']; ?>"><?php echo ucfirst($dept['department']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Format</label>
                <select name="format">
                    <option value="pdf">PDF Document</option>
                    <option value="excel">Excel Spreadsheet</option>
                    <option value="csv">CSV File</option>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReportModal()">Cancel</button>
                <button type="submit" class="btn btn-info">Generate</button>
            </div>
        </form>
    </div>
</div>

<!-- Reminder Modal -->
<div id="reminderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-bell"></i> Send Reminders</h3>
            <span class="modal-close" onclick="closeReminderModal()">&times;</span>
        </div>
        
        <form>
            <div class="form-group">
                <label>Reminder Type</label>
                <select id="reminder_type">
                    <option value="overdue">Overdue Decisions (<?php echo $stats['overdue']; ?>)</option>
                    <option value="ending">Ending Soon (<?php echo $stats['ending_soon']; ?>)</option>
                    <option value="pending">All Pending (<?php echo $stats['pending']; ?>)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Recipients</label>
                <select id="reminder_recipients">
                    <option value="supervisors">Supervisors Only</option>
                    <option value="hr">HR Only</option>
                    <option value="both">Both Supervisors and HR</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Additional Message</label>
                <textarea id="reminder_message" rows="3" placeholder="Optional message to include..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReminderModal()">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="sendReminders()">Send Reminders</button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript Functions -->
<script>
let currentEmployee = null;

// Image error handling
function handleImageError(img) {
    if (img.getAttribute('data-error-handled') === 'true') return;
    img.setAttribute('data-error-handled', 'true');
    
    const parent = img.parentNode;
    const fallback = document.createElement('div');
    fallback.className = img.classList.contains('card-avatar') ? 'card-avatar' : 'table-avatar';
    fallback.textContent = img.getAttribute('data-initials') || '?';
    
    parent.replaceChild(fallback, img);
}

// Decision Modal
function openDecisionModal(employee) {
    currentEmployee = employee;
    
    // Fill employee info
    document.getElementById('emp_name').textContent = employee.full_name;
    document.getElementById('emp_id').textContent = employee.employee_code || 'N/A';
    document.getElementById('emp_position').textContent = employee.position || employee.job_title;
    document.getElementById('emp_dept').textContent = employee.department ? employee.department.toUpperCase() : 'N/A';
    document.getElementById('emp_supervisor').textContent = employee.supervisor_name || 'Not assigned';
    document.getElementById('emp_start_date').textContent = employee.start_date ? new Date(employee.start_date).toLocaleDateString() : 'N/A';
    document.getElementById('emp_probation_end').textContent = employee.probation_end_date ? new Date(employee.probation_end_date).toLocaleDateString() : 'N/A';
    document.getElementById('emp_score').textContent = employee.avg_score ? employee.avg_score + '%' : 'N/A';
    
    // Fill stats
    document.getElementById('stat_incidents').textContent = employee.incident_count || 0;
    document.getElementById('stat_warnings').textContent = employee.warning_count || 0;
    document.getElementById('stat_absences').textContent = employee.absent_days || 0;
    document.getElementById('stat_late').textContent = employee.late_days || 0;
    
    // Set hidden fields
    document.getElementById('decision_employee_id').value = employee.employee_id;
    document.getElementById('decision_probation_id').value = employee.probation_id;
    
    // Reset decision selection
    document.querySelectorAll('.decision-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.getElementById('selected_decision').value = '';
    document.getElementById('extension_fields').style.display = 'none';
    document.getElementById('promotion_fields').style.display = 'none';
    
    document.getElementById('decisionModal').classList.add('active');
}

function closeDecisionModal() {
    document.getElementById('decisionModal').classList.remove('active');
}

function selectDecision(decision) {
    // Update UI
    document.querySelectorAll('.decision-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    document.getElementById('opt_' + decision).classList.add('selected');
    document.getElementById('selected_decision').value = decision;
    
    // Show/hide relevant fields
    document.getElementById('extension_fields').style.display = decision === 'extend' ? 'block' : 'none';
    document.getElementById('promotion_fields').style.display = decision === 'promote' ? 'block' : 'none';
}

// Form validation
function validateDecisionForm() {
    const decision = document.getElementById('selected_decision').value;
    
    if (!decision) {
        alert('Please select a decision');
        return false;
    }
    
    return true;
}

// Batch Decision Modal
function openBatchDecisionModal() {
    document.getElementById('batchDecisionModal').classList.add('active');
}

function closeBatchDecisionModal() {
    document.getElementById('batchDecisionModal').classList.remove('active');
}

function selectAllBatch() {
    document.querySelectorAll('#batchDecisionModal input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
    });
}

function deselectAllBatch() {
    document.querySelectorAll('#batchDecisionModal input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
}

// Report Modal
function openReportModal() {
    document.getElementById('reportModal').classList.add('active');
}

function closeReportModal() {
    document.getElementById('reportModal').classList.remove('active');
}

// Reminder Modal
function openReminderModal() {
    document.getElementById('reminderModal').classList.add('active');
}

function closeReminderModal() {
    document.getElementById('reminderModal').classList.remove('active');
}

function sendReminders() {
    const type = document.getElementById('reminder_type').value;
    const recipients = document.getElementById('reminder_recipients').value;
    const message = document.getElementById('reminder_message').value;
    
    alert(`Reminders sent!\nType: ${type}\nRecipients: ${recipients}\nMessage: ${message || 'No additional message'}`);
    closeReminderModal();
}

// View Decision
function viewDecision(probationId) {
    alert('View decision details for ID: ' + probationId);
}

// Export to Excel
function exportToExcel() {
    alert('Exporting to Excel...');
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
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
</script>

<?php
// End output buffering and flush
ob_end_flush();
?>