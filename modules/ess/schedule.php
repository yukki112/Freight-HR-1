<?php
// /modules/ess/schedule.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];

// For now, generate a placeholder schedule (Mon-Fri 9AM-6PM)
// In production, replace with an actual schedule table
$schedules = [];
$startOfWeek = strtotime('monday this week');
for ($i = 0; $i < 7; $i++) {
    $day = $startOfWeek + ($i * 86400);
    $isWeekend = in_array(date('N', $day), [6, 7]);
    $schedules[] = [
        'date'      => date('Y-m-d', $day),
        'day_name'  => date('l', $day),
        'shift'     => $isWeekend ? 'Rest Day' : 'Morning Shift',
        'time_in'   => $isWeekend ? null : '09:00',
        'time_out'  => $isWeekend ? null : '18:00',
        'is_rest'   => $isWeekend,
    ];
}
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

.sched-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; }
.sched-card { background:linear-gradient(135deg,#f8fafd,#eff6ff); border:1px solid #eef2f6; border-radius:16px; padding:20px; text-align:center; transition:all 0.3s; position:relative; overflow:hidden; }
.sched-card:hover { transform:translateY(-3px); box-shadow:0 12px 30px rgba(14,76,146,0.1); }
.sched-card.rest { background:linear-gradient(135deg,#f8fafd,#f1f5f9); }
.sched-card.today { background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; border-color:#0e4c92; }
.sched-card.today::before { content:'TODAY'; position:absolute; top:10px; right:10px; background:rgba(255,255,255,0.2); color:white; padding:3px 10px; border-radius:20px; font-size:9px; font-weight:800; letter-spacing:0.5px; }
.sched-card .day-name { font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:0.6px; font-weight:700; margin-bottom:8px; }
.sched-card.today .day-name { color:rgba(255,255,255,0.85); }
.sched-card .date { font-size:24px; font-weight:800; color:#0e4c92; margin-bottom:12px; }
.sched-card.today .date { color:white; }
.sched-card .shift-name { font-size:13px; font-weight:700; color:#1e293b; margin-bottom:6px; }
.sched-card.today .shift-name { color:white; }
.sched-card .shift-time { font-size:12px; color:#64748b; font-weight:600; }
.sched-card.today .shift-time { color:rgba(255,255,255,0.85); }
.sched-card .rest-icon { font-size:20px; color:#94a3b8; margin-bottom:8px; display:block; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-calendar-alt"></i></div>
        <div>
            <h1>My Work Schedule</h1>
            <p>Your weekly shift schedule</p>
        </div>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-calendar-week"></i></div>
        <h3>Week of <?= date('M j, Y', strtotime('monday this week')) ?></h3>
    </div>
    <div class="ess-panel-body">
        <div class="sched-grid">
            <?php foreach ($schedules as $s):
                $isToday = $s['date'] === date('Y-m-d');
            ?>
            <div class="sched-card <?= $s['is_rest'] ? 'rest' : '' ?> <?= $isToday ? 'today' : '' ?>">
                <div class="day-name"><?= $s['day_name'] ?></div>
                <div class="date"><?= date('j', strtotime($s['date'])) ?></div>
                <?php if ($s['is_rest']): ?>
                    <i class="fas fa-bed rest-icon"></i>
                    <div class="shift-name"><?= $s['shift'] ?></div>
                <?php else: ?>
                    <div class="shift-name"><?= $s['shift'] ?></div>
                    <div class="shift-time">
                        <i class="fas fa-clock"></i>
                        <?= date('g:i A', strtotime($s['time_in'])) ?> - <?= date('g:i A', strtotime($s['time_out'])) ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>