<?php

function changePassword($conn, $user_id, $current, $new, $confirm){

    if($new !== $confirm){
        return "Passwords do not match!";
    }

    if(strlen($new) < 6){
        return "Password must be at least 6 characters!";
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if(!password_verify($current, $row['password'])){
        return "Current password is incorrect!";
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param("si", $hash, $user_id);
    $stmt->execute();

    return "success";
}
?>