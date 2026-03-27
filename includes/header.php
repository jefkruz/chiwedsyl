<?php
$current_page = $current_page ?? 'home';
$page_title = $page_title ?? SITE_NAME . ' — We\'re Getting Married';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;1,400&family=Manrope:wght@400;500;600;700&family=Parisienne&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
</head>
<body class="page-<?= htmlspecialchars($current_page) ?>">
    <header class="site-header">
        <a href="<?= BASE ?>/" class="logo">Omasyl 2026</a>
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
