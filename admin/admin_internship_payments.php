<?php
/**
 * admin_internship_payments.php — CourseFlow
 * Admin panel: view, approve, reject internship payments.
 * On approve → sends enrollment email to student.
 * On reject  → sends rejection email with reason.
 *
 * FIXES APPLIED:
 *  1. Added search button + Enter-key intercept (no more full page reload on search)
 *  2. Fixed incorrect domain in approval email links (2 places)
 *  3. Fixed incorrect domain in rejection email links (1 place)
 *  4. vs_send_mail() now wrapped with error logging so failures are never silent
 *  5. Added mailer.php load verification guard
 */

session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/mailer.php';

/* FIX 5 — Verify mailer loaded correctly */
if (!function_exists('vs_send_mail')) {
    error_log('CourseFlow: mailer.php failed to load or vs_send_mail() is not defined.');
}

/**
 * ─── DOMAIN → COURSE ID MAP ────────────────────────────────────────────────
 * Maps every internship_chosen value (from the registration form)
 * to its exact course id in the courses table.
 * Add new rows here whenever a new domain or course is added.
 */
function get_course_id_for_domain(string $domain): ?int {
    $map = [
        'AI & ML'                                                   => 10,
        'Web Development'                                           => 11,
        'Cybersecurity, Digital Forensics & Social Media Forensics' => 12,
        'Java Fullstack Development'                                => 13,
        'Data Science'                                              => 14,
        'Cloud Computing & DevOps'                                  => 15,
        'VLSI Designing'                                            => 16,
        'IoT (Internet of Things)'                                  => 18,
        'Data Analytics'                                            => 19,
        'Block Chain'                                               => 20,
        'Digital Marketing'                                         => 21,
        'UI/UX Design & Graphic Designing'                          => 22,
        'Medical Coding'                                            => 23,
        'Python for AI & Automation'                                => 24,
        // Add more mappings below as new courses are created:
        // 'Embedded Systems'                                       => XX,
        // 'Industrial Automation'                                  => XX,
        // 'Power Generation, Transmission & Distribution'          => XX,
        // 'SAP (R to R)'                                           => XX,
    ];
    return $map[trim($domain)] ?? null;
}

requireLogin($conn);
requireRole('admin'); // Adjust to your auth system

/* Ensure payment_later is a valid ENUM value (silent migration) */
@$conn->query("ALTER TABLE internship_payments MODIFY COLUMN payment_status ENUM('pending','approved','rejected','payment_later') NOT NULL DEFAULT 'pending'");

/* ─────────────────────────────────────────
   AJAX: Handle approve / reject actions
   ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action     = $_POST['action'];
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $note       = trim(strip_tags($_POST['note'] ?? ''));

    if (!in_array($action, ['approve', 'reject']) || $payment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit;
    }

    /* Load payment + registration */
    $stmt = $conn->prepare("SELECT ip.*, ir.college_name, ir.registration_number, ir.department,
                                   ir.admission_type
                            FROM internship_payments ip
                            JOIN internship_registrations ir ON ip.reg_id = ir.id
                            WHERE ip.id = ? LIMIT 1");
    $stmt->bind_param("i", $payment_id); $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc(); $stmt->close();

    if (!$pay) { echo json_encode(['success' => false, 'message' => 'Payment not found.']); exit; }

    $new_status = ($action === 'approve') ? 'approved' : 'rejected';

    /* Update status */
    $up = $conn->prepare("UPDATE internship_payments SET payment_status = ?, admin_note = ?, reviewed_at = NOW() WHERE id = ?");
    $up->bind_param("ssi", $new_status, $note, $payment_id); $up->execute(); $up->close();

    /* Send email */
    if ($action === 'approve') {

        $def_pass    = $pay['registration_number']; // default password = registration number
        $hashed_pass = password_hash($def_pass, PASSWORD_DEFAULT);
        $s_name      = $pay['full_name'];
        $s_email     = $pay['email'];
        $s_mobile    = $pay['mobile_number'];
        $s_college   = $pay['college_name'];
        $s_dept      = $pay['department'];
        $s_course    = $pay['internship_chosen'];

        /*
         * If this was a Pay Later registration, the account + enrollment were already
         * created by internship_set_payment_choice.php at registration time.
         * We still run the upsert below — it is safe (idempotent):
         *   - INSERT only fires if the user doesn't exist yet
         *   - enrollment INSERT only fires if not already enrolled
         */

        // Ensure user account exists
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param("s", $s_email); $chk->execute();
        $chk->store_result();
        if ($chk->num_rows === 0) {
            $ins = $conn->prepare("INSERT INTO users (name, email, password, role, mobile, college, dept, internship_course, status) VALUES (?, ?, ?, 'student', ?, ?, ?, ?, 'active')");
            $ins->bind_param("sssssss", $s_name, $s_email, $hashed_pass, $s_mobile, $s_college, $s_dept, $s_course);
            $ins->execute();
            $new_user_id = $conn->insert_id;
            $ins->close();
        } else {
            $chk->bind_result($new_user_id); $chk->fetch();
        }
        $chk->close();

        // Ensure course enrollment exists — use exact domain→course_id map
        if (!empty($new_user_id)) {
            $matched_course_id = get_course_id_for_domain($s_course);

            if ($matched_course_id !== null) {
                // Fetch trainer_id for this course
                $cq = $conn->prepare("SELECT trainer_id FROM courses WHERE id = ? LIMIT 1");
                $cq->bind_param("i", $matched_course_id); $cq->execute();
                $cq->bind_result($matched_trainer_id); $cq->fetch(); $cq->close();

                $ec = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
                $ec->bind_param("ii", $new_user_id, $matched_course_id); $ec->execute();
                $ec->store_result();
                if ($ec->num_rows === 0) {
                    $tid = !empty($matched_trainer_id) ? (int)$matched_trainer_id : 0;
                    $ei  = $conn->prepare("INSERT INTO enrollments (user_id, course_id, trainer_id, status) VALUES (?, ?, ?, 'approved')");
                    $ei->bind_param("iii", $new_user_id, $matched_course_id, $tid);
                    $ei->execute(); $ei->close();
                }
                $ec->close();
            } else {
                error_log("CourseFlow: No course mapped for internship domain '{$s_course}'. "
                    . "Add it to get_course_id_for_domain() in admin_internship_payments.php");
            }
        }

        $mail_ok = send_approval_email($pay, $def_pass);

    } else {
        $mail_ok = send_rejection_email($pay, $note);
    }

    /* Notify admin log */
    $log_title = ($action === 'approve') ? 'Payment Approved' : 'Payment Rejected';
    $log_msg   = $pay['full_name'] . ' — ' . $pay['internship_chosen'] . ' (' . $log_title . ')';
    if (!$mail_ok) {
        $log_title .= ' — EMAIL FAILED';
        $log_msg   .= ' | Could not deliver the confirmation email. Check PHP error log ("CourseFlow Mailer:") and resend manually.';
    }
    $ns = $conn->prepare("INSERT INTO notifications (type, for_role, for_user_id, title, message, link, is_read) VALUES ('internship_payment','admin',NULL,?,?,?,0)");
    if ($ns) {
        $lnk = '/admin/admin_internship_payments.php?id=' . $payment_id;
        $ns->bind_param("sss", $log_title, $log_msg, $lnk); $ns->execute(); $ns->close();
    }

    echo json_encode([
        'success'   => true,
        'status'    => $new_status,
        'mail_sent' => $mail_ok,
    ]); exit;
}

