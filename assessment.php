<?php
// assessment.php — Public page (NO login required — access via token)
require_once __DIR__ . '/includes/config.php';        // adjust path if needed
require_once __DIR__ . '/includes/assessment_questions.php';
require_once __DIR__ . '/includes/assessment_rules.php';

date_default_timezone_set('Asia/Manila');

$app_number = $_GET['app']   ?? '';
$token      = $_GET['token'] ?? '';
$error      = '';
$applicant  = null;

if (empty($app_number) || empty($token)) {
    $error = "Invalid or missing assessment link.";
} else {
    // Fetch applicant + job details
    $stmt = $pdo->prepare("
        SELECT a.*, jp.title AS job_title, jp.department AS job_department
        FROM job_applications a
        LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
        WHERE a.application_number = ?
    ");
    $stmt->execute([$app_number]);
    $applicant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$applicant) {
        $error = "Applicant not found.";
    } elseif ($applicant['assessment_status'] === 'passed') {
        $error = "You have already passed this assessment. Please wait for the interview invitation.";
    } elseif ($applicant['assessment_status'] === 'failed') {
        $error = "You have already taken this assessment. Please contact HR for further details.";
    } elseif ($applicant['requires_assessment'] != 1) {
        $error = "No assessment is required for your application.";
    }
}

// Load questions
$questions = [];
$assessmentType = '';
if (!$error && $applicant) {
    $questions = getAssessmentQuestions($applicant['job_department'] ?? '', $applicant['job_title'] ?? '');
    $assessmentType = getAssessmentType($applicant['job_department'] ?? '');
}

