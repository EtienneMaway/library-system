<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Check the three loan rules inside the open transaction, locking the rows it reads.
 * Returns the message to show when a rule is broken, or null when the loan may go ahead.
 */
function issue_rule_error(PDO $pdo, int $member_id, int $book_id): ?string
{
    $stmt = $pdo->prepare('SELECT status FROM members WHERE member_id = ? FOR UPDATE');
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    if (!$member) {
        return 'That member could not be found.';
    }
    if ($member['status'] !== 'active') {
        return 'A suspended member cannot borrow books.';
    }

    $stmt = $pdo->prepare('SELECT available_copies FROM books WHERE book_id = ? FOR UPDATE');
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();
    if (!$book) {
        return 'That book could not be found.';
    }
    if ((int) $book['available_copies'] < 1) {
        return 'There are no available copies of that book.';
    }

    $stmt = $pdo->prepare(
        'SELECT loan_id FROM loans
         WHERE member_id = ? AND book_id = ? AND date_returned IS NULL
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$member_id, $book_id]);
    if ($stmt->fetch()) {
        return 'This member already has this book on loan.';
    }

    return null;
}

$error = null;
$member_id = '';
$book_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $member_id = is_string($_POST['member_id'] ?? null) ? $_POST['member_id'] : '';
    $book_id = is_string($_POST['book_id'] ?? null) ? $_POST['book_id'] : '';
    if (!ctype_digit($member_id) || (int) $member_id < 1) {
        $error = 'Choose a valid member.';
    } elseif (!ctype_digit($book_id) || (int) $book_id < 1) {
        $error = 'Choose a valid book.';
    } else {
        try {
            // One transaction: the loan is inserted and the copy count drops together, or neither happens.
            $pdo->beginTransaction();
            $error = issue_rule_error($pdo, (int) $member_id, (int) $book_id);

            if ($error === null) {
                $stmt = $pdo->prepare(
                    'UPDATE books
                     SET available_copies = available_copies - 1
                     WHERE book_id = ? AND available_copies > 0'
                );
                $stmt->execute([(int) $book_id]);
                if ($stmt->rowCount() !== 1) {
                    $error = 'There are no available copies of that book.';
                }
            }

            if ($error === null) {
                $stmt = $pdo->prepare(
                    'INSERT INTO loans (member_id, book_id, date_issued, due_date)
                     VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY))'
                );
                $stmt->execute([(int) $member_id, (int) $book_id]);
                $pdo->commit();

                flash('success', 'Book issued. It is due in 14 days.');
                redirect('loans/index.php');
            }

            $pdo->rollBack();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Loan issue failed: ' . $exception->getMessage());
            $error = 'The loan could not be issued because of a database error. Please try again.';
        }
    }
}

$members = $pdo->query(
    "SELECT member_id, member_number, full_name, status
     FROM members
     ORDER BY status, full_name"
)->fetchAll();
$books = $pdo->query(
    'SELECT book_id, title, available_copies
     FROM books
     ORDER BY title'
)->fetchAll();
$due_date = $pdo->query('SELECT DATE_ADD(CURDATE(), INTERVAL 14 DAY)')->fetchColumn();

$page_title = 'Issue a book';
require __DIR__ . '/../includes/header.php';
?>
<h1>Issue a book</h1>
<p>Loans issued today are due on <strong><?= e($due_date) ?></strong> (14 days after issue).</p>

<?php if ($error !== null) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" class="form">
    <?= csrf_field() ?>
    <label for="member_id">Member</label>
    <select id="member_id" name="member_id" required>
        <option value="">Choose a member</option>
        <?php foreach ($members as $member) : ?>
            <option value="<?= e($member['member_id']) ?>"
                <?= (string) $member['member_id'] === $member_id ? 'selected' : '' ?>
                <?= $member['status'] === 'suspended' ? 'disabled' : '' ?>>
                <?= e($member['full_name']) ?> (<?= e($member['member_number']) ?><?= $member['status'] === 'suspended' ? ', suspended' : '' ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <label for="book_id">Book</label>
    <select id="book_id" name="book_id" required>
        <option value="">Choose a book</option>
        <?php foreach ($books as $book) : ?>
            <option value="<?= e($book['book_id']) ?>"
                <?= (string) $book['book_id'] === $book_id ? 'selected' : '' ?>
                <?= (int) $book['available_copies'] < 1 ? 'disabled' : '' ?>>
                <?= e($book['title']) ?> (<?= e($book['available_copies']) ?> available)
            </option>
        <?php endforeach; ?>
    </select>

    <button class="btn" type="submit">Issue book</button>
</form>
<p><a href="<?= e(url('loans/index.php')) ?>">Back to loans</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
