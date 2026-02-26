<?php
// Start output buffering at the VERY FIRST LINE
ob_start();

// modules/hr/probation-tracking.php
$page_title = "Probation Tracking";

// Include required files
require_once 'includes/config.php';
require_once 'config/mail_config.php';

// Get current user ID from session
$current_user_id = $_SESSION['user_id'] ?? 1;

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$decision_filter = isset($_GET['decision']) ? $_GET['decision'] : 'all';
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

function calculateProgress($start_date, $end_date) {
    if (!$start_date || !$end_date) return 0;
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $now = new DateTime();
    
    if ($now > $end) return 100;
    if ($now < $start) return 0;
    
    $total_days = $start->diff($end)->days;
    $elapsed_days = $start->diff($now)->days;
    
    return $total_days > 0 ? round(($elapsed_days / $total_days) * 100) : 0;
}

function getProgressColor($percentage) {
    if ($percentage >= 75) return '#27ae60';
    if ($percentage >= 50) return '#f39c12';
    if ($percentage >= 25) return '#e67e22';
    return '#e74c3c';
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'initiate_probation':
                $employee_id = $_POST['employee_id'] ?? 0;
                $probation_duration = $_POST['probation_duration'] ?? 90;
                $start_date = $_POST['start_date'] ?? date('Y-m-d');
                $end_date = date('Y-m-d', strtotime("+$probation_duration days", strtotime($start_date)));
                
                if ($employee_id) {
                    try {
                        // Check if probation record already exists
                        $check = $pdo->prepare("SELECT id FROM probation_records WHERE new_hire_id = ?");
                        $check->execute([$employee_id]);
                        
                        if ($check->rowCount() == 0) {
                            // Get applicant_id from new_hires
                            $stmt = $pdo->prepare("SELECT applicant_id FROM new_hires WHERE id = ?");
                            $stmt->execute([$employee_id]);
                            $applicant = $stmt->fetch();
                            
                            if ($applicant) {
                                // Insert new probation record - let AUTO_INCREMENT handle the ID
                                $stmt = $pdo->prepare("
                                    INSERT INTO probation_records 
                                    (new_hire_id, applicant_id, probation_start_date, probation_end_date, 
                                     probation_duration_days, status, final_decision, created_by, created_at) 
                                    VALUES (?, ?, ?, ?, ?, 'ongoing', 'pending', ?, NOW())
                                ");
                                $result = $stmt->execute([
                                    $employee_id, 
                                    $applicant['applicant_id'], 
                                    $start_date, 
                                    $end_date, 
                                    $probation_duration,
                                    $current_user_id
                                ]);
                                
                                if ($result) {
                                    $probation_id = $pdo->lastInsertId();
                                    
                                    // Update new_hires with probation end date
                                    $update = $pdo->prepare("UPDATE new_hires SET probation_end_date = ? WHERE id = ?");
                                    $update->execute([$end_date, $employee_id]);
                                    
                                    simpleLog($pdo, $current_user_id, 'initiate_probation', 
                                        "Initiated probation for employee ID: $employee_id");
                                    
                                    $message = "Probation initiated successfully!";
                                } else {
                                    $error = "Failed to insert probation record.";
                                }
                            } else {
                                $error = "Applicant record not found.";
                            }
                        } else {
                            $error = "Probation record already exists for this employee.";
                        }
                    } catch (PDOException $e) {
                        // Log the actual error for debugging
                        error_log("Probation initiation error: " . $e->getMessage());
                        
                        if ($e->errorInfo[1] == 1062) {
                            $error = "Duplicate entry error. Please try again or contact support.";
                        } else {
                            $error = "Error initiating probation: " . $e->getMessage();
                        }
                    } catch (Exception $e) {
                        $error = "Error initiating probation: " . $e->getMessage();
                    }
                } else {
                    $error = "Please select an employee.";
                }
                break;
                
            case 'conduct_review':
                $probation_id = $_POST['probation_id'] ?? 0;
                $review_phase = $_POST['review_phase'] ?? '30_day';
                $review_date = $_POST['review_date'] ?? date('Y-m-d');
                $overall_score = $_POST['overall_score'] ?? 0;
                $strengths = $_POST['strengths'] ?? '';
                $weaknesses = $_POST['weaknesses'] ?? '';
                $improvement_areas = $_POST['improvement_areas'] ?? '';
                $recommendation = $_POST['recommendation'] ?? 'continue';
                
                if ($probation_id) {
                    try {
                        $check = $pdo->prepare("SELECT id FROM probation_reviews WHERE probation_record_id = ? AND review_phase = ?");
                        $check->execute([$probation_id, $review_phase]);
                        
                        if ($check->rowCount() == 0) {
                            $stmt = $pdo->prepare("
                                INSERT INTO probation_reviews 
                                (probation_record_id, review_phase, review_date, reviewer_id, 
                                 overall_score, max_score, percentage_score, strengths, weaknesses, 
                                 improvement_areas, recommendation, status, created_at) 
                                VALUES (?, ?, ?, ?, ?, 100, ?, ?, ?, ?, ?, 'submitted', NOW())
                            ");
                            $stmt->execute([
                                $probation_id,
                                $review_phase,
                                $review_date,
                                $current_user_id,
                                $overall_score,
                                $overall_score,
                                $strengths,
                                $weaknesses,
                                $improvement_areas,
                                $recommendation
                            ]);
                            
                            simpleLog($pdo, $current_user_id, 'conduct_review', 
                                "Conducted $review_phase review for probation ID: $probation_id");
                            
                            $message = "Review submitted successfully!";
                        } else {
                            $error = "A review for this phase already exists.";
                        }
                    } catch (Exception $e) {
                        $error = "Error submitting review: " . $e->getMessage();
                    }
                }
                break;
                
            case 'extend_probation':
                $probation_id = $_POST['probation_id'] ?? 0;
                $extension_days = $_POST['extension_days'] ?? 30;
                $extension_reason = $_POST['extension_reason'] ?? '';
                
                if ($probation_id) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM probation_records WHERE id = ?");
                        $stmt->execute([$probation_id]);
                        $record = $stmt->fetch();
                        
                        if ($record) {
                            $new_end_date = date('Y-m-d', strtotime("+$extension_days days", strtotime($record['probation_end_date'])));
                            
                            $update = $pdo->prepare("
                                UPDATE probation_records 
                                SET status = 'extended', 
                                    probation_end_date = ?,
                                    extended_days = ?,
                                    extension_reason = ?,
                                    final_decision = 'pending'
                                WHERE id = ?
                            ");
                            $update->execute([$new_end_date, $extension_days, $extension_reason, $probation_id]);
                            
                            $update_nh = $pdo->prepare("UPDATE new_hires SET probation_end_date = ? WHERE id = ?");
                            $update_nh->execute([$new_end_date, $record['new_hire_id']]);
                            
                            simpleLog($pdo, $current_user_id, 'extend_probation', 
                                "Extended probation for ID: $probation_id by $extension_days days");
                            
                            $message = "Probation extended successfully!";
                        }
                    } catch (Exception $e) {
                        $error = "Error extending probation: " . $e->getMessage();
                    }
                }
                break;
                
            case 'terminate_employment':
                $probation_id = $_POST['probation_id'] ?? 0;
                $termination_reason = $_POST['termination_reason'] ?? '';
                $termination_date = $_POST['termination_date'] ?? date('Y-m-d');
                
                if ($probation_id) {
                    try {
                        $stmt = $pdo->prepare("SELECT new_hire_id FROM probation_records WHERE id = ?");
                        $stmt->execute([$probation_id]);
                        $record = $stmt->fetch();
                        
                        if ($record) {
                            $update = $pdo->prepare("
                                UPDATE probation_records 
                                SET status = 'terminated', 
                                    final_decision = 'terminate',
                                    decision_date = ?,
                                    decision_notes = ?,
                                    decision_made_by = ?
                                WHERE id = ?
                            ");
                            $update->execute([$termination_date, $termination_reason, $current_user_id, $probation_id]);
                            
                            $update_nh = $pdo->prepare("UPDATE new_hires SET status = 'terminated', employment_status = 'terminated' WHERE id = ?");
                            $update_nh->execute([$record['new_hire_id']]);
                            
                            simpleLog($pdo, $current_user_id, 'terminate_employment', 
                                "Terminated employment for probation ID: $probation_id");
                            
                            $message = "Employment terminated successfully!";
                        }
                    } catch (Exception $e) {
                        $error = "Error terminating employment: " . $e->getMessage();
                    }
                }
                break;
                
            case 'final_decision':
                $probation_id = $_POST['probation_id'] ?? 0;
                $final_decision = $_POST['final_decision'] ?? 'confirm';
                $decision_notes = $_POST['decision_notes'] ?? '';
                
                if ($probation_id) {
                    try {
                        $stmt = $pdo->prepare("SELECT new_hire_id FROM probation_records WHERE id = ?");
                        $stmt->execute([$probation_id]);
                        $record = $stmt->fetch();
                        
                        if ($record) {
                            $status = ($final_decision == 'confirm') ? 'completed' : 
                                     (($final_decision == 'extend') ? 'extended' : 'failed');
                            
                            $update = $pdo->prepare("
                                UPDATE probation_records 
                                SET status = ?,
                                    final_decision = ?,
                                    decision_date = ?,
                                    decision_notes = ?,
                                    decision_made_by = ?
                                WHERE id = ?
                            ");
                            $update->execute([$status, $final_decision, date('Y-m-d'), $decision_notes, $current_user_id, $probation_id]);
                            
                            if ($final_decision == 'confirm') {
                                $update_nh = $pdo->prepare("UPDATE new_hires SET employment_status = 'regular' WHERE id = ?");
                                $update_nh->execute([$record['new_hire_id']]);
                            }
                            
                            simpleLog($pdo, $current_user_id, 'final_decision', 
                                "Made final decision: $final_decision for probation ID: $probation_id");
                            
                            $message = "Final decision recorded successfully!";
                        }
                    } catch (Exception $e) {
                        $error = "Error recording decision: " . $e->getMessage();
                    }
                }
                break;
                
            case 'generate_report':
                $report_type = $_POST['report_type'] ?? 'summary';
                $date_from = $_POST['date_from'] ?? date('Y-m-01');
                $date_to = $_POST['date_to'] ?? date('Y-m-d');
                $department = $_POST['report_department'] ?? '';
                
                simpleLog($pdo, $current_user_id, 'generate_report', 
                    "Generated $report_type probation report for $date_from to $date_to");
                
                $message = "Report generated successfully! Check downloads folder.";
                break;
        }
    }
}

// Get all employees on probation with proper status evaluation
$query = "
    SELECT 
        nh.id as new_hire_id,
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
        jp.title as job_title,
        jp.job_code,
        u.full_name as supervisor_name,
        u.id as supervisor_user_id,
        
        -- Probation record
        pr.id as probation_id,
        pr.probation_start_date,
        pr.probation_end_date as record_end_date,
        pr.status as probation_status,
        pr.final_decision,
        pr.extended_days,
        pr.extension_reason,
        pr.decision_date,
        pr.decision_notes,
        
        -- Calculate effective dates
        COALESCE(pr.probation_start_date, nh.start_date, nh.hire_date) as effective_start_date,
        COALESCE(pr.probation_end_date, nh.probation_end_date, DATE_ADD(COALESCE(nh.start_date, nh.hire_date), INTERVAL 90 DAY)) as effective_end_date,
        
        -- Review information
        (SELECT COUNT(*) FROM probation_reviews WHERE probation_record_id = pr.id) as review_count,
        (SELECT review_phase FROM probation_reviews WHERE probation_record_id = pr.id ORDER BY created_at DESC LIMIT 1) as last_review_phase,
        (SELECT status FROM probation_reviews WHERE probation_record_id = pr.id ORDER BY created_at DESC LIMIT 1) as last_review_status,
        (SELECT recommendation FROM probation_reviews WHERE probation_record_id = pr.id ORDER BY created_at DESC LIMIT 1) as last_review_recommendation,
        (SELECT AVG(percentage_score) FROM probation_reviews WHERE probation_record_id = pr.id) as avg_score,
        
        -- Check if final review exists and is submitted
        CASE 
            WHEN (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND review_phase = 'final' AND status = 'submitted') IS NOT NULL 
            THEN 1 ELSE 0 
        END as has_final_review,
        
        -- Check if any review is submitted
        CASE 
            WHEN (SELECT id FROM probation_reviews WHERE probation_record_id = pr.id AND status = 'submitted') IS NOT NULL 
            THEN 1 ELSE 0 
        END as has_submitted_review,
        
        -- Incident stats
        (SELECT COUNT(*) FROM probation_incidents WHERE probation_record_id = pr.id) as incident_count,
        (SELECT COUNT(*) FROM probation_incidents WHERE probation_record_id = pr.id AND (severity = 'major' OR severity = 'critical')) as serious_incidents,
        
        -- Attendance stats (simplified - you may need to adjust based on your attendance table)
        0 as absent_days,
        0 as late_days,
        
        -- Warning count
        (SELECT COUNT(*) FROM probation_incidents WHERE probation_record_id = pr.id AND incident_type = 'warning') as warning_count,
        
        -- Has probation record flag
        CASE WHEN pr.id IS NOT NULL THEN 1 ELSE 0 END as has_probation_record,
        
        -- Days remaining
        DATEDIFF(COALESCE(pr.probation_end_date, nh.probation_end_date, DATE_ADD(COALESCE(nh.start_date, nh.hire_date), INTERVAL 90 DAY)), CURDATE()) as days_remaining,
        
        -- Overdue flag (probation ended but no final decision)
        CASE 
            WHEN pr.id IS NOT NULL 
                AND COALESCE(pr.probation_end_date, nh.probation_end_date) < CURDATE() 
                AND (pr.final_decision IS NULL OR pr.final_decision = 'pending')
                AND NOT EXISTS (
                    SELECT 1 FROM probation_reviews 
                    WHERE probation_record_id = pr.id 
                    AND review_phase = 'final' 
                    AND status = 'submitted'
                )
            THEN 1 
            ELSE 0 
        END as overdue,
        
        -- Decision needed flag (within 7 days or overdue)
        CASE 
            WHEN pr.id IS NOT NULL 
                AND pr.status IN ('ongoing', 'extended')
                AND (pr.final_decision IS NULL OR pr.final_decision = 'pending')
                AND (
                    DATEDIFF(COALESCE(pr.probation_end_date, nh.probation_end_date), CURDATE()) <= 7
                    OR DATEDIFF(COALESCE(pr.probation_end_date, nh.probation_end_date), CURDATE()) < 0
                )
            THEN 1 
            ELSE 0 
        END as needs_decision,
        
        -- Evaluated flag (has submitted reviews)
        CASE 
            WHEN EXISTS (SELECT 1 FROM probation_reviews WHERE probation_record_id = pr.id AND status = 'submitted')
            THEN 1 
            ELSE 0 
        END as is_evaluated
        
    FROM new_hires nh
    INNER JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
    LEFT JOIN users u ON nh.supervisor_id = u.id
    LEFT JOIN probation_records pr ON nh.id = pr.new_hire_id
    WHERE nh.status IN ('onboarding', 'active')
";

$params = [];

// Status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'no_record') {
        $query .= " AND pr.id IS NULL";
    } elseif ($status_filter === 'evaluated') {
        $query .= " AND EXISTS (SELECT 1 FROM probation_reviews WHERE probation_record_id = pr.id AND status = 'submitted')";
    } elseif ($status_filter === 'pending_decision') {
        $query .= " AND pr.id IS NOT NULL AND pr.status IN ('ongoing', 'extended') AND (pr.final_decision IS NULL OR pr.final_decision = 'pending')";
    } elseif ($status_filter === 'decided') {
        $query .= " AND pr.final_decision IS NOT NULL AND pr.final_decision != 'pending'";
    } elseif ($status_filter === 'ongoing' || $status_filter === 'completed' || $status_filter === 'extended' || $status_filter === 'failed' || $status_filter === 'terminated') {
        $query .= " AND pr.status = ?";
        $params[] = $status_filter;
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
    $query .= " AND (ja.first_name LIKE ? OR ja.last_name LIKE ? OR nh.employee_id LIKE ? OR ja.application_number LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY 
    CASE 
        WHEN pr.id IS NULL THEN 3
        WHEN pr.final_decision IS NOT NULL AND pr.final_decision != 'pending' THEN 4
        WHEN pr.probation_end_date < CURDATE() THEN 0
        WHEN pr.probation_end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1
        ELSE 2
    END,
    COALESCE(pr.probation_end_date, nh.probation_end_date, DATE_ADD(COALESCE(nh.start_date, nh.hire_date), INTERVAL 90 DAY)) ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$probation_employees = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => count($probation_employees),
    'ongoing' => 0,
    'completed' => 0,
    'extended' => 0,
    'failed' => 0,
    'terminated' => 0,
    'no_record' => 0,
    'evaluated' => 0,
    'pending_decision' => 0,
    'decided' => 0,
    'ending_this_month' => 0,
    'overdue' => 0,
    'for_decision' => 0
];

