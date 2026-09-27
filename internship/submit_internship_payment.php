<?php
/**
 * submit_internship_payment.php — CourseFlow
 * Handles POST from internship_payment.php
 * Saves payment record + sends confirmation email to student + admin notification
 *
 * FIX: Now uses vs_send_mail() via SMTP (mailer.php) instead of @mail()
 * FIX: All email addresses corrected to @yourdomain.com domain
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/mailer.php';   // ← SMTP mailer

/* ── Helpers ── */
function fail(string $code, int $reg_id): never {
    header('Location: internship_payment.php?reg_id='.$reg_id.'&error='.$code);
    exit;
}

/* ── Validate POST ── */
$reg_id        = isset($_POST['reg_id'])        ? (int)$_POST['reg_id']                            : 0;
$amount        = isset($_POST['amount'])        ? (float)$_POST['amount']                          : 0;
$terminal_name = isset($_POST['terminal_name']) ? trim(htmlspecialchars($_POST['terminal_name']))   : '';
$terminal_upi  = isset($_POST['terminal_upi'])  ? trim($_POST['terminal_upi'])                      : '';
$utr_raw       = isset($_POST['utr_number'])    ? trim($_POST['utr_number'])                        : '';

if (!$reg_id || !$amount || !$utr_raw) fail('db_error', $reg_id);

/* Sanitise UTR — alphanumeric only, 6-50 chars */
$utr = preg_replace('/[^A-Za-z0-9]/', '', $utr_raw);
if (strlen($utr) < 6 || strlen($utr) > 50) fail('invalid_utr', $reg_id);

/* ── Load registration ── */
$stmt = $conn->prepare("SELECT * FROM internship_registrations WHERE id=? LIMIT 1");
$stmt->bind_param("i", $reg_id);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$reg) fail('db_error', $reg_id);

/* ── Check if there's a payment_later placeholder for this reg ── */
$stmt_pl = $conn->prepare("SELECT id FROM internship_payments WHERE reg_id=? AND payment_status='payment_later' LIMIT 1");
$stmt_pl->bind_param("i", $reg_id);
$stmt_pl->execute();
$pay_later_row = $stmt_pl->get_result()->fetch_assoc();
$stmt_pl->close();

/* ── Check duplicate UTR (skip PENDING placeholder) ── */
$stmt2 = $conn->prepare("SELECT id FROM internship_payments WHERE utr_number=? AND utr_number != 'PENDING' LIMIT 1");
$stmt2->bind_param("s", $utr);
$stmt2->execute();
$dup = $stmt2->get_result()->fetch_assoc();
$stmt2->close();
if ($dup) fail('duplicate_utr', $reg_id);

/* ── Delete any previously rejected entry for this reg ── */
$del = $conn->prepare("DELETE FROM internship_payments WHERE reg_id=? AND payment_status='rejected'");
$del->bind_param("i", $reg_id);
$del->execute();
$del->close();

