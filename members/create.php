<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/member_form.php';

$errors = [];
$form = [
    'member_number' => next_member_number($pdo),
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'status' => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = member_form_input();
    $errors = member_form_errors($pdo, $form);

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO members (member_number, full_name, email, phone, status, date_joined)
                 VALUES (?, ?, ?, ?, ?, CURRENT_DATE)'
            );
            $stmt->execute([$form['member_number'], $form['full_name'], $form['email'], $form['phone'], $form['status']]);

            flash('success', $form['full_name'] . ' joined as ' . $form['member_number'] . '.');
            redirect('members/index.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                error_log('Member insert failed: ' . $e->getMessage());
                throw $e;
            }
            $errors = member_duplicate_error($e);
        }
    }

    flash_now('error', count($errors) === 1 ? 'Fix the highlighted field to add the member.' : 'Fix the ' . count($errors) . ' highlighted fields to add the member.');
}

$page_title = 'Add a member';
require __DIR__ . '/../includes/header.php';
?>
<h1>Add a member</h1>

<form method="post" class="form" data-busy-form>
    <?= csrf_field() ?>
    <?= member_fields($form, $errors) ?>
    <div class="form__actions">
        <button class="btn" type="submit" data-busy-label="Saving…">Add member</button>
        <a href="<?= e(url('members/index.php')) ?>">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
