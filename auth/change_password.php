<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $current = post_password('current_password');
    $new     = post_password('new_password');
    $confirm = post_password('new_password_confirm');

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();

    if ($hash === false || !password_verify($current, $hash)) {
        $errors['current_password'] = 'Your current password is wrong.';
    }
    $errors += array_filter([
        'new_password'         => validate_new_password($new, $new)
            ?? ($hash !== false && password_verify($new, $hash) ? 'Choose a password different from your current one.' : null),
        'new_password_confirm' => $new !== $confirm ? 'The two passwords do not match.' : null,
    ]);

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        session_regenerate_id(true);
        flash('success', 'Your password is updated. Use the new one next time you log in.');
        redirect('dashboard/index.php');
    }

    flash_now('error', count($errors) === 1 ? reset($errors) : 'Fix the ' . count($errors) . ' highlighted fields to continue.');
}

$first_error = array_key_first($errors) ?? 'current_password';

$page_title = 'Change password';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth__card auth__card--solo" aria-labelledby="auth-title">
    <p class="auth__eyebrow">Your account</p>
    <h1 class="auth__title" id="auth-title">Change your password</h1>
    <p class="auth__lede">You need your current password to set a new one.</p>

    <form method="post" class="auth__form" data-busy-form>
        <?= csrf_field() ?>
        <div class="field">
            <label for="current_password">Current password</label>
            <div class="field__password">
                <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                    required<?= $first_error === 'current_password' ? ' autofocus' : '' ?><?= field_attrs($errors, 'current_password') ?>>
                <button type="button" class="field__reveal" data-reveal="current_password" aria-pressed="false">Show</button>
            </div>
            <?= field_error($errors, 'current_password') ?>
        </div>

        <div class="field">
            <label for="new_password">New password</label>
            <div class="field__password">
                <input id="new_password" name="new_password" type="password" minlength="8" maxlength="72" autocomplete="new-password"
                    data-strength="new-password-strength" required<?= $first_error === 'new_password' ? ' autofocus' : '' ?><?= field_attrs($errors, 'new_password') ?>>
                <button type="button" class="field__reveal" data-reveal="new_password" aria-pressed="false">Show</button>
            </div>
            <div class="strength" id="new-password-strength" aria-live="polite"><span class="strength__bar"></span><span class="strength__label">At least 8 characters</span></div>
            <?= field_error($errors, 'new_password') ?>
        </div>

        <div class="field">
            <label for="new_password_confirm">Type the new password again</label>
            <div class="field__password">
                <input id="new_password_confirm" name="new_password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password"
                    data-match="new_password" required<?= $first_error === 'new_password_confirm' ? ' autofocus' : '' ?><?= field_attrs($errors, 'new_password_confirm') ?>>
                <button type="button" class="field__reveal" data-reveal="new_password_confirm" aria-pressed="false">Show</button>
            </div>
            <?= field_error($errors, 'new_password_confirm') ?>
        </div>

        <button class="btn btn--block" type="submit" data-busy-label="Saving…">Update password</button>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
