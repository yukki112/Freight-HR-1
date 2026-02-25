<?php
// modules/settings.php

// Get current user ID
$user_id = $_SESSION['user_id'] ?? 0;

// Get couple information
$couple_info = getCoupleInfo($pdo, $user_id);
$current_user = $couple_info['current_user'];
$partner = $couple_info['partner'];

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get couple relationship details
$couple_details = null;
if ($partner) {
    $stmt = $pdo->prepare("SELECT * FROM couple_relationships WHERE id = ?");
    $stmt->execute([$current_user['couple_id']]);
    $couple_details = $stmt->fetch();
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Update profile information
        if ($_POST['action'] === 'update_profile') {
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $partner_name = trim($_POST['partner_name'] ?? '');
            
            $errors = [];
            
            if (empty($full_name)) {
                $errors[] = 'Full name is required';
            }
            
            if (empty($email)) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
            
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already in use by another account';
            }
            
            if (empty($errors)) {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, partner_name = ? WHERE id = ?");
                if ($stmt->execute([$full_name, $email, $partner_name, $user_id])) {
                    $message = 'Profile updated successfully!';
                    $message_type = 'success';
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                } else {
                    $message = 'Failed to update profile. Please try again.';
                    $message_type = 'error';
                }
            } else {
                $message = implode('<br>', $errors);
                $message_type = 'error';
            }
        }
        
        // Change password
        if ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            $errors = [];
            
            if (empty($current_password)) {
                $errors[] = 'Current password is required';
            }
            
            if (empty($new_password)) {
                $errors[] = 'New password is required';
            } elseif (strlen($new_password) < 6) {
                $errors[] = 'New password must be at least 6 characters';
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = 'New passwords do not match';
            }
            
            if (empty($errors)) {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $hash = $stmt->fetchColumn();
                
                if (password_verify($current_password, $hash)) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$new_hash, $user_id])) {
                        $message = 'Password changed successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to change password. Please try again.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Current password is incorrect';
                    $message_type = 'error';
                }
            } else {
                $message = implode('<br>', $errors);
                $message_type = 'error';
            }
        }
        
        // Update couple settings
        if ($_POST['action'] === 'update_couple') {
            if ($partner) {
                $new_partner_name = trim($_POST['partner_full_name'] ?? '');
                
                if (!empty($new_partner_name)) {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                    if ($stmt->execute([$new_partner_name, $partner['id']])) {
                        $message = 'Partner information updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update partner information.';
                        $message_type = 'error';
                    }
                }
            }
        }
        
        // Generate couple code
        if ($_POST['action'] === 'generate_couple_code') {
            // Generate a unique 8-character code
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            
            // Check if code already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE couple_code = ?");
            $stmt->execute([$code]);
            
            // If code exists, generate a new one
            while ($stmt->fetch()) {
                $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
                $stmt->execute([$code]);
            }
            
            // Update user with new couple code
            $stmt = $pdo->prepare("UPDATE users SET couple_code = ? WHERE id = ?");
            if ($stmt->execute([$code, $user_id])) {
                $message = 'Couple code generated successfully!';
                $message_type = 'success';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $message = 'Failed to generate couple code. Please try again.';
                $message_type = 'error';
            }
        }
        
        // Connect with partner
        if ($_POST['action'] === 'connect_partner') {
            $couple_code = trim($_POST['couple_code'] ?? '');
            
            $errors = [];
            
            if (empty($couple_code)) {
                $errors[] = 'Couple code is required';
            }
            
            if (empty($errors)) {
                // Find user with this couple code
                $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE couple_code = ? AND id != ?");
                $stmt->execute([$couple_code, $user_id]);
                $partner_user = $stmt->fetch();
                
                if ($partner_user) {
                    // Check if both users are not already in a relationship
                    $stmt = $pdo->prepare("
                        SELECT id FROM couple_relationships 
                        WHERE (user1_id = ? OR user2_id = ?) AND status = 'active'
                    ");
                    $stmt->execute([$user_id, $user_id]);
                    if ($stmt->fetch()) {
                        $errors[] = 'You are already in an active relationship';
                    } else {
                        $stmt = $pdo->prepare("
                            SELECT id FROM couple_relationships 
                            WHERE (user1_id = ? OR user2_id = ?) AND status = 'active'
                        ");
                        $stmt->execute([$partner_user['id'], $partner_user['id']]);
                        if ($stmt->fetch()) {
                            $errors[] = 'Your partner is already in an active relationship';
                        }
                    }
                    
                    if (empty($errors)) {
                        // Create couple relationship
                        $stmt = $pdo->prepare("
                            INSERT INTO couple_relationships (user1_id, user2_id, status, connected_date) 
                            VALUES (?, ?, 'active', NOW())
                        ");
                        if ($stmt->execute([$user_id, $partner_user['id']])) {
                            $couple_id = $pdo->lastInsertId();
                            
                            // Update both users with couple_id
                            $stmt = $pdo->prepare("UPDATE users SET couple_id = ?, couple_code = NULL WHERE id = ?");
                            $stmt->execute([$couple_id, $user_id]);
                            $stmt->execute([$couple_id, $partner_user['id']]);
                            
                            $message = 'Successfully connected with ' . htmlspecialchars($partner_user['full_name']) . '!';
                            $message_type = 'success';
                            
                            // Refresh page to show connected state
                            echo '<script>setTimeout(function() { location.reload(); }, 2000);</script>';
                        } else {
                            $errors[] = 'Failed to create relationship. Please try again.';
                        }
                    }
                } else {
                    $errors[] = 'Invalid couple code or code belongs to you';
                }
            }
            
            if (!empty($errors)) {
                $message = implode('<br>', $errors);
                $message_type = 'error';
            }
        }
        
        // Disconnect from partner
        if ($_POST['action'] === 'disconnect_partner') {
            if ($partner) {
                // Start transaction
                $pdo->beginTransaction();
                
                try {
                    // Update users to remove couple_id
                    $stmt = $pdo->prepare("UPDATE users SET couple_id = NULL WHERE id = ? OR id = ?");
                    $stmt->execute([$user_id, $partner['id']]);
                    
                    // Delete couple relationship
                    $stmt = $pdo->prepare("DELETE FROM couple_relationships WHERE id = ?");
                    $stmt->execute([$current_user['couple_id']]);
                    
                    $pdo->commit();
                    
                    $message = 'Successfully disconnected from partner.';
                    $message_type = 'success';
                    
                    // Refresh page
                    echo '<script>setTimeout(function() { location.reload(); }, 2000);</script>';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = 'Failed to disconnect. Please try again.';
                    $message_type = 'error';
                }
            }
        }
    }
}

// Handle profile picture upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_picture'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $message = 'Only JPG, PNG, GIF and WEBP images are allowed';
        $message_type = 'error';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $message = 'File size must be less than 5MB';
        $message_type = 'error';
    } else {
        // Create upload directory if it doesn't exist
        $upload_dir = 'uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Get file extension
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old profile picture if exists
            if ($user['profile_picture'] && file_exists($upload_dir . $user['profile_picture'])) {
                unlink($upload_dir . $user['profile_picture']);
            }
            
            // Update database
            $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            if ($stmt->execute([$filename, $user_id])) {
                $message = 'Profile picture uploaded successfully!';
                $message_type = 'success';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $message = 'Failed to update database.';
                $message_type = 'error';
            }
        } else {
            $message = 'Failed to upload file.';
            $message_type = 'error';
        }
    }
}

