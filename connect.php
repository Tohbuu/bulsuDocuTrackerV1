<?php
// filepath: /home/ayeyou/Downloads/bulsuDocuTracker/connect.php
declare(strict_types=1);

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

function load_dotenv(string $path): void {
    if (!is_file($path) || !is_readable($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;

        $pos = strpos($line, '=');
        if ($pos === false) continue;

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        if ($key === '') continue;

        // strip surrounding quotes
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }

        // don't override real env vars
        if (getenv($key) !== false) continue;

        putenv($key . '=' . $val);
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}

// Load .env next to this file (project root)
load_dotenv(__DIR__ . '/.env');

function db(): mysqli {
    if (!class_exists('mysqli')) {
        error_log('mysqli extension is not enabled. Install/enable php-mysql (mysqli).');
        throw new RuntimeException('mysqli extension is not enabled. Install/enable php-mysql (mysqli).');
    }

    $db     = getenv('DB_NAME') ?: 'bulsu_docu_tracker';
    $user   = getenv('DB_USER') ?: 'bulsu';
    $pass   = getenv('DB_PASS') ?: '';
    $port   = (int)(getenv('DB_PORT') ?: 3306);
    $socket = getenv('DB_SOCKET') ?: '';

    $host = getenv('DB_HOST') ?: ($socket !== '' ? 'localhost' : '127.0.0.1');

    $conn = new mysqli($host, $user, $pass, $db, $port, $socket !== '' ? $socket : null);
    if ($conn->connect_error) {
        $msg = "DB connect failed host={$host} db={$db} user={$user} errno={$conn->connect_errno} err={$conn->connect_error}";
        error_log($msg);
        if (getenv('APP_DEBUG') === '1') throw new RuntimeException($msg);
        throw new RuntimeException('Database connection failed.');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}
?>