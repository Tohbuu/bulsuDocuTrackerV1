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

    $check = $conn->prepare("SELECT 1 FROM office_accounts WHERE username = ? LIMIT 1");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();
    if ($check->num_rows !== 1) {
        json_response(404, ["status" => "error", "message" => "Office account not found."]);
    }
    $check->close();

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE office_accounts SET password = ? WHERE username = ? LIMIT 1");
    $stmt->bind_param("ss", $hash, $username);
    $stmt->execute();

    if ($stmt->errno) {
        json_response(500, ["status" => "error", "message" => "Failed to update password."]);
    }

    json_response(200, ["status" => "success", "message" => "Password updated."]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>