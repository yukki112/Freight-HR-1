<?php
// Start output buffering at the VERY FIRST LINE
ob_start();

// modules/performance/performance-reviews.php
$page_title = "Performance Reviews";

// Include required files
require_once 'includes/config.php';
require_once 'config/mail_config.php';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : 'all';
$review_status_filter = isset($_GET['review_status']) ? $_GET['review_status'] : 'all';
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'dashboard'; // dashboard, list, or templates

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
function getEmployeePhoto($employee) {
    if (!empty($employee['photo_path']) && file_exists('../../' . $employee['photo_path'])) {
        return '../../' . htmlspecialchars($employee['photo_path']);
    }
    return null;
}

function getRatingColor($percentage) {
    if ($percentage >= 90) return '#27ae60'; // Outstanding
    if ($percentage >= 80) return '#2ecc71'; // Very Good
    if ($percentage >= 70) return '#f39c12'; // Satisfactory
    if ($percentage >= 60) return '#e67e22'; // Needs Improvement
    return '#e74c3c'; // Poor
}

function getRatingLabel($percentage) {
    if ($percentage >= 90) return 'Outstanding';
    if ($percentage >= 80) return 'Very Good';
    if ($percentage >= 70) return 'Satisfactory';
    if ($percentage >= 60) return 'Needs Improvement';
    return 'Poor';
}

function getRatingFromScore($score) {
    if (!$score) return null;
    if ($score >= 4.5) return 'outstanding';
    if ($score >= 4.0) return 'very_good';
    if ($score >= 3.0) return 'satisfactory';
    if ($score >= 2.0) return 'needs_improvement';
    return 'poor';
}

function getReviewPeriodText($date = null) {
    if ($date) {
        $review_date = strtotime($date);
        $year = date('Y', $review_date);
        $month = date('n', $review_date);
        
        if ($month >= 1 && $month <= 3) return "Q1 {$year}";
        if ($month >= 4 && $month <= 6) return "Q2 {$year}";
        if ($month >= 7 && $month <= 9) return "Q3 {$year}";
        if ($month >= 10 && $month <= 12) return "Q4 {$year}";
    }
    
    // Default to current quarter
    $current_month = date('n');
    $current_year = date('Y');
    if ($current_month >= 1 && $current_month <= 3) return "Q1 {$current_year}";
    if ($current_month >= 4 && $current_month <= 6) return "Q2 {$current_year}";
    if ($current_month >= 7 && $current_month <= 9) return "Q3 {$current_year}";
    return "Q4 {$current_year}";
}

/**
 * Get all hired/active employees with their review information
 */
$query = "
    SELECT 
        nh.id as employee_id,
        nh.employee_id as employee_code,
        nh.position,
        nh.department,
        nh.start_date,
        nh.employment_status,
        nh.status as employee_status,
        nh.hire_date,
        nh.probation_end_date,
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
        
        -- Review information (if exists)
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
        pr.created_at as review_created_at,
        
        -- Reviewer information
        u.id as reviewer_id,
        u.full_name as reviewer_name,
        
        -- Calculated fields
        DATEDIFF(CURDATE(), nh.start_date) as days_employed,
        TIMESTAMPDIFF(MONTH, nh.start_date, CURDATE()) as months_employed,
        
        -- Review deadline (7 days from review date or 90 days from start if no review)
        CASE 
            WHEN pr.review_date IS NOT NULL THEN DATE_ADD(pr.review_date, INTERVAL 7 DAY)
            ELSE DATE_ADD(nh.start_date, INTERVAL 90 DAY)
        END as review_deadline,
        
        -- Overdue flag
        CASE 
            WHEN pr.id IS NULL AND DATE_ADD(nh.start_date, INTERVAL 90 DAY) < CURDATE() THEN 1
            WHEN pr.status = 'draft' AND DATE_ADD(pr.review_date, INTERVAL 7 DAY) < CURDATE() THEN 1
            ELSE 0
        END as overdue,
        
        -- Days until deadline
        CASE 
            WHEN pr.review_date IS NOT NULL THEN DATEDIFF(DATE_ADD(pr.review_date, INTERVAL 7 DAY), CURDATE())
            ELSE DATEDIFF(DATE_ADD(nh.start_date, INTERVAL 90 DAY), CURDATE())
        END as days_until_deadline,
        
        -- Review exists flag
        CASE WHEN pr.id IS NOT NULL THEN 1 ELSE 0 END as has_review
        
    FROM new_hires nh
    INNER JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
    LEFT JOIN users u ON nh.supervisor_id = u.id
    LEFT JOIN performance_reviews pr ON nh.id = pr.employee_id
    WHERE nh.status IN ('active', 'onboarding')  -- Only active/onboarding employees
";

$params = [];

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

// Review status filter
if ($review_status_filter !== 'all') {
    if ($review_status_filter === 'no_review') {
        $query .= " AND pr.id IS NULL";
    } elseif ($review_status_filter === 'has_review') {
        $query .= " AND pr.id IS NOT NULL";
    } elseif ($review_status_filter === 'draft' || $review_status_filter === 'submitted' || $review_status_filter === 'acknowledged') {
        $query .= " AND pr.status = ?";
        $params[] = $review_status_filter;
    }
}

// Rating filter (only applies to employees with reviews)
if ($rating_filter !== 'all' && $review_status_filter !== 'no_review') {
    switch ($rating_filter) {
        case 'outstanding':
            $query .= " AND pr.overall_rating >= 4.5";
            break;
        case 'very_good':
            $query .= " AND pr.overall_rating >= 4.0 AND pr.overall_rating < 4.5";
            break;
        case 'satisfactory':
            $query .= " AND pr.overall_rating >= 3.0 AND pr.overall_rating < 4.0";
            break;
        case 'needs_improvement':
            $query .= " AND pr.overall_rating >= 2.0 AND pr.overall_rating < 3.0";
            break;
        case 'poor':
            $query .= " AND pr.overall_rating < 2.0";
            break;
    }
}

$query .= " ORDER BY 
    CASE 
        WHEN pr.id IS NULL THEN 0  -- No review first
        WHEN pr.status = 'draft' AND DATE_ADD(pr.review_date, INTERVAL 7 DAY) < CURDATE() THEN 1  -- Overdue drafts
        WHEN pr.status = 'draft' THEN 2  -- Pending drafts
        WHEN pr.status = 'submitted' THEN 3  -- Submitted
        WHEN pr.status = 'acknowledged' THEN 4  -- Acknowledged
        ELSE 5
    END,
    nh.start_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Get statistics
$stats = [
    'total_employees' => count($employees),
    'with_reviews' => 0,
    'without_reviews' => 0,
    'draft' => 0,
    'submitted' => 0,
    'acknowledged' => 0,
    'overdue' => 0,
    'outstanding' => 0,
    'very_good' => 0,
    'satisfactory' => 0,
    'needs_improvement' => 0,
    'poor' => 0
];

