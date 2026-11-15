<?php

// The add and edit member pages share this: reading the form, checking it, and drawing the fields.

const MEMBER_STATUSES = ['active' => 'Active', 'suspended' => 'Suspended'];

/**
 * The posted member fields, trimmed. Member numbers are stored in capitals and emails in lowercase.
 *
 * @return array<string, string>
 */
function member_form_input(): array
{
    return [
        'member_number' => strtoupper(post_text('member_number')),
        'full_name'     => post_text('full_name'),
        'email'         => strtolower(post_text('email')),
        'phone'         => post_text('phone'),
        'status'        => post_text('status') ?: 'active',
    ];
}

/**
 * One message per wrong field, keyed by field name. $member_id is the member being edited, 0 when adding,
 * so their own number and email do not count as taken.
 *
 * @param array<string, string> $form
 * @return array<string, string>
 */
function member_form_errors(PDO $pdo, array $form, int $member_id = 0): array
{
    $phone_digits = preg_replace('/\D/', '', $form['phone']) ?? '';

    $errors = array_filter([
        'member_number' => validate_required($form['member_number'], 'Member number', 20)
            ?? (preg_match('/^[A-Z0-9-]+$/', $form['member_number']) ? null : 'Use only letters, numbers and dashes, like MEM-0011.'),
        'full_name' => validate_required($form['full_name'], 'Full name', 120),
        'email'     => validate_required($form['email'], 'Email', 160) ?? validate_email($form['email']),
        'phone'     => validate_required($form['phone'], 'Phone', 20)
            ?? (preg_match('/^\+?[0-9 ()-]+$/', $form['phone']) && strlen($phone_digits) >= 7
                ? null : 'Enter a phone number of at least 7 digits, like +256 700 555 011.'),
        'status'    => isset(MEMBER_STATUSES[$form['status']]) ? null : 'Choose Active or Suspended.',
    ]);

    // Check for a taken number or email up front, so every problem shows at once. The UNIQUE keys still catch a race.
    $stmt = $pdo->prepare(
        'SELECT member_number = ? AS number_taken, email = ? AS email_taken
         FROM members WHERE (member_number = ? OR email = ?) AND member_id <> ?'
    );
    $stmt->execute([$form['member_number'], $form['email'], $form['member_number'], $form['email'], $member_id]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['number_taken'] && !isset($errors['member_number'])) {
            $errors['member_number'] = 'Another member already has this number.';
        }
        if ($row['email_taken'] && !isset($errors['email'])) {
            $errors['email'] = 'Another member already uses this email.';
        }
    }

    return $errors;
}

/** The field a UNIQUE error from the members table is about, with its message. */
function member_duplicate_error(PDOException $e): array
{
    return str_contains((string) ($e->errorInfo[2] ?? ''), 'uq_members_email')
        ? ['email' => 'Another member already uses this email.']
        : ['member_number' => 'Another member already has this number.'];
}

/** The next free member number after the highest one, like MEM-0011. */
function next_member_number(PDO $pdo): string
{
    $last = (int) $pdo->query("SELECT MAX(CAST(SUBSTRING(member_number, 5) AS UNSIGNED)) FROM members WHERE member_number LIKE 'MEM-%'")
        ->fetchColumn();
    return sprintf('MEM-%04d', $last + 1);
}

/**
 * The fields of the add and edit member forms, filled with $form and marked with $errors.
 *
 * @param array<string, string> $form
 * @param array<string, string> $errors
 */
function member_fields(array $form, array $errors): string
{
    ob_start();
    ?>
    <label for="member_number">Member number</label>
    <input id="member_number" name="member_number" value="<?= e($form['member_number']) ?>" maxlength="20"
        autocapitalize="characters" spellcheck="false" required<?= field_attrs($errors, 'member_number') ?>>
    <?= field_error($errors, 'member_number') ?>

    <label for="full_name">Full name</label>
    <input id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" maxlength="120" autocomplete="off"
        required<?= field_attrs($errors, 'full_name') ?>>
    <?= field_error($errors, 'full_name') ?>

    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" maxlength="160" autocomplete="off"
        required<?= field_attrs($errors, 'email') ?>>
    <?= field_error($errors, 'email') ?>

    <label for="phone">Phone</label>
    <input id="phone" name="phone" type="tel" value="<?= e($form['phone']) ?>" maxlength="20" autocomplete="off"
        placeholder="+256 700 555 011" required<?= field_attrs($errors, 'phone') ?>>
    <?= field_error($errors, 'phone') ?>

    <label for="status">Status</label>
    <select id="status" name="status"<?= field_attrs($errors, 'status') ?>>
        <?= select_options(MEMBER_STATUSES, $form['status']) ?>
    </select>
    <?= field_error($errors, 'status') ?>
    <?php
    return (string) ob_get_clean();
}
