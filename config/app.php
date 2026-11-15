<?php

// Settings that differ between a laptop (local) and the live site (production).
// They come from environment variables, or from the .env file in the project root (git ignores it).
// Copy .env.example to .env to start. .htaccess stops browsers from downloading .env.

// Read KEY=value lines from a .env file. Blank lines and # comments are skipped.
$read_env_file = static function (string $path): array {
    if (!is_file($path)) {
        return [];
    }
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        // Allow "quoted values" so spaces and < > are kept.
        if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && str_ends_with($value, $value[0])) {
            $value = substr($value, 1, -1);
        }
        $values[$key] = $value;
    }
    return $values;
};

$file = $read_env_file(__DIR__ . '/../.env');

$setting = static function (string $name, string $default) use ($file): string {
    $value = getenv($name);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    return ($file[$name] ?? '') !== '' ? $file[$name] : $default;
};

$app_env = $setting('APP_ENV', 'local');
if (!in_array($app_env, ['local', 'production'], true)) {
    error_log('APP_ENV must be local or production, not ' . $app_env);
    http_response_code(500);
    die('The site is misconfigured. Please contact the administrator.');
}

return [
    'env'            => $app_env,
    'resend_api_key' => $setting('RESEND_API_KEY', ''),
    'mail_from'      => $setting('MAIL_FROM', 'Library <onboarding@resend.dev>'),
];
