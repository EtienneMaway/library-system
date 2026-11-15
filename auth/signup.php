<?php
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard/index.php');
}

$full_name = '';
$username = '';
$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/mailer.php';

    $full_name = post_text('full_name');
    $username  = strtolower(post_text('username'));
    $email     = strtolower(post_text('email'));
    $password  = post_password('password');
    $confirm   = post_password('password_confirm');

    $errors = array_filter([
        'full_name'        => validate_required($full_name, 'Full name', 120),
        'username'         => validate_username($username),
        'email'            => validate_required($email, 'Email', 190) ?? validate_email($email),
        'password'         => validate_new_password($password, $password),
        'password_confirm' => $password !== $confirm ? 'The two passwords do not match.' : null,
    ]);

    // Check for taken names up front, so every problem shows at once. The UNIQUE keys still catch a race.
    $stmt = $pdo->prepare('SELECT username = ? AS username_taken, email = ? AS email_taken FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email, $username, $email]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['username_taken'] && !isset($errors['username'])) {
            $errors['username'] = 'That username is taken. Choose another.';
        }
        if ($row['email_taken'] && !isset($errors['email'])) {
            $errors['email'] = 'That email already has an account. Log in, or reset your password.';
        }
    }

    if (!$errors) {
        try {
            // Everyone who signs up is a normal user. Make someone admin in the database.
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, full_name, role) VALUES (?, ?, ?, ?, 'librarian')");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, $full_name]);
            $user = ['user_id' => (int) $pdo->lastInsertId(), 'email' => $email, 'full_name' => $full_name];

            $_SESSION['verify_user_id'] = $user['user_id'];
            $sent = send_code($pdo, $user, 'verify');
            flash($sent['type'], 'Account created. ' . $sent['message']);
            redirect('auth/verify_email.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                error_log('Sign-up failed: ' . $e->getMessage());
                throw $e;
            }
            $errors['username'] = 'That username or email was just taken. Try again.';
        }
    }

    // A successful sign-up has redirected by now, so there are errors to point at.
    flash_now('error', count($errors) === 1 ? 'Fix the highlighted field to continue.' : 'Fix the ' . count($errors) . ' highlighted fields to continue.');
}

$first_error = array_key_first($errors) ?? 'full_name';

$page_title = 'Sign up';
$aside_note = 'Create your staff account in a minute. We only email you a code to confirm it is you.';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <?php require __DIR__ . '/../includes/auth_aside.php'; ?>
    <section class="auth__card" aria-labelledby="auth-title">
        <p class="auth__eyebrow">New account</p>
        <h1 class="auth__title" id="auth-title">Create your account</h1>
        <p class="auth__lede">You will confirm your email with a 6-digit code next.</p>

        <form method="post" class="auth__form" data-busy-form>
            <?= csrf_field() ?>
            <div class="field">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" value="<?= e($full_name) ?>" maxlength="120" autocomplete="name"
                    required<?= $first_error === 'full_name' ? ' autofocus' : '' ?><?= field_attrs($errors, 'full_name') ?>>
                <?= field_error($errors, 'full_name') ?>
            </div>

            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" value="<?= e($username) ?>" maxlength="40" pattern="[a-zA-Z0-9._\-]{3,40}"
                    autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="username-hint"
                    required<?= $first_error === 'username' ? ' autofocus' : '' ?><?= field_attrs($errors, 'username') ?>>
                <p class="field__hint" id="username-hint">3 to 40 letters, numbers, dots, dashes or underscores.</p>
                <?= field_error($errors, 'username') ?>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e($email) ?>" maxlength="190" autocomplete="email"
                    required<?= $first_error === 'email' ? ' autofocus' : '' ?><?= field_attrs($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="field__password">
                    <input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password"
                        data-strength="password-strength" required<?= $first_error === 'password' ? ' autofocus' : '' ?><?= field_attrs($errors, 'password') ?>>
                    <button type="button" class="field__reveal" data-reveal="password" aria-pressed="false">Show</button>
                </div>
                <div class="strength" id="password-strength" aria-live="polite"><span class="strength__bar"></span><span class="strength__label">At least 8 characters</span></div>
                <?= field_error($errors, 'password') ?>
            </div>

            <div class="field">
                <label for="password_confirm">Type the password again</label>
                <div class="field__password">
                    <input id="password_confirm" name="password_confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password"
                        data-match="password" required<?= $first_error === 'password_confirm' ? ' autofocus' : '' ?><?= field_attrs($errors, 'password_confirm') ?>>
                    <button type="button" class="field__reveal" data-reveal="password_confirm" aria-pressed="false">Show</button>
                </div>
                <?= field_error($errors, 'password_confirm') ?>
            </div>

            <button class="btn btn--block" type="submit" data-busy-label="Creating your account…">Create account</button>
        </form>

        <p class="auth__switch">Already have an account? <a href="<?= e(url('auth/login.php')) ?>">Log in</a></p>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
