<?php

/*
Version:     1.0
Date:        17/09/26
Name:        ImageCacheCleaner.php
Purpose:     Validates cached card-image paths against current card records.
Notes:       Stale-file deletion is opt-in and occurs only after validation completes.
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Cards;

use MTG\Core\Message;
use MTG\Core\Validation;

class ImageCacheCleaner
{
    private const DEFAULT_BATCH_SIZE = 100;
    private const MAX_BATCH_SIZE = 1000;
    private const EXCLUDED_DIRECTORY = 'deck_photos';
    private const CACHE_EXTENSIONS = ['jpg', 'webp'];

    /** @param \mysqli|object $db */
    public function __construct(
        private readonly object $db,
        private readonly Message $message,
        private readonly string $imageBaseDir,
    ) {
    }

    /**
     * @return array{
     *     files_seen: int,
     *     image_files_checked: int,
     *     files_valid: int,
     *     files_stale: int,
     *     stale_missing_card: int,
     *     stale_wrong_set: int,
     *     stale_invalid_path: int,
     *     files_skipped: int,
     *     stale_bytes: int,
     *     files_deleted: int,
     *     bytes_deleted: int,
     *     delete_failures: int,
     *     stale_entries: list<array{
     *         relative_path: string,
     *         absolute_path: string,
     *         reason: string,
     *         bytes: int,
     *         deleted: bool,
     *         delete_failed: bool
     *     }>
     * }
     */
    public function run(
        int $batchSize = self::DEFAULT_BATCH_SIZE,
        bool $deleteStale = false,
        ?int $limit = null
    ): array {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH_SIZE) :
            throw new \InvalidArgumentException(
                'Image cleanup batch size must be between 1 and ' . self::MAX_BATCH_SIZE
            );
        endif;
        if ($limit !== null && $limit < 1) :
            throw new \InvalidArgumentException('Image cleanup limit must be a positive integer');
        endif;

        $root = realpath($this->imageBaseDir);
        if ($root === false || !is_dir($root) || !is_readable($root)) :
            throw new \RuntimeException('Image cache directory is not readable: ' . $this->imageBaseDir);
        endif;
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        $stats = $this->newStats();
        $pending = [];
        $iterator = $this->createIterator($root);

        foreach ($iterator as $file) :
            if ($limit !== null && $stats['image_files_checked'] >= $limit) :
                break;
            endif;
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->isLink()) :
                continue;
            endif;

            $stats['files_seen']++;
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, self::CACHE_EXTENSIONS, true)) :
                $stats['files_skipped']++;
                continue;
            endif;

            $stats['image_files_checked']++;
            $candidate = $this->parseCandidate($root, $file);
            if ($candidate === null) :
                $this->recordStale($stats, $root, $file->getPathname(), 'invalid_path');
                continue;
            endif;

            $pending[] = $candidate;
            if (count($pending) >= $batchSize) :
                $this->validateBatch($pending, $stats);
                $pending = [];
            endif;
        endforeach;

        if ($pending !== []) :
            $this->validateBatch($pending, $stats);
        endif;

        // No file is removed until the complete filesystem scan and every database
        // lookup has succeeded. A database failure therefore cannot cause a partial cleanup.
        if ($deleteStale) :
            $this->deleteStaleFiles($root, $stats);
        endif;

        return $stats;
    }

    /** @return \RecursiveIteratorIterator<\RecursiveCallbackFilterIterator> */
    private function createIterator(string $root): \RecursiveIteratorIterator
    {
        $directory = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator(
            $directory,
            static function (\SplFileInfo $entry) use ($root): bool {
                if ($entry->isLink()) :
                    return false;
                endif;

                return !(
                    $entry->isDir()
                    && $entry->getPath() === $root
                    && $entry->getFilename() === self::EXCLUDED_DIRECTORY
                );
            }
        );

        return new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY);
    }

    /**
     * @return array{
     *     path: string,
     *     relative_path: string,
     *     id: string,
     *     setcode: string,
     *     bytes: int
     * }|null
     */
    private function parseCandidate(string $root, \SplFileInfo $file): ?array
    {
        $path = $file->getPathname();
        $relativePath = substr($path, strlen($root) + 1);
        if ($relativePath === false) :
            return null;
        endif;

        $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
        if (count($parts) !== 2) :
            return null;
        endif;

        [$setcode, $filename] = $parts;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        if (!in_array($extension, self::CACHE_EXTENSIONS, true)) :
            return null;
        endif;
        if (preg_match('/^([0-9a-f-]{36})(?:_b)?$/', $stem, $matches) !== 1) :
            return null;
        endif;

        $cardId = $matches[1];
        if (Validation::validUUID($cardId) === false) :
            return null;
        endif;

        return [
            'path' => $path,
            'relative_path' => str_replace(DIRECTORY_SEPARATOR, '/', $relativePath),
            'id' => $cardId,
            'setcode' => $setcode,
            'bytes' => $this->fileSize($path),
        ];
    }

    /**
     * @param list<array{path: string, relative_path: string, id: string, setcode: string, bytes: int}> $batch
     * @param array<string, mixed> $stats
     */
    private function validateBatch(array $batch, array &$stats): void
    {
        $ids = [];
        foreach ($batch as $candidate) :
            $ids[$candidate['id']] = true;
        endforeach;
        $ids = array_keys($ids);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, setcode
                FROM cards_scry
                WHERE id IN ($placeholders)";
        $result = $this->db->execute_query($sql, $ids);
        if ($result === false) :
            $databaseError = (string) ($this->db->error ?? 'unknown database error');
            throw new \RuntimeException("Unable to validate cached card images: $databaseError");
        endif;

        $cards = [];
        while ($row = $result->fetch_assoc()) :
            if (!isset($row['id'], $row['setcode'])) :
                continue;
            endif;
            $cards[(string) $row['id']] = (string) $row['setcode'];
        endwhile;
        if (method_exists($result, 'free')) :
            $result->free();
        elseif (method_exists($result, 'free_result')) :
            $result->free_result();
        endif;

        foreach ($batch as $candidate) :
            if (!array_key_exists($candidate['id'], $cards)) :
                $this->recordStale(
                    $stats,
                    '',
                    $candidate['path'],
                    'missing_card',
                    $candidate['relative_path'],
                    $candidate['bytes']
                );
            elseif ($cards[$candidate['id']] !== $candidate['setcode']) :
                $this->recordStale(
                    $stats,
                    '',
                    $candidate['path'],
                    'wrong_set',
                    $candidate['relative_path'],
                    $candidate['bytes']
                );
            else :
                $stats['files_valid']++;
            endif;
        endforeach;
    }

    /** @param array<string, mixed> $stats */
    private function recordStale(
        array &$stats,
        string $root,
        string $path,
        string $reason,
        ?string $relativePath = null,
        ?int $bytes = null
    ): void {
        if ($relativePath === null) :
            $relativePath = substr($path, strlen($root) + 1);
            if ($relativePath === false) :
                $relativePath = basename($path);
            endif;
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        endif;
        $bytes ??= $this->fileSize($path);

        $stats['files_stale']++;
        $stats['stale_' . $reason]++;
        $stats['stale_bytes'] += $bytes;
        $stats['stale_entries'][] = [
            'relative_path' => $relativePath,
            'absolute_path' => $path,
            'reason' => $reason,
            'bytes' => $bytes,
            'deleted' => false,
            'delete_failed' => false,
        ];
    }

    /** @param array<string, mixed> $stats */
    private function deleteStaleFiles(string $root, array &$stats): void
    {
        $rootPrefix = $root . DIRECTORY_SEPARATOR;
        foreach ($stats['stale_entries'] as &$entry) :
            $path = $entry['absolute_path'];
            if (!file_exists($path) && !is_link($path)) :
                continue;
            endif;

            $realPath = realpath($path);
            $safe = $realPath !== false
                && str_starts_with($realPath, $rootPrefix)
                && !str_starts_with(
                    substr($realPath, strlen($rootPrefix)),
                    self::EXCLUDED_DIRECTORY . DIRECTORY_SEPARATOR
                )
                && !is_link($path);
            if (!$safe || !@unlink($path)) :
                $entry['delete_failed'] = true;
                $stats['delete_failures']++;
                $this->message->logMessage('[ERROR]', "Unable to remove stale card image $path");
                continue;
            endif;

            $entry['deleted'] = true;
            $stats['files_deleted']++;
            $stats['bytes_deleted'] += $entry['bytes'];
            $this->message->logMessage('[DEBUG]', "Removed stale card image $path");
        endforeach;
        unset($entry);
    }

    private function fileSize(string $path): int
    {
        $size = @filesize($path);
        return $size === false ? 0 : $size;
    }

    /** @return array<string, mixed> */
    private function newStats(): array
    {
        return [
            'files_seen' => 0,
            'image_files_checked' => 0,
            'files_valid' => 0,
            'files_stale' => 0,
            'stale_missing_card' => 0,
            'stale_wrong_set' => 0,
            'stale_invalid_path' => 0,
            'files_skipped' => 0,
            'stale_bytes' => 0,
            'files_deleted' => 0,
            'bytes_deleted' => 0,
            'delete_failures' => 0,
            'stale_entries' => [],
        ];
    }
}
