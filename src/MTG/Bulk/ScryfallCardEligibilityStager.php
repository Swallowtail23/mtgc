<?php

/*
Version:     1.1
Date:        19/09/26
Name:        ScryfallCardEligibilityStager.php
Purpose:     Streams a complete Scryfall all_cards source, applies the same import
             policy used by the importer, and records every eligible card UUID into
             a run-scoped temporary staging table for the Stage 5 reconciler.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

use Generator;
use MTG\Core\Message;
use MTG\Core\Validation;

/**
 * Streams a complete Scryfall `all_cards` source through the shared import policy and
 * records every eligible card UUID into a session-scoped temporary table.
 *
 * The service is deliberately destructive-free: it never touches `cards_scry` or any user
 * table. It is a pure eligibility staging pass that the Stage 5 reconciler consumes.
 */
class ScryfallCardEligibilityStager
{
    public const STAGE_TABLE = 'stg_scryfall_eligible_ids';

    private const SOURCE_KIND_ALL = 'all_cards';

    private const FILE_SUFFIX_JSONL = '.jsonl';

    private const FILE_SUFFIX_JSONL_GZ = '.jsonl.gz';

    /** @var object mysqli-compatible connection (execute_query seam). */
    private object $db;

    private Message $logger;

    /** @var callable(string): Generator */
    private $recordIterator;

    private string $stageTable;

    private int $batchSize;

    private ScryfallCardImportPolicy $policy;

    /** @var array<string, mixed> */
    private array $stats = [];

    /** @var array<string, int> */
    private array $excludedReasons = [];

    private int $insertedTotal = 0;

    /** @var array<int, string> */
    private array $failureReasons = [];

    private bool $transactionActive = false;

    private int $batchIndex = 0;

