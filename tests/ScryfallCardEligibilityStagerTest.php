<?php

/*
Version:     1.1
Date:        19/09/26
Name:        ScryfallCardEligibilityStagerTest.php
Purpose:     Unit tests for the ScryfallCardEligibilityStager service.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Tests;

use MTG\Bulk\ScryfallCardEligibilityStager;
use MTG\Bulk\ScryfallCardImportPolicy;
use MTG\Bulk\ScryfallGameTypeRepository;
use MTG\Core\AppConfig;
use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

/**
 * Minimal result object for eligibility stager tests.
 */
class EligStageResult
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;

    /** @var int */
    private int $index = 0;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function fetch_assoc(): ?array
    {
        if ($this->index >= count($this->rows)) {
            return null;
        }
        return $this->rows[$this->index++];
    }

    public function free(): void
    {
        $this->index = 0;
    }
}

/**
 * Duck-typed mysqli-compatible test double for ScryfallCardEligibilityStager.
 *
 * Tracks a session-scoped set of UUIDs and honours the transactional contract:
 * COMMIT/ROLLBACK affect only rows, never the table.
 */
class EligStageMysqli
{
    /** @var array<string, mixed> */
    public array $queryRoutes = [];

    /** @var array<int, string> */
    public array $recordedStatements = [];

    /** @var array<int, string> */
    public array $recordedParams = [];

    public int $affected_rows = 0;

    public string $error = '';

    public bool $failCreate = false;

    public bool $failInsert = false;

    public int $failInsertOnCall = 0;

    public bool $failCount = false;

    public bool $failCommit = false;

    public bool $failBegin = false;

    public bool $failRollback = false;

    public bool $failCleanup = false;

    public int $failCleanupOnDropCall = 0;

    public bool $throwCreate = false;

    public bool $throwInsert = false;

    public bool $throwCount = false;

    public bool $throwCleanup = false;

    public int $commitCount = 0;

    public int $beginCount = 0;

    public int $rollbackCount = 0;

    public int $dropCount = 0;

    public int $insertCount = 0;

    /** @var array<int, string> */
    private array $committedRows = [];

    /** @var array<int, string> */
    private array $pendingRows = [];

    private bool $tableExists = false;

    private bool $transactionActive = false;

    public function set_charset(string $charset): bool
    {
        return true;
    }

