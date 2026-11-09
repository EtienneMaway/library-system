-- Library Management System: schema and sample data.
-- Import this file into a fresh server, or re-import it to reset everything.
-- Tested on MariaDB 10.11, the version XAMPP ships.

CREATE DATABASE IF NOT EXISTS library_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE library_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS authors;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE authors (
  author_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  country     VARCHAR(80)  NOT NULL,
  CONSTRAINT uq_authors_name_country UNIQUE (name, country)
) ENGINE=InnoDB;

CREATE TABLE categories (
  category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  description VARCHAR(255) NULL,
  CONSTRAINT uq_categories_name UNIQUE (name)
) ENGINE=InnoDB;

CREATE TABLE books (
  book_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  isbn             VARCHAR(17)  NOT NULL,
  title            VARCHAR(200) NOT NULL,
  author_id        INT UNSIGNED NOT NULL,
  category_id      INT UNSIGNED NOT NULL,
  publisher        VARCHAR(120) NOT NULL,
  year_published   SMALLINT UNSIGNED NOT NULL,
  total_copies     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  available_copies SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT uq_books_isbn UNIQUE (isbn),
  CONSTRAINT fk_books_author FOREIGN KEY (author_id)
    REFERENCES authors (author_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_books_category FOREIGN KEY (category_id)
    REFERENCES categories (category_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_books_copies CHECK (available_copies <= total_copies),
  CONSTRAINT ck_books_year CHECK (year_published BETWEEN 1400 AND 2100)
) ENGINE=InnoDB;

CREATE TABLE members (
  member_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_number VARCHAR(20)  NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL,
  phone         VARCHAR(20)  NOT NULL,
  status        ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  date_joined   DATE NOT NULL,
  CONSTRAINT uq_members_number UNIQUE (member_number),
  CONSTRAINT uq_members_email UNIQUE (email)
) ENGINE=InnoDB;

CREATE TABLE loans (
  loan_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id     INT UNSIGNED NOT NULL,
  book_id       INT UNSIGNED NOT NULL,
  date_issued   DATE NOT NULL,
  due_date      DATE NOT NULL,
  date_returned DATE NULL,
  CONSTRAINT fk_loans_member FOREIGN KEY (member_id)
    REFERENCES members (member_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_loans_book FOREIGN KEY (book_id)
    REFERENCES books (book_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_loans_due CHECK (due_date >= date_issued),
  CONSTRAINT ck_loans_returned CHECK (date_returned IS NULL OR date_returned >= date_issued),
  INDEX idx_loans_open (book_id, member_id, date_returned)
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(40)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  role          ENUM('admin', 'librarian') NOT NULL DEFAULT 'librarian',
  CONSTRAINT uq_users_username UNIQUE (username)
) ENGINE=InnoDB;

-- Test accounts only. The passwords are listed in README.md.
INSERT INTO users (username, password_hash, full_name, role) VALUES
  ('admin',     '$2y$12$XJb.D71ThIgzoliAmNoCIOneCGndWUP1f4jmZhhFyLNI/CABkG01W',     'Test Admin',     'admin'),
  ('librarian', '$2y$12$u0hZ51RqBGibmZ.N38n6YOO0XWbJXXjVQwGGTC2w2maLm429mevOq', 'Test Librarian', 'librarian');

INSERT INTO authors (author_id, name, country) VALUES
  (1, 'Chinua Achebe',            'Nigeria'),
  (2, 'Ngũgĩ wa Thiong''o',       'Kenya'),
  (3, 'Okot p''Bitek',            'Uganda'),
  (4, 'Chimamanda Ngozi Adichie', 'Nigeria'),
  (5, 'Abraham Silberschatz',     'United States'),
  (6, 'Ramez Elmasri',            'United States');

INSERT INTO categories (category_id, name, description) VALUES
  (1, 'African Fiction',  'Novels and short stories from African writers'),
  (2, 'Poetry',           'Collections and long poems'),
  (3, 'Computer Science', 'Programming, systems and theory'),
  (4, 'Databases',        'Database design and administration'),
  (5, 'Reference',        'Dictionaries, handbooks and guides');

-- Sample data: the ISBNs, publishers and years have not been checked against a catalogue.
INSERT INTO books (book_id, isbn, title, author_id, category_id, publisher, year_published, total_copies, available_copies) VALUES
  (1,  '9780385474542', 'Things Fall Apart',                 1, 1, 'Anchor Books',       1994, 4, 2),
  (2,  '9780385333900', 'No Longer at Ease',                 1, 1, 'Anchor Books',       1994, 2, 2),
  (3,  '9780143106692', 'A Grain of Wheat',                  2, 1, 'Penguin Classics',   2012, 3, 2),
  (4,  '9780435908300', 'Weep Not, Child',                   2, 1, 'Heinemann',          1987, 2, 0),
  (5,  '9780435906627', 'Song of Lawino and Song of Ocol',   3, 2, 'Heinemann',          1984, 3, 2),
  (6,  '9781400095209', 'Half of a Yellow Sun',              4, 1, 'Anchor Books',       2007, 3, 3),
  (7,  '9780307455925', 'Americanah',                        4, 1, 'Anchor Books',       2014, 2, 1),
  (8,  '9780078022159', 'Database System Concepts',          5, 4, 'McGraw-Hill',        2019, 5, 4),
  (9,  '9781119800361', 'Operating System Concepts',         5, 3, 'Wiley',              2021, 3, 3),
  (10, '9780133970777', 'Fundamentals of Database Systems',  6, 4, 'Pearson',            2015, 4, 3);

-- Fictional people. Numbers use the reserved 555 range and emails use example.org.
INSERT INTO members (member_id, member_number, full_name, email, phone, status, date_joined) VALUES
  (1,  'MEM-0001', 'Grace Atim',       'grace.atim@example.org',      '+256700555001', 'active',    '2025-02-03'),
  (2,  'MEM-0002', 'Daniel Okello',    'daniel.okello@example.org',   '+256700555002', 'active',    '2025-02-17'),
  (3,  'MEM-0003', 'Sarah Namukasa',   'sarah.namukasa@example.org',  '+256700555003', 'active',    '2025-03-09'),
  (4,  'MEM-0004', 'Peter Kato',       'peter.kato@example.org',      '+256700555004', 'suspended', '2025-03-22'),
  (5,  'MEM-0005', 'Aisha Nabirye',    'aisha.nabirye@example.org',   '+256700555005', 'active',    '2025-05-11'),
  (6,  'MEM-0006', 'John Deng',        'john.deng@example.org',       '+256700555006', 'active',    '2025-06-01'),
  (7,  'MEM-0007', 'Mary Akello',      'mary.akello@example.org',     '+256700555007', 'active',    '2025-08-14'),
  (8,  'MEM-0008', 'Samuel Mugisha',   'samuel.mugisha@example.org',  '+256700555008', 'active',    '2025-09-02'),
  (9,  'MEM-0009', 'Rita Achan',       'rita.achan@example.org',      '+256700555009', 'suspended', '2026-01-19'),
  (10, 'MEM-0010', 'Isaac Tumusiime',  'isaac.tumusiime@example.org', '+256700555010', 'active',    '2026-02-26');

-- Dates are relative to the import day, so the overdue rows stay overdue.
-- Open loans here must match available_copies in the books table above.
INSERT INTO loans (member_id, book_id, date_issued, due_date, date_returned) VALUES
  -- returned
  (1,  8,  CURDATE() - INTERVAL 60 DAY, CURDATE() - INTERVAL 46 DAY, CURDATE() - INTERVAL 50 DAY),
  (2,  6,  CURDATE() - INTERVAL 55 DAY, CURDATE() - INTERVAL 41 DAY, CURDATE() - INTERVAL 40 DAY),
  (3,  9,  CURDATE() - INTERVAL 40 DAY, CURDATE() - INTERVAL 26 DAY, CURDATE() - INTERVAL 30 DAY),
  (5,  2,  CURDATE() - INTERVAL 35 DAY, CURDATE() - INTERVAL 21 DAY, CURDATE() - INTERVAL 22 DAY),
  -- still out, not yet due
  (1,  1,  CURDATE() - INTERVAL 3 DAY,  CURDATE() + INTERVAL 11 DAY, NULL),
  (2,  4,  CURDATE() - INTERVAL 5 DAY,  CURDATE() + INTERVAL 9 DAY,  NULL),
  (3,  4,  CURDATE() - INTERVAL 6 DAY,  CURDATE() + INTERVAL 8 DAY,  NULL),
  (5,  5,  CURDATE() - INTERVAL 7 DAY,  CURDATE() + INTERVAL 7 DAY,  NULL),
  (6,  8,  CURDATE() - INTERVAL 2 DAY,  CURDATE() + INTERVAL 12 DAY, NULL),
  (7,  10, CURDATE() - INTERVAL 1 DAY,  CURDATE() + INTERVAL 13 DAY, NULL),
  -- overdue
  (8,  1,  CURDATE() - INTERVAL 30 DAY, CURDATE() - INTERVAL 16 DAY, NULL),
  (10, 3,  CURDATE() - INTERVAL 21 DAY, CURDATE() - INTERVAL 7 DAY,  NULL),
  (6,  7,  CURDATE() - INTERVAL 18 DAY, CURDATE() - INTERVAL 4 DAY,  NULL);
