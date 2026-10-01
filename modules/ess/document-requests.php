<?php
// /modules/ess/document-requests.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_doc'])) {
    try {
        $type = $_POST['document_type'];
        $purpose = trim($_POST['purpose'] ?? '');

        $pdo->prepare("
            INSERT INTO ess_document_requests
            (employee_id, document_type, purpose, status)
            VALUES (?, ?, ?, 'pending')
        ")->execute([$empId, $type, $purpose]);

        $message = 'Document request submitted. HR will process it shortly.';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}

$docs = $pdo->prepare("
    SELECT * FROM ess_document_requests
    WHERE employee_id = ?
    ORDER BY created_at DESC
");
$docs->execute([$empId]);
$docs = $docs->fetchAll();

$docLabels = [
    'coe' => 'Certificate of Employment',
    'payslip' => 'Payslip Copy',
    'contract' => 'Employment Contract',
    'id' => 'Company ID',
    'clearance' => 'Clearance',
    'other' => 'Other Document',
];
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

.form-field { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
.form-field label { font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.4px; }
.form-field select, .form-field textarea { padding:13px 16px; border:1.5px solid #e2e8f0; border-radius:12px; font-size:14px; font-family:inherit; background:#f8fafc; color:#1e293b; transition:all 0.25s; }
.form-field select:focus, .form-field textarea:focus { outline:none; border-color:#0e4c92; background:white; box-shadow:0 0 0 4px rgba(14,76,146,0.08); }
.form-field textarea { min-height:80px; resize:vertical; }

.ess-btn { padding:13px 30px; border-radius:12px; font-size:14px; font-weight:700; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:10px; transition:all 0.25s; font-family:inherit; }
.ess-btn.primary { background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; box-shadow:0 6px 18px rgba(14,76,146,0.25); }
.ess-btn.primary:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(14,76,146,0.35); }

.doc-list { display:flex; flex-direction:column; gap:12px; }
.doc-item { padding:18px 20px; background:#f8fafd; border:1px solid #eef2f6; border-radius:14px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.doc-info { flex:1; min-width:0; }
.doc-title { font-size:14.5px; font-weight:700; color:#1e293b; margin-bottom:4px; }
.doc-meta { font-size:12px; color:#64748b; }
.doc-status { padding:4px 12px; border-radius:20px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; white-space:nowrap; }
.doc-status.pending    { background:#fef3c7; color:#92400e; }
.doc-status.processing { background:#dbeafe; color:#1e40af; }
.doc-status.ready      { background:#e0e7ff; color:#3730a3; }
.doc-status.released   { background:#dcfce7; color:#166534; }
.doc-status.rejected   { background:#fee2e2; color:#991b1b; }
.doc-download { display:inline-flex; align-items:center; gap:6px; color:#0e4c92; text-decoration:none; font-size:12px; font-weight:600; margin-top:8px; }
.doc-download:hover { text-decoration:underline; }

.ess-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.ess-empty i { font-size:40px; color:#cbd5e1; margin-bottom:12px; display:block; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-file-alt"></i></div>
        <div>
            <h1>Document Requests</h1>
            <p>Request official HR documents (COE, payslips, contracts)</p>
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
        <h3>Request a Document</h3>
    </div>
    <div class="ess-panel-body">
        <form method="POST">
            <div class="form-field">
                <label>Document Type</label>
                <select name="document_type" required>
                    <?php foreach ($docLabels as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Purpose</label>
                <textarea name="purpose" required placeholder="What is this document for? (e.g. bank application, visa, etc.)"></textarea>
            </div>
            <div style="text-align:right;">
                <button type="submit" name="request_doc" class="ess-btn primary">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-history"></i></div>
        <h3>My Document Requests</h3>
    </div>
    <div class="ess-panel-body">
        <?php if (empty($docs)): ?>
        <div class="ess-empty">
            <i class="fas fa-file-alt"></i>
            No document requests yet.
        </div>
        <?php else: ?>
        <div class="doc-list">
            <?php foreach ($docs as $d): ?>
            <div class="doc-item">
                <div class="doc-info">
                    <div class="doc-title"><?= htmlspecialchars($docLabels[$d['document_type']] ?? $d['document_type']) ?></div>
                    <div class="doc-meta">
                        <i class="fas fa-clock"></i> Requested <?= date('M j, Y · g:i A', strtotime($d['created_at'])) ?>
                    </div>
                    <?php if ($d['file_path']): ?>
                    <a href="<?= htmlspecialchars($d['file_path']) ?>" class="doc-download" download>
                        <i class="fas fa-download"></i> Download Document
                    </a>
                    <?php endif; ?>
                    <?php if ($d['notes']): ?>
                    <div style="margin-top:8px;font-size:12px;color:#64748b;">
                        <strong>HR Notes:</strong> <?= htmlspecialchars($d['notes']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <span class="doc-status <?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>