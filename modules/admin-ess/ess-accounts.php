<?php
// /modules/admin-ess/ess-accounts.php
require_once __DIR__ . '/../../includes/ess/ess_auth.php';

$message = '';
$error = '';
$credentials = null;

// ---- CREATE ESS ACCOUNT + SEND EMAIL ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_account'])) {
    $employeeId = (int)$_POST['employee_id'];
    $email      = trim($_POST['email'] ?? '');

    if (!$employeeId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please select an employee and enter a valid email.';
    } else {
        $result = essCreateAccount($pdo, $employeeId, $email, $_SESSION['user_id'] ?? null);
        if ($result['success']) {
            $stmt = $pdo->prepare("
                SELECT CONCAT(ja.first_name, ' ', ja.last_name) AS emp_name
                FROM new_hires nh
                JOIN job_applications ja ON nh.applicant_id = ja.id
                WHERE nh.id = ?
            ");
            $stmt->execute([$employeeId]);
            $empName = $stmt->fetchColumn() ?: 'Employee';

            $sent = essSendCredentialsEmail($email, $empName, $result['username'], $result['temp_password'], $result['expires_at']);

            $credentials = [
                'employee' => $empName,
                'username' => $result['username'],
                'password' => $result['temp_password'],
                'email'    => $email,
                'expires'  => $result['expires_at'],
                'email_sent' => $sent
            ];
            $message = $sent
                ? "ESS account created and credentials emailed to {$email}."
                : "ESS account created, but email failed to send. Copy credentials below manually.";
        } else {
            $error = $result['error'];
        }
    }
}

// ---- RESET PASSWORD ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $accountId = (int)$_POST['account_id'];
    $result = essResetPassword($pdo, $accountId);
    if ($result['success']) {
        $stmt = $pdo->prepare("
            SELECT ea.email, ea.username,
                   CONCAT(ja.first_name, ' ', ja.last_name) AS emp_name
            FROM ess_accounts ea
            JOIN new_hires nh        ON ea.employee_id = nh.id
            JOIN job_applications ja ON nh.applicant_id = ja.id
            WHERE ea.id = ?
        ");
        $stmt->execute([$accountId]);
        $info = $stmt->fetch();

        $sent = essSendCredentialsEmail($info['email'], $info['emp_name'], $info['username'], $result['temp_password'], $result['expires_at']);

        $credentials = [
            'employee'   => $info['emp_name'],
            'username'   => $info['username'],
            'password'   => $result['temp_password'],
            'email'      => $info['email'],
            'expires'    => $result['expires_at'],
            'email_sent' => $sent
        ];
        $message = "Password reset. New credentials " . ($sent ? 'emailed.' : 'generated below.');
    }
}

// ---- TOGGLE ACTIVE ----
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE ess_accounts SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    $message = "Account status updated.";
}

