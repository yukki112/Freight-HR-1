<?php
// /ess_change_password.php
require_once __DIR__ . '/includes/ess/ess_auth.php';

if (!essIsLoggedIn()) {
    header('Location: ess_login.php');
    exit;
}

$account = essGetAccount($pdo);
if (!$account) { essLogout(); header('Location: ess_login.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $error = 'Password must contain uppercase, lowercase, and a number.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        essChangePassword($pdo, $account['id'], $new);
        header('Location: ess.php?password_changed=1');
        exit;
    }
}

// Calculate expiry info
$expiresAt = $account['temp_password_expires_at'];
$daysLeft = $expiresAt ? ceil((strtotime($expiresAt) - time()) / 86400) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password | ESS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #0e4c92, #4086e4);
    padding: 20px;
}
.card {
    background: white; border-radius: 24px;
    padding: 40px; max-width: 480px; width: 100%;
    box-shadow: 0 30px 80px rgba(0,0,0,0.3);
}
.icon-header {
    width: 80px; height: 80px; margin: 0 auto 20px;
    background: linear-gradient(135deg, #f59e0b, #f97316);
    border-radius: 22px; display: flex; align-items: center; justify-content: center;
    box-shadow: 0 10px 30px rgba(245,158,11,0.3);
}
.icon-header i { font-size: 36px; color: white; }
h1 { text-align: center; color: #0e4c92; font-size: 22px; margin-bottom: 8px; }
.subtitle { text-align: center; color: #64748b; font-size: 13px; margin-bottom: 25px; }
.warning-box {
    background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px;
    padding: 15px; margin-bottom: 22px; font-size: 13px; color: #9a3412; line-height: 1.6;
}
.warning-box strong { color: #ea580c; }
.alert { padding: 13px 18px; border-radius: 12px; margin-bottom: 18px; font-size: 13px; }
.alert-danger { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
.form-group { margin-bottom: 16px; }
.form-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:7px; }
.form-group input {
    width: 100%; padding: 13px 15px;
    border: 2px solid #e5e7eb; border-radius: 12px;
    font-size: 14px; transition: all 0.3s; background: #f9fafb;
    font-family: inherit;
}
.form-group input:focus { outline:none; border-color:#0e4c92; background:white; box-shadow: 0 0 0 4px rgba(14,76,146,0.1); }
.req-list { list-style: none; padding: 0; margin: 8px 0 0; font-size: 12px; color: #64748b; }
.req-list li { padding: 3px 0; }
.req-list li.met { color: #16a34a; }
.req-list li.met i::before { content: "\f058"; }
.btn {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, #0e4c92, #4086e4);
    color: white; border: none; border-radius: 12px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    margin-top: 10px; font-family: inherit;
}
.btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(14,76,146,0.4); }
.btn-logout {
    background: transparent; color: #64748b;
    border: 1px solid #e5e7eb; margin-top: 10px;
}
.btn-logout:hover { background: #f8fafc; color: #dc2626; border-color: #fecaca; box-shadow:none; transform:none; }
</style>
</head>
<body>
<div class="card">
    <div class="icon-header"><i class="fas fa-key"></i></div>
    <h1>Change Your Password</h1>
    <p class="subtitle">Welcome, <?= htmlspecialchars($account['first_name'] ?? 'Employee') ?>!</p>

    <?php if ($daysLeft > 0): ?>
    <div class="warning-box">
        <i class="fas fa-clock"></i> <strong>Action Required:</strong>
        Your temporary password expires in <strong><?= $daysLeft ?> day<?= $daysLeft != 1 ? 's' : '' ?></strong>.
        Please change it now to continue using ESS.
    </div>
    <?php else: ?>
    <div class="warning-box">
        <i class="fas fa-exclamation-triangle"></i> <strong>Password Expired:</strong>
        Your temporary password has expired. You must change it now.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="pwdForm">
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" id="newPwd" required autofocus placeholder="Enter new password">
            <ul class="req-list" id="reqList">
                <li id="req-len"><i class="far fa-circle"></i> At least 8 characters</li>
                <li id="req-upper"><i class="far fa-circle"></i> One uppercase letter</li>
                <li id="req-lower"><i class="far fa-circle"></i> One lowercase letter</li>
                <li id="req-num"><i class="far fa-circle"></i> One number</li>
            </ul>
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirmPwd" required placeholder="Re-enter new password">
            <small id="matchMsg" style="font-size:12px; margin-top:6px; display:block;"></small>
        </div>
        <button type="submit" class="btn"><i class="fas fa-save"></i> Save New Password</button>
        <a href="ess_logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </form>
</div>

<script>
const newPwd = document.getElementById('newPwd');
const confirmPwd = document.getElementById('confirmPwd');
const matchMsg = document.getElementById('matchMsg');

newPwd.addEventListener('input', function() {
    const v = this.value;
    toggleReq('req-len', v.length >= 8);
    toggleReq('req-upper', /[A-Z]/.test(v));
    toggleReq('req-lower', /[a-z]/.test(v));
    toggleReq('req-num', /[0-9]/.test(v));
});
confirmPwd.addEventListener('input', function() {
    if (this.value && this.value !== newPwd.value) {
        matchMsg.textContent = '✗ Passwords do not match';
        matchMsg.style.color = '#dc2626';
    } else if (this.value && this.value === newPwd.value) {
        matchMsg.textContent = '✓ Passwords match';
        matchMsg.style.color = '#16a34a';
    } else {
        matchMsg.textContent = '';
    }
});
function toggleReq(id, met) {
    const el = document.getElementById(id);
    el.classList.toggle('met', met);
}
</script>
</body>
</html>