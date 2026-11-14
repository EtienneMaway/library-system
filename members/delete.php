<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$member = null;
$canDelete = false;
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod === 'POST') {
    require_csrf();
}

$loanCount = null;
$openLoanCount = null;
$memberId = $requestMethod === 'POST'
    ? post_text('member_id')
    : ($_GET['id'] ?? '');
$validMemberId = is_string($memberId)
    ? filter_var($memberId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]])
    : false;

if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    $errors[] = 'This request method is not allowed.';
} elseif ($validMemberId === false) {
    http_response_code(400);
    $errors[] = 'A valid member ID is required.';
} else {
    $memberId = (int) $validMemberId;

    if ($requestMethod === 'POST') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT member_id, member_number, full_name FROM members WHERE member_id = ? FOR UPDATE');
            $stmt->execute([$memberId]);
            $member = $stmt->fetch();

            if (!$member) {
                $pdo->rollBack();
                http_response_code(404);
                $errors[] = 'That member could not be found.';
            } else {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) AS loan_count,
                            COALESCE(SUM(date_returned IS NULL), 0) AS open_loan_count
                     FROM loans
                     WHERE member_id = ?'
                );
                $stmt->execute([$memberId]);
                $loanCounts = $stmt->fetch();
                $loanCount = (int) $loanCounts['loan_count'];
                $openLoanCount = (int) $loanCounts['open_loan_count'];

                if ($openLoanCount > 0) {
                    $pdo->rollBack();
                    $errors[] = "This member has {$openLoanCount} unreturned " .
                        ($openLoanCount === 1 ? 'book' : 'books') . ' and cannot be deleted.';
                } elseif ($loanCount > 0) {
                    $pdo->rollBack();
                    $errors[] = 'This member has loan history that must be preserved, so the member cannot be deleted.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM members WHERE member_id = ?');
                    $stmt->execute([$memberId]);
                    $pdo->commit();

                    flash('success', 'Member deleted successfully.');
                    redirect('members/index.php');
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (is_in_use_error($e)) {
                $errors[] = 'This member has related loan records and cannot be deleted.';
            } else {
                error_log($e->getMessage());
                $errors[] = 'The member could not be deleted. Please try again.';
            }
        }
    } else {
        $stmt = $pdo->prepare('SELECT member_id, member_number, full_name FROM members WHERE member_id = ?');
        $stmt->execute([$memberId]);
        $member = $stmt->fetch();

        if (!$member) {
            http_response_code(404);
            $errors[] = 'That member could not be found.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS loan_count,
                        COALESCE(SUM(date_returned IS NULL), 0) AS open_loan_count
                 FROM loans
                 WHERE member_id = ?'
            );
            $stmt->execute([$memberId]);
            $loanCounts = $stmt->fetch();
            $loanCount = (int) $loanCounts['loan_count'];
            $openLoanCount = (int) $loanCounts['open_loan_count'];
            $canDelete = $loanCount === 0;
        }
    }
}

$page_title = 'Delete a member';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete a member</h1>

<?php foreach ($errors as $error) : ?>
    <p class="flash flash--error" role="alert"><?= e($error) ?></p>
<?php endforeach; ?>

<?php if ($member) : ?>
    <p>Member: <strong><?= e($member['full_name']) ?></strong> (<?= e($member['member_number']) ?>)</p>

    <?php if ($openLoanCount > 0) : ?>
        <p>This member has <?= e($openLoanCount) ?> unreturned <?= $openLoanCount === 1 ? 'book' : 'books' ?>. Return them before deleting the member.</p>
    <?php elseif ($loanCount > 0) : ?>
        <p>This member has loan history. Loan records are retained, so this member cannot be deleted.</p>
    <?php elseif ($canDelete) : ?>
        <p>Are you sure you want to permanently delete this member?</p>
        <form method="post" class="form" onsubmit="return confirm('Permanently delete this member?');">
            <?= csrf_field() ?>
            <input type="hidden" name="member_id" value="<?= e($member['member_id']) ?>">
            <button class="btn" type="submit">Delete member</button>
        </form>
    <?php else : ?>
        <p>Loan records could not be verified. The member has not been deleted; please try again.</p>
    <?php endif; ?>
<?php endif; ?>

<p><a class="btn btn--quiet" href="<?= e(url('members/index.php')) ?>">Back to members</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
