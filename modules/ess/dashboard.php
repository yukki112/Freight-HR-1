<?php
// /modules/ess/dashboard.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];

// Stats
$stats = [
    'pending_requests' => (int)$pdo->query("SELECT COUNT(*) FROM ess_requests WHERE employee_id = {$empId} AND status = 'pending'")->fetchColumn(),
    'unread_notifs'    => (int)$pdo->query("SELECT COUNT(*) FROM ess_notifications WHERE employee_id = {$empId} AND is_read = 0")->fetchColumn(),
    'leave_balance'    => 15 - (int)$pdo->query("SELECT COALESCE(SUM(total_days),0) FROM ess_leave_requests WHERE employee_id = {$empId} AND status = 'approved' AND YEAR(start_date) = YEAR(NOW())")->fetchColumn(),
    'attendance_today' => $pdo->query("SELECT COUNT(*) FROM attendance WHERE employee_id = {$empId} AND date = CURDATE()")->fetchColumn(),
];

// Recent requests
$recentRequests = $pdo->query("
    SELECT * FROM ess_requests WHERE employee_id = {$empId} ORDER BY created_at DESC LIMIT 5
")->fetchAll();

// Recent announcements (targeted to this employee's dept or all)
$dept = $emp['department'] ?? '';
$announcements = $pdo->query("
    SELECT * FROM ess_announcements
    WHERE is_published = 1
      AND (expires_at IS NULL OR expires_at > NOW())
      AND (target_departments IS NULL OR target_departments = '' OR FIND_IN_SET('{$dept}', target_departments))
    ORDER BY created_at DESC LIMIT 3
")->fetchAll();

// Upcoming interviews (not relevant for employee, skip)
// Recent activity
$recentActivity = $pdo->query("
    SELECT * FROM ess_notifications
    WHERE employee_id = {$empId}
    ORDER BY created_at DESC LIMIT 5
")->fetchAll();
?>

<style>
.ess-dash-hero {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 24px;
    padding: 36px 40px;
    margin-bottom: 25px;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(14,76,146,0.2);
}
.ess-dash-hero::before {
    content:''; position:absolute; width:280px; height:280px;
    background: rgba(255,255,255,0.08); border-radius:50%;
    top:-120px; right:-80px;
}
.ess-dash-hero::after {
    content:''; position:absolute; width:160px; height:160px;
    background: rgba(255,255,255,0.05); border-radius:50%;
    bottom:-60px; right:140px;
}
.ess-dash-hero .hero-inner { position:relative; z-index:1; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; }
.ess-dash-hero h1 { font-size:26px; font-weight:700; margin:0 0 8px; letter-spacing:-0.3px; }
.ess-dash-hero p  { font-size:14px; margin:0; opacity:0.9; }
.ess-dash-hero .hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,0.18);
    border: 1.5px solid rgba(255,255,255,0.3);
    padding: 10px 18px; border-radius: 14px;
    font-size: 13px; font-weight: 600;
    backdrop-filter: blur(10px);
}

.ess-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:25px; }
.ess-stat {
    background:white; border-radius:20px; padding:22px 24px;
    box-shadow:0 10px 30px rgba(0,0,0,0.05);
    border:1px solid #eef2f6;
    display:flex; justify-content:space-between; align-items:flex-start;
    transition:all 0.3s; position:relative; overflow:hidden;
}
.ess-stat::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
    background: linear-gradient(90deg, #0e4c92, #4086e4);
}
.ess-stat:hover { transform:translateY(-4px); box-shadow:0 16px 40px rgba(14,76,146,0.1); }
.ess-stat .info .num { font-size:32px; font-weight:800; color:#0e4c92; line-height:1; }
.ess-stat .info .lbl { font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:0.6px; font-weight:600; margin-top:6px; }
.ess-stat .ico {
    width:52px; height:52px; border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:22px; flex-shrink:0;
}
.ess-stat .ico.blue   { background:rgba(14,76,146,0.1);  color:#0e4c92; }
.ess-stat .ico.green  { background:rgba(22,163,74,0.12); color:#16a34a; }
.ess-stat .ico.amber  { background:rgba(245,158,11,0.12);color:#d97706; }
.ess-stat .ico.purple { background:rgba(139,92,246,0.12);color:#7c3aed; }

.ess-dash-grid { display:grid; grid-template-columns:2fr 1fr; gap:22px; }
@media (max-width: 1024px) { .ess-dash-grid { grid-template-columns:1fr; } }

.ess-panel {
    background:white; border-radius:22px; overflow:hidden;
    box-shadow:0 10px 30px rgba(0,0,0,0.05);
    border:1px solid #eef2f6; margin-bottom:22px;
}
.ess-panel-head {
    padding:18px 24px;
    background:linear-gradient(135deg,#f8fafd 0%,#f1f5f9 100%);
    border-bottom:1px solid #eef2f6;
    display:flex; align-items:center; gap:14px; justify-content:space-between;
}
.ess-panel-head .left { display:flex; align-items:center; gap:14px; }
.ess-panel-head .ico {
    width:40px; height:40px; border-radius:12px;
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; display:flex; align-items:center; justify-content:center;
    font-size:17px; box-shadow:0 6px 15px rgba(14,76,146,0.25);
}
.ess-panel-head h3 { margin:0; font-size:15px; font-weight:700; color:#1e293b; }
.ess-panel-head .view-all {
    font-size:12px; color:#0e4c92; text-decoration:none;
    font-weight:600; display:inline-flex; align-items:center; gap:5px;
}
.ess-panel-head .view-all:hover { text-decoration:underline; }
.ess-panel-body { padding:20px 24px; }

.ess-request-row {
    display:flex; align-items:center; gap:14px;
    padding:14px; border-radius:12px;
    background:#f8fafd; margin-bottom:8px;
    transition:all 0.25s; text-decoration:none; color:inherit;
}
.ess-request-row:hover { background:#f1f5f9; }
.ess-request-row:last-child { margin-bottom:0; }
.ess-request-row .r-icon {
    width:40px; height:40px; border-radius:12px;
    background:rgba(14,76,146,0.1); color:#0e4c92;
    display:flex; align-items:center; justify-content:center;
    font-size:16px; flex-shrink:0;
}
.ess-request-row .r-info { flex:1; min-width:0; }
.ess-request-row .r-title { font-size:13.5px; font-weight:600; color:#1e293b; margin-bottom:3px; }
.ess-request-row .r-meta { font-size:11px; color:#94a3b8; }
.ess-request-row .r-status {
    padding:4px 11px; border-radius:20px;
    font-size:10.5px; font-weight:700;
    text-transform:uppercase; letter-spacing:0.4px;
}
.r-status.pending    { background:#fef3c7; color:#92400e; }
.r-status.approved   { background:#dcfce7; color:#166534; }
.r-status.rejected   { background:#fee2e2; color:#991b1b; }
.r-status.completed  { background:#e0e7ff; color:#3730a3; }
.r-status.cancelled  { background:#f1f5f9; color:#64748b; }

.ess-announce {
    padding:16px; border-radius:14px;
    background:#f8fafd; margin-bottom:12px;
    border-left:4px solid #0e4c92;
    text-decoration:none; color:inherit;
    display:block; transition:all 0.25s;
}
.ess-announce:hover { background:#f1f5f9; transform:translateX(3px); }
.ess-announce:last-child { margin-bottom:0; }
.ess-announce .a-title { font-size:14px; font-weight:700; color:#1e293b; margin-bottom:5px; }
.ess-announce .a-excerpt { font-size:12px; color:#64748b; line-height:1.5; margin-bottom:6px; }
.ess-announce .a-date { font-size:11px; color:#94a3b8; }
.ess-announce.type-info    { border-left-color:#3b82f6; }
.ess-announce.type-success { border-left-color:#16a34a; }
.ess-announce.type-warning { border-left-color:#f59e0b; }
.ess-announce.type-danger  { border-left-color:#dc2626; }

.ess-activity-row {
    display:flex; gap:12px; padding:12px 0;
    border-bottom:1px dashed #e2e8f0;
}
.ess-activity-row:last-child { border-bottom:none; }
.ess-activity-row .dot {
    width:10px; height:10px; border-radius:50%;
    background:#0e4c92; margin-top:6px; flex-shrink:0;
}
.ess-activity-row .a-content { flex:1; }
.ess-activity-row .a-title { font-size:13px; font-weight:600; color:#1e293b; margin-bottom:3px; }
.ess-activity-row .a-msg { font-size:12px; color:#64748b; line-height:1.5; }
.ess-activity-row .a-time { font-size:11px; color:#94a3b8; margin-top:4px; }

.ess-empty {
    text-align:center; padding:30px 20px;
    color:#94a3b8; font-size:13px;
}
.ess-empty i { font-size:32px; color:#cbd5e1; margin-bottom:10px; display:block; }

.ess-quick-actions {
    display:grid; grid-template-columns:repeat(2, 1fr); gap:12px;
}
.ess-quick-btn {
    padding:16px; background:#f8fafd; border-radius:14px;
    border:1.5px solid #eef2f6;
    display:flex; flex-direction:column; align-items:flex-start;
    gap:8px; text-decoration:none; color:inherit;
    transition:all 0.25s;
}
.ess-quick-btn:hover {
    background:white; border-color:#0e4c92;
    transform:translateY(-2px);
    box-shadow:0 8px 20px rgba(14,76,146,0.1);
}
.ess-quick-btn i { font-size:20px; color:#0e4c92; }
.ess-quick-btn span { font-size:12.5px; font-weight:600; color:#1e293b; }

@media (max-width:640px) {
    .ess-dash-hero { padding:24px; }
    .ess-dash-hero h1 { font-size:20px; }
    .ess-quick-actions { grid-template-columns:1fr; }
}
</style>

<div class="ess-dash-hero">
    <div class="hero-inner">
        <div>
            <h1>Welcome back, <?= htmlspecialchars($emp['first_name'] ?? 'Employee') ?>! 👋</h1>
            <p>Here's what's happening with your account today.</p>
        </div>
        <div class="hero-badge">
            <i class="fas fa-briefcase"></i>
            <?= htmlspecialchars($emp['position'] ?? 'Employee') ?>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="ess-stats">
    <div class="ess-stat">
        <div class="info">
            <div class="num"><?= $stats['pending_requests'] ?></div>
            <div class="lbl">Pending Requests</div>
        </div>
        <div class="ico amber"><i class="fas fa-hourglass-half"></i></div>
    </div>
    <div class="ess-stat">
        <div class="info">
            <div class="num"><?= $stats['leave_balance'] ?></div>
            <div class="lbl">Leave Balance</div>
        </div>
        <div class="ico green"><i class="fas fa-calendar-minus"></i></div>
    </div>
    <div class="ess-stat">
        <div class="info">
            <div class="num"><?= $stats['unread_notifs'] ?></div>
            <div class="lbl">Unread Notifications</div>
        </div>
        <div class="ico blue"><i class="fas fa-bell"></i></div>
    </div>
    <div class="ess-stat">
        <div class="info">
            <div class="num"><?= $stats['attendance_today'] ? 'In' : 'Out' ?></div>
            <div class="lbl">Today's Attendance</div>
        </div>
        <div class="ico purple"><i class="fas fa-fingerprint"></i></div>
    </div>
</div>

<div class="ess-dash-grid">
    <!-- LEFT COLUMN -->
    <div>
        <!-- Recent Requests -->
        <div class="ess-panel">
            <div class="ess-panel-head">
                <div class="left">
                    <div class="ico"><i class="fas fa-paper-plane"></i></div>
                    <h3>My Recent Requests</h3>
                </div>
                <a href="?page=requests&subpage=requests" class="view-all">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="ess-panel-body">
                <?php if (empty($recentRequests)): ?>
                <div class="ess-empty">
                    <i class="fas fa-inbox"></i>
                    No requests yet. <a href="?page=requests&subpage=requests" style="color:#0e4c92;">Submit one now</a>
                </div>
                <?php else: ?>
                <?php foreach ($recentRequests as $r): ?>
                <a href="?page=requests&subpage=requests" class="ess-request-row">
                    <div class="r-icon">
                        <i class="fas fa-<?= $r['request_type'] === 'leave' ? 'calendar-minus' : ($r['request_type'] === 'overtime' ? 'hourglass-half' : 'file') ?>"></i>
                    </div>
                    <div class="r-info">
                        <div class="r-title"><?= htmlspecialchars($r['title']) ?></div>
                        <div class="r-meta"><?= date('M j, Y · g:i A', strtotime($r['created_at'])) ?></div>
                    </div>
                    <span class="r-status <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Announcements -->
        <div class="ess-panel">
            <div class="ess-panel-head">
                <div class="left">
                    <div class="ico"><i class="fas fa-bullhorn"></i></div>
                    <h3>Latest Announcements</h3>
                </div>
                <a href="?page=comm&subpage=announcements" class="view-all">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="ess-panel-body">
                <?php if (empty($announcements)): ?>
                <div class="ess-empty">
                    <i class="fas fa-bullhorn"></i>
                    No announcements at the moment.
                </div>
                <?php else: ?>
                <?php foreach ($announcements as $a): ?>
                <a href="?page=comm&subpage=announcements" class="ess-announce type-<?= htmlspecialchars($a['type']) ?>">
                    <div class="a-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="a-excerpt"><?= htmlspecialchars(mb_strimwidth($a['content'], 0, 120, '...')) ?></div>
                    <div class="a-date"><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($a['created_at'])) ?></div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div>
        <!-- Quick Actions -->
        <div class="ess-panel">
            <div class="ess-panel-head">
                <div class="left">
                    <div class="ico"><i class="fas fa-bolt"></i></div>
                    <h3>Quick Actions</h3>
                </div>
            </div>
            <div class="ess-panel-body">
                <div class="ess-quick-actions">
                    <a href="?page=time&subpage=leave" class="ess-quick-btn">
                        <i class="fas fa-calendar-plus"></i>
                        <span>File Leave</span>
                    </a>
                    <a href="?page=time&subpage=overtime" class="ess-quick-btn">
                        <i class="fas fa-hourglass-half"></i>
                        <span>Request OT</span>
                    </a>
                    <a href="?page=comp&subpage=payslip" class="ess-quick-btn">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>View Payslip</span>
                    </a>
                    <a href="?page=requests&subpage=document-requests" class="ess-quick-btn">
                        <i class="fas fa-file-alt"></i>
                        <span>Request Doc</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Notifications -->
        <div class="ess-panel">
            <div class="ess-panel-head">
                <div class="left">
                    <div class="ico"><i class="fas fa-bell"></i></div>
                    <h3>Recent Activity</h3>
                </div>
                <a href="?page=comm&subpage=notifications" class="view-all">
                    All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="ess-panel-body">
                <?php if (empty($recentActivity)): ?>
                <div class="ess-empty">
                    <i class="fas fa-bell-slash"></i>
                    No recent activity.
                </div>
                <?php else: ?>
                <?php foreach ($recentActivity as $n): ?>
                <div class="ess-activity-row">
                    <div class="dot" style="background:<?= $n['type'] === 'success' ? '#16a34a' : ($n['type'] === 'danger' ? '#dc2626' : ($n['type'] === 'warning' ? '#f59e0b' : '#0e4c92')) ?>;"></div>
                    <div class="a-content">
                        <div class="a-title"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="a-msg"><?= htmlspecialchars($n['message']) ?></div>
                        <div class="a-time"><?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>