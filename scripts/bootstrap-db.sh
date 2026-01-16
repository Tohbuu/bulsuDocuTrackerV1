#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
SCHEMA_FILE="${ROOT_DIR}/schema.sql"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: .env not found in ${ROOT_DIR}"
  echo "Create it: cp .env.example .env"
  exit 1
fi

if [[ ! -f "$SCHEMA_FILE" ]]; then
  echo "ERROR: schema.sql not found in ${ROOT_DIR}"
  exit 1
fi

# Minimal .env loader (supports KEY=VALUE, ignores comments, strips surrounding quotes)
load_env() {
  while IFS= read -r line || [[ -n "$line" ]]; do
    line="$(echo "$line" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
    [[ -z "$line" ]] && continue
    [[ "$line" == \#* ]] && continue
    [[ "$line" != *"="* ]] && continue
    local key="${line%%=*}"
    local val="${line#*=}"
    key="$(echo "$key" | sed -e 's/[[:space:]]//g')"
    val="$(echo "$val" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
    # strip surrounding quotes
    if [[ "$val" =~ ^\".*\"$ ]] || [[ "$val" =~ ^\'.*\'$ ]]; then
      val="${val:1:${#val}-2}"
    fi
    export "$key=$val"
  done < "$ENV_FILE"
}

sql_escape() {
  # escape single quotes for SQL string literals
  local s="${1:-}"
  s="${s//\'/\'\'}"
  printf "%s" "$s"
}

load_env

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-bulsu_docu_tracker}"
DB_USER="${DB_USER:-bulsu}"
DB_PASS="${DB_PASS:-}"

DB_ROOT_USER="${DB_ROOT_USER:-root}"
DB_ROOT_PASS="${DB_ROOT_PASS:-}"   # optional; if empty we try sudo/socket or prompt

DB_SEED_ADMIN="${DB_SEED_ADMIN:-0}" # set to 1 to seed admin
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"

client=""
if command -v mariadb >/dev/null 2>&1; then client="mariadb"; fi
if [[ -z "$client" ]] && command -v mysql >/dev/null 2>&1; then client="mysql"; fi
if [[ -z "$client" ]]; then
  echo "ERROR: mariadb/mysql client not found in PATH."
  exit 1
fi

run_root() {
  # Usage: run_root "<sql>"  (executes SQL with root privileges)
  local sql="$1"

  if [[ -n "$DB_ROOT_PASS" ]]; then
    MYSQL_PWD="$DB_ROOT_PASS" "$client" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_ROOT_USER" -e "$sql"
    return 0
  fi

  # Try socket auth via sudo on Linux (common)
  if command -v sudo >/dev/null 2>&1; then
    if sudo -n true >/dev/null 2>&1; then
      sudo "$client" -e "$sql" && return 0
    fi
  fi

  # Prompt for root password (still one command run)
  read -r -s -p "Enter MariaDB root password (leave empty to attempt socket auth): " pw
  echo
  if [[ -z "$pw" ]]; then
    if command -v sudo >/dev/null 2>&1; then
      sudo "$client" -e "$sql"
      return 0
    fi
    echo "ERROR: No root password provided and sudo/socket auth not available."
    exit 1
  fi

  MYSQL_PWD="$pw" "$client" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_ROOT_USER" -e "$sql"
}

echo "== DB bootstrap =="
echo "DB_NAME=$DB_NAME"
echo "DB_USER=$DB_USER"
echo "DB_HOST=$DB_HOST"
echo "DB_PORT=$DB_PORT"
echo

create_sql=$(
  cat <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';

GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';

FLUSH PRIVILEGES;
SQL
)

run_root "$create_sql"

echo "Importing schema.sql into ${DB_NAME}..."
if [[ -n "$DB_ROOT_PASS" ]]; then
  MYSQL_PWD="$DB_ROOT_PASS" "$client" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_ROOT_USER" "$DB_NAME" < "$SCHEMA_FILE"
else
  if command -v sudo >/dev/null 2>&1; then
    sudo "$client" "$DB_NAME" < "$SCHEMA_FILE"
  else
    read -r -s -p "Enter MariaDB root password for schema import: " pw2
    echo
    MYSQL_PWD="$pw2" "$client" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_ROOT_USER" "$DB_NAME" < "$SCHEMA_FILE"
  fi
fi

if [[ "$DB_SEED_ADMIN" == "1" ]]; then
  if ! command -v php >/dev/null 2>&1; then
    echo "WARNING: php not found; cannot seed admin password hash. Skipping admin seed."
  elif [[ -z "$ADMIN_PASSWORD" ]]; then
    echo "WARNING: DB_SEED_ADMIN=1 but ADMIN_PASSWORD is empty. Skipping admin seed."
  else
    echo "Seeding admin user (if not exists): ${ADMIN_USERNAME}"
    hash="$(php -r 'echo password_hash(getenv("ADMIN_PASSWORD"), PASSWORD_DEFAULT);' ADMIN_PASSWORD="$ADMIN_PASSWORD")"
    u_esc="$(sql_escape "$ADMIN_USERNAME")"
    h_esc="$(sql_escape "$hash")"
    seed_sql=$(
      cat <<SQL
USE \`${DB_NAME}\`;
INSERT INTO office_accounts (username, password, is_admin)
SELECT '${u_esc}', '${h_esc}', 1
WHERE NOT EXISTS (SELECT 1 FROM office_accounts WHERE username = '${u_esc}' LIMIT 1);
SQL
    )
    run_root "$seed_sql"
  fi
fi

echo "Done."
echo "DB check: http://127.0.0.1:8000/health.php"