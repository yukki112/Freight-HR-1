<?php
// /modules/erm/erm-upload.php
require_once __DIR__ . '/../../includes/erm/erm_functions.php';

$message = '';
$error   = '';
$cats    = ermCategories();
$stdDocs = ermStandardDocs();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_record'])) {
    try {
        $empId      = (int)$_POST['employee_id'];
        $category   = $_POST['record_category'];
        $docType    = trim($_POST['document_type']);
        $docTitle   = trim($_POST['document_title']);
        $docNumber  = trim($_POST['document_number'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $issueDate  = $_POST['issue_date'] ?: null;
        $expiryDate = $_POST['expiry_date'] ?: null;
        $authority  = trim($_POST['issuing_authority'] ?? '');
        $access     = $_POST['access_level'] ?? 'hr_only';
        $retention  = $_POST['retention_until'] ?: null;

        if (!$empId || !$docTitle) throw new Exception('Employee and document title are required.');

        $filePath = $fileSize = $fileType = null;
        if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/employee_records/' . $empId . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext      = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $safeName = 'rec_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest     = $uploadDir . $safeName;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                $filePath = 'uploads/employee_records/' . $empId . '/' . $safeName;
                $fileSize = $_FILES['file']['size'];
                $fileType = $_FILES['file']['type'];
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO employee_records
            (employee_id, record_category, document_type, document_title, document_number,
             description, file_path, file_size, file_type, issue_date, expiry_date,
             issuing_authority, access_level, retention_until, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $empId, $category, $docType, $docTitle, $docNumber ?: null,
            $desc ?: null, $filePath, $fileSize, $fileType, $issueDate, $expiryDate,
            $authority ?: null, $access, $retention, $_SESSION['user_id'] ?? null
        ]);
        $recordId = $pdo->lastInsertId();

        ermAudit($pdo, $recordId, $empId, 'upload', "Uploaded: $docTitle");
        $message = 'Record uploaded successfully.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$employees = $pdo->query("
    SELECT nh.id, nh.employee_id AS emp_code, nh.position,
           CONCAT(ja.first_name, ' ', ja.last_name) AS full_name
    FROM new_hires nh
    JOIN job_applications ja ON nh.applicant_id = ja.id
    WHERE nh.status IN ('active','onboarding')
    ORDER BY ja.first_name
")->fetchAll();
?>
<link rel="stylesheet" href="includes/erm/erm_styles.css">

<div class="erm-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-cloud-upload-alt"></i></div>
        <div>
            <h1>Upload Record</h1>
            <p>Add a new document to an employee's personnel file</p>
        </div>
    </div>
    <a href="?page=erm&subpage=erm-personnel" class="hdr-btn">
        <i class="fas fa-folder-open"></i> View Records
    </a>
</div>

<?php if ($message): ?>
<div class="erm-alert success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="erm-alert error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <div class="erm-panel">
        <div class="erm-panel-head">
            <div class="left">
                <div class="ico"><i class="fas fa-info-circle"></i></div>
                <div>
                    <h3>Document Details</h3>
                    <p>Basic information about this record</p>
                </div>
            </div>
        </div>
        <div class="erm-panel-body">

            <div class="erm-form-grid">
                <div class="erm-field">
                    <label>Employee *</label>
                    <select name="employee_id" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>">
                            <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['emp_code']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erm-field">
                    <label>Category *</label>
                    <select name="record_category" id="catSelect" required onchange="updateDocTypes()">
                        <?php foreach ($cats as $key => $c): ?>
                        <option value="<?= $key ?>"><?= htmlspecialchars($c['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erm-field">
                    <label>Document Type *</label>
                    <select name="document_type" id="docTypeSelect" required>
                        <?php foreach ($stdDocs['personal'] as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erm-field">
                    <label>Access Level</label>
                    <select name="access_level">
                        <option value="hr_only">HR Only (default)</option>
                        <option value="public">Public (Visible to employee)</option>
                        <option value="manager">Manager Access</option>
                        <option value="confidential">Confidential</option>
                    </select>
                </div>
            </div>

            <div class="erm-field">
                <label>Document Title *</label>
                <input type="text" name="document_title" required placeholder="e.g. Driver's License - Renewal 2026">
            </div>

            <div class="erm-form-grid">
                <div class="erm-field">
                    <label>Document Number</label>
                    <input type="text" name="document_number" placeholder="e.g. N01-23-456789">
                </div>
                <div class="erm-field">
                    <label>Issuing Authority</label>
                    <input type="text" name="issuing_authority" placeholder="e.g. LTO, SSS, NBI">
                </div>
                <div class="erm-field">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date">
                </div>
                <div class="erm-field">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date">
                </div>
                <div class="erm-field">
                    <label>Retention Until</label>
                    <input type="date" name="retention_until">
                </div>
            </div>

            <div class="erm-field">
                <label>Notes / Description</label>
                <textarea name="description" placeholder="Any additional information about this record..."></textarea>
            </div>

        </div>
    </div>

    <div class="erm-panel">
        <div class="erm-panel-head">
            <div class="left">
                <div class="ico"><i class="fas fa-paperclip"></i></div>
                <div>
                    <h3>Attach File</h3>
                    <p>Upload the scanned document (PDF, JPG, PNG, DOCX)</p>
                </div>
            </div>
        </div>
        <div class="erm-panel-body">
            <label class="erm-drop">
                <input type="file" name="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <i class="fas fa-cloud-upload-alt"></i>
                <span id="fileName">Click to select a file</span>
                <small>Max file size: 10 MB — PDF, JPG, PNG, DOCX</small>
            </label>
        </div>
    </div>

    <div style="text-align:right;margin-bottom:30px;">
        <a href="?page=erm&subpage=erm-personnel" class="erm-btn" style="background:#f1f5f9;color:#475569;margin-right:8px;">Cancel</a>
        <button type="submit" name="upload_record" class="erm-btn primary">
            <i class="fas fa-save"></i> Upload Record
        </button>
    </div>
</form>

<script>
const stdDocs = <?= json_encode($stdDocs) ?>;
function updateDocTypes() {
    const cat = document.getElementById('catSelect').value;
    const sel = document.getElementById('docTypeSelect');
    sel.innerHTML = '';
    (stdDocs[cat] || []).forEach(function(d) {
        const o = document.createElement('option');
        o.value = d; o.textContent = d;
        sel.appendChild(o);
    });
}
document.getElementById('fileInput').addEventListener('change', function() {
    const span = document.getElementById('fileName');
    span.textContent = this.files[0] ? this.files[0].name : 'Click to select a file';
});
</script>