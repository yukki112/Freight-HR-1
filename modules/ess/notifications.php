<?php
// /modules/ess/notifications.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];

// Mark all as read if requested
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE ess_notifications SET is_read = 1 WHERE employee_id = ?")->execute([$empId]);
    header('Location: ?page=comm&subpage=notifications');
    exit;
}

// Mark single as read
if (isset($_GET['read'])) {
    $pdo->prepare("UPDATE ess_notifications SET is_read = 1 WHERE id = ? AND employee_id = ?")
        ->execute([(int)$_GET['read'], $empId]);
    header('Location: ?page=comm&subpage=notifications');
    exit;
}

$notifs = $pdo->prepare("
    SELECT * FROM ess_notifications
    WHERE employee_id = ?
    ORDER BY is_read ASC, created_at DESC
    LIMIT 100
");
$notifs->execute([$empId]);
$notifs = $notifs->fetchAll();

$unread = (int)$pdo->query("SELECT COUNT(*) FROM ess_notifications WHERE employee_id = {$empId} AND is_read = 0")->fetchColumn();
?>

<style>
.ess-page-header { background:linear-gradient(135deg,#0e4c92,#4086e4); border-radius:24px; padding:32px 36px; margin-bottom:25px; color:white; position:relative; overflow:hidden; box-shadow:0 20px 40px rgba(14,76,146,0.2); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; }
.ess-page-header::before { content:''; position:absolute; width:240px; height:240px; background:rgba(255,255,255,0.08); border-radius:50%; top:-100px; right:-60px; }
.ess-page-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.ess-page-header .hdr-icon { width:60px; height:60px; background:rgba(255,255,255,0.18); border:1.5px solid rgba(255,255,255,0.3); border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:26px; }
.ess-page-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; }
.ess-page-header p { font-size:13px; margin:0; opacity:0.85; }
.mark-all-btn { position:relative; z-index:1; padding:12px 22px; background:white; color:#0e4c92; border-radius:14px; font-size:13px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:8px; box-shadow:0 8px 20px rgba(0,0,0,0.15); }
.mark-all-btn:hover { transform:translateY(-2px); }

.notif-panel { background:white; border-radius:22px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.05); border:1px solid #eef2f6; }
.notif-list { display:flex; flex-direction:column; }
.notif-item { padding:18px 22px; border-bottom:1px solid #f1f5f9; display:flex; gap:14px; align-items:flex-start; text-decoration:none; color:inherit; transition:all 0.25s; }
.notif-item:hover { background:#f8fafd; }
.notif-item:last-child { border-bottom:none; }
.notif-item.unread { background:#eff6ff; }
.notif-item.unread:hover { background:#dbeafe; }
.notif-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.notif-icon.info    { background:#dbeafe; color:#1e40af; }
.notif-icon.success { background:#dcfce7; color:#15803d; }
.notif-icon.warning { background:#fef3c7; color:#92400e; }
.notif-icon.danger  { background:#fee2e2; color:#991b1b; }
.notif-body { flex:1; min-width:0; }
.notif-title { font-size:14px; font-weight:700; color:#1e293b; margin-bottom:4px; }
.notif-msg { font-size:12.5px; color:#64748b; line-height:1.55; }
.notif-time { font-size:11px; color:#94a3b8; margin-top:6px; display:flex; align-items:center; gap:5px; }
.notif-dot { width:8px; height:8px; border-radius:50%; background:#0e4c92; margin-top:8px; flex-shrink:0; }

.ess-empty { text-align:center; padding:60px 30px; color:#94a3b8; }
.ess-empty .icon-wrap { width:80px; height:80px; margin:0 auto 20px; background:linear-gradient(135deg,#eef2ff,#e0e7ff); border-radius:24px; display:flex; align-items:center; justify-content:center; font-size:32px; color:#6366f1; }
.ess-empty h3 { font-size:18px; color:#1e293b; margin:0 0 8px; font-weight:700; }
.ess-empty p { font-size:13px; color:#64748b; margin:0; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-bell"></i></div>
        <div>
            <h1>Notifications</h1>
            <p><?= $unread ?> unread notification<?= $unread != 1 ? 's' : '' ?></p>
        </div>
    </div>
    <?php if ($unread > 0): ?>
    <a href="?page=comm&subpage=notifications&mark_all=1" class="mark-all-btn">
        <i class="fas fa-check-double"></i> Mark All as Read
    </a>
    <?php endif; ?>
</div>

<?php if (empty($notifs)): ?>
<div class="notif-panel">
    <div class="ess-empty">
        <div class="icon-wrap"><i class="fas fa-bell-slash"></i></div>
        <h3>All caught up!</h3>
        <p>You have no notifications at the moment.</p>
    </div>
</div>
<?php else: ?>
<div class="notif-panel">
    <div class="notif-list">
        <?php foreach ($notifs as $n):
            $icons = ['info'=>'fa-info-circle','success'=>'fa-check-circle','warning'=>'fa-exclamation-triangle','danger'=>'fa-exclamation-circle'];
        ?>
        <a href="?page=comm&subpage=notifications&read=<?= $n['id'] ?>" class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
            <div class="notif-icon <?= htmlspecialchars($n['type']) ?>">
                <i class="fas <?= $icons[$n['type']] ?? 'fa-bell' ?>"></i>
            </div>
            <div class="notif-body">
                <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                <div class="notif-time">
                    <i class="fas fa-clock"></i>
                    <?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?>
                </div>
            </div>
            <?php if (!$n['is_read']): ?><div class="notif-dot"></div><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>