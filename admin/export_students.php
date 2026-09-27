<?php

session_start();

include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

/* FILE DOWNLOAD HEADERS */

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=students_export.csv');

/* OUTPUT STREAM */

$output = fopen("php://output", "w");

/* CSV HEADERS */

fputcsv($output, [

"Certificate Number",
"Name",
"Email",
"Mobile",
"College",
"Department",
"Passout Year",
"Hall Ticket",
"Course",
"Start Date",
"End Date",
"Download Link",
"Verification Status"

]);

/* FETCH STUDENTS */

$sql = "SELECT * FROM users WHERE role='student'";

$result = mysqli_query($conn,$sql);

while($row = mysqli_fetch_assoc($result))
{

fputcsv($output,[

$row['certificate_number'],
$row['name'],
$row['email'],
$row['mobile'],
$row['college'],
$row['dept'],
$row['passout_year'],
$row['hall_ticket'],
$row['internship_course'],
$row['start_date'],
$row['end_date'],
$row['download_link'],
$row['verification_symbol']

]);

}

fclose($output);
exit();

?>