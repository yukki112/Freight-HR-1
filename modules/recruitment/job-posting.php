<?php
// modules/recruitment/job-posting.php
require_once 'includes/notification_functions.php';

// API Configuration
define('API_URL', 'https://hsi.qcprotektado.com/recruitment_api.php');

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$error = '';

// Generate unique link function
function generateApplicationLink($job_code, $expiration_days = 30) {
    $link_code = bin2hex(random_bytes(16));
    $expiration = date('Y-m-d H:i:s', strtotime("+{$expiration_days} days"));
    $base_url = 'http://localhost/freight';
    $application_link = $base_url . '/apply.php?code=' . $link_code;
    
    return [
        'link_code' => $link_code,
        'link_expiration' => $expiration,
        'application_link' => $application_link
    ];
}

// Handle manual job creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_job'])) {
    try {
        $pdo->beginTransaction();
        
        // Generate link
        $link_data = generateApplicationLink($_POST['job_code'], $_POST['link_expiration_days']);
        
        // Check if source column exists
        $checkColumn = $pdo->query("SHOW COLUMNS FROM job_postings LIKE 'source'");
        $hasSourceColumn = $checkColumn->rowCount() > 0;
        
        // Build query based on available columns
        if ($hasSourceColumn) {
            $sql = "
                INSERT INTO job_postings (
                    job_code, title, department, employment_type, experience_required, 
                    education_required, license_required, description, requirements, 
                    responsibilities, salary_min, salary_max, location, slots_available,
                    status, published_date, closing_date, created_by, application_link,
                    link_expiration, link_code, source
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual'
                )
            ";
        } else {
            $sql = "
                INSERT INTO job_postings (
                    job_code, title, department, employment_type, experience_required, 
                    education_required, license_required, description, requirements, 
                    responsibilities, salary_min, salary_max, location, slots_available,
                    status, published_date, closing_date, created_by, application_link,
                    link_expiration, link_code
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ";
        }
        
        $stmt = $pdo->prepare($sql);
        
        $params = [
            $_POST['job_code'],
            $_POST['title'],
            $_POST['department'],
            $_POST['employment_type'],
            $_POST['experience_required'] ?? null,
            $_POST['education_required'] ?? null,
            $_POST['license_required'] ?? null,
            $_POST['description'] ?? null,
            $_POST['requirements'] ?? null,
            $_POST['responsibilities'] ?? null,
            !empty($_POST['salary_min']) ? $_POST['salary_min'] : null,
            !empty($_POST['salary_max']) ? $_POST['salary_max'] : null,
            $_POST['location'] ?? null,
            $_POST['slots_available'],
            $_POST['status'] ?? 'draft',
            !empty($_POST['published_date']) ? $_POST['published_date'] : date('Y-m-d'),
            $_POST['closing_date'],
            $_SESSION['user_id'],
            $link_data['application_link'],
            $link_data['link_expiration'],
            $link_data['link_code']
        ];
        
        $stmt->execute($params);
        
        $pdo->commit();
        
        logActivity($pdo, $_SESSION['user_id'], 'create_job_posting', 
            "Created job posting: {$_POST['job_code']} - {$_POST['title']}");
        
        // Create notification for new job posting
        createNotification(
            $pdo,
            $_SESSION['user_id'],
            'New Job Posting Created',
            "Job posting {$_POST['job_code']} - {$_POST['title']} has been created",
            'success',
            'recruitment',
            '?page=recruitment&subpage=job-posting'
        );
        
        $message = "Job posting created successfully! Link has been generated.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Handle single import from API
if (isset($_GET['action']) && $_GET['action'] === 'import_single' && isset($_GET['position_id'])) {
    try {
        $pdo->beginTransaction();
        
        $position_id = $_GET['position_id'];
        
        // Check if already imported
        $check = $pdo->prepare("SELECT id FROM job_postings WHERE job_code = ?");
        $check->execute([$position_id]);
        
        if ($check->fetch()) {
            throw new Exception("This position has already been imported");
        }
        
        // Fetch from API
        $api_url = API_URL . '?action=get_position_details&id=' . urlencode($position_id);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Failed to fetch from API");
        }
        
        $data = json_decode($response, true);
        
        if (!$data['success']) {
            throw new Exception($data['message'] ?? 'Unknown error');
        }
        
        $position = $data['position'];
        
        // Generate link
        $link_data = generateApplicationLink($position['position_id'], 30);
        
        // Prepare requirements
        $requirements = $position['requirements']['skills'] ?? '';
        if (!empty($position['requirements']['education'])) {
            $requirements .= "\n\nEducation: " . $position['requirements']['education'];
        }
        if (!empty($position['requirements']['certifications'])) {
            $requirements .= "\n\nCertifications: " . $position['requirements']['certifications'];
        }
        
        // Check if api_position_id column exists
        $checkColumn = $pdo->query("SHOW COLUMNS FROM job_postings LIKE 'api_position_id'");
        $hasApiColumn = $checkColumn->rowCount() > 0;
        
        if ($hasApiColumn) {
            $sql = "
                INSERT INTO job_postings (
                    job_code, title, department, employment_type, description,
                    requirements, education_required, experience_required,
                    license_required, slots_available, status, closing_date,
                    published_date, created_by, application_link, link_expiration,
                    link_code, api_position_id, api_synced, last_api_sync
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW()
                )
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $position['position_id'],
                $position['title'],
                strtolower($position['department']),
                strtolower(str_replace('-', '_', $position['employment_type'])),
                $position['job_description'] ?? '',
                $requirements,
                $position['requirements']['education'] ?? '',
                $position['requirements']['experience'] ?? '',
                $position['requirements']['certifications'] ?? '',
                $position['vacancies'],
                'published',
                $position['deadline'],
                date('Y-m-d'),
                $_SESSION['user_id'],
                $link_data['application_link'],
                $link_data['link_expiration'],
                $link_data['link_code'],
                $position['position_id']
            ]);
        } else {
            $sql = "
                INSERT INTO job_postings (
                    job_code, title, department, employment_type, description,
                    requirements, education_required, experience_required,
                    license_required, slots_available, status, closing_date,
                    published_date, created_by, application_link, link_expiration,
                    link_code
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $position['position_id'],
                $position['title'],
                strtolower($position['department']),
                strtolower(str_replace('-', '_', $position['employment_type'])),
                $position['job_description'] ?? '',
                $requirements,
                $position['requirements']['education'] ?? '',
                $position['requirements']['experience'] ?? '',
                $position['requirements']['certifications'] ?? '',
                $position['vacancies'],
                'published',
                $position['deadline'],
                date('Y-m-d'),
                $_SESSION['user_id'],
                $link_data['application_link'],
                $link_data['link_expiration'],
                $link_data['link_code']
            ]);
        }
        
        $pdo->commit();
        
        logActivity($pdo, $_SESSION['user_id'], 'import_job_from_api', 
            "Imported job posting: {$position['position_id']} - {$position['title']} from API");
        
        // Create notification for all admins about new import
        notifyAllAdmins(
            $pdo,
            'New Job Imported from API',
            "Job posting {$position['position_id']} - {$position['title']} has been imported",
            'info',
            'recruitment',
            '?page=recruitment&subpage=job-posting'
        );
        
        $message = "Job posting imported successfully! Link has been generated.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Handle bulk import from API
