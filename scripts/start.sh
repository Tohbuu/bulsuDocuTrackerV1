#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8000}"

cd "$ROOT_DIR"

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php is not installed or not on PATH."
  echo "Install PHP 8+ and ensure the mysqli extension is enabled."
  exit 1
fi

if [[ ! -f ".env" ]]; then
  echo "ERROR: .env not found in $ROOT_DIR"
  echo "Create it by copying .env.example -> .env and editing DB_* values."
  exit 1
fi

echo "Starting BulSU DocuTracker on http://${HOST}:${PORT}/index.html"
echo "DB check: http://${HOST}:${PORT}/health.php"
echo "Press Ctrl+C to stop."
php -S "${HOST}:${PORT}"