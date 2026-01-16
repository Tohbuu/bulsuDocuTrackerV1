$ErrorActionPreference = "Stop"

$RootDir = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$EnvFile = Join-Path $RootDir ".env"
$SchemaFile = Join-Path $RootDir "schema.sql"

if (-not (Test-Path $EnvFile)) {
  Write-Host "ERROR: .env not found in $RootDir"
  Write-Host "Create it: copy .env.example .env"
  exit 1
}
if (-not (Test-Path $SchemaFile)) {
  Write-Host "ERROR: schema.sql not found in $RootDir"
  exit 1
}

function Load-DotEnv([string]$Path) {
  Get-Content $Path | ForEach-Object {
    $line = $_.Trim()
    if ($line.Length -eq 0) { return }
    if ($line.StartsWith("#")) { return }
    if ($line -notmatch "=") { return }

    $parts = $line.Split("=", 2)
    $key = $parts[0].Trim()
    $val = $parts[1].Trim()

    if (($val.StartsWith('"') -and $val.EndsWith('"')) -or ($val.StartsWith("'") -and $val.EndsWith("'"))) {
      $val = $val.Substring(1, $val.Length - 2)
    }

    if (-not [string]::IsNullOrWhiteSpace($key) -and -not $env:$key) {
      [Environment]::SetEnvironmentVariable($key, $val, "Process")
    }
  }
}

Load-DotEnv $EnvFile

$DB_HOST = if ($env:DB_HOST) { $env:DB_HOST } else { "127.0.0.1" }
$DB_PORT = if ($env:DB_PORT) { $env:DB_PORT } else { "3306" }
$DB_NAME = if ($env:DB_NAME) { $env:DB_NAME } else { "bulsu_docu_tracker" }
$DB_USER = if ($env:DB_USER) { $env:DB_USER } else { "bulsu" }
$DB_PASS = if ($env:DB_PASS) { $env:DB_PASS } else { "" }

$DB_ROOT_USER = if ($env:DB_ROOT_USER) { $env:DB_ROOT_USER } else { "root" }
$DB_ROOT_PASS = if ($env:DB_ROOT_PASS) { $env:DB_ROOT_PASS } else { "" }

$DB_SEED_ADMIN = if ($env:DB_SEED_ADMIN) { $env:DB_SEED_ADMIN } else { "0" }
$ADMIN_USERNAME = if ($env:ADMIN_USERNAME) { $env:ADMIN_USERNAME } else { "admin" }
$ADMIN_PASSWORD = if ($env:ADMIN_PASSWORD) { $env:ADMIN_PASSWORD } else { "" }

$client = (Get-Command mariadb -ErrorAction SilentlyContinue)
if (-not $client) { $client = (Get-Command mysql -ErrorAction SilentlyContinue) }
if (-not $client) {
  Write-Host "ERROR: mariadb/mysql client not found in PATH."
  exit 1
}

if ([string]::IsNullOrWhiteSpace($DB_ROOT_PASS)) {
  $sec = Read-Host "Enter MariaDB root password (leave empty if none)" -AsSecureString
  $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec)
  $DB_ROOT_PASS = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
  [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
}

$env:MYSQL_PWD = $DB_ROOT_PASS

$sql = @"
CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';

GRANT ALL PRIVILEGES ON `$DB_NAME`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON `$DB_NAME`.* TO '$DB_USER'@'127.0.0.1';

FLUSH PRIVILEGES;
"@

Write-Host "== DB bootstrap =="
Write-Host "DB_NAME=$DB_NAME"
Write-Host "DB_USER=$DB_USER"
Write-Host "DB_HOST=$DB_HOST"
Write-Host "DB_PORT=$DB_PORT"
Write-Host

& $client.Source -h $DB_HOST -P $DB_PORT -u $DB_ROOT_USER -e $sql

Write-Host "Importing schema.sql into $DB_NAME..."
(Get-Content $SchemaFile -Raw) | & $client.Source -h $DB_HOST -P $DB_PORT -u $DB_ROOT_USER $DB_NAME

if ($DB_SEED_ADMIN -eq "1") {
  $php = Get-Command php -ErrorAction SilentlyContinue
  if (-not $php) {
    Write-Host "WARNING: php not found; cannot seed admin. Skipping."
  } elseif ([string]::IsNullOrWhiteSpace($ADMIN_PASSWORD)) {
    Write-Host "WARNING: DB_SEED_ADMIN=1 but ADMIN_PASSWORD is empty. Skipping."
  } else {
    $env:ADMIN_PASSWORD = $ADMIN_PASSWORD
    $hash = & $php.Source -r 'echo password_hash(getenv("ADMIN_PASSWORD"), PASSWORD_DEFAULT);'
    $seedSql = @"
USE `$DB_NAME`;
INSERT INTO office_accounts (username, password, is_admin)
SELECT '$ADMIN_USERNAME', '$hash', 1
WHERE NOT EXISTS (SELECT 1 FROM office_accounts WHERE username = '$ADMIN_USERNAME' LIMIT 1);
"@
    & $client.Source -h $DB_HOST -P $DB_PORT -u $DB_ROOT_USER -e $seedSql
    Write-Host "Seeded admin (if not existed): $ADMIN_USERNAME"
  }
}

Write-Host "Done."
Write-Host "DB check: http://127.0.0.1:8000/health.php"