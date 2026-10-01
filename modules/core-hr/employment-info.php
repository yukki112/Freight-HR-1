<?php
// modules/core-hr/employment-info.php
$page_title = "Employment Information";

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_employment_info'])) {
    try {
        $employee_id = (int)$_POST['employee_id'];
        $old = $pdo->prepare("SELECT position, department, employment_status, supervisor_id FROM new_hires WHERE id = ?");
        $old->execute([$employee_id]);
        $old_data = $old->fetch();

        $stmt = $pdo->prepare("
            UPDATE new_hires SET
                position = ?, department = ?, employment_type = ?, employment_status = ?,
                work_location = ?, branch = ?, supervisor_id = ?,
                hire_date = ?, start_date = ?, probation_end_date = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['position'] ?: null,
            $_POST['department'] ?: null,
            $_POST['employment_type'] ?: 'probationary',
            $_POST['employment_status'] ?: 'probationary',
            $_POST['work_location'] ?: null,
            $_POST['branch'] ?: null,
            $_POST['supervisor_id'] ?: null,
            $_POST['hire_date'] ?: null,
            $_POST['start_date'] ?: null,
            $_POST['probation_end_date'] ?: null,
            $employee_id
        ]);

        // Log history if changed
        $changes = [];
        if ($old_data['position'] !== $_POST['position']) $changes[] = 'position';
        if ($old_data['department'] !== $_POST['department']) $changes[] = 'department';
        if ($old_data['employment_status'] !== $_POST['employment_status']) $changes[] = 'status';
        if (!empty($changes)) {
            $stmt = $pdo->prepare("
                INSERT INTO employment_history 
                (employee_id, change_type, effective_date, old_position, new_position,
                 old_department, new_department, old_status, new_status, changed_by, notes)
                VALUES (?, 'status_change', NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $employee_id,
                $old_data['position'], $_POST['position'],
                $old_data['department'], $_POST['department'],
                $old_data['employment_status'], $_POST['employment_status'],
                $_SESSION['user_id'],
                "Updated via Employment Information page"
            ]);
        }

        $message = "Employment information updated successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$search_filter = $_GET['search'] ?? '';
$dept_filter   = $_GET['department'] ?? '';

$query = "
    SELECT nh.*, ja.first_name, ja.last_name, ja.photo_path,
           jp.title as job_title, jp.job_code,
           u.full_name as supervisor_name
    FROM new_hires nh
    LEFT JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
    LEFT JOIN users u ON nh.supervisor_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($search_filter)) {
    $query .= " AND (ja.first_name LIKE ? OR ja.last_name LIKE ? OR nh.employee_id LIKE ?)";
    $t = "%$search_filter%";
    array_push($params, $t, $t, $t);
}
if (!empty($dept_filter)) { $query .= " AND nh.department = ?"; $params[] = $dept_filter; }
$query .= " ORDER BY nh.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin','manager') ORDER BY full_name");
$supervisors = $stmt->fetchAll();

$stmt = $pdo->query("SELECT department_code, department_name FROM departments WHERE is_active = 1 ORDER BY sort_order");
$departments = $stmt->fetchAll();
?>

