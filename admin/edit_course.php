<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){ header("Location: ../login.php"); exit(); }
if(!isset($_GET['id'])){ header("Location: courses.php"); exit(); }
$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM courses WHERE id=?");
$stmt->bind_param("i",$id); $stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if(!$course){ header("Location: courses.php"); exit(); }
if(isset($_POST['update'])){
    $title=$_POST['title']; $description=$_POST['description'];
    $image=$_POST['image']; $price=$_POST['price'];
    $u = $conn->prepare("UPDATE courses SET title=?,description=?,image=?,price=? WHERE id=?");
    $u->bind_param("sssdi",$title,$description,$image,$price,$id);
    if($u->execute()){ header("Location: courses.php"); exit(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - CourseFlow LMS</title>
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
            <div class="page-header"><h1><i class="fas fa-pen"></i> Edit Course</h1></div>
            <div class="add-course-container">
                <h2><i class="fas fa-book" style="color:var(--primary)"></i> Edit Course Details</h2>
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <div class="form-group">
                        <label>Course Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Image URL</label>
                        <input type="text" name="image" value="<?php echo htmlspecialchars($course['image']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Price (₹)</label>
                        <input type="number" step="0.01" name="price" value="<?php echo $course['price']; ?>" min="0">
                    </div>
                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="submit" name="update" class="btn btn-primary"><i class="fas fa-save"></i> Update Course</button>
                        <a href="courses.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
