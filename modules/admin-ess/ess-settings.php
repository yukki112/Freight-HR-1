<?php
// /modules/admin-ess/ess-settings.php
$message = '';
$error = '';

// ---- SAVE SETTINGS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $settingsFile = __DIR__ . '/../../config/ess_settings.json';

        $settings = [
            'temp_password_expiry_days' => (int)($_POST['temp_password_expiry_days'] ?? 7),
            'max_login_attempts'        => (int)($_POST['max_login_attempts'] ?? 5),
            'lockout_minutes'           => (int)($_POST['lockout_minutes'] ?? 30),
            'session_lifetime_hours'    => (int)($_POST['session_lifetime_hours'] ?? 8),
            'allow_profile_edit'        => isset($_POST['allow_profile_edit']) ? 1 : 0,
            'allow_leave_requests'      => isset($_POST['allow_leave_requests']) ? 1 : 0,
            'allow_overtime_requests'   => isset($_POST['allow_overtime_requests']) ? 1 : 0,
            'allow_document_requests'   => isset($_POST['allow_document_requests']) ? 1 : 0,
            'ess_enabled'               => isset($_POST['ess_enabled']) ? 1 : 0,
            'welcome_message'           => trim($_POST['welcome_message'] ?? 'Welcome to your Employee Self-Service portal!'),
            'updated_at'                => date('Y-m-d H:i:s'),
            'updated_by'                => $_SESSION['user_id'] ?? null,
        ];

        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
        $message = 'ESS settings saved successfully.';
    } catch (Exception $e) {
        $error = 'Error saving settings: ' . $e->getMessage();
    }
}

// ---- LOAD ----
$settingsFile = __DIR__ . '/../../config/ess_settings.json';
$defaults = [
    'temp_password_expiry_days' => 7,
    'max_login_attempts'        => 5,
    'lockout_minutes'           => 30,
    'session_lifetime_hours'    => 8,
    'allow_profile_edit'        => 1,
    'allow_leave_requests'      => 1,
    'allow_overtime_requests'   => 1,
    'allow_document_requests'   => 1,
    'ess_enabled'               => 1,
    'welcome_message'           => 'Welcome to your Employee Self-Service portal!',
];
$settings = $defaults;
if (file_exists($settingsFile)) {
    $saved = json_decode(file_get_contents($settingsFile), true);
    if (is_array($saved)) $settings = array_merge($defaults, $saved);
}

// ---- STATS ----
$totalAccounts   = (int)$pdo->query("SELECT COUNT(*) FROM ess_accounts")->fetchColumn();
$activeAccounts  = (int)$pdo->query("SELECT COUNT(*) FROM ess_accounts WHERE is_active = 1")->fetchColumn();
$pendingSetup    = (int)$pdo->query("SELECT COUNT(*) FROM ess_accounts WHERE must_change_password = 1")->fetchColumn();
$tempExpired     = (int)$pdo->query("SELECT COUNT(*) FROM ess_accounts WHERE must_change_password = 1 AND temp_password_expires_at < NOW()")->fetchColumn();
$totalRequests   = (int)$pdo->query("SELECT COUNT(*) FROM ess_requests")->fetchColumn();
$pendingRequests = (int)$pdo->query("SELECT COUNT(*) FROM ess_requests WHERE status = 'pending'")->fetchColumn();
$pendingDocs     = (int)$pdo->query("SELECT COUNT(*) FROM ess_document_requests WHERE status = 'pending'")->fetchColumn();
$totalAnnounce   = (int)$pdo->query("SELECT COUNT(*) FROM ess_announcements WHERE is_published = 1")->fetchColumn();
?>

<style>
/* ==========================================================
   ESS SETTINGS — REDESIGNED
   ========================================================== */
.eset-header {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 24px; padding: 32px 36px; margin-bottom: 25px;
    color: white; position: relative; overflow: hidden;
    box-shadow: 0 20px 40px rgba(14,76,146,0.2);
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 20px;
}
.eset-header::before {
    content:''; position:absolute; width:240px; height:240px;
    background: rgba(255,255,255,0.08); border-radius:50%;
    top:-100px; right:-60px;
}
.eset-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.eset-header .hdr-icon {
    width:60px; height:60px; background: rgba(255,255,255,0.18);
    border:1.5px solid rgba(255,255,255,0.3); border-radius:18px;
    display:flex; align-items:center; justify-content:center;
    font-size:26px; backdrop-filter: blur(10px);
}
.eset-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; letter-spacing:-0.3px; }
.eset-header p  { font-size:13px; margin:0; opacity:0.85; }

