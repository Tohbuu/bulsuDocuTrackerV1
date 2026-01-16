# BulSU Document Tracker — Setup Guide (Linux / Windows / macOS)

A lightweight **PHP + MariaDB** web app. Configuration is via a local **`.env`** file auto-loaded by [`db()`](connect.php) in [`connect.php`](connect.php).  
Goal: **clone → import DB → copy `.env` → run** (no code edits).

---

## 0) Choose how you want to run it (important)

### Option A — PHP built-in server (recommended for ALL OS)
- Easiest + most consistent across Linux/Windows/macOS
- You run:
  - `php -S 127.0.0.1:8000`
- You open:
  - `http://127.0.0.1:8000/index.html` ([`index.html`](index.html))

### Option B — Apache stack (Windows-friendly GUI)
- XAMPP / Laragon / WAMP
- You place the project in a web root (e.g., `htdocs/`), then open:
  - `http://localhost/<folder>/index.html`

Both options require a working **MariaDB** DB.

---

## 1) Requirements (all operating systems)

### PHP 8+ + mysqli
Verify:
```sh
php -v
php -m | grep -i mysqli
```

If `mysqli` is missing:
- Linux: install `php-mysql` (package name varies)
- Windows stacks: enable `extension=mysqli` in `php.ini`

### MariaDB (or MySQL-compatible)
You must have the DB server running.

---

## 2) Important project files
- UI: [`index.html`](index.html) + [`app.js`](app.js) + [`style.css`](style.css)
- DB config loader + connector: [`connect.php`](connect.php)
- DB schema: [`schema.sql`](schema.sql)
- DB-only check: [`health.php`](health.php)
- Session check: [`me.php`](me.php)

---

## 3) Setup (same core steps on every OS)

### Step 1 — Clone and enter the folder
```sh
git clone <your-repo-url>
cd bulsuDocuTracker
```

### Step 2 — Create `.env` (local configuration)
Copy the example:
```sh
cp .env.example .env
```

Edit `.env` (example):
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bulsu_docu_tracker
DB_USER=bulsu
DB_PASS=YOUR_PASSWORD
APP_DEBUG=0
```

Notes:
- `.env` is loaded by [`connect.php`](connect.php). Do not commit it.
- Set `APP_DEBUG=1` only for troubleshooting.

### Step 3 — Create DB + user + import schema
Run this SQL in your MariaDB client (replace `YOUR_PASSWORD`):

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

### Step 4 — Ensure at least one admin account exists
If your DB already contains users/admins, you can skip.

1) Generate a password hash using PHP:
```sh
php -r 'echo password_hash("AdminPass123!", PASSWORD_DEFAULT), PHP_EOL;'
```

2) Insert admin user (paste the hash):
```sql
USE bulsu_docu_tracker;
INSERT INTO office_accounts (username, password, is_admin)
VALUES ('admin', 'PASTE_HASH_HERE', 1);
```

---

## 4) Run the app

### Option A — PHP built-in server (recommended)
From the project folder:
```sh
php -S 127.0.0.1:8000
```

Open:
- http://127.0.0.1:8000/index.html

### Option B — Apache stack (XAMPP/Laragon/WAMP)
Put the project folder inside your web root:

- XAMPP: `C:\xampp\htdocs\bulsuDocuTracker`
- Laragon: `C:\laragon\www\bulsuDocuTracker`

Then open:
- `http://localhost/bulsuDocuTracker/index.html`

---

## 5) Health checks (run these before logging in)

### DB-only check (no login)
- http://127.0.0.1:8000/health.php ([`health.php`](health.php))

CLI:
```sh
curl -i http://127.0.0.1:8000/health.php
```

You want:
- HTTP 200
- `"ok": true`
- `"missing": []`

### Session/auth check
- http://127.0.0.1:8000/me.php ([`me.php`](me.php))

---

## 6) OS-specific notes (avoid common confusion)

## Linux
### Install packages (examples)
- Arch:
  - `sudo pacman -S php php-mysql mariadb`
