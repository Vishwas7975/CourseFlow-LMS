<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

// Auto-create tables
$conn->query("
    CREATE TABLE IF NOT EXISTS final_year_projects (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        title         VARCHAR(200)  NOT NULL,
        description   TEXT,
        domain        VARCHAR(100),
        tech_stack    VARCHAR(300),
        price         DECIMAL(10,2) NOT NULL DEFAULT 0,
        features      TEXT,
        thumbnail_url VARCHAR(500),
        status        ENUM('active','inactive') DEFAULT 'active',
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$conn->query("
    CREATE TABLE IF NOT EXISTS project_orders (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        project_id     INT           NOT NULL,
        student_name   VARCHAR(150)  NOT NULL,
        email          VARCHAR(150)  NOT NULL,
        mobile         VARCHAR(20)   NOT NULL,
        hall_ticket    VARCHAR(100)  NOT NULL,
        college_name   VARCHAR(200)  NOT NULL,
        department     VARCHAR(150)  NOT NULL,
        utr_number     VARCHAR(60)   NOT NULL,
        terminal_name  VARCHAR(60)   DEFAULT 'Terminal 1',
        terminal_upi   VARCHAR(80),
        amount         DECIMAL(10,2) NOT NULL,
        payment_status ENUM('pending','approved','rejected') DEFAULT 'pending',
        admin_note     VARCHAR(500)  DEFAULT NULL,
        ordered_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
        reviewed_at    DATETIME      DEFAULT NULL,
        INDEX idx_project (project_id),
        INDEX idx_status  (payment_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Mark pending notifications for project_order as read
$conn->query("UPDATE notifications SET is_read=1 WHERE type='project_order' AND for_role='admin' AND is_read=0");

// ── Filters ──
$filter     = $_GET['filter']  ?? 'all';
$project_id = isset($_GET['project']) && is_numeric($_GET['project']) ? (int)$_GET['project'] : 0;
$search     = trim($_GET['search'] ?? '');
$msg        = $_GET['msg'] ?? '';

$allowed_filters = ['all','pending','approved','rejected'];
if (!in_array($filter, $allowed_filters)) $filter = 'all';

// Build WHERE
$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($filter !== 'all') {
    $where   .= " AND o.payment_status = ?";
    $params[] = $filter;
    $types   .= "s";
}
if ($project_id > 0) {
    $where   .= " AND o.project_id = ?";
    $params[] = $project_id;
    $types   .= "i";
}
if ($search !== '') {
    $like     = "%$search%";
    $where   .= " AND (o.student_name LIKE ? OR o.email LIKE ? OR o.hall_ticket LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= "sss";
}

$sql = "
    SELECT o.*, p.title AS project_title, p.domain AS project_domain
    FROM project_orders o
    LEFT JOIN final_year_projects p ON p.id = o.project_id
    $where
    ORDER BY o.ordered_at DESC
";

if ($types) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query($sql);
}
$orders = [];
while ($row = $result->fetch_assoc()) $orders[] = $row;

// Stats
$stats_res = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(payment_status='pending')  AS pending_cnt,
    SUM(payment_status='approved') AS approved_cnt,
    SUM(payment_status='rejected') AS rejected_cnt
    FROM project_orders");
$stats = $stats_res ? $stats_res->fetch_assoc() : ['total'=>0,'pending_cnt'=>0,'approved_cnt'=>0,'rejected_cnt'=>0];

// Project filter dropdown
$proj_res = $conn->query("SELECT DISTINCT p.id, p.title FROM final_year_projects p INNER JOIN project_orders o ON o.project_id=p.id ORDER BY p.title");
$proj_list = [];
while ($pr = $proj_res->fetch_assoc()) $proj_list[] = $pr;

// If filtering by project, get its title
$proj_filter_title = '';
if ($project_id > 0) {
    $pft = $conn->prepare("SELECT title FROM final_year_projects WHERE id=?");
    $pft->bind_param("i", $project_id);
    $pft->execute();
    $pft_res = $pft->get_result()->fetch_assoc();
    $proj_filter_title = $pft_res ? $pft_res['title'] : '';
    $pft->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Orders — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        /* ── Stats ── */
        .po-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .pstat {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 18px;
            display: flex; align-items: center; gap: 13px;
            box-shadow: 0 2px 10px rgba(0,0,0,.04);
        }
        .pstat-ico {
            width: 42px; height: 42px; border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem; flex-shrink: 0;
        }
        .pstat-ico.blue   { background: #eff6ff; color: #2563eb; }
        .pstat-ico.yellow { background: #fffbeb; color: #d97706; }
        .pstat-ico.green  { background: #f0fdf4; color: #16a34a; }
        .pstat-ico.red    { background: #fef2f2; color: #dc2626; }
        .pstat-num { font-family: 'Plus Jakarta Sans',sans-serif; font-size: 1.45rem; font-weight: 800; color: #0f172a; line-height: 1; }
        .pstat-lbl { font-size: .72rem; color: #64748b; margin-top: 3px; font-weight: 500; }

        /* ── Tab filters ── */
        .tab-bar {
            display: flex; align-items: center; gap: 6px;
            margin-bottom: 18px; flex-wrap: wrap;
        }
        .tab-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 9px;
            font-size: .82rem; font-weight: 600; text-decoration: none;
            border: 1.5px solid #e2e8f0; color: #475569; background: #fff;
            transition: all .15s;
        }
        .tab-btn:hover { border-color: #93c5fd; color: #1d4ed8; }
        .tab-btn.active { background: #2563eb; border-color: #2563eb; color: #fff; }
        .tab-btn .badge {
            background: rgba(255,255,255,.3); color: inherit;
            font-size: .66rem; font-weight: 800;
            padding: 1px 6px; border-radius: 999px;
            min-width: 18px; text-align: center;
        }
        .tab-btn:not(.active) .badge { background: #f1f5f9; color: #64748b; }
        .tab-btn.tab-pending:not(.active) .badge  { background: #fef9c3; color: #854d0e; }
        .tab-btn.tab-approved:not(.active) .badge { background: #dcfce7; color: #15803d; }
        .tab-btn.tab-rejected:not(.active) .badge { background: #fee2e2; color: #b91c1c; }

        /* ── Toolbar ── */
        .po-toolbar {
            display: flex; align-items: center; gap: 10px;
            flex-wrap: wrap; margin-bottom: 20px;
        }
        .search-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .85rem; }
        .search-wrap input {
            width: 100%; padding: 9px 12px 9px 34px;
            border: 1.5px solid #e2e8f0; border-radius: 9px;
            font-size: .875rem; font-family: 'DM Sans',sans-serif;
            color: #0f172a; background: #fff; outline: none;
            transition: border-color .2s;
        }
        .search-wrap input:focus { border-color: #3b82f6; }
        .filter-select {
            padding: 9px 12px; border: 1.5px solid #e2e8f0; border-radius: 9px;
            font-size: .84rem; font-family: 'DM Sans',sans-serif;
            color: #334155; background: #fff; outline: none; cursor: pointer;
        }

        /* ── Table card ── */
        .po-table-wrap {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
        }
        .po-table-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px; border-bottom: 1.5px solid #f1f5f9;
            background: #fafbff;
        }
        .po-table-head h3 { font-size: .9rem; font-weight: 700; color: #0f172a; margin: 0; }
        .po-table-head span { font-size: .78rem; color: #64748b; }

        table.po-tbl { width: 100%; border-collapse: collapse; }
        table.po-tbl thead th {
            text-align: left; padding: 11px 14px;
            font-size: .69rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; color: #94a3b8;
            background: #f8fafc; border-bottom: 1.5px solid #f1f5f9;
            white-space: nowrap;
        }
        table.po-tbl tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
        table.po-tbl tbody tr:last-child { border-bottom: none; }
        table.po-tbl tbody tr:hover { background: #fafbff; }
        table.po-tbl tbody td { padding: 12px 14px; font-size: .83rem; color: #334155; vertical-align: middle; }

        .student-name { font-weight: 700; color: #0f172a; font-size: .85rem; }
        .student-sub  { font-size: .72rem; color: #64748b; margin-top: 2px; }

        .proj-name-cell { font-weight: 600; color: #1d4ed8; font-size: .83rem; max-width: 180px; }
        .domain-badge {
            display: inline-block; padding: 2px 8px; border-radius: 999px;
            font-size: .66rem; font-weight: 700; margin-top: 3px;
        }
        .db-ai     { background: #f5f3ff; color: #6d28d9; }
        .db-web    { background: #eff6ff; color: #1d4ed8; }
        .db-iot    { background: #f0fdf4; color: #15803d; }
        .db-data   { background: #fff7ed; color: #c2410c; }
        .db-mobile { background: #fdf4ff; color: #a21caf; }
        .db-cyber  { background: #fef2f2; color: #b91c1c; }
        .db-other  { background: #f8fafc; color: #475569; }

        .utr-code {
            font-family: monospace; font-size: .78rem;
            background: #f8fafc; padding: 3px 8px; border-radius: 6px;
            color: #0f172a; border: 1px solid #e2e8f0;
            display: inline-block; max-width: 120px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .amount-cell { font-weight: 800; font-size: .9rem; color: #0f172a; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 999px;
            font-size: .69rem; font-weight: 700; white-space: nowrap;
        }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .s-pending  { background: #fef9c3; color: #854d0e; }
        .s-approved { background: #dcfce7; color: #15803d; }
        .s-rejected { background: #fee2e2; color: #b91c1c; }

        /* Actions */
        .action-btns { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
        .ab {
            display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            padding: 5px 11px; border-radius: 7px;
            font-size: .75rem; font-weight: 600;
            text-decoration: none; transition: all .18s; white-space: nowrap;
            border: none; cursor: pointer; font-family: 'DM Sans',sans-serif;
        }
        .ab-view   { background: #eff6ff; color: #1d4ed8; }
        .ab-view:hover   { background: #dbeafe; }
        .ab-approve{ background: #dcfce7; color: #15803d; }
        .ab-approve:hover{ background: #bbf7d0; }
        .ab-reject { background: #fee2e2; color: #b91c1c; }
        .ab-reject:hover { background: #fecaca; }
        .ab-wa     { background: #f0fdf4; color: #15803d; }
        .ab-wa:hover     { background: #dcfce7; }
        .ab-wa.disabled  { opacity: .4; cursor: default; pointer-events: none; }

        /* Flash */
        .flash {
            display: flex; align-items: center; gap: 9px;
            padding: 12px 16px; border-radius: 10px;
            font-size: .84rem; font-weight: 500; margin-bottom: 18px;
        }
        .flash-success { background: #f0fdf4; border: 1.5px solid #86efac; color: #065f46; }
        .flash-danger  { background: #fef2f2; border: 1.5px solid #fca5a5; color: #7f1d1d; }
        .flash-info    { background: #eff6ff; border: 1.5px solid #93c5fd; color: #1e3a8a; }

        /* Empty */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state-ico {
            width: 68px; height: 68px; border-radius: 18px;
            background: linear-gradient(135deg, #eff6ff, #e0e7ff);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: #3b82f6; margin: 0 auto 16px;
        }
        .empty-state h3 { font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
        .empty-state p  { font-size: .84rem; color: #64748b; }

        /* Filter-by-project pill */
        .proj-filter-pill {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 6px 12px; border-radius: 8px;
            background: #eff6ff; color: #1d4ed8;
            font-size: .8rem; font-weight: 600; margin-bottom: 14px;
        }
        .proj-filter-pill a { color: #94a3b8; text-decoration: none; margin-left: 4px; }
        .proj-filter-pill a:hover { color: #dc2626; }

        /* ── Detail Modal ── */
        .detail-modal {
            display: none; position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,.45);
            align-items: center; justify-content: center;
            padding: 20px;
        }
        .detail-modal.open { display: flex; }
        .detail-modal-box {
            background: #fff; border-radius: 18px;
            width: 560px; max-width: 98vw; max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,.18);
            animation: modalIn .2s ease;
        }
        @keyframes modalIn { from{transform:scale(.94);opacity:0} to{transform:scale(1);opacity:1} }
        .dm-head {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            padding: 18px 22px; color: #fff;
            display: flex; align-items: center; justify-content: space-between;
            border-radius: 18px 18px 0 0; position: sticky; top: 0; z-index: 2;
        }
        .dm-head h3 { font-size: .95rem; font-weight: 700; margin: 0; }
        .dm-close { background: rgba(255,255,255,.2); border: none; color: #fff; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-size: .9rem; display:flex;align-items:center;justify-content:center; }
        .dm-body { padding: 22px; }
        .dm-section { margin-bottom: 20px; }
        .dm-section h4 { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; margin-bottom: 10px; }
        .dm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .dm-field label { font-size: .72rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 2px; }
        .dm-field span  { font-size: .84rem; color: #0f172a; font-weight: 500; }
        .dm-full { grid-column: 1 / -1; }
        .dm-utr span { font-family: monospace; font-weight: 700; background: #f8fafc; padding: 3px 8px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .dm-note { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 9px; padding: 10px 13px; font-size: .83rem; color: #7f1d1d; }
        .dm-actions { display: flex; gap: 9px; flex-wrap: wrap; padding: 16px 22px; border-top: 1.5px solid #f1f5f9; }
        .dm-btn {
            flex: 1; min-width: 110px; padding: 10px;
            border-radius: 9px; border: none; cursor: pointer;
            font-family: 'DM Sans',sans-serif; font-size: .84rem; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            text-decoration: none; transition: all .2s;
        }
        .dm-btn-approve { background: linear-gradient(135deg,#16a34a,#22c55e); color:#fff; }
        .dm-btn-approve:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(22,163,74,.3); }
        .dm-btn-reject  { background: #fee2e2; color: #b91c1c; }
        .dm-btn-reject:hover { background: #fecaca; }
        .dm-btn-wa      { background: #f0fdf4; color: #15803d; }
        .dm-btn-wa:hover{ background: #dcfce7; }
        .dm-btn-close   { background: #f1f5f9; color: #475569; }

        /* ── Reject modal ── */
        .reject-modal {
            display: none; position: fixed; inset: 0; z-index: 10000;
            background: rgba(0,0,0,.5);
            align-items: center; justify-content: center; padding: 20px;
        }
        .reject-modal.open { display: flex; }
        .reject-modal-box {
            background: #fff; border-radius: 16px; width: 420px; max-width: 96vw;
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
            animation: modalIn .2s ease; overflow: hidden;
        }
        .rm-head {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            padding: 16px 20px; color: #fff;
            display: flex; align-items: center; gap: 10px;
        }
        .rm-head i { font-size: 1rem; }
        .rm-head h3 { margin: 0; font-size: .9rem; font-weight: 700; }
        .rm-body { padding: 18px 20px; }
        .rm-body label { font-size: .8rem; font-weight: 600; color: #334155; display: block; margin-bottom: 6px; }
        .rm-body textarea {
            width: 100%; padding: 10px; border: 1.5px solid #e2e8f0;
            border-radius: 9px; font-family: 'DM Sans',sans-serif; font-size: .84rem;
            color: #0f172a; resize: vertical; min-height: 90px; outline: none;
            transition: border-color .2s; box-sizing: border-box;
        }
        .rm-body textarea:focus { border-color: #ef4444; }
        .rm-actions { display: flex; gap: 10px; padding: 0 20px 18px; }
        .rm-btn-confirm {
            flex: 1; padding: 10px; border-radius: 9px;
            background: linear-gradient(135deg,#dc2626,#ef4444);
            color: #fff; border: none; font-weight: 700; font-size: .875rem;
            cursor: pointer; font-family: 'DM Sans',sans-serif;
            display:flex;align-items:center;justify-content:center;gap:6px;
        }
        .rm-btn-cancel {
            flex: 1; padding: 10px; border-radius: 9px;
            background: #f1f5f9; color: #475569;
            border: none; font-weight: 600; font-size: .875rem;
            cursor: pointer; font-family: 'DM Sans',sans-serif;
        }

        @media (max-width: 900px) {
            .po-stats { grid-template-columns: repeat(2,1fr); }
            table.po-tbl thead th:nth-child(5),
            table.po-tbl tbody td:nth-child(5) { display: none; }
        }
        @media (max-width: 600px) {
            .po-stats { grid-template-columns: repeat(2,1fr); }
            .po-toolbar { flex-direction: column; align-items: stretch; }
            .tab-bar { gap: 4px; }
            .tab-btn { font-size: .76rem; padding: 6px 11px; }
            table.po-tbl thead th:nth-child(3),
            table.po-tbl tbody td:nth-child(3),
            table.po-tbl thead th:nth-child(6),
            table.po-tbl tbody td:nth-child(6) { display: none; }
            .dm-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- Header -->
            <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
                <div>
                    <h1><i class="fas fa-shopping-bag"></i> Project Orders</h1>
                    <p>Review, approve or reject student project purchase submissions.</p>
                </div>
                <a href="manage_projects.php" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:7px;">
                    <i class="fas fa-folder-open"></i> Manage Projects
                </a>
            </div>

            <!-- Flash -->
            <?php if ($msg === 'approved'): ?>
            <div class="flash flash-success"><i class="fas fa-check-circle"></i> Order approved successfully.</div>
            <?php elseif ($msg === 'rejected'): ?>
            <div class="flash flash-danger"><i class="fas fa-times-circle"></i> Order rejected and note saved.</div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="po-stats">
                <div class="pstat">
                    <div class="pstat-ico blue"><i class="fas fa-shopping-bag"></i></div>
                    <div><div class="pstat-num"><?= (int)($stats['total']??0) ?></div><div class="pstat-lbl">Total Orders</div></div>
                </div>
                <div class="pstat">
                    <div class="pstat-ico yellow"><i class="fas fa-clock"></i></div>
                    <div><div class="pstat-num"><?= (int)($stats['pending_cnt']??0) ?></div><div class="pstat-lbl">Pending</div></div>
                </div>
                <div class="pstat">
                    <div class="pstat-ico green"><i class="fas fa-check-circle"></i></div>
                    <div><div class="pstat-num"><?= (int)($stats['approved_cnt']??0) ?></div><div class="pstat-lbl">Approved</div></div>
                </div>
                <div class="pstat">
                    <div class="pstat-ico red"><i class="fas fa-times-circle"></i></div>
                    <div><div class="pstat-num"><?= (int)($stats['rejected_cnt']??0) ?></div><div class="pstat-lbl">Rejected</div></div>
                </div>
            </div>

            <!-- Tab filters -->
            <?php
            $base = 'project_orders.php';
            $pid_qs = $project_id > 0 ? "&project=$project_id" : "";
            ?>
            <div class="tab-bar">
                <a href="<?= $base ?>?filter=all<?= $pid_qs ?>" class="tab-btn <?= $filter==='all'?'active':'' ?>">
                    <i class="fas fa-list"></i> All
                    <span class="badge"><?= (int)($stats['total']??0) ?></span>
                </a>
                <a href="<?= $base ?>?filter=pending<?= $pid_qs ?>" class="tab-btn tab-pending <?= $filter==='pending'?'active':'' ?>">
                    <i class="fas fa-clock"></i> Pending
                    <span class="badge"><?= (int)($stats['pending_cnt']??0) ?></span>
                </a>
                <a href="<?= $base ?>?filter=approved<?= $pid_qs ?>" class="tab-btn tab-approved <?= $filter==='approved'?'active':'' ?>">
                    <i class="fas fa-check-circle"></i> Approved
                    <span class="badge"><?= (int)($stats['approved_cnt']??0) ?></span>
                </a>
                <a href="<?= $base ?>?filter=rejected<?= $pid_qs ?>" class="tab-btn tab-rejected <?= $filter==='rejected'?'active':'' ?>">
                    <i class="fas fa-times-circle"></i> Rejected
                    <span class="badge"><?= (int)($stats['rejected_cnt']??0) ?></span>
                </a>
            </div>

            <!-- Project filter pill -->
            <?php if ($project_id > 0 && $proj_filter_title): ?>
            <div class="proj-filter-pill">
                <i class="fas fa-filter"></i>
                Filtered by: <strong><?= htmlspecialchars($proj_filter_title) ?></strong>
                <a href="project_orders.php?filter=<?= $filter ?>"><i class="fas fa-times"></i> Clear</a>
            </div>
            <?php endif; ?>

            <!-- Toolbar -->
            <div class="po-toolbar">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by name, email, hall ticket…"
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <?php if (!empty($proj_list)): ?>
                <select class="filter-select" onchange="filterByProject(this.value)">
                    <option value="">All Projects</option>
                    <?php foreach ($proj_list as $pl): ?>
                    <option value="<?= $pl['id'] ?>" <?= $project_id==$pl['id']?'selected':'' ?>>
                        <?= htmlspecialchars($pl['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <!-- Table -->
            <div class="po-table-wrap">
                <div class="po-table-head">
                    <h3>Orders <span id="visibleCount">(<?= count($orders) ?>)</span></h3>
                    <span><?= date('d M Y') ?></span>
                </div>

                <?php if (!empty($orders)): ?>
                <div style="overflow-x:auto;">
                <table class="po-tbl" id="ordersTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Project</th>
                            <th>Amount / UTR</th>
                            <th>Contact</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $o):
                        $d = strtolower($o['project_domain'] ?? '');
                        $db_cls = str_contains($d,'ai')||str_contains($d,'ml') ? 'db-ai'
                            : (str_contains($d,'web')    ? 'db-web'
                            : (str_contains($d,'iot')    ? 'db-iot'
                            : (str_contains($d,'data')   ? 'db-data'
                            : (str_contains($d,'mobile') ? 'db-mobile'
                            : (str_contains($d,'cyber')  ? 'db-cyber'
                            : 'db-other')))));
                        $wa_text = urlencode("Hi " . $o['student_name'] . ", your order for " . ($o['project_title'] ?? 'the project') . " has been approved by CourseFlow. We will send your project files shortly. Thank you!");
                        $wa_link = "https://wa.me/91" . preg_replace('/[^0-9]/','',$o['mobile']) . "?text=" . $wa_text;
                        // JSON-encode for modal
                        $o_json = htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="order-row"
                        data-name="<?= htmlspecialchars(strtolower($o['student_name'])) ?>"
                        data-email="<?= htmlspecialchars(strtolower($o['email'])) ?>"
                        data-hticket="<?= htmlspecialchars(strtolower($o['hall_ticket'])) ?>">
                        <td>
                            <div class="student-name"><?= htmlspecialchars($o['student_name']) ?></div>
                            <div class="student-sub"><?= htmlspecialchars($o['college_name']) ?></div>
                            <div class="student-sub"><?= htmlspecialchars($o['department']) ?></div>
                        </td>
                        <td>
                            <div class="proj-name-cell"><?= htmlspecialchars($o['project_title'] ?? '—') ?></div>
                            <?php if (!empty($o['project_domain'])): ?>
                            <span class="domain-badge <?= $db_cls ?>"><?= htmlspecialchars($o['project_domain']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="amount-cell">₹<?= number_format((float)$o['amount'],0) ?></div>
                            <div style="margin-top:4px;">
                                <span class="utr-code" title="<?= htmlspecialchars($o['utr_number']) ?>"><?= htmlspecialchars($o['utr_number']) ?></span>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:.82rem;color:#0f172a;font-weight:600;"><?= htmlspecialchars($o['mobile']) ?></div>
                            <div class="student-sub"><?= htmlspecialchars($o['email']) ?></div>
                        </td>
                        <td style="color:#64748b;font-size:.78rem;white-space:nowrap;">
                            <?= date('d M Y', strtotime($o['ordered_at'])) ?><br>
                            <span style="font-size:.7rem;"><?= date('h:i A', strtotime($o['ordered_at'])) ?></span>
                        </td>
                        <td>
                            <span class="status-badge s-<?= $o['payment_status'] ?>">
                                <span class="status-dot"></span>
                                <?= ucfirst($o['payment_status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="ab ab-view" onclick='openDetail(<?= $o_json ?>)'>
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <?php if ($o['payment_status'] === 'pending'): ?>
                                <form method="POST" action="approve_project_order.php" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                    <button type="submit" class="ab ab-approve" onclick="return confirm('Approve this order?')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <button class="ab ab-reject" onclick="openReject(<?= (int)$o['id'] ?>, '<?= addslashes(htmlspecialchars($o['student_name'])) ?>')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                <?php endif; ?>
                                <?php if ($o['payment_status'] === 'approved'): ?>
                                <a href="<?= $wa_link ?>" target="_blank" class="ab ab-wa" title="WhatsApp">
                                    <i class="fab fa-whatsapp"></i> WA
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-ico"><i class="fas fa-shopping-bag"></i></div>
                    <h3>No orders found</h3>
                    <p><?= $filter !== 'all' ? "No $filter orders yet." : "No project orders submitted yet." ?></p>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /content-wrapper -->
    </div><!-- /main -->
</div><!-- /dashboard -->

<!-- ── Detail Modal ── -->
<div class="detail-modal" id="detailModal">
    <div class="detail-modal-box">
        <div class="dm-head">
            <h3><i class="fas fa-file-alt" style="margin-right:8px;"></i> Order Details</h3>
            <button class="dm-close" onclick="closeDetail()"><i class="fas fa-times"></i></button>
        </div>
        <div class="dm-body">
            <div class="dm-section">
                <h4>Student Information</h4>
                <div class="dm-grid">
                    <div class="dm-field"><label>Full Name</label><span id="dm-name">—</span></div>
                    <div class="dm-field"><label>Mobile</label><span id="dm-mobile">—</span></div>
                    <div class="dm-field dm-full"><label>Email</label><span id="dm-email">—</span></div>
                    <div class="dm-field"><label>Hall Ticket</label><span id="dm-hticket">—</span></div>
                    <div class="dm-field"><label>Department</label><span id="dm-dept">—</span></div>
                    <div class="dm-field dm-full"><label>College</label><span id="dm-college">—</span></div>
                </div>
            </div>
            <div class="dm-section">
                <h4>Order Information</h4>
                <div class="dm-grid">
                    <div class="dm-field dm-full"><label>Project</label><span id="dm-project">—</span></div>
                    <div class="dm-field"><label>Amount Paid</label><span id="dm-amount">—</span></div>
                    <div class="dm-field"><label>Terminal</label><span id="dm-terminal">—</span></div>
                    <div class="dm-field dm-utr dm-full"><label>UTR / Transaction Ref</label><span id="dm-utr">—</span></div>
                    <div class="dm-field"><label>Status</label><span id="dm-status">—</span></div>
                    <div class="dm-field"><label>Submitted At</label><span id="dm-date">—</span></div>
                </div>
            </div>
            <div class="dm-section" id="dm-note-section" style="display:none;">
                <h4>Admin Note</h4>
                <div class="dm-note" id="dm-note">—</div>
            </div>
        </div>
        <div class="dm-actions" id="dm-actions">
            <!-- filled by JS -->
        </div>
    </div>
</div>

<!-- ── Reject Modal ── -->
<div class="reject-modal" id="rejectModal">
    <div class="reject-modal-box">
        <div class="rm-head">
            <i class="fas fa-times-circle"></i>
            <h3>Reject Order — <span id="rm-student-name"></span></h3>
        </div>
        <form method="POST" action="reject_project_order.php">
            <input type="hidden" name="id" id="rm-order-id">
            <div class="rm-body">
                <label>Reason / Admin Note (optional)</label>
                <textarea name="admin_note" placeholder="e.g. UTR not found, invalid payment…"></textarea>
            </div>
            <div class="rm-actions">
                <button type="submit" class="rm-btn-confirm"><i class="fas fa-times"></i> Confirm Reject</button>
                <button type="button" class="rm-btn-cancel" onclick="closeReject()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php include("../includes/footer.php"); ?>

<script>
// ── Search filter ──
const searchInput = document.getElementById('searchInput');
const rows = document.querySelectorAll('.order-row');
const visibleCount = document.getElementById('visibleCount');

function filterRows() {
    const q = searchInput.value.toLowerCase().trim();
    let visible = 0;
    rows.forEach(row => {
        const name    = row.dataset.name    || '';
        const email   = row.dataset.email   || '';
        const hticket = row.dataset.hticket || '';
        const match   = !q || name.includes(q) || email.includes(q) || hticket.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    visibleCount.textContent = '(' + visible + ')';
}
searchInput.addEventListener('input', filterRows);

function filterByProject(val) {
    const url = new URL(window.location.href);
    if (val) url.searchParams.set('project', val);
    else url.searchParams.delete('project');
    window.location.href = url.toString();
}

// ── Detail Modal ──
function openDetail(o) {
    document.getElementById('dm-name').textContent    = o.student_name  || '—';
    document.getElementById('dm-mobile').textContent  = o.mobile        || '—';
    document.getElementById('dm-email').textContent   = o.email         || '—';
    document.getElementById('dm-hticket').textContent = o.hall_ticket   || '—';
    document.getElementById('dm-dept').textContent    = o.department    || '—';
    document.getElementById('dm-college').textContent = o.college_name  || '—';
    document.getElementById('dm-project').textContent = o.project_title || '—';
    document.getElementById('dm-amount').textContent  = '₹' + parseFloat(o.amount).toLocaleString('en-IN');
    document.getElementById('dm-terminal').textContent= o.terminal_name + (o.terminal_upi ? ' (' + o.terminal_upi + ')' : '');
    document.getElementById('dm-utr').textContent     = o.utr_number    || '—';
    document.getElementById('dm-status').innerHTML    = '<span class="status-badge s-' + o.payment_status + '"><span class="status-dot"></span>' + o.payment_status.charAt(0).toUpperCase() + o.payment_status.slice(1) + '</span>';
    document.getElementById('dm-date').textContent    = o.ordered_at    || '—';

    const noteSection = document.getElementById('dm-note-section');
    if (o.admin_note) {
        document.getElementById('dm-note').textContent = o.admin_note;
        noteSection.style.display = '';
    } else {
        noteSection.style.display = 'none';
    }

    // Actions
    const actDiv = document.getElementById('dm-actions');
    let html = '';
    if (o.payment_status === 'pending') {
        html += `<form method="POST" action="approve_project_order.php" style="flex:1;display:contents;">
                    <input type="hidden" name="id" value="${o.id}">
                    <button type="submit" class="dm-btn dm-btn-approve" onclick="return confirm('Approve this order?')"><i class="fas fa-check"></i> Approve</button>
                 </form>`;
        html += `<button class="dm-btn dm-btn-reject" onclick="closeDetail();openReject(${o.id},'${o.student_name.replace(/'/g,"\\'")}')"><i class="fas fa-times"></i> Reject</button>`;
    }
    if (o.payment_status === 'approved') {
        const waText = encodeURIComponent('Hi ' + o.student_name + ', your order for ' + (o.project_title||'the project') + ' has been approved by CourseFlow. We will send your project files shortly. Thank you!');
        const waLink = 'https://wa.me/91' + o.mobile.replace(/\D/g,'') + '?text=' + waText;
        html += `<a href="${waLink}" target="_blank" class="dm-btn dm-btn-wa"><i class="fab fa-whatsapp"></i> WhatsApp</a>`;
    }
    html += `<button class="dm-btn dm-btn-close" onclick="closeDetail()"><i class="fas fa-times"></i> Close</button>`;
    actDiv.innerHTML = html;

    document.getElementById('detailModal').classList.add('open');
}
function closeDetail() {
    document.getElementById('detailModal').classList.remove('open');
}
document.getElementById('detailModal').addEventListener('click', function(e){ if(e.target===this) closeDetail(); });

// ── Reject Modal ──
function openReject(id, name) {
    document.getElementById('rm-order-id').value = id;
    document.getElementById('rm-student-name').textContent = name;
    document.getElementById('rejectModal').classList.add('open');
}
function closeReject() {
    document.getElementById('rejectModal').classList.remove('open');
}
document.getElementById('rejectModal').addEventListener('click', function(e){ if(e.target===this) closeReject(); });

// Auto-dismiss flash
setTimeout(() => {
    document.querySelectorAll('.flash').forEach(el => {
        el.style.transition = 'opacity .5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 4000);
</script>
</body>
</html>