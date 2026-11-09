<?php
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Delete a member';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete a member</h1>
<?php page_todo('Members', [
    'POST only, with a CSRF check and a confirmation prompt.',
    'Refuse when the member has unreturned books and explain why.',
]); ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
