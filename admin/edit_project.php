<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

// Auto-create tables (safe / idempotent)
$conn->query("
    CREATE TABLE IF NOT EXISTS final_year_projects (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        title         VARCHAR(200)  NOT NULL,
        description   TEXT,
        domain        VARCHAR(100),
        tech_stack    VARCHAR(300),
        price         DECIMAL(10,2) NOT NULL DEFAULT 0,
        features      TEXT,
        thumbnail_url VARCHAR(500),
        status        ENUM('active','inactive') DEFAULT 'active',
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── Validate ID ──
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_projects.php"); exit();
}
$id = (int)$_GET['id'];

// ── Load project ──
$stmt = $conn->prepare("SELECT * FROM final_year_projects WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    header("Location: manage_projects.php?msg=notfound"); exit();
}

$success = '';
$error   = '';

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title         = trim($_POST['title']         ?? '');
    $description   = trim($_POST['description']   ?? '');
    $domain        = trim($_POST['domain']        ?? '');
    $tech_stack    = trim($_POST['tech_stack']    ?? '');
    $price         = floatval($_POST['price']     ?? 0);
    $features_raw  = trim($_POST['features']      ?? '');
    $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
    $status        = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    // One per line → pipe-separated
    $features = implode('|', array_filter(array_map('trim', explode("\n", $features_raw))));

    if ($title === '' || $domain === '' || $price <= 0) {
        $error = 'Title, Domain, and Price are required fields.';
    } else {
        $stmt = $conn->prepare("
            UPDATE final_year_projects
            SET title=?, description=?, domain=?, tech_stack=?,
                price=?, features=?, thumbnail_url=?, status=?
            WHERE id=?
        ");
        $stmt->bind_param("ssssdsssi",
            $title, $description, $domain, $tech_stack,
            $price, $features, $thumbnail_url, $status, $id
        );
        if ($stmt->execute()) {
            // Refresh project data
            $project['title']         = $title;
            $project['description']   = $description;
            $project['domain']        = $domain;
            $project['tech_stack']    = $tech_stack;
            $project['price']         = $price;
            $project['features']      = $features;
            $project['thumbnail_url'] = $thumbnail_url;
            $project['status']        = $status;
            $success = 'Project updated successfully!';
        } else {
            $error = 'Database error: ' . $conn->error;
        }
        $stmt->close();
    }
}

// Features: pipe → newline for textarea display
$features_display = str_replace('|', "\n", $project['features'] ?? '');

$domains    = ['AI / ML', 'Web Development', 'IoT', 'Data Science', 'Mobile App', 'Cybersecurity', 'Blockchain', 'Cloud Computing', 'Java Full Stack', 'Python Full Stack', 'Data Analytics', 'VLSI', 'Other'];
$page_title = "Edit Project";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project — CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
        .proj-form-wrap { max-width: 780px; }

        /* ── Breadcrumb ── */
        .breadcrumb {
            display: flex; align-items: center; gap: 7px;
            font-size: .78rem; color: #94a3b8;
            margin-bottom: 18px; flex-wrap: wrap;
        }
        .breadcrumb a { color: #3b82f6; text-decoration: none; font-weight: 600; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb i { font-size: .65rem; }

        /* ── Project meta bar ── */
        .proj-meta-bar {
            display: flex; align-items: center; gap: 14px;
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.04);
            flex-wrap: wrap;
        }
        .pmb-thumb {
            width: 56px; height: 44px; border-radius: 9px;
            overflow: hidden; background: #f1f5f9;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .pmb-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .pmb-thumb i { color: #94a3b8; font-size: 1rem; }
        .pmb-title { font-weight: 700; font-size: .9rem; color: #0f172a; }
        .pmb-sub   { font-size: .73rem; color: #64748b; margin-top: 2px; }
        .pmb-badge {
            margin-left: auto;
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 999px;
            font-size: .72rem; font-weight: 700;
        }
        .pmb-badge.active   { background: #dcfce7; color: #15803d; }
        .pmb-badge.inactive { background: #fef9c3; color: #854d0e; }
        .pmb-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .pmb-id {
            font-size: .72rem; font-weight: 700; color: #94a3b8;
            background: #f1f5f9; padding: 4px 10px; border-radius: 999px;
        }

        /* ── Form cards ── */
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
            display: flex; align-items: center; gap: 12px;
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

        .fld { margin-bottom: 18px; }
        .fld:last-child { margin-bottom: 0; }
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
        .fld textarea      { height: 100px; resize: vertical; }
        .fld textarea.tall { height: 140px; }
        .fld .hint {
            font-size: .71rem; color: #94a3b8;
            margin-top: 5px; display: flex; align-items: center; gap: 4px;
        }

        /* Changed field highlight */
        .fld input.changed,
        .fld select.changed,
        .fld textarea.changed {
            border-color: #f59e0b;
            background: #fffbeb;
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
            border: 2px solid #e2e8f0; border-radius: 9px;
            cursor: pointer; font-size: .82rem; font-weight: 600;
            color: #64748b; transition: all .18s;
            text-transform: none; letter-spacing: 0;
        }
        .status-opt input[type="radio"]:checked + label.active-lbl   { border-color: #10b981; background: #f0fdf4; color: #065f46; }
        .status-opt input[type="radio"]:checked + label.inactive-lbl { border-color: #f59e0b; background: #fffbeb; color: #92400e; }

        /* Thumb preview */
        .thumb-preview {
            width: 100%; height: 140px; border-radius: 10px;
            border: 1.5px dashed #e2e8f0; overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            background: #f8fafc; color: #94a3b8; font-size: .78rem;
            margin-top: 10px;
        }
        .thumb-preview img { width: 100%; height: 100%; object-fit: cover; display: none; }

        /* Tech tags */
        .tag-preview { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; min-height: 28px; }
        .tech-tag {
            background: #eff6ff; border: 1px solid #bfdbfe;
            color: #1d4ed8; border-radius: 999px;
            padding: 3px 10px; font-size: .71rem; font-weight: 700;
        }

        /* Alerts */
        .alert {
            border-radius: 10px; padding: 13px 16px;
            display: flex; align-items: flex-start; gap: 10px;
            font-size: .84rem; font-weight: 500; margin-bottom: 20px;
        }
        .alert-success { background: #f0fdf4; border: 1.5px solid #86efac; color: #065f46; }
        .alert-error   { background: #fef2f2; border: 1.5px solid #fca5a5; color: #991b1b; }
        .alert i { margin-top: 1px; flex-shrink: 0; }

        /* Actions bar */
        .form-actions {
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
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
        .btn-view-orders {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 11px 20px;
            background: #fdf4ff; border: 1.5px solid #e9d5ff;
            color: #7c3aed; border-radius: 9px;
            font-size: .875rem; font-weight: 600;
            text-decoration: none; transition: all .2s; margin-left: auto;
        }
        .btn-view-orders:hover { background: #f5f3ff; border-color: #c4b5fd; }

        /* Changes indicator */
        .changes-bar {
            display: none;
            align-items: center; gap: 9px;
            background: #fffbeb; border: 1.5px solid #fcd34d;
            border-radius: 10px; padding: 10px 16px;
            font-size: .82rem; color: #92400e; font-weight: 600;
            margin-bottom: 16px;
        }
        .changes-bar.show { display: flex; }

        @media (max-width: 640px) {
            .f-grid-2 { grid-template-columns: 1fr; }
            .form-card-body { padding: 16px; }
            .form-actions { flex-direction: column; }
            .form-actions * { width: 100%; justify-content: center; }
            .btn-view-orders { margin-left: 0; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>
    <div class="main">
        <?php include("../includes/topbar.php"); ?>
        <div class="content-wrapper">

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="manage_projects.php">Final Year Projects</a>
                <i class="fas fa-chevron-right"></i>
                <span>Edit Project</span>
            </div>

            <div class="page-header" style="margin-bottom:16px;">
                <h1><i class="fas fa-pen-to-square"></i> Edit Project</h1>
                <p>Update the project details. Changes are reflected immediately on the public projects page.</p>
            </div>

            <div class="proj-form-wrap">

                <!-- Project meta bar -->
                <div class="proj-meta-bar">
                    <div class="pmb-thumb">
                        <?php if (!empty($project['thumbnail_url'])): ?>
                        <img src="<?= htmlspecialchars($project['thumbnail_url']) ?>"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" alt="">
                        <i class="fas fa-folder" style="display:none;"></i>
                        <?php else: ?>
                        <i class="fas fa-folder"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="pmb-title"><?= htmlspecialchars($project['title']) ?></div>
                        <div class="pmb-sub"><?= htmlspecialchars($project['domain']) ?> &nbsp;·&nbsp; ₹<?= number_format((float)$project['price'], 0) ?></div>
                    </div>
                    <span class="pmb-id">#<?= $project['id'] ?></span>
                    <span class="pmb-badge <?= $project['status'] ?>">
                        <span class="pmb-dot"></span>
                        <?= ucfirst($project['status']) ?>
                    </span>
                </div>

                <!-- Alerts -->
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <?= $success ?>
                        — <a href="manage_projects.php" style="color:#065f46;font-weight:700;">Back to all projects</a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <!-- Unsaved changes bar -->
                <div class="changes-bar" id="changesBar">
                    <i class="fas fa-exclamation-triangle"></i>
                    You have unsaved changes — remember to save before leaving.
                </div>

                <form method="POST" id="editProjectForm">

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
                                    value="<?= htmlspecialchars($project['title']) ?>"
                                    required maxlength="200">
                            </div>

                            <div class="f-grid-2">
                                <div class="fld">
                                    <label>Domain <span class="req">*</span></label>
                                    <select name="domain" required>
                                        <option value="">— Select Domain —</option>
                                        <?php foreach ($domains as $d): ?>
                                        <option value="<?= $d ?>" <?= $project['domain'] === $d ? 'selected' : '' ?>>
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
                                            value="<?= htmlspecialchars($project['price']) ?>"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div class="fld" style="margin-bottom:0;">
                                <label>Description</label>
                                <textarea name="description" class="tall"
                                    placeholder="Describe what the project does..."><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
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
                                    value="<?= htmlspecialchars($project['tech_stack'] ?? '') ?>"
                                    maxlength="300">
                                <div class="hint"><i class="fas fa-info-circle"></i> Comma-separated technologies</div>
                                <div class="tag-preview" id="tagPreview"></div>
                            </div>

                            <div class="fld" style="margin-bottom:0;">
                                <label>What's Included (Features)</label>
                                <textarea name="features"
                                    placeholder="Full source code&#10;Project documentation (PDF)&#10;Database schema / SQL file&#10;PPT presentation&#10;Demo video link&#10;Setup & installation guide"><?= htmlspecialchars($features_display) ?></textarea>
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
                                <p>Project image URL and public status</p>
                            </div>
                        </div>
                        <div class="form-card-body">
                            <div class="f-grid-2" style="align-items:start;">
                                <div>
                                    <div class="fld">
                                        <label>Thumbnail Image URL</label>
                                        <input type="url" name="thumbnail_url" id="thumbInput"
                                            placeholder="https://example.com/project-image.jpg"
                                            value="<?= htmlspecialchars($project['thumbnail_url'] ?? '') ?>">
                                        <div class="hint"><i class="fas fa-info-circle"></i> Paste any public image URL</div>
                                    </div>
                                    <div class="fld" style="margin-bottom:0;">
                                        <label>Status <span class="req">*</span></label>
                                        <div class="status-toggle">
                                            <div class="status-opt">
                                                <input type="radio" name="status" id="s_active" value="active"
                                                    <?= $project['status'] === 'active' ? 'checked' : '' ?>>
                                                <label for="s_active" class="active-lbl">
                                                    <i class="fas fa-eye"></i> Active
                                                </label>
                                            </div>
                                            <div class="status-opt">
                                                <input type="radio" name="status" id="s_inactive" value="inactive"
                                                    <?= $project['status'] === 'inactive' ? 'checked' : '' ?>>
                                                <label for="s_inactive" class="inactive-lbl">
                                                    <i class="fas fa-eye-slash"></i> Inactive
                                                </label>
                                            </div>
                                        </div>
                                        <div class="hint" style="margin-top:8px;">
                                            <i class="fas fa-info-circle"></i> Inactive projects are hidden from students
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#475569;margin-bottom:6px;">Preview</div>
                                    <div class="thumb-preview" id="thumbPreview">
                                        <img id="thumbImg" src="" alt="Preview">
                                        <span id="thumbPlaceholder">
                                            <i class="fas fa-image" style="margin-right:5px;"></i> No image URL
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Update Project
                        </button>
                        <a href="manage_projects.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="project_orders.php?project=<?= $id ?>" class="btn-view-orders">
                            <i class="fas fa-shopping-bag"></i> View Orders for this Project
                        </a>
                    </div>

                </form>
            </div><!-- /proj-form-wrap -->

        </div><!-- /content-wrapper -->
    </div><!-- /main -->
</div><!-- /dashboard -->

<?php include("../includes/footer.php"); ?>

<script>
// ── Live tech tag preview ──
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
renderTags();

// ── Live thumbnail preview ──
const thumbInput  = document.getElementById('thumbInput');
const thumbImg    = document.getElementById('thumbImg');
const thumbHolder = document.getElementById('thumbPlaceholder');

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
        thumbHolder.innerHTML = '<i class="fas fa-image" style="margin-right:5px;"></i> No image URL';
    }
}
thumbInput.addEventListener('input', updateThumb);
updateThumb();

// ── Unsaved changes indicator ──
const form       = document.getElementById('editProjectForm');
const changesBar = document.getElementById('changesBar');
let isDirty      = false;

form.addEventListener('change', () => {
    if (!isDirty) {
        isDirty = true;
        changesBar.classList.add('show');
    }
});
form.addEventListener('input', () => {
    if (!isDirty) {
        isDirty = true;
        changesBar.classList.add('show');
    }
});

// Hide changes bar on submit
form.addEventListener('submit', () => { isDirty = false; changesBar.classList.remove('show'); });

// Warn before leaving with unsaved changes
window.addEventListener('beforeunload', (e) => {
    if (isDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>
</body>
</html>