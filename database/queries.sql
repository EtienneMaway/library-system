-- Report queries. The brief asks for at least 3 JOIN queries and 1 aggregate.
-- Queries 1 and 2 are done. The database owner finishes 3 and 4 and adds more.

USE library_db;

-- 1. Books currently on loan, with the member's name.
SELECT b.title, m.member_number, m.full_name, l.date_issued, l.due_date
FROM loans l
JOIN books b   ON b.book_id = l.book_id
JOIN members m ON m.member_id = l.member_id
WHERE l.date_returned IS NULL
ORDER BY l.due_date;

-- 2. Number of loans per category (aggregate).
SELECT c.name AS category, COUNT(l.loan_id) AS total_loans
FROM categories c
LEFT JOIN books b ON b.category_id = c.category_id
LEFT JOIN loans l ON l.book_id = b.book_id
GROUP BY c.category_id, c.name
ORDER BY total_loans DESC, c.name;

-- 3. TODO (database owner): overdue loans, with days overdue and the member's phone.

-- 4. TODO (database owner): every book with its author and category, plus copies out.
