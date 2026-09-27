<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentPath = $_SERVER['PHP_SELF'];
?>

<div class="sidebar" id="sidebar">

    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px;">
        <div style="flex:1;">
            <img 
                src="../logo.jpeg" 
                alt="CourseFlow LMS" 
                style="
                    width:100%;
                    height:auto;
                    object-fit:contain;
                    border-radius:8px;
                    display:block;
                "
            >
        </div>
        <button class="sidebar-close-btn" onclick="closeSidebar()" style="background:transparent; border:none; color:rgba(255,255,255,0.7); font-size:1.2rem; cursor:pointer; padding:5px; margin-left:10px; display:none;">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <style>
        @media (max-width: 768px) {
            .sidebar-close-btn { display: block !important; }
        }
    </style>

    <nav>

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == "admin"): ?>

        <div class="sidebar-section">

            <div class="sidebar-category-title" style="font-size: 0.7rem; color: rgba(255, 255, 255, 0.7); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 14px 6px;"><i class="fas fa-crown"></i> Admin Panel</div>

            <a href="../admin/dashboard.php" class="<?php echo ($currentPage == 'dashboard.php' && strpos($currentPath,'admin') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>

            <a href="../admin/courses.php" class="<?php echo ($currentPage == 'courses.php') ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>Courses</span>
            </a>

            <a href="../admin/lessons.php" class="<?php echo ($currentPage == 'lessons.php' || $currentPage == 'course_lessons_admin.php') ? 'active' : ''; ?>">
                <i class="fas fa-play-circle"></i>
                <span>Lessons</span>
            </a>

            <a href="../admin/users.php" class="<?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>

            <a href="../admin/assign_course.php" class="<?php echo ($currentPage == 'assign_course.php') ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i>
                <span>Assign Courses</span>
            </a>

            <a href="../admin/enrollment_requests.php" class="<?php echo ($currentPage == 'enrollment_requests.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i>
                <span>Enrollments</span>
            </a>
            
            <a href="../admin/fdp_registrations.php" class="<?php echo ($currentPage == 'fdp_registrations.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>FDP Registrations</span>
            </a>

            <a href="../admin/progress.php" class="<?php echo ($currentPage == 'progress.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Progress</span>
            </a>
            
            
