<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Free Workshop Registration — CourseFlow</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" type="image/png" href="assets/images/favicon.png?v=2">
<style>
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --blue: #2563eb; --blue-dark: #1e40af; --blue-light: #eff6ff;
  --indigo: #4f46e5; --violet: #7c3aed;
  --green: #10b981; --yellow: #f59e0b; --red: #ef4444;
  --text: #0f172a; --text2: #334155; --text3: #64748b; --text4: #94a3b8;
  --border: #e2e8f0; --bg: #f8fafc;
  --radius: 12px; --radius-lg: 20px;
  --shadow: 0 4px 24px rgba(0,0,0,.07);
  --shadow-lg: 0 16px 48px rgba(0,0,0,.13);
}
html { scroll-behavior: smooth; }
body { font-family: 'DM Sans', sans-serif; color: var(--text); background: var(--bg); min-height: 100vh; }
a { text-decoration: none; color: inherit; }
img { max-width: 100%; display: block; }

/* ── NAVBAR ── */
.nav {
  background: rgba(255,255,255,.97); backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  padding: 0 5%; height: 64px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 200;
  box-shadow: 0 2px 16px rgba(0,0,0,.05);
}
.nav-logo { display: flex; align-items: center; gap: 9px; }
.nav-logo-icon {
  width: 34px; height: 34px;
  background: linear-gradient(135deg, var(--blue), var(--indigo));
  border-radius: 9px; display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: .82rem; font-weight: 900;
}
.nav-logo span { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1rem; }
.nav-logo span em { color: var(--blue); font-style: normal; }
.nav-back {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: .85rem; font-weight: 600; color: var(--text3);
  border: 1.5px solid var(--border); border-radius: 9px;
  padding: 8px 16px; transition: all .2s;
}
.nav-back:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-light); }

/* ── HERO BANNER ── */
.ws-hero {
  background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 40%, #312e81 70%, #1e1b4b 100%);
  padding: 56px 5% 52px; text-align: center;
  position: relative; overflow: hidden;
}
.ws-hero::before {
  content: ''; position: absolute; top: -180px; left: 50%; transform: translateX(-50%);
  width: 900px; height: 500px;
  background: radial-gradient(ellipse, rgba(99,102,241,.2), transparent 65%);
  pointer-events: none;
}
.ws-hero::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0;
  height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,.15), transparent);
}
.ws-hero-orb {
  position: absolute; border-radius: 50%; pointer-events: none;
  opacity: .12; filter: blur(40px);
}
.ws-hero-orb-1 { width: 300px; height: 300px; background: #3b82f6; top: -80px; left: -60px; }
.ws-hero-orb-2 { width: 250px; height: 250px; background: #8b5cf6; bottom: -80px; right: -40px; }

.ws-hero-inner { position: relative; z-index: 1; max-width: 700px; margin: 0 auto; }
.ws-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(74,222,128,.14); border: 1px solid rgba(74,222,128,.3);
  border-radius: 999px; padding: 5px 16px;
  font-size: .7rem; font-weight: 800; color: #4ade80;
  text-transform: uppercase; letter-spacing: .08em; margin-bottom: 22px;
}
.ws-badge-dot {
  width: 7px; height: 7px; border-radius: 50%; background: #4ade80;
  animation: blink 1.4s ease infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.2} }
.ws-hero h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 2.5rem; font-weight: 900; color: #fff; margin-bottom: 14px; line-height: 1.2;
}
.ws-hero h1 span {
  background: linear-gradient(135deg, #60a5fa, #a78bfa);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.ws-hero p { color: rgba(255,255,255,.7); font-size: .95rem; max-width: 560px; margin: 0 auto 28px; line-height: 1.7; }
.ws-hero-pills { display: flex; align-items: center; justify-content: center; gap: 9px; flex-wrap: wrap; }
.ws-pill {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.16);
  border-radius: 999px; padding: 6px 14px;
  font-size: .76rem; font-weight: 600; color: rgba(255,255,255,.85);
}
.ws-pill i { color: #60a5fa; }

/* ── PROGRESS SHIMMER ── */
.ws-progress {
  height: 4px;
  background: linear-gradient(90deg, var(--blue), var(--indigo), var(--violet));
  position: relative; overflow: hidden;
}
.ws-progress::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent);
  animation: shimmer 2s ease infinite;
}
@keyframes shimmer { from{transform:translateX(-100%)} to{transform:translateX(100%)} }

/* ── PAGE LAYOUT ── */
.ws-page { max-width: 1060px; margin: 0 auto; padding: 40px 5% 70px; display: grid; grid-template-columns: 1fr 330px; gap: 28px; align-items: start; }
@media(max-width:900px){ .ws-page { grid-template-columns: 1fr; } }

