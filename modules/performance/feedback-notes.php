<?php
// Start output buffering at the VERY FIRST LINE
ob_start();

// modules/performance/feedback-notes.php
$page_title = "Feedback & Notes";

// Include required files
require_once 'includes/config.php';
require_once 'config/mail_config.php';

// Get current user ID from session
$current_user_id = $_SESSION['user_id'] ?? 1; // Default to admin if not set

// Get filter parameters
$module_filter = isset($_GET['module']) ? $_GET['module'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'dashboard'; // dashboard, list, or analytics

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
 * Helper Functions - ALL RENAMED to avoid conflicts
 */
function fbGetUserPhoto($user) {
    if (!empty($user['profile_picture']) && file_exists('../../' . $user['profile_picture'])) {
        return '../../' . htmlspecialchars($user['profile_picture']);
    }
    return null;
}

function fbGetEmployeePhoto($employee) {
    if (!empty($employee['photo_path']) && file_exists('../../' . $employee['photo_path'])) {
        return '../../' . htmlspecialchars($employee['photo_path']);
    }
    return null;
}

function fbGetTypeIcon($type) {
    $icons = [
        'interview' => 'fas fa-calendar-check',
        'screening' => 'fas fa-search',
        'probation' => 'fas fa-hourglass-half',
        'performance' => 'fas fa-chart-line',
        'general' => 'fas fa-sticky-note',
        'warning' => 'fas fa-exclamation-triangle',
        'commendation' => 'fas fa-award',
        'training' => 'fas fa-graduation-cap',
        'disciplinary' => 'fas fa-gavel',
        'exit' => 'fas fa-door-open'
    ];
    return $icons[$type] ?? 'fas fa-comment';
}

function fbGetTypeColor($type) {
    $colors = [
        'interview' => '#3498db',
        'screening' => '#9b59b6',
        'probation' => '#f39c12',
        'performance' => '#27ae60',
        'general' => '#7f8c8d',
        'warning' => '#e74c3c',
        'commendation' => '#2ecc71',
        'training' => '#1abc9c',
        'disciplinary' => '#c0392b',
        'exit' => '#95a5a6'
    ];
    return $colors[$type] ?? '#34495e';
}

function fbGetModuleIcon($module) {
    $icons = [
        'recruitment' => 'fas fa-user-plus',
        'onboarding' => 'fas fa-rocket',
        'probation' => 'fas fa-hourglass-half',
        'performance' => 'fas fa-chart-line',
        'training' => 'fas fa-graduation-cap',
        'disciplinary' => 'fas fa-gavel',
        'exit' => 'fas fa-door-open',
        'general' => 'fas fa-folder'
    ];
    return $icons[$module] ?? 'fas fa-comment';
}

// RENAMED: was timeAgo() - changed to fbTimeAgo()
function fbTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

// Create temporary tables if they don't exist (for demo purposes)
// In a real system, these would be permanent tables

// Check if feedback table exists, if not create it
try {
    $pdo->query("SELECT 1 FROM feedback_notes LIMIT 1");
} catch (Exception $e) {
    // Create feedback_notes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `feedback_notes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `module` enum('recruitment','onboarding','probation','performance','training','disciplinary','exit','general') NOT NULL,
            `type` enum('interview','screening','probation','performance','general','warning','commendation','training','disciplinary','exit') NOT NULL,
            `reference_id` int(11) DEFAULT NULL,
            `subject` varchar(255) NOT NULL,
            `content` text NOT NULL,
            `created_by` int(11) DEFAULT NULL,
            `created_for` int(11) DEFAULT NULL,
            `for_type` enum('applicant','employee','user','none') DEFAULT 'none',
            `is_private` tinyint(1) DEFAULT 0,
            `is_important` tinyint(1) DEFAULT 0,
            `is_resolved` tinyint(1) DEFAULT 0,
            `resolved_at` timestamp NULL DEFAULT NULL,
            `resolved_by` int(11) DEFAULT NULL,
            `parent_id` int(11) DEFAULT NULL,
            `attachments` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `module` (`module`),
            KEY `type` (`type`),
            KEY `reference_id` (`reference_id`),
            KEY `created_by` (`created_by`),
            KEY `created_for` (`created_for`),
            KEY `for_type` (`for_type`),
            KEY `is_important` (`is_important`),
            KEY `is_resolved` (`is_resolved`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    
    // Create feedback_reactions table for likes/emojis
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `feedback_reactions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `feedback_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `reaction` varchar(50) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_reaction` (`feedback_id`,`user_id`,`reaction`),
            KEY `feedback_id` (`feedback_id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_feedback':
                // Add new feedback/note
                $module = $_POST['module'] ?? 'general';
                $type = $_POST['type'] ?? 'general';
                $subject = $_POST['subject'] ?? '';
                $content = $_POST['content'] ?? '';
                $for_type = $_POST['for_type'] ?? 'none';
                $created_for = $_POST['created_for'] ?? null;
                $reference_id = $_POST['reference_id'] ?? null;
                $is_private = isset($_POST['is_private']) ? 1 : 0;
                $is_important = isset($_POST['is_important']) ? 1 : 0;
                
                if ($subject && $content) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO feedback_notes 
                            (module, type, reference_id, subject, content, created_by, created_for, for_type, is_private, is_important, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $module,
                            $type,
                            $reference_id ?: null,
                            $subject,
                            $content,
                            $current_user_id,
                            $created_for ?: null,
                            $for_type,
                            $is_private,
                            $is_important
                        ]);
                        
                        $feedback_id = $pdo->lastInsertId();
                        
                        simpleLog($pdo, $current_user_id, 'add_feedback', 
                            "Added feedback: $subject");
                        
                        $message = "Feedback added successfully!";
                        
                    } catch (Exception $e) {
                        $error = "Error adding feedback: " . $e->getMessage();
                    }
                } else {
                    $error = "Subject and content are required";
                }
                break;
                
            case 'update_feedback':
                // Update existing feedback
                $feedback_id = $_POST['feedback_id'] ?? 0;
                $subject = $_POST['subject'] ?? '';
                $content = $_POST['content'] ?? '';
                $is_private = isset($_POST['is_private']) ? 1 : 0;
                $is_important = isset($_POST['is_important']) ? 1 : 0;
                
                if ($feedback_id && $subject && $content) {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE feedback_notes 
                            SET subject = ?, content = ?, is_private = ?, is_important = ?, updated_at = NOW()
                            WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
                        ");
                        $stmt->execute([$subject, $content, $is_private, $is_important, $feedback_id, $current_user_id, $current_user_id]);
                        
                        simpleLog($pdo, $current_user_id, 'update_feedback', 
                            "Updated feedback ID: $feedback_id");
                        
                        $message = "Feedback updated successfully!";
                        
                    } catch (Exception $e) {
                        $error = "Error updating feedback: " . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_feedback':
                // Delete feedback
                $feedback_id = $_POST['feedback_id'] ?? 0;
                
                if ($feedback_id) {
                    try {
                        // Check if user is admin or creator
                        $check = $pdo->prepare("
                            DELETE FROM feedback_notes 
                            WHERE id = ? AND (created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
                        ");
                        $check->execute([$feedback_id, $current_user_id, $current_user_id]);
                        
                        if ($check->rowCount() > 0) {
                            simpleLog($pdo, $current_user_id, 'delete_feedback', 
                                "Deleted feedback ID: $feedback_id");
                            $message = "Feedback deleted successfully!";
                        } else {
                            $error = "You don't have permission to delete this feedback";
                        }
                        
                    } catch (Exception $e) {
                        $error = "Error deleting feedback: " . $e->getMessage();
                    }
                }
                break;
                
            case 'resolve_feedback':
                // Mark feedback as resolved
                $feedback_id = $_POST['feedback_id'] ?? 0;
                
                if ($feedback_id) {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE feedback_notes 
                            SET is_resolved = 1, resolved_at = NOW(), resolved_by = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$current_user_id, $feedback_id]);
                        
                        simpleLog($pdo, $current_user_id, 'resolve_feedback', 
                            "Resolved feedback ID: $feedback_id");
                        
                        $message = "Feedback marked as resolved!";
                        
                    } catch (Exception $e) {
                        $error = "Error resolving feedback: " . $e->getMessage();
                    }
                }
                break;
                
            case 'add_reaction':
                // Add reaction to feedback (like, emoji)
                $feedback_id = $_POST['feedback_id'] ?? 0;
                $reaction = $_POST['reaction'] ?? '👍';
                
                if ($feedback_id) {
                    try {
                        // Check if reaction already exists
                        $check = $pdo->prepare("
                            SELECT id FROM feedback_reactions 
                            WHERE feedback_id = ? AND user_id = ? AND reaction = ?
                        ");
                        $check->execute([$feedback_id, $current_user_id, $reaction]);
                        
                        if ($check->rowCount() == 0) {
                            $stmt = $pdo->prepare("
                                INSERT INTO feedback_reactions (feedback_id, user_id, reaction, created_at)
                                VALUES (?, ?, ?, NOW())
                            ");
                            $stmt->execute([$feedback_id, $current_user_id, $reaction]);
                        } else {
                            // Remove reaction (toggle)
                            $stmt = $pdo->prepare("
                                DELETE FROM feedback_reactions 
                                WHERE feedback_id = ? AND user_id = ? AND reaction = ?
                            ");
                            $stmt->execute([$feedback_id, $current_user_id, $reaction]);
                        }
                        
                        // Return JSON response for AJAX
                        if (isset($_POST['ajax'])) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true]);
                            exit;
                        }
                        
                    } catch (Exception $e) {
                        if (isset($_POST['ajax'])) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                            exit;
                        }
                        $error = "Error adding reaction: " . $e->getMessage();
                    }
                }
                break;
                
            case 'add_reply':
                // Add reply to feedback
                $parent_id = $_POST['parent_id'] ?? 0;
                $content = $_POST['reply_content'] ?? '';
                
                if ($parent_id && $content) {
                    try {
                        // Get parent feedback info
                        $parent = $pdo->prepare("SELECT module, type, reference_id, created_for, for_type FROM feedback_notes WHERE id = ?");
                        $parent->execute([$parent_id]);
                        $parent_data = $parent->fetch();
                        
                        if ($parent_data) {
                            $stmt = $pdo->prepare("
                                INSERT INTO feedback_notes 
                                (module, type, reference_id, subject, content, created_by, created_for, for_type, parent_id, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $parent_data['module'],
                                $parent_data['type'],
                                $parent_data['reference_id'],
                                'Re: ' . ($_POST['reply_subject'] ?? 'Reply'),
                                $content,
                                $current_user_id,
                                $parent_data['created_for'],
                                $parent_data['for_type'],
                                $parent_id
                            ]);
                            
                            $message = "Reply added successfully!";
                        }
                        
                    } catch (Exception $e) {
                        $error = "Error adding reply: " . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get all feedback notes with related information
$query = "
    SELECT 
        fn.*,
        creator.id as creator_id,
        creator.full_name as creator_name,
        creator.username as creator_username,
        creator.role as creator_role,
        creator.profile_picture as creator_photo,
        target_applicant.first_name as applicant_first_name,
        target_applicant.last_name as applicant_last_name,
        target_applicant.photo_path as applicant_photo,
        target_applicant.application_number,
        target_employee.employee_id as target_employee_code,
        target_employee.position as target_position,
        target_employee.department as target_department,
        target_employee_ja.first_name as employee_first_name,
        target_employee_ja.last_name as employee_last_name,
        target_employee_ja.photo_path as employee_photo,
        target_user.full_name as target_user_name,
        target_user.username as target_username,
        
        -- Reaction counts
        (SELECT COUNT(*) FROM feedback_reactions WHERE feedback_id = fn.id) as reaction_count,
        (SELECT GROUP_CONCAT(CONCAT(user_id, ':', reaction) SEPARATOR '|') FROM feedback_reactions WHERE feedback_id = fn.id) as reactions_data,
        
        -- Check if current user reacted
        (SELECT reaction FROM feedback_reactions WHERE feedback_id = fn.id AND user_id = ?) as user_reaction,
        
        -- Reply count
        (SELECT COUNT(*) FROM feedback_notes WHERE parent_id = fn.id) as reply_count
        
    FROM feedback_notes fn
    LEFT JOIN users creator ON fn.created_by = creator.id
    LEFT JOIN job_applications target_applicant ON fn.created_for = target_applicant.id AND fn.for_type = 'applicant'
    LEFT JOIN new_hires target_employee ON fn.created_for = target_employee.id AND fn.for_type = 'employee'
    LEFT JOIN job_applications target_employee_ja ON target_employee.applicant_id = target_employee_ja.id
    LEFT JOIN users target_user ON fn.created_for = target_user.id AND fn.for_type = 'user'
    WHERE 1=1
";

$params = [$current_user_id]; // For user_reaction check

// Module filter
if ($module_filter !== 'all') {
    $query .= " AND fn.module = ?";
    $params[] = $module_filter;
}

// Type filter
if ($type_filter !== 'all') {
    $query .= " AND fn.type = ?";
    $params[] = $type_filter;
}

// Search filter
if (!empty($search_filter)) {
    $query .= " AND (fn.subject LIKE ? OR fn.content LIKE ? OR creator.full_name LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Date filters
if (!empty($date_from)) {
    $query .= " AND DATE(fn.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(fn.created_at) <= ?";
    $params[] = $date_to;
}

// Only show non-private feedback or feedback created by current user or admins
$query .= " AND (fn.is_private = 0 OR fn.created_by = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))";
$params[] = $current_user_id;
$params[] = $current_user_id;

$query .= " ORDER BY fn.is_important DESC, fn.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$feedback_notes = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => count($feedback_notes),
    'important' => 0,
    'resolved' => 0,
    'unresolved' => 0,
    'recent' => 0,
    'by_module' => [],
    'by_type' => []
];

$recent_cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));

