<?php
// /modules/ess/training.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];

// Assigned trainings
$trainings = $pdo->prepare("
    SELECT et.*, tm.title, tm.description, tm.duration_hours, tm.department
    FROM employee_training et
    JOIN training_modules tm ON et.training_id = tm.id
    WHERE et.new_hire_id = ?
    ORDER BY et.created_at DESC
");
$trainings->execute([$empId]);
$trainings = $trainings->fetchAll();

// Available trainings (based on department)
$dept = $emp['department'] ?? 'all';
$available = $pdo->prepare("
    SELECT * FROM training_modules
    WHERE department IN (?, 'all')
      AND id NOT IN (
          SELECT training_id FROM employee_training WHERE new_hire_id = ?
      )
    ORDER BY title
");
$available->execute([$dept, $empId]);
$available = $available->fetchAll();
?>

<style>
.ess-page-header { background:linear-gradient(135deg,#0e4c92,#4086e4); border-radius:24px; padding:32px 36px; margin-bottom:25px; color:white; position:relative; overflow:hidden; box-shadow:0 20px 40px rgba(14,76,146,0.2); }
.ess-page-header::before { content:''; position:absolute; width:240px; height:240px; background:rgba(255,255,255,0.08); border-radius:50%; top:-100px; right:-60px; }
.ess-page-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.ess-page-header .hdr-icon { width:60px; height:60px; background:rgba(255,255,255,0.18); border:1.5px solid rgba(255,255,255,0.3); border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:26px; }
.ess-page-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; }
.ess-page-header p { font-size:13px; margin:0; opacity:0.85; }

.ess-panel { background:white; border-radius:22px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.05); border:1px solid #eef2f6; margin-bottom:22px; }
.ess-panel-head { padding:18px 24px; background:linear-gradient(135deg,#f8fafd 0%,#f1f5f9 100%); border-bottom:1px solid #eef2f6; display:flex; align-items:center; gap:14px; }
.ess-panel-head .ico { width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 6px 15px rgba(14,76,146,0.25); }
.ess-panel-head h3 { margin:0; font-size:15px; font-weight:700; color:#1e293b; }
.ess-panel-body { padding:24px; }

.tr-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.tr-card { background:#f8fafd; border:1px solid #eef2f6; border-radius:16px; padding:20px; transition:all 0.3s; display:flex; flex-direction:column; gap:12px; }
.tr-card:hover { background:white; border-color:#0e4c92; transform:translateY(-3px); box-shadow:0 12px 30px rgba(14,76,146,0.08); }
.tr-head { display:flex; align-items:center; gap:12px; }
.tr-icon { width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.tr-title { font-size:14.5px; font-weight:700; color:#1e293b; }
.tr-meta { font-size:11.5px; color:#94a3b8; margin-top:3px; }
.tr-desc { font-size:12.5px; color:#64748b; line-height:1.55; }
.tr-footer { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:auto; }
.tr-status { padding:4px 11px; border-radius:20px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
.tr-status.completed { background:#dcfce7; color:#166534; }
.tr-status.pending   { background:#fef3c7; color:#92400e; }
.tr-status.available { background:#dbeafe; color:#1e40af; }
.tr-btn { padding:8px 14px; border-radius:10px; background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; text-decoration:none; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:6px; border:none; cursor:pointer; font-family:inherit; }
.tr-btn:hover { transform:translateY(-1px); box-shadow:0 6px 15px rgba(14,76,146,0.25); }

.ess-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.ess-empty i { font-size:40px; color:#cbd5e1; margin-bottom:12px; display:block; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-graduation-cap"></i></div>
        <div>
            <h1>Training &amp; Learning</h1>
            <p>View your assigned trainings and available courses</p>
        </div>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-book"></i></div>
        <h3>My Assigned Trainings</h3>
    </div>
    <div class="ess-panel-body">
        <?php if (empty($trainings)): ?>
        <div class="ess-empty">
            <i class="fas fa-graduation-cap"></i>
            No trainings assigned yet.
        </div>
        <?php else: ?>
        <div class="tr-grid">
            <?php foreach ($trainings as $t): ?>
            <div class="tr-card">
                <div class="tr-head">
                    <div class="tr-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div>
                        <div class="tr-title"><?= htmlspecialchars($t['title']) ?></div>
                        <div class="tr-meta"><i class="fas fa-clock"></i> <?= (int)$t['duration_hours'] ?> hours</div>
                    </div>
                </div>
                <?php if ($t['description']): ?>
                <div class="tr-desc"><?= htmlspecialchars($t['description']) ?></div>
                <?php endif; ?>
                <div class="tr-footer">
                    <span class="tr-status <?= $t['completed'] ? 'completed' : 'pending' ?>">
                        <?= $t['completed'] ? 'Completed' : 'In Progress' ?>
                    </span>
                    <?php if ($t['completion_date']): ?>
                    <span style="font-size:11px;color:#94a3b8;"><?= date('M j, Y', strtotime($t['completion_date'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($available)): ?>
<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-book-open"></i></div>
        <h3>Available Trainings</h3>
    </div>
    <div class="ess-panel-body">
        <div class="tr-grid">
            <?php foreach ($available as $a): ?>
            <div class="tr-card">
                <div class="tr-head">
                    <div class="tr-icon"><i class="fas fa-book"></i></div>
                    <div>
                        <div class="tr-title"><?= htmlspecialchars($a['title']) ?></div>
                        <div class="tr-meta"><i class="fas fa-clock"></i> <?= (int)$a['duration_hours'] ?> hours</div>
                    </div>
                </div>
                <?php if ($a['description']): ?>
                <div class="tr-desc"><?= htmlspecialchars($a['description']) ?></div>
                <?php endif; ?>
                <div class="tr-footer">
                    <span class="tr-status available">Available</span>
                    <button class="tr-btn" onclick="alert('Please coordinate with HR to enroll in this training.')">
                        <i class="fas fa-info-circle"></i> Enroll
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>