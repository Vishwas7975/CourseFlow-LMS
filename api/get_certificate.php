<?php
/*
 * api/get_certificate.php — CourseFlow LMS
 * Returns student certificate data as JSON.
 * Called by the certificate template editor (admin preview) and student cert page.
 */
session_start();
include("../config/db.php");

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

$search = trim($_GET['search'] ?? '');

if (empty($search)) {
    echo json_encode(['success' => false, 'message' => 'Search term required']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT name, email, mobile, college, certificate_number,
            internship_course, start_date, end_date
     FROM users
     WHERE (certificate_number = ? OR email = ?)
       AND role = 'student'
     LIMIT 1"
);
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
    echo json_encode([
        'success' => true,
        'data'    => [
            'candidate_name'     => $row['name']               ?? '',
            'email'              => $row['email']              ?? '',
            'mobile_no'          => $row['mobile']             ?? '',
            'college'            => $row['college']            ?? '',
            'certificate_number' => $row['certificate_number'] ?? '',
            'internship_course'  => $row['internship_course']  ?? '',
            'start_date'         => $row['start_date']         ?? '',
            'end_date'           => $row['end_date']           ?? '',
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No certificate found for that number or email.']);
}