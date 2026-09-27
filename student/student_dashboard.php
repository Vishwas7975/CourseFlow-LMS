<?php
/**
 * student/dashboard.php — CourseFlow LMS
 * DataValley-style layout: Sidebar | Main | Right achievement panel
 * Tables auto-created on first load — no manual SQL needed.
 */

session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("student");

$user_id = (int)$_SESSION['user_id'];
$today   = date('Y-m-d');
$now_ts  = time();

/* ═══════════════════════════════════════════
   AUTO-MIGRATIONS (safe every request)
═══════════════════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS student_stats (
    user_id          INT  PRIMARY KEY,
    points           INT  NOT NULL DEFAULT 0,
    coins            INT  NOT NULL DEFAULT 0,
    streak_days      INT  NOT NULL DEFAULT 0,
    last_active_date DATE DEFAULT NULL,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS student_daily_logins (
    id         INT  AUTO_INCREMENT PRIMARY KEY,
    user_id    INT  NOT NULL,
    login_date DATE NOT NULL,
    UNIQUE KEY uq_user_day (user_id, login_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS student_daily_goal (
    user_id      INT  PRIMARY KEY,
    goal_coins   INT  NOT NULL DEFAULT 50,
    earned_today INT  NOT NULL DEFAULT 0,
    goal_date    DATE DEFAULT NULL,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ── Seed rows for this student ── */
$conn->query("INSERT IGNORE INTO student_stats (user_id) VALUES ($user_id)");
$conn->query("INSERT IGNORE INTO student_daily_goal (user_id, goal_date) VALUES ($user_id, '$today')");

/* ── Record today's login ── */
$conn->query("INSERT IGNORE INTO student_daily_logins (user_id, login_date) VALUES ($user_id, '$today')");

/* ── Calculate streak ── */
$streak_res  = $conn->query("SELECT login_date FROM student_daily_logins WHERE user_id=$user_id ORDER BY login_date DESC");
$login_dates = array();
while ($lr = $streak_res->fetch_assoc()) { $login_dates[] = $lr['login_date']; }

$streak = 0;
$check  = $today;
foreach ($login_dates as $d) {
    if ($d === $check) {
        $streak++;
        $check = date('Y-m-d', strtotime($check . ' -1 day'));
    } else {
        break;
    }
}

