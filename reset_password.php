<?php
ob_start(); 
session_start();
include("config/db.php");

// 1. Security Check: Redirect if they haven't verified OTP
if (!isset($_SESSION['fp_verified']) || $_SESSION['fp_verified'] !== true) {
    header("Location: forgot_password.php");
    exit();
}

$error = "";
$email = $_SESSION['fp_email'] ?? '';

// 2. Handle Password Submission
if (isset($_POST['reset_password'])) {
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (empty($pass1) || empty($pass2)) {
        $error = "Please fill in all fields.";
    } elseif ($pass1 !== $pass2) {
        $error = "Passwords do not match!";
    } elseif (strlen($pass1) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $hashed_password = password_hash($pass1, PASSWORD_BCRYPT);

        // Update the 'password' column and wipe the reset token
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);

        if ($stmt->execute()) {
            // SUCCESS: Clear session and redirect to login
            session_destroy(); 
            header("Location: login.php?reset=success");
            exit();
        } else {
            $error = "Database error. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
          --ink:#0b1120;--ink2:#1e2d4a;--muted:#7e8fa6;
          --border:#dde3ef;--surf:#f5f7fc;--white:#fff;--r:13px;
          --blue:#3b6ef8;--blue2:#2452e0;--bg:rgba(59,110,248,.12);
          --red:#dc2626;--rbg:#fef2f2;--rbdr:#fecaca;
          --shadow: 0 2px 4px rgba(11,17,32,.04), 0 20px 60px rgba(11,17,32,.10), 0 0 0 1px #e2e8f4;
        }
        body{
          min-height:100vh;display:flex;align-items:center;justify-content:center;
          padding:24px 16px; font-family:'Instrument Sans',sans-serif;
          background: linear-gradient(145deg, #e8edf8 0%, #edf0fa 50%, #e4eaf6 100%);
          position:relative;overflow:hidden;
        }
        .bg-shape{position:fixed;border-radius:50%;pointer-events:none;z-index:0;opacity:.6;}
        .s1{width:600px;height:600px;top:-250px;left:-150px;background:radial-gradient(circle,rgba(59,110,248,.09) 0%,transparent 70%);}
        .s2{width:450px;height:450px;bottom:-150px;right:-100px;background:radial-gradient(circle,rgba(99,102,241,.07) 0%,transparent 70%);}
        .dots{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;}
        .dot{position:absolute;border-radius:50%;animation:float linear infinite;}
        @keyframes float{0%{transform:translateY(0) rotate(0deg);opacity:.7}100%{transform:translateY(-100vh) rotate(360deg);opacity:0}}

        .card{
          position:relative;z-index:1;width:100%;max-width:460px;
          background:var(--white);border-radius:24px; box-shadow: var(--shadow);
          overflow:hidden; animation:cardIn .55s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes cardIn{from{opacity:0;transform:translateY(28px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
        .topbar{height:4px;background:linear-gradient(90deg,#3b6ef8 0%,#818cf8 50%,#06b6d4 100%);}
        .inner{padding:40px 40px 36px;}
        
        .steps{display:flex;align-items:center;gap:0;margin-bottom:28px;}
        .step{display:flex;flex-direction:column;align-items:center;gap:5px;flex:1;position:relative;}
        .step:not(:last-child)::after{content:'';position:absolute;top:13px;left:calc(50% + 13px);width:calc(100% - 26px);height:1.5px;background: #16a34a;}
        .step-dot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;}
        .step-dot.done{background:#16a34a;color:#fff;}
        .step-dot.active{background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff;}
        .step-label{font-size:.62rem;font-weight:600;color:var(--muted);letter-spacing:.04em;text-transform:uppercase;}
        
        .icon-area{margin-bottom:22px;position:relative;display:inline-block;}
        .icon-ring{width:68px;height:68px;border-radius:20px;background:linear-gradient(135deg,rgba(59,110,248,.12),rgba(59,110,248,.04));border:1.5px solid rgba(59,110,248,.16);display:flex;align-items:center;justify-content:center;font-size:1.55rem;color:var(--blue);}
        
        h1{font-family:'Bricolage Grotesque',sans-serif;font-size:1.8rem;font-weight:800;color:var(--ink);line-height:1.16;margin-bottom:9px;}
        .desc{font-size:.875rem;color:var(--muted);line-height:1.68;margin-bottom:20px;}
        
        .alert{display:flex;align-items:flex-start;gap:11px;padding:13px 16px;border-radius:12px;font-size:.84rem;font-weight:500;margin-bottom:22px;}
        .a-err{background:var(--rbg);color:var(--red);border:1px solid var(--rbdr);}
        
        .field{margin-bottom:20px;}
        .lbl{display:block;font-size:.79rem;font-weight:600;color:var(--ink2);margin-bottom:8px;}
        .irow{display:flex;align-items:stretch;border:1.5px solid var(--border);border-radius:var(--r);background:var(--surf);overflow:hidden;}
        .irow:focus-within{border-color:var(--blue);background:var(--white);box-shadow:0 0 0 4px var(--bg);}
        .iico{width:48px;display:flex;align-items:center;justify-content:center;color:var(--muted);border-right:1.5px solid var(--border);}
        .iinp{flex:1;padding:13px 15px;border:none;background:transparent;font-size:.9rem;outline:none;}
        
        .btn{width:100%;padding:14.5px;min-height:44px;background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff;border:none;cursor:pointer;border-radius:var(--r);font-family:'Bricolage Grotesque',sans-serif;font-size:.96rem;font-weight:700;display:flex;align-items:center;justify-content:center;gap:11px;margin-top:10px;}
        .btn-icon{width:24px;height:24px;border-radius:7px;background:rgba(255,255,255,.22);display:flex;align-items:center;justify-content:center;font-size:.72rem;}

        /* ── Mobile: body padding stops card from touching screen edges ── */
        @media(max-width:520px){
            body{align-items:flex-start;padding:24px 16px;}
            .card{border-radius:16px;}
            .inner{padding:24px 20px 20px;}
            h1{font-size:1.5rem;}
            .iinp{min-width:0;}
        }
        @media(max-width:360px){
            body{padding:16px 12px;}
            .inner{padding:20px 16px;}
            h1{font-size:1.3rem;}
        }
    </style>
</head>
<body>
<div class="bg-shape s1"></div>
<div class="bg-shape s2"></div>
<div class="dots" id="dots"></div>

<div class="card">
    <div class="topbar"></div>
    <div class="inner">
        <div class="steps">
            <div class="step"><div class="step-dot done"><i class="fas fa-check"></i></div><span class="step-label">Email</span></div>
            <div class="step"><div class="step-dot done"><i class="fas fa-check"></i></div><span class="step-label">Verify</span></div>
            <div class="step"><div class="step-dot active">3</div><span class="step-label" style="color:var(--blue)">Reset</span></div>
        </div>

        <div class="icon-area">
            <div class="icon-ring"><i class="fas fa-key"></i></div>
        </div>
        
        <h1>Create new password</h1>
        <p class="desc">Almost there! Please choose a strong password for your CourseFlow account.</p>

        <?php if ($error): ?>
            <div class="alert a-err"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label class="lbl">New Password</label>
                <div class="irow">
                    <span class="iico"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="iinp" placeholder="At least 8 characters" required autofocus>
                </div>
            </div>

            <div class="field">
                <label class="lbl">Confirm Password</label>
                <div class="irow">
                    <span class="iico"><i class="fas fa-check-double"></i></span>
                    <input type="password" name="password2" class="iinp" placeholder="Repeat new password" required>
                </div>
            </div>
            
            <button type="submit" name="reset_password" class="btn">
                Update Password <span class="btn-icon"><i class="fas fa-save"></i></span>
            </button>
        </form>
    </div>
</div>

<script>
(function(){
  const c=document.getElementById('dots');
  for(let i=0;i<14;i++){
    const d=document.createElement('div');
    const size=Math.random()*5+3;
    d.classList.add('dot');
    d.style.cssText=`width:${size}px;height:${size}px;left:${Math.random()*100}%;bottom:${Math.random()*-10}%;background:rgba(59,110,248,${Math.random()*.18+.04});animation-duration:${Math.random()*14+10}s;animation-delay:${Math.random()*8}s;`;
    c.appendChild(d);
  }
})();
</script>
</body>
</html>
<?php ob_end_flush(); ?>