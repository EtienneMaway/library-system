# Business rule checklist

Every rule the system enforces, how to try to break it, and what should happen. Before the demo, go through it
by hand on the real pages. Tick a rule only after trying to break it.

Most of these also run automatically. `composer code:check:full` and GitHub run `.github/scripts/check-rules.sh`,
which tries each rule marked **auto** through the real pages and then checks the database.

Reset the data first with `composer dev:seed`. In the sample data:
- Peter Kato and Rita Achan are suspended.
- *Weep Not, Child* has no copies left.
- Grace Atim holds a book.

## Login and security

| | Rule | Try this | Expected | Auto |
|---|---|---|---|---|
| [ ] | Pages need a login | Log out, open `/books/index.php` | Sent to the login page with "Please log in to continue." | auto |
| [ ] | Wrong passwords fail | Log in as `admin` with `wrong` | "Those details do not match an account." | auto |
| [ ] | No form works without its CSRF token | Change the hidden `csrf_token` in DevTools, then submit | 403 page, nothing saved | auto |
| [ ] | Passwords are hashed | phpMyAdmin, `users` table | `password_hash` starts with `$2y$` | auto |
| [ ] | Only admins delete records | Log in as `librarian`, open `/categories/delete.php?id=5` | "Only an admin can delete records." and nothing deleted | auto |
| [ ] | Librarians run the desk | As `librarian`: add a book, issue and return a loan, suspend a member | All work; no Delete links on any list | auto |
| [ ] | New accounts confirm their email | Sign up, then log in before typing the code | Asked for the 6-digit code | |
| [ ] | Codes expire and run out | Type a wrong code 5 times | "Too many wrong tries. Ask for a new code." | |

## Issuing and returning loans

| | Rule | Try this | Expected | Auto |
|---|---|---|---|---|
| [ ] | A suspended member cannot borrow | Issue any book to Peter Kato (remove `disabled` in DevTools) | "A suspended member cannot borrow books." | auto |
| [ ] | No copies left, no loan | Issue *Weep Not, Child* | "There are no available copies of that book." | auto |
| [ ] | One open loan of a book per member | Issue the same book to the same member twice | "This member already has this book on loan." | auto |
| [ ] | The database blocks it too | In SQL, insert a second open loan of the same book and member | Duplicate key error `uq_loans_one_open_per_member_book` | auto |
| [ ] | Due date is 14 days | Issue a book | Toast "due in 14 days", Due column is today + 14 | auto |
| [ ] | Issue is all or nothing | Issue a book, check the book's copies | On shelf goes down by exactly 1 | auto |
| [ ] | Return puts the copy back | Return a book | Toast shows the copy count one higher | auto |
| [ ] | A loan returns only once | Return, then press Back and submit again | "This loan has already been returned." | auto |
| [ ] | Fines are UGX 500 a day late | Dashboard, compare days late and fine | Fine = days × 500 | auto |

## Books, members, authors and categories

| | Rule | Try this | Expected | Auto |
|---|---|---|---|---|
| [ ] | A book on loan cannot be deleted | Delete *Things Fall Apart* | "2 copies are still out on loan" | auto |
| [ ] | A book with loan history cannot be deleted | Delete a book whose loans were all returned | "it appears in past loans" | |
| [ ] | ISBNs are unique | Add a book with an ISBN already in the list | "Another book already has that ISBN." | auto |
| [ ] | ISBN is optional | Add a book with the ISBN left empty | It is saved and the list shows "None" | auto |
| [ ] | ISBNs are valid | Change the last digit of a real ISBN | "its last digit does not match" | auto |
| [ ] | Copies cannot drop below those on loan | Edit *Things Fall Apart* to 1 copy | "2 copies are out on loan, so the total cannot be lower" | auto |
| [ ] | A member holding a book cannot be deleted | Delete Grace Atim | "they still hold 1 book" | auto |
| [ ] | Member emails are unique | Add a member with `GRACE.ATIM@example.org` | "Another member already uses this email." | auto |
| [ ] | Member numbers are unique | Add a member with `MEM-0001` | "Another member already has this number." | |
| [ ] | An author with books cannot be deleted | Delete Chinua Achebe | "cannot be deleted while books are assigned" | auto |
| [ ] | A category with books cannot be deleted | Delete African Fiction | "cannot be deleted while books are filed under it" | auto |
| [ ] | Deletes ask first | Click any Delete button | The browser asks "Delete …? This cannot be undone." | |

## Screens

| | Check | How | Expected |
|---|---|---|---|
| [ ] | Every action shows a message | Add, edit, delete, issue, return, suspend | A toast after each one, green for done, red for a problem |
| [ ] | Phone width | DevTools device bar, 390 px wide, on every page | No sideways scrolling; each table row becomes a card |
| [ ] | Light and dark | Theme button in the header | Every page readable in both |
| [ ] | CSV export | Books, Members, Loans and Dashboard: Export CSV | Opens in Excel with the same filters as the screen |
| [ ] | Pagination | Loans list with more than 20 loans | Previous, Next and "1 to 20 of N" under the table |

## Clean run on Windows

Follow only the README, on a computer that has never run the project, with a fresh XAMPP.

| | Step | Result | Notes |
|---|---|---|---|
| [ ] | Install XAMPP, start Apache and MySQL | | |
| [ ] | Clone into `C:\xampp\htdocs\library-system` | | |
| [ ] | Import `database/library_db.sql` in phpMyAdmin | | |
| [ ] | Open the app, log in as `admin` / `admin123` | | |
| [ ] | `composer code:check:full` in Git Bash | | |

Write anything that fails in the Notes column and raise it as a GitHub issue.
