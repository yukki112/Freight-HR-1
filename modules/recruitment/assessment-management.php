<?php
// modules/recruitment/assessment-management.php
$page_title = "Assessment Management";

// ============================================================
// HANDLE: Push to Interview
// ============================================================
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['push_to_interview'])) {
    try {
        $applicant_id = (int) $_POST['applicant_id'];

        // Verify applicant passed assessment
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, status, assessment_status
            FROM job_applications
            WHERE id = ?
        ");
        $stmt->execute([$applicant_id]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            throw new Exception("Applicant not found.");
        }
        if ($app['assessment_status'] !== 'passed') {
            throw new Exception("Applicant has not passed the assessment.");
        }
        if ($app['status'] === 'ready_for_interview') {
            throw new Exception("Already marked for interview.");
        }

        // Update status
        $stmt = $pdo->prepare("
            UPDATE job_applications
            SET status = 'ready_for_interview', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$applicant_id]);

        // Append note
        $note = "\n[" . date('Y-m-d H:i') . "] Marked as READY FOR INTERVIEW by HR.";
        $pdo->prepare("UPDATE job_applications SET notes = CONCAT(IFNULL(notes,''), ?) WHERE id = ?")
            ->execute([$note, $applicant_id]);

        // Activity log
        if (function_exists('logActivity')) {
            logActivity(
                $pdo,
                $_SESSION['user_id'] ?? null,
                'push_to_interview',
                "Marked applicant #{$applicant_id} ({$app['first_name']} {$app['last_name']}) as ready for interview"
            );
        }

        $message = "✅ " . htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) .
                   " is now queued for interview scheduling.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ============================================================
