-- Report queries. The brief asks for at least 3 JOIN queries and 1 aggregate.

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

-- 3. Overdue open loans, with days overdue and the member's phone.
SELECT b.title, m.member_number, m.full_name, m.phone, l.due_date,
       DATEDIFF(CURDATE(), l.due_date) AS days_overdue
FROM loans l
JOIN books b   ON b.book_id = l.book_id
JOIN members m ON m.member_id = l.member_id
WHERE l.date_returned IS NULL
  AND l.due_date < CURDATE()
ORDER BY days_overdue DESC, m.full_name;

-- 4. Every book with its author and category, plus the number of copies out.
SELECT b.isbn, b.title, a.name AS author, c.name AS category,
       b.total_copies, b.available_copies,
       COUNT(l.loan_id) AS copies_out
FROM books b
JOIN authors a    ON a.author_id = b.author_id
JOIN categories c ON c.category_id = b.category_id
LEFT JOIN loans l ON l.book_id = b.book_id
                  AND l.date_returned IS NULL
GROUP BY b.book_id, b.isbn, b.title, a.name, c.name,
         b.total_copies, b.available_copies
ORDER BY b.title;
