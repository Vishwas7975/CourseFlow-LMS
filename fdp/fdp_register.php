<?php
/**
 * fdp_register.php — CourseFlow
 * Handles Faculty Development Program registration form submission.
 * Pattern: matches internship_register.php exactly.
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $data): void {
    ob_end_clean();
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['success' => false, 'message' => 'Method not allowed.']);
}

/* ── DB connection ── */
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
if (!isset($conn)) {
    jsonOut(['success' => false, 'message' => 'DB config not found.']);
}

/* ── Collect & Sanitize ── */
$name         = trim($_POST['name']         ?? '');
$email        = strtolower(trim($_POST['email']        ?? ''));
$whatsapp     = preg_replace('/[^0-9]/', '', trim($_POST['whatsapp'] ?? ''));
$designation  = trim($_POST['designation']  ?? '');
$organisation = trim($_POST['organisation'] ?? '');
$college      = strtoupper(trim($_POST['college']      ?? ''));
$department   = trim($_POST['department']   ?? '');
$utr_number   = strtoupper(trim($_POST['utr_number']   ?? ''));

/* ── Validation ── */
if (!$name || !$email || !$whatsapp || !$designation || !$organisation || !$college || !$department || !$utr_number) {
    jsonOut(['success' => false, 'message' => 'Please fill all required fields.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(['success' => false, 'message' => 'Please enter a valid email address.']);
}
if (strlen($whatsapp) < 10) {
    jsonOut(['success' => false, 'message' => 'Please enter a valid WhatsApp number.']);
}

/* ── Duplicate Check (by email) ── */
$dup = $conn->prepare("SELECT id FROM fdp_registrations WHERE email = ? LIMIT 1");
$dup->bind_param("s", $email);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    $dup->close();
    jsonOut(['success' => false, 'message' => 'This email is already registered for the FDP. Check your inbox for confirmation.']);
}
$dup->close();

/* ── Insert ── */
$stmt = $conn->prepare(
    "INSERT INTO fdp_registrations (name, email, whatsapp, designation, organisation, college, department, utr_number)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("ssssssss", $name, $email, $whatsapp, $designation, $organisation, $college, $department, $utr_number);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    jsonOut(['success' => false, 'message' => 'Registration failed: ' . $err]);
}
$reg_id = $conn->insert_id;
$stmt->close();

/* ── Confirmation Email to Participant ── */
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/mailer.php';

$subject = "Registration Confirmed — Faculty Development Program June 15–22, 2026 | CourseFlow";
$body = "
<div style='font-family:Arial,sans-serif;background:#f4f4f7;padding:40px 0;'>
  <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,.05);'>

    <div style='text-align:center;padding:30px 20px;background:#fff;'>
      <img src='https://yourdomain.com/assets/images/logo.jpeg' alt='CourseFlow' style='max-width:160px;'>
    </div>

    <div style='padding:30px 25px;color:#333;'>
      <h2 style='margin-top:0;'>Registration Confirmed, {$name}! 🎓</h2>
      <p style='font-size:15px;line-height:1.6;'>
        Thank you for registering for the <b>Faculty Development Program (June 15–22, 2026)</b> organized by CourseFlow.
        Your registration has been received successfully.
      </p>

      <div style='background:#f8fafc;padding:15px;border-radius:8px;border:1px solid #e2e8f0;margin:20px 0;font-size:14px;line-height:2;'>
        <p style='margin:0;'><b>Registration ID:</b> FDP-{$reg_id}</p>
        <p style='margin:0;'><b>Name:</b> {$name}</p>
        <p style='margin:0;'><b>Email:</b> {$email}</p>
        <p style='margin:0;'><b>WhatsApp:</b> {$whatsapp}</p>
        <p style='margin:0;'><b>Designation:</b> {$designation}</p>
        <p style='margin:0;'><b>Organisation:</b> {$organisation}</p>
        <p style='margin:0;'><b>College:</b> {$college}</p>
        <p style='margin:0;'><b>Department:</b> {$department}</p>
        <p style='margin:0;'><b>UTR / Txn Ref:</b> {$utr_number}</p>
        <p style='margin:0;'><b>Fee Paid:</b> ₹1,000</p>
      </div>

      <p style='font-size:14px;color:#555;'>
        Our team will verify your payment within 24 hours and send further program details to this email.
        If you have any questions, contact us at <a href='mailto:info@yourdomain.com' style='color:#2448c8;'>info@yourdomain.com</a>.
      </p>

      <p style='margin-top:30px;font-size:14px;'>
        Best regards,<br>
        <b>CourseFlow Team</b>
      </p>
    </div>

    <div style='background:#f4f4f7;text-align:center;padding:20px;font-size:12px;color:#888;'>
      © " . date('Y') . " CourseFlow. All rights reserved.<br>
      This is an automated email — please do not reply.
    </div>
  </div>
</div>
";

vs_send_mail($email, $name, $subject, $body);

/* ── Admin Notification ── */
$notif_title   = 'New FDP Registration: ' . $name;
$notif_message = "Designation: {$designation} | College: {$college} | UTR: {$utr_number}";
$ns = $conn->prepare("INSERT INTO notifications (type, for_role, title, message, is_read) VALUES ('new_registration','admin',?,?,0)");
if ($ns) {
    $ns->bind_param('ss', $notif_title, $notif_message);
    $ns->execute();
    $ns->close();
}

/* ── Success Response ── */
jsonOut([
    'success' => true,
    'reg_id'  => $reg_id,
    'name'    => $name,
    'message' => 'Registration successful! A confirmation has been sent to your email.'
]);