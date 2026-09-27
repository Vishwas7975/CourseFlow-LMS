<?php
/**
 * CourseFlow Portal Integration API
 * Endpoint: https://yourdomain.com/api/enroll_student.php
 * Method: POST
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

// ── Security: API Key check ──────────────────────────────────────────────────
define('API_SECRET_KEY', 'your_api_secret_key_here');

// Try multiple ways to get the API key
$apiKey = '';
if (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'];
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    $apiKey  = $headers['X-Api-Key'] ?? $headers['x-api-key'] ?? '';
}

if ($apiKey !== API_SECRET_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'received_key' => $apiKey]);
    exit;
}

// ── Only allow POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ── Get JSON body ────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

// ── Required fields ──────────────────────────────────────────────────────────
$required = ['name', 'email', 'phone', 'course_id', 'amount', 'utr_number'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

$name         = trim($input['name']);
$email        = trim($input['email']);
$phone        = trim($input['phone']);
$course_id    = (int)$input['course_id'];
$amount       = (float)$input['amount'];
$utr_number   = trim($input['utr_number']);
$payment_type = $input['payment_type'] ?? 'full';

// ── DB Connection ────────────────────────────────────────────────────────────
include(__DIR__ . '/../config/db.php');

// ── Check if course exists ───────────────────────────────────────────────────
$sc = $conn->prepare("SELECT id, title, price FROM courses WHERE id = ?");
$sc->bind_param("i", $course_id);
$sc->execute();
$course = $sc->get_result()->fetch_assoc();

if (!$course) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Course not found']);
    exit;
}

// ── Check if user exists by email or mobile ──────────────────────────────────
$su = $conn->prepare("SELECT id, name, email FROM users WHERE email = ? OR mobile = ? LIMIT 1");
$su->bind_param("ss", $email, $phone);
$su->execute();
$existing_user = $su->get_result()->fetch_assoc();

$user_id     = null;
$password    = null;
$is_new_user = false;

if ($existing_user) {
    $user_id = $existing_user['id'];
} else {
    $is_new_user = true;
    $password    = 'VS@' . substr($phone, -4) . '#' . rand(100, 999);
    $hashed      = password_hash($password, PASSWORD_DEFAULT);

    $ins = $conn->prepare("INSERT INTO users (name, email, mobile, password, role, status, created_at) VALUES (?, ?, ?, ?, 'student', 'active', NOW())");
    $ins->bind_param("ssss", $name, $email, $phone, $hashed);

    if (!$ins->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $conn->error]);
        exit;
    }
    $user_id = $conn->insert_id;
}

// ── Check if already enrolled ────────────────────────────────────────────────
$ec = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
$ec->bind_param("ii", $user_id, $course_id);
$ec->execute();
$already_enrolled = $ec->get_result()->fetch_assoc();

// ── Calculate GST ────────────────────────────────────────────────────────────
$gst_rate       = 18.00;
$base_price     = (float)$course['price'];
$gst_amt        = round($base_price * $gst_rate / 100, 2);
$total_price    = round($base_price + $gst_amt, 2);
$paid_amount    = $amount;
$pending_amount = $payment_type === 'partial' ? round($total_price - $amount, 2) : 0;
$payment_status = $pending_amount > 0 ? 'partial' : 'paid';

// ── Begin transaction ─────────────────────────────────────────────────────────
$conn->begin_transaction();
try {
    $ip = $conn->prepare("INSERT INTO payments (user_id, course_id, amount, utr_number, status, payment_type, gst_rate, base_amount, gst_amount, total_amount, paid_amount, pending_amount, payment_status, created_at) VALUES (?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $ip->bind_param("iidssdddddds",
        $user_id, $course_id, $paid_amount, $utr_number,
        $payment_type, $gst_rate, $base_price, $gst_amt,
        $total_price, $paid_amount, $pending_amount, $payment_status
    );
    $ip->execute();
    $payment_id = $conn->insert_id;

    if (!$already_enrolled) {
        $ie = $conn->prepare("INSERT INTO enrollments (user_id, course_id, progress, created_at) VALUES (?, ?, 0, NOW())");
        $ie->bind_param("ii", $user_id, $course_id);
        $ie->execute();
    }

    $notif_title = "Payment Approved - You're Enrolled!";
    $notif_msg   = "Your payment for \"{$course['title']}\" has been approved. You are now enrolled!";
    $notif_link  = "payment_status.php";
    $role        = "student";
    $nn = $conn->prepare("INSERT INTO notifications (type, for_role, for_user_id, title, message, link, is_read) VALUES ('payment', ?, ?, ?, ?, ?, 0)");
    $nn->bind_param("sisss", $role, $user_id, $notif_title, $notif_msg, $notif_link);
    $nn->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Student enrolled successfully',
        'data'    => [
            'user_id'      => $user_id,
            'name'         => $name,
            'email'        => $email,
            'phone'        => $phone,
            'is_new_user'  => $is_new_user,
            'username'     => $email,
            'password'     => $is_new_user ? $password : null,
            'course_id'    => $course_id,
            'course_title' => $course['title'],
            'payment_id'   => $payment_id,
            'amount_paid'  => $paid_amount,
            'portal_url'   => 'https://yourdomain.com/login.php',
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}