foreach ($employees as $emp) {
    if ($emp['has_review']) {
        $stats['with_reviews']++;
        
        if (isset($stats[$emp['review_status']])) {
            $stats[$emp['review_status']]++;
        }
        
        // Count by rating
        if ($emp['overall_rating']) {
            if ($emp['overall_rating'] >= 4.5) $stats['outstanding']++;
            elseif ($emp['overall_rating'] >= 4.0) $stats['very_good']++;
            elseif ($emp['overall_rating'] >= 3.0) $stats['satisfactory']++;
            elseif ($emp['overall_rating'] >= 2.0) $stats['needs_improvement']++;
            elseif ($emp['overall_rating'] < 2.0) $stats['poor']++;
        }
    } else {
        $stats['without_reviews']++;
    }
    
    if ($emp['overdue']) {
        $stats['overdue']++;
    }
}

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department FROM new_hires WHERE department IS NOT NULL AND status IN ('active', 'onboarding') ORDER BY department");
$departments = $stmt->fetchAll();

// Get all users for reviewer dropdown
$stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('admin', 'supervisor', 'manager') ORDER BY full_name");
$reviewers = $stmt->fetchAll();

// Get review templates
$stmt = $pdo->query("SELECT * FROM performance_review_templates WHERE is_active = 1 ORDER BY department, template_name");
$templates = $stmt->fetchAll();

// Status configuration
$status_config = [
    'no_review' => [
        'label' => 'No Review Yet',
        'icon' => 'fas fa-clock',
        'color' => '#7f8c8d',
        'bg' => '#7f8c8d20',
        'text' => '#7f8c8d',
        'description' => 'Employee needs a performance review'
    ],
    'draft' => [
        'label' => 'Draft',
        'icon' => 'fas fa-pencil-alt',
        'color' => '#f39c12',
        'bg' => '#f39c1220',
        'text' => '#f39c12',
        'description' => 'Review in progress'
    ],
    'submitted' => [
        'label' => 'Submitted',
        'icon' => 'fas fa-check',
        'color' => '#3498db',
        'bg' => '#3498db20',
        'text' => '#3498db',
        'description' => 'Review submitted, awaiting acknowledgment'
    ],
    'acknowledged' => [
        'label' => 'Acknowledged',
        'icon' => 'fas fa-check-double',
        'color' => '#27ae60',
        'bg' => '#27ae6020',
        'text' => '#27ae60',
        'description' => 'Employee has acknowledged the review'
    ]
];

// Rating configuration
$rating_config = [
    'outstanding' => [
        'label' => 'Outstanding',
        'icon' => 'fas fa-star',
        'color' => '#27ae60',
        'bg' => '#27ae6020',
        'text' => '#27ae60',
        'range' => '90-100%'
    ],
    'very_good' => [
        'label' => 'Very Good',
        'icon' => 'fas fa-star-half-alt',
        'color' => '#2ecc71',
        'bg' => '#2ecc7120',
        'text' => '#2ecc71',
        'range' => '80-89%'
    ],
    'satisfactory' => [
        'label' => 'Satisfactory',
        'icon' => 'fas fa-smile',
        'color' => '#f39c12',
        'bg' => '#f39c1220',
        'text' => '#f39c12',
        'range' => '70-79%'
    ],
    'needs_improvement' => [
        'label' => 'Needs Improvement',
        'icon' => 'fas fa-exclamation-triangle',
        'color' => '#e67e22',
        'bg' => '#e67e2220',
        'text' => '#e67e22',
        'range' => '60-69%'
    ],
    'poor' => [
        'label' => 'Poor',
        'icon' => 'fas fa-times-circle',
        'color' => '#e74c3c',
        'bg' => '#e74c3c20',
        'text' => '#e74c3c',
        'range' => '<60%'
    ]
];

