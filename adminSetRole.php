<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_post();
require_admin();

$targetUsername = trim($_POST['username'] ?? '');
$isAdminRaw = $_POST['isAdmin'] ?? null;

if ($targetUsername === '' || $isAdminRaw === null) {
    json_response(400, ["status" => "error", "message" => "username and isAdmin are required."]);
}

$targetIsAdmin = ((int)$isAdminRaw) === 1 ? 1 : 0;

$me = require_login();
if ($targetUsername === $me) {
    json_response(400, ["status" => "error", "message" => "You cannot change your own admin role."]);
}

try {
    $conn = db();

    $q = $conn->prepare("SELECT is_admin FROM office_accounts WHERE username = ? LIMIT 1");
    $q->bind_param("s", $targetUsername);
    $q->execute();
    $q->store_result();
    if ($q->num_rows !== 1) {
        json_response(404, ["status" => "error", "message" => "Office account not found."]);
    }
    $q->bind_result($currentIsAdmin);
    $q->fetch();
    $q->close();

    if (((int)$currentIsAdmin) === 1 && $targetIsAdmin === 0) {
        $cnt = $conn->prepare("SELECT COUNT(*) FROM office_accounts WHERE is_admin = 1");
        $cnt->execute();
        $cnt->bind_result($adminCount);
        $cnt->fetch();
        $cnt->close();

        if ((int)$adminCount <= 1) {
            json_response(409, ["status" => "error", "message" => "Cannot demote the last admin."]);
        }
    }

    $u = $conn->prepare("UPDATE office_accounts SET is_admin = ? WHERE username = ? LIMIT 1");
    $u->bind_param("is", $targetIsAdmin, $targetUsername);
    $u->execute();

    if ($u->errno) {
        json_response(500, ["status" => "error", "message" => "Failed to update role."]);
    }

    json_response(200, [
        "status" => "success",
        "message" => "Role updated.",
        "data" => ["username" => $targetUsername, "isAdmin" => (bool)$targetIsAdmin]
    ]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>