if (isset($_POST['bulk_import'])) {
    try {
        $pdo->beginTransaction();
        
        $selected_positions = $_POST['selected_positions'] ?? [];
        
        if (empty($selected_positions)) {
            throw new Exception("No positions selected for import");
        }
        
        $imported = 0;
        $skipped = 0;
        $imported_titles = [];
        
        foreach ($selected_positions as $position_id) {
            // Check if already imported
            $check = $pdo->prepare("SELECT id FROM job_postings WHERE job_code = ?");
            $check->execute([$position_id]);
            
            if ($check->fetch()) {
                $skipped++;
                continue;
            }
            
            // Fetch from API
            $api_url = API_URL . '?action=get_position_details&id=' . urlencode($position_id);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200) {
                continue;
            }
            
            $data = json_decode($response, true);
            
            if (!$data['success']) {
                continue;
            }
            
            $position = $data['position'];
            
            // Generate link
            $link_data = generateApplicationLink($position['position_id'], 30);
            
            // Prepare requirements
            $requirements = $position['requirements']['skills'] ?? '';
            if (!empty($position['requirements']['education'])) {
                $requirements .= "\n\nEducation: " . $position['requirements']['education'];
            }
            if (!empty($position['requirements']['certifications'])) {
                $requirements .= "\n\nCertifications: " . $position['requirements']['certifications'];
            }
            
            // Check if api_position_id column exists
            $checkColumn = $pdo->query("SHOW COLUMNS FROM job_postings LIKE 'api_position_id'");
            $hasApiColumn = $checkColumn->rowCount() > 0;
            
            if ($hasApiColumn) {
                $sql = "
                    INSERT INTO job_postings (
                        job_code, title, department, employment_type, description,
                        requirements, education_required, experience_required,
                        license_required, slots_available, status, closing_date,
                        published_date, created_by, application_link, link_expiration,
                        link_code, api_position_id, api_synced, last_api_sync
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW()
                    )
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $position['position_id'],
                    $position['title'],
                    strtolower($position['department']),
                    strtolower(str_replace('-', '_', $position['employment_type'])),
                    $position['job_description'] ?? '',
                    $requirements,
                    $position['requirements']['education'] ?? '',
                    $position['requirements']['experience'] ?? '',
                    $position['requirements']['certifications'] ?? '',
                    $position['vacancies'],
                    'published',
                    $position['deadline'],
                    date('Y-m-d'),
                    $_SESSION['user_id'],
                    $link_data['application_link'],
                    $link_data['link_expiration'],
                    $link_data['link_code'],
                    $position['position_id']
                ]);
            } else {
                $sql = "
                    INSERT INTO job_postings (
                        job_code, title, department, employment_type, description,
                        requirements, education_required, experience_required,
                        license_required, slots_available, status, closing_date,
                        published_date, created_by, application_link, link_expiration,
                        link_code
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $position['position_id'],
                    $position['title'],
                    strtolower($position['department']),
                    strtolower(str_replace('-', '_', $position['employment_type'])),
                    $position['job_description'] ?? '',
                    $requirements,
                    $position['requirements']['education'] ?? '',
                    $position['requirements']['experience'] ?? '',
                    $position['requirements']['certifications'] ?? '',
                    $position['vacancies'],
                    'published',
                    $position['deadline'],
                    date('Y-m-d'),
                    $_SESSION['user_id'],
                    $link_data['application_link'],
                    $link_data['link_expiration'],
                    $link_data['link_code']
                ]);
            }
            
            $imported++;
            $imported_titles[] = $position['title'];
        }
        
        $pdo->commit();
        
        logActivity($pdo, $_SESSION['user_id'], 'bulk_import_jobs', 
            "Imported {$imported} jobs from API, {$skipped} skipped");
        
        // Create notification for bulk import
        if ($imported > 0) {
            $title = $imported . " New Job" . ($imported > 1 ? "s" : "") . " Imported";
            $message_text = "Imported " . $imported . " job" . ($imported > 1 ? "s" : "") . " from API";
            if (!empty($imported_titles)) {
                $message_text .= ": " . implode(', ', array_slice($imported_titles, 0, 3));
                if (count($imported_titles) > 3) {
                    $message_text .= " and " . (count($imported_titles) - 3) . " more";
                }
            }
            
            notifyAllAdmins(
                $pdo,
                $title,
                $message_text,
                'success',
                'recruitment',
                '?page=recruitment&subpage=job-posting'
            );
        }
        
        $message = "Successfully imported {$imported} job postings. {$skipped} were already imported. Links have been generated for all.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch from API for import list
