<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/reports.php';

// Overdue loans, most days late first since those owe the most.
$overdue = $pdo->query(
    'SELECT l.loan_id, l.due_date, ' . DAYS_LATE_SQL . ' AS days_late,
            m.full_name, m.member_number, m.phone, b.title
     FROM loans l
     JOIN members m ON m.member_id = l.member_id
     JOIN books b ON b.book_id = l.book_id
     WHERE ' . OVERDUE_SQL . '
     ORDER BY days_late DESC, m.full_name'
)->fetchAll();

if (wants_csv()) {
    send_csv('overdue-loans', ['Member', 'Member number', 'Phone', 'Book', 'Due', 'Days late', 'Fine (UGX)'], array_map(
        fn ($row) => [$row['full_name'], $row['member_number'], $row['phone'], $row['title'], $row['due_date'], $row['days_late'], fine_for((int) $row['days_late'])],
        $overdue
    ));
}

$totals = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM books) AS titles,
        (SELECT COALESCE(SUM(available_copies), 0) FROM books) AS on_shelf,
        (SELECT COUNT(*) FROM members WHERE status = 'active') AS active_members,
        (SELECT COUNT(*) FROM loans WHERE date_returned IS NULL) AS out_now"
)->fetch();
$fines_owed = array_sum(array_map(fn ($row) => fine_for((int) $row['days_late']), $overdue));

// Loans per category: every loan ever made, and how many of them are still out. LEFT JOINs keep empty categories.
$by_category = $pdo->query(
    'SELECT c.name, COUNT(l.loan_id) AS loans, COALESCE(SUM(l.loan_id IS NOT NULL AND l.date_returned IS NULL), 0) AS out_now
     FROM categories c
     LEFT JOIN books b ON b.category_id = c.category_id
     LEFT JOIN loans l ON l.book_id = b.book_id
     GROUP BY c.category_id, c.name
     ORDER BY loans DESC, c.name'
)->fetchAll();
$most = max(1, ...array_map(fn ($row) => (int) $row['loans'], $by_category ?: [['loans' => 0]]));

$page_title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<?php
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first_name = explode(' ', trim($_SESSION['full_name'] ?? ''))[0];
?>
<section class="hero" aria-labelledby="hero-title">
    <p class="hero__eyebrow">Dashboard</p>
    <h1 class="hero__title" id="hero-title"><?= e($greeting) ?><?= $first_name !== '' ? ', ' . e($first_name) : '' ?></h1>
    <p class="hero__lede"><?= e($totals['out_now']) ?> books are out on loan<?= $overdue ? ' and ' . count($overdue) . ' are overdue' : ', and none are overdue' ?>.</p>
</section>
<dl class="tally">
    <div><dt>Titles</dt><dd><?= e($totals['titles']) ?></dd></div>
    <div><dt>Copies on shelf</dt><dd><?= e($totals['on_shelf']) ?></dd></div>
    <div><dt>Active members</dt><dd><?= e($totals['active_members']) ?></dd></div>
    <div><dt>Out on loan</dt><dd><?= e($totals['out_now']) ?></dd></div>
    <div class="tally__alert"><dt>Overdue</dt><dd><?= count($overdue) ?></dd></div>
    <div class="tally__alert"><dt>Fines owed</dt><dd class="tally__money"><?= e(money($fines_owed)) ?></dd></div>
</dl>

<div class="panels">
    <section class="panel" aria-labelledby="chart-title">
        <h2 id="chart-title">Loans per category</h2>
        <p class="panel__note">Every loan ever made, by the book's category. The red part is still out on loan.</p>
        <?php if (!$by_category) : ?>
            <p>No categories yet.</p>
        <?php else : ?>
            <ul class="bars">
                <?php foreach ($by_category as $row) : ?>
                    <?php $loans = (int) $row['loans'];
                    $out = (int) $row['out_now']; ?>
                    <li class="bars__row">
                        <span class="bars__label"><?= e($row['name']) ?></span>
                        <span class="bars__value"><?= $loans ?> <small>(<?= $out ?> out)</small></span>
                        <span class="bars__track" aria-hidden="true">
                            <span class="bars__fill" style="width: <?= round($loans / $most * 100, 1) ?>%">
                                <span class="bars__out" style="width: <?= $loans ? round($out / $loans * 100, 1) : 0 ?>%"></span>
                            </span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="overdue-title">
        <div class="page-head">
            <h2 id="overdue-title">Overdue loans</h2>
            <?php if ($overdue) : ?>
                <?= csv_link() ?>
            <?php endif; ?>
        </div>
        <p class="panel__note">Fine: <?= e(money(FINE_PER_DAY)) ?> for each day late, worked out from the due date.</p>
        <?php if (!$overdue) : ?>
            <p>Nothing is overdue. Every book out on loan is within its 14 days.</p>
        <?php else : ?>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>Member</th><th>Book</th><th>Due</th><th class="num">Days late</th><th class="num">Fine</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($overdue as $row) : ?>
                        <tr>
                            <td><strong><?= e($row['full_name']) ?></strong><br><small><?= e($row['phone']) ?></small></td>
                            <td><?= e($row['title']) ?></td>
                            <td class="nowrap"><?= e($row['due_date']) ?></td>
                            <td class="num"><?= e($row['days_late']) ?></td>
                            <td class="num"><?= e(money(fine_for((int) $row['days_late']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p><a href="<?= e(url('loans/index.php?status=overdue')) ?>">See them in the loans list</a></p>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
