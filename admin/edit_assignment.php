<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){ header("Location: ../login.php"); exit(); }
if(!isset($_GET['id'])){ header("Location: assign_course.php"); exit(); }
$id=(int)$_GET['id'];
$stmt=$conn->prepare("SELECT * FROM enrollments WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute();
$assignment=$stmt->get_result()->fetch_assoc();
$students=$conn->query("SELECT id,name FROM users WHERE role='student'");
$courses=$conn->query("SELECT id,title FROM courses");
$trainers=$conn->query("SELECT id,name FROM users WHERE role='trainer'");
if(isset($_POST['update'])){
    $uid=$_POST['user_id']; $cid=$_POST['course_id']; $tid=$_POST['trainer_id'];
    $u=$conn->prepare("UPDATE enrollments SET user_id=?,course_id=?,trainer_id=? WHERE id=?");
    $u->bind_param("iiii",$uid,$cid,$tid,$id);
    if($u->execute()){ header("Location: assign_course.php"); exit(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assignment - CourseFlow LMS</title>
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
            <div class="page-header"><h1><i class="fas fa-edit"></i> Edit Assignment</h1></div>
            <div class="add-course-container">
                <h2><i class="fas fa-tasks" style="color:var(--primary)"></i> Update Assignment</h2>
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <div class="form-group"><label>Student</label>
                        <select name="user_id">
                            <?php while($s=$students->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>" <?php if($assignment['user_id']==$s['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Course</label>
                        <select name="course_id">
                            <?php while($c=$courses->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>" <?php if($assignment['course_id']==$c['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($c['title']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Trainer</label>
                        <select name="trainer_id">
                            <?php while($t=$trainers->fetch_assoc()): ?>
                            <option value="<?php echo $t['id']; ?>" <?php if($assignment['trainer_id']==$t['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="submit" name="update" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                        <a href="assign_course.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
