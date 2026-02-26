<?php
// includes/sidebar-manager.php
require_once 'includes/notification_functions.php';

$current_page = isset($_GET['page']) ? $_GET['page'] : 'manager-dashboard';
$current_subpage = isset($_GET['subpage']) ? $_GET['subpage'] : '';
$collapsed = isset($_COOKIE['sidebar']) && $_COOKIE['sidebar'] == 'collapsed';

// Get user information
$user = getUserInfo($pdo, $_SESSION['user_id']);
$role = $user['role'];
$full_name = $user['full_name'] ?? 'Manager';
$first_name = explode(' ', $full_name)[0];

// Get profile picture
$profile_picture = $user['profile_picture'] ?? null;
$profile_picture_path = '';
if ($profile_picture && file_exists('uploads/profile_pictures/' . $profile_picture)) {
    $profile_picture_path = 'uploads/profile_pictures/' . $profile_picture;
}

// Get role display name
$role_display = 'HR Manager';

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
                    <img src="assets/images/logo1.png" alt="HR Manager" class="logo-image" 
                         onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=HRM&background=9b59b6&color=fff&size=100&bold=true&format=png';">
                </div>
                <?php if (!$collapsed): ?>
                <div class="logo-text-wrapper">
                    <span class="logo-bcp">SLATE</span>
                    <span class="logo-budget">HR MANAGER</span>
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
                    <li class="nav-item <?php echo $current_page == 'manager-dashboard' ? 'active' : ''; ?>" style="--item-color: #9b59b6;">
                        <a href="?page=manager-dashboard">
                            <div class="icon-wrapper">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Manager Dashboard</span>
                            <?php if ($module_counts['system']['count'] ?? 0 > 0): ?>
                            <span class="nav-badge"><?php echo $module_counts['system']['count']; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- MY TEAM DASHBOARD (New) -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-users"></i>
                    <span>MY TEAM</span>
                </div>
                <?php endif; ?>
                
                <ul class="nav-menu">
                    <!-- Team Dashboard -->
                    <li class="nav-item <?php echo $current_page == 'team-dashboard' ? 'active' : ''; ?>" style="--item-color: #3498db;">
                        <a href="?page=team-dashboard">
                            <div class="icon-wrapper">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">📊 My Team Dashboard</span>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Probation Evaluations -->
                    <li class="nav-item <?php echo $current_page == 'probation-evaluations' ? 'active' : ''; ?>" style="--item-color: #f39c12;">
                        <a href="?page=probation-evaluations">
                            <div class="icon-wrapper">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">📝 Probation Evaluations</span>
                            <?php if (($hr_stats['ongoing_probation'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $hr_stats['ongoing_probation'] ?? 0; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Performance Reviews -->
                    <li class="nav-item <?php echo $current_page == 'performance-reviews' ? 'active' : ''; ?>" style="--item-color: #27ae60;">
                        <a href="?page=performance-reviews">
                            <div class="icon-wrapper">
                                <i class="fas fa-star"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">📈 Performance Reviews</span>
                            <?php if (($hr_stats['pending_reviews'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $hr_stats['pending_reviews'] ?? 0; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Promotion Recommendations -->
                    <li class="nav-item <?php echo $current_page == 'promotion-recommendations' ? 'active' : ''; ?>" style="--item-color: #9b59b6;">
                        <a href="?page=promotion-recommendations">
                            <div class="icon-wrapper">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">📋 Promotion Recommendations</span>
                            <?php if (($hr_stats['pending_promotions'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $hr_stats['pending_promotions'] ?? 0; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Performance Improvement Plans -->
                    <li class="nav-item <?php echo $current_page == 'performance-improvement-plans' ? 'active' : ''; ?>" style="--item-color: #e74c3c;">
                        <a href="?page=performance-improvement-plans">
                            <div class="icon-wrapper">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">⚠ Performance Improvement Plans</span>
                            <?php if (($hr_stats['active_pips'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $hr_stats['active_pips'] ?? 0; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- EMPLOYEE MANAGEMENT -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-users-cog"></i>
                    <span>EMPLOYEES</span>
                </div>
                <?php endif; ?>
                
                <ul class="nav-menu">
                    <!-- Employee Directory -->
                    <li class="nav-item <?php echo $current_page == 'employee-directory' ? 'active' : ''; ?>" style="--item-color: #3498db;">
                        <a href="?page=employee-directory">
                            <div class="icon-wrapper">
                                <i class="fas fa-address-book"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Employee Directory</span>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Team Overview -->
                    <li class="nav-item <?php echo $current_page == 'team-overview' ? 'active' : ''; ?>" style="--item-color: #2ecc71;">
                        <a href="?page=team-overview">
                            <div class="icon-wrapper">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Team Overview</span>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Attendance Overview -->
                    <li class="nav-item <?php echo $current_page == 'attendance-overview' ? 'active' : ''; ?>" style="--item-color: #e67e22;">
                        <a href="?page=attendance-overview">
                            <div class="icon-wrapper">
                                <i class="fas fa-clock"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Attendance Overview</span>
                            <?php if (($hr_stats['attendance_alerts'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $hr_stats['attendance_alerts'] ?? 0; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- REPORTS & ANALYTICS -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-chart-bar"></i>
                    <span>REPORTS</span>
                </div>
                <?php endif; ?>
                
                <ul class="nav-menu">
                    <!-- Performance Reports -->
                    <li class="nav-item has-submenu <?php echo isModuleActive('reports', $current_page, $current_subpage) ? 'active' : ''; ?>" style="--item-color: #9b59b6;">
                        <a href="javascript:void(0)" onclick="toggleSubmenu('reports-submenu')">
                            <div class="icon-wrapper">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Performance Reports</span>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    <ul class="submenu" id="reports-submenu" style="<?php echo (isModuleActive('reports', $current_page, $current_subpage) && !$collapsed) ? 'display: block;' : 'display: none;'; ?>">
                        <li class="submenu-item <?php echo isSubpageActive('probation-report', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=reports&subpage=probation-report">
                                <i class="fas fa-hourglass-half"></i>
                                <span>Probation Report</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('performance-report', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=reports&subpage=performance-report">
                                <i class="fas fa-star"></i>
                                <span>Performance Review Report</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('promotion-report', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=reports&subpage=promotion-report">
                                <i class="fas fa-arrow-up"></i>
                                <span>Promotion Recommendations</span>
                            </a>
                        </li>
                        <li class="submenu-item <?php echo isSubpageActive('pip-report', $current_subpage) ? 'active' : ''; ?>">
                            <a href="?page=reports&subpage=pip-report">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>PIP Report</span>
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Analytics Dashboard -->
                    <li class="nav-item <?php echo $current_page == 'analytics' ? 'active' : ''; ?>" style="--item-color: #3498db;">
                        <a href="?page=analytics">
                            <div class="icon-wrapper">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Analytics Dashboard</span>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Export Data -->
                    <li class="nav-item <?php echo $current_page == 'export' ? 'active' : ''; ?>" style="--item-color: #27ae60;">
                        <a href="?page=export">
                            <div class="icon-wrapper">
                                <i class="fas fa-file-export"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Export Data</span>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- COMMUNICATION -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-comments"></i>
                    <span>COMMUNICATION</span>
                </div>
                <?php endif; ?>
                
                <ul class="nav-menu">
                    <!-- Announcements -->
                    <li class="nav-item <?php echo $current_page == 'announcements' ? 'active' : ''; ?>" style="--item-color: #f39c12;">
                        <a href="?page=announcements">
                            <div class="icon-wrapper">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Announcements</span>
                            <?php if (($module_counts['announcements']['count'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $module_counts['announcements']['count']; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Feedback Inbox -->
                    <li class="nav-item <?php echo $current_page == 'feedback-inbox' ? 'active' : ''; ?>" style="--item-color: #3498db;">
                        <a href="?page=feedback-inbox">
                            <div class="icon-wrapper">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Feedback Inbox</span>
                            <?php if (($hr_stats['unread_feedback'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $hr_stats['unread_feedback'] ?? 0; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- SETTINGS & PROFILE -->
            <div class="sidebar-section">
                <?php if (!$collapsed): ?>
                <div class="section-header">
                    <i class="fas fa-cog"></i>
                    <span>ACCOUNT</span>
                </div>
                <?php endif; ?>
                
                <ul class="nav-menu">
                    <li class="nav-item <?php echo $current_page == 'profile' ? 'active' : ''; ?>" style="--item-color: #3498db;">
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
                    
                    <li class="nav-item <?php echo $current_page == 'notifications' ? 'active' : ''; ?>" style="--item-color: #f39c12;">
                        <a href="?page=notifications">
                            <div class="icon-wrapper">
                                <i class="fas fa-bell"></i>
                            </div>
                            <?php if (!$collapsed): ?>
                            <span class="nav-label">Notifications</span>
                            <?php if (($module_counts['notifications']['count'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?php echo $module_counts['notifications']['count']; ?></span>
                            <?php endif; ?>
                            <div class="nav-indicator"></div>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="nav-item <?php echo $current_page == 'settings' ? 'active' : ''; ?>" style="--item-color: #7f8c8d;">
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
            <!-- Quick Stats Widget -->
            <div class="savings-widget">
                <div class="savings-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <?php if (!$collapsed): ?>
                <div class="savings-info">
                    <span class="savings-label">Team Performance</span>
                    <span class="savings-value">87%</span>
                    <div class="savings-bar">
                        <div class="savings-progress" style="width: 87%; background: #9b59b6;"></div>
                    </div>
                    <span class="savings-detail">
                        <i class="fas fa-arrow-up" style="color: #27ae60;"></i> +5% vs last month
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Manager Profile -->
            <div class="couple-profile-widget">
                <div class="couple-avatar-single">
                    <?php if ($profile_picture_path): ?>
                        <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" 
                             alt="<?php echo htmlspecialchars($full_name); ?>"
                             class="profile-avatar">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($first_name); ?>&background=9b59b6&color=fff&size=100&bold=true&format=png&length=1" 
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
                            <i class="fas fa-tag" style="color: #9b59b6;"></i> 
                            HR Manager
                        </span>
                    </div>
                    <span class="couple-role">
                        <i class="fas fa-building" style="color: #f1c40f;"></i> 
                        Talent & Performance
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
/* Custom styles for manager sidebar */
.unique-sidebar .logo-wrapper {
    background: linear-gradient(135deg, #9b59b6, #8e44ad);
}

.unique-sidebar .logo-budget {
    color: #9b59b6;
}

.unique-sidebar .nav-item.active {
    background: linear-gradient(98deg, rgba(155, 89, 182, 0.1) 0%, rgba(155, 89, 182, 0.05) 100%);
    border-left: 4px solid #9b59b6;
}

.unique-sidebar .nav-item.active .icon-wrapper {
    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
    box-shadow: 0 5px 15px rgba(155, 89, 182, 0.3);
}

.unique-sidebar .nav-item:hover .icon-wrapper {
    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
}

.unique-sidebar .nav-badge {
    background: #9b59b6;
}

.unique-sidebar .savings-widget .savings-icon {
    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
}

.submenu-item.active a {
    background: rgba(155, 89, 182, 0.15);
    color: #9b59b6;
}

.submenu-item i {
    color: #9b59b6;
}

.couple-avatar-single {
    background: linear-gradient(135deg, #9b59b6, #8e44ad);
}

/* Section header adjustments */
.section-header i {
    color: #9b59b6;
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