<?php
// modules/recognition/recognition-feed.php
$page_title = "Recognition Feed";

// Check if user has access
$is_admin = ($_SESSION['role'] === 'admin');
$is_manager = ($_SESSION['role'] === 'manager');
$is_hr = ($is_admin || $is_manager);
$is_supervisor = ($is_admin || $is_manager);
$user_id = $_SESSION['user_id'];

// Get settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM recognition_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get categories
$stmt = $pdo->query("SELECT * FROM recognition_categories WHERE is_active = 1 ORDER BY sort_order");
$categories = $stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Create new recognition post
    if (isset($_POST['create_post'])) {
        $employee_id = $_POST['employee_id'];
        $recognition_type = $_POST['recognition_type'];
        $category = $_POST['category'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $achievement_details = $_POST['achievement_details'];
        $visibility = $_POST['visibility'] ?? 'company';
        
        // Check peer recognition limits
        if ($recognition_type === 'peer') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM recognition_posts 
                WHERE posted_by = ? AND recognition_type = 'peer' 
                AND MONTH(created_at) = MONTH(CURRENT_DATE())
                AND YEAR(created_at) = YEAR(CURRENT_DATE())
            ");
            $stmt->execute([$user_id]);
            $peer_count = $stmt->fetchColumn();
            
            $limit = $settings['peer_recognition_limit'] ?? 3;
            if ($peer_count >= $limit) {
                $error_message = "You have reached your monthly limit of $limit peer recognitions.";
            }
        }
        
        // Handle file upload
        $attachment_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK && $settings['allow_image_attachments'] == '1') {
            $max_size = ($settings['max_attachments_size'] ?? 5) * 1024 * 1024;
            if ($_FILES['attachment']['size'] <= $max_size) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                if (in_array($_FILES['attachment']['type'], $allowed_types)) {
                    $upload_dir = 'uploads/recognition/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                    $filename = 'recog_' . uniqid() . '_' . time() . '.' . $extension;
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
                        $attachment_path = $target_path;
                    }
                }
            }
        }
        
        if (!isset($error_message)) {
            // Generate post number
            $year = date('Y');
            $month = date('m');
            $prefix = strtoupper(substr($recognition_type, 0, 2));
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM recognition_posts WHERE YEAR(created_at) = $year");
            $count = $stmt->fetchColumn() + 1;
            $post_number = sprintf("%s-%s%s-%04d", $prefix, $year, $month, $count);
            
            // Determine poster role
            $poster_role = 'system';
            if ($recognition_type === 'supervisor') $poster_role = 'supervisor';
            elseif ($recognition_type === 'peer') $poster_role = 'peer';
            elseif ($recognition_type === 'employee_month') $poster_role = 'hr';
            
            // Check if approval required
            $is_approved = ($settings['require_approval'] == '1') ? 0 : 1;
            
            $stmt = $pdo->prepare("
                INSERT INTO recognition_posts 
                (post_number, employee_id, recognition_type, category, title, description, 
                 achievement_details, attachment_path, posted_by, poster_role, visibility, is_approved)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $post_number,
                $employee_id,
                $recognition_type,
                $category,
                $title,
                $description,
                $achievement_details,
                $attachment_path,
                $user_id,
                $poster_role,
                $visibility,
                $is_approved
            ]);
            
            $post_id = $pdo->lastInsertId();
            
            // Handle mentions (if any)
            if (isset($_POST['mentions']) && !empty($_POST['mentions'])) {
                $mentions = explode(',', $_POST['mentions']);
                $stmt = $pdo->prepare("INSERT INTO recognition_mentions (post_id, user_id) VALUES (?, ?)");
                foreach ($mentions as $mentioned_id) {
                    if (!empty($mentioned_id)) {
                        $stmt->execute([$post_id, $mentioned_id]);
                    }
                }
            }
            
            if ($is_approved) {
                $success_message = "Recognition posted successfully!";
            } else {
                $info_message = "Recognition submitted for approval. It will appear once approved.";
            }
            
            logActivity($pdo, $user_id, 'create_recognition_post', "Created recognition post #$post_number for employee #$employee_id");
        }
    }
    
    // Like a post
    if (isset($_POST['like_post'])) {
        $post_id = $_POST['post_id'];
        
        // Check if already liked
        $stmt = $pdo->prepare("SELECT id FROM recognition_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user_id]);
        
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO recognition_likes (post_id, user_id) VALUES (?, ?)");
            $stmt->execute([$post_id, $user_id]);
            
            // Update like count
            $pdo->prepare("UPDATE recognition_posts SET like_count = like_count + 1 WHERE id = ?")
               ->execute([$post_id]);
        }
    }
    
    // Unlike a post
    if (isset($_POST['unlike_post'])) {
        $post_id = $_POST['post_id'];
        
        $stmt = $pdo->prepare("DELETE FROM recognition_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user_id]);
        
        // Update like count
        $pdo->prepare("UPDATE recognition_posts SET like_count = like_count - 1 WHERE id = ?")
           ->execute([$post_id]);
    }
    
    // Add comment
    if (isset($_POST['add_comment'])) {
        $post_id = $_POST['post_id'];
        $comment = $_POST['comment'];
        
        $is_approved = ($settings['moderate_comments'] == '1') ? 0 : 1;
        
        $stmt = $pdo->prepare("
            INSERT INTO recognition_comments (post_id, user_id, comment, is_approved)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$post_id, $user_id, $comment, $is_approved]);
        
        // Update comment count
        $pdo->prepare("UPDATE recognition_posts SET comment_count = comment_count + 1 WHERE id = ?")
           ->execute([$post_id]);
        
        if ($is_approved) {
            $success_message = "Comment added successfully!";
        } else {
            $info_message = "Comment submitted for moderation.";
        }
    }
    
    // Delete comment (HR only)
    if (isset($_POST['delete_comment']) && $is_hr) {
        $comment_id = $_POST['comment_id'];
        $post_id = $_POST['post_id'];
        
        $stmt = $pdo->prepare("DELETE FROM recognition_comments WHERE id = ?");
        $stmt->execute([$comment_id]);
        
        // Update comment count
        $pdo->prepare("UPDATE recognition_posts SET comment_count = comment_count - 1 WHERE id = ?")
           ->execute([$post_id]);
    }
    
    // Approve post (HR only)
    if (isset($_POST['approve_post']) && $is_hr) {
        $post_id = $_POST['post_id'];
        
        $pdo->prepare("
            UPDATE recognition_posts 
            SET is_approved = 1, approved_by = ?, approved_at = NOW() 
            WHERE id = ?
        ")->execute([$user_id, $post_id]);
        
        $success_message = "Post approved successfully!";
    }
    
    // Pin/Unpin post (HR only)
    if (isset($_POST['pin_post']) && $is_hr) {
        $post_id = $_POST['post_id'];
        $is_pinned = $_POST['is_pinned'] == '1' ? 0 : 1;
        
        // Unpin other posts if pinning this one
        if ($is_pinned == 1) {
            $pdo->exec("UPDATE recognition_posts SET is_pinned = 0 WHERE is_pinned = 1");
        }
        
        $pdo->prepare("UPDATE recognition_posts SET is_pinned = ? WHERE id = ?")
           ->execute([$is_pinned, $post_id]);
        
        $success_message = $is_pinned ? "Post pinned successfully!" : "Post unpinned successfully!";
    }
    
    // Delete post (HR only)
    if (isset($_POST['delete_post']) && $is_hr) {
        $post_id = $_POST['post_id'];
        
        // Delete related records
        $pdo->prepare("DELETE FROM recognition_likes WHERE post_id = ?")->execute([$post_id]);
        $pdo->prepare("DELETE FROM recognition_comments WHERE post_id = ?")->execute([$post_id]);
        $pdo->prepare("DELETE FROM recognition_mentions WHERE post_id = ?")->execute([$post_id]);
        $pdo->prepare("DELETE FROM recognition_posts WHERE id = ?")->execute([$post_id]);
        
        $success_message = "Post deleted successfully!";
    }
}

