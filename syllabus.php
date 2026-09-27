<?php
/* ── Public page — no login required ── */
include("config/db.php");

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

/* ── Fetch all courses that have a syllabus ── */
$result = $conn->query("
    SELECT c.id, c.title, c.description, c.image, c.price,
           cs.pdf_path, cs.file_name, cs.uploaded_at
    FROM courses c
    INNER JOIN course_syllabus cs ON cs.course_id = c.id
    ORDER BY c.created_at DESC
");

$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

/* ── Site base URL (needed for Google Docs viewer on mobile) ── */
$site_base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Programs – CourseFlow</title>
    <meta name="description" content="Explore our course curriculum at CourseFlow. Browse detailed syllabi for all programs.">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary:        #4DA8FF;
            --primary-dark:   #2D7DD2;
            --primary-deeper: #1A5EA8;
            --primary-light:  #EBF5FF;
            --primary-glow:   rgba(77,168,255,0.18);
            --bg:             #F5FAFF;
            --bg-alt:         #EDF4FD;
            --card:           #FFFFFF;
            --text-primary:   #1F2D3D;
            --text-secondary: #6B7A90;
            --text-muted:     #A0AEBB;
            --success:        #22C55E;
            --success-light:  #DCFCE7;
            --warning:        #F59E0B;
            --warning-light:  #FEF3C7;
            --danger:         #EF4444;
            --danger-light:   #FEE2E2;
            --border:         #E2EAF4;
            --border-light:   #F0F5FC;
            --shadow-xs:  0 1px 3px rgba(31,45,61,0.06);
            --shadow-sm:  0 2px 8px rgba(31,45,61,0.08);
            --shadow-md:  0 4px 16px rgba(31,45,61,0.10);
            --shadow-lg:  0 8px 32px rgba(31,45,61,0.12);
            --shadow-blue:0 4px 20px rgba(77,168,255,0.28);
            --radius-sm:  8px;
            --radius-md:  12px;
            --radius-lg:  16px;
            --radius-xl:  24px;
            --radius-full:999px;
            --transition: 0.25s ease;
            --nav-h: 68px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }
        a { color: var(--primary-dark); text-decoration: none; transition: color var(--transition); }
        a:hover { color: var(--primary); }
        h1,h2,h3,h4,h5,h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-primary);
            line-height: 1.25;
            font-weight: 700;
        }

        /* ══ NAVBAR ══ */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            height: var(--nav-h);
            background: rgba(255,255,255,0.96);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-xs);
            display: flex;
            align-items: center;
            padding: 0 32px;
            gap: 20px;
        }
        .nav-brand img {
            height: 46px;
            width: auto;
            border-radius: var(--radius-sm);
            object-fit: contain;
            display: block;
        }
        .nav-divider {
            width: 1px;
            height: 28px;
            background: var(--border);
            flex-shrink: 0;
        }
        .nav-page-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .88rem;
            font-weight: 700;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
        }
        .nav-page-title i { color: var(--primary); }
        .nav-spacer { flex: 1; }
        .nav-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: var(--radius-full);
            background: var(--primary-light);
            color: var(--primary-dark);
            font-size: .78rem;
            font-weight: 700;
            border: 1.5px solid rgba(77,168,255,.2);
            white-space: nowrap;
            flex-shrink: 0;
        }
        @media (max-width: 640px) {
            .navbar         { padding: 0 16px; gap: 12px; }
            .nav-divider,
            .nav-page-title { display: none; }
            .nav-count-badge{ font-size: .72rem; padding: 5px 10px; }
        }

        /* ══ HERO ══ */
        .hero {
            background: linear-gradient(135deg, var(--primary-deeper) 0%, var(--primary-dark) 50%, #1d6fbf 100%);
            padding: 56px 32px 48px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 20% 50%, rgba(255,255,255,0.06) 0%, transparent 70%),
                radial-gradient(ellipse 40% 60% at 80% 20%, rgba(255,255,255,0.04) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-inner { position: relative; max-width: 680px; margin: 0 auto; }
        .hero h1 {
            color: #fff;
            font-size: clamp(1.7rem, 4vw, 2.5rem);
            font-weight: 900;
            letter-spacing: -.03em;
            margin-bottom: 12px;
            line-height: 1.15;
        }
        .hero h1 span {
            background: linear-gradient(90deg, #93C5FD, #BAE6FD);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero p {
            color: rgba(255,255,255,.72);
            font-size: .95rem;
            margin-bottom: 32px;
        }

        /* ══ SEARCH + FILTER ══ */
        .search-filter-bar {
            display: flex;
            gap: 10px;
            max-width: 640px;
            margin: 0 auto;
            flex-wrap: wrap;
        }
        .search-wrap {
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: var(--radius-full);
            padding: 10px 20px;
            transition: all var(--transition);
            box-shadow: 0 4px 20px rgba(0,0,0,.12);
        }
        .search-wrap:focus-within {
            border-color: #93C5FD;
            box-shadow: 0 4px 24px rgba(0,0,0,.18), 0 0 0 3px rgba(147,197,253,.3);
        }
        .search-wrap i { color: var(--text-muted); font-size: .9rem; flex-shrink: 0; }
        .search-wrap input {
            border: none;
            outline: none;
            background: transparent;
            font-size: .9rem;
            color: var(--text-primary);
            width: 100%;
            font-family: 'DM Sans', sans-serif;
        }
        .search-wrap input::placeholder { color: var(--text-muted); }
        .clear-search {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: .85rem;
            padding: 2px 4px;
            display: none;
            flex-shrink: 0;
            transition: color var(--transition);
        }
        .clear-search.visible { display: block; }
        .clear-search:hover   { color: var(--danger); }

        .filter-sort {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: var(--radius-full);
            padding: 10px 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12);
        }
        .filter-sort select {
            border: none;
            outline: none;
            background: transparent;
            font-size: .85rem;
            color: var(--text-primary);
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            cursor: pointer;
        }
        .filter-sort i { color: var(--primary-dark); font-size: .85rem; }

        @media (max-width: 520px) {
            .hero              { padding: 36px 16px 32px; }
            .search-filter-bar { flex-direction: column; }
            .filter-sort       { justify-content: center; }
        }

        /* ══ RESULTS STRIP ══ */
        .results-strip {
            max-width: 1200px;
            margin: 28px auto 0;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .results-count { font-size: .83rem; color: var(--text-secondary); font-weight: 600; }
        .results-count strong { color: var(--primary-dark); }
        .no-results-hint { font-size: .8rem; color: var(--text-muted); display: none; }

        /* ══ COURSES GRID ══ */
        .courses-container {
            max-width: 1200px;
            margin: 20px auto 60px;
            padding: 0 24px;
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        /* ── Card ── */
        .course-card {
            background: var(--card);
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border-light);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
            cursor: pointer;
            animation: fadeUp .35s ease both;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(77,168,255,.3);
        }
        .course-card.hidden { display: none; }

        .card-thumb {
            width: 100%;
            height: 175px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-light), #dbeeff);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-shrink: 0;
        }
        .card-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .course-card:hover .card-thumb img { transform: scale(1.04); }
        .card-thumb .thumb-icon { font-size: 2.8rem; color: var(--primary); opacity: .45; }

        .card-body {
            padding: 18px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .card-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .95rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .card-desc {
            font-size: .8rem;
            color: var(--text-secondary);
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }
        .card-meta {
            padding-top: 12px;
            border-top: 1px solid var(--border-light);
            margin-top: auto;
        }
        .card-open-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            padding: 9px 14px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-size: .82rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all var(--transition);
            font-family: 'DM Sans', sans-serif;
        }
        .card-open-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-deeper));
            transform: translateY(-1px);
            box-shadow: var(--shadow-blue);
        }

        /* ── Empty State ── */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
        }
        .empty-state .es-icon {
            width: 72px; height: 72px;
            border-radius: var(--radius-lg);
            background: var(--bg-alt);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.9rem;
            color: var(--text-muted);
        }
        .empty-state h3 { font-size: 1rem; }
        .empty-state p  { font-size: .84rem; color: var(--text-muted); max-width: 320px; }

        /* ══ FULLSCREEN PDF VIEWER ══ */
        .pdf-viewer {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 3000;
            background: var(--card);
            flex-direction: column;
        }
        .pdf-viewer.active { display: flex; }

        .pdf-viewer-header {
            height: 56px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 20px;
            gap: 14px;
            flex-shrink: 0;
            box-shadow: var(--shadow-xs);
        }
        .pdf-viewer-logo img {
            height: 34px;
            width: auto;
            border-radius: 6px;
            object-fit: contain;
            display: block;
        }
        .pdf-h-divider { width: 1px; height: 24px; background: var(--border); flex-shrink: 0; }
        .pdf-title-area { flex: 1; min-width: 0; }
        .pdf-title-area .pv-course {
            font-size: .85rem;
            font-weight: 700;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pdf-title-area .pv-filename {
            font-size: .72rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 1px;
        }
        .pv-controls { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .pv-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 36px;
            padding: 0 14px;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border);
            background: var(--bg);
            color: var(--text-secondary);
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            white-space: nowrap;
            text-decoration: none;
            font-family: 'DM Sans', sans-serif;
        }
        .pv-btn:hover { background: var(--primary-light); color: var(--primary-dark); border-color: var(--primary); }
        .pv-btn.close-pv:hover { background: var(--danger-light); color: var(--danger); border-color: var(--danger); }

        .pdf-viewer-body {
            flex: 1;
            overflow: hidden;
            background: #4a4a4a;
            position: relative;
        }
        .pdf-viewer-body iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }

        /* Loading spinner over PDF */
        .pdf-loading {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            background: #3a3a3a;
            color: rgba(255,255,255,.85);
            font-size: .88rem;
            z-index: 2;
            transition: opacity .3s ease;
        }
        .pdf-loading i {
            font-size: 2rem;
            animation: spin 1.1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 640px) {
            .pdf-viewer-header { padding: 0 12px; gap: 10px; }
            .pdf-h-divider     { display: none; }
            .pv-btn span       { display: none; }
            .pv-btn            { padding: 0 10px; }
        }

        /* ══ FOOTER ══ */
        .pub-footer {
            background: #fff;
            border-top: 1px solid var(--border);
            padding: 18px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .pub-footer img { height: 34px; width: auto; border-radius: 6px; object-fit: contain; }
        .pub-footer .pf-copy { font-size: .75rem; color: var(--text-muted); }
        @media (max-width: 480px) {
            .pub-footer { flex-direction: column; text-align: center; padding: 16px; }
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track  { background: var(--bg-alt); }
        ::-webkit-scrollbar-thumb  { background: #C5D8F0; border-radius: var(--radius-full); }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
    </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
    <a href="/" class="nav-brand">
        <img src="logo.jpeg" alt="CourseFlow">
    </a>
    <div class="nav-divider"></div>
    <div class="nav-page-title">
        <i class="fas fa-layer-group"></i>
        Learning Programs
    </div>
    <div class="nav-spacer"></div>
    <div class="nav-count-badge">
        <i class="fas fa-file-pdf"></i>
        <?= count($courses) ?> <?= count($courses) === 1 ? 'Program' : 'Programs' ?> Available
    </div>
</nav>

<!-- ══ HERO ══ -->
<section class="hero">
    <div class="hero-inner">
        <h1>Explore Our <span>Curriculum</span></h1>
        <p>Discover what each program covers in detail before you take the next step.</p>

        <div class="search-filter-bar">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search programs…" autocomplete="off">
                <button class="clear-search" id="clearSearch" title="Clear">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="filter-sort">
                <i class="fas fa-sort-amount-down"></i>
                <select id="sortSelect">
                    <option value="default">Newest First</option>
                    <option value="az">A → Z</option>
                    <option value="za">Z → A</option>
                </select>
            </div>
        </div>
    </div>
</section>

<!-- ══ RESULTS STRIP ══ -->
<div class="results-strip">
    <div class="results-count">
        Showing <strong id="visibleCount"><?= count($courses) ?></strong>
        of <strong><?= count($courses) ?></strong>
        <?= count($courses) === 1 ? 'program' : 'programs' ?>
    </div>
    <div class="no-results-hint" id="noResultsHint">No programs match your search</div>
</div>

<!-- ══ CARDS GRID ══ -->
<div class="courses-container">
    <div class="courses-grid" id="coursesGrid">

        <?php if (empty($courses)): ?>
        <div class="empty-state">
            <div class="es-icon"><i class="fas fa-file-pdf"></i></div>
            <h3>No Programs Available Yet</h3>
            <p>Course syllabi will appear here once they are published. Please check back soon.</p>
        </div>

        <?php else: ?>
        <?php foreach ($courses as $i => $c):
            $has_image = !empty($c['image']);
        ?>
        <div class="course-card"
             data-title="<?= htmlspecialchars(strtolower($c['title'])) ?>"
             data-index="<?= $i ?>"
             style="animation-delay:<?= ($i % 12) * 0.05 ?>s"
             onclick="openViewer(<?= $i ?>)">

            <div class="card-thumb">
                <?php if ($has_image): ?>
                    <img src="<?= htmlspecialchars($c['image']) ?>"
                         alt="<?= htmlspecialchars($c['title']) ?>">
                <?php else: ?>
                    <i class="fas fa-book thumb-icon"></i>
                <?php endif; ?>
            </div>

            <div class="card-body">
                <div class="card-title"><?= htmlspecialchars($c['title']) ?></div>
                <div class="card-desc"><?= htmlspecialchars($c['description']) ?></div>
                <div class="card-meta">
                    <button class="card-open-btn"
                            onclick="event.stopPropagation(); openViewer(<?= $i ?>)">
                        <i class="fas fa-eye"></i> View Syllabus
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<!-- ══ FULLSCREEN PDF VIEWER ══ -->
<div class="pdf-viewer" id="pdfViewer">
    <div class="pdf-viewer-header">
        <div class="pdf-viewer-logo">
            <img src="logo.jpeg" alt="CourseFlow">
        </div>
        <div class="pdf-h-divider"></div>
        <div class="pdf-title-area">
            <div class="pv-course" id="pvCourseTitle">Program</div>
            <div class="pv-filename">
                <i class="fas fa-file-pdf" style="color:#DC2626"></i>
                <span id="pvFilename">syllabus.pdf</span>
            </div>
        </div>
        <div class="pv-controls">
            <a href="#" class="pv-btn" id="pvDownloadBtn" download title="Download PDF">
                <i class="fas fa-download"></i>
                <span>Download</span>
            </a>
            <button class="pv-btn close-pv" onclick="closeViewer()" title="Close">
                <i class="fas fa-times"></i>
                <span>Close</span>
            </button>
        </div>
    </div>
    <div class="pdf-viewer-body">
        <div class="pdf-loading" id="pdfLoading">
            <i class="fas fa-circle-notch"></i>
            <span>Loading syllabus…</span>
        </div>
        <iframe id="pdfIframe" src="" title="Syllabus PDF"
                onload="hidePdfLoading()">
        </iframe>
    </div>
</div>

<!-- ══ FOOTER ══ -->
<footer class="pub-footer">
    <img src="logo.jpeg" alt="CourseFlow">
    <div class="pf-copy">
        &copy; <?= date('Y') ?> CourseFlow Pvt. Ltd. All rights reserved.
    </div>
</footer>

<script>
/* ── Course data ── */
const COURSES = <?= json_encode(array_map(function($c) use ($site_base) {
    $clean_path = '/' . ltrim($c['pdf_path'], '/');
    return [
        'title'    => $c['title'],
        'filename' => $c['file_name'],
        'path'     => $clean_path,
        'full_url' => $site_base . $clean_path,
    ];
}, $courses), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

/* ── Mobile detection ── */
function isMobile() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i
            .test(navigator.userAgent) || window.innerWidth <= 768;
}

/* ── Elements ── */
const searchInput  = document.getElementById('searchInput');
const clearBtn     = document.getElementById('clearSearch');
const sortSelect   = document.getElementById('sortSelect');
const grid         = document.getElementById('coursesGrid');
const visibleCount = document.getElementById('visibleCount');
const noHint       = document.getElementById('noResultsHint');
const pdfViewer    = document.getElementById('pdfViewer');
const pdfIframe    = document.getElementById('pdfIframe');
const pdfLoading   = document.getElementById('pdfLoading');
const pvTitle      = document.getElementById('pvCourseTitle');
const pvFilename   = document.getElementById('pvFilename');
const pvDownload   = document.getElementById('pvDownloadBtn');

function hidePdfLoading() {
    pdfLoading.style.display = 'none';
}

/* ── Search + Sort ── */
function getCards() {
    return Array.from(grid.querySelectorAll('.course-card'));
}

function applyFilter() {
    const q     = searchInput.value.trim().toLowerCase();
    const sort  = sortSelect.value;
    const cards = getCards();

    cards.forEach(card => {
        const title = card.dataset.title || '';
        card.classList.toggle('hidden', q !== '' && !title.includes(q));
    });

    const visible = cards.filter(c => !c.classList.contains('hidden'));
    const hidden  = cards.filter(c =>  c.classList.contains('hidden'));

    visible.sort((a, b) => {
        const ta = a.dataset.title, tb = b.dataset.title;
        if (sort === 'az') return ta.localeCompare(tb);
        if (sort === 'za') return tb.localeCompare(ta);
        return parseInt(a.dataset.index) - parseInt(b.dataset.index);
    });

    [...visible, ...hidden].forEach(card => grid.appendChild(card));
    visibleCount.textContent   = visible.length;
    noHint.style.display       = (q && visible.length === 0) ? 'block' : 'none';
    clearBtn.classList.toggle('visible', q !== '');
}

searchInput.addEventListener('input', applyFilter);
sortSelect.addEventListener('change', applyFilter);
clearBtn.addEventListener('click', function () {
    searchInput.value = '';
    applyFilter();
    searchInput.focus();
});

/* ── PDF Viewer ── */
function openViewer(index) {
    const c = COURSES[index];
    if (!c) return;

    pvTitle.textContent    = c.title;
    pvFilename.textContent = c.filename;
    pvDownload.href        = c.path;

    /* Reset loading state */
    pdfLoading.style.display = 'flex';

    /*
     * MOBILE FIX:
     * Browsers on iOS / Android render PDFs in iframes as single-page only.
     * Google Docs Viewer handles full multi-page rendering on all mobile devices.
     * Desktop gets the native direct PDF iframe (faster, full toolbar).
     */
    if (isMobile()) {
        pdfIframe.src = 'https://docs.google.com/gview?url='
                      + encodeURIComponent(c.full_url)
                      + '&embedded=true';
    } else {
        pdfIframe.src = c.path + '#toolbar=1&navpanes=0&scrollbar=1';
    }

    pdfViewer.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewer() {
    pdfViewer.classList.remove('active');
    document.body.style.overflow = '';
    setTimeout(function () {
        pdfIframe.src = '';
        pdfLoading.style.display = 'flex';
    }, 300);
}

/* Escape closes viewer */
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeViewer();
});
</script>

</body>
</html>