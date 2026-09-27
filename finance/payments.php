<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("finance");


// Filter
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

// Tab counts
$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status='$s'");
    $counts[$s] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

$partial_res   = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status='approved' AND payment_status='partial' AND pending_amount > 0");
$partial_count = $partial_res ? (int)$partial_res->fetch_assoc()['c'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments — CourseFlow Finance</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png?v=2">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    .pay-tag { display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:999px; }
    .pay-tag.full    { background:#dcfce7;color:#065f46; }
    .pay-tag.partial { background:#fef3c7;color:#92400e; }
    .pending-bal { font-size:.78rem;font-weight:700;color:#dc2626; }

    /* Tabs */
    .fin-tabs { display:flex;gap:8px;margin-bottom:22px;flex-wrap:wrap; }
    .fin-tab {
        display:inline-flex;align-items:center;gap:7px;
        padding:9px 18px;border-radius:999px;
        font-size:.83rem;font-weight:700;
        border:2px solid #e2e8f0;background:#fff;
        color:#64748b;text-decoration:none;transition:all .2s;
    }
    .fin-tab:hover { border-color:#94a3b8;color:#334155; }
    .fin-tab.active-pending  { border-color:#f59e0b;background:#f59e0b;color:#fff; }
    .fin-tab.active-approved { border-color:#10b981;background:#10b981;color:#fff; }
    .fin-tab.active-rejected { border-color:#ef4444;background:#ef4444;color:#fff; }
    .fin-tab .tab-count {
        background:rgba(255,255,255,.25);padding:1px 7px;
        border-radius:999px;font-size:.72rem;
    }
    .fin-tab:not([class*="active"]) .tab-count { background:#f1f5f9;color:#64748b; }

    /* Table */
    .fin-tbl-wrap { background:#fff;border-radius:16px;border:1px solid #eef2f7;box-shadow:0 2px 12px rgba(15,23,42,.05);overflow:hidden; }
    .fin-tbl { width:100%;border-collapse:collapse;min-width:960px; }
    .fin-tbl th {
        padding:11px 14px;text-align:left;
        font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;
        color:#94a3b8;background:#f8fafc;border-bottom:1px solid #f1f5f9;white-space:nowrap;
    }
    .fin-tbl td { padding:13px 14px;font-size:.82rem;color:#334155;border-bottom:1px solid #f8fafc;vertical-align:middle; }
    .fin-tbl tbody tr:last-child td { border-bottom:none; }
    .fin-tbl tbody tr:hover td { background:#f8fbff; }

    /* Modal */
    .details-modal { display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center; }
    .details-modal.open { display:flex; }
    .modal-box { background:#fff;border-radius:20px;width:480px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden;animation:modalIn .2s ease; }
    @keyframes modalIn { from{transform:scale(.95);opacity:0} to{transform:scale(1);opacity:1} }
    .modal-head { background:linear-gradient(135deg,#1e40af,#3b82f6);padding:18px 22px;color:#fff;display:flex;align-items:center;justify-content:space-between; }
    .modal-head h3 { margin:0;font-size:1rem;font-weight:700; }
    .modal-close { background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center; }
    .modal-body { padding:20px 22px; }
    .mb-row { display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:.85rem; }
    .mb-row:last-child { border-bottom:none; }
    .mb-lbl { color:#64748b;font-weight:500; }
    .mb-val { font-weight:700;color:#1e293b; }
    .mb-val.gst-v   { color:#d97706; }
    .mb-val.total-v { color:#1e40af; }
    .mb-val.due-v   { color:#dc2626; }
    .mb-val.paid-v  { color:#059669; }

    /* Page header */

    /* ── Mobile responsive fixes ── */
    @media(max-width:768px){
        /* Tabs: smaller padding so they fit on one line */
        .fin-tab { padding:7px 12px; font-size:.76rem; }

        /* Table wrapper: allow horizontal scroll (min-width:960px forces it) */
        .fin-tbl-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        /* Modal: full width on small screens */
        .modal-box { width: 95vw; border-radius: 14px; }
        .modal-body { padding: 16px; }
    }
    @media(max-width:480px){
        /* Tabs wrap to 2 per row on phones */
        .fin-tabs { gap: 6px; }
        .fin-tab { padding: 6px 10px; font-size: .72rem; }
    }
    .fin-page-hdr { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:24px; }
    .fin-page-hdr h1 { font-family:'Plus Jakarta Sans',sans-serif;font-size:1.4rem;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:10px; }
    .fin-hdr-ico { width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;box-shadow:0 4px 12px rgba(16,185,129,.35); }
    .fin-page-hdr p { color:#64748b;font-size:.83rem;margin-top:4px;margin-left:48px; }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- Page Header -->
            <div class="fin-page-hdr">
                <div>
                    <h1><span class="fin-hdr-ico"><i class="fas fa-credit-card"></i></span> Payments</h1>
                    <p>View and monitor all student payment records</p>
                </div>
                <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;background:#f1f5f9;color:#64748b;font-size:.82rem;font-weight:700;">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>

            <!-- Partial balance alert -->
            <?php if ($partial_count > 0): ?>
            <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:13px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-clock" style="color:#1e40af;font-size:1rem;"></i>
                <span style="font-size:.85rem;color:#1e40af;font-weight:600;">
                    <?= $partial_count ?> student<?= $partial_count>1?'s have':' has' ?> an approved partial payment with outstanding balance.
                </span>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="fin-tabs">
                <?php
                $tab_defs = [
                    'pending'  => ['label'=>'Pending',  'icon'=>'fa-clock'],
                    'approved' => ['label'=>'Approved', 'icon'=>'fa-check-circle'],
                    'rejected' => ['label'=>'Rejected', 'icon'=>'fa-times-circle'],
                ];
                foreach ($tab_defs as $tab => $def):
                    $isActive = ($filter === $tab);
                ?>
                <a href="payments.php?filter=<?= $tab ?>"
                   class="fin-tab <?= $isActive ? 'active-'.$tab : '' ?>">
                    <i class="fas <?= $def['icon'] ?>"></i>
                    <?= $def['label'] ?>
                    <span class="tab-count"><?= $counts[$tab] ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Table -->
            <div class="fin-tbl-wrap">
                <div style="overflow-x:auto;">
                <?php if ($result->num_rows > 0): ?>
                <table class="fin-tbl">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Fee Breakdown</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>UTR / Txn ID</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i=1; while ($row = $result->fetch_assoc()):
                        $base_a  = (float)($row['base_amount']    ?: $row['course_price']);
                        $gst_r   = (float)($row['gst_rate']       ?: 18);
                        $gst_a   = (float)($row['gst_amount']     ?: round($base_a*$gst_r/100,2));
                        $total_a = (float)($row['total_amount']   ?: round($base_a+$gst_a,2));
                        $paid_a  = (float)($row['paid_amount']    ?: $row['amount']);
                        $pend_a  = (float)($row['pending_amount'] ?: 0);
                        $ptype   = $row['payment_type']   ?? 'full';
                        $pstatus = $row['payment_status'] ?? 'pending';
                        $status  = $row['status'];
                        $modal_id = 'modal_'.$row['id'];
                    ?>
                    <tr>
                        <td style="color:#cbd5e1;font-size:.72rem;font-weight:600;"><?= $i++ ?></td>
                        <td>
                            <div style="font-weight:700;color:#0f172a;font-size:.84rem;"><?= htmlspecialchars($row['student_name']) ?></div>
                            <div style="font-size:.73rem;color:#94a3b8;"><?= htmlspecialchars($row['email']) ?></div>
                            <?php if(!empty($row['mobile'])): ?>
                            <div style="font-size:.72rem;color:#94a3b8;"><i class="fas fa-phone" style="font-size:.6rem;"></i> <?= htmlspecialchars($row['mobile']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:600;font-size:.83rem;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($row['course_title']) ?>">
                            <?= htmlspecialchars($row['course_title']) ?>
                        </td>
                        <td>
                            <div style="font-size:.76rem;color:#64748b;">Base: ₹<?= number_format($base_a,2) ?></div>
                            <div style="font-size:.76rem;color:#d97706;">GST(<?= $gst_r ?>%): +₹<?= number_format($gst_a,2) ?></div>
                            <div style="font-size:.8rem;font-weight:700;color:#1e40af;">Total: ₹<?= number_format($total_a,2) ?></div>
                        </td>
                        <td style="font-weight:700;color:#059669;font-family:'Plus Jakarta Sans',sans-serif;">₹<?= number_format($paid_a,2) ?></td>
                        <td>
                            <?php if($pend_a > 0): ?>
                            <span class="pending-bal"><i class="fas fa-clock"></i> ₹<?= number_format($pend_a,2) ?></span>
                            <?php else: ?>
                            <span style="font-size:.76rem;color:#059669;"><i class="fas fa-check-circle"></i> Nil</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-family:monospace;font-size:.8rem;color:#64748b;"><?= htmlspecialchars($row['utr_number']) ?></td>
                        <td>
                            <span class="pay-tag <?= $ptype ?>">
                                <i class="fas <?= $ptype==='full' ? 'fa-check-double' : 'fa-layer-group' ?>"></i>
                                <?= $ptype==='full' ? 'Full' : '50% EMI' ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $badge = [
                                'pending'  => 'background:#fef3c7;color:#92400e;',
                                'approved' => 'background:#dcfce7;color:#065f46;',
                                'rejected' => 'background:#fee2e2;color:#7f1d1d;',
                            ];
                            $icon = ['pending'=>'fa-clock','approved'=>'fa-check-circle','rejected'=>'fa-times-circle'];
                            ?>
                            <span style="<?= $badge[$status]??'' ?>;padding:3px 9px;border-radius:999px;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                                <i class="fas <?= $icon[$status]??'fa-question' ?>"></i>
                                <?= ucfirst($status) ?>
                            </span>
                            <?php if($status==='approved' && $pstatus==='partial'): ?>
                            <div style="font-size:.68rem;color:#d97706;margin-top:3px;"><i class="fas fa-clock"></i> Balance due</div>
                            <?php elseif($status==='approved' && $pstatus==='paid'): ?>
                            <div style="font-size:.68rem;color:#059669;margin-top:3px;"><i class="fas fa-check-double"></i> Fully paid</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="openModal('<?= $modal_id ?>')"
                                style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;background:#eff6ff;color:#2563eb;border:none;cursor:pointer;font-size:.76rem;font-weight:700;">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>

                    <!-- Detail Modal -->
                    <tr><td colspan="10" style="padding:0;border:none;">
                    <div class="details-modal" id="<?= $modal_id ?>">
                        <div class="modal-box">
                            <div class="modal-head">
                                <h3><i class="fas fa-file-invoice-dollar"></i> Payment Details</h3>
                                <button class="modal-close" onclick="closeModal('<?= $modal_id ?>')"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-row"><span class="mb-lbl">Student</span><span class="mb-val"><?= htmlspecialchars($row['student_name']) ?></span></div>
                                <div class="mb-row"><span class="mb-lbl">Email</span><span class="mb-val"><?= htmlspecialchars($row['email']) ?></span></div>
                                <?php if(!empty($row['mobile'])): ?><div class="mb-row"><span class="mb-lbl">Mobile</span><span class="mb-val"><?= htmlspecialchars($row['mobile']) ?></span></div><?php endif; ?>
                                <div class="mb-row"><span class="mb-lbl">Course</span><span class="mb-val"><?= htmlspecialchars($row['course_title']) ?></span></div>
                                <div class="mb-row"><span class="mb-lbl">UTR / Txn ID</span><span class="mb-val" style="font-family:monospace;"><?= htmlspecialchars($row['utr_number']) ?></span></div>
                                <div class="mb-row"><span class="mb-lbl">Payment Type</span><span class="mb-val"><?= $ptype==='full'?'Full Payment':'50% Installment (EMI)' ?></span></div>
                                <div style="height:1px;background:#f1f5f9;margin:8px 0;"></div>
                                <div class="mb-row"><span class="mb-lbl">Base Course Price</span><span class="mb-val">₹<?= number_format($base_a,2) ?></span></div>
                                <div class="mb-row"><span class="mb-lbl">GST (<?= $gst_r ?>%)</span><span class="mb-val gst-v">+₹<?= number_format($gst_a,2) ?></span></div>
                                <div class="mb-row"><span class="mb-lbl">Total Fee</span><span class="mb-val total-v">₹<?= number_format($total_a,2) ?></span></div>
                                <div style="height:1px;background:#f1f5f9;margin:8px 0;"></div>
                                <div class="mb-row"><span class="mb-lbl">Amount Paid</span><span class="mb-val paid-v">₹<?= number_format($paid_a,2) ?></span></div>
                                <div class="mb-row"><span class="mb-lbl">Pending Balance</span><span class="mb-val <?= $pend_a>0?'due-v':'paid-v' ?>">₹<?= number_format($pend_a,2) ?> <?= $pend_a<=0?'✓ Cleared':'' ?></span></div>
                                <div class="mb-row"><span class="mb-lbl">Payment Status</span><span class="mb-val"><?= ucfirst($pstatus) ?></span></div>
                                <div class="mb-row"><span class="mb-lbl">Date</span><span class="mb-val"><?php $dt = new DateTime($row['created_at'], new DateTimeZone('Asia/Kolkata')); echo $dt->format('d M Y, h:i A'); ?></span></div>
                            </div>
                        </div>
                    </div>
                    </td></tr>

                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align:center;padding:56px 24px;">
                    <div style="width:60px;height:60px;border-radius:16px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#cbd5e1;margin:0 auto 16px;">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:700;color:#334155;margin-bottom:6px;">No <?= ucfirst($filter) ?> Payments</h3>
                    <p style="font-size:.84rem;color:#94a3b8;">There are no <?= $filter ?> payments at this time.</p>
                </div>
                <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open');    document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.querySelectorAll('.details-modal').forEach(m => {
    m.addEventListener('click', function(e){ if(e.target===this) closeModal(this.id); });
});
document.addEventListener('keydown', e => {
    if(e.key==='Escape') document.querySelectorAll('.details-modal.open').forEach(m => closeModal(m.id));
});
</script>
</body>
</html>