<?php
// modules/compensation/rewards-incentives.php
$page_title = "Rewards & Incentives";

// Check if user has access
$is_admin = ($_SESSION['role'] === 'admin');
$is_manager = ($_SESSION['role'] === 'manager');
$is_hr = ($is_admin || $is_manager);
$is_supervisor = ($is_admin || $is_manager);
$is_finance = ($is_admin || $_SESSION['role'] === 'finance');
$user_id = $_SESSION['user_id'];

// Get current tab
$active_tab = $_GET['tab'] ?? 'dashboard';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Create new incentive program (HR only)
    if (isset($_POST['create_program']) && $is_hr) {
        $program_code = strtoupper($_POST['program_code']);
        $program_name = $_POST['program_name'];
        $category = $_POST['category'];
        $description = $_POST['description'];
        $eligibility_criteria = $_POST['eligibility_criteria'];
        $calculation_method = $_POST['calculation_method'];
        $reward_type = $_POST['reward_type'];
        $reward_value = $_POST['reward_value'];
        $budget_limit = $_POST['budget_limit'] ?: null;
        $department = $_POST['department'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?: null;
        $recurring_frequency = $_POST['recurring_frequency'];
        
        // Check if program code exists
        $stmt = $pdo->prepare("SELECT id FROM incentive_programs WHERE program_code = ?");
        $stmt->execute([$program_code]);
        
        if ($stmt->rowCount() > 0) {
            $error_message = "Program code already exists.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO incentive_programs 
                (program_code, program_name, category, description, eligibility_criteria, calculation_method,
                 reward_type, reward_value, budget_limit, department, start_date, end_date, recurring_frequency, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $program_code,
                $program_name,
                $category,
                $description,
                $eligibility_criteria,
                $calculation_method,
                $reward_type,
                $reward_value,
                $budget_limit,
                $department,
                $start_date,
                $end_date,
                $recurring_frequency,
                $user_id
            ]);
            
            $success_message = "Incentive program created successfully!";
            logActivity($pdo, $user_id, 'create_incentive_program', "Created program: $program_code");
        }
    }
    
    // Run eligibility check (HR/Supervisor)
    if (isset($_POST['run_eligibility']) && $is_supervisor) {
        $program_id = $_POST['program_id'];
        $period_start = $_POST['period_start'];
        $period_end = $_POST['period_end'];
        
        // Get program details
        $stmt = $pdo->prepare("SELECT * FROM incentive_programs WHERE id = ?");
        $stmt->execute([$program_id]);
        $program = $stmt->fetch();
        
        if ($program) {
            $eligible_count = 0;
            
            // Get all active employees in the department
            $dept_condition = $program['department'] != 'all' ? "AND department = '{$program['department']}'" : "";
            $stmt = $pdo->query("
                SELECT nh.*, a.first_name, a.last_name
                FROM new_hires nh
                LEFT JOIN job_applications a ON nh.applicant_id = a.id
                WHERE nh.status IN ('active', 'onboarding') $dept_condition
            ");
            $employees = $stmt->fetchAll();
            
            foreach ($employees as $employee) {
                $criteria_met = [];
                $criteria_missed = [];
                $eligible = true;
                $score = 0;
                
                // Check based on program category
                switch ($program['category']) {
                    case 'safety':
                        // Check safety incidents
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM probation_incidents 
                            WHERE probation_record_id IN (
                                SELECT id FROM probation_records WHERE new_hire_id = ?
                            )
                            AND incident_date BETWEEN ? AND ?
                            AND incident_type IN ('safety', 'accident')
                        ");
                        $stmt->execute([$employee['id'], $period_start, $period_end]);
                        $incidents = $stmt->fetchColumn();
                        
                        if ($incidents == 0) {
                            $criteria_met[] = "Zero safety incidents";
                            $score = 100;
                        } else {
                            $eligible = false;
                            $criteria_missed[] = "$incidents safety incidents recorded";
                        }
                        break;
                        
                    case 'performance':
                        // Check performance reviews
                        $stmt = $pdo->prepare("
                            SELECT AVG(overall_rating) as avg_rating
                            FROM performance_reviews
                            WHERE employee_id = ?
                            AND review_date BETWEEN ? AND ?
                        ");
                        $stmt->execute([$employee['id'], $period_start, $period_end]);
                        $avg_rating = $stmt->fetchColumn();
                        
                        if ($avg_rating && $avg_rating >= 4.5) {
                            $criteria_met[] = "Performance rating: " . number_format($avg_rating, 1) . "/5";
                            $score = ($avg_rating / 5) * 100;
                        } else {
                            $eligible = false;
                            $criteria_missed[] = "Performance rating below 4.5";
                        }
                        break;
                        
                    case 'productivity':
                        // Simplified check - in real system would pull from KPIs
                        $criteria_met[] = "Productivity criteria met (simplified)";
                        $score = 95;
                        break;
                        
                    case 'attendance':
                        // Check attendance
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM attendance 
                            WHERE employee_id = ?
                            AND date BETWEEN ? AND ?
                            AND status IN ('absent', 'late')
                        ");
                        $stmt->execute([$employee['id'], $period_start, $period_end]);
                        $issues = $stmt->fetchColumn();
                        
                        if ($issues == 0) {
                            $criteria_met[] = "Perfect attendance";
                            $score = 100;
                        } else {
                            $eligible = false;
                            $criteria_missed[] = "$issues attendance issues";
                        }
                        break;
                        
                    default:
                        $criteria_met[] = "General eligibility";
                        $score = 85;
                }
                
                if ($eligible) {
                    // Check if already exists for this period
                    $stmt = $pdo->prepare("
                        SELECT id FROM incentive_eligibility
                        WHERE employee_id = ? AND program_id = ? AND period_start = ?
                    ");
                    $stmt->execute([$employee['id'], $program_id, $period_start]);
                    
                    if ($stmt->rowCount() == 0) {
                        // Insert eligibility record
                        $stmt = $pdo->prepare("
                            INSERT INTO incentive_eligibility
                            (employee_id, program_id, period_start, period_end, eligibility_score, 
                             criteria_met, calculated_value, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                        ");
                        
                        $calculated_value = ($score / 100) * $program['reward_value'];
                        
                        $stmt->execute([
                            $employee['id'],
                            $program_id,
                            $period_start,
                            $period_end,
                            $score,
                            implode("\n", $criteria_met),
                            $calculated_value
                        ]);
                        
                        $eligible_count++;
                    }
                }
            }
            
            $success_message = "Eligibility check completed. $eligible_count employees marked as eligible.";
            logActivity($pdo, $user_id, 'run_eligibility', "Ran eligibility for program #$program_id");
        }
    }
    
    // Approve/Reject eligibility (Supervisor)
    if (isset($_POST['update_eligibility']) && $is_supervisor) {
        $eligibility_id = $_POST['eligibility_id'];
        $action = $_POST['action'];
        $comments = $_POST['comments'] ?? '';
        
        if ($action === 'approve') {
            $stmt = $pdo->prepare("
                UPDATE incentive_eligibility 
                SET supervisor_approved = 1, supervisor_id = ?, supervisor_approved_at = NOW(), 
                    supervisor_comments = ?, status = 'eligible'
                WHERE id = ?
            ");
            $stmt->execute([$user_id, $comments, $eligibility_id]);
            
            $success_message = "Eligibility approved successfully!";
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("
                UPDATE incentive_eligibility 
                SET status = 'rejected', rejection_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$comments, $eligibility_id]);
            
            $success_message = "Eligibility rejected.";
        }
        
        logActivity($pdo, $user_id, 'update_eligibility', "Updated eligibility #$eligibility_id: $action");
    }
    
    // Process payout (HR/Finance)
    if (isset($_POST['process_payout']) && ($is_hr || $is_finance)) {
        $eligibility_id = $_POST['eligibility_id'];
        $employee_id = $_POST['employee_id'];
        $program_id = $_POST['program_id'];
        $amount = $_POST['amount'];
        $payout_method = $_POST['payout_method'];
        $reference_number = $_POST['reference_number'] ?? '';
        
        // Generate payout number
        $year = date('Y');
        $month = date('m');
        $stmt = $pdo->query("SELECT COUNT(*) FROM incentive_payouts WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn() + 1;
        $payout_number = sprintf("PAY-%s%s-%04d", $year, $month, $count);
        
        $stmt = $pdo->prepare("
            INSERT INTO incentive_payouts
            (payout_number, employee_id, eligibility_id, program_id, amount, reward_type, 
             payout_date, payout_method, reference_number, approved_by, approved_at, status)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, NOW(), 'approved')
        ");
        
        $stmt->execute([
            $payout_number,
            $employee_id,
            $eligibility_id,
            $program_id,
            $amount,
            $_POST['reward_type'],
            $payout_method,
            $reference_number,
            $user_id
        ]);
        
        // Update eligibility status
        $pdo->prepare("UPDATE incentive_eligibility SET status = 'approved' WHERE id = ?")
           ->execute([$eligibility_id]);
        
        // Update program budget used
        $pdo->prepare("
            UPDATE incentive_programs 
            SET budget_used = budget_used + ? 
            WHERE id = ?
        ")->execute([$amount, $program_id]);
        
        // Add points if applicable
        if ($_POST['reward_type'] === 'points') {
            // Check if points record exists
            $stmt = $pdo->prepare("SELECT id FROM incentive_points WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            
            if ($stmt->rowCount() == 0) {
                $pdo->prepare("INSERT INTO incentive_points (employee_id, points_earned, points_balance) VALUES (?, ?, ?)")
                    ->execute([$employee_id, $amount, $amount]);
            } else {
                $pdo->prepare("
                    UPDATE incentive_points 
                    SET points_earned = points_earned + ?, points_balance = points_balance + ?
                    WHERE employee_id = ?
                ")->execute([$amount, $amount, $employee_id]);
            }
            
            // Add transaction
            $pdo->prepare("
                INSERT INTO incentive_points_transactions
                (employee_id, transaction_type, points, reference_type, reference_id, description, balance_after, created_by)
                VALUES (?, 'earn', ?, 'incentive', ?, ?, ?, ?)
            ")->execute([
                $employee_id,
                $amount,
                $eligibility_id,
                "Incentive payout from program #$program_id",
                $amount, // balance after (simplified)
                $user_id
            ]);
        }
        
        $success_message = "Payout processed successfully! Payout #: $payout_number";
        logActivity($pdo, $user_id, 'process_payout', "Processed payout $payout_number for employee #$employee_id");
    }
    
    // Mark as paid (Finance)
    if (isset($_POST['mark_paid']) && $is_finance) {
        $payout_id = $_POST['payout_id'];
        
        $stmt = $pdo->prepare("
            UPDATE incentive_payouts 
            SET status = 'paid', paid_by = ?, paid_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user_id, $payout_id]);
        
        $success_message = "Payout marked as paid!";
        logActivity($pdo, $user_id, 'mark_paid', "Marked payout #$payout_id as paid");
    }
    
    // Redeem points (Employee)
    if (isset($_POST['redeem_points'])) {
        $employee_id = $_POST['employee_id'];
        $item_id = $_POST['item_id'];
        $quantity = $_POST['quantity'] ?? 1;
        
        // Get item details
        $stmt = $pdo->prepare("SELECT * FROM incentive_redeemable_items WHERE id = ? AND is_active = 1");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        // Get points balance
        $stmt = $pdo->prepare("SELECT points_balance FROM incentive_points WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $points = $stmt->fetch();
        
        $total_points_needed = $item['points_required'] * $quantity;
        
        if ($points && $points['points_balance'] >= $total_points_needed) {
            if ($item['stock'] < $quantity) {
                $error_message = "Insufficient stock available.";
            } else {
                // Generate redemption number
                $year = date('Y');
                $month = date('m');
                $stmt = $pdo->query("SELECT COUNT(*) FROM incentive_redemptions WHERE YEAR(created_at) = $year");
                $count = $stmt->fetchColumn() + 1;
                $redemption_number = sprintf("RED-%s%s-%04d", $year, $month, $count);
                
                $stmt = $pdo->prepare("
                    INSERT INTO incentive_redemptions
                    (redemption_number, employee_id, item_id, points_used, quantity, total_points, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $redemption_number,
                    $employee_id,
                    $item_id,
                    $item['points_required'],
                    $quantity,
                    $total_points_needed
                ]);
                
                // Update points balance (pending until approved)
                $pdo->prepare("
                    UPDATE incentive_points 
                    SET points_balance = points_balance - ? 
                    WHERE employee_id = ?
                ")->execute([$total_points_needed, $employee_id]);
                
                // Update item stock
                $pdo->prepare("
                    UPDATE incentive_redeemable_items 
                    SET stock = stock - ? 
                    WHERE id = ?
                ")->execute([$quantity, $item_id]);
                
                // Add transaction
                $pdo->prepare("
                    INSERT INTO incentive_points_transactions
                    (employee_id, transaction_type, points, reference_type, reference_id, description, balance_after, created_by)
                    VALUES (?, 'redeem', ?, 'redemption', ?, ?, ?, ?)
                ")->execute([
                    $employee_id,
                    $total_points_needed,
                    $pdo->lastInsertId(),
                    "Redeemed $quantity x {$item['item_name']}",
                    $points['points_balance'] - $total_points_needed,
                    $user_id
                ]);
                
                $success_message = "Redemption request submitted successfully!";
                logActivity($pdo, $user_id, 'redeem_points', "Redeemed $total_points_needed points for item #$item_id");
            }
        } else {
            $error_message = "Insufficient points balance.";
        }
    }
    
    // Approve redemption (HR)
    if (isset($_POST['approve_redemption']) && $is_hr) {
        $redemption_id = $_POST['redemption_id'];
        
        $stmt = $pdo->prepare("
            UPDATE incentive_redemptions 
            SET status = 'approved', approved_by = ?, approved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user_id, $redemption_id]);
        
        $success_message = "Redemption approved!";
    }
    
    // Fulfill redemption (HR/Admin)
    if (isset($_POST['fulfill_redemption']) && $is_hr) {
        $redemption_id = $_POST['redemption_id'];
        $delivery_method = $_POST['delivery_method'];
        $tracking_number = $_POST['tracking_number'];
        
        $stmt = $pdo->prepare("
            UPDATE incentive_redemptions 
            SET status = 'fulfilled', fulfilled_by = ?, fulfilled_at = NOW(),
                delivery_method = ?, tracking_number = ?
            WHERE id = ?
        ");
        $stmt->execute([$user_id, $delivery_method, $tracking_number, $redemption_id]);
        
        $success_message = "Redemption fulfilled!";
    }
    
    // Update budget (Finance/HR)
    if (isset($_POST['update_budget']) && ($is_hr || $is_finance)) {
        $year = $_POST['year'];
        $quarter = $_POST['quarter'] ?: null;
        $month = $_POST['month'] ?: null;
        $department = $_POST['department'];
        $total_budget = $_POST['total_budget'];
        
        // Check if exists
        $stmt = $pdo->prepare("
            SELECT id FROM incentive_budget_tracking
            WHERE year = ? AND (quarter = ? OR (quarter IS NULL AND ? IS NULL))
            AND (month = ? OR (month IS NULL AND ? IS NULL)) AND department = ?
        ");
        $stmt->execute([$year, $quarter, $quarter, $month, $month, $department]);
        
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                UPDATE incentive_budget_tracking
                SET total_budget = ?, updated_by = ?
                WHERE year = ? AND quarter = ? AND month = ? AND department = ?
            ");
            $stmt->execute([$total_budget, $user_id, $year, $quarter, $month, $department]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO incentive_budget_tracking
                (year, quarter, month, department, total_budget, updated_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$year, $quarter, $month, $department, $total_budget, $user_id]);
        }
        
        $success_message = "Budget updated successfully!";
    }
}

// Get statistics
$stats = [];

// Active programs
$stmt = $pdo->query("SELECT COUNT(*) FROM incentive_programs WHERE status = 'active'");
$stats['active_programs'] = $stmt->fetchColumn();

// Pending approvals
$stmt = $pdo->query("
    SELECT COUNT(*) FROM incentive_eligibility 
    WHERE status = 'pending' AND supervisor_approved = 0
");
$stats['pending_approvals'] = $stmt->fetchColumn();

// Total paid this month
$stmt = $pdo->query("
    SELECT SUM(amount) FROM incentive_payouts 
    WHERE MONTH(payout_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(payout_date) = YEAR(CURRENT_DATE())
");
$stats['monthly_paid'] = $stmt->fetchColumn() ?: 0;

// Total budget remaining
$stmt = $pdo->query("SELECT SUM(total_budget - used_budget) FROM incentive_budget_tracking WHERE year = YEAR(CURRENT_DATE())");
$stats['budget_remaining'] = $stmt->fetchColumn() ?: 0;

// Get active programs
$stmt = $pdo->query("
    SELECT * FROM incentive_programs 
    WHERE status = 'active' 
    ORDER BY created_at DESC
");
$programs = $stmt->fetchAll();

// Get pending eligibility
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        p.program_name,
        p.reward_type,
        p.reward_value,
        nh.position,
        nh.department,
        a.first_name,
        a.last_name,
        a.photo_path
    FROM incentive_eligibility e
    LEFT JOIN incentive_programs p ON e.program_id = p.id
    LEFT JOIN new_hires nh ON e.employee_id = nh.id
    LEFT JOIN job_applications a ON nh.applicant_id = a.id
    WHERE e.status = 'pending' AND e.supervisor_approved = 0
    ORDER BY e.created_at DESC
");
$stmt->execute();
$pending_eligibility = $stmt->fetchAll();

// Get approved payouts pending payment
$stmt = $pdo->prepare("
    SELECT 
        pay.*,
        p.program_name,
        nh.position,
        nh.department,
        a.first_name,
        a.last_name
    FROM incentive_payouts pay
    LEFT JOIN incentive_programs p ON pay.program_id = p.id
    LEFT JOIN new_hires nh ON pay.employee_id = nh.id
    LEFT JOIN job_applications a ON nh.applicant_id = a.id
    WHERE pay.status IN ('pending', 'approved')
    ORDER BY pay.created_at DESC
");
$stmt->execute();
$pending_payouts = $stmt->fetchAll();

// Get redemption requests
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        i.item_name,
        i.category,
        nh.position,
        nh.department,
        a.first_name,
        a.last_name
    FROM incentive_redemptions r
    LEFT JOIN incentive_redeemable_items i ON r.item_id = i.id
    LEFT JOIN new_hires nh ON r.employee_id = nh.id
    LEFT JOIN job_applications a ON nh.applicant_id = a.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
");
$stmt->execute();
$pending_redemptions = $stmt->fetchAll();

// Get points balance for current user's employees (if supervisor) or all (if HR)
$points_data = [];
if ($is_hr) {
    $stmt = $pdo->query("
        SELECT 
            p.*,
            a.first_name,
            a.last_name,
            nh.position,
            nh.department
        FROM incentive_points p
        LEFT JOIN new_hires nh ON p.employee_id = nh.id
        LEFT JOIN job_applications a ON nh.applicant_id = a.id
        ORDER BY p.points_balance DESC
        LIMIT 10
    ");
    $points_data = $stmt->fetchAll();
}

// Get redeemable items
$stmt = $pdo->query("
    SELECT * FROM incentive_redeemable_items 
    WHERE is_active = 1 
    ORDER BY points_required ASC
");
$redeemable_items = $stmt->fetchAll();

// Get budget data
$stmt = $pdo->query("
    SELECT * FROM incentive_budget_tracking 
    WHERE year = YEAR(CURRENT_DATE())
    ORDER BY department, quarter, month
");
$budget_data = $stmt->fetchAll();

// Get current user's employee ID if they are an employee
$current_employee_id = null;
if (!$is_hr && !$is_supervisor) {
    $stmt = $pdo->prepare("SELECT id FROM new_hires WHERE applicant_id IN (SELECT id FROM job_applications WHERE email = ?)");
    $stmt->execute([$_SESSION['email'] ?? '']);
    $current_employee_id = $stmt->fetchColumn();
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

// Helper function to format currency
function formatCurrency($amount, $unit = '₱') {
    return $unit . ' ' . number_format($amount, 2);
}

// Helper function to get status badge
function getStatusBadge($status) {
    $badges = [
        'active' => 'success',
        'pending' => 'warning',
        'eligible' => 'info',
        'approved' => 'primary',
        'paid' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'secondary',
        'expired' => 'dark',
        'fulfilled' => 'success'
    ];
    $class = $badges[$status] ?? 'secondary';
    return "<span class='category-badge badge-$class'>" . ucfirst($status) . "</span>";
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
    --purple: #9b59b6;
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
    content: '🎁';
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
    background: linear-gradient(135deg, var(--success-color) 0%, #2ecc71 100%);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
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

.stat-sub {
    font-size: 12px;
    color: #64748b;
    margin-top: 5px;
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
    overflow-x: auto;
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
    white-space: nowrap;
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

/* Program Cards */
.programs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.program-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
}

.program-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(14, 76, 146, 0.15);
}

.program-card.active {
    border-left: 4px solid var(--success-color);
}

.program-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.program-code {
    font-size: 12px;
    color: #64748b;
    background: #f8fafd;
    padding: 3px 8px;
    border-radius: 12px;
}

.program-category {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.category-performance {
    background: #3498db20;
    color: #3498db;
}

.category-safety {
    background: #27ae6020;
    color: #27ae60;
}

.category-productivity {
    background: #f39c1220;
    color: #f39c12;
}

.category-attendance {
    background: #9b59b620;
    color: #9b59b6;
}

.category-milestone {
    background: var(--gold);
    color: #2c3e50;
}

.program-title {
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 10px;
}

.program-description {
    font-size: 14px;
    color: #64748b;
    line-height: 1.5;
    margin-bottom: 15px;
}

.program-details {
    background: #f8fafd;
    border-radius: 12px;
    padding: 15px;
    margin: 15px 0;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
}

.detail-label {
    color: #64748b;
}

.detail-value {
    font-weight: 600;
    color: #2c3e50;
}

.program-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eef2f6;
}

.program-budget {
    display: flex;
    flex-direction: column;
}

.budget-bar {
    width: 100px;
    height: 6px;
    background: #eef2f6;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 3px;
}

.budget-progress {
    height: 100%;
    background: var(--primary-color);
    border-radius: 3px;
}

/* Tables */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow-x: auto;
    margin-top: 20px;
}

.unique-table {
    width: 100%;
    border-collapse: collapse;
}

.unique-table th {
    text-align: left;
    padding: 15px;
    background: #f8fafd;
    color: #64748b;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.unique-table td {
    padding: 15px;
    border-bottom: 1px solid #eef2f6;
    color: #2c3e50;
    font-size: 14px;
    vertical-align: middle;
}

.unique-table tr:hover td {
    background: #f8fafd;
}

.employee-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.employee-photo {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    object-fit: cover;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

.employee-details {
    flex: 1;
}

.employee-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 3px;
}

.employee-position {
    font-size: 12px;
    color: #64748b;
}

/* Category Badges */
.category-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.badge-success {
    background: #27ae6020;
    color: #27ae60;
}

.badge-warning {
    background: #f39c1220;
    color: #f39c12;
}

.badge-danger {
    background: #e74c3c20;
    color: #e74c3c;
}

.badge-info {
    background: #3498db20;
    color: #3498db;
}

.badge-primary {
    background: var(--primary-transparent);
    color: var(--primary-color);
}

.badge-purple {
    background: #9b59b620;
    color: #9b59b6;
}

.badge-gold {
    background: var(--gold);
    color: #2c3e50;
}

/* Action Buttons */
.action-btn {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    background: #f8fafd;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    margin: 2px;
}

.action-btn:hover {
    background: var(--primary-transparent);
    color: var(--primary-color);
}

.action-btn.approve:hover {
    background: #27ae6020;
    color: #27ae60;
}

.action-btn.reject:hover {
    background: #e74c3c20;
    color: #e74c3c;
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
    color: var(--success-color);
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

/* Forms */
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

.form-hint {
    font-size: 12px;
    color: #64748b;
    margin-top: 5px;
}

/* Points Display */
.points-badge {
    background: linear-gradient(135deg, var(--gold) 0%, #FDB931 100%);
    color: #2c3e50;
    padding: 8px 15px;
    border-radius: 30px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.points-value {
    font-size: 24px;
    font-weight: 700;
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
    padding: 8px 15px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-warning {
    background: linear-gradient(135deg, var(--gold) 0%, #FDB931 100%);
    color: #2c3e50;
    padding: 8px 15px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    padding: 8px 15px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-secondary {
    background: #f8fafd;
    color: var(--primary-color);
    padding: 8px 15px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
}

.btn-secondary:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 11px;
}

.btn-block {
    width: 100%;
    justify-content: center;
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

/* Responsive */
@media (max-width: 768px) {
    .page-header-content {
        flex-direction: column;
        gap: 15px;
        text-align: center;
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
    
    .programs-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- JavaScript -->
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
    // Update URL parameter
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
    
    // Hide all tabs
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabId + '-tab').classList.add('active');
    
    // Activate button
    event.target.classList.add('active');
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-header-content">
        <div class="page-title">
            <i class="fas fa-gift"></i>
            <div>
                <h1><?php echo $page_title; ?></h1>
                <p style="margin: 5px 0 0; opacity: 0.9;">Rewarding performance, safety, and excellence</p>
            </div>
        </div>
        
        <?php if ($is_hr): ?>
        <button class="btn-primary" onclick="openModal('createProgramModal')" style="background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3);">
            <i class="fas fa-plus-circle"></i> New Program
        </button>
        <?php endif; ?>
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

<!-- Statistics Cards -->
<div class="stats-grid-unique">
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-tasks"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Active Programs</span>
            <span class="stat-value"><?php echo $stats['active_programs']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Approvals</span>
            <span class="stat-value"><?php echo $stats['pending_approvals']; ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-coins"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Monthly Payout</span>
            <span class="stat-value"><?php echo formatCurrency($stats['monthly_paid']); ?></span>
        </div>
    </div>
    
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-piggy-bank"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Budget Remaining</span>
            <span class="stat-value"><?php echo formatCurrency($stats['budget_remaining']); ?></span>
        </div>
    </div>
</div>

<!-- Main Tabs -->
<div class="tab-container">
    <div class="tab-header">
        <button class="tab-btn <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>" onclick="switchTab('dashboard')">
            <i class="fas fa-chart-pie"></i> Dashboard
        </button>
        <button class="tab-btn <?php echo $active_tab == 'programs' ? 'active' : ''; ?>" onclick="switchTab('programs')">
            <i class="fas fa-tasks"></i> Programs
        </button>
        <button class="tab-btn <?php echo $active_tab == 'eligibility' ? 'active' : ''; ?>" onclick="switchTab('eligibility')">
            <i class="fas fa-check-circle"></i> Eligibility
        </button>
        <button class="tab-btn <?php echo $active_tab == 'payouts' ? 'active' : ''; ?>" onclick="switchTab('payouts')">
            <i class="fas fa-money-bill-wave"></i> Payouts
        </button>
        <button class="tab-btn <?php echo $active_tab == 'points' ? 'active' : ''; ?>" onclick="switchTab('points')">
            <i class="fas fa-star"></i> Points
        </button>
        <button class="tab-btn <?php echo $active_tab == 'budget' ? 'active' : ''; ?>" onclick="switchTab('budget')">
            <i class="fas fa-chart-line"></i> Budget
        </button>
    </div>
    
    <div class="tab-content">
        <!-- Dashboard Tab -->
        <div id="dashboard-tab" class="tab-pane <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>">
            <!-- Quick Stats Summary -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <!-- Pending Approvals Card -->
                <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                    <h3 style="margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-clock" style="color: var(--warning-color);"></i>
                        Pending Approvals
                    </h3>
                    <?php if (empty($pending_eligibility)): ?>
                    <p style="color: #64748b; text-align: center;">No pending approvals</p>
                    <?php else: ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach (array_slice($pending_eligibility, 0, 5) as $pending): 
                            $name = getEmployeeFullName($pending);
                        ?>
                        <div style="padding: 10px; border-bottom: 1px solid #eef2f6;">
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($name); ?></div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo $pending['program_name']; ?></div>
                            <div style="font-size: 11px; margin-top: 5px;">
                                <?php echo getStatusBadge('pending'); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top: 15px; text-align: center;">
                        <button class="btn-secondary btn-sm" onclick="switchTab('eligibility')">View All</button>
                    </div>
                </div>
                
                <!-- Recent Payouts Card -->
                <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                    <h3 style="margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-money-bill-wave" style="color: var(--success-color);"></i>
                        Recent Payouts
                    </h3>
                    <?php if (empty($pending_payouts)): ?>
                    <p style="color: #64748b; text-align: center;">No recent payouts</p>
                    <?php else: ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach (array_slice($pending_payouts, 0, 5) as $payout): 
                            $name = getEmployeeFullName($payout);
                        ?>
                        <div style="padding: 10px; border-bottom: 1px solid #eef2f6;">
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($name); ?></div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo $payout['program_name']; ?></div>
                            <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                                <span style="font-weight: 600; color: var(--success-color);">
                                    <?php echo formatCurrency($payout['amount']); ?>
                                </span>
                                <?php echo getStatusBadge($payout['status']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top: 15px; text-align: center;">
                        <button class="btn-secondary btn-sm" onclick="switchTab('payouts')">View All</button>
                    </div>
                </div>
                
                <!-- Points Summary Card -->
                <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                    <h3 style="margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-star" style="color: var(--gold);"></i>
                        Top Point Earners
                    </h3>
                    <?php if (empty($points_data)): ?>
                    <p style="color: #64748b; text-align: center;">No points data yet</p>
                    <?php else: ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($points_data as $points): 
                            $name = getEmployeeFullName($points);
                        ?>
                        <div style="padding: 10px; border-bottom: 1px solid #eef2f6;">
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($name); ?></div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo $points['position'] ?? 'Employee'; ?></div>
                            <div style="margin-top: 5px;">
                                <span class="points-badge" style="padding: 4px 10px;">
                                    <i class="fas fa-star"></i> <?php echo $points['points_balance']; ?> pts
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top: 15px; text-align: center;">
                        <button class="btn-secondary btn-sm" onclick="switchTab('points')">View All</button>
                    </div>
                </div>
            </div>
            
            <!-- Active Programs Preview -->
            <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <h3 style="margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-tasks" style="color: var(--primary-color);"></i>
                    Active Incentive Programs
                </h3>
                
                <div class="programs-grid">
                    <?php foreach (array_slice($programs, 0, 3) as $program): 
                        $budget_used_percent = $program['budget_limit'] > 0 ? ($program['budget_used'] / $program['budget_limit']) * 100 : 0;
                        $category_class = 'category-' . $program['category'];
                    ?>
                    <div class="program-card active">
                        <div class="program-header">
                            <span class="program-code"><?php echo $program['program_code']; ?></span>
                            <span class="program-category <?php echo $category_class; ?>">
                                <?php echo ucfirst($program['category']); ?>
                            </span>
                        </div>
                        <div class="program-title"><?php echo htmlspecialchars($program['program_name']); ?></div>
                        <div class="program-description"><?php echo htmlspecialchars(substr($program['description'], 0, 100)) . '...'; ?></div>
                        <div class="program-details">
                            <div class="detail-row">
                                <span class="detail-label">Reward:</span>
                                <span class="detail-value">
                                    <?php echo formatCurrency($program['reward_value']); ?>
                                    <?php if ($program['reward_type'] == 'points'): ?> points<?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Department:</span>
                                <span class="detail-value"><?php echo ucfirst($program['department']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Period:</span>
                                <span class="detail-value"><?php echo date('M d', strtotime($program['start_date'])); ?> - <?php echo $program['end_date'] ? date('M d, Y', strtotime($program['end_date'])) : 'Ongoing'; ?></span>
                            </div>
                        </div>
                        <?php if ($program['budget_limit']): ?>
                        <div class="program-footer">
                            <div class="program-budget">
                                <span style="font-size: 11px; color: #64748b;">Budget: <?php echo formatCurrency($program['budget_used']); ?> / <?php echo formatCurrency($program['budget_limit']); ?></span>
                                <div class="budget-bar">
                                    <div class="budget-progress" style="width: <?php echo min($budget_used_percent, 100); ?>%;"></div>
                                </div>
                            </div>
                            <?php echo getStatusBadge($program['status']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($programs) > 3): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <button class="btn-secondary btn-sm" onclick="switchTab('programs')">View All Programs</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Programs Tab -->
        <div id="programs-tab" class="tab-pane <?php echo $active_tab == 'programs' ? 'active' : ''; ?>">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Incentive Programs</h3>
                <?php if ($is_hr): ?>
                <button class="btn-primary btn-sm" onclick="openModal('createProgramModal')">
                    <i class="fas fa-plus"></i> New Program
                </button>
                <?php endif; ?>
            </div>
            
            <div class="programs-grid">
                <?php if (empty($programs)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                    <i class="fas fa-tasks" style="font-size: 60px; color: #ddd; margin-bottom: 20px;"></i>
                    <h3 style="color: #64748b;">No Programs Found</h3>
                    <p style="color: #94a3b8;">Create your first incentive program to get started.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($programs as $program): 
                        $budget_used_percent = $program['budget_limit'] > 0 ? ($program['budget_used'] / $program['budget_limit']) * 100 : 0;
                        $category_class = 'category-' . $program['category'];
                    ?>
                    <div class="program-card <?php echo $program['status'] == 'active' ? 'active' : ''; ?>">
                        <div class="program-header">
                            <span class="program-code"><?php echo $program['program_code']; ?></span>
                            <span class="program-category <?php echo $category_class; ?>">
                                <?php echo ucfirst($program['category']); ?>
                            </span>
                        </div>
                        <div class="program-title"><?php echo htmlspecialchars($program['program_name']); ?></div>
                        <div class="program-description"><?php echo nl2br(htmlspecialchars($program['description'])); ?></div>
                        
                        <div class="program-details">
                            <div class="detail-row">
                                <span class="detail-label">Eligibility:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($program['eligibility_criteria']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Reward:</span>
                                <span class="detail-value">
                                    <?php 
                                    if ($program['reward_type'] == 'cash_bonus') echo formatCurrency($program['reward_value']);
                                    elseif ($program['reward_type'] == 'points') echo $program['reward_value'] . ' points';
                                    else echo $program['reward_value'] . ' ' . ucfirst(str_replace('_', ' ', $program['reward_type']));
                                    ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Department:</span>
                                <span class="detail-value"><?php echo ucfirst($program['department']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Frequency:</span>
                                <span class="detail-value"><?php echo ucfirst($program['recurring_frequency']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Period:</span>
                                <span class="detail-value">
                                    <?php echo date('M d, Y', strtotime($program['start_date'])); ?>
                                    <?php if ($program['end_date']): ?> - <?php echo date('M d, Y', strtotime($program['end_date'])); ?><?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($program['budget_limit']): ?>
                        <div class="program-footer">
                            <div class="program-budget">
                                <span style="font-size: 11px; color: #64748b;">Budget: <?php echo formatCurrency($program['budget_used']); ?> / <?php echo formatCurrency($program['budget_limit']); ?></span>
                                <div class="budget-bar">
                                    <div class="budget-progress" style="width: <?php echo min($budget_used_percent, 100); ?>%;"></div>
                                </div>
                            </div>
                            <?php echo getStatusBadge($program['status']); ?>
                        </div>
                        <?php else: ?>
                        <div class="program-footer">
                            <span style="font-size: 11px; color: #64748b;">No budget limit</span>
                            <?php echo getStatusBadge($program['status']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($is_supervisor): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #eef2f6;">
                            <button class="btn-secondary btn-sm btn-block" onclick="openModal('runEligibilityModal_<?php echo $program['id']; ?>')">
                                <i class="fas fa-calculator"></i> Run Eligibility Check
                            </button>
                        </div>
                        
                        <!-- Run Eligibility Modal -->
                        <div id="runEligibilityModal_<?php echo $program['id']; ?>" class="modal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3>
                                        <i class="fas fa-calculator" style="color: var(--primary-color);"></i>
                                        Run Eligibility Check
                                    </h3>
                                    <button class="modal-close" onclick="closeModal('runEligibilityModal_<?php echo $program['id']; ?>')">&times;</button>
                                </div>
                                
                                <p>Program: <strong><?php echo htmlspecialchars($program['program_name']); ?></strong></p>
                                
                                <form method="POST">
                                    <input type="hidden" name="program_id" value="<?php echo $program['id']; ?>">
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Period Start</label>
                                            <input type="date" name="period_start" value="<?php echo date('Y-m-01'); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Period End</label>
                                            <input type="date" name="period_end" value="<?php echo date('Y-m-t'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-hint">
                                        This will check all active employees against the program criteria.
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                                        <button type="button" onclick="closeModal('runEligibilityModal_<?php echo $program['id']; ?>')" class="btn-secondary">
                                            Cancel
                                        </button>
                                        <button type="submit" name="run_eligibility" class="btn-primary">
                                            <i class="fas fa-play"></i> Run Check
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Eligibility Tab -->
        <div id="eligibility-tab" class="tab-pane <?php echo $active_tab == 'eligibility' ? 'active' : ''; ?>">
            <h3 style="margin-bottom: 20px;">Pending Eligibility Approvals</h3>
            
            <div class="table-container">
                <table class="unique-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Program</th>
                            <th>Period</th>
                            <th>Score</th>
                            <th>Value</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_eligibility)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 60px 20px;">
                                <i class="fas fa-check-circle" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                                <p>No pending eligibility approvals</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($pending_eligibility as $eligibility): 
                                $name = getEmployeeFullName($eligibility);
                                $initials = getEmployeeInitials($eligibility);
                                $photoPath = !empty($eligibility['photo_path']) && file_exists($eligibility['photo_path']) ? $eligibility['photo_path'] : null;
                            ?>
                            <tr>
                                <td>
                                    <div class="employee-info">
                                        <?php if ($photoPath): ?>
                                            <img src="<?php echo $photoPath; ?>" 
                                                 alt="<?php echo htmlspecialchars($name); ?>"
                                                 class="employee-photo"
                                                 onerror="handleImageError(this)"
                                                 data-initials="<?php echo $initials; ?>"
                                                 loading="lazy">
                                        <?php else: ?>
                                            <div class="employee-photo img-error-fallback">
                                                <?php echo $initials; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="employee-details">
                                            <div class="employee-name"><?php echo htmlspecialchars($name); ?></div>
                                            <div class="employee-position"><?php echo htmlspecialchars($eligibility['position'] ?? 'Employee'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($eligibility['program_name']); ?></td>
                                <td>
                                    <?php echo date('M d', strtotime($eligibility['period_start'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($eligibility['period_end'])); ?>
                                </td>
                                <td>
                                    <span class="category-badge badge-<?php echo $eligibility['eligibility_score'] >= 90 ? 'success' : ($eligibility['eligibility_score'] >= 75 ? 'warning' : 'info'); ?>">
                                        <?php echo $eligibility['eligibility_score']; ?>%
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: var(--success-color);">
                                        <?php echo formatCurrency($eligibility['calculated_value']); ?>
                                    </span>
                                </td>
                                <td><?php echo getStatusBadge($eligibility['status']); ?></td>
                                <td>
                                    <button class="action-btn approve" onclick="openModal('approveModal_<?php echo $eligibility['id']; ?>')">
                                        <i class="fas fa-check"></i> Review
                                    </button>
                                    
                                    <!-- Approve Modal -->
                                    <div id="approveModal_<?php echo $eligibility['id']; ?>" class="modal">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h3>
                                                    <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                                                    Review Eligibility
                                                </h3>
                                                <button class="modal-close" onclick="closeModal('approveModal_<?php echo $eligibility['id']; ?>')">&times;</button>
                                            </div>
                                            
                                            <p><strong><?php echo htmlspecialchars($name); ?></strong> - <?php echo $eligibility['program_name']; ?></p>
                                            
                                            <?php if ($eligibility['criteria_met']): ?>
                                            <div style="background: #f8fafd; border-radius: 12px; padding: 15px; margin: 15px 0;">
                                                <strong>Criteria Met:</strong>
                                                <p style="margin-top: 5px;"><?php echo nl2br(htmlspecialchars($eligibility['criteria_met'])); ?></p>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <form method="POST">
                                                <input type="hidden" name="eligibility_id" value="<?php echo $eligibility['id']; ?>">
                                                
                                                <div class="form-group">
                                                    <label>Comments</label>
                                                    <textarea name="comments" placeholder="Add comments..."></textarea>
                                                </div>
                                                
                                                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                                    <button type="submit" name="action" value="reject" class="btn-danger">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                    <button type="submit" name="action" value="approve" class="btn-success">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Payouts Tab -->
        <div id="payouts-tab" class="tab-pane <?php echo $active_tab == 'payouts' ? 'active' : ''; ?>">
            <h3 style="margin-bottom: 20px;">Pending Payouts</h3>
            
            <div class="table-container">
                <table class="unique-table">
                    <thead>
                        <tr>
                            <th>Payout #</th>
                            <th>Employee</th>
                            <th>Program</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_payouts)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 60px 20px;">
                                <i class="fas fa-money-bill-wave" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                                <p>No pending payouts</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($pending_payouts as $payout): 
                                $name = getEmployeeFullName($payout);
                            ?>
                            <tr>
                                <td><span class="program-code"><?php echo $payout['payout_number']; ?></span></td>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-details">
                                            <div class="employee-name"><?php echo htmlspecialchars($name); ?></div>
                                            <div class="employee-position"><?php echo htmlspecialchars($payout['position'] ?? 'Employee'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($payout['program_name']); ?></td>
                                <td>
                                    <span style="font-weight: 600; color: var(--success-color);">
                                        <?php echo formatCurrency($payout['amount']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($payout['payout_date'])); ?></td>
                                <td><?php echo ucfirst($payout['payout_method']); ?></td>
                                <td><?php echo getStatusBadge($payout['status']); ?></td>
                                <td>
                                    <?php if ($is_finance && $payout['status'] == 'approved'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="payout_id" value="<?php echo $payout['id']; ?>">
                                        <button type="submit" name="mark_paid" class="btn-success btn-sm">
                                            <i class="fas fa-check"></i> Mark Paid
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Points Tab -->
        <div id="points-tab" class="tab-pane <?php echo $active_tab == 'points' ? 'active' : ''; ?>">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <!-- Left Column - Points Balance -->
                <div>
                    <h3 style="margin-bottom: 20px;">Points Leaderboard</h3>
                    
                    <div class="table-container">
                        <table class="unique-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Points Earned</th>
                                    <th>Points Redeemed</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($points_data)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 60px 20px;">
                                        <i class="fas fa-star" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                                        <p>No points data yet</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($points_data as $points): 
                                        $name = getEmployeeFullName($points);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="employee-info">
                                                <div class="employee-details">
                                                    <div class="employee-name"><?php echo htmlspecialchars($name); ?></div>
                                                    <div class="employee-position"><?php echo htmlspecialchars($points['position'] ?? 'Employee'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo ucfirst($points['department'] ?? 'N/A'); ?></td>
                                        <td><?php echo $points['points_earned']; ?></td>
                                        <td><?php echo $points['points_redeemed']; ?></td>
                                        <td>
                                            <span class="points-badge" style="padding: 4px 10px;">
                                                <i class="fas fa-star"></i> <?php echo $points['points_balance']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Right Column - Redeem Items -->
                <div>
                    <h3 style="margin-bottom: 20px;">Redeem Items</h3>
                    
                    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                        <?php if (empty($redeemable_items)): ?>
                        <p style="color: #64748b; text-align: center;">No redeemable items</p>
                        <?php else: ?>
                            <?php foreach ($redeemable_items as $item): ?>
                            <div style="padding: 15px; border-bottom: 1px solid #eef2f6;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                        <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($item['description']); ?></div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="points-badge" style="padding: 4px 10px;">
                                            <i class="fas fa-star"></i> <?php echo $item['points_required']; ?>
                                        </span>
                                        <?php if ($item['stock'] < 10): ?>
                                        <div style="font-size: 11px; color: var(--danger-color); margin-top: 3px;">
                                            Only <?php echo $item['stock']; ?> left
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($current_employee_id): ?>
                                <form method="POST" style="margin-top: 10px;">
                                    <input type="hidden" name="employee_id" value="<?php echo $current_employee_id; ?>">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <div style="display: flex; gap: 10px;">
                                        <input type="number" name="quantity" value="1" min="1" max="<?php echo min($item['stock'], 5); ?>" style="width: 70px; padding: 8px;">
                                        <button type="submit" name="redeem_points" class="btn-warning btn-sm" style="flex: 1;">
                                            <i class="fas fa-exchange-alt"></i> Redeem
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pending Redemptions (HR view) -->
                    <?php if ($is_hr && !empty($pending_redemptions)): ?>
                    <h3 style="margin: 20px 0;">Pending Redemptions</h3>
                    
                    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                        <?php foreach ($pending_redemptions as $redemption): 
                            $name = getEmployeeFullName($redemption);
                        ?>
                        <div style="padding: 15px; border-bottom: 1px solid #eef2f6;">
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($name); ?></div>
                            <div style="font-size: 13px;"><?php echo $redemption['item_name']; ?> x<?php echo $redemption['quantity']; ?></div>
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <button class="btn-success btn-sm" onclick="openModal('approveRedemptionModal_<?php echo $redemption['id']; ?>')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </div>
                        </div>
                        
                        <!-- Approve Redemption Modal -->
                        <div id="approveRedemptionModal_<?php echo $redemption['id']; ?>" class="modal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3>
                                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                                        Fulfill Redemption
                                    </h3>
                                    <button class="modal-close" onclick="closeModal('approveRedemptionModal_<?php echo $redemption['id']; ?>')">&times;</button>
                                </div>
                                
                                <p><strong><?php echo htmlspecialchars($name); ?></strong> - <?php echo $redemption['item_name']; ?> x<?php echo $redemption['quantity']; ?></p>
                                
                                <form method="POST">
                                    <input type="hidden" name="redemption_id" value="<?php echo $redemption['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label>Delivery Method</label>
                                        <select name="delivery_method" required>
                                            <option value="email">Email</option>
                                            <option value="pickup">Pickup at HR</option>
                                            <option value="delivery">Delivery</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Tracking Number (Optional)</label>
                                        <input type="text" name="tracking_number" placeholder="Enter tracking number if applicable">
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                        <button type="button" onclick="closeModal('approveRedemptionModal_<?php echo $redemption['id']; ?>')" class="btn-secondary">
                                            Cancel
                                        </button>
                                        <button type="submit" name="fulfill_redemption" class="btn-success">
                                            <i class="fas fa-check"></i> Fulfill
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Budget Tab -->
        <div id="budget-tab" class="tab-pane <?php echo $active_tab == 'budget' ? 'active' : ''; ?>">
            <h3 style="margin-bottom: 20px;">Budget Tracking</h3>
            
            <?php if ($is_hr || $is_finance): ?>
            <button class="btn-primary btn-sm" onclick="openModal('updateBudgetModal')" style="margin-bottom: 20px;">
                <i class="fas fa-plus"></i> Update Budget
            </button>
            <?php endif; ?>
            
            <div class="table-container">
                <table class="unique-table">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Quarter</th>
                            <th>Month</th>
                            <th>Department</th>
                            <th>Total Budget</th>
                            <th>Allocated</th>
                            <th>Used</th>
                            <th>Remaining</th>
                            <th>Usage %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($budget_data)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 60px 20px;">
                                <i class="fas fa-chart-line" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                                <p>No budget data for <?php echo date('Y'); ?></p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($budget_data as $budget): 
                                $usage_percent = $budget['total_budget'] > 0 ? ($budget['used_budget'] / $budget['total_budget']) * 100 : 0;
                            ?>
                            <tr>
                                <td><?php echo $budget['year']; ?></td>
                                <td><?php echo $budget['quarter'] ? 'Q' . $budget['quarter'] : '-'; ?></td>
                                <td><?php echo $budget['month'] ? date('F', mktime(0, 0, 0, $budget['month'], 1)) : '-'; ?></td>
                                <td><?php echo ucfirst($budget['department']); ?></td>
                                <td><?php echo formatCurrency($budget['total_budget']); ?></td>
                                <td><?php echo formatCurrency($budget['allocated_budget']); ?></td>
                                <td><?php echo formatCurrency($budget['used_budget']); ?></td>
                                <td><?php echo formatCurrency($budget['remaining_budget']); ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 80px; height: 6px; background: #eef2f6; border-radius: 3px;">
                                            <div style="width: <?php echo min($usage_percent, 100); ?>%; height: 100%; background: <?php echo $usage_percent > 90 ? 'var(--danger-color)' : ($usage_percent > 75 ? 'var(--warning-color)' : 'var(--success-color)'); ?>; border-radius: 3px;"></div>
                                        </div>
                                        <span style="font-size: 12px;"><?php echo number_format($usage_percent, 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Program Modal -->
<div id="createProgramModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-plus-circle" style="color: var(--success-color);"></i>
                Create Incentive Program
            </h3>
            <button class="modal-close" onclick="closeModal('createProgramModal')">&times;</button>
        </div>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Program Code</label>
                    <input type="text" name="program_code" placeholder="e.g., SAFE-001" required>
                </div>
                <div class="form-group">
                    <label>Program Name</label>
                    <input type="text" name="program_name" placeholder="e.g., Safety Excellence Bonus" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="performance">Performance</option>
                        <option value="safety">Safety</option>
                        <option value="productivity">Productivity</option>
                        <option value="attendance">Attendance</option>
                        <option value="milestone">Milestone</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" required>
                        <option value="all">All Departments</option>
                        <option value="driver">Driver</option>
                        <option value="warehouse">Warehouse</option>
                        <option value="logistics">Logistics</option>
                        <option value="admin">Admin</option>
                        <option value="management">Management</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Describe the incentive program..." required></textarea>
            </div>
            
            <div class="form-group">
                <label>Eligibility Criteria</label>
                <textarea name="eligibility_criteria" placeholder="e.g., No safety incidents for 90 days..." required></textarea>
            </div>
            
            <div class="form-group">
                <label>Calculation Method</label>
                <textarea name="calculation_method" placeholder="e.g., Based on performance review score..." required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Reward Type</label>
                    <select name="reward_type" required>
                        <option value="cash_bonus">Cash Bonus</option>
                        <option value="gift_card">Gift Card</option>
                        <option value="extra_leave">Extra Leave Day</option>
                        <option value="fuel_allowance">Fuel Allowance</option>
                        <option value="certificate">Certificate Only</option>
                        <option value="points">Points</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reward Value</label>
                    <input type="number" name="reward_value" step="0.01" placeholder="Amount or points" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Budget Limit (Optional)</label>
                    <input type="number" name="budget_limit" step="0.01" placeholder="Total budget for this program">
                </div>
                <div class="form-group">
                    <label>Recurring Frequency</label>
                    <select name="recurring_frequency" required>
                        <option value="one_time">One Time</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="annual">Annual</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>End Date (Optional)</label>
                    <input type="date" name="end_date">
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeModal('createProgramModal')" class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" name="create_program" class="btn-primary">
                    <i class="fas fa-save"></i> Create Program
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update Budget Modal -->
<div id="updateBudgetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-chart-line" style="color: var(--primary-color);"></i>
                Update Budget
            </h3>
            <button class="modal-close" onclick="closeModal('updateBudgetModal')">&times;</button>
        </div>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" value="<?php echo date('Y'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Quarter (Optional)</label>
                    <select name="quarter">
                        <option value="">-- None --</option>
                        <option value="1">Q1</option>
                        <option value="2">Q2</option>
                        <option value="3">Q3</option>
                        <option value="4">Q4</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Month (Optional)</label>
                    <select name="month">
                        <option value="">-- None --</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" required>
                        <option value="all">All Departments</option>
                        <option value="driver">Driver</option>
                        <option value="warehouse">Warehouse</option>
                        <option value="logistics">Logistics</option>
                        <option value="admin">Admin</option>
                        <option value="management">Management</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Total Budget</label>
                <input type="number" name="total_budget" step="0.01" placeholder="Enter budget amount" required>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeModal('updateBudgetModal')" class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" name="update_budget" class="btn-primary">
                    <i class="fas fa-save"></i> Update Budget
                </button>
            </div>
        </form>
    </div>
</div>