    /**
     * @param array<int, string>|null $params
     */
    public function execute_query(string $query, ?array $params = null)
    {
        $this->error = '';
        $this->recordedStatements[] = $query;
        $this->recordedParams[] = $params ?? [];

        $lowerQuery = strtolower($query);

        // BEGIN
        if (trim($lowerQuery) === 'begin') {
            $this->beginCount++;
            if ($this->failBegin) {
                return false;
            }
            $this->transactionActive = true;
            return true;
        }

        // COMMIT
        if (trim($lowerQuery) === 'commit') {
            $this->commitCount++;
            if ($this->failCommit) {
                return false;
            }
            $this->committedRows = array_merge($this->committedRows, $this->pendingRows);
            $this->pendingRows = [];
            $this->transactionActive = false;
            return true;
        }

        // ROLLBACK
        if (trim($lowerQuery) === 'rollback') {
            $this->rollbackCount++;
            if ($this->failRollback) {
                return false;
            }
            $this->pendingRows = [];
            $this->transactionActive = false;
            return true;
        }

        // DROP TEMPORARY TABLE (cleanup)
        if (str_contains($lowerQuery, 'drop temporary table')) {
            $this->dropCount++;
            if ($this->throwCleanup) {
                throw new \RuntimeException('cleanup boom');
            }
            if ($this->failCleanup || $this->dropCount === $this->failCleanupOnDropCall) {
                return false;
            }
            $this->tableExists = false;
            $this->committedRows = [];
            $this->pendingRows = [];
            return true;
        }

        // DROP TEMPORARY TABLE IF EXISTS (create stage)
        if (str_contains($lowerQuery, 'drop temporary table if exists')) {
            $this->dropCount++;
            return true;
        }

        // CREATE TEMPORARY TABLE
        if (str_contains($lowerQuery, 'create temporary table')) {
            if ($this->throwCreate) {
                throw new \RuntimeException('create boom');
            }
            if ($this->failCreate) {
                return false;
            }
            $this->tableExists = true;
            return new EligStageResult([]);
        }

        // INSERT IGNORE
        if (str_contains($lowerQuery, 'insert ignore')) {
            $this->insertCount++;
            if ($this->throwInsert) {
                throw new \RuntimeException('insert boom');
            }
            if (
                !$this->transactionActive
                || $this->failInsert
                || ($this->failInsertOnCall > 0 && $this->insertCount === $this->failInsertOnCall)
            ) {
                return false;
            }
            $inserted = 0;
            foreach ($params ?? [] as $value) {
                if (
                    !in_array($value, $this->committedRows, true)
                    && !in_array($value, $this->pendingRows, true)
                ) {
                    $this->pendingRows[] = $value;
                    $inserted++;
                }
            }
            $this->affected_rows = $inserted;
            return new EligStageResult([]);
        }

        // SELECT code ... WHERE selected = 1
        if (str_contains($lowerQuery, 'selected = 1')) {
            $rows = [];
            foreach ($this->queryRoutes['selected'] ?? [] as $row) {
                $rows[] = ['code' => $row['code']];
            }
            return new EligStageResult($rows);
        }

        // SELECT COUNT
        if (str_contains($lowerQuery, 'select') && str_contains($lowerQuery, 'count(*)')) {
            if ($this->throwCount) {
                throw new \RuntimeException('count boom');
            }
            if ($this->failCount) {
                return false;
            }
            return new EligStageResult([['cnt' => count($this->committedRows)]]);
        }

        $this->error = 'Unmocked query: ' . $query;
        return false;
    }
}

/**
 * Minimal Message double for eligibility stager tests.
 */
class EligStageMessage extends Message
{
    /** @var list<string> */
    public array $messages = [];

    public function __construct()
    {
        $ini = [
            'general' => [
                'appName' => 'Test',
                'logFile' => '',
                'logLevel' => 3,
            ],
            'security' => [],
            'email' => [],
            'fx' => [],
            'comments' => [],
        ];
        parent::__construct(AppConfig::fromIni($ini));
    }

    public function logMessage(string $errorlevel, string $text, string $logfile = ''): void
    {
        $this->messages[] = "[{$errorlevel}] {$text}";
    }
}

class ScryfallCardEligibilityStagerTest extends TestCase
{
    private const SAMPLE = __DIR__ . '/test_data/elig_stage_all_sample.jsonl';

    private const TRUNCATED = __DIR__ . '/test_data/elig_stage_truncated.jsonl';

    private const EMPTY = __DIR__ . '/test_data/elig_stage_empty.jsonl';

    private const NO_ELIGIBLE = __DIR__ . '/test_data/elig_stage_no_eligible.jsonl';

    private const NON_JSONL = __DIR__ . '/test_data/elig_stage_non_jsonl.json';

    private function makeMessage(): EligStageMessage
    {
        return new EligStageMessage();
    }

    /**
     * Build the policy under test with paper/arena selected, fr in langs_to_skip_all,
     * double in layouts_to_skip.
     */
    private function makePolicy(EligStageMysqli $db): ScryfallCardImportPolicy
    {
        $db->queryRoutes = [
            'selected' => [
                ['code' => 'paper', 'selected' => 1],
                ['code' => 'arena', 'selected' => 1],
            ],
        ];
        $repository = new ScryfallGameTypeRepository($db, $this->makeMessage());
        $gameRules = new \MTG\Core\GameRules([
            'langs_to_skip_all' => ['fr'],
            'layouts_to_skip' => ['double'],
        ]);
        return new ScryfallCardImportPolicy($gameRules, $repository);
    }

