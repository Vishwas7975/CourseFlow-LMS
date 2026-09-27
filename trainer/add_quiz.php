<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="trainer"){ header("Location: ../login.php"); exit(); }
$trainer_id=$_SESSION['user_id'];
$count = isset($_POST['count']) ? (int)$_POST['count'] : 0;
$msg = "";
if(isset($_POST['create'])){
    $course_id=$_POST['course_id']; $title=trim($_POST['title']);
    $quiz=$conn->prepare("INSERT INTO quizzes(course_id,title) VALUES(?,?)");
    $quiz->bind_param("is",$course_id,$title); $quiz->execute();
    $quiz_id=$conn->insert_id;
    foreach($_POST['question'] as $i=>$q){
        if(trim($q)=="") continue;
        $s=$conn->prepare("INSERT INTO quiz_questions(quiz_id,question,option1,option2,option3,option4,correct_answer) VALUES(?,?,?,?,?,?,?)");
        $s->bind_param("issssss",$quiz_id,$q,$_POST['option1'][$i],$_POST['option2'][$i],$_POST['option3'][$i],$_POST['option4'][$i],$_POST['correct'][$i]);
        $s->execute();
    }
    $msg = "Quiz created successfully!";
    $count = 0;
}
$page_title = "Create Quiz";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
    /* ── trainer/add_quiz.php mobile fixes ──
       2-col grids (quiz title+course, option A/B/C/D) → 1-col on phones.
       Targets inline style="" grids via attribute selectors. */
    @media(max-width:600px){
        /* Quiz title + Course select: stack on phones */
        div[style*="grid-template-columns:1fr 1fr"],
        div[style*="grid-template-columns: 1fr 1fr"]{
            grid-template-columns: 1fr !important;
        }
        /* Question block: tighter padding */
        .question-block { padding: 14px !important; }

        /* Submit button: full width, touch-friendly */
        .btn { width: 100%; justify-content: center; min-height: 44px; }

        /* Number input for question count: full width */
        input[type="number"] { width: 100%; }
    }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">
            <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <h1><i class="fas fa-question-circle"></i> Create Quiz</h1>
                    <p>Build a quiz for your students.</p>
                </div>
                <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>

            <?php if($msg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <!-- STEP 1: Question Count -->
            <div style="background:var(--card);border-radius:var(--radius-xl);padding:28px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light);margin-bottom:20px">
                <h2 style="font-size:1rem;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border-light)">
                    <i class="fas fa-list" style="color:var(--primary)"></i> Step 1: How many questions?
                </h2>
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0;display:flex;gap:12px;align-items:flex-end">
                    <div class="form-group" style="margin:0;flex:0 0 200px">
                        <label>Number of Questions</label>
                        <input type="number" name="count" min="1" max="20" placeholder="e.g. 5" required>
                    </div>
                    <button type="submit" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> Generate Form</button>
                </form>
            </div>

            <?php if($count > 0): ?>
            <!-- STEP 2: Quiz Form -->
            <div style="background:var(--card);border-radius:var(--radius-xl);padding:28px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light)">
                <h2 style="font-size:1rem;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border-light)">
                    <i class="fas fa-pen" style="color:var(--primary)"></i> Step 2: Fill in Quiz Details
                </h2>
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <input type="hidden" name="count" value="<?php echo $count; ?>">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
                        <div class="form-group" style="margin:0"><label>Quiz Title <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="title" placeholder="e.g. Module 1 Assessment" required>
                        </div>
                        <div class="form-group" style="margin:0"><label>Assign to Course</label>
                            <select name="course_id">
                                <?php
                                $crs=$conn->prepare("SELECT DISTINCT c.* FROM courses c JOIN enrollments e ON e.course_id=c.id WHERE e.trainer_id=?");
                                $crs->bind_param("i",$trainer_id); $crs->execute();
                                $cs=$crs->get_result();
                                while($c=$cs->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <?php for($i=0;$i<$count;$i++): ?>
                    <div class="question-block" style="margin-bottom:16px;background:var(--bg);border-radius:var(--radius-md);padding:20px;border:1.5px solid var(--border)">
                        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;color:var(--primary-dark);margin-bottom:12px;font-size:0.9rem">
                            Question <?php echo $i+1; ?>
                        </div>
                        <div class="form-group"><label>Question Text</label>
                            <input type="text" name="question[]" placeholder="Enter your question here..." required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                            <div class="form-group" style="margin:0"><label>Option A</label><input type="text" name="option1[]" placeholder="Option A" required></div>
                            <div class="form-group" style="margin:0"><label>Option B</label><input type="text" name="option2[]" placeholder="Option B" required></div>
                            <div class="form-group" style="margin:0"><label>Option C</label><input type="text" name="option3[]" placeholder="Option C" required></div>
                            <div class="form-group" style="margin:0"><label>Option D</label><input type="text" name="option4[]" placeholder="Option D" required></div>
                        </div>
                        <div class="form-group" style="margin-top:10px;margin-bottom:0"><label style="color:var(--success)"><i class="fas fa-check-circle"></i> Correct Answer (exact text)</label>
                            <input type="text" name="correct[]" placeholder="Type the correct option exactly as above" required>
                        </div>
                    </div>
                    <?php endfor; ?>

                    <div style="display:flex;gap:10px;margin-top:12px">
                        <button type="submit" name="create" class="btn btn-primary"><i class="fas fa-save"></i> Create Quiz</button>
                        <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>