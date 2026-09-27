<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("student");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php"); exit;
}

$user_id     = $_SESSION['user_id'];
$course_id   = isset($_POST['course_id']) && is_numeric($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
$utr         = isset($_POST['utr_number']) ? trim($_POST['utr_number']) : '';
$payment_mode= in_array($_POST['payment_mode'] ?? '', ['new','remaining']) ? $_POST['payment_mode'] : 'new';

// ── UTR basic format validation ──
// UTR/RRN must be 12 digits (IMPS/NEFT/UPI reference numbers are always numeric, 12 digits)
if (!$course_id || strlen($utr) < 6) {
    header("Location: payment.php?course_id={$course_id}&error=invalid_utr"); exit;
}
// If it looks numeric and is between 6-50 chars, check format more strictly
// UTR numbers: typically 12 digits for UPI/IMPS, or alphanumeric for NEFT
if (!preg_match('/^[A-Za-z0-9]{6,50}$/', $utr)) {
    header("Location: payment.php?course_id={$course_id}&error=invalid_utr"); exit;
}


// ── Fetch course ──
$sc = $conn->prepare("SELECT id, title, price FROM courses WHERE id = ?");
$sc->bind_param("i", $course_id); $sc->execute();
$course = $sc->get_result()->fetch_assoc();
if (!$course) { header("Location: dashboard.php?error=course_not_found"); exit; }

// ── Fetch student ──
$ss = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$ss->bind_param("i", $user_id); $ss->execute();
$student = $ss->get_result()->fetch_assoc();

define('GST_RATE', 18.00);
$gst_rate_val = 18.00; // Variable copy of GST_RATE for bind_param (constants can't be passed by reference)
$base_price   = (float)$course['price'];
$gst_amt      = round($base_price * GST_RATE / 100, 2);
$total_price  = round($base_price + $gst_amt, 2);
$half_price   = round($total_price / 2, 2);

// ═══════════════════════════════════════════════════════
// MODE A — REMAINING PAYMENT (second installment)
// ═══════════════════════════════════════════════════════
if ($payment_mode === 'remaining') {
    $parent_id = isset($_POST['parent_payment_id']) && is_numeric($_POST['parent_payment_id']) ? (int)$_POST['parent_payment_id'] : 0;
    if (!$parent_id) { header("Location: payment.php?course_id={$course_id}&error=invalid"); exit; }

    // Validate parent payment
    $sp = $conn->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ? AND course_id = ? AND status = 'approved' AND payment_status = 'partial'");
    $sp->bind_param("iii", $parent_id, $user_id, $course_id); $sp->execute();
    $parent = $sp->get_result()->fetch_assoc();
    if (!$parent || (float)$parent['pending_amount'] <= 0) {
        header("Location: payment_status.php?error=invalid_remaining"); exit;
    }

    $remaining_amt = (float)$parent['pending_amount'];

    // ── Check if this UTR already exists in payments table ──
    $utr_check = $conn->prepare("SELECT id FROM payments WHERE utr_number = ? LIMIT 1");
    $utr_check->bind_param("s", $utr); $utr_check->execute();
    if ($utr_check->get_result()->fetch_assoc()) {
        header("Location: payment.php?course_id={$course_id}&error=duplicate_utr"); exit;
    }

    $conn->begin_transaction();
    try {
        // Insert new payment row for remaining
        $submitted_at = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        $ins = $conn->prepare("INSERT INTO payments (user_id, course_id, amount, utr_number, status, payment_type, gst_rate, base_amount, gst_amount, total_amount, paid_amount, pending_amount, payment_status, created_at) VALUES (?, ?, ?, ?, 'pending', 'partial', ?, ?, ?, ?, ?, 0, 'pending', ?)");
        $ins->bind_param("iidsddddds", $user_id, $course_id, $remaining_amt, $utr, $gst_rate_val, $parent['base_amount'], $parent['gst_amount'], $parent['total_amount'], $remaining_amt, $submitted_at);
        $ins->execute();

        // Notify admin
        $ar="admin"; $at="Remaining Payment Submitted";
        $am=htmlspecialchars($student['name'])." submitted remaining ₹".number_format($remaining_amt,2)." for \""
          .htmlspecialchars($course['title'])."\". UTR: ".htmlspecialchars($utr);
        $al="payments.php?filter=pending";
        $n=$conn->prepare("INSERT INTO notifications (type,for_role,for_user_id,title,message,link,is_read) VALUES ('payment',?,NULL,?,?,?,0)");
        $n->bind_param("ssss",$ar,$at,$am,$al); $n->execute();

        $conn->commit();
        header("Location: payment_status.php?success=remaining"); exit;
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: payment.php?course_id={$course_id}&error=db_error"); exit;
    }
}

// ═══════════════════════════════════════════════════════
// MODE B — NEW PAYMENT (full or partial)
// ═══════════════════════════════════════════════════════
$payment_type = isset($_POST['payment_type']) && $_POST['payment_type'] === 'partial' ? 'partial' : 'full';
$is_partial   = ($payment_type === 'partial');

// Values from hidden fields (validated server-side)
$posted_base  = isset($_POST['base_amount'])  ? round((float)$_POST['base_amount'],2)  : $base_price;
$posted_gst_r = isset($_POST['gst_rate'])     ? round((float)$_POST['gst_rate'],2)     : GST_RATE;
$posted_gst_a = isset($_POST['gst_amount'])   ? round((float)$_POST['gst_amount'],2)   : $gst_amt;
$posted_total = isset($_POST['total_amount']) ? round((float)$_POST['total_amount'],2) : $total_price;

// Server-side recalculate (trust server, not client)
$sv_gst_a    = round($base_price * GST_RATE / 100, 2);
$sv_total    = round($base_price + $sv_gst_a, 2);
$sv_half     = round($sv_total / 2, 2);
$paid_now    = $is_partial ? $sv_half : $sv_total;
$pending_now = $is_partial ? round($sv_total - $sv_half, 2) : 0;

if ($paid_now <= 0) {
    header("Location: payment.php?course_id={$course_id}&error=invalid_amount"); exit;
}

// Block duplicates: no new payment if active pending or fully paid already exists
$dc = $conn->prepare("SELECT id, status, payment_status FROM payments WHERE user_id=? AND course_id=? AND status IN ('pending','approved') ORDER BY id DESC LIMIT 1");
$dc->bind_param("ii", $user_id, $course_id); $dc->execute();
$dup = $dc->get_result()->fetch_assoc();
if ($dup) {
    $ds = $dup['status']; $dps = $dup['payment_status'];
    if ($ds === 'pending')                       { header("Location: payment_status.php?error=duplicate"); exit; }
    if ($ds === 'approved' && $dps === 'paid')   { header("Location: payment_status.php?error=duplicate"); exit; }
    if ($ds === 'approved' && $dps === 'partial'){ header("Location: payment.php?course_id={$course_id}"); exit; }
}

// ── Check if this UTR already exists in payments table ──
$utr_chk = $conn->prepare("SELECT id FROM payments WHERE utr_number = ? LIMIT 1");
$utr_chk->bind_param("s", $utr); $utr_chk->execute();
if ($utr_chk->get_result()->fetch_assoc()) {
    header("Location: payment.php?course_id={$course_id}&error=duplicate_utr"); exit;
}

$conn->begin_transaction();
try {
   $submitted_at = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
   $ins = $conn->prepare("INSERT INTO payments (user_id, course_id, amount, utr_number, status, payment_type, gst_rate, base_amount, gst_amount, total_amount, paid_amount, pending_amount, payment_status, created_at) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
   $ins->bind_param("iidssdddddds", $user_id, $course_id, $paid_now, $utr, $payment_type, $gst_rate_val, $base_price, $sv_gst_a, $sv_total, $paid_now, $pending_now, $submitted_at);
    $ins->execute();

    // Notify admin
    $ar="admin";
    $at = $is_partial ? "New Partial Payment (50%)" : "New Full Payment";
    $am = htmlspecialchars($student['name'])." submitted ".($is_partial?"50% installment":"full payment")
        ." of ₹".number_format($paid_now,2)." for \"".htmlspecialchars($course['title'])."\". UTR: ".htmlspecialchars($utr);
    $al = "payments.php?filter=pending";
    $n = $conn->prepare("INSERT INTO notifications (type,for_role,for_user_id,title,message,link,is_read) VALUES ('payment',?,NULL,?,?,?,0)");
    $n->bind_param("ssss",$ar,$at,$am,$al); $n->execute();

    $conn->commit();
    header("Location: payment_status.php?success=1"); exit;
} catch (Exception $e) {
    $conn->rollback();
    header("Location: payment.php?course_id={$course_id}&error=db_error"); exit;
}