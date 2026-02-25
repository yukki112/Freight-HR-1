<?php
// apply.php - Public application form with multi-step wizard
require_once 'includes/config.php';

$code = isset($_GET['code']) ? $_GET['code'] : '';
$message = '';
$error = '';

// Get job posting from link code
$job = null;
if ($code) {
    $stmt = $pdo->prepare("
        SELECT * FROM job_postings 
        WHERE link_code = ? AND status = 'published' 
        AND link_expiration > NOW()
    ");
    $stmt->execute([$code]);
    $job = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    try {
        $job_id = $_POST['job_id'];
        
        // Validate required fields
        $required = ['first_name', 'last_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'city', 'province'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("All required fields must be filled out");
            }
        }
        
        // Validate resume upload
        if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Resume/CV is required");
        }
        
        // Generate application number
        $application_number = 'APP-' . date('Y') . date('m') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Handle file uploads
        $upload_dir = 'uploads/applications/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Create applicant folder
        $applicant_folder = $upload_dir . $application_number . '/';
        if (!file_exists($applicant_folder)) {
            mkdir($applicant_folder, 0777, true);
        }
        
        $resume_path = '';
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
            $filename = 'resume.' . $ext;
            $destination = $applicant_folder . $filename;
            
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $destination)) {
                $resume_path = 'uploads/applications/' . $application_number . '/' . $filename;
            }
        }
        
        $cover_letter_path = '';
        if (isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['cover_letter']['name'], PATHINFO_EXTENSION);
            $filename = 'cover_letter.' . $ext;
            $destination = $applicant_folder . $filename;
            
            if (move_uploaded_file($_FILES['cover_letter']['tmp_name'], $destination)) {
                $cover_letter_path = 'uploads/applications/' . $application_number . '/' . $filename;
            }
        }
        
        $photo_path = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = 'photo.' . $ext;
            $destination = $applicant_folder . $filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $photo_path = 'uploads/applications/' . $application_number . '/' . $filename;
            }
        }
        
        // Prepare work experience as JSON
        $work_experience = [];
        if (isset($_POST['company']) && is_array($_POST['company'])) {
            for ($i = 0; $i < count($_POST['company']); $i++) {
                if (!empty($_POST['company'][$i])) {
                    $work_experience[] = [
                        'company' => $_POST['company'][$i],
                        'position' => $_POST['position'][$i] ?? '',
                        'from_year' => $_POST['from_year'][$i] ?? '',
                        'to_year' => $_POST['to_year'][$i] ?? '',
                        'responsibilities' => $_POST['responsibilities'][$i] ?? ''
                    ];
                }
            }
        }
        
        // Prepare references as JSON
        $references = [];
        if (isset($_POST['ref_name']) && is_array($_POST['ref_name'])) {
            for ($i = 0; $i < count($_POST['ref_name']); $i++) {
                if (!empty($_POST['ref_name'][$i])) {
                    $references[] = [
                        'name' => $_POST['ref_name'][$i],
                        'position' => $_POST['ref_position'][$i] ?? '',
                        'company' => $_POST['ref_company'][$i] ?? '',
                        'contact' => $_POST['ref_contact'][$i] ?? '',
                        'relationship' => $_POST['ref_relationship'][$i] ?? ''
                    ];
                }
            }
        }
        
        // FIXED: Count all 38 columns correctly
        $sql = "INSERT INTO job_applications (
            application_number, 
            job_posting_id, 
            job_posting_link_id,
            first_name, 
            last_name, 
            email, 
            phone, 
            birth_date, 
            gender, 
            address, 
            city, 
            province, 
            postal_code,
            elementary_school, 
            elementary_year,
            high_school, 
            high_school_year,
            senior_high, 
            senior_high_strand, 
            senior_high_year,
            college, 
            college_course, 
            college_year,
            vocational, 
            vocational_course, 
            vocational_year,
            work_experience, 
            skills, 
            certifications, 
            references_info,
            resume_path, 
            cover_letter_path, 
            photo_path,
            ip_address, 
            user_agent,
            applied_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";
        
        $stmt = $pdo->prepare($sql);
        
        // FIXED: Match exactly 36 parameters (38 columns - 2 default columns status & notes)
        $params = [
            $application_number,                    // 1
            $job_id,                                // 2
            null,                                   // 3 - job_posting_link_id (null by default)
            $_POST['first_name'],                    // 4
            $_POST['last_name'],                     // 5
            $_POST['email'],                         // 6
            $_POST['phone'],                         // 7
            $_POST['birth_date'],                    // 8
            $_POST['gender'],                         // 9
            $_POST['address'],                        // 10
            $_POST['city'],                           // 11
            $_POST['province'],                       // 12
            $_POST['postal_code'] ?? null,            // 13
            $_POST['elementary_school'] ?? null,      // 14
            $_POST['elementary_year'] ?? null,        // 15
            $_POST['high_school'] ?? null,            // 16
            $_POST['high_school_year'] ?? null,       // 17
            $_POST['senior_high'] ?? null,            // 18
            $_POST['senior_high_strand'] ?? null,     // 19
            $_POST['senior_high_year'] ?? null,       // 20
            $_POST['college'] ?? null,                 // 21
            $_POST['college_course'] ?? null,          // 22
            $_POST['college_year'] ?? null,            // 23
            $_POST['vocational'] ?? null,              // 24
            $_POST['vocational_course'] ?? null,       // 25
            $_POST['vocational_year'] ?? null,         // 26
            !empty($work_experience) ? json_encode($work_experience) : null,  // 27
            $_POST['skills'] ?? null,                  // 28
            $_POST['certifications'] ?? null,          // 29
            !empty($references) ? json_encode($references) : null,  // 30
            $resume_path,                              // 31
            $cover_letter_path,                         // 32
            $photo_path,                                // 33
            $_SERVER['REMOTE_ADDR'] ?? null,            // 34
            $_SERVER['HTTP_USER_AGENT'] ?? null,        // 35
            date('Y-m-d H:i:s')                         // 36 - applied_at
        ];
        
        // Debug: Check if parameter count matches
        // echo "Number of parameters: " . count($params); exit;
        
        $stmt->execute($params);
        
        // Redirect to success page
        header("Location: apply.php?code=$code&success=1&app=" . urlencode($application_number));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check for success
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['app'])) {
    $message = "Application submitted successfully! Your application number is: " . htmlspecialchars($_GET['app']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Position - HR 1 Freight Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* All your CSS here - same as previous */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        body {
            background: #f5f9ff;
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* Brand Color: #0e4c92 */
        .brand-color {
            color: #0e4c92;
        }
        
        .brand-bg {
            background: #0e4c92;
        }
        
        .brand-border {
            border-color: #0e4c92;
        }
        
        .brand-gradient {
            background: linear-gradient(135deg, #0e4c92 0%, #1a5da0 100%);
        }
        
        /* Company Header with Logo */
        .company-header {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(14, 76, 146, 0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .logo-wrapper {
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(14, 76, 146, 0.15);
            border: 2px solid rgba(14, 76, 146, 0.1);
        }
        
        .logo-wrapper img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        
        .company-text h1 {
            font-size: 24px;
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .company-text p {
            color: #718096;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .company-text p i {
            color: #0e4c92;
            width: 16px;
        }
        
        /* Job Header - Step 0 */
        .job-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(14, 76, 146, 0.08);
            border-left: 5px solid #0e4c92;
            position: relative;
            animation: slideDown 0.5s;
        }
        
        .job-badge {
            display: inline-block;
            background: rgba(14, 76, 146, 0.1);
            color: #0e4c92;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .job-header h1 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .job-code {
            color: #0e4c92;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .job-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin: 25px 0;
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .meta-icon {
            width: 40px;
            height: 40px;
            background: rgba(14, 76, 146, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0e4c92;
            font-size: 18px;
        }
        
        .meta-content h4 {
            font-size: 12px;
            color: #718096;
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .meta-content p {
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .job-description {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .job-description h3 {
            font-size: 16px;
            color: #0e4c92;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .job-description p {
            color: #4a5568;
            line-height: 1.6;
        }
        
        /* Application Form */
        .application-form {
            background: white;
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(14, 76, 146, 0.1);
        }
        
        /* Progress Bar */
        .progress-container {
            margin-bottom: 40px;
            position: relative;
        }
        
        .progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }
        
        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step-circle {
            width: 45px;
            height: 45px;
            background: white;
            border: 3px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            color: #a0aec0;
            transition: all 0.3s;
            position: relative;
            z-index: 3;
            background: white;
            font-size: 16px;
        }
        
        .progress-step.active .step-circle {
            border-color: #0e4c92;
            background: #0e4c92;
            color: white;
            box-shadow: 0 5px 20px rgba(14, 76, 146, 0.3);
            transform: scale(1.05);
        }
        
        .progress-step.completed .step-circle {
            border-color: #10b981;
            background: #10b981;
            color: white;
        }
        
        .step-label {
            font-size: 11px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .progress-step.active .step-label {
            color: #0e4c92;
            font-weight: 700;
        }
        
        .progress-line {
            position: absolute;
            top: 22px;
            left: 0;
            right: 0;
            height: 3px;
            background: #e2e8f0;
            z-index: 1;
        }
        
        .progress-line-fill {
            height: 100%;
            background: #0e4c92;
            transition: width 0.3s;
        }
        
        /* Form Steps */
        .form-step {
            display: none;
            animation: fadeIn 0.5s;
        }
        
        .form-step.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .step-title {
            font-size: 22px;
            color: #2d3748;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }
        
        .step-title i {
            width: 45px;
            height: 45px;
            background: rgba(14, 76, 146, 0.1);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0e4c92;
            font-size: 22px;
        }
        
        /* Form Sections */
        .form-section {
            background: #f8fafc;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(14, 76, 146, 0.1);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(14, 76, 146, 0.1);
        }
        
        .section-header h3 {
            font-size: 18px;
            color: #2d3748;
            font-weight: 600;
        }
        
        .section-header i {
            color: #0e4c92;
            font-size: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
        }
        
        .form-group label .required {
            color: #e53e3e;
            margin-left: 3px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0e4c92;
            box-shadow: 0 0 0 3px rgba(14, 76, 146, 0.1);
        }
        
        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: #0e4c92;
        }
        
        /* Education Cards */
        .education-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(14, 76, 146, 0.1);
            transition: all 0.3s;
        }
        
        .education-card:hover {
            box-shadow: 0 10px 30px rgba(14, 76, 146, 0.1);
            border-color: #0e4c92;
        }
        
        .education-card h4 {
            color: #0e4c92;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .education-card h4 i {
            font-size: 18px;
        }
        
        /* Experience Entry */
        .experience-entry {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(14, 76, 146, 0.1);
            position: relative;
        }
        
        .remove-entry {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #fee2e2;
            color: #ef4444;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .remove-entry:hover {
            background: #ef4444;
            color: white;
        }
        
        .add-more-btn {
            background: white;
            border: 2px dashed #0e4c92;
            color: #0e4c92;
            padding: 15px;
            border-radius: 12px;
            width: 100%;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .add-more-btn:hover {
            background: rgba(14, 76, 146, 0.05);
            border-style: solid;
        }
        
        /* File Upload */
        .file-upload-area {
            border: 3px dashed rgba(14, 76, 146, 0.2);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .file-upload-area:hover {
            border-color: #0e4c92;
            background: rgba(14, 76, 146, 0.02);
        }
        
        .file-upload-area i {
            font-size: 48px;
            color: #0e4c92;
            margin-bottom: 10px;
        }
        
        .file-upload-area p {
            color: #2d3748;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .file-upload-area small {
            color: #718096;
            font-size: 12px;
        }
        
        .file-info {
            display: none;
            background: rgba(14, 76, 146, 0.05);
            border-radius: 12px;
            padding: 12px 15px;
            margin-top: 10px;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #0e4c92;
        }
        
        .file-info.active {
            display: flex;
        }
        
        .file-info i {
            color: #10b981;
            font-size: 18px;
        }
        
        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            gap: 15px;
            margin-top: 40px;
        }
        
        .nav-btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .nav-btn.prev {
            background: #f1f5f9;
            color: #475569;
        }
        
        .nav-btn.prev:hover:not(:disabled) {
            background: #e2e8f0;
            transform: translateX(-3px);
        }
        
        .nav-btn.next {
            background: #0e4c92;
            color: white;
            flex: 1;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(14, 76, 146, 0.2);
        }
        
        .nav-btn.next:hover:not(:disabled) {
            background: #1a5da0;
            transform: translateX(3px);
            box-shadow: 0 15px 30px rgba(14, 76, 146, 0.3);
        }
        
        .nav-btn.submit {
            background: #10b981;
            color: white;
            flex: 1;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);
        }
        
        .nav-btn.submit:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.3);
        }
        
        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Alerts */
        .alert {
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            animation: slideDown 0.3s;
            background: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .alert-success {
            border-left: 6px solid #10b981;
        }
        
        .alert-success i {
            color: #10b981;
        }
        
        .alert-danger {
            border-left: 6px solid #ef4444;
        }
        
        .alert-danger i {
            color: #ef4444;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 20px 15px;
            }
            
            .application-form {
                padding: 25px;
            }
            
            .company-header {
                flex-direction: column;
                text-align: center;
            }
            
            .progress-step .step-label {
                display: none;
            }
            
            .step-circle {
                width: 40px;
                height: 40px;
                font-size: 14px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-navigation {
                flex-direction: column;
            }
            
            .nav-btn {
                width: 100%;
                justify-content: center;
            }
            
            .job-meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Company Header with Logo -->
        <div class="company-header">
            <div class="logo-wrapper">
                <img src="assets/images/logo.png" alt="HR 1 Freight Logo" 
                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=HR1&background=0e4c92&color=fff&size=100&bold=true&format=png';">
            </div>
            <div class="company-text">
                <h1>HR 1 Freight Management</h1>
                <p>
                    <span><i class="fas fa-building"></i> Human Resources</span>
                    <span><i class="fas fa-users"></i> Talent Acquisition</span>
                    <span><i class="fas fa-clock"></i> <?php echo date('F j, Y'); ?></span>
                </p>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle fa-3x"></i>
            <div>
                <h3 style="font-size: 22px; margin-bottom: 8px;">Application Received!</h3>
                <p style="font-size: 16px; margin-bottom: 5px;"><?php echo $message; ?></p>
                <p style="color: #64748b; font-size: 14px;">We'll review your application and contact you soon.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle fa-3x"></i>
            <div>
                <h3 style="font-size: 22px; margin-bottom: 8px;">Submission Error</h3>
                <p style="font-size: 16px;"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!$job && !$message): ?>
        <div class="job-header">
            <div class="job-badge">
                <i class="fas fa-exclamation-triangle"></i> Link Error
            </div>
            <h1>Invalid or Expired Link</h1>
            <p style="color: #64748b; margin-top: 15px; font-size: 16px;">This application link is invalid or has expired. Please contact the HR department for assistance.</p>
            <div style="margin-top: 25px;">
                <a href="mailto:hr@freight.com" style="background: #0e4c92; color: white; padding: 12px 25px; border-radius: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-envelope"></i> Contact HR
                </a>
            </div>
        </div>
        <?php elseif ($job && !$message): ?>
        
        <!-- Job Header - Step 0 -->
        <div class="job-header">
            <div class="job-badge">
                <i class="fas fa-briefcase"></i> Step 0: Job Overview
            </div>
            <h1><?php echo htmlspecialchars($job['title']); ?></h1>
            <div class="job-code">
                <i class="fas fa-hashtag" style="color: #0e4c92;"></i> <?php echo htmlspecialchars($job['job_code']); ?>
            </div>
            
            <div class="job-meta-grid">
                <div class="meta-item">
                    <div class="meta-icon"><i class="fas fa-building"></i></div>
                    <div class="meta-content">
                        <h4>Department</h4>
                        <p><?php echo ucfirst($job['department']); ?></p>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-icon"><i class="fas fa-clock"></i></div>
                    <div class="meta-content">
                        <h4>Employment Type</h4>
                        <p><?php echo ucfirst(str_replace('_', ' ', $job['employment_type'])); ?></p>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="meta-content">
                        <h4>Location</h4>
                        <p><?php echo $job['location'] ?: 'Not specified'; ?></p>
                    </div>
                </div>
                <div class="meta-item">
                    <div class="meta-icon"><i class="fas fa-calendar-times"></i></div>
                    <div class="meta-content">
                        <h4>Closing Date</h4>
                        <p><?php echo date('M d, Y', strtotime($job['closing_date'])); ?></p>
                    </div>
                </div>
            </div>
            
            <?php if ($job['description']): ?>
            <div class="job-description">
                <h3><i class="fas fa-info-circle"></i> Job Description</h3>
                <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($job['requirements']): ?>
            <div class="job-description" style="margin-top: 15px;">
                <h3><i class="fas fa-clipboard-list"></i> Requirements</h3>
                <p><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Multi-Step Application Form -->
        <form method="POST" enctype="multipart/form-data" class="application-form" id="applicationForm">
            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
            
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-step active" data-step="1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Personal</div>
                    </div>
                    <div class="progress-step" data-step="2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Education</div>
                    </div>
                    <div class="progress-step" data-step="3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Experience</div>
                    </div>
                    <div class="progress-step" data-step="4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Skills</div>
                    </div>
                    <div class="progress-step" data-step="5">
                        <div class="step-circle">5</div>
                        <div class="step-label">References</div>
                    </div>
                    <div class="progress-step" data-step="6">
                        <div class="step-circle">6</div>
                        <div class="step-label">Documents</div>
                    </div>
                </div>
                <div class="progress-line">
                    <div class="progress-line-fill" style="width: 0%;"></div>
                </div>
            </div>
            
            <!-- STEP 1: Personal Information -->
            <div class="form-step active" data-step="1">
                <div class="step-title">
                    <i class="fas fa-user"></i>
                    Personal Information
                </div>
                
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-id-card"></i>
                        <h3>Basic Details</h3>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" required placeholder="Enter first name">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" required placeholder="Enter last name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" required placeholder="you@example.com">
                        </div>
                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" required placeholder="e.g., 0998 431 9585">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date of Birth <span class="required">*</span></label>
                            <input type="date" name="birth_date" required>
                        </div>
                        <div class="form-group">
                            <label>Gender <span class="required">*</span></label>
                            <select name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Address Details</h3>
                    </div>
                    
                    <div class="form-group">
                        <label>Street Address <span class="required">*</span></label>
                        <textarea name="address" rows="2" required placeholder="House number, street, barangay"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>City <span class="required">*</span></label>
                            <input type="text" name="city" required placeholder="e.g., Manila">
                        </div>
                        <div class="form-group">
                            <label>Province <span class="required">*</span></label>
                            <input type="text" name="province" required placeholder="e.g., Metro Manila">
                        </div>
                        <div class="form-group">
                            <label>Postal Code</label>
                            <input type="text" name="postal_code" placeholder="e.g., 1000">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- STEP 2: Education Background -->
            <div class="form-step" data-step="2">
                <div class="step-title">
                    <i class="fas fa-graduation-cap"></i>
                    Education Background
                </div>
                
                <!-- Elementary -->
                <div class="education-card">
                    <h4><i class="fas fa-school" style="color: #0e4c92;"></i> Elementary Education</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>School Name</label>
                            <input type="text" name="elementary_school" placeholder="Elementary school name">
                        </div>
                        <div class="form-group">
                            <label>Year Graduated</label>
                            <input type="text" name="elementary_year" placeholder="e.g., 2010">
                        </div>
                    </div>
                </div>
                
                <!-- High School -->
                <div class="education-card">
                    <h4><i class="fas fa-school" style="color: #0e4c92;"></i> High School</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>School Name</label>
                            <input type="text" name="high_school" placeholder="High school name">
                        </div>
                        <div class="form-group">
                            <label>Year Graduated</label>
                            <input type="text" name="high_school_year" placeholder="e.g., 2014">
                        </div>
                    </div>
                </div>
                
                <!-- Senior High School -->
                <div class="education-card">
                    <h4><i class="fas fa-school" style="color: #0e4c92;"></i> Senior High School</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>School Name</label>
                            <input type="text" name="senior_high" placeholder="Senior high school name">
                        </div>
                        <div class="form-group">
                            <label>Strand</label>
                            <input type="text" name="senior_high_strand" placeholder="e.g., STEM, ABM">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Year Graduated</label>
                            <input type="text" name="senior_high_year" placeholder="e.g., 2016">
                        </div>
                    </div>
                </div>
                
                <!-- College -->
                <div class="education-card">
                    <h4><i class="fas fa-university" style="color: #0e4c92;"></i> College / University</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>School Name</label>
                            <input type="text" name="college" placeholder="College/University name">
                        </div>
                        <div class="form-group">
                            <label>Course / Degree</label>
                            <input type="text" name="college_course" placeholder="e.g., BS Information Technology">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Year Graduated</label>
                            <input type="text" name="college_year" placeholder="e.g., 2020">
                        </div>
                    </div>
                </div>
                
                <!-- Vocational -->
                <div class="education-card">
                    <h4><i class="fas fa-tools" style="color: #0e4c92;"></i> Vocational / Technical</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>School/Training Center</label>
                            <input type="text" name="vocational" placeholder="Training center name">
                        </div>
                        <div class="form-group">
                            <label>Course / Program</label>
                            <input type="text" name="vocational_course" placeholder="e.g., Heavy Equipment Operation">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Year Completed</label>
                            <input type="text" name="vocational_year" placeholder="e.g., 2018">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- STEP 3: Work Experience -->
            <div class="form-step" data-step="3">
                <div class="step-title">
                    <i class="fas fa-briefcase"></i>
                    Work Experience
                </div>
                
                <div id="experience-container">
                    <!-- Experience entries will be added here -->
                    <div class="experience-entry">
                        <button type="button" class="remove-entry" onclick="removeExperience(this)">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Company Name</label>
                                <input type="text" name="company[]" placeholder="Company name">
                            </div>
                            <div class="form-group">
                                <label>Position / Role</label>
                                <input type="text" name="position[]" placeholder="Your position">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>From Year</label>
                                <input type="text" name="from_year[]" placeholder="e.g., 2020">
                            </div>
                            <div class="form-group">
                                <label>To Year</label>
                                <input type="text" name="to_year[]" placeholder="e.g., 2023 (or Present)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Key Responsibilities</label>
                            <textarea name="responsibilities[]" rows="2" placeholder="Describe your responsibilities..."></textarea>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="add-more-btn" onclick="addExperience()">
                    <i class="fas fa-plus-circle"></i> Add Another Work Experience
                </button>
            </div>
            
            <!-- STEP 4: Skills & Certifications -->
            <div class="form-step" data-step="4">
                <div class="step-title">
                    <i class="fas fa-cogs"></i>
                    Skills & Certifications
                </div>
                
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-star"></i>
                        <h3>Skills</h3>
                    </div>
                    
                    <div class="form-group">
                        <label>Technical & Professional Skills</label>
                        <textarea name="skills" rows="4" placeholder="List your skills (e.g., Forklift Operation, Warehouse Management, Microsoft Office, etc.)"></textarea>
                        <small style="color: #64748b; margin-top: 5px; display: block;">Separate skills with commas</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-certificate"></i>
                        <h3>Certifications & Licenses</h3>
                    </div>
                    
                    <div class="form-group">
                        <label>Professional Certifications</label>
                        <textarea name="certifications" rows="4" placeholder="e.g., Professional Driver's License, TESDA NC II, First Aid Certificate, etc."></textarea>
                        <small style="color: #64748b; margin-top: 5px; display: block;">Include license numbers if applicable</small>
                    </div>
                </div>
            </div>
            
            <!-- STEP 5: References -->
            <div class="form-step" data-step="5">
                <div class="step-title">
                    <i class="fas fa-address-book"></i>
                    Professional References
                </div>
                
                <div id="references-container">
                    <!-- Reference entries will be added here -->
                    <div class="experience-entry">
                        <button type="button" class="remove-entry" onclick="removeReference(this)">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="ref_name[]" placeholder="Reference's full name">
                            </div>
                            <div class="form-group">
                                <label>Position</label>
                                <input type="text" name="ref_position[]" placeholder="e.g., Manager">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Company</label>
                                <input type="text" name="ref_company[]" placeholder="Company name">
                            </div>
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="ref_contact[]" placeholder="Contact number">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Relationship</label>
                            <input type="text" name="ref_relationship[]" placeholder="e.g., Former Supervisor, Colleague">
                        </div>
                    </div>
                </div>
                
                <button type="button" class="add-more-btn" onclick="addReference()">
                    <i class="fas fa-plus-circle"></i> Add Another Reference
                </button>
            </div>
            
            <!-- STEP 6: Documents -->
            <div class="form-step" data-step="6">
                <div class="step-title">
                    <i class="fas fa-file-alt"></i>
                    Document Upload
                </div>
                
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-id-badge"></i>
                        <h3>Profile Photo (1x1)</h3>
                    </div>
                    
                    <div class="file-upload-area" onclick="document.getElementById('photo').click()">
                        <i class="fas fa-camera"></i>
                        <p>Click to upload your photo</p>
                        <small>JPG, PNG (Max 2MB)</small>
                        <input type="file" id="photo" name="photo" accept="image/*" style="display: none;" onchange="updateFileInfo(this, 'photo-info')">
                    </div>
                    <div id="photo-info" class="file-info">
                        <i class="fas fa-check-circle"></i>
                        <span id="photo-name"></span>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-file-pdf"></i>
                        <h3>Resume / CV <span class="required">*</span></h3>
                    </div>
                    
                    <div class="file-upload-area" onclick="document.getElementById('resume').click()">
                        <i class="fas fa-upload"></i>
                        <p>Click to upload your resume</p>
                        <small>PDF, DOC, DOCX (Max 5MB)</small>
                        <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" required style="display: none;" onchange="updateFileInfo(this, 'resume-info')">
                    </div>
                    <div id="resume-info" class="file-info">
                        <i class="fas fa-check-circle"></i>
                        <span id="resume-name"></span>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-envelope"></i>
                        <h3>Cover Letter (Optional)</h3>
                    </div>
                    
                    <div class="file-upload-area" onclick="document.getElementById('cover_letter').click()">
                        <i class="fas fa-file-alt"></i>
                        <p>Click to upload cover letter</p>
                        <small>PDF, DOC, DOCX (Max 5MB)</small>
                        <input type="file" id="cover_letter" name="cover_letter" accept=".pdf,.doc,.docx" style="display: none;" onchange="updateFileInfo(this, 'cover-info')">
                    </div>
                    <div id="cover-info" class="file-info">
                        <i class="fas fa-check-circle"></i>
                        <span id="cover-name"></span>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Buttons -->
            <div class="form-navigation">
                <button type="button" class="nav-btn prev" onclick="prevStep()" id="prevBtn" disabled>
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                <button type="button" class="nav-btn next" onclick="nextStep()" id="nextBtn">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" name="submit_application" class="nav-btn submit" id="submitBtn" style="display: none;">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 6;
        
        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();
            updateButtons();
        });
        
        function nextStep() {
            if (currentStep < totalSteps) {
                // Validate current step
                if (!validateStep(currentStep)) {
                    return;
                }
                
                // Hide current step
                document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.remove('active');
                
                // Show next step
                currentStep++;
                document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active');
                
                // Update progress bar
                updateProgress();
                updateButtons();
                
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }
        
        function prevStep() {
            if (currentStep > 1) {
                // Hide current step
                document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.remove('active');
                
                // Show previous step
                currentStep--;
                document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active');
                
                // Update progress bar
                updateProgress();
                updateButtons();
                
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }
        
        function updateProgress() {
            // Update step circles
            document.querySelectorAll('.progress-step').forEach((step, index) => {
                const stepNum = index + 1;
                step.classList.remove('active', 'completed');
                
                if (stepNum === currentStep) {
                    step.classList.add('active');
                } else if (stepNum < currentStep) {
                    step.classList.add('completed');
                }
            });
            
            // Update progress line
            const progressFill = document.querySelector('.progress-line-fill');
            const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressFill.style.width = progressPercent + '%';
        }
        
        function updateButtons() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');
            
            prevBtn.disabled = currentStep === 1;
            
            if (currentStep === totalSteps) {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'flex';
            } else {
                nextBtn.style.display = 'flex';
                submitBtn.style.display = 'none';
            }
        }
        
        function validateStep(step) {
            // Basic validation for required fields
            if (step === 1) {
                const required = ['first_name', 'last_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'city', 'province'];
                for (let field of required) {
                    const input = document.querySelector(`[name="${field}"]`);
                    if (input && !input.value) {
                        alert(`Please fill in all required fields in Personal Information`);
                        input.focus();
                        return false;
                    }
                }
            }
            return true;
        }
        
        // Experience management
        function addExperience() {
            const container = document.getElementById('experience-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'experience-entry';
            newEntry.innerHTML = `
                <button type="button" class="remove-entry" onclick="removeExperience(this)">
                    <i class="fas fa-times"></i>
                </button>
                <div class="form-row">
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company[]" placeholder="Company name">
                    </div>
                    <div class="form-group">
                        <label>Position / Role</label>
                        <input type="text" name="position[]" placeholder="Your position">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>From Year</label>
                        <input type="text" name="from_year[]" placeholder="e.g., 2020">
                    </div>
                    <div class="form-group">
                        <label>To Year</label>
                        <input type="text" name="to_year[]" placeholder="e.g., 2023 (or Present)">
                    </div>
                </div>
                <div class="form-group">
                    <label>Key Responsibilities</label>
                    <textarea name="responsibilities[]" rows="2" placeholder="Describe your responsibilities..."></textarea>
                </div>
            `;
            container.appendChild(newEntry);
        }
        
        function removeExperience(btn) {
            const container = document.getElementById('experience-container');
            if (container.children.length > 1) {
                btn.closest('.experience-entry').remove();
            } else {
                alert('You need at least one work experience entry.');
            }
        }
        
        // Reference management
        function addReference() {
            const container = document.getElementById('references-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'experience-entry';
            newEntry.innerHTML = `
                <button type="button" class="remove-entry" onclick="removeReference(this)">
                    <i class="fas fa-times"></i>
                </button>
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="ref_name[]" placeholder="Reference's full name">
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="ref_position[]" placeholder="e.g., Manager">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Company</label>
                        <input type="text" name="ref_company[]" placeholder="Company name">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="ref_contact[]" placeholder="Contact number">
                    </div>
                </div>
                <div class="form-group">
                    <label>Relationship</label>
                    <input type="text" name="ref_relationship[]" placeholder="e.g., Former Supervisor, Colleague">
                </div>
            `;
            container.appendChild(newEntry);
        }
        
        function removeReference(btn) {
            const container = document.getElementById('references-container');
            if (container.children.length > 1) {
                btn.closest('.experience-entry').remove();
            } else {
                alert('You need at least one reference.');
            }
        }
        
        // File upload handling
        function updateFileInfo(input, infoId) {
            const info = document.getElementById(infoId);
            const nameSpan = document.getElementById(infoId === 'photo-info' ? 'photo-name' : 
                                                       (infoId === 'resume-info' ? 'resume-name' : 'cover-name'));
            
            if (input.files && input.files[0]) {
                nameSpan.textContent = input.files[0].name;
                info.classList.add('active');
            } else {
                info.classList.remove('active');
            }
        }
        
        // Form submission confirmation
        document.getElementById('applicationForm').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to submit your application? You cannot make changes after submission.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>