$api_positions = [];
try {
    $api_url = API_URL . '?action=get_vacant_positions';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if ($data['success']) {
            $api_positions = $data['positions'];
        }
    }
} catch (Exception $e) {
    // Silently fail
}

// Get existing job codes to filter out already imported
$existing_job_codes = [];
if (!empty($api_positions)) {
    $codes = array_column($api_positions, 'position_id');
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $stmt = $pdo->prepare("SELECT job_code FROM job_postings WHERE job_code IN ($placeholders)");
    $stmt->execute($codes);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $existing_job_codes = $existing;
}

// Get all job postings
$stmt = $pdo->query("
    SELECT jp.*, u.full_name as created_by_name,
           (SELECT COUNT(*) FROM job_applications WHERE job_posting_id = jp.id) as applications_count
    FROM job_postings jp
    LEFT JOIN users u ON jp.created_by = u.id
    ORDER BY jp.created_at DESC
");
$job_postings = $stmt->fetchAll();

// Get department colors
$dept_colors = [
    'driver' => '#0e4c92',
    'warehouse' => '#1a5da0',
    'logistics' => '#2a6eb0',
    'admin' => '#3a7fc0',
    'management' => '#4a90d0'
];
?>

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

<!-- Header with Create Button -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 style="font-size: 28px; font-weight: 600; color: #2c3e50; margin-bottom: 5px;">
            <i class="fas fa-briefcase" style="color: #0e4c92; margin-right: 10px;"></i>
            Job Posting Management
        </h1>
        <p style="color: #7f8c8d;">Manage and publish job vacancies - Links are automatically generated for all jobs</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <?php if (!empty($api_positions)): ?>
        <?php endif; ?>
        <button class="add-expense-btn" onclick="showCreateModal()" style="padding: 12px 24px; font-size: 14px; background: #27ae60; color: white;">
            <i class="fas fa-plus-circle"></i> Create New Job Posting
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(14,76,146,0.05);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #0e4c92, #1a5da0); border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-briefcase" style="color: white; font-size: 24px;"></i>
            </div>
            <div>
                <p style="font-size: 12px; color: #7f8c8d;">Active Jobs</p>
                <p style="font-size: 28px; font-weight: 700; color: #2c3e50;"><?php echo count(array_filter($job_postings, fn($j) => $j['status'] == 'published')); ?></p>
            </div>
        </div>
    </div>
    
    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(14,76,146,0.05);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #27ae60, #2ecc71); border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-users" style="color: white; font-size: 24px;"></i>
            </div>
            <div>
                <p style="font-size: 12px; color: #7f8c8d;">Total Applications</p>
                <p style="font-size: 28px; font-weight: 700; color: #2c3e50;"><?php echo array_sum(array_column($job_postings, 'applications_count')); ?></p>
            </div>
        </div>
    </div>
    
    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(14,76,146,0.05);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #f39c12, #f1c40f); border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-clock" style="color: white; font-size: 24px;"></i>
            </div>
            <div>
                <p style="font-size: 12px; color: #7f8c8d;">Expiring Soon</p>
                <p style="font-size: 28px; font-weight: 700; color: #2c3e50;"><?php 
                    echo count(array_filter($job_postings, fn($j) => 
                        $j['status'] == 'published' && 
                        strtotime($j['closing_date']) <= strtotime('+7 days')
                    ));
                ?></p>
            </div>
        </div>
    </div>
    
    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(14,76,146,0.05);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #9b59b6, #8e44ad); border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-cloud-download-alt" style="color: white; font-size: 24px;"></i>
            </div>
            <div>
                <p style="font-size: 12px; color: #7f8c8d;">API Available</p>
                <p style="font-size: 28px; font-weight: 700; color: #2c3e50;"><?php echo count($api_positions); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Job Postings List -->
<div style="background: white; border-radius: 25px; padding: 25px; box-shadow: 0 10px 30px rgba(14,76,146,0.05);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <h2 style="font-size: 20px; font-weight: 600; color: #2c3e50;">
            <i class="fas fa-list" style="color: #0e4c92; margin-right: 10px;"></i>
            All Job Postings
        </h2>
        
        <div style="display: flex; gap: 10px;">
            <div style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #95a5a6;"></i>
                <input type="text" id="searchJobs" placeholder="Search jobs..." 
                       style="padding: 10px 10px 10px 35px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px; width: 250px;">
            </div>
            <select id="filterStatus" onchange="filterJobs()" 
                    style="padding: 10px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                <option value="">All Status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="closed">Closed</option>
            </select>
        </div>
    </div>
    
    <?php if (empty($job_postings)): ?>
    <div style="text-align: center; padding: 60px; color: #95a5a6;">
        <i class="fas fa-briefcase" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
        <h3 style="margin-bottom: 10px;">No Job Postings Yet</h3>
        <p>Click the "Create New Job Posting" button to get started.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 1200px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: #7f8c8d; border-radius: 12px 0 0 12px;">Job Details</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: #7f8c8d;">Department</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: #7f8c8d;">Slots</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: #7f8c8d;">Applications</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: #7f8c8d;">Status</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: #7f8c8d;">Closing Date</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: #7f8c8d;">Application Link</th>
                    <th style="padding: 15px; text-align: left; font-size: 12px; font-weight: 600; color: #7f8c8d; border-radius: 0 12px 12px 0;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($job_postings as $job): ?>
                <tr class="job-row" data-status="<?php echo $job['status']; ?>" style="border-bottom: 1px solid rgba(14,76,146,0.05);">
                    <td style="padding: 15px;">
                        <div>
                            <strong style="color: #2c3e50;"><?php echo htmlspecialchars($job['job_code']); ?></strong>
                            <div style="font-size: 14px; margin-top: 3px;"><?php echo htmlspecialchars($job['title']); ?></div>
                        </div>
                    </td>
                    <td style="padding: 15px;">
                        <span class="category-badge" style="background: <?php echo $dept_colors[$job['department']] ?? '#0e4c92'; ?>20; color: <?php echo $dept_colors[$job['department']] ?? '#0e4c92'; ?>;">
                            <?php echo ucfirst($job['department']); ?>
                        </span>
                    </td>
                    <td style="padding: 15px;">
                        <span style="font-weight: 600; color: #2c3e50;"><?php echo $job['slots_available']; ?></span>
                    </td>
                    <td style="padding: 15px;">
                        <span class="category-badge" style="background: #27ae6020; color: #27ae60;">
                            <?php echo $job['applications_count'] ?? 0; ?> applications
                        </span>
                    </td>
                    <td style="padding: 15px;">
                        <?php
                        $status_colors = [
                            'draft' => '#7f8c8d',
                            'published' => '#27ae60',
                            'closed' => '#e74c3c',
                            'cancelled' => '#95a5a6'
                        ];
                        $color = $status_colors[$job['status']] ?? '#7f8c8d';
                        ?>
                        <span class="category-badge" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                            <?php echo ucfirst($job['status']); ?>
                        </span>
                    </td>
                    <td style="padding: 15px;">
                        <?php 
                        $days_left = ceil((strtotime($job['closing_date']) - time()) / (60 * 60 * 24));
                        $expiring_class = $days_left <= 7 ? '#e74c3c' : '#2c3e50';
                        ?>
                        <div style="color: <?php echo $expiring_class; ?>;">
                            <?php echo date('M d, Y', strtotime($job['closing_date'])); ?>
                            <?php if ($days_left > 0 && $job['status'] == 'published'): ?>
                            <br><small>(<?php echo $days_left; ?> days left)</small>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="padding: 15px;">
                        <?php if ($job['application_link']): ?>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <div style="background: #f8f9fa; border-radius: 8px; padding: 5px 10px; font-size: 11px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                Link available
                            </div>
                            <button class="table-action" onclick="copyLink('<?php echo $job['application_link']; ?>')" title="Copy Link">
                                <i class="fas fa-copy"></i>
                            </button>
                            <a href="<?php echo htmlspecialchars($job['application_link']); ?>" target="_blank" class="table-action" title="Open Link">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                        <small style="color: #7f8c8d; font-size: 10px;">
                            Expires: <?php echo date('M d, Y', strtotime($job['link_expiration'])); ?>
                        </small>
                        <?php else: ?>
                        <span class="category-badge" style="background: #95a5a620; color: #7f8c8d;">No link</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 15px;">
                        <div style="display: flex; gap: 8px;">
                            <button class="table-action" onclick="viewJobDetails(<?php echo htmlspecialchars(json_encode($job)); ?>)" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if ($job['application_link']): ?>
                            <button class="table-action" onclick="shareJob('<?php echo $job['application_link']; ?>', '<?php echo addslashes($job['title']); ?>')" title="Share">
                                <i class="fas fa-share-alt"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Create Job Modal -->
<div class="modal" id="createJobModal">
    <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
        <button class="modal-close" onclick="hideCreateModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #0e4c92, #1a5da0); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fas fa-plus-circle" style="color: white; font-size: 30px;"></i>
            </div>
            <h2 style="font-size: 24px; color: #2c3e50; margin-bottom: 5px;">Create New Job Posting</h2>
            <p style="color: #7f8c8d;">Fill in the details below. Application link will be generated automatically.</p>
        </div>
        
        <!-- Import from API Dropdown -->
        <?php if (!empty($api_positions)): ?>
        <div style="background: #f8f9fa; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 2px dashed #3498db;">
            <label style="display: block; font-size: 14px; font-weight: 600; color: #2c3e50; margin-bottom: 10px;">
                <i class="fas fa-cloud-download-alt" style="color: #3498db;"></i> Quick Fill from API
            </label>
            <select id="apiPositionSelect" onchange="fillFromApi()" style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px; margin-bottom: 10px; cursor: pointer;">
                <option value="">-- Select a position to auto-fill the form --</option>
                <?php 
                $counter = 0;
                foreach ($api_positions as $position): 
                    $is_imported = in_array($position['position_id'], $existing_job_codes);
                    if (!$is_imported):
                        $counter++;
                ?>
                <option value="<?php echo $counter - 1; ?>" data-position='<?php echo htmlspecialchars(json_encode($position)); ?>'>
                    <?php echo htmlspecialchars($position['position_id'] . ' - ' . $position['title'] . ' (' . $position['department'] . ')'); ?>
                </option>
                <?php 
                    endif;
                endforeach; 
                ?>
            </select>
            <p style="font-size: 12px; color: #7f8c8d; margin-top: 5px;">
                <i class="fas fa-info-circle"></i> Select a position to automatically fill all fields below
            </p>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="?page=recruitment&subpage=job-posting" id="createJobForm">
            <!-- Basic Information -->
            <div style="background: #f8f9fa; border-radius: 16px; padding: 20px; margin-bottom: 20px;">
                <h3 style="font-size: 16px; margin-bottom: 15px; color: #2c3e50;">
                    <i class="fas fa-info-circle" style="color: #0e4c92; margin-right: 8px;"></i>
                    Basic Information
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Job Code *</label>
                        <input type="text" name="job_code" id="job_code" required 
                               value="<?php echo 'JOB-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT); ?>"
                               style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Job Title *</label>
                        <input type="text" name="title" id="title" required placeholder="e.g., Heavy Truck Driver"
                               style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Department *</label>
                        <select name="department" id="department" required style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                            <option value="">Select Department</option>
                            <option value="driver">Driver</option>
                            <option value="warehouse">Warehouse</option>
                            <option value="logistics">Logistics</option>
                            <option value="admin">Admin</option>
                            <option value="management">Management</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Employment Type *</label>
                        <select name="employment_type" id="employment_type" required style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                            <option value="full_time">Full Time</option>
                            <option value="part_time">Part Time</option>
                            <option value="contract">Contract</option>
                            <option value="probationary">Probationary</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Requirements -->
            <div style="background: #f8f9fa; border-radius: 16px; padding: 20px; margin-bottom: 20px;">
                <h3 style="font-size: 16px; margin-bottom: 15px; color: #2c3e50;">
                    <i class="fas fa-clipboard-list" style="color: #0e4c92; margin-right: 8px;"></i>
                    Requirements & Details
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Experience Required</label>
                        <input type="text" name="experience_required" id="experience_required" placeholder="e.g., 2+ years"
                               style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Education Required</label>
                        <input type="text" name="education_required" id="education_required" placeholder="e.g., High School Graduate"
                               style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">License/Certifications Required</label>
                    <input type="text" name="license_required" id="license_required" placeholder="e.g., Professional Driver's License"
                           style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Salary Min (PHP)</label>
                        <input type="number" name="salary_min" id="salary_min" step="0.01" placeholder="0.00"
                               style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Salary Max (PHP)</label>
                        <input type="number" name="salary_max" id="salary_max" step="0.01" placeholder="0.00"
                               style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Location</label>
                    <input type="text" name="location" id="location" placeholder="e.g., Manila"
                           style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                </div>
            </div>
            
            <!-- Descriptions -->
            <div style="background: #f8f9fa; border-radius: 16px; padding: 20px; margin-bottom: 20px;">
                <h3 style="font-size: 16px; margin-bottom: 15px; color: #2c3e50;">
                    <i class="fas fa-align-left" style="color: #0e4c92; margin-right: 8px;"></i>
                    Descriptions
                </h3>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Job Description</label>
                    <textarea name="description" id="description" rows="3" placeholder="Describe the job responsibilities and overview..."
                              style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;"></textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Requirements</label>
                    <textarea name="requirements" id="requirements" rows="3" placeholder="List all requirements..."
                              style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;"></textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Responsibilities</label>
                    <textarea name="responsibilities" id="responsibilities" rows="3" placeholder="List key responsibilities..."
                              style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;"></textarea>
                </div>
            </div>
            
            <!-- Dates & Settings -->
            <div style="background: #f8f9fa; border-radius: 16px; padding: 20px; margin-bottom: 20px;">
                <h3 style="font-size: 16px; margin-bottom: 15px; color: #2c3e50;">
                    <i class="fas fa-calendar-alt" style="color: #0e4c92; margin-right: 8px;"></i>
                    Dates & Settings
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Slots Available *</label>
                        <input type="number" name="slots_available" id="slots_available" required min="1" value="1"
                               style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Published Date</label>
                        <input type="date" name="published_date" id="published_date" value="<?php echo date('Y-m-d'); ?>"
                               style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Closing Date *</label>
                        <input type="date" name="closing_date" id="closing_date" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                               style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Link Expiration (Days) *</label>
                        <input type="number" name="link_expiration_days" id="link_expiration_days" required min="1" max="365" value="30"
                               style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                        <small style="color: #7f8c8d;">Application link will be generated automatically</small>
                    </div>
                </div>
                
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 5px;">Status</label>
                    <select name="status" style="width: 100%; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                        <option value="draft">Draft</option>
                        <option value="published" selected>Published</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="button" class="add-expense-btn" onclick="hideCreateModal()" style="flex: 1;">
                    Cancel
                </button>
                <button type="submit" name="create_job" class="submit-btn" style="flex: 2;">
                    <i class="fas fa-save"></i> Create Job Posting (Link will be generated)
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Import API Modal -->
<div class="modal" id="importApiModal">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <button class="modal-close" onclick="hideImportModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fas fa-cloud-download-alt" style="color: white; font-size: 30px;"></i>
            </div>
            <h2 style="font-size: 24px; color: #2c3e50; margin-bottom: 5px;">Bulk Import from API</h2>
            <p style="color: #7f8c8d;">Select positions to import. Links will be generated automatically for each.</p>
        </div>
        
        <form method="POST" id="bulkImportForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-bottom: 20px; max-height: 400px; overflow-y: auto; padding: 5px;">
                <?php foreach ($api_positions as $position): 
                    $is_imported = in_array($position['position_id'], $existing_job_codes);
                ?>
                <div style="background: <?php echo $is_imported ? '#f8f9fa' : 'white'; ?>; border: 2px solid <?php echo $is_imported ? '#e74c3c20' : 'rgba(14,76,146,0.1)'; ?>; border-radius: 16px; padding: 15px; <?php echo !$is_imported ? 'cursor: pointer;' : ''; ?>" 
                     onclick="<?php echo !$is_imported ? "toggleCheckbox('{$position['position_id']}')" : ''; ?>"
                     id="card-<?php echo $position['position_id']; ?>">
                    
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?php if (!$is_imported): ?>
                            <input type="checkbox" name="selected_positions[]" value="<?php echo $position['position_id']; ?>" 
                                   id="cb-<?php echo $position['position_id']; ?>"
                                   class="api-checkbox" style="width: 18px; height: 18px;" onclick="event.stopPropagation()">
                            <?php endif; ?>
                            <span class="category-badge" style="background: <?php echo $dept_colors[strtolower($position['department'])] ?? '#0e4c92'; ?>20; color: <?php echo $dept_colors[strtolower($position['department'])] ?? '#0e4c92'; ?>;">
                                <?php echo htmlspecialchars($position['department']); ?>
                            </span>
                        </div>
                        <?php if ($is_imported): ?>
                        <span class="category-badge" style="background: #27ae6020; color: #27ae60;">
                            <i class="fas fa-check"></i> Imported
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 5px;"><?php echo htmlspecialchars($position['title']); ?></h3>
                    <p style="font-size: 12px; color: #7f8c8d; margin-bottom: 10px;">
                        <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($position['position_id']); ?>
                    </p>
                    
                    <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                        <span style="font-size: 12px; color: #2c3e50;">
                            <i class="fas fa-users"></i> <?php echo $position['vacancies']; ?> slots
                        </span>
                        <span style="font-size: 12px; color: #2c3e50;">
                            <i class="fas fa-clock"></i> <?php echo ucfirst(str_replace('_', ' ', $position['employment_type'])); ?>
                        </span>
                    </div>
                    
                    <div style="font-size: 11px; color: #7f8c8d;">
                        <i class="fas fa-calendar-times"></i> Deadline: <?php echo date('M d, Y', strtotime($position['deadline'])); ?>
                    </div>
                    
                    <?php if (!$is_imported): ?>
                    <div style="margin-top: 10px; font-size: 11px; color: #27ae60;">
                        <i class="fas fa-link"></i> Link will be generated automatically
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                <span id="selectedCount" style="padding: 8px 16px; background: #f8f9fa; border-radius: 12px; font-size: 13px;">
                    0 selected
                </span>
                <button type="button" class="add-expense-btn" onclick="selectAllApi()">
                    <i class="fas fa-check-double"></i> Select All
                </button>
                <button type="button" class="add-expense-btn" onclick="deselectAllApi()">
                    <i class="fas fa-times"></i> Deselect All
                </button>
                <button type="submit" name="bulk_import" class="add-expense-btn" style="background: #27ae60; color: white;" onclick="return confirm('Import selected positions? Links will be generated automatically.')">
                    <i class="fas fa-cloud-download-alt"></i> Import Selected (Links will be generated)
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Job Details Modal -->
<div class="modal" id="viewJobModal">
    <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
        <button class="modal-close" onclick="hideViewModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <div id="viewJobContent">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<!-- Share Job Modal -->
<div class="modal" id="shareJobModal">
    <div class="modal-content" style="max-width: 500px;">
        <button class="modal-close" onclick="hideShareModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <h2 style="font-size: 20px; margin-bottom: 20px;">Share Job Posting</h2>
        
        <div id="shareJobContent">
            <!-- Filled by JavaScript -->
        </div>
    </div>
</div>

<script>
// Store API positions data for auto-fill
const apiPositions = <?php echo json_encode(array_values($api_positions)); ?>;

// Create Modal Functions
function showCreateModal() {
    document.getElementById('createJobModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function hideCreateModal() {
    document.getElementById('createJobModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Import Modal Functions
function showImportModal() {
    document.getElementById('importApiModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    updateSelectedCount();
}

function hideImportModal() {
    document.getElementById('importApiModal').classList.remove('active');
    document.body.style.overflow = '';
}

// View Modal Functions
function viewJobDetails(job) {
    const deptColors = {
        'driver': '#0e4c92',
        'warehouse': '#1a5da0',
        'logistics': '#2a6eb0',
        'admin': '#3a7fc0',
        'management': '#4a90d0'
    };
    
    const statusColors = {
        'draft': '#7f8c8d',
        'published': '#27ae60',
        'closed': '#e74c3c',
        'cancelled': '#95a5a6'
    };
    
    const html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #0e4c92, #1a5da0); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                <i class="fas fa-briefcase" style="color: white; font-size: 24px;"></i>
            </div>
            <h2 style="font-size: 22px; color: #2c3e50; margin-bottom: 5px;">${job.job_code}</h2>
            <p style="color: #7f8c8d;">${job.title}</p>
        </div>
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; justify-content: center;">
            <span class="category-badge" style="background: ${deptColors[job.department]}20; color: ${deptColors[job.department]};">
                <i class="fas ${job.department == 'driver' ? 'fa-truck' : (job.department == 'warehouse' ? 'fa-warehouse' : (job.department == 'logistics' ? 'fa-boxes' : (job.department == 'admin' ? 'fa-user-tie' : 'fa-chart-line')))}"></i>
                ${job.department.charAt(0).toUpperCase() + job.department.slice(1)}
            </span>
            <span class="category-badge" style="background: ${statusColors[job.status]}20; color: ${statusColors[job.status]};">
                <i class="fas ${job.status == 'published' ? 'fa-check-circle' : (job.status == 'draft' ? 'fa-pen' : 'fa-times-circle')}"></i>
                ${job.status.charAt(0).toUpperCase() + job.status.slice(1)}
            </span>
            <span class="category-badge" style="background: #3498db20; color: #3498db;">
                <i class="fas fa-clock"></i> ${job.employment_type.replace('_', ' ')}
            </span>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; background: #f8f9fa; border-radius: 16px; padding: 20px; margin-bottom: 20px;">
            <div>
                <p style="font-size: 11px; color: #7f8c8d; margin-bottom: 5px;">Slots Available</p>
                <p style="font-size: 24px; font-weight: 700; color: #2c3e50;">${job.slots_available}</p>
            </div>
            <div>
                <p style="font-size: 11px; color: #7f8c8d; margin-bottom: 5px;">Applications</p>
                <p style="font-size: 24px; font-weight: 700; color: #2c3e50;">${job.applications_count || 0}</p>
            </div>
            <div>
                <p style="font-size: 11px; color: #7f8c8d; margin-bottom: 5px;">Published</p>
                <p style="font-size: 14px; font-weight: 500;">${job.published_date ? new Date(job.published_date).toLocaleDateString() : 'Not published'}</p>
            </div>
            <div>
                <p style="font-size: 11px; color: #7f8c8d; margin-bottom: 5px;">Closing Date</p>
                <p style="font-size: 14px; font-weight: 500;">${new Date(job.closing_date).toLocaleDateString()}</p>
            </div>
        </div>
        
        ${job.location ? `
        <div style="margin-bottom: 15px;">
            <p style="font-size: 12px; font-weight: 600; color: #2c3e50; margin-bottom: 5px;">Location</p>
            <p style="color: #7f8c8d;">${job.location}</p>
        </div>
        ` : ''}
        
        ${job.description ? `
        <div style="margin-bottom: 15px;">
            <p style="font-size: 12px; font-weight: 600; color: #2c3e50; margin-bottom: 5px;">Description</p>
            <p style="color: #7f8c8d; line-height: 1.5;">${job.description.replace(/\n/g, '<br>')}</p>
        </div>
        ` : ''}
        
        ${job.requirements ? `
        <div style="margin-bottom: 15px;">
            <p style="font-size: 12px; font-weight: 600; color: #2c3e50; margin-bottom: 5px;">Requirements</p>
            <p style="color: #7f8c8d; line-height: 1.5;">${job.requirements.replace(/\n/g, '<br>')}</p>
        </div>
        ` : ''}
        
        ${job.salary_min || job.salary_max ? `
        <div style="margin-bottom: 15px;">
            <p style="font-size: 12px; font-weight: 600; color: #2c3e50; margin-bottom: 5px;">Salary Range</p>
            <p style="color: #27ae60; font-weight: 500;">
                ${job.salary_min ? '₱' + Number(job.salary_min).toLocaleString() : ''} 
                ${job.salary_min && job.salary_max ? ' - ' : ''} 
                ${job.salary_max ? '₱' + Number(job.salary_max).toLocaleString() : ''}
            </p>
        </div>
        ` : ''}
        
        ${job.application_link ? `
        <div style="background: #f8f9fa; border-radius: 16px; padding: 15px; margin-top: 20px;">
            <p style="font-size: 12px; font-weight: 600; color: #2c3e50; margin-bottom: 10px;">Application Link</p>
            <div style="display: flex; gap: 10px;">
                <input type="text" value="${job.application_link}" id="viewModalLink" 
                       style="flex: 1; padding: 10px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px; background: white;" readonly>
                <button class="table-action" onclick="copyLink('${job.application_link}')">
                    <i class="fas fa-copy"></i>
                </button>
                <a href="${job.application_link}" target="_blank" class="table-action">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            </div>
            <p style="font-size: 11px; color: #7f8c8d; margin-top: 8px;">
                <i class="fas fa-clock"></i> Expires: ${new Date(job.link_expiration).toLocaleDateString()}
            </p>
        </div>
        ` : ''}
        
        <div style="display: flex; gap: 10px; margin-top: 25px;">
            ${job.application_link ? `
            <button class="add-expense-btn" onclick="shareJob('${job.application_link}', '${job.title.replace(/'/g, "\\'")}')" style="flex: 1;">
                <i class="fas fa-share-alt"></i> Share Job
            </button>
            ` : ''}
        </div>
    `;
    
    document.getElementById('viewJobContent').innerHTML = html;
    document.getElementById('viewJobModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function hideViewModal() {
    document.getElementById('viewJobModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Share Modal Functions
function shareJob(link, title) {
    const html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <i class="fas fa-share-alt" style="font-size: 40px; color: #0e4c92; margin-bottom: 10px;"></i>
            <h3 style="font-size: 16px; color: #2c3e50;">${title}</h3>
        </div>
        
        <div style="background: #f8f9fa; border-radius: 16px; padding: 20px; margin-bottom: 20px;">
            <p style="font-size: 12px; color: #7f8c8d; margin-bottom: 10px;">Application Link:</p>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <input type="text" value="${link}" id="shareModalLink" 
                       style="flex: 1; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;" readonly>
                <button class="table-action" onclick="copyLink('${link}')">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            <p style="font-size: 11px; color: #7f8c8d;"><i class="fas fa-clock"></i> This link will expire as configured</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;">
            <button class="add-expense-btn" onclick="shareVia('facebook', '${link}')" style="background: #1877f2; color: white;">
                <i class="fab fa-facebook"></i>
            </button>
            <button class="add-expense-btn" onclick="shareVia('twitter', '${link}')" style="background: #1da1f2; color: white;">
                <i class="fab fa-twitter"></i>
            </button>
            <button class="add-expense-btn" onclick="shareVia('linkedin', '${link}')" style="background: #0a66c2; color: white;">
                <i class="fab fa-linkedin"></i>
            </button>
        </div>
        
        <div>
            <label style="display: block; font-size: 12px; color: #7f8c8d; margin-bottom: 5px;">Email this link:</label>
            <div style="display: flex; gap: 10px;">
                <input type="email" id="shareEmail" placeholder="Enter email address" 
                       style="flex: 1; padding: 12px; border: 1px solid rgba(14,76,146,0.1); border-radius: 12px;">
                <button class="add-expense-btn" onclick="shareViaEmail()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('shareJobContent').innerHTML = html;
    document.getElementById('shareJobModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function hideShareModal() {
    document.getElementById('shareJobModal').classList.remove('active');
    document.body.style.overflow = '';
}

// FILL FROM API
function fillFromApi() {
    const select = document.getElementById('apiPositionSelect');
    const selectedIndex = select.value;
    
    if (selectedIndex === "") return;
    
    const position = apiPositions[selectedIndex];
    if (!position) return;
    
    console.log('Filling form with:', position);
    
    // Fill basic info
    document.getElementById('job_code').value = position.position_id;
    document.getElementById('title').value = position.title;
    
    // Set department
    const deptSelect = document.getElementById('department');
    const deptValue = position.department.toLowerCase();
    for (let i = 0; i < deptSelect.options.length; i++) {
        if (deptSelect.options[i].value === deptValue) {
            deptSelect.selectedIndex = i;
            break;
        }
    }
    
    // Set employment type
    const empSelect = document.getElementById('employment_type');
    const empValue = position.employment_type.toLowerCase().replace('-', '_');
    for (let i = 0; i < empSelect.options.length; i++) {
        if (empSelect.options[i].value === empValue) {
            empSelect.selectedIndex = i;
            break;
        }
    }
    
    document.getElementById('slots_available').value = position.vacancies;
    document.getElementById('closing_date').value = position.deadline;
    
    // Show loading notification
    showNotification('Fetching full position details...', 'info');
    
    // Fetch full details
    fetch(`https://hsi.qcprotektado.com/recruitment_api.php?action=get_position_details&id=${encodeURIComponent(position.position_id)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const details = data.position;
                
                document.getElementById('description').value = details.job_description || '';
                
                let requirements = details.requirements?.skills || '';
                if (details.requirements?.education) {
                    requirements += '\n\nEducation: ' + details.requirements.education;
                }
                if (details.requirements?.certifications) {
                    requirements += '\n\nCertifications: ' + details.requirements.certifications;
                }
                document.getElementById('requirements').value = requirements;
                
                document.getElementById('experience_required').value = details.requirements?.experience || '';
                document.getElementById('education_required').value = details.requirements?.education || '';
                document.getElementById('license_required').value = details.requirements?.certifications || '';
                
                if (details.salary_range) {
                    const salaryMatch = details.salary_range.match(/(\d+)/g);
                    if (salaryMatch && salaryMatch.length >= 2) {
                        document.getElementById('salary_min').value = salaryMatch[0];
                        document.getElementById('salary_max').value = salaryMatch[1];
                    }
                }
                
                showNotification('Form auto-filled successfully!', 'success');
            }
        })
        .catch(error => {
            console.error('Error fetching details:', error);
            showNotification('Auto-filled basic info. Full details could not be loaded.', 'warning');
        });
}

// Import modal functions
function toggleCheckbox(positionId) {
    const checkbox = document.getElementById('cb-' + positionId);
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        
        const card = document.getElementById('card-' + positionId);
        if (card) {
            if (checkbox.checked) {
                card.style.borderColor = '#27ae60';
                card.style.backgroundColor = '#f0fff4';
            } else {
                card.style.borderColor = 'rgba(14,76,146,0.1)';
                card.style.backgroundColor = 'white';
            }
        }
    }
    updateSelectedCount();
}

function selectAllApi() {
    const checkboxes = document.querySelectorAll('.api-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = true;
        const card = document.getElementById('card-' + cb.value);
        if (card) {
            card.style.borderColor = '#27ae60';
            card.style.backgroundColor = '#f0fff4';
        }
    });
    updateSelectedCount();
}

