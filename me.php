<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$u = current_office_username();
if (!$u) {
    json_response(200, ["status" => "success", "data" => ["loggedIn" => false]]);
}

json_response(200, [
    "status" => "success",
    "data" => [
        "loggedIn" => true,
        "username" => $u,
        "isAdmin" => current_is_admin()
    ]
]);
?>