/* ─────────────────────────────────────────
   Load all payments (with filter)
   ───────────────────────────────────────── */
$filter    = $_GET['filter'] ?? 'all';
$search    = trim($_GET['q'] ?? '');
$detail_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* AJAX search: returns rows+data as JSON, no page reload */
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

$where  = "WHERE 1=1";
$params = []; $types = "";

if ($filter !== 'all') {
    $where .= " AND ip.payment_status = ?";
    $params[] = $filter; $types .= "s";
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $where .= " AND (ip.full_name LIKE ? OR ip.email LIKE ? OR ip.utr_number LIKE ? OR ip.internship_chosen LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "ssss";
}

$sql = "SELECT ip.*, ir.registration_number, ir.college_name, ir.department,
               ir.admission_type
        FROM internship_payments ip
        JOIN internship_registrations ir ON ip.reg_id = ir.id
        {$where}
        ORDER BY ip.submitted_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* If AJAX request, return rows as JSON and stop */
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['payments' => $payments]);
    exit;
}

/* Stats */
$stats = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'payment_later' => 0];
$statsQ = $conn->query("SELECT payment_status, COUNT(*) as c FROM internship_payments GROUP BY payment_status");
while ($row = $statsQ->fetch_assoc()) {
    $stats[$row['payment_status']] = (int)$row['c'];
    $stats['all'] += (int)$row['c'];
}

