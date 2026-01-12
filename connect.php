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

    $host = getenv('DB_HOST') ?: 'sql100.infinityfree.com';
    $db   = getenv('DB_NAME') ?: 'if0_39606867_bulsu_docu_tracker';
    $user = getenv('DB_USER') ?: 'if0_39606867';
    $pass = getenv('DB_PASS') ?: '';

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        error_log("DB connect failed host={$host} db={$db} user={$user} err=" . $conn->connect_error);
        throw new RuntimeException('Database connection failed.');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}
?>