<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/reports.php';

$search = get_text('q');
$author_id = (int) get_text('author_id');
$category_id = (int) get_text('category_id');

$authors = author_choices($pdo);
$categories = category_choices($pdo);

// Each filter is skipped when it is empty (0 or ''), so one query covers every combination.
$from = ' FROM books b
          JOIN authors a ON a.author_id = b.author_id
          JOIN categories c ON c.category_id = b.category_id
          WHERE (? = \'\' OR b.title LIKE ?)
            AND (? = 0 OR b.author_id = ?)
            AND (? = 0 OR b.category_id = ?)';
$params = [$search, '%' . $search . '%', $author_id, $author_id, $category_id, $category_id];
$select = 'SELECT b.book_id, b.isbn, b.title, b.publisher, b.year_published, b.total_copies, b.available_copies,
                  a.name AS author_name, c.name AS category_name' . $from . ' ORDER BY b.title';

if (wants_csv()) {
    export_csv($pdo, $select, $params, 'books', ['Title', 'Author', 'Category', 'ISBN', 'Publisher', 'Year', 'Total copies', 'On shelf'], fn (array $book) => [
        $book['title'], $book['author_name'], $book['category_name'], $book['isbn'], $book['publisher'],
        $book['year_published'], $book['total_copies'], $book['available_copies'],
    ]);
}

$pages = paginate(count_rows($pdo, $from, $params));
$books = fetch_page($pdo, $select, $params, $pages);

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
    <?= search_actions($filtered, 'books/index.php') ?>
</form>

<div class="list-head">
    <p><?= list_count($pages['total'], 'book', 'books', $filtered, ' in the catalogue') ?></p>
    <?php if ($books) : ?>
        <?= csv_link() ?>
    <?php endif; ?>
</div>

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
                <td><?= $book['isbn'] === null ? '<small>None</small>' : e($book['isbn']) ?></td>
                <td class="num">
                    <?= e($book['available_copies']) ?> of <?= e($book['total_copies']) ?>
                    <?php if ($out > 0) : ?>
                        <br><span class="pill pill--out"><?= $out ?> on loan</span>
                    <?php endif; ?>
                </td>
                <td class="row-actions">
                    <?= edit_link('books', (int) $book['book_id']) ?>
                    <?= delete_link('books', (int) $book['book_id']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$books) : ?>
            <?= empty_row(6, $filtered ? 'No books match these filters.' : 'No books yet. Add the first one.') ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?= pagination_nav($pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
