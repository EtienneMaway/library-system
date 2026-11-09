<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$totals = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM books) AS titles,
        (SELECT COALESCE(SUM(available_copies), 0) FROM books) AS on_shelf,
        (SELECT COUNT(*) FROM members WHERE status = 'active') AS active_members,
        (SELECT COUNT(*) FROM loans WHERE date_returned IS NULL) AS out_now,
        (SELECT COUNT(*) FROM loans WHERE date_returned IS NULL AND due_date < CURDATE()) AS overdue"
)->fetch();

$page_title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Dashboard</h1>
<dl class="tally">
    <div><dt>Titles</dt><dd><?= e($totals['titles']) ?></dd></div>
    <div><dt>Copies on shelf</dt><dd><?= e($totals['on_shelf']) ?></dd></div>
    <div><dt>Active members</dt><dd><?= e($totals['active_members']) ?></dd></div>
    <div><dt>Out on loan</dt><dd><?= e($totals['out_now']) ?></dd></div>
    <div class="tally__alert"><dt>Overdue</dt><dd><?= e($totals['overdue']) ?></dd></div>
</dl>
<?php page_todo('Dashboard and reports', [
    'Add the overdue list with a fine for each loan.',
    'Add a chart of loans per category.',
    'Add CSV export for the books and loans lists.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
