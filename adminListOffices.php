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
        SELECT id, username, is_admin, created_at
        FROM office_accounts
        ORDER BY created_at DESC
        LIMIT 200
    ");
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            "id" => (int)$row["id"],
            "username" => (string)$row["username"],
            "isAdmin" => ((int)$row["is_admin"]) === 1,
            "createdAt" => (string)$row["created_at"],
        ];
    }

    json_response(200, ["status" => "success", "data" => ["offices" => $rows]]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>