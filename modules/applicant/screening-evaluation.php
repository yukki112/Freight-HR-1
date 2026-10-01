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
} catch (Exception $e) {
    // Table might already exist
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_evaluation'])) {
    $applicant_id        = (int)$_POST['applicant_id'];
    $screening_score     = (int)$_POST['screening_score'];
    $qualification_match = (int)$_POST['qualification_match'];
    $screening_notes     = $_POST['screening_notes'] ?? '';
    $screening_result    = $_POST['screening_result'] ?? 'pending';
    $update_status       = isset($_POST['update_status']) ? 1 : 0;

    // Fetch applicant + job details (needed for email + assessment rule)
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

            // Update requires_assessment flag on job_applications
            $stmt = $pdo->prepare("UPDATE job_applications SET requires_assessment = ?, assessment_status = ? WHERE id = ?");
            $stmt->execute([
                $needsAssess ? 1 : 0,
                $needsAssess ? 'pending' : 'not_required',
                $applicant_id
            ]);

            $fullName = trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''));
            $emailResult = ['success' => false, 'message' => ''];

            if ($needsAssess) {
                // ---- Path A: Requires assessment ----
                $new_status = 'for_assessment';

                // Build assessment link (placeholder until assessment module is built)
                $assessmentType = getAssessmentType($department);
                $baseUrl        = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                                  . '://' . $_SERVER['HTTP_HOST'];
                $assessmentLink = $baseUrl . '/hr1/assessment.php?app=' . urlencode($applicant['application_number'])
                                  . '&token=' . urlencode(bin2hex(random_bytes(16)));

                // Send email via PHPMailer
                $emailResult = sendScreeningPassedWithAssessmentEmail(
                    $applicant['email'],
                    $fullName,
                    [
                        'application_number' => $applicant['application_number'],
                        'job_title'          => $jobTitle,
                        'department'         => $department,
                        'assessment_type'    => $assessmentType,
                        'assessment_link'    => $assessmentLink,
                    ]
                );

                // Log communication
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

                // Update assessment_sent_at
                if ($emailResult['success']) {
                    $stmt = $pdo->prepare("UPDATE job_applications SET assessment_sent_at = NOW() WHERE id = ?");
                    $stmt->execute([$applicant_id]);
                }

                $email_status = $emailResult['success']
                    ? "Assessment email sent to {$applicant['email']}."
                    : "⚠ Email failed: " . $emailResult['message'];
            } else {
                // ---- Path B: No assessment, go directly to interview ----
                $new_status = 'shortlisted';

                $emailResult = sendScreeningPassedNoAssessmentEmail(
                    $applicant['email'],
                    $fullName,
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

            // Update applicant status + note
            $stmt = $pdo->prepare("UPDATE job_applications SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $applicant_id]);

            $status_note = "[" . date('Y-m-d H:i') . "] Status updated to {$new_status} based on screening evaluation. "
                         . ($needsAssess ? "Assessment required ({$assessmentType})." : "No assessment needed.");
            $stmt = $pdo->prepare("UPDATE job_applications SET notes = CONCAT(IFNULL(notes, ''), '\n', ?) WHERE id = ?");
            $stmt->execute([$status_note, $applicant_id]);

            logActivity($pdo, $_SESSION['user_id'], 'update_applicant_status', "Updated applicant #{$applicant_id} status to {$new_status} via screening");

            $success_message = "Screening evaluation saved! " . ($email_status ?? '');
        }
        // ============================================================
        // IF FAILED -> Update status, no email
        // ============================================================
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
        jp.title as job_title,
        jp.job_code,
        jp.department,
        jp.experience_required,
        jp.education_required,
        jp.license_required,
        se.id as evaluation_id,
        se.screening_score,
        se.qualification_match,
        se.screening_notes,
        se.screening_result,
        se.evaluation_date,
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

// ============================================================
// HELPERS
// ============================================================
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
    --primary-transparent-2: rgba(14, 76, 146, 0.2);
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

.modal-applicant-photo { width: 70px; height: 70px; border-radius: 15px; object-fit: cover; border: 3px solid #fff; box-shadow: 0 5px 15px rgba(14, 76, 146, 0.3); background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 24px; }
.modal-photo-fallback { width: 70px; height: 70px; border-radius: 15px; background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 24px; box-shadow: 0 5px 15px rgba(14, 76, 146, 0.3); }

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

.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: white; border-radius: 20px; padding: 30px; max-width: 700px; width: 90%; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #eef2f6; }
.modal-header h3 { font-size: 20px; font-weight: 600; color: #2c3e50; margin: 0; }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; transition: color 0.3s; }
.modal-close:hover { color: #e74c3c; }

.modal-applicant-info { background: #f8fafd; border-radius: 15px; padding: 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px; }
.modal-details { flex: 1; }
.modal-details h4 { font-size: 18px; font-weight: 600; color: #2c3e50; margin: 0 0 5px 0; }
.modal-details p { margin: 3px 0; font-size: 14px; color: #64748b; }
.modal-details i { color: #0e4c92; width: 20px; }

.requirements-box { background: #f8fafd; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
.requirements-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px; }
.requirement-item { background: white; border-radius: 12px; padding: 12px; border: 1px solid #eef2f6; }
.requirement-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
.requirement-value { font-size: 14px; font-weight: 600; color: #2c3e50; }

.assessment-notice { background: #fff8e1; border-left: 4px solid #f39c12; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; color: #7a5c00; }
.assessment-notice.no-assess { background: #e8fff1; border-left-color: #27ae60; color: #155724; }
.assessment-notice strong { display: block; margin-bottom: 4px; }

.evaluation-form { margin-top: 20px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #2c3e50; margin-bottom: 5px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; transition: all 0.3s; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #0e4c92; box-shadow: 0 0 0 3px rgba(14, 76, 146, 0.1); }
.form-group textarea { min-height: 100px; resize: vertical; }
.checkbox-group { display: flex; align-items: center; gap: 10px; margin: 15px 0; }
.checkbox-group input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #0e4c92; }

.score-input-group { display: flex; align-items: center; gap: 10px; }
.score-input-group input[type="range"] { flex: 1; height: 6px; -webkit-appearance: none; background: #eef2f6; border-radius: 3px; }
.score-input-group input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; width: 20px; height: 20px; background: #0e4c92; border-radius: 50%; cursor: pointer; box-shadow: 0 2px 5px rgba(14, 76, 146, 0.3); }
.score-value { min-width: 50px; text-align: center; font-weight: 600; color: #0e4c92; }

.match-indicator { display: flex; align-items: center; gap: 15px; margin-top: 10px; }
.match-bar { flex: 1; height: 8px; background: #eef2f6; border-radius: 4px; overflow: hidden; }
.match-progress { height: 100%; background: linear-gradient(90deg, #0e4c92, #4086e4); border-radius: 4px; }

.modal-footer { margin-top: 25px; padding-top: 20px; border-top: 2px solid #eef2f6; display: flex; justify-content: flex-end; gap: 15px; flex-wrap: wrap; }

.img-error-fallback-medium, .modal-img-error-fallback { display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%); color: white; font-weight: 600; }
.img-error-fallback-medium { width: 45px; height: 45px; border-radius: 12px; font-size: 16px; }
.modal-img-error-fallback { width: 70px; height: 70px; border-radius: 15px; font-size: 24px; box-shadow: 0 5px 15px rgba(14, 76, 146, 0.3); }

@media (max-width: 768px) {
    .filter-grid { grid-template-columns: 1fr; }
    .modal-applicant-info { flex-direction: column; text-align: center; }
    .form-row { grid-template-columns: 1fr; }
    .requirements-grid { grid-template-columns: 1fr; }
    .modal-footer { flex-direction: column; }
    .modal-footer form, .modal-footer a, .modal-footer button { width: 100%; justify-content: center; }
}
</style>

<script>
function handleImageError(img) {
    if (img.getAttribute('data-error-handled') === 'true') return;
    img.setAttribute('data-error-handled', 'true');
    const initials = img.getAttribute('data-initials') || '?';
    const isModal = img.classList.contains('modal-applicant-photo');
    const parent = img.parentNode;
    const fallback = document.createElement('div');
    fallback.className = isModal ? 'modal-img-error-fallback' : 'img-error-fallback-medium';
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

<!-- Evaluation Modal -->
<?php if ($selected_applicant): 
    $needsAssess = requiresAssessment($selected_applicant['department'] ?? '', $selected_applicant['job_title'] ?? '');
    $assessmentType = getAssessmentType($selected_applicant['department'] ?? '');
?>
<div id="evaluationModal" class="modal active">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check" style="color: #0e4c92;"></i> Screening Evaluation</h3>
            <a href="?page=applicant&subpage=screening-evaluation&status=<?php echo $status_filter; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?>" class="modal-close">&times;</a>
        </div>

        <!-- Assessment notice -->
        <div class="assessment-notice <?php echo $needsAssess ? '' : 'no-assess'; ?>">
            <?php if ($needsAssess): ?>
                <strong><i class="fas fa-file-alt"></i> Assessment Required for this Role</strong>
                If passed, the applicant will be sent an email to take the <strong><?php echo htmlspecialchars($assessmentType); ?></strong> before the initial interview.
            <?php else: ?>
                <strong><i class="fas fa-user-tie"></i> No Assessment Required</strong>
                If passed, the applicant will proceed directly to the initial interview.
            <?php endif; ?>
        </div>

        <div class="modal-applicant-info">
            <?php 
            $photoPath = getApplicantPhoto($selected_applicant);
            $firstName = $selected_applicant['first_name'] ?? '';
            $lastName = $selected_applicant['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
            if ($photoPath): ?>
                <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="modal-applicant-photo" onerror="handleImageError(this)" data-initials="<?php echo $initials; ?>" loading="lazy">
            <?php else: ?>
                <div class="modal-photo-fallback"><?php echo $initials; ?></div>
            <?php endif; ?>
            <div class="modal-details">
                <h4><?php echo htmlspecialchars($fullName); ?></h4>
                <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($selected_applicant['job_title'] ?? $selected_applicant['position_applied'] ?? 'General Application'); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($selected_applicant['email']); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($selected_applicant['phone'] ?? 'N/A'); ?></p>
                <p><i class="fas fa-hashtag"></i> Application #: <?php echo $selected_applicant['application_number']; ?></p>
            </div>
        </div>

        <?php if (!empty($selected_applicant['job_title'])): ?>
        <div class="requirements-box">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                <i class="fas fa-clipboard-list" style="color: #0e4c92;"></i>
                <h4 style="margin: 0; font-size: 16px;">Job Requirements</h4>
            </div>
            <div class="requirements-grid">
                <?php if (!empty($selected_applicant['experience_required'])): ?>
                <div class="requirement-item"><div class="requirement-label">Experience</div><div class="requirement-value"><?php echo htmlspecialchars($selected_applicant['experience_required']); ?></div></div>
                <?php endif; ?>
                <?php if (!empty($selected_applicant['education_required'])): ?>
                <div class="requirement-item"><div class="requirement-label">Education</div><div class="requirement-value"><?php echo htmlspecialchars($selected_applicant['education_required']); ?></div></div>
                <?php endif; ?>
                <?php if (!empty($selected_applicant['license_required'])): ?>
                <div class="requirement-item"><div class="requirement-label">License/Certification</div><div class="requirement-value"><?php echo htmlspecialchars($selected_applicant['license_required']); ?></div></div>
                <?php endif; ?>
                <?php if (!empty($selected_applicant['skills'])): ?>
                <div class="requirement-item"><div class="requirement-label">Applicant Skills</div><div class="requirement-value"><?php echo nl2br(htmlspecialchars($selected_applicant['skills'])); ?></div></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="evaluation-form">
            <input type="hidden" name="applicant_id" value="<?php echo $selected_applicant['id']; ?>">

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-star" style="color: #0e4c92;"></i> Screening Score (0-100)</label>
                    <div class="score-input-group">
                        <input type="range" id="screening_score" name="screening_score" min="0" max="100" value="<?php echo $existing_evaluation['screening_score'] ?? 70; ?>" oninput="updateScore(this.value)">
                        <span class="score-value" id="score_display"><?php echo $existing_evaluation['screening_score'] ?? 70; ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-chart-line" style="color: #0e4c92;"></i> Qualification Match %</label>
                    <div class="score-input-group">
                        <input type="range" id="qualification_match" name="qualification_match" min="0" max="100" value="<?php echo $existing_evaluation['qualification_match'] ?? 70; ?>" oninput="updateMatch(this.value)">
                        <span class="score-value" id="match_display"><?php echo $existing_evaluation['qualification_match'] ?? 70; ?></span>
                    </div>
                </div>
            </div>

            <div class="match-indicator">
                <i class="fas fa-user-check" style="color: #0e4c92;"></i>
                <span>Qualification Match:</span>
                <div class="match-bar"><div class="match-progress" id="match_bar" style="width: <?php echo $existing_evaluation['qualification_match'] ?? 70; ?>%"></div></div>
                <span class="score-value" id="match_percent"><?php echo $existing_evaluation['qualification_match'] ?? 70; ?>%</span>
            </div>

            <div class="form-group">
                <label><i class="fas fa-notes-medical" style="color: #0e4c92;"></i> Screening Notes</label>
                <textarea name="screening_notes" placeholder="Enter your evaluation notes..."><?php echo htmlspecialchars($existing_evaluation['screening_notes'] ?? ''); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tasks" style="color: #0e4c92;"></i> Screening Result</label>
                    <select name="screening_result" required>
                        <option value="pass" <?php echo ($existing_evaluation['screening_result'] ?? '') == 'pass' ? 'selected' : ''; ?>>Pass - Qualified</option>
                        <option value="fail" <?php echo ($existing_evaluation['screening_result'] ?? '') == 'fail' ? 'selected' : ''; ?>>Fail - Not Qualified</option>
                        <option value="pending" <?php echo ($existing_evaluation['screening_result'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending - Need More Review</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-check" style="color: #0e4c92;"></i> Evaluation Date</label>
                    <input type="text" value="<?php echo date('F d, Y H:i'); ?>" readonly disabled style="background: #f8fafd;">
                </div>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" name="update_status" id="update_status" checked>
                <label for="update_status" style="font-weight: 500;">
                    <i class="fas fa-sync-alt" style="color: #0e4c92;"></i> Automatically update applicant status &amp; send email
                    <span style="font-size: 12px; color: #64748b; display: block; margin-top: 3px;">
                        <?php if ($needsAssess): ?>
                            (Pass → <strong>for_assessment</strong> + email with assessment link)
                        <?php else: ?>
                            (Pass → <strong>shortlisted</strong> + email for initial interview)
                        <?php endif; ?>
                    </span>
                </label>
            </div>

            <?php if ($existing_evaluation): ?>
            <div style="background: #f8fafd; border-radius: 10px; padding: 10px; margin: 15px 0; font-size: 13px; color: #64748b;">
                <i class="fas fa-history"></i> Previously evaluated by <?php echo htmlspecialchars($existing_evaluation['evaluator_name'] ?? 'Unknown'); ?> on <?php echo date('F d, Y H:i', strtotime($existing_evaluation['evaluation_date'])); ?>
            </div>
            <?php endif; ?>

            <div class="modal-footer">
                <a href="?page=applicant&subpage=screening-evaluation&status=<?php echo $status_filter; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?>" class="btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                <button type="submit" name="save_evaluation" class="btn-primary"><i class="fas fa-save"></i> Save Evaluation</button>
                <a href="?page=applicant&subpage=applicant-profiles&id=<?php echo $selected_applicant['id']; ?>" class="btn-secondary"><i class="fas fa-user"></i> View Full Profile</a>
            </div>
        </form>
    </div>
</div>

<script>
function updateScore(val) { document.getElementById('score_display').textContent = val; }
function updateMatch(val) {
    document.getElementById('match_display').textContent = val;
    document.getElementById('match_bar').style.width = val + '%';
    document.getElementById('match_percent').textContent = val + '%';
}
window.onclick = function(event) {
    const modal = document.getElementById('evaluationModal');
    if (event.target == modal) {
        window.location.href = '?page=applicant&subpage=screening-evaluation&status=<?php echo $status_filter; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?>';
    }
}
</script>
<?php endif; ?>