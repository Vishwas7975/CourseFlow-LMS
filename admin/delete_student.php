<?php

session_start();

include("../config/db.php");
include("../includes/auth_check.php");

/* SECURITY CHECK */

requireLogin($conn);
requireRole("admin");




/* CHECK IF ID EXISTS */

if(!isset($_GET['id']))
{
header("Location: student_data.php");
exit();
}

$id = intval($_GET['id']);

/* OPTIONAL: CHECK IF USER EXISTS */

$check = mysqli_query($conn,"
SELECT id FROM users 
WHERE id='$id' AND role='student'
");

if(mysqli_num_rows($check)==0)
{
header("Location: student_data.php");
exit();
}


/* DELETE ENROLLMENTS */

mysqli_query($conn,"
DELETE FROM enrollments
WHERE user_id='$id'
");


/* DELETE USER */

mysqli_query($conn,"
DELETE FROM users
WHERE id='$id'
");


/* SUCCESS REDIRECT */

$_SESSION['msg'] = "Student deleted successfully";

header("Location: student_data.php");

exit();

?>