<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("student");

$student_id = $_SESSION['user_id'];

// Ensure table exists
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

// Fetch all live classes for courses this student is enrolled in (approved)
$stmt = $conn->prepare("
    SELECT lc.*, c.title AS course_title, u.name AS trainer_name
    FROM live_classes lc
    JOIN courses     c  ON lc.course_id  = c.id
    JOIN users       u  ON lc.trainer_id = u.id
    JOIN enrollments e  ON e.course_id   = lc.course_id
                       AND e.user_id     = ?
                       AND e.status      = 'approved'
    ORDER BY lc.scheduled_at ASC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$all_classes = $stmt->get_result();

// Split into upcoming/live and past
$upcoming = [];
$past     = [];
$now_ts   = time();

while ($row = $all_classes->fetch_assoc()) {
    $sched_ts = strtotime($row['scheduled_at']);
    $end_ts   = $sched_ts + ($row['duration'] * 60);
    if ($now_ts <= $end_ts) {
        $row['_st']       = ($now_ts >= $sched_ts) ? 'live' : 'upcoming';
        $row['_sched_ts'] = $sched_ts;
        $row['_end_ts']   = $end_ts;
        $upcoming[]       = $row;
    } else {
        $row['_st']       = 'past';
        $row['_sched_ts'] = $sched_ts;
        $past[]           = $row;
    }
}

$page_title = "Live Classes";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Classes — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        /* Hero */
        .slc-hero {
            background: linear-gradient(135deg, #0EA5E9, #38BDF8);
            border-radius: var(--radius-xl);
            padding: 26px 30px;
            color: #fff;
            margin-bottom: 26px;
            position: relative;
            overflow: hidden;
        }
        .slc-hero::before { content:'';position:absolute;top:-50px;right:-50px;width:200px;height:200px;background:rgba(255,255,255,0.07);border-radius:50%; }
        .slc-hero h2 { color:#fff;font-size:1.35rem;margin-bottom:4px; }
        .slc-hero p  { color:rgba(255,255,255,0.82);font-size:0.875rem; }

        /* Stats */
        .slc-stats { display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:26px; }
        .slc-stat { background:var(--card);border-radius:var(--radius-lg);padding:18px 20px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light);display:flex;align-items:center;gap:14px; }
        .slc-stat-icon { width:44px;height:44px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0; }
        .slc-stat-icon.sky    { background:#E0F2FE;color:#0369A1; }
        .slc-stat-icon.muted  { background:var(--bg-alt);color:var(--text-muted); }
        .slc-stat-label { font-size:0.73rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px; }
        .slc-stat-value { font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1; }

        /* Tabs */
        .tab-bar { display:flex;gap:8px;border-bottom:2px solid var(--border-light);margin-bottom:20px; }
        .tab-btn { padding:10px 20px;font-size:0.875rem;font-weight:600;color:var(--text-muted);background:none;border:none;border-bottom:3px solid transparent;cursor:pointer;margin-bottom:-2px;transition:all .2s;display:flex;align-items:center;gap:7px; }
        .tab-btn.active { color:#0EA5E9;border-bottom-color:#0EA5E9; }
        .tab-btn:hover:not(.active) { color:var(--text-primary); }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }

        /* Cards */
        .slc-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px; }
        .slc-card {
            background:var(--card);border-radius:var(--radius-xl);
            border:1px solid var(--border-light);box-shadow:var(--shadow-sm);
            overflow:hidden;transition:box-shadow .2s,transform .2s;
        }
        .slc-card:hover { box-shadow:var(--shadow-md);transform:translateY(-2px); }
        .slc-card.faded { opacity:.7; }

        .slc-card-head { padding:18px 18px 14px; }
        .slc-pill { font-size:0.68rem;font-weight:700;padding:3px 10px;border-radius:var(--radius-full);display:inline-flex;align-items:center;gap:5px;margin-bottom:10px; }
        .slc-pill.upcoming { background:#E0F2FE;color:#0369A1; }
        .slc-pill.live     { background:#FEE2E2;color:#DC2626; }
        .slc-pill.past     { background:#F1F5F9;color:var(--text-secondary); }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.4} }
        .live-dot { width:6px;height:6px;background:#EF4444;border-radius:50%;display:inline-block;animation:blink 1.2s infinite; }

        .slc-card-title  { font-size:0.97rem;font-weight:700;color:var(--text-primary);margin-bottom:4px;line-height:1.35; }
        .slc-card-course { font-size:0.79rem;color:var(--text-muted);display:flex;align-items:center;gap:5px; }

        .countdown-box {
            margin-top:8px;
            background:var(--bg-alt);border-radius:var(--radius-sm);
            padding:5px 11px;font-size:0.78rem;font-weight:700;
            color:#0369A1;display:inline-block;
        }

        .slc-card-meta {
            display:grid;grid-template-columns:1fr 1fr;gap:6px;
            padding:11px 18px;
            border-top:1px solid var(--border-light);
            border-bottom:1px solid var(--border-light);
        }
        .slc-meta { display:flex;align-items:center;gap:6px;font-size:0.8rem;color:var(--text-secondary); }
        .slc-meta i { color:var(--text-muted);font-size:0.74rem;width:13px;text-align:center; }

        .slc-card-desc { padding:10px 18px;font-size:0.81rem;color:var(--text-secondary);line-height:1.55; }

        .slc-card-foot { padding:12px 18px 14px; }

        .btn-sky {
            background:linear-gradient(135deg,#0EA5E9,#38BDF8);
            color:#fff!important;border-color:transparent;
            box-shadow:0 2px 8px rgba(14,165,233,.3);
            width:100%;justify-content:center;
        }
        .btn-sky:hover { background:linear-gradient(135deg,#0284C7,#0EA5E9);transform:translateY(-1px); }
        .btn-sky:disabled, .btn-sky.disabled {
            background:var(--bg-alt)!important;color:var(--text-muted)!important;
            box-shadow:none!important;transform:none!important;cursor:not-allowed;
            border:1.5px solid var(--border)!important;
        }
        .empty-slc { text-align:center;padding:56px 20px; }
        .empty-slc i { font-size:3rem;color:var(--text-muted);opacity:.35;display:block;margin-bottom:14px; }
        .empty-slc h3 { font-size:1rem;font-weight:700;color:var(--text-secondary);margin-bottom:6px; }
        .empty-slc p  { font-size:0.87rem;color:var(--text-muted); }

        /* ── Mobile responsive fixes ── */
        @media(max-width:768px){
            /* Hero: reduce padding on tablet */
            .slc-hero { padding:20px 20px; }
            .slc-hero h2 { font-size:1.15rem; }

            /* Cards: minmax(300px) forces 2 cols on tablets — go 1 col */
            .slc-grid { grid-template-columns:1fr; }

            /* Tabs: smaller text so all tabs fit on one line */
            .tab-btn { padding:8px 14px; font-size:.8rem; }
        }
        @media(max-width:480px){
            /* Stat values: scale down on phones */
            .slc-stat-value { font-size:1.2rem; }
            .slc-stat { padding:14px 16px; }

            /* Hero: minimal padding on phones */
            .slc-hero { padding:16px; }

            /* Tab bar: wrap tabs to 2 rows on very small screens */
            .tab-bar { flex-wrap:wrap; border-bottom:none; gap:4px; }
            .tab-btn {
                border:1.5px solid var(--border-light);
                border-radius:8px;
                border-bottom:1.5px solid var(--border-light) !important;
                margin-bottom:0;
            }
            .tab-btn.active { background:#0EA5E9; color:#fff; border-color:#0EA5E9; }

            /* Join button: touch-friendly height */
            .btn-sky { min-height:44px; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- Hero -->
            <div class="slc-hero">
                <h2><i class="fas fa-video"></i> &nbsp;Live Classes</h2>
                <p>Join your upcoming live sessions and interact with your trainer in real time.</p>
            </div>

            <!-- Stats -->
            <div class="slc-stats">
                <div class="slc-stat">
                    <div class="slc-stat-icon sky"><i class="fas fa-calendar-alt"></i></div>
                    <div><div class="slc-stat-label">Upcoming / Live</div><div class="slc-stat-value"><?php echo count($upcoming); ?></div></div>
                </div>
                <div class="slc-stat">
                    <div class="slc-stat-icon muted"><i class="fas fa-history"></i></div>
                    <div><div class="slc-stat-label">Past Classes</div><div class="slc-stat-value"><?php echo count($past); ?></div></div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tab-bar">
                <button class="tab-btn active" onclick="switchTab('upcoming', this)">
                    <i class="fas fa-calendar-check"></i> Upcoming
                    <?php if (count($upcoming)): ?><span class="badge primary"><?php echo count($upcoming); ?></span><?php endif; ?>
                </button>
                <button class="tab-btn" onclick="switchTab('past', this)">
                    <i class="fas fa-history"></i> Past Classes
                </button>
            </div>

            <!-- Upcoming / Live -->
            <div id="tab-upcoming" class="tab-panel active">
                <?php if (count($upcoming) > 0): ?>
                <div class="slc-grid">
                    <?php foreach ($upcoming as $row):
                        $is_live = ($row['_st'] === 'live');
                    ?>
                    <div class="slc-card">
                        <div class="slc-card-head">
                            <?php if ($is_live): ?>
                            <span class="slc-pill live"><span class="live-dot"></span> Live Now</span>
                            <?php else: ?>
                            <span class="slc-pill upcoming"><i class="fas fa-clock"></i> Upcoming</span>
                            <?php endif; ?>
                            <div class="slc-card-title"><?php echo htmlspecialchars($row['title']); ?></div>
                            <div class="slc-card-course"><i class="fas fa-book-open"></i><?php echo htmlspecialchars($row['course_title']); ?></div>
                            <?php if (!$is_live): ?>
                            <div class="countdown-box" id="cd-<?php echo $row['id']; ?>" data-ts="<?php echo $row['_sched_ts']; ?>">
                                Calculating…
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="slc-card-meta">
                            <div class="slc-meta"><i class="fas fa-calendar-day"></i><?php echo date('d M Y', $row['_sched_ts']); ?></div>
                            <div class="slc-meta"><i class="fas fa-clock"></i><?php echo date('h:i A', $row['_sched_ts']); ?></div>
                            <div class="slc-meta"><i class="fas fa-hourglass-half"></i><?php echo $row['duration']; ?> min</div>
                            <div class="slc-meta"><i class="fas fa-chalkboard-teacher"></i><?php echo htmlspecialchars($row['trainer_name']); ?></div>
                        </div>

                        <?php if (!empty($row['description'])): ?>
                        <div class="slc-card-desc">
                            <?php echo htmlspecialchars(mb_substr($row['description'], 0, 110)) . (mb_strlen($row['description']) > 110 ? '…' : ''); ?>
                        </div>
                        <?php endif; ?>

                        <div class="slc-card-foot">
                            <?php if ($is_live): ?>
                            <a href="../live_class.php?meeting_id=<?php echo urlencode($row['meeting_id']); ?>&class_id=<?php echo $row['id']; ?>"
                               class="btn btn-sky" target="_blank">
                                <i class="fas fa-video"></i> Join Live Class
                            </a>
                            <?php else: ?>
                            <button class="btn btn-sky disabled"
                                id="join-<?php echo $row['id']; ?>"
                                data-ts="<?php echo $row['_sched_ts']; ?>"
                                data-mid="<?php echo urlencode($row['meeting_id']); ?>"
                                data-cid="<?php echo $row['id']; ?>"
                                disabled>
                                <i class="fas fa-lock"></i> Opens at <?php echo date('h:i A', $row['_sched_ts']); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-slc">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Upcoming Live Classes</h3>
                    <p>Your trainer hasn't scheduled a session yet. Check back soon!</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Past -->
            <div id="tab-past" class="tab-panel">
                <?php if (count($past) > 0): ?>
                <div class="slc-grid">
                    <?php foreach ($past as $row): ?>
                    <div class="slc-card faded">
                        <div class="slc-card-head">
                            <span class="slc-pill past"><i class="fas fa-check"></i> Ended</span>
                            <div class="slc-card-title"><?php echo htmlspecialchars($row['title']); ?></div>
                            <div class="slc-card-course"><i class="fas fa-book-open"></i><?php echo htmlspecialchars($row['course_title']); ?></div>
                        </div>
                        <div class="slc-card-meta">
                            <div class="slc-meta"><i class="fas fa-calendar-day"></i><?php echo date('d M Y', $row['_sched_ts']); ?></div>
                            <div class="slc-meta"><i class="fas fa-clock"></i><?php echo date('h:i A', $row['_sched_ts']); ?></div>
                            <div class="slc-meta"><i class="fas fa-hourglass-half"></i><?php echo $row['duration']; ?> min</div>
                            <div class="slc-meta"><i class="fas fa-chalkboard-teacher"></i><?php echo htmlspecialchars($row['trainer_name']); ?></div>
                        </div>
                        <div class="slc-card-foot">
                            <span style="font-size:0.82rem;color:var(--text-muted)"><i class="fas fa-times-circle"></i> This class has ended.</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-slc">
                    <i class="fas fa-history"></i>
                    <h3>No Past Classes Yet</h3>
                    <p>Completed sessions will appear here.</p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function switchTab(name, el) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}

// Countdowns + auto-enable join buttons (5 min grace before class)
function tick() {
    var now = Math.floor(Date.now() / 1000);

    document.querySelectorAll('[id^="cd-"]').forEach(function(el) {
        var ts = parseInt(el.dataset.ts, 10);
        var diff = ts - now;
        if (diff <= 0) { el.style.display = 'none'; return; }
        var d = Math.floor(diff / 86400);
        var h = Math.floor((diff % 86400) / 3600);
        var m = Math.floor((diff % 3600) / 60);
        var s = diff % 60;
        var parts = [];
        if (d > 0) parts.push(d + 'd');
        if (h > 0) parts.push(h + 'h');
        if (m > 0) parts.push(m + 'm');
        if (d === 0) parts.push((s < 10 ? '0' : '') + s + 's');
        el.textContent = 'Starts in ' + parts.join(' ');
    });

    // Enable join buttons when within 5 minutes of start
    document.querySelectorAll('[id^="join-"]').forEach(function(btn) {
        var ts  = parseInt(btn.dataset.ts,  10);
        var mid = btn.dataset.mid;
        var cid = btn.dataset.cid;
        if (now >= ts - 300) { // 5 min early access
            btn.disabled = false;
            btn.classList.remove('disabled');
            btn.innerHTML = '<i class="fas fa-video"></i> Join Live Class';
            btn.onclick = function() {
                window.open('../live_class.php?meeting_id=' + mid + '&class_id=' + cid, '_blank');
            };
        }
    });
}

tick();
setInterval(tick, 1000);
</script>

<?php include("../includes/footer.php"); ?>