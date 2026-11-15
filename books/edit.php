<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/book_form.php';

$book_id = filter_input($_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$book_id || $book_id < 1) {
    flash('error', 'Choose a book to edit.');
    redirect('books/index.php');
}

// copies_out counts the open loans, so the new available_copies is always the total minus those.
$load = 'SELECT b.book_id, b.isbn, b.title, b.author_id, b.category_id, b.publisher, b.year_published, b.total_copies,
                (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.book_id AND l.date_returned IS NULL) AS copies_out
         FROM books b WHERE b.book_id = ?';
$stmt = $pdo->prepare($load);
$stmt->execute([$book_id]);
$book = $stmt->fetch();
if (!$book) {
    flash('error', 'That book is not in the catalogue.');
    redirect('books/index.php');
}

$authors = author_choices($pdo);
$categories = category_choices($pdo);

$errors = [];
$form = [
    'isbn' => $book['isbn'],
    'title' => $book['title'],
    'author_id' => (string) $book['author_id'],
    'category_id' => (string) $book['category_id'],
    'publisher' => $book['publisher'],
    'year_published' => (string) $book['year_published'],
    'total_copies' => (string) $book['total_copies'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = book_form_input();

    try {
        // Lock the row so a loan issued meanwhile cannot slip in between the check and the update.
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($load . ' FOR UPDATE');
        $stmt->execute([$book_id]);
        $book = $stmt->fetch() ?: $book;

        $errors = book_form_errors($form, $authors, $categories, (int) $book['copies_out']);
        if (!$errors) {
            $total = (int) $form['total_copies'];
            $stmt = $pdo->prepare(
                'UPDATE books
                 SET isbn = ?, title = ?, author_id = ?, category_id = ?, publisher = ?, year_published = ?,
                     total_copies = ?, available_copies = ?
                 WHERE book_id = ?'
            );
            $stmt->execute([
                $form['isbn'],
                $form['title'],
                (int) $form['author_id'],
                (int) $form['category_id'],
                $form['publisher'],
                (int) $form['year_published'],
                $total,
                $total - (int) $book['copies_out'],
                $book_id,
            ]);
            $pdo->commit();

            flash('success', '"' . $form['title'] . '" saved.');
            redirect('books/index.php');
        }
        $pdo->rollBack();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!is_duplicate_error($e)) {
            error_log('Book update failed: ' . $e->getMessage());
            throw $e;
        }
        $errors['isbn'] = 'Another book already has that ISBN.';
    }

    flash_now('error', count($errors) === 1 ? 'Fix the highlighted field to save the book.' : 'Fix the ' . count($errors) . ' highlighted fields to save the book.');
}

$copies_out = (int) $book['copies_out'];

$page_title = 'Edit a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Edit a book</h1>
<p>
    <?= $copies_out === 0 ? 'Every copy is on the shelf.' : e('On loan now: ' . $copies_out . ' of ' . $book['total_copies'] . ' copies.') ?>
    Changing the total changes the copies on the shelf by the same amount.
</p>

<form method="post" class="form" data-busy-form>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $book_id ?>">
    <?= book_fields($form, $errors, $authors, $categories) ?>
    <div class="form__actions">
        <button class="btn" type="submit" data-busy-label="Saving…">Save changes</button>
        <a href="<?= e(url('books/index.php')) ?>">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
