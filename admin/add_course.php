<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php"); exit();
}
if(isset($_POST['add_course'])){
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $image = trim($_POST['image']);
    $price = $_POST['price'];
    $admin_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO courses (title,description,image,price,created_by) VALUES(?,?,?,?,?)");
    $stmt->bind_param("sssdi",$title,$description,$image,$price,$admin_id);
    if($stmt->execute()){ header("Location: courses.php"); exit(); }
}
$page_title = "Add Course";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Course - CourseFlow LMS</title>
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
            <div class="page-header"><h1><i class="fas fa-plus-circle"></i> Add New Course</h1></div>
            <div class="add-course-container">
                <h2><i class="fas fa-book" style="color:var(--primary)"></i> Course Details</h2>
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <div class="form-group">
                        <label>Course Title <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="title" placeholder="e.g. Web Development Bootcamp" required>
                    </div>
                    <div class="form-group">
                        <label>Description <span style="color:var(--danger)">*</span></label>
                        <textarea name="description" placeholder="Describe what students will learn..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Image URL</label>
                        <input type="text" name="image" placeholder="https://example.com/image.jpg">
                    </div>
                    <div class="form-group">
                        <label>Price (₹)</label>
                        <input type="number" step="0.01" name="price" placeholder="0 for free" min="0">
                    </div>
                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="submit" name="add_course" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Course
                        </button>
                        <a href="courses.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
