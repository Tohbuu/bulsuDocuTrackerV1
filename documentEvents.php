<?php
declare(strict_types=1);

function add_document_event(
    mysqli $conn,
    string $fileId,
    string $eventType,
    string $actorUsername,
    ?string $note = null,
    ?string $fromStatus = null,
    ?string $toStatus = null
): void {
    $stmt = $conn->prepare("
        INSERT INTO document_events (unique_file_key, event_type, actor_username, note, from_status, to_status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssss", $fileId, $eventType, $actorUsername, $note, $fromStatus, $toStatus);
    $stmt->execute();
}