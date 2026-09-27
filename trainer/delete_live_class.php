<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("trainer");

$trainer_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    $stmt = $conn->prepare("DELETE FROM live_classes WHERE id = ? AND trainer_id = ?");
    $stmt->bind_param("ii", $id, $trainer_id);
    $stmt->execute();
}

header("Location: live_classes.php");
exit();
