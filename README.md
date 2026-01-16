# BulSU Document Tracker — Setup Guide (Linux / Windows / macOS)

A lightweight **PHP + MariaDB** web app. Configuration is done via a local **`.env`** file that is auto-loaded by [`db()`](connect.php) in [`connect.php`](connect.php).  
Goal: **clone → create DB → copy `.env` → run** (no code edits).

---

## What you need (all operating systems)

### 1) PHP (8+ recommended)
- Must have the **mysqli** extension enabled.

Verify:
```sh
php -v
php -m | grep -i mysqli
```

If `mysqli` is missing:
- **Linux**: install `php-mysql` / `php-mysqli` (package name varies)
- **Windows (XAMPP/Laragon/WAMP)**: enable `extension=mysqli` in `php.ini`

### 2) MariaDB (or MySQL-compatible)
Verify MariaDB is running:
- **Linux**:
```sh
sudo systemctl status mariadb
```

---

## Project entry points / important files
- UI: [`index.html`](index.html) + [`app.js`](app.js) + [`style.css`](style.css)
- DB config loader + connector: [`connect.php`](connect.php)
- Auth/session helpers: [`auth.php`](auth.php)
- DB schema: [`schema.sql`](schema.sql)
- DB-only check (no login required): [`health.php`](health.php)

---

## Quick start (recommended flow)

### Step 1 — Clone and enter the folder
```sh
git clone <your-repo-url>
cd bulsuDocuTracker
```

### Step 2 — Create `.env` (local configuration)
Copy the example and edit values:
```sh
cp .env.example .env
```

Edit `.env`:
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bulsu_docu_tracker
DB_USER=bulsu
DB_PASS=YOUR_PASSWORD
APP_DEBUG=0
```

Notes:
- `.env` should be **local only** (do not commit).
- Set `APP_DEBUG=1` temporarily if troubleshooting.

### Step 3 — Create DB + user + import schema
Open a MariaDB client and run (replace `YOUR_PASSWORD`):

```sql
CREATE DATABASE IF NOT EXISTS bulsu_docu_tracker
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

CREATE USER IF NOT EXISTS 'bulsu'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD';
CREATE USER IF NOT EXISTS 'bulsu'@'127.0.0.1' IDENTIFIED BY 'YOUR_PASSWORD';

GRANT ALL PRIVILEGES ON bulsu_docu_tracker.* TO 'bulsu'@'localhost';
GRANT ALL PRIVILEGES ON bulsu_docu_tracker.* TO 'bulsu'@'127.0.0.1';
FLUSH PRIVILEGES;

USE bulsu_docu_tracker;
SOURCE /path/to/bulsuDocuTracker/schema.sql;

SHOW TABLES;
```

Expected tables:
- `office_accounts`
- `documents`
- `document_events`

### Step 4 — Ensure there is at least one admin account
If your DB is already seeded and has users/admins, you can skip this.

**Generate a password hash using PHP:**
```sh
php -r 'echo password_hash("AdminPass123!", PASSWORD_DEFAULT), PHP_EOL;'
```

Insert admin (paste the hash):
```sql
USE bulsu_docu_tracker;
INSERT INTO office_accounts (username, password, is_admin)
VALUES ('admin', 'PASTE_HASH_HERE', 1);
```

### Step 5 — Run the web server
From the project folder:
```sh
php -S 127.0.0.1:8000
```

Open:
- http://127.0.0.1:8000/index.html

---

## Health checks (recommended before logging in)

### DB-only check
This checks **only DB connectivity + required tables**:
- http://127.0.0.1:8000/health.php (see [`health.php`](health.php))

CLI:
```sh
curl -i http://127.0.0.1:8000/health.php
```

### Session/auth check
- http://127.0.0.1:8000/me.php (see [`me.php`](me.php))

---

## OS-specific notes

## Linux
### Install packages (examples)
- **Arch**:
  - `sudo pacman -S php php-mysql mariadb`
- **Debian/Ubuntu**:
  - `sudo apt install php php-mysql mariadb-server`

### Start MariaDB
```sh
sudo systemctl enable --now mariadb
sudo systemctl restart mariadb
```

### MariaDB “root” login on Linux
On some distros, `root` uses socket auth (no password):
```sh
sudo mariadb
```
This is normal—create the `bulsu` user as shown above.

### If DB works only via socket (rare)
Set these in `.env`:
```ini
DB_HOST=localhost
DB_SOCKET=/path/to/mysql.sock
```
(`DB_SOCKET` is supported by [`connect.php`](connect.php).)

Find the socket path:
```sh
sudo mariadb -e "SHOW VARIABLES LIKE 'socket';"
```

---

## Windows
### Option A: Laragon / XAMPP / WAMP (easiest GUI)
1. Install Laragon or XAMPP.
2. Start **Apache** + **MySQL/MariaDB**.
3. Place folder in web root:
   - XAMPP: `C:\xampp\htdocs\bulsuDocuTracker`
   - Laragon: `C:\laragon\www\bulsuDocuTracker`
4. Import [`schema.sql`](schema.sql) using phpMyAdmin/HeidiSQL.
5. Copy `.env.example` → `.env` and set DB creds.
6. Visit:
   - http://localhost/bulsuDocuTracker/index.html

### Option B: PHP built-in server (no Apache)
1. Install PHP and ensure `mysqli` is enabled.
2. Install MariaDB.
3. Create `.env`.
4. Run (PowerShell):
```powershell
cd C:\path\to\bulsuDocuTracker
php -S 127.0.0.1:8000
```

---

## macOS
- Install PHP (Homebrew) and MariaDB (Homebrew), or use a local stack.
- Same steps as “Quick start”.
- If using Homebrew MariaDB, ensure the service is running.

---

## Troubleshooting

### “Server error” when logging in
1. Set `APP_DEBUG=1` in `.env`
2. Confirm DB health:
   - http://127.0.0.1:8000/health.php
3. Confirm MariaDB is running and `.env` credentials match the DB user/password.

### Common causes
- Wrong `DB_PASS` in `.env`
- MariaDB not started
- `mysqli` not enabled
- Using the wrong `DB_HOST` (try `localhost` or `127.0.0.1`)
- Schema not imported (`health.php` will show missing tables)

---

## Security / sharing notes
- Do **not** commit `.env` (it contains secrets).
- Keep `APP_DEBUG=0` for normal use.
- This is intended for local/dev use unless you harden deployment (HTTPS, proper server config, etc.).