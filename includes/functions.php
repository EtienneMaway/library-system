<?php

// Shared helpers. Every page gets these through auth_check.php or login.php.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/** Escape a value for HTML output. Use it on everything printed from the database or a form. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

/** Return an error message when a new password is too short, too long or not confirmed, otherwise null. */
function validate_new_password(string $password, string $confirm): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    // password_hash() only uses the first 72 bytes.
    if (strlen($password) > 72) {
        return 'Password must be 72 characters or fewer.';
    }
    if ($password !== $confirm) {
        return 'The two passwords do not match.';
    }
    return null;
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

/**
 * Dropdown data for the book forms: [id => label], sorted by label.
 * Usage in books/create.php and books/edit.php:
 *   <select name="author_id" required><?= select_options(author_choices($pdo), $author_id, 'Choose an author') ?></select>
 */
function author_choices(PDO $pdo): array
{
    return $pdo->query('SELECT author_id, name FROM authors ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** Same as author_choices(), for categories. */
function category_choices(PDO $pdo): array
{
    return $pdo->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** Print <option> tags for a select, marking $selected. $placeholder adds an empty first option. */
function select_options(array $choices, int|string|null $selected = null, ?string $placeholder = null): string
{
    $html = $placeholder !== null ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($choices as $value => $label) {
        $is_selected = $selected !== null && (string) $selected === (string) $value;
        $html .= '<option value="' . e($value) . '"' . ($is_selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

/** The light and dark switch. assets/js/app.js fills in the icon and label. */
function theme_toggle(): string
{
    return '<button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch colour theme">Theme</button>';
}

/** Placeholder body for a page nobody has built yet. Delete the call when you build the page. */
function page_todo(string $owner, array $tasks): void
{
    echo '<section class="todo"><p class="todo__stamp">Not built yet</p>';
    echo '<p class="todo__owner">Workstream: ' . e($owner) . '</p><ul>';
    foreach ($tasks as $task) {
        echo '<li>' . e($task) . '</li>';
    }
    echo '</ul></section>';
}
