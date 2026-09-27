<?php

session_start();

include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

if(!isset($_FILES['excel_file']))
{
$_SESSION['toast'] = "No file uploaded";
header("Location: excel_upload.php");
exit();
}

$file = $_FILES['excel_file']['tmp_name'];

$handle = fopen($file,"r");

$first = true;

$inserted = 0;

/* Prepare INSERT once outside loop for performance */
$stmt_ins = $conn->prepare("
INSERT INTO users
(name,email,password,role,created_at,status,mobile,college,passout_year,hall_ticket,certificate_number,internship_course,start_date,end_date,download_link,verification_symbol,dept)
VALUES
(?,?,?,'student',NOW(),'active',?,?,?,?,?,?,?,?,?,?)
");

while(($row = fgetcsv($handle,1000,",")) !== FALSE)
{

if($first)
{
$first=false;
continue;
}

$certificate_number = trim($row[0] ?? '');
$name               = trim($row[1] ?? '');
$email              = trim($row[2] ?? '');
$mobile             = trim($row[3] ?? '');
$college            = trim($row[4] ?? '');
$dept               = trim($row[5] ?? '');
$passout_year       = trim($row[6] ?? '');
$hall_ticket        = trim($row[7] ?? '');
$internship_course  = trim($row[8] ?? '');
$start_date         = trim($row[9] ?? '');
$end_date           = trim($row[10] ?? '');
$download_link      = trim($row[11] ?? '');
$verification_symbol= trim($row[12] ?? 'Verified');

if(empty($email)) continue;

/* CHECK DUPLICATE EMAIL */
$stmt_chk = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt_chk->bind_param("s", $email);
$stmt_chk->execute();
$stmt_chk->store_result();

if($stmt_chk->num_rows > 0)
{
$stmt_chk->close();
continue;
}
$stmt_chk->close();

/* DEFAULT PASSWORD */
$password = password_hash("student123", PASSWORD_DEFAULT);

/* INSERT STUDENT — prepared statement (prevents SQL injection) */
$stmt_ins->bind_param("sssssssssssss",
    $name,
    $email,
    $password,
    $mobile,
    $college,
    $passout_year,
    $hall_ticket,
    $certificate_number,
    $internship_course,
    $start_date,
    $end_date,
    $download_link,
    $verification_symbol,
    $dept
);

if($stmt_ins->execute())
{
    $inserted++;
}

}

$stmt_ins->close();
fclose($handle);

$_SESSION['toast'] = "$inserted students uploaded successfully";

header("Location: student_data.php");

exit();

?>