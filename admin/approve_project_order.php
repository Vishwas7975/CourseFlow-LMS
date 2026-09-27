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

$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header("Location: project_orders.php?msg=error"); exit();
}

// Fetch order + project details before updating so we have student info for email
$fetch = $conn->prepare("
    SELECT o.*, p.title AS project_title, p.domain AS project_domain
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
$stmt = $conn->prepare("UPDATE project_orders SET payment_status='approved', reviewed_at=NOW() WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// BUG FIX: Send approval email to student
if (function_exists('vs_send_mail') && !empty($order['email'])) {
    $name    = htmlspecialchars($order['student_name']);
    $email   = $order['email'];
    $project = htmlspecialchars($order['project_title'] ?? 'Your Project');
    $amount  = number_format((float)$order['amount'], 0);
    $utr     = htmlspecialchars($order['utr_number']);

    $subject = "Project Approved & Being Delivered — $project";
    $body = <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:linear-gradient(135deg,#14532d,#16a34a);border-radius:16px 16px 0 0;padding:40px 40px 32px;text-align:center;">
    <div style="font-size:2.5rem;margin-bottom:12px;">🎉</div>
    <div style="font-size:1.6rem;font-weight:800;color:#fff;margin-bottom:6px;">Payment Approved!</div>
    <div style="font-size:.9rem;color:rgba(255,255,255,.8);">Your project is on its way to you</div>
    <div style="margin-top:16px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:40px;padding:7px 22px;display:inline-block;font-size:.78rem;color:#fff;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">✅ Order Approved</div>
  </td></tr>
  <tr><td style="background:#fff;padding:32px 40px;">
    <p style="font-size:1.05rem;font-weight:700;color:#1e293b;margin:0 0 8px;">Hi $name! 🎓</p>
    <p style="font-size:.9rem;color:#64748b;margin:0 0 24px;line-height:1.7;">Great news! Your payment has been <strong style="color:#16a34a;">verified and approved</strong> by our team. We are now preparing your project files and will deliver them to you via <strong>Email or WhatsApp very soon</strong>.</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:24px;">
      <tr style="background:#f8fafc;"><td colspan="2" style="padding:12px 16px;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.08em;">Order Confirmation</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Project</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$project</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Amount Paid</td><td style="padding:10px 16px;font-size:.84rem;font-weight:800;color:#16a34a;border-top:1px solid #f1f5f9;">₹$amount</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">UTR Number</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;font-family:monospace;">$utr</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Status</td><td style="padding:10px 16px;border-top:1px solid #f1f5f9;"><span style="background:#dcfce7;color:#15803d;font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:20px;">✅ Approved</span></td></tr>
    </table>
    <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:12px;padding:16px 18px;margin-bottom:24px;">
      <p style="font-size:.88rem;color:#14532d;margin:0;font-weight:700;margin-bottom:6px;">📦 What happens next?</p>
      <p style="font-size:.84rem;color:#166534;margin:0;line-height:1.7;">Our team will send the complete project package — source code, documentation, and PPT — to this email address and/or your WhatsApp number within the next few hours. Please keep an eye on your inbox (and spam folder).</p>
    </div>
    <p style="font-size:.83rem;color:#64748b;margin:0;">Questions? Reply to this email or reach us at <a href="mailto:support@yourdomain.com" style="color:#2563eb;font-weight:700;">support@yourdomain.com</a></p>
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

header("Location: project_orders.php?filter=approved&msg=approved");
exit();