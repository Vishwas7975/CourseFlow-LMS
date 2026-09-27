<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("trainer");

$trainer_id = $_SESSION['user_id'];
$error   = "";
$success = "";

// Ensure live_classes table exists (safe self-migration)
$conn->query("CREATE TABLE IF NOT EXISTS `live_classes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `course_id` INT NOT NULL,
    `trainer_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `meeting_id` VARCHAR(120) NOT NULL,
    `scheduled_at` DATETIME NOT NULL,
    `duration` INT NOT NULL DEFAULT 60,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_meeting_id` (`meeting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Trainer's courses
$stmt = $conn->prepare("SELECT id, title FROM courses WHERE trainer_id = ? ORDER BY title ASC");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$courses = $stmt->get_result();

if (isset($_POST['schedule'])) {
    $course_id   = (int)$_POST['course_id'];
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $scheduled   = trim($_POST['scheduled_at']);
    $duration    = (int)$_POST['duration'];

    if (!$course_id || !$title || !$scheduled || !$duration) {
        $error = "Please fill in all required fields.";
    } elseif ($duration < 15 || $duration > 480) {
        $error = "Duration must be between 15 and 480 minutes.";
    } elseif (strtotime($scheduled) === false) {
        $error = "Invalid date/time format.";
    } else {
        // Verify course belongs to this trainer
        $v = $conn->prepare("SELECT id FROM courses WHERE id = ? AND trainer_id = ?");
        $v->bind_param("ii", $course_id, $trainer_id);
        $v->execute();
        if ($v->get_result()->num_rows === 0) {
            $error = "Invalid course selection.";
        } else {
            // Generate unique meeting ID: vs-{courseId}-{trainerId}-{8 hex chars}
            do {
                $meeting_id = 'vs-' . $course_id . '-' . $trainer_id . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
                $chk = $conn->prepare("SELECT id FROM live_classes WHERE meeting_id = ?");
                $chk->bind_param("s", $meeting_id);
                $chk->execute();
            } while ($chk->get_result()->num_rows > 0);

            $ins = $conn->prepare("INSERT INTO live_classes (course_id, trainer_id, title, description, meeting_id, scheduled_at, duration) VALUES (?,?,?,?,?,?,?)");
            $ins->bind_param("iissssi", $course_id, $trainer_id, $title, $description, $meeting_id, $scheduled, $duration);

            if ($ins->execute()) {
                // Notify all enrolled students of this course
                $enrolled = $conn->prepare("SELECT user_id FROM enrollments WHERE course_id=? AND IFNULL(status,'approved')='approved'");
                $enrolled->bind_param("i", $course_id); $enrolled->execute();
                $enrolled_students = $enrolled->get_result();
                $notif_title = "Live Class Scheduled: " . $title;
                $notif_msg   = "A new live class has been scheduled on " . date('d M Y, h:i A', strtotime($scheduled)) . ".";
                $notif_link  = "../student/live_classes.php";
                $notif_stmt  = $conn->prepare("INSERT INTO notifications (type,for_role,for_user_id,title,message,link,is_read) VALUES ('live_class','student',?,?,?,?,0)");
                while($s = $enrolled_students->fetch_assoc()){
                    $notif_stmt->bind_param("isss", $s['user_id'], $notif_title, $notif_msg, $notif_link);
                    $notif_stmt->execute();
                }
                header("Location: live_classes.php?scheduled=1");
                exit();
            } else {
                $error = "Database error. Please try again.";
            }
        }
    }
}

$page_title = "Schedule Live Class";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Live Class — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        .schedule-card {
            background: var(--card);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            overflow: hidden;
            max-width: 700px;
        }
        .schedule-card-head {
            background: linear-gradient(135deg, #7C3AED, #9333EA);
            padding: 24px 28px;
            position: relative;
            overflow: hidden;
        }
        .schedule-card-head::after {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 160px; height: 160px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
        }
        .schedule-card-head h2 { color: #fff; font-size: 1.1rem; margin-bottom: 3px; }
        .schedule-card-head p  { color: rgba(255,255,255,0.78); font-size: 0.83rem; }
        .schedule-card-body { padding: 28px 28px 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 18px; }
        .form-grid .col-full { grid-column: 1 / -1; }
        .tip-box {
            background: #F5F3FF;
            border: 1px solid #DDD6FE;
            border-radius: var(--radius-md);
            padding: 13px 16px;
            font-size: 0.82rem;
            color: #5B21B6;
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin-top: 20px;
        }
        .tip-box i { margin-top: 2px; flex-shrink: 0; }
        .btn-purple {
            background: linear-gradient(135deg, #7C3AED, #9333EA);
            color: #fff !important;
            border-color: transparent;
            box-shadow: 0 2px 10px rgba(124,58,237,0.35);
        }
        .btn-purple:hover {
            background: linear-gradient(135deg, #6D28D9, #7C3AED);
            box-shadow: 0 4px 16px rgba(124,58,237,0.45);
            transform: translateY(-1px);
        }

        /* ── Mobile: form grid collapses to 1 col on phones ── */
        @media(max-width:640px){
            /* 2-col form grid → single column */
            .form-grid { grid-template-columns: 1fr; }

            /* Form card: reduce padding */
            .form-card { padding: 20px 16px; }

            /* Submit button: full width, touch-friendly */
            .btn-submit { width: 100%; justify-content: center; min-height: 44px; }

            /* Back button: full width */
            .btn-back { min-height: 44px; justify-content: center; }

            /* Page header: stack title and back */
            .page-header { flex-direction: column !important; align-items: flex-start !important; }
        }
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
                    <h1><i class="fas fa-calendar-plus" style="color:#7C3AED"></i> Schedule Live Class</h1>
                    <p>Create a new Jitsi video session for your enrolled students.</p>
                </div>
                <a href="live_classes.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="schedule-card">
                <div class="schedule-card-head">
                    <h2><i class="fas fa-video"></i> &nbsp;New Live Session</h2>
                    <p>A unique Jitsi room will be auto-generated when you save.</p>
                </div>
                <div class="schedule-card-body">
                    <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                        <div class="form-grid">

                            <div class="form-group col-full">
                                <label>Class Title <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="title" placeholder="e.g. Introduction to Python — Live Q&A"
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Select Course <span style="color:var(--danger)">*</span></label>
                                <select name="course_id" required>
                                    <option value="">— Choose a course —</option>
                                    <?php $courses->data_seek(0); while ($c = $courses->fetch_assoc()): ?>
                                    <option value="<?php echo $c['id']; ?>"
                                        <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['title']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                    <?php if ($courses->num_rows === 0): ?>
                                    <option disabled>No courses assigned yet</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Duration <span style="color:var(--danger)">*</span></label>
                                <select name="duration" required>
                                    <?php foreach ([30=>'30 min',45=>'45 min',60=>'1 hour',90=>'1.5 hours',120=>'2 hours',180=>'3 hours'] as $val=>$label): ?>
                                    <option value="<?php echo $val; ?>"
                                        <?php echo (isset($_POST['duration']) ? $_POST['duration'] : 60) == $val ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group col-full">
                                <label>Date &amp; Time <span style="color:var(--danger)">*</span></label>
                                <input type="datetime-local" name="scheduled_at"
                                       min="<?php echo date('Y-m-d\TH:i'); ?>"
                                       value="<?php echo htmlspecialchars($_POST['scheduled_at'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group col-full">
                                <label>Description <span style="color:var(--text-muted);font-weight:400">(optional)</span></label>
                                <textarea name="description" rows="3"
                                    placeholder="Briefly describe what this session will cover…"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>

                        </div>

                        <div class="tip-box">
                            <i class="fas fa-lightbulb"></i>
                            <div>
                                <strong>Auto-generated Room</strong><br>
                                A unique Jitsi room ID is created when you save. Only you and enrolled students can join via the LMS. Students see the Join button only when class time arrives.
                            </div>
                        </div>

                        <div style="display:flex;gap:10px;margin-top:22px">
                            <button type="submit" name="schedule" class="btn btn-purple">
                                <i class="fas fa-calendar-check"></i> Schedule Class
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