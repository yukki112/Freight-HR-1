<?php
// modules/core-hr/employment-history.php
$page_title = "Employment History";

$message = '';
$error = '';

// Add new history entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_history'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO employment_history
            (employee_id, change_type, effective_date, old_position, new_position,
             old_department, new_department, old_salary, new_salary,
             old_status, new_status, reason, notes, changed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$_POST['employee_id'],
            $_POST['change_type'],
            $_POST['effective_date'],
            $_POST['old_position'] ?: null,
            $_POST['new_position'] ?: null,
            $_POST['old_department'] ?: null,
            $_POST['new_department'] ?: null,
            $_POST['old_salary'] ?: null,
            $_POST['new_salary'] ?: null,
            $_POST['old_status'] ?: null,
            $_POST['new_status'] ?: null,
            $_POST['reason'] ?: null,
            $_POST['notes'] ?: null,
            $_SESSION['user_id']
        ]);

        // If it's a transfer/promotion, update the new_hires record too
        $employee_id = (int)$_POST['employee_id'];
        $updates = [];
        $values = [];
        if (!empty($_POST['new_position'])) { $updates[] = "position = ?"; $values[] = $_POST['new_position']; }
        if (!empty($_POST['new_department'])) { $updates[] = "department = ?"; $values[] = $_POST['new_department']; }
        if (!empty($_POST['new_status'])) { $updates[] = "employment_status = ?"; $values[] = $_POST['new_status']; }
        if (!empty($updates)) {
            $values[] = $employee_id;
            $stmt = $pdo->prepare("UPDATE new_hires SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?");
            $stmt->execute($values);
        }

        $message = "History entry added successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Filters
$emp_filter = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$type_filter = $_GET['change_type'] ?? '';

$query = "
    SELECT eh.*, 
           nh.employee_id as emp_code,
           ja.first_name, ja.last_name, ja.photo_path,
           u.full_name as changed_by_name
    FROM employment_history eh
    JOIN new_hires nh ON eh.employee_id = nh.id
    LEFT JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN users u ON eh.changed_by = u.id
    WHERE 1=1
";
$params = [];

