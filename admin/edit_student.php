<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

$id = intval($_GET['id']);

/* ---------------- UPDATE STUDENT ---------------- */

if($_SERVER['REQUEST_METHOD']=="POST"){

$name        = trim($_POST['name']        ?? '');
$email       = trim($_POST['email']       ?? '');
$mobile      = trim($_POST['mobile']      ?? '');
$college     = trim($_POST['college']     ?? '');
$dept        = trim($_POST['dept']        ?? '');
$passout     = trim($_POST['passout_year']?? '');
$hall        = trim($_POST['hall_ticket'] ?? '');
$certificate = trim($_POST['certificate_number'] ?? '');
$start       = trim($_POST['start_date']  ?? '');
$end         = trim($_POST['end_date']    ?? '');
$link        = trim($_POST['download_link']       ?? '');
$verify      = trim($_POST['verification_symbol'] ?? 'Verified');

$courses = $_POST['courses'] ?? [];
if(!is_array($courses)){ $courses = []; }

/* UPDATE USERS TABLE — prepared statement (fixes SQL injection) */
$stmt_upd = $conn->prepare("
UPDATE users SET
name=?,
email=?,
mobile=?,
college=?,
dept=?,
passout_year=?,
hall_ticket=?,
certificate_number=?,
start_date=?,
end_date=?,
download_link=?,
verification_symbol=?
WHERE id=?
");
$stmt_upd->bind_param("ssssssssssssi",
    $name, $email, $mobile, $college, $dept,
    $passout, $hall, $certificate,
    $start, $end, $link, $verify,
    $id
);
$stmt_upd->execute();
$stmt_upd->close();

/* DELETE OLD ENROLLMENTS */
$stmt_del = $conn->prepare("DELETE FROM enrollments WHERE user_id=?");
$stmt_del->bind_param("i", $id);
$stmt_del->execute();
$stmt_del->close();

/* INSERT NEW ENROLLMENTS — prepared statements */
$stmt_trainer = $conn->prepare("SELECT trainer_id FROM courses WHERE id=?");
$stmt_enroll  = $conn->prepare("
    INSERT INTO enrollments (user_id,course_id,trainer_id,status,progress)
    VALUES (?,?,?,'approved','0')
");

foreach($courses as $course_id){

    $course_id = intval($course_id);

    $stmt_trainer->bind_param("i", $course_id);
    $stmt_trainer->execute();
    $tr = $stmt_trainer->get_result()->fetch_assoc();
    $trainer_id = isset($tr['trainer_id']) ? (int)$tr['trainer_id'] : 0;

    $stmt_enroll->bind_param("iii", $id, $course_id, $trainer_id);
    $stmt_enroll->execute();
}

$stmt_trainer->close();
$stmt_enroll->close();

$_SESSION['toast'] = "Student updated successfully";

header("Location: student_data.php");
exit;
}

/* ---------------- GET STUDENT DATA ---------------- */

$stmt_s = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt_s->bind_param("i", $id);
$stmt_s->execute();
$student = $stmt_s->get_result();
$row = mysqli_fetch_assoc($student);

/* ---------------- GET ENROLLED COURSES ---------------- */

$enrolled = [];

$stmt_e = $conn->prepare("SELECT course_id FROM enrollments WHERE user_id=?");
$stmt_e->bind_param("i", $id);
$stmt_e->execute();
$e = $stmt_e->get_result();

while($r = mysqli_fetch_assoc($e)){
$enrolled[] = $r['course_id'];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Student</title>

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">

<style>

.course-box{
border:1px solid #ddd;
padding:15px;
border-radius:8px;
background:#fafafa;
max-height:200px;
overflow-y:auto;
}

.course-item{
margin-bottom:8px;
}

</style>

</head>

<body>

<div class="dashboard">

<?php include("../includes/sidebar.php"); ?>

<div class="main">

<?php include("../includes/topbar.php"); ?>

<div class="content-wrapper">

<div class="page-header">
<h1><i class="fas fa-user-edit"></i> Edit Student</h1>
<p>Update student profile & enrollments</p>
</div>

<div class="card">

<div class="card-body">

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">
<label>Certificate Number</label>
<input type="text" name="certificate_number" class="form-control"
value="<?php echo htmlspecialchars($row['certificate_number']); ?>">
</div>

<div class="col-md-6 mb-3">
<label>Student Name</label>
<input type="text" name="name" class="form-control"
value="<?php echo htmlspecialchars($row['name']); ?>">
</div>

<div class="col-md-6 mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control"
value="<?php echo htmlspecialchars($row['email']); ?>">
</div>

<div class="col-md-6 mb-3">
<label>Mobile</label>
<input type="text" name="mobile" class="form-control"
value="<?php echo htmlspecialchars($row['mobile']); ?>">
</div>

<div class="col-md-6 mb-3">
<label>College</label>
<input type="text" name="college" class="form-control"
value="<?php echo htmlspecialchars($row['college']); ?>">
</div>

<div class="col-md-6 mb-3">
<label>Department</label>
<input type="text" name="dept" class="form-control"
value="<?php echo htmlspecialchars($row['dept']); ?>">
</div>

<div class="col-md-6 mb-3">
<label>Passout Year</label>
<input type="text" name="passout_year" class="form-control"
value="<?php echo htmlspecialchars($row['passout_year']); ?>">
</div>

<div class="col-md-6 mb-3">
<label>Hall Ticket</label>
<input type="text" name="hall_ticket" class="form-control"
value="<?php echo htmlspecialchars($row['hall_ticket']); ?>">
</div>

<div class="col-md-6 mb-3">
<label>Start Date</label>
<input type="date" name="start_date" class="form-control"
value="<?php echo htmlspecialchars($row['start_date']); ?>">
</div>

<div class="col-md-6 mb-3">
<label>End Date</label>
<input type="date" name="end_date" class="form-control"
value="<?php echo htmlspecialchars($row['end_date']); ?>">
</div>

<div class="col-md-6 mb-3">
<label>Certificate Download Link</label>
<input type="text" name="download_link" class="form-control"
value="<?php echo htmlspecialchars($row['download_link']); ?>">
</div>

<div class="col-md-6 mb-3">

<label>Verification</label>

<select name="verification_symbol" class="form-control">

<option value="Verified"
<?php if($row['verification_symbol']=="Verified") echo "selected"; ?>>
Verified
</option>

<option value="Not Verified"
<?php if($row['verification_symbol']=="Not Verified") echo "selected"; ?>>
Not Verified
</option>

</select>

</div>

</div>

<!-- COURSES -->

<h5 class="mt-4">Enroll Courses</h5>

<div class="course-box">

<?php

$all_courses = mysqli_query($conn,"SELECT * FROM courses");

while($course = mysqli_fetch_assoc($all_courses)){

$checked = in_array($course['id'],$enrolled) ? "checked" : "";

?>

<div class="course-item">

<label>

<input type="checkbox"
name="courses[]"
value="<?php echo (int)$course['id']; ?>"
<?php echo $checked; ?>>

<?php echo htmlspecialchars($course['title']); ?>

</label>

</div>

<?php } ?>

</div>

<button class="btn btn-primary mt-3">
<i class="fas fa-save"></i> Update Student
</button>

<a href="student_data.php" class="btn btn-secondary mt-3">
Cancel
</a>

</form>

</div>

</div>

</div>

</div>

</div>

</div>

</body>
</html>