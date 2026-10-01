<?php
// /modules/ess/performance.php
$emp = essGetEmployee($pdo);
$empId = $emp['id'];

// Get performance reviews if any
$reviews = $pdo->prepare("
    SELECT * FROM performance_reviews
    WHERE employee_id = ?
    ORDER BY review_date DESC
");
$reviews->execute([$empId]);
$reviews = $reviews->fetchAll();
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

.review-list { display:flex; flex-direction:column; gap:14px; }
.review-card { padding:20px; background:#f8fafd; border:1px solid #eef2f6; border-radius:14px; }
.review-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:12px; }
.review-title { font-size:15px; font-weight:700; color:#1e293b; }
.review-meta { font-size:12px; color:#64748b; margin-top:4px; }
.review-rating { font-size:26px; font-weight:800; color:#0e4c92; }
.review-body { font-size:13px; color:#475569; line-height:1.6; padding:12px; background:white; border-radius:10px; border-left:3px solid #0e4c92; }

.ess-empty { text-align:center; padding:60px 30px; color:#94a3b8; }
.ess-empty .icon-wrap { width:80px; height:80px; margin:0 auto 20px; background:linear-gradient(135deg,#eef2ff,#e0e7ff); border-radius:24px; display:flex; align-items:center; justify-content:center; font-size:32px; color:#6366f1; }
.ess-empty h3 { font-size:18px; color:#1e293b; margin:0 0 8px; font-weight:700; }
.ess-empty p { font-size:13px; color:#64748b; margin:0; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-trophy"></i></div>
        <div>
            <h1>My Performance</h1>
            <p>View your performance reviews, goals, and feedback</p>
        </div>
    </div>
</div>

<div class="ess-panel">
    <div class="ess-panel-head">
        <div class="ico"><i class="fas fa-chart-line"></i></div>
        <h3>Performance Reviews</h3>
    </div>
    <div class="ess-panel-body">
        <?php if (empty($reviews)): ?>
        <div class="ess-empty">
            <div class="icon-wrap"><i class="fas fa-chart-line"></i></div>
            <h3>No reviews yet</h3>
            <p>Your performance reviews will appear here once your supervisor submits them.</p>
        </div>
        <?php else: ?>
        <div class="review-list">
            <?php foreach ($reviews as $r): ?>
            <div class="review-card">
                <div class="review-head">
                    <div>
                        <div class="review-title"><?= ucfirst($r['review_type']) ?> Review</div>
                        <div class="review-meta">
                            <i class="fas fa-calendar"></i> <?= date('F j, Y', strtotime($r['review_date'])) ?>
                        </div>
                    </div>
                    <div class="review-rating"><?= number_format((float)$r['overall_rating'], 1) ?>/5</div>
                </div>
                <?php if (!empty($r['strengths'])): ?>
                <div class="review-body" style="border-left-color:#16a34a;">
                    <strong style="color:#166534;">Strengths:</strong> <?= htmlspecialchars($r['strengths']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($r['improvements'])): ?>
                <div class="review-body" style="border-left-color:#f59e0b;margin-top:8px;">
                    <strong style="color:#92400e;">Areas for Improvement:</strong> <?= htmlspecialchars($r['improvements']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($r['comments'])): ?>
                <div class="review-body" style="margin-top:8px;">
                    <strong>Comments:</strong> <?= htmlspecialchars($r['comments']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>