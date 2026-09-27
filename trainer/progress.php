<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "trainer"){ header("Location: ../login.php"); exit(); }
$trainer_id = $_SESSION['user_id'];

$progress_res = $conn->query("
    SELECT u.name as student_name, u.email, c.title as course_title, e.progress, IFNULL(e.status,'active') as status, e.id as enroll_id
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.trainer_id = $trainer_id
    ORDER BY c.title ASC, e.progress DESC
");

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

            <div class="page-header">
                <h1><i class="fas fa-chart-line"></i> Student Progress</h1>
                <p>Full progress report for all students in your courses.</p>
            </div>

            <div class="section-wrapper">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Email</th>
                                <th>Course</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if($progress_res && $progress_res->num_rows > 0):
                            $i = 1;
                            while($row = $progress_res->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--text-muted)"><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['student_name']); ?></strong></td>
                            <td style="color:var(--text-muted);font-size:0.85rem"><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['course_title']); ?></td>
                            <td style="min-width:160px">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="flex:1;height:8px;background:var(--bg-alt);border-radius:var(--radius-full);overflow:hidden">
                                        <div style="width:<?php echo $row['progress']; ?>%;height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-dark));border-radius:var(--radius-full)"></div>
                                    </div>
                                    <span style="font-size:0.8rem;font-weight:700;color:var(--text-primary);min-width:36px"><?php echo $row['progress']; ?>%</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $row['status']=='approved'?'success':($row['status']=='pending'?'warning':'danger'); ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">No student progress data found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>