/* ── Award coins/points on first login today ── */
$stat_row = $conn->query("SELECT * FROM student_stats WHERE user_id=$user_id")->fetch_assoc();
if ($stat_row['last_active_date'] !== $today) {
    $conn->query("UPDATE student_stats
                  SET streak_days=$streak, last_active_date='$today',
                      coins=coins+5, points=points+10
                  WHERE user_id=$user_id");
    $conn->query("UPDATE student_daily_goal SET earned_today=earned_today+5, goal_date='$today' WHERE user_id=$user_id");
} else {
    $conn->query("UPDATE student_stats SET streak_days=$streak WHERE user_id=$user_id");
}

/* ── Reset daily goal if new day ── */
$conn->query("UPDATE student_daily_goal SET earned_today=0, goal_date='$today' WHERE user_id=$user_id AND goal_date < '$today'");

/* ── Fetch final stats ── */
$stats    = $conn->query("SELECT * FROM student_stats    WHERE user_id=$user_id")->fetch_assoc();
$day_goal = $conn->query("SELECT * FROM student_daily_goal WHERE user_id=$user_id")->fetch_assoc();

$streak_days = (int)$stats['streak_days'];
$points      = (int)$stats['points'];
$coins       = (int)$stats['coins'];
$goal_target = (int)$day_goal['goal_coins'];
$goal_earned = (int)$day_goal['earned_today'];
$goal_pct    = $goal_target > 0 ? min(100, (int)round($goal_earned / $goal_target * 100)) : 0;

/* ── User info ── */
$user_q = $conn->prepare("SELECT name, email, profile_pic FROM users WHERE id=?");
$user_q->bind_param("i", $user_id);
$user_q->execute();
$user = $user_q->get_result()->fetch_assoc();
$user_q->close();

/* ═══════════════════════════════════════════
   INTERNSHIP PENDING BANNER
═══════════════════════════════════════════ */
$internship_pending = null;
$ip_table = $conn->query("SHOW TABLES LIKE 'internship_payments'");
if ($ip_table && $ip_table->num_rows > 0) {
    $ip_check = $conn->prepare("
        SELECT ip.reg_id, ip.internship_chosen, ip.payment_status
        FROM internship_payments ip
        JOIN internship_registrations ir ON ip.reg_id = ir.id
        WHERE CONVERT(ir.email USING utf8mb4) COLLATE utf8mb4_unicode_ci =
              CONVERT((SELECT email FROM users WHERE id=? LIMIT 1) USING utf8mb4) COLLATE utf8mb4_unicode_ci
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

/* ═══════════════════════════════════════════
   ENROLLED COURSES
═══════════════════════════════════════════ */
$stmt = $conn->prepare("SELECT courses.*, enrollments.progress FROM enrollments JOIN courses ON enrollments.course_id=courses.id WHERE enrollments.user_id=? AND enrollments.status='approved'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$enrolled = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ═══════════════════════════════════════════
   AVAILABLE COURSES
═══════════════════════════════════════════ */
$stmt2 = $conn->prepare("SELECT * FROM courses WHERE id NOT IN (SELECT course_id FROM enrollments WHERE user_id=? AND status='approved')");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$available = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

/* ═══════════════════════════════════════════
   UPCOMING LIVE CLASSES
═══════════════════════════════════════════ */
$lc_upcoming = array();
$lc_check = $conn->query("SHOW TABLES LIKE 'live_classes'");
if ($lc_check && $lc_check->num_rows > 0) {
    $lc_now = date('Y-m-d H:i:s');
    $lc_q   = $conn->prepare("
        SELECT lc.id, lc.title, lc.meeting_id, lc.scheduled_at, lc.duration,
               c.title AS course_title, u.name AS trainer_name
        FROM live_classes lc
        JOIN courses c     ON lc.course_id=c.id
        JOIN users u       ON lc.trainer_id=u.id
        JOIN enrollments e ON e.course_id=lc.course_id AND e.user_id=? AND e.status='approved'
        WHERE DATE_ADD(lc.scheduled_at, INTERVAL lc.duration MINUTE) >= ?
        ORDER BY lc.scheduled_at ASC LIMIT 3
    ");
    $lc_q->bind_param("is", $user_id, $lc_now);
    $lc_q->execute();
    $lc_res = $lc_q->get_result();
    while ($r = $lc_res->fetch_assoc()) { $lc_upcoming[] = $r; }
}

/* ── Is any class live right now? ── */
$any_live = false;
foreach ($lc_upcoming as $lc) {
    $ts  = strtotime($lc['scheduled_at']);
    $end = $ts + $lc['duration'] * 60;
    if ($now_ts >= $ts && $now_ts <= $end) { $any_live = true; break; }
}

/* ═══════════════════════════════════════════
   LATEST LESSON
═══════════════════════════════════════════ */
$latest_lesson = null;
$ll_q = $conn->prepare("
    SELECT l.id, l.title, l.content_type, c.id AS course_id, c.title AS course_title
    FROM lessons l
    JOIN courses c ON l.course_id=c.id
    JOIN enrollments e ON e.course_id=c.id AND e.user_id=? AND e.status='approved'
    ORDER BY l.created_at DESC LIMIT 1
");
$ll_q->bind_param("i", $user_id);
$ll_q->execute();
$latest_lesson = $ll_q->get_result()->fetch_assoc();
$ll_q->close();

/* ═══════════════════════════════════════════
   QUIZ RESULTS
═══════════════════════════════════════════ */
$quizzes = array();
$qz_check = $conn->query("SHOW TABLES LIKE 'quiz_results'");
if ($qz_check && $qz_check->num_rows > 0) {
    $qz_q = $conn->prepare("SELECT qr.*, q.title FROM quiz_results qr JOIN quizzes q ON qr.quiz_id=q.id WHERE qr.user_id=? ORDER BY qr.created_at DESC LIMIT 3");
    $qz_q->bind_param("i", $user_id);
    $qz_q->execute();
    $quizzes = $qz_q->get_result()->fetch_all(MYSQLI_ASSOC);
    $qz_q->close();
}

/* ═══════════════════════════════════════════
   CALENDAR STRIP  (today-2 … today+8)
═══════════════════════════════════════════ */
$cal_days = array();
for ($i = -2; $i <= 8; $i++) {
    $ts = strtotime("$today $i day");
    $cal_days[] = array(
        'ts'    => $ts,
        'day'   => (int)date('j', $ts),
        'dow'   => strtoupper(date('D', $ts)),
        'date'  => date('Y-m-d', $ts),
        'label' => date('D, M j, Y', $ts),
        'today' => (date('Y-m-d', $ts) === $today),
    );
}

/* ── Circle math ── */
$ring_r      = 37;
$ring_circum = 2 * M_PI * $ring_r;
$ring_offset = $ring_circum * (1 - $goal_pct / 100);

/* ── First enrolled course id (for quick links) ── */
$first_course_id = count($enrolled) ? (int)$enrolled[0]['id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Dashboard — CourseFlow LMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="../assets/css/style.css"/>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
/* ── Layout ── */
*, *::before, *::after { box-sizing: border-box; }
.dash-layout {
    display: grid;
    grid-template-columns: 1fr 290px;
    gap: 22px;
    align-items: start;
}
.dash-main  { min-width:0; display:flex; flex-direction:column; gap:20px; }
.dash-right { position:sticky; top:20px; display:flex; flex-direction:column; gap:14px; }

/* ── Section card ── */
.sec-card {
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:20px 22px;
    box-shadow:0 2px 8px rgba(0,0,0,.04);
}
.sec-hdr {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:16px;
}
.sec-hdr h3 {
    font-size:.92rem; font-weight:800; color:#1e293b;
    margin:0; display:flex; align-items:center; gap:8px;
}
.sec-link {
    font-size:.76rem; font-weight:700; color:#7c3aed;
    text-decoration:none; display:flex; align-items:center; gap:4px;
}
.sec-link:hover { color:#4c1d95; }

/* ── Welcome banner ── */
.wb {
    background:linear-gradient(135deg,#1e1b4b 0%,#312e81 55%,#4c1d95 100%);
    border-radius:18px; padding:26px 28px; color:#fff;
    position:relative; overflow:hidden;
}
.wb::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:220px; height:220px;
    background:rgba(255,255,255,.06); border-radius:50%;
}
.wb-hi  { font-size:1.4rem; font-weight:800; margin-bottom:4px; }
.wb-sub { font-size:.84rem; color:rgba(255,255,255,.7); }
.wb-chips { display:flex; gap:9px; margin-top:15px; flex-wrap:wrap; }
.wb-chip {
    display:inline-flex; align-items:center; gap:6px;
    padding:5px 13px; border-radius:999px;
    background:rgba(255,255,255,.13);
    font-size:.75rem; font-weight:700; color:#fff;
}

/* ── Internship alert ── */
.int-alert {
    background:#fffbeb; border:1.5px solid #fde68a;
    border-radius:14px; padding:14px 18px;
    display:flex; align-items:center; justify-content:space-between;
    gap:14px; flex-wrap:wrap;
}

/* ── Calendar strip ── */
.cal-strip { display:flex; gap:6px; overflow-x:auto; padding-bottom:4px; }
.cal-strip::-webkit-scrollbar { height:3px; }
.cal-strip::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:2px; }
.cal-day {
    display:flex; flex-direction:column; align-items:center;
    min-width:50px; padding:8px 4px; border-radius:12px;
    cursor:pointer; transition:background .15s;
    border:1.5px solid transparent;
}
.cal-day:hover { background:#f5f3ff; }
.cal-day.today {
    background:#7c3aed; color:#fff;
    border-color:#7c3aed;
    box-shadow:0 4px 14px rgba(124,58,237,.35);
}
.cal-day .cal-dow { font-size:.6rem; font-weight:700; letter-spacing:.05em; color:inherit; opacity:.65; margin-bottom:4px; }
.cal-day.today .cal-dow { opacity:.85; }
.cal-day .cal-num { font-size:1rem; font-weight:800; color:#1e293b; }
.cal-day.today .cal-num { color:#fff; }
.cal-lbl { font-size:.78rem; color:#64748b; font-weight:600; margin-top:10px; }

/* ── Assessment empty ── */
.assess-empty {
    border:1.5px dashed #e2e8f0; border-radius:12px;
    padding:20px; text-align:center; color:#94a3b8; font-size:.84rem;
}

/* ── Latest video ── */
.video-card {
    background:linear-gradient(135deg,#fdf4ff,#eff6ff);
    border:1px solid #e9d5ff; border-radius:14px;
    padding:18px; display:flex; align-items:center; gap:16px;
}
.video-tag   { font-size:.63rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:#7c3aed; margin-bottom:4px; }
.video-title { font-size:.92rem; font-weight:700; color:#1e293b; }
.video-dur   { font-size:.73rem; color:#64748b; margin-top:4px; display:flex; align-items:center; gap:4px; }
.video-play  {
    width:44px; height:44px; border-radius:50%;
    background:linear-gradient(135deg,#7c3aed,#a78bfa);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:.9rem; flex-shrink:0;
    text-decoration:none;
    box-shadow:0 4px 14px rgba(124,58,237,.35);
    transition:transform .18s;
}
.video-play:hover { transform:scale(1.08); }

/* ── Live class row ── */
.lc-row {
    display:flex; align-items:center; gap:12px;
    padding:11px 13px; border-radius:12px;
    border:1px solid #e2e8f0; margin-bottom:8px;
    background:#fff; transition:background .15s;
}
.lc-row:last-child { margin-bottom:0; }
.lc-row:hover { background:#f8fafc; }
.lc-ico { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:.88rem; flex-shrink:0; }
.lc-info { flex:1; min-width:0; }
.lc-title { font-size:.85rem; font-weight:700; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lc-sub   { font-size:.72rem; color:#64748b; margin-top:2px; }
.lc-badge { font-size:.67rem; font-weight:700; padding:3px 8px; border-radius:999px; white-space:nowrap; }

/* ── Course grid ── */
.course-grid-new { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:14px; }
.cc-card {
    background:#fff; border:1px solid #e2e8f0; border-radius:14px;
    overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.04);
    display:flex; flex-direction:column;
    transition:transform .18s, box-shadow .18s;
}
.cc-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,.09); }
.cc-thumb {
    height:108px; display:flex; align-items:center;
    justify-content:center; font-size:2rem;
    background:linear-gradient(135deg,#f5f3ff,#ede9fe); color:#7c3aed;
    overflow:hidden;
}
.cc-thumb img { width:100%; height:100%; object-fit:cover; }
.cc-body { padding:14px; flex:1; display:flex; flex-direction:column; }
.cc-name { font-size:.87rem; font-weight:700; color:#1e293b; margin-bottom:6px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.cc-price { font-size:.74rem; color:#64748b; margin-bottom:8px; }
.cc-price .free { color:#16a34a; font-weight:700; }
.cc-prog-bar { height:5px; background:#f1f5f9; border-radius:99px; margin-bottom:3px; }
.cc-prog-fill { height:100%; border-radius:99px; background:linear-gradient(90deg,#7c3aed,#a78bfa); }
.cc-prog-txt { font-size:.68rem; color:#94a3b8; margin-bottom:10px; }
.cc-actions { display:flex; gap:7px; margin-top:auto; }
.cc-btn {
    display:inline-flex; align-items:center; gap:5px;
    padding:6px 11px; border-radius:8px;
    font-size:.75rem; font-weight:700;
    text-decoration:none; transition:filter .15s;
}
.cc-btn.prim { background:linear-gradient(135deg,#7c3aed,#a78bfa); color:#fff; }
.cc-btn.ghost { background:#f5f3ff; color:#7c3aed; border:1px solid #e9d5ff; }
.cc-btn:hover { filter:brightness(1.06); }

/* ── Quiz score rows ── */
.quiz-row { display:flex; align-items:center; justify-content:space-between; padding:9px 0; border-bottom:1px solid #f1f5f9; gap:8px; }
.quiz-row:last-child { border-bottom:none; }
.quiz-name  { font-size:.82rem; font-weight:600; color:#1e293b; flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.quiz-score { font-size:.78rem; font-weight:800; padding:3px 9px; border-radius:999px; }
.qs-good { background:#dcfce7; color:#14532d; }
.qs-mid  { background:#fef3c7; color:#92400e; }
.qs-low  { background:#ffe4e6; color:#881337; }

/* ── Empty state ── */
.empty-box { text-align:center; padding:28px 16px; color:#94a3b8; }
.empty-box i { font-size:1.9rem; display:block; margin-bottom:10px; }
.empty-box p { font-size:.84rem; margin:0; }

/* ════════════════════════════════
   RIGHT PANEL
════════════════════════════════ */
.rp-card {
    background:#fff; border:1px solid #e2e8f0;
    border-radius:16px; padding:20px 18px;
    box-shadow:0 2px 8px rgba(0,0,0,.04);
}

/* Profile */
.rp-top { display:flex; flex-direction:column; align-items:center; gap:8px; text-align:center; margin-bottom:14px; }
.rp-avatar {
    width:68px; height:68px; border-radius:50%;
    border:3px solid #e9d5ff;
    background:linear-gradient(135deg,#7c3aed,#a78bfa);
    display:flex; align-items:center; justify-content:center;
    font-size:1.6rem; font-weight:800; color:#fff;
    overflow:hidden; flex-shrink:0;
}
.rp-avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
.rp-name  { font-size:.98rem; font-weight:800; color:#1e293b; }
.rp-email { font-size:.72rem; color:#94a3b8; }

/* Live pill */
.live-pill {
    display:flex; align-items:center; justify-content:space-between;
    padding:8px 12px; background:#f0f9ff;
    border:1px solid #bae6fd; border-radius:10px; margin-bottom:16px;
}
.live-pill-l { display:flex; align-items:center; gap:7px; font-size:.78rem; font-weight:700; color:#0369a1; }
.live-dot {
    width:8px; height:8px; border-radius:50%; background:#22c55e;
    box-shadow:0 0 0 2px rgba(34,197,94,.25);
    animation:dpulse 1.5s infinite;
}
@keyframes dpulse { 0%,100%{opacity:1} 50%{opacity:.35} }
.live-pill-r { font-size:.72rem; font-weight:700; color:#0369a1; text-decoration:none; }
.live-pill-r:hover { color:#0284c7; }

/* Achievements */
.ach-lbl { font-size:.7rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.08em; margin-bottom:10px; display:flex; justify-content:space-between; }
.ach-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:16px; }
.ach-item { background:#f8fafc; border-radius:12px; padding:10px 6px; text-align:center; border:1px solid #f1f5f9; }
.ach-item:hover { background:#f5f3ff; border-color:#e9d5ff; }
.ach-emoji { font-size:1.35rem; display:block; margin-bottom:3px; }
.ach-val   { font-size:.98rem; font-weight:800; color:#1e293b; display:block; }
.ach-sub   { font-size:.62rem; color:#94a3b8; display:block; margin-top:1px; }

/* Daily goal */
.goal-hdr { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.goal-hdr-lbl { font-size:.8rem; font-weight:700; color:#1e293b; }
.goal-coin-val { font-size:.78rem; font-weight:700; color:#d97706; display:flex; align-items:center; gap:4px; }
.goal-ring-wrap { display:flex; justify-content:center; margin-bottom:8px; }
.goal-ring { position:relative; width:90px; height:90px; }
.goal-ring svg { transform:rotate(-90deg); display:block; }
.ring-bg { fill:none; stroke:#f1f5f9; stroke-width:8; }
.ring-fg { fill:none; stroke:url(#goalGrad); stroke-width:8; stroke-linecap:round; transition:stroke-dashoffset .5s ease; }
.ring-inner { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.ring-pct { font-size:1.05rem; font-weight:800; color:#1e293b; line-height:1; }
.ring-sub  { font-size:.58rem; color:#94a3b8; margin-top:1px; }
.goal-achieved { text-align:center; font-size:.78rem; font-weight:700; color:#16a34a; display:flex; align-items:center; justify-content:center; gap:5px; }
.goal-progress-txt { text-align:center; font-size:.73rem; color:#94a3b8; }

/* Divider */
.rp-divider { height:1px; background:#f1f5f9; margin:14px 0; }

/* Quick links */
.ql-lbl { font-size:.7rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.08em; margin-bottom:10px; }
.ql-item {
    display:flex; align-items:center; gap:10px;
    padding:8px 10px; border-radius:9px;
    text-decoration:none; color:#1e293b;
    font-size:.82rem; font-weight:600;
    transition:background .15s;
}
.ql-item:hover { background:#f5f3ff; }
.ql-chevron { margin-left:auto; font-size:.62rem; color:#cbd5e1; }

/* ── Responsive ── */

/* Tablet: stack right panel below main, show it as 2-col grid */
@media(max-width:1050px) {
    .dash-layout { grid-template-columns:1fr; }
    .dash-right { position:static; display:grid; grid-template-columns:1fr 1fr; }
}

/* Large mobile: right panel goes single col, courses 2-col */
@media(max-width:620px) {
    .dash-right { grid-template-columns:1fr; }
    .course-grid-new { grid-template-columns:1fr 1fr; }

    /* Welcome banner: tighten padding */
    .wb { padding:20px 18px; }
    .wb-hi { font-size:1.15rem; }

    /* Live class row: allow buttons to wrap below info on small screens */
    .lc-row { flex-wrap:wrap; gap:8px; }
    .lc-row .cc-btn { font-size:.72rem; padding:5px 10px; }

    /* Internship alert: stack content vertically */
    .int-alert { flex-direction:column; align-items:flex-start; }

    /* Video card: stack icon and text vertically */
    .video-card { flex-direction:column; align-items:flex-start; gap:12px; }

    /* Section cards: reduce padding */
    .sec-card { padding:16px 14px; }
}

/* Phone: single column everywhere */
@media(max-width:440px) {
    .course-grid-new { grid-template-columns:1fr; }

    /* Welcome banner chips: smaller text */
    .wb-chips { gap:6px; }
    .wb-chip { font-size:.68rem; padding:4px 10px; }

    /* Achievement grid: keep 3-col but tighten */
    .ach-grid { gap:6px; }
    .ach-item { padding:8px 4px; }
    .ach-val { font-size:.85rem; }

    /* Course action buttons: allow wrapping */
    .cc-actions { flex-wrap:wrap; }
    .cc-btn { flex:1; justify-content:center; min-height:36px; }

    /* Content wrapper: less padding on phones */
    .content-wrapper { padding:14px 10px 40px; }

    /* Section header: allow wrapping on tiny screens */
    .sec-hdr { flex-wrap:wrap; gap:6px; }
}
</style>
</head>
<body>
<div class="dashboard">
<?php include("../includes/sidebar.php"); ?>
<div class="main">
<?php include("../includes/topbar.php"); ?>

<div class="content-wrapper">
<div class="dash-layout">

<!-- ══════════════════════════════
     MAIN COLUMN
══════════════════════════════ -->
<div class="dash-main">

    <!-- Welcome Banner -->
    <div class="wb">
        <div class="wb-hi">Good day, <?php echo htmlspecialchars($user['name']); ?>! 👋</div>
        <div class="wb-sub">Continue your learning journey — you're doing great!</div>
        <div class="wb-chips">
            <span class="wb-chip">🔥 <?php echo $streak_days; ?> Day Streak</span>
            <span class="wb-chip">⭐ <?php echo number_format($points); ?> Points</span>
            <span class="wb-chip">🪙 <?php echo number_format($coins); ?> Coins</span>
            <?php if (count($enrolled) > 0): ?>
            <span class="wb-chip">🎓 <?php echo count($enrolled); ?> Course<?php echo count($enrolled)!==1?'s':''; ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Internship Alert -->
    <?php if ($internship_pending): ?>
    <div class="int-alert">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:38px;height:38px;background:#fef3c7;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-exclamation-triangle" style="color:#d97706;"></i>
            </div>
            <div>
                <div style="font-weight:700;color:#92400e;font-size:.88rem;">Internship Payment Pending</div>
                <div style="color:#b45309;font-size:.77rem;margin-top:2px;">Your <strong><?php echo htmlspecialchars($internship_pending['internship_chosen']); ?></strong> seat is reserved — complete payment to confirm.</div>
            </div>
        </div>
        <a href="../internship/internship_payment.php?reg_id=<?php echo (int)$internship_pending['reg_id']; ?>"
           style="background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;padding:8px 16px;border-radius:9px;font-size:.8rem;font-weight:700;text-decoration:none;white-space:nowrap;flex-shrink:0;">
            <i class="fas fa-credit-card"></i> Complete Payment →
        </a>
    </div>
    <?php endif; ?>

    <!-- New Assessments -->
    <div class="sec-card">
        <div class="sec-hdr">
            <h3><i class="fas fa-clipboard-list" style="color:#7c3aed;"></i> New Assessments</h3>
            <a href="<?php echo $first_course_id ? 'course_quizzes.php?course_id='.$first_course_id : 'course_quizzes.php'; ?>" class="sec-link">View all <i class="fas fa-chevron-right"></i></a>
        </div>
        <?php if (!empty($quizzes)): ?>
            <?php foreach ($quizzes as $qz):
                $pct = (isset($qz['score']) && isset($qz['total']) && (int)$qz['total'] > 0)
                       ? (int)round($qz['score'] / $qz['total'] * 100) : 0;
                $sc_cls = $pct >= 70 ? 'qs-good' : ($pct >= 40 ? 'qs-mid' : 'qs-low');
            ?>
            <div class="quiz-row">
                <span class="quiz-name"><i class="fas fa-file-alt" style="color:#a78bfa;margin-right:6px;"></i><?php echo htmlspecialchars($qz['title']); ?></span>
                <span class="quiz-score <?php echo $sc_cls; ?>"><?php echo $pct; ?>%</span>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="assess-empty">
                <i class="fas fa-clipboard" style="font-size:1.6rem;display:block;margin-bottom:8px;"></i>
                No new assessments available at the moment.
            </div>
        <?php endif; ?>
    </div>

    <!-- Your Schedule -->
    <div class="sec-card">
        <div class="sec-hdr">
            <h3><i class="fas fa-calendar-alt" style="color:#7c3aed;"></i> Your Schedule</h3>
            <a href="live_classes.php" class="sec-link">Calendar <i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="cal-strip">
            <?php foreach ($cal_days as $cd): ?>
            <div class="cal-day <?php echo $cd['today'] ? 'today' : ''; ?>"
                 onclick="selectDay(this,'<?php echo htmlspecialchars($cd['label']); ?>')">
                <span class="cal-dow"><?php echo $cd['dow']; ?></span>
                <span class="cal-num"><?php echo $cd['day']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="cal-lbl" id="calLabel"><?php echo date('D, M j, Y'); ?></div>
    </div>

    <!-- Latest Course Video -->
    <?php if ($latest_lesson): ?>
    <div class="sec-card">
        <div class="sec-hdr">
            <h3><i class="fas fa-play-circle" style="color:#7c3aed;"></i> Latest Course Video</h3>
            <a href="course_lessons.php?course_id=<?php echo (int)$latest_lesson['course_id']; ?>" class="sec-link">All lessons <i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="video-card">
            <div style="flex:1;min-width:0;">
                <div class="video-tag">COURSE &middot; <?php echo htmlspecialchars($latest_lesson['course_title']); ?></div>
                <div class="video-title"><?php echo htmlspecialchars($latest_lesson['title']); ?></div>
                <?php if (!empty($latest_lesson['content_type'])): ?>
                <div class="video-dur"><i class="fas fa-file"></i> Type: <?php echo htmlspecialchars(ucfirst($latest_lesson['content_type'])); ?></div>
                <?php endif; ?>
            </div>
            <a href="course_lessons.php?course_id=<?php echo (int)$latest_lesson['course_id']; ?>" class="video-play">
                <i class="fas fa-play"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upcoming Live Classes -->
    <div class="sec-card">
        <div class="sec-hdr">
            <h3><i class="fas fa-video" style="color:#0ea5e9;"></i> Upcoming Live Classes</h3>
            <a href="live_classes.php" class="sec-link">View all <i class="fas fa-chevron-right"></i></a>
        </div>
        <?php if (!empty($lc_upcoming)): ?>
            <?php foreach ($lc_upcoming as $lc):
                $lc_ts  = strtotime($lc['scheduled_at']);
                $lc_end = $lc_ts + $lc['duration'] * 60;
                $is_live = ($now_ts >= $lc_ts && $now_ts <= $lc_end);
            ?>
            <div class="lc-row">
                <div class="lc-ico" style="background:<?php echo $is_live?'#fee2e2':'#e0f2fe'; ?>;color:<?php echo $is_live?'#dc2626':'#0369a1'; ?>;">
                    <i class="fas fa-video"></i>
                </div>
                <div class="lc-info">
                    <div class="lc-title"><?php echo htmlspecialchars($lc['title']); ?></div>
                    <div class="lc-sub"><i class="fas fa-book-open"></i> <?php echo htmlspecialchars($lc['course_title']); ?> &bull; <i class="fas fa-chalkboard-teacher"></i> <?php echo htmlspecialchars($lc['trainer_name']); ?></div>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <div style="font-size:.76rem;font-weight:700;color:#475569;margin-bottom:3px;"><?php echo date('d M, h:i A', $lc_ts); ?></div>
                    <span class="lc-badge" style="background:<?php echo $is_live?'#fee2e2':'#e0f2fe'; ?>;color:<?php echo $is_live?'#dc2626':'#0369a1'; ?>;">
                        <?php echo $is_live ? '&#9679; Live Now' : $lc['duration'].' min'; ?>
                    </span>
                </div>
                <?php if ($is_live): ?>
                <a href="../live_class.php?meeting_id=<?php echo urlencode($lc['meeting_id']); ?>&class_id=<?php echo (int)$lc['id']; ?>" class="cc-btn prim" target="_blank" style="flex-shrink:0;"><i class="fas fa-video"></i> Join</a>
                <?php else: ?>
                <a href="live_classes.php" class="cc-btn ghost" style="flex-shrink:0;">Details</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-box"><i class="fas fa-video"></i><p>No upcoming live classes yet. Your trainer will schedule sessions soon!</p></div>
        <?php endif; ?>
    </div>

    <!-- Enrolled Courses -->
    <div class="sec-card">
        <div class="sec-hdr">
            <h3><i class="fas fa-graduation-cap" style="color:#7c3aed;"></i> My Enrolled Courses</h3>
        </div>
        <?php if (!empty($enrolled)): ?>
        <div class="course-grid-new">
            <?php foreach ($enrolled as $c): ?>
            <div class="cc-card">
                <div class="cc-thumb">
                    <?php if (!empty($c['image'])): ?><img src="<?php echo htmlspecialchars($c['image']); ?>" alt="course"/><?php else: ?><i class="fas fa-book-open"></i><?php endif; ?>
                </div>
                <div class="cc-body">
                    <div class="cc-name"><?php echo htmlspecialchars($c['title']); ?></div>
                    <div class="cc-price"><?php echo $c['price']>0 ? '<b>&#8377;'.$c['price'].'</b>' : '<span class="free">Free</span>'; ?></div>
                    <div class="cc-prog-bar"><div class="cc-prog-fill" style="width:<?php echo (int)$c['progress']; ?>%;"></div></div>
                    <div class="cc-prog-txt">Progress: <?php echo (int)$c['progress']; ?>%</div>
                    <div class="cc-actions">
                        <a href="course_quizzes.php?course_id=<?php echo (int)$c['id']; ?>" class="cc-btn ghost"><i class="fas fa-question-circle"></i> Quizzes</a>
                        <a href="course_lessons.php?course_id=<?php echo (int)$c['id']; ?>" class="cc-btn prim"><i class="fas fa-play"></i> Continue</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-box"><i class="fas fa-book"></i><p>No enrolled courses yet. Contact your admin to get assigned!</p></div>
        <?php endif; ?>
    </div>

    <!-- Available Courses -->
    <?php if (!empty($available)): ?>
    <div class="sec-card">
        <div class="sec-hdr">
            <h3><i class="fas fa-store" style="color:#7c3aed;"></i> Available Courses</h3>
        </div>
        <div class="course-grid-new">
            <?php foreach ($available as $c): ?>
            <div class="cc-card">
                <div class="cc-thumb" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#16a34a;">
                    <?php if (!empty($c['image'])): ?><img src="<?php echo htmlspecialchars($c['image']); ?>" alt="course"/><?php else: ?><i class="fas fa-lock"></i><?php endif; ?>
                </div>
                <div class="cc-body">
                    <div class="cc-name"><?php echo htmlspecialchars($c['title']); ?></div>
                    <div class="cc-price"><?php echo $c['price']>0 ? '&#8377;'.$c['price'] : 'Free'; ?></div>
                    <div class="cc-actions" style="margin-top:auto;padding-top:8px;">
                        <a href="payment.php?course_id=<?php echo (int)$c['id']; ?>" class="cc-btn prim" style="width:100%;justify-content:center;"><i class="fas fa-credit-card"></i> Pay &amp; Enroll</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /dash-main -->

<!-- ══════════════════════════════
     RIGHT PANEL
══════════════════════════════ -->
<div class="dash-right">

    <!-- Profile + Achievements + Daily Goal -->
    <div class="rp-card">

        <!-- Avatar + name -->
        <div class="rp-top">
            <div class="rp-avatar">
                <?php if (!empty($user['profile_pic'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="avatar"/>
                <?php else: ?>
                    <?php echo strtoupper(mb_substr($user['name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="rp-name"><?php echo htmlspecialchars($user['name']); ?></div>
            <div class="rp-email"><?php echo htmlspecialchars($user['email']); ?></div>
        </div>

        <!-- Live Sessions -->
        <div class="live-pill">
            <div class="live-pill-l">
                <span class="live-dot"></span>
                Live Sessions
                <?php if ($any_live): ?>
                <span style="background:#fee2e2;color:#dc2626;font-size:.63rem;padding:2px 6px;border-radius:999px;font-weight:700;">&#9679; Live</span>
                <?php endif; ?>
            </div>
            <a href="live_classes.php" class="live-pill-r">View more &#8250;</a>
        </div>

        <!-- Achievements -->
        <div class="ach-lbl">
            <span>Achievements</span>
            <span style="font-weight:400;">Best: <?php echo $streak_days; ?> &nbsp; Goal: 7</span>
        </div>
        <div class="ach-grid">
            <div class="ach-item">
                <span class="ach-emoji">🔥</span>
                <span class="ach-val"><?php echo $streak_days; ?></span>
                <span class="ach-sub">Streak</span>
            </div>
            <div class="ach-item">
                <span class="ach-emoji">🔔</span>
                <span class="ach-val"><?php echo number_format($points); ?></span>
                <span class="ach-sub">Points</span>
            </div>
            <div class="ach-item">
                <span class="ach-emoji">🪙</span>
                <span class="ach-val"><?php echo number_format($coins); ?></span>
                <span class="ach-sub">Coins</span>
            </div>
        </div>

        <div class="rp-divider"></div>

        <!-- Daily Goal -->
        <div class="goal-hdr">
            <span class="goal-hdr-lbl">Daily Goal</span>
            <span class="goal-coin-val">🪙 <?php echo $goal_target; ?></span>
        </div>

        <div class="goal-ring-wrap">
            <div class="goal-ring">
                <svg viewBox="0 0 90 90" width="90" height="90">
                    <defs>
                        <linearGradient id="goalGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%"   stop-color="#7c3aed"/>
                            <stop offset="100%" stop-color="#a78bfa"/>
                        </linearGradient>
                    </defs>
                    <circle class="ring-bg" cx="45" cy="45" r="<?php echo $ring_r; ?>"/>
                    <circle class="ring-fg" cx="45" cy="45" r="<?php echo $ring_r; ?>"
                        stroke-dasharray="<?php echo round($ring_circum, 2); ?>"
                        stroke-dashoffset="<?php echo round($ring_offset, 2); ?>"/>
                </svg>
                <div class="ring-inner">
                    <span class="ring-pct"><?php echo $goal_pct; ?>/100</span>
                    <span class="ring-sub">coins</span>
                </div>
            </div>
        </div>

        <?php if ($goal_pct >= 100): ?>
        <div class="goal-achieved"><i class="fas fa-check-circle"></i> Yayy! Daily Goal Achieved!!</div>
        <?php else: ?>
        <div class="goal-progress-txt"><?php echo $goal_earned; ?> / <?php echo $goal_target; ?> coins earned today</div>
        <?php endif; ?>

    </div><!-- /rp-card -->

    <!-- Quick Links -->
    <div class="rp-card">
        <div class="ql-lbl">Quick Links</div>
        <?php
        $qlinks = array(
            array('href'=>'assignments.php',       'icon'=>'fa-file-alt',      'label'=>'Assignments',        'clr'=>'#0ea5e9'),
            array('href'=>'coding_challenges.php', 'icon'=>'fa-code',          'label'=>'Coding Challenges',  'clr'=>'#16a34a'),
            array('href'=>'project_submission.php','icon'=>'fa-paper-plane',   'label'=>'Project Submission', 'clr'=>'#d97706'),
            array('href'=>'live_classes.php',      'icon'=>'fa-video',         'label'=>'Live Classes',        'clr'=>'#0369a1'),
            array('href'=>'certificates.php',      'icon'=>'fa-certificate',   'label'=>'My Certificates',    'clr'=>'#dc2626'),
            // OFFER LETTER QUICK LINK COMMENTED OUT — Letters are created manually by staff; auto-generated IDs cause mismatch.
            // array('href'=>'offer_letter.php',      'icon'=>'fa-file-contract', 'label'=>'Offer Letter',       'clr'=>'#4f46e5'),
            array('href'=>'profile.php',           'icon'=>'fa-user-circle',   'label'=>'My Profile',         'clr'=>'#64748b'),
        );
        foreach ($qlinks as $ql):
        ?>
        <a href="<?php echo $ql['href']; ?>" class="ql-item">
            <i class="fas <?php echo $ql['icon']; ?>" style="color:<?php echo $ql['clr']; ?>;width:16px;text-align:center;"></i>
            <?php echo $ql['label']; ?>
            <i class="fas fa-chevron-right ql-chevron"></i>
        </a>
        <?php endforeach; ?>
    </div>

</div><!-- /dash-right -->

</div><!-- /dash-layout -->
</div><!-- /content-wrapper -->

<script>
function selectDay(el, label) {
    var days = document.querySelectorAll('.cal-day');
    for (var i = 0; i < days.length; i++) { days[i].classList.remove('today'); }
    el.classList.add('today');
    document.getElementById('calLabel').textContent = label;
}
</script>

</div><!-- /main -->
</div><!-- /dashboard -->
<?php include("../includes/footer.php"); ?>