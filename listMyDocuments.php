<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(405, ["status" => "error", "message" => "Invalid request method."]);
}

$me = require_login();

try {
    $conn = db();
    $stmt = $conn->prepare("
        SELECT unique_file_key, document_name, referring_to, document_type,
               source_username, receiver_username, created_at, delivered_at, status
        FROM documents
        WHERE source_username = ? OR receiver_username = ?
        ORDER BY created_at DESC
        LIMIT 300
    ");
    $stmt->bind_param("ss", $me, $me);
    $stmt->execute();
    $res = $stmt->get_result();

    $inbox = [];
    $outbox = [];

    while ($row = $res->fetch_assoc()) {
        $status = (string)($row["status"] ?? (($row["delivered_at"] ?? null) ? "delivered" : "in_transit"));

        $item = [
            "fileId" => (string)$row["unique_file_key"],
            "documentName" => (string)$row["document_name"],
            "referringTo" => $row["referring_to"],
            "documentType" => (string)$row["document_type"],
            "sourceOffice" => (string)$row["source_username"],
            "receiverOffice" => (string)$row["receiver_username"],
            "createdAt" => (string)$row["created_at"],
            "deliveredAt" => $row["delivered_at"],
            "status" => $status,
        ];

        if ((string)$row["receiver_username"] === $me) $inbox[] = $item;
        if ((string)$row["source_username"] === $me) $outbox[] = $item;
    }

    $stats = [
        "inboxTotal" => count($inbox),
        "outboxTotal" => count($outbox),
        "inboxDelivered" => count(array_filter($inbox, fn($d) => $d["status"] === "delivered")),
        "outboxDelivered" => count(array_filter($outbox, fn($d) => $d["status"] === "delivered")),
    ];

    json_response(200, ["status" => "success", "data" => ["stats" => $stats, "inbox" => $inbox, "outbox" => $outbox]]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>