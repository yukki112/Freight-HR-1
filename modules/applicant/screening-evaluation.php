<?php
// modules/applicant/screening-evaluation.php
$page_title = "Screening & Evaluation";

// Load helpers
require_once __DIR__ . '/../../includes/assessment_rules.php';
require_once __DIR__ . '/../../config/mail_config.php';

// Create screening_evaluations table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS screening_evaluations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            applicant_id INT NOT NULL,
            screening_score INT,
            qualification_match INT,
            screening_notes TEXT,
            evaluated_by INT,
            evaluation_date DATETIME,
            screening_result ENUM('pass', 'fail', 'pending') DEFAULT 'pending',
            status_updated_to VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (applicant_id) REFERENCES job_applications(id) ON DELETE CASCADE,
            FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_applicant (applicant_id),
            INDEX idx_result (screening_result)
        )
    ");
} catch (Exception $e) { /* Table might already exist */ }

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_evaluation'])) {
    $applicant_id        = (int)$_POST['applicant_id'];
    $screening_score     = max(0, min(100, (int)$_POST['screening_score']));
    $qualification_match = max(0, min(100, (int)$_POST['qualification_match']));
    $screening_notes     = $_POST['screening_notes'] ?? '';
    $screening_result    = $_POST['screening_result'] ?? 'pending';
    $update_status       = isset($_POST['update_status']) ? 1 : 0;

    // Fetch applicant + job details
    $stmt = $pdo->prepare("
        SELECT a.*, jp.title AS job_title, jp.job_code, jp.department
        FROM job_applications a
        LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
        WHERE a.id = ?
    ");
    $stmt->execute([$applicant_id]);
    $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$applicant) {
        $error_message = "Applicant not found.";
    } else {
        // Save / update screening evaluation
        $stmt = $pdo->prepare("SELECT id FROM screening_evaluations WHERE applicant_id = ?");
        $stmt->execute([$applicant_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE screening_evaluations 
                SET screening_score = ?, qualification_match = ?, screening_notes = ?, 
                    evaluated_by = ?, evaluation_date = NOW(), screening_result = ?
                WHERE applicant_id = ?
            ");
            $stmt->execute([$screening_score, $qualification_match, $screening_notes, $_SESSION['user_id'], $screening_result, $applicant_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO screening_evaluations 
                (applicant_id, screening_score, qualification_match, screening_notes, evaluated_by, evaluation_date, screening_result)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$applicant_id, $screening_score, $qualification_match, $screening_notes, $_SESSION['user_id'], $screening_result]);
        }

        // ============================================================
        // IF PASSED -> Determine assessment requirement + send email
        // ============================================================
        if ($screening_result === 'pass' && $update_status) {
            $department  = $applicant['department'] ?? '';
            $jobTitle    = $applicant['job_title'] ?? $applicant['position_applied'] ?? 'the position';
            $needsAssess = requiresAssessment($department, $jobTitle);

            $stmt = $pdo->prepare("UPDATE job_applications SET requires_assessment = ?, assessment_status = ? WHERE id = ?");
            $stmt->execute([$needsAssess ? 1 : 0, $needsAssess ? 'pending' : 'not_required', $applicant_id]);

            $fullName = trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''));
            $emailResult = ['success' => false, 'message' => ''];

            if ($needsAssess) {
                $new_status = 'for_assessment';
                $assessmentType = getAssessmentType($department);
                $baseUrl        = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                                  . '://' . $_SERVER['HTTP_HOST'];
                $assessmentLink = $baseUrl . '/hr1/assessment.php?app=' . urlencode($applicant['application_number'])
                                  . '&token=' . urlencode(bin2hex(random_bytes(16)));

                $emailResult = sendScreeningPassedWithAssessmentEmail(
                    $applicant['email'], $fullName,
                    [
                        'application_number' => $applicant['application_number'],
                        'job_title'          => $jobTitle,
                        'department'         => $department,
                        'assessment_type'    => $assessmentType,
                        'assessment_link'    => $assessmentLink,
                    ]
                );

                $stmt = $pdo->prepare("
                    INSERT INTO communication_log 
                    (applicant_id, communication_type, subject, message, sent_by, sent_at, status)
                    VALUES (?, 'email', ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $applicant_id,
                    "🎉 You Passed Screening – Take Your Assessment | " . $jobTitle,
                    "Assessment invitation sent. Assessment: {$assessmentType}. Link: {$assessmentLink}",
                    $_SESSION['user_id'],
                    $emailResult['success'] ? 'sent' : 'failed'
                ]);

                if ($emailResult['success']) {
                    $stmt = $pdo->prepare("UPDATE job_applications SET assessment_sent_at = NOW() WHERE id = ?");
                    $stmt->execute([$applicant_id]);
                }

                $email_status = $emailResult['success']
                    ? "Assessment email sent to {$applicant['email']}."
                    : "⚠ Email failed: " . $emailResult['message'];
            } else {
                $new_status = 'shortlisted';

                $emailResult = sendScreeningPassedNoAssessmentEmail(
                    $applicant['email'], $fullName,
                    [
                        'application_number' => $applicant['application_number'],
                        'job_title'          => $jobTitle,
                        'department'         => $department,
                    ]
                );

                $stmt = $pdo->prepare("
                    INSERT INTO communication_log 
                    (applicant_id, communication_type, subject, message, sent_by, sent_at, status)
                    VALUES (?, 'email', ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $applicant_id,
                    "🎉 You Passed Screening – Initial Interview Next | " . $jobTitle,
                    "Screening passed. Proceeding to initial interview (no assessment required).",
                    $_SESSION['user_id'],
                    $emailResult['success'] ? 'sent' : 'failed'
                ]);

                $email_status = $emailResult['success']
                    ? "Congratulations email sent to {$applicant['email']}."
                    : "⚠ Email failed: " . $emailResult['message'];
            }

            $stmt = $pdo->prepare("UPDATE job_applications SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $applicant_id]);

            $status_note = "[" . date('Y-m-d H:i') . "] Status updated to {$new_status} based on screening evaluation. "
                         . ($needsAssess ? "Assessment required ({$assessmentType})." : "No assessment needed.");
            $stmt = $pdo->prepare("UPDATE job_applications SET notes = CONCAT(IFNULL(notes, ''), '\n', ?) WHERE id = ?");
            $stmt->execute([$status_note, $applicant_id]);

            logActivity($pdo, $_SESSION['user_id'], 'update_applicant_status', "Updated applicant #{$applicant_id} status to {$new_status} via screening");
            $success_message = "Screening evaluation saved! " . ($email_status ?? '');
        }
        elseif ($screening_result === 'fail' && $update_status) {
            $new_status = 'rejected';
            $stmt = $pdo->prepare("UPDATE job_applications SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $applicant_id]);

            $status_note = "[" . date('Y-m-d H:i') . "] Status updated to {$new_status} based on screening evaluation.";
            $stmt = $pdo->prepare("UPDATE job_applications SET notes = CONCAT(IFNULL(notes, ''), '\n', ?) WHERE id = ?");
            $stmt->execute([$status_note, $applicant_id]);

            logActivity($pdo, $_SESSION['user_id'], 'update_applicant_status', "Updated applicant #{$applicant_id} status to {$new_status} via screening");
            $success_message = "Screening evaluation saved. Applicant marked as rejected.";
        } else {
            $success_message = "Screening evaluation saved successfully!";
        }

        logActivity($pdo, $_SESSION['user_id'], 'screening_evaluation', "Saved screening evaluation for applicant #{$applicant_id}");
    }
}

