<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "trainer"){ header("Location: ../login.php"); exit(); }
$trainer_id = $_SESSION['user_id'];

// Get courses assigned to this trainer
$courses_res = $conn->prepare("SELECT DISTINCT c.id, c.title FROM courses c JOIN enrollments e ON e.course_id = c.id WHERE e.trainer_id = ?");
$courses_res->bind_param("i", $trainer_id);
$courses_res->execute();
$courses = $courses_res->get_result();

$msg = "";

// Handle Upload
if(isset($_POST['upload_note'])){
    $course_id   = intval($_POST['course_id']);
    $title       = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $file_path   = "";
    $file_name   = "";

    if(!empty($title) && $course_id > 0){
        if(!empty($_FILES['note_file']['name'])){
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/notes/";
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $orig_name  = basename($_FILES['note_file']['name']);
            $safe_name  = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
            $target     = $upload_dir . $safe_name;
            if(move_uploaded_file($_FILES['note_file']['tmp_name'], $target)){
                $file_path = "/uploads/notes/" . $safe_name;
                $file_name = $orig_name;
            } else {
                $msg = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> File upload failed. Check server permissions.</div>';
            }
        }
        if(empty($msg)){
            $stmt = $conn->prepare("INSERT INTO notes(course_id, uploaded_by, title, description, file_path, file_name) VALUES(?,?,?,?,?,?)");
            $stmt->bind_param("iissss", $course_id, $trainer_id, $title, $description, $file_path, $file_name);
            if($stmt->execute()){
                // Notify all enrolled students of this course
                $enrolled = $conn->prepare("SELECT user_id FROM enrollments WHERE course_id=? AND IFNULL(status,'approved')='approved'");
                $enrolled->bind_param("i", $course_id); $enrolled->execute();
                $enrolled_students = $enrolled->get_result();
                $notif_title = "New Note: " . $title;
                $notif_msg   = "Your trainer uploaded a new study material for your course.";
                $notif_link  = "../student/notes.php";
                $notif_stmt  = $conn->prepare("INSERT INTO notifications (type,for_role,for_user_id,title,message,link,is_read) VALUES ('note','student',?,?,?,?,0)");
                while($s = $enrolled_students->fetch_assoc()){
                    $notif_stmt->bind_param("isss", $s['user_id'], $notif_title, $notif_msg, $notif_link);
                    $notif_stmt->execute();
                }
                $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Note uploaded successfully.</div>';
            } else {
                $msg = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Failed to save note. Try again.</div>';
            }
        }
    } else {
        $msg = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Please fill all required fields.</div>';
    }
}

// Handle Delete
if(isset($_GET['delete'])){
    $del_id = intval($_GET['delete']);
    $d = $conn->prepare("SELECT file_path, uploaded_by FROM notes WHERE id=?");
    $d->bind_param("i", $del_id); $d->execute();
    $dn = $d->get_result()->fetch_assoc();
    if($dn && $dn['uploaded_by'] == $trainer_id){
        if(!empty($dn['file_path'])){
            $real_path = $_SERVER['DOCUMENT_ROOT'] . $dn['file_path'];
            if(file_exists($real_path)) unlink($real_path);
        }
        $conn->prepare("DELETE FROM notes WHERE id=?")->bind_param("i", $del_id);
        $conn->prepare("DELETE FROM notes WHERE id=?")->execute();
        $del = $conn->prepare("DELETE FROM notes WHERE id=?");
        $del->bind_param("i", $del_id); $del->execute();
        header("Location: notes.php?deleted=1"); exit();
    }
}
if(isset($_GET['deleted'])) $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Note deleted.</div>';

