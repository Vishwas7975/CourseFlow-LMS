<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("trainer");

$trainer_id = $_SESSION['user_id'];
$success    = '';
$error      = '';

// ── Handle Approve / Reject ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['project_id'])) {

    $action     = trim($_POST['action']);
    $project_id = (int)$_POST['project_id'];
    $now        = date('Y-m-d H:i:s');

    // Capture rejection note — only for Rejected, empty string otherwise
    $rejection_note = '';
    if ($action === 'Rejected' && isset($_POST['rejection_note'])) {
        $rejection_note = trim($_POST['rejection_note']);
    }

    if (in_array($action, ['Approved', 'Rejected']) && $project_id > 0) {

        $upd = $conn->prepare(
            "UPDATE project_submissions
             SET status = ?,
                 reviewed_by = ?,
                 reviewed_at = ?,
                 rejection_note = ?
             WHERE id = ?"
        );
        $upd->bind_param("sissi",
            $action,
            $trainer_id,
            $now,
            $rejection_note,
            $project_id
        );

        if ($upd->execute()) {
            $success = "Project has been <strong>" . htmlspecialchars($action) . "</strong> successfully.";

            // Notify the student
            $proj = $conn->prepare("SELECT student_id, project_title FROM project_submissions WHERE id = ?");
            $proj->bind_param("i", $project_id);
            $proj->execute();
            $proj_row = $proj->get_result()->fetch_assoc();

            if ($proj_row) {
                $student_id   = (int)$proj_row['student_id'];
                $proj_title   = $proj_row['project_title'];
                $trainer_name = $_SESSION['name'];
                $n_type       = "project_review";
                $n_title      = "Project " . $action;
                $n_msg        = "Your project \"" . $proj_title . "\" has been "
                                . strtolower($action) . " by " . $trainer_name
                                . ($rejection_note !== '' ? ". Reason: " . $rejection_note : "") . ".";
                $n_link       = "../student/project_submission.php";

                $ns = $conn->prepare(
                    "INSERT INTO notifications
                     (type, for_role, for_user_id, title, message, link, is_read)
                     VALUES (?, 'student', ?, ?, ?, ?, 0)"
                );
                $ns->bind_param("sisss", $n_type, $student_id, $n_title, $n_msg, $n_link);
                $ns->execute();
            }

        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

// ── Filter ───────────────────────────────────────────────────────────────────
$allowed_filters = ['All', 'Pending', 'Approved', 'Rejected'];
$filter = (isset($_GET['status']) && in_array($_GET['status'], $allowed_filters))
          ? $_GET['status'] : 'All';

// ── Fetch — only this trainer's assigned students ─────────────────────────────
$status_cond = ($filter !== 'All')
    ? "AND ps.status = '" . $conn->real_escape_string($filter) . "'"
    : "";

$query = "
    SELECT ps.*, u.name AS student_name, u.email AS student_email,
           u.id AS student_user_id, r.name AS reviewer_name
    FROM   project_submissions ps
    JOIN   users u  ON ps.student_id  = u.id
    LEFT JOIN users r ON ps.reviewed_by = r.id
    WHERE  ps.student_id IN (
        SELECT DISTINCT user_id FROM enrollments
        WHERE  trainer_id = $trainer_id AND user_id IS NOT NULL
    )
    $status_cond
    ORDER BY
        CASE ps.status WHEN 'Pending' THEN 0 WHEN 'Approved' THEN 1 ELSE 2 END,
        ps.submitted_at DESC
";
$projects = $conn->query($query);

// ── Counts ───────────────────────────────────────────────────────────────────
$counts  = ['All' => 0, 'Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
$cnt_res = $conn->query(
    "SELECT status, COUNT(*) AS c FROM project_submissions
     WHERE student_id IN (
         SELECT DISTINCT user_id FROM enrollments WHERE trainer_id = $trainer_id
     )
     GROUP BY status"
);
if ($cnt_res) {
    while ($r = $cnt_res->fetch_assoc()) {
        $counts[$r['status']] = (int)$r['c'];
        $counts['All'] += (int)$r['c'];
    }
}

$page_title = "Student Projects";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Projects – CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        .alert { display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:var(--radius-md);font-size:0.88rem;margin-bottom:20px; }
        .alert-success { background:var(--success-light);border:1px solid #86EFAC;color:#166534; }
        .alert-danger  { background:var(--danger-light); border:1px solid #FCA5A5;color:#991B1B; }
        .alert i { font-size:1rem;margin-top:1px;flex-shrink:0; }

        .filter-tabs { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px; }
        .filter-tab {
            display:inline-flex;align-items:center;gap:7px;padding:8px 16px;
            border-radius:var(--radius-full);font-size:0.82rem;font-weight:600;
            border:1.5px solid var(--border);color:var(--text-secondary);
            background:var(--card);text-decoration:none;transition:all 0.2s;
        }
        .filter-tab:hover { border-color:var(--primary);color:var(--primary); }
        .filter-tab.active { background:var(--primary);color:#fff;border-color:var(--primary);box-shadow:var(--shadow-blue); }
        .filter-tab .count { background:rgba(255,255,255,0.25);border-radius:var(--radius-full);padding:1px 7px;font-size:0.72rem;font-weight:700; }
        .filter-tab:not(.active) .count { background:var(--bg-alt);color:var(--text-muted); }
        .filter-tab.tab-pending  .count { background:#FEF3C7;color:#92400E; }
        .filter-tab.tab-approved .count { background:var(--success-light);color:#166534; }
        .filter-tab.tab-rejected .count { background:var(--danger-light);color:#991B1B; }
        .filter-tab.active .count { background:rgba(255,255,255,0.25);color:#fff; }

        .projects-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:20px; }
        .project-card {
            background:var(--card);border-radius:var(--radius-lg);border:1px solid var(--border);
            box-shadow:var(--shadow-xs);overflow:hidden;display:flex;flex-direction:column;
            transition:box-shadow 0.2s,transform 0.2s;
        }
        .project-card:hover { box-shadow:var(--shadow-md);transform:translateY(-2px); }

        .card-header { padding:16px 18px 12px;border-bottom:1px solid var(--border-light);display:flex;align-items:flex-start;justify-content:space-between;gap:10px; }
        .card-title  { font-size:0.95rem;font-weight:800;color:var(--text-primary);margin-bottom:2px; }
        .card-date   { font-size:0.73rem;color:var(--text-muted); }
        .card-body   { padding:14px 18px;flex:1;display:flex;flex-direction:column;gap:10px; }

        .card-student { display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg);border-radius:var(--radius-md); }
        .student-avatar { width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;font-size:0.85rem;color:#fff;font-weight:700;flex-shrink:0; }
        .student-name  { font-size:0.85rem;font-weight:700;color:var(--text-primary); }
        .student-email { font-size:0.75rem;color:var(--text-muted); }

        .card-desc { font-size:0.83rem;color:var(--text-secondary);line-height:1.6;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden; }
        .card-desc.expanded { -webkit-line-clamp:unset; }
        .tech-chips { display:flex;flex-wrap:wrap;gap:4px; }
        .tech-chip  { background:var(--primary-light);color:var(--primary-dark);border-radius:var(--radius-full);padding:3px 10px;font-size:0.72rem;font-weight:600; }

        .card-links { display:flex;gap:10px; }
        .card-link {
            display:inline-flex;align-items:center;gap:6px;padding:7px 14px;
            border-radius:var(--radius-md);font-size:0.8rem;font-weight:600;
            border:1.5px solid var(--border);color:var(--text-secondary);
            text-decoration:none;transition:all 0.2s;flex:1;justify-content:center;
        }
        .card-link:hover          { background:var(--bg-alt);border-color:var(--primary);color:var(--primary); }
        .card-link.github:hover   { border-color:#333;color:#333;background:#f6f8fa; }
        .card-link.youtube:hover  { border-color:#DC2626;color:#DC2626;background:#FFF5F5; }

        .card-footer { padding:14px 18px;border-top:1px solid var(--border-light);display:flex;align-items:center;gap:10px;flex-wrap:wrap; }

        /* Rejection note display on card */
        .rejection-note-box {
            background:#FFF5F5;border:1px solid #FCA5A5;border-radius:var(--radius-md);
            padding:10px 14px;font-size:0.8rem;color:#991B1B;line-height:1.5;
        }
        .rejection-note-box strong { display:block;margin-bottom:3px;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.4px; }

        .badge { display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:var(--radius-full);font-size:0.75rem;font-weight:700; }
        .badge-pending  { background:var(--warning-light);color:#92400E; }
        .badge-approved { background:var(--success-light);color:#166534; }
        .badge-rejected { background:var(--danger-light); color:#991B1B; }

        .btn-approve, .btn-reject { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--radius-md);font-size:0.82rem;font-weight:700;border:none;cursor:pointer;transition:all 0.2s; }
        .btn-approve { background:var(--success-light);color:#166534;border:1.5px solid #86EFAC; }
        .btn-approve:hover { background:#22C55E;color:#fff;border-color:#22C55E; }
        .btn-reject  { background:var(--danger-light);color:#991B1B;border:1.5px solid #FCA5A5; }
        .btn-reject:hover  { background:var(--danger);color:#fff;border-color:var(--danger); }

        .reviewed-info { font-size:0.72rem;color:var(--text-muted);flex:1;text-align:right; }

        .empty-state-lg { text-align:center;padding:60px 20px;color:var(--text-muted); }
        .empty-state-lg i  { font-size:3.5rem;display:block;margin-bottom:16px; }
        .empty-state-lg h3 { font-size:1.1rem;color:var(--text-secondary);margin-bottom:6px; }

        .stats-strip { display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px; }
        .stat-mini { background:var(--card);border-radius:var(--radius-lg);border:1px solid var(--border);padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow-xs); }
        .stat-mini-icon { width:40px;height:40px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:1rem;color:#fff;flex-shrink:0; }
        .stat-mini-val  { font-size:1.4rem;font-weight:800;color:var(--text-primary);line-height:1; }
        .stat-mini-lbl  { font-size:0.75rem;color:var(--text-muted);margin-top:2px; }

        /* Modal */
        .modal-overlay { position:fixed;inset:0;background:rgba(15,37,64,0.45);backdrop-filter:blur(3px);z-index:9999;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity 0.2s; }
        .modal-overlay.show { opacity:1;pointer-events:all; }
        .modal-box { background:var(--card);border-radius:var(--radius-xl);padding:32px;max-width:420px;width:92%;box-shadow:var(--shadow-lg);transform:scale(0.96);transition:transform 0.2s; }
        .modal-overlay.show .modal-box { transform:scale(1); }
        .modal-icon  { font-size:2.2rem;margin-bottom:14px; }
        .modal-title { font-size:1.1rem;font-weight:800;margin-bottom:8px; }
        .modal-desc  { font-size:0.88rem;color:var(--text-secondary);margin-bottom:20px;line-height:1.6; }
        .modal-btns  { display:flex;gap:12px; }

        @media (max-width:900px) { .projects-grid { grid-template-columns:1fr; } .stats-strip { grid-template-columns:1fr 1fr; } }
        @media (max-width:480px) {
            /* Card links: stack vertically */
            .card-links { flex-direction:column; }

            /* Stats: keep 2-col but scale down text */
            .stats-strip { grid-template-columns:1fr 1fr; gap:10px; }
            .stat-num { font-size:1.2rem; }
            .stat-card { padding:14px 12px; }

            /* Modal buttons: stack on phones */
            .modal-btns { flex-direction:column; }
            .modal-btns .btn { width:100%; justify-content:center; min-height:44px; }

            /* Card action buttons: touch-friendly */
            .card-links .btn, .card-links a { min-height:44px; justify-content:center; }

            /* Page header: stack title and any action buttons */
            .page-header { flex-direction:column !important; align-items:flex-start !important; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- HEADER -->
            <div style="background:linear-gradient(135deg,#7C3AED,#9D5CF6);border-radius:var(--radius-xl);padding:28px 32px;color:#fff;margin-bottom:28px;position:relative;overflow:hidden">
                <div style="position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:rgba(255,255,255,0.07);border-radius:50%"></div>
                <h2 style="color:#fff;font-size:1.4rem;margin-bottom:5px">
                    <i class="fas fa-folder-open" style="margin-right:8px"></i>Student Projects
                </h2>
                <p style="color:rgba(255,255,255,0.78);font-size:0.9rem">Review and manage project submissions from your students.</p>
            </div>

            <!-- ALERTS -->
            <?php if ($success): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
            <?php endif; ?>

            <!-- STATS -->
            <div class="stats-strip">
                <div class="stat-mini">
                    <div class="stat-mini-icon" style="background:linear-gradient(135deg,var(--primary),var(--primary-dark))"><i class="fas fa-layer-group"></i></div>
                    <div><div class="stat-mini-val"><?php echo $counts['All']; ?></div><div class="stat-mini-lbl">Total Projects</div></div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-icon" style="background:linear-gradient(135deg,#F59E0B,#D97706)"><i class="fas fa-clock"></i></div>
                    <div><div class="stat-mini-val"><?php echo $counts['Pending']; ?></div><div class="stat-mini-lbl">Pending Review</div></div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-icon" style="background:linear-gradient(135deg,#22C55E,#16A34A)"><i class="fas fa-check-circle"></i></div>
                    <div><div class="stat-mini-val"><?php echo $counts['Approved']; ?></div><div class="stat-mini-lbl">Approved</div></div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-icon" style="background:linear-gradient(135deg,#EF4444,#DC2626)"><i class="fas fa-times-circle"></i></div>
                    <div><div class="stat-mini-val"><?php echo $counts['Rejected']; ?></div><div class="stat-mini-lbl">Rejected</div></div>
                </div>
            </div>

            <!-- FILTER TABS -->
            <div class="filter-tabs">
                <?php
                $tab_defs = [
                    'All'      => ['icon' => 'fa-layer-group', 'class' => ''],
                    'Pending'  => ['icon' => 'fa-clock',       'class' => 'tab-pending'],
                    'Approved' => ['icon' => 'fa-check-circle','class' => 'tab-approved'],
                    'Rejected' => ['icon' => 'fa-times-circle','class' => 'tab-rejected'],
                ];
                foreach ($tab_defs as $key => $def):
                    $active = $filter === $key ? 'active' : '';
                ?>
                <a href="?status=<?php echo $key; ?>" class="filter-tab <?php echo $def['class']; ?> <?php echo $active; ?>">
                    <i class="fas <?php echo $def['icon']; ?>"></i>
                    <?php echo $key; ?>
                    <span class="count"><?php echo $counts[$key]; ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- PROJECTS GRID -->
            <?php if ($projects && $projects->num_rows > 0): ?>
            <div class="projects-grid">
                <?php while ($p = $projects->fetch_assoc()):
                    $status     = $p['status'];
                    $badgeClass = $status === 'Approved' ? 'badge-approved' : ($status === 'Rejected' ? 'badge-rejected' : 'badge-pending');
                    $icon       = $status === 'Approved' ? 'fa-check-circle' : ($status === 'Rejected' ? 'fa-times-circle' : 'fa-clock');
                    $initials   = strtoupper(substr($p['student_name'], 0, 1));
                    $techs      = array_filter(array_map('trim', explode(',', $p['technologies_used'])));
                ?>
                <div class="project-card" id="card-<?php echo $p['id']; ?>">

                    <!-- Header -->
                    <div class="card-header">
                        <div>
                            <div class="card-title"><?php echo htmlspecialchars($p['project_title']); ?></div>
                            <div class="card-date">
                                <i class="fas fa-calendar-alt" style="margin-right:3px"></i>
                                <?php echo date('d M Y, h:i A', strtotime($p['submitted_at'])); ?>
                            </div>
                        </div>
                        <span class="badge <?php echo $badgeClass; ?>">
                            <i class="fas <?php echo $icon; ?>"></i> <?php echo $status; ?>
                        </span>
                    </div>

                    <!-- Body -->
                    <div class="card-body">

                        <div class="card-student">
                            <div class="student-avatar"><?php echo $initials; ?></div>
                            <div>
                                <div class="student-name"><?php echo htmlspecialchars($p['student_name']); ?></div>
                                <div class="student-email"><?php echo htmlspecialchars($p['student_email']); ?></div>
                            </div>
                        </div>

                        <div>
                            <div style="font-size:0.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:5px">Description</div>
                            <p class="card-desc" id="desc-<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['project_description']); ?></p>
                            <?php if (strlen($p['project_description']) > 160): ?>
                            <button onclick="toggleDesc(<?php echo $p['id']; ?>)" id="toggleBtn-<?php echo $p['id']; ?>"
                                    style="background:none;border:none;color:var(--primary-dark);font-size:0.78rem;font-weight:700;cursor:pointer;padding:2px 0;margin-top:2px">
                                <i class="fas fa-chevron-down"></i> Show more
                            </button>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($techs)): ?>
                        <div>
                            <div style="font-size:0.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px">Technologies</div>
                            <div class="tech-chips">
                                <?php foreach ($techs as $t): ?>
                                <span class="tech-chip"><?php echo htmlspecialchars($t); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div>
                            <div style="font-size:0.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px">Project Links</div>
                            <div class="card-links">
                                <a href="<?php echo htmlspecialchars($p['github_link']); ?>" target="_blank" rel="noopener" class="card-link github">
                                    <i class="fab fa-github"></i> GitHub Repo
                                </a>
                                <a href="<?php echo htmlspecialchars($p['youtube_link']); ?>" target="_blank" rel="noopener" class="card-link youtube">
                                    <i class="fab fa-youtube"></i> YouTube Demo
                                </a>
                            </div>
                        </div>

                        <!-- Rejection note visible on card for trainer reference -->
                        <?php if ($status === 'Rejected' && !empty($p['rejection_note'])): ?>
                        <div class="rejection-note-box">
                            <strong><i class="fas fa-comment-alt"></i> Rejection Reason</strong>
                            <?php echo htmlspecialchars($p['rejection_note']); ?>
                        </div>
                        <?php endif; ?>

                    </div><!-- /.card-body -->

                    <!-- Footer Actions -->
                    <div class="card-footer">
                        <?php if ($status === 'Pending'): ?>
                        <button class="btn-approve" onclick="confirmAction(<?php echo $p['id']; ?>, 'Approved', '<?php echo addslashes(htmlspecialchars($p['project_title'])); ?>')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn-reject" onclick="confirmAction(<?php echo $p['id']; ?>, 'Rejected', '<?php echo addslashes(htmlspecialchars($p['project_title'])); ?>')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <?php elseif ($status === 'Approved'): ?>
                        <span class="badge badge-approved"><i class="fas fa-check-circle"></i> Approved</span>
                        <button class="btn-reject" style="margin-left:auto" onclick="confirmAction(<?php echo $p['id']; ?>, 'Rejected', '<?php echo addslashes(htmlspecialchars($p['project_title'])); ?>')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <?php else: ?>
                        <span class="badge badge-rejected"><i class="fas fa-times-circle"></i> Rejected</span>
                        <button class="btn-approve" style="margin-left:auto" onclick="confirmAction(<?php echo $p['id']; ?>, 'Approved', '<?php echo addslashes(htmlspecialchars($p['project_title'])); ?>')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <?php endif; ?>

                        <?php if ($p['reviewer_name'] && $p['reviewed_at']): ?>
                        <div class="reviewed-info" style="margin-left:auto">
                            <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($p['reviewer_name']); ?><br>
                            <?php echo date('d M Y', strtotime($p['reviewed_at'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endwhile; ?>
            </div>

            <?php else: ?>
            <div class="empty-state-lg">
                <i class="fas fa-inbox" style="color:var(--text-muted)"></i>
                <h3>No projects <?php echo $filter !== 'All' ? "with status \"$filter\"" : "submitted yet"; ?></h3>
                <p style="font-size:0.875rem">
                    <?php echo $filter !== 'All' ? 'Try a different filter to see other projects.' : 'Students will appear here once they submit their projects.'; ?>
                </p>
                <?php if ($filter !== 'All'): ?>
                <a href="?status=All" style="display:inline-flex;align-items:center;gap:6px;margin-top:16px;padding:9px 20px;background:var(--primary-light);color:var(--primary-dark);border-radius:var(--radius-md);font-weight:700;font-size:0.85rem">
                    <i class="fas fa-list"></i> View All Projects
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- CONFIRMATION MODAL                                                        -->
<!-- The <form> wraps EVERYTHING including the rejection textarea.             -->
<!-- The textarea has name="rejection_note" so the browser submits it natively.-->
<!-- No JavaScript copy is needed — the form POST includes it automatically.  -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">

        <div class="modal-icon"  id="modalIcon"></div>
        <div class="modal-title" id="modalTitle"></div>
        <div class="modal-desc"  id="modalDesc"></div>

        <form id="actionForm" method="POST" action="projects.php">

            <input type="hidden" name="action"     id="formAction">
            <input type="hidden" name="project_id" id="formProjectId">

            <!-- Shown only for Reject — cleared and hidden for Approve -->
            <div id="rejectionNoteWrap" style="display:none;margin-bottom:20px">
                <label style="font-size:0.82rem;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:6px">
                    <i class="fas fa-comment-alt" style="color:#EF4444;margin-right:4px"></i>
                    Rejection Reason
                    <span style="font-size:0.75rem;color:var(--text-muted);font-weight:500"> (optional — shown to student)</span>
                </label>
                <textarea name="rejection_note" id="rejectionNoteInput"
                          placeholder="e.g. GitHub link is broken, please fix and resubmit..."
                          style="width:100%;border:1.5px solid #FCA5A5;border-radius:var(--radius-md);padding:10px 14px;font-size:0.85rem;font-family:'DM Sans',sans-serif;resize:vertical;min-height:90px;outline:none;background:#FFF5F5;color:var(--text-primary);transition:border-color 0.2s;box-sizing:border-box"
                          onfocus="this.style.borderColor='#EF4444'"
                          onblur="this.style.borderColor='#FCA5A5'"></textarea>
            </div>

            <div class="modal-btns">
                <button type="submit" id="modalConfirmBtn"
                        style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 22px;border:none;border-radius:var(--radius-md);font-size:0.9rem;font-weight:700;color:#fff;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s">
                    Confirm
                </button>
                <button type="button" onclick="closeModal()"
                        style="flex:1;padding:10px 22px;border:1.5px solid var(--border);border-radius:var(--radius-md);background:var(--card);color:var(--text-secondary);font-size:0.9rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s">
                    Cancel
                </button>
            </div>

        </form>
    </div>
</div>

<?php include("../includes/footer.php"); ?>

<script>
function confirmAction(projectId, action, title) {
    var isApprove = (action === 'Approved');
    var noteWrap  = document.getElementById('rejectionNoteWrap');
    var noteInput = document.getElementById('rejectionNoteInput');
    var btn       = document.getElementById('modalConfirmBtn');

    document.getElementById('modalIcon').innerHTML =
        isApprove
        ? '<i class="fas fa-check-circle" style="color:#22C55E"></i>'
        : '<i class="fas fa-times-circle" style="color:#EF4444"></i>';

    document.getElementById('modalTitle').textContent =
        isApprove ? 'Approve Project?' : 'Reject Project?';

    document.getElementById('modalDesc').innerHTML = isApprove
        ? 'Are you sure you want to <strong>approve</strong> the project:<br><em>"' + title + '"</em>?<br><br><span style="font-size:0.82rem;color:var(--text-muted)">The student will be notified.</span>'
        : 'You are about to <strong>reject</strong> the project:<br><em>"' + title + '"</em><br><br><span style="font-size:0.82rem;color:var(--text-muted)">Optionally add a reason — the student will see it.</span>';

    // Show textarea only for Reject; clear it every time modal opens
    noteWrap.style.display = isApprove ? 'none' : 'block';
    noteInput.value        = '';

    btn.style.background = isApprove ? '#22C55E' : '#EF4444';
    btn.textContent      = isApprove ? 'Yes, Approve' : 'Yes, Reject';

    document.getElementById('formAction').value    = action;
    document.getElementById('formProjectId').value = projectId;

    document.getElementById('confirmModal').classList.add('show');
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('show');
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function toggleDesc(id) {
    var desc     = document.getElementById('desc-' + id);
    var btn      = document.getElementById('toggleBtn-' + id);
    var expanded = desc.classList.toggle('expanded');
    btn.innerHTML = expanded
        ? '<i class="fas fa-chevron-up"></i> Show less'
        : '<i class="fas fa-chevron-down"></i> Show more';
}

var successAlert = document.getElementById('successAlert');
if (successAlert) {
    setTimeout(function () {
        successAlert.style.transition = 'opacity 0.5s';
        successAlert.style.opacity    = '0';
        setTimeout(function () { successAlert.remove(); }, 500);
    }, 4000);
}
</script>
</body>
</html>