<?php

/*
Version:     1.3
Date:        16/09/26
Name:        ImageWebpMigrator.php
Purpose:     Walks stored card records and migrates cached JPEGs to remote WebP images.
Notes:       Uses the ImageManager for per-card cache and remote-source handling.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Cards;

use MTG\Core\Message;

class ImageWebpMigrator
{
    private const DEFAULT_BATCH_SIZE = 100;
    private const MAX_BATCH_SIZE = 1000;

    /** @var \mysqli|object */
    private $db;
    private ImageManager $imageManager;
    private Message $message;

    /**
     * @param \mysqli|object $db
     */
    public function __construct($db, ImageManager $imageManager, Message $message)
    {
        $this->db = $db;
        $this->imageManager = $imageManager;
        $this->message = $message;
    }

    /**
     * Process cards after the supplied UUID in ascending order.
     *
     * The cursor is deliberately the card UUID rather than an offset, so an
     * interrupted run can resume without re-reading the preceding batches.
     * Re-running without a cursor is also safe because completed WebP files
     * are skipped.
     *
     * @return array{
     *     cards_seen: int,
     *     faces_converted: int,
     *     faces_already_webp: int,
     *     faces_dry_run: int,
     *     faces_missing_jpeg: int,
     *     faces_missing_webp_source: int,
     *     faces_failed: int,
     *     jpeg_deleted: int,
     *     cleanup_failed: int,
     *     jpeg_bytes_found: int,
     *     jpeg_candidate_bytes: int,
     *     jpeg_bytes_converted: int,
     *     jpeg_bytes_deleted: int,
     *     webp_bytes_existing: int,
     *     webp_bytes_downloaded: int,
     *     database_paths_updated: int,
     *     database_update_failures: int,
     *     last_id: string
     * }
     */
    public function run(
        string $after = '',
        int $batchSize = self::DEFAULT_BATCH_SIZE,
        bool $deleteJpeg = false,
        bool $dryRun = false,
        ?int $limit = null
    ): array {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH_SIZE) :
            throw new \InvalidArgumentException(
                'Image migration batch size must be between 1 and ' . self::MAX_BATCH_SIZE
            );
        endif;
        if ($limit !== null && $limit < 1) :
            throw new \InvalidArgumentException('Image migration limit must be a positive integer');
        endif;

        $stats = [
            'cards_seen' => 0,
            'faces_converted' => 0,
            'faces_already_webp' => 0,
            'faces_dry_run' => 0,
            'faces_missing_jpeg' => 0,
            'faces_missing_webp_source' => 0,
            'faces_failed' => 0,
            'jpeg_deleted' => 0,
            'cleanup_failed' => 0,
            'jpeg_bytes_found' => 0,
            'jpeg_candidate_bytes' => 0,
            'jpeg_bytes_converted' => 0,
            'jpeg_bytes_deleted' => 0,
            'webp_bytes_existing' => 0,
            'webp_bytes_downloaded' => 0,
            'database_paths_updated' => 0,
            'database_update_failures' => 0,
            'last_id' => $after,
        ];
        $cursor = $after;
        $remaining = $limit;
        $pendingUpdates = [];
        $pendingCleanup = [];

        do {
            $currentBatchSize = $remaining === null ? $batchSize : min($batchSize, $remaining);
            $result = $this->fetchCardBatch($cursor, $currentBatchSize);
            $batchCount = 0;

            while ($row = $result->fetch_assoc()) :
                $cardId = isset($row['id']) ? (string) $row['id'] : '';
                if ($cardId === '') :
                    $this->message->logMessage('[ERROR]', 'Image migration encountered a card without an id');
                    continue;
                endif;

                $batchCount++;
                $stats['cards_seen']++;
                // Keep JPEGs in place until the corresponding database update succeeds.
                $cardResult = $this->imageManager->migrateCardToWebp($cardId, false, $dryRun);
                $this->collectFaceResult($stats, $cardResult['front']);
                $this->collectFaceResult($stats, $cardResult['back']);
                $faceResults = [$cardResult['front'], $cardResult['back']];
                foreach ($faceResults as $faceResult) :
                    if ($this->collectDatabaseUpdate($pendingUpdates, $cardId, $faceResult)) :
                        $pendingCleanup[] = $faceResult;
                    endif;
                endforeach;
                $cursor = $cardId;
                $stats['last_id'] = $cardId;
            endwhile;

            if ($remaining !== null) :
                $remaining -= $batchCount;
            endif;

            if (method_exists($result, 'free')) :
                $result->free();
            elseif (method_exists($result, 'free_result')) :
                $result->free_result();
            endif;

            if (!$dryRun && $pendingUpdates !== []) :
                try {
                    $stats['database_paths_updated'] += $this->updateImagePaths($pendingUpdates);
                } catch (\Throwable $exception) {
                    $stats['database_update_failures'] += $this->countPendingUpdates($pendingUpdates);
                    throw $exception;
                }
            endif;
            if (!$dryRun && $deleteJpeg) :
                foreach ($pendingCleanup as $faceResult) :
                    $cleanup = $this->imageManager->cleanupMigratedJpeg($faceResult);
                    $this->collectCleanupResult($stats, $faceResult, $cleanup);
                endforeach;
            endif;
            $pendingUpdates = [];
            $pendingCleanup = [];
        } while ($batchCount === $currentBatchSize && ($remaining === null || $remaining > 0));

        return $stats;
    }

    /**
     * @param array<string, array<string, string>> $pendingUpdates
     * @param array<string, mixed> $faceResult
     */
    private function collectDatabaseUpdate(array &$pendingUpdates, string $cardId, array $faceResult): bool
    {
        if (!in_array((string) ($faceResult['status'] ?? ''), ['converted', 'already_webp'], true)) :
            return false;
        endif;

        $field = (string) ($faceResult['database_field'] ?? '');
        $source = (string) ($faceResult['source'] ?? '');
        if (!in_array($field, ['image_uri', 'f1_image_uri', 'f2_image_uri'], true) || $source === '') :
            return false;
        endif;

        $pendingUpdates[$cardId][$field] = $source;
        return true;
    }

    /**
     * Update all successful image URI changes from one image-processing batch.
     *
     * @param array<string, array<string, string>> $pendingUpdates
     */
    private function updateImagePaths(array $pendingUpdates): int
    {
        $fields = ['image_uri', 'f1_image_uri', 'f2_image_uri'];
        $sets = [];
        $params = [];
        $ids = [];
        $seenIds = [];

        foreach ($fields as $field) :
            $cases = [];
            foreach ($pendingUpdates as $cardId => $updates) :
                if (!isset($updates[$field])) :
                    continue;
                endif;
                $cases[] = 'WHEN ? THEN ?';
                $params[] = $cardId;
                $params[] = $updates[$field];
                if (!isset($seenIds[$cardId])) :
                    $seenIds[$cardId] = true;
                    $ids[] = $cardId;
                endif;
            endforeach;
            if ($cases !== []) :
                $sets[] = "`$field` = CASE id " . implode(' ', $cases) . " ELSE `$field` END";
            endif;
        endforeach;

        if ($sets === [] || $ids === []) :
            return 0;
        endif;

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $sql = "UPDATE `cards_scry`
                SET " . implode(",\n                    ", $sets) . "
                WHERE id IN ($placeholders)";
        $params = array_merge($params, $ids);
        $result = $this->db->execute_query($sql, $params);
        if ($result === false) :
            $databaseError = (string) ($this->db->error ?? 'unknown database error');
            throw new \RuntimeException("Unable to update card WebP image URIs: $databaseError");
        endif;

        $updated = 0;
        foreach ($pendingUpdates as $updates) :
            $updated += count($updates);
        endforeach;
        $this->message->logMessage('[DEBUG]', "Updated $updated card WebP image URI fields in one batch");
        return $updated;
    }

    /** @param array<string, array<string, string>> $pendingUpdates */
    private function countPendingUpdates(array $pendingUpdates): int
    {
        $count = 0;
        foreach ($pendingUpdates as $updates) :
            $count += count($updates);
        endforeach;
        return $count;
    }

    /**
     * @param array<string, int|string> $stats
     * @param array<string, mixed> $faceResult
     * @param array{deleted: bool, failed: bool} $cleanup
     */
    private function collectCleanupResult(array &$stats, array $faceResult, array $cleanup): void
    {
        if ($cleanup['deleted']) :
            $stats['jpeg_deleted']++;
            $stats['jpeg_bytes_deleted'] += (int) ($faceResult['jpeg_bytes'] ?? 0);
        endif;
        if ($cleanup['failed']) :
            $stats['cleanup_failed']++;
        endif;
    }

    /** @return object */
    private function fetchCardBatch(string $after, int $batchSize): object
    {
        $sql = "SELECT id
                FROM cards_scry
                WHERE id > ?
                ORDER BY id
                LIMIT $batchSize";
        $result = $this->db->execute_query($sql, [$after]);
        if ($result === false) :
            $databaseError = (string) ($this->db->error ?? 'unknown database error');
            throw new \RuntimeException("Unable to read cards for WebP migration: $databaseError");
        endif;

        return $result;
    }

    /**
     * @param array<string, int|string> $stats
     * @param array<string, mixed> $faceResult
     */
    private function collectFaceResult(array &$stats, array $faceResult): void
    {
        $status = (string) ($faceResult['status'] ?? 'failed');
        if ($status === 'converted') :
            $stats['faces_converted']++;
            $stats['jpeg_bytes_converted'] += (int) ($faceResult['jpeg_bytes'] ?? 0);
            $stats['webp_bytes_downloaded'] += (int) ($faceResult['webp_bytes'] ?? 0);
        elseif ($status === 'already_webp') :
            $stats['faces_already_webp']++;
            $stats['webp_bytes_existing'] += (int) ($faceResult['webp_bytes'] ?? 0);
        elseif ($status === 'dry_run') :
            $stats['faces_dry_run']++;
        elseif ($status === 'missing_jpeg') :
            $stats['faces_missing_jpeg']++;
        elseif ($status === 'missing_webp_source') :
            $stats['faces_missing_webp_source']++;
        elseif ($status !== 'not_required') :
            $stats['faces_failed']++;
        endif;

        $jpegBytes = (int) ($faceResult['jpeg_bytes'] ?? 0);
        $stats['jpeg_bytes_found'] += $jpegBytes;
        if (in_array($status, ['converted', 'dry_run', 'download_failed'], true)) :
            $stats['jpeg_candidate_bytes'] += $jpegBytes;
        endif;
    }
}
