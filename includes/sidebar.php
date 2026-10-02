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
                    <img src="assets/images/LOGO.jpg" alt="HR 1 Freight Logo" class="logo-image" 
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=HR1&background=0e4c92&color=fff&size=100&bold=true&format=png';">
                </div>
                <?php if (!$collapsed): ?>
                <div class="logo-text-wrapper">
                    <span class="logo-bcp">PRIORITY</span>
                    <span class="logo-budget">HANDLING 
LOGSTICS,INC.</span>
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

                <!-- CORE HUMAN RESOURCES (no section header, keeps dropdown) -->
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

                

                <!-- ESS ADMIN (no section header, keeps dropdown) -->
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('ess-admin', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #0e4c92;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('ess-admin-submenu')">
                            <div class="icon-wrapper"><i class="fas fa-users-cog"></i></div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">ESS Management</span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu <?php echo isModuleActive('ess-admin', $current_page, $current_subpage) ? 'active' : ''; ?>" id="ess-admin-submenu">
                        <li class="submenu-item <?php echo isSubpageActive('ess-accounts', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess-admin&subpage=ess-accounts"><i class="fas fa-user-plus"></i><span>ESS Accounts</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('ess-announcements', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess-admin&subpage=ess-announcements"><i class="fas fa-bullhorn"></i><span>Announcements</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('ess-requests', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess-admin&subpage=ess-requests"><i class="fas fa-inbox"></i><span>Employee Requests</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('ess-documents', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess-admin&subpage=ess-documents"><i class="fas fa-file-alt"></i><span>Document Requests</span></a></li>
                        <li class="submenu-item <?php echo isSubpageActive('ess-settings', $current_subpage) ? 'active' : ''; ?>"><a href="?page=ess-admin&subpage=ess-settings"><i class="fas fa-sliders-h"></i><span>ESS Settings</span></a></li>
                    </ul>
                </ul>
            </div>

            <!-- EMPLOYEE RECORDS (no section header, keeps dropdown) -->
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('erm', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #0e4c92;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('erm-submenu')">
                            <div class="icon-wrapper"><i class="fas fa-archive"></i></div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Records Management</span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu <?php echo isModuleActive('erm', $current_page, $current_subpage) ? 'active' : ''; ?>" id="erm-submenu">
                        <li class="submenu-item <?php echo isSubpageActive('erm-dashboard', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=erm&subpage=erm-dashboard"><i class="fas fa-chart-pie"></i><span>Overview</span></a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('erm-personnel', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=erm&subpage=erm-personnel"><i class="fas fa-folder-open"></i><span>Personnel Files</span></a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('erm-upload', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=erm&subpage=erm-upload"><i class="fas fa-cloud-upload-alt"></i><span>Upload Records</span></a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('erm-verification', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=erm&subpage=erm-verification"><i class="fas fa-clipboard-check"></i><span>Verification Queue</span></a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('erm-expiration', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=erm&subpage=erm-expiration"><i class="fas fa-hourglass-half"></i><span>Expiration Tracking</span></a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('erm-archives', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=erm&subpage=erm-archives"><i class="fas fa-box-archive"></i><span>Archives</span></a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('erm-audit', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=erm&subpage=erm-audit"><i class="fas fa-history"></i><span>Audit Trail</span></a>
                        </li>
                    </ul>
                </ul>

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

<!-- ============================================= -->
<!-- SESSION TIMEOUT WARNING MODAL -->
<!-- ============================================= -->
<div id="sessionTimeoutModal" class="session-modal-overlay" style="display: none;">
    <div class="session-modal">
        <div class="session-modal-icon">
            <i class="fas fa-clock"></i>
        </div>
        <h3 class="session-modal-title">Session Expiring Soon</h3>
        <p class="session-modal-message">
            You've been inactive for a while. Your session will expire in
            <span id="sessionCountdown" class="session-countdown">60</span> seconds.
        </p>
        <p class="session-modal-submessage">Would you like to continue your session?</p>
        <div class="session-modal-buttons">
            <button id="sessionContinueBtn" class="session-btn session-btn-continue">
                <i class="fas fa-check"></i> Continue Session
            </button>
            <button id="sessionLogoutBtn" class="session-btn session-btn-logout">
                <i class="fas fa-power-off"></i> Logout Now
            </button>
        </div>
    </div>
</div>

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

/* ============================================= */
/* SESSION TIMEOUT MODAL STYLES */
/* ============================================= */
.session-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: sessionFadeIn 0.3s ease;
}
@keyframes sessionFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.session-modal {
    background: #ffffff;
    border-radius: 20px;
    padding: 35px 30px 30px;
    width: 90%;
    max-width: 420px;
    text-align: center;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
    animation: sessionSlideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
}
.session-modal::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, #f39c12, #e74c3c);
}
@keyframes sessionSlideUp {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.session-modal-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f39c12, #e67e22);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 18px;
    box-shadow: 0 8px 20px rgba(243, 156, 18, 0.35);
}
.session-modal-icon i {
    font-size: 30px;
    color: #fff;
    animation: sessionPulse 1.5s ease-in-out infinite;
}
@keyframes sessionPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
}
.session-modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 10px;
}
.session-modal-message {
    font-size: 14px;
    color: #4a5568;
    margin: 0 0 6px;
    line-height: 1.6;
}
.session-countdown {
    font-weight: 800;
    color: #e74c3c;
    font-size: 18px;
    display: inline-block;
    min-width: 30px;
    transition: transform 0.2s;
}
.session-countdown.tick {
    transform: scale(1.3);
}
.session-modal-submessage {
    font-size: 13px;
    color: #a0aec0;
    margin: 0 0 22px;
}
.session-modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}
.session-btn {
    padding: 12px 22px;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
    font-family: inherit;
}
.session-btn-continue {
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
    color: #fff;
    box-shadow: 0 4px 14px rgba(14, 76, 146, 0.35);
    flex: 1;
    justify-content: center;
}
.session-btn-continue:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(14, 76, 146, 0.45);
}
.session-btn-logout {
    background: #f1f2f6;
    color: #e74c3c;
    flex: 1;
    justify-content: center;
}
.session-btn-logout:hover {
    background: #ffeaea;
    transform: translateY(-2px);
}
</style>

<script>
/* ============================================= */
/* SESSION TIMEOUT CONFIGURATION */
/* ============================================= */
const SESSION_TIMEOUT_MS   = 5 * 60 * 1000;   // 5 minutes total inactivity
const SESSION_WARNING_MS   = 1 * 60 * 1000;   // 1 minute before logout (warning)
const SESSION_COUNTDOWN_S  = SESSION_WARNING_MS / 1000; // 60 seconds

let sessionTimeoutTimer   = null;   // fires when the warning should appear
let sessionLogoutTimer    = null;   // fires when auto-logout should happen
let sessionCountdownTimer = null;   // updates the countdown display
let sessionSecondsLeft    = SESSION_COUNTDOWN_S;
let sessionModalVisible   = false;

/* ---------------------------------------------------------
   Reset the inactivity timers (called on any user activity)
   --------------------------------------------------------- */
function resetSessionTimer() {
    // Clear all existing timers
    clearTimeout(sessionTimeoutTimer);
    clearTimeout(sessionLogoutTimer);
    clearInterval(sessionCountdownTimer);

    // If the warning modal is currently visible, hide it and reset countdown
    hideSessionModal();

    // Schedule the warning modal to appear 1 minute before timeout
    sessionTimeoutTimer = setTimeout(showSessionModal, SESSION_TIMEOUT_MS - SESSION_WARNING_MS);

    // Schedule the automatic logout when the full timeout elapses
    sessionLogoutTimer = setTimeout(autoLogout, SESSION_TIMEOUT_MS);
}

/* ---------------------------------------------------------
   Show the warning modal and start the 60-second countdown
   --------------------------------------------------------- */
function showSessionModal() {
    sessionModalVisible = true;
    sessionSecondsLeft  = SESSION_COUNTDOWN_S;

    const modal     = document.getElementById('sessionTimeoutModal');
    const countdown = document.getElementById('sessionCountdown');

    if (countdown) countdown.textContent = sessionSecondsLeft;
    if (modal) modal.style.display = 'flex';

    // Tick every second
    sessionCountdownTimer = setInterval(() => {
        sessionSecondsLeft--;
        if (countdown) {
            countdown.textContent = sessionSecondsLeft;
            countdown.classList.add('tick');
            setTimeout(() => countdown.classList.remove('tick'), 200);
        }
        // If it reaches 0, the logout timer (set in resetSessionTimer) will fire
        if (sessionSecondsLeft <= 0) {
            clearInterval(sessionCountdownTimer);
        }
    }, 1000);
}

/* ---------------------------------------------------------
   Hide the warning modal (without clearing main timers)
   --------------------------------------------------------- */
function hideSessionModal() {
    const modal = document.getElementById('sessionTimeoutModal');
    if (modal) modal.style.display = 'none';
    clearInterval(sessionCountdownTimer);
    sessionModalVisible = false;
}

/* ---------------------------------------------------------
   Continue session – user clicked "Continue"
   --------------------------------------------------------- */
function continueSession() {
    hideSessionModal();
    // Re-arm the timers from scratch
    clearTimeout(sessionTimeoutTimer);
    clearTimeout(sessionLogoutTimer);
    sessionTimeoutTimer = setTimeout(showSessionModal, SESSION_TIMEOUT_MS - SESSION_WARNING_MS);
    sessionLogoutTimer  = setTimeout(autoLogout, SESSION_TIMEOUT_MS);
}

/* ---------------------------------------------------------
   Automatic logout when the full timeout is reached
   --------------------------------------------------------- */
function autoLogout() {
    hideSessionModal();
    window.location.href = '../logout.php?reason=timeout';
}

/* ---------------------------------------------------------
   Manual logout (from modal button or sidebar button)
   --------------------------------------------------------- */
function logoutNow() {
    clearTimeout(sessionTimeoutTimer);
    clearTimeout(sessionLogoutTimer);
    clearInterval(sessionCountdownTimer);
    window.location.href = 'logout.php';
}

/* ---------------------------------------------------------
   Activity listeners – reset the timer on any interaction
   --------------------------------------------------------- */
const activityEvents = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
activityEvents.forEach(function (evt) {
    document.addEventListener(evt, function () {
        // Only reset if the modal is NOT visible.
        // Once the warning modal is showing, the user must explicitly
        // click "Continue" to reset the inactivity timer.
        if (!sessionModalVisible) {
            resetSessionTimer();
        }
    }, { passive: true });
});

/* ---------------------------------------------------------
   Initialize the session timeout on page load
   --------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', function () {
    resetSessionTimer();

    // Wire up the modal buttons
    const continueBtn = document.getElementById('sessionContinueBtn');
    const logoutBtn   = document.getElementById('sessionLogoutBtn');

    if (continueBtn) continueBtn.addEventListener('click', continueSession);
    if (logoutBtn)   logoutBtn.addEventListener('click', logoutNow);

    // ----- EXISTING SIDEBAR LOGIC -----
    document.querySelectorAll('.submenu.active').forEach(s => {
        s.style.display = 'block';
    });

    setTimeout(function () {
        const activeSub  = document.querySelector('.submenu-item.active a');
        const activeTop  = document.querySelector('.nav-item.active > a');
        const target     = activeSub || activeTop;
        const container  = document.querySelector('.sidebar-nav-container')
                        || document.querySelector('.sidebar-content')
                        || document.querySelector('.unique-sidebar');

        if (!target || !container) return;

        const targetRect    = target.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();
        const offset        = targetRect.top - containerRect.top;
        const desiredScroll = container.scrollTop + offset - (containerRect.height / 2) + (targetRect.height / 2);

        container.scrollTo({
            top: Math.max(0, desiredScroll),
            behavior: 'auto'
        });
    }, 50);
});

/* ---------------------------------------------------------
   Existing sidebar functions
   --------------------------------------------------------- */
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
        logoutNow();
    }
}
</script>

<?php
?>