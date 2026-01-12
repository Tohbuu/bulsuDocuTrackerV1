<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/documentEvents.php';

require_post();
$actor = require_login();

$fileID = trim($_POST["fileID"] ?? '');
$toStatus = trim($_POST["status"] ?? '');
$note = trim($_POST["note"] ?? '');

if ($fileID === '' || $toStatus === '') {
    json_response(400, ["status" => "error", "message" => "fileID and status are required."]);
}

$allowed = ["in_transit", "delivered", "cancelled", "rejected", "archived"];
if (!in_array($toStatus, $allowed, true)) {
    json_response(400, ["status" => "error", "message" => "Invalid status."]);
}

try {
    $conn = db();

    $q = $conn->prepare("
        SELECT status, source_username, receiver_username
        FROM documents
        WHERE unique_file_key = ?
        LIMIT 1
    ");
    $q->bind_param("s", $fileID);
    $q->execute();
    $res = $q->get_result();
    $doc = $res->fetch_assoc();
    if (!$doc) {
        json_response(404, ["status" => "error", "message" => "Document not found."]);
    }

    $fromStatus = (string)$doc["status"];
    $source = (string)$doc["source_username"];
    $receiver = (string)$doc["receiver_username"];
    $isAdmin = current_is_admin();

    // permission + transitions
    $ok = false;

    if ($isAdmin) {
        $ok = true; // admin can set any
    } else {
        if ($actor === $source && $toStatus === "cancelled" && $fromStatus === "in_transit") $ok = true;
        if ($actor === $receiver && $toStatus === "rejected" && $fromStatus === "in_transit") $ok = true;
        if ($actor === $receiver && $toStatus === "delivered" && $fromStatus === "in_transit") $ok = true;
    }

    if (!$ok) {
        json_response(403, ["status" => "error", "message" => "Not allowed."]);
    }

    if ($fromStatus === $toStatus) {
        json_response(200, ["status" => "success", "message" => "No change."]);
    }

    if ($toStatus === "delivered") {
        $u = $conn->prepare("
            UPDATE documents
            SET status = ?, delivered_at = COALESCE(delivered_at, NOW())
            WHERE unique_file_key = ?
            LIMIT 1
        ");
        $u->bind_param("ss", $toStatus, $fileID);
    } else {
        $u = $conn->prepare("
            UPDATE documents
            SET status = ?
            WHERE unique_file_key = ?
            LIMIT 1
        ");
        $u->bind_param("ss", $toStatus, $fileID);
    }
    $u->execute();

    add_document_event($conn, $fileID, "status_changed", $actor, ($note !== '' ? $note : null), $fromStatus, $toStatus);

    json_response(200, ["status" => "success", "message" => "Status updated."]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>