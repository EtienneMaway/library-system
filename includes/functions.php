<?php

// Shared helpers. Every page gets these through auth_check.php or login.php.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Anything a page didn't catch, like a database failure, is logged and the user gets a plain error page, never the SQL.
set_exception_handler('show_error_page');

function show_error_page(Throwable $error): void
{
    error_log('Unhandled ' . get_class($error) . ' in ' . $error->getFile() . ':' . $error->getLine() . ': ' . $error->getMessage());

    $home = empty($_SESSION['user_id']) ? url('auth/login.php') : url('dashboard/index.php');
    $box = '<section class="flash flash--error" role="alert"><p><strong>Something went wrong on our side.</strong></p>'
        . '<p>Your last action may not have been saved. Check, then try again. If it keeps happening, tell the administrator the time it happened.</p>'
        . '<p><a href="' . e($home) . '">Back to the library</a></p></section>';

    if (headers_sent()) {
        echo $box; // The page had started: finish it with the message.
        return;
    }
    http_response_code(500);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Something went wrong</title>'
        . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head>'
        . '<body><main class="page">' . $box . '</main></body></html>';
}

/** Escape a value for HTML output. Use it on everything printed from the database or a form. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** The first letters of the first two words of a name: "Mugalu Kevin" gives "MK". */
function initials(string $name): string
{
    $letters = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $letters;
}

/**
 * Build a link from the project root, wherever the folder is installed.
 * url('books/create.php') gives /library-system/books/create.php.
 */
function url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        $root   = str_replace('\\', '/', dirname(__DIR__));
        $script = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']) ?: '');
        $depth  = str_starts_with($script, $root . '/')
            ? substr_count(substr($script, strlen($root) + 1), '/') + 1
            : 1;
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], $depth)), '/');
    }

    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Queue a toast for the next page, after a redirect.
 * $type is 'success', 'error' or 'info'.
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Show a toast on this page, without a redirect. Use it for form errors. */
function flash_now(string $type, string $message): void
{
    pending_flashes(['type' => $type, 'message' => $message]);
}

/** The toasts added with flash_now() during this request. */
function pending_flashes(?array $add = null): array
{
    static $list = [];
    if ($add !== null) {
        $list[] = $add;
    }
    return $list;
}

/** Return the queued toasts and clear them. header.php prints these. */
function take_flashes(): array
{
    $messages = array_merge($_SESSION['flash'] ?? [], pending_flashes());
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Print a form field's error under it. Pair it with field_attrs() on the input.
 * $errors is keyed by field name.
 */
function field_error(array $errors, string $name): string
{
    return isset($errors[$name])
        ? '<p class="field__error" id="' . e($name) . '-error">' . e($errors[$name]) . '</p>'
        : '';
}

/** aria-invalid and aria-describedby for an input that has an error. */
function field_attrs(array $errors, string $name): string
{
    return isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="' . e($name) . '-error"' : '';
}

/** The toast over a form that has errors: "Fix the highlighted field to save the book." */
function flash_form_errors(array $errors, string $goal): void
{
    $count = count($errors);
    if ($count > 0) {
        flash_now('error', ($count === 1 ? 'Fix the highlighted field' : "Fix the $count highlighted fields") . " to $goal.");
    }
}

/**
 * A form's starting values: the keys of $blank, filled from a database row when editing. Every value is text,
 * the same as a posted form, so one set of fields shows both.
 *
 * @param array<string, string> $blank
 * @return array<string, string>
 */
function form_values(array $blank, array $row = []): array
{
    $form = [];
    foreach ($blank as $key => $default) {
        $form[$key] = (string) ($row[$key] ?? $default);
    }
    return $form;
}

/** "1 book" or "3 books". */
function plural(int $count, string $one, string $many): string
{
    return $count . ' ' . ($count === 1 ? $one : $many);
}

function csrf_token(): string
{
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

/** Print this inside every form that uses POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Call this first when handling a POST. It stops the request if the token is wrong. */
function require_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        die('This form has expired. Go back, reload the page and try again.');
    }
}

