$ErrorActionPreference = "Stop"

$RootDir = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path

$HostAddress = if ($env:HOST) { $env:HOST } else { "127.0.0.1" }
$Port = if ($env:PORT) { $env:PORT } else { "8000" }

Set-Location $RootDir

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
  Write-Host "ERROR: php is not installed or not on PATH."
  Write-Host "Install PHP 8+ and ensure the mysqli extension is enabled."
  exit 1
}

if (-not (Test-Path (Join-Path $RootDir ".env"))) {
  Write-Host "ERROR: .env not found in $RootDir"
  Write-Host "Create it by copying .env.example -> .env and editing DB_* values."
  exit 1
}

Write-Host "Starting BulSU DocuTracker on http://$HostAddress`:$Port/index.html"
Write-Host "DB check: http://$HostAddress`:$Port/health.php"
Write-Host "Press Ctrl+C to stop."
php -S "$HostAddress`:$Port"