    private function createStager(
        ?EligStageMysqli $db = null,
        ?ScryfallCardImportPolicy $policy = null,
        ?callable $iterator = null,
        string $stageTable = ScryfallCardEligibilityStager::STAGE_TABLE,
        int $batchSize = 5000
    ): ScryfallCardEligibilityStager {
        $db = $db ?? new EligStageMysqli();
        $policy = $policy ?? $this->makePolicy($db);
        return new ScryfallCardEligibilityStager(
            $db,
            $this->makeMessage(),
            $policy,
            $iterator,
            $stageTable,
            $batchSize
        );
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return callable(string): \Generator
     */
    private function makeIterator(array $records): callable
    {
        return static function () use ($records): \Generator {
            foreach ($records as $i => $record) {
                yield $i => $record;
            }
        };
    }

    /**
     * Assert that no staging DDL/INSERT/transaction statements were issued.
     *
     * The policy constructor issues a single catalogue SELECT, so the recorded statement
     * list is not empty; this checks only statements that originate from the stager.
     */
    private function assertNoStagingStatements(EligStageMysqli $db): void
    {
        foreach ($db->recordedStatements as $statement) {
            $lower = strtolower($statement);
            $this->assertStringNotContainsString(
                'temporary table',
                $lower,
                'No TEMPORARY TABLE statement should have been issued'
            );
            $this->assertStringNotContainsString(
                'begin',
                $lower,
                'No BEGIN should have been issued'
            );
            $this->assertStringNotContainsString(
                'insert ignore',
                $lower,
                'No INSERT IGNORE should have been issued'
            );
        }
    }

    // ====================================================================
    // Eligibility / fixture tests
    // ====================================================================

    public function testEligiblePaperAndArenaAreStaged(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertTrue($result['success']);
        $this->assertSame('SUCCESS', $result['reason']);
        $this->assertSame(3, $result['stats']['eligible_staged']);
        $this->assertSame(1, $db->commitCount);
    }

    public function testMtgoOnlyExcludedWithReason(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('unsupported game', $result['excluded_reasons']);
        $this->assertGreaterThanOrEqual(1, $result['excluded_reasons']['unsupported game']);
    }

    public function testMissingAndEmptyGamesExcludedAndCounted(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('unsupported game', $result['excluded_reasons']);
    }

    public function testInvalidAndMissingIdsNeverStaged(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['stats']['missing_id_records']);
        $this->assertSame(2, $result['stats']['invalid_id_records']);
    }

