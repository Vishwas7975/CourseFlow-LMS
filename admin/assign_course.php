<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
if(!isset($_SESSION['user_id']) || $_SESSION['role']!="admin"){
    header("Location: ../login.php"); exit();
}

// Only fetch students who registered via internship and payment approved by admin
$internship_emails = [];
$ie_res = @$conn->query("SELECT ir.email FROM internship_payments ip JOIN internship_registrations ir ON ip.reg_id=ir.id WHERE ip.payment_status='approved'");
if($ie_res) { while($ie=$ie_res->fetch_assoc()) $internship_emails[] = "'".$conn->real_escape_string($ie['email'])."'"; }

if(!empty($internship_emails)){
    $email_in = implode(',', $internship_emails);
    $students_res = $conn->query("
        SELECT u.id, u.name, u.email, u.college, u.internship_course,
               GROUP_CONCAT(e.course_id) AS enrolled_course_ids
        FROM users u
        LEFT JOIN enrollments e ON e.user_id = u.id
        WHERE u.role='student'
          AND u.email IN ($email_in)
          AND u.internship_course IS NOT NULL
          AND u.internship_course != ''
        GROUP BY u.id
        ORDER BY u.name ASC
    ");
} else {
    $students_res = null;
}

$trainers = $conn->query("SELECT id,name FROM users WHERE role='trainer' ORDER BY name ASC");
$courses  = $conn->query("SELECT id,title FROM courses ORDER BY title ASC");

$msg = ""; $msg_type = "";

if(isset($_POST['bulk_assign'])){
    $course_id  = (int)$_POST['bulk_course_id'];
    $trainer_id = (int)$_POST['bulk_trainer_id'];
    $user_ids   = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
    if(empty($user_ids) || !$course_id || !$trainer_id){
        $msg = "Please select at least one student, a course, and a trainer.";
        $msg_type = "alert-warning";
    } else {
        $assigned_count = 0; $updated_count = 0;
        $stmt_check  = $conn->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=?");
        $stmt_update = $conn->prepare("UPDATE enrollments SET trainer_id=? WHERE user_id=? AND course_id=?");
        $stmt_insert = $conn->prepare("INSERT INTO enrollments(user_id,course_id,trainer_id) VALUES(?,?,?)");
        foreach($user_ids as $uid){
            $uid = (int)$uid;
            $stmt_check->bind_param("ii", $uid, $course_id);
            $stmt_check->execute();
            if($stmt_check->get_result()->num_rows > 0){
                // Already enrolled — just update the trainer
                $stmt_update->bind_param("iii", $trainer_id, $uid, $course_id);
                $stmt_update->execute();
                $updated_count++;
            } else {
                // Not enrolled — create new enrollment with trainer
                $stmt_insert->bind_param("iii", $uid, $course_id, $trainer_id);
                $stmt_insert->execute();
                $assigned_count++;
            }
        }
        $msg = "Done! <strong>$assigned_count</strong> student(s) newly enrolled.";
        if($updated_count > 0) $msg .= " <strong>$updated_count</strong> student(s) trainer updated successfully.";
        $msg_type = "alert-success";
    }
}

$assigned = $conn->query("
    SELECT e.id, u.name AS student_name, c.title, t.name AS trainer_name
    FROM enrollments e JOIN users u ON e.user_id=u.id JOIN courses c ON e.course_id=c.id
    LEFT JOIN users t ON e.trainer_id=t.id ORDER BY e.id DESC
");

$page_title = "Assign Courses";
$students_arr = [];
if($students_res){ while($s=$students_res->fetch_assoc()) $students_arr[]=$s; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Assign Courses - CourseFlow LMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
/* controls row */
.bulk-controls{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
.search-wrap{position:relative;flex:1;min-width:160px}
.search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.8rem;pointer-events:none}
.search-input{width:100%;box-sizing:border-box;padding:9px 14px 9px 36px;border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:0.875rem;font-family:'DM Sans',sans-serif;background:var(--card);color:var(--text-primary);outline:none;transition:border .2s}
.search-input:focus{border-color:var(--primary)}
.filter-select{padding:9px 14px;border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:0.875rem;font-family:'DM Sans',sans-serif;background:var(--card);color:var(--text-primary);outline:none;cursor:pointer;min-width:150px;flex-shrink:0}
.filter-select:focus{border-color:var(--primary)}
.ctrl-btn{padding:9px 14px;border-radius:var(--radius-md);font-size:0.82rem;font-weight:700;cursor:pointer;white-space:nowrap;font-family:'DM Sans',sans-serif;border:1.5px solid}
.ctrl-btn-primary{background:var(--primary-light);color:var(--primary);border-color:var(--primary)}
.ctrl-btn-ghost{background:var(--card);color:var(--text-secondary);border-color:var(--border)}
.sel-badge{display:inline-flex;align-items:center;gap:5px;background:var(--primary-light);color:var(--primary);border-radius:var(--radius-full);padding:4px 12px;font-size:0.8rem;font-weight:700;white-space:nowrap}

/* student table */
.bulk-table-wrap{max-height:360px;overflow-y:auto;overflow-x:auto;border:1.5px solid var(--border);border-radius:var(--radius-md);background:var(--card)}
.bulk-table{width:100%;min-width:520px;border-collapse:collapse}
.bulk-table th{padding:10px 14px;font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;background:var(--bg-alt);position:sticky;top:0;z-index:1;border-bottom:1.5px solid var(--border)}
.bulk-table td{padding:10px 14px;font-size:0.875rem;border-bottom:1px solid var(--border-light);vertical-align:middle}
.bulk-table tr:last-child td{border-bottom:none}
.bulk-table tr:hover td{background:var(--primary-light)}
.bulk-table tr.row-checked td{background:#EBF5FF}
.chk-cell{width:40px;text-align:center}
input[type=checkbox]{width:16px;height:16px;accent-color:var(--primary);cursor:pointer}
.no-results{text-align:center;padding:28px;color:var(--text-muted);font-size:0.875rem}

/* step layout */
.bulk-selects{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
.step-label{font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:12px;display:flex;align-items:center;gap:8px}
.step-num{background:var(--primary);color:#fff;border-radius:50%;width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:800;flex-shrink:0}

/* assigned table — horizontal scroll on small screens */
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.table-wrap table{min-width:480px}

/* submit row */
.submit-row{margin-top:20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.submit-row p{color:var(--text-muted);font-size:0.82rem;margin:0}

/* hide less-important columns on mobile */
@media(max-width:600px){
    .bulk-selects{grid-template-columns:1fr}
    .search-wrap{min-width:100%;flex:none}
    .filter-select{min-width:100%;width:100%}
    .ctrl-btn{flex:1}
    .col-email,.col-college{display:none}
    .bulk-table{min-width:300px}
}
</style>
</head>
<body>
<div class="dashboard">
<?php include("../includes/sidebar.php"); ?>
<div class="main">
<?php include("../includes/topbar.php"); ?>
<div class="content-wrapper">

<!-- Page Header -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px">
    <div>
        <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-primary);display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <i class="fas fa-tasks" style="color:var(--primary)"></i> Assign Courses
        </h1>
        <p style="color:var(--text-secondary);font-size:0.875rem">Assign courses to students and link them with trainers.</p>
    </div>
    <a href="dashboard.php" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius-md);font-size:0.875rem;font-weight:600;color:var(--text-primary);text-decoration:none;transition:all var(--transition)" onmouseover="this.style.background='var(--bg-alt)'" onmouseout="this.style.background='var(--card)'">
        <i class="fas fa-arrow-left" style="font-size:0.8rem"></i> Back to Dashboard
    </a>
</div>

<?php if($msg): ?>
<div class="alert <?php echo $msg_type; ?>" style="margin-bottom:20px">
    <i class="fas fa-<?php echo $msg_type=='alert-success'?'check-circle':'exclamation-triangle'; ?>"></i>
    <?php echo $msg; ?>
</div>
<?php endif; ?>

<!-- ASSIGN FORM -->
<div style="background:var(--card);border-radius:var(--radius-xl);padding:28px;box-shadow:var(--shadow-sm);border:1px solid var(--border-light);margin-bottom:28px">
    <h2 style="font-size:1.05rem;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:8px">
        <i class="fas fa-bolt" style="color:var(--primary)"></i> Assign Course to Students
    </h2>
    <p style="color:var(--text-secondary);font-size:0.85rem;margin-bottom:22px">Select a course &amp; trainer, then pick students and assign in one click.</p>

    <form method="POST" style="background:none!important;padding:0!important;box-shadow:none!important;border:none!important;margin:0!important">

        <!-- Step 1 -->
        <div style="background:var(--bg-alt);border-radius:var(--radius-md);padding:18px;margin-bottom:18px;border:1px solid var(--border-light)">
            <p class="step-label"><span class="step-num">1</span> Choose Course &amp; Trainer</p>
            <div class="bulk-selects">
                <div class="form-group" style="margin:0">
                    <label><i class="fas fa-book"></i> Course</label>
                    <select name="bulk_course_id" id="bulk_course_id" required onchange="filterByCourse(this.value)">
                        <option value="">-- Select Course --</option>
                        <?php $courses->data_seek(0); while($c=$courses->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label><i class="fas fa-chalkboard-teacher"></i> Trainer</label>
                    <select name="bulk_trainer_id" id="bulk_trainer_id" required>
                        <option value="">-- Select Trainer --</option>
                        <?php $trainers->data_seek(0); while($t=$trainers->fetch_assoc()): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Step 2 -->
        <div style="background:var(--bg-alt);border-radius:var(--radius-md);padding:18px;border:1px solid var(--border-light)">
            <p class="step-label"><span class="step-num">2</span> Select Students</p>
            <div class="bulk-controls">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="studentSearch" class="search-input" placeholder="Search by name, email or college…" oninput="filterStudents()">
                </div>
                <select id="courseFilter" class="filter-select" onchange="filterStudents()">
                    <option value="">All Courses</option>
                    <?php $courses->data_seek(0); while($cf=$courses->fetch_assoc()): ?>
                    <option value="<?php echo $cf['id']; ?>"><?php echo htmlspecialchars($cf['title']); ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="button" onclick="selectAll()" class="ctrl-btn ctrl-btn-primary">
                    <i class="fas fa-check-double"></i> Select All
                </button>
                <button type="button" onclick="clearAll()" class="ctrl-btn ctrl-btn-ghost">
                    <i class="fas fa-times"></i> Clear
                </button>
                <span class="sel-badge"><i class="fas fa-user-check"></i> <span id="selCount">0</span> selected</span>
            </div>

            <div class="bulk-table-wrap">
                <table class="bulk-table">
                    <thead>
                        <tr>
                            <th class="chk-cell"><input type="checkbox" id="masterChk" onchange="toggleMaster(this)" title="Select/deselect all visible"></th>
                            <th>Student Name</th>
                            <th class="col-email">Email</th>
                            <th class="col-college">College</th>
                            <th>Registered Course</th>
                        </tr>
                    </thead>
                    <tbody id="studentBody">
                    <?php foreach($students_arr as $s): ?>
                    <tr data-name="<?php echo strtolower(htmlspecialchars($s['name'])); ?>"
                        data-email="<?php echo strtolower(htmlspecialchars($s['email'] ?? '')); ?>"
                        data-college="<?php echo strtolower(htmlspecialchars($s['college'] ?? '')); ?>"
                        data-courseids="<?php echo htmlspecialchars($s['enrolled_course_ids'] ?? ''); ?>">
                        <td class="chk-cell">
                            <input type="checkbox" name="user_ids[]" value="<?php echo $s['id']; ?>" class="student-chk" onchange="updateCount()">
                        </td>
                        <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                        <td class="col-email" style="color:var(--text-secondary);font-size:0.82rem"><?php echo htmlspecialchars($s['email'] ?? '—'); ?></td>
                        <td class="col-college" style="color:var(--text-secondary);font-size:0.82rem"><?php echo htmlspecialchars($s['college'] ?? '—'); ?></td>
                        <td style="font-size:0.82rem">
                            <?php if(!empty($s['internship_course'])): ?>
                            <span style="display:inline-block;padding:2px 10px;background:var(--primary-light);color:var(--primary);border-radius:var(--radius-full);font-size:0.75rem;font-weight:600"><?php echo htmlspecialchars($s['internship_course']); ?></span>
                            <?php else: ?>
                            <span style="color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($students_arr)): ?>
                    <tr><td colspan="5" class="no-results">No approved students found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p style="color:var(--text-muted);font-size:0.78rem;margin-top:8px"><i class="fas fa-info-circle"></i> Showing all approved students. Select a course in Step 1 to filter by enrolled course.</p>
        </div>

        <!-- Submit -->
        <div class="submit-row">
            <button type="submit" name="bulk_assign" onclick="return confirmBulk()"
                style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;border:none;border-radius:var(--radius-md);font-size:0.95rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:var(--shadow-blue)">
                <i class="fas fa-bolt"></i> Assign to <span id="assignBtnCount">0</span> Student(s)
            </button>
            <p><i class="fas fa-info-circle"></i> Already-enrolled students will have their trainer updated.</p>
        </div>
    </form>
</div>

<!-- ASSIGNED TABLE -->
<h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px">
    <i class="fas fa-list" style="color:var(--primary)"></i> Assigned Courses
</h2>
<div class="table-wrap">
    <table>
        <thead><tr><th>#</th><th>Student</th><th>Course</th><th>Trainer</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if($assigned && $assigned->num_rows > 0): while($row=$assigned->fetch_assoc()): ?>
        <tr>
            <td style="color:var(--text-muted)"><?php echo $row['id']; ?></td>
            <td><strong><?php echo htmlspecialchars($row['student_name']); ?></strong></td>
            <td><?php echo htmlspecialchars($row['title']); ?></td>
            <td><?php echo htmlspecialchars($row['trainer_name'] ?? 'N/A'); ?></td>
            <td><div style="display:flex;gap:6px">
                <a href="edit_assignment.php?id=<?php echo $row['id']; ?>" class="btn-edit"><i class="fas fa-pen"></i></a>
                <a href="delete_assignment.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this assignment?')" class="btn-delete"><i class="fas fa-trash"></i></a>
            </div></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--text-muted)">No assignments yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</div>
</div>
</div>
<?php include("../includes/footer.php"); ?>
<script>
// When admin picks a course in Step 1, filter students to those enrolled in that course
function filterByCourse(courseId){
    const rows = document.querySelectorAll('#studentBody tr[data-name]');
    let vis = 0;
    rows.forEach(row => {
        if(!courseId){
            row.style.display = '';
            vis++;
        } else {
            const ids = (row.dataset.courseids || '').split(',').map(x=>x.trim());
            const match = ids.includes(String(courseId));
            row.style.display = match ? '' : 'none';
            if(match) vis++;
        }
    });
    updateNoResults(vis);
    clearAll();
}

function filterStudents(){
    const q  = document.getElementById('studentSearch').value.toLowerCase().trim();
    const cf = document.getElementById('courseFilter').value; // course ID as string
    const rows = document.querySelectorAll('#studentBody tr[data-name]');
    let vis = 0;
    rows.forEach(row => {
        const matchQ = !q || row.dataset.name.includes(q) || row.dataset.email.includes(q) || row.dataset.college.includes(q);
        const ids    = (row.dataset.courseids || '').split(',').map(x => x.trim()).filter(Boolean);
        const matchC = !cf || ids.includes(cf);
        const match  = matchQ && matchC;
        row.style.display = match ? '' : 'none';
        if(match) vis++;
    });
    updateNoResults(vis);
    updateCount();
}

function updateNoResults(vis){
    let nr = document.getElementById('noResultsRow');
    if(vis === 0){
        if(!nr){ nr=document.createElement('tr'); nr.id='noResultsRow'; nr.innerHTML='<td colspan="5" class="no-results"><i class="fas fa-search" style="margin-right:6px"></i>No students match your filters</td>'; document.getElementById('studentBody').appendChild(nr); }
    } else { if(nr) nr.remove(); }
}

function selectAll(){
    document.querySelectorAll('#studentBody tr[data-name]').forEach(row => {
        if(row.style.display !== 'none'){ const c=row.querySelector('.student-chk'); c.checked=true; row.classList.add('row-checked'); }
    });
    updateCount();
}
function clearAll(){
    document.querySelectorAll('.student-chk').forEach(c=>{ c.checked=false; c.closest('tr').classList.remove('row-checked'); });
    document.getElementById('masterChk').checked=false;
    updateCount();
}
function toggleMaster(m){ if(m.checked) selectAll(); else clearAll(); }
function updateCount(){
    const n = document.querySelectorAll('.student-chk:checked').length;
    document.getElementById('selCount').textContent = n;
    document.getElementById('assignBtnCount').textContent = n;
    document.querySelectorAll('.student-chk').forEach(c=>c.closest('tr').classList.toggle('row-checked',c.checked));
}
function confirmBulk(){
    const n = document.querySelectorAll('.student-chk:checked').length;
    if(n===0){ alert('Please select at least one student.'); return false; }
    const course = document.getElementById('bulk_course_id');
    const trainer = document.getElementById('bulk_trainer_id');
    if(!course.value){ alert('Please select a course.'); return false; }
    if(!trainer.value){ alert('Please select a trainer.'); return false; }
    return confirm('Assign "'+course.options[course.selectedIndex].text+'" to '+n+' student(s)?');
}
</script>
</body>
</html>