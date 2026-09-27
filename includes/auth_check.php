<?php
function requireLogin($conn){
    if(!isset($_SESSION['user_id'])){
        header("Location: ../login.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT status, role FROM users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if(!$user || $user['status'] != 'active'){
        session_destroy();
        header("Location: ../login.php?error=inactive");
        exit();
    }

    $_SESSION['role'] = $user['role'];
}

function requireRole($role){
    if($_SESSION['role'] != $role){
        header("Location: ../login.php");
        exit();
    }
}
?>