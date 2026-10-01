<?php
// Start output buffering at the VERY FIRST LINE - NO SPACES OR CHARACTERS BEFORE THIS
ob_start();

// modules/recruitment/interview-panel.php
$page_title = "Interview Panel Evaluation";

require_once 'config/mail_config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter   = isset($_GET['date']) ? $_GET['date'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

/**
 * Simple log
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
 * Recommendation thresholds (SINGLE SOURCE OF TRUTH)
 * Returns: [db_value, label, css_class]
 *   - db_value   -> what goes in panel_evaluations.recommendation
 *   - label      -> human readable text
 *   - css_class  -> status-XXX for CSS badge
 *
 * ⚠️ Only 3 outcomes for INITIAL round (no "hire" — that's the final round):
 *    ≥ 85%  → final_interview (PASSED FOR FINAL INTERVIEW)
 *    ≥ 75%  → hold
 *    else   → reject
 */
function getRecommendationFromPercentage($percentage) {
    $p = (float)$percentage;

    if ($p >= 85) {
        return ['final_interview', 'PASSED FOR FINAL INTERVIEW', 'status-info'];
    } elseif ($p >= 75) {
        return ['hold', 'HOLD', 'status-warning'];
    } else {
        return ['reject', 'REJECT', 'status-danger'];
    }
}

/**
 * Get or create evaluation template
 */
