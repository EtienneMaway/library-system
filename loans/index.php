<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$status = $_GET['status'] ?? '';
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$statuses = ['out', 'returned', 'overdue'];
if (!is_string($status) || !in_array($status, $statuses, true)) {
    $status = '';
}

$conditions = [];
$params = [];
if ($status === 'out') {
    $conditions[] = 'l.date_returned IS NULL AND l.due_date >= CURDATE()';
} elseif ($status === 'returned') {
    $conditions[] = 'l.date_returned IS NOT NULL';
} elseif ($status === 'overdue') {
    $conditions[] = 'l.date_returned IS NULL AND l.due_date < CURDATE()';
}
if ($search !== '') {
    $conditions[] = '(m.full_name LIKE ? OR m.member_number LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
}

$sql = 'SELECT l.loan_id, l.date_issued, l.due_date, l.date_returned, CURDATE() AS today,
               b.title, m.member_number, m.full_name
        FROM loans l
        JOIN books b ON b.book_id = l.book_id
        JOIN members m ON m.member_id = l.member_id';
if ($conditions !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY l.date_returned IS NULL DESC, l.due_date, m.full_name, b.title';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();

$page_title = 'Loans';
require __DIR__ . '/../includes/header.php';
?>
<h1>Loans</h1>
<p>
    <a class="btn" href="<?= e(url('loans/issue.php')) ?>">Issue a book</a>
    <a class="btn btn--quiet" href="<?= e(url('loans/return.php')) ?>">Return a book</a>
</p>

<form method="get" class="search">
    <label for="search">Search by member name or number</label>
    <input id="search" name="search" value="<?= e($search) ?>" type="search">
    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="">All loans</option>
        <option value="out" <?= $status === 'out' ? 'selected' : '' ?>>Out</option>
        <option value="returned" <?= $status === 'returned' ? 'selected' : '' ?>>Returned</option>
        <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
    </select>
    <button class="btn" type="submit">Apply filters</button>
    <?php if ($search !== '' || $status !== '') : ?>
        <a class="btn btn--quiet" href="<?= e(url('loans/index.php')) ?>">Clear</a>
    <?php endif; ?>
</form>

<?php if ($loans === []) : ?>
    <p>No loans match these filters.</p>
<?php else : ?>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Book</th>
                    <th>Issued</th>
                    <th>Due</th>
                    <th>Returned</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loans as $loan) : ?>
                    <?php
                    if ($loan['date_returned'] !== null) {
                        $loan_status = 'returned';
                        $pill_class = 'pill--returned';
                    } elseif ($loan['due_date'] < $loan['today']) {
                        $loan_status = 'overdue';
                        $pill_class = 'pill--late';
                    } else {
                        $loan_status = 'out';
                        $pill_class = 'pill--out';
                    }
                    ?>
                    <tr>
                        <td><?= e($loan['full_name']) ?> (<?= e($loan['member_number']) ?>)</td>
                        <td><?= e($loan['title']) ?></td>
                        <td><?= e($loan['date_issued']) ?></td>
                        <td><?= e($loan['due_date']) ?></td>
                        <td><?= e($loan['date_returned'] ?? '-') ?></td>
                        <td><span class="pill <?= e($pill_class) ?>"><?= e(ucfirst($loan_status)) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
