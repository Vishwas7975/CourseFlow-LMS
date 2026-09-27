<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("student");

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// Success message after redirect from resubmission
if (isset($_GET['resubmitted']) && $_GET['resubmitted'] == '1') {
    $success = "Project resubmitted successfully! Status is back to <strong>Pending</strong> for trainer review.";
}

// ── Handle NEW submission ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_project'])) {

    $title   = trim($_POST['project_title']      ?? '');
    $desc    = trim($_POST['project_description'] ?? '');
    $tech    = trim($_POST['technologies_used']   ?? '');
    $github  = trim($_POST['github_link']         ?? '');
    $youtube = trim($_POST['youtube_link']        ?? '');

    if (!$title || !$desc || !$tech || !$github || !$youtube) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^https?:\/\/(www\.)?github\.com\/.+/i', $github)) {
        $error = "Please enter a valid GitHub URL (e.g. https://github.com/username/repo).";
    } elseif (!preg_match('/^https?:\/\/(www\.)?(youtube\.com\/(watch\?v=|shorts\/)|youtu\.be\/).+/i', $youtube)) {
        $error = "Please enter a valid YouTube URL (e.g. https://www.youtube.com/watch?v=xxxxx).";
    } else {
        $dup = $conn->prepare("SELECT id FROM project_submissions WHERE student_id=? AND project_title=? AND status IN ('Pending','Approved') LIMIT 1");
        $dup->bind_param("is", $user_id, $title);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            $error = "You already submitted a project with this title. Use a different title or wait for trainer review.";
        } else {
            $ins = $conn->prepare("INSERT INTO project_submissions (student_id, project_title, project_description, technologies_used, github_link, youtube_link, status) VALUES (?,?,?,?,?,?,'Pending')");
            $ins->bind_param("isssss", $user_id, $title, $desc, $tech, $github, $youtube);
            if ($ins->execute()) {
                $success = "Project submitted successfully! Status set to <strong>Pending</strong>.";

                // Notify only trainers assigned to this student via enrollments
                $n_title  = "New Project Submitted";
                $n_msg    = htmlspecialchars($_SESSION['name']) . " submitted: \"" . $title . "\"";
                $n_link   = "../trainer/projects.php";
                $n_type   = "project_submit";
                $tr_stmt  = $conn->prepare("SELECT DISTINCT e.trainer_id FROM enrollments e JOIN users u ON u.id=e.trainer_id WHERE e.user_id=? AND e.trainer_id IS NOT NULL AND u.status='active'");
                $tr_stmt->bind_param("i", $user_id);
                $tr_stmt->execute();
                $tr_res = $tr_stmt->get_result();
                if ($tr_res && $tr_res->num_rows > 0) {
                    $ns = $conn->prepare("INSERT INTO notifications (type,for_role,for_user_id,title,message,link,is_read) VALUES (?,'trainer',?,?,?,?,0)");
                    while ($tr = $tr_res->fetch_assoc()) {
                        $ns->bind_param("sisss", $n_type, $tr['trainer_id'], $n_title, $n_msg, $n_link);
                        $ns->execute();
                    }
                }
                $title = $desc = $tech = $github = $youtube = '';
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}

// ── Handle RESUBMISSION (edit rejected project) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resubmit_project'])) {

    $pid     = (int)($_POST['project_id']         ?? 0);
    $title   = trim($_POST['project_title']        ?? '');
    $desc    = trim($_POST['project_description']  ?? '');
    $tech    = trim($_POST['technologies_used']    ?? '');
    $github  = trim($_POST['github_link']          ?? '');
    $youtube = trim($_POST['youtube_link']         ?? '');
    $now     = date('Y-m-d H:i:s');

    if (!$pid) {
        $error = "Invalid project.";
    } elseif (!$title || !$desc || !$tech || !$github || !$youtube) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^https?:\/\/(www\.)?github\.com\/.+/i', $github)) {
        $error = "Please enter a valid GitHub URL.";
    } elseif (!preg_match('/^https?:\/\/(www\.)?(youtube\.com\/(watch\?v=|shorts\/)|youtu\.be\/).+/i', $youtube)) {
        $error = "Please enter a valid YouTube URL.";
    } else {
        // Verify project belongs to this student and is Rejected
        $chk = $conn->prepare("SELECT id FROM project_submissions WHERE id=? AND student_id=? AND status='Rejected' LIMIT 1");
        $chk->bind_param("ii", $pid, $user_id);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows === 0) {
            $error = "Invalid project or not eligible for resubmission.";
        } else {
            $upd = $conn->prepare("UPDATE project_submissions SET project_title=?, project_description=?, technologies_used=?, github_link=?, youtube_link=?, status='Pending', reviewed_by=NULL, reviewed_at=NULL, resubmitted_at=? WHERE id=? AND student_id=?");
            $upd->bind_param("ssssssii", $title, $desc, $tech, $github, $youtube, $now, $pid, $user_id);

            if ($upd->execute() && $upd->affected_rows > 0) {
                // Notify only trainers assigned to this student
                $n_title = "Project Resubmitted";
                $n_msg   = htmlspecialchars($_SESSION['name']) . " resubmitted: \"" . $title . "\"";
                $n_link  = "../trainer/projects.php";
                $n_type  = "project_submit";
                $tr_stmt = $conn->prepare("SELECT DISTINCT e.trainer_id FROM enrollments e JOIN users u ON u.id=e.trainer_id WHERE e.user_id=? AND e.trainer_id IS NOT NULL AND u.status='active'");
                $tr_stmt->bind_param("i", $user_id);
                $tr_stmt->execute();
                $tr_res = $tr_stmt->get_result();
                if ($tr_res && $tr_res->num_rows > 0) {
                    $ns = $conn->prepare("INSERT INTO notifications (type,for_role,for_user_id,title,message,link,is_read) VALUES (?,'trainer',?,?,?,?,0)");
                    while ($tr = $tr_res->fetch_assoc()) {
                        $ns->bind_param("sisss", $n_type, $tr['trainer_id'], $n_title, $n_msg, $n_link);
                        $ns->execute();
                    }
                }
                // Redirect to clear POST and show updated table
                header("Location: project_submission.php?resubmitted=1");
                exit();
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}

// ── Fetch edit project (when student clicks Edit on a rejected project) ───────
$edit_project = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $epid = (int)$_GET['edit'];
    $ep   = $conn->prepare("SELECT * FROM project_submissions WHERE id=? AND student_id=? AND status='Rejected' LIMIT 1");
    $ep->bind_param("ii", $epid, $user_id);
    $ep->execute();
    $edit_project = $ep->get_result()->fetch_assoc();
}

