<?php
// login.php
require_once 'includes/config.php';

if (isLoggedIn()) {
    redirect('root.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            logActivity($pdo, $user['id'], 'login', 'User logged in');
            
            redirect('root.php');
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FreightMaster - Login</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a1929;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-screen {
            width: 100%;
            max-width: 1100px;
            display: flex;
            flex-direction: column;
        }

        .system-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .system-label i {
            width: 24px;
            height: 24px;
            color: #0ea5e9;
        }

        .login-container {
            display: flex;
            background: linear-gradient(135deg, #1e3a52 0%, #2d5a7b 100%);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            min-height: 500px;
        }

        .welcome-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background: linear-gradient(135deg, #1e3a52 0%, #2d5a7b 100%);
        }

        .welcome-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }

        .welcome-logo {
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            padding: 20px;
            border-radius: 50%;
        }

        .welcome-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            
        }

        .welcome-text {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
        }

        .welcome-subtext {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            text-align: center;
            max-width: 300px;
        }

        .login-panel {
            width: 400px;
            min-width: 400px;
            padding: 3rem 2.5rem;
            background: #1e2936;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-box {
            width: 100%;
            text-align: center;
        }

        .login-box .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0e4c92, #1a5da0);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            padding: 15px;
        }

        .login-box .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 20%;
         
        }

        .login-box h2 {
            margin-bottom: 1.75rem;
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .login-box form {
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
        }

        .form-group {
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .login-box input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: #2a3544;
            border: 2px solid #3a4554;
            border-radius: 6px;
            color: #e2e8f0;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .login-box input:focus {
            outline: none;
            border-color: #0ea5e9;
            background: #2a3544;
            box-shadow: 0 0 0 1px #0ea5e9;
        }

        .login-box input::placeholder {
            color: #8b92a0;
        }

        .password-field {
            position: relative;
        }

        .password-field input {
            padding-right: 2.5rem;
        }

        .password-icon {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            width: 18px;
            height: 18px;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .password-icon:hover {
            color: #0ea5e9;
        }

        .login-box button {
            padding: 0.875rem;
            background: #0ea5e9;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.95rem;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .login-box button:hover {
            background: #0284c7;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(14, 165, 233, 0.3);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.15);
            color: #ff6b6b;
            padding: 0.75rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(239, 68, 68, 0.3);
            text-align: center;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0 1rem;
            color: #64748b;
            font-size: 0.8rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #3a4554;
        }

        .divider span {
            margin: 0 0.75rem;
        }

        .demo-credentials {
            background: #2a3544;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            text-align: left;
        }

        .demo-credentials p {
            color: #e2e8f0;
            margin-bottom: 0.75rem;
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .demo-credentials p i {
            color: #0ea5e9;
            width: 16px;
            height: 16px;
        }

        .demo-credentials ul {
            list-style: none;
            color: #94a3b8;
            font-size: 0.8rem;
        }

        .demo-credentials li {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .demo-credentials li i {
            color: #0ea5e9;
            width: 14px;
            height: 14px;
        }

        .signup-link {
            margin-top: 1.5rem;
            text-align: center;
            color: #cbd5e1;
            font-size: 0.85rem;
        }

        .signup-link a {
            color: #0ea5e9;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .signup-link a:hover {
            color: #38bdf8;
        }

        .footer {
            text-align: center;
            padding: 2rem 1rem;
            margin-top: 2rem;
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .footer a {
            color: #94a3b8;
            text-decoration: none;
            margin: 0 0.5rem;
            transition: color 0.3s;
        }

        .footer a:hover {
            color: #0ea5e9;
        }

        @media (max-width: 990px) {
            body {
                padding: 1rem;
            }

            .login-container {
                flex-direction: column;
            }
            
            .welcome-panel {
                min-height: 250px;
                padding: 2rem;
            }
            
            .login-panel {
                width: 100%;
                min-width: unset;
                padding: 2rem 1.5rem;
            }

            .welcome-logo {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="login-screen">
        <div class="login-container">
            <div class="welcome-panel">
                <div class="welcome-content">
                    <div class="welcome-logo">
                        <!-- Replace this path with your actual logo image path -->
                        <img src="assets/images/logo1.png" alt="FreightMaster Logo">
                    </div>
                    <p class="welcome-text">FreightMaster</p>
                    <p class="welcome-subtext">Your complete freight management solution</p>
                </div>
            </div>
            <div class="login-panel">
                <div class="login-box">
                    <div class="logo-icon">
                        <!-- Replace this path with your actual logo image path -->
                        <img src="assets/images/logo1.png" alt="FreightMaster Logo">
                    </div>
                    <h2>Welcome Back</h2>
                        
                    <?php if ($error): ?>
                        <div class="error-message">
                            <i class="lucide-alert-circle" data-lucide="alert-circle"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Username or Email</label>
                            <input type="text" name="username" required placeholder="Enter your username or email" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Password</label>
                            <div class="password-field">
                                <input type="password" name="password" required placeholder="••••••••">
                                <i data-lucide="eye" class="password-icon" id="togglePassword"></i>
                            </div>
                        </div>
                        
                        <button type="submit">
                            <i class="lucide-log-in" data-lucide="log-in"></i> Log In
                        </button>
                    </form>

                    <div class="signup-link">
                        Don't have an account? <a href="#">Contact Administrator</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            © 2025 FreightMaster Management System. All rights reserved. &nbsp;|&nbsp;
            <a href="#">Terms & Conditions</a> &nbsp;|&nbsp;
            <a href="#">Privacy Policy</a>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.querySelector('.password-field input');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            this.setAttribute('data-lucide', type === 'password' ? 'eye' : 'eye-off');
            lucide.createIcons();
        });
    </script>
</body>
</html>