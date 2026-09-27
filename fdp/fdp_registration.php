<?php
/* ── Dynamic QR generator — same pattern as internship_payment.php ── */
$fdp_upi = 'yourupi1@ybl'; // Terminal 1 — change to any of your UPI IDs
$fdp_fee = 1000.00;

function fdpQrUrl(string $upi, float $amount, int $size = 200): string {
    $data = 'upi://pay?pa=' . rawurlencode($upi)
          . '&pn=' . rawurlencode('CourseFlow')
          . '&am=' . number_format($amount, 2, '.', '')
          . '&cu=INR&tn=' . rawurlencode('FDP Registration Fee');
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
         . '&data=' . urlencode($data) . '&format=png&ecc=M&margin=4';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Faculty Development Program — June 15–22, 2026 | CourseFlow</title>
<link rel="icon" type="image/png" href="/assets/images/favicon.png">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --blue-dark:#0f1b3d;
  --blue-mid:#1a2e6b;
  --blue:#2448c8;
  --blue-light:#3b82f6;
  --blue-pale:#eff6ff;
  --blue-border:#bfdbfe;
  --green:#16a34a;
  --green-bg:#f0fdf4;
  --orange:#ea580c;
  --teal:#0d9488;
  --white:#ffffff;
  --grey-bg:#f3f6fb;
  --grey-text:#64748b;
  --ink:#0f172a;
  --border:#e2e8f0;
}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--grey-bg);color:var(--ink);min-height:100vh;}

