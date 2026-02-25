<?php
// modules/recruitment/orientation-schedule.php
$page_title = "Orientation Schedule";

// Include required files
require_once 'config/mail_config.php';

// Fixed meeting link for orientations
$fixed_meeting_link = 'https://meet.google.com/yva-cckb-pqh';

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

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
 * Handle schedule orientation (single)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_single'])) {
    try {
        $pdo->beginTransaction();
        
        // Get new hire details
        $stmt = $pdo->prepare("
            SELECT nh.*, ja.first_name, ja.last_name, ja.email, jp.title as position_title
            FROM new_hires nh
            JOIN job_applications ja ON nh.applicant_id = ja.id
            LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
            WHERE nh.id = ?
        ");
        $stmt->execute([$_POST['new_hire_id']]);
        $new_hire = $stmt->fetch();
        
        if (!$new_hire) {
            throw new Exception("New hire not found");
        }
        
        $orientation_type = $_POST['orientation_type'];
        
        // Set meeting link ONLY if online
        $meeting_link = null;
        if ($orientation_type === 'Online') {
            $meeting_link = $fixed_meeting_link;
        }
        
        // Set location ONLY if face-to-face
        $location = null;
        if ($orientation_type === 'Face-to-Face') {
            $location = $_POST['location'] ?? 'Main Office - Training Room';
            if (empty($location)) {
                throw new Exception("Location is required for face-to-face orientation");
            }
        }
        
        // Insert orientation (using interviews table with orientation type)
        $stmt = $pdo->prepare("
            INSERT INTO interviews (
                applicant_id, job_posting_id, interviewer_id, interview_date,
                interview_time, interview_type, location, meeting_link,
                status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, 'orientation', ?, ?, 'scheduled', ?, ?)
        ");
        
        $stmt->execute([
            $new_hire['applicant_id'],
            $new_hire['job_posting_id'],
            $_POST['facilitator_id'] ?: null,
            $_POST['orientation_date'],
            $_POST['orientation_time'],
            $location,
            $meeting_link,
            $_POST['notes'] ?: null,
            $_SESSION['user_id']
        ]);
        
        $orientation_id = $pdo->lastInsertId();
        
        // Prepare orientation data for email
        $orientation_data = [
            'applicant_name' => $new_hire['first_name'] . ' ' . $new_hire['last_name'],
            'position' => $new_hire['position_title'] ?: 'New Hire',
            'orientation_date' => $_POST['orientation_date'],
            'orientation_time' => $_POST['orientation_time'],
            'orientation_type' => $orientation_type,
            'facilitator' => getFacilitatorName($pdo, $_POST['facilitator_id']),
            'location' => $location ?: 'To be advised',
            'meeting_link' => $meeting_link,
            'duration' => $_POST['duration'] . ' hours',
            'topics' => $_POST['topics'] ?? 'General orientation'
        ];
        
        // Send email notification
        $email_result = sendOrientationEmail(
            $new_hire['email'],
            $new_hire['first_name'] . ' ' . $new_hire['last_name'],
            $orientation_data,
            $orientation_id
        );
        
        // Log communication
        $stmt = $pdo->prepare("
            INSERT INTO communication_log (
                applicant_id, communication_type, subject, message, sent_by, status
            ) VALUES (?, 'email', ?, ?, ?, ?)
        ");
        
        $subject = "Orientation Schedule: " . $new_hire['position_title'];
        $email_message = "Orientation scheduled on " . date('F d, Y', strtotime($_POST['orientation_date'])) . 
                        " at " . date('h:i A', strtotime($_POST['orientation_time'])) .
                        " (" . $_POST['duration'] . " hours)";
        
        $stmt->execute([
            $new_hire['applicant_id'],
            $subject,
            $email_message,
            $_SESSION['user_id'],
            $email_result['success'] ? 'sent' : 'failed'
        ]);
        
        // Add note to new hire
        $note = "[" . date('Y-m-d H:i') . "] Orientation scheduled on " . 
                date('F d, Y', strtotime($_POST['orientation_date'])) . 
                " at " . date('h:i A', strtotime($_POST['orientation_time'])) .
                " (Duration: " . $_POST['duration'] . " hours)";
        
        if ($orientation_type === 'Online' && $meeting_link) {
            $note .= " - Link: {$meeting_link}";
        } elseif ($orientation_type === 'Face-to-Face' && $location) {
            $note .= " - Location: {$location}";
        }
        
        $stmt = $pdo->prepare("
            UPDATE new_hires 
            SET notes = CONCAT(IFNULL(notes, ''), '\n', ?) 
            WHERE id = ?
        ");
        $stmt->execute([$note, $_POST['new_hire_id']]);
        
        $pdo->commit();
        
        simpleLog($pdo, $_SESSION['user_id'], 'schedule_orientation', 
            "Scheduled orientation for new hire #{$_POST['new_hire_id']}");
        
        $message = "Orientation scheduled successfully! ";
        $message .= $email_result['success'] 
            ? "✅ Notification email sent." 
            : "⚠️ Warning: " . $email_result['message'];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle bulk schedule
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_bulk'])) {
    try {
        // Debug: Log the POST data to see what's being submitted
        error_log("POST data: " . print_r($_POST, true));
        
        $selected = $_POST['selected_new_hires'] ?? [];
        $orientation_date = $_POST['bulk_orientation_date'] ?? '';
        $orientation_time = $_POST['bulk_orientation_time'] ?? '';
        $orientation_type = $_POST['bulk_orientation_type'] ?? 'Online';
        $duration = $_POST['bulk_duration'] ?? '4';
        $topics = $_POST['bulk_topics'] ?? 'General Company Orientation';
        $facilitator_id = $_POST['bulk_facilitator_id'] ?? null;
        $location = $_POST['bulk_location'] ?? '';
        
        // Check if selected is empty and provide detailed error
        if (empty($selected)) {
            throw new Exception("No new hires selected. Please check the checkboxes and make sure they are checked before clicking Bulk Orientation.");
        }
        
        if (empty($orientation_date) || empty($orientation_time)) {
            throw new Exception("Orientation date and time are required");
        }
        
        if ($orientation_type === 'Face-to-Face' && empty($location)) {
            throw new Exception("Location is required for face-to-face orientation");
        }
        
        // Set meeting link ONLY if online
        $meeting_link = null;
        if ($orientation_type === 'Online') {
            $meeting_link = $fixed_meeting_link;
        }
        
        $pdo->beginTransaction();
        
        $success_count = 0;
        $failed_count = 0;
        $email_success_count = 0;
        
        foreach ($selected as $new_hire_id) {
            try {
                // Get new hire details
                $stmt = $pdo->prepare("
                    SELECT nh.*, ja.first_name, ja.last_name, ja.email, jp.title as position_title
                    FROM new_hires nh
                    JOIN job_applications ja ON nh.applicant_id = ja.id
                    LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
                    WHERE nh.id = ?
                ");
                $stmt->execute([$new_hire_id]);
                $new_hire = $stmt->fetch();
                
                if (!$new_hire) {
                    $failed_count++;
                    continue;
                }
                
                // Insert orientation
                $stmt = $pdo->prepare("
                    INSERT INTO interviews (
                        applicant_id, job_posting_id, interviewer_id, interview_date,
                        interview_time, interview_type, location, meeting_link,
                        status, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, 'orientation', ?, ?, 'scheduled', ?, ?)
                ");
                
                $stmt->execute([
                    $new_hire['applicant_id'],
                    $new_hire['job_posting_id'],
                    $facilitator_id,
                    $orientation_date,
                    $orientation_time,
                    $location,
                    $meeting_link,
                    "Bulk orientation. Duration: {$duration} hours. Topics: {$topics}",
                    $_SESSION['user_id']
                ]);
                
                $orientation_id = $pdo->lastInsertId();
                
                // Prepare orientation data
                $orientation_data = [
                    'applicant_name' => $new_hire['first_name'] . ' ' . $new_hire['last_name'],
                    'position' => $new_hire['position_title'] ?: 'New Hire',
                    'orientation_date' => $orientation_date,
                    'orientation_time' => $orientation_time,
                    'orientation_type' => $orientation_type,
                    'facilitator' => getFacilitatorName($pdo, $facilitator_id),
                    'location' => $location ?: 'To be advised',
                    'meeting_link' => $meeting_link,
                    'duration' => $duration . ' hours',
                    'topics' => $topics
                ];
                
                // Send email
                $email_result = sendOrientationEmail(
                    $new_hire['email'],
                    $new_hire['first_name'] . ' ' . $new_hire['last_name'],
                    $orientation_data,
                    $orientation_id
                );
                
                if ($email_result['success']) {
                    $email_success_count++;
                }
                
                $success_count++;
                
            } catch (Exception $e) {
                $failed_count++;
            }
        }
        
        $pdo->commit();
        
        $message = "✅ Successfully scheduled {$success_count} orientations. ";
        $message .= "📧 Emails sent to {$email_success_count} new hires.";
        if ($failed_count > 0) {
            $message .= " ❌ Failed: {$failed_count}";
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle reschedule
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_orientation'])) {
    try {
        $pdo->beginTransaction();
        
        // Get orientation details
        $stmt = $pdo->prepare("
            SELECT i.*, ja.first_name, ja.last_name, ja.email, jp.title as position_title
            FROM interviews i
            JOIN job_applications ja ON i.applicant_id = ja.id
            LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
            WHERE i.id = ?
        ");
        $stmt->execute([$_POST['orientation_id']]);
        $orientation = $stmt->fetch();
        
        if (!$orientation) {
            throw new Exception("Orientation not found");
        }
        
        $orientation_type = $_POST['orientation_type'];
        
        // Set meeting link ONLY if online
        $meeting_link = null;
        if ($orientation_type === 'Online') {
            $meeting_link = $fixed_meeting_link;
        }
        
        // Set location ONLY if face-to-face
        $location = null;
        if ($orientation_type === 'Face-to-Face') {
            $location = $_POST['location'] ?? $orientation['location'];
            if (empty($location)) {
                throw new Exception("Location is required for face-to-face orientation");
            }
        }
        
        // Update orientation
        $stmt = $pdo->prepare("
            UPDATE interviews SET
                interview_date = ?,
                interview_time = ?,
                location = ?,
                meeting_link = ?,
                status = 'scheduled',
                notes = CONCAT(IFNULL(notes, ''), '\n[" . date('Y-m-d H:i') . "] Rescheduled')
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['orientation_date'],
            $_POST['orientation_time'],
            $location,
            $meeting_link,
            $_POST['orientation_id']
        ]);
        
        // Send reschedule notification
        $orientation_data = [
            'applicant_name' => $orientation['first_name'] . ' ' . $orientation['last_name'],
            'position' => $orientation['position_title'],
            'orientation_date' => $_POST['orientation_date'],
            'orientation_time' => $_POST['orientation_time'],
            'orientation_type' => $orientation_type,
            'facilitator' => getFacilitatorName($pdo, $orientation['interviewer_id']),
            'location' => $location ?: 'To be advised',
            'meeting_link' => $meeting_link,
            'duration' => $_POST['duration'] . ' hours',
            'topics' => $_POST['topics'] ?? 'General orientation'
        ];
        
        sendOrientationEmail(
            $orientation['email'],
            $orientation['first_name'] . ' ' . $orientation['last_name'],
            $orientation_data,
            $_POST['orientation_id']
        );
        
        $pdo->commit();
        
        $message = "✅ Orientation rescheduled successfully! Notification sent.";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle cancel orientation
 */
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE interviews SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        
        $message = "✅ Orientation cancelled successfully";
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle complete orientation
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_orientation'])) {
    try {
        $pdo->beginTransaction();
        
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
            $_POST['orientation_id']
        ]);
        
        // Update new hire onboarding progress
        $stmt = $pdo->prepare("
            UPDATE new_hires nh
            JOIN interviews i ON nh.applicant_id = i.applicant_id
            SET nh.orientation_completed = 1,
                nh.onboarding_progress = LEAST(nh.onboarding_progress + 20, 100)
            WHERE i.id = ?
        ");
        $stmt->execute([$_POST['orientation_id']]);
        
        $pdo->commit();
        
        $message = "✅ Orientation marked as completed";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Helper Functions
 */
function getFacilitatorName($pdo, $facilitator_id) {
    if (empty($facilitator_id)) return 'HR Team';
    
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$facilitator_id]);
    $name = $stmt->fetchColumn();
    
    return $name ?: 'HR Team';
}

function getNewHirePhoto($new_hire) {
    if (!empty($new_hire['photo_path']) && file_exists($new_hire['photo_path'])) {
        return htmlspecialchars($new_hire['photo_path']);
    }
    return null;
}

function sendOrientationEmail($email, $name, $data, $orientation_id) {
    try {
        // Use MailConfig::getInstance() instead of getMailer()
        $mail = MailConfig::getInstance();
        
        // Clear previous recipients and attachments
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->clearCustomHeaders();
        
        $subject = "🎓 Orientation Schedule - Freight Management Inc.";
        
        $date = date('F d, Y', strtotime($data['orientation_date']));
        $time = date('h:i A', strtotime($data['orientation_time']));
        
        // Add recipient
        $mail->addAddress($email, $name);
        
        // Add BCC to HR
        $mail->addBCC('hr@freightmanagement.com', 'HR Department');
        
        $mail->Subject = $subject;
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0e4c92; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8fafd; padding: 30px; border: 1px solid #eef2f6; }
                .details { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
                .detail-row { display: flex; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #eef2f6; }
                .detail-label { font-weight: bold; width: 120px; color: #0e4c92; }
                .detail-value { flex: 1; }
                .meeting-link { background: #e8f0fe; padding: 15px; border-radius: 8px; text-align: center; margin: 20px 0; }
                .meeting-link a { color: #0e4c92; font-weight: bold; text-decoration: none; font-size: 16px; }
                .footer { text-align: center; padding: 20px; color: #64748b; font-size: 12px; }
                .badge { background: #0e4c92; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎓 New Hire Orientation</h1>
                    <p>Welcome to Freight Management Inc.</p>
                </div>
                <div class='content'>
                    <p>Dear <strong>{$name}</strong>,</p>
                    <p>Your new hire orientation has been scheduled. Please review the details below:</p>
                    
                    <div class='details'>
                        <div class='detail-row'>
                            <div class='detail-label'>Position:</div>
                            <div class='detail-value'>{$data['position']}</div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Date:</div>
                            <div class='detail-value'>{$date}</div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Time:</div>
                            <div class='detail-value'>{$time} ({$data['duration']})</div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Type:</div>
                            <div class='detail-value'>{$data['orientation_type']}</div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Facilitator:</div>
                            <div class='detail-value'>{$data['facilitator']}</div>
                        </div>
                        <div class='detail-row'>
                            <div class='detail-label'>Topics:</div>
                            <div class='detail-value'>{$data['topics']}</div>
                        </div>
                    </div>
        ";
        
        if ($data['orientation_type'] === 'Online' && !empty($data['meeting_link'])) {
            $message .= "
                    <div class='meeting-link'>
                        <p style='margin-bottom: 10px;'><strong>📹 Google Meet Link:</strong></p>
                        <a href='{$data['meeting_link']}' target='_blank'>{$data['meeting_link']}</a>
                        <p style='margin-top: 10px; font-size: 13px; color: #666;'>Click the link above to join the orientation</p>
                    </div>
            ";
        } elseif ($data['orientation_type'] === 'Face-to-Face' && !empty($data['location'])) {
            $message .= "
                    <div class='meeting-link' style='background: #f0f0f0;'>
                        <p style='margin-bottom: 10px;'><strong>📍 Location:</strong></p>
                        <p style='font-size: 16px;'>{$data['location']}</p>
                        <p style='margin-top: 10px; font-size: 13px; color: #666;'>Please arrive 10-15 minutes early</p>
                    </div>
            ";
        }
        
        $message .= "
                    <p><strong>📋 What to prepare:</strong></p>
                    <ul>
                        <li>Valid ID</li>
                        <li>Signed employment contract (if not yet submitted)</li>
                        <li>SSS, PhilHealth, Pag-IBIG, TIN numbers</li>
                        <li>Notebook and pen</li>
                        <li>Questions about the company and your role</li>
                    </ul>
                    
                    <p>If you need to reschedule or have any questions, please contact HR immediately.</p>
                    
                    <p>We're excited to have you on board!</p>
                    
                    <p>Best regards,<br>
                    <strong>HR Team</strong><br>
                    Freight Management Inc.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply directly to this email.</p>
                    <p>&copy; " . date('Y') . " Freight Management Inc. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</div>'], ["\n", "\n\n", "\n"], $message));
        $mail->isHTML(true);
        
        if ($mail->send()) {
            return ['success' => true, 'message' => 'Email sent'];
        } else {
            return ['success' => false, 'message' => $mail->ErrorInfo];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Get new hires ready for orientation
$stmt = $pdo->query("
    SELECT nh.*, ja.first_name, ja.last_name, ja.email, ja.photo_path, 
           jp.title as position_title, jp.department,
           (SELECT COUNT(*) FROM onboarding_documents WHERE new_hire_id = nh.id AND status = 'verified') as verified_docs,
           (SELECT COUNT(*) FROM onboarding_documents WHERE new_hire_id = nh.id) as total_docs
    FROM new_hires nh
    JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
    WHERE nh.status = 'onboarding'
    ORDER BY nh.created_at DESC
");
$new_hires = $stmt->fetchAll();

// Get all orientations (using interviews table with interview_type = 'orientation')
$query = "
    SELECT 
        i.*,
        ja.first_name,
        ja.last_name,
        ja.email,
        ja.photo_path,
        jp.title as position_title,
        jp.job_code,
        jp.department,
        u.full_name as facilitator_name,
        nh.id as new_hire_id,
        nh.onboarding_progress
    FROM interviews i
    JOIN job_applications ja ON i.applicant_id = ja.id
    LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
    LEFT JOIN users u ON i.interviewer_id = u.id
    LEFT JOIN new_hires nh ON nh.applicant_id = ja.id
    WHERE i.interview_type = 'orientation'
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
$orientations = $stmt->fetchAll();

// Get statistics
$stats = [];

// Today's orientations
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM interviews 
    WHERE interview_type = 'orientation' AND status = 'scheduled' AND interview_date = CURDATE()
");
$stmt->execute();
$stats['today'] = $stmt->fetchColumn();

// This week
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM interviews 
    WHERE interview_type = 'orientation' AND status = 'scheduled' 
    AND interview_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute();
$stats['week'] = $stmt->fetchColumn();

// Total scheduled
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM interviews 
    WHERE interview_type = 'orientation' AND status = 'scheduled'
");
$stmt->execute();
$stats['scheduled'] = $stmt->fetchColumn();

// Completed
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM interviews 
    WHERE interview_type = 'orientation' AND status = 'completed'
");
$stmt->execute();
$stats['completed'] = $stmt->fetchColumn();

// Pending document count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM onboarding_documents WHERE status = 'pending'
");
$stmt->execute();
$stats['pending_docs'] = $stmt->fetchColumn();

// Get facilitators list
$stmt = $pdo->query("
    SELECT id, full_name, role, email 
    FROM users 
    WHERE role IN ('admin', 'dispatcher', 'management') 
    ORDER BY full_name
");
$facilitators = $stmt->fetchAll();

// Group orientations by date
$today_orientations = array_filter($orientations, function($o) {
    return $o['interview_date'] == date('Y-m-d') && $o['status'] == 'scheduled';
});

$upcoming_orientations = array_filter($orientations, function($o) {
    return $o['interview_date'] > date('Y-m-d') && $o['status'] == 'scheduled';
});

$past_orientations = array_filter($orientations, function($o) {
    return $o['interview_date'] < date('Y-m-d') || $o['status'] != 'scheduled';
});

// Common topics for orientation
$common_topics = [
    'Company History and Culture',
    'Mission, Vision, and Values',
    'Organizational Structure',
    'HR Policies and Benefits',
    'Code of Conduct',
    'Safety Guidelines',
    'IT Systems and Tools',
    'Payroll and Timekeeping',
    'Performance Management',
    'Department-specific Training'
];
?>

<!-- ==================== STYLES ==================== -->
<style>
:root {
    --primary: #0e4c92;
    --primary-dark: #0a3a70;
    --primary-light: #2a6eb0;
    --primary-transparent: rgba(14, 76, 146, 0.1);
    --primary-transparent-2: rgba(14, 76, 146, 0.2);
    --success: #27ae60;
    --warning: #f39c12;
    --danger: #e74c3c;
    --info: #3498db;
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
    border-left: 4px solid var(--primary);
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
    background: var(--primary);
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
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
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
.orientation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.orientation-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.orientation-card.today {
    border-left: 4px solid var(--warning);
    background: linear-gradient(to right, #fff9e6, white);
}

.orientation-card.urgent {
    border-left: 4px solid var(--danger);
    background: linear-gradient(to right, #fee9e7, white);
}

.orientation-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.employee-photo {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    object-fit: cover;
    border: 2px solid white;
    box-shadow: 0 5px 15px var(--primary-transparent-2);
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 18px;
}

.employee-details h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 3px 0;
}

.employee-details p {
    font-size: 12px;
    color: var(--gray);
    margin: 2px 0;
}

.employee-details i {
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

.progress-indicator {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: var(--light-gray);
    border-radius: 20px;
    font-size: 11px;
    color: var(--dark);
}

.progress-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--success);
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

.duration-badge {
    background: var(--primary)10;
    color: var(--primary);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
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
    gap: 8px;
    justify-content: flex-end;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

/* New Hires List */
.new-hires-list {
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

.new-hire-items {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 500px;
    overflow-y: auto;
    padding-right: 10px;
}

.new-hire-item {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s;
    border: 1px solid var(--border);
}

.new-hire-item:hover {
    transform: translateX(5px);
    background: white;
    border-color: var(--primary);
}

.new-hire-item .checkbox {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
}

.new-hire-item .photo {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

.new-hire-item .info {
    flex: 1;
}

.new-hire-item .info h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 3px;
}

.new-hire-item .info p {
    font-size: 12px;
    color: var(--gray);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.new-hire-item .info i {
    width: 12px;
    color: var(--primary);
}

.doc-progress {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
}

.progress-bar {
    width: 60px;
    height: 4px;
    background: #e0e0e0;
    border-radius: 2px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--success);
    border-radius: 2px;
}

.new-hire-item .badge {
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
    max-width: 650px;
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

.modal-header h3 i {
    color: var(--primary);
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

.topics-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 5px;
}

.topic-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: var(--light-gray);
    border-radius: 8px;
    font-size: 12px;
}

.topic-checkbox input {
    width: auto;
    margin-right: 5px;
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

/* Document status */
.doc-status {
    display: flex;
    align-items: center;
    gap: 5px;
}

.doc-status .verified {
    color: var(--success);
}

.doc-status .pending {
    color: var(--warning);
}

.doc-status .missing {
    color: var(--danger);
}

/* Responsive */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .orientation-grid {
        grid-template-columns: 1fr;
    }
    
    .bulk-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .topics-grid {
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
        <i class="fas fa-chalkboard-teacher"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-primary btn-sm" onclick="showBulkScheduleModal()">
            <i class="fas fa-layer-group"></i> Bulk Orientation
        </button>
        <a href="?page=recruitment&subpage=hiring-pipeline" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> View New Hires
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
            <span class="stat-label">Today's Orientation</span>
            <span class="stat-value"><?php echo $stats['today']; ?></span>
            <div class="stat-small">
                <i class="fas fa-clock" style="color: var(--warning);"></i>
                <?php echo count($today_orientations); ?> scheduled
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
                <i class="fas fa-calendar-check"></i> Pending orientation
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
                <i class="fas fa-file"></i>
                <?php echo $stats['pending_docs']; ?> pending docs
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Orientations
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="recruitment">
        <input type="hidden" name="subpage" value="orientation-schedule">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Orientations</option>
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
            <a href="?page=recruitment&subpage=orientation-schedule" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Today's Orientations Section -->
<?php if (!empty($today_orientations)): ?>
<div style="margin-bottom: 30px;">
    <h2 style="font-size: 18px; font-weight: 600; color: var(--dark); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-sun" style="color: var(--warning);"></i> Today's Orientations
    </h2>
    
    <div class="orientation-grid">
        <?php foreach ($today_orientations as $orientation): 
            $photoPath = getNewHirePhoto($orientation);
            $firstName = $orientation['first_name'] ?? '';
            $lastName = $orientation['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
            $time_remaining = strtotime($orientation['interview_date'] . ' ' . $orientation['interview_time']) - time();
            $is_urgent = $time_remaining < 3600 && $time_remaining > 0;
        ?>
        <div class="orientation-card today <?php echo $is_urgent ? 'urgent' : ''; ?>">
            <div class="card-header">
                <div class="employee-info">
                    <?php if ($photoPath): ?>
                        <img src="<?php echo $photoPath; ?>" 
                             alt="<?php echo htmlspecialchars($fullName); ?>"
                             class="employee-photo"
                             onerror="this.src='assets/img/default-avatar.png'"
                             loading="lazy">
                    <?php else: ?>
                        <div class="employee-photo">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="employee-details">
                        <h3><?php echo htmlspecialchars($fullName); ?></h3>
                        <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($orientation['position_title'] ?: 'New Hire'); ?></p>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($orientation['email']); ?></p>
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
                        <div class="detail-value"><?php echo date('h:i A', strtotime($orientation['interview_time'])); ?></div>
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
                        <div class="detail-label">Facilitator</div>
                        <div class="detail-value"><?php echo $orientation['facilitator_name'] ?: 'HR Team'; ?></div>
                    </div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="detail-content">
                        <div class="detail-label">Progress</div>
                        <div class="detail-value">
                            <div class="progress-indicator">
                                <span class="progress-dot"></span>
                                <?php echo $orientation['onboarding_progress'] ?? 0; ?>% Complete
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($orientation['meeting_link']): ?>
                <div class="meeting-link-box">
                    <i class="fas fa-video"></i>
                    <input type="text" value="<?php echo $orientation['meeting_link']; ?>" readonly>
                    <button class="copy-btn" onclick="copyToClipboard('<?php echo $orientation['meeting_link']; ?>')">
                        <i class="fas fa-copy"></i>
                    </button>
                    <a href="<?php echo $orientation['meeting_link']; ?>" target="_blank" class="copy-btn" style="text-decoration: none;">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
                <?php elseif ($orientation['location']): ?>
                <div class="meeting-link-box" style="background: var(--light-gray);">
                    <i class="fas fa-map-marker-alt" style="color: var(--danger);"></i>
                    <input type="text" value="<?php echo htmlspecialchars($orientation['location']); ?>" readonly>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card-footer">
                <button class="btn btn-info btn-sm" onclick="viewOrientation(<?php echo htmlspecialchars(json_encode($orientation)); ?>)">
                    <i class="fas fa-eye"></i> View
                </button>
                <button class="btn btn-warning btn-sm" onclick="rescheduleOrientation(<?php echo htmlspecialchars(json_encode($orientation)); ?>)">
                    <i class="fas fa-clock"></i> Reschedule
                </button>
                <?php if ($orientation['interview_date'] == date('Y-m-d')): ?>
                <button class="btn btn-success btn-sm" onclick="completeOrientation(<?php echo $orientation['id']; ?>)">
                    <i class="fas fa-check"></i> Complete
                </button>
                <?php endif; ?>
                <button class="btn btn-danger btn-sm" onclick="cancelOrientation(<?php echo $orientation['id']; ?>)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Upcoming Orientations -->
<?php if (!empty($upcoming_orientations)): ?>
<div style="margin-bottom: 30px;">
    <h2 style="font-size: 18px; font-weight: 600; color: var(--dark); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-calendar-alt" style="color: var(--primary);"></i> Upcoming Orientations
    </h2>
    
    <div class="orientation-grid">
        <?php foreach ($upcoming_orientations as $orientation): 
            $photoPath = getNewHirePhoto($orientation);
            $firstName = $orientation['first_name'] ?? '';
            $lastName = $orientation['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
        ?>
        <div class="orientation-card">
            <div class="card-header">
                <div class="employee-info">
                    <?php if ($photoPath): ?>
                        <img src="<?php echo $photoPath; ?>" 
                             alt="<?php echo htmlspecialchars($fullName); ?>"
                             class="employee-photo"
                             onerror="this.src='assets/img/default-avatar.png'"
                             loading="lazy">
                    <?php else: ?>
                        <div class="employee-photo">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="employee-details">
                        <h3><?php echo htmlspecialchars($fullName); ?></h3>
                        <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($orientation['position_title'] ?: 'New Hire'); ?></p>
                    </div>
                </div>
                
                <span class="status-badge status-scheduled">
                    <i class="fas fa-calendar"></i> <?php echo date('M d', strtotime($orientation['interview_date'])); ?>
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
                            <?php echo date('F d, Y', strtotime($orientation['interview_date'])); ?> at 
                            <?php echo date('h:i A', strtotime($orientation['interview_time'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="detail-content">
                        <div class="detail-label">Duration</div>
                        <div class="detail-value">
                            <?php 
                            preg_match('/Duration: (\d+) hours/', $orientation['notes'] ?? '', $matches);
                            $duration = $matches[1] ?? '4';
                            ?>
                            <span class="duration-badge">
                                <i class="fas fa-clock"></i> <?php echo $duration; ?> hours
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if ($orientation['meeting_link']): ?>
                <div class="meeting-link-box">
                    <i class="fas fa-video"></i>
                    <input type="text" value="<?php echo $orientation['meeting_link']; ?>" readonly>
                </div>
                <?php elseif ($orientation['location']): ?>
                <div class="meeting-link-box" style="background: var(--light-gray);">
                    <i class="fas fa-map-marker-alt" style="color: var(--danger);"></i>
                    <span><?php echo htmlspecialchars($orientation['location']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card-footer">
                <button class="btn btn-info btn-sm" onclick="viewOrientation(<?php echo htmlspecialchars(json_encode($orientation)); ?>)">
                    <i class="fas fa-eye"></i> View
                </button>
                <button class="btn btn-warning btn-sm" onclick="rescheduleOrientation(<?php echo htmlspecialchars(json_encode($orientation)); ?>)">
                    <i class="fas fa-clock"></i> Reschedule
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Past Orientations Table -->
<?php if (!empty($past_orientations)): ?>
<div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
    <h2 style="font-size: 18px; font-weight: 600; color: var(--dark); margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-history" style="color: var(--gray);"></i> Past Orientations
    </h2>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--light-gray);">
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Date</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Employee</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Position</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Type</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Status</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Rating</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: var(--gray);">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($past_orientations as $orientation): ?>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 15px;">
                        <?php echo date('M d, Y', strtotime($orientation['interview_date'])); ?>
                        <br><small style="color: var(--gray);"><?php echo date('h:i A', strtotime($orientation['interview_time'])); ?></small>
                    </td>
                    <td style="padding: 15px;">
                        <strong><?php echo htmlspecialchars($orientation['first_name'] . ' ' . $orientation['last_name']); ?></strong>
                        <br><small style="color: var(--gray);"><?php echo htmlspecialchars($orientation['email']); ?></small>
                    </td>
                    <td style="padding: 15px;"><?php echo htmlspecialchars($orientation['position_title'] ?: 'New Hire'); ?></td>
                    <td style="padding: 15px;">
                        <?php
                        $type_class = $orientation['meeting_link'] ? 'status-scheduled' : 'status-completed';
                        ?>
                        <span class="status-badge <?php echo $type_class; ?>" style="padding: 4px 8px;">
                            <?php echo $orientation['meeting_link'] ? 'Online' : 'Face-to-Face'; ?>
                        </span>
                    </td>
                    <td style="padding: 15px;">
                        <?php
                        $status_class = 'status-scheduled';
                        if ($orientation['status'] == 'completed') $status_class = 'status-completed';
                        if ($orientation['status'] == 'cancelled') $status_class = 'status-cancelled';
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>" style="padding: 4px 8px;">
                            <?php echo ucfirst($orientation['status']); ?>
                        </span>
                    </td>
                    <td style="padding: 15px;">
                        <?php if ($orientation['rating']): ?>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span style="font-weight: 600;"><?php echo $orientation['rating']; ?>/10</span>
                            <div style="display: flex; gap: 2px;">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color: <?php echo $i <= floor($orientation['rating']/2) ? '#f1c40f' : '#bdc3c7'; ?>; font-size: 10px;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <span style="color: var(--gray);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 15px;">
                        <button class="btn btn-info btn-sm" onclick="viewOrientation(<?php echo htmlspecialchars(json_encode($orientation)); ?>)">
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

<!-- New Hires Ready for Orientation -->
<?php if (!empty($new_hires)): ?>
<div class="new-hires-list">
    <div class="list-header">
        <h2><i class="fas fa-user-plus"></i> New Hires Ready for Orientation (<?php echo count($new_hires); ?>)</h2>
        <div style="display: flex; gap: 10px;">
            <input type="text" id="searchNewHires" placeholder="Search..." style="padding: 10px; border: 1px solid var(--border); border-radius: 10px; width: 250px;">
            <button class="btn btn-primary btn-sm" onclick="showBulkScheduleModal()">
                <i class="fas fa-layer-group"></i> Bulk Schedule
            </button>
        </div>
    </div>
    
    <form method="POST" id="bulkOrientationForm">
        <div class="new-hire-items" id="newHireList">
            <?php foreach ($new_hires as $new_hire): 
                $firstName = $new_hire['first_name'] ?? '';
                $lastName = $new_hire['last_name'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
                $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                $doc_percentage = $new_hire['total_docs'] > 0 ? round(($new_hire['verified_docs'] / $new_hire['total_docs']) * 100) : 0;
            ?>
            <div class="new-hire-item" data-name="<?php echo strtolower($fullName); ?>" data-position="<?php echo strtolower($new_hire['position_title'] ?? ''); ?>">
                <input type="checkbox" name="selected_new_hires[]" value="<?php echo $new_hire['id']; ?>" class="checkbox newhire-checkbox">
                
                <?php if (getNewHirePhoto($new_hire)): ?>
                    <img src="<?php echo getNewHirePhoto($new_hire); ?>" 
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
                    <p>
                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($new_hire['position_title'] ?: 'New Hire'); ?>
                        <span class="doc-progress">
                            <i class="fas fa-file"></i>
                            <span><?php echo $new_hire['verified_docs']; ?>/<?php echo $new_hire['total_docs']; ?> docs</span>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $doc_percentage; ?>%;"></div>
                            </div>
                        </span>
                    </p>
                </div>
                
                <?php
                $doc_status_class = $new_hire['verified_docs'] == $new_hire['total_docs'] ? 'status-completed' : 'status-warning';
                $doc_status_text = $new_hire['verified_docs'] == $new_hire['total_docs'] ? 'Ready' : 'Pending Docs';
                ?>
                <span class="badge" style="background: <?php echo $doc_percentage == 100 ? 'var(--success)20' : 'var(--warning)20'; ?>; color: <?php echo $doc_percentage == 100 ? 'var(--success)' : 'var(--warning)'; ?>;">
                    <?php echo $doc_status_text; ?>
                </span>
                
                <button type="button" class="btn btn-outline btn-sm" onclick="scheduleSingle(<?php echo htmlspecialchars(json_encode($new_hire)); ?>)">
                    <i class="fas fa-calendar-plus"></i> Schedule
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
</div>
<?php else: ?>
<div class="new-hires-list">
    <div class="list-header">
        <h2><i class="fas fa-user-plus"></i> New Hires Ready for Orientation</h2>
    </div>
    <div style="text-align: center; padding: 40px; color: var(--gray);">
        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
        <p>No new hires ready for orientation at the moment.</p>
    </div>
</div>
<?php endif; ?>

<!-- ==================== MODALS ==================== -->

<!-- Schedule Single Modal -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus" style="color: var(--primary);"></i> Schedule Orientation</h3>
            <span class="modal-close" onclick="closeScheduleModal()">&times;</span>
        </div>
        
        <form method="POST" id="scheduleForm">
            <input type="hidden" name="new_hire_id" id="schedule_new_hire_id">
            
            <div class="form-group">
                <label>Employee</label>
                <input type="text" id="schedule_employee_name" readonly disabled style="background: var(--light-gray);">
            </div>
            
            <div class="form-group">
                <label>Position</label>
                <input type="text" id="schedule_position" readonly disabled style="background: var(--light-gray);">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Orientation Date *</label>
                    <input type="date" name="orientation_date" id="schedule_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Orientation Time *</label>
                    <input type="time" name="orientation_time" id="schedule_time" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Duration (hours) *</label>
                    <select name="duration" required>
                        <option value="2">2 hours</option>
                        <option value="3">3 hours</option>
                        <option value="4" selected>4 hours</option>
                        <option value="5">5 hours</option>
                        <option value="6">6 hours</option>
                        <option value="8">8 hours</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Orientation Type *</label>
                    <select name="orientation_type" id="schedule_type" onchange="toggleOrientationFields()" required>
                        <option value="Online">Online (Google Meet)</option>
                        <option value="Face-to-Face">Face-to-Face</option>
                    </select>
                </div>
            </div>
            
            <!-- Online Meeting Link Section -->
            <div id="online_link_group" class="form-group" style="display: block;">
                <div style="background: var(--primary-transparent); border-radius: 12px; padding: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <i class="fas fa-video" style="color: var(--primary); font-size: 18px;"></i>
                        <span style="font-weight: 500; color: var(--primary);">Google Meet Link:</span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <input type="url" name="meeting_link" id="meeting_link" value="<?php echo $fixed_meeting_link; ?>" style="flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: white;" readonly>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyFixedLink()" style="padding: 12px;">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                    <small style="color: var(--gray); margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> Fixed link for all online orientations
                    </small>
                </div>
            </div>
            
            <!-- Location Section -->
            <div id="location_group" class="form-group" style="display: none;">
                <label>Location *</label>
                <input type="text" name="location" id="location_input" placeholder="e.g., Main Office - Training Room, 2nd Floor">
                <small style="color: var(--gray);">Enter the complete address/room where orientation will take place</small>
            </div>
            
            <div class="form-group">
                <label>Topics to Cover</label>
                <textarea name="topics" rows="3" placeholder="Enter topics to be covered during orientation">Company Culture, HR Policies, Safety Guidelines, IT Systems</textarea>
                <small style="color: var(--gray);">Separate topics with commas</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Facilitator</label>
                    <select name="facilitator_id">
                        <option value="">Select Facilitator</option>
                        <?php foreach ($facilitators as $facilitator): ?>
                        <option value="<?php echo $facilitator['id']; ?>">
                            <?php echo htmlspecialchars($facilitator['full_name'] . ' (' . ucfirst($facilitator['role']) . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" id="schedule_department" readonly disabled style="background: var(--light-gray);">
                </div>
            </div>
            
            <div class="form-group">
                <label>Additional Notes / Instructions</label>
                <textarea name="notes" rows="3" placeholder="What to bring, dress code, other instructions...">Please bring:
- Valid ID
- Signed contract
- SSS, PhilHealth, Pag-IBIG, TIN numbers
- Notebook and pen</textarea>
            </div>
            
            <div style="background: var(--primary-transparent); border-radius: 12px; padding: 15px; margin: 20px 0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-envelope" style="color: var(--primary); font-size: 20px;"></i>
                    <div>
                        <p style="font-weight: 600; color: var(--primary); margin: 0 0 3px;">Email Notification</p>
                        <p style="font-size: 12px; color: var(--gray); margin: 0;">An email with orientation details and meeting link will be sent automatically.</p>
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
            <h3><i class="fas fa-layer-group" style="color: var(--primary);"></i> Bulk Orientation Schedule</h3>
            <span class="modal-close" onclick="closeBulkScheduleModal()">&times;</span>
        </div>
        
        <form method="POST" id="bulkForm">
            <div style="background: var(--primary)10; border-radius: 12px; padding: 15px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                    <p style="margin: 0; font-size: 14px;">
                        <strong id="selectedCount">0</strong> new hire(s) selected for orientation
                    </p>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Orientation Date *</label>
                    <input type="date" name="bulk_orientation_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Orientation Time *</label>
                    <input type="time" name="bulk_orientation_time" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Duration (hours) *</label>
                    <select name="bulk_duration" required>
                        <option value="2">2 hours</option>
                        <option value="3">3 hours</option>
                        <option value="4" selected>4 hours</option>
                        <option value="5">5 hours</option>
                        <option value="6">6 hours</option>
                        <option value="8">8 hours</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Orientation Type *</label>
                    <select name="bulk_orientation_type" id="bulk_orientation_type" onchange="toggleBulkFields()" required>
                        <option value="Online">Online</option>
                        <option value="Face-to-Face">Face-to-Face</option>
                    </select>
                </div>
            </div>
            
            <!-- Bulk Online Link Display -->
            <div id="bulk_online_group" class="form-group">
                <div style="background: var(--primary-transparent); border-radius: 12px; padding: 15px;">
                    <p style="font-weight: 600; color: var(--primary); margin: 0 0 10px;">
                        <i class="fas fa-video"></i> Google Meet Link:
                    </p>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="bulk_meeting_link_display" class="form-control" value="<?php echo $fixed_meeting_link; ?>" style="flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: white;" readonly>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyBulkLink()">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Location Input -->
            <div id="bulk_location_group" class="form-group" style="display: none;">
                <label>Location *</label>
                <input type="text" name="bulk_location" placeholder="e.g., Main Office - Training Room, 2nd Floor">
                <small style="color: var(--gray);">This location will be used for all selected new hires</small>
            </div>
            
            <div class="form-group">
                <label>Topics to Cover</label>
                <textarea name="bulk_topics" rows="3" placeholder="Topics to be covered during orientation">Company Culture, HR Policies, Safety Guidelines, IT Systems</textarea>
                <small style="color: var(--gray);">These topics will be included in all emails</small>
            </div>
            
            <div class="form-group">
                <label>Facilitator</label>
                <select name="bulk_facilitator_id">
                    <option value="">Select Facilitator</option>
                    <?php foreach ($facilitators as $facilitator): ?>
                    <option value="<?php echo $facilitator['id']; ?>">
                        <?php echo htmlspecialchars($facilitator['full_name'] . ' (' . ucfirst($facilitator['role']) . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Additional Instructions</label>
                <textarea name="bulk_notes" rows="3" placeholder="What to bring, dress code, other instructions...">Please bring:
- Valid ID
- Signed contract
- SSS, PhilHealth, Pag-IBIG, TIN numbers
- Notebook and pen</textarea>
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

<!-- View Orientation Modal -->
<div id="viewOrientationModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-eye" style="color: var(--info);"></i> Orientation Details</h3>
            <span class="modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        
        <div id="viewOrientationContent">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<!-- Complete Orientation Modal -->
<div id="completeOrientationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle" style="color: var(--success);"></i> Complete Orientation</h3>
            <span class="modal-close" onclick="closeCompleteModal()">&times;</span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="orientation_id" id="complete_orientation_id">
            
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
                <textarea name="feedback" rows="5" required placeholder="Enter feedback, observations, and recommendations..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Topics Covered</label>
                <textarea name="topics_covered" rows="3" placeholder="Topics covered during orientation..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCompleteModal()">Cancel</button>
                <button type="submit" name="complete_orientation" class="btn btn-success">
                    <i class="fas fa-check"></i> Complete Orientation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
// Fixed meeting link for orientations
const fixedMeetingLink = '<?php echo $fixed_meeting_link; ?>';

// Store selected new hire for single scheduling
let selectedNewHire = null;

// Toggle between Online and Face-to-Face fields
function toggleOrientationFields() {
    const type = document.getElementById('schedule_type').value;
    const onlineGroup = document.getElementById('online_link_group');
    const locationGroup = document.getElementById('location_group');
    const locationInput = document.getElementById('location_input');
    const meetingLinkInput = document.getElementById('meeting_link');
    
    if (type === 'Online') {
        onlineGroup.style.display = 'block';
        locationGroup.style.display = 'none';
        locationInput.removeAttribute('required');
        meetingLinkInput.value = fixedMeetingLink;
    } else {
        onlineGroup.style.display = 'none';
        locationGroup.style.display = 'block';
        locationInput.setAttribute('required', 'required');
        meetingLinkInput.value = '';
    }
}

// Toggle bulk fields
function toggleBulkFields() {
    const type = document.getElementById('bulk_orientation_type').value;
    const onlineGroup = document.getElementById('bulk_online_group');
    const locationGroup = document.getElementById('bulk_location_group');
    const locationInput = document.querySelector('input[name="bulk_location"]');
    const displayElement = document.getElementById('bulk_meeting_link_display');
    
    if (type === 'Online') {
        onlineGroup.style.display = 'block';
        locationGroup.style.display = 'none';
        if (locationInput) locationInput.removeAttribute('required');
        displayElement.value = fixedMeetingLink;
    } else {
        onlineGroup.style.display = 'none';
        locationGroup.style.display = 'block';
        if (locationInput) locationInput.setAttribute('required', 'required');
        displayElement.value = '';
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

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Link copied to clipboard!', 'success');
    }).catch(() => {
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

// Schedule single orientation
function scheduleSingle(newHire) {
    selectedNewHire = newHire;
    
    document.getElementById('schedule_new_hire_id').value = newHire.id;
    document.getElementById('schedule_employee_name').value = newHire.first_name + ' ' + newHire.last_name;
    document.getElementById('schedule_position').value = newHire.position_title || 'New Hire';
    document.getElementById('schedule_department').value = newHire.department || 'N/A';
    
    // Set default date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('schedule_date').value = tomorrow.toISOString().split('T')[0];
    document.getElementById('schedule_time').value = '09:00';
    
    // Reset to Online mode by default
    document.getElementById('schedule_type').value = 'Online';
    
    // Initialize fields
    toggleOrientationFields();
    
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
    const checkboxes = document.querySelectorAll('.newhire-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select at least one new hire from the list above');
        return;
    }
    
    document.getElementById('selectedCount').textContent = checkboxes.length;
    
    // Store the selected checkbox values in a hidden field
    let selectedValues = [];
    checkboxes.forEach(checkbox => {
        selectedValues.push(checkbox.value);
    });
    
    // Create hidden inputs for each selected value
    const form = document.getElementById('bulkForm');
    
    // Remove any existing hidden inputs
    const existingInputs = form.querySelectorAll('input[name="selected_new_hires[]"]');
    existingInputs.forEach(input => input.remove());
    
    // Add new hidden inputs
    selectedValues.forEach(value => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_new_hires[]';
        input.value = value;
        form.appendChild(input);
    });
    
    // Reset to Online mode by default
    document.getElementById('bulk_orientation_type').value = 'Online';
    
    // Initialize bulk fields
    toggleBulkFields();
    
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
    const checkboxes = document.querySelectorAll('.newhire-checkbox:checked');
    const date = document.querySelector('input[name="bulk_orientation_date"]').value;
    const time = document.querySelector('input[name="bulk_orientation_time"]').value;
    const type = document.getElementById('bulk_orientation_type').value;
    const location = document.querySelector('input[name="bulk_location"]')?.value;
    
    if (!date || !time) {
        alert('Please select orientation date and time');
        return false;
    }
    
    if (type === 'Face-to-Face' && !location) {
        alert('Please enter location for face-to-face orientation');
        return false;
    }
    
    // Debug: Show how many checkboxes are checked
    console.log('Checkboxes checked:', checkboxes.length);
    
    return confirm(`Schedule ${type} orientation for ${checkboxes.length} new hire(s) on ${date} at ${time}?`);
}

// View orientation details
function viewOrientation(orientation) {
    const statusColors = {
        'scheduled': 'var(--info)',
        'completed': 'var(--success)',
        'cancelled': 'var(--danger)',
        'rescheduled': 'var(--warning)'
    };
    
    const hasMeetingLink = orientation.meeting_link && orientation.meeting_link.trim() !== '';
    const orientationType = hasMeetingLink ? 'Online' : 'Face-to-Face';
    
    // Extract duration from notes
    const durationMatch = orientation.notes ? orientation.notes.match(/Duration: (\d+) hours/) : null;
    const duration = durationMatch ? durationMatch[1] : '4';
    
    const html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; background: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                <i class="fas fa-chalkboard-teacher" style="color: white; font-size: 24px;"></i>
            </div>
            <h2 style="font-size: 22px; color: var(--dark); margin-bottom: 5px;">${orientation.first_name} ${orientation.last_name}</h2>
            <p style="color: var(--gray);">${orientation.position_title || 'New Hire'}</p>
        </div>
        
        <div style="background: var(--light-gray); border-radius: 16px; padding: 20px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Date</p>
                    <p style="font-weight: 500;">${new Date(orientation.interview_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Time</p>
                    <p style="font-weight: 500;">${new Date('1970-01-01T' + orientation.interview_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })} (${duration} hours)</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Type</p>
                    <p>${orientationType}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Status</p>
                    <span style="background: ${statusColors[orientation.status]}20; color: ${statusColors[orientation.status]}; padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                        ${orientation.status}
                    </span>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Facilitator</p>
                    <p>${orientation.facilitator_name || 'HR Team'}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Contact</p>
                    <p><a href="mailto:${orientation.email}" style="color: var(--primary); text-decoration: none;">${orientation.email}</a></p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Progress</p>
                    <p>${orientation.onboarding_progress || 0}% complete</p>
                </div>
            </div>
        </div>
        
        ${hasMeetingLink ? `
        <div style="background: var(--primary-transparent); border-radius: 16px; padding: 15px; margin-bottom: 20px;">
            <p style="font-size: 12px; font-weight: 600; color: var(--primary); margin-bottom: 8px;">Google Meet Link</p>
            <div style="display: flex; gap: 10px;">
                <input type="text" value="${orientation.meeting_link}" style="flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: white;" readonly>
                <button class="btn btn-outline btn-sm" onclick="copyToClipboard('${orientation.meeting_link}')">
                    <i class="fas fa-copy"></i>
                </button>
                <a href="${orientation.meeting_link}" target="_blank" class="btn btn-primary btn-sm">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            </div>
        </div>
        ` : orientation.location ? `
        <div style="background: var(--light-gray); border-radius: 16px; padding: 15px; margin-bottom: 20px;">
            <p style="font-size: 12px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">Location</p>
            <p><i class="fas fa-map-marker-alt" style="color: var(--danger);"></i> ${orientation.location}</p>
        </div>
        ` : ''}
        
        ${orientation.feedback ? `
        <div style="background: var(--light-gray); border-radius: 16px; padding: 15px; margin-bottom: 20px;">
            <p style="font-size: 12px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">Feedback</p>
            <p style="color: var(--gray); line-height: 1.5;">${orientation.feedback}</p>
            ${orientation.rating ? `
            <div style="margin-top: 10px;">
                <p style="font-size: 12px; font-weight: 600; color: var(--dark); margin-bottom: 3px;">Rating</p>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="display: flex; gap: 2px;">
                        ${Array.from({length: 5}, (_, i) => `<i class="fas fa-star" style="color: ${i < Math.floor(orientation.rating/2) ? '#f1c40f' : '#bdc3c7'};"></i>`).join('')}
                    </div>
                    <span style="font-size: 14px;">(${orientation.rating}/10)</span>
                </div>
            </div>
            ` : ''}
        </div>
        ` : ''}
        
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn btn-outline" onclick="closeViewModal()">Close</button>
        </div>
    `;
    
    document.getElementById('viewOrientationContent').innerHTML = html;
    document.getElementById('viewOrientationModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close view modal
function closeViewModal() {
    document.getElementById('viewOrientationModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Reschedule orientation
function rescheduleOrientation(orientation) {
    // Extract duration from notes
    const durationMatch = orientation.notes ? orientation.notes.match(/Duration: (\d+) hours/) : null;
    const duration = durationMatch ? durationMatch[1] : '4';
    
    scheduleSingle({
        id: orientation.new_hire_id,
        first_name: orientation.first_name,
        last_name: orientation.last_name,
        position_title: orientation.position_title,
        department: orientation.department
    });
    
    // Pre-fill with existing data
    document.getElementById('schedule_date').value = orientation.interview_date;
    document.getElementById('schedule_time').value = orientation.interview_time;
    document.querySelector('select[name="duration"]').value = duration;
    
    const hasMeetingLink = orientation.meeting_link && orientation.meeting_link.trim() !== '';
    document.querySelector('select[name="orientation_type"]').value = hasMeetingLink ? 'Online' : 'Face-to-Face';
    
    if (hasMeetingLink) {
        document.getElementById('meeting_link').value = orientation.meeting_link;
    } else {
        document.querySelector('input[name="location"]').value = orientation.location || '';
    }
    
    document.querySelector('select[name="facilitator_id"]').value = orientation.interviewer_id || '';
    
    toggleOrientationFields();
    
    // Change form action to reschedule
    const form = document.getElementById('scheduleForm');
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'orientation_id';
    hiddenInput.value = orientation.id;
    form.appendChild(hiddenInput);
    
    // Change submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.name = 'reschedule_orientation';
    submitBtn.innerHTML = '<i class="fas fa-clock"></i> Reschedule & Send Email';
}

// Complete orientation
function completeOrientation(orientationId) {
    document.getElementById('complete_orientation_id').value = orientationId;
    document.getElementById('completeOrientationModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close complete modal
function closeCompleteModal() {
    document.getElementById('completeOrientationModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Cancel orientation
function cancelOrientation(orientationId) {
    if (confirm('Are you sure you want to cancel this orientation?')) {
        window.location.href = '?page=recruitment&subpage=orientation-schedule&action=cancel&id=' + orientationId;
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

// Search new hires
document.getElementById('searchNewHires')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const items = document.querySelectorAll('.new-hire-item');
    
    items.forEach(item => {
        const name = item.getAttribute('data-name');
        const position = item.getAttribute('data-position');
        
        if (name.includes(searchTerm) || position.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
});

// Select all functionality
const selectAll = document.getElementById('selectAll');
if (selectAll) {
    selectAll.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.newhire-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
    });
}

// Update select all state when individual checkboxes change
document.querySelectorAll('.newhire-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            const checkboxes = document.querySelectorAll('.newhire-checkbox');
            const checkedCount = document.querySelectorAll('.newhire-checkbox:checked').length;
            selectAll.checked = checkedCount === checkboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }
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
    const viewModal = document.getElementById('viewOrientationModal');
    const completeModal = document.getElementById('completeOrientationModal');
    
    if (event.target == scheduleModal) closeScheduleModal();
    if (event.target == bulkModal) closeBulkScheduleModal();
    if (event.target == viewModal) closeViewModal();
    if (event.target == completeModal) closeCompleteModal();
}
</script>