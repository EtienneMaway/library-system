<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all', 'active', 'suspended'], true)) {
    $statusFilter = 'all';
}

$stmt = $pdo->prepare(
    'SELECT m.member_id, m.member_number, m.full_name, m.email, m.phone, m.status,
            COUNT(l.loan_id) AS books_out
     FROM members m
     LEFT JOIN loans l ON l.member_id = m.member_id AND l.date_returned IS NULL
     WHERE (m.full_name LIKE ? OR m.member_number LIKE ?)
       AND (? = "all" OR m.status = ?)
     GROUP BY m.member_id, m.member_number, m.full_name, m.email, m.phone, m.status
     ORDER BY m.full_name'
);

$term = '%' . $search . '%';
$stmt->execute([$term, $term, $statusFilter, $statusFilter]);
$members = $stmt->fetchAll();

$page_title = 'Members';
require __DIR__ . '/../includes/header.php';
?>
<h1>Members</h1>

<form method="get" class="search" role="search">
    <label for="q">Search members</label>
    <input id="q" name="q" value="<?= e($search) ?>" placeholder="Name or member number">

    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
    </select>

    <button class="btn btn--quiet" type="submit">Filter</button>
    <a class="btn btn--quiet" href="<?= e(url('members/index.php')) ?>">Clear</a>
</form>

<p><a class="btn" href="<?= e(url('members/create.php')) ?>">Add member</a></p>

<div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Member number</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th class="num">Books out</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $member) : ?>
            <tr>
                <td><?= e($member['member_number']) ?></td>
                <td><?= e($member['full_name']) ?></td>
                <td><?= e($member['email']) ?></td>
                <td><?= e($member['phone']) ?></td>
                <td>
                    <span class="tag <?= $member['status'] === 'active' ? 'tag--success' : 'tag--warning' ?>">
                        <?= e(ucfirst($member['status'])) ?>
                    </span>
                </td>
                <td class="num"><?= e($member['books_out']) ?></td>
                <td>
                    <a href="<?= e(url('members/edit.php?id=' . (string) $member['member_id'])) ?>">Edit</a>
                    |
                    <a href="<?= e(url('members/delete.php?id=' . (string) $member['member_id'])) ?>">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$members) : ?>
            <tr><td colspan="7">No members match that search and status filter.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
