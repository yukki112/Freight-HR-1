<?php
// modules/applicant/document-verification.php
$page_title = "Document Verification";

// Handle document verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_all'])) {
        $applicant_id = $_POST['applicant_id'];
        $verified = $_POST['verified'] ?? 1;
        
        // Update all documents for this applicant
        $stmt = $pdo->prepare("UPDATE applicant_documents SET verified = ?, verified_by = ?, verified_at = NOW() WHERE applicant_id = ?");
        $stmt->execute([$verified, $_SESSION['user_id'], $applicant_id]);
        
        // Update job applications documents_verified
        $stmt = $pdo->prepare("UPDATE job_applications SET documents_verified = ? WHERE id = ?");
        if ($stmt->execute([$verified, $applicant_id])) {
            logActivity($pdo, $_SESSION['user_id'], 'verify_all_documents', "Verified all documents for applicant #$applicant_id");
            $success_message = "All documents verified successfully!";
        }
    } elseif (isset($_POST['verify_single'])) {
        $doc_id = $_POST['document_id'];
        $applicant_id = $_POST['applicant_id'];
        $verified = $_POST['verified'] ?? 1;
        $source = $_POST['source'] ?? 'documents';
        
        if ($source === 'job_applications') {
            // Update verification status in job_applications table
            $stmt = $pdo->prepare("UPDATE job_applications SET documents_verified = ? WHERE id = ?");
            if ($stmt->execute([$verified, $applicant_id])) {
                logActivity($pdo, $_SESSION['user_id'], 'verify_document', "Verified documents for applicant #$applicant_id");
                $success_message = "Document verification updated!";
            }
        } else {
            $stmt = $pdo->prepare("UPDATE applicant_documents SET verified = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
            if ($stmt->execute([$verified, $_SESSION['user_id'], $doc_id])) {
                logActivity($pdo, $_SESSION['user_id'], 'verify_document', "Verified document #$doc_id for applicant #$applicant_id");
                $success_message = "Document verification updated!";
            }
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$search_filter = $_GET['search'] ?? '';

// Get all applicants with their document status
$query = "
    SELECT 
        a.id,
        a.first_name,
        a.last_name,
        a.application_number,
        a.email,
        a.phone,
        a.resume_path,
        a.cover_letter_path,
        a.photo_path,
        a.documents_verified,
        a.applied_at,
        jp.title as job_title,
        jp.job_code,
        jp.department,
        (SELECT COUNT(*) FROM applicant_documents d WHERE d.applicant_id = a.id) as additional_docs_count,
        (SELECT COUNT(*) FROM applicant_documents d WHERE d.applicant_id = a.id AND d.verified = 1) as additional_docs_verified,
        (SELECT COUNT(*) FROM applicant_documents d WHERE d.applicant_id = a.id AND d.verified = 0) as additional_docs_pending
    FROM job_applications a
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    WHERE 1=1
";

$params = [];

// Status filter
if ($status_filter === 'pending') {
    $query .= " AND (
        a.documents_verified = 0 OR a.documents_verified IS NULL
        OR EXISTS (SELECT 1 FROM applicant_documents d WHERE d.applicant_id = a.id AND d.verified = 0)
    )";
} elseif ($status_filter === 'verified') {
    $query .= " AND a.documents_verified = 1 
                AND NOT EXISTS (SELECT 1 FROM applicant_documents d WHERE d.applicant_id = a.id AND d.verified = 0)";
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

// Get documents for a specific applicant (for modal)
$selected_applicant = null;
$applicant_documents = [];

if (isset($_GET['view_applicant'])) {
    $applicant_id = (int)$_GET['view_applicant'];
    
    // Get applicant details
    $stmt = $pdo->prepare("
        SELECT a.*, jp.title as job_title, jp.job_code, jp.department
        FROM job_applications a
        LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
        WHERE a.id = ?
    ");
    $stmt->execute([$applicant_id]);
    $selected_applicant = $stmt->fetch();
    
    if ($selected_applicant) {
        // Get additional documents
        $stmt = $pdo->prepare("SELECT * FROM applicant_documents WHERE applicant_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$applicant_id]);
        $applicant_documents = $stmt->fetchAll();
    }
}

// Get statistics
$stats = [];

// Total applicants with pending documents
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT a.id) as count
    FROM job_applications a
    WHERE a.documents_verified = 0 OR a.documents_verified IS NULL
    OR EXISTS (SELECT 1 FROM applicant_documents d WHERE d.applicant_id = a.id AND d.verified = 0)
");
$stats['pending_applicants'] = $stmt->fetchColumn();

// Total applicants with all documents verified
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT a.id) as count
    FROM job_applications a
    WHERE a.documents_verified = 1 
    AND NOT EXISTS (SELECT 1 FROM applicant_documents d WHERE d.applicant_id = a.id AND d.verified = 0)
");
$stats['verified_applicants'] = $stmt->fetchColumn();

// Total documents pending
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM applicant_documents WHERE verified = 0) +
        (SELECT COUNT(*) FROM job_applications WHERE (resume_path IS NOT NULL OR cover_letter_path IS NOT NULL) AND (documents_verified = 0 OR documents_verified IS NULL)) as total
");
$stats['pending_documents'] = $stmt->fetchColumn();

// Total documents verified
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM applicant_documents WHERE verified = 1) +
        (SELECT COUNT(*) FROM job_applications WHERE (resume_path IS NOT NULL OR cover_letter_path IS NOT NULL) AND documents_verified = 1) as total
");
$stats['verified_documents'] = $stmt->fetchColumn();

// Add documents_verified column if it doesn't exist
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM job_applications LIKE 'documents_verified'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE job_applications ADD COLUMN documents_verified TINYINT(1) DEFAULT 0 AFTER cover_letter_path");
    }
} catch (Exception $e) {
    // Column might already exist
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
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
    margin-bottom: 15px;
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
    margin-top: 15px;
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
.applicant-photo-large {
    width: 50px;
    height: 50px;
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
    font-size: 18px;
    flex-shrink: 0;
}

.applicant-photo-large[src=""], 
.applicant-photo-large:not([src]) {
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-fallback-large {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 18px;
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

.badge-info {
    background: #3498db20;
    color: #3498db;
}

/* Progress Bar */
.document-progress {
    width: 100px;
    height: 6px;
    background: #eef2f6;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 5px;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #0e4c92, #4086e4);
    border-radius: 3px;
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
    max-height: 80vh;
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

/* Document Cards in Modal */
.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.document-card {
    background: #f8fafd;
    border-radius: 15px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border: 1px solid #eef2f6;
}

.document-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.1);
}

.doc-icon-large {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.doc-icon-large.resume { background: #e74c3c20; color: #e74c3c; }
.doc-icon-large.cover_letter { background: #3498db20; color: #3498db; }
.doc-icon-large.license { background: #3498db20; color: #3498db; }
.doc-icon-large.id { background: #9b59b620; color: #9b59b6; }
.doc-icon-large.certificate { background: #f1c40f20; color: #f39c12; }
.doc-icon-large.nbi { background: #2ecc7120; color: #27ae60; }
.doc-icon-large.medical { background: #e67e2220; color: #e67e22; }

.doc-info {
    flex: 1;
}

.doc-info h5 {
    font-size: 15px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 5px 0;
}

.doc-info p {
    font-size: 12px;
    color: #64748b;
    margin: 0;
}

.doc-actions {
    display: flex;
    gap: 8px;
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
.img-error-fallback-large,
.modal-img-error-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    color: white;
    font-weight: 600;
}

.img-error-fallback-large {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    font-size: 18px;
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
    
    .documents-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer form {
        width: 100%;
    }
    
    .modal-footer button,
    .modal-footer a {
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
    fallback.className = isModal ? 'modal-img-error-fallback' : 'img-error-fallback-large';
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
        <i class="fas fa-file-signature"></i>
        <h1><?php echo $page_title; ?></h1>
    </div>
    <div>
        <a href="?page=applicant&subpage=document-verification&status=pending" class="btn-secondary btn-sm <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Pending
        </a>
        <a href="?page=applicant&subpage=document-verification&status=verified" class="btn-secondary btn-sm <?php echo $status_filter == 'verified' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i> Verified
        </a>
        <a href="?page=applicant&subpage=document-verification&status=all" class="btn-secondary btn-sm <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> All
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
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Applicants</span>
            <span class="stat-value"><?php echo $stats['pending_applicants']; ?></span>
        </div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Verified Applicants</span>
            <span class="stat-value"><?php echo $stats['verified_applicants']; ?></span>
        </div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-file"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Pending Documents</span>
            <span class="stat-value"><?php echo $stats['pending_documents']; ?></span>
        </div>
    </div>
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-check-double"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Verified Documents</span>
            <span class="stat-value"><?php echo $stats['verified_documents']; ?></span>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-title">
        <i class="fas fa-filter"></i> Filter Applicants
    </div>
    <form method="GET">
        <input type="hidden" name="page" value="applicant">
        <input type="hidden" name="subpage" value="document-verification">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Applicants</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Documents</option>
                    <option value="verified" <?php echo $status_filter == 'verified' ? 'selected' : ''; ?>>All Documents Verified</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, Email, or Application #" value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <a href="?page=applicant&subpage=document-verification" class="btn-secondary">
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
                <th>Documents</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($applicants)): ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 60px 20px; color: #95a5a6;">
                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p>No applicants found</p>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($applicants as $applicant): 
                    $total_docs = 1 + ($applicant['cover_letter_path'] ? 1 : 0) + $applicant['additional_docs_count'];
                    $verified_docs = ($applicant['documents_verified'] ? 1 : 0) + 
                                    ($applicant['documents_verified'] && $applicant['cover_letter_path'] ? 1 : 0) + 
                                    $applicant['additional_docs_verified'];
                    $pending_docs = $total_docs - $verified_docs;
                    $progress_percent = $total_docs > 0 ? ($verified_docs / $total_docs) * 100 : 0;
                    
                    $photoPath = getApplicantPhoto($applicant);
                    $firstName = $applicant['first_name'] ?? '';
                    $lastName = $applicant['last_name'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName) ?: 'Unnamed Applicant';
                    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: '?';
                ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <?php if ($photoPath): ?>
                                <img src="<?php echo $photoPath; ?>" 
                                     alt="<?php echo htmlspecialchars($fullName); ?>"
                                     class="applicant-photo-large"
                                     onerror="handleImageError(this)"
                                     data-initials="<?php echo $initials; ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="photo-fallback-large">
                                    <?php echo $initials; ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong style="font-size: 16px;"><?php echo htmlspecialchars($fullName); ?></strong>
                                <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                                    <i class="fas fa-envelope" style="color: #0e4c92;"></i> <?php echo htmlspecialchars($applicant['email']); ?>
                                </div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">
                                    #<?php echo $applicant['application_number']; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($applicant['job_title'])): ?>
                            <strong><?php echo htmlspecialchars($applicant['job_title']); ?></strong>
                            <?php if (!empty($applicant['job_code'])): ?>
                            <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($applicant['job_code']); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #64748b;">General Application</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span><?php echo date('M d, Y', strtotime($applicant['applied_at'])); ?></span>
                        <div style="font-size: 11px; color: #64748b;"><?php echo timeAgo($applicant['applied_at']); ?></div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-weight: 600;"><?php echo $verified_docs; ?>/<?php echo $total_docs; ?></span>
                            <div class="document-progress">
                                <div class="progress-bar" style="width: <?php echo $progress_percent; ?>%"></div>
                            </div>
                        </div>
                        <?php if ($pending_docs > 0): ?>
                        <div style="font-size: 11px; color: #f39c12; margin-top: 5px;">
                            <i class="fas fa-clock"></i> <?php echo $pending_docs; ?> pending
                        </div>
                        <?php else: ?>
                        <div style="font-size: 11px; color: #27ae60; margin-top: 5px;">
                            <i class="fas fa-check-circle"></i> All verified
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pending_docs == 0): ?>
                        <span class="category-badge badge-success">
                            <i class="fas fa-check-circle"></i> Verified
                        </span>
                        <?php else: ?>
                        <span class="category-badge badge-warning">
                            <i class="fas fa-clock"></i> Pending
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=applicant&subpage=document-verification&view_applicant=<?php echo $applicant['id']; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?>" class="btn-primary btn-sm" style="text-decoration: none;">
                            <i class="fas fa-eye"></i> View Documents
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Document View Modal -->
<?php if ($selected_applicant): ?>
<div id="documentModal" class="modal active">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-signature" style="color: #0e4c92;"></i> Document Verification</h3>
            <a href="?page=applicant&subpage=document-verification&status=<?php echo $status_filter; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?>" class="modal-close">&times;</a>
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
                <p><i class="fas fa-hashtag"></i> Application #: <?php echo $selected_applicant['application_number']; ?></p>
            </div>
        </div>
        
        <!-- Documents Grid -->
        <div class="documents-grid">
            <!-- Resume -->
            <?php if (!empty($selected_applicant['resume_path'])): 
                $verified = $selected_applicant['documents_verified'] ?? 0;
            ?>
            <div class="document-card">
                <div class="doc-icon-large resume">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="doc-info">
                    <h5>Resume/CV</h5>
                    <p><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($selected_applicant['applied_at'])); ?></p>
                    <?php if ($verified): ?>
                    <p style="color: #27ae60;"><i class="fas fa-check-circle"></i> Verified</p>
                    <?php else: ?>
                    <p style="color: #f39c12;"><i class="fas fa-clock"></i> Pending</p>
                    <?php endif; ?>
                </div>
                <div class="doc-actions">
                    <a href="<?php echo htmlspecialchars($selected_applicant['resume_path']); ?>" target="_blank" class="btn-secondary btn-sm" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="<?php echo htmlspecialchars($selected_applicant['resume_path']); ?>" download class="btn-secondary btn-sm" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Cover Letter -->
            <?php if (!empty($selected_applicant['cover_letter_path'])): 
                $verified = $selected_applicant['documents_verified'] ?? 0;
            ?>
            <div class="document-card">
                <div class="doc-icon-large cover_letter">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="doc-info">
                    <h5>Cover Letter</h5>
                    <p><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($selected_applicant['applied_at'])); ?></p>
                    <?php if ($verified): ?>
                    <p style="color: #27ae60;"><i class="fas fa-check-circle"></i> Verified</p>
                    <?php else: ?>
                    <p style="color: #f39c12;"><i class="fas fa-clock"></i> Pending</p>
                    <?php endif; ?>
                </div>
                <div class="doc-actions">
                    <a href="<?php echo htmlspecialchars($selected_applicant['cover_letter_path']); ?>" target="_blank" class="btn-secondary btn-sm" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="<?php echo htmlspecialchars($selected_applicant['cover_letter_path']); ?>" download class="btn-secondary btn-sm" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Additional Documents -->
            <?php foreach ($applicant_documents as $doc): ?>
            <div class="document-card">
                <div class="doc-icon-large <?php echo $doc['document_type']; ?>">
                    <?php
                    $icon = 'fa-file';
                    if ($doc['document_type'] == 'resume') $icon = 'fa-file-pdf';
                    elseif ($doc['document_type'] == 'cover_letter') $icon = 'fa-file-alt';
                    elseif ($doc['document_type'] == 'license') $icon = 'fa-id-card';
                    elseif ($doc['document_type'] == 'id') $icon = 'fa-id-badge';
                    elseif ($doc['document_type'] == 'certificate') $icon = 'fa-certificate';
                    elseif ($doc['document_type'] == 'nbi') $icon = 'fa-shield-alt';
                    elseif ($doc['document_type'] == 'medical') $icon = 'fa-hospital';
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="doc-info">
                    <h5><?php echo htmlspecialchars($doc['document_name'] ?: ucfirst($doc['document_type'])); ?></h5>
                    <p><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></p>
                    <?php if ($doc['verified']): ?>
                    <p style="color: #27ae60;"><i class="fas fa-check-circle"></i> Verified</p>
                    <?php else: ?>
                    <p style="color: #f39c12;"><i class="fas fa-clock"></i> Pending</p>
                    <?php endif; ?>
                </div>
                <div class="doc-actions">
                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn-secondary btn-sm" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" download class="btn-secondary btn-sm" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                    <?php if (!$doc['verified']): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                        <input type="hidden" name="applicant_id" value="<?php echo $selected_applicant['id']; ?>">
                        <input type="hidden" name="verified" value="1">
                        <input type="hidden" name="source" value="documents">
                        <button type="submit" name="verify_single" class="btn-success btn-sm" title="Verify">
                            <i class="fas fa-check"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Modal Footer -->
        <div class="modal-footer">
            <?php
            $all_verified = true;
            if (($selected_applicant['resume_path'] && !$selected_applicant['documents_verified'])) $all_verified = false;
            if (($selected_applicant['cover_letter_path'] && !$selected_applicant['documents_verified'])) $all_verified = false;
            foreach ($applicant_documents as $doc) {
                if (!$doc['verified']) $all_verified = false;
            }
            ?>
            
            <?php if (!$all_verified): ?>
            <form method="POST">
                <input type="hidden" name="applicant_id" value="<?php echo $selected_applicant['id']; ?>">
                <input type="hidden" name="verified" value="1">
                <button type="submit" name="verify_all" class="btn-primary">
                    <i class="fas fa-check-double"></i> Verify All Documents
                </button>
            </form>
            <?php endif; ?>
            
            <a href="?page=applicant&subpage=applicant-profiles&id=<?php echo $selected_applicant['id']; ?>" class="btn-secondary">
                <i class="fas fa-user"></i> View Full Profile
            </a>
            
            <a href="?page=applicant&subpage=document-verification&status=<?php echo $status_filter; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?>" class="btn-secondary">
                <i class="fas fa-times"></i> Close
            </a>
        </div>
    </div>
</div>

<script>
// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('documentModal');
    if (event.target == modal) {
        window.location.href = '?page=applicant&subpage=document-verification&status=<?php echo $status_filter; ?><?php echo !empty($search_filter) ? '&search=' . urlencode($search_filter) : ''; ?>';
    }
}
</script>
<?php endif; ?>