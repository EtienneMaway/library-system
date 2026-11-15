<?php

// The add and edit book pages share this: reading the form, checking it, and the ISBN rules.

/**
 * Removes spaces and dashes, so 978-0-385-47454-2 and 9780385474542 are stored the same way.
 */
function normalize_isbn(string $isbn): string
{
    return strtoupper(preg_replace('/[\s-]+/', '', $isbn) ?? '');
}

/**
 * True for a 10-digit ISBN (last character may be X) or a 13-digit one, with a correct check digit.
 */
function isbn_is_valid(string $isbn): bool
{
    if (preg_match('/^\d{9}[\dX]$/', $isbn)) {
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ($isbn[$i] === 'X' ? 10 : (int) $isbn[$i]) * (10 - $i);
        }
        return $sum % 11 === 0;
    }
    if (preg_match('/^\d{13}$/', $isbn)) {
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return $sum % 10 === 0;
    }
    return false;
}

/** Why an ISBN is not valid, or null when it is. */
function isbn_error(string $isbn): ?string
{
    if (!preg_match('/^(\d{9}[\dX]|\d{13})$/', $isbn)) {
        return 'An ISBN has 10 or 13 digits. Dashes and spaces are fine, and a 10-digit one may end in X.';
    }
    return isbn_is_valid($isbn) ? null : 'That ISBN has a typo: its last digit does not match the others.';
}

/**
 * The posted book fields, trimmed. Numbers stay strings so the form can show back what was typed.
 *
 * @return array<string, string>
 */
function book_form_input(): array
{
    return [
        'isbn'           => normalize_isbn(post_text('isbn')),
        'title'          => post_text('title'),
        'author_id'      => post_text('author_id'),
        'category_id'    => post_text('category_id'),
        'publisher'      => post_text('publisher'),
        'year_published' => post_text('year_published'),
        'total_copies'   => post_text('total_copies'),
    ];
}

/**
 * One message per field that is wrong, keyed by field name. $copies_out is how many copies are on loan now.
 *
 * @param array<string, string> $form
 * @param array<int|string, string> $authors
 * @param array<int|string, string> $categories
 * @return array<string, string>
 */
function book_form_errors(array $form, array $authors, array $categories, int $copies_out = 0): array
{
    $max_year = (int) date('Y') + 1;
    $year = $form['year_published'];
    $copies = $form['total_copies'];

    $errors = [
        'isbn'        => validate_required($form['isbn'], 'ISBN', 17) ?? isbn_error($form['isbn']),
        'title'       => validate_required($form['title'], 'Title', 200),
        'author_id'   => isset($authors[$form['author_id']]) ? null : 'Choose an author from the list.',
        'category_id' => isset($categories[$form['category_id']]) ? null : 'Choose a category from the list.',
        'publisher'   => validate_required($form['publisher'], 'Publisher', 120),
        'year_published' => ctype_digit($year) && (int) $year >= 1400 && (int) $year <= $max_year
            ? null : "Year published must be a year from 1400 to $max_year.",
        'total_copies' => !ctype_digit($copies) || (int) $copies < 1 || (int) $copies > 999
            ? 'Total copies must be a whole number from 1 to 999.'
            : ((int) $copies < $copies_out
                ? "$copies_out copies are out on loan, so the total cannot be lower than that."
                : null),
    ];

    return array_filter($errors);
}

/**
 * The fields of the add and edit book forms, filled with $form and marked with $errors.
 *
 * @param array<string, string> $form
 * @param array<string, string> $errors
 * @param array<int|string, string> $authors
 * @param array<int|string, string> $categories
 */
function book_fields(array $form, array $errors, array $authors, array $categories): string
{
    ob_start();
    ?>
    <label for="isbn">ISBN</label>
    <input id="isbn" name="isbn" value="<?= e($form['isbn']) ?>" maxlength="17" inputmode="numeric" autocomplete="off"
        placeholder="978-0-385-47454-2" required<?= field_attrs($errors, 'isbn') ?>>
    <?= field_error($errors, 'isbn') ?>

    <label for="title">Title</label>
    <input id="title" name="title" value="<?= e($form['title']) ?>" maxlength="200" required<?= field_attrs($errors, 'title') ?>>
    <?= field_error($errors, 'title') ?>

    <label for="author_id">Author</label>
    <select id="author_id" name="author_id" required<?= field_attrs($errors, 'author_id') ?>>
        <?= select_options($authors, $form['author_id'], 'Choose an author') ?>
    </select>
    <?= field_error($errors, 'author_id') ?>

    <label for="category_id">Category</label>
    <select id="category_id" name="category_id" required<?= field_attrs($errors, 'category_id') ?>>
        <?= select_options($categories, $form['category_id'], 'Choose a category') ?>
    </select>
    <?= field_error($errors, 'category_id') ?>

    <label for="publisher">Publisher</label>
    <input id="publisher" name="publisher" value="<?= e($form['publisher']) ?>" maxlength="120" required<?= field_attrs($errors, 'publisher') ?>>
    <?= field_error($errors, 'publisher') ?>

    <label for="year_published">Year published</label>
    <input id="year_published" name="year_published" type="number" min="1400" max="<?= (int) date('Y') + 1 ?>"
        value="<?= e($form['year_published']) ?>" required<?= field_attrs($errors, 'year_published') ?>>
    <?= field_error($errors, 'year_published') ?>

    <label for="total_copies">Total copies</label>
    <input id="total_copies" name="total_copies" type="number" min="1" max="999"
        value="<?= e($form['total_copies']) ?>" required<?= field_attrs($errors, 'total_copies') ?>>
    <?= field_error($errors, 'total_copies') ?>
    <?php
    return (string) ob_get_clean();
}
