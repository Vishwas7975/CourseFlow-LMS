<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="trainer"){ header("Location: ../login.php"); exit(); }
$trainer_id=$_SESSION['user_id'];
$sql="SELECT lessons.*, courses.title AS course_title FROM lessons JOIN courses ON lessons.course_id=courses.id JOIN enrollments e ON e.course_id=courses.id WHERE e.trainer_id=? GROUP BY lessons.id";
$stmt=$conn->prepare($sql); $stmt->bind_param("i",$trainer_id); $stmt->execute();
$result=$stmt->get_result();
$page_title = "Manage Lessons";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons - CourseFlow LMS</title>
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
                    <h1><i class="fas fa-play-circle"></i> Manage Lessons</h1>
                    <p>All lessons assigned to your courses.</p>
                </div>
                <div style="display:flex;gap:10px">
                    <a href="add_lesson.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Lesson</a>
                    <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Dashboard</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>#</th><th>Course</th><th>Lesson Title</th><th>Type</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if($result->num_rows>0): ?>
                        <?php while($row=$result->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--text-muted)"><?php echo $row['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['course_title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><span class="badge primary"><?php echo $row['content_type']; ?></span></td>
                            <td>
                                <div style="display:flex;gap:6px">
                                    <a href="../includes/shared/view_lesson.php?lesson_id=<?php echo $row['id']; ?>" class="btn-edit"><i class="fas fa-eye"></i> View</a>
                                    <a href="edit_lesson.php?id=<?php echo $row['id']; ?>" class="btn-edit"><i class="fas fa-pen"></i> Edit</a>
                                    <a href="delete_lesson.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete?')" class="btn-delete"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted)">No lessons found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>