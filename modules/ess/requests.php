<?php
// /modules/ess/requests.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];
$message = '';

// Handle generic request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    try {
        $type  = $_POST['request_type'];
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $prio  = $_POST['priority'] ?? 'normal';

        $reqNo = 'REQ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $pdo->prepare("
            INSERT INTO ess_requests
            (request_number, employee_id, request_type, title, description, status, priority)
            VALUES (?, ?, ?, ?, ?, 'pending', ?)
        ")->execute([$reqNo, $empId, $type, $title, $desc, $prio]);

        $message = 'Request submitted successfully.';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}

// Filter
$filterStatus = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM ess_requests WHERE employee_id = ?";
$params = [$empId];
if ($filterStatus !== 'all') { $sql .= " AND status = ?"; $params[] = $filterStatus; }
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$counts = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM ess_requests WHERE employee_id = ? GROUP BY status");
$counts->execute([$empId]);
$counts = $counts->fetchAll(PDO::FETCH_KEY_PAIR);
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

.filter-chips { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.filter-chip { padding:8px 16px; border-radius:20px; font-size:12px; font-weight:600; color:#475569; background:#f1f5f9; text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:all 0.25s; }
.filter-chip:hover { background:#e2e8f0; }
.filter-chip.active { background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; box-shadow:0 6px 15px rgba(14,76,146,0.25); }
.filter-chip .cnt { background:rgba(0,0,0,0.08); padding:1px 7px; border-radius:10px; font-size:10px; font-weight:700; }
.filter-chip.active .cnt { background:rgba(255,255,255,0.25); }

.form-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:16px; }
.form-field { display:flex; flex-direction:column; gap:8px; }
.form-field label { font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.4px; }
.form-field input, .form-field textarea, .form-field select { padding:13px 16px; border:1.5px solid #e2e8f0; border-radius:12px; font-size:14px; font-family:inherit; background:#f8fafc; color:#1e293b; transition:all 0.25s; }
.form-field input:focus, .form-field textarea:focus, .form-field select:focus { outline:none; border-color:#0e4c92; background:white; box-shadow:0 0 0 4px rgba(14,76,146,0.08); }
.form-field textarea { min-height:80px; resize:vertical; }

.ess-btn { padding:13px 30px; border-radius:12px; font-size:14px; font-weight:700; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:10px; transition:all 0.25s; font-family:inherit; }
.ess-btn.primary { background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; box-shadow:0 6px 18px rgba(14,76,146,0.25); }
.ess-btn.primary:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(14,76,146,0.35); }

.req-list { display:flex; flex-direction:column; gap:12px; }
.req-item { padding:18px 20px; background:#f8fafd; border:1px solid #eef2f6; border-radius:14px; display:flex; flex-direction:column; gap:8px; }
.req-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.req-title { font-size:14.5px; font-weight:700; color:#1e293b; }
.req-num { font-family:'Courier New',monospace; font-size:11px; color:#0e4c92; background:#e0edff; padding:2px 8px; border-radius:6px; font-weight:700; }
.req-meta { font-size:12px; color:#64748b; display:flex; gap:12px; flex-wrap:wrap; }
.req-status { padding:4px 12px; border-radius:20px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
.req-status.pending  { background:#fef3c7; color:#92400e; }
.req-status.approved { background:#dcfce7; color:#166534; }
.req-status.rejected { background:#fee2e2; color:#991b1b; }
.req-status.processing { background:#dbeafe; color:#1e40af; }
.req-status.completed { background:#e0e7ff; color:#3730a3; }
.req-status.cancelled { background:#f1f5f9; color:#64748b; }

.ess-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.ess-empty i { font-size:40px; color:#cbd5e1; margin-bottom:12px; display:block; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-inbox"></i></div>
        <div>
            <h1>My Requests</h1>
            <p>Submit HR requests and track their status</p>
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
        <h3>Submit New Request</h3>
    </div>
    <div class="ess-panel-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-field">
                    <label>Request Type</label>
                    <select name="request_type" required>
                        <option value="leave">Leave Request</option>
                        <option value="overtime">Overtime</option>
                        <option value="timesheet">Timesheet</option>
                        <option value="schedule_change">Schedule Change</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="low">Low</option>
                        <option value="normal" selected>Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="form-field" style="margin-bottom:16px;">
                <label>Subject / Title</label>
                <input type="text" name="title" required placeholder="Brief title of your request">
            </div>
            <div class="form-field" style="margin-bottom:16px;">
                <label>Details</label>
                <textarea name="description" required placeholder="Describe your request in detail"></textarea>
            </div>
            <div style="text-align:right;">
                <button type="submit" name="submit_request" class="ess-btn primary">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-list"></i></div>
        <h3>Request History</h3>
    </div>
    <div class="ess-panel-body">
        <div class="filter-chips">
            <a href="?page=requests&subpage=requests&filter=all" class="filter-chip <?= $filterStatus === 'all' ? 'active' : '' ?>">
                All <span class="cnt"><?= array_sum($counts) ?></span>
            </a>
            <?php foreach (['pending','approved','rejected','completed'] as $s): ?>
            <a href="?page=requests&subpage=requests&filter=<?= $s ?>" class="filter-chip <?= $filterStatus === $s ? 'active' : '' ?>">
                <?= ucfirst($s) ?> <span class="cnt"><?= (int)($counts[$s] ?? 0) ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($requests)): ?>
        <div class="ess-empty">
            <i class="fas fa-inbox"></i>
            No requests found.
        </div>
        <?php else: ?>
        <div class="req-list">
            <?php foreach ($requests as $r): ?>
            <div class="req-item">
                <div class="req-head">
                    <div>
                        <div class="req-title"><?= htmlspecialchars($r['title']) ?></div>
                        <div class="req-meta" style="margin-top:6px;">
                            <span class="req-num">#<?= htmlspecialchars($r['request_number']) ?></span>
                            <span><i class="fas fa-tag"></i> <?= ucfirst(str_replace('_',' ',$r['request_type'])) ?></span>
                            <span><i class="fas fa-clock"></i> <?= date('M j, Y · g:i A', strtotime($r['created_at'])) ?></span>
                        </div>
                    </div>
                    <span class="req-status <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                </div>
                <?php if (!empty($r['description'])): ?>
                <div style="font-size:13px;color:#475569;line-height:1.6;padding:12px;background:white;border-radius:10px;">
                    <?= nl2br(htmlspecialchars($r['description'])) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($r['approval_notes'])): ?>
                <div style="margin-top:8px;padding:10px 14px;background:#eff6ff;border-left:3px solid #3b82f6;border-radius:8px;font-size:12px;color:#1e40af;">
                    <strong>HR Notes:</strong> <?= htmlspecialchars($r['approval_notes']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>