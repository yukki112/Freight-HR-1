<?php
// Start output buffering
ob_start();

// public/onboarding-upload.php
// This is the public page where new hires upload their documents

// Include required files
require_once '../includes/config.php';
require_once '../config/mail_config.php';

$error = '';
$message = '';
$new_hire = null;
$required_docs = [];
$uploaded_docs = [];
$token_valid = false;
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Get base URL for proper redirects
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_path = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
$base_url = $protocol . $host . $base_path;

// Verify token
if (empty($token)) {
    $error = "Invalid access link. Please check your email for the correct link.";
} else {
    try {
        // Check if token exists and not expired - DON'T check used_at here
        // This allows multiple visits to the same link
        $stmt = $pdo->prepare("
            SELECT oat.*, nh.id as new_hire_id, nh.applicant_id, nh.position, nh.department,
                   ja.first_name, ja.last_name, ja.email, ja.photo_path
            FROM onboarding_access_tokens oat
            JOIN new_hires nh ON oat.new_hire_id = nh.id
            JOIN job_applications ja ON nh.applicant_id = ja.id
            WHERE oat.token = ? AND oat.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch();
        
        if ($token_data) {
            $token_valid = true;
            $new_hire_id = $token_data['new_hire_id'];
            
            // DON'T mark token as used immediately
            // Only mark as used if you want single-use links
            // For multiple uploads, we don't mark as used
            
            // Get new hire details
            $new_hire = $token_data;
            
            // Get required documents for this position/department
            $stmt = $pdo->prepare("
                SELECT * FROM required_onboarding_documents 
                WHERE is_active = 1 
                AND (applicable_departments IS NULL OR FIND_IN_SET(?, applicable_departments))
                ORDER BY sort_order, category
            ");
            $stmt->execute([$new_hire['department']]);
            $required_docs = $stmt->fetchAll();
            
            // Get already uploaded documents
            $stmt = $pdo->prepare("
                SELECT * FROM onboarding_documents 
                WHERE new_hire_id = ? 
                ORDER BY document_type, version DESC
            ");
            $stmt->execute([$new_hire_id]);
            $uploaded_docs_raw = $stmt->fetchAll();
            
            // Group by document type
            foreach ($uploaded_docs_raw as $doc) {
                if (!isset($uploaded_docs[$doc['document_type']]) || $uploaded_docs[$doc['document_type']]['version'] < $doc['version']) {
                    $uploaded_docs[$doc['document_type']] = $doc;
                }
            }
            
        } else {
            // Check if token exists but expired
            $stmt = $pdo->prepare("
                SELECT oat.*, nh.id as new_hire_id
                FROM onboarding_access_tokens oat
                JOIN new_hires nh ON oat.new_hire_id = nh.id
                WHERE oat.token = ? 
            ");
            $stmt->execute([$token]);
            $expired_token = $stmt->fetch();
            
            if ($expired_token) {
                if (strtotime($expired_token['expires_at']) < time()) {
                    $error = "This link has expired. Please contact HR for a new link.";
                } else {
                    $error = "This link is invalid. Please contact HR for assistance.";
                }
            } else {
                $error = "This link is invalid. Please contact HR for a new link.";
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again later.";
        error_log("Token verification error: " . $e->getMessage());
    }
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document']) && $token_valid) {
    $document_type = $_POST['document_type'];
    $document_number = $_POST['document_number'] ?? null;
    $issue_date = !empty($_POST['issue_date']) ? $_POST['issue_date'] : null;
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $remarks = $_POST['remarks'] ?? null;
    
    // Handle file upload
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['document_file']['type'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "Only PDF, JPG, and PNG files are allowed";
        } elseif ($_FILES['document_file']['size'] > $max_size) {
            $error = "File size must be less than 5MB";
        } else {
            // Create upload directory if not exists
            $upload_dir = "../uploads/onboarding/documents/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate filename
            $file_extension = pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION);
            $filename = "newhire_{$new_hire_id}_{$document_type}_" . time() . "." . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $file_path)) {
                try {
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Check if document already exists
                    $stmt = $pdo->prepare("
                        SELECT id, version FROM onboarding_documents 
                        WHERE new_hire_id = ? AND document_type = ? 
                        ORDER BY version DESC LIMIT 1
                    ");
                    $stmt->execute([$new_hire_id, $document_type]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Create new version
                        $version = $existing['version'] + 1;
                        $previous_id = $existing['id'];
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO onboarding_documents 
                            (new_hire_id, document_type, document_name, file_path, file_size, file_type, 
                             document_number, issue_date, expiry_date, remarks, status, version, previous_version_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                        ");
                        $stmt->execute([
                            $new_hire_id, $document_type, $_FILES['document_file']['name'], $file_path,
                            $_FILES['document_file']['size'], $file_type, $document_number, $issue_date,
                            $expiry_date, $remarks, $version, $previous_id
                        ]);
                    } else {
                        // First version
                        $stmt = $pdo->prepare("
                            INSERT INTO onboarding_documents 
                            (new_hire_id, document_type, document_name, file_path, file_size, file_type, 
                             document_number, issue_date, expiry_date, remarks, status, version)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 1)
                        ");
                        $stmt->execute([
                            $new_hire_id, $document_type, $_FILES['document_file']['name'], $file_path,
                            $_FILES['document_file']['size'], $file_type, $document_number, $issue_date,
                            $expiry_date, $remarks
                        ]);
                    }
                    
                    // Log to audit
                    $doc_id = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("
                        INSERT INTO onboarding_document_audit 
                        (document_id, action, old_status, new_status, performed_by)
                        VALUES (?, 'upload', NULL, 'pending', NULL)
                    ");
                    $stmt->execute([$doc_id]);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    // Send notification to HR
                    sendDocumentUploadNotification($pdo, $new_hire_id, $document_type);
                    
                    $message = "Document uploaded successfully! It will be reviewed by HR.";
                    
                    // Refresh uploaded docs
                    $stmt = $pdo->prepare("SELECT * FROM onboarding_documents WHERE new_hire_id = ? ORDER BY document_type, version DESC");
                    $stmt->execute([$new_hire_id]);
                    $uploaded_docs_raw = $stmt->fetchAll();
                    $uploaded_docs = [];
                    foreach ($uploaded_docs_raw as $doc) {
                        if (!isset($uploaded_docs[$doc['document_type']]) || $uploaded_docs[$doc['document_type']]['version'] < $doc['version']) {
                            $uploaded_docs[$doc['document_type']] = $doc;
                        }
                    }
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
                    $error = "Database error: " . $e->getMessage();
                    // Delete uploaded file if database insert fails
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            } else {
                $error = "Failed to upload file";
            }
        }
    } else {
        $error = "Please select a file to upload";
    }
}

/**
 * Send notification to HR about document upload
 */
function sendDocumentUploadNotification($pdo, $new_hire_id, $document_type) {
    try {
        // Get new hire details
        $stmt = $pdo->prepare("
            SELECT nh.*, ja.first_name, ja.last_name, ja.email, jp.title as position_title
            FROM new_hires nh
            JOIN job_applications ja ON nh.applicant_id = ja.id
            LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
            WHERE nh.id = ?
        ");
        $stmt->execute([$new_hire_id]);
        $new_hire = $stmt->fetch();
        
        // Get document name
        $stmt = $pdo->prepare("SELECT document_name FROM required_onboarding_documents WHERE document_code = ?");
        $stmt->execute([$document_type]);
        $doc_name = $stmt->fetchColumn();
        
        // Get HR users
        $stmt = $pdo->query("SELECT email, full_name FROM users WHERE role = 'admin'");
        $hr_users = $stmt->fetchAll();
        
        require_once '../config/mail_config.php';
        
        foreach ($hr_users as $hr) {
            $mail = MailConfig::getInstance();
            $mail->clearAddresses();
            
            $mail->addAddress($hr['email'], $hr['full_name']);
            $mail->Subject = "📄 New Document Upload - {$new_hire['first_name']} {$new_hire['last_name']}";
            
            // Generate proper URL
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $base_path = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
            $dashboard_url = $protocol . $host . $base_path . '/modules/onboarding/document-submission.php';
            
            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #0e4c92 0%, #2a6eb0 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; }
                    .content { background: #f8fafd; padding: 20px; border: 1px solid #eef2f6; }
                    .button { display: inline-block; padding: 12px 24px; background: #0e4c92; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>📄 New Document Upload</h2>
                    </div>
                    <div class='content'>
                        <p><strong>Employee:</strong> {$new_hire['first_name']} {$new_hire['last_name']}</p>
                        <p><strong>Position:</strong> {$new_hire['position_title']}</p>
                        <p><strong>Document:</strong> {$doc_name}</p>
                        <p><strong>Uploaded at:</strong> " . date('Y-m-d H:i:s') . "</p>
                        <a href='{$dashboard_url}' class='button'>Review Document</a>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            $mail->send();
        }
    } catch (Exception $e) {
        error_log("Failed to send HR notification: " . $e->getMessage());
    }
}

// Get document icon
function getDocumentIcon($document_type) {
    $icons = [
        'CONTRACT' => 'file-signature',
        'SSS_ID' => 'id-card',
        'PHILHEALTH_ID' => 'heartbeat',
        'PAGIBIG_ID' => 'home',
        'TIN_ID' => 'file-invoice',
        'BANK_INFO' => 'university',
        'MEDICAL' => 'stethoscope',
        'DRUG_TEST' => 'flask',
        'NBI_CLEARANCE' => 'shield-alt',
        'POLICE_CLEARANCE' => 'shield',
        'PROF_DRIVERS_LICENSE' => 'truck',
        'DEFENSIVE_DRIVING_CERT' => 'car',
        'SAFETY_TRAINING_CERT' => 'hard-hat',
        'FORKLIFT_CERT' => 'industry',
        'HAZMAT_CERT' => 'radiation'
    ];
    return $icons[$document_type] ?? 'file-alt';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Upload - Freight Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #0e4c92;
            --primary-dark: #0a3a70;
            --primary-light: #4086e4;
            --primary-transparent: rgba(14, 76, 146, 0.1);
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --dark: #2c3e50;
            --gray: #64748b;
            --light-gray: #f8fafd;
            --border: #eef2f6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .upload-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .header-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .content {
            padding: 30px;
        }
        
        .employee-info {
            background: var(--light-gray);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid var(--border);
        }
        
        .employee-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: 600;
        }
        
        .employee-details h2 {
            font-size: 22px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .employee-details p {
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 3px 0;
        }
        
        .progress-section {
            background: var(--light-gray);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .progress-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: var(--border);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success) 0%, #2ecc71 100%);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        .progress-stats {
            display: flex;
            justify-content: space-between;
            color: var(--gray);
            font-size: 14px;
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .document-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .document-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .document-card.verified {
            border-left: 4px solid var(--success);
            background: #f0fff4;
        }
        
        .document-card.pending {
            border-left: 4px solid var(--warning);
        }
        
        .document-card.rejected {
            border-left: 4px solid var(--danger);
        }
        
        .document-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-transparent);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 20px;
        }
        
        .document-title {
            flex: 1;
        }
        
        .document-title h3 {
            font-size: 14px;
            color: var(--dark);
            margin-bottom: 3px;
        }
        
        .document-title p {
            font-size: 12px;
            color: var(--gray);
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-verified {
            background: var(--success)20;
            color: var(--success);
        }
        
        .badge-pending {
            background: var(--warning)20;
            color: var(--warning);
        }
        
        .badge-rejected {
            background: var(--danger)20;
            color: var(--danger);
        }
        
        .badge-missing {
            background: var(--gray)20;
            color: var(--gray);
        }
        
        .upload-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 4px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-transparent);
        }
        
        .file-input {
            position: relative;
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            background: var(--light-gray);
            cursor: pointer;
        }
        
        .file-input:hover {
            border-color: var(--primary);
        }
        
        .file-input input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px var(--primary-transparent);
        }
        
        .view-link {
            display: inline-block;
            margin-top: 10px;
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
        }
        
        .view-link:hover {
            text-decoration: underline;
        }
        
        .rejection-reason {
            background: #fee9e7;
            border: 1px solid var(--danger);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            font-size: 12px;
            color: var(--danger);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            color: white;
            opacity: 0.8;
        }
        
        .footer a {
            color: white;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .documents-grid {
                grid-template-columns: 1fr;
            }
            
            .employee-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
        <div class="upload-card">
            <div class="header">
                <div class="header-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1>Access Error</h1>
                <p>We couldn't verify your access link</p>
            </div>
            <div class="content">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <p style="color: var(--gray); margin-bottom: 20px;">Need assistance? Contact our HR department:</p>
                    <p><i class="fas fa-envelope" style="color: var(--primary);"></i> hr@freightmanagement.com</p>
                    <p><i class="fas fa-phone" style="color: var(--primary);"></i> (02) 1234-5678</p>
                </div>
            </div>
        </div>
        <?php elseif ($token_valid && $new_hire): 
            // Calculate progress
            $total_required = count($required_docs);
            $uploaded_count = 0;
            foreach ($required_docs as $doc) {
                if (isset($uploaded_docs[$doc['document_code']]) && $uploaded_docs[$doc['document_code']]['status'] == 'verified') {
                    $uploaded_count++;
                }
            }
            $progress = $total_required > 0 ? round(($uploaded_count / $total_required) * 100) : 0;
        ?>
        <div class="upload-card">
            <div class="header">
                <div class="header-icon">
                    <i class="fas fa-file-upload"></i>
                </div>
                <h1>Welcome, <?php echo htmlspecialchars($new_hire['first_name']); ?>!</h1>
                <p>Please upload your required documents below</p>
                <p style="font-size: 14px; margin-top: 10px; opacity: 0.9;">
                    <i class="fas fa-info-circle"></i> You can upload multiple documents and come back anytime until <?php echo date('F d, Y', strtotime($token_data['expires_at'])); ?>
                </p>
            </div>
            
            <div class="content">
                <!-- Employee Info -->
                <div class="employee-info">
                    <div class="employee-avatar">
                        <?php echo strtoupper(substr($new_hire['first_name'], 0, 1) . substr($new_hire['last_name'], 0, 1)); ?>
                    </div>
                    <div class="employee-details">
                        <h2><?php echo htmlspecialchars($new_hire['first_name'] . ' ' . $new_hire['last_name']); ?></h2>
                        <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($new_hire['position']); ?></p>
                        <p><i class="fas fa-building"></i> <?php echo ucfirst($new_hire['department']); ?></p>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($new_hire['email']); ?></p>
                    </div>
                </div>
                
                <!-- Progress -->
                <div class="progress-section">
                    <div class="progress-title">
                        <i class="fas fa-chart-line" style="color: var(--primary);"></i>
                        Document Submission Progress
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                    </div>
                    <div class="progress-stats">
                        <span><?php echo $uploaded_count; ?> of <?php echo $total_required; ?> verified</span>
                        <span><?php echo $progress; ?>% Complete</span>
                    </div>
                </div>
                
                <!-- Messages -->
                <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <!-- Documents Grid -->
                <h3 style="margin: 20px 0 10px; color: var(--dark);">Required Documents</h3>
                <div class="documents-grid">
                    <?php foreach ($required_docs as $doc): 
                        $uploaded = isset($uploaded_docs[$doc['document_code']]);
                        $doc_data = $uploaded ? $uploaded_docs[$doc['document_code']] : null;
                        
                        $status_class = 'missing';
                        $status_text = 'Not Uploaded';
                        $card_class = '';
                        
                        if ($uploaded) {
                            if ($doc_data['status'] == 'verified') {
                                $status_class = 'badge-verified';
                                $status_text = 'Verified';
                                $card_class = 'verified';
                            } elseif ($doc_data['status'] == 'rejected') {
                                $status_class = 'badge-rejected';
                                $status_text = 'Rejected';
                                $card_class = 'rejected';
                            } else {
                                $status_class = 'badge-pending';
                                $status_text = 'Pending Review';
                                $card_class = 'pending';
                            }
                        }
                    ?>
                    <div class="document-card <?php echo $card_class; ?>">
                        <div class="document-header">
                            <div class="document-icon">
                                <i class="fas fa-<?php echo getDocumentIcon($doc['document_code']); ?>"></i>
                            </div>
                            <div class="document-title">
                                <h3><?php echo htmlspecialchars($doc['document_name']); ?></h3>
                                <p><?php echo ucfirst(str_replace('_', ' ', $doc['category'])); ?></p>
                            </div>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </div>
                        
                        <?php if ($uploaded && $doc_data): ?>
                            <?php if ($doc_data['status'] == 'rejected' && $doc_data['rejection_reason']): ?>
                            <div class="rejection-reason">
                                <i class="fas fa-exclamation-circle"></i>
                                <strong>Reason:</strong> <?php echo htmlspecialchars($doc_data['rejection_reason']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <a href="<?php echo $base_url . '/' . ltrim($doc_data['file_path'], '/'); ?>" target="_blank" class="view-link">
                                <i class="fas fa-eye"></i> View Uploaded Document
                            </a>
                            
                            <?php if ($doc_data['status'] == 'rejected'): ?>
                            <div class="upload-form">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="document_type" value="<?php echo $doc['document_code']; ?>">
                                    
                                    <?php if ($doc['requires_document_number']): ?>
                                    <div class="form-group">
                                        <label>Document Number</label>
                                        <input type="text" name="document_number" placeholder="e.g., SSS-1234-5678" value="<?php echo htmlspecialchars($doc_data['document_number'] ?? ''); ?>">
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($doc['requires_issue_date']): ?>
                                    <div class="form-group">
                                        <label>Issue Date</label>
                                        <input type="date" name="issue_date" value="<?php echo $doc_data['issue_date'] ?? ''; ?>">
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($doc['requires_expiry']): ?>
                                    <div class="form-group">
                                        <label>Expiry Date</label>
                                        <input type="date" name="expiry_date" value="<?php echo $doc_data['expiry_date'] ?? ''; ?>">
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="form-group">
                                        <label>Upload New File *</label>
                                        <div class="file-input">
                                            <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: var(--primary); margin-bottom: 5px; display: block;"></i>
                                            <span style="font-size: 12px;">Click to select or drag file</span>
                                            <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Remarks (Optional)</label>
                                        <textarea name="remarks" rows="2" placeholder="Any notes about this document?"><?php echo htmlspecialchars($doc_data['remarks'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" name="upload_document" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Re-upload Document
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="upload-form">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="document_type" value="<?php echo $doc['document_code']; ?>">
                                    
                                    <?php if ($doc['requires_document_number']): ?>
                                    <div class="form-group">
                                        <label>Document Number</label>
                                        <input type="text" name="document_number" placeholder="e.g., SSS-1234-5678">
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($doc['requires_issue_date']): ?>
                                    <div class="form-group">
                                        <label>Issue Date</label>
                                        <input type="date" name="issue_date">
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($doc['requires_expiry']): ?>
                                    <div class="form-group">
                                        <label>Expiry Date</label>
                                        <input type="date" name="expiry_date">
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="form-group">
                                        <label>Upload File *</label>
                                        <div class="file-input">
                                            <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: var(--primary); margin-bottom: 5px; display: block;"></i>
                                            <span style="font-size: 12px;">Click to select or drag file</span>
                                            <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Remarks (Optional)</label>
                                        <textarea name="remarks" rows="2" placeholder="Any notes about this document?"></textarea>
                                    </div>
                                    
                                    <button type="submit" name="upload_document" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Upload Document
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 30px; padding: 20px; background: var(--light-gray); border-radius: 10px; text-align: center;">
                    <i class="fas fa-info-circle" style="color: var(--primary); font-size: 20px; margin-bottom: 10px;"></i>
                    <p style="color: var(--gray); font-size: 14px;">
                        All documents will be reviewed by our HR team. You will receive an email notification once verified.<br>
                        If any document is rejected, you can upload a new version using the same link.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Freight Management Inc. All rights reserved.</p>
            <p><a href="mailto:hr@freightmanagement.com">Contact HR</a> | <a href="#">Privacy Policy</a></p>
        </div>
    </div>
    
    <script>
        // File input preview
        document.querySelectorAll('.file-input input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const parent = this.closest('.file-input');
                const icon = parent.querySelector('i');
                const text = parent.querySelector('span');
                
                if (this.files[0]) {
                    icon.style.color = 'var(--success)';
                    text.innerHTML = this.files[0].name;
                } else {
                    icon.style.color = 'var(--primary)';
                    text.innerHTML = 'Click to select or drag file';
                }
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>