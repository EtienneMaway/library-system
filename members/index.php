<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/member_form.php';

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
if (!isset(MEMBER_STATUSES[$status])) {
    $status = '';
}

// books_out counts each member's loans that are not returned yet. Each filter is skipped when it is empty.
$stmt = $pdo->prepare(
    'SELECT m.member_id, m.member_number, m.full_name, m.email, m.phone, m.status,
            COUNT(l.loan_id) AS books_out
     FROM members m
     LEFT JOIN loans l ON l.member_id = m.member_id AND l.date_returned IS NULL
     WHERE (? = \'\' OR m.full_name LIKE ? OR m.member_number LIKE ?)
       AND (? = \'\' OR m.status = ?)
     GROUP BY m.member_id, m.member_number, m.full_name, m.email, m.phone, m.status
     ORDER BY m.full_name'
);
$like = '%' . $search . '%';
$stmt->execute([$search, $like, $like, $status, $status]);
$members = $stmt->fetchAll();

$filtered = $search !== '' || $status !== '';
$back = $filtered ? '?' . http_build_query(['q' => $search, 'status' => $status]) : '';

$page_title = 'Members';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Members</h1>
    <a class="btn" href="<?= e(url('members/create.php')) ?>">Add a member</a>
</div>

<form method="get" class="search search--filters" role="search">
    <div>
        <label for="q">Name</label>
        <input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Name or member number">
    </div>
    <div>
        <label for="status">Status</label>
        <select id="status" name="status"><?= select_options(MEMBER_STATUSES, $status, 'All members') ?></select>
    </div>
    <div class="search__actions">
        <button class="btn btn--quiet" type="submit">Search</button>
        <?php if ($filtered) : ?>
            <a href="<?= e(url('members/index.php')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<p><?= count($members) ?> <?= count($members) === 1 ? 'member' : 'members' ?><?= $filtered ? ' match' . (count($members) === 1 ? 'es' : '') . ' these filters' : '' ?>.</p>

<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Number</th>
                <th>Contact</th>
                <th>Status</th>
                <th class="num">Books out</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $member) : ?>
            <?php $active = $member['status'] === 'active'; ?>
            <tr>
                <td class="cell--wide"><strong><?= e($member['full_name']) ?></strong></td>
                <td><?= e($member['member_number']) ?></td>
                <td><?= e($member['email']) ?><br><small><?= e($member['phone']) ?></small></td>
                <td><span class="pill pill--<?= $active ? 'active' : 'suspended' ?>"><?= e(MEMBER_STATUSES[$member['status']]) ?></span></td>
                <td class="num"><?= e($member['books_out']) ?></td>
                <td class="row-actions">
                    <a href="<?= e(url('members/edit.php?id=' . (int) $member['member_id'])) ?>">Edit</a>
                    <form method="post" action="<?= e(url('members/status.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $member['member_id'] ?>">
                        <input type="hidden" name="status" value="<?= $active ? 'suspended' : 'active' ?>">
                        <input type="hidden" name="back" value="<?= e($back) ?>">
                        <button class="link-button" type="submit"><?= $active ? 'Suspend' : 'Reactivate' ?></button>
                    </form>
                    <a href="<?= e(url('members/delete.php?id=' . (int) $member['member_id'])) ?>">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$members) : ?>
            <tr><td colspan="6"><?= $filtered ? 'No members match these filters.' : 'No members yet. Add the first one.' ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
