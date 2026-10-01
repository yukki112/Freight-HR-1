<?php
// /modules/ess/my-employment.php
$emp = essGetEmployee($pdo);

// Employment history
$history = $pdo->prepare("
    SELECT * FROM employment_history
    WHERE employee_id = ?
    ORDER BY effective_date DESC
");
$history->execute([$emp['id']]);
$history = $history->fetchAll();
?>

<style>
.ess-page-header {
    background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%);
    border-radius: 24px; padding: 32px 36px; margin-bottom: 25px;
    color: white; position: relative; overflow: hidden;
    box-shadow: 0 20px 40px rgba(14,76,146,0.2);
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
    font-size:26px;
}
.ess-page-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; }
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

.emp-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
.emp-item {
    padding:16px 18px; background:#f8fafd;
    border:1px solid #eef2f6; border-radius:12px;
    display:flex; align-items:center; gap:14px;
}
.emp-item .item-icon {
    width:42px; height:42px; border-radius:12px;
    background:rgba(14,76,146,0.1); color:#0e4c92;
    display:flex; align-items:center; justify-content:center;
    font-size:16px; flex-shrink:0;
}
.emp-item .item-info { flex:1; min-width:0; }
.emp-item label {
    display:block; font-size:10.5px; font-weight:700;
    color:#94a3b8; text-transform:uppercase;
    letter-spacing:0.5px; margin-bottom:3px;
}
.emp-item .value { font-size:14px; font-weight:600; color:#1e293b; }

.timeline { position:relative; padding-left:30px; }
.timeline::before {
    content:''; position:absolute; left:8px; top:8px; bottom:8px;
    width:2px; background:linear-gradient(180deg,#0e4c92,#4086e4);
    border-radius:2px;
}
.timeline-item {
    position:relative; padding:14px 0 14px 22px;
    border-bottom:1px dashed #e2e8f0;
}
.timeline-item:last-child { border-bottom:none; }
.timeline-item::before {
    content:''; position:absolute; left:-27px; top:20px;
    width:12px; height:12px; border-radius:50%;
    background:white; border:3px solid #0e4c92;
    box-shadow:0 0 0 3px rgba(14,76,146,0.1);
}
.timeline-item .t-date { font-size:11.5px; color:#94a3b8; font-weight:600; margin-bottom:5px; }
.timeline-item .t-title { font-size:14px; font-weight:700; color:#1e293b; margin-bottom:4px; }
.timeline-item .t-desc { font-size:12.5px; color:#64748b; line-height:1.6; }
.timeline-item .t-tag {
    display:inline-block; margin-top:6px;
    background:#e0edff; color:#0e4c92;
    padding:3px 10px; border-radius:20px;
    font-size:10.5px; font-weight:700;
    text-transform:uppercase; letter-spacing:0.4px;
}

.ess-empty {
    text-align:center; padding:40px 20px; color:#94a3b8;
}
.ess-empty i { font-size:40px; color:#cbd5e1; margin-bottom:12px; display:block; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-briefcase"></i></div>
        <div>
            <h1>My Employment Information</h1>
            <p>Your role, department, and employment history</p>
        </div>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-info-circle"></i></div>
        <h3>Current Employment Details</h3>
    </div>
    <div class="ess-panel-body">
        <div class="emp-grid">
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-id-badge"></i></div>
                <div class="item-info">
                    <label>Employee ID</label>
                    <div class="value"><?= htmlspecialchars($emp['employee_id'] ?? '—') ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-user-tie"></i></div>
                <div class="item-info">
                    <label>Position</label>
                    <div class="value"><?= htmlspecialchars($emp['position'] ?? '—') ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-building"></i></div>
                <div class="item-info">
                    <label>Department</label>
                    <div class="value"><?= htmlspecialchars(ucfirst($emp['department'] ?? '—')) ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-user-clock"></i></div>
                <div class="item-info">
                    <label>Employment Status</label>
                    <div class="value"><?= htmlspecialchars(ucfirst($emp['employment_status'] ?? '—')) ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-file-contract"></i></div>
                <div class="item-info">
                    <label>Employment Type</label>
                    <div class="value"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $emp['employment_type'] ?? '—'))) ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="item-info">
                    <label>Date Hired</label>
                    <div class="value"><?= $emp['hire_date'] ? date('F j, Y', strtotime($emp['hire_date'])) : '—' ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="item-info">
                    <label>Start Date</label>
                    <div class="value"><?= $emp['start_date'] ? date('F j, Y', strtotime($emp['start_date'])) : '—' ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-flag-checkered"></i></div>
                <div class="item-info">
                    <label>Probation End</label>
                    <div class="value"><?= $emp['probation_end_date'] ? date('F j, Y', strtotime($emp['probation_end_date'])) : '—' ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="item-info">
                    <label>Work Location</label>
                    <div class="value"><?= htmlspecialchars($emp['work_location'] ?? '—') ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-code-branch"></i></div>
                <div class="item-info">
                    <label>Branch</label>
                    <div class="value"><?= htmlspecialchars($emp['branch'] ?? '—') ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-layer-group"></i></div>
                <div class="item-info">
                    <label>Level</label>
                    <div class="value"><?= htmlspecialchars($emp['employee_level'] ?? '—') ?></div>
                </div>
            </div>
            <div class="emp-item">
                <div class="item-icon"><i class="fas fa-tags"></i></div>
                <div class="item-info">
                    <label>Category</label>
                    <div class="value"><?= htmlspecialchars($emp['employee_category'] ?? '—') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-history"></i></div>
        <h3>Employment History</h3>
    </div>
    <div class="ess-panel-body">
        <?php if (empty($history)): ?>
        <div class="ess-empty">
            <i class="fas fa-history"></i>
            No employment history yet.
        </div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($history as $h): ?>
            <div class="timeline-item">
                <div class="t-date">
                    <i class="fas fa-calendar"></i>
                    <?= date('F j, Y', strtotime($h['effective_date'])) ?>
                </div>
                <div class="t-title">
                    <?= ucfirst(str_replace('_', ' ', $h['change_type'])) ?>
                    <?php if ($h['new_position'] && $h['new_position'] !== $h['old_position']): ?>
                        — <?= htmlspecialchars($h['new_position']) ?>
                    <?php endif; ?>
                </div>
                <div class="t-desc">
                    <?php if ($h['old_position'] && $h['new_position']): ?>
                        <strong><?= htmlspecialchars($h['old_position']) ?></strong>
                        → <strong><?= htmlspecialchars($h['new_position']) ?></strong>
                    <?php endif; ?>
                    <?php if ($h['reason']): ?><br><?= htmlspecialchars($h['reason']) ?><?php endif; ?>
                </div>
                <?php if ($h['new_status']): ?>
                <span class="t-tag"><?= htmlspecialchars($h['new_status']) ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>