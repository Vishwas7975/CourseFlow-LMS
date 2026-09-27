<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "student"){
    header("Location: ../login.php"); exit();
}
$page_title = "Assignments";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- Page Header with Back Button -->
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px">
                <div>
                    <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-primary);display:flex;align-items:center;gap:10px;margin-bottom:4px">
                        <i class="fas fa-file-alt" style="color:var(--primary)"></i> Assignments
                    </h1>
                    <p style="color:var(--text-secondary);font-size:0.875rem">View and submit your course assignments.</p>
                </div>
                <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:0.875rem;font-weight:600;color:var(--text-primary);text-decoration:none;transition:all var(--transition)" onmouseover="this.style.background='var(--bg-alt)'" onmouseout="this.style.background='var(--card)'">
                    <i class="fas fa-arrow-left" style="font-size:0.8rem"></i> Back to Dashboard
                </a>
            </div>

            <div class="empty-state">
                <i class="fas fa-file-alt" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px;display:block"></i>
                <strong>No assignments yet.</strong><br>
                <span style="color:var(--text-muted);font-size:0.875rem">Your trainer will post assignments here.</span>
            </div>

        </div>
    </div>
</div>
<style>
/* ── Mobile: page header row (title + Back button) stacks on phones ── */
@media(max-width:520px){
    /* Stack header title and back button vertically */
    div[style*="justify-content:space-between;flex-wrap:wrap"] {
        flex-direction:column;
        align-items:flex-start !important;
    }
    /* Back button: full width, touch-friendly */
    div[style*="justify-content:space-between;flex-wrap:wrap"] > a {
        width:100%;
        justify-content:center;
        min-height:44px;
        text-align:center;
    }
    /* Page title: scale down on phones */
    h1[style*="font-size:1.5rem"] { font-size:1.25rem !important; }
}
</style>
<?php include("../includes/footer.php"); ?>