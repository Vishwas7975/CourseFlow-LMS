<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php"); exit();
}

// Ensure status column exists
$conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected') DEFAULT 'approved'");
// Also ensure created_at exists
$conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

$msg = "";

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $enrollment_id = intval($_POST['enrollment_id']);
    $action = $_POST['action'];

    if($action == 'approve'){
        $conn->query("UPDATE enrollments SET status='approved' WHERE id=$enrollment_id");
        $res = $conn->query("SELECT u.name, u.email FROM enrollments e JOIN users u ON e.user_id=u.id WHERE e.id=$enrollment_id");
        $student = $res ? $res->fetch_assoc() : null;
        $sname = $student ? htmlspecialchars($student['name']) : 'Student';
        $semail = $student ? htmlspecialchars($student['email']) : '';
        $msg = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Enrollment approved! Student <strong>$sname</strong> ($semail) now has access.</div>";
    } elseif($action == 'reject'){
        $conn->query("UPDATE enrollments SET status='rejected' WHERE id=$enrollment_id");
        $msg = "<div class='alert alert-error'><i class='fas fa-times-circle'></i> Enrollment request rejected.</div>";
    }
}

$pending  = $conn->query("SELECT e.id, u.name, u.email, c.title as course_title, e.created_at FROM enrollments e JOIN users u ON e.user_id=u.id JOIN courses c ON e.course_id=c.id WHERE e.status='pending' ORDER BY e.created_at DESC");
$approved = $conn->query("SELECT e.id, u.name, u.email, c.title as course_title, e.created_at FROM enrollments e JOIN users u ON e.user_id=u.id JOIN courses c ON e.course_id=c.id WHERE e.status='approved' ORDER BY e.created_at DESC LIMIT 20");