// FETCH ASSESSMENTS
// ============================================================
$stmt = $pdo->query("
    SELECT 
        a.id, a.application_number, a.first_name, a.last_name, a.email, a.phone,
        a.status, a.assessment_status, a.requires_assessment, a.assessment_sent_at,
        a.photo_path,
        jp.title AS job_title, jp.department,
        ar.score_percentage, ar.correct_answers, ar.total_questions,
        ar.result AS assess_result, ar.started_at, ar.completed_at, ar.duration_minutes
    FROM job_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN assessment_results ar ON a.id = ar.applicant_id
    WHERE a.requires_assessment = 1 OR ar.id IS NOT NULL
    ORDER BY a.updated_at DESC
");
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total_pending  = 0;
$total_progress = 0;
$total_passed   = 0;
$total_failed   = 0;
$total_queued   = 0;
foreach ($assessments as $row) {
    if ($row['assessment_status'] === 'pending')          $total_pending++;
    elseif ($row['assessment_status'] === 'in_progress')  $total_progress++;
    elseif ($row['assessment_status'] === 'passed') {
        $total_passed++;
        if ($row['status'] === 'ready_for_interview')     $total_queued++;
    }
    elseif ($row['assessment_status'] === 'failed')       $total_failed++;
}
?>

<style>
.assess-header{background:#fff;border-radius:20px;padding:25px;margin-bottom:25px;box-shadow:0 10px 30px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center;}
.assess-header h1{font-size:22px;font-weight:600;color:#2c3e50;margin:0;display:flex;align-items:center;gap:12px;}
.assess-header h1 i{color:#0e4c92;background:rgba(14,76,146,0.1);padding:10px;border-radius:12px;font-size:22px;}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-bottom:25px;}
.stat-card{background:#fff;border-radius:18px;padding:20px;box-shadow:0 6px 20px rgba(0,0,0,0.05);display:flex;align-items:center;gap:15px;}
.stat-icon{width:50px;height:50px;border-radius:14px;background:linear-gradient(135deg,#0e4c92,#4086e4);display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;}
.stat-icon.warn{background:linear-gradient(135deg,#f39c12,#e67e22);}
.stat-icon.ok{background:linear-gradient(135deg,#27ae60,#2ecc71);}
.stat-icon.bad{background:linear-gradient(135deg,#e74c3c,#c0392b);}
.stat-icon.queued{background:linear-gradient(135deg,#9b59b6,#8e44ad);}
.stat-label{font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
.stat-value{font-size:24px;font-weight:700;color:#2c3e50;}
.table-wrap{background:#fff;border-radius:20px;padding:20px;box-shadow:0 10px 30px rgba(0,0,0,0.05);overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:14px;background:#f8fafd;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;}
td{padding:14px;border-bottom:1px solid #eef2f6;font-size:14px;}
tr:hover td{background:#f8fafd;}
.badge{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;display:inline-block;}
.b-pending{background:#f39c1220;color:#f39c12;}
.b-progress{background:#3498db20;color:#3498db;}
.b-passed{background:#27ae6020;color:#27ae60;}
.b-failed{background:#e74c3c20;color:#e74c3c;}
.b-na{background:#95a5a620;color:#7f8c8d;}
.b-ready{background:#9b59b620;color:#9b59b6;}
.avatar{width:42px;height:42px;border-radius:12px;object-fit:cover;background:linear-gradient(135deg,#0e4c92,#4086e4);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:15px;flex-shrink:0;}
.avatar img{width:100%;height:100%;border-radius:12px;object-fit:cover;}
.btn-action{background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff;padding:8px 14px;border-radius:10px;font-size:12px;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
.btn-action:hover{transform:translateY(-1px);box-shadow:0 6px 15px rgba(39,174,96,0.35);}
.btn-disabled{background:#e2e8f0;color:#94a3b8;padding:8px 14px;border-radius:10px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:6px;cursor:not-allowed;}
.alert-success{margin-bottom:20px;padding:15px 20px;background:#d4edda;color:#155724;border-radius:12px;border:1px solid #c3e6cb;display:flex;align-items:center;gap:10px;}
.alert-danger{margin-bottom:20px;padding:15px 20px;background:#f8d7da;color:#721c24;border-radius:12px;border:1px solid #f5c6cb;display:flex;align-items:center;gap:10px;}
</style>

<div class="assess-header">
    <h1><i class="fas fa-file-signature"></i> Assessment Management</h1>
    <div style="font-size:13px;color:#64748b;">Total: <strong><?php echo count($assessments); ?></strong> assessments</div>
</div>

<?php if ($message): ?>
<div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon warn"><i class="fas fa-clock"></i></div><div><div class="stat-label">Pending</div><div class="stat-value"><?php echo $total_pending; ?></div></div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-spinner"></i></div><div><div class="stat-label">In Progress</div><div class="stat-value"><?php echo $total_progress; ?></div></div></div>
    <div class="stat-card"><div class="stat-icon ok"><i class="fas fa-check"></i></div><div><div class="stat-label">Passed</div><div class="stat-value"><?php echo $total_passed; ?></div></div></div>
    <div class="stat-card"><div class="stat-icon queued"><i class="fas fa-user-check"></i></div><div><div class="stat-label">Ready for Interview</div><div class="stat-value"><?php echo $total_queued; ?></div></div></div>
    <div class="stat-card"><div class="stat-icon bad"><i class="fas fa-times"></i></div><div><div class="stat-label">Failed</div><div class="stat-value"><?php echo $total_failed; ?></div></div></div>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Applicant</th>
                <th>Position</th>
                <th>Email Sent</th>
                <th>Assessment</th>
                <th>Score</th>
                <th>Duration</th>
                <th>Completed</th>
                <th style="text-align:right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($assessments)): ?>
                <tr><td colspan="8" style="text-align:center;padding:50px;color:#95a5a6;">
                    <i class="fas fa-inbox" style="font-size:40px;opacity:0.3;display:block;margin-bottom:12px;"></i>
                    No assessments yet
                </td></tr>
            <?php else: foreach ($assessments as $a):
                $fn = $a['first_name'] ?? '';
                $ln = $a['last_name'] ?? '';
                $initials = strtoupper(substr($fn,0,1).substr($ln,0,1)) ?: '?';
                $photo = !empty($a['photo_path']) && file_exists(__DIR__.'/../../'.$a['photo_path']) ? $a['photo_path'] : null;

                $st = $a['assessment_status'] ?? 'not_required';
                $labels = [
                    'pending'      => ['Pending', 'b-pending'],
                    'in_progress'  => ['In Progress', 'b-progress'],
                    'passed'       => ['Passed', 'b-passed'],
                    'failed'       => ['Failed', 'b-failed'],
                    'not_required' => ['N/A', 'b-na'],
                ];
                [$label, $cls] = $labels[$st] ?? ['Unknown', 'b-na'];

                $isReady = ($a['status'] === 'ready_for_interview');
                if ($isReady) { $label = 'Ready for Interview'; $cls = 'b-ready'; }
            ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div class="avatar"><?php if($photo): ?><img src="<?php echo htmlspecialchars($photo); ?>"><?php else: echo $initials; endif; ?></div>
                            <div>
                                <strong><?php echo htmlspecialchars(trim($fn.' '.$ln)); ?></strong>
                                <div style="font-size:11px;color:#64748b;">#<?php echo htmlspecialchars($a['application_number']); ?></div>
                                <div style="font-size:11px;color:#64748b;"><?php echo htmlspecialchars($a['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($a['job_title'] ?? 'N/A'); ?></strong>
                        <div style="font-size:11px;color:#64748b;text-transform:capitalize;"><?php echo htmlspecialchars(str_replace('_',' ',$a['department'] ?? '')); ?></div>
                    </td>
                    <td>
                        <?php if ($a['assessment_sent_at']): ?>
                            <span style="font-size:13px;"><?php echo date('M d, Y', strtotime($a['assessment_sent_at'])); ?></span>
                            <div style="font-size:11px;color:#64748b;"><?php echo date('h:i A', strtotime($a['assessment_sent_at'])); ?></div>
                        <?php else: ?>
                            <span style="color:#94a3b8;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?php echo $cls; ?>"><?php echo $label; ?></span></td>
                    <td>
                        <?php if (!empty($a['score_percentage'])): ?>
                            <strong style="color:<?php echo $a['assess_result']==='passed'?'#27ae60':'#e74c3c'; ?>;">
                                <?php echo number_format((float)$a['score_percentage'],1); ?>%
                            </strong>
                            <div style="font-size:11px;color:#64748b;"><?php echo (int)$a['correct_answers'].'/'.(int)$a['total_questions']; ?> correct</div>
                        <?php else: ?>
                            <span style="color:#94a3b8;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($a['duration_minutes'])): ?>
                            <span><?php echo (int)$a['duration_minutes']; ?> min</span>
                        <?php else: ?>
                            <span style="color:#94a3b8;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($a['completed_at'])): ?>
                            <span style="font-size:13px;"><?php echo date('M d, Y', strtotime($a['completed_at'])); ?></span>
                            <div style="font-size:11px;color:#64748b;"><?php echo date('h:i A', strtotime($a['completed_at'])); ?></div>
                        <?php else: ?>
                            <span style="color:#94a3b8;">Not yet</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <?php if ($a['assessment_status'] === 'passed' && !$isReady): ?>
                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Move <?php echo htmlspecialchars($fn.' '.$ln, ENT_QUOTES); ?> to the interview scheduling queue?');">
                                <input type="hidden" name="applicant_id" value="<?php echo (int)$a['id']; ?>">
                                <button type="submit" name="push_to_interview" class="btn-action">
                                    <i class="fas fa-calendar-plus"></i> For Interview
                                </button>
                            </form>
                        <?php elseif ($isReady): ?>
                            <span class="btn-disabled">
                                <i class="fas fa-check-circle"></i> Queued
                            </span>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>