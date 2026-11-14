<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $loan_id = is_string($_POST['loan_id'] ?? null) ? $_POST['loan_id'] : '';
    if (!ctype_digit($loan_id) || (int) $loan_id < 1) {
        $error = 'Choose a valid loan to return.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT loan_id, book_id, date_returned
                 FROM loans
                 WHERE loan_id = ?
                 FOR UPDATE'
            );
            $stmt->execute([(int) $loan_id]);
            $loan = $stmt->fetch();
            if (!$loan) {
                throw new DomainException('That loan could not be found.');
            }
            if ($loan['date_returned'] !== null) {
                throw new DomainException('This loan has already been returned.');
            }

            $stmt = $pdo->prepare(
                'UPDATE books
                 SET available_copies = available_copies + 1
                 WHERE book_id = ?
                   AND available_copies < total_copies'
            );
            $stmt->execute([(int) $loan['book_id']]);
            if ($stmt->rowCount() !== 1) {
                throw new DomainException('The book copy count is already at its limit; the return was not recorded.');
            }

            $stmt = $pdo->prepare(
                'UPDATE loans
                 SET date_returned = CURDATE()
                 WHERE loan_id = ? AND date_returned IS NULL'
            );
            $stmt->execute([(int) $loan_id]);
            if ($stmt->rowCount() !== 1) {
                throw new DomainException('This loan has already been returned.');
            }

            $pdo->commit();
            flash('success', 'Book return recorded.');
            redirect('loans/index.php');
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Loan return failed: ' . $exception->getMessage());
            $error = 'The book could not be returned because of a database error. Please try again.';
        }
    }
}

$stmt = $pdo->query(
    'SELECT l.loan_id, l.date_issued, l.due_date,
            b.title, m.member_number, m.full_name
     FROM loans l
     JOIN books b ON b.book_id = l.book_id
     JOIN members m ON m.member_id = l.member_id
     WHERE l.date_returned IS NULL
     ORDER BY l.due_date, m.full_name, b.title'
);
$loans = $stmt->fetchAll();

$page_title = 'Return a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Return a book</h1>

<?php if ($error !== null) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<?php if ($loans === []) : ?>
    <p>There are no books currently on loan.</p>
<?php else : ?>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Book</th>
                    <th>Issued</th>
                    <th>Due</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loans as $loan) : ?>
                    <tr>
                        <td><?= e($loan['full_name']) ?> (<?= e($loan['member_number']) ?>)</td>
                        <td><?= e($loan['title']) ?></td>
                        <td><?= e($loan['date_issued']) ?></td>
                        <td><?= e($loan['due_date']) ?></td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="loan_id" value="<?= e($loan['loan_id']) ?>">
                                <button class="btn" type="submit">Record return</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<p><a href="<?= e(url('loans/index.php')) ?>">Back to loans</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
