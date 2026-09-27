<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="student"){ header("Location: ../login.php"); exit(); }
$user_id=$_SESSION['user_id'];
if(!isset($_GET['quiz_id'])){ die("Quiz not found"); }
$quiz_id=(int)$_GET['quiz_id'];
$checkAttempt=$conn->prepare("SELECT id FROM quiz_results WHERE user_id=? AND quiz_id=?");
$checkAttempt->bind_param("ii",$user_id,$quiz_id); $checkAttempt->execute();
if($checkAttempt->get_result()->num_rows > 0){ header("Location: dashboard.php"); exit(); }
$qstmt=$conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id=?");
$qstmt->bind_param("i",$quiz_id); $qstmt->execute();
$result=$qstmt->get_result();
$qs=$conn->prepare("SELECT title FROM quizzes WHERE id=?"); $qs->bind_param("i",$quiz_id); $qs->execute();
$quiz_info=$qs->get_result()->fetch_assoc();
if(isset($_POST['submit'])){
    if(!isset($_POST['answer'])){ header("Location: take_quiz.php?quiz_id=$quiz_id"); exit(); }
    $score=0; $total=count($_POST['answer']);
    foreach($_POST['answer'] as $qid=>$ans){
        $check=$conn->prepare("SELECT correct_answer FROM quiz_questions WHERE id=?");
        $check->bind_param("i",$qid); $check->execute();
        $correct=$check->get_result()->fetch_assoc()['correct_answer'];
        if($ans==$correct) $score++;
    }
    $save=$conn->prepare("INSERT INTO quiz_results(user_id,quiz_id,score,total) VALUES(?,?,?,?)");
    $save->bind_param("iiii",$user_id,$quiz_id,$score,$total);
    $save->execute();
    header("Location: dashboard.php"); exit();
}
$page_title = "Take Quiz";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz - CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
    /* ── Mobile: quiz form, question options, and action buttons ── */
    @media(max-width:600px){
        /* Quiz form card: reduce padding on phones */
        .quiz-form { padding:16px !important; }

        /* Question text: slightly smaller for readability */
        .question-block p { font-size:.9rem; }

        /* Radio option labels: larger touch area, full width, styled */
        .question-block label {
            display:block;
            padding:10px 12px;
            border-radius:8px;
            border:1.5px solid var(--border-light);
            margin-bottom:8px;
            cursor:pointer;
            font-size:.88rem;
            transition:background .15s;
        }
        .question-block label:hover { background:var(--primary-light); }

        /* Submit + Cancel buttons: stack vertically, full width */
        div[style*="display:flex;gap:10px"] { flex-direction:column; }
        .btn { width:100%; justify-content:center; min-height:44px; }
    }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-question-circle"></i> <?php echo htmlspecialchars($quiz_info['title'] ?? 'Quiz'); ?></h1>
                <p>Answer all questions carefully. You can only attempt this quiz once.</p>
            </div>
            <div class="quiz-form">
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <?php $n=1; $result->data_seek(0); while($q=$result->fetch_assoc()): ?>
                    <div class="question-block" style="margin-bottom:16px">
                        <p><span style="color:var(--primary-dark);margin-right:6px"><?php echo $n++; ?>.</span><?php echo htmlspecialchars($q['question']); ?></p>
                        <?php foreach(['option1','option2','option3','option4'] as $opt): ?>
                        <label>
                            <input type="radio" name="answer[<?php echo $q['id']; ?>]" value="<?php echo htmlspecialchars($q[$opt]); ?>" required>
                            <?php echo htmlspecialchars($q[$opt]); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endwhile; ?>
                    <div style="margin-top:20px;display:flex;gap:10px">
                        <button type="submit" name="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Quiz
                        </button>
                        <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>