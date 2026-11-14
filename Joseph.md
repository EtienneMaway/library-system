# Lukwago Joseph — Database Design and Reports

## What I completed

- Updated [`database/library_db.sql`](database/library_db.sql) so the database
  itself prevents one member from having two open loans for the same book.
  Returned loans remain in the history and do not block a future loan.
- Finished all four report queries in [`database/queries.sql`](database/queries.sql):
  open loans by member, loan totals by category, overdue loans with days overdue
  and member phone numbers, and books with their authors, categories, and copies
  currently out.
- Finalized [`database/erd.png`](database/erd.png) to show the six tables and
  the open-loan guard.
- Wrote the table-by-table normalization explanation in
  [`docs/database-design.md`](docs/database-design.md), including the design's
  assumptions and the inventory-counter caveat.
- Extended [`.github/scripts/check-database.sh`](.github/scripts/check-database.sh)
  to test that a duplicate open loan is rejected and rolled back.

## How I approached the work

### 1. Enforce the loan rule in the schema

An application-only check can be bypassed by another code path and can be
unreliable if two requests arrive at the same time. I added a stored generated
column, `open_loan_guard`, to `loans`. It evaluates to `1` when
`date_returned IS NULL` and `NULL` after the loan is returned. A unique
constraint on `(book_id, member_id, open_loan_guard)` then allows only one
matching open loan. Because MariaDB permits multiple `NULL` values in a unique
key, returned loans remain available as history and the same book can be
borrowed again later.

### 2. Complete useful reports

The queries join related tables instead of repeating member, author, or
category information. The overdue report uses `DATEDIFF` and excludes returned
or not-yet-due loans. The books report uses a `LEFT JOIN` so books with no
current loans still appear, and counts only open loans as copies out. The
category report supplies the required aggregate.

### 3. Explain and show the data model

The ERD documents the entities and their one-to-many relationships. The
normalization write-up explains the keys and why descriptive attributes belong
in their respective tables. It also notes that `available_copies` is a
transactionally maintained inventory counter that must agree with the loan
records.

## Verification

I imported the schema into a temporary MariaDB 10.11.16 database and ran all
four report queries. I also confirmed that:

- a second open loan for the same member and book is rejected by the unique
  constraint; and
- a returned historical loan for that same member and book is allowed.

The temporary test database was dropped afterward; the existing `library_db`
was not reset. The repository's broader code checks were not part of this
database-specific verification.

## Rebuild the local sample database

After pulling the changes, open Git Bash in the project folder. Apache and MySQL
must be running in XAMPP. Then run:

```bash
composer dev:seed
```

Confirm with `y` when prompted. This command drops and recreates the local
`library_db`, then loads the sample data using the updated schema. **It erases
all existing data in `library_db`**, so back up anything you want to keep first.
The existing setup can be checked with `composer dev:doctor`; there is no need
to run `composer dev:start` again if it already reports that the servers are
running.

> **NB for the team:** Seeding only rebuilds the database and loads sample
> records. It does not implement unfinished screens or features. For example,
> the Books page currently still says “Not built yet”; its list, search, and
> author/category filters must be implemented by the Books workstream. Refreshing
> the page or reseeding will not change that placeholder.

## How this can be improved

1. **Keep the inventory count atomic.** The application should update a loan
   and `available_copies` in the same transaction when issuing or returning a
   book. Add tests for failed updates and concurrent requests so the count
   cannot drift from the open-loan records.
2. **Expand regression coverage.** Test that a returned loan is allowed by the
   automated database check, and cover boundary cases such as a loan due today,
   returned overdue loans, and books or categories with no loans.
3. **Keep the ERD and schema in sync.** If tables, keys, or relationships
   change, update the diagram and normalization explanation in the same
   change.
4. **Merge this section into the group report.** [`docs/database-design.md`](docs/database-design.md)
   is the report-ready database section; the team still needs to combine it
   with the other members' sections and produce the final submission PDF.

5. **Follow the processes in README.md**