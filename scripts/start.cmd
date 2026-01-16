@echo off
setlocal enabledelayedexpansion

set "ROOT_DIR=%~dp0.."
for %%I in ("%ROOT_DIR%") do set "ROOT_DIR=%%~fI"

if "%HOST%"=="" set "HOST=127.0.0.1"
if "%PORT%"=="" set "PORT=8000"

cd /d "%ROOT_DIR%"

where php >nul 2>nul
if errorlevel 1 (
  echo ERROR: php is not installed or not on PATH.
  echo Install PHP 8+ and ensure the mysqli extension is enabled.
  exit /b 1
)

if not exist ".env" (
  echo ERROR: .env not found in %ROOT_DIR%
  echo Create it by copying .env.example ^> .env and editing DB_* values.
  exit /b 1
)

echo Starting BulSU DocuTracker on http://%HOST%:%PORT%/index.html
echo DB check: http://%HOST%:%PORT%/health.php
echo Press Ctrl+C to stop.
php -S %HOST%:%PORT%