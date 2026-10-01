<?php
// /modules/erm/erm-audit.php
require_once __DIR__ . '/../../includes/erm/erm_functions.php';

$actionFilter = $_GET['action'] ?? 'all';
$empFilter    = (int)($_GET['emp'] ?? 0);

$where  = '1=1';
$params = [];

if ($actionFilter !== 'all') {
    $where .= " AND a.action = ?";
    $params[] = $actionFilter;
}
if ($empFilter) {
    $where .= " AND a.employee_id = ?";
    $params[] = $empFilter;
}

$stmt = $pdo->prepare("
    SELECT a.*,
           u.full_name AS performer,
           CONCAT(ja.first_name, ' ', ja.last_name) AS emp_name,
           nh.employee_id AS emp_code,
           er.document_title, er.record_category
    FROM employee_records_audit a
    LEFT JOIN users u                    ON a.performed_by = u.id
    LEFT JOIN new_hires nh               ON a.employee_id = nh.id
    LEFT JOIN job_applications ja        ON nh.applicant_id = ja.id
    LEFT JOIN employee_records er        ON a.record_id = er.id
    WHERE {$where}
    ORDER BY a.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actionIcons = [
    'upload'   => ['fa-cloud-upload-alt', '#3b82f6'],
    'verify'   => ['fa-check-circle',     '#16a34a'],
    'reject'   => ['fa-times-circle',     '#dc2626'],
    'edit'     => ['fa-pen',              '#0e4c92'],
    'archive'  => ['fa-box-archive',      '#d97706'],
    'restore'  => ['fa-rotate-left',      '#7c3aed'],
    'delete'   => ['fa-trash',            '#dc2626'],
    'view'     => ['fa-eye',              '#64748b'],
    'download' => ['fa-download',         '#0ea5e9'],
];

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
        <div class="hdr-icon"><i class="fas fa-history"></i></div>
        <div>
            <h1>Audit Trail</h1>
            <p>Who viewed, uploaded, changed, or deleted employee records</p>
        </div>
    </div>
</div>

<!-- Filter tabs -->
<div class="erm-tabs-wrap">
    <div class="erm-tabs">
        <a href="?page=erm&subpage=erm-audit&action=all&emp=<?= $empFilter ?>"
           class="erm-tab <?= $actionFilter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All
        </a>
        <?php foreach ($actionIcons as $key => [$ico, $col]):
        ?>
        <a href="?page=erm&subpage=erm-audit&action=<?= $key ?>&emp=<?= $empFilter ?>"
           class="erm-tab <?= $actionFilter === $key ? 'active' : '' ?>">
            <i class="fas <?= $ico ?>"></i> <?= ucfirst($key) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Filter by employee -->
<div class="erm-panel">
    <div class="erm-panel-body">
        <form method="GET" class="erm-search-bar">
            <input type="hidden" name="page" value="erm">
            <input type="hidden" name="subpage" value="erm-audit">
            <input type="hidden" name="action" value="<?= htmlspecialchars($actionFilter) ?>">
            <select name="emp" onchange="this.form.submit()">
                <option value="0">All Employees</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $empFilter == $e['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e['full_name']) ?> (<?= htmlspecialchars($e['emp_code']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- Logs -->
<div class="erm-panel">
    <div class="erm-panel-head">
        <div class="left">
            <div class="ico"><i class="fas fa-list"></i></div>
            <div>
                <h3><?= count($logs) ?> Log Entr<?= count($logs) != 1 ? 'ies' : 'y' ?></h3>
                <p>Most recent activity</p>
            </div>
        </div>
    </div>
    <div class="erm-panel-body">
        <?php if (empty($logs)): ?>
        <div class="erm-empty">
            <div class="icon-wrap"><i class="fas fa-history"></i></div>
            <h3>No activity found</h3>
            <p>Try changing filters above.</p>
        </div>
        <?php else: ?>
        <div class="erm-list">
            <?php foreach ($logs as $l):
                [$ico, $col] = $actionIcons[$l['action']] ?? ['fa-circle', '#64748b'];
            ?>
            <div class="erm-audit-row">
                <div class="a-icon" style="background:<?= $col ?>20; color:<?= $col ?>;">
                    <i class="fas <?= $ico ?>"></i>
                </div>
                <div class="a-info">
                    <div class="a-title">
                        <strong><?= ucfirst($l['action']) ?></strong>
                        <?php if ($l['document_title']): ?>
                        — <?= htmlspecialchars($l['document_title']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="a-meta">
                        <?php if ($l['emp_name']): ?>
                        <i class="fas fa-user"></i> <?= htmlspecialchars($l['emp_name']) ?>
                        (<?= htmlspecialchars($l['emp_code'] ?? '—') ?>)
                        &nbsp;•&nbsp;
                        <?php endif; ?>
                        by <?= htmlspecialchars($l['performer'] ?? 'System') ?>
                        &nbsp;•&nbsp;
                        <?= date('M j, Y g:i A', strtotime($l['created_at'])) ?>
                        <?php if ($l['ip_address']): ?>
                        &nbsp;•&nbsp; IP: <?= htmlspecialchars($l['ip_address']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($l['description']): ?>
                    <div class="a-meta" style="color:#475569;margin-top:4px;">
                        <?= htmlspecialchars($l['description']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>