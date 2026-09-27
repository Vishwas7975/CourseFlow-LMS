<?php
/**
 * internship_set_payment_choice.php — CourseFlow
 * Called via AJAX after registration when student clicks "Pay Later".
 *
 * Does THREE things immediately (same as admin approval, minus payment verification):
 *   1. Creates student account in `users` table (if not already exists)
 *   2. Auto-maps + enrolls student into matching course
 *   3. Inserts placeholder row in `internship_payments` with status='payment_later'
 *   4. Notifies admin: "Internship Mapped — Payment Pending"
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $d): void { ob_end_clean(); echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['success' => false, 'message' => 'Method not allowed.']);
}

include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

$reg_id = isset($_POST['reg_id']) ? (int)$_POST['reg_id'] : 0;
$choice = trim($_POST['choice'] ?? '');

if (!$reg_id || !in_array($choice, ['pay_later', 'pay_now'])) {
    jsonOut(['success' => false, 'message' => 'Invalid request.']);
}

/* ── Load registration ── */
$stmt = $conn->prepare("SELECT * FROM internship_registrations WHERE id=? LIMIT 1");
$stmt->bind_param("i", $reg_id);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reg) {
    jsonOut(['success' => false, 'message' => 'Registration not found.']);
}

/* ── pay_now: send correct payment page URL based on source ── */
if ($choice === 'pay_now') {
    $src      = strtolower(trim($reg['source_page'] ?? 'internship_registration'));
    $pay_page = 'internship_payment.php';
    if ($src === 'index')   $pay_page = 'internship_payment1.php';
    if ($src === 'offline') $pay_page = 'internship_payment_offline.php';
    $pay_url  = '/internship/' . $pay_page . '?reg_id=' . $reg_id;

    $notif_title   = 'Internship — Student Proceeding to Pay Now';
    $notif_message = $reg['full_name'] . ' chose Pay Now for ' . $reg['internship_chosen']
                   . ' internship. Redirected to payment page. Reg No: ' . $reg['registration_number']
                   . '. Mobile: ' . $reg['mobile_number'] . '.';
    $notif_link    = '/admin/admin_internship_payments.php';
    $notif_role    = 'admin';
    $ns = $conn->prepare("INSERT INTO notifications (type, for_role, for_user_id, title, message, link, is_read) VALUES ('internship', ?, NULL, ?, ?, ?, 0)");
    if ($ns) {
        $ns->bind_param('ssss', $notif_role, $notif_title, $notif_message, $notif_link);
        $ns->execute();
        $ns->close();
    }
    jsonOut(['success' => true, 'message' => 'Pay now noted.', 'pay_url' => $pay_url]);
}

/* ── Ensure internship_payments table supports 'payment_later' ── */
$conn->query("ALTER TABLE internship_payments
    MODIFY COLUMN payment_status ENUM('pending','approved','rejected','payment_later') NOT NULL DEFAULT 'pending'");

/* ── Don't process twice if already has a real payment row ── */
$chk = $conn->prepare("SELECT id, payment_status FROM internship_payments WHERE reg_id=? LIMIT 1");
$chk->bind_param("i", $reg_id);
$chk->execute();
$existing_pay = $chk->get_result()->fetch_assoc();
$chk->close();

if ($existing_pay && $existing_pay['payment_status'] !== 'payment_later') {
    jsonOut(['success' => true, 'message' => 'Already processed.']);
}

$s_name    = $reg['full_name'];
$s_email   = $reg['email'];
$s_mobile  = $reg['mobile_number'];
$s_college = $reg['college_name'];
$s_dept    = $reg['department'];
$s_course  = $reg['internship_chosen'];
$s_regnum  = $reg['registration_number'];

/* ═══════════════════════════════════════════════════
   STEP 1 — Create student account
   Default password = registration number (same as approval flow)
   ═══════════════════════════════════════════════════ */
$def_pass    = $s_regnum;
$hashed_pass = password_hash($def_pass, PASSWORD_DEFAULT);
$new_user_id = null;

$chk2 = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$chk2->bind_param("s", $s_email);
$chk2->execute();
$chk2->store_result();

if ($chk2->num_rows === 0) {
    $ins = $conn->prepare("INSERT INTO users (name, email, password, role, mobile, college, dept, internship_course, status) VALUES (?, ?, ?, 'student', ?, ?, ?, ?, 'active')");
    $ins->bind_param("sssssss", $s_name, $s_email, $hashed_pass, $s_mobile, $s_college, $s_dept, $s_course);
    $ins->execute();
    $new_user_id = $conn->insert_id;
    $ins->close();
} else {
    $chk2->bind_result($new_user_id);
    $chk2->fetch();
}
$chk2->close();

/* ═══════════════════════════════════════════════════
   STEP 2 — Auto-map course enrollment
   ═══════════════════════════════════════════════════ */
if (!empty($new_user_id)) {
    $cq = $conn->prepare("SELECT id, trainer_id FROM courses WHERE LOWER(title) LIKE LOWER(?) LIMIT 1");
    $like_term = '%' . $s_course . '%';
    $cq->bind_param("s", $like_term);
    $cq->execute();
    $cq->bind_result($matched_course_id, $matched_trainer_id);
    $cq->fetch();
    $cq->close();

    if (!empty($matched_course_id)) {
        $ec = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
        $ec->bind_param("ii", $new_user_id, $matched_course_id);
        $ec->execute();
        $ec->store_result();
        if ($ec->num_rows === 0) {
            $tid = !empty($matched_trainer_id) ? (int)$matched_trainer_id : 0;
            $ei  = $conn->prepare("INSERT INTO enrollments (user_id, course_id, trainer_id, status) VALUES (?, ?, ?, 'approved')");
            $ei->bind_param("iii", $new_user_id, $matched_course_id, $tid);
            $ei->execute();
            $ei->close();
        }
        $ec->close();
    }
}

/* ═══════════════════════════════════════════════════
   STEP 3 — Insert payment_later placeholder (if not already there)
   ═══════════════════════════════════════════════════ */
if (!$existing_pay) {
    $ins2 = $conn->prepare("
        INSERT INTO internship_payments
            (reg_id, full_name, email, mobile_number, internship_chosen,
             utr_number, terminal_name, terminal_upi, amount, payment_status)
        VALUES (?, ?, ?, ?, ?, 'PENDING', 'N/A', 'N/A', 0.00, 'payment_later')
    ");
    $ins2->bind_param("issss", $reg_id, $s_name, $s_email, $s_mobile, $s_course);
    $ins2->execute();
    $ins2->close();
}

/* ═══════════════════════════════════════════════════
   STEP 4 — Admin notification
   ═══════════════════════════════════════════════════ */
$notif_title   = 'Internship Mapped — Payment Pending';
$notif_message = $s_name . ' chose Pay Later for ' . $s_course
               . ' internship. Account created & course enrolled immediately.'
               . ' Payment is outstanding. Reg No: ' . $s_regnum
               . '. Mobile: ' . $s_mobile . '.';
$notif_link    = '/admin/admin_internship_payments.php';
$notif_role    = 'admin';

$ns = $conn->prepare("
    INSERT INTO notifications (type, for_role, for_user_id, title, message, link, is_read)
    VALUES ('internship', ?, NULL, ?, ?, ?, 0)
");
if ($ns) {
    $ns->bind_param('ssss', $notif_role, $notif_title, $notif_message, $notif_link);
    $ns->execute();
    $ns->close();
}

jsonOut(['success' => true, 'message' => 'Account created, enrolled, payment marked as pending.']);