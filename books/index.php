<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Books';
require __DIR__ . '/../includes/header.php';
?>
<h1>Books</h1>
<?php page_todo('Books', [
    'List books with author, category and copies, using a JOIN.',
    'Search by title and filter by author and category.',
    'Copy the pattern in authors/index.php.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
