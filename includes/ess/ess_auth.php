<?php
// /includes/ess/ess_auth.php
require_once __DIR__ . '/ess_config.php';

// Load PHPMailer config (your existing one)
require_once __DIR__ . '/../../config/mail_config.php';

/**
 * Check if ESS user is logged in
 */
function essIsLoggedIn() {
    if (!isset($_SESSION['ess_account_id']) || !isset($_SESSION['ess_employee_id'])) {
        return false;
    }
    if (isset($_SESSION['ess_last_activity'])) {
        $inactive = time() - $_SESSION['ess_last_activity'];
        if ($inactive > (ESS_SESSION_LIFETIME_HOURS * 3600)) {
            essLogout();
            return false;
        }
    }
    $_SESSION['ess_last_activity'] = time();
    return true;
}

/**
 * Require ESS login — redirect if not logged in
 */
function essRequireLogin() {
    if (!essIsLoggedIn()) {
        header('Location: ' . ESS_LOGIN_URL . '?error=session_expired');
        exit;
    }
    // Force password change if needed
    if (!empty($_SESSION['ess_must_change_password'])) {
        $current = basename($_SERVER['PHP_SELF']);
        if ($current !== 'ess_change_password.php' && $current !== 'ess_logout.php') {
            header('Location: ess_change_password.php');
            exit;
        }
    }
}

/**
 * Get ESS account (with employee info joined)
 * NOTE: first_name / last_name come from job_applications via applicant_id
 */
