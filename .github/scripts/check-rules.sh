#!/usr/bin/env bash
# Tries to break every business rule through the real pages, then checks the database agrees.
# docs/rule-checklist.md lists the same rules for testing by hand. It changes data, so it re-imports the
# sample data at the end.
# Usage: BASE_URL=http://localhost:8080/library-system MYSQL="mariadb -uroot" .github/scripts/check-rules.sh
set -u
cd "$(dirname "$0")/../.." || exit 1
BASE=${BASE_URL:-http://localhost/library-system}
MYSQL=${MYSQL:-"mariadb -uroot"}
JAR=$(mktemp)
fail=0
passed=0

sql() { $MYSQL -N library_db -e "$1"; }
token() { curl -s -b "$JAR" -c "$JAR" "$BASE/books/create.php" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1; }
# post PAGE FIELDS...: sends the form with a fresh CSRF token, follows the redirect and prints the page.
post() { local page=$1; shift; curl -s -L -b "$JAR" -c "$JAR" --data-urlencode "csrf_token=$(token)" "$@" "$BASE/$page"; }
# expect RULE "text the page must show" PAGE-HTML
expect() {
  if printf '%s' "$3" | sed 's/&quot;/"/g; s/&#039;/'"'"'/g' | grep -qF -- "$2"; then
    echo "ok    $1"; passed=$((passed + 1))
  else
    echo "::error::Rule broken: $1. Expected the page to say: $2"; fail=1
  fi
}
# same RULE expected actual
same() {
  if [ "$2" = "$3" ]; then echo "ok    $1"; passed=$((passed + 1))
  else echo "::error::Rule broken: $1. Expected $2, got $3"; fail=1; fi
}

# --- Login and security
same "A protected page sends a logged-out visitor to log in" 302 \
  "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/books/index.php")"
t=$(curl -s -b "$JAR" -c "$JAR" "$BASE/auth/login.php" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)
expect "A wrong password is refused" "do not match" \
  "$(curl -s -b "$JAR" -c "$JAR" --data-urlencode "csrf_token=$t" -d 'username=admin&password=wrong' "$BASE/auth/login.php")"
same "A form sent without its CSRF token is refused" 403 \
  "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" -d 'username=admin&password=admin123' "$BASE/auth/login.php")"
same "Passwords are stored as hashes, never as typed" 0 \
  "$(sql "SELECT COUNT(*) FROM users WHERE password_hash NOT LIKE '\$2y\$%' OR password_hash IN ('admin123', 'library123')")"
curl -s -o /dev/null -b "$JAR" -c "$JAR" --data-urlencode "csrf_token=$t" -d 'username=admin&password=admin123' "$BASE/auth/login.php"

# --- Issuing loans
suspended=$(sql "SELECT member_id FROM members WHERE status = 'suspended' LIMIT 1")
active=$(sql "SELECT member_id FROM members WHERE status = 'active' ORDER BY member_id LIMIT 1")
empty_book=$(sql "SELECT book_id FROM books WHERE available_copies = 0 LIMIT 1")
free_book=$(sql "SELECT book_id FROM books b WHERE available_copies > 1 AND NOT EXISTS (SELECT 1 FROM loans l
  WHERE l.book_id = b.book_id AND l.member_id = $active AND l.date_returned IS NULL) ORDER BY book_id LIMIT 1")

expect "A suspended member cannot borrow" "A suspended member cannot borrow books." \
  "$(post loans/issue.php -d "member_id=$suspended&book_id=$free_book")"
expect "A book with no copies left cannot be issued" "There are no available copies of that book." \
  "$(post loans/issue.php -d "member_id=$active&book_id=$empty_book")"

before=$(sql "SELECT available_copies FROM books WHERE book_id = $free_book")
expect "Issuing a book works" "Book issued. It is due in 14 days." \
  "$(post loans/issue.php -d "member_id=$active&book_id=$free_book")"
same "Issuing takes one copy off the shelf" "$((before - 1))" \
  "$(sql "SELECT available_copies FROM books WHERE book_id = $free_book")"
same "The due date is 14 days after issue" 14 \
  "$(sql "SELECT DATEDIFF(due_date, date_issued) FROM loans WHERE member_id = $active AND book_id = $free_book AND date_returned IS NULL")"
expect "A member cannot borrow the same book twice at once" "This member already has this book on loan." \
  "$(post loans/issue.php -d "member_id=$active&book_id=$free_book")"
same "The refused issues left the copy count alone" "$((before - 1))" \
  "$(sql "SELECT available_copies FROM books WHERE book_id = $free_book")"

# --- Returns
loan=$(sql "SELECT loan_id FROM loans WHERE member_id = $active AND book_id = $free_book AND date_returned IS NULL")
expect "Returning a book works and shows the copy count" "now has $before of" \
  "$(post loans/return.php -d "loan_id=$loan")"
same "Returning puts the copy back on the shelf" "$before" \
  "$(sql "SELECT available_copies FROM books WHERE book_id = $free_book")"
expect "A loan cannot be returned twice" "This loan has already been returned." \
  "$(post loans/return.php -d "loan_id=$loan")"
same "The second return did not add a copy" "$before" \
  "$(sql "SELECT available_copies FROM books WHERE book_id = $free_book")"

# --- Guarded deletes
out_book=$(sql "SELECT book_id FROM loans WHERE date_returned IS NULL LIMIT 1")
expect "A book that is out on loan cannot be deleted" "still out on loan" \
  "$(post books/delete.php -d "id=$out_book")"
