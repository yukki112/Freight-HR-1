<?php
// /modules/ess/benefits.php
$emp = essGetEmployee($pdo);
?>

<style>
.ess-page-header { background:linear-gradient(135deg,#0e4c92,#4086e4); border-radius:24px; padding:32px 36px; margin-bottom:25px; color:white; position:relative; overflow:hidden; box-shadow:0 20px 40px rgba(14,76,146,0.2); }
.ess-page-header::before { content:''; position:absolute; width:240px; height:240px; background:rgba(255,255,255,0.08); border-radius:50%; top:-100px; right:-60px; }
.ess-page-header .hdr-left { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
.ess-page-header .hdr-icon { width:60px; height:60px; background:rgba(255,255,255,0.18); border:1.5px solid rgba(255,255,255,0.3); border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:26px; }
.ess-page-header h1 { font-size:24px; font-weight:700; margin:0 0 4px; }
.ess-page-header p { font-size:13px; margin:0; opacity:0.85; }

.benefits-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:18px; }
.benefit-card { background:white; border-radius:20px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.05); border:1px solid #eef2f6; transition:all 0.3s; }
.benefit-card:hover { transform:translateY(-4px); box-shadow:0 16px 40px rgba(14,76,146,0.1); }
.benefit-head { padding:20px; background:linear-gradient(135deg,#0e4c92,#4086e4); color:white; display:flex; align-items:center; gap:14px; }
.benefit-head .ico { width:44px; height:44px; background:rgba(255,255,255,0.2); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; }
.benefit-head h3 { margin:0; font-size:15px; font-weight:700; }
.benefit-body { padding:20px; }
.benefit-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px dashed #e2e8f0; font-size:13px; }
.benefit-row:last-child { border-bottom:none; }
.benefit-row .lbl { color:#64748b; }
.benefit-row .val { color:#1e293b; font-weight:600; text-align:right; }
</style>

<div class="ess-page-header">
    <div class="hdr-left">
        <div class="hdr-icon"><i class="fas fa-heart"></i></div>
        <div>
            <h1>My Benefits</h1>
            <p>View your government-mandated and company benefits</p>
        </div>
    </div>
</div>

<div class="benefits-grid">
    <div class="benefit-card">
        <div class="benefit-head">
            <div class="ico"><i class="fas fa-shield-alt"></i></div>
            <h3>SSS Contribution</h3>
        </div>
        <div class="benefit-body">
            <div class="benefit-row"><span class="lbl">Member Status</span><span class="val">Active</span></div>
            <div class="benefit-row"><span class="lbl">Monthly Contribution</span><span class="val">₱1,125.00</span></div>
            <div class="benefit-row"><span class="lbl">Employer Share</span><span class="val">₱2,375.00</span></div>
        </div>
    </div>

    <div class="benefit-card">
        <div class="benefit-head">
            <div class="ico"><i class="fas fa-hospital"></i></div>
            <h3>PhilHealth</h3>
        </div>
        <div class="benefit-body">
            <div class="benefit-row"><span class="lbl">Member Status</span><span class="val">Active</span></div>
            <div class="benefit-row"><span class="lbl">Monthly Contribution</span><span class="val">₱450.00</span></div>
            <div class="benefit-row"><span class="lbl">Coverage</span><span class="val">₱100,000</span></div>
        </div>
    </div>

    <div class="benefit-card">
        <div class="benefit-head">
            <div class="ico"><i class="fas fa-home"></i></div>
            <h3>Pag-IBIG</h3>
        </div>
        <div class="benefit-body">
            <div class="benefit-row"><span class="lbl">Member Status</span><span class="val">Active</span></div>
            <div class="benefit-row"><span class="lbl">Monthly Contribution</span><span class="val">₱100.00</span></div>
            <div class="benefit-row"><span class="lbl">Loan Eligibility</span><span class="val">Available</span></div>
        </div>
    </div>

    <div class="benefit-card">
        <div class="benefit-head">
            <div class="ico"><i class="fas fa-medkit"></i></div>
            <h3>HMO Coverage</h3>
        </div>
        <div class="benefit-body">
            <div class="benefit-row"><span class="lbl">Provider</span><span class="val">Maxicare</span></div>
            <div class="benefit-row"><span class="lbl">Annual Limit</span><span class="val">₱200,000</span></div>
            <div class="benefit-row"><span class="lbl">Dependents</span><span class="val">1 covered</span></div>
        </div>
    </div>
</div>