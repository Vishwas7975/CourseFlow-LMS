<?php
include("config/db.php");

// Auto-create table if not exists
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

// Fetch active projects
$result   = $conn->query("SELECT * FROM final_year_projects WHERE status='active' ORDER BY created_at DESC");
$projects = [];
while ($row = $result->fetch_assoc()) $projects[] = $row;

// Distinct domains for filter
$domains_used = array_unique(array_filter(array_column($projects, 'domain')));
$total = count($projects);

// Get query parameters for server-side filter state (SEO indexable URLs)
$selected_domain = isset($_GET['domain']) ? trim($_GET['domain']) : 'all';
$search_query    = isset($_GET['q']) ? trim($_GET['q']) : '';

// SEO Dynamic Titles & Meta Descriptions
if ($selected_domain !== 'all') {
    $page_title = htmlspecialchars($selected_domain) . " Final Year Projects in Bengaluru & BTM Layout with Source Code | CourseFlow";
    $meta_desc  = "Explore top-rated " . htmlspecialchars($selected_domain) . " final year projects for B.Tech, M.Tech & Diploma students in Bengaluru, BTM Layout & online across India. IEEE report, PPT & source code included.";
} else {
    $page_title = "Buy Final Year Projects in Bengaluru & BTM Layout | Source Code, IEEE Report & PPT | CourseFlow";
    $meta_desc  = "Browse & buy industry-ready final year projects for B.Tech CSE, ECE, IT & MCA in Bengaluru, BTM Layout & online across India. Java, Python, AI/ML, Web Dev, IoT with source code, IEEE report & PPT.";
}

$canonical_url = "https://yourdomain.com/projects.php" . ($selected_domain !== 'all' ? "?domain=" . urlencode($selected_domain) : "");

// Build JSON-LD Schemas
$schema_items = [];
foreach ($projects as $idx => $p) {
    $schema_items[] = [
        "@type" => "ListItem",
        "position" => $idx + 1,
        "item" => [
            "@type" => "Product",
            "name" => $p['title'],
            "description" => mb_strimwidth(strip_tags($p['description'] ?? ''), 0, 160, '...'),
            "image" => !empty($p['thumbnail_url']) ? $p['thumbnail_url'] : "https://yourdomain.com/assets/images/logo.jpeg",
            "url" => "https://yourdomain.com/project_payment.php?id=" . $p['id'],
            "offers" => [
                "@type" => "Offer",
                "priceCurrency" => "INR",
                "price" => number_format((float)$p['price'], 2, '.', ''),
                "availability" => "https://schema.org/InStock",
                "seller" => [
                    "@type" => "Organization",
                    "name" => "CourseFlow"
                ]
            ]
        ]
    ];
}

$itemListSchema = [
    "@context" => "https://schema.org",
    "@type" => "ItemList",
    "name" => "Final Year Academic Projects Catalogue - CourseFlow",
    "numberOfItems" => count($projects),
    "itemListElement" => $schema_items
];

$localBusinessSchema = [
    "@context" => "https://schema.org",
    "@type" => "EducationalOrganization",
    "name" => "CourseFlow",
    "url" => "https://yourdomain.com/",
    "logo" => "https://yourdomain.com/assets/images/logo.jpeg",
    "address" => [
        "@type" => "PostalAddress",
        "streetAddress" => "BTM Layout",
        "addressLocality" => "Bengaluru",
        "addressRegion" => "Karnataka",
        "postalCode" => "560076",
        "addressCountry" => "IN"
    ],
    "description" => "Leading provider of IEEE final year projects, source code, and technical guidance for B.Tech, M.Tech, MCA, and Diploma students in BTM Layout Bengaluru and online across India."
];

$breadcrumbSchema = [
    "@context" => "https://schema.org",
    "@type" => "BreadcrumbList",
    "itemListElement" => [
        [
            "@type" => "ListItem",
            "position" => 1,
            "name" => "Home",
            "item" => "https://yourdomain.com/"
        ],
        [
            "@type" => "ListItem",
            "position" => 2,
            "name" => "Projects Catalogue",
            "item" => "https://yourdomain.com/projects.php"
        ]
    ]
];

