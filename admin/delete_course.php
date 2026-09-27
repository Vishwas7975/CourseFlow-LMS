<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM courses WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();

header("Location: courses.php");
exit();