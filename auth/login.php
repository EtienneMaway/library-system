<?php
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard/index.php');
}

$username = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_once __DIR__ . '/../config/database.php';

    $username = post_text('username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $stmt = $pdo->prepare('SELECT user_id, password_hash, full_name, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $account = $stmt->fetch();

    if ($account && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $account['user_id'];
        $_SESSION['full_name'] = $account['full_name'];
        $_SESSION['role']      = $account['role'];
        flash('success', 'Welcome back, ' . $account['full_name'] . '.');
        redirect('dashboard/index.php');
    }

    $error = 'That username and password do not match.';
}

$page_title = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<section class="slip">
    <h1 class="slip__title">Library</h1>
    <p class="slip__sub">Staff sign-in</p>

    <?php if ($error): ?>
        <p class="flash flash--error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button class="btn" type="submit">Log in</button>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
