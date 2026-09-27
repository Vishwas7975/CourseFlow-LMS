<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'];

// prevent admin deleting himself (important)
if($id == $_SESSION['user_id']){
    die("You cannot delete yourself.");
}

$stmt = $conn->prepare("DELETE FROM users WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();

header("Location: users.php");
exit();