<?php
// includes/sidebar.php
require_once 'includes/notification_functions.php';

$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$current_subpage = isset($_GET['subpage']) ? $_GET['subpage'] : '';
$collapsed = isset($_COOKIE['sidebar']) && $_COOKIE['sidebar'] == 'collapsed';

// Get user information
$user = getUserInfo($pdo, $_SESSION['user_id']);
$role = $user['role'];
$full_name = $user['full_name'] ?? 'User';
$first_name = explode(' ', $full_name)[0];

// Get profile picture
$profile_picture = $user['profile_picture'] ?? null;
$profile_picture_path = '';
if ($profile_picture && file_exists('uploads/profile_pictures/' . $profile_picture)) {
    $profile_picture_path = 'uploads/profile_pictures/' . $profile_picture;
}

// Get role display name
$role_display = ucfirst($role);

// Get HR stats for widget
$hr_stats = getHRStats($pdo, $_SESSION['user_id']);

// Get unread notifications count per module
$module_counts = getUnreadCountByModule($pdo, $_SESSION['user_id']);

// Function to check if a module is active
function isModuleActive($module, $current_page, $current_subpage = '') {
    if ($current_page == $module) return true;
    if ($current_subpage && strpos($current_subpage, $module) === 0) return true;
    return false;
}

