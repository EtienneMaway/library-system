<?php
// Step 2 of a password reset: the emailed code plus the new password.
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['reset_user_id'])) {
    redirect('auth/forgot_password.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/mailer.php';

    $user_id = (int) $_SESSION['reset_user_id'];
    $code    = preg_replace('/\D/', '', post_text('code')) ?? '';
    $new     = post_password('new_password');
    $confirm = post_password('new_password_confirm');

    $errors = array_filter([
        'code'                 => strlen($code) !== 6 ? 'Enter the 6 digits from the email.' : null,
        'new_password'         => validate_new_password($new, $new),
        'new_password_confirm' => $new !== $confirm ? 'The two passwords do not match.' : null,
    ]);

    if (!$errors) {
        $code_error = $user_id === 0
            ? 'That code is wrong. Check the email and try again.' // No such account: answer like a wrong code.
            : check_code($pdo, $user_id, 'reset', $code);
        if ($code_error !== null) {
            $errors['code'] = $code_error;
        }
    }

    if (!$errors) {
        // Getting the code proves they own the email, so it counts as confirmed too.
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE user_id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
        unset($_SESSION['reset_user_id']);
        flash('success', 'Your password is reset. Log in with the new one.');
        redirect('auth/login.php');
    }

    flash_now('error', $errors['code'] ?? 'Fix the highlighted fields to continue.');
}

$first_error = array_key_first($errors) ?? 'code';

$page_title = 'Reset password';
$aside_note = 'Choose something new that you have not used here before.';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <?php require __DIR__ . '/../includes/auth_aside.php'; ?>
    <section class="auth__card" aria-labelledby="auth-title">
        <p class="auth__eyebrow">Password reset</p>
        <h1 class="auth__title" id="auth-title">Set a new password</h1>
        <p class="auth__lede">Type the code from the email, then your new password.</p>

        <form method="post" class="auth__form" data-busy-form>
            <?= csrf_field() ?>
            <div class="field">
                <label for="code">Code from the email</label>
                <input id="code" name="code" class="field__code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7"
                    placeholder="000 000" autocomplete="one-time-code" required<?= $first_error === 'code' ? ' autofocus' : '' ?><?= field_attrs($errors, 'code') ?>>
                <?= field_error($errors, 'code') ?>
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

            <button class="btn btn--block" type="submit" data-busy-label="Saving…">Reset password</button>
        </form>

        <p class="auth__switch">No email? Check spam, or <a href="<?= e(url('auth/forgot_password.php')) ?>">send a new code</a>.</p>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
