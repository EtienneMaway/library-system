<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Issue a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Issue a book</h1>
<?php page_todo('Issuing loans', [
    'Refuse: no copies left, suspended member, same book already out to this member.',
    'Set the due date to 14 days after the issue date.',
    'Insert the loan and reduce the copies in one transaction.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
