<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Return a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Return a book</h1>
<?php page_todo('Loan returns and history', [
    'POST only, with a CSRF check.',
    'Set the return date and add a copy back in one transaction.',
    'Refuse a loan that was already returned.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
