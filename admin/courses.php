<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "admin") {
    header("Location: ../login.php"); exit();
}

/* ── Auto-create course_syllabus table if not exists ── */
$conn->query("
    CREATE TABLE IF NOT EXISTS `course_syllabus` (
      `id`          INT(11)       NOT NULL AUTO_INCREMENT,
      `course_id`   INT(11)       NOT NULL,
      `pdf_path`    VARCHAR(500)  NOT NULL,
      `file_name`   VARCHAR(255)  NOT NULL,
      `uploaded_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_course` (`course_id`),
      CONSTRAINT `fk_syl_course` FOREIGN KEY (`course_id`)
          REFERENCES `courses` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

/* ── Fetch courses LEFT JOINed with syllabus status ── */
$result = $conn->query("
    SELECT c.*, cs.id AS syl_id
    FROM courses c
    LEFT JOIN course_syllabus cs ON cs.course_id = c.id
    ORDER BY c.created_at DESC
");

$page_title = "Manage Courses";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses – CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        /* ── Syllabus badge ── */
        .syl-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: .7rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 999px;
            line-height: 1;
            white-space: nowrap;
        }
        .syl-badge.has-syl {
            background: var(--success-light);
            color: #15803D;
        }
        .syl-badge.no-syl {
            background: var(--warning-light);
            color: #B45309;
        }

        /* ── Add Syllabus button in card actions ── */
        a.btn-syllabus {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: var(--radius-sm);
            font-size: .76rem;
            font-weight: 600;
            transition: all var(--transition);
            text-decoration: none;
            white-space: nowrap;
        }
        a.btn-syllabus.has-syl {
            background: var(--success-light);
            color: #15803D;
        }
        a.btn-syllabus.has-syl:hover {
            background: var(--success);
            color: #fff;
        }
        a.btn-syllabus.no-syl {
            background: var(--warning-light);
            color: #B45309;
        }
        a.btn-syllabus.no-syl:hover {
            background: var(--warning);
            color: #fff;
        }

        /* ── Card price + badge row ── */
        .card-meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        /* ── Mobile fixes ── */
        @media (max-width: 520px) {
            .page-header              { flex-direction: column !important; align-items: flex-start !important; }
            .page-header .btn         { width: 100%; justify-content: center; min-height: 44px; }
            .course-actions           { flex-wrap: wrap; gap: 6px; }
            .btn-edit, .btn-delete,
            a.btn-syllabus            { flex: 1 1 calc(50% - 6px); justify-content: center; min-height: 40px; }
            .back-btn                 { display: flex; justify-content: center; min-height: 44px; align-items: center; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- PAGE HEADER -->
            <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <h1><i class="fas fa-book"></i> Manage Courses</h1>
                    <p>Create, edit and manage all courses on the platform.</p>
                </div>
                <a href="add_course.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Course
                </a>
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
            <div class="course-grid">
                <?php while ($row = $result->fetch_assoc()):
                    $has_syl = !empty($row['syl_id']);
                ?>
                <div class="course-card">

                    <!-- Course thumbnail -->
                    <div class="course-image">
                        <?php if (!empty($row['image'])): ?>
                            <img src="<?= htmlspecialchars($row['image']) ?>" alt="Course image">
                        <?php else: ?>
                            <i class="fas fa-book"></i>
                        <?php endif; ?>
                    </div>

                    <div class="course-content">
                        <h3><?= htmlspecialchars($row['title']) ?></h3>
                        <p><?= htmlspecialchars(substr($row['description'], 0, 100)) ?>…</p>

                        <!-- Price + syllabus badge in one row -->
                        <div class="card-meta-row">
                            <div class="course-price" style="margin-bottom:0">
                                <?php if ($row['price'] > 0): ?>
                                    <span class="course-price-paid">₹<?= $row['price'] ?></span>
                                <?php else: ?>
                                    <span class="course-price-free">Free</span>
                                <?php endif; ?>
                            </div>
                            <span class="syl-badge <?= $has_syl ? 'has-syl' : 'no-syl' ?>">
                                <i class="fas <?= $has_syl ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                                <?= $has_syl ? 'Syllabus uploaded ✓' : 'No syllabus yet' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Actions: Edit | Add Syllabus | Delete -->
                    <div class="course-actions">
                        <a href="edit_course.php?id=<?= $row['id'] ?>" class="btn-edit">
                            <i class="fas fa-pen"></i> Edit
                        </a>
                        <a href="manage_syllabus.php?course_id=<?= $row['id'] ?>"
                           class="btn-syllabus <?= $has_syl ? 'has-syl' : 'no-syl' ?>">
                            <i class="fas <?= $has_syl ? 'fa-file-pdf' : 'fa-upload' ?>"></i>
                            <?= $has_syl ? 'Syllabus' : 'Add Syllabus' ?>
                        </a>
                        <a href="delete_course.php?id=<?= $row['id'] ?>" class="btn-delete"
                           onclick="return confirm('Delete this course?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>

                </div>
                <?php endwhile; ?>
            </div>

            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px;display:block"></i>
                <p>No courses yet. <a href="add_course.php">Add your first course!</a></p>
            </div>
            <?php endif; ?>

            <div style="margin-top:24px">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>