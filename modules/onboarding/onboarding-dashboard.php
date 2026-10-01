<?php
// Start output buffering at the VERY FIRST LINE - NO SPACES OR CHARACTERS BEFORE THIS
ob_start();

// modules/recruitment/onboarding-dashboard.php
$page_title = "New Hire Onboarding Dashboard";

// Include required files
require_once 'config/mail_config.php';

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : 'all';

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
function getOnboardingProgressClass($percentage) {
    if ($percentage >= 100) return 'progress-complete';
    if ($percentage >= 75) return 'progress-high';
    if ($percentage >= 50) return 'progress-medium';
    if ($percentage >= 25) return 'progress-low';
    return 'progress-start';
}

function getStatusBadgeClass($status) {
    switch($status) {
        case 'onboarding': return 'badge-info';
        case 'active': return 'badge-success';
        case 'terminated': return 'badge-danger';
        case 'resigned': return 'badge-warning';
        default: return 'badge-secondary';
    }
}

function getTaskStatusIcon($status) {
    switch($status) {
        case 'completed': return '<i class="fas fa-check-circle" style="color: #27ae60;"></i>';
        case 'pending': return '<i class="fas fa-clock" style="color: #f39c12;"></i>';
        case 'overdue': return '<i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i>';
        default: return '<i class="fas fa-circle" style="color: #bdc3c7;"></i>';
    }
}

// Using a different name to avoid conflict with config.php
function formatHireDate($date) {
    return $date ? date('M d, Y', strtotime($date)) : 'Not set';
}

// Get onboarding statistics
$stats = [];

// Total new hires (onboarding)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM new_hires nh
    WHERE nh.status = 'onboarding'
");
$stmt->execute();
$stats['total_onboarding'] = $stmt->fetchColumn() ?: 0;

// Total new hires this month
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM new_hires nh
    WHERE MONTH(nh.hire_date) = MONTH(CURRENT_DATE())
    AND YEAR(nh.hire_date) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$stats['new_hires_month'] = $stmt->fetchColumn() ?: 0;

// Pending documents count (contract, id, medical)
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN contract_signed = 0 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN id_submitted = 0 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN medical_clearance = 0 THEN 1 ELSE 0 END) as pending_docs
    FROM new_hires nh
    WHERE nh.status = 'onboarding'
");
$stmt->execute();
$stats['pending_documents'] = $stmt->fetchColumn() ?: 0;

// Pending orientation/training
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN orientation_completed = 0 THEN 1 ELSE 0 END) +
        SUM(CASE WHEN training_completed = 0 THEN 1 ELSE 0 END) as pending_training
    FROM new_hires nh
    WHERE nh.status = 'onboarding'
");
$stmt->execute();
$stats['pending_orientation'] = $stmt->fetchColumn() ?: 0;

// Ready for activation (100% progress)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM new_hires nh
    WHERE nh.status = 'onboarding'
    AND nh.contract_signed = 1
    AND nh.id_submitted = 1
    AND nh.medical_clearance = 1
    AND nh.training_completed = 1
    AND nh.orientation_completed = 1
    AND nh.equipment_assigned = 1
    AND nh.system_access_granted = 1
");
$stmt->execute();
$stats['ready_for_activation'] = $stmt->fetchColumn() ?: 0;

// Overdue onboarding (start date passed but not completed)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM new_hires nh
    WHERE nh.status = 'onboarding'
    AND nh.start_date < CURRENT_DATE()
    AND (
        nh.contract_signed = 0 OR
        nh.id_submitted = 0 OR
        nh.medical_clearance = 0 OR
        nh.training_completed = 0 OR
        nh.orientation_completed = 0 OR
        nh.equipment_assigned = 0 OR
        nh.system_access_granted = 0
    )
");
$stmt->execute();
$stats['overdue_onboarding'] = $stmt->fetchColumn() ?: 0;

