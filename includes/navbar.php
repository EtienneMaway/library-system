<?php
// Icon paths (24 by 24, drawn with a stroke) for each page in the rail.
$links = [
    'dashboard/index.php'  => ['Dashboard', '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
    'books/index.php'      => ['Books', '<path d="M4 19.5V5a2 2 0 0 1 2-2h13v15H6a2 2 0 0 0-2 2.5z"/><path d="M6 21h13v-3"/><path d="M9 7h6"/>'],
    'members/index.php'    => ['Members', '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18 14a6.5 6.5 0 0 1 3.5 6"/>'],
    'loans/index.php'      => ['Loans', '<path d="M4 8h14l-4-4"/><path d="M20 16H6l4 4"/>'],
    'authors/index.php'    => ['Authors', '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>'],
    'categories/index.php' => ['Categories', '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>'],
];
$here = $_SERVER['SCRIPT_NAME'] ?? '';
$name = $_SESSION['full_name'] ?? '';
$initials = '';
foreach (array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2) as $part) {
    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
?>
<nav class="rail" id="drawer" aria-label="Main">
    <div class="rail__top">
        <a class="rail__brand" href="<?= e(url('dashboard/index.php')) ?>">
            <span class="rail__logo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 5.5C4.5 4 8 4 12 6c4-2 7.5-2 10-.5V19c-2.5-1.5-6-1.5-10 .5-4-2-7.5-2-10-.5z"/><path d="M12 6v13.5"/></svg></span>
            <span class="rail__name">Library</span>
        </a>
        <button type="button" class="rail__close" data-drawer-close aria-label="Close menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </div>
    <ul class="rail__links">
        <?php foreach ($links as $path => [$label, $icon]) :
            $section = dirname($path);
            $current = str_contains($here, "/$section/"); ?>
            <li>
                <a class="rail__link" href="<?= e(url($path)) ?>" title="<?= e($label) ?>"<?= $current ? ' aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg>
                    <span class="rail__label"><?= e($label) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="rail__toggle" data-rail-toggle aria-controls="drawer" aria-expanded="true">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
        <span class="rail__label">Collapse</span>
    </button>
</nav>
<div class="drawer-backdrop" data-drawer-close></div>
<header class="topbar">
    <button type="button" class="topbar__menu" data-drawer-open aria-controls="drawer" aria-expanded="false" aria-label="Open menu">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
    </button>
    <a class="topbar__brand" href="<?= e(url('dashboard/index.php')) ?>">Library</a>
    <p class="topbar__date"><?= e(date('l j F Y')) ?></p>
    <div class="topbar__tools">
        <?= theme_toggle() ?>
        <details class="account">
            <summary>
                <span class="account__avatar" aria-hidden="true"><?= e($initials) ?></span>
                <span class="account__who"><?= e($name) ?> <span class="account__role"><?= is_admin() ? 'Admin' : 'Librarian' ?></span></span>
            </summary>
            <div class="account__menu">
                <a href="<?= e(url('auth/change_password.php')) ?>">Change password</a>
                <a href="<?= e(url('auth/logout.php')) ?>">Log out</a>
            </div>
        </details>
    </div>
</header>
