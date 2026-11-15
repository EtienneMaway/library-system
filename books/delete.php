<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$book_id = filter_input($_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$book_id || $book_id < 1) {
    flash('error', 'Choose a book to delete.');
    redirect('books/index.php');
}

// copies_out: loans not returned yet. past_loans: returned ones, which keep the book as part of the loan history.
$load = 'SELECT b.title, b.isbn,
                SUM(l.loan_id IS NOT NULL AND l.date_returned IS NULL) AS copies_out,
                SUM(l.date_returned IS NOT NULL) AS past_loans
         FROM books b
         LEFT JOIN loans l ON l.book_id = b.book_id
         WHERE b.book_id = ?
         GROUP BY b.book_id, b.title, b.isbn';

/** Why this book cannot be deleted, or null when it can. */
function delete_block_reason(array $book): ?string
{
    $out = (int) $book['copies_out'];
    if ($out > 0) {
        return '"' . $book['title'] . '" cannot be deleted: ' . $out . ' ' . ($out === 1 ? 'copy is' : 'copies are')
            . ' still out on loan. Return ' . ($out === 1 ? 'it' : 'them') . ' first.';
    }
    if ((int) $book['past_loans'] > 0) {
        return '"' . $book['title'] . '" cannot be deleted: it appears in past loans, and those records must keep it.';
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        // Lock the book so nobody issues it between the check and the delete.
        $pdo->beginTransaction();
        $pdo->prepare('SELECT book_id FROM books WHERE book_id = ? FOR UPDATE')->execute([$book_id]);
        $stmt = $pdo->prepare($load);
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();

        $reason = $book ? delete_block_reason($book) : 'That book is not in the catalogue.';
        if ($reason !== null) {
            $pdo->rollBack();
            flash('error', $reason);
            redirect('books/index.php');
        }

        $pdo->prepare('DELETE FROM books WHERE book_id = ?')->execute([$book_id]);
        $pdo->commit();
        flash('success', '"' . $book['title'] . '" deleted.');
        redirect('books/index.php');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!is_in_use_error($e)) {
            error_log('Book delete failed: ' . $e->getMessage());
            throw $e;
        }
        // The loans table refused it (ON DELETE RESTRICT): a loan was recorded since we checked.
        flash('error', 'That book cannot be deleted because loans refer to it.');
        redirect('books/index.php');
    }
}

$stmt = $pdo->prepare($load);
$stmt->execute([$book_id]);
$book = $stmt->fetch();
if (!$book) {
    flash('error', 'That book is not in the catalogue.');
    redirect('books/index.php');
}
$reason = delete_block_reason($book);

$page_title = 'Delete a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete a book</h1>

<?php if ($reason !== null) : ?>
    <p class="flash flash--error" role="alert"><?= e($reason) ?></p>
    <p><a href="<?= e(url('books/index.php')) ?>">Back to books</a></p>
<?php else : ?>
    <p>Delete <strong><?= e($book['title']) ?></strong> (ISBN <?= e($book['isbn']) ?>)? This cannot be undone.</p>
    <form method="post" class="form" data-busy-form>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $book_id ?>">
        <div class="form__actions">
            <button class="btn btn--danger" type="submit" data-busy-label="Deleting…">Delete book</button>
            <a href="<?= e(url('books/index.php')) ?>">Cancel</a>
        </div>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
