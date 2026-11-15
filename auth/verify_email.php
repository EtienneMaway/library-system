<?php
// Step 2 of sign-up: type the code we emailed. Login sends people here too while their email is unconfirmed.
require_once __DIR__ . '/../includes/functions.php';

$user_id = (int) ($_SESSION['verify_user_id'] ?? 0);
if ($user_id === 0) {
    flash('info', 'Log in to confirm your email.');
    redirect('auth/login.php');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

$stmt = $pdo->prepare('SELECT user_id, email, full_name, role, email_verified_at FROM users WHERE user_id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user || $user['email_verified_at'] !== null) {
    unset($_SESSION['verify_user_id']);
    if ($user) {
        flash('info', 'Your email is already confirmed. Log in to continue.');
    }
    redirect('auth/login.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (post_text('action') === 'resend') {
        $sent = send_code($pdo, $user, 'verify');
        flash($sent['type'], $sent['message']);
        redirect('auth/verify_email.php');
    }

    $code = preg_replace('/\D/', '', post_text('code')) ?? '';
    $error = strlen($code) !== 6 ? 'Enter the 6 digits from the email.' : check_code($pdo, $user_id, 'verify', $code);
    if ($error === null) {
        $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE user_id = ?')->execute([$user_id]);
        unset($_SESSION['verify_user_id']);
        log_in($user);
        flash('success', 'Email confirmed. Welcome to the Library, ' . $user['full_name'] . '.');
        redirect('dashboard/index.php');
    }
    $errors['code'] = $error;
    flash_now('error', $error);
}

$page_title = 'Confirm your email';
$aside_note = 'One last step. The code proves the email address is really yours.';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <?php require __DIR__ . '/../includes/auth_aside.php'; ?>
    <section class="auth__card" aria-labelledby="auth-title">
        <p class="auth__eyebrow">Step 2 of 2</p>
        <h1 class="auth__title" id="auth-title">Check your email</h1>
        <p class="auth__lede">We sent a 6-digit code to <strong><?= e($user['email']) ?></strong>. It works for <?= CODE_MINUTES ?> minutes.</p>

        <form method="post" class="auth__form" data-busy-form>
            <?= csrf_field() ?>
            <div class="field">
                <label for="code">Confirmation code</label>
                <input id="code" name="code" class="field__code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7"
                    placeholder="000 000" autocomplete="one-time-code" required autofocus<?= field_attrs($errors, 'code') ?>>
                <?= field_error($errors, 'code') ?>
            </div>
            <button class="btn btn--block" type="submit" data-busy-label="Checking…">Confirm email</button>
        </form>

        <form method="post" class="auth__switch" data-busy-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resend">
            No email? Check your spam folder, or
            <button class="link-button" type="submit" data-busy-label="Sending…">send a new code</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
