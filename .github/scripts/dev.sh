#!/usr/bin/env bash
# Run the project on your computer. PHP serves both the pages (frontend) and the
# logic (backend), so "starting the app" means starting Apache and MySQL.
#
#   bash .github/scripts/dev.sh doctor   check what is installed and running, and how to fix what is missing
#   bash .github/scripts/dev.sh start    start Apache and MySQL, then open the app
#   bash .github/scripts/dev.sh seed     create library_db and load the sample data (erases your local data)
#   bash .github/scripts/dev.sh stop     stop Apache and MySQL
#
# Works in Git Bash on Windows (XAMPP) and Terminal on a Mac (Homebrew).
set -u
cd "$(dirname "$0")/../.." || exit 1
ROOT=$(pwd)

if [ -t 1 ]; then G=$'\e[32m'; R=$'\e[31m'; Y=$'\e[33m'; B=$'\e[1m'; N=$'\e[0m'; else G=; R=; Y=; B=; N=; fi
ok()   { printf '%s OK %s %s\n' "$G" "$N" "$1"; }
miss() { printf '%sMISS%s %s\n      fix: %s\n' "$R" "$N" "$1" "$2"; problems=$((problems + 1)); }
note() { printf '%sNOTE%s %s\n' "$Y" "$N" "$1"; }
problems=0

case "$(uname -s)" in MINGW*|MSYS*|CYGWIN*) OS=windows ;; Darwin) OS=mac ;; *) OS=linux ;; esac
XAMPP=/c/xampp
BREW_DB=/opt/homebrew/opt/mariadb@10.11/bin

# Find the tools: PATH first, then the usual install folders.
PHP=$(command -v php || true)
[ -z "$PHP" ] && [ -x "$XAMPP/php/php.exe" ] && PHP="$XAMPP/php/php.exe"
if [ -n "${MYSQL:-}" ]; then :
elif [ -x "$XAMPP/mysql/bin/mysql.exe" ]; then MYSQL="$XAMPP/mysql/bin/mysql.exe -uroot"
elif [ -x "$BREW_DB/mariadb" ]; then MYSQL="$BREW_DB/mariadb -uroot"
elif command -v mysql >/dev/null; then MYSQL="mysql -uroot"
else MYSQL=; fi

find_url() {
  for u in http://localhost/library-system http://localhost:8080/library-system; do
    code=$(curl -s -o /dev/null -w '%{http_code}' "$u/auth/login.php" 2>/dev/null)
    [ "$code" = 200 ] && { echo "$u"; return 0; }
  done
  return 1
}
db_up() { [ -n "$MYSQL" ] && $MYSQL -e 'SELECT 1' >/dev/null 2>&1; }

doctor() {
  printf '%sChecking your setup (%s)%s\n' "$B" "$OS" "$N"

  if [ -z "$PHP" ]; then
    if [ "$OS" = windows ]; then miss "PHP not found" "install XAMPP into C:\\xampp from apachefriends.org"
    else miss "PHP not found" "brew install php"; fi
  else
    v=$("$PHP" -r 'echo PHP_VERSION;')
    if "$PHP" -r 'exit(version_compare(PHP_VERSION, "8.0", ">=") ? 0 : 1);'; then ok "PHP $v"
    else miss "PHP $v is too old" "install PHP 8.0 or newer (a current XAMPP)"; fi
    if "$PHP" -m | grep -qi '^pdo_mysql$'; then ok "PHP can talk to MySQL (pdo_mysql)"
    else miss "pdo_mysql extension is off" "in php.ini remove the ; before extension=pdo_mysql, then restart Apache"; fi
  fi

  if [ -z "$MYSQL" ]; then
    if [ "$OS" = windows ]; then miss "MySQL client not found" "XAMPP includes it at C:\\xampp\\mysql\\bin; reinstall XAMPP with MySQL ticked"
    else miss "MySQL client not found" "brew install mariadb@10.11"; fi
  elif db_up; then
    ok "MySQL server is running"
    tables=$($MYSQL -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='library_db'" 2>/dev/null)
    if [ "${tables:-0}" -ge 6 ]; then
      books=$($MYSQL -N -e "SELECT COUNT(*) FROM library_db.books" 2>/dev/null)
      ok "Database library_db has $tables tables and ${books:-0} books"
    else miss "Database library_db is missing or incomplete" "bash .github/scripts/dev.sh seed"; fi
  else
    miss "MySQL server is not running" "bash .github/scripts/dev.sh start"
  fi

  if url=$(find_url); then ok "App answers at $url"
  else
    if [ "$OS" = windows ]; then
      case "$ROOT" in /c/xampp/htdocs/library-system) ;;
        *) miss "Project is not in the web folder (it is in $ROOT)" "clone it into C:\\xampp\\htdocs\\library-system" ;; esac
    fi
    miss "Apache is not serving the app" "bash .github/scripts/dev.sh start (on a Mac, see the README for the one-time Apache setup)"
  fi

  if command -v node >/dev/null; then ok "Node.js $(node -v) (for the JavaScript and CSS checks)"
  else note "Node.js not found. Optional: only needed to run the JS and CSS checks locally."; fi
  command -v git >/dev/null && ok "Git $(git --version | awk '{print $3}')" || miss "Git not found" "install it from git-scm.com"

  echo
  if [ "$problems" -eq 0 ]; then printf '%sEverything is ready.%s\n' "$G" "$N"; return 0; fi
  printf '%s%s problem(s) to fix.%s Run the fix lines above, then run doctor again.\n' "$R" "$problems" "$N"
  return 1
}

