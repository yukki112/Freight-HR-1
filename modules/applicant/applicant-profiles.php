<?php
// modules/applicant/applicant-profiles.php
$page_title = "Applicant Profiles";

// Get applicant ID from URL
$applicant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle document verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_document'])) {
    $doc_id = $_POST['document_id'];
    $verified = $_POST['verified'] ?? 0;
    
    $stmt = $pdo->prepare("UPDATE applicant_documents SET verified = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
    if ($stmt->execute([$verified, $_SESSION['user_id'], $doc_id])) {
        logActivity($pdo, $_SESSION['user_id'], 'verify_document', "Verified document #$doc_id for applicant #$applicant_id");
        $success_message = "Document verification updated!";
    }
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $document_type = $_POST['document_type'];
    $document_name = $_POST['document_name'];
    
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
        $upload_dir = 'uploads/applicants/' . $applicant_id . '/documents/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION);
        $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $document_name) . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $file_path)) {
            $stmt = $pdo->prepare("INSERT INTO applicant_documents (applicant_id, document_type, document_name, file_path, uploaded_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt->execute([$applicant_id, $document_type, $document_name, $file_path])) {
                logActivity($pdo, $_SESSION['user_id'], 'upload_document', "Uploaded document for applicant #$applicant_id");
                $success_message = "Document uploaded successfully!";
            }
        }
    }
}

