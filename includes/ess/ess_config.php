<?php
// /includes/ess/ess_config.php
// ESS-specific configuration — loaded AFTER main config.php

require_once __DIR__ . '/../config.php';

// ESS session namespace (uses same $_SESSION but with prefixes)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ESS Constants
define('ESS_TEMP_PASSWORD_EXPIRY_DAYS', 7);
define('ESS_MAX_LOGIN_ATTEMPTS', 5);
define('ESS_LOCKOUT_MINUTES', 30);
define('ESS_SESSION_LIFETIME_HOURS', 8);
define('ESS_BASE_URL', 'ess.php');           // main router
define('ESS_LOGIN_URL', 'ess_login.php');
define('ESS_LOGOUT_URL', 'ess_logout.php');

// ESS timezone
date_default_timezone_set('Asia/Manila');
?>