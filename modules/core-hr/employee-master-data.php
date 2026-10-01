<?php
// modules/core-hr/employee-master-data.php
$page_title = "Employee Master Data";

$message = '';
$error = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_master_data'])) {
    try {
        $employee_id = (int)$_POST['employee_id'];
        $stmt = $pdo->prepare("
            UPDATE new_hires SET
                middle_name = ?, suffix = ?, birth_date = ?, gender = ?, civil_status = ?,
                nationality = ?, address = ?, city = ?, province = ?, postal_code = ?,
                personal_email = ?, personal_phone = ?,
                emergency_contact_name = ?, emergency_contact_relationship = ?, emergency_contact_phone = ?,
                employee_level = ?, employee_category = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['middle_name'] ?: null, $_POST['suffix'] ?: null,
            $_POST['birth_date'] ?: null, $_POST['gender'] ?: 'other',
            $_POST['civil_status'] ?: 'single', $_POST['nationality'] ?: 'Filipino',
            $_POST['address'] ?: null, $_POST['city'] ?: null,
            $_POST['province'] ?: null, $_POST['postal_code'] ?: null,
            $_POST['personal_email'] ?: null, $_POST['personal_phone'] ?: null,
            $_POST['emergency_contact_name'] ?: null,
            $_POST['emergency_contact_relationship'] ?: null,
            $_POST['emergency_contact_phone'] ?: null,
            $_POST['employee_level'] ?: null, $_POST['employee_category'] ?: null,
            $employee_id
        ]);
        $message = "Employee master data updated successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Filters
$search_filter = $_GET['search'] ?? '';
$dept_filter   = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "
    SELECT nh.*, ja.first_name, ja.last_name, ja.email as applicant_email,
           ja.photo_path, ja.application_number,
           ja.birth_date as a_birth, ja.gender as a_gender, ja.address as a_addr,
           ja.city as a_city, ja.province as a_prov, ja.postal_code as a_zip,
           ja.phone as a_phone,
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
    $query .= " AND (ja.first_name LIKE ? OR ja.last_name LIKE ? OR nh.employee_id LIKE ? OR ja.email LIKE ?)";
    $t = "%$search_filter%";
    array_push($params, $t, $t, $t, $t);
}
if (!empty($dept_filter)) { $query .= " AND nh.department = ?"; $params[] = $dept_filter; }
if (!empty($status_filter)) { $query .= " AND nh.status = ?"; $params[] = $status_filter; }
$query .= " ORDER BY nh.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Stats
$stats = ['total' => count($employees), 'onboarding' => 0, 'active' => 0, 'probationary' => 0];
foreach ($employees as $e) {
    if ($e['status'] === 'onboarding') $stats['onboarding']++;
    if ($e['status'] === 'active')     $stats['active']++;
    if ($e['employment_status'] === 'probationary') $stats['probationary']++;
}

// Departments for filter
$stmt = $pdo->query("SELECT department_code, department_name FROM departments WHERE is_active = 1 ORDER BY sort_order");
$departments = $stmt->fetchAll();
?>