// Get filter parameters
$type_filter = $_GET['type'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$department_filter = $_GET['department'] ?? 'all';
$time_filter = $_GET['time'] ?? 'all';
$search_filter = $_GET['search'] ?? '';

// Build query for posts
$query = "
    SELECT 
        p.*,
        nh.position,
        nh.department,
        nh.applicant_id,
        a.first_name,
        a.last_name,
        a.photo_path,
        u.full_name as poster_name,
        u.role as poster_user_role,
        (SELECT COUNT(*) FROM recognition_likes WHERE post_id = p.id) as actual_likes,
        (SELECT COUNT(*) FROM recognition_comments WHERE post_id = p.id) as actual_comments,
        (SELECT id FROM recognition_likes WHERE post_id = p.id AND user_id = ?) as user_liked
    FROM recognition_posts p
    LEFT JOIN new_hires nh ON p.employee_id = nh.id
    LEFT JOIN job_applications a ON nh.applicant_id = a.id
    LEFT JOIN users u ON p.posted_by = u.id
    WHERE p.is_approved = 1
";

$params = [$user_id];

// Type filter
if ($type_filter !== 'all') {
    $query .= " AND p.recognition_type = ?";
    $params[] = $type_filter;
}

// Category filter
if ($category_filter !== 'all') {
    $query .= " AND p.category = ?";
    $params[] = $category_filter;
}

// Department filter
if ($department_filter !== 'all') {
    $query .= " AND nh.department = ?";
    $params[] = $department_filter;
}

// Time filter
if ($time_filter === 'today') {
    $query .= " AND DATE(p.created_at) = CURDATE()";
} elseif ($time_filter === 'week') {
    $query .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($time_filter === 'month') {
    $query .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// Search filter
if (!empty($search_filter)) {
    $query .= " AND (p.title LIKE ? OR p.description LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ?)";
    $search_term = "%$search_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Order by (pinned first, then newest)
$query .= " ORDER BY p.is_pinned DESC, p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Get pending approvals (HR only)
$pending_posts = [];
if ($is_hr) {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            nh.position,
            nh.department,
            a.first_name,
            a.last_name,
            u.full_name as poster_name
        FROM recognition_posts p
        LEFT JOIN new_hires nh ON p.employee_id = nh.id
        LEFT JOIN job_applications a ON nh.applicant_id = a.id
        LEFT JOIN users u ON p.posted_by = u.id
        WHERE p.is_approved = 0
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $pending_posts = $stmt->fetchAll();
}

// Get employees for post creation (supervisors see their team, HR sees all)
$employees = [];
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

// Get users for mentions
$users = [];
if ($settings['allow_peer_recognition'] == '1') {
    $stmt = $pdo->query("
        SELECT id, full_name FROM users 
        WHERE role IN ('admin', 'manager', 'dispatcher', 'driver')
        ORDER BY full_name
    ");
    $users = $stmt->fetchAll();
}

// Get statistics
$stats = [];

// Total posts this month
$stmt = $pdo->query("
    SELECT COUNT(*) FROM recognition_posts 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stats['monthly_posts'] = $stmt->fetchColumn();

// Total likes this month
$stmt = $pdo->query("
    SELECT COUNT(*) FROM recognition_likes l
    INNER JOIN recognition_posts p ON l.post_id = p.id
    WHERE MONTH(l.created_at) = MONTH(CURRENT_DATE())
    AND YEAR(l.created_at) = YEAR(CURRENT_DATE())
");
$stats['monthly_likes'] = $stmt->fetchColumn();

// Total comments this month
$stmt = $pdo->query("
    SELECT COUNT(*) FROM recognition_comments c
    INNER JOIN recognition_posts p ON c.post_id = p.id
    WHERE MONTH(c.created_at) = MONTH(CURRENT_DATE())
    AND YEAR(c.created_at) = YEAR(CURRENT_DATE())
");
$stats['monthly_comments'] = $stmt->fetchColumn();

// Most recognized employee
$stmt = $pdo->query("
    SELECT 
        p.employee_id,
        a.first_name,
        a.last_name,
        COUNT(*) as recognition_count
    FROM recognition_posts p
    LEFT JOIN new_hires nh ON p.employee_id = nh.id
    LEFT JOIN job_applications a ON nh.applicant_id = a.id
    WHERE p.is_approved = 1
    GROUP BY p.employee_id
    ORDER BY recognition_count DESC
    LIMIT 1
");
$most_recognized = $stmt->fetch();

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

// Helper function to get recognition badge style
function getRecognitionBadge($type, $category = null) {
    $badges = [
        'employee_month' => ['bg' => 'gold', 'icon' => 'fa-crown', 'text' => 'Employee of the Month'],
        'supervisor' => ['bg' => 'primary', 'icon' => 'fa-user-tie', 'text' => 'Supervisor Recognition'],
        'peer' => ['bg' => 'danger', 'icon' => 'fa-heart', 'text' => 'Peer Recognition'],
        'system' => ['bg' => 'info', 'icon' => 'fa-robot', 'text' => 'System Recognition'],
        'safety' => ['bg' => 'success', 'icon' => 'fa-shield-alt', 'text' => 'Safety Award'],
        'milestone' => ['bg' => 'warning', 'icon' => 'fa-trophy', 'text' => 'Milestone']
    ];
    
    return $badges[$type] ?? ['bg' => 'secondary', 'icon' => 'fa-award', 'text' => 'Recognition'];
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
    content: '📰';
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

.create-post-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 12px 25px;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 500;
    border: 2px solid rgba(255, 255, 255, 0.3);
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 10px;
}

.create-post-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
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

.stat-sub {
    font-size: 12px;
    color: #64748b;
    margin-top: 5px;
}

/* Main Layout */
.feed-layout {
    display: grid;
    grid-template-columns: 280px 1fr 300px;
    gap: 20px;
}

/* Sidebar Filters */
.filters-sidebar {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    height: fit-content;
    position: sticky;
    top: 20px;
}

.filter-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f6;
}

.filter-title i {
    color: var(--primary-color);
}

.filter-group {
    margin-bottom: 20px;
}

.filter-group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px var(--primary-transparent);
}

.filter-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.filter-actions a,
.filter-actions button {
    flex: 1;
    text-align: center;
    padding: 10px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
}

/* Feed Container */
.feed-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

/* Pinned Post */
.pinned-post {
    background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
    border: 2px solid var(--gold);
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 20px;
    position: relative;
}

.pinned-badge {
    position: absolute;
    top: -10px;
    left: 20px;
    background: var(--gold);
    color: #2c3e50;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 5px 10px rgba(255, 215, 0, 0.3);
}

/* Recognition Card */
.recognition-card {
    background: white;
    border: 1px solid #eef2f6;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    position: relative;
}

.recognition-card:hover {
    box-shadow: 0 10px 30px rgba(14, 76, 146, 0.1);
    transform: translateY(-2px);
}

.recognition-card.pending {
    opacity: 0.7;
    background: #f8fafd;
    border: 2px dashed var(--warning-color);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.employee-photo {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    object-fit: cover;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 18px;
    flex-shrink: 0;
}

.employee-info {
    flex: 1;
}

.employee-name {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 3px;
}

.employee-name a {
    color: #2c3e50;
    text-decoration: none;
}

.employee-name a:hover {
    color: var(--primary-color);
}

.employee-details {
    font-size: 13px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.employee-details i {
    color: var(--primary-color);
    width: 16px;
}

.recognition-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.badge-gold {
    background: var(--gold);
    color: #2c3e50;
}

.badge-primary {
    background: var(--primary-transparent);
    color: var(--primary-color);
}

.badge-danger {
    background: #e74c3c20;
    color: #e74c3c;
}

.badge-success {
    background: #27ae6020;
    color: #27ae60;
}

.badge-info {
    background: #3498db20;
    color: #3498db;
}

.badge-warning {
    background: #f39c1220;
    color: #f39c12;
}

.badge-purple {
    background: #9b59b620;
    color: #9b59b6;
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
    margin: 10px 0 5px;
}

.card-description {
    font-size: 15px;
    line-height: 1.6;
    color: #2c3e50;
    margin-bottom: 15px;
}

.card-metrics {
    background: #f8fafd;
    border-radius: 12px;
    padding: 15px;
    margin: 15px 0;
    font-size: 14px;
    border-left: 3px solid var(--primary-color);
}

.card-attachment {
    margin: 15px 0;
    padding: 10px;
    background: #f8fafd;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.card-attachment img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 10px;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eef2f6;
}

.post-meta {
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 12px;
    color: #64748b;
}

.post-meta i {
    color: var(--primary-color);
}

.post-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-btn {
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
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
}

.action-btn:hover {
    background: var(--primary-transparent);
    color: var(--primary-color);
}

.action-btn.liked {
    background: #e74c3c20;
    color: #e74c3c;
}

.action-btn.liked i {
    color: #e74c3c;
}

/* Comments Section */
.comments-section {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eef2f6;
}

.comment {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.comment-photo {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    object-fit: cover;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}

.comment-content {
    flex: 1;
    background: #f8fafd;
    border-radius: 12px;
    padding: 10px 15px;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.comment-author {
    font-weight: 600;
    font-size: 13px;
    color: #2c3e50;
}

.comment-time {
    font-size: 11px;
    color: #64748b;
}

.comment-text {
    font-size: 13px;
    color: #2c3e50;
    line-height: 1.5;
}

.comment-actions {
    display: flex;
    gap: 10px;
    margin-top: 5px;
}

.comment-actions button {
    background: none;
    border: none;
    font-size: 11px;
    color: #64748b;
    cursor: pointer;
    padding: 2px 5px;
}

.comment-actions button:hover {
    color: var(--danger-color);
}

.add-comment {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.add-comment input {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    font-size: 13px;
}

.add-comment input:focus {
    outline: none;
    border-color: var(--primary-color);
}

.add-comment button {
    padding: 10px 20px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 20px;
    font-size: 13px;
    cursor: pointer;
}

.add-comment button:hover {
    background: var(--primary-light);
}

/* Right Sidebar */
.right-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.widget {
    background: white;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.widget-title {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef2f6;
}

.widget-title i {
    color: var(--primary-color);
}

/* Categories List */
.category-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.category-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #eef2f6;
}

.category-item:last-child {
    border-bottom: none;
}

.category-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.category-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
}

.category-count {
    background: #f8fafd;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    color: #64748b;
}

/* Top Recognized */
.top-employee {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #eef2f6;
}

.top-employee:last-child {
    border-bottom: none;
}

.top-employee-photo {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    object-fit: cover;
    background: linear-gradient(135deg, var(--gold) 0%, #FDB931 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}

.top-employee-info {
    flex: 1;
}

.top-employee-name {
    font-weight: 600;
    font-size: 14px;
    color: #2c3e50;
    margin-bottom: 2px;
}

.top-employee-count {
    font-size: 12px;
    color: var(--gold);
    font-weight: 600;
}

/* Pending Approvals */
.pending-item {
    padding: 10px;
    background: #f8fafd;
    border-radius: 10px;
    margin-bottom: 10px;
}

.pending-item:last-child {
    margin-bottom: 0;
}

.pending-title {
    font-weight: 600;
    font-size: 13px;
    color: #2c3e50;
    margin-bottom: 5px;
}

.pending-meta {
    font-size: 11px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.pending-actions {
    display: flex;
    gap: 8px;
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

/* Form Styles */
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

/* Image error handling */
.img-error-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
    color: white;
    font-weight: 600;
}

.img-error-fallback.employee-photo {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    font-size: 18px;
}

.img-error-fallback.comment-photo {
    width: 35px;
    height: 35px;
    border-radius: 10px;
    font-size: 14px;
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

/* Load More */
.load-more {
    text-align: center;
    padding: 20px;
}

.load-more button {
    background: #f8fafd;
    border: 1px solid #e2e8f0;
    padding: 10px 30px;
    border-radius: 30px;
    color: var(--primary-color);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.load-more button:hover {
    background: var(--primary-color);
    color: white;
}

/* Responsive */
@media (max-width: 1200px) {
    .feed-layout {
        grid-template-columns: 250px 1fr 250px;
    }
}

@media (max-width: 992px) {
    .feed-layout {
        grid-template-columns: 1fr;
    }
    
    .filters-sidebar,
    .right-sidebar {
        display: none;
    }
}

@media (max-width: 768px) {
    .page-header-content {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .card-header {
        flex-direction: column;
        text-align: center;
    }
    
    .employee-photo {
        margin: 0 auto;
    }
    
    .card-footer {
        flex-direction: column;
        gap: 10px;
    }
    
    .post-actions {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .form-row {
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

function toggleComments(postId) {
    const comments = document.getElementById('comments-' + postId);
    if (comments.style.display === 'none' || !comments.style.display) {
        comments.style.display = 'block';
    } else {
        comments.style.display = 'none';
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}

// File upload preview
function previewFile(input) {
    const preview = document.getElementById('attachment-preview');
    const file = input.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 100px; max-height: 100px; border-radius: 10px;">';
        }
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
}
</script>

<!-- Page Header -->
<div class="page-header-unique">
    <div class="page-header-content">
        <div class="page-title">
            <i class="fas fa-newspaper"></i>
            <div>
                <h1><?php echo $page_title; ?></h1>
                <p style="margin: 5px 0 0; opacity: 0.9;">Celebrating achievements and recognizing excellence</p>
            </div>
        </div>
        
        <?php if ($is_supervisor || $settings['allow_peer_recognition'] == '1'): ?>
        <button class="create-post-btn" onclick="openModal('createPostModal')">
            <i class="fas fa-plus-circle"></i>
            <span>Create Recognition</span>
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

<?php if (isset($info_message)): ?>
<div class="alert-info">
    <i class="fas fa-info-circle"></i> <?php echo $info_message; ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid-unique">
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">This Month</span>
            <span class="stat-value"><?php echo $stats['monthly_posts']; ?></span>
            <span class="stat-sub">recognitions</span>
        </div>
    </div>
    
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-heart"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Likes</span>
            <span class="stat-value"><?php echo $stats['monthly_likes']; ?></span>
            <span class="stat-sub">this month</span>
        </div>
    </div>
    
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-comment"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Comments</span>
            <span class="stat-value"><?php echo $stats['monthly_comments']; ?></span>
            <span class="stat-sub">this month</span>
        </div>
    </div>
    
    <div class="stat-card-unique">
        <div class="stat-icon-3d">
            <i class="fas fa-trophy"></i>
        </div>
        <div class="stat-content">
            <span class="stat-label">Top Recognized</span>
            <span class="stat-value">
                <?php if ($most_recognized): ?>
                <?php echo htmlspecialchars(substr($most_recognized['first_name'] ?? '', 0, 1) . '. ' . ($most_recognized['last_name'] ?? '')); ?>
                <?php else: ?>
                —
                <?php endif; ?>
            </span>
            <span class="stat-sub"><?php echo $most_recognized['recognition_count'] ?? 0; ?> recognitions</span>
        </div>
    </div>
</div>

<!-- Main Feed Layout -->
<div class="feed-layout">
    <!-- Left Sidebar - Filters -->
    <div class="filters-sidebar">
        <div class="filter-title">
            <i class="fas fa-filter"></i> Filters
        </div>
        
        <form method="GET" id="filter-form">
            <input type="hidden" name="page" value="recognition">
            <input type="hidden" name="subpage" value="recognition-feed">
            
            <div class="filter-group">
                <label>Recognition Type</label>
                <select name="type" onchange="this.form.submit()">
                    <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="employee_month" <?php echo $type_filter == 'employee_month' ? 'selected' : ''; ?>>🏆 Employee of the Month</option>
                    <option value="supervisor" <?php echo $type_filter == 'supervisor' ? 'selected' : ''; ?>>👔 Supervisor Recognition</option>
                    <option value="peer" <?php echo $type_filter == 'peer' ? 'selected' : ''; ?>>❤️ Peer Recognition</option>
                    <option value="system" <?php echo $type_filter == 'system' ? 'selected' : ''; ?>>🤖 System Recognition</option>
                    <option value="safety" <?php echo $type_filter == 'safety' ? 'selected' : ''; ?>>🛡️ Safety Awards</option>
                    <option value="milestone" <?php echo $type_filter == 'milestone' ? 'selected' : ''; ?>>🎯 Milestones</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Category</label>
                <select name="category" onchange="this.form.submit()">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['category_name']; ?>" <?php echo $category_filter == $cat['category_name'] ? 'selected' : ''; ?>>
                        <?php echo $cat['category_name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Department</label>
                <select name="department" onchange="this.form.submit()">
                    <option value="all">All Departments</option>
                    <option value="driver" <?php echo $department_filter == 'driver' ? 'selected' : ''; ?>>Driver</option>
                    <option value="warehouse" <?php echo $department_filter == 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                    <option value="logistics" <?php echo $department_filter == 'logistics' ? 'selected' : ''; ?>>Logistics</option>
                    <option value="admin" <?php echo $department_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="management" <?php echo $department_filter == 'management' ? 'selected' : ''; ?>>Management</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Time Period</label>
                <select name="time" onchange="this.form.submit()">
                    <option value="all" <?php echo $time_filter == 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $time_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $time_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo $time_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Search posts..." value="<?php echo htmlspecialchars($search_filter); ?>">
            </div>
            
            <div class="filter-actions">
                <a href="?page=recognition&subpage=recognition-feed" class="btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i> Apply
                </button>
            </div>
        </form>
        
        <!-- Quick Categories -->
        <div style="margin-top: 20px;">
            <div class="filter-title">
                <i class="fas fa-tags"></i> Categories
            </div>
            
            <ul class="category-list">
                <?php foreach ($categories as $cat): ?>
                <li class="category-item">
                    <div class="category-info">
                        <div class="category-icon" style="background: <?php 
                            $colors = ['primary' => '#0e4c92', 'success' => '#27ae60', 'danger' => '#e74c3c', 'warning' => '#f39c12', 'info' => '#3498db', 'purple' => '#9b59b6'];
                            echo $colors[$cat['badge_color']] ?? '#0e4c92';
                        ?>">
                            <i class="fas <?php echo $cat['icon'] ?? 'fa-tag'; ?>"></i>
                        </div>
                        <span><?php echo $cat['category_name']; ?></span>
                    </div>
                    <span class="category-count"><?php 
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM recognition_posts WHERE category = ? AND is_approved = 1");
                        $stmt->execute([$cat['category_name']]);
                        echo $stmt->fetchColumn();
                    ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    
    <!-- Main Feed -->
    <div class="feed-container">
        <?php if ($is_hr && !empty($pending_posts)): ?>
        <div style="margin-bottom: 20px;">
            <div class="filter-title" style="color: var(--warning-color);">
                <i class="fas fa-clock"></i> Pending Approval (<?php echo count($pending_posts); ?>)
            </div>
            
            <?php foreach ($pending_posts as $post): 
                $fullName = getEmployeeFullName($post);
                $badge = getRecognitionBadge($post['recognition_type']);
            ?>
            <div class="recognition-card pending">
                <div class="card-header">
                    <div class="employee-photo img-error-fallback">
                        <?php echo getEmployeeInitials($post); ?>
                    </div>
                    <div class="employee-info">
                        <div class="employee-name"><?php echo htmlspecialchars($fullName); ?></div>
                        <div class="employee-details">
                            <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($post['position'] ?? 'Employee'); ?></span>
                            <span><i class="fas fa-building"></i> <?php echo ucfirst($post['department'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <span class="recognition-badge badge-<?php echo $badge['bg']; ?>">
                        <i class="fas <?php echo $badge['icon']; ?>"></i> Pending
                    </span>
                </div>
                
                <div class="card-title"><?php echo htmlspecialchars($post['title']); ?></div>
                <div class="card-description"><?php echo nl2br(htmlspecialchars($post['description'])); ?></div>
                
                <div class="card-footer">
                    <div class="post-meta">
                        <span><i class="fas fa-user"></i> by <?php echo htmlspecialchars($post['poster_name'] ?? 'System'); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo timeAgo($post['created_at']); ?></span>
                    </div>
                    
                    <div class="post-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <button type="submit" name="approve_post" class="btn-success btn-sm">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this post?');">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <button type="submit" name="delete_post" class="btn-danger btn-sm">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Feed Posts -->
        <?php if (empty($posts)): ?>
        <div style="text-align: center; padding: 60px 20px;">
            <i class="fas fa-newspaper" style="font-size: 60px; color: #ddd; margin-bottom: 20px;"></i>
            <h3 style="color: #64748b; margin-bottom: 10px;">No Recognitions Found</h3>
            <p style="color: #94a3b8; margin-bottom: 20px;">No recognition posts match your filters.</p>
            <?php if ($is_supervisor || $settings['allow_peer_recognition'] == '1'): ?>
            <button onclick="openModal('createPostModal')" class="btn-primary">
                <i class="fas fa-plus-circle"></i> Create First Recognition
            </button>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <?php foreach ($posts as $post): 
                $fullName = getEmployeeFullName($post);
                $initials = getEmployeeInitials($post);
                $photoPath = !empty($post['photo_path']) && file_exists($post['photo_path']) ? $post['photo_path'] : null;
                $badge = getRecognitionBadge($post['recognition_type']);
                $userLiked = $post['user_liked'] ? true : false;
                
                // Get comments for this post
                $stmt = $pdo->prepare("
                    SELECT c.*, u.full_name, u.id as user_id
                    FROM recognition_comments c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.post_id = ? AND c.is_approved = 1
                    ORDER BY c.created_at ASC
                ");
                $stmt->execute([$post['id']]);
                $comments = $stmt->fetchAll();
            ?>
            
            <!-- Pinned Indicator -->
            <?php if ($post['is_pinned']): ?>
            <div class="pinned-post">
                <div class="pinned-badge">
                    <i class="fas fa-thumbtack"></i> Pinned Recognition
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recognition Card -->
            <div class="recognition-card" id="post-<?php echo $post['id']; ?>">
                <div class="card-header">
                    <?php if ($photoPath): ?>
                        <img src="<?php echo $photoPath; ?>" 
                             alt="<?php echo htmlspecialchars($fullName); ?>"
                             class="employee-photo"
                             onerror="handleImageError(this)"
                             data-initials="<?php echo $initials; ?>"
                             loading="lazy">
                    <?php else: ?>
                        <div class="employee-photo img-error-fallback">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="employee-info">
                        <div class="employee-name">
                            <a href="?page=employee&subpage=profile&id=<?php echo $post['employee_id']; ?>">
                                <?php echo htmlspecialchars($fullName); ?>
                            </a>
                        </div>
                        <div class="employee-details">
                            <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($post['position'] ?? 'Employee'); ?></span>
                            <span><i class="fas fa-building"></i> <?php echo ucfirst($post['department'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    
                    <span class="recognition-badge badge-<?php echo $badge['bg']; ?>">
                        <i class="fas <?php echo $badge['icon']; ?>"></i> <?php echo $badge['text']; ?>
                    </span>
                </div>
                
                <div class="card-title"><?php echo htmlspecialchars($post['title']); ?></div>
                
                <div class="card-description">
                    <?php echo nl2br(htmlspecialchars($post['description'])); ?>
                </div>
                
                <?php if (!empty($post['achievement_details'])): ?>
                <div class="card-metrics">
                    <i class="fas fa-chart-line" style="color: var(--primary-color); margin-right: 8px;"></i>
                    <?php echo nl2br(htmlspecialchars($post['achievement_details'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($post['attachment_path']) && file_exists($post['attachment_path'])): ?>
                <div class="card-attachment">
                    <?php 
                    $ext = strtolower(pathinfo($post['attachment_path'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <img src="<?php echo $post['attachment_path']; ?>" alt="Attachment" style="max-width: 100%; border-radius: 10px;">
                    <?php else: ?>
                        <i class="fas fa-file-pdf" style="color: #e74c3c; font-size: 24px;"></i>
                        <a href="<?php echo $post['attachment_path']; ?>" target="_blank">View Attachment</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="card-footer">
                    <div class="post-meta">
                        <span><i class="fas fa-user-tie"></i> by <?php echo htmlspecialchars($post['poster_name'] ?? 'System'); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo timeAgo($post['created_at']); ?></span>
                        <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($post['category'] ?? 'General'); ?></span>
                    </div>
                    
                    <div class="post-actions">
                        <!-- Like Button -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <?php if ($userLiked): ?>
                            <button type="submit" name="unlike_post" class="action-btn liked">
                                <i class="fas fa-heart"></i> <?php echo $post['actual_likes']; ?>
                            </button>
                            <?php else: ?>
                            <button type="submit" name="like_post" class="action-btn">
                                <i class="far fa-heart"></i> <?php echo $post['actual_likes']; ?>
                            </button>
                            <?php endif; ?>
                        </form>
                        
                        <!-- Comment Button -->
                        <button class="action-btn" onclick="toggleComments(<?php echo $post['id']; ?>)">
                            <i class="far fa-comment"></i> <?php echo $post['actual_comments']; ?>
                        </button>
                        
                        <?php if ($is_hr): ?>
                        <!-- Pin Button -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <input type="hidden" name="is_pinned" value="<?php echo $post['is_pinned']; ?>">
                            <button type="submit" name="pin_post" class="action-btn" title="<?php echo $post['is_pinned'] ? 'Unpin' : 'Pin'; ?>">
                                <i class="fas fa-thumbtack" style="color: <?php echo $post['is_pinned'] ? 'var(--gold)' : ''; ?>"></i>
                            </button>
                        </form>
                        
                        <!-- Delete Button -->
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this post?');">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <button type="submit" name="delete_post" class="action-btn" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Comments Section -->
                <div id="comments-<?php echo $post['id']; ?>" class="comments-section" style="display: none;">
                    <?php foreach ($comments as $comment): 
                        $commentInitials = strtoupper(substr($comment['full_name'] ?? '', 0, 1) ?: '?');
                    ?>
                    <div class="comment">
                        <div class="comment-photo img-error-fallback">
                            <?php echo $commentInitials; ?>
                        </div>
                        <div class="comment-content">
                            <div class="comment-header">
                                <span class="comment-author"><?php echo htmlspecialchars($comment['full_name'] ?? 'Unknown'); ?></span>
                                <span class="comment-time"><?php echo timeAgo($comment['created_at']); ?></span>
                            </div>
                            <div class="comment-text">
                                <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                            </div>
                            <?php if ($is_hr || $comment['user_id'] == $user_id): ?>
                            <div class="comment-actions">
                                <form method="POST" onsubmit="return confirm('Delete this comment?');">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" name="delete_comment">Delete</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($settings['allow_comments'] == '1'): ?>
                    <form method="POST" class="add-comment">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <input type="text" name="comment" placeholder="Write a comment..." required>
                        <button type="submit" name="add_comment">Post</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Load More (pagination would go here) -->
            <div class="load-more">
                <button>Load More</button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Sidebar -->
    <div class="right-sidebar">
        <!-- Top Recognized Widget -->
        <div class="widget">
            <div class="widget-title">
                <i class="fas fa-trophy" style="color: var(--gold);"></i> Top Recognized
            </div>
            
            <?php
            $stmt = $pdo->query("
                SELECT 
                    p.employee_id,
                    COUNT(*) as recognition_count,
                    a.first_name,
                    a.last_name,
                    a.photo_path,
                    nh.position,
                    nh.department
                FROM recognition_posts p
                LEFT JOIN new_hires nh ON p.employee_id = nh.id
                LEFT JOIN job_applications a ON nh.applicant_id = a.id
                WHERE p.is_approved = 1
                GROUP BY p.employee_id
                ORDER BY recognition_count DESC
                LIMIT 5
            ");
            $top_employees = $stmt->fetchAll();
            ?>
            
            <?php if (!empty($top_employees)): ?>
                <?php foreach ($top_employees as $top): 
                    $topName = getEmployeeFullName($top);
                    $topInitials = getEmployeeInitials($top);
                    $topPhoto = !empty($top['photo_path']) && file_exists($top['photo_path']) ? $top['photo_path'] : null;
                ?>
                <div class="top-employee">
                    <?php if ($topPhoto): ?>
                        <img src="<?php echo $topPhoto; ?>" 
                             alt="<?php echo htmlspecialchars($topName); ?>"
                             class="top-employee-photo"
                             onerror="handleImageError(this)"
                             data-initials="<?php echo $topInitials; ?>"
                             loading="lazy">
                    <?php else: ?>
                        <div class="top-employee-photo img-error-fallback">
                            <?php echo $topInitials; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="top-employee-info">
                        <div class="top-employee-name"><?php echo htmlspecialchars($topName); ?></div>
                        <div class="top-employee-count">
                            <i class="fas fa-award"></i> <?php echo $top['recognition_count']; ?> recognitions
                        </div>
                        <div style="font-size: 11px; color: #64748b;">
                            <?php echo ucfirst($top['department'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #64748b; text-align: center;">No recognitions yet</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Activity Widget -->
        <div class="widget">
            <div class="widget-title">
                <i class="fas fa-history"></i> Recent Activity
            </div>
            
            <?php
            $stmt = $pdo->query("
                SELECT 
                    'like' as activity_type,
                    l.created_at,
                    u.full_name as user_name,
                    p.employee_id,
                    a.first_name,
                    a.last_name
                FROM recognition_likes l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN recognition_posts p ON l.post_id = p.id
                LEFT JOIN new_hires nh ON p.employee_id = nh.id
                LEFT JOIN job_applications a ON nh.applicant_id = a.id
                WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY l.created_at DESC
                LIMIT 5
            ");
            $activities = $stmt->fetchAll();
            ?>
            
            <?php if (!empty($activities)): ?>
                <?php foreach ($activities as $activity): ?>
                <div style="padding: 8px 0; border-bottom: 1px solid #eef2f6; font-size: 12px;">
                    <i class="fas fa-heart" style="color: #e74c3c;"></i>
                    <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'Someone'); ?></strong>
                    liked <?php echo htmlspecialchars($activity['first_name'] ?? ''); ?>
                    <div style="color: #64748b; font-size: 10px; margin-top: 2px;">
                        <?php echo timeAgo($activity['created_at']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #64748b; text-align: center;">No recent activity</p>
            <?php endif; ?>
        </div>
        
        <!-- Recognition Stats Widget -->
        <div class="widget">
            <div class="widget-title">
                <i class="fas fa-chart-pie"></i> This Month
            </div>
            
            <div style="margin-bottom: 10px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 13px;">Total Recognitions</span>
                    <span style="font-weight: 600;"><?php echo $stats['monthly_posts']; ?></span>
                </div>
                <div style="height: 5px; background: #eef2f6; border-radius: 5px;">
                    <div style="width: 100%; height: 100%; background: var(--primary-color); border-radius: 5px;"></div>
                </div>
            </div>
            
            <div style="margin-bottom: 10px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 13px;">Engagement Rate</span>
                    <span style="font-weight: 600;">
                        <?php 
                        $engagement = $stats['monthly_posts'] > 0 
                            ? round(($stats['monthly_likes'] + $stats['monthly_comments']) / $stats['monthly_posts'], 1)
                            : 0;
                        echo $engagement;
                        ?>
                    </span>
                </div>
                <div style="height: 5px; background: #eef2f6; border-radius: 5px;">
                    <?php $engagement_percent = min(($engagement / 10) * 100, 100); ?>
                    <div style="width: <?php echo $engagement_percent; ?>%; height: 100%; background: var(--success-color); border-radius: 5px;"></div>
                </div>
            </div>
            
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 13px;">Departments Active</span>
                    <span style="font-weight: 600;">
                        <?php
                        $stmt = $pdo->query("
                            SELECT COUNT(DISTINCT nh.department) 
                            FROM recognition_posts p
                            LEFT JOIN new_hires nh ON p.employee_id = nh.id
                            WHERE MONTH(p.created_at) = MONTH(CURRENT_DATE())
                            AND YEAR(p.created_at) = YEAR(CURRENT_DATE())
                        ");
                        echo $stmt->fetchColumn();
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Post Modal -->
<div id="createPostModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-plus-circle" style="color: var(--gold);"></i>
                Create Recognition Post
            </h3>
            <button class="modal-close" onclick="closeModal('createPostModal')">&times;</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
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
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Recognition Type</label>
                    <select name="recognition_type" required id="recognitionType" onchange="toggleCategoryField()">
                        <option value="supervisor">👔 Supervisor Recognition</option>
                        <?php if ($settings['allow_peer_recognition'] == '1'): ?>
                        <option value="peer">❤️ Peer Recognition</option>
                        <?php endif; ?>
                        <option value="safety">🛡️ Safety Award</option>
                        <option value="milestone">🎯 Milestone</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-layer-group"></i> Category</label>
                    <select name="category" required id="categorySelect">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_name']; ?>">
                            <?php echo $cat['category_name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-heading"></i> Title</label>
                <input type="text" name="title" placeholder="e.g., Excellence in Safety" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Description</label>
                <textarea name="description" placeholder="Describe the achievement..." required></textarea>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-chart-bar"></i> Achievement Details (Optional)</label>
                <textarea name="achievement_details" placeholder="e.g., 100% on-time delivery, Zero incidents, etc."></textarea>
            </div>
            
            <?php if ($settings['allow_image_attachments'] == '1'): ?>
            <div class="form-group">
                <label><i class="fas fa-paperclip"></i> Attachment (Optional)</label>
                <input type="file" name="attachment" onchange="previewFile(this)" accept="image/*,.pdf">
                <div class="form-hint">Max size: <?php echo $settings['max_attachments_size'] ?? 5; ?>MB. Allowed: Images, PDF</div>
                <div id="attachment-preview" style="margin-top: 10px;"></div>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label><i class="fas fa-eye"></i> Visibility</label>
                <select name="visibility">
                    <option value="company">Company-wide</option>
                    <option value="department">Department Only</option>
                    <option value="managers">Managers Only</option>
                </select>
            </div>
            
            <?php if ($settings['allow_peer_recognition'] == '1'): ?>
            <div class="form-group" id="mentionsField" style="display: none;">
                <label><i class="fas fa-at"></i> Mention Colleagues (Optional)</label>
                <select name="mentions[]" multiple style="height: 100px;">
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Hold Ctrl to select multiple</div>
            </div>
            <?php endif; ?>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeModal('createPostModal')" class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" name="create_post" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Post Recognition
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCategoryField() {
    const type = document.getElementById('recognitionType').value;
    const mentions = document.getElementById('mentionsField');
    
    if (type === 'peer' && mentions) {
        mentions.style.display = 'block';
    } else if (mentions) {
        mentions.style.display = 'none';
    }
}
</script>