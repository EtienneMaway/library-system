#!/usr/bin/env bash
# Enforces the security rules from section 4.2 and 10 of the brief.
set -u
cd "$(dirname "$0")/../.." || exit 1
fail=0
problem() { echo "::error file=$1::$2"; fail=1; }

php_files=$(git ls-files '*.php')

for f in $php_files; do
  # SQL built from variables: query()/exec() with a $variable or concatenation inside.
  if grep -nE '\->(query|exec)\(\s*("[^"]*\$|[^)]*\.\s*\$)' "$f" >/dev/null; then
    problem "$f" "SQL is built from a variable. Use \$pdo->prepare() with ? placeholders and execute()."
  fi
  if grep -nE '\->prepare\(\s*("[^"]*\$[a-zA-Z_]|[^)]*\.\s*\$)' "$f" >/dev/null; then
    problem "$f" "A variable is pasted into prepare(). Pass values through execute([...]) instead."
  fi
  if grep -nE '\bmysqli_query\b|\bmysql_query\b' "$f" >/dev/null; then
    problem "$f" "Use PDO prepared statements, not mysqli_query."
  fi
  if grep -nE '\bphpinfo\s*\(' "$f" >/dev/null; then
    problem "$f" "Remove phpinfo(). The brief says to delete it before submitting."
  fi
  if grep -nE '\b(var_dump|print_r|var_export)\s*\(' "$f" >/dev/null; then
    problem "$f" "Remove debugging output (var_dump, print_r, var_export)."
  fi
  if grep -nE '(echo|print|<\?=)\s*\$_(GET|POST|REQUEST|COOKIE)' "$f" >/dev/null; then
    problem "$f" "User input is printed without e(). Wrap it: e(\$value)."
  fi
  if grep -nE 'getMessage\(\)' "$f" | grep -vE 'error_log' >/dev/null; then
    problem "$f" "Do not show database error messages to users. Log them with error_log() and show a friendly message."
  fi
  if grep -nE '\bmd5\s*\(|\bsha1\s*\(' "$f" >/dev/null; then
    problem "$f" "Use password_hash() and password_verify() for passwords, not md5 or sha1."
  fi
done

# Every page in a protected folder must load the login check before anything else.
for f in $(git ls-files 'dashboard/*.php' 'books/*.php' 'members/*.php' 'loans/*.php' 'authors/*.php' 'categories/*.php'); do
  first=$(grep -m1 -nE 'require(_once)?\s' "$f" || true)
  case "$first" in
    *auth_check.php*) ;;
    *) problem "$f" "The first require must be includes/auth_check.php so logged-out users are blocked.";;
  esac
done

# Forms that change data must carry the CSRF token.
for f in $php_files; do
  if grep -qiE '<form[^>]*method="post"' "$f" && ! grep -q 'csrf_field()' "$f"; then
    problem "$f" "This POST form has no csrf_field()."
  fi
done

# No real-looking secrets in the repository.
if git ls-files | xargs grep -nIE '(api[_-]?key|secret|token)\s*=\s*["'"'"'][A-Za-z0-9]{20,}' 2>/dev/null | grep -v '^.github/' >/dev/null; then
  echo "::error::Something that looks like a secret key is committed."; fail=1
fi

[ "$fail" = 0 ] && echo "Security rules pass."
exit "$fail"
