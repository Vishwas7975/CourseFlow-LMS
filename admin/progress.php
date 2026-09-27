<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php"); exit();
}
$sql = "SELECT users.name, courses.title, enrollments.progress
        FROM enrollments
        JOIN users ON enrollments.user_id = users.id
        JOIN courses ON enrollments.course_id = courses.id";
$result = $conn->query($sql);
$page_title = "Student Progress";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Progress - CourseFlow LMS</title>
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

            <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <h1><i class="fas fa-chart-line"></i> Student Progress</h1>
                    <p>Track completion rates and progress for all enrolled students.</p>
                </div>
                <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Student</th><th>Course</th><th>Progress</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()):
                            $p = (int)$row['progress'];
                            $status = $p >= 100 ? 'success' : ($p >= 50 ? 'warning' : 'danger');
                            $label  = $p >= 100 ? 'Completed' : ($p >= 50 ? 'In Progress' : 'Started');
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div style="width:34px;height:34px;border-radius:50%;background:var(--primary-light);color:var(--primary-dark);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0">
                                        <?php echo strtoupper(substr($row['name'],0,1)); ?>
                                    </div>
                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td style="min-width:180px">
                                <div class="progress-label" style="margin-bottom:4px">
                                    <span></span><span><?php echo $p; ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width:<?php echo $p; ?>%;background:<?php echo $p>=100?'var(--success)':($p>=50?'var(--warning)':'var(--primary)'); ?>"></div>
                                </div>
                            </td>
                            <td><span class="badge <?php echo $status; ?>"><?php echo $label; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--text-muted)">No progress data available.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
