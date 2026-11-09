<?php
// Worked example of the page pattern. Copy this shape for the other lists:
// auth check, handle POST, validate on the server, prepared statements,
// flash a message, redirect, then query and print with e().
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$name = '';
$country = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name    = post_text('name');
    $country = post_text('country');

    $errors = array_filter([
        validate_required($name, 'Name', 120),
        validate_required($country, 'Country', 80),
    ]);

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO authors (name, country) VALUES (?, ?)');
            $stmt->execute([$name, $country]);
            flash('success', 'Author added successfully.');
            redirect('authors/index.php');
        } catch (PDOException $e) {
            if (is_duplicate_error($e)) {
                $errors[] = 'That author is already on the list.';
            } else {
                error_log($e->getMessage());
                $errors[] = 'The author could not be saved. Please try again.';
            }
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$stmt = $pdo->prepare(
    'SELECT a.name, a.country, COUNT(b.book_id) AS titles
     FROM authors a
     LEFT JOIN books b ON b.author_id = a.author_id
     WHERE a.name LIKE ? OR a.country LIKE ?
     GROUP BY a.author_id, a.name, a.country
     ORDER BY a.name'
);
$like = '%' . $search . '%';
$stmt->execute([$like, $like]);
$authors = $stmt->fetchAll();

$page_title = 'Authors';
require __DIR__ . '/../includes/header.php';
?>
<h1>Authors</h1>

<form method="get" class="search" role="search">
    <label for="q">Search authors</label>
    <input id="q" name="q" value="<?= e($search) ?>" placeholder="Name or country">
    <button class="btn btn--quiet" type="submit">Search</button>
</form>

<div class="table-scroll">
    <table>
        <thead><tr><th>Name</th><th>Country</th><th class="num">Titles</th></tr></thead>
        <tbody>
        <?php foreach ($authors as $author) : ?>
            <tr>
                <td><?= e($author['name']) ?></td>
                <td><?= e($author['country']) ?></td>
                <td class="num"><?= e($author['titles']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$authors) : ?>
            <tr><td colspan="3">No authors match that search.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<h2>Add an author</h2>
<?php foreach ($errors as $error) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endforeach; ?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <label for="name">Name</label>
    <input id="name" name="name" value="<?= e($name) ?>" maxlength="120" required>

    <label for="country">Country</label>
    <input id="country" name="country" value="<?= e($country) ?>" maxlength="80" required>

    <button class="btn" type="submit">Add author</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