    public function testDuplicateIdsStagedOnceWithMetric(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['stats']['duplicate_ids']);
        $this->assertSame(3, $result['stats']['eligible_staged']);
    }

    public function testExcludedLayoutCounted(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('excluded layout', $result['excluded_reasons']);
    }

    public function testAllFileLanguageExclusion(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('excluded all-file language', $result['excluded_reasons']);
        $this->assertArrayNotHasKey('excluded language', $result['excluded_reasons']);
    }

    public function testStreamingBatchesLargerThanBatchSize(): void
    {
        $db = new EligStageMysqli();
        $records = [];
        for ($i = 0; $i < 250; $i++) {
            $records[] = [
                'id' => sprintf('%08x-0000-1000-8000-%012x', $i, $i),
                'games' => ['paper'],
                'lang' => 'en',
                'layout' => 'normal',
            ];
        }

        $stager = $this->createStager($db, null, $this->makeIterator($records), ScryfallCardEligibilityStager::STAGE_TABLE, 50);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertTrue($result['success']);
        $this->assertSame(5, $db->commitCount);
        $this->assertSame(5, $db->beginCount);
        $this->assertSame(250, $result['stats']['eligible_staged']);
    }

    public function testExactMultipleBatchSizeNoTrailingCommit(): void
    {
        $db = new EligStageMysqli();
        $records = [];
        for ($i = 0; $i < 200; $i++) {
            $records[] = [
                'id' => sprintf('%08x-0000-1000-8000-%012x', $i, $i),
                'games' => ['paper'],
                'lang' => 'en',
                'layout' => 'normal',
            ];
        }

        $stager = $this->createStager($db, null, $this->makeIterator($records), ScryfallCardEligibilityStager::STAGE_TABLE, 50);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertTrue($result['success']);
        $this->assertSame(4, $db->commitCount);
        $this->assertSame(4, $db->beginCount);
        $this->assertSame(200, $result['stats']['eligible_staged']);
    }

    // ====================================================================
    // Failure tests
    // ====================================================================

    public function testTruncatedJsonlFailsAndDropsStage(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::TRUNCATED, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('SOURCE_PARSE_FAILED', $result['reason']);
        $this->assertGreaterThanOrEqual(1, $db->rollbackCount);
        $this->assertGreaterThanOrEqual(1, $db->dropCount);
        $this->assertArrayHasKey('total_records', $result['stats']);
    }

    public function testEmptySourceFails(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::EMPTY, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('SOURCE_EMPTY', $result['reason']);
    }

    public function testNoEligibleRecordsFailsEmptySet(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::NO_ELIGIBLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('EMPTY_ELIGIBLE_SET', $result['reason']);
        $this->assertGreaterThanOrEqual(1, $db->dropCount);
    }

    public function testEmptyEligibleSetHasNoEscapeHatch(): void
    {
        $reflection = new \ReflectionClass(ScryfallCardEligibilityStager::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $paramNames = [];
        foreach ($constructor->getParameters() as $param) {
            $paramNames[] = $param->getName();
        }
        $this->assertNotContains('allowEmptyEligible', $paramNames);
    }

    public function testDefaultCardsSourceCannotProduceStage(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'default_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('SOURCE_NOT_COMPLETE', $result['reason']);
        $this->assertNoStagingStatements($db);
    }

    public function testUnrecognisedExtensionFailsBeforeDDL(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::NON_JSONL, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('SOURCE_UNRECOGNIZED', $result['reason']);
        $this->assertNoStagingStatements($db);
    }

    public function testCreateFailureFailsClosed(): void
    {
        $db = new EligStageMysqli();
        $db->failCreate = true;
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('STAGE_CREATE_FAILED', $result['reason']);
        $this->assertContains('STAGE_CREATE_FAILED', $result['failure_reasons']);
    }

    public function testThrownCreateFailureIsStructured(): void
    {
        $db = new EligStageMysqli();
        $db->throwCreate = true;
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('STAGE_CREATE_FAILED', $result['reason']);
        $this->assertContains('STAGE_CREATE_FAILED', $result['failure_reasons']);
    }

    public function testInsertFailureRollsBackAndDrops(): void
    {
        $db = new EligStageMysqli();
        $records = [];
        for ($i = 0; $i < 50; $i++) {
            $records[] = [
                'id' => sprintf('%08x-0000-1000-8000-%012x', $i, $i),
                'games' => ['paper'],
                'lang' => 'en',
                'layout' => 'normal',
            ];
        }
        $db->failInsert = true;
        $stager = $this->createStager($db, null, $this->makeIterator($records), ScryfallCardEligibilityStager::STAGE_TABLE, 50);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('STAGE_INSERT_FAILED', $result['reason']);
        $this->assertSame(1, $db->rollbackCount);
        $this->assertGreaterThanOrEqual(1, $db->dropCount);
    }

    public function testThrownInsertFailureIsStructured(): void
    {
        $db = new EligStageMysqli();
        $db->throwInsert = true;
        $records = [[
            'id' => '11111111-1111-1111-8111-111111111111',
            'games' => ['paper'],
            'lang' => 'en',
            'layout' => 'normal',
        ]];
        $stager = $this->createStager($db, null, $this->makeIterator($records), ScryfallCardEligibilityStager::STAGE_TABLE, 1);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('STAGE_INSERT_FAILED', $result['reason']);
        $this->assertContains('STAGE_INSERT_FAILED', $result['failure_reasons']);
    }

    public function testLaterBatchFailureRollsBackCurrentTransaction(): void
    {
        $db = new EligStageMysqli();
        $records = [];
        for ($i = 0; $i < 100; $i++) {
            $records[] = [
                'id' => sprintf('%08x-0000-1000-8000-%012x', $i, $i),
                'games' => ['paper'],
                'lang' => 'en',
                'layout' => 'normal',
            ];
        }
        $db->failInsertOnCall = 2;
        $stager = $this->createStager(
            $db,
            null,
            $this->makeIterator($records),
            ScryfallCardEligibilityStager::STAGE_TABLE,
            50
        );

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('STAGE_INSERT_FAILED', $result['reason']);
        $this->assertSame(2, $db->beginCount);
        $this->assertSame(1, $db->rollbackCount);
        $this->assertGreaterThanOrEqual(1, $db->dropCount);
    }

    public function testCommitFailureRollsBackAndDrops(): void
    {
        $db = new EligStageMysqli();
        $records = [];
        for ($i = 0; $i < 50; $i++) {
            $records[] = [
                'id' => sprintf('%08x-0000-1000-8000-%012x', $i, $i),
                'games' => ['paper'],
                'lang' => 'en',
                'layout' => 'normal',
            ];
        }
        $db->failCommit = true;
        $stager = $this->createStager($db, null, $this->makeIterator($records), ScryfallCardEligibilityStager::STAGE_TABLE, 50);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('COMMIT_FAILED', $result['reason']);
        $this->assertSame(1, $db->rollbackCount);
        $this->assertGreaterThanOrEqual(1, $db->dropCount);
    }

    public function testBeginFailureFailsClosed(): void
    {
        $db = new EligStageMysqli();
        $db->failBegin = true;
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('BEGIN_FAILED', $result['reason']);
        $this->assertSame(0, $db->commitCount);
        $this->assertGreaterThanOrEqual(1, $db->dropCount);
    }

    public function testInvalidStageTableNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createStager(new EligStageMysqli(), null, null, 'stage`_injection');
    }

    public function testRollbackFailureReportsCleanup(): void
    {
        $db = new EligStageMysqli();
        $records = [];
        for ($i = 0; $i < 50; $i++) {
            $records[] = [
                'id' => sprintf('%08x-0000-1000-8000-%012x', $i, $i),
                'games' => ['paper'],
                'lang' => 'en',
                'layout' => 'normal',
            ];
        }
        $db->failInsert = true;
        $db->failRollback = true;
        $stager = $this->createStager($db, null, $this->makeIterator($records), ScryfallCardEligibilityStager::STAGE_TABLE, 50);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertContains('ROLLBACK_FAILED', $result['failure_reasons']);
        $this->assertGreaterThanOrEqual(1, $db->dropCount);
    }

    public function testCleanupFailureIsReported(): void
    {
        $db = new EligStageMysqli();
        $db->failCleanupOnDropCall = 2;
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::NO_ELIGIBLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertContains('CLEANUP_FAILED', $result['failure_reasons']);
    }

    public function testPolicyExceptionFailsClosed(): void
    {
        $db = new EligStageMysqli();
        $policy = $this->makePolicy($db);
        $throwingPolicy = new class ($policy) extends ScryfallCardImportPolicy {
            private ScryfallCardImportPolicy $delegate;

            public function __construct(ScryfallCardImportPolicy $delegate)
            {
                $this->delegate = $delegate;
            }

            public function decide(array $card, string $type): array
            {
                throw new \RuntimeException('policy boom');
            }
        };
        $stager = $this->createStager($db, $throwingPolicy, $this->makeIterator([
            ['id' => '11111111-1111-1111-8111-111111111111', 'games' => ['paper'], 'lang' => 'en', 'layout' => 'normal'],
        ]));

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('POLICY_FAILED', $result['reason']);
        $this->assertSame(1, $db->rollbackCount);
        $this->assertGreaterThanOrEqual(1, $db->dropCount);
    }

    public function testNonRuntimeIteratorExceptionFailsClosed(): void
    {
        $db = new EligStageMysqli();
        $throwingIterator = static function (): \Generator {
            throw new \LogicException('logic boom');
            yield;
        };
        $stager = $this->createStager($db, null, $throwingIterator);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertSame('ITERATOR_FAILED', $result['reason']);
        $this->assertSame(1, $db->rollbackCount);
        $this->assertGreaterThanOrEqual(1, $db->dropCount);
    }

    // ====================================================================
    // Lifecycle / contract tests
    // ====================================================================

    public function testDropStageUsesTemporaryKeyword(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $stager->dropStage();
        $found = false;
        foreach ($db->recordedStatements as $statement) {
            if (str_contains(strtolower($statement), 'drop temporary table')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'dropStage() should issue a DROP TEMPORARY TABLE statement');

        $stager->dropStage();
        $this->assertSame(2, $db->dropCount);
    }

    public function testNeverReferencesCardsScry(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $stager->stageSource(self::SAMPLE, 'all_cards');

        foreach ($db->recordedStatements as $statement) {
            $this->assertStringNotContainsString('cards_scry', $statement);
        }
    }

    public function testStagedCountNegativeOnQueryFailure(): void
    {
        $db = new EligStageMysqli();
        $db->failCount = true;
        $stager = $this->createStager($db);

        $this->assertSame(-1, $stager->stagedCount());
    }

    public function testStagedCountNegativeOnThrownQueryFailure(): void
    {
        $db = new EligStageMysqli();
        $db->throwCount = true;
        $stager = $this->createStager($db);

        $this->assertSame(-1, $stager->stagedCount());
    }

    public function testThrownCleanupFailureIsReported(): void
    {
        $db = new EligStageMysqli();
        $db->throwCleanup = true;
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::NO_ELIGIBLE, 'all_cards');

        $this->assertFalse($result['success']);
        $this->assertContains('STAGE_CREATE_FAILED', $result['failure_reasons']);
        $this->assertContains('CLEANUP_FAILED', $result['failure_reasons']);
    }

    public function testStatsInvariantOnSuccess(): void
    {
        $db = new EligStageMysqli();
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertTrue($result['success']);
        $stats = $result['stats'];
        $this->assertSame(
            $stats['valid_records'],
            $stats['eligible_staged'] + $stats['excluded_records'] + $stats['duplicate_ids']
        );
    }

    public function testPreservesOriginalReasonOnRollbackFailure(): void
    {
        $db = new EligStageMysqli();
        $records = [];
        for ($i = 0; $i < 50; $i++) {
            $records[] = [
                'id' => sprintf('%08x-0000-1000-8000-%012x', $i, $i),
                'games' => ['paper'],
                'lang' => 'en',
                'layout' => 'normal',
            ];
        }
        $db->failInsert = true;
        $db->failRollback = true;
        $stager = $this->createStager($db, null, $this->makeIterator($records), ScryfallCardEligibilityStager::STAGE_TABLE, 50);

        $result = $stager->stageSource(self::SAMPLE, 'all_cards');

        $this->assertContains('STAGE_INSERT_FAILED', $result['failure_reasons']);
        $this->assertContains('ROLLBACK_FAILED', $result['failure_reasons']);
    }

    public function testPreservesOriginalReasonOnCleanupFailure(): void
    {
        $db = new EligStageMysqli();
        $db->failCleanupOnDropCall = 2;
        $stager = $this->createStager($db);

        $result = $stager->stageSource(self::NO_ELIGIBLE, 'all_cards');

        $this->assertContains('EMPTY_ELIGIBLE_SET', $result['failure_reasons']);
        $this->assertContains('CLEANUP_FAILED', $result['failure_reasons']);
    }
}
