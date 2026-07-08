<?php

/*
Version:     1.0
Date:        08/07/26
Name:        ScryfallSyncStateUpdater.php
Purpose:     Updates local Scryfall data sync state records.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use MTG\Core\Message;

class ScryfallSyncStateUpdater
{
    private mixed $stmt = null;
    private ?string $lookupId = null;

    private function __construct(private mixed $db)
    {
        $this->stmt = $this->db->prepare(
            "INSERT INTO
                `scryfall_sync_state`
                    (id, manifest_data_updated_at, data_checked_at)
                SELECT
                    lookup.id,
                    manifest.data_updated_at,
                    NOW()
                FROM
                    (SELECT ? AS id) AS lookup
                LEFT JOIN
                    `scryfall_manifest` AS manifest
                    ON manifest.id = lookup.id
                ON DUPLICATE KEY UPDATE
                    manifest_data_updated_at = VALUES(manifest_data_updated_at),
                    data_checked_at = VALUES(data_checked_at)"
        );
        if ($this->stmt === false) :
            throw new \Exception('[ERROR] scryfall_bulk.php: Preparing sync state SQL: ' . $this->db->error);
        endif;

        $syncBind = $this->stmt->bind_param('s', $this->lookupId);
        if ($syncBind === false) :
            throw new \Exception('[ERROR] scryfall_bulk.php: Binding sync state SQL: ' . $this->db->error);
        endif;
    }

    public static function prepareForCardsTable(string $tableName, mixed $db): ?self
    {
        if ($tableName !== 'cards_scry') :
            return null;
        endif;

        return new self($db);
    }

    public function update(string $id, string $context): void
    {
        $this->lookupId = $id;
        if (!$this->stmt->execute()) :
            throw new \Exception(
                "[ERROR] scryfall_bulk.php: Updating sync state for $context: " . $this->db->error
            );
        endif;
    }

    public function close(): void
    {
        if ($this->stmt !== null) :
            $this->stmt->close();
            $this->stmt = null;
        endif;
    }

    /**
    * @param \mysqli|object $db
    */
    public static function backfillData($db, Message $msg): int
    {
        $msg->logMessage('[NOTICE]', 'Scryfall sync state: starting data backfill');

        $sql = "INSERT INTO
            `scryfall_sync_state`
                (id, manifest_data_updated_at, data_checked_at)
            SELECT
                cards_scry.id,
                scryfall_manifest.data_updated_at,
                NOW()
            FROM
                `cards_scry`
            LEFT JOIN
                `scryfall_manifest`
                ON scryfall_manifest.id = cards_scry.id
            ON DUPLICATE KEY UPDATE
                manifest_data_updated_at = VALUES(manifest_data_updated_at),
                data_checked_at = VALUES(data_checked_at)";

        $result = $db->query($sql);
        if ($result === false) :
            throw new \Exception('[ERROR] scryfall_sync_state: data backfill failed: ' . $db->error);
        endif;

        $affected = isset($db->affected_rows) ? (int) $db->affected_rows : 0;
        $msg->logMessage('[NOTICE]', "Scryfall sync state: data backfill completed; affected rows: $affected");
        return $affected;
    }
}