// Function to check if a subpage is active
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
            <button class="sidebar-toggle-btn" onclick="toggleSidebar()">
                <i class="fas fa-chevron-<?php echo $collapsed ? 'right' : 'left'; ?>"></i>
            </button>
        </div>

        <div class="sidebar-nav-container">
            <!-- MAIN DASHBOARD -->
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
                            <div class="icon-wrapper">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">HR Dashboard</span>
                            <?php if ($module_counts['system']['count'] ?? 0 > 0): ?>
                            <span class="nav-badge"><?php echo $module_counts['system']['count']; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- TALENT ACQUISITION & WORKFORCE ENTRY -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-user-plus"></i>
                    <span>TALENT ACQUISITION</span>
                </div>
                <?php endif; ?>
                
                <!-- 1. APPLICANT MANAGEMENT (Screening & Shortlisting) -->
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('applicant', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #0e4c92;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('applicant-submenu')">
                            <div class="icon-wrapper">
                                <i class="fas fa-users"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Applicant Management</span>
                            <?php if (($module_counts['applicant']['count'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $module_counts['applicant']['count']; ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu" id="applicant-submenu" style="<?php echo (isModuleActive('applicant', $current_page, $current_subpage) && !$collapsed) ? 'display: block;' : 'display: none;'; ?>">
                        <li class="submenu-item <?php echo isSubpageActive('applicant-dashboard', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=applicant&subpage=applicant-dashboard">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Applicant Dashboard</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('applicant-profiles', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=applicant&subpage=applicant-profiles">
                                <i class="fas fa-id-card"></i>
                                <span>Applicant Profiles</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('document-verification', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=applicant&subpage=document-verification">
                                <i class="fas fa-file-signature"></i>
                                <span>Document Verification</span>
                                <?php if (($hr_stats['pending_verifications'] ?? 0) > 0): ?>
                                <span class="submenu-badge"><?php echo $hr_stats['pending_verifications']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <!-- NEW: Screening & Evaluation -->
                        <li class="submenu-item <?php echo isSubpageActive('screening-evaluation', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=applicant&subpage=screening-evaluation">
                                <i class="fas fa-clipboard-check"></i>
                                <span>Screening & Evaluation</span>
                                <?php if (($hr_stats['pending_screening'] ?? 0) > 0): ?>
                                <span class="submenu-badge"><?php echo $hr_stats['pending_screening']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <!-- NEW: Shortlisted Candidates -->
                        <li class="submenu-item <?php echo isSubpageActive('shortlisted-candidates', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=applicant&subpage=shortlisted-candidates">
                                <i class="fas fa-star"></i>
                                <span>Shortlisted Candidates</span>
                                <?php if (($hr_stats['shortlisted_count'] ?? 0) > 0): ?>
                                <span class="submenu-badge"><?php echo $hr_stats['shortlisted_count']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('application-status', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=applicant&subpage=application-status">
                                <i class="fas fa-tasks"></i>
                                <span>Status Tracking</span>
                            </a>
                        </li>
                        
                    </ul>
                </ul>

                <!-- 2. RECRUITMENT MANAGEMENT (Interviewing & Selection) -->
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('recruitment', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #1a5da0;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('recruitment-submenu')">
                            <div class="icon-wrapper">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Recruitment Management</span>
                            <?php if (($module_counts['recruitment']['count'] ?? 0) > 0): ?>
                            <span class="nav-badge pulse"><?php echo $module_counts['recruitment']['count']; ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu" id="recruitment-submenu" style="<?php echo (isModuleActive('recruitment', $current_page, $current_subpage) && !$collapsed) ? 'display: block;' : 'display: none;'; ?>">
                        <li class="submenu-item <?php echo isSubpageActive('job-posting', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=recruitment&subpage=job-posting">
                                <i class="fas fa-briefcase"></i>
                                <span>Job Posting Management</span>
                                <?php if (($module_counts['recruitment']['count'] ?? 0) > 0): ?>
                                <span class="submenu-badge"><?php echo $module_counts['recruitment']['count']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <!-- NEW: Interview Scheduling (from shortlisted) -->
                        <li class="submenu-item <?php echo isSubpageActive('interview-scheduling', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=recruitment&subpage=interview-scheduling">
                                <i class="fas fa-calendar-check"></i>
                                <span>Interview Scheduling</span>
                                <?php if (($hr_stats['scheduled_interviews'] ?? 0) > 0): ?>
                                <span class="submenu-badge"><?php echo $hr_stats['scheduled_interviews']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <!-- NEW: Interview Panel Evaluation -->
                        <li class="submenu-item <?php echo isSubpageActive('interview-panel', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=recruitment&subpage=interview-panel">
                                <i class="fas fa-users-cog"></i>
                                <span>Panel Evaluation</span>
                                <?php if (($hr_stats['pending_evaluations'] ?? 0) > 0): ?>
                                <span class="submenu-badge"><?php echo $hr_stats['pending_evaluations']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                       
                        <!-- NEW: Final Selection & Offers -->
                        <li class="submenu-item <?php echo isSubpageActive('final-selection', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=recruitment&subpage=final-selection">
                                <i class="fas fa-trophy"></i>
                                <span>Final Selection</span>
                                <?php if (($hr_stats['pending_offers'] ?? 0) > 0): ?>
                                <span class="submenu-badge"><?php echo $hr_stats['pending_offers']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('hiring-pipeline', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=recruitment&subpage=hiring-pipeline">
                                <i class="fas fa-filter"></i>
                                <span>Hiring Pipeline</span>
                            </a>
                        </li>

                         <!-- NEW: Interview Feedback & Ranking -->
                        <li class="submenu-item <?php echo isSubpageActive('interview-feedback', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=recruitment&subpage=interview-feedback">
                                <i class="fas fa-star"></i>
                                <span>Feedback & Ranking</span>
                            </a>
                        </li>
                        
                        
                    </ul>
                </ul>

                <!-- 3. NEW HIRE ONBOARDING -->
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('onboarding', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #2a6eb0;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('onboarding-submenu')">
                            <div class="icon-wrapper">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">New Hire Onboarding</span>
                            <?php if (($module_counts['onboarding']['count'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $module_counts['onboarding']['count']; ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu" id="onboarding-submenu" style="<?php echo (isModuleActive('onboarding', $current_page, $current_subpage) && !$collapsed) ? 'display: block;' : 'display: none;'; ?>">
                        <li class="submenu-item <?php echo isSubpageActive('onboarding-dashboard', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=onboarding&subpage=onboarding-dashboard">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>New Hire Dashboard</span>
                            </a>
                        </li>
                        
                        <li class="submenu-item <?php echo isSubpageActive('document-submission', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=onboarding&subpage=document-submission">
                                <i class="fas fa-file-upload"></i>
                                <span>Document Submission</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('orientation-schedule', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=onboarding&subpage=orientation-schedule">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Orientation Schedule</span>
                            </a>
                        </li>
                    
                       
                    </ul>
                </ul>

                <!-- 4. PERFORMANCE MANAGEMENT -->
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('performance', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #3a7fc0;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('performance-submenu')">
                            <div class="icon-wrapper">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Performance Management</span>
                            <?php if (($module_counts['performance']['count'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $module_counts['performance']['count']; ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu" id="performance-submenu" style="<?php echo (isModuleActive('performance', $current_page, $current_subpage) && !$collapsed) ? 'display: block;' : 'display: none;'; ?>">
                        <li class="submenu-item <?php echo isSubpageActive('probation-tracking', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=performance&subpage=probation-tracking">
                                <i class="fas fa-clock"></i>
                                <span>Probation Tracking</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('performance-reviews', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=performance&subpage=performance-reviews">
                                <i class="fas fa-star"></i>
                                <span>Performance Reviews</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('attendance-tracking', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=performance&subpage=attendance-tracking">
                                <i class="fas fa-clock"></i>
                                <span>Attendance & Time Tracking</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('feedback-notes', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=performance&subpage=feedback-notes">
                                <i class="fas fa-comment"></i>
                                <span>Feedback & Notes</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('confirmation-decisions', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=performance&subpage=confirmation-decisions">
                                <i class="fas fa-gavel"></i>
                                <span>Promotion/Confirmation</span>
                            </a>
                        </li>
                    </ul>
                </ul>

                <!-- 5. SOCIAL RECOGNITION -->
                <ul class="nav-menu">
                    <li class="nav-item has-submenu <?php echo isModuleActive('recognition', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #4a90d0;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('recognition-submenu')">
                            <div class="icon-wrapper">
                                <i class="fas fa-award"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Social Recognition</span>
                            <?php if (($module_counts['recognition']['count'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $module_counts['recognition']['count']; ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu" id="recognition-submenu" style="<?php echo (isModuleActive('recognition', $current_page, $current_subpage) && !$collapsed) ? 'display: block;' : 'display: none;'; ?>">
                        <li class="submenu-item <?php echo isSubpageActive('employee-month', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=recognition&subpage=employee-month">
                                <i class="fas fa-crown"></i>
                                <span>Employee of the Month</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('recognition-feed', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=recognition&subpage=recognition-feed">
                                <i class="fas fa-rss"></i>
                                <span>Recognition Feed</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('rewards-incentives', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=recognition&subpage=rewards-incentives">
                                <i class="fas fa-gift"></i>
                                <span>Rewards & Incentives</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('milestones', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=recognition&subpage=milestones">
                                <i class="fas fa-birthday-cake"></i>
                                <span>Milestones & Anniversaries</span>
                            </a>
                        </li>
                    </ul>
                </ul>
            </div>

            <!-- USER MANAGEMENT -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-cog"></i>
                    <span>ADMIN</span>
                </div>
                <?php endif; ?>
                <ul class="nav-menu">
                    <li class="nav-item <?php echo $current_page == 'user-management' ? 'active' : ''; ?>" style="--item-color: #0e4c92;">
                        <a href="?page=user-management">
                            <div class="icon-wrapper">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">User Management</span>
                            <?php if (($module_counts['user']['count'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $module_counts['user']['count']; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $current_page == 'profile' ? 'active' : ''; ?>" style="--item-color: #1a5da0;">
                        <a href="?page=profile">
                            <div class="icon-wrapper">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">My Profile</span>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $current_page == 'settings' ? 'active' : ''; ?>" style="--item-color: #2a6eb0;">
                        <a href="?page=settings">
                            <div class="icon-wrapper">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Settings</span>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="sidebar-footer">
            <!-- HR Quick Stats Widget -->
            <div class="savings-widget">
                <div class="savings-icon">
                    <i class="fas fa-users"></i>
                </div>
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

            <!-- User Profile -->
            <div class="couple-profile-widget">
                <div class="couple-avatar-single">
                    <?php if ($profile_picture_path): ?>
                        <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" 
                             alt="<?php echo htmlspecialchars($full_name); ?>"
                             class="profile-avatar">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($first_name); ?>&background=0e4c92&color=fff&size=100&bold=true&format=png&length=1" 
                             alt="<?php echo htmlspecialchars($full_name); ?>">
                    <?php endif; ?>
                    <div class="status-badge"></div>
                </div>
                
                <?php if (!$collapsed): ?>
                <div class="couple-info">
                    <div class="couple-names">
                        <span class="your-name">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($first_name); ?>
                        </span>
                        <span class="partner-name">
                            <i class="fas fa-tag" style="color: #e74c3c;"></i> 
                            HR 1 | <?php echo $role_display; ?>
                        </span>
                    </div>
                    <span class="couple-role">
                        <i class="fas fa-building" style="color: #f1c40f;"></i> 
                        Talent Acquisition
                    </span>
                </div>
                <?php endif; ?>
                
                <button class="logout-btn" onclick="logout()" title="Logout">
                    <i class="fas fa-power-off"></i>
                </button>
            </div>
        </div>
    </div>
</aside>

<style>
/* Logo image styles */
.logo-wrapper {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.2);
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 15px;
}

/* Submenu Styles */
.has-submenu > a {
    position: relative;
}

.submenu-arrow {
    margin-left: auto;
    font-size: 12px;
    transition: transform 0.3s;
}

.has-submenu.active .submenu-arrow {
    transform: rotate(180deg);
}

.submenu {
    list-style: none;
    padding-left: 55px;
    margin: 5px 0 10px 0;
    display: none;
}

.unique-sidebar.collapsed .submenu {
    display: none !important;
}

.submenu-item {
    margin: 3px 0;
}

.submenu-item a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    color: #4a5568;
    text-decoration: none;
    border-radius: 12px;
    font-size: 13px;
    transition: all 0.3s;
    position: relative;
}

.submenu-item a:hover {
    background: rgba(14, 76, 146, 0.1);
    color: #0e4c92;
}

.submenu-item.active a {
    background: rgba(14, 76, 146, 0.15);
    color: #0e4c92;
    font-weight: 500;
}

.submenu-item i {
    width: 18px;
    font-size: 12px;
    color: #0e4c92;
}

.submenu-badge {
    background: #e74c3c;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: auto;
}

/* Nav badge for main menu items */
.nav-badge {
    background: #e74c3c;
    color: white;
    font-size: 10px;
    font-weight: 600;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    margin-left: 8px;
    animation: pulse 2s infinite;
}

.nav-badge.pulse {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification-badge-small {
    background: #e74c3c;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 5px;
}

.couple-avatar-single {
    position: relative;
    width: 50px;
    height: 50px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
    box-shadow: 0 4px 10px rgba(14, 76, 146, 0.2);
}

.couple-avatar-single img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 12px;
}

.profile-avatar {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 12px;
}

.unique-sidebar.collapsed .couple-avatar-single {
    width: 40px;
    height: 40px;
    margin: 0 auto;
}

/* When sidebar is collapsed, hide text but keep logo */
.unique-sidebar.collapsed .logo-wrapper {
    width: 40px;
    height: 40px;
    margin: 0 auto;
}
</style>

<script>
function toggleSubmenu(submenuId) {
    const submenu = document.getElementById(submenuId);
    if (submenu) {
        if (submenu.style.display === 'block') {
            submenu.style.display = 'none';
        } else {
            submenu.style.display = 'block';
        }
    }
}

function toggleSidebar() {
    const sidebar = document.querySelector('.unique-sidebar');
    const isCollapsed = sidebar.classList.contains('collapsed');
    
    if (isCollapsed) {
        sidebar.classList.remove('collapsed');
        document.cookie = "sidebar=expanded; path=/; max-age=" + 60*60*24*30;
    } else {
        sidebar.classList.add('collapsed');
        document.cookie = "sidebar=collapsed; path=/; max-age=" + 60*60*24*30;
    }
    
    // Close all submenus when collapsing
    if (!isCollapsed) {
        document.querySelectorAll('.submenu').forEach(submenu => {
            submenu.style.display = 'none';
        });
    }
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}
</script>