#!/usr/bin/env bash
# Runs the same checks as GitHub, on your own computer.
#
#   bash .github/scripts/check-all.sh          code checks (fast, no database needed)
#   bash .github/scripts/check-all.sh --full   also imports the database and tests the running app
#   bash .github/scripts/check-all.sh --fix    fixes PHP code style automatically
#
# Works in Git Bash on Windows and Terminal on a Mac. Missing tools are skipped with a note.
set -u
cd "$(dirname "$0")/../.." || exit 1

FULL=0; [ "${1:-}" = "--full" ] && FULL=1
TOOLS="${HOME}/.cache/library-system-tools"
failed=()
skipped=()

if [ -t 1 ]; then G=$'\e[32m'; R=$'\e[31m'; Y=$'\e[33m'; B=$'\e[1m'; N=$'\e[0m'; else G=; R=; Y=; B=; N=; fi
step() { printf '\n%s== %s%s\n' "$B" "$1" "$N"; }
pass() { printf '%sPASS%s %s\n' "$G" "$N" "$1"; }
fail() { printf '%sFAIL%s %s\n' "$R" "$N" "$1"; failed+=("$1"); }
skip() { printf '%sSKIP%s %s: %s\n' "$Y" "$N" "$1" "$2"; skipped+=("$1"); }
run()  { local name=$1; shift; if "$@"; then pass "$name"; else fail "$name"; fi; }

# Find PHP: on PATH, or XAMPP's default location on Windows.
PHP=$(command -v php || true)
[ -z "$PHP" ] && [ -x /c/xampp/php/php.exe ] && PHP=/c/xampp/php/php.exe

phar() { # name url -> path, downloaded once into ~/.cache
  local file="$TOOLS/$1.phar"
  if [ ! -f "$file" ]; then
    mkdir -p "$TOOLS"
    curl -fsSL -o "$file" "$2" 2>/dev/null || { rm -f "$file"; return 1; }
  fi
  echo "$file"
}

if [ "${1:-}" = "--fix" ]; then
  [ -n "$PHP" ] || { echo "PHP not found."; exit 1; }
  PHPCBF=$(phar phpcbf https://github.com/PHPCSStandards/PHP_CodeSniffer/releases/latest/download/phpcbf.phar) || { echo "Could not download phpcbf."; exit 1; }
  "$PHP" "$PHPCBF" --standard=.github/linters/phpcs.xml
  echo "Style fixed where possible. Review the changes with: git diff"
  exit 0
fi

step "Folder structure"
run "Folder structure" bash .github/scripts/check-structure.sh

step "Security rules"
run "Security rules" bash .github/scripts/check-security.sh

step "PHP"
if [ -z "$PHP" ]; then
  skip "PHP checks" "PHP not found. Install XAMPP or add php to your PATH."
else
  lint() { local s=0; for f in $(git ls-files '*.php'); do "$PHP" -l "$f" >/dev/null || s=1; done; return $s; }
  run "PHP syntax ($("$PHP" -r 'echo PHP_VERSION;'))" lint

  if PHPCS=$(phar phpcs https://github.com/PHPCSStandards/PHP_CodeSniffer/releases/latest/download/phpcs.phar); then
    if ! "$PHP" "$PHPCS" --standard=.github/linters/phpcs.xml; then
      fail "Code style (PSR-12)"
      echo "  Most style errors fix themselves with: bash .github/scripts/check-all.sh --fix"
    else
      pass "Code style (PSR-12)"
    fi
  else
    skip "Code style" "could not download phpcs (no internet?)"
  fi

  if PHPSTAN=$(phar phpstan https://github.com/phpstan/phpstan/releases/latest/download/phpstan.phar); then
    run "Static analysis (PHPStan)" "$PHP" "$PHPSTAN" analyse --configuration=.github/linters/phpstan.neon --no-progress --memory-limit=512M
  else
    skip "Static analysis" "could not download phpstan (no internet?)"
  fi
fi

step "JavaScript and CSS"
if command -v npx >/dev/null; then
  run "ESLint" npx --yes eslint@9.17.0 --config .github/linters/eslint.config.mjs assets/js
  run "Stylelint" npx --yes stylelint@16.12.0 --config .github/linters/stylelint.json "assets/css/**/*.css"
else
  skip "JavaScript and CSS" "Node.js not found. Install it from nodejs.org to run these locally."
fi

if [ "$FULL" = 1 ]; then
  step "Database (this resets library_db to the sample data)"
  if [ -n "${MYSQL:-}" ]; then :;
  elif [ -x /c/xampp/mysql/bin/mysql.exe ]; then MYSQL="/c/xampp/mysql/bin/mysql.exe -uroot";
  elif [ -x /opt/homebrew/opt/mariadb@10.11/bin/mariadb ]; then MYSQL="/opt/homebrew/opt/mariadb@10.11/bin/mariadb -uroot";
  else MYSQL="mysql -uroot"; fi
  run "Database import and sample data" env MYSQL="$MYSQL" bash .github/scripts/check-database.sh

  step "App smoke test"
  if [ -z "${BASE_URL:-}" ]; then
    if curl -s -o /dev/null http://localhost/library-system/auth/login.php; then BASE_URL=http://localhost/library-system
    else BASE_URL=http://localhost:8080/library-system; fi
  fi
  echo "Testing $BASE_URL"
  run "App smoke test" env BASE_URL="$BASE_URL" bash .github/scripts/check-smoke.sh
fi

printf '\n%s== Summary%s\n' "$B" "$N"
[ ${#skipped[@]} -gt 0 ] && printf '%sSkipped:%s %s\n' "$Y" "$N" "${skipped[*]}"
if [ ${#failed[@]} -gt 0 ]; then
  printf '%sFailed:%s %s\n' "$R" "$N" "$(IFS=,; echo "${failed[*]}")"
  echo "Scroll up for the file and line of each problem. GitHub will block the merge until these pass."
  exit 1
fi
printf '%sAll checks passed.%s\n' "$G" "$N"
[ "$FULL" = 0 ] && echo "Run with --full before a pull request to also test the database and the app."
exit 0
