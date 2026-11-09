<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Members';
require __DIR__ . '/../includes/header.php';
?>
<h1>Members</h1>
<?php page_todo('Members', [
    'List members with status and the number of books out.',
    'Search by name and number, filter by status.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
