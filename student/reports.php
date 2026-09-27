<?php
session_start();
include("../config/db.php");
include("../includes/auth_check.php");

requireLogin($conn);
requireRole("student");

$user_id = $_SESSION['user_id'];
$page_title = "Student Reports";

// Fetch Leaderboard
$leaderboard = [];
$res_lb = $conn->query("
    SELECT u.name, s.points 
    FROM student_stats s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.points DESC
    LIMIT 10
");
if ($res_lb) {
    while ($row = $res_lb->fetch_assoc()) {
        $leaderboard[] = $row;
    }
}

// Stats for chart
$res_stats = $conn->query("SELECT points, streak_days FROM student_stats WHERE user_id = " . (int)$user_id);
$my_points = 0;
if ($res_stats && $row_stats = $res_stats->fetch_assoc()) {
    $my_points = (int)$row_stats['points'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - CourseFlow LMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .report-card { background: #fff; border: 1px solid var(--border-light); border-radius: 12px; box-shadow: var(--shadow-xs); padding: 24px; }
        .tabs { display: flex; gap: 12px; border-bottom: 2px solid var(--border-light); margin-bottom: 20px; }
        .tab { padding: 10px 16px; cursor: pointer; color: var(--text-secondary); font-weight: 600; font-size: 0.9rem; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab.active { color: var(--primary); border-bottom-color: var(--primary); }
        .leaderboard-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border-light); }
        .leaderboard-item:last-child { border-bottom: none; }
        @media (max-width: 900px) { .report-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="dashboard">
    <?php include("../includes/sidebar.php"); ?>

    <div class="main">
        <?php include("../includes/topbar.php"); ?>

        <div class="content-wrapper">
            <h1 style="font-size:1.6rem; margin-bottom:24px;">Student Reports</h1>

            <div class="report-grid">
                <!-- Analytics Section -->
                <div class="report-card">
                    <div class="tabs">
                        <div class="tab active">Overview</div>
                        <div class="tab">Activity</div>
                        <div class="tab">Performance</div>
                    </div>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:20px;">
                            <div style="color:#64748b; font-size:0.8rem; font-weight:700; text-transform:uppercase; margin-bottom:8px;">Total Score</div>
                            <div style="font-size:2rem; font-weight:800; color:#1e293b;"><?= $my_points ?></div>
                        </div>
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:20px;">
                            <div style="color:#64748b; font-size:0.8rem; font-weight:700; text-transform:uppercase; margin-bottom:8px;">Eligibility</div>
                            <div style="font-size:1.2rem; font-weight:800; color:#22c55e;">Qualified</div>
                            <div style="font-size:0.75rem; color:#64748b; margin-top:4px;">You meet the 2000 points requirement for placements.</div>
                        </div>
                    </div>

                    <h3 style="font-size:1rem; margin-bottom:16px;">Learning Heatmap</h3>
                    <div style="position:relative; height:250px; width:100%;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>

                <!-- Leaderboard Section -->
                <div class="report-card">
                    <h3 style="font-size:1.1rem; margin-bottom:16px; display:flex; align-items:center; gap:8px;"><i class="fas fa-trophy" style="color:#eab308;"></i> Leaderboard</h3>
                    <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:12px 16px; margin-bottom:20px; font-size:0.8rem; color:#92400e;">
                        Compete with your peers to reach the top!
                    </div>

                    <?php if (empty($leaderboard)): ?>
                        <div style="text-align:center; padding:20px; color:#94a3b8;">No data available yet.</div>
                    <?php else: ?>
                        <?php foreach($leaderboard as $idx => $user): ?>
                            <div class="leaderboard-item">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="width:28px; height:28px; border-radius:50%; background:<?= $idx < 3 ? '#fef08a' : '#f1f5f9' ?>; color:<?= $idx < 3 ? '#ca8a04' : '#64748b' ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.8rem;">
                                        <?= $idx + 1 ?>
                                    </div>
                                    <div style="font-size:0.9rem; font-weight:600; color:#1e293b;"><?= htmlspecialchars($user['name']) ?></div>
                                </div>
                                <div style="font-weight:800; color:#3b82f6; font-size:0.9rem;"><?= $user['points'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('activityChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Points Earned',
                data: [12, 19, 3, 5, 2, 3, 15],
                backgroundColor: '#3b82f6',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
</script>

</body>
</html>