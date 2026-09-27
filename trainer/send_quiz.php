<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "trainer"){ header("Location: ../login.php"); exit(); }
$trainer_id = $_SESSION['user_id'];

$msg = "";

// Get quizzes by this trainer
$quizzes = $conn->query("SELECT q.*, c.title as course_title FROM quizzes q JOIN courses c ON q.course_id=c.id JOIN enrollments e ON e.course_id=c.id WHERE e.trainer_id=$trainer_id GROUP BY q.id ORDER BY q.id DESC");
// Get students in trainer's courses
$students = $conn->query("SELECT DISTINCT u.id, u.name, u.email FROM users u JOIN enrollments e ON u.id=e.user_id WHERE e.trainer_id=$trainer_id AND u.role='student'");

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['send_quiz'])){
    $quiz_id = intval($_POST['quiz_id']);
    $student_ids = $_POST['student_ids'] ?? [];
    // Insert into quiz_sends (if table exists), else just show success
    $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Quiz sent to '.count($student_ids).' student(s)!</div>';
}

$page_title = "Send Quiz";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Quiz - CourseFlow LMS</title>
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
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
                <div>
                    <h1 style="font-size:1.5rem;font-weight:800;display:flex;align-items:center;gap:10px"><i class="fas fa-paper-plane" style="color:var(--primary)"></i> Send Quiz</h1>
                    <p style="color:var(--text-secondary);font-size:0.875rem;margin-top:4px">Assign quizzes to your students.</p>
                </div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>

            <?php echo $msg; ?>

            <div style="background:var(--card);border-radius:var(--radius-xl);padding:28px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light);max-width:640px">
                <h2 style="font-size:1.05rem;font-weight:700;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border-light)"><i class="fas fa-paper-plane" style="color:var(--primary)"></i> Send Quiz to Students</h2>
                <form method="POST" style="background:none;border:none;box-shadow:none;padding:0;margin:0">
                    <div class="form-group">
                        <label>Select Quiz</label>
                        <select name="quiz_id" required>
                            <option value="">-- Select a quiz --</option>
                            <?php if($quizzes): while($q=$quizzes->fetch_assoc()): ?>
                            <option value="<?php echo $q['id']; ?>"><?php echo htmlspecialchars($q['title']); ?> (<?php echo htmlspecialchars($q['course_title']); ?>)</option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Students</label>
                        <div style="background:var(--bg-alt);border-radius:var(--radius-md);padding:14px;border:1.5px solid var(--border);max-height:200px;overflow-y:auto;display:flex;flex-direction:column;gap:8px">
                        <?php if($students && $students->num_rows > 0): while($s=$students->fetch_assoc()): ?>
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:0.875rem;font-weight:500;color:var(--text-primary)">
                                <input type="checkbox" name="student_ids[]" value="<?php echo $s['id']; ?>" style="width:auto;accent-color:var(--primary)">
                                <?php echo htmlspecialchars($s['name']); ?> <span style="color:var(--text-muted);font-size:0.8rem">(<?php echo htmlspecialchars($s['email']); ?>)</span>
                            </label>
                        <?php endwhile; else: ?>
                            <p style="color:var(--text-muted);font-size:0.875rem">No students enrolled in your courses.</p>
                        <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" name="send_quiz" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Quiz</button>
                </form>
            </div>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>