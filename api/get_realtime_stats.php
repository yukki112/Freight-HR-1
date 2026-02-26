<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Get real-time stats
    $stats = getHRStats($pdo, $_SESSION['user_id']);
    
    // Get latest activity
    $stmt = $pdo->prepare("
        SELECT al.action, al.description, u.full_name, 
               TIME_FORMAT(al.created_at, '%h:%i %p') as time
        FROM activity_log al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    $latest_activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = array_merge($stats, [
        'latest_activity' => $latest_activity,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>