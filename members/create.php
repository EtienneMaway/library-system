<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Add a member';
require __DIR__ . '/../includes/header.php';
?>
<h1>Add a member</h1>
<?php page_todo('Members', [
    'Validate name, email, phone and member number on the server.',
    'Show a friendly message for a duplicate email or member number.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
