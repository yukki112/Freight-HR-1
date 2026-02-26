<?php
// modules/recruitment/interview-scheduling.php
$page_title = "Interview Scheduling";

// Include required files
require_once 'config/mail_config.php';

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

// Fixed meeting links for each interview round (ONLY for Online mode)
$fixed_meeting_links = [
    'initial' => 'https://meet.google.com/dor-rpqx-ben',
    'technical' => 'https://meet.google.com/atz-arcu-zjf',
    'hr' => 'https://meet.google.com/wvk-mzpy-ggw',
    'final' => 'https://meet.google.com/syy-vbmr-mga'
];

// Simple log function (replacement for logActivity)
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
        // Silently fail - logging shouldn't break the main functionality
    }
}

/**
 * Handle schedule interview (single)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_single'])) {
    try {
        $pdo->beginTransaction();
        
        // Get applicant details from job_applications table
        $stmt = $pdo->prepare("
            SELECT ja.*, jp.title as position_title, jp.id as job_posting_id
            FROM job_applications ja
            LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
            WHERE ja.id = ?
        ");
        $stmt->execute([$_POST['applicant_id']]);
        $applicant = $stmt->fetch();
        
        if (!$applicant) {
            throw new Exception("Applicant not found");
        }
        
        $interview_type = $_POST['interview_type'];
        $interview_round = $_POST['interview_round'];
        
        // Set meeting link ONLY if online
        $meeting_link = null;
        if ($interview_type === 'Online') {
            $meeting_link = $fixed_meeting_links[$interview_round] ?? '';
        }
        
        // Set location ONLY if face-to-face
        $location = null;
        if ($interview_type === 'Face-to-Face') {
            $location = $_POST['location'] ?? 'Main Office';
            if (empty($location)) {
                throw new Exception("Location is required for face-to-face interviews");
            }
        }
        
        // Insert interview - referencing job_applications.id
        $stmt = $pdo->prepare("
            INSERT INTO interviews (
                applicant_id, job_posting_id, interviewer_id, interview_date,
                interview_time, interview_type, location, meeting_link,
                status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, ?)
        ");
        
        $stmt->execute([
            $_POST['applicant_id'], // This is from job_applications.id
            $applicant['job_posting_id'],
            $_POST['interviewer_id'] ?: null,
            $_POST['interview_date'],
            $_POST['interview_time'],
            $interview_round,
            $location,
            $meeting_link,
            $_POST['notes'] ?: null,
            $_SESSION['user_id']
        ]);
        
        $interview_id = $pdo->lastInsertId();
        
        // Prepare interview data for email
        $interview_data = [
            'applicant_name' => $applicant['first_name'] . ' ' . $applicant['last_name'],
            'position' => $applicant['position_title'] ?: 'General Application',
            'interview_round' => $interview_round,
            'interview_date' => $_POST['interview_date'],
            'interview_time' => $_POST['interview_time'],
            'interview_type' => $interview_type,
            'interview_panel' => getInterviewerNames($pdo, $_POST['interviewer_id']),
            'location' => $location ?: 'To be advised',
            'meeting_link' => $meeting_link
        ];
        
        // Send email notification
        $email_result = sendInterviewEmail(
            $applicant['email'],
            $applicant['first_name'] . ' ' . $applicant['last_name'],
            $interview_data,
            $interview_id
        );
        
        // Log communication
        $stmt = $pdo->prepare("
            INSERT INTO communication_log (
                applicant_id, communication_type, subject, message, sent_by, status
            ) VALUES (?, 'email', ?, ?, ?, ?)
        ");
        
        $subject = "Interview Schedule: {$applicant['position_title']}";
        $email_message = "Interview scheduled on " . date('F d, Y', strtotime($_POST['interview_date'])) . 
                        " at " . date('h:i A', strtotime($_POST['interview_time']));
        
        $stmt->execute([
            $_POST['applicant_id'],
            $subject,
            $email_message,
            $_SESSION['user_id'],
            $email_result['success'] ? 'sent' : 'failed'
        ]);
        
        // Update applicant status to interviewed
        $stmt = $pdo->prepare("
            UPDATE job_applications 
            SET status = 'interviewed', updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$_POST['applicant_id']]);
        
        // Add note to applicant
        $note = "[" . date('Y-m-d H:i') . "] Interview scheduled: {$interview_round} on " . 
                date('F d, Y', strtotime($_POST['interview_date'])) . 
                " at " . date('h:i A', strtotime($_POST['interview_time']));
        
        if ($interview_type === 'Online' && $meeting_link) {
            $note .= " - Link: {$meeting_link}";
        } elseif ($interview_type === 'Face-to-Face' && $location) {
            $note .= " - Location: {$location}";
        }
        
        $stmt = $pdo->prepare("
            UPDATE job_applications 
            SET notes = CONCAT(IFNULL(notes, ''), '\n', ?) 
            WHERE id = ?
        ");
        $stmt->execute([$note, $_POST['applicant_id']]);
        
        $pdo->commit();
        
        // Simple log instead of logActivity
        simpleLog($pdo, $_SESSION['user_id'], 'schedule_interview', 
            "Scheduled interview for applicant #{$_POST['applicant_id']}");
        
        $message = "Interview scheduled successfully! ";
        $message .= $email_result['success'] 
            ? "✅ Notification email sent to applicant." 
            : "⚠️ Warning: " . $email_result['message'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle bulk schedule
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_bulk'])) {
    try {
        $selected = $_POST['selected_candidates'] ?? [];
        $interview_date = $_POST['bulk_interview_date'] ?? '';
        $interview_time = $_POST['bulk_interview_time'] ?? '';
        $interview_round = $_POST['bulk_interview_round'] ?? 'initial';
        $interview_type = $_POST['bulk_interview_type'] ?? 'Online';
        $interviewer_id = $_POST['bulk_interviewer_id'] ?? null;
        $location = $_POST['bulk_location'] ?? '';
        
        if (empty($selected)) {
            throw new Exception("No candidates selected");
        }
        
        if (empty($interview_date) || empty($interview_time)) {
            throw new Exception("Interview date and time are required");
        }
        
        if ($interview_type === 'Face-to-Face' && empty($location)) {
            throw new Exception("Location is required for face-to-face interviews");
        }
        
        // Set meeting link ONLY if online
        $meeting_link = null;
        if ($interview_type === 'Online') {
            $meeting_link = $fixed_meeting_links[$interview_round] ?? '';
        }
        
        $pdo->beginTransaction();
        
        $success_count = 0;
        $failed_count = 0;
        $email_success_count = 0;
        
        foreach ($selected as $applicant_id) {
            try {
                // Get applicant details
                $stmt = $pdo->prepare("
                    SELECT ja.*, jp.title as position_title, jp.id as job_posting_id
                    FROM job_applications ja
                    LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
                    WHERE ja.id = ?
                ");
                $stmt->execute([$applicant_id]);
                $applicant = $stmt->fetch();
                
                if (!$applicant) continue;
                
                // Insert interview
                $stmt = $pdo->prepare("
                    INSERT INTO interviews (
                        applicant_id, job_posting_id, interviewer_id, interview_date,
                        interview_time, interview_type, location, meeting_link, status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
                ");
                
                $stmt->execute([
                    $applicant_id,
                    $applicant['job_posting_id'],
                    $interviewer_id,
                    $interview_date,
                    $interview_time,
                    $interview_round,
                    $location,
                    $meeting_link,
                    $_SESSION['user_id']
                ]);
                
                $interview_id = $pdo->lastInsertId();
                
                // Prepare interview data
                $interview_data = [
                    'applicant_name' => $applicant['first_name'] . ' ' . $applicant['last_name'],
                    'position' => $applicant['position_title'] ?: 'General Application',
                    'interview_round' => $interview_round,
                    'interview_date' => $interview_date,
                    'interview_time' => $interview_time,
                    'interview_type' => $interview_type,
                    'interview_panel' => getInterviewerNames($pdo, $interviewer_id),
                    'location' => $location ?: 'To be advised',
                    'meeting_link' => $meeting_link
                ];
                
                // Send email
                $email_result = sendInterviewEmail(
                    $applicant['email'],
                    $applicant['first_name'] . ' ' . $applicant['last_name'],
                    $interview_data,
                    $interview_id
                );
                
                if ($email_result['success']) {
                    $email_success_count++;
                }
                
                // Update applicant status
                $stmt = $pdo->prepare("
                    UPDATE job_applications SET status = 'interviewed', updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$applicant_id]);
                
                $success_count++;
                
            } catch (Exception $e) {
                $failed_count++;
            }
        }
        
        $pdo->commit();
        
        $message = "✅ Successfully scheduled {$success_count} interviews. ";
        $message .= "📧 Emails sent to {$email_success_count} applicants.";
        if ($failed_count > 0) {
            $message .= " ❌ Failed: {$failed_count}";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle reschedule
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_interview'])) {
    try {
        $pdo->beginTransaction();
        
        // Get interview details
        $stmt = $pdo->prepare("
            SELECT i.*, ja.first_name, ja.last_name, ja.email, jp.title as position_title
            FROM interviews i
            JOIN job_applications ja ON i.applicant_id = ja.id
            LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
            WHERE i.id = ?
        ");
        $stmt->execute([$_POST['interview_id']]);
        $interview = $stmt->fetch();
        
        if (!$interview) {
            throw new Exception("Interview not found");
        }
        
        $interview_type = $_POST['interview_type'];
        $interview_round = $_POST['interview_round'];
        
        // Set meeting link ONLY if online
        $meeting_link = null;
        if ($interview_type === 'Online') {
            $meeting_link = $fixed_meeting_links[$interview_round] ?? $interview['meeting_link'];
        }
        
        // Set location ONLY if face-to-face
        $location = null;
        if ($interview_type === 'Face-to-Face') {
            $location = $_POST['location'] ?? $interview['location'];
            if (empty($location)) {
                throw new Exception("Location is required for face-to-face interviews");
            }
        }
        
        // Update interview
        $stmt = $pdo->prepare("
            UPDATE interviews SET
                interview_date = ?,
                interview_time = ?,
                interview_type = ?,
                location = ?,
                meeting_link = ?,
                status = 'scheduled',
                notes = CONCAT(IFNULL(notes, ''), '\n[" . date('Y-m-d H:i') . "] Rescheduled')
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['interview_date'],
            $_POST['interview_time'],
            $interview_round,
            $location,
            $meeting_link,
            $_POST['interview_id']
        ]);
        
        // Send reschedule notification
        $interview_data = [
            'applicant_name' => $interview['first_name'] . ' ' . $interview['last_name'],
            'position' => $interview['position_title'],
            'interview_round' => $interview_round,
            'interview_date' => $_POST['interview_date'],
            'interview_time' => $_POST['interview_time'],
            'interview_type' => $interview_type,
            'interview_panel' => getInterviewerNames($pdo, $interview['interviewer_id']),
            'location' => $location ?: 'To be advised',
            'meeting_link' => $meeting_link
        ];
        
        sendInterviewEmail(
            $interview['email'],
            $interview['first_name'] . ' ' . $interview['last_name'],
            $interview_data,
            $_POST['interview_id']
        );
        
        $pdo->commit();
        
        $message = "✅ Interview rescheduled successfully! Notification sent to applicant.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle cancel interview
 */
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE interviews SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        
        $message = "✅ Interview cancelled successfully";
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle complete interview
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_interview'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE interviews SET
                status = 'completed',
                feedback = ?,
                rating = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['feedback'],
            $_POST['rating'],
            $_POST['interview_id']
        ]);
        
        $message = "✅ Interview marked as completed";
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Helper Functions
 */
