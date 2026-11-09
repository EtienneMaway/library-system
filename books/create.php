<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Add a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Add a book</h1>
<?php page_todo('Books', [
    'Form with author and category dropdowns.',
    'Validate on the server: ISBN format and unique, year, copies.',
    'Set available copies equal to total copies.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
