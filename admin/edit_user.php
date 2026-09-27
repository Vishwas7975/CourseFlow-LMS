<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){ header("Location: ../login.php"); exit(); }
if(!isset($_GET['id'])){ header("Location: users.php"); exit(); }
$id=(int)$_GET['id'];
$stmt=$conn->prepare("SELECT * FROM users WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute();
$user=$stmt->get_result()->fetch_assoc();
if(!$user){ die("User not found"); }
if(isset($_POST['update'])){
    $name=$_POST['name']; $email=$_POST['email']; $role=$_POST['role'];
    $u=$conn->prepare("UPDATE users SET name=?,email=?,role=? WHERE id=?");
    $u->bind_param("sssi",$name,$email,$role,$id);
    if($u->execute()){ header("Location: users.php"); exit(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">
            <div class="page-header"><h1><i class="fas fa-user-edit"></i> Edit User</h1></div>
            <div class="edit-user-container">
                <h2><i class="fas fa-user" style="color:var(--primary)"></i> User Details</h2>
                <form method="POST" style="background:none;padding:0;box-shadow:none;border:none;margin:0">
                    <div class="form-group"><label>Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-group"><label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group"><label>Role</label>
                        <select name="role" required>
                            <option value="admin"   <?php echo $user['role']=='admin'?'selected':''; ?>>Admin</option>
                            <option value="trainer" <?php echo $user['role']=='trainer'?'selected':''; ?>>Trainer</option>
                            <option value="student" <?php echo $user['role']=='student'?'selected':''; ?>>Student</option>
                        </select>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:8px">
                        <button type="submit" name="update" class="btn btn-primary"><i class="fas fa-save"></i> Update User</button>
                        <a href="users.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
