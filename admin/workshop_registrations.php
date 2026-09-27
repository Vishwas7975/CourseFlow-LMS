<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

// ── Mark workshop notifications as read ────────────────────────────────
$conn->query("UPDATE notifications SET is_read=1 WHERE type='workshop' AND for_role='admin'");

// ── Auto-create table ───────────────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS workshop_registrations (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        full_name        VARCHAR(120)  NOT NULL,
        hall_ticket      VARCHAR(40)   NOT NULL,
        email            VARCHAR(150)  NOT NULL,
        year_of_study    TINYINT(1)    NOT NULL,
        contact_number   VARCHAR(20)   NOT NULL,
        whatsapp_number  VARCHAR(20)   NOT NULL,
        college_name     VARCHAR(220)  NOT NULL,
        college_location VARCHAR(200)  NOT NULL,
        department       VARCHAR(200)  NOT NULL,
        interested_domain VARCHAR(60)  NOT NULL,
        source           VARCHAR(60)   NOT NULL DEFAULT 'direct',
        ip_address       VARCHAR(45)   DEFAULT NULL,
        registered_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Filters ─────────────────────────────────────────────────────────────
$search   = trim($_GET['search']  ?? '');
$domain   = trim($_GET['domain']  ?? '');
$year     = trim($_GET['year']    ?? '');
$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($search !== '') {
    $s = '%' . $search . '%';
    $where .= " AND (full_name LIKE ? OR email LIKE ? OR hall_ticket LIKE ? OR college_name LIKE ?)";
    $params = array_merge($params, [$s, $s, $s, $s]);
    $types .= "ssss";
}
if ($domain !== '') {
    $where .= " AND interested_domain = ?";
    $params[] = $domain;
    $types .= "s";
}
if ($year !== '') {
    $where .= " AND year_of_study = ?";
    $params[] = (int)$year;
    $types .= "i";
}

// ── Total count ─────────────────────────────────────────────────────────
$cs = $conn->prepare("SELECT COUNT(*) FROM workshop_registrations $where");
if ($cs) {
    if ($types) $cs->bind_param($types, ...$params);
    $cs->execute(); $cs->bind_result($total); $cs->fetch(); $cs->close();
} else { $total = 0; }
$total_pages = max(1, ceil($total / $per_page));

// ── Fetch rows ──────────────────────────────────────────────────────────
$rows = [];
$st   = $conn->prepare("SELECT * FROM workshop_registrations $where ORDER BY registered_at DESC LIMIT ? OFFSET ?");
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
$today_res   = $conn->query("SELECT COUNT(*) FROM workshop_registrations WHERE DATE(registered_at)=CURDATE()");
$today_count = $today_res ? $today_res->fetch_row()[0] : 0;

$domain_res  = $conn->query("SELECT COUNT(DISTINCT interested_domain) FROM workshop_registrations");
$domain_count = $domain_res ? $domain_res->fetch_row()[0] : 0;

$college_res  = $conn->query("SELECT COUNT(DISTINCT college_name) FROM workshop_registrations");
$college_count = $college_res ? $college_res->fetch_row()[0] : 0;

$year_labels = [1=>'1st Year', 2=>'2nd Year', 3=>'3rd Year', 4=>'4th Year'];

