<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){ header("Location: ../login.php"); exit(); }
if(!isset($_GET['id'])){ header("Location: lessons.php"); exit(); }
$id=(int)$_GET['id'];
$stmt=$conn->prepare("SELECT * FROM lessons WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute();
$lesson=$stmt->get_result()->fetch_assoc();
if(!$lesson){ header("Location: lessons.php"); exit(); }
if(isset($_POST['update'])){
    $title=$_POST['title']; $type=$_POST['type']; $url=$_POST['url'];
    $description=trim($_POST['description'] ?? '');
    $u=$conn->prepare("UPDATE lessons SET title=?,content_type=?,content_url=?,description=? WHERE id=?");
    $u->bind_param("ssssi",$title,$type,$url,$description,$id);
    if($u->execute()){ header("Location: lessons.php"); exit(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lesson - CourseFlow LMS</title>
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
            <div class="page-header"><h1><i class="fas fa-pen"></i> Edit Lesson</h1></div>
            <div class="add-lesson-wrapper">
                <h2><i class="fas fa-play-circle" style="color:var(--primary)"></i> Update Lesson</h2>
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <div class="form-group">
                        <label>Lesson Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Content Type</label>
                        <select name="type">
                            <option value="video" <?php if($lesson['content_type']=="video") echo "selected"; ?>>Video</option>
                            <option value="pdf"   <?php if($lesson['content_type']=="pdf")   echo "selected"; ?>>PDF</option>
                            <option value="text"  <?php if($lesson['content_type']=="text")  echo "selected"; ?>>Text</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Content URL</label>
                        <input type="text" name="url" value="<?php echo htmlspecialchars($lesson['content_url']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4" style="width:100%;resize:vertical"><?php echo htmlspecialchars($lesson['description'] ?? ''); ?></textarea>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="submit" name="update" class="btn btn-primary"><i class="fas fa-save"></i> Update Lesson</button>
                        <a href="lessons.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>