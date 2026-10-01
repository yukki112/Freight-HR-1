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
    <title>Login — Priority Handling Logistics, Inc.</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a1929 0%, #1a2942 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: #f8fafc;
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
            border: 1px solid rgba(58, 69, 84, 0.5);
        }

        .welcome-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background: linear-gradient(135deg, #1e3a52 0%, #2d5a7b 100%);
            position: relative;
        }

        .welcome-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(14, 165, 233, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(99, 102, 241, 0.15) 0%, transparent 50%);
            pointer-events: none;
        }

        .welcome-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .welcome-logo {
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid rgba(255, 255, 255, 0.15);
            overflow: hidden;
            padding: 20px;
            backdrop-filter: blur(10px);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.4); }
            50% { transform: scale(1.03); box-shadow: 0 0 0 20px rgba(14, 165, 233, 0); }
        }

        .welcome-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .welcome-text {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 0.3px;
        }

        .welcome-subtext {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            text-align: center;
            max-width: 320px;
            line-height: 1.6;
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
            background: rgba(14, 165, 233, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            padding: 15px;
            border: 1px solid rgba(14, 165, 233, 0.2);
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

        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 1rem;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.3s;
        }

        .back-home:hover {
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
                        <img src="assets/images/LOGO.jpg" alt="Priority Handling Logistics Logo">
                    </div>
                    <p class="welcome-text">Priority Handling Logistics, Inc.</p>
                    <p class="welcome-subtext">Complete HR Management System for Logistics Excellence</p>
                </div>
            </div>
            <div class="login-panel">
                <div class="login-box">
                    <div class="logo-icon">
                        <img src="assets/images/LOGO.jpg" alt="Priority Handling Logistics Logo">
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

                    

                    <a href="index.php" class="back-home">
                        <i data-lucide="arrow-left" style="width:14px;height:14px;"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
        
        <div class="footer">
            &copy; 2026 Priority Handling Logistics, Inc. All rights reserved. &nbsp;|&nbsp;
            <a href="#">Terms &amp; Conditions</a> &nbsp;|&nbsp;
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