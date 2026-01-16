# BulSU Document Tracker (Local Setup)

This project is a simple PHP + MariaDB web app. Configuration is done via a local **`.env`** file that is automatically loaded by [`db()`](connect.php) in [`connect.php`](connect.php).

---

## 1) Requirements (any OS)

### Software
- **PHP 8+** with **mysqli**
- **MariaDB** (or MySQL-compatible)

### Quick checks
```sh
php -v
php -m | grep -i mysqli
```

If `mysqli` is missing, install/enable the PHP MySQL extension.

---

## 2) Project files you should know
- Config loader + DB connector: [`connect.php`](connect.php) (see [`db()`](connect.php))
- Auth/session helpers: [`auth.php`](auth.php)
- DB-only health endpoint: [`health.php`](health.php)
- Main UI: [`index.html`](index.html) + [`app.js`](app.js) + [`style.css`](style.css)
- DB schema: [`schema.sql`](schema.sql)

---

## 3) Configure `.env` (no code edits)

### Step A — create `.env`
Copy `.env.example` to `.env` in the project root:

```sh
cp .env.example .env
```

Edit `.env` to match your DB:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bulsu_docu_tracker
DB_USER=bulsu
DB_PASS=YOUR_PASSWORD
APP_DEBUG=0
```

Notes:
- `APP_DEBUG=1` makes API errors show the real message (useful during setup).
- `.env` is local machine config. Do not commit it.

---

## 4) Database setup (Linux/macOS/Windows)

### Step A — create database and user
Run these SQL commands in MariaDB (replace `YOUR_PASSWORD`):

```sql
CREATE DATABASE IF NOT EXISTS bulsu_docu_tracker
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

CREATE USER IF NOT EXISTS 'bulsu'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD';
CREATE USER IF NOT EXISTS 'bulsu'@'127.0.0.1' IDENTIFIED BY 'YOUR_PASSWORD';

GRANT ALL PRIVILEGES ON bulsu_docu_tracker.* TO 'bulsu'@'localhost';
GRANT ALL PRIVILEGES ON bulsu_docu_tracker.* TO 'bulsu'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### Step B — import schema
Import [`schema.sql`](schema.sql):

```sql
USE bulsu_docu_tracker;
SOURCE /path/to/bulsuDocuTracker/schema.sql;
```

(Windows: use your SQL tool’s “Run SQL file” to execute `schema.sql`.)

### Step C — confirm tables exist
```sql
SHOW TABLES;
```

Expected:
- `office_accounts`
- `documents`
- `document_events`

---

## 5) Create your first admin account

If you already have users in `office_accounts`, you can skip this.

### Step A — generate a password hash (PHP)
Run this in a terminal (choose your password):

```sh
php -r 'echo password_hash("AdminPass123!", PASSWORD_DEFAULT), PHP_EOL;'
```

Copy the printed hash.

### Step B — insert admin user (SQL)
```sql
USE bulsu_docu_tracker;

INSERT INTO office_accounts (username, password, is_admin)
VALUES ('admin', 'PASTE_HASH_HERE', 1);
```

---

## 6) Run the app

### Option 1 (recommended): PHP built-in server
From the project folder:

```sh
php -S 127.0.0.1:8000
```

Open:
- http://127.0.0.1:8000/index.html

### DB-only test (recommended)
This checks only DB connectivity + required tables:
- http://127.0.0.1:8000/health.php (see [`health.php`](health.php))

CLI test:
```sh
curl -i http://127.0.0.1:8000/health.php
```

---

## 7) OS-specific instructions

## Linux (example)
### Install packages (examples; depends on distro)
- Arch: `php`, `php-mysql`, `mariadb`
- Ubuntu/Debian: `php`, `php-mysql`, `mariadb-server`

### Start MariaDB
```sh
sudo systemctl enable --now mariadb
sudo systemctl status mariadb
```

### Open MariaDB shell
On some systems, root uses socket auth:
```sh
sudo mariadb
```

---

## Windows setup

### Option A: Laragon / XAMPP / WAMP (easiest GUI)
1. Install Laragon or XAMPP.
2. Start **Apache** + **MySQL/MariaDB**.
3. Put the project folder into the web root:
   - XAMPP: `C:\xampp\htdocs\bulsuDocuTracker`
   - Laragon: `C:\laragon\www\bulsuDocuTracker`
4. Import [`schema.sql`](schema.sql) in phpMyAdmin / HeidiSQL.
5. Copy `.env.example` → `.env` and set DB credentials.
6. Open:
   - http://localhost/bulsuDocuTracker/index.html

### Option B: PHP built-in server (no Apache needed)
1. Install PHP (and ensure `mysqli` is enabled).
2. Install MariaDB.
3. Configure `.env`.
4. Run (PowerShell):
```powershell
cd C:\path\to\bulsuDocuTracker
php -S 127.0.0.1:8000
```
Open:
- http://127.0.0.1:8000/index.html

---

## 8) Troubleshooting

### “Server error” on login
- Turn on debug:
  - set `APP_DEBUG=1` in `.env`
- Check DB-only endpoint:
  - http://127.0.0.1:8000/health.php

Common causes:
- Wrong `.env` values (host/user/pass/db)
- MariaDB not running
- `mysqli` not enabled

### DB connects only via socket (rare; Linux)
If TCP fails, set:
```ini
DB_HOST=localhost
DB_SOCKET=/path/to/mysql.sock
```
[`db()`](connect.php) in [`connect.php`](connect.php) supports `DB_SOCKET`.

---

## 9) Security notes (local/dev)
- Do not expose this server to the public internet as-is.
- Keep `APP_DEBUG=0` for normal use.
- Do not commit `.env`.