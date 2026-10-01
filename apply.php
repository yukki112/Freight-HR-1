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
        $required = ['first_name', 'last_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'city', 'province', 'highest_education', 'skills'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("All required fields must be filled out");
            }
        }
        
        // Validate resume upload
        if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Resume/CV is required");
        }
        
        // Validate photo upload
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Profile photo is required");
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
        
        // Prepare work experience as JSON (respects "no work experience" checkbox)
        $work_experience = [];
        if (!isset($_POST['no_work_experience'])) {
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
        
        // Build education data based on highest_education selection
        $highest = $_POST['highest_education'];
        $elementary_school = null; $elementary_year = null;
        $high_school = null; $high_school_year = null;
        $senior_high = null; $senior_high_strand = null; $senior_high_year = null;
        $college = null; $college_course = null; $college_year = null;
        $vocational = null; $vocational_course = null; $vocational_year = null;
        
        switch ($highest) {
            case 'elementary':
                $elementary_school = $_POST['elementary_school'] ?? null;
                $elementary_year = $_POST['elementary_year'] ?? null;
                break;
            case 'high_school':
                $high_school = $_POST['high_school'] ?? null;
                $high_school_year = $_POST['high_school_year'] ?? null;
                break;
            case 'senior_high':
                $senior_high = $_POST['senior_high'] ?? null;
                $senior_high_strand = $_POST['senior_high_strand'] ?? null;
                $senior_high_year = $_POST['senior_high_year'] ?? null;
                break;
            case 'college':
                $college = $_POST['college'] ?? null;
                $college_course = $_POST['college_course'] ?? null;
                $college_year = $_POST['college_year'] ?? null;
                break;
            case 'vocational':
                $vocational = $_POST['vocational'] ?? null;
                $vocational_course = $_POST['vocational_course'] ?? null;
                $vocational_year = $_POST['vocational_year'] ?? null;
                break;
        }
        
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
        
        $params = [
            $application_number,
            $job_id,
            null,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['birth_date'],
            $_POST['gender'],
            $_POST['address'],
            $_POST['city'],
            $_POST['province'],
            $_POST['postal_code'] ?? null,
            $elementary_school,
            $elementary_year,
            $high_school,
            $high_school_year,
            $senior_high,
            $senior_high_strand,
            $senior_high_year,
            $college,
            $college_course,
            $college_year,
            $vocational,
            $vocational_course,
            $vocational_year,
            !empty($work_experience) ? json_encode($work_experience) : null,
            $_POST['skills'],
            $_POST['certifications'] ?? null,
            !empty($references) ? json_encode($references) : null,
            $resume_path,
            $cover_letter_path,
            $photo_path,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            date('Y-m-d H:i:s')
        ];
        
        $stmt->execute($params);
        
        header("Location: apply.php?code=$code&success=1&app=" . urlencode($application_number));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['app'])) {
    $message = "Application submitted successfully! Your application number is: " . htmlspecialchars($_GET['app']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply — Priority Handling Logistics, Inc.</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0a1929 0%, #1a2942 100%);
            min-height: 100vh;
            padding: 40px 20px;
            color: #f8fafc;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* Company Header */
        .company-header {
            background: rgba(30, 41, 54, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            border: 1px solid rgba(58, 69, 84, 0.5);
        }
        
        .logo-wrapper {
            width: 70px;
            height: 70px;
            background: rgba(14, 165, 233, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(14, 165, 233, 0.15);
            border: 2px solid rgba(14, 165, 233, 0.2);
            padding: 8px;
        }
        
        .logo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 10px;
        }
        
        .company-text h1 {
            font-size: 22px;
            color: #ffffff;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .company-text p {
            color: #94a3b8;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .company-text p span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .company-text p i {
            color: #0ea5e9;
            width: 14px;
        }
        
        /* Job Header */
        .job-header {
            background: rgba(30, 41, 54, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            border-left: 5px solid #0ea5e9;
            border-top: 1px solid rgba(58, 69, 84, 0.5);
            border-right: 1px solid rgba(58, 69, 84, 0.5);
            border-bottom: 1px solid rgba(58, 69, 84, 0.5);
            animation: slideDown 0.5s;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .job-badge {
            display: inline-block;
            background: rgba(14, 165, 233, 0.15);
            color: #0ea5e9;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
            border: 1px solid rgba(14, 165, 233, 0.3);
        }
        
        .job-header h1 {
            font-size: 28px;
            color: #ffffff;
            margin-bottom: 10px;
        }
        
        .job-code {
            color: #0ea5e9;
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
            background: rgba(15, 23, 42, 0.5);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(58, 69, 84, 0.5);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .meta-icon {
            width: 40px;
            height: 40px;
            background: rgba(14, 165, 233, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0ea5e9;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .meta-content h4 {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 500;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .meta-content p {
            font-size: 14px;
            font-weight: 600;
            color: #ffffff;
        }
        
        .job-description {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 16px;
            padding: 20px;
            margin-top: 15px;
            border: 1px solid rgba(58, 69, 84, 0.5);
        }
        
        .job-description h3 {
            font-size: 15px;
            color: #0ea5e9;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .job-description p {
            color: #cbd5e1;
            line-height: 1.7;
            font-size: 14px;
        }
        
        /* Application Form */
        .application-form {
            background: rgba(30, 41, 54, 0.85);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(58, 69, 84, 0.5);
        }
        
        /* Progress Bar */
        .progress-container {
            margin-bottom: 40px;
            position: relative;
            padding: 0 10px;
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
            width: 42px;
            height: 42px;
            background: #1e2936;
            border: 3px solid #3a4554;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 700;
            color: #64748b;
            transition: all 0.3s;
            position: relative;
            z-index: 3;
            font-size: 14px;
        }
        
        .progress-step.active .step-circle {
            border-color: #0ea5e9;
            background: #0ea5e9;
            color: white;
            box-shadow: 0 0 0 6px rgba(14, 165, 233, 0.15), 0 5px 20px rgba(14, 165, 233, 0.4);
            transform: scale(1.08);
        }
        
        .progress-step.completed .step-circle {
            border-color: #10b981;
            background: #10b981;
            color: white;
        }
        
        .progress-step.completed .step-circle::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 14px;
        }
        
        .progress-step.completed .step-circle {
            font-size: 0;
        }
        
        .progress-step.completed .step-circle::before {
            font-size: 14px;
        }
        
        .step-label {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .progress-step.active .step-label {
            color: #0ea5e9;
            font-weight: 700;
        }
        
        .progress-line {
            position: absolute;
            top: 20px;
            left: 5%;
            right: 5%;
            height: 3px;
            background: #3a4554;
            z-index: 1;
            border-radius: 3px;
        }
        
        .progress-line-fill {
            height: 100%;
            background: linear-gradient(90deg, #0ea5e9, #10b981);
            transition: width 0.4s ease;
            border-radius: 3px;
        }
        
        /* Form Steps */
        .form-step {
            display: none;
        }
        
        .form-step.active {
            display: block;
            animation: fadeIn 0.4s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .step-title {
            font-size: 22px;
            color: #ffffff;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }
        
        .step-title i {
            width: 45px;
            height: 45px;
            background: rgba(14, 165, 233, 0.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0ea5e9;
            font-size: 20px;
        }
        
        /* Form Sections */
        .form-section {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(58, 69, 84, 0.5);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(14, 165, 233, 0.15);
        }
        
        .section-header h3 {
            font-size: 16px;
            color: #ffffff;
            font-weight: 600;
        }
        
        .section-header i {
            color: #0ea5e9;
            font-size: 18px;
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
            color: #cbd5e1;
            margin-bottom: 8px;
        }
        
        .form-group label .required {
            color: #ef4444;
            margin-left: 3px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #3a4554;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            background: #1e2936;
            color: #e2e8f0;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
            background: #1e2936;
        }
        
        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: rgba(14, 165, 233, 0.6);
        }
        
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #64748b;
        }
        
        .form-group select option {
            background: #1e2936;
            color: #e2e8f0;
        }
        
        .form-group small {
            display: block;
            color: #64748b;
            margin-top: 6px;
            font-size: 12px;
        }
        
        /* Highest Education Selector */
        .edu-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .edu-option {
            position: relative;
            cursor: pointer;
        }
        
        .edu-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        
        .edu-option-inner {
            padding: 16px 12px;
            border: 2px solid #3a4554;
            border-radius: 14px;
            text-align: center;
            transition: all 0.3s;
            background: #1e2936;
        }
        
        .edu-option-inner i {
            font-size: 22px;
            color: #64748b;
            display: block;
            margin-bottom: 8px;
            transition: all 0.3s;
        }
        
        .edu-option-inner span {
            font-size: 12px;
            font-weight: 600;
            color: #cbd5e1;
            display: block;
        }
        
        .edu-option input:checked + .edu-option-inner {
            border-color: #0ea5e9;
            background: rgba(14, 165, 233, 0.1);
            box-shadow: 0 5px 20px rgba(14, 165, 233, 0.2);
            transform: translateY(-2px);
        }
        
        .edu-option input:checked + .edu-option-inner i {
            color: #0ea5e9;
        }
        
        .edu-option input:checked + .edu-option-inner span {
            color: #ffffff;
        }
        
        .edu-option-inner:hover {
            border-color: rgba(14, 165, 233, 0.5);
        }
        
        .edu-detail {
            display: none;
            background: rgba(14, 165, 233, 0.05);
            border-radius: 16px;
            padding: 25px;
            border: 1px dashed rgba(14, 165, 233, 0.3);
            animation: fadeIn 0.4s;
        }
        
        .edu-detail.active {
            display: block;
        }
        
        .edu-detail h4 {
            color: #0ea5e9;
            margin-bottom: 20px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Experience Entry */
        .experience-entry {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 18px;
            border: 1px solid rgba(58, 69, 84, 0.5);
            position: relative;
            transition: all 0.3s;
        }
        
        .experience-entry:hover {
            border-color: rgba(14, 165, 233, 0.3);
        }
        
        .remove-entry {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            width: 32px;
            height: 32px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .remove-entry:hover {
            background: #ef4444;
            color: white;
            transform: scale(1.1);
        }
        
        .add-more-btn {
            background: transparent;
            border: 2px dashed rgba(14, 165, 233, 0.5);
            color: #0ea5e9;
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
            background: rgba(14, 165, 233, 0.1);
            border-style: solid;
            border-color: #0ea5e9;
        }
        
        /* No Work Experience Checkbox */
        .no-exp-checkbox {
            background: rgba(14, 165, 233, 0.08);
            border: 2px solid rgba(14, 165, 233, 0.3);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .no-exp-checkbox:hover {
            background: rgba(14, 165, 233, 0.12);
            border-color: #0ea5e9;
        }
        
        .no-exp-checkbox input[type="checkbox"] {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: #0ea5e9;
            flex-shrink: 0;
        }
        
        .no-exp-checkbox-content {
            flex: 1;
        }
        
        .no-exp-checkbox-content strong {
            display: block;
            color: #ffffff;
            font-size: 15px;
            margin-bottom: 3px;
        }
        
        .no-exp-checkbox-content span {
            color: #94a3b8;
            font-size: 13px;
        }
        
        .no-exp-checkbox input[type="checkbox"]:checked ~ .no-exp-checkbox-content strong {
            color: #0ea5e9;
        }
        
        #experience-container.disabled {
            opacity: 0.4;
            pointer-events: none;
        }
        
        /* File Upload */
        .file-upload-area {
            border: 3px dashed rgba(14, 165, 233, 0.3);
            border-radius: 16px;
            padding: 30px 20px;
            text-align: center;
            background: rgba(15, 23, 42, 0.3);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-area:hover {
            border-color: #0ea5e9;
            background: rgba(14, 165, 233, 0.05);
            transform: translateY(-2px);
        }
        
        .file-upload-area i {
            font-size: 42px;
            color: #0ea5e9;
            margin-bottom: 12px;
            display: block;
        }
        
        .file-upload-area p {
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .file-upload-area small {
            color: #64748b;
            font-size: 12px;
        }
        
        .file-info {
            display: none;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 12px;
            padding: 12px 15px;
            margin-top: 12px;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #10b981;
        }
        
        .file-info.active {
            display: flex;
        }
        
        .file-info i {
            color: #10b981;
            font-size: 18px;
        }
        
        .file-info span {
            color: #cbd5e1;
            font-size: 13px;
            word-break: break-all;
        }
        
        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(58, 69, 84, 0.5);
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
            font-family: inherit;
        }
        
        .nav-btn.prev {
            background: #3a4554;
            color: #cbd5e1;
        }
        
        .nav-btn.prev:hover:not(:disabled) {
            background: #475569;
            transform: translateX(-3px);
        }
        
        .nav-btn.next {
            background: #0ea5e9;
            color: white;
            flex: 1;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(14, 165, 233, 0.25);
        }
        
        .nav-btn.next:hover:not(:disabled) {
            background: #0284c7;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(14, 165, 233, 0.4);
        }
        
        .nav-btn.submit {
            background: #10b981;
            color: white;
            flex: 1;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.25);
        }
        
        .nav-btn.submit:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.4);
        }
        
        .nav-btn:disabled {
            opacity: 0.4;
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
            background: rgba(30, 41, 54, 0.85);
            backdrop-filter: blur(20px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(58, 69, 84, 0.5);
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
        
        .alert h3 {
            color: #ffffff;
        }
        
        .alert p {
            color: #cbd5e1;
        }
        
        /* Error highlight */
        .form-group input.error,
        .form-group select.error,
        .form-group textarea.error {
            border-color: #ef4444;
            background: rgba(239, 68, 68, 0.05);
        }
        
        .field-error {
            color: #ef4444;
            font-size: 12px;
            margin-top: 5px;
            display: none;
            align-items: center;
            gap: 5px;
        }
        
        .field-error.show {
            display: flex;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 20px 15px;
            }
            
            .application-form {
                padding: 25px 20px;
            }
            
            .company-header {
                flex-direction: column;
                text-align: center;
            }
            
            .company-text p {
                justify-content: center;
            }
            
            .progress-step .step-label {
                display: none;
            }
            
            .step-circle {
                width: 36px;
                height: 36px;
                font-size: 13px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-navigation {
                flex-direction: column-reverse;
            }
            
            .nav-btn {
                width: 100%;
                justify-content: center;
            }
            
            .job-meta-grid {
                grid-template-columns: 1fr;
            }
            
            .edu-selector {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Company Header -->
        <div class="company-header">
            <div class="logo-wrapper">
                <img src="assets/images/LOGO.jpg" alt="Priority Handling Logistics Logo">
            </div>
            <div class="company-text">
                <h1>Priority Handling Logistics, Inc.</h1>
                <p>
                    <span><i data-lucide="building-2"></i> Human Resources</span>
                    <span><i data-lucide="users"></i> Talent Acquisition</span>
                    <span><i data-lucide="calendar"></i> <?php echo date('F j, Y'); ?></span>
                </p>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle fa-3x"></i>
            <div>
                <h3 style="font-size: 22px; margin-bottom: 8px;">Application Received!</h3>
                <p style="font-size: 16px; margin-bottom: 5px;"><?php echo $message; ?></p>
                <p style="color: #94a3b8; font-size: 14px;">We'll review your application and contact you soon.</p>
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
            <p style="color: #94a3b8; margin-top: 15px; font-size: 16px;">This application link is invalid or has expired. Please contact the HR department for assistance.</p>
            <div style="margin-top: 25px;">
                <a href="mailto:hr@priorityhandling.com" style="background: #0ea5e9; color: white; padding: 12px 25px; border-radius: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-envelope"></i> Contact HR
                </a>
            </div>
        </div>
        <?php elseif ($job && !$message): ?>
        
        <!-- Job Header -->
        <div class="job-header">
            <div class="job-badge">
                <i class="fas fa-briefcase"></i> Now Hiring
            </div>
            <h1><?php echo htmlspecialchars($job['title']); ?></h1>
            <div class="job-code">
                <i class="fas fa-hashtag" style="color: #0ea5e9;"></i> <?php echo htmlspecialchars($job['job_code']); ?>
            </div>
            
            <div class="job-meta-grid">
                <div class="meta-item">
                    <div class="meta-icon"><i class="fas fa-building"></i></div>
                    <div class="meta-content">
                        <h4>Department</h4>
                        <p><?php echo ucwords(str_replace('_', ' ', $job['department'])); ?></p>
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
        <form method="POST" enctype="multipart/form-data" class="application-form" id="applicationForm" novalidate>
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
                            <input type="text" name="city" required placeholder="e.g., Quezon City">
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
            
            <!-- STEP 2: Highest Education Attained -->
            <div class="form-step" data-step="2">
                <div class="step-title">
                    <i class="fas fa-graduation-cap"></i>
                    Highest Education Attained
                </div>
                
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-award"></i>
                        <h3>Select Your Highest Educational Attainment <span class="required">*</span></h3>
                    </div>
                    
                    <div class="edu-selector">
                        <label class="edu-option">
                            <input type="radio" name="highest_education" value="elementary" onchange="showEduDetail('elementary')" required>
                            <div class="edu-option-inner">
                                <i class="fas fa-book-reader"></i>
                                <span>Elementary</span>
                            </div>
                        </label>
                        
                        <label class="edu-option">
                            <input type="radio" name="highest_education" value="high_school" onchange="showEduDetail('high_school')">
                            <div class="edu-option-inner">
                                <i class="fas fa-school"></i>
                                <span>High School</span>
                            </div>
                        </label>
                        
                        <label class="edu-option">
                            <input type="radio" name="highest_education" value="senior_high" onchange="showEduDetail('senior_high')">
                            <div class="edu-option-inner">
                                <i class="fas fa-user-graduate"></i>
                                <span>Senior High</span>
                            </div>
                        </label>
                        
                        <label class="edu-option">
                            <input type="radio" name="highest_education" value="vocational" onchange="showEduDetail('vocational')">
                            <div class="edu-option-inner">
                                <i class="fas fa-tools"></i>
                                <span>Vocational</span>
                            </div>
                        </label>
                        
                        <label class="edu-option">
                            <input type="radio" name="highest_education" value="college" onchange="showEduDetail('college')">
                            <div class="edu-option-inner">
                                <i class="fas fa-university"></i>
                                <span>College</span>
                            </div>
                        </label>
                    </div>
                    
                    <!-- Elementary Detail -->
                    <div class="edu-detail" id="edu-elementary">
                        <h4><i class="fas fa-book-reader"></i> Elementary Education</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>School Name <span class="required">*</span></label>
                                <input type="text" name="elementary_school" placeholder="Elementary school name">
                            </div>
                            <div class="form-group">
                                <label>Year Graduated <span class="required">*</span></label>
                                <input type="text" name="elementary_year" placeholder="e.g., 2010">
                            </div>
                        </div>
                    </div>
                    
                    <!-- High School Detail -->
                    <div class="edu-detail" id="edu-high_school">
                        <h4><i class="fas fa-school"></i> High School Education</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>School Name <span class="required">*</span></label>
                                <input type="text" name="high_school" placeholder="High school name">
                            </div>
                            <div class="form-group">
                                <label>Year Graduated <span class="required">*</span></label>
                                <input type="text" name="high_school_year" placeholder="e.g., 2014">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Senior High Detail -->
                    <div class="edu-detail" id="edu-senior_high">
                        <h4><i class="fas fa-user-graduate"></i> Senior High School Education</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>School Name <span class="required">*</span></label>
                                <input type="text" name="senior_high" placeholder="Senior high school name">
                            </div>
                            <div class="form-group">
                                <label>Strand <span class="required">*</span></label>
                                <input type="text" name="senior_high_strand" placeholder="e.g., STEM, ABM, HUMSS">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Year Graduated <span class="required">*</span></label>
                                <input type="text" name="senior_high_year" placeholder="e.g., 2016">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vocational Detail -->
                    <div class="edu-detail" id="edu-vocational">
                        <h4><i class="fas fa-tools"></i> Vocational / Technical Education</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>School / Training Center <span class="required">*</span></label>
                                <input type="text" name="vocational" placeholder="Training center name">
                            </div>
                            <div class="form-group">
                                <label>Course / Program <span class="required">*</span></label>
                                <input type="text" name="vocational_course" placeholder="e.g., Heavy Equipment Operation">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Year Completed <span class="required">*</span></label>
                                <input type="text" name="vocational_year" placeholder="e.g., 2018">
                            </div>
                        </div>
                    </div>
                    
                    <!-- College Detail -->
                    <div class="edu-detail" id="edu-college">
                        <h4><i class="fas fa-university"></i> College / University Education</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>School Name <span class="required">*</span></label>
                                <input type="text" name="college" placeholder="College/University name">
                            </div>
                            <div class="form-group">
                                <label>Course / Degree <span class="required">*</span></label>
                                <input type="text" name="college_course" placeholder="e.g., BS Information Technology">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Year Graduated <span class="required">*</span></label>
                                <input type="text" name="college_year" placeholder="e.g., 2020">
                            </div>
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
                
                <!-- No Experience Checkbox -->
                <label class="no-exp-checkbox">
                    <input type="checkbox" name="no_work_experience" id="noExpCheckbox" onchange="toggleNoExperience(this)">
                    <div class="no-exp-checkbox-content">
                        <strong>I don't have any work experience yet</strong>
                        <span>Check this box if you're a fresh graduate or first-time job seeker</span>
                    </div>
                </label>
                
                <div id="experience-container">
                    <div class="experience-entry">
                        <button type="button" class="remove-entry" onclick="removeExperience(this)">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Company Name <span class="required">*</span></label>
                                <input type="text" name="company[]" placeholder="Company name">
                            </div>
                            <div class="form-group">
                                <label>Position / Role <span class="required">*</span></label>
                                <input type="text" name="position[]" placeholder="Your position">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>From Year <span class="required">*</span></label>
                                <input type="text" name="from_year[]" placeholder="e.g., 2020">
                            </div>
                            <div class="form-group">
                                <label>To Year <span class="required">*</span></label>
                                <input type="text" name="to_year[]" placeholder="e.g., 2023 (or Present)">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Key Responsibilities <span class="required">*</span></label>
                            <textarea name="responsibilities[]" rows="2" placeholder="Describe your responsibilities..."></textarea>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="add-more-btn" onclick="addExperience()" id="addExpBtn">
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
                        <h3>Skills <span class="required">*</span></h3>
                    </div>
                    
                    <div class="form-group">
                        <label>Technical & Professional Skills <span class="required">*</span></label>
                        <textarea name="skills" rows="5" required placeholder="List your skills (e.g., Forklift Operation, Warehouse Management, Microsoft Office, etc.)"></textarea>
                        <small>Separate skills with commas</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-certificate"></i>
                        <h3>Certifications & Licenses</h3>
                    </div>
                    
                    <div class="form-group">
                        <label>Professional Certifications</label>
                        <textarea name="certifications" rows="5" placeholder="e.g., Professional Driver's License, TESDA NC II, First Aid Certificate, etc."></textarea>
                        <small>Include license numbers if applicable (optional)</small>
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
                    <div class="experience-entry">
                        <button type="button" class="remove-entry" onclick="removeReference(this)">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name <span class="required">*</span></label>
                                <input type="text" name="ref_name[]" placeholder="Reference's full name">
                            </div>
                            <div class="form-group">
                                <label>Position <span class="required">*</span></label>
                                <input type="text" name="ref_position[]" placeholder="e.g., Manager">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Company <span class="required">*</span></label>
                                <input type="text" name="ref_company[]" placeholder="Company name">
                            </div>
                            <div class="form-group">
                                <label>Contact Number <span class="required">*</span></label>
                                <input type="text" name="ref_contact[]" placeholder="Contact number">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Relationship <span class="required">*</span></label>
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
                        <h3>Profile Photo (1x1) <span class="required">*</span></h3>
                    </div>
                    
                    <div class="file-upload-area" onclick="document.getElementById('photo').click()">
                        <i class="fas fa-camera"></i>
                        <p>Click to upload your photo</p>
                        <small>JPG, PNG (Max 2MB) — Required</small>
                        <input type="file" id="photo" name="photo" accept="image/*" style="display: none;" required onchange="updateFileInfo(this, 'photo-info', 'photo-name')">
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
                        <small>PDF, DOC, DOCX (Max 5MB) — Required</small>
                        <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" required style="display: none;" onchange="updateFileInfo(this, 'resume-info', 'resume-name')">
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
                        <input type="file" id="cover_letter" name="cover_letter" accept=".pdf,.doc,.docx" style="display: none;" onchange="updateFileInfo(this, 'cover-info', 'cover-name')">
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
        
        // Get the form container for scroll positioning
        const formContainer = document.querySelector('.application-form');
        const jobHeader = document.querySelector('.job-header');
        
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();
            updateButtons();
        });
        
        // KEY FIX: Scroll to the form container (or job header) instead of top of page
        // This keeps user right where the form is, not at the very top
        function scrollToFormTop() {
            const target = formContainer || jobHeader;
            if (target) {
                const yOffset = -20; // Small offset from top
                const y = target.getBoundingClientRect().top + window.pageYOffset + yOffset;
                window.scrollTo({ top: y, behavior: 'smooth' });
            }
        }
        
        function nextStep() {
            if (currentStep < totalSteps) {
                if (!validateStep(currentStep)) return;
                
                document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.remove('active');
                currentStep++;
                document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active');
                
                updateProgress();
                updateButtons();
                scrollToFormTop();  // ← FIXED: stays at form, no jump to top
            }
        }
        
        function prevStep() {
            if (currentStep > 1) {
                document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.remove('active');
                currentStep--;
                document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active');
                
                updateProgress();
                updateButtons();
                scrollToFormTop();  // ← FIXED
            }
        }
        
        function updateProgress() {
            document.querySelectorAll('.progress-step').forEach((step, index) => {
                const stepNum = index + 1;
                step.classList.remove('active', 'completed');
                if (stepNum === currentStep) {
                    step.classList.add('active');
                } else if (stepNum < currentStep) {
                    step.classList.add('completed');
                }
            });
            
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
        
        // Show selected education detail only
        function showEduDetail(type) {
            // Hide all edu-detail blocks
            document.querySelectorAll('.edu-detail').forEach(el => el.classList.remove('active'));
            
            // Show the selected one
            const target = document.getElementById('edu-' + type);
            if (target) {
                target.classList.add('active');
                
                // Toggle required attributes on inputs of selected detail only
                document.querySelectorAll('.edu-detail input').forEach(input => {
                    input.required = false;
                });
                
                target.querySelectorAll('input').forEach(input => {
                    input.required = true;
                });
            }
        }
        
        // Toggle work experience container when "no experience" is checked
        function toggleNoExperience(checkbox) {
            const container = document.getElementById('experience-container');
            const addBtn = document.getElementById('addExpBtn');
            const inputs = container.querySelectorAll('input, textarea');
            
            if (checkbox.checked) {
                container.classList.add('disabled');
                addBtn.style.display = 'none';
                inputs.forEach(input => {
                    input.required = false;
                    input.value = '';
                });
            } else {
                container.classList.remove('disabled');
                addBtn.style.display = 'flex';
                // Mark first entry inputs as required
                const firstEntry = container.querySelector('.experience-entry');
                if (firstEntry) {
                    firstEntry.querySelectorAll('input, textarea').forEach(input => {
                        input.required = true;
                    });
                }
            }
        }
        
        // Validation per step
        function validateStep(step) {
            const currentFormStep = document.querySelector(`.form-step[data-step="${step}"]`);
            const requiredInputs = currentFormStep.querySelectorAll('[required]');
            let valid = true;
            let firstInvalid = null;
            
            // Clear previous error styles
            currentFormStep.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
            
            requiredInputs.forEach(input => {
                // Skip hidden/disabled inputs
                if (input.closest('.disabled')) return;
                
                let value = input.value.trim();
                
                if (!value) {
                    input.classList.add('error');
                    if (!firstInvalid) firstInvalid = input;
                    valid = false;
                } else if (input.type === 'email') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        input.classList.add('error');
                        if (!firstInvalid) firstInvalid = input;
                        valid = false;
                    }
                } else if (input.type === 'radio') {
                    // Handled separately below
                }
            });
            
            // Special check for radio group (highest_education)
            if (step === 2) {
                const eduSelected = currentFormStep.querySelector('input[name="highest_education"]:checked');
                if (!eduSelected) {
                    valid = false;
                    alert('Please select your highest educational attainment.');
                    return false;
                }
                // Check that the visible detail block has all required filled
                const activeDetail = currentFormStep.querySelector('.edu-detail.active');
                if (activeDetail) {
                    activeDetail.querySelectorAll('input').forEach(input => {
                        if (!input.value.trim()) {
                            input.classList.add('error');
                            if (!firstInvalid) firstInvalid = input;
                            valid = false;
                        }
                    });
                }
            }
            
            // Step 3: Work experience check
            if (step === 3) {
                const noExp = document.getElementById('noExpCheckbox').checked;
                if (!noExp) {
                    const firstEntry = document.querySelector('#experience-container .experience-entry');
                    if (firstEntry) {
                        firstEntry.querySelectorAll('input, textarea').forEach(input => {
                            if (!input.value.trim()) {
                                input.classList.add('error');
                                if (!firstInvalid) firstInvalid = input;
                                valid = false;
                            }
                        });
                    }
                }
            }
            
            // Step 5: All reference fields required
            if (step === 5) {
                const refs = document.querySelectorAll('#references-container .experience-entry');
                refs.forEach(ref => {
                    ref.querySelectorAll('input').forEach(input => {
                        if (!input.value.trim()) {
                            input.classList.add('error');
                            if (!firstInvalid) firstInvalid = input;
                            valid = false;
                        }
                    });
                });
            }
            
            // Step 6: File uploads
            if (step === 6) {
                const photo = document.getElementById('photo');
                const resume = document.getElementById('resume');
                
                if (!photo.files.length) {
                    valid = false;
                    alert('Please upload your profile photo.');
                    photo.parentElement.classList.add('error');
                    if (!firstInvalid) firstInvalid = photo.parentElement;
                }
                if (!resume.files.length) {
                    valid = false;
                    if (!firstInvalid) firstInvalid = resume.parentElement;
                }
            }
            
            if (!valid && firstInvalid) {
                const label = firstInvalid.closest('.form-group')?.querySelector('label')?.innerText.replace('*', '').trim();
                if (label && step !== 6) {
                    alert(`Please fill in: ${label}`);
                }
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            return valid;
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
                        <label>Company Name <span class="required">*</span></label>
                        <input type="text" name="company[]" placeholder="Company name" required>
                    </div>
                    <div class="form-group">
                        <label>Position / Role <span class="required">*</span></label>
                        <input type="text" name="position[]" placeholder="Your position" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>From Year <span class="required">*</span></label>
                        <input type="text" name="from_year[]" placeholder="e.g., 2020" required>
                    </div>
                    <div class="form-group">
                        <label>To Year <span class="required">*</span></label>
                        <input type="text" name="to_year[]" placeholder="e.g., 2023 (or Present)" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Key Responsibilities <span class="required">*</span></label>
                    <textarea name="responsibilities[]" rows="2" placeholder="Describe your responsibilities..." required></textarea>
                </div>
            `;
            container.appendChild(newEntry);
        }
        
        function removeExperience(btn) {
            const container = document.getElementById('experience-container');
            if (container.children.length > 1) {
                btn.closest('.experience-entry').remove();
            } else {
                alert('You need at least one work experience entry, or check "I don\'t have any work experience yet".');
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
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="ref_name[]" placeholder="Reference's full name" required>
                    </div>
                    <div class="form-group">
                        <label>Position <span class="required">*</span></label>
                        <input type="text" name="ref_position[]" placeholder="e.g., Manager" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Company <span class="required">*</span></label>
                        <input type="text" name="ref_company[]" placeholder="Company name" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Number <span class="required">*</span></label>
                        <input type="text" name="ref_contact[]" placeholder="Contact number" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Relationship <span class="required">*</span></label>
                    <input type="text" name="ref_relationship[]" placeholder="e.g., Former Supervisor, Colleague" required>
                </div>
            `;
            container.appendChild(newEntry);
        }
        
        function removeReference(btn) {
            const container = document.getElementById('references-container');
            if (container.children.length > 1) {
                btn.closest('.experience-entry').remove();
            } else {
                alert('You need at least one professional reference.');
            }
        }
        
        // File upload display
        function updateFileInfo(input, infoId, nameId) {
            const info = document.getElementById(infoId);
            const nameSpan = document.getElementById(nameId);
            
            if (input.files && input.files[0]) {
                nameSpan.textContent = input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' KB)';
                info.classList.add('active');
            } else {
                info.classList.remove('active');
            }
        }
        
        // Final form submit confirmation
        document.getElementById('applicationForm').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to submit your application? You cannot make changes after submission.')) {
                e.preventDefault();
            }
        });
        
        // Init Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>