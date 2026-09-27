<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){ header("Location: ../login.php"); exit(); }

// Get course_id from URL or POST
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : (isset($_POST['course_id']) ? intval($_POST['course_id']) : 0);
if(!$course_id){ header("Location: lessons.php"); exit(); }

// Get course info
$course_res = $conn->query("SELECT * FROM courses WHERE id=$course_id");
$course = $course_res ? $course_res->fetch_assoc() : null;
if(!$course){ header("Location: lessons.php"); exit(); }

$msg = "";
if(isset($_POST['add'])){
    $title = trim($_POST['title']);
    $type  = $_POST['type'];
    $url   = trim($_POST['url']);
    $description = trim($_POST['description'] ?? '');
    if(!empty($title) && !empty($url)){
        $stmt = $conn->prepare("INSERT INTO lessons(course_id,title,content_type,content_url,description) VALUES(?,?,?,?,?)");
        $stmt->bind_param("issss", $course_id, $title, $type, $url, $description);
        if($stmt->execute()){
            header("Location: course_lessons_admin.php?course_id=$course_id");
            exit();
        } else {
            $msg = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Failed to add lesson. Try again.</div>';
        }
    } else {
        $msg = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Please fill all required fields.</div>';
    }
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

            <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
                <div>
                    <h1 style="font-size:1.5rem;font-weight:800;display:flex;align-items:center;gap:10px">
                        <i class="fas fa-plus-circle" style="color:var(--primary)"></i> Add Lesson
                    </h1>
                    <p style="color:var(--text-secondary);font-size:0.875rem;margin-top:4px">
                        Adding lesson to: <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                    </p>
                </div>
                <a href="course_lessons_admin.php?course_id=<?php echo $course_id; ?>" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Lessons
                </a>
            </div>

            <?php echo $msg; ?>

            <div class="add-lesson-wrapper">
                <h2><i class="fas fa-play-circle" style="color:var(--primary)"></i> Lesson Details</h2>
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">

                    <div class="form-group">
                        <label>Lesson Title <span style="color:var(--danger)">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-heading input-icon"></i>
                            <input type="text" name="title" placeholder="e.g. Introduction to HTML" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Content Type</label>
                        <select name="type">
                            <option value="video">Video (YouTube URL)</option>
                            <option value="pdf">PDF (Link)</option>
                            <option value="text">Text Content</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Content URL / Link <span style="color:var(--danger)">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-link input-icon"></i>
                            <input type="text" name="url" placeholder="https://youtube.com/watch?v=..." required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4" placeholder="Enter a description for this lesson (optional)..." style="width:100%;resize:vertical"></textarea>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="submit" name="add" class="btn btn-primary"><i class="fas fa-save"></i> Add Lesson</button>
                        <a href="course_lessons_admin.php?course_id=<?php echo $course_id; ?>" class="btn btn-ghost"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>