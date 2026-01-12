<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(405, ["status" => "error", "message" => "Invalid request method."]);
}

$me = require_login();

try {
    $conn = db();
    $stmt = $conn->prepare("
        SELECT username, is_admin
        FROM office_accounts
        ORDER BY username ASC
        LIMIT 500
    ");
    $stmt->execute();
    $res = $stmt->get_result();

    $offices = [];
    while ($row = $res->fetch_assoc()) {
        $username = (string)$row["username"];
        $offices[] = [
            "username" => $username,
            "isAdmin" => ((int)$row["is_admin"]) === 1,
            "isMe" => $username === $me
        ];
    }

    json_response(200, ["status" => "success", "data" => ["offices" => $offices]]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>