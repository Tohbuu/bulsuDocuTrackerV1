<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(405, ["status" => "error", "message" => "Invalid request method."]);
}

require_admin();

try {
    $conn = db();
    $stmt = $conn->prepare("
        SELECT
          oa.username,

          COALESCE(s.sent_total, 0) AS sent_total,
          COALESCE(s.sent_delivered, 0) AS sent_delivered,
          COALESCE(s.sent_in_transit, 0) AS sent_in_transit,

          COALESCE(r.recv_total, 0) AS recv_total,
          COALESCE(r.recv_delivered, 0) AS recv_delivered,
          COALESCE(r.recv_in_transit, 0) AS recv_in_transit

        FROM office_accounts oa

        LEFT JOIN (
          SELECT
            source_username AS username,
            COUNT(*) AS sent_total,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS sent_delivered,
            SUM(CASE WHEN status = 'in_transit' THEN 1 ELSE 0 END) AS sent_in_transit
          FROM documents
          GROUP BY source_username
        ) s ON s.username = oa.username

        LEFT JOIN (
          SELECT
            receiver_username AS username,
            COUNT(*) AS recv_total,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS recv_delivered,
            SUM(CASE WHEN status = 'in_transit' THEN 1 ELSE 0 END) AS recv_in_transit
          FROM documents
          GROUP BY receiver_username
        ) r ON r.username = oa.username

        ORDER BY oa.username ASC
        LIMIT 500
    ");
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            "username" => (string)$row["username"],
            "sentTotal" => (int)$row["sent_total"],
            "sentDelivered" => (int)$row["sent_delivered"],
            "sentInTransit" => (int)$row["sent_in_transit"],
            "recvTotal" => (int)$row["recv_total"],
            "recvDelivered" => (int)$row["recv_delivered"],
            "recvInTransit" => (int)$row["recv_in_transit"],
        ];
    }

    json_response(200, ["status" => "success", "data" => ["rows" => $rows]]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>