/* ── FORM CARD ── */
.ws-card {
  background: #fff; border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg); border: 1px solid var(--border); overflow: hidden;
}
.ws-card-header {
  background: linear-gradient(135deg, var(--blue), var(--indigo));
  padding: 22px 28px; display: flex; align-items: center; gap: 14px;
}
.ws-card-hico {
  width: 46px; height: 46px; background: rgba(255,255,255,.2);
  border-radius: 12px; display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; color: #fff; flex-shrink: 0;
}
.ws-card-header h2 {
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.1rem; font-weight: 800; color: #fff; margin-bottom: 2px;
}
.ws-card-header p { font-size: .78rem; color: rgba(255,255,255,.72); }
.ws-card-body { padding: 30px 28px; }
@media(max-width:600px){ .ws-card-body { padding: 22px 18px; } }

/* ── SECTION DIVIDER ── */
.form-section-div {
  display: flex; align-items: center; gap: 12px;
  margin: 26px 0 20px; color: var(--blue); font-size: .72rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: .07em;
}
.form-section-div::before, .form-section-div::after {
  content: ''; flex: 1; height: 1px; background: var(--border);
}
.form-section-div i { font-size: .8rem; }

/* ── FIELD ── */
.fg { margin-bottom: 20px; }
.fg-label {
  display: flex; align-items: center; gap: 6px;
  font-size: .73rem; font-weight: 700; color: var(--text2);
  text-transform: uppercase; letter-spacing: .05em; margin-bottom: 7px;
}
.fg-label .req { color: var(--red); font-size: 1rem; line-height: 1; }
.fg-label i { color: var(--blue); font-size: .8rem; flex-shrink: 0; }
.fg-input {
  width: 100%; padding: 12px 14px;
  border: 2px solid var(--border); border-radius: 10px;
  font-size: .9rem; color: var(--text); background: #fff;
  outline: none; transition: all .2s; font-family: 'DM Sans', sans-serif;
}
.fg-input:focus { border-color: var(--blue); box-shadow: 0 0 0 4px rgba(37,99,235,.08); }
.fg-input.has-error { border-color: var(--red); background: #fff8f8; }
.fg-input::placeholder { color: var(--text4); }
.fg-hint { font-size: .71rem; color: var(--text4); margin-top: 5px; display: flex; align-items: center; gap: 4px; }
.fg-err {
  font-size: .72rem; color: var(--red); margin-top: 5px;
  display: none; align-items: center; gap: 4px;
}
.fg-err.show { display: flex; }

/* ── TWO COL ROW ── */
.fg-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media(max-width:560px){ .fg-row { grid-template-columns: 1fr; } }

/* ── RADIO: YEAR ── */
.radio-list { display: flex; flex-direction: column; gap: 9px; margin-top: 2px; }
.radio-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 14px; border: 2px solid var(--border); border-radius: 10px;
  cursor: pointer; transition: all .2s; user-select: none;
}
.radio-item:hover { border-color: #bfdbfe; background: #f8fbff; }
.radio-item.chosen { border-color: var(--blue); background: var(--blue-light); }
.radio-item input[type="radio"] { width: 16px; height: 16px; accent-color: var(--blue); flex-shrink: 0; cursor: pointer; }
.radio-item .ri-label { font-size: .87rem; font-weight: 500; color: var(--text2); }
.radio-item.chosen .ri-label { color: var(--blue); font-weight: 700; }

/* ── RADIO: DOMAIN ── */
.domain-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 2px; }
@media(max-width:460px){ .domain-grid { grid-template-columns: 1fr; } }
.domain-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border: 2px solid var(--border); border-radius: 10px;
  cursor: pointer; transition: all .2s; user-select: none;
}
.domain-item:hover { border-color: #bfdbfe; background: #f8fbff; }
.domain-item.chosen { border-color: var(--blue); background: var(--blue-light); }
.domain-item input[type="radio"] { width: 15px; height: 15px; accent-color: var(--blue); flex-shrink: 0; cursor: pointer; }
.di-icon {
  width: 30px; height: 30px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; flex-shrink: 0;
}
.di-label { font-size: .82rem; font-weight: 600; color: var(--text2); line-height: 1.3; }
.domain-item.chosen .di-label { color: var(--blue); }

/* ── SUBMIT BUTTON ── */
.ws-submit-btn {
  width: 100%; padding: 15px;
  background: linear-gradient(135deg, var(--blue), var(--indigo));
  color: #fff; border: none; border-radius: 12px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1rem; font-weight: 800;
  cursor: pointer; transition: all .25s;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  box-shadow: 0 6px 22px rgba(37,99,235,.38);
  margin-top: 28px;
}
.ws-submit-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 32px rgba(37,99,235,.45); }
.ws-submit-btn:active { transform: translateY(0); }
.ws-submit-btn:disabled { opacity: .65; cursor: not-allowed; transform: none; }
.spin-icon {
  display: none; width: 18px; height: 18px;
  border: 2.5px solid rgba(255,255,255,.3); border-top-color: #fff;
  border-radius: 50%; animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── SIDEBAR ── */
.ws-sidebar { display: flex; flex-direction: column; gap: 16px; }

/* Seats urgency card */
.seats-card {
  background: linear-gradient(135deg, #fffbeb, #fef3c7);
  border: 1.5px solid #fde68a; border-radius: var(--radius);
  padding: 16px 18px;
}
.seats-card-top { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.seats-emoji { font-size: 1.4rem; }
.seats-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: .88rem; font-weight: 800; color: #92400e; }
.seats-sub { font-size: .72rem; color: #b45309; }
.seats-bar-wrap { margin-bottom: 6px; }
.seats-bar-top { display: flex; justify-content: space-between; font-size: .7rem; font-weight: 700; color: #92400e; margin-bottom: 5px; }
.seats-bar { height: 8px; background: #fde68a; border-radius: 999px; overflow: hidden; }
.seats-fill { height: 100%; background: linear-gradient(90deg, #f59e0b, #d97706); border-radius: 999px; width: 78%; animation: fillAnim 1.5s ease .5s both; }
@keyframes fillAnim { from{width:0} to{width:78%} }
.seats-note { font-size: .7rem; color: #b45309; font-weight: 600; }

/* Info card */
.ws-info-card { background: #fff; border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden; }
.ws-info-head { background: linear-gradient(135deg, #0f172a, #1e3a8a); padding: 15px 18px; }
.ws-info-head h3 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: .9rem; font-weight: 800; color: #fff; margin-bottom: 2px; }
.ws-info-head p { font-size: .72rem; color: rgba(255,255,255,.58); }
.ws-info-body { padding: 4px 0; }
.ws-info-row {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px 18px; border-bottom: 1px solid #f8fafc; transition: background .15s;
}
.ws-info-row:last-child { border-bottom: none; }
.ws-info-row:hover { background: #f8fafc; }
.wir-ico {
  width: 34px; height: 34px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; flex-shrink: 0; margin-top: 1px;
}
.wir-lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--text4); margin-bottom: 2px; }
.wir-val { font-size: .82rem; font-weight: 600; color: var(--text2); line-height: 1.45; }
.wir-val small { display: block; font-size: .72rem; color: var(--text4); font-weight: 400; margin-top: 1px; }

/* Who should attend */
.attend-card { background: #fff; border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden; }
.attend-head { background: linear-gradient(135deg, #064e3b, #065f46); padding: 14px 18px; }
.attend-head h3 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: .88rem; font-weight: 800; color: #fff; margin-bottom: 2px; }
.attend-head p { font-size: .71rem; color: rgba(255,255,255,.56); }
.attend-list { padding: 8px 0; }
.attend-item { display: flex; align-items: center; gap: 10px; padding: 9px 18px; font-size: .82rem; color: var(--text2); border-bottom: 1px solid #f8fafc; }
.attend-item:last-child { border-bottom: none; }
.attend-item i { color: var(--green); font-size: .8rem; flex-shrink: 0; }

/* Accreditation */
.accred-card { background: #fff; border-radius: var(--radius); border: 1px solid var(--border); padding: 16px 18px; text-align: center; }
.accred-title { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: var(--blue); margin-bottom: 12px; }
.accred-logos { display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap; }
.accred-logo {
  width: 46px; height: 46px; border-radius: 9px;
  background: var(--bg); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
}
.accred-note { font-size: .68rem; color: var(--text4); margin-top: 10px; }

/* ── SUCCESS OVERLAY ── */
.suc-overlay {
  display: none; position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,.55); backdrop-filter: blur(6px);
  align-items: center; justify-content: center; padding: 20px;
}
.suc-overlay.show { display: flex; animation: ovIn .25s ease; }
@keyframes ovIn { from{opacity:0} to{opacity:1} }
.suc-box {
  background: #fff; border-radius: 24px;
  width: 100%; max-width: 450px; text-align: center;
  padding: 44px 36px;
  box-shadow: 0 24px 64px rgba(0,0,0,.2);
  animation: popIn .3s cubic-bezier(.34,1.56,.64,1);
}
@keyframes popIn { from{transform:scale(.84);opacity:0} to{transform:scale(1);opacity:1} }
.suc-ring {
  width: 82px; height: 82px; border-radius: 50%;
  background: linear-gradient(135deg, #dcfce7, #bbf7d0);
  display: flex; align-items: center; justify-content: center;
  font-size: 2rem; color: #16a34a; margin: 0 auto 20px;
}
.suc-box h2 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.4rem; font-weight: 900; color: var(--text); margin-bottom: 8px; }
.suc-box > p { font-size: .87rem; color: var(--text3); line-height: 1.65; margin-bottom: 22px; }
.suc-detail { background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 14px 18px; margin-bottom: 24px; text-align: left; }
.suc-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: .82rem; }
.suc-row:last-child { border-bottom: none; }
.suc-row .k { color: var(--text3); }
.suc-row .v { color: var(--text); font-weight: 700; }
.suc-cta {
  display: inline-flex; align-items: center; gap: 8px;
  background: linear-gradient(135deg, var(--blue), var(--indigo));
  color: #fff; padding: 12px 30px; border-radius: 10px;
  font-size: .9rem; font-weight: 700;
  box-shadow: 0 4px 16px rgba(37,99,235,.32); transition: all .2s;
}
.suc-cta:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(37,99,235,.4); }

/* Error toast */
.err-toast {
  display: none; position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  background: #1e293b; color: #fff; padding: 12px 22px; border-radius: 12px;
  font-size: .85rem; font-weight: 600; z-index: 10000;
  box-shadow: 0 8px 28px rgba(0,0,0,.2); gap: 8px; align-items: center;
  max-width: 90vw; text-align: center;
}
.err-toast.show { display: flex; animation: toastIn .3s ease; }
@keyframes toastIn { from{transform:translateX(-50%) translateY(20px);opacity:0} to{transform:translateX(-50%) translateY(0);opacity:1} }
</style>
</head>
<body>

<!-- ── NAV ── -->
<nav class="nav">
  <a href="index.php" class="nav-logo">
   <img src="assets/images/logo.jpeg" alt="CourseFlow" style="height:42px;width:auto;object-fit:contain;display:block;">
  </a>
  <a href="index.html" class="nav-back"><i class="fas fa-arrow-left"></i> Back to Home</a>
</nav>

<!-- ── HERO BANNER ── -->
<div class="ws-hero">
  <div class="ws-hero-orb ws-hero-orb-1"></div>
  <div class="ws-hero-orb ws-hero-orb-2"></div>
  <div class="ws-hero-inner">
    <div class="ws-badge"><span class="ws-badge-dot"></span> Registration Open — 2026, 2027, 2028 Batch</div>
    <p style="font-size:.78rem;font-weight:700;color:rgba(255,255,255,.55);letter-spacing:.04em;margin-bottom:18px;margin-top:-10px;">
      <i class="fas fa-calendar-check" style="color:#4ade80;margin-right:5px;"></i> Starts Sunday, 15th March 2026
    </p>
    <!-- Google Organizer Badge -->
    <div style="display:inline-flex;align-items:center;gap:10px;background:rgba(255,255,255,.1);border:1.5px solid rgba(255,255,255,.2);border-radius:14px;padding:9px 18px;margin-bottom:22px;backdrop-filter:blur(8px);">
      <span style="font-size:.72rem;font-weight:600;color:rgba(255,255,255,.65);letter-spacing:.03em;">Organized by</span>
      <div style="width:1px;height:18px;background:rgba(255,255,255,.2);"></div>
      <div style="display:flex;align-items:center;gap:8px;">
        <svg width="22" height="22" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        <span style="font-family:'Plus Jakarta Sans',sans-serif;font-size:.95rem;font-weight:800;color:#fff;letter-spacing:.01em;">Google</span>
      </div>
    </div>
    <h1>Free Industry<br><span>Workshop Registration</span></h1>
    <p>Led by working industry professionals. Build real skills, earn a certificate, and get placement guidance — 100% free for students.</p>
     <div class="ws-hero-pills">
      <div class="ws-pill"><i class="fas fa-users"></i> Limited Seats</div>
      <div class="ws-pill"><i class="fas fa-certificate"></i> Free Certificate</div>
      <div class="ws-pill"><i class="fas fa-laptop-code"></i> Hands-on Projects</div>
      <div class="ws-pill ws-pill-time"><i class="fas fa-clock"></i> 10:00 AM – 6:00 PM</div>
    </div>
  </div>
</div>
<div class="ws-progress"></div>

<!-- ── PAGE LAYOUT ── -->
<div class="ws-page">

  <!-- ════ FORM CARD ════ -->
  <div class="ws-card">
    <div class="ws-card-header">
      <div class="ws-card-hico"><i class="fas fa-user-graduate"></i></div>
      <div>
        <h2>Workshop Registration Form</h2>
        <p>All fields are required — enter details accurately as per your college records</p>
      </div>
    </div>
    <div class="ws-card-body">

      <form id="wsForm" novalidate>

        <!-- ── PERSONAL INFORMATION ── -->
        <div class="form-section-div"><i class="fas fa-user"></i> Personal Information</div>

        <!-- Full Name -->
        <div class="fg">
          <div class="fg-label"><i class="fas fa-user-circle"></i> NAME (Full name as per SSC) <span class="req">*</span></div>
          <input class="fg-input" type="text" name="full_name" id="full_name" placeholder="Your full name exactly as on SSC certificate" maxlength="120" autocomplete="name">
          <div class="fg-err" id="err_full_name"><i class="fas fa-exclamation-circle"></i> Please enter your full name (min 3 characters)</div>
        </div>

        <!-- Hall Ticket & Email -->
        <div class="fg-row">
          <div class="fg">
            <div class="fg-label"><i class="fas fa-id-card"></i> Hall Ticket No / Roll No <span class="req">*</span></div>
            <input class="fg-input" type="text" name="hall_ticket" id="hall_ticket" placeholder="e.g. 23F41A0589" maxlength="30" autocomplete="off">
            <div class="fg-hint"><i class="fas fa-info-circle"></i> As printed on your college ID</div>
            <div class="fg-err" id="err_hall_ticket"><i class="fas fa-exclamation-circle"></i> Enter your hall ticket / roll number</div>
          </div>
          <div class="fg">
            <div class="fg-label"><i class="fas fa-envelope"></i> Email ID <span class="req">*</span></div>
            <input class="fg-input" type="email" name="email" id="email" placeholder="you@email.com" maxlength="120" autocomplete="email">
            <div class="fg-err" id="err_email"><i class="fas fa-exclamation-circle"></i> Enter a valid email address</div>
          </div>
        </div>

        <!-- Year of Studying -->
        <div class="fg">
          <div class="fg-label"><i class="fas fa-graduation-cap"></i> Year of Studying <span class="req">*</span></div>
          <div class="radio-list" id="year_group">
            <label class="radio-item" id="yr_1"><input type="radio" name="year_of_study" value="1"> <span class="ri-label">1st Year</span></label>
            <label class="radio-item" id="yr_2"><input type="radio" name="year_of_study" value="2"> <span class="ri-label">2nd Year</span></label>
            <label class="radio-item" id="yr_3"><input type="radio" name="year_of_study" value="3"> <span class="ri-label">3rd Year</span></label>
            <label class="radio-item" id="yr_4"><input type="radio" name="year_of_study" value="4"> <span class="ri-label">4th Year / Final Year</span></label>
          </div>
          <div class="fg-err" id="err_year"><i class="fas fa-exclamation-circle"></i> Please select your year of study</div>
        </div>

        <!-- Contact & WhatsApp -->
        <div class="fg-row">
          <div class="fg">
            <div class="fg-label"><i class="fas fa-phone"></i> Contact Number <span class="req">*</span></div>
            <input class="fg-input" type="tel" name="contact_number" id="contact_number" placeholder="10-digit mobile number" maxlength="15" autocomplete="tel">
            <div class="fg-err" id="err_contact"><i class="fas fa-exclamation-circle"></i> Enter a valid 10-digit Indian mobile number</div>
          </div>
          <div class="fg">
            <div class="fg-label"><i class="fab fa-whatsapp" style="color:#25d366"></i> WhatsApp Number <span class="req">*</span></div>
            <input class="fg-input" type="tel" name="whatsapp_number" id="whatsapp_number" placeholder="WhatsApp mobile number" maxlength="15" autocomplete="off">
            <div class="fg-hint"><i class="fas fa-info-circle"></i> Workshop link will be sent here</div>
            <div class="fg-err" id="err_whatsapp"><i class="fas fa-exclamation-circle"></i> Enter a valid WhatsApp number</div>
          </div>
        </div>

        <!-- ── COLLEGE INFORMATION ── -->
        <div class="form-section-div"><i class="fas fa-university"></i> College Information</div>

        <!-- College Name -->
        <div class="fg">
          <div class="fg-label"><i class="fas fa-building"></i> College Name (Full Name) <span class="req">*</span></div>
          <input class="fg-input" type="text" name="college_name" id="college_name" placeholder="Full official name of your college / institution" maxlength="220" autocomplete="organization">
          <div class="fg-err" id="err_college"><i class="fas fa-exclamation-circle"></i> Enter your college's full name</div>
        </div>

        <!-- College Location & Department -->
        <div class="fg-row">
          <div class="fg">
            <div class="fg-label"><i class="fas fa-map-marker-alt"></i> College Location <span class="req">*</span></div>
            <input class="fg-input" type="text" name="college_location" id="college_location" placeholder="Location, District" maxlength="180" autocomplete="address-level2">
            <div class="fg-hint"><i class="fas fa-info-circle"></i> e.g. Kukatpally, Hyderabad</div>
            <div class="fg-err" id="err_location"><i class="fas fa-exclamation-circle"></i> Enter college location / district</div>
          </div>
          <div class="fg">
            <div class="fg-label"><i class="fas fa-book-open"></i> Department <span class="req">*</span></div>
            <input class="fg-input" type="text" name="department" id="department" placeholder="e.g. Computer Science and Engineering" maxlength="180" autocomplete="off">
            <div class="fg-err" id="err_dept"><i class="fas fa-exclamation-circle"></i> Enter your department name</div>
          </div>
        </div>

        <!-- ── INTERESTED DOMAIN ── -->
        <div class="form-section-div"><i class="fas fa-code-branch"></i> Interested Domain</div>

        <div class="fg">
          <div class="fg-label"><i class="fas fa-layer-group"></i> Select Your Interested Domain <span class="req">*</span></div>
          <div class="domain-grid" id="domain_group">
            <!-- Tech Domains -->
            <label class="domain-item" id="dom_aiml">
              <input type="radio" name="interested_domain" value="AIML">
              <div class="di-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-robot"></i></div>
              <span class="di-label">AIML</span>
            </label>
            <label class="domain-item" id="dom_cyber">
              <input type="radio" name="interested_domain" value="Cyber Security">
              <div class="di-icon" style="background:#fff1f2;color:#e11d48"><i class="fas fa-shield-alt"></i></div>
              <span class="di-label">Cyber Security</span>
            </label>
            <label class="domain-item" id="dom_web">
              <input type="radio" name="interested_domain" value="Web Development">
              <div class="di-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-globe"></i></div>
              <span class="di-label">Web Development</span>
            </label>
            <label class="domain-item" id="dom_java">
              <input type="radio" name="interested_domain" value="Java Fullstack Development">
              <div class="di-icon" style="background:#fffbeb;color:#d97706"><i class="fab fa-java"></i></div>
              <span class="di-label">Java Fullstack Development</span>
            </label>
            <label class="domain-item" id="dom_analytics">
              <input type="radio" name="interested_domain" value="Data Analytics">
              <div class="di-icon" style="background:#fdf4ff;color:#9333ea"><i class="fas fa-chart-bar"></i></div>
              <span class="di-label">Data Analytics</span>
            </label>
            <label class="domain-item" id="dom_datascience">
              <input type="radio" name="interested_domain" value="Data Science">
              <div class="di-icon" style="background:#f0fdfa;color:#0d9488"><i class="fas fa-brain"></i></div>
              <span class="di-label">Data Science</span>
            </label>
            <!-- Non-Tech Domains -->
            <label class="domain-item" id="dom_hr">
              <input type="radio" name="interested_domain" value="HR">
              <div class="di-icon" style="background:#fef9ee;color:#ea7c1a"><i class="fas fa-people-arrows"></i></div>
              <span class="di-label">HR</span>
            </label>
            <label class="domain-item" id="dom_finance">
              <input type="radio" name="interested_domain" value="Finance">
              <div class="di-icon" style="background:#f0fdf4;color:#059669"><i class="fas fa-coins"></i></div>
              <span class="di-label">Finance</span>
            </label>
            <label class="domain-item" id="dom_marketing">
              <input type="radio" name="interested_domain" value="Marketing">
              <div class="di-icon" style="background:#fdf2ff;color:#a21caf"><i class="fas fa-bullhorn"></i></div>
              <span class="di-label">Marketing</span>
            </label>
          </div>
          <div class="fg-err" id="err_domain"><i class="fas fa-exclamation-circle"></i> Please select a domain you are interested in</div>
        </div>

        <!-- Hidden meta -->
        <input type="hidden" name="source" value="landing_page_cta">

        <!-- Submit -->
        <button type="submit" class="ws-submit-btn" id="wsSubmitBtn">
          <span class="spin-icon" id="btnSpinner"></span>
          <i class="fas fa-rocket" id="btnIcon"></i>
          <span id="btnTxt">Register for Free Workshop</span>
        </button>

        <p style="text-align:center;font-size:.72rem;color:var(--text4);margin-top:14px;line-height:1.6;">
          <i class="fas fa-lock"></i> &nbsp;Your data is secure and used solely for workshop coordination. No spam, ever.
        </p>

      </form>
    </div>
  </div>

  <!-- ════ SIDEBAR ════ -->
  <div class="ws-sidebar">

    <!-- Seats urgency -->
    <div class="seats-card">
      <div class="seats-card-top">
        <div class="seats-emoji">🔥</div>
        <div>
          <div class="seats-title">Seats filling fast!</div>
          <div class="seats-sub">2026 batch is almost full</div>
        </div>
      </div>
      <div class="seats-bar-wrap">
        <div class="seats-bar-top"><span>Registrations</span><span>78% filled</span></div>
        <div class="seats-bar"><div class="seats-fill"></div></div>
      </div>
      <div class="seats-note">⚡ Only a few seats remaining — register now before it closes</div>
    </div>

    <!-- Workshop details -->
    <div class="ws-info-card">
      <div class="ws-info-head">
        <h3>Workshop Details</h3>
        <p>Everything you need to know</p>
      </div>
      <div class="ws-info-body">
        <!-- ── UPDATED DATE ROW ── -->
        <div class="ws-info-row">
          <div class="wir-ico" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-calendar-alt"></i></div>
          <div>
            <div class="wir-lbl">Date</div>
            <div class="wir-val">Sunday, 15th March 2026<small>2027 &amp; 2028 batch dates confirmed on WhatsApp</small></div>
          </div>
        </div>
        <div class="ws-info-row">
          <div class="wir-ico" style="background:#eff6ff;color:#2563eb"><i class="fas fa-clock"></i></div>
          <div>
            <div class="wir-lbl">Duration</div>
            <div class="wir-val">Full Day Session<small>10:00 AM – 6:00 PM</small></div>
          </div>
        </div>
        <div class="ws-info-row">
          <div class="wir-ico" style="background:#fffbeb;color:#d97706"><i class="fas fa-laptop"></i></div>
          <div>
            <div class="wir-lbl">Mode</div>
            <div class="wir-val">Online<small>Join link sent on WhatsApp</small></div>
          </div>
        </div>
        <div class="ws-info-row">
          <div class="wir-ico" style="background:#fdf4ff;color:#9333ea"><i class="fas fa-gift"></i></div>
          <div>
            <div class="wir-lbl">You Will Get</div>
            <div class="wir-val">Certificate + Project Kit<small>+ Placement Guidance + Mentorship</small></div>
          </div>
        </div>
        <div class="ws-info-row">
          <div class="wir-ico" style="background:#fff1f2;color:#e11d48"><i class="fas fa-rupee-sign"></i></div>
          <div>
            <div class="wir-lbl">Cost</div>
            <div class="wir-val" style="color:#16a34a;font-weight:800">100% FREE<small style="color:var(--text4)">No registration fee whatsoever</small></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Who should attend -->
    <div class="attend-card">
      <div class="attend-head">
        <h3>Who Should Attend?</h3>
        <p>This workshop is ideal for</p>
      </div>
      <div class="attend-list">
        <div class="attend-item"><i class="fas fa-check-circle"></i> Engineering students (1st–4th year)</div>
        <div class="attend-item"><i class="fas fa-check-circle"></i> BCA / MCA / BSc CS students</div>
        <div class="attend-item"><i class="fas fa-check-circle"></i> Freshers seeking industry exposure</div>
        <div class="attend-item"><i class="fas fa-check-circle"></i> Anyone looking to upskill in tech</div>
        <div class="attend-item"><i class="fas fa-check-circle"></i> Students targeting campus placements</div>
      </div>
    </div>

    <!-- Accreditation -->
    <div class="accred-card">
      <div class="accred-title">Accredited By</div>
      <div class="accred-logos">
        <div class="accred-logo" title="AICTE"><i class="fas fa-university" style="color:#1e40af"></i></div>
        <div class="accred-logo" title="NASSCOM"><i class="fas fa-laptop-code" style="color:#16a34a"></i></div>
        <div class="accred-logo" title="NIELIT"><i class="fas fa-award" style="color:#d97706"></i></div>
        <div class="accred-logo" title="National Career Service"><i class="fas fa-briefcase" style="color:#7c3aed"></i></div>
      </div>
      <div class="accred-note">Recognized by leading national bodies</div>
    </div>

  </div><!-- /sidebar -->

</div><!-- /ws-page -->

<!-- ── SUCCESS MODAL ── -->
<div class="suc-overlay" id="sucOverlay">
  <div class="suc-box">
    <div class="suc-ring"><i class="fas fa-check"></i></div>
    <h2>You're Registered! 🎉</h2>
    <p>You're registered! Join our WhatsApp group to get workshop updates, schedule, and resources directly on your phone.</p>
    <div class="suc-detail" id="sucDetail">
      <div class="suc-row"><span class="k">Name</span><span class="v" id="sd_name">—</span></div>
      <div class="suc-row"><span class="k">Domain</span><span class="v" id="sd_domain">—</span></div>
      <div class="suc-row"><span class="k">WhatsApp</span><span class="v" id="sd_wa">—</span></div>
      <div class="suc-row"><span class="k">Registration ID</span><span class="v" id="sd_regid">—</span></div>
    </div>
    <!-- WhatsApp Group Button -->
    <a href="https://chat.whatsapp.com/FwmyDyZ1JYmHSmYVjlPO4a?mode=gi_t" target="_blank" style="display:inline-flex;align-items:center;gap:9px;background:#25d366;color:#fff;padding:13px 28px;border-radius:10px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:700;box-shadow:0 4px 16px rgba(37,211,102,.35);transition:all .2s;margin-bottom:12px;width:100%;justify-content:center;">
    <i class="fab fa-whatsapp" style="font-size:1.1rem;"></i> Join WhatsApp Group
    </a>
    <a href="index.html" class="suc-cta">
    <i class="fas fa-home"></i> Back to Home</a>
  </div>
</div>

<!-- Error toast -->
<div class="err-toast" id="errToast"><i class="fas fa-exclamation-circle" style="color:#f87171;flex-shrink:0"></i> <span id="errToastMsg"></span></div>

<script>
/* ── Utility: show/hide field errors ── */
function showFE(id, inputId) {
  var el = document.getElementById(id);
  if (el) el.classList.add('show');
  if (inputId) { var i = document.getElementById(inputId); if (i) i.classList.add('has-error'); }
}
function hideFE(id, inputId) {
  var el = document.getElementById(id);
  if (el) el.classList.remove('show');
  if (inputId) { var i = document.getElementById(inputId); if (i) i.classList.remove('has-error'); }
}

/* ── Radio: Year ── */
document.querySelectorAll('#year_group .radio-item input').forEach(function(r) {
  r.addEventListener('change', function() {
    document.querySelectorAll('#year_group .radio-item').forEach(function(li) { li.classList.remove('chosen'); });
    this.closest('.radio-item').classList.add('chosen');
    hideFE('err_year');
  });
});

/* ── Radio: Domain ── */
document.querySelectorAll('#domain_group .domain-item input').forEach(function(r) {
  r.addEventListener('change', function() {
    document.querySelectorAll('#domain_group .domain-item').forEach(function(li) { li.classList.remove('chosen'); });
    this.closest('.domain-item').classList.add('chosen');
    hideFE('err_domain');
  });
});

/* ── Live inline validation on blur ── */
var liveRules = [
  { id: 'full_name',       err: 'err_full_name',  test: function(v){ return v.trim().length >= 3; } },
  { id: 'hall_ticket',     err: 'err_hall_ticket', test: function(v){ return v.trim().length >= 3; } },
  { id: 'email',           err: 'err_email',       test: function(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); } },
  { id: 'contact_number',  err: 'err_contact',     test: function(v){ return /^[6-9]\d{9}$/.test(v.replace(/[\s\-]/g,'')); } },
  { id: 'whatsapp_number', err: 'err_whatsapp',    test: function(v){ return /^[6-9]\d{9}$/.test(v.replace(/[\s\-]/g,'')); } },
  { id: 'college_name',    err: 'err_college',     test: function(v){ return v.trim().length >= 5; } },
  { id: 'college_location',err: 'err_location',    test: function(v){ return v.trim().length >= 3; } },
  { id: 'department',      err: 'err_dept',        test: function(v){ return v.trim().length >= 3; } },
];
liveRules.forEach(function(rule) {
  var el = document.getElementById(rule.id);
  if (!el) return;
  el.addEventListener('blur', function() {
    if (!rule.test(this.value)) showFE(rule.err, rule.id);
    else hideFE(rule.err, rule.id);
  });
  el.addEventListener('input', function() {
    if (rule.test(this.value)) hideFE(rule.err, rule.id);
  });
});

/* ── Toast helper ── */
function showToast(msg) {
  var t = document.getElementById('errToast');
  document.getElementById('errToastMsg').textContent = msg;
  t.classList.add('show');
  setTimeout(function(){ t.classList.remove('show'); }, 4000);
}

/* ── Form submit → AJAX → workshop_register.php ── */
document.getElementById('wsForm').addEventListener('submit', function(e) {
  e.preventDefault();
  var ok = true;

  var fn = document.getElementById('full_name').value;
  if (fn.trim().length < 3) { showFE('err_full_name','full_name'); ok = false; }

  var ht = document.getElementById('hall_ticket').value;
  if (ht.trim().length < 3) { showFE('err_hall_ticket','hall_ticket'); ok = false; }

  var em = document.getElementById('email').value;
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em.trim())) { showFE('err_email','email'); ok = false; }

  var yr = document.querySelector('input[name="year_of_study"]:checked');
  if (!yr) { showFE('err_year'); ok = false; }

  var cn = document.getElementById('contact_number').value.replace(/[\s\-]/g,'');
  if (!/^[6-9]\d{9}$/.test(cn)) { showFE('err_contact','contact_number'); ok = false; }

  var wa = document.getElementById('whatsapp_number').value.replace(/[\s\-]/g,'');
  if (!/^[6-9]\d{9}$/.test(wa)) { showFE('err_whatsapp','whatsapp_number'); ok = false; }

  var col = document.getElementById('college_name').value;
  if (col.trim().length < 5) { showFE('err_college','college_name'); ok = false; }

  var loc = document.getElementById('college_location').value;
  if (loc.trim().length < 3) { showFE('err_location','college_location'); ok = false; }

  var dep = document.getElementById('department').value;
  if (dep.trim().length < 3) { showFE('err_dept','department'); ok = false; }

  var dom = document.querySelector('input[name="interested_domain"]:checked');
  if (!dom) { showFE('err_domain'); ok = false; }

  if (!ok) {
    var firstErr = document.querySelector('.fg-input.has-error, .fg-err.show');
    if (firstErr) { firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    showToast('Please fill all required fields correctly.');
    return;
  }

  var btn = document.getElementById('wsSubmitBtn');
  btn.disabled = true;
  document.getElementById('btnSpinner').style.display = 'block';
  document.getElementById('btnIcon').style.display = 'none';
  document.getElementById('btnTxt').textContent = 'Submitting...';

  var fd = new FormData(this);

  function resetBtn() {
    btn.disabled = false;
    document.getElementById('btnSpinner').style.display = 'none';
    document.getElementById('btnIcon').style.display = '';
    document.getElementById('btnTxt').textContent = 'Register for Free Workshop';
  }

  fetch('workshop_register.php', { method: 'POST', body: fd })
    .then(function(r) {
      return r.text();
    })
    .then(function(raw) {
      var data;
      try {
        var clean = raw.replace(/^\s+/, '');
        data = JSON.parse(clean);
      } catch(e) {
        resetBtn();
        showToast('Server error. Please contact support. (' + raw.substring(0, 120) + ')');
        return;
      }
      if (data.success) {
        document.getElementById('sd_name').textContent   = data.name     || fn.trim();
        document.getElementById('sd_domain').textContent = data.domain   || (dom ? dom.value : '');
        document.getElementById('sd_wa').textContent     = data.whatsapp || wa;
        document.getElementById('sd_regid').textContent  = '#WS' + String(data.reg_id || 0).padStart(6, '0');
        document.getElementById('sucOverlay').classList.add('show');
      } else {
        resetBtn();
        showToast(data.message || 'Registration failed. Please try again.');
      }
    })
    .catch(function(err) {
      resetBtn();
      showToast('Could not reach server. Please check your internet and try again.');
    });
});

/* Close success overlay on backdrop click */
document.getElementById('sucOverlay').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('show');
});
</script>
</body>
</html>