<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$member = null;
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod === 'POST') {
    require_csrf();
}

$memberId = $requestMethod === 'POST'
    ? post_text('member_id')
    : ($_GET['id'] ?? '');
$validMemberId = is_string($memberId)
    ? filter_var($memberId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]])
    : false;

$memberNumber = '';
$fullName = '';
$email = '';
$phone = '';
$status = 'active';

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    $errors[] = 'This request method is not allowed.';
} elseif ($validMemberId === false) {
    http_response_code(400);
    $errors[] = 'A valid member ID is required.';
} else {
    $memberId = (int) $validMemberId;
    $stmt = $pdo->prepare(
        'SELECT member_id, member_number, full_name, email, phone, status
         FROM members
         WHERE member_id = ?'
    );
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();

    if (!$member) {
        http_response_code(404);
        $errors[] = 'That member could not be found.';
    } else {
        $memberNumber = $member['member_number'];
        $fullName = $member['full_name'];
        $email = $member['email'];
        $phone = $member['phone'];
        $status = $member['status'];

        if ($requestMethod === 'POST') {
            $memberNumber = post_text('member_number');
            $fullName = post_text('full_name');
            $email = post_text('email');
            $phone = post_text('phone');
            $status = post_text('status');

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
                in_array($status, ['active', 'suspended'], true)
                    ? null
                    : 'Choose a valid member status.',
            ]);

            if (!$errors) {
                try {
                    $stmt = $pdo->prepare(
                        'UPDATE members
                         SET member_number = ?, full_name = ?, email = ?, phone = ?, status = ?
                         WHERE member_id = ?'
                    );
                    $stmt->execute([$memberNumber, $fullName, $email, $phone, $status, $memberId]);
                    flash('success', 'Member updated successfully.');
                    redirect('members/index.php');
                } catch (PDOException $e) {
                    if (is_duplicate_error($e)) {
                        $errors[] = 'That email address or member number is already registered.';
                    } else {
                        error_log($e->getMessage());
                        $errors[] = 'The member could not be updated. Please try again.';
                    }
                }
            }
        }
    }
}

$page_title = 'Edit a member';
require __DIR__ . '/../includes/header.php';
?>
<h1>Edit a member</h1>

<?php foreach ($errors as $error) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endforeach; ?>

<?php if ($member) : ?>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="member_id" value="<?= e($member['member_id']) ?>">

        <label for="member_number">Member number</label>
        <input id="member_number" name="member_number" value="<?= e($memberNumber) ?>" maxlength="20" required>

        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="<?= e($fullName) ?>" maxlength="120" autocomplete="name" required>

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($email) ?>" maxlength="160" autocomplete="email" required>

        <label for="phone">Phone</label>
        <input id="phone" name="phone" type="tel" value="<?= e($phone) ?>" maxlength="20" autocomplete="tel" required>

        <label for="status">Status</label>
        <select id="status" name="status" required>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>

        <button class="btn" type="submit">Save changes</button>
        <a class="btn btn--quiet" href="<?= e(url('members/index.php')) ?>">Cancel</a>
    </form>
<?php endif; ?>

<p><a class="btn btn--quiet" href="<?= e(url('members/index.php')) ?>">Back to members</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
