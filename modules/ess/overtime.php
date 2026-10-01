<?php
// /modules/ess/overtime.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_overtime'])) {
    try {
        $date  = $_POST['date'];
        $start = $_POST['start_time'];
        $end   = $_POST['end_time'];
        $reason = trim($_POST['reason'] ?? '');

        $hours = (strtotime($end) - strtotime($start)) / 3600;
        if ($hours <= 0) throw new Exception('End time must be after start time.');

        $pdo->prepare("
            INSERT INTO ess_overtime_requests
            (employee_id, date, start_time, end_time, total_hours, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ")->execute([$empId, $date, $start, $end, $hours, $reason]);

        $otId = $pdo->lastInsertId();

        $reqNo = 'OT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $pdo->prepare("
            INSERT INTO ess_requests
            (request_number, employee_id, request_type, title, description, request_data, status, priority)
            VALUES (?, ?, 'overtime', ?, ?, ?, 'pending', 'normal')
        ")->execute([
            $reqNo, $empId,
            "Overtime — " . date('M j, Y', strtotime($date)),
            $reason,
            json_encode(['ot_id' => $otId, 'hours' => $hours, 'start' => $start, 'end' => $end])
        ]);

        $message = 'Overtime request submitted for approval.';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}

$requests = $pdo->prepare("
    SELECT * FROM ess_overtime_requests
    WHERE employee_id = ?
    ORDER BY created_at DESC
");
$requests->execute([$empId]);
$requests = $requests->fetchAll();

$totalHours = array_sum(array_column(array_filter($requests, fn($r) => $r['status'] === 'approved'), 'total_hours'));
?>

<style>
.ess-page-header { background:linear-gradient(135deg,#0e4c92,#4086e4); border-radius:24px; padding:32px 36px; margin-bottom:25px; color:white; position:relative; overflow:hidden; box-shadow:0 20px 40px rgba(14,76,146,0.2); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; }
.ess-page-header::before { content:''; position:absolute; width:240px; height:240px; background:rgba(255,255,255,0.08); border-radius:50%; top:-100px; right:-60px; }
.ess-page-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.ess-page-header .hdr-icon { width:60px; height:60px; background:rgba(255,255,255,0.18); border:1.5px solid rgba(255,255,255,0.3); border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:26px; }
.ess-page-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; }
.ess-page-header p { font-size:13px; margin:0; opacity:0.85; }
.hours-badge { position:relative; z-index:1; background:rgba(255,255,255,0.15); border:1.5px solid rgba(255,255,255,0.3); border-radius:18px; padding:16px 26px; text-align:center; backdrop-filter:blur(10px); }
.hours-badge .num { font-size:32px; font-weight:800; }
.hours-badge .lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.6px; opacity:0.85; font-weight:600; }

.ess-panel { background:white; border-radius:22px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.05); border:1px solid #eef2f6; margin-bottom:22px; }
.ess-panel-head { padding:18px 24px; background:linear-gradient(135deg,#f8fafd 0%,#f1f5f9 100%); border-bottom:1px solid #eef2f6; display:flex; align-items:center; gap:14px; }
.ess-panel-head .ico { width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 6px 15px rgba(14,76,146,0.25); }
.ess-panel-head h3 { margin:0; font-size:15px; font-weight:700; color:#1e293b; }
.ess-panel-body { padding:24px; }

.ess-alert { padding:14px 20px; border-radius:14px; margin-bottom:20px; font-size:13px; font-weight:500; display:flex; align-items:center; gap:10px; }
.ess-alert.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.ess-alert.error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

.form-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:16px; }
.form-field { display:flex; flex-direction:column; gap:8px; }
.form-field label { font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.4px; }
.form-field input, .form-field textarea { padding:13px 16px; border:1.5px solid #e2e8f0; border-radius:12px; font-size:14px; font-family:inherit; background:#f8fafc; color:#1e293b; transition:all 0.25s; }
.form-field input:focus, .form-field textarea:focus { outline:none; border-color:#0e4c92; background:white; box-shadow:0 0 0 4px rgba(14,76,146,0.08); }
.form-field textarea { min-height:80px; resize:vertical; }

.ess-btn { padding:13px 30px; border-radius:12px; font-size:14px; font-weight:700; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:10px; transition:all 0.25s; font-family:inherit; }
.ess-btn.primary { background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; box-shadow:0 6px 18px rgba(14,76,146,0.25); }
.ess-btn.primary:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(14,76,146,0.35); }

.ot-list { display:flex; flex-direction:column; gap:12px; }
.ot-item { padding:18px 20px; background:#f8fafd; border:1px solid #eef2f6; border-radius:14px; }
.ot-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:8px; }
.ot-title { font-size:14.5px; font-weight:700; color:#1e293b; }
.ot-sub { font-size:12px; color:#64748b; margin-top:4px; }
.ot-hours { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; background:#dbeafe; color:#1e40af; border-radius:20px; font-size:12px; font-weight:700; margin-top:8px; }
.ot-status { padding:4px 12px; border-radius:20px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
.ot-status.pending  { background:#fef3c7; color:#92400e; }
.ot-status.approved { background:#dcfce7; color:#166534; }
.ot-status.rejected { background:#fee2e2; color:#991b1b; }
.ot-status.cancelled { background:#f1f5f9; color:#64748b; }
.ot-reason { font-size:13px; color:#475569; line-height:1.6; margin-top:10px; padding:12px; background:white; border-radius:10px; }

.ess-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.ess-empty i { font-size:40px; color:#cbd5e1; margin-bottom:12px; display:block; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <h1>Overtime Requests</h1>
            <p>Submit and track your overtime hours</p>
        </div>
    </div>
    <div class="hours-badge">
        <div class="num"><?= number_format($totalHours, 1) ?></div>
        <div class="lbl">Approved Hours</div>
    </div>
</div>

<?php if ($message): ?>
<div class="ess-alert <?= strpos($message, 'Error') === 0 ? 'error' : 'success' ?>">
    <i class="fas fa-<?= strpos($message, 'Error') === 0 ? 'circle-exclamation' : 'circle-check' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-plus"></i></div>
        <h3>File Overtime Request</h3>
    </div>
    <div class="ess-panel-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-field">
                    <label>Date</label>
                    <input type="date" name="date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-field">
                    <label>Start Time</label>
                    <input type="time" name="start_time" required>
                </div>
                <div class="form-field">
                    <label>End Time</label>
                    <input type="time" name="end_time" required>
                </div>
            </div>
            <div class="form-field" style="margin-bottom:16px;">
                <label>Reason / Justification</label>
                <textarea name="reason" required placeholder="Why do you need to work overtime?"></textarea>
            </div>
            <div style="text-align:right;">
                <button type="submit" name="file_overtime" class="ess-btn primary">
                    <i class="fas fa-paper-plane"></i> Submit Overtime Request
                </button>
            </div>
        </form>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-history"></i></div>
        <h3>My Overtime History</h3>
    </div>
    <div class="ess-panel-body">
        <?php if (empty($requests)): ?>
        <div class="ess-empty">
            <i class="fas fa-hourglass-half"></i>
            No overtime requests yet.
        </div>
        <?php else: ?>
        <div class="ot-list">
            <?php foreach ($requests as $r): ?>
            <div class="ot-item">
                <div class="ot-head">
                    <div>
                        <div class="ot-title"><?= date('l, F j, Y', strtotime($r['date'])) ?></div>
                        <div class="ot-sub">
                            <i class="fas fa-clock"></i>
                            <?= date('g:i A', strtotime($r['start_time'])) ?> → <?= date('g:i A', strtotime($r['end_time'])) ?>
                        </div>
                        <div class="ot-hours">
                            <i class="fas fa-hourglass-half"></i>
                            <?= number_format($r['total_hours'], 2) ?> hours
                        </div>
                    </div>
                    <span class="ot-status <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                </div>
                <?php if ($r['reason']): ?>
                <div class="ot-reason"><?= nl2br(htmlspecialchars($r['reason'])) ?></div>
                <?php endif; ?>
                <?php if ($r['status'] === 'rejected' && $r['rejection_reason']): ?>
                <div style="margin-top:10px;padding:10px 14px;background:#fef2f2;border-left:3px solid #dc2626;border-radius:8px;font-size:12px;color:#991b1b;">
                    <strong>Rejection Reason:</strong> <?= htmlspecialchars($r['rejection_reason']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>