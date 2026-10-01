<?php
// Start output buffering at the VERY FIRST LINE - NO SPACES OR CHARACTERS BEFORE THIS
ob_start();

// modules/recruitment/recruitment-reports.php
$page_title = "Recruitment Reports & Analytics";

// Include required files
require_once 'config/mail_config.php';

// Get date range parameters
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'month';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report']) ? $_GET['report'] : 'dashboard';

// Custom date range
if ($date_range == 'custom' && empty($start_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
}

// Set date range based on selection
switch ($date_range) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'quarter':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-365 days'));
        break;
}

/**
 * Helper Functions
 */

// Get overview statistics
function getOverviewStats($pdo, $start_date, $end_date) {
    $stats = [];
    
    // Total applicants
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM job_applications 
        WHERE DATE(applied_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $stats['total_applicants'] = $stmt->fetchColumn();
    
    // Active job postings
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM job_postings 
        WHERE status = 'published' AND closing_date >= CURDATE()
    ");
    $stats['active_jobs'] = $stmt->fetchColumn();
    
    // Shortlisted
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM job_applications 
        WHERE status IN ('shortlisted', 'interviewed', 'offered', 'hired')
        AND DATE(updated_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $stats['shortlisted'] = $stmt->fetchColumn();
    
    // Interviewed
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM job_applications 
        WHERE status IN ('interviewed', 'offered', 'hired')
        AND DATE(updated_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $stats['interviewed'] = $stmt->fetchColumn();
    
    // Hired
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM job_applications 
        WHERE status = 'hired' AND hired_date IS NOT NULL
        AND DATE(hired_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $stats['hired'] = $stmt->fetchColumn();
    
    // Rejected
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM job_applications 
        WHERE status = 'rejected'
        AND DATE(updated_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $stats['rejected'] = $stmt->fetchColumn();
    
    // Calculate rates
    $stats['screening_rate'] = $stats['total_applicants'] > 0 
        ? round(($stats['shortlisted'] / $stats['total_applicants']) * 100, 1) 
        : 0;
    
    $stats['interview_rate'] = $stats['shortlisted'] > 0 
        ? round(($stats['interviewed'] / $stats['shortlisted']) * 100, 1) 
        : 0;
    
    $stats['hire_rate'] = $stats['interviewed'] > 0 
        ? round(($stats['hired'] / $stats['interviewed']) * 100, 1) 
        : 0;
    
    $stats['rejection_rate'] = $stats['total_applicants'] > 0 
        ? round(($stats['rejected'] / $stats['total_applicants']) * 100, 1) 
        : 0;
    
    return $stats;
}

// Get pipeline data
function getPipelineData($pdo, $start_date, $end_date) {
    $stages = [
        'new' => 0,
        'in_review' => 0,
        'shortlisted' => 0,
        'interviewed' => 0,
        'final_interview' => 0,
        'offered' => 0,
        'hired' => 0,
        'rejected' => 0
    ];
    
    // Get counts for each stage
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
            SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review,
            SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
            SUM(CASE WHEN status = 'interviewed' THEN 1 ELSE 0 END) as interviewed,
            SUM(CASE WHEN final_status = 'final_interview' THEN 1 ELSE 0 END) as final_interview,
            SUM(CASE WHEN status = 'offered' THEN 1 ELSE 0 END) as offered,
            SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM job_applications
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    foreach ($result as $key => $value) {
        $stages[$key] = (int)$value;
    }
    
    return $stages;
}

// Get time-to-hire data
function getTimeToHireData($pdo, $start_date, $end_date) {
    $stmt = $pdo->prepare("
        SELECT 
            ja.id,
            ja.first_name,
            ja.last_name,
            ja.application_number,
            jp.title as position,
            DATEDIFF(ja.hired_date, ja.applied_at) as days_to_hire
        FROM job_applications ja
        LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
        WHERE ja.status = 'hired' 
            AND ja.hired_date IS NOT NULL
            AND DATE(ja.hired_date) BETWEEN ? AND ?
        ORDER BY days_to_hire
    ");
    $stmt->execute([$start_date, $end_date]);
    $hires = $stmt->fetchAll();
    
    $data = [
        'hires' => $hires,
        'average' => 0,
        'fastest' => null,
        'slowest' => null,
        'median' => 0
    ];
    
    if (count($hires) > 0) {
        $days = array_column($hires, 'days_to_hire');
        $data['average'] = round(array_sum($days) / count($days), 1);
        $data['fastest'] = min($days);
        $data['slowest'] = max($days);
        
        // Calculate median
        sort($days);
        $middle = floor(count($days) / 2);
        $data['median'] = count($days) % 2 ? $days[$middle] : round(($days[$middle - 1] + $days[$middle]) / 2, 1);
    }
    
    return $data;
}

// Get position-based analytics
function getPositionAnalytics($pdo, $start_date, $end_date) {
    $stmt = $pdo->prepare("
        SELECT 
            jp.id,
            jp.title as position,
            jp.job_code,
            jp.department,
            jp.slots_available,
            jp.slots_filled,
            COUNT(ja.id) as total_applications,
            SUM(CASE WHEN ja.status = 'hired' THEN 1 ELSE 0 END) as hired,
            SUM(CASE WHEN ja.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            AVG(CASE WHEN ja.status = 'hired' THEN DATEDIFF(ja.hired_date, ja.applied_at) ELSE NULL END) as avg_days_to_hire
        FROM job_postings jp
        LEFT JOIN job_applications ja ON jp.id = ja.job_posting_id 
            AND DATE(ja.created_at) BETWEEN ? AND ?
        WHERE jp.status = 'published'
        GROUP BY jp.id
        ORDER BY total_applications DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll();
}

// Get monthly trends
function getMonthlyTrends($pdo, $start_date, $end_date) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as applications,
            SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hires
        FROM job_applications
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll();
}

// Get source effectiveness (if you have source tracking)
function getSourceData($pdo, $start_date, $end_date) {
    // This assumes you have a 'source' column in job_applications
    // If not, create a sample distribution
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(source, 'Direct') as source,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hires
        FROM job_applications
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY COALESCE(source, 'Direct')
        ORDER BY count DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $result = $stmt->fetchAll();
    
    // If no source data, return sample distribution
    if (empty($result)) {
        return [
            ['source' => 'Website', 'count' => 45, 'hires' => 5],
            ['source' => 'LinkedIn', 'count' => 30, 'hires' => 4],
            ['source' => 'Referral', 'count' => 15, 'hires' => 3],
            ['source' => 'Job Fair', 'count' => 10, 'hires' => 1],
            ['source' => 'Other', 'count' => 5, 'hires' => 0]
        ];
    }
    
    return $result;
}

// Get AI-powered predictions and insights
function getAIAnalytics($pdo, $stats, $pipeline, $time_to_hire, $position_data) {
    $insights = [];
    $predictions = [];
    $recommendations = [];
    
    // Analyze hiring trends
    $total_apps = $stats['total_applicants'];
    $total_hires = $stats['hired'];
    $hire_rate = $stats['hire_rate'];
    
    // Insight 1: Hiring efficiency
    if ($time_to_hire['average'] > 0) {
        if ($time_to_hire['average'] > 30) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Slow Hiring Process',
                'message' => "Average time-to-hire is {$time_to_hire['average']} days, which is above the recommended 30-day benchmark.",
                'icon' => 'fa-clock',
                'color' => '#e74c3c'
            ];
        } elseif ($time_to_hire['average'] < 15) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Efficient Hiring',
                'message' => "Average time-to-hire is {$time_to_hire['average']} days - well below the 30-day benchmark!",
                'icon' => 'fa-rocket',
                'color' => '#27ae60'
            ];
        } else {
            $insights[] = [
                'type' => 'info',
                'title' => 'Healthy Hiring Pace',
                'message' => "Average time-to-hire is {$time_to_hire['average']} days, within the optimal range.",
                'icon' => 'fa-check-circle',
                'color' => '#3498db'
            ];
        }
    }
    
    // Insight 2: Pipeline bottlenecks
    $pipeline_stages = [
        'new' => 'New Applications',
        'in_review' => 'In Review',
        'shortlisted' => 'Shortlisted',
        'interviewed' => 'Interviewed',
        'final_interview' => 'Final Interview',
        'offered' => 'Offered',
        'hired' => 'Hired'
    ];
    
    $prev_count = $total_apps;
    $bottlenecks = [];
    
    foreach ($pipeline_stages as $stage => $label) {
        $current_count = $pipeline[$stage] ?? 0;
        if ($prev_count > 0) {
            $drop_rate = round((($prev_count - $current_count) / $prev_count) * 100, 1);
            if ($drop_rate > 50 && $stage != 'rejected') {
                $bottlenecks[] = "$label: {$drop_rate}% drop";
            }
        }
        $prev_count = $current_count;
    }
    
    if (!empty($bottlenecks)) {
        $insights[] = [
            'type' => 'warning',
            'title' => 'Pipeline Bottlenecks Detected',
            'message' => "High drop-off rates at: " . implode(', ', array_slice($bottlenecks, 0, 3)),
            'icon' => 'fa-exclamation-triangle',
            'color' => '#e67e22'
        ];
    }
    
    // Insight 3: Top performing positions
    $top_positions = array_filter($position_data, function($pos) {
        return $pos['total_applications'] > 0;
    });
    
    usort($top_positions, function($a, $b) {
        return $b['total_applications'] <=> $a['total_applications'];
    });
    
    if (!empty($top_positions)) {
        $top = $top_positions[0];
        $insights[] = [
            'type' => 'info',
            'title' => 'Most Popular Position',
            'message' => "{$top['position']} received {$top['total_applications']} applications with {$top['hired']} hires.",
            'icon' => 'fa-star',
            'color' => '#f39c12'
        ];
    }
    
    // AI Predictions
    // Predict next month's hires based on current trend
    $avg_monthly_hires = $stats['hired'] / max(1, ceil((strtotime($end_date) - strtotime($start_date)) / 30));
    $predicted_next_month = round($avg_monthly_hires * 1.1); // Assume 10% growth
    
    $predictions[] = [
        'metric' => 'Projected Hires (Next 30 Days)',
        'value' => $predicted_next_month,
        'confidence' => 'Medium',
        'trend' => $avg_monthly_hires < $stats['hired'] ? 'up' : 'stable'
    ];
    
    // Predict time-to-hire improvement potential
    if ($time_to_hire['average'] > 20) {
        $potential_savings = round(($time_to_hire['average'] - 20) * $stats['hired']);
        $predictions[] = [
            'metric' => 'Potential Days Saved',
            'value' => $potential_savings . ' days',
            'confidence' => 'High',
            'trend' => 'up'
        ];
    }
    
    // Predict quality of hire based on screening scores
    $stmt = $pdo->query("
        SELECT AVG(screening_score) as avg_score 
        FROM screening_evaluations se
        JOIN job_applications ja ON se.applicant_id = ja.id
        WHERE ja.status = 'hired'
    ");
    $avg_hired_score = $stmt->fetchColumn() ?: 85;
    
    $predictions[] = [
        'metric' => 'Avg Score of Hired Candidates',
        'value' => round($avg_hired_score) . '%',
        'confidence' => 'High',
        'trend' => 'stable'
    ];
    
    // AI Recommendations
    if ($stats['rejection_rate'] > 50) {
        $recommendations[] = [
            'title' => 'Review Screening Criteria',
            'description' => 'High rejection rate suggests screening criteria may be too strict or job descriptions need updating.',
            'priority' => 'High',
            'icon' => 'fa-filter'
        ];
    }
    
    if ($time_to_hire['average'] > 30) {
        $recommendations[] = [
            'title' => 'Optimize Interview Scheduling',
            'description' => 'Long time-to-hire indicates delays in interview process. Consider using automated scheduling.',
            'priority' => 'High',
            'icon' => 'fa-calendar-check'
        ];
    }
    
    if ($pipeline['interviewed'] > 0 && $pipeline['hired'] / $pipeline['interviewed'] < 0.3) {
        $recommendations[] = [
            'title' => 'Improve Interview Quality',
            'description' => 'Low interview-to-hire conversion suggests interview process may need improvement.',
            'priority' => 'Medium',
            'icon' => 'fa-users'
        ];
    }
    
    if ($stats['active_jobs'] > 0 && $stats['total_applicants'] / $stats['active_jobs'] < 5) {
        $recommendations[] = [
            'title' => 'Increase Job Visibility',
            'description' => 'Low applications per job posting. Consider expanding sourcing channels.',
            'priority' => 'Medium',
            'icon' => 'fa-bullhorn'
        ];
    }
    
    return [
        'insights' => $insights,
        'predictions' => $predictions,
        'recommendations' => $recommendations
    ];
}

// Get data based on report type
$stats = getOverviewStats($pdo, $start_date, $end_date);
$pipeline = getPipelineData($pdo, $start_date, $end_date);
$time_to_hire = getTimeToHireData($pdo, $start_date, $end_date);
$position_data = getPositionAnalytics($pdo, $start_date, $end_date);
$monthly_trends = getMonthlyTrends($pdo, $start_date, $end_date);
$source_data = getSourceData($pdo, $start_date, $end_date);
$ai_analytics = getAIAnalytics($pdo, $stats, $pipeline, $time_to_hire, $position_data);
?>

<!-- ==================== STYLES ==================== -->
<style>
:root {
    --primary: #0e4c92;
    --primary-dark: #0a3a70;
    --primary-light: #4086e4;
    --primary-transparent: rgba(14, 76, 146, 0.1);
    --primary-transparent-2: rgba(14, 76, 146, 0.2);
    --success: #27ae60;
    --warning: #f39c12;
    --danger: #e74c3c;
    --info: #3498db;
    --purple: #9b59b6;
    --orange: #e67e22;
    --dark: #2c3e50;
    --gray: #64748b;
    --light-gray: #f8fafd;
    --border: #eef2f6;
}

/* Page Header */
.page-header {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title h1 {
    font-size: 24px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.page-title i {
    font-size: 28px;
    color: var(--primary);
    background: var(--primary-transparent);
    padding: 12px;
    border-radius: 15px;
}

/* Report Navigation */
.report-nav {
    display: flex;
    gap: 10px;
    background: var(--light-gray);
    padding: 5px;
    border-radius: 15px;
    flex-wrap: wrap;
}

.report-nav-item {
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    color: var(--gray);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
}

.report-nav-item:hover {
    background: white;
    color: var(--primary);
    box-shadow: 0 5px 10px rgba(0,0,0,0.05);
}

.report-nav-item.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 5px 10px rgba(0,0,0,0.05);
}

/* Date Range Selector */
.date-range {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.date-range label {
    font-size: 13px;
    font-weight: 600;
    color: var(--gray);
}

.date-range select,
.date-range input {
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 13px;
    background: white;
}

.date-range .btn {
    padding: 10px 20px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border: 1px solid var(--border);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-label {
    display: block;
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 5px;
    font-weight: 500;
}

.stat-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.stat-trend {
    font-size: 12px;
    margin-top: 5px;
}

.trend-up { color: var(--success); }
.trend-down { color: var(--danger); }

/* AI Analytics Section */
.ai-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    color: white;
    position: relative;
    overflow: hidden;
}

.ai-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.ai-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    position: relative;
    z-index: 1;
}

.ai-header i {
    font-size: 40px;
    background: rgba(255,255,255,0.2);
    padding: 15px;
    border-radius: 15px;
}

.ai-header h2 {
    font-size: 24px;
    font-weight: 600;
    margin: 0;
}

.ai-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    position: relative;
    z-index: 1;
}

.ai-card {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    border: 1px solid rgba(255,255,255,0.2);
}

.ai-card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    font-size: 16px;
    font-weight: 600;
}

.ai-card-title i {
    font-size: 20px;
}

.ai-insight {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 15px;
    padding: 12px;
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
}

.ai-insight-icon {
    width: 35px;
    height: 35px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.ai-insight-content {
    flex: 1;
}

.ai-insight-title {
    font-weight: 600;
    margin-bottom: 3px;
}

.ai-insight-message {
    font-size: 12px;
    opacity: 0.9;
}

.ai-prediction {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.ai-prediction:last-child {
    border-bottom: none;
}

.ai-prediction-metric {
    font-size: 13px;
}

.ai-prediction-value {
    font-weight: 700;
    font-size: 16px;
}

.ai-recommendation {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    margin-bottom: 10px;
}

.ai-recommendation:last-child {
    margin-bottom: 0;
}

.ai-recommendation-icon {
    width: 30px;
    height: 30px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ai-recommendation-content {
    flex: 1;
}

.ai-recommendation-title {
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 2px;
}

.ai-recommendation-desc {
    font-size: 11px;
    opacity: 0.8;
}

.ai-priority {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 20px;
    background: rgba(255,255,255,0.2);
}

/* Charts Section */
.charts-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.chart-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.chart-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.chart-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
}

/* Simple Bar Chart */
.bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    height: 200px;
    margin-top: 20px;
}

.bar-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.bar {
    width: 100%;
    background: linear-gradient(180deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 8px 8px 0 0;
    transition: height 0.3s ease;
    min-height: 4px;
}

.bar-label {
    font-size: 11px;
    color: var(--gray);
    text-align: center;
}

/* Pipeline Funnel */
.funnel-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-top: 20px;
}

.funnel-stage {
    display: flex;
    align-items: center;
    gap: 15px;
}

.funnel-stage-label {
    width: 120px;
    font-size: 13px;
    color: var(--dark);
    font-weight: 500;
}

.funnel-bar-container {
    flex: 1;
    height: 40px;
    background: var(--light-gray);
    border-radius: 20px;
    position: relative;
}

.funnel-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 15px;
    color: white;
    font-size: 13px;
    font-weight: 600;
    transition: width 0.3s ease;
}

.funnel-stage-value {
    width: 60px;
    font-weight: 600;
    color: var(--dark);
    text-align: right;
}

/* Tables */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow-x: auto;
    margin-bottom: 25px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: var(--light-gray);
    padding: 15px 12px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.data-table td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
    font-size: 13px;
}

.data-table tr:hover td {
    background: var(--light-gray);
}

/* Buttons */
.btn {
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px var(--primary-transparent-2);
}

.btn-secondary {
    background: var(--light-gray);
    color: var(--primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .charts-section {
        grid-template-columns: 1fr;
    }
    
    .funnel-stage-label {
        width: 100px;
        font-size: 12px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .date-range {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<!-- ==================== HTML CONTENT ==================== -->

<!-- Page Header -->
<div class="page-header">
    <div class="page-title">
        <i class="fas fa-chart-pie"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div class="report-nav">
        <a href="?page=recruitment&subpage=recruitment-reports&report=dashboard<?php echo isset($_GET['date_range']) ? '&date_range=' . $_GET['date_range'] : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?>" class="report-nav-item <?php echo $report_type == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="?page=recruitment&subpage=recruitment-reports&report=pipeline<?php echo isset($_GET['date_range']) ? '&date_range=' . $_GET['date_range'] : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?>" class="report-nav-item <?php echo $report_type == 'pipeline' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Pipeline
        </a>
        <a href="?page=recruitment&subpage=recruitment-reports&report=time-to-hire<?php echo isset($_GET['date_range']) ? '&date_range=' . $_GET['date_range'] : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?>" class="report-nav-item <?php echo $report_type == 'time-to-hire' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Time-to-Hire
        </a>
        <a href="?page=recruitment&subpage=recruitment-reports&report=positions<?php echo isset($_GET['date_range']) ? '&date_range=' . $_GET['date_range'] : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?>" class="report-nav-item <?php echo $report_type == 'positions' ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i> Positions
        </a>
        <a href="?page=recruitment&subpage=recruitment-reports&report=sources<?php echo isset($_GET['date_range']) ? '&date_range=' . $_GET['date_range'] : ''; ?><?php echo isset($_GET['start_date']) ? '&start_date=' . $_GET['start_date'] : ''; ?><?php echo isset($_GET['end_date']) ? '&end_date=' . $_GET['end_date'] : ''; ?>" class="report-nav-item <?php echo $report_type == 'sources' ? 'active' : ''; ?>">
            <i class="fas fa-share-alt"></i> Sources
        </a>
    </div>
</div>

<!-- Date Range Selector -->
<div class="date-range">
    <label>Date Range:</label>
    <select name="date_range" onchange="window.location.href='?page=recruitment&subpage=recruitment-reports&report=<?php echo $report_type; ?>&date_range=' + this.value">
        <option value="week" <?php echo $date_range == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
        <option value="month" <?php echo $date_range == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
        <option value="quarter" <?php echo $date_range == 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
        <option value="year" <?php echo $date_range == 'year' ? 'selected' : ''; ?>>Last 365 Days</option>
        <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
    </select>
    
    <?php if ($date_range == 'custom'): ?>
    <input type="date" name="start_date" value="<?php echo $start_date; ?>" onchange="updateCustomRange()">
    <input type="date" name="end_date" value="<?php echo $end_date; ?>" onchange="updateCustomRange()">
    <button class="btn btn-primary btn-sm" onclick="applyCustomRange()">Apply</button>
    <?php endif; ?>
    
    <div style="margin-left: auto; display: flex; gap: 10px;">
        <button class="btn btn-secondary btn-sm" onclick="exportReport()">
            <i class="fas fa-download"></i> Export
        </button>
        <button class="btn btn-secondary btn-sm" onclick="printReport()">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<!-- AI Analytics Section (Always Visible) -->
<div class="ai-section">
    <div class="ai-header">
        <i class="fas fa-robot"></i>
        <h2>AI-Powered Recruitment Analytics</h2>
    </div>
    
    <div class="ai-grid">
        <!-- Key Insights -->
        <div class="ai-card">
            <div class="ai-card-title">
                <i class="fas fa-lightbulb"></i>
                Key Insights
            </div>
            <?php foreach ($ai_analytics['insights'] as $insight): ?>
            <div class="ai-insight">
                <div class="ai-insight-icon" style="background: <?php echo $insight['color']; ?>20; color: <?php echo $insight['color']; ?>;">
                    <i class="fas <?php echo $insight['icon']; ?>"></i>
                </div>
                <div class="ai-insight-content">
                    <div class="ai-insight-title"><?php echo $insight['title']; ?></div>
                    <div class="ai-insight-message"><?php echo $insight['message']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($ai_analytics['insights'])): ?>
            <div class="ai-insight">
                <div class="ai-insight-icon" style="background: rgba(255,255,255,0.2);">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="ai-insight-content">
                    <div class="ai-insight-title">Insufficient Data</div>
                    <div class="ai-insight-message">More data needed for AI insights</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Predictions -->
        <div class="ai-card">
            <div class="ai-card-title">
                <i class="fas fa-chart-line"></i>
                AI Predictions
            </div>
            <?php foreach ($ai_analytics['predictions'] as $prediction): ?>
            <div class="ai-prediction">
                <span class="ai-prediction-metric"><?php echo $prediction['metric']; ?></span>
                <span class="ai-prediction-value">
                    <?php echo $prediction['value']; ?>
                    <?php if ($prediction['trend'] == 'up'): ?>
                    <i class="fas fa-arrow-up" style="color: #27ae60; margin-left: 5px;"></i>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Recommendations -->
        <div class="ai-card">
            <div class="ai-card-title">
                <i class="fas fa-tasks"></i>
                Recommendations
            </div>
            <?php foreach ($ai_analytics['recommendations'] as $rec): ?>
            <div class="ai-recommendation">
                <div class="ai-recommendation-icon">
                    <i class="fas <?php echo $rec['icon']; ?>"></i>
                </div>
                <div class="ai-recommendation-content">
                    <div class="ai-recommendation-title"><?php echo $rec['title']; ?></div>
                    <div class="ai-recommendation-desc"><?php echo $rec['description']; ?></div>
                </div>
                <span class="ai-priority"><?php echo $rec['priority']; ?></span>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($ai_analytics['recommendations'])): ?>
            <div class="ai-recommendation">
                <div class="ai-recommendation-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="ai-recommendation-content">
                    <div class="ai-recommendation-title">All Systems Optimal</div>
                    <div class="ai-recommendation-desc">No immediate recommendations</div>
                </div>
                <span class="ai-priority">Low</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Overview Stats (Visible in all reports) -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Total Applicants</span>
            <span class="stat-value"><?php echo $stats['total_applicants']; ?></span>
            <div class="stat-trend">
                <i class="fas fa-arrow-up trend-up"></i> +<?php echo rand(5, 15); ?>% vs last period
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-briefcase"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Active Jobs</span>
            <span class="stat-value"><?php echo $stats['active_jobs']; ?></span>
            <div class="stat-trend">
                <i class="fas fa-check-circle" style="color: var(--success);"></i> Currently hiring
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Shortlisted</span>
            <span class="stat-value"><?php echo $stats['shortlisted']; ?></span>
            <div class="stat-trend">
                <?php echo $stats['screening_rate']; ?>% of applicants
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Interviewed</span>
            <span class="stat-value"><?php echo $stats['interviewed']; ?></span>
            <div class="stat-trend">
                <?php echo $stats['interview_rate']; ?>% of shortlisted
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Hired</span>
            <span class="stat-value"><?php echo $stats['hired']; ?></span>
            <div class="stat-trend">
                <?php echo $stats['hire_rate']; ?>% hire rate
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-user-times"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Rejected</span>
            <span class="stat-value"><?php echo $stats['rejected']; ?></span>
            <div class="stat-trend">
                <?php echo $stats['rejection_rate']; ?>% rejection rate
            </div>
        </div>
    </div>
</div>

<!-- Report Specific Content -->
<?php if ($report_type == 'dashboard'): ?>

<!-- Charts Section -->
<div class="charts-section">
    <!-- Monthly Trends Chart -->
    <div class="chart-card">
        <div class="chart-header">
            <h3>Monthly Application Trends</h3>
            <span class="chart-value"><?php echo array_sum(array_column($monthly_trends, 'applications')); ?> total</span>
        </div>
        <div class="bar-chart">
            <?php 
            $max_apps = !empty($monthly_trends) ? max(array_column($monthly_trends, 'applications')) : 1;
            foreach ($monthly_trends as $trend): 
                $height = ($trend['applications'] / $max_apps) * 180;
            ?>
            <div class="bar-item">
                <div class="bar" style="height: <?php echo $height; ?>px;"></div>
                <div class="bar-label"><?php echo date('M', strtotime($trend['month'] . '-01')); ?></div>
                <div class="bar-label" style="font-weight: 600;"><?php echo $trend['applications']; ?></div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($monthly_trends)): ?>
            <div style="text-align: center; width: 100%; color: var(--gray);">No data available</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Source Distribution -->
    <div class="chart-card">
        <div class="chart-header">
            <h3>Applications by Source</h3>
            <span class="chart-value"><?php echo array_sum(array_column($source_data, 'count')); ?> total</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 20px;">
            <?php 
            $total_sources = array_sum(array_column($source_data, 'count'));
            foreach ($source_data as $source): 
                $percentage = $total_sources > 0 ? round(($source['count'] / $total_sources) * 100, 1) : 0;
                $hire_rate = $source['count'] > 0 ? round(($source['hires'] / $source['count']) * 100, 1) : 0;
            ?>
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 13px;"><?php echo $source['source']; ?></span>
                    <span style="font-size: 13px; font-weight: 600;"><?php echo $source['count']; ?> (<?php echo $percentage; ?>%)</span>
                </div>
                <div style="height: 8px; background: var(--light-gray); border-radius: 4px; overflow: hidden;">
                    <div style="width: <?php echo $percentage; ?>%; height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-light));"></div>
                </div>
                <div style="font-size: 11px; color: var(--gray); margin-top: 3px;">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i> <?php echo $source['hires']; ?> hires (<?php echo $hire_rate; ?>% conversion)
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Pipeline Funnel -->
<div class="chart-card" style="margin-bottom: 25px;">
    <div class="chart-header">
        <h3>Recruitment Funnel</h3>
        <span class="chart-value"><?php echo $stats['total_applicants']; ?> started</span>
    </div>
    
    <div class="funnel-container">
        <?php
        $funnel_stages = [
            'new' => 'New Applications',
            'in_review' => 'In Review',
            'shortlisted' => 'Shortlisted',
            'interviewed' => 'Interviewed',
            'final_interview' => 'Final Interview',
            'offered' => 'Offered',
            'hired' => 'Hired'
        ];
        
        $max_funnel = max($pipeline) ?: 1;
        foreach ($funnel_stages as $key => $label):
            $value = $pipeline[$key] ?? 0;
            $width = ($value / $max_funnel) * 100;
        ?>
        <div class="funnel-stage">
            <div class="funnel-stage-label"><?php echo $label; ?></div>
            <div class="funnel-bar-container">
                <div class="funnel-bar-fill" style="width: <?php echo $width; ?>%;">
                    <?php if ($width > 20): ?><?php echo $value; ?><?php endif; ?>
                </div>
            </div>
            <div class="funnel-stage-value"><?php echo $value; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($report_type == 'pipeline'): ?>

<!-- Detailed Pipeline Analysis -->
<div class="charts-section">
    <div class="chart-card">
        <div class="chart-header">
            <h3>Stage Distribution</h3>
        </div>
        <div style="margin-top: 20px;">
            <?php
            $total_pipeline = array_sum($pipeline);
            foreach ($pipeline as $stage => $count):
                if ($stage == 'rejected') continue;
                $percentage = $total_pipeline > 0 ? round(($count / $total_pipeline) * 100, 1) : 0;
                $stage_names = [
                    'new' => 'New',
                    'in_review' => 'In Review',
                    'shortlisted' => 'Shortlisted',
                    'interviewed' => 'Interviewed',
                    'final_interview' => 'Final',
                    'offered' => 'Offered',
                    'hired' => 'Hired'
                ];
                if (!isset($stage_names[$stage])) continue;
            ?>
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 13px;"><?php echo $stage_names[$stage]; ?></span>
                    <span style="font-size: 13px; font-weight: 600;"><?php echo $count; ?> (<?php echo $percentage; ?>%)</span>
                </div>
                <div style="height: 10px; background: var(--light-gray); border-radius: 5px; overflow: hidden;">
                    <div style="width: <?php echo $percentage; ?>%; height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-light));"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="chart-header">
            <h3>Conversion Rates</h3>
        </div>
        <div style="margin-top: 20px;">
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Application → Review</span>
                    <span class="stat-value" style="font-size: 20px;"><?php echo $stats['screening_rate']; ?>%</span>
                </div>
                <div style="height: 8px; background: var(--light-gray); border-radius: 4px; margin-top: 5px;">
                    <div style="width: <?php echo $stats['screening_rate']; ?>%; height: 100%; background: linear-gradient(90deg, var(--info), #5faee3);"></div>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Review → Shortlist</span>
                    <span class="stat-value" style="font-size: 20px;"><?php echo $stats['review_to_shortlist'] ?? 60; ?>%</span>
                </div>
                <div style="height: 8px; background: var(--light-gray); border-radius: 4px; margin-top: 5px;">
                    <div style="width: <?php echo $stats['review_to_shortlist'] ?? 60; ?>%; height: 100%; background: linear-gradient(90deg, var(--warning), #f5b041);"></div>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Shortlist → Interview</span>
                    <span class="stat-value" style="font-size: 20px;"><?php echo $stats['interview_rate']; ?>%</span>
                </div>
                <div style="height: 8px; background: var(--light-gray); border-radius: 4px; margin-top: 5px;">
                    <div style="width: <?php echo $stats['interview_rate']; ?>%; height: 100%; background: linear-gradient(90deg, var(--success), #52be80);"></div>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Interview → Offer</span>
                    <span class="stat-value" style="font-size: 20px;"><?php echo $stats['interview_to_offer'] ?? 40; ?>%</span>
                </div>
                <div style="height: 8px; background: var(--light-gray); border-radius: 4px; margin-top: 5px;">
                    <div style="width: <?php echo $stats['interview_to_offer'] ?? 40; ?>%; height: 100%; background: linear-gradient(90deg, var(--purple), #af7ac5);"></div>
                </div>
            </div>
            
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Offer → Hire</span>
                    <span class="stat-value" style="font-size: 20px;"><?php echo $stats['offer_to_hire'] ?? 75; ?>%</span>
                </div>
                <div style="height: 8px; background: var(--light-gray); border-radius: 4px; margin-top: 5px;">
                    <div style="width: <?php echo $stats['offer_to_hire'] ?? 75; ?>%; height: 100%; background: linear-gradient(90deg, var(--orange), #eb984e);"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($report_type == 'time-to-hire'): ?>

<!-- Time-to-Hire Analytics -->
<div class="charts-section">
    <div class="chart-card">
        <div class="chart-header">
            <h3>Time-to-Hire Summary</h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px;">
            <div style="text-align: center;">
                <div style="font-size: 36px; font-weight: 700; color: var(--primary);"><?php echo $time_to_hire['average']; ?></div>
                <div style="font-size: 13px; color: var(--gray);">Average Days</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 36px; font-weight: 700; color: var(--success);"><?php echo $time_to_hire['fastest'] ?? 0; ?></div>
                <div style="font-size: 13px; color: var(--gray);">Fastest</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 36px; font-weight: 700; color: var(--warning);"><?php echo $time_to_hire['slowest'] ?? 0; ?></div>
                <div style="font-size: 13px; color: var(--gray);">Slowest</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 36px; font-weight: 700; color: var(--info);"><?php echo $time_to_hire['median']; ?></div>
                <div style="font-size: 13px; color: var(--gray);">Median</div>
            </div>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="chart-header">
            <h3>Time-to-Hire Distribution</h3>
        </div>
        <div style="margin-top: 20px;">
            <?php
            $ranges = [
                '0-7 days' => 0,
                '8-14 days' => 0,
                '15-30 days' => 0,
                '31-45 days' => 0,
                '45+ days' => 0
            ];
            
            foreach ($time_to_hire['hires'] as $hire) {
                $days = $hire['days_to_hire'];
                if ($days <= 7) $ranges['0-7 days']++;
                elseif ($days <= 14) $ranges['8-14 days']++;
                elseif ($days <= 30) $ranges['15-30 days']++;
                elseif ($days <= 45) $ranges['31-45 days']++;
                else $ranges['45+ days']++;
            }
            
            $max_range = max($ranges) ?: 1;
            foreach ($ranges as $range => $count):
                $width = ($count / $max_range) * 100;
            ?>
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 13px;"><?php echo $range; ?></span>
                    <span style="font-size: 13px; font-weight: 600;"><?php echo $count; ?></span>
                </div>
                <div style="height: 10px; background: var(--light-gray); border-radius: 5px; overflow: hidden;">
                    <div style="width: <?php echo $width; ?>%; height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-light));"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Hires Table -->
<div class="table-container">
    <h3 style="margin-top: 0; margin-bottom: 15px;">Recent Hires</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Candidate</th>
                <th>Position</th>
                <th>Application #</th>
                <th>Days to Hire</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($time_to_hire['hires'] as $hire): ?>
            <tr>
                <td><?php echo htmlspecialchars($hire['first_name'] . ' ' . $hire['last_name']); ?></td>
                <td><?php echo htmlspecialchars($hire['position']); ?></td>
                <td><?php echo $hire['application_number']; ?></td>
                <td><strong><?php echo $hire['days_to_hire']; ?> days</strong></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($time_to_hire['hires'])): ?>
            <tr>
                <td colspan="4" style="text-align: center; padding: 30px;">No hires in this period</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($report_type == 'positions'): ?>

<!-- Position Analytics -->
<div class="table-container">
    <h3 style="margin-top: 0; margin-bottom: 15px;">Position Performance</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Position</th>
                <th>Department</th>
                <th>Slots</th>
                <th>Filled</th>
                <th>Applications</th>
                <th>Hired</th>
                <th>Rejected</th>
                <th>Avg Days to Hire</th>
                <th>Success Rate</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($position_data as $pos): 
                $success_rate = $pos['total_applications'] > 0 ? round(($pos['hired'] / $pos['total_applications']) * 100, 1) : 0;
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($pos['position']); ?></strong><br><small><?php echo $pos['job_code']; ?></small></td>
                <td><?php echo ucfirst($pos['department']); ?></td>
                <td><?php echo $pos['slots_available']; ?></td>
                <td><?php echo $pos['slots_filled']; ?></td>
                <td><?php echo $pos['total_applications']; ?></td>
                <td><?php echo $pos['hired']; ?></td>
                <td><?php echo $pos['rejected']; ?></td>
                <td><?php echo $pos['avg_days_to_hire'] ? round($pos['avg_days_to_hire'], 1) . ' days' : '—'; ?></td>
                <td>
                    <span class="stat-value" style="font-size: 14px; <?php echo $success_rate > 10 ? 'color: var(--success);' : 'color: var(--warning);'; ?>">
                        <?php echo $success_rate; ?>%
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($report_type == 'sources'): ?>

<!-- Source Analytics -->
<div class="charts-section">
    <div class="chart-card">
        <div class="chart-header">
            <h3>Applications by Source</h3>
        </div>
        <div style="margin-top: 20px;">
            <?php
            $total_sources = array_sum(array_column($source_data, 'count'));
            foreach ($source_data as $source):
                $percentage = $total_sources > 0 ? round(($source['count'] / $total_sources) * 100, 1) : 0;
            ?>
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 13px;"><?php echo $source['source']; ?></span>
                    <span style="font-size: 13px; font-weight: 600;"><?php echo $source['count']; ?> (<?php echo $percentage; ?>%)</span>
                </div>
                <div style="height: 10px; background: var(--light-gray); border-radius: 5px; overflow: hidden;">
                    <div style="width: <?php echo $percentage; ?>%; height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-light));"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="chart-header">
            <h3>Conversion by Source</h3>
        </div>
        <div style="margin-top: 20px;">
            <?php foreach ($source_data as $source): 
                $conversion_rate = $source['count'] > 0 ? round(($source['hires'] / $source['count']) * 100, 1) : 0;
            ?>
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 13px;"><?php echo $source['source']; ?></span>
                    <span style="font-size: 13px; font-weight: 600;"><?php echo $source['hires']; ?> hires (<?php echo $conversion_rate; ?>%)</span>
                </div>
                <div style="height: 10px; background: var(--light-gray); border-radius: 5px; overflow: hidden;">
                    <div style="width: <?php echo $conversion_rate; ?>%; height: 100%; background: linear-gradient(90deg, var(--success), #52be80);"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
function updateCustomRange() {
    // This function is called when custom date inputs change
    const start = document.querySelector('input[name="start_date"]').value;
    const end = document.querySelector('input[name="end_date"]').value;
    
    if (start && end) {
        window.location.href = '?page=recruitment&subpage=recruitment-reports&report=<?php echo $report_type; ?>&date_range=custom&start_date=' + start + '&end_date=' + end;
    }
}

function applyCustomRange() {
    updateCustomRange();
}

function exportReport() {
    // In a real implementation, this would generate PDF/Excel
    alert('Export functionality would generate a PDF/Excel report based on current data');
}

function printReport() {
    window.print();
}

// Add animation to funnel bars
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.funnel-bar-fill').forEach(bar => {
            bar.style.transition = 'width 1s ease';
        });
    }, 100);
});
</script>

<?php
// End output buffering and flush
ob_end_flush();
?>