function getOrCreateEvaluationTemplate($pdo, $job_posting_id) {
    if (!empty($job_posting_id)) {
        $stmt = $pdo->prepare("
            SELECT et.* 
            FROM evaluation_templates et
            WHERE et.position_id = ? AND et.is_active = 1
            ORDER BY et.id DESC LIMIT 1
        ");
        $stmt->execute([$job_posting_id]);
        $template = $stmt->fetch();
        if ($template) return $template;
    }
    
    $stmt = $pdo->prepare("
        SELECT et.* 
        FROM evaluation_templates et
        WHERE et.position_id IS NULL AND et.is_active = 1
        ORDER BY et.id DESC LIMIT 1
    ");
    $stmt->execute();
    $template = $stmt->fetch();
    if ($template) return $template;
    
    // Auto-create
    try {
        $pdo->beginTransaction();
        
        $position_code = 'DEFAULT-AUTO';
        $template_name = 'Default Interview Evaluation Template';
        
        if (!empty($job_posting_id)) {
            $stmt = $pdo->prepare("SELECT title, job_code FROM job_postings WHERE id = ?");
            $stmt->execute([$job_posting_id]);
            $job = $stmt->fetch();
            if ($job) {
                $position_code = 'DEFAULT-' . ($job['job_code'] ?? 'AUTO');
                $template_name = 'Default Template - ' . ($job['title'] ?? 'Position');
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO evaluation_templates 
                (position_id, position_code, template_name, is_active, created_by, created_at)
            VALUES (NULL, ?, ?, 1, ?, NOW())
        ");
        $stmt->execute([$position_code, $template_name, $_SESSION['user_id'] ?? null]);
        $template_id = $pdo->lastInsertId();
        
        $categories = [
            ['Technical Skills', 30, 1],
            ['Communication Skills', 25, 2],
            ['Problem Solving', 20, 3],
            ['Cultural Fit', 15, 4],
            ['Overall Impression', 10, 5]
        ];
        
        $category_ids = [];
        foreach ($categories as $cat) {
            $stmt = $pdo->prepare("
                INSERT INTO evaluation_categories (template_id, category_name, weight, sort_order)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$template_id, $cat[0], $cat[1], $cat[2]]);
            $category_ids[$cat[0]] = $pdo->lastInsertId();
        }
        
        $questions = [
            'Technical Skills' => [
                ['Does the candidate demonstrate the required technical knowledge for this position?', 'technical'],
                ['Can the candidate apply relevant skills and experience to real-world scenarios?', 'technical'],
                ['Does the candidate show familiarity with industry tools and best practices?', 'technical']
            ],
            'Communication Skills' => [
                ['Does the candidate communicate clearly and effectively?', 'behavioral'],
                ['Does the candidate listen actively and respond appropriately?', 'behavioral'],
                ['Can the candidate explain complex ideas in simple terms?', 'behavioral']
            ],
            'Problem Solving' => [
                ['Does the candidate demonstrate critical thinking skills?', 'situational'],
                ['Can the candidate provide practical solutions to problems?', 'situational'],
                ['How does the candidate handle unexpected challenges?', 'situational']
            ],
            'Cultural Fit' => [
                ['Does the candidate align with company values and culture?', 'behavioral'],
                ['Would the candidate work well with the existing team?', 'behavioral'],
                ['Does the candidate show commitment to long-term growth?', 'behavioral']
            ],
            'Overall Impression' => [
                ['What is your overall impression of the candidate?', 'behavioral'],
                ['Would you recommend this candidate for the position?', 'behavioral']
            ]
        ];
        
        foreach ($questions as $cat_name => $q_list) {
            $cat_id = $category_ids[$cat_name];
            $sort = 1;
            foreach ($q_list as $q) {
                $stmt = $pdo->prepare("
                    INSERT INTO evaluation_questions (category_id, question, question_type, sort_order)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$cat_id, $q[0], $q[1], $sort]);
                $sort++;
            }
        }
        
        $pdo->commit();
        
        $stmt = $pdo->prepare("SELECT * FROM evaluation_templates WHERE id = ?");
        $stmt->execute([$template_id]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw new Exception("Failed to auto-create template: " . $e->getMessage());
    }
}

function getApplicantPhoto($applicant) {
    if (!empty($applicant['photo_path']) && file_exists($applicant['photo_path'])) {
        return htmlspecialchars($applicant['photo_path']);
    }
    return null;
}

// ============================================================
// HANDLE: START EVALUATION
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'start' && isset($_GET['id'])) {
    try {
        $interview_id = (int)$_GET['id'];

        $stmt = $pdo->prepare("
            SELECT i.* FROM interviews i
            WHERE i.id = ?
              AND i.interview_round = 'initial'
              AND i.status != 'cancelled'
        ");
        $stmt->execute([$interview_id]);
        $check = $stmt->fetch();
        if (!$check) throw new Exception("Only initial interviews can be evaluated here.");
        
        $stmt = $pdo->prepare("
            SELECT id FROM panel_evaluations 
            WHERE interview_id = ? AND panel_id = ?
        ");
        $stmt->execute([$interview_id, $_SESSION['user_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            ob_clean();
            header("Location: ?page=recruitment&subpage=interview-panel&action=evaluate&id=" . $existing['id']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT i.*, ja.id as applicant_id, ja.first_name, ja.last_name, ja.email, ja.photo_path,
                   jp.id as job_posting_id, jp.title as position_title
            FROM interviews i
            JOIN job_applications ja ON i.applicant_id = ja.id
            LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
            WHERE i.id = ?
        ");
        $stmt->execute([$interview_id]);
        $interview = $stmt->fetch();
        
        if (!$interview) throw new Exception("Interview not found");
        
        getOrCreateEvaluationTemplate($pdo, $interview['job_posting_id']);
        
        $stmt = $pdo->prepare("
            INSERT INTO panel_evaluations (interview_id, applicant_id, panel_id, status)
            VALUES (?, ?, ?, 'ongoing')
        ");
        $stmt->execute([$interview_id, $interview['applicant_id'], $_SESSION['user_id']]);
        $evaluation_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("UPDATE interviews SET status = 'ongoing' WHERE id = ?");
        $stmt->execute([$interview_id]);
        
        simpleLog($pdo, $_SESSION['user_id'], 'start_evaluation', "Started evaluation for interview #$interview_id");
        
        ob_clean();
        header("Location: ?page=recruitment&subpage=interview-panel&action=evaluate&id=" . $evaluation_id);
        exit;
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// HANDLE: SUBMIT EVALUATION (uses new thresholds)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    try {
        $pdo->beginTransaction();
        
        $evaluation_id = (int)$_POST['evaluation_id'];
        $ratings = $_POST['rating'] ?? [];
        $comments = $_POST['comments'] ?? [];
        $strengths = $_POST['strengths'] ?? '';
        $weaknesses = $_POST['weaknesses'] ?? '';
        $overall_comments = $_POST['overall_comments'] ?? '';
        
        if (empty($ratings)) throw new Exception("Please rate at least one question");
        
        $total_score = 0;
        $max_score = count($ratings) * 5;
        
        foreach ($ratings as $question_id => $rating) {
            $total_score += intval($rating);
            
            $stmt = $pdo->prepare("
                SELECT id FROM evaluation_responses 
                WHERE evaluation_id = ? AND question_id = ?
            ");
            $stmt->execute([$evaluation_id, $question_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $pdo->prepare("
                    UPDATE evaluation_responses SET rating = ?, comments = ?
                    WHERE evaluation_id = ? AND question_id = ?
                ");
                $stmt->execute([$rating, $comments[$question_id] ?? null, $evaluation_id, $question_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO evaluation_responses (evaluation_id, question_id, rating, comments)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$evaluation_id, $question_id, $rating, $comments[$question_id] ?? null]);
            }
        }
        
        $final_percentage = ($total_score / $max_score) * 100;

        // ⚠️ NEW THRESHOLDS: 85-100 = passed for final; 75-84 = hold; ≤74 = reject
        list($recommendation, $rec_label, $rec_class) = getRecommendationFromPercentage($final_percentage);
        
        // Save evaluation
        $stmt = $pdo->prepare("
            UPDATE panel_evaluations SET
                total_score = ?, max_score = ?, final_percentage = ?, recommendation = ?,
                strengths = ?, weaknesses = ?, overall_comments = ?,
                status = 'submitted', submitted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $total_score, $max_score, $final_percentage, $recommendation,
            $strengths, $weaknesses, $overall_comments, $evaluation_id
        ]);
        
        // Fetch context for auto-complete
        $stmt = $pdo->prepare("
            SELECT pe.*, i.id as interview_id, i.interview_round, i.applicant_id,
                   ja.first_name, ja.last_name, ja.email,
                   jp.title as position_title
            FROM panel_evaluations pe
            JOIN interviews i ON pe.interview_id = i.id
            JOIN job_applications ja ON pe.applicant_id = ja.id
            LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
            WHERE pe.id = ?
        ");
        $stmt->execute([$evaluation_id]);
        $eval_data = $stmt->fetch();
        $interview_id = $eval_data['interview_id'];
        $applicant_id = $eval_data['applicant_id'];

        // Auto-complete interview
        $stmt = $pdo->prepare("
            UPDATE interviews SET 
                status = 'completed',
                final_recommendation = ?,
                auto_processed = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$recommendation, $interview_id]);

        // Keep applicant as 'interviewed' so scheduling page shows them for final
        $stmt = $pdo->prepare("
            UPDATE job_applications 
            SET status = 'interviewed', updated_at = NOW()
            WHERE id = ? AND status NOT IN ('rejected', 'hired')
        ");
        $stmt->execute([$applicant_id]);

        // Note
        $note = "\n[" . date('Y-m-d H:i') . "] Initial interview evaluation submitted. Score: " .
                number_format($final_percentage, 1) . "% — " . $rec_label .
                ". Initial interview marked COMPLETED.";
        $pdo->prepare("UPDATE job_applications SET notes = CONCAT(IFNULL(notes, ''), ?) WHERE id = ?")
            ->execute([$note, $applicant_id]);
        
        $pdo->commit();
        
        simpleLog($pdo, $_SESSION['user_id'], 'submit_evaluation',
            "Submitted evaluation #$evaluation_id — initial interview auto-completed");
        
        ob_clean();
        header("Location: ?page=recruitment&subpage=interview-panel&success=1");
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// HANDLE: SAVE DRAFT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_draft'])) {
    try {
        $pdo->beginTransaction();
        
        $evaluation_id = (int)$_POST['evaluation_id'];
        $ratings = $_POST['rating'] ?? [];
        $comments = $_POST['comments'] ?? [];
        $strengths = $_POST['strengths'] ?? '';
        $weaknesses = $_POST['weaknesses'] ?? '';
        $overall_comments = $_POST['overall_comments'] ?? '';
        
        foreach ($ratings as $question_id => $rating) {
            $stmt = $pdo->prepare("
                SELECT id FROM evaluation_responses WHERE evaluation_id = ? AND question_id = ?
            ");
            $stmt->execute([$evaluation_id, $question_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $pdo->prepare("
                    UPDATE evaluation_responses SET rating = ?, comments = ?
                    WHERE evaluation_id = ? AND question_id = ?
                ");
                $stmt->execute([$rating, $comments[$question_id] ?? null, $evaluation_id, $question_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO evaluation_responses (evaluation_id, question_id, rating, comments)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$evaluation_id, $question_id, $rating, $comments[$question_id] ?? null]);
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE panel_evaluations SET strengths = ?, weaknesses = ?, overall_comments = ?
            WHERE id = ?
        ");
        $stmt->execute([$strengths, $weaknesses, $overall_comments, $evaluation_id]);
        
        $pdo->commit();
        $message = "Draft saved successfully.";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// FETCH: MY INITIAL INTERVIEWS
// ============================================================
$query = "
    SELECT 
        i.*,
        ja.id as applicant_id, ja.first_name, ja.last_name, ja.email, ja.photo_path,
        ja.application_number, ja.status as applicant_status, ja.final_status,
        jp.title as position_title, jp.job_code, jp.department,
        pe.id as evaluation_id, pe.status as evaluation_status,
        pe.total_score, pe.max_score, pe.final_percentage, pe.recommendation,
        pe.strengths, pe.weaknesses, pe.overall_comments,
        u.full_name as interviewer_name
    FROM interviews i
    JOIN job_applications ja ON i.applicant_id = ja.id
    LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
    LEFT JOIN panel_evaluations pe ON i.id = pe.interview_id AND pe.panel_id = ?
    LEFT JOIN users u ON i.interviewer_id = u.id
    WHERE i.interview_round = 'initial'
      AND i.status != 'cancelled'
      AND (i.interviewer_id = ? OR i.interviewer_id IS NULL)
";

$params = [$_SESSION['user_id'], $_SESSION['user_id']];

if (!empty($status_filter) && $status_filter !== 'all') {
    if ($status_filter === 'pending') {
        $query .= " AND i.status IN ('scheduled','ongoing') AND pe.id IS NULL";
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
    $term = "%$search_filter%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

$query .= " ORDER BY i.interview_date DESC, i.interview_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$my_interviews = $stmt->fetchAll();

// ============================================================
// STATS
// ============================================================
$stats = [];

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM interviews i
    LEFT JOIN panel_evaluations pe ON i.id = pe.interview_id AND pe.panel_id = ?
    WHERE i.interview_round = 'initial'
      AND i.status IN ('scheduled','ongoing') 
      AND pe.id IS NULL
      AND (i.interviewer_id = ? OR i.interviewer_id IS NULL)
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$stats['pending'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM panel_evaluations pe
    JOIN interviews i ON pe.interview_id = i.id
    WHERE pe.panel_id = ? AND pe.status = 'ongoing' AND i.interview_round = 'initial'
");
$stmt->execute([$_SESSION['user_id']]);
$stats['ongoing'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM panel_evaluations pe
    JOIN interviews i ON pe.interview_id = i.id
    WHERE pe.panel_id = ? AND pe.status = 'submitted' AND i.interview_round = 'initial'
");
$stmt->execute([$_SESSION['user_id']]);
$stats['completed'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT AVG(pe.final_percentage) FROM panel_evaluations pe
    JOIN interviews i ON pe.interview_id = i.id
    WHERE pe.panel_id = ? AND pe.status = 'submitted' AND i.interview_round = 'initial'
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
    font-size: 24px; color: white;
    flex-shrink: 0;
}
.stat-content { flex: 1; }
.stat-label { display: block; font-size: 13px; color: var(--gray); margin-bottom: 5px; font-weight: 500; }
.stat-value { display: block; font-size: 28px; font-weight: 700; color: var(--dark); line-height: 1.2; }
.stat-small { font-size: 12px; color: var(--gray); margin-top: 5px; display: flex; align-items: center; gap: 5px; }

.filter-section {
    background: white; border-radius: 20px; padding: 20px;
    margin-bottom: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
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

.interview-card {
    background: white; border-radius: 20px; padding: 20px;
    border: 1px solid var(--border);
    display: flex; flex-direction: column;
}
.interview-card.today { border-left: 4px solid var(--warning); background: linear-gradient(to right, #fff9e6, white); }
.interview-card.pending { border-left: 4px solid var(--danger); background: linear-gradient(to right, #fee9e7, white); }
.interview-card.ongoing { border-left: 4px solid var(--info); background: linear-gradient(to right, #e8f4fd, white); }
.interview-card.completed { border-left: 4px solid var(--success); background: linear-gradient(to right, #e8f8f0, white); }

.card-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; gap: 10px; }
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
.status-pending { background: rgba(231,76,60,0.15); color: var(--danger); }
.status-ongoing { background: rgba(52,152,219,0.15); color: var(--info); }
.status-completed { background: rgba(39,174,96,0.15); color: var(--success); }
.status-success { background: rgba(39,174,96,0.15); color: var(--success); }
.status-info { background: rgba(52,152,219,0.15); color: var(--info); }
.status-warning { background: rgba(243,156,18,0.15); color: var(--warning); }
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

/* ========== EVALUATION FORM ========== */
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
.category-section {
    background: white; border-radius: 15px; padding: 20px;
    margin-bottom: 20px; border: 1px solid var(--border);
}
.category-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid var(--border);
}
.category-header h3 { font-size: 18px; font-weight: 600; color: var(--dark); margin: 0; }
.category-header .weight {
    background: var(--primary-transparent); padding: 5px 15px;
    border-radius: 30px; color: var(--primary); font-weight: 600; font-size: 14px;
}
.question-item {
    background: var(--light-gray); border-radius: 12px; padding: 15px;
    margin-bottom: 15px; border: 1px solid var(--border);
}
.question-text { font-size: 14px; font-weight: 500; color: var(--dark); margin-bottom: 12px; }
.rating-scale { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.rating-option { flex: 1; min-width: 70px; text-align: center; }
.rating-option input[type="radio"] { display: none; }
.rating-option label {
    display: block; padding: 8px 5px; background: white;
    border-radius: 8px; cursor: pointer;
    font-size: 12px; border: 1px solid var(--border);
}
.rating-option input[type="radio"]:checked + label {
    background: var(--primary); color: white;
    border-color: var(--primary);
}
.rating-option label:hover { background: var(--primary-transparent); border-color: var(--primary); }
.comment-box {
    width: 100%; padding: 10px; border: 1px solid var(--border);
    border-radius: 8px; font-size: 12px; resize: vertical; background: white;
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
.score-item .label { font-size: 12px; opacity: 0.9; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
.score-item .value { font-size: 42px; font-weight: 700; line-height: 1.2; }
.score-item .unit { font-size: 14px; opacity: 0.8; }
.score-divider { height: 2px; background: rgba(255,255,255,0.2); margin: 15px 0; }

.progress-bar {
    width: 100%; height: 8px; background: var(--light-gray);
    border-radius: 4px; overflow: hidden; margin-top: 10px;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 4px;
}

.strength-weakness-box {
    background: var(--light-gray); border-radius: 15px;
    padding: 20px; margin-bottom: 25px;
}
.strength-weakness-box h4 {
    font-size: 14px; font-weight: 600; color: var(--dark);
    margin-bottom: 10px; display: flex; align-items: center; gap: 5px;
}
.strength-weakness-box textarea {
    width: 100%; padding: 12px; border: 1px solid var(--border);
    border-radius: 10px; font-size: 13px; resize: vertical;
    margin-bottom: 15px; background: white;
}
.strength-weakness-box textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-transparent); }

.form-actions {
    display: flex; gap: 10px; justify-content: flex-end;
    margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--border);
}

/* ========== MODAL ========== */
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
.modal-close { font-size: 28px; cursor: pointer; color: var(--gray); background: none; border: none; }
.modal-close:hover { color: var(--danger); }

/* ========== INLINE ALERTS (NO ANIMATION) ========== */
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
.inline-alert.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.inline-alert.danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.inline-alert.info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

@media (max-width: 1024px) {
    .evaluation-content { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .filter-grid { grid-template-columns: 1fr; }
    .applicant-header { flex-direction: column; text-align: center; }
    .rating-scale { flex-direction: column; }
}
</style>

<!-- ==================== HTML CONTENT ==================== -->

<?php if (isset($_GET['success'])): ?>
<div class="inline-alert success">
    <i class="fas fa-check-circle"></i> Evaluation submitted. Initial interview has been marked as COMPLETED. If the score is 85% or higher, the applicant is now ready for final interview.
</div>
<?php endif; ?>

<?php if ($message): ?>
<div class="inline-alert success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="inline-alert danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- EVALUATION FORM MODE                                          -->
<!-- ============================================================ -->
<?php if (isset($_GET['action']) && $_GET['action'] === 'evaluate' && isset($_GET['id'])): 
    $evaluation_id = (int)$_GET['id'];
    
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
        echo '<div class="inline-alert danger"><i class="fas fa-exclamation-circle"></i> Evaluation not found or you do not have permission.</div>';
    } else {
        try {
            $template = getOrCreateEvaluationTemplate($pdo, $evaluation['job_posting_id']);
        } catch (Exception $e) {
            echo '<div class="inline-alert danger"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($e->getMessage()) . '</div>';
            $template = null;
        }
        
        if (!$template) {
            echo '<div class="inline-alert danger"><i class="fas fa-exclamation-circle"></i> No evaluation template available.</div>';
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    ec.id as category_id, ec.category_name, ec.weight,
                    eq.id as question_id, eq.question, eq.question_type,
                    er.rating, er.comments
                FROM evaluation_categories ec
                JOIN evaluation_questions eq ON ec.id = eq.category_id
                LEFT JOIN evaluation_responses er ON eq.id = er.question_id AND er.evaluation_id = ?
                WHERE ec.template_id = ?
                ORDER BY ec.sort_order, eq.sort_order
            ");
            $stmt->execute([$evaluation_id, $template['id']]);
            $questions = $stmt->fetchAll();
            
            if (empty($questions)) {
                echo '<div class="inline-alert danger"><i class="fas fa-exclamation-circle"></i> Template has no questions.</div>';
            } else {
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
                
                // ⚠️ NEW THRESHOLDS via shared helper
                list($auto_recommendation, $rec_text, $rec_class) = getRecommendationFromPercentage($final_percentage);
?>

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
                <div class="applicant-header-photo"><?php echo $initials; ?></div>
            <?php endif; ?>
            
            <div class="applicant-header-info">
                <h2><?php echo htmlspecialchars($fullName); ?></h2>
                <p><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($evaluation['application_number']); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($evaluation['email']); ?></p>
            </div>
            
            <div class="applicant-header-badge">
                <div class="label">Position</div>
                <div class="value"><?php echo htmlspecialchars($evaluation['position_title'] ?: 'N/A'); ?></div>
                <div style="font-size: 12px; margin-top: 5px;"><?php echo htmlspecialchars($evaluation['job_code'] ?? ''); ?></div>
            </div>
            
            <div class="applicant-header-badge">
                <div class="label">Round</div>
                <div class="value">INITIAL</div>
                <div style="font-size: 12px; margin-top: 5px;"><?php echo date('M d, Y', strtotime($evaluation['interview_date'])); ?></div>
            </div>
        </div>
        
        <div class="evaluation-content">
            <!-- LEFT COLUMN -->
            <div class="questions-column">
                <div style="margin-bottom: 20px; background: white; padding: 15px; border-radius: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-size: 13px; color: var(--gray);">Evaluation Progress</span>
                        <span style="font-size: 13px; font-weight: 600; color: var(--primary);"><?php echo $completion_percentage; ?>% Complete</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%;"></div>
                    </div>
                </div>
                
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
            
            <!-- RIGHT COLUMN -->
            <div class="summary-column">
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
                
                <div style="background: var(--light-gray); border-radius: 15px; padding: 20px; margin-bottom: 25px; text-align: center;">
                    <h4 style="margin: 0 0 10px; color: var(--dark); font-size: 14px;">Automatic Recommendation</h4>
                    <span class="status-badge <?php echo $rec_class; ?>" id="recommendationBadge" style="font-size: 12px; padding: 10px 14px;">
                        <?php echo $rec_text; ?>
                    </span>
                    
                    <div style="margin-top: 15px; font-size: 12px; color: var(--gray);">
                        <div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed var(--border);">
                            <span>Passed for Final</span>
                            <span style="color: var(--info); font-weight: 600;">85% - 100%</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed var(--border);">
                            <span>Hold</span>
                            <span style="color: var(--warning); font-weight: 600;">75% - 84%</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 5px 0;">
                            <span>Reject</span>
                            <span style="color: var(--danger); font-weight: 600;">≤ 74%</span>
                        </div>
                    </div>
                </div>
                
                <div class="strength-weakness-box">
                    <h4><i class="fas fa-check-circle" style="color: var(--success);"></i> Strengths</h4>
                    <textarea name="strengths" rows="3" placeholder="What are the candidate's key strengths?"><?php echo htmlspecialchars($evaluation['strengths'] ?? ''); ?></textarea>
                    
                    <h4 style="margin-top: 15px;"><i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Areas for Improvement</h4>
                    <textarea name="weaknesses" rows="3" placeholder="What areas need improvement?"><?php echo htmlspecialchars($evaluation['weaknesses'] ?? ''); ?></textarea>
                    
                    <h4 style="margin-top: 15px;"><i class="fas fa-comment" style="color: var(--primary);"></i> Overall Comments</h4>
                    <textarea name="overall_comments" rows="3" placeholder="Additional comments about the candidate..."><?php echo htmlspecialchars($evaluation['overall_comments'] ?? ''); ?></textarea>
                </div>
                
                <input type="hidden" name="recommendation" id="autoRecommendation" value="<?php echo $auto_recommendation; ?>">
            </div>
        </div>
        
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
    
    updateRecommendation(percentage);
}

function updateRecommendation(percentage) {
    const badge = document.getElementById('recommendationBadge');
    const hiddenField = document.getElementById('autoRecommendation');
    const p = parseFloat(percentage);

    let recClass, recText, recValue;

    // ⚠️ NEW THRESHOLDS
    if (p >= 85) {
        recClass = 'status-info';
        recText = 'PASSED FOR FINAL INTERVIEW';
        recValue = 'final_interview';
    } else if (p >= 75) {
        recClass = 'status-warning';
        recText = 'HOLD';
        recValue = 'hold';
    } else {
        recClass = 'status-danger';
        recText = 'REJECT';
        recValue = 'reject';
    }
    
    badge.className = 'status-badge ' + recClass;
    badge.textContent = recText;
    hiddenField.value = recValue;
}

function validateForm() {
    const ratingRadios = Array.from(document.querySelectorAll('input[type="radio"]:checked'))
        .filter(r => r.name.startsWith('rating['));
    
    if (ratingRadios.length === 0) {
        alert('Please rate at least one question before submitting.');
        return false;
    }
    return true;
}

function confirmSubmit() {
    if (!validateForm()) return false;
    return confirm('Submit this evaluation? The initial interview will be marked as COMPLETED automatically.');
}
</script>

<?php 
            }
        }
    }
else: 
?>

<!-- ============================================================ -->
<!-- DASHBOARD MODE                                                -->
<!-- ============================================================ -->

<div class="page-header">
    <div class="page-title">
        <i class="fas fa-clipboard-check"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div style="font-size: 13px; color: var(--gray);">
        Panel evaluations for <strong>INITIAL interviews only</strong>
    </div>
</div>

<div class="inline-alert info">
    <i class="fas fa-info-circle"></i>
    Submitting a panel evaluation automatically marks the initial interview as <strong>COMPLETED</strong>. Scores of <strong>85%+</strong> mean the applicant passed for final interview.
</div>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
            <span class="stat-label">Pending Evaluations</span>
            <span class="stat-value"><?php echo $stats['pending']; ?></span>
            <div class="stat-small"><i class="fas fa-calendar" style="color: var(--warning);"></i> Ready to start</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-spinner"></i></div>
        <div class="stat-content">
            <span class="stat-label">In Progress</span>
            <span class="stat-value"><?php echo $stats['ongoing']; ?></span>
            <div class="stat-small"><i class="fas fa-pencil-alt" style="color: var(--info);"></i> Draft mode</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
            <span class="stat-label">Completed</span>
            <span class="stat-value"><?php echo $stats['completed']; ?></span>
            <div class="stat-small"><i class="fas fa-star" style="color: var(--success);"></i> Submitted</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div class="stat-content">
            <span class="stat-label">Avg. Rating</span>
            <span class="stat-value"><?php echo $stats['avg_rating']; ?>%</span>
            <div class="stat-small"><i class="fas fa-trophy" style="color: var(--warning);"></i> Your average</div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="filter-section">
    <div class="filter-title"><i class="fas fa-filter"></i> Filter My Initial Interviews</div>
    <form method="GET">
        <input type="hidden" name="page" value="recruitment">
        <input type="hidden" name="subpage" value="interview-panel">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
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

<!-- Grid -->
<div class="section-block">
    <div class="section-header">
        <h2>
            <i class="fas fa-clipboard-list"></i>
            Initial Interviews
            <span class="badge-count"><?php echo count($my_interviews); ?></span>
        </h2>
    </div>

    <?php if (empty($my_interviews)): ?>
        <div style="text-align: center; padding: 50px 20px; color: var(--gray);">
            <i class="fas fa-clipboard-list" style="font-size: 48px; opacity: 0.25; display: block; margin-bottom: 15px; color: var(--primary);"></i>
            <h3 style="margin: 0 0 8px; font-size: 18px; color: var(--dark);">No Initial Interviews</h3>
            <p style="margin: 0; font-size: 14px;">There are no initial interviews assigned to you at the moment.</p>
        </div>
    <?php else: ?>
        <?php $per_page = 3; $pages = array_chunk($my_interviews, $per_page); ?>

        <?php foreach ($pages as $page_index => $page_items): ?>
        <div class="cards-page <?php echo $page_index === 0 ? 'active' : ''; ?>" data-page="<?php echo $page_index; ?>">
            <?php foreach ($page_items as $interview): 
                $photoPath = getApplicantPhoto($interview);
                $firstName = $interview['first_name'] ?? '';
                $lastName = $interview['last_name'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
                $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                
                $status_class = 'pending';
                $status_text = 'Pending';
                $status_icon = 'clock';
                
                if ($interview['evaluation_status'] == 'ongoing') {
                    $status_class = 'ongoing';
                    $status_text = 'In Progress';
                    $status_icon = 'spinner';
                } elseif ($interview['evaluation_status'] == 'submitted') {
                    $status_class = 'completed';
                    $status_text = 'Completed';
                    $status_icon = 'check';
                }
                
                $is_today = $interview['interview_date'] == date('Y-m-d');
            ?>
            <div class="interview-card <?php echo $is_today ? 'today' : ''; ?> <?php echo $status_class; ?>">
                <div class="card-header">
                    <div class="applicant-info">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fullName); ?>"
                                 class="applicant-photo"
                                 onerror="this.style.display='none'">
                        <?php else: ?>
                            <div class="applicant-photo"><?php echo $initials; ?></div>
                        <?php endif; ?>
                        
                        <div class="applicant-details">
                            <h3><?php echo htmlspecialchars($fullName); ?></h3>
                            <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($interview['position_title'] ?: 'General Application'); ?></p>
                            <p><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($interview['application_number']); ?></p>
                        </div>
                    </div>
                    
                    <span class="status-badge status-<?php echo $status_class; ?>">
                        <i class="fas fa-<?php echo $status_icon; ?>"></i>
                        <?php echo $status_text; ?>
                    </span>
                </div>
                
                <div class="card-body">
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-calendar"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Interview Date</div>
                            <div class="detail-value">
                                <?php echo date('M d, Y', strtotime($interview['interview_date'])); ?>
                                <?php if ($is_today): ?><span style="color: var(--warning); margin-left: 5px;">(Today)</span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-clock"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Time</div>
                            <div class="detail-value"><?php echo date('h:i A', strtotime($interview['interview_time'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-tag"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Round</div>
                            <div class="detail-value">Initial Interview</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($interview['final_percentage']) && $interview['final_percentage'] > 0): ?>
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-star"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Your Score</div>
                            <div class="detail-value">
                                <span class="score-badge <?php 
                                    echo $interview['final_percentage'] >= 85 ? 'score-high' : 
                                        ($interview['final_percentage'] >= 75 ? 'score-medium' : 'score-low'); 
                                ?>">
                                    <?php echo $interview['final_percentage']; ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer">
                    <?php if ($interview['evaluation_status'] == 'ongoing'): ?>
                        <a href="?page=recruitment&subpage=interview-panel&action=evaluate&id=<?php echo $interview['evaluation_id']; ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-pencil-alt"></i> Continue
                        </a>
                    <?php elseif ($interview['evaluation_status'] == 'submitted'): ?>
                        <button class="btn btn-info btn-sm" onclick='viewEvaluation(<?php echo json_encode($interview, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                            <i class="fas fa-eye"></i> Results
                        </button>
                    <?php else: ?>
                        <a href="?page=recruitment&subpage=interview-panel&action=start&id=<?php echo $interview['id']; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-play"></i> Start
                        </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline btn-sm" onclick='viewInterviewDetails(<?php echo json_encode($interview, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                        <i class="fas fa-info-circle"></i> Details
                    </button>
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

<!-- Modals -->
<div id="viewDetailsModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle" style="color: var(--info);"></i> Interview Details</h3>
            <button type="button" class="modal-close" onclick="closeDetailsModal()">&times;</button>
        </div>
        <div id="detailsContent"></div>
    </div>
</div>

<div id="viewResultsModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check" style="color: var(--success);"></i> Evaluation Results</h3>
            <button type="button" class="modal-close" onclick="closeResultsModal()">&times;</button>
        </div>
        <div id="resultsContent"></div>
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

function pageNext() {
    if (currentPage < totalPages - 1) { currentPage++; updatePagination(); }
}
function pagePrev() {
    if (currentPage > 0) { currentPage--; updatePagination(); }
}

document.addEventListener('keydown', function(e) {
    if (document.activeElement && ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;
    if (e.key === 'ArrowRight') pageNext();
    if (e.key === 'ArrowLeft') pagePrev();
});

/* ========== VIEW MODALS ========== */
function viewInterviewDetails(interview) {
    const hasMeetingLink = interview.meeting_link && interview.meeting_link.trim() !== '';
    const interviewType = hasMeetingLink ? 'Online' : 'Face-to-Face';
    
    const html = `
        <div style="text-align: center; margin-bottom: 20px;">
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
                <p><a href="${interview.meeting_link}" target="_blank" style="color: var(--primary); word-break: break-all;">${interview.meeting_link}</a></p>
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
}

function viewEvaluation(interview) {
    // Build label from percentage using the same thresholds
    const p = parseFloat(interview.final_percentage || 0);
    let label, cls;
    if (p >= 85)      { label = 'Passed for Final Interview'; cls = 'info'; }
    else if (p >= 75) { label = 'Hold'; cls = 'warning'; }
    else              { label = 'Reject'; cls = 'danger'; }
    
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
                <span style="font-weight: 600; color: var(--dark);">Recommendation</span>
                <span class="status-badge status-${cls}">${label}</span>
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
}

function closeDetailsModal() {
    document.getElementById('viewDetailsModal').classList.remove('active');
}

function closeResultsModal() {
    document.getElementById('viewResultsModal').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetailsModal();
        closeResultsModal();
    }
});

window.onclick = function(event) {
    const dm = document.getElementById('viewDetailsModal');
    const rm = document.getElementById('viewResultsModal');
    if (event.target == dm) closeDetailsModal();
    if (event.target == rm) closeResultsModal();
}

document.addEventListener('DOMContentLoaded', updatePagination);
</script>

<?php endif; ?>

<?php
ob_end_flush();
?>