$page_title = "Enrollment Requests";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Requests - CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,37,64,0.6);z-index:2000;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
    .modal-overlay.active{display:flex}
    .modal-box{background:#fff;border-radius:var(--radius-xl);padding:32px;max-width:460px;width:90%;box-shadow:var(--shadow-lg);animation:slideIn 0.25s ease}
    @keyframes slideIn{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
    .modal-actions{display:flex;gap:10px;margin-top:20px;justify-content:flex-end}
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
                <div>
                    <h1 style="font-size:1.5rem;font-weight:800;display:flex;align-items:center;gap:10px">
                        <i class="fas fa-user-check" style="color:var(--primary)"></i> Enrollment Requests
                    </h1>
                    <p style="color:var(--text-secondary);font-size:0.875rem;margin-top:4px">Approve or reject student enrollment requests.</p>
                </div>
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>

            <?php echo $msg; ?>

            <!-- PENDING -->
            <div class="section-wrapper">
                <h2 style="margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fas fa-clock" style="color:var(--warning)"></i> Pending Requests
                    <?php if($pending && $pending->num_rows > 0): ?>
                    <span style="background:var(--warning);color:#fff;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:var(--radius-full)"><?php echo $pending->num_rows; ?></span>
                    <?php endif; ?>
                </h2>
                <?php if($pending && $pending->num_rows > 0): ?>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php while($row = $pending->fetch_assoc()): ?>
                    <div style="background:var(--card);border-radius:var(--radius-lg);padding:18px 22px;box-shadow:var(--shadow-sm);border:1px solid var(--warning-light);display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                        <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;flex-shrink:0">
                            <?php echo strtoupper(substr($row['name'],0,1)); ?>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:700;font-size:0.95rem;color:var(--text-primary)"><?php echo htmlspecialchars($row['name']); ?></div>
                            <div style="font-size:0.8rem;color:var(--text-muted)"><?php echo htmlspecialchars($row['email']); ?></div>
                            <div style="font-size:0.82rem;color:var(--text-secondary);margin-top:3px">
                                <i class="fas fa-book" style="color:var(--primary)"></i> <?php echo htmlspecialchars($row['course_title']); ?>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;flex-shrink:0">
                            <button class="btn btn-success btn-sm" onclick="openModal(<?php echo $row['id']; ?>,'<?php echo addslashes(htmlspecialchars($row['name'])); ?>','<?php echo addslashes(htmlspecialchars($row['email'])); ?>','<?php echo addslashes(htmlspecialchars($row['course_title'])); ?>','approve')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="openModal(<?php echo $row['id']; ?>,'<?php echo addslashes(htmlspecialchars($row['name'])); ?>','<?php echo addslashes(htmlspecialchars($row['email'])); ?>','<?php echo addslashes(htmlspecialchars($row['course_title'])); ?>','reject')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox" style="font-size:2rem;color:var(--text-muted);margin-bottom:10px;display:block"></i>
                    No pending enrollment requests.
                </div>
                <?php endif; ?>
            </div>

            <!-- APPROVED -->
            <div class="section-wrapper">
                <h2 style="margin-bottom:14px"><i class="fas fa-check-circle" style="color:var(--success)"></i> Recently Approved</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Student</th><th>Email</th><th>Course</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if($approved && $approved->num_rows > 0): while($row=$approved->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                            <td style="color:var(--text-muted)"><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['course_title']); ?></td>
                            <td><span class="badge success"><i class="fas fa-check"></i> Approved</span></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted)">No approved enrollments yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- CONFIRMATION MODAL -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <h3 id="modalTitle" style="font-size:1.1rem;margin-bottom:16px;display:flex;align-items:center;gap:8px"></h3>
        <p id="modalBody" style="color:var(--text-secondary);font-size:0.9rem;line-height:1.6"></p>
        <div id="credentialsSection" style="display:none;margin-top:16px;background:var(--bg-alt);border-radius:var(--radius-md);padding:14px;border:1px solid var(--border)">
            <p style="font-size:0.82rem;font-weight:700;color:var(--text-primary);margin-bottom:6px"><i class="fas fa-key" style="color:var(--warning)"></i> Credentials to share with student:</p>
            <p style="font-size:0.82rem;color:var(--text-secondary)" id="credEmail"></p>
            <p style="font-size:0.82rem;color:var(--text-secondary)">Password: <strong>Their registered password</strong></p>
        </div>
        <form method="POST" id="confirmForm">
            <input type="hidden" name="enrollment_id" id="modalEnrollmentId">
            <input type="hidden" name="action" id="modalAction">
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" onclick="closeModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn" id="modalConfirmBtn"></button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, name, email, course, action) {
    document.getElementById('modalEnrollmentId').value = id;
    document.getElementById('modalAction').value = action;
    const isApprove = action === 'approve';
    document.getElementById('modalTitle').innerHTML = isApprove
        ? '<i class="fas fa-check-circle" style="color:var(--success)"></i> Approve Enrollment'
        : '<i class="fas fa-times-circle" style="color:var(--danger)"></i> Reject Enrollment';
    document.getElementById('modalBody').innerHTML = isApprove
        ? `Approve enrollment for <strong>${name}</strong> in <strong>${course}</strong>?`
        : `Reject enrollment request from <strong>${name}</strong> for <strong>${course}</strong>?`;
    document.getElementById('credentialsSection').style.display = isApprove ? 'block' : 'none';
    if(isApprove) document.getElementById('credEmail').innerHTML = 'Email: <strong>' + email + '</strong>';
    const btn = document.getElementById('modalConfirmBtn');
    btn.className = 'btn ' + (isApprove ? 'btn-success' : 'btn-danger');
    btn.innerHTML = isApprove ? '<i class="fas fa-check"></i> Approve' : '<i class="fas fa-times"></i> Reject';
    document.getElementById('confirmModal').classList.add('active');
}
function closeModal() {
    document.getElementById('confirmModal').classList.remove('active');
}
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if(e.target === this) closeModal();
});
</script>
<?php include("../includes/footer.php"); ?>
