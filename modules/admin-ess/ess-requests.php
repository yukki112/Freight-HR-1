<?php
// /modules/admin-ess/ess-requests.php
$message = '';
$error = '';

// ---- APPROVE / REJECT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request'])) {
    try {
        $id     = (int)$_POST['request_id'];
        $action = $_POST['action_request'];
        $notes  = trim($_POST['approval_notes'] ?? '');
        $status = $action === 'approve' ? 'approved' : 'rejected';

        $pdo->prepare("
            UPDATE ess_requests
            SET status = ?, approved_by = ?, approved_at = NOW(), approval_notes = ?
            WHERE id = ?
        ")->execute([$status, $_SESSION['user_id'] ?? null, $notes ?: null, $id]);

        // Notify employee
        $empId = $pdo->prepare("SELECT employee_id FROM ess_requests WHERE id = ?");
        $empId->execute([$id]);
        $employeeId = $empId->fetchColumn();

        if ($employeeId) {
            $pdo->prepare("
                INSERT INTO ess_notifications (employee_id, title, message, type, module, reference_id)
                VALUES (?, ?, ?, ?, 'requests', ?)
            ")->execute([
                $employeeId,
                $action === 'approve' ? 'Request Approved' : 'Request Rejected',
                $action === 'approve' ? 'Your request has been approved.' : ('Your request has been rejected.' . ($notes ? ' Reason: ' . $notes : '')),
                $action === 'approve' ? 'success' : 'danger',
                $id
            ]);
        }

        $message = "Request {$status} successfully.";
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// ---- MARK COMPLETED ----
if (isset($_GET['complete'])) {
    try {
        $pdo->prepare("UPDATE ess_requests SET status = 'completed', completed_at = NOW() WHERE id = ?")
            ->execute([(int)$_GET['complete']]);
        $message = 'Request marked as completed.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// ---- FILTERS ----
$filterStatus = $_GET['filter'] ?? 'pending';
$filterType   = $_GET['type'] ?? 'all';

$where = [];
$params = [];
if ($filterStatus !== 'all') { $where[] = "r.status = ?"; $params[] = $filterStatus; }
if ($filterType !== 'all')   { $where[] = "r.request_type = ?"; $params[] = $filterType; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT r.*, nh.employee_id AS emp_code, nh.position, nh.department,
           ja.first_name, ja.last_name
    FROM ess_requests r
    JOIN new_hires nh        ON r.employee_id = nh.id
    JOIN job_applications ja ON nh.applicant_id = ja.id
    {$whereSql}
    ORDER BY
        CASE r.priority
            WHEN 'urgent' THEN 1 WHEN 'high' THEN 2
            WHEN 'normal' THEN 3 WHEN 'low' THEN 4
        END,
        r.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

$counts = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM ess_requests GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$typeCounts = $pdo->query("
    SELECT request_type, COUNT(*) AS cnt FROM ess_requests GROUP BY request_type
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<style>
/* ==========================================================
   ESS REQUESTS — REDESIGNED
   ========================================================== */
.erq-header {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 24px; padding: 32px 36px; margin-bottom: 25px;
    color: white; position: relative; overflow: hidden;
    box-shadow: 0 20px 40px rgba(14,76,146,0.2);
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 20px;
}
.erq-header::before {
    content:''; position:absolute; width:240px; height:240px;
    background: rgba(255,255,255,0.08); border-radius:50%;
    top:-100px; right:-60px;
}
.erq-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.erq-header .hdr-icon {
    width:60px; height:60px; background: rgba(255,255,255,0.18);
    border:1.5px solid rgba(255,255,255,0.3); border-radius:18px;
    display:flex; align-items:center; justify-content:center;
    font-size:26px; backdrop-filter: blur(10px);
}
.erq-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; letter-spacing:-0.3px; }
.erq-header p  { font-size:13px; margin:0; opacity:0.85; }

/* Stats */
.erq-stats {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
    gap:14px; margin-bottom:22px;
}
.erq-stat {
    background:white; border-radius:18px; padding:18px 20px;
    display:flex; align-items:center; gap:14px;
    box-shadow:0 6px 20px rgba(0,0,0,0.04);
    border:1px solid #eef2f6; transition:all 0.3s;
}
.erq-stat:hover { transform:translateY(-3px); box-shadow:0 12px 28px rgba(14,76,146,0.08); }
.erq-stat .ico {
    width:44px; height:44px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; flex-shrink:0;
}
.erq-stat .ico.amber  { background:rgba(245,158,11,0.12);color:#d97706; }
.erq-stat .ico.green  { background:rgba(22,163,74,0.12); color:#16a34a; }
.erq-stat .ico.red    { background:rgba(220,38,38,0.12); color:#dc2626; }
.erq-stat .ico.blue   { background:rgba(14,76,146,0.1);  color:#0e4c92; }
.erq-stat .ico.purple { background:rgba(139,92,246,0.12);color:#7c3aed; }
.erq-stat .num { font-size:22px; font-weight:700; color:#1e293b; line-height:1.1; }
.erq-stat .lbl { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.6px; font-weight:600; margin-top:2px; }

/* Filters */
.erq-filter-panel {
    background:white; border-radius:20px; padding:22px 26px;
    box-shadow:0 10px 30px rgba(0,0,0,0.04);
    border:1px solid #eef2f6; margin-bottom:20px;
}
.erq-filter-title {
    font-size:11px; font-weight:700; color:#94a3b8;
    text-transform:uppercase; letter-spacing:0.6px;
    margin-bottom:10px; display:flex; align-items:center; gap:6px;
}
.erq-filter-row { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.erq-filter-row:last-child { margin-bottom:0; }
.erq-chip {
    padding:8px 16px; border-radius:20px; font-size:12px; font-weight:600;
    color:#475569; background:#f1f5f9; text-decoration:none;
    display:inline-flex; align-items:center; gap:8px;
    transition:all 0.25s; border:1.5px solid transparent;
}
.erq-chip:hover { background:#e2e8f0; }
.erq-chip.active {
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; box-shadow:0 6px 15px rgba(14,76,146,0.25);
}
.erq-chip .cnt {
    background:rgba(0,0,0,0.08); padding:1px 7px;
    border-radius:10px; font-size:10px; font-weight:700;
}
.erq-chip.active .cnt { background:rgba(255,255,255,0.25); }

/* Request cards */
.erq-list { display:flex; flex-direction:column; gap:14px; }
.erq-card {
    background:white; border-radius:18px;
    padding:22px 24px; border:1px solid #eef2f6;
    box-shadow:0 8px 24px rgba(0,0,0,0.04);
    transition:all 0.3s; position:relative; overflow:hidden;
}
.erq-card:hover { transform:translateY(-2px); box-shadow:0 14px 34px rgba(14,76,146,0.08); }
.erq-card::before {
    content:''; position:absolute; left:0; top:0; bottom:0;
    width:4px; background:#0e4c92;
}
.erq-card.priority-urgent::before { background:#dc2626; }
.erq-card.priority-high::before   { background:#f59e0b; }
.erq-card.priority-normal::before { background:#0e4c92; }
.erq-card.priority-low::before    { background:#94a3b8; }

.erq-card-head {
    display:flex; justify-content:space-between;
    align-items:flex-start; gap:15px; margin-bottom:12px;
}
.erq-title { font-size:17px; font-weight:700; color:#0f172a; margin:0 0 6px; }
.erq-meta {
    display:flex; align-items:center; gap:12px;
    font-size:12px; color:#64748b; flex-wrap:wrap;
}
.erq-meta strong { color:#334155; font-weight:600; }
.erq-meta i { color:#cbd5e1; margin-right:3px; }
.erq-number {
    font-family:'Courier New', monospace;
    font-size:11px; color:#0e4c92;
    background:#e0edff; padding:3px 9px;
    border-radius:6px; font-weight:700;
}

.erq-badges { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
.erq-badge {
    padding:4px 11px; border-radius:20px;
    font-size:10.5px; font-weight:700;
    text-transform:uppercase; letter-spacing:0.4px;
    display:inline-flex; align-items:center; gap:4px;
}
.erq-badge.priority-urgent { background:#dc2626; color:white; }
.erq-badge.priority-high   { background:#f59e0b; color:white; }
.erq-badge.priority-normal { background:#dbeafe; color:#1e40af; }
.erq-badge.priority-low    { background:#f1f5f9; color:#64748b; }
.erq-badge.status-pending    { background:#fef3c7; color:#92400e; }
.erq-badge.status-approved   { background:#dcfce7; color:#166534; }
.erq-badge.status-rejected   { background:#fee2e2; color:#991b1b; }
.erq-badge.status-processing { background:#dbeafe; color:#1e40af; }
.erq-badge.status-completed  { background:#e0e7ff; color:#3730a3; }
.erq-badge.status-cancelled  { background:#f1f5f9; color:#64748b; }

.erq-body {
    font-size:13.5px; color:#475569; line-height:1.65;
    margin:14px 0; padding:14px 16px;
    background:#f8fafc; border-radius:12px;
    border-left:3px solid #e2e8f0;
    white-space:pre-wrap; word-break:break-word;
}

.erq-foot {
    display:flex; justify-content:space-between;
    align-items:center; gap:12px; margin-top:16px;
    padding-top:16px; border-top:1px dashed #e2e8f0;
    flex-wrap:wrap;
}
.erq-timestamp {
    font-size:12px; color:#94a3b8;
    display:flex; align-items:center; gap:6px; flex-wrap:wrap;
}
.erq-timestamp strong { color:#475569; }
.erq-timestamp .notes {
    display:block; margin-top:6px; font-style:italic;
    color:#64748b; font-size:11.5px;
}

.erq-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.erq-actions form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.erq-actions input[type=text] {
    padding:9px 14px; border:1.5px solid #e2e8f0;
    border-radius:10px; font-size:12px; min-width:180px;
    font-family:inherit; background:#f8fafc;
    transition:all 0.25s; color:#1e293b;
}
.erq-actions input[type=text]:focus {
    outline:none; border-color:#0e4c92; background:white;
    box-shadow:0 0 0 3px rgba(14,76,146,0.08);
}

.erq-btn {
    padding:9px 18px; border-radius:11px; font-size:12px; font-weight:700;
    cursor:pointer; border:none; display:inline-flex; align-items:center;
    gap:6px; transition:all 0.25s; font-family:inherit; text-decoration:none;
}
.erq-btn.approve {
    background:linear-gradient(135deg,#16a34a,#22c55e); color:white;
    box-shadow:0 5px 14px rgba(22,163,74,0.25);
}
.erq-btn.approve:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(22,163,74,0.35); }
.erq-btn.reject {
    background:#fef2f2; color:#dc2626; border:1.5px solid #fecaca;
}
.erq-btn.reject:hover { background:#dc2626; color:white; }
.erq-btn.complete {
    background:linear-gradient(135deg,#7c3aed,#a855f7); color:white;
    box-shadow:0 5px 14px rgba(124,58,237,0.25);
}
.erq-btn.complete:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(124,58,237,0.35); }

/* Empty state */
.erq-empty {
    padding:60px 30px; text-align:center;
    background:white; border-radius:22px;
    border:2px dashed #e2e8f0;
}
.erq-empty .icon-wrap {
    width:80px; height:80px; margin:0 auto 20px;
    background:linear-gradient(135deg,#eef2ff,#e0e7ff);
    border-radius:24px;
    display:flex; align-items:center; justify-content:center;
    font-size:32px; color:#6366f1;
}
.erq-empty h3 { font-size:18px; color:#1e293b; margin:0 0 8px; font-weight:700; }
.erq-empty p  { font-size:13px; color:#64748b; margin:0; }

/* Alerts */
.erq-alert {
    padding:14px 20px; border-radius:14px; margin-bottom:20px;
    font-size:13px; font-weight:500;
    display:flex; align-items:center; gap:10px;
}
.erq-alert.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.erq-alert.error   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

@media (max-width:640px) {
    .erq-header { padding:24px; }
    .erq-header h1 { font-size:20px; }
    .erq-card-head { flex-direction:column; }
    .erq-badges { justify-content:flex-start; }
    .erq-actions form { width:100%; }
    .erq-actions input[type=text] { width:100%; }
}
</style>

<!-- ============== HEADER ============== -->
<div class="erq-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-inbox"></i></div>
        <div>
            <h1>Employee Requests</h1>
            <p>Review, approve, and process employee submissions</p>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="erq-alert success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="erq-alert error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ============== STATS ============== -->
<div class="erq-stats">
    <div class="erq-stat">
        <div class="ico amber"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="num"><?= (int)($counts['pending'] ?? 0) ?></div><div class="lbl">Pending</div></div>
    </div>
    <div class="erq-stat">
        <div class="ico green"><i class="fas fa-circle-check"></i></div>
        <div><div class="num"><?= (int)($counts['approved'] ?? 0) ?></div><div class="lbl">Approved</div></div>
    </div>
    <div class="erq-stat">
        <div class="ico red"><i class="fas fa-circle-xmark"></i></div>
        <div><div class="num"><?= (int)($counts['rejected'] ?? 0) ?></div><div class="lbl">Rejected</div></div>
    </div>
    <div class="erq-stat">
        <div class="ico purple"><i class="fas fa-flag-checkered"></i></div>
        <div><div class="num"><?= (int)($counts['completed'] ?? 0) ?></div><div class="lbl">Completed</div></div>
    </div>
    <div class="erq-stat">
        <div class="ico blue"><i class="fas fa-layer-group"></i></div>
        <div><div class="num"><?= array_sum($counts) ?></div><div class="lbl">Total</div></div>
    </div>
</div>

<!-- ============== FILTERS ============== -->
<div class="erq-filter-panel">
    <div class="erq-filter-title"><i class="fas fa-filter"></i> Filter by Status</div>
    <div class="erq-filter-row">
        <?php
        $statuses = [
            'all'        => 'All',
            'pending'    => 'Pending',
            'approved'   => 'Approved',
            'rejected'   => 'Rejected',
            'processing' => 'Processing',
            'completed'  => 'Completed',
            'cancelled'  => 'Cancelled',
        ];
        foreach ($statuses as $key => $label):
            $cnt = $key === 'all' ? array_sum($counts) : ($counts[$key] ?? 0);
        ?>
        <a href="?page=ess-admin&subpage=ess-requests&filter=<?= $key ?>&type=<?= $filterType ?>"
           class="erq-chip <?= $filterStatus === $key ? 'active' : '' ?>">
            <?= $label ?>
            <span class="cnt"><?= (int)$cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="erq-filter-title"><i class="fas fa-tags"></i> Filter by Type</div>
    <div class="erq-filter-row">
        <?php
        $types = [
            'all'           => 'All Types',
            'leave'         => 'Leave',
            'overtime'      => 'Overtime',
            'timesheet'     => 'Timesheet',
            'document'      => 'Document',
            'claim'         => 'Claim',
            'reimbursement' => 'Reimbursement',
            'other'         => 'Other',
        ];
        foreach ($types as $key => $label):
            $cnt = $key === 'all' ? array_sum($typeCounts) : ($typeCounts[$key] ?? 0);
        ?>
        <a href="?page=ess-admin&subpage=ess-requests&filter=<?= $filterStatus ?>&type=<?= $key ?>"
           class="erq-chip <?= $filterType === $key ? 'active' : '' ?>">
            <?= $label ?>
            <?php if ($cnt > 0): ?><span class="cnt"><?= (int)$cnt ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============== LIST ============== -->
<?php if (empty($requests)): ?>
<div class="erq-empty">
    <div class="icon-wrap"><i class="fas fa-inbox"></i></div>
    <h3>No requests found</h3>
    <p>No requests match the current filter. Try changing the filters above.</p>
</div>
<?php else: ?>
<div class="erq-list">
    <?php foreach ($requests as $r): ?>
    <div class="erq-card priority-<?= htmlspecialchars($r['priority']) ?>">
        <div class="erq-card-head">
            <div style="flex:1;min-width:0;">
                <h3 class="erq-title"><?= htmlspecialchars($r['title']) ?></h3>
                <div class="erq-meta">
                    <span><i class="fas fa-user"></i>
                        <strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                        (<?= htmlspecialchars($r['emp_code']) ?>)
                    </span>
                    <span><i class="fas fa-briefcase"></i> <?= htmlspecialchars($r['position'] ?? '—') ?></span>
                    <span class="erq-number">#<?= htmlspecialchars($r['request_number']) ?></span>
                    <span><i class="fas fa-clock"></i> <?= date('M j, Y · g:i A', strtotime($r['created_at'])) ?></span>
                </div>
            </div>
            <div class="erq-badges">
                <span class="erq-badge priority-<?= htmlspecialchars($r['priority']) ?>">
                    <?= htmlspecialchars($r['priority']) ?>
                </span>
                <span class="erq-badge status-<?= htmlspecialchars($r['status']) ?>">
                    <?= ucfirst($r['status']) ?>
                </span>
            </div>
        </div>

        <div style="font-size:12px;color:#94a3b8;margin-bottom:6px;">
            <i class="fas fa-tag"></i> Type:
            <strong style="color:#475569;"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $r['request_type']))) ?></strong>
        </div>

        <?php if (!empty($r['description'])): ?>
        <div class="erq-body"><?= nl2br(htmlspecialchars($r['description'])) ?></div>
        <?php endif; ?>

        <div class="erq-foot">
            <div class="erq-timestamp">
                <?php if ($r['status'] === 'pending'): ?>
                    <i class="fas fa-hourglass-half" style="color:#f59e0b;"></i>
                    Awaiting your review
                <?php elseif ($r['status'] === 'approved'): ?>
                    <i class="fas fa-check-circle" style="color:#16a34a;"></i>
                    Approved on <?= date('M j, Y · g:i A', strtotime($r['approved_at'])) ?>
                <?php elseif ($r['status'] === 'rejected'): ?>
                    <i class="fas fa-times-circle" style="color:#dc2626;"></i>
                    Rejected on <?= date('M j, Y · g:i A', strtotime($r['approved_at'])) ?>
                <?php elseif ($r['status'] === 'completed'): ?>
                    <i class="fas fa-flag-checkered" style="color:#7c3aed;"></i>
                    Completed on <?= date('M j, Y · g:i A', strtotime($r['completed_at'])) ?>
                <?php endif; ?>
                <?php if (!empty($r['approval_notes'])): ?>
                    <span class="notes"><i class="fas fa-comment"></i> Notes: <?= htmlspecialchars($r['approval_notes']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($r['status'] === 'pending'): ?>
            <div class="erq-actions">
                <form method="POST">
                    <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                    <input type="text" name="approval_notes" placeholder="Optional notes...">
                    <button type="submit" name="action_request" value="approve" class="erq-btn approve">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button type="submit" name="action_request" value="reject" class="erq-btn reject"
                            onclick="return confirm('Reject this request?')">
                        <i class="fas fa-times"></i> Reject
                    </button>
                </form>
            </div>
            <?php elseif ($r['status'] === 'approved'): ?>
            <div class="erq-actions">
                <a href="?page=ess-admin&subpage=ess-requests&complete=<?= $r['id'] ?>&filter=<?= $filterStatus ?>&type=<?= $filterType ?>"
                   class="erq-btn complete"
                   onclick="return confirm('Mark this request as completed?')">
                    <i class="fas fa-check-double"></i> Mark Completed
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>