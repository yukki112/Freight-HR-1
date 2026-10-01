<?php
// /modules/ess/my-documents.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];

// Onboarding documents
$docs = $pdo->prepare("
    SELECT * FROM onboarding_documents
    WHERE new_hire_id = ?
    ORDER BY uploaded_at DESC
");
$docs->execute([$empId]);
$docs = $docs->fetchAll();

// Uploaded by employee via ESS (from document requests)
$requestDocs = $pdo->prepare("
    SELECT * FROM ess_document_requests
    WHERE employee_id = ? AND file_path IS NOT NULL AND status IN ('ready','released')
    ORDER BY created_at DESC
");
$requestDocs->execute([$empId]);
$requestDocs = $requestDocs->fetchAll();
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

.doc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:14px; }
.doc-card { padding:20px; background:#f8fafd; border:1px solid #eef2f6; border-radius:14px; display:flex; flex-direction:column; gap:10px; transition:all 0.3s; }
.doc-card:hover { background:white; border-color:#0e4c92; transform:translateY(-3px); box-shadow:0 12px 30px rgba(14,76,146,0.08); }
.doc-card .top { display:flex; align-items:flex-start; gap:12px; }
.doc-card .icon-box { width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.doc-card .info { flex:1; min-width:0; }
.doc-card .name { font-size:13.5px; font-weight:700; color:#1e293b; word-break:break-word; }
.doc-card .meta { font-size:11px; color:#94a3b8; margin-top:4px; }
.doc-card .status { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
.doc-card .status.verified { background:#dcfce7; color:#15803d; }
.doc-card .status.pending  { background:#fef3c7; color:#92400e; }
.doc-card .status.rejected { background:#fee2e2; color:#991b1b; }
.doc-card .btn-view { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:9px 14px; background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; text-decoration:none; border-radius:10px; font-size:12px; font-weight:700; }
.doc-card .btn-view:hover { transform:translateY(-1px); box-shadow:0 6px 15px rgba(14,76,146,0.25); }

.ess-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.ess-empty i { font-size:40px; color:#cbd5e1; margin-bottom:12px; display:block; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-folder-open"></i></div>
        <div>
            <h1>My Documents</h1>
            <p>View your uploaded and issued documents</p>
        </div>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-file-upload"></i></div>
        <h3>Onboarding Documents</h3>
    </div>
    <div class="ess-panel-body">
        <?php if (empty($docs)): ?>
        <div class="ess-empty">
            <i class="fas fa-folder-open"></i>
            No documents on file.
        </div>
        <?php else: ?>
        <div class="doc-grid">
            <?php foreach ($docs as $d): ?>
            <div class="doc-card">
                <div class="top">
                    <div class="icon-box"><i class="fas fa-file-pdf"></i></div>
                    <div class="info">
                        <div class="name"><?= htmlspecialchars($d['document_name'] ?? $d['document_type']) ?></div>
                        <div class="meta">Uploaded <?= date('M j, Y', strtotime($d['uploaded_at'])) ?></div>
                    </div>
                </div>
                <span class="status <?= $d['status'] === 'verified' ? 'verified' : ($d['status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                    <?= ucfirst($d['status']) ?>
                </span>
                <?php if ($d['file_path']): ?>
                <a href="<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="btn-view">
                    <i class="fas fa-eye"></i> View Document
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($requestDocs)): ?>
<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-file-download"></i></div>
        <h3>Issued Documents (from HR)</h3>
    </div>
    <div class="ess-panel-body">
        <div class="doc-grid">
            <?php foreach ($requestDocs as $d): ?>
            <div class="doc-card">
                <div class="top">
                    <div class="icon-box"><i class="fas fa-file-alt"></i></div>
                    <div class="info">
                        <div class="name"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$d['document_type']))) ?></div>
                        <div class="meta">Issued <?= date('M j, Y', strtotime($d['processed_at'] ?? $d['created_at'])) ?></div>
                    </div>
                </div>
                <span class="status verified">Ready</span>
                <a href="<?= htmlspecialchars($d['file_path']) ?>" download class="btn-view">
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>