<?php
require_once __DIR__ . '/../includes/auth_check.php';

// Group Sixteen, the students who built this system (same list as docs/team.html).
const TEAM_COURSE = 'BCS';
const TEAM = [
    ['Maway Abili Etienne', '161-552', 'Foundation, login and security', 'Team lead'],
    ['Antonia Nyakato', '260-296', 'Project management', ''],
    ['Lukwago Joseph Bakumpe', '258-278', 'Database design and reports', ''],
    ['Mugalu Kevin', '215-555', 'Books', ''],
    ['Atyang Sarah', '264-111', 'Authors and categories', ''],
    ['Anai Achiek Wel Alith', '208-350', 'Members', ''],
    ['Unguech Unuech William Mawut', '250-773', 'Issuing loans', ''],
    ['Lwoto Josiah Jared', '187-322', 'Loan returns and history', ''],
    ['Ibrahim Ali Artan', '219-401', 'Dashboard and reports', ''],
    ['Ali Henry Fred Alimo', '206-884', 'Layout, phone support and rule testing', ''],
];

$page_title = 'Team';
require __DIR__ . '/../includes/header.php';
?>
<section class="hero hero--short" aria-labelledby="team-title">
    <p class="hero__eyebrow">Group Sixteen</p>
    <h1 class="hero__title" id="team-title">The team</h1>
    <p class="hero__lede"><?= count(TEAM) ?> <?= e(TEAM_COURSE) ?> students built this Library Management System for Database 2, CAT 2.</p>
</section>

<ul class="team">
    <?php foreach (TEAM as $i => [$name, $id, $work, $badge]) : ?>
        <li class="member-card" style="--i: <?= (int) $i ?>">
            <span class="member-card__avatar" aria-hidden="true"><?= e(initials($name)) ?></span>
            <div class="member-card__body">
                <h2 class="member-card__name"><?= e($name) ?></h2>
                <p class="member-card__work"><?= e($work) ?></p>
                <?php if ($badge !== '') : ?>
                    <span class="member-card__badge"><?= e($badge) ?></span>
                <?php endif; ?>
            </div>
            <dl class="member-card__facts">
                <div><dt>ID</dt><dd><?= e($id) ?></dd></div>
                <div><dt>Course</dt><dd><?= e(TEAM_COURSE) ?></dd></div>
            </dl>
        </li>
    <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/../includes/footer.php'; ?>