// ============================================================
// FILTERS
// ============================================================
$status_filter     = $_GET['status'] ?? 'pending';
$search_filter     = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';

$query = "
    SELECT 
        a.*,
        jp.title as job_title, jp.job_code, jp.department,
        jp.experience_required, jp.education_required, jp.license_required,
        se.id as evaluation_id, se.screening_score, se.qualification_match,
        se.screening_notes, se.screening_result, se.evaluation_date,
        u.full_name as evaluator_name,
        CASE 
            WHEN se.id IS NOT NULL THEN 
                CASE 
                    WHEN se.screening_result = 'pass' THEN 'Passed'
                    WHEN se.screening_result = 'fail' THEN 'Failed'
                    ELSE 'Evaluated'
                END
            ELSE 'Pending'
        END as screening_status
    FROM job_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN screening_evaluations se ON a.id = se.applicant_id
    LEFT JOIN users u ON se.evaluated_by = u.id
    WHERE 1=1
";
$params = [];

if ($status_filter === 'pending')       $query .= " AND se.id IS NULL";
elseif ($status_filter === 'evaluated') $query .= " AND se.id IS NOT NULL";
elseif ($status_filter === 'passed')    $query .= " AND se.screening_result = 'pass'";
elseif ($status_filter === 'failed')    $query .= " AND se.screening_result = 'fail'";

if (!empty($department_filter)) {
    $query .= " AND jp.department = ?";
    $params[] = $department_filter;
}
if (!empty($search_filter)) {
    $query .= " AND (a.first_name LIKE ? OR a.last_name LIKE ? OR a.application_number LIKE ? OR a.email LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term; $params[] = $search_term; $params[] = $search_term; $params[] = $search_term;
}
$query .= " ORDER BY a.applied_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

// ============================================================
// LOAD SELECTED APPLICANT FOR MODAL
// ============================================================
$selected_applicant = null;
$existing_evaluation = null;