<style>
.master-header{background:white;border-radius:20px;padding:25px;margin-bottom:25px;box-shadow:0 10px 30px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;}
.master-header h1{font-size:24px;font-weight:600;color:#2c3e50;margin:0;display:flex;align-items:center;gap:12px;}
.master-header h1 i{color:#0e4c92;background:rgba(14,76,146,0.1);padding:12px;border-radius:15px;font-size:24px;}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-bottom:25px;}
.stat-box{background:white;border-radius:18px;padding:20px;box-shadow:0 6px 20px rgba(0,0,0,0.05);display:flex;align-items:center;gap:15px;}
.stat-box .icon{width:50px;height:50px;border-radius:14px;background:linear-gradient(135deg,#0e4c92,#4086e4);display:flex;align-items:center;justify-content:center;color:white;font-size:22px;}
.stat-box .icon.green{background:linear-gradient(135deg,#27ae60,#2ecc71);}
.stat-box .icon.orange{background:linear-gradient(135deg,#f39c12,#e67e22);}
.stat-box .icon.purple{background:linear-gradient(135deg,#9b59b6,#8e44ad);}
.stat-box .label{font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
.stat-box .value{font-size:24px;font-weight:700;color:#2c3e50;}
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
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;overflow-y:auto;}
.modal.active{display:flex;}
.modal-content{background:white;border-radius:24px;padding:30px;max-width:800px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 30px 60px rgba(0,0,0,0.3);}
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

<div class="master-header">
    <h1><i class="fas fa-id-badge"></i> Employee Master Data</h1>
    <span style="font-size:13px;color:#64748b;">Personal info • Contact • Emergency • Classification</span>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="stats-row">
    <div class="stat-box"><div class="icon"><i class="fas fa-users"></i></div><div><div class="label">Total</div><div class="value"><?= $stats['total'] ?></div></div></div>
    <div class="stat-box"><div class="icon orange"><i class="fas fa-user-clock"></i></div><div><div class="label">Onboarding</div><div class="value"><?= $stats['onboarding'] ?></div></div></div>
    <div class="stat-box"><div class="icon green"><i class="fas fa-user-check"></i></div><div><div class="label">Active</div><div class="value"><?= $stats['active'] ?></div></div></div>
    <div class="stat-box"><div class="icon purple"><i class="fas fa-hourglass-half"></i></div><div><div class="label">Probationary</div><div class="value"><?= $stats['probationary'] ?></div></div></div>
</div>

<div class="filter-bar">
    <form method="GET">
        <input type="hidden" name="page" value="core-hr">
        <input type="hidden" name="subpage" value="employee-master-data">
        <div class="filter-grid">
            <div><label>Search</label><input type="text" name="search" placeholder="Name, ID, Email" value="<?= htmlspecialchars($search_filter) ?>"></div>
            <div><label>Department</label>
                <select name="department">
                    <option value="">All</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['department_code'] ?>" <?= $dept_filter==$d['department_code']?'selected':'' ?>><?= htmlspecialchars($d['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <option value="onboarding" <?= $status_filter=='onboarding'?'selected':'' ?>>Onboarding</option>
                    <option value="active" <?= $status_filter=='active'?'selected':'' ?>>Active</option>
                    <option value="terminated" <?= $status_filter=='terminated'?'selected':'' ?>>Terminated</option>
                    <option value="resigned" <?= $status_filter=='resigned'?'selected':'' ?>>Resigned</option>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <a href="?page=core-hr&subpage=employee-master-data" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
</div>

<div class="table-wrap">
    <?php if (empty($employees)): ?>
        <div style="text-align:center;padding:60px 20px;color:#94a3b8;">
            <i class="fas fa-user-slash" style="font-size:48px;opacity:0.3;display:block;margin-bottom:15px;"></i>
            <h3>No Employees Found</h3>
            <p>Employees appear here once they're hired from Final Selection.</p>
        </div>
    <?php else: ?>
    <table>
        <thead><tr><th>Employee</th><th>Employee ID</th><th>Position</th><th>Department</th><th>Type</th><th>Status</th><th>Hired</th><th>Actions</th></tr></thead>
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
                        <div>
                            <strong><?= htmlspecialchars($full) ?></strong>
                            <div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($emp['personal_email'] ?? $emp['applicant_email'] ?? '') ?></div>
                        </div>
                    </div>
                </td>
                <td><strong><?= htmlspecialchars($emp['employee_id']) ?></strong></td>
                <td><?= htmlspecialchars($emp['position'] ?? $emp['job_title'] ?? '-') ?></td>
                <td style="text-transform:capitalize;"><?= htmlspecialchars(str_replace('_',' ',$emp['department'] ?? '-')) ?></td>
                <td><span class="badge badge-probationary"><?= ucfirst(str_replace('_',' ', $emp['employment_type'] ?? 'probationary')) ?></span></td>
                <td><span class="badge badge-<?= $emp['status'] ?>"><?= ucfirst($emp['status']) ?></span></td>
                <td><?= date('M d, Y', strtotime($emp['hire_date'])) ?></td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick='editEmployee(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES) ?>)'>
                        <i class="fas fa-edit"></i> Edit
                    </button>
                </td>
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
            <h3><i class="fas fa-user-edit" style="color:#0e4c92;"></i> Edit Employee Master Data</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="employee_id" id="edit_id">

            <div class="section-title"><i class="fas fa-user"></i> Personal Information</div>
            <div class="form-grid">
                <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" id="edit_middle_name"></div>
                <div class="form-group"><label>Suffix</label><input type="text" name="suffix" id="edit_suffix" placeholder="Jr., Sr., III"></div>
                <div class="form-group"><label>Birth Date</label><input type="date" name="birth_date" id="edit_birth_date"></div>
                <div class="form-group"><label>Gender</label>
                    <select name="gender" id="edit_gender">
                        <option value="male">Male</option><option value="female">Female</option><option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group"><label>Civil Status</label>
                    <select name="civil_status" id="edit_civil_status">
                        <option value="single">Single</option><option value="married">Married</option>
                        <option value="widowed">Widowed</option><option value="separated">Separated</option><option value="divorced">Divorced</option>
                    </select>
                </div>
                <div class="form-group"><label>Nationality</label><input type="text" name="nationality" id="edit_nationality"></div>
            </div>

            <div class="section-title"><i class="fas fa-phone"></i> Contact &amp; Address</div>
            <div class="form-grid">
                <div class="form-group"><label>Personal Email</label><input type="email" name="personal_email" id="edit_personal_email"></div>
                <div class="form-group"><label>Personal Phone</label><input type="text" name="personal_phone" id="edit_personal_phone"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Address</label><textarea name="address" id="edit_address"></textarea></div>
                <div class="form-group"><label>City</label><input type="text" name="city" id="edit_city"></div>
                <div class="form-group"><label>Province</label><input type="text" name="province" id="edit_province"></div>
                <div class="form-group"><label>Postal Code</label><input type="text" name="postal_code" id="edit_postal_code"></div>
            </div>

            <div class="section-title"><i class="fas fa-heartbeat"></i> Emergency Contact</div>
            <div class="form-grid">
                <div class="form-group"><label>Contact Name</label><input type="text" name="emergency_contact_name" id="edit_emergency_contact_name"></div>
                <div class="form-group"><label>Relationship</label><input type="text" name="emergency_contact_relationship" id="edit_emergency_contact_relationship"></div>
                <div class="form-group"><label>Contact Phone</label><input type="text" name="emergency_contact_phone" id="edit_emergency_contact_phone"></div>
            </div>

            <div class="section-title"><i class="fas fa-sitemap"></i> Classification</div>
            <div class="form-grid">
                <div class="form-group"><label>Employee Level</label><input type="text" name="employee_level" id="edit_employee_level" placeholder="e.g. Staff, Supervisor, Manager"></div>
                <div class="form-group"><label>Employee Category</label><input type="text" name="employee_category" id="edit_employee_category" placeholder="e.g. Rank & File, Confidential"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="save_master_data" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editEmployee(e) {
    document.getElementById('edit_id').value = e.id;
    document.getElementById('edit_middle_name').value = e.middle_name || '';
    document.getElementById('edit_suffix').value = e.suffix || '';
    document.getElementById('edit_birth_date').value = e.birth_date || e.a_birth || '';
    document.getElementById('edit_gender').value = e.gender || e.a_gender || 'other';
    document.getElementById('edit_civil_status').value = e.civil_status || 'single';
    document.getElementById('edit_nationality').value = e.nationality || 'Filipino';
    document.getElementById('edit_personal_email').value = e.personal_email || e.applicant_email || '';
    document.getElementById('edit_personal_phone').value = e.personal_phone || e.a_phone || '';
    document.getElementById('edit_address').value = e.address || e.a_addr || '';
    document.getElementById('edit_city').value = e.city || e.a_city || '';
    document.getElementById('edit_province').value = e.province || e.a_prov || '';
    document.getElementById('edit_postal_code').value = e.postal_code || e.a_zip || '';
    document.getElementById('edit_emergency_contact_name').value = e.emergency_contact_name || '';
    document.getElementById('edit_emergency_contact_relationship').value = e.emergency_contact_relationship || '';
    document.getElementById('edit_emergency_contact_phone').value = e.emergency_contact_phone || '';
    document.getElementById('edit_employee_level').value = e.employee_level || '';
    document.getElementById('edit_employee_category').value = e.employee_category || '';
    document.getElementById('editModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeEditModal(){document.getElementById('editModal').classList.remove('active');document.body.style.overflow='';}
window.onclick=function(e){if(e.target.id==='editModal')closeEditModal();}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeEditModal();});
</script>