<?php
// /modules/ess/attendance.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];

$month = $_GET['month'] ?? date('Y-m');
$firstDay = $month . '-01';
$lastDay = date('Y-m-t', strtotime($firstDay));

$records = $pdo->prepare("
    SELECT * FROM attendance
    WHERE employee_id = ? AND date BETWEEN ? AND ?
    ORDER BY date DESC
");
$records->execute([$empId, $firstDay, $lastDay]);
$records = $records->fetchAll();

// Stats
$present = count(array_filter($records, fn($r) => $r['status'] === 'present'));
$absent  = count(array_filter($records, fn($r) => $r['status'] === 'absent'));
$late    = count(array_filter($records, fn($r) => $r['status'] === 'late'));
$totalHours = array_sum(array_column($records, 'hours_worked'));

// Today's attendance
$today = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()");
$today->execute([$empId]);
$todayRecord = $today->fetch();

$statusColors = [
    'present'  => ['bg' => '#dcfce7', 'text' => '#166534', 'icon' => 'fa-check-circle'],
    'absent'   => ['bg' => '#fee2e2', 'text' => '#991b1b', 'icon' => 'fa-times-circle'],
    'late'     => ['bg' => '#fef3c7', 'text' => '#92400e', 'icon' => 'fa-clock'],
    'half_day' => ['bg' => '#e0e7ff', 'text' => '#3730a3', 'icon' => 'fa-adjust'],
    'leave'    => ['bg' => '#dbeafe', 'text' => '#1e40af', 'icon' => 'fa-plane'],
    'holiday'  => ['bg' => '#fae8ff', 'text' => '#86198f', 'icon' => 'fa-star'],
];
?>

<style>
.ess-page-header { background:linear-gradient(135deg,#0e4c92,#4086e4); border-radius:24px; padding:32px 36px; margin-bottom:25px; color:white; position:relative; overflow:hidden; box-shadow:0 20px 40px rgba(14,76,146,0.2); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; }
.ess-page-header::before { content:''; position:absolute; width:240px; height:240px; background:rgba(255,255,255,0.08); border-radius:50%; top:-100px; right:-60px; }
.ess-page-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.ess-page-header .hdr-icon { width:60px; height:60px; background:rgba(255,255,255,0.18); border:1.5px solid rgba(255,255,255,0.3); border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:26px; }
.ess-page-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; }
.ess-page-header p { font-size:13px; margin:0; opacity:0.85; }
.ess-page-header .month-selector { position:relative; z-index:1; display:flex; gap:8px; }
.ess-page-header .month-selector input { padding:10px 16px; border:1.5px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.15); border-radius:12px; color:white; font-size:13px; font-family:inherit; backdrop-filter:blur(10px); }
.ess-page-header .month-selector input::-webkit-calendar-picker-indicator { filter:invert(1); }
.ess-page-header .month-selector button { padding:10px 18px; background:white; color:#0e4c92; border:none; border-radius:12px; font-weight:600; font-size:13px; cursor:pointer; }

.ess-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:22px; }
.ess-stat { background:white; border-radius:18px; padding:18px 20px; display:flex; align-items:center; gap:14px; box-shadow:0 6px 20px rgba(0,0,0,0.04); border:1px solid #eef2f6; transition:all 0.3s; }
.ess-stat:hover { transform:translateY(-3px); box-shadow:0 12px 28px rgba(14,76,146,0.08); }
.ess-stat .ico { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.ess-stat .ico.green  { background:rgba(22,163,74,0.12); color:#16a34a; }
.ess-stat .ico.red    { background:rgba(220,38,38,0.12); color:#dc2626; }
.ess-stat .ico.amber  { background:rgba(245,158,11,0.12);color:#d97706; }
.ess-stat .ico.blue   { background:rgba(14,76,146,0.1);  color:#0e4c92; }
.ess-stat .num { font-size:22px; font-weight:700; color:#1e293b; line-height:1.1; }
.ess-stat .lbl { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.6px; font-weight:600; margin-top:2px; }

.ess-panel { background:white; border-radius:22px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.05); border:1px solid #eef2f6; margin-bottom:22px; }
.ess-panel-head { padding:18px 24px; background:linear-gradient(135deg,#f8fafd 0%,#f1f5f9 100%); border-bottom:1px solid #eef2f6; display:flex; align-items:center; gap:14px; }
.ess-panel-head .ico { width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 6px 15px rgba(14,76,146,0.25); }
.ess-panel-head h3 { margin:0; font-size:15px; font-weight:700; color:#1e293b; }
.ess-panel-body { padding:0; }

.att-table { width:100%; border-collapse:collapse; }
.att-table th { text-align:left; padding:14px 20px; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; background:#f8fafd; border-bottom:1px solid #eef2f6; }
.att-table td { padding:16px 20px; font-size:13.5px; color:#334155; border-bottom:1px solid #f1f5f9; }
.att-table tr:hover td { background:#f8fafd; }
.att-table tr:last-child td { border-bottom:none; }
.att-table .status-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }

.ess-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.ess-empty i { font-size:40px; color:#cbd5e1; margin-bottom:12px; display:block; }

.today-card {
    background:linear-gradient(135deg,#0e4c92,#4086e4);
    border-radius:20px; padding:24px 28px; color:white;
    display:flex; justify-content:space-between; align-items:center;
    gap:20px; flex-wrap:wrap; margin-bottom:22px;
    box-shadow:0 15px 40px rgba(14,76,146,0.2);
}
.today-card .info-block .lbl { font-size:11px; text-transform:uppercase; letter-spacing:0.6px; opacity:0.8; margin-bottom:6px; font-weight:600; }
.today-card .info-block .val { font-size:22px; font-weight:700; }
.today-card .status-now { display:flex; align-items:center; gap:10px; padding:12px 20px; background:rgba(255,255,255,0.15); border:1.5px solid rgba(255,255,255,0.3); border-radius:14px; backdrop-filter:blur(10px); font-weight:600; }

@media (max-width:640px) {
    .ess-page-header { padding:24px; }
    .ess-page-header h1 { font-size:20px; }
    .att-table th:nth-child(5), .att-table td:nth-child(5) { display:none; }
}
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-fingerprint"></i></div>
        <div>
            <h1>My Attendance</h1>
            <p>Track your daily attendance and work hours</p>
        </div>
    </div>
    <form method="GET" class="month-selector">
        <input type="hidden" name="page" value="time">
        <input type="hidden" name="subpage" value="attendance">
        <input type="month" name="month" value="<?= htmlspecialchars($month) ?>">
        <button type="submit"><i class="fas fa-search"></i> Filter</button>
    </form>
</div>

<?php if ($todayRecord): ?>
<div class="today-card">
    <div class="info-block">
        <div class="lbl">Time In</div>
        <div class="val"><?= $todayRecord['time_in'] ? date('g:i A', strtotime($todayRecord['time_in'])) : '—' ?></div>
    </div>
    <div class="info-block">
        <div class="lbl">Time Out</div>
        <div class="val"><?= $todayRecord['time_out'] ? date('g:i A', strtotime($todayRecord['time_out'])) : '—' ?></div>
    </div>
    <div class="info-block">
        <div class="lbl">Hours Worked</div>
        <div class="val"><?= $todayRecord['hours_worked'] ? number_format($todayRecord['hours_worked'], 2) : '0.00' ?>h</div>
    </div>
    <div class="status-now">
        <i class="fas <?= $statusColors[$todayRecord['status']]['icon'] ?? 'fa-circle' ?>"></i>
        <?= ucfirst(str_replace('_', ' ', $todayRecord['status'])) ?>
    </div>
</div>
<?php endif; ?>

<div class="ess-stats">
    <div class="ess-stat">
        <div class="ico green"><i class="fas fa-check-circle"></i></div>
        <div><div class="num"><?= $present ?></div><div class="lbl">Present</div></div>
    </div>
    <div class="ess-stat">
        <div class="ico amber"><i class="fas fa-clock"></i></div>
        <div><div class="num"><?= $late ?></div><div class="lbl">Late</div></div>
    </div>
    <div class="ess-stat">
        <div class="ico red"><i class="fas fa-times-circle"></i></div>
        <div><div class="num"><?= $absent ?></div><div class="lbl">Absent</div></div>
    </div>
    <div class="ess-stat">
        <div class="ico blue"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="num"><?= number_format($totalHours, 1) ?>h</div><div class="lbl">Total Hours</div></div>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-list"></i></div>
        <h3>Attendance Records — <?= date('F Y', strtotime($firstDay)) ?></h3>
    </div>
    <div class="ess-panel-body">
        <?php if (empty($records)): ?>
        <div class="ess-empty">
            <i class="fas fa-calendar-times"></i>
            No attendance records for this month.
        </div>
        <?php else: ?>
        <table class="att-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r):
                    $sc = $statusColors[$r['status']] ?? ['bg'=>'#f1f5f9','text'=>'#64748b','icon'=>'fa-circle'];
                ?>
                <tr>
                    <td><strong><?= date('D, M j, Y', strtotime($r['date'])) ?></strong></td>
                    <td><?= $r['time_in'] ? date('g:i A', strtotime($r['time_in'])) : '—' ?></td>
                    <td><?= $r['time_out'] ? date('g:i A', strtotime($r['time_out'])) : '—' ?></td>
                    <td><?= $r['hours_worked'] ? number_format($r['hours_worked'], 2) . 'h' : '—' ?></td>
                    <td>
                        <span class="status-badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['text'] ?>;">
                            <i class="fas <?= $sc['icon'] ?>"></i>
                            <?= ucfirst(str_replace('_',' ',$r['status'])) ?>
                        </span>
                    </td>
                    <td style="color:#94a3b8;font-size:12px;"><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>