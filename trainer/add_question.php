<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="trainer"){ header("Location: ../login.php"); exit(); }
if(!isset($_GET['quiz_id'])){ die("Quiz not found"); }
$quiz_id=(int)$_GET['quiz_id'];
$msg="";
if(isset($_POST['add'])){
    $s=$conn->prepare("INSERT INTO quiz_questions(quiz_id,question,option1,option2,option3,option4,correct_answer) VALUES(?,?,?,?,?,?,?)");
    $s->bind_param("issssss",$quiz_id,$_POST['question'],$_POST['option1'],$_POST['option2'],$_POST['option3'],$_POST['option4'],$_POST['correct']);
    if($s->execute()) $msg="Question added!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Question - CourseFlow LMS</title>
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
            <div class="page-header"><h1><i class="fas fa-plus"></i> Add Question</h1></div>
            <?php if($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div><?php endif; ?>
            <div class="add-lesson-wrapper">
                <h2><i class="fas fa-question-circle" style="color:var(--warning)"></i> Question Details</h2>
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <div class="form-group"><label>Question</label><input type="text" name="question" required></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="form-group" style="margin:0"><label>Option A</label><input type="text" name="option1" required></div>
                        <div class="form-group" style="margin:0"><label>Option B</label><input type="text" name="option2" required></div>
                        <div class="form-group" style="margin:0"><label>Option C</label><input type="text" name="option3" required></div>
                        <div class="form-group" style="margin:0"><label>Option D</label><input type="text" name="option4" required></div>
                    </div>
                    <div class="form-group" style="margin-top:12px"><label style="color:var(--success)">Correct Answer</label><input type="text" name="correct" required></div>
                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="submit" name="add" class="btn btn-primary"><i class="fas fa-plus"></i> Add Question</button>
                        <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
