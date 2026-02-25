<?php
// ajax/mark_notifications_read.php
session_start();
require_once 'config.php';
require_once 'notification_functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

try {
    if (isset($data['all']) && $data['all'] === true) {
        // Mark all notifications as read - use the function from config.php
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $success = $stmt->execute([$user_id]);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'All notifications marked as read' : 'Failed to mark notifications'
        ]);
    } 
    elseif (isset($data['id'])) {
        // Mark specific notification as read - use the function from config.php
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $success = $stmt->execute([$data['id'], $user_id]);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Notification marked as read' : 'Failed to mark notification'
        ]);
    }
    elseif (isset($data['module'])) {
        // Mark all notifications in a module as read - use our new function
        if (function_exists('markModuleNotificationsAsRead')) {
            $success = markModuleNotificationsAsRead($pdo, $user_id, $data['module']);
        } else {
            // Fallback if function doesn't exist
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND module = ?");
            $success = $stmt->execute([$user_id, $data['module']]);
        }
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Module notifications marked as read' : 'Failed to mark notifications'
        ]);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>