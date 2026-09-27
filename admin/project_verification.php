<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

$admin_id = $_SESSION['user_id'];
$success  = '';
$error    = '';

// ── Handle Mark Completed / Incomplete ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['project_id'])) {
    $action = $_POST['action'];
    $pid    = (int)$_POST['project_id'];
    $now    = date('Y-m-d H:i:s');

    if ($action === 'mark_completed') {
        $upd = $conn->prepare("UPDATE project_submissions SET completion_status='Completed', completed_by=?, completed_at=? WHERE id=? AND status='Approved'");
        $upd->bind_param("isi", $admin_id, $now, $pid);
        if ($upd->execute() && $upd->affected_rows > 0) {
            $success = "Project marked as <strong>Completed</strong>.";
            // Notify the student
            $pr = $conn->query("SELECT student_id, project_title FROM project_submissions WHERE id=$pid");
            if ($pr && $row = $pr->fetch_assoc()) {
                $nt = "Project Marked Completed";
                $nm = "Congratulations! Your project \"" . $row['project_title'] . "\" has been marked as Completed by Admin.";
                $nl = "../student/project_submission.php";
                $ny = "project_review";
                $ns = $conn->prepare("INSERT INTO notifications (type,for_role,for_user_id,title,message,link,is_read) VALUES (?,'student',?,?,?,?,0)");
                $ns->bind_param("sisss", $ny, $row['student_id'], $nt, $nm, $nl);
                $ns->execute();
            }
        } else {
            $error = "Could not update. Project must be Approved first.";
        }

    } elseif ($action === 'mark_incomplete') {
        $upd = $conn->prepare("UPDATE project_submissions SET completion_status='Incomplete', completed_by=NULL, completed_at=NULL WHERE id=?");
        $upd->bind_param("i", $pid);
        $upd->execute();
        $success = "Project marked as <strong>Incomplete</strong>.";

    } elseif ($action === 'issue_certificate') {
        // Mark completed first, then record certificate issuance
        $chk = $conn->query("SELECT ps.*, u.name AS sname, u.email AS semail FROM project_submissions ps JOIN users u ON ps.student_id=u.id WHERE ps.id=$pid AND ps.status='Approved' AND ps.completion_status='Completed'");
        if ($chk && $chk->num_rows > 0) {
            $pr = $chk->fetch_assoc();
            // Notify student a certificate has been issued
            $nt = "Certificate Issued";
            $nm = "Your certificate for project \"" . $pr['project_title'] . "\" has been issued by Admin. Check your certificates section.";
            $nl = "../student/certificates.php";
            $ny = "project_review";
            $ns = $conn->prepare("INSERT INTO notifications (type,for_role,for_user_id,title,message,link,is_read) VALUES (?,'student',?,?,?,?,0)");
            $ns->bind_param("sisss", $ny, $pr['student_id'], $nt, $nm, $nl);
            $ns->execute();
            $success = "Certificate issued and student notified successfully!";
        } else {
            $error = "Certificate can only be issued for <strong>Approved</strong> and <strong>Completed</strong> projects.";
        }
    }
}

// ── Filters ──────────────────────────────────────────────────────────────────
$allowed_status     = ['All','Pending','Approved','Rejected'];
$allowed_completion = ['All','Completed','Incomplete'];
$f_status     = (isset($_GET['status'])     && in_array($_GET['status'],     $allowed_status))     ? $_GET['status']     : 'All';
$f_completion = (isset($_GET['completion']) && in_array($_GET['completion'], $allowed_completion)) ? $_GET['completion'] : 'All';
$f_search     = trim($_GET['search'] ?? '');

// ── Build WHERE ───────────────────────────────────────────────────────────────
$conditions = [];
if ($f_status !== 'All')     $conditions[] = "ps.status='" . $conn->real_escape_string($f_status) . "'";
if ($f_completion !== 'All') $conditions[] = "ps.completion_status='" . $conn->real_escape_string($f_completion) . "'";
if ($f_search !== '')        $conditions[] = "(u.name LIKE '%" . $conn->real_escape_string($f_search) . "%' OR ps.project_title LIKE '%" . $conn->real_escape_string($f_search) . "%')";
$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

