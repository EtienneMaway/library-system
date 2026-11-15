<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$search = trim((string) ($_GET['q'] ?? ''));
$author_id = (int) ($_GET['author_id'] ?? 0);
$category_id = (int) ($_GET['category_id'] ?? 0);

$authors = author_choices($pdo);
$categories = category_choices($pdo);

// Each filter is skipped when it is empty (0 or ''), so one query covers every combination.
$stmt = $pdo->prepare(
    'SELECT b.book_id, b.isbn, b.title, b.publisher, b.year_published, b.total_copies, b.available_copies,
            a.name AS author_name, c.name AS category_name
     FROM books b
     JOIN authors a ON a.author_id = b.author_id
     JOIN categories c ON c.category_id = b.category_id
     WHERE (? = \'\' OR b.title LIKE ?)
       AND (? = 0 OR b.author_id = ?)
       AND (? = 0 OR b.category_id = ?)
     ORDER BY b.title'
);
$stmt->execute([$search, '%' . $search . '%', $author_id, $author_id, $category_id, $category_id]);
$books = $stmt->fetchAll();

$filtered = $search !== '' || $author_id > 0 || $category_id > 0;

$page_title = 'Books';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Books</h1>
    <a class="btn" href="<?= e(url('books/create.php')) ?>">Add a book</a>
</div>

<form method="get" class="search search--filters" role="search">
    <div>
        <label for="q">Title</label>
        <input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Search by title">
    </div>
    <div>
        <label for="author_id">Author</label>
        <select id="author_id" name="author_id"><?= select_options($authors, $author_id, 'All authors') ?></select>
    </div>
    <div>
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id"><?= select_options($categories, $category_id, 'All categories') ?></select>
    </div>
    <div class="search__actions">
        <button class="btn btn--quiet" type="submit">Search</button>
        <?php if ($filtered) : ?>
            <a href="<?= e(url('books/index.php')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<p><?= count($books) ?> <?= count($books) === 1 ? 'book' : 'books' ?><?= $filtered ? ' match' . (count($books) === 1 ? 'es' : '') . ' these filters' : ' in the catalogue' ?>.</p>

<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>Category</th>
                <th>ISBN</th>
                <th class="num">On shelf</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($books as $book) : ?>
            <?php $out = (int) $book['total_copies'] - (int) $book['available_copies']; ?>
            <tr>
                <td class="cell--wide">
                    <strong><?= e($book['title']) ?></strong><br>
                    <small><?= e($book['publisher']) ?>, <?= e($book['year_published']) ?></small>
                </td>
                <td><?= e($book['author_name']) ?></td>
                <td><?= e($book['category_name']) ?></td>
                <td><?= e($book['isbn']) ?></td>
                <td class="num">
                    <?= e($book['available_copies']) ?> of <?= e($book['total_copies']) ?>
                    <?php if ($out > 0) : ?>
                        <br><span class="pill pill--out"><?= $out ?> on loan</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= e(url('books/edit.php?id=' . (int) $book['book_id'])) ?>">Edit</a>
                    <a href="<?= e(url('books/delete.php?id=' . (int) $book['book_id'])) ?>">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$books) : ?>
            <tr><td colspan="6"><?= $filtered ? 'No books match these filters.' : 'No books yet. Add the first one.' ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
