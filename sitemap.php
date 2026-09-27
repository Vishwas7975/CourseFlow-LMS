<?php
header("Content-Type: application/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$baseUrl = "https://yourdomain.com";

// 1. Static Pages
$staticPages = [
    ['loc' => $baseUrl . '/', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => $baseUrl . '/projects.php', 'priority' => '0.9', 'changefreq' => 'daily'],
    ['loc' => $baseUrl . '/workshop.php', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['loc' => $baseUrl . '/syllabus.php', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['loc' => $baseUrl . '/careers.html', 'priority' => '0.6', 'changefreq' => 'monthly'],
];

foreach ($staticPages as $page) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($page['loc']) . "</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>" . $page['changefreq'] . "</changefreq>\n";
    echo "    <priority>" . $page['priority'] . "</priority>\n";
    echo "  </url>\n";
}

// 2. Domain Filter URLs
$domains = [
    'AI / ML', 'Web Development', 'IoT', 'Data Science', 'Mobile App', 
    'Cybersecurity', 'Blockchain', 'Cloud Computing', 'Java Full Stack', 
    'Python Full Stack', 'Data Analytics', 'VLSI'
];

foreach ($domains as $dom) {
    $url = $baseUrl . '/projects.php?domain=' . urlencode($dom);
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($url) . "</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>daily</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

// 3. Dynamic Active Projects
@include_once("config/db.php");

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $res = $conn->query("SELECT id, created_at FROM final_year_projects WHERE status='active' ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $projId   = (int)$row['id'];
            $lastmod  = !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : date('Y-m-d');
            $projUrl  = $baseUrl . '/project_payment.php?id=' . $projId;

            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($projUrl) . "</loc>\n";
            echo "    <lastmod>" . $lastmod . "</lastmod>\n";
            echo "    <changefreq>weekly</changefreq>\n";
            echo "    <priority>0.8</priority>\n";
            echo "  </url>\n";
        }
    }
}

echo '</urlset>';
