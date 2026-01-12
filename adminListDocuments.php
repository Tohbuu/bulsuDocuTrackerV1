<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(405, ["status" => "error", "message" => "Invalid request method."]);
}

require_admin();

function bind_params(mysqli_stmt $stmt, string $types, array $params): void {
    $refs = [];
    foreach ($params as $k => $v) $refs[$k] = &$params[$k];
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
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

    // Date range: created_at in [fromDate 00:00:00, (toDate+1) 00:00:00)
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

    $sql .= " ORDER BY created_at DESC LIMIT 500 ";

    $conn = db();
    $stmt = $conn->prepare($sql);
    if ($types !== "") bind_params($stmt, $types, $params);

    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            "fileId" => (string)$row["unique_file_key"],
            "documentName" => (string)$row["document_name"],
            "referringTo" => $row["referring_to"],
            "documentType" => (string)$row["document_type"],
            "sourceOffice" => (string)$row["source_username"],
            "receiverOffice" => (string)$row["receiver_username"],
            "createdAt" => (string)$row["created_at"],
            "deliveredAt" => $row["delivered_at"],
            "status" => (string)$row["status"],
        ];
    }

    json_response(200, ["status" => "success", "data" => ["documents" => $rows]]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>