<?php
include("../config/db.php");

$student = null;
$courses  = [];

if (isset($_GET['cert'])) {

    $cert = trim($_GET['cert']);

    /* GET STUDENT — prepared statement (fixes SQL injection) */
    $stmt = $conn->prepare("SELECT * FROM users WHERE certificate_number = ?");
    $stmt->bind_param("s", $cert);
    $stmt->execute();
    $res     = $stmt->get_result();
    $student = $res->fetch_assoc();
    $stmt->close();

    if ($student) {
        $uid = (int)$student['id'];

        /* GET COURSES — prepared statement (fixes SQL injection) */
        $sc = $conn->prepare("
            SELECT courses.title
            FROM enrollments
            JOIN courses ON courses.id = enrollments.course_id
            WHERE enrollments.user_id = ?
        ");
        $sc->bind_param("i", $uid);
        $sc->execute();
        $cr = $sc->get_result();
        while ($row = $cr->fetch_assoc()) {
            $courses[] = $row['title'];
        }
        $sc->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate Verification — CourseFlow LMS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<style>
body { background: #f2f4f8; font-family: Arial, sans-serif; }
.verify-box {
    max-width: 700px; margin: auto; margin-top: 80px;
    background: white; padding: 30px;
    border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
</style>
</head>
<body>

<div class="verify-box">

    <h3 class="text-center mb-4">Certificate Verification Portal</h3>

    <form method="GET">
        <div class="input-group mb-4">
            <input type="text" name="cert" class="form-control"
                   placeholder="Enter Certificate Number"
                   value="<?php echo htmlspecialchars($_GET['cert'] ?? ''); ?>"
                   required>
            <button class="btn btn-primary">Verify</button>
        </div>
    </form>

    <?php if (isset($_GET['cert'])): ?>

        <?php if ($student): ?>

            <div class="alert alert-success text-center">Certificate Verified ✅</div>

            <!-- table-wrap: enables horizontal scroll on mobile without breaking layout -->
            <div class="table-wrap">
            <table class="table table-bordered">
                <tr>
                    <th>Certificate Number</th>
                    <td><?php echo htmlspecialchars($student['certificate_number'] ?? ''); ?></td>
                </tr>
                <tr>
                    <th>Student Name</th>
                    <td><?php echo htmlspecialchars($student['name'] ?? ''); ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?php echo htmlspecialchars($student['email'] ?? ''); ?></td>
                </tr>
                <tr>
                    <th>College</th>
                    <td><?php echo htmlspecialchars($student['college'] ?? ''); ?></td>
                </tr>
                <tr>
                    <th>Department</th>
                    <td><?php echo htmlspecialchars($student['dept'] ?? ''); ?></td>
                </tr>
                <tr>
                    <th>Passout Year</th>
                    <td><?php echo htmlspecialchars($student['passout_year'] ?? ''); ?></td>
                </tr>
                <tr>
                    <th>Courses</th>
                    <td>
                        <?php foreach ($courses as $course): ?>
                            <?php echo htmlspecialchars($course); ?><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th>Start Date</th>
                    <td><?php echo htmlspecialchars($student['start_date'] ?? ''); ?></td>
                </tr>
                <tr>
                    <th>End Date</th>
                    <td><?php echo htmlspecialchars($student['end_date'] ?? ''); ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        <span class="badge bg-success">
                            <?php echo htmlspecialchars($student['verification_symbol'] ?? 'Verified'); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Certificate</th>
                    <td>
                        <?php if (!empty($student['download_link'])): ?>
                            <a href="<?php echo htmlspecialchars($student['download_link']); ?>" target="_blank">
                                Download
                            </a>
                        <?php else: ?>
                            <span class="text-muted">Not available</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            </div><!-- /table-wrap -->

            <div class="alert alert-danger text-center">Certificate Not Found ❌</div>

        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>