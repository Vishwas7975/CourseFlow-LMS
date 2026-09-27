<?php
/**
 * internship_register.php - CourseFlow
 * Handles Summer Short Term Internship registration form submission.
 * ADDED: Premium Email Template with full student details.
 * PRESERVED: All existing logic, data cleaning, and admin notifications.
 */

/* ——— Buffer output so any PHP warning doesn't corrupt JSON ——— */
ob_start();

/* ——— Always respond with JSON ——— */
header('Content-Type: application/json; charset=utf-8');

/* ——— Helper: clean output buffer and exit with JSON ——— */
function jsonOut(array $data): void {
    ob_end_clean();
    echo json_encode($data);
    exit;
}

/* ——— Only accept POST ——— */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['success' => false, 'message' => 'Method not allowed.']);
}

/* ——— DB connection ——— */
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

if (!isset($conn)) {
    jsonOut(['success' => false, 'message' => 'DB config not found.']);
}

/* ——— Data Collection & Sanitization ——— */
$full_name           = trim($_POST['full_name'] ?? '');
$registration_number = trim($_POST['registration_number'] ?? '');
$email               = trim($_POST['email'] ?? '');
$mobile_number       = trim($_POST['mobile_number'] ?? '');
$college_name        = strtoupper(trim($_POST['college_name'] ?? ''));
$department          = trim($_POST['department'] ?? '');
$internship_chosen   = trim($_POST['internship_chosen'] ?? '');
$admission_type      = trim($_POST['admission_type'] ?? 'N/A');
/* source_page tells us which form the student came from:
   'index'                   → ₹299 payment (internship_payment1.php)
   'internship_registration' → ₹500 payment (internship_payment.php)  */
$source_page         = trim($_POST['source_page'] ?? 'internship_registration');

/* ——— Validation ——— */
if (!$full_name || !$email || !$mobile_number || !$internship_chosen) {
    jsonOut(['success' => false, 'message' => 'Please fill all required fields.']);
}

$mobile_clean = preg_replace('/[^0-9]/', '', $mobile_number);

/* ——— Duplicate Check ——— */
/* Check if same student (by email OR registration_number) already registered for the same internship domain */
$dup_stmt = $conn->prepare("SELECT id FROM internship_registrations WHERE (email = ? OR registration_number = ?) AND internship_chosen = ? LIMIT 1");
$dup_stmt->bind_param("sss", $email, $registration_number, $internship_chosen);
$dup_stmt->execute();
$dup_stmt->store_result();
if ($dup_stmt->num_rows > 0) {
    $dup_stmt->close();
    jsonOut(['success' => false, 'message' => 'You have already registered for this internship. Please check your email for the payment link.']);
}
$dup_stmt->close();

/* ——— Database Insertion ——— */
$stmt = $conn->prepare("INSERT INTO internship_registrations (full_name, registration_number, email, mobile_number, college_name, department, internship_chosen, source_page) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssss", $full_name, $registration_number, $email, $mobile_clean, $college_name, $department, $internship_chosen, $source_page);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    jsonOut(['success' => false, 'message' => 'Registration failed: ' . $error]);
}

$reg_id = $conn->insert_id;
$stmt->close();

/* ═══════════════════════════════════════════════════════
   PREMIUM AUTOMATIC EMAIL WITH STUDENT DETAILS
═══════════════════════════════════════════════════════ */
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/mailer.php'; 

/* Payment page depends on which form they came from:
   index.html                      → internship_payment1.php (₹299)
   internship_registration.html    → internship_payment.php  (₹500)
   offline_internship_registration → internship_payment_offline.php (₹7,080) */
$pay_page = 'internship_payment.php';
if ($source_page === 'index')   $pay_page = 'internship_payment1.php';
if ($source_page === 'offline') $pay_page = 'internship_payment_offline.php';
$payment_link = "https://yourdomain.com/internship/" . $pay_page . "?reg_id=" . $reg_id;
$subject = "Enrollment Successful - CourseFlow Internship Program 2026";

