<?php
// /modules/ess/my-profile.php
$emp = essGetEmployee($pdo);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $pdo->prepare("
            UPDATE new_hires
            SET personal_email = ?, personal_phone = ?, address = ?,
                city = ?, province = ?, postal_code = ?,
                emergency_contact_name = ?, emergency_contact_relationship = ?,
                emergency_contact_phone = ?
            WHERE id = ?
        ")->execute([
            $_POST['personal_email'] ?: null,
            $_POST['personal_phone'] ?: null,
            $_POST['address'] ?: null,
            $_POST['city'] ?: null,
            $_POST['province'] ?: null,
            $_POST['postal_code'] ?: null,
            $_POST['emergency_contact_name'] ?: null,
            $_POST['emergency_contact_relationship'] ?: null,
            $_POST['emergency_contact_phone'] ?: null,
            $emp['id']
        ]);
        $message = 'Profile updated successfully.';
        $emp = essGetEmployee($pdo);
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}
?>

<style>
.ess-page-header {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 24px; padding: 32px 36px; margin-bottom: 25px;
    color: white; position: relative; overflow: hidden;
    box-shadow: 0 20px 40px rgba(14,76,146,0.2);
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;
}
.ess-page-header::before {
    content:''; position:absolute; width:240px; height:240px;
    background: rgba(255,255,255,0.08); border-radius:50%;
    top:-100px; right:-60px;
}
.ess-page-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.ess-page-header .hdr-icon {
    width:60px; height:60px; background: rgba(255,255,255,0.18);
    border:1.5px solid rgba(255,255,255,0.3); border-radius:18px;
    display:flex; align-items:center; justify-content:center;
    font-size:26px; backdrop-filter: blur(10px);
}
.ess-page-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; letter-spacing:-0.3px; }
.ess-page-header p  { font-size:13px; margin:0; opacity:0.85; }

