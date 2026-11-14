#!/usr/bin/env bash
# Imports the schema twice (it must rebuild from nothing) and checks the sample data minimums.
# Usage: MYSQL="mariadb -uroot -h127.0.0.1" .github/scripts/check-database.sh
set -u
cd "$(dirname "$0")/../.." || exit 1
MYSQL=${MYSQL:-"mariadb -uroot"}
fail=0

for run in 1 2; do
  if ! $MYSQL < database/library_db.sql; then
    echo "::error file=database/library_db.sql::Import $run failed."; exit 1
  fi
done

if ! $MYSQL < database/queries.sql > /dev/null; then
  echo "::error file=database/queries.sql::A report query fails."; fail=1
fi

check() { # label minimum sql
  got=$($MYSQL -N library_db -e "$3")
  if [ "$got" -lt "$2" ]; then
    echo "::error file=database/library_db.sql::Need at least $2 $1, found $got."; fail=1
  else
    echo "ok  $1: $got (need $2)"
  fi
}
check "tables" 6 "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'library_db'"
check "foreign keys" 4 "SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = 'library_db'"
check "books" 10 "SELECT COUNT(*) FROM books"
check "authors" 5 "SELECT COUNT(*) FROM authors"
check "categories" 5 "SELECT COUNT(*) FROM categories"
check "members" 10 "SELECT COUNT(*) FROM members"
check "loans" 10 "SELECT COUNT(*) FROM loans"
check "returned loans" 1 "SELECT COUNT(*) FROM loans WHERE date_returned IS NOT NULL"
check "open loans" 1 "SELECT COUNT(*) FROM loans WHERE date_returned IS NULL"
check "overdue loans" 1 "SELECT COUNT(*) FROM loans WHERE date_returned IS NULL AND due_date < CURDATE()"
check "JOIN queries in queries.sql" 3 "SELECT $(grep -ciE '^\s*(LEFT |RIGHT |INNER )?JOIN ' database/queries.sql)"
check "aggregate queries in queries.sql" 1 "SELECT $(grep -ciE 'COUNT\(|SUM\(|GROUP BY' database/queries.sql)"

duplicate_error=$($MYSQL library_db -e "
  START TRANSACTION;
  INSERT INTO loans (member_id, book_id, date_issued, due_date)
  VALUES (1, 1, CURDATE(), CURDATE() + INTERVAL 14 DAY);
  ROLLBACK" 2>&1) && duplicate_status=0 || duplicate_status=$?
if [ "$duplicate_status" -eq 0 ]; then
  echo "::error file=database/library_db.sql::The database allowed a duplicate open loan for one member and book."; fail=1
elif ! printf '%s' "$duplicate_error" | grep -q "Duplicate entry"; then
  echo "::error file=database/library_db.sql::Duplicate-loan test failed for an unexpected reason: $duplicate_error"; fail=1
else
  echo "ok  duplicate open loans are rejected by the database"
fi

mismatch=$($MYSQL -N library_db -e "
  SELECT COUNT(*) FROM (
    SELECT b.book_id FROM books b
    LEFT JOIN loans l ON l.book_id = b.book_id AND l.date_returned IS NULL
    GROUP BY b.book_id
    HAVING MAX(b.total_copies) - COUNT(l.loan_id) <> MAX(b.available_copies)
  ) x")
if [ "$mismatch" != "0" ]; then
  echo "::error file=database/library_db.sql::$mismatch books have available_copies that do not match their open loans."; fail=1
fi

plain=$($MYSQL -N library_db -e "SELECT COUNT(*) FROM users WHERE password_hash NOT LIKE '\$2y\$%' AND password_hash NOT LIKE '\$argon2%'")
[ "$plain" = "0" ] || { echo "::error file=database/library_db.sql::$plain users have a password that is not hashed."; fail=1; }

[ "$fail" = 0 ] && echo "Database checks pass."
exit "$fail"
