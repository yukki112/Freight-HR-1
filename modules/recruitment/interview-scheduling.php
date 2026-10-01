<?php
// modules/recruitment/interview-scheduling.php
$page_title = "Interview Scheduling";

require_once 'config/mail_config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter   = isset($_GET['date']) ? $_GET['date'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

$fixed_meeting_links = [
    'initial'   => 'https://meet.google.com/dor-rpqx-ben',
    'technical' => 'https://meet.google.com/atz-arcu-zjf',
    'hr'        => 'https://meet.google.com/wvk-mzpy-ggw',
    'final'     => 'https://meet.google.com/syy-vbmr-mga'
];

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

function getInterviewerNames($pdo, $interviewer_ids) {
    if (empty($interviewer_ids)) return 'HR Team';
    $ids = is_array($interviewer_ids) ? $interviewer_ids : explode(',', $interviewer_ids);
    $ids = array_filter($ids);
    if (empty($ids)) return 'HR Team';
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $names ? implode(', ', $names) : 'HR Team';
}

function getApplicantPhoto($applicant) {
    if (!empty($applicant['photo_path']) && file_exists($applicant['photo_path'])) {
        return htmlspecialchars($applicant['photo_path']);
    }
    return null;
}

function roundLabel($round) {
    $map = [
        'initial'   => 'Initial Interview',
        'technical' => 'Technical Interview',
        'hr'        => 'HR Interview',
        'final'     => 'Final Interview',
    ];
    return $map[$round] ?? ucfirst($round);
}