// ---- FETCH DATA ----
$accounts = $pdo->query("
    SELECT ea.*, nh.employee_id AS emp_code, nh.position, nh.department,
           ja.first_name, ja.last_name
    FROM ess_accounts ea
    JOIN new_hires nh        ON ea.employee_id = nh.id
    JOIN job_applications ja ON nh.applicant_id = ja.id
    ORDER BY ea.created_at DESC
")->fetchAll();

$employeesNoAccount = $pdo->query("
    SELECT nh.id, nh.employee_id AS emp_code, nh.position, nh.personal_email,
           ja.first_name, ja.last_name, ja.email AS applicant_email
    FROM new_hires nh
    JOIN job_applications ja ON nh.applicant_id = ja.id
    LEFT JOIN ess_accounts ea ON nh.id = ea.employee_id
    WHERE ea.id IS NULL AND nh.status IN ('active','onboarding')
    ORDER BY ja.first_name
")->fetchAll();

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(is_active = 1) AS active,
        SUM(must_change_password = 1) AS pending_setup,
        SUM(must_change_password = 1 AND temp_password_expires_at < NOW()) AS expired,
        SUM(is_active = 0) AS disabled
    FROM ess_accounts
")->fetch();
?>

<style>
/* ==========================================================
   ESS ACCOUNTS — REDESIGNED
   ========================================================== */
.eacc-header {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 24px;
    padding: 32px 36px;
    margin-bottom: 25px;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(14,76,146,0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}
.eacc-header::before {
    content:''; position:absolute; width:240px; height:240px;
    background: rgba(255,255,255,0.08); border-radius:50%;
    top:-100px; right:-60px;
}
.eacc-header::after {
    content:''; position:absolute; width:140px; height:140px;
    background: rgba(255,255,255,0.05); border-radius:50%;
    bottom:-60px; right:120px;
}
.eacc-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.eacc-header .hdr-icon {
    width:60px; height:60px; background: rgba(255,255,255,0.18);
    border:1.5px solid rgba(255,255,255,0.3); border-radius:18px;
    display:flex; align-items:center; justify-content:center;
    font-size:26px; backdrop-filter: blur(10px);
}
.eacc-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; letter-spacing:-0.3px; }
.eacc-header p  { font-size:13px; margin:0; opacity:0.85; }

/* Stats */
.eacc-stats {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
    gap:14px; margin-bottom:22px;
}
.eacc-stat {
    background:white; border-radius:18px; padding:18px 20px;
    display:flex; align-items:center; gap:14px;
    box-shadow:0 6px 20px rgba(0,0,0,0.04);
    border:1px solid #eef2f6;
    transition:all 0.3s;
}
.eacc-stat:hover { transform:translateY(-3px); box-shadow:0 12px 28px rgba(14,76,146,0.08); }
.eacc-stat .ico {
    width:44px; height:44px; border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; flex-shrink:0;
}
.eacc-stat .ico.blue   { background:rgba(14,76,146,0.1);  color:#0e4c92; }
.eacc-stat .ico.green  { background:rgba(22,163,74,0.12); color:#16a34a; }
.eacc-stat .ico.amber  { background:rgba(245,158,11,0.12);color:#d97706; }
.eacc-stat .ico.red    { background:rgba(220,38,38,0.12); color:#dc2626; }
.eacc-stat .ico.gray   { background:rgba(100,116,139,0.12);color:#475569; }
.eacc-stat .num { font-size:22px; font-weight:700; color:#1e293b; line-height:1.1; }
.eacc-stat .lbl { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.6px; font-weight:600; margin-top:2px; }

/* Panels */
.eacc-panel {
    background:white; border-radius:22px; overflow:hidden;
    box-shadow:0 15px 40px rgba(0,0,0,0.06);
    border:1px solid #eef2f6; margin-bottom:25px;
}
.eacc-panel-head {
    padding:20px 26px;
    background:linear-gradient(135deg,#f8fafd 0%,#f1f5f9 100%);
    border-bottom:1px solid #eef2f6;
    display:flex; align-items:center; gap:14px;
}
.eacc-panel-head .ico {
    width:42px; height:42px; border-radius:12px;
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; display:flex; align-items:center; justify-content:center;
    font-size:18px; box-shadow:0 6px 15px rgba(14,76,146,0.25);
}
.eacc-panel-head h2 { margin:0; font-size:16px; font-weight:700; color:#1e293b; }
.eacc-panel-head p  { margin:2px 0 0; font-size:12px; color:#64748b; }
.eacc-panel-body { padding:26px; }

/* Alerts */
.eacc-alert {
    padding:14px 20px; border-radius:14px; margin-bottom:20px;
    font-size:13px; font-weight:500;
    display:flex; align-items:center; gap:10px;
}
.eacc-alert.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.eacc-alert.error   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.eacc-alert.warning { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }

/* Credentials box */
.cred-box {
    background:linear-gradient(135deg,#f0f7ff 0%,#e0edff 100%);
    border:2px dashed #0e4c92;
    border-radius:18px;
    padding:24px;
    margin-bottom:22px;
    position:relative;
    overflow:hidden;
}
.cred-box::before {
    content:''; position:absolute; width:180px; height:180px;
    background:rgba(14,76,146,0.06); border-radius:50%;
    top:-80px; right:-40px;
}
.cred-box h3 {
    margin:0 0 16px; color:#0e4c92; font-size:15px;
    display:flex; align-items:center; gap:10px;
    position:relative; z-index:1;
}
.cred-box .close-btn {
    margin-left:auto; background:none; border:none; color:#64748b;
    cursor:pointer; font-size:14px; padding:4px;
}
.cred-row {
    display:flex; padding:10px 0;
    border-bottom:1px solid rgba(14,76,146,0.1);
    font-size:13px; position:relative; z-index:1;
    align-items:center; gap:10px;
}
.cred-row:last-child { border-bottom:none; }
.cred-row .lbl { font-weight:700; width:140px; color:#475569; font-size:12px; text-transform:uppercase; letter-spacing:0.4px; }
.cred-row .val { font-family:'Courier New',monospace; color:#1e293b; background:#fff; padding:4px 10px; border-radius:8px; font-size:13px; font-weight:600; }
.cred-row .val.pwd { font-size:16px; letter-spacing:1px; color:#0e4c92; background:#fff; border:1.5px solid #bfdbfe; }

/* Form */
.eacc-field { margin-bottom:18px; }
.eacc-field label {
    display:block; font-size:12px; font-weight:700; color:#475569;
    margin-bottom:8px; text-transform:uppercase; letter-spacing:0.4px;
}
.eacc-field input, .eacc-field select {
    width:100%; padding:13px 16px; border:1.5px solid #e2e8f0;
    border-radius:12px; font-size:14px; background:#f8fafc;
    font-family:inherit; color:#1e293b; transition:all 0.25s;
}
.eacc-field input:focus, .eacc-field select:focus {
    outline:none; border-color:#0e4c92; background:white;
    box-shadow:0 0 0 4px rgba(14,76,146,0.08);
}
.eacc-row { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
@media (max-width:640px) { .eacc-row { grid-template-columns:1fr; } }

.eacc-btn {
    padding:11px 24px; border-radius:12px; font-size:13px; font-weight:700;
    cursor:pointer; border:none; display:inline-flex; align-items:center;
    gap:8px; transition:all 0.25s; font-family:inherit; text-decoration:none;
}
.eacc-btn.primary {
    background:linear-gradient(135deg,#0e4c92,#4086e4); color:white;
    box-shadow:0 6px 18px rgba(14,76,146,0.25);
}
.eacc-btn.primary:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(14,76,146,0.35); }
.eacc-btn.success {
    background:linear-gradient(135deg,#16a34a,#22c55e); color:white;
    box-shadow:0 6px 18px rgba(22,163,74,0.25);
}
.eacc-btn.success:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(22,163,74,0.35); }
.eacc-btn.warning {
    background:linear-gradient(135deg,#d97706,#f59e0b); color:white;
    box-shadow:0 6px 18px rgba(217,119,6,0.25);
}
.eacc-btn.warning:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(217,119,6,0.35); }
.eacc-btn.danger {
    background:#fef2f2; color:#dc2626; border:1.5px solid #fecaca;
}
.eacc-btn.danger:hover { background:#dc2626; color:white; }
.eacc-btn.ghost {
    background:transparent; color:#64748b; border:1.5px solid #e2e8f0;
}
.eacc-btn.ghost:hover { background:white; color:#0e4c92; border-color:#0e4c92; }
.eacc-btn.sm { padding:7px 12px; font-size:12px; border-radius:10px; }

/* Account cards (replaces table) */
.eacc-list { display:flex; flex-direction:column; gap:14px; }
.eacc-card {
    background:#f8fafd; border:1px solid #eef2f6;
    border-radius:16px; padding:18px 22px;
    display:flex; align-items:center; gap:18px;
    transition:all 0.3s;
}
.eacc-card:hover {
    background:white; border-color:#0e4c92;
    box-shadow:0 8px 24px rgba(14,76,146,0.08);
    transform:translateY(-2px);
}
.eacc-avatar {
    width:52px; height:52px; border-radius:14px;
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; display:flex; align-items:center; justify-content:center;
    font-size:20px; font-weight:700; flex-shrink:0;
    box-shadow:0 6px 15px rgba(14,76,146,0.25);
}
.eacc-info { flex:1; min-width:0; }
.eacc-name { font-size:15px; font-weight:700; color:#1e293b; margin-bottom:3px; }
.eacc-sub { font-size:12px; color:#64748b; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.eacc-sub code {
    background:#e0edff; color:#0e4c92; padding:2px 8px;
    border-radius:6px; font-size:11px; font-weight:600;
}
.eacc-status {
    display:inline-flex; align-items:center; gap:5px;
    padding:5px 12px; border-radius:20px;
    font-size:11px; font-weight:700;
    text-transform:uppercase; letter-spacing:0.4px;
}
.eacc-status.active   { background:#dcfce7; color:#15803d; }
.eacc-status.inactive { background:#f1f5f9; color:#64748b; }
.eacc-status.pending  { background:#fef3c7; color:#92400e; }
.eacc-status.expired  { background:#fee2e2; color:#991b1b; }
.eacc-actions { display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap; }

.eacc-empty {
    padding:50px 30px; text-align:center;
    background:white; border-radius:22px;
    border:2px dashed #e2e8f0;
}
.eacc-empty .icon-wrap {
    width:80px; height:80px; margin:0 auto 20px;
    background:linear-gradient(135deg,#eef2ff,#e0e7ff);
    border-radius:24px;
    display:flex; align-items:center; justify-content:center;
    font-size:32px; color:#6366f1;
}
.eacc-empty h3 { font-size:18px; color:#1e293b; margin:0 0 8px; font-weight:700; }
.eacc-empty p  { font-size:13px; color:#64748b; margin:0; }

@media (max-width:640px) {
    .eacc-header { padding:24px; }
    .eacc-header h1 { font-size:20px; }
    .eacc-card { flex-direction:column; align-items:flex-start; }
    .eacc-actions { width:100%; }
    .eacc-actions .eacc-btn { flex:1; justify-content:center; }
}
</style>

<!-- ============== HEADER ============== -->
<div class="eacc-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-users-cog"></i></div>
        <div>
            <h1>ESS Account Management</h1>
            <p>Create, manage, and control employee self-service access</p>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="eacc-alert success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="eacc-alert error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ============== STATS ============== -->
<div class="eacc-stats">
    <div class="eacc-stat">
        <div class="ico blue"><i class="fas fa-users"></i></div>
        <div><div class="num"><?= (int)$stats['total'] ?></div><div class="lbl">Total Accounts</div></div>
    </div>
    <div class="eacc-stat">
        <div class="ico green"><i class="fas fa-circle-check"></i></div>
        <div><div class="num"><?= (int)$stats['active'] ?></div><div class="lbl">Active</div></div>
    </div>
    <div class="eacc-stat">
        <div class="ico amber"><i class="fas fa-clock"></i></div>
        <div><div class="num"><?= (int)$stats['pending_setup'] ?></div><div class="lbl">Pending Setup</div></div>
    </div>
    <div class="eacc-stat">
        <div class="ico red"><i class="fas fa-triangle-exclamation"></i></div>
        <div><div class="num"><?= (int)$stats['expired'] ?></div><div class="lbl">Temp Expired</div></div>
    </div>
    <div class="eacc-stat">
        <div class="ico gray"><i class="fas fa-power-off"></i></div>
        <div><div class="num"><?= (int)$stats['disabled'] ?></div><div class="lbl">Disabled</div></div>
    </div>
</div>

<!-- ============== CREDENTIALS MODAL BOX ============== -->
<?php if ($credentials): ?>
<div class="cred-box" id="credBox">
    <h3>
        <i class="fas fa-key"></i>
        Credentials <?= $credentials['email_sent'] ? '— Emailed Successfully' : '— Share Manually' ?>
        <button class="close-btn" onclick="document.getElementById('credBox').style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </h3>
    <div class="cred-row">
        <span class="lbl">Employee</span>
        <span class="val"><?= htmlspecialchars($credentials['employee']) ?></span>
    </div>
    <div class="cred-row">
        <span class="lbl">Username</span>
        <span class="val"><?= htmlspecialchars($credentials['username']) ?></span>
    </div>
    <div class="cred-row">
        <span class="lbl">Email</span>
        <span class="val"><?= htmlspecialchars($credentials['email']) ?></span>
    </div>
    <div class="cred-row">
        <span class="lbl">Temp Password</span>
        <span class="val pwd"><?= htmlspecialchars($credentials['password']) ?></span>
    </div>
    <div class="cred-row">
        <span class="lbl">Expires</span>
        <span class="val"><?= date('F j, Y g:i A', strtotime($credentials['expires'])) ?></span>
    </div>
</div>
<?php endif; ?>

<!-- ============== CREATE ACCOUNT ============== -->
<?php if (!empty($employeesNoAccount)): ?>
<div class="eacc-panel">
    <div class="eacc-panel-head">
        <div class="ico"><i class="fas fa-user-plus"></i></div>
        <div>
            <h2>Create ESS Account</h2>
            <p>Generate credentials and email them to the employee</p>
        </div>
    </div>
    <div class="eacc-panel-body">
        <form method="POST">
            <div class="eacc-row">
                <div class="eacc-field">
                    <label>Employee *</label>
                    <select name="employee_id" id="empSelect" required onchange="fillEmail()">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employeesNoAccount as $e): ?>
                        <option value="<?= $e['id'] ?>"
                                data-email="<?= htmlspecialchars($e['personal_email'] ?: $e['applicant_email']) ?>">
                            <?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?>
                            (<?= htmlspecialchars($e['emp_code']) ?>) — <?= htmlspecialchars($e['position'] ?? '') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="eacc-field">
                    <label>Email Address *</label>
                    <input type="email" name="email" id="emailInput" required placeholder="employee@example.com">
                </div>
            </div>
            <div style="text-align:right;margin-top:6px;">
                <button type="submit" name="create_account" class="eacc-btn success">
                    <i class="fas fa-paper-plane"></i> Create &amp; Send Credentials
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ============== EXISTING ACCOUNTS ============== -->
<div class="eacc-panel">
    <div class="eacc-panel-head">
        <div class="ico"><i class="fas fa-list"></i></div>
        <div>
            <h2>Existing ESS Accounts</h2>
            <p><?= count($accounts) ?> account<?= count($accounts) != 1 ? 's' : '' ?> registered</p>
        </div>
    </div>
    <div class="eacc-panel-body">

        <?php if (empty($accounts)): ?>
        <div class="eacc-empty">
            <div class="icon-wrap"><i class="fas fa-user-slash"></i></div>
            <h3>No ESS accounts yet</h3>
            <p>Create accounts above to give employees access to the portal.</p>
        </div>
        <?php else: ?>

        <div class="eacc-list">
            <?php foreach ($accounts as $a):
                $initial = strtoupper(substr($a['first_name'] ?? 'E', 0, 1));

                $statusClass = 'inactive';
                $statusText  = 'Inactive';
                if (!$a['is_active']) { $statusClass='inactive'; $statusText='Disabled'; }
                elseif ($a['must_change_password']) {
                    if ($a['temp_password_expires_at'] && strtotime($a['temp_password_expires_at']) < time()) {
                        $statusClass='expired'; $statusText='Temp Expired';
                    } else {
                        $statusClass='pending'; $statusText='Pending Setup';
                    }
                } else {
                    $statusClass='active'; $statusText='Active';
                }
            ?>
            <div class="eacc-card">
                <div class="eacc-avatar"><?= htmlspecialchars($initial) ?></div>
                <div class="eacc-info">
                    <div class="eacc-name">
                        <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>
                    </div>
                    <div class="eacc-sub">
                        <span><i class="fas fa-id-badge"></i> <?= htmlspecialchars($a['emp_code']) ?></span>
                        <span><i class="fas fa-briefcase"></i> <?= htmlspecialchars($a['position'] ?? '—') ?></span>
                        <code><?= htmlspecialchars($a['username']) ?></code>
                        <span class="eacc-status <?= $statusClass ?>">
                            <?= $statusText ?>
                        </span>
                    </div>
                    <div class="eacc-sub" style="margin-top:6px;">
                        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($a['email']) ?></span>
                        <span><i class="fas fa-clock-rotate-left"></i>
                            <?= $a['last_login'] ? 'Last login: ' . date('M j, Y · g:i A', strtotime($a['last_login'])) : 'Never logged in' ?>
                        </span>
                    </div>
                </div>
                <div class="eacc-actions">
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('Reset password for <?= htmlspecialchars($a['first_name']) ?>? A new temp password will be emailed.');">
                        <input type="hidden" name="account_id" value="<?= $a['id'] ?>">
                        <button type="submit" name="reset_password" class="eacc-btn warning sm">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </form>
                    <a href="?page=ess-admin&subpage=ess-accounts&toggle=<?= $a['id'] ?>"
                       class="eacc-btn <?= $a['is_active'] ? 'danger' : 'success' ?> sm"
                       onclick="return confirm('<?= $a['is_active'] ? 'Disable' : 'Enable' ?> this account?')">
                        <i class="fas fa-power-off"></i>
                        <?= $a['is_active'] ? 'Disable' : 'Enable' ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function fillEmail() {
    const sel = document.getElementById('empSelect');
    const opt = sel.options[sel.selectedIndex];
    const email = opt.getAttribute('data-email') || '';
    document.getElementById('emailInput').value = email;
}
</script>