function getInterviewerNames($pdo, $interviewer_ids) {
    if (empty($interviewer_ids)) return 'HR Team';
    
    $ids = is_array($interviewer_ids) ? $interviewer_ids : explode(',', $interviewer_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $pdo->prepare("
        SELECT full_name FROM users WHERE id IN ($placeholders)
    ");
    $stmt->execute($ids);
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return implode(', ', $names);
}

function getApplicantPhoto($applicant) {
    if (!empty($applicant['photo_path']) && file_exists($applicant['photo_path'])) {
        return htmlspecialchars($applicant['photo_path']);
    }
    return null;
}

// Get shortlisted applicants (from job_applications table)
$stmt = $pdo->query("
    SELECT ja.*, jp.title as position_title, jp.department
    FROM job_applications ja
    LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
    WHERE ja.status IN ('shortlisted', 'interviewed')
    ORDER BY ja.updated_at DESC
");
$shortlisted = $stmt->fetchAll();

// Get all interviews with details
$query = "
    SELECT 
        i.*,
        ja.first_name,
        ja.last_name,
        ja.email,
        ja.phone,
        ja.photo_path,
        jp.title as position_title,
        jp.job_code,
        jp.department,
        u.full_name as interviewer_name,
        u.email as interviewer_email
    FROM interviews i
    JOIN job_applications ja ON i.applicant_id = ja.id
    LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
    LEFT JOIN users u ON i.interviewer_id = u.id
    WHERE 1=1
";

$params = [];

// Apply filters
if (!empty($status_filter) && $status_filter !== 'all') {
    $query .= " AND i.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $query .= " AND i.interview_date = ?";
    $params[] = $date_filter;
}

if (!empty($search_filter)) {
    $query .= " AND (ja.first_name LIKE ? OR ja.last_name LIKE ? OR jp.title LIKE ? OR ja.email LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY i.interview_date ASC, i.interview_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$interviews = $stmt->fetchAll();

// Get statistics
$stats = [];

// Upcoming today
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM interviews 
    WHERE status = 'scheduled' AND interview_date = CURDATE()
");
$stmt->execute();
$stats['today'] = $stmt->fetchColumn();

// Upcoming this week
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM interviews 
    WHERE status = 'scheduled' 
    AND interview_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute();
$stats['week'] = $stmt->fetchColumn();

// Total scheduled
$stmt = $pdo->prepare("SELECT COUNT(*) FROM interviews WHERE status = 'scheduled'");
$stmt->execute();
$stats['scheduled'] = $stmt->fetchColumn();

// Completed
$stmt = $pdo->prepare("SELECT COUNT(*) FROM interviews WHERE status = 'completed'");
$stmt->execute();
$stats['completed'] = $stmt->fetchColumn();

// Average rating
$stmt = $pdo->prepare("SELECT AVG(rating) FROM interviews WHERE rating IS NOT NULL");
$stmt->execute();
$stats['avg_rating'] = round($stmt->fetchColumn() ?: 0, 1);

// Get interviewers list
$stmt = $pdo->query("
    SELECT id, full_name, role, email 
    FROM users 
    WHERE role IN ('admin', 'dispatcher') 
    ORDER BY full_name
");
$interviewers = $stmt->fetchAll();

// Group interviews by date
$today_interviews = array_filter($interviews, function($i) {
    return $i['interview_date'] == date('Y-m-d') && $i['status'] == 'scheduled';
});

$upcoming_interviews = array_filter($interviews, function($i) {
    return $i['interview_date'] > date('Y-m-d') && $i['status'] == 'scheduled';
});

$past_interviews = array_filter($interviews, function($i) {
    return $i['interview_date'] < date('Y-m-d') || $i['status'] != 'scheduled';
});
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
    --dark: #2c3e50;
    --gray: #64748b;
    --light-gray: #f8fafd;
    --border: #eef2f6;
}

/* Layout */
.page-header {
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
    box-shadow: 0 10px 20px var(--primary-transparent-2);
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
    display: flex;
    align-items: center;
    gap: 5px;
}

.stat-small i {
    font-size: 12px;
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-item label {
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-item input,
.filter-item select {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
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
    margin-top: 20px;
}

/* Buttons */
.btn {
    padding: 12px 24px;
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
    border: 1px solid transparent;
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
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #219a52;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
}

.btn-warning {
    background: var(--warning);
    color: white;
}

.btn-warning:hover {
    background: #e67e22;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(243, 156, 18, 0.3);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(231, 76, 60, 0.3);
}

.btn-info {
    background: var(--info);
    color: white;
}

.btn-info:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--primary);
    color: var(--primary);
}

.btn-outline:hover {
    background: var(--primary);
    color: white;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

/* Cards */
.interviews-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.interview-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.interview-card.today {
    border-left: 4px solid var(--warning);
    background: linear-gradient(to right, #fff9e6, white);
}

.interview-card.urgent {
    border-left: 4px solid var(--danger);
    background: linear-gradient(to right, #fee9e7, white);
}

.interview-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.applicant-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.applicant-photo {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    object-fit: cover;
    border: 2px solid white;
    box-shadow: 0 5px 15px var(--primary-transparent-2);
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 18px;
}

.applicant-details h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 3px 0;
}

.applicant-details p {
    font-size: 12px;
    color: var(--gray);
    margin: 2px 0;
}

.applicant-details i {
    width: 14px;
    color: var(--primary);
}

.status-badge {
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-scheduled {
    background: var(--info)20;
    color: var(--info);
}

.status-completed {
    background: var(--success)20;
    color: var(--success);
}

.status-cancelled {
    background: var(--danger)20;
    color: var(--danger);
}

.status-rescheduled {
    background: var(--warning)20;
    color: var(--warning);
}

.card-body {
    margin: 15px 0;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    padding: 8px 0;
    border-bottom: 1px dashed var(--border);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-icon {
    width: 30px;
    height: 30px;
    background: var(--light-gray);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 14px;
}

.detail-content {
    flex: 1;
}

.detail-label {
    font-size: 11px;
    color: var(--gray);
    margin-bottom: 2px;
}

.detail-value {
    font-size: 14px;
    font-weight: 500;
    color: var(--dark);
}

.meeting-link-box {
    background: var(--light-gray);
    border-radius: 12px;
    padding: 12px;
    margin-top: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid var(--border);
}

.meeting-link-box i {
    color: var(--primary);
    font-size: 18px;
}

.meeting-link-box input {
    flex: 1;
    border: none;
    background: transparent;
    color: var(--primary);
    font-size: 12px;
    padding: 5px;
    outline: none;
}

.copy-btn {
    background: white;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    color: var(--primary);
    cursor: pointer;
    transition: all 0.3s;
}

.copy-btn:hover {
    background: var(--primary);
    color: white;
}

.card-footer {
    display: flex;
    gap: 2px;
    justify-content: flex-end;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

/* Shortlisted List */
.shortlisted-list {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-top: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.list-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.list-header h2 i {
    color: var(--primary);
}

.shortlisted-items {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.shortlisted-item {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s;
    border: 1px solid var(--border);
}

.shortlisted-item:hover {
    transform: translateX(5px);
    background: white;
    border-color: var(--primary);
}

.shortlisted-item .checkbox {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
}

.shortlisted-item .photo {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

.shortlisted-item .info {
    flex: 1;
}

.shortlisted-item .info h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 3px;
}

.shortlisted-item .info p {
    font-size: 12px;
    color: var(--gray);
    margin: 0;
}

.shortlisted-item .info i {
    width: 12px;
    color: var(--primary);
}

.shortlisted-item .badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    background: var(--primary-transparent);
    color: var(--primary);
}

/* Bulk Actions */
.bulk-actions {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 2px dashed var(--primary);
}

.bulk-actions h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.bulk-actions h3 i {
    color: var(--primary);
}

.bulk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
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
    z-index: 9999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 30px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 30px 60px rgba(0,0,0,0.3);
    position: relative;
    animation: modalPop 0.3s;
}

@keyframes modalPop {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border);
}

.modal-header h3 {
    font-size: 22px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    font-size: 28px;
    cursor: pointer;
    color: var(--gray);
    transition: all 0.3s;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}

.modal-close:hover {
    background: var(--danger)20;
    color: var(--danger);
    transform: rotate(90deg);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: 14px;
    font-size: 14px;
    transition: all 0.3s;
    background: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 25px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

/* Alert Messages */
.alert-success {
    background: #d4edda;
    color: #155724;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border: 1px solid #c3e6cb;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.3s;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border: 1px solid #f5c6cb;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.3s;
}

@keyframes slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .interviews-grid {
        grid-template-columns: 1fr;
    }
    
    .bulk-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<!-- ==================== HTML CONTENT ==================== -->

<!-- Messages -->
<?php if ($message): ?>
<div class="alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-title">
        <i class="fas fa-calendar-alt"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-success btn-sm" onclick="showBulkScheduleModal()">
            <i class="fas fa-layer-group"></i> Bulk Schedule
        </button>
        <a href="?page=recruitment&subpage=shortlisted-candidates" class="btn btn-primary btn-sm">
            <i class="fas fa-users"></i> View Shortlisted
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Today's Interviews</span>
            <span class="stat-value"><?php echo $stats['today']; ?></span>
            <div class="stat-small">
                <i class="fas fa-clock" style="color: var(--warning);"></i>
                <?php echo count($today_interviews); ?> scheduled
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">This Week</span>
            <span class="stat-value"><?php echo $stats['week']; ?></span>
            <div class="stat-small">
                <i class="fas fa-arrow-up" style="color: var(--success);"></i>
                Upcoming
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Scheduled</span>
            <span class="stat-value"><?php echo $stats['scheduled']; ?></span>
            <div class="stat-small">
                <i class="fas fa-calendar-check"></i> Total pending
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Completed</span>
            <span class="stat-value"><?php echo $stats['completed']; ?></span>
            <div class="stat-small">
                <i class="fas fa-star" style="color: var(--warning);"></i>
                Avg. Rating: <?php echo $stats['avg_rating']; ?>/10
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Interviews
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="recruitment">
        <input type="hidden" name="subpage" value="interview-scheduling">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Interviews</option>
                    <option value="scheduled" <?php echo $status_filter == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Date</label>
                <input type="date" name="date" value="<?php echo $date_filter; ?>">
            </div>
            
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, position, email..." value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=recruitment&subpage=interview-scheduling" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Today's Interviews Section -->
<?php if (!empty($today_interviews)): ?>
<div style="margin-bottom: 30px;">
    <h2 style="font-size: 18px; font-weight: 600; color: var(--dark); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-sun" style="color: var(--warning);"></i> Today's Interviews
    </h2>
    
    <div class="interviews-grid">
        <?php foreach ($today_interviews as $interview): 
            $photoPath = getApplicantPhoto($interview);
            $firstName = $interview['first_name'] ?? '';
            $lastName = $interview['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
            $time_remaining = strtotime($interview['interview_date'] . ' ' . $interview['interview_time']) - time();
            $is_urgent = $time_remaining < 3600 && $time_remaining > 0; // Less than 1 hour
        ?>
        <div class="interview-card today <?php echo $is_urgent ? 'urgent' : ''; ?>">
            <div class="card-header">
                <div class="applicant-info">
                    <?php if ($photoPath): ?>
                        <img src="<?php echo $photoPath; ?>" 
                             alt="<?php echo htmlspecialchars($fullName); ?>"
                             class="applicant-photo"
                             onerror="this.src='assets/img/default-avatar.png'"
                             loading="lazy">
                    <?php else: ?>
                        <div class="applicant-photo">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="applicant-details">
                        <h3><?php echo htmlspecialchars($fullName); ?></h3>
                        <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($interview['position_title'] ?: 'General Application'); ?></p>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($interview['email']); ?></p>
                    </div>
                </div>
                
                <span class="status-badge status-scheduled">
                    <i class="fas fa-clock"></i> Today
                </span>
            </div>
            
            <div class="card-body">
                <div class="detail-row">
                    <div class="detail-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="detail-content">
                        <div class="detail-label">Time</div>
                        <div class="detail-value"><?php echo date('h:i A', strtotime($interview['interview_time'])); ?></div>
                    </div>
                    <?php if ($is_urgent): ?>
                    <span style="color: var(--danger); font-size: 12px;">
                        <i class="fas fa-exclamation-circle"></i> Soon
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="detail-row">
                    <div class="detail-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="detail-content">
                        <div class="detail-label">Interviewer</div>
                        <div class="detail-value"><?php echo $interview['interviewer_name'] ?: 'HR Team'; ?></div>
                    </div>
                </div>
                
                <?php if ($interview['meeting_link']): ?>
                <div class="meeting-link-box">
                    <i class="fas fa-video"></i>
                    <input type="text" value="<?php echo $interview['meeting_link']; ?>" readonly>
                    <button class="copy-btn" onclick="copyToClipboard('<?php echo $interview['meeting_link']; ?>')">
                        <i class="fas fa-copy"></i>
                    </button>
                    <a href="<?php echo $interview['meeting_link']; ?>" target="_blank" class="copy-btn" style="text-decoration: none;">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
                <?php elseif ($interview['location']): ?>
                <div class="meeting-link-box" style="background: var(--light-gray);">
                    <i class="fas fa-map-marker-alt" style="color: var(--danger);"></i>
                    <input type="text" value="<?php echo htmlspecialchars($interview['location']); ?>" readonly>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card-footer">
                <button class="btn btn-info btn-sm" onclick="viewInterview(<?php echo htmlspecialchars(json_encode($interview)); ?>)">
                    <i class="fas fa-eye"></i> View
                </button>
                <button class="btn btn-warning btn-sm" onclick="rescheduleInterview(<?php echo htmlspecialchars(json_encode($interview)); ?>)">
                    <i class="fas fa-clock"></i> Reschedule
                </button>
                <?php if ($interview['interview_date'] == date('Y-m-d')): ?>
                <button class="btn btn-success btn-sm" onclick="completeInterview(<?php echo $interview['id']; ?>)">
                    <i class="fas fa-check"></i> Complete
                </button>
                <?php endif; ?>
                <button class="btn btn-danger btn-sm" onclick="cancelInterview(<?php echo $interview['id']; ?>)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Upcoming Interviews -->
<?php if (!empty($upcoming_interviews)): ?>
<div style="margin-bottom: 30px;">
    <h2 style="font-size: 18px; font-weight: 600; color: var(--dark); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-calendar-alt" style="color: var(--primary);"></i> Upcoming Interviews
    </h2>
    
    <div class="interviews-grid">
        <?php foreach ($upcoming_interviews as $interview): 
            $photoPath = getApplicantPhoto($interview);
            $firstName = $interview['first_name'] ?? '';
            $lastName = $interview['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
        ?>
        <div class="interview-card">
            <div class="card-header">
                <div class="applicant-info">
                    <?php if ($photoPath): ?>
                        <img src="<?php echo $photoPath; ?>" 
                             alt="<?php echo htmlspecialchars($fullName); ?>"
                             class="applicant-photo"
                             onerror="this.src='assets/img/default-avatar.png'"
                             loading="lazy">
                    <?php else: ?>
                        <div class="applicant-photo">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="applicant-details">
                        <h3><?php echo htmlspecialchars($fullName); ?></h3>
                        <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($interview['position_title'] ?: 'General Application'); ?></p>
                    </div>
                </div>
                
                <span class="status-badge status-scheduled">
                    <i class="fas fa-calendar"></i> <?php echo date('M d', strtotime($interview['interview_date'])); ?>
                </span>
            </div>
            
            <div class="card-body">
                <div class="detail-row">
                    <div class="detail-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="detail-content">
                        <div class="detail-label">Date & Time</div>
                        <div class="detail-value">
                            <?php echo date('F d, Y', strtotime($interview['interview_date'])); ?> at 
                            <?php echo date('h:i A', strtotime($interview['interview_time'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-icon">
                        <i class="fas fa-tag"></i>
                    </div>
                    <div class="detail-content">
                        <div class="detail-label">Interview Round</div>
                        <div class="detail-value"><?php echo ucfirst($interview['interview_type']); ?></div>
                    </div>
                </div>
                
                <?php if ($interview['meeting_link']): ?>
                <div class="meeting-link-box">
                    <i class="fas fa-video"></i>
                    <input type="text" value="<?php echo $interview['meeting_link']; ?>" readonly>
                </div>
                <?php elseif ($interview['location']): ?>
                <div class="meeting-link-box" style="background: var(--light-gray);">
                    <i class="fas fa-map-marker-alt" style="color: var(--danger);"></i>
                    <span><?php echo htmlspecialchars($interview['location']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card-footer">
                <button class="btn btn-info btn-sm" onclick="viewInterview(<?php echo htmlspecialchars(json_encode($interview)); ?>)">
                    <i class="fas fa-eye"></i> View
                </button>
                <button class="btn btn-warning btn-sm" onclick="rescheduleInterview(<?php echo htmlspecialchars(json_encode($interview)); ?>)">
                    <i class="fas fa-clock"></i> Reschedule
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Past Interviews Table -->
<?php if (!empty($past_interviews)): ?>
<div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
    <h2 style="font-size: 18px; font-weight: 600; color: var(--dark); margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-history" style="color: var(--gray);"></i> Past Interviews
    </h2>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--light-gray);">
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Date</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Applicant</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Position</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Type</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Status</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Rating</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($past_interviews as $interview): ?>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 15px;">
                        <?php echo date('M d, Y', strtotime($interview['interview_date'])); ?>
                        <br><small style="color: var(--gray);"><?php echo date('h:i A', strtotime($interview['interview_time'])); ?></small>
                    </td>
                    <td style="padding: 15px;">
                        <strong><?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?></strong>
                        <br><small style="color: var(--gray);"><?php echo htmlspecialchars($interview['email']); ?></small>
                    </td>
                    <td style="padding: 15px;"><?php echo htmlspecialchars($interview['position_title'] ?: 'General Application'); ?></td>
                    <td style="padding: 15px;">
                        <?php
                        $type_class = $interview['meeting_link'] ? 'status-scheduled' : 'status-completed';
                        ?>
                        <span class="status-badge <?php echo $type_class; ?>" style="padding: 4px 8px;">
                            <?php echo $interview['meeting_link'] ? 'Online' : 'Face-to-Face'; ?>
                        </span>
                    </td>
                    <td style="padding: 15px;">
                        <?php
                        $status_class = 'status-scheduled';
                        if ($interview['status'] == 'completed') $status_class = 'status-completed';
                        if ($interview['status'] == 'cancelled') $status_class = 'status-cancelled';
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>" style="padding: 4px 8px;">
                            <?php echo ucfirst($interview['status']); ?>
                        </span>
                    </td>
                    <td style="padding: 15px;">
                        <?php if ($interview['rating']): ?>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span style="font-weight: 600;"><?php echo $interview['rating']; ?>/10</span>
                            <div style="display: flex; gap: 2px;">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color: <?php echo $i <= floor($interview['rating']/2) ? '#f1c40f' : '#bdc3c7'; ?>; font-size: 10px;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <span style="color: var(--gray);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 15px;">
                        <button class="btn btn-info btn-sm" onclick="viewInterview(<?php echo htmlspecialchars(json_encode($interview)); ?>)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Shortlisted Candidates Section -->
<?php if (!empty($shortlisted)): ?>
<div class="shortlisted-list">
    <div class="list-header">
        <h2><i class="fas fa-users"></i> Shortlisted Candidates (<?php echo count($shortlisted); ?>)</h2>
        <button class="btn btn-success btn-sm" onclick="showBulkScheduleModal()">
            <i class="fas fa-layer-group"></i> Bulk Schedule
        </button>
    </div>
    
    <form method="POST" id="bulkScheduleForm">
        <div class="shortlisted-items">
            <?php foreach ($shortlisted as $candidate): 
                $firstName = $candidate['first_name'] ?? '';
                $lastName = $candidate['last_name'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
                $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
            ?>
            <div class="shortlisted-item">
                <input type="checkbox" name="selected_candidates[]" value="<?php echo $candidate['id']; ?>" class="checkbox candidate-checkbox">
                
                <?php if (getApplicantPhoto($candidate)): ?>
                    <img src="<?php echo getApplicantPhoto($candidate); ?>" 
                         alt="<?php echo htmlspecialchars($fullName); ?>"
                         style="width: 45px; height: 45px; border-radius: 12px; object-fit: cover;"
                         loading="lazy">
                <?php else: ?>
                    <div class="photo">
                        <?php echo $initials; ?>
                    </div>
                <?php endif; ?>
                
                <div class="info">
                    <h4><?php echo htmlspecialchars($fullName); ?></h4>
                    <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($candidate['position_title'] ?: 'General Application'); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($candidate['email']); ?></p>
                </div>
                
                <?php if ($candidate['status'] == 'shortlisted'): ?>
                <span class="badge">For Interview</span>
                <?php else: ?>
                <span class="badge" style="background: var(--info)20; color: var(--info);">Interview Scheduled</span>
                <?php endif; ?>
                
                <button type="button" class="btn btn-outline btn-sm" onclick="scheduleSingle(<?php echo htmlspecialchars(json_encode($candidate)); ?>)">
                    <i class="fas fa-calendar-plus"></i> Schedule
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ==================== MODALS ==================== -->

<!-- Schedule Single Modal -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus" style="color: var(--primary);"></i> Schedule Interview</h3>
            <span class="modal-close" onclick="closeScheduleModal()">&times;</span>
        </div>
        
        <form method="POST" id="scheduleForm">
            <input type="hidden" name="applicant_id" id="schedule_applicant_id">
            
            <div class="form-group">
                <label>Candidate</label>
                <input type="text" id="schedule_candidate_name" readonly disabled style="background: var(--light-gray);">
            </div>
            
            <div class="form-group">
                <label>Position</label>
                <input type="text" id="schedule_position" readonly disabled style="background: var(--light-gray);">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Interview Date *</label>
                    <input type="date" name="interview_date" id="schedule_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Interview Time *</label>
                    <input type="time" name="interview_time" id="schedule_time" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Interview Round *</label>
                    <select name="interview_round" id="interview_round_select" required>
                        <option value="initial">Initial Interview</option>
                        <option value="technical">Technical Interview</option>
                        <option value="hr">HR Interview</option>
                        <option value="final">Final Interview</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Interview Type *</label>
                    <select name="interview_type" id="schedule_type" onchange="toggleInterviewFields()" required>
                        <option value="Online">Online (Google Meet)</option>
                        <option value="Face-to-Face">Face-to-Face</option>
                    </select>
                </div>
            </div>
            
            <!-- Online Meeting Link Section (ONLY shown for Online) -->
            <div id="online_link_group" class="form-group" style="display: block;">
                <div style="background: var(--primary-transparent); border-radius: 12px; padding: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <i class="fas fa-video" style="color: var(--primary); font-size: 18px;"></i>
                        <span style="font-weight: 500; color: var(--primary);">Google Meet Link for Selected Round:</span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <input type="url" name="meeting_link" id="meeting_link" value="" style="flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: white;" readonly>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyFixedLink()" style="padding: 12px;">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                    <small style="color: var(--gray); margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> Fixed link for this interview round
                    </small>
                </div>
            </div>
            
            <!-- Location Section (ONLY shown for Face-to-Face) -->
            <div id="location_group" class="form-group" style="display: none;">
                <label>Location *</label>
                <input type="text" name="location" id="location_input" placeholder="e.g., Main Office, Room 201">
                <small style="color: var(--gray);">Enter the complete address/room where interview will take place</small>
            </div>
            
            <div class="form-group">
                <label>Interview Panel</label>
                <select name="interviewer_id">
                    <option value="">Select Interviewer</option>
                    <?php foreach ($interviewers as $interviewer): ?>
                    <option value="<?php echo $interviewer['id']; ?>">
                        <?php echo htmlspecialchars($interviewer['full_name'] . ' (' . ucfirst($interviewer['role']) . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Notes / Instructions</label>
                <textarea name="notes" rows="3" placeholder="Additional instructions for the applicant..."></textarea>
            </div>
            
            <div style="background: var(--primary-transparent); border-radius: 12px; padding: 15px; margin: 20px 0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-envelope" style="color: var(--primary); font-size: 20px;"></i>
                    <div>
                        <p style="font-weight: 600; color: var(--primary); margin: 0 0 3px;">Email Notification</p>
                        <p style="font-size: 12px; color: var(--gray); margin: 0;">An email with interview details will be sent automatically.</p>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeScheduleModal()">Cancel</button>
                <button type="submit" name="schedule_single" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Schedule & Send Email
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Schedule Modal -->
<div id="bulkScheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group" style="color: var(--success);"></i> Bulk Schedule Interviews</h3>
            <span class="modal-close" onclick="closeBulkScheduleModal()">&times;</span>
        </div>
        
        <form method="POST" id="bulkForm">
            <div style="background: var(--success)10; border-radius: 12px; padding: 15px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle" style="color: var(--success);"></i>
                    <p style="margin: 0; font-size: 14px;">
                        <strong id="selectedCount">0</strong> candidate(s) selected
                    </p>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Interview Date *</label>
                    <input type="date" name="bulk_interview_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Interview Time *</label>
                    <input type="time" name="bulk_interview_time" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Interview Round *</label>
                    <select name="bulk_interview_round" id="bulk_interview_round_select" onchange="updateBulkFields()" required>
                        <option value="initial">Initial Interview</option>
                        <option value="technical">Technical Interview</option>
                        <option value="hr">HR Interview</option>
                        <option value="final">Final Interview</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Interview Type *</label>
                    <select name="bulk_interview_type" id="bulk_interview_type" onchange="toggleBulkFields()" required>
                        <option value="Online">Online</option>
                        <option value="Face-to-Face">Face-to-Face</option>
                    </select>
                </div>
            </div>
            
            <!-- Bulk Online Link Display -->
            <div id="bulk_online_group" class="form-group">
                <div style="background: var(--primary-transparent); border-radius: 12px; padding: 15px;">
                    <p style="font-weight: 600; color: var(--primary); margin: 0 0 10px;">
                        <i class="fas fa-video"></i> Google Meet Link for Selected Round:
                    </p>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="bulk_meeting_link_display" class="form-control" style="flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: white;" readonly>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyBulkLink()">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Location Input -->
            <div id="bulk_location_group" class="form-group" style="display: none;">
                <label>Location *</label>
                <input type="text" name="bulk_location" placeholder="e.g., Main Office, Room 201">
                <small style="color: var(--gray);">This location will be used for all selected candidates</small>
            </div>
            
            <div class="form-group">
                <label>Interview Panel (Optional)</label>
                <select name="bulk_interviewer_id">
                    <option value="">Select Interviewer</option>
                    <?php foreach ($interviewers as $interviewer): ?>
                    <option value="<?php echo $interviewer['id']; ?>">
                        <?php echo htmlspecialchars($interviewer['full_name'] . ' (' . ucfirst($interviewer['role']) . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeBulkScheduleModal()">Cancel</button>
                <button type="submit" name="schedule_bulk" class="btn btn-success" onclick="return confirmBulkSchedule()">
                    <i class="fas fa-paper-plane"></i> Schedule Selected
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Interview Modal -->
<div id="viewInterviewModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-eye" style="color: var(--info);"></i> Interview Details</h3>
            <span class="modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        
        <div id="viewInterviewContent">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<!-- Complete Interview Modal -->
<div id="completeInterviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle" style="color: var(--success);"></i> Complete Interview</h3>
            <span class="modal-close" onclick="closeCompleteModal()">&times;</span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="interview_id" id="complete_interview_id">
            
            <div class="form-group">
                <label>Rating (1-10)</label>
                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                    <?php for($i = 1; $i <= 10; $i++): ?>
                    <label style="flex: 1; min-width: 40px;">
                        <input type="radio" name="rating" value="<?php echo $i; ?>" required style="display: none;" onchange="highlightRating(this)">
                        <span style="display: block; padding: 10px; background: var(--light-gray); border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.3s;">
                            <?php echo $i; ?>
                        </span>
                    </label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Feedback / Notes *</label>
                <textarea name="feedback" rows="5" required placeholder="Enter interview feedback, observations, and recommendations..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCompleteModal()">Cancel</button>
                <button type="submit" name="complete_interview" class="btn btn-success">
                    <i class="fas fa-check"></i> Complete Interview
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// Fixed meeting links for each interview round (ONLY for Online mode)
const fixedLinks = {
    'initial': 'https://meet.google.com/dor-rpqx-ben',
    'technical': 'https://meet.google.com/atz-arcu-zjf',
    'hr': 'https://meet.google.com/wvk-mzpy-ggw',
    'final': 'https://meet.google.com/syy-vbmr-mga'
};

// Store selected applicant for single scheduling
let selectedApplicant = null;

// Toggle between Online and Face-to-Face fields
function toggleInterviewFields() {
    const type = document.getElementById('schedule_type').value;
    const onlineGroup = document.getElementById('online_link_group');
    const locationGroup = document.getElementById('location_group');
    const locationInput = document.getElementById('location_input');
    const meetingLinkInput = document.getElementById('meeting_link');
    
    if (type === 'Online') {
        // Show online fields, hide location
        onlineGroup.style.display = 'block';
        locationGroup.style.display = 'none';
        locationInput.removeAttribute('required');
        
        // Update the meeting link based on selected round
        updateMeetingLink();
    } else {
        // Hide online fields, show location
        onlineGroup.style.display = 'none';
        locationGroup.style.display = 'block';
        locationInput.setAttribute('required', 'required');
        
        // Clear meeting link for face-to-face
        meetingLinkInput.value = '';
    }
}

// Update meeting link based on selected round
function updateMeetingLink() {
    const roundSelect = document.getElementById('interview_round_select');
    const selectedRound = roundSelect.value;
    const meetingLinkInput = document.getElementById('meeting_link');
    
    // Only set link if online is selected
    const type = document.getElementById('schedule_type').value;
    if (type === 'Online') {
        meetingLinkInput.value = fixedLinks[selectedRound] || '';
    } else {
        meetingLinkInput.value = '';
    }
}

// Copy fixed link to clipboard
function copyFixedLink() {
    const linkInput = document.getElementById('meeting_link');
    if (linkInput.value) {
        copyToClipboard(linkInput.value);
    } else {
        showNotification('No meeting link available', 'error');
    }
}

// Copy bulk link to clipboard
function copyBulkLink() {
    const linkInput = document.getElementById('bulk_meeting_link_display');
    if (linkInput.value) {
        copyToClipboard(linkInput.value);
    } else {
        showNotification('No meeting link available', 'error');
    }
}

// Toggle bulk fields based on interview type
function toggleBulkFields() {
    const type = document.getElementById('bulk_interview_type').value;
    const onlineGroup = document.getElementById('bulk_online_group');
    const locationGroup = document.getElementById('bulk_location_group');
    const locationInput = document.querySelector('input[name="bulk_location"]');
    
    if (type === 'Online') {
        onlineGroup.style.display = 'block';
        locationGroup.style.display = 'none';
        if (locationInput) locationInput.removeAttribute('required');
        updateBulkMeetingLink();
    } else {
        onlineGroup.style.display = 'none';
        locationGroup.style.display = 'block';
        if (locationInput) locationInput.setAttribute('required', 'required');
    }
}

// Update bulk meeting link display
function updateBulkMeetingLink() {
    const roundSelect = document.getElementById('bulk_interview_round_select');
    const selectedRound = roundSelect.value;
    const displayElement = document.getElementById('bulk_meeting_link_display');
    
    displayElement.value = fixedLinks[selectedRound] || '';
}

// Update both meeting link and bulk fields when round changes
function updateBulkFields() {
    updateBulkMeetingLink();
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Link copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showNotification('Link copied to clipboard!', 'success');
    });
}

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = type === 'success' ? 'alert-success' : 'alert-danger';
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '10000';
    notification.style.animation = 'slideDown 0.3s';
    notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideUp 0.3s';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Schedule single interview
function scheduleSingle(candidate) {
    selectedApplicant = candidate;
    
    document.getElementById('schedule_applicant_id').value = candidate.id;
    document.getElementById('schedule_candidate_name').value = candidate.first_name + ' ' + candidate.last_name;
    document.getElementById('schedule_position').value = candidate.position_title || 'General Application';
    
    // Set default date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('schedule_date').value = tomorrow.toISOString().split('T')[0];
    document.getElementById('schedule_time').value = '10:00';
    
    // Reset to Online mode by default
    document.getElementById('schedule_type').value = 'Online';
    document.getElementById('interview_round_select').value = 'initial';
    
    // Initialize fields
    toggleInterviewFields();
    updateMeetingLink();
    
    document.getElementById('scheduleModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close schedule modal
function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Show bulk schedule modal
function showBulkScheduleModal() {
    const checkboxes = document.querySelectorAll('.candidate-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select at least one candidate from the list above');
        return;
    }
    
    document.getElementById('selectedCount').textContent = checkboxes.length;
    
    // Reset to Online mode by default
    document.getElementById('bulk_interview_type').value = 'Online';
    document.getElementById('bulk_interview_round_select').value = 'initial';
    
    // Initialize bulk fields
    toggleBulkFields();
    updateBulkMeetingLink();
    
    document.getElementById('bulkScheduleModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close bulk schedule modal
function closeBulkScheduleModal() {
    document.getElementById('bulkScheduleModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Confirm bulk schedule
function confirmBulkSchedule() {
    const checkboxes = document.querySelectorAll('.candidate-checkbox:checked');
    const date = document.querySelector('input[name="bulk_interview_date"]').value;
    const time = document.querySelector('input[name="bulk_interview_time"]').value;
    const type = document.getElementById('bulk_interview_type').value;
    const location = document.querySelector('input[name="bulk_location"]')?.value;
    
    if (!date || !time) {
        alert('Please select interview date and time');
        return false;
    }
    
    if (type === 'Face-to-Face' && !location) {
        alert('Please enter location for face-to-face interviews');
        return false;
    }
    
    return confirm(`Schedule ${type} interviews for ${checkboxes.length} candidate(s) on ${date} at ${time}?`);
}

// View interview details
function viewInterview(interview) {
    const statusColors = {
        'scheduled': 'var(--info)',
        'completed': 'var(--success)',
        'cancelled': 'var(--danger)',
        'rescheduled': 'var(--warning)'
    };
    
    const hasMeetingLink = interview.meeting_link && interview.meeting_link.trim() !== '';
    const interviewType = hasMeetingLink ? 'Online' : 'Face-to-Face';
    
    const html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                <i class="fas fa-calendar-check" style="color: white; font-size: 24px;"></i>
            </div>
            <h2 style="font-size: 22px; color: var(--dark); margin-bottom: 5px;">${interview.first_name} ${interview.last_name}</h2>
            <p style="color: var(--gray);">${interview.position_title || 'General Application'}</p>
        </div>
        
        <div style="background: var(--light-gray); border-radius: 16px; padding: 20px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Date</p>
                    <p style="font-weight: 500;">${new Date(interview.interview_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Time</p>
                    <p style="font-weight: 500;">${new Date('1970-01-01T' + interview.interview_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Type</p>
                    <p>${interviewType}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Status</p>
                    <span style="background: ${statusColors[interview.status]}20; color: ${statusColors[interview.status]}; padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                        ${interview.status}
                    </span>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Panel</p>
                    <p>${interview.interviewer_name || 'HR Team'}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Contact</p>
                    <p><a href="mailto:${interview.email}" style="color: var(--primary); text-decoration: none;">${interview.email}</a></p>
                </div>
            </div>
        </div>
        
        ${hasMeetingLink ? `
        <div style="background: var(--primary-transparent); border-radius: 16px; padding: 15px; margin-bottom: 20px;">
            <p style="font-size: 12px; font-weight: 600; color: var(--primary); margin-bottom: 8px;">Google Meet Link</p>
            <div style="display: flex; gap: 10px;">
                <input type="text" value="${interview.meeting_link}" style="flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: white;" readonly>
                <button class="btn btn-outline btn-sm" onclick="copyToClipboard('${interview.meeting_link}')">
                    <i class="fas fa-copy"></i>
                </button>
                <a href="${interview.meeting_link}" target="_blank" class="btn btn-primary btn-sm">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            </div>
        </div>
        ` : interview.location ? `
        <div style="background: var(--light-gray); border-radius: 16px; padding: 15px; margin-bottom: 20px;">
            <p style="font-size: 12px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">Location</p>
            <p><i class="fas fa-map-marker-alt" style="color: var(--danger);"></i> ${interview.location}</p>
        </div>
        ` : ''}
        
        ${interview.feedback ? `
        <div style="background: var(--light-gray); border-radius: 16px; padding: 15px; margin-bottom: 20px;">
            <p style="font-size: 12px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">Feedback</p>
            <p style="color: var(--gray); line-height: 1.5;">${interview.feedback}</p>
            ${interview.rating ? `
            <div style="margin-top: 10px;">
                <p style="font-size: 12px; font-weight: 600; color: var(--dark); margin-bottom: 3px;">Rating</p>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="display: flex; gap: 2px;">
                        ${Array.from({length: 5}, (_, i) => `<i class="fas fa-star" style="color: ${i < Math.floor(interview.rating/2) ? '#f1c40f' : '#bdc3c7'};"></i>`).join('')}
                    </div>
                    <span style="font-size: 14px;">(${interview.rating}/10)</span>
                </div>
            </div>
            ` : ''}
        </div>
        ` : ''}
        
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn btn-outline" onclick="closeViewModal()">Close</button>
        </div>
    `;
    
    document.getElementById('viewInterviewContent').innerHTML = html;
    document.getElementById('viewInterviewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close view modal
function closeViewModal() {
    document.getElementById('viewInterviewModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Reschedule interview
function rescheduleInterview(interview) {
    scheduleSingle({
        id: interview.applicant_id,
        first_name: interview.first_name,
        last_name: interview.last_name,
        position_title: interview.position_title
    });
    
    // Pre-fill with existing data
    document.getElementById('schedule_date').value = interview.interview_date;
    document.getElementById('schedule_time').value = interview.interview_time;
    document.querySelector('select[name="interview_round"]').value = interview.interview_type;
    
    const hasMeetingLink = interview.meeting_link && interview.meeting_link.trim() !== '';
    document.querySelector('select[name="interview_type"]').value = hasMeetingLink ? 'Online' : 'Face-to-Face';
    
    if (hasMeetingLink) {
        document.getElementById('meeting_link').value = interview.meeting_link;
    } else {
        document.querySelector('input[name="location"]').value = interview.location || '';
    }
    
    document.querySelector('select[name="interviewer_id"]').value = interview.interviewer_id || '';
    
    toggleInterviewFields();
    
    // Change form action to reschedule
    const form = document.getElementById('scheduleForm');
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'interview_id';
    hiddenInput.value = interview.id;
    form.appendChild(hiddenInput);
    
    // Change submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.name = 'reschedule_interview';
    submitBtn.innerHTML = '<i class="fas fa-clock"></i> Reschedule & Send Email';
}

// Complete interview
function completeInterview(interviewId) {
    document.getElementById('complete_interview_id').value = interviewId;
    document.getElementById('completeInterviewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close complete modal
function closeCompleteModal() {
    document.getElementById('completeInterviewModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Cancel interview
function cancelInterview(interviewId) {
    if (confirm('Are you sure you want to cancel this interview?')) {
        window.location.href = '?page=recruitment&subpage=interview-scheduling&action=cancel&id=' + interviewId;
    }
}

// Highlight rating
function highlightRating(radio) {
    const labels = document.querySelectorAll('input[name="rating"] + span');
    labels.forEach(span => {
        span.style.background = 'var(--light-gray)';
        span.style.color = 'var(--dark)';
    });
    
    if (radio.checked) {
        radio.nextElementSibling.style.background = 'var(--success)';
        radio.nextElementSibling.style.color = 'white';
    }
}

// Add event listeners for round selection
document.addEventListener('DOMContentLoaded', function() {
    const roundSelect = document.getElementById('interview_round_select');
    if (roundSelect) {
        roundSelect.addEventListener('change', updateMeetingLink);
    }
    
    const bulkRoundSelect = document.getElementById('bulk_interview_round_select');
    if (bulkRoundSelect) {
        bulkRoundSelect.addEventListener('change', updateBulkMeetingLink);
    }
    
    // Select all functionality
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.candidate-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        });
    }
    
    // Update select all state when individual checkboxes change
    document.querySelectorAll('.candidate-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                const checkboxes = document.querySelectorAll('.candidate-checkbox');
                const checkedCount = document.querySelectorAll('.candidate-checkbox:checked').length;
                selectAll.checked = checkedCount === checkboxes.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
            }
        });
    });
});

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeScheduleModal();
        closeBulkScheduleModal();
        closeViewModal();
        closeCompleteModal();
    }
});

// Close modals when clicking outside
window.onclick = function(event) {
    const scheduleModal = document.getElementById('scheduleModal');
    const bulkModal = document.getElementById('bulkScheduleModal');
    const viewModal = document.getElementById('viewInterviewModal');
    const completeModal = document.getElementById('completeInterviewModal');
    
    if (event.target == scheduleModal) closeScheduleModal();
    if (event.target == bulkModal) closeBulkScheduleModal();
    if (event.target == viewModal) closeViewModal();
    if (event.target == completeModal) closeCompleteModal();
}
</script>