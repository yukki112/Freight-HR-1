<?php
// /modules/erm/erm-expiration.php
require_once __DIR__ . '/../../includes/erm/erm_functions.php';

$cats = ermCategories();

// Group by urgency
$expired = $pdo->query("
    SELECT er.*, nh.employee_id AS emp_code, ja.first_name, ja.last_name
    FROM employee_records er
    JOIN new_hires nh ON er.employee_id = nh.id
    JOIN job_applications ja ON nh.applicant_id = ja.id
    WHERE er.is_archived = 0 AND er.expiry_date < CURDATE()
    ORDER BY er.expiry_date ASC
")->fetchAll();

$soon = $pdo->query("
    SELECT er.*, nh.employee_id AS emp_code, ja.first_name, ja.last_name,
           DATEDIFF(er.expiry_date, CURDATE()) AS days_left
    FROM employee_records er
    JOIN new_hires nh ON er.employee_id = nh.id
    JOIN job_applications ja ON nh.applicant_id = ja.id
    WHERE er.is_archived = 0
      AND er.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ORDER BY er.expiry_date ASC
")->fetchAll();

// Upcoming (30/60/90 window summary)
$summary = $pdo->query("
    SELECT
        SUM(expiry_date < CURDATE()) AS expired,
        SUM(expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS d30,
        SUM(expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 31 DAY) AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)) AS d60,
        SUM(expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 61 DAY) AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)) AS d90
    FROM employee_records WHERE is_archived = 0
")->fetch();
?>
<link rel="stylesheet" href="includes/erm/erm_styles.css">

<div class="erm-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <h1>Expiration Tracking</h1>
            <p>Monitor expiring IDs, licenses, certificates, and permits</p>
        </div>
    </div>
</div>

<!-- Summary -->
<div class="erm-stats">
    <div class="erm-stat">
        <div class="ico red"><i class="fas fa-triangle-exclamation"></i></div>
        <div><div class="num"><?= (int)$summary['expired'] ?></div><div class="lbl">Expired</div></div>
    </div>
    <div class="erm-stat">
        <div class="ico red"><i class="fas fa-clock"></i></div>
        <div><div class="num"><?= (int)$summary['d30'] ?></div><div class="lbl">≤ 30 Days</div></div>
    </div>
    <div class="erm-stat">
        <div class="ico amber"><i class="fas fa-clock"></i></div>
        <div><div class="num"><?= (int)$summary['d60'] ?></div><div class="lbl">31-60 Days</div></div>
    </div>
    <div class="erm-stat">
        <div class="ico blue"><i class="fas fa-clock"></i></div>
        <div><div class="num"><?= (int)$summary['d90'] ?></div><div class="lbl">61-90 Days</div></div>
    </div>
</div>

<!-- Expired -->
<?php if (!empty($expired)): ?>
<div class="erm-panel">
    <div class="erm-panel-head">
        <div class="left">
            <div class="ico" style="background:linear-gradient(135deg,#dc2626,#ef4444);"><i class="fas fa-triangle-exclamation"></i></div>
            <div>
                <h3>Already Expired (<?= count($expired) ?>)</h3>
                <p>Action required — these have passed their expiry date</p>
            </div>
        </div>
    </div>
    <div class="erm-panel-body">
        <div class="erm-list">
            <?php foreach ($expired as $e):
                $c = $cats[$e['record_category']] ?? $cats['other'];
                $daysAgo = (int)((time() - strtotime($e['expiry_date'])) / 86400);
            ?>
            <div class="erm-card expired">
                <div class="doc-icon" style="background:<?= $c['color'] ?>;"><i class="fas <?= $c['icon'] ?>"></i></div>
                <div class="doc-info">
                    <div class="doc-title"><?= htmlspecialchars($e['document_title']) ?></div>
                    <div class="doc-meta">
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?> (<?= htmlspecialchars($e['emp_code']) ?>)</span>
                        <span><i class="fas fa-calendar"></i> Expired <?= date('M j, Y', strtotime($e['expiry_date'])) ?></span>
                    </div>
                    <div class="doc-expiry expired">
                        <i class="fas fa-hourglass-end"></i> <?= $daysAgo ?> day<?= $daysAgo != 1 ? 's' : '' ?> overdue
                    </div>
                </div>
                <div class="doc-actions">
                    <?php if ($e['file_path']): ?>
                    <a href="<?= htmlspecialchars($e['file_path']) ?>" target="_blank" class="erm-action-btn view">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <?php endif; ?>
                    <a href="?page=erm&subpage=erm-upload&renew=<?= $e['id'] ?>" class="erm-action-btn view">
                        <i class="fas fa-rotate"></i> Renew
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Expiring soon -->
<div class="erm-panel">
    <div class="erm-panel-head">
        <div class="left">
            <div class="ico" style="background:linear-gradient(135deg,#d97706,#f59e0b);"><i class="fas fa-hourglass-half"></i></div>
            <div>
                <h3>Expiring Soon (<?= count($soon) ?>)</h3>
                <p>Within the next 90 days</p>
            </div>
        </div>
    </div>
    <div class="erm-panel-body">
        <?php if (empty($soon)): ?>
        <div class="erm-empty">
            <div class="icon-wrap"><i class="fas fa-check-circle"></i></div>
            <h3>All clear</h3>
            <p>No documents expiring in the next 90 days.</p>
        </div>
        <?php else: ?>
        <div class="erm-list">
            <?php foreach ($soon as $s):
                $c = $cats[$s['record_category']] ?? $cats['other'];
                $days = (int)$s['days_left'];
                $cls = $days <= 30 ? 'expired' : ($days <= 60 ? 'expiring' : '');
            ?>
            <div class="erm-card <?= $cls ?>">
                <div class="doc-icon" style="background:<?= $c['color'] ?>;"><i class="fas <?= $c['icon'] ?>"></i></div>
                <div class="doc-info">
                    <div class="doc-title"><?= htmlspecialchars($s['document_title']) ?></div>
                    <div class="doc-meta">
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?> (<?= htmlspecialchars($s['emp_code']) ?>)</span>
                        <span><i class="fas fa-calendar"></i> Expires <?= date('M j, Y', strtotime($s['expiry_date'])) ?></span>
                    </div>
                    <div class="doc-expiry <?= $days <= 30 ? 'expired' : ($days <= 60 ? 'soon' : 'ok') ?>">
                        <i class="fas fa-clock"></i> <?= $days ?> day<?= $days != 1 ? 's' : '' ?> left
                    </div>
                </div>
                <div class="doc-actions">
                    <?php if ($s['file_path']): ?>
                    <a href="<?= htmlspecialchars($s['file_path']) ?>" target="_blank" class="erm-action-btn view">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <?php endif; ?>
                    <a href="?page=erm&subpage=erm-upload&renew=<?= $s['id'] ?>" class="erm-action-btn view">
                        <i class="fas fa-rotate"></i> Renew
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>