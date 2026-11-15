<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/member_form.php';

$member_id = filter_input($_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$member_id || $member_id < 1) {
    flash('error', 'Choose a member to edit.');
    redirect('members/index.php');
}

$stmt = $pdo->prepare(
    'SELECT m.member_number, m.full_name, m.email, m.phone, m.status,
            (SELECT COUNT(*) FROM loans l WHERE l.member_id = m.member_id AND l.date_returned IS NULL) AS books_out
     FROM members m WHERE m.member_id = ?'
);
$stmt->execute([$member_id]);
$member = $stmt->fetch();
if (!$member) {
    flash('error', 'That member is not on the list.');
    redirect('members/index.php');
}

$errors = [];
$form = [
    'member_number' => $member['member_number'],
    'full_name' => $member['full_name'],
    'email' => $member['email'],
    'phone' => $member['phone'],
    'status' => $member['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = member_form_input();
    $errors = member_form_errors($pdo, $form, $member_id);

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE members SET member_number = ?, full_name = ?, email = ?, phone = ?, status = ? WHERE member_id = ?'
            );
            $stmt->execute([$form['member_number'], $form['full_name'], $form['email'], $form['phone'], $form['status'], $member_id]);

            flash('success', $form['full_name'] . ' saved.');
            redirect('members/index.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                error_log('Member update failed: ' . $e->getMessage());
                throw $e;
            }
            $errors = member_duplicate_error($e);
        }
    }

    flash_now('error', count($errors) === 1 ? 'Fix the highlighted field to save the member.' : 'Fix the ' . count($errors) . ' highlighted fields to save the member.');
}

$books_out = (int) $member['books_out'];

$page_title = 'Edit a member';
require __DIR__ . '/../includes/header.php';
?>
<h1>Edit a member</h1>
<p>
    <?= $books_out === 0 ? 'No books out right now.' : e('Books out right now: ' . $books_out . '.') ?>
    A suspended member keeps the books they have but cannot borrow more.
</p>

<form method="post" class="form" data-busy-form>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $member_id ?>">
    <?= member_fields($form, $errors) ?>
    <div class="form__actions">
        <button class="btn" type="submit" data-busy-label="Saving…">Save changes</button>
        <a href="<?= e(url('members/index.php')) ?>">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