function essGetAccount($pdo, $accountId = null) {
    $accountId = $accountId ?? ($_SESSION['ess_account_id'] ?? 0);
    if (!$accountId) return null;

    $stmt = $pdo->prepare("
        SELECT ea.*,
               nh.employee_id       AS emp_code,
               nh.position,
               nh.department,
               nh.personal_email,
               nh.personal_phone,
               nh.status            AS employee_status,
               nh.hire_date,
               ja.first_name,
               ja.last_name,
               ja.email             AS applicant_email
        FROM ess_accounts ea
        JOIN new_hires nh         ON ea.employee_id = nh.id
        JOIN job_applications ja  ON nh.applicant_id = ja.id
        WHERE ea.id = ?
    ");
    $stmt->execute([$accountId]);
    return $stmt->fetch();
}

/**
 * Get ESS employee info
 */
function essGetEmployee($pdo, $employeeId = null) {
    $employeeId = $employeeId ?? ($_SESSION['ess_employee_id'] ?? 0);
    if (!$employeeId) return null;

    $stmt = $pdo->prepare("
        SELECT nh.*,
               ja.first_name,
               ja.last_name,
               ja.email AS applicant_email,
               CONCAT(ja.first_name, ' ', ja.last_name) AS full_name
        FROM new_hires nh
        JOIN job_applications ja ON nh.applicant_id = ja.id
        WHERE nh.id = ?
    ");
    $stmt->execute([$employeeId]);
    return $stmt->fetch();
}

/**
 * ESS Login
 */
function essLogin($pdo, $usernameOrEmail, $password) {
    $stmt = $pdo->prepare("
        SELECT * FROM ess_accounts
        WHERE (username = ? OR email = ?) AND is_active = 1
    ");
    $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
    $account = $stmt->fetch();

    if (!$account) {
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    // Lockout check
    if ($account['locked_until'] && strtotime($account['locked_until']) > time()) {
        $remaining = ceil((strtotime($account['locked_until']) - time()) / 60);
        return ['success' => false, 'error' => "Account locked. Try again in {$remaining} minutes."];
    }

    // Temp password expiry check
    if ($account['must_change_password'] && $account['temp_password_expires_at']) {
        if (strtotime($account['temp_password_expires_at']) < time()) {
            return ['success' => false, 'error' => 'Temporary password has expired. Please request a new one from HR.'];
        }
    }

    // Verify password
    if (!password_verify($password, $account['password_hash'])) {
        $attempts = $account['login_attempts'] + 1;
        $lockedUntil = null;
        if ($attempts >= ESS_MAX_LOGIN_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', strtotime('+' . ESS_LOCKOUT_MINUTES . ' minutes'));
        }
        $stmt = $pdo->prepare("UPDATE ess_accounts SET login_attempts = ?, locked_until = ? WHERE id = ?");
        $stmt->execute([$attempts, $lockedUntil, $account['id']]);
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }

    // Reset attempts
    $stmt = $pdo->prepare("UPDATE ess_accounts SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
    $stmt->execute([$account['id']]);

    // Set session
    $_SESSION['ess_account_id']           = $account['id'];
    $_SESSION['ess_employee_id']          = $account['employee_id'];
    $_SESSION['ess_username']             = $account['username'];
    $_SESSION['ess_email']                = $account['email'];
    $_SESSION['ess_must_change_password'] = (int)$account['must_change_password'];
    $_SESSION['ess_last_activity']        = time();

    essLogActivity($pdo, $account['id'], $account['employee_id'], 'login', 'Employee logged in');

    return [
        'success'              => true,
        'must_change_password' => (bool)$account['must_change_password']
    ];
}

/**
 * ESS Logout
 */
function essLogout() {
    if (isset($_SESSION['ess_account_id'])) {
        global $pdo;
        if (isset($pdo)) {
            essLogActivity(
                $pdo,
                $_SESSION['ess_account_id'],
                $_SESSION['ess_employee_id'] ?? null,
                'logout',
                'Employee logged out'
            );
        }
    }
    unset(
        $_SESSION['ess_account_id'],
        $_SESSION['ess_employee_id'],
        $_SESSION['ess_username'],
        $_SESSION['ess_email'],
        $_SESSION['ess_must_change_password'],
        $_SESSION['ess_last_activity']
    );
}

/**
 * Change password
 */
function essChangePassword($pdo, $accountId, $newPassword) {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO ess_password_history (account_id, password_hash) VALUES (?, ?)");
    $stmt->execute([$accountId, $hash]);

    $stmt = $pdo->prepare("
        UPDATE ess_accounts
        SET password_hash            = ?,
            temp_password_hash       = NULL,
            temp_password_expires_at = NULL,
            must_change_password     = 0,
            password_changed_at      = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$hash, $accountId]);

    $_SESSION['ess_must_change_password'] = 0;
    essLogActivity($pdo, $accountId, $_SESSION['ess_employee_id'] ?? null, 'change_password', 'Password changed');
    return true;
}

/**
 * Generate temp password
 */
function essGenerateTempPassword($length = 12) {
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Create ESS account
 */
function essCreateAccount($pdo, $employeeId, $email, $createdBy = null) {
    $stmt = $pdo->prepare("SELECT id FROM ess_accounts WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Account already exists for this employee.'];
    }

    $username = strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0]));
    if (empty($username)) $username = 'emp' . $employeeId;
    $base = $username;
    $i = 1;
    while (true) {
        $chk = $pdo->prepare("SELECT id FROM ess_accounts WHERE username = ?");
        $chk->execute([$username]);
        if (!$chk->fetch()) break;
        $username = $base . $i++;
    }

    $tempPassword = essGenerateTempPassword();
    $tempHash     = password_hash($tempPassword, PASSWORD_DEFAULT);
    $expiresAt    = date('Y-m-d H:i:s', strtotime('+' . ESS_TEMP_PASSWORD_EXPIRY_DAYS . ' days'));

    $stmt = $pdo->prepare("
        INSERT INTO ess_accounts
        (employee_id, username, email, password_hash, temp_password_hash, temp_password_expires_at, must_change_password, created_by)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $stmt->execute([$employeeId, $username, $email, $tempHash, $tempHash, $expiresAt, $createdBy]);

    return [
        'success'       => true,
        'account_id'    => (int)$pdo->lastInsertId(),
        'username'      => $username,
        'temp_password' => $tempPassword,
        'expires_at'    => $expiresAt,
        'email'         => $email
    ];
}

/**
 * Reset password (admin action)
 */
function essResetPassword($pdo, $accountId) {
    $tempPassword = essGenerateTempPassword();
    $tempHash     = password_hash($tempPassword, PASSWORD_DEFAULT);
    $expiresAt    = date('Y-m-d H:i:s', strtotime('+' . ESS_TEMP_PASSWORD_EXPIRY_DAYS . ' days'));

    $stmt = $pdo->prepare("
        UPDATE ess_accounts
        SET password_hash            = ?,
            temp_password_hash       = ?,
            temp_password_expires_at = ?,
            must_change_password     = 1,
            login_attempts           = 0,
            locked_until             = NULL
        WHERE id = ?
    ");
    $stmt->execute([$tempHash, $tempHash, $expiresAt, $accountId]);

    return [
        'success'       => true,
        'temp_password' => $tempPassword,
        'expires_at'    => $expiresAt
    ];
}

/**
 * Log activity
 */
function essLogActivity($pdo, $accountId, $employeeId, $action, $description = '') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ess_activity_log (account_id, employee_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $accountId,
            $employeeId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Silent fail — logging should never break the app
    }
}

/**
 * Get ESS notifications
 */
function essGetNotifications($pdo, $employeeId, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT * FROM ess_notifications
        WHERE employee_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $employeeId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get unread notification count
 */
function essGetUnreadCount($pdo, $employeeId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ess_notifications WHERE employee_id = ? AND is_read = 0");
    $stmt->execute([$employeeId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Send ESS credentials email — USES PHPMailer via your MailConfig class
 *
 * @return bool  true if sent, false on failure
 */
function essSendCredentialsEmail($toEmail, $employeeName, $username, $tempPassword, $expiresAt) {
    try {
        // Get the shared PHPMailer instance
        $mail = MailConfig::getInstance();

        // Clear any previous recipients/attachments from shared instance
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->clearCustomHeaders();
        $mail->clearBCCs();
        $mail->clearCCs();

        // Build login URL
        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir      = rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/\\');
        // dirname on /hr1/root.php = /hr1  → ess_login.php lives at /hr1/ess_login.php
        $loginUrl = $scheme . $host . $dir . '/ess_login.php';

        $expiresFmt = date('F j, Y g:i A', strtotime($expiresAt));

        // Recipients
        $mail->addAddress($toEmail, $employeeName);
        $mail->addBCC('hr@freightmanagement.com', 'HR Department');

        // Subject
        $mail->Subject = "Your Employee Self-Service (ESS) Account | Freight HR 1";

        // Build HTML body
        $safeName     = htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8');
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safePass     = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
        $safeUrl      = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width,initial-scale=1.0">
            <style>
                body{font-family:"Inter",Arial,sans-serif;line-height:1.6;margin:0;padding:0;background:linear-gradient(135deg,#f5f7fa,#e9edf5);}
                .container{max-width:600px;margin:20px auto;background:#fff;border-radius:30px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);}
                .header{background:linear-gradient(135deg,#0e4c92,#4086e4);color:#fff;padding:40px 30px;text-align:center;}
                .header h1{margin:0;font-size:24px;font-weight:700;}
                .header p{margin:8px 0 0;opacity:0.9;font-size:14px;}
                .content{padding:35px 30px;color:#333;}
                .content p{font-size:14px;color:#4a5568;}
                .cred-box{background:#f0f7ff;border-left:4px solid #0e4c92;padding:20px;border-radius:10px;margin:20px 0;}
                .cred-box p{margin:0 0 10px;font-size:14px;color:#2c3e50;}
                .cred-box p:last-child{margin-bottom:0;}
                .cred-box code{background:#e0edff;padding:3px 8px;border-radius:6px;font-family:monospace;color:#0e4c92;font-size:15px;}
                .cred-box .pwd{font-size:17px;font-weight:700;}
                .warn-box{background:#fff7ed;border-left:4px solid #f59e0b;padding:15px 18px;border-radius:10px;margin:20px 0;}
                .warn-box p{margin:0;font-size:13px;color:#92400e;line-height:1.6;}
                .footer{background:#f8fafd;padding:20px 30px;text-align:center;border-top:1px solid #eef2f6;}
                .footer p{margin:5px 0;font-size:12px;color:#64748b;}
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Employee Self-Service Access</h1>
                    <p>Freight Management HR 1</p>
                </div>
                <div class="content">
                    <p>Hi <strong>' . $safeName . '</strong>,</p>
                    <p>Your Employee Self-Service (ESS) account has been created. Use the credentials below to log in.</p>

                    <div class="cred-box">
                        <p><strong>Login URL:</strong><br>
                            <a href="' . $safeUrl . '" style="color:#0e4c92;">' . $safeUrl . '</a>
                        </p>
                        <p><strong>Username:</strong> <code>' . $safeUsername . '</code></p>
                        <p><strong>Temporary Password:</strong> <code class="pwd">' . $safePass . '</code></p>
                    </div>

                    <div class="warn-box">
                        <p><strong>⚠️ Important:</strong> This temporary password expires on <strong>' . $expiresFmt . '</strong>. You must change it upon first login. After expiry, request a new one from HR.</p>
                    </div>

                    <p>If you did not request this account, please contact HR immediately.</p>
                    <p style="margin-top:25px;">Best regards,<br>
                        <strong>HR Department</strong><br>
                        Freight Management HR 1
                    </p>
                </div>
                <div class="footer">
                    <p>Freight Management Inc.</p>
                    <p>This is an automated message. Please do not reply.</p>
                </div>
            </div>
        </body>
        </html>';

        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = "Hi {$employeeName},\n\nYour ESS account has been created.\n\nLogin URL: {$loginUrl}\nUsername: {$username}\nTemporary Password: {$tempPassword}\n\nThis password expires on {$expiresFmt}. You must change it upon first login.\n\n- HR Department";

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Log the actual error for debugging
        error_log("ESS email send failed: " . ($mail->ErrorInfo ?? $e->getMessage()));
        return false;
    }
}
?>