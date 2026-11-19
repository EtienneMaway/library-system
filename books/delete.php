<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/delete_page.php';

// copies_out: loans not returned yet. past_loans: returned ones, which must keep the book.
$view = delete_page($pdo, [
    'back'    => 'books/index.php',
    'missing' => 'That book is not in the catalogue.',
    'lock'    => 'SELECT book_id FROM books WHERE book_id = ? FOR UPDATE',
    'load'    => 'SELECT b.title, b.isbn,
                         SUM(l.loan_id IS NOT NULL AND l.date_returned IS NULL) AS copies_out,
                         SUM(l.date_returned IS NOT NULL) AS past_loans
                  FROM books b
                  LEFT JOIN loans l ON l.book_id = b.book_id
                  WHERE b.book_id = ?
                  GROUP BY b.book_id, b.title, b.isbn',
    'delete'  => 'DELETE FROM books WHERE book_id = ?',
    'blocked' => function (array $book): ?string {
        $out = (int) $book['copies_out'];
        if ($out > 0) {
            return '"' . $book['title'] . '" cannot be deleted: ' . ($out === 1 ? '1 copy is' : "$out copies are")
                . ' still out on loan. Return ' . ($out === 1 ? 'it' : 'them') . ' first.';
        }
        if ((int) $book['past_loans'] > 0) {
            return '"' . $book['title'] . '" cannot be deleted: it appears in past loans, and those records must keep it.';
        }
        return null;
    },
    'done'    => fn (array $book): string => '"' . $book['title'] . '" deleted.',
    'in_use'  => 'That book cannot be deleted because loans refer to it.',
]);

$page_title = 'Delete a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete a book</h1>
<?= delete_confirmation($view, 'book', $view['row']['title'], $view['row']['isbn'] === null ? 'no ISBN' : 'ISBN ' . $view['row']['isbn']) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
