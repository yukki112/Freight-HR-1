<?php
// /modules/erm/erm-archives.php
require_once __DIR__ . '/../../includes/erm/erm_functions.php';

$cats = ermCategories();

// Restore
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $pdo->prepare("UPDATE employee_records SET is_archived=0, archived_at=NULL, archived_by=NULL, status='pending' WHERE id=?")
        ->execute([$id]);
    ermAudit($pdo, $id, 0, 'restore', 'Restored from archive');
    echo "<script>window.location='?page=erm&subpage=erm-archives';</script>";
    exit;
}

$archived = $pdo->query("
    SELECT er.*, nh.employee_id AS emp_code, nh.position,
           ja.first_name, ja.last_name,
           u.full_name AS archived_by_name
    FROM employee_records er
    JOIN new_hires nh        ON er.employee_id = nh.id
    JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN users u        ON er.archived_by = u.id
    WHERE er.is_archived = 1
    ORDER BY er.archived_at DESC
")->fetchAll();
?>
<link rel="stylesheet" href="includes/erm/erm_styles.css">

<div class="erm-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-box-archive"></i></div>
        <div>
            <h1>Archives</h1>
            <p>Inactive and old records kept for retention compliance</p>
        </div>
    </div>
</div>

<div class="erm-stats">
    <div class="erm-stat">
        <div class="ico gray"><i class="fas fa-box-archive"></i></div>
        <div><div class="num"><?= count($archived) ?></div><div class="lbl">Archived Records</div></div>
    </div>
</div>

<?php if (empty($archived)): ?>
<div class="erm-panel">
    <div class="erm-panel-body">
        <div class="erm-empty">
            <div class="icon-wrap"><i class="fas fa-box-open"></i></div>
            <h3>No archived records</h3>
            <p>Records you archive will appear here for retention tracking.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="erm-panel">
    <div class="erm-panel-head">
        <div class="left">
            <div class="ico"><i class="fas fa-list"></i></div>
            <div>
                <h3><?= count($archived) ?> Archived Record<?= count($archived) != 1 ? 's' : '' ?></h3>
                <p>Restore to reactivate, or leave archived for compliance</p>
            </div>
        </div>
    </div>
    <div class="erm-panel-body">
        <div class="erm-list">
            <?php foreach ($archived as $a):
                $c = $cats[$a['record_category']] ?? $cats['other'];
            ?>
            <div class="erm-card archived">
                <div class="doc-icon" style="background:<?= $c['color'] ?>;"><i class="fas <?= $c['icon'] ?>"></i></div>
                <div class="doc-info">
                    <div class="doc-title"><?= htmlspecialchars($a['document_title']) ?></div>
                    <div class="doc-meta">
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?> (<?= htmlspecialchars($a['emp_code']) ?>)</span>
                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($c['label']) ?></span>
                        <span><i class="fas fa-clock"></i> Archived <?= $a['archived_at'] ? date('M j, Y', strtotime($a['archived_at'])) : '—' ?></span>
                        <?php if ($a['archived_by_name']): ?>
                        <span><i class="fas fa-user-shield"></i> by <?= htmlspecialchars($a['archived_by_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($a['retention_until']): ?>
                    <div style="font-size:11px;color:#94a3b8;margin-top:6px;">
                        <i class="fas fa-clock-rotate-left"></i>
                        Retention until <?= date('M j, Y', strtotime($a['retention_until'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="doc-actions">
                    <?php if ($a['file_path']): ?>
                    <a href="<?= htmlspecialchars($a['file_path']) ?>" target="_blank" class="erm-action-btn view">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <?php endif; ?>
                    <a href="?page=erm&subpage=erm-archives&restore=<?= $a['id'] ?>"
                       class="erm-action-btn verify" onclick="return confirm('Restore this record?')">
                        <i class="fas fa-rotate-left"></i> Restore
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>