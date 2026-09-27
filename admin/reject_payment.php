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

// Fetch payment with student + course info
$stmt = $conn->prepare("
    SELECT p.*, c.title AS course_title, u.name AS student_name
    FROM payments p
    JOIN courses c ON p.course_id = c.id
    JOIN users   u ON p.user_id   = u.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment)                        { header("Location: payments.php?msg=error&filter=pending"); exit; }
if ($payment['status'] !== 'pending') { header("Location: payments.php?msg=error&filter=pending"); exit; }

$user_id = (int)$payment['user_id'];


$conn->begin_transaction();
try {
    // Reject payment
    $u = $conn->prepare("UPDATE payments SET status='rejected', payment_status='rejected' WHERE id=?");
    $u->bind_param("i", $payment_id); $u->execute();

    // Notify student
    $sr  = "student";
    $st  = "Payment Rejected";
    $sm  = "Your payment for \"".$payment['course_title']."\" was rejected. Please verify your UTR number and resubmit, or contact support for assistance.";
    $sl  = "payment_status.php";
    $sn  = $conn->prepare("INSERT INTO notifications (type,for_role,for_user_id,title,message,link,is_read) VALUES ('payment',?,?,?,?,?,0)");
    $sn->bind_param("sisss", $sr, $user_id, $st, $sm, $sl); $sn->execute();

    // Mark admin notifications read
    $conn->query("UPDATE notifications SET is_read=1 WHERE for_role='admin' AND is_read=0");

    $conn->commit();
    header("Location: payments.php?msg=rejected&filter=pending"); exit;
} catch (Exception $e) {
    $conn->rollback();
    header("Location: payments.php?msg=error&filter=pending"); exit;
}