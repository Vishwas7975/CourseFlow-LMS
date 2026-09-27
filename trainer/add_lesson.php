<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="trainer"){ header("Location: ../login.php"); exit(); }
$trainer_id=$_SESSION['user_id'];
$stmt=$conn->prepare("SELECT DISTINCT c.* FROM courses c JOIN enrollments e ON e.course_id=c.id WHERE e.trainer_id=?"); $stmt->bind_param("i",$trainer_id); $stmt->execute();
$courses=$stmt->get_result();
if(isset($_POST['add'])){
    $course_id=$_POST['course_id']; $title=trim($_POST['title']); $type=$_POST['type']; $url=trim($_POST['url']);
    $description=trim($_POST['description'] ?? '');
    $s=$conn->prepare("INSERT INTO lessons(course_id,title,content_type,content_url,description) VALUES(?,?,?,?,?)");
    $s->bind_param("issss",$course_id,$title,$type,$url,$description);
    if($s->execute()){ header("Location: lessons.php"); exit(); }
}
$page_title = "Add Lesson";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Lesson - CourseFlow LMS</title>
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
            <div class="page-header"><h1><i class="fas fa-plus-circle"></i> Add Lesson</h1></div>
            <div class="add-lesson-wrapper">
                <h2><i class="fas fa-play-circle" style="color:var(--primary)"></i> Lesson Details</h2>
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <div class="form-group"><label>Select Course</label>
                        <select name="course_id" required>
                            <?php $courses->data_seek(0); while($c=$courses->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Lesson Title</label>
                        <input type="text" name="title" placeholder="Lesson title" required>
                    </div>
                    <div class="form-group"><label>Content Type</label>
                        <select name="type">
                            <option value="video">Video</option>
                            <option value="pdf">PDF</option>
                            <option value="text">Text</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Content URL</label>
                        <input type="text" name="url" placeholder="YouTube URL or PDF link" required>
                    </div>
                    <div class="form-group"><label>Description</label>
                        <textarea name="description" rows="4" placeholder="Enter a description for this lesson (optional)..." style="width:100%;resize:vertical"></textarea>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="submit" name="add" class="btn btn-primary"><i class="fas fa-save"></i> Add Lesson</button>
                        <a href="lessons.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>