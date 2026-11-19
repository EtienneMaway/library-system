<?php

declare(strict_types=1);

// Run a project Bash script from any shell. Composer calls this with @php, so
// "composer dev:start" works the same in PowerShell, Command Prompt, Git Bash
// and the Mac Terminal. On Windows it uses Git for Windows' Bash, never WSL.
//
//   php .github/scripts/run-bash.php .github/scripts/dev.sh start

require __DIR__ . '/lib/executables.php';

$arguments = array_slice($argv, 1);
if ($arguments === []) {
    fwrite(STDERR, "Usage: php .github/scripts/run-bash.php <script> [arguments...]\n");
    exit(2);
}

$bash = findBash();
if ($bash === null) {
    fwrite(STDERR, isWindows()
        ? "Git Bash was not found. Install Git for Windows from git-scm.com, then open a new terminal.\n"
        : "Bash was not found. Install it, or run the script with your shell directly.\n");
    exit(1);
}

$process = proc_open(
    array_merge([$bash], $arguments),
    [0 => STDIN, 1 => STDOUT, 2 => STDERR],
    $pipes,
    dirname(__DIR__, 2)
);

if (!is_resource($process)) {
    fwrite(STDERR, "Could not start Bash ({$bash}).\n");
    exit(1);
}

exit(proc_close($process));