// Get all applicants for list view
$stmt = $pdo->query("
    SELECT ja.*, 
           jp.title as job_title,
           jp.job_code
    FROM job_applications ja
    LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
    ORDER BY ja.applied_at DESC
");
$applicants = $stmt->fetchAll();

// Get single applicant details for detailed view
if ($applicant_id > 0) {
    // Get applicant main info
    $stmt = $pdo->prepare("
        SELECT ja.*, 
               jp.title as job_title,
               jp.job_code,
               jp.department,
               jp.employment_type
        FROM job_applications ja
        LEFT JOIN job_postings jp ON ja.job_posting_id = jp.id
        WHERE ja.id = ?
    ");
    $stmt->execute([$applicant_id]);
    $applicant = $stmt->fetch();
    
    if (!$applicant) {
        $error_message = "Applicant not found!";
    } else {
        // Get applicant documents
        $stmt = $pdo->prepare("
            SELECT * FROM applicant_documents 
            WHERE applicant_id = ? 
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$applicant_id]);
        $documents = $stmt->fetchAll();
        
        // Parse work experience JSON
        $work_experience = [];
        if (!empty($applicant['work_experience'])) {
            $work_experience = json_decode($applicant['work_experience'], true) ?: [];
        }
        
        // Parse references JSON
        $references = [];
        if (!empty($applicant['references_info'])) {
            $references = json_decode($applicant['references_info'], true) ?: [];
        }
    }
}

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
.applicant-photo {
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

.applicant-photo[src=""], 
.applicant-photo:not([src]) {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    font-weight: 600;
    font-size: 16px;
}

.photo-fallback {
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

/* Profile Photo Large */
.profile-photo-large {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    object-fit: cover;
    margin: 0 auto 15px;
    border: 4px solid #fff;
    box-shadow: 0 15px 30px rgba(14, 76, 146, 0.3);
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 48px;
    font-weight: 600;
}

.profile-photo-large[src=""], 
.profile-photo-large:not([src]) {
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-photo-fallback {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    color: white;
    font-size: 48px;
    font-weight: 600;
    box-shadow: 0 15px 30px rgba(14, 76, 146, 0.3);
}

/* Status Badges */
.category-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.status-new { background: #3498db20; color: #3498db; }
.status-in_review { background: #f39c1220; color: #f39c12; }
.status-shortlisted { background: #27ae6020; color: #27ae60; }
.status-interviewed { background: #9b59b620; color: #9b59b6; }
.status-offered { background: #e67e2220; color: #e67e22; }
.status-hired { background: #2ecc7120; color: #2ecc71; }
.status-rejected { background: #e74c3c20; color: #e74c3c; }

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
    padding: 6px 12px;
    font-size: 12px;
}

/* Back Button */
.back-button {
    margin-bottom: 25px;
}

.back-button a {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #0e4c92 0%, #1e5ca8 100%);
    color: white;
    text-decoration: none;
    border-radius: 15px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
}

.back-button a:hover {
    transform: translateX(-5px);
    box-shadow: 0 15px 30px rgba(14, 76, 146, 0.3);
    background: linear-gradient(135deg, #0a3a70 0%, #0e4c92 100%);
}

.back-button i {
    font-size: 16px;
    transition: transform 0.3s ease;
}

.back-button a:hover i {
    transform: translateX(-3px);
}

/* Detail View Layout */
.detail-container {
    display: grid;
    grid-template-columns: 1.2fr 2fr;
    gap: 25px;
}

.detail-sidebar {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    align-self: start;
}

.detail-main {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

/* Profile Header */
.profile-header {
    text-align: center;
    margin-bottom: 25px;
}

.profile-name {
    font-size: 22px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 5px;
}

.profile-position {
    color: #0e4c92;
    font-weight: 500;
    margin-bottom: 5px;
}

.profile-id {
    font-size: 12px;
    color: #94a3b8;
}

/* Info Sections */
.info-section {
    margin-bottom: 25px;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #eef2f6;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: #0e4c92;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.info-item {
    margin-bottom: 12px;
}

.info-label {
    display: block;
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.info-value {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #2c3e50;
    word-break: break-word;
}

/* Document List */
.document-list {
    list-style: none;
    padding: 0;
}

.document-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    background: #f8fafd;
    border-radius: 12px;
    margin-bottom: 8px;
}

.document-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.document-icon {
    width: 40px;
    height: 40px;
    background: rgba(14, 76, 146, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0e4c92;
    font-size: 18px;
}

.document-name {
    font-weight: 500;
    color: #2c3e50;
}

.document-type {
    font-size: 11px;
    color: #64748b;
    display: block;
}

.document-actions {
    display: flex;
    gap: 8px;
}

/* Tabs */
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 1px solid #eef2f6;
    padding-bottom: 10px;
    flex-wrap: wrap;
}

.tab {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    color: #64748b;
}

.tab:hover {
    background: rgba(14, 76, 146, 0.1);
    color: #0e4c92;
}

.tab.active {
    background: #0e4c92;
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
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
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
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
    font-size: 20px;
    cursor: pointer;
    color: #64748b;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #e74c3c;
}

.modal-body {
    margin-bottom: 20px;
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
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #0e4c92;
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

.alert-error {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid #f5c6cb;
}

/* Image error handling */
.img-error-fallback,
.photo-large-error-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    font-weight: 600;
}

.img-error-fallback {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    font-size: 16px;
}

.photo-large-error-fallback {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    font-size: 48px;
    margin: 0 auto 15px;
    box-shadow: 0 15px 30px rgba(14, 76, 146, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .detail-container {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .tabs {
        gap: 5px;
    }
    
    .tab {
        padding: 6px 12px;
        font-size: 12px;
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
    const isLarge = img.classList.contains('profile-photo-large');
    
    // Create fallback element
    const parent = img.parentNode;
    const fallback = document.createElement('div');
    fallback.className = isLarge ? 'photo-large-error-fallback' : 'img-error-fallback';
    fallback.textContent = initials;
    
    // Replace image with fallback
    parent.replaceChild(fallback, img);
}

function handleProfileImageError(img) {
    handleImageError(img);
}
</script>

<?php if (isset($success_message)): ?>
<div class="alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
</div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert-error">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
</div>
<?php endif; ?>

<?php if ($applicant_id > 0 && $applicant): ?>
    <!-- Back Button with Design -->
    <div class="back-button">
        <a href="?page=applicant&subpage=applicant-profiles">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Applicants List</span>
            <i class="fas fa-users" style="margin-left: auto; opacity: 0.7;"></i>
        </a>
    </div>

    <!-- Detail View -->
    <div class="detail-container">
        <!-- Sidebar -->
        <div class="detail-sidebar">
            <div class="profile-header">
                <?php 
                $photoPath = getApplicantPhoto($applicant);
                $firstName = $applicant['first_name'] ?? '';
                $lastName = $applicant['last_name'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
                $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                
                if ($photoPath): ?>
                    <img src="<?php echo $photoPath; ?>" 
                         alt="<?php echo htmlspecialchars($fullName); ?>"
                         class="profile-photo-large"
                         onerror="handleProfileImageError(this)"
                         data-initials="<?php echo $initials; ?>"
                         loading="lazy">
                <?php else: ?>
                    <div class="profile-photo-fallback">
                        <?php echo $initials; ?>
                    </div>
                <?php endif; ?>
                
                <h2 class="profile-name"><?php echo htmlspecialchars($fullName); ?></h2>
                <div class="profile-position">
                    <?php if (!empty($applicant['job_title'])): ?>
                        <?php echo htmlspecialchars($applicant['job_title']); ?>
                    <?php else: ?>
                        <?php echo htmlspecialchars($applicant['position_applied'] ?? 'General Application'); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-id">Application #: <?php echo $applicant['application_number']; ?></div>
                
                <!-- Status Badge -->
                <div style="margin-top: 15px;">
                    <?php
                    $status = $applicant['status'] ?? 'new';
                    $status_class = 'status-' . str_replace('_', '-', $status);
                    $status_labels = [
                        'new' => 'New',
                        'in_review' => 'In Review',
                        'shortlisted' => 'Shortlisted',
                        'interviewed' => 'Interviewed',
                        'offered' => 'Offered',
                        'hired' => 'Hired',
                        'rejected' => 'Rejected'
                    ];
                    $status_label = $status_labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                    ?>
                    <span class="category-badge <?php echo $status_class; ?>" style="padding: 8px 16px; font-size: 14px;">
                        <?php echo $status_label; ?>
                    </span>
                </div>
            </div>

            <!-- Quick Info -->
            <div class="info-section">
                <div class="section-title">
                    <i class="fas fa-address-card"></i> Contact Information
                </div>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="info-value">
                        <a href="mailto:<?php echo htmlspecialchars($applicant['email']); ?>" style="color: #0e4c92; text-decoration: none;">
                            <?php echo htmlspecialchars($applicant['email']); ?>
                        </a>
                    </span>
                </div>
                <?php if (!empty($applicant['phone'])): ?>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="info-value"><?php echo htmlspecialchars($applicant['phone']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($applicant['address'])): ?>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address</span>
                    <span class="info-value"><?php echo htmlspecialchars($applicant['address']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="info-section">
                <div class="section-title">
                    <i class="fas fa-user"></i> Personal Information
                </div>
                <?php if (!empty($applicant['birth_date'])): ?>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-calendar"></i> Birth Date</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($applicant['birth_date'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($applicant['gender'])): ?>
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-venus-mars"></i> Gender</span>
                    <span class="info-value"><?php echo ucfirst($applicant['gender']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="detail-main">
            <!-- Tabs (Profile and Documents only) -->
            <div class="tabs">
                <div class="tab active" onclick="showTab('profile')">Profile</div>
                <div class="tab" onclick="showTab('documents')">Documents</div>
            </div>

            <!-- Profile Tab -->
            <div id="tab-profile" class="tab-content active">
                <!-- Education Section -->
                <div class="info-section">
                    <div class="section-title">
                        <i class="fas fa-graduation-cap"></i> Educational Background
                    </div>
                    <div class="info-grid">
                        <?php if (!empty($applicant['elementary_school'])): ?>
                        <div class="info-item">
                            <span class="info-label">Elementary</span>
                            <span class="info-value"><?php echo htmlspecialchars($applicant['elementary_school']); ?></span>
                            <?php if (!empty($applicant['elementary_year'])): ?>
                            <span style="font-size: 11px; color: #64748b;">(<?php echo $applicant['elementary_year']; ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($applicant['high_school'])): ?>
                        <div class="info-item">
                            <span class="info-label">High School</span>
                            <span class="info-value"><?php echo htmlspecialchars($applicant['high_school']); ?></span>
                            <?php if (!empty($applicant['high_school_year'])): ?>
                            <span style="font-size: 11px; color: #64748b;">(<?php echo $applicant['high_school_year']; ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($applicant['senior_high'])): ?>
                        <div class="info-item">
                            <span class="info-label">Senior High</span>
                            <span class="info-value"><?php echo htmlspecialchars($applicant['senior_high']); ?></span>
                            <?php if (!empty($applicant['senior_high_strand'])): ?>
                            <span style="font-size: 11px; color: #64748b;">- <?php echo $applicant['senior_high_strand']; ?></span>
                            <?php endif; ?>
                            <?php if (!empty($applicant['senior_high_year'])): ?>
                            <span style="font-size: 11px; color: #64748b;">(<?php echo $applicant['senior_high_year']; ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($applicant['college'])): ?>
                        <div class="info-item">
                            <span class="info-label">College</span>
                            <span class="info-value"><?php echo htmlspecialchars($applicant['college']); ?></span>
                            <?php if (!empty($applicant['college_course'])): ?>
                            <span style="font-size: 11px; color: #64748b;">- <?php echo $applicant['college_course']; ?></span>
                            <?php endif; ?>
                            <?php if (!empty($applicant['college_year'])): ?>
                            <span style="font-size: 11px; color: #64748b;">(<?php echo $applicant['college_year']; ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($applicant['vocational'])): ?>
                        <div class="info-item">
                            <span class="info-label">Vocational</span>
                            <span class="info-value"><?php echo htmlspecialchars($applicant['vocational']); ?></span>
                            <?php if (!empty($applicant['vocational_course'])): ?>
                            <span style="font-size: 11px; color: #64748b;">- <?php echo $applicant['vocational_course']; ?></span>
                            <?php endif; ?>
                            <?php if (!empty($applicant['vocational_year'])): ?>
                            <span style="font-size: 11px; color: #64748b;">(<?php echo $applicant['vocational_year']; ?>)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Work Experience Section -->
                <?php if (!empty($work_experience)): ?>
                <div class="info-section">
                    <div class="section-title">
                        <i class="fas fa-briefcase"></i> Work Experience
                    </div>
                    <?php foreach ($work_experience as $exp): ?>
                    <div style="background: #f8fafd; border-radius: 12px; padding: 15px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong><?php echo htmlspecialchars($exp['position'] ?? ''); ?></strong>
                            <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($exp['from_year'] ?? ''); ?> - <?php echo htmlspecialchars($exp['to_year'] ?? ''); ?></span>
                        </div>
                        <div style="font-size: 13px; color: #0e4c92; margin-bottom: 8px;">
                            <?php echo htmlspecialchars($exp['company'] ?? ''); ?>
                        </div>
                        <?php if (!empty($exp['responsibilities'])): ?>
                        <div style="font-size: 13px; color: #2c3e50;">
                            <?php echo nl2br(htmlspecialchars($exp['responsibilities'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Skills & Certifications -->
                <?php if (!empty($applicant['skills']) || !empty($applicant['certifications'])): ?>
                <div class="info-section">
                    <div class="section-title">
                        <i class="fas fa-code"></i> Skills & Certifications
                    </div>
                    <div class="info-grid">
                        <?php if (!empty($applicant['skills'])): ?>
                        <div class="info-item">
                            <span class="info-label">Skills</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($applicant['skills'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($applicant['certifications'])): ?>
                        <div class="info-item">
                            <span class="info-label">Certifications</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($applicant['certifications'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- References -->
                <?php if (!empty($references)): ?>
                <div class="info-section">
                    <div class="section-title">
                        <i class="fas fa-address-book"></i> References
                    </div>
                    <?php foreach ($references as $ref): ?>
                    <div style="background: #f8fafd; border-radius: 12px; padding: 15px; margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between;">
                            <strong><?php echo htmlspecialchars($ref['name'] ?? ''); ?></strong>
                            <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($ref['relationship'] ?? ''); ?></span>
                        </div>
                        <div style="font-size: 13px; color: #0e4c92; margin: 5px 0;">
                            <?php echo htmlspecialchars($ref['position'] ?? ''); ?> at <?php echo htmlspecialchars($ref['company'] ?? ''); ?>
                        </div>
                        <div style="font-size: 12px;">
                            <i class="fas fa-phone" style="color: #0e4c92;"></i> <?php echo htmlspecialchars($ref['contact'] ?? ''); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Documents Tab -->
            <div id="tab-documents" class="tab-content">
                <div class="info-section">
                    <div class="section-title" style="justify-content: space-between;">
                        <span><i class="fas fa-file"></i> Documents</span>
                        <button class="btn-primary btn-sm" onclick="openUploadModal()">
                            <i class="fas fa-upload"></i> Upload Document
                        </button>
                    </div>

                    <?php if (empty($documents)): ?>
                    <div style="text-align: center; padding: 40px; color: #95a5a6;">
                        <i class="fas fa-file" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>No documents uploaded yet</p>
                    </div>
                    <?php else: ?>
                    <ul class="document-list">
                        <?php foreach ($documents as $doc): ?>
                        <li class="document-item">
                            <div class="document-info">
                                <div class="document-icon">
                                    <?php
                                    $icon = 'fa-file';
                                    if ($doc['document_type'] == 'resume') $icon = 'fa-file-pdf';
                                    elseif ($doc['document_type'] == 'license') $icon = 'fa-id-card';
                                    elseif ($doc['document_type'] == 'id') $icon = 'fa-id-badge';
                                    elseif ($doc['document_type'] == 'certificate') $icon = 'fa-certificate';
                                    elseif ($doc['document_type'] == 'nbi') $icon = 'fa-shield-alt';
                                    elseif ($doc['document_type'] == 'medical') $icon = 'fa-hospital';
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div>
                                    <span class="document-name"><?php echo htmlspecialchars($doc['document_name'] ?: ucfirst($doc['document_type'])); ?></span>
                                    <span class="document-type"><?php echo ucfirst($doc['document_type']); ?> • <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></span>
                                    <?php if ($doc['verified']): ?>
                                    <span style="font-size: 11px; color: #27ae60; margin-left: 10px;">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                    <?php else: ?>
                                    <span style="font-size: 11px; color: #e74c3c; margin-left: 10px;">
                                        <i class="fas fa-times-circle"></i> Pending
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="document-actions">
                                <?php if (!$doc['verified']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                    <input type="hidden" name="verified" value="1">
                                    <button type="submit" name="verify_document" class="btn-secondary btn-sm" title="Verify Document">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn-secondary btn-sm" title="View Document">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" download class="btn-secondary btn-sm" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <!-- Resume Preview -->
                <?php if (!empty($applicant['resume_path'])): ?>
                <div class="info-section" style="margin-top: 20px;">
                    <div class="section-title">
                        <i class="fas fa-file-pdf"></i> Resume/CV
                    </div>
                    <div style="background: #f8fafd; border-radius: 12px; padding: 20px; text-align: center;">
                        <i class="fas fa-file-pdf" style="font-size: 48px; color: #e74c3c; margin-bottom: 10px;"></i>
                        <p style="margin-bottom: 15px;"><?php echo basename($applicant['resume_path']); ?></p>
                        <a href="<?php echo htmlspecialchars($applicant['resume_path']); ?>" target="_blank" class="btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View Resume
                        </a>
                        <a href="<?php echo htmlspecialchars($applicant['resume_path']); ?>" download class="btn-secondary btn-sm">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($applicant['cover_letter_path'])): ?>
                <div class="info-section" style="margin-top: 20px;">
                    <div class="section-title">
                        <i class="fas fa-file-alt"></i> Cover Letter
                    </div>
                    <div style="background: #f8fafd; border-radius: 12px; padding: 20px; text-align: center;">
                        <i class="fas fa-file-alt" style="font-size: 48px; color: #0e4c92; margin-bottom: 10px;"></i>
                        <p style="margin-bottom: 15px;"><?php echo basename($applicant['cover_letter_path']); ?></p>
                        <a href="<?php echo htmlspecialchars($applicant['cover_letter_path']); ?>" target="_blank" class="btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View Cover Letter
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-upload" style="color: #0e4c92;"></i> Upload Document</h3>
                <button class="modal-close" onclick="closeUploadModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="document_type">Document Type</label>
                        <select name="document_type" id="document_type" required>
                            <option value="">Select Document Type</option>
                            <option value="resume">Resume/CV</option>
                            <option value="license">Driver's License</option>
                            <option value="id">Government ID</option>
                            <option value="certificate">Certificate</option>
                            <option value="nbi">NBI Clearance</option>
                            <option value="medical">Medical Certificate</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="document_name">Document Name</label>
                        <input type="text" name="document_name" id="document_name" placeholder="e.g., Updated Resume 2026" required>
                    </div>
                    <div class="form-group">
                        <label for="document_file">Select File</label>
                        <input type="file" name="document_file" id="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <small style="color: #64748b; font-size: 11px;">Allowed: PDF, DOC, DOCX, JPG, PNG (Max: 5MB)</small>
                    </div>
                </div>
                <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" name="upload_document" class="btn-primary">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Remove active class from all tab buttons
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById('tab-' + tabName).classList.add('active');
        
        // Add active class to clicked tab
        event.target.classList.add('active');
    }
    
    function openUploadModal() {
        document.getElementById('uploadModal').classList.add('active');
    }
    
    function closeUploadModal() {
        document.getElementById('uploadModal').classList.remove('active');
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('uploadModal');
        if (event.target == modal) {
            modal.classList.remove('active');
        }
    }
    </script>

<?php else: ?>
    <!-- List View -->
    <div class="stats-grid-unique">
        <div class="stat-card-unique">
            <div class="stat-icon-3d">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label">Total Applicants</span>
                <span class="stat-value"><?php echo count($applicants); ?></span>
            </div>
        </div>
        <div class="stat-card-unique">
            <div class="stat-icon-3d">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label">In Review</span>
                <span class="stat-value">
                    <?php echo count(array_filter($applicants, function($a) { return $a['status'] == 'in_review'; })); ?>
                </span>
            </div>
        </div>
        <div class="stat-card-unique">
            <div class="stat-icon-3d">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label">Shortlisted</span>
                <span class="stat-value">
                    <?php echo count(array_filter($applicants, function($a) { return $a['status'] == 'shortlisted'; })); ?>
                </span>
            </div>
        </div>
        <div class="stat-card-unique">
            <div class="stat-icon-3d">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <span class="stat-label">This Month</span>
                <span class="stat-value">
                    <?php echo count(array_filter($applicants, function($a) { 
                        return date('Y-m', strtotime($a['applied_at'])) == date('Y-m'); 
                    })); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="table-container">
        <table class="unique-table">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Position</th>
                    <th>Applied</th>
                    <th>Status</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($applicants)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #95a5a6;">
                        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>No applicants found</p>
                        <a href="?page=recruitment&subpage=job-posting" class="btn-secondary btn-sm" style="margin-top: 10px;">
                            <i class="fas fa-plus"></i> Create Job Posting
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($applicants as $applicant): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php 
                                $photoPath = getApplicantPhoto($applicant);
                                $firstName = $applicant['first_name'] ?? '';
                                $lastName = $applicant['last_name'] ?? '';
                                $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                                
                                if ($photoPath): ?>
                                    <img src="<?php echo $photoPath; ?>" 
                                         alt="<?php echo htmlspecialchars($firstName . ' ' . $lastName); ?>"
                                         class="applicant-photo"
                                         onerror="handleImageError(this)"
                                         data-initials="<?php echo $initials; ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="photo-fallback">
                                        <?php echo $initials; ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></strong>
                                    <div style="font-size: 11px; color: #64748b;">#<?php echo $applicant['application_number']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($applicant['job_title'])): ?>
                                <strong><?php echo htmlspecialchars($applicant['job_title']); ?></strong>
                                <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($applicant['job_code']); ?></div>
                            <?php else: ?>
                                <span style="color: #64748b;">General Application</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span><?php echo date('M d, Y', strtotime($applicant['applied_at'])); ?></span>
                            <div style="font-size: 11px; color: #64748b;"><?php echo timeAgo($applicant['applied_at']); ?></div>
                        </td>
                        <td>
                            <?php
                            $status = $applicant['status'] ?? 'new';
                            $status_class = 'status-' . str_replace('_', '-', $status);
                            $status_labels = [
                                'new' => 'New',
                                'in_review' => 'In Review',
                                'shortlisted' => 'Shortlisted',
                                'interviewed' => 'Interviewed',
                                'offered' => 'Offered',
                                'hired' => 'Hired',
                                'rejected' => 'Rejected'
                            ];
                            $status_label = $status_labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                            ?>
                            <span class="category-badge <?php echo $status_class; ?>">
                                <?php echo $status_label; ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($applicant['email'])): ?>
                            <div><i class="fas fa-envelope" style="color: #0e4c92; width: 16px;"></i> <?php echo htmlspecialchars($applicant['email']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($applicant['phone'])): ?>
                            <div style="font-size: 12px; margin-top: 3px;"><i class="fas fa-phone" style="color: #0e4c92; width: 16px;"></i> <?php echo htmlspecialchars($applicant['phone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?page=applicant&subpage=applicant-profiles&id=<?php echo $applicant['id']; ?>" class="btn-secondary btn-sm" style="padding: 6px 12px;">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>