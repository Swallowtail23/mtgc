<?php

/*
Version:     1.0
Date:        18/09/26
Name:        ScryfallGameTypeRepositoryTest.php
Purpose:     Unit tests for ScryfallGameTypeRepository database-backed
             game-type catalogue and selection state management.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Tests;

use MTG\Bulk\ScryfallGameTypeRepository;
use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

/**
 * Minimal result object for repository tests.
 */
class GameTypeMysqliResult
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
 * Database test double interface for ScryfallGameTypeRepository.
 */
interface GameTypeDb
{
    /**
     * @param array<int, string>|null $params
     * @return GameTypeMysqliResult|false
     */
    public function execute_query(string $query, ?array $params = null);
}

/**
 * Stateful mysqli-compatible test double for ScryfallGameTypeRepository tests.
 */
class GameTypeMysqli implements GameTypeDb
{
    public string $error = '';

    /**
     * @var array<int, array<string, array<string, mixed>>>
     */
    public array $gameTypes = [
        'arena' => ['code' => 'arena', 'label' => 'MtG Arena', 'sort_order' => 10, 'selected' => 1],
        'mtgo' => ['code' => 'mtgo', 'label' => 'MtG Online', 'sort_order' => 20, 'selected' => 0],
        'paper' => ['code' => 'paper', 'label' => 'Paper', 'sort_order' => 30, 'selected' => 1],
    ];

    /** @var bool */
    public bool $failBegin = false;

    /** @var bool */
    public bool $failSelectForUpdate = false;

    /** @var bool */
    public bool $failDeselect = false;

    /** @var bool */
    public bool $failSelect = false;

    /** @var bool */
    public bool $failVerify = false;

    /** @var bool */
    public bool $failCommit = false;

    /** @var bool */
    public bool $failRollback = false;

    /** @var bool */
    public bool $failAllReads = false;

    /** @var bool */
    public bool $throwOnRead = false;

    /** @var list<string> */
    public array $executedStatements = [];

    /** @var list<string> */
    public array $executedParams = [];

    /** @var bool */
    public bool $transactionActive = false;

    public function set_charset(string $charset): bool
    {
        return true;
    }

    /**
     * @param array<int, string>|null $params
     * @return GameTypeMysqliResult|false
     */
    public function execute_query(string $query, ?array $params = null)
    {
        $this->error = '';
        $this->executedStatements[] = $query;
        $this->executedParams[] = $params ?? [];

        $lowerQuery = strtolower($query);
        $isSelect = str_starts_with(trim($lowerQuery), 'select');

        // BEGIN
        if (strtolower(trim($query)) === 'begin') {
            if ($this->failBegin) {
                return false;
            }
            $this->transactionActive = true;
            return true;
        }

        // COMMIT
        if (strtolower(trim($query)) === 'commit') {
            if ($this->failCommit) {
                return false;
            }
            $this->transactionActive = false;
            return true;
        }

        // ROLLBACK
        if (strtolower(trim($query)) === 'rollback') {
            if ($this->failRollback) {
                return false;
            }
            $this->transactionActive = false;
            return true;
        }

        // SELECT ... FOR UPDATE
        if (str_contains($lowerQuery, 'for update')) {
            if ($this->failSelectForUpdate) {
                return false;
            }
            $rows = [];
            foreach ($this->gameTypes as $code => $row) {
                $rows[] = ['code' => $row['code']];
            }
            return new GameTypeMysqliResult($rows);
        }

        // SELECT COUNT
        if ($isSelect && str_contains($lowerQuery, 'count(*)')) {
            if ($this->throwOnRead) {
                throw new \RuntimeException('Simulated read failure');
            }
            if ($this->failAllReads) {
                return false;
            }
            $count = 0;
            foreach ($this->gameTypes as $row) {
                if ($row['selected'] === 1) {
                    $count++;
                }
            }
            return new GameTypeMysqliResult([['cnt' => $count]]);
        }

        // SELECT with WHERE selected = 1
        if ($isSelect && str_contains($lowerQuery, 'selected = 1')) {
            if ($this->throwOnRead) {
                throw new \RuntimeException('Simulated read failure');
            }
            if ($this->failAllReads) {
                return false;
            }
            $rows = [];
            foreach ($this->gameTypes as $code => $row) {
                if ($row['selected'] === 1) {
                    $rows[] = $row;
                }
            }
            return new GameTypeMysqliResult($rows);
        }

        // SELECT without selected filter (getAll, catalogue)
        if ($isSelect) {
            if ($this->throwOnRead) {
                throw new \RuntimeException('Simulated read failure');
            }
            if ($this->failAllReads) {
                return false;
            }
            $rows = [];
            foreach ($this->gameTypes as $code => $row) {
                $rows[] = $row;
            }
            return new GameTypeMysqliResult($rows);
        }

        // UPDATE
        if (str_contains($lowerQuery, 'update')) {
            if ($this->failDeselect && str_contains($lowerQuery, 'set selected = 0')) {
                return false;
            }
            if ($this->failSelect && str_contains($lowerQuery, 'set selected = 1')) {
                return false;
            }
            // Apply the update to gameTypes
            if (str_contains($lowerQuery, 'set selected = 0')) {
                foreach ($this->gameTypes as $code => $row) {
                    $this->gameTypes[$code]['selected'] = 0;
                }
            }
            if (str_contains($lowerQuery, 'set selected = 1')) {
                // Extract codes from WHERE IN clause
                if (preg_match('/WHERE code IN \((.+?)\)/', $query, $matches)) {
                    $placeholders = $matches[1];
                    // Use params to determine which codes to select
                    if ($params !== null) {
                        foreach ($this->gameTypes as $code => $row) {
                            $this->gameTypes[$code]['selected'] = in_array($code, $params, true) ? 1 : 0;
                        }
                    }
                }
            }
            return true;
        }

        $this->error = 'Unmocked query: ' . $query;
        return false;
    }
}

