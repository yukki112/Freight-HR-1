<?php
// Start output buffering at the VERY FIRST LINE - NO SPACES OR CHARACTERS BEFORE THIS
ob_start();

// modules/onboarding/document-submission.php
$page_title = "Document Submission & Verification";

// Include required files
require_once 'config/mail_config.php';

// Define base URL for the application
define('BASE_URL', '/freight/');

/**
 * Helper function to get correct document URL
 * @param string $file_path The file path from database
 * @return string Full URL with correct base
 */
function getDocumentUrl($file_path) {
    if (empty($file_path)) {
        return '#';
    }
    
    // If it's already a full URL with domain
    if (strpos($file_path, 'http://') === 0 || strpos($file_path, 'https://') === 0) {
        // Extract the path part after the domain
        $parsed = parse_url($file_path);
        $path = $parsed['path'] ?? '';
        
        // If the path already starts with /freight/, return as is
        if (strpos($path, '/freight/') === 0) {
            return $file_path;
        }
        
        // Otherwise, add /freight/ to the path
        $path = '/freight' . $path;
        return $parsed['scheme'] . '://' . $parsed['host'] . $path;
    }
    
    // Remove any leading slashes
    $file_path = ltrim($file_path, '/');
    
    // If it already starts with freight/, just add leading slash
    if (strpos($file_path, 'freight/') === 0) {
        return '/' . $file_path;
    }
    
    // If it starts with uploads/, add /freight/
    if (strpos($file_path, 'uploads/') === 0) {
        return '/freight/' . $file_path;
    }
    
    // Default case: add /freight/ and the path
    return '/freight/' . $file_path;
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : 'all';

// Get current user ID (from session)
$current_user_id = $_SESSION['user_id'] ?? 5; // Default to admin for testing
$is_hr = true; // You should check actual user role

// Simple log function
function simpleLog($pdo, $user_id, $action, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Helper Functions
 */
function getDocumentStatusBadge($status) {
    switch($status) {
        case 'verified': return '<span class="category-badge" style="background: #27ae6020; color: #27ae60;"><i class="fas fa-check-circle"></i> Verified</span>';
        case 'rejected': return '<span class="category-badge" style="background: #e74c3c20; color: #e74c3c;"><i class="fas fa-times-circle"></i> Rejected</span>';
        case 'expired': return '<span class="category-badge" style="background: #f39c1220; color: #f39c12;"><i class="fas fa-exclamation-triangle"></i> Expired</span>';
        case 'pending':
        default: return '<span class="category-badge" style="background: #3498db20; color: #3498db;"><i class="fas fa-clock"></i> Pending</span>';
    }
}

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

function getDocumentProgressColor($percentage) {
    if ($percentage >= 100) return '#27ae60';
    if ($percentage >= 75) return '#2ecc71';
    if ($percentage >= 50) return '#f39c12';
    if ($percentage >= 25) return '#e67e22';
    return '#e74c3c';
}

function getDocumentStatusColor($status) {
    switch($status) {
        case 'verified': return '#27ae60';
        case 'rejected': return '#e74c3c';
        case 'expired': return '#f39c12';
        case 'pending': return '#3498db';
        default: return '#7f8c8d';
    }
}

function getDocumentStatusText($status) {
    switch($status) {
        case 'verified': return 'Verified';
        case 'rejected': return 'Rejected';
        case 'expired': return 'Expired';
        case 'pending': return 'Pending Review';
        default: return 'No Request';
    }
}

/**
 * Generate secure document upload link (like job posting links)
 */
function generateDocumentUploadLink($new_hire_id, $expiration_days = 7) {
    $token = bin2hex(random_bytes(32));
    $expiration = date('Y-m-d H:i:s', strtotime("+{$expiration_days} days"));
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $upload_link = $protocol . $host . '/freight/public/onboarding-upload.php?token=' . $token;
    
    return [
        'token' => $token,
        'expiration' => $expiration,
        'upload_link' => $upload_link
    ];
}

/**
 * Send document request email with secure link
 */
function sendDocumentRequestEmail($pdo, $new_hire_id, $user_id) {
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
        
        if (!$new_hire) {
            return ['success' => false, 'message' => 'New hire not found'];
        }
        
        // Generate secure link
        $link_data = generateDocumentUploadLink($new_hire_id, 7);
        
        // Save token to database
        $stmt = $pdo->prepare("
            INSERT INTO onboarding_access_tokens (new_hire_id, token, email, expires_at, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$new_hire_id, $link_data['token'], $new_hire['email'], $link_data['expiration'], $user_id]);
        
        // Log document request
        $stmt = $pdo->prepare("
            INSERT INTO document_requests (new_hire_id, requested_by, status)
            VALUES (?, ?, 'pending')
        ");
        $stmt->execute([$new_hire_id, $user_id]);
        
        // Send email using PHPMailer
        $mail = MailConfig::getInstance();
        $mail->clearAddresses();
        $mail->clearAttachments();
        
        $mail->addAddress($new_hire['email'], $new_hire['first_name'] . ' ' . $new_hire['last_name']);
        $mail->addBCC('hr@freightmanagement.com', 'HR Department');
        
        $mail->Subject = "📄 Document Submission Required - Freight Management Onboarding";
        
        // Build email body
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #0e4c92 0%, #2a6eb0 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8fafd; padding: 30px; border: 1px solid #eef2f6; }
                .button { display: inline-block; padding: 15px 30px; background: #0e4c92; color: white; text-decoration: none; border-radius: 50px; font-weight: bold; margin: 20px 0; }
                .button:hover { background: #0a3a70; }
                .footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; border-radius: 0 0 10px 10px; }
                .warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Welcome to Freight Management!</h1>
                    <p>Complete your onboarding documents</p>
                </div>
                
                <div class="content">
                    <h2>Dear ' . htmlspecialchars($new_hire['first_name'] . ' ' . $new_hire['last_name']) . ',</h2>
                    
                    <p>Congratulations on your new position as <strong>' . htmlspecialchars($new_hire['position_title']) . '</strong>! We\'re excited to have you join our team.</p>
                    
                    <p>To complete your onboarding process, please upload the required documents using the secure link below:</p>
                    
                    <div style="text-align: center;">
                        <a href="' . $link_data['upload_link'] . '" class="button">
                            <i class="fas fa-upload"></i> Upload Your Documents
                        </a>
                    </div>
                    
                    <div class="warning">
                        <strong><i class="fas fa-info-circle"></i> Important:</strong>
                        <ul style="margin-top: 10px;">
                            <li>This link is unique to you and will expire in 7 days</li>
                            <li>You can upload multiple documents in one session</li>
                            <li>Supported formats: PDF, JPG, PNG (Max 5MB per file)</li>
                            <li>You\'ll receive email notifications when documents are verified</li>
                        </ul>
                    </div>
                    
                    <p><strong>Required Documents:</strong></p>
                    <ul>
        ';
        
        // Add required documents list
        $stmt = $pdo->prepare("
            SELECT * FROM required_onboarding_documents 
            WHERE is_active = 1 
            AND (applicable_departments IS NULL OR FIND_IN_SET(?, applicable_departments))
            ORDER BY sort_order
        ");
        $stmt->execute([$new_hire['department']]);
        $required_docs = $stmt->fetchAll();
        
        foreach ($required_docs as $doc) {
            $body .= '<li><i class="fas fa-check-circle" style="color: #27ae60;"></i> ' . htmlspecialchars($doc['document_name']) . '</li>';
        }
        
        $body .= '
                    </ul>
                    
                    <p>If you have any questions or trouble uploading your documents, please contact our HR department:</p>
                    <p>📧 hr@freightmanagement.com<br>📞 (02) 1234-5678</p>
                </div>
                
                <div class="footer">
                    <p>This is an automated message. Please do not reply directly to this email.</p>
                    <p>&copy; ' . date('Y') . ' Freight Management Inc. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $body));
        
        if ($mail->send()) {
            // Log communication
            $stmt = $pdo->prepare("
                INSERT INTO communication_log (applicant_id, communication_type, subject, message, sent_by, status)
                VALUES ((SELECT applicant_id FROM new_hires WHERE id = ?), 'email', ?, ?, ?, 'sent')
            ");
            $stmt->execute([
                $new_hire_id,
                $mail->Subject,
                "Document request sent with link",
                $user_id
            ]);
            
            simpleLog($pdo, $user_id, 'request_documents', "Sent document request to new hire #$new_hire_id");
            
            return ['success' => true, 'message' => 'Email sent successfully', 'link' => $link_data['upload_link']];
        } else {
            return ['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Send rejection email
 */
function sendRejectionEmail($pdo, $document_id, $rejection_reason) {
    try {
        // Get document and new hire details
        $stmt = $pdo->prepare("
            SELECT od.*, nh.id as new_hire_id, nh.applicant_id,
                   ja.first_name, ja.last_name, ja.email,
                   rod.document_name
            FROM onboarding_documents od
            JOIN new_hires nh ON od.new_hire_id = nh.id
            JOIN job_applications ja ON nh.applicant_id = ja.id
            JOIN required_onboarding_documents rod ON od.document_type = rod.document_code
            WHERE od.id = ?
        ");
        $stmt->execute([$document_id]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return ['success' => false, 'message' => 'Document not found'];
        }
        
        // Generate new token for re-upload
        $link_data = generateDocumentUploadLink($data['new_hire_id'], 7);
        
        $stmt = $pdo->prepare("
            INSERT INTO onboarding_access_tokens (new_hire_id, token, email, expires_at, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$data['new_hire_id'], $link_data['token'], $data['email'], $link_data['expiration'], $_SESSION['user_id'] ?? 5]);
        
        // Send email
        $mail = MailConfig::getInstance();
        $mail->clearAddresses();
        
        $mail->addAddress($data['email'], $data['first_name'] . ' ' . $data['last_name']);
        $mail->Subject = "⚠️ Document Update Required - {$data['document_name']}";
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8fafd; padding: 30px; border: 1px solid #eef2f6; }
                .button { display: inline-block; padding: 15px 30px; background: #0e4c92; color: white; text-decoration: none; border-radius: 50px; font-weight: bold; margin: 20px 0; }
                .reason-box { background: #fee9e7; border: 1px solid #e74c3c; padding: 20px; border-radius: 10px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Document Update Required</h1>
                </div>
                
                <div class='content'>
                    <h2>Dear " . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . ",</h2>
                    
                    <p>Your <strong>" . htmlspecialchars($data['document_name']) . "</strong> could not be verified.</p>
                    
                    <div class='reason-box'>
                        <h3 style='color: #e74c3c; margin-top: 0;'><i class='fas fa-exclamation-circle'></i> Reason for Rejection:</h3>
                        <p>" . nl2br(htmlspecialchars($rejection_reason)) . "</p>
                    </div>
                    
                    <p>Please upload a corrected version using the link below:</p>
                    
                    <div style='text-align: center;'>
                        <a href='" . $link_data['upload_link'] . "' class='button'>
                            <i class='fas fa-upload'></i> Re-upload Document
                        </a>
                    </div>
                    
                    <p style='color: #666; font-size: 14px; margin-top: 20px;'>
                        <i class='fas fa-info-circle'></i> This link will expire in 7 days.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $body));
        
        if ($mail->send()) {
            // Log communication
            $stmt = $pdo->prepare("
                INSERT INTO communication_log (applicant_id, communication_type, subject, message, sent_by, status)
                VALUES (?, 'email', ?, ?, ?, 'sent')
            ");
            $stmt->execute([
                $data['applicant_id'],
                $mail->Subject,
                "Document rejected: " . substr($rejection_reason, 0, 100),
                $_SESSION['user_id'] ?? 5
            ]);
            
            return ['success' => true, 'message' => 'Rejection email sent'];
        } else {
            return ['success' => false, 'message' => 'Failed to send email'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Handle document request (can be clicked multiple times)
if (isset($_POST['request_documents'])) {
    $new_hire_id = $_POST['new_hire_id'];
    $result = sendDocumentRequestEmail($pdo, $new_hire_id, $current_user_id);
    
    if ($result['success']) {
        $message = "✅ Document request email sent successfully! A new link has been generated and sent to the new hire.";
    } else {
        $error = "❌ " . $result['message'];
    }
}

// Handle document verification
if (isset($_POST['verify_document']) && $is_hr) {
    $document_id = $_POST['document_id'];
    $action_type = $_POST['verification_action'];
    $rejection_reason = $_POST['rejection_reason'] ?? null;
    
    try {
        if ($action_type == 'verify') {
            $stmt = $pdo->prepare("
                UPDATE onboarding_documents 
                SET status = 'verified', verified_by = ?, verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$current_user_id, $document_id]);
            
            // Log to audit
            $stmt = $pdo->prepare("
                INSERT INTO onboarding_document_audit (document_id, action, old_status, new_status, performed_by)
                VALUES (?, 'verify', 'pending', 'verified', ?)
            ");
            $stmt->execute([$document_id, $current_user_id]);
            
            simpleLog($pdo, $current_user_id, 'verify_onboarding_document', "Verified document #$document_id");
            $message = "Document verified successfully";
            
        } elseif ($action_type == 'reject') {
            $stmt = $pdo->prepare("
                UPDATE onboarding_documents 
                SET status = 'rejected', rejection_reason = ?, verified_by = ?, verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$rejection_reason, $current_user_id, $document_id]);
            
            // Log to audit
            $stmt = $pdo->prepare("
                INSERT INTO onboarding_document_audit (document_id, action, old_status, new_status, performed_by, remarks)
                VALUES (?, 'reject', 'pending', 'rejected', ?, ?)
            ");
            $stmt->execute([$document_id, $current_user_id, $rejection_reason]);
            
            // Send rejection email
            $email_result = sendRejectionEmail($pdo, $document_id, $rejection_reason);
            if ($email_result['success']) {
                $message = "Document rejected and notification email sent";
            } else {
                $message = "Document rejected but email failed: " . $email_result['message'];
            }
            
            simpleLog($pdo, $current_user_id, 'reject_onboarding_document', "Rejected document #$document_id: $rejection_reason");
        }
        
        // Check if all required documents are now verified
        $stmt = $pdo->prepare("
            SELECT nh.id, 
                   COUNT(DISTINCT rod.id) as total_required,
                   SUM(CASE WHEN od.status = 'verified' THEN 1 ELSE 0 END) as verified_count
            FROM new_hires nh
            CROSS JOIN required_onboarding_documents rod
            LEFT JOIN onboarding_documents od ON od.new_hire_id = nh.id 
                AND od.document_type = rod.document_code 
                AND od.status = 'verified'
            WHERE nh.id = (SELECT new_hire_id FROM onboarding_documents WHERE id = ?)
                AND (rod.applicable_departments IS NULL OR FIND_IN_SET(nh.department, rod.applicable_departments))
                AND rod.is_required = 1
            GROUP BY nh.id
        ");
        $stmt->execute([$document_id]);
        $progress = $stmt->fetch();
        
        if ($progress && $progress['total_required'] == $progress['verified_count']) {
            $stmt = $pdo->prepare("
                UPDATE new_hires 
                SET onboarding_progress = 25, 
                    notes = CONCAT(COALESCE(notes, ''), '\n[', NOW(), '] All required documents verified')
                WHERE id = ?
            ");
            $stmt->execute([$progress['id']]);
            
            simpleLog($pdo, $current_user_id, 'documents_completed', 
                "All required documents verified for new hire #{$progress['id']}");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get statistics
$stats = [];

// Total new hires in document submission phase
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM new_hires nh
    WHERE nh.status = 'onboarding'
    AND nh.onboarding_progress < 25
");
$stmt->execute();
$stats['total_pending'] = $stmt->fetchColumn() ?: 0;

// Documents pending verification
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM onboarding_documents
    WHERE status = 'pending'
");
$stmt->execute();
$stats['pending_verification'] = $stmt->fetchColumn() ?: 0;

// Documents expiring soon (next 30 days)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM onboarding_documents
    WHERE status = 'verified'
    AND expiry_date IS NOT NULL
    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute();
$stats['expiring_soon'] = $stmt->fetchColumn() ?: 0;

// Expired documents
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM onboarding_documents
    WHERE status = 'verified'
    AND expiry_date IS NOT NULL
    AND expiry_date < CURDATE()
");
$stmt->execute();
$stats['expired'] = $stmt->fetchColumn() ?: 0;

// Completed document submissions
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT nh.id) as total
    FROM new_hires nh
    WHERE NOT EXISTS (
        SELECT 1
        FROM required_onboarding_documents rod
        LEFT JOIN onboarding_documents od ON od.new_hire_id = nh.id 
            AND od.document_type = rod.document_code 
            AND od.status = 'verified'
        WHERE (rod.applicable_departments IS NULL OR FIND_IN_SET(nh.department, rod.applicable_departments))
            AND rod.is_required = 1
            AND od.id IS NULL
    )
    AND nh.status = 'onboarding'
");
$stmt->execute();
$stats['completed'] = $stmt->fetchColumn() ?: 0;

// Pending document requests
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM document_requests
    WHERE status = 'pending'
");
$stmt->execute();
$stats['pending_requests'] = $stmt->fetchColumn() ?: 0;

// Get all new hires with document status
$query = "
    SELECT 
        nh.id as new_hire_id,
        nh.employee_id,
        nh.hire_date,
        nh.start_date,
        nh.position,
        nh.department,
        nh.employment_status,
        nh.contract_signed,
        nh.id_submitted,
        nh.medical_clearance,
        nh.onboarding_progress,
        nh.status as onboarding_status,
        
        ja.id as applicant_id,
        ja.application_number,
        ja.first_name,
        ja.last_name,
        ja.email,
        ja.phone,
        ja.photo_path,
        
        jp.id as job_posting_id,
        jp.title as job_title,
        jp.job_code,
        
        -- Document statistics
        (
            SELECT COUNT(*) 
            FROM required_onboarding_documents rod
            WHERE rod.is_required = 1
            AND (rod.applicable_departments IS NULL OR FIND_IN_SET(nh.department, rod.applicable_departments))
        ) as total_required_docs,
        
        (
            SELECT COUNT(*)
            FROM required_onboarding_documents rod
            LEFT JOIN onboarding_documents od ON od.new_hire_id = nh.id 
                AND od.document_type = rod.document_code 
                AND od.status = 'verified'
            WHERE rod.is_required = 1
            AND (rod.applicable_departments IS NULL OR FIND_IN_SET(nh.department, rod.applicable_departments))
            AND od.id IS NOT NULL
        ) as verified_docs,
        
        (
            SELECT COUNT(*)
            FROM onboarding_documents od
            WHERE od.new_hire_id = nh.id
            AND od.status = 'pending'
        ) as pending_docs,
        
        (
            SELECT COUNT(*)
            FROM onboarding_documents od
            WHERE od.new_hire_id = nh.id
            AND od.status = 'rejected'
        ) as rejected_docs,
        
        (
            SELECT COUNT(*)
            FROM onboarding_documents od
            WHERE od.new_hire_id = nh.id
            AND od.expiry_date IS NOT NULL
            AND od.expiry_date < CURDATE()
            AND od.status = 'verified'
        ) as expired_docs,
        
        (
            SELECT COUNT(*)
            FROM document_requests dr
            WHERE dr.new_hire_id = nh.id
            AND dr.status = 'pending'
        ) as has_pending_request,
        
        (
            SELECT MAX(dr.requested_at)
            FROM document_requests dr
            WHERE dr.new_hire_id = nh.id
        ) as last_request_date,
        
        -- Check if all required docs are verified
        CASE 
            WHEN (
                SELECT COUNT(*) 
                FROM required_onboarding_documents rod
                WHERE rod.is_required = 1
                AND (rod.applicable_departments IS NULL OR FIND_IN_SET(nh.department, rod.applicable_departments))
            ) = (
                SELECT COUNT(*)
                FROM required_onboarding_documents rod
                LEFT JOIN onboarding_documents od ON od.new_hire_id = nh.id 
                    AND od.document_type = rod.document_code 
                    AND od.status = 'verified'
                WHERE rod.is_required = 1
                AND (rod.applicable_departments IS NULL OR FIND_IN_SET(nh.department, rod.applicable_departments))
                AND od.id IS NOT NULL
            ) THEN 1 ELSE 0 
        END as all_docs_verified
        
    FROM new_hires nh
    LEFT JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN job_postings jp ON nh.job_posting_id = jp.id
    WHERE nh.status IN ('onboarding', 'active')
";

$params = [];

// Status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'pending_docs') {
        $query .= " AND EXISTS (
            SELECT 1 FROM required_onboarding_documents rod
            LEFT JOIN onboarding_documents od ON od.new_hire_id = nh.id 
                AND od.document_type = rod.document_code 
                AND od.status = 'verified'
            WHERE rod.is_required = 1
            AND (rod.applicable_departments IS NULL OR FIND_IN_SET(nh.department, rod.applicable_departments))
            AND od.id IS NULL
        )";
    } elseif ($status_filter === 'all_verified') {
        $query .= " AND NOT EXISTS (
            SELECT 1 FROM required_onboarding_documents rod
            LEFT JOIN onboarding_documents od ON od.new_hire_id = nh.id 
                AND od.document_type = rod.document_code 
                AND od.status = 'verified'
            WHERE rod.is_required = 1
            AND (rod.applicable_departments IS NULL OR FIND_IN_SET(nh.department, rod.applicable_departments))
            AND od.id IS NULL
        )";
    } elseif ($status_filter === 'has_rejected') {
        $query .= " AND EXISTS (
            SELECT 1 FROM onboarding_documents od
            WHERE od.new_hire_id = nh.id
            AND od.status = 'rejected'
        )";
    } elseif ($status_filter === 'has_expired') {
        $query .= " AND EXISTS (
            SELECT 1 FROM onboarding_documents od
            WHERE od.new_hire_id = nh.id
            AND od.expiry_date IS NOT NULL
            AND od.expiry_date < CURDATE()
            AND od.status = 'verified'
        )";
    } elseif ($status_filter === 'request_sent') {
        $query .= " AND EXISTS (
            SELECT 1 FROM document_requests dr
            WHERE dr.new_hire_id = nh.id
            AND dr.status = 'pending'
        )";
    }
}

// Department filter
if (!empty($department_filter) && $department_filter !== 'all') {
    $query .= " AND nh.department = ?";
    $params[] = $department_filter;
}

// Search filter
if (!empty($search_filter)) {
    $query .= " AND (ja.first_name LIKE ? OR ja.last_name LIKE ? OR ja.application_number LIKE ? OR ja.email LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY 
            CASE WHEN EXISTS (
                SELECT 1 FROM onboarding_documents od
                WHERE od.new_hire_id = nh.id
                AND od.status = 'rejected'
            ) THEN 0 ELSE 1 END,
            all_docs_verified ASC,
            nh.start_date ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$new_hires = $stmt->fetchAll();

// Get required documents for each position/department
$stmt = $pdo->query("
    SELECT * FROM required_onboarding_documents 
    WHERE is_active = 1 
    ORDER BY sort_order, category, document_name
");
$all_required_docs = $stmt->fetchAll();

// Get user list for verification
$stmt = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'dispatcher') ORDER BY full_name");
$users = $stmt->fetchAll();

// Department colors (same as job posting)
$dept_colors = [
    'driver' => '#0e4c92',
    'warehouse' => '#1a5da0',
    'logistics' => '#2a6eb0',
    'admin' => '#3a7fc0',
    'management' => '#4a90d0'
];
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

/* Stats Cards */
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

.stat-small {
    font-size: 12px;
    color: var(--gray);
    margin-top: 5px;
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
    color: var(--dark);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-title i {
    color: var(--primary);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-item label {
    font-size: 11px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-item input,
.filter-item select {
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 13px;
    transition: all 0.3s;
    background: white;
}

.filter-item input:focus,
.filter-item select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

.filter-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
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

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    text-decoration: none;
}

.btn-icon.request {
    background: var(--info)20;
    color: var(--info);
}

.btn-icon.view {
    background: var(--primary)20;
    color: var(--primary);
}

.btn-icon.activate {
    background: var(--success)20;
    color: var(--success);
}

.btn-icon:hover {
    transform: scale(1.1);
    filter: brightness(0.9);
}

/* Document Cards Grid */
.document-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.document-card {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.document-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px var(--primary-transparent-2);
}

.document-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
}

.document-card.pending::before { background: linear-gradient(90deg, var(--warning) 0%, #f1c40f 100%); }
.document-card.verified::before { background: linear-gradient(90deg, var(--success) 0%, #2ecc71 100%); }
.document-card.rejected::before { background: linear-gradient(90deg, var(--danger) 0%, #c0392b 100%); }
.document-card.expired::before { background: linear-gradient(90deg, var(--gray) 0%, #95a5a6 100%); }

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.applicant-photo {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    object-fit: cover;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 18px;
}

.applicant-info {
    flex: 1;
}

.applicant-info h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 3px;
}

.applicant-info p {
    font-size: 12px;
    color: var(--gray);
    margin: 2px 0;
}

.applicant-info i {
    width: 14px;
    color: var(--primary);
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
}

/* Progress Section */
.progress-section {
    background: var(--light-gray);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.progress-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--gray);
}

.progress-value {
    font-size: 14px;
    font-weight: 700;
    color: var(--dark);
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--border);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-stats {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: var(--gray);
}

/* Document Summary */
.document-summary {
    display: flex;
    justify-content: space-around;
    margin-bottom: 15px;
    padding: 10px;
    background: white;
    border-radius: 12px;
    border: 1px solid var(--border);
}

.summary-item {
    text-align: center;
}

.summary-count {
    font-size: 18px;
    font-weight: 700;
    line-height: 1.2;
}

.summary-count.verified { color: var(--success); }
.summary-count.pending { color: var(--warning); }
.summary-count.rejected { color: var(--danger); }

.summary-label {
    font-size: 10px;
    color: var(--gray);
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 15px;
    padding: 12px;
    background: var(--light-gray);
    border-radius: 12px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 10px;
    color: var(--gray);
    margin-bottom: 2px;
}

.info-value {
    font-size: 13px;
    font-weight: 500;
    color: var(--dark);
}

/* Card Actions */
.card-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

/* Department Tag */
.dept-tag {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    background: var(--primary-transparent);
    color: var(--primary);
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
    backdrop-filter: blur(5px);
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 30px;
    padding: 30px;
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: modalPop 0.3s;
}

@keyframes modalPop {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 36px;
    height: 36px;
    background: var(--primary-transparent);
    border: none;
    border-radius: 10px;
    color: var(--primary);
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.modal-close:hover {
    background: var(--primary);
    color: white;
    transform: rotate(90deg);
}

/* Document Item in Modal */
.document-item {
    background: var(--light-gray);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 15px;
    border-left: 4px solid transparent;
}

.document-item.verified { border-left-color: var(--success); }
.document-item.pending { border-left-color: var(--warning); }
.document-item.rejected { border-left-color: var(--danger); }

.document-item .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.document-item .title {
    font-weight: 600;
    color: var(--dark);
    font-size: 16px;
}

.document-item .details {
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 15px;
    line-height: 1.6;
}

.document-item .details div {
    margin-bottom: 5px;
}

.document-item .details i {
    width: 18px;
    color: var(--primary);
}

.document-item .actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Alert styles */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
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

@keyframes slideDown {
    from { transform: translateY(-100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px;
    color: var(--gray);
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: var(--dark);
}

/* Responsive */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .document-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- ==================== HTML CONTENT ==================== -->

<!-- Messages -->
<?php if ($message): ?>
<div class="alert alert-success" style="margin-bottom: 20px; animation: slideDown 0.3s;">
    <i class="fas fa-check-circle"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger" style="margin-bottom: 20px; animation: slideDown 0.3s;">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-title">
        <i class="fas fa-file-upload"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div>
        <span class="stat-small" style="background: var(--primary-transparent); padding: 8px 16px; border-radius: 30px;">
            <i class="fas fa-clock"></i> Pending Verification: <?php echo $stats['pending_verification']; ?>
        </span>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Active Onboarding</span>
            <span class="stat-value"><?php echo $stats['total_pending']; ?></span>
            <div class="stat-small">
                <i class="fas fa-hourglass-half" style="color: var(--warning);"></i> In progress
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Completed</span>
            <span class="stat-value"><?php echo $stats['completed']; ?></span>
            <div class="stat-small">
                <i class="fas fa-check" style="color: var(--success);"></i> All verified
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Verification</span>
            <span class="stat-value"><?php echo $stats['pending_verification']; ?></span>
            <div class="stat-small">
                <i class="fas fa-hourglass"></i> Need review
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Expiring Soon</span>
            <span class="stat-value"><?php echo $stats['expiring_soon']; ?></span>
            <div class="stat-small">
                <i class="fas fa-calendar"></i> Next 30 days
            </div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-ban"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Expired</span>
            <span class="stat-value"><?php echo $stats['expired']; ?></span>
            <div class="stat-small">
                <i class="fas fa-exclamation-circle" style="color: var(--danger);"></i> Need update
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Document Submissions
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="onboarding">
        <input type="hidden" name="subpage" value="document-submission">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All New Hires</option>
                    <option value="pending_docs" <?php echo $status_filter == 'pending_docs' ? 'selected' : ''; ?>>Pending Documents</option>
                    <option value="all_verified" <?php echo $status_filter == 'all_verified' ? 'selected' : ''; ?>>All Verified</option>
                    <option value="has_rejected" <?php echo $status_filter == 'has_rejected' ? 'selected' : ''; ?>>Has Rejected</option>
                    <option value="has_expired" <?php echo $status_filter == 'has_expired' ? 'selected' : ''; ?>>Has Expired</option>
                    <option value="request_sent" <?php echo $status_filter == 'request_sent' ? 'selected' : ''; ?>>Request Sent</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Department</label>
                <select name="department">
                    <option value="all" <?php echo $department_filter == 'all' ? 'selected' : ''; ?>>All Departments</option>
                    <option value="driver" <?php echo $department_filter == 'driver' ? 'selected' : ''; ?>>Driver</option>
                    <option value="warehouse" <?php echo $department_filter == 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                    <option value="logistics" <?php echo $department_filter == 'logistics' ? 'selected' : ''; ?>>Logistics</option>
                    <option value="admin" <?php echo $department_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="management" <?php echo $department_filter == 'management' ? 'selected' : ''; ?>>Management</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, Email, or ID" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=onboarding&subpage=document-submission" class="btn btn-secondary btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Apply
            </button>
        </div>
    </form>
</div>

<!-- Document Grid -->
<div style="margin-top: 20px;">
    <div class="filter-title">
        <i class="fas fa-id-card"></i> New Hires Document Status
        <span style="margin-left: auto; font-size: 13px; color: var(--gray);"><?php echo count($new_hires); ?> records found</span>
    </div>
    
    <?php if (empty($new_hires)): ?>
    <div class="empty-state">
        <i class="fas fa-file-upload"></i>
        <h3>No Document Submissions Found</h3>
        <p>No new hires are currently in the document submission phase.</p>
    </div>
    <?php else: ?>
    <div class="document-grid">
        <?php foreach ($new_hires as $hire): 
            $fullName = $hire['first_name'] . ' ' . $hire['last_name'];
            $initials = strtoupper(substr($hire['first_name'] ?? '', 0, 1) . substr($hire['last_name'] ?? '', 0, 1)) ?: '?';
            
            // Handle photo path
            $photoPath = null;
            if (!empty($hire['photo_path'])) {
                $photo_full_path = $_SERVER['DOCUMENT_ROOT'] . '/freight/' . ltrim($hire['photo_path'], '/');
                if (file_exists($photo_full_path)) {
                    $photoPath = $hire['photo_path'];
                }
            }
            
            $verified_percentage = $hire['total_required_docs'] > 0 
                ? round(($hire['verified_docs'] / $hire['total_required_docs']) * 100) 
                : 0;
            
            $progress_color = getDocumentProgressColor($verified_percentage);
            
            $status_color = '#7f8c8d';
            $status_text = 'No Request';
            $card_class = '';
            
            if ($hire['rejected_docs'] > 0) {
                $status_color = '#e74c3c';
                $status_text = 'Has Rejected';
                $card_class = 'rejected';
            } elseif ($hire['expired_docs'] > 0) {
                $status_color = '#f39c12';
                $status_text = 'Has Expired';
                $card_class = 'expired';
            } elseif ($hire['all_docs_verified']) {
                $status_color = '#27ae60';
                $status_text = 'All Verified';
                $card_class = 'verified';
            } elseif ($hire['has_pending_request']) {
                $status_color = '#3498db';
                $status_text = 'Request Sent';
                $card_class = 'pending';
            } elseif ($hire['pending_docs'] > 0 || $hire['verified_docs'] > 0) {
                $status_color = '#f39c12';
                $status_text = 'In Progress';
                $card_class = 'pending';
            }
            
            $last_request = $hire['last_request_date'] ? date('M d, Y', strtotime($hire['last_request_date'])) : 'Never';
        ?>
        <div class="document-card <?php echo $card_class; ?>">
            <div class="card-header">
                <?php if ($photoPath): ?>
                    <img src="<?php echo getDocumentUrl($photoPath); ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="applicant-photo">
                <?php else: ?>
                    <div class="applicant-photo">
                        <?php echo $initials; ?>
                    </div>
                <?php endif; ?>
                
                <div class="applicant-info">
                    <h3><?php echo htmlspecialchars($fullName); ?></h3>
                    <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($hire['position']); ?></p>
                    <p><i class="fas fa-hashtag"></i> <?php echo $hire['application_number']; ?></p>
                </div>
                
                <span class="dept-tag" style="background: <?php echo $dept_colors[$hire['department']] ?? '#0e4c92'; ?>20; color: <?php echo $dept_colors[$hire['department']] ?? '#0e4c92'; ?>;">
                    <?php echo ucfirst($hire['department']); ?>
                </span>
            </div>
            
            <!-- Progress Section -->
            <div class="progress-section">
                <div class="progress-header">
                    <span class="progress-label">Document Progress</span>
                    <span class="progress-value"><?php echo $verified_percentage; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $verified_percentage; ?>%; background: <?php echo $progress_color; ?>;"></div>
                </div>
                <div class="progress-stats">
                    <span><?php echo $hire['verified_docs']; ?>/<?php echo $hire['total_required_docs']; ?> verified</span>
                    <span>
                        <?php if ($hire['pending_docs'] > 0): ?>
                        <span style="color: var(--warning);"><?php echo $hire['pending_docs']; ?> pending</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <!-- Document Summary -->
            <div class="document-summary">
                <div class="summary-item">
                    <div class="summary-count verified"><?php echo $hire['verified_docs']; ?></div>
                    <div class="summary-label">Verified</div>
                </div>
                <div class="summary-item">
                    <div class="summary-count pending"><?php echo $hire['pending_docs']; ?></div>
                    <div class="summary-label">Pending</div>
                </div>
                <div class="summary-item">
                    <div class="summary-count rejected"><?php echo $hire['rejected_docs']; ?></div>
                    <div class="summary-label">Rejected</div>
                </div>
            </div>
            
            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-calendar"></i> Start Date</span>
                    <span class="info-value"><?php echo date('M d, Y', strtotime($hire['start_date'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="info-value" style="font-size: 11px;"><?php echo htmlspecialchars($hire['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-tag"></i> Status</span>
                    <span class="status-badge" style="background: <?php echo $status_color; ?>20; color: <?php echo $status_color; ?>;">
                        <?php echo $status_text; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-clock"></i> Last Request</span>
                    <span class="info-value"><?php echo $last_request; ?></span>
                </div>
            </div>
            
            <!-- Card Actions -->
            <div class="card-actions">
                <!-- Request Documents Button -->
                <form method="POST" style="display: inline;" onsubmit="return confirm('Send document request email to <?php echo addslashes($fullName); ?>? A new link will be generated.');">
                    <input type="hidden" name="new_hire_id" value="<?php echo $hire['new_hire_id']; ?>">
                    <button type="submit" name="request_documents" class="btn-icon request" title="Request Documents (will send new email)">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
                
                <!-- View Documents Button -->
                <button class="btn-icon view" onclick="viewDocuments(<?php echo $hire['new_hire_id']; ?>, '<?php echo addslashes($fullName); ?>')" title="View Documents">
                    <i class="fas fa-eye"></i>
                </button>
                
                <?php if ($hire['all_docs_verified'] && $hire['onboarding_status'] != 'active'): ?>
                <a href="?page=onboarding&subpage=onboarding-dashboard&activate=<?php echo $hire['new_hire_id']; ?>" class="btn-icon activate" title="Ready for Activation">
                    <i class="fas fa-user-check"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- View Documents Modal -->
<div class="modal" id="viewDocumentsModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeViewModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="width: 70px; height: 70px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fas fa-folder-open" style="color: white; font-size: 30px;"></i>
            </div>
            <h2 style="font-size: 24px; color: var(--dark); margin-bottom: 5px;" id="viewModalTitle">Documents</h2>
        </div>
        
        <div id="viewDocumentsContent" style="max-height: 60vh; overflow-y: auto; padding: 10px;">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<!-- Verify Document Modal -->
<div class="modal" id="verifyModal">
    <div class="modal-content" style="max-width: 500px;">
        <button class="modal-close" onclick="closeVerifyModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="width: 70px; height: 70px; background: linear-gradient(135deg, var(--success) 0%, #2ecc71 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fas fa-check-circle" style="color: white; font-size: 30px;"></i>
            </div>
            <h2 style="font-size: 24px; color: var(--dark); margin-bottom: 5px;" id="verifyModalTitle">Verify Document</h2>
        </div>
        
        <form method="POST" id="verifyForm">
            <input type="hidden" name="document_id" id="verify_document_id">
            
            <div style="margin-bottom: 20px; padding: 15px; background: var(--light-gray); border-radius: 12px;">
                <p><strong id="verify_document_name"></strong></p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray); margin-bottom: 5px;">Action *</label>
                <select name="verification_action" id="verification_action" onchange="toggleRejectionReason()" required style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 12px;">
                    <option value="">-- Select Action --</option>
                    <option value="verify">✅ Verify Document</option>
                    <option value="reject">❌ Reject Document</option>
                </select>
            </div>
            
            <div id="rejection_reason_container" style="display: none; margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray); margin-bottom: 5px;">Rejection Reason *</label>
                <textarea name="rejection_reason" rows="4" placeholder="Explain why this document is being rejected. The applicant will receive this explanation via email." style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 12px;"></textarea>
                <small style="color: var(--gray);">This reason will be sent to the applicant's email.</small>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeVerifyModal()">Cancel</button>
                <button type="submit" name="verify_document" class="btn btn-primary">
                    <i class="fas fa-save"></i> Submit
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// View Documents - FIXED VERSION
function viewDocuments(hireId, employeeName) {
    document.getElementById('viewModalTitle').textContent = `Documents - ${employeeName}`;
    
    // Redirect with view_hire parameter
    window.location.href = `?page=onboarding&subpage=document-submission&view_hire=${hireId}`;
}

// Handle view_hire parameter
<?php if (isset($_GET['view_hire'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php
    $hire_id = $_GET['view_hire'];
    
    // Get documents for this hire
    $stmt = $pdo->prepare("
        SELECT od.*, rod.document_name, rod.document_code, rod.category,
               ja.first_name, ja.last_name
        FROM onboarding_documents od
        JOIN required_onboarding_documents rod ON od.document_type = rod.document_code
        JOIN new_hires nh ON od.new_hire_id = nh.id
        JOIN job_applications ja ON nh.applicant_id = ja.id
        WHERE od.new_hire_id = ?
        ORDER BY od.document_type, od.version DESC
    ");
    $stmt->execute([$hire_id]);
    $docs = $stmt->fetchAll();
    
    // Get new hire name
    $stmt = $pdo->prepare("
        SELECT ja.first_name, ja.last_name 
        FROM new_hires nh 
        JOIN job_applications ja ON nh.applicant_id = ja.id 
        WHERE nh.id = ?
    ");
    $stmt->execute([$hire_id]);
    $name = $stmt->fetch();
    ?>
    
    // Set modal title
    document.getElementById('viewModalTitle').textContent = 'Documents - <?php echo addslashes($name['first_name'] . ' ' . $name['last_name']); ?>';
    
    // Generate HTML for documents
    let html = '<div style="margin: 20px 0;">';
    
    <?php if (empty($docs)): ?>
        html += '<div style="text-align: center; padding: 40px;"><i class="fas fa-folder-open" style="font-size: 48px; color: var(--gray); opacity: 0.3;"></i><p style="margin-top: 10px; color: var(--gray);">No documents uploaded yet.</p></div>';
    <?php else: 
        // Group documents by type
        $grouped = [];
        foreach ($docs as $doc) {
            if (!isset($grouped[$doc['document_type']])) {
                $grouped[$doc['document_type']] = [];
            }
            $grouped[$doc['document_type']][] = $doc;
        }
    ?>
        
        <?php foreach ($grouped as $type => $versions): 
            $latest = $versions[0]; // First is latest due to ORDER BY
            $statusClass = $latest['status'] == 'verified' ? 'verified' : ($latest['status'] == 'rejected' ? 'rejected' : 'pending');
            $statusColor = $latest['status'] == 'verified' ? '#27ae60' : ($latest['status'] == 'rejected' ? '#e74c3c' : '#f39c12');
            $statusText = $latest['status'] == 'verified' ? 'Verified' : ($latest['status'] == 'rejected' ? 'Rejected' : 'Pending Review');
            
            // Get the correct document URL using the PHP function
            $documentUrl = getDocumentUrl($latest['file_path']);
        ?>
            html += `
                <div class="document-item <?php echo $statusClass; ?>">
                    <div class="header">
                        <span class="title"><?php echo htmlspecialchars($latest['document_name']); ?></span>
                        <span class="status-badge" style="background: <?php echo $statusColor; ?>20; color: <?php echo $statusColor; ?>;">
                            <?php echo $statusText; ?>
                        </span>
                    </div>
                    <div class="details">
                        <div><i class="fas fa-file"></i> <?php echo htmlspecialchars($latest['document_name']); ?></div>
                        <?php if ($latest['document_number']): ?>
                            <div><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($latest['document_number']); ?></div>
                        <?php endif; ?>
                        <?php if ($latest['issue_date']): ?>
                            <div><i class="fas fa-calendar-alt"></i> Issued: <?php echo date('M d, Y', strtotime($latest['issue_date'])); ?></div>
                        <?php endif; ?>
                        <?php if ($latest['expiry_date']): ?>
                            <div><i class="fas fa-hourglass-end"></i> Expires: <?php echo date('M d, Y', strtotime($latest['expiry_date'])); ?></div>
                        <?php endif; ?>
                        <?php if ($latest['version'] > 1): ?>
                            <div><i class="fas fa-code-branch"></i> Version <?php echo $latest['version']; ?></div>
                        <?php endif; ?>
                        <div><i class="fas fa-clock"></i> Uploaded: <?php echo date('M d, Y H:i', strtotime($latest['uploaded_at'])); ?></div>
                    </div>
                    <div class="actions">
                        <a href="<?php echo $documentUrl; ?>" target="_blank" class="btn btn-secondary btn-sm">
                            <i class="fas fa-eye"></i> View Document
                        </a>
                        <?php if ($latest['status'] == 'pending'): ?>
                            <button class="btn btn-primary btn-sm" onclick="verifyDocument(<?php echo $latest['id']; ?>, '<?php echo addslashes($latest['document_name']); ?>')">
                                <i class="fas fa-check-circle"></i> Verify
                            </button>
                        <?php endif; ?>
                        <?php if ($latest['status'] == 'rejected' && $latest['rejection_reason']): ?>
                            <span style="color: var(--danger); font-size: 12px; margin-left: 10px;">Reason: <?php echo htmlspecialchars($latest['rejection_reason']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (count($versions) > 1): ?>
                        <details style="margin-top: 15px;">
                            <summary style="color: var(--primary); cursor: pointer; font-size: 13px;">Previous versions (<?php echo count($versions) - 1; ?>)</summary>
                            <div style="margin-top: 10px; padding-left: 15px; border-left: 2px solid var(--border);">
                                <?php for ($i = 1; $i < count($versions); $i++): 
                                    $prevDocumentUrl = getDocumentUrl($versions[$i]['file_path']);
                                ?>
                                    <div style="padding: 8px; border-bottom: 1px solid var(--border); font-size: 12px;">
                                        <div><strong>Version <?php echo $versions[$i]['version']; ?></strong> - <?php echo date('M d, Y H:i', strtotime($versions[$i]['uploaded_at'])); ?></div>
                                        <?php if ($versions[$i]['document_number']): ?>
                                            <div>Number: <?php echo htmlspecialchars($versions[$i]['document_number']); ?></div>
                                        <?php endif; ?>
                                        <a href="<?php echo $prevDocumentUrl; ?>" target="_blank" style="color: var(--primary); text-decoration: none;">View Document</a>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            `;
        <?php endforeach; ?>
    <?php endif; ?>
    
    html += '</div>';
    
    document.getElementById('viewDocumentsContent').innerHTML = html;
    
    // Show the modal
    document.getElementById('viewDocumentsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
});
<?php endif; ?>

function closeViewModal() {
    document.getElementById('viewDocumentsModal').classList.remove('active');
    document.body.style.overflow = '';
    
    // Remove view_hire from URL without reloading
    const url = new URL(window.location);
    url.searchParams.delete('view_hire');
    window.history.replaceState({}, '', url);
}

// Verification Functions
function verifyDocument(docId, docName) {
    document.getElementById('verify_document_id').value = docId;
    document.getElementById('verify_document_name').textContent = docName;
    document.getElementById('verifyModalTitle').textContent = `Verify: ${docName}`;
    
    document.getElementById('verifyModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function toggleRejectionReason() {
    const action = document.getElementById('verification_action').value;
    const container = document.getElementById('rejection_reason_container');
    const reasonInput = document.querySelector('[name="rejection_reason"]');
    
    if (action === 'reject') {
        container.style.display = 'block';
        if (reasonInput) reasonInput.setAttribute('required', 'required');
    } else {
        container.style.display = 'none';
        if (reasonInput) reasonInput.removeAttribute('required');
    }
}

function closeVerifyModal() {
    document.getElementById('verifyModal').classList.remove('active');
    document.getElementById('verifyForm').reset();
    document.getElementById('rejection_reason_container').style.display = 'none';
    document.body.style.overflow = '';
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewModal();
        closeVerifyModal();
    }
});

// Close modals when clicking outside
window.onclick = function(event) {
    const viewModal = document.getElementById('viewDocumentsModal');
    const verifyModal = document.getElementById('verifyModal');
    
    if (event.target == viewModal) {
        closeViewModal();
    }
    if (event.target == verifyModal) {
        closeVerifyModal();
    }
}
</script>

<?php
// End output buffering and flush
ob_end_flush();
?>