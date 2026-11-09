<?php
$links = [
    'dashboard/index.php'  => 'Dashboard',
    'books/index.php'      => 'Books',
    'members/index.php'    => 'Members',
    'loans/index.php'      => 'Loans',
    'authors/index.php'    => 'Authors',
    'categories/index.php' => 'Categories',
];
$here = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<header class="stacks">
    <a class="stacks__brand" href="<?= e(url('dashboard/index.php')) ?>">Library</a>
    <nav class="stacks__nav" aria-label="Main">
        <?php foreach ($links as $path => $label) :
            $section = dirname($path);
            $current = str_contains($here, "/$section/"); ?>
            <a href="<?= e(url($path)) ?>"<?= $current ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <p class="stacks__user">
        <?= theme_toggle() ?>
        <span><?= e($_SESSION['full_name'] ?? '') ?></span>
        <a href="<?= e(url('auth/logout.php')) ?>">Log out</a>
    </p>
</header>
