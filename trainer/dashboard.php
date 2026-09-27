<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("trainer");

$trainer_id = $_SESSION['user_id'];

// Count distinct courses assigned to this trainer via enrollments
$stmt = $conn->prepare("SELECT DISTINCT c.id, c.* FROM courses c JOIN enrollments e ON e.course_id=c.id WHERE e.trainer_id=?");
$stmt->bind_param("i", $trainer_id); $stmt->execute();
$result = $stmt->get_result();
$total_courses = $result->num_rows;

// Student progress for trainer's assigned students via enrollments
$progress_res = $conn->query("
    SELECT u.name as student_name, c.title as course_title, e.progress, IFNULL(e.status,'active') as status
    FROM enrollments e
    JOIN users u ON e.user_id=u.id
    JOIN courses c ON e.course_id=c.id
    WHERE e.trainer_id=$trainer_id
    ORDER BY e.progress DESC
    LIMIT 10
");

// Quiz count
$quiz_count_res = $conn->query("SELECT COUNT(*) as c FROM quizzes WHERE course_id IN (SELECT DISTINCT course_id FROM enrollments WHERE trainer_id=$trainer_id)");
$quiz_count = $quiz_count_res ? $quiz_count_res->fetch_assoc()['c'] : 0;

// Live classes — upcoming count (safe: table may not exist yet)
$lc_count = 0;
$lc_upcoming_preview = [];
$lc_table_check = $conn->query("SHOW TABLES LIKE 'live_classes'");
if ($lc_table_check && $lc_table_check->num_rows > 0) {
    $lc_now = date('Y-m-d H:i:s');
    $lc_cnt = $conn->prepare("SELECT COUNT(*) as c FROM live_classes WHERE trainer_id=? AND scheduled_at >= ?");
    $lc_cnt->bind_param("is", $trainer_id, $lc_now); $lc_cnt->execute();
    $lc_count = $lc_cnt->get_result()->fetch_assoc()['c'];

    $lc_prev = $conn->prepare("
        SELECT lc.id, lc.title, lc.meeting_id, lc.scheduled_at, lc.duration, c.title AS course_title
        FROM live_classes lc JOIN courses c ON lc.course_id=c.id
        WHERE lc.trainer_id=? AND DATE_ADD(lc.scheduled_at, INTERVAL lc.duration MINUTE) >= ?
        ORDER BY lc.scheduled_at ASC LIMIT 3
    ");
    $lc_prev->bind_param("is", $trainer_id, $lc_now); $lc_prev->execute();
    $lc_res = $lc_prev->get_result();
    while ($r = $lc_res->fetch_assoc()) $lc_upcoming_preview[] = $r;
}

$page_title = "Trainer Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainer Dashboard - CourseFlow LMS</title>
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

            <!-- Welcome Banner -->
            <div style="background:linear-gradient(135deg,#16A34A,#22C55E);border-radius:var(--radius-xl);padding:28px 32px;color:#fff;margin-bottom:28px;position:relative;overflow:hidden">
                <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:rgba(255,255,255,0.06);border-radius:50%"></div>
                <h2 style="color:#fff;font-size:1.4rem;margin-bottom:4px"><i class="fas fa-chalkboard-teacher"></i> Trainer Dashboard</h2>
                <p style="color:rgba(255,255,255,0.8);font-size:0.9rem">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! Manage your courses and students.</p>
            </div>

            <!-- Stats Row -->
            <div class="dashboard-cards" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:28px">
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <h3>Assigned Courses</h3>
                    <div class="stat-value"><?php echo $total_courses; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-question-circle"></i></div>
                    <h3>Quizzes Created</h3>
                    <div class="stat-value"><?php echo $quiz_count; ?></div>
                </div>
                <div class="stat-card" style="border-top-color:#7C3AED">
                    <div class="stat-icon" style="background:#F3E8FF;color:#7C3AED"><i class="fas fa-video"></i></div>
                    <h3>Upcoming Classes</h3>
                    <div class="stat-value"><?php echo $lc_count; ?></div>
                </div>
            </div>

            <!-- Quick Actions (removed Add Lesson, View Progress, My Profile) -->
            <div class="section-wrapper">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="quick-actions-grid">
                    <a href="lessons.php" class="action-card">
                        <i class="fas fa-play-circle"></i><div>Manage Lessons</div>
                    </a>
                    <a href="add_quiz.php" class="action-card">
                        <i class="fas fa-question-circle"></i><div>Create Quiz</div>
                    </a>
                    <a href="send_quiz.php" class="action-card">
                        <i class="fas fa-paper-plane"></i><div>Send Quiz</div>
                    </a>
                    <a href="live_classes.php" class="action-card" style="border-color:#DDD6FE">
                        <i class="fas fa-video" style="color:#7C3AED"></i><div>Live Classes</div>
                    </a>
                </div>
            </div>

            <!-- Upcoming Live Classes Preview -->
            <div class="section-wrapper">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px">
                    <h2 style="margin-bottom:0"><i class="fas fa-video" style="color:#7C3AED"></i> Upcoming Live Classes</h2>
                    <a href="live_classes.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-right"></i> View All</a>
                </div>
                <?php if (!empty($lc_upcoming_preview)): ?>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php foreach ($lc_upcoming_preview as $lc):
                        $lc_ts  = strtotime($lc['scheduled_at']);
                        $lc_end = $lc_ts + $lc['duration'] * 60;
                        $now_t  = time();
                        $lc_live = ($now_t >= $lc_ts && $now_t <= $lc_end);
                    ?>
                    <div style="background:var(--card);border-radius:var(--radius-lg);padding:15px 18px;border:1px solid var(--border-light);box-shadow:var(--shadow-xs);display:flex;align-items:center;gap:14px">
                        <div style="width:42px;height:42px;border-radius:var(--radius-md);background:<?php echo $lc_live ? '#FEE2E2' : '#F3E8FF'; ?>;color:<?php echo $lc_live ? '#DC2626' : '#7C3AED'; ?>;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0">
                            <i class="fas fa-video"></i>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:0.9rem;font-weight:700;color:var(--text-primary);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($lc['title']); ?></div>
                            <div style="font-size:0.78rem;color:var(--text-muted)"><i class="fas fa-book-open"></i> <?php echo htmlspecialchars($lc['course_title']); ?></div>
                        </div>
                        <div style="text-align:right;flex-shrink:0">
                            <div style="font-size:0.8rem;font-weight:700;color:var(--text-secondary)"><?php echo date('d M, h:i A', $lc_ts); ?></div>
                            <span style="font-size:0.68rem;font-weight:700;padding:2px 8px;border-radius:999px;background:<?php echo $lc_live ? '#FEE2E2' : '#F3E8FF'; ?>;color:<?php echo $lc_live ? '#DC2626' : '#7C3AED'; ?>">
                                <?php echo $lc_live ? '● Live Now' : $lc['duration'].' min'; ?>
                            </span>
                        </div>
                        <?php if ($lc_live): ?>
                        <a href="../live_class.php?meeting_id=<?php echo urlencode($lc['meeting_id']); ?>&class_id=<?php echo $lc['id']; ?>"
                           class="btn btn-sm" style="background:linear-gradient(135deg,#7C3AED,#9333EA);color:#fff;border-color:transparent;flex-shrink:0" target="_blank">
                            <i class="fas fa-video"></i> Join
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state" style="padding:20px 0">
                    <i class="fas fa-video" style="font-size:1.8rem;color:var(--text-muted);margin-bottom:8px;display:block"></i>
                    No upcoming classes.
                    <a href="schedule_live_class.php" style="color:var(--primary);font-weight:600"> Schedule one →</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Assigned Courses (removed Lessons & Quiz buttons from cards) -->
            <div class="section-wrapper">
                <h2><i class="fas fa-book"></i> Your Assigned Courses (<?php echo $total_courses; ?>)</h2>
                <?php if($total_courses > 0): ?>
                <div class="course-grid">
                    <?php $result->data_seek(0); while($row = $result->fetch_assoc()): ?>
                    <div class="course-card">
                        <div class="course-image"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="course-content">
                            <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 100)); ?>...</p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book" style="font-size:2rem;color:var(--text-muted);margin-bottom:10px;display:block"></i>
                    No courses assigned yet. Contact admin!
                </div>
                <?php endif; ?>
            </div>

            <!-- Student Progress Table (removed Full Report button) -->
            <div class="section-wrapper">
                <h2 style="margin-bottom:14px"><i class="fas fa-chart-bar"></i> Student Progress</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Progress</th><th>Student</th><th>Course</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php if($progress_res && $progress_res->num_rows > 0): while($row = $progress_res->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="flex:1;height:8px;background:var(--bg-alt);border-radius:var(--radius-full);overflow:hidden;min-width:100px">
                                        <div style="width:<?php echo $row['progress']; ?>%;height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-dark));border-radius:var(--radius-full)"></div>
                                    </div>
                                    <span style="font-size:0.78rem;font-weight:700;color:var(--text-primary);min-width:32px"><?php echo $row['progress']; ?>%</span>
                                </div>
                            </td>
                            <td><strong><?php echo htmlspecialchars($row['student_name']); ?></strong></td>
                            <td style="color:var(--text-secondary)"><?php echo htmlspecialchars($row['course_title']); ?></td>
                            <td>
                                <span class="badge <?php echo $row['status']=='approved'?'success':($row['status']=='pending'?'warning':'danger'); ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">No student data available.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>