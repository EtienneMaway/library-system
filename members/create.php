<?php
require_once '../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$memberNumber = '';
$fullName = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $memberNumber = post_text('member_number');
    $fullName = post_text('full_name');
    $email = post_text('email');
    $phone = post_text('phone');

    $errors = array_filter([
        validate_required($memberNumber, 'Member number', 20),
        validate_required($fullName, 'Full name', 120),
        validate_required($email, 'Email', 160),
        $email !== '' ? validate_email($email) : null,
        validate_required($phone, 'Phone', 20),
        $phone !== '' && !preg_match('/^[0-9+().\-\s]+$/', $phone)
            ? 'Phone may contain only digits, spaces, +, -, and parentheses.'
            : null,
        $phone !== '' && !preg_match('/[0-9]/', $phone)
            ? 'Phone must contain at least one digit.'
            : null,
    ]);

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO members (member_number, full_name, email, phone, date_joined)
                 VALUES (?, ?, ?, ?, CURRENT_DATE)'
            );
            $stmt->execute([$memberNumber, $fullName, $email, $phone]);
            flash('success', 'Member added successfully.');
            redirect('members/index.php');
        } catch (PDOException $e) {
            if (is_duplicate_error($e)) {
                $errors[] = 'That email address or member number is already registered.';
            } else {
                error_log($e->getMessage());
                $errors[] = 'The member could not be saved. Please try again.';
            }
        }
    }
}

$page_title = 'Add a member';
require __DIR__ . '/../includes/header.php';
?>
<h1>Add a member</h1>

<?php foreach ($errors as $error) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endforeach; ?>

<form method="post" class="form">
    <?= csrf_field() ?>
    <label for="member_number">Member number</label>
    <input id="member_number" name="member_number" value="<?= e($memberNumber) ?>" maxlength="20" required>

    <label for="full_name">Full name</label>
    <input id="full_name" name="full_name" value="<?= e($fullName) ?>" maxlength="120" autocomplete="name" required>

    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="<?= e($email) ?>" maxlength="160" autocomplete="email" required>

    <label for="phone">Phone</label>
    <input id="phone" name="phone" type="tel" value="<?= e($phone) ?>" maxlength="20" autocomplete="tel" required>

    <button class="btn" type="submit">Add member</button>
    <a class="btn btn--quiet" href="<?= e(url('members/index.php')) ?>">Cancel</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
