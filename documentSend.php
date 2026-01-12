<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_post();
$sourceUsername = require_login();

$docName         = trim($_POST["docName"] ?? '');
$referringTo     = trim($_POST["referringTo"] ?? '');
$docType         = trim($_POST["docType"] ?? '');
$receiverUsername = trim($_POST["receiverUsername"] ?? '');
$docTag          = trim($_POST["docTag"] ?? '');

if ($docName === '' || $docType === '' || $receiverUsername === '') {
    json_response(400, ["status" => "error", "message" => "docName, docType, and receiverUsername are required."]);
}

function make_tag(int $len = 10): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'BULSU-' . $out;
}

try {
    $conn = db();

    // receiver must exist
    $chk = $conn->prepare("SELECT 1 FROM office_accounts WHERE username = ? LIMIT 1");
    $chk->bind_param("s", $receiverUsername);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows !== 1) {
        json_response(404, ["status" => "error", "message" => "Receiver office account not found."]);
    }
    $chk->close();

    // ensure unique tag
    $fileId = $docTag !== '' ? $docTag : make_tag();
    for ($i = 0; $i < 5; $i++) {
        $exists = $conn->prepare("SELECT 1 FROM documents WHERE unique_file_key = ? LIMIT 1");
        $exists->bind_param("s", $fileId);
        $exists->execute();
        $exists->store_result();
        $isTaken = $exists->num_rows > 0;
        $exists->close();

        if (!$isTaken) break;
        $fileId = make_tag();
    }

    $stmt = $conn->prepare("
        INSERT INTO documents (unique_file_key, document_name, referring_to, document_type, source_username, receiver_username)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssss", $fileId, $docName, $referringTo, $docType, $sourceUsername, $receiverUsername);

    if (!$stmt->execute()) {
        json_response(500, ["status" => "error", "message" => "Failed to record document."]);
    }

    json_response(200, [
        "status" => "success",
        "message" => "Document recorded successfully.",
        "data" => ["fileId" => $fileId]
    ]);
} catch (Throwable $e) {
    json_response(500, ["status" => "error", "message" => "Server error."]);
}
?>