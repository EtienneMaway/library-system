<?php
// Same pattern as authors/index.php: list with search, and an add form.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$name = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name        = post_text('name');
    $description = post_text('description');

    $errors = array_filter([
        validate_required($name, 'Name', 80),
        mb_strlen($description) > 255 ? 'Description must be 255 characters or fewer.' : null,
    ]);

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $stmt->execute([$name, $description !== '' ? $description : null]);
            flash('success', 'Category added successfully.');
            redirect('categories/index.php');
        } catch (PDOException $e) {
            if (is_duplicate_error($e)) {
                $errors[] = 'That category is already on the list.';
            } else {
                error_log('Category insert failed: ' . $e->getMessage());
                $errors[] = 'The category could not be saved. Please try again.';
            }
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$stmt = $pdo->prepare(
    'SELECT c.category_id, c.name, c.description, COUNT(b.book_id) AS titles
     FROM categories c
     LEFT JOIN books b ON b.category_id = c.category_id
     WHERE c.name LIKE ? OR c.description LIKE ?
     GROUP BY c.category_id, c.name, c.description
     ORDER BY c.name'
);
$like = '%' . $search . '%';
$stmt->execute([$like, $like]);
$categories = $stmt->fetchAll();

$page_title = 'Categories';
require __DIR__ . '/../includes/header.php';
?>
<h1>Categories</h1>

<form method="get" class="search" role="search">
    <label for="q">Search categories</label>
    <input id="q" name="q" value="<?= e($search) ?>" placeholder="Name or description">
    <button class="btn btn--quiet" type="submit">Search</button>
</form>

<div class="table-scroll">
    <table>
        <thead><tr><th>Name</th><th>Description</th><th class="num">Titles</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $category) : ?>
            <tr>
                <td><?= e($category['name']) ?></td>
                <td><?= e($category['description'] ?? '') ?></td>
                <td class="num"><?= e($category['titles']) ?></td>
                <td>
                    <a href="<?= e(url('categories/edit.php?id=' . (int) $category['category_id'])) ?>">Edit</a>
                    <a href="<?= e(url('categories/delete.php?id=' . (int) $category['category_id'])) ?>">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$categories) : ?>
            <tr><td colspan="4">No categories match that search.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<h2>Add a category</h2>
<?php foreach ($errors as $error) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endforeach; ?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <label for="name">Name</label>
    <input id="name" name="name" value="<?= e($name) ?>" maxlength="80" required>

    <label for="description">Description (optional)</label>
    <input id="description" name="description" value="<?= e($description) ?>" maxlength="255">

    <button class="btn" type="submit">Add category</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