// Record attempt start (create row in assessment_results if not existing)
if (!$error && $applicant) {
    $stmt = $pdo->prepare("SELECT id, started_at FROM assessment_results WHERE applicant_id = ?");
    $stmt->execute([$applicant['id']]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $stmt = $pdo->prepare("
            INSERT INTO assessment_results
            (applicant_id, job_posting_id, department, position_title, assessment_type,
             total_questions, started_at, result)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')
        ");
        $stmt->execute([
            $applicant['id'],
            $applicant['job_posting_id'],
            $applicant['job_department'],
            $applicant['job_title'],
            $assessmentType,
            count($questions)
        ]);

        // update status to in_progress
        $pdo->prepare("UPDATE job_applications SET assessment_status = 'in_progress' WHERE id = ?")
            ->execute([$applicant['id']]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Online Assessment — Freight Management</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{
  --primary:#0e4c92; --primary-light:#4086e4; --success:#27ae60;
  --danger:#e74c3c; --warning:#f39c12; --gray:#64748b; --light:#f8fafd;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{
  font-family:'Inter','Segoe UI',Arial,sans-serif;
  background:linear-gradient(135deg,#f5f7fa 0%,#e9edf5 100%);
  min-height:100vh;color:#2c3e50;padding:20px;
}
.wrap{max-width:900px;margin:0 auto;}

/* Header */
.assess-header{
  background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);
  border-radius:22px; padding:35px; text-align:center; color:#fff;
  margin-bottom:25px; box-shadow:0 20px 40px rgba(14,76,146,0.25);
  position:relative; overflow:hidden;
}
.assess-header::before{
  content:""; position:absolute; inset:0;
  background:radial-gradient(circle at top right, rgba(255,255,255,0.15), transparent 60%);
}
.assess-header .icon{
  width:80px;height:80px;border-radius:22px;background:rgba(255,255,255,0.2);
  display:flex;align-items:center;justify-content:center;margin:0 auto 15px;
  border:2px solid rgba(255,255,255,0.3);font-size:34px;
}
.assess-header h1{font-size:26px;font-weight:700;position:relative;}
.assess-header p{margin-top:6px;opacity:0.95;font-size:14px;position:relative;}

/* Cards */
.card{
  background:#fff; border-radius:20px; padding:30px;
  box-shadow:0 10px 30px rgba(0,0,0,0.05); margin-bottom:20px;
}
.card h2{font-size:19px;margin-bottom:15px;display:flex;align-items:center;gap:10px;}
.card h2 i{color:var(--primary);}
.info-grid{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
  gap:15px;margin-top:15px;
}
.info-item{
  background:var(--light);border-radius:14px;padding:14px;border:1px solid #eef2f6;
}
.info-label{
  font-size:11px;letter-spacing:0.6px;text-transform:uppercase;
  color:var(--gray);font-weight:600;margin-bottom:4px;
}
.info-value{font-size:15px;font-weight:600;color:#2c3e50;}

/* Instructions */
.instruction-list{list-style:none;padding:0;margin-top:10px;}
.instruction-list li{
  display:flex;gap:14px;align-items:flex-start;
  padding:12px 0;border-bottom:1px dashed #eef2f6;font-size:14px;
}
.instruction-list li:last-child{border-bottom:none;}
.instruction-list .num{
  width:32px;height:32px;border-radius:10px;background:rgba(14,76,146,0.1);
  color:var(--primary);display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:13px;flex-shrink:0;
}
.tip{
  background:#fff8e1;border-left:4px solid var(--warning);
  padding:14px 16px;border-radius:10px;font-size:13px;color:#7a5c00;margin-top:15px;
}
.tip strong{display:block;margin-bottom:3px;}

/* Buttons */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:10px;
  padding:15px 34px;border:none;border-radius:14px;font-weight:600;
  font-size:15px;cursor:pointer;text-decoration:none;
  transition:transform .25s, box-shadow .25s;font-family:inherit;
}
.btn-primary{
  background:linear-gradient(135deg,var(--primary),var(--primary-light));
  color:#fff;box-shadow:0 10px 20px rgba(14,76,146,0.25);
}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 15px 30px rgba(14,76,146,0.35);}
.btn-primary:disabled{opacity:0.6;cursor:not-allowed;transform:none;}

/* Question Card */
.q-card{
  background:#fff;border-radius:18px;padding:25px;margin-bottom:18px;
  box-shadow:0 6px 18px rgba(0,0,0,0.04);border:1px solid #eef2f6;
  transition:border-color .3s;
}
.q-card.answered{border-color:rgba(39,174,96,0.3);}
.q-number{
  display:inline-block;background:rgba(14,76,146,0.1);color:var(--primary);
  padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;
  margin-bottom:12px;
}
.q-text{font-size:16px;font-weight:600;color:#2c3e50;margin-bottom:18px;line-height:1.5;}
.opt{
  display:flex;align-items:center;gap:12px;padding:13px 16px;
  border:2px solid #eef2f6;border-radius:12px;margin-bottom:9px;
  cursor:pointer;transition:all .2s;font-size:14px;color:#4a5568;
}
.opt:hover{border-color:rgba(14,76,146,0.4);background:var(--light);}
.opt input{display:none;}
.opt .circle{
  width:22px;height:22px;border-radius:50%;border:2px solid #cbd5e0;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  transition:all .2s;
}
.opt .circle::after{
  content:"";width:10px;height:10px;border-radius:50%;background:#fff;transform:scale(0);transition:transform .2s;
}
.opt input:checked + .circle{background:var(--primary);border-color:var(--primary);}
.opt input:checked + .circle::after{transform:scale(1);}
.opt.selected{border-color:var(--primary);background:rgba(14,76,146,0.06);font-weight:500;color:#2c3e50;}

/* Progress */
.progress-wrap{
  position:sticky;top:10px;background:#fff;border-radius:16px;padding:16px 20px;
  margin-bottom:20px;box-shadow:0 6px 18px rgba(0,0,0,0.06);z-index:50;
}
.progress-info{display:flex;justify-content:space-between;font-size:13px;font-weight:600;color:var(--gray);margin-bottom:8px;}
.progress-bar{height:8px;background:#eef2f6;border-radius:4px;overflow:hidden;}
.progress-fill{
  height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-light));
  width:0%;transition:width .3s;border-radius:4px;
}

/* Error */
.error-wrap{
  max-width:560px;margin:80px auto;background:#fff;border-radius:20px;
  padding:50px 35px;text-align:center;box-shadow:0 20px 40px rgba(0,0,0,0.08);
}
.error-wrap .icon{
  width:80px;height:80px;border-radius:50%;background:#f8d7da;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 20px;font-size:34px;color:var(--danger);
}
.error-wrap h2{color:#721c24;margin-bottom:10px;font-size:20px;}
.error-wrap p{color:#64748b;font-size:14px;line-height:1.6;}

/* Result modal */
.result-modal{
  position:fixed;inset:0;background:rgba(0,0,0,0.6);
  display:none;align-items:center;justify-content:center;z-index:1000;padding:20px;
}
.result-modal.active{display:flex;}
.result-box{
  background:#fff;border-radius:22px;padding:40px;max-width:500px;text-align:center;
  box-shadow:0 25px 60px rgba(0,0,0,0.3);
}
.result-box .icon{
  width:100px;height:100px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  margin:0 auto 20px;font-size:48px;
}
.result-box.pass .icon{background:#d4edda;color:var(--success);}
.result-box.fail .icon{background:#f8d7da;color:var(--danger);}
.result-box h2{font-size:24px;margin-bottom:10px;}
.result-box p{color:#64748b;margin-bottom:20px;font-size:14px;line-height:1.6;}
.result-box .score{
  display:inline-block;background:var(--light);padding:10px 24px;border-radius:30px;
  font-weight:700;color:var(--primary);margin-bottom:20px;font-size:16px;
}
@media(max-width:600px){
  .card{padding:22px;}
  .q-card{padding:20px;}
  .assess-header{padding:26px 18px;}
  .btn{width:100%;}
}
</style>
</head>
<body>
<div class="wrap">

<?php if ($error): ?>
    <div class="error-wrap">
        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h2>Assessment Not Available</h2>
        <p><?php echo htmlspecialchars($error); ?></p>
    </div>
</div>
</body>
</html>
<?php exit; endif; ?>

<!-- ========== INSTRUCTIONS SCREEN ========== -->
<div id="instructionsScreen">
    <div class="assess-header">
        <div class="icon"><i class="fas fa-file-signature"></i></div>
        <h1>Online Assessment</h1>
        <p><?php echo htmlspecialchars($assessmentType); ?></p>
    </div>

    <div class="card">
        <h2><i class="fas fa-user-check"></i> Applicant Details</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Name</div>
                <div class="info-value"><?php echo htmlspecialchars(trim($applicant['first_name'].' '.$applicant['last_name'])); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Application #</div>
                <div class="info-value"><?php echo htmlspecialchars($applicant['application_number']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Position</div>
                <div class="info-value"><?php echo htmlspecialchars($applicant['job_title'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Questions</div>
                <div class="info-value"><?php echo count($questions); ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2><i class="fas fa-clipboard-list"></i> Instructions — Please Read Carefully</h2>
        <ul class="instruction-list">
            <li><span class="num">1</span><div>This assessment contains <strong><?php echo count($questions); ?> multiple-choice questions</strong> about the role you applied for.</div></li>
            <li><span class="num">2</span><div>You must answer <strong>ALL questions</strong>. Unanswered items are marked wrong.</div></li>
            <li><span class="num">3</span><div>You have <strong>30 minutes</strong> to complete this assessment. The timer starts once you click <em>Start</em>.</div></li>
            <li><span class="num">4</span><div>You can change your answer anytime before submitting.</div></li>
            <li><span class="num">5</span><div>You can only take this assessment <strong>ONCE</strong>. Answers cannot be changed after submission.</div></li>
            <li><span class="num">6</span><div>Passing score is <strong>70%</strong>. Results are sent to HR automatically.</div></li>
            <li><span class="num">7</span><div>Do <strong>NOT</strong> refresh or close your browser during the test.</div></li>
        </ul>

        <div class="tip">
            <strong><i class="fas fa-lightbulb"></i> Tip</strong>
            Find a quiet place with a stable internet connection. Read each question carefully before answering.
        </div>

        <div style="text-align:center;margin-top:28px;">
            <button class="btn btn-primary" onclick="startAssessment()">
                <i class="fas fa-play"></i> Start Assessment
            </button>
        </div>
    </div>
</div>

<!-- ========== ASSESSMENT SCREEN ========== -->
<div id="assessmentScreen" style="display:none;">
    <div class="progress-wrap">
        <div class="progress-info">
            <span><i class="fas fa-list-check"></i> Answered: <span id="answeredCount">0</span> / <?php echo count($questions); ?></span>
            <span><i class="fas fa-clock"></i> <span id="timer">30:00</span></span>
        </div>
        <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
    </div>

    <form id="assessForm">
        <?php foreach ($questions as $i => $q): ?>
        <div class="q-card" data-q-index="<?php echo $i; ?>">
            <div class="q-number">Question <?php echo $i + 1; ?> of <?php echo count($questions); ?></div>
            <div class="q-text"><?php echo htmlspecialchars($q['q']); ?></div>
            <?php foreach ($q['options'] as $j => $opt): ?>
                <label class="opt" onclick="selectOpt(this)">
                    <input type="radio" name="q<?php echo $i; ?>" value="<?php echo $j; ?>" onchange="updateProgress()">
                    <span class="circle"></span>
                    <span><?php echo htmlspecialchars($opt); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <div style="text-align:center;margin-top:30px;">
            <button type="button" class="btn btn-primary" id="submitBtn" onclick="submitAssessment()">
                <i class="fas fa-paper-plane"></i> Submit Assessment
            </button>
        </div>
    </form>
</div>

<!-- ========== RESULT MODAL ========== -->
<div class="result-modal" id="resultModal">
    <div class="result-box" id="resultBox">
        <div class="icon"><i class="fas fa-check" id="resultIcon"></i></div>
        <h2 id="resultTitle">Assessment Complete</h2>
        <div class="score" id="resultScore">Score: 0%</div>
        <p id="resultMsg"></p>
        <a href="#" class="btn btn-primary" id="resultClose" style="margin-top:10px;">
            <i class="fas fa-home"></i> Close
        </a>
    </div>
</div>

</div>

<script>
const TOTAL_Q    = <?php echo count($questions); ?>;
const APPLICANT  = <?php echo json_encode($applicant['id']); ?>;
const APP_NUMBER = <?php echo json_encode($applicant['application_number']); ?>;
const TOKEN      = <?php echo json_encode($token); ?>;
let timeLeft     = 30 * 60; // 30 minutes in seconds
let timerInterval= null;

function startAssessment() {
    document.getElementById('instructionsScreen').style.display = 'none';
    document.getElementById('assessmentScreen').style.display = 'block';
    window.scrollTo({top:0,behavior:'smooth'});
    startTimer();
}

function startTimer() {
    timerInterval = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            alert('Time is up! Your assessment will be submitted automatically.');
            submitAssessment();
            return;
        }
        timeLeft--;
        const m = Math.floor(timeLeft / 60).toString().padStart(2,'0');
        const s = (timeLeft % 60).toString().padStart(2,'0');
        document.getElementById('timer').textContent = m + ':' + s;
    }, 1000);
}

function selectOpt(label) {
    // remove 'selected' from siblings in same q-card
    const card = label.closest('.q-card');
    card.querySelectorAll('.opt').forEach(o => o.classList.remove('selected'));
    label.classList.add('selected');
}

function updateProgress() {
    let answered = 0;
    for (let i = 0; i < TOTAL_Q; i++) {
        if (document.querySelector(`input[name="q${i}"]:checked`)) answered++;
    }
    document.getElementById('answeredCount').textContent = answered;
    const pct = TOTAL_Q ? (answered / TOTAL_Q) * 100 : 0;
    document.getElementById('progressFill').style.width = pct + '%';
    document.querySelectorAll('.q-card').forEach(card => {
        const idx = card.dataset.qIndex;
        const checked = card.querySelector(`input[name="q${idx}"]:checked`);
        card.classList.toggle('answered', !!checked);
    });
}

function submitAssessment() {
    // gather answers
    const answers = {};
    let unanswered = 0;
    for (let i = 0; i < TOTAL_Q; i++) {
        const sel = document.querySelector(`input[name="q${i}"]:checked`);
        if (sel) answers['q' + i] = parseInt(sel.value);
        else unanswered++;
    }
    if (unanswered > 0) {
        if (!confirm(`You have ${unanswered} unanswered question(s). Submit anyway?`)) return;
    } else {
        if (!confirm('Submit your assessment now? You cannot change your answers after this.')) return;
    }

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    fetch('assessment_submit.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            applicant_id: APPLICANT,
            application_number: APP_NUMBER,
            token: TOKEN,
            answers: answers
        })
    })
    .then(r => r.json())
    .then(res => {
        clearInterval(timerInterval);
        showResult(res);
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Assessment';
        alert('Submission failed. Please check your connection and try again.');
    });
}

function showResult(res) {
    const modal = document.getElementById('resultModal');
    const box   = document.getElementById('resultBox');
    const icon  = document.getElementById('resultIcon');
    const title = document.getElementById('resultTitle');
    const score = document.getElementById('resultScore');
    const msg   = document.getElementById('resultMsg');

    box.classList.remove('pass','fail');
    if (res.success && res.result === 'passed') {
        box.classList.add('pass');
        icon.className = 'fas fa-check';
        title.textContent = '🎉 Congratulations! You Passed!';
        score.textContent = 'Score: ' + res.score + '%';
        msg.textContent = 'Your assessment has been submitted. Our HR team will contact you shortly regarding your initial interview.';
    } else if (res.success && res.result === 'failed') {
        box.classList.add('fail');
        icon.className = 'fas fa-times';
        title.textContent = 'Assessment Completed';
        score.textContent = 'Score: ' + res.score + '%';
        msg.textContent = 'Thank you for taking the assessment. Unfortunately, your score did not meet the passing requirement. HR will be in touch.';
    } else {
        box.classList.add('fail');
        icon.className = 'fas fa-exclamation-triangle';
        title.textContent = 'Submission Error';
        score.style.display = 'none';
        msg.textContent = res.message || 'Something went wrong. Please contact HR.';
    }
    modal.classList.add('active');
}

document.getElementById('resultClose').addEventListener('click', e => {
    e.preventDefault();
    document.getElementById('resultModal').classList.remove('active');
    // Optionally redirect
    window.location.href = 'assessment.php?app=' + encodeURIComponent(APP_NUMBER) + '&token=' + encodeURIComponent(TOKEN);
});
</script>
</body>
</html>