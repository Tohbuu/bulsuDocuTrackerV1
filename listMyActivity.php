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
        SELECT
          e.unique_file_key,
          d.document_name,
          e.event_type,
          e.actor_username,
          e.note,
          e.from_status,
          e.to_status,
          e.created_at
        FROM document_events e
        JOIN documents d ON d.unique_file_key = e.unique_file_key
        WHERE d.source_username = ? OR d.receiver_username = ? OR e.actor_username = ?
        ORDER BY e.created_at DESC, e.id DESC
        LIMIT 120
    ");
    $stmt->bind_param("sss", $me, $me, $me);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            "fileId" => (string)$r["unique_file_key"],
            "documentName" => (string)$r["document_name"],
            "type" => (string)$r["event_type"],
            "actor" => (string)$r["actor_username"],
            "note" => $r["note"],
            "fromStatus" => $r["from_status"],
            "toStatus" => $r["to_status"],
            "createdAt" => (string)$r["created_at"],
        ];
    }

    json_response(200, ["status" => "success", "data" => ["events" => $rows]]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>