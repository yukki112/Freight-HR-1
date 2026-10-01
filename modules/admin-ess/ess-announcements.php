<?php
// /modules/admin-ess/ess-announcements.php
$message = '';
$error = '';

// ---- CREATE ANNOUNCEMENT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    try {
        $title    = trim($_POST['title'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $type     = $_POST['type'] ?? 'info';
        $depts    = !empty($_POST['target_departments']) ? implode(',', $_POST['target_departments']) : null;
        $roles    = !empty($_POST['target_roles']) ? implode(',', $_POST['target_roles']) : null;
        $publish  = isset($_POST['publish_now']) ? 1 : 0;
        $expires  = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        if ($title === '' || $content === '') {
            $error = 'Title and content are required.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ess_announcements
                (title, content, type, target_departments, target_roles, is_published, published_at, expires_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, " . ($publish ? 'NOW()' : 'NULL') . ", ?, ?)
            ");
            $stmt->execute([$title, $content, $type, $depts, $roles, $publish, $expires, $_SESSION['user_id'] ?? null]);
            $message = 'Announcement created successfully.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// ---- UPDATE ANNOUNCEMENT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_announcement'])) {
    try {
        $id       = (int)$_POST['announcement_id'];
        $title    = trim($_POST['title'] ?? '');
        $content  = trim($_POST['content'] ?? '');
        $type     = $_POST['type'] ?? 'info';
        $depts    = !empty($_POST['target_departments']) ? implode(',', $_POST['target_departments']) : null;
        $roles    = !empty($_POST['target_roles']) ? implode(',', $_POST['target_roles']) : null;
        $expires  = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        $stmt = $pdo->prepare("
            UPDATE ess_announcements
            SET title = ?, content = ?, type = ?, target_departments = ?, target_roles = ?, expires_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $content, $type, $depts, $roles, $expires, $id]);
        $message = 'Announcement updated successfully.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// ---- DELETE ----
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM ess_announcements WHERE id = ?")->execute([(int)$_GET['delete']]);
        $message = 'Announcement deleted.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// ---- TOGGLE PUBLISH ----
if (isset($_GET['toggle_publish'])) {
    try {
        $pdo->prepare("
            UPDATE ess_announcements
            SET is_published = 1 - is_published,
                published_at = IF(is_published = 0 AND published_at IS NULL, NOW(), published_at)
            WHERE id = ?
        ")->execute([(int)$_GET['toggle_publish']]);
        $message = 'Announcement status updated.';
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// ---- EDIT MODE ----
$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM ess_announcements WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch();
}

// ---- FETCH ANNOUNCEMENTS ----
$filterTab = $_GET['tab'] ?? 'all';
$where = '';
$params = [];
if ($filterTab === 'published') { $where = 'WHERE a.is_published = 1'; }
elseif ($filterTab === 'draft') { $where = 'WHERE a.is_published = 0'; }
elseif ($filterTab === 'expired') { $where = 'WHERE a.expires_at IS NOT NULL AND a.expires_at < NOW()'; }

$announcements = $pdo->query("
    SELECT a.*, u.full_name AS author_name
    FROM ess_announcements a
    LEFT JOIN users u ON a.created_by = u.id
    {$where}
    ORDER BY a.created_at DESC
")->fetchAll();

$counts = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(is_published = 1) AS published,
        SUM(is_published = 0) AS draft,
        SUM(expires_at IS NOT NULL AND expires_at < NOW()) AS expired
    FROM ess_announcements
")->fetch();

$departments = $pdo->query("SELECT department_code, department_name FROM departments WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Type presets
$typePresets = [
    'info'    => ['label' => 'Information', 'icon' => 'fa-circle-info',       'gradient' => 'linear-gradient(135deg,#3b82f6,#60a5fa)', 'bg' => '#eff6ff', 'text' => '#1e40af', 'border' => '#bfdbfe'],
    'success' => ['label' => 'Success',     'icon' => 'fa-circle-check',      'gradient' => 'linear-gradient(135deg,#16a34a,#22c55e)', 'bg' => '#f0fdf4', 'text' => '#166534', 'border' => '#bbf7d0'],
    'warning' => ['label' => 'Warning',     'icon' => 'fa-triangle-exclamation','gradient' => 'linear-gradient(135deg,#f59e0b,#fbbf24)', 'bg' => '#fffbeb', 'text' => '#92400e', 'border' => '#fde68a'],
    'danger'  => ['label' => 'Important',   'icon' => 'fa-circle-exclamation','gradient' => 'linear-gradient(135deg,#dc2626,#ef4444)', 'bg' => '#fef2f2', 'text' => '#991b1b', 'border' => '#fecaca'],
];
?>

<style>
/* ==========================================================
   ESS ANNOUNCEMENTS — REDESIGNED
   ========================================================== */

.anc-page-header {
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
.anc-page-header::before {
    content: '';
    position: absolute;
    width: 240px; height: 240px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
    top: -100px; right: -60px;
}
.anc-page-header::after {
    content: '';
    position: absolute;
    width: 140px; height: 140px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    bottom: -60px; right: 120px;
}
.anc-page-header .hdr-left { display: flex; align-items: center; gap: 18px; position: relative; z-index: 1; }
.anc-page-header .hdr-icon {
    width: 60px; height: 60px;
    background: rgba(255,255,255,0.18);
    border: 1.5px solid rgba(255,255,255,0.3);
    border-radius: 18px;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px;
    backdrop-filter: blur(10px);
}
.anc-page-header h1 { font-size: 24px; font-weight: 700; margin: 0 0 4px; letter-spacing: -0.3px; }
.anc-page-header p  { font-size: 13px; margin: 0; opacity: 0.85; }
.anc-page-header .hdr-btn {
    position: relative; z-index: 1;
    background: white;
    color: #0e4c92;
    padding: 12px 22px;
    border-radius: 14px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    transition: all 0.3s;
}
.anc-page-header .hdr-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(0,0,0,0.2); }

/* ---- STATS STRIP ---- */
.anc-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}
.anc-stat {
    background: white;
    border-radius: 18px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.04);
    border: 1px solid #eef2f6;
    transition: all 0.3s;
}
.anc-stat:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(14,76,146,0.08); }
.anc-stat .ico {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.anc-stat .ico.blue   { background: rgba(14,76,146,0.1);  color: #0e4c92; }
.anc-stat .ico.green  { background: rgba(22,163,74,0.12); color: #16a34a; }
.anc-stat .ico.gray   { background: rgba(100,116,139,0.12); color: #475569; }
.anc-stat .ico.red    { background: rgba(220,38,38,0.12); color: #dc2626; }
.anc-stat .num { font-size: 22px; font-weight: 700; color: #1e293b; line-height: 1.1; }
.anc-stat .lbl { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px; font-weight: 600; margin-top: 2px; }

/* ---- TABS ---- */
.anc-tabs {
    display: flex;
    gap: 6px;
    background: white;
    padding: 6px;
    border-radius: 16px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.04);
    border: 1px solid #eef2f6;
    margin-bottom: 22px;
    flex-wrap: wrap;
}
.anc-tab {
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    justify-content: center;
    min-width: 110px;
}
.anc-tab:hover { background: #f1f5f9; color: #0e4c92; }
.anc-tab.active { background: linear-gradient(135deg,#0e4c92,#4086e4); color: white; box-shadow: 0 6px 15px rgba(14,76,146,0.25); }
.anc-tab .badge {
    background: rgba(0,0,0,0.08);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
}
.anc-tab.active .badge { background: rgba(255,255,255,0.25); }

/* ---- COMPOSE / EDIT PANEL ---- */
.anc-compose {
    background: white;
    border-radius: 22px;
    padding: 0;
    overflow: hidden;
    box-shadow: 0 15px 40px rgba(0,0,0,0.06);
    border: 1px solid #eef2f6;
    margin-bottom: 25px;
}
.anc-compose-head {
    padding: 20px 26px;
    background: linear-gradient(135deg, #f8fafd 0%, #f1f5f9 100%);
    border-bottom: 1px solid #eef2f6;
    display: flex;
    align-items: center;
    gap: 14px;
}
.anc-compose-head .ico {
    width: 42px; height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg,#0e4c92,#4086e4);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    box-shadow: 0 6px 15px rgba(14,76,146,0.25);
}
.anc-compose-head h2 { margin: 0; font-size: 16px; font-weight: 700; color: #1e293b; }
.anc-compose-head p  { margin: 2px 0 0; font-size: 12px; color: #64748b; }

.anc-compose-body { padding: 26px; }

.anc-field { margin-bottom: 18px; }
.anc-field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.anc-field input[type=text],
.anc-field input[type=datetime-local],
.anc-field textarea,
.anc-field select {
    width: 100%;
    padding: 13px 16px;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    background: #f8fafc;
    font-family: inherit;
    transition: all 0.25s;
    color: #1e293b;
}
.anc-field input:focus,
.anc-field textarea:focus,
.anc-field select:focus {
    outline: none;
    border-color: #0e4c92;
    background: white;
    box-shadow: 0 0 0 4px rgba(14,76,146,0.08);
}
.anc-field textarea { min-height: 140px; resize: vertical; line-height: 1.6; }

/* Type chooser */
.anc-types { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.anc-type-opt { position: relative; }
.anc-type-opt input { position: absolute; opacity: 0; }
.anc-type-opt label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px 10px;
    background: #f8fafc;
    border: 2px solid #eef2f6;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 600;
    font-size: 12px;
    color: #64748b;
    margin: 0;
}
.anc-type-opt label:hover { background: white; transform: translateY(-2px); }
.anc-type-opt label i { font-size: 20px; }
.anc-type-opt input:checked + label { border-width: 2px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.anc-type-opt input:checked + label i { transform: scale(1.1); }

/* Two-column grid */
.anc-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media (max-width: 700px) { .anc-row { grid-template-columns: 1fr; } }

/* Chip checkboxes */
.anc-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.anc-chip { position: relative; }
.anc-chip input { position: absolute; opacity: 0; }
.anc-chip label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #f1f5f9;
    border: 1.5px solid transparent;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.25s;
    text-transform: none;
    letter-spacing: 0;
    margin: 0;
}
.anc-chip label:hover { background: #e2e8f0; }
.anc-chip input:checked + label {
    background: linear-gradient(135deg,#0e4c92,#4086e4);
    color: white;
    box-shadow: 0 4px 12px rgba(14,76,146,0.25);
}

/* Footer actions */
.anc-compose-foot {
    padding: 20px 26px;
    background: #f8fafd;
    border-top: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.anc-publish-toggle { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #475569; font-weight: 600; }

.anc-btn {
    padding: 11px 24px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s;
    font-family: inherit;
    text-decoration: none;
}
.anc-btn.primary {
    background: linear-gradient(135deg,#0e4c92,#4086e4);
    color: white;
    box-shadow: 0 6px 18px rgba(14,76,146,0.25);
}
.anc-btn.primary:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(14,76,146,0.35); }
.anc-btn.ghost {
    background: transparent;
    color: #64748b;
    border: 1.5px solid #e2e8f0;
}
.anc-btn.ghost:hover { background: white; color: #0e4c92; border-color: #0e4c92; }

/* ---- ANNOUNCEMENT CARDS ---- */
.anc-feed { display: flex; flex-direction: column; gap: 16px; }

.anc-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    border: 1px solid #eef2f6;
    transition: all 0.3s;
    position: relative;
}
.anc-card:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(14,76,146,0.1); }

.anc-card-strip { height: 5px; width: 100%; }
.anc-card-inner { padding: 22px 26px; }

.anc-card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 14px;
}
.anc-card-title-wrap { flex: 1; min-width: 0; }
.anc-card-type {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.anc-card-title {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
    margin: 0;
    word-break: break-word;
}
.anc-card-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 8px;
    font-size: 12px;
    color: #94a3b8;
    flex-wrap: wrap;
}
.anc-card-meta span { display: inline-flex; align-items: center; gap: 5px; }
.anc-card-meta i { color: #cbd5e1; }

.anc-card-status-pill {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    white-space: nowrap;
    flex-shrink: 0;
}
.anc-card-status-pill.published { background: #dcfce7; color: #15803d; }
.anc-card-status-pill.draft     { background: #f1f5f9; color: #475569; }
.anc-card-status-pill.expired   { background: #fee2e2; color: #991b1b; }

.anc-card-content {
    font-size: 14px;
    color: #475569;
    line-height: 1.7;
    margin: 12px 0 16px;
    padding: 16px 18px;
    background: #f8fafc;
    border-left: 3px solid #e2e8f0;
    border-radius: 0 12px 12px 0;
    white-space: pre-wrap;
    word-break: break-word;
    max-height: 180px;
    overflow: hidden;
    position: relative;
}
.anc-card-content::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 40px;
    background: linear-gradient(to bottom, transparent, #f8fafc);
    pointer-events: none;
}

.anc-card-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
}
.anc-card-tag {
    padding: 5px 11px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.anc-card-tag.dept { background: #e0e7ff; color: #3730a3; }
.anc-card-tag.role { background: #fae8ff; color: #86198f; }
.anc-card-tag.exp  { background: #fef3c7; color: #92400e; }
.anc-card-tag.all  { background: #dcfce7; color: #15803d; }

.anc-card-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
    flex-wrap: wrap;
}

.anc-action-btn {
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.25s;
    border: 1.5px solid transparent;
    cursor: pointer;
    font-family: inherit;
    background: #f8fafc;
    color: #475569;
}
.anc-action-btn:hover { transform: translateY(-1px); }
.anc-action-btn.publish   { color: #16a34a; }
.anc-action-btn.publish:hover { background: #dcfce7; border-color: #bbf7d0; }
.anc-action-btn.unpublish { color: #f59e0b; }
.anc-action-btn.unpublish:hover { background: #fef3c7; border-color: #fde68a; }
.anc-action-btn.edit   { color: #0e4c92; }
.anc-action-btn.edit:hover { background: #dbeafe; border-color: #bfdbfe; }
.anc-action-btn.delete { color: #dc2626; }
.anc-action-btn.delete:hover { background: #fee2e2; border-color: #fecaca; }

/* ---- ALERT ---- */
.anc-alert {
    padding: 14px 20px;
    border-radius: 14px;
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}
.anc-alert.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.anc-alert.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

/* ---- EMPTY STATE ---- */
.anc-empty {
    padding: 60px 30px;
    text-align: center;
    background: white;
    border-radius: 22px;
    border: 2px dashed #e2e8f0;
}
.anc-empty .icon-wrap {
    width: 80px; height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg,#eef2ff,#e0e7ff);
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: #6366f1;
}
.anc-empty h3 { font-size: 18px; color: #1e293b; margin: 0 0 8px; font-weight: 700; }
.anc-empty p  { font-size: 13px; color: #64748b; margin: 0 0 20px; }

/* ---- RESPONSIVE ---- */
@media (max-width: 640px) {
    .anc-page-header { padding: 24px; }
    .anc-page-header h1 { font-size: 20px; }
    .anc-page-header .hdr-icon { width: 50px; height: 50px; font-size: 22px; }
    .anc-types { grid-template-columns: repeat(2, 1fr); }
    .anc-compose-body { padding: 20px; }
    .anc-card-inner { padding: 18px 20px; }
    .anc-card-head { flex-direction: column; }
    .anc-card-status-pill { align-self: flex-start; }
}
</style>

<!-- ==========================================================
     PAGE HEADER
     ========================================================== -->
<div class="anc-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-bullhorn"></i></div>
        <div>
            <h1>Announcements</h1>
            <p>Broadcast company news, updates, and important notices</p>
        </div>
    </div>
    <a href="#compose" class="hdr-btn">
        <i class="fas fa-plus"></i> New Announcement
    </a>
</div>

<?php if ($message): ?>
<div class="anc-alert success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="anc-alert error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ==========================================================
     STATS
     ========================================================== -->
<div class="anc-stats">
    <div class="anc-stat">
        <div class="ico blue"><i class="fas fa-layer-group"></i></div>
        <div><div class="num"><?= (int)$counts['total'] ?></div><div class="lbl">Total</div></div>
    </div>
    <div class="anc-stat">
        <div class="ico green"><i class="fas fa-check-circle"></i></div>
        <div><div class="num"><?= (int)$counts['published'] ?></div><div class="lbl">Published</div></div>
    </div>
    <div class="anc-stat">
        <div class="ico gray"><i class="fas fa-file-pen"></i></div>
        <div><div class="num"><?= (int)$counts['draft'] ?></div><div class="lbl">Drafts</div></div>
    </div>
    <div class="anc-stat">
        <div class="ico red"><i class="fas fa-clock-rotate-left"></i></div>
        <div><div class="num"><?= (int)$counts['expired'] ?></div><div class="lbl">Expired</div></div>
    </div>
</div>

<!-- ==========================================================
     TABS
     ========================================================== -->
<div class="anc-tabs">
    <a href="?page=ess-admin&subpage=ess-announcements&tab=all" class="anc-tab <?= $filterTab === 'all' ? 'active' : '' ?>">
        <i class="fas fa-list"></i> All <span class="badge"><?= (int)$counts['total'] ?></span>
    </a>
    <a href="?page=ess-admin&subpage=ess-announcements&tab=published" class="anc-tab <?= $filterTab === 'published' ? 'active' : '' ?>">
        <i class="fas fa-check-circle"></i> Published <span class="badge"><?= (int)$counts['published'] ?></span>
    </a>
    <a href="?page=ess-admin&subpage=ess-announcements&tab=draft" class="anc-tab <?= $filterTab === 'draft' ? 'active' : '' ?>">
        <i class="fas fa-file-pen"></i> Drafts <span class="badge"><?= (int)$counts['draft'] ?></span>
    </a>
    <a href="?page=ess-admin&subpage=ess-announcements&tab=expired" class="anc-tab <?= $filterTab === 'expired' ? 'active' : '' ?>">
        <i class="fas fa-clock-rotate-left"></i> Expired <span class="badge"><?= (int)$counts['expired'] ?></span>
    </a>
</div>

<!-- ==========================================================
     COMPOSE / EDIT FORM
     ========================================================== -->
<div class="anc-compose" id="compose">
    <div class="anc-compose-head">
        <div class="ico">
            <i class="fas fa-<?= $editItem ? 'pen-to-square' : 'plus' ?>"></i>
        </div>
        <div>
            <h2><?= $editItem ? 'Edit Announcement' : 'Create New Announcement' ?></h2>
            <p><?= $editItem ? 'Update the details below' : 'Fill out the form to broadcast a message to your employees' ?></p>
        </div>
    </div>

    <form method="POST" id="ancForm">
        <?php if ($editItem): ?>
        <input type="hidden" name="announcement_id" value="<?= (int)$editItem['id'] ?>">
        <?php endif; ?>

        <div class="anc-compose-body">

            <!-- Title -->
            <div class="anc-field">
                <label>Announcement Title *</label>
                <input type="text" name="title" required
                       placeholder="e.g. Company-wide Town Hall Meeting"
                       value="<?= htmlspecialchars($editItem['title'] ?? '') ?>">
            </div>

            <!-- Message -->
            <div class="anc-field">
                <label>Message Content *</label>
                <textarea name="content" required
                          placeholder="Write your announcement here..."><?= htmlspecialchars($editItem['content'] ?? '') ?></textarea>
            </div>

            <!-- Type chooser -->
            <div class="anc-field">
                <label>Announcement Type</label>
                <div class="anc-types">
                    <?php foreach ($typePresets as $key => $preset):
                        $checked = ($editItem['type'] ?? 'info') === $key ? 'checked' : '';
                    ?>
                    <div class="anc-type-opt">
                        <input type="radio" name="type" id="type-<?= $key ?>" value="<?= $key ?>" <?= $checked ?>>
                        <label for="type-<?= $key ?>"
                               style="<?= $checked ? "background:{$preset['bg']}; border-color:{$preset['border']}; color:{$preset['text']};" : '' ?>"
                               data-bg="<?= $preset['bg'] ?>"
                               data-border="<?= $preset['border'] ?>"
                               data-text="<?= $preset['text'] ?>"
                               data-key="<?= $key ?>">
                            <i class="fas <?= $preset['icon'] ?>"></i>
                            <?= $preset['label'] ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Row: Expiry + Publish -->
            <div class="anc-row">
                <div class="anc-field">
                    <label>Expires At <span style="font-weight:500;text-transform:none;letter-spacing:0;color:#94a3b8;">(optional)</span></label>
                    <input type="datetime-local" name="expires_at"
                           value="<?= !empty($editItem['expires_at']) ? date('Y-m-d\TH:i', strtotime($editItem['expires_at'])) : '' ?>">
                </div>
                <?php if (!$editItem): ?>
                <div class="anc-field">
                    <label>Publish</label>
                    <div class="anc-publish-toggle" style="padding-top:12px;">
                        <label class="switch" style="position:relative; width:50px; height:28px; display:inline-block;">
                            <input type="checkbox" name="publish_now" value="1" checked
                                   style="opacity:0; width:0; height:0;">
                            <span style="position:absolute; cursor:pointer; inset:0; background:#cbd5e1; border-radius:34px; transition:.3s;"
                                  class="tgl-slider"></span>
                        </label>
                        <span>Publish immediately</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Target Departments -->
            <div class="anc-field">
                <label>Target Departments <span style="font-weight:500;text-transform:none;letter-spacing:0;color:#94a3b8;">(leave empty for ALL)</span></label>
                <div class="anc-chips">
                    <?php
                    $editDepts = !empty($editItem['target_departments']) ? explode(',', $editItem['target_departments']) : [];
                    foreach ($departments as $d):
                        $checked = in_array($d['department_code'], $editDepts) ? 'checked' : '';
                    ?>
                    <div class="anc-chip">
                        <input type="checkbox" name="target_departments[]" id="d-<?= htmlspecialchars($d['department_code']) ?>"
                               value="<?= htmlspecialchars($d['department_code']) ?>" <?= $checked ?>>
                        <label for="d-<?= htmlspecialchars($d['department_code']) ?>">
                            <?= htmlspecialchars($d['department_name']) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Target Roles -->
            <div class="anc-field">
                <label>Target Roles <span style="font-weight:500;text-transform:none;letter-spacing:0;color:#94a3b8;">(leave empty for ALL)</span></label>
                <div class="anc-chips">
                    <?php
                    $editRoles = !empty($editItem['target_roles']) ? explode(',', $editItem['target_roles']) : [];
                    $roles = ['admin' => 'Admin', 'manager' => 'Manager', 'driver' => 'Driver', 'dispatcher' => 'Dispatcher', 'customer' => 'Customer'];
                    foreach ($roles as $key => $label):
                        $checked = in_array($key, $editRoles) ? 'checked' : '';
                    ?>
                    <div class="anc-chip">
                        <input type="checkbox" name="target_roles[]" id="r-<?= $key ?>" value="<?= $key ?>" <?= $checked ?>>
                        <label for="r-<?= $key ?>"><?= $label ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <div class="anc-compose-foot">
            <div></div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php if ($editItem): ?>
                <a href="?page=ess-admin&subpage=ess-announcements" class="anc-btn ghost">
                    <i class="fas fa-xmark"></i> Cancel
                </a>
                <button type="submit" name="update_announcement" class="anc-btn primary">
                    <i class="fas fa-save"></i> Update Announcement
                </button>
                <?php else: ?>
                <button type="reset" class="anc-btn ghost">
                    <i class="fas fa-rotate-left"></i> Clear
                </button>
                <button type="submit" name="create_announcement" class="anc-btn primary">
                    <i class="fas fa-paper-plane"></i> Publish Announcement
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- ==========================================================
     ANNOUNCEMENTS FEED
     ========================================================== -->
<?php if (empty($announcements)): ?>
    <div class="anc-empty">
        <div class="icon-wrap"><i class="fas fa-bullhorn"></i></div>
        <h3>No announcements yet</h3>
        <p>Create your first announcement to keep employees informed.</p>
        <a href="#compose" class="anc-btn primary" style="margin-top:10px;">
            <i class="fas fa-plus"></i> Create First Announcement
        </a>
    </div>
<?php else: ?>
    <div class="anc-feed">
        <?php foreach ($announcements as $a):
            $isExpired = !empty($a['expires_at']) && strtotime($a['expires_at']) < time();
            $typeInfo  = $typePresets[$a['type']] ?? $typePresets['info'];

            $statusClass = 'draft';
            $statusLabel = 'Draft';
            if ($isExpired) { $statusClass = 'expired'; $statusLabel = 'Expired'; }
            elseif ($a['is_published']) { $statusClass = 'published'; $statusLabel = 'Published'; }
        ?>
        <div class="anc-card">
            <div class="anc-card-strip" style="background: <?= $typeInfo['gradient'] ?>;"></div>
            <div class="anc-card-inner">

                <div class="anc-card-head">
                    <div class="anc-card-title-wrap">
                        <span class="anc-card-type"
                              style="background:<?= $typeInfo['bg'] ?>; color:<?= $typeInfo['text'] ?>;">
                            <i class="fas <?= $typeInfo['icon'] ?>"></i>
                            <?= $typeInfo['label'] ?>
                        </span>
                        <h3 class="anc-card-title"><?= htmlspecialchars($a['title']) ?></h3>
                        <div class="anc-card-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($a['author_name'] ?? 'System') ?></span>
                            <span><i class="fas fa-clock"></i> <?= date('M j, Y · g:i A', strtotime($a['created_at'])) ?></span>
                            <?php if ($a['published_at'] && $a['is_published']): ?>
                            <span><i class="fas fa-rocket"></i> Published <?= date('M j, Y', strtotime($a['published_at'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="anc-card-status-pill <?= $statusClass ?>"><?= $statusLabel ?></span>
                </div>

                <div class="anc-card-content"><?= nl2br(htmlspecialchars($a['content'])) ?></div>

                <!-- Target tags -->
                <div class="anc-card-tags">
                    <?php if (!empty($a['target_departments'])): ?>
                        <span class="anc-card-tag dept">
                            <i class="fas fa-building"></i> <?= htmlspecialchars($a['target_departments']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($a['target_roles'])): ?>
                        <span class="anc-card-tag role">
                            <i class="fas fa-users"></i> <?= htmlspecialchars($a['target_roles']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($a['expires_at'])): ?>
                        <span class="anc-card-tag exp">
                            <i class="fas fa-hourglass-half"></i> Expires <?= date('M j, Y · g:i A', strtotime($a['expires_at'])) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (empty($a['target_departments']) && empty($a['target_roles'])): ?>
                        <span class="anc-card-tag all">
                            <i class="fas fa-globe"></i> Everyone
                        </span>
                    <?php endif; ?>
                </div>

                <div class="anc-card-actions">
                    <a href="?page=ess-admin&subpage=ess-announcements&edit=<?= $a['id'] ?>#compose"
                       class="anc-action-btn edit">
                        <i class="fas fa-pen-to-square"></i> Edit
                    </a>
                    <a href="?page=ess-admin&subpage=ess-announcements&toggle_publish=<?= $a['id'] ?>&tab=<?= $filterTab ?>"
                       class="anc-action-btn <?= $a['is_published'] ? 'unpublish' : 'publish' ?>">
                        <i class="fas fa-<?= $a['is_published'] ? 'eye-slash' : 'eye' ?>"></i>
                        <?= $a['is_published'] ? 'Unpublish' : 'Publish' ?>
                    </a>
                    <a href="?page=ess-admin&subpage=ess-announcements&delete=<?= $a['id'] ?>&tab=<?= $filterTab ?>"
                       class="anc-action-btn delete"
                       onclick="return confirm('Delete this announcement permanently?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
// Type chooser visual feedback
document.querySelectorAll('input[name="type"]').forEach(function(input) {
    input.addEventListener('change', function() {
        document.querySelectorAll('.anc-type-opt label').forEach(function(lbl) {
            lbl.style.background = '#f8fafc';
            lbl.style.borderColor = '#eef2f6';
            lbl.style.color = '#64748b';
        });
        if (this.checked) {
            const lbl = document.querySelector('label[for="' + this.id + '"]');
            if (lbl) {
                lbl.style.background = lbl.dataset.bg;
                lbl.style.borderColor = lbl.dataset.border;
                lbl.style.color = lbl.dataset.text;
            }
        }
    });
});

// Publish toggle
document.querySelectorAll('.anc-publish-toggle input[type=checkbox]').forEach(function(cb) {
    const slider = cb.parentElement.querySelector('.tgl-slider');
    function update() {
        if (cb.checked) {
            slider.style.background = 'linear-gradient(135deg,#0e4c92,#4086e4)';
        } else {
            slider.style.background = '#cbd5e1';
        }
        // Handle the knob
        let knob = slider.querySelector('.tgl-knob');
        if (!knob) {
            knob = document.createElement('span');
            knob.className = 'tgl-knob';
            knob.style.cssText = 'position:absolute; height:22px; width:22px; left:3px; bottom:3px; background:white; border-radius:50%; transition:.3s;';
            slider.appendChild(knob);
        }
        knob.style.transform = cb.checked ? 'translateX(22px)' : 'translateX(0)';
    }
    cb.addEventListener('change', update);
    update();
});

// Scroll to compose when editing
<?php if ($editItem): ?>
document.getElementById('compose').scrollIntoView({ behavior: 'smooth', block: 'start' });
<?php endif; ?>
</script>