// Fetch this trainer's notes
$notes_res = $conn->prepare("
    SELECT n.*, c.title as course_title
    FROM notes n
    JOIN courses c ON n.course_id = c.id
    WHERE n.uploaded_by = ?
    ORDER BY n.created_at DESC
");
$notes_res->bind_param("i", $trainer_id);
$notes_res->execute();
$notes = $notes_res->get_result();

$page_title = "Notes & Materials";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes & Materials - CourseFlow LMS</title>
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
                        <i class="fas fa-sticky-note" style="color:var(--primary)"></i> Notes & Study Materials
                    </h1>
                    <p style="color:var(--text-secondary);font-size:0.875rem;margin-top:4px">Upload notes and study materials for your students.</p>
                </div>
                <button onclick="document.getElementById('uploadPanel').scrollIntoView({behavior:'smooth'})" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Note
                </button>
            </div>

            <?php echo $msg; ?>

            <!-- Upload Form -->
            <div id="uploadPanel" class="add-lesson-wrapper" style="margin-bottom:28px">
                <h2><i class="fas fa-upload" style="color:var(--primary)"></i> Upload New Note / Material</h2>
                <form method="POST" enctype="multipart/form-data" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <div class="form-group">
                        <label>Select Course <span style="color:var(--danger)">*</span></label>
                        <select name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php $courses->data_seek(0); while($c = $courses->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Title <span style="color:var(--danger)">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-heading input-icon"></i>
                            <input type="text" name="title" placeholder="e.g. Week 1 - Introduction Notes" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description <span style="color:var(--text-muted);font-size:0.8rem">(optional)</span></label>
                        <textarea name="description" rows="3" placeholder="Brief description of this material..." style="width:100%;resize:vertical"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Upload File <span style="color:var(--text-muted);font-size:0.8rem">(PDF, DOC, PPT, etc.)</span></label>
                        <input type="file" name="note_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.rar,.jpg,.jpeg,.png" style="width:100%;padding:8px;border:1.5px solid var(--border);border-radius:var(--radius-md);background:var(--bg);color:var(--text-primary)">
                    </div>
                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="submit" name="upload_note" class="btn btn-primary"><i class="fas fa-save"></i> Upload Note</button>
                    </div>
                </form>
            </div>

            <!-- Notes List -->
            <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                <i class="fas fa-list" style="color:var(--primary)"></i> Uploaded Notes
            </h2>

            <?php if($notes && $notes->num_rows > 0): ?>
            <div style="display:flex;flex-direction:column;gap:12px">
                <?php while($row = $notes->fetch_assoc()):
                    $ext = strtolower(pathinfo($row['file_name'] ?? '', PATHINFO_EXTENSION));
                    $icon = 'fas fa-file'; $icon_color = 'var(--text-muted)';
                    if($ext=='pdf'){ $icon='fas fa-file-pdf'; $icon_color='#EF4444'; }
                    elseif(in_array($ext,['doc','docx'])){ $icon='fas fa-file-word'; $icon_color='#2D7DD2'; }
                    elseif(in_array($ext,['ppt','pptx'])){ $icon='fas fa-file-powerpoint'; $icon_color='#D97706'; }
                    elseif(in_array($ext,['xls','xlsx'])){ $icon='fas fa-file-excel'; $icon_color='#16A34A'; }
                    elseif(in_array($ext,['zip','rar'])){ $icon='fas fa-file-archive'; $icon_color='#F59E0B'; }
                    elseif(in_array($ext,['jpg','jpeg','png','gif'])){ $icon='fas fa-file-image'; $icon_color='#06B6D4'; }
                ?>
                <div style="background:var(--card);border-radius:var(--radius-lg);padding:18px 22px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light);display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:var(--bg-alt);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="<?php echo $icon; ?>" style="font-size:1.5rem;color:<?php echo $icon_color; ?>"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;color:var(--text-primary);font-size:0.95rem">
                            <?php echo htmlspecialchars($row['title']); ?>
                        </div>
                        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                            <span><i class="fas fa-book" style="color:var(--primary)"></i> <?php echo htmlspecialchars($row['course_title']); ?></span>
                            <?php if(!empty($row['file_name'])): ?>
                            <span><i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($row['file_name']); ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                        </div>
                        <?php if(!empty($row['description'])): ?>
                        <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px"><?php echo htmlspecialchars($row['description']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:8px;flex-shrink:0">
                        <?php if(!empty($row['file_path'])): ?>
                        <a href="<?php echo htmlspecialchars($row['file_path']); ?>" download class="btn btn-ghost btn-sm"><i class="fas fa-download"></i></a>
                        <?php endif; ?>
                        <a href="notes.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm" style="background:#FEE2E2;color:#DC2626;border:none" onclick="return confirm('Delete this note?')"><i class="fas fa-trash"></i></a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-sticky-note" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px;display:block"></i>
                <strong>No notes uploaded yet.</strong><br>
                <span style="color:var(--text-muted);font-size:0.875rem">Use the form above to upload your first note.</span>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>