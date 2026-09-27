<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php"); exit();
}

if(!isset($_GET['course_id'])) { header("Location: lessons.php"); exit(); }
$course_id = intval($_GET['course_id']);

$course = $conn->query("SELECT * FROM courses WHERE id=$course_id")->fetch_assoc();
if(!$course) { header("Location: lessons.php"); exit(); }

$lessons = $conn->query("SELECT * FROM lessons WHERE course_id=$course_id ORDER BY id ASC");
$page_title = "Lessons: ".htmlspecialchars($course['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons - <?php echo htmlspecialchars($course['title']); ?> - CourseFlow LMS</title>
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

            <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
                <div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                        <a href="lessons.php" class="back-btn"><i class="fas fa-arrow-left"></i> All Courses</a>
                    </div>
                    <h1 style="font-size:1.4rem;font-weight:800;display:flex;align-items:center;gap:10px">
                        <i class="fas fa-play-circle" style="color:var(--primary)"></i>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </h1>
                    <p style="color:var(--text-secondary);font-size:0.875rem;margin-top:4px"><?php echo $lessons->num_rows; ?> lesson(s) in this course</p>
                </div>
                <!-- Add Lesson top-right -->
                <a href="add_lesson.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Lesson
                </a>
            </div>

            <?php if($lessons->num_rows > 0): ?>
            <div style="display:flex;flex-direction:column;gap:12px">
                <?php $i=1; while($row = $lessons->fetch_assoc()): ?>
                <div style="background:var(--card);border-radius:var(--radius-lg);padding:18px 22px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light);display:flex;align-items:center;gap:16px;transition:all var(--transition)" onmouseover="this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.boxShadow='var(--shadow-sm)'">
                    <div style="width:38px;height:38px;border-radius:var(--radius-md);background:var(--primary-light);color:var(--primary-dark);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;flex-shrink:0"><?php echo $i++; ?></div>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;color:var(--text-primary);font-size:0.95rem"><?php echo htmlspecialchars($row['title']); ?></div>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
                            <span class="badge primary"><?php echo htmlspecialchars($row['content_type']); ?></span>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-shrink:0">
                        <a href="../includes/shared/view_lesson.php?lesson_id=<?php echo $row['id']; ?>" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i> View</a>
                        <a href="edit_lesson.php?id=<?php echo $row['id']; ?>" class="btn-edit"><i class="fas fa-pen"></i> Edit</a>
                        <a href="delete_lesson.php?id=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Delete this lesson?')"><i class="fas fa-trash"></i> Delete</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-play-circle" style="font-size:2.2rem;color:var(--text-muted);margin-bottom:10px;display:block"></i>
                No lessons yet. <a href="add_lesson.php?course_id=<?php echo $course_id; ?>">Add the first lesson</a>!
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
