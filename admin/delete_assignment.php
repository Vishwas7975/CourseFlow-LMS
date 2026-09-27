<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

// ADMIN SECURITY
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

if(!isset($_GET['id'])){
    header("Location: assign_course.php");
    exit();
}

$id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM enrollments WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();

header("Location: assign_course.php");
exit();