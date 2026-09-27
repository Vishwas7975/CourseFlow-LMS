<?php
session_start();
include("../config/db.php");
include("../includes/password_handler.php"); // ⭐ shared handler
include("../includes/auth_check.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin"){
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

/* =========================
   HANDLE FORM SUBMISSION
========================= */
if($_SERVER['REQUEST_METHOD'] == 'POST'){

    /* ---------- UPDATE PROFILE ---------- */
    if(isset($_POST['update_profile'])){

        $name   = trim($_POST['name']);
        $mobile = trim($_POST['mobile']);

        $stmt = $conn->prepare("UPDATE users SET name=?, mobile=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $mobile, $user_id);

        if($stmt->execute()){
            $_SESSION['name'] = $name;
            $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Profile updated successfully!</div>';
        } else {
            $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Failed to update profile.</div>';
        }
    }

    /* ---------- CHANGE PASSWORD (SHARED LOGIC) ---------- */
    if(isset($_POST['change_password'])){

        $result = changePassword(
            $conn,
            $user_id,
            $_POST['current_password'],
            $_POST['new_password'],
            $_POST['confirm_password']
        );

        if($result === "success"){

            // OPTIONAL SECURITY (recommended)
            // session_destroy();
            // header("Location: ../login.php");
            // exit();

            $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Password changed successfully!</div>';

        } else {

            $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> '.$result.'</div>';
        }
    }
}

/* =========================
   FETCH USER DATA
========================= */
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

$page_title = "Admin Profile";
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Profile - CourseFlow LMS</title>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
/* ── admin/profile.php mobile fixes ──
   All grids here use inline style="" so we target them via
   attribute selectors — zero logic or markup changed. */

/* Outer layout: profile card (280px) + form panel → stack on tablet */
@media(max-width:900px){
    div[style*="grid-template-columns:280px 1fr"],
    div[style*="grid-template-columns: 280px 1fr"]{
        grid-template-columns: 1fr !important;
    }
}

/* Form 2-col (name/email rows) → 1-col on mobile */
@media(max-width:600px){
    div[style*="grid-template-columns:1fr 1fr"],
    div[style*="grid-template-columns: 1fr 1fr"]{
        grid-template-columns: 1fr !important;
    }
    /* Form 3-col (password fields) → 1-col on mobile */
    div[style*="grid-template-columns:1fr 1fr 1fr"],
    div[style*="grid-template-columns: 1fr 1fr 1fr"]{
        grid-template-columns: 1fr !important;
    }
    /* Submit buttons: touch-friendly */
    .btn { min-height:44px; }
}
</style>
</head>

<body>
<div class="dashboard">

<?php include("../includes/sidebar.php"); ?>

<div class="main">

<?php include("../includes/topbar.php"); ?>

<div class="content-wrapper">

<div class="page-header">
<h1><i class="fas fa-user-shield"></i> Admin Profile</h1>
<p>Manage your administrator account.</p>
</div>

<?php echo $msg; ?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start">

<!-- LEFT CARD -->
<div style="background:var(--card);border-radius:var(--radius-xl);padding:28px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light);text-align:center">

<div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#F59E0B,#D97706);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;margin:0 auto 14px">
<?php echo strtoupper(substr($user['name'],0,1)); ?>
</div>

<h3 style="font-size:1rem;font-weight:800;margin-bottom:4px">
<?php echo htmlspecialchars($user['name']); ?>
</h3>

<div style="display:inline-block;background:var(--warning-light);color:#D97706;font-size:0.72rem;font-weight:700;padding:3px 12px;border-radius:var(--radius-full);margin-bottom:16px">
<i class="fas fa-crown"></i> Administrator
</div>

<div style="text-align:left;display:flex;flex-direction:column;gap:10px">
<div style="display:flex;align-items:center;gap:10px;font-size:0.82rem;color:var(--text-secondary)">
<i class="fas fa-envelope" style="color:#F59E0B;width:16px"></i>
<?php echo htmlspecialchars($user['email']); ?>
</div>

<?php if(!empty($user['mobile'])): ?>
<div style="display:flex;align-items:center;gap:10px;font-size:0.82rem;color:var(--text-secondary)">
<i class="fas fa-phone" style="color:#F59E0B;width:16px"></i>
<?php echo htmlspecialchars($user['mobile']); ?>
</div>
<?php endif; ?>
</div>

</div>

<!-- RIGHT SIDE -->
<div style="display:flex;flex-direction:column;gap:24px">

<!-- EDIT PROFILE -->
<div style="background:var(--card);border-radius:var(--radius-xl);padding:28px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light)">
<h2 style="font-size:1.05rem;font-weight:700;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border-light)">
<i class="fas fa-pen"></i> Edit Profile
</h2>

<form method="POST" style="background:none;border:none;box-shadow:none;padding:0;margin:0">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

<div class="form-group">
<label>Full Name</label>
<div class="input-group">
<i class="fas fa-user input-icon"></i>
<input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
</div>
</div>

<div class="form-group">
<label>Email Address</label>
<input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
</div>

<div class="form-group" style="grid-column:1/-1">
<label>Mobile Number</label>
<div class="input-group">
<i class="fas fa-phone input-icon"></i>
<input type="text" name="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>">
</div>
</div>

</div>

<button type="submit" name="update_profile" class="btn btn-primary">
<i class="fas fa-save"></i> Save Changes
</button>
</form>
</div>

<!-- CHANGE PASSWORD -->
<div style="background:var(--card);border-radius:var(--radius-xl);padding:28px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light)">
<h2 style="font-size:1.05rem;font-weight:700;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border-light)">
<i class="fas fa-lock"></i> Change Password
</h2>

<form method="POST" style="background:none;border:none;box-shadow:none;padding:0;margin:0">
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">

<div class="form-group">
<label>Current Password</label>
<input type="password" name="current_password" required>
</div>

<div class="form-group">
<label>New Password</label>
<input type="password" name="new_password" required>
</div>

<div class="form-group">
<label>Confirm Password</label>
<input type="password" name="confirm_password" required>
</div>

</div>

<button type="submit" name="change_password" class="btn btn-secondary">
<i class="fas fa-key"></i> Update Password
</button>
</form>
</div>

</div>
</div>
</div>
</div>
</div>

<?php include("../includes/footer.php"); ?>
</body>
</html>