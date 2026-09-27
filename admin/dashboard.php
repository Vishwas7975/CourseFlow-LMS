<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("admin");

// Auto-run migrations silently if columns are missing
$conn->query("ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected') DEFAULT 'approved'");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','inactive') DEFAULT 'active'");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS mobile VARCHAR(20) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS college VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS passout_year VARCHAR(10) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS hall_ticket VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE lessons ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL");
$conn->query("CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Auto-create final_year_projects table
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
 
// Auto-create project_orders table
$conn->query("
    CREATE TABLE IF NOT EXISTS project_orders (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        project_id     INT           NOT NULL,
        student_name   VARCHAR(150)  NOT NULL,
        email          VARCHAR(150)  NOT NULL,
        mobile         VARCHAR(20)   NOT NULL,
        hall_ticket    VARCHAR(100)  NOT NULL,
        college_name   VARCHAR(200)  NOT NULL,
        department     VARCHAR(150)  NOT NULL,
        utr_number     VARCHAR(60)   NOT NULL,
        terminal_name  VARCHAR(60)   DEFAULT 'Terminal 1',
        terminal_upi   VARCHAR(80),
        amount         DECIMAL(10,2) NOT NULL,
        payment_status ENUM('pending','approved','rejected') DEFAULT 'pending',
        admin_note     VARCHAR(500)  DEFAULT NULL,
        ordered_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
        reviewed_at    DATETIME      DEFAULT NULL,
        INDEX idx_project (project_id),
        INDEX idx_status  (payment_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
 
// Count pending project orders
$proj_orders_check = $conn->query("SHOW TABLES LIKE 'project_orders'");
$pending_proj_orders = 0;
if ($proj_orders_check && $proj_orders_check->num_rows > 0) {
    $r = $conn->query("SELECT COUNT(*) as c FROM project_orders WHERE payment_status='pending'");
    $pending_proj_orders = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

// Analytics
$total_courses   = $conn->query("SELECT COUNT(*) as c FROM courses")->fetch_assoc()['c'];
$total_students  = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student'")->fetch_assoc()['c'];
$total_trainers  = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='trainer'")->fetch_assoc()['c'];
$avg_progress_res = $conn->query("SELECT AVG(progress) as c FROM enrollments");
$avg_progress    = $avg_progress_res ? $avg_progress_res->fetch_assoc()['c'] : 0;
$completion_rate = $avg_progress ? round($avg_progress) : 0;
$pending_res     = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE status='pending'");
$pending_enrollments = $pending_res ? $pending_res->fetch_assoc()['c'] : 0;

// Course payments pending
$pending_payments_res = $conn->query("SELECT COUNT(*) as c FROM payments WHERE status='pending'");
$pending_payments = $pending_payments_res ? $pending_payments_res->fetch_assoc()['c'] : 0;

// FIX 1: Internship payments pending (both 'pending' submitted + 'payment_later' outstanding)
$ip_table_check = $conn->query("SHOW TABLES LIKE 'internship_payments'");
$pending_internship     = 0;
$pay_later_internship   = 0;
$total_internship_action = 0;
if ($ip_table_check && $ip_table_check->num_rows > 0) {
    $ip_pend_res = $conn->query("SELECT COUNT(*) as c FROM internship_payments WHERE payment_status='pending'");
    $pending_internship = $ip_pend_res ? (int)$ip_pend_res->fetch_assoc()['c'] : 0;

    $ip_later_res = $conn->query("SELECT COUNT(*) as c FROM internship_payments WHERE payment_status='payment_later'");
    $pay_later_internship = $ip_later_res ? (int)$ip_later_res->fetch_assoc()['c'] : 0;

    $total_internship_action = $pending_internship + $pay_later_internship;
}

// Unread notifications
$unread_notif_res = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE is_read = 0 AND for_role = 'admin'");
$unread_notifs    = $unread_notif_res ? $unread_notif_res->fetch_assoc()['c'] : 0;

// Workshop registrations
$ws_total_res  = $conn->query("SELECT COUNT(*) as c FROM workshop_registrations");
$ws_total      = $ws_total_res ? $ws_total_res->fetch_assoc()['c'] : 0;
$ws_unread_res = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE type='workshop' AND for_role='admin' AND is_read=0");
$ws_unread     = $ws_unread_res ? $ws_unread_res->fetch_assoc()['c'] : 0;

// Internship Registrations stats
$ir_table_check = $conn->query("SHOW TABLES LIKE 'internship_registrations'");
$total_internship_regs   = 0;
$new_internship_regs     = 0; // registered in last 7 days
if ($ir_table_check && $ir_table_check->num_rows > 0) {
    $ir_total_res = $conn->query("SELECT COUNT(*) as c FROM internship_registrations");
    $total_internship_regs = $ir_total_res ? (int)$ir_total_res->fetch_assoc()['c'] : 0;
    $ir_new_res = $conn->query("SELECT COUNT(*) as c FROM internship_registrations WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $new_internship_regs = $ir_new_res ? (int)$ir_new_res->fetch_assoc()['c'] : 0;
    // Notification count for new registrations
    $ir_notif_res = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE type='new_registration' AND for_role='admin' AND is_read=0");
    $ir_unread_notifs = $ir_notif_res ? (int)$ir_notif_res->fetch_assoc()['c'] : 0;
}

$page_title = "Admin Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CourseFlow LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <style>
    /* Workshop action card */
    .action-card-ws {
        position: relative;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 10px;
        padding: 20px 14px;
        border-radius: var(--radius-lg, 14px);
        background: linear-gradient(135deg, #eff6ff, #f0f4ff);
        border: 1.5px solid #bfdbfe;
        color: #1d4ed8;
        text-decoration: none;
        text-align: center;
        font-weight: 700; font-size: .85rem;
        transition: all .22s;
        box-shadow: 0 3px 12px rgba(37,99,235,.10);
    }
    .action-card-ws:hover {
        background: linear-gradient(135deg, #dbeafe, #e0e7ff);
        border-color: #93c5fd;
        transform: translateY(-3px);
        box-shadow: 0 8px 22px rgba(37,99,235,.18);
        color: #1e40af;
    }
    .action-card-ws i { font-size: 1.5rem; color: #2563eb; }

    /* Internship action card */
    .action-card-intern {
        position: relative;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 10px;
        padding: 20px 14px;
        border-radius: var(--radius-lg, 14px);
        background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
        border: 1.5px solid #86efac;
        color: #15803d;
        text-decoration: none;
        text-align: center;
        font-weight: 700; font-size: .85rem;
        transition: all .22s;
        box-shadow: 0 3px 12px rgba(22,163,74,.10);
    }
    .action-card-intern:hover {
        background: linear-gradient(135deg, #dcfce7, #d1fae5);
        border-color: #4ade80;
        transform: translateY(-3px);
        box-shadow: 0 8px 22px rgba(22,163,74,.18);
        color: #166534;
    }
    .action-card-intern i { font-size: 1.5rem; color: #16a34a; }

    .ws-badge-new {
        position: absolute; top: 9px; right: 9px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff; font-size: .6rem; font-weight: 800;
        padding: 2px 8px; border-radius: 999px;
        letter-spacing: .05em; animation: pulse 2s infinite;
        white-space: nowrap;
    }
    .ws-badge-count {
        position: absolute; top: 9px; right: 9px;
        background: #2563eb; color: #fff;
        font-size: .65rem; font-weight: 800;
        padding: 2px 7px; border-radius: 999px;
    }
    .intern-badge-pending {
        position: absolute; top: 9px; right: 9px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff; font-size: .6rem; font-weight: 800;
        padding: 2px 8px; border-radius: 999px;
        letter-spacing: .05em; animation: pulse 2s infinite;
        white-space: nowrap;
    }
    /* Internship Registrations action card */
    .action-card-intern-reg {
        position: relative;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 10px;
        padding: 20px 14px;
        border-radius: var(--radius-lg, 14px);
        background: linear-gradient(135deg, #fdf4ff, #faf5ff);
        border: 1.5px solid #e9d5ff;
        color: #7c3aed;
        text-decoration: none;
        text-align: center;
        font-weight: 700; font-size: .85rem;
        transition: all .22s;
        box-shadow: 0 3px 12px rgba(124,58,237,.10);
    }
    .action-card-intern-reg:hover {
        background: linear-gradient(135deg, #f5f3ff, #ede9fe);
        border-color: #c4b5fd;
        transform: translateY(-3px);
        box-shadow: 0 8px 22px rgba(124,58,237,.18);
        color: #6d28d9;
    }
    .action-card-intern-reg i { font-size: 1.5rem; color: #7c3aed; }
    .intern-reg-badge-new {
        position: absolute; top: 9px; right: 9px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff; font-size: .6rem; font-weight: 800;
        padding: 2px 8px; border-radius: 999px;
        letter-spacing: .05em; animation: pulse 2s infinite;
        white-space: nowrap;
    }
    .intern-reg-badge-count {
        position: absolute; top: 9px; right: 9px;
        background: #7c3aed; color: #fff;
        font-size: .65rem; font-weight: 800;
        padding: 2px 7px; border-radius: 999px;
    }

    /* ── Mobile: action cards stack cleanly on small screens ── */
    @media(max-width:480px){
        /* Action cards: reduce padding so text fits on 320px */
        .action-card-ws,
        .action-card-intern,
        .action-card-intern-reg {
            padding:16px 10px;
            font-size:.78rem;
        }
        .action-card-ws i,
        .action-card-intern i,
        .action-card-intern-reg i { font-size:1.25rem; }
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
                <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>! Here's your platform overview.</p>
            </div>

            <!-- Analytics Cards -->
            <div class="dashboard-cards">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <h3>Total Courses</h3>
                    <div class="stat-value"><?php echo $total_courses; ?></div>
                    <p class="stat-desc">Active courses on platform</p>
                    <a href="courses.php" class="btn btn-primary btn-sm"><i class="fas fa-arrow-right"></i> Manage</a>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>Total Students</h3>
                    <div class="stat-value"><?php echo $total_students; ?></div>
                    <p class="stat-desc">Enrolled learners</p>
                    <a href="users.php" class="btn btn-success btn-sm"><i class="fas fa-arrow-right"></i> View All</a>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <h3>Total Trainers</h3>
                    <div class="stat-value"><?php echo $total_trainers; ?></div>
                    <p class="stat-desc">Active instructors</p>
                    <a href="users.php" class="btn btn-sm" style="background:var(--warning-light);color:#D97706;border:1px solid rgba(245,158,11,0.3)"><i class="fas fa-arrow-right"></i> View All</a>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Completion Rate</h3>
                    <div class="stat-value"><?php echo $completion_rate; ?>%</div>
                    <p class="stat-desc">Average student progress</p>
                    <a href="progress.php" class="btn btn-danger btn-sm"><i class="fas fa-arrow-right"></i> Reports</a>
                </div>
                <!-- Course Payments -->
                <div class="stat-card" style="border-left:4px solid var(--primary);position:relative">
                    <?php if($unread_notifs > 0): ?>
                    <span style="position:absolute;top:14px;right:14px;background:#ef4444;color:#fff;font-size:0.62rem;font-weight:800;padding:3px 8px;border-radius:999px;letter-spacing:.04em;animation:pulse 2s infinite">NEW</span>
                    <?php endif; ?>
                    <div class="stat-icon" style="background:var(--primary-light);color:var(--primary)"><i class="fas fa-money-check-alt"></i></div>
                    <h3>Course Payments</h3>
                    <div class="stat-value"><?php echo $pending_payments; ?></div>
                    <p class="stat-desc">Pending approvals</p>
                    <a href="payments.php?filter=pending" class="btn btn-primary btn-sm"><i class="fas fa-arrow-right"></i> Review</a>
                </div>
                <!-- FIX 1: Internship Payments stat card -->
                <div class="stat-card" style="border-left:4px solid #16a34a;position:relative">
                    <?php if($pending_internship > 0): ?>
                    <span style="position:absolute;top:14px;right:14px;background:#ef4444;color:#fff;font-size:0.62rem;font-weight:800;padding:3px 8px;border-radius:999px;letter-spacing:.04em;animation:pulse 2s infinite">NEW</span>
                    <?php endif; ?>
                    <div class="stat-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-briefcase"></i></div>
                    <h3>Internship Payments</h3>
                    <div class="stat-value"><?php echo $pending_internship; ?></div>
                    <p class="stat-desc"><?php echo $pay_later_internship; ?> pay-later outstanding</p>
                    <a href="admin_internship_payments.php?filter=pending" class="btn btn-sm" style="background:#dcfce7;color:#166534;border:1px solid #86efac"><i class="fas fa-arrow-right"></i> Review</a>
                </div>
                <!-- Internship Registrations stat card -->
                <div class="stat-card" style="border-left:4px solid #7c3aed;position:relative">
                    <?php if(!empty($ir_unread_notifs) && $ir_unread_notifs > 0): ?>
                    <span style="position:absolute;top:14px;right:14px;background:#ef4444;color:#fff;font-size:0.62rem;font-weight:800;padding:3px 8px;border-radius:999px;letter-spacing:.04em;animation:pulse 2s infinite">NEW</span>
                    <?php endif; ?>
                    <div class="stat-icon" style="background:#fdf4ff;color:#7c3aed"><i class="fas fa-user-plus"></i></div>
                    <h3>Internship Registrations</h3>
                    <div class="stat-value"><?php echo $total_internship_regs; ?></div>
                    <p class="stat-desc"><?php echo $new_internship_regs; ?> new this week</p>
                    <a href="internship_college_report.php" class="btn btn-sm" style="background:#fdf4ff;color:#7c3aed;border:1px solid #e9d5ff"><i class="fas fa-arrow-right"></i> View All</a>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section-wrapper">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="quick-actions-grid">
                    <a href="courses.php" class="action-card">
                        <i class="fas fa-book"></i><div>Manage Courses</div>
                    </a>
                    <a href="lessons.php" class="action-card">
                        <i class="fas fa-play-circle"></i><div>Manage Lessons</div>
                    </a>
                    <a href="assign_course.php" class="action-card">
                        <i class="fas fa-tasks"></i><div>Assign Course</div>
                    </a>
                    <a href="enrollment_requests.php" class="action-card" style="position:relative">
                        <i class="fas fa-user-check"></i>
                        <div>Enrollments</div>
                        <?php if($pending_enrollments > 0): ?>
                        <span style="position:absolute;top:10px;right:10px;background:var(--danger);color:#fff;font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:var(--radius-full)"><?php echo $pending_enrollments; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="users.php" class="action-card">
                        <i class="fas fa-users"></i><div>Manage Users</div>
                    </a>
                    <a href="payments.php" class="action-card" style="position:relative">
                        <i class="fas fa-money-check-alt"></i>
                        <div>Course Payments</div>
                        <?php if($pending_payments > 0): ?>
                        <span style="position:absolute;top:10px;right:10px;background:var(--danger);color:#fff;font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:var(--radius-full)"><?php echo $pending_payments; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="progress.php" class="action-card">
                        <i class="fas fa-chart-bar"></i><div>Progress</div>
                    </a>

                    <!-- FIX 6: Internship Payments quick action card -->
                    <a href="admin_internship_payments.php" class="action-card-intern">
                        <?php if($pending_internship > 0): ?>
                            <span class="intern-badge-pending"><i class="fas fa-bell"></i> <?php echo $pending_internship; ?> NEW</span>
                        <?php elseif($total_internship_action > 0): ?>
                            <span class="intern-badge-count"><?php echo $total_internship_action; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-briefcase"></i>
                        <div>Internship Payments</div>
                    </a>

                    <!-- Internship Registrations quick action card -->
                    <a href="internship_college_report.php" class="action-card-intern-reg">
                        <?php if(!empty($ir_unread_notifs) && $ir_unread_notifs > 0): ?>
                            <span class="intern-reg-badge-new"><i class="fas fa-bell"></i> <?php echo $ir_unread_notifs; ?> NEW</span>
                        <?php elseif($total_internship_regs > 0): ?>
                            <span class="intern-reg-badge-count"><?php echo $total_internship_regs; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-user-plus"></i>
                        <div>Internship Registrations</div>
                    </a>

                    <!-- Workshop Registrations -->
                    <a href="workshop_registrations.php" class="action-card-ws">
                        <?php if($ws_unread > 0): ?>
                            <span class="ws-badge-new"><i class="fas fa-bell"></i> <?php echo $ws_unread; ?> NEW</span>
                        <?php elseif($ws_total > 0): ?>
                            <span class="ws-badge-count"><?php echo $ws_total; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-chalkboard-teacher"></i>
                        <div>Workshop Registrations</div>
                    </a>
                    
                    <div class="action-card" style="background:linear-gradient(135deg,#fff7ed,#ffedd5);border:1.5px solid #fed7aa;">
    <div class="action-card-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">
        <i class="fas fa-folder-open"></i>
    </div>
    <div class="action-card-content">
        <h3 style="color:#92400e;">Final Year Projects</h3>
        <div class="action-card-links" style="display:flex;flex-direction:column;gap:6px;margin-top:8px;">
            <a href="manage_projects.php" style="display:inline-flex;align-items:center;gap:6px;font-size:.82rem;font-weight:700;color:#c2410c;text-decoration:none;">
                <i class="fas fa-layer-group" style="font-size:.75rem;"></i> Manage Projects
            </a>
            <a href="project_orders.php" style="display:inline-flex;align-items:center;gap:6px;font-size:.82rem;font-weight:700;color:#c2410c;text-decoration:none;">
                <i class="fas fa-shopping-bag" style="font-size:.75rem;"></i> View Orders
                <?php if ($pending_proj_orders > 0): ?>
                <span style="
                    background:#ef4444;color:#fff;
                    font-size:.62rem;font-weight:800;
                    padding:2px 7px;border-radius:999px;
                    animation:pulse 1.4s infinite;
                    display:inline-block;
                "><?= $pending_proj_orders ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</div>

                </div>
            </div>

        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>