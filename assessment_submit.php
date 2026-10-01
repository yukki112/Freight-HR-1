<?php
// assessment_submit.php — handles AJAX submission from assessment.php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/assessment_questions.php';
require_once __DIR__ . '/config/mail_config.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Manila');

function jsonOut($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data);
    exit;
}

try {
    // ---- 1. Read raw input ----
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        jsonOut(['success' => false, 'message' => 'Empty request body.']);
    }
    $input = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonOut([
            'success' => false,
            'message' => 'Invalid JSON: ' . json_last_error_msg(),
            'raw_first_200' => substr($raw, 0, 200)
        ]);
    }

    // ---- 2. Extract fields ----
    $applicant_id = (int)($input['applicant_id'] ?? 0);
    $app_number   = trim($input['application_number'] ?? '');
    $token        = trim($input['token'] ?? '');
    $answers      = $input['answers'] ?? [];

    if (!$applicant_id || $app_number === '' || $token === '') {
        jsonOut(['success' => false, 'message' => 'Missing required data (applicant_id, application_number, or token).']);
    }
    if (!is_array($answers) || empty($answers)) {
        jsonOut(['success' => false, 'message' => 'No answers were submitted.']);
    }

    // ---- 3. Fetch applicant + job ----
    $stmt = $pdo->prepare("
        SELECT a.*, jp.title AS job_title, jp.department AS job_department
        FROM job_applications a
        LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
        WHERE a.id = ? AND a.application_number = ?
        LIMIT 1
    ");
    $stmt->execute([$applicant_id, $app_number]);
    $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$applicant) {
        jsonOut(['success' => false, 'message' => 'Applicant not found.']);
    }
    if (in_array($applicant['assessment_status'], ['passed', 'failed'], true)) {
        jsonOut(['success' => false, 'message' => 'Assessment already submitted.']);
    }
    if ((int)$applicant['requires_assessment'] !== 1) {
        jsonOut(['success' => false, 'message' => 'This applicant does not require an assessment.']);
    }

    // ---- 4. Load questions & grade ----
    $questions = getAssessmentQuestions($applicant['job_department'] ?? '', $applicant['job_title'] ?? '');
    $total     = count($questions);
    if ($total === 0) {
        jsonOut(['success' => false, 'message' => 'No assessment questions configured for this role.']);
    }

    $correct = 0;
    foreach ($questions as $i => $q) {
        if (isset($answers['q' . $i]) && (int)$answers['q' . $i] === (int)$q['answer']) {
            $correct++;
        }
    }

    $score_pct  = round(($correct / $total) * 100, 2);
    $passing    = 70.00;
    $result     = $score_pct >= $passing ? 'passed' : 'failed';
    $new_status = $result === 'passed' ? 'assessment_passed' : 'assessment_failed';

    // ---- 5. Save result (upsert) ----
    $stmt = $pdo->prepare("SELECT id FROM assessment_results WHERE applicant_id = ? LIMIT 1");
    $stmt->execute([$applicant_id]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $stmt = $pdo->prepare("
            UPDATE assessment_results
            SET correct_answers = ?, total_questions = ?, score_percentage = ?, result = ?,
                completed_at = NOW(),
                duration_minutes = TIMESTAMPDIFF(MINUTE, IFNULL(started_at, NOW()), NOW()),
                answers_json = ?
            WHERE applicant_id = ?
        ");
        $stmt->execute([
            $correct, $total, $score_pct, $result,
            json_encode($answers), $applicant_id
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO assessment_results
            (applicant_id, job_posting_id, department, position_title, assessment_type,
             total_questions, correct_answers, score_percentage, result,
             started_at, completed_at, duration_minutes, answers_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0, ?)
        ");
        $stmt->execute([
            $applicant_id,
            $applicant['job_posting_id'] ?? null,
            $applicant['job_department'] ?? null,
            $applicant['job_title'] ?? null,
            function_exists('getAssessmentType') ? getAssessmentType($applicant['job_department'] ?? '') : 'General',
            $total, $correct, $score_pct, $result,
            json_encode($answers)
        ]);
    }

    // ---- 6. Update job_applications ----
    $stmt = $pdo->prepare("UPDATE job_applications SET assessment_status = ?, status = ? WHERE id = ?");
    $stmt->execute([$result, $new_status, $applicant_id]);

    $note = "\n[" . date('Y-m-d H:i') . "] Assessment taken. Score: {$score_pct}% ({$correct}/{$total}) — " . strtoupper($result);
    $pdo->prepare("UPDATE job_applications SET notes = CONCAT(IFNULL(notes,''), ?) WHERE id = ?")
        ->execute([$note, $applicant_id]);

    // ---- 7. Activity log — FIXED: NULL user_id for guest actions ----
    if (function_exists('logActivity')) {
        try {
            // Passing NULL is safe: FK allows ON DELETE SET NULL.
            // Also pass IP + user agent since we know them.
            logActivity(
                $pdo,
                null,                                 // <-- NULL, not 0
                'assessment_submitted',
                "Applicant #{$applicant_id} ({$app_number}) took assessment. Score: {$score_pct}% ({$result})"
            );
        } catch (Throwable $e) {
            // Never let logging break the response
            error_log('logActivity failed: ' . $e->getMessage());
        }
    }

    // ---- 8. Send result email ----
    $emailSent = false;
    try {
        $fullName = trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''));
        $res = sendAssessmentResultEmail(
            $applicant['email'],
            $fullName,
            [
                'application_number' => $applicant['application_number'],
                'job_title'          => $applicant['job_title'] ?? 'Position',
                'score'              => $score_pct,
                'correct'            => $correct,
                'total'              => $total,
                'result'             => $result,
            ]
        );
        $emailSent = !empty($res['success']);

        $stmt = $pdo->prepare("
            INSERT INTO communication_log
            (applicant_id, communication_type, subject, message, sent_by, sent_at, status)
            VALUES (?, 'email', ?, ?, NULL, NOW(), ?)
        ");
        $stmt->execute([
            $applicant_id,
            ($result === 'passed' ? '🎉 Assessment Passed — ' : 'Assessment Result — ') . ($applicant['job_title'] ?? ''),
            "Assessment score: {$score_pct}% — Result: " . strtoupper($result),
            $emailSent ? 'sent' : 'failed'
        ]);
    } catch (Throwable $e) {
        error_log('Assessment result email failed: ' . $e->getMessage());
    }

    // ---- 9. Respond ----
    jsonOut([
        'success'    => true,
        'result'     => $result,
        'score'      => $score_pct,
        'correct'    => $correct,
        'total'      => $total,
        'email_sent' => $emailSent,
    ]);

} catch (Throwable $e) {
    jsonOut([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}