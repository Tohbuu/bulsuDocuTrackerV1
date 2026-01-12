<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_post();
require_admin();

$newUsername = trim($_POST['username'] ?? '');
$newPassword = trim($_POST['password'] ?? '');
$isAdmin = (int)($_POST['isAdmin'] ?? 0);

if ($newUsername === '' || $newPassword === '') {
    json_response(400, ["status" => "error", "message" => "username and password are required."]);
}

if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $newUsername)) {
    json_response(400, ["status" => "error", "message" => "Username must be 3-64 chars (letters, numbers, . _ -)."]);
}

if (strlen($newPassword) < 6) {
    json_response(400, ["status" => "error", "message" => "Password must be at least 6 characters."]);
}

try {
    $conn = db();

    $check = $conn->prepare("SELECT 1 FROM office_accounts WHERE username = ? LIMIT 1");
    $check->bind_param("s", $newUsername);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        json_response(409, ["status" => "error", "message" => "Username already exists."]);
    }
    $check->close();

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO office_accounts (username, password, is_admin) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $newUsername, $hash, $isAdmin);

    if (!$stmt->execute()) {
        json_response(500, ["status" => "error", "message" => "Failed to create account."]);
    }

    json_response(200, [
        "status" => "success",
        "message" => "Office account created.",
        "data" => ["username" => $newUsername, "isAdmin" => (bool)$isAdmin]
    ]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>