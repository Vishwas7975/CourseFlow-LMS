<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("finance");


// ── Stats ───────────────────────────────────────────────────────────────
// Total revenue (approved payments)
$rev_res        = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as total FROM payments WHERE status='approved'");
$total_revenue  = $rev_res ? (float)$rev_res->fetch_assoc()['total'] : 0;

// Pending payments count & amount
$pend_res       = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as amt FROM payments WHERE status='pending'");
$pend_row       = $pend_res ? $pend_res->fetch_assoc() : ['cnt'=>0,'amt'=>0];
$pending_count  = (int)$pend_row['cnt'];
$pending_amount = (float)$pend_row['amt'];

// Approved payments
$appr_res       = $conn->query("SELECT COUNT(*) as cnt FROM payments WHERE status='approved'");
$approved_count = $appr_res ? (int)$appr_res->fetch_assoc()['cnt'] : 0;

// Rejected payments
$rej_res        = $conn->query("SELECT COUNT(*) as cnt FROM payments WHERE status='rejected'");
$rejected_count = $rej_res ? (int)$rej_res->fetch_assoc()['cnt'] : 0;

// Partial payments (balance due)
$part_res       = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(pending_amount),0) as amt FROM payments WHERE status='approved' AND payment_status='partial' AND pending_amount > 0");
$part_row       = $part_res ? $part_res->fetch_assoc() : ['cnt'=>0,'amt'=>0];
$partial_count  = (int)$part_row['cnt'];
$balance_due    = (float)$part_row['amt'];

// Total students who paid
$paid_students  = $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM payments WHERE status='approved'");
$paid_students  = $paid_students ? (int)$paid_students->fetch_assoc()['c'] : 0;

