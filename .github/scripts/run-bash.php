<?php

declare(strict_types=1);

function findExecutable(string $name): ?string
{
    $extensions = PHP_OS_FAMILY === 'Windows'
        ? (getenv('PATHEXT') ? explode(';', getenv('PATHEXT')) : ['.EXE', '.BAT', '.CMD'])
        : [''];

    foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
        foreach ($extensions as $extension) {
            $candidate = rtrim($directory, '\\/') . DIRECTORY_SEPARATOR . $name . $extension;
            if (is_file($candidate) && (PHP_OS_FAMILY === 'Windows' || is_executable($candidate))) {
                return $candidate;
            }
        }
    }

    return null;
}

function findBash(): ?string
{
    $bash = findExecutable('bash');
    if ($bash !== null || PHP_OS_FAMILY !== 'Windows') {
        return $bash;
    }

    $git = findExecutable('git');
    if ($git === null) {
        return null;
    }

    $directory = dirname($git);
    for ($depth = 0; $depth < 5; ++$depth) {
        foreach (['bin\\bash.exe', 'usr\\bin\\bash.exe'] as $relativePath) {
            $candidate = $directory . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativePath);
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $parent = dirname($directory);
        if ($parent === $directory) {
            break;
        }
        $directory = $parent;
    }

    return null;
}

$arguments = array_slice($argv, 1);
if ($arguments === []) {
    fwrite(STDERR, "Usage: php .github/scripts/run-bash.php <script> [arguments...]\n");
    exit(2);
}

$bash = findBash();
if ($bash === null) {
    fwrite(STDERR, "Bash was not found. Install Git for Windows or add Bash to PATH.\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
$process = proc_open(
    array_merge([$bash], $arguments),
    [0 => STDIN, 1 => STDOUT, 2 => STDERR],
    $pipes,
    $root
);

if (!is_resource($process)) {
    fwrite(STDERR, "Could not start Bash.\n");
    exit(1);
}

exit(proc_close($process));
