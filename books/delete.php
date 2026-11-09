<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Delete a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete a book</h1>
<?php page_todo('Books', [
    'POST only, with a CSRF check and a confirmation prompt.',
    'Refuse when the book has unreturned loans and explain why.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