// ============================================================
// SCHEDULE (SINGLE)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_single'])) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT ja.*, jp.title as position_title, jp.id as job_posting_id
            FROM job_applications ja
            LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
            WHERE ja.id = ?
        ");
        $stmt->execute([$_POST['applicant_id']]);
        $applicant = $stmt->fetch();

        if (!$applicant) throw new Exception("Applicant not found");

        $came_from_assessment = ($applicant['status'] === 'ready_for_interview');
        $interview_type  = $_POST['interview_type'];
        $interview_round = $_POST['interview_round'];

        $meeting_link = null;
        if ($interview_type === 'Online') {
            $meeting_link = $fixed_meeting_links[$interview_round] ?? '';
        }

        $location = null;
        if ($interview_type === 'Face-to-Face') {
            $location = $_POST['location'] ?? 'Main Office';
            if (empty($location)) throw new Exception("Location is required for face-to-face interviews");
        }

        $stmt = $pdo->prepare("
            INSERT INTO interviews (
                applicant_id, job_posting_id, interviewer_id, interview_date,
                interview_time, interview_type, interview_round, location, meeting_link,
                status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, ?)
        ");
        $stmt->execute([
            $_POST['applicant_id'],
            $applicant['job_posting_id'],
            $_POST['interviewer_id'] ?: null,
            $_POST['interview_date'],
            $_POST['interview_time'],
            $interview_type,
            $interview_round,
            $location,
            $meeting_link,
            $_POST['notes'] ?: null,
            $_SESSION['user_id']
        ]);

        $interview_id = $pdo->lastInsertId();

        $interview_data = [
            'applicant_name'  => $applicant['first_name'] . ' ' . $applicant['last_name'],
            'position'        => $applicant['position_title'] ?: 'General Application',
            'interview_round' => $interview_round,
            'interview_date'  => $_POST['interview_date'],
            'interview_time'  => $_POST['interview_time'],
            'interview_type'  => $interview_type,
            'interview_panel' => getInterviewerNames($pdo, $_POST['interviewer_id']),
            'location'        => $location ?: 'To be advised',
            'meeting_link'    => $meeting_link
        ];

        $email_result = sendInterviewEmail(
            $applicant['email'],
            $applicant['first_name'] . ' ' . $applicant['last_name'],
            $interview_data,
            $interview_id
        );

        $stmt = $pdo->prepare("
            INSERT INTO communication_log (applicant_id, communication_type, subject, message, sent_by, status)
            VALUES (?, 'email', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['applicant_id'],
            "Interview Schedule: {$applicant['position_title']}",
            "Interview scheduled on " . date('F d, Y', strtotime($_POST['interview_date'])) .
            " at " . date('h:i A', strtotime($_POST['interview_time'])),
            $_SESSION['user_id'],
            $email_result['success'] ? 'sent' : 'failed'
        ]);

        if ($applicant['status'] !== 'interviewed') {
            $stmt = $pdo->prepare("UPDATE job_applications SET status = 'interviewed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_POST['applicant_id']]);
        }

        $note = "[" . date('Y-m-d H:i') . "] Interview scheduled: {$interview_round} on " .
                date('F d, Y', strtotime($_POST['interview_date'])) .
                " at " . date('h:i A', strtotime($_POST['interview_time']));
        if ($came_from_assessment) $note .= " (via Assessment Passed)";
        if ($interview_type === 'Online' && $meeting_link) $note .= " - Link: {$meeting_link}";
        elseif ($interview_type === 'Face-to-Face' && $location) $note .= " - Location: {$location}";

        $stmt = $pdo->prepare("UPDATE job_applications SET notes = CONCAT(IFNULL(notes, ''), '\n', ?) WHERE id = ?");
        $stmt->execute([$note, $_POST['applicant_id']]);

        $pdo->commit();

        simpleLog($pdo, $_SESSION['user_id'], 'schedule_interview',
            "Scheduled {$interview_round} interview for applicant #{$_POST['applicant_id']}" .
            ($came_from_assessment ? " (assessment-passed)" : ""));

        $message = roundLabel($interview_round) . " scheduled successfully. ";
        $message .= $email_result['success']
            ? "Notification email sent to applicant."
            : "Warning: " . $email_result['message'];

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// SCHEDULE (BULK)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_bulk'])) {
    try {
        $selected        = $_POST['selected_candidates'] ?? [];
        $interview_date  = $_POST['bulk_interview_date'] ?? '';
        $interview_time  = $_POST['bulk_interview_time'] ?? '';
        $interview_round = $_POST['bulk_interview_round'] ?? 'initial';
        $interview_type  = $_POST['bulk_interview_type'] ?? 'Online';
        $interviewer_id  = $_POST['bulk_interviewer_id'] ?? null;
        $location        = $_POST['bulk_location'] ?? '';

        if (empty($selected)) throw new Exception("No candidates selected");
        if (empty($interview_date) || empty($interview_time)) throw new Exception("Interview date and time are required");
        if ($interview_type === 'Face-to-Face' && empty($location)) throw new Exception("Location is required for face-to-face interviews");

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
                $stmt = $pdo->prepare("
                    SELECT ja.*, jp.title as position_title, jp.id as job_posting_id
                    FROM job_applications ja
                    LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
                    WHERE ja.id = ?
                ");
                $stmt->execute([$applicant_id]);
                $applicant = $stmt->fetch();
                if (!$applicant) continue;

                $stmt = $pdo->prepare("
                    INSERT INTO interviews (
                        applicant_id, job_posting_id, interviewer_id, interview_date,
                        interview_time, interview_type, interview_round, location, meeting_link,
                        status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
                ");
                $stmt->execute([
                    $applicant_id,
                    $applicant['job_posting_id'],
                    $interviewer_id,
                    $interview_date,
                    $interview_time,
                    $interview_type,
                    $interview_round,
                    $location,
                    $meeting_link,
                    $_SESSION['user_id']
                ]);

                $interview_id = $pdo->lastInsertId();

                $interview_data = [
                    'applicant_name'  => $applicant['first_name'] . ' ' . $applicant['last_name'],
                    'position'        => $applicant['position_title'] ?: 'General Application',
                    'interview_round' => $interview_round,
                    'interview_date'  => $interview_date,
                    'interview_time'  => $interview_time,
                    'interview_type'  => $interview_type,
                    'interview_panel' => getInterviewerNames($pdo, $interviewer_id),
                    'location'        => $location ?: 'To be advised',
                    'meeting_link'    => $meeting_link
                ];

                $email_result = sendInterviewEmail(
                    $applicant['email'],
                    $applicant['first_name'] . ' ' . $applicant['last_name'],
                    $interview_data,
                    $interview_id
                );
                if ($email_result['success']) $email_success_count++;

                if ($applicant['status'] !== 'interviewed') {
                    $stmt = $pdo->prepare("UPDATE job_applications SET status = 'interviewed', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$applicant_id]);
                }

                $success_count++;
            } catch (Exception $e) {
                $failed_count++;
            }
        }

        $pdo->commit();

        $message = "Successfully scheduled {$success_count} interviews. ";
        $message .= "Emails sent to {$email_success_count} applicants.";
        if ($failed_count > 0) $message .= " Failed: {$failed_count}";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// RESCHEDULE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_interview'])) {
    try {
        $pdo->beginTransaction();

        $interview_id = (int)$_POST['interview_id'];
        if (!$interview_id) throw new Exception("Invalid interview ID");

        $stmt = $pdo->prepare("
            SELECT i.*, ja.first_name, ja.last_name, ja.email, jp.title as position_title
            FROM interviews i
            JOIN job_applications ja ON i.applicant_id = ja.id
            LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
            WHERE i.id = ?
        ");
        $stmt->execute([$interview_id]);
        $interview = $stmt->fetch();

        if (!$interview) throw new Exception("Interview not found");

        $interview_type  = $_POST['interview_type'];
        $interview_round = $_POST['interview_round'];

        $meeting_link = null;
        if ($interview_type === 'Online') {
            $meeting_link = $fixed_meeting_links[$interview_round] ?? $interview['meeting_link'];
        }

        $location = null;
        if ($interview_type === 'Face-to-Face') {
            $location = $_POST['location'] ?? $interview['location'];
            if (empty($location)) throw new Exception("Location is required for face-to-face interviews");
        }

        $stmt = $pdo->prepare("
            UPDATE interviews SET
                interview_date = ?,
                interview_time = ?,
                interview_type = ?,
                interview_round = ?,
                location = ?,
                meeting_link = ?,
                interviewer_id = ?,
                status = 'scheduled',
                notes = CONCAT(IFNULL(notes, ''), '\n[" . date('Y-m-d H:i') . "] Rescheduled')
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['interview_date'],
            $_POST['interview_time'],
            $interview_type,
            $interview_round,
            $location,
            $meeting_link,
            $_POST['interviewer_id'] ?: null,
            $interview_id
        ]);

        $interview_data = [
            'applicant_name'  => $interview['first_name'] . ' ' . $interview['last_name'],
            'position'        => $interview['position_title'],
            'interview_round' => $interview_round,
            'interview_date'  => $_POST['interview_date'],
            'interview_time'  => $_POST['interview_time'],
            'interview_type'  => $interview_type,
            'interview_panel' => getInterviewerNames($pdo, $_POST['interviewer_id']),
            'location'        => $location ?: 'To be advised',
            'meeting_link'    => $meeting_link
        ];

        $email_result = sendInterviewEmail(
            $interview['email'],
            $interview['first_name'] . ' ' . $interview['last_name'],
            $interview_data,
            $interview_id
        );

        $stmt = $pdo->prepare("
            INSERT INTO communication_log (applicant_id, communication_type, subject, message, sent_by, status)
            VALUES (?, 'email', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $interview['applicant_id'],
            "Interview Rescheduled: {$interview['position_title']}",
            "Rescheduled to " . date('F d, Y', strtotime($_POST['interview_date'])) .
            " at " . date('h:i A', strtotime($_POST['interview_time'])),
            $_SESSION['user_id'],
            $email_result['success'] ? 'sent' : 'failed'
        ]);

        $pdo->commit();

        simpleLog($pdo, $_SESSION['user_id'], 'reschedule_interview', "Rescheduled interview #{$interview_id}");

        $message = "Interview rescheduled successfully.";
        if (!$email_result['success']) $message .= " Warning: " . $email_result['message'];

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// CANCEL
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE interviews SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        $message = "Interview cancelled successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// FETCH DATA
// ============================================================

// Ready for Interview list
$stmt = $pdo->query("
    SELECT ja.*, jp.title as position_title, jp.department
    FROM job_applications ja
    LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
    WHERE ja.status IN ('shortlisted', 'ready_for_interview')
    ORDER BY 
        CASE WHEN ja.status = 'ready_for_interview' THEN 0 ELSE 1 END,
        ja.updated_at DESC
");
$shortlisted = $stmt->fetchAll();

// All interviews
$query = "
    SELECT 
        i.*,
        ja.first_name, ja.last_name, ja.email, ja.phone, ja.photo_path,
        jp.title as position_title, jp.job_code, jp.department,
        u.full_name as interviewer_name,
        u.email as interviewer_email
    FROM interviews i
    JOIN job_applications ja ON i.applicant_id = ja.id
    LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
    LEFT JOIN users u ON i.interviewer_id = u.id
    WHERE 1=1
";
$params = [];

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
    $term = "%$search_filter%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}
$query .= " ORDER BY i.interview_date ASC, i.interview_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$interviews = $stmt->fetchAll();

// Stats
$stats = [];
$stats['today']     = $pdo->query("SELECT COUNT(*) FROM interviews WHERE status = 'scheduled' AND interview_date = CURDATE()")->fetchColumn();
$stats['week']      = $pdo->query("SELECT COUNT(*) FROM interviews WHERE status = 'scheduled' AND interview_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$stats['scheduled'] = $pdo->query("SELECT COUNT(*) FROM interviews WHERE status = 'scheduled'")->fetchColumn();
$stats['completed'] = $pdo->query("SELECT COUNT(*) FROM interviews WHERE status = 'completed'")->fetchColumn();
$stats['avg_rating'] = round($pdo->query("SELECT AVG(rating) FROM interviews WHERE rating IS NOT NULL")->fetchColumn() ?: 0, 1);

$stmt = $pdo->query("
    SELECT id, full_name, role, email 
    FROM users 
    WHERE role IN ('admin', 'dispatcher', 'manager') 
    ORDER BY full_name
");
$interviewers = $stmt->fetchAll();

// Active = scheduled/ongoing AND today or future
$active_interviews = [];
$past_interviews = [];
foreach ($interviews as $i) {
    if (in_array($i['status'], ['scheduled', 'ongoing'], true) && $i['interview_date'] >= date('Y-m-d')) {
        $active_interviews[] = $i;
    } else {
        $past_interviews[] = $i;
    }
}

// Check which applicants have a FINAL round already
$final_scheduled_map = [];
$stmt = $pdo->query("
    SELECT DISTINCT applicant_id 
    FROM interviews 
    WHERE interview_round = 'final'
      AND status IN ('scheduled','ongoing','completed')
");
foreach ($stmt as $r) $final_scheduled_map[$r['applicant_id']] = true;

// Check which applicants have COMPLETED initial interview
$initial_completed_map = [];
$stmt = $pdo->query("
    SELECT DISTINCT applicant_id 
    FROM interviews 
    WHERE interview_round = 'initial' AND status = 'completed'
");
foreach ($stmt as $r) $initial_completed_map[$r['applicant_id']] = true;

// Applicants who completed initial but don't have final scheduled → READY FOR FINAL
$ready_for_final = [];
foreach ($initial_completed_map as $applicant_id => $_) {
    if (isset($final_scheduled_map[$applicant_id])) continue;

    $stmt = $pdo->prepare("
        SELECT ja.id, ja.first_name, ja.last_name, ja.email, ja.photo_path, ja.application_number,
               jp.title as position_title
        FROM job_applications ja
        LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
        WHERE ja.id = ?
          AND ja.status NOT IN ('rejected', 'hired')
    ");
    $stmt->execute([$applicant_id]);
    $rec = $stmt->fetch();
    if ($rec) {
        $ready_for_final[] = $rec;
    }
}
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
    transition: all 0.3s ease; border: 1px solid var(--border);
}
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px var(--primary-transparent-2); }
.stat-icon {
    width: 50px; height: 50px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 15px; display: flex; align-items: center; justify-content: center;
    font-size: 24px; color: white; box-shadow: 0 10px 20px var(--primary-transparent-2);
    flex-shrink: 0;
}
.stat-content { flex: 1; }
.stat-label { display: block; font-size: 13px; color: var(--gray); margin-bottom: 5px; font-weight: 500; }
.stat-value { display: block; font-size: 28px; font-weight: 700; color: var(--dark); line-height: 1.2; }
.stat-small { font-size: 12px; color: var(--gray); margin-top: 5px; display: flex; align-items: center; gap: 5px; }
.stat-small i { font-size: 12px; }

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
    font-size: 14px; transition: all 0.3s; background: white;
}
.filter-item input:focus, .filter-item select:focus {
    outline: none; border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}
.filter-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

.btn {
    padding: 12px 24px; border-radius: 12px; font-size: 14px; font-weight: 500;
    transition: all 0.3s ease; cursor: pointer;
    display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
    border: 1px solid transparent;
}
.btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px var(--primary-transparent-2); }
.btn-success { background: var(--success); color: white; }
.btn-success:hover { background: #219a52; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3); }
.btn-warning { background: var(--warning); color: white; }
.btn-warning:hover { background: #e67e22; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(243, 156, 18, 0.3); }
.btn-danger { background: var(--danger); color: white; }
.btn-danger:hover { background: #c0392b; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(231, 76, 60, 0.3); }
.btn-info { background: var(--info); color: white; }
.btn-info:hover { background: #2980b9; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3); }
.btn-outline { background: transparent; border: 1px solid var(--primary); color: var(--primary); }
.btn-outline:hover { background: var(--primary); color: white; }
.btn-sm { padding: 8px 16px; font-size: 13px; }
.btn-icon { padding: 10px; width: 42px; height: 42px; justify-content: center; border-radius: 10px; }
.btn:disabled { opacity: 0.35; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

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
    transition: all 0.3s;
    display: flex; flex-direction: column;
}
.interview-card.today { border-left: 4px solid var(--warning); background: linear-gradient(to right, #fff9e6, white); }
.interview-card.urgent { border-left: 4px solid var(--danger); background: linear-gradient(to right, #fee9e7, white); }
.interview-card.ongoing { border-left: 4px solid var(--info); background: linear-gradient(to right, #e8f4fd, white); }
.interview-card:hover { transform: translateY(-3px); box-shadow: 0 15px 30px var(--primary-transparent-2); }

.card-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; gap: 10px; }
.applicant-info { display: flex; align-items: center; gap: 10px; min-width: 0; }
.applicant-photo {
    width: 48px; height: 48px; border-radius: 12px; object-fit: cover;
    border: 2px solid white; box-shadow: 0 5px 15px var(--primary-transparent-2);
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 600; font-size: 16px; flex-shrink: 0;
}
.applicant-details { min-width: 0; }
.applicant-details h3 {
    font-size: 15px; font-weight: 600; color: var(--dark);
    margin: 0 0 3px 0;
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
.status-scheduled { background: rgba(52,152,219,0.13); color: var(--info); }
.status-completed { background: rgba(39,174,96,0.13); color: var(--success); }
.status-cancelled { background: rgba(231,76,60,0.13); color: var(--danger); }
.status-ongoing { background: rgba(52,152,219,0.13); color: var(--info); }

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

.card-footer {
    display: flex; gap: 4px; justify-content: flex-end;
    margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);
    flex-wrap: wrap;
}
.card-footer .btn { padding: 6px 10px; font-size: 11px; gap: 4px; }
.card-footer .btn i { font-size: 11px; }

.final-interview-banner {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: white;
    padding: 14px 16px;
    border-radius: 12px;
    margin: 10px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    font-weight: 600;
    box-shadow: 0 6px 15px rgba(39, 174, 96, 0.3);
}
.final-interview-banner i { font-size: 16px; }
.final-interview-banner span { flex: 1; }

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

.final-ready-list {
    background: linear-gradient(135deg, rgba(39,174,96,0.05) 0%, rgba(46,204,113,0.05) 100%);
    border: 2px dashed rgba(39,174,96,0.4);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 30px;
}
.final-ready-list .section-header h2 { color: var(--success); }
.final-ready-list .section-header h2 i { color: var(--success); }
.final-ready-item {
    background: white; border-radius: 15px; padding: 15px;
    display: flex; align-items: center; gap: 12px;
    transition: all 0.3s; border: 1px solid rgba(39,174,96,0.2);
    margin-bottom: 10px;
}
.final-ready-item:last-child { margin-bottom: 0; }
.final-ready-item:hover { transform: translateX(5px); border-color: var(--success); }
.final-ready-item .photo {
    width: 45px; height: 45px; border-radius: 12px;
    background: linear-gradient(135deg, var(--success) 0%, #2ecc71 100%);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 600; font-size: 16px; flex-shrink: 0;
    object-fit: cover;
}
.final-ready-item .info { flex: 1; min-width: 0; }
.final-ready-item .info h4 { font-size: 14px; font-weight: 600; color: var(--dark); margin: 0 0 3px; }
.final-ready-item .info p { font-size: 12px; color: var(--gray); margin: 0; }
.final-ready-item .info i { width: 12px; color: var(--success); }

.shortlisted-list {
    background: white; border-radius: 20px; padding: 20px;
    margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}
.list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
.list-header h2 {
    font-size: 18px; font-weight: 600; color: var(--dark); margin: 0;
    display: flex; align-items: center; gap: 8px;
}
.list-header h2 i { color: var(--primary); }
.shortlisted-items {
    display: flex; flex-direction: column; gap: 10px; max-height: 500px; overflow-y: auto;
}
.shortlisted-item {
    background: var(--light-gray); border-radius: 15px; padding: 15px;
    display: flex; align-items: center; gap: 12px;
    transition: all 0.3s; border: 1px solid var(--border);
}
.shortlisted-item:hover { transform: translateX(5px); background: white; border-color: var(--primary); }
.shortlisted-item.assessment-passed {
    background: linear-gradient(to right, #e8fff1, var(--light-gray));
    border-left: 4px solid var(--success);
}
.shortlisted-item .checkbox { width: 20px; height: 20px; accent-color: var(--primary); flex-shrink: 0; }
.shortlisted-item .photo {
    width: 45px; height: 45px; border-radius: 12px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 600; font-size: 16px; flex-shrink: 0;
    object-fit: cover;
}
.shortlisted-item .info { flex: 1; min-width: 0; }
.shortlisted-item .info h4 { font-size: 14px; font-weight: 600; color: var(--dark); margin: 0 0 3px; }
.shortlisted-item .info p { font-size: 12px; color: var(--gray); margin: 0; }
.shortlisted-item .info i { width: 12px; color: var(--primary); }
.shortlisted-item .badge {
    padding: 4px 10px; border-radius: 20px; font-size: 11px;
    background: var(--primary-transparent); color: var(--primary);
    font-weight: 500; display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap;
}

.past-section {
    background: white; border-radius: 20px; padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}
.past-section h2 {
    font-size: 18px; font-weight: 600; color: var(--dark); margin: 0 0 20px 0;
    display: flex; align-items: center; gap: 8px;
}
.past-section h2 i { color: var(--gray); }
.past-table { width: 100%; border-collapse: collapse; }
.past-table thead tr { background: var(--light-gray); }
.past-table th {
    padding: 14px; text-align: left; font-size: 12px;
    font-weight: 600; color: var(--gray); text-transform: uppercase; letter-spacing: 0.4px;
}
.past-table td { padding: 14px; border-bottom: 1px solid var(--border); font-size: 14px; }
.past-table tr:hover td { background: var(--light-gray); }

.modal {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5); z-index: 9999;
    align-items: center; justify-content: center; backdrop-filter: blur(5px);
}
.modal.active { display: flex; }
.modal-content {
    background: white; border-radius: 30px; padding: 30px;
    max-width: 600px; width: 90%; max-height: 85vh; overflow-y: auto;
    box-shadow: 0 30px 60px rgba(0,0,0,0.3);
    position: relative;
}
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid var(--border);
}
.modal-header h3 {
    font-size: 22px; font-weight: 600; color: var(--dark);
    margin: 0; display: flex; align-items: center; gap: 10px;
}
.modal-close {
    font-size: 28px; cursor: pointer; color: var(--gray);
    transition: all 0.3s; width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px; background: none; border: none;
}
.modal-close:hover { background: rgba(231,76,60,0.12); color: var(--danger); }

.form-group { margin-bottom: 20px; }
.form-group label {
    display: block; font-size: 13px; font-weight: 600;
    color: var(--dark); margin-bottom: 8px;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 14px; border: 1px solid var(--border);
    border-radius: 14px; font-size: 14px; transition: all 0.3s; background: white;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none; border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}
.form-group textarea { min-height: 100px; resize: vertical; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.modal-footer {
    display: flex; gap: 10px; justify-content: flex-end;
    margin-top: 25px; padding-top: 15px; border-top: 1px solid var(--border);
}

/* Inline alerts - NO ANIMATION, NO FLOAT */
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

@media (max-width: 768px) {
    .filter-grid { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: stretch; }
}
</style>

<!-- ==================== HTML ==================== -->

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

<div class="page-header">
    <div class="page-title">
        <i class="fas fa-calendar-alt"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-success btn-sm" onclick="showBulkScheduleModal()">
            <i class="fas fa-layer-group"></i> Bulk Schedule
        </button>
        <a href="?page=recruitment&subpage=assessment-management" class="btn btn-primary btn-sm">
            <i class="fas fa-file-signature"></i> Assessment Management
        </a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-content">
            <span class="stat-label">Today's Interviews</span>
            <span class="stat-value"><?php echo $stats['today']; ?></span>
            <div class="stat-small"><i class="fas fa-clock" style="color: var(--warning);"></i> <?php echo count($active_interviews); ?> active</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
        <div class="stat-content">
            <span class="stat-label">This Week</span>
            <span class="stat-value"><?php echo $stats['week']; ?></span>
            <div class="stat-small"><i class="fas fa-arrow-up" style="color: var(--success);"></i> Upcoming</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
            <span class="stat-label">Scheduled</span>
            <span class="stat-value"><?php echo $stats['scheduled']; ?></span>
            <div class="stat-small"><i class="fas fa-calendar-check"></i> Total pending</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
            <span class="stat-label">Completed</span>
            <span class="stat-value"><?php echo $stats['completed']; ?></span>
            <div class="stat-small"><i class="fas fa-star" style="color: var(--warning);"></i> Avg. Rating: <?php echo $stats['avg_rating']; ?>/10</div>
        </div>
    </div>
</div>

<div class="filter-section">
    <div class="filter-title"><i class="fas fa-filter"></i> Filter Interviews</div>
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
            <a href="?page=recruitment&subpage=interview-scheduling" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Apply Filters</button>
        </div>
    </form>
</div>

<!-- FOR FINAL INTERVIEW LIST -->
<?php if (!empty($ready_for_final)): ?>
<div class="final-ready-list">
    <div class="section-header">
        <h2>
            <i class="fas fa-flag-checkered"></i>
            For Final Interview
            <span class="badge-count" style="background: rgba(39,174,96,0.15); color: var(--success);"><?php echo count($ready_for_final); ?></span>
        </h2>
    </div>
    <?php foreach ($ready_for_final as $candidate):
        $firstName = $candidate['first_name'] ?? '';
        $lastName = $candidate['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
    ?>
    <div class="final-ready-item">
        <?php if (getApplicantPhoto($candidate)): ?>
            <img src="<?php echo getApplicantPhoto($candidate); ?>" alt="<?php echo htmlspecialchars($fullName); ?>" style="width: 45px; height: 45px; border-radius: 12px; object-fit: cover;">
        <?php else: ?>
            <div class="photo"><?php echo $initials; ?></div>
        <?php endif; ?>
        <div class="info">
            <h4><?php echo htmlspecialchars($fullName); ?></h4>
            <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($candidate['position_title'] ?: 'General Application'); ?></p>
        </div>
        <button type="button" class="btn btn-success btn-sm" onclick='scheduleNextRound(<?php echo htmlspecialchars(json_encode($candidate), ENT_QUOTES, "UTF-8"); ?>, "final")'>
            <i class="fas fa-flag-checkered"></i> Schedule Final Interview
        </button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- SCHEDULED INTERVIEWS — 3 CARDS PER PAGE -->
<div class="section-block">
    <div class="section-header">
        <h2>
            <i class="fas fa-calendar-check"></i>
            Scheduled Interviews
            <span class="badge-count"><?php echo count($active_interviews); ?></span>
        </h2>
    </div>

    <?php if (empty($active_interviews)): ?>
        <div style="text-align: center; padding: 50px 20px; color: var(--gray);">
            <i class="fas fa-calendar-times" style="font-size: 48px; opacity: 0.25; display: block; margin-bottom: 15px; color: var(--primary);"></i>
            <h3 style="margin: 0 0 8px; font-size: 18px; color: var(--dark);">No Scheduled Interviews</h3>
            <p style="margin: 0; font-size: 14px;">Schedule one from the "Ready for Interview" list below.</p>
        </div>
    <?php else: ?>
        <?php $per_page = 3; $pages = array_chunk($active_interviews, $per_page); ?>

        <?php foreach ($pages as $page_index => $page_items): ?>
        <div class="cards-page <?php echo $page_index === 0 ? 'active' : ''; ?>" data-page="<?php echo $page_index; ?>">
            <?php foreach ($page_items as $interview):
                $photoPath = getApplicantPhoto($interview);
                $firstName = $interview['first_name'] ?? '';
                $lastName = $interview['last_name'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
                $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                $isToday = ($interview['interview_date'] === date('Y-m-d'));
                $timeRemaining = strtotime($interview['interview_date'] . ' ' . $interview['interview_time']) - time();
                $isUrgent = $isToday && $timeRemaining > 0 && $timeRemaining < 3600;
                $round = $interview['interview_round'] ?: 'initial';
                $hasFinalAlready = !empty($final_scheduled_map[$interview['applicant_id']]);
            ?>
            <div class="interview-card <?php echo $isToday ? 'today' : ''; ?> <?php echo $isUrgent ? 'urgent' : ''; ?> <?php echo $interview['status'] === 'ongoing' ? 'ongoing' : ''; ?>">
                <div class="card-header">
                    <div class="applicant-info">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="applicant-photo">
                        <?php else: ?>
                            <div class="applicant-photo"><?php echo $initials; ?></div>
                        <?php endif; ?>
                        <div class="applicant-details">
                            <h3><?php echo htmlspecialchars($fullName); ?></h3>
                            <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($interview['position_title'] ?: 'General Application'); ?></p>
                        </div>
                    </div>
                    <span class="status-badge status-<?php echo htmlspecialchars($interview['status']); ?>">
                        <i class="fas fa-<?php echo $interview['status'] === 'ongoing' ? 'spinner' : ($isToday ? 'clock' : 'calendar'); ?>"></i>
                        <?php echo $isToday ? 'Today' : ucfirst($interview['status']); ?>
                    </span>
                </div>

                <div class="card-body">
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-tag"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Round</div>
                            <div class="detail-value"><?php echo htmlspecialchars(roundLabel($round)); ?></div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Date &amp; Time</div>
                            <div class="detail-value"><?php echo date('M d, Y', strtotime($interview['interview_date'])); ?> @ <?php echo date('h:i A', strtotime($interview['interview_time'])); ?></div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-user-tie"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Interviewer</div>
                            <div class="detail-value"><?php echo htmlspecialchars($interview['interviewer_name'] ?: 'HR Team'); ?></div>
                        </div>
                    </div>

                    <?php if ($round === 'initial' && $interview['status'] === 'completed' && !$hasFinalAlready): ?>
                    <div class="final-interview-banner">
                        <i class="fas fa-flag-checkered"></i>
                        <span>Initial interview done — ready for FINAL interview.</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer">
                    <?php if ($round === 'initial' && $interview['status'] === 'completed' && !$hasFinalAlready): ?>
                        <button type="button" class="btn btn-success btn-sm" onclick='scheduleNextRound(<?php echo htmlspecialchars(json_encode([
                            "id" => $interview["applicant_id"],
                            "first_name" => $interview["first_name"],
                            "last_name" => $interview["last_name"],
                            "position_title" => $interview["position_title"],
                        ]), ENT_QUOTES); ?>, "final")'>
                            <i class="fas fa-flag-checkered"></i> Final
                        </button>
                    <?php endif; ?>

                    <button type="button" class="btn btn-info btn-sm" onclick='viewInterview(<?php echo htmlspecialchars(json_encode($interview), ENT_QUOTES, "UTF-8"); ?>)'>
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button type="button" class="btn btn-warning btn-sm" onclick='rescheduleInterview(<?php echo htmlspecialchars(json_encode($interview), ENT_QUOTES, "UTF-8"); ?>)'>
                        <i class="fas fa-clock"></i> Resched
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="cancelInterview(<?php echo $interview['id']; ?>)">
                        <i class="fas fa-times"></i>
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

<!-- READY FOR INTERVIEW LIST -->
<?php if (!empty($shortlisted)): ?>
<div class="shortlisted-list">
    <div class="list-header">
        <h2><i class="fas fa-users"></i> Ready for Interview (<?php echo count($shortlisted); ?>)</h2>
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
                $isAssessmentPassed = ($candidate['status'] === 'ready_for_interview');
            ?>
            <div class="shortlisted-item <?php echo $isAssessmentPassed ? 'assessment-passed' : ''; ?>">
                <input type="checkbox" name="selected_candidates[]" value="<?php echo $candidate['id']; ?>" class="checkbox candidate-checkbox">
                <?php if (getApplicantPhoto($candidate)): ?>
                    <img src="<?php echo getApplicantPhoto($candidate); ?>" alt="<?php echo htmlspecialchars($fullName); ?>" style="width: 45px; height: 45px; border-radius: 12px; object-fit: cover;">
                <?php else: ?>
                    <div class="photo"><?php echo $initials; ?></div>
                <?php endif; ?>
                <div class="info">
                    <h4><?php echo htmlspecialchars($fullName); ?></h4>
                    <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($candidate['position_title'] ?: 'General Application'); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($candidate['email']); ?></p>
                </div>
                <?php if ($isAssessmentPassed): ?>
                    <span class="badge" style="background: rgba(39,174,96,0.13); color: var(--success);">
                        <i class="fas fa-check-circle"></i> Passed Assessment
                    </span>
                <?php else: ?>
                    <span class="badge"><i class="fas fa-calendar-plus"></i> For Initial</span>
                <?php endif; ?>
                <button type="button" class="btn btn-outline btn-sm" onclick='scheduleSingle(<?php echo htmlspecialchars(json_encode($candidate), ENT_QUOTES, "UTF-8"); ?>)'>
                    <i class="fas fa-calendar-plus"></i> Schedule
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- PAST INTERVIEWS -->
<?php if (!empty($past_interviews)): ?>
<div class="past-section">
    <h2><i class="fas fa-history"></i> Past Interviews (<?php echo count($past_interviews); ?>)</h2>
    <div style="overflow-x: auto;">
        <table class="past-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Applicant</th>
                    <th>Position</th>
                    <th>Round</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Rating</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($past_interviews as $interview):
                    $round = $interview['interview_round'] ?: 'initial';
                ?>
                <tr>
                    <td>
                        <?php echo date('M d, Y', strtotime($interview['interview_date'])); ?>
                        <br><small style="color: var(--gray);"><?php echo date('h:i A', strtotime($interview['interview_time'])); ?></small>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?></strong>
                        <br><small style="color: var(--gray);"><?php echo htmlspecialchars($interview['email']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($interview['position_title'] ?: 'General Application'); ?></td>
                    <td>
                        <span class="status-badge" style="padding: 4px 10px; background: rgba(155,89,182,0.13); color: var(--purple);">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars(roundLabel($round)); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $interview['meeting_link'] ? 'status-scheduled' : 'status-completed'; ?>" style="padding: 4px 10px;">
                            <i class="fas fa-<?php echo $interview['meeting_link'] ? 'video' : 'building'; ?>"></i>
                            <?php echo $interview['meeting_link'] ? 'Online' : 'Face-to-Face'; ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $status_class = 'status-scheduled';
                        if ($interview['status'] == 'completed') $status_class = 'status-completed';
                        if ($interview['status'] == 'cancelled') $status_class = 'status-cancelled';
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>" style="padding: 4px 10px;">
                            <i class="fas fa-<?php echo $interview['status'] === 'completed' ? 'check' : ($interview['status'] === 'cancelled' ? 'times' : 'clock'); ?>"></i>
                            <?php echo ucfirst($interview['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($interview['rating']): ?>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span style="font-weight: 600;"><?php echo $interview['rating']; ?>/10</span>
                        </div>
                        <?php else: ?>
                        <span style="color: var(--gray);">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-info btn-sm" onclick='viewInterview(<?php echo htmlspecialchars(json_encode($interview), ENT_QUOTES, "UTF-8"); ?>)'>
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

<!-- ==================== MODALS ==================== -->

<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="scheduleModalTitle"><i class="fas fa-calendar-plus" style="color: var(--primary);"></i> Schedule Interview</h3>
            <button type="button" class="modal-close" onclick="closeScheduleModal()">&times;</button>
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
            <div id="online_link_group" class="form-group" style="display: block;">
                <div style="background: var(--primary-transparent); border-radius: 12px; padding: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <i class="fas fa-video" style="color: var(--primary); font-size: 18px;"></i>
                        <span style="font-weight: 500; color: var(--primary);">Google Meet Link:</span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <input type="url" name="meeting_link" id="meeting_link" value="" style="flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: white;" readonly>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyFixedLink()" style="padding: 12px;">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <small style="color: var(--gray); margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> Fixed link for this round
                    </small>
                </div>
            </div>
            <div id="location_group" class="form-group" style="display: none;">
                <label>Location *</label>
                <input type="text" name="location" id="location_input" placeholder="e.g., Main Office, Room 201">
            </div>
            <div class="form-group">
                <label>Interview Panel</label>
                <select name="interviewer_id" id="interviewer_select">
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
                <textarea name="notes" id="schedule_notes" rows="3" placeholder="Additional instructions..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeScheduleModal()">Cancel</button>
                <button type="submit" id="scheduleSubmitBtn" name="schedule_single" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Schedule &amp; Send Email
                </button>
            </div>
        </form>
    </div>
</div>

<div id="bulkScheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group" style="color: var(--success);"></i> Bulk Schedule Interviews</h3>
            <button type="button" class="modal-close" onclick="closeBulkScheduleModal()">&times;</button>
        </div>
        <form method="POST" id="bulkForm">
            <div style="background: rgba(39,174,96,0.08); border-radius: 12px; padding: 15px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle" style="color: var(--success);"></i>
                    <p style="margin: 0; font-size: 14px;"><strong id="selectedCount">0</strong> candidate(s) selected</p>
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
            <div id="bulk_online_group" class="form-group">
                <div style="background: var(--primary-transparent); border-radius: 12px; padding: 15px;">
                    <p style="font-weight: 600; color: var(--primary); margin: 0 0 10px;">
                        <i class="fas fa-video"></i> Google Meet Link:
                    </p>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="bulk_meeting_link_display" style="flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 12px; background: white;" readonly>
                        <button type="button" class="btn btn-outline btn-sm" onclick="copyBulkLink()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div id="bulk_location_group" class="form-group" style="display: none;">
                <label>Location *</label>
                <input type="text" name="bulk_location" placeholder="e.g., Main Office, Room 201">
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

<div id="viewInterviewModal" class="modal">
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <h3><i class="fas fa-eye" style="color: var(--info);"></i> Interview Details</h3>
            <button type="button" class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div id="viewInterviewContent"></div>
    </div>
</div>

<!-- ==================== JAVASCRIPT ==================== -->
<script>
const fixedLinks = {
    'initial':   'https://meet.google.com/dor-rpqx-ben',
    'technical': 'https://meet.google.com/atz-arcu-zjf',
    'hr':        'https://meet.google.com/wvk-mzpy-ggw',
    'final':     'https://meet.google.com/syy-vbmr-mga'
};

const roundLabels = {
    'initial':   'Initial Interview',
    'technical': 'Technical Interview',
    'hr':        'HR Interview',
    'final':     'Final Interview'
};

/* ============ PAGINATION ============ */
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
    if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA' || document.activeElement.tagName === 'SELECT')) return;
    if (e.key === 'ArrowRight') pageNext();
    if (e.key === 'ArrowLeft') pagePrev();
});

/* ============ FIELD TOGGLING ============ */
function toggleInterviewFields() {
    const type = document.getElementById('schedule_type').value;
    const onlineGroup = document.getElementById('online_link_group');
    const locationGroup = document.getElementById('location_group');
    const locationInput = document.getElementById('location_input');
    const meetingLinkInput = document.getElementById('meeting_link');

    if (type === 'Online') {
        onlineGroup.style.display = 'block';
        locationGroup.style.display = 'none';
        locationInput.removeAttribute('required');
        updateMeetingLink();
    } else {
        onlineGroup.style.display = 'none';
        locationGroup.style.display = 'block';
        locationInput.setAttribute('required', 'required');
        meetingLinkInput.value = '';
    }
}

function updateMeetingLink() {
    const round = document.getElementById('interview_round_select').value;
    const meetingLinkInput = document.getElementById('meeting_link');
    const type = document.getElementById('schedule_type').value;
    meetingLinkInput.value = (type === 'Online') ? (fixedLinks[round] || '') : '';
}

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

function updateBulkMeetingLink() {
    const round = document.getElementById('bulk_interview_round_select').value;
    document.getElementById('bulk_meeting_link_display').value = fixedLinks[round] || '';
}
function updateBulkFields() { updateBulkMeetingLink(); }

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    });
}
function copyFixedLink() {
    const v = document.getElementById('meeting_link').value;
    if (v) copyToClipboard(v);
}
function copyBulkLink() {
    const v = document.getElementById('bulk_meeting_link_display').value;
    if (v) copyToClipboard(v);
}

function resetScheduleForm() {
    document.querySelectorAll('#scheduleForm input[data-dynamic="1"]').forEach(el => el.remove());
    const btn = document.getElementById('scheduleSubmitBtn');
    btn.name = 'schedule_single';
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Schedule &amp; Send Email';

    document.getElementById('scheduleModalTitle').innerHTML =
        '<i class="fas fa-calendar-plus" style="color: var(--primary);"></i> Schedule Interview';

    document.getElementById('scheduleForm').reset();
    document.getElementById('schedule_applicant_id').value = '';
    document.getElementById('schedule_notes').value = '';
    document.getElementById('schedule_date').value = '';
    document.getElementById('schedule_time').value = '';
    document.getElementById('interview_round_select').value = 'initial';
    document.getElementById('schedule_type').value = 'Online';
    document.getElementById('meeting_link').value = fixedLinks['initial'];
    document.getElementById('interviewer_select').value = '';
    document.getElementById('location_input').value = '';
}

function scheduleSingle(candidate) {
    resetScheduleForm();
    toggleInterviewFields();

    document.getElementById('schedule_applicant_id').value = candidate.id;
    document.getElementById('schedule_candidate_name').value =
        (candidate.first_name || '') + ' ' + (candidate.last_name || '');
    document.getElementById('schedule_position').value =
        candidate.position_title || 'General Application';

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('schedule_date').value = tomorrow.toISOString().split('T')[0];
    document.getElementById('schedule_time').value = '10:00';

    document.getElementById('interview_round_select').value = 'initial';
    updateMeetingLink();

    document.getElementById('scheduleModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function scheduleNextRound(candidate, nextRound) {
    resetScheduleForm();
    toggleInterviewFields();

    document.getElementById('schedule_applicant_id').value = candidate.id;
    document.getElementById('schedule_candidate_name').value =
        (candidate.first_name || '') + ' ' + (candidate.last_name || '');
    document.getElementById('schedule_position').value =
        candidate.position_title || 'General Application';

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 3);
    document.getElementById('schedule_date').value = tomorrow.toISOString().split('T')[0];
    document.getElementById('schedule_time').value = '10:00';

    document.getElementById('interview_round_select').value = nextRound;
    updateMeetingLink();

    document.getElementById('scheduleModalTitle').innerHTML =
        '<i class="fas fa-flag-checkered" style="color: var(--success);"></i> Schedule ' +
        (roundLabels[nextRound] || 'Interview');

    document.getElementById('scheduleModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function rescheduleInterview(interview) {
    resetScheduleForm();

    document.getElementById('schedule_applicant_id').value = interview.applicant_id;
    document.getElementById('schedule_candidate_name').value =
        (interview.first_name || '') + ' ' + (interview.last_name || '');
    document.getElementById('schedule_position').value =
        interview.position_title || 'General Application';

    document.getElementById('schedule_date').value = interview.interview_date || '';
    document.getElementById('schedule_time').value = interview.interview_time || '';

    const round = interview.interview_round || interview.interview_type || 'initial';
    document.getElementById('interview_round_select').value = round;

    const hasMeetingLink = interview.meeting_link && interview.meeting_link.trim() !== '';
    document.getElementById('schedule_type').value = hasMeetingLink ? 'Online' : 'Face-to-Face';
    toggleInterviewFields();

    if (hasMeetingLink) {
        document.getElementById('meeting_link').value = interview.meeting_link;
    } else {
        document.getElementById('location_input').value = interview.location || '';
    }
    if (interview.interviewer_id) {
        document.getElementById('interviewer_select').value = interview.interviewer_id;
    }
    document.getElementById('schedule_notes').value = interview.notes || '';

    const form = document.getElementById('scheduleForm');
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'interview_id';
    hidden.value = interview.id;
    hidden.setAttribute('data-dynamic', '1');
    form.appendChild(hidden);

    const btn = document.getElementById('scheduleSubmitBtn');
    btn.name = 'reschedule_interview';
    btn.innerHTML = '<i class="fas fa-clock"></i> Reschedule &amp; Send Email';

    document.getElementById('scheduleModalTitle').innerHTML =
        '<i class="fas fa-clock" style="color: var(--warning);"></i> Reschedule Interview';

    document.getElementById('scheduleModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.remove('active');
    document.body.style.overflow = '';
    resetScheduleForm();
}

function showBulkScheduleModal() {
    const checkboxes = document.querySelectorAll('.candidate-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Please select at least one candidate from the list above');
        return;
    }
    document.getElementById('selectedCount').textContent = checkboxes.length;
    document.getElementById('bulk_interview_type').value = 'Online';
    document.getElementById('bulk_interview_round_select').value = 'initial';
    toggleBulkFields();
    updateBulkMeetingLink();
    document.getElementById('bulkScheduleModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeBulkScheduleModal() {
    document.getElementById('bulkScheduleModal').classList.remove('active');
    document.body.style.overflow = '';
}
function confirmBulkSchedule() {
    const checkboxes = document.querySelectorAll('.candidate-checkbox:checked');
    const date = document.querySelector('input[name="bulk_interview_date"]').value;
    const time = document.querySelector('input[name="bulk_interview_time"]').value;
    const type = document.getElementById('bulk_interview_type').value;
    const location = document.querySelector('input[name="bulk_location"]')?.value;
    if (!date || !time) { alert('Please select interview date and time'); return false; }
    if (type === 'Face-to-Face' && !location) { alert('Please enter location for face-to-face interviews'); return false; }
    return confirm('Schedule ' + type + ' interviews for ' + checkboxes.length + ' candidate(s) on ' + date + ' at ' + time + '?');
}

function viewInterview(interview) {
    const statusColors = {
        'scheduled': 'var(--info)',
        'completed': 'var(--success)',
        'cancelled': 'var(--danger)',
        'ongoing':   'var(--info)'
    };
    const hasMeetingLink = interview.meeting_link && interview.meeting_link.trim() !== '';
    const interviewType = hasMeetingLink ? 'Online' : 'Face-to-Face';
    const round = interview.interview_round || interview.interview_type || 'initial';

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
                <div><p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Date</p><p style="font-weight: 500;">${new Date(interview.interview_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p></div>
                <div><p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Time</p><p style="font-weight: 500;">${new Date('1970-01-01T' + interview.interview_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}</p></div>
                <div><p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Round</p><p style="font-weight: 500;">${roundLabels[round] || round}</p></div>
                <div><p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Type</p><p>${interviewType}</p></div>
                <div><p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Status</p><span style="background: ${statusColors[interview.status] || 'var(--info)'}20; color: ${statusColors[interview.status] || 'var(--info)'}; padding: 5px 10px; border-radius: 20px; font-size: 12px;">${interview.status}</span></div>
                <div><p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Panel</p><p>${interview.interviewer_name || 'HR Team'}</p></div>
                <div><p style="font-size: 11px; color: var(--gray); margin-bottom: 5px;">Contact</p><p><a href="mailto:${interview.email}" style="color: var(--primary); text-decoration: none;">${interview.email}</a></p></div>
            </div>
        </div>
        ${hasMeetingLink ? `
        <div style="background: var(--primary-transparent); border-radius: 16px; padding: 15px; margin-bottom: 20px;">
            <p style="font-size: 12px; font-weight: 600; color: var(--primary); margin-bottom: 8px;">Google Meet Link</p>
            <div style="display: flex; gap: 10px;">
                <input type="text" value="${interview.meeting_link}" style="flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: white;" readonly>
                <button class="btn btn-outline btn-sm" onclick="copyToClipboard('${interview.meeting_link}')"><i class="fas fa-copy"></i></button>
                <a href="${interview.meeting_link}" target="_blank" class="btn btn-primary btn-sm"><i class="fas fa-external-link-alt"></i></a>
            </div>
        </div>
        ` : interview.location ? `
        <div style="background: var(--light-gray); border-radius: 16px; padding: 15px; margin-bottom: 20px;">
            <p style="font-size: 12px; font-weight: 600; color: var(--dark); margin-bottom: 5px;">Location</p>
            <p><i class="fas fa-map-marker-alt" style="color: var(--danger);"></i> ${interview.location}</p>
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
function closeViewModal() {
    document.getElementById('viewInterviewModal').classList.remove('active');
    document.body.style.overflow = '';
}

function cancelInterview(id) {
    if (confirm('Are you sure you want to cancel this interview?')) {
        window.location.href = '?page=recruitment&subpage=interview-scheduling&action=cancel&id=' + id;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    updatePagination();

    const round = document.getElementById('interview_round_select');
    if (round) round.addEventListener('change', updateMeetingLink);

    const bulkRound = document.getElementById('bulk_interview_round_select');
    if (bulkRound) bulkRound.addEventListener('change', updateBulkMeetingLink);
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeScheduleModal();
        closeBulkScheduleModal();
        closeViewModal();
    }
});

window.onclick = function (event) {
    const sm = document.getElementById('scheduleModal');
    const bm = document.getElementById('bulkScheduleModal');
    const vm = document.getElementById('viewInterviewModal');
    if (event.target === sm) closeScheduleModal();
    if (event.target === bm) closeBulkScheduleModal();
    if (event.target === vm) closeViewModal();
};
</script>