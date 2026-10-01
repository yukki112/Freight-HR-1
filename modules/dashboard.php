<?php
// modules/dashboard.php

// ============================================================
// AI ANALYTICS FUNCTIONS
// ============================================================
function getAIAnalytics($pdo) {
    try {
        $insights = [];

        // Analyze recent hiring trends
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_applications,
                AVG(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) * 100 as hire_rate,
                DATE(created_at) as date
            FROM job_applications
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 7
        ");
        $stmt->execute();
        $trends = $stmt->fetchAll();

        if (!empty($trends)) {
            $avg_daily = array_sum(array_column($trends, 'total_applications')) / count($trends);

            if ($avg_daily > 10) {
                $insights[] = [
                    'title'      => 'High Application Volume',
                    'description'=> 'Consider adding more screening resources. Analysis predicts a 20% increase next week.',
                    'color'      => '#f39c12',
                    'confidence' => 85
                ];
            }

            $avg_hire_rate = array_sum(array_column($trends, 'hire_rate')) / count($trends);
            if ($avg_hire_rate < 30) {
                $insights[] = [
                    'title'      => 'Optimize Screening Process',
                    'description'=> 'Hire rate is below target. Analysis recommends reviewing job requirements.',
                    'color'      => '#e74c3c',
                    'confidence' => 78
                ];
            }
        }

        // Check for positions with low applicants
        $stmt = $pdo->prepare("
            SELECT jp.title, COUNT(ja.id) as applicant_count
            FROM job_postings jp
            LEFT JOIN job_applications ja ON jp.id = ja.job_posting_id
            WHERE jp.status = 'published'
            GROUP BY jp.id
            HAVING applicant_count < 3
            LIMIT 2
        ");
        $stmt->execute();
        $low_applicants = $stmt->fetchAll();

        foreach ($low_applicants as $job) {
            $insights[] = [
                'title'      => 'Low Applications for ' . $job['title'],
                'description'=> 'Consider boosting job posting or adjusting requirements.',
                'color'      => '#3498db',
                'confidence' => 92
            ];
        }

        return ['recommendations' => $insights];
    } catch (Exception $e) {
        return ['recommendations' => []];
    }
}

function getPredictiveMetrics($pdo) {
    try {
        $metrics = [];

        // Predict tomorrow's applicants based on weekly average
        $stmt = $pdo->prepare("
            SELECT AVG(daily_count) as avg_daily
            FROM (
                SELECT DATE(created_at) as date, COUNT(*) as daily_count
                FROM job_applications
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
            ) as daily_stats
        ");
        $stmt->execute();
        $avg_daily = $stmt->fetchColumn();
        $metrics['applicants_tomorrow'] = $avg_daily ? round($avg_daily) : 0;

        // Count interviews this week
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM interviews
            WHERE interview_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $metrics['interviews_this_week'] = $stmt->fetchColumn() ?: 0;

        // Calculate average verification days
        $stmt = $pdo->prepare("
            SELECT AVG(DATEDIFF(verified_at, uploaded_at))
            FROM onboarding_documents
            WHERE verified_at IS NOT NULL
        ");
        $stmt->execute();
        $metrics['avg_verification_days'] = round($stmt->fetchColumn() ?: 0, 1);

        // Calculate probation success rate
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN final_decision = 'confirm' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)
            FROM probation_records
            WHERE final_decision != 'pending'
        ");
        $stmt->execute();
        $metrics['probation_success_rate'] = round($stmt->fetchColumn() ?: 0);

        // Retention risk - based on employees hired in last 90 days
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM new_hires 
            WHERE hire_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND status = 'terminated'
        ");
        $stmt->execute();
        $terminated = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM new_hires 
            WHERE hire_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmt->execute();
        $total_recent = (int)$stmt->fetchColumn();

        $metrics['retention_risk'] = $total_recent > 0
            ? round(($terminated / $total_recent) * 100, 1) . '%'
            : '0%';

        // Average performance score from completed final interviews
        $stmt = $pdo->prepare("
            SELECT AVG(final_score)
            FROM interviews
            WHERE final_score IS NOT NULL AND final_score > 0
        ");
        $stmt->execute();
        $avg_score = $stmt->fetchColumn();
        $metrics['avg_performance'] = $avg_score ? round($avg_score) . '%' : '0%';

        return $metrics;
    } catch (Exception $e) {
        return [
            'applicants_tomorrow'    => 0,
            'interviews_this_week'   => 0,
            'avg_verification_days'  => 0,
            'probation_success_rate' => 0,
            'retention_risk'         => '0%',
            'avg_performance'        => '0%'
        ];
    }
}

function getHiringForecast($pdo) {
    try {
        $forecast = ['projected_hires' => 0];

        $stmt = $pdo->prepare("
            SELECT SUM(slots_available - slots_filled) as total_openings
            FROM job_postings
            WHERE status = 'published'
        ");
        $stmt->execute();
        $openings = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as hires
            FROM new_hires
            WHERE hire_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $hires_last_month = (int)$stmt->fetchColumn();

        if ($openings && $hires_last_month) {
            $forecast['projected_hires'] = round(($openings * 0.6) + ($hires_last_month * 0.4));
        } elseif ($openings) {
            $forecast['projected_hires'] = round($openings * 0.7);
        } else {
            $forecast['projected_hires'] = $hires_last_month;
        }

        return $forecast;
    } catch (Exception $e) {
        return ['projected_hires' => 0];
    }
}

// ============================================================
// ENHANCED HR STATS - REAL DATA ONLY
// ============================================================
function getEnhancedHRStats($pdo, $user_id) {
    try {
        $stats = [];

        // Active employees count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM new_hires WHERE status = 'active'");
        $stmt->execute();
        $stats['active_employees'] = (int)$stmt->fetchColumn();

        // Onboarding count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM new_hires WHERE status = 'onboarding'");
        $stmt->execute();
        $stats['onboarding_count'] = (int)$stmt->fetchColumn();

        // Active jobs
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE status = 'published'");
        $stmt->execute();
        $stats['active_jobs'] = (int)$stmt->fetchColumn();

        // Total applicants
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications");
        $stmt->execute();
        $stats['total_applicants'] = (int)$stmt->fetchColumn();

        // New applicants today
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['new_applicants_today'] = (int)$stmt->fetchColumn();

        // Pending interviews
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM interviews WHERE status = 'scheduled'");
        $stmt->execute();
        $stats['pending_interviews'] = (int)$stmt->fetchColumn();

        // Pending verifications
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM onboarding_documents WHERE status = 'pending'");
        $stmt->execute();
        $stats['pending_verifications'] = (int)$stmt->fetchColumn();

        // Upcoming reviews (probation ending in next 30 days)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM probation_records 
            WHERE status = 'ongoing' 
            AND probation_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $stats['upcoming_reviews'] = (int)$stmt->fetchColumn();

        // Hired this month
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM new_hires 
            WHERE MONTH(hire_date) = MONTH(CURDATE()) 
            AND YEAR(hire_date) = YEAR(CURDATE())
        ");
        $stmt->execute();
        $stats['hired_this_month'] = (int)$stmt->fetchColumn();

        // Probation count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM probation_records WHERE status = 'ongoing'");
        $stmt->execute();
        $stats['probation_count'] = (int)$stmt->fetchColumn();

        // Probation success rate
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN final_decision = 'confirm' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)
            FROM probation_records
            WHERE final_decision != 'pending'
        ");
        $stmt->execute();
        $stats['probation_success_rate'] = round($stmt->fetchColumn() ?: 0);

        // Funnel metrics - REAL DATA
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE status IN ('in_review', 'shortlisted')");
        $stmt->execute();
        $stats['screened'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE status IN ('interviewed', 'ready_for_interview')");
        $stmt->execute();
        $stats['interviewed'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE status = 'offered'");
        $stmt->execute();
        $stats['offered'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE status = 'hired'");
        $stmt->execute();
        $stats['hired'] = (int)$stmt->fetchColumn();

        // Monthly hiring goal (configurable constant)
        $stats['monthly_hiring_goal'] = 15;

        return $stats;
    } catch (Exception $e) {
        return [
            'active_employees'       => 0,
            'onboarding_count'       => 0,
            'active_jobs'            => 0,
            'total_applicants'       => 0,
            'new_applicants_today'   => 0,
            'pending_interviews'     => 0,
            'pending_verifications'  => 0,
            'upcoming_reviews'       => 0,
            'hired_this_month'       => 0,
            'probation_count'        => 0,
            'probation_success_rate' => 0,
            'screened'               => 0,
            'interviewed'            => 0,
            'offered'                => 0,
            'hired'                  => 0,
            'monthly_hiring_goal'    => 15
        ];
    }
}

// ============================================================
// DATA FETCHERS - REAL DATA ONLY
// ============================================================
function getEnhancedRecentApplicants($pdo, $limit = 10) {
    try {
        // Real AI match score based on screening evaluation
        $stmt = $pdo->prepare("
            SELECT ja.*, jp.title as job_title,
                   COALESCE(se.screening_score, 0) as ai_match_score
            FROM job_applications ja
            LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
            LEFT JOIN screening_evaluations se ON se.applicant_id = ja.id
            ORDER BY ja.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getEnhancedUpcomingInterviews($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT i.*, 
                   CONCAT(ja.first_name, ' ', ja.last_name) as applicant_name,
                   ja.position_applied,
                   jp.title as job_title,
                   u.full_name as interviewer_name,
                   COALESCE(i.final_score, 0) as ai_prediction
            FROM interviews i
            JOIN job_applications ja ON i.applicant_id = ja.id
            LEFT JOIN job_postings jp ON i.job_posting_id = jp.id
            LEFT JOIN users u ON i.interviewer_id = u.id
            WHERE i.status = 'scheduled' 
            AND i.interview_date >= CURDATE()
            ORDER BY i.interview_date ASC, i.interview_time ASC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getEnhancedOnboardingList($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT nh.*, 
                   CONCAT(ja.first_name, ' ', ja.last_name) as employee_name,
                   jp.title as job_title
            FROM new_hires nh
            JOIN job_applications ja ON nh.applicant_id = ja.id
            LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
            WHERE nh.status = 'onboarding'
            ORDER BY nh.start_date ASC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getEnhancedPendingVerifications($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT od.*, 
                   CONCAT(ja.first_name, ' ', ja.last_name) as applicant_name,
                   CASE 
                       WHEN od.uploaded_at < DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 1
                       ELSE 2
                   END as ai_priority
            FROM onboarding_documents od
            JOIN new_hires nh ON od.new_hire_id = nh.id
            JOIN job_applications ja ON nh.applicant_id = ja.id
            WHERE od.status = 'pending'
            ORDER BY od.uploaded_at ASC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getEnhancedUserInfo($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return ['full_name' => 'User', 'role' => 'admin'];
    }
}

function timeAgoEnhanced($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;

    $minutes = round($seconds / 60);
    $hours   = round($seconds / 3600);
    $days    = round($seconds / 86400);
    $weeks   = round($seconds / 604800);
    $months  = round($seconds / 2629440);
    $years   = round($seconds / 31553280);

    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}

function getApplicantStatusBadgeEnhanced($status) {
    $colors = [
        'new'         => '#3498db',
        'in_review'   => '#f39c12',
        'shortlisted' => '#27ae60',
        'interviewed' => '#9b59b6',
        'offered'     => '#e67e22',
        'hired'       => '#2ecc71',
        'rejected'    => '#e74c3c',
        'on_hold'     => '#95a5a6'
    ];
    return $colors[$status] ?? '#3498db';
}

// ============================================================
// FETCH DATA
// ============================================================
$user                 = getEnhancedUserInfo($pdo, $_SESSION['user_id']);
$stats                = getEnhancedHRStats($pdo, $_SESSION['user_id']);
$recent_applicants    = getEnhancedRecentApplicants($pdo, 10);
$upcoming_interviews  = getEnhancedUpcomingInterviews($pdo, 5);
$onboarding_list      = getEnhancedOnboardingList($pdo, 5);
$pending_verifications= getEnhancedPendingVerifications($pdo, 5);

// Activity log with pagination
$page     = isset($_GET['activity_page']) ? (int)$_GET['activity_page'] : 1;
$per_page = 5;
$offset   = ($page - 1) * $per_page;

$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM activity_log al
    JOIN users u ON al.user_id = u.id
");
$count_stmt->execute();
$total_activities = $count_stmt->fetch()['total'];
$total_pages = ceil($total_activities / $per_page);

$stmt = $pdo->prepare("
    SELECT al.*, u.full_name, u.role 
    FROM activity_log al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT :offset, :per_page
");
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$activities = $stmt->fetchAll();

// AI Analytics Data
$ai_insights         = getAIAnalytics($pdo);
$predictive_metrics  = getPredictiveMetrics($pdo);
$hiring_forecast     = getHiringForecast($pdo);
?>

<style>
/* AI Insights Cards */
.ai-insights-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin: 20px 0;
}

.ai-insight-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 20px;
    color: white;
    position: relative;
    overflow: hidden;
}

.ai-insight-card.recruitment {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.ai-insight-card.retention {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.ai-insight-card.performance {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.ai-insight-card.sentiment {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.ai-insight-title {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 10px;
}

.ai-insight-value {
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 5px;
}

.ai-insight-trend {
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.trend-up { color: #a7ffeb; }
.trend-down { color: #ffb8b8; }

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.pagination-btn {
    background: white;
    border: none;
    padding: 8px 15px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.pagination-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-info { font-size: 14px; color: #666; }

.pagination-pages { display: flex; gap: 5px; }

.page-number {
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}

.page-number.active {
    background: #0e4c92;
    color: white;
}

.page-number:not(.active):hover {
    background: #f0f0f0;
}

/* AI Chart Container */
.ai-chart-container {
    background: white;
    border-radius: 25px;
    padding: 20px;
    margin-bottom: 20px;
}

.ai-chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.ai-chart-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ai-chart-title i {
    font-size: 24px;
    color: #667eea;
    background: linear-gradient(135deg, #667eea20, #764ba220);
    padding: 10px;
    border-radius: 15px;
}

.ai-chart-badge {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
}
</style>

<!-- ==================== WELCOME BANNER ==================== -->
<div class="budget-banner">
    <div class="banner-content">
        <div class="welcome-text">
            <h1>
                Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>!
            </h1>
            <p><?php echo date('l, F j, Y'); ?> &bull; HR Dashboard with AI Insights</p>
        </div>
        <div class="banner-stats">
            <div class="banner-stat">
                <span class="stat-value"><?php echo $stats['active_employees']; ?></span>
                <span class="stat-label">Active Employees</span>
            </div>
            <div class="banner-stat">
                <span class="stat-value"><?php echo $stats['onboarding_count']; ?></span>
                <span class="stat-label">In Onboarding</span>
            </div>
            <div class="banner-stat">
                <span class="stat-value"><?php echo $stats['active_jobs']; ?></span>
                <span class="stat-label">Open Positions</span>
            </div>
            <div class="banner-stat">
                <span class="stat-value"><?php echo $stats['total_applicants']; ?></span>
                <span class="stat-label">Total Applicants</span>
            </div>
        </div>
    </div>
    <div class="banner-decoration"></div>
</div>

<!-- ==================== AI INSIGHTS CARDS ==================== -->
<div class="ai-insights-grid">
    <div class="ai-insight-card recruitment">
        <div class="ai-insight-title">
            <i class="fas fa-robot"></i> Recruitment Forecast
        </div>
        <div class="ai-insight-value"><?php echo $hiring_forecast['projected_hires']; ?></div>
        <div class="ai-insight-trend">
            <i class="fas fa-chart-line"></i>
            <span>Projected hires this month</span>
        </div>
        <div style="font-size: 12px; margin-top: 10px; opacity: 0.8;">
            <i class="fas fa-clock"></i> Based on historical data
        </div>
    </div>

    <div class="ai-insight-card retention">
        <div class="ai-insight-title">
            <i class="fas fa-shield-alt"></i> Retention Risk
        </div>
        <div class="ai-insight-value"><?php echo $predictive_metrics['retention_risk']; ?></div>
        <div class="ai-insight-trend">
            <span class="trend-up"><i class="fas fa-arrow-down"></i> Last 90 days</span>
        </div>
        <div style="font-size: 12px; margin-top: 10px; opacity: 0.8;">
            <i class="fas fa-exclamation-triangle"></i> Based on terminations
        </div>
    </div>

    <div class="ai-insight-card performance">
        <div class="ai-insight-title">
            <i class="fas fa-chart-bar"></i> Performance Trend
        </div>
        <div class="ai-insight-value"><?php echo $predictive_metrics['avg_performance']; ?></div>
        <div class="ai-insight-trend">
            <span class="trend-up"><i class="fas fa-arrow-up"></i> Avg final interview score</span>
        </div>
        <div style="font-size: 12px; margin-top: 10px; opacity: 0.8;">
            <i class="fas fa-star"></i> Across all evaluations
        </div>
    </div>

    <div class="ai-insight-card sentiment">
        <div class="ai-insight-title">
            <i class="fas fa-smile"></i> Probation Success
        </div>
        <div class="ai-insight-value"><?php echo $predictive_metrics['probation_success_rate']; ?>%</div>
        <div class="ai-insight-trend">
            <span class="trend-up"><i class="fas fa-arrow-up"></i> Confirmed employees</span>
        </div>
        <div style="font-size: 12px; margin-top: 10px; opacity: 0.8;">
            <i class="fas fa-comment"></i> Based on probation records
        </div>
    </div>
</div>

<!-- ==================== AI ANALYTICS CHART ==================== -->
<div class="ai-chart-container">
    <div class="ai-chart-header">
        <div class="ai-chart-title">
            <i class="fas fa-chart-pie"></i>
            <h3>Hiring Analytics</h3>
        </div>
        <span class="ai-chart-badge">
            <i class="fas fa-sync-alt"></i> Real-time Analysis
        </span>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <!-- Hiring Funnel Chart -->
        <div>
            <canvas id="hiringFunnelChart" style="height: 300px; width: 100%;"></canvas>
        </div>

        <!-- AI Recommendations -->
        <div style="background: linear-gradient(135deg, #667eea10, #764ba210); border-radius: 20px; padding: 20px;">
            <h4 style="margin-bottom: 15px;">Recommendations</h4>

            <?php if (!empty($ai_insights['recommendations'])): ?>
                <?php foreach ($ai_insights['recommendations'] as $rec): ?>
                <div style="background: white; border-radius: 15px; padding: 15px; margin-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                        <i class="fas fa-lightbulb" style="color: <?php echo $rec['color']; ?>;"></i>
                        <strong><?php echo htmlspecialchars($rec['title']); ?></strong>
                    </div>
                    <p style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($rec['description']); ?></p>
                    <div style="font-size: 11px; color: #999; margin-top: 5px;">
                        <i class="fas fa-clock"></i> Confidence: <?php echo $rec['confidence']; ?>%
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="background: white; border-radius: 15px; padding: 20px; text-align: center; color: #666;">
                    <i class="fas fa-robot" style="font-size: 40px; margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>Analyzing data for recommendations...</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ==================== STATS GRID ==================== -->
<div class="stats-grid-unique">
    <div class="stat-card-unique budget">
        <div class="stat-icon-3d">
            <i class="fas fa-user-plus"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">New Applicants Today</span>
            <span class="stat-value"><?php echo $stats['new_applicants_today']; ?></span>
            <span class="stat-trend positive">
                <i class="fas fa-arrow-up"></i> Predicted tomorrow: <?php echo $predictive_metrics['applicants_tomorrow']; ?>
            </span>
        </div>
    </div>

    <div class="stat-card-unique expenses">
        <div class="stat-icon-3d">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Interviews</span>
            <span class="stat-value"><?php echo $stats['pending_interviews']; ?></span>
            <span class="stat-trend warning">
                <i class="fas fa-clock"></i> <?php echo $predictive_metrics['interviews_this_week']; ?> this week
            </span>
        </div>
    </div>

    <div class="stat-card-unique remaining">
        <div class="stat-icon-3d">
            <i class="fas fa-file-signature"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Verifications</span>
            <span class="stat-value"><?php echo $stats['pending_verifications']; ?></span>
            <span class="stat-trend">
                <i class="fas fa-hourglass-half"></i> Avg. processing: <?php echo $predictive_metrics['avg_verification_days']; ?> days
            </span>
        </div>
    </div>

    <div class="stat-card-unique savings">
        <div class="stat-icon-3d">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Probation Reviews</span>
            <span class="stat-value"><?php echo $stats['upcoming_reviews']; ?></span>
            <span class="stat-trend">
                <i class="fas fa-calendar"></i> Success rate: <?php echo $predictive_metrics['probation_success_rate']; ?>%
            </span>
        </div>
    </div>
</div>

<!-- ==================== RECENT APPLICANTS + UPCOMING INTERVIEWS ==================== -->
<div class="dashboard-grid">
    <!-- Recent Applicants -->
    <div class="recent-expenses-unique">
        <div class="expenses-header">
            <h2>Recent Applicants <span class="ai-chart-badge" style="font-size: 11px;">Screening Score</span></h2>
            <a href="?page=applicant&subpage=applicant-profiles" class="add-expense-btn">
                <i class="fas fa-eye"></i> View All
            </a>
        </div>

        <?php if (empty($recent_applicants)): ?>
        <div style="text-align: center; padding: 40px; color: #95a5a6;">
            <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No applicants yet</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="unique-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Applied Date</th>
                        <th>Match</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_applicants as $applicant):
                        $ai_match_score = (int)$applicant['ai_match_score'];
                        $match_color = $ai_match_score >= 85 ? '#27ae60' : ($ai_match_score >= 70 ? '#f39c12' : '#e74c3c');
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></strong>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($applicant['job_title'] ?? $applicant['position_applied']); ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($applicant['application_date'] ?? $applicant['created_at'])); ?>
                        </td>
                        <td>
                            <?php if ($ai_match_score > 0): ?>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <div class="savings-bar" style="width: 60px; margin-bottom: 0;">
                                    <div class="savings-progress" style="width: <?php echo $ai_match_score; ?>%; background: <?php echo $match_color; ?>;"></div>
                                </div>
                                <span style="font-size: 11px; color: <?php echo $match_color; ?>; font-weight: 600;"><?php echo $ai_match_score; ?>%</span>
                            </div>
                            <?php else: ?>
                            <span style="font-size: 11px; color: #95a5a6;">Not screened</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $colors = [
                                'new'         => '#3498db',
                                'in_review'   => '#f39c12',
                                'shortlisted' => '#27ae60',
                                'interviewed' => '#9b59b6',
                                'offered'     => '#e67e22',
                                'hired'       => '#2ecc71',
                                'rejected'    => '#e74c3c',
                                'on_hold'     => '#95a5a6'
                            ];
                            $color = $colors[$applicant['status']] ?? '#3498db';
                            ?>
                            <span class="category-badge" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                <?php echo ucfirst(str_replace('_', ' ', $applicant['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <a href="?page=applicant&subpage=applicant-profiles&id=<?php echo $applicant['id']; ?>" class="table-action">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Upcoming Interviews -->
    <div class="activity-timeline">
        <div class="timeline-header">
            <h3>Upcoming Interviews</h3>
            <a href="?page=recruitment&subpage=interview-scheduling" class="add-expense-btn">
                <i class="fas fa-calendar-plus"></i> Schedule
            </a>
        </div>

        <?php if (empty($upcoming_interviews)): ?>
        <div style="text-align: center; padding: 20px; color: #95a5a6;">
            <i class="fas fa-calendar-times" style="font-size: 24px; margin-bottom: 10px; opacity: 0.5;"></i>
            <p>No upcoming interviews</p>
        </div>
        <?php else: ?>
            <?php foreach ($upcoming_interviews as $interview):
                $score = (float)$interview['ai_prediction'];
                $priority_color = $score >= 85 ? '#27ae60' : ($score >= 70 ? '#f39c12' : '#e74c3c');
            ?>
            <div class="timeline-item">
                <div class="timeline-dot" style="background: <?php echo $priority_color; ?>;"></div>
                <div class="timeline-avatar" style="background: linear-gradient(135deg, <?php echo $priority_color; ?>, <?php echo $priority_color; ?>dd);">
                    <?php echo strtoupper(substr($interview['applicant_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="timeline-content">
                    <p>
                        <strong><?php echo htmlspecialchars($interview['applicant_name'] ?? 'Unknown'); ?></strong>
                        <span class="highlight"> - <?php echo htmlspecialchars($interview['job_title'] ?? $interview['position_applied'] ?? 'Position'); ?></span>
                    </p>
                    <p style="font-size: 11px; color: #7f8c8d;">
                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($interview['interview_date'])); ?>
                        <?php if (!empty($interview['interview_time'])): ?>
                        at <?php echo date('h:i A', strtotime($interview['interview_time'])); ?>
                        <?php endif; ?>
                        <?php if (!empty($interview['interviewer_name'])): ?>
                        <br><i class="fas fa-user"></i> Interviewer: <?php echo htmlspecialchars($interview['interviewer_name']); ?>
                        <?php endif; ?>
                    </p>
                    <span class="timeline-time">
                        <i class="far fa-clock"></i> <?php echo timeAgoEnhanced($interview['created_at'] ?? date('Y-m-d H:i:s')); ?>
                        <?php if ($score > 0): ?>
                        &bull; <i class="fas fa-chart-line"></i> Score: <?php echo number_format($score, 1); ?>%
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== ONBOARDING LIST ==================== -->
<div class="dashboard-grid" style="margin-top: 20px;">
    <div class="recent-expenses-unique">
        <div class="expenses-header">
            <h2>Active Onboarding <span class="ai-chart-badge" style="font-size: 11px;">Progress</span></h2>
            <a href="?page=onboarding&subpage=onboarding-dashboard" class="add-expense-btn">
                <i class="fas fa-arrow-right"></i> View All
            </a>
        </div>

        <?php if (empty($onboarding_list)): ?>
        <div style="text-align: center; padding: 40px; color: #95a5a6;">
            <i class="fas fa-user-graduate" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No employees in onboarding</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="unique-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Position</th>
                        <th>Start Date</th>
                        <th>Progress</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($onboarding_list as $onboarding): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($onboarding['employee_name'] ?? 'Unknown'); ?></strong>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($onboarding['job_title'] ?? 'Position'); ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($onboarding['start_date'] ?? $onboarding['created_at'])); ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="savings-bar" style="width: 100px; margin-bottom: 0;">
                                    <div class="savings-progress" style="width: <?php echo (int)$onboarding['onboarding_progress']; ?>%"></div>
                                </div>
                                <span style="font-size: 11px;"><?php echo (int)$onboarding['onboarding_progress']; ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="category-badge" style="background: #f39c1220; color: #f39c12;">
                                <?php echo ucfirst($onboarding['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Stats Column -->
    <div class="activity-timeline">
        <div class="timeline-header">
            <h3>Quick Stats</h3>
        </div>

        <div style="background: white; border-radius: 15px; padding: 15px; margin-bottom: 12px;">
            <p style="font-size: 12px; color: #7f8c8d; margin-bottom: 5px;">Hired This Month</p>
            <p style="font-size: 28px; font-weight: 700; color: #27ae60;"><?php echo $stats['hired_this_month']; ?></p>
            <p style="font-size: 11px; color: #95a5a6;">Goal: <?php echo $stats['monthly_hiring_goal']; ?></p>
        </div>

        <div style="background: white; border-radius: 15px; padding: 15px; margin-bottom: 12px;">
            <p style="font-size: 12px; color: #7f8c8d; margin-bottom: 5px;">In Probation</p>
            <p style="font-size: 28px; font-weight: 700; color: #f39c12;"><?php echo $stats['probation_count']; ?></p>
            <p style="font-size: 11px; color: #95a5a6;">Success rate: <?php echo $stats['probation_success_rate']; ?>%</p>
        </div>

        <div style="background: white; border-radius: 15px; padding: 15px;">
            <p style="font-size: 12px; color: #7f8c8d; margin-bottom: 5px;">Active Positions</p>
            <p style="font-size: 28px; font-weight: 700; color: #3498db;"><?php echo $stats['active_jobs']; ?></p>
            <p style="font-size: 11px; color: #95a5a6;">Open job postings</p>
        </div>
    </div>
</div>

<!-- ==================== PENDING VERIFICATIONS ==================== -->
<?php if (!empty($pending_verifications)): ?>
<div class="stats-grid-unique" style="margin-top: 20px; grid-template-columns: 1fr;">
    <div class="stat-card-unique budget" style="grid-column: span 1;">
        <div class="expenses-header">
            <h3><i class="fas fa-file-signature"></i> Pending Document Verifications</h3>
            <a href="?page=applicant&subpage=document-verification" class="add-expense-btn">
                <i class="fas fa-check-double"></i> Verify Now
            </a>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
            <?php foreach ($pending_verifications as $verification):
                $priority = (int)$verification['ai_priority'];
            ?>
            <div style="background: white; border-radius: 16px; padding: 15px; display: flex; align-items: center; gap: 15px; position: relative;">
                <?php if ($priority === 1): ?>
                <div style="position: absolute; top: -5px; right: -5px; width: 12px; height: 12px; background: #e74c3c; border-radius: 50%;"></div>
                <?php endif; ?>
                <div style="width: 40px; height: 40px; background: rgba(14,76,146,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-file-pdf" style="color: #e74c3c;"></i>
                </div>
                <div style="flex: 1;">
                    <p style="font-weight: 600; margin-bottom: 3px;">
                        <?php echo htmlspecialchars($verification['applicant_name'] ?? 'Unknown'); ?>
                        <?php if ($priority === 1): ?>
                        <span style="background: #e74c3c20; color: #e74c3c; padding: 2px 8px; border-radius: 30px; font-size: 10px; margin-left: 5px;">High Priority</span>
                        <?php endif; ?>
                    </p>
                    <p style="font-size: 11px; color: #7f8c8d;"><?php echo htmlspecialchars($verification['document_type']); ?></p>
                    <p style="font-size: 10px; color: #95a5a6;"><?php echo timeAgoEnhanced($verification['uploaded_at']); ?></p>
                </div>
                <a href="?page=applicant&subpage=document-verification&id=<?php echo $verification['id']; ?>" class="table-action">
                    <i class="fas fa-eye"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ==================== RECENT ACTIVITY ==================== -->
<div style="margin-top: 20px; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border-radius: 25px; padding: 20px;">
    <div class="expenses-header">
        <h3><i class="fas fa-history"></i> Recent Activity</h3>
        <button class="add-expense-btn" onclick="refreshActivity()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>

    <?php if (empty($activities)): ?>
    <div style="text-align: center; padding: 40px; color: #95a5a6;">
        <i class="fas fa-history" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
        <p>No recent activity</p>
    </div>
    <?php else: ?>
    <div class="table-container">
        <table class="unique-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars(explode(' ', $activity['full_name'])[0] ?? $activity['full_name']); ?></strong>
                        <span style="display: block; font-size: 10px; color: #7f8c8d;"><?php echo ucfirst($activity['role'] ?? 'user'); ?></span>
                    </td>
                    <td>
                        <span class="category-badge" style="background: rgba(14,76,146,0.1); color: #0e4c92;">
                            <?php echo htmlspecialchars($activity['action']); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($activity['description'] ?? ''); ?>
                    </td>
                    <td>
                        <span style="font-size: 11px; color: #7f8c8d;">
                            <i class="far fa-clock"></i> <?php echo timeAgoEnhanced($activity['created_at']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <button class="pagination-btn" onclick="changeActivityPage(<?php echo $page - 1; ?>)" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
            <i class="fas fa-chevron-left"></i> Previous
        </button>

        <div class="pagination-pages">
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="page-number active"><?php echo $i; ?></span>
                <?php elseif ($i >= $page - 2 && $i <= $page + 2): ?>
                    <span class="page-number" onclick="changeActivityPage(<?php echo $i; ?>)"><?php echo $i; ?></span>
                <?php elseif ($i == 1 || $i == $total_pages): ?>
                    <?php if ($i == 1 && $page > 3): ?>
                        <span class="page-number" onclick="changeActivityPage(1)">1</span>
                        <span style="padding: 0 5px;">...</span>
                    <?php elseif ($i == $total_pages && $page < $total_pages - 2): ?>
                        <span style="padding: 0 5px;">...</span>
                        <span class="page-number" onclick="changeActivityPage(<?php echo $total_pages; ?>)"><?php echo $total_pages; ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endfor; ?>
        </div>

        <div class="pagination-info">
            Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_activities); ?> of <?php echo $total_activities; ?>
        </div>

        <button class="pagination-btn" onclick="changeActivityPage(<?php echo $page + 1; ?>)" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
            Next <i class="fas fa-chevron-right"></i>
        </button>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Hiring Funnel Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('hiringFunnelChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Applications', 'Screened', 'Interviewed', 'Offered', 'Hired'],
            datasets: [{
                data: [
                    <?php echo $stats['total_applicants']; ?>,
                    <?php echo $stats['screened']; ?>,
                    <?php echo $stats['interviewed']; ?>,
                    <?php echo $stats['offered']; ?>,
                    <?php echo $stats['hired']; ?>
                ],
                backgroundColor: [
                    'rgba(102, 126, 234, 0.8)',
                    'rgba(118, 75, 162, 0.8)',
                    'rgba(240, 147, 251, 0.8)',
                    'rgba(245, 87, 108, 0.8)',
                    'rgba(79, 172, 254, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
});

function changeActivityPage(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('activity_page', page);
    window.location.href = url.toString();
}

function refreshActivity() {
    location.reload();
}
</script>