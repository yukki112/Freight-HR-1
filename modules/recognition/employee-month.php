<?php
// modules/recognition/employee-month.php
$page_title = "Employee of the Month";

// Check if user has access
$is_admin = ($_SESSION['role'] === 'admin');
$is_manager = ($_SESSION['role'] === 'manager');
$is_supervisor = ($is_admin || $is_manager);
$is_hr = ($is_admin || $is_manager);

// Get current month/year
$current_month = $_GET['month'] ?? date('Y-m');
$view_month = $current_month . '-01';
$prev_month = date('Y-m', strtotime($view_month . ' -1 month'));
$next_month = date('Y-m', strtotime($view_month . ' +1 month'));

// Get settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM eom_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Save criteria settings (HR/Admin only)
    if (isset($_POST['save_criteria']) && $is_hr) {
        // Delete existing criteria
        $pdo->exec("DELETE FROM eom_criteria WHERE is_active = 1");
        
        // Insert new criteria
        if (isset($_POST['criteria']) && is_array($_POST['criteria'])) {
            $stmt = $pdo->prepare("
                INSERT INTO eom_criteria (criteria_name, description, weight, category, sort_order, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['criteria'] as $index => $criteria) {
                if (!empty($criteria['name'])) {
                    $stmt->execute([
                        $criteria['name'],
                        $criteria['description'] ?? '',
                        $criteria['weight'] ?? 0,
                        $criteria['category'] ?? 'all',
                        $index + 1,
                        $_SESSION['user_id']
                    ]);
                }
            }
        }
        
        // Update settings
        $settings_to_update = [
            'voting_enabled' => $_POST['voting_enabled'] ?? '0',
            'supervisor_weight' => $_POST['supervisor_weight'] ?? '50',
            'kpi_weight' => $_POST['kpi_weight'] ?? '30',
            'vote_weight' => $_POST['vote_weight'] ?? '20',
            'prevent_consecutive_wins' => $_POST['prevent_consecutive_wins'] ?? '1',
            'auto_suggest_kpi' => $_POST['auto_suggest_kpi'] ?? '1'
        ];
        
        foreach ($settings_to_update as $key => $value) {
            $stmt = $pdo->prepare("UPDATE eom_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
            $stmt->execute([$value, $_SESSION['user_id'], $key]);
        }
        
        $success_message = "Criteria and settings saved successfully!";
        logActivity($pdo, $_SESSION['user_id'], 'update_eom_criteria', 'Updated EOM criteria and settings');
    }
    
    // Submit nomination (Supervisors only)
    if (isset($_POST['submit_nomination']) && $is_supervisor) {
        $employee_id = $_POST['employee_id'];
        $category = $_POST['category'];
        $reason = $_POST['reason'];
        $highlights = $_POST['performance_highlights'];
        $metrics = $_POST['supporting_metrics'];
        
        // Check if already nominated this month
        $stmt = $pdo->prepare("
            SELECT id FROM eom_nominations 
            WHERE employee_id = ? AND DATE_FORMAT(month, '%Y-%m') = ?
        ");
        $stmt->execute([$employee_id, $current_month]);
        
        if ($stmt->rowCount() > 0) {
            $error_message = "This employee has already been nominated for this month.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO eom_nominations 
                (month, employee_id, nominated_by, category, reason, performance_highlights, supporting_metrics, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $view_month,
                $employee_id,
                $_SESSION['user_id'],
                $category,
                $reason,
                $highlights,
                $metrics
            ]);
            
            $success_message = "Nomination submitted successfully!";
            logActivity($pdo, $_SESSION['user_id'], 'submit_eom_nomination', "Nominated employee #$employee_id for EOM");
        }
    }
    
    // Cast vote (if voting enabled)
    if (isset($_POST['cast_vote']) && $settings['voting_enabled'] == '1') {
        $nomination_id = $_POST['nomination_id'];
        
        // Check if already voted
        $stmt = $pdo->prepare("
            SELECT id FROM eom_votes 
            WHERE nomination_id = ? AND voter_id = ?
        ");
        $stmt->execute([$nomination_id, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO eom_votes (nomination_id, voter_id, score)
                VALUES (?, ?, 1)
            ");
            $stmt->execute([$nomination_id, $_SESSION['user_id']]);
            
            // Update vote count
            $pdo->exec("
                UPDATE eom_nominations 
                SET vote_count = vote_count + 1 
                WHERE id = $nomination_id
            ");
            
            $success_message = "Vote cast successfully!";
        } else {
            $error_message = "You have already voted.";
        }
    }
    
    // Select winner (HR/Admin only)
    if (isset($_POST['select_winner']) && $is_hr) {
        $nomination_id = $_POST['nomination_id'];
        $employee_id = $_POST['employee_id'];
        $reward_type = $_POST['reward_type'];
        $reward_details = $_POST['reward_details'];
        $description = $_POST['winner_description'];
        
        // Check if already has winner this month
        $stmt = $pdo->prepare("
            SELECT id FROM eom_winners WHERE DATE_FORMAT(month, '%Y-%m') = ?
        ");
        $stmt->execute([$current_month]);
        
        if ($stmt->rowCount() > 0) {
            $error_message = "A winner has already been selected for this month.";
        } else {
            // Check consecutive wins if enabled
            if ($settings['prevent_consecutive_wins'] == '1') {
                $stmt = $pdo->prepare("
                    SELECT id FROM eom_winners 
                    WHERE employee_id = ? 
                    ORDER BY month DESC LIMIT 1
                ");
                $stmt->execute([$employee_id]);
                $last_win = $stmt->fetch();
                
                if ($last_win) {
                    $last_month = date('Y-m', strtotime($last_win['month']));
                    if ($last_month == $prev_month) {
                        $error_message = "This employee won last month and cannot win consecutively.";
                    }
                }
            }
            
            if (!isset($error_message)) {
                // Update nomination status
                $pdo->prepare("UPDATE eom_nominations SET status = 'winner' WHERE id = ?")
                   ->execute([$nomination_id]);
                
                // Mark other nominations as rejected
                $pdo->prepare("
                    UPDATE eom_nominations 
                    SET status = 'rejected' 
                    WHERE month = ? AND id != ?
                ")->execute([$view_month, $nomination_id]);
                
                // Insert winner
                $stmt = $pdo->prepare("
                    INSERT INTO eom_winners 
                    (month, employee_id, nomination_id, reward_type, reward_details, description, approved_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $view_month,
                    $employee_id,
                    $nomination_id,
                    $reward_type,
                    $reward_details,
                    $description,
                    $_SESSION['user_id']
                ]);
                
                // Update employee record
                $pdo->prepare("
                    UPDATE new_hires 
                    SET eom_count = eom_count + 1, last_eom_month = ? 
                    WHERE id = ?
                ")->execute([$view_month, $employee_id]);
                
                $success_message = "Winner selected and announced successfully!";
                logActivity($pdo, $_SESSION['user_id'], 'select_eom_winner', "Selected employee #$employee_id as EOM");
            }
        }
    }
    
    // Auto-suggest nominees (HR/Admin only)
    if (isset($_POST['auto_suggest']) && $is_hr && $settings['auto_suggest_kpi'] == '1') {
        $suggestions = [];
        
        // Get employees with potential KPI data (simplified)
        $stmt = $pdo->prepare("
            SELECT 
                nh.*,
                jp.department,
                jp.title as position
            FROM new_hires nh
            LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
            WHERE nh.status = 'active'
            ORDER BY nh.id
            LIMIT 10
        ");
        $stmt->execute();
        $suggestions = $stmt->fetchAll();
        
        if (!empty($suggestions)) {
            $_SESSION['eom_suggestions'] = $suggestions;
            $info_message = "Found " . count($suggestions) . " employees eligible for nomination.";
        } else {
            $info_message = "No eligible employees found for auto-suggestion.";
        }
    }
}

// Get current month's winner
$stmt = $pdo->prepare("
    SELECT 
        w.*,
        nh.position,
        nh.department,
        nh.applicant_id,
        nh.job_posting_id,
        a.first_name,
        a.last_name,
        a.photo_path,
        u.full_name as approver_name
    FROM eom_winners w
    LEFT JOIN new_hires nh ON w.employee_id = nh.id
    LEFT JOIN job_applications a ON nh.applicant_id = a.id
    LEFT JOIN users u ON w.approved_by = u.id
    WHERE DATE_FORMAT(w.month, '%Y-%m') = ?
");
$stmt->execute([$current_month]);
$current_winner = $stmt->fetch();

// Get nominations for current month
$stmt = $pdo->prepare("
    SELECT 
        n.*,
        nh.position,
        nh.department,
        nh.applicant_id,
        a.first_name,
        a.last_name,
        a.photo_path,
        u.full_name as nominator_name,
        (SELECT COUNT(*) FROM eom_votes WHERE nomination_id = n.id) as actual_votes
    FROM eom_nominations n
    LEFT JOIN new_hires nh ON n.employee_id = nh.id
    LEFT JOIN job_applications a ON nh.applicant_id = a.id
    LEFT JOIN users u ON n.nominated_by = u.id
    WHERE DATE_FORMAT(n.month, '%Y-%m') = ?
    ORDER BY 
        CASE 
            WHEN n.status = 'winner' THEN 0
            WHEN n.status = 'approved' THEN 1
            WHEN n.status = 'pending' THEN 2
            ELSE 3
        END,
        n.vote_count DESC,
        n.created_at ASC
");
$stmt->execute([$current_month]);
$nominations = $stmt->fetchAll();

// Get previous winners
$stmt = $pdo->prepare("
    SELECT 
        w.*,
        nh.position,
        nh.department,
        nh.eom_count,
        a.first_name,
        a.last_name,
        a.photo_path
    FROM eom_winners w
    LEFT JOIN new_hires nh ON w.employee_id = nh.id
    LEFT JOIN job_applications a ON nh.applicant_id = a.id
    WHERE w.month < ?
    ORDER BY w.month DESC
    LIMIT 12
");
$stmt->execute([$view_month]);
$previous_winners = $stmt->fetchAll();

// Get employees for nomination dropdown (supervisor's team)
$employees = [];
if ($is_supervisor) {
    $stmt = $pdo->prepare("
        SELECT 
            nh.id,
            nh.position,
            nh.department,
            a.first_name,
            a.last_name,
            a.photo_path
        FROM new_hires nh
        LEFT JOIN job_applications a ON nh.applicant_id = a.id
        WHERE nh.status IN ('active', 'onboarding')
        ORDER BY a.last_name, a.first_name
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll();
}

// Get statistics
$stats = [];

// Total nominations this month
$stmt = $pdo->prepare("SELECT COUNT(*) FROM eom_nominations WHERE DATE_FORMAT(month, '%Y-%m') = ?");
$stmt->execute([$current_month]);
$stats['nominations'] = $stmt->fetchColumn();

// Departments participating
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT n.category) 
    FROM eom_nominations n 
    WHERE DATE_FORMAT(n.month, '%Y-%m') = ?
");
$stmt->execute([$current_month]);
$stats['departments'] = $stmt->fetchColumn();

// Total votes cast
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM eom_votes v
    INNER JOIN eom_nominations n ON v.nomination_id = n.id
    WHERE DATE_FORMAT(n.month, '%Y-%m') = ?
");
$stmt->execute([$current_month]);
$stats['votes'] = $stmt->fetchColumn();

// Total previous winners
$stmt = $pdo->query("SELECT COUNT(*) FROM eom_winners");
$stats['total_winners'] = $stmt->fetchColumn();

// Get criteria
$stmt = $pdo->query("
    SELECT * FROM eom_criteria 
    WHERE is_active = 1 
    ORDER BY sort_order ASC
");
$criteria_list = $stmt->fetchAll();

// Get settings for form
$voting_enabled = $settings['voting_enabled'] ?? '0';
$supervisor_weight = $settings['supervisor_weight'] ?? '50';
$kpi_weight = $settings['kpi_weight'] ?? '30';
$vote_weight = $settings['vote_weight'] ?? '20';
$prevent_consecutive = $settings['prevent_consecutive_wins'] ?? '1';
$auto_suggest = $settings['auto_suggest_kpi'] ?? '1';

// Helper function to get employee photo
function getEmployeePhoto($employee) {
    if (!empty($employee['photo_path']) && file_exists($employee['photo_path'])) {
        return htmlspecialchars($employee['photo_path']);
    }
    return null;
}

// Helper function to get category icon
function getCategoryIcon($category) {
    $icons = [
        'driver' => 'fa-truck',
        'warehouse' => 'fa-warehouse',
        'logistics' => 'fa-route',
        'admin' => 'fa-user-tie',
        'management' => 'fa-chart-line'
    ];
    return $icons[$category] ?? 'fa-user';
}

// Helper function to get employee full name
function getEmployeeFullName($employee) {
    $firstName = $employee['first_name'] ?? '';
    $lastName = $employee['last_name'] ?? '';
    return trim($firstName . ' ' . $lastName) ?: 'Unknown Employee';
}

// Helper function to get employee initials
function getEmployeeInitials($employee) {
    $firstName = $employee['first_name'] ?? '';
    $lastName = $employee['last_name'] ?? '';
    return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
}
?>

<style>
:root {
    --primary-color: #0e4c92;
    --primary-light: #1e5ca8;
    --primary-dark: #0a3a70;
    --primary-transparent: rgba(14, 76, 146, 0.1);
    --success-color: #27ae60;
    --warning-color: #f39c12;
    --danger-color: #e74c3c;
    --info-color: #3498db;
    --gold: #FFD700;
    --silver: #C0C0C0;
    --bronze: #CD7F32;
}

/* Page Header */
.page-header-unique {
    background: linear-gradient(135deg, #0e4c92 0%, #1e5ca8 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(14, 76, 146, 0.3);
    color: white;
    position: relative;
    overflow: hidden;
}

.page-header-unique::before {
    content: '🏆';
    position: absolute;
    right: 30px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 80px;
    opacity: 0.2;
}

.page-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 20px;
}

.page-title h1 {
    font-size: 28px;
    font-weight: 600;
    margin: 0;
    color: white;
}

.page-title i {
    font-size: 32px;
    color: var(--gold);
    background: rgba(255, 255, 255, 0.2);
    padding: 12px;
    border-radius: 15px;
}

.month-navigation {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255, 255, 255, 0.2);
    padding: 10px 20px;
    border-radius: 50px;
    backdrop-filter: blur(10px);
}

.month-navigation a {
    color: white;
    text-decoration: none;
    font-size: 18px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s;
}

.month-navigation a:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.month-navigation span {
    font-size: 18px;
    font-weight: 600;
    min-width: 150px;
    text-align: center;
}

/* Stats Cards */
.stats-grid-unique {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card-unique {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.03);
}

.stat-card-unique:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(14, 76, 146, 0.15);
}

.stat-icon-3d {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--gold) 0%, #FDB931 100%);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    box-shadow: 0 10px 20px rgba(255, 215, 0, 0.3);
}

.stat-content {
    flex: 1;
}

.stat-label {
    display: block;
    font-size: 13px;
    color: #64748b;
    margin-bottom: 5px;
    font-weight: 500;
}

.stat-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1.2;
}

/* Winner Card */
.winner-card {
    background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 15px 40px rgba(255, 215, 0, 0.2);
    border: 2px solid var(--gold);
    position: relative;
    overflow: hidden;
}

.winner-card::before {
    content: '🏆';
    position: absolute;
    right: 30px;
    bottom: 30px;
    font-size: 120px;
    opacity: 0.1;
    transform: rotate(15deg);
}

.winner-header {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 20px;
}

.winner-trophy {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--gold) 0%, #FDB931 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: white;
    box-shadow: 0 15px 30px rgba(255, 215, 0, 0.4);
}

.winner-title h2 {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 5px 0;
}

.winner-title p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

.winner-info {
    display: flex;
    align-items: center;
    gap: 30px;
}

.winner-photo-large {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    object-fit: cover;
    border: 5px solid white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 40px;
}

.winner-details {
    flex: 1;
}

.winner-name {
    font-size: 32px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 10px;
}

.winner-position {
    font-size: 18px;
    color: var(--primary-color);
    margin-bottom: 10px;
    font-weight: 500;
}

.winner-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--gold);
    color: #2c3e50;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 15px;
}

.winner-description {
    background: rgba(255, 215, 0, 0.1);
    padding: 20px;
    border-radius: 15px;
    font-size: 16px;
    line-height: 1.6;
    color: #2c3e50;
    border-left: 4px solid var(--gold);
}

/* Nomination Cards Grid */
.nominations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.nomination-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
}

.nomination-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(14, 76, 146, 0.15);
}

.nomination-card.winner {
    border: 2px solid var(--gold);
    background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
}

.nomination-card.winner::before {
    content: '🏆';
    position: absolute;
    right: 10px;
    top: 10px;
    font-size: 40px;
    opacity: 0.2;
}

.nomination-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.nomination-photo {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 20px;
    flex-shrink: 0;
}

.nomination-info {
    flex: 1;
}

.nomination-info h4 {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 5px 0;
}

.nomination-info p {
    margin: 2px 0;
    font-size: 13px;
    color: #64748b;
}

.nomination-category {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: var(--primary-transparent);
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 5px;
}

.nomination-reason {
    background: #f8fafd;
    border-radius: 12px;
    padding: 15px;
    margin: 15px 0;
    font-size: 14px;
    line-height: 1.5;
    color: #2c3e50;
    border-left: 3px solid var(--primary-color);
}

.nomination-metrics {
    display: flex;
    gap: 10px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.metric-badge {
    padding: 6px 12px;
    background: #f8fafd;
    border-radius: 20px;
    font-size: 12px;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 5px;
}

.metric-badge i {
    color: var(--primary-color);
}

.nomination-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eef2f6;
}

.nomination-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-pending {
    background: #f39c1220;
    color: #f39c12;
}

.status-approved {
    background: #3498db20;
    color: #3498db;
}
.status-winner {
    background: var(--gold);
    color: #2c3e50;
}

.status-rejected {
    background: #e74c3c20;
    color: #e74c3c;
}

.nomination-votes {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #64748b;
    font-size: 13px;
}

.nomination-votes i {
    color: var(--gold);
}

/* Filter Section */
.filter-section {
    background: white;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.filter-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-title i {
    color: var(--primary-color);
}

/* Tabs */
.tab-container {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.tab-header {
    display: flex;
    border-bottom: 1px solid #eef2f6;
    background: #f8fafd;
}

.tab-btn {
    padding: 15px 25px;
    background: none;
    border: none;
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    border-bottom: 3px solid transparent;
}

.tab-btn:hover {
    color: var(--primary-color);
    background: rgba(14, 76, 146, 0.05);
}

.tab-btn.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.tab-content {
    padding: 25px;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

/* Previous Winners Grid */
.winners-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.winner-history-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 1px solid rgba(0,0,0,0.03);
}

.winner-history-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(14, 76, 146, 0.1);
}

.winner-history-photo {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    object-fit: cover;
    background: linear-gradient(135deg, var(--gold) 0%, #FDB931 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 20px;
    flex-shrink: 0;
}

.winner-history-info {
    flex: 1;
}

.winner-history-info h4 {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 5px 0;
}

.winner-history-info p {
    margin: 2px 0;
    font-size: 13px;
    color: #64748b;
}

.winner-month {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: var(--gold);
    color: #2c3e50;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 5px;
}

/* Forms */
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
}

.form-group label i {
    color: var(--primary-color);
    margin-right: 5px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

/* Criteria Grid */
.criteria-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.criteria-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 1px solid #eef2f6;
}

.criteria-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.criteria-header h4 {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.criteria-weight {
    padding: 4px 10px;
    background: var(--primary-transparent);
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.criteria-description {
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
    margin-bottom: 10px;
}

.criteria-category {
    font-size: 12px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    color: white;
    padding: 12px 25px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
}

.btn-warning {
    background: linear-gradient(135deg, var(--gold) 0%, #FDB931 100%);
    color: #2c3e50;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
}

.btn-secondary {
    background: #f8fafd;
    color: var(--primary-color);
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}

.btn-secondary:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 12px;
}

.btn-block {
    width: 100%;
    justify-content: center;
}

/* Alert Messages */
.alert-success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid #c3e6cb;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid #ffeeba;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid #bee5eb;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 30px;
    max-width: 600px;
    width: 90%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #eef2f6;
}

.modal-header h3 {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header h3 i {
    color: var(--gold);
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #64748b;
    transition: color 0.3s;
}

.modal-close:hover {
    color: var(--danger-color);
}

/* Settings Panel */
.settings-panel {
    background: #f8fafd;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}

.settings-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.setting-item {
    background: white;
    border-radius: 12px;
    padding: 15px;
    border: 1px solid #eef2f6;
}

.setting-label {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 5px;
    display: block;
}

.setting-value {
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

.setting-value small {
    font-size: 12px;
    font-weight: normal;
    color: #64748b;
}

/* Toggle Switch */
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--success-color);
}

input:checked + .slider:before {
    transform: translateX(26px);
}

/* Image error handling */
.img-error-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    color: white;
    font-weight: 600;
}

.img-error-fallback.winner-photo-large {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    font-size: 40px;
}

.img-error-fallback.nomination-photo {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    font-size: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header-content {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .winner-header {
        flex-direction: column;
        text-align: center;
    }
    
    .winner-info {
        flex-direction: column;
        text-align: center;
    }
    
    .winner-photo-large {
        margin: 0 auto;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .tab-header {
        flex-wrap: wrap;
    }
    
    .tab-btn {
        flex: 1;
        padding: 12px;
        font-size: 12px;
    }
}
</style>

<!-- JavaScript for handling image errors -->
<script>
function handleImageError(img) {
    if (img.getAttribute('data-error-handled') === 'true') return;
    
    img.setAttribute('data-error-handled', 'true');
    
    const initials = img.getAttribute('data-initials') || '?';
    const className = img.className;
    
    const fallback = document.createElement('div');
    fallback.className = 'img-error-fallback ' + className;
    fallback.textContent = initials;
    
    img.parentNode.replaceChild(fallback, img);
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function switchTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabId).classList.add('active');
    
    // Activate button
    event.target.classList.add('active');
}
</script>

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-header-content">
        <div class="page-title">
            <i class="fas fa-trophy"></i>
            <div>
                <h1><?php echo $page_title; ?></h1>
                <p style="margin: 5px 0 0; opacity: 0.9;">Recognizing excellence and dedication</p>
            </div>
        </div>
        
        <div class="month-navigation">
            <a href="?page=recognition&subpage=employee-month&month=<?php echo $prev_month; ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
            <span>
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('F Y', strtotime($view_month)); ?>
            </span>
            <a href="?page=recognition&subpage=employee-month&month=<?php echo $next_month; ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
</div>

<?php if (isset($success_message)): ?>
<div class="alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
</div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert-warning">
    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
</div>
<?php endif; ?>

<?php if (isset($info_message)): ?>
<div class="alert-info">
    <i class="fas fa-info-circle"></i> <?php echo $info_message; ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid-unique">
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-trophy"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Current Month</span>
            <span class="stat-value"><?php echo $current_winner ? 'Winner Selected' : 'No Winner'; ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Nominations</span>
            <span class="stat-value"><?php echo $stats['nominations']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-building"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Departments</span>
            <span class="stat-value"><?php echo $stats['departments']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-history"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Previous Winners</span>
            <span class="stat-value"><?php echo $stats['total_winners']; ?></span>
        </div>
    </div>
</div>

<!-- Current Winner Display -->
<?php if ($current_winner): 
    $photoPath = getEmployeePhoto($current_winner);
    $fullName = getEmployeeFullName($current_winner);
    $initials = getEmployeeInitials($current_winner);
?>
<div class="winner-card">
    <div class="winner-header">
        <div class="winner-trophy">
            <i class="fas fa-crown"></i>
        </div>
        <div class="winner-title">
            <h2>Employee of the Month</h2>
            <p><?php echo date('F Y', strtotime($current_winner['month'])); ?></p>
        </div>
    </div>
    
    <div class="winner-info">
        <?php if ($photoPath): ?>
            <img src="<?php echo $photoPath; ?>" 
                 alt="<?php echo htmlspecialchars($fullName); ?>"
                 class="winner-photo-large"
                 onerror="handleImageError(this)"
                 data-initials="<?php echo $initials; ?>"
                 loading="lazy">
        <?php else: ?>
            <div class="winner-photo-large img-error-fallback">
                <?php echo $initials; ?>
            </div>
        <?php endif; ?>
        
        <div class="winner-details">
            <div class="winner-name"><?php echo htmlspecialchars($fullName); ?></div>
            <div class="winner-position">
                <i class="fas <?php echo getCategoryIcon($current_winner['department']); ?>"></i>
                <?php echo htmlspecialchars($current_winner['position'] ?? 'Employee'); ?> 
                (<?php echo ucfirst($current_winner['department'] ?? 'N/A'); ?>)
            </div>
            
            <div class="winner-badge">
                <i class="fas fa-star"></i>
                <?php 
                $reward_labels = [
                    'certificate' => 'Certificate of Excellence',
                    'bonus' => 'Performance Bonus',
                    'gift_card' => 'Gift Card',
                    'extra_leave' => 'Extra Leave Day',
                    'public_recognition' => 'Public Recognition'
                ];
                echo $reward_labels[$current_winner['reward_type']] ?? 'Recognition';
                ?>
                <?php if (!empty($current_winner['reward_details'])): ?>
                <span style="background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 15px;">
                    <?php echo htmlspecialchars($current_winner['reward_details']); ?>
                </span>
                <?php endif; ?>
            </div>
            
            <div class="winner-description">
                <i class="fas fa-quote-left" style="color: var(--gold); opacity: 0.5; margin-right: 5px;"></i>
                <?php echo nl2br(htmlspecialchars($current_winner['description'])); ?>
                <i class="fas fa-quote-right" style="color: var(--gold); opacity: 0.5; margin-left: 5px;"></i>
            </div>
            
            <div style="margin-top: 15px; font-size: 13px; color: #64748b;">
                <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                Approved by <?php echo htmlspecialchars($current_winner['approver_name'] ?? 'HR'); ?>
                on <?php echo date('F d, Y', strtotime($current_winner['approved_at'])); ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Tabs -->
<div class="tab-container">
    <div class="tab-header">
        <button class="tab-btn active" onclick="switchTab('nominations-tab')">
            <i class="fas fa-users"></i> Nominations
        </button>
        <button class="tab-btn" onclick="switchTab('history-tab')">
            <i class="fas fa-history"></i> Previous Winners
        </button>
        <?php if ($is_supervisor): ?>
        <button class="tab-btn" onclick="switchTab('nominate-tab')">
            <i class="fas fa-plus-circle"></i> Nominate
        </button>
        <?php endif; ?>
        <?php if ($is_hr): ?>
        <button class="tab-btn" onclick="switchTab('criteria-tab')">
            <i class="fas fa-sliders-h"></i> Criteria
        </button>
        <?php endif; ?>
    </div>
    
    <div class="tab-content">
        <!-- Nominations Tab -->
        <div id="nominations-tab" class="tab-pane active">
            <?php if (empty($nominations)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-users" style="font-size: 60px; color: #ddd; margin-bottom: 20px;"></i>
                <h3 style="color: #64748b; margin-bottom: 10px;">No Nominations Yet</h3>
                <p style="color: #94a3b8; margin-bottom: 20px;">No nominations have been submitted for this month.</p>
                <?php if ($is_supervisor): ?>
                <button onclick="switchTab('nominate-tab')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Submit a Nomination
                </button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="nominations-grid">
                <?php foreach ($nominations as $nomination): 
                    $photoPath = getEmployeePhoto($nomination);
                    $fullName = getEmployeeFullName($nomination);
                    $initials = getEmployeeInitials($nomination);
                    
                    $status_class = 'status-pending';
                    $status_text = 'Pending';
                    
                    if ($nomination['status'] == 'winner') {
                        $status_class = 'status-winner';
                        $status_text = 'Winner 🏆';
                    } elseif ($nomination['status'] == 'approved') {
                        $status_class = 'status-approved';
                        $status_text = 'Approved';
                    } elseif ($nomination['status'] == 'rejected') {
                        $status_class = 'status-rejected';
                        $status_text = 'Rejected';
                    }
                ?>
                <div class="nomination-card <?php echo $nomination['status'] == 'winner' ? 'winner' : ''; ?>">
                    <div class="nomination-header">
                        <?php if ($photoPath): ?>
                            <img src="<?php echo $photoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fullName); ?>"
                                 class="nomination-photo"
                                 onerror="handleImageError(this)"
                                 data-initials="<?php echo $initials; ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="nomination-photo img-error-fallback">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="nomination-info">
                            <h4><?php echo htmlspecialchars($fullName); ?></h4>
                            <p>
                                <i class="fas fa-briefcase"></i>
                                <?php echo htmlspecialchars($nomination['position'] ?? 'Employee'); ?>
                            </p>
                            <div class="nomination-category">
                                <i class="fas <?php echo getCategoryIcon($nomination['category']); ?>"></i>
                                <?php echo ucfirst($nomination['category']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="nomination-reason">
                        <strong>Reason:</strong><br>
                        <?php echo nl2br(htmlspecialchars($nomination['reason'])); ?>
                    </div>
                    
                    <?php if (!empty($nomination['performance_highlights'])): ?>
                    <div class="nomination-metrics">
                        <div class="metric-badge">
                            <i class="fas fa-star"></i>
                            <?php echo htmlspecialchars($nomination['performance_highlights']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($nomination['supporting_metrics'])): ?>
                    <div class="nomination-metrics">
                        <div class="metric-badge">
                            <i class="fas fa-chart-line"></i>
                            <?php echo htmlspecialchars($nomination['supporting_metrics']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="nomination-footer">
                        <span class="nomination-status <?php echo $status_class; ?>">
                            <i class="fas <?php 
                                echo $nomination['status'] == 'winner' ? 'fa-trophy' : 
                                    ($nomination['status'] == 'approved' ? 'fa-check' : 
                                    ($nomination['status'] == 'rejected' ? 'fa-times' : 'fa-clock')); 
                            ?>"></i>
                            <?php echo $status_text; ?>
                        </span>
                        
                        <?php if ($settings['voting_enabled'] == '1' && $nomination['status'] == 'pending'): ?>
                            <?php
                            // Check if user has already voted
                            $stmt = $pdo->prepare("SELECT id FROM eom_votes WHERE nomination_id = ? AND voter_id = ?");
                            $stmt->execute([$nomination['id'], $_SESSION['user_id']]);
                            $has_voted = $stmt->fetch();
                            ?>
                            
                            <?php if (!$has_voted): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="nomination_id" value="<?php echo $nomination['id']; ?>">
                                <button type="submit" name="cast_vote" class="btn-success btn-sm">
                                    <i class="fas fa-vote-yea"></i> Vote
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="nomination-votes">
                                <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                                Voted
                            </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <span class="nomination-votes">
                            <i class="fas fa-heart" style="color: #e74c3c;"></i>
                            <?php echo $nomination['actual_votes'] ?? 0; ?> votes
                        </span>
                        
                        <small style="color: #64748b;">
                            <i class="fas fa-user-check"></i>
                            by <?php echo htmlspecialchars($nomination['nominator_name'] ?? 'Unknown'); ?>
                        </small>
                    </div>
                    
                    <?php if ($is_hr && $nomination['status'] == 'pending' && !$current_winner): ?>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #eef2f6;">
                        <button onclick="openModal('winnerModal_<?php echo $nomination['id']; ?>')" class="btn-warning btn-sm btn-block">
                            <i class="fas fa-crown"></i> Select as Winner
                        </button>
                    </div>
                    
                    <!-- Winner Selection Modal -->
                    <div id="winnerModal_<?php echo $nomination['id']; ?>" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>
                                    <i class="fas fa-crown" style="color: var(--gold);"></i>
                                    Select Winner
                                </h3>
                                <button class="modal-close" onclick="closeModal('winnerModal_<?php echo $nomination['id']; ?>')">&times;</button>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <p>You are about to select <strong><?php echo htmlspecialchars($fullName); ?></strong> as Employee of the Month for <?php echo date('F Y', strtotime($view_month)); ?>.</p>
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="nomination_id" value="<?php echo $nomination['id']; ?>">
                                <input type="hidden" name="employee_id" value="<?php echo $nomination['employee_id']; ?>">
                                
                                <div class="form-group">
                                    <label><i class="fas fa-gift"></i> Reward Type</label>
                                    <select name="reward_type" required>
                                        <option value="certificate">Certificate of Excellence</option>
                                        <option value="bonus">Performance Bonus</option>
                                        <option value="gift_card">Gift Card</option>
                                        <option value="extra_leave">Extra Leave Day</option>
                                        <option value="public_recognition">Public Recognition Only</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-tag"></i> Reward Details (Optional)</label>
                                    <input type="text" name="reward_details" placeholder="e.g., ₱1,000, 1 day, etc.">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-pen"></i> Award Description</label>
                                    <textarea name="winner_description" placeholder="Describe why this employee deserves to be Employee of the Month..." required><?php echo $nomination['reason']; ?></textarea>
                                </div>
                                
                                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                    <button type="button" onclick="closeModal('winnerModal_<?php echo $nomination['id']; ?>')" class="btn-secondary">
                                        Cancel
                                    </button>
                                    <button type="submit" name="select_winner" class="btn-warning">
                                        <i class="fas fa-crown"></i> Confirm Winner
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- History Tab -->
        <div id="history-tab" class="tab-pane">
            <?php if (empty($previous_winners)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-history" style="font-size: 60px; color: #ddd; margin-bottom: 20px;"></i>
                <h3 style="color: #64748b; margin-bottom: 10px;">No Previous Winners</h3>
                <p style="color: #94a3b8;">Recognition history will appear here once winners are selected.</p>
            </div>
            <?php else: ?>
            <div class="winners-grid">
                <?php foreach ($previous_winners as $winner): 
                    $photoPath = getEmployeePhoto($winner);
                    $fullName = getEmployeeFullName($winner);
                    $initials = getEmployeeInitials($winner);
                ?>
                <div class="winner-history-card">
                    <?php if ($photoPath): ?>
                        <img src="<?php echo $photoPath; ?>" 
                             alt="<?php echo htmlspecialchars($fullName); ?>"
                             class="winner-history-photo"
                             onerror="handleImageError(this)"
                             data-initials="<?php echo $initials; ?>"
                             loading="lazy">
                    <?php else: ?>
                        <div class="winner-history-photo img-error-fallback">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="winner-history-info">
                        <h4><?php echo htmlspecialchars($fullName); ?></h4>
                        <p>
                            <i class="fas fa-briefcase"></i>
                            <?php echo htmlspecialchars($winner['position'] ?? 'Employee'); ?>
                        </p>
                        <p>
                            <i class="fas fa-building"></i>
                            <?php echo ucfirst($winner['department'] ?? 'N/A'); ?>
                        </p>
                        <div class="winner-month">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('F Y', strtotime($winner['month'])); ?>
                        </div>
                        <?php if (($winner['eom_count'] ?? 0) > 1): ?>
                        <div style="margin-top: 5px;">
                            <span class="category-badge badge-success">
                                <i class="fas fa-trophy"></i> <?php echo $winner['eom_count']; ?>x Winner
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Nominate Tab (Supervisors only) -->
        <?php if ($is_supervisor): ?>
        <div id="nominate-tab" class="tab-pane">
            <div class="form-container">
                <h3 style="margin-bottom: 20px;">Submit a Nomination</h3>
                
                <?php if (!empty($_SESSION['eom_suggestions']) && $auto_suggest == '1'): ?>
                <div class="alert-info" style="margin-bottom: 20px;">
                    <i class="fas fa-robot"></i>
                    <strong>Suggestions:</strong> The following employees are eligible for nomination:
                    <ul style="margin-top: 10px;">
                        <?php foreach ($_SESSION['eom_suggestions'] as $suggestion): 
                            $name = getEmployeeFullName($suggestion);
                        ?>
                        <li>
                            <?php echo htmlspecialchars($name); ?>
                            (<?php echo ucfirst($suggestion['department'] ?? 'N/A'); ?>)
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php unset($_SESSION['eom_suggestions']); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($is_hr && $auto_suggest == '1'): ?>
                <form method="POST" style="margin-bottom: 20px;">
                    <button type="submit" name="auto_suggest" class="btn-secondary">
                        <i class="fas fa-robot"></i> Show Suggestions
                    </button>
                </form>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Employee</label>
                            <select name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): 
                                    $name = getEmployeeFullName($emp);
                                ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($name); ?>
                                    (<?php echo ucfirst($emp['department'] ?? 'N/A'); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Category</label>
                            <select name="category" required>
                                <option value="driver">Driver</option>
                                <option value="warehouse">Warehouse</option>
                                <option value="logistics">Logistics</option>
                                <option value="admin">Admin</option>
                                <option value="management">Management</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Reason for Nomination</label>
                        <textarea name="reason" placeholder="Explain why this employee deserves to be Employee of the Month..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-chart-line"></i> Performance Highlights</label>
                        <input type="text" name="performance_highlights" placeholder="e.g., 100% on-time delivery, No violations, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-chart-bar"></i> Supporting Metrics (Optional)</label>
                        <input type="text" name="supporting_metrics" placeholder="e.g., 98% accuracy, 5 positive feedback, etc.">
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="switchTab('nominations-tab')" class="btn-secondary">
                            Cancel
                        </button>
                        <button type="submit" name="submit_nomination" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Nomination
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Criteria Tab (HR/Admin only) -->
        <?php if ($is_hr): ?>
        <div id="criteria-tab" class="tab-pane">
            <div class="form-container">
                <h3 style="margin-bottom: 20px;">EOM Criteria & Settings</h3>
                
                <form method="POST">
                    <!-- Settings Panel -->
                    <div class="settings-panel">
                        <div class="settings-title">
                            <i class="fas fa-cog"></i> Voting Settings
                        </div>
                        
                        <div class="settings-grid">
                            <div class="setting-item">
                                <span class="setting-label">Enable Employee Voting</span>
                                <label class="switch">
                                    <input type="checkbox" name="voting_enabled" value="1" <?php echo $voting_enabled == '1' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="setting-item">
                                <span class="setting-label">Prevent Consecutive Wins</span>
                                <label class="switch">
                                    <input type="checkbox" name="prevent_consecutive_wins" value="1" <?php echo $prevent_consecutive == '1' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="setting-item">
                                <span class="setting-label">Auto-Suggest</span>
                                <label class="switch">
                                    <input type="checkbox" name="auto_suggest_kpi" value="1" <?php echo $auto_suggest == '1' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <div class="settings-title">
                                <i class="fas fa-weight"></i> Scoring Weights
                            </div>
                            
                            <div class="settings-grid">
                                <div class="setting-item">
                                    <span class="setting-label">Supervisor Score Weight</span>
                                    <div class="setting-value">
                                        <input type="number" name="supervisor_weight" value="<?php echo $supervisor_weight; ?>" min="0" max="100" step="5" style="width: 80px;"> <small>%</small>
                                    </div>
                                </div>
                                
                                <div class="setting-item">
                                    <span class="setting-label">KPI Score Weight</span>
                                    <div class="setting-value">
                                        <input type="number" name="kpi_weight" value="<?php echo $kpi_weight; ?>" min="0" max="100" step="5" style="width: 80px;"> <small>%</small>
                                    </div>
                                </div>
                                
                                <div class="setting-item">
                                    <span class="setting-label">Employee Vote Weight</span>
                                    <div class="setting-value">
                                        <input type="number" name="vote_weight" value="<?php echo $vote_weight; ?>" min="0" max="100" step="5" style="width: 80px;"> <small>%</small>
                                    </div>
                                </div>
                            </div>
                            <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Total should equal 100%</p>
                        </div>
                    </div>
                    
                    <!-- Criteria List -->
                    <div class="settings-title">
                        <i class="fas fa-list"></i> Evaluation Criteria
                    </div>
                    
                    <div id="criteria-container">
                        <?php if (!empty($criteria_list)): ?>
                            <?php foreach ($criteria_list as $index => $criteria): ?>
                            <div class="criteria-card" style="margin-bottom: 15px;">
                                <div style="display: flex; gap: 10px; align-items: flex-start;">
                                    <div style="flex: 2;">
                                        <input type="text" name="criteria[<?php echo $index; ?>][name]" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($criteria['criteria_name']); ?>" 
                                               placeholder="Criteria Name" required>
                                    </div>
                                    <div style="flex: 1;">
                                        <input type="number" name="criteria[<?php echo $index; ?>][weight]" 
                                               class="form-control" 
                                               value="<?php echo $criteria['weight']; ?>" 
                                               placeholder="Weight %" min="0" max="100" required>
                                    </div>
                                    <div style="flex: 1;">
                                        <select name="criteria[<?php echo $index; ?>][category]" class="form-control">
                                            <option value="all" <?php echo $criteria['category'] == 'all' ? 'selected' : ''; ?>>All Departments</option>
                                            <option value="driver" <?php echo $criteria['category'] == 'driver' ? 'selected' : ''; ?>>Driver</option>
                                            <option value="warehouse" <?php echo $criteria['category'] == 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                                            <option value="logistics" <?php echo $criteria['category'] == 'logistics' ? 'selected' : ''; ?>>Logistics</option>
                                            <option value="admin" <?php echo $criteria['category'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="management" <?php echo $criteria['category'] == 'management' ? 'selected' : ''; ?>>Management</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn-secondary btn-sm" onclick="this.closest('.criteria-card').remove()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div style="margin-top: 10px;">
                                    <input type="text" name="criteria[<?php echo $index; ?>][description]" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($criteria['description'] ?? ''); ?>" 
                                           placeholder="Description (optional)">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Default criteria -->
                            <?php 
                            $default_criteria = [
                                ['name' => 'On-time Performance', 'weight' => 25, 'desc' => 'Consistently meeting deadlines and schedules'],
                                ['name' => 'Quality of Work', 'weight' => 20, 'desc' => 'Accuracy and attention to detail'],
                                ['name' => 'Attendance & Punctuality', 'weight' => 20, 'desc' => 'No absences or tardiness'],
                                ['name' => 'Teamwork', 'weight' => 15, 'desc' => 'Collaboration with colleagues'],
                                ['name' => 'Customer Feedback', 'weight' => 10, 'desc' => 'Positive feedback from customers'],
                                ['name' => 'Safety Compliance', 'weight' => 10, 'desc' => 'Following safety protocols']
                            ];
                            
                            foreach ($default_criteria as $index => $criteria): ?>
                            <div class="criteria-card" style="margin-bottom: 15px;">
                                <div style="display: flex; gap: 10px; align-items: flex-start;">
                                    <div style="flex: 2;">
                                        <input type="text" name="criteria[<?php echo $index; ?>][name]" class="form-control" value="<?php echo $criteria['name']; ?>" placeholder="Criteria Name" required>
                                    </div>
                                    <div style="flex: 1;">
                                        <input type="number" name="criteria[<?php echo $index; ?>][weight]" class="form-control" value="<?php echo $criteria['weight']; ?>" placeholder="Weight %" min="0" max="100" required>
                                    </div>
                                    <div style="flex: 1;">
                                        <select name="criteria[<?php echo $index; ?>][category]" class="form-control">
                                            <option value="all">All Departments</option>
                                            <option value="driver">Driver</option>
                                            <option value="warehouse">Warehouse</option>
                                            <option value="logistics">Logistics</option>
                                            <option value="admin">Admin</option>
                                            <option value="management">Management</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn-secondary btn-sm" onclick="this.closest('.criteria-card').remove()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div style="margin-top: 10px;">
                                    <input type="text" name="criteria[<?php echo $index; ?>][description]" class="form-control" value="<?php echo $criteria['desc']; ?>" placeholder="Description">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" class="btn-secondary" onclick="addCriteria()" style="margin-bottom: 20px;">
                        <i class="fas fa-plus"></i> Add Criteria
                    </button>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="submit" name="save_criteria" class="btn-primary">
                            <i class="fas fa-save"></i> Save Criteria & Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
let criteriaIndex = <?php echo count($criteria_list) ?: 6; ?>;

function addCriteria() {
    const container = document.getElementById('criteria-container');
    const div = document.createElement('div');
    div.className = 'criteria-card';
    div.style.marginBottom = '15px';
    div.innerHTML = `
        <div style="display: flex; gap: 10px; align-items: flex-start;">
            <div style="flex: 2;">
                <input type="text" name="criteria[${criteriaIndex}][name]" class="form-control" placeholder="Criteria Name" required>
            </div>
            <div style="flex: 1;">
                <input type="number" name="criteria[${criteriaIndex}][weight]" class="form-control" placeholder="Weight %" min="0" max="100" required>
            </div>
            <div style="flex: 1;">
                <select name="criteria[${criteriaIndex}][category]" class="form-control">
                    <option value="all">All Departments</option>
                    <option value="driver">Driver</option>
                    <option value="warehouse">Warehouse</option>
                    <option value="logistics">Logistics</option>
                    <option value="admin">Admin</option>
                    <option value="management">Management</option>
                </select>
            </div>
            <button type="button" class="btn-secondary btn-sm" onclick="this.closest('.criteria-card').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="margin-top: 10px;">
            <input type="text" name="criteria[${criteriaIndex}][description]" class="form-control" placeholder="Description (optional)">
        </div>
    `;
    container.appendChild(div);
    criteriaIndex++;
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>