$body = "
<div style='font-family: Arial, sans-serif; background-color:#f4f4f7; padding:40px 0;'>

    <div style='max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,0.05);'>
        
        <div style='text-align:center; padding:30px 20px; background:#ffffff;'>
            <img src='https://yourdomain.com/logo.jpeg' alt='CourseFlow' style='max-width:160px;'>
        </div>

        <div style='padding:30px 25px; color:#333333;'>
            
            <h2 style='margin-top:0;'>Congratulations $full_name, You are Successfully enrolled in <b>$internship_chosen Internship Program</b> </h2>

            <p style='font-size:15px; line-height:1.6;'>
                We’re excited to have you onboard for the <b>$internship_chosen Internship Program</b> at CourseFlow.
            </p>

            <div style='background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin: 20px 0; font-size: 14px; line-height: 1.8;'>
                <p style='margin:0;'><b>Full Name (as per SSC):</b> $full_name</p>
                <p style='margin:0;'><b>Registration Number:</b> $registration_number</p>
                <p style='margin:0;'><b>Email ID:</b> $email</p>
                <p style='margin:0;'><b>Mobile Number:</b> $mobile_clean</p>
                <p style='margin:0;'><b>Department:</b> $department</p>
                <p style='margin:0;'><b>College Name:</b> $college_name</p>
                <p style='margin:0;'><b>Internship Chosen:</b> $internship_chosen</p>
                <p style='margin:0;'><b>Admission Type:</b> $admission_type</p>
            </div>

            <p style='font-size:15px; line-height:1.6;'>
                To proceed further, please complete your payment using the secure link below:
            </p>

            <div style='text-align:center; margin:30px 0;'>
                <a href='$payment_link' 
                   style='background:#7c3aed; color:#ffffff; padding:14px 28px; text-decoration:none; 
                          border-radius:6px; font-size:16px; font-weight:bold; display:inline-block;'>
                    Confirm Details & Complete Payment Here!!
                </a>
            </div>

            <p style='font-size:14px; color:#555;'>
                If the button above doesn’t work, you can copy and paste the link below into your browser: 
            </p>

            <p style='font-size:13px; word-break:break-all; color:#7c3aed;'>
                $payment_link
            </p>

            <p style='margin-top:30px; font-size:14px;'>
                If you have any questions, feel free to reach out to our support team.
            </p>

            <p style='margin-top:20px;'>
                Best regards,<br>
                <b>CourseFlow Team</b>
            </p>

        </div>

        <div style='background:#f4f4f7; text-align:center; padding:20px; font-size:12px; color:#888;'>
            © " . date('Y') . " CourseFlow. All rights reserved.<br>
            This is an automated email — please do not reply.
        </div>

    </div>

</div>
";

// Send the automated mail
vs_send_mail($email, $full_name, $subject, $body);

/* ═══════════════════════════════════════════════════════
   PRESERVED: Admin notification (silent)
═══════════════════════════════════════════════════════ */
$notif_title = 'New Registration: ' . $full_name;
$notif_message = "Track: $internship_chosen | College: $college_name | Reg No: $registration_number";
$ns = $conn->prepare("INSERT INTO notifications (type, for_role, title, message, is_read) VALUES ('new_registration', 'admin', ?, ?, 0)");

if ($ns) {
    $ns->bind_param('ss', $notif_title, $notif_message);
    $ns->execute();
    $ns->close();
}

/* ═══════════════════════════════════════════════════════
   PRESERVED: Return success JSON
═══════════════════════════════════════════════════════ */
jsonOut([
    'success'             => true,
    'reg_id'              => $reg_id,
    'name'                => $full_name,
    'internship'          => $internship_chosen,
    'mobile'              => $mobile_clean,
    'registration_number' => $registration_number,
    'message'             => 'Registration successful! Check your email for details and payment link.'
]);