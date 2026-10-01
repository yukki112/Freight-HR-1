<?php
// /modules/admin-ess/ess-documents.php
$message = '';
$error = '';

// ---- PROCESS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_document'])) {
    try {
        $id     = (int)$_POST['request_id'];
        $action = $_POST['process_document'];
        $notes  = trim($_POST['notes'] ?? '');
        $filePath = null;

        if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/ess_documents/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext      = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $safeName = 'doc_' . $id . '_' . time() . '.' . strtolower($ext);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $safeName)) {
                $filePath = 'uploads/ess_documents/' . $safeName;
            }
        }

        $statusMap = [
            'processing' => 'processing',
            'ready'      => 'ready',
            'released'   => 'released',
            'reject'     => 'rejected',
        ];
        $newStatus = $statusMap[$action] ?? 'processing';

        $sql = "UPDATE ess_document_requests SET status = ?, processed_by = ?, processed_at = NOW(), notes = ?";
        $params = [$newStatus, $_SESSION['user_id'] ?? null, $notes ?: null];
        if ($filePath) { $sql .= ", file_path = ?"; $params[] = $filePath; }
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);

        // Notify employee
        $stmt = $pdo->prepare("SELECT employee_id FROM ess_document_requests WHERE id = ?");
        $stmt->execute([$id]);
        $empId = $stmt->fetchColumn();

        if ($empId) {
            $notifMap = [
                'processing' => ['Document Being Processed', 'Your document request is now being processed.', 'info'],
                'ready'      => ['Document Ready', 'Your document is ready. You can download it from My Documents.', 'success'],
                'released'   => ['Document Released', 'Your document has been released.', 'success'],
                'rejected'   => ['Document Request Rejected', 'Your request was rejected.' . ($notes ? ' Reason: ' . $notes : ''), 'danger'],
            ];
            [$title, $msg, $type] = $notifMap[$newStatus] ?? ['Update', 'Status updated.', 'info'];
            $pdo->prepare("INSERT INTO ess_notifications (employee_id, title, message, type, module, reference_id) VALUES (?, ?, ?, ?, 'document-requests', ?)")
                ->execute([$empId, $title, $msg, $type, $id]);
        }

        $message = "Document request updated to: " . ucfirst($newStatus) . ".";
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// ---- FILTERS ----
$filterStatus = $_GET['filter'] ?? 'pending';
$whereSql = $filterStatus === 'all' ? '' : 'WHERE d.status = ?';
$params   = $filterStatus === 'all' ? [] : [$filterStatus];

$stmt = $pdo->prepare("
    SELECT d.*, nh.employee_id AS emp_code, nh.position, nh.department,
           ja.first_name, ja.last_name, u.full_name AS processor_name
    FROM ess_document_requests d
    JOIN new_hires nh        ON d.employee_id = nh.id
    JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN users u        ON d.processed_by = u.id
    {$whereSql}
    ORDER BY d.created_at DESC
");
$stmt->execute($params);
$documents = $stmt->fetchAll();

$counts = $pdo->query("SELECT status, COUNT(*) AS cnt FROM ess_document_requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$docLabels = [
    'coe'       => 'Certificate of Employment',
    'payslip'   => 'Payslip Copy',
    'contract'  => 'Employment Contract',
    'id'        => 'Company ID',
    'clearance' => 'Clearance',
    'other'     => 'Other Document',
];

$docIcons = [
    'coe'       => 'fa-certificate',
    'payslip'   => 'fa-file-invoice-dollar',
    'contract'  => 'fa-file-signature',
    'id'        => 'fa-id-card',
    'clearance' => 'fa-stamp',
    'other'     => 'fa-file',
];
?>

<style>
/* ==========================================================
   ESS DOCUMENTS — REDESIGNED
   ========================================================== */
.edoc-header {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 24px; padding: 32px 36px; margin-bottom: 25px;
    color: white; position: relative; overflow: hidden;
    box-shadow: 0 20px 40px rgba(14,76,146,0.2);
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 20px;
}
.edoc-header::before {
    content:''; position:absolute; width:240px; height:240px;
    background: rgba(255,255,255,0.08); border-radius:50%;
    top:-100px; right:-60px;
}
.edoc-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.edoc-header .hdr-icon {
    width:60px; height:60px; background: rgba(255,255,255,0.18);
    border:1.5px solid rgba(255,255,255,0.3); border-radius:18px;
    display:flex; align-items:center; justify-content:center;
    font-size:26px; backdrop-filter: blur(10px);
}
.edoc-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; letter-spacing:-0.3px; }
.edoc-header p  { font-size:13px; margin:0; opacity:0.85; }

