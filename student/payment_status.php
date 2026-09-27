<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("student");

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT p.*, c.title, c.price
    FROM payments p
    JOIN courses c ON p.course_id = c.id
    WHERE p.user_id = ?
    ORDER BY p.id DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$page_title = "My Payments";
$success    = isset($_GET['success']) ? $_GET['success'] : '';
$error      = isset($_GET['error'])   ? $_GET['error']   : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
    .pay-type-chip {
        display:inline-flex;align-items:center;gap:4px;
        font-size:0.7rem;font-weight:700;padding:2px 7px;border-radius:999px;
    }
    .chip-full    { background:#dcfce7;color:#065f46; }
    .chip-partial { background:#fef3c7;color:#92400e; }
    .gst-tooltip {
        display:none;position:absolute;bottom:calc(100% + 6px);left:0;
        background:#1e293b;color:#fff;border-radius:10px;padding:10px 14px;
        font-size:0.77rem;white-space:nowrap;z-index:99;
        box-shadow:0 4px 16px rgba(0,0,0,.25);
    }
    .gst-cell { position:relative;cursor:pointer; }
    .gst-cell:hover .gst-tooltip { display:block; }
    </style>
</head>
<body>
<div class="dashboard">
<?php include("../includes/sidebar.php"); ?>
<div class="main">
<?php include("../includes/topbar.php"); ?>
<div class="content-wrapper">

<div class="page-header">
    <h1><i class="fas fa-receipt"></i> My Payment History</h1>
    <p>Track the status of your course payments including GST and installment details.</p>
</div>

<!-- Alerts -->
<?php if ($success === '1'): ?>
<div style="background:var(--success-light);border:1px solid rgba(16,185,129,.3);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:22px;display:flex;align-items:center;gap:10px;color:#065F46">
    <i class="fas fa-check-circle" style="font-size:1.3rem;color:#10B981"></i>
    <div><strong>Payment Submitted!</strong><br><span style="font-size:0.875rem">Your payment is under review. You will be enrolled once admin approves it.</span></div>
</div>
<?php elseif ($success === 'remaining'): ?>
<div style="background:var(--success-light);border:1px solid rgba(16,185,129,.3);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:22px;display:flex;align-items:center;gap:10px;color:#065F46">
    <i class="fas fa-check-circle" style="font-size:1.3rem;color:#10B981"></i>
    <div><strong>Remaining Payment Submitted!</strong><br><span style="font-size:0.875rem">Your remaining balance payment is under review and will be cleared shortly.</span></div>
</div>
<?php elseif ($error === 'duplicate'): ?>
<div style="background:var(--warning-light);border:1px solid rgba(245,158,11,.3);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:22px;display:flex;align-items:center;gap:10px;color:#92400E">
    <i class="fas fa-exclamation-triangle" style="font-size:1.3rem;color:#D97706"></i>
    <div><strong>Already Submitted</strong><br><span style="font-size:0.875rem">You already have an active payment for this course.</span></div>
</div>
<?php elseif ($error === 'invalid_remaining'): ?>
<div style="background:#FEE2E2;border:1px solid rgba(239,68,68,.3);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:22px;display:flex;align-items:center;gap:10px;color:#7F1D1D">
    <i class="fas fa-times-circle" style="font-size:1.3rem;color:#ef4444"></i>
    <div><strong>Invalid Request</strong><br><span style="font-size:0.875rem">No valid partial payment found to pay the remaining balance for.</span></div>
</div>
<?php endif; ?>

<div class="section-wrapper">
<?php if ($result->num_rows > 0): ?>
<div style="overflow-x:auto">
<table class="data-table" style="width:100%;border-collapse:collapse">
    <thead>
        <tr>
            <?php foreach(['#','Course','Fee Breakdown','Paid','Balance','UTR / Txn ID','Type','Status','Action'] as $col): ?>
            <th style="text-align:left;padding:11px 14px;border-bottom:2px solid var(--border-color);font-size:0.78rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);white-space:nowrap"><?php echo $col; ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php $i=1; while ($row = $result->fetch_assoc()):
        $base_a  = (float)($row['base_amount']    ?: $row['price']);
        $gst_r   = (float)($row['gst_rate']       ?: 18);
        $gst_a   = (float)($row['gst_amount']     ?: round($base_a*$gst_r/100,2));
        $total_a = (float)($row['total_amount']   ?: round($base_a+$gst_a,2));
        $paid_a  = (float)($row['paid_amount']    ?: $row['amount']);
        $pend_a  = (float)($row['pending_amount'] ?? 0);
        $ptype   = $row['payment_type']   ?? 'full';
        $pstatus = $row['payment_status'] ?? $row['status'];
        $status  = $row['status'];
        $badge = [
            'pending'  => 'background:#FEF3C7;color:#92400E;border:1px solid rgba(245,158,11,.3)',
            'approved' => 'background:#D1FAE5;color:#065F46;border:1px solid rgba(16,185,129,.3)',
            'rejected' => 'background:#FEE2E2;color:#7F1D1D;border:1px solid rgba(239,68,68,.3)',
        ][$status] ?? '';
        $bicon = ['pending'=>'fa-clock','approved'=>'fa-check-circle','rejected'=>'fa-times-circle'][$status] ?? 'fa-question-circle';
    ?>
    <tr style="border-bottom:1px solid var(--border-color);transition:background .15s" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background=''">
        <td style="padding:13px 14px;font-size:0.83rem;color:var(--text-muted)"><?php echo $i++; ?></td>
        <td style="padding:13px 14px">
            <div style="font-weight:700;font-size:0.88rem"><?php echo htmlspecialchars($row['title']); ?></div>
            <div style="font-size:0.74rem;color:var(--text-muted)">Course price: &#8377;<?php echo number_format((float)$row['price'],2); ?></div>
        </td>
        <td style="padding:13px 14px" class="gst-cell">
            <div style="font-size:0.77rem;color:var(--text-muted)">Base: &#8377;<?php echo number_format($base_a,2); ?></div>
            <div style="font-size:0.77rem;color:#d97706">+GST: &#8377;<?php echo number_format($gst_a,2); ?></div>
            <div style="font-size:0.82rem;font-weight:700;color:#1e40af">&#8377;<?php echo number_format($total_a,2); ?></div>
            <div class="gst-tooltip">
                Base: &#8377;<?php echo number_format($base_a,2); ?><br>
                GST @ <?php echo $gst_r; ?>%: &#8377;<?php echo number_format($gst_a,2); ?><br>
                <strong>Total: &#8377;<?php echo number_format($total_a,2); ?></strong>
            </div>
        </td>
        <td style="padding:13px 14px;font-weight:700;color:var(--success)">&#8377;<?php echo number_format($paid_a,2); ?></td>
        <td style="padding:13px 14px">
            <?php if ($pend_a > 0): ?>
            <span style="font-size:0.8rem;font-weight:700;color:#dc2626"><i class="fas fa-clock"></i> &#8377;<?php echo number_format($pend_a,2); ?></span>
            <?php else: ?>
            <span style="font-size:0.78rem;color:var(--success)"><i class="fas fa-check-circle"></i> Nil</span>
            <?php endif; ?>
        </td>
        <td style="padding:13px 14px;font-family:monospace;font-size:0.83rem"><?php echo htmlspecialchars($row['utr_number']); ?></td>
        <td style="padding:13px 14px">
            <span class="pay-type-chip <?php echo $ptype==='full'?'chip-full':'chip-partial'; ?>">
                <i class="fas <?php echo $ptype==='full'?'fa-check-double':'fa-layer-group'; ?>"></i>
                <?php echo $ptype==='full'?'Full':'50% EMI'; ?>
            </span>
        </td>
        <td style="padding:13px 14px">
            <span style="<?php echo $badge; ?>;padding:3px 9px;border-radius:var(--radius-full);font-size:0.76rem;font-weight:700;display:inline-flex;align-items:center;gap:4px">
                <i class="fas <?php echo $bicon; ?>"></i>
                <?php echo ucfirst($status); ?>
            </span>
            <?php if ($status==='approved' && $pstatus==='partial'): ?>
            <div style="font-size:0.7rem;color:#d97706;margin-top:3px"><i class="fas fa-clock"></i> Balance pending</div>
            <?php elseif ($status==='approved' && $pstatus==='paid'): ?>
            <div style="font-size:0.7rem;color:#059669;margin-top:3px"><i class="fas fa-check-double"></i> Fully paid</div>
            <?php endif; ?>
        </td>
        <td style="padding:13px 14px">
            <?php if ($status === 'approved' && $pstatus === 'paid'): ?>
                <a href="course_lessons.php?course_id=<?php echo (int)$row['course_id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-play"></i> Start</a>
            <?php elseif ($status === 'approved' && $pstatus === 'partial' && $pend_a > 0): ?>
                <a href="payment.php?course_id=<?php echo (int)$row['course_id']; ?>" class="btn btn-sm" style="background:#dc2626;color:#fff;border-radius:var(--radius-md)"><i class="fas fa-credit-card"></i> Pay &#8377;<?php echo number_format($pend_a,2); ?></a>
            <?php elseif ($status === 'approved'): ?>
                <a href="course_lessons.php?course_id=<?php echo (int)$row['course_id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-play"></i> Start</a>
            <?php elseif ($status === 'rejected'): ?>
                <a href="payment.php?course_id=<?php echo (int)$row['course_id']; ?>" class="btn btn-sm" style="background:var(--danger);color:#fff;border-radius:var(--radius-md)"><i class="fas fa-redo"></i> Retry</a>
            <?php else: ?>
                <span style="font-size:0.78rem;color:var(--text-muted)"><i class="fas fa-hourglass-half"></i> Pending</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>

<?php else: ?>
<div class="empty-state" style="text-align:center;padding:48px 24px">
    <i class="fas fa-receipt" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px;display:block"></i>
    <h3 style="margin-bottom:8px">No Payment Records</h3>
    <p style="color:var(--text-muted);margin-bottom:20px">You have not made any payments yet.</p>
    <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-store"></i> Browse Courses</a>
</div>
<?php endif; ?>

</div>
</div>
</div>
</div>
<?php include("../includes/footer.php"); ?>
</body>
</html>
