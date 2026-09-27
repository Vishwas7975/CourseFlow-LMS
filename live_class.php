<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("config/db.php");
include("includes/auth_check.php");

requireLogin($conn);

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['name'];

$meeting_id = isset($_GET['meeting_id']) ? trim($_GET['meeting_id']) : '';
$class_id   = isset($_GET['class_id'])   ? (int)$_GET['class_id']  : 0;

// Reject missing params immediately
if (!$meeting_id || !$class_id) {
    header("Location: login.php"); exit();
}

// Fetch live class record
$stmt = $conn->prepare("
    SELECT lc.*, c.title AS course_title, u.name AS trainer_name
    FROM live_classes lc
    JOIN courses c ON lc.course_id  = c.id
    JOIN users   u ON lc.trainer_id = u.id
    WHERE lc.id = ? AND lc.meeting_id = ?
");
$stmt->bind_param("is", $class_id, $meeting_id);
$stmt->execute();
$cls = $stmt->get_result()->fetch_assoc();

if (!$cls) { header("Location: login.php"); exit(); }

// ── Access control ────────────────────────────────────────────
$access_ok  = false;
$deny_reason = "";

if ($user_role === 'trainer') {
    $access_ok = ($cls['trainer_id'] == $user_id);
    if (!$access_ok) $deny_reason = "You are not the trainer of this class.";

} elseif ($user_role === 'student') {
    $e = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'approved'");
    $e->bind_param("ii", $user_id, $cls['course_id']);
    $e->execute();
    $access_ok = ($e->get_result()->num_rows > 0);
    if (!$access_ok) $deny_reason = "You are not enrolled in the course for this class.";

} else {
    $deny_reason = "Your account role cannot access live classes.";
}

// ── Class timing ──────────────────────────────────────────────
$sched_ts  = strtotime($cls['scheduled_at']);
$end_ts    = $sched_ts + ($cls['duration'] * 60);
$now_ts    = time();

// Allow entry 5 min early, leave 15 min grace after end
if ($now_ts < $sched_ts - 300)   $timing = 'early';
elseif ($now_ts > $end_ts + 900) $timing = 'ended';
else                              $timing = 'active';

// Sanitise room name for Jitsi (alphanumeric + hyphens only)
$room_name = preg_replace('/[^a-zA-Z0-9\-]/', '', $cls['meeting_id']);

$back_url  = ($user_role === 'trainer') ? 'trainer/live_classes.php' : 'student/live_classes.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($cls['title']); ?> — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0F172A; color: #E2E8F0; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

        /* Top Bar */
        .lc-topbar {
            height: 54px;
            background: #1E293B;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            padding: 0 18px;
            gap: 14px;
            flex-shrink: 0;
            z-index: 10;
        }
        .lc-logo { font-weight: 800; font-size: 0.92rem; color: #F8FAFC; display: flex; align-items: center; gap: 7px; white-space: nowrap; }
        .lc-logo i { color: #38BDF8; }
        .lc-divider { width: 1px; height: 22px; background: rgba(255,255,255,0.12); flex-shrink: 0; }
        .lc-info { flex: 1; min-width: 0; }
        .lc-title  { font-size: 0.88rem; font-weight: 700; color: #F1F5F9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lc-course { font-size: 0.73rem; color: #94A3B8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lc-status { font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 99px; white-space: nowrap; flex-shrink: 0; display: flex; align-items: center; gap: 5px; }
        .lc-status.live     { background: rgba(239,68,68,.18); color: #FCA5A5; border: 1px solid rgba(239,68,68,.3); }
        .lc-status.upcoming { background: rgba(14,165,233,.16); color: #7DD3FC; border: 1px solid rgba(14,165,233,.3); }
        .lc-status.ended    { background: rgba(148,163,184,.12); color: #94A3B8; border: 1px solid rgba(148,163,184,.2); }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.4} }
        .live-dot { width: 6px; height: 6px; background: #EF4444; border-radius: 50%; animation: blink 1.2s infinite; }
        .lc-back {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 13px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 7px;
            color: #CBD5E1; font-size: 0.78rem; font-weight: 600;
            text-decoration: none;
            transition: background .15s;
            flex-shrink: 0;
        }
        .lc-back:hover { background: rgba(255,255,255,0.13); color: #fff; }

        /* Active class page */
        .active-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 30px 20px; overflow-y: auto; }

        /* Bottom bar */
        .lc-infobar {
            height: 36px;
            background: #1E293B;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; gap: 20px;
            padding: 0 18px;
            flex-shrink: 0;
        }
        .lc-chip { display: flex; align-items: center; gap: 6px; font-size: 0.73rem; color: #64748B; }
        .lc-chip i { font-size: 0.68rem; }

        /* Message pages */
        .msg-page { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .msg-card { background: #1E293B; border-radius: 20px; padding: 40px; max-width: 480px; width: 100%; text-align: center; border: 1px solid rgba(255,255,255,0.08); }
        .msg-icon { width: 72px; height: 72px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 20px; }
        .msg-icon.red    { background: rgba(239,68,68,.15);  color: #F87171; }
        .msg-icon.sky    { background: rgba(14,165,233,.15); color: #7DD3FC; }
        .msg-icon.muted  { background: rgba(148,163,184,.1); color: #94A3B8; }
        .msg-card h2  { font-size: 1.2rem; font-weight: 800; color: #F1F5F9; margin-bottom: 10px; }
        .msg-card p   { font-size: 0.875rem; color: #94A3B8; line-height: 1.65; margin-bottom: 20px; }
        .info-box { background: rgba(255,255,255,0.04); border-radius: 10px; padding: 14px 18px; font-size: 0.83rem; color: #CBD5E1; margin-bottom: 18px; text-align: left; display: flex; flex-direction: column; gap: 8px; }
        .info-row { display: flex; align-items: center; gap: 8px; }
        .info-row i { color: #38BDF8; width: 16px; text-align: center; }
        .countdown-big { font-size: 1.5rem; font-weight: 800; color: #38BDF8; margin: 10px 0 16px; letter-spacing: -1px; }
        .btn-back-msg { display: inline-flex; align-items: center; gap: 7px; padding: 10px 22px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14); border-radius: 9px; color: #CBD5E1; font-size: 0.875rem; font-weight: 600; text-decoration: none; transition: background .15s; }
        .btn-back-msg:hover { background: rgba(255,255,255,0.15); color: #fff; }

        @media (max-width: 600px) {
            /* Hide course badge and info bar on small screens */
            .lc-course, .lc-infobar { display: none; }

            /* Top nav: reduce padding so title doesn't overflow */
            .lc-topbar { padding: 0 12px; }
            .lc-title { font-size: 0.85rem; }

            /* Main card: tighter padding on phones */
            .lc-card { padding: 24px 16px; border-radius: 16px; }

            /* Info grid (Trainer/Duration/Time): 1-col on phones */
            div[style*="grid-template-columns:1fr 1fr"],
            div[style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            /* Join button: full width touch-friendly */
            #join-btn { width: 100%; justify-content: center; min-height: 52px; }

            /* Countdown: slightly smaller font */
            .countdown-big { font-size: 1.25rem; }

            /* Back button: full width */
            .btn-back-msg { width: 100%; justify-content: center; }
        }
        @media(max-width:360px){
            .lc-card { padding: 20px 12px; }
        }
    </style>
</head>
<body>

<!-- ── Top Bar ─────────────────────────────────────── -->
<div class="lc-topbar">
    <div class="lc-logo"><i class="fas fa-graduation-cap"></i> CourseFlow LMS</div>
    <div class="lc-divider"></div>
    <div class="lc-info">
        <div class="lc-title"><?php echo htmlspecialchars($cls['title']); ?></div>
        <div class="lc-course"><i class="fas fa-book-open" style="font-size:.65rem"></i> <?php echo htmlspecialchars($cls['course_title']); ?></div>
    </div>

    <?php if ($access_ok): ?>
    <div class="lc-status <?php echo $timing === 'active' ? 'live' : ($timing === 'early' ? 'upcoming' : 'ended'); ?>">
        <?php if ($timing === 'active'): ?><span class="live-dot"></span> Live
        <?php elseif ($timing === 'early'): ?><i class="fas fa-clock"></i> Starting Soon
        <?php else: ?><i class="fas fa-check"></i> Ended<?php endif; ?>
    </div>
    <?php endif; ?>

    <a href="<?php echo $back_url; ?>" class="lc-back"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<?php if (!$access_ok): ?>
<!-- ── ACCESS DENIED ──────────────────────────────── -->
<div class="msg-page">
    <div class="msg-card">
        <div class="msg-icon red"><i class="fas fa-lock"></i></div>
        <h2>Access Denied</h2>
        <p><?php echo htmlspecialchars($deny_reason); ?></p>
        <a href="<?php echo $back_url; ?>" class="btn-back-msg"><i class="fas fa-arrow-left"></i> Go Back</a>
    </div>
</div>

<?php elseif ($timing === 'early'): ?>
<!-- ── TOO EARLY ───────────────────────────────────── -->
<div class="msg-page">
    <div class="msg-card">
        <div class="msg-icon sky"><i class="fas fa-hourglass-start"></i></div>
        <h2>Class Not Started Yet</h2>
        <p>The room will open 5 minutes before the scheduled time. Please come back soon!</p>
        <div class="info-box">
            <div class="info-row"><i class="fas fa-calendar-day"></i><?php echo date('l, d F Y', $sched_ts); ?></div>
            <div class="info-row"><i class="fas fa-clock"></i><?php echo date('h:i A', $sched_ts); ?> — <?php echo date('h:i A', $end_ts); ?></div>
            <div class="info-row"><i class="fas fa-hourglass-half"></i>Duration: <?php echo $cls['duration']; ?> minutes</div>
        </div>
        <p style="font-size:0.78rem;color:#64748B;margin-bottom:6px">Room opens in:</p>
        <div class="countdown-big" id="main-cd">—</div>
        <a href="<?php echo $back_url; ?>" class="btn-back-msg"><i class="fas fa-arrow-left"></i> Back to Classes</a>
    </div>
</div>
<script>
var schedTs = <?php echo $sched_ts - 300; ?>; // 5 min early
function countTick() {
    var diff = schedTs - Math.floor(Date.now() / 1000);
    if (diff <= 0) { location.reload(); return; }
    var h = Math.floor(diff / 3600);
    var m = Math.floor((diff % 3600) / 60);
    var s = diff % 60;
    var parts = [];
    if (h > 0) parts.push(h + 'h');
    if (m > 0) parts.push(m + 'm');
    parts.push((s < 10 ? '0' : '') + s + 's');
    document.getElementById('main-cd').textContent = parts.join(' ');
}
countTick(); setInterval(countTick, 1000);
</script>

<?php elseif ($timing === 'ended'): ?>
<!-- ── CLASS ENDED ────────────────────────────────── -->
<div class="msg-page">
    <div class="msg-card">
        <div class="msg-icon muted"><i class="fas fa-check-circle"></i></div>
        <h2>Class Has Ended</h2>
        <p>This session concluded at <strong style="color:#E2E8F0"><?php echo date('h:i A, d M Y', $end_ts); ?></strong>. Check your schedule for upcoming sessions.</p>
        <a href="<?php echo $back_url; ?>" class="btn-back-msg"><i class="fas fa-arrow-left"></i> Back to Classes</a>
    </div>
</div>

<?php else: ?>
<!-- ── ACTIVE CLASS — Launch in new tab (no embedding = no limit) ── -->
<div class="active-wrap">
    <div style="max-width:560px;width:100%;text-align:center">

        <!-- Pulsing live indicator -->
        <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:24px">
            <span style="position:relative;display:inline-flex">
                <span style="width:14px;height:14px;background:#EF4444;border-radius:50%;display:block"></span>
                <span style="position:absolute;top:0;left:0;width:14px;height:14px;background:#EF4444;border-radius:50%;animation:ping 1.4s cubic-bezier(0,0,0.2,1) infinite;opacity:.75"></span>
            </span>
            <span style="font-size:0.8rem;font-weight:700;color:#FCA5A5;letter-spacing:.5px;text-transform:uppercase">Class is Live Now</span>
        </div>

        <!-- Class info card -->
        <div style="background:#1E293B;border-radius:20px;padding:32px;border:1px solid rgba(255,255,255,0.08);margin-bottom:24px;text-align:left">

            <h2 style="font-size:1.3rem;font-weight:800;color:#F1F5F9;margin-bottom:6px;line-height:1.3">
                <?php echo htmlspecialchars($cls['title']); ?>
            </h2>
            <p style="font-size:0.83rem;color:#64748B;margin-bottom:22px;display:flex;align-items:center;gap:6px">
                <i class="fas fa-book-open" style="color:#38BDF8"></i>
                <?php echo htmlspecialchars($cls['course_title']); ?>
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:22px">
                <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px 14px">
                    <div style="font-size:0.68rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Trainer</div>
                    <div style="font-size:0.88rem;font-weight:700;color:#CBD5E1;display:flex;align-items:center;gap:6px">
                        <i class="fas fa-chalkboard-teacher" style="color:#38BDF8;font-size:.75rem"></i>
                        <?php echo htmlspecialchars($cls['trainer_name']); ?>
                    </div>
                </div>
                <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px 14px">
                    <div style="font-size:0.68rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Duration</div>
                    <div style="font-size:0.88rem;font-weight:700;color:#CBD5E1;display:flex;align-items:center;gap:6px">
                        <i class="fas fa-hourglass-half" style="color:#38BDF8;font-size:.75rem"></i>
                        <?php echo $cls['duration']; ?> minutes
                    </div>
                </div>
                <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px 14px">
                    <div style="font-size:0.68rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Started At</div>
                    <div style="font-size:0.88rem;font-weight:700;color:#CBD5E1;display:flex;align-items:center;gap:6px">
                        <i class="fas fa-clock" style="color:#38BDF8;font-size:.75rem"></i>
                        <?php echo date('h:i A', $sched_ts); ?>
                    </div>
                </div>
                <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px 14px">
                    <div style="font-size:0.68rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Ends At</div>
                    <div style="font-size:0.88rem;font-weight:700;color:#CBD5E1;display:flex;align-items:center;gap:6px">
                        <i class="fas fa-flag-checkered" style="color:#38BDF8;font-size:.75rem"></i>
                        <?php echo date('h:i A', $end_ts); ?>
                    </div>
                </div>
            </div>

            <!-- Time remaining bar -->
            <?php
                $elapsed  = max(0, $now_ts - $sched_ts);
                $total    = $cls['duration'] * 60;
                $pct      = min(100, round(($elapsed / $total) * 100));
                $remaining_secs = max(0, $end_ts - $now_ts);
                $rem_m = floor($remaining_secs / 60);
                $rem_s = $remaining_secs % 60;
            ?>
            <div style="margin-bottom:6px;display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:0.72rem;color:#475569;font-weight:600">Session Progress</span>
                <span style="font-size:0.72rem;color:#94A3B8;font-weight:600" id="rem-label">
                    <?php echo $rem_m; ?>m <?php echo $rem_s; ?>s remaining
                </span>
            </div>
            <div style="width:100%;height:6px;background:rgba(255,255,255,0.07);border-radius:99px;overflow:hidden">
                <div id="progress-bar" style="height:100%;width:<?php echo $pct; ?>%;background:linear-gradient(90deg,#38BDF8,#0EA5E9);border-radius:99px;transition:width 1s linear"></div>
            </div>
        </div>

        <!-- Join button -->
        <?php
            $jitsi_url = 'https://meet.jit.si/' . $room_name
                       . '#userInfo.displayName=' . rawurlencode($user_name)
                       . '&config.prejoinPageEnabled=false'
                       . '&config.startWithAudioMuted=false'
                       . '&config.startWithVideoMuted=false';
        ?>
        <a href="<?php echo $jitsi_url; ?>" target="_blank" id="join-btn"
           style="display:inline-flex;align-items:center;justify-content:center;gap:10px;
                  width:100%;padding:16px 24px;
                  background:linear-gradient(135deg,#16A34A,#22C55E);
                  color:#fff;font-size:1rem;font-weight:800;
                  border-radius:14px;text-decoration:none;
                  box-shadow:0 4px 24px rgba(34,197,94,0.4);
                  transition:opacity .2s,transform .2s;
                  margin-bottom:14px">
            <i class="fas fa-video"></i>
            Join Live Class
            <i class="fas fa-external-link-alt" style="font-size:0.75rem;opacity:.8"></i>
        </a>

        <p style="font-size:0.76rem;color:#475569;line-height:1.6">
            <i class="fas fa-info-circle" style="color:#38BDF8"></i>
            The meeting opens in a new tab. Come back here anytime to see class info.
            <br>Room: <code style="color:#38BDF8;font-size:0.78rem"><?php echo htmlspecialchars($room_name); ?></code>
        </p>

    </div>
</div>

<style>
@keyframes ping {
    75%, 100% { transform: scale(2); opacity: 0; }
}
#join-btn:hover { opacity: .9; transform: translateY(-2px); }
</style>

<script>
// Live countdown timer for remaining time
var endTs = <?php echo $end_ts; ?>;
var startTs = <?php echo $sched_ts; ?>;
var totalSecs = <?php echo $total; ?>;

function updateTimer() {
    var now  = Math.floor(Date.now() / 1000);
    var rem  = Math.max(0, endTs - now);
    var elapsed = Math.max(0, now - startTs);
    var pct  = Math.min(100, (elapsed / totalSecs) * 100);

    var m = Math.floor(rem / 60);
    var s = rem % 60;

    var lbl = document.getElementById('rem-label');
    var bar = document.getElementById('progress-bar');

    if (lbl) lbl.textContent = m + 'm ' + (s < 10 ? '0' : '') + s + 's remaining';
    if (bar) bar.style.width = pct.toFixed(1) + '%';

    if (rem <= 0) {
        // Class ended — reload to show ended screen
        location.reload();
    }
}

updateTimer();
setInterval(updateTimer, 1000);
</script>
<?php endif; ?>

</body>
</html>