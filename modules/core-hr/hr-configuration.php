<?php
// modules/core-hr/hr-configuration.php
$page_title = "HR Configuration";

$message = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'departments';

// Handle add department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO departments (department_code, department_name, description, is_active, sort_order)
            VALUES (?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            strtolower(str_replace(' ', '_', trim($_POST['department_code']))),
            trim($_POST['department_name']),
            $_POST['description'] ?: null,
            (int)($_POST['sort_order'] ?? 0)
        ]);
        $message = "Department added successfully.";
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
}

// Handle add position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_position'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO positions (position_code, position_title, department_id, level, is_active, sort_order)
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            strtoupper(trim($_POST['position_code'])),
            trim($_POST['position_title']),
            (int)$_POST['department_id'],
            (int)($_POST['level'] ?? 5),
            (int)($_POST['sort_order'] ?? 0)
        ]);
        $message = "Position added successfully.";
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
}

// Handle delete
if (isset($_GET['delete_dept'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([(int)$_GET['delete_dept']]);
        $message = "Department deleted.";
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
}

// Fetch data
$departments = $pdo->query("SELECT * FROM departments ORDER BY sort_order, department_name")->fetchAll();
$positions = $pdo->query("
    SELECT p.*, d.department_name 
    FROM positions p
    LEFT JOIN departments d ON p.department_id = d.id
    ORDER BY d.department_name, p.sort_order
")->fetchAll();

// Group positions by department
$positions_by_dept = [];
foreach ($positions as $p) {
    $key = $p['department_name'] ?? 'Unassigned';
    $positions_by_dept[$key][] = $p;
}
?>

<style>
.cfg-header{background:white;border-radius:20px;padding:25px;margin-bottom:25px;box-shadow:0 10px 30px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;}
.cfg-header h1{font-size:24px;font-weight:600;color:#2c3e50;margin:0;display:flex;align-items:center;gap:12px;}
.cfg-header h1 i{color:#0e4c92;background:rgba(14,76,146,0.1);padding:12px;border-radius:15px;font-size:24px;}
.tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
.tab{padding:10px 20px;border-radius:12px;font-size:13px;font-weight:600;cursor:pointer;background:white;border:1px solid #eef2f6;color:#64748b;transition:all 0.3s;text-decoration:none;}
.tab:hover{background:rgba(14,76,146,0.05);}
.tab.active{background:linear-gradient(135deg,#0e4c92,#4086e4);color:white;border-color:#0e4c92;}
.panel{background:white;border-radius:20px;padding:25px;box-shadow:0 10px 30px rgba(0,0,0,0.05);margin-bottom:25px;}
.panel h2{font-size:18px;font-weight:600;color:#2c3e50;margin:0 0 15px;display:flex;align-items:center;gap:10px;}
.panel h2 i{color:#0e4c92;}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:15px;}
.card{background:#f8fafd;border-radius:14px;padding:15px;border:1px solid #eef2f6;transition:all 0.3s;}
.card:hover{background:white;border-color:#0e4c92;box-shadow:0 6px 15px rgba(14,76,146,0.1);}
.card .title{font-size:15px;font-weight:600;color:#2c3e50;margin-bottom:5px;}
.card .subtitle{font-size:12px;color:#64748b;}
.card .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:600;background:rgba(14,76,146,0.1);color:#0e4c92;margin-top:8px;}
.btn{padding:10px 20px;border-radius:12px;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;border:1px solid transparent;}
.btn-primary{background:linear-gradient(135deg,#0e4c92,#4086e4);color:white;}
.btn-outline{background:transparent;border:1px solid #0e4c92;color:#0e4c92;}
.btn-sm{padding:7px 14px;font-size:12px;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;}
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group label{font-size:12px;font-weight:600;color:#64748b;}
.form-group input,.form-group select,.form-group textarea{padding:11px;border:1px solid #eef2f6;border-radius:10px;font-size:13px;background:white;font-family:inherit;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#0e4c92;box-shadow:0 0 0 3px rgba(14,76,146,0.1);}
.form-group textarea{min-height:60px;resize:vertical;}
.alert{padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14px;}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
.divider{height:1px;background:#eef2f6;margin:20px 0;}
</style>

<div class="cfg-header">
    <h1><i class="fas fa-cogs"></i> HR Configuration</h1>
    <span style="font-size:13px;color:#64748b;">Departments • Positions • Master Data</span>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="tabs">
    <a href="?page=core-hr&subpage=hr-configuration&tab=departments" class="tab <?= $active_tab=='departments'?'active':'' ?>"><i class="fas fa-building"></i> Departments</a>
    <a href="?page=core-hr&subpage=hr-configuration&tab=positions" class="tab <?= $active_tab=='positions'?'active':'' ?>"><i class="fas fa-briefcase"></i> Positions</a>
</div>

<?php if ($active_tab === 'departments'): ?>
<!-- Departments Panel -->
<div class="panel">
    <h2><i class="fas fa-plus-circle"></i> Add New Department</h2>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group"><label>Department Code *</label><input type="text" name="department_code" required placeholder="e.g. logistics"></div>
            <div class="form-group"><label>Department Name *</label><input type="text" name="department_name" required placeholder="e.g. Logistics Department"></div>
            <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div>
            <div class="form-group" style="grid-column:1/-1;"><label>Description</label><textarea name="description"></textarea></div>
        </div>
        <div style="margin-top:15px;text-align:right;">
            <button type="submit" name="add_department" class="btn btn-primary"><i class="fas fa-save"></i> Add Department</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2><i class="fas fa-building"></i> Departments (<?= count($departments) ?>)</h2>
    <div class="grid">
        <?php foreach ($departments as $d): ?>
        <div class="card">
            <div class="title"><?= htmlspecialchars($d['department_name']) ?></div>
            <div class="subtitle"><?= htmlspecialchars($d['description'] ?: 'No description') ?></div>
            <span class="badge"><?= htmlspecialchars($d['department_code']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($active_tab === 'positions'): ?>
<!-- Positions Panel -->
<div class="panel">
    <h2><i class="fas fa-plus-circle"></i> Add New Position</h2>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group"><label>Position Code *</label><input type="text" name="position_code" required placeholder="e.g. LOG-SUP"></div>
            <div class="form-group"><label>Position Title *</label><input type="text" name="position_title" required placeholder="e.g. Logistics Supervisor"></div>
            <div class="form-group"><label>Department *</label>
                <select name="department_id" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Level</label>
                <select name="level">
                    <option value="1">1 - Executive</option>
                    <option value="2">2 - Director</option>
                    <option value="3">3 - Manager</option>
                    <option value="4">4 - Supervisor</option>
                    <option value="5" selected>5 - Staff</option>
                </select>
            </div>
            <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div>
        </div>
        <div style="margin-top:15px;text-align:right;">
            <button type="submit" name="add_position" class="btn btn-primary"><i class="fas fa-save"></i> Add Position</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2><i class="fas fa-briefcase"></i> Positions by Department</h2>
    <?php foreach ($positions_by_dept as $dept_name => $pos_list): ?>
    <div style="margin-bottom:20px;">
        <h3 style="font-size:15px;color:#0e4c92;font-weight:700;margin:0 0 12px;"><?= htmlspecialchars($dept_name) ?> (<?= count($pos_list) ?>)</h3>
        <div class="grid">
            <?php foreach ($pos_list as $p): ?>
            <div class="card">
                <div class="title"><?= htmlspecialchars($p['position_title']) ?></div>
                <div class="subtitle">Code: <?= htmlspecialchars($p['position_code']) ?></div>
                <span class="badge">Level <?= (int)$p['level'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>