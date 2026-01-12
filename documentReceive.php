<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_post();
$receiverUsername = require_login();

$fileID = trim($_POST["fileID"] ?? '');
if ($fileID === '') {
    json_response(400, ["status" => "error", "message" => "fileID is required."]);
}

try {
    $conn = db();
    $stmt = $conn->prepare("
        UPDATE documents
        SET delivered_at = NOW()
        WHERE unique_file_key = ?
          AND receiver_username = ?
          AND delivered_at IS NULL
    ");
    $stmt->bind_param("ss", $fileID, $receiverUsername);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        json_response(404, ["status" => "error", "message" => "Document not found (or already received)."]);
    }

    json_response(200, ["status" => "success", "message" => "Document marked as received."]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>