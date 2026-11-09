<?php
// Set $page_title before including this file.
require_once __DIR__ . '/functions.php';
$page_title = $page_title ?? 'Library';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> | Library</title>
    <script>
        // Apply the saved theme before the page draws, so it never flashes the wrong colours.
        try {
            var savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light' || savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
        } catch (e) {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Fraunces:opsz,wght@9..144,600&family=Courier+Prime:wght@400;700&display=swap">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body>
<?php if (!empty($_SESSION['user_id'])): ?>
    <?php require __DIR__ . '/navbar.php'; ?>
<?php else: ?>
    <div class="theme-corner"><?= theme_toggle() ?></div>
<?php endif; ?>
<main class="page">
<?php foreach (take_flashes() as $flash): ?>
    <p class="flash flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
<?php endforeach; ?>