- Debian/Ubuntu:
  - `sudo apt install php php-mysql mariadb-server`

### Start MariaDB
```sh
sudo systemctl enable --now mariadb
sudo systemctl restart mariadb
```

### Root login note
Some Linux setups use socket auth for root:
```sh
sudo mariadb
```
That is normal—create the `bulsu` DB user as shown in Step 3.

---

## Windows (more detailed)

### Recommended Windows path (least friction)
**Laragon** (or XAMPP) + phpMyAdmin/HeidiSQL.

#### 1) Install and start services
- Start **Apache** and **MySQL/MariaDB** in Laragon/XAMPP.

#### 2) Import the database schema
Using phpMyAdmin (typical):
- Create DB: `bulsu_docu_tracker`
- Import file: [`schema.sql`](schema.sql)

Or using HeidiSQL:
- Create DB → right click DB → “Load SQL file” → choose `schema.sql`

#### 3) Create `.env`
In the project folder (same level as [`connect.php`](connect.php)):
- Copy `.env.example` → `.env`
- Set credentials to match the DB user/password you created.

#### 4) Open the app
- `http://localhost/bulsuDocuTracker/index.html`

### Alternative Windows path (no Apache)
If you installed PHP separately and want the simplest run command:

PowerShell:
```powershell
cd C:\path\to\bulsuDocuTracker
php -S 127.0.0.1:8000
```

Open:
- http://127.0.0.1:8000/index.html

---

## macOS
- Install PHP + MariaDB via Homebrew or a local stack.
- Use Option A (PHP built-in server) + same DB steps.
- Ensure MariaDB is started (brew services).

---

## 7) Troubleshooting

### Login shows “Server error”
1) Set `APP_DEBUG=1` in `.env`
2) Visit DB check:
   - http://127.0.0.1:8000/health.php ([`health.php`](health.php))
3) Most common causes:
   - Wrong `DB_PASS` / `DB_USER` / `DB_NAME` in `.env`
   - MariaDB service not running
   - `mysqli` not enabled
   - Wrong host: try `DB_HOST=127.0.0.1` vs `DB_HOST=localhost`

### Health check says missing tables
- You didn’t import [`schema.sql`](schema.sql) into the correct DB.

---

## 8) Notes / limitations
- QR generation uses an external service URL from [`app.js`](app.js) (needs internet to render QR images).
- Intended for local/dev use unless you harden deployment.

## Convenience start scripts (no memorizing commands)

These start the PHP built-in server (Option A). They require:
- `.env` present (copy from `.env.example`)
- MariaDB running + schema imported

### Linux / macOS
```sh
./scripts/start.sh
```

### Windows (PowerShell)
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start.ps1
```

### Windows (CMD)
```bat
scripts\start.cmd
```

You can override host/port:
- Linux/macOS: `HOST=127.0.0.1 PORT=8000 ./scripts/start.sh`
- Windows (PowerShell): `$env:HOST="127.0.0.1"; $env:PORT="8000"; .\scripts\start.ps1`
- Windows (CMD): `set HOST=127.0.0.1 && set PORT=8000 && scripts\start.cmd`

## One-command DB bootstrap (creates DB/user + imports schema)

These scripts read `.env` and will:
- create `DB_NAME`
- create `DB_USER` with `DB_PASS`
- grant privileges
- import [`schema.sql`](schema.sql)

### Linux / macOS
```sh
./scripts/bootstrap-db.sh
```

### Windows (PowerShell)
```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\bootstrap-db.ps1
```

### Windows (CMD)
```bat
scripts\bootstrap-db.cmd
```

### Optional: seed an initial admin account
Add to `.env`:
```ini
DB_SEED_ADMIN=1
ADMIN_USERNAME=admin
ADMIN_PASSWORD=AdminPass123!
```

### Root access note
- Windows often requires a root password. Set in `.env` to avoid prompts:
```ini
DB_ROOT_USER=root
DB_ROOT_PASS=your_root_password
```
- On many Linux installs, `sudo mariadb` works via socket auth (no root password needed).