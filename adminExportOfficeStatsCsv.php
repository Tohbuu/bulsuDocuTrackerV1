<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(405, ["status" => "error", "message" => "Invalid request method."]);
}

require_admin();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="office_stats_export.csv"');

function csv_row(array $cols): string {
    $out = [];
    foreach ($cols as $v) {
        $s = (string)($v ?? '');
        $s = str_replace('"', '""', $s);
        $out[] = '"' . $s . '"';
    }
    return implode(',', $out) . "\r\n";
}

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

    echo csv_row(["office","sentTotal","sentDelivered","sentInTransit","recvTotal","recvDelivered","recvInTransit"]);

    while ($row = $res->fetch_assoc()) {
        echo csv_row([
            $row["username"],
            $row["sent_total"],
            $row["sent_delivered"],
            $row["sent_in_transit"],
            $row["recv_total"],
            $row["recv_delivered"],
            $row["recv_in_transit"],
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Server error.\n";
}
?>