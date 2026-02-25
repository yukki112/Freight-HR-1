<?php
// api/create_applicant.php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

try {
    $applicant_number = generateApplicantNumber();
    
    $sql = "INSERT INTO applicants (
        applicant_number, first_name, last_name, email, phone,
        position_applied, department_applied, application_date, source, notes, status
    ) VALUES (
        :applicant_number, :first_name, :last_name, :email, :phone,
        :position_applied, :department_applied, CURDATE(), :source, :notes, 'new'
    )";
    
    $stmt = $pdo->prepare($sql);
    
    $params = [
        'applicant_number' => $applicant_number,
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name'],
        'email' => $_POST['email'],
        'phone' => $_POST['phone'] ?? null,
        'position_applied' => $_POST['position_applied'],
        'department_applied' => $_POST['department_applied'] ?? null,
        'source' => $_POST['source'] ?? 'website',
        'notes' => $_POST['notes'] ?? null
    ];
    
    $stmt->execute($params);
    
    $applicant_id = $pdo->lastInsertId();
    
    // Create notification for HR staff
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type) 
        SELECT id, 'New Applicant', ?, 'info' FROM users WHERE role IN ('admin', 'hr_manager', 'hr_staff')
    ");
    $stmt->execute(["New applicant: {$_POST['first_name']} {$_POST['last_name']} applied for {$_POST['position_applied']}"]);
    
    // Log activity
    logActivity($pdo, $_SESSION['user_id'], 'create_applicant', "Created new applicant: {$_POST['first_name']} {$_POST['last_name']}");
    
    echo json_encode(['success' => true, 'applicant_id' => $applicant_id, 'applicant_number' => $applicant_number]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>