<a href="../admin/internship_college_report.php" class="<?php echo ($currentPage == 'internship_college_report.php') ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>Internship reports</span>
            </a>
            <a href="../admin/admin_internship_payments.php" class="<?php echo ($currentPage == 'admin_internship_payments.php') ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>Internship Payments</span>
            </a>

            <!-- NEW STUDENT DATA MENU -->
            <a href="../admin/student_data.php" class="<?php echo ($currentPage == 'student_data.php') ? 'active' : ''; ?>">
                <i class="fas fa-id-card"></i>
                <span>Student Data</span>
            </a>

            <a href="../admin/project_verification.php" class="<?php echo ($currentPage == 'project_verification.php') ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i>
                <span>Project Verification</span>
            </a>

            <!-- Final Year Projects Module -->
            <a href="../admin/manage_projects.php" class="<?php echo (in_array($currentPage, ['manage_projects.php','edit_project.php','add_project.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i>
                <span>Final Year Projects</span>
            </a>

            <a href="../admin/project_orders.php" class="<?php echo ($currentPage == 'project_orders.php') ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i>
                <span>Project Orders</span>
                <?php
                // Show pending badge
                $po_badge = $conn->query("SELECT COUNT(*) AS c FROM project_orders WHERE payment_status='pending'");
                $po_cnt   = $po_badge ? (int)$po_badge->fetch_assoc()['c'] : 0;
                if ($po_cnt > 0):
                ?>
                <span style="margin-left:auto;background:#dc2626;color:#fff;font-size:.6rem;font-weight:800;padding:1px 6px;border-radius:999px;min-width:18px;text-align:center;"><?= $po_cnt ?></span>
                <?php endif; ?>
            </a>

            <a href="../admin/certificate_template.php" class="<?php echo ($currentPage == 'certificate_template.php') ? 'active' : ''; ?>">
                <i class="fas fa-certificate"></i>
                <span>Certificate Template</span>
            </a>

            <a href="../admin/profile.php" class="<?php echo ($currentPage == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i>
                <span>My Profile</span>
            </a>

        </div>
        
        
        
        
         <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == "finance"): ?>

        <div class="sidebar-section">
           <div class="sidebar-category-title" style="font-size: 0.7rem; color: rgba(255, 255, 255, 0.7); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 14px 6px;"><i class="fas fa-rupee-sign"></i> Finance Panel</div>

        <a href="../finance/dashboard.php" class="<?php echo ($currentPage == 'dashboard.php' && strpos($currentPath,'finance') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
        </a>

       <a href="../finance/payments.php" class="<?php echo ($currentPage == 'payments.php') ? 'active' : ''; ?>">
        <i class="fas fa-credit-card"></i>
        <span>Payments</span>
       </a>
       
       <a href="../finance/course_revenue.php" class="<?php echo ($currentPage == 'course_revenue.php') ? 'active' : ''; ?>">
        <i class="fas fa-chart-bar"></i>
        <span>Course Revenue</span>
       </a>
       <a href="../finance/internship_payments.php" class="<?php echo ($currentPage == 'admin_internship_payments.php') ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>Internship Payments</span>
            </a>

      <a href="../finance/profile.php" class="<?php echo ($currentPage == 'profile.php') ? 'active' : ''; ?>">
        <i class="fas fa-user-circle"></i>
        <span>My Profile</span>
      </a>

     </div>
        
        
        
        

        <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == "trainer"): ?>

        <div class="sidebar-section">

            <div class="sidebar-category-title" style="font-size: 0.7rem; color: rgba(255, 255, 255, 0.7); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 14px 6px;"><i class="fas fa-chalkboard-teacher"></i> Trainer Tools</div>

            <a href="../trainer/dashboard.php" class="<?php echo ($currentPage == 'dashboard.php' && strpos($currentPath,'trainer') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>

            <a href="../trainer/lessons.php" class="<?php echo ($currentPage == 'lessons.php') ? 'active' : ''; ?>">
                <i class="fas fa-play-circle"></i>
                <span>Manage Lessons</span>
            </a>

            <a href="../trainer/progress.php" class="<?php echo ($currentPage == 'progress.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Student Progress</span>
            </a>

            <a href="../trainer/add_quiz.php" class="<?php echo ($currentPage == 'add_quiz.php') ? 'active' : ''; ?>">
                <i class="fas fa-question-circle"></i>
                <span>Create Quiz</span>
            </a>

            <a href="../trainer/send_quiz.php" class="<?php echo ($currentPage == 'send_quiz.php') ? 'active' : ''; ?>">
                <i class="fas fa-paper-plane"></i>
                <span>Send Quiz</span>
            </a>

            <a href="../trainer/live_classes.php" class="<?php echo (in_array($currentPage, ['live_classes.php','schedule_live_class.php','edit_live_class.php'])) ? 'active' : ''; ?>">
                <i class="fas fa-video"></i>
                <span>Live Classes</span>
            </a>

            <a href="../trainer/projects.php" class="<?php echo ($currentPage == 'projects.php') ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i>
                <span>Projects</span>
            </a>

            <a href="../trainer/notes.php" class="<?php echo ($currentPage == 'notes.php') ? 'active' : ''; ?>">
                <i class="fas fa-sticky-note"></i>
                <span>Notes & Materials</span>
            </a>

            <a href="../trainer/profile.php" class="<?php echo ($currentPage == 'profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>

        </div>

        <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == "student"): ?>

        <div class="sidebar-section">
            <div class="sidebar-category-title" style="font-size: 0.7rem; color: rgba(255, 255, 255, 0.7); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 14px 6px;">Home</div>
            <a href="../student/dashboard.php" class="<?php echo ($currentPage == 'dashboard.php' && strpos($currentPath,'student') !== false) ? 'active' : ''; ?>">
                <i class="far fa-compass"></i>
                <span>Dashboard</span>
            </a>
            <a href="../student/profile.php" class="<?php echo ($currentPage == 'profile.php') ? 'active' : ''; ?>">
                <i class="far fa-user-circle"></i>
                <span>My Profile</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-category-title" style="font-size: 0.7rem; color: rgba(255, 255, 255, 0.7); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 14px 6px;">Learning</div>
            <a href="../student/coding_challenges.php" class="<?php echo ($currentPage == 'coding_challenges.php' || $currentPage == 'coding_challenge_detail.php') ? 'active' : ''; ?>">
                <i class="fas fa-code"></i>
                <span>Coding Challenges</span>
            </a>
            <a href="../student/live_classes.php" class="<?php echo ($currentPage == 'live_classes.php') ? 'active' : ''; ?>">
                <i class="fas fa-video"></i>
                <span>Live Classes</span>
            </a>
            <a href="../student/notes.php" class="<?php echo ($currentPage == 'notes.php') ? 'active' : ''; ?>">
                <i class="far fa-sticky-note"></i>
                <span>Notes</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-category-title" style="font-size: 0.7rem; color: rgba(255, 255, 255, 0.7); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 14px 6px;">Assessments & Projects</div>
            <a href="../student/assignments.php" class="<?php echo ($currentPage == 'assignments.php') ? 'active' : ''; ?>">
                <i class="far fa-file-alt"></i>
                <span>Assignments</span>
            </a>
            <a href="../student/project_submission.php" class="<?php echo ($currentPage == 'project_submission.php') ? 'active' : ''; ?>">
                <i class="fas fa-paper-plane"></i>
                <span>Project Submission</span>
            </a>
            <a href="../student/certificates.php" class="<?php echo ($currentPage == 'certificates.php') ? 'active' : ''; ?>">
                <i class="fas fa-certificate"></i>
                <span>My Certificates</span>
            </a>
            <a href="../student/pending_payments.php" class="<?php echo ($currentPage == 'pending_payments.php') ? 'active' : ''; ?>">
                <i class="far fa-clock"></i>
                <span>Pending Payments</span>
            </a>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-category-title" style="font-size: 0.7rem; color: rgba(255, 255, 255, 0.7); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 14px 6px;">Reports</div>
            <a href="../student/reports.php" class="<?php echo ($currentPage == 'reports.php') ? 'active' : ''; ?>">
                <i class="far fa-chart-bar"></i>
                <span>Student Reports</span>
            </a>
        </div>

        <?php endif; ?>

        <!-- LOGOUT -->

        <div class="sidebar-section">

            <a href="../logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>

        </div>

    </nav>

</div>