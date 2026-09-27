<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("student");

$user_id = $_SESSION['user_id'];

/* ── Gamification Stats ── */
$stats_points = 0;
$stats_streak = 0;
$res_stats = $conn->query("SELECT points, streak_days FROM student_stats WHERE user_id = " . (int)$user_id);
if ($res_stats && $row_stats = $res_stats->fetch_assoc()) {
    $stats_points = (int)$row_stats['points'];
    $stats_streak = (int)$row_stats['streak_days'];
}

$problems_solved = 0;
$res_prob = $conn->query("SELECT COUNT(*) as c FROM lesson_progress WHERE user_id = " . (int)$user_id . " AND completed = 1");
if ($res_prob && $row_prob = $res_prob->fetch_assoc()) {
    $problems_solved = (int)$row_prob['c'];
}

$total_projects = 0;
$res_proj = $conn->query("SELECT COUNT(*) as c FROM project_submissions WHERE student_id = " . (int)$user_id);
if ($res_proj && $row_proj = $res_proj->fetch_assoc()) {
    $total_projects = (int)$row_proj['c'];
}

// Compute Real Rank
$my_rank = 1;
$res_rank = $conn->query("SELECT COUNT(*) as higher_scores FROM student_stats WHERE points > " . (int)$stats_points);
if ($res_rank && $row_rank = $res_rank->fetch_assoc()) {
    $my_rank = (int)$row_rank['higher_scores'] + 1;
}

// Fetch 3 recent notifications for the Hero Banner
$hero_notifs = [];
$res_notif = $conn->query("SELECT title FROM notifications WHERE for_role='student' AND for_user_id=" . (int)$user_id . " ORDER BY created_at DESC LIMIT 3");
if ($res_notif) {
    while ($row = $res_notif->fetch_assoc()) {
        $hero_notifs[] = $row['title'];
    }
}
if (empty($hero_notifs)) {
    $hero_notifs = [
        "Welcome to the new CourseFlow Dashboard!",
        "Check out your new learning stats.",
        "New courses are available now."
    ];
}

// Calculate gauge degree (max 2000 points = 180 degrees)
$gauge_percent = min(100, max(0, ($stats_points / 2000) * 100));
$gauge_deg = -90 + ($gauge_percent / 100) * 180; // from -90 to +90
$score_tier = 'Beginner';
if ($stats_points >= 500) $score_tier = 'Novice';
if ($stats_points >= 1000) $score_tier = 'Proficient';
if ($stats_points >= 1500) $score_tier = 'Advanced';
if ($stats_points >= 2000) $score_tier = 'Expert';