// Monthly revenue (current month)
$monthly_res    = $conn->query("SELECT COALESCE(SUM(paid_amount),0) as total FROM payments WHERE status='approved' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
$monthly_rev    = $monthly_res ? (float)$monthly_res->fetch_assoc()['total'] : 0;

// GST collected
$gst_res        = $conn->query("SELECT COALESCE(SUM(gst_amount),0) as total FROM payments WHERE status='approved'");
$total_gst      = $gst_res ? (float)$gst_res->fetch_assoc()['total'] : 0;

// ── Recent payments (last 8) ────────────────────────────────────────────
$recent_res = $conn->query("
    SELECT p.*, u.name AS student_name, u.email, c.title AS course_title
    FROM payments p
    JOIN users   u ON p.user_id   = u.id
    JOIN courses c ON p.course_id = c.id
    ORDER BY p.id DESC
    LIMIT 8
");
$recent_payments = [];
while ($row = $recent_res->fetch_assoc()) $recent_payments[] = $row;

// ── Revenue by course (top 5) ───────────────────────────────────────────
$course_rev_res = $conn->query("
    SELECT c.title, COUNT(p.id) as enrollments, COALESCE(SUM(p.paid_amount),0) as revenue
    FROM payments p
    JOIN courses c ON p.course_id = c.id
    WHERE p.status='approved'
    GROUP BY p.course_id
    ORDER BY revenue DESC
    LIMIT 5
");
$course_revenues = [];
while ($row = $course_rev_res->fetch_assoc()) $course_revenues[] = $row;

// ── Internship Payments ─────────────────────────────────────────────────
$ip_rev_res  = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as total, COUNT(*) as cnt FROM internship_payments WHERE payment_status='approved'");
$ip_rev_row  = $ip_rev_res ? $ip_rev_res->fetch_assoc() : ['total'=>0,'cnt'=>0];
$ip_revenue  = (float)$ip_rev_row['total'];
$ip_approved = (int)$ip_rev_row['cnt'];

$ip_pend_res = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(amount_paid),0) as amt FROM internship_payments WHERE payment_status='pending'");
$ip_pend_row = $ip_pend_res ? $ip_pend_res->fetch_assoc() : ['cnt'=>0,'amt'=>0];
$ip_pending  = (int)$ip_pend_row['cnt'];
$ip_pend_amt = (float)$ip_pend_row['amt'];

$ip_mon_res  = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as total FROM internship_payments WHERE payment_status='approved' AND MONTH(reviewed_at)=MONTH(CURDATE()) AND YEAR(reviewed_at)=YEAR(CURDATE())");
$ip_month    = $ip_mon_res ? (float)$ip_mon_res->fetch_assoc()['total'] : 0;

// ── Monthly revenue last 6 months ─────────────────────────────────────────
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $res = $conn->query("
        SELECT COALESCE(SUM(paid_amount),0) as rev, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL $i MONTH),'%b') as mon
        FROM payments
        WHERE status='approved'
        AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL $i MONTH))
        AND YEAR(created_at)  = YEAR(DATE_SUB(CURDATE(), INTERVAL $i MONTH))
    ");
    $row = $res->fetch_assoc();
    $monthly_data[] = ['month' => $row['mon'], 'rev' => (float)$row['rev']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png?v=2">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>

    /* ── STAT CARDS ── */
    .fin-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 26px;
    }
    @media(max-width:1100px){ .fin-stat-grid { grid-template-columns: repeat(2,1fr); } }
    @media(max-width:540px){  .fin-stat-grid { grid-template-columns: 1fr 1fr; } }

    .fin-stat {
        background: #fff;
        border-radius: 16px;
        padding: 22px 20px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 12px rgba(15,23,42,.05);
        position: relative;
        overflow: hidden;
        transition: box-shadow .2s, transform .2s;
    }
    .fin-stat:hover { box-shadow: 0 8px 28px rgba(15,23,42,.1); transform: translateY(-2px); }
    .fin-stat::before {
        content: ''; position: absolute;
        top: 0; left: 0; right: 0; height: 3px;
        border-radius: 16px 16px 0 0;
    }
    .fin-stat-green::before  { background: linear-gradient(90deg,#10b981,#6ee7b7); }
    .fin-stat-blue::before   { background: linear-gradient(90deg,#2563eb,#60a5fa); }
    .fin-stat-yellow::before { background: linear-gradient(90deg,#f59e0b,#fcd34d); }
    .fin-stat-red::before    { background: linear-gradient(90deg,#ef4444,#fca5a5); }
    .fin-stat-violet::before { background: linear-gradient(90deg,#7c3aed,#c4b5fd); }
    .fin-stat-teal::before   { background: linear-gradient(90deg,#0d9488,#5eead4); }
    .fin-stat-orange::before { background: linear-gradient(90deg,#ea580c,#fdba74); }
    .fin-stat-pink::before   { background: linear-gradient(90deg,#db2777,#f9a8d4); }

    .fin-stat-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .fin-stat-ico {
        width: 44px; height: 44px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
    }
    .fin-stat-trend {
        font-size: .68rem; font-weight: 700; padding: 3px 9px;
        border-radius: 999px; display: flex; align-items: center; gap: 3px;
    }
    .fin-stat-num {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.75rem; font-weight: 900; color: #0f172a; line-height: 1;
        margin-bottom: 4px;
    }
    .fin-stat-lbl { font-size: .75rem; color: #64748b; font-weight: 500; }

    /* ── SECOND ROW STATS (smaller) ── */
    .fin-stat-grid2 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 26px;
    }
    @media(max-width:1100px){ .fin-stat-grid2 { grid-template-columns: repeat(2,1fr); } }
    @media(max-width:540px){  .fin-stat-grid2 { grid-template-columns: 1fr 1fr; } }

    .fin-mini {
        background: #fff; border-radius: 12px;
        padding: 16px 18px; border: 1px solid #eef2f7;
        box-shadow: 0 2px 8px rgba(15,23,42,.04);
        display: flex; align-items: center; gap: 14px;
        transition: box-shadow .2s;
    }
    .fin-mini:hover { box-shadow: 0 4px 16px rgba(15,23,42,.08); }
    .fin-mini-ico {
        width: 38px; height: 38px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: .9rem; flex-shrink: 0;
    }
    .fin-mini-num {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.2rem; font-weight: 800; color: #0f172a; line-height: 1;
    }
    .fin-mini-lbl { font-size: .72rem; color: #64748b; margin-top: 2px; }

    /* ── GRID LAYOUT ── */
    .fin-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; margin-bottom: 20px; }
    @media(max-width:1000px){ .fin-grid { grid-template-columns: 1fr; } }

    /* ── Mobile: scale down large stat numbers and tighten stat cards ── */
    @media(max-width:480px){
        /* Large currency values like ₹1,20,000 overflow on 2-col at 320px */
        .fin-stat-num { font-size: 1.3rem; }
        .fin-stat { padding: 14px; }
        /* Card header: allow title and view-all to wrap */
        .fin-card-head { flex-wrap: wrap; gap: 8px; }
        /* Table: smaller font on phones */
        .fin-tbl th, .fin-tbl td { padding: 8px 10px; font-size: .72rem; }
    }

    /* ── CARDS ── */
    .fin-card {
        background: #fff; border-radius: 16px;
        border: 1px solid #eef2f7;
        box-shadow: 0 2px 12px rgba(15,23,42,.05);
        overflow: hidden;
    }
    .fin-card-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 20px; border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(to bottom, #fafbff, #fff);
    }
    .fin-card-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .92rem; font-weight: 800; color: #0f172a;
        display: flex; align-items: center; gap: 8px;
    }
    .fin-card-title i { color: #2563eb; font-size: .85rem; }
    .fin-view-all {
        font-size: .75rem; font-weight: 700; color: #2563eb;
        display: flex; align-items: center; gap: 4px;
        padding: 5px 12px; border-radius: 8px;
        background: #eff6ff; transition: background .2s;
    }
    .fin-view-all:hover { background: #dbeafe; }

    /* ── RECENT PAYMENTS TABLE ── */
    .fin-tbl { width: 100%; border-collapse: collapse; }
    .fin-tbl th {
        padding: 10px 16px; text-align: left;
        font-size: .67rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: .07em;
        color: #94a3b8; background: #f8fafc;
        border-bottom: 1px solid #f1f5f9;
        white-space: nowrap;
    }
    .fin-tbl td {
        padding: 12px 16px; font-size: .81rem;
        color: #334155; border-bottom: 1px solid #f8fafc;
        vertical-align: middle;
    }
    .fin-tbl tbody tr:last-child td { border-bottom: none; }
    .fin-tbl tbody tr:hover td { background: #f8fbff; }

    .pay-status {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 10px; border-radius: 999px;
        font-size: .67rem; font-weight: 800;
    }
    .pay-status-pending  { background: #fef3c7; color: #b45309; }
    .pay-status-approved { background: #dcfce7; color: #16a34a; }
    .pay-status-rejected { background: #fee2e2; color: #dc2626; }

    .amount-cell {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-weight: 700; color: #0f172a; font-size: .85rem;
    }

    /* ── CHART BAR ── */
    .chart-wrap { padding: 20px; }
    .chart-bar-group { display: flex; flex-direction: column; gap: 14px; }
    .chart-bar-item { display: flex; flex-direction: column; gap: 5px; }
    .chart-bar-label {
        display: flex; justify-content: space-between;
        font-size: .75rem; font-weight: 600; color: #334155;
    }
    .chart-bar-label span:last-child { color: #2563eb; font-weight: 700; }
    .chart-bar-track {
        height: 10px; background: #f1f5f9;
        border-radius: 999px; overflow: hidden;
    }
    .chart-bar-fill {
        height: 100%; border-radius: 999px;
        background: linear-gradient(90deg, #2563eb, #4f46e5);
        transition: width 1s ease;
    }

    /* ── MONTHLY CHART ── */
    .month-chart { padding: 20px; }
    .month-bars { display: flex; align-items: flex-end; gap: 8px; height: 130px; }
    .month-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; }
    .month-bar {
        width: 100%; border-radius: 6px 6px 0 0;
        background: linear-gradient(to top, #2563eb, #60a5fa);
        transition: height 1s ease; min-height: 4px;
        position: relative; cursor: pointer;
    }
    .month-bar:hover { filter: brightness(1.1); }
    .month-lbl { font-size: .68rem; color: #94a3b8; font-weight: 600; }

    /* ── QUICK ACTIONS ── */
    .quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 16px; }
    .qa-btn {
        display: flex; align-items: center; gap: 10px;
        padding: 13px 14px; border-radius: 11px;
        font-size: .8rem; font-weight: 700;
        transition: all .2s; text-decoration: none;
        border: 1.5px solid transparent;
    }
    .qa-btn-green  { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
    .qa-btn-blue   { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .qa-btn-yellow { background: #fffbeb; color: #b45309; border-color: #fde68a; }
    .qa-btn-violet { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
    .qa-btn:hover  { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.08); }
    .qa-btn i      { font-size: 1rem; }

    /* ── PAGE HEADER ── */
    .fin-page-hdr {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 14px; margin-bottom: 26px;
    }
    .fin-page-hdr-left h1 {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.4rem; font-weight: 900; color: #0f172a;
        display: flex; align-items: center; gap: 10px;
    }
    .fin-hdr-ico {
        width: 38px; height: 38px; border-radius: 10px;
        background: linear-gradient(135deg,#10b981,#059669);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: .9rem;
        box-shadow: 0 4px 12px rgba(16,185,129,.35);
    }
    .fin-page-hdr-left p { color: #64748b; font-size: .83rem; margin-top: 4px; margin-left: 48px; }
    .fin-date-pill {
        display: flex; align-items: center; gap: 7px;
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 10px; padding: 8px 16px;
        font-size: .8rem; font-weight: 600; color: #334155;
        box-shadow: 0 2px 8px rgba(0,0,0,.04);
    }
    .fin-date-pill i { color: #2563eb; }

    /* ── SUMMARY BOX ── */
    .summary-box {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #1e1b4b 100%);
        border-radius: 16px; padding: 24px 22px; color: #fff;
        margin-bottom: 26px; position: relative; overflow: hidden;
    }
    .summary-box::before {
        content: ''; position: absolute;
        top: -60px; right: -60px; width: 200px; height: 200px;
        border-radius: 50%; background: rgba(99,102,241,.15);
    }
    .summary-box-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
    .summary-box-left h3 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: .8rem; font-weight: 700; color: rgba(255,255,255,.6); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 6px; }
    .summary-box-left .big-num { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2.4rem; font-weight: 900; color: #fff; line-height: 1; }
    .summary-box-left .big-num small { font-size: 1rem; color: #93c5fd; }
    .summary-box-left p { font-size: .78rem; color: rgba(255,255,255,.55); margin-top: 6px; }
    .summary-dividers { display: flex; gap: 28px; flex-wrap: wrap; }
    .sum-div { text-align: center; }
    .sum-div-num { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.3rem; font-weight: 800; color: #fff; }
    .sum-div-lbl { font-size: .69rem; color: rgba(255,255,255,.5); margin-top: 3px; }

    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- ── Page Header ── -->
            <div class="fin-page-hdr">
                <div class="fin-page-hdr-left">
                    <h1>
                        <span class="fin-hdr-ico"><i class="fas fa-rupee-sign"></i></span>
                        Finance Dashboard
                    </h1>
                    <p>Overview of payments, revenue and financial activity</p>
                </div>
                <div class="fin-date-pill">
                    <i class="fas fa-calendar-alt"></i>
                    <?= date('l, d F Y') ?>
                </div>
            </div>

            <!-- ── Revenue Summary Banner ── -->
            <div class="summary-box">
                <div class="summary-box-inner">
                    <div class="summary-box-left">
                        <h3>Total Revenue Collected</h3>
                        <div class="big-num"><small>₹</small><?= number_format($total_revenue, 2) ?></div>
                        <p>All time approved payments &nbsp;·&nbsp; <?= $approved_count ?> transactions</p>
                    </div>
                    <div class="summary-dividers">
                        <div class="sum-div">
                            <div class="sum-div-num">₹<?= number_format($monthly_rev, 0) ?></div>
                            <div class="sum-div-lbl">This Month</div>
                        </div>
                        <div class="sum-div">
                            <div class="sum-div-num">₹<?= number_format($total_gst, 0) ?></div>
                            <div class="sum-div-lbl">GST Collected</div>
                        </div>
                        <div class="sum-div">
                            <div class="sum-div-num"><?= $paid_students ?></div>
                            <div class="sum-div-lbl">Paying Students</div>
                        </div>
                        <div class="sum-div">
                            <div class="sum-div-num"><?= $pending_count ?></div>
                            <div class="sum-div-lbl">Pending</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Stat Cards Row 1 ── -->
            <div class="fin-stat-grid">
                <div class="fin-stat fin-stat-green">
                    <div class="fin-stat-top">
                        <div class="fin-stat-ico" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-check-circle"></i></div>
                        <span class="fin-stat-trend" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-arrow-up"></i> Approved</span>
                    </div>
                    <div class="fin-stat-num">₹<?= number_format($total_revenue, 0) ?></div>
                    <div class="fin-stat-lbl">Total Revenue Collected</div>
                </div>
                <div class="fin-stat fin-stat-yellow">
                    <div class="fin-stat-top">
                        <div class="fin-stat-ico" style="background:#fffbeb;color:#b45309"><i class="fas fa-clock"></i></div>
                        <span class="fin-stat-trend" style="background:#fffbeb;color:#b45309"><i class="fas fa-hourglass-half"></i> Pending</span>
                    </div>
                    <div class="fin-stat-num"><?= $pending_count ?></div>
                    <div class="fin-stat-lbl">Payments Awaiting Approval</div>
                </div>
                <div class="fin-stat fin-stat-blue">
                    <div class="fin-stat-top">
                        <div class="fin-stat-ico" style="background:#eff6ff;color:#2563eb"><i class="fas fa-calendar-check"></i></div>
                        <span class="fin-stat-trend" style="background:#eff6ff;color:#2563eb"><i class="fas fa-clock"></i> Month</span>
                    </div>
                    <div class="fin-stat-num">₹<?= number_format($monthly_rev, 0) ?></div>
                    <div class="fin-stat-lbl">Revenue This Month</div>
                </div>
                <div class="fin-stat fin-stat-red">
                    <div class="fin-stat-top">
                        <div class="fin-stat-ico" style="background:#fef2f2;color:#dc2626"><i class="fas fa-times-circle"></i></div>
                        <span class="fin-stat-trend" style="background:#fef2f2;color:#dc2626"><i class="fas fa-ban"></i> Rejected</span>
                    </div>
                    <div class="fin-stat-num"><?= $rejected_count ?></div>
                    <div class="fin-stat-lbl">Rejected Payments</div>
                </div>
            </div>

            <!-- ── Stat Cards Row 2 (mini) ── -->
            <div class="fin-stat-grid2">
                <div class="fin-mini">
                    <div class="fin-mini-ico" style="background:#f5f3ff;color:#7c3aed"><i class="fas fa-rupee-sign"></i></div>
                    <div>
                        <div class="fin-mini-num">₹<?= number_format($total_gst, 0) ?></div>
                        <div class="fin-mini-lbl">Total GST Collected</div>
                    </div>
                </div>
                <div class="fin-mini">
                    <div class="fin-mini-ico" style="background:#fff7ed;color:#ea580c"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <div class="fin-mini-num">₹<?= number_format($balance_due, 0) ?></div>
                        <div class="fin-mini-lbl">Balance Due (Partial)</div>
                    </div>
                </div>
                <div class="fin-mini">
                    <div class="fin-mini-ico" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="fin-mini-num"><?= $paid_students ?></div>
                        <div class="fin-mini-lbl">Students Who Paid</div>
                    </div>
                </div>
                <div class="fin-mini">
                    <div class="fin-mini-ico" style="background:#fdf4ff;color:#9333ea"><i class="fas fa-percentage"></i></div>
                    <div>
                        <div class="fin-mini-num"><?= $partial_count ?></div>
                        <div class="fin-mini-lbl">Partial Payments</div>
                    </div>
                </div>
            </div>

            <!-- ── Internship Payments Revenue Row ── -->
            <div class="fin-stat-grid" style="margin-bottom:26px;">
                <div class="fin-stat fin-stat-teal">
                    <div class="fin-stat-top">
                        <div class="fin-stat-ico" style="background:#f0fdf4;color:#0d9488"><i class="fas fa-briefcase"></i></div>
                        <span class="fin-stat-trend" style="background:#f0fdf4;color:#0d9488"><i class="fas fa-check"></i> Approved</span>
                    </div>
                    <div class="fin-stat-num">₹<?= number_format($ip_revenue, 0) ?></div>
                    <div class="fin-stat-lbl">Internship Revenue Collected</div>
                </div>
                <div class="fin-stat fin-stat-yellow" style="position:relative;">
                    <?php if($ip_pending > 0): ?>
                    <span style="position:absolute;top:14px;right:14px;background:#ef4444;color:#fff;font-size:.62rem;font-weight:800;padding:3px 8px;border-radius:999px;letter-spacing:.04em;animation:pulse 2s infinite;"><?= $ip_pending ?> PENDING</span>
                    <?php endif; ?>
                    <div class="fin-stat-top">
                        <div class="fin-stat-ico" style="background:#fffbeb;color:#b45309"><i class="fas fa-hourglass-half"></i></div>
                    </div>
                    <div class="fin-stat-num">₹<?= number_format($ip_pend_amt, 0) ?></div>
                    <div class="fin-stat-lbl">Internship Pending Amount</div>
                </div>
                <div class="fin-stat fin-stat-blue">
                    <div class="fin-stat-top">
                        <div class="fin-stat-ico" style="background:#eff6ff;color:#2563eb"><i class="fas fa-calendar-check"></i></div>
                        <span class="fin-stat-trend" style="background:#eff6ff;color:#2563eb"><i class="fas fa-clock"></i> Month</span>
                    </div>
                    <div class="fin-stat-num">₹<?= number_format($ip_month, 0) ?></div>
                    <div class="fin-stat-lbl">Internship Revenue This Month</div>
                </div>
                <div class="fin-stat fin-stat-green">
                    <div class="fin-stat-top">
                        <div class="fin-stat-ico" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-user-check"></i></div>
                    </div>
                    <div class="fin-stat-num"><?= $ip_approved ?></div>
                    <div class="fin-stat-lbl">Internship Students Enrolled</div>
                </div>
            </div>

            <!-- ── Main Grid ── -->
            <div class="fin-grid">

                <!-- Recent Payments Table -->
                <div class="fin-card">
                    <div class="fin-card-head">
                        <div class="fin-card-title"><i class="fas fa-list-alt"></i> Recent Payments</div>
                        <a href="payments.php" class="fin-view-all"><i class="fas fa-arrow-right"></i> View All</a>
                    </div>
                    <div style="overflow-x:auto;">
                    <table class="fin-tbl">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(empty($recent_payments)): ?>
                            <tr><td colspan="5" style="text-align:center;padding:32px;color:#94a3b8;">No payments yet</td></tr>
                        <?php else: ?>
                        <?php foreach($recent_payments as $p): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700;color:#0f172a;font-size:.82rem;"><?= htmlspecialchars($p['student_name']) ?></div>
                                    <div style="font-size:.72rem;color:#94a3b8;"><?= htmlspecialchars($p['email']) ?></div>
                                </td>
                                <td style="font-size:.78rem;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($p['course_title']) ?>">
                                    <?= htmlspecialchars($p['course_title']) ?>
                                </td>
                                <td class="amount-cell">
                                    ₹<?= number_format($p['paid_amount'] > 0 ? $p['paid_amount'] : $p['total_amount'], 0) ?>
                                </td>
                                <td>
                                    <span class="pay-status pay-status-<?= $p['status'] ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td style="font-size:.73rem;color:#64748b;white-space:nowrap;">
                                    <?php $dt=new DateTime($p['created_at'],new DateTimeZone('UTC'));$dt->setTimezone(new DateTimeZone('Asia/Kolkata'));echo $dt->format('d M Y'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Right Column -->
                <div style="display:flex;flex-direction:column;gap:18px;">

                    <!-- Quick Actions -->
                    <div class="fin-card">
                        <div class="fin-card-head">
                            <div class="fin-card-title"><i class="fas fa-bolt"></i> Quick Actions</div>
                        </div>
                        <div class="quick-actions">
                            <a href="payments.php?filter=pending" class="qa-btn qa-btn-yellow">
                                <i class="fas fa-clock"></i> Pending Payments
                            </a>
                            <a href="payments.php?filter=approved" class="qa-btn qa-btn-green">
                                <i class="fas fa-check"></i> Approved
                            </a>
                            <a href="payments.php?filter=rejected" class="qa-btn qa-btn-blue" style="background:#eff6ff;color:#2563eb;border-color:#bfdbfe;">
                                <i class="fas fa-times"></i> Rejected
                            </a>
                            <a href="profile.php" class="qa-btn qa-btn-violet">
                                <i class="fas fa-user-circle"></i> My Profile
                            </a>
                            <a href="internship_payments.php" class="qa-btn" style="background:#f0fdf4;color:#0d9488;border-color:#99f6e4;grid-column:span 2;position:relative;">
                                <?php if($ip_pending > 0): ?>
                                <span style="position:absolute;top:8px;right:10px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:800;padding:2px 7px;border-radius:999px;animation:pulse 2s infinite;"><?= $ip_pending ?> PENDING</span>
                                <?php endif; ?>
                                <i class="fas fa-briefcase"></i> Internship Payments &nbsp;<span style="font-size:.72rem;color:#0d9488;font-weight:500;">₹<?= number_format($ip_revenue,0) ?> collected</span>
                            </a>
                        </div>
                    </div>

                    <!-- Monthly Revenue Chart -->
                    <div class="fin-card">
                        <div class="fin-card-head">
                            <div class="fin-card-title"><i class="fas fa-chart-bar"></i> Last 6 Months</div>
                        </div>
                        <div class="month-chart">
                            <?php
                            $max_rev = max(array_column($monthly_data, 'rev'));
                            if($max_rev == 0) $max_rev = 1;
                            ?>
                            <div class="month-bars">
                                <?php foreach($monthly_data as $m): ?>
                                <div class="month-bar-wrap">
                                    <div class="month-bar" style="height:<?= round(($m['rev']/$max_rev)*110) ?>px;"
                                         title="₹<?= number_format($m['rev'],0) ?>"></div>
                                    <div class="month-lbl"><?= $m['month'] ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ── Revenue by Course ── -->
            <?php if(!empty($course_revenues)): ?>
            <div class="fin-card">
                <div class="fin-card-head">
                    <div class="fin-card-title"><i class="fas fa-book"></i> Revenue by Course</div>
                </div>
                <div class="chart-wrap">
                    <div class="chart-bar-group">
                        <?php
                        $max_course_rev = max(array_column($course_revenues, 'revenue'));
                        if($max_course_rev == 0) $max_course_rev = 1;
                        foreach($course_revenues as $cr): ?>
                        <div class="chart-bar-item">
                            <div class="chart-bar-label">
                                <span><?= htmlspecialchars($cr['title']) ?> <span style="color:#94a3b8;font-weight:500;">(<?= $cr['enrollments'] ?> students)</span></span>
                                <span>₹<?= number_format($cr['revenue'], 0) ?></span>
                            </div>
                            <div class="chart-bar-track">
                                <div class="chart-bar-fill" style="width:<?= round(($cr['revenue']/$max_course_rev)*100) ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /content-wrapper -->
    </div><!-- /main -->
</div><!-- /dashboard -->
</body>
</html>