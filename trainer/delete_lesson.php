<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="trainer"){
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM lessons WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();

header("Location: lessons.php");
exit();
?>