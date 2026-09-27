<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

// ── Mark notifications as read ────────────────────────────────
$conn->query("UPDATE notifications SET is_read=1 WHERE type='new_registration' AND for_role='admin'");

// ── Auto-create table ───────────────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS fdp_registrations (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        name             VARCHAR(120)  NOT NULL,
        email            VARCHAR(150)  NOT NULL,
        whatsapp         VARCHAR(20)   NOT NULL,
        designation      VARCHAR(100)  NOT NULL,
        organisation     VARCHAR(200)  NOT NULL,
        college          VARCHAR(220)  NOT NULL,
        department       VARCHAR(200)  NOT NULL,
        utr_number       VARCHAR(50)   NOT NULL,
        registered_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Filters ─────────────────────────────────────────────────────────────
$search   = trim($_GET['search']  ?? '');
$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($search !== '') {
    $s = '%' . $search . '%';
    $where .= " AND (name LIKE ? OR email LIKE ? OR utr_number LIKE ? OR college LIKE ?)";
    $params = array_merge($params, [$s, $s, $s, $s]);
    $types .= "ssss";
}

// ── Total count ─────────────────────────────────────────────────────────
$cs = $conn->prepare("SELECT COUNT(*) FROM fdp_registrations $where");
if ($cs) {
    if ($types) $cs->bind_param($types, ...$params);
    $cs->execute(); $cs->bind_result($total); $cs->fetch(); $cs->close();
} else { $total = 0; }
$total_pages = max(1, ceil($total / $per_page));

// ── Fetch rows ──────────────────────────────────────────────────────────
$rows = [];
$st   = $conn->prepare("SELECT * FROM fdp_registrations $where ORDER BY registered_at DESC LIMIT ? OFFSET ?");
if ($st) {
    $lim = $per_page;
    if ($types) {
        $st->bind_param($types . "ii", ...(array_merge($params, [$lim, $offset])));
    } else {
        $st->bind_param("ii", $lim, $offset);
    }
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $st->close();
}

// ── Stats ────────────────────────────────────────────────────────────────
$today_res   = $conn->query("SELECT COUNT(*) FROM fdp_registrations WHERE DATE(registered_at)=CURDATE()");
$today_count = $today_res ? $today_res->fetch_row()[0] : 0;

$college_res  = $conn->query("SELECT COUNT(DISTINCT college) FROM fdp_registrations");
$college_count = $college_res ? $college_res->fetch_row()[0] : 0;

