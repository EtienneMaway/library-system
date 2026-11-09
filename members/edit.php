<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Edit a member';
require __DIR__ . '/../includes/header.php';
?>
<h1>Edit a member</h1>
<?php page_todo('Members', [
    'Edit details and switch between active and suspended.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
