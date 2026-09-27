<?php
session_start();
if(!isset($_SESSION['user_id'])){ header("Location: login.php"); exit(); }
$role = $_SESSION['role'] ?? '';
switch($role){
    case 'admin':   header("Location: admin/dashboard.php");   exit();
    case 'trainer': header("Location: trainer/dashboard.php"); exit();
    case 'student': header("Location: student/dashboard.php"); exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
</head>
<body>
<div class="dashboard">
    <?php include("includes/sidebar.php"); ?>
    <div class="main">
        <?php include("includes/topbar.php"); ?>
        <div class="content-wrapper">
            <div class="welcome-hero">
                <div class="welcome-content">
                    <div class="welcome-icon"><i class="fas fa-rocket"></i></div>
                    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
                    <p class="welcome-role"><i class="fas fa-user-tag"></i> Role: <strong><?php echo ucfirst($role); ?></strong></p>
                    <div class="welcome-actions">
                        <a href="<?php echo $role; ?>/dashboard.php" class="btn btn-primary btn-large">
                            <i class="fas fa-arrow-right"></i> Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("includes/footer.php"); ?>
