<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php"); exit();
}

// Handle status toggle
if(isset($_GET['toggle_status']) && isset($_GET['id'])){
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT IFNULL(status,'active') as status FROM users WHERE id=$id");
    $row = $res->fetch_assoc();
    $new_status = ($row['status'] == 'active') ? 'inactive' : 'active';
    $conn->query("UPDATE users SET status='$new_status' WHERE id=$id");
    header("Location: users.php"); exit();
}

$trainers_admins = $conn->query("SELECT id,name,email,role,IFNULL(status,'active') as status FROM users WHERE role IN ('trainer','admin') ORDER BY role,name");
$students = $conn->query("SELECT id,name,email,role,IFNULL(status,'active') as status FROM users WHERE role='student' ORDER BY name");
$page_title = "Manage Users";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - CourseFlow LMS</title>
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

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
                <div>
                    <h1 style="font-size:1.5rem;font-weight:800;display:flex;align-items:center;gap:8px"><i class="fas fa-users" style="color:var(--primary)"></i> Manage Users</h1>
                    <p style="color:var(--text-secondary);font-size:0.875rem;margin-top:4px">Manage trainers, admins, and students</p>
                </div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>

            <!-- TRAINERS & ADMINS TABLE -->
            <div class="section-wrapper">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fas fa-chalkboard-teacher" style="color:var(--warning)"></i> Trainers & Admins
                </h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if($trainers_admins && $trainers_admins->num_rows > 0): ?>
                            <?php $i=1; while($row = $trainers_admins->fetch_assoc()): ?>
                            <tr>
                                <td style="color:var(--text-muted)"><?php echo $i++; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,<?php echo $row['role']=='admin'?'#F59E0B,#D97706':'#22C55E,#16A34A'; ?>);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.85rem;flex-shrink:0">
                                            <?php echo strtoupper(substr($row['name'],0,1)); ?>
                                        </div>
                                        <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                    </div>
                                </td>
                                <td style="color:var(--text-secondary)"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <?php if($row['role']=='admin'): ?>
                                    <span class="badge warning"><i class="fas fa-crown"></i> Admin</span>
                                    <?php else: ?>
                                    <span class="badge success"><i class="fas fa-chalkboard-teacher"></i> Trainer</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $status = $row['status'] ?? 'active'; ?>
                                    <span class="badge <?php echo $status=='active'?'success':'danger'; ?>">
                                        <i class="fas fa-circle" style="font-size:0.5rem"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn-edit"><i class="fas fa-pen"></i> Edit</a>
                                        <a href="?toggle_status=1&id=<?php echo $row['id']; ?>" class="btn btn-sm" style="background:var(--bg);border:1px solid var(--border);color:var(--text-secondary)" onclick="return confirm('Toggle status?')"><i class="fas fa-toggle-on"></i></a>
                                        <a href="delete_user.php?id=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Delete this user?')"><i class="fas fa-trash"></i> Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">No trainers or admins found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- STUDENTS TABLE -->
            <div class="section-wrapper">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fas fa-user-graduate" style="color:var(--primary)"></i> Students
                </h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if($students && $students->num_rows > 0): ?>
                            <?php $i=1; while($row = $students->fetch_assoc()): ?>
                            <tr>
                                <td style="color:var(--text-muted)"><?php echo $i++; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.85rem;flex-shrink:0">
                                            <?php echo strtoupper(substr($row['name'],0,1)); ?>
                                        </div>
                                        <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                    </div>
                                </td>
                                <td style="color:var(--text-secondary)"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><span class="badge primary"><i class="fas fa-user-graduate"></i> Student</span></td>
                                <td>
                                    <?php $status = $row['status'] ?? 'active'; ?>
                                    <span class="badge <?php echo $status=='active'?'success':'danger'; ?>">
                                        <i class="fas fa-circle" style="font-size:0.5rem"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn-edit"><i class="fas fa-pen"></i> Edit</a>
                                        <a href="?toggle_status=1&id=<?php echo $row['id']; ?>" class="btn btn-sm" style="background:var(--bg);border:1px solid var(--border);color:var(--text-secondary)" onclick="return confirm('Toggle status?')"><i class="fas fa-toggle-on"></i></a>
                                        <a href="delete_user.php?id=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Delete this user?')"><i class="fas fa-trash"></i> Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted)">No students found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