holder=$(sql "SELECT member_id FROM loans WHERE date_returned IS NULL LIMIT 1")
expect "A member who holds a book cannot be deleted" "cannot be deleted: they still hold" \
  "$(post members/delete.php -d "id=$holder")"
author=$(sql "SELECT author_id FROM books LIMIT 1")
expect "An author who has books cannot be deleted" "cannot be deleted while books are assigned" \
  "$(post authors/delete.php -d "id=$author")"
category=$(sql "SELECT category_id FROM books LIMIT 1")
expect "A category with books cannot be deleted" "cannot be deleted while books are filed under it" \
  "$(post categories/delete.php -d "id=$category")"
same "None of the guarded rows were deleted" 4 \
  "$(sql "SELECT (SELECT COUNT(*) FROM books WHERE book_id = $out_book) + (SELECT COUNT(*) FROM members WHERE member_id = $holder)
    + (SELECT COUNT(*) FROM authors WHERE author_id = $author) + (SELECT COUNT(*) FROM categories WHERE category_id = $category)")"

# --- Validation and duplicates
isbn=$(sql "SELECT isbn FROM books LIMIT 1")
expect "Two books cannot share an ISBN" "Another book already has that ISBN." \
  "$(post books/create.php -d "isbn=$isbn&title=Copy&author_id=$author&category_id=$category&publisher=P&year_published=2000&total_copies=1")"
expect "A mistyped ISBN is refused" "its last digit does not match" \
  "$(post books/create.php -d "isbn=9780385474543&title=Copy&author_id=$author&category_id=$category&publisher=P&year_published=2000&total_copies=1")"
expect "A book can be added without an ISBN" "added to the catalogue" \
  "$(post books/create.php -d "isbn=&title=NoIsbnA&author_id=$author&category_id=$category&publisher=P&year_published=2000&total_copies=1")"
expect "A second book without an ISBN is also fine" "added to the catalogue" \
  "$(post books/create.php -d "isbn=&title=NoIsbnB&author_id=$author&category_id=$category&publisher=P&year_published=2000&total_copies=1")"
same "Both books were saved with no ISBN" 2 "$(sql "SELECT COUNT(*) FROM books WHERE title LIKE 'NoIsbn%' AND isbn IS NULL")"
sql "DELETE FROM books WHERE title LIKE 'NoIsbn%'" >/dev/null
busy_book=$(sql "SELECT book_id FROM loans WHERE date_returned IS NULL GROUP BY book_id HAVING COUNT(*) > 1 LIMIT 1")
expect "Total copies cannot drop below the copies on loan" "out on loan, so the total cannot be lower" \
  "$(post books/edit.php -d "id=$busy_book&isbn=$(sql "SELECT isbn FROM books WHERE book_id = $busy_book")&title=T&author_id=$author&category_id=$category&publisher=P&year_published=2000&total_copies=1")"
email=$(sql "SELECT email FROM members LIMIT 1")
expect "Two members cannot share an email" "Another member already uses this email." \
  "$(post members/create.php -d "member_number=MEM-9999&full_name=Copy&email=$(echo "$email" | tr '[:lower:]' '[:upper:]')&phone=0700555999&status=active")"
same "The refused book and member were not saved" 0 \
  "$(sql "SELECT (SELECT COUNT(*) FROM books WHERE title = 'Copy') + (SELECT COUNT(*) FROM members WHERE member_number = 'MEM-9999')")"

# --- Admin and librarian limits
LIB=$(mktemp)
t=$(curl -s -b "$LIB" -c "$LIB" "$BASE/auth/login.php" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)
curl -s -o /dev/null -b "$LIB" -c "$LIB" --data-urlencode "csrf_token=$t" -d 'username=librarian&password=library123' "$BASE/auth/login.php"
spare=$(sql "SELECT category_id FROM categories c WHERE NOT EXISTS (SELECT 1 FROM books b WHERE b.category_id = c.category_id) LIMIT 1")
lt=$(curl -s -b "$LIB" -c "$LIB" "$BASE/books/create.php" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -1)
expect "A librarian cannot delete records" "Only an admin can delete records." \
  "$(curl -s -L -b "$LIB" -c "$LIB" --data-urlencode "csrf_token=$lt" -d "id=$spare" "$BASE/categories/delete.php")"
same "The librarian's delete changed nothing" 1 "$(sql "SELECT COUNT(*) FROM categories WHERE category_id = $spare")"
same "A librarian sees no Delete links" 0 \
  "$(curl -s -b "$LIB" "$BASE/books/index.php" | grep -c 'books/delete.php')"
expect "A librarian can still issue a book" "Book issued. It is due in 14 days." \
  "$(curl -s -L -b "$LIB" -c "$LIB" --data-urlencode "csrf_token=$lt" -d "member_id=$active&book_id=$free_book" "$BASE/loans/issue.php")"

# --- Fines and reports
late=$(sql "SELECT DATEDIFF(CURDATE(), due_date) FROM loans WHERE date_returned IS NULL AND due_date < CURDATE() ORDER BY due_date LIMIT 1")
csv=$(curl -s -b "$JAR" "$BASE/dashboard/index.php?export=csv")
expect "The overdue CSV has its heading row" 'Member,"Member number",Phone,Book,Due,"Days late","Fine (UGX)"' "$csv"
expect "The fine is 500 for each day late" ",$late,$((late * 500))" "$csv"

$MYSQL < database/library_db.sql || { echo "::error::Could not re-import the sample data."; fail=1; }
echo "$passed rules held."
exit $fail
