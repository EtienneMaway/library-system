<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Edit a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Edit a book</h1>
<?php page_todo('Books', [
    'Load the book by id and show a clear message when it does not exist.',
    'Total copies cannot drop below the copies currently out.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
