<?php
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard/index.php');
}

$login = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // The field is called username, but an email works too. Usernames cannot contain @.
    $login = strtolower(post_text('username'));
    $password = post_password('password');

    if ($login === '') {
        $errors['username'] = 'Enter your username or email.';
    }
    if ($password === '') {
        $errors['password'] = 'Enter your password.';
    }

    if ($errors) {
        flash_now('error', 'Fill in both fields to log in.');
    } else {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../includes/mailer.php';

        $stmt = $pdo->prepare(
            'SELECT user_id, password_hash, email, email_verified_at, full_name, role FROM users WHERE username = ? OR email = ?'
        );
        $stmt->execute([$login, $login]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password_hash'])) {
            if ($account['email_verified_at'] === null) {
                // Right password, but the email is not confirmed yet: send a code and ask for it.
                $_SESSION['verify_user_id'] = (int) $account['user_id'];
                $sent = send_code($pdo, $account, 'verify');
                flash('info', 'Confirm your email to finish signing in.');
                flash($sent['type'], $sent['message']);
                redirect('auth/verify_email.php');
            }
            log_in($account);
            flash('success', 'Welcome back, ' . $account['full_name'] . '.');
            redirect('dashboard/index.php');
        }

        // One message for an unknown account and a wrong password, so nobody can test which accounts exist.
        $errors['password'] = 'Those details do not match an account.';
        flash_now('error', 'Those details do not match an account. Check your username or email, and your password.');
    }
}

$focus = isset($errors['password']) && !isset($errors['username']) ? 'password' : 'username';

$page_title = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <?php require __DIR__ . '/../includes/auth_aside.php'; ?>
    <section class="auth__card" aria-labelledby="auth-title">
        <p class="auth__eyebrow">Staff sign-in</p>
        <h1 class="auth__title" id="auth-title">Welcome back</h1>
        <p class="auth__lede">Log in with your username or your email address.</p>

        <form method="post" class="auth__form" data-busy-form>
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Username or email</label>
                <input id="username" name="username" value="<?= e($login) ?>" autocomplete="username"
                    autocapitalize="none" spellcheck="false" required<?= $focus === 'username' ? ' autofocus' : '' ?><?= field_attrs($errors, 'username') ?>>
                <?= field_error($errors, 'username') ?>
            </div>

            <div class="field">
                <div class="field__row">
                    <label for="password">Password</label>
                    <a class="field__link" href="<?= e(url('auth/forgot_password.php')) ?>">Forgot password?</a>
                </div>
                <div class="field__password">
                    <input id="password" name="password" type="password" autocomplete="current-password"
                        required<?= $focus === 'password' ? ' autofocus' : '' ?><?= field_attrs($errors, 'password') ?>>
                    <button type="button" class="field__reveal" data-reveal="password" aria-pressed="false">Show</button>
                </div>
                <?= field_error($errors, 'password') ?>
            </div>

            <button class="btn btn--block" type="submit" data-busy-label="Logging in…">Log in</button>
        </form>

        <p class="auth__switch">New here? <a href="<?= e(url('auth/signup.php')) ?>">Create an account</a></p>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
