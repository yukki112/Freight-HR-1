<?php
// /ess_logout.php
require_once __DIR__ . '/includes/ess/ess_auth.php';
essLogout();
header('Location: ess_login.php?logout=1');
exit;
?>