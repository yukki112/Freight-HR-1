<?php
// modules/applicant/screening-evaluation.php
$page_title = "Screening & Evaluation";

// Create screening_evaluations table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS screening_evaluations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            applicant_id INT NOT NULL,
            screening_score INT,
            qualification_match INT,
            screening_notes TEXT,
            evaluated_by INT,
            evaluation_date DATETIME,
            screening_result ENUM('pass', 'fail', 'pending') DEFAULT 'pending',
            status_updated_to VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (applicant_id) REFERENCES job_applications(id) ON DELETE CASCADE,
            FOREIGN KEY (evaluated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_applicant (applicant_id),
            INDEX idx_result (screening_result)
        )
    ");
} catch (Exception $e) {
    // Table might already exist
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_evaluation'])) {
        $applicant_id = $_POST['applicant_id'];
        $screening_score = $_POST['screening_score'];
        $qualification_match = $_POST['qualification_match'];
        $screening_notes = $_POST['screening_notes'];
        $screening_result = $_POST['screening_result'];
        $update_status = isset($_POST['update_status']) ? 1 : 0;
        
        // Check if evaluation already exists
        $stmt = $pdo->prepare("SELECT id FROM screening_evaluations WHERE applicant_id = ?");
        $stmt->execute([$applicant_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing evaluation
            $stmt = $pdo->prepare("
                UPDATE screening_evaluations 
                SET screening_score = ?, qualification_match = ?, screening_notes = ?, 
                    evaluated_by = ?, evaluation_date = NOW(), screening_result = ?
                WHERE applicant_id = ?
            ");
            $stmt->execute([$screening_score, $qualification_match, $screening_notes, $_SESSION['user_id'], $screening_result, $applicant_id]);
        } else {
            // Insert new evaluation
            $stmt = $pdo->prepare("
                INSERT INTO screening_evaluations 
                (applicant_id, screening_score, qualification_match, screening_notes, evaluated_by, evaluation_date, screening_result)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$applicant_id, $screening_score, $qualification_match, $screening_notes, $_SESSION['user_id'], $screening_result]);
        }
        
        // Update applicant status if checkbox is checked
        if ($update_status) {
            $new_status = ($screening_result === 'pass') ? 'shortlisted' : 'rejected';
            $stmt = $pdo->prepare("UPDATE job_applications SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $applicant_id]);
            
            // Add note about status change
            $status_note = "[" . date('Y-m-d H:i') . "] Status updated to " . $new_status . " based on screening evaluation.";
            $stmt = $pdo->prepare("UPDATE job_applications SET notes = CONCAT(IFNULL(notes, ''), '\n', ?) WHERE id = ?");
            $stmt->execute([$status_note, $applicant_id]);
            
            logActivity($pdo, $_SESSION['user_id'], 'update_applicant_status', "Updated applicant #$applicant_id status to $new_status via screening");
        }
        
        logActivity($pdo, $_SESSION['user_id'], 'screening_evaluation', "Saved screening evaluation for applicant #$applicant_id");
        $success_message = "Screening evaluation saved successfully!";
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$search_filter = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Get all applicants with their screening status
$query = "
    SELECT 
        a.*,
        jp.title as job_title,
        jp.job_code,
        jp.department,
        jp.experience_required,
        jp.education_required,
        jp.license_required,
        se.id as evaluation_id,
        se.screening_score,
        se.qualification_match,
        se.screening_notes,
        se.screening_result,
        se.evaluation_date,
        u.full_name as evaluator_name,
        CASE 
            WHEN se.id IS NOT NULL THEN 
                CASE 
                    WHEN se.screening_result = 'pass' THEN 'Passed'
                    WHEN se.screening_result = 'fail' THEN 'Failed'
                    ELSE 'Evaluated'
                END
            ELSE 'Pending'
        END as screening_status
    FROM job_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN screening_evaluations se ON a.id = se.applicant_id
    LEFT JOIN users u ON se.evaluated_by = u.id
    WHERE 1=1
";

$params = [];

// Status filter
if ($status_filter === 'pending') {
    $query .= " AND se.id IS NULL";
} elseif ($status_filter === 'evaluated') {
    $query .= " AND se.id IS NOT NULL";
} elseif ($status_filter === 'passed') {
    $query .= " AND se.screening_result = 'pass'";
} elseif ($status_filter === 'failed') {
    $query .= " AND se.screening_result = 'fail'";
}

// Department filter
if (!empty($department_filter)) {
    $query .= " AND jp.department = ?";
    $params[] = $department_filter;
}

// Search filter
if (!empty($search_filter)) {
    $query .= " AND (a.first_name LIKE ? OR a.last_name LIKE ? OR a.application_number LIKE ? OR a.email LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY a.applied_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

// Get applicant for evaluation modal
$selected_applicant = null;
$existing_evaluation = null;

if (isset($_GET['evaluate'])) {
    $applicant_id = (int)$_GET['evaluate'];
    
    // Get applicant details
    $stmt = $pdo->prepare("
        SELECT a.*, jp.title as job_title, jp.job_code, jp.department,
               jp.experience_required, jp.education_required, jp.license_required,
               jp.salary_min, jp.salary_max
        FROM job_applications a
        LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
        WHERE a.id = ?
    ");
    $stmt->execute([$applicant_id]);
    $selected_applicant = $stmt->fetch();
    
    if ($selected_applicant) {
        // Get existing evaluation if any
        $stmt = $pdo->prepare("
            SELECT se.*, u.full_name as evaluator_name
            FROM screening_evaluations se
            LEFT JOIN users u ON se.evaluated_by = u.id
            WHERE se.applicant_id = ?
        ");
        $stmt->execute([$applicant_id]);
        $existing_evaluation = $stmt->fetch();
    }
}

// Get statistics
$stats = [];

// Total pending screening
$stmt = $pdo->query("
    SELECT COUNT(*) FROM job_applications a
    LEFT JOIN screening_evaluations se ON a.id = se.applicant_id
    WHERE se.id IS NULL
");
$stats['pending'] = $stmt->fetchColumn();

// Total evaluated
$stmt = $pdo->query("
    SELECT COUNT(*) FROM screening_evaluations
");
$stats['evaluated'] = $stmt->fetchColumn();

// Passed
$stmt = $pdo->query("
    SELECT COUNT(*) FROM screening_evaluations WHERE screening_result = 'pass'
");
$stats['passed'] = $stmt->fetchColumn();

// Failed
$stmt = $pdo->query("
    SELECT COUNT(*) FROM screening_evaluations WHERE screening_result = 'fail'
");
$stats['failed'] = $stmt->fetchColumn();

// By department
$stmt = $pdo->query("
    SELECT jp.department, COUNT(*) as total,
           SUM(CASE WHEN se.id IS NOT NULL THEN 1 ELSE 0 END) as evaluated,
           SUM(CASE WHEN se.id IS NULL THEN 1 ELSE 0 END) as pending
    FROM job_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    LEFT JOIN screening_evaluations se ON a.id = se.applicant_id
    WHERE jp.department IS NOT NULL
    GROUP BY jp.department
    ORDER BY total DESC
");
$dept_stats = $stmt->fetchAll();

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department FROM job_postings WHERE department IS NOT NULL ORDER BY department");
$departments = $stmt->fetchAll();

// Helper function to get applicant photo or fallback to initials
function getApplicantPhoto($applicant) {
    if (!empty($applicant['photo_path']) && file_exists($applicant['photo_path'])) {
        return htmlspecialchars($applicant['photo_path']);
    }
    return null;
}
?>

<style>
:root {
    --primary-color: #0e4c92;
    --primary-light: #1e5ca8;
    --primary-dark: #0a3a70;
    --primary-transparent: rgba(14, 76, 146, 0.1);
    --primary-transparent-2: rgba(14, 76, 146, 0.2);
    --success-color: #27ae60;
    --warning-color: #f39c12;
    --danger-color: #e74c3c;
    --info-color: #3498db;
}

/* Page Header */
.page-header-unique {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title h1 {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.page-title i {
    font-size: 28px;
    color: #0e4c92;
    background: rgba(14, 76, 146, 0.1);
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
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.2);
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

/* Department Stats */
.dept-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.dept-card {
    background: white;
    border-radius: 15px;
    padding: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
}

.dept-icon {
    width: 45px;
    height: 45px;
    background: rgba(14, 76, 146, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0e4c92;
    font-size: 20px;
}

.dept-info {
    flex: 1;
}

.dept-name {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
    text-transform: capitalize;
}

.dept-progress {
    height: 6px;
    background: #eef2f6;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 5px;
}

.dept-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #0e4c92, #4086e4);
    border-radius: 3px;
}

.dept-stats-text {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #64748b;
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
    color: #0e4c92;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-item label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-item input,
.filter-item select {
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.filter-item input:focus,
.filter-item select:focus {
    outline: none;
    border-color: #0e4c92;
    box-shadow: 0 0 0 3px rgba(14, 76, 146, 0.1);
}

.filter-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    overflow-x: auto;
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

/* Avatar/Photo Styles */
.applicant-photo-medium {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 8px rgba(14, 76, 146, 0.2);
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

.applicant-photo-medium[src=""], 
.applicant-photo-medium:not([src]) {
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-fallback-medium {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

/* Modal Avatar */
.modal-applicant-photo {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 5px 15px rgba(14, 76, 146, 0.3);
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 24px;
}

.modal-photo-fallback {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 24px;
    box-shadow: 0 5px 15px rgba(14, 76, 146, 0.3);
}

/* Status Badges */
.category-badge {
    padding: 6px 12px;
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

.badge-secondary {
    background: #95a5a620;
    color: #7f8c8d;
}

/* Score Badge */
.score-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
}

.score-high {
    background: #27ae6020;
    color: #27ae60;
}

.score-medium {
    background: #f39c1220;
    color: #f39c12;
}

.score-low {
    background: #e74c3c20;
    color: #e74c3c;
}

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    padding: 10px 20px;
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

.btn-secondary {
    background: #f8fafd;
    color: #0e4c92;
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-secondary:hover {
    background: #0e4c92;
    color: white;
    border-color: #0e4c92;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

.btn-success {
    background: #27ae60;
    color: white;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-success:hover {
    background: #219a52;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
}

/* Alert Messages */
.alert-success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid #c3e6cb;
}

/* Modal Styles */
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
    max-width: 700px;
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
    color: #e74c3c;
}

/* Applicant Info in Modal */
.modal-applicant-info {
    background: #f8fafd;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.modal-details {
    flex: 1;
}

.modal-details h4 {
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 5px 0;
}

.modal-details p {
    margin: 3px 0;
    font-size: 14px;
    color: #64748b;
}

.modal-details i {
    color: #0e4c92;
    width: 20px;
}

/* Job Requirements */
.requirements-box {
    background: #f8fafd;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}

.requirements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.requirement-item {
    background: white;
    border-radius: 12px;
    padding: 12px;
    border: 1px solid #eef2f6;
}

.requirement-label {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.requirement-value {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
}

/* Evaluation Form */
.evaluation-form {
    margin-top: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #0e4c92;
    box-shadow: 0 0 0 3px rgba(14, 76, 146, 0.1);
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 15px 0;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #0e4c92;
}

/* Score Input with Range */
.score-input-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.score-input-group input[type="range"] {
    flex: 1;
    height: 6px;
    -webkit-appearance: none;
    background: #eef2f6;
    border-radius: 3px;
}

.score-input-group input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    background: #0e4c92;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(14, 76, 146, 0.3);
}

.score-value {
    min-width: 50px;
    text-align: center;
    font-weight: 600;
    color: #0e4c92;
}

/* Qualification Match */
.match-indicator {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 10px;
}

.match-bar {
    flex: 1;
    height: 8px;
    background: #eef2f6;
    border-radius: 4px;
    overflow: hidden;
}

.match-progress {
    height: 100%;
    background: linear-gradient(90deg, #0e4c92, #4086e4);
    border-radius: 4px;
}

/* Modal Footer */
.modal-footer {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 2px solid #eef2f6;
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    flex-wrap: wrap;
}

/* Image error handling */
.img-error-fallback-medium,
.modal-img-error-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    font-weight: 600;
}

.img-error-fallback-medium {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    font-size: 16px;
}

.modal-img-error-fallback {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    font-size: 24px;
    box-shadow: 0 5px 15px rgba(14, 76, 146, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-applicant-info {
        flex-direction: column;
        text-align: center;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .requirements-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer form,
    .modal-footer a,
    .modal-footer button {
        width: 100%;
        justify-content: center;
    }
}
</style>

<!-- JavaScript for handling image errors -->
<script>
function handleImageError(img) {
    // Don't process if already handled
    if (img.getAttribute('data-error-handled') === 'true') return;
    
    // Mark as handled
    img.setAttribute('data-error-handled', 'true');
    
    // Get the initials from data attribute
    const initials = img.getAttribute('data-initials') || '?';
    const isModal = img.classList.contains('modal-applicant-photo');
    
    // Create fallback element
    const parent = img.parentNode;
    const fallback = document.createElement('div');
    fallback.className = isModal ? 'modal-img-error-fallback' : 'img-error-fallback-medium';
    fallback.textContent = initials;
    
    // Replace image with fallback
    parent.replaceChild(fallback, img);
}

function handleModalImageError(img) {
    handleImageError(img);
}
</script>

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-title">
        <i class="fas fa-clipboard-check"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div>
        <a href="?page=applicant&subpage=screening-evaluation&status=pending" class="btn-secondary btn-sm <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Pending
        </a>
        <a href="?page=applicant&subpage=screening-evaluation&status=evaluated" class="btn-secondary btn-sm <?php echo $status_filter == 'evaluated' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i> Evaluated
        </a>
        <a href="?page=applicant&subpage=screening-evaluation&status=passed" class="btn-secondary btn-sm <?php echo $status_filter == 'passed' ? 'active' : ''; ?>">
            <i class="fas fa-check"></i> Passed
        </a>
        <a href="?page=applicant&subpage=screening-evaluation&status=failed" class="btn-secondary btn-sm <?php echo $status_filter == 'failed' ? 'active' : ''; ?>">
            <i class="fas fa-times"></i> Failed
        </a>
    </div>
</div>

<?php if (isset($success_message)): ?>
<div class="alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid-unique">
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Screening</span>
            <span class="stat-value"><?php echo $stats['pending']; ?></span>
        </div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Evaluated</span>
            <span class="stat-value"><?php echo $stats['evaluated']; ?></span>
        </div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-check"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Passed</span>
            <span class="stat-value"><?php echo $stats['passed']; ?></span>
        </div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-times"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Failed</span>
            <span class="stat-value"><?php echo $stats['failed']; ?></span>
        </div>
    </div>
</div>

<!-- Department Statistics -->
<?php if (!empty($dept_stats)): ?>
<div class="dept-stats">
    <?php foreach ($dept_stats as $dept): ?>
    <div class="dept-card">
        <div class="dept-icon">
            <?php
            $icon = 'fa-building';
            if ($dept['department'] == 'driver') $icon = 'fa-truck';
            elseif ($dept['department'] == 'warehouse') $icon = 'fa-warehouse';
            elseif ($dept['department'] == 'logistics') $icon = 'fa-route';
            elseif ($dept['department'] == 'admin') $icon = 'fa-user-tie';
            elseif ($dept['department'] == 'management') $icon = 'fa-chart-line';
            ?>
            <i class="fas <?php echo $icon; ?>"></i>
        </div>
        <div class="dept-info">
            <div class="dept-name"><?php echo ucfirst($dept['department']); ?></div>
            <div class="dept-progress">
                <div class="dept-progress-bar" style="width: <?php echo ($dept['evaluated'] / max($dept['total'], 1)) * 100; ?>%"></div>
            </div>
            <div class="dept-stats-text">
                <span><i class="fas fa-check-circle" style="color: #27ae60;"></i> <?php echo $dept['evaluated']; ?></span>
                <span><i class="fas fa-clock" style="color: #f39c12;"></i> <?php echo $dept['pending']; ?></span>
                <span>Total: <?php echo $dept['total']; ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Applicants
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="applicant">
        <input type="hidden" name="subpage" value="screening-evaluation">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Screening Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Applicants</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Screening</option>
                    <option value="evaluated" <?php echo $status_filter == 'evaluated' ? 'selected' : ''; ?>>Evaluated</option>
                    <option value="passed" <?php echo $status_filter == 'passed' ? 'selected' : ''; ?>>Passed</option>
                    <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Department</label>
                <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['department']; ?>" <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                        <?php echo ucfirst($dept['department']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, Email, or Application #" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=applicant&subpage=screening-evaluation" class="btn-secondary">
                <i class="fas fa-times"></i> Clear Filters
            </a>
            <button type="submit" class="btn-primary">
                <i class="fas fa-search"></i> Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Applicants Table -->
<div class="table-container">
    <table class="unique-table">
        <thead>
            <tr>
                <th>Applicant</th>
                <th>Position</th>
                <th>Applied</th>
                <th>Screening Score</th>
                <th>Match %</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($applicants)): ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 60px 20px; color: #95a5a6;">
                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p>No applicants found</p>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($applicants as $applicant): 
                    $photoPath = getApplicantPhoto($applicant);
                    $firstName = $applicant['first_name'] ?? '';
                    $lastName = $applicant['last_name'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
                    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <?php if ($photoPath): ?>
                                <img src="<?php echo $photoPath; ?>" 
                                     alt="<?php echo htmlspecialchars($fullName); ?>"
                                     class="applicant-photo-medium"
                                     onerror="handleImageError(this)"
                                     data-initials="<?php echo $initials; ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="photo-fallback-medium">
                                    <?php echo $initials; ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($fullName); ?></strong>
                                <div style="font-size: 11px; color: #64748b;">#<?php echo $applicant['application_number']; ?></div>
                                <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($applicant['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($applicant['job_title'])): ?>
                            <strong><?php echo htmlspecialchars($applicant['job_title']); ?></strong>
                            <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($applicant['job_code']); ?></div>
                            <div style="font-size: 11px; color: #64748b; text-transform: capitalize;"><?php echo $applicant['department']; ?></div>
                        <?php else: ?>
                            <span style="color: #64748b;">General Application</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span><?php echo date('M d, Y', strtotime($applicant['applied_at'])); ?></span>
                        <div style="font-size: 11px; color: #64748b;"><?php echo timeAgo($applicant['applied_at']); ?></div>
                    </td>
                    <td>
                        <?php if ($applicant['screening_score'] !== null): ?>
                            <?php
                            $score_class = 'score-high';
                            if ($applicant['screening_score'] < 40) $score_class = 'score-low';
                            elseif ($applicant['screening_score'] < 70) $score_class = 'score-medium';
                            ?>
                            <span class="score-badge <?php echo $score_class; ?>">
                                <?php echo $applicant['screening_score']; ?>/100
                            </span>
                        <?php else: ?>
                            <span style="color: #94a3b8;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($applicant['qualification_match'] !== null): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-weight: 600;"><?php echo $applicant['qualification_match']; ?>%</span>
                                <div style="width: 50px; height: 6px; background: #eef2f6; border-radius: 3px;">
                                    <div style="width: <?php echo $applicant['qualification_match']; ?>%; height: 100%; background: linear-gradient(90deg, #0e4c92, #4086e4); border-radius: 3px;"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <span style="color: #94a3b8;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        if ($applicant['screening_status'] == 'Passed'): ?>
                            <span class="category-badge badge-success">
                                <i class="fas fa-check-circle"></i> Passed
                            </span>
                        <?php elseif ($applicant['screening_status'] == 'Failed'): ?>
                            <span class="category-badge badge-danger">
                                <i class="fas fa-times-circle"></i> Failed
                            </span>
                        <?php elseif ($applicant['screening_status'] == 'Evaluated'): ?>
                            <span class="category-badge badge-info">
                                <i class="fas fa-check"></i> Evaluated
                            </span>
                        <?php else: ?>
                            <span class="category-badge badge-warning">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=applicant&subpage=screening-evaluation&evaluate=<?php echo $applicant['id']; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?>" class="btn-primary btn-sm" style="text-decoration: none;">
                            <i class="fas fa-clipboard-check"></i> 
                            <?php echo $applicant['evaluation_id'] ? 'Re-evaluate' : 'Evaluate'; ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Evaluation Modal -->
<?php if ($selected_applicant): ?>
<div id="evaluationModal" class="modal active">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-check" style="color: #0e4c92;"></i> Screening Evaluation</h3>
            <a href="?page=applicant&subpage=screening-evaluation&status=<?php echo $status_filter; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?>" class="modal-close">&times;</a>
        </div>
        
        <!-- Applicant Info -->
        <div class="modal-applicant-info">
            <?php 
            $photoPath = getApplicantPhoto($selected_applicant);
            $firstName = $selected_applicant['first_name'] ?? '';
            $lastName = $selected_applicant['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
            
            if ($photoPath): ?>
                <img src="<?php echo $photoPath; ?>" 
                     alt="<?php echo htmlspecialchars($fullName); ?>"
                     class="modal-applicant-photo"
                     onerror="handleModalImageError(this)"
                     data-initials="<?php echo $initials; ?>"
                     loading="lazy">
            <?php else: ?>
                <div class="modal-photo-fallback">
                    <?php echo $initials; ?>
                </div>
            <?php endif; ?>
            
            <div class="modal-details">
                <h4><?php echo htmlspecialchars($fullName); ?></h4>
                <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($selected_applicant['job_title'] ?? $selected_applicant['position_applied'] ?? 'General Application'); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($selected_applicant['email']); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($selected_applicant['phone'] ?? 'N/A'); ?></p>
                <p><i class="fas fa-hashtag"></i> Application #: <?php echo $selected_applicant['application_number']; ?></p>
            </div>
        </div>
        
        <!-- Job Requirements -->
        <?php if (!empty($selected_applicant['job_title'])): ?>
        <div class="requirements-box">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                <i class="fas fa-clipboard-list" style="color: #0e4c92;"></i>
                <h4 style="margin: 0; font-size: 16px;">Job Requirements</h4>
            </div>
            <div class="requirements-grid">
                <?php if (!empty($selected_applicant['experience_required'])): ?>
                <div class="requirement-item">
                    <div class="requirement-label">Experience</div>
                    <div class="requirement-value"><?php echo htmlspecialchars($selected_applicant['experience_required']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($selected_applicant['education_required'])): ?>
                <div class="requirement-item">
                    <div class="requirement-label">Education</div>
                    <div class="requirement-value"><?php echo htmlspecialchars($selected_applicant['education_required']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($selected_applicant['license_required'])): ?>
                <div class="requirement-item">
                    <div class="requirement-label">License/Certification</div>
                    <div class="requirement-value"><?php echo htmlspecialchars($selected_applicant['license_required']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($selected_applicant['skills'])): ?>
                <div class="requirement-item">
                    <div class="requirement-label">Applicant Skills</div>
                    <div class="requirement-value"><?php echo nl2br(htmlspecialchars($selected_applicant['skills'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Evaluation Form -->
        <form method="POST" class="evaluation-form">
            <input type="hidden" name="applicant_id" value="<?php echo $selected_applicant['id']; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-star" style="color: #0e4c92;"></i> Screening Score (0-100)</label>
                    <div class="score-input-group">
                        <input type="range" id="screening_score" name="screening_score" min="0" max="100" value="<?php echo $existing_evaluation['screening_score'] ?? 70; ?>" oninput="updateScore(this.value)">
                        <span class="score-value" id="score_display"><?php echo $existing_evaluation['screening_score'] ?? 70; ?></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-chart-line" style="color: #0e4c92;"></i> Qualification Match %</label>
                    <div class="score-input-group">
                        <input type="range" id="qualification_match" name="qualification_match" min="0" max="100" value="<?php echo $existing_evaluation['qualification_match'] ?? 70; ?>" oninput="updateMatch(this.value)">
                        <span class="score-value" id="match_display"><?php echo $existing_evaluation['qualification_match'] ?? 70; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Visual Match Indicator -->
            <div class="match-indicator">
                <i class="fas fa-user-check" style="color: #0e4c92;"></i>
                <span>Qualification Match:</span>
                <div class="match-bar">
                    <div class="match-progress" id="match_bar" style="width: <?php echo $existing_evaluation['qualification_match'] ?? 70; ?>%"></div>
                </div>
                <span class="score-value" id="match_percent"><?php echo $existing_evaluation['qualification_match'] ?? 70; ?>%</span>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-notes-medical" style="color: #0e4c92;"></i> Screening Notes</label>
                <textarea name="screening_notes" placeholder="Enter your evaluation notes, observations, and comments..."><?php echo htmlspecialchars($existing_evaluation['screening_notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tasks" style="color: #0e4c92;"></i> Screening Result</label>
                    <select name="screening_result" required>
                        <option value="pass" <?php echo ($existing_evaluation['screening_result'] ?? '') == 'pass' ? 'selected' : ''; ?>>Pass - Qualified for Interview</option>
                        <option value="fail" <?php echo ($existing_evaluation['screening_result'] ?? '') == 'fail' ? 'selected' : ''; ?>>Fail - Not Qualified</option>
                        <option value="pending" <?php echo ($existing_evaluation['screening_result'] ?? 'pending') == 'pending' ? 'selected' : ''; ?>>Pending - Need More Review</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar-check" style="color: #0e4c92;"></i> Evaluation Date</label>
                    <input type="text" value="<?php echo date('F d, Y H:i'); ?>" readonly disabled style="background: #f8fafd;">
                </div>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="update_status" id="update_status" checked>
                <label for="update_status" style="font-weight: 500;">
                    <i class="fas fa-sync-alt" style="color: #0e4c92;"></i> Automatically update applicant status based on result
                    <span style="font-size: 12px; color: #64748b; display: block; margin-top: 3px;">
                        (Pass → Shortlisted, Fail → Rejected)
                    </span>
                </label>
            </div>
            
            <?php if ($existing_evaluation): ?>
            <div style="background: #f8fafd; border-radius: 10px; padding: 10px; margin: 15px 0; font-size: 13px; color: #64748b;">
                <i class="fas fa-history"></i> Previously evaluated by <?php echo htmlspecialchars($existing_evaluation['evaluator_name'] ?? 'Unknown'); ?> on <?php echo date('F d, Y H:i', strtotime($existing_evaluation['evaluation_date'])); ?>
            </div>
            <?php endif; ?>
            
            <div class="modal-footer">
                <a href="?page=applicant&subpage=screening-evaluation&status=<?php echo $status_filter; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?>" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" name="save_evaluation" class="btn-primary">
                    <i class="fas fa-save"></i> Save Evaluation
                </button>
                <a href="?page=applicant&subpage=applicant-profiles&id=<?php echo $selected_applicant['id']; ?>" class="btn-secondary">
                    <i class="fas fa-user"></i> View Full Profile
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function updateScore(val) {
    document.getElementById('score_display').textContent = val;
}

function updateMatch(val) {
    document.getElementById('match_display').textContent = val;
    document.getElementById('match_bar').style.width = val + '%';
    document.getElementById('match_percent').textContent = val + '%';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('evaluationModal');
    if (event.target == modal) {
        window.location.href = '?page=applicant&subpage=screening-evaluation&status=<?php echo $status_filter; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?><?php echo !empty($department_filter) ? '&department=' . $department_filter : ''; ?>';
    }
}
</script>
<?php endif; ?>