<?php
/**
 * finance/internship_payments.php — CourseFlow
 * Finance panel: full internship payment management.
 * Approve / Reject / Email — identical capability to admin panel.
 */

session_start();
include("../config/db.php");
include("../includes/auth_check.php");
include_once "../includes/mailer.php";   // ← SMTP mailer (FIX: added)

requireLogin($conn);
requireRole("finance");

/* ═══════════════════════════════════════════════════════
   EMAIL FUNCTIONS  (copied from admin panel)
   ═══════════════════════════════════════════════════════ */

function send_approval_email(array $pay, string $def_pass = ''): void {
    $to      = $pay['email'];
    $name    = $pay['full_name'];
    $domain  = $pay['internship_chosen'];
    $utr     = $pay['utr_number'];
    $amount  = number_format((float)$pay['amount'], 2);
    $pid_str = '#VSI-PAY-' . str_pad($pay['id'], 4, '0', STR_PAD_LEFT);
    $college = $pay['college_name'];
    $dept    = $pay['department'];
    $reg_num = $pay['registration_number'];

    $subject = "🎉 Internship Enrollment Confirmed — {$domain} | CourseFlow";

    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:'Plus Jakarta Sans',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <tr><td style="background:linear-gradient(135deg,#0f1b3d,#2448c8);border-radius:16px 16px 0 0;padding:40px 40px 32px;text-align:center;">
    <div style="width:72px;height:72px;background:linear-gradient(135deg,#4ade80,#22c55e);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:16px;">✅</div>
    <div style="font-size:1.6rem;font-weight:800;color:#fff;margin-bottom:4px;">Payment Approved!</div>
    <div style="font-size:.88rem;color:rgba(255,255,255,.7);">You're officially enrolled in the {$domain} Internship</div>
    <div style="margin-top:18px;background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.4);border-radius:40px;padding:7px 20px;display:inline-block;font-size:.78rem;color:#86efac;font-weight:600;letter-spacing:.06em;text-transform:uppercase;">🎓 Enrollment Confirmed</div>
  </td></tr>

  <tr><td style="background:#ffffff;padding:36px 40px;">
    <p style="font-size:1.05rem;font-weight:700;color:#1e293b;margin:0 0 8px;">Hi {$name}, welcome aboard! 🚀</p>
    <p style="font-size:.9rem;color:#64748b;margin:0 0 26px;line-height:1.7;">Your payment has been <strong style="color:#16a34a;">verified and approved</strong> by our team. You are now officially enrolled in the <strong>{$domain}</strong> internship program.</p>

    <div style="background:linear-gradient(135deg,#f0fdf4,#f0f9ff);border:1.5px solid #bbf7d0;border-radius:14px;padding:22px 24px;margin-bottom:24px;">
      <div style="font-size:.7rem;font-weight:700;color:#14532d;text-transform:uppercase;letter-spacing:.1em;margin-bottom:14px;">📋 Enrollment Summary</div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;width:40%;">Payment ID</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$pid_str}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Internship Track</td><td style="font-size:.83rem;font-weight:700;color:#1e40af;text-align:right;">{$domain}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Registration No.</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$reg_num}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">College</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$college}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Department</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$dept}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Amount Paid</td><td style="font-size:.83rem;font-weight:700;color:#16a34a;text-align:right;">₹{$amount}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">UTR Number</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;font-family:monospace;">{$utr}</td></tr>
      </table>
    </div>

    <div style="margin-bottom:26px;">
      <div style="font-size:.75rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px;">🗓️ What Happens Next?</div>
      <div style="padding:14px;background:#f8faff;border-left:4px solid #3b82f6;border-radius:0 10px 10px 0;margin-bottom:10px;">
        <div style="font-size:.85rem;font-weight:700;color:#1e293b;margin-bottom:3px;">1. Onboarding Email (within 12 hrs)</div>
        <div style="font-size:.82rem;color:#64748b;line-height:1.5;">You'll receive your internship schedule, mentor details, joining link, and resource kit.</div>
      </div>
      <div style="padding:14px;background:#f8faff;border-left:4px solid #10b981;border-radius:0 10px 10px 0;margin-bottom:10px;">
        <div style="font-size:.85rem;font-weight:700;color:#1e293b;margin-bottom:3px;">2. Orientation Session</div>
        <div style="font-size:.82rem;color:#64748b;line-height:1.5;">Attend the live orientation where your mentor will walk you through the {$domain} curriculum and project roadmap.</div>
      </div>
      <div style="padding:14px;background:#f8faff;border-left:4px solid #a78bfa;border-radius:0 10px 10px 0;">
        <div style="font-size:.85rem;font-weight:700;color:#1e293b;margin-bottom:3px;">3. Start Building &amp; Learning</div>
        <div style="font-size:.82rem;color:#64748b;line-height:1.5;">Dive into real-world projects, weekly sessions, and mentorship over the 6-week program.</div>
      </div>
    </div>

    <div style="background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1.5px solid #bfdbfe;border-radius:14px;padding:22px 24px;margin-bottom:24px;">
      <div style="font-size:.7rem;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.1em;margin-bottom:14px;">🔐 Your LMS Login Credentials</div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="font-size:.83rem;color:#64748b;padding:6px 0;width:40%;">Login URL</td><td style="font-size:.83rem;font-weight:700;color:#1e40af;text-align:right;"><a href="https://yourdomain.com/login.php" style="color:#1e40af;">yourdomain.com/login.php</a></td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:6px 0;">Email (Username)</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;font-family:monospace;">{$to}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:6px 0;">Default Password</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;font-family:monospace;">{$def_pass}</td></tr>
      </table>
      <div style="margin-top:12px;font-size:.76rem;color:#1e40af;background:rgba(59,130,246,.08);border-radius:8px;padding:9px 12px;line-height:1.6;">
        ⚠️ <strong>Please change your password</strong> after your first login from your profile settings.
      </div>
    </div>

    <div style="text-align:center;margin-bottom:24px;">
      <a href="https://yourdomain.com/login.php" style="display:inline-block;padding:14px 36px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;text-decoration:none;border-radius:12px;font-weight:700;font-size:.92rem;box-shadow:0 4px 14px rgba(59,130,246,.35);">Login to Dashboard →</a>
    </div>

    <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;font-size:.8rem;color:#92400e;line-height:1.6;margin-bottom:16px;">
      💡 <strong>Keep this email safe</strong> — it is your enrollment confirmation. Your UTR number <strong>{$utr}</strong> is your payment reference.
    </div>
    <p style="font-size:.82rem;color:#94a3b8;line-height:1.6;margin:0;">Questions? Reply to this email or reach us on WhatsApp. We're here to help you succeed!</p>
  </td></tr>

  <tr><td style="background:#0f1b3d;border-radius:0 0 16px 16px;padding:22px 40px;text-align:center;">
    <div style="font-size:.8rem;color:rgba(255,255,255,.6);margin-bottom:4px;">CourseFlow Pvt. Ltd.</div>
    <div style="font-size:.72rem;color:rgba(255,255,255,.3);">© 2025 All rights reserved.</div>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

    // Send via SMTP (FIX: replaced @mail() with vs_send_mail)
    vs_send_mail($to, $name, $subject, $body, 'support@yourdomain.com');
}