<style>
.ei-header{background:white;border-radius:20px;padding:25px;margin-bottom:25px;box-shadow:0 10px 30px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;}
.ei-header h1{font-size:24px;font-weight:600;color:#2c3e50;margin:0;display:flex;align-items:center;gap:12px;}
.ei-header h1 i{color:#0e4c92;background:rgba(14,76,146,0.1);padding:12px;border-radius:15px;font-size:24px;}
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
.avatar{width:42px;height:42px;border-radius:12px;object-fit:cover;background:linear-gradient(135deg,#0e4c92,#4086e4);display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:15px;flex-shrink:0;}
.avatar img{width:100%;height:100%;border-radius:12px;object-fit:cover;}
.badge{padding:5px 12px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.badge-onboarding{background:rgba(243,156,18,0.15);color:#f39c12;}
.badge-active{background:rgba(39,174,96,0.15);color:#27ae60;}
.badge-probationary{background:rgba(52,152,219,0.15);color:#3498db;}
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
.form-group input,.form-group select{padding:11px;border:1px solid #eef2f6;border-radius:10px;font-size:13px;background:white;}
.form-group input:focus,.form-group select:focus{outline:none;border-color:#0e4c92;box-shadow:0 0 0 3px rgba(14,76,146,0.1);}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:25px;padding-top:20px;border-top:1px solid #eef2f6;}
.alert{padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
</style>

<div class="ei-header">
    <h1><i class="fas fa-briefcase"></i> Employment Information</h1>
    <span style="font-size:13px;color:#64748b;">Position • Department • Type • Location • Supervisor</span>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="filter-bar">
    <form method="GET">
        <input type="hidden" name="page" value="core-hr">
        <input type="hidden" name="subpage" value="employment-info">
        <div class="filter-grid">
            <div><label>Search</label><input type="text" name="search" placeholder="Name or Employee ID" value="<?= htmlspecialchars($search_filter) ?>"></div>
            <div><label>Department</label>
                <select name="department">
                    <option value="">All</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['department_code'] ?>" <?= $dept_filter==$d['department_code']?'selected':'' ?>><?= htmlspecialchars($d['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <a href="?page=core-hr&subpage=employment-info" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
</div>

<div class="table-wrap">
    <?php if (empty($employees)): ?>
        <div style="text-align:center;padding:60px 20px;color:#94a3b8;"><h3>No Employees Found</h3></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Employee</th><th>Position</th><th>Department</th><th>Type</th><th>Location</th><th>Supervisor</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($employees as $emp):
                $fn = $emp['first_name'] ?? ''; $ln = $emp['last_name'] ?? '';
                $full = trim($fn.' '.$ln) ?: 'Unknown';
                $initials = strtoupper(substr($fn,0,1).substr($ln,0,1)) ?: '?';
                $photo = !empty($emp['photo_path']) && file_exists($emp['photo_path']) ? $emp['photo_path'] : null;
            ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div class="avatar"><?php if ($photo): ?><img src="<?= htmlspecialchars($photo) ?>"><?php else: echo $initials; endif; ?></div>
                        <div><strong><?= htmlspecialchars($full) ?></strong><div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($emp['employee_id']) ?></div></div>
                    </div>
                </td>
                <td><?= htmlspecialchars($emp['position'] ?? '-') ?></td>
                <td style="text-transform:capitalize;"><?= htmlspecialchars(str_replace('_',' ',$emp['department'] ?? '-')) ?></td>
                <td><span class="badge badge-probationary"><?= ucfirst(str_replace('_',' ', $emp['employment_type'] ?? 'probationary')) ?></span></td>
                <td><?= htmlspecialchars($emp['work_location'] ?? '-') ?></td>
                <td><?= htmlspecialchars($emp['supervisor_name'] ?? 'None') ?></td>
                <td><span class="badge badge-<?= $emp['status'] ?>"><?= ucfirst($emp['status']) ?></span></td>
                <td><button class="btn btn-primary btn-sm" onclick='editEmployment(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i> Edit</button></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-briefcase" style="color:#0e4c92;"></i> Edit Employment Information</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="employee_id" id="ei_id">

            <div class="section-title"><i class="fas fa-briefcase"></i> Position &amp; Department</div>
            <div class="form-grid">
                <div class="form-group"><label>Position</label><input type="text" name="position" id="ei_position"></div>
                <div class="form-group"><label>Department</label>
                    <select name="department" id="ei_department">
                        <option value="">Select</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['department_code'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Employment Type</label>
                    <select name="employment_type" id="ei_employment_type">
                        <option value="full_time">Full Time</option>
                        <option value="part_time">Part Time</option>
                        <option value="contract">Contract</option>
                        <option value="probationary">Probationary</option>
                    </select>
                </div>
                <div class="form-group"><label>Employment Status</label>
                    <select name="employment_status" id="ei_employment_status">
                        <option value="probationary">Probationary</option>
                        <option value="regular">Regular</option>
                        <option value="contractual">Contractual</option>
                    </select>
                </div>
            </div>

            <div class="section-title"><i class="fas fa-map-marker-alt"></i> Work Location &amp; Supervisor</div>
            <div class="form-grid">
                <div class="form-group"><label>Work Location</label><input type="text" name="work_location" id="ei_work_location"></div>
                <div class="form-group"><label>Branch</label><input type="text" name="branch" id="ei_branch"></div>
                <div class="form-group"><label>Supervisor</label>
                    <select name="supervisor_id" id="ei_supervisor_id">
                        <option value="">None</option>
                        <?php foreach ($supervisors as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="section-title"><i class="fas fa-calendar-alt"></i> Key Dates</div>
            <div class="form-grid">
                <div class="form-group"><label>Hire Date</label><input type="date" name="hire_date" id="ei_hire_date"></div>
                <div class="form-group"><label>Start Date</label><input type="date" name="start_date" id="ei_start_date"></div>
                <div class="form-group"><label>Probation End Date</label><input type="date" name="probation_end_date" id="ei_probation_end_date"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="save_employment_info" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editEmployment(e) {
    document.getElementById('ei_id').value = e.id;
    document.getElementById('ei_position').value = e.position || e.job_title || '';
    document.getElementById('ei_department').value = e.department || '';
    document.getElementById('ei_employment_type').value = e.employment_type || 'probationary';
    document.getElementById('ei_employment_status').value = e.employment_status || 'probationary';
    document.getElementById('ei_work_location').value = e.work_location || '';
    document.getElementById('ei_branch').value = e.branch || '';
    document.getElementById('ei_supervisor_id').value = e.supervisor_id || '';
    document.getElementById('ei_hire_date').value = e.hire_date || '';
    document.getElementById('ei_start_date').value = e.start_date || '';
    document.getElementById('ei_probation_end_date').value = e.probation_end_date || '';
    document.getElementById('editModal').classList.add('active');
}
function closeEditModal(){document.getElementById('editModal').classList.remove('active');}
window.onclick=function(e){if(e.target.id==='editModal')closeEditModal();}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeEditModal();});
</script>