<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: payments.php"); exit;
}
$payment_id = (int)$_GET['id'];


// Fetch payment with full info
$stmt = $conn->prepare("
    SELECT p.*, c.title AS course_title, u.name AS student_name, u.email AS student_email
    FROM payments p
    JOIN courses c ON p.course_id = c.id
    JOIN users   u ON p.user_id   = u.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment)                          { header("Location: payments.php?msg=error"); exit; }
if ($payment['status'] !== 'pending')   { header("Location: payments.php?msg=error&filter=".$payment['status']); exit; }

$user_id   = (int)$payment['user_id'];
$course_id = (int)$payment['course_id'];
$ptype     = $payment['payment_type']   ?? 'full';
$paid_a    = (float)($payment['paid_amount']    ?: $payment['amount']);
$pend_a    = (float)($payment['pending_amount'] ?? 0);
$total_a   = (float)($payment['total_amount']   ?: $payment['amount']);

// Check enrollment
$ec = $conn->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=?");
$ec->bind_param("ii", $user_id, $course_id); $ec->execute();
$already_enrolled = $ec->get_result()->fetch_assoc();

// Determine if this is a remaining installment submission:
// A remaining installment is a 'partial' payment_type row with pending_amount=0
// (it carries the remaining amount in 'amount'/'paid_amount' but no new pending)
// AND there exists an already-approved partial payment for the same user+course
$is_remaining   = false;
$parent_partial = null;
if ($ptype === 'partial' && $pend_a <= 0) {
    $sp = $conn->prepare("SELECT * FROM payments WHERE user_id=? AND course_id=? AND status='approved' AND payment_status='partial' AND pending_amount > 0 ORDER BY id ASC LIMIT 1");
    $sp->bind_param("ii", $user_id, $course_id); $sp->execute();
    $parent_partial = $sp->get_result()->fetch_assoc();
    if ($parent_partial) {
        $is_remaining = true;
    }
}

$conn->begin_transaction();
try {
    if ($ptype === 'full') {
        // ── FULL PAYMENT ──
        $u=$conn->prepare("UPDATE payments SET status='approved', payment_status='paid' WHERE id=?");
        $u->bind_param("i",$payment_id); $u->execute();
        if (!$already_enrolled) {
            $e=$conn->prepare("INSERT INTO enrollments (user_id,course_id,progress) VALUES (?,?,0)");
            $e->bind_param("ii",$user_id,$course_id); $e->execute();
        }
        $stu_title = "Payment Approved — You're Enrolled!";
        $stu_msg   = "Your full payment for \"".$payment['course_title']."\" has been approved. You are now fully enrolled and can start learning immediately.";
        $redirect  = "payments.php?msg=approved&filter=pending";

    } elseif ($ptype === 'partial' && $is_remaining && $parent_partial) {
        // ── REMAINING / SECOND INSTALLMENT ──
        // Mark this payment paid
        $u=$conn->prepare("UPDATE payments SET status='approved', payment_status='paid' WHERE id=?");
        $u->bind_param("i",$payment_id); $u->execute();
        // Mark the original partial as fully paid, zero out pending
        $u2=$conn->prepare("UPDATE payments SET payment_status='paid', pending_amount=0 WHERE id=?");
        $u2->bind_param("i",$parent_partial['id']); $u2->execute();

        $stu_title = "Remaining Payment Approved — Fully Paid!";
        $stu_msg   = "Your remaining balance for \"".$payment['course_title']."\" has been cleared. The course is now fully paid.";
        $redirect  = "payments.php?msg=remaining_approved&filter=pending";

    } elseif ($ptype === 'partial' && $pend_a > 0) {
        // ── FIRST INSTALLMENT (50%) ──
        $u=$conn->prepare("UPDATE payments SET status='approved', payment_status='partial' WHERE id=?");
        $u->bind_param("i",$payment_id); $u->execute();
        if (!$already_enrolled) {
            $e=$conn->prepare("INSERT INTO enrollments (user_id,course_id,progress) VALUES (?,?,0)");
            $e->bind_param("ii",$user_id,$course_id); $e->execute();
        }
        $stu_title = "First Installment Approved — You're Enrolled!";
        $stu_msg   = "Your 50% installment for \"".$payment['course_title']."\" has been approved. You are now enrolled! Pay the remaining ₹".number_format($pend_a,2)." to complete your payment.";
        $redirect  = "payments.php?msg=partial_approved&filter=pending";

    } elseif ($ptype === 'partial' && $pend_a <= 0 && !$is_remaining) {
        // ── PARTIAL WITH ZERO BALANCE (e.g. zero-price course) — treat as fully paid ──
        $u=$conn->prepare("UPDATE payments SET status='approved', payment_status='paid' WHERE id=?");
        $u->bind_param("i",$payment_id); $u->execute();
        if (!$already_enrolled) {
            $e=$conn->prepare("INSERT INTO enrollments (user_id,course_id,progress) VALUES (?,?,0)");
            $e->bind_param("ii",$user_id,$course_id); $e->execute();
        }
        $stu_title = "Payment Approved — You're Enrolled!";
        $stu_msg   = "Your payment for \"".$payment['course_title']."\" has been approved. You are now enrolled.";
        $redirect  = "payments.php?msg=approved&filter=pending";

    } else {
        // ── SAFETY FALLBACK — should not normally reach here ──
        $conn->rollback();
        header("Location: payments.php?msg=error&filter=pending"); exit;
    }

    // Notify student
    $sr="student"; $sl="payment_status.php";
    $sn=$conn->prepare("INSERT INTO notifications (type,for_role,for_user_id,title,message,link,is_read) VALUES ('payment',?,?,?,?,?,0)");
    $sn->bind_param("sisss",$sr,$user_id,$stu_title,$stu_msg,$sl); $sn->execute();

    // Mark admin notifications read
    $conn->query("UPDATE notifications SET is_read=1 WHERE for_role='admin' AND is_read=0");

    $conn->commit();
    header("Location: $redirect"); exit;

} catch (Exception $e) {
    $conn->rollback();
    header("Location: payments.php?msg=error&filter=pending"); exit;
}