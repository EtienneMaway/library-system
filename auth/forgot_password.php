<?php
// Step 1 of a password reset: email a code to the account's address.
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('auth/change_password.php');
}

$login = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $login = strtolower(post_text('username'));

    if ($login === '') {
        $errors['username'] = 'Enter your username or email.';
        flash_now('error', 'Enter your username or email to get a code.');
    } else {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../includes/mailer.php';

        $stmt = $pdo->prepare('SELECT user_id, email, full_name FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        // Say the same thing whether or not the account exists, so nobody can test which ones do.
        $_SESSION['reset_user_id'] = $user ? (int) $user['user_id'] : 0;
        $sent = $user ? send_code($pdo, $user, 'reset') : null;
        if ($sent !== null && $sent['type'] === 'error') {
            error_log('Reset code not sent for user ' . $user['user_id'] . ': ' . $sent['message']);
        }
        if ($sent !== null && $sent['type'] === 'info') {
            flash('info', $sent['message']); // Local mode only: the code, since no email went out.
        } else {
            flash('success', 'If that account exists, we emailed it a 6-digit code. It works for ' . CODE_MINUTES . ' minutes.');
        }
        redirect('auth/reset_password.php');
    }
}

$page_title = 'Forgot password';
$aside_note = 'It happens to everyone. A code by email gets you back in.';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <?php require __DIR__ . '/../includes/auth_aside.php'; ?>
    <section class="auth__card" aria-labelledby="auth-title">
        <p class="auth__eyebrow">Password reset</p>
        <h1 class="auth__title" id="auth-title">Forgot your password?</h1>
        <p class="auth__lede">Enter your username or email. We will email you a code to set a new password.</p>

        <form method="post" class="auth__form" data-busy-form>
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Username or email</label>
                <input id="username" name="username" value="<?= e($login) ?>" autocomplete="username"
                    autocapitalize="none" spellcheck="false" required autofocus<?= field_attrs($errors, 'username') ?>>
                <?= field_error($errors, 'username') ?>
            </div>
            <button class="btn btn--block" type="submit" data-busy-label="Sending the code…">Email me a code</button>
        </form>

        <p class="auth__switch">Remembered it? <a href="<?= e(url('auth/login.php')) ?>">Back to log in</a></p>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
