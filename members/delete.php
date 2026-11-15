<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$member_id = filter_input($_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$member_id || $member_id < 1) {
    flash('error', 'Choose a member to delete.');
    redirect('members/index.php');
}

// books_out: loans not returned yet. past_loans: returned ones, which keep the member as part of the loan history.
$load = 'SELECT m.full_name, m.member_number,
                SUM(l.loan_id IS NOT NULL AND l.date_returned IS NULL) AS books_out,
                SUM(l.date_returned IS NOT NULL) AS past_loans
         FROM members m
         LEFT JOIN loans l ON l.member_id = m.member_id
         WHERE m.member_id = ?
         GROUP BY m.member_id, m.full_name, m.member_number';

/** Why this member cannot be deleted, or null when they can. */
function member_delete_block_reason(array $member): ?string
{
    $out = (int) $member['books_out'];
    if ($out > 0) {
        return $member['full_name'] . ' cannot be deleted: they still hold ' . $out . ' ' . ($out === 1 ? 'book' : 'books')
            . '. Return ' . ($out === 1 ? 'it' : 'them') . ' first.';
    }
    if ((int) $member['past_loans'] > 0) {
        return $member['full_name'] . ' cannot be deleted: they appear in past loans, and those records must keep them. '
            . 'Suspend the member instead.';
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        // Lock the member so nobody issues them a book between the check and the delete.
        $pdo->beginTransaction();
        $pdo->prepare('SELECT member_id FROM members WHERE member_id = ? FOR UPDATE')->execute([$member_id]);
        $stmt = $pdo->prepare($load);
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();

        $reason = $member ? member_delete_block_reason($member) : 'That member is not on the list.';
        if ($reason !== null) {
            $pdo->rollBack();
            flash('error', $reason);
            redirect('members/index.php');
        }

        $pdo->prepare('DELETE FROM members WHERE member_id = ?')->execute([$member_id]);
        $pdo->commit();
        flash('success', $member['full_name'] . ' deleted.');
        redirect('members/index.php');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (!is_in_use_error($e)) {
            error_log('Member delete failed: ' . $e->getMessage());
            throw $e;
        }
        // The loans table refused it (ON DELETE RESTRICT): a loan was recorded since we checked.
        flash('error', 'That member cannot be deleted because loans refer to them.');
        redirect('members/index.php');
    }
}

$stmt = $pdo->prepare($load);
$stmt->execute([$member_id]);
$member = $stmt->fetch();
if (!$member) {
    flash('error', 'That member is not on the list.');
    redirect('members/index.php');
}
$reason = member_delete_block_reason($member);

$page_title = 'Delete a member';
require __DIR__ . '/../includes/header.php';
?>
<h1>Delete a member</h1>

<?php if ($reason !== null) : ?>
    <p class="flash flash--error" role="alert"><?= e($reason) ?></p>
    <p><a href="<?= e(url('members/index.php')) ?>">Back to members</a></p>
<?php else : ?>
    <p>Delete <strong><?= e($member['full_name']) ?></strong> (<?= e($member['member_number']) ?>)? This cannot be undone.</p>
    <form method="post" class="form" data-busy-form>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $member_id ?>">
        <div class="form__actions">
            <button class="btn btn--danger" type="submit" data-busy-label="Deleting…">Delete member</button>
            <a href="<?= e(url('members/index.php')) ?>">Cancel</a>
        </div>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
