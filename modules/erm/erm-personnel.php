<?php
// /modules/erm/erm-personnel.php
require_once __DIR__ . '/../../includes/erm/erm_functions.php';

$cats         = ermCategories();
$activeTab    = $_GET['tab'] ?? 'all';
$searchQuery  = trim($_GET['q'] ?? '');
$filterEmp    = (int)($_GET['emp'] ?? 0);

// Build query
$where  = ['er.is_archived = 0'];
$params = [];

if ($activeTab !== 'all') {
    $where[] = "er.record_category = ?";
    $params[] = $activeTab;
}
if ($filterEmp) {
    $where[] = "er.employee_id = ?";
    $params[] = $filterEmp;
}
if ($searchQuery) {
    $where[] = "(er.document_title LIKE ? OR er.document_number LIKE ? OR ja.first_name LIKE ? OR ja.last_name LIKE ?)";
    $like = "%$searchQuery%";
    array_push($params, $like, $like, $like, $like);
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT er.*,
           nh.employee_id AS emp_code, nh.position, nh.department,
           ja.first_name, ja.last_name,
           u.full_name AS verifier_name
    FROM employee_records er
    JOIN new_hires nh        ON er.employee_id = nh.id
    JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN users u        ON er.verified_by = u.id
    {$whereSql}
    ORDER BY er.created_at DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Category counts
$catCounts = $pdo->query("
    SELECT record_category, COUNT(*) AS cnt
    FROM employee_records WHERE is_archived = 0
    GROUP BY record_category
")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCount = array_sum($catCounts);

// Employees dropdown
$employees = $pdo->query("
    SELECT nh.id, nh.employee_id AS emp_code,
           CONCAT(ja.first_name, ' ', ja.last_name) AS full_name
    FROM new_hires nh
    JOIN job_applications ja ON nh.applicant_id = ja.id
    ORDER BY ja.first_name
")->fetchAll();
?>
<link rel="stylesheet" href="includes/erm/erm_styles.css">

<div class="erm-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-folder-open"></i></div>
        <div>
            <h1>Personnel Files</h1>
            <p>Browse all employee records by category</p>
        </div>
    </div>
    <a href="?page=erm&subpage=erm-upload" class="hdr-btn">
        <i class="fas fa-cloud-upload-alt"></i> Upload Record
    </a>
</div>

<!-- Tabs -->
<div class="erm-tabs-wrap">
    <div class="erm-tabs">
        <a href="?page=erm&subpage=erm-personnel&tab=all" class="erm-tab <?= $activeTab === 'all' ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i> All
            <span class="badge"><?= $totalCount ?></span>
        </a>
        <?php foreach ($cats as $key => $c):
            $cnt = (int)($catCounts[$key] ?? 0);
        ?>
        <a href="?page=erm&subpage=erm-personnel&tab=<?= $key ?>" class="erm-tab <?= $activeTab === $key ? 'active' : '' ?>">
            <i class="fas <?= $c['icon'] ?>"></i> <?= $c['label'] ?>
            <span class="badge"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Search -->
<div class="erm-panel">
    <div class="erm-panel-body">
        <form method="GET" class="erm-search-bar">
            <input type="hidden" name="page" value="erm">
            <input type="hidden" name="subpage" value="erm-personnel">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            <input type="text" name="q" placeholder="Search by document title, number, or employee name..."
                   value="<?= htmlspecialchars($searchQuery) ?>">
            <select name="emp">
                <option value="0">All Employees</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $filterEmp == $e['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['emp_code']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="erm-btn primary"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</div>

<!-- Records list -->
<?php if (empty($records)): ?>
<div class="erm-panel">
    <div class="erm-panel-body">
        <div class="erm-empty">
            <div class="icon-wrap"><i class="fas fa-folder-open"></i></div>
            <h3>No records found</h3>
            <p>Try changing filters or upload a new record.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="erm-panel">
    <div class="erm-panel-head">
        <div class="left">
            <div class="ico"><i class="fas fa-file-alt"></i></div>
            <div>
                <h3><?= count($records) ?> Record<?= count($records) != 1 ? 's' : '' ?></h3>
                <p><?= $activeTab === 'all' ? 'All categories' : htmlspecialchars($cats[$activeTab]['label'] ?? '') ?></p>
            </div>
        </div>
    </div>
    <div class="erm-panel-body">
        <div class="erm-list">
            <?php foreach ($records as $r):
                $c = $cats[$r['record_category']] ?? $cats['other'];
                $expiryCls = '';
                $expiryText = '';
                if ($r['expiry_date']) {
                    $days = (int)((strtotime($r['expiry_date']) - time()) / 86400);
                    if ($days < 0) { $expiryCls = 'expired'; $expiryText = abs($days) . ' days overdue'; }
                    elseif ($days <= 30) { $expiryCls = 'soon'; $expiryText = 'Expires in ' . $days . ' days'; }
                    else { $expiryCls = 'ok'; $expiryText = 'Expires ' . date('M j, Y', strtotime($r['expiry_date'])); }
                }
            ?>
            <div class="erm-card <?= $expiryCls === 'expired' ? 'expired' : ($expiryCls === 'soon' ? 'expiring' : '') ?>">
                <div class="doc-icon" style="background:<?= $c['color'] ?>;">
                    <i class="fas <?= $c['icon'] ?>"></i>
                </div>
                <div class="doc-info">
                    <div class="doc-title"><?= htmlspecialchars($r['document_title']) ?></div>
                    <div class="doc-meta">
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?> (<?= htmlspecialchars($r['emp_code']) ?>)</span>
                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($r['document_type']) ?></span>
                        <?php if ($r['document_number']): ?>
                        <code><?= htmlspecialchars($r['document_number']) ?></code>
                        <?php endif; ?>
                        <span><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                    </div>
                    <?php if ($expiryText): ?>
                    <div class="doc-expiry <?= $expiryCls ?>">
                        <i class="fas fa-hourglass-half"></i> <?= $expiryText ?>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top:8px;">
                        <span class="erm-status <?= ermStatusClass($r['status']) ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="doc-actions">
                    <?php if ($r['file_path']): ?>
                    <a href="<?= htmlspecialchars($r['file_path']) ?>" target="_blank" class="erm-action-btn view">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <?php endif; ?>
                    <?php if ($r['status'] !== 'verified'): ?>
                    <a href="?page=erm&subpage=erm-personnel&tab=<?= $activeTab ?>&verify=<?= $r['id'] ?>"
                       class="erm-action-btn verify" onclick="return confirm('Verify this record?')">
                        <i class="fas fa-check"></i> Verify
                    </a>
                    <?php endif; ?>
                    <a href="?page=erm&subpage=erm-personnel&tab=<?= $activeTab ?>&archive=<?= $r['id'] ?>"
                       class="erm-action-btn archive" onclick="return confirm('Archive this record?')">
                        <i class="fas fa-box-archive"></i>
                    </a>
                    <a href="?page=erm&subpage=erm-personnel&tab=<?= $activeTab ?>&delete=<?= $r['id'] ?>"
                       class="erm-action-btn delete" onclick="return confirm('Delete permanently? This cannot be undone.')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Handle GET actions inside this page (uses same $pdo)
if (isset($_GET['verify'])) {
    $id = (int)$_GET['verify'];
    $pdo->prepare("UPDATE employee_records SET status='verified', verified_by=?, verified_at=NOW() WHERE id=?")
        ->execute([$_SESSION['user_id'] ?? null, $id]);
    ermAudit($pdo, $id, $_SESSION['ess_employee_id'] ?? 0, 'verify', 'Verified via Personnel Files');
    echo "<script>window.location='?page=erm&subpage=erm-personnel&tab=" . htmlspecialchars($activeTab) . "';</script>";
}
if (isset($_GET['archive'])) {
    $id = (int)$_GET['archive'];
    $pdo->prepare("UPDATE employee_records SET is_archived=1, archived_at=NOW(), archived_by=?, status='archived' WHERE id=?")
        ->execute([$_SESSION['user_id'] ?? null, $id]);
    ermAudit($pdo, $id, 0, 'archive', 'Archived via Personnel Files');
    echo "<script>window.location='?page=erm&subpage=erm-personnel&tab=" . htmlspecialchars($activeTab) . "';</script>";
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT file_path FROM employee_records WHERE id=?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch();
    if ($rec && $rec['file_path'] && file_exists(__DIR__ . '/../../' . $rec['file_path'])) {
        @unlink(__DIR__ . '/../../' . $rec['file_path']);
    }
    $pdo->prepare("DELETE FROM employee_records WHERE id=?")->execute([$id]);
    echo "<script>window.location='?page=erm&subpage=erm-personnel&tab=" . htmlspecialchars($activeTab) . "';</script>";
}
?>