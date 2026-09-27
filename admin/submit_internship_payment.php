<?php
/**
 * admin/submit_internship_payment.php — CourseFlow
 * Handles internship payment form submission from admin side.
 * Validates UTR → inserts into internship_payments → notifies admin → redirect.
 *
 * FIX: Now uses vs_send_mail() via SMTP (mailer.php) instead of @mail()
 * FIX: All email addresses corrected to @yourdomain.com domain
 */

session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/mailer.php';   // ← SMTP mailer

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /internship/internship_registration.html'); exit;
}

function fail(int $reg_id, string $code): void {
    header("Location: /internship/internship_payment.php?reg_id={$reg_id}&error={$code}"); exit;
}

$reg_id        = isset($_POST['reg_id']) ? (int)$_POST['reg_id'] : 0;
$amount        = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$terminal_name = trim(strip_tags($_POST['terminal_name'] ?? 'Terminal 3'));
$terminal_upi  = trim(strip_tags($_POST['terminal_upi']  ?? ''));
$utr_raw       = trim($_POST['utr_number'] ?? '');

/* ── Validate reg_id ── */
if ($reg_id <= 0) {
    header('Location: /internship/internship_registration.html'); exit;
}

/* ── Load registration ── */
$stmt = $conn->prepare("SELECT * FROM internship_registrations WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $reg_id); $stmt->execute();
$reg = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$reg) fail($reg_id, 'invalid_input');

/* ── Validate UTR: 6-50 alphanumeric only ── */
if (!preg_match('/^[A-Za-z0-9]{6,50}$/', $utr_raw)) {
    fail($reg_id, 'invalid_utr');
}
$utr = $utr_raw;

/* ── Duplicate UTR check ── */
$dup = $conn->prepare("SELECT id FROM internship_payments WHERE utr_number = ? LIMIT 1");
$dup->bind_param("s", $utr); $dup->execute(); $dup->store_result();
if ($dup->num_rows > 0) { $dup->close(); fail($reg_id, 'duplicate_utr'); }
$dup->close();

/* ── Duplicate pending payment check ── */
$dp = $conn->prepare("SELECT id FROM internship_payments WHERE reg_id = ? AND payment_status IN ('pending','approved') LIMIT 1");
$dp->bind_param("i", $reg_id); $dp->execute(); $dp->store_result();
if ($dp->num_rows > 0) { $dp->close(); fail($reg_id, 'duplicate_utr'); }
$dp->close();

