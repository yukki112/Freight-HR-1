<?php
// Start output buffering at the VERY FIRST LINE - NO SPACES OR CHARACTERS BEFORE THIS
ob_start();

// modules/recruitment/final-selection.php
$page_title = "Final Selection & Final Interview";

require_once 'config/mail_config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$job_filter    = isset($_GET['job_id']) ? $_GET['job_id'] : '';

/**
 * Simple log helper
 */
function simpleLog($pdo, $user_id, $action, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id, $action, $description,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {}
}

/**
 * Final round thresholds — SINGLE SOURCE OF TRUTH
 *   ≥ 95%  → hire
 *   < 95%  → reject
 */
function getFinalRecommendation($percentage) {
    $p = (float)$percentage;
    if ($p >= 95) {
        return ['hire', 'PASSED — FOR HIRING', 'status-hired'];
    }
    return ['reject', 'NOT PASSED', 'status-pending'];
}

function getApplicantPhoto($applicant) {
    if (!empty($applicant['photo_path']) && file_exists($applicant['photo_path'])) {
        return htmlspecialchars($applicant['photo_path']);
    }
    return null;
}

function calculateOverallScore($screening, $initial, $final) {
    return round(
        ((float)$screening * 0.3) +
        ((float)$initial   * 0.4) +
        ((float)$final     * 0.3),
        2
    );
}

/* ============================================================
   EMAIL: Final result
   ============================================================ */
function sendFinalResultEmail($applicant_email, $applicant_name, $result_data) {
    try {
        $mail = MailConfig::getInstance();
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->addAddress($applicant_email, $applicant_name);
        $mail->addBCC('hr@freightmanagement.com', 'HR Department');

        if ($result_data['result'] == 'hire') {
            $mail->Subject = "Congratulations! Job Offer - {$result_data['position']}";
        } else {
            $mail->Subject = "Update on Your Final Interview - {$result_data['position']}";
        }

        $body = buildFinalResultEmailHTML($applicant_name, $result_data);
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</div>'], ["\n", "\n\n", "\n"], $body));

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}

function buildFinalResultEmailHTML($applicant_name, $data) {
    $result = $data['result'];
    $position = $data['position'];
    $score = $data['score'] ?? 0;

    if ($result == 'hire') {
        $content = '
        <div style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); border-radius: 15px; padding: 30px; margin: 20px 0; text-align: center; color: white;">
            <h2 style="font-size: 28px; margin: 0 0 10px;">Congratulations!</h2>
            <p style="font-size: 18px; opacity: 0.9;">You have been selected for the position</p>
            <h3 style="font-size: 24px; margin: 15px 0; background: rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 50px; display: inline-block;">' . htmlspecialchars($position) . '</h3>
            <p style="margin-top: 20px;">Your final interview score: <strong>' . $score . '%</strong></p>
        </div>
        <div style="background: #f8fafd; border-radius: 15px; padding: 25px; margin: 20px 0;">
            <h4 style="color: #2c3e50; margin-bottom: 15px;">Next Steps:</h4>
            <ol style="color: #64748b; line-height: 1.8;">
                <li>Our HR team will contact you within 24 hours</li>
                <li>You will receive your employment contract via email</li>
                <li>We will schedule your orientation and onboarding</li>
            </ol>
        </div>';
    } else {
        $content = '
        <div style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); border-radius: 15px; padding: 30px; margin: 20px 0; text-align: center; color: white;">
            <h2 style="font-size: 28px; margin: 0 0 10px;">Thank You for Your Interest</h2>
            <p style="font-size: 18px; opacity: 0.9;">Update on your final interview</p>
        </div>
        <div style="background: #f8fafd; border-radius: 15px; padding: 25px; margin: 20px 0;">
            <p style="color: #64748b; line-height: 1.8;">Dear ' . htmlspecialchars($applicant_name) . ',</p>
            <p style="color: #64748b; line-height: 1.8;">
                Thank you for participating in the final interview for the <strong>' . htmlspecialchars($position) . '</strong> position.
                After careful consideration, we will not be moving forward with your application.
            </p>
            <p style="color: #64748b; line-height: 1.8;">
                Your final interview score: <strong>' . $score . '%</strong> (Passing score: 95%)
            </p>
        </div>';
    }

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Inter, Arial, sans-serif; background: #f5f7fa; padding: 20px;">' . $content . '</body></html>';
}

/* ============================================================
   HANDLE: START FINAL EVALUATION
   ============================================================ */
