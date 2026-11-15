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

$category_id = filter_input(
    $_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($category_id === false || $category_id === null || $category_id < 1) {
    flash('error', 'Please select a valid category to edit.');
    redirect('categories/index.php');
}

try {
    $category_stmt = $pdo->prepare('SELECT name, description FROM categories WHERE category_id = ?');
    $category_stmt->execute([$category_id]);
    $category = $category_stmt->fetch();
} catch (PDOException $e) {
    error_log('Unable to load category for editing: ' . $e->getMessage());
    http_response_code(503);
    die('The category could not be loaded. Please try again later.');
}

if ($category === false) {
    flash('error', 'That category does not exist.');
    redirect('categories/index.php');
}

$errors = [];
$name = $category['name'];
$description = $category['description'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = post_text('name');
    $description = post_text('description');

    $errors = array_filter([
        validate_required($name, 'Name', 80),
        mb_strlen($description) > 255 ? 'Description must be 255 characters or fewer.' : null,
    ]);

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE category_id = ?');
            $stmt->execute([$name, $description !== '' ? $description : null, $category_id]);
            flash('success', 'Category updated successfully.');
            redirect('categories/index.php');
        } catch (PDOException $e) {
            if (is_duplicate_error($e)) {
                $errors[] = 'Another category already has that name.';
            } else {
                error_log('Category update failed: ' . $e->getMessage());
                $errors[] = 'The category could not be saved. Please try again.';
            }
        }
    }
}

$page_title = 'Edit a category';
require __DIR__ . '/../includes/header.php';
?>
<h1>Edit a category</h1>

<?php foreach ($errors as $error) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endforeach; ?>

<form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $category_id ?>">

    <label for="name">Name</label>
    <input id="name" name="name" value="<?= e($name) ?>" maxlength="80" required>

    <label for="description">Description (optional)</label>
    <input id="description" name="description" value="<?= e($description) ?>" maxlength="255">

    <button class="btn" type="submit">Save changes</button>
    <a href="<?= e(url('categories/index.php')) ?>">Cancel</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
