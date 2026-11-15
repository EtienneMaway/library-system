<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$totals = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM books) AS titles,
        (SELECT COALESCE(SUM(total_copies), 0) FROM books) AS total_copies,
        (SELECT COALESCE(SUM(available_copies), 0) FROM books) AS on_shelf,
        (SELECT COUNT(*) FROM members WHERE status = 'active') AS active_members,
        (SELECT COUNT(*) FROM loans WHERE date_returned IS NULL) AS out_now,
        (SELECT COUNT(*) FROM loans WHERE date_returned IS NULL AND due_date < CURDATE()) AS overdue"
)->fetch();

$overdue_loans = $pdo->query(
    'SELECT l.loan_id, l.due_date, b.title, m.full_name, m.member_number
     FROM loans l
     JOIN books b ON b.book_id = l.book_id
     JOIN members m ON m.member_id = l.member_id
     WHERE l.date_returned IS NULL AND l.due_date < CURDATE()
     ORDER BY l.due_date, m.full_name
     LIMIT 5'
)->fetchAll();

$categories = $pdo->query(
    'SELECT c.name, COUNT(b.book_id) AS titles,
            COALESCE(SUM(b.available_copies), 0) AS available_copies
     FROM categories c
     LEFT JOIN books b ON b.category_id = c.category_id
     GROUP BY c.category_id, c.name
     ORDER BY titles DESC, c.name
     LIMIT 5'
)->fetchAll();

$recent_loans = $pdo->query(
    'SELECT l.date_issued, l.due_date, l.date_returned,
            b.title, m.full_name, m.member_number
     FROM loans l
     JOIN books b ON b.book_id = l.book_id
     JOIN members m ON m.member_id = l.member_id
     ORDER BY l.date_issued DESC, l.loan_id DESC
     LIMIT 6'
)->fetchAll();

