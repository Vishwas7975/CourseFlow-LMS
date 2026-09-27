<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("student");

$user_id = $_SESSION['user_id'];
$page_title = "Pending Payments";

// Fetch all partial-approved payments with outstanding balance
$stmt = $conn->prepare("
    SELECT p.*, c.title AS course_title, c.price AS course_price
    FROM payments p
    JOIN courses c ON p.course_id = c.id
    WHERE p.user_id = ?
      AND p.status = 'approved'
      AND p.payment_status = 'partial'
      AND p.pending_amount > 0
    ORDER BY p.id DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_rows = $stmt->get_result();
$count = $pending_rows->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Payments — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
    .pend-card {
        background:#fff; border-radius:18px; box-shadow:0 4px 20px rgba(0,0,0,0.07);
        border:1px solid #e2e8f0; overflow:hidden; margin-bottom:20px; transition:box-shadow .2s;
    }
    .pend-card:hover { box-shadow:0 8px 32px rgba(0,0,0,0.10); }

    .pend-head {
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;
        padding:18px 22px; border-bottom:1px solid #f1f5f9;
        background:linear-gradient(135deg,#fefce8,#fff7ed);
    }
    .pend-course-icon {
        width:44px; height:44px; border-radius:13px;
        background:linear-gradient(135deg,#d97706,#f59e0b);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:1.05rem; flex-shrink:0;
    }
    .pend-title { font-size:0.97rem; font-weight:800; color:#1e293b; margin-bottom:2px; }
    .pend-meta  { font-size:0.76rem; color:#64748b; }
    .badge-partial {
        display:inline-flex; align-items:center; gap:5px;
        background:#fef3c7; color:#92400e; border:1px solid #fde68a;
        border-radius:999px; font-size:0.74rem; font-weight:700; padding:4px 11px;
    }

    .pend-body { padding:18px 22px; }

    /* Progress bar */
    .prog-wrap { margin-bottom:18px; }
    .prog-labels { display:flex; justify-content:space-between; font-size:0.78rem; font-weight:600; margin-bottom:7px; }
    .prog-paid { color:#059669; }
    .prog-due  { color:#dc2626; }
    .prog-bar  { height:9px; border-radius:999px; background:#fee2e2; overflow:hidden; }
    .prog-fill { height:100%; border-radius:999px; background:linear-gradient(90deg,#059669,#10b981); transition:width .7s ease; }
    .prog-pct  { font-size:0.7rem; color:#94a3b8; text-align:right; margin-top:4px; }

    /* Stats grid */
    .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px; margin-bottom:18px; }
    .stat-tile { background:#f8fafc; border-radius:12px; padding:13px 14px; border:1px solid #e2e8f0; text-align:center; }
    .stat-lbl  { font-size:0.68rem; color:#94a3b8; text-transform:uppercase; letter-spacing:.04em; font-weight:600; margin-bottom:5px; }
    .stat-val  { font-size:1.05rem; font-weight:800; color:#1e293b; }
    .stat-tile.paid-tile .stat-val  { color:#059669; }
    .stat-tile.due-tile  .stat-val  { color:#dc2626; }
    .stat-tile.total-tile .stat-val { color:#1e40af; }

    /* Breakdown */
    .breakdown { background:#f0f7ff; border:1px solid #bfdbfe; border-radius:13px; padding:14px 16px; margin-bottom:18px; }
    .bk-title  { font-size:0.7rem; font-weight:700; color:#1e40af; text-transform:uppercase; letter-spacing:.04em; margin-bottom:9px; }
    .bk-row    { display:flex; justify-content:space-between; align-items:center; padding:4px 0; font-size:0.82rem; }
    .bk-row.divider { border-top:1px solid #bfdbfe; margin-top:6px; padding-top:8px; }
    .bk-lbl { color:#64748b; font-weight:500; }
    .bk-val { font-weight:700; color:#1e293b; }
    .bk-val.gst-v    { color:#d97706; }
    .bk-val.total-v  { color:#1e40af; font-size:0.88rem; }
    .bk-val.paid-v   { color:#059669; }
    .bk-val.due-v    { color:#dc2626; }
    .gst-badge-sm { display:inline-flex;align-items:center;gap:3px;background:#fef3c7;color:#92400e;font-size:0.62rem;font-weight:700;padding:2px 5px;border-radius:999px;margin-left:3px; }

    /* Actions */
    .pend-actions { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
    .btn-continue {
        display:inline-flex; align-items:center; gap:6px; padding:9px 16px;
        border:2px solid #e2e8f0; border-radius:11px; font-size:0.82rem; font-weight:600;
        color:#64748b; text-decoration:none; background:#f8fafc; transition:all .2s;
    }
    .btn-continue:hover { border-color:#3b82f6; color:#3b82f6; }
    .btn-pay-now {
        display:inline-flex; align-items:center; gap:7px; padding:11px 22px;
        background:linear-gradient(135deg,#dc2626,#ef4444); color:#fff;
        border-radius:11px; font-size:0.88rem; font-weight:700; text-decoration:none;
        box-shadow:0 4px 14px rgba(220,38,38,.3); transition:all .2s;
    }
    .btn-pay-now:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(220,38,38,.4); }

    /* Empty state */
    .empty-pend { text-align:center; padding:64px 24px; }
    .ep-icon { width:80px; height:80px; border-radius:22px; background:#f0fdf4; display:inline-flex; align-items:center; justify-content:center; font-size:2rem; color:#16a34a; margin-bottom:18px; }

    /* Alert banner */
    .alert-banner { background:linear-gradient(135deg,#fffbeb,#fef9c3); border:1.5px solid #fde68a; border-radius:14px; padding:14px 18px; margin-bottom:22px; display:flex; align-items:center; gap:12px; }
    .ab-icon { width:38px; height:38px; border-radius:11px; background:#fef3c7; display:flex; align-items:center; justify-content:center; color:#d97706; font-size:1rem; flex-shrink:0; }

    /* ── Mobile responsive fixes ── */
    @media(max-width:600px){
        /* Card body: tighter padding on phones */
        .pend-body { padding:14px 16px; }
        .pend-head { padding:14px 16px; }

        /* Stat values: scale down ₹ amounts on 2-col grid */
        .stat-val { font-size:.88rem; }

        /* Action buttons: stack vertically, full width */
        .pend-actions { flex-direction:column; align-items:stretch; }
        .btn-pay-now, .btn-continue {
            width:100%; justify-content:center; min-height:44px;
        }

        /* Breakdown rows: allow long labels to wrap */
        .bk-row { flex-wrap:wrap; gap:4px; }

        /* Alert banner: stack icon and text on phones */
        .alert-banner { flex-direction:column; align-items:flex-start; }
    }
    @media(max-width:380px){
        /* Stat grid: force 2-col on very small screens */
        .stat-grid { grid-template-columns:1fr 1fr; }
        .stat-val { font-size:.8rem; }
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
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;color:#1e293b;margin:0 0 3px;display:flex;align-items:center;gap:10px">
            <span style="width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,#d97706,#f59e0b);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:0.9rem"><i class="fas fa-clock"></i></span>
            Pending Payments
        </h1>
        <p style="margin:0;color:#64748b;font-size:0.85rem">Courses with outstanding balance — pay remaining amount to clear dues</p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="payment_status.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:2px solid #e2e8f0;border-radius:11px;font-size:0.8rem;font-weight:600;color:#64748b;text-decoration:none;background:#fff"><i class="fas fa-receipt"></i> All Payments</a>
        <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:2px solid #e2e8f0;border-radius:11px;font-size:0.8rem;font-weight:600;color:#64748b;text-decoration:none;background:#fff"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</div>

<?php if ($count > 0): ?>

<div class="alert-banner">
    <div class="ab-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div>
        <strong style="color:#92400e;font-size:0.88rem">You have <?php echo $count; ?> pending balance<?php echo $count>1?'s':''; ?></strong><br>
        <span style="font-size:0.79rem;color:#78350f">Complete your remaining payments to retain full access and avoid any interruption.</span>
    </div>
</div>

<?php while ($row = $pending_rows->fetch_assoc()):
    $base       = (float)$row['base_amount'];
    $gst_r      = (float)$row['gst_rate'];
    $gst_a      = (float)$row['gst_amount'];
    $total      = (float)$row['total_amount'];
    $paid       = (float)$row['paid_amount'];
    $remaining  = (float)$row['pending_amount'];
    $pct_paid   = $total > 0 ? min(100, round(($paid/$total)*100)) : 0;
    // Fallback for old rows without GST columns
    if ($total <= 0) { $total = (float)$row['course_price']; $gst_r = 18.00; $gst_a = round($total*18/118,2); $base=$total-$gst_a; $paid=(float)$row['amount']; $remaining=round($total-$paid,2); $pct_paid=round(($paid/$total)*100); }
?>
<div class="pend-card">
    <div class="pend-head">
        <div style="display:flex;align-items:center;gap:12px">
            <div class="pend-course-icon"><i class="fas fa-book"></i></div>
            <div>
                <div class="pend-title"><?php echo htmlspecialchars($row['course_title']); ?></div>
                <div class="pend-meta">Payment #<?php echo $row['id']; ?> &bull; UTR: <span style="font-family:monospace"><?php echo htmlspecialchars($row['utr_number']); ?></span></div>
            </div>
        </div>
        <span class="badge-partial"><i class="fas fa-clock"></i> 50% Paid</span>
    </div>

    <div class="pend-body">
        <!-- Progress -->
        <div class="prog-wrap">
            <div class="prog-labels">
                <span class="prog-paid"><i class="fas fa-check-circle"></i> Paid: &#8377;<?php echo number_format($paid,2); ?></span>
                <span class="prog-due"><i class="fas fa-clock"></i> Due: &#8377;<?php echo number_format($remaining,2); ?></span>
            </div>
            <div class="prog-bar"><div class="prog-fill" style="width:<?php echo $pct_paid; ?>%"></div></div>
            <div class="prog-pct"><?php echo $pct_paid; ?>% paid</div>
        </div>

        <!-- Stats -->
        <div class="stat-grid">
            <div class="stat-tile total-tile"><div class="stat-lbl">Total Fee</div><div class="stat-val">&#8377;<?php echo number_format($total,2); ?></div></div>
            <div class="stat-tile paid-tile"><div class="stat-lbl">Paid</div><div class="stat-val">&#8377;<?php echo number_format($paid,2); ?></div></div>
            <div class="stat-tile due-tile"><div class="stat-lbl">Balance Due</div><div class="stat-val">&#8377;<?php echo number_format($remaining,2); ?></div></div>
            <div class="stat-tile"><div class="stat-lbl">GST (<?php echo $gst_r; ?>%)</div><div class="stat-val" style="color:#d97706">&#8377;<?php echo number_format($gst_a,2); ?></div></div>
        </div>

        <!-- Breakdown -->
        <div class="breakdown">
            <div class="bk-title"><i class="fas fa-file-invoice"></i> Complete Fee Breakdown</div>
            <div class="bk-row"><span class="bk-lbl">Course Base Price</span><span class="bk-val">&#8377;<?php echo number_format($base,2); ?></span></div>
            <div class="bk-row"><span class="bk-lbl">GST @ <?php echo $gst_r; ?>% <span class="gst-badge-sm">GST</span></span><span class="bk-val gst-v">+ &#8377;<?php echo number_format($gst_a,2); ?></span></div>
            <div class="bk-row divider"><span class="bk-lbl" style="font-weight:700">Total Payable</span><span class="bk-val total-v">&#8377;<?php echo number_format($total,2); ?></span></div>
            <div class="bk-row" style="margin-top:8px"><span class="bk-lbl">1st Installment Paid</span><span class="bk-val paid-v">&#8377;<?php echo number_format($paid,2); ?></span></div>
            <div class="bk-row"><span class="bk-lbl" style="color:#dc2626;font-weight:700">Remaining Balance</span><span class="bk-val due-v">&#8377;<?php echo number_format($remaining,2); ?></span></div>
        </div>

        <!-- Actions -->
        <div class="pend-actions">
            <a href="course_lessons.php?course_id=<?php echo (int)$row['course_id']; ?>" class="btn-continue"><i class="fas fa-play-circle"></i> Continue Course</a>
            <a href="payment.php?course_id=<?php echo (int)$row['course_id']; ?>" class="btn-pay-now"><i class="fas fa-credit-card"></i> Pay Remaining &#8377;<?php echo number_format($remaining,2); ?></a>
        </div>
    </div>
</div>
<?php endwhile; ?>

<?php else: ?>
<div class="empty-pend">
    <div class="ep-icon"><i class="fas fa-check-double"></i></div>
    <h2 style="color:#1e293b;margin:0 0 8px;font-size:1.25rem">No Pending Balances!</h2>
    <p style="color:#64748b;max-width:340px;margin:0 auto 22px;font-size:0.87rem">All your course payments are fully settled. Great job!</p>
    <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:7px;padding:11px 22px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;border-radius:11px;font-weight:700;text-decoration:none;font-size:0.88rem"><i class="fas fa-graduation-cap"></i> My Courses</a>
</div>
<?php endif; ?>

</div>
</div>
</div>
<?php include("../includes/footer.php"); ?>
</body>
</html>