    /**
     * @param object                     $db             mysqli-compatible connection (execute_query seam)
     * @param Message                    $logger         log-writer (DEBUG/NOTICE/ERROR levels only)
     * @param ScryfallCardImportPolicy   $policy         same policy instance contract as the importer
     * @param callable(string): Generator|null $recordIterator test seam; defaults to
     *                                             [ScryfallBulkFiles::class, 'iterateBulkRecords']
     * @param string                     $stageTable     constructor-injectable for test isolation
     * @param int                        $batchSize      buffer/flush size, minimum 1 (default 5000)
     */
    public function __construct(
        object $db,
        Message $logger,
        ScryfallCardImportPolicy $policy,
        ?callable $recordIterator = null,
        string $stageTable = self::STAGE_TABLE,
        int $batchSize = 5000
    ) {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException(
                "Batch size must be at least 1, {$batchSize} given"
            );
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $stageTable)) {
            throw new \InvalidArgumentException("Invalid staging table name '{$stageTable}'");
        }

        $this->db = $db;
        $this->logger = $logger;
        $this->policy = $policy;
        $this->recordIterator = $recordIterator ?? [ScryfallBulkFiles::class, 'iterateBulkRecords'];
        $this->stageTable = $stageTable;
        $this->batchSize = $batchSize;
    }

    /**
     * Stream the source and populate the session temporary stage.
     *
     * The `verifiedSource` argument is a Stage 6 contract: when provided, the caller has
     * already confirmed the file against the source-tracker row / manifest hash and passes the
     * descriptor through unchanged. It is validated by the caller, not the stager; the stager
     * only relies on it being non-null to log that a verified source was supplied.
     *
     * @param string $fileLocation local .jsonl or .jsonl.gz path
     * @param string $sourceKind   'all_cards' or 'default_cards'
     * @param array<string, mixed>|null $verifiedSource Stage 6 optional descriptor (manifest hash /
     *                                                  download URI); null means Stage 4 contract-only
     *
     * @return array{
     *     success: bool,
     *     reason: string|null,
     *     failure_reasons: array<int, string>,
     *     stage_table: string,
     *     stats: array{
     *         total_records: int,
     *         valid_records: int,
     *         missing_id_records: int,
     *         invalid_id_records: int,
     *         excluded_records: int,
     *         duplicate_ids: int,
     *         eligible_staged: int
     *     },
     *     excluded_reasons: array<string, int>,
     *     elapsed_seconds: float
     * }
     */
    public function stageSource(string $fileLocation, string $sourceKind, ?array $verifiedSource = null): array
    {
        $start = microtime(true);
        $this->resetState();

        if ($sourceKind !== self::SOURCE_KIND_ALL) :
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: source kind is '{$sourceKind}', not 'all_cards'; refusing to stage"
            );
            return $this->buildResult(false, 'SOURCE_NOT_COMPLETE', $start);
        endif;

        if (
            !str_ends_with($fileLocation, self::FILE_SUFFIX_JSONL)
            && !str_ends_with($fileLocation, self::FILE_SUFFIX_JSONL_GZ)
        ) :
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: unrecognised source extension for '{$fileLocation}'"
            );
            return $this->buildResult(false, 'SOURCE_UNRECOGNIZED', $start);
        endif;

        if (!is_readable($fileLocation)) :
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: source file not readable: {$fileLocation}"
            );
            return $this->buildResult(false, 'SOURCE_UNREADABLE', $start);
        endif;

        if ($verifiedSource !== null) :
            $this->logger->logMessage(
                '[DEBUG]',
                'Eligibility stager: verified source supplied (Stage 6 contract); trusting caller'
            );
        endif;

        if (!$this->createStageTable()) :
            return $this->buildResult(false, 'STAGE_CREATE_FAILED', $start);
        endif;

        if (!$this->beginTransaction()) :
            $this->failureReasons[] = 'BEGIN_FAILED';
            if (!$this->dropStageChecked()) :
                $this->failureReasons[] = 'CLEANUP_FAILED';
            endif;
            return $this->buildResult(false, $this->primaryReason() ?? 'BEGIN_FAILED', $start);
        endif;

        try {
            $iterator = ($this->recordIterator)($fileLocation);
            $buffer = [];

            foreach ($iterator as $record) {
                if (!$this->transactionActive && !$this->beginTransaction()) {
                    return $this->fail('BEGIN_FAILED', $start);
                }

                $this->stats['total_records']++;

                if (!is_array($record)) {
                    $this->stats['missing_id_records']++;
                    continue;
                }

                $id = $record['id'] ?? null;
                if ($id === null) {
                    $this->stats['missing_id_records']++;
                    continue;
                }

                $validated = Validation::validUUID($id);
                if ($validated === false) {
                    $this->stats['invalid_id_records']++;
                    continue;
                }

                $decision = $this->applyPolicy($record, $validated);
                if ($decision === null) {
                    return $this->fail('POLICY_FAILED', $start);
                }

                $this->stats['valid_records']++;
                if (!$decision['include']) {
                    $this->stats['excluded_records']++;
                    $reason = $decision['reason'];
                    $this->excludedReasons[$reason] = ($this->excludedReasons[$reason] ?? 0) + 1;
                    continue;
                }

                $buffer[] = strtolower($validated);

                if (count($buffer) >= $this->batchSize) {
                    $flush = $this->flushBatch($buffer);
                    if ($flush['reason'] !== null) {
                        return $this->fail($flush['reason'], $start);
                    }
                    $buffer = [];
                }
            }
        } catch (\RuntimeException $e) {
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: JSONL parse failed: {$e->getMessage()}"
            );
            return $this->fail('SOURCE_PARSE_FAILED', $start);
        } catch (\Throwable $e) {
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: iterator failed: {$e->getMessage()}"
            );
            return $this->fail('ITERATOR_FAILED', $start);
        }

        if ($this->stats['total_records'] === 0) :
            $this->logger->logMessage('[ERROR]', 'Eligibility stager: source contained no records');
            return $this->fail('SOURCE_EMPTY', $start);
        endif;

        if (!empty($buffer)) :
            $flush = $this->flushBatch($buffer);
            if ($flush['reason'] !== null) :
                return $this->fail($flush['reason'], $start);
            endif;
        endif;

        $eligibleStaged = $this->countStaged();
        if ($eligibleStaged === -1) :
            $this->logger->logMessage('[ERROR]', 'Eligibility stager: failed to count staged rows');
            return $this->fail('STAGE_COUNT_FAILED', $start);
        endif;

        $this->stats['eligible_staged'] = $eligibleStaged;

        if ($eligibleStaged !== $this->insertedTotal) :
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: staged count {$eligibleStaged} != insertedTotal {$this->insertedTotal} "
                . '(STAGE_COUNT_MISMATCH)'
            );
            return $this->fail('STAGE_COUNT_FAILED', $start);
        endif;

        if ($eligibleStaged === 0) :
            $this->logger->logMessage('[ERROR]', 'Eligibility stager: eligible set is empty');
            return $this->fail('EMPTY_ELIGIBLE_SET', $start);
        endif;

        $this->logger->logMessage(
            '[NOTICE]',
            "Eligibility stager: complete. eligible_staged={$eligibleStaged} "
            . "excluded={$this->stats['excluded_records']} "
            . "duplicate={$this->stats['duplicate_ids']} "
            . "valid={$this->stats['valid_records']} "
            . "total={$this->stats['total_records']}"
        );

        return $this->buildResult(true, 'SUCCESS', $start);
    }

    /**
     * Authoritative staged row count for Stage 5 preconditions.
     *
     * Returns -1 on query failure so callers can fail closed.
     */
    public function stagedCount(): int
    {
        try {
            $result = $this->db->execute_query(
                "SELECT COUNT(*) AS cnt FROM `{$this->stageTable}`"
            );
        } catch (\Throwable $e) {
            return -1;
        }
        if ($result === false) :
            return -1;
        endif;

        try {
            $row = $result->fetch_assoc();
            $result->free();
        } catch (\Throwable $e) {
            return -1;
        }

        return (int) ($row['cnt'] ?? -1);
    }

    /**
     * Explicit cleanup; also happens implicitly at session close. Safe to call twice.
     */
    public function dropStage(): void
    {
        $this->dropStageChecked();
    }

    /**
     * Create the session temporary staging table outside any transaction.
     *
     * @return bool true when the table is present, false on DDL failure
     */
    private function createStageTable(): bool
    {
        if (!$this->dropStageChecked()) {
            $this->logger->logMessage(
                '[ERROR]',
                'Eligibility stager: failed to remove existing temporary stage table'
            );
            $this->failureReasons[] = 'STAGE_CREATE_FAILED';
            $this->failureReasons[] = 'CLEANUP_FAILED';
            return false;
        }

        try {
            $result = $this->db->execute_query(
                "CREATE TEMPORARY TABLE `{$this->stageTable}` (
                   `id` CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                   PRIMARY KEY (`id`)
                 ) ENGINE=InnoDB"
            );
        } catch (\Throwable $e) {
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: temporary stage table creation threw: {$e->getMessage()}"
            );
            $this->failureReasons[] = 'STAGE_CREATE_FAILED';
            return false;
        }

        if ($result === false) :
            $this->logger->logMessage(
                '[ERROR]',
                'Eligibility stager: failed to create temporary stage table'
            );
            $this->failureReasons[] = 'STAGE_CREATE_FAILED';
            return false;
        endif;

        $this->logger->logMessage(
            '[DEBUG]',
            "Eligibility stager: created temporary stage table '{$this->stageTable}'"
        );

        return true;
    }

    /**
     * Begin the staging transaction.
     *
     * @return bool true when BEGIN succeeded, false otherwise
     */
    private function beginTransaction(): bool
    {
        try {
            $result = $this->db->execute_query('BEGIN');
        } catch (\Throwable $e) {
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: BEGIN threw: {$e->getMessage()}"
            );
            return false;
        }
        if ($result === false) :
            $this->logger->logMessage('[ERROR]', 'Eligibility stager: BEGIN failed');
            return false;
        endif;

        $this->transactionActive = true;

        return true;
    }

    /**
     * Apply the shared import policy to a validated record.
     *
     * @param array<string, mixed> $record
     *
     * @return array{include: bool, reason: string}|null null when the policy throws
     */
    private function applyPolicy(array $record, string $validated): ?array
    {
        try {
            // The policy's decide() uses 'all'/'default' as its type discriminator (matching the
            // import runner), not the source-kind value 'all_cards'.
            $decision = $this->policy->decide($record, 'all');
        } catch (\Throwable $e) {
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: policy threw: {$e->getMessage()}"
            );
            return null;
        }

        return [
            'include' => $decision['include'] ?? false,
            'reason' => $decision['reason'] ?? 'excluded',
        ];
    }

    /**
     * Flush the current buffer with a single batched INSERT IGNORE and commit.
     *
     * @param array<int, string> $buffer
     *
     * @return array{inserted: int, reason: string|null}
     */
    private function flushBatch(array $buffer): array
    {
        $this->batchIndex++;
        $batchIndex = $this->batchIndex;
        $placeholders = implode(',', array_fill(0, count($buffer), '?'));
        $sql = "INSERT IGNORE INTO `{$this->stageTable}` (`id`) VALUES ({$placeholders})";

        $this->logger->logMessage('[DEBUG]', "Eligibility stager: flushing batch {$batchIndex} of "
            . count($buffer) . ' UUID(s)');

        try {
            $result = $this->db->execute_query($sql, $buffer);
        } catch (\Throwable $e) {
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: batch insert threw: {$e->getMessage()}"
            );
            return ['inserted' => 0, 'reason' => 'STAGE_INSERT_FAILED'];
        }
        if ($result === false) :
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: batch insert failed"
            );
            return ['inserted' => 0, 'reason' => 'STAGE_INSERT_FAILED'];
        endif;

        $inserted = (int) ($this->db->affected_rows ?? 0);
        $this->insertedTotal += $inserted;

        try {
            $commitResult = $this->db->execute_query('COMMIT');
        } catch (\Throwable $e) {
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: batch commit threw: {$e->getMessage()}"
            );
            return ['inserted' => $inserted, 'reason' => 'COMMIT_FAILED'];
        }
        if ($commitResult === false) :
            $this->logger->logMessage('[ERROR]', 'Eligibility stager: batch commit failed');
            return ['inserted' => $inserted, 'reason' => 'COMMIT_FAILED'];
        endif;

        $this->transactionActive = false;

        $this->logger->logMessage(
            '[DEBUG]',
            "Eligibility stager: committed batch {$batchIndex}; size=" . count($buffer)
            . "; inserted={$inserted}"
        );

        return ['inserted' => $inserted, 'reason' => null];
    }

    /**
     * Authoritative count of staged rows, or -1 on failure.
     */
    private function countStaged(): int
    {
        try {
            $result = $this->db->execute_query(
                "SELECT COUNT(*) AS cnt FROM `{$this->stageTable}`"
            );
        } catch (\Throwable $e) {
            return -1;
        }
        if ($result === false) :
            return -1;
        endif;

        try {
            $row = $result->fetch_assoc();
            $result->free();
        } catch (\Throwable $e) {
            return -1;
        }

        return (int) ($row['cnt'] ?? -1);
    }

    /**
     * Handle a streaming failure: roll back, drop the stage, and record the reason.
     *
     * The originating reason is preserved; a failed rollback or drop is appended rather
     * than replacing it.
     */
    private function fail(string $reason, float $start): array
    {
        $this->failureReasons[] = $reason;
        $this->logger->logMessage('[ERROR]', "Eligibility stager: {$reason}");

        if ($this->transactionActive) :
            try {
                $rollback = $this->db->execute_query('ROLLBACK');
            } catch (\Throwable $e) {
                $rollback = false;
            }
            if ($rollback === false) :
                $this->logger->logMessage('[ERROR]', 'Eligibility stager: ROLLBACK failed');
                $this->failureReasons[] = 'ROLLBACK_FAILED';
            endif;
            $this->transactionActive = false;
        endif;

        if (!$this->dropStageChecked()) :
            $this->failureReasons[] = 'CLEANUP_FAILED';
        endif;

        return $this->buildResult(false, $this->primaryReason(), $start);
    }

    /**
     * Drop the temporary stage and report whether the database accepted the cleanup.
     */
    private function dropStageChecked(): bool
    {
        try {
            $result = $this->db->execute_query(
                "DROP TEMPORARY TABLE IF EXISTS `{$this->stageTable}`"
            );
        } catch (\Throwable $e) {
            $this->logger->logMessage(
                '[ERROR]',
                "Eligibility stager: DROP TEMPORARY TABLE threw: {$e->getMessage()}"
            );
            return false;
        }
        if ($result === false) :
            $this->logger->logMessage('[ERROR]', 'Eligibility stager: DROP TEMPORARY TABLE failed');
            return false;
        endif;

        return true;
    }

    /**
     * Reset per-run mutable state.
     */
    private function resetState(): void
    {
        $this->stats = [
            'total_records' => 0,
            'valid_records' => 0,
            'missing_id_records' => 0,
            'invalid_id_records' => 0,
            'excluded_records' => 0,
            'duplicate_ids' => 0,
            'eligible_staged' => 0,
        ];
        $this->excludedReasons = [];
        $this->insertedTotal = 0;
        $this->failureReasons = [];
        $this->transactionActive = false;
        $this->batchIndex = 0;
    }

    /**
     * Build the returned contract.
     */
    private function buildResult(bool $success, string $reason, float $start): array
    {
        if (!$success && $this->failureReasons === []) {
            $this->failureReasons[] = $reason;
        }

        $this->stats['duplicate_ids'] = $this->duplicateIds();

        return [
            'success' => $success,
            'reason' => $success ? $reason : ($this->failureReasons[0] ?? $reason),
            'failure_reasons' => $this->failureReasons,
            'stage_table' => $this->stageTable,
            'stats' => $this->stats,
            'excluded_reasons' => $this->excludedReasons,
            'elapsed_seconds' => microtime(true) - $start,
        ];
    }

    /**
     * The originating failure reason (first recorded), or null on success.
     */
    private function primaryReason(): ?string
    {
        return $this->failureReasons[0] ?? null;
    }

    /**
     * Policy-passing records that did not insert (already present, or case-variant).
     */
    private function duplicateIds(): int
    {
        $policyPassed = $this->stats['valid_records'] - $this->stats['excluded_records'];

        return max(0, $policyPassed - $this->insertedTotal);
    }
}
