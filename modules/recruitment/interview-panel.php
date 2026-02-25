<?php
// Start output buffering at the VERY FIRST LINE - NO SPACES OR CHARACTERS BEFORE THIS
ob_start();

// modules/recruitment/interview-panel.php
$page_title = "Interview Panel Evaluation";

// Include required files
require_once 'config/mail_config.php';

// Handle actions - MOVED TO TOP BEFORE ANY OUTPUT
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
 * Send email notification to applicant based on result
 */
function sendResultEmail($applicant_email, $applicant_name, $result_data) {
    try {
        $mail = MailConfig::getInstance();
        
        // Clear previous recipients
        $mail->clearAddresses();
        $mail->clearAttachments();
        
        // Recipient
        $mail->addAddress($applicant_email, $applicant_name);
        $mail->addBCC('hr@freightmanagement.com', 'HR Department');
        
        // Set subject based on result
        switch ($result_data['result']) {
            case 'hire':
                $mail->Subject = "🎉 Congratulations! Job Offer - {$result_data['position']}";
                break;
            case 'final_interview':
                $mail->Subject = "✅ You've Passed! Final Interview - {$result_data['position']}";
                break;
            case 'reject':
                $mail->Subject = "Update on Your Application - {$result_data['position']}";
                break;
            default:
                $mail->Subject = "Interview Result - {$result_data['position']}";
        }
        
        // Build email body
        $body = buildResultEmailHTML($applicant_name, $result_data);
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</div>'], ["\n", "\n\n", "\n"], $body));
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}

/**
 * Build HTML email for result notification
 */
function buildResultEmailHTML($applicant_name, $data) {
    $result = $data['result'];
    $position = $data['position'];
    $score = $data['score'] ?? 0;
    
    $content = '';
    
    if ($result == 'hire') {
        $content = '
        <div style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); border-radius: 15px; padding: 30px; margin: 20px 0; text-align: center; color: white;">
            <i class="fas fa-trophy" style="font-size: 60px; margin-bottom: 20px;"></i>
            <h2 style="font-size: 28px; margin: 0 0 10px;">Congratulations!</h2>
            <p style="font-size: 18px; opacity: 0.9;">You have been selected for the position</p>
            <h3 style="font-size: 24px; margin: 15px 0; background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 50px; display: inline-block;">' . htmlspecialchars($position) . '</h3>
        </div>
        
        <div style="background: #f8fafd; border-radius: 15px; padding: 25px; margin: 20px 0;">
            <h4 style="color: #2c3e50; margin-bottom: 15px;">📋 Next Steps:</h4>
            <ol style="color: #64748b; line-height: 1.8;">
                <li>Our HR team will contact you within 24-48 hours</li>
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
    } elseif ($result == 'final_interview') {
        $content = '
        <div style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); border-radius: 15px; padding: 30px; margin: 20px 0; text-align: center; color: white;">
            <i class="fas fa-check-circle" style="font-size: 60px; margin-bottom: 20px;"></i>
            <h2 style="font-size: 28px; margin: 0 0 10px;">You\'ve Passed!</h2>
            <p style="font-size: 18px; opacity: 0.9;">Moving to Final Interview</p>
            <h3 style="font-size: 20px; margin: 15px 0;">' . htmlspecialchars($position) . '</h3>
        </div>
        
        <div style="background: #f8fafd; border-radius: 15px; padding: 25px; margin: 20px 0;">
            <h4 style="color: #2c3e50; margin-bottom: 15px;">📋 What\'s Next:</h4>
            <p style="color: #64748b; line-height: 1.8;">
                Congratulations on passing the initial interview! Our HR team will contact you shortly to schedule your final interview. 
                Please prepare for the final round which will include:
            </p>
            <ul style="color: #64748b; margin-top: 10px;">
                <li>In-depth technical assessment</li>
                <li>Meeting with department head</li>
                <li>Final salary discussion</li>
            </ul>
        </div>';
    } else {
        $content = '
        <div style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); border-radius: 15px; padding: 30px; margin: 20px 0; text-align: center; color: white;">
            <i class="fas fa-frown" style="font-size: 60px; margin-bottom: 20px;"></i>
            <h2 style="font-size: 28px; margin: 0 0 10px;">Thank You for Your Interest</h2>
            <p style="font-size: 18px; opacity: 0.9;">Update on your application</p>
        </div>
        
        <div style="background: #f8fafd; border-radius: 15px; padding: 25px; margin: 20px 0;">
            <p style="color: #64748b; line-height: 1.8;">Dear ' . htmlspecialchars($applicant_name) . ',</p>
            <p style="color: #64748b; line-height: 1.8;">
                Thank you for taking the time to interview with us for the <strong>' . htmlspecialchars($position) . '</strong> position.
                After careful consideration, we regret to inform you that we will not be moving forward with your application at this time.
            </p>
            <p style="color: #64748b; line-height: 1.8;">
                This decision was difficult, as we were impressed with your qualifications. However, we have selected candidates whose experience more closely matches our current needs.
            </p>
            <p style="color: #64748b; line-height: 1.8;">
                We wish you the best in your job search and future endeavors. Please feel free to apply again for future openings that match your profile.
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
 * Handle start evaluation
 */
