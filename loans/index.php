<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Loans';
require __DIR__ . '/../includes/header.php';
?>
<h1>Loans</h1>
<?php page_todo('Loan returns and history', [
    'List loans with the member and book names.',
    'Filter by status: out, returned, overdue.',
    'Search by member name.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
