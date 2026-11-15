<?php
// Guarded delete: a category that still has books stays. fk_books_category (ON DELETE RESTRICT) backs this up.
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
    flash('error', 'Please select a valid category to delete.');
    redirect('categories/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $category_stmt = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = ? FOR UPDATE');
        $category_stmt->execute([$category_id]);
        if ($category_stmt->fetch() === false) {
            $pdo->rollBack();
            flash('error', 'That category no longer exists.');
            redirect('categories/index.php');
        }

        $books_stmt = $pdo->prepare('SELECT COUNT(*) FROM books WHERE category_id = ?');
        $books_stmt->execute([$category_id]);
        if ((int) $books_stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            flash('error', 'This category cannot be deleted while books are filed under it.');
            redirect('categories/index.php');
        }

        $delete_stmt = $pdo->prepare('DELETE FROM categories WHERE category_id = ?');
        $delete_stmt->execute([$category_id]);
        $pdo->commit();

        flash('success', 'Category deleted successfully.');
        redirect('categories/index.php');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (is_in_use_error($e)) {
            flash('error', 'This category cannot be deleted while books are filed under it.');
        } else {
            error_log('Category delete failed: ' . $e->getMessage());
            flash('error', 'The category could not be deleted. Please try again.');
        }
        redirect('categories/index.php');
    }
}

try {
    $stmt = $pdo->prepare(
        'SELECT c.name, COUNT(b.book_id) AS titles
         FROM categories c
         LEFT JOIN books b ON b.category_id = c.category_id
         WHERE c.category_id = ?
         GROUP BY c.category_id, c.name'
    );
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();

    if ($category === false) {
        flash('error', 'That category does not exist.');
        redirect('categories/index.php');
    }
} catch (PDOException $e) {
    error_log('Unable to load category for deletion: ' . $e->getMessage());
    http_response_code(503);
    die('The category could not be loaded. Please try again later.');
}

$page_title = 'Delete a category';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete a category</h1>
<p>Are you sure you want to delete the category <strong><?= e($category['name']) ?></strong>?</p>

<?php if ((int) $category['titles'] > 0) : ?>
    <p class="flash flash--error" role="alert">
        This category has <?= e($category['titles']) ?> book(s) filed under it and cannot be deleted.
    </p>
    <p><a href="<?= e(url('categories/index.php')) ?>">Back to categories</a></p>
<?php else : ?>
    <form method="post" class="form" onsubmit="return confirm('Delete this category? This cannot be undone.');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $category_id ?>">
        <button class="btn btn--danger" type="submit">Delete category</button>
        <a href="<?= e(url('categories/index.php')) ?>">Cancel</a>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
