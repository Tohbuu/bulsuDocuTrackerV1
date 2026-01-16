<?php
declare(strict_types=1);

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

function db(): mysqli {
    if (!class_exists('mysqli')) {
        error_log('mysqli extension is not enabled. Install/enable php-mysql (mysqli).');
        throw new RuntimeException('mysqli extension is not enabled. Install/enable php-mysql (mysqli).');
    }

    // Local defaults (override via env vars)
    $db     = getenv('DB_NAME') ?: 'bulsu_docu_tracker';
    $user   = getenv('DB_USER') ?: 'bulsu';
    $pass   = getenv('DB_PASS') ?: 'test1234'; // <-- set your local password here
    $port   = (int)(getenv('DB_PORT') ?: 3306);
    $socket = getenv('DB_SOCKET') ?: '';

    $host = getenv('DB_HOST') ?: ($socket !== '' ? 'localhost' : '127.0.0.1');

    $conn = new mysqli($host, $user, $pass, $db, $port, $socket !== '' ? $socket : null);

    if ($conn->connect_error) {
        $msg = "DB connect failed host={$host} db={$db} user={$user} errno={$conn->connect_errno} err={$conn->connect_error}";
        error_log($msg);
        if (getenv('APP_DEBUG') === '1') {
            throw new RuntimeException($msg);
        }
        throw new RuntimeException('Database connection failed.');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}
?>