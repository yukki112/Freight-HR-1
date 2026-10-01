<?php
// /modules/ess/timesheet.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_timesheet'])) {
    try {
        $date = $_POST['date'];
        $hours = (float)$_POST['hours'];
        $task = trim($_POST['task'] ?? '');

        // Insert into ess_requests as timesheet
        $reqNo = 'TS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $pdo->prepare("
            INSERT INTO ess_requests
            (request_number, employee_id, request_type, title, description, request_data, status, priority)
            VALUES (?, ?, 'timesheet', ?, ?, ?, 'pending', 'normal')
        ")->execute([
            $reqNo, $empId,
            "Timesheet Entry — " . date('M j, Y', strtotime($date)),
            $task,
            json_encode(['date' => $date, 'hours' => $hours, 'task' => $task])
        ]);
        $message = 'Timesheet entry submitted for approval.';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}

$entries = $pdo->prepare("
    SELECT * FROM ess_requests
    WHERE employee_id = ? AND request_type = 'timesheet'
    ORDER BY created_at DESC
");
$entries->execute([$empId]);
$entries = $entries->fetchAll();
?>

<style>
.ess-page-header { background:linear-gradient(135deg,#0e4c92,#4086e4); border-radius:24px; padding:32px 36px; margin-bottom:25px; color:white; position:relative; overflow:hidden; box-shadow:0 20px 40px rgba(14,76,146,0.2); }
.ess-page-header::before { content:''; position:absolute; width:240px; height:240px; background:rgba(255,255,255,0.08); border-radius:50%; top:-100px; right:-60px; }
.ess-page-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.ess-page-header .hdr-icon { width:60px; height:60px; background:rgba(255,255,255,0.18); border:1.5px solid rgba(255,255,255,0.3); border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:26px; }
.ess-page-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; }
.ess-page-header p { font-size:13px; margin:0; opacity:0.85; }

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

.ess-btn { padding:13px 30px; border-radius:12px; font-size:14px; font-weight:700; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:10px; transition:all 0.25s; font-family:inherit; }
.ess-btn.primary { background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; box-shadow:0 6px 18px rgba(14,76,146,0.25); }
.ess-btn.primary:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(14,76,146,0.35); }

.ts-list { display:flex; flex-direction:column; gap:12px; }
.ts-item { padding:18px 20px; background:#f8fafd; border:1px solid #eef2f6; border-radius:14px; }
.ts-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:8px; }
.ts-title { font-size:14.5px; font-weight:700; color:#1e293b; }
.ts-date { font-size:12px; color:#64748b; margin-top:4px; }
.ts-status { padding:4px 12px; border-radius:20px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
.ts-status.pending  { background:#fef3c7; color:#92400e; }
.ts-status.approved { background:#dcfce7; color:#166534; }
.ts-status.rejected { background:#fee2e2; color:#991b1b; }
.ts-desc { font-size:13px; color:#475569; line-height:1.6; margin-top:8px; padding:12px; background:white; border-radius:10px; }

.ess-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.ess-empty i { font-size:40px; color:#cbd5e1; margin-bottom:12px; display:block; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-clock"></i></div>
        <div>
            <h1>My Timesheet</h1>
            <p>Submit and track your daily work hours</p>
        </div>
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
        <h3>Submit New Entry</h3>
    </div>
    <div class="ess-panel-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-field">
                    <label>Date</label>
                    <input type="date" name="date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-field">
                    <label>Hours Worked</label>
                    <input type="number" name="hours" step="0.25" min="0.25" max="24" required placeholder="e.g. 8">
                </div>
            </div>
            <div class="form-field" style="margin-bottom:16px;">
                <label>Task Description</label>
                <textarea name="task" required placeholder="What did you work on?"></textarea>
            </div>
            <div style="text-align:right;">
                <button type="submit" name="submit_timesheet" class="ess-btn primary">
                    <i class="fas fa-paper-plane"></i> Submit Timesheet
                </button>
            </div>
        </form>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-history"></i></div>
        <h3>My Submitted Timesheets</h3>
    </div>
    <div class="ess-panel-body">
        <?php if (empty($entries)): ?>
        <div class="ess-empty">
            <i class="fas fa-clock"></i>
            No timesheet entries yet.
        </div>
        <?php else: ?>
        <div class="ts-list">
            <?php foreach ($entries as $e):
                $data = json_decode($e['request_data'] ?? '{}', true);
            ?>
            <div class="ts-item">
                <div class="ts-head">
                    <div>
                        <div class="ts-title"><?= htmlspecialchars($e['title']) ?></div>
                        <div class="ts-date">
                            <i class="fas fa-clock"></i> Submitted <?= date('M j, Y · g:i A', strtotime($e['created_at'])) ?>
                            <?php if (!empty($data['hours'])): ?>
                            · <strong><?= htmlspecialchars($data['hours']) ?> hours</strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="ts-status <?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span>
                </div>
                <?php if (!empty($e['description'])): ?>
                <div class="ts-desc"><?= nl2br(htmlspecialchars($e['description'])) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>