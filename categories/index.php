<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Categories';
require __DIR__ . '/../includes/header.php';
?>
<h1>Categories</h1>
<?php page_todo('Authors and categories', [
    'List categories and add a new one, following authors/index.php.',
    'Stop a category with books from being deleted, if you add delete.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