.ess-panel {
    background:white; border-radius:22px; overflow:hidden;
    box-shadow:0 10px 30px rgba(0,0,0,0.05);
    border:1px solid #eef2f6; margin-bottom:22px;
}
.ess-panel-head {
    padding:18px 24px;
    background:linear-gradient(135deg,#f8fafd 0%,#f1f5f9 100%);
    border-bottom:1px solid #eef2f6;
    display:flex; align-items:center; gap:14px;
}
.ess-panel-head .ico {
    width:40px; height:40px; border-radius:12px;
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; display:flex; align-items:center; justify-content:center;
    font-size:17px; box-shadow:0 6px 15px rgba(14,76,146,0.25);
}
.ess-panel-head h3 { margin:0; font-size:15px; font-weight:700; color:#1e293b; }
.ess-panel-body { padding:24px; }

.ess-alert {
    padding:14px 20px; border-radius:14px; margin-bottom:20px;
    font-size:13px; font-weight:500;
    display:flex; align-items:center; gap:10px;
}
.ess-alert.success { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.ess-alert.error   { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

.profile-banner {
    display:flex; align-items:center; gap:24px;
    padding:26px;
    background:linear-gradient(135deg,#f8fafd 0%,#eff6ff 100%);
    border-radius:18px;
    margin-bottom:22px;
    border:1px solid #eef2f6;
}
.profile-avatar-lg {
    width:90px; height:90px; border-radius:24px;
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; display:flex; align-items:center; justify-content:center;
    font-size:36px; font-weight:700;
    box-shadow:0 10px 30px rgba(14,76,146,0.3);
    flex-shrink:0;
}
.profile-banner-info h2 { font-size:22px; font-weight:700; color:#0f172a; margin:0 0 6px; }
.profile-banner-info .role { font-size:14px; color:#0e4c92; font-weight:600; margin-bottom:8px; }
.profile-banner-info .meta { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; color:#64748b; }
.profile-banner-info .meta span { display:flex; align-items:center; gap:5px; }

.info-grid {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:14px;
}
.info-item {
    padding:14px 18px; background:#f8fafd;
    border:1px solid #eef2f6; border-radius:12px;
}
.info-item label {
    display:block; font-size:10.5px; font-weight:700;
    color:#94a3b8; text-transform:uppercase;
    letter-spacing:0.5px; margin-bottom:6px;
}
.info-item .value { font-size:14px; font-weight:600; color:#1e293b; word-break:break-word; }

.form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:18px; }
.form-field { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
.form-field label {
    font-size:12px; font-weight:700; color:#475569;
    text-transform:uppercase; letter-spacing:0.4px;
}
.form-field input, .form-field textarea, .form-field select {
    padding:13px 16px; border:1.5px solid #e2e8f0;
    border-radius:12px; font-size:14px; font-family:inherit;
    background:#f8fafc; color:#1e293b; transition:all 0.25s;
}
.form-field input:focus, .form-field textarea:focus {
    outline:none; border-color:#0e4c92; background:white;
    box-shadow:0 0 0 4px rgba(14,76,146,0.08);
}
.form-field textarea { min-height:80px; resize:vertical; }

.ess-btn {
    padding:13px 30px; border-radius:12px;
    font-size:14px; font-weight:700; cursor:pointer;
    border:none; display:inline-flex; align-items:center;
    gap:10px; transition:all 0.25s; font-family:inherit;
}
.ess-btn.primary {
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    color:white; box-shadow:0 6px 18px rgba(14,76,146,0.25);
}
.ess-btn.primary:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(14,76,146,0.35); }

@media (max-width:640px) {
    .ess-page-header { padding:24px; }
    .ess-page-header h1 { font-size:20px; }
    .profile-banner { flex-direction:column; text-align:center; }
}
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-id-card"></i></div>
        <div>
            <h1>My Profile</h1>
            <p>View and update your personal information</p>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="ess-alert <?= strpos($message, 'Error') === 0 ? 'error' : 'success' ?>">
    <i class="fas fa-<?= strpos($message, 'Error') === 0 ? 'circle-exclamation' : 'circle-check' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="profile-banner">
    <div class="profile-avatar-lg">
        <?= strtoupper(substr($emp['first_name'] ?? 'E', 0, 1)) ?>
    </div>
    <div class="profile-banner-info">
        <h2><?= htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['middle_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?></h2>
        <div class="role"><?= htmlspecialchars($emp['position'] ?? 'Employee') ?></div>
        <div class="meta">
            <span><i class="fas fa-id-badge"></i> <?= htmlspecialchars($emp['employee_id'] ?? '—') ?></span>
            <span><i class="fas fa-building"></i> <?= htmlspecialchars(ucfirst($emp['department'] ?? '—')) ?></span>
            <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($emp['applicant_email'] ?? '—') ?></span>
        </div>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-user-lock"></i></div>
        <h3>Read-Only Information</h3>
    </div>
    <div class="ess-panel-body">
        <div class="info-grid">
            <div class="info-item">
                <label>Employee ID</label>
                <div class="value"><?= htmlspecialchars($emp['employee_id'] ?? '—') ?></div>
            </div>
            <div class="info-item">
                <label>Position</label>
                <div class="value"><?= htmlspecialchars($emp['position'] ?? '—') ?></div>
            </div>
            <div class="info-item">
                <label>Department</label>
                <div class="value"><?= htmlspecialchars(ucfirst($emp['department'] ?? '—')) ?></div>
            </div>
            <div class="info-item">
                <label>Employment Status</label>
                <div class="value"><?= htmlspecialchars(ucfirst($emp['employment_status'] ?? '—')) ?></div>
            </div>
            <div class="info-item">
                <label>Date Hired</label>
                <div class="value"><?= $emp['hire_date'] ? date('F j, Y', strtotime($emp['hire_date'])) : '—' ?></div>
            </div>
            <div class="info-item">
                <label>Employment Type</label>
                <div class="value"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $emp['employment_type'] ?? '—'))) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-pen"></i></div>
        <h3>Update Contact Information</h3>
    </div>
    <div class="ess-panel-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-field">
                    <label>Personal Email</label>
                    <input type="email" name="personal_email" value="<?= htmlspecialchars($emp['personal_email'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label>Personal Phone</label>
                    <input type="text" name="personal_phone" value="<?= htmlspecialchars($emp['personal_phone'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label>City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($emp['city'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label>Province</label>
                    <input type="text" name="province" value="<?= htmlspecialchars($emp['province'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label>Postal Code</label>
                    <input type="text" name="postal_code" value="<?= htmlspecialchars($emp['postal_code'] ?? '') ?>">
                </div>
            </div>
            <div class="form-field">
                <label>Full Address</label>
                <textarea name="address"><?= htmlspecialchars($emp['address'] ?? '') ?></textarea>
            </div>

            <h4 style="font-size:13px;color:#0e4c92;margin:20px 0 12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
                <i class="fas fa-phone"></i> Emergency Contact
            </h4>
            <div class="form-grid">
                <div class="form-field">
                    <label>Contact Name</label>
                    <input type="text" name="emergency_contact_name" value="<?= htmlspecialchars($emp['emergency_contact_name'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label>Relationship</label>
                    <input type="text" name="emergency_contact_relationship" value="<?= htmlspecialchars($emp['emergency_contact_relationship'] ?? '') ?>">
                </div>
                <div class="form-field">
                    <label>Contact Phone</label>
                    <input type="text" name="emergency_contact_phone" value="<?= htmlspecialchars($emp['emergency_contact_phone'] ?? '') ?>">
                </div>
            </div>

            <div style="text-align:right;margin-top:20px;">
                <button type="submit" name="update_profile" class="ess-btn primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>