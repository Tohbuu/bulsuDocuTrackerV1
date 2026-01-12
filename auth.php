<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/connect.php';

function json_response(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, ["status" => "error", "message" => "Invalid request method."]);
    }
}

function current_office_username(): ?string {
    $u = $_SESSION['office_username'] ?? null;
    return is_string($u) && $u !== '' ? $u : null;
}

function require_login(): string {
    $u = current_office_username();
    if (!$u) {
        json_response(401, ["status" => "error", "message" => "Not logged in."]);
    }
    return $u;
}

function current_is_admin(): bool {
    return ($_SESSION['office_is_admin'] ?? 0) === 1;
}

function require_admin(): void {
    require_login();
    if (!current_is_admin()) {
        json_response(403, ["status" => "error", "message" => "Admin access required."]);
    }
}
?>