start() {
  case "$OS" in
    mac)
      command -v brew >/dev/null || { echo "Homebrew not found. Install it from brew.sh, then follow the README."; exit 1; }
      brew services start mariadb@10.11 >/dev/null && echo "MySQL (MariaDB) started"
      brew services start httpd >/dev/null && echo "Apache started" ;;
    windows)
      [ -d "$XAMPP" ] || { echo "XAMPP not found in C:\\xampp. Install it from apachefriends.org."; exit 1; }
      db_up || { cmd //c start "" /min "C:\\xampp\\mysql_start.bat" >/dev/null 2>&1; echo "MySQL starting (a small window opens, leave it open)"; }
      find_url >/dev/null || { cmd //c start "" /min "C:\\xampp\\apache_start.bat" >/dev/null 2>&1; echo "Apache starting (a small window opens, leave it open)"; } ;;
    *) echo "Start Apache and MySQL with your system's service manager."; exit 1 ;;
  esac
  printf 'Waiting for the servers'
  for _ in $(seq 1 15); do
    if db_up && url=$(find_url); then
      printf '\n%sRunning:%s %s\n' "$G" "$N" "$url"
      echo "phpMyAdmin: ${url%/library-system}/phpmyadmin"
      echo "Log in with admin / admin123"
      if ! $MYSQL -e 'USE library_db' >/dev/null 2>&1; then echo "No database yet. Run: bash .github/scripts/dev.sh seed"; fi
      return 0
    fi
    printf '.'; sleep 1
  done
  echo; echo "The servers did not come up. Here is what is wrong:"; echo
  doctor
}

seed() {
  [ -n "$MYSQL" ] || { echo "MySQL client not found. Run: bash .github/scripts/dev.sh doctor"; exit 1; }
  db_up || { echo "MySQL is not running. Run: bash .github/scripts/dev.sh start"; exit 1; }
  if [ "${1:-}" != "--yes" ] && [ -t 0 ]; then
    printf 'This erases everything in library_db and loads the sample data. Continue? [y/N] '
    read -r answer; case "$answer" in y|Y|yes) ;; *) echo "Cancelled."; exit 0 ;; esac
  fi
  if $MYSQL < database/library_db.sql; then
    counts=$($MYSQL -N -e "SELECT CONCAT((SELECT COUNT(*) FROM library_db.books),' books, ',(SELECT COUNT(*) FROM library_db.members),' members, ',(SELECT COUNT(*) FROM library_db.loans),' loans')")
    printf '%sSeeded:%s %s\n' "$G" "$N" "$counts"
  else
    echo "Import failed. The error above names the line in database/library_db.sql."; exit 1
  fi
}

stop() {
  case "$OS" in
    mac) brew services stop httpd >/dev/null; brew services stop mariadb@10.11 >/dev/null; echo "Apache and MySQL stopped." ;;
    windows) cmd //c "C:\\xampp\\apache_stop.bat" >/dev/null 2>&1; cmd //c "C:\\xampp\\mysql_stop.bat" >/dev/null 2>&1; echo "Apache and MySQL stopped." ;;
    *) echo "Stop Apache and MySQL with your system's service manager." ;;
  esac
}

case "${1:-}" in
  doctor) doctor ;;
  start)  start ;;
  seed)   seed "${2:-}" ;;
  stop)   stop ;;
  *) sed -n '2,8p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
esac