if (isset($_GET['evaluate'])) {
    $applicant_id = (int)$_GET['evaluate'];
    $stmt = $pdo->prepare("
        SELECT a.*, jp.title as job_title, jp.job_code, jp.department,
               jp.experience_required, jp.education_required, jp.license_required,
               jp.salary_min, jp.salary_max
        FROM job_applications a
        LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
        WHERE a.id = ?
    ");
    $stmt->execute([$applicant_id]);
    $selected_applicant = $stmt->fetch();

    if ($selected_applicant) {
        $stmt = $pdo->prepare("
            SELECT se.*, u.full_name as evaluator_name
            FROM screening_evaluations se
            LEFT JOIN users u ON se.evaluated_by = u.id
            WHERE se.applicant_id = ?
        ");
        $stmt->execute([$applicant_id]);
        $existing_evaluation = $stmt->fetch();
    }
}

// ============================================================
// STATS
// ============================================================
$stats = [];
$stats['pending']   = $pdo->query("SELECT COUNT(*) FROM job_applications a LEFT JOIN screening_evaluations se ON a.id = se.applicant_id WHERE se.id IS NULL")->fetchColumn();
$stats['evaluated'] = $pdo->query("SELECT COUNT(*) FROM screening_evaluations")->fetchColumn();
$stats['passed']    = $pdo->query("SELECT COUNT(*) FROM screening_evaluations WHERE screening_result = 'pass'")->fetchColumn();
$stats['failed']    = $pdo->query("SELECT COUNT(*) FROM screening_evaluations WHERE screening_result = 'fail'")->fetchColumn();

$dept_stats = $pdo->query("
    SELECT jp.department, COUNT(*) as total,
           SUM(CASE WHEN se.id IS NOT NULL THEN 1 ELSE 0 END) as evaluated,
           SUM(CASE WHEN se.id IS NULL THEN 1 ELSE 0 END) as pending
    FROM job_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN screening_evaluations se ON a.id = se.applicant_id
    WHERE jp.department IS NOT NULL
    GROUP BY jp.department
    ORDER BY total DESC
")->fetchAll();

$departments = $pdo->query("SELECT DISTINCT department FROM job_postings WHERE department IS NOT NULL ORDER BY department")->fetchAll();

function getApplicantPhoto($applicant) {
    if (!empty($applicant['photo_path']) && file_exists($applicant['photo_path'])) {
        return htmlspecialchars($applicant['photo_path']);
    }
    return null;
}
?>

<style>
:root {
    --primary-color: #0e4c92;
    --primary-light: #1e5ca8;
    --primary-dark: #0a3a70;
    --primary-transparent: rgba(14, 76, 146, 0.1);
    --success-color: #27ae60;
    --warning-color: #f39c12;
    --danger-color: #e74c3c;
    --info-color: #3498db;
}

.page-header-unique {
    background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;
}
.page-title { display: flex; align-items: center; gap: 15px; }
.page-title h1 { font-size: 24px; font-weight: 600; color: #2c3e50; margin: 0; }
.page-title i { font-size: 28px; color: #0e4c92; background: rgba(14, 76, 146, 0.1); padding: 12px; border-radius: 15px; }

.stats-grid-unique { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
.stat-card-unique {
    background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex; align-items: center; gap: 15px; transition: all 0.3s ease; border: 1px solid rgba(0,0,0,0.03);
}
.stat-card-unique:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(14, 76, 146, 0.15); }
.stat-icon-3d {
    width: 50px; height: 50px; background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white;
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.2);
}
.stat-content { flex: 1; }
.stat-label { display: block; font-size: 13px; color: #64748b; margin-bottom: 5px; font-weight: 500; }
.stat-value { display: block; font-size: 24px; font-weight: 700; color: #2c3e50; line-height: 1.2; }

.dept-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 25px; }
.dept-card { background: white; border-radius: 15px; padding: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
.dept-icon { width: 45px; height: 45px; background: rgba(14, 76, 146, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #0e4c92; font-size: 20px; }
.dept-info { flex: 1; }
.dept-name { font-size: 14px; font-weight: 600; color: #2c3e50; margin-bottom: 5px; text-transform: capitalize; }
.dept-progress { height: 6px; background: #eef2f6; border-radius: 3px; overflow: hidden; margin-bottom: 5px; }
.dept-progress-bar { height: 100%; background: linear-gradient(90deg, #0e4c92, #4086e4); border-radius: 3px; }
.dept-stats-text { display: flex; justify-content: space-between; font-size: 11px; color: #64748b; }

.filter-section { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
.filter-title { font-size: 16px; font-weight: 600; color: #2c3e50; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
.filter-title i { color: #0e4c92; }
.filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.filter-item { display: flex; flex-direction: column; gap: 5px; }
.filter-item label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.filter-item input, .filter-item select { padding: 10px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; transition: all 0.3s; }
.filter-item input:focus, .filter-item select:focus { outline: none; border-color: #0e4c92; box-shadow: 0 0 0 3px rgba(14, 76, 146, 0.1); }
.filter-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

.table-container { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow-x: auto; }
.unique-table { width: 100%; border-collapse: collapse; }
.unique-table th { text-align: left; padding: 15px; background: #f8fafd; color: #64748b; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
.unique-table td { padding: 15px; border-bottom: 1px solid #eef2f6; color: #2c3e50; font-size: 14px; vertical-align: middle; }
.unique-table tr:hover td { background: #f8fafd; }

.applicant-photo-medium { width: 45px; height: 45px; border-radius: 12px; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 8px rgba(14, 76, 146, 0.2); background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px; flex-shrink: 0; }
.photo-fallback-medium { width: 45px; height: 45px; border-radius: 12px; background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px; flex-shrink: 0; }

.category-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-success { background: #27ae6020; color: #27ae60; }
.badge-warning { background: #f39c1220; color: #f39c12; }
.badge-danger { background: #e74c3c20; color: #e74c3c; }
.badge-info { background: #3498db20; color: #3498db; }
.badge-purple { background: #9b59b620; color: #9b59b6; }

.score-badge { padding: 4px 10px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
.score-high { background: #27ae6020; color: #27ae60; }
.score-medium { background: #f39c1220; color: #f39c12; }
.score-low { background: #e74c3c20; color: #e74c3c; }

.btn-primary { background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%); color: white; padding: 10px 20px; border-radius: 12px; font-size: 14px; font-weight: 500; transition: all 0.3s ease; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(14, 76, 146, 0.3); }
.btn-secondary { background: #f8fafd; color: #0e4c92; padding: 10px 20px; border-radius: 12px; font-size: 14px; font-weight: 500; transition: all 0.3s ease; border: 1px solid #e2e8f0; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
.btn-secondary:hover { background: #0e4c92; color: white; border-color: #0e4c92; }
.btn-sm { padding: 8px 16px; font-size: 13px; }

.alert-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
.alert-danger { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

/* ==========================================
   REDESIGNED MODAL
   ========================================== */
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(10, 25, 41, 0.75); backdrop-filter: blur(6px); z-index: 1000; align-items: flex-start; justify-content: center; padding: 30px 15px; overflow-y: auto; }
.modal.active { display: flex; }

.modal-content {
    background: #ffffff;
    border-radius: 24px;
    max-width: 780px;
    width: 100%;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    animation: modalSlide 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    margin: auto;
}
@keyframes modalSlide {
    from { opacity: 0; transform: translateY(30px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* Gradient header bar */
.modal-header {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    padding: 22px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    position: relative;
    overflow: hidden;
}
.modal-header::before {
    content: '';
    position: absolute;
    width: 200px; height: 200px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
    top: -80px; right: -40px;
}
.modal-header::after {
    content: '';
    position: absolute;
    width: 120px; height: 120px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    bottom: -60px; left: 30%;
}
.modal-header h3 {
    font-size: 18px; font-weight: 600; margin: 0;
    display: flex; align-items: center; gap: 10px;
    position: relative; z-index: 2;
}
.modal-header h3 i {
    background: rgba(255,255,255,0.2);
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
}
.modal-close {
    background: rgba(255,255,255,0.15);
    border: none;
    width: 34px; height: 34px;
    border-radius: 10px;
    color: white;
    font-size: 18px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s;
    text-decoration: none;
    position: relative; z-index: 2;
    line-height: 1;
}
.modal-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }

/* Modal body wrapper */
.modal-body { padding: 26px 28px 0; }

/* Applicant hero card */
.applicant-hero {
    background: linear-gradient(135deg, #f8fafd 0%, #eef4fc 100%);
    border-radius: 18px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
    position: relative;
    overflow: hidden;
}
.applicant-hero::after {
    content: '';
    position: absolute;
    width: 100px; height: 100px;
    background: radial-gradient(circle, rgba(14,76,146,0.08) 0%, transparent 70%);
    border-radius: 50%;
    top: -30px; right: -30px;
}
.applicant-avatar {
    width: 76px; height: 76px;
    border-radius: 20px;
    flex-shrink: 0;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 8px 20px rgba(14, 76, 146, 0.25);
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 700; font-size: 26px;
}
.applicant-details { flex: 1; position: relative; z-index: 2; }
.applicant-details h4 { font-size: 19px; font-weight: 700; color: #1e293b; margin: 0 0 8px 0; }
.applicant-meta { display: flex; flex-wrap: wrap; gap: 14px; font-size: 13px; color: #64748b; }
.applicant-meta span { display: flex; align-items: center; gap: 6px; }
.applicant-meta i { color: #0e4c92; font-size: 12px; }

/* Assessment banner */
.assessment-banner {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 18px;
    border-radius: 14px;
    margin-bottom: 22px;
    font-size: 13px;
    line-height: 1.5;
    border-left: 4px solid;
    animation: fadeIn 0.4s;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.assessment-banner.required {
    background: linear-gradient(135deg, #fff8e1 0%, #fffbf0 100%);
    border-left-color: #f39c12;
    color: #7a5c00;
}
.assessment-banner.not-required {
    background: linear-gradient(135deg, #e8fff1 0%, #f0fff7 100%);
    border-left-color: #27ae60;
    color: #155724;
}
.assessment-banner-icon {
    width: 40px; height: 40px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
    background: rgba(255,255,255,0.6);
}
.assessment-banner.required .assessment-banner-icon { color: #f39c12; }
.assessment-banner.not-required .assessment-banner-icon { color: #27ae60; }
.assessment-banner strong { display: block; font-weight: 700; margin-bottom: 3px; font-size: 13.5px; }

/* Requirements grid */
.req-section { margin-bottom: 22px; }
.req-section-title {
    font-size: 12px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; color: #64748b;
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 12px;
}
.req-section-title i { color: #0e4c92; }
.req-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
}
.req-card {
    background: #f8fafd;
    border: 1px solid #eef2f6;
    border-radius: 12px;
    padding: 12px 14px;
    transition: all 0.3s;
}
.req-card:hover { border-color: #0e4c92; background: white; box-shadow: 0 4px 12px rgba(14,76,146,0.06); }
.req-card-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; font-weight: 600; }
.req-card-value { font-size: 13px; font-weight: 600; color: #1e293b; line-height: 1.4; }

/* ==========================================
   SCORE INPUT — dual (slider + number)
   ========================================== */
.score-block {
    background: #f8fafd;
    border-radius: 16px;
    padding: 18px;
    margin-bottom: 18px;
    border: 1px solid #eef2f6;
    transition: all 0.3s;
}
.score-block:hover { border-color: #0e4c92; box-shadow: 0 4px 16px rgba(14,76,146,0.06); }

.score-block-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px;
}
.score-block-title {
    font-size: 13px; font-weight: 700; color: #1e293b;
    display: flex; align-items: center; gap: 8px;
}
.score-block-title i {
    width: 28px; height: 28px;
    background: rgba(14, 76, 146, 0.1);
    color: #0e4c92;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
}

/* Big score number input */
.score-number-wrap {
    display: flex; align-items: center; gap: 6px;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 4px 10px;
    transition: all 0.3s;
}
.score-number-wrap:focus-within {
    border-color: #0e4c92;
    box-shadow: 0 0 0 4px rgba(14, 76, 146, 0.1);
}
.score-number {
    width: 56px;
    border: none;
    outline: none;
    font-size: 22px;
    font-weight: 800;
    color: #0e4c92;
    text-align: center;
    background: transparent;
    font-family: inherit;
    -moz-appearance: textfield;
}
.score-number::-webkit-outer-spin-button,
.score-number::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.score-suffix { font-size: 14px; font-weight: 700; color: #94a3b8; }

/* Range slider */
.score-slider {
    width: 100%;
    height: 8px;
    -webkit-appearance: none;
    appearance: none;
    background: #e2e8f0;
    border-radius: 4px;
    outline: none;
    cursor: pointer;
    margin-top: 4px;
    background-image: linear-gradient(90deg, #0e4c92 0%, #4086e4 100%);
    background-repeat: no-repeat;
    background-size: var(--slider-fill, 70%) 100%;
}
.score-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 22px; height: 22px;
    border-radius: 50%;
    background: white;
    border: 3px solid #0e4c92;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(14, 76, 146, 0.3);
    transition: transform 0.2s;
}
.score-slider::-webkit-slider-thumb:hover { transform: scale(1.15); }
.score-slider::-moz-range-thumb {
    width: 22px; height: 22px;
    border-radius: 50%;
    background: white;
    border: 3px solid #0e4c92;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(14, 76, 146, 0.3);
}

.score-scale {
    display: flex; justify-content: space-between;
    margin-top: 6px;
    font-size: 10px; color: #94a3b8; font-weight: 600;
    letter-spacing: 0.3px;
}

.score-hint {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 8px;
    display: flex; align-items: center; gap: 5px;
}

/* Match progress bar */
.match-progress-container {
    margin-top: 10px;
    height: 10px;
    background: #e2e8f0;
    border-radius: 5px;
    overflow: hidden;
    position: relative;
}
.match-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 5px;
    transition: width 0.3s ease;
    position: relative;
}
.match-progress-fill::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 2s infinite;
}
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Notes textarea */
.notes-block {
    background: #f8fafd;
    border-radius: 16px;
    padding: 18px;
    margin-bottom: 18px;
    border: 1px solid #eef2f6;
}
.notes-block label {
    font-size: 13px; font-weight: 700; color: #1e293b;
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 10px;
}
.notes-block label i {
    width: 28px; height: 28px;
    background: rgba(14, 76, 146, 0.1);
    color: #0e4c92;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
}
.notes-block textarea {
    width: 100%;
    min-height: 90px;
    padding: 12px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 13.5px;
    font-family: inherit;
    resize: vertical;
    transition: all 0.3s;
    background: white;
    color: #1e293b;
}
.notes-block textarea:focus {
    outline: none;
    border-color: #0e4c92;
    box-shadow: 0 0 0 4px rgba(14, 76, 146, 0.1);
}

/* Result selector — big pill buttons */
.result-block { margin-bottom: 18px; }
.result-label {
    font-size: 13px; font-weight: 700; color: #1e293b;
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 12px;
}
.result-label i {
    width: 28px; height: 28px;
    background: rgba(14, 76, 146, 0.1);
    color: #0e4c92;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
}
.result-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}
.result-option { position: relative; cursor: pointer; }
.result-option input { position: absolute; opacity: 0; pointer-events: none; }
.result-option-inner {
    padding: 14px 10px;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    text-align: center;
    transition: all 0.3s;
    background: white;
}
.result-option-inner i {
    font-size: 20px;
    display: block;
    margin-bottom: 6px;
    color: #94a3b8;
    transition: all 0.3s;
}
.result-option-inner span {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    display: block;
    letter-spacing: 0.3px;
}
.result-option input:checked + .result-option-inner.pass {
    border-color: #27ae60;
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
    box-shadow: 0 6px 20px rgba(39, 174, 96, 0.2);
    transform: translateY(-2px);
}
.result-option input:checked + .result-option-inner.pass i,
.result-option input:checked + .result-option-inner.pass span { color: #27ae60; }

.result-option input:checked + .result-option-inner.fail {
    border-color: #e74c3c;
    background: linear-gradient(135deg, #fef2f2 0%, #fff5f5 100%);
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.2);
    transform: translateY(-2px);
}
.result-option input:checked + .result-option-inner.fail i,
.result-option input:checked + .result-option-inner.fail span { color: #e74c3c; }

.result-option input:checked + .result-option-inner.pending {
    border-color: #f39c12;
    background: linear-gradient(135deg, #fffbeb 0%, #fefce8 100%);
    box-shadow: 0 6px 20px rgba(243, 156, 18, 0.2);
    transform: translateY(-2px);
}
.result-option input:checked + .result-option-inner.pending i,
.result-option input:checked + .result-option-inner.pending span { color: #f39c12; }

.result-option-inner:hover { border-color: #0e4c92; }

/* Auto-update toggle */
.auto-update-block {
    background: linear-gradient(135deg, #f0f7ff 0%, #f8fafd 100%);
    border: 2px solid #dbeafe;
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
    transition: all 0.3s;
}
.auto-update-block:hover { border-color: #0e4c92; }
.auto-update-block input[type="checkbox"] {
    width: 20px; height: 20px;
    accent-color: #0e4c92;
    cursor: pointer;
    flex-shrink: 0;
}
.auto-update-block label {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    cursor: pointer;
    display: flex; align-items: center; gap: 8px;
    margin: 0;
}
.auto-update-block label i { color: #0e4c92; }

/* Previous evaluation notice */
.prev-eval {
    background: #f8fafd;
    border-radius: 12px;
    padding: 11px 14px;
    margin-bottom: 18px;
    font-size: 12.5px;
    color: #64748b;
    display: flex; align-items: center; gap: 8px;
    border: 1px dashed #e2e8f0;
}
.prev-eval i { color: #0e4c92; }

/* Modal footer */
.modal-footer {
    background: #f8fafd;
    padding: 18px 28px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    border-top: 1px solid #eef2f6;
    margin: 20px -28px 0;
}
.modal-footer .btn-secondary,
.modal-footer .btn-primary { padding: 11px 22px; font-size: 13.5px; }

@media (max-width: 768px) {
    .filter-grid { grid-template-columns: 1fr; }
    .applicant-hero { flex-direction: column; text-align: center; }
    .req-grid { grid-template-columns: 1fr; }
    .result-options { grid-template-columns: 1fr; }
    .modal-footer { flex-direction: column; }
    .modal-footer a, .modal-footer button { width: 100%; justify-content: center; }
    .modal-header h3 { font-size: 16px; }
}
</style>

<script>
function handleImageError(img) {
    if (img.getAttribute('data-error-handled') === 'true') return;
    img.setAttribute('data-error-handled', 'true');
    const initials = img.getAttribute('data-initials') || '?';
    const parent = img.parentNode;
    const fallback = document.createElement('div');
    fallback.className = 'applicant-avatar';
    fallback.textContent = initials;
    parent.replaceChild(fallback, img);
}
</script>

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-title">
        <i class="fas fa-clipboard-check"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div>
        <a href="?page=applicant&subpage=screening-evaluation&status=pending" class="btn-secondary btn-sm">Pending</a>
        <a href="?page=applicant&subpage=screening-evaluation&status=evaluated" class="btn-secondary btn-sm">Evaluated</a>
        <a href="?page=applicant&subpage=screening-evaluation&status=passed" class="btn-secondary btn-sm">Passed</a>
        <a href="?page=applicant&subpage=screening-evaluation&status=failed" class="btn-secondary btn-sm">Failed</a>
    </div>
</div>

<?php if (isset($success_message)): ?>
<div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if (isset($error_message)): ?>
<div class="alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid-unique">
    <div class="stat-card-unique">
        <div class="stat-icon-3d"><i class="fas fa-clock"></i></div>
        <div class="stat-content"><span class="stat-label">Pending Screening</span><span class="stat-value"><?php echo $stats['pending']; ?></span></div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content"><span class="stat-label">Evaluated</span><span class="stat-value"><?php echo $stats['evaluated']; ?></span></div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d"><i class="fas fa-check"></i></div>
        <div class="stat-content"><span class="stat-label">Passed</span><span class="stat-value"><?php echo $stats['passed']; ?></span></div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d"><i class="fas fa-times"></i></div>
        <div class="stat-content"><span class="stat-label">Failed</span><span class="stat-value"><?php echo $stats['failed']; ?></span></div>
    </div>
</div>

<!-- Department Statistics -->
<?php if (!empty($dept_stats)): ?>
<div class="dept-stats">
    <?php foreach ($dept_stats as $dept): ?>
    <div class="dept-card">
        <div class="dept-icon">
            <?php
            $icon = 'fa-building';
            if ($dept['department'] == 'transportation') $icon = 'fa-truck';
            elseif ($dept['department'] == 'warehouse') $icon = 'fa-warehouse';
            elseif ($dept['department'] == 'customer_service') $icon = 'fa-headset';
            elseif ($dept['department'] == 'sales') $icon = 'fa-chart-line';
            elseif ($dept['department'] == 'finance') $icon = 'fa-coins';
            elseif ($dept['department'] == 'hr') $icon = 'fa-users';
            elseif ($dept['department'] == 'it') $icon = 'fa-laptop-code';
            elseif ($dept['department'] == 'safety_security') $icon = 'fa-shield-alt';
            elseif ($dept['department'] == 'operations') $icon = 'fa-cogs';
            elseif ($dept['department'] == 'procurement') $icon = 'fa-shopping-cart';
            elseif ($dept['department'] == 'compliance') $icon = 'fa-gavel';
            elseif ($dept['department'] == 'administration') $icon = 'fa-file-alt';
            ?>
            <i class="fas <?php echo $icon; ?>"></i>
        </div>
        <div class="dept-info">
            <div class="dept-name"><?php echo ucwords(str_replace('_', ' ', $dept['department'])); ?></div>
            <div class="dept-progress"><div class="dept-progress-bar" style="width: <?php echo ($dept['evaluated'] / max($dept['total'], 1)) * 100; ?>%"></div></div>
            <div class="dept-stats-text">
                <span><i class="fas fa-check-circle" style="color: #27ae60;"></i> <?php echo $dept['evaluated']; ?></span>
                <span><i class="fas fa-clock" style="color: #f39c12;"></i> <?php echo $dept['pending']; ?></span>
                <span>Total: <?php echo $dept['total']; ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title"><i class="fas fa-filter"></i> Filter Applicants</div>
    <form method="GET">
        <input type="hidden" name="page" value="applicant">
        <input type="hidden" name="subpage" value="screening-evaluation">
        <div class="filter-grid">
            <div class="filter-item">
                <label>Screening Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Applicants</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Screening</option>
                    <option value="evaluated" <?php echo $status_filter == 'evaluated' ? 'selected' : ''; ?>>Evaluated</option>
                    <option value="passed" <?php echo $status_filter == 'passed' ? 'selected' : ''; ?>>Passed</option>
                    <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>
            <div class="filter-item">
                <label>Department</label>
                <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['department']; ?>" <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                        <?php echo ucwords(str_replace('_', ' ', $dept['department'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, Email, or Application #" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        <div class="filter-actions">
            <a href="?page=applicant&subpage=screening-evaluation" class="btn-secondary"><i class="fas fa-times"></i> Clear Filters</a>
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Apply Filters</button>
        </div>
    </form>
</div>

<!-- Applicants Table -->
<div class="table-container">
    <table class="unique-table">
        <thead>
            <tr>
                <th>Applicant</th>
                <th>Position</th>
                <th>Applied</th>
                <th>Screening Score</th>
                <th>Match %</th>
                <th>Status</th>
                <th>Next Step</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($applicants)): ?>
            <tr><td colspan="8" style="text-align: center; padding: 60px 20px; color: #95a5a6;">
                <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i><p>No applicants found</p>
            </td></tr>
            <?php else: ?>
                <?php foreach ($applicants as $applicant): 
                    $photoPath = getApplicantPhoto($applicant);
                    $firstName = $applicant['first_name'] ?? '';
                    $lastName = $applicant['last_name'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
                    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                    $needsAssess = requiresAssessment($applicant['department'] ?? '', $applicant['job_title'] ?? '');
                ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <?php if ($photoPath): ?>
                                <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="applicant-photo-medium" onerror="handleImageError(this)" data-initials="<?php echo $initials; ?>" loading="lazy">
                            <?php else: ?>
                                <div class="photo-fallback-medium"><?php echo $initials; ?></div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($fullName); ?></strong>
                                <div style="font-size: 11px; color: #64748b;">#<?php echo $applicant['application_number']; ?></div>
                                <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($applicant['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($applicant['job_title'])): ?>
                            <strong><?php echo htmlspecialchars($applicant['job_title']); ?></strong>
                            <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($applicant['job_code']); ?></div>
                            <div style="font-size: 11px; color: #64748b; text-transform: capitalize;"><?php echo ucwords(str_replace('_', ' ', $applicant['department'])); ?></div>
                        <?php else: ?>
                            <span style="color: #64748b;">General Application</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span><?php echo date('M d, Y', strtotime($applicant['applied_at'])); ?></span>
                        <div style="font-size: 11px; color: #64748b;"><?php echo timeAgo($applicant['applied_at']); ?></div>
                    </td>
                    <td>
                        <?php if ($applicant['screening_score'] !== null): ?>
                            <?php $score_class = 'score-high'; if ($applicant['screening_score'] < 40) $score_class = 'score-low'; elseif ($applicant['screening_score'] < 70) $score_class = 'score-medium'; ?>
                            <span class="score-badge <?php echo $score_class; ?>"><?php echo $applicant['screening_score']; ?>/100</span>
                        <?php else: ?><span style="color: #94a3b8;">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($applicant['qualification_match'] !== null): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-weight: 600;"><?php echo $applicant['qualification_match']; ?>%</span>
                                <div style="width: 50px; height: 6px; background: #eef2f6; border-radius: 3px;">
                                    <div style="width: <?php echo $applicant['qualification_match']; ?>%; height: 100%; background: linear-gradient(90deg, #0e4c92, #4086e4); border-radius: 3px;"></div>
                                </div>
                            </div>
                        <?php else: ?><span style="color: #94a3b8;">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($applicant['screening_status'] == 'Passed'): ?>
                            <span class="category-badge badge-success"><i class="fas fa-check-circle"></i> Passed</span>
                        <?php elseif ($applicant['screening_status'] == 'Failed'): ?>
                            <span class="category-badge badge-danger"><i class="fas fa-times-circle"></i> Failed</span>
                        <?php elseif ($applicant['screening_status'] == 'Evaluated'): ?>
                            <span class="category-badge badge-info"><i class="fas fa-check"></i> Evaluated</span>
                        <?php else: ?>
                            <span class="category-badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($applicant['screening_status'] == 'Passed'): ?>
                            <?php if ($needsAssess): ?>
                                <span class="category-badge badge-purple"><i class="fas fa-file-alt"></i> Assessment First</span>
                            <?php else: ?>
                                <span class="category-badge badge-info"><i class="fas fa-user-tie"></i> Direct to Interview</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #94a3b8;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=applicant&subpage=screening-evaluation&evaluate=<?php echo $applicant['id']; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?>" class="btn-primary btn-sm">
                            <i class="fas fa-clipboard-check"></i> <?php echo $applicant['evaluation_id'] ? 'Re-evaluate' : 'Evaluate'; ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ============================================================
     REDESIGNED EVALUATION MODAL
     ============================================================ -->
<?php if ($selected_applicant): 
    $needsAssess = requiresAssessment($selected_applicant['department'] ?? '', $selected_applicant['job_title'] ?? '');
    $assessmentType = getAssessmentType($selected_applicant['department'] ?? '');
    $closeUrl = '?page=applicant&subpage=screening-evaluation&status=' . $status_filter 
              . (!empty($search_filter) ? '&search=' . urlencode($search_filter) : '') 
              . (!empty($department_filter) ? '&department=' . $department_filter : '');
    $curScore = (int)($existing_evaluation['screening_score'] ?? 70);
    $curMatch = (int)($existing_evaluation['qualification_match'] ?? 70);
    $curResult = $existing_evaluation['screening_result'] ?? 'pending';
?>
<div id="evaluationModal" class="modal active">
    <div class="modal-content">
        <!-- Gradient header -->
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check"></i> Screening Evaluation</h3>
            <a href="<?php echo $closeUrl; ?>" class="modal-close">&times;</a>
        </div>

        <div class="modal-body">
            <!-- Applicant hero card -->
            <?php 
            $photoPath = getApplicantPhoto($selected_applicant);
            $firstName = $selected_applicant['first_name'] ?? '';
            $lastName = $selected_applicant['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
            ?>
            <div class="applicant-hero">
                <?php if ($photoPath): ?>
                    <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="applicant-avatar" onerror="handleImageError(this)" data-initials="<?php echo $initials; ?>" loading="lazy">
                <?php else: ?>
                    <div class="applicant-avatar"><?php echo $initials; ?></div>
                <?php endif; ?>
                <div class="applicant-details">
                    <h4><?php echo htmlspecialchars($fullName); ?></h4>
                    <div class="applicant-meta">
                        <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($selected_applicant['job_title'] ?? $selected_applicant['position_applied'] ?? 'General Application'); ?></span>
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($selected_applicant['email']); ?></span>
                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($selected_applicant['phone'] ?? 'N/A'); ?></span>
                        <span><i class="fas fa-hashtag"></i> <?php echo $selected_applicant['application_number']; ?></span>
                    </div>
                </div>
            </div>

            <!-- Assessment banner -->
            <div class="assessment-banner <?php echo $needsAssess ? 'required' : 'not-required'; ?>">
                <div class="assessment-banner-icon">
                    <i class="fas <?php echo $needsAssess ? 'fa-file-alt' : 'fa-user-tie'; ?>"></i>
                </div>
                <div>
                    <?php if ($needsAssess): ?>
                        <strong>Assessment Required for this Role</strong>
                        If passed, the applicant will be sent an email to take the <strong><?php echo htmlspecialchars($assessmentType); ?></strong> before the initial interview.
                    <?php else: ?>
                        <strong>No Assessment Required</strong>
                        If passed, the applicant will proceed directly to the initial interview.
                    <?php endif; ?>
                </div>
            </div>

            <!-- Job Requirements -->
            <?php if (!empty($selected_applicant['job_title'])): ?>
            <div class="req-section">
                <div class="req-section-title"><i class="fas fa-clipboard-list"></i> Job Requirements</div>
                <div class="req-grid">
                    <?php if (!empty($selected_applicant['experience_required'])): ?>
                    <div class="req-card"><div class="req-card-label">Experience</div><div class="req-card-value"><?php echo htmlspecialchars($selected_applicant['experience_required']); ?></div></div>
                    <?php endif; ?>
                    <?php if (!empty($selected_applicant['education_required'])): ?>
                    <div class="req-card"><div class="req-card-label">Education</div><div class="req-card-value"><?php echo htmlspecialchars($selected_applicant['education_required']); ?></div></div>
                    <?php endif; ?>
                    <?php if (!empty($selected_applicant['license_required'])): ?>
                    <div class="req-card"><div class="req-card-label">License</div><div class="req-card-value"><?php echo htmlspecialchars($selected_applicant['license_required']); ?></div></div>
                    <?php endif; ?>
                    <?php if (!empty($selected_applicant['skills'])): ?>
                    <div class="req-card"><div class="req-card-label">Applicant Skills</div><div class="req-card-value"><?php echo nl2br(htmlspecialchars($selected_applicant['skills'])); ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- FORM -->
            <form method="POST" id="evalForm">
                <input type="hidden" name="applicant_id" value="<?php echo $selected_applicant['id']; ?>">

                <!-- SCORE BLOCK -->
                <div class="score-block">
                    <div class="score-block-header">
                        <div class="score-block-title"><i class="fas fa-star"></i> Screening Score</div>
                        <div class="score-number-wrap">
                            <input type="number" class="score-number" id="screening_score_num" name="screening_score" 
                                   min="0" max="100" value="<?php echo $curScore; ?>" 
                                   oninput="syncScoreFromNumber()">
                            <span class="score-suffix">/100</span>
                        </div>
                    </div>
                    <input type="range" class="score-slider" id="screening_score_range" 
                           min="0" max="100" value="<?php echo $curScore; ?>" 
                           style="--slider-fill: <?php echo $curScore; ?>%"
                           oninput="syncScoreFromSlider()">
                    <div class="score-scale"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></div>
                    <div class="score-hint"><i class="fas fa-info-circle"></i> Drag the slider or type a number directly.</div>
                </div>

                <!-- MATCH BLOCK -->
                <div class="score-block">
                    <div class="score-block-header">
                        <div class="score-block-title"><i class="fas fa-chart-line"></i> Qualification Match</div>
                        <div class="score-number-wrap">
                            <input type="number" class="score-number" id="qualification_match_num" name="qualification_match" 
                                   min="0" max="100" value="<?php echo $curMatch; ?>" 
                                   oninput="syncMatchFromNumber()">
                            <span class="score-suffix">%</span>
                        </div>
                    </div>
                    <input type="range" class="score-slider" id="qualification_match_range" 
                           min="0" max="100" value="<?php echo $curMatch; ?>" 
                           style="--slider-fill: <?php echo $curMatch; ?>%"
                           oninput="syncMatchFromSlider()">
                    <div class="score-scale"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></div>
                    <div class="match-progress-container">
                        <div class="match-progress-fill" id="matchBar" style="width: <?php echo $curMatch; ?>%"></div>
                    </div>
                </div>

                <!-- NOTES -->
                <div class="notes-block">
                    <label><i class="fas fa-notes-medical"></i> Screening Notes</label>
                    <textarea name="screening_notes" placeholder="Enter your evaluation notes, observations, or remarks about this applicant..."><?php echo htmlspecialchars($existing_evaluation['screening_notes'] ?? ''); ?></textarea>
                </div>

                <!-- RESULT -->
                <div class="result-block">
                    <div class="result-label"><i class="fas fa-tasks"></i> Screening Result</div>
                    <div class="result-options">
                        <label class="result-option">
                            <input type="radio" name="screening_result" value="pass" <?php echo $curResult == 'pass' ? 'checked' : ''; ?> required>
                            <div class="result-option-inner pass">
                                <i class="fas fa-check-circle"></i>
                                <span>Pass</span>
                            </div>
                        </label>
                        <label class="result-option">
                            <input type="radio" name="screening_result" value="pending" <?php echo $curResult == 'pending' ? 'checked' : ''; ?>>
                            <div class="result-option-inner pending">
                                <i class="fas fa-clock"></i>
                                <span>Pending</span>
                            </div>
                        </label>
                        <label class="result-option">
                            <input type="radio" name="screening_result" value="fail" <?php echo $curResult == 'fail' ? 'checked' : ''; ?>>
                            <div class="result-option-inner fail">
                                <i class="fas fa-times-circle"></i>
                                <span>Fail</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- AUTO UPDATE -->
                <div class="auto-update-block">
                    <input type="checkbox" name="update_status" id="update_status" checked>
                    <label for="update_status">
                        <i class="fas fa-sync-alt"></i> Update applicant status automatically
                    </label>
                </div>

                <?php if ($existing_evaluation): ?>
                <div class="prev-eval">
                    <i class="fas fa-history"></i>
                    Previously evaluated by <strong><?php echo htmlspecialchars($existing_evaluation['evaluator_name'] ?? 'Unknown'); ?></strong>
                    on <?php echo date('F d, Y H:i', strtotime($existing_evaluation['evaluation_date'])); ?>
                </div>
                <?php endif; ?>

                <!-- FOOTER -->
                <div class="modal-footer">
                    <a href="<?php echo $closeUrl; ?>" class="btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    <a href="?page=applicant&subpage=applicant-profiles&id=<?php echo $selected_applicant['id']; ?>" class="btn-secondary"><i class="fas fa-user"></i> View Profile</a>
                    <button type="submit" name="save_evaluation" class="btn-primary"><i class="fas fa-save"></i> Save Evaluation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* ============================
   SCORE SYNC (dual input)
   ============================ */
const scoreNum   = document.getElementById('screening_score_num');
const scoreRange = document.getElementById('screening_score_range');
const matchNum   = document.getElementById('qualification_match_num');
const matchRange = document.getElementById('qualification_match_range');
const matchBar   = document.getElementById('matchBar');

function clamp(v) {
    v = parseInt(v);
    if (isNaN(v)) v = 0;
    return Math.max(0, Math.min(100, v));
}

/* SCREENING SCORE */
function syncScoreFromSlider() {
    const v = clamp(scoreRange.value);
    scoreNum.value = v;
    scoreRange.style.setProperty('--slider-fill', v + '%');
}
function syncScoreFromNumber() {
    const v = clamp(scoreNum.value);
    scoreRange.value = v;
    scoreRange.style.setProperty('--slider-fill', v + '%');
}
scoreRange.addEventListener('input', syncScoreFromSlider);
scoreNum.addEventListener('input', syncScoreFromNumber);
scoreNum.addEventListener('blur', function() {
    this.value = clamp(this.value);  // normalize on blur
    syncScoreFromNumber();
});

/* QUALIFICATION MATCH */
function syncMatchFromSlider() {
    const v = clamp(matchRange.value);
    matchNum.value = v;
    matchRange.style.setProperty('--slider-fill', v + '%');
    matchBar.style.width = v + '%';
}
function syncMatchFromNumber() {
    const v = clamp(matchNum.value);
    matchRange.value = v;
    matchRange.style.setProperty('--slider-fill', v + '%');
    matchBar.style.width = v + '%';
}
matchRange.addEventListener('input', syncMatchFromSlider);
matchNum.addEventListener('input', syncMatchFromNumber);
matchNum.addEventListener('blur', function() {
    this.value = clamp(this.value);
    syncMatchFromNumber();
});

/* Init fills on load */
syncScoreFromNumber();
syncMatchFromNumber();

/* Close modal when clicking outside */
window.addEventListener('click', function(e) {
    const modal = document.getElementById('evaluationModal');
    if (e.target === modal) {
        window.location.href = '<?php echo $closeUrl; ?>';
    }
});
</script>
<?php endif; ?>