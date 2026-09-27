<?php
session_start();
include("config/db.php");
include_once("includes/mailer.php");

// CSRF token — generated once per session
if (empty($_SESSION['proj_csrf'])) {
    $_SESSION['proj_csrf'] = bin2hex(random_bytes(32));
}
$proj_csrf = $_SESSION['proj_csrf'];

// Auto-create project_orders table
$conn->query("
    CREATE TABLE IF NOT EXISTS project_orders (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        project_id     INT           NOT NULL,
        student_name   VARCHAR(150)  NOT NULL,
        email          VARCHAR(150)  NOT NULL,
        mobile         VARCHAR(20)   NOT NULL,
        hall_ticket    VARCHAR(100)  NOT NULL,
        college_name   VARCHAR(200)  NOT NULL,
        department     VARCHAR(150)  NOT NULL,
        utr_number     VARCHAR(60)   NOT NULL,
        terminal_name  VARCHAR(60)   DEFAULT 'Terminal 1',
        terminal_upi   VARCHAR(80),
        amount         DECIMAL(10,2) NOT NULL,
        payment_status ENUM('pending','approved','rejected') DEFAULT 'pending',
        admin_note     VARCHAR(500)  DEFAULT NULL,
        ordered_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
        reviewed_at    DATETIME      DEFAULT NULL,
        INDEX idx_project (project_id),
        INDEX idx_status  (payment_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Ensure UNIQUE constraint on utr_number exists (safe to run every time — no-op if already present)
$conn->query("
    ALTER TABLE project_orders ADD UNIQUE KEY uq_utr (utr_number)
"); // silently fails if already exists

// UPI Terminals
$terminals = [
    1 => ['name' => 'Terminal 1', 'upi' => 'yourupi1@ybl'],
    2 => ['name' => 'Terminal 2', 'upi' => 'yourupi2@ybl'],
    3 => ['name' => 'Terminal 3', 'upi' => 'yourupi3@ybl'],
];

// Load project
$project_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($project_id <= 0) { die('<p style="font-family:sans-serif;padding:40px;color:#dc2626;">Invalid project ID.</p>'); }

$pstmt = $conn->prepare("SELECT * FROM final_year_projects WHERE id=? AND status='active'");
$pstmt->bind_param("i", $project_id);
$pstmt->execute();
$project = $pstmt->get_result()->fetch_assoc();
$pstmt->close();

if (!$project) { die('<p style="font-family:sans-serif;padding:40px;color:#dc2626;">Project not found or no longer available.</p>'); }

$features = array_filter(array_map('trim', explode('|', $project['features'] ?? '')));
$amount   = (float)$project['price'];

// ── UTR pre-fill from callback params ──
$utr_prefill = '';
$utr_keys = ['txnId','txnRef','txnid','txnref','utr','UTR','referenceId','rrn','transactionId'];
foreach ($utr_keys as $k) {
    if (!empty($_GET[$k])) {
        $utr_prefill = preg_replace('/[^A-Za-z0-9]/', '', $_GET[$k]);
        break;
    }
}

// ── Domain style helpers ──
$d = strtolower($project['domain'] ?? '');
$domain_key = str_contains($d,'ai')||str_contains($d,'ml') ? 'ai'
    : (str_contains($d,'web')    ? 'web'
    : (str_contains($d,'iot')    ? 'iot'
    : (str_contains($d,'data')   ? 'data'
    : (str_contains($d,'mobile') ? 'mobile'
    : (str_contains($d,'cyber')  ? 'cyber'
    : 'other')))));
$grad_map = [
    'ai' => ['#4f46e5','#7c3aed'], 'web' => ['#2563eb','#0ea5e9'],
    'iot' => ['#059669','#10b981'], 'data' => ['#d97706','#f59e0b'],
    'mobile' => ['#db2777','#ec4899'], 'cyber' => ['#dc2626','#ef4444'],
    'other' => ['#475569','#64748b'],
];
[$g1, $g2] = $grad_map[$domain_key];

// ── POST: Handle form submission ──
$submitted    = false;
$submitted_data = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (
        empty($_POST['proj_csrf']) ||
        !hash_equals($_SESSION['proj_csrf'], $_POST['proj_csrf'])
    ) {
        http_response_code(403);
        die('<p style="font-family:sans-serif;padding:40px;color:#dc2626;">Invalid request. Please refresh the page and try again.</p>');
    }

    $student_name = trim(htmlspecialchars($_POST['student_name'] ?? ''));
    $mobile       = trim(preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? ''));
    $email        = trim($_POST['email'] ?? '');
    $hall_ticket  = trim(htmlspecialchars($_POST['hall_ticket'] ?? ''));
    $college_name = trim(htmlspecialchars($_POST['college_name'] ?? ''));
    $department   = trim(htmlspecialchars($_POST['department'] ?? ''));
    $utr_raw      = trim($_POST['utr_number'] ?? '');
    $utr_number   = preg_replace('/[^A-Za-z0-9]/', '', $utr_raw);
    $terminal_no  = isset($_POST['terminal_no']) && is_numeric($_POST['terminal_no']) ? (int)$_POST['terminal_no'] : 1;
    if (!isset($terminals[$terminal_no])) $terminal_no = 1;
    $terminal_name = $terminals[$terminal_no]['name'];
    $terminal_upi  = $terminals[$terminal_no]['upi'];

    // Validation
    if (!$student_name) $errors[] = 'Full name is required.';
    if (strlen($mobile) !== 10) $errors[] = 'Valid 10-digit mobile number is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address is required.';
    if (!$hall_ticket) $errors[] = 'Hall ticket number is required.';
    if (!$college_name) $errors[] = 'College name is required.';
    if (!$department) $errors[] = 'Department is required.';
    if (strlen($utr_number) < 12) $errors[] = 'UTR / transaction reference must be at least 12 characters.';

    // BUG FIX: Prevent duplicate UTR submissions
    if (empty($errors)) {
        $dup = $conn->prepare("SELECT id FROM project_orders WHERE utr_number = ?");
        $dup->bind_param("s", $utr_number);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            $errors[] = 'This UTR number has already been submitted. If you believe this is a mistake, please contact support.';
        }
        $dup->close();
    }

    if (empty($errors)) {
        // Insert order — DB UNIQUE key on utr_number is the final safety net for race conditions
        $ins = $conn->prepare("INSERT INTO project_orders (project_id, student_name, email, mobile, hall_ticket, college_name, department, utr_number, terminal_name, terminal_upi, amount) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $ins->bind_param("isssssssssd", $project_id, $student_name, $email, $mobile, $hall_ticket, $college_name, $department, $utr_number, $terminal_name, $terminal_upi, $amount);
        $ins->execute();

        // MySQL error 1062 = duplicate entry (race condition caught at DB level)
        if ($conn->errno === 1062) {
            $errors[] = 'This UTR number has already been submitted. If you believe this is a mistake, please contact support.';
            $ins->close();
        } else {
            $new_order_id = $ins->insert_id;
            $ins->close();

        // Insert notification
        $notif_msg = "New project order: $student_name purchased {$project['title']} - UTR: $utr_number";
        $ntype = 'project_order'; $nrole = 'admin'; $nread = 0;
        $notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($notif_check && $notif_check->num_rows > 0) {
            $nins = $conn->prepare("INSERT INTO notifications (type, for_role, message, is_read) VALUES (?,?,?,?)");
            $nins->bind_param("sssi", $ntype, $nrole, $notif_msg, $nread);
            $nins->execute();
            $nins->close();
        }

        // BUG FIX: Send admin alert email on new project order
        if (function_exists('vs_send_mail')) {
            $admin_subject = "New Project Order: {$project['title']}";
            $admin_body = <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:16px 16px 0 0;padding:36px 40px;text-align:center;">
    <div style="font-size:2rem;margin-bottom:12px;">🛒</div>
    <div style="font-size:1.5rem;font-weight:800;color:#fff;margin-bottom:4px;">New Project Order Received</div>
    <div style="font-size:.88rem;color:rgba(255,255,255,.75);">Pending payment verification</div>
  </td></tr>
  <tr><td style="background:#fff;padding:32px 40px;">
    <p style="font-size:.95rem;color:#1e293b;margin:0 0 20px;">A new project order has been submitted and is awaiting your approval.</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:24px;">
      <tr style="background:#f8fafc;"><td colspan="2" style="padding:12px 16px;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.08em;">Order Details</td></tr>
      <tr style="background:#eff6ff;"><td style="padding:10px 16px;font-size:.84rem;color:#1e40af;font-weight:700;border-top:1px solid #f1f5f9;">Order ID</td><td style="padding:10px 16px;font-size:.9rem;font-weight:800;color:#1e40af;border-top:1px solid #f1f5f9;font-family:monospace;">#$new_order_id</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Student Name</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$student_name</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Email</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$email</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Mobile</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$mobile</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">College</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$college_name</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Department</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$department</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Hall Ticket</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$hall_ticket</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Project</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">{$project['title']}</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Amount</td><td style="padding:10px 16px;font-size:.84rem;font-weight:800;color:#16a34a;border-top:1px solid #f1f5f9;">₹{$amount}</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">UTR Number</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;font-family:monospace;">$utr_number</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Terminal</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$terminal_name ($terminal_upi)</td></tr>
    </table>
    <a href="https://yourdomain.com/admin/project_orders.php?filter=pending" style="display:inline-block;padding:13px 28px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;border-radius:10px;font-weight:700;font-size:.9rem;text-decoration:none;">Review Order in Admin Panel →</a>
  </td></tr>
  <tr><td style="background:#f8fafc;border-radius:0 0 16px 16px;padding:18px 40px;text-align:center;font-size:.78rem;color:#94a3b8;">
    CourseFlow · <a href="mailto:support@yourdomain.com" style="color:#3b82f6;">support@yourdomain.com</a>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
            vs_send_mail(VS_ADMIN_EMAIL, VS_ADMIN_NAME, $admin_subject, $admin_body);
        }

        // BUG FIX: Send order confirmation email to student
        if (function_exists('vs_send_mail')) {
            $stu_subject = "Order Received: {$project['title']} — CourseFlow";
            $stu_body = <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:linear-gradient(135deg,#0f172a,#1e3a8a);border-radius:16px 16px 0 0;padding:40px 40px 32px;text-align:center;">
    <div style="font-size:2.2rem;margin-bottom:12px;">✅</div>
    <div style="font-size:1.5rem;font-weight:800;color:#fff;margin-bottom:6px;">Order Submitted!</div>
    <div style="font-size:.88rem;color:rgba(255,255,255,.7);">Your payment is under review — we'll deliver within 24 hours</div>
  </td></tr>
  <tr><td style="background:#fff;padding:32px 40px;">
    <p style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 6px;">Hi $student_name,</p>
    <p style="font-size:.9rem;color:#64748b;margin:0 0 24px;line-height:1.7;">Thank you for your order! We have received your project order and payment details. Our team will verify your UTR number and deliver the project files to you via <strong>Email or WhatsApp within 24 hours</strong> of approval.</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:24px;">
      <tr style="background:#eff6ff;"><td style="padding:10px 16px;font-size:.84rem;color:#1e40af;font-weight:700;border-top:1px solid #f1f5f9;">Order ID</td><td style="padding:10px 16px;font-size:.9rem;font-weight:800;color:#1e40af;border-top:1px solid #f1f5f9;font-family:monospace;">#$new_order_id</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Project</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">{$project['title']}</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Amount Paid</td><td style="padding:10px 16px;font-size:.84rem;font-weight:800;color:#16a34a;border-top:1px solid #f1f5f9;">₹{$amount}</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">UTR Number</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;font-family:monospace;">$utr_number</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">College</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$college_name</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Hall Ticket</td><td style="padding:10px 16px;font-size:.84rem;font-weight:700;color:#0f172a;border-top:1px solid #f1f5f9;">$hall_ticket</td></tr>
      <tr><td style="padding:10px 16px;font-size:.84rem;color:#64748b;border-top:1px solid #f1f5f9;">Status</td><td style="padding:10px 16px;border-top:1px solid #f1f5f9;"><span style="background:#fef9c3;color:#854d0e;font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:20px;">⏳ Pending Verification</span></td></tr>
    </table>
    <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:12px;padding:16px 18px;margin-bottom:24px;display:flex;gap:12px;">
      <div style="font-size:1.2rem;">ℹ️</div>
      <p style="font-size:.84rem;color:#1e3a8a;margin:0;line-height:1.6;">If you don't receive your project within 24 hours of placing this order, please contact us at <a href="mailto:support@yourdomain.com" style="color:#2563eb;font-weight:700;">support@yourdomain.com</a> or WhatsApp us with your UTR number.</p>
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
            vs_send_mail($email, $student_name, $stu_subject, $stu_body);
        }

        $submitted = true;
        $submitted_data = compact('student_name','email','mobile','college_name','department','hall_ticket','utr_number','terminal_name','amount');
        $submitted_data['order_id'] = $new_order_id;
        } // end else (no DB duplicate)
    }
}
// Dynamic SEO Setup for individual project detail page
$proj_title = htmlspecialchars($project['title']);
$proj_domain = htmlspecialchars($project['domain'] ?? 'Academic Project');
$proj_price = number_format((float)$project['price'], 2, '.', '');
$proj_desc_clean = htmlspecialchars(mb_strimwidth(strip_tags($project['description'] ?? ''), 0, 160, '...'));
$proj_url = "https://yourdomain.com/project_payment.php?id=" . (int)$project['id'];
$proj_img = !empty($project['thumbnail_url']) ? $project['thumbnail_url'] : "https://yourdomain.com/assets/images/logo.jpeg";

$seo_title = "{$proj_title} - Final Year Project in Bengaluru with Source Code | CourseFlow";
$seo_meta_desc = "Buy {$proj_title} ({$proj_domain} final year project) with complete verified source code, IEEE report, PPT & viva guidance. Instant delivery in Bengaluru & across India.";

$product_schema = [
    "@context" => "https://schema.org",
    "@type" => "Product",
    "name" => $project['title'],
    "image" => $proj_img,
    "description" => strip_tags($project['description'] ?? ''),
    "category" => $project['domain'] ?? 'Software',
    "offers" => [
        "@type" => "Offer",
        "url" => $proj_url,
        "priceCurrency" => "INR",
        "price" => $proj_price,
        "priceValidUntil" => date('Y-12-31'),
        "itemCondition" => "https://schema.org/NewCondition",
        "availability" => "https://schema.org/InStock",
        "seller" => [
            "@type" => "Organization",
            "name" => "CourseFlow",
            "url" => "https://yourdomain.com/"
        ]
    ]
];

$breadcrumb_project_schema = [
    "@context" => "https://schema.org",
    "@type" => "BreadcrumbList",
    "itemListElement" => [
        [
            "@type" => "ListItem",
            "position" => 1,
            "name" => "Home",
            "item" => "https://yourdomain.com/"
        ],
        [
            "@type" => "ListItem",
            "position" => 2,
            "name" => "Projects Catalogue",
            "item" => "https://yourdomain.com/projects.php"
        ],
        [
            "@type" => "ListItem",
            "position" => 3,
            "name" => $project['title'],
            "item" => $proj_url
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $seo_title ?></title>
    <meta name="description" content="<?= htmlspecialchars($seo_meta_desc) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($proj_title) ?>, <?= htmlspecialchars($proj_domain) ?> final year project, buy final year project bengaluru, btech project source code btm layout">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($proj_url) ?>">

    <!-- Open Graph / WhatsApp / Facebook -->
    <meta property="og:type" content="product">
    <meta property="og:url" content="<?= htmlspecialchars($proj_url) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seo_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seo_meta_desc) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($proj_img) ?>">
    <meta property="og:price:amount" content="<?= $proj_price ?>">
    <meta property="og:price:currency" content="INR">
    <meta property="og:site_name" content="CourseFlow">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars($proj_url) ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($seo_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seo_meta_desc) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($proj_img) ?>">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">

    <!-- Product & Breadcrumb JSON-LD Schema -->
    <script type="application/ld+json"><?= json_encode($product_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?></script>
    <script type="application/ld+json"><?= json_encode($breadcrumb_project_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?></script>
    <style>
        :root {
            --blue: #2563eb;
            --indigo: #4f46e5;
            --text: #0f172a;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --radius: 12px;
            --shadow: 0 4px 24px rgba(0,0,0,.07);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; color: var(--text); background: var(--bg); }

        /* ── TOP BAR ── */
        .topbar {
            background: #0f172a;
            padding: 0 5%; height: 64px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        .topbar-logo {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.15rem; font-weight: 800;
            color: #fff; text-decoration: none;
            display: flex; align-items: center; gap: 8px;
        }
        .topbar-logo span { color: #60a5fa; }
        .topbar-back {
            display: inline-flex; align-items: center; gap: 6px;
            color: rgba(255,255,255,.7); text-decoration: none;
            font-size: .84rem; font-weight: 500; transition: color .2s;
        }
        .topbar-back:hover { color: #fff; }

        /* ── MAIN LAYOUT ── */
        .pay-layout {
            max-width: 1100px; margin: 0 auto;
            padding: 36px 20px 60px;
            display: grid; grid-template-columns: 360px 1fr; gap: 28px;
            align-items: start;
        }

        /* ── LEFT: PROJECT SUMMARY ── */
        .summary-card {
            background: #fff; border: 1.5px solid var(--border);
            border-radius: 18px; overflow: hidden;
            box-shadow: var(--shadow);
            position: sticky; top: 80px;
        }
        .summary-thumb {
            width: 100%; height: 180px; overflow: hidden;
            background: linear-gradient(135deg, <?= $g1 ?>, <?= $g2 ?>);
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .summary-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .summary-thumb-icon { font-size: 3rem; color: rgba(255,255,255,.5); }
        .summary-body { padding: 20px; }
        .summary-domain-badge {
            display: inline-block; padding: 3px 10px; border-radius: 999px;
            font-size: .7rem; font-weight: 700; margin-bottom: 10px;
        }
        .summary-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.05rem; font-weight: 800; color: var(--text);
            line-height: 1.3; margin-bottom: 16px;
        }
        .summary-price {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2rem; font-weight: 800; color: var(--text);
            margin-bottom: 4px;
        }
        .summary-price-sub { font-size: .78rem; color: #64748b; margin-bottom: 18px; }

        .summary-features { list-style: none; margin-bottom: 20px; }
        .summary-features li {
            display: flex; align-items: center; gap: 8px;
            font-size: .84rem; color: #334155; padding: 5px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .summary-features li:last-child { border-bottom: none; }
        .summary-features li i { color: #22c55e; font-size: .75rem; flex-shrink: 0; }

        .delivery-box {
            background: #eff6ff; border: 1.5px solid #bfdbfe;
            border-radius: 10px; padding: 12px 14px;
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 14px;
        }
        .delivery-box i { color: #2563eb; margin-top: 2px; flex-shrink: 0; }
        .delivery-box span { font-size: .8rem; color: #1e3a8a; line-height: 1.5; }

        .trust-badge {
            display: flex; align-items: center; justify-content: center; gap: 7px;
            background: #f0fdf4; border: 1.5px solid #86efac;
            border-radius: 9px; padding: 9px;
            font-size: .78rem; font-weight: 700; color: #15803d;
        }

        /* ── RIGHT: STEPS FORM ── */
        .steps-card {
            background: #fff; border: 1.5px solid var(--border);
            border-radius: 18px; overflow: hidden;
            box-shadow: var(--shadow);
        }
        .steps-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            padding: 20px 26px; color: #fff;
        }
        .steps-header h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.1rem; font-weight: 800; margin-bottom: 14px;
        }
        .step-indicators {
            display: flex; align-items: center; gap: 0;
        }
        .step-ind {
            display: flex; align-items: center; gap: 7px;
            font-size: .78rem; font-weight: 600; color: rgba(255,255,255,.6);
        }
        .step-ind.active { color: #fff; }
        .step-ind.done { color: #86efac; }
        .step-num {
            width: 26px; height: 26px; border-radius: 50%;
            background: rgba(255,255,255,.2); border: 2px solid rgba(255,255,255,.3);
            display: flex; align-items: center; justify-content: center;
            font-size: .72rem; font-weight: 800; flex-shrink: 0;
        }
        .step-ind.active .step-num { background: rgba(255,255,255,.9); color: #1e40af; border-color: #fff; }
        .step-ind.done .step-num { background: #22c55e; border-color: #22c55e; color: #fff; }
        .step-sep { width: 36px; height: 2px; background: rgba(255,255,255,.2); margin: 0 4px; }

        /* Step panels */
        .step-panel { display: none; padding: 28px 26px; }
        .step-panel.active { display: block; }

        /* Form fields */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block; font-size: .8rem; font-weight: 700; color: #334155;
            margin-bottom: 6px;
        }
        .form-group label span { color: #ef4444; }
        .form-control {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid var(--border); border-radius: 10px;
            font-size: .88rem; font-family: 'DM Sans', sans-serif;
            color: var(--text); background: #fff; outline: none;
            transition: border-color .2s;
        }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        /* Payment — terminal tabs */
        .terminal-tabs { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
        .terminal-tab {
            padding: 8px 16px; border-radius: 9px;
            border: 1.5px solid var(--border);
            background: #fff; color: #475569;
            font-size: .82rem; font-weight: 600;
            cursor: pointer; transition: all .18s; font-family: 'DM Sans', sans-serif;
        }
        .terminal-tab:hover { border-color: #93c5fd; color: var(--blue); }
        .terminal-tab.active { background: var(--blue); border-color: var(--blue); color: #fff; }

        .qr-section { display: flex; align-items: flex-start; gap: 24px; margin-bottom: 20px; flex-wrap: wrap; }
        .qr-box {
            width: 200px; height: 200px; border-radius: 14px;
            overflow: hidden; border: 2px solid var(--border);
            flex-shrink: 0; background: #fff;
        }
        .qr-box img { width: 100%; height: 100%; object-fit: contain; }

        .qr-info { flex: 1; min-width: 180px; }
        .amount-display {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2rem; font-weight: 800; color: var(--text); margin-bottom: 4px;
        }
        .amount-display span { font-size: .8rem; font-weight: 500; color: #64748b; }
        .upi-row {
            display: flex; align-items: center; gap: 8px;
            background: #f8fafc; border: 1.5px solid var(--border);
            border-radius: 10px; padding: 9px 13px;
            margin: 12px 0 16px;
        }
        .upi-row code { font-family: monospace; font-size: .88rem; color: var(--text); font-weight: 700; flex: 1; }
        .btn-copy {
            padding: 4px 11px; border-radius: 7px;
            background: #eff6ff; color: #1d4ed8;
            border: none; font-size: .75rem; font-weight: 700;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            transition: all .15s;
        }
        .btn-copy:hover { background: #dbeafe; }
        .btn-copy.copied { background: #dcfce7; color: #15803d; }

        .deeplink-btns { display: flex; gap: 7px; flex-wrap: wrap; }
        .dl-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 7px 13px; border-radius: 8px;
            border: 1.5px solid var(--border); background: #fff;
            font-size: .76rem; font-weight: 700; text-decoration: none;
            color: #334155; transition: all .18s;
        }
        .dl-btn:hover { border-color: #93c5fd; color: var(--blue); transform: translateY(-1px); }
        .dl-btn i { font-size: .85rem; }
        .dl-btn.phonepe { border-color: #7b2d8b; color: #7b2d8b; }
        .dl-btn.phonepe:hover { background: #fdf4ff; }
        .dl-btn.gpay    { border-color: #1a73e8; color: #1a73e8; }
        .dl-btn.gpay:hover { background: #eff6ff; }
        .dl-btn.paytm   { border-color: #002970; color: #002970; }
        .dl-btn.paytm:hover { background: #eff6ff; }

        /* UTR step */
        .review-box {
            background: #f8fafc; border: 1.5px solid var(--border);
            border-radius: 12px; padding: 16px; margin-bottom: 18px;
        }
        .review-box h4 { font-size: .8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 12px; }
        .review-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: .84rem; }
        .review-row:last-child { border-bottom: none; }
        .review-row label { color: #64748b; font-weight: 500; }
        .review-row span  { color: var(--text); font-weight: 700; }

        .utr-note {
            background: #fffbeb; border: 1.5px solid #fde68a;
            border-radius: 10px; padding: 12px 14px;
            display: flex; align-items: flex-start; gap: 9px;
            font-size: .8rem; color: #92400e; line-height: 1.5; margin-bottom: 18px;
        }
        .utr-note i { color: #f59e0b; margin-top: 2px; flex-shrink: 0; }

        /* Buttons */
        .btn-primary {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: #fff; border: none; border-radius: 11px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .95rem; font-weight: 700;
            cursor: pointer; transition: all .2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(37,99,235,.35); }
        .btn-outline {
            width: 100%; padding: 11px;
            background: #fff; color: #475569;
            border: 1.5px solid var(--border); border-radius: 11px;
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem; font-weight: 600;
            cursor: pointer; transition: all .2s; margin-top: 10px;
            display: flex; align-items: center; justify-content: center; gap: 7px;
        }
        .btn-outline:hover { border-color: #3b82f6; color: var(--blue); }

        /* Error list */
        .error-list {
            background: #fef2f2; border: 1.5px solid #fca5a5;
            border-radius: 10px; padding: 12px 16px; margin-bottom: 18px;
        }
        .error-list p { font-size: .84rem; color: #b91c1c; font-weight: 600; margin-bottom: 6px; }
        .error-list ul { list-style: none; }
        .error-list ul li { font-size: .82rem; color: #7f1d1d; padding: 2px 0; }
        .error-list ul li::before { content: '• '; }

        /* ── SUCCESS SCREEN ── */
        .success-screen { padding: 40px 26px; text-align: center; }
        .checkmark-wrap { margin-bottom: 22px; }
        .checkmark-circle {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, #16a34a, #22c55e);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            animation: checkPop .4s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes checkPop { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }
        .checkmark-circle i { font-size: 2.5rem; color: #fff; }
        .success-screen h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.4rem; font-weight: 800; color: var(--text); margin-bottom: 6px;
        }
        .success-screen p.sub { font-size: .9rem; color: #64748b; margin-bottom: 28px; }

        .success-table { width: 100%; border-collapse: collapse; text-align: left; margin-bottom: 22px; }
        .success-table tr { border-bottom: 1px solid #f1f5f9; }
        .success-table tr:last-child { border-bottom: none; }
        .success-table td { padding: 10px 14px; font-size: .84rem; }
        .success-table td:first-child { color: #64748b; font-weight: 600; width: 42%; }
        .success-table td:last-child { color: var(--text); font-weight: 700; }

        .success-info-box {
            background: #eff6ff; border: 1.5px solid #bfdbfe;
            border-radius: 12px; padding: 16px 18px;
            display: flex; align-items: flex-start; gap: 10px; text-align: left;
            margin-bottom: 24px;
        }
        .success-info-box i { color: #2563eb; margin-top: 2px; flex-shrink: 0; font-size: 1rem; }
        .success-info-box span { font-size: .84rem; color: #1e3a8a; line-height: 1.6; }

        .btn-browse {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 28px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: #fff; border-radius: 11px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .9rem; font-weight: 700; text-decoration: none;
            transition: all .2s;
        }
        .btn-browse:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(37,99,235,.35); color: #fff; }

        /* Domain badge colours */
        .db-ai     { background: #f5f3ff; color: #6d28d9; }
        .db-web    { background: #eff6ff; color: #1d4ed8; }
        .db-iot    { background: #f0fdf4; color: #15803d; }
        .db-data   { background: #fff7ed; color: #c2410c; }
        .db-mobile { background: #fdf4ff; color: #a21caf; }
        .db-cyber  { background: #fef2f2; color: #b91c1c; }
        .db-other  { background: #f8fafc; color: #475569; }

        /* Footer */
        .site-footer {
            background: #0f172a; color: rgba(255,255,255,.6);
            text-align: center; padding: 24px 5%;
            font-size: .82rem;
        }
        .site-footer a { color: #60a5fa; text-decoration: none; }

        @media (max-width: 820px) {
            .pay-layout { grid-template-columns: 1fr; }
            .summary-card { position: static; }
            .form-row { grid-template-columns: 1fr; }
            .qr-section { flex-direction: column; align-items: center; }
        }
        @media (max-width: 480px) {
            .pay-layout { padding: 20px 14px 50px; }
            .step-panel { padding: 20px 16px; }
            .steps-header { padding: 16px 18px; }
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<header class="topbar">
    <a href="index.html" class="topbar-logo">
        <i class="fas fa-vault"></i> CourseFlow<span> AI</span>
    </a>
    <a href="projects.php" class="topbar-back"><i class="fas fa-arrow-left"></i> Back to Projects</a>
</header>

<!-- MAIN -->
<div class="pay-layout">

    <!-- LEFT: Summary -->
    <aside class="summary-card">
        <div class="summary-thumb">
            <?php if (!empty($project['thumbnail_url'])): ?>
            <img src="<?= htmlspecialchars($project['thumbnail_url']) ?>" alt=""
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="summary-thumb-icon" style="display:none;"><i class="fas fa-folder"></i></div>
            <?php else: ?>
            <div class="summary-thumb-icon"><i class="fas fa-folder"></i></div>
            <?php endif; ?>
        </div>
        <div class="summary-body">
            <?php if (!empty($project['domain'])): ?>
            <span class="summary-domain-badge db-<?= $domain_key ?>"><?= htmlspecialchars($project['domain']) ?></span>
            <?php endif; ?>
            <h2 class="summary-title"><?= htmlspecialchars($project['title']) ?></h2>
            <div class="summary-price">₹<?= number_format($amount, 0) ?></div>
            <div class="summary-price-sub">One-time payment • Incl. all taxes</div>

            <?php if (!empty($features)): ?>
            <ul class="summary-features">
                <?php foreach ($features as $feat): ?>
                <li><i class="fas fa-check-circle"></i><?= htmlspecialchars($feat) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <div class="delivery-box">
                <i class="fas fa-paper-plane"></i>
                <span>Delivered via <strong>Email or WhatsApp</strong> within 24 hours after payment approval.</span>
            </div>
            <div class="trust-badge">
                <i class="fas fa-lock"></i> Secure UPI Payment
            </div>
        </div>
    </aside>

    <!-- RIGHT: Form / Success -->
    <div class="steps-card">

    <?php if ($submitted): ?>
    <!-- SUCCESS SCREEN -->
    <div class="success-screen">
        <div class="checkmark-wrap">
            <div class="checkmark-circle"><i class="fas fa-check"></i></div>
            <h2>Order Submitted Successfully!</h2>
            <p class="sub">Your payment is under review. We'll contact you within 24 hours.</p>
        </div>

        <table class="success-table">
            <tr style="background:#eff6ff;">
                <td style="font-weight:700;color:#1e40af;">Order ID</td>
                <td style="font-weight:800;color:#1e40af;font-family:monospace;font-size:1rem;">#<?= (int)$submitted_data['order_id'] ?></td>
            </tr>
            <tr><td>Name</td><td><?= htmlspecialchars($submitted_data['student_name']) ?></td></tr>
            <tr><td>Email</td><td><?= htmlspecialchars($submitted_data['email']) ?></td></tr>
            <tr><td>Mobile</td><td><?= htmlspecialchars($submitted_data['mobile']) ?></td></tr>
            <tr><td>College</td><td><?= htmlspecialchars($submitted_data['college_name']) ?></td></tr>
            <tr><td>Department</td><td><?= htmlspecialchars($submitted_data['department']) ?></td></tr>
            <tr><td>Hall Ticket</td><td><?= htmlspecialchars($submitted_data['hall_ticket']) ?></td></tr>
            <tr><td>Project</td><td><?= htmlspecialchars($project['title']) ?></td></tr>
            <tr><td>Amount Paid</td><td>₹<?= number_format((float)$submitted_data['amount'], 0) ?></td></tr>
            <tr><td>UTR Number</td><td><?= htmlspecialchars($submitted_data['utr_number']) ?></td></tr>
        </table>

        <div class="success-info-box">
            <i class="fas fa-info-circle"></i>
            <span>Our team will verify your payment and contact you within <strong>24 hours</strong> via Email or WhatsApp with your project files.</span>
        </div>

        <a href="projects.php" class="btn-browse"><i class="fas fa-arrow-left"></i> Browse More Projects</a>
    </div>

    <?php else: ?>
    <!-- FORM STEPS -->
    <div class="steps-header">
        <h2><i class="fas fa-shopping-cart" style="margin-right:8px;"></i>Purchase Project</h2>
        <div class="step-indicators">
            <div class="step-ind active" id="ind-1">
                <div class="step-num">1</div><span>Student Details</span>
            </div>
            <div class="step-sep"></div>
            <div class="step-ind" id="ind-2">
                <div class="step-num">2</div><span>Make Payment</span>
            </div>
            <div class="step-sep"></div>
            <div class="step-ind" id="ind-3">
                <div class="step-num">3</div><span>Submit UTR</span>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div style="padding:18px 26px 0;">
        <div class="error-list">
            <p><i class="fas fa-exclamation-circle"></i> Please fix the following errors:</p>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" id="orderForm">
        <input type="hidden" name="proj_csrf" value="<?= htmlspecialchars($proj_csrf) ?>">
        <input type="hidden" name="terminal_no" id="terminalNoInput" value="1">

        <!-- STEP 1: Student Details -->
        <div class="step-panel active" id="step1">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name <span>*</span></label>
                    <input type="text" name="student_name" class="form-control" placeholder="Your full name" required
                           value="<?= htmlspecialchars($_POST['student_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Mobile Number <span>*</span></label>
                    <input type="tel" name="mobile" class="form-control" placeholder="10-digit mobile" maxlength="10" required
                           value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Email Address <span>*</span></label>
                <input type="email" name="email" class="form-control" placeholder="your@email.com" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Hall Ticket Number <span>*</span></label>
                    <input type="text" name="hall_ticket" class="form-control" placeholder="e.g. 21A91A0501" required
                           value="<?= htmlspecialchars($_POST['hall_ticket'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Department <span>*</span></label>
                    <input type="text" name="department" class="form-control" placeholder="e.g. CSE, ECE" required
                           value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>College Name <span>*</span></label>
                <input type="text" name="college_name" class="form-control" placeholder="Full college name" required
                       value="<?= htmlspecialchars($_POST['college_name'] ?? '') ?>">
            </div>
            <button type="button" class="btn-primary" onclick="goToStep(2)">
                <i class="fas fa-arrow-right"></i> Continue to Payment
            </button>
        </div>

        <!-- STEP 2: Make Payment -->
        <div class="step-panel" id="step2">
            <div style="margin-bottom:16px;">
                <div style="font-size:.82rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">Select Payment Terminal</div>
                <div class="terminal-tabs">
                    <?php foreach ($terminals as $tn => $t): ?>
                    <button type="button" class="terminal-tab <?= $tn===1?'active':'' ?>" onclick="selectTerminal(<?= $tn ?>)">
                        <?= htmlspecialchars($t['name']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="qr-section">
                <div class="qr-box">
                    <img id="qrImg" src="" alt="QR Code">
                </div>
                <div class="qr-info">
                    <div class="amount-display">₹<?= number_format($amount, 0) ?><span> to pay</span></div>
                    <div style="font-size:.78rem;color:#64748b;margin-bottom:8px;">Scan QR or use UPI ID below</div>
                    <div class="upi-row">
                        <code id="upiIdDisplay"></code>
                        <button type="button" class="btn-copy" id="copyBtn" onclick="copyUPI()">Copy</button>
                    </div>
                    <div style="font-size:.78rem;font-weight:700;color:#64748b;margin-bottom:8px;">Pay with app</div>
                    <div class="deeplink-btns" id="deeplinkBtns"></div>
                </div>
            </div>

            <button type="button" class="btn-primary" onclick="goToStep(3)">
                <i class="fas fa-check"></i> I've Completed the Payment
            </button>
            <button type="button" class="btn-outline" onclick="goToStep(1)">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>

        <!-- STEP 3: Submit UTR -->
        <div class="step-panel" id="step3">
            <div class="form-group">
                <label>UTR / Transaction Reference Number <span>*</span></label>
                <input type="text" name="utr_number" id="utrInput" class="form-control"
                       placeholder="e.g. 432156789012"
                       value="<?= htmlspecialchars(!empty($errors) ? ($_POST['utr_number'] ?? '') : $utr_prefill) ?>"
                       required>
            </div>

            <!-- Review summary -->
            <div class="review-box" id="reviewSummary">
                <h4>Order Summary</h4>
                <div class="review-row"><label>Name</label><span id="rs-name">—</span></div>
                <div class="review-row"><label>College</label><span id="rs-college">—</span></div>
                <div class="review-row"><label>Project</label><span><?= htmlspecialchars($project['title']) ?></span></div>
                <div class="review-row"><label>Amount</label><span>₹<?= number_format($amount, 0) ?></span></div>
            </div>

            <div class="utr-note">
                <i class="fas fa-info-circle"></i>
                After submission, our team will verify your payment and send the project via Email / WhatsApp within <strong>24 hours</strong>.
            </div>

            <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:12px 14px;display:flex;align-items:flex-start;gap:10px;margin-bottom:18px;">
                <input type="checkbox" id="payConfirm" style="margin-top:3px;width:16px;height:16px;accent-color:#16a34a;flex-shrink:0;cursor:pointer;">
                <label for="payConfirm" style="font-size:.84rem;color:#14532d;font-weight:600;cursor:pointer;line-height:1.5;">
                    I confirm that I have completed the UPI payment of <strong>₹<?= number_format($amount, 0) ?></strong> and the UTR number entered above is correct.
                </label>
            </div>

            <button type="submit" class="btn-primary" id="submitBtn" disabled style="opacity:.5;cursor:not-allowed;">
                <i class="fas fa-paper-plane"></i> Submit Order
            </button>
            <button type="button" class="btn-outline" onclick="goToStep(2)">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>

    </form>
    <?php endif; ?>
    </div><!-- /steps-card -->

</div><!-- /pay-layout -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <strong style="color:rgba(255,255,255,.85);">CourseFlow</strong>. All rights reserved. |
    <a href="projects.php">Browse Projects</a> · <a href="index.html#contact">Contact Us</a></p>
</footer>

<script>
const terminals = <?= json_encode($terminals) ?>;
const amount    = <?= json_encode($amount) ?>;
let currentTerminal = 1;

function buildQRUrl(upi) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' +
        encodeURIComponent('upi://pay?pa=' + upi + '&pn=CourseFlow+AI+Technologies&am=' + amount + '&cu=INR&tn=Project+Fee') +
        '&format=png&ecc=M&margin=4';
}
function buildDeeplinks(upi) {
    const amt = encodeURIComponent(amount);
    const upiE = encodeURIComponent(upi);
    return [
        { label: 'PhonePe', cls: 'phonepe', icon: 'fa-mobile-alt', href: `phonepe://pay?pa=${upiE}&pn=CourseFlow+AI&am=${amt}&cu=INR&tn=Project+Fee` },
        { label: 'GPay',    cls: 'gpay',    icon: 'fa-google',     href: `tez://upi/pay?pa=${upiE}&pn=CourseFlow+AI&am=${amt}&cu=INR&tn=Project+Fee` },
        { label: 'Paytm',   cls: 'paytm',   icon: 'fa-wallet',     href: `paytmmp://pay?pa=${upiE}&pn=CourseFlow+AI&am=${amt}&cu=INR&tn=Project+Fee` },
        { label: 'Any UPI', cls: '',        icon: 'fa-qrcode',     href: `upi://pay?pa=${upiE}&pn=CourseFlow+AI&am=${amt}&cu=INR&tn=Project+Fee` },
    ];
}

function selectTerminal(n) {
    currentTerminal = n;
    document.getElementById('terminalNoInput').value = n;
    const t = terminals[n];

    document.getElementById('qrImg').src = buildQRUrl(t.upi);
    document.getElementById('upiIdDisplay').textContent = t.upi;

    document.querySelectorAll('.terminal-tab').forEach((btn, i) => {
        btn.classList.toggle('active', i + 1 === n);
    });

    const links = buildDeeplinks(t.upi);
    document.getElementById('deeplinkBtns').innerHTML = links.map(l =>
        `<a href="${l.href}" class="dl-btn ${l.cls}"><i class="fas ${l.icon}"></i>${l.label}</a>`
    ).join('');
}

function copyUPI() {
    const upi = terminals[currentTerminal].upi;
    navigator.clipboard.writeText(upi).then(() => {
        const btn = document.getElementById('copyBtn');
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
    });
}

// Step navigation
let formData = {};
function goToStep(n) {
    if (n === 2) {
        // Validate step 1
        const name    = document.querySelector('[name=student_name]')?.value.trim();
        const mobile  = document.querySelector('[name=mobile]')?.value.replace(/\D/g,'');
        const email   = document.querySelector('[name=email]')?.value.trim();
        const ticket  = document.querySelector('[name=hall_ticket]')?.value.trim();
        const college = document.querySelector('[name=college_name]')?.value.trim();
        const dept    = document.querySelector('[name=department]')?.value.trim();
        if (!name || !mobile || !email || !ticket || !college || !dept) {
            alert('Please fill all student details before continuing.');
            return;
        }
        if (mobile.length !== 10) { alert('Please enter a valid 10-digit mobile number.'); return; }
        formData = { name, college };
    }
    if (n === 3) {
        // Update review summary
        document.getElementById('rs-name').textContent    = formData.name    || document.querySelector('[name=student_name]')?.value || '—';
        document.getElementById('rs-college').textContent = formData.college || document.querySelector('[name=college_name]')?.value || '—';
        // Reset confirmation checkbox each time step 3 is entered
        const cb = document.getElementById('payConfirm');
        const sb = document.getElementById('submitBtn');
        if (cb && sb) { cb.checked = false; sb.disabled = true; sb.style.opacity = '0.5'; sb.style.cursor = 'not-allowed'; }
    }

    document.querySelectorAll('.step-panel').forEach((p, i) => p.classList.toggle('active', i + 1 === n));
    document.querySelectorAll('.step-ind').forEach((ind, i) => {
        ind.classList.toggle('active', i + 1 === n);
        ind.classList.toggle('done', i + 1 < n);
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Payment confirmation checkbox — enable/disable submit
const payConfirm = document.getElementById('payConfirm');
const submitBtn  = document.getElementById('submitBtn');
if (payConfirm && submitBtn) {
    payConfirm.addEventListener('change', () => {
        submitBtn.disabled = !payConfirm.checked;
        submitBtn.style.opacity  = payConfirm.checked ? '1'      : '0.5';
        submitBtn.style.cursor   = payConfirm.checked ? 'pointer' : 'not-allowed';
    });
}

// Init terminal 1
selectTerminal(1);

<?php if (!empty($errors)): ?>
// Re-open at step 3 if UTR was missing after POST error
document.addEventListener('DOMContentLoaded', () => goToStep(3));
<?php endif; ?>
</script>
</body>
</html>