<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="student"){ header("Location: ../login.php"); exit(); }
$user_id=$_SESSION['user_id'];
if(!isset($_GET['course_id'])){ die("Course not found"); }
$course_id=(int)$_GET['course_id'];
$stmt=$conn->prepare("SELECT * FROM quizzes WHERE course_id=?");
$stmt->bind_param("i",$course_id); $stmt->execute();
$quizzes=$stmt->get_result();
$cs=$conn->prepare("SELECT title FROM courses WHERE id=?"); $cs->bind_param("i",$course_id); $cs->execute();
$course=$cs->get_result()->fetch_assoc();
$page_title = "Course Quizzes";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes - CourseFlow LMS</title>
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
                    <h1><i class="fas fa-question-circle"></i> Quizzes</h1>
                    <p><?php echo htmlspecialchars($course['title'] ?? ''); ?></p>
                </div>
                <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> My Courses</a>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px">
            <?php
            $quizzes->data_seek(0);
            $has = false;
            while($quiz=$quizzes->fetch_assoc()):
                $has = true;
                $check=$conn->prepare("SELECT * FROM quiz_results WHERE user_id=? AND quiz_id=?");
                $check->bind_param("ii",$user_id,$quiz['id']); $check->execute();
                $done=$check->get_result();
            ?>
            <div style="background:var(--card);border-radius:var(--radius-lg);padding:20px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light);display:flex;align-items:center;gap:16px">
                <div style="width:48px;height:48px;border-radius:var(--radius-md);background:var(--warning-light);color:var(--warning);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div style="flex:1">
                    <div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;color:var(--text-primary);font-size:0.95rem"><?php echo htmlspecialchars($quiz['title']); ?></div>
                    <div style="font-size:0.78rem;color:var(--text-muted);margin-top:3px">
                        <?php if($done->num_rows>0): ?>
                            <span style="color:var(--success)"><i class="fas fa-check-circle"></i> Completed</span>
                        <?php else: ?>
                            <span style="color:var(--warning)"><i class="fas fa-clock"></i> Pending</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if($done->num_rows > 0): ?>
                    <span class="badge success"><i class="fas fa-check"></i> Done</span>
                <?php else: ?>
                    <a href="take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-play"></i> Start Quiz
                    </a>
                <?php endif; ?>
            </div>
            <?php endwhile;
            if(!$has): ?>
            <div class="empty-state">No quizzes assigned for this course yet.</div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<style>
/* ── Mobile: quiz card is flex row (icon + info + Start/Done button).
   On phones the button overflows — wrap it below the info block. */
@media(max-width:520px){
    /* Allow quiz card to wrap */
    div[style*="display:flex;align-items:center;gap:16px"] { flex-wrap:wrap; }

    /* Info block: full width when wrapped */
    div[style*="flex:1"] { width:100%; min-width:0; }

    /* Start Quiz button: full width, touch-friendly */
    .btn.btn-primary.btn-sm {
        width:100%;
        justify-content:center;
        min-height:44px;
    }

    /* Page header: stack title and back button */
    .page-header { flex-direction:column !important; align-items:flex-start !important; }
    .page-header .btn { min-height:44px; }
}
</style>
<?php include("../includes/footer.php"); ?>