$domain_colors = [
    'AIML'                      => ['bg'=>'#ede9fe','text'=>'#6d28d9','dot'=>'#7c3aed'],
    'Cyber Security'            => ['bg'=>'#fee2e2','text'=>'#dc2626','dot'=>'#ef4444'],
    'Web Development'           => ['bg'=>'#dbeafe','text'=>'#1d4ed8','dot'=>'#3b82f6'],
    'Java Fullstack Development'=> ['bg'=>'#fef3c7','text'=>'#b45309','dot'=>'#f59e0b'],
    'Data Analytics'            => ['bg'=>'#d1fae5','text'=>'#065f46','dot'=>'#10b981'],
    'Data Science'              => ['bg'=>'#fce7f3','text'=>'#9d174d','dot'=>'#ec4899'],
    'HR'                        => ['bg'=>'#fff7ed','text'=>'#c2410c','dot'=>'#ea7c1a'],
    'Finance'                   => ['bg'=>'#f0fdf4','text'=>'#065f46','dot'=>'#059669'],
    'Marketing'                 => ['bg'=>'#fdf4ff','text'=>'#86198f','dot'=>'#a21caf'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workshop Registrations — CourseFlow Admin</title>
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
    .ws-fselect {
        padding: 9px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 9px;
        font-size: .83rem;
        color: #0f172a;
        background: #fafbfc;
        outline: none;
        font-family: 'DM Sans', sans-serif;
        cursor: pointer;
        transition: border-color .2s;
        min-width: 140px;
    }
    .ws-fselect:focus { border-color: #2563eb; background: #fff; }

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

    /* ── Export btn in table topbar ── */
    .btn-export {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 16px; border-radius: 9px;
        background: linear-gradient(135deg,#059669,#10b981);
        color: #fff; font-size: .8rem; font-weight: 700;
        border: none; cursor: pointer;
        transition: all .2s; text-decoration: none;
        box-shadow: 0 3px 10px rgba(16,185,129,.25);
        white-space: nowrap;
    }
    .btn-export:hover { box-shadow: 0 5px 16px rgba(16,185,129,.35); transform: translateY(-1px); }

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
    .year-pill {
        display: inline-block;
        padding: 3px 10px; border-radius: 999px;
        font-size: .68rem; font-weight: 800;
        background: #eff6ff; color: #2563eb;
        white-space: nowrap;
    }
    .domain-pill {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 10px; border-radius: 999px;
        font-size: .68rem; font-weight: 800;
        white-space: nowrap;
    }
    .domain-dot {
        width: 6px; height: 6px; border-radius: 50%;
        flex-shrink: 0;
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
                        Workshop Registrations
                    </h1>
                    <p>All students who registered for the Free Industry Workshop</p>
                </div>
                <div class="ws-page-hdr-right">
                    <a href="export_workshop.php<?= ($search||$domain||$year) ? '?'.http_build_query(array_filter(['search'=>$search,'domain'=>$domain,'year'=>$year])) : '' ?>" class="btn-export">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </a>
                    <a href="dashboard.php" class="back-btn">
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
                            <i class="fas fa-code-branch"></i>
                        </div>
                        <span class="ws-stat-trend" style="background:#f5f3ff;color:#7c3aed">
                            <i class="fas fa-layer-group"></i> Unique
                        </span>
                    </div>
                    <div>
                        <div class="ws-stat-num"><?= $domain_count ?></div>
                        <div class="ws-stat-lbl">Domains Interested</div>
                    </div>
                </div>

                <div class="ws-stat ws-stat-orange">
                    <div class="ws-stat-top">
                        <div class="ws-stat-ico" style="background:#fff7ed;color:#ea580c">
                            <i class="fas fa-university"></i>
                        </div>
                        <span class="ws-stat-trend" style="background:#fff7ed;color:#ea580c">
                            <i class="fas fa-map-marker-alt"></i> Colleges
                        </span>
                    </div>
                    <div>
                        <div class="ws-stat-num"><?= $college_count ?></div>
                        <div class="ws-stat-lbl">Colleges Represented</div>
                    </div>
                </div>
            </div>

          <!-- ── Filter Bar ── -->
 
            <form method="GET" action="">
            <div class="ws-filter-bar">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input class="ws-finput" type="text" name="search"
                        placeholder="    Search name, email, hall ticket...."
                        value="<?= htmlspecialchars($search) ?>">
                </div> 
<select class="ws-fselect" name="domain">
    <option value="">All Domains</option>
    <?php foreach([
        'AIML','Cyber Security','Web Development',
        'Java Fullstack Development','Data Analytics','Data Science',
        'HR','Finance','Marketing'
    ] as $d): ?>
    <option value="<?= $d ?>" <?= $domain===$d?'selected':'' ?>><?= $d ?></option>
    <?php endforeach; ?>
</select>
                <select class="ws-fselect" name="year" style="min-width:120px;">
                    <option value="">All Years</option>
                    <?php foreach([1,2,3,4] as $y): ?>
                    <option value="<?= $y ?>" <?= (string)$year===(string)$y?'selected':'' ?>><?= $year_labels[$y] ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="ws-filter-btn ws-filter-btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <?php if($search || $domain || $year): ?>
                <a href="workshop_registrations.php" class="ws-filter-btn ws-filter-btn-clear">
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
                        <?php if($search||$domain||$year): ?>
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
                        <p><?= ($search||$domain||$year) ? 'No results match your current filters. Try adjusting your search.' : 'No students have registered for the workshop yet.' ?></p>
                    </div>
                <?php else: ?>
                <table class="ws-tbl">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reg ID</th>
                            <th>Student</th>
                            <th>Hall Ticket</th>
                            <th>Year</th>
                            <th>Domain</th>
                            <th>College</th>
                            <th>Department</th>
                            <th>WhatsApp</th>
                            <th>Registered On</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $i => $r):
                        $dc    = $domain_colors[$r['interested_domain']] ?? ['bg'=>'#f1f5f9','text'=>'#64748b','dot'=>'#94a3b8'];
                        $waNum = preg_replace('/\D/', '', $r['whatsapp_number']);
                    ?>
                        <tr>
                            <td class="cell-num"><?= $offset + $i + 1 ?></td>
                            <td><span class="reg-id">#WS<?= str_pad($r['id'],6,'0',STR_PAD_LEFT) ?></span></td>
                            <td>
                                <div class="cell-name"><?= htmlspecialchars($r['full_name']) ?></div>
                                <div class="cell-email"><?= htmlspecialchars($r['email']) ?></div>
                            </td>
                            <td><span class="ht-code"><?= htmlspecialchars($r['hall_ticket']) ?></span></td>
                            <td><span class="year-pill"><?= $year_labels[$r['year_of_study']] ?? $r['year_of_study'] ?></span></td>
                            <td>
                                <span class="domain-pill" style="background:<?= $dc['bg'] ?>;color:<?= $dc['text'] ?>">
                                    <span class="domain-dot" style="background:<?= $dc['dot'] ?>"></span>
                                    <?= htmlspecialchars($r['interested_domain']) ?>
                                </span>
                            </td>
                            <td class="cell-college" title="<?= htmlspecialchars($r['college_name']) ?><?= $r['college_location'] ? ' — '.$r['college_location'] : '' ?>">
                                <?= htmlspecialchars($r['college_name']) ?>
                            </td>
                            <td class="cell-dept" title="<?= htmlspecialchars($r['department']) ?>">
                                <?= htmlspecialchars($r['department']) ?>
                            </td>
                            <td>
                                <a href="https://wa.me/91<?= $waNum ?>" target="_blank" class="wa-btn">
                                    <i class="fab fa-whatsapp"></i>
                                    <?= htmlspecialchars($r['whatsapp_number']) ?>
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
                    $qs = http_build_query(array_filter(['search'=>$search,'domain'=>$domain,'year'=>$year]));
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