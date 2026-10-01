<?php
// /includes/ess/ess_header.php
$essEmp    = essGetEmployee($pdo);
$essFirst  = $essEmp['first_name'] ?? 'Employee';
$essUnread = essGetUnreadCount($pdo, $essEmp['id'] ?? 0);
?>
<header class="unique-header ess-header">
    <div class="header-left">
        <button class="sidebar-toggle" onclick="toggleESSidebar()" title="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="page-title">
            <h1><?= htmlspecialchars(ucwords(str_replace('-', ' ', $_GET['subpage'] ?? $_GET['page'] ?? 'My Dashboard'))) ?></h1>
            <p>Welcome back, <?= htmlspecialchars($essFirst) ?>! 👋</p>
        </div>
    </div>

    <div class="header-right">
        <a href="?page=comm&subpage=notifications" class="header-icon-btn" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($essUnread > 0): ?>
            <span class="badge-dot"><?= $essUnread > 9 ? '9+' : $essUnread ?></span>
            <?php endif; ?>
        </a>
        <a href="?page=comm&subpage=announcements" class="header-icon-btn" title="Announcements">
            <i class="fas fa-bullhorn"></i>
        </a>
        <a href="?page=requests&subpage=requests" class="header-icon-btn" title="My Requests">
            <i class="fas fa-inbox"></i>
        </a>

        <div class="header-user" onclick="toggleUserDropdown()">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($essFirst) ?></span>
                <span class="user-role"><?= htmlspecialchars($essEmp['position'] ?? 'Employee') ?></span>
            </div>
            <i class="fas fa-chevron-down"></i>

            <div class="user-dropdown" id="userDropdown">
                <a href="?page=my-info&subpage=my-profile"><i class="fas fa-id-card"></i> My Profile</a>
                <a href="?page=my-info&subpage=my-employment"><i class="fas fa-briefcase"></i> Employment Info</a>
                <a href="?page=comp&subpage=payslip"><i class="fas fa-file-invoice-dollar"></i> Payslips</a>
                <div class="dropdown-divider"></div>
                <a href="ess_change_password.php"><i class="fas fa-key"></i> Change Password</a>
                <a href="ess_logout.php" class="danger" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>

<style>
.ess-header { display:flex; justify-content:space-between; align-items:center; padding:18px 28px; background:white; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,0.04); margin-bottom:22px; }
.header-left { display:flex; align-items:center; gap:16px; }
.sidebar-toggle { background:transparent; border:none; color:#0e4c92; font-size:18px; cursor:pointer; padding:8px; border-radius:10px; transition:all 0.3s; }
.sidebar-toggle:hover { background:rgba(14,76,146,0.1); }
.page-title h1 { font-size:20px; font-weight:700; color:#1e293b; margin:0 0 2px; }
.page-title p { font-size:13px; color:#64748b; margin:0; }
.header-right { display:flex; align-items:center; gap:12px; }
.header-icon-btn { position:relative; width:42px; height:42px; display:flex; align-items:center; justify-content:center; background:#f8fafc; color:#0e4c92; border-radius:12px; text-decoration:none; transition:all 0.3s; }
.header-icon-btn:hover { background:rgba(14,76,146,0.1); transform:translateY(-2px); }
.badge-dot { position:absolute; top:-4px; right:-4px; background:#ef4444; color:white; font-size:10px; font-weight:700; padding:2px 6px; border-radius:10px; min-width:18px; text-align:center; }
.header-user { position:relative; display:flex; align-items:center; gap:10px; padding:6px 12px; background:#f8fafc; border-radius:14px; cursor:pointer; transition:all 0.3s; }
.header-user:hover { background:rgba(14,76,146,0.08); }
.user-avatar { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#0e4c92,#4086e4); display:flex; align-items:center; justify-content:center; color:white; font-size:20px; }
.user-info { display:flex; flex-direction:column; }
.user-name { font-size:13px; font-weight:600; color:#1e293b; }
.user-role { font-size:11px; color:#64748b; }
.header-user > i.fa-chevron-down { color:#94a3b8; font-size:12px; margin-left:5px; }
.user-dropdown { position:absolute; top:calc(100% + 8px); right:0; background:white; border-radius:14px; box-shadow:0 10px 40px rgba(0,0,0,0.15); padding:8px; min-width:230px; display:none; z-index:100; }
.user-dropdown.show { display:block; }
.user-dropdown a { display:flex; align-items:center; gap:10px; padding:10px 14px; color:#475569; text-decoration:none; border-radius:10px; font-size:13px; transition:all 0.2s; }
.user-dropdown a:hover { background:rgba(14,76,146,0.08); color:#0e4c92; }
.user-dropdown a.danger:hover { background:#fef2f2; color:#dc2626; }
.user-dropdown a i { width:16px; }
.dropdown-divider { height:1px; background:#e5e7eb; margin:6px 0; }
@media (max-width: 768px) {
    .ess-header { padding:14px 18px; border-radius:16px; }
    .page-title h1 { font-size:16px; }
    .user-info { display:none; }
}
</style>

<script>
function toggleESSidebar() {
    const sb = document.querySelector('.ess-sidebar');
    if (!sb) return;
    const collapsed = sb.classList.contains('collapsed');
    if (collapsed) {
        sb.classList.remove('collapsed');
        document.cookie = "ess_sidebar=expanded; path=/; max-age=" + 60*60*24*30;
        document.querySelector('.unique-main').style.marginLeft = '320px';
    } else {
        sb.classList.add('collapsed');
        document.cookie = "ess_sidebar=collapsed; path=/; max-age=" + 60*60*24*30;
        document.querySelector('.unique-main').style.marginLeft = '100px';
    }
}
function toggleUserDropdown() {
    document.getElementById('userDropdown').classList.toggle('show');
}
document.addEventListener('click', function(e) {
    const dd = document.getElementById('userDropdown');
    const user = e.target.closest('.header-user');
    if (dd && !user) dd.classList.remove('show');
});
</script>