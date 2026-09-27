<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "student"){
    header("Location: ../login.php"); exit();
}
$page_title = "Tools";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools - CourseFlow LMS</title>
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
            <div class="page-header">
                <h1><i class="fas fa-tools"></i> Learning Tools</h1>
                <p>Helpful resources and tools for your studies.</p>
            </div>
            <div class="quick-actions-grid">
                <a href="#" class="action-card"><i class="fas fa-calculator"></i><div>Calculator</div></a>
                <a href="#" class="action-card"><i class="fas fa-clock"></i><div>Study Timer</div></a>
                <a href="#" class="action-card"><i class="fas fa-bookmark"></i><div>Bookmarks</div></a>
                <a href="#" class="action-card"><i class="fas fa-file-pdf"></i><div>PDF Viewer</div></a>
                <a href="#" class="action-card"><i class="fas fa-pencil-alt"></i><div>Whiteboard</div></a>
                <a href="#" class="action-card"><i class="fas fa-code"></i><div>Code Editor</div></a>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
