<?php
session_start();

include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

$page_title = "Add Student";

/* INSERT STUDENT */

if(isset($_POST['name'])){

$name=$_POST['name'];
$email=$_POST['email'];
$mobile=$_POST['mobile'];
$college=$_POST['college'];
$dept=$_POST['dept'];
$passout=$_POST['passout_year'];
$hall=$_POST['hall_ticket'];

$certificate=$_POST['certificate_number'];
$course=$_POST['internship_course'];

$start=$_POST['start_date'];
$end=$_POST['end_date'];

$link=$_POST['download_link'];
$verify=$_POST['verification_symbol'];

$password=password_hash("student123",PASSWORD_DEFAULT);

// Use prepared statement to prevent SQL injection
$stmt = $conn->prepare(
    "INSERT INTO users (name,email,password,role,mobile,college,passout_year,hall_ticket,
     certificate_number,internship_course,start_date,end_date,download_link,
     verification_symbol,dept,status)
     VALUES (?,?,'student',?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
// bind: name, email, password, mobile, college, passout, hall, cert, course, start, end, link, verify, dept, status(literal above)
$stmt->bind_param('sssssssssssssss',
    $name, $email, $password, $mobile, $college,
    $passout, $hall, $certificate, $course,
    $start, $end, $link, $verify, $dept
);

if($stmt->execute()){

echo "<script>
alert('Student Added Successfully');
window.location='student_data.php';
</script>";

}else{

echo "<script>alert('Database Error: " . addslashes($stmt->error) . "');</script>";

}

}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add Student</title>

<link rel="stylesheet" href="../assets/css/style.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link rel="icon" type="image/png" href="../assets/images/favicon.png">

</head>

<body>

<div class="dashboard">

<?php include("../includes/sidebar.php"); ?>

<div class="main">

<?php include("../includes/topbar.php"); ?>

<div class="content-wrapper">


<!-- HEADER -->

<div class="page-header d-flex justify-content-between align-items-center">

<div>
<h1><i class="fas fa-user-plus"></i> Add Student</h1>
<p>Create new student certificate record</p>
</div>

<div>

<a href="student_data.php" class="btn btn-secondary">
<i class="fas fa-arrow-left"></i> Back
</a>

<button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
<i class="fas fa-file-excel"></i> Bulk Upload
</button>

<a href="download_sample_excel.php" class="btn btn-success">
<i class="fas fa-download"></i> Sample Excel
</a>

</div>

</div>


<!-- FORM CARD -->

<div class="card">

<div class="card-body">

<form method="POST">

<div class="row">


<div class="col-md-6 mb-3">
<label>Certificate Number</label>
<input type="text" name="certificate_number" class="form-control" required>
</div>


<div class="col-md-6 mb-3">
<label>Student Name</label>
<input type="text" name="name" class="form-control" required>
</div>


<div class="col-md-6 mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control" required>
</div>


<div class="col-md-6 mb-3">
<label>Mobile</label>
<input type="text" name="mobile" class="form-control">
</div>


<div class="col-md-6 mb-3">
<label>College</label>
<input type="text" name="college" class="form-control">
</div>


<div class="col-md-6 mb-3">
<label>Department</label>
<input type="text" name="dept" class="form-control">
</div>


<div class="col-md-6 mb-3">
<label>Passout Year</label>
<input type="text" name="passout_year" class="form-control">
</div>


<div class="col-md-6 mb-3">
<label>Hall Ticket</label>
<input type="text" name="hall_ticket" class="form-control">
</div>


<!-- COURSE DROPDOWN -->

<div class="col-md-6 mb-3">

<label>Course</label>

<select name="internship_course" class="form-control" required>

<option value="">Select Course</option>

<?php
$cq = $conn->prepare("SELECT title FROM courses ORDER BY title ASC");
$cq->execute();
$course_query = $cq->get_result();
while($course = $course_query->fetch_assoc()){
?>

<option value="<?php echo $course['title']; ?>">
<?php echo $course['title']; ?>
</option>

<?php } ?>

</select>

</div>


<div class="col-md-6 mb-3">
<label>Start Date</label>
<input type="date" name="start_date" class="form-control">
</div>


<div class="col-md-6 mb-3">
<label>End Date</label>
<input type="date" name="end_date" class="form-control">
</div>


<div class="col-md-6 mb-3">
<label>Certificate Download Link</label>
<input type="text" name="download_link" class="form-control">
</div>


<div class="col-md-6 mb-3">

<label>Verification Status</label>

<select name="verification_symbol" class="form-control">

<option value="Verified">Verified</option>
<option value="Not Verified">Not Verified</option>

</select>

</div>

</div>


<div class="mt-3">

<button class="btn btn-success">
<i class="fas fa-save"></i> Save Student
</button>

<button type="reset" class="btn btn-danger">
Reset
</button>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

</div>


<!-- BULK UPLOAD MODAL -->

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

<label>Select Excel / CSV File</label>

<input type="file" name="excel_file" class="form-control" required>

</div>

<p style="font-size:13px;color:#888;">
Upload CSV file with student data
</p>

</div>

<div class="modal-footer">

<button class="btn btn-primary">
Upload
</button>

<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
Cancel
</button>

</div>

</form>

</div>

</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>