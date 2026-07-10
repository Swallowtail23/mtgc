<?php

/*
Version:     1.0
Date:        10/07/26
Name:        ScryfallBulkSourceTracker.php
Purpose:     Track successful Scryfall bulk-source imports.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

class ScryfallBulkSourceTracker
{
    /** @param \mysqli|object $db */
    public function __construct(private mixed $db)
    {
    }

    public function isCurrent(string $sourceType, string $downloadUri, string $localPath): bool
    {
        $snapshot = $this->snapshot($localPath);
        $result = $this->db->execute_query(
            "SELECT 1 FROM `scryfall_bulk_sources`
            WHERE source_type = ? AND download_uri = ? AND local_path = ?
                AND content_hash = ? AND status = 'completed'",
            [$sourceType, $downloadUri, $localPath, $snapshot['hash']]
        );
        if ($result === false) :
            throw new \Exception('[ERROR] scryfall_bulk_sources: Checking source state: ' . $this->db->error);
        endif;
        $current = $result->num_rows > 0;
        $result->free();
        return $current;
    }

    public function markStarted(string $sourceType, string $downloadUri, string $localPath): void
    {
        $this->write($sourceType, $downloadUri, $localPath, 'running', false);
    }

    public function markCompleted(string $sourceType, string $downloadUri, string $localPath): void
    {
        $this->write($sourceType, $downloadUri, $localPath, 'completed', true);
    }

    public function markFailed(string $sourceType, string $downloadUri, string $localPath): void
    {
        $this->write($sourceType, $downloadUri, $localPath, 'failed', false);
    }

    private function write(
        string $sourceType,
        string $downloadUri,
        string $localPath,
        string $status,
        bool $completed
    ): void {
        $snapshot = $this->snapshot($localPath);
        $result = $this->db->execute_query(
            "INSERT INTO `scryfall_bulk_sources`
                (source_type, download_uri, local_path, file_size, file_mtime, content_hash, status,
                last_import_started_at, last_import_completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), IF(?, NOW(), NULL))
            ON DUPLICATE KEY UPDATE
                download_uri = VALUES(download_uri), local_path = VALUES(local_path), file_size = VALUES(file_size),
                file_mtime = VALUES(file_mtime), content_hash = VALUES(content_hash), status = VALUES(status),
                last_import_started_at = NOW(),
                last_import_completed_at = IF(VALUES(status) = 'completed', NOW(), last_import_completed_at)",
            [
                $sourceType,
                $downloadUri,
                $localPath,
                $snapshot['size'],
                $snapshot['mtime'],
                $snapshot['hash'],
                $status,
                $completed,
            ]
        );
        if ($result === false) :
            throw new \Exception('[ERROR] scryfall_bulk_sources: Updating source state: ' . $this->db->error);
        endif;
    }

    /** @return array{size: int, mtime: int, hash: string} */
    private function snapshot(string $localPath): array
    {
        $size = filesize($localPath);
        $mtime = filemtime($localPath);
        $hash = hash_file('sha256', $localPath);
        if ($size === false || $mtime === false || $hash === false) :
            throw new \Exception("[ERROR] scryfall_bulk_sources: Reading local source '$localPath'");
        endif;
        return ['size' => $size, 'mtime' => $mtime, 'hash' => $hash];
    }
}