$org_res = $conn->query("SELECT COUNT(DISTINCT organisation) FROM fdp_registrations");
$org_count = $org_res ? $org_res->fetch_row()[0] : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FDP Registrations — CourseFlow Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>

    /* ══════════════════════════════════════════
       STAT CARDS
    ══════════════════════════════════════════ */
    .ws-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    @media(max-width:900px){ .ws-stat-grid { grid-template-columns: repeat(2,1fr); } }
    @media(max-width:480px){ .ws-stat-grid { grid-template-columns: 1fr 1fr; } }

    .ws-stat {
        background: #fff;
        border-radius: 16px;
        padding: 20px 22px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 12px rgba(15,23,42,.05);
        display: flex;
        flex-direction: column;
        gap: 12px;
        position: relative;
        overflow: hidden;
        transition: box-shadow .2s, transform .2s;
    }
    .ws-stat:hover {
        box-shadow: 0 6px 24px rgba(15,23,42,.09);
        transform: translateY(-2px);
    }
    .ws-stat::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        border-radius: 16px 16px 0 0;
    }
    .ws-stat-blue::before  { background: linear-gradient(90deg,#2563eb,#60a5fa); }
    .ws-stat-green::before { background: linear-gradient(90deg,#10b981,#6ee7b7); }
    .ws-stat-violet::before{ background: linear-gradient(90deg,#7c3aed,#a78bfa); }
    .ws-stat-orange::before{ background: linear-gradient(90deg,#ea580c,#fb923c); }

    .ws-stat-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .ws-stat-ico {
        width: 42px; height: 42px;
        border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
    }
    .ws-stat-trend {
        font-size: .69rem; font-weight: 700;
        padding: 3px 8px; border-radius: 999px;
        display: flex; align-items: center; gap: 3px;
    }
    .ws-stat-num {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 2rem; font-weight: 900;
        color: #0f172a; line-height: 1;
    }
    .ws-stat-lbl {
        font-size: .75rem; color: #64748b;
        font-weight: 500; margin-top: 2px;
    }

    /* ══════════════════════════════════════════
       FILTER BAR
    ══════════════════════════════════════════ */
    .ws-filter-bar {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 12px rgba(15,23,42,.05);
        padding: 14px 18px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .ws-filter-bar .search-wrap {
    flex: 0 1 260px; min-width: 160px;
    position: relative;
    }
    .ws-filter-bar .search-wrap i {
        position: absolute; left: 12px; top: 50%;
        transform: translateY(-50%);
        color: #94a3b8; font-size: .82rem; pointer-events: none;
    }
    .ws-finput {
        width: 100%;
        padding: 9px 12px 9px 34px;
        border: 1.5px solid #e2e8f0;
        border-radius: 9px;
        font-size: .83rem;
        color: #0f172a;
        background: #fafbfc;
        outline: none;
        font-family: 'DM Sans', sans-serif;
        transition: border-color .2s, box-shadow .2s;
    }
    .ws-finput:focus {
        border-color: #2563eb;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(37,99,235,.08);
    }

    .ws-filter-btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 7px 12px; border-radius: 8px;
        font-size: .76rem; font-weight: 700;
        border: none; cursor: pointer;
        transition: all .2s; font-family: 'DM Sans', sans-serif;
        white-space: nowrap;
    }
    .ws-filter-btn-primary {
        background: linear-gradient(135deg,#2563eb,#4f46e5);
        color: #fff;
        box-shadow: 0 3px 10px rgba(37,99,235,.25);
    }
    .ws-filter-btn-primary:hover { box-shadow: 0 5px 16px rgba(37,99,235,.35); transform: translateY(-1px); }
    .ws-filter-btn-clear {
        background: #f1f5f9; color: #64748b;
        text-decoration: none;
    }
    .ws-filter-btn-clear:hover { background: #e2e8f0; color: #0f172a; }

    /* ══════════════════════════════════════════
       TABLE CARD
    ══════════════════════════════════════════ */
    .ws-table-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 12px rgba(15,23,42,.05);
        overflow: hidden;
    }
    .ws-table-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        border-bottom: 1px solid #f1f5f9;
        flex-wrap: wrap;
        gap: 10px;
        background: linear-gradient(to bottom, #fafbff, #fff);
    }
    .ws-table-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .95rem; font-weight: 800;
        color: #0f172a;
        display: flex; align-items: center; gap: 9px;
    }
    .ws-count-pill {
        background: #eff6ff; color: #2563eb;
        font-size: .68rem; font-weight: 800;
        padding: 3px 10px; border-radius: 999px;
        letter-spacing: .02em;
    }
    .ws-filtered-pill {
        background: #fef3c7; color: #b45309;
        font-size: .68rem; font-weight: 700;
        padding: 3px 9px; border-radius: 999px;
    }

    /* ── Scrollable table wrap ── */
    .tbl-scroll { overflow-x: auto; }

    .ws-tbl {
        width: 100%; border-collapse: collapse;
        min-width: 960px;
    }
    .ws-tbl thead tr {
        background: #f8fafc;
    }
    .ws-tbl th {
        padding: 11px 16px;
        text-align: left;
        font-size: .69rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: .07em;
        color: #94a3b8;
        border-bottom: 1px solid #f1f5f9;
        white-space: nowrap;
    }
    .ws-tbl th:first-child { padding-left: 22px; }
    .ws-tbl th:last-child  { padding-right: 22px; }

    .ws-tbl td {
        padding: 13px 16px;
        font-size: .82rem; color: #334155;
        border-bottom: 1px solid #f8fafc;
        vertical-align: middle;
    }
    .ws-tbl td:first-child { padding-left: 22px; }
    .ws-tbl td:last-child  { padding-right: 22px; }
    .ws-tbl tbody tr:last-child td { border-bottom: none; }
    .ws-tbl tbody tr {
        transition: background .15s;
    }
    .ws-tbl tbody tr:hover td { background: #f8fbff; }

    /* ── Cell styles ── */
    .cell-num {
        font-size: .72rem; color: #cbd5e1;
        font-weight: 600; width: 36px;
    }
    .reg-id {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .76rem; font-weight: 800; color: #2563eb;
        background: #eff6ff; padding: 3px 9px;
        border-radius: 6px; white-space: nowrap;
    }
    .cell-name { font-weight: 700; color: #0f172a; }
    .cell-email { font-size: .78rem; color: #64748b; }
    .ht-code {
        font-family: 'Courier New', monospace;
        font-size: .78rem; background: #f1f5f9;
        color: #475569; padding: 3px 8px;
        border-radius: 6px; letter-spacing: .03em;
    }
    .cell-college {
        max-width: 150px; overflow: hidden;
        text-overflow: ellipsis; white-space: nowrap;
        font-size: .78rem;
    }
    .cell-dept {
        max-width: 120px; overflow: hidden;
        text-overflow: ellipsis; white-space: nowrap;
        font-size: .77rem; color: #64748b;
    }
    .wa-btn {
        display: inline-flex; align-items: center; gap: 5px;
        color: #16a34a; font-size: .79rem; font-weight: 600;
        text-decoration: none; transition: all .2s;
        padding: 4px 8px; border-radius: 7px;
        background: #f0fdf4;
    }
    .wa-btn:hover { background: #dcfce7; }
    .wa-btn i { font-size: 1rem; }
    .cell-date { font-size: .74rem; color: #64748b; white-space: nowrap; line-height: 1.7; }
    .cell-date strong { color: #334155; font-weight: 600; display: block; }

    /* ── Empty state ── */
    .ws-empty {
        text-align: center; padding: 64px 20px;
    }
    .ws-empty-ico {
        width: 64px; height: 64px; border-radius: 18px;
        background: #f1f5f9;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.6rem; color: #cbd5e1;
        margin: 0 auto 16px;
    }
    .ws-empty h3 {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1rem; font-weight: 700; color: #334155;
        margin-bottom: 6px;
    }
    .ws-empty p { font-size: .84rem; color: #94a3b8; }

    /* ══════════════════════════════════════════
       PAGINATION
    ══════════════════════════════════════════ */
    .ws-pag {
        display: flex; align-items: center;
        justify-content: space-between;
        padding: 14px 22px;
        border-top: 1px solid #f1f5f9;
        flex-wrap: wrap; gap: 10px;
        background: #fafbfc;
    }
    .pag-info {
        font-size: .77rem; color: #94a3b8; font-weight: 500;
    }
    .pag-btns { display: flex; gap: 5px; }
    .pag-a {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 34px; height: 34px; padding: 0 10px;
        border-radius: 8px; border: 1.5px solid #e2e8f0;
        background: #fff; font-size: .78rem; font-weight: 700;
        color: #334155; text-decoration: none; transition: all .2s;
    }
    .pag-a:hover, .pag-a.cur {
        border-color: #2563eb; color: #2563eb;
        background: #eff6ff; box-shadow: 0 2px 8px rgba(37,99,235,.12);
    }
    .pag-a.cur { box-shadow: 0 2px 8px rgba(37,99,235,.2); }
    .pag-a.off { opacity: .3; pointer-events: none; }

    /* ══════════════════════════════════════════
       PAGE HEADER
    ══════════════════════════════════════════ */
    .ws-page-hdr {
        display: flex; align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap; gap: 14px;
        margin-bottom: 24px;
    }
    .ws-page-hdr-left h1 {
        font-size: 1.45rem; font-weight: 800;
        display: flex; align-items: center; gap: 10px;
        color: #0f172a;
    }
    .ws-page-hdr-left h1 .hdr-ico {
        width: 36px; height: 36px; border-radius: 10px;
        background: linear-gradient(135deg,#2563eb,#4f46e5);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: .85rem;
        box-shadow: 0 4px 12px rgba(37,99,235,.3);
    }
    .ws-page-hdr-left p {
        color: #64748b; font-size: .84rem; margin-top: 5px; margin-left: 46px;
    }
    .ws-page-hdr-right {
        display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
    }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- ── Page Header ── -->
            <div class="ws-page-hdr">
                <div class="ws-page-hdr-left">
                    <h1>
                        <span class="hdr-ico"><i class="fas fa-chalkboard-teacher"></i></span>
                        FDP Registrations
                    </h1>
                    <p>All participants who registered for the Faculty Development Program</p>
                </div>
                <div class="ws-page-hdr-right">
                    <a href="dashboard.php" class="back-btn" style="display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:9px;background:#f1f5f9;color:#334155;text-decoration:none;font-weight:700;font-size:.8rem;">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>

            <!-- ── Stat Cards ── -->
            <div class="ws-stat-grid">
                <div class="ws-stat ws-stat-blue">
                    <div class="ws-stat-top">
                        <div class="ws-stat-ico" style="background:#eff6ff;color:#2563eb">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="ws-stat-trend" style="background:#eff6ff;color:#2563eb">
                            <i class="fas fa-layer-group"></i> Total
                        </span>
                    </div>
                    <div>
                        <div class="ws-stat-num"><?= number_format($total) ?></div>
                        <div class="ws-stat-lbl">Total Registrations</div>
                    </div>
                </div>

                <div class="ws-stat ws-stat-green">
                    <div class="ws-stat-top">
                        <div class="ws-stat-ico" style="background:#f0fdf4;color:#16a34a">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <span class="ws-stat-trend" style="background:#f0fdf4;color:#16a34a">
                            <i class="fas fa-clock"></i> Today
                        </span>
                    </div>
                    <div>
                        <div class="ws-stat-num"><?= $today_count ?></div>
                        <div class="ws-stat-lbl">Registered Today</div>
                    </div>
                </div>

                <div class="ws-stat ws-stat-violet">
                    <div class="ws-stat-top">
                        <div class="ws-stat-ico" style="background:#f5f3ff;color:#7c3aed">
                            <i class="fas fa-university"></i>
                        </div>
                        <span class="ws-stat-trend" style="background:#f5f3ff;color:#7c3aed">
                            <i class="fas fa-layer-group"></i> Unique
                        </span>
                    </div>
                    <div>
                        <div class="ws-stat-num"><?= $college_count ?></div>
                        <div class="ws-stat-lbl">Colleges Represented</div>
                    </div>
                </div>

                <div class="ws-stat ws-stat-orange">
                    <div class="ws-stat-top">
                        <div class="ws-stat-ico" style="background:#fff7ed;color:#ea580c">
                            <i class="fas fa-building"></i>
                        </div>
                        <span class="ws-stat-trend" style="background:#fff7ed;color:#ea580c">
                            <i class="fas fa-map-marker-alt"></i> Orgs
                        </span>
                    </div>
                    <div>
                        <div class="ws-stat-num"><?= $org_count ?></div>
                        <div class="ws-stat-lbl">Organisations</div>
                    </div>
                </div>
            </div>

          <!-- ── Filter Bar ── -->
 
            <form method="GET" action="">
            <div class="ws-filter-bar">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input class="ws-finput" type="text" name="search"
                        placeholder="    Search name, email, UTR, college...."
                        value="<?= htmlspecialchars($search) ?>">
                </div> 
                <button type="submit" class="ws-filter-btn ws-filter-btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <?php if($search): ?>
                <a href="fdp_registrations.php" class="ws-filter-btn ws-filter-btn-clear">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>
            </form>

            <!-- ── Table Card ── -->
            <div class="ws-table-card">

                <div class="ws-table-topbar">
                    <div class="ws-table-title">
                        <i class="fas fa-table" style="color:#2563eb;font-size:.9rem"></i>
                        Registrations
                        <span class="ws-count-pill"><?= number_format($total) ?> total</span>
                        <?php if($search): ?>
                        <span class="ws-filtered-pill"><i class="fas fa-filter"></i> Filtered</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:.77rem;color:#94a3b8;font-weight:500;">
                        <?php if(!empty($rows)): ?>
                        Showing <?= $offset+1 ?>–<?= min($offset+$per_page,$total) ?> of <?= number_format($total) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tbl-scroll">
                <?php if (empty($rows)): ?>
                    <div class="ws-empty">
                        <div class="ws-empty-ico"><i class="fas fa-inbox"></i></div>
                        <h3>No registrations found</h3>
                        <p><?= ($search) ? 'No results match your current filters. Try adjusting your search.' : 'No participants have registered for the FDP yet.' ?></p>
                    </div>
                <?php else: ?>
                <table class="ws-tbl">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reg ID</th>
                            <th>Participant</th>
                            <th>Designation</th>
                            <th>Organisation</th>
                            <th>College</th>
                            <th>Department</th>
                            <th>UTR Number</th>
                            <th>WhatsApp</th>
                            <th>Registered On</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $i => $r):
                        $waNum = preg_replace('/\D/', '', $r['whatsapp']);
                    ?>
                        <tr>
                            <td class="cell-num"><?= $offset + $i + 1 ?></td>
                            <td><span class="reg-id">#FDP<?= str_pad($r['id'],6,'0',STR_PAD_LEFT) ?></span></td>
                            <td>
                                <div class="cell-name"><?= htmlspecialchars($r['name']) ?></div>
                                <div class="cell-email"><?= htmlspecialchars($r['email']) ?></div>
                            </td>
                            <td><span class="ht-code"><?= htmlspecialchars($r['designation']) ?></span></td>
                            <td class="cell-college" title="<?= htmlspecialchars($r['organisation']) ?>">
                                <?= htmlspecialchars($r['organisation']) ?>
                            </td>
                            <td class="cell-college" title="<?= htmlspecialchars($r['college']) ?>">
                                <?= htmlspecialchars($r['college']) ?>
                            </td>
                            <td class="cell-dept" title="<?= htmlspecialchars($r['department']) ?>">
                                <?= htmlspecialchars($r['department']) ?>
                            </td>
                            <td><span class="ht-code" style="color:#b45309;background:#fef3c7;"><?= htmlspecialchars($r['utr_number']) ?></span></td>
                            <td>
                                <a href="https://wa.me/91<?= $waNum ?>" target="_blank" class="wa-btn">
                                    <i class="fab fa-whatsapp"></i>
                                    <?= htmlspecialchars($r['whatsapp']) ?>
                                </a>
                            </td>
                            <td class="cell-date">
                            <?php $dt=new DateTime($r['registered_at'],new DateTimeZone('UTC'));$dt->setTimezone(new DateTimeZone('Asia/Kolkata')); ?>
                            <strong><?= $dt->format('d M Y') ?></strong>
                            <?= $dt->format('h:i A') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                </div>

                <!-- ── Pagination ── -->
                <?php if ($total_pages > 1):
                    $qs = http_build_query(array_filter(['search'=>$search]));
                    $qs = $qs ? $qs.'&' : '';
                ?>
                <div class="ws-pag">
                    <div class="pag-info">
                        Page <?= $page ?> of <?= $total_pages ?> &nbsp;·&nbsp; <?= number_format($total) ?> registrations
                    </div>
                    <div class="pag-btns">
                        <a href="?<?= $qs ?>page=<?= max(1,$page-1) ?>" class="pag-a <?= $page<=1?'off':'' ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php for($p=max(1,$page-2); $p<=min($total_pages,$page+2); $p++): ?>
                        <a href="?<?= $qs ?>page=<?= $p ?>" class="pag-a <?= $p===$page?'cur':'' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <a href="?<?= $qs ?>page=<?= min($total_pages,$page+1) ?>" class="pag-a <?= $page>=$total_pages?'off':'' ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /ws-table-card -->

        </div><!-- /content-wrapper -->
    </div><!-- /main -->
</div><!-- /dashboard -->
</body>
</html>