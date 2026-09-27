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

/* ── Ensure upload folder exists ── */
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/syllabus/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

/* ── Validate course_id ── */
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if ($course_id <= 0) {
    header("Location: courses.php"); exit();
}

$course_stmt = $conn->prepare("SELECT id, title, image FROM courses WHERE id = ? LIMIT 1");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course = $course_stmt->get_result()->fetch_assoc();
if (!$course) {
    header("Location: courses.php"); exit();
}

/* ── Fetch existing syllabus ── */
$syl_stmt = $conn->prepare("SELECT * FROM course_syllabus WHERE course_id = ? LIMIT 1");
$syl_stmt->bind_param("i", $course_id);
$syl_stmt->execute();
$existing = $syl_stmt->get_result()->fetch_assoc();

$msg   = '';
$mtype = '';

/* ── Handle REMOVE action ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove') {
    if ($existing) {
        $old_file = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($existing['pdf_path'], '/');
        if (file_exists($old_file)) @unlink($old_file);
        $del = $conn->prepare("DELETE FROM course_syllabus WHERE course_id = ?");
        $del->bind_param("i", $course_id);
        $del->execute();
        $existing = null;
        $msg   = 'Syllabus removed successfully.';
        $mtype = 'success';
    }
}

/* ── Handle UPLOAD action ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['syllabus_pdf']) && $_FILES['syllabus_pdf']['error'] === UPLOAD_ERR_OK) {
    $file     = $_FILES['syllabus_pdf'];
    $max_size = 20 * 1024 * 1024; // 20 MB

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mime     = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['size'] > $max_size) {
        $msg = 'File too large. Maximum allowed size is 20 MB.';
        $mtype = 'error';
    } elseif ($ext !== 'pdf' || $mime !== 'application/pdf') {
        $msg = 'Invalid file. Only PDF files are accepted.';
        $mtype = 'error';
    } else {
        /* Remove old file */
        if ($existing) {
            $old_file = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($existing['pdf_path'], '/');
            if (file_exists($old_file)) @unlink($old_file);
        }

        $new_filename = 'course_' . $course_id . '_' . time() . '.pdf';
        $dest         = $upload_dir . $new_filename;
        $db_path      = 'uploads/syllabus/' . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $orig_name = htmlspecialchars($file['name']);
            if ($existing) {
                $upd = $conn->prepare("UPDATE course_syllabus SET pdf_path=?, file_name=? WHERE course_id=?");
                $upd->bind_param("ssi", $db_path, $orig_name, $course_id);
                $upd->execute();
            } else {
                $ins = $conn->prepare("INSERT INTO course_syllabus (course_id, pdf_path, file_name) VALUES (?, ?, ?)");
                $ins->bind_param("iss", $course_id, $db_path, $orig_name);
                $ins->execute();
            }
            /* Re-fetch */
            $syl_stmt->execute();
            $existing = $syl_stmt->get_result()->fetch_assoc();
            $msg   = 'Syllabus uploaded successfully!';
            $mtype = 'success';
        } else {
            $msg   = 'Upload failed. Please check server permissions on uploads/syllabus/.';
            $mtype = 'error';
        }
    }
}

