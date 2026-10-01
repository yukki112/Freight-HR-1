<?php
// /ess_login.php
require_once __DIR__ . '/includes/ess/ess_auth.php';

if (essIsLoggedIn()) {
    if (!empty($_SESSION['ess_must_change_password'])) {
        header('Location: ess_change_password.php');
    } else {
        header('Location: ess.php');
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $result = essLogin($pdo, $username, $password);
        if ($result['success']) {
            if ($result['must_change_password']) {
                header('Location: ess_change_password.php');
            } else {
                header('Location: ess.php');
            }
            exit;
        }
        $error = $result['error'];
    }
}

if (isset($_GET['error']) && $_GET['error'] === 'session_expired') {
    $error = 'Your session has expired. Please log in again.';
}
if (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employee Self-Service | Freight HR 1</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #0e4c92 0%, #1a5da0 50%, #4086e4 100%);
    padding: 20px; position: relative; overflow: hidden;
}
.floating-shape { position: absolute; border-radius: 50%; opacity: 0.08; animation: float 20s infinite ease-in-out; }
.shape-1 { width: 450px; height: 450px; background: white; top: -180px; right: -120px; }
.shape-2 { width: 350px; height: 350px; background: white; bottom: -120px; left: -80px; animation-delay: -7s; }
.shape-3 { width: 200px; height: 200px; background: white; top: 55%; right: 18%; animation-delay: -12s; }
@keyframes float {
    0%,100% { transform: translate(0,0) rotate(0deg); }
    33% { transform: translate(30px,-30px) rotate(120deg); }
    66% { transform: translate(-20px,20px) rotate(240deg); }
}
.login-container {
    background: rgba(255,255,255,0.98);
    backdrop-filter: blur(20px);
    border-radius: 28px;
    box-shadow: 0 30px 80px rgba(0,0,0,0.3);
    width: 100%; max-width: 440px;
    padding: 45px 40px;
    position: relative; z-index: 10;
    animation: slideUp 0.6s ease-out;
}
@keyframes slideUp { from {opacity:0; transform:translateY(30px);} to {opacity:1; transform:translateY(0);} }
.login-header { text-align: center; margin-bottom: 32px; }
.logo-wrapper {
    width: 80px; height: 80px; margin: 0 auto 20px;
    background: linear-gradient(135deg, #0e4c92, #4086e4);
    border-radius: 24px; display:flex; align-items:center; justify-content:center;
    box-shadow: 0 10px 30px rgba(14,76,146,0.3);
}
.logo-wrapper i { font-size: 38px; color: white; }
.login-header h1 { font-size: 26px; font-weight: 700; color: #0e4c92; margin-bottom: 6px; }
.login-header p { font-size: 14px; color: #64748b; }
.badge {
    display: inline-block;
    background: linear-gradient(135deg, #0e4c92, #4086e4);
    color: white; font-size: 11px; font-weight: 600;
    padding: 5px 14px; border-radius: 20px; margin-top: 12px; letter-spacing: 0.5px;
}
.alert {
    padding: 14px 18px; border-radius: 14px; margin-bottom: 22px;
    font-size: 13px; display: flex; align-items: center; gap: 10px;
}
.alert-danger { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
.alert-success { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
.form-group { margin-bottom: 20px; }
.form-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px; }
.input-wrapper { position: relative; }
.input-wrapper i { position:absolute; left:16px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:15px; }
.form-group input {
    width: 100%; padding: 14px 46px;
    border: 2px solid #e5e7eb; border-radius: 14px;
    font-size: 14px; transition: all 0.3s;
    background: #f9fafb; font-family: inherit;
}
.form-group input:focus { outline:none; border-color:#0e4c92; background:white; box-shadow: 0 0 0 4px rgba(14,76,146,0.1); }
.password-toggle {
    position:absolute; right:16px; top:50%; transform:translateY(-50%);
    background:none; border:none; color:#94a3b8; cursor:pointer; font-size:15px; padding:5px;
}
.password-toggle:hover { color:#0e4c92; }
.btn-login {
    width: 100%; padding: 15px;
    background: linear-gradient(135deg, #0e4c92, #4086e4);
    color: white; border: none; border-radius: 14px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    transition: all 0.3s; display: flex; align-items: center; justify-content: center;
    gap: 10px; font-family: inherit;
    box-shadow: 0 4px 15px rgba(14,76,146,0.3);
}
.btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(14,76,146,0.4); }
.btn-login:active { transform: translateY(0); }
.login-footer { text-align: center; margin-top: 28px; padding-top: 22px; border-top: 1px solid #f1f5f9; }
.login-footer p { font-size: 12px; color: #94a3b8; line-height: 1.6; }
.login-footer a { color: #0e4c92; text-decoration: none; font-weight: 600; }
.login-footer a:hover { text-decoration: underline; }
@media (max-width: 480px) {
    .login-container { padding: 35px 25px; border-radius: 22px; }
    .login-header h1 { font-size: 22px; }
}
</style>
</head>
<body>
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>
    <div class="floating-shape shape-3"></div>

    <div class="login-container">
        <div class="login-header">
            <div class="logo-wrapper"><i class="fas fa-user-circle"></i></div>
            <h1>Employee Self-Service</h1>
            <p>Freight Management HR 1</p>
            <span class="badge"><i class="fas fa-shield-alt"></i> SECURE PORTAL</span>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Username or Email</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Enter your username or email" required
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autofocus>
                </div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="essPassword" placeholder="Enter your password" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()"><i class="fas fa-eye" id="eyeIcon"></i></button>
                </div>
            </div>
            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="login-footer">
            <p>
                <i class="fas fa-info-circle"></i> First-time user? Use the temporary password sent to your email.<br>
                Need access? Contact <a href="#">HR Department</a>
            </p>
        </div>
    </div>

    <script>
    function togglePassword() {
        const p = document.getElementById('essPassword');
        const i = document.getElementById('eyeIcon');
        if (p.type === 'password') { p.type = 'text'; i.classList.replace('fa-eye','fa-eye-slash'); }
        else { p.type = 'password'; i.classList.replace('fa-eye-slash','fa-eye'); }
    }
    </script>
</body>
</html>