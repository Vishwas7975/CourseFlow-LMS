<?php
// includes/header.php — Shared HTML head
require_once __DIR__ . '/../config/config.php';

$page_title       = $page_title ?? APP_NAME . ' — ' . APP_TAGLINE;
$page_description = $page_description ?? 'Become placement ready in 150 days with daily learning, quizzes, gamification, and AI-powered career tools.';
$page_keywords    = $page_keywords ?? 'placement preparation, aptitude, reasoning, Java, Python, DSA, mock interview, resume builder, India placement';
$app_url          = APP_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
  <meta name="keywords" content="<?= htmlspecialchars($page_keywords) ?>">
  <meta name="author" content="CourseFlow Technologies">
  <meta name="app-url" content="<?= $app_url ?>">
  <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary_large_image">
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="icon" type="image/jpeg" href="<?= $app_url ?>/assets/images/favicon.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 Grid & Utilities (Hybrid Approach) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap-grid.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap-utilities.min.css" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Custom Design System -->
  <link rel="stylesheet" href="<?= $app_url ?>/assets/css/style.css">
  <?php if (!empty($extra_css)): ?>
    <?php foreach ((array)$extra_css as $css): ?>
      <link rel="stylesheet" href="<?= $app_url ?>/assets/css/<?= $css ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body>
