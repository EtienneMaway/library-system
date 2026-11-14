#!/usr/bin/env bash
# Logs in through the real pages and checks that every page loads without PHP errors.
# Usage: BASE_URL=http://localhost:8080/library-system .github/scripts/check-smoke.sh
set -u
BASE=${BASE_URL:-http://localhost/library-system}
JAR=$(mktemp)
fail=0
problem() { echo "::error::$1"; fail=1; }
status() { curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" "$@"; }
token() { curl -s -b "$JAR" -c "$JAR" "$1" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1; }

pages="dashboard/index.php books/index.php books/create.php books/edit.php books/delete.php
members/index.php members/create.php members/edit.php members/delete.php
loans/index.php loans/issue.php loans/return.php authors/index.php authors/edit.php?id=1 authors/delete.php?id=1 categories/index.php"

for p in $pages; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/$p")
  [ "$code" = "302" ] || problem "$p answered $code while logged out. It must redirect to the login page."
done

t=$(token "$BASE/auth/login.php")
[ -n "$t" ] || problem "The login page has no CSRF token."
curl -s -b "$JAR" -c "$JAR" -d "csrf_token=$t&username=admin&password=wrong" "$BASE/auth/login.php" | grep -q 'do not match' \
  || problem "A wrong password did not show the error message."
[ "$(status -d "username=admin&password=admin123" "$BASE/auth/login.php")" = "403" ] \
  || problem "A login without a CSRF token was not rejected."
[ "$(status -d "csrf_token=$t&username=admin&password=admin123" "$BASE/auth/login.php")" = "302" ] \
  || problem "The test account admin / admin123 could not log in."

for p in $pages; do
  body=$(curl -s -b "$JAR" -c "$JAR" -w '\n%{http_code}' "$BASE/$p")
  code=${body##*$'\n'}
  [ "$code" = "200" ] || problem "$p answered $code after login."
  if echo "$body" | grep -qE '(Fatal error|Warning|Notice|Deprecated|Parse error|Uncaught|SQLSTATE)'; then
    problem "$p shows a PHP or SQL error on the page."
  fi
done

curl -s -o /dev/null -b "$JAR" -c "$JAR" "$BASE/auth/logout.php"
[ "$(status "$BASE/dashboard/index.php")" = "302" ] || problem "The dashboard still opens after logging out."

rm -f "$JAR"
[ "$fail" = 0 ] && echo "Smoke test passes."
exit "$fail"
