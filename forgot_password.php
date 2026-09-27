<?php
session_start();
include("config/db.php");

/**
 * password_reset_mailer function
 */
function vs_send_reset_mail($to, $toName, $subject, $htmlBody) {
    $host = 'ssl://smtp.hostinger.com';
    $port = 465;
    $user = 'password_reset@yourdomain.com';
    $pass = 'your_db_password_here';

    $socket = fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) return false;

    function smtp_chat($socket, $msg, $expected) {
        if($msg) fwrite($socket, $msg . "\r\n");
        $res = "";
        while ($line = fgets($socket, 515)) {
            $res .= $line;
            if (substr($line, 3, 1) == " ") break;
        }
        return (strpos($res, (string)$expected) !== false);
    }

    smtp_chat($socket, null, 220);
    smtp_chat($socket, "EHLO " . $_SERVER['HTTP_HOST'], 250);
    smtp_chat($socket, "AUTH LOGIN", 334);
    smtp_chat($socket, base64_encode($user), 334);
    smtp_chat($socket, base64_encode($pass), 235);
    smtp_chat($socket, "MAIL FROM: <$user>", 250);
    smtp_chat($socket, "RCPT TO: <$to>", 250);
    smtp_chat($socket, "DATA", 354);

    $headers = "MIME-Version: 1.0\r\n" .
               "Content-Type: text/html; charset=UTF-8\r\n" .
               "From: CourseFlow Security <$user>\r\n" .
               "To: $toName <$to>\r\n" .
               "Subject: $subject\r\n\r\n";

    smtp_chat($socket, $headers . $htmlBody . "\r\n.", 250);
    smtp_chat($socket, "QUIT", 221);
    fclose($socket);
    return true;
}

$error   = "";
$success = "";

if (isset($_POST['send_otp'])) {
    $email = trim(strtolower($_POST['email'] ?? ''));

    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user_data) {
            $success = "If that email is registered, an OTP has been sent.";
        } else {
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $upd = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
            $upd->bind_param("sss", $otp, $expires, $email);
            
            if ($upd->execute()) {
                $subject = "CourseFlow LMS — Password Reset OTP";
                $html_body = "
                <div style='font-family:sans-serif; background:#f4f7fb; padding:40px; border-radius:15px;'>
                    <h2 style='color:#1e293b;'>Password Reset Request</h2>
                    <p>Hello " . htmlspecialchars($user_data['name']) . ",</p>
                    <p>Your 6-digit OTP is: <b>$otp</b></p>
                    <p>Valid for 15 minutes.</p>
                </div>";

                if (vs_send_reset_mail($email, $user_data['name'], $subject, $html_body)) {
                    $_SESSION['fp_email'] = $email;
                    header("Location: verify_otp.php");
                    exit();
                } else {
                    $error = "Email could not be sent. Please try again.";
                }
            } else {
                $error = "Database error.";
            }
            $upd->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <style>
        :root { --blue:#3b6ef8; --blue2:#2452e0; --bg:rgba(59,110,248,.12); --ink:#0b1120; --muted:#7e8fa6; --white:#fff; }
        body { min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Instrument Sans',sans-serif; background:#e8edf8; position:relative; overflow:hidden; }
        .dots { position:fixed; inset:0; z-index:0; }
        .dot { position:absolute; border-radius:50%; animation:float linear infinite; }
        @keyframes float { 0% { transform:translateY(0); opacity:.7 } 100% { transform:translateY(-100vh); opacity:0 } }
        /* width:100% + padding ensures card never touches screen edges on mobile */
        .card { position:relative; z-index:1; width:100%; max-width:460px; background:var(--white); border-radius:24px; box-shadow:0 20px 60px rgba(0,0,0,0.1); overflow:hidden; }
        .topbar { height:4px; background:linear-gradient(90deg,#3b6ef8,#06b6d4); }
        .inner { padding:40px; }
        .steps { display:flex; gap:10px; margin-bottom:28px; }
        .step { flex:1; text-align:center; }
        .step-dot { width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; background:#f5f7fc; color:#7e8fa6; border:1px solid #dde3ef; }
        .step-dot.active { background:var(--blue); color:#fff; border-color:var(--blue); }
        .step-label { display:block; font-size:.65rem; font-weight:600; text-transform:uppercase; margin-top:5px; color:#7e8fa6; }
        .step.active .step-label { color:var(--blue); }
        h1 { font-family:'Bricolage Grotesque',sans-serif; font-size:1.8rem; margin-bottom:10px; color:var(--ink); }
        .irow { display:flex; border:1.5px solid #dde3ef; border-radius:12px; background:#f5f7fc; margin:20px 0; }
        .iico { padding:15px; color:#7e8fa6; border-right:1px solid #dde3ef; }
        .iinp { flex:1; padding:15px; border:none; background:transparent; outline:none; min-width:0; }
        /* Touch-friendly: 44px min-height on submit button */
        .btn { width:100%; padding:15px; min-height:44px; background:linear-gradient(135deg,var(--blue),var(--blue2)); color:#fff; border:none; border-radius:12px; font-weight:700; cursor:pointer; }
        .alert { padding:12px; border-radius:10px; margin-bottom:20px; font-size:.85rem; }
        .a-err { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
        .a-ok { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }

        /* ── Mobile: add body padding so card doesn't touch screen edges ── */
        @media(max-width:520px){
            body { align-items:flex-start; padding:24px 16px; }
            .card { border-radius:16px; }
            .inner { padding:24px 20px; }
            h1 { font-size:1.5rem; }
        }
        @media(max-width:360px){
            .inner { padding:20px 16px; }
            h1 { font-size:1.3rem; }
        }
    </style>
</head>
<body>
<div class="dots" id="dots"></div>
<div class="card">
    <div class="topbar"></div>
    <div class="inner">
        <div class="steps">
            <div class="step active"><div class="step-dot active">1</div><span class="step-label">Email</span></div>
            <div class="step"><div class="step-dot">2</div><span class="step-label">Verify</span></div>
            <div class="step"><div class="step-dot">3</div><span class="step-label">Reset</span></div>
        </div>
        <h1>Forgot Password</h1>
        <p style="color:#7e8fa6; font-size:.9rem;">Enter your email to receive a 6-digit security code.</p>
        
        <?php if ($error): ?><div class="alert a-err"><?=$error?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert a-ok"><?=$success?></div><?php endif; ?>

        <form method="POST">
            <div class="irow">
                <span class="iico"><i class="fas fa-envelope"></i></span>
                <input class="iinp" type="email" name="email" placeholder="you@example.com" required autofocus>
            </div>
            <button type="submit" name="send_otp" class="btn">Send OTP</button>
        </form>
        <div style="margin-top:20px; text-align:center;">
            <a href="login.php" style="text-decoration:none; color:#7e8fa6; font-size:.85rem;"><i class="fas fa-arrow-left"></i> Back to login</a>
        </div>
    </div>
</div>
<script>
    const c=document.getElementById('dots');
    for(let i=0;i<10;i++){
        const d=document.createElement('div');
        d.className='dot';
        d.style.cssText=`width:5px;height:5px;left:${Math.random()*100}%;bottom:0;background:rgba(59,110,248,0.1);animation-duration:${Math.random()*10+5}s;`;
        c.appendChild(d);
    }
</script>
</body>
</html>