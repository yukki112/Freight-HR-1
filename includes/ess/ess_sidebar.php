<?php
// /includes/ess/ess_sidebar.php
$current_page    = $_GET['page']    ?? 'dashboard';
$current_subpage = $_GET['subpage'] ?? '';
$collapsed       = isset($_COOKIE['ess_sidebar']) && $_COOKIE['ess_sidebar'] == 'collapsed';

$essAccount  = essGetAccount($pdo);
$essEmployee = essGetEmployee($pdo);

$essFirst    = $essEmployee['first_name'] ?? 'Employee';
$essPosition = $essEmployee['position'] ?? 'Employee';
$essDept     = $essEmployee['department'] ?? '';

// Avatar (new_hires has no profile_picture column → always fallback to ui-avatars)
$avatar_path = 'https://ui-avatars.com/api/?name=' . urlencode($essFirst)
    . '&background=0e4c92&color=fff&size=100&bold=true&format=png&length=1';

$essUnread = essGetUnreadCount($pdo, $essEmployee['id'] ?? 0);

// Helper: is this subpage active?
if (!function_exists('essSubActive')) {
    function essSubActive($subpage, $current_subpage) {
        return $current_subpage === $subpage;
    }
}
?>
<aside class="unique-sidebar ess-sidebar <?= $collapsed ? 'collapsed' : '' ?>">
    <div class="sidebar-glass"></div>
    <div class="sidebar-content">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-wrapper">
                    <img src="assets/images/LOGO.jpg" alt="HR 1 Logo" class="logo-image"
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=HR1&background=0e4c92&color=fff&size=100&bold=true&format=png';">
                </div>
                <?php if (!$collapsed): ?>
                <div class="logo-text-wrapper">
                    <span class="logo-bcp">ESS</span>
                    <span class="logo-budget">EMPLOYEE PORTAL</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="sidebar-nav-container">

            <!-- MAIN -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header"><i class="fas fa-compass"></i><span>MAIN</span></div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item <?= $current_page === 'dashboard' ? 'active' : '' ?>" style="--item-color:#0e4c92;">
                        <a href="?page=dashboard">
                            <div class="icon-wrapper"><i class="fas fa-home"></i></div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">My Dashboard</span>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- MY INFO -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header"><i class="fas fa-user"></i><span>MY INFORMATION</span></div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item <?= essSubActive('my-profile', $current_subpage) ? 'active' : '' ?>" style="--item-color:#0e4c92;">
                        <a href="?page=my-info&subpage=my-profile">
                            <div class="icon-wrapper"><i class="fas fa-id-card"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">My Profile</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('my-employment', $current_subpage) ? 'active' : '' ?>" style="--item-color:#0e4c92;">
                        <a href="?page=my-info&subpage=my-employment">
                            <div class="icon-wrapper"><i class="fas fa-briefcase"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">Employment Info</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('my-documents', $current_subpage) ? 'active' : '' ?>" style="--item-color:#0e4c92;">
                        <a href="?page=my-info&subpage=my-documents">
                            <div class="icon-wrapper"><i class="fas fa-folder-open"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">My Documents</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- TIME & ATTENDANCE -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header"><i class="fas fa-clock"></i><span>TIME &amp; ATTENDANCE</span></div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item <?= essSubActive('attendance', $current_subpage) ? 'active' : '' ?>" style="--item-color:#1a5da0;">
                        <a href="?page=time&subpage=attendance">
                            <div class="icon-wrapper"><i class="fas fa-fingerprint"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">My Attendance</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('timesheet', $current_subpage) ? 'active' : '' ?>" style="--item-color:#1a5da0;">
                        <a href="?page=time&subpage=timesheet">
                            <div class="icon-wrapper"><i class="fas fa-clock"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">Timesheet</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('leave', $current_subpage) ? 'active' : '' ?>" style="--item-color:#1a5da0;">
                        <a href="?page=time&subpage=leave">
                            <div class="icon-wrapper"><i class="fas fa-calendar-minus"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">Leave Management</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('overtime', $current_subpage) ? 'active' : '' ?>" style="--item-color:#1a5da0;">
                        <a href="?page=time&subpage=overtime">
                            <div class="icon-wrapper"><i class="fas fa-hourglass-half"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">Overtime Request</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('schedule', $current_subpage) ? 'active' : '' ?>" style="--item-color:#1a5da0;">
                        <a href="?page=time&subpage=schedule">
                            <div class="icon-wrapper"><i class="fas fa-calendar-alt"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">My Schedule</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- COMPENSATION -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header"><i class="fas fa-money-bill-wave"></i><span>COMPENSATION</span></div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item <?= essSubActive('payslip', $current_subpage) ? 'active' : '' ?>" style="--item-color:#2a6eb0;">
                        <a href="?page=comp&subpage=payslip">
                            <div class="icon-wrapper"><i class="fas fa-file-invoice-dollar"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">My Payslips</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('benefits', $current_subpage) ? 'active' : '' ?>" style="--item-color:#2a6eb0;">
                        <a href="?page=comp&subpage=benefits">
                            <div class="icon-wrapper"><i class="fas fa-heart"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">My Benefits</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('claims', $current_subpage) ? 'active' : '' ?>" style="--item-color:#2a6eb0;">
                        <a href="?page=comp&subpage=claims">
                            <div class="icon-wrapper"><i class="fas fa-receipt"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">Claims &amp; Reimbursement</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- REQUESTS -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header"><i class="fas fa-paper-plane"></i><span>REQUESTS</span></div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item <?= essSubActive('requests', $current_subpage) ? 'active' : '' ?>" style="--item-color:#0e4c92;">
                        <a href="?page=requests&subpage=requests">
                            <div class="icon-wrapper"><i class="fas fa-inbox"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">Employee Requests</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('document-requests', $current_subpage) ? 'active' : '' ?>" style="--item-color:#0e4c92;">
                        <a href="?page=requests&subpage=document-requests">
                            <div class="icon-wrapper"><i class="fas fa-file-alt"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">Document Requests</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- GROWTH -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header"><i class="fas fa-chart-line"></i><span>GROWTH</span></div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item <?= essSubActive('performance', $current_subpage) ? 'active' : '' ?>" style="--item-color:#1a5da0;">
                        <a href="?page=growth&subpage=performance">
                            <div class="icon-wrapper"><i class="fas fa-trophy"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">My Performance</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('training', $current_subpage) ? 'active' : '' ?>" style="--item-color:#1a5da0;">
                        <a href="?page=growth&subpage=training">
                            <div class="icon-wrapper"><i class="fas fa-graduation-cap"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">Training &amp; Learning</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- COMMUNICATION -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header"><i class="fas fa-bullhorn"></i><span>COMMUNICATION</span></div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item <?= essSubActive('announcements', $current_subpage) ? 'active' : '' ?>" style="--item-color:#2a6eb0;">
                        <a href="?page=comm&subpage=announcements">
                            <div class="icon-wrapper"><i class="fas fa-bullhorn"></i></div>
                            <?php if (!$collapsed): ?><span class="nav-label">Announcements</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?= essSubActive('notifications', $current_subpage) ? 'active' : '' ?>" style="--item-color:#2a6eb0;">
                        <a href="?page=comm&subpage=notifications">
                            <div class="icon-wrapper" style="position:relative;">
                                <i class="fas fa-bell"></i>
                                <?php if ($essUnread > 0): ?>
                                <span class="ess-badge"><?= $essUnread > 9 ? '9+' : $essUnread ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!$collapsed): ?><span class="nav-label">Notifications</span><div class="nav-indicator"></div><?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

        </div>

        <div class="sidebar-footer">
            <div class="couple-profile-widget">
                <div class="couple-avatar-single">
                    <img src="<?= htmlspecialchars($avatar_path) ?>" alt="" class="profile-avatar">
                    <div class="status-badge"></div>
                </div>
                <?php if (!$collapsed): ?>
                <div class="couple-info">
                    <div class="couple-names">
                        <span class="your-name"><i class="fas fa-user"></i> <?= htmlspecialchars($essFirst) ?></span>
                        <span class="partner-name"><i class="fas fa-tag" style="color:#e74c3c;"></i> <?= htmlspecialchars($essPosition) ?></span>
                    </div>
                    <span class="couple-role"><i class="fas fa-building" style="color:#f1c40f;"></i> <?= htmlspecialchars(ucfirst($essDept)) ?></span>
                </div>
                <?php endif; ?>
                <a href="ess_logout.php" class="logout-btn" onclick="return confirm('Logout from ESS?')" title="Logout">
                    <i class="fas fa-power-off"></i>
                </a>
            </div>
        </div>
    </div>
</aside>

<style>
/* ==========================================================
   ESS SIDEBAR — Component overrides (theme uses .unique-sidebar)
   ========================================================== */

/* Unread notification badge */
.ess-sidebar .ess-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(239,68,68,0.4);
    line-height: 1;
}

/* When sidebar collapsed, hide sidebar footer detail (already handled by $collapsed), reposition avatar */
.ess-sidebar.collapsed .couple-avatar-single {
    width: 44px;
    height: 44px;
    margin: 0 auto;
}

/* Logout button */
.ess-sidebar .logout-btn {
    background: transparent;
    border: none;
    color: #94a3b8;
    padding: 8px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.ess-sidebar .logout-btn:hover {
    background: #fef2f2;
    color: #dc2626;
}

/* Notification count when sidebar collapsed → don't show full number, just dot */
@media (max-width: 1100px) {
    .ess-sidebar .ess-badge { font-size: 9px; min-width: 16px; height: 16px; padding: 0 4px; }
}
</style>