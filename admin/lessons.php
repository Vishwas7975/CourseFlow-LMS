<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php"); exit();
}

// Show courses as cards; clicking a course goes to course_lessons_admin.php
$courses = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM lessons WHERE course_id=c.id) as lesson_count FROM courses c ORDER BY c.title");
$page_title = "Manage Lessons";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lessons - CourseFlow LMS</title>
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
                    <h1 style="font-size:1.5rem;font-weight:800;display:flex;align-items:center;gap:10px"><i class="fas fa-play-circle" style="color:var(--primary)"></i> Manage Lessons</h1>
                    <p style="color:var(--text-secondary);font-size:0.875rem;margin-top:4px">Select a course to view and manage its lessons.</p>
                </div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>

            <?php if($courses && $courses->num_rows > 0): ?>
            <div class="course-grid">
                <?php while($row = $courses->fetch_assoc()): ?>
                <div class="course-card" style="cursor:pointer" onclick="window.location='course_lessons_admin.php?course_id=<?php echo $row['id']; ?>'">
                    <div class="course-image">
                        <?php if(!empty($row['image'])): ?>
                            <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="course">
                        <?php else: ?>
                            <i class="fas fa-book"></i>
                        <?php endif; ?>
                    </div>
                    <div class="course-content">
                        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($row['description'],0,80)); ?>...</p>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:auto">
                            <span class="badge primary"><i class="fas fa-list"></i> <?php echo $row['lesson_count']; ?> Lessons</span>
                            <?php if($row['price'] > 0): ?>
                            <span class="badge muted">₹<?php echo $row['price']; ?></span>
                            <?php else: ?>
                            <span class="badge success">Free</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="course-actions">
                        <a href="course_lessons_admin.php?course_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" onclick="event.stopPropagation()">
                            <i class="fas fa-folder-open"></i> Open Lessons
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book" style="font-size:2.2rem;color:var(--text-muted);margin-bottom:10px;display:block"></i>
                No courses found. <a href="add_course.php">Add a course</a> first.
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<style>
/* ── admin/lessons.php mobile fixes ──
   Uses .course-grid and .course-card from style.css.
   Only layout adjustments — no logic changed. */
@media(max-width:520px){
    /* Page header: stack title and Dashboard button */
    div[style*="justify-content:space-between;flex-wrap:wrap"] {
        flex-direction:column;
        align-items:flex-start !important;
    }
    .back-btn { display:flex; justify-content:center; min-height:44px; align-items:center; width:100%; }

    /* Open Lessons button: full width, touch-friendly */
    .course-actions .btn { width:100%; justify-content:center; min-height:44px; }
}
</style>
<?php include("../includes/footer.php"); ?>