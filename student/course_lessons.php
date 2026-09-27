<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="student"){ header("Location: ../login.php"); exit(); }
if(!isset($_GET['course_id'])){ header("Location: dashboard.php"); exit(); }
$course_id=(int)$_GET['course_id'];
$user_id=$_SESSION['user_id'];
$check=$conn->prepare("SELECT * FROM enrollments WHERE user_id=? AND course_id=? AND status='approved'");
$check->bind_param("ii",$user_id,$course_id); $check->execute();
$is_enrolled = ($check->get_result()->num_rows > 0);

$cs=$conn->prepare("SELECT title FROM courses WHERE id=?"); $cs->bind_param("i",$course_id); $cs->execute();
$course=$cs->get_result()->fetch_assoc();

if ($is_enrolled) {
    $stmt=$conn->prepare("SELECT * FROM lessons WHERE course_id=?"); $stmt->bind_param("i",$course_id); $stmt->execute();
    $result=$stmt->get_result();
    $page_title = "Course Lessons";
} else {
    $page_title = "Access Denied";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Lessons - CourseFlow LMS</title>
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
            <?php if (!$is_enrolled): ?>
            <div style="max-width: 600px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); overflow: hidden; border: 1px solid #e2e8f0; text-align: center;">
                <div style="background: linear-gradient(135deg, #ef4444, #b91c1c); padding: 40px 20px;">
                    <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; margin-bottom: 20px;">
                        <i class="fas fa-lock" style="font-size: 36px; color: #fff;"></i>
                    </div>
                    <h2 style="color: #fff; font-size: 1.8rem; font-weight: 800; margin: 0;">Access Denied</h2>
                </div>
                <div style="padding: 40px 30px;">
                    <p style="color: #64748b; font-size: 1.05rem; line-height: 1.6; margin-bottom: 30px;">
                        You are not enrolled in <strong style="color:#1e293b;"><?= htmlspecialchars($course['title']) ?></strong>. <br>Please enroll in this course to access its lessons and materials.
                    </p>
                    <a href="dashboard.php" style="display: inline-flex; align-items: center; gap: 8px; background: var(--primary); color: #fff; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; transition: background 0.2s; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <h1><i class="fas fa-play-circle"></i> <?php echo htmlspecialchars($course['title']); ?></h1>
                    <p>All lessons for this course are listed below.</p>
                </div>
                <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> My Courses</a>
            </div>

            <?php if($result->num_rows > 0): ?>
            <div style="display:flex;flex-direction:column;gap:10px">
                <?php $i=1; while($lesson=$result->fetch_assoc()): ?>
                <div style="background:var(--card);border-radius:var(--radius-md);border:1.5px solid var(--border-light);padding:16px 20px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-xs);transition:all var(--transition)" class="lesson-row">
                    <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);color:var(--primary-dark);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0"><?php echo $i++; ?></div>
                    <div style="flex:1">
                        <div style="font-weight:600;color:var(--text-primary);font-size:0.95rem"><?php echo htmlspecialchars($lesson['title']); ?></div>
                        <div style="font-size:0.76rem;color:var(--text-muted);margin-top:2px">
                            <span class="badge primary"><?php echo $lesson['content_type']; ?></span>
                        </div>
                    </div>
                    <a href="../includes/shared/view_lesson.php?lesson_id=<?php echo $lesson['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-play"></i> Open
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-video" style="font-size:2rem;color:var(--text-muted);margin-bottom:10px;display:block"></i>
                No lessons added yet for this course.
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
.lesson-row:hover{border-color:var(--primary);background:var(--primary-light)!important;transform:translateX(4px)}

/* ── Mobile: lesson row is flex row (number + title + Open button).
   On phones the Open button gets pushed off-screen — wrap it below. */
@media(max-width:520px){
    /* Allow lesson row to wrap onto 2 lines */
    .lesson-row { flex-wrap:wrap; padding:14px 14px; }

    /* Title block takes full width when wrapped */
    .lesson-row > div[style*="flex:1"] { width:100%; min-width:0; }

    /* Open button: full width, touch-friendly 44px height */
    .lesson-row .btn { width:100%; justify-content:center; min-height:44px; }

    /* Page header: stack title and back button vertically */
    .page-header { flex-direction:column !important; align-items:flex-start !important; }
    .page-header .btn { min-height:44px; }
}
</style>
<?php include("../includes/footer.php"); ?>