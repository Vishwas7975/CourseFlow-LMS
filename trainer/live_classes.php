<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("trainer");

$trainer_id = $_SESSION['user_id'];

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

// Success flash
$flash = "";
if (isset($_GET['scheduled'])) $flash = "Live class scheduled successfully!";
if (isset($_GET['updated']))   $flash = "Live class updated successfully!";

// Stats
$now = date('Y-m-d H:i:s');
$total_res    = $conn->prepare("SELECT COUNT(*) as c FROM live_classes WHERE trainer_id=?");
$total_res->bind_param("i", $trainer_id); $total_res->execute();
$total_count  = $total_res->get_result()->fetch_assoc()['c'];

$upcoming_res = $conn->prepare("SELECT COUNT(*) as c FROM live_classes WHERE trainer_id=? AND scheduled_at > ?");
$upcoming_res->bind_param("is", $trainer_id, $now); $upcoming_res->execute();
$upcoming_count = $upcoming_res->get_result()->fetch_assoc()['c'];

// All classes
$stmt = $conn->prepare("
    SELECT lc.*, c.title AS course_title
    FROM live_classes lc
    JOIN courses c ON lc.course_id = c.id
    WHERE lc.trainer_id = ?
    ORDER BY lc.scheduled_at DESC
");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$classes = $stmt->get_result();

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
        /* ── Hero banner ── */
        .lc-hero {
            background: linear-gradient(135deg, #7C3AED, #9333EA);
            border-radius: var(--radius-xl);
            padding: 26px 30px;
            color: #fff;
            margin-bottom: 26px;
            position: relative;
            overflow: hidden;
        }
        .lc-hero::before {
            content: '';
            position: absolute;
            top: -50px; right: -50px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
        }
        .lc-hero::after {
            content: '';
            position: absolute;
            bottom: -30px; right: 100px;
            width: 120px; height: 120px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }
        .lc-hero h2  { color: #fff; font-size: 1.35rem; margin-bottom: 4px; }
        .lc-hero p   { color: rgba(255,255,255,0.80); font-size: 0.875rem; }

        /* ── Stat mini-cards ── */
        .lc-stats { display: grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap: 16px; margin-bottom: 26px; }
        .lc-stat  {
            background: var(--card);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            display: flex; align-items: center; gap: 14px;
        }
        .lc-stat-icon {
            width: 44px; height: 44px;
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem; flex-shrink: 0;
        }
        .lc-stat-icon.purple { background: #F3E8FF; color: #7C3AED; }
        .lc-stat-icon.green  { background: var(--success-light); color: #16A34A; }
        .lc-stat-label { font-size: 0.73rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
        .lc-stat-value { font-size: 1.55rem; font-weight: 800; color: var(--text-primary); line-height: 1; }

        /* ── Class card grid ── */
        .lc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .lc-card {
            background: var(--card);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: box-shadow .2s, transform .2s;
            position: relative;
        }
        .lc-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
        .lc-card-stripe {
            position: absolute; top: 0; left: 0;
            width: 4px; height: 100%;
            border-radius: 4px 0 0 4px;
        }
        .lc-card-stripe.live     { background: linear-gradient(180deg,#EF4444,#F87171); }
        .lc-card-stripe.upcoming { background: linear-gradient(180deg,#7C3AED,#A855F7); }
        .lc-card-stripe.past     { background: linear-gradient(180deg,#94A3B8,#CBD5E1); }

        .lc-card-body   { padding: 18px 18px 14px 22px; }
        .lc-card-top    { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
        .lc-card-title  { font-size: 0.97rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; line-height: 1.35; }
        .lc-card-course { font-size: 0.79rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }

        .status-pill {
            font-size: 0.68rem; font-weight: 700;
            padding: 3px 10px; border-radius: var(--radius-full);
            white-space: nowrap; flex-shrink: 0; display: inline-flex; align-items: center; gap: 5px;
        }
        .status-pill.live     { background: #FEE2E2; color: #DC2626; }
        .status-pill.upcoming { background: #F3E8FF; color: #7C3AED; }
        .status-pill.past     { background: #F1F5F9; color: var(--text-secondary); }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.4} }
        .live-dot { width:6px;height:6px;background:#EF4444;border-radius:50%;display:inline-block;animation:blink 1.2s infinite; }

        .lc-card-meta {
            display: grid; grid-template-columns: 1fr 1fr; gap: 6px;
            padding: 12px 18px 12px 22px;
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
        }
        .lc-meta { display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: var(--text-secondary); }
        .lc-meta i { color: var(--text-muted); font-size: 0.74rem; width: 13px; text-align: center; }

        .lc-card-desc {
            padding: 10px 18px 10px 22px;
            font-size: 0.81rem; color: var(--text-secondary); line-height: 1.55;
        }
        .lc-card-foot {
            padding: 12px 18px 14px 22px;
            display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        }
        .meeting-chip {
            display: inline-flex; align-items: center; gap: 5px;
            background: var(--bg-alt); border: 1px solid var(--border);
            border-radius: var(--radius-sm); padding: 4px 10px;
            font-size: 0.73rem; color: var(--text-secondary);
            font-family: monospace; margin-right: auto;
        }
        .btn-purple {
            background: linear-gradient(135deg, #7C3AED, #9333EA);
            color: #fff !important; border-color: transparent;
            box-shadow: 0 2px 8px rgba(124,58,237,0.3);
        }
        .btn-purple:hover {
            background: linear-gradient(135deg, #6D28D9, #7C3AED);
            transform: translateY(-1px);
        }
        .empty-live { text-align: center; padding: 60px 20px; }
        .empty-live i { font-size: 3rem; color: var(--text-muted); opacity: .4; display: block; margin-bottom: 14px; }
        .empty-live h3 { font-size: 1rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px; }
        .empty-live p  { font-size: 0.87rem; color: var(--text-muted); margin-bottom: 20px; }

        /* ── Mobile responsive fixes ── */
        @media(max-width:768px){
            /* Cards: minmax(320px) too wide for phones — force 1 col */
            .lc-grid { grid-template-columns: 1fr; }

            /* Hero: reduce padding on tablet */
            .lc-hero { padding: 20px; }
            .lc-hero h2 { font-size: 1.15rem; }

            /* Stat values: scale down on tablet */
            .lc-stat-value { font-size: 1.25rem; }
        }
        @media(max-width:480px){
            /* Hero: minimal padding on phones */
            .lc-hero { padding: 16px; }

            /* Stat cards: tighten padding */
            .lc-stat { padding: 14px; }
            .lc-stat-value { font-size: 1.1rem; }

            /* Card foot buttons: wrap cleanly */
            .lc-card-foot { gap: 6px; }
            .btn-purple, .lc-card-foot .btn { min-height: 44px; }

            /* Schedule button in header: full width */
            .page-header .btn { min-height: 44px; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <?php if ($flash): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($flash); ?></div>
            <?php endif; ?>

            <!-- Hero -->
            <div class="lc-hero">
                <h2><i class="fas fa-video"></i> &nbsp;Live Classes</h2>
                <p>Schedule and manage live Jitsi video sessions for your students.</p>
            </div>

            <!-- Stats -->
            <div class="lc-stats">
                <div class="lc-stat">
                    <div class="lc-stat-icon purple"><i class="fas fa-calendar-check"></i></div>
                    <div><div class="lc-stat-label">Total Classes</div><div class="lc-stat-value"><?php echo $total_count; ?></div></div>
                </div>
                <div class="lc-stat">
                    <div class="lc-stat-icon green"><i class="fas fa-clock"></i></div>
                    <div><div class="lc-stat-label">Upcoming</div><div class="lc-stat-value"><?php echo $upcoming_count; ?></div></div>
                </div>
            </div>

            <!-- Header row -->
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
                <div>
                    <h2 style="font-size:1rem;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:8px;margin:0">
                        <i class="fas fa-list" style="color:#7C3AED;font-size:.9rem"></i> All Scheduled Classes
                    </h2>
                    <p style="font-size:0.82rem;color:var(--text-muted);margin-top:3px">View, edit or delete your sessions.</p>
                </div>
                <div style="display:flex;gap:10px">
                    <a href="schedule_live_class.php" class="btn btn-purple"><i class="fas fa-plus"></i> Schedule New Class</a>
                    <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Dashboard</a>
                </div>
            </div>

            <!-- Class Cards -->
            <?php if ($classes->num_rows > 0): ?>
            <div class="lc-grid">
                <?php while ($row = $classes->fetch_assoc()):
                    $sched_ts = strtotime($row['scheduled_at']);
                    $end_ts   = $sched_ts + ($row['duration'] * 60);
                    $now_ts   = time();
                    if ($now_ts < $sched_ts)                            $st = 'upcoming';
                    elseif ($now_ts >= $sched_ts && $now_ts <= $end_ts) $st = 'live';
                    else                                                 $st = 'past';
                    $labels = ['upcoming' => 'Upcoming', 'live' => 'Live Now', 'past' => 'Ended'];
                ?>
                <div class="lc-card">
                    <div class="lc-card-stripe <?php echo $st; ?>"></div>

                    <div class="lc-card-body">
                        <div class="lc-card-top">
                            <div>
                                <div class="lc-card-title"><?php echo htmlspecialchars($row['title']); ?></div>
                                <div class="lc-card-course">
                                    <i class="fas fa-book-open"></i>
                                    <?php echo htmlspecialchars($row['course_title']); ?>
                                </div>
                            </div>
                            <span class="status-pill <?php echo $st; ?>">
                                <?php if ($st === 'live'): ?><span class="live-dot"></span><?php endif; ?>
                                <?php echo $labels[$st]; ?>
                            </span>
                        </div>
                    </div>

                    <div class="lc-card-meta">
                        <div class="lc-meta"><i class="fas fa-calendar-day"></i><?php echo date('d M Y', $sched_ts); ?></div>
                        <div class="lc-meta"><i class="fas fa-clock"></i><?php echo date('h:i A', $sched_ts); ?></div>
                        <div class="lc-meta"><i class="fas fa-hourglass-half"></i><?php echo $row['duration']; ?> min</div>
                        <div class="lc-meta"><i class="fas fa-door-open"></i><?php echo date('h:i A', $end_ts); ?></div>
                    </div>

                    <?php if (!empty($row['description'])): ?>
                    <div class="lc-card-desc">
                        <?php echo htmlspecialchars(mb_substr($row['description'], 0, 110)) . (mb_strlen($row['description']) > 110 ? '…' : ''); ?>
                    </div>
                    <?php endif; ?>

                    <div class="lc-card-foot">
                        <span class="meeting-chip"><i class="fas fa-link"></i><?php echo htmlspecialchars($row['meeting_id']); ?></span>

                        <?php if ($st === 'live' || $st === 'upcoming'): ?>
                        <a href="../live_class.php?meeting_id=<?php echo urlencode($row['meeting_id']); ?>&class_id=<?php echo $row['id']; ?>"
                           class="btn btn-sm btn-purple" target="_blank">
                            <i class="fas fa-video"></i> <?php echo $st === 'live' ? 'Join Now' : 'Open Room'; ?>
                        </a>
                        <?php endif; ?>
                        <a href="edit_live_class.php?id=<?php echo $row['id']; ?>" class="btn-edit btn-sm"><i class="fas fa-pen"></i> Edit</a>
                        <a href="delete_live_class.php?id=<?php echo $row['id']; ?>"
                           onclick="return confirm('Delete this live class permanently?')"
                           class="btn-delete btn-sm"><i class="fas fa-trash"></i></a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <?php else: ?>
            <div class="empty-live">
                <i class="fas fa-video-slash"></i>
                <h3>No Live Classes Yet</h3>
                <p>Schedule your first session and students will see it automatically.</p>
                <a href="schedule_live_class.php" class="btn btn-purple">
                    <i class="fas fa-plus"></i> Schedule First Class
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>