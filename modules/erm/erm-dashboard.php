<?php
// /modules/erm/erm-dashboard.php
require_once __DIR__ . '/../../includes/erm/erm_functions.php';

$stats    = ermStats($pdo);
$cats     = ermCategories();
$catCounts = $pdo->query("
    SELECT record_category, COUNT(*) AS cnt
    FROM employee_records WHERE is_archived = 0
    GROUP BY record_category
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Expiring soon
$expiring = $pdo->query("
    SELECT er.*, ja.first_name, ja.last_name, nh.employee_id AS emp_code
    FROM employee_records er
    JOIN new_hires nh ON er.employee_id = nh.id
    JOIN job_applications ja ON nh.applicant_id = ja.id
    WHERE er.is_archived = 0
      AND er.expiry_date IS NOT NULL
      AND er.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    ORDER BY er.expiry_date ASC
    LIMIT 8
")->fetchAll();

// Recent activity
$recent = $pdo->query("
    SELECT a.*, u.full_name AS performer,
           CONCAT(ja.first_name, ' ', ja.last_name) AS emp_name
    FROM employee_records_audit a
    LEFT JOIN users u        ON a.performed_by = u.id
    LEFT JOIN new_hires nh   ON a.employee_id = nh.id
    LEFT JOIN job_applications ja ON nh.applicant_id = ja.id
    ORDER BY a.created_at DESC LIMIT 8
")->fetchAll();

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
?>
<link rel="stylesheet" href="includes/erm/erm_styles.css">

<div class="erm-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-folder-tree"></i></div>
        <div>
            <h1>Employee Records</h1>
            <p>Central repository for all official employee documents and HR files</p>
        </div>
    </div>
    <a href="?page=erm&subpage=erm-upload" class="hdr-btn">
        <i class="fas fa-cloud-upload-alt"></i> Upload Record
    </a>
</div>

<!-- Stats -->
<div class="erm-stats">
    <div class="erm-stat">
        <div class="ico blue"><i class="fas fa-folder"></i></div>
        <div><div class="num"><?= (int)$stats['total'] ?></div><div class="lbl">Total Records</div></div>
    </div>
    <div class="erm-stat">
        <div class="ico green"><i class="fas fa-circle-check"></i></div>
        <div><div class="num"><?= (int)$stats['verified'] ?></div><div class="lbl">Verified</div></div>
    </div>
    <div class="erm-stat">
        <div class="ico amber"><i class="fas fa-clock"></i></div>
        <div><div class="num"><?= (int)$stats['pending'] ?></div><div class="lbl">Pending</div></div>
    </div>
    <div class="erm-stat">
        <div class="ico red"><i class="fas fa-triangle-exclamation"></i></div>
        <div><div class="num"><?= (int)$stats['expired'] ?></div><div class="lbl">Expired</div></div>
    </div>
    <div class="erm-stat">
        <div class="ico purple"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="num"><?= (int)$stats['expiring_soon'] ?></div><div class="lbl">Expiring (30d)</div></div>
    </div>
    <div class="erm-stat">
        <div class="ico gray"><i class="fas fa-box-archive"></i></div>
        <div><div class="num"><?= (int)$stats['archived'] ?></div><div class="lbl">Archived</div></div>
    </div>
</div>

<!-- Category grid -->
<div class="erm-panel">
    <div class="erm-panel-head">
        <div class="left">
            <div class="ico"><i class="fas fa-layer-group"></i></div>
            <div>
                <h3>Record Categories</h3>
                <p>Click a category to browse records</p>
            </div>
        </div>
    </div>
    <div class="erm-panel-body">
        <div class="erm-cat-grid">
            <?php foreach ($cats as $key => $c):
                $cnt = (int)($catCounts[$key] ?? 0);
            ?>
            <a href="?page=erm&subpage=erm-personnel&tab=<?= $key ?>" class="erm-cat-card">
                <div class="icon" style="background: <?= $c['color'] ?>;">
                    <i class="fas <?= $c['icon'] ?>"></i>
                </div>
                <div class="info">
                    <div class="label"><?= $c['label'] ?></div>
                    <div class="count"><?= $cnt ?> record<?= $cnt != 1 ? 's' : '' ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Two-column -->
<div class="erm-dash-grid">

    <!-- Expiring Soon -->
    <div class="erm-panel">
        <div class="erm-panel-head">
            <div class="left">
                <div class="ico"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <h3>Expiring Documents</h3>
                    <p>Next 60 days</p>
                </div>
            </div>
            <a href="?page=erm&subpage=erm-expiration" style="font-size:12px;color:#0e4c92;text-decoration:none;font-weight:600;">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="erm-panel-body">
            <?php if (empty($expiring)): ?>
            <div class="erm-empty">
                <div class="icon-wrap"><i class="fas fa-check-circle"></i></div>
                <h3>All caught up</h3>
                <p>No documents expiring in the next 60 days.</p>
            </div>
            <?php else: ?>
            <?php foreach ($expiring as $e):
                $days = (int)((strtotime($e['expiry_date']) - time()) / 86400);
                $cls  = $days < 0 ? 'expired' : ($days <= 30 ? 'soon' : 'ok');
                $cat  = $cats[$e['record_category']] ?? $cats['other'];
            ?>
            <div class="erm-audit-row">
                <div class="a-icon" style="background:<?= $cat['color'] ?>20; color:<?= $cat['color'] ?>;">
                    <i class="fas <?= $cat['icon'] ?>"></i>
                </div>
                <div class="a-info">
                    <div class="a-title"><?= htmlspecialchars($e['document_title']) ?></div>
                    <div class="a-meta">
                        <?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?>
                        (<?= htmlspecialchars($e['emp_code']) ?>)
                    </div>
                </div>
                <div class="doc-expiry <?= $cls ?>" style="margin:0;">
                    <i class="fas fa-clock"></i>
                    <?= $days < 0 ? abs($days) . ' days ago' : ($days == 0 ? 'Today' : 'in ' . $days . ' days') ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="erm-panel">
        <div class="erm-panel-head">
            <div class="left">
                <div class="ico"><i class="fas fa-history"></i></div>
                <div>
                    <h3>Recent Activity</h3>
                    <p>Audit trail</p>
                </div>
            </div>
            <a href="?page=erm&subpage=erm-audit" style="font-size:12px;color:#0e4c92;text-decoration:none;font-weight:600;">
                Full Log <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="erm-panel-body">
            <?php if (empty($recent)): ?>
            <div class="erm-empty">
                <div class="icon-wrap"><i class="fas fa-history"></i></div>
                <h3>No activity yet</h3>
                <p>Records you upload will show up here.</p>
            </div>
            <?php else: ?>
            <?php foreach ($recent as $r):
                [$ico, $col] = $actionIcons[$r['action']] ?? ['fa-circle', '#64748b'];
            ?>
            <div class="erm-audit-row">
                <div class="a-icon" style="background:<?= $col ?>20; color:<?= $col ?>;">
                    <i class="fas <?= $ico ?>"></i>
                </div>
                <div class="a-info">
                    <div class="a-title">
                        <strong><?= ucfirst($r['action']) ?></strong>
                        — <?= htmlspecialchars($r['emp_name'] ?? 'Unknown') ?>
                    </div>
                    <div class="a-meta">
                        by <?= htmlspecialchars($r['performer'] ?? 'System') ?>
                        • <?= date('M j, Y g:i A', strtotime($r['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>