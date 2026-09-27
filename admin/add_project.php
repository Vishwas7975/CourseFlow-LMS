<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

// Auto-create tables if not exist
$conn->query("
    CREATE TABLE IF NOT EXISTS final_year_projects (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        title           VARCHAR(200)  NOT NULL,
        description     TEXT,
        domain          VARCHAR(100),
        tech_stack      VARCHAR(300),
        price           DECIMAL(10,2) NOT NULL DEFAULT 0,
        features        TEXT,
        thumbnail_url   VARCHAR(500),
        status          ENUM('active','inactive') DEFAULT 'active',
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
    CREATE TABLE IF NOT EXISTS project_orders (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        project_id      INT           NOT NULL,
        student_name    VARCHAR(150)  NOT NULL,
        email           VARCHAR(150)  NOT NULL,
        mobile          VARCHAR(20)   NOT NULL,
        hall_ticket     VARCHAR(100)  NOT NULL,
        college_name    VARCHAR(200)  NOT NULL,
        department      VARCHAR(150)  NOT NULL,
        utr_number      VARCHAR(60)   NOT NULL,
        terminal_name   VARCHAR(60)   DEFAULT 'Terminal 1',
        terminal_upi    VARCHAR(80),
        amount          DECIMAL(10,2) NOT NULL,
        payment_status  ENUM('pending','approved','rejected') DEFAULT 'pending',
        admin_note      VARCHAR(500)  DEFAULT NULL,
        ordered_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
        reviewed_at     DATETIME      DEFAULT NULL,
        INDEX idx_project (project_id),
        INDEX idx_email (email),
        INDEX idx_status (payment_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$success = '';
$error   = '';

if (isset($_SESSION['add_project_success'])) {
    $success = $_SESSION['add_project_success'];
    unset($_SESSION['add_project_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $domain        = trim($_POST['domain'] ?? '');
    $tech_stack    = trim($_POST['tech_stack'] ?? '');
    $price         = floatval($_POST['price'] ?? 0);
    $features_raw  = trim($_POST['features'] ?? '');
    $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
    $status        = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    // Convert features (one per line) → pipe-separated
    $features = implode('|', array_filter(array_map('trim', explode("\n", $features_raw))));

    if ($title === '' || $domain === '' || $price <= 0) {
        $error = 'Title, Domain, and Price are required fields.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO final_year_projects (title, description, domain, tech_stack, price, features, thumbnail_url, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssdsss", $title, $description, $domain, $tech_stack, $price, $features, $thumbnail_url, $status);
        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['add_project_success'] = "Project \"" . htmlspecialchars($title) . "\" added successfully!";
            header("Location: add_project.php");
            exit;
        } else {
            $error = "Database error: " . $conn->error;
            $stmt->close();
        }
    }
}

$domains = ['AI / ML', 'Web Development', 'IoT', 'Data Science', 'Mobile App', 'Cybersecurity', 'Blockchain', 'Cloud Computing', 'Java Full Stack', 'Python Full Stack', 'Data Analytics', 'VLSI', 'Other'];
$page_title = "Add Project";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Final Year Project — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        .proj-form-wrap {
            max-width: 780px;
        }
        .form-card {
            background: #fff;
            border-radius: 16px;
            border: 1.5px solid #e2e8f0;
            box-shadow: 0 4px 24px rgba(0,0,0,.06);
            overflow: hidden;
            margin-bottom: 18px;
        }
        .form-card-head {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
        }
        .form-card-head-ico {
            width: 38px; height: 38px;
            background: rgba(255,255,255,.18);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem;
        }
        .form-card-head h3 { font-size: .95rem; font-weight: 700; margin: 0; }
        .form-card-head p  { font-size: .75rem; opacity: .8; margin: 2px 0 0; }
        .form-card-body { padding: 24px; }

        .f-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .f-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

        .fld { margin-bottom: 18px; }
        .fld label {
            display: block;
            font-size: .72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .05em;
            color: #475569; margin-bottom: 6px;
        }
        .fld label span.req { color: #ef4444; margin-left: 2px; }
        .fld input,
        .fld select,
        .fld textarea {
            width: 100%;
            padding: 10px 13px;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            font-size: .875rem;
            color: #0f172a;
            background: #f8fafc;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            font-family: 'DM Sans', sans-serif;
        }
        .fld input:focus,
        .fld select:focus,
        .fld textarea:focus {
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59,130,246,.1);
        }
        .fld textarea { height: 100px; resize: vertical; }
        .fld textarea.tall { height: 140px; }

        .fld .hint {
            font-size: .71rem; color: #94a3b8;
            margin-top: 5px; display: flex; align-items: center; gap: 4px;
        }

        .price-wrap { position: relative; }
        .price-wrap .rupee {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            font-weight: 700; color: #64748b; font-size: .88rem;
        }
        .price-wrap input { padding-left: 26px; }

        /* Status toggle */
        .status-toggle { display: flex; gap: 10px; }
        .status-opt { flex: 1; }
        .status-opt input[type="radio"] { display: none; }
        .status-opt label {
            display: flex; align-items: center; justify-content: center; gap: 7px;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 9px;
            cursor: pointer;
            font-size: .82rem; font-weight: 600;
            color: #64748b;
            transition: all .18s;
            text-transform: none; letter-spacing: 0;
        }
        .status-opt input[type="radio"]:checked + label.active-lbl {
            border-color: #10b981; background: #f0fdf4; color: #065f46;
        }
        .status-opt input[type="radio"]:checked + label.inactive-lbl {
            border-color: #f59e0b; background: #fffbeb; color: #92400e;
        }

        /* Thumb preview */
        .thumb-preview {
            margin-top: 10px;
            width: 100%; height: 140px;
            border-radius: 10px;
            border: 1.5px dashed #e2e8f0;
            overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            background: #f8fafc;
            color: #94a3b8; font-size: .78rem;
        }
        .thumb-preview img { width: 100%; height: 100%; object-fit: cover; display: none; }

        /* Alert */
        .alert {
            border-radius: 10px; padding: 13px 16px;
            display: flex; align-items: flex-start; gap: 10px;
            font-size: .84rem; font-weight: 500; margin-bottom: 20px;
        }
        .alert-success { background: #f0fdf4; border: 1.5px solid #86efac; color: #065f46; }
        .alert-error   { background: #fef2f2; border: 1.5px solid #fca5a5; color: #991b1b; }
        .alert i       { margin-top: 1px; flex-shrink: 0; }

        /* Actions bar */
        .form-actions {
            display: flex; align-items: center; gap: 12px;
            padding: 18px 24px;
            background: #f8fafc;
            border-top: 1.5px solid #e2e8f0;
        }
        .btn-save {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 26px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: #fff; border: none; border-radius: 9px;
            font-size: .875rem; font-weight: 700;
            cursor: pointer; transition: all .2s;
            font-family: 'DM Sans', sans-serif;
        }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(37,99,235,.35); }
        .btn-back {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 11px 20px;
            background: #fff; border: 1.5px solid #e2e8f0;
            color: #64748b; border-radius: 9px;
            font-size: .875rem; font-weight: 600;
            text-decoration: none; transition: all .2s;
        }
        .btn-back:hover { border-color: #3b82f6; color: #1d4ed8; }

        /* Tech stack tag preview */
        .tag-preview {
            display: flex; flex-wrap: wrap; gap: 6px;
            margin-top: 8px; min-height: 28px;
        }
        .tech-tag {
            background: #eff6ff; border: 1px solid #bfdbfe;
            color: #1d4ed8; border-radius: 999px;
            padding: 3px 10px; font-size: .71rem; font-weight: 700;
        }

        @media (max-width: 640px) {
            .f-grid-2, .f-grid-3 { grid-template-columns: 1fr; }
            .form-card-body { padding: 16px; }
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
                <h1><i class="fas fa-folder-plus"></i> Add Final Year Project</h1>
                <p>Add a new project to the catalogue. Students can browse and purchase projects from the public projects page.</p>
            </div>

            <div class="proj-form-wrap">

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <?= $success ?>
                        — <a href="manage_projects.php" style="color:#065f46;font-weight:700;">View all projects</a>
                        or <a href="add_project.php" style="color:#065f46;font-weight:700;">add another</a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="addProjectForm">

                    <!-- Basic Info -->
                    <div class="form-card">
                        <div class="form-card-head">
                            <div class="form-card-head-ico"><i class="fas fa-info-circle"></i></div>
                            <div>
                                <h3>Basic Information</h3>
                                <p>Project title, domain and pricing</p>
                            </div>
                        </div>
                        <div class="form-card-body">
                            <div class="fld">
                                <label>Project Title <span class="req">*</span></label>
                                <input type="text" name="title"
                                    placeholder="e.g. Smart Attendance System using Face Recognition"
                                    value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                    required maxlength="200">
                            </div>

                            <div class="f-grid-2">
                                <div class="fld">
                                    <label>Domain <span class="req">*</span></label>
                                    <select name="domain" required>
                                        <option value="">— Select Domain —</option>
                                        <?php foreach ($domains as $d): ?>
                                        <option value="<?= $d ?>" <?= (($_POST['domain'] ?? '') === $d) ? 'selected' : '' ?>>
                                            <?= $d ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="fld">
                                    <label>Price (₹) <span class="req">*</span></label>
                                    <div class="price-wrap">
                                        <span class="rupee">₹</span>
                                        <input type="number" name="price" step="1" min="1"
                                            placeholder="e.g. 1499"
                                            value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div class="fld">
                                <label>Description</label>
                                <textarea name="description" class="tall"
                                    placeholder="Describe what the project does, its purpose, and what problem it solves..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Tech Stack & Features -->
                    <div class="form-card">
                        <div class="form-card-head">
                            <div class="form-card-head-ico"><i class="fas fa-code"></i></div>
                            <div>
                                <h3>Tech Stack &amp; Features</h3>
                                <p>Technologies used and what's included in delivery</p>
                            </div>
                        </div>
                        <div class="form-card-body">
                            <div class="fld">
                                <label>Tech Stack</label>
                                <input type="text" name="tech_stack" id="techInput"
                                    placeholder="e.g. Python, Flask, MySQL, OpenCV"
                                    value="<?= htmlspecialchars($_POST['tech_stack'] ?? '') ?>"
                                    maxlength="300">
                                <div class="hint"><i class="fas fa-info-circle"></i> Comma-separated technologies</div>
                                <div class="tag-preview" id="tagPreview"></div>
                            </div>

                            <div class="fld">
                                <label>What's Included (Features)</label>
                                <textarea name="features"
                                    placeholder="Full source code&#10;Project documentation (PDF)&#10;Database schema / SQL file&#10;PPT presentation&#10;Demo video link&#10;Setup & installation guide"><?= htmlspecialchars($_POST['features'] ?? '') ?></textarea>
                                <div class="hint"><i class="fas fa-info-circle"></i> One item per line — shown as bullet points to students</div>
                            </div>
                        </div>
                    </div>

                    <!-- Thumbnail & Status -->
                    <div class="form-card">
                        <div class="form-card-head">
                            <div class="form-card-head-ico"><i class="fas fa-image"></i></div>
                            <div>
                                <h3>Thumbnail &amp; Visibility</h3>
                                <p>Project image and public status</p>
                            </div>
                        </div>
                        <div class="form-card-body">
                            <div class="f-grid-2" style="align-items:start;">
                                <div>
                                    <div class="fld">
                                        <label>Thumbnail Image URL</label>
                                        <input type="url" name="thumbnail_url" id="thumbInput"
                                            placeholder="https://example.com/project-image.jpg"
                                            value="<?= htmlspecialchars($_POST['thumbnail_url'] ?? '') ?>">
                                        <div class="hint"><i class="fas fa-info-circle"></i> Paste any public image URL</div>
                                    </div>
                                    <div class="fld" style="margin-bottom:0;">
                                        <label>Status <span class="req">*</span></label>
                                        <div class="status-toggle">
                                            <div class="status-opt">
                                                <input type="radio" name="status" id="s_active" value="active"
                                                    <?= (($_POST['status'] ?? 'active') === 'active') ? 'checked' : '' ?>>
                                                <label for="s_active" class="active-lbl">
                                                    <i class="fas fa-eye"></i> Active
                                                </label>
                                            </div>
                                            <div class="status-opt">
                                                <input type="radio" name="status" id="s_inactive" value="inactive"
                                                    <?= (($_POST['status'] ?? '') === 'inactive') ? 'checked' : '' ?>>
                                                <label for="s_inactive" class="inactive-lbl">
                                                    <i class="fas fa-eye-slash"></i> Inactive
                                                </label>
                                            </div>
                                        </div>
                                        <div class="hint" style="margin-top:8px;"><i class="fas fa-info-circle"></i> Inactive projects are hidden from students</div>
                                    </div>
                                </div>
                                <div>
                                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#475569;margin-bottom:6px;">Preview</div>
                                    <div class="thumb-preview" id="thumbPreview">
                                        <img id="thumbImg" src="" alt="Preview">
                                        <span id="thumbPlaceholder"><i class="fas fa-image" style="margin-right:5px;"></i> No image URL entered</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save Project
                        </button>
                        <a href="manage_projects.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Projects
                        </a>
                    </div>

                </form>
            </div><!-- /proj-form-wrap -->

        </div><!-- /content-wrapper -->
    </div><!-- /main -->
</div><!-- /dashboard -->

<?php include("../includes/footer.php"); ?>

<script>
// Live tech stack tag preview
const techInput = document.getElementById('techInput');
const tagPreview = document.getElementById('tagPreview');

function renderTags() {
    const val = techInput.value.trim();
    tagPreview.innerHTML = '';
    if (!val) return;
    val.split(',').forEach(t => {
        t = t.trim();
        if (t) {
            const sp = document.createElement('span');
            sp.className = 'tech-tag';
            sp.textContent = t;
            tagPreview.appendChild(sp);
        }
    });
}
techInput.addEventListener('input', renderTags);
renderTags(); // run on load (for re-populated form after error)

// Live thumbnail preview
const thumbInput   = document.getElementById('thumbInput');
const thumbImg     = document.getElementById('thumbImg');
const thumbHolder  = document.getElementById('thumbPlaceholder');

function updateThumb() {
    const url = thumbInput.value.trim();
    if (url) {
        thumbImg.src = url;
        thumbImg.style.display = 'block';
        thumbHolder.style.display = 'none';
        thumbImg.onerror = () => {
            thumbImg.style.display = 'none';
            thumbHolder.style.display = '';
            thumbHolder.innerHTML = '<i class="fas fa-exclamation-triangle" style="margin-right:5px;color:#f59e0b"></i> Could not load image';
        };
    } else {
        thumbImg.style.display = 'none';
        thumbHolder.style.display = '';
        thumbHolder.innerHTML = '<i class="fas fa-image" style="margin-right:5px;"></i> No image URL entered';
    }
}
thumbInput.addEventListener('input', updateThumb);
updateThumb();
</script>
</body>
</html>