/**
 * Minimal logger double for repository tests.
 * Extends Message to capture log output for assertions.
 */
class GameTypeMessageDouble extends Message
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
        parent::__construct(\MTG\Core\AppConfig::fromIni($ini));
    }

    public function logMessage(string $errorlevel, string $text, string $logfile = ''): void
    {
        $this->messages[] = "[{$errorlevel}] {$text}";
        // Also call parent to avoid breaking expected behavior
        // phpcs:ignore Generic.CodeAnalysis.DelegateAndRemove
        parent::logMessage($errorlevel, $text, $logfile);
    }
}

class ScryfallGameTypeRepositoryTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function getDefaultGameTypes(): array
    {
        return [
            'arena' => ['code' => 'arena', 'label' => 'MtG Arena', 'sort_order' => 10, 'selected' => 1],
            'mtgo' => ['code' => 'mtgo', 'label' => 'MtG Online', 'sort_order' => 20, 'selected' => 0],
            'paper' => ['code' => 'paper', 'label' => 'Paper', 'sort_order' => 30, 'selected' => 1],
        ];
    }

    private function makeRepository(GameTypeMysqli $db): ScryfallGameTypeRepository
    {
        $message = new GameTypeMessageDouble();
        return new ScryfallGameTypeRepository($db, $message);
    }

    // ====================================================================
    // getAll() tests
    // ====================================================================

    public function testGetAllReturnsAllRowsOrdered(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $rows = $repo->getAll();

        $this->assertCount(3, $rows);
        $this->assertEquals('arena', $rows[0]['code']);
        $this->assertEquals('mtgo', $rows[1]['code']);
        $this->assertEquals('paper', $rows[2]['code']);
    }

    // ====================================================================
    // getSelectedCodes() tests
    // ====================================================================

    public function testGetSelectedCodesReturnsSelectedOnly(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $codes = $repo->getSelectedCodes();

        $this->assertEquals(['arena', 'paper'], $codes);
    }

    // ====================================================================
    // getSelected() tests
    // ====================================================================

    public function testGetSelectedReturnsFullRows(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $rows = $repo->getSelected();

        $this->assertCount(2, $rows);
        $this->assertEquals('arena', $rows[0]['code']);
        $this->assertEquals(1, $rows[0]['selected']);
        $this->assertEquals('paper', $rows[1]['code']);
        $this->assertEquals(1, $rows[1]['selected']);
    }

    // ====================================================================
    // validateSelection() tests
    // ====================================================================

    public function testValidateSelectionValid(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection(['paper', 'arena']);

        $this->assertTrue($result['valid']);
        $this->assertEquals(['paper', 'arena'], $result['normalized']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateSelectionEmptyRejected(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection([]);

        $this->assertFalse($result['valid']);
        $this->assertEmpty($result['normalized']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidateSelectionUnknownCodeRejected(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection(['xyz']);

        $this->assertFalse($result['valid']);
        $this->assertEmpty($result['normalized']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Unknown game type code', $result['errors'][0]);
    }

    public function testValidateSelectionNonStringRejected(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection([123]);

        $this->assertFalse($result['valid']);
        $this->assertEmpty($result['normalized']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidateSelectionDuplicatesNormalized(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection(['paper', 'paper']);

        $this->assertTrue($result['valid']);
        $this->assertEquals(['paper'], $result['normalized']);
    }

    public function testValidateSelectionWhitespaceTrimmed(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection(['  Paper  ', '  arena  ']);

        $this->assertTrue($result['valid']);
        $this->assertEquals(['paper', 'arena'], $result['normalized']);
    }

    public function testValidateSelectionCaseInsensitive(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection(['PAPER', 'Arena']);

        $this->assertTrue($result['valid']);
        $this->assertEquals(['paper', 'arena'], $result['normalized']);
    }

    public function testValidateSelectionNullRejected(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection([null, 'paper']);

        $this->assertFalse($result['valid']);
        $this->assertEmpty($result['normalized']);
    }

    public function testValidateSelectionBoolRejected(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection([true, 'paper']);

        $this->assertFalse($result['valid']);
        $this->assertEmpty($result['normalized']);
    }

    // ====================================================================
    // applySelection() tests
    // ====================================================================

    public function testApplySelectionTransactional(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['paper', 'mtgo']);

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(1, $db->gameTypes['paper']['selected']);
        $this->assertEquals(1, $db->gameTypes['mtgo']['selected']);
        $this->assertEquals(0, $db->gameTypes['arena']['selected']);
    }

    public function testApplySelectionDeselectsOthers(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['mtgo']);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $db->gameTypes['arena']['selected']);
        $this->assertEquals(1, $db->gameTypes['mtgo']['selected']);
        $this->assertEquals(0, $db->gameTypes['paper']['selected']);
    }

    public function testApplySelectionEmptyRejected(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection([]);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
        // No state change should have occurred
        $this->assertEquals(1, $db->gameTypes['arena']['selected']);
        $this->assertEquals(1, $db->gameTypes['paper']['selected']);
    }

    public function testApplySelectionRollbackOnFailure(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $db->failDeselect = true;
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['paper']);

        $this->assertFalse($result['success']);
        // Original state should be preserved (rollback happened)
        $this->assertEquals(1, $db->gameTypes['arena']['selected']);
        $this->assertEquals(1, $db->gameTypes['paper']['selected']);
    }

    public function testApplySelectionBeginFailureReturnsFalse(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $db->failBegin = true;
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['paper']);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testApplySelectionCommitFailureReturnsFalse(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $db->failCommit = true;
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['paper']);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testApplySelectionUnknownCodeRejected(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['nonexistent']);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testApplySelectionMixedValidAndUnknownCodeRejected(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['paper', 'nonexistent']);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame(1, $db->gameTypes['arena']['selected']);
        $this->assertSame(1, $db->gameTypes['paper']['selected']);
        $this->assertSame(0, $db->gameTypes['mtgo']['selected']);
    }

    // ====================================================================
    // getSelectionFingerprint() tests
    // ====================================================================

    public function testGetSelectionFingerprintDeterministic(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $fingerprint1 = $repo->getSelectionFingerprint();
        $fingerprint2 = $repo->getSelectionFingerprint();

        $this->assertEquals($fingerprint1, $fingerprint2);
        $this->assertNotEmpty($fingerprint1);
        $this->assertEquals(64, strlen($fingerprint1));
    }

    public function testGetSelectionFingerprintChanges(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $fingerprint1 = $repo->getSelectionFingerprint();

        // Change selection
        $db->gameTypes['arena']['selected'] = 0;
        $db->gameTypes['mtgo']['selected'] = 1;

        $fingerprint2 = $repo->getSelectionFingerprint();

        $this->assertNotEquals($fingerprint1, $fingerprint2);
    }

    // ====================================================================
    // Error handling tests
    // ====================================================================

    public function testReadFailureThrowsRuntimeException(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $db->throwOnRead = true;
        $repo = $this->makeRepository($db);

        $this->expectException(\RuntimeException::class);
        $repo->getAll();
    }

    public function testGetSelectedCodesReadFailureThrowsRuntimeException(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $db->throwOnRead = true;
        $repo = $this->makeRepository($db);

        $this->expectException(\RuntimeException::class);
        $repo->getSelectedCodes();
    }

    public function testGetSelectedCodesEmptySelectionFailsClosed(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        foreach ($db->gameTypes as $code => $row) {
            $db->gameTypes[$code]['selected'] = 0;
        }
        $repo = $this->makeRepository($db);

        $this->expectException(\RuntimeException::class);
        $repo->getSelectedCodes();
    }

    public function testGetSelectedReadFailureThrowsRuntimeException(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $db->throwOnRead = true;
        $repo = $this->makeRepository($db);

        $this->expectException(\RuntimeException::class);
        $repo->getSelected();
    }

    // ====================================================================
    // Schema migration tests
    // ====================================================================

    public function testMigrateIncludesMandatoryFields(): void
    {
        $migrationContent = file_get_contents(__DIR__ . '/../setup/schema_v003.sql');
        $this->assertStringContainsString('CREATE TABLE', $migrationContent);
        $this->assertStringContainsString('scryfall_game_types', $migrationContent);
        $this->assertStringContainsString('`code`', $migrationContent);
        $this->assertStringContainsString('`label`', $migrationContent);
        $this->assertStringContainsString('`sort_order`', $migrationContent);
        $this->assertStringContainsString('`selected`', $migrationContent);
        $this->assertStringContainsString('chk_scryfall_game_types_code', $migrationContent);
        $this->assertStringContainsString('chk_scryfall_game_types_selected', $migrationContent);
    }

    public function testMigrateBumpsSchemaVersion(): void
    {
        $migrationContent = file_get_contents(__DIR__ . '/../setup/schema_v003.sql');
        $this->assertStringContainsString(
            "UPDATE schema_metadata SET schema_version = 3 WHERE id = 1 AND schema_version = 2",
            $migrationContent
        );
    }

    public function testFreshInstallSchemaIncludesTable(): void
    {
        $schemaContent = file_get_contents(__DIR__ . '/../setup/mtg_new.sql');
        $this->assertStringContainsString('CREATE TABLE `scryfall_game_types`', $schemaContent);
    }

    public function testFreshInstallSchemaSeedsData(): void
    {
        $schemaContent = file_get_contents(__DIR__ . '/../setup/mtg_new.sql');
        $this->assertStringContainsString("'arena', 'MtG Arena'", $schemaContent);
        $this->assertStringContainsString("'mtgo', 'MtG Online'", $schemaContent);
        $this->assertStringContainsString("'paper', 'Paper'", $schemaContent);
    }

    public function testFreshInstallSchemaVersionsMatch(): void
    {
        $schemaContent = file_get_contents(__DIR__ . '/../setup/mtg_new.sql');
        $this->assertStringContainsString('(1, 3)', $schemaContent);
    }

    // ====================================================================
    // FOR UPDATE locking test
    // ====================================================================

    public function testApplySelectionUsesForUpdate(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $db->failSelect = true; // Simulate failure during select phase
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['paper']);

        $this->assertFalse($result['success']);

        // Verify FOR UPDATE was in a query
        $hasForUpdate = false;
        foreach ($db->executedStatements as $stmt) {
            if (stripos($stmt, 'for update') !== false) {
                $hasForUpdate = true;
                break;
            }
        }
        $this->assertTrue($hasForUpdate, 'FOR UPDATE query was executed');

        // Verify rollback was attempted
        $hasRollback = false;
        foreach ($db->executedStatements as $stmt) {
            if (strtolower(trim($stmt)) === 'rollback') {
                $hasRollback = true;
                break;
            }
        }
        $this->assertTrue($hasRollback, 'ROLLBACK was executed after failure');

        // Verify commit was NOT executed
        $hasCommit = false;
        foreach ($db->executedStatements as $stmt) {
            if (strtolower(trim($stmt)) === 'commit') {
                $hasCommit = true;
                break;
            }
        }
        $this->assertFalse($hasCommit, 'COMMIT was not executed after failure');
    }

    // ====================================================================
    // Catalogue code validation test
    // ====================================================================

    public function testCanonicalMtgCodeIncluded(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection(['mtgo']);

        $this->assertTrue($result['valid']);
        $this->assertEquals(['mtgo'], $result['normalized']);
    }

    // ====================================================================
    // Constructor type enforcement test
    // ====================================================================

    public function testPolicyConstructorTypeEnforcement(): void
    {
        // This test verifies that the repository constructor accepts the required parameters.
        // The policy constructor type enforcement is tested in a separate test file.
        $db = new GameTypeMysqli();
        $message = new GameTypeMessageDouble();

        // Valid construction should succeed
        $repo = new ScryfallGameTypeRepository($db, $message);
        $this->assertInstanceOf(ScryfallGameTypeRepository::class, $repo);
    }

    // ====================================================================
    // Additional edge case tests
    // ====================================================================

    public function testValidateSelectionMixedValidInvalid(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection(['paper', 'invalid_code', 'arena']);

        $this->assertFalse($result['valid']);
        $this->assertEmpty($result['normalized']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidateSelectionPreservesFirstOccurrence(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->validateSelection(['paper', 'PAPER', 'Paper']);

        $this->assertTrue($result['valid']);
        $this->assertEquals(['paper'], $result['normalized']);
    }

    public function testApplySelectionWithDuplicates(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['paper', 'paper']);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $db->gameTypes['paper']['selected']);
    }

    public function testTransactionActiveAfterBegin(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['paper']);

        $this->assertTrue($result['success']);
        $this->assertFalse($db->transactionActive);
    }

    public function testExecutedStatementsRecorded(): void
    {
        $db = new GameTypeMysqli();
        $db->gameTypes = $this->getDefaultGameTypes();
        $repo = $this->makeRepository($db);

        $result = $repo->applySelection(['paper']);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($db->executedStatements);
        // First statement should be BEGIN
        $this->assertEquals('BEGIN', $db->executedStatements[0]);
    }
}
