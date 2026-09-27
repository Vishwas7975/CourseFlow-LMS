<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
include_once("../includes/mailer.php");

requireLogin($conn);
requireRole("admin");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: project_orders.php"); exit();
}

$id   = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
$note = isset($_POST['admin_note']) ? trim(substr($_POST['admin_note'], 0, 500)) : '';

if ($id <= 0) {
    header("Location: project_orders.php?msg=error"); exit();
}

// Fetch order details before updating so we have student info for email
$fetch = $conn->prepare("
    SELECT o.*, p.title AS project_title
    FROM project_orders o
    LEFT JOIN final_year_projects p ON p.id = o.project_id
    WHERE o.id = ?
");
$fetch->bind_param("i", $id);
$fetch->execute();
$order = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$order) {
    header("Location: project_orders.php?msg=error"); exit();
}

// Update status
$stmt = $conn->prepare("UPDATE project_orders SET payment_status='rejected', admin_note=?, reviewed_at=NOW() WHERE id=?");
$stmt->bind_param("si", $note, $id);
$stmt->execute();
$stmt->close();

// BUG FIX: Send rejection email to student with reason
if (function_exists('vs_send_mail') && !empty($order['email'])) {
    $name    = htmlspecialchars($order['student_name']);
    $email   = $order['email'];
    $project = htmlspecialchars($order['project_title'] ?? 'Your Project');
    $utr     = htmlspecialchars($order['utr_number']);
    $reason  = !empty($note) ? htmlspecialchars($note) : 'The UTR number could not be verified. Please ensure you entered the correct transaction reference.';

    $subject = "Order Update: Payment Could Not Be Verified — $project";
    $body = <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:linear-gradient(135deg,#7f1d1d,#dc2626);border-radius:16px 16px 0 0;padding:40px 40px 32px;text-align:center;">
    <div style="font-size:2.2rem;margin-bottom:12px;">⚠️</div>
    <div style="font-size:1.5rem;font-weight:800;color:#fff;margin-bottom:6px;">Payment Verification Failed</div>
    <div style="font-size:.88rem;color:rgba(255,255,255,.75);">We were unable to verify your payment</div>
  </td></tr>
  <tr><td style="background:#fff;padding:32px 40px;">
    <p style="font-size:1.05rem;font-weight:700;color:#1e293b;margin:0 0 8px;">Hi $name,</p>
    <p style="font-size:.9rem;color:#64748b;margin:0 0 20px;line-height:1.7;">Unfortunately, we were unable to verify your payment for <strong>$project</strong>. Please review the details below and contact us so we can resolve this quickly.</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:20px;">
      <tr style="background:#f8fafc;"><td colspan="2" style="padding:12px 16px;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.08em;">Order Details</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Project</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$project</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">UTR Submitted</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;font-family:monospace;">$utr</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Status</td><td style="padding:10px 16px;border-top:1px solid #f1f5f9;"><span style="background:#fee2e2;color:#b91c1c;font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:20px;">❌ Rejected</span></td></tr>
    </table>
    <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:12px;padding:16px 18px;margin-bottom:24px;">
      <p style="font-size:.84rem;font-weight:700;color:#b91c1c;margin:0 0 6px;">Reason from Admin:</p>
      <p style="font-size:.84rem;color:#7f1d1d;margin:0;line-height:1.6;">$reason</p>
    </div>
    <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:12px;padding:16px 18px;margin-bottom:24px;">
      <p style="font-size:.88rem;font-weight:700;color:#1e3a8a;margin:0 0 8px;">🔄 What to do next?</p>
      <p style="font-size:.84rem;color:#1e40af;margin:0;line-height:1.7;">If you believe this is an error or your payment was successful, please contact us immediately at <a href="mailto:support@yourdomain.com" style="color:#2563eb;font-weight:700;">support@yourdomain.com</a> with your correct UTR number and a screenshot of your payment. You can also resubmit your order at <a href="https://yourdomain.com/projects.php" style="color:#2563eb;font-weight:700;">yourdomain.com/projects.php</a>.</p>
    </div>
  </td></tr>
  <tr><td style="background:#f8fafc;border-radius:0 0 16px 16px;padding:18px 40px;text-align:center;font-size:.78rem;color:#94a3b8;">
    CourseFlow · <a href="mailto:support@yourdomain.com" style="color:#3b82f6;">support@yourdomain.com</a>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
    vs_send_mail($email, $name, $subject, $body);
}

header("Location: project_orders.php?filter=rejected&msg=rejected");
exit();