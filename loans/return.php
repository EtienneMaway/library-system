<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$page_title = 'Return a book';
require __DIR__ . '/../includes/header.php';

$open_loans = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $loan_id = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);

    if ($loan_id === false || $loan_id === null || $loan_id < 1) {
        flash('error', 'Please select an open loan to return.');
        redirect('loans/return.php');
    }

    try {
        $pdo->beginTransaction();

        $loan_stmt = $pdo->prepare('SELECT loan_id, book_id, date_returned FROM loans WHERE loan_id = ? FOR UPDATE');
        $loan_stmt->execute([$loan_id]);
        $loan = $loan_stmt->fetch();

        if ($loan === false) {
            throw new RuntimeException('The selected loan was not found.');
        }

        if ($loan['date_returned'] !== null) {
            throw new RuntimeException('This loan was already returned.');
        }

        $today = date('Y-m-d');
        $close_stmt = $pdo->prepare('UPDATE loans SET date_returned = ? WHERE loan_id = ? AND date_returned IS NULL');
        $close_stmt->execute([$today, $loan_id]);

        if ($close_stmt->rowCount() !== 1) {
            throw new RuntimeException('This loan was already returned.');
        }

        $book_stmt = $pdo->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?');
        $book_stmt->execute([$loan['book_id']]);

        $pdo->commit();
        flash('success', 'Book returned successfully.');
        redirect('loans/index.php');
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $e->getMessage());
        redirect('loans/return.php');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Loan return failed: ' . $e->getMessage());
        flash('error', 'The return could not be saved. Please try again.');
        redirect('loans/return.php');
    }
}

try {
    $open_loans = $pdo->query(
        'SELECT l.loan_id, m.full_name, b.title, l.due_date
         FROM loans l
         JOIN members m ON m.member_id = l.member_id
         JOIN books b ON b.book_id = l.book_id
         WHERE l.date_returned IS NULL
         ORDER BY l.due_date ASC, m.full_name ASC, b.title ASC'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('Unable to load open loans: ' . $e->getMessage());
    $open_loans = [];
}
?>
<h1>Return a book</h1>
<?php if (empty($open_loans)) : ?>
    <p>There are no open loans to return right now.</p>
<?php else : ?>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <label for="loan_id">Open loan</label>
        <select id="loan_id" name="loan_id" required>
            <option value="">Select a loan…</option>
            <?php foreach ($open_loans as $loan) : ?>
                <option value="<?= (int) $loan['loan_id'] ?>">
                    <?= e($loan['full_name']) ?> — <?= e($loan['title']) ?> (due <?= e($loan['due_date']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Return book</button>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