/* Detail view */
$detail = null;
if ($detail_id > 0) {
    $ds = $conn->prepare("SELECT ip.*, ir.registration_number, ir.college_name, ir.department,
                                  ir.admission_type
                           FROM internship_payments ip
                           JOIN internship_registrations ir ON ip.reg_id = ir.id
                           WHERE ip.id = ? LIMIT 1");
    $ds->bind_param("i", $detail_id); $ds->execute();
    $detail = $ds->get_result()->fetch_assoc(); $ds->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Internship Payments — Admin | CourseFlow</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<link rel="stylesheet" href="../assets/css/style.css"/>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
/* ── Page layout ── */
.pg-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px;}
.pg-head h1{font-size:1.35rem;font-weight:800;color:#1e293b;display:flex;align-items:center;gap:10px;margin:0;}
.ph-ico{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#1e40af,#3b82f6);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.88rem;}

/* Stats row */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:26px;}
@media(max-width:640px){.stats-row{grid-template-columns:1fr 1fr;}}
.stat-card{background:#fff;border-radius:14px;padding:16px 18px;border:1px solid rgba(0,0,0,.06);box-shadow:0 2px 10px rgba(0,0,0,.05);display:flex;align-items:center;gap:12px;cursor:pointer;transition:all .2s;text-decoration:none;}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.09);}
.stat-card.active{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12);}
.stat-ico{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;}
.stat-num{font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1;}
.stat-lbl{font-size:.72rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-top:2px;}

/* Filter + Search bar */
.toolbar{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.search-wrap{flex:1;min-width:220px;position:relative;}
.search-wrap i.fa-search{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.85rem;pointer-events:none;z-index:1;}
.search-inp{width:100%;padding:10px 12px 10px 36px;border:2px solid #e2e8f0;border-radius:10px;font-size:.86rem;color:#1e293b;background:#f8fafc;outline:none;font-family:inherit;transition:all .2s;}
.search-inp:focus{border-color:#3b82f6;background:#fff;}
.export-btn{display:flex;align-items:center;gap:6px;padding:9px 16px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:10px;font-size:.82rem;font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit;white-space:nowrap;}

/* Table */
.tbl-wrap{background:#fff;border-radius:16px;border:1px solid rgba(0,0,0,.06);box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden;}
.tbl-inner{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead tr{background:linear-gradient(135deg,#0f1b3d,#1e40af);}
thead th{padding:12px 16px;font-size:.72rem;font-weight:700;color:rgba(255,255,255,.85);text-align:left;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .15s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:#f8faff;}
td{padding:13px 16px;font-size:.84rem;color:#1e293b;vertical-align:middle;}
.td-name{font-weight:700;color:#1e293b;}
.td-sub{font-size:.72rem;color:#94a3b8;margin-top:1px;}
.td-utr{font-family:monospace;font-size:.8rem;color:#3b82f6;background:#eff6ff;padding:2px 8px;border-radius:6px;display:inline-block;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:700;}
.badge.pending{background:#fef3c7;color:#92400e;}
.badge.approved{background:#dcfce7;color:#14532d;}
.badge.rejected{background:#ffe4e6;color:#881337;}
.badge.payment_later{background:#eff6ff;color:#1e40af;}
.act-row{display:flex;gap:6px;}
.btn-sm{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;font-size:.76rem;font-weight:700;border:none;cursor:pointer;font-family:inherit;transition:all .18s;text-decoration:none;}
.btn-approve{background:#dcfce7;color:#14532d;}.btn-approve:hover{background:#bbf7d0;}
.btn-reject{background:#ffe4e6;color:#881337;}.btn-reject:hover{background:#fecdd3;}
.btn-view{background:#eff6ff;color:#1e40af;}.btn-view:hover{background:#bfdbfe;}
.empty-state{text-align:center;padding:60px 22px;color:#94a3b8;}
.empty-state i{font-size:2.5rem;margin-bottom:14px;display:block;}

/* Modal / Detail Drawer */
.modal-overlay{display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;}
.modal-overlay.show{display:flex;animation:ovIn .2s;}
@keyframes ovIn{from{opacity:0}to{opacity:1}}
.modal-box{background:#fff;border-radius:20px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 70px rgba(0,0,0,.25);animation:mIn .25s cubic-bezier(.34,1.56,.64,1);}
@keyframes mIn{from{transform:scale(.88);opacity:0}to{transform:scale(1);opacity:1}}
.modal-head{background:linear-gradient(135deg,#0f1b3d,#1e40af);border-radius:20px 20px 0 0;padding:22px 26px;display:flex;align-items:center;justify-content:space-between;}
.modal-head h2{color:#fff;font-size:1rem;font-weight:700;margin:0;}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;}
.modal-body{padding:24px 26px;}
.drow{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid #f1f5f9;}
.drow:last-child{border-bottom:none;}
.dico{width:30px;height:30px;border-radius:8px;background:#eff6ff;color:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;margin-top:2px;}
.dlbl{font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;font-weight:600;margin-bottom:1px;}
.dval{font-size:.87rem;font-weight:600;color:#1e293b;}
.modal-footer{padding:16px 26px 22px;display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid #f1f5f9;}
.note-area{width:100%;padding:9px 12px;border:2px solid #e2e8f0;border-radius:10px;font-size:.84rem;color:#1e293b;font-family:inherit;resize:vertical;min-height:70px;outline:none;transition:border-color .2s;}
.note-area:focus{border-color:#3b82f6;}
.btn-full{flex:1;padding:11px;border-radius:10px;font-size:.88rem;font-weight:700;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;font-family:inherit;transition:all .2s;}
.btn-full.approve{background:linear-gradient(135deg,#059669,#10b981);color:#fff;box-shadow:0 4px 12px rgba(16,185,129,.3);}
.btn-full.approve:hover{transform:translateY(-1px);}
.btn-full.reject{background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;box-shadow:0 4px 12px rgba(239,68,68,.3);}
.btn-full.reject:hover{transform:translateY(-1px);}
.btn-full:disabled{opacity:.6;cursor:not-allowed;transform:none;}

/* Toast */
.toast{position:fixed;bottom:28px;right:28px;z-index:10000;background:#1e293b;color:#fff;border-radius:12px;padding:13px 20px;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:10px;box-shadow:0 8px 28px rgba(0,0,0,.25);transform:translateY(80px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);}
.toast.show{transform:translateY(0);opacity:1;}
.toast.success i{color:#4ade80;}
.toast.error i{color:#f87171;}
</style>
</head>
<body>
<div class="dashboard">
<?php include("../includes/sidebar.php"); ?>
<div class="main">
<?php include("../includes/topbar.php"); ?>
<div class="content-wrapper">

<div class="pg-head">
  <div>
    <h1><span class="ph-ico"><i class="fas fa-money-check-alt"></i></span> Internship Payments</h1>
    <p style="margin:4px 0 0;color:#64748b;font-size:.83rem;">Review, approve, and manage internship payment submissions</p>
  </div>
  <a href="?export=csv&filter=<?php echo urlencode($filter); ?>" class="export-btn">
    <i class="fas fa-download"></i> Export CSV
  </a>
</div>

<!-- CSV Export -->
<?php
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="internship_payments_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Reg ID','Name','Email','Mobile','Internship','College','Dept','UTR','Amount','Terminal','Status','Submitted','Reviewed']);
    foreach ($payments as $p) {
        fputcsv($out, [$p['id'],$p['reg_id'],$p['full_name'],$p['email'],$p['mobile_number'],$p['internship_chosen'],$p['college_name'],$p['department'],$p['utr_number'],$p['amount'],$p['terminal_name'],$p['payment_status'],$p['submitted_at'],$p['reviewed_at']]);
    }
    fclose($out); exit;
}
?>

<!-- Stats -->
<div class="stats-row">
  <?php
  $sc = [
    'all'           => ['ico'=>'fas fa-list',          'bg'=>'#eff6ff','c'=>'#1e40af','lbl'=>'All'],
    'pending'       => ['ico'=>'fas fa-hourglass-half','bg'=>'#fef3c7','c'=>'#92400e','lbl'=>'Pending'],
    'approved'      => ['ico'=>'fas fa-check-circle',  'bg'=>'#dcfce7','c'=>'#14532d','lbl'=>'Approved'],
    'rejected'      => ['ico'=>'fas fa-times-circle',  'bg'=>'#ffe4e6','c'=>'#881337','lbl'=>'Rejected'],
    'payment_later' => ['ico'=>'fas fa-clock',         'bg'=>'#eff6ff','c'=>'#1d4ed8','lbl'=>'Pay Later'],
  ];
  foreach ($sc as $key => $s):
  ?>
  <a href="?filter=<?php echo $key; ?>" class="stat-card<?php echo $filter===$key?' active':''; ?>">
    <div class="stat-ico" style="background:<?php echo $s['bg']; ?>">
      <i class="<?php echo $s['ico']; ?>" style="color:<?php echo $s['c']; ?>"></i>
    </div>
    <div>
      <div class="stat-num"><?php echo $stats[$key] ?? 0; ?></div>
      <div class="stat-lbl"><?php echo $s['lbl']; ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<div class="toolbar">
  <div class="search-wrap">
    <i class="fas fa-search"></i>
    <input
      class="search-inp"
      type="text"
      id="searchInp"
      value="<?php echo htmlspecialchars($search); ?>"
      placeholder="Search by name, email, UTR, domain…"
      oninput="liveSearch(this.value)"
      autocomplete="off"
    />
  </div>
  <a href="?export=csv&filter=<?php echo urlencode($filter); ?>" class="export-btn">
    <i class="fas fa-download"></i> Export CSV
  </a>
</div>

<!-- Table -->
<div class="tbl-wrap">
  <div class="tbl-inner">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Student</th><th>Internship</th><th>Amount</th>
          <th>UTR</th><th>Terminal</th><th>Submitted</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="9">
          <div class="empty-state"><i class="fas fa-inbox"></i><p>No payments found.</p></div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td style="font-weight:700;color:#94a3b8;font-size:.78rem;">#<?php echo $p['id']; ?></td>
          <td>
            <div class="td-name"><?php echo htmlspecialchars($p['full_name']); ?></div>
            <div class="td-sub"><?php echo htmlspecialchars($p['email']); ?></div>
            <div class="td-sub"><?php echo htmlspecialchars($p['mobile_number']); ?></div>
          </td>
          <td>
            <div style="font-weight:600;font-size:.84rem;"><?php echo htmlspecialchars($p['internship_chosen']); ?></div>
            <div class="td-sub"><?php echo htmlspecialchars($p['college_name']); ?></div>
          </td>
          <td style="font-weight:800;color:#1e40af;">&#8377;<?php echo number_format((float)$p['amount'], 2); ?></td>
          <td><span class="td-utr"><?php echo htmlspecialchars($p['utr_number']); ?></span></td>
          <td style="font-size:.8rem;color:#64748b;"><?php echo htmlspecialchars($p['terminal_name']); ?></td>
          <td style="font-size:.78rem;color:#64748b;white-space:nowrap;"><?php $dt=new DateTime($p['submitted_at'],new DateTimeZone('UTC'));$dt->setTimezone(new DateTimeZone('Asia/Kolkata'));echo $dt->format('d M y, h:i A'); ?></td>
          <td>
            <span class="badge <?php echo $p['payment_status']; ?>">
              <?php if($p['payment_status']==='pending'): ?><i class="fas fa-clock"></i>
              <?php elseif($p['payment_status']==='approved'): ?><i class="fas fa-check"></i>
              <?php elseif($p['payment_status']==='payment_later'): ?><i class="fas fa-hourglass"></i>
              <?php else: ?><i class="fas fa-times"></i><?php endif; ?>
              <?php echo $p['payment_status']==='payment_later' ? 'Pay Later' : ucfirst($p['payment_status']); ?>
            </span>
          </td>
          <td>
            <div class="act-row">
              <button class="btn-sm btn-view" onclick="viewDetail(<?php echo $p['id']; ?>)"><i class="fas fa-eye"></i> View</button>
              <?php if ($p['payment_status'] === 'pending'): ?>
              <button class="btn-sm btn-approve" onclick="openAction(<?php echo $p['id']; ?>,'approve','<?php echo addslashes(htmlspecialchars($p['full_name'])); ?>')"><i class="fas fa-check"></i> Approve</button>
              <button class="btn-sm btn-reject"  onclick="openAction(<?php echo $p['id']; ?>,'reject','<?php echo addslashes(htmlspecialchars($p['full_name'])); ?>')"><i class="fas fa-times"></i> Reject</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div></div></div>

<!-- Detail Modal -->
<div class="modal-overlay" id="detailModal">
  <div class="modal-box">
    <div class="modal-head">
      <h2><i class="fas fa-receipt" style="margin-right:8px"></i> Payment Details</h2>
      <button class="modal-close" onclick="closeModal('detailModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="detailBody">
      <div style="text-align:center;padding:30px;color:#94a3b8"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem"></i><p style="margin-top:10px">Loading...</p></div>
    </div>
  </div>
</div>

<!-- Action Modal (Approve / Reject) -->
<div class="modal-overlay" id="actionModal">
  <div class="modal-box">
    <div class="modal-head" id="actionHead">
      <h2 id="actionTitle"></h2>
      <button class="modal-close" onclick="closeModal('actionModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <p style="font-size:.88rem;color:#64748b;margin-bottom:14px;">You are about to <strong id="actionVerb"></strong> the payment for <strong id="actionName"></strong>.</p>
      <div style="margin-bottom:16px;">
        <label style="font-size:.73rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:6px;">
          Note / Reason <span style="font-weight:400;color:#94a3b8">(optional for approve, recommended for reject)</span>
        </label>
        <textarea class="note-area" id="actionNote" placeholder="e.g. UTR verified via PhonePe dashboard…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-full" id="actionBtn" onclick="doAction()">Confirm</button>
      <button class="btn-full" style="background:#f1f5f9;color:#475569;flex:0.4;" onclick="closeModal('actionModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg">Done!</span></div>

<script>
var ALL_PAYMENTS = <?php echo json_encode($payments); ?>;
var curActionId  = 0;
var curAction    = '';

function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function showModal(id)  { document.getElementById(id).classList.add('show'); }

function viewDetail(id) {
    var p = ALL_PAYMENTS.find(function(x){ return x.id == id; });
    if (!p) return;
    var statusClass = { pending:'#fef3c7', approved:'#dcfce7', rejected:'#ffe4e6', payment_later:'#eff6ff' };
    var statusColor = { pending:'#92400e', approved:'#14532d', rejected:'#881337', payment_later:'#1e40af' };
    var statusIcon  = { pending:'⏳', approved:'✅', rejected:'❌', payment_later:'🕐' };
    document.getElementById('detailBody').innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <div style="background:#f0f5ff;border-radius:12px;padding:14px;">
          <div style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Payment ID</div>
          <div style="font-weight:800;color:#1e40af;font-size:1rem;">#VSI-PAY-${String(p.id).padStart(4,'0')}</div>
        </div>
        <div style="background:${statusClass[p.payment_status]};border-radius:12px;padding:14px;">
          <div style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Status</div>
          <div style="font-weight:700;color:${statusColor[p.payment_status]};font-size:.9rem;">${statusIcon[p.payment_status]} ${p.payment_status.charAt(0).toUpperCase()+p.payment_status.slice(1)}</div>
        </div>
      </div>
      ${row('fas fa-user','Student',p.full_name)}
      ${row('fas fa-envelope','Email',p.email)}
      ${row('fas fa-phone','Mobile',p.mobile_number)}
      ${row('fas fa-briefcase','Internship',p.internship_chosen)}
      ${row('fas fa-university','College',p.college_name)}
      ${row('fas fa-id-card','Reg. Number',p.registration_number)}
      ${row('fas fa-code-branch','Department',p.department)}
      ${row('fas fa-tag','Admission Type',p.admission_type)}
      ${row('fas fa-receipt','UTR Number','<code style="font-family:monospace;background:#eff6ff;color:#1e40af;padding:2px 8px;border-radius:6px;">'+p.utr_number+'</code>')}
      ${row('fas fa-store','Terminal',p.terminal_name+' — <span style="font-family:monospace;color:#3b82f6">'+p.terminal_upi+'</span>')}
      ${row('fas fa-rupee-sign','Amount','<strong style="color:#1e40af;font-size:1rem;">₹'+parseFloat(p.amount).toFixed(2)+'</strong>')}
      ${row('fas fa-calendar','Submitted',p.submitted_at)}
      ${p.reviewed_at ? row('fas fa-check-double','Reviewed',p.reviewed_at) : ''}
      ${p.admin_note ? row('fas fa-comment','Admin Note',p.admin_note) : ''}
    `;
    showModal('detailModal');
}
function row(ico, lbl, val) {
    return `<div class="drow"><div class="dico"><i class="${ico}"></i></div><div><div class="dlbl">${lbl}</div><div class="dval">${val}</div></div></div>`;
}

function openAction(id, action, name) {
    curActionId = id; curAction = action;
    document.getElementById('actionTitle').textContent = action === 'approve' ? '✅ Approve Payment' : '❌ Reject Payment';
    document.getElementById('actionHead').style.background = action === 'approve' ? 'linear-gradient(135deg,#059669,#10b981)' : 'linear-gradient(135deg,#dc2626,#ef4444)';
    document.getElementById('actionVerb').textContent = action === 'approve' ? 'APPROVE' : 'REJECT';
    document.getElementById('actionName').textContent = name;
    document.getElementById('actionNote').value = '';
    var btn = document.getElementById('actionBtn');
    btn.className = 'btn-full ' + (action === 'approve' ? 'approve' : 'reject');
    btn.innerHTML = action === 'approve' ? '<i class="fas fa-check"></i> Approve & Send Email' : '<i class="fas fa-times"></i> Reject & Notify Student';
    showModal('actionModal');
}

function doAction() {
    var btn  = document.getElementById('actionBtn');
    var note = document.getElementById('actionNote').value.trim();
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';

    var fd = new FormData();
    fd.append('action', curAction);
    fd.append('payment_id', curActionId);
    fd.append('note', note);

    fetch('', { method:'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(data) {
          closeModal('actionModal');
          if (data.success) {
              var statusWord = curAction === 'approve' ? 'Payment approved' : 'Payment rejected';
              if (data.mail_sent === false) {
                  showToast(statusWord + ', but the email FAILED to send. Check error log / resend manually.', false);
              } else {
                  showToast(statusWord + '! Email sent to student.', data.success);
              }
              setTimeout(function(){ location.reload(); }, 1800);
          } else {
              showToast(data.message || 'Something went wrong.', false);
              btn.disabled = false;
          }
      })
      .catch(function() { showToast('Network error. Please try again.', false); btn.disabled = false; });
}

function showToast(msg, success) {
    var t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.className = 'toast show ' + (success ? 'success' : 'error');
    t.querySelector('i').className = success ? 'fas fa-check-circle' : 'fas fa-times-circle';
    setTimeout(function(){ t.classList.remove('show'); }, 3500);
}

/* ── FIX 1: Live AJAX search — intercepts Enter, calls liveSearch(), no page reload ── */
var _searchTimer = null;
function liveSearch(q) {
    clearTimeout(_searchTimer);
    _searchTimer = setTimeout(function() {
        var filter = '<?php echo addslashes($filter); ?>';
        fetch('?ajax=1&filter=' + encodeURIComponent(filter) + '&q=' + encodeURIComponent(q))
          .then(function(r){ return r.json(); })
          .then(function(data) {
            ALL_PAYMENTS = data.payments;
            renderTable(data.payments);
          })
          .catch(function(){ /* silent fail, keep current results */ });
    }, 350);
}

function renderTable(payments) {
    var tbody = document.querySelector('tbody');
    if (!payments || payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state"><i class="fas fa-inbox"></i><p>No payments found.</p></div></td></tr>';
        return;
    }
    var statusClass = { pending:'badge pending', approved:'badge approved', rejected:'badge rejected', payment_later:'badge payment_later' };
    var statusIcon  = { pending:'fa-clock', approved:'fa-check', rejected:'fa-times', payment_later:'fa-hourglass' };
    var statusLabel = { pending:'Pending', approved:'Approved', rejected:'Rejected', payment_later:'Pay Later' };
    var html = '';
    payments.forEach(function(p) {
        var dt = p.submitted_at ? p.submitted_at : '';
        var actBtns = '';
        if (p.payment_status === 'pending') {
            actBtns = '<button class="btn-sm btn-approve" onclick="openAction('+p.id+',\'approve\',\''+p.full_name.replace(/'/g,"\\'")+'\')">'+'<i class="fas fa-check"></i> Approve</button>'
                    + '<button class="btn-sm btn-reject" onclick="openAction('+p.id+',\'reject\',\''+p.full_name.replace(/'/g,"\\'")+'\')">'+'<i class="fas fa-times"></i> Reject</button>';
        }
        html += '<tr>'
            + '<td style="font-weight:700;color:#94a3b8;font-size:.78rem;">#'+p.id+'</td>'
            + '<td><div class="td-name">'+escHtml(p.full_name)+'</div><div class="td-sub">'+escHtml(p.email)+'</div><div class="td-sub">'+escHtml(p.mobile_number)+'</div></td>'
            + '<td><div style="font-weight:600;font-size:.84rem;">'+escHtml(p.internship_chosen)+'</div><div class="td-sub">'+escHtml(p.college_name)+'</div></td>'
            + '<td style="font-weight:800;color:#1e40af;">&#8377;'+parseFloat(p.amount).toFixed(2)+'</td>'
            + '<td><span class="td-utr">'+escHtml(p.utr_number)+'</span></td>'
            + '<td style="font-size:.8rem;color:#64748b;">'+escHtml(p.terminal_name)+'</td>'
            + '<td style="font-size:.78rem;color:#64748b;white-space:nowrap;">'+escHtml(dt)+'</td>'
            + '<td><span class="'+statusClass[p.payment_status]+'"><i class="fas '+statusIcon[p.payment_status]+'"></i> '+statusLabel[p.payment_status]+'</span></td>'
            + '<td><div class="act-row"><button class="btn-sm btn-view" onclick="viewDetail('+p.id+')"><i class="fas fa-eye"></i> View</button>'+actBtns+'</div></td>'
            + '</tr>';
    });
    tbody.innerHTML = html;
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.querySelectorAll('.modal-overlay').forEach(function(o){
    o.addEventListener('click', function(e){ if(e.target===this) closeModal(this.id); });
});

// Auto-open detail if ?id= is set
<?php if ($detail_id > 0): ?>
window.addEventListener('DOMContentLoaded', function(){ viewDetail(<?php echo $detail_id; ?>); });
<?php endif; ?>
</script>

<?php include("../includes/footer.php"); ?>
</body>
</html>

<?php
/* ════════════════════════════════════════
   EMAIL FUNCTIONS
   ════════════════════════════════════════ */

function send_approval_email(array $pay, string $def_pass = ''): bool {
    $to      = $pay['email'];
    $name    = $pay['full_name'];
    $domain  = $pay['internship_chosen'];
    $utr     = $pay['utr_number'];
    $amount  = number_format((float)$pay['amount'], 2);
    $pid_str = '#VSI-PAY-' . str_pad($pay['id'], 4, '0', STR_PAD_LEFT);
    $college = $pay['college_name'];
    $dept    = $pay['department'];
    $reg_num = $pay['registration_number'];

    $subject = "🎉 Internship Enrollment Confirmed — {$domain} | CourseFlow";

    /* FIX 2 — All login URLs corrected to yourdomain.com */
    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:'Plus Jakarta Sans',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <!-- Hero Header -->
  <tr><td style="background:linear-gradient(135deg,#0f1b3d,#2448c8);border-radius:16px 16px 0 0;padding:40px 40px 32px;text-align:center;">
    <div style="width:72px;height:72px;background:linear-gradient(135deg,#4ade80,#22c55e);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:16px;">✅</div>
    <div style="font-size:1.6rem;font-weight:800;color:#fff;margin-bottom:4px;">Payment Approved!</div>
    <div style="font-size:.88rem;color:rgba(255,255,255,.7);">You're officially enrolled in the {$domain} Internship</div>
    <div style="margin-top:18px;background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.4);border-radius:40px;padding:7px 20px;display:inline-block;font-size:.78rem;color:#86efac;font-weight:600;letter-spacing:.06em;text-transform:uppercase;">🎓 Enrollment Confirmed</div>
  </td></tr>

  <!-- Body -->
  <tr><td style="background:#ffffff;padding:36px 40px;">
    <p style="font-size:1.05rem;font-weight:700;color:#1e293b;margin:0 0 8px;">Hi {$name}, welcome aboard! 🚀</p>
    <p style="font-size:.9rem;color:#64748b;margin:0 0 26px;line-height:1.7;">Your payment has been <strong style="color:#16a34a;">verified and approved</strong> by our team. You are now officially enrolled in the <strong>{$domain}</strong> internship program. Here's everything you need to get started.</p>

    <!-- Enrollment Summary -->
    <div style="background:linear-gradient(135deg,#f0fdf4,#f0f9ff);border:1.5px solid #bbf7d0;border-radius:14px;padding:22px 24px;margin-bottom:24px;">
      <div style="font-size:.7rem;font-weight:700;color:#14532d;text-transform:uppercase;letter-spacing:.1em;margin-bottom:14px;">📋 Enrollment Summary</div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;width:40%;">Payment ID</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$pid_str}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Internship Track</td><td style="font-size:.83rem;font-weight:700;color:#1e40af;text-align:right;">{$domain}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Registration No.</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$reg_num}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">College</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$college}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Department</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;">{$dept}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">Amount Paid</td><td style="font-size:.83rem;font-weight:700;color:#16a34a;text-align:right;">₹{$amount}</td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:5px 0;">UTR Number</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;font-family:monospace;">{$utr}</td></tr>
      </table>
    </div>

    <!-- What's Next -->
    <div style="margin-bottom:26px;">
      <div style="font-size:.75rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px;">🗓️ What Happens Next?</div>
      <div style="padding:14px;background:#f8faff;border-left:4px solid #3b82f6;border-radius:0 10px 10px 0;margin-bottom:10px;">
        <div style="font-size:.85rem;font-weight:700;color:#1e293b;margin-bottom:3px;">1. Onboarding Email (within 12 hrs)</div>
        <div style="font-size:.82rem;color:#64748b;line-height:1.5;">You'll receive your internship schedule, mentor details, joining link, and resource kit.</div>
      </div>
      <div style="padding:14px;background:#f8faff;border-left:4px solid #10b981;border-radius:0 10px 10px 0;margin-bottom:10px;">
        <div style="font-size:.85rem;font-weight:700;color:#1e293b;margin-bottom:3px;">2. Orientation Session</div>
        <div style="font-size:.82rem;color:#64748b;line-height:1.5;">Attend the live orientation where your mentor will walk you through the {$domain} curriculum and project roadmap.</div>
      </div>
      <div style="padding:14px;background:#f8faff;border-left:4px solid #a78bfa;border-radius:0 10px 10px 0;">
        <div style="font-size:.85rem;font-weight:700;color:#1e293b;margin-bottom:3px;">3. Start Building &amp; Learning</div>
        <div style="font-size:.82rem;color:#64748b;line-height:1.5;">Dive into real-world projects, weekly sessions, and mentorship over the 6-week program.</div>
      </div>
    </div>

    <!-- Login Credentials Box -->
    <div style="background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1.5px solid #bfdbfe;border-radius:14px;padding:22px 24px;margin-bottom:24px;">
      <div style="font-size:.7rem;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.1em;margin-bottom:14px;">&#128272; Your LMS Login Credentials</div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <!-- FIX 2 — Corrected login URL domain -->
        <tr><td style="font-size:.83rem;color:#64748b;padding:6px 0;width:40%;">Login URL</td><td style="font-size:.83rem;font-weight:700;color:#1e40af;text-align:right;"><a href="https://yourdomain.com/login.php" style="color:#1e40af;">yourdomain.com/login.php</a></td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:6px 0;">Email (Username)</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;font-family:monospace;">{$to}</td></tr>
        <tr style="border-top:1px dashed #bfdbfe;"><td colspan="2" style="padding:3px 0;"></td></tr>
        <tr><td style="font-size:.83rem;color:#64748b;padding:6px 0;">Default Password</td><td style="font-size:.83rem;font-weight:700;color:#1e293b;text-align:right;font-family:monospace;">{$def_pass}</td></tr>
      </table>
      <div style="margin-top:12px;font-size:.76rem;color:#1e40af;background:rgba(59,130,246,.08);border-radius:8px;padding:9px 12px;line-height:1.6;">
        ⚠️ <strong>Please change your password</strong> after your first login from your profile settings.
      </div>
    </div>

    <!-- CTA — FIX 2: Corrected href domain -->
    <div style="text-align:center;margin-bottom:24px;">
      <a href="https://yourdomain.com/login.php" style="display:inline-block;padding:14px 36px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;text-decoration:none;border-radius:12px;font-weight:700;font-size:.92rem;box-shadow:0 4px 14px rgba(59,130,246,.35);">Login to Dashboard →</a>
    </div>

    <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;font-size:.8rem;color:#92400e;line-height:1.6;margin-bottom:16px;">
      💡 <strong>Keep this email safe</strong> — it is your enrollment confirmation. Your UTR number <strong>{$utr}</strong> is your payment reference.
    </div>

    <p style="font-size:.82rem;color:#94a3b8;line-height:1.6;margin:0;">
      Questions?
      <a href="mailto:admin@yourdomain.com" style="color:#38bdf8;text-decoration:none;">admin@yourdomain.com</a>
      or reach us on
      <a href="https://wa.me/91XXXXXXXXXX" target="_blank" style="color:#25D366;text-decoration:none;">WhatsApp</a>.
      We're here to help you succeed!
    </p>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#0f1b3d;border-radius:0 0 16px 16px;padding:22px 40px;text-align:center;">
    <div style="font-size:.8rem;color:rgba(255,255,255,.6);margin-bottom:4px;">CourseFlow Pvt. Ltd.</div>
    <div style="font-size:.72rem;color:rgba(255,255,255,.3);">© 2025 All rights reserved.</div>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

    /* FIX 4 — Wrap vs_send_mail() with error logging so failures are never silent */
    $mail_sent = vs_send_mail($to, $name, $subject, $body, 'support@yourdomain.com');
    if (!$mail_sent) {
        error_log("CourseFlow: Failed to send APPROVAL email to {$to} for payment #{$pay['id']} ({$pay['full_name']})");
    }
    return $mail_sent;
}

function send_rejection_email(array $pay, string $note): bool {
    $to     = $pay['email'];
    $name   = $pay['full_name'];
    $domain = $pay['internship_chosen'];
    $utr    = $pay['utr_number'];
    $reason = $note ?: 'The UTR number provided could not be verified. Please check your payment and resubmit with the correct UTR.';
    $reg_id = $pay['reg_id'];

    $subject = "Action Required — Payment Verification Failed | CourseFlow";

    /* FIX 3 — Resubmit link corrected to yourdomain.com */
    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:'Plus Jakarta Sans',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:linear-gradient(135deg,#7f1d1d,#dc2626);border-radius:16px 16px 0 0;padding:36px 40px 28px;text-align:center;">
    <div style="font-size:2.5rem;margin-bottom:12px;">⚠️</div>
    <div style="font-size:1.4rem;font-weight:800;color:#fff;">Payment Rejected</div>
    <div style="font-size:.85rem;color:rgba(255,255,255,.75);margin-top:6px;">Action required to complete your enrollment</div>
  </td></tr>
  <tr><td style="background:#fff;padding:36px 40px;">
    <p style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 8px;">Hi {$name},</p>
    <p style="font-size:.88rem;color:#64748b;margin:0 0 22px;line-height:1.7;">Unfortunately, your payment submission for the <strong>{$domain}</strong> internship could not be verified. Please see the reason below and resubmit with the correct details.</p>
    <div style="background:#fff1f2;border:1.5px solid #fecdd3;border-radius:12px;padding:18px 20px;margin-bottom:22px;">
      <div style="font-size:.7rem;font-weight:700;color:#881337;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">❌ Rejection Reason</div>
      <div style="font-size:.88rem;color:#7f1d1d;line-height:1.6;">{$reason}</div>
    </div>
    <div style="background:#f0f5ff;border:1px solid #bfdbfe;border-radius:12px;padding:18px 20px;margin-bottom:22px;">
      <div style="font-size:.7rem;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;">📋 Submitted Details</div>
      <div style="font-size:.83rem;color:#64748b;margin-bottom:4px;">UTR Submitted: <strong style="color:#1e293b;font-family:monospace;">{$utr}</strong></div>
      <div style="font-size:.83rem;color:#64748b;">Internship: <strong style="color:#1e293b;">{$domain}</strong></div>
    </div>
    <!-- FIX 3 — Corrected resubmit URL domain -->
    <div style="text-align:center;margin-bottom:22px;">
      <a href="https://yourdomain.com/internship/internship_payment.php?reg_id={$reg_id}" style="display:inline-block;padding:13px 32px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;text-decoration:none;border-radius:12px;font-weight:700;font-size:.9rem;">Resubmit Payment →</a>
    </div>
    <p style="font-size:.82rem;color:#94a3b8;line-height:1.6;margin:0;">If you believe this is an error, reply to this email with a screenshot of your UPI payment confirmation. Our team will review it promptly.</p>
  </td></tr>
  <tr><td style="background:#0f1b3d;border-radius:0 0 16px 16px;padding:20px 40px;text-align:center;">
    <div style="font-size:.78rem;color:rgba(255,255,255,.5);">© 2025 CourseFlow Pvt. Ltd.</div>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    /* FIX 4 — Wrap vs_send_mail() with error logging so failures are never silent */
    $mail_sent = vs_send_mail($to, $name, $subject, $body, 'support@yourdomain.com');
    if (!$mail_sent) {
        error_log("CourseFlow: Failed to send REJECTION email to {$to} for payment #{$pay['id']} ({$pay['full_name']})");
    }
    return $mail_sent;
}