// Get profile picture path
$profile_picture = $user['profile_picture'] 
    ? 'uploads/profile_pictures/' . $user['profile_picture'] 
    : 'assets/images/default-avatar.png';

// Get active tab from URL parameter
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
?>

<!-- Settings Header -->
<div class="settings-header">
    <h2><i class="fas fa-cog"></i> Settings</h2>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Settings Tabs -->
<div class="settings-tabs">
    <a href="?page=settings&tab=profile" class="settings-tab <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </a>
    <a href="?page=settings&tab=password" class="settings-tab <?php echo $active_tab === 'password' ? 'active' : ''; ?>">
        <i class="fas fa-lock"></i>
        <span>Password</span>
    </a>
    <a href="?page=settings&tab=couple" class="settings-tab <?php echo $active_tab === 'couple' ? 'active' : ''; ?>">
        <i class="fas fa-heart"></i>
        <span>Couple</span>
    </a>
    <a href="?page=settings&tab=notifications" class="settings-tab <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>">
        <i class="fas fa-bell"></i>
        <span>Notifications</span>
    </a>
    <a href="?page=settings&tab=danger" class="settings-tab <?php echo $active_tab === 'danger' ? 'active' : ''; ?>">
        <i class="fas fa-exclamation-triangle"></i>
        <span>Danger Zone</span>
    </a>
</div>

