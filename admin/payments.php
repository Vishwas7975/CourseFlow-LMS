<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

// Mark admin notifications as read on visit
@$conn->query("UPDATE notifications SET is_read = 1 WHERE for_role = 'admin' AND is_read = 0");

// Safe column migrations (idempotent)

// Filter: pending | approved | rejected
$filter = isset($_GET['filter']) && in_array($_GET['filter'], ['pending','approved','rejected']) ? $_GET['filter'] : 'pending';

$stmt = $conn->prepare("
    SELECT p.*, u.name AS student_name, u.email, u.mobile, c.title AS course_title, c.price AS course_price
    FROM payments p
    JOIN users   u ON p.user_id   = u.id
    JOIN courses c ON p.course_id = c.id
    WHERE p.status = ?
    ORDER BY p.id DESC
");
$stmt->bind_param("s", $filter);
$stmt->execute();
$result = $stmt->get_result();

// Counts for tab badges
$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status='$s'");
    $counts[$s] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

// Extra: count partial-approved (balance due) for dashboard insight
$partial_res = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status='approved' AND payment_status='partial' AND pending_amount > 0");
$partial_count = $partial_res ? (int)$partial_res->fetch_assoc()['c'] : 0;

$page_title = "Payment Approvals";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Approvals — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
    .pay-tag {
        display:inline-flex;align-items:center;gap:4px;
        font-size:0.72rem;font-weight:700;padding:3px 8px;border-radius:999px;
    }
    .pay-tag.full    { background:#dcfce7;color:#065f46; }
    .pay-tag.partial { background:#fef3c7;color:#92400e; }
    .gst-mini { font-size:0.72rem;color:#64748b;margin-top:2px; }
    .pending-bal { font-size:0.78rem;font-weight:700;color:#dc2626; }
    .details-modal {
        display:none;position:fixed;inset:0;z-index:9999;
        background:rgba(0,0,0,0.45);align-items:center;justify-content:center;
    }
    .details-modal.open { display:flex; }
    .modal-box {
        background:#fff;border-radius:20px;width:480px;max-width:95vw;
        box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;
        animation:modalIn .2s ease;
    }
    @keyframes modalIn { from{transform:scale(.95);opacity:0} to{transform:scale(1);opacity:1} }
    .modal-head { background:linear-gradient(135deg,#1e40af,#3b82f6);padding:18px 22px;color:#fff;display:flex;align-items:center;justify-content:space-between; }
    .modal-head h3 { margin:0;font-size:1rem;font-weight:700; }
    .modal-close { background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:0.9rem;display:flex;align-items:center;justify-content:center; }
    .modal-body { padding:20px 22px; }
    .mb-row { display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:0.85rem; }
    .mb-row:last-child{border-bottom:none;}
    .mb-lbl { color:#64748b;font-weight:500; }
    .mb-val { font-weight:700;color:#1e293b; }
    .mb-val.gst-v { color:#d97706; }
    .mb-val.total-v { color:#1e40af; }
    .mb-val.due-v { color:#dc2626; }
    .mb-val.paid-v { color:#059669; }
    </style>
</head>
<body>
<div class="dashboard">
<?php include("../includes/sidebar.php"); ?>
<div class="main">
<?php include("../includes/topbar.php"); ?>
<div class="content-wrapper">

<div class="page-header">
    <h1><i class="fas fa-money-check-alt"></i> Payment Approvals</h1>
    <p>Review and manage student payment submissions.</p>
</div>

<!-- Alert messages -->
<?php if (isset($_GET['msg'])): ?>
<?php
$msgs = [
    'approved'        => ['color'=>'#065F46','bg'=>'var(--success-light)','border'=>'rgba(16,185,129,.3)','icon'=>'fa-check-circle','text'=>'Payment approved and student enrolled successfully.'],
    'partial_approved'=> ['color'=>'#1e40af','bg'=>'#eff6ff','border'=>'rgba(59,130,246,.3)','icon'=>'fa-check-circle','text'=>'First installment approved. Student enrolled. Remaining balance pending.'],
    'remaining_approved'=>['color'=>'#065F46','bg'=>'var(--success-light)','border'=>'rgba(16,185,129,.3)','icon'=>'fa-check-double','text'=>'Remaining payment approved. Course fully paid.'],
    'rejected'        => ['color'=>'#7F1D1D','bg'=>'#FEE2E2','border'=>'rgba(239,68,68,.3)','icon'=>'fa-times-circle','text'=>'Payment has been rejected and student notified.'],
    'already_enrolled'=> ['color'=>'#92400E','bg'=>'var(--warning-light)','border'=>'rgba(245,158,11,.3)','icon'=>'fa-exclamation-triangle','text'=>'Student is already enrolled in this course.'],
    'error'           => ['color'=>'#7F1D1D','bg'=>'#FEE2E2','border'=>'rgba(239,68,68,.3)','icon'=>'fa-times-circle','text'=>'An error occurred. Please try again.'],
];
$m = $msgs[$_GET['msg']] ?? null;
if ($m): ?>
<div style="background:<?php echo $m['bg'];?>;border:1px solid <?php echo $m['border'];?>;border-radius:var(--radius-lg);padding:13px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;color:<?php echo $m['color'];?>">
    <i class="fas <?php echo $m['icon'];?>" style="font-size:1.1rem"></i>
    <span><?php echo htmlspecialchars($m['text']); ?></span>
</div>
<?php endif; endif; ?>

<!-- Partial balance alert -->
<?php if ($partial_count > 0): ?>
<div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:13px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
    <i class="fas fa-clock" style="color:#1e40af;font-size:1rem"></i>
    <span style="font-size:0.85rem;color:#1e40af;font-weight:600"><?php echo $partial_count; ?> student<?php echo $partial_count>1?'s have':' has'; ?> an approved partial payment with outstanding balance awaiting second installment.</span>
</div>
<?php endif; ?>

<!-- Filter Tabs -->
<div style="display:flex;gap:8px;margin-bottom:22px;flex-wrap:wrap">
<?php
$tab_defs = [
    'pending'  => ['label'=>'Pending',  'icon'=>'fa-clock',        'color'=>'var(--warning)'],
    'approved' => ['label'=>'Approved', 'icon'=>'fa-check-circle', 'color'=>'var(--success)'],
    'rejected' => ['label'=>'Rejected', 'icon'=>'fa-times-circle', 'color'=>'var(--danger)'],
];
foreach ($tab_defs as $tab => $def):
    $active = ($filter === $tab);
?>
<a href="payments.php?filter=<?php echo $tab; ?>"
   style="display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:var(--radius-full);border:2px solid <?php echo $active ? $def['color'] : 'var(--border-color)'; ?>;background:<?php echo $active ? $def['color'] : 'transparent'; ?>;color:<?php echo $active ? '#fff' : 'var(--text-secondary)'; ?>;font-size:0.875rem;font-weight:600;text-decoration:none;transition:all .2s">
    <i class="fas <?php echo $def['icon']; ?>"></i>
    <?php echo $def['label']; ?>
    <span style="background:<?php echo $active ? 'rgba(255,255,255,.25)' : 'var(--bg-secondary)'; ?>;padding:1px 7px;border-radius:var(--radius-full);font-size:0.75rem"><?php echo $counts[$tab]; ?></span>
</a>
<?php endforeach; ?>
</div>

<div class="section-wrapper">
<?php if ($result->num_rows > 0): ?>
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse">
    <thead>
        <tr>
            <?php foreach(['#','Student','Course','Fee Breakdown','Paid','Balance','UTR / Txn ID','Submitted At','Type','Status','Actions'] as $col): ?>
            <th style="text-align:left;padding:11px 14px;border-bottom:2px solid var(--border-color);font-size:0.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);white-space:nowrap"><?php echo $col; ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php $i=1; while ($row = $result->fetch_assoc()):
        $base_a   = (float)($row['base_amount']    ?: $row['course_price']);
        $gst_r    = (float)($row['gst_rate']       ?: 18);
        $gst_a    = (float)($row['gst_amount']     ?: round($base_a*$gst_r/100,2));
        $total_a  = (float)($row['total_amount']   ?: round($base_a+$gst_a,2));
        $paid_a   = (float)($row['paid_amount']    ?: $row['amount']);
        $pend_a   = (float)($row['pending_amount'] ?: 0);
        $ptype    = $row['payment_type'] ?? 'full';
        $pstatus  = $row['payment_status'] ?? 'pending';
        $status   = $row['status'];
        $modal_id = 'modal_'.$row['id'];
    ?>
    <tr style="border-bottom:1px solid var(--border-color);transition:background .15s" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background=''">
        <td style="padding:13px 14px;color:var(--text-muted);font-size:0.83rem"><?php echo $i++; ?></td>
        <td style="padding:13px 14px">
            <div style="font-weight:700;font-size:0.88rem"><?php echo htmlspecialchars($row['student_name']); ?></div>
            <div style="font-size:0.76rem;color:var(--text-muted)"><?php echo htmlspecialchars($row['email']); ?></div>
            <?php if (!empty($row['mobile'])): ?>
            <div style="font-size:0.74rem;color:var(--text-muted)"><i class="fas fa-phone" style="font-size:.65rem"></i> <?php echo htmlspecialchars($row['mobile']); ?></div>
            <?php endif; ?>
        </td>
        <td style="padding:13px 14px;font-weight:600;font-size:0.88rem"><?php echo htmlspecialchars($row['course_title']); ?></td>
        <!-- Fee Breakdown cell -->
        <td style="padding:13px 14px">
            <div style="font-size:0.78rem;color:var(--text-muted)">Base: &#8377;<?php echo number_format($base_a,2); ?></div>
            <div style="font-size:0.78rem;color:#d97706">GST(<?php echo $gst_r;?>%): +&#8377;<?php echo number_format($gst_a,2); ?></div>
            <div style="font-size:0.82rem;font-weight:700;color:#1e40af">Total: &#8377;<?php echo number_format($total_a,2); ?></div>
        </td>
        <td style="padding:13px 14px;font-weight:700;color:var(--success)">&#8377;<?php echo number_format($paid_a,2); ?></td>
        <td style="padding:13px 14px">
            <?php if ($pend_a > 0): ?>
            <span class="pending-bal"><i class="fas fa-clock"></i> &#8377;<?php echo number_format($pend_a,2); ?></span>
            <?php else: ?>
            <span style="font-size:0.78rem;color:var(--success)"><i class="fas fa-check-circle"></i> Nil</span>
            <?php endif; ?>
        </td>
        <td style="padding:13px 14px;font-family:monospace;font-size:0.83rem;color:var(--text-secondary)"><?php echo htmlspecialchars($row['utr_number']); ?></td>
        <td style="padding:13px 14px;font-size:0.82rem;color:var(--text-secondary);white-space:nowrap">
            <?php
            if (!empty($row['created_at'])) {
            $submitted_dt = new DateTime($row['created_at'], new DateTimeZone('Asia/Kolkata'));
            echo '<div style="font-weight:600;color:#1e293b">'.$submitted_dt->format('d M Y').'</div>';
            echo '<div style="font-size:0.76rem;color:#64748b">'.$submitted_dt->format('h:i A').'</div>';
            } else {
            echo '<span style="color:var(--text-muted)">—</span>';
            }
            ?>
        </td>
        <td style="padding:13px 14px">
            <span class="pay-tag <?php echo $ptype; ?>">
                <i class="fas <?php echo $ptype==='full' ? 'fa-check-double' : 'fa-layer-group'; ?>"></i>
                <?php echo $ptype==='full' ? 'Full' : '50% EMI'; ?>
            </span>
        </td>
        <td style="padding:13px 14px">
            <?php
            $badge_map = [
                'pending'  => 'background:#FEF3C7;color:#92400E;border:1px solid rgba(245,158,11,.3)',
                'approved' => 'background:#D1FAE5;color:#065F46;border:1px solid rgba(16,185,129,.3)',
                'rejected' => 'background:#FEE2E2;color:#7F1D1D;border:1px solid rgba(239,68,68,.3)',
            ];
            $icon_map = ['pending'=>'fa-clock','approved'=>'fa-check-circle','rejected'=>'fa-times-circle'];
            ?>
            <span style="<?php echo $badge_map[$status]??''; ?>;padding:3px 9px;border-radius:var(--radius-full);font-size:0.76rem;font-weight:700;display:inline-flex;align-items:center;gap:4px">
                <i class="fas <?php echo $icon_map[$status]??'fa-question'; ?>"></i>
                <?php echo ucfirst($status); ?>
            </span>
            <?php if ($status==='approved' && $pstatus==='partial'): ?>
            <div style="font-size:0.7rem;color:#d97706;margin-top:3px"><i class="fas fa-clock"></i> Balance due</div>
            <?php elseif ($status==='approved' && $pstatus==='paid'): ?>
            <div style="font-size:0.7rem;color:#059669;margin-top:3px"><i class="fas fa-check-double"></i> Fully paid</div>
            <?php endif; ?>
        </td>
        <td style="padding:13px 14px">
            <?php if ($status === 'pending'): ?>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
                <a href="approve_payment.php?id=<?php echo (int)$row['id']; ?>"
                   class="btn btn-sm" style="background:var(--success);color:#fff;border-radius:var(--radius-md)"
                   onclick="return confirm('Approve this payment?\n\n<?php echo addslashes($row['student_name']); ?> — <?php echo addslashes($row['course_title']); ?>\nAmount: ₹<?php echo number_format($paid_a,2); ?>')">
                    <i class="fas fa-check"></i> Approve
                </a>
                <a href="reject_payment.php?id=<?php echo (int)$row['id']; ?>"
                   class="btn btn-sm" style="background:var(--danger);color:#fff;border-radius:var(--radius-md)"
                   onclick="return confirm('Reject this payment?')">
                    <i class="fas fa-times"></i> Reject
                </a>
                <button onclick="openModal('<?php echo $modal_id; ?>')" class="btn btn-sm" style="background:var(--bg-secondary);border-radius:var(--radius-md)">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <?php elseif ($status === 'approved'): ?>
            <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center">
                <span style="font-size:0.78rem;color:var(--success)"><i class="fas fa-user-check"></i> Enrolled</span>
                <button onclick="openModal('<?php echo $modal_id; ?>')" class="btn btn-sm" style="background:var(--bg-secondary);border-radius:var(--radius-md)">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <?php else: ?>
            <span style="font-size:0.78rem;color:var(--danger)"><i class="fas fa-times-circle"></i> Rejected</span>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Detail Modal for this row -->
    <tr><td colspan="11" style="padding:0;border:none">
    <div class="details-modal" id="<?php echo $modal_id; ?>">
        <div class="modal-box">
            <div class="modal-head">
                <h3><i class="fas fa-file-invoice-dollar"></i> Payment Details</h3>
                <button class="modal-close" onclick="closeModal('<?php echo $modal_id; ?>')"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="mb-row"><span class="mb-lbl">Student</span><span class="mb-val"><?php echo htmlspecialchars($row['student_name']); ?></span></div>
                <div class="mb-row"><span class="mb-lbl">Email</span><span class="mb-val"><?php echo htmlspecialchars($row['email']); ?></span></div>
                <?php if (!empty($row['mobile'])): ?><div class="mb-row"><span class="mb-lbl">Mobile</span><span class="mb-val"><?php echo htmlspecialchars($row['mobile']); ?></span></div><?php endif; ?>
                <div class="mb-row"><span class="mb-lbl">Course</span><span class="mb-val"><?php echo htmlspecialchars($row['course_title']); ?></span></div>
                <div class="mb-row"><span class="mb-lbl">UTR / Txn ID</span><span class="mb-val" style="font-family:monospace"><?php echo htmlspecialchars($row['utr_number']); ?></span></div>
                <div class="mb-row"><span class="mb-lbl">Submitted At</span><span class="mb-val"><?php if (!empty($row['created_at'])) { $dt = new DateTime($row['created_at'], new DateTimeZone('Asia/Kolkata')); echo $dt->format('d M Y, h:i A'); } else { echo '—'; } ?></span></div>
                <div class="mb-row"><span class="mb-lbl">Payment Type</span><span class="mb-val"><?php echo $ptype==='full'?'Full Payment':'50% Installment (EMI)'; ?></span></div>
                <div style="height:1px;background:#f1f5f9;margin:8px 0"></div>
                <div class="mb-row"><span class="mb-lbl">Base Course Price</span><span class="mb-val">&#8377;<?php echo number_format($base_a,2); ?></span></div>
                <div class="mb-row"><span class="mb-lbl">GST (<?php echo $gst_r; ?>%)</span><span class="mb-val gst-v">+&#8377;<?php echo number_format($gst_a,2); ?></span></div>
                <div class="mb-row"><span class="mb-lbl">Total Fee</span><span class="mb-val total-v">&#8377;<?php echo number_format($total_a,2); ?></span></div>
                <div style="height:1px;background:#f1f5f9;margin:8px 0"></div>
                <div class="mb-row"><span class="mb-lbl">Amount Paid</span><span class="mb-val paid-v">&#8377;<?php echo number_format($paid_a,2); ?></span></div>
                <div class="mb-row"><span class="mb-lbl">Pending Balance</span><span class="mb-val <?php echo $pend_a>0?'due-v':'paid-v'; ?>">&#8377;<?php echo number_format($pend_a,2); ?> <?php echo $pend_a<=0?'✓ Cleared':''; ?></span></div>
                <div class="mb-row"><span class="mb-lbl">Payment Status</span><span class="mb-val"><?php echo ucfirst($pstatus); ?></span></div>
            </div>
        </div>
    </div>
    </td></tr>

    <?php endwhile; ?>
    </tbody>
</table>
</div>

<?php else: ?>
<div style="text-align:center;padding:48px 24px">
    <i class="fas fa-money-check-alt" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px;display:block"></i>
    <h3 style="margin-bottom:8px">No <?php echo ucfirst($filter); ?> Payments</h3>
    <p style="color:var(--text-muted)">There are no <?php echo $filter; ?> payments at this time.</p>
</div>
<?php endif; ?>

</div><!-- section-wrapper -->
</div><!-- content-wrapper -->
</div><!-- main -->
</div><!-- dashboard -->

<script>
function openModal(id)  { document.getElementById(id).classList.add('open');    document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.querySelectorAll('.details-modal').forEach(m => {
    m.addEventListener('click', function(e){ if(e.target===this) closeModal(this.id); });
});
document.addEventListener('keydown', e => {
    if (e.key==='Escape') document.querySelectorAll('.details-modal.open').forEach(m => closeModal(m.id));
});
</script>

<?php include("../includes/footer.php"); ?>
</body>
</html>