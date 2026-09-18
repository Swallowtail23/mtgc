<?php

/*
Version:     1.0
Date:        18/09/26
Name:        ScryfallCardDeletionSafety.php
Purpose:     Reusable service for checking deletion safety of Scryfall cards.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use MTG\Core\Message;
use MTG\Core\Validation;

class ScryfallCardDeletionSafety
{
    /** @var object */
    private object $db;

    private Message $logger;

    /** @var callable(mixed): (string|false) */
    private $tableNameValidator;

    private int $batchSize;

    /**
     * @param object $db              mysqli-compatible database object
     * @param Message $logger         Structured logger for DEBUG/NOTICE/ERROR output
     * @param callable(mixed): (string|false)|null $tableNameValidator Callable wrapping Validation::validTableName();
     *                                defaults to a static call; injectable for testing
     * @param int $batchSize          Number of UUIDs per batch query (minimum 1)
     */
    public function __construct(
        object $db,
        Message $logger,
        ?callable $tableNameValidator = null,
        int $batchSize = 500
    ) {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException(
                "Batch size must be at least 1, {$batchSize} given"
            );
        }

        $this->db = $db;
        $this->logger = $logger;
        $this->batchSize = $batchSize;
        $this->tableNameValidator = $tableNameValidator ?? [Validation::class, 'validTableName'];
    }

    /**
     * Validates a single UUID and delegates to checkBatch.
     *
     * @return array<string, mixed>
     */
    public function checkSingle(string $uuid): array
    {
        return $this->checkBatch([$uuid])[$uuid] ?? $this->failedResult(
            $uuid,
            ['INVALID_UUID']
        );
    }

    /**
     * Processes a list of candidate UUIDs in batches.
     *
     * @param array<int, string> $uuids
     * @return array<string, array<string, mixed>>
     */
    public function checkBatch(array $uuids): array
    {
        $results = [];

        if (empty($uuids)) {
            return $results;
        }

        $this->logger->logMessage('[NOTICE]', "Checking deletion safety for " . count($uuids) . " UUID(s)");

        // Split into valid and invalid UUIDs using Validation::validUUID()
        $validUuids = [];
        $invalidUuids = [];
        foreach ($uuids as $uuid) {
            $validated = Validation::validUUID($uuid);
            if ($validated === false) {
                $invalidUuids[] = $uuid;
            } else {
                $validUuids[] = $uuid;
            }
        }

        // Mark invalid UUIDs immediately
        foreach ($invalidUuids as $uuid) {
            $results[$uuid] = $this->failedResult($uuid, ['INVALID_UUID']);
        }

        if (empty($validUuids)) {
            return $results;
        }

        // Discover users once before entering the batch loop.
        // This avoids repeated full user queries and ensures consistency
        // across all batches.
        $users = $this->discoverUsers();
        if ($users === false) {
            foreach ($validUuids as $uuid) {
                if (isset($results[$uuid])) {
                    $results[$uuid]['check_failed'] = true;
                    $results[$uuid]['failure_reasons'][] = 'USER_QUERY_FAILED';
                } else {
                    $results[$uuid] = $this->failedResult($uuid, ['USER_QUERY_FAILED']);
                }
            }
            return $results;
        }

        // Process valid UUIDs in batches
        $batches = array_chunk($validUuids, $this->batchSize);
        foreach ($batches as $batchIndex => $batch) {
            $this->logger->logMessage(
                '[DEBUG]',
                "Processing deck reference batch " . ($batchIndex + 1) . " of " . count($batches)
                . " (" . count($batch) . " UUIDs)"
            );

            // Deck reference check
            $deckRefs = $this->checkDeckReferences($batch);
            if ($deckRefs === false) {
                // Query failed - mark all UUIDs in this batch
                foreach ($batch as $uuid) {
                    if (isset($results[$uuid])) {
                        $results[$uuid]['check_failed'] = true;
                        $results[$uuid]['failure_reasons'][] = 'DECK_QUERY_FAILED';
                    } else {
                        $results[$uuid] = $this->failedResult($uuid, ['DECK_QUERY_FAILED']);
                    }
                }
                continue;
            }

            // Accumulate deck references
            foreach ($batch as $uuid) {
                if (!isset($results[$uuid])) {
                    $results[$uuid] = $this->defaultResult($uuid);
                }
                if (isset($deckRefs[$uuid])) {
                    $results[$uuid]['deck_references'] = $deckRefs[$uuid];
                }
            }

            // Collection quantity check
            $collectionResult = $this->checkCollectionQuantities($batch, $users);
            if ($collectionResult['failure_reasons'] !== []) {
                // Structured failure from checkCollectionQuantities
                foreach ($batch as $uuid) {
                    if (isset($results[$uuid])) {
                        $results[$uuid]['check_failed'] = true;
                        $results[$uuid]['failure_reasons'] = array_merge(
                            $results[$uuid]['failure_reasons'],
                            $collectionResult['failure_reasons']
                        );
                    } else {
                        $results[$uuid] = $this->failedResult($uuid, $collectionResult['failure_reasons']);
                    }
                }
            } elseif (!empty($collectionResult['quantities'])) {
                // Accumulate collection quantities
                foreach ($batch as $uuid) {
                    if (!isset($results[$uuid])) {
                        $results[$uuid] = $this->defaultResult($uuid);
                    }
                    if (isset($collectionResult['quantities'][$uuid])) {
                        $results[$uuid]['collection_quantity'] = $collectionResult['quantities'][$uuid];
                    }
                }
            }
        }

        $this->logger->logMessage(
            '[NOTICE]',
            "Deletion safety check complete. " . count($results) . " UUID(s) evaluated."
        );

        return $results;
    }

    /**
     * Check deck references for a batch of UUIDs using a single set-based query.
     *
     * Returns ['uuid' => count] for UUIDs with references, or false on query failure.
     *
     * @param array<int, string> $uuids
     * @return array<string, int>|false
     */
    private function checkDeckReferences(array $uuids): array|false
    {
        $placeholders = implode(',', array_fill(0, count($uuids), '?'));
        $sql = "SELECT dc.cardnumber AS card_id, COUNT(*) AS deck_ref_count
                FROM deckcards dc
                WHERE dc.cardnumber IN ({$placeholders})
                GROUP BY dc.cardnumber";

        $this->logger->logMessage('[DEBUG]', "Deck reference query: {$sql}");

        $result = $this->db->execute_query($sql, $uuids);
        if ($result === false) {
            $this->logger->logMessage('[ERROR]', 'Deck reference query failed');
            return false;
        }

        $refs = [];
        while ($row = $result->fetch_assoc()) {
            $refs[$row['card_id']] = (int) $row['deck_ref_count'];
        }
        $result->free();

        return $refs;
    }

    /**
     * Discover all users for collection table iteration.
     *
     * Returns [['usernumber' => int, 'username' => string], ...] or false on failure.
     *
     * @return array<int, array<string, mixed>>|false
     */
    private function discoverUsers(): array|false
    {
        $sql = "SELECT usernumber, username FROM users";
        $this->logger->logMessage('[DEBUG]', "User discovery query: {$sql}");

        $result = $this->db->execute_query($sql);
        if ($result === false) {
            $this->logger->logMessage('[ERROR]', 'User discovery query failed');
            return false;
        }

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'usernumber' => (int) $row['usernumber'],
                'username' => $row['username'],
            ];
        }
        $result->free();

        return $users;
    }

    /**
     * Check collection quantities for a batch of UUIDs across all user collection tables.
     *
     * Returns a structured result with quantities and any failure reasons.
     *
     * @param array<int, string> $uuids
     * @param array<int, array<string, mixed>> $users
     * @return array{quantities: array<string, int>, failure_reasons: array<int, string>}
     */
    private function checkCollectionQuantities(array $uuids, array $users): array
    {
        $quantities = [];
        $failureReasons = [];
        $placeholders = implode(',', array_fill(0, count($uuids), '?'));

        foreach ($users as $user) {
            $candidateTable = (string) $user['usernumber'] . 'collection';
            $validatedTable = call_user_func($this->tableNameValidator, $candidateTable);
            // Validate the callable result: require false or a non-empty string.
            // Fail closed for null, true, or arbitrary values that could inject SQL.
            if ($validatedTable === false) {
                $this->logger->logMessage(
                    '[ERROR]',
                    "Collection table name validation failed for {$candidateTable}"
                );
                $failureReasons[] = 'COLLECTION_TABLE_NAME_INVALID';
                break;
            }
            if (!is_string($validatedTable) || $validatedTable === '') {
                $this->logger->logMessage(
                    '[ERROR]',
                    "Collection table name validation returned non-string for {$candidateTable}"
                );
                $failureReasons[] = 'COLLECTION_TABLE_NAME_INVALID';
                break;
            }

            // Check existence via information_schema (parameterized)
            $existenceSql = "SELECT 1 FROM information_schema.tables
                             WHERE table_schema = DATABASE()
                             AND table_name = ?";
            $existenceResult = $this->db->execute_query($existenceSql, [$candidateTable]);
            if ($existenceResult === false) {
                $this->logger->logMessage(
                    '[ERROR]',
                    "Table existence check failed for {$candidateTable}"
                );
                $failureReasons[] = 'COLLECTION_TABLE_DISCOVERY_FAILED';
                break;
            }

            if ($existenceResult->num_rows === 0) {
                $this->logger->logMessage(
                    '[DEBUG]',
                    "Collection table {$candidateTable} does not exist; skipping"
                );
                $existenceResult->free();
                continue;
            }
            $existenceResult->free();

            // Query quantities in the validated collection table
            $sql = "SELECT id,
                           SUM(COALESCE(`normal`, 0) + COALESCE(`foil`, 0) + COALESCE(`etched`, 0))
                           AS total_qty
                    FROM `{$validatedTable}`
                    WHERE id IN ({$placeholders})
                    GROUP BY id";

            $this->logger->logMessage(
                '[DEBUG]',
                "Collection quantity query for {$validatedTable}: {$sql}"
            );

            $result = $this->db->execute_query($sql, $uuids);
            if ($result === false) {
                $this->logger->logMessage(
                    '[ERROR]',
                    "Collection quantity query failed for {$validatedTable}"
                );
                $failureReasons[] = 'COLLECTION_QUERY_FAILED';
                break;
            }

            while ($row = $result->fetch_assoc()) {
                $cardId = $row['id'];
                $qty = (int) $row['total_qty'];
                if (isset($quantities[$cardId])) {
                    $quantities[$cardId] += $qty;
                } else {
                    $quantities[$cardId] = $qty;
                }
            }
            $result->free();
        }

        return ['quantities' => $quantities, 'failure_reasons' => $failureReasons];
    }

    /**
     * Build a default (safe) result for a UUID.
     *
     * @return array<string, mixed>
     */
    private function defaultResult(string $uuid): array
    {
        return [
            'id' => $uuid,
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => false,
            'failure_reasons' => [],
        ];
    }

    /**
     * Build a failed result for a UUID with the given failure reasons.
     *
     * @param array<int, string> $reasons
     * @return array<string, mixed>
     */
    private function failedResult(string $uuid, array $reasons): array
    {
        return [
            'id' => $uuid,
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => true,
            'failure_reasons' => $reasons,
        ];
    }
}
