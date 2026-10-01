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
<title>Employee Self-Service | Priority Handling Logistics, Inc.</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #0a1929 0%, #1a2942 50%, #0f3a4a 100%);
    padding: 20px;
    position: relative;
    overflow: hidden;
    color: #f8fafc;
}

/* Ambient radial glows (matches index.php hero) */
body::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 20% 30%, rgba(14, 165, 233, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 50% 90%, rgba(59, 130, 246, 0.08) 0%, transparent 50%);
    pointer-events: none;
}

/* Floating shapes */
.floating-shape {
    position: absolute;
    border-radius: 50%;
    opacity: 0.06;
    background: white;
    animation: float 20s infinite ease-in-out;
    pointer-events: none;
}
.shape-1 { width: 450px; height: 450px; top: -180px; right: -120px; }
.shape-2 { width: 350px; height: 350px; bottom: -120px; left: -80px; animation-delay: -7s; }
.shape-3 { width: 200px; height: 200px; top: 55%; right: 18%; animation-delay: -12s; }
@keyframes float {
    0%, 100% { transform: translate(0,0) rotate(0deg); }
    33% { transform: translate(30px,-30px) rotate(120deg); }
    66% { transform: translate(-20px,20px) rotate(240deg); }
}

/* Login Card */
.login-container {
    background: rgba(30, 41, 54, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 28px;
    border: 1px solid rgba(58, 69, 84, 0.6);
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
    width: 100%;
    max-width: 440px;
    padding: 45px 40px;
    position: relative;
    z-index: 10;
    animation: slideUp 0.6s ease-out;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header */
.login-header { text-align: center; margin-bottom: 32px; }

.logo-wrapper {
    width: 90px;
    height: 90px;
    margin: 0 auto 20px;
    background: rgba(14, 165, 233, 0.1);
    border: 2px solid rgba(14, 165, 233, 0.3);
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 0 8px rgba(14, 165, 233, 0.05), 0 15px 40px rgba(14, 165, 233, 0.2);
    padding: 10px;
    overflow: hidden;
    animation: pulse 3s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 8px rgba(14, 165, 233, 0.05), 0 15px 40px rgba(14, 165, 233, 0.2); }
    50% { transform: scale(1.03); box-shadow: 0 0 0 12px rgba(14, 165, 233, 0.08), 0 20px 50px rgba(14, 165, 233, 0.3); }
}
.logo-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 16px;
}

.login-header h1 {
    font-size: 24px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 6px;
    letter-spacing: 0.3px;
}
.login-header p {
    font-size: 13px;
    color: #94a3b8;
    font-weight: 500;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(14, 165, 233, 0.15);
    border: 1px solid rgba(14, 165, 233, 0.3);
    color: #0ea5e9;
    font-size: 11px;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 20px;
    margin-top: 14px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}
.badge i { font-size: 10px; }

/* Alerts */
.alert {
    padding: 14px 18px;
    border-radius: 14px;
    margin-bottom: 22px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.3s ease-out;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: #6ee7b7;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.alert i { font-size: 16px; flex-shrink: 0; }

/* Form */
.form-group { margin-bottom: 20px; }
.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #cbd5e1;
    margin-bottom: 8px;
    letter-spacing: 0.2px;
}

.input-wrapper { position: relative; }
.input-wrapper > i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 15px;
    pointer-events: none;
    transition: color 0.3s;
}

.form-group input {
    width: 100%;
    padding: 14px 46px;
    border: 2px solid #3a4554;
    border-radius: 14px;
    font-size: 14px;
    transition: all 0.3s;
    background: #1e2936;
    color: #e2e8f0;
    font-family: inherit;
}
.form-group input::placeholder { color: #64748b; }
.form-group input:hover { border-color: rgba(14, 165, 233, 0.4); }
.form-group input:focus {
    outline: none;
    border-color: #0ea5e9;
    background: #1e2936;
    box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15);
}
.form-group input:focus ~ i,
.input-wrapper:focus-within > i {
    color: #0ea5e9;
}

.password-toggle {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    font-size: 15px;
    padding: 5px;
    transition: color 0.3s;
}
.password-toggle:hover { color: #0ea5e9; }

/* Button */
.btn-login {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-family: inherit;
    box-shadow: 0 10px 25px rgba(14, 165, 233, 0.3);
    letter-spacing: 0.3px;
    margin-top: 8px;
}
.btn-login:hover {
    background: linear-gradient(135deg, #0284c7, #0369a1);
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(14, 165, 233, 0.45);
}
.btn-login:active { transform: translateY(0); }

/* Footer */
.login-footer {
    text-align: center;
    margin-top: 28px;
    padding-top: 22px;
    border-top: 1px solid rgba(58, 69, 84, 0.6);
}
.login-footer p {
    font-size: 12px;
    color: #64748b;
    line-height: 1.6;
}

/* Back to home link */
.back-home {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    color: #94a3b8;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    transition: color 0.3s;
}
.back-home:hover { color: #0ea5e9; }
.back-home i { font-size: 12px; }

/* Responsive */
@media (max-width: 480px) {
    .login-container { padding: 35px 25px; border-radius: 22px; }
    .login-header h1 { font-size: 20px; }
    .logo-wrapper { width: 80px; height: 80px; }
}
</style>
</head>
<body>
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>
    <div class="floating-shape shape-3"></div>

    <div class="login-container">
        <div class="login-header">
            <div class="logo-wrapper">
                <img src="assets/images/LOGO.jpg" alt="Priority Handling Logistics Logo">
            </div>
            <h1>Employee Self-Service</h1>
            <p>Priority Handling Logistics, Inc.</p>
            <span class="badge"><i class="fas fa-shield-alt"></i> Secure Portal</span>
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
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="login-footer">
            <a href="index.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <script>
    function togglePassword() {
        const p = document.getElementById('essPassword');
        const i = document.getElementById('eyeIcon');
        if (p.type === 'password') {
            p.type = 'text';
            i.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            p.type = 'password';
            i.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>
</body>
</html>