// Get all new hires with their applicant and job details
$query = "
    SELECT 
        nh.id as new_hire_id,
        nh.employee_id,
        nh.hire_date,
        nh.start_date,
        nh.probation_end_date,
        nh.position,
        nh.department,
        nh.supervisor_id,
        nh.employment_status,
        nh.contract_signed,
        nh.contract_signed_date,
        nh.id_submitted,
        nh.medical_clearance,
        nh.training_completed,
        nh.orientation_completed,
        nh.equipment_assigned,
        nh.system_access_granted,
        nh.uniform_size,
        nh.assigned_vehicle,
        nh.assigned_device,
        nh.locker_number,
        nh.system_username,
        nh.system_role,
        nh.status as onboarding_status,
        nh.onboarding_progress,
        nh.notes,
        nh.created_at as onboarded_at,
        
        ja.id as applicant_id,
        ja.application_number,
        ja.first_name,
        ja.last_name,
        ja.email,
        ja.phone,
        ja.photo_path,
        ja.applied_at,
        
        jp.id as job_posting_id,
        jp.title as job_title,
        jp.job_code,
        
        u.full_name as supervisor_name,
        
        -- Calculate progress percentage
        ROUND(
            (
                (CASE WHEN nh.contract_signed = 1 THEN 12.5 ELSE 0 END) +
                (CASE WHEN nh.id_submitted = 1 THEN 12.5 ELSE 0 END) +
                (CASE WHEN nh.medical_clearance = 1 THEN 12.5 ELSE 0 END) +
                (CASE WHEN nh.training_completed = 1 THEN 12.5 ELSE 0 END) +
                (CASE WHEN nh.orientation_completed = 1 THEN 12.5 ELSE 0 END) +
                (CASE WHEN nh.equipment_assigned = 1 THEN 12.5 ELSE 0 END) +
                (CASE WHEN nh.system_access_granted = 1 THEN 12.5 ELSE 0 END) +
                12.5 -- Base for being in system
            ), 0
        ) as progress_calculated,
        
        -- Check if ready for activation
        CASE 
            WHEN nh.contract_signed = 1 
            AND nh.id_submitted = 1 
            AND nh.medical_clearance = 1 
            AND nh.training_completed = 1 
            AND nh.orientation_completed = 1 
            AND nh.equipment_assigned = 1 
            AND nh.system_access_granted = 1 
            THEN 1 
            ELSE 0 
        END as is_ready_for_activation,
        
        -- Check if overdue
        CASE 
            WHEN nh.start_date < CURRENT_DATE() 
            AND (
                nh.contract_signed = 0 OR
                nh.id_submitted = 0 OR
                nh.medical_clearance = 0 OR
                nh.training_completed = 0 OR
                nh.orientation_completed = 0 OR
                nh.equipment_assigned = 0 OR
                nh.system_access_granted = 0
            )
            THEN 1 
            ELSE 0 
        END as is_overdue
        
    FROM new_hires nh
    LEFT JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
    LEFT JOIN users u ON nh.supervisor_id = u.id
    WHERE 1=1
";

$params = [];

// Status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'onboarding') {
        $query .= " AND nh.status = 'onboarding'";
    } elseif ($status_filter === 'active') {
        $query .= " AND nh.status = 'active'";
    } elseif ($status_filter === 'ready') {
        $query .= " AND nh.contract_signed = 1 
                    AND nh.id_submitted = 1 
                    AND nh.medical_clearance = 1 
                    AND nh.training_completed = 1 
                    AND nh.orientation_completed = 1 
                    AND nh.equipment_assigned = 1 
                    AND nh.system_access_granted = 1";
    } elseif ($status_filter === 'overdue') {
        $query .= " AND nh.start_date < CURRENT_DATE() 
                    AND (
                        nh.contract_signed = 0 OR
                        nh.id_submitted = 0 OR
                        nh.medical_clearance = 0 OR
                        nh.training_completed = 0 OR
                        nh.orientation_completed = 0 OR
                        nh.equipment_assigned = 0 OR
                        nh.system_access_granted = 0
                    )";
    } elseif ($status_filter === 'pending_docs') {
        $query .= " AND (nh.contract_signed = 0 OR nh.id_submitted = 0 OR nh.medical_clearance = 0)";
    }
}

