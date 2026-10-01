<?php
// register.php
require_once 'includes/config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

// Define roles for dropdown
$roles = [
    'admin' => 'Administrator',
    'dispatcher' => 'Dispatcher',
    'driver' => 'Driver',
    'customer' => 'Customer'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'customer';
    $company_name = $_POST['company_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($full_name) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Username or email already exists';
        } else {
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, company_name, role, phone, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([$username, $email, $hashed_password, $full_name, $company_name, $role, $phone]);
                
                // Get the new user ID
                $user_id = $pdo->lastInsertId();
                
                // If role is driver, create driver record
                if ($role == 'driver') {
                    $license_number = $_POST['license_number'] ?? 'TMP-' . strtoupper(substr(uniqid(), -6));
                    $license_expiry = $_POST['license_expiry'] ?? date('Y-m-d', strtotime('+1 year'));
                    $emergency_contact = $_POST['emergency_contact'] ?? '';
                    $emergency_phone = $_POST['emergency_phone'] ?? '';
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO drivers (user_id, license_number, license_expiry, phone, address, emergency_contact, emergency_phone, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'available')
                    ");
                    
                    $address = $_POST['address'] ?? '';
                    $stmt->execute([$user_id, $license_number, $license_expiry, $phone, $address, $emergency_contact, $emergency_phone]);
                }
                
                // If role is customer, create customer record
                if ($role == 'customer') {
                    $contact_person = $_POST['contact_person'] ?? $full_name;
                    $tax_id = $_POST['tax_id'] ?? '';
                    $address = $_POST['address'] ?? '';
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO customers (user_id, company_name, contact_person, email, phone, address, tax_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([$user_id, $company_name, $contact_person, $email, $phone, $address, $tax_id]);
                }
                
                // Create welcome notification
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type) 
                    VALUES (?, 'Welcome to FreightMaster!', 'Your account has been created successfully. Please complete your profile.', 'success')
                ");
                $stmt->execute([$user_id]);
                
                // Log activity
                logActivity($pdo, $user_id, 'register', "New user registered as $role");
                
                $pdo->commit();
                
                $success = 'Registration successful! You can now login.';
                
                // Redirect to login after 2 seconds
                header("refresh:2;url=login.php");
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FreightMaster - Register</title>
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
            max-width: 600px;
            padding: 20px;
            max-height: 90vh;
            overflow-y: auto;
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
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 15px;
            background: white;
            border: 1px solid rgba(14, 76, 146, 0.1);
            border-radius: 16px;
            font-size: 14px;
            color: #2c3e50;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0e4c92;
            box-shadow: 0 0 0 3px rgba(14, 76, 146, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
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
        
        .success {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 4px solid #27ae60;
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 25px;
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .auth-footer a {
            color: #0e4c92;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .auth-footer a:hover {
            text-decoration: underline;
        }
        
        .password-hint {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 5px;
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
        
        .role-badge {
            background: linear-gradient(135deg, #0e4c92, #1a5da0);
            color: white;
            padding: 10px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 13px;
        }
        
        .driver-fields,
        .customer-fields {
            display: none;
            padding: 15px;
            background: rgba(14, 76, 146, 0.05);
            border-radius: 16px;
            margin-bottom: 15px;
        }
        
        .driver-fields.visible,
        .customer-fields.visible {
            display: block;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #0e4c92;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .note {
            background: rgba(14, 76, 146, 0.1);
            border-radius: 12px;
            padding: 10px;
            font-size: 12px;
            color: #0e4c92;
            margin-bottom: 15px;
        }
        
        .note i {
            margin-right: 5px;
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
                <h1>Create Account 🚚</h1>
                <p>Register new member for <span class="truck">FreightMaster</span></p>
            </div>
            
            <div class="note">
                <i class="fas fa-info-circle"></i>
                This is a dummy registration page. All fields marked with * are required.
            </div>
            
            <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registrationForm">
                <!-- Basic Information -->
                <div class="section-title">
                    <i class="fas fa-user"></i> Basic Information
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name *</label>
                    <input type="text" name="full_name" required placeholder="e.g., Juan Dela Cruz" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Username *</label>
                        <input type="text" name="username" required placeholder="Choose username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" name="email" required placeholder="your@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password *</label>
                        <input type="password" name="password" required placeholder="Min. 6 characters">
                        <div class="password-hint">At least 6 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm Password *</label>
                        <input type="password" name="confirm_password" required placeholder="Re-enter password">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Role *</label>
                        <select name="role" id="roleSelect" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo (isset($_POST['role']) && $_POST['role'] == $value) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="text" name="phone" placeholder="e.g., 09123456789" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Company Name</label>
                    <input type="text" name="company_name" placeholder="e.g., ABC Logistics" value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">
                </div>
                
                <!-- Driver Specific Fields -->
                <div class="driver-fields <?php echo (isset($_POST['role']) && $_POST['role'] == 'driver') ? 'visible' : ''; ?>" id="driverFields">
                    <div class="section-title">
                        <i class="fas fa-id-card"></i> Driver Information
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> License Number</label>
                        <input type="text" name="license_number" placeholder="e.g., D12-34-567890" value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>">
                        <div class="password-hint">Leave empty for auto-generated temporary license</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> License Expiry</label>
                            <input type="date" name="license_expiry" value="<?php echo isset($_POST['license_expiry']) ? htmlspecialchars($_POST['license_expiry']) : date('Y-m-d', strtotime('+1 year')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <input type="text" name="address" placeholder="Current address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Emergency Contact</label>
                            <input type="text" name="emergency_contact" placeholder="Full name" value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Emergency Phone</label>
                            <input type="text" name="emergency_phone" placeholder="Contact number" value="<?php echo isset($_POST['emergency_phone']) ? htmlspecialchars($_POST['emergency_phone']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Customer Specific Fields -->
                <div class="customer-fields <?php echo (isset($_POST['role']) && $_POST['role'] == 'customer') ? 'visible' : ''; ?>" id="customerFields">
                    <div class="section-title">
                        <i class="fas fa-building"></i> Customer Information
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user-tie"></i> Contact Person</label>
                            <input type="text" name="contact_person" placeholder="Primary contact" value="<?php echo isset($_POST['contact_person']) ? htmlspecialchars($_POST['contact_person']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-barcode"></i> Tax ID</label>
                            <input type="text" name="tax_id" placeholder="Tax identification" value="<?php echo isset($_POST['tax_id']) ? htmlspecialchars($_POST['tax_id']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Business Address</label>
                        <input type="text" name="address" placeholder="Full address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                    </div>
                </div>
                
                <button type="submit" class="auth-btn">
                    <i class="fas fa-user-plus"></i> Register Member
                </button>
            </form>
            
            <div class="divider">already have an account?</div>
            
            <a href="login.php">
                <button class="auth-btn secondary">
                    <i class="fas fa-sign-in-alt"></i> Login Instead
                </button>
            </a>
            
            <div class="auth-footer">
                <p>By registering, you agree to the terms of service</p>
            </div>
        </div>
    </div>
    
    <script>
        // Show/hide fields based on role selection
        document.getElementById('roleSelect').addEventListener('change', function() {
            const role = this.value;
            const driverFields = document.getElementById('driverFields');
            const customerFields = document.getElementById('customerFields');
            
            driverFields.classList.remove('visible');
            customerFields.classList.remove('visible');
            
            if (role === 'driver') {
                driverFields.classList.add('visible');
            } else if (role === 'customer') {
                customerFields.classList.add('visible');
            }
        });
        
        // Auto-generate username from email (optional feature)
        document.querySelector('input[name="email"]').addEventListener('blur', function() {
            const usernameField = document.querySelector('input[name="username"]');
            if (!usernameField.value && this.value) {
                usernameField.value = this.value.split('@')[0];
            }
        });
    </script>
</body>
</html>