/* ── TOPBAR ── */
.topbar{position:fixed;top:0;left:0;right:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:10px 40px;background:rgba(15,27,61,.92);backdrop-filter:blur(10px);}
.logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.logo img{height:38px;width:auto;object-fit:contain;border-radius:6px;}
.logo-text{font-weight:800;font-size:1rem;color:white;letter-spacing:-.01em;}
.logo-sub{font-size:.6rem;color:rgba(255,255,255,.5);font-weight:400;letter-spacing:.08em;text-transform:uppercase;display:block;margin-top:-2px;}
.back-btn{display:none;}
.breadcrumb-bar{position:fixed;top:58px;left:0;right:0;z-index:99;background:#fff;border-bottom:1px solid #e2e8f0;padding:0 40px;}
.breadcrumb-inner{max-width:1100px;margin:0 auto;display:flex;align-items:center;gap:6px;height:36px;font-size:.78rem;font-weight:600;color:#64748b;}
.breadcrumb-inner a{color:#64748b;text-decoration:none;display:flex;align-items:center;gap:5px;transition:color .2s;}
.breadcrumb-inner a:hover{color:#0d9488;}
.breadcrumb-inner a i{font-size:.7rem;}
.breadcrumb-sep{color:#cbd5e1;font-size:.7rem;}
.breadcrumb-current{color:#0d9488;font-weight:700;}
@media(max-width:600px){.breadcrumb-bar{padding:0 16px;}}

/* ── HERO ── */
.hero{padding:130px 40px 80px;background:linear-gradient(160deg,#0d3b30 0%,#065f46 42%,#0d9488 72%,#0891b2 100%);position:relative;overflow:hidden;text-align:center;}
.hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 80%,rgba(13,148,136,.22),transparent),radial-gradient(ellipse 60% 40% at 80% 20%,rgba(8,145,178,.18),transparent);}
.hero::after{content:'';position:absolute;bottom:0;left:0;right:0;height:60px;background:var(--grey-bg);clip-path:ellipse(55% 100% at 50% 100%);}
.hero-dots{position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.1) 1px,transparent 1px);background-size:28px 28px;pointer-events:none;}
.hero-inner{position:relative;z-index:2;}
.reg-pill{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.22);border-radius:40px;padding:5px 16px;margin-bottom:14px;}
.reg-pill .dot{width:7px;height:7px;background:#4ade80;border-radius:50%;box-shadow:0 0 6px #4ade80;animation:pulse 1.5s infinite;}
.reg-pill span{font-size:.72rem;font-weight:600;color:white;letter-spacing:.08em;text-transform:uppercase;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(74,222,128,.5);}50%{box-shadow:0 0 0 6px rgba(74,222,128,0);}}
.hero h1{font-size:clamp(1.8rem,4.5vw,2.9rem);font-weight:800;color:white;line-height:1.1;letter-spacing:-.03em;margin-bottom:8px;}
.hero h1 .gtxt{background:linear-gradient(90deg,#6ee7b7,#a5f3fc);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero p{color:rgba(255,255,255,.72);font-size:.95rem;line-height:1.7;max-width:540px;margin:0 auto 28px;}
.hero-badges{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:10px;margin-top:20px;}
.h-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:6px 14px;color:white;font-size:.75rem;font-weight:600;}
.h-badge svg{width:13px;height:13px;stroke:white;fill:none;stroke-width:2;}

/* ── MAIN LAYOUT (full-width like internship) ── */
.main{max-width:1100px;margin:0 auto;padding:40px 24px 80px;display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start;}

/* ── FORM CARD ── */
.form-card{background:white;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(15,27,61,.08);border:1px solid var(--border);}
.form-card-header{background:linear-gradient(135deg,#0d9488,#0891b2);padding:24px 28px;display:flex;align-items:flex-start;gap:14px;}
.fch-icon{width:38px;height:38px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;}
.fch-icon svg{width:20px;height:20px;stroke:white;fill:none;stroke-width:2;}
.fch-title{font-size:1.05rem;font-weight:800;color:white;margin-bottom:4px;}
.fch-sub{font-size:.78rem;color:rgba(255,255,255,.72);line-height:1.5;}
.form-body{padding:28px 28px 32px;}

/* ── SECTION HEADERS ── */
.sec-head{display:flex;align-items:center;gap:10px;margin-bottom:18px;margin-top:26px;}
.sec-head:first-child{margin-top:0;}
.sec-icon{width:26px;height:26px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sec-icon.teal{background:rgba(13,148,136,.12);}
.sec-icon.teal svg{stroke:#0d9488;}
.sec-icon svg{width:14px;height:14px;stroke-width:2;fill:none;}
.sec-title{font-size:.82rem;font-weight:800;color:var(--ink);letter-spacing:.04em;text-transform:uppercase;}
.sec-sub{font-size:.73rem;color:var(--grey-text);margin-left:auto;}

/* ── FIELD GROUPS ── */
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
.field-row.single{grid-template-columns:1fr;}
.field{display:flex;flex-direction:column;gap:5px;}
.field label{font-size:.75rem;font-weight:700;color:#374151;letter-spacing:.02em;display:flex;align-items:center;gap:5px;}
.field label .req{color:#ef4444;margin-left:1px;}
.field input,.field select{width:100%;padding:11px 14px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;color:var(--ink);background:#f8faff;border:1.5px solid #dbeafe;border-radius:10px;outline:none;transition:border-color .2s,box-shadow .2s,background .2s;-webkit-appearance:none;appearance:none;}
.field input:focus,.field select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(13,148,136,.12);background:white;}
.field input::placeholder{color:#94a3b8;}

/* ── DIVIDER ── */
.section-divider{margin:28px 0 22px;display:flex;align-items:center;gap:12px;}
.section-divider span{font-size:.76rem;font-weight:700;color:var(--grey-text);white-space:nowrap;text-transform:uppercase;letter-spacing:.05em;}
.section-divider::before,.section-divider::after{content:'';flex:1;height:1px;background:var(--border);}

/* ── QR PAYMENT SECTION ── */
.pay-box{background:linear-gradient(135deg,#f0fdf9,#e0f2fe);border:1.5px solid #a7f3d0;border-radius:16px;padding:24px;margin-bottom:20px;}
.pay-box-top{display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.pay-badge{background:linear-gradient(135deg,#0d9488,#0891b2);color:white;border-radius:8px;padding:6px 14px;font-size:.78rem;font-weight:800;letter-spacing:.04em;}
.pay-amount{font-size:1.5rem;font-weight:800;color:#065f46;}
.pay-amount small{font-size:.8rem;font-weight:500;color:var(--grey-text);display:block;margin-top:1px;}
.pay-layout{display:flex;align-items:flex-start;gap:24px;}
.qr-wrap{flex-shrink:0;text-align:center;}
.qr-wrap img{width:150px;height:150px;border-radius:12px;border:2px solid #a7f3d0;padding:6px;background:white;display:block;}
.qr-label{font-size:.68rem;font-weight:600;color:#0d9488;margin-top:6px;text-align:center;}
.pay-steps{flex:1;}
.pay-steps ol{padding-left:18px;}
.pay-steps li{font-size:.82rem;color:#374151;line-height:1.7;margin-bottom:4px;}
.pay-steps li strong{color:#065f46;}
.pay-note{margin-top:10px;display:flex;align-items:flex-start;gap:7px;background:rgba(234,179,8,.1);border:1px solid rgba(234,179,8,.3);border-radius:8px;padding:8px 12px;}
.pay-note svg{width:14px;height:14px;stroke:#b45309;fill:none;stroke-width:2;flex-shrink:0;margin-top:1px;}
.pay-note span{font-size:.75rem;color:#92400e;line-height:1.5;}

/* ── ALERT ── */
.alert-err{display:none;align-items:center;gap:10px;background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:16px;}
.alert-err svg{width:16px;height:16px;stroke:#dc2626;fill:none;stroke-width:2;flex-shrink:0;}
.alert-err span{font-size:.82rem;color:#991b1b;font-weight:500;}

/* ── SUBMIT BUTTON ── */
.btn-submit{width:100%;padding:15px;background:linear-gradient(135deg,#0d9488,#0891b2);color:white;border:none;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.97rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:opacity .2s,transform .15s,box-shadow .2s;box-shadow:0 4px 20px rgba(13,148,136,.35);}
.btn-submit:hover:not(:disabled){opacity:.92;transform:translateY(-1px);box-shadow:0 6px 20px rgba(13,148,136,.4);}
.btn-submit:disabled{opacity:.65;cursor:not-allowed;transform:none;}
.btn-submit svg{width:18px;height:18px;stroke:white;fill:none;stroke-width:2;}
.btn-spin{width:18px;height:18px;border:2.5px solid rgba(255,255,255,.35);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;display:none;}
@keyframes spin{to{transform:rotate(360deg);}}

/* ── SIDEBAR ── */
.sidebar{display:flex;flex-direction:column;gap:18px;}

.urgency-card{background:#fffbeb;border:1.5px solid #fde68a;border-radius:14px;padding:18px;}
.urgency-head{display:flex;align-items:center;gap:8px;margin-bottom:4px;}
.urgency-head span{font-weight:700;font-size:.92rem;color:#92400e;}
.urgency-sub{font-size:.78rem;color:#a16207;margin-bottom:12px;}
.progress-bar-wrap{background:#fde68a;border-radius:40px;height:8px;overflow:hidden;margin-bottom:6px;}
.progress-bar{height:100%;border-radius:40px;background:linear-gradient(90deg,#f59e0b,#d97706);width:68%;}
.progress-label{display:flex;justify-content:space-between;font-size:.71rem;font-weight:600;color:#92400e;}
.urgency-tip{display:flex;align-items:flex-start;gap:7px;background:#fef3c7;border-radius:8px;padding:10px 12px;margin-top:12px;}
.urgency-tip svg{width:14px;height:14px;stroke:#d97706;fill:none;stroke-width:2;flex-shrink:0;margin-top:1px;}
.urgency-tip span{font-size:.74rem;color:#92400e;line-height:1.5;}

.details-card{background:var(--blue-dark);border-radius:14px;padding:20px;}
.details-title{font-weight:700;font-size:.95rem;color:white;margin-bottom:3px;}
.details-sub{font-size:.73rem;color:rgba(255,255,255,.45);margin-bottom:18px;}
.detail-item{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;}
.detail-item:last-child{margin-bottom:0;}
.di-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.di-icon.g{background:rgba(74,222,128,.15);}
.di-icon.b{background:rgba(96,165,250,.15);}
.di-icon.o{background:rgba(251,146,60,.15);}
.di-icon.p{background:rgba(167,139,250,.15);}
.di-icon.t{background:rgba(13,148,136,.2);}
.di-icon.g svg{stroke:#4ade80;}
.di-icon.b svg{stroke:#60a5fa;}
.di-icon.o svg{stroke:#fb923c;}
.di-icon.p svg{stroke:#a78bfa;}
.di-icon.t svg{stroke:#2dd4bf;}
.di-icon svg{width:15px;height:15px;fill:none;stroke-width:2;}
.di-label{font-size:.65rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.38);margin-bottom:2px;}
.di-val{font-size:.87rem;font-weight:600;color:white;}
.di-hint{font-size:.72rem;color:rgba(255,255,255,.4);margin-top:1px;}

.who-card{background:linear-gradient(135deg,#0d3b30,#065f46);border-radius:14px;padding:20px;}
.who-title{font-weight:700;font-size:.95rem;color:white;margin-bottom:3px;}
.who-sub{font-size:.73rem;color:rgba(255,255,255,.5);margin-bottom:14px;}
.who-item{display:flex;align-items:flex-start;gap:9px;margin-bottom:10px;}
.who-item:last-child{margin-bottom:0;}
.who-dot{width:18px;height:18px;background:rgba(74,222,128,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;}
.who-dot svg{width:10px;height:10px;stroke:#4ade80;fill:none;stroke-width:2.5;}
.who-item span{font-size:.82rem;color:rgba(255,255,255,.88);line-height:1.4;}

.acc-card{background:white;border:1px solid var(--border);border-radius:14px;padding:18px;text-align:center;}
.acc-label{font-size:.65rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--grey-text);margin-bottom:14px;}
.acc-logos{display:flex;justify-content:center;align-items:center;gap:16px;flex-wrap:wrap;}
.acc-logo-wrap{display:flex;flex-direction:column;align-items:center;gap:6px;}
.acc-logo-wrap img{height:48px;width:auto;max-width:90px;object-fit:contain;}
.acc-badge{display:none;padding:5px 12px;border-radius:7px;font-size:.72rem;font-weight:800;letter-spacing:.04em;border:1.5px solid currentColor;}
.acc-name{font-size:.6rem;color:var(--grey-text);font-weight:600;text-align:center;}
.acc-note{font-size:.71rem;color:var(--grey-text);margin-top:12px;}

/* ── SUCCESS OVERLAY ── */
.success-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;padding:20px;}
.success-overlay.show{display:flex;}
.success-modal{background:white;border-radius:20px;max-width:480px;width:100%;padding:36px 32px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:popIn .3s ease;}
@keyframes popIn{from{transform:scale(.92);opacity:0;}to{transform:scale(1);opacity:1;}}
.s-check{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#0d9488,#0891b2);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;}
.s-check svg{width:30px;height:30px;stroke:white;fill:none;stroke-width:2.5;}
.success-modal h2{font-size:1.35rem;font-weight:800;color:var(--ink);margin-bottom:8px;}
.success-modal p{font-size:.88rem;color:var(--grey-text);margin-bottom:6px;line-height:1.6;}
.s-id{display:inline-block;background:var(--green-bg);color:var(--green);border:1px solid #bbf7d0;border-radius:8px;padding:6px 16px;font-size:.82rem;font-weight:700;margin:12px 0 20px;}
.s-close{width:100%;padding:12px;background:linear-gradient(135deg,#0d9488,#0891b2);color:white;border:none;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:700;cursor:pointer;}

/* ── RESPONSIVE ── */
@media(max-width:820px){
  .topbar{padding:10px 16px;}
  .hero{padding:115px 20px 70px;}
  .main{grid-template-columns:1fr;padding:24px 16px 60px;}
  .sidebar{order:-1;}
}
@media(max-width:600px){
  .field-row{grid-template-columns:1fr;}
  .pay-layout{flex-direction:column;align-items:center;}
  .qr-wrap img{width:130px;height:130px;}
  .form-body{padding:20px 18px;}
  .hero h1{font-size:1.8rem;}
}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
  <a href="/" class="logo">
    <img src="/assets/images/logo.jpeg" alt="CourseFlow" onerror="this.style.display='none'">
  </a>
</header>

<!-- BREADCRUMB -->
<div class="breadcrumb-bar">
  <div class="breadcrumb-inner">
    <a href="/"><i class="fas fa-home"></i> Home</a>
    <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
    <span class="breadcrumb-current">Faculty Development Program</span>
  </div>
</div>

<!-- HERO -->
<div class="hero">
  <div class="hero-dots"></div>
  <div class="hero-inner">
    <div class="reg-pill">
      <div class="dot"></div>
      <span>Registrations Open</span>
    </div>
    <h1>Faculty Development<br><span class="gtxt">Program — June 15–22, 2026</span></h1>
    <p>An intensive professional development program designed for faculty members to enhance teaching skills, research capabilities, and industry awareness.</p>
    <div class="hero-badges">
      <div class="h-badge">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Certified Program
      </div>
      <div class="h-badge">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        June 15 – June 22, 2026
      </div>
      <div class="h-badge">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Limited Seats
      </div>
      <div class="h-badge">
        <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          ₹1,000 Registration Fee
      </div>
    </div>
  </div>
</div>

<!-- MAIN (full-width two-column layout) -->
<div class="main">

  <!-- FORM CARD -->
  <div class="form-card">
    <div class="form-card-header">
      <div class="fch-icon">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </div>
      <div>
        <div class="fch-title">Participant Registration Form</div>
        <div class="fch-sub">Fill in your details below. All fields are mandatory.</div>
      </div>
    </div>

    <div class="form-body">

      <!-- Program Date Banner -->
      <div style="display:flex;align-items:center;gap:10px;background:#f0fdf9;border:1.5px solid #a7f3d0;border-radius:12px;padding:14px 18px;margin-bottom:20px;">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,#0d9488,#0891b2);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="white" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div>
          <div style="font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#0d9488;margin-bottom:2px;">Program Dates</div>
          <div style="font-size:.95rem;font-weight:800;color:#065f46;">June 15 – June 22, 2026</div>
          <div style="font-size:.72rem;color:#64748b;margin-top:1px;">8-day intensive program · Online / Hybrid</div>
        </div>
      </div>

      <!-- Error Alert -->
      <div class="alert-err" id="alertErr">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="alertErrTxt"></span>
      </div>

      <form id="fdpForm" novalidate>

        <!-- Personal Details Section -->
        <div class="sec-head">
          <div class="sec-icon teal"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
          <div class="sec-title">Personal Details</div>
        </div>

        <!-- ROW 1: Name -->
        <div class="field-row single">
          <div class="field">
            <label>Name of the Participant <span class="req">*</span></label>
            <input type="text" name="name" placeholder="Enter your full name" autocomplete="name" maxlength="200">
          </div>
        </div>

        <!-- ROW 2: Email + WhatsApp -->
        <div class="field-row">
          <div class="field">
            <label>Email Address <span class="req">*</span></label>
            <input type="email" name="email" placeholder="example@college.edu" autocomplete="email" maxlength="200">
          </div>
          <div class="field">
            <label>WhatsApp Number <span class="req">*</span></label>
            <input type="tel" name="whatsapp" placeholder="10-digit mobile number" maxlength="15">
          </div>
        </div>

        <!-- Professional Details Section -->
        <div class="sec-head">
          <div class="sec-icon teal"><svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></div>
          <div class="sec-title">Professional Details</div>
        </div>

        <!-- ROW 3: Designation + Organisation -->
        <div class="field-row">
          <div class="field">
            <label>Designation <span class="req">*</span></label>
            <input type="text" name="designation" placeholder="e.g. Assistant Professor" maxlength="200">
          </div>
          <div class="field">
            <label>Name of Organisation <span class="req">*</span></label>
            <input type="text" name="organisation" placeholder="Your company / institution" maxlength="200">
          </div>
        </div>

        <!-- ROW 4: College -->
        <div class="field-row single">
          <div class="field">
            <label>College Name <span class="req">*</span></label>
            <input type="text" name="college" placeholder="Full name of your college / university" maxlength="200">
          </div>
        </div>

        <!-- ROW 5: Department -->
        <div class="field-row single">
          <div class="field">
            <label>Department Name <span class="req">*</span></label>
            <input type="text" name="department" placeholder="e.g. Computer Science & Engineering" maxlength="200">
          </div>
        </div>

        <!-- PAYMENT SECTION -->
        <div class="section-divider">
          <span>Payment — ₹1,000 Registration Fee</span>
        </div>

        <div class="pay-box">
          <div class="pay-box-top">
            <div class="pay-badge">UPI / QR Payment</div>
            <div class="pay-amount">₹1,000 <small>One-time registration fee</small></div>
          </div>
          <div class="pay-layout">
            <div class="qr-wrap">
              <div class="qr-spinner-wrap" id="qrSpinner" style="width:150px;height:150px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#f8fafc;border-radius:12px;border:2px solid #a7f3d0;gap:6px;color:#94a3b8;font-size:.72rem;">
                <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:#0d9488;"></i>
                Loading QR...
              </div>
              <img src="<?php echo htmlspecialchars(fdpQrUrl($fdp_upi, $fdp_fee, 150)); ?>"
                   alt="UPI QR Code — Scan to Pay ₹1,000"
                   style="display:none;width:150px;height:150px;border-radius:12px;border:2px solid #a7f3d0;padding:6px;background:white;"
                   onload="this.style.display='block';document.getElementById('qrSpinner').style.display='none';"
                   onerror="document.getElementById('qrSpinner').innerHTML='<i class=\'fas fa-qrcode\' style=\'color:#0d9488;font-size:1.8rem\'></i><span>UPI: <?php echo htmlspecialchars($fdp_upi); ?></span>'">
              <div class="qr-label">Scan to Pay ₹1,000</div>
            </div>
            <div class="pay-steps">
              <ol>
                <li>Scan the QR code using any UPI app<br><small>(GPay, PhonePe, Paytm, BHIM, etc.)</small></li>
                <li>Pay exactly <strong>₹1,000</strong></li>
                <li>Note down the <strong>UTR / Transaction Reference Number</strong> from your payment app</li>
                <li>Enter the UTR number in the field below and submit the form</li>
              </ol>
              <div class="pay-note">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Your registration will be confirmed after our team verifies the payment (within 24 hours). A confirmation email will be sent to you.</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ROW 6: UTR -->
        <div class="field-row single">
          <div class="field">
            <label>UTR / Transaction Reference Number <span class="req">*</span></label>
            <input type="text" name="utr_number" placeholder="Enter UTR after completing payment" maxlength="100" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
          </div>
        </div>

        <!-- SUBMIT -->
        <button type="submit" class="btn-submit" id="submitBtn">
          <span id="btnTxt">
            <svg viewBox="0 0 24 24" width="17" height="17" stroke="currentColor" fill="none" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Submit Registration
          </span>
          <div class="btn-spin" id="btnSpin"></div>
        </button>

      </form>
    </div>
  </div>

  <!-- SIDEBAR -->
  <div class="sidebar">

    <div class="urgency-card">
      <div class="urgency-head"><span>🔥</span><span>Seats filling fast!</span></div>
      <div class="urgency-sub">June 15–22, 2026 batch is almost full</div>
      <div class="progress-bar-wrap"><div class="progress-bar"></div></div>
      <div class="progress-label"><span>Registrations</span><span>68% filled</span></div>
      <div class="urgency-tip">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>Only a few seats remaining — register now before it closes.</span>
      </div>
    </div>

    <div class="details-card">
      <div class="details-title">Program Details</div>
      <div class="details-sub">Everything you need to know</div>
      <div class="detail-item">
        <div class="di-icon g"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div><div class="di-label">Schedule</div><div class="di-val">June 15 – June 22, 2026</div><div class="di-hint">8 days intensive program</div></div>
      </div>
      <div class="detail-item">
        <div class="di-icon b"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div><div class="di-label">Duration</div><div class="di-val">Intensive Program</div><div class="di-hint">Structured daily sessions</div></div>
      </div>
      <div class="detail-item">
        <div class="di-icon o"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
        <div><div class="di-label">Mode</div><div class="di-val">Online / Hybrid</div><div class="di-hint">Join link sent on WhatsApp &amp; Email</div></div>
      </div>
      <div class="detail-item">
        <div class="di-icon t"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg></div>
        <div><div class="di-label">You Will Get</div><div class="di-val">Participation Certificate</div><div class="di-hint">+ Resource Kit + Mentorship</div></div>
      </div>
      <div class="detail-item">
        <div class="di-icon p"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div><div class="di-label">Registration Fee</div><div class="di-val">₹1,000 One-Time</div><div class="di-hint">Pay via UPI / QR code above</div></div>
      </div>
    </div>

    <div class="who-card">
      <div class="who-title">Who Should Apply?</div>
      <div class="who-sub">This program is ideal for</div>
      <div class="who-item"><div class="who-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div><span>Assistant &amp; Associate Professors</span></div>
      <div class="who-item"><div class="who-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div><span>Lecturers seeking professional growth</span></div>
      <div class="who-item"><div class="who-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div><span>Faculty wanting industry exposure</span></div>
      <div class="who-item"><div class="who-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div><span>Educators upskilling in AI &amp; Tech</span></div>
      <div class="who-item"><div class="who-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div><span>Department heads &amp; coordinators</span></div>
    </div>

    <div class="acc-card">
      <div class="acc-label">In Association With</div>
      <div class="acc-logos">
        <div class="acc-logo-wrap">
          <img src="https://www.uxdt.nic.in/wp-content/uploads/2024/05/all-india-council-of-technical-education-feature-all-india-council-of-technical-education-01.jpg"
               alt="AICTE"
               onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block'"/>
          <span class="acc-badge" style="color:#1e40af;background:#eff6ff;">AICTE</span>
          <span class="acc-name">AICTE</span>
        </div>
        <div class="acc-logo-wrap">
          <img src="https://cets.apsche.ap.gov.in/APSCHE/images/ApscheLogoNew.png"
               alt="APSCHE"
               onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block'"/>
          <span class="acc-badge" style="color:#92400e;background:#fef3c7;">APSCHE</span>
          <span class="acc-name">APSCHE</span>
        </div>
        <div class="acc-logo-wrap">
          <img src="https://cloud.google.com/_static/cloud/images/social-icon-google-cloud-1200-630.png"
               alt="Google Cloud"
               onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block'"/>
          <span class="acc-badge" style="color:#1a73e8;background:#e8f0fe;">Google Cloud</span>
          <span class="acc-name">Google Cloud</span>
        </div>
        <div class="acc-logo-wrap">
          <img src="/assets/images/accreditations/nanmodalvan.png"
               alt="Naan Mudhalvan"
               onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block'"/>
          <span class="acc-badge" style="color:#7c3aed;background:#f5f3ff;">Naan Mudhalvan</span>
          <span class="acc-name">Naan Mudhalvan</span>
        </div>
      </div>
      <div class="acc-note">In association with leading national &amp; state bodies</div>
    </div>

    <div class="acc-card">
      <div class="acc-label">Organised By</div>
      <div class="acc-logos">
        <div class="acc-logo-wrap">
          <img src="/assets/images/logo.jpeg" alt="CourseFlow" style="height:48px;width:auto;max-width:120px;object-fit:contain;border-radius:6px;"
               onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block'"/>
          <span class="acc-badge" style="color:#0d9488;background:#f0fdfa;">CourseFlow</span>
          <span class="acc-name">CourseFlow</span>
        </div>
      </div>
      <div class="acc-note">Bangalore-based AI &amp; Technology education company</div>
    </div>

  </div>
</div>

<!-- SUCCESS OVERLAY -->
<div class="success-overlay" id="successOverlay">
  <div class="success-modal">
    <div class="s-check">
      <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h2>Registration Successful! 🎉</h2>
    <p>Welcome, <strong id="sName"></strong>!</p>
    <p>Your registration for the <strong>Faculty Development Program (June 15–22, 2026)</strong> has been received.</p>
    <div class="s-id">Registration ID: <span id="sRegId"></span></div>
    <p style="font-size:.78rem;color:#94a3b8;margin-bottom:18px;">
      Our team will verify your payment and send confirmation to your email within 24 hours.
    </p>
    <button class="s-close" onclick="document.getElementById('successOverlay').classList.remove('show');window.location.href='/';">
      Go to Home
    </button>
  </div>
</div>

<script>
(function () {
  'use strict';

  document.getElementById('fdpForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    var alertEl  = document.getElementById('alertErr');
    var alertTxt = document.getElementById('alertErrTxt');
    var btn      = document.getElementById('submitBtn');
    var btnTxt   = document.getElementById('btnTxt');
    var spin     = document.getElementById('btnSpin');

    function showErr(msg) {
      alertEl.style.display = 'flex';
      alertTxt.textContent  = msg;
      alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    alertEl.style.display = 'none';

    var f   = this;
    var get = function (n) { return f.elements[n] ? f.elements[n].value.trim() : ''; };

    var name         = get('name');
    var email        = get('email');
    var whatsapp     = get('whatsapp').replace(/[\s\-]/g, '');
    var designation  = get('designation');
    var organisation = get('organisation');
    var college      = get('college');
    var department   = get('department');
    var utr          = get('utr_number');

    if (name.length < 3)                                     return showErr('Please enter your full name (min. 3 characters).');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))          return showErr('Please enter a valid email address.');
    if (!/^[6-9]\d{9}$/.test(whatsapp))                     return showErr('Enter a valid 10-digit Indian mobile number.');
    if (designation.length < 2)                              return showErr('Please enter your designation.');
    if (organisation.length < 2)                             return showErr('Please enter your organisation name.');
    if (college.length < 5)                                  return showErr('Please enter your full college name.');
    if (department.length < 2)                               return showErr('Please enter your department.');
    if (utr.length < 6)                                      return showErr('Please enter a valid UTR / Transaction Reference Number.');

    btn.disabled         = true;
    btnTxt.style.display = 'none';
    spin.style.display   = 'block';

    try {
      var fd = new FormData(f);
      fd.set('whatsapp', whatsapp);

      var res  = await fetch('/fdp/fdp_register.php', { method: 'POST', body: fd });
      var data = await res.json();

      if (data.success) {
        document.getElementById('sName').textContent  = data.name;
        document.getElementById('sRegId').textContent = 'FDP-' + String(data.reg_id).padStart(4, '0');
        document.getElementById('successOverlay').classList.add('show');
        f.reset();
      } else {
        showErr(data.message || 'Something went wrong. Please try again.');
      }
    } catch (err) {
      showErr('Network error. Please check your connection and try again.');
    } finally {
      btn.disabled         = false;
      btnTxt.style.display = '';
      spin.style.display   = 'none';
    }
  });
})();
</script>
</body>
</html>