$page_title = "Manage Syllabus";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Syllabus – CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        /* ── Manage Syllabus Styles ── */
        .syl-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 860px) { .syl-layout { grid-template-columns: 1fr; } }

        .syl-card {
            background: var(--card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
            padding: 28px;
        }
        .syl-card h3 {
            font-size: .95rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .syl-card h3 i { color: var(--primary); }

        /* Drop zone */
        .drop-zone {
            border: 2.5px dashed var(--border);
            border-radius: var(--radius-lg);
            background: var(--bg-alt);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 40px 24px;
            cursor: pointer;
            transition: all .22s ease;
            text-align: center;
            position: relative;
        }
        .drop-zone.drag-over {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .drop-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .drop-zone .dz-icon {
            width: 58px;
            height: 58px;
            border-radius: var(--radius-md);
            background: var(--primary-light);
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            pointer-events: none;
        }
        .drop-zone .dz-title {
            font-weight: 700;
            font-size: .92rem;
            color: var(--text-primary);
            pointer-events: none;
        }
        .drop-zone .dz-sub {
            font-size: .78rem;
            color: var(--text-muted);
            pointer-events: none;
        }
        .dz-filename {
            margin-top: 10px;
            font-size: .82rem;
            color: var(--primary-dark);
            font-weight: 600;
            display: none;
            align-items: center;
            gap: 6px;
        }

        /* Upload progress bar */
        .upload-progress {
            display: none;
            margin-top: 14px;
        }
        .upload-bar-bg {
            height: 6px;
            background: var(--border-light);
            border-radius: 99px;
            overflow: hidden;
        }
        .upload-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            border-radius: 99px;
            transition: width .15s linear;
        }
        .upload-label {
            font-size: .74rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Existing syllabus panel */
        .existing-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-alt);
            border: 1.5px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .existing-pill .pill-icon {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            background: var(--danger-light);
            color: #DC2626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .existing-pill .pill-meta { flex: 1; min-width: 0; }
        .existing-pill .pill-name {
            font-weight: 700;
            font-size: .84rem;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .existing-pill .pill-date {
            font-size: .73rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .pdf-preview-frame {
            width: 100%;
            height: 420px;
            border: 1.5px solid var(--border-light);
            border-radius: var(--radius-md);
            background: #f4f6fa;
        }
        @media (max-width: 520px) { .pdf-preview-frame { height: 280px; } }

        /* Course info banner */
        .course-info-banner {
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--primary-light);
            border: 1.5px solid rgba(77,168,255,.25);
            border-radius: var(--radius-lg);
            padding: 14px 18px;
            margin-bottom: 24px;
        }
        .course-info-banner img {
            width: 54px;
            height: 54px;
            border-radius: var(--radius-md);
            object-fit: cover;
            flex-shrink: 0;
        }
        .course-info-banner .ci-icon {
            width: 54px;
            height: 54px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary-light), #dbeeff);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .course-info-banner .ci-title {
            font-size: .92rem;
            font-weight: 700;
            color: var(--primary-darker, var(--primary-dark));
        }
        .course-info-banner .ci-sub {
            font-size: .76rem;
            color: var(--primary-dark);
            margin-top: 2px;
        }

        /* Alert */
        .syl-alert {
            border-radius: var(--radius-md);
            padding: 12px 16px;
            font-size: .83rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .syl-alert.success { background: var(--success-light); color: #14532D; }
        .syl-alert.error   { background: var(--danger-light);  color: #7F1D1D; }

        /* Mobile action buttons */
        @media (max-width: 520px) {
            .syl-action-row {
                flex-direction: column;
            }
            .syl-action-row .btn,
            .syl-action-row a {
                width: 100%;
                justify-content: center;
                min-height: 44px;
            }
            .page-header { flex-direction: column !important; align-items: flex-start !important; }
            .page-header .btn { width: 100%; justify-content: center; min-height: 44px; }
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
            <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
                <div>
                    <h1><i class="fas fa-file-pdf"></i> Manage Syllabus</h1>
                    <p>Upload or replace the course syllabus PDF.</p>
                </div>
                <a href="courses.php" class="btn btn-ghost">
                    <i class="fas fa-arrow-left"></i> Back to Courses
                </a>
            </div>

            <!-- COURSE BANNER -->
            <div class="course-info-banner">
                <?php if (!empty($course['image'])): ?>
                    <img src="<?= htmlspecialchars($course['image']) ?>" alt="course">
                <?php else: ?>
                    <div class="ci-icon"><i class="fas fa-book"></i></div>
                <?php endif; ?>
                <div>
                    <div class="ci-title"><?= htmlspecialchars($course['title']) ?></div>
                    <div class="ci-sub"><i class="fas fa-hashtag"></i> Course ID: <?= $course_id ?></div>
                </div>
            </div>

            <!-- ALERT -->
            <?php if ($msg): ?>
            <div class="syl-alert <?= $mtype ?>">
                <i class="fas fa-<?= $mtype === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= htmlspecialchars($msg) ?>
            </div>
            <?php endif; ?>

            <div class="syl-layout">

                <!-- LEFT: Upload Panel -->
                <div class="syl-card">
                    <h3><i class="fas fa-cloud-upload-alt"></i>
                        <?= $existing ? 'Replace Syllabus PDF' : 'Upload Syllabus PDF' ?>
                    </h3>

                    <?php if ($existing): ?>
                    <div class="existing-pill">
                        <div class="pill-icon"><i class="fas fa-file-pdf"></i></div>
                        <div class="pill-meta">
                            <div class="pill-name"><?= htmlspecialchars($existing['file_name']) ?></div>
                            <div class="pill-date">
                                <i class="fas fa-clock"></i>
                                Uploaded <?= date('d M Y, h:i A', strtotime($existing['uploaded_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:16px">
                        <i class="fas fa-info-circle"></i>
                        Uploading a new PDF will automatically replace the existing one.
                    </p>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="drop-zone" id="dropZone">
                            <input type="file" name="syllabus_pdf" id="pdfInput" accept="application/pdf,.pdf">
                            <div class="dz-icon"><i class="fas fa-file-pdf"></i></div>
                            <div class="dz-title">Drag & drop PDF here</div>
                            <div class="dz-sub">or click to browse &bull; PDF only &bull; Max 20 MB</div>
                        </div>

                        <div class="dz-filename" id="dzFilename">
                            <i class="fas fa-check-circle" style="color:var(--success)"></i>
                            <span id="dzFilenameTxt"></span>
                        </div>

                        <div class="upload-progress" id="uploadProgress">
                            <div class="upload-bar-bg"><div class="upload-bar-fill" id="uploadBar"></div></div>
                            <div class="upload-label" id="uploadLabel">Uploading…</div>
                        </div>

                        <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap" class="syl-action-row">
                            <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
                                <i class="fas fa-upload"></i>
                                <?= $existing ? 'Replace PDF' : 'Upload PDF' ?>
                            </button>
                        </div>
                    </form>

                    <?php if ($existing): ?>
                    <hr style="border:none;border-top:1px solid var(--border-light);margin:20px 0">
                    <form method="POST" onsubmit="return confirm('Remove the syllabus PDF for this course?')">
                        <input type="hidden" name="action" value="remove">
                        <div class="syl-action-row" style="display:flex">
                            <button type="submit" class="btn btn-danger btn-sm" style="gap:6px">
                                <i class="fas fa-trash-alt"></i> Remove Syllabus
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- RIGHT: Preview Panel -->
                <div class="syl-card">
                    <h3><i class="fas fa-eye"></i> PDF Preview</h3>
                    <?php if ($existing): ?>
                        <div style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                            <span style="font-size:.8rem;color:var(--text-muted)">
                                <i class="fas fa-file-pdf" style="color:#DC2626"></i>
                                <?= htmlspecialchars($existing['file_name']) ?>
                            </span>
                            <a href="/<?= htmlspecialchars($existing['pdf_path']) ?>"
                               target="_blank" class="btn btn-ghost btn-sm">
                                <i class="fas fa-external-link-alt"></i> Open Full
                            </a>
                        </div>
                        <iframe
                            src="/<?= htmlspecialchars($existing['pdf_path']) ?>#toolbar=1&navpanes=0"
                            class="pdf-preview-frame"
                            title="Syllabus PDF Preview">
                        </iframe>
                    <?php else: ?>
                        <div class="empty-state" style="min-height:200px;display:flex;flex-direction:column;align-items:center;justify-content:center">
                            <i class="fas fa-file-pdf" style="font-size:2.4rem;color:var(--text-muted);margin-bottom:12px;display:block"></i>
                            <p style="margin:0;color:var(--text-muted);font-size:.85rem">No syllabus uploaded yet.<br>Upload a PDF to preview it here.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div><!-- /.syl-layout -->

        </div>
    </div>
</div>

<script>
(function () {
    const dropZone  = document.getElementById('dropZone');
    const pdfInput  = document.getElementById('pdfInput');
    const uploadBtn = document.getElementById('uploadBtn');
    const dzFn      = document.getElementById('dzFilename');
    const dzFnTxt   = document.getElementById('dzFilenameTxt');
    const progress  = document.getElementById('uploadProgress');
    const bar       = document.getElementById('uploadBar');
    const barLabel  = document.getElementById('uploadLabel');
    const form      = document.getElementById('uploadForm');

    function setFile(file) {
        if (!file) return;
        if (file.type !== 'application/pdf') {
            alert('Only PDF files are accepted.');
            pdfInput.value = '';
            return;
        }
        const maxMB = 20 * 1024 * 1024;
        if (file.size > maxMB) {
            alert('File exceeds 20 MB limit.');
            pdfInput.value = '';
            return;
        }
        dzFnTxt.textContent = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
        dzFn.style.display  = 'flex';
        uploadBtn.disabled  = false;
        dropZone.style.borderColor = 'var(--success)';
        dropZone.style.background  = 'var(--success-light)';
    }

    pdfInput.addEventListener('change', function () {
        setFile(this.files[0]);
    });

    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });
    dropZone.addEventListener('dragleave', function () {
        this.classList.remove('drag-over');
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) {
            // Manually set the file input (DataTransfer workaround)
            const dt = new DataTransfer();
            dt.items.add(file);
            pdfInput.files = dt.files;
            setFile(file);
        }
    });

    /* Animated progress bar during submit (simulated) */
    form.addEventListener('submit', function () {
        if (pdfInput.files.length === 0) return;
        progress.style.display = 'block';
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading…';

        let pct = 0;
        const iv = setInterval(function () {
            pct += Math.random() * 18;
            if (pct >= 90) pct = 90;
            bar.style.width = pct + '%';
            barLabel.textContent = 'Uploading… ' + Math.round(pct) + '%';
        }, 200);

        /* Let the real form submit proceed */
        setTimeout(function () { clearInterval(iv); }, 8000);
    });
})();
</script>

<?php include("../includes/footer.php"); ?>