/** Read a text field from POST with the surrounding spaces removed. */
function post_text(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Read a text value from the address (?q=...) with the surrounding spaces removed. */
function get_text(string $name): string
{
    $value = $_GET[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** A key of $choices taken from the address, or '' when it is missing or not one of them. Use it for filters. */
function get_choice(string $name, array $choices): string
{
    $value = get_text($name);
    return isset($choices[$value]) ? $value : '';
}

/** A positive whole number from a POST field, such as a chosen member, or 0 when it is missing or not valid. */
function post_id(string $name): int
{
    $id = filter_input(INPUT_POST, $name, FILTER_VALIDATE_INT);
    return is_int($id) && $id > 0 ? $id : 0;
}

/**
 * The id of the record an edit or delete page works on: ?id= when the page opens, the hidden id field when its
 * form is sent. A missing or invalid id sends the user back to the list with $message.
 */
function require_id(string $back, string $message): int
{
    $id = filter_input($_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!is_int($id) || $id < 1) {
        flash('error', $message);
        redirect($back);
    }
    return $id;
}

/** Return an error message when a required value is empty or too long, otherwise null. */
function validate_required(string $value, string $label, int $max = 255): ?string
{
    if ($value === '') {
        return "$label is required.";
    }
    if (mb_strlen($value) > $max) {
        return "$label must be $max characters or fewer.";
    }
    return null;
}

/** Return an error message when an email address is not valid, otherwise null. */
function validate_email(string $value, string $label = 'Email'): ?string
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) === false
        ? "$label is not a valid email address."
        : null;
}

/** Return an error message when a username is not 3 to 40 lowercase letters, digits, dots, dashes or underscores. */
function validate_username(string $value): ?string
{
    if ($value === '') {
        return 'Username is required.';
    }
    return preg_match('/^[a-z0-9._-]{3,40}$/', $value) === 1
        ? null
        : 'Username must be 3 to 40 letters, numbers, dots, dashes or underscores, with no spaces.';
}

/** Return an error message when a new password is too short or too long, otherwise null. */
function validate_new_password(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    // password_hash() only uses the first 72 bytes.
    if (strlen($password) > 72) {
        return 'Password must be 72 characters or fewer.';
    }
    return null;
}

/**
 * Errors for a new password and its "type it again" box, keyed by their field names. Used by sign-up,
 * reset password and change password.
 *
 * @return array<string, string>
 */
function new_password_errors(string $password, string $confirm, string $field, string $confirm_field): array
{
    return array_filter([
        $field         => validate_new_password($password),
        $confirm_field => $password !== $confirm ? 'The two passwords do not match.' : null,
    ]);
}

/** Read a password field from POST exactly as typed (no trimming). */
function post_password(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? $value : '';
}

/** Start a fresh session for a user row that has user_id, full_name and role. */
function log_in(array $account): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int) $account['user_id'];
    $_SESSION['full_name'] = $account['full_name'];
    $_SESSION['role']      = $account['role'];
}

/** The sign-in pages call this first: someone already logged in goes to $to instead. */
function redirect_if_logged_in(string $to = 'dashboard/index.php'): void
{
    if (!empty($_SESSION['user_id'])) {
        redirect($to);
    }
}

/** One row from a prepared query, or null when nothing matches. $sql holds only ? placeholders. */
function fetch_row(PDO $pdo, string $sql, array $params): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Undo the open transaction, if there is one. Call it first in a catch block. */
function rollback_if_open(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

/** True when a PDOException came from a UNIQUE constraint. */
function is_duplicate_error(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? null) === 1062;
}

/** True when a PDOException came from a foreign key blocking a delete or an update. */
function is_in_use_error(PDOException $e): bool
{
    return in_array($e->errorInfo[1] ?? null, [1451, 1452], true);
}

/** Authors for a dropdown: [id => name], sorted by name. Use with select_options(). */
function author_choices(PDO $pdo): array
{
    return $pdo->query('SELECT author_id, name FROM authors ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** Categories for a dropdown, like author_choices(). */
function category_choices(PDO $pdo): array
{
    return $pdo->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Print <option> tags for a select, marking $selected. $placeholder adds an empty first option.
 * Values listed in $disabled show but cannot be picked, like a suspended member on the issue page.
 */
function select_options(array $choices, int|string|null $selected = null, ?string $placeholder = null, array $disabled = []): string
{
    $html = $placeholder !== null ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($choices as $value => $label) {
        $is_selected = $selected !== null && (string) $selected === (string) $value;
        $html .= '<option value="' . e($value) . '"' . ($is_selected ? ' selected' : '')
            . (in_array($value, $disabled, true) ? ' disabled' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

/** The light and dark switch. assets/js/app.js fills in the icon and label. */
function theme_toggle(): string
{
    return '<button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch colour theme">Theme</button>';
}

/** True when the logged-in user is an admin. Librarians are everyone else. */
function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

/** Admin-only pages call this after auth_check.php. Librarians can do everything except delete. */
function require_admin(string $back): void
{
    if (!is_admin()) {
        flash('error', 'Only an admin can delete records. Ask an admin to do it.');
        redirect($back);
    }
}

/** A table row that spans every column, for "nothing found" messages. */
function empty_row(int $columns, string $message): string
{
    return '<tr><td colspan="' . $columns . '">' . e($message) . '</td></tr>';
}

/** The Edit link for one row of a list, like edit_link('books', 7). */
function edit_link(string $section, int $id): string
{
    return '<a href="' . e(url("$section/edit.php?id=$id")) . '">Edit</a>';
}

/** The Delete link for one row of a list. Only admins see it; delete pages also refuse everyone else. */
function delete_link(string $section, int $id): string
{
    return is_admin() ? '<a href="' . e(url("$section/delete.php?id=$id")) . '">Delete</a>' : '';
}