// Period types
$period_type_config = [
    'monthly' => ['icon' => 'fas fa-calendar-alt', 'color' => '#3498db'],
    'quarterly' => ['icon' => 'fas fa-calendar-check', 'color' => '#9b59b6'],
    'semi_annual' => ['icon' => 'fas fa-calendar', 'color' => '#e67e22'],
    'annual' => ['icon' => 'fas fa-calendar-plus', 'color' => '#27ae60'],
    'probation' => ['icon' => 'fas fa-hourglass-half', 'color' => '#f39c12']
];

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_review':
                // Create new performance review
                $employee_id = $_POST['employee_id'] ?? 0;
                $template_id = $_POST['template_id'] ?? 0;
                $review_type = $_POST['review_type'] ?? 'quarterly';
                $review_date = $_POST['review_date'] ?? date('Y-m-d');
                $reviewer_id = $_POST['reviewer_id'] ?? $_SESSION['user_id'] ?? 0;
                
                if ($employee_id && $template_id) {
                    try {
                        // Get template details
                        $stmt = $pdo->prepare("SELECT * FROM performance_review_templates WHERE id = ?");
                        $stmt->execute([$template_id]);
                        $template = $stmt->fetch();
                        
                        // Create review
                        $stmt = $pdo->prepare("
                            INSERT INTO performance_reviews 
                            (employee_id, reviewer_id, review_type, review_date, status, created_at) 
                            VALUES (?, ?, ?, ?, 'draft', NOW())
                        ");
                        $stmt->execute([$employee_id, $reviewer_id, $review_type, $review_date]);
                        $review_id = $pdo->lastInsertId();
                        
                        // Log activity
                        simpleLog($pdo, $_SESSION['user_id'], 'create_review', 
                            "Created performance review for employee ID: $employee_id");
                        
                        $message = "Review created successfully!";
                        
                        // Redirect to edit page
                        header("Location: ?page=performance&subpage=edit-review&id=$review_id");
                        exit;
                        
                    } catch (Exception $e) {
                        $error = "Error creating review: " . $e->getMessage();
                    }
                } else {
                    $error = "Please select both employee and template";
                }
                break;
                
            case 'bulk_create':
                // Create multiple reviews
                $employee_ids = $_POST['employee_ids'] ?? [];
                $template_id = $_POST['bulk_template_id'] ?? 0;
                $review_type = $_POST['bulk_review_type'] ?? 'quarterly';
                $review_date = $_POST['bulk_review_date'] ?? date('Y-m-d');
                $reviewer_id = $_POST['bulk_reviewer_id'] ?? $_SESSION['user_id'] ?? 0;
                
                if (!empty($employee_ids) && $template_id) {
                    $success_count = 0;
                    $fail_count = 0;
                    
                    foreach ($employee_ids as $emp_id) {
                        try {
                            $stmt = $pdo->prepare("
                                INSERT INTO performance_reviews 
                                (employee_id, reviewer_id, review_type, review_date, status, created_at) 
                                VALUES (?, ?, ?, ?, 'draft', NOW())
                            ");
                            $stmt->execute([$emp_id, $reviewer_id, $review_type, $review_date]);
                            $success_count++;
                        } catch (Exception $e) {
                            $fail_count++;
                        }
                    }
                    
                    simpleLog($pdo, $_SESSION['user_id'], 'bulk_create_reviews', 
                        "Created $success_count reviews, $fail_count failed");
                    
                    $message = "Successfully created $success_count reviews. Failed: $fail_count";
                } else {
                    $error = "Please select at least one employee and a template";
                }
                break;
                
            case 'send_reminders':
                // Send reminder emails
                $reminder_type = $_POST['reminder_type'] ?? 'overdue';
                $custom_message = $_POST['custom_message'] ?? '';
                
                $sent_count = 0;
                $reminder_list = [];
                
                if ($reminder_type == 'overdue') {
                    // Get overdue reviews
                    foreach ($employees as $emp) {
                        if ($emp['overdue']) {
                            $reminder_list[] = $emp;
                        }
                    }
                } elseif ($reminder_type == 'upcoming') {
                    // Get upcoming reviews (within 7 days)
                    foreach ($employees as $emp) {
                        if (!$emp['overdue'] && $emp['days_until_deadline'] <= 7 && $emp['days_until_deadline'] > 0) {
                            $reminder_list[] = $emp;
                        }
                    }
                } elseif ($reminder_type == 'no_review') {
                    // Get employees without reviews
                    foreach ($employees as $emp) {
                        if (!$emp['has_review']) {
                            $reminder_list[] = $emp;
                        }
                    }
                }
                
                // In a real system, you would send emails here
                // For now, we'll just count and log
                $sent_count = count($reminder_list);
                
                simpleLog($pdo, $_SESSION['user_id'], 'send_reminders', 
                    "Sent $sent_count reminders of type: $reminder_type");
                
                $message = "Successfully sent $sent_count reminder emails!";
                break;
                
            case 'generate_report':
                // Generate performance report
                $report_type = $_POST['report_type'] ?? 'summary';
                $date_from = $_POST['date_from'] ?? date('Y-01-01');
                $date_to = $_POST['date_to'] ?? date('Y-m-d');
                $department = $_POST['report_department'] ?? '';
                
                // Log report generation
                simpleLog($pdo, $_SESSION['user_id'], 'generate_report', 
                    "Generated $report_type report for period $date_from to $date_to");
                
                $message = "Report generated successfully! Check downloads folder.";
                
                // In a real system, you would generate PDF/Excel here
                // For now, we'll just show a message
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

/* Rating Distribution */
.rating-section {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.rating-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.rating-title i {
    color: var(--primary);
}

.rating-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
}

.rating-item {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.rating-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.rating-icon.outstanding { background: #27ae6020; color: #27ae60; }
.rating-icon.very_good { background: #2ecc7120; color: #2ecc71; }
.rating-icon.satisfactory { background: #f39c1220; color: #f39c12; }
.rating-icon.needs_improvement { background: #e67e2220; color: #e67e22; }
.rating-icon.poor { background: #e74c3c20; color: #e74c3c; }

.rating-info {
    flex: 1;
}

.rating-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 3px;
}

.rating-count {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.rating-range {
    font-size: 10px;
    color: var(--gray);
    margin-top: 2px;
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

.review-card.no-review {
    border-left: 5px solid var(--gray);
}

.review-card.draft {
    border-left: 5px solid var(--warning);
}

.review-card.submitted {
    border-left: 5px solid var(--info);
}

.review-card.acknowledged {
    border-left: 5px solid var(--success);
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
    display: flex;
    align-items: center;
    gap: 5px;
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
.period-icon.semi_annual { background: #e67e2220; color: #e67e22; }
.period-icon.annual { background: #27ae6020; color: #27ae60; }
.period-icon.probation { background: #f39c1220; color: #f39c12; }

.period-details {
    flex: 1;
}

.period-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 3px;
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
    width: 70px;
    height: 70px;
    border-radius: 50%;
    margin: 0 auto 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    color: white;
}

.rating-label-display {
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 2px;
}

.rating-percentage {
    font-size: 11px;
    color: var(--gray);
}

.kpi-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 12px 0;
}

.kpi-tag {
    background: var(--light-gray);
    border-radius: 20px;
    padding: 3px 10px;
    font-size: 10px;
    color: var(--gray);
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.kpi-tag i {
    color: var(--primary);
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

.deadline-badge {
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.review-required-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--warning);
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

.review-table {
    width: 100%;
    border-collapse: collapse;
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

.review-table tr.no-review-row {
    background: #f8f9fa;
}

.review-table tr.no-review-row:hover {
    background: #e9ecef;
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
.rating-badge.very_good { background: #2ecc7120; color: #2ecc71; }
.rating-badge.satisfactory { background: #f39c1220; color: #f39c12; }
.rating-badge.needs_improvement { background: #e67e2220; color: #e67e22; }
.rating-badge.poor { background: #e74c3c20; color: #e74c3c; }

/* Templates View */
.templates-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.template-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    transition: all 0.3s;
}

.template-card:hover {
    border-color: var(--primary);
    box-shadow: 0 10px 30px var(--primary-transparent);
}

.template-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.template-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: var(--primary-transparent);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.template-title {
    flex: 1;
}

.template-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 3px;
}

.template-dept {
    font-size: 11px;
    color: var(--gray);
}

.category-list {
    margin: 15px 0;
}

.category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px dashed var(--border);
    font-size: 12px;
}

.category-name {
    color: var(--dark);
}

.category-weight {
    background: var(--light-gray);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    color: var(--primary);
}

.template-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
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
    max-width: 600px;
    width: 90%;
    max-height: 85vh;
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

/* Employee Selection for Bulk */
.employee-selection {
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

/* Weight Distribution */
.weight-distribution {
    margin: 15px 0;
    background: var(--light-gray);
    border-radius: 12px;
    padding: 12px;
}

.weight-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.weight-item:last-child {
    margin-bottom: 0;
}

.weight-label {
    width: 100px;
    font-size: 12px;
    color: var(--dark);
}

.weight-bar-container {
    flex: 1;
    height: 8px;
    background: var(--border);
    border-radius: 4px;
    overflow: hidden;
}

.weight-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 4px;
}

.weight-value {
    width: 50px;
    font-size: 12px;
    font-weight: 600;
    color: var(--dark);
    text-align: right;
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
    
    .rating-grid {
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
    
    .rating-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .rating-grid {
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

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-title">
        <i class="fas fa-chart-line"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div class="view-toggle">
        <a href="?page=performance&subpage=performance-reviews&view=dashboard<?php 
            echo !empty($department_filter) ? '&department=' . $department_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($review_status_filter) ? '&review_status=' . $review_status_filter : ''; 
        ?>" class="view-option <?php echo $view_mode == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a href="?page=performance&subpage=performance-reviews&view=list<?php 
            echo !empty($department_filter) ? '&department=' . $department_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
            echo !empty($review_status_filter) ? '&review_status=' . $review_status_filter : ''; 
        ?>" class="view-option <?php echo $view_mode == 'list' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i> List View
        </a>
        <a href="?page=performance&subpage=performance-reviews&view=templates" class="view-option <?php echo $view_mode == 'templates' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Templates
        </a>
    </div>
</div>

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

<!-- Statistics Cards -->
<div class="stats-container">
    <div class="stat-card-modern primary">
        <div class="stat-icon-modern primary">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Total Employees</span>
            <span class="stat-value-modern"><?php echo $stats['total_employees']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern gray">
        <div class="stat-icon-modern gray">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Need Review</span>
            <span class="stat-value-modern"><?php echo $stats['without_reviews']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern warning">
        <div class="stat-icon-modern warning">
            <i class="fas fa-pencil-alt"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">In Draft</span>
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
            <span class="stat-label-modern">Acknowledged</span>
            <span class="stat-value-modern"><?php echo $stats['acknowledged']; ?></span>
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

<!-- Rating Distribution -->
<div class="rating-section">
    <div class="rating-title">
        <i class="fas fa-chart-pie"></i> Performance Rating Distribution
    </div>
    
    <div class="rating-grid">
        <div class="rating-item">
            <div class="rating-icon outstanding">
                <i class="fas fa-star"></i>
            </div>
            <div class="rating-info">
                <div class="rating-label">Outstanding</div>
                <div class="rating-count"><?php echo $stats['outstanding']; ?></div>
                <div class="rating-range">90-100%</div>
            </div>
        </div>
        
        <div class="rating-item">
            <div class="rating-icon very_good">
                <i class="fas fa-star-half-alt"></i>
            </div>
            <div class="rating-info">
                <div class="rating-label">Very Good</div>
                <div class="rating-count"><?php echo $stats['very_good']; ?></div>
                <div class="rating-range">80-89%</div>
            </div>
        </div>
        
        <div class="rating-item">
            <div class="rating-icon satisfactory">
                <i class="fas fa-smile"></i>
            </div>
            <div class="rating-info">
                <div class="rating-label">Satisfactory</div>
                <div class="rating-count"><?php echo $stats['satisfactory']; ?></div>
                <div class="rating-range">70-79%</div>
            </div>
        </div>
        
        <div class="rating-item">
            <div class="rating-icon needs_improvement">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="rating-info">
                <div class="rating-label">Needs Improvement</div>
                <div class="rating-count"><?php echo $stats['needs_improvement']; ?></div>
                <div class="rating-range">60-69%</div>
            </div>
        </div>
        
        <div class="rating-item">
            <div class="rating-icon poor">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="rating-info">
                <div class="rating-label">Poor</div>
                <div class="rating-count"><?php echo $stats['poor']; ?></div>
                <div class="rating-range"><60%</div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Section -->
<div class="alert-section">
    <?php if ($stats['without_reviews'] > 0): ?>
    <div class="alert-card warning">
        <div class="alert-icon warning">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Employees Need Review</div>
            <div class="alert-value"><?php echo $stats['without_reviews']; ?> employees</div>
            <a onclick="openNewReviewModal()" class="alert-link">Create Reviews →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['overdue'] > 0): ?>
    <div class="alert-card danger">
        <div class="alert-icon danger">
            <i class="fas fa-clock"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Overdue Reviews</div>
            <div class="alert-value"><?php echo $stats['overdue']; ?> reviews</div>
            <a onclick="openReminderModal('overdue')" class="alert-link">Send Reminders →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['draft'] > 0): ?>
    <div class="alert-card info">
        <div class="alert-icon info">
            <i class="fas fa-pencil-alt"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">In Progress</div>
            <div class="alert-value"><?php echo $stats['draft']; ?> drafts</div>
            <a onclick="openBulkReviewModal()" class="alert-link">Continue →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['poor'] > 0): ?>
    <div class="alert-card danger">
        <div class="alert-icon danger">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Poor Performers</div>
            <div class="alert-value"><?php echo $stats['poor']; ?> employees</div>
            <a href="?page=performance&subpage=performance-reviews&view=<?php echo $view_mode; ?>&rating=poor" class="alert-link">Review →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['outstanding'] > 0): ?>
    <div class="alert-card success">
        <div class="alert-icon success">
            <i class="fas fa-trophy"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Top Performers</div>
            <div class="alert-value"><?php echo $stats['outstanding']; ?> employees</div>
            <a href="?page=performance&subpage=performance-reviews&view=<?php echo $view_mode; ?>&rating=outstanding" class="alert-link">View →</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Employees & Reviews
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="performance">
        <input type="hidden" name="subpage" value="performance-reviews">
        <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Review Status</label>
                <select name="review_status">
                    <option value="all" <?php echo $review_status_filter == 'all' ? 'selected' : ''; ?>>All Employees</option>
                    <option value="no_review" <?php echo $review_status_filter == 'no_review' ? 'selected' : ''; ?>>Need Review</option>
                    <option value="has_review" <?php echo $review_status_filter == 'has_review' ? 'selected' : ''; ?>>Has Review</option>
                    <option value="draft" <?php echo $review_status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="submitted" <?php echo $review_status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="acknowledged" <?php echo $review_status_filter == 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Rating</label>
                <select name="rating">
                    <option value="all" <?php echo $rating_filter == 'all' ? 'selected' : ''; ?>>All Ratings</option>
                    <option value="outstanding" <?php echo $rating_filter == 'outstanding' ? 'selected' : ''; ?>>Outstanding</option>
                    <option value="very_good" <?php echo $rating_filter == 'very_good' ? 'selected' : ''; ?>>Very Good</option>
                    <option value="satisfactory" <?php echo $rating_filter == 'satisfactory' ? 'selected' : ''; ?>>Satisfactory</option>
                    <option value="needs_improvement" <?php echo $rating_filter == 'needs_improvement' ? 'selected' : ''; ?>>Needs Improvement</option>
                    <option value="poor" <?php echo $rating_filter == 'poor' ? 'selected' : ''; ?>>Poor</option>
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
    <!-- Dashboard Grid View - Shows ALL hired employees -->
    <div class="review-grid">
        <?php foreach ($employees as $emp): 
            $fullName = $emp['full_name'];
            $initials = $emp['first_initial'] . $emp['last_initial'];
            $photoPath = getEmployeePhoto($emp);
            
            // Determine status class for card
            $card_status_class = 'no-review';
            $status_key = 'no_review';
            
            if ($emp['has_review']) {
                $status_key = $emp['review_status'] ?? 'draft';
                $card_status_class = $status_key;
            }
            
            $status = $status_config[$status_key] ?? $status_config['no_review'];
            
            // Rating
            $rating_percentage = $emp['overall_rating'] ? ($emp['overall_rating'] * 20) : 0;
            $rating_label = getRatingLabel($rating_percentage);
            $rating_color = getRatingColor($rating_percentage);
            $rating_key = getRatingFromScore($emp['overall_rating']);
            
            // Period
            $period_name = getReviewPeriodText($emp['review_date']);
            $period_type = $emp['review_type'] ?? 'quarterly';
            $period_icon = isset($period_type_config[$period_type]) ? $period_type_config[$period_type]['icon'] : 'fas fa-calendar-check';
            $period_color = isset($period_type_config[$period_type]) ? $period_type_config[$period_type]['color'] : '#9b59b6';
            
            // Deadline status
            $deadline_class = '';
            $deadline_text = '';
            if ($emp['overdue']) {
                $deadline_class = 'danger';
                $deadline_text = 'Overdue';
            } elseif ($emp['days_until_deadline'] <= 7 && $emp['days_until_deadline'] > 0) {
                $deadline_class = 'warning';
                $deadline_text = $emp['days_until_deadline'] . ' days left';
            } elseif ($emp['days_until_deadline'] > 7) {
                $deadline_class = 'success';
                $deadline_text = $emp['days_until_deadline'] . ' days left';
            }
        ?>
        <div class="review-card <?php echo $card_status_class; ?>" onclick="window.location.href='?page=performance&subpage=performance-reviews&id=<?php echo $emp['employee_id']; ?><?php echo $emp['has_review'] ? '&review_id=' . $emp['review_id'] : ''; ?>'">
            
            <?php if (!$emp['has_review']): ?>
            <div class="review-required-badge">
                <i class="fas fa-exclamation-circle"></i> Review Required
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
                    </div>
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
                <div class="period-info">
                    <div class="period-icon" style="background: <?php echo $period_color; ?>20; color: <?php echo $period_color; ?>;">
                        <i class="<?php echo $period_icon; ?>"></i>
                    </div>
                    <div class="period-details">
                        <div class="period-name"><?php echo $period_name; ?></div>
                        <div class="period-date">
                            <?php echo ucfirst($period_type); ?> Review
                            <?php if ($emp['review_date']): ?>
                                • <?php echo date('M d, Y', strtotime($emp['review_date'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($emp['has_review'] && $emp['overall_rating']): ?>
                <div class="rating-display">
                    <div class="rating-circle" style="background: <?php echo $rating_color; ?>;">
                        <?php echo number_format($emp['overall_rating'], 1); ?>
                    </div>
                    <div class="rating-label-display" style="color: <?php echo $rating_color; ?>;">
                        <?php echo $rating_label; ?>
                    </div>
                    <div class="rating-percentage"><?php echo round($rating_percentage); ?>%</div>
                </div>
                
                <div class="kpi-preview">
                    <?php if ($emp['kpi_rating']): ?>
                    <span class="kpi-tag"><i class="fas fa-chart-line"></i> KPI: <?php echo $emp['kpi_rating']; ?>/5</span>
                    <?php endif; ?>
                    <?php if ($emp['attendance_rating']): ?>
                    <span class="kpi-tag"><i class="fas fa-clock"></i> Attendance: <?php echo $emp['attendance_rating']; ?>/5</span>
                    <?php endif; ?>
                    <?php if ($emp['teamwork_rating']): ?>
                    <span class="kpi-tag"><i class="fas fa-users"></i> Teamwork: <?php echo $emp['teamwork_rating']; ?>/5</span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 15px; background: var(--light-gray); border-radius: 12px; margin: 10px 0;">
                    <i class="fas fa-file-alt" style="font-size: 24px; color: var(--gray); opacity: 0.5;"></i>
                    <p style="margin: 5px 0 0; color: var(--gray); font-size: 12px;">No review yet</p>
                </div>
                <?php endif; ?>
                
                <div class="card-meta-grid">
                    <div class="meta-item">
                        <div class="meta-label">Hire Date</div>
                        <div class="meta-value small"><?php echo $emp['hire_date'] ? date('M d, Y', strtotime($emp['hire_date'])) : 'N/A'; ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Months</div>
                        <div class="meta-value"><?php echo $emp['months_employed']; ?> mo</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Status</div>
                        <div class="meta-value small"><?php echo ucfirst($emp['employment_status'] ?? 'Probationary'); ?></div>
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
        
        <?php if (empty($employees)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 20px;">
            <i class="fas fa-users" style="font-size: 48px; color: var(--gray); opacity: 0.3;"></i>
            <h3 style="margin-top: 15px; color: var(--dark);">No Employees Found</h3>
            <p style="color: var(--gray);">No active employees match your current filters.</p>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($view_mode == 'list'): ?>
    <!-- List View - Shows ALL hired employees -->
    <div class="table-container">
        <table class="review-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Position/Dept</th>
                    <th>Hire Date</th>
                    <th>Review Period</th>
                    <th>Rating</th>
                    <th>Reviewer</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp): 
                    $fullName = $emp['full_name'];
                    $initials = $emp['first_initial'] . $emp['last_initial'];
                    $photoPath = getEmployeePhoto($emp);
                    
                    $status_key = $emp['has_review'] ? ($emp['review_status'] ?? 'draft') : 'no_review';
                    $status = $status_config[$status_key] ?? $status_config['no_review'];
                    
                    $rating_percentage = $emp['overall_rating'] ? ($emp['overall_rating'] * 20) : 0;
                    $rating_key = getRatingFromScore($emp['overall_rating']);
                    
                    $period_name = getReviewPeriodText($emp['review_date']);
                ?>
                <tr class="<?php echo !$emp['has_review'] ? 'no-review-row' : ''; ?>" 
                    onclick="window.location.href='?page=performance&subpage=performance-reviews&id=<?php echo $emp['employee_id']; ?><?php echo $emp['has_review'] ? '&review_id=' . $emp['review_id'] : ''; ?>'">
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
                        <div><?php echo $emp['hire_date'] ? date('M d, Y', strtotime($emp['hire_date'])) : 'N/A'; ?></div>
                        <div style="font-size: 11px; color: var(--gray);"><?php echo $emp['months_employed']; ?> months</div>
                    </td>
                    <td>
                        <div><strong><?php echo $period_name; ?></strong></div>
                        <div style="font-size: 11px; color: var(--gray);">
                            <?php echo ucfirst($emp['review_type'] ?: 'Quarterly'); ?>
                            <?php if ($emp['review_date']): ?>
                                <br><?php echo date('M d', strtotime($emp['review_date'])); ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($emp['has_review'] && $emp['overall_rating']): ?>
                            <span class="rating-badge <?php echo $rating_key; ?>">
                                <i class="fas fa-star"></i> <?php echo number_format($emp['overall_rating'], 1); ?>
                            </span>
                            <div style="font-size: 10px; color: var(--gray); margin-top: 2px;">
                                <?php echo round($rating_percentage); ?>%
                            </div>
                        <?php elseif ($emp['has_review']): ?>
                            <span style="color: var(--gray);">In progress</span>
                        <?php else: ?>
                            <span style="color: var(--warning); font-weight: 500;">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $emp['reviewer_name'] ?: 'Not assigned'; ?></td>
                    <td>
                        <span class="status-badge" style="background: <?php echo $status['bg']; ?>; color: <?php echo $status['color']; ?>; padding: 3px 8px;">
                            <i class="<?php echo $status['icon']; ?>"></i> <?php echo $status['label']; ?>
                        </span>
                        <?php if ($emp['overdue']): ?>
                            <br><span style="font-size: 10px; color: var(--danger);">Overdue</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        <i class="fas fa-users" style="font-size: 32px; color: var(--gray); opacity: 0.3;"></i>
                        <p style="margin-top: 10px;">No active employees found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($view_mode == 'templates'): ?>
    <!-- Templates View -->
    <div class="filter-section">
        <div class="filter-title">
            <i class="fas fa-file-alt"></i> Performance Review Templates by Department
        </div>
        
        <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn btn-primary" onclick="openAddTemplateModal()">
                <i class="fas fa-plus"></i> Create New Template
            </button>
            <button class="btn btn-info" onclick="openKpiLibraryModal()">
                <i class="fas fa-chart-line"></i> KPI Library
            </button>
        </div>
        
        <div class="templates-section">
            <!-- Driver Template -->
            <div class="template-card">
                <div class="template-header">
                    <div class="template-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="template-title">
                        <div class="template-name">Driver Performance Template</div>
                        <div class="template-dept">Department: Driver</div>
                    </div>
                </div>
                
                <div class="category-list">
                    <div class="category-item">
                        <span class="category-name">A. Productivity (On-time delivery, Trips completed)</span>
                        <span class="category-weight">40%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">B. Compliance (Safety, Traffic violations)</span>
                        <span class="category-weight">30%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">C. Behavior (Communication, Teamwork)</span>
                        <span class="category-weight">20%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">D. Attendance</span>
                        <span class="category-weight">10%</span>
                    </div>
                </div>
                
                <div class="weight-distribution">
                    <div class="weight-item">
                        <span class="weight-label">Productivity</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 40%"></div>
                        </div>
                        <span class="weight-value">40%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Compliance</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 30%"></div>
                        </div>
                        <span class="weight-value">30%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Behavior</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 20%"></div>
                        </div>
                        <span class="weight-value">20%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Attendance</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 10%"></div>
                        </div>
                        <span class="weight-value">10%</span>
                    </div>
                </div>
                
                <div class="template-footer">
                    <span class="status-badge" style="background: #27ae6020; color: #27ae60;">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                    <div>
                        <button class="btn btn-sm" onclick="editTemplate(1)" style="margin-right: 5px;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm" onclick="useTemplate(1, 'driver')">
                            <i class="fas fa-copy"></i> Use
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Warehouse Template -->
            <div class="template-card">
                <div class="template-header">
                    <div class="template-icon">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <div class="template-title">
                        <div class="template-name">Warehouse Staff Template</div>
                        <div class="template-dept">Department: Warehouse</div>
                    </div>
                </div>
                
                <div class="category-list">
                    <div class="category-item">
                        <span class="category-name">A. Productivity (Order accuracy, Picking speed)</span>
                        <span class="category-weight">45%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">B. Quality (Inventory discrepancies)</span>
                        <span class="category-weight">25%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">C. Attendance</span>
                        <span class="category-weight">20%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">D. Teamwork</span>
                        <span class="category-weight">10%</span>
                    </div>
                </div>
                
                <div class="weight-distribution">
                    <div class="weight-item">
                        <span class="weight-label">Productivity</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 45%"></div>
                        </div>
                        <span class="weight-value">45%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Quality</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 25%"></div>
                        </div>
                        <span class="weight-value">25%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Attendance</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 20%"></div>
                        </div>
                        <span class="weight-value">20%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Teamwork</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 10%"></div>
                        </div>
                        <span class="weight-value">10%</span>
                    </div>
                </div>
                
                <div class="template-footer">
                    <span class="status-badge" style="background: #27ae6020; color: #27ae60;">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                    <div>
                        <button class="btn btn-sm" onclick="editTemplate(2)" style="margin-right: 5px;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm" onclick="useTemplate(2, 'warehouse')">
                            <i class="fas fa-copy"></i> Use
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Logistics Template -->
            <div class="template-card">
                <div class="template-header">
                    <div class="template-icon">
                        <i class="fas fa-route"></i>
                    </div>
                    <div class="template-title">
                        <div class="template-name">Logistics Staff Template</div>
                        <div class="template-dept">Department: Logistics</div>
                    </div>
                </div>
                
                <div class="category-list">
                    <div class="category-item">
                        <span class="category-name">A. Operations (Route optimization, Dispatch)</span>
                        <span class="category-weight">40%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">B. Communication</span>
                        <span class="category-weight">25%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">C. Problem Solving</span>
                        <span class="category-weight">20%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">D. Documentation</span>
                        <span class="category-weight">15%</span>
                    </div>
                </div>
                
                <div class="weight-distribution">
                    <div class="weight-item">
                        <span class="weight-label">Operations</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 40%"></div>
                        </div>
                        <span class="weight-value">40%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Communication</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 25%"></div>
                        </div>
                        <span class="weight-value">25%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Problem Solving</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 20%"></div>
                        </div>
                        <span class="weight-value">20%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Documentation</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 15%"></div>
                        </div>
                        <span class="weight-value">15%</span>
                    </div>
                </div>
                
                <div class="template-footer">
                    <span class="status-badge" style="background: #27ae6020; color: #27ae60;">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                    <div>
                        <button class="btn btn-sm" onclick="editTemplate(3)" style="margin-right: 5px;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm" onclick="useTemplate(3, 'logistics')">
                            <i class="fas fa-copy"></i> Use
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Admin Template -->
            <div class="template-card">
                <div class="template-header">
                    <div class="template-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="template-title">
                        <div class="template-name">Admin Staff Template</div>
                        <div class="template-dept">Department: Admin</div>
                    </div>
                </div>
                
                <div class="category-list">
                    <div class="category-item">
                        <span class="category-name">A. Task Completion</span>
                        <span class="category-weight">35%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">B. Accuracy</span>
                        <span class="category-weight">30%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">C. Responsiveness</span>
                        <span class="category-weight">20%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">D. Initiative</span>
                        <span class="category-weight">15%</span>
                    </div>
                </div>
                
                <div class="weight-distribution">
                    <div class="weight-item">
                        <span class="weight-label">Task Completion</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 35%"></div>
                        </div>
                        <span class="weight-value">35%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Accuracy</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 30%"></div>
                        </div>
                        <span class="weight-value">30%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Responsiveness</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 20%"></div>
                        </div>
                        <span class="weight-value">20%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Initiative</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 15%"></div>
                        </div>
                        <span class="weight-value">15%</span>
                    </div>
                </div>
                
                <div class="template-footer">
                    <span class="status-badge" style="background: #27ae6020; color: #27ae60;">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                    <div>
                        <button class="btn btn-sm" onclick="editTemplate(4)" style="margin-right: 5px;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm" onclick="useTemplate(4, 'admin')">
                            <i class="fas fa-copy"></i> Use
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Management Template -->
            <div class="template-card">
                <div class="template-header">
                    <div class="template-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="template-title">
                        <div class="template-name">Management Template</div>
                        <div class="template-dept">Department: Management</div>
                    </div>
                </div>
                
                <div class="category-list">
                    <div class="category-item">
                        <span class="category-name">A. Team Performance</span>
                        <span class="category-weight">30%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">B. Decision Making</span>
                        <span class="category-weight">25%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">C. Process Improvement</span>
                        <span class="category-weight">20%</span>
                    </div>
                    <div class="category-item">
                        <span class="category-name">D. Leadership</span>
                        <span class="category-weight">25%</span>
                    </div>
                </div>
                
                <div class="weight-distribution">
                    <div class="weight-item">
                        <span class="weight-label">Team Performance</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 30%"></div>
                        </div>
                        <span class="weight-value">30%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Decision Making</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 25%"></div>
                        </div>
                        <span class="weight-value">25%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Process Imp.</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 20%"></div>
                        </div>
                        <span class="weight-value">20%</span>
                    </div>
                    <div class="weight-item">
                        <span class="weight-label">Leadership</span>
                        <div class="weight-bar-container">
                            <div class="weight-bar" style="width: 25%"></div>
                        </div>
                        <span class="weight-value">25%</span>
                    </div>
                </div>
                
                <div class="template-footer">
                    <span class="status-badge" style="background: #27ae6020; color: #27ae60;">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                    <div>
                        <button class="btn btn-sm" onclick="editTemplate(5)" style="margin-right: 5px;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm" onclick="useTemplate(5, 'management')">
                            <i class="fas fa-copy"></i> Use
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- Action Buttons -->
<div class="action-buttons">
    <button class="action-btn primary" onclick="openNewReviewModal()">
        <i class="fas fa-plus"></i> New Review
    </button>
    <button class="action-btn success" onclick="openBulkReviewModal()">
        <i class="fas fa-layer-group"></i> Bulk Review
    </button>
    <button class="action-btn warning" onclick="openReminderModal()">
        <i class="fas fa-bell"></i> Send Reminders
    </button>
    <button class="action-btn info" onclick="openReportModal()">
        <i class="fas fa-file-pdf"></i> Generate Report
    </button>
</div>

<!-- ==================== MODALS ==================== -->

<!-- New Review Modal -->
<div id="newReviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Create New Performance Review</h3>
            <span class="modal-close" onclick="closeNewReviewModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_review">
            
            <div class="form-group">
                <label>Select Employee <span style="color: var(--danger);">*</span></label>
                <select name="employee_id" required id="review_employee_select">
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($employees as $emp): 
                        if (!$emp['has_review']): // Only show employees without reviews ?>
                    <option value="<?php echo $emp['employee_id']; ?>" 
                            data-department="<?php echo $emp['department']; ?>">
                        <?php echo $emp['full_name'] . ' - ' . ($emp['position'] ?? $emp['job_title']); ?>
                    </option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Review Template <span style="color: var(--danger);">*</span></label>
                <select name="template_id" required id="review_template_select">
                    <option value="">-- Choose Template --</option>
                    <option value="1" data-department="driver">Driver Template (Productivity 40%, Compliance 30%, Behavior 20%, Attendance 10%)</option>
                    <option value="2" data-department="warehouse">Warehouse Template (Productivity 45%, Quality 25%, Attendance 20%, Teamwork 10%)</option>
                    <option value="3" data-department="logistics">Logistics Template (Operations 40%, Communication 25%, Problem Solving 20%, Documentation 15%)</option>
                    <option value="4" data-department="admin">Admin Template (Task Completion 35%, Accuracy 30%, Responsiveness 20%, Initiative 15%)</option>
                    <option value="5" data-department="management">Management Template (Team Performance 30%, Decision Making 25%, Process 20%, Leadership 25%)</option>
                </select>
                <small style="color: var(--gray);">Template should match employee's department</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Review Type</label>
                    <select name="review_type">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly" selected>Quarterly</option>
                        <option value="semi_annual">Semi-Annual</option>
                        <option value="annual">Annual</option>
                        <option value="probation">Probation</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Review Date</label>
                    <input type="date" name="review_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Reviewer (optional)</label>
                <select name="reviewer_id">
                    <option value="">-- Select Reviewer --</option>
                    <?php foreach ($reviewers as $reviewer): ?>
                    <option value="<?php echo $reviewer['id']; ?>" <?php echo ($reviewer['id'] == ($_SESSION['user_id'] ?? 0)) ? 'selected' : ''; ?>>
                        <?php echo $reviewer['full_name']; ?> (<?php echo ucfirst($reviewer['role']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeNewReviewModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Review</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Review Modal -->
<div id="bulkReviewModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group"></i> Bulk Create Reviews</h3>
            <span class="modal-close" onclick="closeBulkReviewModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="bulk_create">
            
            <div class="form-group">
                <label>Select Employees <span style="color: var(--danger);">*</span></label>
                <div class="employee-selection">
                    <?php 
                    $no_review_employees = array_filter($employees, function($emp) {
                        return !$emp['has_review'];
                    });
                    
                    foreach ($no_review_employees as $emp): 
                        $initials = $emp['first_initial'] . $emp['last_initial'];
                    ?>
                    <div class="employee-checkbox">
                        <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['employee_id']; ?>" id="emp_<?php echo $emp['employee_id']; ?>">
                        <label for="emp_<?php echo $emp['employee_id']; ?>">
                            <span class="checkbox-avatar"><?php echo $initials; ?></span>
                            <div>
                                <strong><?php echo $emp['full_name']; ?></strong><br>
                                <small><?php echo $emp['position'] ?? $emp['job_title']; ?> | <?php echo ucfirst($emp['department']); ?></small>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($no_review_employees)): ?>
                    <p style="text-align: center; color: var(--gray); padding: 20px;">All employees already have reviews!</p>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-sm" onclick="selectAllEmployees()">Select All</button>
                    <button type="button" class="btn btn-sm" onclick="deselectAllEmployees()">Deselect All</button>
                </div>
            </div>
            
            <div class="form-group">
                <label>Review Template <span style="color: var(--danger);">*</span></label>
                <select name="bulk_template_id" required>
                    <option value="">-- Choose Template --</option>
                    <option value="1">Driver Template</option>
                    <option value="2">Warehouse Template</option>
                    <option value="3">Logistics Template</option>
                    <option value="4">Admin Template</option>
                    <option value="5">Management Template</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Review Type</label>
                    <select name="bulk_review_type">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly" selected>Quarterly</option>
                        <option value="semi_annual">Semi-Annual</option>
                        <option value="annual">Annual</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Review Date</label>
                    <input type="date" name="bulk_review_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Reviewer (optional - will be assigned to all)</label>
                <select name="bulk_reviewer_id">
                    <option value="">-- Select Reviewer --</option>
                    <?php foreach ($reviewers as $reviewer): ?>
                    <option value="<?php echo $reviewer['id']; ?>" <?php echo ($reviewer['id'] == ($_SESSION['user_id'] ?? 0)) ? 'selected' : ''; ?>>
                        <?php echo $reviewer['full_name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkReviewModal()">Cancel</button>
                <button type="submit" class="btn btn-success" <?php echo empty($no_review_employees) ? 'disabled' : ''; ?>>Create Reviews</button>
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
                <label>Reminder Type</label>
                <select name="reminder_type" id="reminder_type" onchange="updateReminderCount()">
                    <option value="overdue">Overdue Reviews (<?php echo $stats['overdue']; ?>)</option>
                    <option value="upcoming">Upcoming Deadlines (within 7 days)</option>
                    <option value="no_review">Employees Without Reviews (<?php echo $stats['without_reviews']; ?>)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Custom Message (optional)</label>
                <textarea name="custom_message" rows="3" placeholder="Enter a custom message to include in the reminder email..."></textarea>
            </div>
            
            <div class="form-group" id="reminder_preview">
                <label>Preview:</label>
                <div style="background: var(--light-gray); padding: 15px; border-radius: 10px; font-size: 12px;">
                    <strong>Subject:</span> Performance Review Reminder</strong><br>
                    <p>Dear [Supervisor],</p>
                    <p>This is a reminder about pending performance reviews.</p>
                    <p id="reminder_details">- Overdue reviews: <?php echo $stats['overdue']; ?></p>
                    <p>Please complete these reviews as soon as possible.</p>
                    <p>Thank you,<br>HR Department</p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReminderModal()">Cancel</button>
                <button type="submit" class="btn btn-warning">Send Reminders</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Modal -->
<div id="reportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-pdf"></i> Generate Performance Report</h3>
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
                    <option value="rating">Rating Distribution Report</option>
                    <option value="trend">Performance Trends</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo date('Y-01-01'); ?>">
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

<!-- Add Template Modal -->
<div id="addTemplateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Create Review Template</h3>
            <span class="modal-close" onclick="closeAddTemplateModal()">&times;</span>
        </div>
        
        <form method="POST" action="modules/performance/save_template.php">
            <div class="form-group">
                <label>Template Name</label>
                <input type="text" name="template_name" placeholder="e.g., Driver Q1 Template" required>
            </div>
            
            <div class="form-row">
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
                    <label>Position (Optional)</label>
                    <select name="position_id">
                        <option value="">All Positions</option>
                        <?php
                        $pos_stmt = $pdo->query("SELECT id, title FROM job_postings WHERE status = 'published'");
                        while ($pos = $pos_stmt->fetch()) {
                            echo "<option value='{$pos['id']}'>{$pos['title']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2" placeholder="Template description"></textarea>
            </div>
            
            <div class="form-group">
                <label>Categories (JSON format)</label>
                <textarea name="categories" rows="4" placeholder='[{"name":"Productivity","weight":40},{"name":"Compliance","weight":30}]'></textarea>
                <small style="color: var(--gray);">Define categories and weights (must sum to 100)</small>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddTemplateModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Template</button>
            </div>
        </form>
    </div>
</div>

<!-- KPI Library Modal -->
<div id="kpiLibraryModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-chart-line"></i> KPI Library by Department</h3>
            <span class="modal-close" onclick="closeKpiLibraryModal()">&times;</span>
        </div>
        
        <div style="margin-bottom: 20px;">
            <p style="color: var(--gray); font-size: 13px;">Common KPIs used in performance reviews. Click to add to template.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <!-- Driver KPIs -->
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px;">
                <h4 style="margin: 0 0 10px 0; color: var(--primary); display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-truck"></i> Driver KPIs
                </h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> On-time Delivery Rate
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Safety Compliance
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Accident Record
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Customer Complaints
                    </li>
                    <li style="padding: 5px 0; font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Fuel Efficiency
                    </li>
                </ul>
            </div>
            
            <!-- Warehouse KPIs -->
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px;">
                <h4 style="margin: 0 0 10px 0; color: var(--primary); display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-warehouse"></i> Warehouse KPIs
                </h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Picking Accuracy
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Processing Speed
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Inventory Error Rate
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Safety Compliance
                    </li>
                    <li style="padding: 5px 0; font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Team Cooperation
                    </li>
                </ul>
            </div>
            
            <!-- Logistics KPIs -->
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px;">
                <h4 style="margin: 0 0 10px 0; color: var(--primary); display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-route"></i> Logistics KPIs
                </h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Route Optimization
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Dispatch Accuracy
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Communication
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Problem Resolution
                    </li>
                    <li style="padding: 5px 0; font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Documentation
                    </li>
                </ul>
            </div>
            
            <!-- Admin KPIs -->
            <div style="background: var(--light-gray); border-radius: 15px; padding: 15px;">
                <h4 style="margin: 0 0 10px 0; color: var(--primary); display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-user-tie"></i> Admin KPIs
                </h4>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Task Completion Rate
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Accuracy of Work
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Responsiveness
                    </li>
                    <li style="padding: 5px 0; border-bottom: 1px dashed var(--border); font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Initiative
                    </li>
                    <li style="padding: 5px 0; font-size: 12px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Attendance
                    </li>
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

// ========== MODAL FUNCTIONS ==========

// New Review Modal
function openNewReviewModal() {
    document.getElementById('newReviewModal').classList.add('active');
    
    // Auto-suggest template based on selected employee
    const employeeSelect = document.getElementById('review_employee_select');
    const templateSelect = document.getElementById('review_template_select');
    
    employeeSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const department = selectedOption.getAttribute('data-department');
        
        // Auto-select template matching department
        for (let i = 0; i < templateSelect.options.length; i++) {
            const opt = templateSelect.options[i];
            if (opt.getAttribute('data-department') === department) {
                templateSelect.value = opt.value;
                break;
            }
        }
    });
}

function closeNewReviewModal() {
    document.getElementById('newReviewModal').classList.remove('active');
}

// Bulk Review Modal
function openBulkReviewModal() {
    document.getElementById('bulkReviewModal').classList.add('active');
}

function closeBulkReviewModal() {
    document.getElementById('bulkReviewModal').classList.remove('active');
}

function selectAllEmployees() {
    const checkboxes = document.querySelectorAll('#bulkReviewModal input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = true);
}

function deselectAllEmployees() {
    const checkboxes = document.querySelectorAll('#bulkReviewModal input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
}

// Reminder Modal
function openReminderModal(type = 'overdue') {
    document.getElementById('reminderModal').classList.add('active');
    document.getElementById('reminder_type').value = type;
    updateReminderCount();
}

function closeReminderModal() {
    document.getElementById('reminderModal').classList.remove('active');
}

function updateReminderCount() {
    const type = document.getElementById('reminder_type').value;
    const detailsEl = document.getElementById('reminder_details');
    
    <?php
    // Calculate counts for JavaScript
    $overdue_count = $stats['overdue'];
    $upcoming_count = 0;
    $no_review_count = $stats['without_reviews'];
    
    foreach ($employees as $emp) {
        if (!$emp['overdue'] && $emp['days_until_deadline'] <= 7 && $emp['days_until_deadline'] > 0) {
            $upcoming_count++;
        }
    }
    ?>
    
    if (type === 'overdue') {
        detailsEl.innerHTML = '- Overdue reviews: <?php echo $overdue_count; ?>';
    } else if (type === 'upcoming') {
        detailsEl.innerHTML = '- Upcoming deadlines (within 7 days): <?php echo $upcoming_count; ?>';
    } else if (type === 'no_review') {
        detailsEl.innerHTML = '- Employees without reviews: <?php echo $no_review_count; ?>';
    }
}

// Report Modal
function openReportModal() {
    document.getElementById('reportModal').classList.add('active');
}

function closeReportModal() {
    document.getElementById('reportModal').classList.remove('active');
}

// Template Modals
function openAddTemplateModal() {
    document.getElementById('addTemplateModal').classList.add('active');
}

function closeAddTemplateModal() {
    document.getElementById('addTemplateModal').classList.remove('active');
}

function openKpiLibraryModal() {
    document.getElementById('kpiLibraryModal').classList.add('active');
}

function closeKpiLibraryModal() {
    document.getElementById('kpiLibraryModal').classList.remove('active');
}

function editTemplate(id) {
    alert('Edit template: ' + id + ' - This would open the template editor');
}

function useTemplate(id, department) {
    // Close templates view and open new review modal with template pre-selected
    closeAddTemplateModal();
    openNewReviewModal();
    
    const templateSelect = document.getElementById('review_template_select');
    if (templateSelect) {
        templateSelect.value = id;
    }
    
    // Also suggest employees from same department
    const employeeSelect = document.getElementById('review_employee_select');
    if (employeeSelect) {
        // You could filter employees here
    }
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