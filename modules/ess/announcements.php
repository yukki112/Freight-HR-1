<?php
// /modules/ess/announcements.php
$emp = essGetEmployee($pdo);
$dept = $emp['department'] ?? '';

$announcements = $pdo->prepare("
    SELECT a.*, u.full_name AS author_name
    FROM ess_announcements a
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.is_published = 1
      AND (a.expires_at IS NULL OR a.expires_at > NOW())
      AND (a.target_departments IS NULL OR a.target_departments = '' OR FIND_IN_SET(?, a.target_departments))
    ORDER BY a.created_at DESC
");
$announcements->execute([$dept]);
$announcements = $announcements->fetchAll();

$typePresets = [
    'info'    => ['label' => 'Information', 'icon' => 'fa-circle-info',        'gradient' => 'linear-gradient(135deg,#3b82f6,#60a5fa)', 'bg' => '#eff6ff', 'text' => '#1e40af'],
    'success' => ['label' => 'Success',     'icon' => 'fa-circle-check',       'gradient' => 'linear-gradient(135deg,#16a34a,#22c55e)', 'bg' => '#f0fdf4', 'text' => '#166534'],
    'warning' => ['label' => 'Warning',     'icon' => 'fa-triangle-exclamation','gradient' => 'linear-gradient(135deg,#f59e0b,#fbbf24)', 'bg' => '#fffbeb', 'text' => '#92400e'],
    'danger'  => ['label' => 'Important',   'icon' => 'fa-circle-exclamation','gradient' => 'linear-gradient(135deg,#dc2626,#ef4444)', 'bg' => '#fef2f2', 'text' => '#991b1b'],
];
?>

<style>
.ess-page-header { background:linear-gradient(135deg,#0e4c92,#4086e4); border-radius:24px; padding:32px 36px; margin-bottom:25px; color:white; position:relative; overflow:hidden; box-shadow:0 20px 40px rgba(14,76,146,0.2); }
.ess-page-header::before { content:''; position:absolute; width:240px; height:240px; background:rgba(255,255,255,0.08); border-radius:50%; top:-100px; right:-60px; }
.ess-page-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.ess-page-header .hdr-icon { width:60px; height:60px; background:rgba(255,255,255,0.18); border:1.5px solid rgba(255,255,255,0.3); border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:26px; }
.ess-page-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; }
.ess-page-header p { font-size:13px; margin:0; opacity:0.85; }

.anc-feed { display:flex; flex-direction:column; gap:16px; }
.anc-card { background:white; border-radius:20px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.05); border:1px solid #eef2f6; transition:all 0.3s; }
.anc-card:hover { transform:translateY(-3px); box-shadow:0 16px 40px rgba(14,76,146,0.1); }
.anc-strip { height:5px; }
.anc-inner { padding:22px 26px; }
.anc-head { display:flex; justify-content:space-between; align-items:flex-start; gap:15px; margin-bottom:14px; }
.anc-type-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
.anc-title { font-size:18px; font-weight:700; color:#0f172a; margin:0; line-height:1.3; }
.anc-meta { display:flex; align-items:center; gap:14px; margin-top:8px; font-size:12px; color:#94a3b8; flex-wrap:wrap; }
.anc-content { font-size:14px; color:#475569; line-height:1.7; padding:16px 18px; background:#f8fafc; border-left:3px solid #e2e8f0; border-radius:0 12px 12px 0; margin-top:12px; white-space:pre-wrap; word-break:break-word; }
.anc-expires { margin-top:12px; font-size:11px; color:#92400e; background:#fef3c7; padding:6px 12px; border-radius:20px; display:inline-flex; align-items:center; gap:5px; font-weight:600; }

.ess-empty { text-align:center; padding:60px 30px; color:#94a3b8; }
.ess-empty .icon-wrap { width:80px; height:80px; margin:0 auto 20px; background:linear-gradient(135deg,#eef2ff,#e0e7ff); border-radius:24px; display:flex; align-items:center; justify-content:center; font-size:32px; color:#6366f1; }
.ess-empty h3 { font-size:18px; color:#1e293b; margin:0 0 8px; font-weight:700; }
.ess-empty p { font-size:13px; color:#64748b; margin:0; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-bullhorn"></i></div>
        <div>
            <h1>Announcements</h1>
            <p>Company-wide news and important notices</p>
        </div>
    </div>
</div>

<?php if (empty($announcements)): ?>
<div class="ess-empty">
    <div class="icon-wrap"><i class="fas fa-bullhorn"></i></div>
    <h3>No announcements yet</h3>
    <p>Check back later for company updates and news.</p>
</div>
<?php else: ?>
<div class="anc-feed">
    <?php foreach ($announcements as $a):
        $t = $typePresets[$a['type']] ?? $typePresets['info'];
    ?>
    <div class="anc-card">
        <div class="anc-strip" style="background:<?= $t['gradient'] ?>;"></div>
        <div class="anc-inner">
            <span class="anc-type-badge" style="background:<?= $t['bg'] ?>;color:<?= $t['text'] ?>;">
                <i class="fas <?= $t['icon'] ?>"></i> <?= $t['label'] ?>
            </span>
            <h3 class="anc-title"><?= htmlspecialchars($a['title']) ?></h3>
            <div class="anc-meta">
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($a['author_name'] ?? 'HR Department') ?></span>
                <span><i class="fas fa-clock"></i> <?= date('M j, Y · g:i A', strtotime($a['created_at'])) ?></span>
            </div>
            <div class="anc-content"><?= nl2br(htmlspecialchars($a['content'])) ?></div>
            <?php if ($a['expires_at']): ?>
            <div class="anc-expires">
                <i class="fas fa-hourglass-half"></i> Expires <?= date('M j, Y · g:i A', strtotime($a['expires_at'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>