<!-- Tab Content -->
<div class="settings-container">
    <!-- Profile Tab -->
    <?php if ($active_tab === 'profile'): ?>
    <div class="settings-tab-content active">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="settings-card-title">
                    <h3>Profile Information</h3>
                    <p>Update your personal details</p>
                </div>
            </div>
            
            <div class="settings-card-body">
                <!-- Profile Picture Section -->
                <div class="profile-picture-section">
                    <div class="profile-picture-label">
                        <span>Profile Picture</span>
                    </div>
                    
                    <div class="profile-picture-container">
                        <div class="profile-picture-wrapper">
                            <img src="<?php echo htmlspecialchars($profile_picture); ?>?t=<?php echo time(); ?>" 
                                 alt="Profile Picture" 
                                 id="profilePicturePreview"
                                 class="profile-picture-img">
                        </div>
                        
                        <div class="profile-picture-info">
                            <h4><?php echo htmlspecialchars($user['full_name'] ?? 'Your Name'); ?></h4>
                            <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                            <span class="profile-role"><?php echo ucfirst($user['role'] ?? 'User'); ?></span>
                            
                            <form method="POST" enctype="multipart/form-data" class="profile-upload-form">
                                <label for="profilePictureInput" class="profile-upload-btn">
                                    <i class="fas fa-camera"></i> Change Photo
                                </label>
                                <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*" style="display: none;" onchange="this.form.submit()">
                            </form>
                        </div>
                    </div>
                </div>
                
                <form method="POST" class="settings-form" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" 
                               name="full_name" 
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                               placeholder="Enter your full name"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                               placeholder="Enter your email"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-heart"></i> Partner's Name</label>
                        <input type="text" 
                               name="partner_name" 
                               value="<?php echo htmlspecialchars($user['partner_name'] ?? ''); ?>" 
                               placeholder="Enter your partner's name">
                        <small class="form-text">This is how your partner will be referred to in the app</small>
                    </div>
                    
                    <button type="submit" class="settings-save-btn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Password Tab -->
    <?php if ($active_tab === 'password'): ?>
    <div class="settings-tab-content active">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="settings-card-title">
                    <h3>Change Password</h3>
                    <p>Update your password regularly</p>
                </div>
            </div>
            
            <div class="settings-card-body">
                <form method="POST" class="settings-form" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Current Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   name="current_password" 
                                   id="current_password"
                                   placeholder="Enter current password"
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   name="new_password" 
                                   id="new_password"
                                   placeholder="Enter new password"
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordStrength">
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                            <span class="strength-text">Enter password</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> Confirm New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password"
                                   placeholder="Confirm new password"
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-match" id="passwordMatch"></div>
                    </div>
                    
                    <div class="password-requirements">
                        <p>Password must contain:</p>
                        <ul>
                            <li id="req-length"><i class="fas fa-circle"></i> At least 6 characters</li>
                            <li id="req-number"><i class="fas fa-circle"></i> At least one number</li>
                            <li id="req-letter"><i class="fas fa-circle"></i> At least one letter</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="settings-save-btn" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                        <i class="fas fa-sync-alt"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Couple Tab -->
    <?php if ($active_tab === 'couple'): ?>
    <div class="settings-tab-content active">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="settings-card-title">
                    <h3>Couple Settings</h3>
                    <p>Manage your relationship</p>
                </div>
            </div>
            
            <div class="settings-card-body">
                <?php if ($partner): ?>
                    <!-- Connected couple view -->
                    <div class="couple-connected-card">
                        <div class="couple-avatars">
                            <div class="couple-avatar-small">
                                <img src="<?php echo $user['profile_picture'] ? 'uploads/profile_pictures/' . $user['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                     alt="You">
                            </div>
                            <div class="couple-heart">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="couple-avatar-small">
                                <img src="<?php echo $partner['profile_picture'] ? 'uploads/profile_pictures/' . $partner['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                                     alt="Partner">
                            </div>
                        </div>
                        
                        <div class="couple-connected-info">
                            <h4><?php echo htmlspecialchars($user['full_name'] ?? 'You'); ?> & <?php echo htmlspecialchars($partner['full_name'] ?? 'Partner'); ?></h4>
                            <p>Connected since <?php echo date('F j, Y', strtotime($couple_details['connected_date'] ?? 'now')); ?></p>
                            <span class="couple-status active">
                                <i class="fas fa-check-circle"></i> Active
                            </span>
                        </div>
                        
                        <form method="POST" onsubmit="return confirmDisconnect()" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="disconnect_partner">
                            <button type="submit" class="disconnect-btn">
                                <i class="fas fa-unlink"></i> Disconnect from Partner
                            </button>
                        </form>
                    </div>
                    
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="action" value="update_couple">
                        
                        <div class="form-group">
                            <label><i class="fas fa-user-friends"></i> Partner's Full Name</label>
                            <input type="text" 
                                   name="partner_full_name" 
                                   value="<?php echo htmlspecialchars($partner['full_name'] ?? ''); ?>" 
                                   placeholder="Enter partner's full name">
                            <small class="form-text">Update your partner's display name</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Partner's Email</label>
                            <input type="email" 
                                   value="<?php echo htmlspecialchars($partner['email'] ?? ''); ?>" 
                                   readonly
                                   disabled>
                            <small class="form-text">Email cannot be changed here</small>
                        </div>
                        
                        <button type="submit" class="settings-save-btn" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <i class="fas fa-save"></i> Update Partner Info
                        </button>
                    </form>
                    
                <?php else: ?>
                    <!-- Not connected view with tabs -->
                    <div class="couple-tabs">
                        <button class="couple-tab active" onclick="switchCoupleTab('generate')">
                            <i class="fas fa-key"></i> Generate Code
                        </button>
                        <button class="couple-tab" onclick="switchCoupleTab('connect')">
                            <i class="fas fa-link"></i> Connect
                        </button>
                    </div>
                    
                    <!-- Generate Code Tab -->
                    <div id="generate-tab" class="couple-tab-content active">
                        <div class="couple-not-connected">
                            <div class="not-connected-icon">
                                <i class="fas fa-qrcode"></i>
                            </div>
                            <h4>Generate Your Couple Code</h4>
                            <p>Share this code with your partner to connect</p>
                            
                            <?php if ($user['couple_code']): ?>
                                <div class="couple-code-display">
                                    <span class="code-label">Your Couple Code:</span>
                                    <div class="code-wrapper">
                                        <code class="couple-code"><?php echo htmlspecialchars($user['couple_code']); ?></code>
                                        <button class="copy-code-btn" onclick="copyCoupleCode('<?php echo $user['couple_code']; ?>')">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                    <div class="code-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="generate_couple_code">
                                            <button type="submit" class="generate-new-code-btn">
                                                <i class="fas fa-sync-alt"></i> Generate New Code
                                            </button>
                                        </form>
                                    </div>
                                    <small>Share this code with your partner to connect. They can enter it in the "Connect" tab.</small>
                                </div>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="generate_couple_code">
                                    <button type="submit" class="generate-code-btn">
                                        <i class="fas fa-sync-alt"></i> Generate Couple Code
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Connect Tab -->
                    <div id="connect-tab" class="couple-tab-content">
                        <div class="couple-not-connected">
                            <div class="not-connected-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h4>Connect with Partner</h4>
                            <p>Enter your partner's couple code to connect</p>
                            
                            <form method="POST" class="connect-form" onsubmit="return validateCoupleCode()">
                                <input type="hidden" name="action" value="connect_partner">
                                
                                <div class="form-group">
                                    <label><i class="fas fa-key"></i> Partner's Couple Code</label>
                                    <div class="code-input-wrapper">
                                        <input type="text" 
                                               name="couple_code" 
                                               id="couple_code_input"
                                               class="couple-code-input"
                                               placeholder="Enter 8-digit code"
                                               maxlength="8"
                                               style="text-transform: uppercase;"
                                               required>
                                    </div>
                                    <small class="form-text">Enter the code your partner generated</small>
                                </div>
                                
                                <button type="submit" class="connect-btn">
                                    <i class="fas fa-link"></i> Connect with Partner
                                </button>
                            </form>
                            
                            <div class="connect-info">
                                <i class="fas fa-info-circle"></i>
                                <p>Make sure your partner has generated a code first. Both of you will be connected immediately after entering a valid code.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Notifications Tab -->
    <?php if ($active_tab === 'notifications'): ?>
    <div class="settings-tab-content active">
        <div class="settings-card">
            <div class="settings-card-header">
                <div class="settings-card-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="settings-card-title">
                    <h3>Notifications</h3>
                    <p>Manage your notification preferences</p>
                </div>
            </div>
            
            <div class="settings-card-body">
                <div class="notification-settings">
                    <div class="notification-item">
                        <div class="notification-info">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Email Notifications</h4>
                                <p>Receive updates via email</p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked id="emailNotifications">
                            <span class="slider round"></span>
                        </label>
                    </div>
                    
                    <div class="notification-item">
                        <div class="notification-info">
                            <i class="fas fa-bell"></i>
                            <div>
                                <h4>Push Notifications</h4>
                                <p>Browser notifications</p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="pushNotifications">
                            <span class="slider round"></span>
                        </label>
                    </div>
                    
                    <div class="notification-item">
                        <div class="notification-info">
                            <i class="fas fa-coins"></i>
                            <div>
                                <h4>Budget Alerts</h4>
                                <p>When budget is low</p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked id="budgetAlerts">
                            <span class="slider round"></span>
                        </label>
                    </div>
                    
                    <div class="notification-item">
                        <div class="notification-info">
                            <i class="fas fa-handshake"></i>
                            <div>
                                <h4>Approval Requests</h4>
                                <p>Budget completion requests</p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked id="approvalRequests">
                            <span class="slider round"></span>
                        </label>
                    </div>
                    
                    <div class="notification-item">
                        <div class="notification-info">
                            <i class="fas fa-shopping-cart"></i>
                            <div>
                                <h4>Expense Updates</h4>
                                <p>When partner adds expense</p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked id="expenseUpdates">
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>
                
                <button class="settings-save-btn" onclick="saveNotificationSettings()" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                    <i class="fas fa-save"></i> Save Preferences
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Danger Zone Tab -->
    <?php if ($active_tab === 'danger'): ?>
    <div class="settings-tab-content active">
        <div class="settings-card danger-zone">
            <div class="settings-card-header">
                <div class="settings-card-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="settings-card-title">
                    <h3>Danger Zone</h3>
                    <p>Irreversible actions</p>
                </div>
            </div>
            
            <div class="settings-card-body">
                <div class="danger-actions">
                    <div class="danger-action-item">
                        <div>
                            <h4>Export All Data</h4>
                            <p>Download your expenses and budget history</p>
                        </div>
                        <button class="danger-action-btn export" onclick="exportData()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                    
                    <div class="danger-action-item">
                        <div>
                            <h4>Clear All Expenses</h4>
                            <p>Delete all expense records (cannot be undone)</p>
                        </div>
                        <button class="danger-action-btn clear" onclick="confirmClearExpenses()">
                            <i class="fas fa-trash"></i> Clear
                        </button>
                    </div>
                    
                    <div class="danger-action-item">
                        <div>
                            <h4>Delete Account</h4>
                            <p>Permanently delete your account and all data</p>
                        </div>
                        <button class="danger-action-btn delete" onclick="confirmDeleteAccount()">
                            <i class="fas fa-user-slash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* ========== SETTINGS PAGE STYLES ========== */
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');

