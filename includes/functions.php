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

/** Queue a message for the next page. $type is 'success' or 'error'. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Return the queued messages and clear them. header.php prints these. */
function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
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