/* ── Update payment_later placeholder OR insert new payment record ── */
if ($pay_later_row) {
    $stmt3 = $conn->prepare("
        UPDATE internship_payments
        SET utr_number=?, terminal_name=?, terminal_upi=?, amount=?, payment_status='pending', submitted_at=NOW()
        WHERE id=?
    ");
    $stmt3->bind_param("sssdi", $utr, $terminal_name, $terminal_upi, $amount, $pay_later_row['id']);
} else {
    $stmt3 = $conn->prepare("
        INSERT INTO internship_payments
            (reg_id, full_name, email, mobile_number, internship_chosen,
             utr_number, terminal_name, terminal_upi, amount, payment_status)
        VALUES (?,?,?,?,?,?,?,?,?,'pending')
    ");
    $stmt3->bind_param(
        "isssssssd",
        $reg_id,
        $reg['full_name'],
        $reg['email'],
        $reg['mobile_number'],
        $reg['internship_chosen'],
        $utr,
        $terminal_name,
        $terminal_upi,
        $amount
    );
}
if (!$stmt3->execute()) fail('db_error', $reg_id);
$stmt3->close();

/* ── Insert in-app admin notification ── */
$notif_title   = 'New Internship Payment Submitted';
$notif_message = $reg['full_name'] . ' submitted payment for '
               . $reg['internship_chosen'] . ' internship.'
               . ' UTR: ' . strtoupper($utr)
               . '. Amount: ₹' . number_format($amount, 2)
               . '. Reg No: ' . $reg['registration_number']
               . '. Mobile: ' . $reg['mobile_number'] . '.';
$notif_link    = '/admin/admin_internship_payments.php?filter=pending';
$notif_role    = 'admin';

$ns = $conn->prepare("
    INSERT INTO notifications (type, for_role, for_user_id, title, message, link, is_read)
    VALUES ('internship_payment', ?, NULL, ?, ?, ?, 0)
");
if ($ns) {
    $ns->bind_param('ssss', $notif_role, $notif_title, $notif_message, $notif_link);
    $ns->execute();
    $ns->close();
}

/* ══════════════════════════════════════════════
   SEND CONFIRMATION EMAIL TO STUDENT (via SMTP)
   ══════════════════════════════════════════════ */
$to      = $reg['email'];
$name    = $reg['full_name'];
$intern  = $reg['internship_chosen'];
$mobile  = $reg['mobile_number'];
$college = $reg['college_name'];
$regnum  = $reg['registration_number'];
$amtFmt  = '₹' . number_format($amount, 2);
$utrDisp = strtoupper($utr);
$date    = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('d M Y, h:i A');

$subject = "✅ Payment Received — CourseFlow Internship";

$body = '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body{margin:0;padding:0;background:#f3f6fb;font-family:\'Segoe UI\',Arial,sans-serif;}
  .wrap{max-width:580px;margin:30px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);}
  .hdr{background:linear-gradient(135deg,#1e40af,#3b82f6);padding:32px 28px;text-align:center;}
  .hdr h1{color:#fff;margin:0;font-size:1.4rem;font-weight:800;}
  .hdr p{color:rgba(255,255,255,.8);margin:6px 0 0;font-size:.88rem;}
  .body{padding:28px 28px 10px;}
  .hi{font-size:1rem;color:#1e293b;margin:0 0 18px;font-weight:600;}
  .status-box{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:12px;padding:16px 18px;margin-bottom:22px;}
  .status-box h3{margin:0 0 3px;color:#14532d;font-size:.95rem;}
  .status-box p{margin:0;font-size:.82rem;color:#166534;}
  table{width:100%;border-collapse:collapse;margin-bottom:22px;}
  td{padding:10px 12px;font-size:.84rem;border-bottom:1px solid #f1f5f9;}
  td:first-child{color:#64748b;font-weight:600;width:42%;}
  td:last-child{color:#1e293b;font-weight:700;}
  .utr-cell{font-family:monospace;font-size:.9rem;color:#1e40af;background:#eff6ff;padding:6px 10px;border-radius:7px;display:inline-block;}
  .note{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:13px 15px;font-size:.8rem;color:#92400e;margin-bottom:22px;line-height:1.55;}
  .ftr{background:#f8fafc;border-top:1px solid #e2e8f0;padding:18px 28px;text-align:center;font-size:.75rem;color:#94a3b8;}
  .ftr a{color:#3b82f6;text-decoration:none;}
</style></head><body>
<div class="wrap">
  <div class="hdr">
    <h1>🎓 CourseFlow — Internship</h1>
    <p>Payment Confirmation</p>
  </div>
  <div class="body">
    <p class="hi">Dear '.$name.',</p>
    <div class="status-box">
      <h3>✅ Payment Received Successfully!</h3>
      <p>Your internship payment has been received and is under review. You will be notified once approved.</p>
    </div>
    <table>
      <tr><td>Student Name</td><td>'.$name.'</td></tr>
      <tr><td>Email</td><td>'.$to.'</td></tr>
      <tr><td>Mobile</td><td>'.$mobile.'</td></tr>
      <tr><td>College</td><td>'.$college.'</td></tr>
      <tr><td>Reg. Number</td><td>'.$regnum.'</td></tr>
      <tr><td>Internship</td><td>'.$intern.'</td></tr>
      <tr><td>Amount Paid</td><td>'.$amtFmt.'</td></tr>
      <tr><td>UTR / Txn ID</td><td><span class="utr-cell">'.$utrDisp.'</span></td></tr>
      <tr><td>Terminal</td><td>'.$terminal_name.' ('.$terminal_upi.')</td></tr>
      <tr><td>Submitted At</td><td>'.$date.'</td></tr>
      <tr><td>Status</td><td style="color:#d97706;">⏳ Pending Approval</td></tr>
    </table>
    <div class="note">
      <strong>⚠ Important:</strong> This email confirms we received your payment details.
      Your enrollment will be <strong>confirmed only after admin approval</strong>,
      which typically happens within <strong>24 hours</strong>.
      You will receive a separate approval email once verified.
      <br><br>
      If you have any questions, reply to this email or WhatsApp us with your UTR: <strong>'.$utrDisp.'</strong>
    </div>
  </div>
  <div class="ftr">
    &copy; '.date('Y').' CourseFlow Private Limited<br>
    <a href="mailto:support@yourdomain.com">support@yourdomain.com</a>
  </div>
</div>
</body></html>';

/* Send to student via SMTP */
vs_send_mail($to, $name, $subject, $body, 'support@yourdomain.com');

/* ── Admin notification email via SMTP ── */
$adminSubject = "[Internship] Payment Submitted — {$name} | UTR: {$utrDisp}";
$adminBody    = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#1e293b;">
<h3 style="color:#1e40af;">New Internship Payment Submitted</h3>
<table style="border-collapse:collapse;font-size:.9rem;">
  <tr><td style="padding:6px 12px 6px 0;color:#64748b;">Name</td><td style="padding:6px 0;font-weight:700;">'.$name.'</td></tr>
  <tr><td style="padding:6px 12px 6px 0;color:#64748b;">Email</td><td style="padding:6px 0;">'.$to.'</td></tr>
  <tr><td style="padding:6px 12px 6px 0;color:#64748b;">Mobile</td><td style="padding:6px 0;">'.$mobile.'</td></tr>
  <tr><td style="padding:6px 12px 6px 0;color:#64748b;">College</td><td style="padding:6px 0;">'.$college.'</td></tr>
  <tr><td style="padding:6px 12px 6px 0;color:#64748b;">Reg No.</td><td style="padding:6px 0;">'.$regnum.'</td></tr>
  <tr><td style="padding:6px 12px 6px 0;color:#64748b;">Internship</td><td style="padding:6px 0;">'.$intern.'</td></tr>
  <tr><td style="padding:6px 12px 6px 0;color:#64748b;">Amount</td><td style="padding:6px 0;font-weight:700;color:#059669;">'.$amtFmt.'</td></tr>
  <tr><td style="padding:6px 12px 6px 0;color:#64748b;">UTR</td><td style="padding:6px 0;font-family:monospace;">'.$utrDisp.'</td></tr>
  <tr><td style="padding:6px 12px 6px 0;color:#64748b;">Terminal</td><td style="padding:6px 0;">'.$terminal_name.' ('.$terminal_upi.')</td></tr>
  <tr><td style="padding:6px 12px 6px 0;color:#64748b;">Time</td><td style="padding:6px 0;">'.$date.'</td></tr>
</table>
<p style="margin-top:20px;">
  <a href="https://yourdomain.com/admin/admin_internship_payments.php?filter=pending"
     style="display:inline-block;padding:10px 24px;background:#1e40af;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">
    Review in Admin Panel →
  </a>
</p>
</body></html>';

vs_send_mail('noreply@yourdomain.com', 'CourseFlow Admin', $adminSubject, $adminBody, $to);

/* ── Redirect to success view ── */
header('Location: internship_payment.php?reg_id='.$reg_id);
exit;