<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

// Auto-create tables (safe / idempotent)
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

// ── Handle inline toggle status ──
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $tid  = (int)$_GET['toggle'];
    $stmt = $conn->prepare("UPDATE final_year_projects SET status = IF(status='active','inactive','active') WHERE id=?");
    $stmt->bind_param("i", $tid);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_projects.php?msg=toggled"); exit();
}

// ── Handle delete ──
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did  = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM final_year_projects WHERE id=?");
    $stmt->bind_param("i", $did);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_projects.php?msg=deleted"); exit();
}

// ── Fetch all projects with order counts ──
$search = trim($_GET['search'] ?? '');
$domain_filter = trim($_GET['domain'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types  = "";

if ($search !== '') {
    $where   .= " AND (p.title LIKE ? OR p.tech_stack LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}
if ($domain_filter !== '') {
    $where   .= " AND p.domain = ?";
    $params[] = $domain_filter;
    $types   .= "s";
}

$sql = "
    SELECT p.*,
        COUNT(o.id)                                          AS total_orders,
        SUM(o.payment_status = 'pending')                    AS pending_orders,
        SUM(o.payment_status = 'approved')                   AS approved_orders
    FROM final_year_projects p
    LEFT JOIN project_orders o ON o.project_id = p.id
    $where
    GROUP BY p.id
    ORDER BY p.created_at DESC
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

// Stats
$stats_res     = $conn->query("SELECT COUNT(*) AS total, SUM(status='active') AS active_cnt, SUM(status='inactive') AS inactive_cnt FROM final_year_projects");
$stats         = $stats_res ? $stats_res->fetch_assoc() : ['total'=>0,'active_cnt'=>0,'inactive_cnt'=>0];
$pending_cnt_r = $conn->query("SELECT COUNT(*) AS c FROM project_orders WHERE payment_status='pending'");
$pending_cnt   = $pending_cnt_r ? (int)$pending_cnt_r->fetch_assoc()['c'] : 0;

// Distinct domains for filter
$domain_res = $conn->query("SELECT DISTINCT domain FROM final_year_projects WHERE domain != '' ORDER BY domain");
$all_domains = [];
while ($dr = $domain_res->fetch_assoc()) $all_domains[] = $dr['domain'];

$msg = $_GET['msg'] ?? '';
$page_title = "Manage Projects";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Final Year Projects — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        /* ── Stats row ── */
        .proj-stats {
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
        .pstat-ico.green  { background: #f0fdf4; color: #16a34a; }
        .pstat-ico.yellow { background: #fffbeb; color: #d97706; }
        .pstat-ico.red    { background: #fef2f2; color: #dc2626; }
        .pstat-num  { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.45rem; font-weight: 800; color: #0f172a; line-height: 1; }
        .pstat-lbl  { font-size: .72rem; color: #64748b; margin-top: 3px; font-weight: 500; }

        /* ── Toolbar ── */
        .proj-toolbar {
            display: flex; align-items: center; gap: 10px;
            flex-wrap: wrap; margin-bottom: 20px;
        }
        .search-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .85rem; }
        .search-wrap input {
            width: 100%; padding: 9px 12px 9px 34px;
            border: 1.5px solid #e2e8f0; border-radius: 9px;
            font-size: .875rem; font-family: 'DM Sans', sans-serif;
            color: #0f172a; background: #fff; outline: none;
            transition: border-color .2s;
        }
        .search-wrap input:focus { border-color: #3b82f6; }
        .filter-select {
            padding: 9px 12px; border: 1.5px solid #e2e8f0; border-radius: 9px;
            font-size: .84rem; font-family: 'DM Sans', sans-serif;
            color: #334155; background: #fff; outline: none; cursor: pointer;
        }
        .btn-add-proj {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: #fff; border-radius: 9px;
            font-size: .875rem; font-weight: 700;
            text-decoration: none; white-space: nowrap;
            transition: all .2s;
        }
        .btn-add-proj:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.32); color: #fff; }

        /* ── Project table card ── */
        .proj-table-wrap {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
        }
        .proj-table-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1.5px solid #f1f5f9;
            background: #fafbff;
        }
        .proj-table-head h3 { font-size: .9rem; font-weight: 700; color: #0f172a; margin: 0; }
        .proj-table-head span { font-size: .78rem; color: #64748b; }

        table.proj-tbl { width: 100%; border-collapse: collapse; }
        table.proj-tbl thead th {
            text-align: left; padding: 11px 16px;
            font-size: .69rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; color: #94a3b8;
            background: #f8fafc; border-bottom: 1.5px solid #f1f5f9;
            white-space: nowrap;
        }
        table.proj-tbl tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
        table.proj-tbl tbody tr:last-child { border-bottom: none; }
        table.proj-tbl tbody tr:hover { background: #fafbff; }
        table.proj-tbl tbody td { padding: 13px 16px; font-size: .84rem; color: #334155; vertical-align: middle; }

        /* Thumbnail cell */
        .proj-thumb {
            width: 52px; height: 40px; border-radius: 8px;
            overflow: hidden; background: #f1f5f9;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .proj-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .proj-thumb i { color: #94a3b8; font-size: .9rem; }

        .proj-title-cell { display: flex; align-items: center; gap: 11px; }
        .proj-title-name { font-weight: 700; color: #0f172a; font-size: .85rem; line-height: 1.3; max-width: 280px; }
        .proj-title-domain { font-size: .7rem; color: #64748b; margin-top: 2px; }

        /* Domain badge */
        .domain-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 9px; border-radius: 999px;
            font-size: .68rem; font-weight: 700; white-space: nowrap;
        }
        .db-ai     { background: #f5f3ff; color: #6d28d9; }
        .db-web    { background: #eff6ff; color: #1d4ed8; }
        .db-iot    { background: #f0fdf4; color: #15803d; }
        .db-data   { background: #fff7ed; color: #c2410c; }
        .db-mobile { background: #fdf4ff; color: #a21caf; }
        .db-cyber  { background: #fef2f2; color: #b91c1c; }
        .db-java   { background: #fff3e0; color: #e65100; }
        .db-python { background: #e8f5e9; color: #2e7d32; }
        .db-block  { background: #e3f2fd; color: #0277bd; }
        .db-cloud  { background: #e0f7fa; color: #00695c; }
        .db-vlsi   { background: #fce4ec; color: #880e4f; }
        .db-analytics { background: #fff8e1; color: #f57f17; }
        .db-other  { background: #f8fafc; color: #475569; }

        /* Status badge */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 999px;
            font-size: .7rem; font-weight: 700;
        }
        .status-badge.active   { background: #dcfce7; color: #15803d; }
        .status-badge.inactive { background: #fef9c3; color: #854d0e; }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* Order count badge */
        .order-badge {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: .78rem; font-weight: 700; color: #1d4ed8;
        }
        .order-badge .pend { color: #dc2626; }

        /* Price */
        .price-cell { font-weight: 800; font-size: .9rem; color: #0f172a; }
        .price-cell span { font-size: .72rem; font-weight: 500; color: #94a3b8; }

        /* Actions */
        .action-btns { display: flex; align-items: center; gap: 6px; }
        .ab {
            display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            padding: 6px 12px; border-radius: 7px;
            font-size: .76rem; font-weight: 600;
            text-decoration: none; transition: all .18s; white-space: nowrap;
            border: none; cursor: pointer; font-family: 'DM Sans', sans-serif;
        }
        .ab-edit    { background: #eff6ff; color: #1d4ed8; }
        .ab-edit:hover    { background: #dbeafe; color: #1e40af; }
        .ab-toggle  { background: #f0fdf4; color: #15803d; }
        .ab-toggle:hover  { background: #dcfce7; }
        .ab-toggle.off { background: #fffbeb; color: #b45309; }
        .ab-toggle.off:hover { background: #fef3c7; }
        .ab-delete  { background: #fef2f2; color: #b91c1c; }
        .ab-delete:hover  { background: #fee2e2; }
        .ab-orders  { background: #fdf4ff; color: #a21caf; }
        .ab-orders:hover  { background: #fae8ff; }

        /* Flash message */
        .flash {
            display: flex; align-items: center; gap: 9px;
            padding: 12px 16px; border-radius: 10px;
            font-size: .84rem; font-weight: 500; margin-bottom: 18px;
        }
        .flash-success { background: #f0fdf4; border: 1.5px solid #86efac; color: #065f46; }
        .flash-info    { background: #eff6ff; border: 1.5px solid #93c5fd; color: #1e3a8a; }

        /* Empty state */
        .empty-proj {
            text-align: center; padding: 60px 20px;
        }
        .empty-proj-ico {
            width: 68px; height: 68px; border-radius: 18px;
            background: linear-gradient(135deg, #eff6ff, #e0e7ff);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: #3b82f6;
            margin: 0 auto 16px;
        }
        .empty-proj h3 { font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
        .empty-proj p  { font-size: .84rem; color: #64748b; margin-bottom: 20px; }

        /* Confirm modal */
        .del-modal {
            display: none; position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,.45);
            align-items: center; justify-content: center;
        }
        .del-modal.open { display: flex; }
        .del-modal-box {
            background: #fff; border-radius: 18px; width: 420px; max-width: 94vw;
            box-shadow: 0 20px 60px rgba(0,0,0,.18); overflow: hidden;
            animation: modalIn .2s ease;
        }
        @keyframes modalIn { from{transform:scale(.94);opacity:0} to{transform:scale(1);opacity:1} }
        .del-modal-head {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            padding: 18px 22px; color: #fff;
            display: flex; align-items: center; gap: 11px;
        }
        .del-modal-head i { font-size: 1.1rem; }
        .del-modal-head h3 { font-size: .95rem; font-weight: 700; margin: 0; }
        .del-modal-body { padding: 20px 22px; }
        .del-modal-body p { font-size: .88rem; color: #334155; margin-bottom: 20px; line-height: 1.6; }
        .del-modal-actions { display: flex; gap: 10px; }
        .btn-confirm-del {
            flex: 1; padding: 10px; border-radius: 9px;
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: #fff; border: none; font-weight: 700; font-size: .875rem;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
        }
        .btn-cancel-del {
            flex: 1; padding: 10px; border-radius: 9px;
            background: #f1f5f9; color: #475569;
            border: none; font-weight: 600; font-size: .875rem;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
        }

        @media (max-width: 900px) {
            .proj-stats { grid-template-columns: repeat(2, 1fr); }
            table.proj-tbl thead th:nth-child(5),
            table.proj-tbl tbody td:nth-child(5) { display: none; }
        }
        @media (max-width: 600px) {
            .proj-stats { grid-template-columns: repeat(2, 1fr); }
            .proj-toolbar { flex-direction: column; align-items: stretch; }
            .btn-add-proj { justify-content: center; }
            table.proj-tbl thead th:nth-child(3),
            table.proj-tbl tbody td:nth-child(3),
            table.proj-tbl thead th:nth-child(4),
            table.proj-tbl tbody td:nth-child(4) { display: none; }
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
                    <h1><i class="fas fa-folder-open"></i> Final Year Projects</h1>
                    <p>Manage all projects in the catalogue. Students browse and purchase from the public page.</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="project_orders.php" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:7px;">
                        <i class="fas fa-shopping-bag"></i> View Orders
                        <?php if ($pending_cnt > 0): ?>
                        <span style="background:#ef4444;color:#fff;font-size:.62rem;font-weight:800;padding:1px 7px;border-radius:999px;"><?= $pending_cnt ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="add_project.php" class="btn-add-proj">
                        <i class="fas fa-plus"></i> Add Project
                    </a>
                </div>
            </div>

            <!-- Flash messages -->
            <?php if ($msg === 'deleted'): ?>
            <div class="flash flash-success"><i class="fas fa-check-circle"></i> Project deleted successfully.</div>
            <?php elseif ($msg === 'toggled'): ?>
            <div class="flash flash-info"><i class="fas fa-sync-alt"></i> Project status updated.</div>
            <?php elseif ($msg === 'saved'): ?>
            <div class="flash flash-success"><i class="fas fa-check-circle"></i> Project updated successfully.</div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="proj-stats">
                <div class="pstat">
                    <div class="pstat-ico blue"><i class="fas fa-layer-group"></i></div>
                    <div>
                        <div class="pstat-num"><?= (int)($stats['total'] ?? 0) ?></div>
                        <div class="pstat-lbl">Total Projects</div>
                    </div>
                </div>
                <div class="pstat">
                    <div class="pstat-ico green"><i class="fas fa-eye"></i></div>
                    <div>
                        <div class="pstat-num"><?= (int)($stats['active_cnt'] ?? 0) ?></div>
                        <div class="pstat-lbl">Active (Visible)</div>
                    </div>
                </div>
                <div class="pstat">
                    <div class="pstat-ico yellow"><i class="fas fa-eye-slash"></i></div>
                    <div>
                        <div class="pstat-num"><?= (int)($stats['inactive_cnt'] ?? 0) ?></div>
                        <div class="pstat-lbl">Inactive</div>
                    </div>
                </div>
                <div class="pstat">
                    <div class="pstat-ico red"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="pstat-num"><?= $pending_cnt ?></div>
                        <div class="pstat-lbl">Pending Orders</div>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="proj-toolbar">
                <form method="GET" style="display:contents;">
                    <div class="search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by title or tech stack..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="domain" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Domains</option>
                        <?php foreach ($all_domains as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $domain_filter === $d ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($search || $domain_filter): ?>
                    <a href="manage_projects.php" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <div class="proj-table-wrap">
                <div class="proj-table-head">
                    <h3><i class="fas fa-list" style="color:#3b82f6;margin-right:7px;"></i>
                        All Projects
                        <?php if ($search || $domain_filter): ?>
                        <span style="font-weight:500;color:#64748b;font-size:.8rem;"> — filtered</span>
                        <?php endif; ?>
                    </h3>
                    <span><?= $result ? $result->num_rows : 0 ?> project(s) found</span>
                </div>

                <?php if ($result && $result->num_rows > 0): ?>
                <div style="overflow-x:auto;">
                <table class="proj-tbl">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Domain</th>
                            <th>Price</th>
                            <th>Orders</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()):
                        // Domain → CSS class
                        $d = strtolower($row['domain'] ?? '');
                        $db_cls = str_contains($d,'ai') || str_contains($d,'ml') ? 'db-ai'
                            : (str_contains($d,'web')        ? 'db-web'
                            : (str_contains($d,'iot')        ? 'db-iot'
                            : (str_contains($d,'analytics')  ? 'db-analytics'
                            : (str_contains($d,'data')       ? 'db-data'
                            : (str_contains($d,'mobile')     ? 'db-mobile'
                            : (str_contains($d,'cyber')      ? 'db-cyber'
                            : (str_contains($d,'java')       ? 'db-java'
                            : (str_contains($d,'python')     ? 'db-python'
                            : (str_contains($d,'block')      ? 'db-block'
                            : (str_contains($d,'cloud')      ? 'db-cloud'
                            : (str_contains($d,'vlsi')       ? 'db-vlsi'
                            : 'db-other')))))))))));
                        $is_active = $row['status'] === 'active';
                        $pend  = (int)($row['pending_orders']  ?? 0);
                        $apprd = (int)($row['approved_orders'] ?? 0);
                        $total = (int)($row['total_orders']    ?? 0);
                    ?>
                    <tr>
                        <!-- Project -->
                        <td>
                            <div class="proj-title-cell">
                                <div class="proj-thumb">
                                    <?php if (!empty($row['thumbnail_url'])): ?>
                                    <img src="<?= htmlspecialchars($row['thumbnail_url']) ?>" alt=""
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <i class="fas fa-folder" style="display:none;"></i>
                                    <?php else: ?>
                                    <i class="fas fa-folder"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="proj-title-name"><?= htmlspecialchars($row['title']) ?></div>
                                    <?php if (!empty($row['tech_stack'])): ?>
                                    <div class="proj-title-domain">
                                        <?php
                                        $tags = array_slice(explode(',', $row['tech_stack']), 0, 3);
                                        echo implode(' · ', array_map('trim', $tags));
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Domain -->
                        <td>
                            <span class="domain-badge <?= $db_cls ?>">
                                <?= htmlspecialchars($row['domain']) ?>
                            </span>
                        </td>

                        <!-- Price -->
                        <td>
                            <div class="price-cell">
                                ₹<?= number_format((float)$row['price'], 0) ?>
                                <div><span>incl. all files</span></div>
                            </div>
                        </td>

                        <!-- Orders -->
                        <td>
                            <?php if ($total > 0): ?>
                            <div class="order-badge">
                                <i class="fas fa-shopping-bag" style="font-size:.7rem;"></i>
                                <?= $total ?> total
                            </div>
                            <?php if ($pend > 0): ?>
                            <div class="order-badge" style="margin-top:3px;">
                                <i class="fas fa-clock" style="font-size:.7rem;"></i>
                                <span class="pend"><?= $pend ?> pending</span>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span style="color:#94a3b8;font-size:.78rem;">No orders yet</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td>
                            <span class="status-badge <?= $is_active ? 'active' : 'inactive' ?>">
                                <span class="status-dot"></span>
                                <?= $is_active ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>

                        <!-- Added -->
                        <td style="color:#64748b;font-size:.78rem;white-space:nowrap;">
                            <?= date('d M Y', strtotime($row['created_at'])) ?>
                        </td>

                        <!-- Actions -->
                        <td>
                            <div class="action-btns">
                                <a href="edit_project.php?id=<?= $row['id'] ?>" class="ab ab-edit">
                                    <i class="fas fa-pen"></i> Edit
                                </a>
                                <a href="manage_projects.php?toggle=<?= $row['id'] ?>"
                                   class="ab ab-toggle <?= $is_active ? '' : 'off' ?>"
                                   title="<?= $is_active ? 'Click to deactivate' : 'Click to activate' ?>">
                                    <i class="fas fa-<?= $is_active ? 'eye-slash' : 'eye' ?>"></i>
                                </a>
                                <?php if ($total > 0): ?>
                                <a href="project_orders.php?project=<?= $row['id'] ?>" class="ab ab-orders">
                                    <i class="fas fa-shopping-bag"></i>
                                    <?php if ($pend > 0): ?><span style="font-weight:800;color:#dc2626;"><?= $pend ?></span><?php endif; ?>
                                </a>
                                <?php endif; ?>
                                <button class="ab ab-delete" onclick="confirmDelete(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['title'])) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                </div>

                <?php else: ?>
                <div class="empty-proj">
                    <div class="empty-proj-ico"><i class="fas fa-folder-open"></i></div>
                    <?php if ($search || $domain_filter): ?>
                    <h3>No projects match your search</h3>
                    <p>Try different keywords or clear the filters.</p>
                    <a href="manage_projects.php" class="btn btn-ghost"><i class="fas fa-times"></i> Clear Filters</a>
                    <?php else: ?>
                    <h3>No projects added yet</h3>
                    <p>Add your first final year project to the catalogue.</p>
                    <a href="add_project.php" class="btn-add-proj" style="display:inline-flex;"><i class="fas fa-plus"></i> Add First Project</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div><!-- /proj-table-wrap -->

        </div><!-- /content-wrapper -->
    </div><!-- /main -->
</div><!-- /dashboard -->

<!-- Delete Confirm Modal -->
<div class="del-modal" id="delModal">
    <div class="del-modal-box">
        <div class="del-modal-head">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Delete Project</h3>
        </div>
        <div class="del-modal-body">
            <p>Are you sure you want to delete <strong id="delProjectName"></strong>?<br>
            This action cannot be undone. Any existing orders for this project will still remain in the orders table.</p>
            <div class="del-modal-actions">
                <a href="#" id="delConfirmBtn" class="btn-confirm-del">
                    <i class="fas fa-trash"></i> Yes, Delete
                </a>
                <button class="btn-cancel-del" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>

<script>
function confirmDelete(id, name) {
    document.getElementById('delProjectName').textContent = '"' + name + '"';
    document.getElementById('delConfirmBtn').href = 'manage_projects.php?delete=' + id;
    document.getElementById('delModal').classList.add('open');
}
function closeModal() {
    document.getElementById('delModal').classList.remove('open');
}
document.getElementById('delModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Auto-dismiss flash messages after 4s
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