function deselectAllApi() {
    const checkboxes = document.querySelectorAll('.api-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
        const card = document.getElementById('card-' + cb.value);
        if (card) {
            card.style.borderColor = 'rgba(14,76,146,0.1)';
            card.style.backgroundColor = 'white';
        }
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.api-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count + ' selected';
}

// Utility Functions
function copyLink(link) {
    navigator.clipboard.writeText(link).then(() => {
        showNotification('Link copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = link;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showNotification('Link copied to clipboard!', 'success');
    });
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.animation = 'slideDown 0.3s';
    notification.innerHTML = `<i class="fas fa-${type == 'success' ? 'check-circle' : (type == 'error' ? 'exclamation-circle' : 'info-circle')}"></i> ${message}`;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideUp 0.3s';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function shareVia(platform, link) {
    let url = '';
    switch(platform) {
        case 'facebook':
            url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(link)}`;
            break;
        case 'twitter':
            url = `https://twitter.com/intent/tweet?url=${encodeURIComponent(link)}`;
            break;
        case 'linkedin':
            url = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(link)}`;
            break;
    }
    window.open(url, '_blank', 'width=600,height=400');
}

function shareViaEmail() {
    const email = document.getElementById('shareEmail').value;
    if (email) {
        window.location.href = `mailto:${email}?subject=Job%20Opportunity&body=Check%20out%20this%20job%20opportunity:%20${encodeURIComponent(document.getElementById('shareModalLink').value)}`;
    }
}

// Filter and Search
function filterJobs() {
    const status = document.getElementById('filterStatus').value;
    const search = document.getElementById('searchJobs').value.toLowerCase();
    const rows = document.querySelectorAll('.job-row');
    
    rows.forEach(row => {
        const rowStatus = row.dataset.status;
        const text = row.textContent.toLowerCase();
        const matchesStatus = !status || rowStatus === status;
        const matchesSearch = !search || text.includes(search);
        
        row.style.display = matchesStatus && matchesSearch ? '' : 'none';
    });
}

document.getElementById('searchJobs').addEventListener('keyup', filterJobs);

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideCreateModal();
        hideImportModal();
        hideViewModal();
        hideShareModal();
    }
});

// Close modals when clicking outside
window.onclick = function(event) {
    const createModal = document.getElementById('createJobModal');
    const importModal = document.getElementById('importApiModal');
    const viewModal = document.getElementById('viewJobModal');
    const shareModal = document.getElementById('shareJobModal');
    
    if (event.target == createModal) hideCreateModal();
    if (event.target == importModal) hideImportModal();
    if (event.target == viewModal) hideViewModal();
    if (event.target == shareModal) hideShareModal();
}
</script>

<style>
/* Animation for notifications */
@keyframes slideDown {
    from { transform: translateY(-100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(0); opacity: 1; }
    to { transform: translateY(-100%); opacity: 0; }
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    backdrop-filter: blur(5px);
    padding: 20px;
    overflow-y: auto;
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 30px;
    padding: 30px;
    width: 90%;
    max-width: 700px;
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
    width: 30px;
    height: 30px;
    background: rgba(14, 76, 146, 0.1);
    border: none;
    border-radius: 10px;
    color: #0e4c92;
    cursor: pointer;
    transition: all 0.3s;
}

.modal-close:hover {
    background: #0e4c92;
    color: white;
    transform: rotate(90deg);
}

/* Hover effects */
.api-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(14,76,146,0.1);
}

.table-action {
    width: 32px;
    height: 32px;
    background: rgba(14,76,146,0.1);
    border: none;
    border-radius: 8px;
    color: #0e4c92;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    text-decoration: none;
}

.table-action:hover {
    background: #0e4c92;
    color: white;
    transform: scale(1.1);
}

/* Make dropdown more visible */
#apiPositionSelect {
    background-color: white;
    font-size: 14px;
    cursor: pointer;
    border: 2px solid #3498db !important;
}

#apiPositionSelect:hover {
    border-color: #2980b9 !important;
    background-color: #f0f7ff;
}

#apiPositionSelect option {
    padding: 10px;
}
</style>