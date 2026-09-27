<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

// ADMIN SECURITY CHECK
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

// CHECK IF ID EXISTS
if(!isset($_GET['id'])){
    header("Location: lessons.php");
    exit();
}

$id = $_GET['id'];

// DELETE LESSON
$stmt = $conn->prepare("DELETE FROM lessons WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();

// REDIRECT BACK
header("Location: lessons.php");
exit();