// ── Fetch projects ────────────────────────────────────────────────────────────
$query = "
    SELECT
        ps.id, ps.project_title, ps.project_description, ps.technologies_used,
        ps.github_link, ps.youtube_link, ps.status, ps.completion_status,
        ps.submitted_at, ps.reviewed_at, ps.completed_at, ps.resubmitted_at,
        u.id   AS student_id,
        u.name AS student_name,
        u.email AS student_email,
        r.name AS trainer_name,
        cb.name AS completed_by_name,
        c.title AS course_title
    FROM project_submissions ps
    JOIN  users u  ON ps.student_id  = u.id
    LEFT JOIN users r  ON ps.reviewed_by = r.id
    LEFT JOIN users cb ON ps.completed_by = cb.id
    LEFT JOIN enrollments e ON e.user_id = ps.student_id AND e.status='approved'
    LEFT JOIN courses c     ON c.id = e.course_id
    $where
    GROUP BY ps.id
    ORDER BY
        CASE ps.status WHEN 'Approved' THEN 0 WHEN 'Pending' THEN 1 ELSE 2 END,
        CASE ps.completion_status WHEN 'Incomplete' THEN 0 ELSE 1 END,
        ps.submitted_at DESC
";
$projects = $conn->query($query);

// ── Counts ────────────────────────────────────────────────────────────────────
$cnt = ['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'completed'=>0,'incomplete'=>0];
$cr  = $conn->query("SELECT status, completion_status, COUNT(*) as c FROM project_submissions GROUP BY status, completion_status");
if ($cr) {
    while ($r = $cr->fetch_assoc()) {
        $cnt['total'] += (int)$r['c'];
        $cnt[strtolower($r['status'])]     = ($cnt[strtolower($r['status'])] ?? 0) + (int)$r['c'];
        $cnt[strtolower($r['completion_status'])] = ($cnt[strtolower($r['completion_status'])] ?? 0) + (int)$r['c'];
    }
}

$page_title = "Project Verification";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Verification – CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        /* Alerts */
        .pv-alert {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 13px 18px; border-radius: var(--radius-md);
            font-size: 0.88rem; margin-bottom: 20px;
        }
        .pv-alert.ok  { background:#DCFCE7; border:1px solid #86EFAC; color:#166534; }
        .pv-alert.err { background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; }
        .pv-alert i { flex-shrink:0; font-size:1rem; margin-top:1px; }

        /* Stats strip */
        .pv-stats {
            display: grid; grid-template-columns: repeat(6,1fr);
            gap: 14px; margin-bottom: 24px;
        }
        .pv-stat {
            background: var(--card); border-radius: var(--radius-lg);
            border: 1px solid var(--border); padding: 14px 16px;
            display: flex; align-items: center; gap: 11px;
            box-shadow: var(--shadow-xs);
        }
        .pv-stat-icon {
            width: 38px; height: 38px; border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem; color: #fff; flex-shrink: 0;
        }
        .pv-stat-val { font-size: 1.3rem; font-weight: 800; color: var(--text-primary); line-height: 1; }
        .pv-stat-lbl { font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; }

        /* Filter bar */
        .pv-filters {
            background: var(--card); border-radius: var(--radius-lg);
            border: 1px solid var(--border); padding: 16px 20px;
            margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
        }
        .pv-filter-group { display: flex; align-items: center; gap: 8px; }
        .pv-filter-group label { font-size: 0.78rem; font-weight: 700; color: var(--text-secondary); white-space: nowrap; }
        .pv-select {
            border: 1.5px solid var(--border); border-radius: var(--radius-md);
            padding: 7px 12px; font-size: 0.85rem; color: var(--text-primary);
            background: var(--bg); outline: none; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: border-color 0.2s;
        }
        .pv-select:focus { border-color: var(--primary); }
        .pv-search {
            display: flex; align-items: center; gap: 8px;
            border: 1.5px solid var(--border); border-radius: var(--radius-md);
            padding: 7px 12px; background: var(--bg);
            transition: border-color 0.2s; flex: 1; min-width: 180px;
        }
        .pv-search:focus-within { border-color: var(--primary); background: #fff; }
        .pv-search i { color: var(--text-muted); font-size: 0.85rem; }
        .pv-search input {
            border: none; background: transparent; outline: none;
            font-size: 0.85rem; color: var(--text-primary); width: 100%;
            font-family: 'DM Sans', sans-serif;
        }
        .pv-filter-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: var(--radius-md);
            background: var(--primary); color: #fff; border: none;
            font-size: 0.85rem; font-weight: 700; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: opacity 0.2s;
        }
        .pv-filter-btn:hover { opacity: 0.9; }

        /* Table */
        .pv-table-wrap {
            background: var(--card); border-radius: var(--radius-xl);
            border: 1px solid var(--border); box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .pv-table { width: 100%; border-collapse: collapse; }
        .pv-table th {
            background: var(--bg-alt); font-size: 0.72rem; font-weight: 700;
            color: var(--text-secondary); text-transform: uppercase;
            letter-spacing: 0.5px; padding: 11px 14px; text-align: left;
            border-bottom: 1px solid var(--border); white-space: nowrap;
        }
        .pv-table td {
            padding: 13px 14px; font-size: 0.855rem;
            border-bottom: 1px solid var(--border-light); vertical-align: top;
        }
        .pv-table tr:last-child td { border-bottom: none; }
        .pv-table tr:hover td { background: var(--bg); }

        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: var(--radius-full);
            font-size: 0.72rem; font-weight: 700; white-space: nowrap;
        }
        .badge-pending    { background:#FEF3C7; color:#92400E; }
        .badge-approved   { background:#DCFCE7; color:#166534; }
        .badge-rejected   { background:#FEE2E2; color:#991B1B; }
        .badge-completed  { background:#EDE9FE; color:#5B21B6; }
        .badge-incomplete { background:#F1F5F9; color:#475569; }

        /* Action buttons */
        .btn-complete {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 12px; border-radius: var(--radius-md);
            font-size: 0.78rem; font-weight: 700;
            background: #EDE9FE; color: #5B21B6;
            border: 1.5px solid #C4B5FD; cursor: pointer;
            transition: all 0.2s; white-space: nowrap;
        }
        .btn-complete:hover { background: #7C3AED; color: #fff; border-color: #7C3AED; }
        .btn-incomplete {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 12px; border-radius: var(--radius-md);
            font-size: 0.78rem; font-weight: 700;
            background: var(--bg-alt); color: var(--text-secondary);
            border: 1.5px solid var(--border); cursor: pointer;
            transition: all 0.2s; white-space: nowrap;
        }
        .btn-incomplete:hover { background: var(--border); color: var(--text-primary); }
        .btn-certificate {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 12px; border-radius: var(--radius-md);
            font-size: 0.78rem; font-weight: 700;
            background: linear-gradient(135deg,#F59E0B,#D97706);
            color: #fff; border: none; cursor: pointer;
            transition: opacity 0.2s; white-space: nowrap;
        }
        .btn-certificate:hover { opacity: 0.88; }
        .btn-certificate:disabled { opacity: 0.4; cursor: not-allowed; }

        .action-stack { display: flex; flex-direction: column; gap: 6px; }

        /* Student / project info in cells */
        .cell-name  { font-weight: 700; font-size: 0.88rem; color: var(--text-primary); margin-bottom: 2px; }
        .cell-sub   { font-size: 0.75rem; color: var(--text-muted); }
        .cell-link  {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 0.78rem; font-weight: 600; color: var(--primary-dark);
        }
        .cell-link:hover { color: var(--primary); }
        .tech-chip {
            display: inline-block;
            background: var(--primary-light); color: var(--primary-dark);
            border-radius: var(--radius-full); padding: 2px 8px;
            font-size: 0.68rem; font-weight: 600; margin: 1px 2px 1px 0;
        }
        .empty-state {
            text-align: center; padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 3rem; display: block; margin-bottom: 14px; }
        .empty-state h3 { font-size: 1rem; color: var(--text-secondary); margin-bottom: 6px; }

        /* Confirm modal */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(15,37,64,0.45);
            backdrop-filter: blur(3px); z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.2s;
        }
        .modal-overlay.show { opacity: 1; pointer-events: all; }
        .modal-box {
            background: var(--card); border-radius: var(--radius-xl);
            padding: 32px; max-width: 420px; width: 92%;
            box-shadow: var(--shadow-lg); transform: scale(0.96); transition: transform 0.2s;
        }
        .modal-overlay.show .modal-box { transform: scale(1); }
        .modal-icon  { font-size: 2.2rem; margin-bottom: 14px; }
        .modal-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 8px; }
        .modal-desc  { font-size: 0.87rem; color: var(--text-secondary); margin-bottom: 24px; line-height: 1.6; }
        .modal-btns  { display: flex; gap: 12px; }
        .modal-confirm {
            flex: 1; padding: 10px 20px; border: none; border-radius: var(--radius-md);
            font-size: 0.9rem; font-weight: 700; color: #fff; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: opacity 0.2s;
        }
        .modal-confirm:hover { opacity: 0.9; }
        .modal-cancel {
            flex: 1; padding: 10px 20px; border: 1.5px solid var(--border);
            border-radius: var(--radius-md); background: var(--card);
            color: var(--text-secondary); font-size: 0.9rem; font-weight: 700;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
        }

        @media (max-width: 1100px) { .pv-stats { grid-template-columns: repeat(3,1fr); } }
        @media (max-width: 700px)  {
            .pv-stats { grid-template-columns: repeat(2,1fr); }
            .pv-table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- BANNER -->
            <div style="background:linear-gradient(135deg,#0F766E,#14B8A6);border-radius:var(--radius-xl);padding:26px 32px;color:#fff;margin-bottom:28px;position:relative;overflow:hidden">
                <div style="position:absolute;top:-40px;right:-40px;width:160px;height:160px;background:rgba(255,255,255,0.07);border-radius:50%"></div>
                <h2 style="color:#fff;font-size:1.35rem;margin-bottom:4px">
                    <i class="fas fa-tasks" style="margin-right:8px"></i>Project Verification
                </h2>
                <p style="color:rgba(255,255,255,0.78);font-size:0.88rem">
                    Verify approved projects, mark completion status, and issue certificates.
                </p>
            </div>

            <!-- ALERTS -->
            <?php if ($success): ?>
            <div class="pv-alert ok" id="pvAlert">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="pv-alert err">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
            <?php endif; ?>

            <!-- STATS -->
            <div class="pv-stats">
                <div class="pv-stat">
                    <div class="pv-stat-icon" style="background:linear-gradient(135deg,var(--primary),var(--primary-dark))"><i class="fas fa-layer-group"></i></div>
                    <div><div class="pv-stat-val"><?php echo $cnt['total']; ?></div><div class="pv-stat-lbl">Total</div></div>
                </div>
                <div class="pv-stat">
                    <div class="pv-stat-icon" style="background:linear-gradient(135deg,#F59E0B,#D97706)"><i class="fas fa-clock"></i></div>
                    <div><div class="pv-stat-val"><?php echo $cnt['pending'] ?? 0; ?></div><div class="pv-stat-lbl">Pending</div></div>
                </div>
                <div class="pv-stat">
                    <div class="pv-stat-icon" style="background:linear-gradient(135deg,#22C55E,#16A34A)"><i class="fas fa-check-circle"></i></div>
                    <div><div class="pv-stat-val"><?php echo $cnt['approved'] ?? 0; ?></div><div class="pv-stat-lbl">Approved</div></div>
                </div>
                <div class="pv-stat">
                    <div class="pv-stat-icon" style="background:linear-gradient(135deg,#EF4444,#DC2626)"><i class="fas fa-times-circle"></i></div>
                    <div><div class="pv-stat-val"><?php echo $cnt['rejected'] ?? 0; ?></div><div class="pv-stat-lbl">Rejected</div></div>
                </div>
                <div class="pv-stat">
                    <div class="pv-stat-icon" style="background:linear-gradient(135deg,#7C3AED,#6D28D9)"><i class="fas fa-award"></i></div>
                    <div><div class="pv-stat-val"><?php echo $cnt['completed'] ?? 0; ?></div><div class="pv-stat-lbl">Completed</div></div>
                </div>
                <div class="pv-stat">
                    <div class="pv-stat-icon" style="background:linear-gradient(135deg,#64748B,#475569)"><i class="fas fa-hourglass-half"></i></div>
                    <div><div class="pv-stat-val"><?php echo $cnt['incomplete'] ?? 0; ?></div><div class="pv-stat-lbl">Incomplete</div></div>
                </div>
            </div>

            <!-- FILTERS -->
            <form method="GET" action="project_verification.php">
                <div class="pv-filters">
                    <div class="pv-filter-group">
                        <label>Status</label>
                        <select name="status" class="pv-select">
                            <?php foreach (['All','Pending','Approved','Rejected'] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo $f_status === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pv-filter-group">
                        <label>Completion</label>
                        <select name="completion" class="pv-select">
                            <?php foreach (['All','Completed','Incomplete'] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo $f_completion === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pv-search">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by student name or project title…"
                               value="<?php echo htmlspecialchars($f_search); ?>">
                    </div>
                    <button type="submit" class="pv-filter-btn"><i class="fas fa-filter"></i> Apply</button>
                    <?php if ($f_status !== 'All' || $f_completion !== 'All' || $f_search !== ''): ?>
                    <a href="project_verification.php" style="font-size:0.82rem;color:var(--text-muted);text-decoration:none;white-space:nowrap">
                        <i class="fas fa-times"></i> Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- TABLE -->
            <div class="pv-table-wrap">
                <?php if ($projects && $projects->num_rows > 0): ?>
                <div style="overflow-x:auto">
                    <table class="pv-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Project Details</th>
                                <th>Technologies</th>
                                <th>Links</th>
                                <th>Trainer</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th>Completion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; while ($p = $projects->fetch_assoc()):
                            $s  = $p['status'];
                            $cs = $p['completion_status'] ?? 'Incomplete';
                            $techs = array_filter(array_map('trim', explode(',', $p['technologies_used'])));
                        ?>
                        <tr>
                            <td style="font-weight:700;color:var(--text-muted)"><?php echo $i++; ?></td>

                            <!-- Student -->
                            <td style="min-width:160px">
                                <div class="cell-name"><?php echo htmlspecialchars($p['student_name']); ?></div>
                                <div class="cell-sub"><?php echo htmlspecialchars($p['student_email']); ?></div>
                                <?php if (!empty($p['course_title'])): ?>
                                <div class="cell-sub" style="margin-top:3px">
                                    <i class="fas fa-book-open" style="font-size:0.68rem"></i>
                                    <?php echo htmlspecialchars($p['course_title']); ?>
                                </div>
                                <?php endif; ?>
                            </td>

                            <!-- Project -->
                            <td style="min-width:200px">
                                <div class="cell-name"><?php echo htmlspecialchars($p['project_title']); ?></div>
                                <div class="cell-sub" style="max-width:220px;line-height:1.5">
                                    <?php
                                    $d = $p['project_description'];
                                    echo htmlspecialchars(mb_strlen($d) > 80 ? mb_substr($d,0,80).'…' : $d);
                                    ?>
                                </div>
                            </td>

                            <!-- Technologies -->
                            <td style="min-width:140px">
                                <?php foreach ($techs as $t): ?>
                                <span class="tech-chip"><?php echo htmlspecialchars($t); ?></span>
                                <?php endforeach; ?>
                            </td>

                            <!-- Links -->
                            <td style="white-space:nowrap;min-width:120px">
                                <a href="<?php echo htmlspecialchars($p['github_link']); ?>"
                                   target="_blank" rel="noopener" class="cell-link" style="display:block;margin-bottom:5px">
                                    <i class="fab fa-github"></i> GitHub
                                </a>
                                <a href="<?php echo htmlspecialchars($p['youtube_link']); ?>"
                                   target="_blank" rel="noopener" class="cell-link" style="color:#DC2626">
                                    <i class="fab fa-youtube"></i> Demo
                                </a>
                            </td>

                            <!-- Trainer -->
                            <td style="min-width:130px">
                                <?php if ($p['trainer_name']): ?>
                                <div class="cell-name"><?php echo htmlspecialchars($p['trainer_name']); ?></div>
                                <div class="cell-sub">
                                    <?php if ($p['reviewed_at']): ?>
                                    Approved <?php echo date('d M Y', strtotime($p['reviewed_at'])); ?>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <span class="cell-sub">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Dates -->
                            <td style="font-size:0.78rem;color:var(--text-muted);white-space:nowrap;min-width:120px">
                                <div><i class="fas fa-paper-plane" style="font-size:0.68rem"></i> <?php echo date('d M Y', strtotime($p['submitted_at'])); ?></div>
                                <?php if ($p['resubmitted_at']): ?>
                                <div style="color:#EA580C"><i class="fas fa-redo" style="font-size:0.68rem"></i> <?php echo date('d M Y', strtotime($p['resubmitted_at'])); ?></div>
                                <?php endif; ?>
                                <?php if ($p['completed_at']): ?>
                                <div style="color:#7C3AED"><i class="fas fa-award" style="font-size:0.68rem"></i> <?php echo date('d M Y', strtotime($p['completed_at'])); ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Project Status -->
                            <td>
                                <?php
                                $bc = $s === 'Approved' ? 'badge-approved' : ($s === 'Rejected' ? 'badge-rejected' : 'badge-pending');
                                $ic = $s === 'Approved' ? 'fa-check-circle' : ($s === 'Rejected' ? 'fa-times-circle' : 'fa-clock');
                                ?>
                                <span class="badge <?php echo $bc; ?>">
                                    <i class="fas <?php echo $ic; ?>"></i> <?php echo $s; ?>
                                </span>
                            </td>

                            <!-- Completion Status -->
                            <td>
                                <?php if ($cs === 'Completed'): ?>
                                <span class="badge badge-completed"><i class="fas fa-award"></i> Completed</span>
                                <?php if ($p['completed_by_name']): ?>
                                <div style="font-size:0.7rem;color:var(--text-muted);margin-top:3px">
                                    by <?php echo htmlspecialchars($p['completed_by_name']); ?>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="badge badge-incomplete"><i class="fas fa-hourglass-half"></i> Incomplete</span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td style="min-width:140px">
                                <?php if ($s === 'Approved'): ?>
                                <div class="action-stack">
                                    <?php if ($cs !== 'Completed'): ?>
                                    <button class="btn-complete"
                                            onclick="openModal(<?php echo $p['id']; ?>, 'mark_completed', '<?php echo addslashes(htmlspecialchars($p['project_title'])); ?>')">
                                        <i class="fas fa-check"></i> Mark Completed
                                    </button>
                                    <?php else: ?>
                                    <button class="btn-incomplete"
                                            onclick="openModal(<?php echo $p['id']; ?>, 'mark_incomplete', '<?php echo addslashes(htmlspecialchars($p['project_title'])); ?>')">
                                        <i class="fas fa-undo"></i> Mark Incomplete
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn-certificate"
                                            <?php echo ($cs !== 'Completed') ? 'disabled title="Mark as Completed first"' : ''; ?>
                                            onclick="<?php echo ($cs === 'Completed') ? 'openModal(' . $p['id'] . ', \'issue_certificate\', \'' . addslashes(htmlspecialchars($p['project_title'])) . '\')' : 'void(0)'; ?>">
                                        <i class="fas fa-certificate"></i> Issue Certificate
                                    </button>
                                </div>
                                <?php else: ?>
                                <span style="font-size:0.78rem;color:var(--text-muted)">
                                    <?php echo $s === 'Pending' ? 'Awaiting trainer review' : 'Rejected — no action'; ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox" style="color:var(--text-muted)"></i>
                    <h3>No projects found</h3>
                    <p style="font-size:0.875rem">
                        <?php echo ($f_status!=='All'||$f_completion!=='All'||$f_search!=='') ? 'Try adjusting your filters.' : 'No project submissions yet.'; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- CONFIRM MODAL -->
<div class="modal-overlay" id="pvModal">
    <div class="modal-box">
        <div class="modal-icon" id="pvModalIcon"></div>
        <div class="modal-title" id="pvModalTitle"></div>
        <div class="modal-desc"  id="pvModalDesc"></div>
        <div class="modal-btns">
            <button class="modal-confirm" id="pvModalConfirm">Confirm</button>
            <button class="modal-cancel"  onclick="closeModal()">Cancel</button>
        </div>
        <form id="pvActionForm" method="POST" action="" style="display:none">
            <input type="hidden" name="action"     id="pvFormAction">
            <input type="hidden" name="project_id" id="pvFormId">
        </form>
    </div>
</div>

<?php include("../includes/footer.php"); ?>

<script>
function openModal(id, action, title) {
    var configs = {
        mark_completed: {
            icon:  '<i class="fas fa-award" style="color:#7C3AED"></i>',
            title: 'Mark as Completed?',
            desc:  'Mark <em>"' + title + '"</em> as <strong>Completed</strong>? The student will be notified.',
            color: '#7C3AED', label: 'Yes, Mark Completed'
        },
        mark_incomplete: {
            icon:  '<i class="fas fa-undo" style="color:#64748B"></i>',
            title: 'Mark as Incomplete?',
            desc:  'Revert <em>"' + title + '"</em> back to <strong>Incomplete</strong>?',
            color: '#64748B', label: 'Yes, Mark Incomplete'
        },
        issue_certificate: {
            icon:  '<i class="fas fa-certificate" style="color:#F59E0B"></i>',
            title: 'Issue Certificate?',
            desc:  'Issue a certificate for <em>"' + title + '"</em>? The student will be notified to check their certificates section.',
            color: '#D97706', label: 'Yes, Issue Certificate'
        }
    };
    var c = configs[action];
    if (!c) return;
    document.getElementById('pvModalIcon').innerHTML  = c.icon;
    document.getElementById('pvModalTitle').textContent = c.title;
    document.getElementById('pvModalDesc').innerHTML  = c.desc;
    var btn = document.getElementById('pvModalConfirm');
    btn.style.background = c.color;
    btn.textContent = c.label;
    btn.onclick = function () { submitAction(id, action); };
    document.getElementById('pvFormAction').value = action;
    document.getElementById('pvFormId').value     = id;
    document.getElementById('pvModal').classList.add('show');
}

function closeModal() {
    document.getElementById('pvModal').classList.remove('show');
}

function submitAction(id, action) {
    document.getElementById('pvFormAction').value = action;
    document.getElementById('pvFormId').value     = id;
    document.getElementById('pvActionForm').submit();
}

document.getElementById('pvModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

var pvAlert = document.getElementById('pvAlert');
if (pvAlert) {
    setTimeout(function () {
        pvAlert.style.transition = 'opacity 0.5s';
        pvAlert.style.opacity    = '0';
        setTimeout(function () { pvAlert.remove(); }, 500);
    }, 5000);
}
</script>
</body>
</html>
