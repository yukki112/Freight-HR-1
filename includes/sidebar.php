<?php
// includes/sidebar.php
ob_start();

require_once 'includes/notification_functions.php';

$current_page    = isset($_GET['page'])    ? $_GET['page']    : 'dashboard';
$current_subpage = isset($_GET['subpage']) ? $_GET['subpage'] : '';
$collapsed       = isset($_COOKIE['sidebar']) && $_COOKIE['sidebar'] == 'collapsed';

$user       = getUserInfo($pdo, $_SESSION['user_id']);
$role       = $user['role'];
$full_name  = $user['full_name'] ?? 'User';
$first_name = explode(' ', $full_name)[0];

$profile_picture = $user['profile_picture'] ?? null;
$profile_picture_path = '';
if ($profile_picture && file_exists('uploads/profile_pictures/' . $profile_picture)) {
    $profile_picture_path = 'uploads/profile_pictures/' . $profile_picture;
}

$role_display = ucfirst($role);
$hr_stats = getHRStats($pdo, $_SESSION['user_id']);

function isModuleActive($module, $current_page, $current_subpage = '') {
    if ($current_page == $module) return true;
    if ($current_subpage && strpos($current_subpage, $module) === 0) return true;
    return false;
}
function isSubpageActive($subpage, $current_subpage) {
    return $current_subpage == $subpage;
}
?>
<aside class="unique-sidebar <?php echo $collapsed ? 'collapsed' : ''; ?>">
    <div class="sidebar-glass"></div>
    <div class="sidebar-content">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-wrapper">
                    <img src="assets/images/logo1.png" alt="HR 1 Freight Logo" class="logo-image" 
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=HR1&background=0e4c92&color=fff&size=100&bold=true&format=png';">
                </div>
                <?php if (!$collapsed): ?>
                <div class="logo-text-wrapper">
                    <span class="logo-bcp">SLATE</span>
                    <span class="logo-budget">FREIGHT HR 1</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="sidebar-nav-container">
            <!-- MAIN -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-compass"></i>
                    <span>MAIN</span>
                </div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" style="--item-color: #0e4c92;">
                        <a href="?page=dashboard">
                            <div class="icon-wrapper"><i class="fas fa-chart-pie"></i></div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">HR Dashboard</span>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- TALENT ACQUISITION -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-user-plus"></i>
                    <span>TALENT ACQUISITION</span>
                </div>
                <?php endif; ?>

                <!-- APPLICANT -->
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('applicant', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #0e4c92;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('applicant-submenu')">
                            <div class="icon-wrapper"><i class="fas fa-users"></i></div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Applicant Management</span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu <?php echo isModuleActive('applicant', $current_page, $current_subpage) ? 'active' : ''; ?>" id="applicant-submenu">
                        <li class="submenu-item <?php echo isSubpageActive('applicant-profiles', $current_subpage) ? 'active' : ''; ?>"><a href="?page=applicant&subpage=applicant-profiles"><i class="fas fa-id-card"></i><span>Applicant Profiles</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('document-verification', $current_subpage) ? 'active' : ''; ?>"><a href="?page=applicant&subpage=document-verification"><i class="fas fa-file-signature"></i><span>Document Verification</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('screening-evaluation', $current_subpage) ? 'active' : ''; ?>"><a href="?page=applicant&subpage=screening-evaluation"><i class="fas fa-clipboard-check"></i><span>Screening &amp; Evaluation</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('application-status', $current_subpage) ? 'active' : ''; ?>"><a href="?page=applicant&subpage=application-status"><i class="fas fa-tasks"></i><span>Status Tracking</span></a></li>
                    </ul>
                </ul>

                <!-- RECRUITMENT -->
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('recruitment', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #1a5da0;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('recruitment-submenu')">
                            <div class="icon-wrapper"><i class="fas fa-bullhorn"></i></div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Recruitment Management</span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu <?php echo isModuleActive('recruitment', $current_page, $current_subpage) ? 'active' : ''; ?>" id="recruitment-submenu">
                        <li class="submenu-item <?php echo isSubpageActive('job-posting', $current_subpage) ? 'active' : ''; ?>"><a href="?page=recruitment&subpage=job-posting"><i class="fas fa-briefcase"></i><span>Job Posting Management</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('assessment-management', $current_subpage) ? 'active' : ''; ?>"><a href="?page=recruitment&subpage=assessment-management"><i class="fas fa-file-signature"></i><span>Assessment Management</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('interview-scheduling', $current_subpage) ? 'active' : ''; ?>"><a href="?page=recruitment&subpage=interview-scheduling"><i class="fas fa-calendar-check"></i><span>Interview Scheduling</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('interview-panel', $current_subpage) ? 'active' : ''; ?>"><a href="?page=recruitment&subpage=interview-panel"><i class="fas fa-users-cog"></i><span>Panel Evaluation</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('final-selection', $current_subpage) ? 'active' : ''; ?>"><a href="?page=recruitment&subpage=final-selection"><i class="fas fa-trophy"></i><span>Final Selection</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('interview-feedback', $current_subpage) ? 'active' : ''; ?>"><a href="?page=recruitment&subpage=interview-feedback"><i class="fas fa-star"></i><span>Feedback &amp; Ranking</span></a></li>
                    </ul>
                </ul>

                <!-- ONBOARDING -->
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('onboarding', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #2a6eb0;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('onboarding-submenu')">
                            <div class="icon-wrapper"><i class="fas fa-user-graduate"></i></div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">New Hire Onboarding</span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu <?php echo isModuleActive('onboarding', $current_page, $current_subpage) ? 'active' : ''; ?>" id="onboarding-submenu">
                        <li class="submenu-item <?php echo isSubpageActive('onboarding-dashboard', $current_subpage) ? 'active' : ''; ?>"><a href="?page=onboarding&subpage=onboarding-dashboard"><i class="fas fa-tachometer-alt"></i><span>New Hire Dashboard</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('document-submission', $current_subpage) ? 'active' : ''; ?>"><a href="?page=onboarding&subpage=document-submission"><i class="fas fa-file-upload"></i><span>Document Submission</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('orientation-schedule', $current_subpage) ? 'active' : ''; ?>"><a href="?page=onboarding&subpage=orientation-schedule"><i class="fas fa-calendar-alt"></i><span>Orientation Schedule</span></a></li>
                    </ul>
                </ul>
            </div>

            <!-- CORE HUMAN RESOURCES -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-users-cog"></i>
                    <span>CORE HUMAN RESOURCES</span>
                </div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('core-hr', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #0e4c92;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('core-hr-submenu')">
                            <div class="icon-wrapper"><i class="fas fa-user-tie"></i></div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Core Human Resources</span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu <?php echo isModuleActive('core-hr', $current_page, $current_subpage) ? 'active' : ''; ?>" id="core-hr-submenu">
                        <li class="submenu-item <?php echo isSubpageActive('employee-master-data', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=core-hr&subpage=employee-master-data">
                                <i class="fas fa-id-badge"></i>
                                <span>Employee Master Data</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('employment-info', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=core-hr&subpage=employment-info">
                                <i class="fas fa-briefcase"></i>
                                <span>Employment Information</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('employment-history', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=core-hr&subpage=employment-history">
                                <i class="fas fa-history"></i>
                                <span>Employment History</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('hr-configuration', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=core-hr&subpage=hr-configuration">
                                <i class="fas fa-cogs"></i>
                                <span>HR Configuration</span>
                            </a>
                        </li>
                    </ul>
                </ul>
            </div>

            <!-- EMPLOYEE SELF-SERVICE -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-user-check"></i>
                    <span>EMPLOYEE SELF-SERVICE</span>
                </div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('ess', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #1a5da0;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('ess-submenu')">
                            <div class="icon-wrapper"><i class="fas fa-user-circle"></i></div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Employee Self-Service</span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu <?php echo isModuleActive('ess', $current_page, $current_subpage) ? 'active' : ''; ?>" id="ess-submenu">
                        <li class="submenu-item <?php echo isSubpageActive('ess-dashboard', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess&subpage=ess-dashboard"><i class="fas fa-tachometer-alt"></i><span>My Dashboard</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('my-profile', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess&subpage=my-profile"><i class="fas fa-id-card"></i><span>My Profile</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('my-attendance', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess&subpage=my-attendance"><i class="fas fa-clock"></i><span>My Attendance</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('my-leave', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess&subpage=my-leave"><i class="fas fa-calendar-minus"></i><span>My Leave</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('my-payslips', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess&subpage=my-payslips"><i class="fas fa-file-invoice-dollar"></i><span>My Payslips</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('my-benefits', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess&subpage=my-benefits"><i class="fas fa-heart"></i><span>My Benefits</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('my-performance', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess&subpage=my-performance"><i class="fas fa-chart-line"></i><span>My Performance</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('my-requests', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess&subpage=my-requests"><i class="fas fa-paper-plane"></i><span>My Requests</span></a></li>
                    </ul>
                </ul>
            </div>

            <!-- ADMIN -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-cog"></i>
                    <span>ADMIN</span>
                </div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item <?php echo $current_page == 'profile' ? 'active' : ''; ?>" style="--item-color: #1a5da0;">
                        <a href="?page=profile">
                            <div class="icon-wrapper"><i class="fas fa-user-circle"></i></div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">My Profile</span>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="sidebar-footer">
            <div class="savings-widget">
                <div class="savings-icon"><i class="fas fa-users"></i></div>
                <?php if (!$collapsed): ?>
                <div class="savings-info">
                    <span class="savings-label">Active Employees</span>
                    <span class="savings-value"><?php echo $hr_stats['active_employees'] ?? 0; ?></span>
                    <div class="savings-bar">
                        <div class="savings-progress" style="width: <?php echo min(100, ($hr_stats['active_employees'] ?? 0) * 5); ?>%"></div>
                    </div>
                    <span class="savings-detail">
                        <i class="fas fa-user-plus"></i> <?php echo $hr_stats['onboarding_count'] ?? 0; ?> onboarding
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <div class="couple-profile-widget">
                <div class="couple-avatar-single">
                    <?php if ($profile_picture_path): ?>
                        <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="" class="profile-avatar">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($first_name); ?>&background=0e4c92&color=fff&size=100&bold=true&format=png&length=1" alt="">
                    <?php endif; ?>
                    <div class="status-badge"></div>
                </div>
                <?php if (!$collapsed): ?>
                <div class="couple-info">
                    <div class="couple-names">
                        <span class="your-name"><i class="fas fa-user"></i> <?php echo htmlspecialchars($first_name); ?></span>
                        <span class="partner-name"><i class="fas fa-tag" style="color: #e74c3c;"></i> HR 1 | <?php echo $role_display; ?></span>
                    </div>
                    <span class="couple-role"><i class="fas fa-building" style="color: #f1c40f;"></i> Talent Acquisition</span>
                </div>
                <?php endif; ?>
                <button class="logout-btn" onclick="logout()" title="Logout"><i class="fas fa-power-off"></i></button>
            </div>
        </div>
    </div>
</aside>

<style>
.logo-wrapper{width:50px;height:50px;border-radius:15px;overflow:hidden;box-shadow:0 10px 20px rgba(14,76,146,0.2);background:white;display:flex;align-items:center;justify-content:center;}
.logo-image{width:100%;height:100%;object-fit:contain;border-radius:15px;}
.has-submenu>a{position:relative;}
.submenu-arrow{margin-left:auto;font-size:12px;transition:transform 0.3s;}
.has-submenu.active .submenu-arrow{transform:rotate(180deg);}
.submenu{list-style:none;padding-left:55px;margin:5px 0 10px 0;display:none;}
.submenu.active{display:block !important;}
.unique-sidebar.collapsed .submenu{display:none !important;}
.submenu-item{margin:3px 0;}
.submenu-item a{display:flex;align-items:center;gap:10px;padding:8px 12px;color:#4a5568;text-decoration:none;border-radius:12px;font-size:13px;transition:all 0.3s;position:relative;}
.submenu-item a:hover{background:rgba(14,76,146,0.1);color:#0e4c92;}
.submenu-item.active a{background:rgba(14,76,146,0.15);color:#0e4c92;font-weight:500;}
.submenu-item i{width:18px;font-size:12px;color:#0e4c92;}
.couple-avatar-single{position:relative;width:50px;height:50px;border-radius:12px;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,#0e4c92,#1a5da0);box-shadow:0 4px 10px rgba(14,76,146,0.2);}
.couple-avatar-single img{width:100%;height:100%;object-fit:cover;border-radius:12px;}
.profile-avatar{width:100%;height:100%;object-fit:cover;border-radius:12px;}
.unique-sidebar.collapsed .couple-avatar-single{width:40px;height:40px;margin:0 auto;}
.unique-sidebar.collapsed .logo-wrapper{width:40px;height:40px;margin:0 auto;}
</style>

<script>
function toggleSubmenu(id) {
    const s = document.getElementById(id);
    if (!s) return;
    if (s.classList.contains('active')) {
        s.classList.remove('active');
        s.style.display = 'none';
    } else {
        document.querySelectorAll('.submenu').forEach(other => {
            other.classList.remove('active');
            other.style.display = 'none';
        });
        s.classList.add('active');
        s.style.display = 'block';
    }
}

function toggleSidebar() {
    const sb = document.querySelector('.unique-sidebar');
    const c = sb.classList.contains('collapsed');
    if (c) {
        sb.classList.remove('collapsed');
        document.cookie = "sidebar=expanded; path=/; max-age=" + 60*60*24*30;
    } else {
        sb.classList.add('collapsed');
        document.cookie = "sidebar=collapsed; path=/; max-age=" + 60*60*24*30;
        document.querySelectorAll('.submenu').forEach(s => s.style.display = 'none');
    }
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}

// ============================================================
// 1. Ensure active submenu is visible on load
// 2. Auto-scroll sidebar so the active item stays in view
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    // Make sure the active submenu opens
    document.querySelectorAll('.submenu.active').forEach(s => {
        s.style.display = 'block';
    });

    // Small delay to let layout settle, then scroll active item into view
    setTimeout(function () {
        // Priority: active submenu item → active top-level nav item
        const activeSub  = document.querySelector('.submenu-item.active a');
        const activeTop  = document.querySelector('.nav-item.active > a');
        const target     = activeSub || activeTop;

        // Find the scrollable sidebar container
        const container = document.querySelector('.sidebar-nav-container')
                       || document.querySelector('.sidebar-content')
                       || document.querySelector('.unique-sidebar');

        if (!target || !container) return;

        // Compute scroll position so target is vertically centered
        const targetRect    = target.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();
        const offset        = targetRect.top - containerRect.top;
        const desiredScroll = container.scrollTop + offset - (containerRect.height / 2) + (targetRect.height / 2);

        container.scrollTo({
            top: Math.max(0, desiredScroll),
            behavior: 'auto'      // use 'smooth' for an animated scroll
        });
    }, 50);
});
</script>

<?php
?>