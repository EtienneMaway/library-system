<?php

declare(strict_types=1);

// Check what is installed and running, and print a fix for anything missing.
// This is the only copy of the doctor: "composer dev:doctor" runs it directly,
// and "bash .github/scripts/dev.sh doctor" hands over to it.

require __DIR__ . '/lib/executables.php';

$root = dirname(__DIR__, 2);
$isWindows = PHP_OS_FAMILY === 'Windows';
$problems = 0;

function ok(string $message): void
{
    echo " OK  {$message}\n";
}

function miss(string $message, string $fix): void
{
    global $problems;
    ++$problems;
    echo "MISS {$message}\n     fix: {$fix}\n";
}

function note(string $message): void
{
    echo "NOTE {$message}\n";
}

function runCommand(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        return ['', '', 1];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [$stdout, $stderr, proc_close($process)];
}

function findAppUrl(): ?string
{
    $context = stream_context_create(['http' => ['timeout' => 3]]);
    foreach (['http://localhost/library-system', 'http://localhost:8080/library-system'] as $url) {
        $headers = @get_headers($url . '/auth/login.php', false, $context);
        if ($headers !== false) {
            foreach (array_reverse($headers) as $header) {
                if (preg_match('/^HTTP\/\S+\s+200\b/', $header)) {
                    return $url;
                }
            }
        }
    }

    return null;
}

function mysqlCommand(bool $isWindows): ?array
{
    // Same override as dev.sh, for example MYSQL="mysql -uroot -psecret".
    $override = trim((string) getenv('MYSQL'));
    if ($override !== '') {
        return preg_split('/\s+/', $override) ?: null;
    }

    if ($isWindows) {
        $xamppMysql = rtrim((string) (getenv('SystemDrive') ?: 'C:'), '\\/') . '\\xampp\\mysql\\bin\\mysql.exe';
        if (is_file($xamppMysql)) {
            return [$xamppMysql, '-uroot'];
        }
    } else {
        $homebrewMysql = '/opt/homebrew/opt/mariadb@10.11/bin/mariadb';
        if (is_file($homebrewMysql)) {
            return [$homebrewMysql, '-uroot'];
        }
    }

    $mysql = findExecutable('mysql') ?? findExecutable('mariadb');

    return $mysql === null ? null : [$mysql, '-uroot'];
}

echo 'Checking your setup (' . ($isWindows ? 'windows' : (PHP_OS_FAMILY === 'Darwin' ? 'mac' : 'linux')) . ")\n";

if (version_compare(PHP_VERSION, '8.0', '>=')) {
    ok('PHP ' . PHP_VERSION);
} else {
    miss('PHP ' . PHP_VERSION . ' is too old', 'install PHP 8.0 or newer (a current XAMPP)');
}

if (extension_loaded('pdo_mysql')) {
    ok('PHP can talk to MySQL (pdo_mysql)');
} else {
    miss('pdo_mysql extension is off', 'in php.ini enable extension=pdo_mysql, then restart Apache');
}

$mysql = mysqlCommand($isWindows);
if ($mysql === null) {
    if ($isWindows) {
        miss('MySQL client not found', 'XAMPP includes it at C:\\xampp\\mysql\\bin; reinstall XAMPP with MySQL ticked');
    } else {
        miss('MySQL client not found', 'brew install mariadb@10.11');
    }
} else {
    [$output, , $status] = runCommand(array_merge($mysql, ['-e', 'SELECT 1']));
    if ($status === 0) {
        ok('MySQL server is running');
        [$tablesOutput, , $tablesStatus] = runCommand(array_merge($mysql, [
            '-N',
            '-e',
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='library_db'",
        ]));
        $tables = $tablesStatus === 0 ? (int) trim($tablesOutput) : 0;

        if ($tables >= 6) {
            [$booksOutput, , $booksStatus] = runCommand(array_merge($mysql, ['-N', '-e', 'SELECT COUNT(*) FROM library_db.books']));
            $books = $booksStatus === 0 ? trim($booksOutput) : '0';
            ok("Database library_db has {$tables} tables and {$books} books");
        } else {
            miss('Database library_db is missing or incomplete', 'composer dev:seed');
        }
    } else {
        miss('MySQL server is not running', 'composer dev:start');
    }
}

$url = findAppUrl();
if ($url !== null) {
    ok("App answers at {$url}");
} else {
    if ($isWindows) {
        $expectedRoot = rtrim((string) (getenv('SystemDrive') ?: 'C:'), '\\/') . '\\xampp\\htdocs\\library-system';
        $normalize = static fn (string $path): string => strtolower(rtrim(str_replace('/', '\\', $path), '\\'));
        if ($normalize($root) !== $normalize($expectedRoot)) {
            miss("Project is not in the web folder (it is in {$root})", 'clone it into C:\\xampp\\htdocs\\library-system');
        }
    }
    miss('Apache is not serving the app', $isWindows
        ? 'composer dev:start'
        : 'composer dev:start (on a Mac, see the README for the one-time Apache setup)');
}

$node = findExecutable('node');
if ($node !== null) {
    [$nodeVersion, , $status] = runCommand([$node, '-v']);
    if ($status === 0) {
        ok('Node.js ' . trim($nodeVersion) . ' (for the JavaScript and CSS checks)');
    }
} else {
    note('Node.js not found. Optional: only needed to run the JS and CSS checks locally.');
}

$git = findExecutable('git');
if ($git !== null) {
    [$gitVersion, , $status] = runCommand([$git, '--version']);
    if ($status === 0 && preg_match('/\bgit version (\S+)/', $gitVersion, $matches)) {
        ok('Git ' . $matches[1]);
    }
} else {
    miss('Git not found', 'install it from git-scm.com');
}

echo "\n";
if ($problems === 0) {
    echo "Everything is ready.\n";
    exit(0);
}

echo "{$problems} problem(s) to fix. Run the fix lines above, then run doctor again.\n";
exit(1);
