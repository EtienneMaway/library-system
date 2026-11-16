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
        // Mark that JavaScript runs, so toasts can float and close themselves (see style.css).
        document.documentElement.classList.add('js');
        // Apply the saved theme before the page draws, so it never flashes the wrong colours.
        try {
            var savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light' || savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
            // The same for the drawer on wide screens: open (icons and names) or closed (icons only).
            if (localStorage.getItem('rail') === 'closed') {
                document.documentElement.setAttribute('data-rail', 'closed');
            }
        } catch (e) {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Courier+Prime:wght@400;700&display=swap">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script src="<?= e(url('assets/js/app.js')) ?>?v=<?= (int) filemtime(__DIR__ . '/../assets/js/app.js') ?>" defer></script>
</head>
<body<?= !empty($_SESSION['user_id']) ? ' class="app"' : '' ?>>
<?php if (!empty($_SESSION['user_id'])) : ?>
    <?php require __DIR__ . '/navbar.php'; ?>
<?php else : ?>
    <div class="theme-corner"><?= theme_toggle() ?></div>
<?php endif; ?>
<main class="page">
<?php $toasts = take_flashes(); ?>
<div class="toasts" aria-label="Notifications">
<?php foreach ($toasts as $toast) :
    $type = in_array($toast['type'], ['success', 'error', 'info'], true) ? $toast['type'] : 'info';
    $title = ['success' => 'Done', 'error' => 'Something needs attention', 'info' => 'Heads up'][$type]; ?>
    <div class="toast toast--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>" data-toast>
        <span class="toast__icon" aria-hidden="true"><?= $type === 'success' ? '&#10003;' : ($type === 'error' ? '!' : 'i') ?></span>
        <div class="toast__body">
            <p class="toast__title"><?= e($title) ?></p>
            <p class="toast__message"><?= e($toast['message']) ?></p>
        </div>
        <button type="button" class="toast__close" aria-label="Dismiss" data-toast-close>&times;</button>
        <span class="toast__timer" aria-hidden="true"></span>
    </div>
<?php endforeach; ?>
</div>
