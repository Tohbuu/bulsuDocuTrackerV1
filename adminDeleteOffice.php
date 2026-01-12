<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_post();
require_admin();

$targetUsername = trim($_POST['username'] ?? '');
if ($targetUsername === '') {
    json_response(400, ["status" => "error", "message" => "username is required."]);
}

$me = require_login();
if ($targetUsername === $me) {
    json_response(400, ["status" => "error", "message" => "You cannot delete your own account."]);
}

try {
    $conn = db();

    // Check target exists + role
    $q = $conn->prepare("SELECT is_admin FROM office_accounts WHERE username = ? LIMIT 1");
    $q->bind_param("s", $targetUsername);
    $q->execute();
    $q->store_result();
    if ($q->num_rows !== 1) {
        json_response(404, ["status" => "error", "message" => "Office account not found."]);
    }
    $q->bind_result($targetIsAdmin);
    $q->fetch();
    $q->close();

    // Block deleting last admin
    if (((int)$targetIsAdmin) === 1) {
        $cnt = $conn->prepare("SELECT COUNT(*) FROM office_accounts WHERE is_admin = 1");
        $cnt->execute();
        $cnt->bind_result($adminCount);
        $cnt->fetch();
        $cnt->close();

        if ((int)$adminCount <= 1) {
            json_response(409, ["status" => "error", "message" => "Cannot delete the last admin."]);
        }
    }

    // Block deletion if documents exist
    $docs = $conn->prepare("
        SELECT COUNT(*) 
        FROM documents 
        WHERE source_username = ? OR receiver_username = ?
    ");
    $docs->bind_param("ss", $targetUsername, $targetUsername);
    $docs->execute();
    $docs->bind_result($docCount);
    $docs->fetch();
    $docs->close();

    if ((int)$docCount > 0) {
        json_response(409, [
            "status" => "error",
            "message" => "Cannot delete office: it is referenced by existing documents."
        ]);
    }

    $del = $conn->prepare("DELETE FROM office_accounts WHERE username = ? LIMIT 1");
    $del->bind_param("s", $targetUsername);
    $del->execute();

    if ($del->affected_rows !== 1) {
        json_response(500, ["status" => "error", "message" => "Failed to delete office."]);
    }

    json_response(200, ["status" => "success", "message" => "Office deleted."]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>