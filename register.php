<?php
include("config/db.php");

$message = "";
$success = false;

if(isset($_POST['register'])){
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if(empty($name)||empty($email)||empty($password)){
        $message = "Please fill in all fields.";
    } elseif(strlen($name)<2){
        $message = "Full name must be at least 2 characters.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $message = "Please enter a valid email address.";
    } elseif(strlen($password)<6){
        $message = "Password must be at least 6 characters.";
    } else {
        $hash  = password_hash($password, PASSWORD_DEFAULT);
        $check = $conn->prepare("SELECT id FROM users WHERE email=?");
        $check->bind_param("s",$email); $check->execute(); $check->store_result();
        if($check->num_rows>0){
            $message = "This email is already registered.";
        } else {
            $role   = "student";
            $status = "active";
            $s = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $s->bind_param("sssss", $name, $email, $hash, $role, $status);
            if($s->execute()){
                $s->close();
                $message = "Registration successful! Your account is ready.";
                $success  = true;
            } else {
                $s->close();
                $message = "Registration failed. Please try again.";
            }
        }
        $check->close();
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — CourseFlow LMS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { 
    font-family: 'Inter', sans-serif; 
    background: #0f62c6; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    min-height: 100vh; 
    padding: 24px;
}
.login-container {
    background: #fff;
    width: 100%;
    max-width: 900px;
    display: flex;
    border-radius: 20px;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4), 0 0 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    min-height: 650px;
    position: relative;
}

/* RIGHT BOTTOM CIRCLE OVERLAY */
.container-bg-shape {
    position: absolute;
    bottom: -60px;
    right: -60px;
    width: 180px;
    height: 180px;
    background: #0e6df2;
    border-radius: 50%;
    z-index: 1;
}

/* LEFT PANEL */
.lp {
    width: 45%;
    background: #0854c4;
    color: #fff;
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
    z-index: 2;
    border-radius: 20px 0 0 20px;
}
.lp::before {
    content: '';
    position: absolute;
    bottom: -70px;
    left: -50px;
    width: 250px;
    height: 250px;
    background: linear-gradient(135deg, #0f62c6, #0854c4);
    border-radius: 50%;
    box-shadow: -10px -10px 30px rgba(0,0,0,0.1);
}
.lp::after {
    content: '';
    position: absolute;
    bottom: 30px;
    right: -60px;
    width: 280px;
    height: 280px;
    background: linear-gradient(135deg, #0f62c6, #0854c4);
    border-radius: 50%;
    box-shadow: -10px -10px 30px rgba(0,0,0,0.1);
}
.lp-content {
    z-index: 3;
    position: relative;
}
.lp-logo {
    font-size: 2.2rem;
    font-weight: 800;
    letter-spacing: 2px;
    margin-bottom: 8px;
    text-transform: uppercase;
}
.lp-title {
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 1px;
    margin-bottom: 24px;
    text-transform: uppercase;
}
.lp-desc {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.85);
    line-height: 1.8;
}

/* RIGHT PANEL */
.rp {
    width: 55%;
    padding: 60px 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: #fff;
    z-index: 2;
    position: relative;
}
.form-title {
    font-size: 2.2rem;
    font-weight: 800;
    color: #333;
    margin-bottom: 8px;
    text-align: center;
}
.form-subtitle {
    color: #888;
    font-size: 0.85rem;
    margin-bottom: 30px;
    text-align: center;
}

/* FORMS */
.form-group {
    margin-bottom: 16px;
}
.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    border: 1px solid transparent;
    border-radius: 8px;
    background: #f4f5f7;
    transition: all 0.2s;
}
.input-wrapper.has-err { border-color: #e53e3e; background: #fff5f5; }
.input-wrapper.has-ok { border-color: #38a169; background: #f0fff4; }
.input-wrapper i {
    position: absolute;
    left: 16px;
    color: #444;
    font-size: 1.1rem;
}
.input-wrapper.has-err i:not(.eye-icon) { color: #e53e3e; }
.input-wrapper.has-ok i:not(.eye-icon) { color: #38a169; }

.input-wrapper input {
    width: 100%;
    padding: 16px 16px 16px 50px;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    color: #333;
    background: transparent;
    outline: none;
    font-weight: 500;
}
.input-wrapper input::placeholder {
    color: #999;
}
.input-wrapper .eye-icon {
    left: auto;
    right: 16px;
    color: #888;
    cursor: pointer;
}
.input-wrapper .eye-icon:hover {
    color: #333;
}

/* VALIDATION HINTS */
.vs-hint{display:none;font-size:.74rem;font-weight:500;margin-top:6px;align-items:center;gap:5px;}
.vs-hint.show{display:flex;}
.vs-hint.err{color:#e53e3e;}
.vs-hint.ok{color:#38a169;}
.email-fb{display:none;font-size:.74rem;font-weight:500;margin-top:6px;align-items:center;gap:5px;}
.email-fb.show{display:flex;}
.pwd-strength{margin-top:8px;display:none;}
.pwd-strength.show{display:block;}
.str-bars{display:flex;gap:5px;margin-bottom:5px;}
.str-bar{flex:1;height:4px;border-radius:2px;background:#e0e0e0;transition:background .3s;}
.str-label{display:flex;align-items:center;justify-content:space-between;font-size:.72rem;font-weight:600;}
.str-tips{margin-top:8px;padding:10px;background:#f4f5f7;border-radius:8px;font-size:.73rem;color:#888;display:none;}
.str-tips.show{display:block;}
.str-tip{display:flex;align-items:center;gap:6px;}
.str-tip.pass{color:#38a169;}
.str-tip.fail{color:#888;}

/* BUTTONS */
.submit-btn {
    width: 100%;
    padding: 16px;
    background: #0d47a1;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    margin-top: 10px;
    margin-bottom: 20px;
    transition: background 0.2s;
}
.submit-btn:hover {
    background: #0a367a;
}
.divider {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #888;
    font-size: 0.8rem;
    margin-bottom: 20px;
}
.divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e0e0e0;
}
.g-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    padding: 14px;
    border: 1px solid #333;
    border-radius: 8px;
    background: #fff;
    color: #333;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    cursor: pointer;
    margin-bottom: 30px;
    transition: background 0.2s;
}
.g-btn:hover { background: #f9f9f9; }
.g-btn svg { width: 24px; height: 24px; flex-shrink: 0; }

.footer-text {
    text-align: center;
    font-size: 0.8rem;
    color: #888;
}
.footer-text a {
    color: #0854c4;
    font-weight: 600;
    text-decoration: none;
}
.back-home {
    display: block;
    text-align: center;
    margin-top: 16px;
    font-size: 0.8rem;
    color: #888;
    text-decoration: none;
    font-weight: 500;
}
.back-home:hover {
    color: #333;
}
.vs-alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; font-weight: 500; }
.vs-alert.err { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
.vs-alert.ok { background: #f0fdf4; color: #22c55e; border: 1px solid #bbf7d0; }

@media(max-width: 900px) {
    .lp { display: none; }
    .rp { width: 100%; padding: 40px 32px; }
}
</style>
</head>
<body>

<div class="login-container">
    <div class="container-bg-shape"></div>

    <!-- LEFT PANEL -->
    <div class="lp">
        <div class="lp-content">
            <div class="lp-logo">START YOUR JOURNEY</div>
            <div class="lp-title">CREATE AN ACCOUNT</div>
            <div class="lp-desc">
                Join our global community of learners.<br><br>
                Get full access to expert-led courses, hands-on projects, and earn industry-recognized certificates.<br><br>
                Free to Join &nbsp;|&nbsp; Unlimited Access
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="rp">
        <h2 class="form-title">Sign up</h2>
        <p class="form-subtitle">Already have an account? <a href="login.php" style="color:#0854c4;text-decoration:none;font-weight:600;">Sign in &rarr;</a></p>

        <?php if($message): ?>
        <div class="vs-alert <?=$success?'ok':'err'?>" id="srv-msg">
            <i class="fas fa-<?=$success?'check-circle':'exclamation-circle'?>"></i>
            <div>
                <?=htmlspecialchars($message)?>
                <?php if($success): ?>
                <br><a href="login.php" style="color:#38a169;font-weight:700;text-decoration:underline;font-size:.82rem;">Sign in now &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if(!$success): ?>
        <a href="google_login.php" class="g-btn">
            <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.6 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 2.9l6-6C34.5 5.1 29.6 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.4-.1-2.7-.4-3.5z"/>
                <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.5 16 18.9 13 24 13c3.1 0 5.8 1.1 8 2.9l6-6C34.5 5.1 29.6 3 24 3 16.3 3 9.7 7.3 6.3 14.7z"/>
                <path fill="#4CAF50" d="M24 45c5.5 0 10.4-2.1 14.1-5.5l-6.5-5.4C29.6 35.9 26.9 37 24 37c-5.2 0-9.6-3.3-11.3-8l-6.6 5.1C9.6 40.6 16.3 45 24 45z"/>
                <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3.1-3.4 5.6-6.5 7.1l6.5 5.4C39.4 37.4 43 31.2 43 24c0-1.4-.1-2.7-.4-3.5z"/>
            </svg>
            Continue with Google
        </a>

        <div class="divider">Or sign up with email</div>

        <form method="POST" id="regForm" novalidate>
            <!-- Full Name -->
            <div class="form-group">
                <div class="input-wrapper" id="w-name">
                    <i class="fas fa-user"></i>
                    <input type="text" id="rname" name="name" placeholder="Full name" value="<?=isset($_POST['name'])?htmlspecialchars($_POST['name']):''; ?>" autocomplete="name" required>
                </div>
                <div class="vs-hint err" id="he-name"><i class="fas fa-times-circle"></i><span></span></div>
            </div>

            <!-- Email Address -->
            <div class="form-group">
                <div class="input-wrapper" id="w-email">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="remail" name="email" placeholder="Email address" value="<?=isset($_POST['email'])?htmlspecialchars($_POST['email']):''; ?>" autocomplete="email" required>
                </div>
                <div class="email-fb" id="email-fb"></div>
                <div class="vs-hint err" id="he-email"><i class="fas fa-times-circle"></i><span></span></div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <div class="input-wrapper" id="w-pass">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="rpass" name="password" placeholder="Create a strong password" autocomplete="new-password" required>
                    <i class="fas fa-eye eye-icon" id="eyeIcon"></i>
                </div>
                <div class="pwd-strength" id="pwd-strength">
                    <div class="str-bars">
                        <div class="str-bar" id="sb1"></div><div class="str-bar" id="sb2"></div>
                        <div class="str-bar" id="sb3"></div><div class="str-bar" id="sb4"></div>
                    </div>
                    <div class="str-label">
                        <span id="str-lbl" style="color:#888">Strength</span>
                        <span id="str-pct" style="color:#888;font-size:.7rem;"></span>
                    </div>
                    <div class="str-tips" id="str-tips">
                        <div class="str-tip" id="tip-len"><i class="fas fa-circle" style="font-size:.45rem;"></i> At least 6 characters</div>
                        <div class="str-tip" id="tip-case"><i class="fas fa-circle" style="font-size:.45rem;"></i> Upper &amp; lowercase letters</div>
                        <div class="str-tip" id="tip-num"><i class="fas fa-circle" style="font-size:.45rem;"></i> Contains a number</div>
                        <div class="str-tip" id="tip-sym"><i class="fas fa-circle" style="font-size:.45rem;"></i> Special character (!@#$)</div>
                    </div>
                </div>
                <div class="vs-hint err" id="he-pass"><i class="fas fa-times-circle"></i><span></span></div>
            </div>

            <button type="submit" name="register" class="submit-btn">
                Create Account
            </button>
        </form>
        <?php endif; ?>

        <div class="footer-text">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>

        <a href="index.php" class="back-home">&larr; Back to Home</a>

    </div>
</div>

<script>
function setErr(w,h,m){const wr=document.getElementById(w);wr.classList.add('has-err');wr.classList.remove('has-ok');const hh=document.getElementById(h);hh.querySelector('span').textContent=m;hh.classList.add('show');}
function setOk(w,h){const wr=document.getElementById(w);wr.classList.remove('has-err');wr.classList.add('has-ok');document.getElementById(h).classList.remove('show');}
function clr(w,h){const wr=document.getElementById(w);wr.classList.remove('has-err','has-ok');document.getElementById(h).classList.remove('show');}

// Email live validation
const emailRe=/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;
let eTmr;
document.getElementById('remail')?.addEventListener('input',function(){
  clr('w-email','he-email');
  const fb=document.getElementById('email-fb');
  fb.className='email-fb';clearTimeout(eTmr);
  const v=this.value.trim();if(!v)return;
  eTmr=setTimeout(()=>{
    if(emailRe.test(v)){
      fb.innerHTML='<i class="fas fa-check-circle" style="color:#38a169"></i><span style="color:#38a169">Valid email address</span>';
      fb.classList.add('show');setOk('w-email','he-email');
    } else {
      fb.innerHTML='<i class="fas fa-times-circle" style="color:#e53e3e"></i><span style="color:#e53e3e">Enter a valid email (e.g. name@domain.com)</span>';
      fb.classList.add('show');setErr('w-email','he-email','Invalid email format.');
    }
  },500);
});

document.getElementById('rname')?.addEventListener('input',()=>clr('w-name','he-name'));

// Password strength
const colors=['#e53e3e','#d97706','#3b82f6','#38a169'];
const labels=['Weak','Fair','Good','Strong'];
const bars=[document.getElementById('sb1'),document.getElementById('sb2'),document.getElementById('sb3'),document.getElementById('sb4')];

function calcStr(p){
  let s=0;
  const checks={len:p.length>=6,case:/[A-Z]/.test(p)&&/[a-z]/.test(p),num:/[0-9]/.test(p),sym:/[^A-Za-z0-9]/.test(p)};
  if(checks.len)s++;if(checks.case)s++;if(checks.num)s++;if(checks.sym)s++;
  return{score:Math.max(0,Math.min(3,s-1+(s>0?0:0)),s>0?s-1:0),checks,raw:s};
}

document.getElementById('rpass')?.addEventListener('input',function(){
  clr('w-pass','he-pass');
  const v=this.value;
  const ps=document.getElementById('pwd-strength');
  const tips=document.getElementById('str-tips');
  if(!v){ps.classList.remove('show');tips.classList.remove('show');return;}
  ps.classList.add('show');tips.classList.add('show');

  const {score,checks,raw}=calcStr(v);
  const actualLvl=raw===0?0:raw===1?0:raw===2?1:raw===3?2:3;

  bars.forEach((b,i)=>{if(b) b.style.background=i<=actualLvl?colors[actualLvl]:'#e0e0e0';});
  document.getElementById('str-lbl').textContent=labels[actualLvl];
  document.getElementById('str-lbl').style.color=colors[actualLvl];
  document.getElementById('str-pct').textContent=Math.round((raw/4)*100)+'%';

  const tipMap=[
    {id:'tip-len',pass:checks.len},
    {id:'tip-case',pass:checks.case},
    {id:'tip-num',pass:checks.num},
    {id:'tip-sym',pass:checks.sym},
  ];
  tipMap.forEach(t=>{
    const el=document.getElementById(t.id);
    if(el) {
        el.className='str-tip '+(t.pass?'pass':'fail');
        el.querySelector('i').className='fas '+(t.pass?'fa-check-circle':'fa-circle');
        el.querySelector('i').style.fontSize=t.pass?'.75rem':'.45rem';
    }
  });
});

// Password toggle
document.getElementById('eyeIcon')?.addEventListener('click',function(){
  const i=document.getElementById('rpass');
  if (i.type === 'password') {
      i.type = 'text';
      this.classList.remove('fa-eye');
      this.classList.add('fa-eye-slash');
  } else {
      i.type = 'password';
      this.classList.remove('fa-eye-slash');
      this.classList.add('fa-eye');
  }
});

// Form submit
document.getElementById('regForm')?.addEventListener('submit',function(e){
  let ok=true;
  const name=document.getElementById('rname').value.trim();
  const email=document.getElementById('remail').value.trim();
  const pass=document.getElementById('rpass').value;

  clr('w-name','he-name');clr('w-email','he-email');clr('w-pass','he-pass');

  if(!name){setErr('w-name','he-name','Full name is required.');ok=false;}
  else if(name.length<2){setErr('w-name','he-name','Name must be at least 2 characters.');ok=false;}

  if(!email){setErr('w-email','he-email','Email address is required.');ok=false;}
  else if(!emailRe.test(email)){setErr('w-email','he-email','Enter a valid email address.');ok=false;}

  if(!pass){setErr('w-pass','he-pass','Password is required.');ok=false;}
  else if(pass.length<6){setErr('w-pass','he-pass','Password must be at least 6 characters.');ok=false;}

  if(!ok) e.preventDefault();
});
</script>
</body>
</html>