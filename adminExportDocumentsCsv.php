<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(405, ["status" => "error", "message" => "Invalid request method."]);
}

require_admin();

// override auth.php JSON header
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="documents_export.csv"');

function bind_params(mysqli_stmt $stmt, string $types, array $params): void {
    $refs = [];
    foreach ($params as $k => $v) $refs[$k] = &$params[$k];
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function csv_row(array $cols): string {
    $out = [];
    foreach ($cols as $v) {
        $s = (string)($v ?? '');
        $s = str_replace('"', '""', $s);
        $out[] = '"' . $s . '"';
    }
    return implode(',', $out) . "\r\n";
}

function parse_ymd(?string $s): ?string {
    $s = trim((string)$s);
    if ($s === '') return null;
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $s);
    if (!$dt) return null;
    return $dt->format('Y-m-d');
}

$q = trim((string)($_GET['q'] ?? ''));
$username = trim((string)($_GET['username'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$fromDate = parse_ymd($_GET['fromDate'] ?? null);
$toDate = parse_ymd($_GET['toDate'] ?? null);

try {
    $sql = "
        SELECT unique_file_key, document_name, referring_to, document_type,
               source_username, receiver_username, created_at, delivered_at, status
        FROM documents
        WHERE 1=1
    ";

    $types = "";
    $params = [];

    if ($q !== '') {
        $sql .= " AND unique_file_key LIKE ? ";
        $types .= "s";
        $params[] = "%" . $q . "%";
    }

    if ($username !== '') {
        $sql .= " AND (source_username = ? OR receiver_username = ?) ";
        $types .= "ss";
        $params[] = $username;
        $params[] = $username;
    }

    if ($status !== '') {
        $sql .= " AND status = ? ";
        $types .= "s";
        $params[] = $status;
    }

    if ($fromDate) {
        $sql .= " AND created_at >= ? ";
        $types .= "s";
        $params[] = $fromDate . " 00:00:00";
    }
    if ($toDate) {
        $toExclusive = (new DateTimeImmutable($toDate))->modify('+1 day')->format('Y-m-d') . " 00:00:00";
        $sql .= " AND created_at < ? ";
        $types .= "s";
        $params[] = $toExclusive;
    }

    $sql .= " ORDER BY created_at DESC LIMIT 5000 ";

    $conn = db();
    $stmt = $conn->prepare($sql);
    if ($types !== "") bind_params($stmt, $types, $params);

    $stmt->execute();
    $res = $stmt->get_result();

    echo csv_row(["fileId","documentName","referringTo","documentType","sourceOffice","receiverOffice","createdAt","deliveredAt","status"]);

    while ($row = $res->fetch_assoc()) {
        echo csv_row([
            $row["unique_file_key"],
            $row["document_name"],
            $row["referring_to"],
            $row["document_type"],
            $row["source_username"],
            $row["receiver_username"],
            $row["created_at"],
            $row["delivered_at"],
            $row["status"],
        ]);
    }
} catch (Throwable $e) {
    // CSV response: fail as plain text
    http_response_code(500);
    echo "Server error.\n";
}
?>