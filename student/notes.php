<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "student"){
    header("Location: ../login.php"); exit();
}
$user_id = $_SESSION['user_id'];

$notes = $conn->query("
    SELECT n.*, c.title as course_title, u.name as uploaded_by, u.role as uploader_role
    FROM notes n
    JOIN courses c ON n.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id AND e.user_id = $user_id
    JOIN users u ON n.uploaded_by = u.id
    WHERE IFNULL(e.status,'approved') = 'approved'
    ORDER BY n.created_at DESC
");

$page_title = "Notes";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - CourseFlow LMS</title>
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

            <!-- Page Header with Back Button -->
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px">
                <div>
                    <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-primary);display:flex;align-items:center;gap:10px;margin-bottom:4px">
                        <i class="fas fa-sticky-note" style="color:var(--primary)"></i> Notes & Study Materials
                    </h1>
                    <p style="color:var(--text-secondary);font-size:0.875rem">Download notes and materials uploaded by your trainers and admin.</p>
                </div>
                <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:0.875rem;font-weight:600;color:var(--text-primary);text-decoration:none;transition:all var(--transition)" onmouseover="this.style.background='var(--bg-alt)'" onmouseout="this.style.background='var(--card)'">
                    <i class="fas fa-arrow-left" style="font-size:0.8rem"></i> Back to Dashboard
                </a>
            </div>

            <?php if($notes && $notes->num_rows > 0): ?>
            <div style="display:flex;flex-direction:column;gap:12px">
                <?php while($row = $notes->fetch_assoc()):
                    $ext = strtolower(pathinfo($row['file_name'] ?? '', PATHINFO_EXTENSION));
                    $icon = 'fas fa-file'; $icon_color = 'var(--text-muted)';
                    if($ext=='pdf'){ $icon='fas fa-file-pdf'; $icon_color='#EF4444'; }
                    elseif(in_array($ext,['doc','docx'])){ $icon='fas fa-file-word'; $icon_color='#2D7DD2'; }
                    elseif(in_array($ext,['ppt','pptx'])){ $icon='fas fa-file-powerpoint'; $icon_color='#D97706'; }
                    elseif(in_array($ext,['xls','xlsx'])){ $icon='fas fa-file-excel'; $icon_color='#16A34A'; }
                    elseif(in_array($ext,['zip','rar'])){ $icon='fas fa-file-archive'; $icon_color='#F59E0B'; }
                    elseif(in_array($ext,['jpg','jpeg','png','gif'])){ $icon='fas fa-file-image'; $icon_color='#06B6D4'; }
                ?>
                <div style="background:var(--card);border-radius:var(--radius-lg);padding:18px 22px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light);display:flex;align-items:center;gap:16px;transition:box-shadow var(--transition)" onmouseover="this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.boxShadow='var(--shadow-sm)'">
                    <div style="width:48px;height:48px;border-radius:var(--radius-md);background:var(--bg-alt);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="<?php echo $icon; ?>" style="font-size:1.5rem;color:<?php echo $icon_color; ?>"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;color:var(--text-primary);font-size:0.95rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            <?php echo htmlspecialchars($row['title'] ?? $row['file_name'] ?? 'Note'); ?>
                        </div>
                        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                            <span><i class="fas fa-book" style="color:var(--primary)"></i> <?php echo htmlspecialchars($row['course_title']); ?></span>
                            <span><i class="fas fa-user" style="color:var(--primary)"></i> <?php echo htmlspecialchars($row['uploaded_by']); ?> (<?php echo ucfirst($row['uploader_role']); ?>)</span>
                            <?php if(!empty($row['created_at'])): ?>
                            <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if(!empty($row['description'])): ?>
                        <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px"><?php echo htmlspecialchars($row['description']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="flex-shrink:0">
                        <?php if(!empty($row['file_path'])): ?>
                        <a href="<?php echo htmlspecialchars($row['file_path']); ?>" download class="btn btn-primary btn-sm">
                            <i class="fas fa-download"></i> Download
                        </a>
                        <?php else: ?>
                        <span style="font-size:0.8rem;color:var(--text-muted)"><i class="fas fa-ban"></i> No file</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-sticky-note" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px;display:block"></i>
                <strong>No notes available yet.</strong><br>
                <span style="color:var(--text-muted);font-size:0.875rem">Your trainers and admin will upload study materials here.</span>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<style>
/* ── Mobile: note card is flex row (file icon + info + Download button).
   On phones the download button overflows — wrap it below the info. */
@media(max-width:560px){
    /* Allow note card to wrap */
    div[style*="display:flex;align-items:center;gap:16px"] { flex-wrap:wrap; }

    /* File icon: keep its fixed size, don't shrink */
    div[style*="width:48px;height:48px"] { flex-shrink:0; }

    /* Info block: take full width when wrapped */
    div[style*="flex:1;min-width:0"] { width:100%; }

    /* Note title: allow wrapping on phones (was nowrap) */
    div[style*="white-space:nowrap"] { white-space:normal !important; }

    /* Meta row (date, size): wrap on very small screens */
    div[style*="display:flex;align-items:center;gap:14px"] {
        flex-wrap:wrap; gap:6px !important;
    }

    /* Download button: full width, touch-friendly */
    div[style*="flex-shrink:0"] .btn {
        width:100%; justify-content:center; min-height:44px;
    }

    /* Page header: stack title and back button */
    div[style*="justify-content:space-between;flex-wrap:wrap"] {
        flex-direction:column;
    }
    div[style*="justify-content:space-between;flex-wrap:wrap"] > a {
        min-height:44px; width:100%; justify-content:center;
    }
}
</style>
<?php include("../includes/footer.php"); ?>