// Department filter
if (!empty($department_filter) && $department_filter !== 'all') {
    $query .= " AND nh.department = ?";
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

$query .= " ORDER BY 
            CASE WHEN nh.start_date < CURRENT_DATE() AND (
                nh.contract_signed = 0 OR
                nh.id_submitted = 0 OR
                nh.medical_clearance = 0 OR
                nh.training_completed = 0 OR
                nh.orientation_completed = 0 OR
                nh.equipment_assigned = 0 OR
                nh.system_access_granted = 0
            ) THEN 0 ELSE 1 END,
            nh.start_date ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$new_hires = $stmt->fetchAll();

// Get training modules for assignment
$stmt = $pdo->query("SELECT id, title, department FROM training_modules WHERE required = 1 ORDER BY title");
$training_modules = $stmt->fetchAll();

// Handle AJAX request for task update
if (isset($_POST['ajax']) && $_POST['ajax'] === 'update_task') {
    header('Content-Type: application/json');
    
    $new_hire_id = $_POST['new_hire_id'] ?? 0;
    $task = $_POST['task'] ?? '';
    $value = $_POST['value'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 5; // Default to admin for testing
    
    $valid_tasks = [
        'contract_signed', 'id_submitted', 'medical_clearance', 
        'training_completed', 'orientation_completed', 'equipment_assigned', 
        'system_access_granted'
    ];
    
    if (!in_array($task, $valid_tasks)) {
        echo json_encode(['success' => false, 'error' => 'Invalid task']);
        exit;
    }
    
    try {
        // Update the task
        $stmt = $pdo->prepare("UPDATE new_hires SET $task = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$value, $new_hire_id]);
        
        // If task is completed, set completion date
        if ($value == 1) {
            $date_field = $task . '_date';
            $stmt = $pdo->prepare("UPDATE new_hires SET $date_field = CURRENT_DATE() WHERE id = ?");
            $stmt->execute([$new_hire_id]);
        }
        
        // Calculate new progress
        $stmt = $pdo->prepare("
            SELECT 
                contract_signed, id_submitted, medical_clearance,
                training_completed, orientation_completed, equipment_assigned,
                system_access_granted
            FROM new_hires WHERE id = ?
        ");
        $stmt->execute([$new_hire_id]);
        $hire = $stmt->fetch();
        
        $progress = round(
            (
                ($hire['contract_signed'] ? 12.5 : 0) +
                ($hire['id_submitted'] ? 12.5 : 0) +
                ($hire['medical_clearance'] ? 12.5 : 0) +
                ($hire['training_completed'] ? 12.5 : 0) +
                ($hire['orientation_completed'] ? 12.5 : 0) +
                ($hire['equipment_assigned'] ? 12.5 : 0) +
                ($hire['system_access_granted'] ? 12.5 : 0) +
                12.5
            ), 0
        );
        
        $stmt = $pdo->prepare("UPDATE new_hires SET onboarding_progress = ? WHERE id = ?");
        $stmt->execute([$progress, $new_hire_id]);
        
        // Check if ready for activation
        $ready = (
            $hire['contract_signed'] && 
            $hire['id_submitted'] && 
            $hire['medical_clearance'] && 
            $hire['training_completed'] && 
            $hire['orientation_completed'] && 
            $hire['equipment_assigned'] && 
            $hire['system_access_granted']
        );
        
        // Log activity
        $task_name = str_replace('_', ' ', $task);
        $action_text = $value ? "Completed" : "Marked incomplete";
        simpleLog($pdo, $user_id, 'update_onboarding_task', 
            "$action_text $task_name for new hire #$new_hire_id");
        
        echo json_encode([
            'success' => true, 
            'progress' => $progress,
            'ready' => $ready,
            'message' => 'Task updated successfully'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle activate employee
if (isset($_POST['activate_employee'])) {
    $new_hire_id = $_POST['new_hire_id'];
    $user_id = $_SESSION['user_id'] ?? 5;
    
    try {
        // Check if all requirements are met
        $stmt = $pdo->prepare("
            SELECT * FROM new_hires WHERE id = ? AND 
            contract_signed = 1 AND id_submitted = 1 AND medical_clearance = 1 AND
            training_completed = 1 AND orientation_completed = 1 AND
            equipment_assigned = 1 AND system_access_granted = 1
        ");
        $stmt->execute([$new_hire_id]);
        
        if ($stmt->rowCount() == 0) {
            $error = "Cannot activate employee: Not all onboarding steps are completed";
        } else {
            // Generate employee ID if not set
            $emp_id = 'EMP-' . date('Y') . '-' . str_pad($new_hire_id, 4, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("
                UPDATE new_hires 
                SET status = 'active', 
                    employee_id = COALESCE(employee_id, ?),
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$emp_id, $new_hire_id]);
            
            // Update applicant status
            $stmt = $pdo->prepare("
                UPDATE new_hires SET applicant_id WHERE id = ?
            ");
            $stmt->execute([$new_hire_id]);
            $applicant_id = $stmt->fetchColumn();
            
            if ($applicant_id) {
                $stmt = $pdo->prepare("UPDATE job_applications SET status = 'hired' WHERE id = ?");
                $stmt->execute([$applicant_id]);
            }
            
            simpleLog($pdo, $user_id, 'activate_employee', "Activated employee #$new_hire_id");
            $message = "Employee activated successfully";
        }
    } catch (Exception $e) {
        $error = "Error activating employee: " . $e->getMessage();
    }
}

// Handle assign training
if (isset($_POST['assign_training'])) {
    $new_hire_id = $_POST['new_hire_id'];
    $training_id = $_POST['training_id'];
    $user_id = $_SESSION['user_id'] ?? 5;
    
    try {
        // Check if already assigned
        $stmt = $pdo->prepare("SELECT id FROM employee_training WHERE new_hire_id = ? AND training_id = ?");
        $stmt->execute([$new_hire_id, $training_id]);
        
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO employee_training (new_hire_id, training_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$new_hire_id, $training_id]);
            
            simpleLog($pdo, $user_id, 'assign_training', "Assigned training module #$training_id to new hire #$new_hire_id");
            $message = "Training assigned successfully";
        } else {
            $error = "Training already assigned to this employee";
        }
    } catch (Exception $e) {
        $error = "Error assigning training: " . $e->getMessage();
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
    --dark: #2c3e50;
    --gray: #64748b;
    --light-gray: #f8fafd;
    --border: #eef2f6;
    
    --progress-start: #e74c3c;
    --progress-low: #e67e22;
    --progress-medium: #f39c12;
    --progress-high: #3498db;
    --progress-complete: #27ae60;
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
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
}

.stat-card.overdue {
    border-left: 4px solid var(--danger);
}

.stat-card.ready {
    border-left: 4px solid var(--success);
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

.stat-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.stat-badge.warning {
    background: var(--warning);
    color: white;
}

.stat-badge.success {
    background: var(--success);
    color: white;
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

.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #2ecc71 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(39, 174, 96, 0.2);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning) 0%, #f1c40f 100%);
    color: white;
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

.btn-icon {
    padding: 8px;
    border-radius: 8px;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
}

.badge-info {
    background: var(--info)20;
    color: var(--info);
}

.badge-success {
    background: var(--success)20;
    color: var(--success);
}

.badge-warning {
    background: var(--warning)20;
    color: var(--warning);
}

.badge-danger {
    background: var(--danger)20;
    color: var(--danger);
}

.badge-secondary {
    background: var(--gray)20;
    color: var(--gray);
}

/* New Hire Table */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    margin-bottom: 30px;
    overflow-x: auto;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.table-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-title i {
    color: var(--primary);
}

.new-hire-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
}

.new-hire-table th {
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

.new-hire-table td {
    padding: 15px 12px;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
    font-size: 13px;
    vertical-align: middle;
}

.new-hire-table tr:hover td {
    background: var(--light-gray);
}

.new-hire-table tr.overdue {
    background: rgba(231, 76, 60, 0.05);
    border-left: 3px solid var(--danger);
}

.new-hire-table tr.ready {
    background: rgba(39, 174, 96, 0.05);
    border-left: 3px solid var(--success);
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.employee-avatar {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    object-fit: cover;
}

.employee-details {
    display: flex;
    flex-direction: column;
}

.employee-name {
    font-weight: 600;
    color: var(--dark);
}

.employee-email {
    font-size: 11px;
    color: var(--gray);
}

/* Progress Bar */
.progress-container {
    width: 120px;
    background: var(--border);
    border-radius: 20px;
    height: 8px;
    position: relative;
    margin-bottom: 5px;
}

.progress-bar {
    height: 8px;
    border-radius: 20px;
    transition: width 0.3s ease;
}

.progress-start { background: var(--progress-start); }
.progress-low { background: var(--progress-low); }
.progress-medium { background: var(--progress-medium); }
.progress-high { background: var(--progress-high); }
.progress-complete { background: var(--progress-complete); }

.progress-text {
    font-size: 11px;
    font-weight: 600;
}

/* Task Checklist */
.checklist-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    margin-top: 20px;
}

.checklist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border);
}

.checklist-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

.checklist-title i {
    color: var(--primary);
}

.checklist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.checklist-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    background: var(--light-gray);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.checklist-item:hover {
    transform: translateX(5px);
}

.checklist-item.completed {
    opacity: 0.8;
    background: rgba(39, 174, 96, 0.1);
}

.checklist-checkbox {
    margin-top: 2px;
}

.checklist-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

.checklist-content {
    flex: 1;
}

.checklist-task {
    font-weight: 600;
    font-size: 14px;
    color: var(--dark);
    margin-bottom: 3px;
}

.checklist-meta {
    display: flex;
    gap: 10px;
    font-size: 11px;
    color: var(--gray);
}

.checklist-meta i {
    width: 12px;
}

.checklist-date {
    display: flex;
    align-items: center;
    gap: 3px;
}

/* Details Card */
.details-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border);
}

.details-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.detail-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.detail-value {
    font-size: 14px;
    color: var(--dark);
    font-weight: 500;
}

.detail-value i {
    color: var(--primary);
    width: 16px;
}

/* Action Bar */
.action-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .checklist-grid {
        grid-template-columns: 1fr;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
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
    max-width: 600px;
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

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 300px;
    animation: slideIn 0.3s ease;
}

.toast.success {
    border-left: 4px solid var(--success);
}

.toast.error {
    border-left: 4px solid var(--danger);
}

.toast-icon {
    font-size: 20px;
}

.toast.success .toast-icon {
    color: var(--success);
}

.toast.error .toast-icon {
    color: var(--danger);
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 600;
    font-size: 14px;
    color: var(--dark);
    margin-bottom: 2px;
}

.toast-message {
    font-size: 12px;
    color: var(--gray);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>

<!-- ==================== HTML CONTENT ==================== -->

<!-- Toast Notifications Container -->
<div id="toastContainer" class="toast-container"></div>

<!-- Page Header -->
<div class="page-header">
    <div class="page-title">
        <i class="fas fa-users-cog"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div class="action-bar">
        <span class="stat-small" style="background: var(--primary-transparent); padding: 8px 16px; border-radius: 30px;">
            <i class="fas fa-chart-line"></i> Active Onboarding: <?php echo $stats['total_onboarding']; ?>
        </span>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-plus"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">New Hires (This Month)</span>
            <span class="stat-value"><?php echo $stats['new_hires_month']; ?></span>
            <div class="stat-small">
                <i class="fas fa-calendar"></i> Started this month
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Documents</span>
            <span class="stat-value"><?php echo $stats['pending_documents']; ?></span>
            <div class="stat-small">
                <i class="fas fa-clock"></i> Contracts, IDs, Medical
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Orientation</span>
            <span class="stat-value"><?php echo $stats['pending_orientation']; ?></span>
            <div class="stat-small">
                <i class="fas fa-graduation-cap"></i> Training & Orientation
            </div>
        </div>
    </div>
    
    <div class="stat-card ready">
        <div class="stat-icon">
            <i class="fas fa-check-double"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Ready for Activation</span>
            <span class="stat-value"><?php echo $stats['ready_for_activation']; ?></span>
            <div class="stat-small">
                <i class="fas fa-thumbs-up"></i> All steps completed
            </div>
        </div>
        <div class="stat-badge success">Ready</div>
    </div>
    
    <div class="stat-card overdue">
        <div class="stat-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Overdue Onboarding</span>
            <span class="stat-value"><?php echo $stats['overdue_onboarding']; ?></span>
            <div class="stat-small">
                <i class="fas fa-clock"></i> Past start date
            </div>
        </div>
        <div class="stat-badge warning">Overdue</div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter New Hires
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="recruitment">
        <input type="hidden" name="subpage" value="onboarding-dashboard">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All New Hires</option>
                    <option value="onboarding" <?php echo $status_filter == 'onboarding' ? 'selected' : ''; ?>>In Onboarding</option>
                    <option value="ready" <?php echo $status_filter == 'ready' ? 'selected' : ''; ?>>Ready for Activation</option>
                    <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    <option value="pending_docs" <?php echo $status_filter == 'pending_docs' ? 'selected' : ''; ?>>Pending Documents</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active Employees</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Department</label>
                <select name="department">
                    <option value="all" <?php echo $department_filter == 'all' ? 'selected' : ''; ?>>All Departments</option>
                    <option value="driver" <?php echo $department_filter == 'driver' ? 'selected' : ''; ?>>Driver</option>
                    <option value="warehouse" <?php echo $department_filter == 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                    <option value="logistics" <?php echo $department_filter == 'logistics' ? 'selected' : ''; ?>>Logistics</option>
                    <option value="admin" <?php echo $department_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="management" <?php echo $department_filter == 'management' ? 'selected' : ''; ?>>Management</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name or Application #" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=recruitment&subpage=onboarding-dashboard" class="btn btn-secondary btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Apply
            </button>
        </div>
    </form>
</div>

<!-- Message/Error Display -->
<?php if ($message): ?>
<div style="background: rgba(39, 174, 96, 0.1); border: 1px solid var(--success); border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: var(--success); font-size: 20px;"></i>
    <span style="color: var(--success);"><?php echo htmlspecialchars($message); ?></span>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div style="background: rgba(231, 76, 60, 0.1); border: 1px solid var(--danger); border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-exclamation-circle" style="color: var(--danger); font-size: 20px;"></i>
    <span style="color: var(--danger);"><?php echo htmlspecialchars($error); ?></span>
</div>
<?php endif; ?>

<!-- New Hires Table -->
<div class="table-container">
    <div class="table-header">
        <div class="table-title">
            <i class="fas fa-list"></i>
            New Hires List
        </div>
        <div>
            <span class="badge badge-info">Total: <?php echo count($new_hires); ?></span>
        </div>
    </div>
    
    <table class="new-hire-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Position</th>
                <th>Department</th>
                <th>Hire Date</th>
                <th>Start Date</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($new_hires)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 60px; color: var(--gray);">
                    <i class="fas fa-user-slash" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <h3>No New Hires Found</h3>
                    <p>No employees are currently in the onboarding process.</p>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($new_hires as $hire): 
                    $progress = $hire['progress_calculated'] ?? 0;
                    $progress_class = getOnboardingProgressClass($progress);
                    $is_ready = $hire['is_ready_for_activation'];
                    $is_overdue = $hire['is_overdue'];
                    
                    $fullName = $hire['first_name'] . ' ' . $hire['last_name'];
                    $initials = strtoupper(substr($hire['first_name'] ?? '', 0, 1) . substr($hire['last_name'] ?? '', 0, 1)) ?: '?';
                    $photoPath = !empty($hire['photo_path']) && file_exists($hire['photo_path']) ? $hire['photo_path'] : null;
                    
                    $row_class = '';
                    if ($is_overdue) $row_class = 'overdue';
                    elseif ($is_ready) $row_class = 'ready';
                ?>
                <tr class="<?php echo $row_class; ?>" data-hire-id="<?php echo $hire['new_hire_id']; ?>">
                    <td>
                        <div class="employee-info">
                            <?php if ($photoPath): ?>
                                <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="employee-avatar" style="object-fit: cover;">
                            <?php else: ?>
                                <div class="employee-avatar">
                                    <?php echo $initials; ?>
                                </div>
                            <?php endif; ?>
                            <div class="employee-details">
                                <span class="employee-name"><?php echo htmlspecialchars($fullName); ?></span>
                                <span class="employee-email"><?php echo htmlspecialchars($hire['email']); ?></span>
                                <span class="employee-email">ID: <?php echo $hire['employee_id'] ?: 'Not assigned'; ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($hire['position']); ?></strong>
                        <div style="font-size: 11px; color: var(--gray);"><?php echo $hire['job_code']; ?></div>
                    </td>
                    <td>
                        <span class="badge badge-info"><?php echo ucfirst($hire['department']); ?></span>
                    </td>
                    <td><?php echo formatDate($hire['hire_date']); ?></td>
                    <td>
                        <?php echo formatDate($hire['start_date']); ?>
                        <?php if ($is_overdue): ?>
                        <div style="font-size: 10px; color: var(--danger); margin-top: 3px;">
                            <i class="fas fa-exclamation-circle"></i> Overdue
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="progress-container">
                            <div class="progress-bar <?php echo $progress_class; ?>" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                        <span class="progress-text <?php echo $progress_class; ?>"><?php echo $progress; ?>% Complete</span>
                    </td>
                    <td>
                        <?php if ($hire['onboarding_status'] == 'active'): ?>
                        <span class="badge badge-success">Active</span>
                        <?php elseif ($is_ready): ?>
                        <span class="badge badge-success">Ready to Activate</span>
                        <?php elseif ($is_overdue): ?>
                        <span class="badge badge-danger">Overdue</span>
                        <?php else: ?>
                        <span class="badge badge-info">In Progress</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-secondary btn-sm btn-icon" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($hire)); ?>)" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-secondary btn-sm btn-icon" onclick="manageTasks(<?php echo $hire['new_hire_id']; ?>, '<?php echo htmlspecialchars($fullName); ?>')" title="Manage Tasks">
                            <i class="fas fa-tasks"></i>
                        </button>
                        <?php if ($is_ready && $hire['onboarding_status'] != 'active'): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Activate this employee? They will become an active employee in the system.');">
                            <input type="hidden" name="new_hire_id" value="<?php echo $hire['new_hire_id']; ?>">
                            <button type="submit" name="activate_employee" class="btn btn-success btn-sm btn-icon" title="Activate Employee">
                                <i class="fas fa-user-check"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Task Management Modal -->
<div id="taskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-tasks" style="color: var(--primary);"></i> <span id="taskModalTitle">Manage Onboarding Tasks</span></h3>
            <span class="modal-close" onclick="closeTaskModal()">&times;</span>
        </div>
        
        <div id="taskModalContent">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-user" style="color: var(--primary);"></i> Employee Details</h3>
            <span class="modal-close" onclick="closeDetailsModal()">&times;</span>
        </div>
        
        <div id="detailsModalContent">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<!-- Assign Training Modal -->
<div id="trainingModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-graduation-cap" style="color: var(--primary);"></i> Assign Training</h3>
            <span class="modal-close" onclick="closeTrainingModal()">&times;</span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="new_hire_id" id="training_new_hire_id">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">Select Training Module</label>
                <select name="training_id" required style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;">
                    <option value="">-- Select Training --</option>
                    <?php foreach ($training_modules as $module): ?>
                    <option value="<?php echo $module['id']; ?>"><?php echo htmlspecialchars($module['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeTrainingModal()">Cancel</button>
                <button type="submit" name="assign_training" class="btn btn-primary">Assign Training</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentHireId = null;

// Task Management
function manageTasks(hireId, employeeName) {
    currentHireId = hireId;
    document.getElementById('taskModalTitle').textContent = `Onboarding Tasks - ${employeeName}`;
    
    // Fetch current task status via AJAX
    fetchTasks(hireId);
    
    document.getElementById('taskModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function fetchTasks(hireId) {
    // In a real implementation, you'd make an AJAX call to get the latest task status
    // For now, we'll use the data from the table row
    const row = document.querySelector(`tr[data-hire-id="${hireId}"]`);
    if (!row) return;
    
    // This is a simplified version - in production, you'd want to fetch fresh data
    const cells = row.cells;
    const name = cells[0].querySelector('.employee-name').textContent;
    
    // You'd normally get this from the database via AJAX
    // For demo purposes, we'll create a basic task list
    let html = `
        <div style="margin-bottom: 20px;">
            <p><strong>${name}</strong> - Onboarding Checklist</p>
            <p style="color: var(--gray); font-size: 13px;">Check off completed tasks. Changes are saved automatically.</p>
        </div>
        
        <div class="checklist-grid" id="taskChecklist">
            <!-- Tasks will be loaded here -->
        </div>
        
        <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn btn-secondary" onclick="closeTaskModal()">Close</button>
            <button class="btn btn-primary" onclick="assignTraining()">Assign Training</button>
        </div>
    `;
    
    document.getElementById('taskModalContent').innerHTML = html;
    
    // Now load the actual tasks
    loadTaskChecklist(hireId);
}

function loadTaskChecklist(hireId) {
    const row = document.querySelector(`tr[data-hire-id="${hireId}"]`);
    if (!row) return;
    
    // In production, you'd get this from the hire data
    // For now, we'll create a standard checklist
    const tasks = [
        { id: 'contract_signed', name: 'Signed Employment Contract', icon: 'file-signature', date: null },
        { id: 'id_submitted', name: 'Submitted Government IDs', icon: 'id-card', date: null },
        { id: 'medical_clearance', name: 'Medical Clearance', icon: 'stethoscope', date: null },
        { id: 'orientation_completed', name: 'Orientation Completed', icon: 'chalkboard-teacher', date: null },
        { id: 'training_completed', name: 'Training Completed', icon: 'graduation-cap', date: null },
        { id: 'equipment_assigned', name: 'Equipment Issued', icon: 'tools', date: null },
        { id: 'system_access_granted', name: 'System Access Created', icon: 'laptop', date: null }
    ];
    
    let checklistHtml = '';
    
    tasks.forEach(task => {
        // In production, you'd check the actual status
        // For demo, we'll assume all are pending
        const status = 0; // This would come from the database
        const dateCompleted = ''; // This would come from the database
        
        checklistHtml += `
            <div class="checklist-item ${status ? 'completed' : ''}" data-task="${task.id}">
                <div class="checklist-checkbox">
                    <input type="checkbox" 
                           onchange="updateTask(${hireId}, '${task.id}', this.checked)"
                           ${status ? 'checked' : ''}>
                </div>
                <div class="checklist-content">
                    <div class="checklist-task">
                        <i class="fas fa-${task.icon}" style="width: 16px; color: var(--primary);"></i>
                        ${task.name}
                    </div>
                    <div class="checklist-meta">
                        ${dateCompleted ? `
                            <span class="checklist-date">
                                <i class="fas fa-calendar-check"></i> ${dateCompleted}
                            </span>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    document.getElementById('taskChecklist').innerHTML = checklistHtml;
}

function updateTask(hireId, task, checked) {
    const value = checked ? 1 : 0;
    
    // Show loading state
    showToast('info', 'Updating...', 'Saving task status');
    
    // AJAX request to update task
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'ajax': 'update_task',
            'new_hire_id': hireId,
            'task': task,
            'value': value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const item = document.querySelector(`.checklist-item[data-task="${task}"]`);
            if (item) {
                if (checked) {
                    item.classList.add('completed');
                } else {
                    item.classList.remove('completed');
                }
            }
            
            // Update progress bar in table
            const row = document.querySelector(`tr[data-hire-id="${hireId}"]`);
            if (row) {
                const progressBar = row.querySelector('.progress-bar');
                const progressText = row.querySelector('.progress-text');
                if (progressBar && progressText) {
                    progressBar.style.width = data.progress + '%';
                    progressBar.className = `progress-bar ${getProgressClass(data.progress)}`;
                    progressText.textContent = data.progress + '% Complete';
                    progressText.className = `progress-text ${getProgressClass(data.progress)}`;
                }
                
                // Update status cell if ready
                if (data.ready) {
                    const statusCell = row.cells[6];
                    statusCell.innerHTML = '<span class="badge badge-success">Ready to Activate</span>';
                    
                    // Add activate button if not present
                    const actionsCell = row.cells[7];
                    if (!actionsCell.innerHTML.includes('activate_employee')) {
                        actionsCell.innerHTML += `
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Activate this employee?');">
                                <input type="hidden" name="new_hire_id" value="${hireId}">
                                <button type="submit" name="activate_employee" class="btn btn-success btn-sm btn-icon" title="Activate Employee">
                                    <i class="fas fa-user-check"></i>
                                </button>
                            </form>
                        `;
                    }
                }
            }
            
            showToast('success', 'Success', 'Task updated successfully');
        } else {
            showToast('error', 'Error', data.error || 'Failed to update task');
            // Revert checkbox
            const checkbox = document.querySelector(`.checklist-item[data-task="${task}"] input`);
            if (checkbox) {
                checkbox.checked = !checked;
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'Error', 'Failed to update task');
        // Revert checkbox
        const checkbox = document.querySelector(`.checklist-item[data-task="${task}"] input`);
        if (checkbox) {
            checkbox.checked = !checked;
        }
    });
}

function getProgressClass(progress) {
    if (progress >= 100) return 'progress-complete';
    if (progress >= 75) return 'progress-high';
    if (progress >= 50) return 'progress-medium';
    if (progress >= 25) return 'progress-low';
    return 'progress-start';
}

function assignTraining() {
    if (!currentHireId) return;
    
    document.getElementById('training_new_hire_id').value = currentHireId;
    closeTaskModal();
    document.getElementById('trainingModal').classList.add('active');
}

// Details View
function viewDetails(hire) {
    const photoPath = hire.photo_path;
    const fullName = hire.first_name + ' ' + hire.last_name;
    const initials = (hire.first_name ? hire.first_name.charAt(0) : '') + (hire.last_name ? hire.last_name.charAt(0) : '');
    
    let html = `
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 25px;">
            ${photoPath ? `
                <img src="${photoPath}" alt="${fullName}" style="width: 80px; height: 80px; border-radius: 15px; object-fit: cover;">
            ` : `
                <div style="width: 80px; height: 80px; border-radius: 15px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 32px;">
                    ${initials}
                </div>
            `}
            <div>
                <h2 style="margin: 0 0 5px; font-size: 22px;">${fullName}</h2>
                <p style="margin: 0; color: var(--gray);">
                    <i class="fas fa-briefcase"></i> ${hire.position} • ${hire.department}
                </p>
                <p style="margin: 5px 0 0; color: var(--gray);">
                    <i class="fas fa-envelope"></i> ${hire.email} • <i class="fas fa-phone"></i> ${hire.phone || 'N/A'}
                </p>
            </div>
        </div>
        
        <div class="details-grid">
            <div class="detail-item">
                <span class="detail-label">Employee ID</span>
                <span class="detail-value">${hire.employee_id || 'Not assigned'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Hire Date</span>
                <span class="detail-value">${formatDate(hire.hire_date)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Start Date</span>
                <span class="detail-value">${formatDate(hire.start_date)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Probation End</span>
                <span class="detail-value">${formatDate(hire.probation_end_date)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Employment Status</span>
                <span class="detail-value">${hire.employment_status}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Supervisor</span>
                <span class="detail-value">${hire.supervisor_name || 'Not assigned'}</span>
            </div>
    `;
    
    // Add equipment details if available
    if (hire.uniform_size || hire.assigned_vehicle || hire.assigned_device || hire.locker_number) {
        html += `
            <div style="grid-column: 1 / -1; margin-top: 15px;">
                <h4 style="margin: 0 0 10px; color: var(--primary);"><i class="fas fa-box"></i> Equipment & Assets</h4>
            </div>
        `;
        
        if (hire.uniform_size) {
            html += `
                <div class="detail-item">
                    <span class="detail-label">Uniform Size</span>
                    <span class="detail-value">${hire.uniform_size}</span>
                </div>
            `;
        }
        if (hire.assigned_vehicle) {
            html += `
                <div class="detail-item">
                    <span class="detail-label">Assigned Vehicle</span>
                    <span class="detail-value">${hire.assigned_vehicle}</span>
                </div>
            `;
        }
        if (hire.assigned_device) {
            html += `
                <div class="detail-item">
                    <span class="detail-label">Assigned Device</span>
                    <span class="detail-value">${hire.assigned_device}</span>
                </div>
            `;
        }
        if (hire.locker_number) {
            html += `
                <div class="detail-item">
                    <span class="detail-label">Locker Number</span>
                    <span class="detail-value">${hire.locker_number}</span>
                </div>
            `;
        }
    }
    
    // Add system access if available
    if (hire.system_username || hire.system_role) {
        html += `
            <div style="grid-column: 1 / -1; margin-top: 15px;">
                <h4 style="margin: 0 0 10px; color: var(--primary);"><i class="fas fa-laptop"></i> System Access</h4>
            </div>
        `;
        
        if (hire.system_username) {
            html += `
                <div class="detail-item">
                    <span class="detail-label">Username</span>
                    <span class="detail-value">${hire.system_username}</span>
                </div>
            `;
        }
        if (hire.system_role) {
            html += `
                <div class="detail-item">
                    <span class="detail-label">Role</span>
                    <span class="detail-value">${hire.system_role}</span>
                </div>
            `;
        }
    }
    
    // Add notes if available
    if (hire.notes) {
        html += `
            <div style="grid-column: 1 / -1; margin-top: 15px;">
                <h4 style="margin: 0 0 10px; color: var(--primary);"><i class="fas fa-sticky-note"></i> Notes</h4>
                <div style="background: var(--light-gray); padding: 15px; border-radius: 10px;">
                    ${hire.notes}
                </div>
            </div>
        `;
    }
    
    html += `</div>`;
    
    document.getElementById('detailsModalContent').innerHTML = html;
    document.getElementById('detailsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Modal Controls
function closeTaskModal() {
    document.getElementById('taskModal').classList.remove('active');
    document.body.style.overflow = '';
}

function closeDetailsModal() {
    document.getElementById('detailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

function closeTrainingModal() {
    document.getElementById('trainingModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Toast Notifications
function showToast(type, title, message) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            container.removeChild(toast);
        }, 300);
    }, 3000);
}

// Helper function
function formatDate(dateString) {
    if (!dateString) return 'Not set';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTaskModal();
        closeDetailsModal();
        closeTrainingModal();
    }
});

// Close modals when clicking outside
window.onclick = function(event) {
    const taskModal = document.getElementById('taskModal');
    const detailsModal = document.getElementById('detailsModal');
    const trainingModal = document.getElementById('trainingModal');
    
    if (event.target == taskModal) {
        closeTaskModal();
    }
    if (event.target == detailsModal) {
        closeDetailsModal();
    }
    if (event.target == trainingModal) {
        closeTrainingModal();
    }
}
</script>

<?php
// End output buffering and flush
ob_end_flush();
?>