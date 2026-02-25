<?php
// Start output buffering at the VERY FIRST LINE - NO SPACES OR CHARACTERS BEFORE THIS
ob_start();

// modules/recruitment/final-selection.php
$page_title = "Final Selection & Final Interview";

// Include required files
require_once 'config/mail_config.php';

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$job_filter = isset($_GET['job_id']) ? $_GET['job_id'] : '';

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
 * Send email notification for final interview result
 */
function sendFinalResultEmail($applicant_email, $applicant_name, $result_data) {
    try {
        $mail = MailConfig::getInstance();
        
        // Clear previous recipients
        $mail->clearAddresses();
        $mail->clearAttachments();
        
        // Recipient
        $mail->addAddress($applicant_email, $applicant_name);
        $mail->addBCC('hr@freightmanagement.com', 'HR Department');
        
        // Set subject based on result
        if ($result_data['result'] == 'hire') {
            $mail->Subject = "🎉 Congratulations! Job Offer - {$result_data['position']}";
        } else {
            $mail->Subject = "Update on Your Final Interview - {$result_data['position']}";
        }
        
        // Build email body
        $body = buildFinalResultEmailHTML($applicant_name, $result_data);
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</div>'], ["\n", "\n\n", "\n"], $body));
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}

/**
 * Build HTML email for final result notification
 */
function buildFinalResultEmailHTML($applicant_name, $data) {
    $result = $data['result'];
    $position = $data['position'];
    $score = $data['score'] ?? 0;
    
    if ($result == 'hire') {
        $content = '
        <div style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); border-radius: 15px; padding: 30px; margin: 20px 0; text-align: center; color: white;">
            <i class="fas fa-trophy" style="font-size: 60px; margin-bottom: 20px;"></i>
            <h2 style="font-size: 28px; margin: 0 0 10px;">Congratulations!</h2>
            <p style="font-size: 18px; opacity: 0.9;">You have been selected for the position</p>
            <h3 style="font-size: 24px; margin: 15px 0; background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 50px; display: inline-block;">' . htmlspecialchars($position) . '</h3>
            <p style="margin-top: 20px;">Your final interview score: <strong>' . $score . '%</strong></p>
        </div>
        
        <div style="background: #f8fafd; border-radius: 15px; padding: 25px; margin: 20px 0;">
            <h4 style="color: #2c3e50; margin-bottom: 15px;">📋 Next Steps:</h4>
            <ol style="color: #64748b; line-height: 1.8;">
                <li>Our HR team will contact you within 24 hours</li>
                <li>You will receive your employment contract via email</li>
                <li>We\'ll schedule your orientation and onboarding</li>
                <li>Please prepare the following requirements:
                    <ul style="margin-top: 10px;">
                        <li>Valid government IDs (2 copies)</li>
                        <li>SSS, PhilHealth, Pag-IBIG numbers</li>
                        <li>Birth certificate (PSA)</li>
                        <li>Latest NBI clearance</li>
                    </ul>
                </li>
            </ol>
        </div>';
    } else {
        $content = '
        <div style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); border-radius: 15px; padding: 30px; margin: 20px 0; text-align: center; color: white;">
            <i class="fas fa-frown" style="font-size: 60px; margin-bottom: 20px;"></i>
            <h2 style="font-size: 28px; margin: 0 0 10px;">Thank You for Your Interest</h2>
            <p style="font-size: 18px; opacity: 0.9;">Update on your final interview</p>
        </div>
        
        <div style="background: #f8fafd; border-radius: 15px; padding: 25px; margin: 20px 0;">
            <p style="color: #64748b; line-height: 1.8;">Dear ' . htmlspecialchars($applicant_name) . ',</p>
            <p style="color: #64748b; line-height: 1.8;">
                Thank you for participating in the final interview for the <strong>' . htmlspecialchars($position) . '</strong> position.
                We appreciate the time and effort you invested in our selection process.
            </p>
            <p style="color: #64748b; line-height: 1.8;">
                After careful consideration of all candidates, we regret to inform you that we have decided to move forward with another candidate whose qualifications more closely match our current needs.
            </p>
            <p style="color: #64748b; line-height: 1.8;">
                Your final interview score: <strong>' . $score . '%</strong> (Passing score: 95%)
            </p>
            <p style="color: #64748b; line-height: 1.8;">
                We encourage you to apply again for future openings that match your profile. We wish you the best in your job search and future endeavors.
            </p>
        </div>';
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap");
            
            body {
                font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                line-height: 1.6;
                margin: 0;
                padding: 0;
                background: linear-gradient(135deg, #f5f7fa 0%, #e9edf5 100%);
            }
            
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: white;
                border-radius: 30px;
                overflow: hidden;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            }
            
            .header {
                background: linear-gradient(135deg, #0e4c92 0%, #2a6eb0 100%);
                padding: 30px;
                text-align: center;
            }
            
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
                color: white;
            }
            
            .content {
                padding: 30px;
            }
            
            .footer {
                background: #f8fafd;
                padding: 20px;
                text-align: center;
                border-top: 1px solid #eef2f6;
                font-size: 12px;
                color: #64748b;
            }
            
            .score-badge {
                display: inline-block;
                padding: 8px 16px;
                background: #f8fafd;
                border-radius: 30px;
                font-size: 14px;
                color: #2c3e50;
                margin: 10px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Freight Management Inc.</h1>
            </div>
            
            <div class="content">
                ' . $content . '
                
                <div style="text-align: center; margin-top: 30px;">
                    <p style="color: #64748b;">If you have any questions, please contact our HR department:</p>
                    <p style="color: #0e4c92; font-weight: 500;">📞 (02) 1234-5678 | ✉️ hr@freightmanagement.com</p>
                </div>
            </div>
            
            <div class="footer">
                <p>This is an automated message. Please do not reply directly to this email.</p>
                <p>&copy; ' . date('Y') . ' Freight Management Inc. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Handle start final interview evaluation
 */
if (isset($_GET['action']) && $_GET['action'] === 'start_final' && isset($_GET['id'])) {
    try {
        $applicant_id = $_GET['id'];
        
        // Check if final interview already exists
        $stmt = $pdo->prepare("
            SELECT id FROM final_interviews 
            WHERE applicant_id = ? AND status = 'scheduled'
        ");
        $stmt->execute([$applicant_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Clear output buffer and redirect
            ob_clean();
            header("Location: ?page=recruitment&subpage=final-selection&action=evaluate_final&id=" . $existing['id']);
            exit;
        }
        
        // Get applicant details
        $stmt = $pdo->prepare("
            SELECT ja.*, jp.id as job_posting_id, jp.title as position_title, jp.job_code
            FROM job_applications ja
            LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
            WHERE ja.id = ? AND ja.final_status = 'final_interview'
        ");
        $stmt->execute([$applicant_id]);
        $applicant = $stmt->fetch();
        
        if (!$applicant) {
            throw new Exception("Applicant not found or not in final interview stage");
        }
        
        // Create new final interview
        $stmt = $pdo->prepare("
            INSERT INTO final_interviews (
                applicant_id, job_posting_id, interviewer_id, interview_date, status, created_by
            ) VALUES (?, ?, ?, CURDATE(), 'scheduled', ?)
        ");
        $stmt->execute([$applicant_id, $applicant['job_posting_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
        $final_interview_id = $pdo->lastInsertId();
        
        simpleLog($pdo, $_SESSION['user_id'], 'start_final_interview', "Started final interview for applicant #$applicant_id");
        
        // Clear output buffer and redirect
        ob_clean();
        header("Location: ?page=recruitment&subpage=final-selection&action=evaluate_final&id=" . $final_interview_id);
        exit;
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle final interview evaluation submission
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_final_evaluation'])) {
    try {
        $pdo->beginTransaction();
        
        $final_interview_id = $_POST['final_interview_id'];
        $ratings = $_POST['rating'] ?? [];
        $comments = $_POST['comments'] ?? [];
        $strengths = $_POST['strengths'] ?? '';
        $weaknesses = $_POST['weaknesses'] ?? '';
        $overall_comments = $_POST['overall_comments'] ?? '';
        
        if (empty($ratings)) {
            throw new Exception("Please rate all questions");
        }
        
        // Calculate scores
        $total_score = 0;
        $max_score = count($ratings) * 5;
        
        foreach ($ratings as $question_id => $rating) {
            $total_score += intval($rating);
            
            // Check if response exists
            $stmt = $pdo->prepare("
                SELECT id FROM final_evaluation_responses 
                WHERE final_interview_id = ? AND question_id = ?
            ");
            $stmt->execute([$final_interview_id, $question_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE final_evaluation_responses SET
                        rating = ?,
                        comments = ?
                    WHERE final_interview_id = ? AND question_id = ?
                ");
                $stmt->execute([
                    $rating,
                    $comments[$question_id] ?? null,
                    $final_interview_id,
                    $question_id
                ]);
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO final_evaluation_responses (
                        final_interview_id, question_id, rating, comments
                    ) VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $final_interview_id,
                    $question_id,
                    $rating,
                    $comments[$question_id] ?? null
                ]);
            }
        }
        
        $final_percentage = ($total_score / $max_score) * 100;
        
        // Determine recommendation based on score (95% passing)
        $recommendation = ($final_percentage >= 95) ? 'hire' : 'reject';
        
        // Update final interview
        $stmt = $pdo->prepare("
            UPDATE final_interviews SET
                final_score = ?,
                recommendation = ?,
                strengths = ?,
                weaknesses = ?,
                overall_comments = ?,
                status = 'completed'
            WHERE id = ?
        ");
        $stmt->execute([
            $final_percentage,
            $recommendation,
            $strengths,
            $weaknesses,
            $overall_comments,
            $final_interview_id
        ]);
        
        // Get applicant details for email
        $stmt = $pdo->prepare("
            SELECT fi.*, ja.first_name, ja.last_name, ja.email, jp.title as position_title
            FROM final_interviews fi
            JOIN job_applications ja ON fi.applicant_id = ja.id
            LEFT JOIN job_postings jp ON fi.job_posting_id = jp.id
            WHERE fi.id = ?
        ");
        $stmt->execute([$final_interview_id]);
        $interview_data = $stmt->fetch();
        
        // Send email notification
        $result_data = [
            'result' => $recommendation,
            'position' => $interview_data['position_title'] ?: 'the position',
            'score' => round($final_percentage, 1)
        ];
        
        $email_result = sendFinalResultEmail(
            $interview_data['email'],
            $interview_data['first_name'] . ' ' . $interview_data['last_name'],
            $result_data
        );
        
        // Update applicant's final interview score
        $stmt = $pdo->prepare("
            UPDATE job_applications 
            SET final_interview_score = ?
            WHERE id = ?
        ");
        $stmt->execute([$final_percentage, $interview_data['applicant_id']]);
        
        // Log communication
        $stmt = $pdo->prepare("
            INSERT INTO communication_log (
                applicant_id, communication_type, subject, message, sent_by, status
            ) VALUES (?, 'email', ?, ?, ?, ?)
        ");
        
        $subject = "Final Interview Result: {$interview_data['position_title']}";
        $email_message = "Final decision: " . ucfirst($recommendation) . " (Score: " . round($final_percentage, 1) . "%)";
        
        $stmt->execute([
            $interview_data['applicant_id'],
            $subject,
            $email_message,
            $_SESSION['user_id'],
            $email_result['success'] ? 'sent' : 'failed'
        ]);
        
        $pdo->commit();
        
        simpleLog($pdo, $_SESSION['user_id'], 'complete_final_interview', "Completed final interview #$final_interview_id");
        
        $message = "✅ Final interview evaluation submitted successfully!";
        
        // Clear output buffer and redirect
        ob_clean();
        header("Location: ?page=recruitment&subpage=final-selection&success=1");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle final selection approval
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_selection'])) {
    try {
        $pdo->beginTransaction();
        
        $applicant_id = $_POST['applicant_id'];
        $approved_salary = $_POST['approved_salary'];
        $start_date = $_POST['start_date'];
        $remarks = $_POST['remarks'] ?? '';
        
        // Update applicant as selected
        $stmt = $pdo->prepare("
            UPDATE job_applications SET
                status = 'hired',
                final_status = 'hired',
                hired_date = NOW(),
                selected_by = ?,
                selection_date = NOW(),
                approval_remarks = ?,
                approved_salary = ?,
                proposed_start_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $remarks, $approved_salary, $start_date, $applicant_id]);
        
        // Get applicant details for email
        $stmt = $pdo->prepare("
            SELECT ja.*, jp.title as position_title
            FROM job_applications ja
            LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
            WHERE ja.id = ?
        ");
        $stmt->execute([$applicant_id]);
        $applicant = $stmt->fetch();
        
        // Send hire notification
        $result_data = [
            'result' => 'hire',
            'position' => $applicant['position_title'] ?: 'the position',
            'score' => $applicant['final_interview_score'] ?: 0
        ];
        
        sendFinalResultEmail($applicant['email'], $applicant['first_name'] . ' ' . $applicant['last_name'], $result_data);
        
        // Create new hire record
        $stmt = $pdo->prepare("
            INSERT INTO new_hires (
                applicant_id, job_posting_id, hire_date, start_date, position, department, status, created_by
            ) VALUES (?, ?, CURDATE(), ?, ?, ?, 'onboarding', ?)
        ");
        
        // Get department from job posting
        $dept_stmt = $pdo->prepare("SELECT department FROM job_postings WHERE id = ?");
        $dept_stmt->execute([$applicant['job_posting_id']]);
        $dept = $dept_stmt->fetch();
        
        $stmt->execute([
            $applicant_id,
            $applicant['job_posting_id'],
            $start_date,
            $applicant['position_title'],
            $dept['department'] ?? 'operations',
            $_SESSION['user_id']
        ]);
        
        // Update job posting slots
        $stmt = $pdo->prepare("
            UPDATE job_postings 
            SET slots_filled = slots_filled + 1,
                slots_filled_auto = slots_filled_auto + 1
            WHERE id = ?
        ");
        $stmt->execute([$applicant['job_posting_id']]);
        
        $pdo->commit();
        
        simpleLog($pdo, $_SESSION['user_id'], 'final_selection', "Selected applicant #$applicant_id for hiring");
        
        $message = "✅ Candidate selected successfully! Job offer will be sent.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Helper Functions
 */
function getApplicantPhoto($applicant) {
    if (!empty($applicant['photo_path']) && file_exists($applicant['photo_path'])) {
        return htmlspecialchars($applicant['photo_path']);
    }
    return null;
}

function calculateOverallScore($screening, $initial, $final) {
    // Weight distribution: Screening 30%, Initial Interview 40%, Final Interview 30%
    $screening_weight = 0.3;
    $initial_weight = 0.4;
    $final_weight = 0.3;
    
    $overall = ($screening * $screening_weight) + ($initial * $initial_weight) + ($final * $final_weight);
    return round($overall, 2);
}

// Get candidates for final selection (those who passed to final interview)
$query = "
    SELECT 
        ja.*,
        jp.id as job_posting_id,
        jp.title as position_title,
        jp.job_code,
        jp.department,
        jp.slots_available,
        jp.slots_filled,
        se.screening_score,
        se.qualification_match,
        pe.final_percentage as panel_score,
        fi.id as final_interview_id,
        fi.final_score as final_interview_score,
        fi.recommendation as final_recommendation,
        fi.status as final_status,
        u.full_name as selected_by_name,
        ROW_NUMBER() OVER (PARTITION BY ja.job_posting_id ORDER BY 
            (COALESCE(se.screening_score, 0) * 0.3 + 
             COALESCE(pe.final_percentage, 0) * 0.4 + 
             COALESCE(fi.final_score, 0) * 0.3) DESC) as rank_position
    FROM job_applications ja
    LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
    LEFT JOIN screening_evaluations se ON ja.id = se.applicant_id
    LEFT JOIN panel_evaluations pe ON ja.id = pe.applicant_id AND pe.status = 'submitted'
    LEFT JOIN final_interviews fi ON ja.id = fi.applicant_id
    LEFT JOIN users u ON ja.selected_by = u.id
    WHERE ja.final_status = 'final_interview' 
       OR (ja.status = 'hired' AND ja.final_status = 'hired')
";

$params = [];

// Apply filters
if (!empty($status_filter) && $status_filter !== 'all') {
    if ($status_filter === 'pending') {
        $query .= " AND ja.final_status = 'final_interview' AND ja.status != 'hired'";
    } elseif ($status_filter === 'evaluated') {
        $query .= " AND fi.status = 'completed' AND ja.status != 'hired'";
    } elseif ($status_filter === 'selected') {
        $query .= " AND ja.status = 'hired'";
    }
}

if (!empty($job_filter)) {
    $query .= " AND ja.job_posting_id = ?";
    $params[] = $job_filter;
}

if (!empty($search_filter)) {
    $query .= " AND (ja.first_name LIKE ? OR ja.last_name LIKE ? OR ja.application_number LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY jp.title, rank_position";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// Get job postings for filter
$stmt = $pdo->query("SELECT id, job_code, title FROM job_postings WHERE status = 'published' ORDER BY title");
$job_postings = $stmt->fetchAll();

// Get statistics
$stats = [];

// Pending final interviews
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM job_applications 
    WHERE final_status = 'final_interview' AND status != 'hired'
");
$stmt->execute();
$stats['pending_final'] = $stmt->fetchColumn();

// Completed final interviews
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM final_interviews WHERE status = 'completed'
");
$stmt->execute();
$stats['completed_final'] = $stmt->fetchColumn();

// Selected candidates
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM job_applications WHERE status = 'hired'
");
$stmt->execute();
$stats['selected'] = $stmt->fetchColumn();

// Available slots
$stmt = $pdo->prepare("
    SELECT SUM(slots_available - slots_filled) FROM job_postings WHERE status = 'published'
");
$stmt->execute();
$stats['available_slots'] = $stmt->fetchColumn() ?: 0;
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

/* Table Styles - FIXED ALIGNMENT */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow-x: auto;
    margin-bottom: 30px;
}

.selection-table {
    width: 100%;
    border-collapse: collapse;
}

.selection-table th {
    background: var(--light-gray);
    padding: 15px 10px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.selection-table td {
    padding: 15px 10px;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
    font-size: 14px;
    vertical-align: middle;
    white-space: nowrap;
}

.selection-table tr:hover td {
    background: var(--light-gray);
}

/* Column width definitions - using percentages for better responsiveness */
.selection-table th:nth-child(1) { width: 5%; }  /* Rank */
.selection-table th:nth-child(2) { width: 20%; } /* Candidate */
.selection-table th:nth-child(3) { width: 12%; } /* Position */
.selection-table th:nth-child(4) { width: 8%; }  /* Screening */
.selection-table th:nth-child(5) { width: 8%; }  /* Panel */
.selection-table th:nth-child(6) { width: 8%; }  /* Final */
.selection-table th:nth-child(7) { width: 8%; }  /* Overall % */
.selection-table th:nth-child(8) { width: 10%; } /* Status */
.selection-table th:nth-child(9) { width: 21%; } /* Actions */

.rank-badge {
    display: inline-block;
    width: 30px;
    height: 30px;
    line-height: 30px;
    text-align: center;
    border-radius: 50%;
    font-weight: 700;
    font-size: 14px;
}

.rank-1 {
    background: gold;
    color: #000;
}

.rank-2 {
    background: silver;
    color: #000;
}

.rank-3 {
    background: #cd7f32;
    color: #fff;
}

.score-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: 600;
    min-width: 50px;
    text-align: center;
    white-space: nowrap;
}

.score-high {
    background: var(--success)20;
    color: var(--success);
}

.score-medium {
    background: var(--warning)20;
    color: var(--warning);
}

.score-low {
    background: var(--danger)20;
    color: var(--danger);
}

.status-badge {
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
    min-width: 70px;
    text-align: center;
    white-space: nowrap;
}

.status-pending {
    background: var(--warning)20;
    color: var(--warning);
}

.status-completed {
    background: var(--info)20;
    color: var(--info);
}

.status-hired {
    background: var(--success)20;
    color: var(--success);
}

/* Action buttons container */
.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: nowrap;
    white-space: nowrap;
}

/* Final Interview Evaluation Form */
.evaluation-container {
    background: white;
    border-radius: 25px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.applicant-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    color: white;
    display: flex;
    align-items: center;
    gap: 25px;
    flex-wrap: wrap;
}

.applicant-header-photo {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    object-fit: cover;
    border: 3px solid white;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 600;
    color: var(--primary);
}

.applicant-header-info {
    flex: 1;
}

.applicant-header-info h2 {
    font-size: 28px;
    font-weight: 600;
    margin: 0 0 5px;
}

.applicant-header-info p {
    margin: 3px 0;
    opacity: 0.9;
    font-size: 14px;
}

.applicant-header-info i {
    margin-right: 8px;
}

.applicant-header-badge {
    background: rgba(255,255,255,0.2);
    padding: 12px 24px;
    border-radius: 15px;
    text-align: center;
}

.applicant-header-badge .label {
    font-size: 12px;
    opacity: 0.8;
    margin-bottom: 5px;
}

.applicant-header-badge .value {
    font-size: 24px;
    font-weight: 700;
}

/* Two Column Layout */
.evaluation-content {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 25px;
    margin-top: 20px;
}

.questions-column {
    background: var(--light-gray);
    border-radius: 20px;
    padding: 20px;
}

.question-item {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    border: 1px solid var(--border);
}

.question-text {
    font-size: 15px;
    font-weight: 500;
    color: var(--dark);
    margin-bottom: 15px;
}

.rating-scale {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.rating-option {
    flex: 1;
    min-width: 70px;
    text-align: center;
}

.rating-option input[type="radio"] {
    display: none;
}

.rating-option label {
    display: block;
    padding: 10px;
    background: var(--light-gray);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 13px;
    border: 1px solid var(--border);
}

.rating-option input[type="radio"]:checked + label {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: scale(1.02);
}

.comment-box {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    font-size: 13px;
    resize: vertical;
}

.comment-box:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

/* Right Column */
.summary-column {
    background: white;
    border-radius: 20px;
    padding: 20px;
    border: 1px solid var(--border);
    position: sticky;
    top: 20px;
    height: fit-content;
}

.score-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    color: white;
}

.score-item {
    text-align: center;
    margin-bottom: 15px;
}

.score-item .label {
    font-size: 12px;
    opacity: 0.9;
    margin-bottom: 5px;
}

.score-item .value {
    font-size: 42px;
    font-weight: 700;
    line-height: 1.2;
}

.score-item .unit {
    font-size: 14px;
    opacity: 0.8;
}

.passing-info {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    text-align: center;
}

.passing-badge {
    display: inline-block;
    padding: 12px 30px;
    border-radius: 50px;
    font-size: 20px;
    font-weight: 700;
    margin-top: 10px;
}

.passing-hire {
    background: var(--success)20;
    color: var(--success);
    border: 2px solid var(--success);
}

.passing-reject {
    background: var(--danger)20;
    color: var(--danger);
    border: 2px solid var(--danger);
}

/* Approval Modal */
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
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
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
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border);
}

.modal-header h3 {
    font-size: 20px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.modal-close {
    font-size: 28px;
    cursor: pointer;
    color: var(--gray);
    transition: all 0.3s;
}

.modal-close:hover {
    color: var(--danger);
    transform: rotate(90deg);
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
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
@media (max-width: 1200px) {
    .selection-table th:nth-child(2) { width: 18%; }
    .selection-table th:nth-child(9) { width: 19%; }
}

@media (max-width: 1024px) {
    .evaluation-content {
        grid-template-columns: 1fr;
    }
    
    .summary-column {
        position: static;
    }
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .applicant-header {
        flex-direction: column;
        text-align: center;
    }
    
    .rating-scale {
        flex-direction: column;
    }
    
    .action-buttons {
        flex-wrap: wrap;
    }
}
</style>

<!-- ==================== HTML CONTENT ==================== -->

<!-- Messages -->
<?php if (isset($_GET['success'])): ?>
<div class="alert-success">
    <i class="fas fa-check-circle"></i> Operation completed successfully!
</div>
<?php endif; ?>

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

<!-- Check if we're in final interview evaluation mode -->
<?php if (isset($_GET['action']) && $_GET['action'] === 'evaluate_final' && isset($_GET['id'])): 
    $final_interview_id = $_GET['id'];
    
    // Get final interview details
    $stmt = $pdo->prepare("
        SELECT fi.*, 
               ja.id as applicant_id, ja.first_name, ja.last_name, ja.email, ja.photo_path, ja.application_number,
               jp.id as job_posting_id, jp.title as position_title, jp.job_code, jp.department,
               u.full_name as interviewer_name
        FROM final_interviews fi
        JOIN job_applications ja ON fi.applicant_id = ja.id
        LEFT JOIN job_postings jp ON fi.job_posting_id = jp.id
        LEFT JOIN users u ON fi.interviewer_id = u.id
        WHERE fi.id = ?
    ");
    $stmt->execute([$final_interview_id]);
    $interview = $stmt->fetch();
    
    if (!$interview) {
        echo '<div class="alert-danger"><i class="fas fa-exclamation-circle"></i> Final interview not found.</div>';
    } else {
        // Get evaluation questions
        $stmt = $pdo->query("
            SELECT * FROM final_evaluation_questions 
            WHERE is_active = 1 
            ORDER BY sort_order
        ");
        $questions = $stmt->fetchAll();
        
        // Get existing responses
        $responses = [];
        if (!empty($questions)) {
            $stmt = $pdo->prepare("
                SELECT * FROM final_evaluation_responses 
                WHERE final_interview_id = ?
            ");
            $stmt->execute([$final_interview_id]);
            $existing_responses = $stmt->fetchAll();
            foreach ($existing_responses as $r) {
                $responses[$r['question_id']] = $r;
            }
        }
        
        // Calculate current scores
        $total_score = 0;
        $max_score = count($questions) * 5;
        $rated_count = 0;
        
        foreach ($questions as $q) {
            if (isset($responses[$q['id']]['rating'])) {
                $total_score += intval($responses[$q['id']]['rating']);
                $rated_count++;
            }
        }
        
        $completion_percentage = $rated_count > 0 ? round(($rated_count / count($questions)) * 100) : 0;
        $final_percentage = $total_score > 0 ? round(($total_score / $max_score) * 100, 1) : 0;
        
        // Determine if passing
        $is_passing = $final_percentage >= 95;
?>

<!-- Final Interview Evaluation Form -->
<div style="margin-bottom: 20px;">
    <a href="?page=recruitment&subpage=final-selection" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Final Selection
    </a>
</div>

<div class="evaluation-container">
    <form method="POST" id="finalEvaluationForm" onsubmit="return validateFinalForm()">
        <input type="hidden" name="final_interview_id" value="<?php echo $final_interview_id; ?>">
        
        <!-- Applicant Header -->
        <div class="applicant-header">
            <?php 
            $photoPath = getApplicantPhoto($interview);
            $fullName = $interview['first_name'] . ' ' . $interview['last_name'];
            $initials = strtoupper(substr($interview['first_name'] ?? '', 0, 1) . substr($interview['last_name'] ?? '', 0, 1)) ?: '?';
            ?>
            
            <?php if ($photoPath): ?>
                <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="applicant-header-photo">
            <?php else: ?>
                <div class="applicant-header-photo">
                    <?php echo $initials; ?>
                </div>
            <?php endif; ?>
            
            <div class="applicant-header-info">
                <h2><?php echo htmlspecialchars($fullName); ?></h2>
                <p><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($interview['application_number']); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($interview['email']); ?></p>
            </div>
            
            <div class="applicant-header-badge">
                <div class="label">Position</div>
                <div class="value"><?php echo htmlspecialchars($interview['position_title'] ?: 'N/A'); ?></div>
                <div style="font-size: 12px; margin-top: 5px;"><?php echo htmlspecialchars($interview['job_code']); ?></div>
            </div>
        </div>
        
        <!-- TWO COLUMN LAYOUT -->
        <div class="evaluation-content">
            <!-- LEFT COLUMN: Questions -->
            <div class="questions-column">
                <!-- Progress Bar -->
                <div style="margin-bottom: 20px; background: white; padding: 15px; border-radius: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-size: 13px; color: var(--gray);">Evaluation Progress</span>
                        <span style="font-size: 13px; font-weight: 600; color: var(--primary);"><?php echo $completion_percentage; ?>% Complete</span>
                    </div>
                    <div class="progress-bar" style="height: 8px; background: var(--light-gray); border-radius: 4px;">
                        <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%; height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-light)); border-radius: 4px;"></div>
                    </div>
                </div>
                
                <!-- Questions -->
                <?php foreach ($questions as $index => $question): ?>
                <div class="question-item">
                    <div class="question-text">
                        <?php echo ($index + 1) . '. ' . htmlspecialchars($question['question']); ?>
                        <span style="font-size: 11px; color: var(--gray); margin-left: 10px;">(<?php echo ucfirst(str_replace('_', ' ', $question['category'])); ?>)</span>
                    </div>
                    
                    <div class="rating-scale">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                        <div class="rating-option">
                            <input type="radio" 
                                   name="rating[<?php echo $question['id']; ?>]" 
                                   id="rating_<?php echo $question['id']; ?>_<?php echo $i; ?>" 
                                   value="<?php echo $i; ?>"
                                   <?php echo (isset($responses[$question['id']]['rating']) && $responses[$question['id']]['rating'] == $i) ? 'checked' : ''; ?>
                                   onchange="updateFinalScores()">
                            <label for="rating_<?php echo $question['id']; ?>_<?php echo $i; ?>">
                                <?php 
                                $labels = ['Poor', 'Below', 'Average', 'Good', 'Excel'];
                                echo $labels[$i-1] . ' (' . $i . ')';
                                ?>
                            </label>
                        </div>
                        <?php endfor; ?>
                    </div>
                    
                    <textarea name="comments[<?php echo $question['id']; ?>]" 
                              class="comment-box" 
                              placeholder="Add comments/notes for this question (optional)"><?php echo htmlspecialchars($responses[$question['id']]['comments'] ?? ''); ?></textarea>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- RIGHT COLUMN: Score Summary & Result -->
            <div class="summary-column">
                <!-- Live Score Summary -->
                <div class="score-card">
                    <div class="score-item">
                        <div class="label">Total Score</div>
                        <div class="value" id="totalScore"><?php echo $total_score; ?></div>
                        <div class="unit">out of <?php echo $max_score; ?></div>
                    </div>
                    
                    <div style="height: 2px; background: rgba(255,255,255,0.2); margin: 15px 0;"></div>
                    
                    <div class="score-item">
                        <div class="label">Percentage</div>
                        <div class="value" id="finalPercentage"><?php echo $final_percentage; ?>%</div>
                    </div>
                </div>
                
                <!-- Passing Info -->
                <div class="passing-info">
                    <h4 style="margin: 0 0 10px; color: var(--dark);">Final Result</h4>
                    <div class="passing-badge <?php echo $is_passing ? 'passing-hire' : 'passing-reject'; ?>" id="resultBadge">
                        <?php echo $is_passing ? 'PASSED' : 'FAILED'; ?>
                    </div>
                    
                    <div style="margin-top: 15px; font-size: 14px; color: var(--gray);">
                        <p><strong>Passing Score:</strong> 95%</p>
                        <p><strong>Current Score:</strong> <?php echo $final_percentage; ?>%</p>
                        <p style="color: <?php echo $is_passing ? 'var(--success)' : 'var(--danger)'; ?>; font-weight: 600;">
                            <?php echo $is_passing ? '✅ Candidate passed' : '❌ Candidate did not meet passing score'; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Strengths & Weaknesses -->
                <div style="background: var(--light-gray); border-radius: 15px; padding: 20px;">
                    <h4 style="margin-top: 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Strengths</h4>
                    <textarea name="strengths" rows="3" placeholder="What are the candidate's key strengths?" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 15px;"><?php echo htmlspecialchars($interview['strengths'] ?? ''); ?></textarea>
                    
                    <h4><i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Weaknesses</h4>
                    <textarea name="weaknesses" rows="3" placeholder="What areas need improvement?" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 15px;"><?php echo htmlspecialchars($interview['weaknesses'] ?? ''); ?></textarea>
                    
                    <h4><i class="fas fa-comment" style="color: var(--primary);"></i> Overall Comments</h4>
                    <textarea name="overall_comments" rows="3" placeholder="Additional comments..." style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;"><?php echo htmlspecialchars($interview['overall_comments'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--border);">
            <button type="submit" name="submit_final_evaluation" class="btn btn-success" onclick="return confirmFinalSubmit()">
                <i class="fas fa-check-circle"></i> Submit Final Evaluation
            </button>
        </div>
    </form>
</div>

<script>
function updateFinalScores() {
    let total = 0;
    let count = 0;
    const radios = document.querySelectorAll('input[type="radio"]:checked');
    
    radios.forEach(radio => {
        if (radio.name.startsWith('rating[')) {
            total += parseInt(radio.value);
            count++;
        }
    });
    
    const maxScore = <?php echo count($questions); ?> * 5;
    const percentage = count > 0 ? (total / maxScore * 100).toFixed(1) : 0;
    
    document.getElementById('totalScore').textContent = total;
    document.getElementById('finalPercentage').textContent = percentage + '%';
    
    // Update result badge
    const badge = document.getElementById('resultBadge');
    if (percentage >= 95) {
        badge.className = 'passing-badge passing-hire';
        badge.textContent = 'PASSED';
    } else {
        badge.className = 'passing-badge passing-reject';
        badge.textContent = 'FAILED';
    }
}

function validateFinalForm() {
    const radios = document.querySelectorAll('input[type="radio"]:checked');
    
    if (radios.length === 0) {
        alert('Please rate at least one question before submitting.');
        return false;
    }
    
    return true;
}

function confirmFinalSubmit() {
    if (!validateFinalForm()) return false;
    return confirm('Are you sure you want to submit this final evaluation? An email will be sent to the applicant with the result.');
}
</script>

<?php 
    } // end evaluation exists check
else: 
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-title">
        <i class="fas fa-trophy"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div>
        <span class="status-badge" style="background: var(--primary-transparent); color: var(--primary);">
            <i class="fas fa-users"></i> Available Slots: <?php echo $stats['available_slots']; ?>
        </span>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Final Interviews</span>
            <span class="stat-value"><?php echo $stats['pending_final']; ?></span>
            <div class="stat-small">
                <i class="fas fa-calendar" style="color: var(--warning);"></i> Ready for evaluation
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Completed Final</span>
            <span class="stat-value"><?php echo $stats['completed_final']; ?></span>
            <div class="stat-small">
                <i class="fas fa-star" style="color: var(--info);"></i> Evaluated
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Selected</span>
            <span class="stat-value"><?php echo $stats['selected']; ?></span>
            <div class="stat-small">
                <i class="fas fa-trophy" style="color: var(--success);"></i> Hired
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Selection Rate</span>
            <span class="stat-value"><?php 
                $total = $stats['completed_final'] + $stats['selected'];
                $rate = $total > 0 ? round(($stats['selected'] / $total) * 100) : 0;
                echo $rate; ?>%
            </span>
            <div class="stat-small">
                <i class="fas fa-percent"></i> of final candidates
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Candidates
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="recruitment">
        <input type="hidden" name="subpage" value="final-selection">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Final Candidates</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Final Interview</option>
                    <option value="evaluated" <?php echo $status_filter == 'evaluated' ? 'selected' : ''; ?>>Evaluated (Not Selected)</option>
                    <option value="selected" <?php echo $status_filter == 'selected' ? 'selected' : ''; ?>>Selected / Hired</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Job Position</label>
                <select name="job_id">
                    <option value="">All Positions</option>
                    <?php foreach ($job_postings as $job): ?>
                    <option value="<?php echo $job['id']; ?>" <?php echo $job_filter == $job['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($job['title'] . ' (' . $job['job_code'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name or Application #" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=recruitment&subpage=final-selection" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Candidates Table - FIXED ALIGNMENT -->
<div class="table-container">
    <?php if (empty($candidates)): ?>
    <div style="text-align: center; padding: 60px; color: var(--gray);">
        <i class="fas fa-users" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
        <h3>No Candidates Found</h3>
        <p>No candidates are currently in the final selection stage.</p>
    </div>
    <?php else: ?>
    <table class="selection-table">
        <thead>
            <tr>
                <th>Rank</th>
                <th>Candidate</th>
                <th>Position</th>
                <th>Screening</th>
                <th>Panel</th>
                <th>Final</th>
                <th>Overall %</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $current_job = '';
            foreach ($candidates as $candidate): 
                // Calculate overall score
                $screening = $candidate['screening_score'] ?? 0;
                $panel = $candidate['panel_score'] ?? 0;
                $final = $candidate['final_interview_score'] ?? 0;
                $overall = calculateOverallScore($screening, $panel, $final);
                
                // Show job grouping
                if ($current_job != $candidate['job_posting_id']):
                    $current_job = $candidate['job_posting_id'];
            ?>
            <tr style="background: var(--light-gray);">
                <td colspan="9" style="padding: 10px 15px; font-weight: 600; color: var(--primary);">
                    <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($candidate['position_title']); ?> 
                    (<?php echo $candidate['slots_available'] - $candidate['slots_filled']; ?> slots remaining)
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>
                    <?php if ($candidate['rank_position'] <= 3): ?>
                    <span class="rank-badge rank-<?php echo $candidate['rank_position']; ?>">
                        <?php echo $candidate['rank_position']; ?>
                    </span>
                    <?php else: ?>
                    <span style="font-weight: 600; margin-left: 5px;">#<?php echo $candidate['rank_position']; ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <?php 
                        $photoPath = getApplicantPhoto($candidate);
                        $fullName = $candidate['first_name'] . ' ' . $candidate['last_name'];
                        $initials = strtoupper(substr($candidate['first_name'] ?? '', 0, 1) . substr($candidate['last_name'] ?? '', 0, 1)) ?: '?';
                        ?>
                        
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($fullName); ?>" style="width: 40px; height: 40px; border-radius: 10px; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <strong><?php echo htmlspecialchars($fullName); ?></strong>
                            <div style="font-size: 11px; color: var(--gray);">#<?php echo $candidate['application_number']; ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($candidate['job_code']); ?></strong>
                    <div style="font-size: 11px; color: var(--gray);"><?php echo htmlspecialchars($candidate['department']); ?></div>
                </td>
                <td>
                    <span class="score-badge <?php 
                        echo $screening >= 80 ? 'score-high' : ($screening >= 60 ? 'score-medium' : 'score-low'); 
                    ?>">
                        <?php echo $screening; ?>%
                    </span>
                </td>
                <td>
                    <span class="score-badge <?php 
                        echo $panel >= 80 ? 'score-high' : ($panel >= 60 ? 'score-medium' : 'score-low'); 
                    ?>">
                        <?php echo $panel; ?>%
                    </span>
                </td>
                <td>
                    <?php if ($candidate['final_interview_score']): ?>
                    <span class="score-badge <?php 
                        echo $final >= 95 ? 'score-high' : ($final >= 75 ? 'score-medium' : 'score-low'); 
                    ?>">
                        <?php echo $final; ?>%
                    </span>
                    <?php else: ?>
                    <span class="score-badge" style="background: var(--light-gray); color: var(--gray);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="score-badge <?php 
                        echo $overall >= 85 ? 'score-high' : ($overall >= 70 ? 'score-medium' : 'score-low'); 
                    ?>">
                        <?php echo $overall; ?>%
                    </span>
                </td>
                <td>
                    <?php if ($candidate['status'] == 'hired'): ?>
                    <span class="status-badge status-hired">
                        <i class="fas fa-check"></i> Hired
                    </span>
                    <?php elseif ($candidate['final_status'] == 'completed'): ?>
                    <span class="status-badge status-completed">
                        <i class="fas fa-check-circle"></i> Evaluated
                    </span>
                    <?php else: ?>
                    <span class="status-badge status-pending">
                        <i class="fas fa-clock"></i> Pending
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="action-buttons">
                        <?php if ($candidate['status'] != 'hired'): ?>
                            <?php if (!$candidate['final_interview_id']): ?>
                            <a href="?page=recruitment&subpage=final-selection&action=start_final&id=<?php echo $candidate['id']; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-play"></i> Start
                            </a>
                            <?php elseif ($candidate['final_status'] != 'completed'): ?>
                            <a href="?page=recruitment&subpage=final-selection&action=evaluate_final&id=<?php echo $candidate['final_interview_id']; ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-pencil-alt"></i> Continue
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($candidate['final_interview_score'] && $candidate['final_interview_score'] >= 95 && $candidate['status'] != 'hired'): ?>
                            <button class="btn btn-success btn-sm" onclick="openApprovalModal(<?php echo htmlspecialchars(json_encode($candidate)); ?>)">
                                <i class="fas fa-check-double"></i> Select
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline btn-sm" onclick="viewCandidateDetails(<?php echo htmlspecialchars(json_encode($candidate)); ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Approval Modal -->
<div id="approvalModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle" style="color: var(--success);"></i> Final Approval</h3>
            <span class="modal-close" onclick="closeApprovalModal()">&times;</span>
        </div>
        
        <form method="POST" id="approvalForm">
            <input type="hidden" name="applicant_id" id="approval_applicant_id">
            
            <div id="approvalCandidateInfo" style="background: var(--light-gray); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
                <!-- Filled by JavaScript -->
            </div>
            
            <div class="form-group">
                <label>Approved Salary (₱)</label>
                <input type="number" name="approved_salary" id="approved_salary" step="1000" min="10000" required placeholder="e.g., 25000">
            </div>
            
            <div class="form-group">
                <label>Proposed Start Date</label>
                <input type="date" name="start_date" id="start_date" required min="<?php echo date('Y-m-d', strtotime('+1 week')); ?>">
            </div>
            
            <div class="form-group">
                <label>Approval Remarks</label>
                <textarea name="remarks" rows="3" placeholder="Any notes about this selection..."></textarea>
            </div>
            
            <div style="background: var(--warning)10; border-radius: 12px; padding: 15px; margin: 20px 0; border-left: 4px solid var(--warning);">
                <p style="margin: 0; font-size: 13px; color: var(--dark);">
                    <i class="fas fa-info-circle" style="color: var(--warning);"></i> 
                    Once approved, this candidate will be moved to Job Offer stage and notified via email.
                </p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeApprovalModal()">Cancel</button>
                <button type="submit" name="approve_selection" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Confirm Selection
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Candidate Modal -->
<div id="viewCandidateModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-user" style="color: var(--info);"></i> Candidate Details</h3>
            <span class="modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        
        <div id="candidateDetailsContent">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<script>
function openApprovalModal(candidate) {
    document.getElementById('approval_applicant_id').value = candidate.id;
    
    const infoHtml = `
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 20px;">
                ${candidate.first_name ? candidate.first_name.charAt(0) + (candidate.last_name ? candidate.last_name.charAt(0) : '') : '?'}
            </div>
            <div>
                <h4 style="margin: 0 0 5px;">${candidate.first_name} ${candidate.last_name}</h4>
                <p style="margin: 0; font-size: 13px; color: var(--gray);">${candidate.position_title}</p>
                <p style="margin: 5px 0 0; font-size: 12px;"><strong>Final Score:</strong> ${candidate.final_interview_score}%</p>
            </div>
        </div>
    `;
    
    document.getElementById('approvalCandidateInfo').innerHTML = infoHtml;
    
    // Set default start date (2 weeks from now)
    const startDate = new Date();
    startDate.setDate(startDate.getDate() + 14);
    document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
    
    document.getElementById('approvalModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeApprovalModal() {
    document.getElementById('approvalModal').classList.remove('active');
    document.body.style.overflow = '';
}

function viewCandidateDetails(candidate) {
    const html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; border-radius: 20px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 24px; margin: 0 auto 10px;">
                ${candidate.first_name ? candidate.first_name.charAt(0) + (candidate.last_name ? candidate.last_name.charAt(0) : '') : '?'}
            </div>
            <h2 style="font-size: 20px; margin-bottom: 5px;">${candidate.first_name} ${candidate.last_name}</h2>
            <p style="color: var(--gray);">${candidate.application_number}</p>
        </div>
        
        <div style="background: var(--light-gray); border-radius: 16px; padding: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Position</p>
                    <p style="font-weight: 500;">${candidate.position_title || 'N/A'}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Department</p>
                    <p style="font-weight: 500;">${candidate.department ? candidate.department.charAt(0).toUpperCase() + candidate.department.slice(1) : 'N/A'}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Screening Score</p>
                    <p style="font-weight: 500;">${candidate.screening_score || 0}%</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Panel Score</p>
                    <p style="font-weight: 500;">${candidate.panel_score || 0}%</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Final Score</p>
                    <p style="font-weight: 500;">${candidate.final_interview_score || 'Pending'}%</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Overall Rank</p>
                    <p style="font-weight: 500;">#${candidate.rank_position}</p>
                </div>
            </div>
            
            ${candidate.final_recommendation ? `
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                <p style="font-size: 12px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">Final Recommendation</p>
                <span class="status-badge ${candidate.final_recommendation == 'hire' ? 'status-hired' : 'status-pending'}">
                    ${candidate.final_recommendation == 'hire' ? 'Recommended for Hire' : 'Not Recommended'}
                </span>
            </div>
            ` : ''}
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
            <button class="btn btn-outline" onclick="closeViewModal()">Close</button>
        </div>
    `;
    
    document.getElementById('candidateDetailsContent').innerHTML = html;
    document.getElementById('viewCandidateModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewCandidateModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeApprovalModal();
        closeViewModal();
    }
});

// Close modals when clicking outside
window.onclick = function(event) {
    const approvalModal = document.getElementById('approvalModal');
    const viewModal = document.getElementById('viewCandidateModal');
    
    if (event.target == approvalModal) closeApprovalModal();
    if (event.target == viewModal) closeViewModal();
}
</script>

<?php endif; // End evaluation mode check ?>

<?php
// End output buffering and flush
ob_end_flush();
?>