if (isset($_GET['action']) && $_GET['action'] === 'start_final' && isset($_GET['id'])) {
    try {
        $final_interview_id = (int)$_GET['id'];
        if (!$final_interview_id) throw new Exception("Invalid final interview ID");

        $stmt = $pdo->prepare("
            SELECT id FROM interviews 
            WHERE id = ? AND interview_round = 'final' AND status != 'cancelled'
        ");
        $stmt->execute([$final_interview_id]);
        if (!$stmt->fetch()) throw new Exception("Final interview not found.");

        simpleLog($pdo, $_SESSION['user_id'], 'start_final_interview',
            "Opened final evaluation for interview #$final_interview_id");

        ob_clean();
        header("Location: ?page=recruitment&subpage=final-selection&action=evaluate_final&id=" . $final_interview_id);
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/* ============================================================
   HANDLE: SUBMIT FINAL EVALUATION
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_final_evaluation'])) {
    try {
        $pdo->beginTransaction();

        $final_interview_id = (int)$_POST['final_interview_id'];
        $ratings   = $_POST['rating']   ?? [];
        $comments  = $_POST['comments'] ?? [];
        $strengths = $_POST['strengths'] ?? '';
        $weaknesses = $_POST['weaknesses'] ?? '';
        $overall_comments = $_POST['overall_comments'] ?? '';

        if (empty($ratings)) throw new Exception("Please rate at least one question");

        // Calculate score
        $total_score = 0;
        $max_score = count($ratings) * 5;
        foreach ($ratings as $question_id => $rating) {
            $total_score += intval($rating);

            $stmt = $pdo->prepare("
                SELECT id FROM final_evaluation_responses 
                WHERE final_interview_id = ? AND question_id = ?
            ");
            $stmt->execute([$final_interview_id, $question_id]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("
                    UPDATE final_evaluation_responses 
                    SET rating = ?, comments = ?
                    WHERE final_interview_id = ? AND question_id = ?
                ");
                $stmt->execute([$rating, $comments[$question_id] ?? null, $final_interview_id, $question_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO final_evaluation_responses (final_interview_id, question_id, rating, comments)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$final_interview_id, $question_id, $rating, $comments[$question_id] ?? null]);
            }
        }

        $final_percentage = ($total_score / $max_score) * 100;
        list($recommendation, $rec_label, $rec_class) = getFinalRecommendation($final_percentage);
        $rating_10 = max(1, min(10, (int)round($final_percentage / 10)));

        // Fetch context
        $stmt = $pdo->prepare("
            SELECT i.*, ja.first_name, ja.last_name, ja.email, ja.id as applicant_id,
                   jp.title as position_title
            FROM interviews i
            JOIN job_applications ja ON i.applicant_id = ja.id
            LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
            WHERE i.id = ?
        ");
        $stmt->execute([$final_interview_id]);
        $interview = $stmt->fetch();

        if (!$interview) throw new Exception("Final interview not found.");

        // Update interviews row (this is where final round lives)
        $stmt = $pdo->prepare("
            UPDATE interviews SET
                feedback = ?,
                rating = ?,
                final_score = ?,
                final_recommendation = ?,
                status = 'completed',
                auto_processed = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $overall_comments,
            $rating_10,
            $final_percentage,
            $recommendation,
            $final_interview_id
        ]);

        // Update applicant: score + final status
        $final_status_value = ($recommendation == 'hire') ? 'final_interview' : 'rejected';
        $stmt = $pdo->prepare("
            UPDATE job_applications 
            SET final_interview_score = ?, final_status = ?
            WHERE id = ?
        ");
        $stmt->execute([$final_percentage, $final_status_value, $interview['applicant_id']]);

        // Append note
        $note = "\n[" . date('Y-m-d H:i') . "] FINAL interview evaluated. Score: " .
                number_format($final_percentage, 1) . "% — " . $rec_label . ".";
        $pdo->prepare("UPDATE job_applications SET notes = CONCAT(IFNULL(notes, ''), ?) WHERE id = ?")
            ->execute([$note, $interview['applicant_id']]);

        // Send email
        $result_data = [
            'result' => $recommendation,
            'position' => $interview['position_title'] ?: 'the position',
            'score' => round($final_percentage, 1)
        ];
        $email_result = sendFinalResultEmail(
            $interview['email'],
            $interview['first_name'] . ' ' . $interview['last_name'],
            $result_data
        );

        // Log comm
        $stmt = $pdo->prepare("
            INSERT INTO communication_log (applicant_id, communication_type, subject, message, sent_by, status)
            VALUES (?, 'email', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $interview['applicant_id'],
            "Final Interview Result: {$interview['position_title']}",
            "Final decision: " . ucfirst($recommendation) . " (Score: " . number_format($final_percentage, 1) . "%)",
            $_SESSION['user_id'],
            $email_result['success'] ? 'sent' : 'failed'
        ]);

        $pdo->commit();

        simpleLog($pdo, $_SESSION['user_id'], 'complete_final_interview',
            "Completed final interview #$final_interview_id");

        ob_clean();
        header("Location: ?page=recruitment&subpage=final-selection&success=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

/* ============================================================
   HANDLE: APPROVE SELECTION (salary + start date)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_selection'])) {
    try {
        $pdo->beginTransaction();

        $applicant_id = (int)$_POST['applicant_id'];
        $approved_salary = $_POST['approved_salary'];
        $start_date = $_POST['start_date'];
        $remarks = $_POST['remarks'] ?? '';

        $stmt = $pdo->prepare("
            SELECT ja.*, jp.title as position_title, jp.department, jp.id as job_posting_id
            FROM job_applications ja
            LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
            WHERE ja.id = ?
        ");
        $stmt->execute([$applicant_id]);
        $applicant = $stmt->fetch();
        if (!$applicant) throw new Exception("Applicant not found");

        // Mark as hired
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

        // Update or insert new_hire
        $stmt = $pdo->prepare("SELECT id FROM new_hires WHERE applicant_id = ?");
        $stmt->execute([$applicant_id]);
        $existing_hire = $stmt->fetch();

        if ($existing_hire) {
            $stmt = $pdo->prepare("
                UPDATE new_hires SET
                    job_posting_id = ?, hire_date = CURDATE(), start_date = ?,
                    position = ?, department = ?, status = 'onboarding', updated_at = NOW()
                WHERE applicant_id = ?
            ");
            $stmt->execute([
                $applicant['job_posting_id'],
                $start_date,
                $applicant['position_title'],
                $applicant['department'] ?? 'operations',
                $applicant_id
            ]);
        } else {
            $employee_id = function_exists('generateEmployeeID')
                ? generateEmployeeID($pdo, $applicant['department'] ?? 'operations')
                : 'EMP-' . date('Y') . '-' . rand(1000, 9999);

            $stmt = $pdo->prepare("
                INSERT INTO new_hires (
                    applicant_id, employee_id, job_posting_id, hire_date, start_date,
                    position, department, status, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'onboarding', ?, NOW(), NOW())
            ");
            $stmt->execute([
                $applicant_id,
                $employee_id,
                $applicant['job_posting_id'],
                $start_date,
                $applicant['position_title'],
                $applicant['department'] ?? 'operations',
                $_SESSION['user_id']
            ]);
        }

        // Update job posting slots
        $stmt = $pdo->prepare("
            UPDATE job_postings 
            SET slots_filled = slots_filled + 1, slots_filled_auto = slots_filled_auto + 1
            WHERE id = ?
        ");
        $stmt->execute([$applicant['job_posting_id']]);

        // Send hire email
        $result_data = [
            'result' => 'hire',
            'position' => $applicant['position_title'] ?: 'the position',
            'score' => $applicant['final_interview_score'] ?: 0
        ];
        sendFinalResultEmail($applicant['email'], $applicant['first_name'] . ' ' . $applicant['last_name'], $result_data);

        $pdo->commit();

        simpleLog($pdo, $_SESSION['user_id'], 'final_selection',
            "Selected applicant #$applicant_id for hiring");

        ob_clean();
        header("Location: ?page=recruitment&subpage=final-selection&success=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

/* ============================================================
   FETCH CANDIDATES (uses interviews for final round)
   ============================================================ */
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
        fi.final_recommendation as final_recommendation,
        fi.status as final_status,
        fi.feedback as final_feedback,
        fi.rating as final_rating,
        fi.interview_date as final_interview_date,
        fi.interview_time as final_interview_time,
        u.full_name as selected_by_name
    FROM job_applications ja
    LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
    LEFT JOIN screening_evaluations se ON ja.id = se.applicant_id
    LEFT JOIN panel_evaluations pe ON ja.id = pe.applicant_id AND pe.status = 'submitted'
    LEFT JOIN interviews fi ON fi.applicant_id = ja.id AND fi.interview_round = 'final'
    LEFT JOIN users u ON ja.selected_by = u.id
    WHERE 
        (fi.id IS NOT NULL OR ja.status = 'hired')
";

$params = [];

if (!empty($status_filter) && $status_filter !== 'all') {
    if ($status_filter === 'pending') {
        $query .= " AND fi.status IN ('scheduled','ongoing') AND ja.status != 'hired'";
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
    $term = "%$search_filter%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

$query .= " ORDER BY (ja.status = 'hired') ASC, (fi.status = 'completed') DESC, ja.updated_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// Job postings for filter
$stmt = $pdo->query("SELECT id, job_code, title FROM job_postings WHERE status = 'published' ORDER BY title");
$job_postings = $stmt->fetchAll();

/* ============================================================
   STATS
   ============================================================ */
$stats = [];
$stats['pending_final'] = $pdo->query("
    SELECT COUNT(*) FROM interviews 
    WHERE interview_round = 'final' AND status IN ('scheduled','ongoing')
")->fetchColumn();

$stats['completed_final'] = $pdo->query("
    SELECT COUNT(*) FROM interviews 
    WHERE interview_round = 'final' AND status = 'completed'
")->fetchColumn();

$stats['selected'] = $pdo->query("
    SELECT COUNT(*) FROM job_applications WHERE status = 'hired'
")->fetchColumn();

$stats['available_slots'] = $pdo->query("
    SELECT SUM(slots_available - slots_filled) FROM job_postings WHERE status = 'published'
")->fetchColumn() ?: 0;
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

* { box-sizing: border-box; }

.page-header {
    background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 15px;
}
.page-title { display: flex; align-items: center; gap: 15px; }
.page-title h1 { font-size: 24px; font-weight: 600; color: var(--dark); margin: 0; }
.page-title i {
    font-size: 28px; color: var(--primary); background: var(--primary-transparent);
    padding: 12px; border-radius: 15px;
}

.stats-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px; margin-bottom: 25px;
}
.stat-card {
    background: white; border-radius: 20px; padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex; align-items: center; gap: 15px;
    border: 1px solid var(--border);
}
.stat-icon {
    width: 50px; height: 50px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 15px; display: flex; align-items: center; justify-content: center;
    font-size: 24px; color: white; flex-shrink: 0;
}
.stat-content { flex: 1; }
.stat-label { display: block; font-size: 13px; color: var(--gray); margin-bottom: 5px; font-weight: 500; }
.stat-value { display: block; font-size: 28px; font-weight: 700; color: var(--dark); line-height: 1.2; }
.stat-small { font-size: 12px; color: var(--gray); margin-top: 5px; display: flex; align-items: center; gap: 5px; }

.filter-section {
    background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}
.filter-title { font-size: 16px; font-weight: 600; color: var(--dark); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
.filter-title i { color: var(--primary); }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.filter-item { display: flex; flex-direction: column; gap: 5px; }
.filter-item label { font-size: 12px; font-weight: 600; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px; }
.filter-item input, .filter-item select {
    padding: 12px; border: 1px solid var(--border); border-radius: 12px;
    font-size: 14px; background: white;
}
.filter-item input:focus, .filter-item select:focus {
    outline: none; border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}
.filter-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

.btn {
    padding: 12px 24px; border-radius: 12px; font-size: 14px; font-weight: 500;
    cursor: pointer;
    display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
    border: 1px solid transparent;
}
.btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; }
.btn-primary:hover { box-shadow: 0 10px 20px var(--primary-transparent-2); }
.btn-success { background: var(--success); color: white; }
.btn-success:hover { background: #219a52; }
.btn-warning { background: var(--warning); color: white; }
.btn-warning:hover { background: #e67e22; }
.btn-info { background: var(--info); color: white; }
.btn-info:hover { background: #2980b9; }
.btn-outline { background: transparent; border: 1px solid var(--primary); color: var(--primary); }
.btn-outline:hover { background: var(--primary); color: white; }
.btn-sm { padding: 8px 16px; font-size: 13px; }
.btn-icon { padding: 10px; width: 42px; height: 42px; justify-content: center; border-radius: 10px; }
.btn:disabled { opacity: 0.35; cursor: not-allowed; }

.section-block {
    background: white; border-radius: 24px; padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    margin-bottom: 30px; border: 1px solid var(--border);
}
.section-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--border);
    flex-wrap: wrap; gap: 12px;
}
.section-header h2 {
    font-size: 18px; font-weight: 600; color: var(--dark);
    margin: 0; display: flex; align-items: center; gap: 10px;
}
.section-header h2 i { color: var(--primary); }
.section-header .badge-count {
    background: var(--primary-transparent); color: var(--primary);
    padding: 3px 10px; border-radius: 20px;
    font-size: 12px; font-weight: 600;
}

.cards-page { display: none; }
.cards-page.active { display: grid; }
.cards-page {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}
@media (max-width: 1100px) { .cards-page { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 720px)  { .cards-page { grid-template-columns: 1fr; } }

.candidate-card {
    background: white; border-radius: 20px; padding: 20px;
    border: 1px solid var(--border);
    display: flex; flex-direction: column;
}
.candidate-card.hired { border-left: 4px solid var(--success); background: linear-gradient(to right, #e8fff1, white); }
.candidate-card.pending { border-left: 4px solid var(--warning); background: linear-gradient(to right, #fff9e6, white); }
.candidate-card.failed { border-left: 4px solid var(--danger); background: linear-gradient(to right, #fee9e7, white); }
.candidate-card.ready { border-left: 4px solid var(--info); background: linear-gradient(to right, #e8f4fd, white); }

.card-header {
    display: flex; justify-content: space-between; align-items: start;
    gap: 10px; margin-bottom: 15px;
}
.applicant-info { display: flex; align-items: center; gap: 10px; min-width: 0; }
.applicant-photo {
    width: 48px; height: 48px; border-radius: 12px; object-fit: cover;
    border: 2px solid white;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 600; font-size: 16px; flex-shrink: 0;
}
.applicant-details { min-width: 0; }
.applicant-details h3 {
    font-size: 15px; font-weight: 600; color: var(--dark); margin: 0 0 3px 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.applicant-details p {
    font-size: 11px; color: var(--gray); margin: 2px 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.applicant-details i { width: 12px; color: var(--primary); }

.status-badge {
    padding: 5px 10px; border-radius: 30px; font-size: 10px; font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
}
.status-pending { background: rgba(243,156,18,0.15); color: var(--warning); }
.status-info { background: rgba(52,152,219,0.15); color: var(--info); }
.status-hired { background: rgba(39,174,96,0.15); color: var(--success); }
.status-danger { background: rgba(231,76,60,0.15); color: var(--danger); }

.card-body { margin: 12px 0; flex: 1; }
.detail-item {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 10px; background: var(--light-gray); border-radius: 10px;
    margin-bottom: 6px;
}
.detail-item:last-child { margin-bottom: 0; }
.detail-icon {
    width: 30px; height: 30px; background: white;
    border-radius: 8px; display: flex; align-items: center; justify-content: center;
    color: var(--primary); font-size: 13px; flex-shrink: 0;
}
.detail-content { flex: 1; min-width: 0; }
.detail-label { font-size: 9px; color: var(--gray); text-transform: uppercase; letter-spacing: 0.4px; font-weight: 600; }
.detail-value {
    font-size: 12px; font-weight: 600; color: var(--dark); margin-top: 1px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.score-badge {
    display: inline-block; padding: 4px 10px; border-radius: 20px;
    font-size: 12px; font-weight: 600;
}
.score-high { background: rgba(39,174,96,0.15); color: var(--success); }
.score-medium { background: rgba(243,156,18,0.15); color: var(--warning); }
.score-low { background: rgba(231,76,60,0.15); color: var(--danger); }

.card-footer {
    display: flex; gap: 4px; justify-content: flex-end;
    margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);
    flex-wrap: wrap;
}
.card-footer .btn { padding: 6px 10px; font-size: 11px; gap: 4px; }
.card-footer .btn i { font-size: 11px; }

.pagination-bar {
    display: flex; justify-content: center; align-items: center;
    gap: 15px; margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}
.pagination-info {
    font-size: 13px; color: var(--gray); font-weight: 500;
    min-width: 100px; text-align: center;
}

/* ============ EVALUATION FORM ============ */
.evaluation-container {
    background: white; border-radius: 25px; padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}
.applicant-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 20px; padding: 25px; margin-bottom: 30px;
    color: white; display: flex; align-items: center; gap: 25px; flex-wrap: wrap;
}
.applicant-header-photo {
    width: 80px; height: 80px; border-radius: 20px; object-fit: cover;
    border: 3px solid white; background: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; font-weight: 600; color: var(--primary);
}
.applicant-header-info { flex: 1; }
.applicant-header-info h2 { font-size: 26px; font-weight: 600; margin: 0 0 5px; }
.applicant-header-info p { margin: 3px 0; opacity: 0.9; font-size: 14px; }
.applicant-header-badge {
    background: rgba(255,255,255,0.2); padding: 12px 24px;
    border-radius: 15px; text-align: center;
}
.applicant-header-badge .label { font-size: 12px; opacity: 0.8; margin-bottom: 5px; }
.applicant-header-badge .value { font-size: 20px; font-weight: 700; }

.evaluation-content {
    display: grid; grid-template-columns: 1fr 350px;
    gap: 25px; margin-top: 20px;
}
.questions-column { background: var(--light-gray); border-radius: 20px; padding: 20px; }
.question-item {
    background: white; border-radius: 15px; padding: 20px;
    margin-bottom: 15px; border: 1px solid var(--border);
}
.question-text { font-size: 15px; font-weight: 500; color: var(--dark); margin-bottom: 15px; }
.rating-scale { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
.rating-option { flex: 1; min-width: 70px; text-align: center; }
.rating-option input[type="radio"] { display: none; }
.rating-option label {
    display: block; padding: 10px; background: var(--light-gray);
    border-radius: 10px; cursor: pointer; font-size: 13px;
    border: 1px solid var(--border);
}
.rating-option input[type="radio"]:checked + label {
    background: var(--primary); color: white; border-color: var(--primary);
}
.comment-box {
    width: 100%; padding: 12px; border: 1px solid var(--border);
    border-radius: 12px; font-size: 13px; resize: vertical;
}
.comment-box:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-transparent); }

.summary-column {
    background: white; border-radius: 20px; padding: 20px;
    border: 1px solid var(--border);
}
.score-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px; padding: 20px; margin-bottom: 25px; color: white;
}
.score-item { text-align: center; margin-bottom: 15px; }
.score-item .label { font-size: 12px; opacity: 0.9; margin-bottom: 5px; }
.score-item .value { font-size: 42px; font-weight: 700; line-height: 1.2; }
.score-item .unit { font-size: 14px; opacity: 0.8; }
.passing-info {
    background: var(--light-gray); border-radius: 15px;
    padding: 20px; margin-bottom: 25px; text-align: center;
}
.passing-badge {
    display: inline-block; padding: 12px 30px; border-radius: 50px;
    font-size: 18px; font-weight: 700; margin-top: 10px;
}
.passing-hire { background: rgba(39,174,96,0.15); color: var(--success); border: 2px solid var(--success); }
.passing-reject { background: rgba(231,76,60,0.15); color: var(--danger); border: 2px solid var(--danger); }

/* ============ MODAL ============ */
.modal {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5);
    z-index: 9999; align-items: center; justify-content: center;
}
.modal.active { display: flex; }
.modal-content {
    background: white; border-radius: 30px; padding: 30px;
    max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;
    box-shadow: 0 30px 60px rgba(0,0,0,0.3);
}
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--border);
}
.modal-header h3 {
    font-size: 20px; font-weight: 600; color: var(--dark);
    margin: 0; display: flex; align-items: center; gap: 8px;
}
.modal-close {
    font-size: 28px; cursor: pointer; color: var(--gray);
    background: none; border: none;
}
.modal-close:hover { color: var(--danger); }

.form-group { margin-bottom: 15px; }
.form-group label {
    display: block; font-size: 13px; font-weight: 600;
    color: var(--dark); margin-bottom: 5px;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 12px; border: 1px solid var(--border);
    border-radius: 12px; font-size: 14px;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none; border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}
.modal-footer {
    display: flex; gap: 10px; justify-content: flex-end;
    margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border);
}

/* ============ INLINE ALERTS (NO ANIMATION) ============ */
.inline-alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 500;
}
.inline-alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.inline-alert.danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.inline-alert.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

@media (max-width: 1024px) {
    .evaluation-content { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .filter-grid { grid-template-columns: 1fr; }
    .applicant-header { flex-direction: column; text-align: center; }
    .rating-scale { flex-direction: column; }
}
</style>

<!-- ==================== HTML ==================== -->

<?php if (isset($_GET['success'])): ?>
<div class="inline-alert success">
    <i class="fas fa-check-circle"></i> Operation completed successfully.
</div>
<?php endif; ?>

<?php if ($message): ?>
<div class="inline-alert success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="inline-alert danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- FINAL EVALUATION FORM MODE                                    -->
<!-- ============================================================ -->
<?php if (isset($_GET['action']) && $_GET['action'] === 'evaluate_final' && isset($_GET['id'])): 
    $final_interview_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("
        SELECT i.*,
               ja.id as applicant_id, ja.first_name, ja.last_name, ja.email, ja.photo_path, ja.application_number,
               jp.id as job_posting_id, jp.title as position_title, jp.job_code, jp.department,
               u.full_name as interviewer_name
        FROM interviews i
        JOIN job_applications ja ON i.applicant_id = ja.id
        LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
        LEFT JOIN users u ON i.interviewer_id = u.id
        WHERE i.id = ? AND i.interview_round = 'final'
    ");
    $stmt->execute([$final_interview_id]);
    $interview = $stmt->fetch();
    
    if (!$interview) {
        echo '<div class="inline-alert danger"><i class="fas fa-exclamation-circle"></i> Final interview not found.</div>';
    } else {
        // Fetch final-evaluation questions
        $stmt = $pdo->query("SELECT * FROM final_evaluation_questions WHERE is_active = 1 ORDER BY sort_order");
        $questions = $stmt->fetchAll();

        // Existing responses
        $responses = [];
        if (!empty($questions)) {
            $stmt = $pdo->prepare("SELECT * FROM final_evaluation_responses WHERE final_interview_id = ?");
            $stmt->execute([$final_interview_id]);
            foreach ($stmt->fetchAll() as $r) {
                $responses[$r['question_id']] = $r;
            }
        }

        $total_score = 0;
        $max_score = count($questions) * 5;
        $rated_count = 0;
        foreach ($questions as $q) {
            if (isset($responses[$q['id']]['rating'])) {
                $total_score += intval($responses[$q['id']]['rating']);
                $rated_count++;
            }
        }

        $completion_percentage = $rated_count > 0 ? round(($rated_count / max(count($questions), 1)) * 100) : 0;
        $final_percentage = $total_score > 0 ? round(($total_score / max($max_score, 1)) * 100, 1) : 0;

        $is_passing = $final_percentage >= 95;
?>

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
                <div class="applicant-header-photo"><?php echo $initials; ?></div>
            <?php endif; ?>

            <div class="applicant-header-info">
                <h2><?php echo htmlspecialchars($fullName); ?></h2>
                <p><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($interview['application_number']); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($interview['email']); ?></p>
            </div>

            <div class="applicant-header-badge">
                <div class="label">Position</div>
                <div class="value"><?php echo htmlspecialchars($interview['position_title'] ?: 'N/A'); ?></div>
                <div style="font-size: 12px; margin-top: 5px;"><?php echo htmlspecialchars($interview['job_code'] ?? ''); ?></div>
            </div>

            <div class="applicant-header-badge">
                <div class="label">Round</div>
                <div class="value">FINAL</div>
                <div style="font-size: 12px; margin-top: 5px;"><?php echo date('M d, Y', strtotime($interview['interview_date'])); ?></div>
            </div>
        </div>

        <!-- Two-column layout -->
        <div class="evaluation-content">
            <!-- LEFT: Questions -->
            <div class="questions-column">
                <div style="margin-bottom: 20px; background: white; padding: 15px; border-radius: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-size: 13px; color: var(--gray);">Evaluation Progress</span>
                        <span style="font-size: 13px; font-weight: 600; color: var(--primary);"><?php echo $completion_percentage; ?>% Complete</span>
                    </div>
                    <div style="height: 8px; background: var(--light-gray); border-radius: 4px; overflow: hidden;">
                        <div style="width: <?php echo $completion_percentage; ?>%; height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-light));"></div>
                    </div>
                </div>

                <?php foreach ($questions as $index => $question): ?>
                <div class="question-item">
                    <div class="question-text">
                        <?php echo ($index + 1) . '. ' . htmlspecialchars($question['question']); ?>
                        <span style="font-size: 11px; color: var(--gray); margin-left: 10px;">
                            (<?php echo ucfirst(str_replace('_', ' ', $question['category'])); ?>)
                        </span>
                    </div>

                    <div class="rating-scale">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
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

            <!-- RIGHT: Score Summary -->
            <div class="summary-column">
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

                <div class="passing-info">
                    <h4 style="margin: 0 0 10px; color: var(--dark);">Final Result</h4>
                    <div class="passing-badge <?php echo $is_passing ? 'passing-hire' : 'passing-reject'; ?>" id="resultBadge">
                        <?php echo $is_passing ? 'PASSED' : 'NOT PASSED'; ?>
                    </div>
                    <div style="margin-top: 15px; font-size: 13px; color: var(--gray);">
                        <p style="margin: 5px 0;"><strong>Passing Score:</strong> 95%</p>
                        <p style="margin: 5px 0;"><strong>Current Score:</strong> <span id="resultScoreText"><?php echo $final_percentage; ?>%</span></p>
                    </div>
                </div>

                <div style="background: var(--light-gray); border-radius: 15px; padding: 20px;">
                    <h4 style="margin-top: 0;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Strengths</h4>
                    <textarea name="strengths" rows="3" placeholder="What are the candidate's key strengths?" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 15px;"><?php echo htmlspecialchars($interview['strengths'] ?? ''); ?></textarea>

                    <h4><i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Weaknesses</h4>
                    <textarea name="weaknesses" rows="3" placeholder="What areas need improvement?" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 15px;"><?php echo htmlspecialchars($interview['weaknesses'] ?? ''); ?></textarea>

                    <h4><i class="fas fa-comment" style="color: var(--primary);"></i> Overall Comments</h4>
                    <textarea name="overall_comments" rows="3" placeholder="Additional comments..." style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px;"><?php echo htmlspecialchars($interview['feedback'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--border);">
            <button type="submit" name="submit_final_evaluation" class="btn btn-success" onclick="return confirmFinalSubmit()">
                <i class="fas fa-check-circle"></i> Submit Final Evaluation
            </button>
        </div>
    </form>
</div>

<script>
function updateFinalScores() {
    let total = 0, count = 0;
    document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
        if (radio.name.startsWith('rating[')) {
            total += parseInt(radio.value);
            count++;
        }
    });
    const maxScore = <?php echo count($questions); ?> * 5;
    const percentage = count > 0 ? (total / maxScore * 100).toFixed(1) : 0;

    document.getElementById('totalScore').textContent = total;
    document.getElementById('finalPercentage').textContent = percentage + '%';
    document.getElementById('resultScoreText').textContent = percentage + '%';

    const badge = document.getElementById('resultBadge');
    if (percentage >= 95) {
        badge.className = 'passing-badge passing-hire';
        badge.textContent = 'PASSED';
    } else {
        badge.className = 'passing-badge passing-reject';
        badge.textContent = 'NOT PASSED';
    }
}
function validateFinalForm() {
    const ratings = document.querySelectorAll('input[type="radio"]:checked');
    if (ratings.length === 0) {
        alert('Please rate at least one question before submitting.');
        return false;
    }
    return true;
}
function confirmFinalSubmit() {
    if (!validateFinalForm()) return false;
    return confirm('Submit this final evaluation? The interview will be marked as COMPLETED and an email will be sent to the applicant.');
}
</script>

<?php 
    }
else: 
?>

<!-- ============================================================ -->
<!-- DASHBOARD MODE                                                -->
<!-- ============================================================ -->

<div class="page-header">
    <div class="page-title">
        <i class="fas fa-trophy"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div>
        <span class="status-badge status-info" style="font-size: 12px;">
            <i class="fas fa-users"></i> Available Slots: <?php echo $stats['available_slots']; ?>
        </span>
    </div>
</div>

<div class="inline-alert info">
    <i class="fas fa-info-circle"></i>
    Only candidates who have completed the <strong>final interview</strong> (via Interview Scheduling) appear here. Once their evaluation is submitted, you can select them and set their salary.
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
            <span class="stat-label">Pending Final</span>
            <span class="stat-value"><?php echo $stats['pending_final']; ?></span>
            <div class="stat-small"><i class="fas fa-calendar" style="color: var(--warning);"></i> Awaiting evaluation</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
            <span class="stat-label">Completed Final</span>
            <span class="stat-value"><?php echo $stats['completed_final']; ?></span>
            <div class="stat-small"><i class="fas fa-star" style="color: var(--info);"></i> Evaluated</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div class="stat-content">
            <span class="stat-label">Selected</span>
            <span class="stat-value"><?php echo $stats['selected']; ?></span>
            <div class="stat-small"><i class="fas fa-trophy" style="color: var(--success);"></i> Hired</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div class="stat-content">
            <span class="stat-label">Selection Rate</span>
            <span class="stat-value"><?php 
                $total = $stats['completed_final'];
                $rate = $total > 0 ? round(($stats['selected'] / $total) * 100) : 0;
                echo $rate;
            ?>%</span>
            <div class="stat-small"><i class="fas fa-percent"></i> of evaluated</div>
        </div>
    </div>
</div>

<div class="filter-section">
    <div class="filter-title"><i class="fas fa-filter"></i> Filter Candidates</div>
    <form method="GET">
        <input type="hidden" name="page" value="recruitment">
        <input type="hidden" name="subpage" value="final-selection">
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Final</option>
                    <option value="evaluated" <?php echo $status_filter == 'evaluated' ? 'selected' : ''; ?>>Evaluated</option>
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
            <a href="?page=recruitment&subpage=final-selection" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Apply Filters</button>
        </div>
    </form>
</div>

<div class="section-block">
    <div class="section-header">
        <h2>
            <i class="fas fa-users"></i>
            Final Interview Candidates
            <span class="badge-count"><?php echo count($candidates); ?></span>
        </h2>
    </div>

    <?php if (empty($candidates)): ?>
        <div style="text-align: center; padding: 50px 20px; color: var(--gray);">
            <i class="fas fa-user-slash" style="font-size: 48px; opacity: 0.25; display: block; margin-bottom: 15px; color: var(--primary);"></i>
            <h3 style="margin: 0 0 8px; font-size: 18px; color: var(--dark);">No Final Candidates</h3>
            <p style="margin: 0; font-size: 14px;">Candidates appear here once their final interview is scheduled in Interview Scheduling.</p>
        </div>
    <?php else: ?>
        <?php $per_page = 3; $pages = array_chunk($candidates, $per_page); ?>

        <?php foreach ($pages as $page_index => $page_items): ?>
        <div class="cards-page <?php echo $page_index === 0 ? 'active' : ''; ?>" data-page="<?php echo $page_index; ?>">
            <?php foreach ($page_items as $candidate):
                $photoPath = getApplicantPhoto($candidate);
                $firstName = $candidate['first_name'] ?? '';
                $lastName = $candidate['last_name'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
                $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';

                $screening = (float)($candidate['screening_score'] ?? 0);
                $panel     = (float)($candidate['panel_score'] ?? 0);
                $final     = (float)($candidate['final_interview_score'] ?? 0);
                $overall   = calculateOverallScore($screening, $panel, $final);

                $is_hired = ($candidate['status'] === 'hired');
                $final_completed = ($candidate['final_status'] === 'completed');
                $final_scheduled = ($candidate['final_status'] === 'scheduled' || $candidate['final_status'] === 'ongoing');
                $passed_final = ($final >= 95);

                if ($is_hired)              $card_class = 'hired';
                elseif ($final_completed && $passed_final) $card_class = 'ready';
                elseif ($final_completed)   $card_class = 'failed';
                elseif ($final_scheduled)   $card_class = 'pending';
                else                        $card_class = '';
            ?>
            <div class="candidate-card <?php echo $card_class; ?>">
                <div class="card-header">
                    <div class="applicant-info">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="applicant-photo">
                        <?php else: ?>
                            <div class="applicant-photo"><?php echo $initials; ?></div>
                        <?php endif; ?>
                        <div class="applicant-details">
                            <h3><?php echo htmlspecialchars($fullName); ?></h3>
                            <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($candidate['position_title'] ?: 'General Application'); ?></p>
                            <p><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($candidate['application_number']); ?></p>
                        </div>
                    </div>
                    <?php if ($is_hired): ?>
                        <span class="status-badge status-hired"><i class="fas fa-check"></i> Hired</span>
                    <?php elseif ($final_completed && $passed_final): ?>
                        <span class="status-badge status-info"><i class="fas fa-check-circle"></i> Ready to Select</span>
                    <?php elseif ($final_completed): ?>
                        <span class="status-badge status-danger"><i class="fas fa-times"></i> Not Passed</span>
                    <?php else: ?>
                        <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending Final</span>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-clipboard-check"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Screening Score</div>
                            <div class="detail-value"><?php echo $screening; ?>%</div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-users-cog"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Panel (Initial)</div>
                            <div class="detail-value"><?php echo $panel; ?>%</div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-flag-checkered"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Final Interview</div>
                            <div class="detail-value">
                                <?php if ($candidate['final_interview_score'] !== null): ?>
                                    <span class="score-badge <?php 
                                        echo $final >= 95 ? 'score-high' : ($final >= 75 ? 'score-medium' : 'score-low'); 
                                    ?>"><?php echo $final; ?>%</span>
                                <?php else: ?>
                                    <span class="score-badge" style="background: var(--light-gray); color: var(--gray);">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Overall Score</div>
                            <div class="detail-value">
                                <span class="score-badge <?php 
                                    echo $overall >= 85 ? 'score-high' : ($overall >= 70 ? 'score-medium' : 'score-low'); 
                                ?>"><?php echo $overall; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <?php if ($is_hired): ?>
                        <button class="btn btn-info btn-sm" onclick='viewCandidateDetails(<?php echo htmlspecialchars(json_encode($candidate), ENT_QUOTES); ?>)'>
                            <i class="fas fa-eye"></i> View
                        </button>
                    <?php elseif ($final_completed && $passed_final): ?>
                        <button class="btn btn-success btn-sm" onclick='openApprovalModal(<?php echo htmlspecialchars(json_encode($candidate), ENT_QUOTES); ?>)'>
                            <i class="fas fa-check-double"></i> Select &amp; Set Salary
                        </button>
                        <button class="btn btn-outline btn-sm" onclick='viewCandidateDetails(<?php echo htmlspecialchars(json_encode($candidate), ENT_QUOTES); ?>)'>
                            <i class="fas fa-eye"></i> View
                        </button>
                    <?php elseif ($final_completed): ?>
                        <button class="btn btn-info btn-sm" onclick='viewCandidateDetails(<?php echo htmlspecialchars(json_encode($candidate), ENT_QUOTES); ?>)'>
                            <i class="fas fa-eye"></i> View Results
                        </button>
                    <?php elseif ($final_scheduled): ?>
                        <a href="?page=recruitment&subpage=final-selection&action=start_final&id=<?php echo $candidate['final_interview_id']; ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-play"></i> Evaluate Final
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <?php if (count($pages) > 1): ?>
        <div class="pagination-bar">
            <button class="btn btn-outline btn-icon" id="prevBtn" onclick="pagePrev()" disabled>
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="pagination-info" id="paginationInfo">Page 1 of <?php echo count($pages); ?></div>
            <button class="btn btn-outline btn-icon" id="nextBtn" onclick="pageNext()">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Approval Modal -->
<div id="approvalModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle" style="color: var(--success);"></i> Final Selection &amp; Salary</h3>
            <button type="button" class="modal-close" onclick="closeApprovalModal()">&times;</button>
        </div>

        <form method="POST" id="approvalForm">
            <input type="hidden" name="applicant_id" id="approval_applicant_id">

            <div id="approvalCandidateInfo" style="background: var(--light-gray); border-radius: 12px; padding: 15px; margin-bottom: 20px;"></div>

            <div class="form-group">
                <label>Approved Salary (PHP) *</label>
                <input type="number" name="approved_salary" id="approved_salary" step="500" min="5000" required placeholder="e.g., 25000">
            </div>

            <div class="form-group">
                <label>Proposed Start Date *</label>
                <input type="date" name="start_date" id="start_date" required min="<?php echo date('Y-m-d', strtotime('+1 week')); ?>">
            </div>

            <div class="form-group">
                <label>Approval Remarks</label>
                <textarea name="remarks" rows="3" placeholder="Any notes about this selection..."></textarea>
            </div>

            <div style="background: rgba(243,156,18,0.08); border-radius: 12px; padding: 15px; margin: 20px 0; border-left: 4px solid var(--warning);">
                <p style="margin: 0; font-size: 13px; color: var(--dark);">
                    <i class="fas fa-info-circle" style="color: var(--warning);"></i>
                    Once confirmed, the candidate is marked <strong>HIRED</strong>, a new hire record is created, and the job offer email is sent.
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
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <h3><i class="fas fa-user" style="color: var(--info);"></i> Candidate Details</h3>
            <button type="button" class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div id="candidateDetailsContent"></div>
    </div>
</div>

<script>
/* ========== PAGINATION ========== */
let currentPage = 0;
const pages = document.querySelectorAll('.cards-page');
const totalPages = pages.length;

function updatePagination() {
    pages.forEach((p, i) => p.classList.toggle('active', i === currentPage));
    const info = document.getElementById('paginationInfo');
    if (info) info.textContent = 'Page ' + (currentPage + 1) + ' of ' + totalPages;

    const prev = document.getElementById('prevBtn');
    const next = document.getElementById('nextBtn');
    if (prev) prev.disabled = currentPage <= 0;
    if (next) next.disabled = currentPage >= totalPages - 1;
}

function pageNext() { if (currentPage < totalPages - 1) { currentPage++; updatePagination(); } }
function pagePrev() { if (currentPage > 0) { currentPage--; updatePagination(); } }

document.addEventListener('keydown', function(e) {
    if (document.activeElement && ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;
    if (e.key === 'ArrowRight') pageNext();
    if (e.key === 'ArrowLeft') pagePrev();
});

/* ========== APPROVAL MODAL ========== */
function openApprovalModal(candidate) {
    document.getElementById('approval_applicant_id').value = candidate.id;

    const initials = (candidate.first_name ? candidate.first_name.charAt(0) : '') +
                     (candidate.last_name ? candidate.last_name.charAt(0) : '');

    const infoHtml = `
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 20px;">
                ${initials || '?'}
            </div>
            <div>
                <h4 style="margin: 0 0 5px;">${candidate.first_name} ${candidate.last_name}</h4>
                <p style="margin: 0; font-size: 13px; color: var(--gray);">${candidate.position_title || 'General Application'}</p>
                <p style="margin: 5px 0 0; font-size: 12px;"><strong>Final Score:</strong> ${candidate.final_interview_score}%</p>
            </div>
        </div>
    `;
    document.getElementById('approvalCandidateInfo').innerHTML = infoHtml;

    const startDate = new Date();
    startDate.setDate(startDate.getDate() + 14);
    document.getElementById('start_date').value = startDate.toISOString().split('T')[0];

    document.getElementById('approvalModal').classList.add('active');
}

function closeApprovalModal() {
    document.getElementById('approvalModal').classList.remove('active');
}

/* ========== VIEW CANDIDATE ========== */
function viewCandidateDetails(candidate) {
    const initials = (candidate.first_name ? candidate.first_name.charAt(0) : '') +
                     (candidate.last_name ? candidate.last_name.charAt(0) : '');

    const html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; border-radius: 20px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 24px; margin: 0 auto 10px;">
                ${initials || '?'}
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
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Screening</p>
                    <p style="font-weight: 500;">${candidate.screening_score || 0}%</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Panel (Initial)</p>
                    <p style="font-weight: 500;">${candidate.panel_score || 0}%</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Final Interview</p>
                    <p style="font-weight: 500;">${candidate.final_interview_score !== null ? candidate.final_interview_score + '%' : 'Pending'}</p>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Rating</p>
                    <p style="font-weight: 500;">${candidate.final_rating ? candidate.final_rating + '/10' : '-'}</p>
                </div>
            </div>

            ${candidate.final_feedback ? `
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                <p style="font-size: 12px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">Feedback</p>
                <p style="color: var(--gray); font-size: 13px; line-height: 1.5;">${candidate.final_feedback}</p>
            </div>
            ` : ''}
        </div>

        <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
            <button class="btn btn-outline" onclick="closeViewModal()">Close</button>
        </div>
    `;

    document.getElementById('candidateDetailsContent').innerHTML = html;
    document.getElementById('viewCandidateModal').classList.add('active');
}

function closeViewModal() {
    document.getElementById('viewCandidateModal').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeApprovalModal(); closeViewModal(); }
});

window.onclick = function(event) {
    const a = document.getElementById('approvalModal');
    const v = document.getElementById('viewCandidateModal');
    if (event.target == a) closeApprovalModal();
    if (event.target == v) closeViewModal();
}

document.addEventListener('DOMContentLoaded', updatePagination);
</script>

<?php endif; // End evaluation mode check ?>

<?php
ob_end_flush();
?>