$ending_this_month_start = date('Y-m-01');
$ending_this_month_end = date('Y-m-t');

foreach ($probation_employees as $emp) {
    if ($emp['has_probation_record']) {
        if (isset($stats[$emp['probation_status']])) {
            $stats[$emp['probation_status']]++;
        }
        
        if ($emp['is_evaluated']) {
            $stats['evaluated']++;
        }
        
        if ($emp['final_decision'] && $emp['final_decision'] != 'pending') {
            $stats['decided']++;
        } else {
            $stats['pending_decision']++;
        }
    } else {
        $stats['no_record']++;
    }
    
    $end_date = $emp['effective_end_date'];
    if ($end_date >= $ending_this_month_start && $end_date <= $ending_this_month_end) {
        $stats['ending_this_month']++;
    }
    
    if ($emp['overdue']) {
        $stats['overdue']++;
    }
    
    if ($emp['needs_decision']) {
        $stats['for_decision']++;
    }
}

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department FROM new_hires WHERE department IS NOT NULL AND status IN ('onboarding', 'active') ORDER BY department");
$departments = $stmt->fetchAll();

// Get all employees without probation records for Initiate modal
$stmt = $pdo->prepare("
    SELECT nh.id, nh.employee_id, nh.position, nh.department, nh.start_date, nh.hire_date,
           ja.first_name, ja.last_name, ja.photo_path
    FROM new_hires nh
    INNER JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN probation_records pr ON nh.id = pr.new_hire_id
    WHERE nh.status IN ('onboarding', 'active') AND pr.id IS NULL
    ORDER BY nh.start_date DESC
");
$stmt->execute();
$employees_without_probation = $stmt->fetchAll();

// Get all probation records for dropdowns in modals
$probation_list = [];
foreach ($probation_employees as $emp) {
    if ($emp['has_probation_record']) {
        $probation_list[] = $emp;
    }
}

// Probation status configuration
$status_config = [
    'no_record' => [
        'label' => 'Not Started',
        'icon' => 'fas fa-hourglass-start',
        'color' => '#7f8c8d',
        'bg' => '#7f8c8d20',
        'text' => '#7f8c8d',
        'description' => 'Probation not yet initiated'
    ],
    'evaluated' => [
        'label' => 'Evaluated',
        'icon' => 'fas fa-check-circle',
        'color' => '#3498db',
        'bg' => '#3498db20',
        'text' => '#3498db',
        'description' => 'Evaluation submitted, awaiting decision'
    ],
    'ongoing' => [
        'label' => 'Ongoing',
        'icon' => 'fas fa-clock',
        'color' => '#3498db',
        'bg' => '#3498db20',
        'text' => '#3498db',
        'description' => 'Active probation period'
    ],
    'completed' => [
        'label' => 'Completed',
        'icon' => 'fas fa-check-circle',
        'color' => '#27ae60',
        'bg' => '#27ae6020',
        'text' => '#27ae60',
        'description' => 'Successfully completed probation'
    ],
    'extended' => [
        'label' => 'Extended',
        'icon' => 'fas fa-hourglass-half',
        'color' => '#f39c12',
        'bg' => '#f39c1220',
        'text' => '#f39c12',
        'description' => 'Probation period extended'
    ],
    'failed' => [
        'label' => 'Failed',
        'icon' => 'fas fa-times-circle',
        'color' => '#e74c3c',
        'bg' => '#e74c3c20',
        'text' => '#e74c3c',
        'description' => 'Did not meet probation requirements'
    ],
    'terminated' => [
        'label' => 'Terminated',
        'icon' => 'fas fa-user-slash',
        'color' => '#7f8c8d',
        'bg' => '#7f8c8d20',
        'text' => '#7f8c8d',
        'description' => 'Employment terminated during probation'
    ]
];

// Decision options
$decision_config = [
    'confirm' => [
        'label' => 'Confirm Employment',
        'icon' => 'fas fa-thumbs-up',
        'color' => '#27ae60',
        'bg' => '#27ae6020',
        'text' => '#27ae60'
    ],
    'extend' => [
        'label' => 'Extend Probation',
        'icon' => 'fas fa-hourglass',
        'color' => '#f39c12',
        'bg' => '#f39c1220',
        'text' => '#f39c12'
    ],
    'terminate' => [
        'label' => 'Terminate',
        'icon' => 'fas fa-ban',
        'color' => '#e74c3c',
        'bg' => '#e74c3c20',
        'text' => '#e74c3c'
    ],
    'pending' => [
        'label' => 'Pending Decision',
        'icon' => 'fas fa-hourglass-half',
        'color' => '#7f8c8d',
        'bg' => '#7f8c8d20',
        'text' => '#7f8c8d'
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
.stat-card-modern.orange { border-left-color: var(--orange); }
.stat-card-modern.purple { border-left-color: var(--purple); }
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
.stat-icon-modern.orange { background: var(--orange)20; color: var(--orange); }
.stat-icon-modern.purple { background: var(--purple)20; color: var(--purple); }
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

/* Probation Cards Grid */
.probation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.probation-card {
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

.probation-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
    border-color: var(--primary);
}

.probation-card.no-record {
    border-left: 5px solid var(--gray);
}

.probation-card.ongoing {
    border-left: 5px solid var(--info);
}

.probation-card.evaluated {
    border-left: 5px solid var(--info);
    background: linear-gradient(to right, white, #f0f7ff);
}

.probation-card.completed {
    border-left: 5px solid var(--success);
}

.probation-card.extended {
    border-left: 5px solid var(--warning);
}

.probation-card.failed,
.probation-card.terminated {
    border-left: 5px solid var(--danger);
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

.card-body {
    margin-bottom: 15px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.progress-label {
    font-size: 12px;
    color: var(--gray);
    font-weight: 500;
}

.progress-value {
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
}

.progress-container {
    background: var(--border);
    border-radius: 8px;
    height: 8px;
    overflow: hidden;
    margin-bottom: 12px;
}

.progress-bar {
    height: 100%;
    border-radius: 8px;
    transition: width 0.3s ease;
}

.card-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 12px;
}

.meta-item {
    background: var(--light-gray);
    border-radius: 10px;
    padding: 8px;
    text-align: center;
}

.meta-label {
    font-size: 10px;
    color: var(--gray);
    margin-bottom: 3px;
}

.meta-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
}

.meta-value.small {
    font-size: 12px;
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
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
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

.evaluated-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--info);
    color: white;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    display: flex;
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

.probation-table {
    width: 100%;
    border-collapse: collapse;
}

.probation-table th {
    text-align: left;
    padding: 12px 10px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
}

.probation-table td {
    padding: 12px 10px;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
}

.probation-table tr {
    transition: all 0.3s;
    cursor: pointer;
}

.probation-table tr:hover {
    background: var(--light-gray);
}

.probation-table tr.no-record-row {
    background: #f8f9fa;
}

.probation-table tr.urgent-row {
    background: #fff3f3;
}

.probation-table tr.evaluated-row {
    background: #e8f4fd;
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

.table-progress {
    width: 80px;
    height: 6px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden;
}

.table-progress-bar {
    height: 100%;
    border-radius: 3px;
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

.action-btn.warning {
    background: var(--warning);
    color: white;
}

.action-btn.success {
    background: var(--success);
    color: white;
}

.action-btn.danger {
    background: var(--danger);
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
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 25px;
    max-width: 550px;
    width: 90%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.modal-lg {
    max-width: 700px;
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

/* Employee Selection */
.employee-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: var(--light-gray);
    border-radius: 10px;
    margin-bottom: 10px;
}

.preview-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
}

.preview-info {
    flex: 1;
}

.preview-name {
    font-weight: 600;
    font-size: 14px;
}

.preview-details {
    font-size: 12px;
    color: var(--gray);
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
    
    .probation-grid {
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
}

@media (max-width: 480px) {
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .card-meta-grid {
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
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-title">
        <i class="fas fa-hourglass-half"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div class="view-toggle">
        <a href="?page=performance&subpage=probation-tracking&view=dashboard<?php 
            echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($department_filter) ? '&department=' . urlencode($department_filter) : ''; 
            echo !empty($decision_filter) && $decision_filter != 'all' ? '&decision=' . urlencode($decision_filter) : '';
        ?>" class="view-option <?php echo $view_mode == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a href="?page=performance&subpage=probation-tracking&view=list<?php 
            echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($department_filter) ? '&department=' . urlencode($department_filter) : ''; 
            echo !empty($decision_filter) && $decision_filter != 'all' ? '&decision=' . urlencode($decision_filter) : '';
        ?>" class="view-option <?php echo $view_mode == 'list' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i> List View
        </a>
        <a href="?page=performance&subpage=probation-tracking&view=kpi<?php 
            echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($department_filter) ? '&department=' . urlencode($department_filter) : ''; 
            echo !empty($decision_filter) && $decision_filter != 'all' ? '&decision=' . urlencode($decision_filter) : '';
        ?>" class="view-option <?php echo $view_mode == 'kpi' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> KPI Settings
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-container">
    <div class="stat-card-modern primary">
        <div class="stat-icon-modern primary">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Ongoing Probation</span>
            <span class="stat-value-modern"><?php echo $stats['ongoing']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern info">
        <div class="stat-icon-modern primary">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Evaluated</span>
            <span class="stat-value-modern"><?php echo $stats['evaluated']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern gray">
        <div class="stat-icon-modern gray">
            <i class="fas fa-hourglass-start"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Not Started</span>
            <span class="stat-value-modern"><?php echo $stats['no_record']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern warning">
        <div class="stat-icon-modern warning">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Ending This Month</span>
            <span class="stat-value-modern"><?php echo $stats['ending_this_month']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern orange">
        <div class="stat-icon-modern orange">
            <i class="fas fa-hourglass"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Extended</span>
            <span class="stat-value-modern"><?php echo $stats['extended']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern success">
        <div class="stat-icon-modern success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Completed</span>
            <span class="stat-value-modern"><?php echo $stats['completed']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern purple">
        <div class="stat-icon-modern purple">
            <i class="fas fa-gavel"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Decided</span>
            <span class="stat-value-modern"><?php echo $stats['decided']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern danger">
        <div class="stat-icon-modern danger">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">For Decision</span>
            <span class="stat-value-modern"><?php echo $stats['for_decision']; ?></span>
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
            <div class="alert-title">Overdue Probations</div>
            <div class="alert-value"><?php echo $stats['overdue']; ?> employees</div>
            <a href="#" class="alert-link" onclick="applyFilter('overdue')">View Now →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['for_decision'] > 0): ?>
    <div class="alert-card warning">
        <div class="alert-icon warning">
            <i class="fas fa-clock"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Decision Needed (7 days)</div>
            <div class="alert-value"><?php echo $stats['for_decision']; ?> employees</div>
            <a href="#" class="alert-link" onclick="applyFilter('decision_needed')">Review →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['evaluated'] > 0): ?>
    <div class="alert-card info">
        <div class="alert-icon info">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Evaluated - Awaiting Decision</div>
            <div class="alert-value"><?php echo $stats['evaluated']; ?> employees</div>
            <a href="#" class="alert-link" onclick="applyFilter('evaluated')">View →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['no_record'] > 0): ?>
    <div class="alert-card info">
        <div class="alert-icon info">
            <i class="fas fa-hourglass-start"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Need Probation Initiation</div>
            <div class="alert-value"><?php echo $stats['no_record']; ?> employees</div>
            <a href="#" class="alert-link" onclick="openInitiateProbationModal()">Start Now →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="alert-card success">
        <div class="alert-icon success">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Average Progress</div>
            <div class="alert-value">
                <?php 
                $avg_progress = 0;
                $count = 0;
                foreach ($probation_employees as $emp) {
                    if ($emp['probation_status'] == 'ongoing' || !$emp['has_probation_record']) {
                        $progress = calculateProgress($emp['effective_start_date'], $emp['effective_end_date']);
                        $avg_progress += $progress;
                        $count++;
                    }
                }
                echo $count > 0 ? round($avg_progress / $count) . '%' : '0%';
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Probation Records
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="performance">
        <input type="hidden" name="subpage" value="probation-tracking">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Employees</option>
                    <option value="no_record" <?php echo $status_filter == 'no_record' ? 'selected' : ''; ?>>Not Started</option>
                    <option value="ongoing" <?php echo $status_filter == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="evaluated" <?php echo $status_filter == 'evaluated' ? 'selected' : ''; ?>>Evaluated</option>
                    <option value="pending_decision" <?php echo $status_filter == 'pending_decision' ? 'selected' : ''; ?>>Pending Decision</option>
                    <option value="decided" <?php echo $status_filter == 'decided' ? 'selected' : ''; ?>>Decided</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="extended" <?php echo $status_filter == 'extended' ? 'selected' : ''; ?>>Extended</option>
                    <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="terminated" <?php echo $status_filter == 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Final Decision</label>
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
                    <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                        <?php echo ucfirst(htmlspecialchars($dept['department'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, Employee ID, Application #" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=performance&subpage=probation-tracking&view=<?php echo htmlspecialchars($view_mode); ?>" class="btn btn-secondary btn-sm">
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
    <div class="probation-grid">
        <?php foreach ($probation_employees as $emp): 
            $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
            $initials = strtoupper(substr($emp['first_name'] ?? '', 0, 1) . substr($emp['last_name'] ?? '', 0, 1));
            $progress = calculateProgress($emp['effective_start_date'], $emp['effective_end_date']);
            $progressColor = getProgressColor($progress);
            $photoPath = getEmployeePhoto($emp);
            
            // Determine status
            if (!$emp['has_probation_record']) {
                $status_key = 'no_record';
                $card_class = 'no-record';
            } elseif ($emp['final_decision'] && $emp['final_decision'] != 'pending') {
                $status_key = $emp['probation_status'] == 'completed' ? 'completed' : 
                             ($emp['probation_status'] == 'extended' ? 'extended' : 
                             ($emp['probation_status'] == 'failed' ? 'failed' : 'terminated'));
                $card_class = $status_key;
            } elseif ($emp['is_evaluated']) {
                $status_key = 'evaluated';
                $card_class = 'evaluated';
            } else {
                $status_key = $emp['probation_status'] ?? 'ongoing';
                $card_class = $status_key;
            }
            
            $status = $status_config[$status_key] ?? $status_config['ongoing'];
            $decision = $decision_config[$emp['final_decision'] ?? 'pending'] ?? $decision_config['pending'];
        ?>
        <div class="probation-card <?php echo $card_class; ?>" onclick="window.location.href='?page=performance&subpage=probation-tracking&id=<?php echo $emp['new_hire_id']; ?><?php echo $emp['has_probation_record'] ? '&probation_id=' . $emp['probation_id'] : ''; ?>'">
            
            <?php if ($emp['needs_decision'] && !$emp['is_evaluated']): ?>
            <div class="urgent-badge">
                <i class="fas fa-exclamation-circle"></i> Decision Needed
            </div>
            <?php elseif ($emp['is_evaluated'] && !$emp['final_decision']): ?>
            <div class="evaluated-badge">
                <i class="fas fa-check-circle"></i> Evaluated
            </div>
            <?php endif; ?>
            
            <div class="card-header">
                <?php if ($photoPath): ?>
                    <img src="<?php echo htmlspecialchars($photoPath); ?>" 
                         alt="<?php echo htmlspecialchars($fullName); ?>"
                         class="card-avatar"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                         loading="lazy"
                         data-initials="<?php echo htmlspecialchars($initials); ?>">
                    <div class="card-avatar" style="display: none;"><?php echo htmlspecialchars($initials); ?></div>
                <?php else: ?>
                    <div class="card-avatar">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card-info">
                    <div class="card-name"><?php echo htmlspecialchars($fullName); ?></div>
                    <div class="card-position">
                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($emp['position'] ?? $emp['job_title'] ?? 'N/A'); ?>
                    </div>
                    <div>
                        <span class="card-badge">
                            <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($emp['employee_code'] ?: 'No ID'); ?>
                        </span>
                        <span class="card-badge" style="margin-left: 5px;">
                            <i class="fas fa-building"></i> <?php echo ucfirst(htmlspecialchars($emp['department'] ?? 'N/A')); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="progress-info">
                    <span class="progress-label">Probation Progress</span>
                    <span class="progress-value"><?php echo $progress; ?>%</span>
                </div>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?php echo $progress; ?>%; background: <?php echo $progressColor; ?>;"></div>
                </div>
                
                <div class="card-meta-grid">
                    <div class="meta-item">
                        <div class="meta-label">Start Date</div>
                        <div class="meta-value small"><?php echo $emp['effective_start_date'] ? date('M d, Y', strtotime($emp['effective_start_date'])) : 'N/A'; ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">End Date</div>
                        <div class="meta-value small"><?php echo $emp['effective_end_date'] ? date('M d, Y', strtotime($emp['effective_end_date'])) : 'N/A'; ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Days Left</div>
                        <div class="meta-value"><?php echo $emp['days_remaining'] > 0 ? $emp['days_remaining'] : '0'; ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Incidents</div>
                        <div class="meta-value"><?php echo $emp['incident_count'] ?? 0; ?></div>
                    </div>
                </div>
                
                <?php if ($emp['avg_score']): ?>
                <div style="margin-top: 10px; text-align: center;">
                    <span class="status-badge" style="background: <?php echo $progressColor; ?>20; color: <?php echo $progressColor; ?>;">
                        <i class="fas fa-star"></i> Avg Score: <?php echo round($emp['avg_score']); ?>%
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($emp['last_review_recommendation']): ?>
                <div style="margin-top: 5px; text-align: center;">
                    <span class="status-badge" style="background: #3498db20; color: #3498db;">
                        <i class="fas fa-comment"></i> Recommendation: <?php echo ucfirst(htmlspecialchars($emp['last_review_recommendation'])); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card-footer">
                <span class="status-badge" style="background: <?php echo $status['bg']; ?>; color: <?php echo $status['color']; ?>;">
                    <i class="<?php echo $status['icon']; ?>"></i> <?php echo $status['label']; ?>
                </span>
                <?php if ($emp['has_probation_record']): ?>
                <span class="decision-badge" style="background: <?php echo $decision['bg']; ?>; color: <?php echo $decision['color']; ?>;">
                    <i class="<?php echo $decision['icon']; ?>"></i> <?php echo $decision['label']; ?>
                </span>
                <?php endif; ?>
            </div>
            
            <?php if ($emp['overdue'] && $emp['has_probation_record']): ?>
            <div style="margin-top: 10px;">
                <span class="status-badge" style="background: #e74c3c20; color: #e74c3c; width: 100%;">
                    <i class="fas fa-exclamation-circle"></i> OVERDUE - Decision Required
                </span>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($probation_employees)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 20px;">
            <i class="fas fa-hourglass" style="font-size: 48px; color: var(--gray); opacity: 0.3;"></i>
            <h3 style="margin-top: 15px; color: var(--dark);">No Employees Found</h3>
            <p style="color: var(--gray);">No employees match your current filters.</p>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($view_mode == 'list'): ?>
    <!-- List View -->
    <div class="table-container">
        <table class="probation-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Position/Department</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Progress</th>
                    <th>Days Left</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($probation_employees as $emp): 
                    $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
                    $initials = strtoupper(substr($emp['first_name'] ?? '', 0, 1) . substr($emp['last_name'] ?? '', 0, 1));
                    $progress = calculateProgress($emp['effective_start_date'], $emp['effective_end_date']);
                    $progressColor = getProgressColor($progress);
                    $photoPath = getEmployeePhoto($emp);
                    
                    if (!$emp['has_probation_record']) {
                        $status_key = 'no_record';
                    } elseif ($emp['final_decision'] && $emp['final_decision'] != 'pending') {
                        $status_key = $emp['probation_status'] == 'completed' ? 'completed' : 
                                     ($emp['probation_status'] == 'extended' ? 'extended' : 
                                     ($emp['probation_status'] == 'failed' ? 'failed' : 'terminated'));
                    } elseif ($emp['is_evaluated']) {
                        $status_key = 'evaluated';
                    } else {
                        $status_key = $emp['probation_status'] ?? 'ongoing';
                    }
                    
                    $status = $status_config[$status_key] ?? $status_config['no_record'];
                    $decision = $decision_config[$emp['final_decision'] ?? 'pending'] ?? $decision_config['pending'];
                    
                    $row_class = '';
                    if (!$emp['has_probation_record']) $row_class = 'no-record-row';
                    elseif ($emp['needs_decision'] && !$emp['is_evaluated']) $row_class = 'urgent-row';
                    elseif ($emp['is_evaluated']) $row_class = 'evaluated-row';
                ?>
                <tr class="<?php echo $row_class; ?>" onclick="window.location.href='?page=performance&subpage=probation-tracking&id=<?php echo $emp['new_hire_id']; ?><?php echo $emp['has_probation_record'] ? '&probation_id=' . $emp['probation_id'] : ''; ?>'">
                    <td>
                        <div class="employee-info">
                            <?php if ($photoPath): ?>
                                <img src="<?php echo htmlspecialchars($photoPath); ?>" 
                                     alt="<?php echo htmlspecialchars($fullName); ?>"
                                     class="table-avatar"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                     loading="lazy"
                                     data-initials="<?php echo htmlspecialchars($initials); ?>">
                                <div class="table-avatar" style="display: none;"><?php echo htmlspecialchars($initials); ?></div>
                            <?php else: ?>
                                <div class="table-avatar"><?php echo htmlspecialchars($initials); ?></div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($fullName); ?></strong>
                                <div style="font-size: 11px; color: var(--gray);"><?php echo htmlspecialchars($emp['employee_code'] ?: 'No ID'); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?php echo htmlspecialchars($emp['position'] ?? $emp['job_title'] ?? 'N/A'); ?></div>
                        <div style="font-size: 11px; color: var(--gray);"><?php echo ucfirst(htmlspecialchars($emp['department'] ?? 'N/A')); ?></div>
                    </td>
                    <td><?php echo $emp['effective_start_date'] ? date('M d, Y', strtotime($emp['effective_start_date'])) : 'N/A'; ?></td>
                    <td><?php echo $emp['effective_end_date'] ? date('M d, Y', strtotime($emp['effective_end_date'])) : 'N/A'; ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span><?php echo $progress; ?>%</span>
                            <div class="table-progress">
                                <div class="table-progress-bar" style="width: <?php echo $progress; ?>%; background: <?php echo $progressColor; ?>;"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($emp['days_remaining'] > 0): ?>
                            <?php echo $emp['days_remaining']; ?> days
                        <?php else: ?>
                            <span style="color: #e74c3c;">Overdue</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($emp['avg_score']): ?>
                            <span style="font-weight: 600;"><?php echo round($emp['avg_score']); ?>%</span>
                        <?php else: ?>
                            <span style="color: var(--gray);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge" style="background: <?php echo $status['bg']; ?>; color: <?php echo $status['color']; ?>;">
                            <i class="<?php echo $status['icon']; ?>"></i> <?php echo $status['label']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($emp['has_probation_record']): ?>
                        <span class="decision-badge" style="background: <?php echo $decision['bg']; ?>; color: <?php echo $decision['color']; ?>;">
                            <i class="<?php echo $decision['icon']; ?>"></i> <?php echo $decision['label']; ?>
                        </span>
                        <?php else: ?>
                        <span class="decision-badge" style="background: #7f8c8d20; color: #7f8c8d;">
                            <i class="fas fa-minus"></i> Not Started
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($probation_employees)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <i class="fas fa-hourglass" style="font-size: 32px; color: var(--gray); opacity: 0.3;"></i>
                        <p style="margin-top: 10px;">No employees found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($view_mode == 'kpi'): ?>
    <!-- KPI Settings View -->
    <div class="filter-section">
        <div class="filter-title">
            <i class="fas fa-chart-line"></i> Probation KPI Settings
        </div>
        
        <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn btn-primary" onclick="openAddKPIModal()">
                <i class="fas fa-plus"></i> Add New KPI
            </button>
            <button class="btn btn-info" onclick="openKpiLibraryModal()">
                <i class="fas fa-book"></i> KPI Library
            </button>
        </div>
        
        <?php
        $kpi_query = "
            SELECT * FROM probation_kpis 
            WHERE is_active = 1 
            ORDER BY department, sort_order
        ";
        $kpi_stmt = $pdo->query($kpi_query);
        $all_kpis = $kpi_stmt->fetchAll();
        
        $grouped_kpis = [];
        foreach ($all_kpis as $kpi) {
            $grouped_kpis[$kpi['department']][] = $kpi;
        }
        ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($grouped_kpis as $dept => $kpis): ?>
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; color: var(--dark); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-building"></i> <?php echo ucfirst(htmlspecialchars($dept)); ?> Department
                </h3>
                
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; font-size: 11px; color: var(--gray); padding-bottom: 5px;">KPI</th>
                            <th style="text-align: center; font-size: 11px; color: var(--gray); padding-bottom: 5px;">Target</th>
                            <th style="text-align: center; font-size: 11px; color: var(--gray); padding-bottom: 5px;">Weight</th>
                            <th style="text-align: center; font-size: 11px; color: var(--gray); padding-bottom: 5px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kpis as $kpi): ?>
                        <tr>
                            <td style="padding: 8px 0;">
                                <div style="font-weight: 500; font-size: 12px;"><?php echo htmlspecialchars($kpi['kpi_name']); ?></div>
                                <div style="font-size: 10px; color: var(--gray);"><?php echo htmlspecialchars($kpi['description']); ?></div>
                            </td>
                            <td style="text-align: center; font-size: 12px;">
                                <?php echo htmlspecialchars($kpi['target_value'] ?: $kpi['target_percentage'] . '%'); ?>
                            </td>
                            <td style="text-align: center; font-size: 12px;">
                                <?php echo htmlspecialchars($kpi['weight']); ?>%
                            </td>
                            <td style="text-align: center;">
                                <button class="btn btn-sm" onclick="editKPI(<?php echo $kpi['id']; ?>)" style="padding: 3px 6px;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm" onclick="toggleKPI(<?php echo $kpi['id']; ?>)" style="padding: 3px 6px; background: var(--danger)20; color: var(--danger);">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($all_kpis)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 40px;">
                <i class="fas fa-chart-line" style="font-size: 48px; color: var(--gray); opacity: 0.3;"></i>
                <p style="margin-top: 10px;">No KPIs defined yet. Click "Add New KPI" to create one.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="action-buttons">
    <button class="action-btn primary" onclick="openInitiateProbationModal()">
        <i class="fas fa-play"></i> Initiate Probation
    </button>
    <button class="action-btn success" onclick="openReviewModal()">
        <i class="fas fa-star"></i> Conduct Review
    </button>
    <button class="action-btn warning" onclick="openExtensionModal()">
        <i class="fas fa-hourglass"></i> Extend Probation
    </button>
    <button class="action-btn danger" onclick="openTerminationModal()">
        <i class="fas fa-ban"></i> Terminate
    </button>
    <button class="action-btn info" onclick="openFinalDecisionModal()">
        <i class="fas fa-gavel"></i> Final Decision
    </button>
    <button class="action-btn" onclick="openReportModal()">
        <i class="fas fa-file-pdf"></i> Generate Report
    </button>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Initiate Probation Modal -->
<div id="initiateProbationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-play-circle"></i> Initiate Probation</h3>
            <span class="modal-close" onclick="closeInitiateProbationModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="initiate_probation">
            
            <div class="form-group">
                <label>Select Employee <span style="color: var(--danger);">*</span></label>
                <select name="employee_id" required id="initiate_employee_select" onchange="updateEmployeePreview(this)">
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($employees_without_probation as $emp): 
                        $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
                    ?>
                    <option value="<?php echo $emp['id']; ?>" 
                            data-name="<?php echo htmlspecialchars($fullName); ?>"
                            data-position="<?php echo htmlspecialchars($emp['position']); ?>"
                            data-dept="<?php echo htmlspecialchars($emp['department']); ?>"
                            data-start="<?php echo htmlspecialchars($emp['start_date'] ?: $emp['hire_date']); ?>">
                        <?php echo htmlspecialchars($fullName . ' - ' . ($emp['position'] ?? 'No Position') . ' (' . ucfirst($emp['department']) . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="employee_preview" style="display: none;" class="employee-preview">
                <div class="preview-avatar" id="preview_avatar">?</div>
                <div class="preview-info">
                    <div class="preview-name" id="preview_name"></div>
                    <div class="preview-details" id="preview_details"></div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Probation Duration (days)</label>
                    <select name="probation_duration" required>
                        <option value="30">30 days (1 month)</option>
                        <option value="60">60 days (2 months)</option>
                        <option value="90" selected>90 days (3 months)</option>
                        <option value="120">120 days (4 months)</option>
                        <option value="180">180 days (6 months)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2" placeholder="Additional notes about probation initiation"></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeInitiateProbationModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" <?php echo empty($employees_without_probation) ? 'disabled' : ''; ?>>Initiate Probation</button>
            </div>
        </form>
    </div>
</div>

<!-- Conduct Review Modal -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-star"></i> Conduct Probation Review</h3>
            <span class="modal-close" onclick="closeReviewModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="conduct_review">
            
            <div class="form-group">
                <label>Select Probation Record <span style="color: var(--danger);">*</span></label>
                <select name="probation_id" required>
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($probation_list as $emp): 
                        if ($emp['probation_status'] == 'ongoing' || $emp['probation_status'] == 'extended'):
                        $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
                    ?>
                    <option value="<?php echo $emp['probation_id']; ?>">
                        <?php echo htmlspecialchars($fullName . ' - ' . ($emp['position'] ?? $emp['job_title']) . ' (Ends: ' . date('M d, Y', strtotime($emp['effective_end_date'])) . ')'); ?>
                    </option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Review Phase</label>
                <select name="review_phase" required>
                    <option value="30_day">30-Day Review</option>
                    <option value="60_day">60-Day Review</option>
                    <option value="90_day">90-Day Review</option>
                    <option value="final">Final Review</option>
                    <option value="extension">Extension Review</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Review Date</label>
                    <input type="date" name="review_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Overall Score (0-100)</label>
                    <input type="number" name="overall_score" min="0" max="100" step="0.01" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Strengths</label>
                <textarea name="strengths" rows="2" placeholder="What are the employee's strengths?"></textarea>
            </div>
            
            <div class="form-group">
                <label>Weaknesses / Areas for Improvement</label>
                <textarea name="weaknesses" rows="2" placeholder="What needs improvement?"></textarea>
            </div>
            
            <div class="form-group">
                <label>Improvement Areas</label>
                <textarea name="improvement_areas" rows="2" placeholder="Specific areas to focus on"></textarea>
            </div>
            
            <div class="form-group">
                <label>Recommendation</label>
                <select name="recommendation" required>
                    <option value="continue">Continue Probation</option>
                    <option value="confirm">Confirm Employment Early</option>
                    <option value="extend">Extend Probation</option>
                    <option value="terminate">Terminate Employment</option>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReviewModal()">Cancel</button>
                <button type="submit" class="btn btn-success">Submit Review</button>
            </div>
        </form>
    </div>
</div>

<!-- Extend Probation Modal -->
<div id="extensionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-hourglass"></i> Extend Probation Period</h3>
            <span class="modal-close" onclick="closeExtensionModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="extend_probation">
            
            <div class="form-group">
                <label>Select Probation Record <span style="color: var(--danger);">*</span></label>
                <select name="probation_id" required>
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($probation_list as $emp): 
                        if ($emp['probation_status'] == 'ongoing' || $emp['probation_status'] == 'extended'):
                        $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
                    ?>
                    <option value="<?php echo $emp['probation_id']; ?>">
                        <?php echo htmlspecialchars($fullName . ' - Current end: ' . date('M d, Y', strtotime($emp['effective_end_date']))); ?>
                    </option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Extension Days <span style="color: var(--danger);">*</span></label>
                <select name="extension_days" required>
                    <option value="15">15 days</option>
                    <option value="30" selected>30 days (1 month)</option>
                    <option value="45">45 days</option>
                    <option value="60">60 days (2 months)</option>
                    <option value="90">90 days (3 months)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Reason for Extension <span style="color: var(--danger);">*</span></label>
                <textarea name="extension_reason" rows="3" placeholder="Explain why probation needs to be extended..." required></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeExtensionModal()">Cancel</button>
                <button type="submit" class="btn btn-warning">Extend Probation</button>
            </div>
        </form>
    </div>
</div>

<!-- Terminate Employment Modal -->
<div id="terminationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-ban"></i> Terminate Employment</h3>
            <span class="modal-close" onclick="closeTerminationModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="terminate_employment">
            
            <div class="form-group">
                <label>Select Probation Record <span style="color: var(--danger);">*</span></label>
                <select name="probation_id" required>
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($probation_list as $emp): 
                        if ($emp['probation_status'] == 'ongoing' || $emp['probation_status'] == 'extended'):
                        $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
                    ?>
                    <option value="<?php echo $emp['probation_id']; ?>">
                        <?php echo htmlspecialchars($fullName . ' - ' . ($emp['position'] ?? $emp['job_title'])); ?>
                    </option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Termination Date</label>
                    <input type="date" name="termination_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Reason for Termination <span style="color: var(--danger);">*</span></label>
                <textarea name="termination_reason" rows="4" placeholder="Provide detailed reason for termination..." required></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTerminationModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Terminate Employment</button>
            </div>
        </form>
    </div>
</div>

<!-- Final Decision Modal -->
<div id="finalDecisionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-gavel"></i> Make Final Probation Decision</h3>
            <span class="modal-close" onclick="closeFinalDecisionModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="final_decision">
            
            <div class="form-group">
                <label>Select Probation Record <span style="color: var(--danger);">*</span></label>
                <select name="probation_id" required>
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($probation_list as $emp): 
                        if ($emp['probation_status'] == 'ongoing' || $emp['probation_status'] == 'extended' || $emp['is_evaluated']):
                        $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
                    ?>
                    <option value="<?php echo $emp['probation_id']; ?>">
                        <?php echo htmlspecialchars($fullName . ' - ' . ($emp['position'] ?? $emp['job_title']) . ' (Score: ' . ($emp['avg_score'] ? round($emp['avg_score']) . '%' : 'N/A') . ')'); ?>
                    </option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Final Decision <span style="color: var(--danger);">*</span></label>
                <select name="final_decision" required>
                    <option value="confirm">Confirm Employment (Regular Status)</option>
                    <option value="extend">Extend Probation</option>
                    <option value="terminate">Terminate Employment</option>
                    <option value="failed">Failed Probation (No Rehire)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Decision Notes</label>
                <textarea name="decision_notes" rows="3" placeholder="Add notes about this decision..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeFinalDecisionModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Record Decision</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Modal -->
<div id="reportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-pdf"></i> Generate Probation Report</h3>
            <span class="modal-close" onclick="closeReportModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="generate_report">
            
            <div class="form-group">
                <label>Report Type</label>
                <select name="report_type">
                    <option value="summary">Summary Report</option>
                    <option value="detailed">Detailed Report (with KPIs)</option>
                    <option value="department">Department-wise Report</option>
                    <option value="ending">Ending Soon Report</option>
                    <option value="overdue">Overdue Probations Report</option>
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
                    <option value="<?php echo htmlspecialchars($dept['department']); ?>"><?php echo ucfirst(htmlspecialchars($dept['department'])); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Format</label>
                <select name="report_format">
                    <option value="pdf">PDF Document</option>
                    <option value="excel">Excel Spreadsheet</option>
                    <option value="csv">CSV File</option>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReportModal()">Cancel</button>
                <button type="submit" class="btn btn-info">Generate Report</button>
            </div>
        </form>
    </div>
</div>

<!-- Add KPI Modal -->
<div id="addKPIModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New KPI</h3>
            <span class="modal-close" onclick="closeAddKPIModal()">&times;</span>
        </div>
        
        <form method="POST" action="modules/hr/save_kpi.php">
            <div class="form-group">
                <label>Department</label>
                <select name="department" required>
                    <option value="">Select Department</option>
                    <option value="driver">Driver</option>
                    <option value="warehouse">Warehouse</option>
                    <option value="logistics">Logistics</option>
                    <option value="admin">Admin</option>
                    <option value="management">Management</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>KPI Name</label>
                <input type="text" name="kpi_name" placeholder="e.g., On-time Delivery Rate" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2" placeholder="Describe this KPI"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Target Value</label>
                    <input type="text" name="target_value" placeholder="e.g., 95% or 0">
                </div>
                
                <div class="form-group">
                    <label>Target %</label>
                    <input type="number" name="target_percentage" placeholder="95" min="0" max="100">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Weight (%)</label>
                    <input type="number" name="weight" value="100" min="1" max="100" required>
                </div>
                
                <div class="form-group">
                    <label>Unit</label>
                    <input type="text" name="measurement_unit" placeholder="percentage, count, rating">
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_required" value="1" checked> 
                    Required KPI
                </label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddKPIModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save KPI</button>
            </div>
        </form>
    </div>
</div>

<!-- KPI Library Modal -->
<div id="kpiLibraryModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-book"></i> KPI Library</h3>
            <span class="modal-close" onclick="closeKpiLibraryModal()">&times;</span>
        </div>
        
        <div style="margin-bottom: 20px;">
            <p style="color: var(--gray);">Common KPIs used during probation periods. Click to use in your review.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px;">
                <h4 style="margin: 0 0 10px 0; color: var(--primary);">🚛 Driver KPIs</h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> On-time Delivery Rate (Target: 95%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Safety Compliance (Target: 100%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Accident Record (Target: 0)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Customer Complaints (Target: 0)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Attendance & Punctuality (Target: 95%)</li>
                </ul>
            </div>
            
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px;">
                <h4 style="margin: 0 0 10px 0; color: var(--primary);">📦 Warehouse KPIs</h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Picking Accuracy (Target: 98%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Processing Speed (Target: 90%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Inventory Error Rate (Target: <2%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Attendance & Punctuality (Target: 95%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Team Cooperation (Target: Good)</li>
                </ul>
            </div>
            
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px;">
                <h4 style="margin: 0 0 10px 0; color: var(--primary);">📍 Logistics KPIs</h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Route Optimization (Target: 90%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Dispatch Accuracy (Target: 98%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Communication (Target: Good)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Problem Resolution (Target: 85%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Documentation Accuracy (Target: 98%)</li>
                </ul>
            </div>
            
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px;">
                <h4 style="margin: 0 0 10px 0; color: var(--primary);">👔 Admin KPIs</h4>
                <ul style="list-style: none; padding: 0;">
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Task Completion Rate (Target: 95%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Accuracy of Work (Target: 98%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Responsiveness (Target: 90%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Attendance & Punctuality (Target: 95%)</li>
                    <li style="padding: 5px 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Initiative (Target: Good)</li>
                </ul>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeKpiLibraryModal()">Close</button>
        </div>
    </div>
</div>

<!-- JavaScript Functions -->
<script>
function handleImageError(img) {
    if (img.getAttribute('data-error-handled') === 'true') return;
    img.setAttribute('data-error-handled', 'true');
    
    const parent = img.parentNode;
    const fallback = document.createElement('div');
    fallback.className = img.classList.contains('card-avatar') ? 'card-avatar' : 'table-avatar';
    fallback.textContent = img.getAttribute('data-initials') || '?';
    
    parent.replaceChild(fallback, img);
}

function applyFilter(type) {
    if (type === 'overdue') {
        window.location.href = '?page=performance&subpage=probation-tracking&view=<?php echo $view_mode; ?>&status=overdue';
    } else if (type === 'decision_needed') {
        window.location.href = '?page=performance&subpage=probation-tracking&view=<?php echo $view_mode; ?>&status=pending_decision';
    } else if (type === 'evaluated') {
        window.location.href = '?page=performance&subpage=probation-tracking&view=<?php echo $view_mode; ?>&status=evaluated';
    }
}

function updateEmployeePreview(select) {
    const preview = document.getElementById('employee_preview');
    const selected = select.options[select.selectedIndex];
    
    if (selected.value) {
        const name = selected.getAttribute('data-name');
        const position = selected.getAttribute('data-position');
        const dept = selected.getAttribute('data-dept');
        const startDate = selected.getAttribute('data-start');
        
        document.getElementById('preview_name').textContent = name;
        document.getElementById('preview_details').textContent = 
            (position ? position + ' | ' : '') + 
            (dept ? dept.toUpperCase() : '') + 
            (startDate ? ' | Start: ' + new Date(startDate).toLocaleDateString() : '');
        
        const initials = name ? name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : '?';
        document.getElementById('preview_avatar').textContent = initials;
        
        preview.style.display = 'flex';
    } else {
        preview.style.display = 'none';
    }
}

function openInitiateProbationModal() {
    document.getElementById('initiateProbationModal').classList.add('active');
}

function closeInitiateProbationModal() {
    document.getElementById('initiateProbationModal').classList.remove('active');
}

function openReviewModal() {
    document.getElementById('reviewModal').classList.add('active');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('active');
}

function openExtensionModal() {
    document.getElementById('extensionModal').classList.add('active');
}

function closeExtensionModal() {
    document.getElementById('extensionModal').classList.remove('active');
}

function openTerminationModal() {
    document.getElementById('terminationModal').classList.add('active');
}

function closeTerminationModal() {
    document.getElementById('terminationModal').classList.remove('active');
}

function openFinalDecisionModal() {
    document.getElementById('finalDecisionModal').classList.add('active');
}

function closeFinalDecisionModal() {
    document.getElementById('finalDecisionModal').classList.remove('active');
}

function openReportModal() {
    document.getElementById('reportModal').classList.add('active');
}

function closeReportModal() {
    document.getElementById('reportModal').classList.remove('active');
}

function openAddKPIModal() {
    document.getElementById('addKPIModal').classList.add('active');
}

function closeAddKPIModal() {
    document.getElementById('addKPIModal').classList.remove('active');
}

function openKpiLibraryModal() {
    document.getElementById('kpiLibraryModal').classList.add('active');
}

function closeKpiLibraryModal() {
    document.getElementById('kpiLibraryModal').classList.remove('active');
}

function editKPI(id) {
    alert('Edit KPI: ' + id);
}

function toggleKPI(id) {
    if (confirm('Are you sure you want to deactivate this KPI?')) {
        alert('Toggle KPI: ' + id);
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);
</script>

<?php
ob_end_flush();
?>