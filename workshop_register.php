<?php
/**
 * workshop_register.php — CourseFlow
 * Handles FREE workshop registration form submission.
 * Returns JSON: { success, reg_id, name, domain, whatsapp, message }
 */

/* ─── Buffer output so any PHP warning doesn't corrupt JSON ─── */
ob_start();

/* ─── Always respond with JSON ─── */
header('Content-Type: application/json; charset=utf-8');

/* ─── Helper: clean output buffer and exit with JSON ─── */
function jsonOut(array $data): void {
    ob_end_clean();
    echo json_encode($data);
    exit;
}

/* ─── Only accept POST ─── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['success' => false, 'message' => 'Method not allowed.']);
}

/* ─── DB connection (same path as payment.php / submit_payment.php) ─── */
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php'; // provides $conn (mysqli)

if (!isset($conn)) {
    error_log('workshop_register.php: $conn not set after including db.php');
    jsonOut(['success' => false, 'message' => 'DB config not found. Contact support.']);
}

if ($conn->connect_errno) {
    error_log('workshop_register.php: mysqli connect error ' . $conn->connect_errno . ' — ' . $conn->connect_error);
    jsonOut(['success' => false, 'message' => 'DB connect failed (code ' . $conn->connect_errno . '). Please try again.']);
}

/* ─── Auto-create table ─── */
$conn->query("
    CREATE TABLE IF NOT EXISTS workshop_registrations (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        full_name        VARCHAR(120)  NOT NULL,
        hall_ticket      VARCHAR(40)   NOT NULL,
        email            VARCHAR(150)  NOT NULL,
        year_of_study    TINYINT(1)    NOT NULL,
        contact_number   VARCHAR(20)   NOT NULL,
        whatsapp_number  VARCHAR(20)   NOT NULL,
        college_name     VARCHAR(220)  NOT NULL,
        college_location VARCHAR(200)  NOT NULL,
        department       VARCHAR(200)  NOT NULL,
        interested_domain VARCHAR(60)  NOT NULL,
        source           VARCHAR(60)   NOT NULL DEFAULT 'direct',
        ip_address       VARCHAR(45)   DEFAULT NULL,
        registered_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email  (email),
        INDEX idx_hall   (hall_ticket),
        INDEX idx_domain (interested_domain)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ─── Helper: sanitize string — no typed hints for PHP 7 compat ─── */
function wsClean($val, $max = 200) {
    return mb_substr(trim(strip_tags((string)$val)), 0, $max);
}

/* ─── Read and sanitize inputs ─── */
$full_name         = wsClean($_POST['full_name']        ?? '', 120);
$hall_ticket       = wsClean($_POST['hall_ticket']      ?? '', 40);
$email             = strtolower(trim((string)($_POST['email'] ?? '')));
$year_of_study     = isset($_POST['year_of_study']) ? (int)$_POST['year_of_study'] : 0;
$contact_number    = wsClean($_POST['contact_number']   ?? '', 20);
$whatsapp_number   = wsClean($_POST['whatsapp_number']  ?? '', 20);
$college_name      = wsClean($_POST['college_name']     ?? '', 220);
$college_location  = wsClean($_POST['college_location'] ?? '', 200);
$department        = wsClean($_POST['department']       ?? '', 200);
$interested_domain = wsClean($_POST['interested_domain'] ?? '', 60);
$source            = wsClean($_POST['source']           ?? 'direct', 60);
$ip_address        = $_SERVER['REMOTE_ADDR'] ?? '';

/* ─── Strip spaces/dashes from phone numbers ─── */
$cn_clean = preg_replace('/[\s\-]/', '', $contact_number);
$wa_clean = preg_replace('/[\s\-]/', '', $whatsapp_number);

/* ─── Server-side validation ─── */
$errors = [];

if (strlen($full_name) < 3)
    $errors[] = 'Full name must be at least 3 characters.';

if (strlen($hall_ticket) < 3)
    $errors[] = 'Hall ticket / Roll number is required.';

if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = 'Please enter a valid email address.';

if (!in_array($year_of_study, [1, 2, 3, 4]))
    $errors[] = 'Please select your year of study.';

if (!preg_match('/^[6-9]\d{9}$/', $cn_clean))
    $errors[] = 'Enter a valid 10-digit Indian contact number.';

if (!preg_match('/^[6-9]\d{9}$/', $wa_clean))
    $errors[] = 'Enter a valid 10-digit Indian WhatsApp number.';

if (strlen($college_name) < 5)
    $errors[] = 'College name must be at least 5 characters.';

if (strlen($college_location) < 3)
    $errors[] = 'College location is required.';

if (strlen($department) < 3)
    $errors[] = 'Department is required.';

$allowed_domains = [
    'AIML', 'Cyber Security', 'Web Development',
    'Java Fullstack Development', 'Data Analytics', 'Data Science',
    'HR', 'Finance', 'Marketing'
];

if (!in_array($interested_domain, $allowed_domains))
    $errors[] = 'Please select a valid interested domain.';

if (!empty($errors)) {
    jsonOut(['success' => false, 'message' => implode(' ', $errors)]);
}

/* ─── Duplicate check ─── */
$dup = $conn->prepare("SELECT id FROM workshop_registrations WHERE email = ? OR hall_ticket = ? LIMIT 1");
if ($dup) {
    $dup->bind_param('ss', $email, $hall_ticket);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        $dup->close();
        jsonOut([
            'success' => false,
            'message' => 'You have already registered! We will send workshop details to your WhatsApp number soon.'
        ]);
    }
    $dup->close();
}

/* ─── Insert registration ─── */
$stmt = $conn->prepare("
    INSERT INTO workshop_registrations
        (full_name, hall_ticket, email, year_of_study, contact_number,
         whatsapp_number, college_name, college_location, department,
         interested_domain, source, ip_address)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    error_log('workshop_register.php prepare error: ' . $conn->error);
    jsonOut(['success' => false, 'message' => 'Database error. Please try again.']);
}

$stmt->bind_param(
    'sssissssssss',
    $full_name, $hall_ticket, $email, $year_of_study,
    $cn_clean, $wa_clean,
    $college_name, $college_location, $department,
    $interested_domain, $source, $ip_address
);

if (!$stmt->execute()) {
    error_log('workshop_register.php execute error: ' . $stmt->error);
    $stmt->close();
    jsonOut(['success' => false, 'message' => 'Registration failed. Please try again.']);
}

$reg_id = $conn->insert_id;
$stmt->close();


$notif_title   = 'New Workshop Registration';
$notif_message = $full_name . ' registered for Free Workshop (' . $interested_domain
               . '). Hall Ticket: ' . $hall_ticket
               . '. WhatsApp: ' . $wa_clean
               . '. College: ' . $college_name . '.';
$notif_link    = 'workshop_registrations.php';
$notif_role    = 'admin';

$ns = $conn->prepare("
    INSERT INTO notifications (type, for_role, for_user_id, title, message, link, is_read)
    VALUES ('workshop', ?, NULL, ?, ?, ?, 0)
");
if ($ns) {
    $ns->bind_param('ssss', $notif_role, $notif_title, $notif_message, $notif_link);
    $ns->execute();
    $ns->close();
}

/* ─── Return success ─── */
jsonOut([
    'success'  => true,
    'reg_id'   => $reg_id,
    'name'     => $full_name,
    'domain'   => $interested_domain,
    'whatsapp' => $wa_clean,
    'message'  => 'Registration successful! Workshop details will be sent to your WhatsApp number.'
]);