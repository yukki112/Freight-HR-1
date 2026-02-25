<?php
// includes/notification_functions.php

/**
 * Get all notifications for a user with module grouping
 * This function is NOT in config.php based on your errors
 */
function getUserNotificationsWithModules($pdo, $user_id, $limit = 50) {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT :limit
    ");
    
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Get unread notifications count per module
 * This function is NOT in config.php based on your errors
 */
function getUnreadCountByModule($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            module,
            COUNT(*) as count
        FROM notifications 
        WHERE user_id = :user_id AND is_read = 0
        GROUP BY module
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $module = $row['module'] ?: 'system';
        $results[$module] = [
            'count' => $row['count'],
            'icon' => getModuleIcon($module),
            'color' => getModuleColor($module)
        ];
    }
    
    return $results;
}

/**
 * Get module icon
 */
function getModuleIcon($module) {
    $icons = [
        'recruitment' => 'fa-bullhorn',
        'applicant' => 'fa-users',
        'onboarding' => 'fa-user-graduate',
        'performance' => 'fa-chart-line',
        'recognition' => 'fa-award',
        'user' => 'fa-users-cog',
        'system' => 'fa-cog'
    ];
    
    return $icons[$module] ?? 'fa-bell';
}

/**
 * Get module color
 */
function getModuleColor($module) {
    $colors = [
        'recruitment' => '#1a5da0',
        'applicant' => '#0e4c92',
        'onboarding' => '#2a6eb0',
        'performance' => '#3a7fc0',
        'recognition' => '#4a90d0',
        'user' => '#0e4c92',
        'system' => '#7f8c8d'
    ];
    
    return $colors[$module] ?? '#0e4c92';
}

/**
 * Get recent API import activity
 * This function is NOT in config.php based on your errors
 */
function getRecentAPIImports($pdo, $limit = 5) {
    $stmt = $pdo->prepare("
        SELECT * FROM activity_log 
        WHERE action IN ('import_job_from_api', 'bulk_import_jobs')
        ORDER BY created_at DESC 
        LIMIT :limit
    ");
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Check for API updates and create notifications
 */
function checkForAPIUpdates($pdo) {
    try {
        // Check for new positions from API
        $api_url = 'https://hsi.qcprotektado.com/recruitment_api.php?action=get_vacant_positions';
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
            if (isset($data['success']) && $data['success']) {
                $api_positions = $data['positions'] ?? [];
                
                // Get existing job codes
                $codes = array_column($api_positions, 'position_id');
                if (!empty($codes)) {
                    // Create placeholders for IN clause
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $stmt = $pdo->prepare("SELECT job_code FROM job_postings WHERE job_code IN ($placeholders)");
                    $stmt->execute($codes);
                    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Find new positions
                    $new_positions = [];
                    foreach ($api_positions as $position) {
                        if (!in_array($position['position_id'], $existing)) {
                            $new_positions[] = $position;
                        }
                    }
                    
                    // Create notifications for new positions
                    if (!empty($new_positions)) {
                        $count = count($new_positions);
                        $title = $count . " New Job " . ($count > 1 ? "Positions" : "Position") . " Available";
                        $message = $count . " new position" . ($count > 1 ? "s" : "") . " from API: ";
                        $message .= implode(', ', array_column(array_slice($new_positions, 0, 3), 'title'));
                        if ($count > 3) {
                            $message .= " and " . ($count - 3) . " more";
                        }
                        
                        notifyAllAdmins($pdo, $title, $message, 'info', 'recruitment', '?page=recruitment&subpage=job-posting');
                        
                        return $count;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("API Update Check Error: " . $e->getMessage());
    }
    
    return 0;
}

/**
 * Create notification for all admin users
 */
function notifyAllAdmins($pdo, $title, $message, $type = 'info', $module = 'system', $link = null) {
    $stmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'dispatcher', 'management')");
    $admins = $stmt->fetchAll();
    
    $success = true;
    foreach ($admins as $admin) {
        // Use createNotification function from config.php
        if (function_exists('createNotification')) {
            $result = createNotification($pdo, $admin['id'], $title, $message, $type, $module, $link);
        } else {
            // Fallback if function doesn't exist
            $stmt2 = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, module, link, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $result = $stmt2->execute([$admin['id'], $title, $message, $type, $module, $link]);
        }
        
        if (!$result) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Mark module notifications as read
 */
function markModuleNotificationsAsRead($pdo, $user_id, $module) {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = :user_id AND module = :module AND is_read = 0
    ");
    
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':module', $module, PDO::PARAM_STR);
    
    return $stmt->execute();
}

/**
 * Time ago function - check if it exists first
 */
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }
}