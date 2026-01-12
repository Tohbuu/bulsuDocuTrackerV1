<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_post();

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    json_response(400, ["status" => "error", "message" => "Username and password are required."]);
}

try {
    $conn = db();
    $stmt = $conn->prepare("SELECT password, is_admin FROM office_accounts WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows !== 1) {
        json_response(401, ["status" => "error", "message" => "Invalid credentials."]);
    }

    $stmt->bind_result($hashed, $isAdmin);
    $stmt->fetch();

    if (!password_verify($password, $hashed)) {
        json_response(401, ["status" => "error", "message" => "Invalid credentials."]);
    }

    $_SESSION['office_username'] = $username;
    $_SESSION['office_is_admin'] = (int)$isAdmin;

    json_response(200, [
        "status" => "success",
        "message" => "Logged in.",
        "data" => ["username" => $username, "isAdmin" => (bool)$isAdmin]
    ]);
} catch (Throwable $e) {
    error_log('login.php error: ' . $e->getMessage());
    json_response(500, ["status" => "error", "message" => (getenv('APP_DEBUG') === '1') ? $e->getMessage() : "Server error."]);
}
?>