if (isset($_GET['action']) && $_GET['action'] === 'start' && isset($_GET['id'])) {
    try {
        $interview_id = $_GET['id'];
        
        // Check if evaluation already exists
        $stmt = $pdo->prepare("
            SELECT id FROM panel_evaluations 
            WHERE interview_id = ? AND panel_id = ?
        ");
        $stmt->execute([$interview_id, $_SESSION['user_id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Clear output buffer and redirect
            ob_clean();
            header("Location: ?page=recruitment&subpage=interview-panel&action=evaluate&id=" . $existing['id']);
            exit;
        }
        
        // Get interview details
        $stmt = $pdo->prepare("
            SELECT i.*, ja.id as applicant_id, ja.first_name, ja.last_name, ja.email, ja.photo_path,
                   jp.id as job_posting_id, jp.title as position_title, jp.job_code, jp.slots_available, jp.slots_filled,
                   i.interview_type as interview_round, i.interview_date
            FROM interviews i
            JOIN job_applications ja ON i.applicant_id = ja.id
            LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
            WHERE i.id = ? AND i.status = 'scheduled'
        ");
        $stmt->execute([$interview_id]);
        $interview = $stmt->fetch();
        
        if (!$interview) {
            throw new Exception("Interview not found or already completed");
        }
        
        // Get template for this position
        $stmt = $pdo->prepare("
            SELECT et.* 
            FROM evaluation_templates et
            WHERE et.position_id = ? AND et.is_active = 1
            ORDER BY et.id DESC LIMIT 1
        ");
        $stmt->execute([$interview['job_posting_id']]);
        $template = $stmt->fetch();
        
        if (!$template) {
            throw new Exception("No evaluation template found for this position");
        }
        
        // Create new evaluation
        $stmt = $pdo->prepare("
            INSERT INTO panel_evaluations (
                interview_id, applicant_id, panel_id, status
            ) VALUES (?, ?, ?, 'ongoing')
        ");
        $stmt->execute([$interview_id, $interview['applicant_id'], $_SESSION['user_id']]);
        $evaluation_id = $pdo->lastInsertId();
        
        // Update interview status to ongoing
        $stmt = $pdo->prepare("UPDATE interviews SET status = 'ongoing' WHERE id = ?");
        $stmt->execute([$interview_id]);
        
        simpleLog($pdo, $_SESSION['user_id'], 'start_evaluation', "Started evaluation for interview #$interview_id");
        
        // Clear output buffer and redirect
        ob_clean();
        header("Location: ?page=recruitment&subpage=interview-panel&action=evaluate&id=" . $evaluation_id);
        exit;
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle evaluation form submission
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    try {
        $pdo->beginTransaction();
        
        $evaluation_id = $_POST['evaluation_id'];
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
                SELECT id FROM evaluation_responses 
                WHERE evaluation_id = ? AND question_id = ?
            ");
            $stmt->execute([$evaluation_id, $question_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE evaluation_responses SET
                        rating = ?,
                        comments = ?
                    WHERE evaluation_id = ? AND question_id = ?
                ");
                $stmt->execute([
                    $rating,
                    $comments[$question_id] ?? null,
                    $evaluation_id,
                    $question_id
                ]);
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO evaluation_responses (
                        evaluation_id, question_id, rating, comments
                    ) VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $evaluation_id,
                    $question_id,
                    $rating,
                    $comments[$question_id] ?? null
                ]);
            }
        }
        
        $final_percentage = ($total_score / $max_score) * 100;
        
        // AUTOMATIC RECOMMENDATION BASED ON SCORE
        $recommendation = 'reject'; // default
        if ($final_percentage >= 100) {
            $recommendation = 'hire';
        } elseif ($final_percentage >= 85) {
            $recommendation = 'final_interview';
        } elseif ($final_percentage >= 75) {
            $recommendation = 'hold';
        } else {
            $recommendation = 'reject';
        }
        
        // Update evaluation
        $stmt = $pdo->prepare("
            UPDATE panel_evaluations SET
                total_score = ?,
                max_score = ?,
                final_percentage = ?,
                recommendation = ?,
                strengths = ?,
                weaknesses = ?,
                overall_comments = ?,
                status = 'submitted',
                submitted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $total_score,
            $max_score,
            $final_percentage,
            $recommendation,
            $strengths,
            $weaknesses,
            $overall_comments,
            $evaluation_id
        ]);
        
        // Get interview ID and details
        $stmt = $pdo->prepare("
            SELECT pe.*, i.job_posting_id, i.applicant_id, ja.status as applicant_status,
                   ja.first_name, ja.last_name, ja.email,
                   jp.title as position_title, jp.slots_available, jp.slots_filled, jp.slots_filled_auto
            FROM panel_evaluations pe
            JOIN interviews i ON pe.interview_id = i.id
            JOIN job_applications ja ON pe.applicant_id = ja.id
            LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
            WHERE pe.id = ?
        ");
        $stmt->execute([$evaluation_id]);
        $eval_data = $stmt->fetch();
        $interview_id = $eval_data['interview_id'];
        
        // Check if all panels have submitted
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted
            FROM panel_evaluations 
            WHERE interview_id = ?
        ");
        $stmt->execute([$interview_id]);
        $progress = $stmt->fetch();
        
        // If all panels have submitted, process final decision
        if ($progress['total'] == $progress['submitted']) {
            // Get all recommendations for this interview
            $stmt = $pdo->prepare("
                SELECT recommendation, final_percentage 
                FROM panel_evaluations 
                WHERE interview_id = ?
            ");
            $stmt->execute([$interview_id]);
            $all_recommendations = $stmt->fetchAll();
            
            // Calculate average percentage
            $avg_percentage = 0;
            foreach ($all_recommendations as $rec) {
                $avg_percentage += $rec['final_percentage'];
            }
            $avg_percentage = $avg_percentage / count($all_recommendations);
            
            // Determine final recommendation based on average
            $final_recommendation = 'reject';
            if ($avg_percentage >= 100) {
                $final_recommendation = 'hire';
            } elseif ($avg_percentage >= 85) {
                $final_recommendation = 'final_interview';
            } elseif ($avg_percentage >= 75) {
                $final_recommendation = 'hold';
            } else {
                $final_recommendation = 'reject';
            }
            
            // Update interview with final recommendation
            $stmt = $pdo->prepare("
                UPDATE interviews SET
                    status = 'completed',
                    final_recommendation = ?,
                    auto_processed = 1
                WHERE id = ?
            ");
            $stmt->execute([$final_recommendation, $interview_id]);
            
            // Update applicant status based on final recommendation
            $applicant_id = $eval_data['applicant_id'];
            $applicant_email = $eval_data['email'];
            $applicant_name = $eval_data['first_name'] . ' ' . $eval_data['last_name'];
            $position_title = $eval_data['position_title'] ?: 'the position';
            
            // Prepare result data for email
            $result_data = [
                'result' => $final_recommendation,
                'position' => $position_title,
                'score' => round($avg_percentage, 1)
            ];
            
            if ($final_recommendation == 'hire') {
                // HIRE - automatically fill slot
                $stmt = $pdo->prepare("
                    UPDATE job_applications 
                    SET status = 'hired', 
                        final_status = 'hired',
                        hired_date = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$applicant_id]);
                
                // Update job posting slots
                if ($eval_data['job_posting_id']) {
                    $stmt = $pdo->prepare("
                        UPDATE job_postings 
                        SET slots_filled_auto = slots_filled_auto + 1,
                            slots_filled = slots_filled + 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$eval_data['job_posting_id']]);
                }
                
                // Create new hire record
                $stmt = $pdo->prepare("
                    INSERT INTO new_hires (
                        applicant_id, job_posting_id, hire_date, position, department, status
                    ) SELECT ?, ?, CURDATE(), jp.title, jp.department, 'onboarding'
                    FROM job_postings jp WHERE jp.id = ?
                ");
                $stmt->execute([$applicant_id, $eval_data['job_posting_id'], $eval_data['job_posting_id']]);
                
            } elseif ($final_recommendation == 'final_interview') {
                // MOVE TO FINAL INTERVIEW
                $stmt = $pdo->prepare("
                    UPDATE job_applications 
                    SET status = 'interviewed',
                        final_status = 'final_interview'
                    WHERE id = ?
                ");
                $stmt->execute([$applicant_id]);
                
            } elseif ($final_recommendation == 'hold') {
                // HOLD - keep in interviewed status
                $stmt = $pdo->prepare("
                    UPDATE job_applications 
                    SET status = 'interviewed',
                        final_status = 'pending'
                    WHERE id = ?
                ");
                $stmt->execute([$applicant_id]);
                
            } else { // reject
                // REJECT
                $stmt = $pdo->prepare("
                    UPDATE job_applications 
                    SET status = 'rejected',
                        final_status = 'rejected'
                    WHERE id = ?
                ");
                $stmt->execute([$applicant_id]);
            }
            
            // Send email notification to applicant
            $email_result = sendResultEmail($applicant_email, $applicant_name, $result_data);
            
            // Log communication
            $stmt = $pdo->prepare("
                INSERT INTO communication_log (
                    applicant_id, communication_type, subject, message, sent_by, status
                ) VALUES (?, 'email', ?, ?, ?, ?)
            ");
            
            $subject = "Application Update: {$position_title}";
            $email_message = "Final decision: " . ucfirst(str_replace('_', ' ', $final_recommendation));
            
            $stmt->execute([
                $applicant_id,
                $subject,
                $email_message,
                $_SESSION['user_id'],
                $email_result['success'] ? 'sent' : 'failed'
            ]);
        }
        
        $pdo->commit();
        
        simpleLog($pdo, $_SESSION['user_id'], 'submit_evaluation', "Submitted evaluation #$evaluation_id");
        
        // Clear output buffer and redirect
        ob_clean();
        header("Location: ?page=recruitment&subpage=interview-panel&success=1");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

/**
 * Handle save draft
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_draft'])) {
    try {
        $pdo->beginTransaction();
        
        $evaluation_id = $_POST['evaluation_id'];
        $ratings = $_POST['rating'] ?? [];
        $comments = $_POST['comments'] ?? [];
        $strengths = $_POST['strengths'] ?? '';
        $weaknesses = $_POST['weaknesses'] ?? '';
        $overall_comments = $_POST['overall_comments'] ?? '';
        
        foreach ($ratings as $question_id => $rating) {
            // Check if response exists
            $stmt = $pdo->prepare("
                SELECT id FROM evaluation_responses 
                WHERE evaluation_id = ? AND question_id = ?
            ");
            $stmt->execute([$evaluation_id, $question_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $pdo->prepare("
                    UPDATE evaluation_responses SET
                        rating = ?,
                        comments = ?
                    WHERE evaluation_id = ? AND question_id = ?
                ");
                $stmt->execute([
                    $rating,
                    $comments[$question_id] ?? null,
                    $evaluation_id,
                    $question_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO evaluation_responses (
                        evaluation_id, question_id, rating, comments
                    ) VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $evaluation_id,
                    $question_id,
                    $rating,
                    $comments[$question_id] ?? null
                ]);
            }
        }
        
        // Update evaluation with draft notes
        $stmt = $pdo->prepare("
            UPDATE panel_evaluations SET
                strengths = ?,
                weaknesses = ?,
                overall_comments = ?
            WHERE id = ?
        ");
        $stmt->execute([$strengths, $weaknesses, $overall_comments, $evaluation_id]);
        
        $pdo->commit();
        
        $message = "✅ Draft saved successfully!";
        
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

// Get interviews assigned to current panel
$query = "
    SELECT 
        i.*,
        ja.id as applicant_id,
        ja.first_name,
        ja.last_name,
        ja.email,
        ja.photo_path,
        ja.application_number,
        ja.status as applicant_status,
        ja.final_status,
        jp.title as position_title,
        jp.job_code,
        jp.department,
        jp.slots_available,
        jp.slots_filled,
        pe.id as evaluation_id,
        pe.status as evaluation_status,
        pe.total_score,
        pe.max_score,
        pe.final_percentage,
        pe.recommendation,
        pe.strengths,
        pe.weaknesses,
        pe.overall_comments,
        u.full_name as interviewer_name
    FROM interviews i
    JOIN job_applications ja ON i.applicant_id = ja.id
    LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
    LEFT JOIN panel_evaluations pe ON i.id = pe.interview_id AND pe.panel_id = ?
    LEFT JOIN users u ON i.interviewer_id = u.id
    WHERE (i.interviewer_id = ? OR i.interviewer_id IS NULL)
";

$params = [$_SESSION['user_id'], $_SESSION['user_id']];

// Apply filters
if (!empty($status_filter) && $status_filter !== 'all') {
    if ($status_filter === 'pending') {
        $query .= " AND i.status = 'scheduled' AND pe.id IS NULL";
    } elseif ($status_filter === 'ongoing') {
        $query .= " AND pe.status = 'ongoing'";
    } elseif ($status_filter === 'completed') {
        $query .= " AND pe.status = 'submitted'";
    }
}

if (!empty($date_filter)) {
    $query .= " AND DATE(i.interview_date) = ?";
    $params[] = $date_filter;
}

if (!empty($search_filter)) {
    $query .= " AND (ja.first_name LIKE ? OR ja.last_name LIKE ? OR jp.title LIKE ? OR ja.application_number LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY i.interview_date DESC, i.interview_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$my_interviews = $stmt->fetchAll();

// Get statistics
$stats = [];

// Pending evaluations
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM interviews i
    LEFT JOIN panel_evaluations pe ON i.id = pe.interview_id AND pe.panel_id = ?
    WHERE i.status = 'scheduled' AND pe.id IS NULL
    AND (i.interviewer_id = ? OR i.interviewer_id IS NULL)
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$stats['pending'] = $stmt->fetchColumn();

// Ongoing evaluations
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM panel_evaluations 
    WHERE panel_id = ? AND status = 'ongoing'
");
$stmt->execute([$_SESSION['user_id']]);
$stats['ongoing'] = $stmt->fetchColumn();

// Completed evaluations
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM panel_evaluations 
    WHERE panel_id = ? AND status = 'submitted'
");
$stmt->execute([$_SESSION['user_id']]);
$stats['completed'] = $stmt->fetchColumn();

// Average rating
$stmt = $pdo->prepare("
    SELECT AVG(final_percentage) FROM panel_evaluations 
    WHERE panel_id = ? AND status = 'submitted'
");
$stmt->execute([$_SESSION['user_id']]);
$stats['avg_rating'] = round($stmt->fetchColumn() ?: 0, 1);
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

.interview-card.pending {
    border-left: 4px solid var(--danger);
    background: linear-gradient(to right, #fee9e7, white);
}

.interview-card.ongoing {
    border-left: 4px solid var(--info);
    background: linear-gradient(to right, #e8f4fd, white);
}

.interview-card.completed {
    border-left: 4px solid var(--success);
    background: linear-gradient(to right, #e8f8f0, white);
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

.status-pending {
    background: var(--danger)20;
    color: var(--danger);
}

.status-ongoing {
    background: var(--info)20;
    color: var(--info);
}

.status-completed {
    background: var(--success)20;
    color: var(--success);
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

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--light-gray);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 4px;
    transition: width 0.3s ease;
}

.score-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
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

.card-footer {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

/* Evaluation Form - NEW LAYOUT */
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

/* TWO COLUMN LAYOUT */
.evaluation-content {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 25px;
    margin-top: 20px;
}

/* Left Column - Questions */
.questions-column {
    background: var(--light-gray);
    border-radius: 20px;
    padding: 20px;
}

.category-section {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border);
}

.category-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.category-header .weight {
    background: var(--primary-transparent);
    padding: 5px 15px;
    border-radius: 30px;
    color: var(--primary);
    font-weight: 600;
    font-size: 14px;
}

.question-item {
    background: var(--light-gray);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid var(--border);
    transition: all 0.3s;
}

.question-item:hover {
    border-color: var(--primary);
    box-shadow: 0 5px 15px var(--primary-transparent);
}

.question-text {
    font-size: 14px;
    font-weight: 500;
    color: var(--dark);
    margin-bottom: 12px;
}

.rating-scale {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
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
    padding: 8px 5px;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 12px;
    border: 1px solid var(--border);
}

.rating-option input[type="radio"]:checked + label {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: scale(1.02);
}

.rating-option label:hover {
    background: var(--primary-transparent);
    border-color: var(--primary);
}

.comment-box {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 12px;
    resize: vertical;
    transition: all 0.3s;
    background: white;
}

.comment-box:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

/* Right Column - Score Summary & Recommendation */
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
    text-transform: uppercase;
    letter-spacing: 1px;
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

.score-divider {
    height: 2px;
    background: rgba(255,255,255,0.2);
    margin: 15px 0;
}

.auto-recommendation {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    text-align: center;
}

.rec-badge {
    display: inline-block;
    padding: 12px 30px;
    border-radius: 50px;
    font-size: 20px;
    font-weight: 700;
    margin-top: 10px;
    transition: all 0.3s ease;
}

.rec-hire {
    background: var(--success)20;
    color: var(--success);
    border: 2px solid var(--success);
}

.rec-final {
    background: var(--info)20;
    color: var(--info);
    border: 2px solid var(--info);
}

.rec-hold {
    background: var(--warning)20;
    color: var(--warning);
    border: 2px solid var(--warning);
}

.rec-reject {
    background: var(--danger)20;
    color: var(--danger);
    border: 2px solid var(--danger);
}

.rec-scale {
    margin-top: 15px;
    font-size: 12px;
    color: var(--gray);
}

.rec-scale-item {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px dashed var(--border);
}

.rec-scale-item:last-child {
    border-bottom: none;
}

.strength-weakness-box {
    background: var(--light-gray);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
}

.strength-weakness-box h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.strength-weakness-box textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 13px;
    resize: vertical;
    margin-bottom: 15px;
    background: white;
}

.strength-weakness-box textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid var(--border);
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
    display: flex;
    align-items: center;
    gap: 8px;
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
    transition: all 0.3s;
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
    
    .interviews-grid {
        grid-template-columns: 1fr;
    }
    
    .applicant-header {
        flex-direction: column;
        text-align: center;
    }
    
    .rating-scale {
        flex-direction: column;
    }
}
</style>

<!-- ==================== HTML CONTENT ==================== -->

<!-- Messages -->
<?php if (isset($_GET['success'])): ?>
<div class="alert-success">
    <i class="fas fa-check-circle"></i> Evaluation submitted successfully!
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

<!-- Check if we're in evaluation mode -->
<?php if (isset($_GET['action']) && $_GET['action'] === 'evaluate' && isset($_GET['id'])): 
    $evaluation_id = $_GET['id'];
    
    // Get evaluation details
    $stmt = $pdo->prepare("
        SELECT pe.*, 
               i.id as interview_id, i.interview_date, i.interview_time, i.interview_round,
               ja.id as applicant_id, ja.first_name, ja.last_name, ja.email, ja.photo_path, ja.application_number,
               jp.id as job_posting_id, jp.title as position_title, jp.job_code, jp.department,
               i.interview_type,
               u.full_name as panel_name
        FROM panel_evaluations pe
        JOIN interviews i ON pe.interview_id = i.id
        JOIN job_applications ja ON pe.applicant_id = ja.id
        LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
        LEFT JOIN users u ON pe.panel_id = u.id
        WHERE pe.id = ? AND pe.panel_id = ?
    ");
    $stmt->execute([$evaluation_id, $_SESSION['user_id']]);
    $evaluation = $stmt->fetch();
    
    if (!$evaluation) {
        echo '<div class="alert-danger"><i class="fas fa-exclamation-circle"></i> Evaluation not found or you do not have permission to access it.</div>';
    } else {
        // Get template questions
        $stmt = $pdo->prepare("
            SELECT 
                ec.id as category_id,
                ec.category_name,
                ec.weight,
                eq.id as question_id,
                eq.question,
                eq.question_type,
                er.rating,
                er.comments
            FROM evaluation_templates et
            JOIN evaluation_categories ec ON et.id = ec.template_id
            JOIN evaluation_questions eq ON ec.id = eq.category_id
            LEFT JOIN evaluation_responses er ON eq.id = er.question_id AND er.evaluation_id = ?
            WHERE et.position_id = ? AND et.is_active = 1
            ORDER BY ec.sort_order, eq.sort_order
        ");
        $stmt->execute([$evaluation_id, $evaluation['job_posting_id']]);
        $questions = $stmt->fetchAll();
        
        // Group by category
        $categories = [];
        foreach ($questions as $q) {
            $cat_id = $q['category_id'];
            if (!isset($categories[$cat_id])) {
                $categories[$cat_id] = [
                    'name' => $q['category_name'],
                    'weight' => $q['weight'],
                    'questions' => []
                ];
            }
            $categories[$cat_id]['questions'][] = $q;
        }
        
        // Calculate current scores
        $total_score = 0;
        $max_score = count($questions) * 5;
        $rated_count = 0;
        
        foreach ($questions as $q) {
            if ($q['rating'] !== null) {
                $total_score += intval($q['rating']);
                $rated_count++;
            }
        }
        
        $completion_percentage = $rated_count > 0 ? round(($rated_count / count($questions)) * 100) : 0;
        $final_percentage = $total_score > 0 ? round(($total_score / $max_score) * 100, 1) : 0;
        
        // Determine automatic recommendation
        $auto_recommendation = 'reject';
        $rec_class = 'rec-reject';
        $rec_text = 'REJECT';
        
        if ($final_percentage >= 100) {
            $auto_recommendation = 'hire';
            $rec_class = 'rec-hire';
            $rec_text = 'HIRE';
        } elseif ($final_percentage >= 85) {
            $auto_recommendation = 'final_interview';
            $rec_class = 'rec-final';
            $rec_text = 'FINAL INTERVIEW';
        } elseif ($final_percentage >= 75) {
            $auto_recommendation = 'hold';
            $rec_class = 'rec-hold';
            $rec_text = 'HOLD';
        } else {
            $auto_recommendation = 'reject';
            $rec_class = 'rec-reject';
            $rec_text = 'REJECT';
        }
?>

<!-- Evaluation Form -->
<div style="margin-bottom: 20px;">
    <a href="?page=recruitment&subpage=interview-panel" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Panel Dashboard
    </a>
</div>

<div class="evaluation-container">
    <form method="POST" id="evaluationForm" onsubmit="return validateForm()">
        <input type="hidden" name="evaluation_id" value="<?php echo $evaluation_id; ?>">
        
        <!-- Applicant Header -->
        <div class="applicant-header">
            <?php 
            $photoPath = getApplicantPhoto($evaluation);
            $fullName = $evaluation['first_name'] . ' ' . $evaluation['last_name'];
            $initials = strtoupper(substr($evaluation['first_name'] ?? '', 0, 1) . substr($evaluation['last_name'] ?? '', 0, 1)) ?: '?';
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
                <p><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($evaluation['application_number']); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($evaluation['email']); ?></p>
            </div>
            
            <div class="applicant-header-badge">
                <div class="label">Position</div>
                <div class="value"><?php echo htmlspecialchars($evaluation['position_title'] ?: 'N/A'); ?></div>
                <div style="font-size: 12px; margin-top: 5px;"><?php echo htmlspecialchars($evaluation['job_code']); ?></div>
            </div>
            
            <div class="applicant-header-badge">
                <div class="label">Interview Round</div>
                <div class="value"><?php echo ucfirst($evaluation['interview_type']); ?></div>
                <div style="font-size: 12px; margin-top: 5px;"><?php echo date('M d, Y', strtotime($evaluation['interview_date'])); ?></div>
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
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%;"></div>
                    </div>
                </div>
                
                <!-- Evaluation Categories -->
                <?php foreach ($categories as $cat_id => $category): ?>
                <div class="category-section">
                    <div class="category-header">
                        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                        <span class="weight">Weight: <?php echo $category['weight']; ?>%</span>
                    </div>
                    
                    <?php foreach ($category['questions'] as $index => $question): ?>
                    <div class="question-item">
                        <div class="question-text">
                            <?php echo ($index + 1) . '. ' . htmlspecialchars($question['question']); ?>
                        </div>
                        
                        <div class="rating-scale">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                            <div class="rating-option">
                                <input type="radio" 
                                       name="rating[<?php echo $question['question_id']; ?>]" 
                                       id="rating_<?php echo $question['question_id']; ?>_<?php echo $i; ?>" 
                                       value="<?php echo $i; ?>"
                                       <?php echo ($question['rating'] == $i) ? 'checked' : ''; ?>
                                       onchange="updateScores()">
                                <label for="rating_<?php echo $question['question_id']; ?>_<?php echo $i; ?>">
                                    <?php 
                                    $labels = ['Poor', 'Below', 'Average', 'Good', 'Excel'];
                                    echo $labels[$i-1] . ' (' . $i . ')';
                                    ?>
                                </label>
                            </div>
                            <?php endfor; ?>
                        </div>
                        
                        <textarea name="comments[<?php echo $question['question_id']; ?>]" 
                                  class="comment-box" 
                                  placeholder="Add comments/notes for this question (optional)"><?php echo htmlspecialchars($question['comments'] ?? ''); ?></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- RIGHT COLUMN: Score Summary & Recommendation -->
            <div class="summary-column">
                <!-- Live Score Summary -->
                <div class="score-card">
                    <div class="score-item">
                        <div class="label">Total Score</div>
                        <div class="value" id="totalScore"><?php echo $total_score; ?></div>
                        <div class="unit">out of <?php echo $max_score; ?></div>
                    </div>
                    
                    <div class="score-divider"></div>
                    
                    <div class="score-item">
                        <div class="label">Percentage</div>
                        <div class="value" id="finalPercentage"><?php echo $final_percentage; ?>%</div>
                    </div>
                    
                    <div class="score-divider"></div>
                    
                    <div class="score-item">
                        <div class="label">Questions Rated</div>
                        <div class="value" id="ratedCount"><?php echo $rated_count; ?></div>
                        <div class="unit">of <?php echo count($questions); ?></div>
                    </div>
                </div>
                
                <!-- Automatic Recommendation -->
                <div class="auto-recommendation">
                    <h4 style="margin: 0 0 10px; color: var(--dark);">Automatic Recommendation</h4>
                    <div class="rec-badge <?php echo $rec_class; ?>" id="recommendationBadge">
                        <?php echo $rec_text; ?>
                    </div>
                    
                    <div class="rec-scale">
                        <div class="rec-scale-item">
                            <span>Hire</span>
                            <span style="color: var(--success); font-weight: 600;">100%</span>
                        </div>
                        <div class="rec-scale-item">
                            <span>Final Interview</span>
                            <span style="color: var(--info); font-weight: 600;">85% - 99%</span>
                        </div>
                        <div class="rec-scale-item">
                            <span>Hold</span>
                            <span style="color: var(--warning); font-weight: 600;">75% - 84%</span>
                        </div>
                        <div class="rec-scale-item">
                            <span>Reject</span>
                            <span style="color: var(--danger); font-weight: 600;">≤ 74%</span>
                        </div>
                    </div>
                </div>
                
                <!-- Strengths & Weaknesses -->
                <div class="strength-weakness-box">
                    <h4><i class="fas fa-check-circle" style="color: var(--success);"></i> Strengths</h4>
                    <textarea name="strengths" rows="3" placeholder="What are the candidate's key strengths?"><?php echo htmlspecialchars($evaluation['strengths'] ?? ''); ?></textarea>
                    
                    <h4 style="margin-top: 15px;"><i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Areas for Improvement</h4>
                    <textarea name="weaknesses" rows="3" placeholder="What areas need improvement?"><?php echo htmlspecialchars($evaluation['weaknesses'] ?? ''); ?></textarea>
                    
                    <h4 style="margin-top: 15px;"><i class="fas fa-comment" style="color: var(--primary);"></i> Overall Comments</h4>
                    <textarea name="overall_comments" rows="3" placeholder="Additional comments about the candidate..."><?php echo htmlspecialchars($evaluation['overall_comments'] ?? ''); ?></textarea>
                </div>
                
                <!-- Hidden recommendation field (automatically set) -->
                <input type="hidden" name="recommendation" id="autoRecommendation" value="<?php echo $auto_recommendation; ?>">
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" name="save_draft" class="btn btn-outline">
                <i class="fas fa-save"></i> Save Draft
            </button>
            <button type="submit" name="submit_evaluation" class="btn btn-success" onclick="return confirmSubmit()">
                <i class="fas fa-check-circle"></i> Submit Evaluation
            </button>
        </div>
    </form>
</div>

<script>
function updateScores() {
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
    document.getElementById('ratedCount').textContent = count;
    
    // Update automatic recommendation based on percentage
    updateRecommendation(percentage);
}

function updateRecommendation(percentage) {
    const badge = document.getElementById('recommendationBadge');
    const hiddenField = document.getElementById('autoRecommendation');
    
    let recClass = '';
    let recText = '';
    let recValue = '';
    
    if (percentage >= 100) {
        recClass = 'rec-hire';
        recText = 'HIRE';
        recValue = 'hire';
    } else if (percentage >= 85) {
        recClass = 'rec-final';
        recText = 'FINAL INTERVIEW';
        recValue = 'final_interview';
    } else if (percentage >= 75) {
        recClass = 'rec-hold';
        recText = 'HOLD';
        recValue = 'hold';
    } else {
        recClass = 'rec-reject';
        recText = 'REJECT';
        recValue = 'reject';
    }
    
    // Remove all existing classes and add new one
    badge.className = 'rec-badge ' + recClass;
    badge.textContent = recText;
    hiddenField.value = recValue;
}

function validateForm() {
    const radios = document.querySelectorAll('input[type="radio"]:checked');
    const ratingRadios = Array.from(radios).filter(r => r.name.startsWith('rating['));
    
    if (ratingRadios.length === 0) {
        alert('Please rate at least one question before submitting.');
        return false;
    }
    
    return true;
}

function confirmSubmit() {
    if (!validateForm()) return false;
    return confirm('Are you sure you want to submit this evaluation? You cannot edit it after submission.');
}
</script>

<?php 
    } // end evaluation exists check
else: 
?>

<!-- Page Header - NO WELCOME MESSAGE -->
<div class="page-header">
    <div class="page-title">
        <i class="fas fa-clipboard-check"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div>
        <!-- REMOVED: Welcome message completely -->
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Evaluations</span>
            <span class="stat-value"><?php echo $stats['pending']; ?></span>
            <div class="stat-small">
                <i class="fas fa-calendar" style="color: var(--warning);"></i> Ready to start
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-spinner"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">In Progress</span>
            <span class="stat-value"><?php echo $stats['ongoing']; ?></span>
            <div class="stat-small">
                <i class="fas fa-pencil-alt" style="color: var(--info);"></i> Draft mode
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
                <i class="fas fa-star" style="color: var(--success);"></i> Submitted
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Avg. Rating</span>
            <span class="stat-value"><?php echo $stats['avg_rating']; ?>%</span>
            <div class="stat-small">
                <i class="fas fa-trophy" style="color: var(--warning);"></i> Your average
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter My Interviews
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="recruitment">
        <input type="hidden" name="subpage" value="interview-panel">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All My Interviews</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Evaluation</option>
                    <option value="ongoing" <?php echo $status_filter == 'ongoing' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Date</label>
                <input type="date" name="date" value="<?php echo $date_filter; ?>">
            </div>
            
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, position, application #" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=recruitment&subpage=interview-panel" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- My Interviews List -->
<?php if (empty($my_interviews)): ?>
<div style="background: white; border-radius: 20px; padding: 60px; text-align: center; color: var(--gray);">
    <i class="fas fa-clipboard-list" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
    <h3 style="margin-bottom: 10px;">No Interviews Found</h3>
    <p>There are no interviews assigned to you at the moment.</p>
</div>
<?php else: ?>
<div class="interviews-grid">
    <?php foreach ($my_interviews as $interview): 
        $photoPath = getApplicantPhoto($interview);
        $firstName = $interview['first_name'] ?? '';
        $lastName = $interview['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
        
        // Determine status class
        $status_class = 'pending';
        $status_text = 'Pending';
        
        if ($interview['evaluation_status'] == 'ongoing') {
            $status_class = 'ongoing';
            $status_text = 'In Progress';
        } elseif ($interview['evaluation_status'] == 'submitted') {
            $status_class = 'completed';
            $status_text = 'Completed';
        }
        
        $is_today = $interview['interview_date'] == date('Y-m-d');
        $is_past = $interview['interview_date'] < date('Y-m-d');
    ?>
    <div class="interview-card <?php echo $is_today ? 'today' : ''; ?> <?php echo $status_class; ?>">
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
                    <p><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($interview['application_number']); ?></p>
                </div>
            </div>
            
            <span class="status-badge status-<?php echo $status_class; ?>">
                <i class="fas fa-<?php echo $status_class == 'pending' ? 'clock' : ($status_class == 'ongoing' ? 'spinner' : 'check'); ?>"></i>
                <?php echo $status_text; ?>
            </span>
        </div>
        
        <div class="card-body">
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Interview Date</div>
                    <div class="detail-value">
                        <?php echo date('F d, Y', strtotime($interview['interview_date'])); ?>
                        <?php if ($is_today): ?>
                        <span style="color: var(--warning); margin-left: 5px;">(Today)</span>
                        <?php elseif ($is_past): ?>
                        <span style="color: var(--danger); margin-left: 5px;">(Past)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Time</div>
                    <div class="detail-value"><?php echo date('h:i A', strtotime($interview['interview_time'])); ?></div>
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
            
            <?php if ($interview['final_percentage'] > 0): ?>
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Your Score</div>
                    <div class="detail-value">
                        <span class="score-badge <?php 
                            echo $interview['final_percentage'] >= 80 ? 'score-high' : 
                                ($interview['final_percentage'] >= 60 ? 'score-medium' : 'score-low'); 
                        ?>">
                            <?php echo $interview['final_percentage']; ?>%
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($interview['recommendation']): ?>
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="fas fa-thumbs-up"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Your Recommendation</div>
                    <div class="detail-value">
                        <?php 
                        $rec_colors = [
                            'hire' => 'success',
                            'final_interview' => 'info',
                            'hold' => 'warning',
                            'reject' => 'danger'
                        ];
                        $rec_labels = [
                            'hire' => 'Hire',
                            'final_interview' => 'Final Interview',
                            'hold' => 'Hold',
                            'reject' => 'Reject'
                        ];
                        $rec_color = $rec_colors[$interview['recommendation']] ?? 'secondary';
                        ?>
                        <span class="status-badge status-<?php echo $rec_color; ?>">
                            <?php echo $rec_labels[$interview['recommendation']] ?? $interview['recommendation']; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card-footer">
            <?php if ($interview['evaluation_status'] == 'ongoing'): ?>
                <a href="?page=recruitment&subpage=interview-panel&action=evaluate&id=<?php echo $interview['evaluation_id']; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-pencil-alt"></i> Continue Evaluation
                </a>
            <?php elseif ($interview['evaluation_status'] == 'submitted'): ?>
                <button class="btn btn-info btn-sm" onclick="viewEvaluation(<?php echo htmlspecialchars(json_encode($interview)); ?>)">
                    <i class="fas fa-eye"></i> View Results
                </button>
            <?php elseif (!$is_past): ?>
                <a href="?page=recruitment&subpage=interview-panel&action=start&id=<?php echo $interview['id']; ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-play"></i> Start Evaluation
                </a>
            <?php else: ?>
                <span class="btn btn-secondary btn-sm disabled">
                    <i class="fas fa-clock"></i> Past Interview
                </span>
            <?php endif; ?>
            
            <button class="btn btn-outline btn-sm" onclick="viewInterviewDetails(<?php echo htmlspecialchars(json_encode($interview)); ?>)">
                <i class="fas fa-info-circle"></i> Details
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- View Interview Details Modal -->
<div id="viewDetailsModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle" style="color: var(--info);"></i> Interview Details</h3>
            <span class="modal-close" onclick="closeDetailsModal()">&times;</span>
        </div>
        
        <div id="detailsContent">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<!-- View Evaluation Results Modal -->
<div id="viewResultsModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check" style="color: var(--success);"></i> Evaluation Results</h3>
            <span class="modal-close" onclick="closeResultsModal()">&times;</span>
        </div>
        
        <div id="resultsContent">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<script>
// View interview details
function viewInterviewDetails(interview) {
    const hasMeetingLink = interview.meeting_link && interview.meeting_link.trim() !== '';
    const interviewType = hasMeetingLink ? 'Online' : 'Face-to-Face';
    
    const html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                <i class="fas fa-user" style="color: white; font-size: 24px;"></i>
            </div>
            <h2 style="font-size: 20px; color: var(--dark); margin-bottom: 5px;">${interview.first_name} ${interview.last_name}</h2>
            <p style="color: var(--gray);">${interview.position_title || 'General Application'}</p>
        </div>
        
        <div style="background: var(--light-gray); border-radius: 16px; padding: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Application #</p>
                    <p style="font-weight: 500;">${interview.application_number}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Department</p>
                    <p style="font-weight: 500;">${interview.department ? interview.department.charAt(0).toUpperCase() + interview.department.slice(1) : 'N/A'}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Date</p>
                    <p style="font-weight: 500;">${new Date(interview.interview_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</p>
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
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Interviewer</p>
                    <p>${interview.interviewer_name || 'HR Team'}</p>
                </div>
            </div>
            
            ${hasMeetingLink ? `
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                <p style="font-size: 12px; font-weight: 600; color: var(--primary); margin-bottom: 5px;">Meeting Link</p>
                <p><a href="${interview.meeting_link}" target="_blank" style="color: var(--primary);">${interview.meeting_link}</a></p>
            </div>
            ` : interview.location ? `
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                <p style="font-size: 12px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">Location</p>
                <p>${interview.location}</p>
            </div>
            ` : ''}
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
            <button class="btn btn-outline" onclick="closeDetailsModal()">Close</button>
        </div>
    `;
    
    document.getElementById('detailsContent').innerHTML = html;
    document.getElementById('viewDetailsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// View evaluation results
function viewEvaluation(interview) {
    const recLabels = {
        'hire': { text: 'Hire', color: 'success' },
        'final_interview': { text: 'Final Interview', color: 'info' },
        'hold': { text: 'Hold', color: 'warning' },
        'reject': { text: 'Reject', color: 'danger' }
    };
    
    const rec = recLabels[interview.recommendation] || { text: 'Unknown', color: 'secondary' };
    
    const scoreClass = interview.final_percentage >= 80 ? 'score-high' : 
                      (interview.final_percentage >= 60 ? 'score-medium' : 'score-low');
    
    const html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <h2 style="font-size: 20px; color: var(--dark); margin-bottom: 5px;">${interview.first_name} ${interview.last_name}</h2>
            <p style="color: var(--gray);">${interview.position_title || 'General Application'}</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 20px; margin-bottom: 20px; color: white;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; text-align: center;">
                <div>
                    <div style="font-size: 28px; font-weight: 700;">${interview.final_percentage}%</div>
                    <div style="font-size: 12px; opacity: 0.8;">Overall Score</div>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700;">${interview.total_score || 0}</div>
                    <div style="font-size: 12px; opacity: 0.8;">Total Points</div>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700;">${interview.max_score || 0}</div>
                    <div style="font-size: 12px; opacity: 0.8;">Max Points</div>
                </div>
            </div>
        </div>
        
        <div style="background: var(--light-gray); border-radius: 16px; padding: 20px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <span style="font-weight: 600; color: var(--dark);">Final Recommendation</span>
                <span class="status-badge status-${rec.color}">${rec.text}</span>
            </div>
            
            ${interview.strengths ? `
            <div style="margin-bottom: 15px;">
                <p style="font-size: 12px; font-weight: 600; color: var(--success); margin-bottom: 5px;">Strengths</p>
                <p style="color: var(--dark);">${interview.strengths}</p>
            </div>
            ` : ''}
            
            ${interview.weaknesses ? `
            <div style="margin-bottom: 15px;">
                <p style="font-size: 12px; font-weight: 600; color: var(--warning); margin-bottom: 5px;">Areas for Improvement</p>
                <p style="color: var(--dark);">${interview.weaknesses}</p>
            </div>
            ` : ''}
            
            ${interview.overall_comments ? `
            <div>
                <p style="font-size: 12px; font-weight: 600; color: var(--primary); margin-bottom: 5px;">Overall Comments</p>
                <p style="color: var(--dark);">${interview.overall_comments}</p>
            </div>
            ` : ''}
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn btn-outline" onclick="closeResultsModal()">Close</button>
        </div>
    `;
    
    document.getElementById('resultsContent').innerHTML = html;
    document.getElementById('viewResultsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close modals
function closeDetailsModal() {
    document.getElementById('viewDetailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

function closeResultsModal() {
    document.getElementById('viewResultsModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetailsModal();
        closeResultsModal();
    }
});

// Close modals when clicking outside
window.onclick = function(event) {
    const detailsModal = document.getElementById('viewDetailsModal');
    const resultsModal = document.getElementById('viewResultsModal');
    
    if (event.target == detailsModal) closeDetailsModal();
    if (event.target == resultsModal) closeResultsModal();
}
</script>

<?php endif; // End evaluation mode check ?>

<?php
// End output buffering and flush
ob_end_flush();
?>