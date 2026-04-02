<?php
$current_page = $current_page ?? 'home';
$page_title = $page_title ?? SITE_NAME . ' — We\'re Getting Married';
$og_description = $og_description ?? 'Join us as we celebrate our wedding day.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$base_url = $scheme . '://' . $host;
$og_url = $base_url . $request_uri;
$og_image = $base_url . BASE . '/assets/images/wedding.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($og_description) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_image) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($og_url) ?>">
    <meta property="og:type" content="website">
    <link rel="icon" type="image/png" href="<?= BASE ?>/assets/images/logo.png">
    <link rel="apple-touch-icon" href="<?= BASE ?>/assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://db.onlinewebfonts.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;1,400&family=Manrope:wght@400;500;600;700&family=Parisienne&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
</head>
<body class="page-<?= htmlspecialchars($current_page) ?>">
    <div class="page-loader" id="page-loader" aria-hidden="true">
        <img src="<?= BASE ?>/assets/images/logo.png" alt="" class="page-loader-logo">
    </div>
    <header class="site-header">
        <a href="<?= BASE ?>/" class="logo" aria-label="Omasyl home">
            <img src="<?= BASE ?>/assets/images/logo.png" alt="Omasyl 2026" class="logo-img">
        </a>
        <nav class="main-nav">
            <a href="<?= BASE ?>/" class="<?= $current_page === 'home' ? 'active' : '' ?>">Home</a>
            <a href="<?= BASE ?>/#our-story">Our Story</a>
            <a href="<?= BASE ?>/#details">Details</a>
            <a href="<?= BASE ?>/gallery">Gallery</a>
            <a href="<?= BASE ?>/gifts">Gifts</a>
            <a href="<?= BASE ?>/well-wishes">Well Wishes</a>
            <a href="<?= BASE ?>/register" class="nav-cta">RSVP</a>
        </nav>
        <button class="menu-toggle" type="button" aria-label="Toggle menu" aria-expanded="false">☰</button>
    </header>
    <main class="main-content">
