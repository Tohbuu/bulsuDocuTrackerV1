<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_post();
$office = require_login();

$fileID = trim($_POST["fileID"] ?? '');
if ($fileID === '') {
    json_response(400, ["status" => "error", "message" => "fileID is required."]);
}

try {
    $conn = db();
    $stmt = $conn->prepare("
        SELECT unique_file_key, document_name, referring_to, document_type,
               source_username, receiver_username, created_at, delivered_at
        FROM documents
        WHERE unique_file_key = ?
          AND (source_username = ? OR receiver_username = ?)
        LIMIT 1
    ");
    $stmt->bind_param("sss", $fileID, $office, $office);
    $stmt->execute();
    $res = $stmt->get_result();

    $row = $res->fetch_assoc();
    if (!$row) {
        json_response(404, ["status" => "error", "message" => "Document not found."]);
    }

    $status = ($row["delivered_at"] ?? null) ? "delivered" : "in_transit";

    json_response(200, [
        "status" => "success",
        "message" => "Document found.",
        "data" => [
            "fileId" => $row["unique_file_key"],
            "documentName" => $row["document_name"],
            "referringTo" => $row["referring_to"],
            "documentType" => $row["document_type"],
            "sourceOffice" => $row["source_username"],
            "receiverOffice" => $row["receiver_username"],
            "createdAt" => $row["created_at"],
            "deliveredAt" => $row["delivered_at"],
            "status" => $status
        ]
    ]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>
