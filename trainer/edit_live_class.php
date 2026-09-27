<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("trainer");

$trainer_id = $_SESSION['user_id'];
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = "";

// Fetch — must belong to this trainer
$stmt = $conn->prepare("SELECT * FROM live_classes WHERE id = ? AND trainer_id = ?");
$stmt->bind_param("ii", $id, $trainer_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) { header("Location: live_classes.php"); exit(); }

// Trainer courses
$cs = $conn->prepare("SELECT id, title FROM courses WHERE trainer_id = ? ORDER BY title ASC");
$cs->bind_param("i", $trainer_id);
$cs->execute();
$courses = $cs->get_result();

if (isset($_POST['update'])) {
    $course_id   = (int)$_POST['course_id'];
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $scheduled   = trim($_POST['scheduled_at']);
    $duration    = (int)$_POST['duration'];

    if (!$course_id || !$title || !$scheduled || !$duration) {
        $error = "Please fill in all required fields.";
    } elseif ($duration < 15 || $duration > 480) {
        $error = "Duration must be between 15 and 480 minutes.";
    } else {
        $v = $conn->prepare("SELECT id FROM courses WHERE id = ? AND trainer_id = ?");
        $v->bind_param("ii", $course_id, $trainer_id);
        $v->execute();
        if ($v->get_result()->num_rows === 0) {
            $error = "Invalid course selection.";
        } else {
            $upd = $conn->prepare("UPDATE live_classes SET course_id=?, title=?, description=?, scheduled_at=?, duration=? WHERE id=? AND trainer_id=?");
            $upd->bind_param("isssiis", $course_id, $title, $description, $scheduled, $duration, $id, $trainer_id);
            if ($upd->execute()) {
                header("Location: live_classes.php?updated=1");
                exit();
            } else {
                $error = "Update failed. Please try again.";
            }
        }
    }
}

$page_title = "Edit Live Class";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Live Class — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        .edit-card { background:var(--card);border-radius:var(--radius-xl);box-shadow:var(--shadow-sm);border:1px solid var(--border-light);overflow:hidden;max-width:700px; }
        .edit-card-head { background:linear-gradient(135deg,#D97706,#F59E0B);padding:24px 28px; }
        .edit-card-head h2 { color:#fff;font-size:1.1rem;margin-bottom:3px; }
        .edit-card-head p  { color:rgba(255,255,255,0.80);font-size:0.83rem; }
        .edit-card-body { padding:28px; }
        .form-grid { display:grid;grid-template-columns:1fr 1fr;gap:0 18px; }
        .col-full  { grid-column:1 / -1; }
        .meeting-badge {
            display:inline-flex;align-items:center;gap:7px;
            background:#FFFBEB;border:1px solid #FDE68A;
            border-radius:var(--radius-md);padding:10px 14px;
            font-size:0.83rem;color:#92400E;margin-bottom:20px;
        }
        .meeting-badge code { font-family:monospace;font-weight:700;color:#B45309; }
        .btn-amber {
            background:linear-gradient(135deg,#D97706,#F59E0B);
            color:#fff!important;border-color:transparent;
            box-shadow:0 2px 8px rgba(217,119,6,.3);
        }
        .btn-amber:hover { background:linear-gradient(135deg,#B45309,#D97706);transform:translateY(-1px); }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <h1><i class="fas fa-pen" style="color:#D97706"></i> Edit Live Class</h1>
                    <p>Update the details for this scheduled session.</p>
                </div>
                <a href="live_classes.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="edit-card">
                <div class="edit-card-head">
                    <h2><i class="fas fa-video"></i> &nbsp;Edit Session Details</h2>
                    <p>The meeting room ID is fixed and cannot be changed after creation.</p>
                </div>
                <div class="edit-card-body">
                    <div class="meeting-badge">
                        <i class="fas fa-link"></i>
                        Room ID: <code><?php echo htmlspecialchars($row['meeting_id']); ?></code>
                        <span style="margin-left:auto;font-size:0.72rem;color:var(--text-muted)">(read-only)</span>
                    </div>

                    <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                        <div class="form-grid">

                            <div class="form-group col-full">
                                <label>Class Title <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="title" required
                                       value="<?php echo htmlspecialchars($row['title']); ?>">
                            </div>

                            <div class="form-group">
                                <label>Course <span style="color:var(--danger)">*</span></label>
                                <select name="course_id" required>
                                    <option value="">— Choose course —</option>
                                    <?php $courses->data_seek(0); while ($c = $courses->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                        <?php echo ($row['course_id'] == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['title']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Duration <span style="color:var(--danger)">*</span></label>
                                <select name="duration" required>
                                    <?php foreach ([30=>'30 min',45=>'45 min',60=>'1 hour',90=>'1.5 hours',120=>'2 hours',180=>'3 hours'] as $val=>$label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo ($row['duration'] == $val) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group col-full">
                                <label>Date &amp; Time <span style="color:var(--danger)">*</span></label>
                                <input type="datetime-local" name="scheduled_at" required
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($row['scheduled_at'])); ?>">
                            </div>

                            <div class="form-group col-full">
                                <label>Description</label>
                                <textarea name="description" rows="3"><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
                            </div>

                        </div>
                        <div style="display:flex;gap:10px;margin-top:8px">
                            <button type="submit" name="update" class="btn btn-amber">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="live_classes.php" class="btn btn-ghost">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
