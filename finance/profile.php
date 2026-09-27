<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("finance");

$user_id = $_SESSION['user_id'];
$msg = "";

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST'){

    if(isset($_POST['update_profile'])){
        $name   = trim($_POST['name']);
        $mobile = trim($_POST['mobile']);
        $stmt = $conn->prepare("UPDATE users SET name=?, mobile=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $mobile, $user_id);
        if($stmt->execute()){
            $_SESSION['name'] = $name;
            $msg = 'success|Profile updated successfully!';
        } else {
            $msg = 'error|Failed to update profile.';
        }
    }

    if(isset($_POST['change_password'])){
        $current = $_POST['current_password'];
        $new     = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if(!password_verify($current, $res['password'])){
            $msg = 'error|Current password is incorrect.';
        } elseif(strlen($new) < 6){
            $msg = 'error|New password must be at least 6 characters.';
        } elseif($new !== $confirm){
            $msg = 'error|New passwords do not match.';
        } else {
            $hashed = password_hash($new, PASSWORD_BCRYPT);
            $stmt2  = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt2->bind_param("si", $hashed, $user_id);
            if($stmt2->execute()){
                $msg = 'success|Password changed successfully!';
            } else {
                $msg = 'error|Failed to change password.';
            }
        }
    }
}

// Fetch user
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Parse msg
$msg_type = $msg_text = '';
if($msg){ [$msg_type, $msg_text] = explode('|', $msg, 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — CourseFlow Finance</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png?v=2">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    .prof-grid { display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start; }
    @media(max-width:800px){ .prof-grid { grid-template-columns:1fr; } }

    .prof-card {
        background:#fff;border-radius:18px;
        border:1px solid #eef2f7;
        box-shadow:0 2px 12px rgba(15,23,42,.05);
        padding:28px;
    }

    .prof-avatar {
        width:84px;height:84px;border-radius:50%;
        background:linear-gradient(135deg,#10b981,#059669);
        display:flex;align-items:center;justify-content:center;
        font-family:'Plus Jakarta Sans',sans-serif;
        font-size:2rem;font-weight:900;color:#fff;
        margin:0 auto 14px;
        box-shadow:0 6px 20px rgba(16,185,129,.35);
    }
    .prof-name {
        font-family:'Plus Jakarta Sans',sans-serif;
        font-size:1.05rem;font-weight:800;color:#0f172a;
        text-align:center;margin-bottom:6px;
    }
    .prof-role-badge {
        display:inline-flex;align-items:center;gap:5px;
        background:#f0fdf4;color:#16a34a;
        font-size:.72rem;font-weight:800;
        padding:4px 12px;border-radius:999px;
        margin:0 auto 20px;
    }
    .prof-info-row {
        display:flex;align-items:center;gap:10px;
        font-size:.82rem;color:#64748b;padding:8px 0;
        border-bottom:1px solid #f8fafc;
    }
    .prof-info-row:last-child { border-bottom:none; }
    .prof-info-row i { color:#10b981;width:16px;flex-shrink:0; }

    .prof-section-title {
        font-family:'Plus Jakarta Sans',sans-serif;
        font-size:1rem;font-weight:800;color:#0f172a;
        padding-bottom:12px;margin-bottom:20px;
        border-bottom:1px solid #f1f5f9;
        display:flex;align-items:center;gap:8px;
    }
    .prof-section-title i { color:#10b981; }

    .prof-alert {
        display:flex;align-items:center;gap:10px;
        padding:12px 16px;border-radius:10px;
        font-size:.84rem;font-weight:600;
        margin-bottom:20px;
    }
    .prof-alert-success { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
    .prof-alert-error   { background:#fef2f2;color:#dc2626;border:1px solid #fecaca; }

    /* ── Input group fix ── */
    .prof-input-group {
        display: flex;
        align-items: center;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        background: #fafbfc;
        transition: border-color .2s, box-shadow .2s;
        overflow: hidden;
    }
    .prof-input-group:focus-within {
        border-color: #10b981;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(16,185,129,.1);
    }
    .prof-input-group .input-icon {
        flex-shrink: 0;
        width: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        font-size: .80rem;
        pointer-events: none;
        border-right: 1.5px solid #e2e8f0;
        align-self: stretch;
        background: #f1f5f9;
    }
    .prof-input {
        flex: 1;
        width: 100%;
        padding: 10px 12px;
        border: none;
        border-radius: 0;
        font-size: .84rem;
        color: #0f172a;
        background: transparent;
        outline: none;
        font-family: 'DM Sans', sans-serif;
        box-sizing: border-box;
    }
    .prof-input::placeholder {
        color: #94a3b8;
    }
    .prof-input:disabled {
        background: #f8fafc;
        color: #94a3b8;
        cursor: not-allowed;
    }
    /* ─────────────────────────────────────────────────────────── */

    .prof-label { font-size:.78rem;font-weight:700;color:#334155;margin-bottom:6px;display:block; }

    .prof-form-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
    @media(max-width:600px){ .prof-form-grid { grid-template-columns:1fr; } }
    .prof-form-grid-3 { display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px; }
    @media(max-width:700px){ .prof-form-grid-3 { grid-template-columns:1fr; } }

    .prof-btn {
        display:inline-flex;align-items:center;gap:7px;
        padding:10px 22px;border-radius:10px;
        font-size:.84rem;font-weight:700;border:none;cursor:pointer;
        font-family:'DM Sans',sans-serif;transition:all .2s;
    }
    .prof-btn-green {
        background:linear-gradient(135deg,#10b981,#059669);color:#fff;
        box-shadow:0 4px 12px rgba(16,185,129,.3);
    }
    .prof-btn-green:hover { box-shadow:0 6px 18px rgba(16,185,129,.4);transform:translateY(-1px); }
    .prof-btn-slate {
        background:#f1f5f9;color:#334155;
    }
    .prof-btn-slate:hover { background:#e2e8f0; }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- Page Header -->
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:26px;">
                <div>
                    <h1 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:10px;">
                        <span style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;box-shadow:0 4px 12px rgba(16,185,129,.35);">
                            <i class="fas fa-user-circle"></i>
                        </span>
                        My Profile
                    </h1>
                    <p style="color:#64748b;font-size:.83rem;margin-top:4px;margin-left:48px;">Manage your Finance Manager account</p>
                </div>
                <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:.82rem;font-weight:700;">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>

            <!-- Alert -->
            <?php if($msg_text): ?>
            <div class="prof-alert prof-alert-<?= $msg_type ?>">
                <i class="fas <?= $msg_type==='success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($msg_text) ?>
            </div>
            <?php endif; ?>

            <div class="prof-grid">

                <!-- Left: Profile Card -->
                <div class="prof-card" style="text-align:center;">
                    <div class="prof-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
                    <div class="prof-name"><?= htmlspecialchars($user['name']) ?></div>
                    <div style="display:flex;justify-content:center;">
                        <span class="prof-role-badge"><i class="fas fa-rupee-sign"></i> Finance Manager</span>
                    </div>
                    <div style="text-align:left;margin-top:8px;">
                        <div class="prof-info-row">
                            <i class="fas fa-envelope"></i>
                            <?= htmlspecialchars($user['email']) ?>
                        </div>
                        <?php if(!empty($user['mobile'])): ?>
                        <div class="prof-info-row">
                            <i class="fas fa-phone"></i>
                            <?= htmlspecialchars($user['mobile']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="prof-info-row">
                            <i class="fas fa-shield-alt"></i>
                            Account Status:
                            <span style="font-weight:700;color:<?= $user['status']==='active'?'#16a34a':'#dc2626' ?>;">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </div>
                        <div class="prof-info-row">
                            <i class="fas fa-calendar-alt"></i>
                            Joined <?= date('d M Y', strtotime($user['created_at'] ?? 'now')) ?>
                        </div>
                    </div>
                </div>

                <!-- Right: Forms -->
                <div style="display:flex;flex-direction:column;gap:22px;">

                    <!-- Edit Profile -->
                    <div class="prof-card">
                        <div class="prof-section-title"><i class="fas fa-pen"></i> Edit Profile</div>
                        <form method="POST">
                            <div class="prof-form-grid">
                                <div>
                                    <label class="prof-label">Full Name</label>
                                    <div class="prof-input-group">
                                        <i class="fas fa-user input-icon"></i>
                                        <input class="prof-input" type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="prof-label">Email Address</label>
                                    <div class="prof-input-group">
                                        <i class="fas fa-envelope input-icon"></i>
                                        <input class="prof-input" type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                    </div>
                                </div>
                                <div style="grid-column:1/-1;">
                                    <label class="prof-label">Mobile Number</label>
                                    <div class="prof-input-group">
                                        <i class="fas fa-phone input-icon"></i>
                                        <input class="prof-input" type="text" name="mobile" value="<?= htmlspecialchars($user['mobile'] ?? '') ?>" placeholder="Enter mobile number">
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top:18px;">
                                <button type="submit" name="update_profile" class="prof-btn prof-btn-green">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="prof-card">
                        <div class="prof-section-title"><i class="fas fa-lock"></i> Change Password</div>
                        <form method="POST">
                            <div class="prof-form-grid-3">
                                <div>
                                    <label class="prof-label">Current Password</label>
                                    <div class="prof-input-group">
                                        <i class="fas fa-lock input-icon"></i>
                                        <input class="prof-input" type="password" name="current_password" required placeholder="Current password">
                                    </div>
                                </div>
                                <div>
                                    <label class="prof-label">New Password</label>
                                    <div class="prof-input-group">
                                        <i class="fas fa-key input-icon"></i>
                                        <input class="prof-input" type="password" name="new_password" required placeholder="New password">
                                    </div>
                                </div>
                                <div>
                                    <label class="prof-label">Confirm Password</label>
                                    <div class="prof-input-group">
                                        <i class="fas fa-key input-icon"></i>
                                        <input class="prof-input" type="password" name="confirm_password" required placeholder="Confirm password">
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top:18px;">
                                <button type="submit" name="change_password" class="prof-btn prof-btn-slate">
                                    <i class="fas fa-key"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>