/* Stats */
.edoc-stats {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
    gap:14px; margin-bottom:22px;
}
.edoc-stat {
    background:white; border-radius:18px; padding:18px 20px;
    display:flex; align-items:center; gap:14px;
    box-shadow:0 6px 20px rgba(0,0,0,0.04);
    border:1px solid #eef2f6; transition:all 0.3s;
}
.edoc-stat:hover { transform:translateY(-3px); box-shadow:0 12px 28px rgba(14,76,146,0.08); }
.edoc-stat .ico {
    width:44px; height:44px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; flex-shrink:0;
}
.edoc-stat .ico.amber  { background:rgba(245,158,11,0.12);color:#d97706; }
.edoc-stat .ico.blue   { background:rgba(14,76,146,0.1);  color:#0e4c92; }
.edoc-stat .ico.purple { background:rgba(139,92,246,0.12);color:#7c3aed; }
.edoc-stat .ico.green  { background:rgba(22,163,74,0.12); color:#16a34a; }
.edoc-stat .ico.red    { background:rgba(220,38,38,0.12); color:#dc2626; }
.edoc-stat .num { font-size:22px; font-weight:700; color:#1e293b; line-height:1.1; }
.edoc-stat .lbl { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.6px; font-weight:600; margin-top:2px; }

/* Filter panel */
.edoc-filter-panel {
    background:white; border-radius:20px; padding:22px 26px;
    box-shadow:0 10px 30px rgba(0,0,0,0.04);
    border:1px solid #eef2f6; margin-bottom:20px;
}
.edoc-filter-title {
    font-size:11px; font-weight:700; color:#94a3b8;
    text-transform:uppercase; letter-spacing:0.6px;
    margin-bottom:12px; display:flex; align-items:center; gap:6px;
}
.edoc-chips { display:flex; gap:8px; flex-wrap:wrap; }
.edoc-chip {
    padding:8px 16px; border-radius:20px; font-size:12px; font-weight:600;
    color:#475569; background:#f1f5f9; text-decoration:none;
    display:inline-flex; align-items:center; gap:8px;
    transition:all 0.25s; border:1.5px solid transparent;
}
.edoc-chip:hover { background:#e2e8f0; }
.edoc-chip.active {
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; box-shadow:0 6px 15px rgba(14,76,146,0.25);
}
.edoc-chip .cnt {
    background:rgba(0,0,0,0.08); padding:1px 7px;
    border-radius:10px; font-size:10px; font-weight:700;
}
.edoc-chip.active .cnt { background:rgba(255,255,255,0.25); }

/* Document cards */
.edoc-list { display:flex; flex-direction:column; gap:14px; }
.edoc-card {
    background:white; border-radius:18px;
    padding:22px 24px; border:1px solid #eef2f6;
    box-shadow:0 8px 24px rgba(0,0,0,0.04);
    transition:all 0.3s; position:relative; overflow:hidden;
}
.edoc-card:hover { transform:translateY(-2px); box-shadow:0 14px 34px rgba(14,76,146,0.08); }

.edoc-card-head {
    display:flex; justify-content:space-between;
    align-items:flex-start; gap:15px; margin-bottom:12px;
}
.edoc-doc-info { display:flex; align-items:flex-start; gap:14px; flex:1; min-width:0; }
.edoc-doc-icon {
    width:52px; height:52px; border-radius:14px;
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; display:flex; align-items:center; justify-content:center;
    font-size:22px; flex-shrink:0;
    box-shadow:0 6px 15px rgba(14,76,146,0.25);
}
.edoc-doc-title { font-size:17px; font-weight:700; color:#0f172a; margin:0 0 4px; }
.edoc-doc-meta {
    display:flex; align-items:center; gap:12px;
    font-size:12px; color:#64748b; flex-wrap:wrap;
}
.edoc-doc-meta strong { color:#334155; font-weight:600; }
.edoc-doc-meta i { color:#cbd5e1; margin-right:3px; }

.edoc-status {
    padding:5px 12px; border-radius:20px;
    font-size:10.5px; font-weight:700;
    text-transform:uppercase; letter-spacing:0.4px;
    display:inline-flex; align-items:center; gap:5px;
    white-space:nowrap; flex-shrink:0;
}
.edoc-status.pending    { background:#fef3c7; color:#92400e; }
.edoc-status.processing { background:#dbeafe; color:#1e40af; }
.edoc-status.ready      { background:#e0e7ff; color:#3730a3; }
.edoc-status.released   { background:#dcfce7; color:#15803d; }
.edoc-status.rejected   { background:#fee2e2; color:#991b1b; }

.edoc-purpose {
    font-size:13.5px; color:#475569; line-height:1.65;
    margin:14px 0; padding:14px 16px;
    background:#f8fafc; border-radius:12px;
    border-left:3px solid #e2e8f0;
    word-break:break-word;
}
.edoc-purpose strong { color:#334155; }

.edoc-meta-line {
    font-size:12px; color:#64748b; margin-top:8px;
    display:flex; align-items:center; gap:8px;
}
.edoc-meta-line i { color:#cbd5e1; }
.edoc-meta-line a { color:#0e4c92; text-decoration:none; font-weight:600; }
.edoc-meta-line a:hover { text-decoration:underline; }

.edoc-actions {
    display:flex; gap:10px; flex-wrap:wrap;
    align-items:center; margin-top:16px;
    padding-top:16px; border-top:1px dashed #e2e8f0;
}
.edoc-actions form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; width:100%; }

.edoc-file-input {
    font-size:12px; padding:9px 12px;
    border:1.5px dashed #cbd5e1; border-radius:10px;
    background:white; font-family:inherit;
    color:#475569; width:200px; transition:all 0.25s;
}
.edoc-file-input:hover { border-color:#0e4c92; background:#f8fafc; }
.edoc-file-input::file-selector-button {
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; border:none; padding:6px 12px;
    border-radius:8px; font-size:11px; font-weight:600;
    margin-right:10px; cursor:pointer; font-family:inherit;
}

.edoc-notes-input {
    padding:9px 14px; border:1.5px solid #e2e8f0;
    border-radius:10px; font-size:12px; font-family:inherit;
    background:#f8fafc; min-width:200px; flex:1;
    transition:all 0.25s; color:#1e293b;
}
.edoc-notes-input:focus {
    outline:none; border-color:#0e4c92; background:white;
    box-shadow:0 0 0 3px rgba(14,76,146,0.08);
}

.edoc-btn {
    padding:9px 18px; border-radius:11px;
    font-size:12px; font-weight:700;
    cursor:pointer; border:none; display:inline-flex;
    align-items:center; gap:6px; transition:all 0.25s;
    font-family:inherit; text-decoration:none; white-space:nowrap;
}
.edoc-btn.info {
    background:linear-gradient(135deg,#0e4c92,#4086e4); color:white;
    box-shadow:0 5px 14px rgba(14,76,146,0.25);
}
.edoc-btn.info:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(14,76,146,0.35); }
.edoc-btn.warning {
    background:linear-gradient(135deg,#d97706,#f59e0b); color:white;
    box-shadow:0 5px 14px rgba(217,119,6,0.25);
}
.edoc-btn.warning:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(217,119,6,0.35); }
.edoc-btn.success {
    background:linear-gradient(135deg,#16a34a,#22c55e); color:white;
    box-shadow:0 5px 14px rgba(22,163,74,0.25);
}
.edoc-btn.success:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(22,163,74,0.35); }
.edoc-btn.reject {
    background:#fef2f2; color:#dc2626; border:1.5px solid #fecaca;
}
.edoc-btn.reject:hover { background:#dc2626; color:white; }

/* Empty */
.edoc-empty {
    padding:60px 30px; text-align:center;
    background:white; border-radius:22px;
    border:2px dashed #e2e8f0;
}
.edoc-empty .icon-wrap {
    width:80px; height:80px; margin:0 auto 20px;
    background:linear-gradient(135deg,#eef2ff,#e0e7ff);
    border-radius:24px; display:flex; align-items:center; justify-content:center;
    font-size:32px; color:#6366f1;
}
.edoc-empty h3 { font-size:18px; color:#1e293b; margin:0 0 8px; font-weight:700; }
.edoc-empty p  { font-size:13px; color:#64748b; margin:0; }

/* Alerts */
.edoc-alert {
    padding:14px 20px; border-radius:14px; margin-bottom:20px;
    font-size:13px; font-weight:500;
    display:flex; align-items:center; gap:10px;
}
.edoc-alert.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.edoc-alert.error   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

@media (max-width:640px) {
    .edoc-header { padding:24px; }
    .edoc-header h1 { font-size:20px; }
    .edoc-card-head { flex-direction:column; }
    .edoc-actions form { flex-direction:column; align-items:stretch; }
    .edoc-file-input, .edoc-notes-input { width:100%; }
}
</style>

<!-- ============== HEADER ============== -->
<div class="edoc-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <h1>Document Requests</h1>
            <p>Process employee requests for COE, payslips, contracts, and more</p>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="edoc-alert success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="edoc-alert error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ============== STATS ============== -->
<div class="edoc-stats">
    <div class="edoc-stat">
        <div class="ico amber"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="num"><?= (int)($counts['pending'] ?? 0) ?></div><div class="lbl">Pending</div></div>
    </div>
    <div class="edoc-stat">
        <div class="ico blue"><i class="fas fa-cog"></i></div>
        <div><div class="num"><?= (int)($counts['processing'] ?? 0) ?></div><div class="lbl">Processing</div></div>
    </div>
    <div class="edoc-stat">
        <div class="ico purple"><i class="fas fa-check"></i></div>
        <div><div class="num"><?= (int)($counts['ready'] ?? 0) ?></div><div class="lbl">Ready</div></div>
    </div>
    <div class="edoc-stat">
        <div class="ico green"><i class="fas fa-paper-plane"></i></div>
        <div><div class="num"><?= (int)($counts['released'] ?? 0) ?></div><div class="lbl">Released</div></div>
    </div>
    <div class="edoc-stat">
        <div class="ico red"><i class="fas fa-times-circle"></i></div>
        <div><div class="num"><?= (int)($counts['rejected'] ?? 0) ?></div><div class="lbl">Rejected</div></div>
    </div>
</div>

<!-- ============== FILTERS ============== -->
<div class="edoc-filter-panel">
    <div class="edoc-filter-title"><i class="fas fa-filter"></i> Filter by Status</div>
    <div class="edoc-chips">
        <?php
        $statuses = [
            'all'        => 'All',
            'pending'    => 'Pending',
            'processing' => 'Processing',
            'ready'      => 'Ready',
            'released'   => 'Released',
            'rejected'   => 'Rejected',
        ];
        foreach ($statuses as $key => $label):
            $cnt = $key === 'all' ? array_sum($counts) : ($counts[$key] ?? 0);
        ?>
        <a href="?page=ess-admin&subpage=ess-documents&filter=<?= $key ?>"
           class="edoc-chip <?= $filterStatus === $key ? 'active' : '' ?>">
            <?= $label ?>
            <span class="cnt"><?= (int)$cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============== LIST ============== -->
<?php if (empty($documents)): ?>
<div class="edoc-empty">
    <div class="icon-wrap"><i class="fas fa-folder-open"></i></div>
    <h3>No document requests</h3>
    <p>No requests match the current filter. Try changing the filter above.</p>
</div>
<?php else: ?>
<div class="edoc-list">
    <?php foreach ($documents as $d):
        $icon = $docIcons[$d['document_type']] ?? 'fa-file';
    ?>
    <div class="edoc-card">
        <div class="edoc-card-head">
            <div class="edoc-doc-info">
                <div class="edoc-doc-icon"><i class="fas <?= $icon ?>"></i></div>
                <div style="flex:1;min-width:0;">
                    <h3 class="edoc-doc-title">
                        <?= htmlspecialchars($docLabels[$d['document_type']] ?? ucfirst($d['document_type'])) ?>
                    </h3>
                    <div class="edoc-doc-meta">
                        <span><i class="fas fa-user"></i>
                            <strong><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></strong>
                            (<?= htmlspecialchars($d['emp_code']) ?>)
                        </span>
                        <span><i class="fas fa-briefcase"></i> <?= htmlspecialchars($d['position'] ?? '—') ?></span>
                        <span><i class="fas fa-clock"></i> <?= date('M j, Y · g:i A', strtotime($d['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            <span class="edoc-status <?= htmlspecialchars($d['status']) ?>">
                <?= ucfirst($d['status']) ?>
            </span>
        </div>

        <?php if (!empty($d['purpose'])): ?>
        <div class="edoc-purpose">
            <strong>Purpose:</strong> <?= nl2br(htmlspecialchars($d['purpose'])) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($d['file_path'])): ?>
        <div class="edoc-meta-line">
            <i class="fas fa-paperclip"></i>
            <span>Attached file:</span>
            <a href="<?= htmlspecialchars($d['file_path']) ?>" target="_blank">
                <i class="fas fa-download"></i> View / Download
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($d['notes'])): ?>
        <div class="edoc-meta-line">
            <i class="fas fa-comment"></i>
            <span><strong>Notes:</strong> <?= htmlspecialchars($d['notes']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($d['processed_at']): ?>
        <div class="edoc-meta-line">
            <i class="fas fa-user-check"></i>
            <span>Processed by <?= htmlspecialchars($d['processor_name'] ?? 'System') ?>
                  on <?= date('M j, Y · g:i A', strtotime($d['processed_at'])) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!in_array($d['status'], ['released', 'rejected'])): ?>
        <div class="edoc-actions">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="request_id" value="<?= $d['id'] ?>">

                <input type="file" name="file" class="edoc-file-input"
                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <input type="text" name="notes" class="edoc-notes-input"
                       placeholder="Optional notes...">

                <?php if ($d['status'] === 'pending'): ?>
                <button type="submit" name="process_document" value="processing" class="edoc-btn info">
                    <i class="fas fa-cog"></i> Start Processing
                </button>
                <?php endif; ?>

                <?php if ($d['status'] === 'processing'): ?>
                <button type="submit" name="process_document" value="ready" class="edoc-btn warning">
                    <i class="fas fa-check"></i> Mark Ready
                </button>
                <?php endif; ?>

                <?php if ($d['status'] === 'ready'): ?>
                <button type="submit" name="process_document" value="released" class="edoc-btn success">
                    <i class="fas fa-paper-plane"></i> Release to Employee
                </button>
                <?php endif; ?>

                <button type="submit" name="process_document" value="reject" class="edoc-btn reject"
                        onclick="return confirm('Reject this document request?')">
                    <i class="fas fa-times"></i> Reject
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>