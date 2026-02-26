<?php
// Start output buffering at the VERY FIRST LINE
ob_start();

// modules/performance/performance-reviews.php
$page_title = "Performance Reviews";

// Include required files
require_once 'includes/config.php';
require_once 'config/mail_config.php';

// Get current user info (supervisor)
$user = getUserInfo($pdo, $_SESSION['user_id']);
$supervisor_id = $user['id'];
$user_role = $user['role'];

// Get filter parameters
$period_filter = isset($_GET['period']) ? $_GET['period'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
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

function getRatingColor($percentage) {
    if ($percentage >= 90) return '#27ae60';
    if ($percentage >= 80) return '#2ecc71';
    if ($percentage >= 70) return '#f39c12';
    if ($percentage >= 60) return '#e67e22';
    return '#e74c3c';
}

function getRatingLabel($percentage) {
    if ($percentage >= 90) return 'Outstanding';
    if ($percentage >= 80) return 'Very Good';
    if ($percentage >= 70) return 'Satisfactory';
    if ($percentage >= 60) return 'Needs Improvement';
    return 'Poor';
}

function getReviewPeriodText($period) {
    if (!$period) return 'Not Set';
    
    $text = $period['period_name'];
    if (!empty($period['quarter'])) {
        $text .= ' (Q' . $period['quarter'] . ' ' . $period['year'] . ')';
    } elseif (!empty($period['month'])) {
        $monthName = date('F', mktime(0, 0, 0, $period['month'], 1));
        $text .= ' (' . $monthName . ' ' . $period['year'] . ')';
    } else {
        $text .= ' (' . $period['year'] . ')';
    }
    return $text;
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_draft':
            case 'submit_review':
                $review_id = $_POST['review_id'] ?? 0;
                $employee_id = $_POST['employee_id'] ?? 0;
                $period_id = $_POST['period_id'] ?? 0;
                $review_date = $_POST['review_date'] ?? date('Y-m-d');
                
                // KPI ratings
                $kpi_ratings = $_POST['kpi_rating'] ?? [];
                $kpi_comments = $_POST['kpi_comment'] ?? [];
                
                // Behavioral ratings
                $behavioral_ratings = $_POST['behavioral_rating'] ?? [];
                $behavioral_comments = $_POST['behavioral_comment'] ?? [];
                
                // Overall assessment
                $strengths = $_POST['strengths'] ?? '';
                $improvements = $_POST['improvements'] ?? '';
                $achievements = $_POST['achievements'] ?? '';
                $overall_comments = $_POST['overall_comments'] ?? '';
                
                // Recommendation
                $recommendation = $_POST['recommendation'] ?? '';
                
                // Promotion fields
                $proposed_position = $_POST['proposed_position'] ?? '';
                $promotion_justification = $_POST['promotion_justification'] ?? '';
                
                // Salary increase fields
                $proposed_increase = $_POST['proposed_increase'] ?? '';
                $salary_justification = $_POST['salary_justification'] ?? '';
                
                // PIP fields
                $pip_reason = $_POST['pip_reason'] ?? '';
                $pip_duration = $_POST['pip_duration'] ?? 30;
                
                // Calculate scores
                $total_kpi_score = 0;
                $total_kpi_weight = 0;
                foreach ($kpi_ratings as $kpi_id => $rating) {
                    $weight = $_POST['kpi_weight'][$kpi_id] ?? 0;
                    $total_kpi_score += $rating * $weight;
                    $total_kpi_weight += $weight;
                }
                $kpi_average = $total_kpi_weight > 0 ? $total_kpi_score / $total_kpi_weight : 0;
                
                $total_behavioral_score = 0;
                $total_behavioral_weight = 0;
                foreach ($behavioral_ratings as $criteria => $rating) {
                    $weight = $_POST['behavioral_weight'][$criteria] ?? 0;
                    $total_behavioral_score += $rating * $weight;
                    $total_behavioral_weight += $weight;
                }
                $behavioral_average = $total_behavioral_weight > 0 ? $total_behavioral_score / $total_behavioral_weight : 0;
                
                // Overall rating (scale of 1-5)
                $overall_rating = ($kpi_average + $behavioral_average) / 2;
                $overall_percentage = ($overall_rating / 5) * 100;
                
                $status = ($_POST['action'] == 'submit_review') ? 'submitted' : 'draft';
                
                try {
                    $pdo->beginTransaction();
                    
                    // Check if review exists
                    if ($review_id) {
                        // Update existing review
                        $stmt = $pdo->prepare("
                            UPDATE performance_reviews 
                            SET review_date = ?,
                                kpi_rating = ?,
                                attendance_rating = ?,
                                quality_rating = ?,
                                teamwork_rating = ?,
                                overall_rating = ?,
                                strengths = ?,
                                improvements = ?,
                                comments = ?,
                                status = ?,
                                updated_at = NOW()
                            WHERE id = ? AND (reviewer_id = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
                        ");
                        $stmt->execute([
                            $review_date,
                            $kpi_average,
                            $behavioral_average,
                            $overall_rating,
                            $overall_rating,
                            $overall_rating,
                            $strengths,
                            $improvements,
                            $overall_comments,
                            $status,
                            $review_id,
                            $supervisor_id,
                            $supervisor_id
                        ]);
                    } else {
                        // Create new review
                        $stmt = $pdo->prepare("
                            INSERT INTO performance_reviews 
                            (employee_id, reviewer_id, period_id, review_type, review_date, 
                             kpi_rating, attendance_rating, quality_rating, teamwork_rating, 
                             overall_rating, strengths, improvements, comments, status, created_at)
                            VALUES (?, ?, ?, 'quarterly', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $employee_id,
                            $supervisor_id,
                            $period_id,
                            $review_date,
                            $kpi_average,
                            $behavioral_average,
                            $overall_rating,
                            $overall_rating,
                            $overall_rating,
                            $strengths,
                            $improvements,
                            $overall_comments,
                            $status
                        ]);
                        
                        $review_id = $pdo->lastInsertId();
                    }
                    
                    // Save metrics as JSON
                    $metrics = [
                        'kpis' => $kpi_ratings,
                        'kpi_comments' => $kpi_comments,
                        'behavioral' => $behavioral_ratings,
                        'behavioral_comments' => $behavioral_comments,
                        'recommendation' => $recommendation,
                        'proposed_position' => $proposed_position,
                        'promotion_justification' => $promotion_justification,
                        'proposed_increase' => $proposed_increase,
                        'salary_justification' => $salary_justification,
                        'pip_reason' => $pip_reason,
                        'pip_duration' => $pip_duration,
                        'achievements' => $achievements,
                        'overall_percentage' => $overall_percentage,
                        'rating_label' => getRatingLabel($overall_percentage)
                    ];
                    
                    $update = $pdo->prepare("
                        UPDATE performance_reviews 
                        SET metrics = ? 
                        WHERE id = ?
                    ");
                    $update->execute([json_encode($metrics), $review_id]);
                    
                    simpleLog($pdo, $supervisor_id, 'performance_review', 
                        "Submitted performance review for employee ID: $employee_id");
                    
                    $pdo->commit();
                    
                    $message = "Performance review " . ($status == 'submitted' ? 'submitted' : 'saved as draft') . " successfully!";
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error saving review: " . $e->getMessage();
                }
                break;
                
            case 'bulk_review':
                $employee_ids = $_POST['employee_ids'] ?? [];
                $period_id = $_POST['bulk_period_id'] ?? 0;
                
                if (!empty($employee_ids) && $period_id) {
                    $success_count = 0;
                    
                    foreach ($employee_ids as $emp_id) {
                        try {
                            // Check if review already exists
                            $check = $pdo->prepare("SELECT id FROM performance_reviews WHERE employee_id = ? AND period_id = ?");
                            $check->execute([$emp_id, $period_id]);
                            
                            if ($check->rowCount() == 0) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO performance_reviews 
                                    (employee_id, reviewer_id, period_id, review_type, status, created_at)
                                    VALUES (?, ?, ?, 'quarterly', 'draft', NOW())
                                ");
                                $stmt->execute([$emp_id, $supervisor_id, $period_id]);
                                $success_count++;
                            }
                        } catch (Exception $e) {
                            // Skip if error
                        }
                    }
                    
                    $message = "Created $success_count new review drafts.";
                } else {
                    $error = "Please select employees and a review period";
                }
                break;
                
            case 'send_reminders':
                $period_id = $_POST['reminder_period_id'] ?? 0;
                
                if ($period_id) {
                    // Get employees without reviews
                    $stmt = $pdo->prepare("
                        SELECT nh.id, nh.employee_id, ja.first_name, ja.last_name, ja.email,
                               u.full_name as supervisor_name, u.email as supervisor_email
                        FROM new_hires nh
                        INNER JOIN job_applications ja ON nh.applicant_id = ja.id
                        LEFT JOIN performance_reviews pr ON nh.id = pr.employee_id AND pr.period_id = ?
                        LEFT JOIN users u ON nh.supervisor_id = u.id
                        WHERE nh.status = 'active' 
                          AND nh.employment_status = 'regular'
                          AND pr.id IS NULL
                    ");
                    $stmt->execute([$period_id]);
                    $pending = $stmt->fetchAll();
                    
                    $message = "Reminders would be sent to " . count($pending) . " supervisors.";
                    // In production, you'd actually send emails here
                }
                break;
                
            case 'export_report':
                $report_type = $_POST['export_type'] ?? 'summary';
                $period_id = $_POST['export_period_id'] ?? 0;
                
                $message = "Report exported successfully!";
                break;
        }
    }
}

// Get all active review periods
$stmt = $pdo->query("
    SELECT * FROM performance_review_periods 
    WHERE status = 'active' 
    ORDER BY year DESC, 
    CASE period_type
        WHEN 'quarterly' THEN quarter
        WHEN 'monthly' THEN month
        ELSE 0
    END DESC
");
$review_periods = $stmt->fetchAll();

// Get current active period (default to most recent)
$current_period = !empty($review_periods) ? $review_periods[0]['id'] : 0;

// FIXED QUERY - Get all regular employees with their review status for the selected period
$query = "
    SELECT 
        nh.id as employee_id,
        nh.employee_id as employee_code,
        nh.position,
        nh.department,
        nh.start_date as hire_date,
        nh.employment_status,
        nh.supervisor_id,
        ja.first_name,
        ja.last_name,
        ja.email,
        ja.photo_path,
        ja.application_number,
        jp.title as job_title,
        u.full_name as supervisor_name,
        
        -- Review information for selected period (if exists)
        pr.id as review_id,
        pr.review_type,
        pr.review_date,
        pr.kpi_rating,
        pr.attendance_rating,
        pr.quality_rating,
        pr.teamwork_rating,
        pr.overall_rating,
        pr.strengths,
        pr.improvements,
        pr.comments,
        pr.status as review_status,
        pr.metrics,
        pr.created_at as review_created_at,
        
        -- Previous review (for comparison)
        (SELECT overall_rating FROM performance_reviews 
         WHERE employee_id = nh.id AND status = 'acknowledged' 
         ORDER BY review_date DESC LIMIT 1 OFFSET 1) as previous_rating,
        
        -- Attendance summary (last 90 days)
        (SELECT COUNT(*) FROM attendance WHERE employee_id = nh.id AND status = 'absent' AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) as absent_days,
        (SELECT COUNT(*) FROM attendance WHERE employee_id = nh.id AND status = 'late' AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) as late_days,
        
        -- Incident/warning count
        (SELECT COUNT(*) FROM probation_incidents pi 
         INNER JOIN probation_records pr ON pi.probation_record_id = pr.id
         WHERE pr.new_hire_id = nh.id AND pi.severity IN ('major', 'critical')) as incident_count,
        (SELECT COUNT(*) FROM probation_incidents pi 
         INNER JOIN probation_records pr ON pi.probation_record_id = pr.id
         WHERE pr.new_hire_id = nh.id AND pi.incident_type = 'warning') as warning_count,
        
        -- Review period info
        pp.id as period_id,
        pp.period_name,
        pp.period_type,
        pp.year,
        pp.quarter,
        pp.month,
        pp.start_date as period_start,
        pp.end_date as period_end,
        pp.review_deadline,
        
        -- Days until deadline
        DATEDIFF(pp.review_deadline, CURDATE()) as days_until_deadline,
        
        -- Overdue flag
        CASE 
            WHEN pp.review_deadline < CURDATE() AND (pr.status IS NULL OR pr.status = 'draft') THEN 1
            ELSE 0
        END as overdue,
        
        -- Review exists flag
        CASE WHEN pr.id IS NOT NULL THEN 1 ELSE 0 END as has_review,
        
        -- Due soon flag (within 7 days)
        CASE 
            WHEN pp.review_deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                 AND (pr.id IS NULL OR pr.status = 'draft') THEN 1
            ELSE 0
        END as due_soon
        
    FROM new_hires nh
    INNER JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
    LEFT JOIN users u ON nh.supervisor_id = u.id
    CROSS JOIN performance_review_periods pp
    LEFT JOIN performance_reviews pr ON nh.id = pr.employee_id AND pr.period_id = pp.id
    WHERE nh.status = 'active' 
        AND nh.employment_status = 'regular'
";

$params = [];

// Filter by period
if ($period_filter !== 'all') {
    $query .= " AND pp.id = ?";
    $params[] = $period_filter;
} else if ($current_period > 0) {
    $query .= " AND pp.id = ?";
    $params[] = $current_period;
}

// Filter by supervisor (if not admin)
if ($user_role != 'admin') {
    $query .= " AND (nh.supervisor_id = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))";
    $params[] = $supervisor_id;
    $params[] = $supervisor_id;
}

// Status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'pending') {
        $query .= " AND pr.id IS NULL";
    } elseif ($status_filter === 'draft') {
        $query .= " AND pr.status = 'draft'";
    } elseif ($status_filter === 'submitted') {
        $query .= " AND pr.status = 'submitted'";
    } elseif ($status_filter === 'completed') {
        $query .= " AND pr.status = 'acknowledged'";
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
        WHEN pr.id IS NULL THEN 0
        WHEN pr.status = 'draft' THEN 1
        WHEN pr.status = 'submitted' THEN 2
        ELSE 3
    END,
    pp.review_deadline ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$performance_reviews = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => count($performance_reviews),
    'pending' => 0,
    'draft' => 0,
    'submitted' => 0,
    'completed' => 0,
    'overdue' => 0,
    'due_soon' => 0,
    'high_performers' => 0,
    'low_performers' => 0
];

foreach ($performance_reviews as $review) {
    if (!$review['has_review']) {
        $stats['pending']++;
    } else {
        if ($review['review_status'] == 'draft') $stats['draft']++;
        elseif ($review['review_status'] == 'submitted') $stats['submitted']++;
        elseif ($review['review_status'] == 'acknowledged') $stats['completed']++;
    }
    
    if ($review['overdue']) {
        $stats['overdue']++;
    }
    
    if ($review['due_soon']) {
        $stats['due_soon']++;
    }
    
    // Count high/low performers based on current or previous ratings
    $rating = $review['overall_rating'] ? $review['overall_rating'] * 20 : 0;
    if ($rating >= 90) $stats['high_performers']++;
    elseif ($rating < 70 && $rating > 0) $stats['low_performers']++;
}

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department FROM new_hires WHERE department IS NOT NULL AND status = 'active' ORDER BY department");
$departments = $stmt->fetchAll();

// Get employees without reviews for bulk actions
$stmt = $pdo->prepare("
    SELECT nh.id, nh.employee_id, ja.first_name, ja.last_name, nh.position, nh.department
    FROM new_hires nh
    INNER JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN performance_reviews pr ON nh.id = pr.employee_id AND pr.period_id = ?
    WHERE nh.status = 'active' 
      AND nh.employment_status = 'regular'
      AND pr.id IS NULL
      AND (nh.supervisor_id = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
    ORDER BY ja.last_name ASC
");
$stmt->execute([$current_period, $supervisor_id, $supervisor_id]);
$employees_without_review = $stmt->fetchAll();

// KPIs by department (using probation_kpis as reference)
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

// Behavioral criteria with weights
$behavioral_criteria = [
    ['name' => 'Communication', 'weight' => 10, 'description' => 'Effectively communicates with team and supervisors'],
    ['name' => 'Teamwork', 'weight' => 10, 'description' => 'Collaborates well with others'],
    ['name' => 'Initiative', 'weight' => 5, 'description' => 'Takes proactive approach to tasks'],
    ['name' => 'Leadership', 'weight' => 5, 'description' => 'Demonstrates leadership qualities (if applicable)']
];

// Status configuration
$status_config = [
    'pending' => [
        'label' => 'Pending',
        'icon' => 'fas fa-clock',
        'color' => '#7f8c8d',
        'bg' => '#7f8c8d20',
        'text' => '#7f8c8d'
    ],
    'draft' => [
        'label' => 'Draft',
        'icon' => 'fas fa-pencil-alt',
        'color' => '#f39c12',
        'bg' => '#f39c1220',
        'text' => '#f39c12'
    ],
    'submitted' => [
        'label' => 'Submitted',
        'icon' => 'fas fa-check',
        'color' => '#3498db',
        'bg' => '#3498db20',
        'text' => '#3498db'
    ],
    'completed' => [
        'label' => 'Completed',
        'icon' => 'fas fa-check-double',
        'color' => '#27ae60',
        'bg' => '#27ae6020',
        'text' => '#27ae60'
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

.btn-purple {
    background: var(--purple);
    color: white;
}

/* Review Cards Grid */
.review-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.review-card {
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

.review-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
    border-color: var(--primary);
}

.review-card.pending {
    border-left: 5px solid var(--gray);
}

.review-card.draft {
    border-left: 5px solid var(--warning);
}

.review-card.submitted {
    border-left: 5px solid var(--info);
}

.review-card.completed {
    border-left: 5px solid var(--success);
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

.card-body {
    margin-bottom: 15px;
}

.period-info {
    background: var(--light-gray);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.period-icon {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.period-icon.monthly { background: #3498db20; color: #3498db; }
.period-icon.quarterly { background: #9b59b620; color: #9b59b6; }
.period-icon.annual { background: #27ae6020; color: #27ae60; }

.period-details {
    flex: 1;
}

.period-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 2px;
}

.period-date {
    font-size: 11px;
    color: var(--gray);
}

.rating-display {
    text-align: center;
    margin-bottom: 15px;
}

.rating-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 700;
    color: white;
}

.rating-label-display {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 2px;
}

.rating-percentage {
    font-size: 12px;
    color: var(--gray);
}

.comparison-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 5px;
}

.comparison-up { background: #27ae6020; color: #27ae60; }
.comparison-down { background: #e74c3c20; color: #e74c3c; }
.comparison-same { background: #7f8c8d20; color: #7f8c8d; }

.card-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
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

/* Table View */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow-x: auto;
}

.review-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.review-table th {
    text-align: left;
    padding: 12px 10px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
}

.review-table td {
    padding: 12px 10px;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
}

.review-table tr {
    transition: all 0.3s;
    cursor: pointer;
}

.review-table tr:hover {
    background: var(--light-gray);
}

.review-table tr.pending-row {
    background: #f8f9fa;
}

.review-table tr.draft-row {
    background: #fff9e6;
}

.review-table tr.urgent-row {
    background: #fff3f3;
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

.rating-badge {
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.rating-badge.outstanding { background: #27ae6020; color: #27ae60; }
.rating-badge.very-good { background: #2ecc7120; color: #2ecc71; }
.rating-badge.satisfactory { background: #f39c1220; color: #f39c12; }
.rating-badge.needs-improvement { background: #e67e2220; color: #e67e22; }
.rating-badge.poor { background: #e74c3c20; color: #e74c3c; }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 10px 20px;
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
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.modal-lg {
    max-width: 1000px;
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
    vertical-align: middle;
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
    transition: all 0.3s;
}

.kpi-table select {
    width: 100px;
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
    padding: 8px 0;
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
}

.rating-category {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
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
    grid-template-columns: repeat(3, 1fr);
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
    font-size: 14px;
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

/* Bulk Review Modal */
.bulk-selection {
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
    
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .recommendation-options {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .stats-container {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .score-display {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .alert-section {
        grid-template-columns: 1fr;
    }
    
    .review-grid {
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
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .recommendation-options {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stats-container {
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
        <i class="fas fa-star"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div class="view-toggle">
        <a href="?page=performance&subpage=performance-reviews&view=dashboard<?php 
            echo !empty($period_filter) ? '&period=' . $period_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($department_filter) ? '&department=' . $department_filter : ''; 
        ?>" class="view-option <?php echo $view_mode == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a href="?page=performance&subpage=performance-reviews&view=list<?php 
            echo !empty($period_filter) ? '&period=' . $period_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($department_filter) ? '&department=' . $department_filter : ''; 
        ?>" class="view-option <?php echo $view_mode == 'list' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i> List View
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
            <span class="stat-label-modern">Pending Reviews</span>
            <span class="stat-value-modern"><?php echo $stats['pending']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern warning">
        <div class="stat-icon-modern warning">
            <i class="fas fa-pencil-alt"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Draft</span>
            <span class="stat-value-modern"><?php echo $stats['draft']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern info">
        <div class="stat-icon-modern primary">
            <i class="fas fa-check"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Submitted</span>
            <span class="stat-value-modern"><?php echo $stats['submitted']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern success">
        <div class="stat-icon-modern success">
            <i class="fas fa-check-double"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Completed</span>
            <span class="stat-value-modern"><?php echo $stats['completed']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern orange">
        <div class="stat-icon-modern orange">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Due Soon</span>
            <span class="stat-value-modern"><?php echo $stats['due_soon']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern danger">
        <div class="stat-icon-modern danger">
            <i class="fas fa-clock"></i>
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
            <div class="alert-title">Pending Reviews</div>
            <div class="alert-value"><?php echo $stats['pending']; ?> employees</div>
            <a href="#" class="alert-link" onclick="applyFilter('pending')">Review Now →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['overdue'] > 0): ?>
    <div class="alert-card danger">
        <div class="alert-icon danger">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Overdue Reviews</div>
            <div class="alert-value"><?php echo $stats['overdue']; ?> employees</div>
            <a href="#" class="alert-link" onclick="applyFilter('overdue')">Take Action →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['due_soon'] > 0): ?>
    <div class="alert-card info">
        <div class="alert-icon info">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Due This Week</div>
            <div class="alert-value"><?php echo $stats['due_soon']; ?> employees</div>
            <a href="#" class="alert-link" onclick="applyFilter('due')">View →</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Performance Reviews
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="performance">
        <input type="hidden" name="subpage" value="performance-reviews">
        <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Review Period</label>
                <select name="period">
                    <option value="all" <?php echo $period_filter == 'all' ? 'selected' : ''; ?>>All Periods</option>
                    <?php foreach ($review_periods as $period): ?>
                    <option value="<?php echo $period['id']; ?>" <?php echo $period_filter == $period['id'] ? 'selected' : ''; ?>>
                        <?php echo getReviewPeriodText($period); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
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
            <a href="?page=performance&subpage=performance-reviews&view=<?php echo $view_mode; ?>" class="btn btn-secondary btn-sm">
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
    <div class="review-grid">
        <?php foreach ($performance_reviews as $review): 
            $fullName = $review['first_name'] . ' ' . $review['last_name'];
            $initials = strtoupper(substr($review['first_name'], 0, 1) . substr($review['last_name'], 0, 1));
            $photoPath = getEmployeePhoto($review);
            
            // Determine status
            if (!$review['has_review']) {
                $status_key = 'pending';
                $card_class = 'pending';
            } else {
                $status_key = $review['review_status'];
                $card_class = $status_key;
            }
            
            $status = $status_config[$status_key] ?? $status_config['pending'];
            
            // Calculate rating
            $rating_percentage = $review['overall_rating'] ? $review['overall_rating'] * 20 : 0;
            $rating_label = getRatingLabel($rating_percentage);
            $rating_color = getRatingColor($rating_percentage);
            
            // Compare with previous rating
            $comparison = '';
            $comparison_class = '';
            if ($review['previous_rating'] && $review['overall_rating']) {
                $diff = $review['overall_rating'] - $review['previous_rating'];
                if ($diff > 0.1) {
                    $comparison = '↑ ' . number_format($diff, 1);
                    $comparison_class = 'comparison-up';
                } elseif ($diff < -0.1) {
                    $comparison = '↓ ' . number_format(abs($diff), 1);
                    $comparison_class = 'comparison-down';
                } else {
                    $comparison = '=';
                    $comparison_class = 'comparison-same';
                }
            }
            
            // Deadline status
            $deadline_class = '';
            $deadline_text = '';
            if ($review['overdue']) {
                $deadline_class = 'danger';
                $deadline_text = 'Overdue';
            } elseif ($review['days_until_deadline'] <= 7 && $review['days_until_deadline'] > 0) {
                $deadline_class = 'warning';
                $deadline_text = $review['days_until_deadline'] . ' days left';
            }
        ?>
        <div class="review-card <?php echo $card_class; ?>" onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($review)); ?>)">
            
            <?php if ($review['overdue']): ?>
            <div class="urgent-badge">
                <i class="fas fa-exclamation-circle"></i> OVERDUE
            </div>
            <?php elseif ($review['due_soon']): ?>
            <div class="warning-badge">
                <i class="fas fa-clock"></i> Due Soon
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
                    <div class="card-name">
                        <?php echo htmlspecialchars($fullName); ?>
                        <?php if ($comparison): ?>
                        <span class="comparison-badge <?php echo $comparison_class; ?>">
                            <?php echo $comparison; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-position">
                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($review['position'] ?? $review['job_title']); ?>
                    </div>
                    <div>
                        <span class="card-badge">
                            <i class="fas fa-hashtag"></i> <?php echo $review['employee_code'] ?: 'No ID'; ?>
                        </span>
                        <span class="card-badge" style="margin-left: 5px;">
                            <i class="fas fa-building"></i> <?php echo ucfirst($review['department']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="period-info">
                    <div class="period-icon <?php echo $review['period_type']; ?>">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="period-details">
                        <div class="period-name"><?php echo $review['period_name']; ?></div>
                        <div class="period-date">
                            <?php echo date('M d', strtotime($review['period_start'])); ?> - 
                            <?php echo date('M d, Y', strtotime($review['period_end'])); ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($review['has_review'] && $review['overall_rating']): ?>
                <div class="rating-display">
                    <div class="rating-circle" style="background: <?php echo $rating_color; ?>;">
                        <?php echo number_format($review['overall_rating'], 1); ?>
                    </div>
                    <div class="rating-label-display" style="color: <?php echo $rating_color; ?>;">
                        <?php echo $rating_label; ?>
                    </div>
                    <div class="rating-percentage"><?php echo round($rating_percentage); ?>%</div>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 20px; background: var(--light-gray); border-radius: 12px; margin: 10px 0;">
                    <i class="fas fa-file-alt" style="font-size: 32px; color: var(--gray); opacity: 0.5;"></i>
                    <p style="margin: 5px 0 0; color: var(--gray);">No review yet</p>
                </div>
                <?php endif; ?>
                
                <div class="card-meta-grid">
                    <div class="meta-item">
                        <div class="meta-label">Deadline</div>
                        <div class="meta-value small"><?php echo date('M d', strtotime($review['review_deadline'])); ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Hire Date</div>
                        <div class="meta-value small"><?php echo date('M d, Y', strtotime($review['hire_date'])); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="card-footer">
                <span class="status-badge" style="background: <?php echo $status['bg']; ?>; color: <?php echo $status['color']; ?>;">
                    <i class="<?php echo $status['icon']; ?>"></i> <?php echo $status['label']; ?>
                </span>
                
                <?php if ($deadline_text): ?>
                <span class="deadline-badge" style="background: var(--<?php echo $deadline_class; ?>)20; color: var(--<?php echo $deadline_class; ?>);">
                    <i class="fas fa-clock"></i> <?php echo $deadline_text; ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($performance_reviews)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 20px;">
            <i class="fas fa-star" style="font-size: 48px; color: var(--gray); opacity: 0.3;"></i>
            <h3 style="margin-top: 15px; color: var(--dark);">No Performance Reviews Found</h3>
            <p style="color: var(--gray);">No regular employees found for the selected period.</p>
            <?php if (empty($performance_reviews) && $period_filter == 'all'): ?>
            <p style="margin-top: 10px;">You need to create a review period first in HR settings.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($view_mode == 'list'): ?>
    <!-- List View -->
    <div class="table-container">
        <table class="review-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Position/Dept</th>
                    <th>Review Period</th>
                    <th>Due Date</th>
                    <th>Rating</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($performance_reviews as $review): 
                    $fullName = $review['first_name'] . ' ' . $review['last_name'];
                    $initials = strtoupper(substr($review['first_name'], 0, 1) . substr($review['last_name'], 0, 1));
                    $photoPath = getEmployeePhoto($review);
                    
                    if (!$review['has_review']) {
                        $status_key = 'pending';
                        $status = $status_config[$status_key];
                        $row_class = 'pending-row';
                    } else {
                        $status_key = $review['review_status'];
                        $status = $status_config[$status_key] ?? $status_config['pending'];
                        $row_class = $status_key == 'draft' ? 'draft-row' : '';
                    }
                    
                    if ($review['overdue']) {
                        $row_class = 'urgent-row';
                    }
                    
                    $rating_percentage = $review['overall_rating'] ? $review['overall_rating'] * 20 : 0;
                    $rating_class = '';
                    if ($rating_percentage >= 90) $rating_class = 'outstanding';
                    elseif ($rating_percentage >= 80) $rating_class = 'very-good';
                    elseif ($rating_percentage >= 70) $rating_class = 'satisfactory';
                    elseif ($rating_percentage >= 60) $rating_class = 'needs-improvement';
                    elseif ($rating_percentage > 0) $rating_class = 'poor';
                ?>
                <tr class="<?php echo $row_class; ?>" onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($review)); ?>)">
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
                                <div style="font-size: 11px; color: var(--gray);"><?php echo $review['employee_code'] ?: 'No ID'; ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?php echo htmlspecialchars($review['position'] ?? $review['job_title']); ?></div>
                        <div style="font-size: 11px; color: var(--gray);"><?php echo ucfirst($review['department']); ?></div>
                    </td>
                    <td>
                        <div><strong><?php echo $review['period_name']; ?></strong></div>
                        <div style="font-size: 11px; color: var(--gray);">
                            <?php echo ucfirst($review['period_type']); ?>
                        </div>
                    </td>
                    <td>
                        <?php echo date('M d, Y', strtotime($review['review_deadline'])); ?>
                        <?php if ($review['overdue']): ?>
                        <br><span style="font-size: 10px; color: var(--danger);">Overdue</span>
                        <?php elseif ($review['days_until_deadline'] <= 7 && $review['days_until_deadline'] > 0): ?>
                        <br><span style="font-size: 10px; color: var(--warning);"><?php echo $review['days_until_deadline']; ?> days left</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($review['has_review'] && $review['overall_rating']): ?>
                            <span class="rating-badge <?php echo $rating_class; ?>">
                                <?php echo number_format($review['overall_rating'], 1); ?>
                            </span>
                            <div style="font-size: 10px; color: var(--gray); margin-top: 2px;">
                                <?php echo round($rating_percentage); ?>%
                            </div>
                        <?php elseif ($review['has_review']): ?>
                            <span style="color: var(--gray);">In progress</span>
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
                        <?php if (!$review['has_review'] || $review['review_status'] == 'draft'): ?>
                        <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); openReviewModal(<?php echo htmlspecialchars(json_encode($review)); ?>)">
                            <i class="fas fa-pencil-alt"></i> <?php echo $review['has_review'] ? 'Continue' : 'Evaluate'; ?>
                        </button>
                        <?php elseif ($review['review_status'] == 'submitted'): ?>
                        <button class="btn btn-info btn-sm" onclick="event.stopPropagation(); viewReview(<?php echo $review['review_id']; ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php else: ?>
                        <button class="btn btn-success btn-sm" disabled>
                            <i class="fas fa-check"></i> Completed
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($performance_reviews)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        <i class="fas fa-star" style="font-size: 32px; color: var(--gray); opacity: 0.3;"></i>
                        <p style="margin-top: 10px;">No performance reviews found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="action-buttons">
    <button class="action-btn primary" onclick="openBulkReviewModal()">
        <i class="fas fa-layer-group"></i> Bulk Review
    </button>
    <button class="action-btn success" onclick="openExportModal()">
        <i class="fas fa-file-excel"></i> Export Report
    </button>
    <button class="action-btn warning" onclick="openReminderModal()">
        <i class="fas fa-bell"></i> Send Reminders
    </button>
</div>

<!-- Performance Review Modal -->
<div id="reviewModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-star"></i> Performance Review Form</h3>
            <span class="modal-close" onclick="closeReviewModal()">&times;</span>
        </div>
        
        <form method="POST" action="" id="reviewForm">
            <input type="hidden" name="action" id="form_action" value="save_draft">
            <input type="hidden" name="review_id" id="review_id" value="">
            <input type="hidden" name="employee_id" id="employee_id" value="">
            <input type="hidden" name="period_id" id="period_id" value="">
            
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
                        <div class="summary-label">Hire Date</div>
                        <div class="summary-value" id="modal_hire_date"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Department</div>
                        <div class="summary-value" id="modal_department"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Last Rating</div>
                        <div class="summary-value" id="modal_last_rating"></div>
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
                        <div class="summary-label">Review Period</div>
                        <div class="summary-value" id="modal_period"></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Due Date</div>
                        <div class="summary-value" id="modal_due_date"></div>
                    </div>
                </div>
            </div>
            
            <!-- KPI Performance Section -->
            <div class="kpi-section" id="kpi_section">
                <div class="section-title">
                    <i class="fas fa-chart-line"></i>
                    KPI Performance
                </div>
                
                <table class="kpi-table">
                    <thead>
                        <tr>
                            <th>KPI</th>
                            <th>Target</th>
                            <th>Weight</th>
                            <th>Rating (1-5)</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody id="kpi_rows">
                        <!-- Will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <!-- Behavioral / Competency Section -->
            <div class="kpi-section">
                <div class="section-title">
                    <i class="fas fa-users"></i>
                    Behavioral Competencies
                </div>
                
                <table class="kpi-table">
                    <thead>
                        <tr>
                            <th>Criteria</th>
                            <th>Weight</th>
                            <th>Rating (1-5)</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($behavioral_criteria as $criteria): ?>
                        <tr>
                            <td>
                                <strong><?php echo $criteria['name']; ?></strong>
                                <div style="font-size: 11px; color: var(--gray);"><?php echo $criteria['description']; ?></div>
                                <input type="hidden" name="behavioral_weight[<?php echo $criteria['name']; ?>]" value="<?php echo $criteria['weight']; ?>">
                            </td>
                            <td style="width: 80px;"><?php echo $criteria['weight']; ?>%</td>
                            <td style="width: 120px;">
                                <select name="behavioral_rating[<?php echo $criteria['name']; ?>]" class="behavioral-rating" onchange="updateScores()">
                                    <option value="">Select</option>
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Below Average</option>
                                    <option value="3">3 - Satisfactory</option>
                                    <option value="4">4 - Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
                            </td>
                            <td>
                                <textarea name="behavioral_comment[<?php echo $criteria['name']; ?>]" rows="2" placeholder="Optional comments..."></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Score Display -->
            <div class="score-display" id="score_display">
                <div class="score-circle">
                    <span class="score-value" id="final_score">0.0</span>
                    <span class="score-label">Rating</span>
                </div>
                <div class="score-details">
                    <div class="score-row">
                        <span class="label">KPI Score:</span>
                        <span class="value" id="kpi_score">0.0</span>
                    </div>
                    <div class="score-row">
                        <span class="label">Behavioral Score:</span>
                        <span class="value" id="behavioral_score">0.0</span>
                    </div>
                    <div class="score-row">
                        <span class="label">Overall Rating:</span>
                        <span class="value" id="overall_rating">0.0 / 5</span>
                    </div>
                    <div class="score-row">
                        <span class="label">Percentage:</span>
                        <span class="value" id="percentage">0%</span>
                    </div>
                    <div class="score-row">
                        <span class="label">Category:</span>
                        <span class="value">
                            <span id="rating_category" class="rating-category" style="background: #7f8c8d20; color: #7f8c8d;">Not Rated</span>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Supervisor Comments -->
            <div class="kpi-section">
                <div class="section-title">
                    <i class="fas fa-pen"></i>
                    Supervisor Assessment
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Strengths <span style="color: var(--danger);">*</span></label>
                    <textarea name="strengths" rows="3" placeholder="What are the employee's key strengths?" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 10px;"></textarea>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Areas for Improvement <span style="color: var(--danger);">*</span></label>
                    <textarea name="improvements" rows="3" placeholder="What areas need improvement?" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 10px;"></textarea>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Notable Achievements</label>
                    <textarea name="achievements" rows="2" placeholder="Any notable achievements this period?" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 10px;"></textarea>
                </div>
                
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Overall Summary <span style="color: var(--danger);">*</span></label>
                    <textarea name="overall_comments" rows="3" placeholder="Provide a comprehensive assessment..." required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 10px;"></textarea>
                </div>
            </div>
            
            <!-- Recommendation Section -->
            <div class="recommendation-section">
                <div class="section-title">
                    <i class="fas fa-gavel"></i>
                    Recommendations
                </div>
                
                <div class="recommendation-options">
                    <div class="recommendation-option" onclick="selectRecommendation('none')">
                        <div class="title">No Action</div>
                        <div class="desc">Continue as is</div>
                    </div>
                    <div class="recommendation-option" onclick="selectRecommendation('salary')">
                        <div class="title"><i class="fas fa-money-bill-wave" style="color: #27ae60;"></i> Salary Increase</div>
                        <div class="desc">Recommend salary adjustment</div>
                    </div>
                    <div class="recommendation-option" onclick="selectRecommendation('promotion')">
                        <div class="title"><i class="fas fa-arrow-up" style="color: #9b59b6;"></i> Promotion</div>
                        <div class="desc">Recommend promotion</div>
                    </div>
                    <div class="recommendation-option" onclick="selectRecommendation('training')">
                        <div class="title"><i class="fas fa-graduation-cap" style="color: #3498db;"></i> Training Required</div>
                        <div class="desc">Additional training needed</div>
                    </div>
                    <div class="recommendation-option" onclick="selectRecommendation('pip')">
                        <div class="title"><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i> Performance Improvement Plan</div>
                        <div class="desc">Formal PIP required</div>
                    </div>
                    <div class="recommendation-option" onclick="selectRecommendation('disciplinary')">
                        <div class="title"><i class="fas fa-gavel" style="color: #c0392b;"></i> Disciplinary Action</div>
                        <div class="desc">Formal discipline needed</div>
                    </div>
                </div>
                
                <input type="hidden" name="recommendation" id="selected_recommendation" value="">
                
                <!-- Salary Increase Fields -->
                <div id="salary_fields" style="display: none; margin-top: 20px; padding: 15px; background: white; border-radius: 10px;">
                    <h4 style="margin: 0 0 15px 0; color: #27ae60;">Salary Increase Details</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label>Proposed Increase (%)</label>
                            <input type="number" name="proposed_increase" step="0.1" min="0" max="100" placeholder="e.g., 10">
                        </div>
                        <div>
                            <label>Justification</label>
                            <textarea name="salary_justification" rows="2" placeholder="Why does this employee deserve an increase?"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Promotion Fields -->
                <div id="promotion_fields" style="display: none; margin-top: 20px; padding: 15px; background: white; border-radius: 10px;">
                    <h4 style="margin: 0 0 15px 0; color: #9b59b6;">Promotion Details</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label>Proposed Position</label>
                            <input type="text" name="proposed_position" placeholder="e.g., Senior Driver">
                        </div>
                        <div>
                            <label>Justification</label>
                            <textarea name="promotion_justification" rows="2" placeholder="Why does this employee deserve promotion?"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- PIP Fields -->
                <div id="pip_fields" style="display: none; margin-top: 20px; padding: 15px; background: white; border-radius: 10px;">
                    <h4 style="margin: 0 0 15px 0; color: #e74c3c;">Performance Improvement Plan</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label>PIP Duration (days)</label>
                            <select name="pip_duration">
                                <option value="30">30 days</option>
                                <option value="45">45 days</option>
                                <option value="60">60 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                        <div>
                            <label>Reason for PIP</label>
                            <textarea name="pip_reason" rows="2" placeholder="Specific areas requiring improvement..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Review Date -->
            <div class="form-row" style="margin: 20px 0;">
                <div class="form-group">
                    <label>Review Date</label>
                    <input type="date" name="review_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeReviewModal()">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="saveDraft()">
                    <i class="fas fa-save"></i> Save Draft
                </button>
                <button type="button" class="btn btn-success" onclick="submitReview()">
                    <i class="fas fa-paper-plane"></i> Submit to HR
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Review Modal -->
<div id="bulkReviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group"></i> Bulk Create Reviews</h3>
            <span class="modal-close" onclick="closeBulkReviewModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="bulk_review">
            
            <div class="form-group">
                <label>Select Review Period</label>
                <select name="bulk_period_id" required>
                    <?php foreach ($review_periods as $period): ?>
                    <option value="<?php echo $period['id']; ?>"><?php echo getReviewPeriodText($period); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Select Employees <span style="color: var(--danger);">*</span></label>
                <div class="bulk-selection">
                    <?php if (!empty($employees_without_review)): ?>
                        <?php foreach ($employees_without_review as $emp): 
                            $fullName = $emp['first_name'] . ' ' . $emp['last_name'];
                            $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
                        ?>
                        <div class="employee-checkbox">
                            <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['id']; ?>" id="bulk_<?php echo $emp['id']; ?>">
                            <label for="bulk_<?php echo $emp['id']; ?>">
                                <span class="checkbox-avatar"><?php echo $initials; ?></span>
                                <div>
                                    <strong><?php echo $fullName; ?></strong><br>
                                    <small><?php echo $emp['position']; ?> | <?php echo ucfirst($emp['department']); ?></small>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--gray); padding: 20px;">All employees already have reviews for this period!</p>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-sm" onclick="selectAllBulk()">Select All</button>
                    <button type="button" class="btn btn-sm" onclick="deselectAllBulk()">Deselect All</button>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkReviewModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Reviews</button>
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
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="send_reminders">
            
            <div class="form-group">
                <label>Review Period</label>
                <select name="reminder_period_id" required>
                    <?php foreach ($review_periods as $period): ?>
                    <option value="<?php echo $period['id']; ?>"><?php echo getReviewPeriodText($period); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Reminder Type</label>
                <select name="reminder_type">
                    <option value="all">All Pending Reviews</option>
                    <option value="overdue">Overdue Only</option>
                    <option value="upcoming">Due Within 7 Days</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Additional Message (optional)</label>
                <textarea name="reminder_message" rows="3" placeholder="Enter any additional message..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReminderModal()">Cancel</button>
                <button type="submit" class="btn btn-warning">Send Reminders</button>
            </div>
        </form>
    </div>
</div>

<!-- Export Modal -->
<div id="exportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-excel"></i> Export Report</h3>
            <span class="modal-close" onclick="closeExportModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="export_report">
            
            <div class="form-group">
                <label>Report Type</label>
                <select name="export_type">
                    <option value="summary">Summary Report</option>
                    <option value="detailed">Detailed Report (with KPIs)</option>
                    <option value="pending">Pending Reviews Only</option>
                    <option value="completed">Completed Reviews</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Review Period</label>
                <select name="export_period_id">
                    <option value="0">All Periods</option>
                    <?php foreach ($review_periods as $period): ?>
                    <option value="<?php echo $period['id']; ?>"><?php echo getReviewPeriodText($period); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Format</label>
                <select name="export_format">
                    <option value="excel">Excel (.xlsx)</option>
                    <option value="csv">CSV</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeExportModal()">Cancel</button>
                <button type="submit" class="btn btn-success">Export</button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript -->
<script>
let currentReview = null;

function applyFilter(type) {
    const url = new URL(window.location.href);
    if (type === 'pending') {
        url.searchParams.set('status', 'pending');
        window.location.href = url.toString();
    } else if (type === 'overdue') {
        // This would need server-side handling with a custom parameter
        alert('Filtering for overdue reviews - would add to URL');
    } else if (type === 'due') {
        // This would need server-side handling
        alert('Filtering for reviews due this week');
    }
}

function openReviewModal(review) {
    currentReview = review;
    
    // Set form values
    document.getElementById('review_id').value = review.review_id || '';
    document.getElementById('employee_id').value = review.employee_id;
    document.getElementById('period_id').value = review.period_id;
    
    // Set employee info
    document.getElementById('modal_name').textContent = review.first_name + ' ' + review.last_name;
    document.getElementById('modal_position').textContent = (review.position || review.job_title) + ' • ' + review.department;
    document.getElementById('modal_hire_date').textContent = new Date(review.hire_date).toLocaleDateString();
    document.getElementById('modal_department').textContent = review.department ? review.department.toUpperCase() : 'N/A';
    document.getElementById('modal_last_rating').textContent = review.previous_rating ? review.previous_rating.toFixed(1) + '/5' : 'First Review';
    document.getElementById('modal_attendance').textContent = (review.absent_days || 0) + ' absent, ' + (review.late_days || 0) + ' late';
    document.getElementById('modal_incidents').textContent = review.incident_count || 0;
    document.getElementById('modal_warnings').textContent = review.warning_count || 0;
    document.getElementById('modal_period').textContent = review.period_name;
    document.getElementById('modal_due_date').textContent = new Date(review.review_deadline).toLocaleDateString();
    
    // Set avatar
    const initials = (review.first_name[0] + review.last_name[0]).toUpperCase();
    document.getElementById('modal_avatar').textContent = initials;
    
    // Load KPIs based on department
    loadKPIs(review.department);
    
    // Load existing data if review exists
    if (review.has_review && review.metrics) {
        try {
            const metrics = JSON.parse(review.metrics);
            loadExistingData(metrics, review);
        } catch (e) {
            console.error('Error parsing metrics', e);
        }
    } else {
        resetForm();
    }
    
    document.getElementById('reviewModal').classList.add('active');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('active');
}

function loadKPIs(department) {
    const kpiData = {
        'driver': [
            { name: 'On-time Delivery Rate', target: '95%', weight: 30, id: 1 },
            { name: 'Trips Completed', target: '100', weight: 20, id: 2 },
            { name: 'Safety Compliance', target: '0 violations', weight: 20, id: 3 },
            { name: 'Fuel Efficiency', target: '8 km/L', weight: 10, id: 4 },
            { name: 'Attendance', target: '100%', weight: 20, id: 5 }
        ],
        'warehouse': [
            { name: 'Picking Accuracy', target: '98%', weight: 30, id: 6 },
            { name: 'Processing Speed', target: '90%', weight: 25, id: 7 },
            { name: 'Inventory Accuracy', target: '99%', weight: 20, id: 8 },
            { name: 'Safety Compliance', target: '100%', weight: 15, id: 9 },
            { name: 'Attendance', target: '100%', weight: 10, id: 10 }
        ],
        'logistics': [
            { name: 'Route Optimization', target: '90%', weight: 25, id: 11 },
            { name: 'Dispatch Accuracy', target: '98%', weight: 25, id: 12 },
            { name: 'On-time Delivery', target: '95%', weight: 20, id: 13 },
            { name: 'Customer Satisfaction', target: '4.5/5', weight: 15, id: 14 },
            { name: 'Documentation Accuracy', target: '98%', weight: 15, id: 15 }
        ],
        'admin': [
            { name: 'Task Completion Rate', target: '95%', weight: 30, id: 16 },
            { name: 'Accuracy of Work', target: '98%', weight: 25, id: 17 },
            { name: 'Response Time', target: '< 24hrs', weight: 20, id: 18 },
            { name: 'Attendance', target: '100%', weight: 15, id: 19 },
            { name: 'Initiative', target: 'Proactive', weight: 10, id: 20 }
        ],
        'management': [
            { name: 'Team Performance', target: '85%', weight: 30, id: 21 },
            { name: 'Goal Achievement', target: '90%', weight: 25, id: 22 },
            { name: 'Employee Satisfaction', target: '4/5', weight: 20, id: 23 },
            { name: 'Budget Adherence', target: 'Within 5%', weight: 15, id: 24 },
            { name: 'Process Improvements', target: '2 per quarter', weight: 10, id: 25 }
        ]
    };
    
    const kpis = kpiData[department] || kpiData['driver'];
    const tbody = document.getElementById('kpi_rows');
    tbody.innerHTML = '';
    
    kpis.forEach(kpi => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <strong>${kpi.name}</strong>
                <input type="hidden" name="kpi_weight[${kpi.id}]" value="${kpi.weight}">
            </td>
            <td style="width: 80px;"><span style="font-weight: 600;">${kpi.target}</span></td>
            <td style="width: 60px;">${kpi.weight}%</td>
            <td style="width: 120px;">
                <select name="kpi_rating[${kpi.id}]" class="kpi-rating" onchange="updateScores()">
                    <option value="">Select</option>
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

function loadExistingData(metrics, review) {
    // Set KPI ratings
    if (metrics.kpis) {
        Object.keys(metrics.kpis).forEach(kpiId => {
            const select = document.querySelector(`select[name="kpi_rating[${kpiId}]"]`);
            if (select) select.value = metrics.kpis[kpiId];
        });
    }
    
    if (metrics.kpi_comments) {
        Object.keys(metrics.kpi_comments).forEach(kpiId => {
            const textarea = document.querySelector(`textarea[name="kpi_comment[${kpiId}]"]`);
            if (textarea) textarea.value = metrics.kpi_comments[kpiId];
        });
    }
    
    // Set behavioral ratings
    if (metrics.behavioral) {
        Object.keys(metrics.behavioral).forEach(criteria => {
            const select = document.querySelector(`select[name="behavioral_rating[${criteria}]"]`);
            if (select) select.value = metrics.behavioral[criteria];
        });
    }
    
    if (metrics.behavioral_comments) {
        Object.keys(metrics.behavioral_comments).forEach(criteria => {
            const textarea = document.querySelector(`textarea[name="behavioral_comment[${criteria}]"]`);
            if (textarea) textarea.value = metrics.behavioral_comments[criteria];
        });
    }
    
    // Set comments
    if (review.strengths) document.querySelector('textarea[name="strengths"]').value = review.strengths;
    if (review.improvements) document.querySelector('textarea[name="improvements"]').value = review.improvements;
    if (metrics.achievements) document.querySelector('textarea[name="achievements"]').value = metrics.achievements;
    if (review.comments) document.querySelector('textarea[name="overall_comments"]').value = review.comments;
    
    // Set recommendation
    if (metrics.recommendation) {
        selectRecommendation(metrics.recommendation);
        
        if (metrics.recommendation === 'salary') {
            document.querySelector('input[name="proposed_increase"]').value = metrics.proposed_increase || '';
            document.querySelector('textarea[name="salary_justification"]').value = metrics.salary_justification || '';
        } else if (metrics.recommendation === 'promotion') {
            document.querySelector('input[name="proposed_position"]').value = metrics.proposed_position || '';
            document.querySelector('textarea[name="promotion_justification"]').value = metrics.promotion_justification || '';
        } else if (metrics.recommendation === 'pip') {
            document.querySelector('select[name="pip_duration"]').value = metrics.pip_duration || 30;
            document.querySelector('textarea[name="pip_reason"]').value = metrics.pip_reason || '';
        }
    }
    
    updateScores();
}

function resetForm() {
    // Reset all selects
    document.querySelectorAll('select').forEach(select => select.value = '');
    document.querySelectorAll('textarea').forEach(textarea => textarea.value = '');
    
    // Reset recommendation
    document.querySelectorAll('.recommendation-option').forEach(opt => opt.classList.remove('selected'));
    document.getElementById('selected_recommendation').value = '';
    document.getElementById('salary_fields').style.display = 'none';
    document.getElementById('promotion_fields').style.display = 'none';
    document.getElementById('pip_fields').style.display = 'none';
    
    updateScores();
}

function updateScores() {
    // Calculate KPI score
    const kpiRatings = document.querySelectorAll('.kpi-rating');
    let kpiTotal = 0;
    let kpiWeightTotal = 0;
    
    kpiRatings.forEach(select => {
        const row = select.closest('tr');
        const weightInput = row.querySelector('input[name^="kpi_weight"]');
        const weight = weightInput ? parseInt(weightInput.value) : 0;
        
        if (select.value) {
            kpiTotal += parseInt(select.value) * weight;
            kpiWeightTotal += weight;
        }
    });
    
    const kpiScore = kpiWeightTotal > 0 ? (kpiTotal / kpiWeightTotal).toFixed(1) : 0;
    
    // Calculate Behavioral score
    const behavioralRatings = document.querySelectorAll('.behavioral-rating');
    let behavioralTotal = 0;
    let behavioralWeightTotal = 0;
    
    behavioralRatings.forEach(select => {
        const row = select.closest('tr');
        const weightInput = row.querySelector('input[name^="behavioral_weight"]');
        const weight = weightInput ? parseInt(weightInput.value) : 0;
        
        if (select.value) {
            behavioralTotal += parseInt(select.value) * weight;
            behavioralWeightTotal += weight;
        }
    });
    
    const behavioralScore = behavioralWeightTotal > 0 ? (behavioralTotal / behavioralWeightTotal).toFixed(1) : 0;
    
    // Overall rating (average of both)
    const overallRating = ((parseFloat(kpiScore) + parseFloat(behavioralScore)) / 2).toFixed(1);
    const percentage = overallRating > 0 ? ((overallRating / 5) * 100).toFixed(0) : 0;
    
    // Determine category
    let category = 'Not Rated';
    let categoryColor = '#7f8c8d';
    let categoryBg = '#7f8c8d20';
    
    if (overallRating > 0) {
        const pct = parseFloat(percentage);
        if (pct >= 90) { category = 'Outstanding'; categoryColor = '#27ae60'; categoryBg = '#27ae6020'; }
        else if (pct >= 80) { category = 'Very Good'; categoryColor = '#2ecc71'; categoryBg = '#2ecc7120'; }
        else if (pct >= 70) { category = 'Satisfactory'; categoryColor = '#f39c12'; categoryBg = '#f39c1220'; }
        else if (pct >= 60) { category = 'Needs Improvement'; categoryColor = '#e67e22'; categoryBg = '#e67e2220'; }
        else { category = 'Poor'; categoryColor = '#e74c3c'; categoryBg = '#e74c3c20'; }
    }
    
    // Update display
    document.getElementById('kpi_score').textContent = kpiScore;
    document.getElementById('behavioral_score').textContent = behavioralScore;
    document.getElementById('overall_rating').textContent = overallRating + ' / 5';
    document.getElementById('percentage').textContent = percentage + '%';
    document.getElementById('final_score').textContent = overallRating;
    
    const categorySpan = document.getElementById('rating_category');
    categorySpan.textContent = category;
    categorySpan.style.background = categoryBg;
    categorySpan.style.color = categoryColor;
}

function selectRecommendation(type) {
    document.querySelectorAll('.recommendation-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    
    // Find and select the matching option
    const options = document.querySelectorAll('.recommendation-option');
    options.forEach(opt => {
        if (type === 'none' && opt.textContent.includes('No Action')) opt.classList.add('selected');
        else if (type === 'salary' && opt.textContent.includes('Salary')) opt.classList.add('selected');
        else if (type === 'promotion' && opt.textContent.includes('Promotion')) opt.classList.add('selected');
        else if (type === 'training' && opt.textContent.includes('Training')) opt.classList.add('selected');
        else if (type === 'pip' && opt.textContent.includes('Performance Improvement')) opt.classList.add('selected');
        else if (type === 'disciplinary' && opt.textContent.includes('Disciplinary')) opt.classList.add('selected');
    });
    
    document.getElementById('selected_recommendation').value = type;
    
    // Show relevant fields
    document.getElementById('salary_fields').style.display = type === 'salary' ? 'block' : 'none';
    document.getElementById('promotion_fields').style.display = type === 'promotion' ? 'block' : 'none';
    document.getElementById('pip_fields').style.display = type === 'pip' ? 'block' : 'none';
}

function saveDraft() {
    document.getElementById('form_action').value = 'save_draft';
    document.getElementById('reviewForm').submit();
}

function submitReview() {
    if (!document.getElementById('selected_recommendation').value) {
        alert('Please select a recommendation');
        return;
    }
    
    if (confirm('Are you sure you want to submit this review? You will not be able to edit it after submission.')) {
        document.getElementById('form_action').value = 'submit_review';
        document.getElementById('reviewForm').submit();
    }
}

function viewReview(id) {
    alert('View review details for ID: ' + id);
}

// Bulk Review Modal
function openBulkReviewModal() {
    document.getElementById('bulkReviewModal').classList.add('active');
}

function closeBulkReviewModal() {
    document.getElementById('bulkReviewModal').classList.remove('active');
}

function selectAllBulk() {
    document.querySelectorAll('#bulkReviewModal input[type="checkbox"]').forEach(cb => cb.checked = true);
}

function deselectAllBulk() {
    document.querySelectorAll('#bulkReviewModal input[type="checkbox"]').forEach(cb => cb.checked = false);
}

// Reminder Modal
function openReminderModal() {
    document.getElementById('reminderModal').classList.add('active');
}

function closeReminderModal() {
    document.getElementById('reminderModal').classList.remove('active');
}

// Export Modal
function openExportModal() {
    document.getElementById('exportModal').classList.add('active');
}

function closeExportModal() {
    document.getElementById('exportModal').classList.remove('active');
}

// Add event listeners for real-time score updates
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('kpi-rating') || e.target.classList.contains('behavioral-rating')) {
        updateScores();
    }
});

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
ob_end_flush();
?>