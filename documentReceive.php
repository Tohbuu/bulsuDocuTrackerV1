<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/documentEvents.php';

require_post();
$receiverUsername = require_login();

$fileID = trim($_POST["fileID"] ?? '');
if ($fileID === '') {
    json_response(400, ["status" => "error", "message" => "fileID is required."]);
}

try {
    $conn = db();

    $cur = $conn->prepare("
        SELECT status
        FROM documents
        WHERE unique_file_key = ? AND receiver_username = ?
        LIMIT 1
    ");
    $cur->bind_param("ss", $fileID, $receiverUsername);
    $cur->execute();
    $res = $cur->get_result();
    $row = $res->fetch_assoc();
    if (!$row) {
        json_response(404, ["status" => "error", "message" => "Document not found."]);
    }
    $fromStatus = (string)$row["status"];

    if ($fromStatus !== "in_transit") {
        json_response(409, ["status" => "error", "message" => "Document cannot be received in its current state."]);
    }

    $toStatus = "delivered";
    $stmt = $conn->prepare("
        UPDATE documents
        SET delivered_at = NOW(),
            status = ?
        WHERE unique_file_key = ?
          AND receiver_username = ?
          AND status = 'in_transit'
          AND delivered_at IS NULL
    ");
    $stmt->bind_param("sss", $toStatus, $fileID, $receiverUsername);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        json_response(404, ["status" => "error", "message" => "Document not found (or already received)."]);
    }

    add_document_event($conn, $fileID, "delivered", $receiverUsername, null, $fromStatus, $toStatus);

    json_response(200, ["status" => "success", "message" => "Document marked as received."]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>