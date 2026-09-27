<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

$page_title = "Student Data";

$limit = 20;
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$start = ($page - 1) * $limit;

/* ── Sanitize filter inputs ── */
$search  = trim($_GET['search']  ?? '');
$college = trim($_GET['college'] ?? '');
$course  = trim($_GET['course']  ?? '');

/* ── Build WHERE clause + params for prepared statement ── */
$where_sql  = "WHERE users.role = 'student'";
$params     = [];
$types      = '';

if ($search !== '') {
    $where_sql .= " AND users.name LIKE ?";
    $params[]   = '%' . $search . '%';
    $types     .= 's';
}

if ($college !== '') {
    $where_sql .= " AND users.college = ?";
    $params[]   = $college;
    $types     .= 's';
}

if ($course !== '') {
    $where_sql .= " AND users.internship_course = ?";
    $params[]   = $course;
    $types     .= 's';
}

/* ── Main data query ── */
$sql = "
SELECT
    users.*,
    GROUP_CONCAT(courses.title SEPARATOR ', ') AS enrolled_courses
FROM users
LEFT JOIN enrollments ON enrollments.user_id = users.id
LEFT JOIN courses     ON courses.id = enrollments.course_id
$where_sql
GROUP BY users.id
LIMIT ?, ?
";

$all_params = array_merge($params, [$start, $limit]);
$all_types  = $types . 'ii';

$stmt = $conn->prepare($sql);
if (!empty($all_params)) {
    $stmt->bind_param($all_types, ...$all_params);
}
$stmt->execute();
$result = $stmt->get_result();

/* ── Count query for pagination ── */
$count_sql  = "SELECT COUNT(*) as total FROM users $where_sql";
$stmt_count = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_row   = $stmt_count->get_result()->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);
?>

<?php if(isset($_SESSION['toast'])): ?>
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999">
<div class="toast show align-items-center text-bg-success border-0">
<div class="d-flex">
<div class="toast-body"><?php echo htmlspecialchars($_SESSION['toast']); ?></div>
<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
</div>
</div>
</div>
<?php unset($_SESSION['toast']); endif; ?>


<div class="modal fade" id="bulkUploadModal">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Bulk Upload Students</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form method="POST" action="process_excel.php" enctype="multipart/form-data">
<div class="modal-body">
<div class="mb-3">
<label>Select CSV / Excel File</label>
<input type="file" name="excel_file" class="form-control" required>
</div>
<p style="font-size:13px;color:gray">Upload CSV file with student details.</p>
</div>
<div class="modal-footer">
<button class="btn btn-primary">Upload</button>
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
</div>
</form>
</div>
</div>
</div>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Data - CourseFlow LMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
</head>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>

<body>

<div class="dashboard">

<?php include("../includes/sidebar.php"); ?>

<div class="main">

<?php include("../includes/topbar.php"); ?>

<div class="content-wrapper">

<div class="page-header">
<h1><i class="fas fa-user-graduate"></i> Student Data</h1>
<p>Manage student certificates</p>
</div>

<!-- FILTERS -->
<form method="GET">
<div class="row mb-3">

<div class="col-md-3">
<input type="text" name="search" class="form-control" placeholder="Search Student"
    value="<?php echo htmlspecialchars($search); ?>">
</div>

<div class="col-md-3">
<input type="text" name="college" class="form-control" placeholder="Filter by College"
    value="<?php echo htmlspecialchars($college); ?>">
</div>

<div class="col-md-3">
<input type="text" name="course" class="form-control" placeholder="Filter by Course"
    value="<?php echo htmlspecialchars($course); ?>">
</div>

<div class="col-md-3">
<button class="btn btn-primary">Apply</button>
<a href="student_data.php" class="btn btn-secondary">Clear</a>
</div>

</div>
</form>

<!-- ACTION BUTTONS -->
<div class="mb-3">
<a href="add_student.php" class="btn btn-success">
<i class="fas fa-user-plus"></i> Add Student
</a>

<a href="export_students.php?search=<?php echo urlencode($search); ?>&college=<?php echo urlencode($college); ?>&course=<?php echo urlencode($course); ?>"
   class="btn btn-warning">
<i class="fas fa-download"></i> Export Excel
</a>
</div>

<!-- TABLE -->
<div class="card">
<div class="card-body">
<div class="table-responsive">
<table class="table table-bordered table-hover">
<thead class="table-primary">
<tr>
<th>#</th>
<th>Certificate</th>
<th>Name</th>
<th>Email</th>
<th>Mobile</th>
<th>College</th>
<th>Course</th>
<th>Start</th>
<th>End</th>
<th>Download</th>
<th>Verify</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php
$count = $start + 1;
while($row = $result->fetch_assoc()):
?>
<tr>
<td><?php echo $count++; ?></td>
<td><?php echo htmlspecialchars($row['certificate_number']); ?></td>
<td><?php echo htmlspecialchars($row['name']); ?></td>
<td><?php echo htmlspecialchars($row['email']); ?></td>
<td><?php echo htmlspecialchars($row['mobile']); ?></td>
<td><?php echo htmlspecialchars($row['college']); ?></td>
<td><?php echo htmlspecialchars($row['enrolled_courses'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['start_date']); ?></td>
<td><?php echo htmlspecialchars($row['end_date']); ?></td>
<td>
<a href="<?php echo htmlspecialchars($row['download_link']); ?>" target="_blank">Download</a>
</td>
<td>
<a href="verify.php?cert=<?php echo urlencode($row['certificate_number']); ?>" class="btn btn-success btn-sm">Verify</a>
</td>
<td>
<div class="d-flex gap-2">
<a href="edit_student.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-primary btn-sm">
<i class="fas fa-edit"></i>
</a>
<a href="delete_student.php?id=<?php echo (int)$row['id']; ?>"
   class="btn btn-danger btn-sm"
   onclick="return confirm('Are you sure you want to delete this student?')">
<i class="fas fa-trash"></i>
</a>
</div>
</td>
</tr>
<?php endwhile; ?>

</tbody>
</table>
</div>
</div>
</div>

<!-- PAGINATION -->
<div class="mt-3">
<?php for($i = 1; $i <= $total_pages; $i++): ?>
<a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&college=<?php echo urlencode($college); ?>&course=<?php echo urlencode($course); ?>"
   class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-outline-primary'; ?> me-1">
    <?php echo $i; ?>
</a>
<?php endfor; ?>
</div>

</div>
</div>
</div>
</div>

</body>
</html>