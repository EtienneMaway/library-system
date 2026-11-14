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
    flash('error', 'Please select a valid author to delete.');
    redirect('authors/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $author_stmt = $pdo->prepare('SELECT author_id FROM authors WHERE author_id = ? FOR UPDATE');
        $author_stmt->execute([$author_id]);
        if ($author_stmt->fetch() === false) {
            $pdo->rollBack();
            flash('error', 'That author no longer exists.');
            redirect('authors/index.php');
        }

        $books_stmt = $pdo->prepare('SELECT COUNT(*) FROM books WHERE author_id = ?');
        $books_stmt->execute([$author_id]);
        if ((int) $books_stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            flash('error', 'This author cannot be deleted while books are assigned to them.');
            redirect('authors/index.php');
        }

        $delete_stmt = $pdo->prepare('DELETE FROM authors WHERE author_id = ?');
        $delete_stmt->execute([$author_id]);
        $pdo->commit();

        flash('success', 'Author deleted successfully.');
        redirect('authors/index.php');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (is_in_use_error($e)) {
            flash('error', 'This author cannot be deleted while books are assigned to them.');
        } else {
            error_log('Author delete failed: ' . $e->getMessage());
            flash('error', 'The author could not be deleted. Please try again.');
        }
        redirect('authors/index.php');
    }
}

try {
    $stmt = $pdo->prepare(
        'SELECT a.name, a.country, COUNT(b.book_id) AS titles
         FROM authors a
         LEFT JOIN books b ON b.author_id = a.author_id
         WHERE a.author_id = ?
         GROUP BY a.author_id, a.name, a.country'
    );
    $stmt->execute([$author_id]);
    $author = $stmt->fetch();

    if ($author === false) {
        flash('error', 'That author does not exist.');
        redirect('authors/index.php');
    }
} catch (PDOException $e) {
    error_log('Unable to load author for deletion: ' . $e->getMessage());
    http_response_code(503);
    die('The author could not be loaded. Please try again later.');
}

$page_title = 'Delete an author';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete an author</h1>
<p>Are you sure you want to delete <strong><?= e($author['name']) ?></strong> from <?= e($author['country']) ?>?</p>

<?php if ((int) $author['titles'] > 0) : ?>
    <p class="flash flash--error" role="alert">
        This author has <?= e($author['titles']) ?> book(s) assigned and cannot be deleted.
    </p>
    <p><a href="<?= e(url('authors/index.php')) ?>">Back to authors</a></p>
<?php else : ?>
    <form method="post" class="form" onsubmit="return confirm('Delete this author? This cannot be undone.');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $author_id ?>">
        <button class="btn btn--danger" type="submit">Delete author</button>
        <a href="<?= e(url('authors/index.php')) ?>">Cancel</a>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