$faqSchema = [
    "@context" => "https://schema.org",
    "@type" => "FAQPage",
    "mainEntity" => [
        [
            "@type" => "Question",
            "name" => "What is included with each final year project purchase from CourseFlow?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Every project purchase includes verified complete source code, database scripts, detailed execution & installation video guide, IEEE format project report & synopsis, editable PowerPoint PPT, and 1-on-1 viva guidance."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "Can I get project guidance and support at your BTM Layout Bengaluru office?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Yes! CourseFlow provides both online instant downloads and offline technical guidance at our office in BTM Layout, Bengaluru. Our experts assist with project setup, execution, and viva voice preparation."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "How quickly will I receive the project source code and documentation?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Project access and files are delivered instantly after payment approval via email or direct download link within 24 hours."
            ]
        ],
        [
            "@type" => "Question",
            "name" => "Are these projects suitable for B.Tech, M.Tech, and Diploma final year students in Bengaluru?",
            "acceptedAnswer" => [
                "@type" => "Answer",
                "text" => "Absolutely. All projects are curated according to university curriculum standards (VTU, Anna University, JNTU, deemed universities) across CSE, ECE, IT, and AI/DS branches."
            ]
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta_desc) ?>">
    <meta name="keywords" content="final year projects bengaluru, btech projects btm layout, java projects with source code bangalore, python ai ml projects btm layout, ieee final year projects bengaluru, buy college projects online india, final year project center btm layout, web development projects source code">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($canonical_url) ?>">

    <!-- Open Graph / WhatsApp / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($canonical_url) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_desc) ?>">
    <meta property="og:image" content="https://yourdomain.com/assets/images/logo.jpeg">
    <meta property="og:site_name" content="CourseFlow">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars($canonical_url) ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta_desc) ?>">
    <meta name="twitter:image" content="https://yourdomain.com/assets/images/logo.jpeg">

    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- JSON-LD Structured Data Schemas -->
    <script type="application/ld+json"><?= json_encode($itemListSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?></script>
    <script type="application/ld+json"><?= json_encode($localBusinessSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?></script>
    <script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?></script>
    <script type="application/ld+json"><?= json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?></script>

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
        img { max-width: 100%; display: block; }

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
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 9px;
            text-decoration: none;
            flex-shrink: 0;
        }
        .nav-brand img {
            height: 44px;
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
            .navbar          { padding: 0 16px; gap: 12px; }
            .nav-divider,
            .nav-page-title  { display: none; }
            .nav-count-badge { font-size: .72rem; padding: 5px 10px; }
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
        .hero-inner { position: relative; max-width: 820px; margin: 0 auto; }
        .location-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: var(--radius-full);
            background: rgba(255,255,255,0.15);
            color: #E0F2FE;
            font-size: .78rem;
            font-weight: 700;
            margin-bottom: 14px;
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(8px);
        }
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
            color: rgba(255,255,255,.85);
            font-size: .95rem;
            margin-bottom: 28px;
        }

        /* ══ SEARCH + FILTER BAR ══ */
        .search-filter-bar {
            display: flex;
            gap: 10px;
            max-width: 700px;
            margin: 0 auto 16px;
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
        .clear-search:hover   { color: #EF4444; }

        .filter-domain {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: var(--radius-full);
            padding: 10px 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12);
            transition: all var(--transition);
        }
        .filter-domain:focus-within {
            border-color: #93C5FD;
            box-shadow: 0 4px 24px rgba(0,0,0,.18), 0 0 0 3px rgba(147,197,253,.3);
        }
        .filter-domain select {
            border: none;
            outline: none;
            background: transparent;
            font-size: .85rem;
            color: var(--text-primary);
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            cursor: pointer;
            max-width: 160px;
        }
        .filter-domain i { color: var(--primary-dark); font-size: .85rem; flex-shrink: 0; }

        /* SEO Domain Quick Filters */
        .seo-domain-tags {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 14px;
        }
        .sdt-label {
            font-size: .75rem;
            color: rgba(255,255,255,0.75);
            font-weight: 600;
        }
        .sdt-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            background: rgba(255,255,255,0.14);
            color: #fff;
            font-size: .74rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all var(--transition);
        }
        .sdt-pill:hover, .sdt-pill.active {
            background: #fff;
            color: var(--primary-deeper);
            border-color: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        @media (max-width: 560px) {
            .hero               { padding: 36px 16px 32px; }
            .search-filter-bar  { flex-direction: column; }
            .filter-domain      { justify-content: center; }
            .filter-domain select { max-width: 100%; }
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

        /* ══ PROJECTS GRID ══ */
        .projects-container {
            max-width: 1200px;
            margin: 20px auto 60px;
            padding: 0 24px;
        }
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        /* ── Card ── */
        .project-card {
            background: var(--card);
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border-light);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
            animation: fadeUp .35s ease both;
        }
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(77,168,255,.3);
        }
        .project-card.hidden { display: none; }

        /* Thumbnail */
        .card-thumb {
            width: 100%;
            height: 175px;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }
        .card-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .project-card:hover .card-thumb img { transform: scale(1.04); }
        .card-thumb-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.6rem;
            color: rgba(255,255,255,.55);
        }
        /* Domain gradients */
        .grad-ai     { background: linear-gradient(135deg, #4f46e5, #7c3aed); }
        .grad-web    { background: linear-gradient(135deg, #2563eb, #0ea5e9); }
        .grad-iot    { background: linear-gradient(135deg, #059669, #10b981); }
        .grad-data   { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .grad-mobile { background: linear-gradient(135deg, #db2777, #ec4899); }
        .grad-cyber  { background: linear-gradient(135deg, #dc2626, #ef4444); }
        .grad-cloud  { background: linear-gradient(135deg, #0284c7, #38bdf8); }
        .grad-block  { background: linear-gradient(135deg, #7c3aed, #a855f7); }
        .grad-java   { background: linear-gradient(135deg, #b45309, #d97706); }
        .grad-python { background: linear-gradient(135deg, #047857, #059669); }
        .grad-analytics { background: linear-gradient(135deg, #9f1239, #e11d48); }
        .grad-vlsi   { background: linear-gradient(135deg, #0369a1, #0ea5e9); }
        .grad-other  { background: linear-gradient(135deg, #475569, #64748b); }

        /* Domain badge on thumb */
        .card-domain-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 3px 11px;
            border-radius: var(--radius-full);
            font-size: .67rem;
            font-weight: 700;
            backdrop-filter: blur(8px);
            letter-spacing: .02em;
        }
        .db-ai       { background: rgba(109,40,217,.85);  color: #ede9fe; }
        .db-web      { background: rgba(29,78,216,.85);   color: #dbeafe; }
        .db-iot      { background: rgba(21,128,61,.85);   color: #dcfce7; }
        .db-data     { background: rgba(194,65,12,.85);   color: #ffedd5; }
        .db-mobile   { background: rgba(162,28,175,.85);  color: #fae8ff; }
        .db-cyber    { background: rgba(185,28,28,.85);   color: #fee2e2; }
        .db-cloud    { background: rgba(2,132,199,.85);   color: #e0f2fe; }
        .db-block    { background: rgba(124,58,237,.85);  color: #f3e8ff; }
        .db-java     { background: rgba(180,83,9,.85);    color: #fef3c7; }
        .db-python   { background: rgba(4,120,87,.85);    color: #d1fae5; }
        .db-analytics{ background: rgba(159,18,57,.85);   color: #ffe4e6; }
        .db-vlsi     { background: rgba(3,105,161,.85);   color: #e0f2fe; }
        .db-other    { background: rgba(71,85,105,.85);   color: #f1f5f9; }

        /* Card body */
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
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 2px;
        }
        .card-tag {
            padding: 2px 9px;
            border-radius: 6px;
            background: var(--bg-alt);
            color: var(--text-secondary);
            font-size: .67rem;
            font-weight: 600;
            border: 1px solid var(--border);
        }
        .card-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: 2px;
        }
        .card-features li {
            font-size: .76rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card-features li i { color: var(--success); font-size: .62rem; flex-shrink: 0; }

        /* Card footer */
        .card-meta {
            padding: 14px 20px;
            border-top: 1.5px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: auto;
        }
        .card-price {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.25rem;
            font-weight: 900;
            color: var(--text-primary);
            line-height: 1;
        }
        .card-price small {
            display: block;
            font-size: .68rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-top: 3px;
            font-family: 'DM Sans', sans-serif;
        }
        .btn-buy {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-size: .82rem;
            font-weight: 700;
            text-decoration: none;
            transition: all var(--transition);
            white-space: nowrap;
            font-family: 'DM Sans', sans-serif;
            border: none;
            cursor: pointer;
        }
        .btn-buy:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-deeper));
            transform: translateY(-1px);
            box-shadow: var(--shadow-blue);
            color: #fff;
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

        /* No results (JS-driven) */
        .no-filter-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            display: none;
        }
        .no-filter-results.visible { display: block; }
        .no-filter-results i { font-size: 2rem; color: #93c5fd; margin-bottom: 12px; display: block; }
        .no-filter-results h3 { font-size: .95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 5px; }
        .no-filter-results p { font-size: .82rem; color: var(--text-muted); }

        /* ══ SEO CONTENT & FAQ SECTION ══ */
        .seo-content-section {
            background: #fff;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 60px 32px;
            margin-top: 40px;
        }
        .seo-inner {
            max-width: 1000px;
            margin: 0 auto;
        }
        .seo-inner h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 12px;
            text-align: center;
        }
        .seo-inner p.seo-lead {
            font-size: .95rem;
            color: var(--text-secondary);
            text-align: center;
            max-width: 800px;
            margin: 0 auto 36px;
        }
        .seo-highlights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 48px;
        }
        .seo-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
        }
        .seo-card i {
            font-size: 1.8rem;
            color: var(--primary-dark);
            margin-bottom: 12px;
        }
        .seo-card h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .seo-card p {
            font-size: .82rem;
            color: var(--text-secondary);
        }

        .faq-block {
            margin-top: 40px;
        }
        .faq-block h3 {
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 20px;
            text-align: center;
        }
        .faq-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .faq-item {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
            background: var(--bg);
        }
        .faq-question {
            width: 100%;
            padding: 16px 20px;
            background: none;
            border: none;
            outline: none;
            text-align: left;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .92rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: background var(--transition);
        }
        .faq-question:hover {
            background: var(--bg-alt);
        }
        .faq-answer {
            padding: 0 20px 16px;
            font-size: .86rem;
            color: var(--text-secondary);
            line-height: 1.6;
            display: none;
        }
        .faq-item.active .faq-answer {
            display: block;
        }
        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        /* ══ FOOTER ══ */
        .pub-footer {
            background: #fff;
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

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ══ BREADCRUMB ══ */
        .breadcrumb-bar {
            background: #fff;
            border-bottom: 1px solid var(--border-light);
            padding: 0 32px;
        }
        .breadcrumb-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 6px;
            height: 38px;
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .breadcrumb-inner a {
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color var(--transition);
        }
        .breadcrumb-inner a:hover { color: var(--primary-dark); }
        .breadcrumb-inner a i { font-size: .7rem; }
        .breadcrumb-sep {
            color: var(--border);
            font-size: .7rem;
        }
        .breadcrumb-current {
            color: var(--primary-dark);
            font-weight: 700;
        }
        @media (max-width: 640px) {
            .breadcrumb-bar { padding: 0 16px; }
        }
    </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
    <a href="index.php" class="nav-brand">
        <img src="assets/images/logo.jpeg" alt="CourseFlow - Project Center BTM Layout Bengaluru">
    </a>
    <div class="nav-divider"></div>
    <div class="nav-page-title">
        <i class="fas fa-folder-open"></i>
        Project Catalogue
    </div>
    <div class="nav-spacer"></div>
    <div class="nav-count-badge">
        <i class="fas fa-layer-group"></i>
        <?= $total ?> <?= $total === 1 ? 'Project' : 'Projects' ?> Available
    </div>
</nav>

<!-- ══ BREADCRUMB ══ -->
<div class="breadcrumb-bar">
    <div class="breadcrumb-inner">
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
        <span class="breadcrumb-current">Project Catalogue</span>
        <?php if ($selected_domain !== 'all'): ?>
        <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
        <span class="breadcrumb-current"><?= htmlspecialchars($selected_domain) ?></span>
        <?php endif; ?>
    </div>
</div>

<!-- ══ HERO ══ -->
<section class="hero">
    <div class="hero-inner">
        <div class="location-tag">
            <i class="fas fa-map-marker-alt"></i> BTM Layout, Bengaluru &amp; Online Instant Delivery
        </div>
        <h1>Discover IEEE &amp; Industry <span>Final Year Projects</span></h1>
        <p>Hand-crafted final year projects for B.Tech CSE, ECE, IT, MCA &amp; Diploma students. Includes complete verified source code, IEEE report documentation, synopsis &amp; PPT presentation.</p>

        <div class="search-filter-bar">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search projects by title or tech…" value="<?= htmlspecialchars($search_query) ?>" autocomplete="off">
                <button class="clear-search <?= $search_query !== '' ? 'visible' : '' ?>" id="clearSearch" title="Clear">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="filter-domain">
                <i class="fas fa-filter"></i>
                <select id="domainFilter">
                    <option value="all" <?= $selected_domain === 'all' ? 'selected' : '' ?>>All Domains</option>
                    <?php
                    $domain_list = ['AI / ML','Web Development','IoT','Data Science','Mobile App','Cybersecurity','Blockchain','Cloud Computing','Java Full Stack','Python Full Stack','Data Analytics','VLSI','Other'];
                    $domain_counts = array_count_values(array_column($projects, 'domain'));
                    foreach ($domain_list as $d):
                        $cnt = $domain_counts[$d] ?? 0;
                        if ($cnt === 0 && !in_array($d, $domains_used)) continue;
                    ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= $selected_domain === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?> (<?= $cnt ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- SEO Domain Quick Filters -->
        <div class="seo-domain-tags">
            <span class="sdt-label"><i class="fas fa-tags"></i> Top Categories:</span>
            <a href="projects.php?domain=all" class="sdt-pill <?= $selected_domain === 'all' ? 'active' : '' ?>">All Projects</a>
            <a href="projects.php?domain=Java+Full+Stack" class="sdt-pill <?= $selected_domain === 'Java Full Stack' ? 'active' : '' ?>">Java Full Stack</a>
            <a href="projects.php?domain=Python+Full+Stack" class="sdt-pill <?= $selected_domain === 'Python Full Stack' ? 'active' : '' ?>">Python Full Stack</a>
            <a href="projects.php?domain=AI+%2F+ML" class="sdt-pill <?= $selected_domain === 'AI / ML' ? 'active' : '' ?>">AI &amp; ML</a>
            <a href="projects.php?domain=Web+Development" class="sdt-pill <?= $selected_domain === 'Web Development' ? 'active' : '' ?>">Web Development</a>
            <a href="projects.php?domain=IoT" class="sdt-pill <?= $selected_domain === 'IoT' ? 'active' : '' ?>">IoT &amp; Embedded</a>
            <a href="projects.php?domain=Cybersecurity" class="sdt-pill <?= $selected_domain === 'Cybersecurity' ? 'active' : '' ?>">Cybersecurity</a>
            <a href="projects.php?domain=Data+Science" class="sdt-pill <?= $selected_domain === 'Data Science' ? 'active' : '' ?>">Data Science</a>
        </div>
    </div>
</section>

<!-- ══ RESULTS STRIP ══ -->
<div class="results-strip">
    <div class="results-count">
        Showing <strong id="visibleCount"><?= $total ?></strong>
        of <strong><?= $total ?></strong>
        <?= $total === 1 ? 'project' : 'projects' ?>
    </div>
    <div class="no-results-hint" id="noResultsHint">No projects match your search</div>
</div>

<!-- ══ CARDS GRID ══ -->
<div class="projects-container">
    <div class="projects-grid" id="projectsGrid">

        <?php if (empty($projects)): ?>
        <div class="empty-state">
            <div class="es-icon"><i class="fas fa-rocket"></i></div>
            <h3>Projects Coming Soon</h3>
            <p>We're curating industry-ready projects. Check back soon or contact us for a custom project.</p>
        </div>

        <?php else: foreach ($projects as $i => $p):
            $d  = strtolower($p['domain'] ?? '');
            $dk = str_contains($d,'ai')||str_contains($d,'ml')     ? 'ai'
                : (str_contains($d,'web')                           ? 'web'
                : (str_contains($d,'iot')                           ? 'iot'
                : (str_contains($d,'java')                          ? 'java'
                : (str_contains($d,'python')                        ? 'python'
                : (str_contains($d,'analytics')                     ? 'analytics'
                : (str_contains($d,'vlsi')                          ? 'vlsi'
                : (str_contains($d,'data')                          ? 'data'
                : (str_contains($d,'mobile')                        ? 'mobile'
                : (str_contains($d,'cyber')                         ? 'cyber'
                : (str_contains($d,'cloud')                         ? 'cloud'
                : (str_contains($d,'block')                         ? 'block'
                : 'other')))))))))));
            $icon_map = [
                'ai'        => 'fa-brain',
                'web'       => 'fa-globe',
                'iot'       => 'fa-microchip',
                'data'      => 'fa-chart-bar',
                'mobile'    => 'fa-mobile-alt',
                'cyber'     => 'fa-shield-alt',
                'cloud'     => 'fa-cloud',
                'block'     => 'fa-link',
                'java'      => 'fa-coffee',
                'python'    => 'fa-code',
                'analytics' => 'fa-chart-pie',
                'vlsi'      => 'fa-microchip',
                'other'     => 'fa-folder',
            ];
            $dicon    = $icon_map[$dk] ?? 'fa-folder';
            $tags     = array_slice(array_filter(array_map('trim', explode(',', $p['tech_stack'] ?? ''))), 0, 4);
            $features = array_slice(array_filter(array_map('trim', explode('|', $p['features'] ?? ''))), 0, 2);
            $desc     = htmlspecialchars(mb_strimwidth(strip_tags($p['description'] ?? ''), 0, 110, '…'));
            $search_data = strtolower(($p['title'] ?? '') . ' ' . ($p['tech_stack'] ?? '') . ' ' . ($p['domain'] ?? ''));
            $img_alt  = htmlspecialchars($p['title']) . " - Final Year Project in Bengaluru";
        ?>
        <div class="project-card"
             data-title="<?= htmlspecialchars(strtolower($p['title'] ?? '')) ?>"
             data-domain="<?= htmlspecialchars($p['domain'] ?? '') ?>"
             data-search="<?= htmlspecialchars($search_data) ?>"
             data-index="<?= $i ?>"
             style="animation-delay:<?= ($i % 12) * 0.05 ?>s">

            <!-- Thumbnail -->
            <div class="card-thumb">
                <?php if (!empty($p['thumbnail_url'])): ?>
                <img src="<?= htmlspecialchars($p['thumbnail_url']) ?>"
                     alt="<?= $img_alt ?>"
                     loading="lazy"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="card-thumb-placeholder grad-<?= $dk ?>" style="display:none;">
                    <i class="fas <?= $dicon ?>"></i>
                </div>
                <?php else: ?>
                <div class="card-thumb-placeholder grad-<?= $dk ?>">
                    <i class="fas <?= $dicon ?>"></i>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['domain'])): ?>
                <span class="card-domain-badge db-<?= $dk ?>"><?= htmlspecialchars($p['domain']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Body -->
            <div class="card-body">
                <h3 class="card-title"><?= htmlspecialchars($p['title']) ?></h3>
                <?php if (!empty($p['description'])): ?>
                <div class="card-desc"><?= $desc ?></div>
                <?php endif; ?>
                <?php if (!empty($tags)): ?>
                <div class="card-tags">
                    <?php foreach ($tags as $tag): ?>
                    <span class="card-tag"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($features)): ?>
                <ul class="card-features">
                    <?php foreach ($features as $feat): ?>
                    <li><i class="fas fa-check-circle"></i><?= htmlspecialchars($feat) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="card-meta">
                <div class="card-price">
                    ₹<?= number_format((float)$p['price'], 0) ?>
                    <small>All source &amp; report files</small>
                </div>
                <a href="project_payment.php?id=<?= (int)$p['id'] ?>" class="btn-buy" title="View &amp; Buy <?= htmlspecialchars($p['title']) ?>">
                    <i class="fas fa-arrow-right"></i> View &amp; Buy
                </a>
            </div>
        </div>
        <?php endforeach; endif; ?>

        <!-- No filter results (JS) -->
        <div class="no-filter-results" id="noFilterResults">
            <i class="fas fa-search"></i>
            <h3>No projects found</h3>
            <p>Try a different search term or filter.</p>
        </div>

    </div>
</div>

<!-- ══ SEO CONTENT & FAQ SECTION ══ -->
<section class="seo-content-section">
    <div class="seo-inner">
        <h2>Final Year IEEE Project Center in BTM Layout, Bengaluru &amp; Online</h2>
        <p class="seo-lead">CourseFlow provides complete, industry-grade final year college projects for B.Tech CSE, ECE, IT, MCA &amp; Diploma students. Visit our BTM Layout office in Bengaluru or download online instantly.</p>

        <div class="seo-highlights-grid">
            <div class="seo-card">
                <i class="fas fa-code"></i>
                <h3>Complete Source Code</h3>
                <p>Clean, well-documented source code in Java, Python, Web, React, Node, AI/ML, and Android with DB scripts.</p>
            </div>
            <div class="seo-card">
                <i class="fas fa-file-pdf"></i>
                <h3>IEEE Format Report &amp; PPT</h3>
                <p>Includes complete project report, abstract, synopsis, system architecture diagrams &amp; editable PowerPoint PPT.</p>
            </div>
            <div class="seo-card">
                <i class="fas fa-headset"></i>
                <h3>Installation &amp; Viva Support</h3>
                <p>Step-by-step setup video guide plus 1-on-1 viva voice preparation support from expert mentors in Bengaluru.</p>
            </div>
            <div class="seo-card">
                <i class="fas fa-bolt"></i>
                <h3>Instant 24-Hour Delivery</h3>
                <p>Get immediate access to project files right after payment approval, anywhere in Bengaluru or across India.</p>
            </div>
        </div>

        <div class="faq-block">
            <h3>Frequently Asked Questions (FAQ)</h3>
            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question">
                        <span>What is included with each final year project purchase?</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        Every project package comes with 100% verified source code, database script/dump, step-by-step installation video/guide, IEEE format project report, synopsis, system design diagrams, editable PPT presentation, and viva voce Q&amp;A assistance.
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Can I visit your BTM Layout Bengaluru office for project guidance?</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        Yes! Students in Bengaluru are welcome to visit our office in BTM Layout for direct project consultation, execution walkthroughs, and viva preparation sessions with our senior software engineers.
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question">
                        <span>How quickly will I receive the project source code and report?</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        After placing your order and payment approval, project download links and files are emailed to you instantly or within a maximum of 24 hours.
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Are these projects suitable for B.Tech CSE, ECE, IT, and MCA students?</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        Yes, all our projects strictly adhere to VTU, Anna University, JNTU, and autonomous university standards across Computer Science, Information Science, AI/ML, Data Science, and Electronics domains.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══ FOOTER ══ -->
<footer class="pub-footer">
    <img src="assets/images/logo.jpeg" alt="CourseFlow - BTM Layout Bengaluru">
    <div class="pf-copy">
        &copy; <?= date('Y') ?> CourseFlow Pvt. Ltd., BTM Layout, Bengaluru. All rights reserved.
    </div>
</footer>

<script>
const searchInput   = document.getElementById('searchInput');
const clearBtn      = document.getElementById('clearSearch');
const domainFilter  = document.getElementById('domainFilter');
const grid          = document.getElementById('projectsGrid');
const visibleCount  = document.getElementById('visibleCount');
const noHint        = document.getElementById('noResultsHint');
const noFilterRes   = document.getElementById('noFilterResults');

function getCards() {
    return Array.from(grid.querySelectorAll('.project-card'));
}

function applyFilter() {
    const q      = searchInput.value.trim().toLowerCase();
    const domain = domainFilter.value;
    const cards  = getCards();

    let visible = 0;

    cards.forEach(card => {
        const searchData  = card.dataset.search  || '';
        const cardDomain  = card.dataset.domain  || '';
        const matchSearch = q === '' || searchData.includes(q);
        const matchDomain = domain === 'all' || cardDomain === domain;
        const show = matchSearch && matchDomain;
        card.classList.toggle('hidden', !show);
        if (show) visible++;
    });

    visibleCount.textContent = visible;
    noHint.style.display     = (q && visible === 0) ? 'block' : 'none';
    noFilterRes.classList.toggle('visible', visible === 0 && cards.length > 0);
    clearBtn.classList.toggle('visible', q !== '');
}

searchInput.addEventListener('input', applyFilter);
domainFilter.addEventListener('change', applyFilter);
clearBtn.addEventListener('click', function () {
    searchInput.value = '';
    applyFilter();
    searchInput.focus();
});

// FAQ Accordion Toggle
document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.parentElement;
        item.classList.toggle('active');
    });
});

// Apply filter on initial load if query params were passed
document.addEventListener('DOMContentLoaded', () => {
    applyFilter();
});
</script>

</body>
</html>