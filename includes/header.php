<?php
declare(strict_types=1);
$description = $description !== '' ? $description : 'Simple, secure tools to convert and manage your PDF files.';
$baseUrl = app_config('app_url');
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="canonical" href="<?= e($baseUrl . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#5b4bff">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/main.css">
    <script>window.PDFFORGE = <?= json_encode([
        'baseUrl' => $baseUrl,
        'csrfToken' => csrf_token(),
        'maxUploadSize' => app_config('max_upload_size'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script defer src="<?= e($baseUrl) ?>/assets/js/app.js"></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" id="site-header">
    <nav class="navbar container" aria-label="Main navigation">
        <a class="brand" href="<?= e($baseUrl) ?>/index.php" aria-label="PDFForge home">
            <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M7 3h12l7 7v19H7z"/><path d="M19 3v8h7M11 18h11M11 23h8"/></svg></span>
            <span>PDF<span class="brand-accent">Forge</span></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-menu" aria-label="Open menu"><span></span><span></span><span></span></button>
        <div class="nav-menu" id="primary-menu">
            <div class="nav-links">
                <details class="nav-dropdown"><summary>Tools <span aria-hidden="true">⌄</span></summary><div class="nav-popover"><strong>Explore PDF tools</strong><a href="<?= e($baseUrl) ?>/tools/tools.php">All tools</a><a href="<?= e($baseUrl) ?>/tools/pdf-to-jpg.php">PDF to JPG <span class="menu-ready">Ready</span></a><a href="<?= e($baseUrl) ?>/tools/merge-pdf.php">Merge PDF <span class="menu-ready">Ready</span></a></div></details>
                <a href="<?= e($baseUrl) ?>/index.php#features">Features</a>
                <a href="<?= e($baseUrl) ?>/pricing.php">Pricing</a>
                <a href="<?= e($baseUrl) ?>/about.php">About</a>
                <a href="<?= e($baseUrl) ?>/contact.php">Contact</a>
            </div>
            <div class="nav-actions">
                <button class="theme-toggle" type="button" aria-label="Toggle dark mode"><span aria-hidden="true">◐</span></button>
                <?php if (current_user()): ?>
                    <a class="nav-login" href="<?= e($baseUrl) ?>/dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a class="nav-login" href="<?= e($baseUrl) ?>/login.php">Log in</a>
                <?php endif; ?>
                <a class="button button-small" href="<?= e($baseUrl) ?>/register.php">Get started</a>
            </div>
        </div>
    </nav>
</header>