function send_rejection_email(array $pay, string $note): void {
    $to     = $pay['email'];
    $name   = $pay['full_name'];
    $domain = $pay['internship_chosen'];
    $utr    = $pay['utr_number'];
    $reason = $note ?: 'The UTR number provided could not be verified. Please check your payment and resubmit with the correct UTR.';
    $reg_id = $pay['reg_id'];

    $subject = "Action Required — Payment Verification Failed | CourseFlow";

    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:'Plus Jakarta Sans',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:linear-gradient(135deg,#7f1d1d,#dc2626);border-radius:16px 16px 0 0;padding:36px 40px 28px;text-align:center;">
    <div style="font-size:2.5rem;margin-bottom:12px;">⚠️</div>
    <div style="font-size:1.4rem;font-weight:800;color:#fff;">Payment Rejected</div>
    <div style="font-size:.85rem;color:rgba(255,255,255,.75);margin-top:6px;">Action required to complete your enrollment</div>
  </td></tr>
  <tr><td style="background:#fff;padding:36px 40px;">
    <p style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 8px;">Hi {$name},</p>
    <p style="font-size:.88rem;color:#64748b;margin:0 0 22px;line-height:1.7;">Unfortunately, your payment submission for the <strong>{$domain}</strong> internship could not be verified. Please see the reason below and resubmit.</p>
    <div style="background:#fff1f2;border:1.5px solid #fecdd3;border-radius:12px;padding:18px 20px;margin-bottom:22px;">
      <div style="font-size:.7rem;font-weight:700;color:#881337;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">❌ Rejection Reason</div>
      <div style="font-size:.88rem;color:#7f1d1d;line-height:1.6;">{$reason}</div>
    </div>
    <div style="background:#f0f5ff;border:1px solid #bfdbfe;border-radius:12px;padding:18px 20px;margin-bottom:22px;">
      <div style="font-size:.7rem;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;">📋 Submitted Details</div>
      <div style="font-size:.83rem;color:#64748b;margin-bottom:4px;">UTR Submitted: <strong style="color:#1e293b;font-family:monospace;">{$utr}</strong></div>
      <div style="font-size:.83rem;color:#64748b;">Internship: <strong style="color:#1e293b;">{$domain}</strong></div>
    </div>
    <div style="text-align:center;margin-bottom:22px;">
      <a href="https://yourdomain.com/internship/internship_payment.php?reg_id={$reg_id}" style="display:inline-block;padding:13px 32px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;text-decoration:none;border-radius:12px;font-weight:700;font-size:.9rem;">Resubmit Payment →</a>
    </div>
    <p style="font-size:.82rem;color:#94a3b8;line-height:1.6;margin:0;">If you believe this is an error, reply to this email with a screenshot of your UPI payment confirmation. Our team will review it promptly.</p>
  </td></tr>
  <tr><td style="background:#0f1b3d;border-radius:0 0 16px 16px;padding:20px 40px;text-align:center;">
    <div style="font-size:.78rem;color:rgba(255,255,255,.5);">© 2025 CourseFlow Pvt. Ltd.</div>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    // Send via SMTP (FIX: replaced @mail() with vs_send_mail)
    vs_send_mail($to, $name, $subject, $body, 'support@yourdomain.com');
}

/* ═══════════════════════════════════════════════════════
   AJAX — Approve / Reject
   ═══════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action     = $_POST['action'];
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $note       = trim(strip_tags($_POST['note'] ?? ''));

    if (!in_array($action, ['approve', 'reject']) || $payment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit;
    }

    /* Load payment + registration */
    $stmt = $conn->prepare("SELECT ip.*, ir.college_name, ir.registration_number, ir.department,
                                   ir.admission_type
                            FROM internship_payments ip
                            JOIN internship_registrations ir ON ip.reg_id = ir.id
                            WHERE ip.id = ? LIMIT 1");
    $stmt->bind_param("i", $payment_id); $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc(); $stmt->close();

    if (!$pay) { echo json_encode(['success' => false, 'message' => 'Payment not found.']); exit; }

    $new_status = ($action === 'approve') ? 'approved' : 'rejected';

    /* Update status */
    $up = $conn->prepare("UPDATE internship_payments SET payment_status = ?, admin_note = ?, reviewed_at = NOW() WHERE id = ?");
    $up->bind_param("ssi", $new_status, $note, $payment_id); $up->execute(); $up->close();

    if ($action === 'approve') {
        /* Create student account */
        $def_pass    = $pay['registration_number'];
        $hashed_pass = password_hash($def_pass, PASSWORD_DEFAULT);
        $s_name      = $pay['full_name'];
        $s_email     = $pay['email'];
        $s_mobile    = $pay['mobile_number'];
        $s_college   = $pay['college_name'];
        $s_dept      = $pay['department'];
        $s_course    = $pay['internship_chosen'];

        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param("s", $s_email); $chk->execute(); $chk->store_result();
        if ($chk->num_rows === 0) {
            $ins = $conn->prepare("INSERT INTO users (name, email, password, role, mobile, college, dept, internship_course, status) VALUES (?, ?, ?, 'student', ?, ?, ?, ?, 'active')");
            $ins->bind_param("sssssss", $s_name, $s_email, $hashed_pass, $s_mobile, $s_college, $s_dept, $s_course);
            $ins->execute(); $new_user_id = $conn->insert_id; $ins->close();
        } else {
            $chk->bind_result($new_user_id); $chk->fetch();
        }
        $chk->close();

        /* Auto-enroll into matching course */
        if (!empty($new_user_id)) {
            $cq = $conn->prepare("SELECT id, trainer_id FROM courses WHERE LOWER(title) LIKE LOWER(?) LIMIT 1");
            $like_term = '%' . $s_course . '%';
            $cq->bind_param("s", $like_term); $cq->execute();
            $cq->bind_result($matched_course_id, $matched_trainer_id); $cq->fetch(); $cq->close();

            if (!empty($matched_course_id)) {
                $ec = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
                $ec->bind_param("ii", $new_user_id, $matched_course_id); $ec->execute(); $ec->store_result();
                if ($ec->num_rows === 0) {
                    $tid = !empty($matched_trainer_id) ? $matched_trainer_id : 0;
                    $ei  = $conn->prepare("INSERT INTO enrollments (user_id, course_id, trainer_id, status) VALUES (?, ?, ?, 'approved')");
                    $ei->bind_param("iii", $new_user_id, $matched_course_id, $tid); $ei->execute(); $ei->close();
                }
                $ec->close();
            }
        }

        send_approval_email($pay, $def_pass);
    } else {
        send_rejection_email($pay, $note);
    }

    /* Log notification */
    $log_title = ($action === 'approve') ? 'Payment Approved' : 'Payment Rejected';
    $log_msg   = $pay['full_name'] . ' — ' . $pay['internship_chosen'] . ' (' . $log_title . ') [Finance]';
    $ns = $conn->prepare("INSERT INTO notifications (type, for_role, for_user_id, title, message, link, is_read) VALUES ('internship_payment','admin',NULL,?,?,?,0)");
    if ($ns) {
        $lnk = '/admin/admin_internship_payments.php?id=' . $payment_id;
        $ns->bind_param("sss", $log_title, $log_msg, $lnk); $ns->execute(); $ns->close();
    }

    echo json_encode(['success' => true, 'status' => $new_status]); exit;
}

/* ═══════════════════════════════════════════════════════
   CSV Export
   ═══════════════════════════════════════════════════════ */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $ef = $_GET['filter'] ?? 'all'; $eq = trim($_GET['q'] ?? '');
    $ew = "WHERE 1=1"; $ep = []; $et = "";
    if ($ef !== 'all') { $ew .= " AND ip.payment_status = ?"; $ep[] = $ef; $et .= "s"; }
    if ($eq !== '') {
        $el = '%'.$eq.'%';
        $ew .= " AND (ip.full_name LIKE ? OR ip.email LIKE ? OR ip.utr_number LIKE ? OR ip.internship_chosen LIKE ?)";
        $ep[] = $el; $ep[] = $el; $ep[] = $el; $ep[] = $el; $et .= "ssss";
    }
    $es = $conn->prepare("SELECT ip.id,ip.reg_id,ip.full_name,ip.email,ip.mobile_number,ip.internship_chosen,
                                 ir.college_name,ir.department,ir.registration_number,
                                 ip.utr_number,ip.amount,ip.terminal_name,ip.payment_status,
                                 ip.submitted_at,ip.reviewed_at,ip.admin_note
                          FROM internship_payments ip
                          JOIN internship_registrations ir ON ip.reg_id=ir.id {$ew}
                          ORDER BY ip.submitted_at DESC");
    if ($ep) { $es->bind_param($et, ...$ep); }
    $es->execute(); $edata = $es->get_result()->fetch_all(MYSQLI_ASSOC); $es->close();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="internship_payments_'.date('Ymd_His').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['#','Reg ID','Name','Email','Mobile','Internship','College','Dept','Reg No.','UTR','Amount (₹)','Terminal','Status','Submitted','Reviewed','Admin Note']);
    foreach ($edata as $p) {
        fputcsv($out, [$p['id'],$p['reg_id'],$p['full_name'],$p['email'],$p['mobile_number'],
                       $p['internship_chosen'],$p['college_name'],$p['department'],$p['registration_number'],
                       $p['utr_number'],number_format((float)$p['amount'],2),$p['terminal_name'],
                       $p['payment_status'],$p['submitted_at'],$p['reviewed_at'],$p['admin_note']]);
    }
    fclose($out); exit;
}

/* ═══════════════════════════════════════════════════════
   Load payments
   ═══════════════════════════════════════════════════════ */
$filter    = $_GET['filter'] ?? 'all';
$search    = trim($_GET['q'] ?? '');
$detail_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$where = "WHERE 1=1"; $params = []; $types = "";
if ($filter !== 'all') { $where .= " AND ip.payment_status = ?"; $params[] = $filter; $types .= "s"; }
if ($search !== '') {
    $like = '%'.$search.'%';
    $where .= " AND (ip.full_name LIKE ? OR ip.email LIKE ? OR ip.utr_number LIKE ? OR ip.internship_chosen LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $types .= "ssss";
}

$stmt = $conn->prepare("SELECT ip.*, ir.registration_number, ir.college_name, ir.department, ir.admission_type
                        FROM internship_payments ip
                        JOIN internship_registrations ir ON ip.reg_id = ir.id
                        {$where} ORDER BY ip.submitted_at DESC");
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Stats */
$stats = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'payment_later' => 0];
$sq = $conn->query("SELECT payment_status, COUNT(*) as c FROM internship_payments GROUP BY payment_status");
if ($sq) { while ($r = $sq->fetch_assoc()) { $key = $r['payment_status']; $stats[$key] = (int)$r['c']; $stats['all'] += (int)$r['c']; } }

/* Revenue figures */
$rev_r  = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM internship_payments WHERE payment_status='approved'");
$total_rev = $rev_r ? (float)$rev_r->fetch_assoc()['t'] : 0;

$pend_r = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM internship_payments WHERE payment_status='pending'");
$pending_rev = $pend_r ? (float)$pend_r->fetch_assoc()['t'] : 0;

$mon_r  = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM internship_payments WHERE payment_status='approved' AND MONTH(reviewed_at)=MONTH(CURDATE()) AND YEAR(reviewed_at)=YEAR(CURDATE())");
$month_rev = $mon_r ? (float)$mon_r->fetch_assoc()['t'] : 0;

/* Detail modal */
$detail = null;
if ($detail_id > 0) {
    $ds = $conn->prepare("SELECT ip.*, ir.registration_number, ir.college_name, ir.department, ir.admission_type
                          FROM internship_payments ip JOIN internship_registrations ir ON ip.reg_id=ir.id
                          WHERE ip.id=? LIMIT 1");
    $ds->bind_param("i", $detail_id); $ds->execute();
    $detail = $ds->get_result()->fetch_assoc(); $ds->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Internship Payments — CourseFlow Finance</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="icon" type="image/png" href="../assets/images/favicon.png?v=2"/>
<link rel="stylesheet" href="../assets/css/style.css"/>
<style>

/* ── PAGE HEADER ── (matches finance panel style) */
.fin-page-hdr { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:26px; }
.fin-page-hdr h1 { font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:10px; }
.fin-hdr-ico { width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;box-shadow:0 4px 12px rgba(16,185,129,.35); }
.fin-page-hdr p { color:#64748b;font-size:.83rem;margin-top:4px;margin-left:48px; }

/* ── KPI MINI STATS (matches fin-stat-grid2) ── */
.ip-kpi-row { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px; }
@media(max-width:1000px){ .ip-kpi-row { grid-template-columns:repeat(2,1fr); } }
@media(max-width:540px){  .ip-kpi-row { grid-template-columns:1fr 1fr; } }
.ip-kpi {
    background:#fff; border-radius:14px; padding:18px 20px;
    border:1px solid #eef2f7; box-shadow:0 2px 10px rgba(15,23,42,.05);
    position:relative; overflow:hidden; transition:all .2s;
}
.ip-kpi:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(15,23,42,.09); }
.ip-kpi::before { content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0; }
.ip-kpi.green::before  { background:linear-gradient(90deg,#10b981,#6ee7b7); }
.ip-kpi.amber::before  { background:linear-gradient(90deg,#f59e0b,#fcd34d); }
.ip-kpi.blue::before   { background:linear-gradient(90deg,#2563eb,#60a5fa); }
.ip-kpi.purple::before { background:linear-gradient(90deg,#7c3aed,#c4b5fd); }
.ip-kpi-ico { width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.88rem;margin-bottom:12px; }
.ip-kpi.green  .ip-kpi-ico { background:#f0fdf4;color:#059669; }
.ip-kpi.amber  .ip-kpi-ico { background:#fffbeb;color:#b45309; }
.ip-kpi.blue   .ip-kpi-ico { background:#eff6ff;color:#2563eb; }
.ip-kpi.purple .ip-kpi-ico { background:#f5f3ff;color:#7c3aed; }
.ip-kpi-num { font-family:'Plus Jakarta Sans',sans-serif;font-size:1.45rem;font-weight:900;color:#0f172a;line-height:1;margin-bottom:4px; }
.ip-kpi-lbl { font-size:.73rem;color:#64748b;font-weight:500; }

/* ── TABS (matches fin-tabs style) ── */
.fin-tabs { display:flex;gap:8px;margin-bottom:22px;flex-wrap:wrap; }
.fin-tab  { display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:999px;font-size:.83rem;font-weight:700;border:2px solid #e2e8f0;background:#fff;color:#64748b;text-decoration:none;transition:all .2s; }
.fin-tab:hover { border-color:#94a3b8;color:#334155; }
.fin-tab.active-all           { border-color:#64748b;background:#64748b;color:#fff; }
.fin-tab.active-pending       { border-color:#f59e0b;background:#f59e0b;color:#fff; }
.fin-tab.active-approved      { border-color:#10b981;background:#10b981;color:#fff; }
.fin-tab.active-rejected      { border-color:#ef4444;background:#ef4444;color:#fff; }
.fin-tab.active-payment_later { border-color:#3b82f6;background:#3b82f6;color:#fff; }
.fin-tab .tab-count { background:rgba(255,255,255,.25);padding:1px 7px;border-radius:999px;font-size:.72rem; }
.fin-tab:not([class*="active"]) .tab-count { background:#f1f5f9;color:#64748b; }

/* ── SEARCH TOOLBAR ── */
.ip-toolbar { display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap; }
.ip-search-wrap { flex:1;min-width:220px;position:relative; }
.ip-search-wrap i { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.85rem; }
.ip-search-inp { width:100%;padding:10px 12px 10px 36px;border:2px solid #e2e8f0;border-radius:10px;font-size:.85rem;color:#1e293b;background:#f8fafc;outline:none;font-family:inherit;transition:all .2s;box-sizing:border-box; }
.ip-search-inp:focus { border-color:#10b981;background:#fff; }
.ip-export-btn { display:inline-flex;align-items:center;gap:7px;padding:10px 18px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:10px;font-size:.82rem;font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit;box-shadow:0 3px 10px rgba(16,185,129,.25);transition:all .2s;white-space:nowrap; }
.ip-export-btn:hover { transform:translateY(-1px);box-shadow:0 6px 16px rgba(16,185,129,.35); }

/* ── TABLE (matches fin-tbl-wrap) ── */
.fin-tbl-wrap { background:#fff;border-radius:16px;border:1px solid #eef2f7;box-shadow:0 2px 12px rgba(15,23,42,.05);overflow:hidden; }
.ip-tbl { width:100%;border-collapse:collapse;min-width:980px; }
.ip-tbl th { padding:12px 14px;text-align:left;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.85);background:linear-gradient(135deg,#0a2540,#059669);white-space:nowrap; }
.ip-tbl td { padding:13px 14px;font-size:.82rem;color:#334155;border-bottom:1px solid #f8fafc;vertical-align:middle; }
.ip-tbl tbody tr:last-child td { border-bottom:none; }
.ip-tbl tbody tr:hover td { background:#f8fff8; }
.td-name { font-weight:700;color:#0f172a;font-size:.84rem; }
.td-sub  { font-size:.72rem;color:#94a3b8;margin-top:1px; }
.td-utr  { font-family:monospace;font-size:.78rem;color:#059669;background:#f0fdf4;padding:2px 8px;border-radius:6px;display:inline-block;border:1px solid #bbf7d0; }
.td-amt  { font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:.9rem;color:#059669; }

.ip-badge { display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:999px;font-size:.7rem;font-weight:700; }
.ip-badge.pending        { background:#fef3c7;color:#92400e; }
.ip-badge.approved       { background:#dcfce7;color:#14532d; }
.ip-badge.rejected       { background:#fee2e2;color:#7f1d1d; }
.ip-badge.payment_later  { background:#eff6ff;color:#1e40af; }

.btn-sm { display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:8px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;font-family:inherit;transition:all .18s;text-decoration:none; }
.btn-view    { background:#eff6ff;color:#2563eb; } .btn-view:hover    { background:#dbeafe; }
.btn-approve { background:#dcfce7;color:#14532d; } .btn-approve:hover { background:#bbf7d0; }
.btn-reject  { background:#fee2e2;color:#7f1d1d; } .btn-reject:hover  { background:#fecdd3; }
.act-row { display:flex;gap:5px;flex-wrap:wrap; }

.ip-empty { text-align:center;padding:60px 24px; }
.ip-empty i { font-size:2.4rem;color:#cbd5e1;display:block;margin-bottom:14px; }
.ip-empty p { font-size:.88rem;color:#94a3b8; }

/* ── TABLE FOOTER ── */
.tbl-footer { padding:12px 18px;background:#f8fafc;border-top:1px solid #f1f5f9;font-size:.75rem;color:#94a3b8;font-weight:600;display:flex;align-items:center;justify-content:space-between; }

/* ── MODALS ── */
.ip-modal-overlay { display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px; }
.ip-modal-overlay.show { display:flex;animation:ovIn .2s; }
@keyframes ovIn { from{opacity:0}to{opacity:1} }
.ip-modal-box { background:#fff;border-radius:20px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 70px rgba(0,0,0,.22);animation:mIn .25s cubic-bezier(.34,1.56,.64,1); }
@keyframes mIn { from{transform:scale(.88);opacity:0}to{transform:scale(1);opacity:1} }
.ip-modal-head { border-radius:20px 20px 0 0;padding:20px 24px;display:flex;align-items:center;justify-content:space-between; }
.ip-modal-head h2 { color:#fff;font-size:1rem;font-weight:700;margin:0; }
.ip-modal-close { background:rgba(255,255,255,.18);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;transition:background .2s; }
.ip-modal-close:hover { background:rgba(255,255,255,.28); }
.ip-modal-body { padding:22px 24px; }
.ip-modal-footer { padding:14px 24px 20px;display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid #f1f5f9; }
.drow { display:flex;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px solid #f1f5f9; }
.drow:last-child { border-bottom:none; }
.dico { width:30px;height:30px;border-radius:8px;background:#f0fdf4;color:#059669;display:flex;align-items:center;justify-content:center;font-size:.78rem;flex-shrink:0;margin-top:2px; }
.dlbl { font-size:.66rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;font-weight:600;margin-bottom:2px; }
.dval { font-size:.86rem;font-weight:600;color:#1e293b; }
.note-area { width:100%;padding:10px 12px;border:2px solid #e2e8f0;border-radius:10px;font-size:.84rem;color:#1e293b;font-family:inherit;resize:vertical;min-height:72px;outline:none;transition:border-color .2s;box-sizing:border-box; }
.note-area:focus { border-color:#10b981; }
.btn-full { flex:1;padding:11px;border-radius:10px;font-size:.88rem;font-weight:700;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;font-family:inherit;transition:all .2s; }
.btn-full.approve { background:linear-gradient(135deg,#059669,#10b981);color:#fff;box-shadow:0 4px 12px rgba(16,185,129,.3); }
.btn-full.approve:hover { transform:translateY(-1px); }
.btn-full.reject  { background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;box-shadow:0 4px 12px rgba(239,68,68,.3); }
.btn-full.reject:hover  { transform:translateY(-1px); }
.btn-full:disabled { opacity:.55;cursor:not-allowed;transform:none; }
.btn-full.cancel  { background:#f1f5f9;color:#475569;flex:0.4; }

/* ── TOAST ── */
.ip-toast { position:fixed;bottom:28px;right:28px;z-index:10000;background:#1e293b;color:#fff;border-radius:12px;padding:13px 20px;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:10px;box-shadow:0 8px 28px rgba(0,0,0,.25);transform:translateY(80px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1); }
.ip-toast.show { transform:translateY(0);opacity:1; }
.ip-toast.success i { color:#4ade80; }
.ip-toast.error i   { color:#f87171; }

/* ── Mobile responsive fixes ── */
@media(max-width:768px){
    /* Table has min-width:980px — wrap it for horizontal scroll */
    .fin-tbl-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }

    /* Search bar: full width on tablet */
    .ip-search-wrap { min-width:100%; }

    /* KPI numbers: scale down large currency on 2-col */
    .ip-kpi-num { font-size:1.15rem; }
    .ip-kpi { padding:14px 16px; }
}
@media(max-width:480px){
    /* KPI numbers further reduced for 320px */
    .ip-kpi-num { font-size:1rem; }
    .ip-kpi-lbl { font-size:.68rem; }
}

</style>
</head>
<body>
<div class="dashboard">
<?php include("../includes/sidebar.php"); ?>
<div class="main">
<?php include("../includes/topbar.php"); ?>
<div class="content-wrapper">

<!-- ══ PAGE HEADER ══════════════════════════════════════════ -->
<div class="fin-page-hdr">
  <div>
    <h1>
      <span class="fin-hdr-ico"><i class="fas fa-briefcase"></i></span>
      Internship Payments
    </h1>
    <p>Review, approve and manage all internship payment submissions</p>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:.82rem;font-weight:700;">
      <i class="fas fa-arrow-left"></i> Dashboard
    </a>
    <a href="?export=csv&filter=<?= urlencode($filter) ?>&q=<?= urlencode($search) ?>" class="ip-export-btn">
      <i class="fas fa-file-csv"></i> Export CSV
    </a>
  </div>
</div>

<!-- ══ KPI STATS ═══════════════════════════════════════════ -->
<div class="ip-kpi-row">
  <div class="ip-kpi green">
    <div class="ip-kpi-ico"><i class="fas fa-rupee-sign"></i></div>
    <div class="ip-kpi-num">₹<?= number_format($total_rev, 0) ?></div>
    <div class="ip-kpi-lbl">Total Revenue Collected</div>
  </div>
  <div class="ip-kpi amber">
    <div class="ip-kpi-ico"><i class="fas fa-hourglass-half"></i></div>
    <div class="ip-kpi-num">₹<?= number_format($pending_rev, 0) ?></div>
    <div class="ip-kpi-lbl"><?= $stats['pending'] ?> Pending · Unverified</div>
  </div>
  <div class="ip-kpi blue">
    <div class="ip-kpi-ico"><i class="fas fa-calendar-check"></i></div>
    <div class="ip-kpi-num">₹<?= number_format($month_rev, 0) ?></div>
    <div class="ip-kpi-lbl">This Month (<?= date('M Y') ?>)</div>
  </div>
  <div class="ip-kpi purple">
    <div class="ip-kpi-ico"><i class="fas fa-users"></i></div>
    <div class="ip-kpi-num"><?= $stats['all'] ?></div>
    <div class="ip-kpi-lbl"><?= $stats['all'] > 0 ? round(($stats['approved']/$stats['all'])*100) : 0 ?>% Approval Rate</div>
  </div>
</div>

<!-- ══ TABS ══════════════════════════════════════════════════ -->
<div class="fin-tabs">
  <?php
  $tab_defs = [
    'all'           => ['label'=>'All',          'icon'=>'fa-list'],
    'pending'       => ['label'=>'Pending',      'icon'=>'fa-clock'],
    'approved'      => ['label'=>'Approved',     'icon'=>'fa-check-circle'],
    'rejected'      => ['label'=>'Rejected',     'icon'=>'fa-times-circle'],
    'payment_later' => ['label'=>'Pay Later',    'icon'=>'fa-hourglass-half'],
  ];
  foreach ($tab_defs as $tab => $def):
    $isActive = ($filter === $tab);
  ?>
  <a href="?filter=<?= $tab ?>&q=<?= urlencode($search) ?>"
     class="fin-tab <?= $isActive ? 'active-'.$tab : '' ?>">
    <i class="fas <?= $def['icon'] ?>"></i>
    <?= $def['label'] ?>
    <span class="tab-count"><?= $stats[$tab] ?? 0 ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ══ TOOLBAR ════════════════════════════════════════════════ -->
<div class="ip-toolbar">
  <div class="ip-search-wrap">
    <i class="fas fa-search"></i>
    <form method="GET" style="margin:0">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input class="ip-search-inp" type="text" name="q"
             value="<?= htmlspecialchars($search) ?>"
             placeholder="Search by name, email, UTR, domain…"
             oninput="this.form.submit()">
    </form>
  </div>
  <div style="font-size:.78rem;color:#94a3b8;font-weight:600;white-space:nowrap;">
    <?= count($payments) ?> record<?= count($payments)!==1?'s':'' ?>
  </div>
</div>

<!-- ══ TABLE ═══════════════════════════════════════════════════ -->
<div class="fin-tbl-wrap">
  <div style="overflow-x:auto;">
    <table class="ip-tbl">
      <thead>
        <tr>
          <th>#</th><th>Student</th><th>Internship</th>
          <th>Amount</th><th>UTR No.</th><th>Terminal</th>
          <th>Submitted</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="9">
          <div class="ip-empty">
            <i class="fas fa-inbox"></i>
            <p style="font-weight:600;font-size:.9rem;">No payments found.</p>
            <p>Try adjusting your search or filter.</p>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td style="color:#cbd5e1;font-size:.72rem;font-weight:600;">#<?= $p['id'] ?></td>
          <td>
            <div class="td-name"><?= htmlspecialchars($p['full_name']) ?></div>
            <div class="td-sub"><?= htmlspecialchars($p['email']) ?></div>
            <div class="td-sub"><?= htmlspecialchars($p['mobile_number']) ?></div>
          </td>
          <td>
            <div style="font-weight:600;font-size:.84rem;"><?= htmlspecialchars($p['internship_chosen']) ?></div>
            <div class="td-sub"><?= htmlspecialchars($p['college_name']) ?></div>
          </td>
          <td class="td-amt">₹<?= number_format((float)$p['amount'], 2) ?></td>
          <td><span class="td-utr"><?= htmlspecialchars($p['utr_number']) ?></span></td>
          <td style="font-size:.78rem;color:#64748b;"><?= htmlspecialchars($p['terminal_name']) ?></td>
          <td style="font-size:.75rem;color:#64748b;white-space:nowrap;"><?php $dt=new DateTime($p['submitted_at'],new DateTimeZone('UTC'));$dt->setTimezone(new DateTimeZone('Asia/Kolkata'));echo $dt->format('d M y, h:i A'); ?></td>
          <td>
            <span class="ip-badge <?= $p['payment_status'] ?>">
              <?php if($p['payment_status']==='pending'): ?><i class="fas fa-clock"></i>
              <?php elseif($p['payment_status']==='approved'): ?><i class="fas fa-check"></i>
              <?php elseif($p['payment_status']==='payment_later'): ?><i class="fas fa-hourglass-half"></i>
              <?php else: ?><i class="fas fa-times"></i><?php endif; ?>
              <?= $p['payment_status']==='payment_later' ? 'Pay Later' : ucfirst($p['payment_status']) ?>
            </span>
          </td>
          <td>
            <div class="act-row">
              <button class="btn-sm btn-view" onclick="viewDetail(<?= $p['id'] ?>)">
                <i class="fas fa-eye"></i> View
              </button>
              <?php if ($p['payment_status'] === 'pending'): ?>
              <button class="btn-sm btn-approve" onclick="openAction(<?= $p['id'] ?>,'approve','<?= addslashes(htmlspecialchars($p['full_name'])) ?>')">
                <i class="fas fa-check"></i> Approve
              </button>
              <button class="btn-sm btn-reject" onclick="openAction(<?= $p['id'] ?>,'reject','<?= addslashes(htmlspecialchars($p['full_name'])) ?>')">
                <i class="fas fa-times"></i> Reject
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($payments)): ?>
  <div class="tbl-footer">
    <span><i class="fas fa-table" style="margin-right:5px;color:#10b981"></i>
      <?= count($payments) ?> record<?= count($payments)!==1?'s':'' ?>
      <?php if($filter!=='all') echo ' &nbsp;·&nbsp; Filtered: '.ucfirst($filter); ?>
      <?php if($search!=='') echo ' &nbsp;·&nbsp; Search: "'.htmlspecialchars($search).'"'; ?>
    </span>
    <?php if($filter==='approved'):
      $shown = array_sum(array_column(array_filter($payments, fn($x)=>$x['payment_status']==='approved'),'amount'));
    ?>
    <span style="color:#059669;font-weight:700;">Total: ₹<?= number_format($shown, 2) ?></span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

</div></div></div>

<!-- ══ DETAIL MODAL ════════════════════════════════════════════ -->
<div class="ip-modal-overlay" id="detailModal">
  <div class="ip-modal-box">
    <div class="ip-modal-head" style="background:linear-gradient(135deg,#0a2540,#059669);">
      <h2><i class="fas fa-receipt" style="margin-right:8px"></i> Payment Details</h2>
      <button class="ip-modal-close" onclick="closeModal('detailModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="ip-modal-body" id="detailBody">
      <div style="text-align:center;padding:30px;color:#94a3b8">
        <i class="fas fa-spinner fa-spin" style="font-size:1.5rem"></i>
        <p style="margin-top:10px">Loading…</p>
      </div>
    </div>
  </div>
</div>

<!-- ══ APPROVE / REJECT MODAL ══════════════════════════════════ -->
<div class="ip-modal-overlay" id="actionModal">
  <div class="ip-modal-box">
    <div class="ip-modal-head" id="actionHead">
      <h2 id="actionTitle"></h2>
      <button class="ip-modal-close" onclick="closeModal('actionModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="ip-modal-body">
      <p style="font-size:.88rem;color:#64748b;margin-bottom:14px;">
        You are about to <strong id="actionVerb"></strong> the payment for <strong id="actionName"></strong>.
      </p>
      <div style="margin-bottom:16px;">
        <label style="font-size:.73rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:6px;">
          Note / Reason
          <span style="font-weight:400;color:#94a3b8;">(optional for approve, recommended for reject)</span>
        </label>
        <textarea class="note-area" id="actionNote" placeholder="e.g. UTR verified via PhonePe dashboard…"></textarea>
      </div>
    </div>
    <div class="ip-modal-footer">
      <button class="btn-full" id="actionBtn" onclick="doAction()">Confirm</button>
      <button class="btn-full cancel" onclick="closeModal('actionModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- ══ TOAST ═══════════════════════════════════════════════════ -->
<div class="ip-toast" id="ipToast">
  <i class="fas fa-check-circle"></i>
  <span id="toastMsg">Done!</span>
</div>

<script>
var ALL_PAYMENTS = <?= json_encode($payments) ?>;
var curActionId  = 0;
var curAction    = '';

function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function showModal(id)  { document.getElementById(id).classList.add('show'); }

function viewDetail(id) {
    var p = ALL_PAYMENTS.find(function(x){ return x.id == id; });
    if (!p) return;
    var sBg    = {pending:'#fef3c7', approved:'#dcfce7', rejected:'#fee2e2'};
    var sColor = {pending:'#92400e', approved:'#14532d', rejected:'#7f1d1d'};
    var sIcon  = {pending:'⏳', approved:'✅', rejected:'❌'};
    document.getElementById('detailBody').innerHTML =
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">' +
          '<div style="background:#f0fdf4;border-radius:12px;padding:14px;">' +
            '<div style="font-size:.63rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;">Payment Ref</div>' +
            '<div style="font-weight:800;color:#059669;font-size:.95rem;">#VSI-PAY-' + String(p.id).padStart(4,'0') + '</div>' +
          '</div>' +
          '<div style="background:'+sBg[p.payment_status]+';border-radius:12px;padding:14px;">' +
            '<div style="font-size:.63rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;">Status</div>' +
            '<div style="font-weight:700;color:'+sColor[p.payment_status]+';font-size:.88rem;">' + sIcon[p.payment_status] + ' ' + p.payment_status.charAt(0).toUpperCase() + p.payment_status.slice(1) + '</div>' +
          '</div>' +
        '</div>' +
        drow('fas fa-user',         'Student Name',      p.full_name) +
        drow('fas fa-envelope',     'Email',             p.email) +
        drow('fas fa-phone',        'Mobile',            p.mobile_number) +
        drow('fas fa-briefcase',    'Internship Domain', p.internship_chosen) +
        drow('fas fa-university',   'College',           p.college_name) +
        drow('fas fa-id-card',      'Reg. Number',       p.registration_number) +
        drow('fas fa-code-branch',  'Department',        p.department) +
        drow('fas fa-tag',          'Admission Type',    p.admission_type || '—') +
        drow('fas fa-receipt',      'UTR Number',        '<code style="font-family:monospace;background:#f0fdf4;color:#059669;padding:2px 8px;border-radius:6px;border:1px solid #bbf7d0;">' + p.utr_number + '</code>') +
        drow('fas fa-store',        'Terminal',          (p.terminal_name||'—') + (p.terminal_upi ? ' &nbsp;<span style="font-family:monospace;font-size:.76rem;color:#059669;">'+p.terminal_upi+'</span>':'')) +
        drow('fas fa-rupee-sign',   'Amount Paid',       '<strong style="color:#059669;font-size:1.05rem;">₹' + parseFloat(p.amount).toLocaleString('en-IN',{minimumFractionDigits:2}) + '</strong>') +
        drow('fas fa-calendar-alt', 'Submitted At',      p.submitted_at || '—') +
        (p.reviewed_at ? drow('fas fa-check-double','Reviewed At', p.reviewed_at) : '') +
        (p.admin_note  ? drow('fas fa-comment',    'Admin Note',  '<em style="color:#64748b;">' + p.admin_note + '</em>') : '');
    showModal('detailModal');
}

function drow(ico, lbl, val) {
    return '<div class="drow"><div class="dico"><i class="'+ico+'"></i></div><div><div class="dlbl">'+lbl+'</div><div class="dval">'+val+'</div></div></div>';
}

function openAction(id, action, name) {
    curActionId = id; curAction = action;
    document.getElementById('actionTitle').textContent = action === 'approve' ? '✅ Approve Payment' : '❌ Reject Payment';
    document.getElementById('actionHead').style.background = action === 'approve'
        ? 'linear-gradient(135deg,#059669,#10b981)'
        : 'linear-gradient(135deg,#dc2626,#ef4444)';
    document.getElementById('actionVerb').textContent = action === 'approve' ? 'APPROVE' : 'REJECT';
    document.getElementById('actionName').textContent = name;
    document.getElementById('actionNote').value = '';
    var btn = document.getElementById('actionBtn');
    btn.className = 'btn-full ' + (action === 'approve' ? 'approve' : 'reject');
    btn.innerHTML = action === 'approve'
        ? '<i class="fas fa-check"></i> Approve &amp; Send Email'
        : '<i class="fas fa-times"></i> Reject &amp; Notify Student';
    btn.disabled = false;
    showModal('actionModal');
}

function doAction() {
    var btn  = document.getElementById('actionBtn');
    var note = document.getElementById('actionNote').value.trim();
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';

    var fd = new FormData();
    fd.append('action', curAction);
    fd.append('payment_id', curActionId);
    fd.append('note', note);

    fetch('', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(data) {
          closeModal('actionModal');
          if (data.success) {
              showToast(
                curAction === 'approve'
                  ? '✅ Payment approved! Enrollment email sent.'
                  : '❌ Payment rejected. Student notified.',
                true
              );
              setTimeout(function(){ location.reload(); }, 1800);
          } else {
              showToast(data.message || 'Something went wrong.', false);
              btn.disabled = false;
          }
      })
      .catch(function() {
          showToast('Network error. Please try again.', false);
          btn.disabled = false;
      });
}

function showToast(msg, success) {
    var t = document.getElementById('ipToast');
    document.getElementById('toastMsg').textContent = msg;
    t.className = 'ip-toast show ' + (success ? 'success' : 'error');
    t.querySelector('i').className = success ? 'fas fa-check-circle' : 'fas fa-times-circle';
    setTimeout(function(){ t.classList.remove('show'); }, 3500);
}

document.querySelectorAll('.ip-modal-overlay').forEach(function(o){
    o.addEventListener('click', function(e){ if(e.target===this) closeModal(this.id); });
});
document.addEventListener('keydown', function(e){
    if(e.key==='Escape') document.querySelectorAll('.ip-modal-overlay.show').forEach(function(m){ closeModal(m.id); });
});

<?php if ($detail_id > 0): ?>
window.addEventListener('DOMContentLoaded', function(){ viewDetail(<?= $detail_id ?>); });
<?php endif; ?>
</script>

<?php include("../includes/footer.php"); ?>
</body>
</html>