$max_category_titles = max(1, ...array_map(
    static fn (array $category): int => (int) $category['titles'],
    $categories
));
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$page_title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard">
    <div class="dashboard__intro">
        <div>
            <p class="dashboard__eyebrow">Your library, at a glance</p>
            <h1><?= e($greeting) ?>, <?= e($_SESSION['full_name'] ?? 'Librarian') ?>.</h1>
            <p class="dashboard__date"><?= e(date('l, F j, Y')) ?> <span aria-hidden="true">/</span> Here is what is happening today.</p>
        </div>
        <nav class="dashboard__actions" aria-label="Quick actions">
            <a class="btn" href="<?= e(url('loans/issue.php')) ?>">Issue a book</a>
            <a class="btn btn--quiet" href="<?= e(url('books/create.php')) ?>">Add a book</a>
            <a class="dashboard__action-link" href="<?= e(url('members/create.php')) ?>">Add a member</a>
        </nav>
    </div>

    <section class="dashboard__stats" aria-label="Library summary">
        <article class="dashboard-stat">
            <p class="dashboard-stat__label">Book titles</p>
            <p class="dashboard-stat__value"><?= e($totals['titles']) ?></p>
            <p class="dashboard-stat__note"><?= e($totals['total_copies']) ?> copies in the collection</p>
        </article>
        <article class="dashboard-stat dashboard-stat--gold">
            <p class="dashboard-stat__label">Available copies</p>
            <p class="dashboard-stat__value"><?= e($totals['on_shelf']) ?></p>
            <p class="dashboard-stat__note">Ready to be borrowed</p>
        </article>
        <article class="dashboard-stat">
            <p class="dashboard-stat__label">On loan</p>
            <p class="dashboard-stat__value"><?= e($totals['out_now']) ?></p>
            <p class="dashboard-stat__note"><a href="<?= e(url('loans/index.php?status=out')) ?>">View current loans</a></p>
        </article>
        <article class="dashboard-stat dashboard-stat--alert">
            <p class="dashboard-stat__label">Overdue</p>
            <p class="dashboard-stat__value"><?= e($totals['overdue']) ?></p>
            <p class="dashboard-stat__note"><a href="<?= e(url('loans/index.php?status=overdue')) ?>">Follow up on late books</a></p>
        </article>
        <article class="dashboard-stat">
            <p class="dashboard-stat__label">Active members</p>
            <p class="dashboard-stat__value"><?= e($totals['active_members']) ?></p>
            <p class="dashboard-stat__note"><a href="<?= e(url('members/index.php')) ?>">Browse member directory</a></p>
        </article>
    </section>

    <div class="dashboard__columns">
        <section class="dashboard-panel" aria-labelledby="overdue-title">
            <div class="dashboard-panel__head">
                <div>
                    <p class="dashboard-panel__eyebrow">Needs attention</p>
                    <h2 id="overdue-title">Overdue loans</h2>
                </div>
                <a href="<?= e(url('loans/index.php?status=overdue')) ?>">View all</a>
            </div>
            <?php if ($overdue_loans === []) : ?>
                <p class="dashboard__empty">No overdue books. Everything is on track.</p>
            <?php else : ?>
                <ul class="dashboard-overdue">
                    <?php foreach ($overdue_loans as $loan) : ?>
                        <li>
                            <span class="dashboard-overdue__mark" aria-hidden="true">!</span>
                            <span class="dashboard-overdue__details">
                                <strong><?= e($loan['title']) ?></strong>
                                <small><?= e($loan['full_name']) ?> · <?= e($loan['member_number']) ?></small>
                            </span>
                            <span class="dashboard-overdue__date">Due <?= e($loan['due_date']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel" aria-labelledby="categories-title">
            <div class="dashboard-panel__head">
                <div>
                    <p class="dashboard-panel__eyebrow">The collection</p>
                    <h2 id="categories-title">By category</h2>
                </div>
                <a href="<?= e(url('categories/index.php')) ?>">Browse</a>
            </div>
            <?php if ($categories === []) : ?>
                <p class="dashboard__empty">No categories yet. Add one to organize your books.</p>
            <?php else : ?>
                <ul class="dashboard-categories">
                    <?php foreach ($categories as $category) : ?>
                        <?php $category_width = (int) round((int) $category['titles'] / $max_category_titles * 100); ?>
                        <li>
                            <div class="dashboard-categories__labels">
                                <strong><?= e($category['name']) ?></strong>
                                <span><?= e($category['titles']) ?> titles · <?= e($category['available_copies']) ?> available</span>
                            </div>
                            <div class="dashboard-categories__track" aria-hidden="true">
                                <span style="width: <?= e($category_width) ?>%"></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="dashboard-panel dashboard-panel--activity" aria-labelledby="activity-title">
        <div class="dashboard-panel__head">
            <div>
                <p class="dashboard-panel__eyebrow">The latest from the desk</p>
                <h2 id="activity-title">Recent loans</h2>
            </div>
            <a href="<?= e(url('loans/index.php')) ?>">All loans</a>
        </div>
        <?php if ($recent_loans === []) : ?>
            <p class="dashboard__empty">No loan activity yet. <a href="<?= e(url('loans/issue.php')) ?>">Issue the first book</a>.</p>
        <?php else : ?>
            <div class="table-scroll">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Book</th>
                            <th>Issued</th>
                            <th>Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_loans as $loan) : ?>
                            <?php
                            if ($loan['date_returned'] !== null) {
                                $loan_status = 'Returned';
                                $pill_class = 'pill--returned';
                            } elseif ($loan['due_date'] < date('Y-m-d')) {
                                $loan_status = 'Overdue';
                                $pill_class = 'pill--late';
                            } else {
                                $loan_status = 'On loan';
                                $pill_class = 'pill--out';
                            }
                            ?>
                            <tr>
                                <td><?= e($loan['full_name']) ?><small class="dashboard-table__sub"><?= e($loan['member_number']) ?></small></td>
                                <td><?= e($loan['title']) ?></td>
                                <td><?= e($loan['date_issued']) ?></td>
                                <td><?= e($loan['due_date']) ?></td>
                                <td><span class="pill <?= e($pill_class) ?>"><?= e($loan_status) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

