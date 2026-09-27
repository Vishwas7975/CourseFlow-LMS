<?php
session_start();
include("config/db.php");

$error = "";

if(isset($_GET['error']) && $_GET['error'] === 'oauth_failed'){
    $error = "Google sign-in didn't go through. Please try again or sign in with email and password.";
} elseif(isset($_GET['error']) && $_GET['error'] === 'inactive'){
    $error = "Your account is inactive. Please contact admin.";
}

if(isset($_POST['login'])){
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if(empty($email) || empty($password)){
        $error = "Please fill all fields.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows > 0){
            $user = $result->fetch_assoc();
            if(password_verify($password, $user['password'] ?? '')){
                if($user['status'] != 'active'){
                    $error = "Your account is inactive. Please contact admin.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['role']    = $user['role'];
                    $_SESSION['email']   = $user['email']; // ← FIX: required by offer_letter.php and other pages

                    // Remember Me — set a 30-day secure cookie
                    if (isset($_POST['remember'])) {
                        $token = base64_encode($user['id'] . '|' . hash('sha256', $user['password'] . session_id()));
                        setcookie('vs_remember', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                    }

                    if($user['role'] == "admin")       header("Location: admin/dashboard.php");
                    elseif($user['role'] == "trainer") header("Location: trainer/dashboard.php");
                    elseif($user['role'] == "finance") header("Location: finance/dashboard.php");
                    else                               header("Location: student/dashboard.php");
                    exit();
                }
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "No account found with this email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — CourseFlow LMS</title>
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
    min-height: 500px;
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
}
.input-wrapper i {
    position: absolute;
    left: 16px;
    color: #444;
    font-size: 1.1rem;
}
.input-wrapper input {
    width: 100%;
    padding: 16px 16px 16px 50px;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    color: #333;
    background: #f4f5f7;
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

/* OPTIONS ROW */
.options-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.checkbox-group input {
    width: 16px;
    height: 16px;
    accent-color: #0854c4;
    cursor: pointer;
}
.checkbox-group label {
    font-size: 0.8rem;
    color: #555;
    font-weight: 500;
    cursor: pointer;
}
.forgot-link {
    color: #0854c4;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
}

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
            <div class="lp-logo">WELCOME</div>
            <div class="lp-title">CourseFlow LMS</div>
            <div class="lp-desc">
                Master Skills. Shape Your Future.<br><br>
                Join thousands of students building career-ready expertise in AI, finance, and cutting-edge tech.<br><br>
                12K+ Students &nbsp;|&nbsp; 98% Satisfaction &nbsp;|&nbsp; 50+ Courses
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="rp">
        <h2 class="form-title">Sign in</h2>
        <p class="form-subtitle">Don't have an account? <a href="register.php" style="color:#0854c4;text-decoration:none;font-weight:600;">Create one free</a></p>

        <?php if(isset($_GET['logout']) && $_GET['logout']=='success'): ?>
            <div class="vs-alert ok"><i class="fas fa-check-circle"></i> You've been logged out successfully.</div>
        <?php endif; ?>
        <?php if(isset($_GET['reset']) && $_GET['reset']=='success'): ?>
            <div class="vs-alert ok"><i class="fas fa-check-circle"></i> Password reset successful. Please sign in.</div>
        <?php endif; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg']==='pay_later'): ?>
            <div class="vs-alert ok" style="background:#eff6ff;color:#1e3a8a;border-color:#bfdbfe;"><i class="fas fa-clock" style="color:#1d4ed8;"></i> <span><strong>Registration successful!</strong> Log in with your credentials to complete your internship payment anytime.</span></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="vs-alert err"><i class="fas fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="email" name="email" placeholder="Email address" value="<?=isset($_POST['email'])?htmlspecialchars($_POST['email']):''?>" required>
                </div>
            </div>

            <div class="form-group">
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="passInput" name="password" placeholder="Password" required>
                    <i class="fas fa-eye eye-icon" id="eyeIcon"></i>
                </div>
            </div>

            <div class="options-row">
                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember" <?=isset($_POST['remember'])?'checked':''?>>
                    <label for="remember">Remember me</label>
                </div>
                <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="submit-btn">
                Sign in
            </button>
        </form>

        <div class="divider">Or</div>

        <a href="google_login.php" class="g-btn">
            Sign in with Google
        </a>

        <div class="footer-text">
            Don't have an account? <a href="register.php">Sign Up</a>
        </div>

        <a href="index.php" class="back-home">&larr; Back to Home</a>
    </div>
</div>

<script>
// Password toggle
document.getElementById('eyeIcon').addEventListener('click', function() {
    const input = document.getElementById('passInput');
    if (input.type === 'password') {
        input.type = 'text';
        this.classList.remove('fa-eye');
        this.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        this.classList.remove('fa-eye-slash');
        this.classList.add('fa-eye');
    }
});
</script>

</body>
</html>