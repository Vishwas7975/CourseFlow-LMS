<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "student"){
    header("Location: ../login.php"); exit();
}
$page_title = "Requirements";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requirements - CourseFlow LMS</title>
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
                <h1><i class="fas fa-clipboard-list"></i> Course Requirements</h1>
                <p>Prerequisites and requirements for your enrolled courses.</p>
            </div>
            <?php
            $user_id = $_SESSION['user_id'];
            $enrolled = $conn->query("SELECT c.title, c.description FROM enrollments e JOIN courses c ON e.course_id=c.id WHERE e.user_id=$user_id AND IFNULL(e.status,'approved')='approved'");
            ?>
            <?php if($enrolled && $enrolled->num_rows > 0): ?>
            <div style="display:flex;flex-direction:column;gap:16px">
                <?php while($row=$enrolled->fetch_assoc()): ?>
                <div style="background:var(--card);border-radius:var(--radius-lg);padding:22px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light)">
                    <h3 style="font-size:1rem;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:8px"><i class="fas fa-book" style="color:var(--primary)"></i><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p style="font-size:0.875rem;color:var(--text-secondary)"><?php echo htmlspecialchars($row['description']); ?></p>
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light)">
                        <p style="font-size:0.82rem;color:var(--text-muted)"><i class="fas fa-info-circle" style="color:var(--primary)"></i> Contact your trainer for specific prerequisites and requirements.</p>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state"><i class="fas fa-clipboard-list" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px;display:block"></i>No enrolled courses found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
