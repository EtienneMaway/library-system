# Library Management System

**GitHub repository:** _add the link here after the first push_

A web system that lets librarians manage books, members and loans from a browser. All data is stored in MySQL (MariaDB). Built with PHP, HTML, CSS and JavaScript by Group Sixteen for Database 2, CAT 2.

- [What you need](#what-you-need)
- [Set up on Windows (XAMPP)](#set-up-on-windows-xampp)
- [Set up on a Mac (Homebrew)](#set-up-on-a-mac-homebrew)
- [Log in](#log-in)
- [Daily use](#daily-use)
- [Problems and fixes](#problems-and-fixes)
- [Project layout](#project-layout)
- [Working as a team](#working-as-a-team)

## What you need

| | Windows | Mac |
| --- | --- | --- |
| Server stack | XAMPP | Homebrew packages: PHP, Apache, MariaDB, phpMyAdmin |
| PHP version | 8.3.36, 8.4.27, 8.5.12 or newer | Same |
| Git | [git-scm.com](https://git-scm.com) | Comes with Xcode tools, or `brew install git` |
| Editor | VS Code or any editor | Same |

Older PHP versions have two critical security flaws (CVE-2026-109618 and CVE-2026-109619). Check yours with `php -v` or in the XAMPP Control Panel.

XAMPP is what the lecturer uses and supports. Mac users install the same parts with Homebrew because there is no patched XAMPP for macOS. The project code is identical on both.

## Set up on Windows (XAMPP)

**1. Install XAMPP.** Download the newest version from [apachefriends.org](https://www.apachefriends.org/download.html) and install it to the default folder, `C:\xampp`.

**2. Start the servers.** Open the XAMPP Control Panel and click **Start** next to **Apache** and **MySQL**. Both should turn green.

**3. Get the code into htdocs.** Open Command Prompt or Git Bash:

```sh
cd C:\xampp\htdocs
git clone <repository-url> library-system
```

Without Git, download the ZIP from GitHub and extract it so the folder is `C:\xampp\htdocs\library-system`, with `index.php` directly inside it.

**4. Create the database.** Open <http://localhost/phpmyadmin>.

1. Click **New**, enter `library_db`, choose the collation `utf8mb4_general_ci`, and click **Create**.
2. Select `library_db`, open the **Import** tab, choose `database/library_db.sql` from the project, and click **Import**.

**5. Check the connection settings.** `config/database.php` uses XAMPP's defaults: user `root` and an empty password. Change nothing unless you set a MySQL password yourself.

**6. Open the app** at <http://localhost/library-system>.

## Set up on a Mac (Homebrew)

You need [Homebrew](https://brew.sh). Run every command in Terminal.

**1. Install the stack.**

```sh
brew install php httpd mariadb@10.11 phpmyadmin
```

**2. Get the code.** Clone it anywhere, for example into your Desktop:

```sh
cd ~/Desktop
git clone <repository-url> library-system
cd library-system
pwd
```

Copy the folder path that `pwd` prints. It is used in the next step.

**3. Configure Apache.** Open `/opt/homebrew/etc/httpd/httpd.conf` in your editor.

- Find the line starting with `Listen` and change it to `Listen 127.0.0.1:8080`.
- Add this block at the very end, replacing both `/path/to/library-system` lines with the path you copied:

```apache
LoadModule php_module /opt/homebrew/opt/php/lib/httpd/modules/libphp.so
<FilesMatch \.php$>
    SetHandler application/x-httpd-php
</FilesMatch>
DirectoryIndex index.php index.html

Alias /library-system /path/to/library-system
<Directory /path/to/library-system>
    Options FollowSymLinks
    AllowOverride None
    Require local
</Directory>

Alias /phpmyadmin /opt/homebrew/share/phpmyadmin
<Directory /opt/homebrew/share/phpmyadmin>
    AllowOverride All
    Require local
</Directory>
```

Check the file with `apachectl -t`. It should print `Syntax OK`.

**4. Configure phpMyAdmin.** In `/opt/homebrew/etc/phpmyadmin.config.inc.php`, set these lines:

```php
$cfg['Servers'][$i]['host'] = '127.0.0.1';
$cfg['Servers'][$i]['AllowNoPassword'] = true;
```

**5. Start the servers.**

```sh
brew services start mariadb@10.11
brew services start httpd
```

**6. Match XAMPP's login and load the data.** These commands give `root` an empty password, as on XAMPP, then create the database with its sample data:

```sh
/opt/homebrew/opt/mariadb@10.11/bin/mariadb -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('');"
/opt/homebrew/opt/mariadb@10.11/bin/mariadb -uroot < database/library_db.sql
```

**7. Open the app** at <http://localhost:8080/library-system>. phpMyAdmin is at <http://localhost:8080/phpmyadmin>.

Mac addresses use port 8080 because port 80 needs admin rights. Links inside the app work on either port.

## Log in

These are fake test accounts. Do not reuse the passwords anywhere.

| Username | Password | Role |
| --- | --- | --- |
| `admin` | `admin123` | admin |
| `librarian` | `library123` | librarian |

After logging in, the dashboard should show 10 titles, 8 active members and 3 overdue loans.

## Daily use

There is no build step and no separate backend server. Apache runs the PHP files and sends the pages to the browser. Edit a file, save it, and refresh the page.

| Task | Windows | Mac |
| --- | --- | --- |
| Start | XAMPP Control Panel: **Start** Apache and MySQL | `brew services start mariadb@10.11` then `brew services start httpd` |
| Stop | XAMPP Control Panel: **Stop** both | `brew services stop httpd` then `brew services stop mariadb@10.11` |
| Check status | Green labels in the Control Panel | `brew services list` |
| Error log | `C:\xampp\apache\logs\error.log` | `/opt/homebrew/var/log/httpd/error_log` |

On a Mac the services start again on their own after a restart. On Windows, open the Control Panel each time.

### Reset the sample data

Re-import `database/library_db.sql`. It deletes every table in `library_db` and rebuilds it, so anything you added is lost. Loan dates are counted from the import day, so the overdue loans stay overdue.

- **Windows:** phpMyAdmin, select `library_db`, **Import**, choose the file.
- **Mac:** `/opt/homebrew/opt/mariadb@10.11/bin/mariadb -uroot < database/library_db.sql`

## Problems and fixes

| What you see | Cause | Fix |
| --- | --- | --- |
| Apache will not start on Windows | Another program (often Skype or IIS) is using port 80 or 443 | Close that program, or in XAMPP click **Config** next to Apache, open `httpd.conf`, change `Listen 80` to `Listen 8080`, and use <http://localhost:8080/library-system> |
| MySQL will not start on Windows | Another MySQL is using port 3306 | Stop the other MySQL service in Windows Services, then start it again in XAMPP |
| MariaDB will not start on Mac | Another MySQL is using port 3306 | See [Mac with another MySQL](#mac-with-another-mysql) |
| "Database connection failed" | MySQL is not running, the database was not imported, or root has a password | Start MySQL, import `library_db.sql`, and check the password in `config/database.php` |
| "Not Found" | The folder is in the wrong place or has a different name | It must be called `library-system`, with `index.php` directly inside it |
| The PHP code shows as text | PHP is not loaded in Apache | Mac: check the `LoadModule php_module` line from step 3, then `brew services restart httpd` |
| "This form has expired" | The page sat open too long or you logged out in another tab | Reload the page and submit again |
| Login fails with the right password | The database was imported before the latest changes | Re-import `library_db.sql` |

### Mac with another MySQL

If Oracle MySQL is already installed, it holds port 3306 and `/tmp/mysql.sock`. Run MariaDB beside it on port 3307:

1. Create `/opt/homebrew/etc/my.cnf.d/library.cnf` containing:

   ```ini
   [client-server]
   port = 3307
   socket = /tmp/mariadb.sock

   [mysqld]
   bind-address = 127.0.0.1
   ```

2. Run `php --ini` and open the file listed as "Loaded Configuration File". Set `pdo_mysql.default_socket = /tmp/mariadb.sock`. If no file is listed, create `/opt/homebrew/etc/php/8.5/php.ini` with that line.
3. Add `$cfg['Servers'][$i]['port'] = '3307';` to the phpMyAdmin config from step 4.
4. Run `brew services restart mariadb@10.11` and `brew services restart httpd`.

`config/database.php` stays the same. Use the full `/opt/homebrew/opt/mariadb@10.11/bin/mariadb` path in commands, because plain `mysql` connects to Oracle MySQL instead.

## Project layout

The structure follows section 5 of the project brief exactly.

```
library-system/
├── index.php            sends you to the login page or the dashboard
├── README.md
├── config/database.php  the one database connection
├── database/            library_db.sql, queries.sql, erd.png
├── includes/            header, navbar, footer, auth_check, functions
├── auth/                login and logout
├── dashboard/           summary after login
├── books/               list, create, edit, delete
├── members/             list, create, edit, delete
├── loans/               list, issue, return
├── authors/             list and add
├── categories/          list and add
├── assets/              css, js, images
└── docs/                report.pdf, screenshots, team page
```

## Working as a team

Group members and who builds what: [docs/team.html](docs/team.html).

**Build a page.** `authors/index.php` is the working example. Copy its pattern:

1. `require_once` `includes/auth_check.php` on the first line.
2. Call `require_csrf()` when handling a POST, and print `csrf_field()` inside every POST form.
3. Validate every field on the server with the helpers in `includes/functions.php`.
4. Run every query with `$pdo->prepare()` and `execute()`. Never put user input into an SQL string.
5. Use `flash()` for the success or error message, then `redirect()`.
6. Print anything from the database or a form with `e()`.

Pages showing a "Not built yet" box are waiting for their owner. Delete the `page_todo()` call when you build the page.

**Commit your work.** The brief marks regular commits from every member, so commit from your own GitHub account. Only the repository owner can push to `main` or merge, so everyone else works on a fork and sends a pull request:

1. On GitHub, click **Fork** on this repository.
2. Clone your fork and connect it to the main repository:

   ```sh
   git clone https://github.com/<your-username>/library-system.git
   cd library-system
   git remote add upstream <main-repository-url>
   ```

3. Work on a branch named after your area, then push it to your fork:

   ```sh
   git checkout -b books
   git add books/
   git commit -m "Add the books list with search"
   git push -u origin books
   ```

4. On GitHub, open a pull request from your branch to `main` on the main repository, and fill in the template.

Before each work session, get everyone else's changes with `git checkout main`, `git pull upstream main` and `git push origin main`.

Your commits keep your name when the pull request is merged, so they count as yours.

### Checks on every pull request

GitHub runs these automatically. A pull request cannot be merged until all of them pass.

| Check | What it catches |
| --- | --- |
| Folder structure | Missing required files, files loose in the root, folders not in the brief, capital letters or spaces in names, a second database connection |
| Security rules | SQL built from variables, user input printed without `e()`, pages without the login check, POST forms without `csrf_field()`, `phpinfo()`, debug output, database errors shown to users |
| PHP lint and analysis | Syntax errors on PHP 8.3 and 8.5, PSR-12 code style, bugs found by PHPStan |
| JavaScript and CSS lint | Unused or undefined variables, invalid CSS |
| Database import and app smoke test | `library_db.sql` must import twice on MariaDB 10.11 with the minimum sample data, `queries.sql` must run, and every page must load after logging in with no PHP errors |

Open the failed check on the pull request page to see which file and line to fix. Push the fix to the same branch and the checks run again.

### Run the checks on your computer

Run them before every push, from the project folder, in Git Bash on Windows or Terminal on a Mac:

```sh
bash .github/scripts/check-all.sh           # code checks, takes about a minute
bash .github/scripts/check-all.sh --full    # also the database and app test, before a pull request
bash .github/scripts/check-all.sh --fix     # fixes PHP code style automatically
```

`--full` resets `library_db` to the sample data, and needs Apache and MySQL running.

It needs PHP (XAMPP's is found automatically) and an internet connection the first time, to download the PHP checkers. The JavaScript and CSS checks need [Node.js](https://nodejs.org); without it they are skipped, but GitHub still runs them.

**Make it automatic.** Run this once in your clone, and the code checks run every time you `git push`. A failing check stops the push.

```sh
git config core.hooksPath .github/hooks
```

**Before submitting,** one member follows only this README on a fresh Windows XAMPP install, and the group fixes whatever fails.
