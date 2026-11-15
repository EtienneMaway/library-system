<?php

// Suspend or reactivate a member from the list. POST only: it changes data.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('members/index.php');
}
require_csrf();

$member_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$status = post_text('status');
$back = 'members/index.php' . (str_starts_with(post_text('back'), '?') ? post_text('back') : '');

if (!$member_id || !in_array($status, ['active', 'suspended'], true)) {
    flash('error', 'Choose a member and a status.');
    redirect('members/index.php');
}

$stmt = $pdo->prepare('UPDATE members SET status = ? WHERE member_id = ?');
$stmt->execute([$status, $member_id]);

$name = $pdo->prepare('SELECT full_name FROM members WHERE member_id = ?');
$name->execute([$member_id]);
$full_name = $name->fetchColumn();

if ($full_name === false) {
    flash('error', 'That member is not on the list.');
} elseif ($status === 'suspended') {
    flash('success', $full_name . ' is suspended and cannot borrow books until reactivated.');
} else {
    flash('success', $full_name . ' is active again and can borrow books.');
}
redirect($back);
