<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("student");

$user_id = $_SESSION['user_id'];

if (!isset($_GET['course_id']) || !is_numeric($_GET['course_id'])) {
    header("Location: dashboard.php"); exit;
}
$course_id = (int)$_GET['course_id'];

$stmt = $conn->prepare("SELECT name, email, mobile FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

$stmt2 = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt2->bind_param("i", $course_id); $stmt2->execute();
$course = $stmt2->get_result()->fetch_assoc();
if (!$course) { header("Location: dashboard.php"); exit; }


$stmt3 = $conn->prepare("SELECT * FROM payments WHERE user_id = ? AND course_id = ? ORDER BY id DESC LIMIT 1");
$stmt3->bind_param("ii", $user_id, $course_id); $stmt3->execute();
$existing = $stmt3->get_result()->fetch_assoc();

define('GST_RATE', 18.00);
$base_price  = (float)$course['price'];
$gst_amount  = round($base_price * GST_RATE / 100, 2);
$total_price = round($base_price + $gst_amount, 2);
$half_price  = round($total_price / 2, 2);

$ep_status  = $existing['status']         ?? null;
$ep_pstatus = $existing['payment_status'] ?? null;
$show_form   = !$existing || $ep_status === 'rejected';
$show_remain = ($existing && $ep_status === 'approved' && $ep_pstatus === 'partial'
             && (float)($existing['pending_amount'] ?? 0) > 0);
$rem_amt = $show_remain ? (float)$existing['pending_amount'] : 0;

$terminals = [
    ['name' => 'Terminal 1', 'upi' => 'yourupi1@ybl'],
    ['name' => 'Terminal 2', 'upi' => 'yourupi2@ybl'],
    ['name' => 'Terminal 3', 'upi' => 'yourupi3@ybl'],
];

function qrUrl(string $upi, float $amount, string $note = 'Course Fee', int $size = 220): string {
    return 'qr_generate.php?upi=' . urlencode($upi)
         . '&amt=' . number_format($amount, 2, '.', '')
         . '&note=' . urlencode($note)
         . '&size=' . $size;
}

function upiDeepLink(string $upi, float $amount, string $note = 'Course Fee'): string {
    // CRITICAL FIX: Do NOT encode the @ in UPI VPA.
    // rawurlencode() converts @ -> %40 which causes PhonePe/GPay to show
    // "Could not initiate transaction". The pa field must keep @ as-is.
    $pa = str_replace(' ', '%20', $upi);           // keep @ intact
    $pn = str_replace(' ', '%20', 'CourseFlow'); // short payee name
    $am = number_format($amount, 2, '.', '');
    $tn = urlencode($note);
    return 'upi://pay?pa=' . $pa . '&pn=' . $pn . '&am=' . $am . '&cu=INR&tn=' . $tn . '&mode=02&purpose=00';
}

$page_title = "Course Payment";

// Capture error from redirect for popup display
$page_error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment — CourseFlow LMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
.pay-wrap{display:grid;grid-template-columns:340px 1fr;gap:22px;align-items:start;}
@media(max-width:900px){.pay-wrap{grid-template-columns:1fr;}}
.summary-card{background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.07);overflow:hidden;border:1px solid rgba(0,0,0,.06);position:sticky;top:84px;}
.s-head{background:linear-gradient(135deg,#1e40af,#3b82f6);padding:26px 22px 20px;color:#fff;position:relative;overflow:hidden;}
.s-head::before{content:'';position:absolute;top:-40px;right:-40px;width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,.08);}
.s-head::after{content:'';position:absolute;bottom:-28px;left:-18px;width:110px;height:110px;border-radius:50%;background:rgba(255,255,255,.05);}
.av{width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;color:#fff;margin-bottom:12px;position:relative;z-index:1;}
.s-head h3{font-size:.98rem;font-weight:700;margin:0 0 2px;position:relative;z-index:1;}
.s-head p{font-size:.8rem;opacity:.8;margin:0;position:relative;z-index:1;}
.s-body{padding:18px 20px;}
.srow{display:flex;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px solid #f1f5f9;}
.srow:last-child{border-bottom:none;}
.sico{width:30px;height:30px;border-radius:9px;background:#eff6ff;color:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;margin-top:2px;}
.slbl{font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;font-weight:600;margin-bottom:1px;}
.sval{font-size:.87rem;font-weight:600;color:#1e293b;}
.gst-box{background:linear-gradient(135deg,#f0f7ff,#f0fdf4);border:1px solid #bfdbfe;border-radius:13px;padding:13px 15px;margin:11px 0 4px;}
.grow{display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:.81rem;}
.grow.dv{border-top:1.5px dashed #bfdbfe;margin-top:5px;padding-top:7px;}
.glbl{color:#64748b;font-weight:500;}.gval{font-weight:700;color:#1e293b;}
.gval.or{color:#d97706;}.gval.bl{color:#1e40af;font-size:.9rem;}.gval.gr{color:#059669;}.gval.rd{color:#dc2626;}
.gbadge{display:inline-flex;background:#fef3c7;color:#92400e;font-size:.62rem;font-weight:700;padding:1px 5px;border-radius:999px;margin-left:3px;}
.plan-sec{padding:16px 20px;border-bottom:1px solid #f1f5f9;}
.plan-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
@media(max-width:520px){.plan-grid{grid-template-columns:1fr;}}

/*
 * PLAN CARD — radio fills the entire card behind all content.
 * Clicking anywhere on the card triggers the radio's change event.
 * z-index:0 keeps the radio behind text/icons (z-index:1).
 */
.plan-card{
    border:2px solid #e2e8f0;border-radius:13px;padding:13px 11px;
    cursor:pointer;transition:all .2s;position:relative;
    background:#f8fafc;text-align:center;user-select:none;
}
.plan-card:hover{border-color:#3b82f6;background:#eff6ff;}
.plan-card.sel{border-color:#1e40af;background:linear-gradient(135deg,#eff6ff,#f0f9ff);box-shadow:0 0 0 3px rgba(59,130,246,.12);}
.plan-card input[type="radio"]{
    position:absolute;inset:0;width:100%;height:100%;
    opacity:0;margin:0;cursor:pointer;z-index:0;
}
.plan-card > *:not(input){position:relative;z-index:1;}

.pico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.95rem;margin:0 auto 7px;}
.full-c .pico{background:#dcfce7;color:#16a34a;}.half-c .pico{background:#fef3c7;color:#d97706;}
.ptitle{font-size:.8rem;font-weight:700;color:#1e293b;margin-bottom:2px;}
.pamt{font-size:1.02rem;font-weight:800;color:#1e40af;}
.psub{font-size:.69rem;color:#64748b;margin-top:2px;}
.prec{position:absolute;top:-9px;right:8px;background:#16a34a;color:#fff;font-size:.59rem;font-weight:800;padding:2px 8px;border-radius:999px;z-index:2;}
.emi-note{display:none;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:9px 12px;margin-top:9px;font-size:.78rem;color:#92400e;align-items:flex-start;gap:6px;}
.emi-note.show{display:flex;}.emi-note i{flex-shrink:0;margin-top:2px;color:#d97706;}
.pay-panel{background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.07);border:1px solid rgba(0,0,0,.06);overflow:hidden;margin-bottom:18px;}
.phead{padding:16px 22px 13px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:10px;}
.phnum{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;font-weight:800;font-size:.8rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.phead h3{font-size:.93rem;font-weight:700;margin:0;color:#1e293b;}
.phead p{font-size:.77rem;color:#94a3b8;margin:2px 0 0;}
.qr-area{padding:18px 22px;border-bottom:1px solid #f1f5f9;}
.term-tabs{display:flex;gap:7px;margin-bottom:14px;}
.ttab{flex:1;padding:8px 6px;border:2px solid #e2e8f0;border-radius:10px;background:#f8fafc;font-size:.76rem;font-weight:700;color:#64748b;cursor:pointer;transition:all .18s;text-align:center;font-family:inherit;}
.ttab:hover{border-color:#5a31f4;color:#5a31f4;}
.ttab.active{border-color:#5a31f4;background:linear-gradient(135deg,#5a31f4,#7c3aed);color:#fff;box-shadow:0 3px 8px rgba(90,49,244,.22);}
.qr-box{background:#f8fafc;border:2px solid #e2e8f0;border-radius:15px;padding:18px;text-align:center;}
.qr-frame{width:224px;height:224px;margin:0 auto 12px;background:#fff;border-radius:13px;border:1px solid #e2e8f0;box-shadow:0 4px 18px rgba(0,0,0,.08);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;}
.qr-frame img{width:200px;height:200px;display:block;object-fit:contain;}
.qr-spinner{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#fff;gap:6px;color:#94a3b8;font-size:.78rem;}
.qr-spinner i{font-size:1.8rem;color:#5a31f4;}
.qr-amt-badge{display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;padding:5px 14px;border-radius:999px;font-size:.97rem;font-weight:800;margin-bottom:8px;}
.qr-pre{font-size:.63rem;opacity:.75;background:rgba(255,255,255,.2);padding:1px 5px;border-radius:999px;}
.qr-tname{font-size:.8rem;font-weight:700;color:#1e293b;margin-bottom:2px;}
.qr-upi{font-size:.73rem;font-family:monospace;color:#5a31f4;background:#f5f3ff;display:inline-block;padding:3px 9px;border-radius:999px;border:1px solid #ede9fe;margin-bottom:10px;}
.app-row{display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;}
.app-lbl{font-size:.68rem;color:#94a3b8;font-weight:600;text-transform:uppercase;}
.app-btn{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border-radius:8px;font-size:.74rem;font-weight:700;text-decoration:none;transition:all .18s;}
.app-btn.pp{background:#5a31f4;color:#fff;}.app-btn.pp:hover{background:#4c27d6;transform:translateY(-1px);}
.app-btn.gp{background:#4285f4;color:#fff;}.app-btn.gp:hover{background:#3367d6;transform:translateY(-1px);}
.app-btn.up{background:#f97316;color:#fff;}.app-btn.up:hover{background:#ea6c0a;transform:translateY(-1px);}
.pay-note{display:flex;align-items:flex-start;gap:9px;background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1px solid #bfdbfe;border-radius:10px;padding:11px 13px;margin-top:13px;}
.pay-note i{color:#3b82f6;font-size:.92rem;margin-top:2px;flex-shrink:0;}
.pay-note p{margin:0;font-size:.79rem;color:#1e40af;line-height:1.5;}
.amt-ban{background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:12px;padding:13px 17px;margin-bottom:15px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.abl{color:rgba(255,255,255,.8);font-size:.73rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;}
.aba{color:#fff;font-size:1.45rem;font-weight:800;}
.abs{color:rgba(255,255,255,.7);font-size:.71rem;margin-top:1px;}
.form-sec{padding:18px 22px;}
.fg{margin-bottom:15px;}
.fl{display:block;font-size:.74rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;}
.fl span{color:#ef4444;margin-left:2px;}
.fi{width:100%;padding:10px 12px;border:2px solid #e2e8f0;border-radius:10px;font-size:.87rem;color:#1e293b;background:#f8fafc;transition:all .2s;outline:none;box-sizing:border-box;font-family:inherit;}
.fi:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 4px rgba(59,130,246,.1);}
.fi[readonly]{background:#f1f5f9;color:#64748b;cursor:not-allowed;}
.fi.hi{border-color:#3b82f6;background:#fff;font-size:.9rem;font-weight:600;}
.fr{display:grid;grid-template-columns:1fr 1fr;gap:11px;}
@media(max-width:600px){.fr{grid-template-columns:1fr;}}

/* ── Mobile responsive fixes ── */
@media(max-width:600px){
    /* Summary card: don't sticky-scroll on mobile */
    .summary-card { position:static; }

    /* Panels: reduce inner padding */
    .phead    { padding:12px 15px 10px; }
    .qr-area  { padding:14px 15px; }
    .form-sec { padding:14px 15px; }
    .plan-sec { padding:12px 15px; }

    /* Terminal tabs: all 3 must be visible — hide icon, shrink text */
    .term-tabs { gap:5px; }
    .ttab {
        min-width:0;
        font-size:.65rem;
        padding:7px 4px;
    }
    .ttab i { display:none; }

    /* QR frame: responsive instead of fixed 224px */
    .qr-frame {
        width:100%; max-width:210px; height:auto;
        aspect-ratio:1/1; margin:0 auto 12px;
    }
    .qr-frame img { width:100%; max-width:190px; height:auto; }

    /* Amount banner */
    .amt-ban { padding:11px 13px; }
    .aba     { font-size:1.2rem; }

    /* App buttons */
    .app-row { gap:5px; }
    .app-btn { font-size:.7rem; padding:5px 9px; }

    /* Plan cards */
    .plan-grid { gap:8px; }
}
@media(max-width:380px){
    .ttab         { font-size:.6rem; padding:6px 3px; }
    .qr-amt-badge { font-size:.82rem; padding:4px 10px; }
    .aba          { font-size:1.1rem; }
}
.uhint{font-size:.71rem;color:#94a3b8;margin-top:4px;display:flex;align-items:center;gap:3px;}
.warn{display:flex;align-items:flex-start;gap:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 13px;margin:0 0 17px;}
.warn i{color:#d97706;font-size:.88rem;margin-top:2px;flex-shrink:0;}.warn p{margin:0;font-size:.78rem;color:#92400e;line-height:1.5;}
.sub-btn{width:100%;padding:13px 22px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;border:none;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;transition:all .22s;box-shadow:0 4px 14px rgba(59,130,246,.33);font-family:inherit;}
.sub-btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(59,130,246,.43);}
.sub-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.sb{display:flex;align-items:flex-start;gap:13px;border-radius:15px;padding:17px 20px;margin-bottom:20px;}
.sb.pending{background:#fffbeb;border:1.5px solid #fde68a;}.sb.partial{background:#eff6ff;border:1.5px solid #bfdbfe;}
.sb.approved{background:#f0fdf4;border:1.5px solid #bbf7d0;}.sb.rejected{background:#fff1f2;border:1.5px solid #fecdd3;}
.sbi{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;}
.pending .sbi{background:#fef3c7;color:#d97706;}.partial .sbi{background:#dbeafe;color:#1e40af;}
.approved .sbi{background:#dcfce7;color:#16a34a;}.rejected .sbi{background:#ffe4e6;color:#e11d48;}
.sb h4{margin:0 0 3px;font-size:.9rem;font-weight:700;}
.pending h4{color:#92400e;}.partial h4{color:#1e40af;}.approved h4{color:#14532d;}.rejected h4{color:#881337;}
.sb p{margin:0;font-size:.81rem;opacity:.85;}
.cs{text-align:center;padding:50px 22px;}
.csico{width:74px;height:74px;border-radius:20px;display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:16px;}
.steps-row{display:flex;align-items:center;padding:14px 20px;}
.sti{display:flex;align-items:center;gap:7px;flex:1;}
.sti:last-child .stl{display:none;}
.stn{width:25px;height:25px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;flex-shrink:0;transition:all .3s;}
.stn.active{background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;box-shadow:0 2px 8px rgba(59,130,246,.3);}
.stn.done{background:linear-gradient(135deg,#059669,#10b981);color:#fff;}
.stn.inactive{background:#f1f5f9;color:#94a3b8;}
.stt{font-size:.69rem;font-weight:600;color:#64748b;white-space:nowrap;}
.stt.active{color:#1e40af;}.stt.done{color:#059669;}
.stl{flex:1;height:2px;background:#e2e8f0;border-radius:1px;margin:0 6px;}
.stl.done{background:linear-gradient(90deg,#059669,#10b981);}
@keyframes qrFade{from{opacity:0}to{opacity:1}}
.qr-fade{animation:qrFade .3s ease;}

/* ── Error / Alert Popups ── */
.popup-overlay{
    display:none;position:fixed;inset:0;z-index:9999;
    background:rgba(0,0,0,.55);backdrop-filter:blur(4px);
    align-items:center;justify-content:center;padding:20px;
}
.popup-overlay.show{display:flex;animation:ovIn .2s ease;}
@keyframes ovIn{from{opacity:0}to{opacity:1}}
.popup-box{
    background:#fff;border-radius:20px;width:100%;max-width:400px;
    box-shadow:0 24px 60px rgba(0,0,0,.22);overflow:hidden;
    animation:popIn .25s cubic-bezier(.34,1.56,.64,1);
}
@keyframes popIn{from{transform:scale(.88);opacity:0}to{transform:scale(1);opacity:1}}
.popup-icon-wrap{
    padding:30px 24px 18px;text-align:center;
}
.popup-icon{
    width:72px;height:72px;border-radius:50%;display:inline-flex;
    align-items:center;justify-content:center;font-size:2rem;margin-bottom:14px;
}
.popup-icon.error  {background:#fff1f2;color:#e11d48;}
.popup-icon.warning{background:#fffbeb;color:#d97706;}
.popup-icon.info   {background:#eff6ff;color:#1e40af;}
.popup-title{font-size:1.1rem;font-weight:800;color:#1e293b;margin:0 0 6px;}
.popup-msg  {font-size:.85rem;color:#64748b;line-height:1.6;margin:0;}
.popup-footer{
    padding:16px 24px 22px;display:flex;gap:10px;justify-content:center;
}
.popup-btn{
    padding:10px 28px;border-radius:10px;font-size:.88rem;font-weight:700;
    border:none;cursor:pointer;transition:all .18s;font-family:inherit;
}
.popup-btn.primary{background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;box-shadow:0 4px 12px rgba(59,130,246,.3);}
.popup-btn.primary:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(59,130,246,.4);}
.popup-btn.danger {background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;box-shadow:0 4px 12px rgba(239,68,68,.3);}
.popup-btn.danger:hover{transform:translateY(-1px);}
.popup-btn.secondary{background:#f1f5f9;color:#475569;}
.popup-btn.secondary:hover{background:#e2e8f0;}
</style>
</head>
<body>
<div class="dashboard">
<?php include("../includes/sidebar.php"); ?>
<div class="main">
<?php include("../includes/topbar.php"); ?>
<div class="content-wrapper">

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;color:#1e293b;margin:0 0 3px;display:flex;align-items:center;gap:10px">
            <span style="width:35px;height:35px;border-radius:10px;background:linear-gradient(135deg,#1e40af,#3b82f6);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem"><i class="fas fa-shield-alt"></i></span>
            Secure Course Payment
        </h1>
        <p style="margin:0;color:#64748b;font-size:.84rem">CourseFlow &mdash; PhonePe / UPI &bull; GST @ <?php echo GST_RATE; ?>%</p>
    </div>
    <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border:2px solid #e2e8f0;border-radius:10px;font-size:.82rem;font-weight:600;color:#64748b;text-decoration:none;background:#fff;transition:all .2s" onmouseover="this.style.borderColor='#3b82f6';this.style.color='#3b82f6'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<?php if ($existing && $ep_status === 'pending'): ?>
<div class="sb pending"><div class="sbi"><i class="fas fa-hourglass-half"></i></div><div><h4>Payment Under Review</h4><p>Submitted and being reviewed — usually verified within 24 hours.</p></div></div>
<div class="cs">
    <div class="csico" style="background:#fef3c7;color:#d97706"><i class="fas fa-hourglass-half"></i></div>
    <h2 style="color:#1e293b;margin:0 0 8px">Awaiting Admin Approval</h2>
    <p style="color:#64748b;max-width:360px;margin:0 auto 22px;font-size:.87rem">Our team will review your payment shortly.</p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a href="payment_status.php" style="display:inline-flex;align-items:center;gap:7px;padding:10px 20px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;border-radius:10px;font-weight:700;text-decoration:none;font-size:.86rem"><i class="fas fa-receipt"></i> Payment Status</a>
        <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border:2px solid #e2e8f0;border-radius:10px;font-weight:600;text-decoration:none;color:#64748b;font-size:.86rem"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</div>

<?php elseif ($existing && $ep_status === 'approved' && $ep_pstatus === 'paid'): ?>
<div class="sb approved"><div class="sbi"><i class="fas fa-check-circle"></i></div><div><h4>Fully Paid &amp; Enrolled</h4><p>Already enrolled in <strong><?php echo htmlspecialchars($course['title']); ?></strong>.</p></div></div>
<div class="cs">
    <div class="csico" style="background:#dcfce7;color:#16a34a"><i class="fas fa-graduation-cap"></i></div>
    <h2 style="color:#1e293b;margin:0 0 8px">You're Enrolled!</h2>
    <a href="course_lessons.php?course_id=<?php echo (int)$course_id; ?>" style="display:inline-flex;align-items:center;gap:7px;padding:11px 24px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border-radius:10px;font-weight:700;text-decoration:none"><i class="fas fa-play"></i> Start Learning</a>
</div>

<?php elseif ($show_remain): ?>
<div class="sb partial"><div class="sbi"><i class="fas fa-clock"></i></div><div><h4>First Installment Approved — Balance Due</h4><p>Pay the remaining <strong>&#8377;<?php echo number_format($rem_amt,2); ?></strong> to complete enrollment.</p></div></div>
<div class="pay-wrap">
<div class="summary-card">
    <div class="s-head">
        <div class="av"><?php echo strtoupper(htmlspecialchars($student['name'][0])); ?></div>
        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
        <p><?php echo htmlspecialchars($student['email']); ?></p>
    </div>
    <div class="s-body">
        <div class="srow"><div class="sico"><i class="fas fa-book"></i></div><div><div class="slbl">Course</div><div class="sval"><?php echo htmlspecialchars($course['title']); ?></div></div></div>
        <div class="gst-box">
            <div class="grow"><span class="glbl">Total Fee</span><span class="gval bl">&#8377;<?php echo number_format((float)($existing['total_amount'] ?: $total_price),2); ?></span></div>
            <div class="grow"><span class="glbl">Paid (1st)</span><span class="gval gr">&#8377;<?php echo number_format((float)$existing['paid_amount'],2); ?></span></div>
            <div class="grow dv"><span class="glbl" style="color:#dc2626;font-weight:700">Balance Due</span><span class="gval rd">&#8377;<?php echo number_format($rem_amt,2); ?></span></div>
        </div>
    </div>
</div>
<div>
<div class="pay-panel">
    <div class="phead"><div class="phnum">1</div><div><h3>Scan &amp; Pay Remaining Balance</h3><p>&#8377;<?php echo number_format($rem_amt,2); ?> is pre-filled in QR — just scan</p></div></div>
    <div class="qr-area">
        <div class="term-tabs" id="rTabs">
            <?php foreach ($terminals as $i => $t): ?>
            <button type="button" class="ttab<?php echo $i===0?' active':''; ?>" onclick="switchR(<?php echo $i; ?>)">
                <i class="fas fa-store"></i> <?php echo htmlspecialchars($t['name']); ?>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="qr-box">
            <div class="qr-frame">
                <div class="qr-spinner" id="rSpin"><i class="fas fa-spinner fa-spin"></i><span>Loading QR...</span></div>
                <img id="rQrImg" src="<?php echo htmlspecialchars(qrUrl($terminals[0]['upi'], $rem_amt, 'Remaining Fee')); ?>" alt="UPI QR Code" style="display:none"
                     onload="this.style.display='block';document.getElementById('rSpin').style.display='none';this.classList.add('qr-fade')"
                     onerror="document.getElementById('rSpin').innerHTML='<i class=\'fas fa-qrcode\' style=\'color:#5a31f4;font-size:2rem\'></i><br><span style=\'font-size:.75rem\'>Use app buttons below</span>'">
            </div>
            <div class="qr-amt-badge">&#8377;<?php echo number_format($rem_amt,2); ?> <span class="qr-pre">pre-filled</span></div>
            <div class="qr-tname" id="rTName"><i class="fas fa-store" style="color:#5a31f4"></i> <?php echo htmlspecialchars($terminals[0]['name']); ?></div>
            <div class="qr-upi" id="rUpiId"><?php echo htmlspecialchars($terminals[0]['upi']); ?></div>
            <div class="app-row">
                <span class="app-lbl">Open in:</span>
                <a class="app-btn pp" id="rLinkPP" href="<?php echo upiDeepLink($terminals[0]['upi'], $rem_amt, 'Remaining Fee'); ?>"><i class="fas fa-mobile-alt"></i> PhonePe</a>
                <a class="app-btn gp" id="rLinkGP" href="<?php echo upiDeepLink($terminals[0]['upi'], $rem_amt, 'Remaining Fee'); ?>"><i class="fas fa-google"></i> GPay</a>
                <a class="app-btn up" id="rLinkUP" href="<?php echo upiDeepLink($terminals[0]['upi'], $rem_amt, 'Remaining Fee'); ?>"><i class="fas fa-qrcode"></i> Any UPI</a>
            </div>
        </div>
        <div class="pay-note"><i class="fas fa-info-circle"></i><p>Scan QR &rarr; Amount <strong>&#8377;<?php echo number_format($rem_amt,2); ?></strong> pre-filled &rarr; Confirm &rarr; Copy <strong>UTR</strong> &rarr; Submit below.</p></div>
    </div>
</div>
<div class="pay-panel">
    <div class="phead"><div class="phnum">2</div><div><h3>Submit Remaining Payment</h3><p>Enter UTR to complete enrollment</p></div></div>
    <div class="form-sec">
        <form action="submit_payment.php" method="POST">
            <input type="hidden" name="course_id" value="<?php echo (int)$course_id; ?>">
            <input type="hidden" name="payment_mode" value="remaining">
            <input type="hidden" name="parent_payment_id" value="<?php echo (int)$existing['id']; ?>">
            <input type="hidden" name="amount" value="<?php echo $rem_amt; ?>">
            <div class="amt-ban"><div><div class="abl">Balance to Pay</div><div class="aba">&#8377;<?php echo number_format($rem_amt,2); ?></div><div class="abs">Final installment</div></div><i class="fas fa-lock" style="color:rgba(255,255,255,.4);font-size:1.2rem"></i></div>
            <div class="fg"><label class="fl">UTR / Transaction ID <span>*</span></label>
                <input class="fi hi" type="text" name="utr_number" placeholder="e.g. 512345678901" required minlength="6" maxlength="50" autocomplete="off">
                <div class="uhint"><i class="fas fa-info-circle"></i> Find this in your UPI app after payment</div>
            </div>
            <div class="warn"><i class="fas fa-exclamation-triangle"></i><p>Enter the exact UTR. Incorrect details delay verification.</p></div>
            <button type="submit" class="sub-btn"><i class="fas fa-paper-plane"></i> Submit Remaining Payment</button>
        </form>
    </div>
</div>
</div></div>

<?php elseif ($show_form): ?>

<?php if ($existing && $ep_status === 'rejected'): ?>
<div class="sb rejected" style="margin-bottom:20px"><div class="sbi"><i class="fas fa-times-circle"></i></div><div><h4>Previous Payment Rejected</h4><p>Please verify your UTR and resubmit below.</p></div></div>
<?php endif; ?>

<div class="pay-panel" style="margin-bottom:18px">
    <div class="steps-row">
        <div class="sti"><div class="stn active" id="sn1">1</div><div class="stt active" id="st1">Choose Plan</div><div class="stl" id="sl1"></div></div>
        <div class="sti"><div class="stn inactive" id="sn2">2</div><div class="stt" id="st2">Scan &amp; Pay</div><div class="stl" id="sl2"></div></div>
        <div class="sti"><div class="stn inactive" id="sn3">3</div><div class="stt" id="st3">Submit</div></div>
    </div>
</div>

<div class="pay-wrap">
<div class="summary-card">
    <div class="s-head">
        <div class="av"><?php echo strtoupper(htmlspecialchars($student['name'][0])); ?></div>
        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
        <p><?php echo htmlspecialchars($student['email']); ?></p>
    </div>
    <div class="s-body">
        <div class="srow"><div class="sico"><i class="fas fa-book"></i></div><div><div class="slbl">Course</div><div class="sval"><?php echo htmlspecialchars($course['title']); ?></div></div></div>
        <div class="srow"><div class="sico"><i class="fas fa-phone"></i></div><div><div class="slbl">Mobile</div><div class="sval"><?php echo htmlspecialchars($student['mobile'] ?? 'Not provided'); ?></div></div></div>
        <div class="gst-box">
            <div style="font-size:.68rem;font-weight:700;color:#1e40af;margin-bottom:7px;text-transform:uppercase;letter-spacing:.04em"><i class="fas fa-file-invoice"></i> Fee Breakdown</div>
            <div class="grow"><span class="glbl">Course Price</span><span class="gval">&#8377;<?php echo number_format($base_price,2); ?></span></div>
            <div class="grow"><span class="glbl">GST (<?php echo GST_RATE; ?>%) <span class="gbadge">GST</span></span><span class="gval or">+&#8377;<?php echo number_format($gst_amount,2); ?></span></div>
            <div class="grow dv"><span class="glbl" style="font-weight:700">Total Payable</span><span class="gval bl">&#8377;<?php echo number_format($total_price,2); ?></span></div>
        </div>
        <div class="srow" style="padding-top:10px"><div class="sico" style="background:#fef3c7;color:#d97706"><i class="fas fa-shield-alt"></i></div><div><div class="slbl">Method</div><div class="sval" style="font-size:.82rem">PhonePe / BHIM UPI</div></div></div>
        <div class="srow"><div class="sico" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-clock"></i></div><div><div class="slbl">Approval Time</div><div class="sval" style="font-size:.82rem">Within 24 hours</div></div></div>
    </div>
</div>

<div>

<!-- Panel 1: Plan -->
<div class="pay-panel">
    <div class="phead"><div class="phnum">1</div><div><h3>Choose Your Payment Plan</h3><p>Full payment or split into 2 equal installments</p></div></div>
    <div class="plan-sec">
        <div class="plan-grid">
            <label class="plan-card full-c sel" id="cFull">
                <input type="radio" name="_plan" value="full" checked>
                <span class="prec">Recommended</span>
                <div class="pico"><i class="fas fa-check-double"></i></div>
                <div class="ptitle">Full Payment</div>
                <div class="pamt">&#8377;<?php echo number_format($total_price,2); ?></div>
                <div class="psub">Pay once, enroll instantly</div>
            </label>
            <label class="plan-card half-c" id="cHalf">
                <input type="radio" name="_plan" value="half">
                <div class="pico"><i class="fas fa-layer-group"></i></div>
                <div class="ptitle">Pay 50% Now</div>
                <div class="pamt">&#8377;<?php echo number_format($half_price,2); ?></div>
                <div class="psub">Pay &#8377;<?php echo number_format($total_price-$half_price,2); ?> later</div>
            </label>
        </div>
        <div class="emi-note" id="emiNote"><i class="fas fa-info-circle"></i><span><strong>EMI:</strong> Pay &#8377;<?php echo number_format($half_price,2); ?> now &amp; get instant access. Remaining &#8377;<?php echo number_format($total_price-$half_price,2); ?> due after.</span></div>
    </div>
</div>

<!-- Panel 2: QR -->
<div class="pay-panel">
    <div class="phead"><div class="phnum">2</div><div><h3>Scan &amp; Pay via PhonePe / UPI</h3><p id="qrSub">Amount pre-filled &mdash; just scan &amp; pay</p></div></div>
    <div class="qr-area">
        <div class="term-tabs" id="mTabs">
            <?php foreach ($terminals as $i => $t): ?>
            <button type="button" class="ttab<?php echo $i===0?' active':''; ?>" id="mt<?php echo $i; ?>" onclick="switchM(<?php echo $i; ?>)">
                <i class="fas fa-store"></i> <?php echo htmlspecialchars($t['name']); ?>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="qr-box">
            <div class="qr-frame">
                <div class="qr-spinner" id="mSpin"><i class="fas fa-spinner fa-spin"></i><span>Loading QR...</span></div>
                <img id="mQrImg" src="<?php echo htmlspecialchars(qrUrl($terminals[0]['upi'], $total_price, 'Course Fee')); ?>" alt="UPI QR Code" style="display:none"
                     onload="this.style.display='block';document.getElementById('mSpin').style.display='none';this.classList.add('qr-fade')"
                     onerror="document.getElementById('mSpin').innerHTML='<i class=\'fas fa-qrcode\' style=\'color:#5a31f4;font-size:2rem\'></i><br><span style=\'font-size:.75rem\'>Use app buttons below</span>'">
            </div>
            <div class="qr-amt-badge">&#8377;<span id="mBadge"><?php echo number_format($total_price,2); ?></span> <span class="qr-pre">pre-filled</span></div>
            <div class="qr-tname" id="mTName"><i class="fas fa-store" style="color:#5a31f4"></i> <?php echo htmlspecialchars($terminals[0]['name']); ?></div>
            <div class="qr-upi" id="mUpiId"><?php echo htmlspecialchars($terminals[0]['upi']); ?></div>
            <div class="app-row">
                <span class="app-lbl">Open in:</span>
                <a class="app-btn pp" id="mLinkPP" href="<?php echo upiDeepLink($terminals[0]['upi'], $total_price); ?>"><i class="fas fa-mobile-alt"></i> PhonePe</a>
                <a class="app-btn gp" id="mLinkGP" href="<?php echo upiDeepLink($terminals[0]['upi'], $total_price); ?>"><i class="fas fa-google"></i> GPay</a>
                <a class="app-btn up" id="mLinkUP" href="<?php echo upiDeepLink($terminals[0]['upi'], $total_price); ?>"><i class="fas fa-qrcode"></i> Any UPI</a>
            </div>
        </div>
        <div class="pay-note"><i class="fas fa-info-circle"></i>
            <p>Open <strong>PhonePe, GPay, Paytm</strong> or any UPI app &rarr; Scan QR &rarr; Amount <strong id="instrAmt">&#8377;<?php echo number_format($total_price,2); ?></strong> is pre-filled &rarr; Confirm &rarr; Copy <strong>UTR</strong> &rarr; Fill form below.</p>
        </div>
    </div>
</div>

<!-- Panel 3: Form -->
<div class="pay-panel">
    <div class="phead"><div class="phnum">3</div><div><h3>Confirm Payment Details</h3><p>Enter transaction info to complete enrollment</p></div></div>
    <div class="form-sec">
        <form action="submit_payment.php" method="POST" id="payForm" novalidate>
            <input type="hidden" name="course_id"    value="<?php echo (int)$course_id; ?>">
            <input type="hidden" name="payment_mode" value="new">
            <input type="hidden" name="payment_type" id="ptH" value="full">
            <input type="hidden" name="amount"       id="amH" value="<?php echo $total_price; ?>">
            <input type="hidden" name="base_amount"  value="<?php echo $base_price; ?>">
            <input type="hidden" name="gst_rate"     value="<?php echo GST_RATE; ?>">
            <input type="hidden" name="gst_amount"   value="<?php echo $gst_amount; ?>">
            <input type="hidden" name="total_amount" value="<?php echo $total_price; ?>">
            <div class="fr">
                <div class="fg"><label class="fl">Student Name</label><input class="fi" type="text" value="<?php echo htmlspecialchars($student['name']); ?>" readonly></div>
                <div class="fg"><label class="fl">Email Address</label><input class="fi" type="text" value="<?php echo htmlspecialchars($student['email']); ?>" readonly></div>
            </div>
            <div class="fr">
                <div class="fg"><label class="fl">Mobile Number</label><input class="fi" type="text" value="<?php echo htmlspecialchars($student['mobile'] ?? 'N/A'); ?>" readonly></div>
                <div class="fg"><label class="fl">Course</label><input class="fi" type="text" value="<?php echo htmlspecialchars($course['title']); ?>" readonly></div>
            </div>
            <div class="amt-ban">
                <div><div class="abl" id="abL">Full Payment</div><div class="aba" id="abA">&#8377;<?php echo number_format($total_price,2); ?></div><div class="abs" id="abS">Base &#8377;<?php echo number_format($base_price,2); ?> + GST &#8377;<?php echo number_format($gst_amount,2); ?></div></div>
                <i class="fas fa-lock" style="color:rgba(255,255,255,.4);font-size:1.2rem"></i>
            </div>
            <div class="fg">
                <label class="fl">UTR / Transaction ID <span>*</span></label>
                <input class="fi hi" type="text" name="utr_number" id="utrI" placeholder="e.g. 512345678901" required minlength="6" maxlength="50" autocomplete="off" spellcheck="false" oninput="onUtr()">
                <div class="uhint"><i class="fas fa-info-circle"></i> Find this in your UPI app's transaction history after payment</div>
            </div>
            <div class="warn"><i class="fas fa-exclamation-triangle"></i><p>Ensure UTR is exactly correct. Incorrect details delay enrollment verification. Reviewed manually.</p></div>
            <button type="submit" class="sub-btn" id="subBtn"><i class="fas fa-paper-plane"></i><span id="subTxt">Submit Full Payment for Approval</span></button>
        </form>
    </div>
</div>

</div>
</div>
<?php endif; ?>


<!-- ══ POPUP MODALS ══ -->

<!-- Invalid UTR popup -->
<div class="popup-overlay" id="popInvalidUtr">
    <div class="popup-box">
        <div class="popup-icon-wrap">
            <div class="popup-icon error"><i class="fas fa-times-circle"></i></div>
            <div class="popup-title">Invalid Transaction ID</div>
            <p class="popup-msg">
                The UTR / Transaction ID you entered is <strong>not valid</strong>.<br><br>
                A valid UTR is <strong>6–50 alphanumeric characters</strong> (letters and digits only, no spaces or special characters).<br><br>
                Please copy it directly from your UPI app's transaction history and try again.
            </p>
        </div>
        <div class="popup-footer">
            <button class="popup-btn primary" onclick="closePopup('popInvalidUtr')"><i class="fas fa-edit"></i> Fix UTR & Retry</button>
        </div>
    </div>
</div>

<!-- Duplicate UTR popup -->
<div class="popup-overlay" id="popDuplicateUtr">
    <div class="popup-box">
        <div class="popup-icon-wrap">
            <div class="popup-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="popup-title">UTR Already Submitted</div>
            <p class="popup-msg">
                This <strong>UTR / Transaction ID</strong> has already been submitted by another student or in a previous payment.<br><br>
                Each UPI transaction has a unique UTR. Please double-check your UPI app and enter the <strong>correct UTR</strong> for this payment.
            </p>
        </div>
        <div class="popup-footer">
            <button class="popup-btn danger" onclick="closePopup('popDuplicateUtr')"><i class="fas fa-edit"></i> Enter Correct UTR</button>
        </div>
    </div>
</div>

<!-- Invalid input popup (general) -->
<div class="popup-overlay" id="popInvalidInput">
    <div class="popup-box">
        <div class="popup-icon-wrap">
            <div class="popup-icon error"><i class="fas fa-ban"></i></div>
            <div class="popup-title">Missing Information</div>
            <p class="popup-msg">
                Please fill in all required fields, especially the <strong>UTR / Transaction ID</strong> (minimum 6 characters) before submitting.
            </p>
        </div>
        <div class="popup-footer">
            <button class="popup-btn primary" onclick="closePopup('popInvalidInput')"><i class="fas fa-arrow-left"></i> Go Back</button>
        </div>
    </div>
</div>

<!-- DB error popup -->
<div class="popup-overlay" id="popDbError">
    <div class="popup-box">
        <div class="popup-icon-wrap">
            <div class="popup-icon error"><i class="fas fa-server"></i></div>
            <div class="popup-title">Submission Failed</div>
            <p class="popup-msg">
                Something went wrong while saving your payment. Please try again in a moment.<br><br>
                If the issue persists, contact support with your UTR number.
            </p>
        </div>
        <div class="popup-footer">
            <button class="popup-btn primary" onclick="closePopup('popDbError')"><i class="fas fa-redo"></i> Try Again</button>
        </div>
    </div>
</div>

<!-- Duplicate payment popup -->
<div class="popup-overlay" id="popDuplicatePayment">
    <div class="popup-box">
        <div class="popup-icon-wrap">
            <div class="popup-icon info"><i class="fas fa-info-circle"></i></div>
            <div class="popup-title">Payment Already Submitted</div>
            <p class="popup-msg">
                You have already submitted a payment for this course which is pending or approved.<br><br>
                Please check your <strong>Payment Status</strong> page for updates.
            </p>
        </div>
        <div class="popup-footer">
            <a href="payment_status.php" class="popup-btn primary" style="text-decoration:none"><i class="fas fa-receipt"></i> View Status</a>
        </div>
    </div>
</div>

</div></div></div>

<script>
var TOTAL  = <?php echo $total_price; ?>;
var HALF   = <?php echo $half_price; ?>;
var BASE   = <?php echo $base_price; ?>;
var GAMT   = <?php echo $gst_amount; ?>;
var REMAMT = <?php echo $rem_amt; ?>;

var TERMINALS = <?php echo json_encode(array_map(function($t){
    return ['name'=>$t['name'],'upi'=>$t['upi']];
}, $terminals)); ?>;

var curPlan  = 'full';
var curTermM = 0;
var curTermR = 0;

function deepLink(upi, amt, note) {
    // CRITICAL FIX: Keep @ intact in UPI VPA — encodeURIComponent converts @ -> %40
    // which causes "Could not initiate transaction" in PhonePe/GPay.
    var pa = upi.replace(/ /g, '%20');         // keep @ as-is
    var pn = 'CourseFlow%20AI';               // short name, pre-encoded
    var am = parseFloat(amt).toFixed(2);
    var tn = encodeURIComponent(note || 'Course Fee');
    return 'upi://pay?pa=' + pa + '&pn=' + pn + '&am=' + am + '&cu=INR&tn=' + tn + '&mode=02&purpose=00';
}

function qrSrc(upi, amt, note) {
    return 'qr_generate.php?upi=' + encodeURIComponent(upi)
         + '&amt=' + parseFloat(amt).toFixed(2)
         + '&note=' + encodeURIComponent(note || 'Course Fee')
         + '&size=200&t=' + Date.now();
}

function swapQR(imgId, spinId, upi, amt, note) {
    var img = document.getElementById(imgId);
    var spin = document.getElementById(spinId);
    if (!img || !spin) return;
    img.style.display = 'none';
    spin.style.display = 'flex';
    spin.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Loading QR...</span>';
    img.onload = function() {
        spin.style.display = 'none';
        img.style.display = 'block';
        img.classList.remove('qr-fade');
        void img.offsetWidth;
        img.classList.add('qr-fade');
    };
    img.onerror = function() {
        spin.innerHTML = '<i class="fas fa-qrcode" style="color:#5a31f4;font-size:2rem"></i><span style="font-size:.75rem;margin-top:4px">Use app buttons below</span>';
    };
    img.src = qrSrc(upi, amt, note);
}

function updateMainQR(termIdx, amt) {
    var t = TERMINALS[termIdx];
    var note = (curPlan === 'full') ? 'Course Fee' : 'First Installment';
    swapQR('mQrImg', 'mSpin', t.upi, amt, note);
    var tn = document.getElementById('mTName');
    var ui = document.getElementById('mUpiId');
    if (tn) tn.innerHTML = '<i class="fas fa-store" style="color:#5a31f4"></i> ' + t.name;
    if (ui) ui.textContent = t.upi;
    var dl = deepLink(t.upi, amt, note);
    ['mLinkPP','mLinkGP','mLinkUP'].forEach(function(id) {
        var a = document.getElementById(id); if (a) a.href = dl;
    });
}

function updateRemainQR(termIdx) {
    if (REMAMT <= 0) return;
    var t = TERMINALS[termIdx];
    swapQR('rQrImg', 'rSpin', t.upi, REMAMT, 'Remaining Fee');
    var tn = document.getElementById('rTName');
    var ui = document.getElementById('rUpiId');
    if (tn) tn.innerHTML = '<i class="fas fa-store" style="color:#5a31f4"></i> ' + t.name;
    if (ui) ui.textContent = t.upi;
    var dl = deepLink(t.upi, REMAMT, 'Remaining Fee');
    ['rLinkPP','rLinkGP','rLinkUP'].forEach(function(id) {
        var a = document.getElementById(id); if (a) a.href = dl;
    });
}

/* Terminal tab switches — plain buttons with onclick, unaffected by plan cards */
function switchM(idx) {
    curTermM = idx;
    document.querySelectorAll('#mTabs .ttab').forEach(function(b, i) {
        b.classList.toggle('active', i === idx);
    });
    updateMainQR(idx, curPlan === 'full' ? TOTAL : HALF);
}

function switchR(idx) {
    curTermR = idx;
    document.querySelectorAll('#rTabs .ttab').forEach(function(b, i) {
        b.classList.toggle('active', i === idx);
    });
    updateRemainQR(idx);
}

/*
 * PLAN SELECTION
 * The radio input (opacity:0, z-index:0) fills the entire plan card.
 * Clicking anywhere on the card checks the radio and fires 'change' exactly once.
 * 'value' is "full" or "half" — maps to payment_type "full" or "partial".
 */
document.querySelectorAll('input[name="_plan"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        selPlan(this.value);
    });
});

function selPlan(plan) {
    curPlan = plan;

    /* Plan card highlight */
    var cF = document.getElementById('cFull');
    var cH = document.getElementById('cHalf');
    if (cF) cF.classList.toggle('sel', plan === 'full');
    if (cH) cH.classList.toggle('sel', plan === 'half');

    /* EMI note */
    var en = document.getElementById('emiNote');
    if (en) en.classList.toggle('show', plan === 'half');

    /* Amount */
    var amt = (plan === 'full') ? TOTAL : HALF;

    /*
     * Update hidden fields — payment_type is the authoritative field
     * read by submit_payment.php. "full" => full payment, "partial" => 50% installment.
     */
    document.getElementById('ptH').value = (plan === 'full') ? 'full' : 'partial';
    document.getElementById('amH').value = amt;

    /* Amount banner */
    var fmt = function(n) { return '₹' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); };
    var abL = document.getElementById('abL');
    var abA = document.getElementById('abA');
    var abS = document.getElementById('abS');
    if (abL) abL.textContent = (plan === 'full') ? 'Full Payment' : 'First Installment (50%)';
    if (abA) abA.innerHTML   = fmt(amt);
    if (abS) abS.textContent = (plan === 'full')
        ? 'Base ' + fmt(BASE) + ' + GST ' + fmt(GAMT)
        : 'Remaining ' + fmt(TOTAL - HALF) + ' due later';

    /* QR badge & instruction text */
    var mb = document.getElementById('mBadge');
    var qs = document.getElementById('qrSub');
    var ia = document.getElementById('instrAmt');
    var st = document.getElementById('subTxt');
    if (mb) mb.textContent = amt.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    if (qs) qs.textContent = fmt(amt) + ' pre-filled — just scan & pay';
    if (ia) ia.innerHTML   = fmt(amt);
    if (st) st.textContent = (plan === 'full')
        ? 'Submit Full Payment for Approval'
        : 'Submit 50% Installment for Approval';

    /* Refresh QR with correct amount */
    updateMainQR(curTermM, amt);
    doSteps();
}

/* Steps */
function doSteps() {
    var utr = ((document.getElementById('utrI') || {}).value || '').trim();
    step(1, 'done');
    step(2, utr.length >= 6 ? 'done' : 'active');
    step(3, utr.length >= 6 ? 'active' : 'inactive');
}
function step(n, state) {
    var nm = document.getElementById('sn' + n);
    var tx = document.getElementById('st' + n);
    var ln = document.getElementById('sl' + n);
    if (!nm) return;
    nm.className = 'stn ' + state;
    nm.innerHTML = (state === 'done') ? '<i class="fas fa-check" style="font-size:.6rem"></i>' : n;
    tx.className = 'stt ' + state;
    if (ln) ln.className = 'stl ' + (state === 'done' ? 'done' : '');
}
function onUtr() { doSteps(); }

/* ── Popup system ── */
function closePopup(id) {
    var el = document.getElementById(id);
    if (el) {
        el.style.animation = 'ovIn .15s ease reverse';
        setTimeout(function() {
            el.classList.remove('show');
            el.style.animation = '';
            // Re-enable submit button so user can retry
            var btn = document.getElementById('subBtn');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i><span id="subTxt">' + (curPlan === 'full' ? 'Submit Full Payment for Approval' : 'Submit 50% Installment for Approval') + '</span>'; }
        }, 150);
    }
}
function showPopup(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('show');
}

// Close popup when clicking the overlay background
document.querySelectorAll('.popup-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) closePopup(this.id);
    });
});

// Auto-show popup based on ?error= URL param
(function() {
    var err = '<?php echo htmlspecialchars($page_error); ?>';
    var map = {
        'invalid_utr'       : 'popInvalidUtr',
        'duplicate_utr'     : 'popDuplicateUtr',
        'invalid_input'     : 'popInvalidInput',
        'db_error'          : 'popDbError',
        'duplicate'         : 'popDuplicatePayment',
    };
    if (err && map[err]) {
        showPopup(map[err]);
        // Focus the UTR input so user can immediately fix it
        if (err === 'invalid_utr' || err === 'duplicate_utr') {
            setTimeout(function() {
                var f = document.getElementById('utrI');
                if (f) { f.focus(); f.select(); }
            }, 300);
        }
    }
})();

/* Form submit guard */
var pf = document.getElementById('payForm');
if (pf) {
    pf.addEventListener('submit', function(e) {
        var utr = (document.getElementById('utrI').value || '').trim();
        // Client-side: must be 6-50 alphanumeric characters
        var utrValid = /^[A-Za-z0-9]{6,50}$/.test(utr);
        if (!utrValid) {
            e.preventDefault();
            showPopup('popInvalidUtr');
            return;
        }
        var btn = document.getElementById('subBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        step(1, 'done'); step(2, 'done'); step(3, 'active');
    });
}
</script>

<?php include("../includes/footer.php"); ?>
</body>
</html>