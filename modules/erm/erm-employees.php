<?php
// /modules/erm/erm-employees.php
require_once __DIR__ . '/../../includes/erm/erm_functions.php';

$search = trim($_GET['q'] ?? '');
$where  = "nh.status IN ('active','onboarding')";
$params = [];
if ($search) {
    $where .= " AND (ja.first_name LIKE ? OR ja.last_name LIKE ? OR nh.employee_id LIKE ? OR nh.position LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}

$stmt = $pdo->prepare("
    SELECT nh.id, nh.employee_id AS emp_code, nh.position, nh.department,
           nh.status AS emp_status,
           ja.first_name, ja.last_name, ja.email AS applicant_email,
           nh.personal_email,
           (SELECT COUNT(*) FROM employee_records er WHERE er.employee_id = nh.id AND er.is_archived = 0) AS doc_count,
           (SELECT COUNT(*) FROM employee_records er WHERE er.employee_id = nh.id AND er.expiry_date IS NOT NULL AND er.expiry_date < CURDATE() AND er.is_archived = 0) AS expired_count
    FROM new_hires nh
    JOIN job_applications ja ON nh.applicant_id = ja.id
    WHERE {$where}
    ORDER BY ja.first_name
");
$stmt->execute($params);
$employees = $stmt->fetchAll();
?>
<link rel="stylesheet" href="includes/erm/erm_styles.css">

<div class="erm-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-users"></i></div>
        <div>
            <h1>Employee 201 Files</h1>
            <p>Central personnel file — click an employee to view their full record</p>
        </div>
    </div>
    <a href="?page=erm&subpage=erm-upload" class="hdr-btn">
        <i class="fas fa-cloud-upload-alt"></i> Upload Record
    </a>
</div>

<div class="erm-panel">
    <div class="erm-panel-body">
        <form method="GET" class="erm-search-bar">
            <input type="hidden" name="page" value="erm">
            <input type="hidden" name="subpage" value="erm-employees">
            <input type="text" name="q" placeholder="Search by name, employee ID, or position..."
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="erm-btn primary"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</div>

<?php if (empty($employees)): ?>
<div class="erm-panel">
    <div class="erm-panel-body">
        <div class="erm-empty">
            <div class="icon-wrap"><i class="fas fa-users"></i></div>
            <h3>No employees found</h3>
            <p>Try a different search term.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="erm-panel">
    <div class="erm-panel-head">
        <div class="left">
            <div class="ico"><i class="fas fa-list"></i></div>
            <div>
                <h3><?= count($employees) ?> Employee<?= count($employees) != 1 ? 's' : '' ?></h3>
                <p>Each card is a 201 personnel file</p>
            </div>
        </div>
    </div>
    <div class="erm-panel-body">
        <div class="erm-emp-grid">
            <?php foreach ($employees as $e):
                $initial = strtoupper(substr($e['first_name'] ?? 'E', 0, 1));
            ?>
            <a href="?page=erm&subpage=erm-employee-file&emp=<?= $e['id'] ?>" class="erm-emp-card">
                <div class="emp-avatar"><?= htmlspecialchars($initial) ?></div>
                <div class="emp-name"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></div>
                <div class="emp-role">
                    <i class="fas fa-id-badge"></i> <?= htmlspecialchars($e['emp_code']) ?>
                    &nbsp;·&nbsp;
                    <i class="fas fa-briefcase"></i> <?= htmlspecialchars($e['position'] ?? '—') ?>
                </div>
                <div class="emp-stats">
                    <div class="stat-item">
                        <div class="stat-num"><?= (int)$e['doc_count'] ?></div>
                        <div class="stat-lbl">Records</div>
                    </div>
                    <?php if ($e['expired_count'] > 0): ?>
                    <div class="stat-item">
                        <div class="stat-num" style="color:#dc2626;"><?= (int)$e['expired_count'] ?></div>
                        <div class="stat-lbl">Expired</div>
                    </div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>