/* Stats */
.eset-stats {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
    gap:14px; margin-bottom:22px;
}
.eset-stat {
    background:white; border-radius:18px; padding:18px 20px;
    display:flex; align-items:center; gap:14px;
    box-shadow:0 6px 20px rgba(0,0,0,0.04);
    border:1px solid #eef2f6; transition:all 0.3s;
}
.eset-stat:hover { transform:translateY(-3px); box-shadow:0 12px 28px rgba(14,76,146,0.08); }
.eset-stat .ico {
    width:44px; height:44px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; flex-shrink:0;
}
.eset-stat .ico.blue   { background:rgba(14,76,146,0.1);  color:#0e4c92; }
.eset-stat .ico.green  { background:rgba(22,163,74,0.12); color:#16a34a; }
.eset-stat .ico.amber  { background:rgba(245,158,11,0.12);color:#d97706; }
.eset-stat .ico.red    { background:rgba(220,38,38,0.12); color:#dc2626; }
.eset-stat .ico.purple { background:rgba(139,92,246,0.12);color:#7c3aed; }
.eset-stat .num { font-size:22px; font-weight:700; color:#1e293b; line-height:1.1; }
.eset-stat .lbl { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.6px; font-weight:600; margin-top:2px; }

/* Panel */
.eset-panel {
    background:white; border-radius:22px; overflow:hidden;
    box-shadow:0 15px 40px rgba(0,0,0,0.06);
    border:1px solid #eef2f6; margin-bottom:25px;
}
.eset-panel-head {
    padding:20px 26px;
    background:linear-gradient(135deg,#f8fafd 0%,#f1f5f9 100%);
    border-bottom:1px solid #eef2f6;
    display:flex; align-items:center; gap:14px;
}
.eset-panel-head .ico {
    width:42px; height:42px; border-radius:12px;
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; display:flex; align-items:center; justify-content:center;
    font-size:18px; box-shadow:0 6px 15px rgba(14,76,146,0.25);
}
.eset-panel-head h2 { margin:0; font-size:16px; font-weight:700; color:#1e293b; }
.eset-panel-head p  { margin:2px 0 0; font-size:12px; color:#64748b; }
.eset-panel-body { padding:26px; }

/* Info note */
.eset-info-note {
    padding:14px 18px; background:#eff6ff;
    border-left:4px solid #3b82f6; border-radius:10px;
    font-size:13px; color:#1e40af; line-height:1.6;
    margin-bottom:20px; display:flex; align-items:flex-start; gap:10px;
}
.eset-info-note i { margin-top:2px; }

/* Form grid */
.eset-form-grid {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:18px;
}
.eset-field { display:flex; flex-direction:column; gap:8px; }
.eset-field label {
    font-size:12px; font-weight:700; color:#475569;
    text-transform:uppercase; letter-spacing:0.4px;
}
.eset-field input, .eset-field textarea {
    padding:13px 16px; border:1.5px solid #e2e8f0;
    border-radius:12px; font-size:14px; font-family:inherit;
    background:#f8fafc; color:#1e293b; transition:all 0.25s;
}
.eset-field input:focus, .eset-field textarea:focus {
    outline:none; border-color:#0e4c92; background:white;
    box-shadow:0 0 0 4px rgba(14,76,146,0.08);
}
.eset-field textarea { min-height:90px; resize:vertical; line-height:1.6; }
.eset-field .help { font-size:11px; color:#94a3b8; margin-top:-3px; }

/* Toggle rows */
.eset-toggle-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 20px; background:#f8fafd;
    border:1px solid #eef2f6; border-radius:14px;
    margin-bottom:10px; transition:all 0.25s;
}
.eset-toggle-row:hover { background:#f1f5f9; }
.eset-toggle-row .info { flex:1; padding-right:20px; }
.eset-toggle-row .info strong {
    display:flex; align-items:center; gap:8px;
    font-size:14px; color:#1e293b; font-weight:700;
}
.eset-toggle-row .info strong i { color:#0e4c92; }
.eset-toggle-row .info span {
    display:block; font-size:12px; color:#64748b;
    margin-top:4px; line-height:1.5;
}

/* Switch */
.eset-switch {
    position:relative; width:50px; height:28px;
    display:inline-block; flex-shrink:0;
}
.eset-switch input { opacity:0; width:0; height:0; }
.eset-slider {
    position:absolute; cursor:pointer; inset:0;
    background:#cbd5e1; border-radius:34px; transition:.3s;
}
.eset-slider:before {
    position:absolute; content:""; height:22px; width:22px;
    left:3px; bottom:3px; background:white;
    border-radius:50%; transition:.3s;
    box-shadow:0 2px 4px rgba(0,0,0,0.15);
}
.eset-switch input:checked + .eset-slider {
    background:linear-gradient(135deg,#0e4c92,#4086e4);
}
.eset-switch input:checked + .eset-slider:before {
    transform:translateX(22px);
}

/* Buttons */
.eset-btn {
    padding:13px 30px; border-radius:12px;
    font-size:14px; font-weight:700; cursor:pointer;
    border:none; display:inline-flex; align-items:center;
    gap:10px; transition:all 0.25s; font-family:inherit;
    text-decoration:none;
}
.eset-btn.primary {
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; box-shadow:0 6px 18px rgba(14,76,146,0.25);
}
.eset-btn.primary:hover {
    transform:translateY(-2px);
    box-shadow:0 10px 24px rgba(14,76,146,0.35);
}

/* Alerts */
.eset-alert {
    padding:14px 20px; border-radius:14px; margin-bottom:20px;
    font-size:13px; font-weight:500;
    display:flex; align-items:center; gap:10px;
}
.eset-alert.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.eset-alert.error   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

/* Save bar */
.eset-save-bar {
    background:white; border-radius:20px; padding:20px 26px;
    display:flex; justify-content:space-between; align-items:center;
    box-shadow:0 10px 30px rgba(0,0,0,0.06);
    border:1px solid #eef2f6;
    gap:15px; flex-wrap:wrap; margin-bottom:30px;
}
.eset-save-bar .save-hint {
    font-size:13px; color:#64748b;
    display:flex; align-items:center; gap:8px;
}
.eset-save-bar .save-hint i { color:#f59e0b; }

@media (max-width:640px) {
    .eset-header { padding:24px; }
    .eset-header h1 { font-size:20px; }
    .eset-save-bar { flex-direction:column; align-items:stretch; }
    .eset-btn { justify-content:center; width:100%; }
}
</style>

<!-- ============== HEADER ============== -->
<div class="eset-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-sliders-h"></i></div>
        <div>
            <h1>ESS Settings</h1>
            <p>Configure security, features, and the employee portal experience</p>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="eset-alert success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="eset-alert error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ============== STATS ============== -->
<div class="eset-stats">
    <div class="eset-stat">
        <div class="ico blue"><i class="fas fa-users"></i></div>
        <div><div class="num"><?= $totalAccounts ?></div><div class="lbl">Total Accounts</div></div>
    </div>
    <div class="eset-stat">
        <div class="ico green"><i class="fas fa-circle-check"></i></div>
        <div><div class="num"><?= $activeAccounts ?></div><div class="lbl">Active</div></div>
    </div>
    <div class="eset-stat">
        <div class="ico amber"><i class="fas fa-clock"></i></div>
        <div><div class="num"><?= $pendingSetup ?></div><div class="lbl">Pending Setup</div></div>
    </div>
    <div class="eset-stat">
        <div class="ico red"><i class="fas fa-triangle-exclamation"></i></div>
        <div><div class="num"><?= $tempExpired ?></div><div class="lbl">Temp Expired</div></div>
    </div>
    <div class="eset-stat">
        <div class="ico purple"><i class="fas fa-inbox"></i></div>
        <div><div class="num"><?= $pendingRequests ?></div><div class="lbl">Pending Requests</div></div>
    </div>
    <div class="eset-stat">
        <div class="ico amber"><i class="fas fa-file-alt"></i></div>
        <div><div class="num"><?= $pendingDocs ?></div><div class="lbl">Pending Docs</div></div>
    </div>
    <div class="eset-stat">
        <div class="ico green"><i class="fas fa-bullhorn"></i></div>
        <div><div class="num"><?= $totalAnnounce ?></div><div class="lbl">Announcements</div></div>
    </div>
</div>

<form method="POST">

    <!-- ============== SECURITY ============== -->
    <div class="eset-panel">
        <div class="eset-panel-head">
            <div class="ico"><i class="fas fa-shield-alt"></i></div>
            <div>
                <h2>Security Settings</h2>
                <p>Control password expiry, lockout thresholds, and session lifetime</p>
            </div>
        </div>
        <div class="eset-panel-body">
            <div class="eset-info-note">
                <i class="fas fa-info-circle"></i>
                <span>These settings apply to all ESS accounts. Changes take effect on the employee's next login.</span>
            </div>
            <div class="eset-form-grid">
                <div class="eset-field">
                    <label>Temporary Password Expiry (days)</label>
                    <input type="number" name="temp_password_expiry_days" min="1" max="365"
                           value="<?= (int)$settings['temp_password_expiry_days'] ?>">
                    <span class="help">Employees must change temp password within this period.</span>
                </div>
                <div class="eset-field">
                    <label>Max Login Attempts</label>
                    <input type="number" name="max_login_attempts" min="1" max="20"
                           value="<?= (int)$settings['max_login_attempts'] ?>">
                    <span class="help">Failed attempts before account is locked.</span>
                </div>
                <div class="eset-field">
                    <label>Lockout Duration (minutes)</label>
                    <input type="number" name="lockout_minutes" min="1" max="1440"
                           value="<?= (int)$settings['lockout_minutes'] ?>">
                    <span class="help">How long accounts stay locked after too many failures.</span>
                </div>
                <div class="eset-field">
                    <label>Session Lifetime (hours)</label>
                    <input type="number" name="session_lifetime_hours" min="1" max="72"
                           value="<?= (int)$settings['session_lifetime_hours'] ?>">
                    <span class="help">Auto-logout after this much inactivity.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ============== FEATURES ============== -->
    <div class="eset-panel">
        <div class="eset-panel-head">
            <div class="ico"><i class="fas fa-toggle-on"></i></div>
            <div>
                <h2>Portal Features</h2>
                <p>Enable or disable specific ESS capabilities</p>
            </div>
        </div>
        <div class="eset-panel-body">

            <div class="eset-toggle-row">
                <div class="info">
                    <strong><i class="fas fa-power-off"></i> ESS Portal Enabled</strong>
                    <span>Turn the entire ESS portal on or off for all employees.</span>
                </div>
                <label class="eset-switch">
                    <input type="checkbox" name="ess_enabled" <?= $settings['ess_enabled'] ? 'checked' : '' ?>>
                    <span class="eset-slider"></span>
                </label>
            </div>

            <div class="eset-toggle-row">
                <div class="info">
                    <strong><i class="fas fa-user-edit"></i> Allow Profile Editing</strong>
                    <span>Employees can update their own contact info.</span>
                </div>
                <label class="eset-switch">
                    <input type="checkbox" name="allow_profile_edit" <?= $settings['allow_profile_edit'] ? 'checked' : '' ?>>
                    <span class="eset-slider"></span>
                </label>
            </div>

            <div class="eset-toggle-row">
                <div class="info">
                    <strong><i class="fas fa-calendar-minus"></i> Allow Leave Requests</strong>
                    <span>Employees can file leave from ESS.</span>
                </div>
                <label class="eset-switch">
                    <input type="checkbox" name="allow_leave_requests" <?= $settings['allow_leave_requests'] ? 'checked' : '' ?>>
                    <span class="eset-slider"></span>
                </label>
            </div>

            <div class="eset-toggle-row">
                <div class="info">
                    <strong><i class="fas fa-hourglass-half"></i> Allow Overtime Requests</strong>
                    <span>Employees can submit OT requests.</span>
                </div>
                <label class="eset-switch">
                    <input type="checkbox" name="allow_overtime_requests" <?= $settings['allow_overtime_requests'] ? 'checked' : '' ?>>
                    <span class="eset-slider"></span>
                </label>
            </div>

            <div class="eset-toggle-row">
                <div class="info">
                    <strong><i class="fas fa-file-alt"></i> Allow Document Requests</strong>
                    <span>Employees can request COE, payslips, etc.</span>
                </div>
                <label class="eset-switch">
                    <input type="checkbox" name="allow_document_requests" <?= $settings['allow_document_requests'] ? 'checked' : '' ?>>
                    <span class="eset-slider"></span>
                </label>
            </div>

        </div>
    </div>

    <!-- ============== WELCOME MESSAGE ============== -->
    <div class="eset-panel">
        <div class="eset-panel-head">
            <div class="ico"><i class="fas fa-comment-dots"></i></div>
            <div>
                <h2>Welcome Message</h2>
                <p>Shown on the employee's ESS dashboard</p>
            </div>
        </div>
        <div class="eset-panel-body">
            <div class="eset-field">
                <label>Message Text</label>
                <textarea name="welcome_message"><?= htmlspecialchars($settings['welcome_message']) ?></textarea>
                <span class="help">Personalized greeting shown to employees when they land on their dashboard.</span>
            </div>
        </div>
    </div>

    <!-- ============== SAVE BAR ============== -->
    <div class="eset-save-bar">
        <div class="save-hint">
            <i class="fas fa-circle-info"></i>
            Remember to save your changes — settings apply immediately after saving.
        </div>
        <button type="submit" name="save_settings" class="eset-btn primary">
            <i class="fas fa-save"></i> Save All Settings
        </button>
    </div>

</form>