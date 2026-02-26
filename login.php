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
    <title>Freight Management - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: linear-gradient(135deg, #f0f7ff 0%, #e6f0fa 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .floating-bg {
            position: fixed;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .floating-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(14, 76, 146, 0.03);
            animation: float 20s infinite ease-in-out;
        }
        
        .circle-1 {
            width: 600px;
            height: 600px;
            top: -200px;
            right: -200px;
            background: radial-gradient(circle, rgba(14,76,146,0.05) 0%, rgba(14,76,146,0) 70%);
        }
        
        .circle-2 {
            width: 400px;
            height: 400px;
            bottom: -100px;
            left: -100px;
            background: radial-gradient(circle, rgba(26,93,160,0.05) 0%, rgba(26,93,160,0) 70%);
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(50px, 50px) scale(1.1); }
            50% { transform: translate(0, 100px) scale(0.9); }
            75% { transform: translate(-50px, 50px) scale(1.05); }
        }
        
        .auth-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }
        
        .auth-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(14, 76, 146, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-header .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0e4c92, #1a5da0);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 40px;
        }
        
        .auth-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .auth-header p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .auth-header .truck {
            color: #0e4c92;
            animation: moveTruck 2s infinite;
        }
        
        @keyframes moveTruck {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(5px); }
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            background: white;
            border: 1px solid rgba(14, 76, 146, 0.1);
            border-radius: 16px;
            font-size: 14px;
            color: #2c3e50;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #0e4c92;
            box-shadow: 0 0 0 3px rgba(14, 76, 146, 0.1);
        }
        
        .auth-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0e4c92, #1a5da0);
            border: none;
            border-radius: 20px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .auth-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(14, 76, 146, 0.3);
        }
        
        .auth-btn.secondary {
            background: white;
            color: #0e4c92;
            border: 2px solid #0e4c92;
        }
        
        .auth-btn.secondary:hover {
            background: #0e4c92;
            color: white;
        }
        
        .error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 4px solid #e74c3c;
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: #bdc3c7;
            font-size: 12px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(14, 76, 146, 0.1);
        }
        
        .demo-credentials {
            background: rgba(14, 76, 146, 0.05);
            border-radius: 16px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
        }
        
        .demo-credentials p {
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .demo-credentials ul {
            list-style: none;
            color: #7f8c8d;
        }
        
        .demo-credentials li {
            margin-bottom: 4px;
        }
        
        .demo-credentials i {
            color: #0e4c92;
            width: 20px;
        }
    </style>
</head>
<body>
    <div class="floating-bg">
        <div class="floating-circle circle-1"></div>
        <div class="floating-circle circle-2"></div>
    </div>
    
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-truck"></i>
                </div>
                <h1>FreightMaster 🚚</h1>
                <p>Login to manage your <span class="truck">shipments</span></p>
            </div>
            
            <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username or Email</label>
                    <input type="text" name="username" required placeholder="Enter your username or email">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                
                <button type="submit" class="auth-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="divider">demo credentials</div>
            
            <div class="demo-credentials">
                <p><i class="fas fa-key"></i> Sample Accounts:</p>
                <ul>
                    <li><i class="fas fa-user-tie"></i> Admin: admin / password123</li>
                    <li><i class="fas fa-truck"></i> Dispatcher: dispatcher1 / password123</li>
                    <li><i class="fas fa-user"></i> Driver: driver1 / password123</li>
                    <li><i class="fas fa-building"></i> Customer: customer1 / password123</li>
                </ul>
            </div>
            
            <div class="auth-footer" style="text-align: center; margin-top: 20px; color: #7f8c8d;">
                <p>Don't have an account? Contact your administrator</p>
            </div>
        </div>
    </div>
</body>
</html>