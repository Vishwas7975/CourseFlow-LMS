<?php

function changePassword($conn, $user_id, $current, $new, $confirm){

    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if(!$row){
        return "User not found.";
    }

    // Verify current password
    if(!password_verify($current, $row['password'])){
        return "Current password incorrect.";
    }

    // Validate new password
    if($new !== $confirm){
        return "Passwords do not match.";
    }

    if(strlen($new) < 6){
        return "Password must be at least 6 characters.";
    }

    // Hash new password
    $hash = password_hash($new, PASSWORD_DEFAULT);

    // Update password
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param("si", $hash, $user_id);

    if($stmt->execute()){
        return "success";
    }

    return "Failed to update password.";
}
?>