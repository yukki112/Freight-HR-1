<?php
// includes/config.php
session_start();

$host = 'localhost:3307'; // Change if needed
$dbname = 'freight_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function formatMoney($amount) {
    return '₱' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function timeAgo($datetime) {
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
        return date('M j, Y', $time);
    }
}

// Get user info
function getUserInfo($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Get HR Dashboard Stats
function getHRStats($pdo, $user_id) {
    $stats = [];
    
    // Total applicants
    $stmt = $pdo->query("SELECT COUNT(*) FROM applicants");
    $stats['total_applicants'] = $stmt->fetchColumn();
    
    // New applicants today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE DATE(application_date) = CURDATE()");
    $stmt->execute();
    $stats['new_applicants_today'] = $stmt->fetchColumn();
    
    // Active job postings
    $stmt = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'published'");
    $stats['active_jobs'] = $stmt->fetchColumn();
    
    // Pending interviews
    $stmt = $pdo->query("SELECT COUNT(*) FROM interviews WHERE status = 'scheduled' AND interview_date >= CURDATE()");
    $stats['pending_interviews'] = $stmt->fetchColumn();
    
    // New hires in onboarding
    $stmt = $pdo->query("SELECT COUNT(*) FROM new_hires WHERE status = 'onboarding'");
    $stats['onboarding_count'] = $stmt->fetchColumn();
    
    // Active employees (probationary + regular)
    $stmt = $pdo->query("SELECT COUNT(*) FROM new_hires WHERE status = 'active'");
    $stats['active_employees'] = $stmt->fetchColumn();
    
    // Pending document verifications
    $stmt = $pdo->query("SELECT COUNT(*) FROM applicant_documents WHERE verified = FALSE");
    $stats['pending_verifications'] = $stmt->fetchColumn();
    
    // Upcoming probation reviews
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM new_hires WHERE probation_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute();
    $stats['upcoming_reviews'] = $stmt->fetchColumn();
    
    return $stats;
}

// Get recent applicants
function getRecentApplicants($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT a.*, j.title as job_title 
        FROM applicants a
        LEFT JOIN job_postings j ON a.position_applied = j.job_code
        ORDER BY a.created_at DESC 
        LIMIT " . intval($limit)
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get upcoming interviews
function getUpcomingInterviews($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT i.*, 
               CONCAT(a.first_name, ' ', a.last_name) as applicant_name,
               a.position_applied,
               j.title as job_title,
               u.full_name as interviewer_name
        FROM interviews i
        JOIN applicants a ON i.applicant_id = a.id
        LEFT JOIN job_postings j ON i.job_posting_id = j.id
        LEFT JOIN users u ON i.interviewer_id = u.id
        WHERE i.status = 'scheduled' AND i.interview_date >= CURDATE()
        ORDER BY i.interview_date ASC, i.interview_time ASC
        LIMIT " . intval($limit)
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get onboarding list
function getOnboardingList($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT nh.*, 
               CONCAT(a.first_name, ' ', a.last_name) as employee_name,
               a.email, a.phone,
               j.title as job_title
        FROM new_hires nh
        JOIN applicants a ON nh.applicant_id = a.id
        LEFT JOIN job_postings j ON nh.job_posting_id = j.id
        WHERE nh.status = 'onboarding'
        ORDER BY nh.start_date ASC
        LIMIT " . intval($limit)
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get recent recognitions
function getRecentRecognitions($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT r.*, 
               CONCAT(a.first_name, ' ', a.last_name) as employee_name,
               u.full_name as recognizer_name
        FROM recognitions r
        JOIN new_hires nh ON r.employee_id = nh.id
        JOIN applicants a ON nh.applicant_id = a.id
        LEFT JOIN users u ON r.recognizer_id = u.id
        WHERE r.published = TRUE
        ORDER BY r.created_at DESC
        LIMIT " . intval($limit)
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get pending document verifications
function getPendingVerifications($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT d.*, 
               CONCAT(a.first_name, ' ', a.last_name) as applicant_name,
               a.position_applied
        FROM applicant_documents d
        JOIN applicants a ON d.applicant_id = a.id
        WHERE d.verified = FALSE
        ORDER BY d.uploaded_at ASC
        LIMIT " . intval($limit)
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get user notifications
function getUserNotifications($pdo, $user_id, $limit = 10, $unread_only = false) {
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    if ($unread_only) {
        $sql .= " AND is_read = FALSE";
    }
    $sql .= " ORDER BY created_at DESC LIMIT " . intval($limit);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Get unread notification count
function getUnreadNotificationCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Get applicant status badge class
function getApplicantStatusBadge($status) {
    $badges = [
        'new' => 'info',
        'in_review' => 'primary',
        'shortlisted' => 'success',
        'interviewed' => 'info',
        'offered' => 'warning',
        'hired' => 'success',
        'rejected' => 'danger',
        'on_hold' => 'secondary'
    ];
    return $badges[$status] ?? 'secondary';
}

// Generate application number
function generateApplicationNumber() {
    $year = date('Y');
    $month = date('m');
    $random = strtoupper(substr(uniqid(), -6));
    return "APP-{$year}{$month}-{$random}";
}

// Generate employee ID
function generateEmployeeID() {
    $year = date('Y');
    $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return "EMP-{$year}-{$random}";
}

// Log activity
function logActivity($pdo, $user_id, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $description, $ip, $user_agent]);
}
?>