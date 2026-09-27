<?php
/**
 * internship_payment.php — CourseFlow
 * Fixes:
 *  1. Per-app UPI deep links (PhonePe / GPay / Paytm / Any UPI)
 *  2. UPI callback: auto-fill UTR on return from payment app
 *  3. Confirmation email on submission (handled in submit_internship_payment.php)
 */

ob_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

$conn->query("
  CREATE TABLE IF NOT EXISTS internship_payments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    reg_id           INT           NOT NULL,
    full_name        VARCHAR(150)  NOT NULL,
    email            VARCHAR(150)  NOT NULL,
    mobile_number    VARCHAR(20)   NOT NULL,
    internship_chosen VARCHAR(120) NOT NULL,
    utr_number       VARCHAR(60)   NOT NULL,
    terminal_name    VARCHAR(60)   NOT NULL DEFAULT 'Terminal 1',
    terminal_upi     VARCHAR(80)   NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    payment_status   ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note       VARCHAR(500)  DEFAULT NULL,
    submitted_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at      DATETIME      DEFAULT NULL,
    INDEX idx_reg (reg_id), INDEX idx_email (email),
    INDEX idx_utr (utr_number), INDEX idx_status (payment_status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

define('INTERNSHIP_FEE_BASE', 299.00);
define('GST_RATE', 1.00);
$gst_amount = round(INTERNSHIP_FEE_BASE * GST_RATE / 100, 2);
$fee        = round(INTERNSHIP_FEE_BASE + $gst_amount, 2);

if (!isset($_GET['reg_id']) || !is_numeric($_GET['reg_id'])) die('Invalid registration link.');
$reg_id = (int)$_GET['reg_id'];

$stmt = $conn->prepare("SELECT * FROM internship_registrations WHERE id=? LIMIT 1");
$stmt->bind_param("i",$reg_id); $stmt->execute();
$reg = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$reg) die('Registration not found. Please contact support.');

// Auto-delete ghost rows (admin edit bug: terminal_name='Admin', amount=0).
$conn->query("DELETE FROM internship_payments WHERE reg_id=$reg_id AND terminal_name='Admin' AND (amount=0 OR amount='0.00' OR amount='0' OR amount IS NULL OR amount='')");

$stmt2 = $conn->prepare("SELECT * FROM internship_payments WHERE reg_id=? ORDER BY id DESC LIMIT 1");
$stmt2->bind_param("i",$reg_id); $stmt2->execute();
$existing = $stmt2->get_result()->fetch_assoc(); $stmt2->close();

$ep_status = $existing['payment_status'] ?? null;
$show_form = !$existing || $ep_status === 'rejected' || $ep_status === 'payment_later';

$terminals = [
    ['name'=>'Terminal 1','upi'=>'yourupi1@ybl'],
    ['name'=>'Terminal 2','upi'=>'yourupi2@ybl'],
    ['name'=>'Terminal 3','upi'=>'yourupi3@ybl'],
];

function qrUrl(string $upi, float $amount, int $size=220): string {
    $data = 'upi://pay?pa='.rawurlencode($upi).'&pn='.rawurlencode('CourseFlow')
          . '&am='.number_format($amount,2,'.','').'&cu=INR&tn='.rawurlencode('Internship Fee');
    return 'https://api.qrserver.com/v1/create-qr-code/?size='.$size.'x'.$size
          .'&data='.urlencode($data).'&format=png&ecc=M&margin=4';
}

$page_error  = $_GET['error'] ?? '';
/* Read UTR pre-fill from UPI callback params */
$prefill_utr = '';
foreach (['txnId','txnRef','txnid','txnref','utr','UTR','referenceId','rrn','transactionId'] as $k) {
    if (!empty($_GET[$k])) { $prefill_utr = preg_replace('/[^A-Za-z0-9]/','', $_GET[$k]); break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<title>Internship Payment — CourseFlow</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f3f6fb;color:#0f172a;min-height:100vh;}
.topbar{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:12px 20px;background:rgba(15,27,61,.94);backdrop-filter:blur(10px);}
.logo{display:flex;align-items:center;gap:10px;}
.logo-icon{width:34px;height:34px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.logo-icon i{color:#fff;font-size:.85rem;}
.logo-text{font-weight:800;font-size:.95rem;color:#fff;}
.logo-sub{font-size:.58rem;color:rgba(255,255,255,.45);display:block;font-weight:400;letter-spacing:.07em;text-transform:uppercase;margin-top:-2px;}
.back-btn{display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:7px 13px;color:#fff;font-size:.8rem;font-weight:500;text-decoration:none;white-space:nowrap;flex-shrink:0;}
.wrap{max-width:1060px;margin:0 auto;padding:24px 16px 80px;}
.page-head{margin-bottom:20px;}
.page-head h1{font-size:1.2rem;font-weight:800;color:#1e293b;display:flex;align-items:center;gap:10px;}
.ph-icon{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#1e40af,#3b82f6);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem;flex-shrink:0;}
.page-head p{color:#64748b;font-size:.8rem;margin:6px 0 0;}
.pay-wrap{display:grid;grid-template-columns:300px 1fr;gap:18px;align-items:start;}
.summary-card{background:#fff;border-radius:18px;box-shadow:0 4px 24px rgba(0,0,0,.07);overflow:hidden;border:1px solid rgba(0,0,0,.06);}
@media(min-width:861px){.summary-card{position:sticky;top:72px;}}
.s-head{background:linear-gradient(135deg,#1e40af,#3b82f6);padding:22px 20px 18px;color:#fff;position:relative;overflow:hidden;}
.s-head::before{content:'';position:absolute;top:-40px;right:-40px;width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.08);}
.s-head::after{content:'';position:absolute;bottom:-28px;left:-18px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.05);}
.av{width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.35);display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:800;color:#fff;margin-bottom:10px;position:relative;z-index:1;}
.s-head h3{font-size:.92rem;font-weight:700;margin:0 0 2px;position:relative;z-index:1;}
.s-head p{font-size:.76rem;opacity:.8;margin:0;position:relative;z-index:1;}
.s-body{padding:16px 18px;}
.srow{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9;}
.srow:last-child{border-bottom:none;}
.sico{width:28px;height:28px;border-radius:8px;background:#eff6ff;color:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:.76rem;flex-shrink:0;margin-top:2px;}
.slbl{font-size:.65rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;font-weight:600;margin-bottom:1px;}
.sval{font-size:.83rem;font-weight:600;color:#1e293b;line-height:1.4;word-break:break-word;}
.fee-box{background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1px solid #bfdbfe;border-radius:12px;padding:12px 14px;margin:10px 0 4px;}
.frow{display:flex;justify-content:space-between;align-items:center;padding:3px 0;font-size:.8rem;}
.frow.dv{border-top:1.5px dashed #bfdbfe;margin-top:6px;padding-top:7px;}
.flbl{color:#64748b;font-weight:500;} .fval{font-weight:700;color:#1e293b;}
.fval.blue{color:#1e40af;font-size:.9rem;}
/* Steps */
.steps-panel{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.06);margin-bottom:16px;}
.steps-row{display:flex;align-items:center;padding:12px 16px;}
.sti{display:flex;align-items:center;}
.stl{flex:1;height:2px;background:#e2e8f0;border-radius:1px;margin:0 6px;}
.stl.done{background:linear-gradient(90deg,#059669,#10b981);}
.stn{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;flex-shrink:0;transition:all .3s;}
.stn.active{background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;box-shadow:0 2px 8px rgba(59,130,246,.3);}
.stn.done{background:linear-gradient(135deg,#059669,#10b981);color:#fff;}
.stn.inactive{background:#f1f5f9;color:#94a3b8;}
.stt{font-size:.66rem;font-weight:600;color:#64748b;white-space:nowrap;margin-left:5px;}
.stt.active{color:#1e40af;} .stt.done{color:#059669;}
/* Panels */
.pay-panel{background:#fff;border-radius:18px;box-shadow:0 4px 24px rgba(0,0,0,.07);border:1px solid rgba(0,0,0,.06);overflow:hidden;margin-bottom:16px;}
.phead{padding:14px 18px 12px;border-bottom:1px solid #f1f5f9;display:flex;align-items:flex-start;gap:10px;}
.phnum{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;font-weight:800;font-size:.76rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;}
.phead h3{font-size:.88rem;font-weight:700;margin:0;color:#1e293b;}
.phead p{font-size:.73rem;color:#94a3b8;margin:2px 0 0;}
/* QR */
.qr-area{padding:16px 18px;border-bottom:1px solid #f1f5f9;}
.term-tabs{display:flex;gap:6px;margin-bottom:14px;overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:2px;}
.term-tabs::-webkit-scrollbar{height:3px;}
.term-tabs::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:99px;}
.ttab{flex:1;min-width:90px;padding:8px 6px;border:2px solid #e2e8f0;border-radius:10px;background:#f8fafc;font-size:.73rem;font-weight:700;color:#64748b;cursor:pointer;transition:all .18s;text-align:center;font-family:inherit;white-space:nowrap;}
.ttab.active{border-color:#1e40af;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;box-shadow:0 3px 8px rgba(30,64,175,.22);}
.qr-box{background:#f8fafc;border:2px solid #e2e8f0;border-radius:14px;padding:16px;text-align:center;}
.qr-frame{width:200px;height:200px;margin:0 auto 12px;background:#fff;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 4px 18px rgba(0,0,0,.08);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;}
.qr-frame img{width:180px;height:180px;display:block;object-fit:contain;}
.qr-spinner{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#fff;gap:6px;color:#94a3b8;font-size:.76rem;}
.qr-spinner i{font-size:1.6rem;color:#3b82f6;}
.qr-amt-badge{display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;padding:5px 13px;border-radius:999px;font-size:.92rem;font-weight:800;margin-bottom:7px;}
.qr-pre{font-size:.6rem;opacity:.75;background:rgba(255,255,255,.2);padding:1px 5px;border-radius:999px;}
.qr-tname{font-size:.78rem;font-weight:700;color:#1e293b;margin-bottom:2px;}
.qr-upi{font-size:.71rem;font-family:monospace;color:#3b82f6;background:#eff6ff;display:inline-block;padding:3px 9px;border-radius:999px;border:1px solid #bfdbfe;margin-bottom:14px;word-break:break-all;}

/* ── App buttons ── */
.app-lbl{font-size:.68rem;color:#475569;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;display:block;}
.app-row{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;}
/* Base style */
.app-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 18px;border-radius:11px;font-size:.82rem;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:transform .15s,box-shadow .15s;min-width:88px;letter-spacing:.01em;}
.app-btn:active{transform:scale(.95);}
/* PhonePe — official purple */
.app-btn.pp{background:#5f259f;color:#fff;box-shadow:0 3px 10px rgba(95,37,159,.35);}
.app-btn.pp:hover{background:#4e1d85;box-shadow:0 5px 16px rgba(95,37,159,.45);}
/* GPay — Google blue */
.app-btn.gp{background:#1a73e8;color:#fff;box-shadow:0 3px 10px rgba(26,115,232,.35);}
.app-btn.gp:hover{background:#1558b0;box-shadow:0 5px 16px rgba(26,115,232,.45);}
/* Paytm — navy */
.app-btn.pt{background:#002970;color:#fff;box-shadow:0 3px 10px rgba(0,41,112,.35);}
.app-btn.pt:hover{background:#001d52;box-shadow:0 5px 16px rgba(0,41,112,.45);}
/* Any UPI — teal */
.app-btn.up{background:#0ea5e9;color:#fff;box-shadow:0 3px 10px rgba(14,165,233,.35);}
.app-btn.up:hover{background:#0284c7;box-shadow:0 5px 16px rgba(14,165,233,.45);}

/* UTR return banner */
.utr-return-banner{display:none;margin-top:13px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:11px;padding:12px 14px;gap:10px;align-items:flex-start;}
.utr-return-banner.show{display:flex;}
.utr-return-banner i{color:#16a34a;font-size:1.1rem;flex-shrink:0;margin-top:2px;}
.utr-return-banner div{font-size:.78rem;color:#14532d;line-height:1.55;}
.utr-return-banner strong{display:block;font-size:.83rem;margin-bottom:2px;}

.pay-note{display:flex;align-items:flex-start;gap:9px;background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1px solid #bfdbfe;border-radius:10px;padding:11px 13px;margin-top:13px;}
.pay-note i{color:#3b82f6;font-size:.85rem;margin-top:2px;flex-shrink:0;}
.pay-note p{margin:0;font-size:.77rem;color:#1e40af;line-height:1.55;}

/* Form */
.form-sec{padding:16px 18px;}
.amt-ban{background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:12px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:8px;}
.abl{color:rgba(255,255,255,.8);font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;}
.aba{color:#fff;font-size:1.35rem;font-weight:800;}
.abs{color:rgba(255,255,255,.65);font-size:.68rem;margin-top:1px;}
.fr{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.fg{margin-bottom:13px;}
.fl{display:block;font-size:.7rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px;}
.fl span{color:#ef4444;}
.fi{width:100%;padding:10px 12px;border:2px solid #e2e8f0;border-radius:10px;font-size:.85rem;color:#1e293b;background:#f8fafc;transition:all .2s;outline:none;font-family:inherit;}
.fi:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 4px rgba(59,130,246,.1);}
.fi[readonly]{background:#f1f5f9;color:#64748b;cursor:not-allowed;}
.fi.hi{border-color:#3b82f6;background:#fff;font-size:.88rem;font-weight:600;}
/* Green pulse when UTR auto-filled */
.fi.autofilled{border-color:#16a34a !important;background:#f0fdf4 !important;animation:utrPulse .7s ease;}
@keyframes utrPulse{0%{box-shadow:0 0 0 0 rgba(22,163,74,.5);}70%{box-shadow:0 0 0 10px rgba(22,163,74,0);}100%{box-shadow:none;}}
.uhint{font-size:.69rem;color:#94a3b8;margin-top:5px;display:flex;align-items:flex-start;gap:4px;line-height:1.45;}
.uhint.ok{color:#16a34a !important;}
.uhint.warn{color:#d97706 !important;}
.warn-box{display:flex;align-items:flex-start;gap:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 13px;margin:0 0 15px;}
.warn-box i{color:#d97706;font-size:.85rem;margin-top:2px;flex-shrink:0;}
.warn-box p{margin:0;font-size:.76rem;color:#92400e;line-height:1.5;}
.sub-btn{width:100%;padding:13px 20px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;border:none;border-radius:12px;font-size:.92rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;transition:all .22s;box-shadow:0 4px 14px rgba(59,130,246,.33);font-family:inherit;}
.sub-btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(59,130,246,.43);}
.sub-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}

/* Status blocks */
.sb{display:flex;align-items:flex-start;gap:13px;border-radius:16px;padding:16px 18px;margin-bottom:20px;}
.sb.pending{background:#fffbeb;border:1.5px solid #fde68a;}
.sb.approved{background:#f0fdf4;border:1.5px solid #bbf7d0;}
.sb.rejected{background:#fff1f2;border:1.5px solid #fecdd3;}
.sbi{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.pending .sbi{background:#fef3c7;color:#d97706;} .approved .sbi{background:#dcfce7;color:#16a34a;} .rejected .sbi{background:#ffe4e6;color:#e11d48;}
.sb h4{margin:0 0 4px;font-size:.88rem;font-weight:700;}
.pending h4{color:#92400e;} .approved h4{color:#14532d;} .rejected h4{color:#881337;}
.sb p{margin:0;font-size:.8rem;opacity:.85;line-height:1.5;}
.cs{text-align:center;padding:40px 18px;}
.csico{width:72px;height:72px;border-radius:18px;display:inline-flex;align-items:center;justify-content:center;font-size:1.7rem;margin-bottom:16px;}
.action-row{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:18px;}
.btn-a{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:10px;font-weight:700;text-decoration:none;font-size:.84rem;}
.btn-outline{border:2px solid #e2e8f0;color:#64748b;}

/* Popups */
.popup-overlay{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;}
.popup-overlay.show{display:flex;animation:ovIn .2s ease;}
@keyframes ovIn{from{opacity:0}to{opacity:1}}
.popup-box{background:#fff;border-radius:18px;width:100%;max-width:380px;box-shadow:0 24px 60px rgba(0,0,0,.22);overflow:hidden;animation:popIn .25s cubic-bezier(.34,1.56,.64,1);}
@keyframes popIn{from{transform:scale(.88);opacity:0}to{transform:scale(1);opacity:1}}
.popup-icon-wrap{padding:26px 22px 16px;text-align:center;}
.popup-icon{width:66px;height:66px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:12px;}
.popup-icon.error{background:#fff1f2;color:#e11d48;}
.popup-icon.warning{background:#fffbeb;color:#d97706;}
.popup-title{font-size:1.05rem;font-weight:800;color:#1e293b;margin:0 0 6px;}
.popup-msg{font-size:.83rem;color:#64748b;line-height:1.6;margin:0;}
.popup-footer{padding:14px 22px 20px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}
.popup-btn{padding:10px 24px;border-radius:10px;font-size:.86rem;font-weight:700;border:none;cursor:pointer;font-family:inherit;}
.popup-btn.primary{background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;}
.popup-btn.danger{background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;}
@keyframes qrFade{from{opacity:0}to{opacity:1}}
.qr-fade{animation:qrFade .3s ease;}

/* ══ MOBILE ══ */
@media(max-width:860px){
  .pay-wrap{grid-template-columns:1fr;}
  .pay-wrap>div:first-child{order:2;}
  .pay-wrap>div:last-child{order:1;}
  .summary-card{position:static !important;}
  .steps-row{padding:10px 12px;}
  .stt{display:none;}
  .qr-area,.form-sec{padding:14px;}
  .phead{padding:12px 14px 10px;}
  .qr-frame{width:min(200px,calc(100vw - 110px));height:min(200px,calc(100vw - 110px));}
  .qr-frame img{width:calc(100% - 20px);height:calc(100% - 20px);}
  .term-tabs{flex-wrap:nowrap;}
  .ttab{flex:0 0 auto;min-width:80px;font-size:.7rem;padding:7px 8px;}
  .fr{grid-template-columns:1fr;gap:0;}
  .aba{font-size:1.2rem;}
  .app-btn{padding:10px 13px;font-size:.78rem;min-width:78px;}
  .page-head h1{font-size:1.05rem;}
  .wrap{padding:16px 12px 80px;}
  .topbar{padding:10px 14px;}
}
@media(max-width:380px){
  .app-btn{padding:9px 10px;font-size:.74rem;min-width:68px;}
  .aba{font-size:1.1rem;}
  .sub-btn{font-size:.85rem;padding:12px 14px;}
}
</style>
</head>
<body>

<nav class="topbar">
  <div class="logo">
    <div class="logo-icon"><i class="fas fa-layer-group"></i></div>
    <div>
      <div class="logo-text">CourseFlow</div>
      <span class="logo-sub">A Technology Private Limited</span>
    </div>
  </div>
  <a href="../student/dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
</nav>

<div class="wrap">
  <div class="page-head">
    <h1><span class="ph-icon"><i class="fas fa-shield-alt"></i></span> Secure Internship Payment</h1>
    <p>CourseFlow &mdash; PhonePe / UPI &bull; Secure &amp; Instant</p>
  </div>

<?php if ($existing && $ep_status==='pending'): ?>
  <div class="sb pending"><div class="sbi"><i class="fas fa-hourglass-half"></i></div><div><h4>Payment Under Review</h4><p>Submitted successfully. Confirmation will be sent to <strong><?php echo htmlspecialchars($reg['mobile_number']); ?></strong> and your email within 24 hours.</p></div></div>
  <div class="cs">
    <div class="csico" style="background:#fef3c7;color:#d97706"><i class="fas fa-hourglass-half"></i></div>
    <h2 style="color:#1e293b;margin:0 0 10px;font-size:1.1rem;">Awaiting Admin Approval</h2>
    <p style="color:#64748b;max-width:360px;margin:0 auto;font-size:.85rem;line-height:1.6">Our team verifies payments manually. Once approved, internship details will be sent to your email and WhatsApp.</p>
    <div class="action-row"><a href="../student/dashboard.php" class="btn-a btn-outline"><i class="fas fa-home"></i> Go Home</a></div>
  </div>

<?php elseif ($existing && $ep_status==='approved'): ?>
  <div class="sb approved"><div class="sbi"><i class="fas fa-check-circle"></i></div><div><h4>Payment Approved &amp; Enrolled!</h4><p>Internship details emailed to <strong><?php echo htmlspecialchars($reg['email']); ?></strong>.</p></div></div>
  <div class="cs">
    <div class="csico" style="background:#dcfce7;color:#16a34a"><i class="fas fa-graduation-cap"></i></div>
    <h2 style="color:#1e293b;margin:0 0 10px;font-size:1.1rem;">You're Enrolled! 🎉</h2>
    <p style="color:#64748b;max-width:360px;margin:0 auto;font-size:.85rem;line-height:1.6">Welcome to <strong><?php echo htmlspecialchars($reg['internship_chosen']); ?></strong>. Check your email for details &amp; onboarding guide.</p>
  </div>

<?php elseif ($show_form): ?>

<?php if ($existing && $ep_status==='payment_later'): ?>
  <div class="sb" style="background:#eff6ff;border:1.5px solid #bfdbfe;"><div class="sbi" style="background:#dbeafe;color:#1d4ed8;"><i class="fas fa-clock"></i></div><div><h4 style="color:#1e3a8a;">Payment Outstanding</h4><p>You chose to pay later. Complete your payment below to confirm your internship seat.</p></div></div>
<?php elseif ($existing && $ep_status==='rejected'): ?>
  <div class="sb rejected"><div class="sbi"><i class="fas fa-times-circle"></i></div><div><h4>Previous Payment Rejected</h4><p><?php echo htmlspecialchars($existing['admin_note']??'Please re-submit with the correct UTR number.'); ?></p></div></div>
<?php endif; ?>

  <div class="steps-panel">
    <div class="steps-row">
      <div class="sti"><div class="stn active" id="sn1">1</div><div class="stt active" id="st1">Scan &amp; Pay</div></div>
      <div class="stl" id="sl1"></div>
      <div class="sti"><div class="stn inactive" id="sn2">2</div><div class="stt" id="st2">Enter UTR</div></div>
      <div class="stl" id="sl2"></div>
      <div class="sti"><div class="stn inactive" id="sn3">3</div><div class="stt" id="st3">Submit</div></div>
    </div>
  </div>

  <div class="pay-wrap">

    <!-- Summary -->
    <div>
      <div class="summary-card">
        <div class="s-head">
          <div class="av"><?php echo strtoupper($reg['full_name'][0]); ?></div>
          <h3><?php echo htmlspecialchars($reg['full_name']); ?></h3>
          <p><?php echo htmlspecialchars($reg['email']); ?></p>
        </div>
        <div class="s-body">
          <div class="srow"><div class="sico"><i class="fas fa-briefcase"></i></div><div><div class="slbl">Internship</div><div class="sval"><?php echo htmlspecialchars($reg['internship_chosen']); ?></div></div></div>
          <div class="srow"><div class="sico"><i class="fas fa-university"></i></div><div><div class="slbl">College</div><div class="sval"><?php echo htmlspecialchars($reg['college_name']); ?></div></div></div>
          <div class="srow"><div class="sico"><i class="fas fa-id-card"></i></div><div><div class="slbl">Reg. Number</div><div class="sval"><?php echo htmlspecialchars($reg['registration_number']); ?></div></div></div>
          <div class="srow"><div class="sico"><i class="fas fa-phone"></i></div><div><div class="slbl">Mobile</div><div class="sval"><?php echo htmlspecialchars($reg['mobile_number']); ?></div></div></div>
          <div class="fee-box">
            <div style="font-size:.65rem;font-weight:700;color:#1e40af;margin-bottom:7px;text-transform:uppercase;letter-spacing:.04em;"><i class="fas fa-file-invoice"></i> Fee Breakdown</div>
            <div class="frow"><span class="flbl">Base Fee</span><span class="fval">&#8377;<?php echo number_format(INTERNSHIP_FEE_BASE,2); ?></span></div>
            <div class="frow"><span class="flbl">GST (<?php echo GST_RATE; ?>%)</span><span class="fval" style="color:#d97706;">+&#8377;<?php echo number_format($gst_amount,2); ?></span></div>
            <div class="frow dv"><span class="flbl" style="font-weight:700">Total Payable</span><span class="fval blue">&#8377;<?php echo number_format($fee,2); ?></span></div>
          </div>
          <div class="srow" style="padding-top:8px"><div class="sico" style="background:#fef3c7;color:#d97706"><i class="fas fa-clock"></i></div><div><div class="slbl">Approval Time</div><div class="sval" style="font-size:.8rem">Within 24 hours</div></div></div>
        </div>
      </div>
    </div>

    <!-- Main Form -->
    <div>

      <!-- Panel 1: QR -->
      <div class="pay-panel">
        <div class="phead">
          <div class="phnum">1</div>
          <div><h3>Scan &amp; Pay via PhonePe / UPI</h3><p>&#8377;<?php echo number_format($fee,2); ?> pre-filled — just tap your app &amp; confirm</p></div>
        </div>
        <div class="qr-area">
          <div class="term-tabs" id="mTabs">
            <?php foreach($terminals as $i=>$t): ?>
            <button type="button" class="ttab<?php echo $i===0?' active':''; ?>" onclick="switchTerm(<?php echo $i; ?>)">
              <i class="fas fa-store"></i> <?php echo htmlspecialchars($t['name']); ?>
            </button>
            <?php endforeach; ?>
          </div>

          <div class="qr-box">
            <div class="qr-frame">
              <div class="qr-spinner" id="mSpin"><i class="fas fa-spinner fa-spin"></i><span>Loading QR...</span></div>
              <img id="mQrImg"
                   src="<?php echo htmlspecialchars(qrUrl($terminals[0]['upi'],$fee)); ?>"
                   alt="UPI QR" style="display:none"
                   onload="this.style.display='block';document.getElementById('mSpin').style.display='none';this.classList.add('qr-fade')"
                   onerror="document.getElementById('mSpin').innerHTML='<i class=\'fas fa-qrcode\' style=\'color:#3b82f6;font-size:2rem\'></i><span style=\'font-size:.75rem\'>Use buttons below</span>'">
            </div>
            <div class="qr-amt-badge">&#8377;<?php echo number_format($fee,2); ?> <span class="qr-pre">pre-filled</span></div>
            <div class="qr-tname" id="mTName"><i class="fas fa-store" style="color:#3b82f6"></i> <?php echo htmlspecialchars($terminals[0]['name']); ?></div>
            <div class="qr-upi" id="mUpiId"><?php echo htmlspecialchars($terminals[0]['upi']); ?></div>

            <!--
              App buttons — each uses its own registered URI scheme:
              PhonePe  → phonepe://
              GPay     → tez://
              Paytm    → paytmmp://
              Any UPI  → upi://  (shows OS app picker)
            -->
            <span class="app-lbl">Tap to open directly in your payment app:</span>
            <div class="app-row">
              <button type="button" class="app-btn pp" onclick="launchApp('phonepe')">
                <i class="fas fa-mobile-alt"></i> PhonePe
              </button>
              <button type="button" class="app-btn gp" onclick="launchApp('gpay')">
                <i class="fas fa-google"></i> GPay
              </button>
              <button type="button" class="app-btn pt" onclick="launchApp('paytm')">
                <i class="fas fa-wallet"></i> Paytm
              </button>
              <button type="button" class="app-btn up" onclick="launchApp('any')">
                <i class="fas fa-th"></i> Any UPI
              </button>
            </div>
          </div>

          <!-- Shows after returning from app -->
          <div class="utr-return-banner" id="utrBanner">
            <i class="fas fa-check-circle"></i>
            <div id="utrBannerMsg">
              <strong>Returned from payment app!</strong>
              UTR has been auto-filled below — please verify and submit.
            </div>
          </div>

          <div class="pay-note">
            <i class="fas fa-info-circle"></i>
            <p>Tap an app button above &rarr; complete payment &rarr; you'll return here automatically with <strong>UTR pre-filled</strong>. Or scan the QR code with any UPI app.</p>
          </div>
        </div>
      </div>

      <!-- Panel 2: Form -->
      <div class="pay-panel">
        <div class="phead">
          <div class="phnum">2</div>
          <div><h3>Confirm Payment Details</h3><p>Verify UTR and submit to complete enrollment</p></div>
        </div>
        <div class="form-sec">
          <form action="submit_internship_payment.php" method="POST" id="payForm" novalidate>
            <input type="hidden" name="reg_id"        value="<?php echo $reg_id; ?>">
            <input type="hidden" name="amount"        value="<?php echo $fee; ?>">
            <input type="hidden" name="terminal_name" id="tNameH" value="<?php echo htmlspecialchars($terminals[0]['name']); ?>">
            <input type="hidden" name="terminal_upi"  id="tUpiH"  value="<?php echo htmlspecialchars($terminals[0]['upi']); ?>">

            <div class="fr">
              <div class="fg"><label class="fl">Student Name</label><input class="fi" type="text" value="<?php echo htmlspecialchars($reg['full_name']); ?>" readonly></div>
              <div class="fg"><label class="fl">Email</label><input class="fi" type="text" value="<?php echo htmlspecialchars($reg['email']); ?>" readonly></div>
            </div>
            <div class="fr">
              <div class="fg"><label class="fl">Internship</label><input class="fi" type="text" value="<?php echo htmlspecialchars($reg['internship_chosen']); ?>" readonly></div>
              <div class="fg"><label class="fl">Mobile</label><input class="fi" type="text" value="<?php echo htmlspecialchars($reg['mobile_number']); ?>" readonly></div>
            </div>

            <div class="amt-ban">
              <div>
                <div class="abl">Internship Fee</div>
                <div class="aba">&#8377;<?php echo number_format($fee,2); ?></div>
                <div class="abs">Base &#8377;<?php echo number_format(INTERNSHIP_FEE_BASE,2); ?> + GST (<?php echo GST_RATE; ?>%) &#8377;<?php echo number_format($gst_amount,2); ?></div>
              </div>
              <i class="fas fa-lock" style="color:rgba(255,255,255,.4);font-size:1.1rem"></i>
            </div>

            <div class="fg">
              <label class="fl">UTR / Transaction ID <span>*</span></label>
              <input class="fi hi" type="text" name="utr_number" id="utrI"
                     placeholder="e.g. 512345678901" required minlength="6" maxlength="50"
                     autocomplete="off" spellcheck="false" inputmode="text"
                     value="<?php echo htmlspecialchars($prefill_utr); ?>"
                     oninput="onUtr()">
              <div class="uhint" id="utrHint">
                <i class="fas fa-info-circle"></i>
                <span id="utrHintTxt">Find this in your UPI app's transaction history after payment</span>
              </div>
            </div>

            <div class="warn-box">
              <i class="fas fa-exclamation-triangle"></i>
              <p>Ensure the UTR is exactly correct. Incorrect details delay enrollment. Payments are reviewed manually within 24 hours.</p>
            </div>

            <button type="submit" class="sub-btn" id="subBtn">
              <i class="fas fa-paper-plane"></i>
              <span id="subTxt">Submit Payment for Approval</span>
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>
<?php endif; ?>
</div>

<!-- Popups -->
<div class="popup-overlay" id="popInvalidUtr">
  <div class="popup-box"><div class="popup-icon-wrap">
    <div class="popup-icon error"><i class="fas fa-times-circle"></i></div>
    <div class="popup-title">Invalid Transaction ID</div>
    <p class="popup-msg">The UTR is <strong>not valid</strong>. A valid UTR is <strong>6–50 alphanumeric characters</strong> (no spaces or symbols). Copy it directly from your UPI app's transaction history.</p>
  </div><div class="popup-footer"><button class="popup-btn primary" onclick="closePopup('popInvalidUtr')"><i class="fas fa-edit"></i> Fix UTR &amp; Retry</button></div></div>
</div>

<div class="popup-overlay" id="popDuplicateUtr">
  <div class="popup-box"><div class="popup-icon-wrap">
    <div class="popup-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="popup-title">UTR Already Submitted</div>
    <p class="popup-msg">This UTR has already been used. Please check your UPI app and enter the <strong>correct UTR</strong> for this payment.</p>
  </div><div class="popup-footer"><button class="popup-btn danger" onclick="closePopup('popDuplicateUtr')"><i class="fas fa-edit"></i> Enter Correct UTR</button></div></div>
</div>

<div class="popup-overlay" id="popDbError">
  <div class="popup-box"><div class="popup-icon-wrap">
    <div class="popup-icon error"><i class="fas fa-server"></i></div>
    <div class="popup-title">Submission Failed</div>
    <p class="popup-msg">Something went wrong. Please try again. If the issue persists, contact support with your UTR number.</p>
  </div><div class="popup-footer"><button class="popup-btn primary" onclick="closePopup('popDbError')"><i class="fas fa-redo"></i> Try Again</button></div></div>
</div>

<div class="popup-overlay" id="popAppMissing">
  <div class="popup-box"><div class="popup-icon-wrap">
    <div class="popup-icon warning"><i class="fas fa-mobile-alt"></i></div>
    <div class="popup-title" id="popAppTitle">App Not Found</div>
    <p class="popup-msg" id="popAppMsg">The app doesn't appear to be installed. Tap "Any UPI" to pick from all installed apps, or scan the QR code.</p>
  </div><div class="popup-footer">
    <button class="popup-btn primary" onclick="closePopup('popAppMissing');launchApp('any')"><i class="fas fa-th"></i> Any UPI</button>
    <button class="popup-btn danger" onclick="closePopup('popAppMissing')">Cancel</button>
  </div></div>
</div>

<script>
var TERMINALS = <?php echo json_encode(array_map(function($t){return['name'=>$t['name'],'upi'=>$t['upi']];},$terminals)); ?>;
var FEE       = <?php echo $fee; ?>;
var REG_ID    = <?php echo $reg_id; ?>;
var curTerm   = 0;

/* Current page URL (clean, used as UPI callback) */
var CALLBACK_BASE = (function(){
    var u = window.location.href.split('?')[0];
    return u + '?reg_id=' + REG_ID;
})();

/*
 * ── buildLink(app, upi) ──────────────────────────────────────────
 * Each UPI app has its own registered URI scheme.
 * We also append &url= so the app can callback with txnId.
 *
 *  App       Scheme          Notes
 *  ──────    ─────────────   ──────────────────────────────────────
 *  PhonePe   phonepe://pay   Official deep-link supported by PhonePe
 *  GPay      tez://upi/pay   Google Pay's registered scheme (tez)
 *  Paytm     paytmmp://pay   Paytm Money/Payments scheme
 *  Any       upi://pay       Standard; Android shows app-chooser
 * ────────────────────────────────────────────────────────────────
 */
function buildLink(app, upi) {
    var q = 'pa='  + encodeURIComponent(upi)
          + '&pn=' + encodeURIComponent('CourseFlow')
          + '&am=' + FEE.toFixed(2)
          + '&cu=INR'
          + '&tn=' + encodeURIComponent('Internship Fee')
          + '&mode=02&purpose=00'
          /* callback URL — app appends txnId on return */
          + '&url=' + encodeURIComponent(CALLBACK_BASE + '&txnId=');

    if (app === 'phonepe') return 'phonepe://pay?' + q;
    if (app === 'gpay')    return 'tez://upi/pay?' + q;
    if (app === 'paytm')   return 'paytmmp://pay?' + q;
    return 'upi://pay?' + q;   /* 'any' — OS shows picker */
}

/*
 * launchApp(app)
 * Navigates to app-specific scheme. Detects if app is missing
 * (page stays visible after 2 s) and shows fallback popup.
 */
function launchApp(app) {
    var upi  = TERMINALS[curTerm].upi;
    var link = buildLink(app, upi);

    /* Save launch context so we can show banner on return */
    try {
        sessionStorage.setItem('vsupi_launched', '1');
        sessionStorage.setItem('vsupi_time', Date.now().toString());
        sessionStorage.setItem('vsupi_app', app);
    } catch(e){}

    var appNames = {phonepe:'PhonePe', gpay:'Google Pay', paytm:'Paytm'};

    /* Watch visibility — if page hides, the app opened */
    var opened = false;
    function onHide(){ if(document.hidden){ opened=true; } }
    document.addEventListener('visibilitychange', onHide);

    window.location.href = link;

    /* After 2 s, if still visible and not 'any', app is probably missing */
    setTimeout(function(){
        document.removeEventListener('visibilitychange', onHide);
        if (!opened && app !== 'any') {
            document.getElementById('popAppTitle').textContent = (appNames[app]||'App') + ' Not Found';
            document.getElementById('popAppMsg').textContent   =
                (appNames[app]||'The app') + ' doesn\'t appear to be installed on this device. '
                + 'Use "Any UPI" to pick from installed apps, or scan the QR code above.';
            showPopup('popAppMissing');
        }
    }, 2000);
}

/* ── QR helpers ── */
function qrSrc(upi){
    var d='upi://pay?pa='+encodeURIComponent(upi)+'&pn=CourseFlow%20AI%20Technologies&am='+FEE.toFixed(2)+'&cu=INR&tn=Internship%20Fee';
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='+encodeURIComponent(d)+'&format=png&ecc=M&margin=4&t='+Date.now();
}
function swapQR(upi){
    var img=document.getElementById('mQrImg'),spin=document.getElementById('mSpin');
    img.style.display='none'; spin.style.display='flex';
    spin.innerHTML='<i class="fas fa-spinner fa-spin"></i><span>Loading QR...</span>';
    img.onload=function(){spin.style.display='none';img.style.display='block';img.classList.remove('qr-fade');void img.offsetWidth;img.classList.add('qr-fade');};
    img.onerror=function(){spin.innerHTML='<i class="fas fa-qrcode" style="color:#3b82f6;font-size:2rem"></i><span style="font-size:.75rem">Use buttons below</span>';};
    img.src=qrSrc(upi);
}
function switchTerm(idx){
    curTerm=idx;
    document.querySelectorAll('#mTabs .ttab').forEach(function(b,i){b.classList.toggle('active',i===idx);});
    var t=TERMINALS[idx];
    swapQR(t.upi);
    document.getElementById('mTName').innerHTML='<i class="fas fa-store" style="color:#3b82f6"></i> '+t.name;
    document.getElementById('mUpiId').textContent=t.upi;
    document.getElementById('tNameH').value=t.name;
    document.getElementById('tUpiH').value=t.upi;
}

/* ── Steps ── */
function step(n,s){
    var nm=document.getElementById('sn'+n),tx=document.getElementById('st'+n),ln=document.getElementById('sl'+n);
    if(!nm)return;
    nm.className='stn '+s;
    nm.innerHTML=(s==='done')?'<i class="fas fa-check" style="font-size:.6rem"></i>':n;
    if(tx)tx.className='stt '+s;
    if(ln)ln.className='stl '+(s==='done'?'done':'');
}
function onUtr(){
    var v=(document.getElementById('utrI').value||'').trim();
    step(1,v.length>=6?'done':'active');
    step(2,v.length>=6?'active':'inactive');
    step(3,'inactive');
}

/* ── Popups ── */
function closePopup(id){var el=document.getElementById(id);if(el)el.classList.remove('show');var b=document.getElementById('subBtn');if(b){b.disabled=false;b.innerHTML='<i class="fas fa-paper-plane"></i><span>Submit Payment for Approval</span>';}}
function showPopup(id){var el=document.getElementById(id);if(el)el.classList.add('show');}
document.querySelectorAll('.popup-overlay').forEach(function(o){o.addEventListener('click',function(e){if(e.target===this)closePopup(this.id);});});

/* ── Server error popups ── */
(function(){
    var err='<?php echo addslashes(htmlspecialchars($page_error)); ?>';
    var map={invalid_utr:'popInvalidUtr',duplicate_utr:'popDuplicateUtr',db_error:'popDbError'};
    if(err&&map[err]){showPopup(map[err]);if(err!=='db_error'){setTimeout(function(){var f=document.getElementById('utrI');if(f){f.focus();f.select();}},300);}}
})();

/* ══════════════════════════════════════════════════
   UTR AUTO-FILL ON RETURN FROM PAYMENT APP
   UPI apps append txnId / txnRef to the callback URL.
   We read those params and fill the UTR field.
   ══════════════════════════════════════════════════ */
(function autoFill(){
    var params  = new URLSearchParams(window.location.search);
    var keys    = ['txnId','txnRef','txnid','txnref','utr','UTR','referenceId','rrn','transactionId'];
    var utr     = '';
    for(var i=0;i<keys.length;i++){ var v=params.get(keys[i]); if(v){utr=v.replace(/[^A-Za-z0-9]/g,'');break;} }

    var status  = (params.get('Status')||params.get('status')||'').toLowerCase();
    var field   = document.getElementById('utrI');
    var banner  = document.getElementById('utrBanner');
    var hint    = document.getElementById('utrHint');
    var hintTxt = document.getElementById('utrHintTxt');

    if(utr && field){
        /* Fill & style the field */
        field.value = utr;
        field.classList.add('autofilled');

        /* Update hint text */
        if(status==='failure'||status==='failed'){
            if(hint)hint.classList.add('warn');
            if(hintTxt)hintTxt.innerHTML='<i class="fas fa-exclamation-triangle"></i> Your app reported a <strong>failed</strong> payment. Please verify before submitting.';
        } else {
            if(hint)hint.classList.add('ok');
            if(hintTxt)hintTxt.textContent='✓ UTR auto-filled from your payment app. Verify and submit.';
        }

        /* Show banner */
        if(banner)banner.classList.add('show');

        /* Advance steps */
        step(1,'done'); step(2,'active');

        /* Scroll to form */
        setTimeout(function(){field.scrollIntoView({behavior:'smooth',block:'center'});},500);

        /* Clean URL */
        if(window.history&&window.history.replaceState){
            keys.concat(['Status','status']).forEach(function(k){params.delete(k);});
            window.history.replaceState({},'',window.location.pathname+'?'+params.toString());
        }

    } else {
        /* App was launched but returned without UTR */
        try {
            var launched = sessionStorage.getItem('vsupi_launched');
            var t        = parseInt(sessionStorage.getItem('vsupi_time')||'0');
            if(launched && (Date.now()-t)>3000 && banner){
                document.getElementById('utrBannerMsg').innerHTML =
                    '<strong>Returned from payment app.</strong> '
                    +'If payment succeeded, find your <strong>UTR / Transaction ID</strong> '
                    +'in the app\'s transaction history and enter it below.';
                banner.classList.add('show');
            }
        } catch(e){}
    }

    /* Clear session flag after processing */
    try{ sessionStorage.removeItem('vsupi_launched'); }catch(e){}
})();

/* If UTR was pre-filled by PHP (server-side from URL), also trigger styling */
(function(){
    var f=document.getElementById('utrI');
    if(f && f.value.length>=6){
        f.classList.add('autofilled');
        step(1,'done'); step(2,'active');
    }
})();

/* ── Form submit ── */
var pf=document.getElementById('payForm');
if(pf){pf.addEventListener('submit',function(e){
    var utr=(document.getElementById('utrI').value||'').trim();
    if(!/^[A-Za-z0-9]{6,50}$/.test(utr)){e.preventDefault();showPopup('popInvalidUtr');return;}
    var btn=document.getElementById('subBtn');
    btn.disabled=true;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Submitting...';
    step(1,'done');step(2,'done');step(3,'active');
});}
</script>
</body>
</html>