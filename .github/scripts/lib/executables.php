<?php

declare(strict_types=1);

// Helpers shared by the developer scripts in .github/scripts to find programs
// on Windows (PowerShell, Command Prompt, Git Bash) and on a Mac.

function isWindows(): bool
{
    return PHP_OS_FAMILY === 'Windows';
}

/**
 * Find a program on PATH. On Windows this also tries the PATHEXT extensions
 * (.EXE, .BAT, .CMD, ...). Folders listed in $skipDirectories are ignored.
 *
 * @param list<string> $skipDirectories
 */
function findExecutable(string $name, array $skipDirectories = []): ?string
{
    $extensions = isWindows()
        ? (getenv('PATHEXT') ? explode(';', (string) getenv('PATHEXT')) : ['.EXE', '.BAT', '.CMD'])
        : [''];
    $skip = array_map('normalizeDirectory', $skipDirectories);

    foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
        if ($directory === '' || in_array(normalizeDirectory($directory), $skip, true)) {
            continue;
        }
        foreach ($extensions as $extension) {
            $candidate = rtrim($directory, '\\/') . DIRECTORY_SEPARATOR . $name . $extension;
            if (is_file($candidate) && (isWindows() || is_executable($candidate))) {
                return $candidate;
            }
        }
    }

    return null;
}

function normalizeDirectory(string $path): string
{
    $path = rtrim(str_replace('\\', '/', $path), '/');

    return isWindows() ? strtolower($path) : $path;
}

/**
 * Find the Bash that should run the project scripts.
 *
 * On Windows this must be Git for Windows' Bash. The bash.exe in
 * C:\Windows\System32 (and the WindowsApps alias) starts WSL instead, which runs
 * the scripts inside Linux where XAMPP is not installed, so it is never used.
 */
function findBash(): ?string
{
    if (!isWindows()) {
        return findExecutable('bash');
    }

    // 1. Next to git.exe (works for the normal installer and most custom folders).
    $git = findExecutable('git');
    if ($git !== null) {
        $directory = dirname($git);
        for ($depth = 0; $depth < 5; ++$depth) {
            foreach (['bin', 'usr' . DIRECTORY_SEPARATOR . 'bin'] as $sub) {
                $candidate = $directory . DIRECTORY_SEPARATOR . $sub . DIRECTORY_SEPARATOR . 'bash.exe';
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
    }

    // 2. The usual install folders (covers Scoop, whose git.exe is only a shim).
    $folders = [];
    foreach (['ProgramW6432', 'ProgramFiles', 'ProgramFiles(x86)'] as $variable) {
        if (getenv($variable)) {
            $folders[] = getenv($variable) . '\\Git';
        }
    }
    if (getenv('LOCALAPPDATA')) {
        $folders[] = getenv('LOCALAPPDATA') . '\\Programs\\Git';
    }
    $scoop = getenv('SCOOP') ?: (getenv('USERPROFILE') ? getenv('USERPROFILE') . '\\scoop' : null);
    if ($scoop) {
        $folders[] = $scoop . '\\apps\\git\\current';
    }
    foreach ($folders as $folder) {
        foreach (['\\bin\\bash.exe', '\\usr\\bin\\bash.exe'] as $relative) {
            if (is_file($folder . $relative)) {
                return $folder . $relative;
            }
        }
    }

    // 3. Any other bash on PATH, except the WSL launchers.
    $systemRoot = (string) (getenv('SystemRoot') ?: 'C:\\Windows');
    $skip = [$systemRoot . '\\System32', $systemRoot . '\\Sysnative'];
    if (getenv('LOCALAPPDATA')) {
        $skip[] = getenv('LOCALAPPDATA') . '\\Microsoft\\WindowsApps';
    }

    return findExecutable('bash', $skip);
}