/* ── Auto-create course_syllabus table if not exists ── */
$conn->query("
    CREATE TABLE IF NOT EXISTS `course_syllabus` (
      `id`          INT(11)       NOT NULL AUTO_INCREMENT,
      `course_id`   INT(11)       NOT NULL,
      `pdf_path`    VARCHAR(500)  NOT NULL,
      `file_name`   VARCHAR(255)  NOT NULL,
      `uploaded_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_course` (`course_id`),
      CONSTRAINT `fk_syl_course` FOREIGN KEY (`course_id`)
          REFERENCES `courses` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

/* ── Check internship pending payment ── */
$internship_pending = null;
$ip_table = $conn->query("SHOW TABLES LIKE 'internship_payments'");
if ($ip_table && $ip_table->num_rows > 0) {
    $ip_check = $conn->prepare("
        SELECT ip.reg_id, ip.internship_chosen, ip.payment_status
        FROM internship_payments ip
        JOIN internship_registrations ir ON ip.reg_id = ir.id
        WHERE CONVERT(ir.email USING utf8mb4) COLLATE utf8mb4_unicode_ci =
              CONVERT((SELECT email FROM users WHERE id = ? LIMIT 1) USING utf8mb4) COLLATE utf8mb4_unicode_ci
          AND ip.payment_status = 'payment_later'
        LIMIT 1
    ");
    if ($ip_check) {
        $ip_check->bind_param("i", $user_id);
        $ip_check->execute();
        $internship_pending = $ip_check->get_result()->fetch_assoc();
        $ip_check->close();
    }
}

/* ── Enrolled courses ── */
$stmt = $conn->prepare("
    SELECT courses.*, enrollments.progress
    FROM enrollments
    JOIN courses ON enrollments.course_id = courses.id
    WHERE enrollments.user_id = ? AND enrollments.status = 'approved'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$enrolled = $stmt->get_result();

/* ── Available courses LEFT JOIN syllabus ── */
$stmt2 = $conn->prepare("
    SELECT c.*, cs.pdf_path AS syl_path, cs.file_name AS syl_name
    FROM courses c
    LEFT JOIN course_syllabus cs ON cs.course_id = c.id
    WHERE c.id NOT IN (
        SELECT course_id FROM enrollments WHERE user_id = ? AND status = 'approved'
    )
");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$available = $stmt2->get_result();

/* Collect available courses into array for JS */
$avail_courses = [];
while ($row = $available->fetch_assoc()) {
    $avail_courses[] = $row;
}

/* ── Upcoming live classes ── */
$lc_upcoming = [];
$lc_table_check = $conn->query("SHOW TABLES LIKE 'live_classes'");
if ($lc_table_check && $lc_table_check->num_rows > 0) {
    $lc_now = date('Y-m-d H:i:s');
    $lc_q = $conn->prepare("
        SELECT lc.id, lc.title, lc.meeting_id, lc.scheduled_at, lc.duration,
               c.title AS course_title, u.name AS trainer_name
        FROM live_classes lc
        JOIN courses c     ON lc.course_id  = c.id
        JOIN users u       ON lc.trainer_id = u.id
        JOIN enrollments e ON e.course_id   = lc.course_id AND e.user_id = ? AND e.status = 'approved'
        WHERE DATE_ADD(lc.scheduled_at, INTERVAL lc.duration MINUTE) >= ?
        ORDER BY lc.scheduled_at ASC LIMIT 3
    ");
    $lc_q->bind_param("is", $user_id, $lc_now);
    $lc_q->execute();
    $lc_res = $lc_q->get_result();
    while ($r = $lc_res->fetch_assoc()) $lc_upcoming[] = $r;
}

$page_title = "My Courses";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses – CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        /* ═══════════════════════════════════════════════
           OVERLAY BACKDROP
        ═══════════════════════════════════════════════ */
        .syl-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
        }
        .syl-overlay.active { display: flex; }

        /* ═══════════════════════════════════════════════
           PANEL CONTAINER — full screen
        ═══════════════════════════════════════════════ */
        .syl-panel {
            background: var(--card);
            border-radius: 0;
            box-shadow: none;
            width: 100vw;
            height: 100vh;
            max-width: 100%;
            max-height: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            transition: grid-template-columns 0.38s cubic-bezier(.4,0,.2,1);
            position: relative;
        }

        /* Expanded state: left collapses to 0 */
        .syl-panel.pdf-expanded {
            grid-template-columns: 0fr 1fr;
        }
        .syl-panel.pdf-expanded .syl-left {
            opacity: 0;
            pointer-events: none;
            overflow: hidden;
        }

        /* ═══════════════════════════════════════════════
           LEFT HALF – course info + enroll
        ═══════════════════════════════════════════════ */
        .syl-left {
            padding: 28px 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
            transition: opacity 0.3s ease;
            min-width: 0;
            border-right: 1px solid var(--border-light);
        }
        .syl-thumb {
            width: 100%;
            height: 170px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-light), #dbeeff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--primary);
            flex-shrink: 0;
        }
        .syl-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .syl-course-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.3;
        }
        .syl-course-desc {
            font-size: .84rem;
            color: var(--text-secondary);
            line-height: 1.65;
        }
        .gst-box {
            background: var(--bg-alt);
            border: 1.5px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 7px;
            font-size: .82rem;
        }
        .gst-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-secondary);
        }
        .gst-row.total {
            font-size: .9rem;
            font-weight: 700;
            color: var(--text-primary);
            padding-top: 7px;
            border-top: 1px solid var(--border-light);
        }
        .gst-row.total span:last-child { color: var(--primary-dark); }

        .syl-enroll-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 20px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-weight: 700;
            font-size: .9rem;
            text-decoration: none;
            box-shadow: 0 2px 10px rgba(77,168,255,.34);
            transition: all var(--transition);
            margin-top: auto;
        }
        .syl-enroll-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-deeper));
            box-shadow: 0 4px 18px rgba(77,168,255,.45);
            transform: translateY(-1px);
            color: #fff;
        }

        /* ═══════════════════════════════════════════════
           RIGHT HALF – Syllabus PDF
        ═══════════════════════════════════════════════ */
        .syl-right {
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
        }
        .syl-right-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-light);
            background: var(--card);
            flex-shrink: 0;
            gap: 10px;
        }
        .syl-right-header .syl-pane-title {
            font-weight: 700;
            font-size: .88rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .syl-right-header .syl-pane-title i { color: #DC2626; }

        .syl-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .syl-icon-btn {
            width: 34px;
            height: 34px;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border);
            background: var(--bg);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: .85rem;
            transition: all var(--transition);
            flex-shrink: 0;
        }
        .syl-icon-btn:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
            border-color: var(--primary);
        }
        .syl-icon-btn.close-btn:hover {
            background: var(--danger-light);
            color: var(--danger);
            border-color: var(--danger);
        }

        .syl-pdf-area {
            flex: 1;
            overflow: hidden;
            background: #f4f6fa;
        }
        .syl-pdf-area iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        .syl-coming-soon {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 14px;
            padding: 40px 24px;
            text-align: center;
        }
        .syl-coming-soon .cs-icon {
            width: 70px;
            height: 70px;
            border-radius: var(--radius-lg);
            background: var(--warning-light);
            color: #D97706;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        .syl-coming-soon h3 {
            font-size: .98rem;
            color: var(--text-primary);
            margin: 0;
        }
        .syl-coming-soon p {
            font-size: .82rem;
            color: var(--text-muted);
            margin: 0;
            max-width: 260px;
        }

        /* ═══════════════════════════════════════════════
           MOBILE RESPONSIVE
        ═══════════════════════════════════════════════ */
        @media (max-width: 760px) {
            .syl-panel {
                grid-template-columns: 1fr !important;
                grid-template-rows: auto 1fr;
                height: 100vh;
                border-radius: 0;
            }
            .syl-panel.pdf-expanded {
                grid-template-columns: 1fr !important;
                grid-template-rows: 0fr 1fr;
            }
            .syl-panel.pdf-expanded .syl-left { max-height: 0; padding: 0; }
            .syl-left {
                border-right: none;
                border-bottom: 1px solid var(--border-light);
                max-height: 50vh;
            }
            .syl-right { min-height: 0; }
            .syl-expand-btn { display: none !important; }
        }
        @media (max-width: 520px) {
            .syl-thumb { height: 130px; }
            .syl-left  { padding: 16px; gap: 12px; }
        }

        /* ── Available card: View Details button ── */
        .btn-view-details {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-size: .78rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
        }
        .btn-view-details:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-deeper));
            transform: translateY(-1px);
            color: #fff;
        }
        /* Clickable course card overlay */
        .avail-card-clickable {
            cursor: pointer;
            transition: transform var(--transition), box-shadow var(--transition);
        }
        .avail-card-clickable:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        @media (max-width: 520px) {
            .course-actions { flex-wrap: wrap; gap: 6px; }
            .btn-view-details { flex: 1 1 100%; justify-content: center; min-height: 44px; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <div style="background:linear-gradient(135deg, #bf953f, #fcf6ba, #b38728, #fbf5b7, #aa771c); border-radius:12px; padding:30px 40px; color:#5c3a00; margin-bottom:24px; position:relative; overflow:hidden; display:flex; justify-content:space-between; align-items:center;">
                <div style="z-index:2; position:relative;">
                    <h2 style="font-size:1.8rem; font-weight:800; color:#5c3a00; margin-bottom:10px;">What's new on<br><span style="color:#3e2600;">CourseFlow?</span></h2>
                </div>
                <div style="z-index:2; position:relative; font-size:0.85rem; color:#5c3a00; line-height:1.6; font-weight:600;">
                    <?php foreach($hero_notifs as $n): ?>
                        <div style="display:flex; align-items:center; gap:8px;"><i class="fas fa-bullhorn" style="font-size:0.6rem; color:#784f00;"></i> <?= htmlspecialchars($n) ?></div>
                    <?php endforeach; ?>
                    <a href="dashboard.php" style="display:inline-block; margin-top:14px; padding:8px 16px; background:#5c3a00; color:#fff; border-radius:20px; font-weight:700; text-decoration:none; border:1px solid #5c3a00;">Refresh Dashboard</a>
                </div>
                <!-- Graphic decoration -->
                <i class="fas fa-rocket" style="position:absolute; right:40px; top:50%; transform:translateY(-50%); font-size:120px; color:rgba(92, 58, 0, 0.1); z-index:1;"></i>
            </div>

            <!-- ── INTERNSHIP PENDING BANNER ── -->
            <?php if ($internship_pending): ?>
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;background:#fef3c7;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-exclamation-triangle" style="color:#d97706;font-size:.9rem;"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;color:#92400e;font-size:.9rem;">Internship Payment Pending</div>
                        <div style="color:#b45309;font-size:.8rem;margin-top:2px;">Your <strong><?= htmlspecialchars($internship_pending['internship_chosen']) ?></strong> internship seat is reserved. Complete payment to confirm enrollment.</div>
                    </div>
                </div>
                <a href="../internship/internship_payment.php?reg_id=<?= (int)$internship_pending['reg_id'] ?>"
                   style="background:#f59e0b;color:#fff;padding:9px 18px;border-radius:6px;font-size:.83rem;font-weight:700;text-decoration:none;white-space:nowrap;flex-shrink:0;">
                    <i class="fas fa-credit-card"></i> Complete Payment →
                </a>
            </div>
            <?php endif; ?>

            <!-- ── WELCOME & RANKS ── -->
            <div style="display:flex; gap:20px; margin-bottom:24px; flex-wrap:wrap;">
                <div style="flex:1; min-width:300px; display:flex; flex-direction:column; justify-content:center;">
                    <h2 style="font-size:1.4rem; color:var(--text-primary); margin-bottom:4px;">Welcome <?= strtoupper(htmlspecialchars($_SESSION['name'])) ?>,</h2>
                    <p style="color:var(--text-secondary); font-size:0.95rem;">keep up the great work!</p>
                </div>
                <div style="display:flex; gap:16px; flex-wrap:wrap;">
                    <div style="background:#f0fdfa; border:1px solid #99f6e4; border-radius:8px; padding:16px 24px; min-width:200px;">
                        <div style="display:flex; align-items:center; gap:8px; color:#0f766e; font-weight:700; font-size:0.85rem; margin-bottom:6px;"><i class="fas fa-trophy"></i> Your Global Rank #<?= $my_rank ?></div>
                        <div style="font-size:0.75rem; color:#115e59;">Keep solving to climb the leaderboard!</div>
                    </div>
                </div>
            </div>



            <!-- ── STATS & SCORE ROW ── -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:32px;">
                <!-- Stats Column -->
                <div>
                    <h3 style="font-size:1.1rem; color:var(--text-primary); margin-bottom:16px;">Stats</h3>
                    <div style="background:#fff; border:1px solid var(--border-light); border-radius:12px; box-shadow:var(--shadow-xs); padding:32px; display:flex; flex-direction:column; align-items:center; justify-content:center; margin-bottom:16px;">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                            <i class="fas fa-fire" style="font-size:2rem; color:#f97316;"></i>
                            <span style="font-size:2.2rem; font-weight:800; color:var(--text-primary);"><?= $stats_streak ?> days</span>
                        </div>
                        <div style="color:var(--text-secondary); font-size:0.9rem;">Longest problem Streak</div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        <div style="background:#fff; border:1px solid var(--border-light); border-radius:12px; box-shadow:var(--shadow-xs); padding:20px;">
                            <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:8px; display:flex; align-items:center; gap:6px;"><i class="far fa-lightbulb" style="color:#22c55e;"></i> Problems Solved</div>
                            <div style="font-size:1.8rem; font-weight:800; color:var(--text-primary);"><?= $problems_solved ?></div>
                        </div>
                        <div style="background:#fff; border:1px solid var(--border-light); border-radius:12px; box-shadow:var(--shadow-xs); padding:20px;">
                            <div style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:8px; display:flex; align-items:center; gap:6px;"><i class="far fa-folder" style="color:#3b82f6;"></i> Total Projects</div>
                            <div style="font-size:1.8rem; font-weight:800; color:var(--text-primary);"><?= $total_projects ?></div>
                        </div>
                    </div>
                </div>

                <!-- ByteXL Score Column -->
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h3 style="font-size:1.1rem; color:var(--text-primary);">CourseFlow Score</h3>
                        <a href="reports.php" style="font-size:0.8rem; color:#3b82f6; text-decoration:none;">Score Breakdown</a>
                    </div>
                    <div style="background:#fff; border:1px solid var(--border-light); border-radius:12px; box-shadow:var(--shadow-xs); padding:24px; display:flex; flex-direction:column; align-items:center; height:calc(100% - 38px);">
                        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px 16px; font-size:0.75rem; color:#166534; width:100%; margin-bottom:20px; display:flex; align-items:center; gap:8px;">
                            <div style="width:6px; height:6px; background:#22c55e; border-radius:50%;"></div>
                            Earn more points by completing courses, quizzes, and projects to increase your rank!
                        </div>
                        <div style="position:relative; width:280px; height:140px; overflow:hidden; margin: 0 auto;">
                            <!-- Colorful arc -->
                            <div style="position:absolute; top:0; left:0; width:280px; height:280px; border-radius:50%; background:conic-gradient(from 270deg, #94a3b8 0%, #ef4444 15%, #f59e0b 35%, #10b981 50%, transparent 50%);"></div>
                            <!-- Inner white cutout -->
                            <div style="position:absolute; top:30px; left:30px; width:220px; height:220px; background:#fff; border-radius:50%; z-index:2;"></div>
                            <!-- Text inside cutout -->
                            <div style="position:absolute; bottom:15px; left:0; width:100%; text-align:center; z-index:4;">
                                <div style="font-size:2.8rem; font-weight:800; color:var(--text-primary); line-height:1;"><?= $stats_points ?></div>
                                <div style="font-size:1rem; color:#3b82f6; font-weight:600; margin-top:4px;"><?= $score_tier ?></div>
                            </div>
                            
                            <style>
                                @keyframes sweepNeedle {
                                    0% { transform: rotate(-90deg); }
                                    100% { transform: rotate(var(--target-deg)); }
                                }
                            </style>
                            <!-- Needle -->
                            <div style="position:absolute; bottom:0; left:138px; width:4px; height:130px; background:transparent; transform-origin:bottom center; transform:rotate(-90deg); animation: sweepNeedle 1.8s cubic-bezier(0.22, 1, 0.36, 1) forwards; --target-deg: <?= $gauge_deg ?>deg; z-index:5;">
                                <!-- Needle triangle -->
                                <div style="width:0; height:0; border-left:5px solid transparent; border-right:5px solid transparent; border-bottom:130px solid #1e293b; position:absolute; bottom:0; left:-3px;"></div>
                                <!-- Pivot circle -->
                                <div style="width:24px; height:24px; border-radius:50%; background:#1e293b; position:absolute; bottom:-12px; left:-10px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── LEARNING TO EARNING ── -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-size:1.1rem; color:var(--text-primary);">Learning to Earning</h3>
                <a href="student_dashboard.php" style="font-size:0.8rem; color:#3b82f6; text-decoration:none;">View All</a>
            </div>
            
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:20px;">
                <!-- Combine Enrolled & Available Courses into this view -->
                <?php 
                $all_courses_display = array_merge(
                    $enrolled->num_rows > 0 ? $enrolled->fetch_all(MYSQLI_ASSOC) : [],
                    $avail_courses
                );
                if (!empty($all_courses_display)): 
                ?>
                    <?php foreach (array_slice($all_courses_display, 0, 4) as $row): ?>
                    <div class="course-card avail-card-clickable" onclick="window.location.href='course_lessons.php?course_id=<?= $row['id'] ?>'" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; text-align:center; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                        <div style="height:120px; display:flex; align-items:center; justify-content:center; margin-bottom:16px;">
                            <?php if (!empty($row['image'])): ?>
                                <img src="<?= htmlspecialchars($row['image']) ?>" alt="course" style="max-height:100%; object-fit:contain;">
                            <?php else: ?>
                                <i class="fas fa-laptop-code" style="font-size:4.5rem; color:#cbd5e1;"></i>
                            <?php endif; ?>
                        </div>
                        <h4 style="font-size:0.95rem; font-weight:700; color:var(--text-primary); margin-bottom:6px;"><?= htmlspecialchars($row['title']) ?></h4>
                        <div style="font-size:0.75rem; color:#64748b;"><?= htmlspecialchars(substr($row['description'], 0, 40)) ?>...</div>
                        <div style="margin-top:16px; font-size:0.8rem; font-weight:700; color:var(--text-primary); text-align:left; display:flex; justify-content:space-between;">
                            <span><?= isset($row['progress']) ? $row['progress'].'% Completed' : 'Placement Readiness' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column:1/-1; padding:40px; text-align:center; color:#94a3b8; background:#fff; border-radius:12px; border:1px solid #e2e8f0;">
                        No courses available at the moment.
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SPLIT PANEL OVERLAY
════════════════════════════════════════════════════════════ -->
<div class="syl-overlay" id="sylOverlay">
    <div class="syl-panel" id="sylPanel">

        <!-- LEFT: Course info + GST + Enroll -->
        <div class="syl-left" id="sylLeft">
            <div class="syl-thumb" id="sylThumb">
                <i class="fas fa-book"></i>
            </div>
            <div class="syl-course-title" id="sylTitle">Course Title</div>
            <div class="syl-course-desc" id="sylDesc"></div>

            <div class="gst-box" id="gstBox" style="display:none">
                <div class="gst-row">
                    <span>Course Fee</span>
                    <span id="gstBase">₹0</span>
                </div>
                <div class="gst-row">
                    <span>GST (18%)</span>
                    <span id="gstAmt">₹0</span>
                </div>
                <div class="gst-row total">
                    <span>Total Payable</span>
                    <span id="gstTotal">₹0</span>
                </div>
            </div>
            <div id="freeTag" style="display:none;font-size:.82rem;font-weight:700;color:#16A34A;display:none">
                <i class="fas fa-gift"></i> This course is Free!
            </div>

            <a href="#" class="syl-enroll-btn" id="sylEnrollBtn" target="_self">
                <i class="fas fa-credit-card"></i> Pay &amp; Enroll
            </a>
        </div>

        <!-- RIGHT: Syllabus PDF pane -->
        <div class="syl-right">
            <div class="syl-right-header">
                <div class="syl-pane-title">
                    <i class="fas fa-file-pdf"></i>
                    <span>Course Syllabus</span>
                </div>
                <div class="syl-controls">
                    <!-- Expand / Collapse toggle -->
                    <button class="syl-icon-btn syl-expand-btn" id="sylExpandBtn" onclick="toggleExpand()" title="Expand PDF">
                        <i class="fas fa-expand-alt" id="sylExpandIcon"></i>
                    </button>
                    <!-- Open in new tab (only when PDF available) -->
                    <a href="#" class="syl-icon-btn" id="sylOpenBtn" target="_blank" title="Open PDF in new tab" style="display:none">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <!-- Close panel -->
                    <button class="syl-icon-btn close-btn" onclick="closeSylPanel()" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="syl-pdf-area" id="sylPdfArea">
                <!-- Dynamically filled by JS -->
            </div>
        </div>

    </div>
</div>

<!-- Course data passed to JS -->
<script>
const COURSES = <?= json_encode(array_map(function($c) {
    return [
        'id'       => (int)$c['id'],
        'title'    => $c['title'],
        'desc'     => $c['description'],
        'image'    => $c['image'] ?? '',
        'price'    => (float)$c['price'],
        'syl_path' => $c['syl_path'] ?? '',
        'syl_name' => $c['syl_name'] ?? '',
    ];
}, $avail_courses), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

const overlay      = document.getElementById('sylOverlay');
const panel        = document.getElementById('sylPanel');
const sylThumb     = document.getElementById('sylThumb');
const sylTitle     = document.getElementById('sylTitle');
const sylDesc      = document.getElementById('sylDesc');
const gstBox       = document.getElementById('gstBox');
const freeTag      = document.getElementById('freeTag');
const gstBase      = document.getElementById('gstBase');
const gstAmt       = document.getElementById('gstAmt');
const gstTotal     = document.getElementById('gstTotal');
const sylEnrollBtn = document.getElementById('sylEnrollBtn');
const sylPdfArea   = document.getElementById('sylPdfArea');
const sylOpenBtn   = document.getElementById('sylOpenBtn');
const sylExpandBtn = document.getElementById('sylExpandBtn');
const sylExpandIcon= document.getElementById('sylExpandIcon');
let expanded = false;

function openSylPanel(courseId) {
    const c = COURSES.find(x => x.id === courseId);
    if (!c) return;

    /* Thumbnail */
    if (c.image) {
        sylThumb.innerHTML = '<img src="' + escHtml(c.image) + '" alt="course">';
    } else {
        sylThumb.innerHTML = '<i class="fas fa-book"></i>';
    }

    /* Title & Description */
    sylTitle.textContent = c.title;
    sylDesc.textContent  = c.desc;

    /* Price / GST */
    if (c.price > 0) {
        const gst   = Math.round(c.price * 0.18 * 100) / 100;
        const total = Math.round((c.price + gst) * 100) / 100;
        gstBase.textContent  = '₹' + c.price.toLocaleString('en-IN', {minimumFractionDigits:2});
        gstAmt.textContent   = '₹' + gst.toLocaleString('en-IN', {minimumFractionDigits:2});
        gstTotal.textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits:2});
        gstBox.style.display  = 'flex';
        freeTag.style.display = 'none';
        sylEnrollBtn.innerHTML = '<i class="fas fa-credit-card"></i> Pay &amp; Enroll';
    } else {
        gstBox.style.display  = 'none';
        freeTag.style.display = 'block';
        sylEnrollBtn.innerHTML = '<i class="fas fa-rocket"></i> Enroll Now (Free)';
    }
    sylEnrollBtn.href = 'payment.php?course_id=' + courseId;

    /* PDF */
    if (c.syl_path) {
        sylPdfArea.innerHTML = '<iframe src="/' + escHtml(c.syl_path) + '#toolbar=1&navpanes=0" title="Syllabus PDF"></iframe>';
        sylOpenBtn.href          = '/' + escHtml(c.syl_path);
        sylOpenBtn.style.display = 'flex';
    } else {
        sylPdfArea.innerHTML = comingSoonHTML();
        sylOpenBtn.style.display = 'none';
    }

    /* Reset expand state */
    if (expanded) toggleExpand();

    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSylPanel() {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
    /* Clear iframe to stop PDF loading in background */
    sylPdfArea.innerHTML = '';
    if (expanded) {
        panel.classList.remove('pdf-expanded');
        sylExpandIcon.className = 'fas fa-expand-alt';
        expanded = false;
    }
}

function toggleExpand() {
    expanded = !expanded;
    panel.classList.toggle('pdf-expanded', expanded);
    if (expanded) {
        sylExpandIcon.className = 'fas fa-compress-alt';
        sylExpandBtn.title      = 'Collapse PDF';
    } else {
        sylExpandIcon.className = 'fas fa-expand-alt';
        sylExpandBtn.title      = 'Expand PDF';
    }
}

function comingSoonHTML() {
    return '<div class="syl-coming-soon">'
        + '<div class="cs-icon"><i class="fas fa-file-pdf"></i></div>'
        + '<h3>Syllabus Coming Soon</h3>'
        + '<p>The syllabus for this course hasn\'t been uploaded yet. Check back soon!</p>'
        + '</div>';
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

/* Keyboard close */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        sylPdfArea.innerHTML = '';
        if (expanded) { panel.classList.remove('pdf-expanded'); expanded = false; }
    }
});
</script>

<?php include("../includes/footer.php"); ?>