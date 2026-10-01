<?php
// /modules/erm/erm-verification.php
require_once __DIR__ . '/../../includes/erm/erm_functions.php';

$cats = ermCategories();

// Filter
$statusFilter = $_GET['status'] ?? 'pending';

$where  = "er.is_archived = 0";
$params = [];
if ($statusFilter !== 'all') {
    $where .= " AND er.status = ?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT er.*, nh.employee_id AS emp_code, nh.position,
           ja.first_name, ja.last_name
    FROM employee_records er
    JOIN new_hires nh        ON er.employee_id = nh.id
    JOIN job_applications ja ON nh.applicant_id = ja.id
    WHERE {$where}
    ORDER BY er.created_at DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Counts
$counts = $pdo->query("
    SELECT status, COUNT(*) AS cnt
    FROM employee_records WHERE is_archived = 0
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<link rel="stylesheet" href="includes/erm/erm_styles.css">

<div class="erm-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-clipboard-check"></i></div>
        <div>
            <h1>Verification Queue</h1>
            <p>Review and verify uploaded employee records</p>
        </div>
    </div>
</div>

<!-- Filter tabs -->
<div class="erm-tabs-wrap">
    <div class="erm-tabs">
        <?php
        $filters = [
            'pending'  => 'Pending',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            'all'      => 'All',
        ];
        foreach ($filters as $key => $lbl):
            $cnt = $key === 'all' ? array_sum($counts) : ($counts[$key] ?? 0);
        ?>
        <a href="?page=erm&subpage=erm-verification&status=<?= $key ?>"
           class="erm-tab <?= $statusFilter === $key ? 'active' : '' ?>">
            <?= $lbl ?> <span class="badge"><?= (int)$cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($records)): ?>
<div class="erm-panel">
    <div class="erm-panel-body">
        <div class="erm-empty">
            <div class="icon-wrap"><i class="fas fa-check-circle"></i></div>
            <h3>Nothing to verify</h3>
            <p>All records under this filter have been processed.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="erm-panel">
    <div class="erm-panel-head">
        <div class="left">
            <div class="ico"><i class="fas fa-list-check"></i></div>
            <div>
                <h3><?= count($records) ?> Record<?= count($records) != 1 ? 's' : '' ?></h3>
                <p><?= ucfirst($statusFilter) ?> queue</p>
            </div>
        </div>
    </div>
    <div class="erm-panel-body">
        <div class="erm-list">
            <?php foreach ($records as $r):
                $c = $cats[$r['record_category']] ?? $cats['other'];
            ?>
            <div class="erm-card">
                <div class="doc-icon" style="background:<?= $c['color'] ?>;">
                    <i class="fas <?= $c['icon'] ?>"></i>
                </div>
                <div class="doc-info">
                    <div class="doc-title"><?= htmlspecialchars($r['document_title']) ?></div>
                    <div class="doc-meta">
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?> (<?= htmlspecialchars($r['emp_code']) ?>)</span>
                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($c['label']) ?></span>
                        <span><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                    </div>
                    <div style="margin-top:8px;">
                        <span class="erm-status <?= ermStatusClass($r['status']) ?>"><?= ucfirst($r['status']) ?></span>
                    </div>
                </div>
                <div class="doc-actions">
                    <?php if ($r['file_path']): ?>
                    <a href="<?= htmlspecialchars($r['file_path']) ?>" target="_blank" class="erm-action-btn view">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <?php endif; ?>
                    <?php if ($r['status'] === 'pending'): ?>
                    <a href="?page=erm&subpage=erm-verification&verify=<?= $r['id'] ?>&status=<?= $statusFilter ?>"
                       class="erm-action-btn verify" onclick="return confirm('Verify this record?')">
                        <i class="fas fa-check"></i> Verify
                    </a>
                    <a href="?page=erm&subpage=erm-verification&reject=<?= $r['id'] ?>&status=<?= $statusFilter ?>"
                       class="erm-action-btn reject" onclick="return confirm('Reject this record?')">
                        <i class="fas fa-times"></i> Reject
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
if (isset($_GET['verify'])) {
    $id = (int)$_GET['verify'];
    $pdo->prepare("UPDATE employee_records SET status='verified', verified_by=?, verified_at=NOW() WHERE id=?")
        ->execute([$_SESSION['user_id'] ?? null, $id]);
    ermAudit($pdo, $id, 0, 'verify', 'Verified via queue');
    echo "<script>window.location='?page=erm&subpage=erm-verification&status=" . htmlspecialchars($statusFilter) . "';</script>";
}
if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $pdo->prepare("UPDATE employee_records SET status='rejected' WHERE id=?")->execute([$id]);
    ermAudit($pdo, $id, 0, 'reject', 'Rejected via queue');
    echo "<script>window.location='?page=erm&subpage=erm-verification&status=" . htmlspecialchars($statusFilter) . "';</script>";
}
?>