.settings-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Settings Header */
.settings-header {
    background: linear-gradient(135deg, #0e4c92, #1a5da0, #2b6cb0);
    padding: 40px 30px;
    border-radius: 30px;
    margin-bottom: 30px;
    box-shadow: 0 20px 40px rgba(14, 76, 146, 0.25);
    position: relative;
    overflow: hidden;
}

.settings-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transform: rotate(25deg);
}

.settings-header::after {
    content: '';
    position: absolute;
    bottom: -50%;
    left: -10%;
    width: 350px;
    height: 350px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 50%;
    transform: rotate(-15deg);
}

.settings-header h2 {
    font-size: 32px;
    color: white;
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    z-index: 1;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    margin: 0;
}

.settings-header h2 i {
    font-size: 40px;
    background: rgba(255, 255, 255, 0.2);
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 20px;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.settings-header p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
    margin-top: 10px;
    position: relative;
    z-index: 1;
    padding-left: 75px;
}

/* Settings Tabs */
.settings-tabs {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    background: white;
    padding: 10px;
    border-radius: 50px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(14, 76, 146, 0.1);
    flex-wrap: wrap;
}

.settings-tab {
    flex: 1;
    min-width: 120px;
    padding: 15px 25px;
    background: transparent;
    border: none;
    border-radius: 40px;
    font-size: 15px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.settings-tab i {
    font-size: 18px;
    transition: all 0.3s;
}

.settings-tab:hover {
    color: #0e4c92;
    background: rgba(14, 76, 146, 0.05);
    transform: translateY(-2px);
}

.settings-tab.active {
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
    color: white;
    box-shadow: 0 15px 25px rgba(14, 76, 146, 0.3);
}

.settings-tab.active i {
    transform: scale(1.1);
}

.settings-container {
    max-width: 900px;
    margin: 0 auto;
}

.settings-tab-content {
    display: none;
    animation: fadeIn 0.5s ease;
}

.settings-tab-content.active {
    display: block;
}

/* Settings Card */
.settings-card {
    background: white;
    border-radius: 40px;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(14, 76, 146, 0.08);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.settings-card:hover {
    box-shadow: 0 35px 60px -15px rgba(14, 76, 146, 0.25);
    transform: translateY(-5px);
}

.settings-card.danger-zone {
    border: 2px solid rgba(231, 76, 60, 0.2);
    background: linear-gradient(135deg, white, #fff5f5);
}

.settings-card.danger-zone:hover {
    box-shadow: 0 35px 60px -15px rgba(231, 76, 60, 0.25);
}

.settings-card-header {
    padding: 30px 30px;
    background: linear-gradient(135deg, #f8fafd, #ffffff);
    border-bottom: 1px solid rgba(14, 76, 146, 0.08);
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
    overflow: hidden;
}

.settings-card-header::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(14, 76, 146, 0.03) 0%, transparent 70%);
    border-radius: 50%;
}

.settings-card-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #0e4c92, #1a5da0, #2b6cb0);
    border-radius: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    box-shadow: 0 15px 30px rgba(14, 76, 146, 0.3);
    position: relative;
    z-index: 1;
    transition: all 0.3s;
}

.settings-card:hover .settings-card-icon {
    transform: scale(1.05) rotate(5deg);
}

.settings-card-title h3 {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 5px;
    letter-spacing: -0.5px;
}

.settings-card-title p {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

.settings-card-body {
    padding: 35px;
}

/* Profile Picture Section */
.profile-picture-section {
    margin-bottom: 35px;
    padding-bottom: 25px;
    border-bottom: 2px dashed rgba(14, 76, 146, 0.1);
}

.profile-picture-label {
    margin-bottom: 20px;
}

.profile-picture-label span {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    text-transform: uppercase;
    letter-spacing: 1px;
    background: rgba(14, 76, 146, 0.05);
    padding: 5px 15px;
    border-radius: 30px;
}

.profile-picture-container {
    display: flex;
    align-items: center;
    gap: 35px;
    flex-wrap: wrap;
}

.profile-picture-wrapper {
    width: 130px;
    height: 130px;
    border-radius: 35px;
    overflow: hidden;
    border: 5px solid white;
    box-shadow: 0 20px 40px rgba(14, 76, 146, 0.25);
    flex-shrink: 0;
    position: relative;
    cursor: pointer;
    transition: all 0.3s;
}

.profile-picture-wrapper:hover {
    transform: scale(1.05);
    box-shadow: 0 25px 50px rgba(14, 76, 146, 0.35);
}

.profile-picture-wrapper:hover::after {
    content: '\f030';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(14, 76, 146, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 30px;
    backdrop-filter: blur(3px);
}

.profile-picture-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-picture-info h4 {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 5px;
    letter-spacing: -0.5px;
}

.profile-picture-info p {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 10px;
}

.profile-role {
    display: inline-block;
    font-size: 12px;
    padding: 6px 18px;
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
    color: white;
    border-radius: 30px;
    font-weight: 600;
    margin-bottom: 15px;
    box-shadow: 0 5px 15px rgba(14, 76, 146, 0.2);
}

.profile-upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 25px;
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
    color: white;
    border-radius: 40px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.4s;
    border: none;
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.2);
}

.profile-upload-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(14, 76, 146, 0.35);
    background: linear-gradient(135deg, #1a5da0, #2b6cb0);
}

/* Settings Form */
.settings-form {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.settings-form .form-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.settings-form .form-group label {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.settings-form .form-group label i {
    color: #0e4c92;
    font-size: 14px;
    width: 20px;
}

.settings-form .form-group input {
    padding: 15px 20px;
    background: #f8fafc;
    border: 2px solid rgba(14, 76, 146, 0.1);
    border-radius: 25px;
    font-size: 14px;
    color: #1e293b;
    transition: all 0.3s;
}

.settings-form .form-group input:focus {
    outline: none;
    border-color: #0e4c92;
    background: white;
    box-shadow: 0 10px 30px rgba(14, 76, 146, 0.1);
}

.settings-form .form-group input[readonly],
.settings-form .form-group input:disabled {
    background: #f1f5f9;
    border-color: rgba(14, 76, 146, 0.05);
    cursor: not-allowed;
}

.form-text {
    font-size: 12px;
    color: #64748b;
    margin-top: 5px;
    padding-left: 10px;
}

/* Password Input */
.password-input-wrapper {
    position: relative;
}

.password-input-wrapper input {
    width: 100%;
    padding-right: 55px !important;
}

.password-toggle {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    width: 35px;
    height: 35px;
    background: rgba(14, 76, 146, 0.1);
    border: none;
    border-radius: 12px;
    color: #0e4c92;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.password-toggle:hover {
    background: #0e4c92;
    color: white;
    transform: translateY(-50%) scale(1.1);
}

/* Password Strength */
.password-strength {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding: 12px 18px;
    background: #f8fafc;
    border-radius: 20px;
    border: 1px solid rgba(14, 76, 146, 0.08);
}

.strength-bar {
    flex: 1;
    height: 6px;
    background: #e2e8f0;
    border-radius: 10px;
    transition: all 0.3s;
    overflow: hidden;
    position: relative;
}

.strength-bar.active::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    animation: loading 1s infinite;
}

@keyframes loading {
    0% { background: rgba(255, 255, 255, 0.2); }
    50% { background: rgba(255, 255, 255, 0.4); }
    100% { background: rgba(255, 255, 255, 0.2); }
}

.strength-bar.weak {
    background: #ef4444;
}

.strength-bar.medium {
    background: #f59e0b;
}

.strength-bar.strong {
    background: #10b981;
}

.strength-text {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    margin-left: 10px;
}

.password-match {
    font-size: 12px;
    margin-top: 8px;
    padding: 8px 15px;
    border-radius: 30px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}

.password-match.match {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.password-match.no-match {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

/* Password Requirements */
.password-requirements {
    background: #f8fafc;
    border-radius: 25px;
    padding: 20px;
    border: 1px solid rgba(14, 76, 146, 0.08);
}

.password-requirements p {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.password-requirements p i {
    color: #0e4c92;
}

.password-requirements ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
}

.password-requirements li {
    font-size: 12px;
    color: #64748b;
    padding: 8px 15px;
    background: white;
    border-radius: 30px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
}

.password-requirements li i {
    font-size: 8px;
    color: #94a3b8;
    transition: all 0.3s;
}

.password-requirements li.valid {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.password-requirements li.valid i {
    color: #10b981;
    transform: scale(1.2);
}

/* Settings Save Button */
.settings-save-btn {
    padding: 18px 30px;
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
    border: none;
    border-radius: 40px;
    color: white;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.4s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
    box-shadow: 0 15px 30px rgba(14, 76, 146, 0.2);
    position: relative;
    overflow: hidden;
}

.settings-save-btn::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: rgba(255, 255, 255, 0.1);
    transform: rotate(45deg) translateX(-100%);
    transition: transform 0.6s;
}

.settings-save-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 40px rgba(14, 76, 146, 0.3);
}

.settings-save-btn:hover::after {
    transform: rotate(45deg) translateX(100%);
}

/* Couple Connected Card */
.couple-connected-card {
    background: linear-gradient(135deg, #f8fafc, white);
    border-radius: 35px;
    padding: 30px;
    margin-bottom: 30px;
    text-align: center;
    border: 1px solid rgba(14, 76, 146, 0.1);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
}

.couple-avatars {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 25px;
    margin-bottom: 20px;
}

.couple-avatar-small {
    width: 90px;
    height: 90px;
    border-radius: 30px;
    overflow: hidden;
    border: 5px solid white;
    box-shadow: 0 15px 35px rgba(14, 76, 146, 0.2);
    transition: all 0.3s;
}

.couple-avatar-small:hover {
    transform: scale(1.1) rotate(5deg);
}

.couple-heart {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #ff6b6b, #ee5253);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    animation: heartbeat 2s infinite;
    box-shadow: 0 10px 25px rgba(238, 82, 83, 0.3);
}

@keyframes heartbeat {
    0%, 100% { transform: scale(1); }
    25% { transform: scale(1.2); }
    35% { transform: scale(1.1); }
    45% { transform: scale(1.2); }
}

.couple-connected-info h4 {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
}

.couple-connected-info p {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 15px;
}

.couple-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 600;
}

.disconnect-btn {
    padding: 12px 30px;
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid rgba(239, 68, 68, 0.2);
    border-radius: 40px;
    color: #ef4444;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.4s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.disconnect-btn:hover {
    background: #ef4444;
    color: white;
    border-color: #ef4444;
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(239, 68, 68, 0.3);
}

/* Couple Tabs */
.couple-tabs {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    background: #f8fafc;
    padding: 8px;
    border-radius: 50px;
    border: 1px solid rgba(14, 76, 146, 0.08);
}

.couple-tab {
    flex: 1;
    padding: 14px 20px;
    background: transparent;
    border: none;
    border-radius: 40px;
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.4s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.couple-tab i {
    font-size: 16px;
}

.couple-tab.active {
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
    color: white;
    box-shadow: 0 15px 25px rgba(14, 76, 146, 0.25);
    transform: translateY(-2px);
}

.couple-tab-content {
    display: none;
    animation: slideUp 0.5s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.couple-tab-content.active {
    display: block;
}

/* Couple Not Connected */
.couple-not-connected {
    text-align: center;
    padding: 40px 20px;
}

.not-connected-icon {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, rgba(14, 76, 146, 0.1), rgba(26, 93, 160, 0.1));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30px;
    font-size: 48px;
    color: #0e4c92;
    border: 3px dashed rgba(14, 76, 146, 0.2);
    animation: rotate 10s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.couple-not-connected h4 {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 10px;
}

.couple-not-connected p {
    font-size: 15px;
    color: #64748b;
    margin-bottom: 35px;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Couple Code Display */
.couple-code-display {
    background: linear-gradient(135deg, #f8fafc, white);
    border-radius: 35px;
    padding: 35px;
    text-align: left;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(14, 76, 146, 0.1);
}

.code-label {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 15px;
    display: block;
}

.code-wrapper {
    display: flex;
    align-items: center;
    gap: 20px;
    background: white;
    border-radius: 25px;
    padding: 20px 25px;
    border: 2px dashed rgba(14, 76, 146, 0.2);
    margin-bottom: 25px;
    transition: all 0.3s;
}

.code-wrapper:hover {
    border-color: #0e4c92;
    background: rgba(14, 76, 146, 0.02);
}

.couple-code {
    flex: 1;
    font-size: 36px;
    font-weight: 800;
    color: #0e4c92;
    letter-spacing: 8px;
    text-align: center;
    font-family: 'Courier New', monospace;
    text-shadow: 2px 2px 4px rgba(14, 76, 146, 0.1);
}

.copy-code-btn {
    padding: 12px 25px;
    background: #0e4c92;
    border: none;
    border-radius: 20px;
    color: white;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.4s;
    white-space: nowrap;
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.2);
}

.copy-code-btn:hover {
    background: #1a5da0;
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 20px 30px rgba(14, 76, 146, 0.3);
}

.copy-code-btn:active {
    transform: translateY(0) scale(1);
}

.code-actions {
    text-align: center;
    margin-bottom: 15px;
}

.generate-new-code-btn {
    padding: 12px 30px;
    background: transparent;
    border: 2px solid #0e4c92;
    border-radius: 40px;
    color: #0e4c92;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.4s;
}

.generate-new-code-btn:hover {
    background: #0e4c92;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(14, 76, 146, 0.2);
}

.generate-code-btn {
    padding: 16px 35px;
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
    border: none;
    border-radius: 50px;
    color: white;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    transition: all 0.4s;
    box-shadow: 0 20px 35px rgba(14, 76, 146, 0.25);
}

.generate-code-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 30px 45px rgba(14, 76, 146, 0.35);
}

/* Connect Form */
.connect-form {
    max-width: 400px;
    margin: 0 auto;
}

.couple-code-input {
    width: 100%;
    padding: 18px 25px;
    font-size: 28px;
    text-align: center;
    letter-spacing: 8px;
    background: white;
    border: 3px solid rgba(14, 76, 146, 0.1);
    border-radius: 30px;
    color: #0e4c92;
    font-weight: 700;
    font-family: 'Courier New', monospace;
    transition: all 0.3s;
}

.couple-code-input:focus {
    outline: none;
    border-color: #0e4c92;
    box-shadow: 0 15px 35px rgba(14, 76, 146, 0.15);
    transform: scale(1.02);
}

.connect-btn {
    width: 100%;
    padding: 18px 30px;
    background: linear-gradient(135deg, #10b981, #059669);
    border: none;
    border-radius: 40px;
    color: white;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    transition: all 0.4s;
    box-shadow: 0 15px 30px rgba(16, 185, 129, 0.2);
}

.connect-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 40px rgba(16, 185, 129, 0.3);
}

.connect-info {
    margin-top: 30px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 25px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    text-align: left;
    border-left: 5px solid #0e4c92;
}

.connect-info i {
    color: #0e4c92;
    font-size: 20px;
    background: white;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.connect-info p {
    font-size: 13px;
    color: #475569;
    margin: 0;
    line-height: 1.6;
}

/* Notification Settings */
.notification-settings {
    margin-bottom: 30px;
}

.notification-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    background: #f8fafc;
    border-radius: 25px;
    margin-bottom: 10px;
    transition: all 0.3s;
    border: 1px solid transparent;
}

.notification-item:hover {
    background: white;
    border-color: rgba(14, 76, 146, 0.1);
    transform: translateX(5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
}

.notification-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.notification-info i {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    box-shadow: 0 10px 20px rgba(14, 76, 146, 0.15);
}

.notification-info h4 {
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 3px;
}

.notification-info p {
    font-size: 12px;
    color: #64748b;
}

/* Toggle Switch - Modern */
.switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 32px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #cbd5e1;
    transition: .4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: .4s;
    border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

input:checked + .slider {
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
}

input:checked + .slider:before {
    transform: translateX(28px);
}

input:focus + .slider {
    box-shadow: 0 0 0 3px rgba(14, 76, 146, 0.2);
}

/* Danger Zone */
.danger-actions {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.danger-action-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 25px;
    background: #fff5f5;
    border-radius: 30px;
    border: 2px solid rgba(239, 68, 68, 0.1);
    transition: all 0.4s;
    position: relative;
    overflow: hidden;
}

.danger-action-item::before {
    content: '⚠️';
    position: absolute;
    right: -20px;
    top: -20px;
    font-size: 80px;
    opacity: 0.1;
    transform: rotate(15deg);
}

.danger-action-item:hover {
    border-color: #ef4444;
    background: #fee2e2;
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(239, 68, 68, 0.1);
}

.danger-action-item h4 {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 5px;
}

.danger-action-item p {
    font-size: 13px;
    color: #64748b;
}

.danger-action-btn {
    padding: 12px 25px;
    border: none;
    border-radius: 25px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.4s;
    white-space: nowrap;
    z-index: 1;
    position: relative;
}

.danger-action-btn.export {
    background: #e2e8f0;
    color: #334155;
}

.danger-action-btn.export:hover {
    background: #cbd5e1;
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.danger-action-btn.clear {
    background: #fed7aa;
    color: #92400e;
}

.danger-action-btn.clear:hover {
    background: #fbbf24;
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(251, 191, 36, 0.2);
}

.danger-action-btn.delete {
    background: #fecaca;
    color: #b91c1c;
}

.danger-action-btn.delete:hover {
    background: #ef4444;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
}

/* Alert Messages */
.alert {
    padding: 18px 25px;
    border-radius: 25px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 14px;
    font-weight: 500;
    animation: slideDown 0.5s ease;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert i {
    font-size: 20px;
}

.alert-success {
    background: #d1fae5;
    border-left: 6px solid #10b981;
    color: #065f46;
}

.alert-danger {
    background: #fee2e2;
    border-left: 6px solid #ef4444;
    color: #991b1b;
}

/* Responsive */
@media (max-width: 1024px) {
    .settings-wrapper {
        padding: 15px;
    }
}

@media (max-width: 768px) {
    .settings-tabs {
        flex-direction: column;
        border-radius: 30px;
    }
    
    .settings-tab {
        width: 100%;
        justify-content: center;
    }
    
    .settings-card-body {
        padding: 25px;
    }
    
    .profile-picture-container {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .danger-action-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
    }
    
    .danger-action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .code-wrapper {
        flex-direction: column;
    }
    
    .copy-code-btn {
        width: 100%;
        justify-content: center;
    }
    
    .couple-code {
        font-size: 28px;
        letter-spacing: 4px;
    }
}

@media (max-width: 480px) {
    .settings-header {
        padding: 30px 20px;
    }
    
    .settings-header h2 {
        font-size: 26px;
    }
    
    .settings-card-header {
        flex-direction: column;
        text-align: center;
    }
    
    .notification-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
    }
    
    .notification-info {
        width: 100%;
    }
    
    .switch {
        align-self: flex-start;
    }
    
    .couple-tabs {
        flex-direction: column;
    }
    
    .couple-avatars {
        flex-direction: column;
    }
    
    .couple-heart {
        transform: rotate(90deg);
    }
    
    .couple-code {
        font-size: 24px;
        letter-spacing: 2px;
    }
}

/* Animation Keyframes */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 12px;
}

::-webkit-scrollbar-track {
    background: #f1f5f9;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #0e4c92, #1a5da0);
    border-radius: 10px;
    border: 3px solid #f1f5f9;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #1a5da0, #2b6cb0);
}

/* Loading Spinner */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Print Styles */
@media print {
    .settings-tabs,
    .settings-save-btn,
    .disconnect-btn,
    .copy-code-btn,
    .generate-code-btn,
    .connect-btn {
        display: none !important;
    }
}
</style>

<script>
// ========== PASSWORD FUNCTIONALITY ==========

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Check password strength
document.getElementById('new_password')?.addEventListener('input', function() {
    const password = this.value;
    const strengthBars = document.querySelectorAll('.strength-bar');
    const strengthText = document.querySelector('.strength-text');
    
    // Check requirements
    const hasLength = password.length >= 6;
    const hasNumber = /\d/.test(password);
    const hasLetter = /[a-zA-Z]/.test(password);
    
    // Update requirement indicators
    if (document.getElementById('req-length')) {
        document.getElementById('req-length').className = hasLength ? 'valid' : '';
    }
    if (document.getElementById('req-number')) {
        document.getElementById('req-number').className = hasNumber ? 'valid' : '';
    }
    if (document.getElementById('req-letter')) {
        document.getElementById('req-letter').className = hasLetter ? 'valid' : '';
    }
    
    // Calculate strength
    let strength = 0;
    if (hasLength) strength++;
    if (hasNumber) strength++;
    if (hasLetter) strength++;
    if (password.length >= 8 && /[!@#$%^&*]/.test(password)) strength++;
    
    // Update bars
    strengthBars.forEach((bar, index) => {
        bar.classList.remove('active', 'weak', 'medium', 'strong');
        if (index < strength) {
            bar.classList.add('active');
            if (strength <= 2) {
                bar.classList.add('weak');
            } else if (strength === 3) {
                bar.classList.add('medium');
            } else {
                bar.classList.add('strong');
            }
        }
    });
    
    // Update text
    const texts = ['Weak', 'Fair', 'Good', 'Strong'];
    strengthText.textContent = strength > 0 ? texts[strength - 1] : 'Enter password';
});

// Check password match
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
    const confirm = this.value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirm.length > 0) {
        if (password === confirm) {
            matchDiv.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            matchDiv.className = 'password-match match';
        } else {
            matchDiv.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
            matchDiv.className = 'password-match no-match';
        }
    } else {
        matchDiv.innerHTML = '';
    }
});

// ========== COUPLE CODE FUNCTIONALITY ==========

// Switch between tabs
function switchCoupleTab(tab) {
    // Update tab buttons
    document.querySelectorAll('.couple-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.couple-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(tab + '-tab').classList.add('active');
}

// Copy couple code
function copyCoupleCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        showNotification('Couple code copied to clipboard!', 'success');
    }).catch(() => {
        showNotification('Failed to copy code', 'error');
    });
}

// Validate couple code before submission
function validateCoupleCode() {
    const code = document.getElementById('couple_code_input').value.trim().toUpperCase();
    
    if (code.length !== 8) {
        showNotification('Couple code must be 8 characters', 'error');
        return false;
    }
    
    if (!/^[A-Z0-9]+$/.test(code)) {
        showNotification('Couple code can only contain letters and numbers', 'error');
        return false;
    }
    
    return true;
}

// Auto-uppercase couple code input
document.getElementById('couple_code_input')?.addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
});

// Confirm disconnect
function confirmDisconnect() {
    return confirm('Are you sure you want to disconnect from your partner? This will remove your connection and all shared budgets will be archived.');
}

// ========== NOTIFICATION SETTINGS ==========

function saveNotificationSettings() {
    const settings = {
        email: document.getElementById('emailNotifications')?.checked || false,
        push: document.getElementById('pushNotifications')?.checked || false,
        budget: document.getElementById('budgetAlerts')?.checked || false,
        approval: document.getElementById('approvalRequests')?.checked || false,
        expense: document.getElementById('expenseUpdates')?.checked || false
    };
    
    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;
    
    // Simulate API call (replace with actual API)
    setTimeout(() => {
        showNotification('Notification preferences saved!', 'success');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }, 1000);
}

// ========== DANGER ZONE FUNCTIONS ==========

function exportData() {
    window.location.href = '../api/export_data.php';
}

function confirmClearExpenses() {
    if (confirm('⚠️ WARNING: This will permanently delete ALL expense records. This action cannot be undone. Are you absolutely sure?')) {
        if (prompt('Type "DELETE" to confirm:') === 'DELETE') {
            showNotification('This feature is coming soon', 'warning');
        }
    }
}

function confirmDeleteAccount() {
    if (confirm('⚠️ WARNING: This will permanently delete your account and ALL associated data. This action cannot be undone. Are you absolutely sure?')) {
        if (prompt('Type "DELETE ACCOUNT" to confirm:') === 'DELETE ACCOUNT') {
            showNotification('This feature is coming soon', 'warning');
        }
    }
}

// ========== NOTIFICATION FUNCTION ==========
function showNotification(message, type = 'info') {
    // Remove any existing notifications
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notif => notif.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification-toast`;
    
    // Set icon based on type
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    if (type === 'warning') icon = 'exclamation-triangle';
    
    notification.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
    `;
    
    // Style the notification
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.style.maxWidth = '90%';
    notification.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
    notification.style.animation = 'slideInRight 0.3s ease';
    notification.style.wordBreak = 'break-word';
    
    // Add to body
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add animation styles if they don't exist
if (!document.getElementById('notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}

console.log('Settings page JavaScript loaded successfully');
</script>