if ($emp_filter) { $query .= " AND eh.employee_id = ?"; $params[] = $emp_filter; }
if (!empty($type_filter)) { $query .= " AND eh.change_type = ?"; $params[] = $type_filter; }
$query .= " ORDER BY eh.effective_date DESC, eh.created_at DESC LIMIT 200";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Employees for dropdown
$stmt = $pdo->query("
    SELECT nh.id, nh.employee_id, ja.first_name, ja.last_name
    FROM new_hires nh
    LEFT JOIN job_applications ja ON nh.applicant_id = ja.id
    ORDER BY ja.first_name
");
$employees = $stmt->fetchAll();

// Stats
$stats = ['total' => count($history)];
$type_counts = [];
foreach ($history as $h) {
    $type_counts[$h['change_type']] = ($type_counts[$h['change_type']] ?? 0) + 1;
}
?>

<style>
.eh-header{background:white;border-radius:20px;padding:25px;margin-bottom:25px;box-shadow:0 10px 30px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;}
.eh-header h1{font-size:24px;font-weight:600;color:#2c3e50;margin:0;display:flex;align-items:center;gap:12px;}
.eh-header h1 i{color:#0e4c92;background:rgba(14,76,146,0.1);padding:12px;border-radius:15px;font-size:24px;}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:15px;margin-bottom:25px;}
.stat-box{background:white;border-radius:16px;padding:18px;box-shadow:0 6px 20px rgba(0,0,0,0.05);display:flex;align-items:center;gap:12px;}
.stat-box .icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#0e4c92,#4086e4);display:flex;align-items:center;justify-content:center;color:white;font-size:20px;}
.stat-box .label{font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
.stat-box .value{font-size:20px;font-weight:700;color:#2c3e50;}
.filter-bar{background:white;border-radius:20px;padding:20px;margin-bottom:25px;box-shadow:0 10px 30px rgba(0,0,0,0.05);}
.filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;}
.filter-grid label{font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px;display:block;}
.filter-grid input,.filter-grid select{width:100%;padding:11px;border:1px solid #eef2f6;border-radius:12px;font-size:14px;background:white;}
.filter-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:15px;}
.btn{padding:10px 20px;border-radius:12px;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;border:1px solid transparent;}
.btn-primary{background:linear-gradient(135deg,#0e4c92,#4086e4);color:white;}
.btn-outline{background:transparent;border:1px solid #0e4c92;color:#0e4c92;}
.btn-outline:hover{background:#0e4c92;color:white;}
.btn-sm{padding:7px 14px;font-size:12px;}
.table-wrap{background:white;border-radius:20px;padding:20px;box-shadow:0 10px 30px rgba(0,0,0,0.05);overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:14px;background:#f8fafd;color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
td{padding:14px;border-bottom:1px solid #eef2f6;font-size:14px;}
tr:hover td{background:#f8fafd;}
.badge{padding:5px 12px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;text-transform:capitalize;}
.b-hire{background:rgba(39,174,96,0.15);color:#27ae60;}
.b-promotion{background:rgba(52,152,219,0.15);color:#3498db;}
.b-transfer{background:rgba(155,89,182,0.15);color:#9b59b6;}
.b-salary_change{background:rgba(243,156,18,0.15);color:#f39c12;}
.b-status_change{background:rgba(230,126,34,0.15);color:#e67e22;}
.b-suspension{background:rgba(231,76,60,0.15);color:#e74c3c;}
.b-separation{background:rgba(149,165,166,0.25);color:#7f8c8d;}
.b-other{background:rgba(100,116,139,0.15);color:#64748b;}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;}
.modal.active{display:flex;}
.modal-content{background:white;border-radius:24px;padding:30px;max-width:750px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 30px 60px rgba(0,0,0,0.3);}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #eef2f6;}
.modal-header h3{font-size:20px;font-weight:600;color:#2c3e50;margin:0;}
.modal-close{background:none;border:none;font-size:28px;cursor:pointer;color:#64748b;}
.section-title{font-size:13px;font-weight:700;color:#0e4c92;margin:20px 0 12px;padding-bottom:6px;border-bottom:2px solid rgba(14,76,146,0.15);display:flex;align-items:center;gap:8px;text-transform:uppercase;letter-spacing:0.5px;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group label{font-size:12px;font-weight:600;color:#64748b;}
.form-group input,.form-group select,.form-group textarea{padding:11px;border:1px solid #eef2f6;border-radius:10px;font-size:13px;background:white;font-family:inherit;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#0e4c92;box-shadow:0 0 0 3px rgba(14,76,146,0.1);}
.form-group textarea{min-height:70px;resize:vertical;}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:25px;padding-top:20px;border-top:1px solid #eef2f6;}
.alert{padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
</style>

<div class="eh-header">
    <h1><i class="fas fa-history"></i> Employment History</h1>
    <button class="btn btn-primary btn-sm" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Entry</button>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="stats-row">
    <div class="stat-box"><div class="icon"><i class="fas fa-list"></i></div><div><div class="label">Total Records</div><div class="value"><?= $stats['total'] ?></div></div></div>
    <?php foreach ($type_counts as $type => $cnt): ?>
    <div class="stat-box"><div class="icon"><i class="fas fa-circle"></i></div><div><div class="label"><?= ucfirst(str_replace('_',' ',$type)) ?></div><div class="value"><?= $cnt ?></div></div></div>
    <?php endforeach; ?>
</div>

<div class="filter-bar">
    <form method="GET">
        <input type="hidden" name="page" value="core-hr">
        <input type="hidden" name="subpage" value="employment-history">
        <div class="filter-grid">
            <div><label>Employee</label>
                <select name="employee_id">
                    <option value="">All Employees</option>
                    <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $emp_filter==$e['id']?'selected':'' ?>>
                        <?= htmlspecialchars(trim($e['first_name'].' '.$e['last_name'])) ?> (<?= $e['employee_id'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Change Type</label>
                <select name="change_type">
                    <option value="">All Types</option>
                    <option value="hire" <?= $type_filter=='hire'?'selected':'' ?>>Hire</option>
                    <option value="promotion" <?= $type_filter=='promotion'?'selected':'' ?>>Promotion</option>
                    <option value="transfer" <?= $type_filter=='transfer'?'selected':'' ?>>Transfer</option>
                    <option value="salary_change" <?= $type_filter=='salary_change'?'selected':'' ?>>Salary Change</option>
                    <option value="status_change" <?= $type_filter=='status_change'?'selected':'' ?>>Status Change</option>
                    <option value="suspension" <?= $type_filter=='suspension'?'selected':'' ?>>Suspension</option>
                    <option value="separation" <?= $type_filter=='separation'?'selected':'' ?>>Separation</option>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <a href="?page=core-hr&subpage=employment-history" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
        </div>
    </form>
</div>

<div class="table-wrap">
    <?php if (empty($history)): ?>
        <div style="text-align:center;padding:60px 20px;color:#94a3b8;"><h3>No History Records</h3><p>Add a promotion, transfer, or status change to get started.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Employee</th><th>Change Type</th><th>Effective Date</th><th>What Changed</th><th>Reason</th><th>By</th></tr></thead>
        <tbody>
            <?php foreach ($history as $h):
                $fn = $h['first_name'] ?? ''; $ln = $h['last_name'] ?? '';
                $full = trim($fn.' '.$ln) ?: 'Unknown';
                $change_desc = '';
                if ($h['change_type'] === 'promotion' || $h['change_type'] === 'transfer') {
                    $change_desc = ($h['old_position'] ?? '-') . ' → ' . ($h['new_position'] ?? '-');
                    if ($h['old_department'] !== $h['new_department']) {
                        $change_desc .= ' | ' . ($h['old_department'] ?? '-') . ' → ' . ($h['new_department'] ?? '-');
                    }
                } elseif ($h['change_type'] === 'salary_change') {
                    $change_desc = '₱' . number_format((float)$h['old_salary'], 2) . ' → ₱' . number_format((float)$h['new_salary'], 2);
                } elseif ($h['change_type'] === 'status_change') {
                    $change_desc = ($h['old_status'] ?? '-') . ' → ' . ($h['new_status'] ?? '-');
                }
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($full) ?></strong><div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($h['emp_code']) ?></div></td>
                <td><span class="badge b-<?= $h['change_type'] ?>"><?= ucfirst(str_replace('_',' ',$h['change_type'])) ?></span></td>
                <td><?= date('M d, Y', strtotime($h['effective_date'])) ?></td>
                <td style="font-size:12px;color:#4a5568;"><?= htmlspecialchars($change_desc ?: '-') ?></td>
                <td style="font-size:12px;color:#64748b;"><?= htmlspecialchars($h['reason'] ?: '-') ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($h['changed_by_name'] ?? 'System') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Add History Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color:#0e4c92;"></i> Add Employment History Entry</h3>
            <button class="modal-close" onclick="closeAddModal()">&times;</button>
        </div>
        <form method="POST">
            <div class="section-title"><i class="fas fa-info-circle"></i> Change Details</div>
            <div class="form-grid">
                <div class="form-group"><label>Employee *</label>
                    <select name="employee_id" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= htmlspecialchars(trim($e['first_name'].' '.$e['last_name'])) ?> (<?= $e['employee_id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Change Type *</label>
                    <select name="change_type" required>
                        <option value="hire">Hire</option>
                        <option value="promotion">Promotion</option>
                        <option value="transfer">Transfer</option>
                        <option value="salary_change">Salary Change</option>
                        <option value="status_change">Status Change</option>
                        <option value="suspension">Suspension</option>
                        <option value="separation">Separation</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group"><label>Effective Date *</label>
                    <input type="date" name="effective_date" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="section-title"><i class="fas fa-exchange-alt"></i> Old → New Values</div>
            <div class="form-grid">
                <div class="form-group"><label>Old Position</label><input type="text" name="old_position"></div>
                <div class="form-group"><label>New Position</label><input type="text" name="new_position"></div>
                <div class="form-group"><label>Old Department</label><input type="text" name="old_department"></div>
                <div class="form-group"><label>New Department</label><input type="text" name="new_department"></div>
                <div class="form-group"><label>Old Salary</label><input type="number" step="0.01" name="old_salary"></div>
                <div class="form-group"><label>New Salary</label><input type="number" step="0.01" name="new_salary"></div>
                <div class="form-group"><label>Old Status</label><input type="text" name="old_status"></div>
                <div class="form-group"><label>New Status</label><input type="text" name="new_status"></div>
            </div>

            <div class="section-title"><i class="fas fa-comment-alt"></i> Reason &amp; Notes</div>
            <div class="form-group"><label>Reason</label><input type="text" name="reason" placeholder="Brief reason"></div>
            <div class="form-group"><label>Notes</label><textarea name="notes" placeholder="Additional details..."></textarea></div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeAddModal()">Cancel</button>
                <button type="submit" name="add_history" class="btn btn-primary"><i class="fas fa-save"></i> Save Entry</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal(){document.getElementById('addModal').classList.add('active');}
function closeAddModal(){document.getElementById('addModal').classList.remove('active');}
window.onclick=function(e){if(e.target.id==='addModal')closeAddModal();}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeAddModal();});
</script>