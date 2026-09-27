<?php
session_start();

include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");
?>

<!DOCTYPE html>
<html>

<head>

<title>Bulk Upload Students</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">

</head>

<body>

<div class="container mt-5">

<h3>Bulk Upload Students</h3>

<p>Upload CSV file to insert multiple students.</p>

<a href="download_sample_excel.php" class="btn btn-success mb-3">
Download Sample Excel
</a>

<form method="POST" action="process_excel.php" enctype="multipart/form-data">

<div class="mb-3">

<label>Select CSV File</label>

<input type="file" name="excel_file" class="form-control" required>

</div>

<button class="btn btn-primary">
Upload Students
</button>

<a href="student_data.php" class="btn btn-secondary">
Back
</a>

</form>

</div>

</body>

</html>