foreach ($feedback_notes as $note) {
    if ($note['is_important']) {
        $stats['important']++;
    }
    
    if ($note['is_resolved']) {
        $stats['resolved']++;
    } else {
        $stats['unresolved']++;
    }
    
    if ($note['created_at'] >= $recent_cutoff) {
        $stats['recent']++;
    }
    
    // Count by module
    $module = $note['module'];
    if (!isset($stats['by_module'][$module])) {
        $stats['by_module'][$module] = 0;
    }
    $stats['by_module'][$module]++;
    
    // Count by type
    $type = $note['type'];
    if (!isset($stats['by_type'][$type])) {
        $stats['by_type'][$type] = 0;
    }
    $stats['by_type'][$type]++;
}

// Get all applicants for dropdown
$applicants = $pdo->query("
    SELECT id, first_name, last_name, application_number, email 
    FROM job_applications 
    ORDER BY created_at DESC 
    LIMIT 50
")->fetchAll();

// Get all employees for dropdown
$employees = $pdo->query("
    SELECT nh.id, nh.employee_id, nh.position, nh.department, ja.first_name, ja.last_name, ja.email
    FROM new_hires nh
    INNER JOIN job_applications ja ON nh.applicant_id = ja.id
    WHERE nh.status IN ('onboarding', 'active')
    ORDER BY ja.last_name ASC
")->fetchAll();

// Get all users for dropdown
$users = $pdo->query("
    SELECT id, full_name, username, role, email 
    FROM users 
    WHERE role IN ('admin', 'supervisor', 'manager')
    ORDER BY full_name ASC
")->fetchAll();

// Module configuration
$module_config = [
    'recruitment' => ['label' => 'Recruitment', 'icon' => 'fas fa-user-plus', 'color' => '#3498db'],
    'onboarding' => ['label' => 'Onboarding', 'icon' => 'fas fa-rocket', 'color' => '#9b59b6'],
    'probation' => ['label' => 'Probation', 'icon' => 'fas fa-hourglass-half', 'color' => '#f39c12'],
    'performance' => ['label' => 'Performance', 'icon' => 'fas fa-chart-line', 'color' => '#27ae60'],
    'training' => ['label' => 'Training', 'icon' => 'fas fa-graduation-cap', 'color' => '#1abc9c'],
    'disciplinary' => ['label' => 'Disciplinary', 'icon' => 'fas fa-gavel', 'color' => '#e74c3c'],
    'exit' => ['label' => 'Exit', 'icon' => 'fas fa-door-open', 'color' => '#95a5a6'],
    'general' => ['label' => 'General', 'icon' => 'fas fa-folder', 'color' => '#7f8c8d']
];

// Type configuration
$type_config = [
    'interview' => ['label' => 'Interview', 'icon' => 'fas fa-calendar-check', 'color' => '#3498db'],
    'screening' => ['label' => 'Screening', 'icon' => 'fas fa-search', 'color' => '#9b59b6'],
    'probation' => ['label' => 'Probation', 'icon' => 'fas fa-hourglass-half', 'color' => '#f39c12'],
    'performance' => ['label' => 'Performance', 'icon' => 'fas fa-chart-line', 'color' => '#27ae60'],
    'general' => ['label' => 'General', 'icon' => 'fas fa-sticky-note', 'color' => '#7f8c8d'],
    'warning' => ['label' => 'Warning', 'icon' => 'fas fa-exclamation-triangle', 'color' => '#e74c3c'],
    'commendation' => ['label' => 'Commendation', 'icon' => 'fas fa-award', 'color' => '#2ecc71'],
    'training' => ['label' => 'Training', 'icon' => 'fas fa-graduation-cap', 'color' => '#1abc9c'],
    'disciplinary' => ['label' => 'Disciplinary', 'icon' => 'fas fa-gavel', 'color' => '#c0392b'],
    'exit' => ['label' => 'Exit', 'icon' => 'fas fa-door-open', 'color' => '#95a5a6']
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

/* Module Distribution */
.module-section {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.module-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.module-title i {
    color: var(--primary);
}

.module-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
}

.module-item {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.module-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px var(--primary-transparent);
}

.module-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.module-icon.recruitment { background: #3498db20; color: #3498db; }
.module-icon.onboarding { background: #9b59b620; color: #9b59b6; }
.module-icon.probation { background: #f39c1220; color: #f39c12; }
.module-icon.performance { background: #27ae6020; color: #27ae60; }
.module-icon.training { background: #1abc9c20; color: #1abc9c; }
.module-icon.disciplinary { background: #e74c3c20; color: #e74c3c; }
.module-icon.exit { background: #95a5a620; color: #95a5a6; }
.module-icon.general { background: #7f8c8d20; color: #7f8c8d; }

.module-info {
    flex: 1;
}

.module-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 3px;
}

.module-count {
    font-size: 18px;
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

/* Feedback Grid */
.feedback-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.feedback-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid var(--border);
    position: relative;
}

.feedback-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px var(--primary-transparent-2);
    border-color: var(--primary);
}

.feedback-card.important {
    border-left: 5px solid var(--warning);
}

.feedback-card.resolved {
    opacity: 0.8;
}

.feedback-card.resolved::after {
    content: '✓ RESOLVED';
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--success);
    color: white;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 600;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.card-avatar {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid white;
    box-shadow: 0 3px 10px var(--primary-transparent);
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}

.card-info {
    flex: 1;
}

.card-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 2px;
}

.card-meta {
    font-size: 11px;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    background: var(--light-gray);
    color: var(--gray);
}

.card-badge i {
    font-size: 10px;
}

.card-subject {
    font-size: 15px;
    font-weight: 600;
    color: var(--dark);
    margin: 10px 0 5px;
}

.card-content {
    font-size: 13px;
    color: var(--dark);
    line-height: 1.5;
    margin-bottom: 12px;
    max-height: 80px;
    overflow: hidden;
    position: relative;
}

.card-content.expanded {
    max-height: none;
}

.read-more {
    color: var(--primary);
    font-size: 11px;
    cursor: pointer;
    margin-top: 3px;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

.footer-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.footer-right {
    display: flex;
    align-items: center;
    gap: 5px;
}

.reaction-btn {
    background: none;
    border: none;
    color: var(--gray);
    font-size: 14px;
    cursor: pointer;
    padding: 3px 6px;
    border-radius: 20px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.reaction-btn:hover {
    background: var(--light-gray);
    color: var(--primary);
}

.reaction-btn.active {
    color: var(--warning);
}

.reaction-count {
    font-size: 11px;
    font-weight: 600;
    margin-left: 2px;
}

.reply-indicator {
    display: flex;
    align-items: center;
    gap: 3px;
    color: var(--gray);
    font-size: 11px;
    cursor: pointer;
}

.reply-indicator:hover {
    color: var(--primary);
}

/* Target Info */
.target-info {
    background: var(--light-gray);
    border-radius: 10px;
    padding: 8px 12px;
    margin: 10px 0;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.target-avatar {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: var(--primary-transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-weight: 600;
    font-size: 12px;
}

.target-details {
    flex: 1;
}

.target-name {
    font-weight: 600;
    color: var(--dark);
}

.target-role {
    font-size: 10px;
    color: var(--gray);
}

/* Table View */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow-x: auto;
}

.feedback-table {
    width: 100%;
    border-collapse: collapse;
}

.feedback-table th {
    text-align: left;
    padding: 12px 10px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
}

.feedback-table td {
    padding: 12px 10px;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
}

.feedback-table tr {
    transition: all 0.3s;
    cursor: pointer;
}

.feedback-table tr:hover {
    background: var(--light-gray);
}

.feedback-table tr.important-row {
    background: #fff9e6;
}

.feedback-table tr.resolved-row {
    opacity: 0.7;
}

.creator-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-avatar {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    object-fit: cover;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 12px;
}

.module-badge {
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.type-badge {
    padding: 2px 6px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 600;
}

.important-star {
    color: var(--warning);
    font-size: 14px;
}

/* Analytics View */
.analytics-section {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.analytics-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.analytics-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.analytics-chart {
    height: 200px;
    display: flex;
    align-items: flex-end;
    gap: 15px;
    margin-bottom: 20px;
}

.chart-bar-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.chart-bar {
    width: 100%;
    background: var(--primary-transparent);
    border-radius: 8px 8px 0 0;
    position: relative;
    min-height: 30px;
    transition: height 0.3s;
}

.chart-bar-fill {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    background: linear-gradient(180deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 8px 8px 0 0;
}

.chart-label {
    font-size: 11px;
    color: var(--gray);
    text-align: center;
}

.chart-value {
    font-size: 12px;
    font-weight: 600;
    color: var(--dark);
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
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
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
    left: -20px;
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
    margin-bottom: 5px;
}

.timeline-content {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 15px;
}

.timeline-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
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

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 5px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.checkbox-item input[type="checkbox"] {
    width: auto;
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

/* Reaction Picker */
.reaction-picker {
    display: flex;
    gap: 5px;
    margin-top: 5px;
}

.reaction-option {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: var(--light-gray);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.2s;
}

.reaction-option:hover {
    background: var(--primary-transparent);
    transform: scale(1.1);
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
    
    .analytics-section {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 992px) {
    .stats-container {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .module-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .alert-section {
        grid-template-columns: 1fr;
    }
    
    .feedback-grid {
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
    
    .module-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .module-grid {
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
        <i class="fas fa-comment-dots"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div class="view-toggle">
        <a href="?page=performance&subpage=feedback-notes&view=dashboard<?php 
            echo !empty($module_filter) ? '&module=' . $module_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
        ?>" class="view-option <?php echo $view_mode == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>
        <a href="?page=performance&subpage=feedback-notes&view=list<?php 
            echo !empty($module_filter) ? '&module=' . $module_filter : ''; 
            echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; 
        ?>" class="view-option <?php echo $view_mode == 'list' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i> List View
        </a>
        <a href="?page=performance&subpage=feedback-notes&view=analytics<?php 
        ?>" class="view-option <?php echo $view_mode == 'analytics' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> Analytics
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-container">
    <div class="stat-card-modern primary">
        <div class="stat-icon-modern primary">
            <i class="fas fa-comment"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Total Feedback</span>
            <span class="stat-value-modern"><?php echo $stats['total']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern warning">
        <div class="stat-icon-modern warning">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Important</span>
            <span class="stat-value-modern"><?php echo $stats['important']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern success">
        <div class="stat-icon-modern success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Resolved</span>
            <span class="stat-value-modern"><?php echo $stats['resolved']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern danger">
        <div class="stat-icon-modern danger">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Unresolved</span>
            <span class="stat-value-modern"><?php echo $stats['unresolved']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-modern purple">
        <div class="stat-icon-modern purple">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class="stat-content-modern">
            <span class="stat-label-modern">Last 7 Days</span>
            <span class="stat-value-modern"><?php echo $stats['recent']; ?></span>
        </div>
    </div>
</div>

<!-- Module Distribution -->
<div class="module-section">
    <div class="module-title">
        <i class="fas fa-th-large"></i> Feedback by Module
    </div>
    
    <div class="module-grid">
        <?php foreach ($module_config as $key => $module): 
            $count = $stats['by_module'][$key] ?? 0;
            $percentage = $stats['total'] > 0 ? round(($count / $stats['total']) * 100) : 0;
        ?>
        <div class="module-item" onclick="filterByModule('<?php echo $key; ?>')">
            <div class="module-icon <?php echo $key; ?>">
                <i class="<?php echo $module['icon']; ?>"></i>
            </div>
            <div class="module-info">
                <div class="module-label"><?php echo $module['label']; ?></div>
                <div class="module-count"><?php echo $count; ?></div>
                <div style="font-size: 10px; color: var(--gray);"><?php echo $percentage; ?>%</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Alert Section -->
<div class="alert-section">
    <?php if ($stats['important'] > 0): ?>
    <div class="alert-card warning">
        <div class="alert-icon warning">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Important Feedback</div>
            <div class="alert-value"><?php echo $stats['important']; ?> items</div>
            <a href="#" class="alert-link" onclick="applyFilter('important')">View Now →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['unresolved'] > 0): ?>
    <div class="alert-card danger">
        <div class="alert-icon danger">
            <i class="fas fa-clock"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">Unresolved Items</div>
            <div class="alert-value"><?php echo $stats['unresolved']; ?> pending</div>
            <a href="#" class="alert-link" onclick="applyFilter('unresolved')">Review →</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($stats['recent'] > 0): ?>
    <div class="alert-card info">
        <div class="alert-icon info">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class="alert-content">
            <div class="alert-title">New This Week</div>
            <div class="alert-value"><?php echo $stats['recent']; ?> items</div>
            <a href="#" class="alert-link" onclick="applyFilter('recent')">View →</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Feedback & Notes
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="performance">
        <input type="hidden" name="subpage" value="feedback-notes">
        <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Module</label>
                <select name="module">
                    <option value="all" <?php echo $module_filter == 'all' ? 'selected' : ''; ?>>All Modules</option>
                    <?php foreach ($module_config as $key => $module): ?>
                    <option value="<?php echo $key; ?>" <?php echo $module_filter == $key ? 'selected' : ''; ?>>
                        <?php echo $module['label']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Type</label>
                <select name="type">
                    <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                    <?php foreach ($type_config as $key => $type): ?>
                    <option value="<?php echo $key; ?>" <?php echo $type_filter == $key ? 'selected' : ''; ?>>
                        <?php echo $type['label']; ?>
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
                <input type="text" name="search" placeholder="Subject, content, creator..." value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=performance&subpage=feedback-notes&view=<?php echo $view_mode; ?>" class="btn btn-secondary btn-sm">
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
    <div class="feedback-grid">
        <?php foreach ($feedback_notes as $note): 
            $creator_name = $note['creator_name'] ?? 'System';
            $creator_initials = $note['creator_name'] ? 
                strtoupper(substr($note['creator_name'], 0, 1) . substr(strrchr($note['creator_name'], ' '), 1, 1)) : 'S';
            $creator_photo = fbGetUserPhoto(['profile_picture' => $note['creator_photo']]);
            
            // Target info
            $target_name = '';
            $target_role = '';
            $target_initials = '';
            $target_photo = null;
            
            if ($note['for_type'] == 'applicant' && $note['applicant_first_name']) {
                $target_name = $note['applicant_first_name'] . ' ' . $note['applicant_last_name'];
                $target_role = 'Applicant';
                $target_initials = strtoupper(substr($note['applicant_first_name'], 0, 1) . substr($note['applicant_last_name'], 0, 1));
                $target_photo = fbGetEmployeePhoto(['photo_path' => $note['applicant_photo']]);
            } elseif ($note['for_type'] == 'employee' && $note['employee_first_name']) {
                $target_name = $note['employee_first_name'] . ' ' . $note['employee_last_name'];
                $target_role = $note['target_position'] . ' (' . ucfirst($note['target_department']) . ')';
                $target_initials = strtoupper(substr($note['employee_first_name'], 0, 1) . substr($note['employee_last_name'], 0, 1));
                $target_photo = fbGetEmployeePhoto(['photo_path' => $note['employee_photo']]);
            } elseif ($note['for_type'] == 'user' && $note['target_user_name']) {
                $target_name = $note['target_user_name'];
                $target_role = ucfirst($note['target_username'] ?? 'User');
                $target_initials = strtoupper(substr($note['target_user_name'], 0, 1) . substr(strrchr($note['target_user_name'], ' '), 1, 1));
            }
            
            $type_info = $type_config[$note['type']] ?? $type_config['general'];
            $module_info = $module_config[$note['module']] ?? $module_config['general'];
            
            $card_class = '';
            if ($note['is_important']) $card_class .= ' important';
            if ($note['is_resolved']) $card_class .= ' resolved';
        ?>
        <div class="feedback-card<?php echo $card_class; ?>" id="feedback-<?php echo $note['id']; ?>">
            <div class="card-header">
                <?php if ($creator_photo): ?>
                    <img src="<?php echo $creator_photo; ?>" 
                         alt="<?php echo htmlspecialchars($creator_name); ?>"
                         class="card-avatar"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                         loading="lazy">
                    <div class="card-avatar" style="display: none;"><?php echo $creator_initials; ?></div>
                <?php else: ?>
                    <div class="card-avatar">
                        <?php echo $creator_initials; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card-info">
                    <div class="card-name"><?php echo htmlspecialchars($creator_name); ?></div>
                    <div class="card-meta">
                        <span><?php echo fbTimeAgo($note['created_at']); ?></span>
                        <span class="card-badge" style="background: <?php echo $module_info['color']; ?>20; color: <?php echo $module_info['color']; ?>;">
                            <i class="<?php echo $module_info['icon']; ?>"></i> <?php echo $module_info['label']; ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($note['is_important']): ?>
                <i class="fas fa-star" style="color: var(--warning);"></i>
                <?php endif; ?>
            </div>
            
            <div class="card-subject"><?php echo htmlspecialchars($note['subject']); ?></div>
            
            <?php if ($target_name): ?>
            <div class="target-info">
                <?php if ($target_photo): ?>
                    <img src="<?php echo $target_photo; ?>" class="target-avatar" style="object-fit: cover;">
                <?php else: ?>
                    <div class="target-avatar"><?php echo $target_initials ?: '?'; ?></div>
                <?php endif; ?>
                <div class="target-details">
                    <div class="target-name"><?php echo htmlspecialchars($target_name); ?></div>
                    <div class="target-role"><?php echo $target_role; ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-content" id="content-<?php echo $note['id']; ?>">
                <?php echo nl2br(htmlspecialchars($note['content'])); ?>
            </div>
            
            <?php if (strlen($note['content']) > 200): ?>
            <div class="read-more" onclick="toggleContent(<?php echo $note['id']; ?>)">
                Read more...
            </div>
            <?php endif; ?>
            
            <div class="card-footer">
                <div class="footer-left">
                    <span class="card-badge" style="background: <?php echo $type_info['color']; ?>20; color: <?php echo $type_info['color']; ?>;">
                        <i class="<?php echo $type_info['icon']; ?>"></i> <?php echo $type_info['label']; ?>
                    </span>
                    
                    <?php if ($note['reply_count'] > 0): ?>
                    <span class="reply-indicator" onclick="showReplies(<?php echo $note['id']; ?>)">
                        <i class="fas fa-reply"></i> <?php echo $note['reply_count']; ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="footer-right">
                    <span class="reaction-btn <?php echo $note['user_reaction'] ? 'active' : ''; ?>" 
                          onclick="addReaction(<?php echo $note['id']; ?>, '👍')">
                        <i class="fas fa-thumbs-up"></i>
                        <?php if ($note['reaction_count'] > 0): ?>
                        <span class="reaction-count"><?php echo $note['reaction_count']; ?></span>
                        <?php endif; ?>
                    </span>
                    
                    <span class="reaction-btn" onclick="openReplyModal(<?php echo $note['id']; ?>, '<?php echo addslashes($note['subject']); ?>')">
                        <i class="fas fa-reply"></i>
                    </span>
                    
                    <?php if ($note['created_by'] == $current_user_id || $_SESSION['role'] == 'admin'): ?>
                    <span class="reaction-btn" onclick="editFeedback(<?php echo $note['id']; ?>)">
                        <i class="fas fa-edit"></i>
                    </span>
                    <?php endif; ?>
                    
                    <?php if (!$note['is_resolved']): ?>
                    <span class="reaction-btn" onclick="resolveFeedback(<?php echo $note['id']; ?>)">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($feedback_notes)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 20px;">
            <i class="fas fa-comment-slash" style="font-size: 48px; color: var(--gray); opacity: 0.3;"></i>
            <h3 style="margin-top: 15px; color: var(--dark);">No Feedback Found</h3>
            <p style="color: var(--gray);">No feedback or notes match your current filters.</p>
            <button class="btn btn-primary" onclick="openAddFeedbackModal()" style="margin-top: 15px;">
                <i class="fas fa-plus"></i> Add New Feedback
            </button>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($view_mode == 'list'): ?>
    <!-- List View -->
    <div class="table-container">
        <table class="feedback-table">
            <thead>
                <tr>
                    <th>Creator</th>
                    <th>Subject</th>
                    <th>Module/Type</th>
                    <th>Target</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feedback_notes as $note): 
                    $creator_name = $note['creator_name'] ?? 'System';
                    $creator_initials = $note['creator_name'] ? 
                        strtoupper(substr($note['creator_name'], 0, 1) . substr(strrchr($note['creator_name'], ' '), 1, 1)) : 'S';
                    $creator_photo = fbGetUserPhoto(['profile_picture' => $note['creator_photo']]);
                    
                    $target_name = '';
                    if ($note['for_type'] == 'applicant' && $note['applicant_first_name']) {
                        $target_name = $note['applicant_first_name'] . ' ' . $note['applicant_last_name'];
                    } elseif ($note['for_type'] == 'employee' && $note['employee_first_name']) {
                        $target_name = $note['employee_first_name'] . ' ' . $note['employee_last_name'];
                    } elseif ($note['for_type'] == 'user' && $note['target_user_name']) {
                        $target_name = $note['target_user_name'];
                    }
                    
                    $type_info = $type_config[$note['type']] ?? $type_config['general'];
                    $module_info = $module_config[$note['module']] ?? $module_config['general'];
                    
                    $row_class = '';
                    if ($note['is_important']) $row_class = 'important-row';
                    if ($note['is_resolved']) $row_class = 'resolved-row';
                ?>
                <tr class="<?php echo $row_class; ?>" onclick="viewFeedback(<?php echo $note['id']; ?>)">
                    <td>
                        <div class="creator-info">
                            <?php if ($creator_photo): ?>
                                <img src="<?php echo $creator_photo; ?>" 
                                     alt="<?php echo htmlspecialchars($creator_name); ?>"
                                     class="table-avatar">
                            <?php else: ?>
                                <div class="table-avatar"><?php echo $creator_initials; ?></div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($creator_name); ?></strong>
                                <div style="font-size: 10px; color: var(--gray);"><?php echo ucfirst($note['creator_role'] ?? 'User'); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div>
                            <strong><?php echo htmlspecialchars($note['subject']); ?></strong>
                            <?php if ($note['is_important']): ?>
                            <i class="fas fa-star important-star" style="margin-left: 5px;"></i>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 11px; color: var(--gray); max-width: 250px; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo substr(htmlspecialchars($note['content']), 0, 100); ?>...
                        </div>
                    </td>
                    <td>
                        <span class="module-badge" style="background: <?php echo $module_info['color']; ?>20; color: <?php echo $module_info['color']; ?>;">
                            <i class="<?php echo $module_info['icon']; ?>"></i> <?php echo $module_info['label']; ?>
                        </span>
                        <br>
                        <span class="type-badge" style="background: <?php echo $type_info['color']; ?>20; color: <?php echo $type_info['color']; ?>;">
                            <i class="<?php echo $type_info['icon']; ?>"></i> <?php echo $type_info['label']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($target_name): ?>
                            <strong><?php echo htmlspecialchars($target_name); ?></strong>
                            <div style="font-size: 10px; color: var(--gray);"><?php echo ucfirst($note['for_type']); ?></div>
                        <?php else: ?>
                            <span style="color: var(--gray);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div><?php echo date('M d, Y', strtotime($note['created_at'])); ?></div>
                        <div style="font-size: 10px; color: var(--gray);"><?php echo fbTimeAgo($note['created_at']); ?></div>
                    </td>
                    <td>
                        <?php if ($note['is_resolved']): ?>
                        <span class="status-badge" style="background: #27ae6020; color: #27ae60;">Resolved</span>
                        <?php else: ?>
                        <span class="status-badge" style="background: #e74c3c20; color: #e74c3c;">Open</span>
                        <?php endif; ?>
                        
                        <?php if ($note['reply_count'] > 0): ?>
                        <br><span style="font-size: 10px; color: var(--gray);"><?php echo $note['reply_count']; ?> replies</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($feedback_notes)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <i class="fas fa-comment-slash" style="font-size: 32px; color: var(--gray); opacity: 0.3;"></i>
                        <p style="margin-top: 10px;">No feedback found</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($view_mode == 'analytics'): ?>
    <!-- Analytics View -->
    <div class="analytics-section">
        <!-- Feedback by Module Chart -->
        <div class="analytics-card">
            <div class="analytics-title">
                <i class="fas fa-chart-pie"></i> Feedback by Module
            </div>
            
            <div class="analytics-chart">
                <?php 
                $max_count = !empty($stats['by_module']) ? max($stats['by_module']) : 1;
                foreach ($module_config as $key => $module):
                    $count = $stats['by_module'][$key] ?? 0;
                    $height = ($count / $max_count) * 180;
                    if ($height < 30) $height = 30;
                ?>
                <div class="chart-bar-container">
                    <div class="chart-bar" style="height: <?php echo $height; ?>px;">
                        <div class="chart-bar-fill" style="height: <?php echo $height; ?>px; background: <?php echo $module['color']; ?>;"></div>
                    </div>
                    <div class="chart-label"><?php echo $module['label']; ?></div>
                    <div class="chart-value"><?php echo $count; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Feedback by Type Chart -->
        <div class="analytics-card">
            <div class="analytics-title">
                <i class="fas fa-chart-bar"></i> Feedback by Type
            </div>
            
            <div class="analytics-chart">
                <?php 
                $max_count = !empty($stats['by_type']) ? max($stats['by_type']) : 1;
                $type_limit = array_slice($type_config, 0, 8, true);
                foreach ($type_limit as $key => $type):
                    $count = $stats['by_type'][$key] ?? 0;
                    $height = ($count / $max_count) * 180;
                    if ($height < 30) $height = 30;
                ?>
                <div class="chart-bar-container">
                    <div class="chart-bar" style="height: <?php echo $height; ?>px;">
                        <div class="chart-bar-fill" style="height: <?php echo $height; ?>px; background: <?php echo $type['color']; ?>;"></div>
                    </div>
                    <div class="chart-label"><?php echo $type['label']; ?></div>
                    <div class="chart-value"><?php echo $count; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Activity Timeline -->
        <div class="analytics-card" style="grid-column: 1/-1;">
            <div class="analytics-title">
                <i class="fas fa-clock"></i> Recent Activity
            </div>
            
            <div class="timeline">
                <?php 
                $recent_feedback = array_slice($feedback_notes, 0, 10);
                foreach ($recent_feedback as $note):
                    $type_info = $type_config[$note['type']] ?? $type_config['general'];
                ?>
                <div class="timeline-item">
                    <div class="timeline-date"><?php echo fbTimeAgo($note['created_at']); ?></div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="status-badge" style="background: <?php echo $type_info['color']; ?>20; color: <?php echo $type_info['color']; ?>;">
                                <i class="<?php echo $type_info['icon']; ?>"></i> <?php echo $type_info['label']; ?>
                            </span>
                            <strong><?php echo htmlspecialchars($note['subject']); ?></strong>
                        </div>
                        <p style="margin: 5px 0 0; font-size: 12px;"><?php echo substr(htmlspecialchars($note['content']), 0, 150); ?>...</p>
                        <div style="margin-top: 8px; font-size: 11px; color: var(--gray);">
                            <i class="fas fa-user"></i> <?php echo $note['creator_name'] ?? 'System'; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="action-buttons">
    <button class="action-btn primary" onclick="openAddFeedbackModal()">
        <i class="fas fa-plus"></i> Add Feedback
    </button>
    <button class="action-btn success" onclick="openBulkActionModal()">
        <i class="fas fa-layer-group"></i> Bulk Actions
    </button>
    <button class="action-btn warning" onclick="openReminderModal()">
        <i class="fas fa-bell"></i> Set Reminder
    </button>
    <button class="action-btn info" onclick="openReportModal()">
        <i class="fas fa-file-pdf"></i> Export Report
    </button>
</div>

<!-- ==================== MODALS ==================== -->

<!-- Add Feedback Modal -->
<div id="addFeedbackModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Feedback / Note</h3>
            <span class="modal-close" onclick="closeAddFeedbackModal()">&times;</span>
        </div>
        
        <form method="POST" action="" id="feedbackForm">
            <input type="hidden" name="action" value="add_feedback">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Module <span style="color: var(--danger);">*</span></label>
                    <select name="module" required>
                        <option value="">Select Module</option>
                        <?php foreach ($module_config as $key => $module): ?>
                        <option value="<?php echo $key; ?>"><?php echo $module['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Type <span style="color: var(--danger);">*</span></label>
                    <select name="type" required>
                        <option value="">Select Type</option>
                        <?php foreach ($type_config as $key => $type): ?>
                        <option value="<?php echo $key; ?>"><?php echo $type['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Subject <span style="color: var(--danger);">*</span></label>
                <input type="text" name="subject" placeholder="Brief subject line" required>
            </div>
            
            <div class="form-group">
                <label>Content <span style="color: var(--danger);">*</span></label>
                <textarea name="content" rows="4" placeholder="Write your feedback or notes here..." required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>For Type</label>
                    <select name="for_type" id="for_type" onchange="toggleTargetSelect()">
                        <option value="none">None (General)</option>
                        <option value="applicant">Applicant</option>
                        <option value="employee">Employee</option>
                        <option value="user">User (Staff)</option>
                    </select>
                </div>
                
                <div class="form-group" id="target_applicant_group" style="display: none;">
                    <label>Select Applicant</label>
                    <select name="created_for">
                        <option value="">Select Applicant</option>
                        <?php foreach ($applicants as $app): ?>
                        <option value="<?php echo $app['id']; ?>">
                            <?php echo $app['first_name'] . ' ' . $app['last_name'] . ' (' . $app['application_number'] . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="target_employee_group" style="display: none;">
                    <label>Select Employee</label>
                    <select name="created_for">
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>">
                            <?php echo $emp['first_name'] . ' ' . $emp['last_name'] . ' (' . ($emp['employee_id'] ?: 'No ID') . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="target_user_group" style="display: none;">
                    <label>Select User</label>
                    <select name="created_for">
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo $user['full_name'] . ' (' . ucfirst($user['role']) . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="checkbox-group">
                <label class="checkbox-item">
                    <input type="checkbox" name="is_important" value="1"> 
                    <i class="fas fa-star" style="color: var(--warning);"></i> Mark as Important
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" name="is_private" value="1"> 
                    <i class="fas fa-lock" style="color: var(--gray);"></i> Private (Only visible to admins and creator)
                </label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddFeedbackModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Feedback</button>
            </div>
        </form>
    </div>
</div>

<!-- Reply Modal -->
<div id="replyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-reply"></i> Add Reply</h3>
            <span class="modal-close" onclick="closeReplyModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_reply">
            <input type="hidden" name="parent_id" id="reply_parent_id" value="">
            
            <div class="form-group">
                <label>Original Subject</label>
                <input type="text" id="reply_original_subject" readonly class="form-control" style="background: var(--light-gray);">
            </div>
            
            <div class="form-group">
                <label>Reply Subject</label>
                <input type="text" name="reply_subject" id="reply_subject" placeholder="Re: [original subject]" required>
            </div>
            
            <div class="form-group">
                <label>Your Reply <span style="color: var(--danger);">*</span></label>
                <textarea name="reply_content" rows="4" placeholder="Type your reply here..." required></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReplyModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Post Reply</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Feedback Modal -->
<div id="editFeedbackModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Feedback</h3>
            <span class="modal-close" onclick="closeEditFeedbackModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_feedback">
            <input type="hidden" name="feedback_id" id="edit_feedback_id" value="">
            
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" id="edit_subject" required>
            </div>
            
            <div class="form-group">
                <label>Content</label>
                <textarea name="content" id="edit_content" rows="4" required></textarea>
            </div>
            
            <div class="checkbox-group">
                <label class="checkbox-item">
                    <input type="checkbox" name="is_important" value="1" id="edit_important"> 
                    <i class="fas fa-star" style="color: var(--warning);"></i> Important
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" name="is_private" value="1" id="edit_private"> 
                    <i class="fas fa-lock" style="color: var(--gray);"></i> Private
                </label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditFeedbackModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-danger" onclick="deleteFeedback()">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- View Feedback Modal -->
<div id="viewFeedbackModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-eye"></i> Feedback Details</h3>
            <span class="modal-close" onclick="closeViewFeedbackModal()">&times;</span>
        </div>
        
        <div id="view_feedback_content">
            <!-- Loaded dynamically -->
        </div>
    </div>
</div>

<!-- Bulk Action Modal -->
<div id="bulkActionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group"></i> Bulk Actions</h3>
            <span class="modal-close" onclick="closeBulkActionModal()">&times;</span>
        </div>
        
        <p style="margin-bottom: 15px;">Select feedback items and choose an action:</p>
        
        <div class="form-group">
            <label>Action</label>
            <select id="bulk_action_type">
                <option value="resolve">Mark as Resolved</option>
                <option value="important">Mark as Important</option>
                <option value="unimportant">Remove Important Flag</option>
                <option value="delete">Delete Selected</option>
            </select>
        </div>
        
        <div class="employee-selection" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px; padding: 10px;">
            <?php foreach ($feedback_notes as $note): 
                $subject = $note['subject'];
                if (strlen($subject) > 50) $subject = substr($subject, 0, 50) . '...';
            ?>
            <div class="employee-checkbox">
                <input type="checkbox" class="bulk-item" value="<?php echo $note['id']; ?>" id="bulk_<?php echo $note['id']; ?>">
                <label for="bulk_<?php echo $note['id']; ?>">
                    <strong><?php echo htmlspecialchars($subject); ?></strong>
                    <br><small><?php echo $note['creator_name'] ?? 'System'; ?> • <?php echo date('M d', strtotime($note['created_at'])); ?></small>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin: 10px 0;">
            <button type="button" class="btn btn-sm" onclick="selectAllBulk()">Select All</button>
            <button type="button" class="btn btn-sm" onclick="deselectAllBulk()">Deselect All</button>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeBulkActionModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="executeBulkAction()">Execute</button>
        </div>
    </div>
</div>

<!-- Reminder Modal -->
<div id="reminderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-bell"></i> Set Reminder</h3>
            <span class="modal-close" onclick="closeReminderModal()">&times;</span>
        </div>
        
        <form id="reminderForm">
            <div class="form-group">
                <label>Select Feedback</label>
                <select id="reminder_feedback_id">
                    <option value="">Select Item</option>
                    <?php foreach ($feedback_notes as $note): ?>
                    <option value="<?php echo $note['id']; ?>">
                        <?php echo htmlspecialchars($note['subject']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Remind me on</label>
                <input type="date" id="reminder_date" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea id="reminder_notes" rows="2" placeholder="Why are you setting this reminder?"></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeReminderModal()">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="setReminder()">Set Reminder</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Modal -->
<div id="reportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-pdf"></i> Export Feedback Report</h3>
            <span class="modal-close" onclick="closeReportModal()">&times;</span>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="generate_report">
            
            <div class="form-group">
                <label>Report Type</label>
                <select name="report_type">
                    <option value="summary">Summary Report</option>
                    <option value="detailed">Detailed Report</option>
                    <option value="module">By Module</option>
                    <option value="type">By Type</option>
                    <option value="timeline">Timeline Report</option>
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
                <label>Module Filter</label>
                <select name="module">
                    <option value="">All Modules</option>
                    <?php foreach ($module_config as $key => $module): ?>
                    <option value="<?php echo $key; ?>"><?php echo $module['label']; ?></option>
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
                <button type="submit" class="btn btn-info">Export</button>
            </div>
        </form>
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

// Toggle content expand/collapse
function toggleContent(id) {
    const content = document.getElementById('content-' + id);
    const link = content.nextElementSibling;
    
    if (content.classList.contains('expanded')) {
        content.classList.remove('expanded');
        link.textContent = 'Read more...';
    } else {
        content.classList.add('expanded');
        link.textContent = 'Show less';
    }
}

// Apply filters
function applyFilter(type) {
    if (type === 'important') {
        window.location.href = '?page=performance&subpage=feedback-notes&view=<?php echo $view_mode; ?>&important=1';
    } else if (type === 'unresolved') {
        window.location.href = '?page=performance&subpage=feedback-notes&view=<?php echo $view_mode; ?>&resolved=0';
    } else if (type === 'recent') {
        window.location.href = '?page=performance&subpage=feedback-notes&view=<?php echo $view_mode; ?>&recent=1';
    }
}

function filterByModule(module) {
    window.location.href = '?page=performance&subpage=feedback-notes&view=<?php echo $view_mode; ?>&module=' + module;
}

// Toggle target select based on for_type
function toggleTargetSelect() {
    const forType = document.getElementById('for_type').value;
    
    document.getElementById('target_applicant_group').style.display = 'none';
    document.getElementById('target_employee_group').style.display = 'none';
    document.getElementById('target_user_group').style.display = 'none';
    
    if (forType === 'applicant') {
        document.getElementById('target_applicant_group').style.display = 'block';
    } else if (forType === 'employee') {
        document.getElementById('target_employee_group').style.display = 'block';
    } else if (forType === 'user') {
        document.getElementById('target_user_group').style.display = 'block';
    }
}

// ========== MODAL FUNCTIONS ==========

// Add Feedback Modal
function openAddFeedbackModal() {
    document.getElementById('addFeedbackModal').classList.add('active');
}

function closeAddFeedbackModal() {
    document.getElementById('addFeedbackModal').classList.remove('active');
    document.getElementById('feedbackForm').reset();
    toggleTargetSelect();
}

// Reply Modal
function openReplyModal(id, subject) {
    document.getElementById('replyModal').classList.add('active');
    document.getElementById('reply_parent_id').value = id;
    document.getElementById('reply_original_subject').value = subject;
    document.getElementById('reply_subject').value = 'Re: ' + subject;
}

function closeReplyModal() {
    document.getElementById('replyModal').classList.remove('active');
}

// Edit Feedback Modal
function editFeedback(id) {
    // In a real system, you'd fetch the data via AJAX
    // For demo, we'll set some sample data
    document.getElementById('editFeedbackModal').classList.add('active');
    document.getElementById('edit_feedback_id').value = id;
    document.getElementById('edit_subject').value = 'Sample feedback subject';
    document.getElementById('edit_content').value = 'Sample feedback content...';
}

function closeEditFeedbackModal() {
    document.getElementById('editFeedbackModal').classList.remove('active');
}

function deleteFeedback() {
    if (confirm('Are you sure you want to delete this feedback?')) {
        const id = document.getElementById('edit_feedback_id').value;
        
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_feedback">
            <input type="hidden" name="feedback_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// View Feedback Modal
function viewFeedback(id) {
    document.getElementById('viewFeedbackModal').classList.add('active');
    
    // In a real system, you'd load the full feedback with replies via AJAX
    document.getElementById('view_feedback_content').innerHTML = `
        <p>Loading feedback details...</p>
    `;
}

function closeViewFeedbackModal() {
    document.getElementById('viewFeedbackModal').classList.remove('active');
}

// Bulk Action Modal
function openBulkActionModal() {
    document.getElementById('bulkActionModal').classList.add('active');
}

function closeBulkActionModal() {
    document.getElementById('bulkActionModal').classList.remove('active');
}

function selectAllBulk() {
    document.querySelectorAll('.bulk-item').forEach(cb => cb.checked = true);
}

function deselectAllBulk() {
    document.querySelectorAll('.bulk-item').forEach(cb => cb.checked = false);
}

function executeBulkAction() {
    const selected = [];
    document.querySelectorAll('.bulk-item:checked').forEach(cb => {
        selected.push(cb.value);
    });
    
    if (selected.length === 0) {
        alert('Please select at least one item');
        return;
    }
    
    const action = document.getElementById('bulk_action_type').value;
    
    if (action === 'delete') {
        if (!confirm(`Are you sure you want to delete ${selected.length} items?`)) {
            return;
        }
    }
    
    alert(`Bulk action "${action}" would be applied to ${selected.length} items.`);
    closeBulkActionModal();
}

// Reminder Modal
function openReminderModal() {
    document.getElementById('reminderModal').classList.add('active');
}

function closeReminderModal() {
    document.getElementById('reminderModal').classList.remove('active');
}

function setReminder() {
    const feedbackId = document.getElementById('reminder_feedback_id').value;
    const date = document.getElementById('reminder_date').value;
    const notes = document.getElementById('reminder_notes').value;
    
    if (!feedbackId || !date) {
        alert('Please select a feedback item and date');
        return;
    }
    
    alert(`Reminder set for ${date}`);
    closeReminderModal();
}

// Report Modal
function openReportModal() {
    document.getElementById('reportModal').classList.add('active');
}

function closeReportModal() {
    document.getElementById('reportModal').classList.remove('active');
}

// Reactions
function addReaction(id, reaction) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_reaction&feedback_id=${id}&reaction=${reaction}&ajax=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Toggle button state
            const btn = event.currentTarget;
            btn.classList.toggle('active');
            
            // Update count
            const countSpan = btn.querySelector('.reaction-count');
            if (countSpan) {
                let count = parseInt(countSpan.textContent) || 0;
                if (btn.classList.contains('active')) {
                    count++;
                } else {
                    count--;
                }
                
                if (count > 0) {
                    if (!countSpan) {
                        btn.innerHTML += ' <span class="reaction-count">' + count + '</span>';
                    } else {
                        countSpan.textContent = count;
                    }
                } else {
                    if (countSpan) countSpan.remove();
                }
            } else if (btn.classList.contains('active')) {
                btn.innerHTML += ' <span class="reaction-count">1</span>';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Resolve feedback
function resolveFeedback(id) {
    if (confirm('Mark this feedback as resolved?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="resolve_feedback">
            <input type="hidden" name="feedback_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Show replies
function showReplies(id) {
    alert('View replies for feedback #' + id);
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