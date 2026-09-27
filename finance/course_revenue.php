<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("finance");


// ── Selected course filter ───────────────────────────────────────────────
$selected_course_id = isset($_GET['course_id']) && is_numeric($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// ── All courses that have at least one approved payment ──────────────────
$courses_res = $conn->query("
    SELECT
        c.id,
        c.title,
        c.price,
        COUNT(p.id)                          AS total_students,
        COALESCE(SUM(p.paid_amount), 0)      AS total_collected,
        COALESCE(SUM(p.total_amount), 0)     AS total_billed,
        COALESCE(SUM(p.gst_amount), 0)       AS total_gst,
        COALESCE(SUM(p.pending_amount), 0)   AS total_pending,
        SUM(p.payment_type = 'full')         AS full_payments,
        SUM(p.payment_type = 'partial')      AS partial_payments
    FROM courses c
    JOIN payments p ON p.course_id = c.id AND p.status = 'approved'
    GROUP BY c.id, c.title, c.price
    ORDER BY total_collected DESC
");
$courses = [];
while ($row = $courses_res->fetch_assoc()) $courses[] = $row;

// ── Grand totals ─────────────────────────────────────────────────────────
$grand_res = $conn->query("
    SELECT
        COALESCE(SUM(paid_amount),0)    AS grand_collected,
        COALESCE(SUM(total_amount),0)   AS grand_billed,
        COALESCE(SUM(gst_amount),0)     AS grand_gst,
        COALESCE(SUM(pending_amount),0) AS grand_pending,
        COUNT(DISTINCT course_id)       AS active_courses,
        COUNT(DISTINCT user_id)         AS total_students
    FROM payments WHERE status='approved'
");
$grand = $grand_res->fetch_assoc();

// ── Students for selected course ─────────────────────────────────────────
$students = [];
$selected_course = null;
if ($selected_course_id) {
    $sc_res = $conn->prepare("SELECT id, title, price FROM courses WHERE id=?");
    $sc_res->bind_param("i", $selected_course_id);
    $sc_res->execute();
    $selected_course = $sc_res->get_result()->fetch_assoc();

    $st_res = $conn->prepare("
        SELECT
            u.name, u.email, u.mobile,
            p.payment_type, p.payment_status,
            p.base_amount, p.gst_rate, p.gst_amount,
            p.total_amount, p.paid_amount, p.pending_amount,
            p.utr_number, p.created_at, p.status AS pay_status
        FROM payments p
        JOIN users u ON p.user_id = u.id
        WHERE p.course_id = ? AND p.status = 'approved'
        ORDER BY p.created_at DESC
    ");
    $st_res->bind_param("i", $selected_course_id);
    $st_res->execute();
    $sr = $st_res->get_result();
    while ($row = $sr->fetch_assoc()) $students[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Revenue — CourseFlow Finance</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png?v=2">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    /* ── Grand summary cards ── */
    .cr-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }
    .cr-stat {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 16px;
        padding: 18px 20px;
        box-shadow: 0 2px 10px rgba(15,23,42,.04);
        position: relative;
        overflow: hidden;
    }
    .cr-stat::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        border-radius: 16px 16px 0 0;
    }
    .cr-stat.green::before  { background: linear-gradient(90deg,#10b981,#059669); }
    .cr-stat.blue::before   { background: linear-gradient(90deg,#3b82f6,#1d4ed8); }
    .cr-stat.amber::before  { background: linear-gradient(90deg,#f59e0b,#d97706); }
    .cr-stat.red::before    { background: linear-gradient(90deg,#ef4444,#dc2626); }
    .cr-stat.purple::before { background: linear-gradient(90deg,#8b5cf6,#6d28d9); }
    .cr-stat.teal::before   { background: linear-gradient(90deg,#06b6d4,#0891b2); }
    .cr-stat-ico {
        width: 38px; height: 38px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: .9rem; margin-bottom: 12px;
    }
    .cr-stat.green  .cr-stat-ico { background:#f0fdf4; color:#10b981; }
    .cr-stat.blue   .cr-stat-ico { background:#eff6ff; color:#3b82f6; }
    .cr-stat.amber  .cr-stat-ico { background:#fffbeb; color:#f59e0b; }
    .cr-stat.red    .cr-stat-ico { background:#fef2f2; color:#ef4444; }
    .cr-stat.purple .cr-stat-ico { background:#f5f3ff; color:#8b5cf6; }
    .cr-stat.teal   .cr-stat-ico { background:#ecfeff; color:#06b6d4; }
    .cr-stat-val {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.35rem; font-weight: 900; color: #0f172a;
        line-height: 1.1; margin-bottom: 4px;
    }
    .cr-stat-lbl { font-size: .74rem; font-weight: 600; color: #94a3b8; }

    /* ── Course cards grid ── */
    .cr-courses-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
        gap: 18px;
        margin-bottom: 32px;
    }
    .cr-course-card {
        background: #fff;
        border: 2px solid #eef2f7;
        border-radius: 18px;
        padding: 22px;
        box-shadow: 0 2px 12px rgba(15,23,42,.05);
        cursor: pointer;
        transition: all .22s;
        text-decoration: none;
        display: block;
        position: relative;
        overflow: hidden;
    }
    .cr-course-card::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(16,185,129,.03), transparent);
        opacity: 0;
        transition: opacity .22s;
    }
    .cr-course-card:hover {
        border-color: #10b981;
        box-shadow: 0 8px 28px rgba(16,185,129,.15);
        transform: translateY(-2px);
    }
    .cr-course-card:hover::after { opacity: 1; }
    .cr-course-card.active-card {
        border-color: #10b981;
        background: linear-gradient(135deg,#f0fdf4,#fff);
        box-shadow: 0 8px 28px rgba(16,185,129,.18);
    }

    .cr-card-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .94rem; font-weight: 800; color: #0f172a;
        margin-bottom: 4px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cr-card-price {
        font-size: .76rem; color: #64748b; margin-bottom: 16px;
    }
    .cr-card-stats {
        display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
        margin-bottom: 14px;
    }
    .cr-card-stat { background: #f8fafc; border-radius: 10px; padding: 10px 12px; }
    .cr-card-stat-val {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1rem; font-weight: 800; color: #0f172a;
    }
    .cr-card-stat-val.green { color: #059669; }
    .cr-card-stat-val.red   { color: #dc2626; }
    .cr-card-stat-lbl { font-size: .68rem; font-weight: 600; color: #94a3b8; margin-top: 2px; }

    .cr-revenue-bar-wrap {
        background: #f1f5f9; border-radius: 999px;
        height: 6px; overflow: hidden; margin-bottom: 10px;
    }
    .cr-revenue-bar {
        height: 100%; border-radius: 999px;
        background: linear-gradient(90deg,#10b981,#059669);
        transition: width .6s ease;
    }

    .cr-card-footer {
        display: flex; align-items: center; justify-content: space-between;
        font-size: .74rem;
    }
    .cr-pill {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 9px; border-radius: 999px;
        font-size: .7rem; font-weight: 700;
    }
    .cr-pill.full    { background: #dcfce7; color: #065f46; }
    .cr-pill.partial { background: #fef3c7; color: #92400e; }
    .cr-view-btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 8px;
        background: #f0fdf4; color: #059669;
        font-size: .73rem; font-weight: 700;
        border: 1px solid #bbf7d0;
        transition: all .18s;
    }
    .cr-course-card:hover .cr-view-btn,
    .cr-course-card.active-card .cr-view-btn {
        background: #10b981; color: #fff; border-color: #10b981;
    }

    /* ── Students table section ── */
    .cr-students-section {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 18px;
        box-shadow: 0 2px 12px rgba(15,23,42,.05);
        overflow: hidden;
        margin-bottom: 28px;
        animation: fadeInUp .3s ease;
    }
    @keyframes fadeInUp {
        from { opacity:0; transform:translateY(12px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .cr-students-head {
        padding: 20px 24px;
        background: linear-gradient(135deg,#0f172a,#1e293b);
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
    }
    .cr-students-head h2 {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1rem; font-weight: 800; color: #fff;
        display: flex; align-items: center; gap: 8px; margin: 0;
    }
    .cr-students-head h2 i { color: #10b981; }
    .cr-course-totals {
        display: flex; gap: 20px; flex-wrap: wrap;
    }
    .cr-ctotal { text-align: right; }
    .cr-ctotal-val {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .95rem; font-weight: 800;
    }
    .cr-ctotal-lbl { font-size: .68rem; color: #94a3b8; font-weight: 600; }

    /* Table */
    .cr-tbl { width: 100%; border-collapse: collapse; min-width: 820px; }
    .cr-tbl th {
        padding: 11px 16px;
        font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em;
        color: #94a3b8; background: #f8fafc;
        border-bottom: 1px solid #f1f5f9;
        text-align: left; white-space: nowrap;
    }
    .cr-tbl td {
        padding: 13px 16px; font-size: .82rem; color: #334155;
        border-bottom: 1px solid #f8fafc; vertical-align: middle;
    }
    .cr-tbl tbody tr:last-child td { border-bottom: none; }
    .cr-tbl tbody tr:hover td { background: #f8fbff; }

    /* Summary bar at bottom of table */
    .cr-tbl-summary {
        padding: 16px 24px;
        background: #f8fafc;
        border-top: 2px solid #e2e8f0;
        display: flex; align-items: center; justify-content: flex-end; gap: 28px; flex-wrap: wrap;
    }
    .cr-tbl-summary-item { text-align: right; }
    .cr-tbl-summary-val {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1rem; font-weight: 900;
    }
    .cr-tbl-summary-lbl { font-size: .7rem; color: #64748b; font-weight: 600; }

    /* Empty state */
    .cr-empty {
        text-align: center; padding: 56px 24px;
    }
    .cr-empty-ico {
        width: 64px; height: 64px; border-radius: 18px;
        background: #f1f5f9;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.6rem; color: #cbd5e1;
        margin: 0 auto 16px;
    }

    /* Page header */
    .cr-page-hdr { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:26px; }
    .cr-page-hdr h1 { font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:10px; margin:0; }
    .cr-hdr-ico { width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;box-shadow:0 4px 12px rgba(16,185,129,.35); }

    .cr-section-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .78rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: .08em; color: #94a3b8;
        margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
    }
    .cr-section-title::after {
        content: ''; flex: 1; height: 1px; background: #f1f5f9;
    }

    /* ── Mobile responsive fixes ── */
    @media(max-width:768px){
        /* Course cards: minmax(310px) is too wide for phones — drop to 1 col */
        .cr-courses-grid { grid-template-columns: 1fr; }

        /* Stat values: scale down large numbers for 2-col on phones */
        .cr-stat-val { font-size: 1.1rem; }
        .cr-stat { padding: 14px 16px; }
    }
    @media(max-width:480px){
        /* Summary cards: ensure 2-col fits on 320px */
        .cr-summary-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .cr-stat-val { font-size: 1rem; }
    }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- Page Header -->
            <div class="cr-page-hdr">
                <div>
                    <h1>
                        <span class="cr-hdr-ico"><i class="fas fa-chart-bar"></i></span>
                        Course-wise Revenue
                    </h1>
                    <p style="color:#64748b;font-size:.83rem;margin-top:4px;margin-left:48px;">Track revenue and student payments by course</p>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <a href="payments.php" style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:.82rem;font-weight:700;text-decoration:none;">
                        <i class="fas fa-credit-card"></i> Payments
                    </a>
                    <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:.82rem;font-weight:700;text-decoration:none;">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>

            <!-- Grand Summary Cards -->
            <div class="cr-section-title"><i class="fas fa-globe"></i> Overall Summary</div>
            <div class="cr-summary-grid">
                <div class="cr-stat green">
                    <div class="cr-stat-ico"><i class="fas fa-rupee-sign"></i></div>
                    <div class="cr-stat-val">₹<?= number_format((float)$grand['grand_collected'], 2) ?></div>
                    <div class="cr-stat-lbl">Total Collected</div>
                </div>
                <div class="cr-stat blue">
                    <div class="cr-stat-ico"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="cr-stat-val">₹<?= number_format((float)$grand['grand_billed'], 2) ?></div>
                    <div class="cr-stat-lbl">Total Billed</div>
                </div>
                <div class="cr-stat amber">
                    <div class="cr-stat-ico"><i class="fas fa-percent"></i></div>
                    <div class="cr-stat-val">₹<?= number_format((float)$grand['grand_gst'], 2) ?></div>
                    <div class="cr-stat-lbl">GST Collected</div>
                </div>
                <div class="cr-stat red">
                    <div class="cr-stat-ico"><i class="fas fa-clock"></i></div>
                    <div class="cr-stat-val">₹<?= number_format((float)$grand['grand_pending'], 2) ?></div>
                    <div class="cr-stat-lbl">Balance Due</div>
                </div>
                <div class="cr-stat purple">
                    <div class="cr-stat-ico"><i class="fas fa-book-open"></i></div>
                    <div class="cr-stat-val"><?= (int)$grand['active_courses'] ?></div>
                    <div class="cr-stat-lbl">Active Courses</div>
                </div>
                <div class="cr-stat teal">
                    <div class="cr-stat-ico"><i class="fas fa-user-graduate"></i></div>
                    <div class="cr-stat-val"><?= (int)$grand['total_students'] ?></div>
                    <div class="cr-stat-lbl">Paying Students</div>
                </div>
            </div>

            <!-- Course Cards -->
            <?php if (empty($courses)): ?>
            <div class="cr-empty">
                <div class="cr-empty-ico"><i class="fas fa-inbox"></i></div>
                <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700;color:#334155;margin-bottom:6px;">No Revenue Data</h3>
                <p style="font-size:.84rem;color:#94a3b8;">No approved payments found for any course yet.</p>
            </div>
            <?php else: ?>

            <div class="cr-section-title"><i class="fas fa-th-large"></i> Courses — Click to view students</div>
            <?php
            $max_rev = max(array_column($courses, 'total_collected')) ?: 1;
            ?>
            <div class="cr-courses-grid">
                <?php foreach ($courses as $course):
                    $pct = round(($course['total_collected'] / $max_rev) * 100);
                    $is_active = ($selected_course_id == $course['id']);
                ?>
                <a href="course_revenue.php?course_id=<?= $course['id'] ?>#students-section"
                   class="cr-course-card <?= $is_active ? 'active-card' : '' ?>">

                    <?php if ($is_active): ?>
                    <div style="position:absolute;top:14px;right:14px;width:22px;height:22px;border-radius:50%;background:#10b981;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-check" style="color:#fff;font-size:.6rem;"></i>
                    </div>
                    <?php endif; ?>

                    <div class="cr-card-title" title="<?= htmlspecialchars($course['title']) ?>">
                        <?= htmlspecialchars($course['title']) ?>
                    </div>
                    <div class="cr-card-price">
                        Base price: ₹<?= number_format((float)$course['price'], 2) ?>
                    </div>

                    <div class="cr-card-stats">
                        <div class="cr-card-stat">
                            <div class="cr-card-stat-val green">₹<?= number_format((float)$course['total_collected'], 2) ?></div>
                            <div class="cr-card-stat-lbl">Collected</div>
                        </div>
                        <div class="cr-card-stat">
                            <div class="cr-card-stat-val"><?= (int)$course['total_students'] ?></div>
                            <div class="cr-card-stat-lbl">Students</div>
                        </div>
                        <div class="cr-card-stat">
                            <div class="cr-card-stat-val" style="color:#d97706;">₹<?= number_format((float)$course['total_gst'], 2) ?></div>
                            <div class="cr-card-stat-lbl">GST</div>
                        </div>
                        <div class="cr-card-stat">
                            <div class="cr-card-stat-val <?= $course['total_pending'] > 0 ? 'red' : 'green' ?>">
                                ₹<?= number_format((float)$course['total_pending'], 2) ?>
                            </div>
                            <div class="cr-card-stat-lbl">Balance Due</div>
                        </div>
                    </div>

                    <div class="cr-revenue-bar-wrap">
                        <div class="cr-revenue-bar" style="width:<?= $pct ?>%;"></div>
                    </div>

                    <div class="cr-card-footer">
                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                            <?php if ($course['full_payments'] > 0): ?>
                            <span class="cr-pill full"><i class="fas fa-check-double"></i> <?= (int)$course['full_payments'] ?> Full</span>
                            <?php endif; ?>
                            <?php if ($course['partial_payments'] > 0): ?>
                            <span class="cr-pill partial"><i class="fas fa-layer-group"></i> <?= (int)$course['partial_payments'] ?> EMI</span>
                            <?php endif; ?>
                        </div>
                        <span class="cr-view-btn">
                            <i class="fas <?= $is_active ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                            <?= $is_active ? 'Viewing' : 'View Students' ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Students Table for Selected Course -->
            <?php if ($selected_course_id && $selected_course): ?>
            <div id="students-section" class="cr-students-section">

                <!-- Section Header -->
                <div class="cr-students-head">
                    <h2>
                        <i class="fas fa-users"></i>
                        <?= htmlspecialchars($selected_course['title']) ?>
                        <span style="background:rgba(16,185,129,.2);color:#10b981;font-size:.72rem;padding:2px 10px;border-radius:999px;font-weight:700;">
                            <?= count($students) ?> student<?= count($students)!=1?'s':'' ?>
                        </span>
                    </h2>
                    <?php
                    $s_collected = array_sum(array_column($students, 'paid_amount'));
                    $s_billed    = array_sum(array_column($students, 'total_amount'));
                    $s_gst       = array_sum(array_column($students, 'gst_amount'));
                    $s_pending   = array_sum(array_column($students, 'pending_amount'));
                    ?>
                    <div class="cr-course-totals">
                        <div class="cr-ctotal">
                            <div class="cr-ctotal-val" style="color:#10b981;">₹<?= number_format($s_collected, 2) ?></div>
                            <div class="cr-ctotal-lbl">Collected</div>
                        </div>
                        <div class="cr-ctotal" style="border-left:1px solid rgba(255,255,255,.1);padding-left:20px;">
                            <div class="cr-ctotal-val" style="color:#f59e0b;">₹<?= number_format($s_gst, 2) ?></div>
                            <div class="cr-ctotal-lbl">GST</div>
                        </div>
                        <div class="cr-ctotal" style="border-left:1px solid rgba(255,255,255,.1);padding-left:20px;">
                            <div class="cr-ctotal-val" style="color:#60a5fa;">₹<?= number_format($s_billed, 2) ?></div>
                            <div class="cr-ctotal-lbl">Total Billed</div>
                        </div>
                        <?php if ($s_pending > 0): ?>
                        <div class="cr-ctotal" style="border-left:1px solid rgba(255,255,255,.1);padding-left:20px;">
                            <div class="cr-ctotal-val" style="color:#f87171;">₹<?= number_format($s_pending, 2) ?></div>
                            <div class="cr-ctotal-lbl">Balance Due</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($students)): ?>
                <div class="cr-empty">
                    <div class="cr-empty-ico"><i class="fas fa-user-slash"></i></div>
                    <p style="font-size:.84rem;color:#94a3b8;">No approved student payments for this course.</p>
                </div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="cr-tbl">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Fee Breakdown</th>
                                <th>Type</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>UTR / Txn ID</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; foreach ($students as $s):
                            $base_a  = (float)($s['base_amount']    ?: $selected_course['price']);
                            $gst_r   = (float)($s['gst_rate']       ?: 18);
                            $gst_a   = (float)($s['gst_amount']     ?: round($base_a * $gst_r / 100, 2));
                            $total_a = (float)($s['total_amount']   ?: round($base_a + $gst_a, 2));
                            $paid_a  = (float)$s['paid_amount'];
                            $pend_a  = (float)$s['pending_amount'];
                            $ptype   = $s['payment_type'] ?? 'full';
                            $pstatus = $s['payment_status'] ?? 'paid';
                        ?>
                        <tr>
                            <td style="color:#cbd5e1;font-size:.72rem;font-weight:600;"><?= $i++ ?></td>
                            <td>
                                <div style="font-weight:700;color:#0f172a;font-size:.85rem;"><?= htmlspecialchars($s['name']) ?></div>
                                <div style="font-size:.73rem;color:#94a3b8;"><?= htmlspecialchars($s['email']) ?></div>
                                <?php if (!empty($s['mobile'])): ?>
                                <div style="font-size:.72rem;color:#94a3b8;"><i class="fas fa-phone" style="font-size:.6rem;"></i> <?= htmlspecialchars($s['mobile']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:.75rem;color:#64748b;">Base: ₹<?= number_format($base_a, 2) ?></div>
                                <div style="font-size:.75rem;color:#d97706;">GST(<?= $gst_r ?>%): +₹<?= number_format($gst_a, 2) ?></div>
                                <div style="font-size:.8rem;font-weight:700;color:#1e40af;">Total: ₹<?= number_format($total_a, 2) ?></div>
                            </td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:4px;font-size:.71rem;font-weight:700;padding:3px 9px;border-radius:999px;<?= $ptype==='full' ? 'background:#dcfce7;color:#065f46;' : 'background:#fef3c7;color:#92400e;' ?>">
                                    <i class="fas <?= $ptype==='full' ? 'fa-check-double' : 'fa-layer-group' ?>"></i>
                                    <?= $ptype==='full' ? 'Full' : '50% EMI' ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;font-weight:800;color:#059669;">
                                    ₹<?= number_format($paid_a, 2) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($pend_a > 0): ?>
                                <span style="font-size:.78rem;font-weight:700;color:#dc2626;">
                                    <i class="fas fa-clock"></i> ₹<?= number_format($pend_a, 2) ?>
                                </span>
                                <?php else: ?>
                                <span style="font-size:.76rem;color:#059669;">
                                    <i class="fas fa-check-circle"></i> Nil
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $pstatus_colors = [
                                    'paid'    => 'background:#dcfce7;color:#065f46;',
                                    'partial' => 'background:#fef3c7;color:#92400e;',
                                    'pending' => 'background:#f1f5f9;color:#64748b;',
                                ];
                                $pstatus_icons = [
                                    'paid'    => 'fa-check-double',
                                    'partial' => 'fa-clock',
                                    'pending' => 'fa-hourglass',
                                ];
                                ?>
                                <span style="<?= $pstatus_colors[$pstatus] ?? '' ?>;display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:.71rem;font-weight:700;">
                                    <i class="fas <?= $pstatus_icons[$pstatus] ?? 'fa-question' ?>"></i>
                                    <?= ucfirst($pstatus) ?>
                                </span>
                            </td>
                            <td style="font-family:'DM Mono',monospace;font-size:.78rem;color:#64748b;">
                                <?= htmlspecialchars($s['utr_number'] ?? '—') ?>
                            </td>
                            <td style="font-size:.76rem;color:#64748b;white-space:nowrap;">
                                <?php $dt=new DateTime($s['created_at'],new DateTimeZone('UTC'));$dt->setTimezone(new DateTimeZone('Asia/Kolkata'));echo $dt->format('d M Y'); ?><br>
                                <span style="font-size:.68rem;color:#94a3b8;"><?= $dt->format('h:i A') ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Summary Footer -->
                <div class="cr-tbl-summary">
                    <div style="flex:1;font-size:.8rem;color:#64748b;">
                        <i class="fas fa-info-circle"></i>
                        Showing <?= count($students) ?> approved payment<?= count($students)!=1?'s':'' ?> for <strong><?= htmlspecialchars($selected_course['title']) ?></strong>
                    </div>
                    <div class="cr-tbl-summary-item">
                        <div class="cr-tbl-summary-val" style="color:#d97706;">₹<?= number_format($s_gst, 2) ?></div>
                        <div class="cr-tbl-summary-lbl">Total GST</div>
                    </div>
                    <div class="cr-tbl-summary-item">
                        <div class="cr-tbl-summary-val" style="color:#dc2626;">₹<?= number_format($s_pending, 2) ?></div>
                        <div class="cr-tbl-summary-lbl">Total Balance Due</div>
                    </div>
                    <div class="cr-tbl-summary-item">
                        <div class="cr-tbl-summary-val" style="color:#059669;">₹<?= number_format($s_collected, 2) ?></div>
                        <div class="cr-tbl-summary-lbl">Total Collected</div>
                    </div>
                    <div class="cr-tbl-summary-item" style="background:#f0fdf4;padding:10px 16px;border-radius:10px;border:1.5px solid #bbf7d0;">
                        <div class="cr-tbl-summary-val" style="color:#1e40af;">₹<?= number_format($s_billed, 2) ?></div>
                        <div class="cr-tbl-summary-lbl" style="color:#3b82f6;">Course Total Billed</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; // end if courses not empty ?>

        </div>
    </div>
</div>

<script>
    
// Auto-scroll to students section when a course is selected
<?php if ($selected_course_id): ?>
window.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('students-section');
    if (el) {
        setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 120);
    }
});
<?php endif; ?>
</script>
</body>
</html>