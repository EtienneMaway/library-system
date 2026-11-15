<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
}

$author_id = filter_input(
    $_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($author_id === false || $author_id === null || $author_id < 1) {
    flash('error', 'Please select a valid author to edit.');
    redirect('authors/index.php');
}

try {
    $author_stmt = $pdo->prepare('SELECT name, country FROM authors WHERE author_id = ?');
    $author_stmt->execute([$author_id]);
    $author = $author_stmt->fetch();
} catch (PDOException $e) {
    error_log('Unable to load author for editing: ' . $e->getMessage());
    http_response_code(503);
    die('The author could not be loaded. Please try again later.');
}

if ($author === false) {
    flash('error', 'That author does not exist.');
    redirect('authors/index.php');
}

$errors = [];
$name = $author['name'];
$country = $author['country'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = post_text('name');
    $country = post_text('country');

    $errors = array_filter([
        validate_required($name, 'Name', 120),
        validate_required($country, 'Country', 80),
    ]);

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('UPDATE authors SET name = ?, country = ? WHERE author_id = ?');
            $stmt->execute([$name, $country, $author_id]);

            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare('SELECT author_id FROM authors WHERE author_id = ?');
                $check->execute([$author_id]);
                if ($check->fetch() === false) {
                    flash('error', 'That author no longer exists.');
                    redirect('authors/index.php');
                }
            }

            flash('success', 'Author updated successfully.');
            redirect('authors/index.php');
        } catch (PDOException $e) {
            if (is_duplicate_error($e)) {
                $errors[] = 'That author is already on the list.';
            } else {
                error_log('Author update failed: ' . $e->getMessage());
                $errors[] = 'The author could not be saved. Please try again.';
            }
        }
    }
}

$page_title = 'Edit an author';
require __DIR__ . '/../includes/header.php';
?>
<h1>Edit an author</h1>

<?php foreach ($errors as $error) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endforeach; ?>

<form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $author_id ?>">

    <label for="name">Name</label>
    <input id="name" name="name" value="<?= e($name) ?>" maxlength="120" required>

    <label for="country">Country</label>
    <input id="country" name="country" value="<?= e($country) ?>" maxlength="80" required>

    <button class="btn" type="submit">Save changes</button>
    <a href="<?= e(url('authors/index.php')) ?>">Cancel</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
