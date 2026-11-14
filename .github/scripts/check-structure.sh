#!/usr/bin/env bash
# Checks the folder layout against section 5 of the project brief.
set -u
cd "$(dirname "$0")/../.." || exit 1
fail=0
problem() { echo "::error::$1"; fail=1; }

required=(
  index.php README.md config/database.php
  database/library_db.sql database/queries.sql database/erd.png
  includes/header.php includes/navbar.php includes/footer.php includes/auth_check.php includes/functions.php
  auth/login.php auth/logout.php dashboard/index.php
  books/index.php books/create.php books/edit.php books/delete.php
  members/index.php members/create.php members/edit.php members/delete.php
  loans/index.php loans/issue.php loans/return.php
  authors/index.php authors/edit.php authors/delete.php categories/index.php
)
for f in "${required[@]}"; do
  [ -f "$f" ] || problem "Missing required file: $f"
done
for d in assets/css assets/js assets/images docs/screenshots; do
  [ -d "$d" ] || problem "Missing required folder: $d"
done

# Only index.php, README.md and composer.json may sit in the root. Dot files (.github) are tooling.
while IFS= read -r f; do
  case "$f" in index.php|README.md|composer.json) ;; *) problem "Loose file in the project root: $f (move it into a folder)";; esac
done < <(git ls-files | grep -v '/' | grep -v '^\.')

allowed_top="assets auth authors books categories config dashboard database docs includes loans members"
while IFS= read -r d; do
  case " $allowed_top " in *" $d "*) ;; *) problem "Folder not in the brief's structure: $d/";; esac
done < <(git ls-files | grep '/' | grep -v '^\.' | cut -d/ -f1 | sort -u)

# Lowercase names with no spaces (README.md is named by the brief).
while IFS= read -r f; do
  [ "$f" = "README.md" ] && continue
  if [[ "$f" =~ [A-Z\ ] ]]; then problem "Use lowercase names with no spaces: $f"; fi
done < <(git ls-files | grep -v '^\.')

# One database connection only.
count=$(grep -rl --include='*.php' 'new PDO\|mysqli_connect\|new mysqli' . | grep -v '^./.github' | wc -l | tr -d ' ')
[ "$count" = "1" ] || problem "Found $count files that open a database connection. Only config/database.php may."
grep -q 'new PDO' config/database.php || problem "config/database.php must open the PDO connection."

[ "$fail" = 0 ] && echo "Structure matches the brief."
exit "$fail"
