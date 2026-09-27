<?php
// Suppress connection errors from showing raw PHP errors
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli("localhost", "your_db_username", "your_db_password_here", "courseflow_lms");

if ($conn->connect_error) {
    $error_code = $conn->connect_errno;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Error - CourseFlow LMS</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
                min-height: 100vh;
                display: flex; align-items: center; justify-content: center;
                background: linear-gradient(135deg, #FFF5F5 0%, #FFF 50%, #FFF5F5 100%);
                padding: 24px 16px;
            }
            .card {
                background: #fff;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(200,0,0,0.08);
                padding: 40px;
                border: 1px solid #FECACA;
                max-width: 520px; width: 100%;
                text-align: center;
            }
            .icon {
                width: 70px; height: 70px;
                background: #FEE2E2;
                border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                font-size: 1.8rem; color: #EF4444;
                margin: 0 auto 20px;
            }
            h1 { font-size: 1.3rem; font-weight: 800; color: #1F2D3D; margin-bottom: 8px; }
            p  { font-size: 0.9rem; color: #6B7A90; line-height: 1.7; margin-bottom: 20px; }
            .steps {
                background: #FFF5F5;
                border-radius: 12px;
                padding: 20px 24px;
                text-align: left;
                border: 1px solid #FECACA;
                margin-bottom: 24px;
            }
            .steps h3 { font-size: 0.85rem; font-weight: 700; color: #991B1B; margin-bottom: 12px; }
            .step {
                display: flex; align-items: flex-start; gap: 10px;
                margin-bottom: 10px; font-size: 0.85rem; color: #374151;
            }
            .step:last-child { margin-bottom: 0; }
            .step .num {
                min-width: 22px; height: 22px;
                background: #EF4444; color: #fff;
                border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                font-size: 0.7rem; font-weight: 700; flex-shrink: 0;
            }
            code {
                background: #F3F4F6; padding: 2px 6px;
                border-radius: 4px; font-size: 0.8rem;
                color: #EF4444; font-family: monospace;
            }
            .refresh {
                display: inline-flex; align-items: center; gap: 8px;
                padding: 11px 24px;
                background: linear-gradient(135deg, #EF4444, #DC2626);
                color: #fff; border-radius: 10px;
                font-size: 0.9rem; font-weight: 700;
                text-decoration: none;
                box-shadow: 0 4px 14px rgba(239,68,68,0.3);
                transition: opacity 0.2s;
            }
            .refresh:hover { opacity: 0.9; }
            .err-code { font-size: 0.78rem; color: #9CA3AF; margin-top: 16px; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon"><i class="fas fa-database"></i></div>
            <h1>Database Connection Failed</h1>
            <p>CourseFlow LMS could not connect to MySQL. This usually means <strong>MySQL is not running</strong> in XAMPP.</p>

            <div class="steps">
                <h3><i class="fas fa-wrench"></i> &nbsp;How to fix this:</h3>
                <div class="step">
                    <div class="num">1</div>
                    <div>Open <strong>XAMPP Control Panel</strong> on your computer.</div>
                </div>
                <div class="step">
                    <div class="num">2</div>
                    <div>Click <strong>Start</strong> next to <strong>Apache</strong> and <strong>MySQL</strong>. Both must show green.</div>
                </div>
                <div class="step">
                    <div class="num">3</div>
                    <div>Open <strong>phpMyAdmin</strong> → create a database named <code>courseflow_lms</code> if it doesn't exist.</div>
                </div>
                <div class="step">
                    <div class="num">4</div>
                    <div>Import <code>schema.sql</code> from the project folder into <code>courseflow_lms</code>.</div>
                </div>
                <div class="step">
                    <div class="num">5</div>
                    <div>Click the button below to retry.</div>
                </div>
            </div>

            <a href="javascript:location.reload()" class="refresh">
                <i class="fas fa-redo"></i> Retry Connection
            </a>
            <div class="err-code">Error <?php echo $error_code; ?>: <?php echo htmlspecialchars($conn->connect_error); ?></div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ── Ensure shared tables exist (runs once on every request via db.php) ──
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    type        VARCHAR(50)  NOT NULL DEFAULT 'general',
    for_role    VARCHAR(20)  NOT NULL DEFAULT 'admin',
    for_user_id INT          DEFAULT NULL,
    title       VARCHAR(255) NOT NULL,
    message     TEXT         NOT NULL,
    link        VARCHAR(500) DEFAULT NULL,
    is_read     TINYINT(1)   DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add columns if upgrading from older schema (safe to run repeatedly)
@$conn->query("ALTER TABLE notifications ADD COLUMN for_role    VARCHAR(20) NOT NULL DEFAULT 'admin'");
@$conn->query("ALTER TABLE notifications ADD COLUMN for_user_id INT DEFAULT NULL");
?>