// ── Fetch all submissions ─────────────────────────────────────────────────────
$subs_stmt = $conn->prepare("SELECT * FROM project_submissions WHERE student_id=? ORDER BY submitted_at DESC");
$subs_stmt->bind_param("i", $user_id);
$subs_stmt->execute();
$submissions = $subs_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Submission – CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        .ps-wrap {
            background: var(--card); border-radius: var(--radius-xl);
            border: 1px solid var(--border); box-shadow: var(--shadow-sm);
            padding: 32px; margin-bottom: 28px;
        }
        .ps-section-title {
            font-size: 1rem; font-weight: 800; color: var(--text-primary);
            margin-bottom: 24px; display: flex; align-items: center; gap: 10px;
        }
        .ps-icon-box {
            width: 36px; height: 36px; border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem; color: #fff; flex-shrink: 0;
        }
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .ps-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
        .ps-field label { font-size: 0.82rem; font-weight: 700; color: var(--text-secondary); }
        .ps-field label span { color: var(--danger); }
        .ps-field input, .ps-field textarea {
            border: 1.5px solid var(--border); border-radius: var(--radius-md);
            padding: 10px 14px; font-size: 0.9rem; color: var(--text-primary);
            background: var(--bg); outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%; font-family: 'DM Sans', sans-serif;
        }
        .ps-field input:focus, .ps-field textarea:focus {
            border-color: var(--primary); background: #fff;
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        .ps-field textarea { resize: vertical; min-height: 100px; }
        .ps-field .hint { font-size: 0.76rem; color: var(--text-muted); }
        .url-wrap { position: relative; }
        .url-wrap input { padding-right: 36px; }
        .url-status-icon {
            position: absolute; right: 11px; top: 50%;
            transform: translateY(-50%); font-size: 0.85rem;
            display: none; pointer-events: none;
        }
        /* Alerts */
        .ps-alert {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px 18px; border-radius: var(--radius-md);
            font-size: 0.88rem; margin-bottom: 22px;
        }
        .ps-alert.ok  { background: #DCFCE7; border: 1px solid #86EFAC; color: #166534; }
        .ps-alert.err { background: #FEE2E2; border: 1px solid #FCA5A5; color: #991B1B; }
        .ps-alert i { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
        /* Rejected banner on edit form */
        .rejected-notice {
            background: #FFF7ED; border: 1.5px solid #FED7AA;
            border-radius: var(--radius-md); padding: 14px 18px;
            margin-bottom: 22px; display: flex; align-items: flex-start; gap: 12px;
        }
        .rejected-notice i { color: #EA580C; font-size: 1rem; flex-shrink: 0; margin-top: 2px; }
        .rejected-notice strong { color: #9A3412; }
        .rejected-notice p { font-size: 0.85rem; color: #7C2D12; margin: 0; }
        /* Buttons */
        .ps-submit {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; border: none; border-radius: var(--radius-md);
            font-size: 0.9rem; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; box-shadow: var(--shadow-blue);
            transition: opacity 0.2s, transform 0.2s;
        }
        .ps-submit:hover { opacity: 0.9; transform: translateY(-1px); }
        .ps-resubmit {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 28px;
            background: linear-gradient(135deg, #EA580C, #C2410C);
            color: #fff; border: none; border-radius: var(--radius-md);
            font-size: 0.9rem; font-weight: 700; font-family: 'DM Sans', sans-serif;
            cursor: pointer; box-shadow: 0 4px 14px rgba(234,88,12,0.35);
            transition: opacity 0.2s, transform 0.2s;
        }
        .ps-resubmit:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-edit-sm {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; font-size: 0.78rem; font-weight: 700;
            background: #FFF7ED; color: #EA580C;
            border: 1.5px solid #FED7AA; border-radius: var(--radius-md);
            cursor: pointer; text-decoration: none; transition: all 0.2s;
        }
        .btn-edit-sm:hover { background: #EA580C; color: #fff; border-color: #EA580C; }
        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 12px; border-radius: var(--radius-full);
            font-size: 0.75rem; font-weight: 700;
        }
        .badge-pending   { background: #FEF3C7; color: #92400E; }
        .badge-approved  { background: #DCFCE7; color: #166534; }
        .badge-rejected  { background: #FEE2E2; color: #991B1B; }
        .badge-completed { background: #EDE9FE; color: #5B21B6; }
        /* Table */
        .ps-table { width: 100%; border-collapse: collapse; }
        .ps-table th {
            background: var(--bg-alt); font-size: 0.73rem; font-weight: 700;
            color: var(--text-secondary); text-transform: uppercase;
            letter-spacing: 0.5px; padding: 11px 14px; text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .ps-table td {
            padding: 13px 14px; font-size: 0.875rem;
            border-bottom: 1px solid var(--border-light); vertical-align: top;
        }
        .ps-table tr:last-child td { border-bottom: none; }
        .ps-table tr:hover td { background: var(--bg); }
        .ps-link {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.82rem; font-weight: 600;
            color: var(--primary-dark); margin-bottom: 4px;
        }
        .ps-link:hover { color: var(--primary); }
        .tech-chip {
            display: inline-block;
            background: var(--primary-light); color: var(--primary-dark);
            border-radius: var(--radius-full); padding: 2px 9px;
            font-size: 0.72rem; font-weight: 600; margin: 2px 2px 2px 0;
        }
        .empty-box {
            text-align: center; padding: 44px 20px;
            color: var(--text-muted); font-size: 0.9rem;
        }
        .empty-box i { font-size: 2.4rem; display: block; margin-bottom: 10px; }
        /* Resubmit highlight row */
        .ps-table tr.row-rejected td { background: #FFF8F6; }
        @media (max-width: 700px) {
            .form-row-2 { grid-template-columns: 1fr; }
            .ps-wrap { padding: 18px; }
            .ps-table { display: block; overflow-x: auto; }
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
            <div style="background:linear-gradient(135deg,#7C3AED,#9D5CF6);border-radius:var(--radius-xl);padding:26px 32px;color:#fff;margin-bottom:28px;position:relative;overflow:hidden">
                <div style="position:absolute;top:-40px;right:-40px;width:160px;height:160px;background:rgba(255,255,255,0.07);border-radius:50%"></div>
                <h2 style="color:#fff;font-size:1.35rem;margin-bottom:4px">
                    <i class="fas fa-paper-plane" style="margin-right:8px"></i>Project Submission
                </h2>
                <p style="color:rgba(255,255,255,0.78);font-size:0.88rem">
                    Submit your project for trainer review. If rejected, you can edit and resubmit.
                </p>
            </div>

            <!-- ALERTS -->
            <?php if ($success): ?>
            <div class="ps-alert ok" id="psAlert">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="ps-alert err">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
            <?php endif; ?>

            <?php if ($edit_project): ?>
            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- RESUBMISSION FORM (shown when student clicks Edit on rejected) -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div class="ps-wrap" style="border-color:#FED7AA">
                <div class="ps-section-title">
                    <span class="ps-icon-box" style="background:linear-gradient(135deg,#EA580C,#C2410C)">
                        <i class="fas fa-redo"></i>
                    </span>
                    Edit &amp; Resubmit Project
                </div>

                <div class="rejected-notice">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>This project was rejected.</strong>
                        <?php if (!empty($edit_project['rejection_note'])): ?>
                        <p style="margin-top:6px">
                            <strong>Trainer's reason:</strong>
                            <?php echo htmlspecialchars($edit_project['rejection_note']); ?>
                        </p>
                        <?php endif; ?>
                        <p style="margin-top:6px">Update your project details below and click <strong>Resubmit</strong>. The status will go back to <strong>Pending</strong> for trainer review.</p>
                    </div>
                </div>

                <form method="POST" action="project_submission.php">
                    <input type="hidden" name="project_id" value="<?php echo (int)$edit_project['id']; ?>">

                    <div class="ps-field">
                        <label for="re_title">Project Title <span>*</span></label>
                        <input type="text" id="re_title" name="project_title"
                               value="<?php echo htmlspecialchars($edit_project['project_title']); ?>"
                               maxlength="255">
                    </div>

                    <div class="ps-field">
                        <label for="re_desc">Project Description <span>*</span></label>
                        <textarea id="re_desc" name="project_description" rows="4"><?php echo htmlspecialchars($edit_project['project_description']); ?></textarea>
                    </div>

                    <div class="ps-field">
                        <label for="re_tech">Technologies Used <span>*</span></label>
                        <input type="text" id="re_tech" name="technologies_used"
                               value="<?php echo htmlspecialchars($edit_project['technologies_used']); ?>">
                        <span class="hint"><i class="fas fa-info-circle"></i> Separate technologies with commas</span>
                    </div>

                    <div class="form-row-2">
                        <div class="ps-field">
                            <label for="re_github">GitHub Repository Link <span>*</span></label>
                            <div class="url-wrap">
                                <input type="text" id="re_github" name="github_link"
                                       value="<?php echo htmlspecialchars($edit_project['github_link']); ?>">
                                <i class="fas fa-check-circle url-status-icon" id="reGhOk"  style="color:#22C55E"></i>
                                <i class="fas fa-times-circle url-status-icon" id="reGhErr" style="color:#EF4444"></i>
                            </div>
                            <span class="hint"><i class="fab fa-github"></i> Must start with https://github.com/</span>
                        </div>
                        <div class="ps-field">
                            <label for="re_youtube">YouTube Demo Video Link <span>*</span></label>
                            <div class="url-wrap">
                                <input type="text" id="re_youtube" name="youtube_link"
                                       value="<?php echo htmlspecialchars($edit_project['youtube_link']); ?>">
                                <i class="fas fa-check-circle url-status-icon" id="reYtOk"  style="color:#22C55E"></i>
                                <i class="fas fa-times-circle url-status-icon" id="reYtErr" style="color:#EF4444"></i>
                            </div>
                            <span class="hint"><i class="fab fa-youtube"></i> Must be a valid YouTube URL</span>
                        </div>
                    </div>

                    <div style="margin-top:6px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                        <button type="submit" name="resubmit_project" value="1" class="ps-resubmit">
                            <i class="fas fa-redo"></i> Resubmit Project
                        </button>
                        <a href="project_submission.php" style="font-size:0.85rem;color:var(--text-muted);text-decoration:none">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

            <?php else: ?>
            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- NEW SUBMISSION FORM                                           -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div class="ps-wrap">
                <div class="ps-section-title">
                    <span class="ps-icon-box" style="background:linear-gradient(135deg,#7C3AED,#9D5CF6)">
                        <i class="fas fa-upload"></i>
                    </span>
                    Submit a New Project
                </div>

                <form method="POST" action="project_submission.php">

                    <div class="ps-field">
                        <label for="project_title">Project Title <span>*</span></label>
                        <input type="text" id="project_title" name="project_title"
                               placeholder="e.g. E-Commerce Website with React and Node.js"
                               value="<?php echo htmlspecialchars($title ?? ''); ?>"
                               maxlength="255">
                    </div>

                    <div class="ps-field">
                        <label for="project_description">Project Description <span>*</span></label>
                        <textarea id="project_description" name="project_description"
                                  placeholder="Describe your project — what it does, key features, and the problem it solves..."
                                  rows="4"><?php echo htmlspecialchars($desc ?? ''); ?></textarea>
                    </div>

                    <div class="ps-field">
                        <label for="technologies_used">Technologies Used <span>*</span></label>
                        <input type="text" id="technologies_used" name="technologies_used"
                               placeholder="e.g. HTML, CSS, JavaScript, React, MySQL"
                               value="<?php echo htmlspecialchars($tech ?? ''); ?>">
                        <span class="hint"><i class="fas fa-info-circle"></i> Enter all technologies separated by commas</span>
                    </div>

                    <div class="form-row-2">
                        <div class="ps-field">
                            <label for="github_link">GitHub Repository Link <span>*</span></label>
                            <div class="url-wrap">
                                <input type="text" id="github_link" name="github_link"
                                       placeholder="https://github.com/username/repo"
                                       value="<?php echo htmlspecialchars($github ?? ''); ?>">
                                <i class="fas fa-check-circle url-status-icon" id="ghOk"  style="color:#22C55E"></i>
                                <i class="fas fa-times-circle url-status-icon" id="ghErr" style="color:#EF4444"></i>
                            </div>
                            <span class="hint"><i class="fab fa-github"></i> Must start with https://github.com/</span>
                        </div>
                        <div class="ps-field">
                            <label for="youtube_link">YouTube Demo Video Link <span>*</span></label>
                            <div class="url-wrap">
                                <input type="text" id="youtube_link" name="youtube_link"
                                       placeholder="https://www.youtube.com/watch?v=..."
                                       value="<?php echo htmlspecialchars($youtube ?? ''); ?>">
                                <i class="fas fa-check-circle url-status-icon" id="ytOk"  style="color:#22C55E"></i>
                                <i class="fas fa-times-circle url-status-icon" id="ytErr" style="color:#EF4444"></i>
                            </div>
                            <span class="hint"><i class="fab fa-youtube"></i> Must be a valid YouTube video URL</span>
                        </div>
                    </div>

                    <div style="margin-top:6px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                        <button type="submit" name="submit_project" value="1" class="ps-submit">
                            <i class="fas fa-paper-plane"></i> Submit Project
                        </button>
                        <span style="font-size:0.8rem;color:var(--text-muted)">
                            Status will be set to <strong>Pending</strong> after submission
                        </span>
                    </div>

                </form>
            </div>
            <?php endif; ?>

            <!-- MY SUBMITTED PROJECTS -->
            <div class="ps-wrap">
                <div class="ps-section-title">
                    <span class="ps-icon-box" style="background:linear-gradient(135deg,#0EA5E9,#2D7DD2)">
                        <i class="fas fa-folder-open"></i>
                    </span>
                    My Submitted Projects
                </div>

                <?php if ($submissions->num_rows > 0): ?>
                <div style="overflow-x:auto">
                    <table class="ps-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Project Title</th>
                                <th>Technologies</th>
                                <th>Links</th>
                                <th>Submitted On</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; while ($row = $submissions->fetch_assoc()):
                            $s  = $row['status'];
                            $bc = $s === 'Approved' ? 'badge-approved' : ($s === 'Rejected' ? 'badge-rejected' : 'badge-pending');
                            $ic = $s === 'Approved' ? 'fa-check-circle' : ($s === 'Rejected' ? 'fa-times-circle' : 'fa-clock');
                            $techs = array_filter(array_map('trim', explode(',', $row['technologies_used'])));
                        ?>
                        <tr class="<?php echo $s === 'Rejected' ? 'row-rejected' : ''; ?>">
                            <td style="font-weight:700;color:var(--text-muted)"><?php echo $i++; ?></td>
                            <td>
                                <div style="font-weight:700;font-size:0.9rem;margin-bottom:3px">
                                    <?php echo htmlspecialchars($row['project_title']); ?>
                                </div>
                                <div style="font-size:0.78rem;color:var(--text-muted);max-width:220px;line-height:1.5">
                                    <?php
                                    $d = $row['project_description'];
                                    echo htmlspecialchars(mb_strlen($d) > 75 ? mb_substr($d,0,75).'…' : $d);
                                    ?>
                                </div>
                            </td>
                            <td>
                                <?php foreach ($techs as $t): ?>
                                <span class="tech-chip"><?php echo htmlspecialchars($t); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td style="white-space:nowrap">
                                <a href="<?php echo htmlspecialchars($row['github_link']); ?>"
                                   target="_blank" rel="noopener" class="ps-link" style="display:block">
                                    <i class="fab fa-github"></i> GitHub
                                </a>
                                <a href="<?php echo htmlspecialchars($row['youtube_link']); ?>"
                                   target="_blank" rel="noopener" class="ps-link" style="color:#DC2626">
                                    <i class="fab fa-youtube"></i> YouTube
                                </a>
                            </td>
                            <td style="font-size:0.8rem;color:var(--text-muted);white-space:nowrap">
                                <?php echo date('d M Y', strtotime($row['submitted_at'])); ?><br>
                                <span style="font-size:0.72rem"><?php echo date('h:i A', strtotime($row['submitted_at'])); ?></span>
                                <?php if (!empty($row['resubmitted_at'])): ?>
                                <div style="font-size:0.68rem;color:#EA580C;margin-top:2px">
                                    <i class="fas fa-redo"></i> Resubmitted <?php echo date('d M Y', strtotime($row['resubmitted_at'])); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $bc; ?>">
                                    <i class="fas <?php echo $ic; ?>"></i> <?php echo $s; ?>
                                </span>
                                <?php if ($s === 'Rejected' && !empty($row['rejection_note'])): ?>
                                <div style="margin-top:6px;background:#FFF5F5;border:1px solid #FCA5A5;border-radius:6px;padding:6px 10px;font-size:0.75rem;color:#991B1B;max-width:200px;line-height:1.5">
                                    <i class="fas fa-comment-alt" style="margin-right:3px"></i>
                                    <strong>Reason:</strong> <?php echo htmlspecialchars($row['rejection_note']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($s === 'Approved' && !empty($row['completion_status'])): ?>
                                <div style="margin-top:5px">
                                    <span class="badge badge-completed">
                                        <i class="fas fa-award"></i> <?php echo $row['completion_status']; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if ($row['reviewed_at']): ?>
                                <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px">
                                    Reviewed <?php echo date('d M Y', strtotime($row['reviewed_at'])); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($s === 'Rejected'): ?>
                                <a href="project_submission.php?edit=<?php echo (int)$row['id']; ?>"
                                   class="btn-edit-sm">
                                    <i class="fas fa-edit"></i> Edit &amp; Resubmit
                                </a>
                                <?php else: ?>
                                <span style="font-size:0.78rem;color:var(--text-muted)">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-box">
                    <i class="fas fa-inbox"></i>
                    No projects submitted yet.<br>
                    <span style="font-size:0.82rem">Use the form above to submit your first project!</span>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>

<script>
function liveUrlCheck(inputId, okId, errId, pattern) {
    var input = document.getElementById(inputId);
    var ok    = document.getElementById(okId);
    var err   = document.getElementById(errId);
    if (!input) return;
    input.addEventListener('input', function () {
        var val = this.value.trim();
        if (!val) { ok.style.display='none'; err.style.display='none'; this.style.borderColor=''; return; }
        if (pattern.test(val)) {
            ok.style.display='block'; err.style.display='none'; this.style.borderColor='#22C55E';
        } else {
            ok.style.display='none'; err.style.display='block'; this.style.borderColor='#EF4444';
        }
    });
}
var ghPat = /^https?:\/\/(www\.)?github\.com\/.+/i;
var ytPat = /^https?:\/\/(www\.)?(youtube\.com\/(watch\?v=|shorts\/)|youtu\.be\/).+/i;
liveUrlCheck('github_link',  'ghOk',   'ghErr',  ghPat);
liveUrlCheck('youtube_link', 'ytOk',   'ytErr',  ytPat);
liveUrlCheck('re_github',    'reGhOk', 'reGhErr', ghPat);
liveUrlCheck('re_youtube',   'reYtOk', 'reYtErr', ytPat);

var psAlert = document.getElementById('psAlert');
if (psAlert) {
    setTimeout(function () {
        psAlert.style.transition = 'opacity 0.5s';
        psAlert.style.opacity = '0';
        setTimeout(function () { psAlert.remove(); }, 500);
    }, 5000);
}
</script>
</body>
</html>
