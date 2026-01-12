<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_post();
require_admin();

$username = trim($_POST['username'] ?? '');
$newPassword = trim($_POST['newPassword'] ?? '');

if ($username === '' || $newPassword === '') {
    json_response(400, ["status" => "error", "message" => "username and newPassword are required."]);
}

if (strlen($newPassword) < 6) {
    json_response(400, ["status" => "error", "message" => "Password must be at least 6 characters."]);
}

try {
    $conn = db();

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE office_accounts SET password = ? WHERE username = ? LIMIT 1");
    $stmt->bind_param("ss", $hash, $username);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        json_response(404, ["status" => "error", "message" => "Office account not found."]);
    }

    json_response(200, ["status" => "success", "message" => "Password updated."]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>