/* ── Insert payment record ── */
$stmt2 = $conn->prepare("
    INSERT INTO internship_payments
        (reg_id, full_name, email, mobile_number, internship_chosen,
         utr_number, terminal_name, terminal_upi, amount, payment_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");
if (!$stmt2) fail($reg_id, 'db_error');

$stmt2->bind_param("isssssssd",
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
if (!$stmt2->execute()) { $stmt2->close(); fail($reg_id, 'db_error'); }
$payment_id = $conn->insert_id;
$stmt2->close();

/* ── Admin notification ── */
$notif_title   = 'New Internship Payment';
$notif_message = $reg['full_name'] . ' submitted payment for ' . $reg['internship_chosen']
               . ' internship. UTR: ' . $utr . '. Amount: ₹' . number_format($amount, 2)
               . '. Terminal: ' . $terminal_name . '.';
$notif_link    = '/admin/admin_internship_payments.php?id=' . $payment_id;
$notif_role    = 'admin';

$ns = $conn->prepare("INSERT INTO notifications (type, for_role, for_user_id, title, message, link, is_read) VALUES ('internship_payment', ?, NULL, ?, ?, ?, 0)");
if ($ns) {
    $ns->bind_param('ssss', $notif_role, $notif_title, $notif_message, $notif_link);
    $ns->execute(); $ns->close();
}

/* ── Send student confirmation email via SMTP ── */
send_student_confirmation($reg, $utr, $amount, $payment_id);

/* ── Redirect to pending screen ── */
header("Location: /internship/internship_payment.php?reg_id={$reg_id}"); exit;

/* ════════════════════════════════════════ */
function send_student_confirmation(array $reg, string $utr, float $amount, int $payment_id): void {
    $to      = $reg['email'];
    $name    = $reg['full_name'];
    $intern  = $reg['internship_chosen'];
    $reg_num = $reg['registration_number'];
    $mobile  = $reg['mobile_number'];
    $college = $reg['college_name'] ?? '';
    $pid_str = '#VSI-PAY-' . str_pad($payment_id, 4, '0', STR_PAD_LEFT);
    $amtFmt  = '₹' . number_format($amount, 2);
    $date    = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('d M Y, h:i A');

    $subject = "Payment Received — CourseFlow Internship [{$intern}]";

    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:'Plus Jakarta Sans',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <!-- Header -->
  <tr><td style="background:linear-gradient(135deg,#0f1b3d,#2448c8);border-radius:16px 16px 0 0;padding:36px 40px 28px;text-align:center;">
    <div style="font-size:1.6rem;font-weight:800;color:#fff;letter-spacing:-.02em;">CourseFlow</div>
    <div style="font-size:.78rem;color:rgba(255,255,255,.6);letter-spacing:.08em;text-transform:uppercase;margin-top:4px;">Technologies Private Limited</div>
    <div style="margin-top:18px;display:inline-block;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);border-radius:40px;padding:6px 18px;font-size:.78rem;color:rgba(255,255,255,.9);font-weight:600;">
      ⏳ Payment Under Review
    </div>
  </td></tr>

  <!-- Body -->
  <tr><td style="background:#ffffff;padding:36px 40px;">
    <p style="font-size:1.05rem;font-weight:700;color:#1e293b;margin:0 0 6px;">Hi {$name},</p>
    <p style="font-size:.9rem;color:#64748b;margin:0 0 24px;line-height:1.7;">Thank you for submitting your internship payment! We've received your payment details and our team will verify and approve it within <strong>24 hours</strong>.</p>

    <!-- Summary Box -->
    <div style="background:#f0f5ff;border:1.5px solid #bfdbfe;border-radius:14px;padding:20px 24px;margin-bottom:24px;">
      <div style="font-size:.7rem;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.1em;margin-bottom:14px;">📋 Payment Summary</div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Payment ID</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$pid_str}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Internship</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$intern}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Reg. Number</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$reg_num}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">UTR Number</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;font-family:monospace;">{$utr}</td></tr>
        <tr style="border-top:1.5px dashed #bfdbfe;"><td colspan="2" style="padding:4px 0;"></td></tr>
        <tr><td style="font-size:.88rem;font-weight:700;color:#1e40af;padding:5px 0;">Amount Paid</td><td style="font-size:.95rem;font-weight:800;color:#1e40af;text-align:right;">{$amtFmt}</td></tr>
      </table>
    </div>

    <!-- What happens next -->
    <div style="margin-bottom:24px;">
      <div style="font-size:.78rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px;">What happens next?</div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="padding:10px 12px;background:#f8faff;border-radius:10px;font-size:.84rem;color:#475569;line-height:1.6;margin-bottom:8px;">
          <strong>1. Admin Verification</strong> — Our team verifies your UTR within 24 hours.</td></tr>
        <tr><td style="padding:6px 0;"></td></tr>
        <tr><td style="padding:10px 12px;background:#f8faff;border-radius:10px;font-size:.84rem;color:#475569;line-height:1.6;margin-bottom:8px;">
          <strong>2. Approval &amp; Enrollment</strong> — Once approved, you'll receive a confirmation email with your schedule and joining link.</td></tr>
        <tr><td style="padding:6px 0;"></td></tr>
        <tr><td style="padding:10px 12px;background:#f0fdf4;border-radius:10px;font-size:.84rem;color:#475569;line-height:1.6;">
          <strong>3. Start Internship</strong> — Join the {$intern} track and build real projects with industry mentors.</td></tr>
      </table>
    </div>

    <p style="font-size:.82rem;color:#94a3b8;line-height:1.6;margin:0;">If you have any questions, reply to this email or contact us on WhatsApp. Please keep your UTR number <strong>{$utr}</strong> safe for reference.</p>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#0f1b3d;border-radius:0 0 16px 16px;padding:22px 40px;text-align:center;">
    <div style="font-size:.78rem;color:rgba(255,255,255,.5);">&copy; 2025 CourseFlow Pvt. Ltd.</div>
    <div style="font-size:.72rem;color:rgba(255,255,255,.3);margin-top:4px;">
      <a href="mailto:support@yourdomain.com" style="color:rgba(255,255,255,.4);text-decoration:none;">support@yourdomain.com</a>
    </div>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

    // Send via SMTP
    vs_send_mail($to, $name, $subject, $body, 'support@yourdomain.com');
}