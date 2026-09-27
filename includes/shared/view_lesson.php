<?php
session_start();
include("../../config/db.php");
include("../auth_check.php");
if(!isset($_SESSION['user_id'])){ header("Location: ../login.php"); exit(); }
$role=$_SESSION['role'];
$user_id=$_SESSION['user_id'];
if(!isset($_GET['lesson_id'])){ die("Lesson not found"); }
$lesson_id=(int)$_GET['lesson_id'];
$stmt=$conn->prepare("SELECT * FROM lessons WHERE id=?"); $stmt->bind_param("i",$lesson_id); $stmt->execute();
$lesson=$stmt->get_result()->fetch_assoc();
if(!$lesson){ die("Lesson not found"); }

// Handle description update (admin/trainer only)
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['update_desc']) && in_array($role,['admin','trainer'])){
    $new_desc = trim($_POST['description']);
    $conn->query("UPDATE lessons SET description='".mysqli_real_escape_string($conn,$new_desc)."' WHERE id=$lesson_id");
    $lesson['description'] = $new_desc;
}

if($role=="student"){
    $check=$conn->prepare("SELECT * FROM lesson_progress WHERE user_id=? AND lesson_id=?");
    $check->bind_param("ii",$user_id,$lesson_id); $check->execute();
    if($check->get_result()->num_rows==0){
        $ins=$conn->prepare("INSERT INTO lesson_progress(user_id,lesson_id) VALUES(?,?)");
        $ins->bind_param("ii",$user_id,$lesson_id); $ins->execute();
    }
    $course_id=$lesson['course_id'];
    $tot=$conn->prepare("SELECT COUNT(*) AS total FROM lessons WHERE course_id=?");
    $tot->bind_param("i",$course_id); $tot->execute();
    $total_lessons=$tot->get_result()->fetch_assoc()['total'];
    $comp=$conn->prepare("SELECT COUNT(*) AS done FROM lesson_progress JOIN lessons ON lesson_progress.lesson_id=lessons.id WHERE lesson_progress.user_id=? AND lessons.course_id=?");
    $comp->bind_param("ii",$user_id,$course_id); $comp->execute();
    $done=$comp->get_result()->fetch_assoc()['done'];
    $progress=$total_lessons>0 ? round(($done/$total_lessons)*100) : 0;
    $upd=$conn->prepare("UPDATE enrollments SET progress=? WHERE user_id=? AND course_id=?");
    $upd->bind_param("iii",$progress,$user_id,$course_id); $upd->execute();
}
function getYoutubeID($url){
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/',$url,$m);
    return $m[1]??"";
}
$video_id=getYoutubeID($lesson['content_url']);
$page_title = $lesson['title'];
$can_edit = in_array($role, ['admin', 'trainer']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title']); ?> - CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="icon" type="image/png" href="../../assets/images/favicon.png">
</head>
<body>
<div class="dashboard">
    <?php include("../sidebar.php"); ?>
    <div class="main">
        <?php include("../topbar.php"); ?>
        <div class="content-wrapper">
            <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <h1><i class="fas fa-play-circle"></i> <?php echo htmlspecialchars($lesson['title']); ?></h1>
                    <p>
                        <span class="badge primary"><?php echo $lesson['content_type']; ?></span>
                        <?php if($role=='student'): ?>
                        <span style="margin-left:8px;font-size:0.8rem;color:var(--success)"><i class="fas fa-check-circle"></i> Lesson marked as viewed</span>
                        <?php endif; ?>
                    </p>
                </div>
                <button onclick="history.back()" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Go Back</button>
            </div>

            <!-- Video Player at top -->
            <div class="lesson-viewer">
                <?php if($lesson['content_type']=="video" && $video_id!=""): ?>
                    <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:var(--radius-md);margin-bottom:24px">
        <iframe 
style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;border-radius:var(--radius-md)"
src="https://www.youtube-nocookie.com/embed/<?php echo $video_id; ?>?controls=1&modestbranding=1&rel=0&fs=1&iv_load_policy=3&disablekb=1&playsinline=1"
allow="fullscreen"
allowfullscreen>
</iframe>
                    </div>

                <?php elseif($lesson['content_type']=="pdf"): ?>
                    <div style="border-radius:var(--radius-md);overflow:hidden;border:1px solid var(--border);margin-bottom:24px">
                        <iframe src="<?php echo htmlspecialchars($lesson['content_url']); ?>"
                            style="width:100%;height:600px;border:none"></iframe>
                    </div>

                <?php else: ?>
                    <div style="background:var(--bg);border-radius:var(--radius-md);padding:24px;border:1px solid var(--border);margin-bottom:24px">
                        <p style="font-size:1rem;line-height:1.8;color:var(--text-primary)">
                            <?php echo nl2br(htmlspecialchars($lesson['content_url'])); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Description Section -->
                <div style="background:var(--bg-alt);border-radius:var(--radius-lg);padding:24px;border:1px solid var(--border)">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                        <h3 style="font-size:1rem;font-weight:700;display:flex;align-items:center;gap:8px">
                            <i class="fas fa-info-circle" style="color:var(--primary)"></i> Lesson Description
                        </h3>
                        <?php if($can_edit): ?>
                        <button onclick="toggleEdit()" class="btn btn-ghost btn-sm" id="editToggleBtn"><i class="fas fa-pen"></i> Edit</button>
                        <?php else: ?>
                        <span class="badge muted"><i class="fas fa-lock"></i> Read Only</span>
                        <?php endif; ?>
                    </div>

                    <!-- Read-only view -->
                    <div id="descView" style="color:var(--text-secondary);line-height:1.8;font-size:0.9rem">
                        <?php echo !empty($lesson['description']) ? nl2br(htmlspecialchars($lesson['description'])) : '<span style="color:var(--text-muted);font-style:italic">No description added yet.</span>'; ?>
                    </div>

                    <!-- Edit form (admin/trainer only) -->
                    <?php if($can_edit): ?>
                    <form method="POST" id="descEditForm" style="display:none;background:none;border:none;box-shadow:none;padding:0;margin:0">
                        <textarea name="description" rows="5" style="width:100%;margin-bottom:12px"><?php echo htmlspecialchars($lesson['description'] ?? ''); ?></textarea>
                        <div style="display:flex;gap:8px">
                            <button type="submit" name="update_desc" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Description</button>
                            <button type="button" onclick="toggleEdit()" class="btn btn-ghost btn-sm">Cancel</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
function toggleEdit(){
    const view = document.getElementById('descView');
    const form = document.getElementById('descEditForm');
    const btn = document.getElementById('editToggleBtn');
    if(form.style.display === 'none'){
        form.style.display = 'block';
        view.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-times"></i> Cancel';
    } else {
        form.style.display = 'none';
        view.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-pen"></i> Edit';
    }
}
</script>
<?php include("../footer.php"); ?>
