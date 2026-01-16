<?php
// filepath: /home/ayeyou/Downloads/bulsuDocuTracker/health.php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(405, ["status" => "error", "message" => "Invalid request method."]);
}

try {
    $conn = db();

    $meta = $conn->query("SELECT DATABASE() AS db, USER() AS user")->fetch_assoc() ?: [];
    $ver  = $conn->query("SELECT VERSION() AS version")->fetch_assoc() ?: [];

    $required = ['office_accounts', 'documents', 'document_events'];
    $placeholders = implode(',', array_fill(0, count($required), '?'));

    $stmt = $conn->prepare("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name IN ($placeholders)
    ");

    $types = str_repeat('s', count($required));
    $stmt->bind_param($types, ...$required);
    $stmt->execute();
    $res = $stmt->get_result();

    $found = [];
    while ($r = $res->fetch_assoc()) {
        $found[] = (string)$r['table_name'];
    }

    $missing = array_values(array_diff($required, $found));

    json_response(200, [
        "status" => "success",
        "data" => [
            "db" => $meta["db"] ?? null,
            "user" => $meta["user"] ?? null,
            "version" => $ver["version"] ?? null,
            "tables" => [
                "required" => $required,
                "found" => $found,
                "missing" => $missing,
            ],
            "ok" => count($missing) === 0,
        ],
    ]);
} catch (Throwable $e) {
    json_response(500, [
        "status" => "error",
        "message" => (getenv('APP_DEBUG') === '1') ? $e->getMessage() : "Server error.",
    ]);
}
?>