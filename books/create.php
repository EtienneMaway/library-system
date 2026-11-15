<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/book_form.php';

$authors = author_choices($pdo);
$categories = category_choices($pdo);

$errors = [];
$form = [
    'isbn' => '',
    'title' => '',
    'author_id' => '',
    'category_id' => '',
    'publisher' => '',
    'year_published' => '',
    'total_copies' => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = book_form_input();
    $errors = book_form_errors($form, $authors, $categories);

    if (!$errors) {
        try {
            // A new book has every copy on the shelf.
            $stmt = $pdo->prepare(
                'INSERT INTO books (isbn, title, author_id, category_id, publisher, year_published, total_copies, available_copies)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $form['isbn'],
                $form['title'],
                (int) $form['author_id'],
                (int) $form['category_id'],
                $form['publisher'],
                (int) $form['year_published'],
                (int) $form['total_copies'],
                (int) $form['total_copies'],
            ]);

            flash('success', '"' . $form['title'] . '" added to the catalogue.');
            redirect('books/index.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                error_log('Book insert failed: ' . $e->getMessage());
                throw $e;
            }
            $errors['isbn'] = 'Another book already has that ISBN.';
        }
    }

    flash_now('error', count($errors) === 1 ? 'Fix the highlighted field to save the book.' : 'Fix the ' . count($errors) . ' highlighted fields to save the book.');
}

$page_title = 'Add a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Add a book</h1>

<form method="post" class="form" data-busy-form>
    <?= csrf_field() ?>
    <?= book_fields($form, $errors, $authors, $categories) ?>
    <div class="form__actions">
        <button class="btn" type="submit" data-busy-label="Saving…">Add book</button>
        <a href="<?= e(url('books/index.php')) ?>">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
