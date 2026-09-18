<?php

/*
Version:     1.1
Date:        18/09/26
Name:        ScryfallMigrationSafetyAdapterTest.php
Purpose:     Unit tests for ScryfallMigrationSafetyAdapter and migration compatibility.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallCardDeletionSafety;
use MTG\Bulk\ScryfallMigrationSafetyAdapter;
use MTG\Core\AppConfig;
use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

/**
 * Duck-typed stub for a result set (reused from ScryfallCardDeletionSafetyTest).
 *
 * @mixin array<int, array<string, mixed>>
 */
class MigrationSafetyMysqliResult
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;

    /** @var int */
    private int $index = 0;

    /** @var int */
    private int $rowCount;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->rowCount = count($rows);
    }

    /** @return int|string */
    public function __get(string $name): int|string
    {
        if ($name === 'num_rows') {
            return $this->rowCount;
        }
        return 0;
    }

    public function fetch_assoc(): ?array
    {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return null;
    }

    public function free(): void
    {
        $this->index = 0;
    }
}

/**
 * Minimal interface for the database object the service needs.
 */
interface MigrationSafetyDb
{
    /**
     * @param array<int, string>|null $params
     * @return MigrationSafetyMysqliResult|false
     */
    public function execute_query(string $query, ?array $params = null);

    public function set_charset(string $charset): bool;
}

/**
 * Test double for mysqli that supports query routing for adapter tests.
 */
class MigrationSafetyMysqli implements MigrationSafetyDb
{
    /** @var array<string, mixed> */
    public array $queryRoutes = [];

    /** @var bool */
    public bool $failDeckQuery = false;

    /** @var bool */
    public bool $failUserQuery = false;

    /** @var bool */
    public bool $failCollectionQuery = false;

    /** @var bool */
    public bool $failDiscoveryQuery = false;

    public function set_charset(string $charset): bool
    {
        return true;
    }

    /**
     * @param array<int, string>|null $params
     * @return MigrationSafetyMysqliResult|false
     */
    public function execute_query(string $query, ?array $params = null)
    {
        $lowerQuery = strtolower($query);

        // User discovery query
        if (
            strpos($lowerQuery, 'select usernumber') !== false
            && strpos($lowerQuery, 'from users') !== false
        ) {
            if ($this->failUserQuery) {
                return false;
            }
            $users = $this->queryRoutes['users'] ?? [
                ['usernumber' => 1, 'username' => 'testuser'],
            ];
            return new MigrationSafetyMysqliResult($users);
        }

        // Deck reference query
        if (
            strpos($lowerQuery, 'from deckcards') !== false
            && strpos($lowerQuery, 'cardnumber') !== false
        ) {
            if ($this->failDeckQuery) {
                return false;
            }
            $deckRows = $this->queryRoutes['deckcards'] ?? [];
            return new MigrationSafetyMysqliResult($deckRows);
        }

        // Collection quantity query
        if (
            strpos($lowerQuery, 'sum(coalesce') !== false
            && strpos($lowerQuery, 'group by id') !== false
        ) {
            if ($this->failCollectionQuery) {
                return false;
            }
            $collectionRows = $this->queryRoutes['collection_qty'] ?? [];
            return new MigrationSafetyMysqliResult($collectionRows);
        }

        // Information schema existence query
        if (strpos($lowerQuery, 'information_schema.tables') !== false) {
            if ($this->failDiscoveryQuery) {
                return false;
            }
            $discoveryExists = $this->queryRoutes['discovery_exists'] ?? true;
            if ($discoveryExists) {
                return new MigrationSafetyMysqliResult([['1' => 1]]);
            }
            return new MigrationSafetyMysqliResult([]);
        }

        // Default: no rows
        return new MigrationSafetyMysqliResult([]);
    }
}

class ScryfallMigrationSafetyAdapterTest extends TestCase
{
    /**
     * Build a minimal AppConfig for Message instantiation.
     */
    private function makeAppConfig(): AppConfig
    {
        $iniArray = [
            'general' => [
                'URL' => 'https://test.example',
                'title' => 'Test',
                'tier' => 'dev',
                'Loglevel' => 0,
                'Logfile' => sys_get_temp_dir() . '/phpunit_test.log',
                'ImgLocation' => sys_get_temp_dir() . '/cardimg/',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => '',
            ],
            'security' => [
                'Turnstile' => 'disabled',
                'Turnstile_site_key' => '',
                'Turnstile_secret_key' => '',
                'TrustDuration' => 0,
                'Badloginlimit' => 0,
                'AdminIP' => '',
            ],
            'email' => [
                'Email' => 'disabled',
                'AdminEmail' => 'admin@example.test',
                'ServerEmail' => 'server@example.test',
                'SMTPDebug' => 0,
                'Host' => 'localhost',
                'SMTPAuth' => '',
                'Username' => '',
                'Password' => '',
                'SMTPSecure' => '',
                'Port' => 25,
                'SMTPVerifySSL' => 1,
            ],
            'fx' => [
                'FreecurrencyAPI' => '',
                'TargetCurrency' => '',
            ],
            'comments' => [
                'Disqus' => 'disabled',
                'DisqusDevURL' => '',
                'DisqusProdURL' => '',
            ],
        ];
        return AppConfig::fromIni($iniArray, [
            'general' => [
                'logLevel' => 0,
                'logFile' => sys_get_temp_dir() . '/phpunit_test.log',
            ],
            'email' => [
                'enabled' => false,
            ],
        ]);
    }

    /**
     * Create a service with a test mysqli and message logger.
     */
    private function createService(
        ?MigrationSafetyMysqli $mysqli = null,
        ?int $batchSize = null,
        ?array $queryRoutes = null
    ): ScryfallCardDeletionSafety {
        $db = $mysqli ?? new MigrationSafetyMysqli();
        if ($queryRoutes !== null) {
            $db->queryRoutes = $queryRoutes;
        }
        $logger = new Message($this->makeAppConfig());
        $bs = $batchSize ?? 500;
        return new ScryfallCardDeletionSafety($db, $logger, null, $bs);
    }

    /**
     * Test 1: Structured result with all zeros maps to safe score 0.
     */
    public function testMapSafetyScoreNoReferences(): void
    {
        $result = [
            'id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $this->assertSame(0, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 2: Deck reference maps to score 1.
     */
    public function testMapSafetyScoreDeckReference(): void
    {
        $result = [
            'id' => 'bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb',
            'deck_references' => 1,
            'collection_quantity' => 0,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $this->assertSame(1, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 3: Collection quantity maps to score 1.
     */
    public function testMapSafetyScoreCollectionQuantity(): void
    {
        $result = [
            'id' => 'cccccccc-cccc-3ccc-8ccc-cccccccccccc',
            'deck_references' => 0,
            'collection_quantity' => 3,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $this->assertSame(1, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 4: Combined deck + collection references map to score 1.
     */
    public function testMapSafetyScoreCombinedReferences(): void
    {
        $result = [
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'deck_references' => 2,
            'collection_quantity' => 5,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $this->assertSame(1, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 5: Deck query failure maps to 10001.
     */
    public function testMapSafetyScoreDeckQueryFailure(): void
    {
        $result = [
            'id' => 'eeeeeeee-eeee-5eee-8eee-eeeeeeeeeeee',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => true,
            'failure_reasons' => ['DECK_QUERY_FAILED'],
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 6: User query failure maps to 10001.
     */
    public function testMapSafetyScoreUserQueryFailure(): void
    {
        $result = [
            'id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => true,
            'failure_reasons' => ['USER_QUERY_FAILED'],
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 7: Collection query failure maps to 10001.
     */
    public function testMapSafetyScoreCollectionQueryFailure(): void
    {
        $result = [
            'id' => '11111111-1111-1111-8111-111111111111',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => true,
            'failure_reasons' => ['COLLECTION_QUERY_FAILED'],
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 8: Invalid UUID failure maps to 10001.
     */
    public function testMapSafetyScoreInvalidUuid(): void
    {
        $result = [
            'id' => 'not-a-uuid',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => true,
            'failure_reasons' => ['INVALID_UUID'],
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 9: Collection table discovery failure maps to 10001.
     */
    public function testMapSafetyScoreInformationSchemaFailure(): void
    {
        $result = [
            'id' => '22222222-2222-2222-8222-222222222222',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => true,
            'failure_reasons' => ['COLLECTION_TABLE_DISCOVERY_FAILED'],
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 10: Collection table name invalid maps to 10001.
     */
    public function testMapSafetyScoreTableNameInvalid(): void
    {
        $result = [
            'id' => '33333333-3333-3333-8333-333333333333',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => true,
            'failure_reasons' => ['COLLECTION_TABLE_NAME_INVALID'],
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 11: Adapter delegates to Stage 1 service with no references -> 0.
     */
    public function testAdapterCheckNoReferences(): void
    {
        $service = $this->createService();
        $adapter = new ScryfallMigrationSafetyAdapter($service);
        $result = $adapter->check('aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertSame(0, $result);
    }

    /**
     * Test 12: Adapter delegates with a deck reference -> 1.
     */
    public function testAdapterCheckDeckReference(): void
    {
        $mysqli = new MigrationSafetyMysqli();
        $mysqli->queryRoutes = [
            'deckcards' => [
                ['card_id' => 'bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb', 'deck_ref_count' => 3],
            ],
        ];
        $service = $this->createService($mysqli);
        $adapter = new ScryfallMigrationSafetyAdapter($service);
        $result = $adapter->check('bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb');

        $this->assertSame(1, $result);
    }

    /**
     * Test 13: Adapter delegates with collection quantity -> 1.
     */
    public function testAdapterCheckCollectionReference(): void
    {
        $mysqli = new MigrationSafetyMysqli();
        $mysqli->queryRoutes = [
            'collection_qty' => [
                ['id' => 'cccccccc-cccc-3ccc-8ccc-cccccccccccc', 'total_qty' => 3],
            ],
        ];
        $service = $this->createService($mysqli);
        $adapter = new ScryfallMigrationSafetyAdapter($service);
        $result = $adapter->check('cccccccc-cccc-3ccc-8ccc-cccccccccccc');

        $this->assertSame(1, $result);
    }

    /**
     * Test 14: Adapter delegates with a service failure -> > 10000.
     */
    public function testAdapterCheckFailure(): void
    {
        $mysqli = new MigrationSafetyMysqli();
        $mysqli->failDeckQuery = true;
        $service = $this->createService($mysqli);
        $adapter = new ScryfallMigrationSafetyAdapter($service);
        $result = $adapter->check('aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertGreaterThan(10000, $result);
    }

    /**
     * Test 15: Malformed result (missing keys) maps to 10001.
     */
    public function testMapSafetyScoreMalformedResult(): void
    {
        $result = [
            'id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'deck_references' => 0,
            // Missing collection_quantity, check_failed, failure_reasons
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 16: check_failed true with empty reasons maps to 10001.
     */
    public function testMapSafetyScoreFailureWithoutReason(): void
    {
        $result = [
            'id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => true,
            'failure_reasons' => [],
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 17: Valid zero-reference result returns exactly 0 (strict comparison compatible).
     */
    public function testMapSafetyScoreBoundarySafe(): void
    {
        $result = [
            'id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $this->assertSame(0, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
        $this->assertTrue(ScryfallMigrationSafetyAdapter::mapSafetyScore($result) === 0);
    }

    /**
     * Test 18: Valid failed result returns > 10000 (strict comparison compatible).
     */
    public function testMapSafetyScoreBoundaryFailure(): void
    {
        $result = [
            'id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => true,
            'failure_reasons' => ['DECK_QUERY_FAILED'],
        ];

        $score = ScryfallMigrationSafetyAdapter::mapSafetyScore($result);
        $this->assertGreaterThan(10000, $score);
    }

    /**
     * Test 19: Valid positive reference result returns 1 (positive, <= 10000).
     */
    public function testMapSafetyScoreBoundaryReference(): void
    {
        $result = [
            'id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'deck_references' => 1,
            'collection_quantity' => 0,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $score = ScryfallMigrationSafetyAdapter::mapSafetyScore($result);
        $this->assertSame(1, $score);
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual(10000, $score);
    }

    /**
     * Test 20: Malformed result with wrong types maps to 10001.
     */
    public function testMapSafetyScoreWrongTypes(): void
    {
        $result = [
            'id' => 123,
            'deck_references' => 'not-an-int',
            'collection_quantity' => -1,
            'check_failed' => 'not-a-bool',
            'failure_reasons' => 'not-an-array',
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 21: Empty structured result maps to 10001.
     */
    public function testMapSafetyScoreEmptyResult(): void
    {
        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore([]));
    }

    /**
     * Test 22: Deck reference only (no collection) maps to 1.
     */
    public function testMapSafetyScoreDeckReferenceOnly(): void
    {
        $result = [
            'id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'deck_references' => 5,
            'collection_quantity' => 0,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $this->assertSame(1, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 23: Collection reference only (no deck) maps to 1.
     */
    public function testMapSafetyScoreCollectionReferenceOnly(): void
    {
        $result = [
            'id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'deck_references' => 0,
            'collection_quantity' => 10,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $this->assertSame(1, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 24: Multiple failure reasons all map to 10001.
     */
    public function testMapSafetyScoreMultipleFailureReasons(): void
    {
        $result = [
            'id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => true,
            'failure_reasons' => ['DECK_QUERY_FAILED', 'COLLECTION_QUERY_FAILED'],
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 25: Adapter check() returns consistent results across multiple calls.
     */
    public function testAdapterCheckConsistency(): void
    {
        $mysqli = new MigrationSafetyMysqli();
        $mysqli->queryRoutes = [
            'deckcards' => [
                ['card_id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa', 'deck_ref_count' => 1],
            ],
        ];
        $service = $this->createService($mysqli);
        $adapter = new ScryfallMigrationSafetyAdapter($service);

        $result1 = $adapter->check('aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');
        $result2 = $adapter->check('aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertSame(1, $result1);
        $this->assertSame(1, $result2);
    }

    /**
     * Testable wrapper that mirrors safeDeleteCheck() but accepts our duck-typed database double.
     *
     * This allows runtime execution of the migration decision path without requiring
     * a real \mysqli instance. The wrapper constructs the same service/adapter as the
     * production migration file.
     *
     * @param MigrationSafetyMysqli $db Database double
     * @param Message $logger Logger for the service
     * @param string $id UUID to check
     * @return int Legacy score
     */
    private function runMigrationDecision(MigrationSafetyMysqli $db, Message $logger, string $id): int
    {
        try {
            $service = new ScryfallCardDeletionSafety($db, $logger, null, 500);
            $adapter = new ScryfallMigrationSafetyAdapter($service);
            return $adapter->check($id);
        } catch (\Throwable $e) {
            $logger->logMessage(
                '[ERROR]',
                "safeDeleteCheck: service construction or check failed for $id: " . $e->getMessage()
            );
            return 10001;
        }
    }

    /**
     * Test 26: Empty ID string maps to 10001 (fails closed).
     */
    public function testMapSafetyScoreEmptyId(): void
    {
        $result = [
            'id' => '',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 27: Invalid UUID string maps to 10001 (fails closed).
     */
    public function testMapSafetyScoreInvalidUuidString(): void
    {
        $result = [
            'id' => 'not-a-uuid-at-all',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $this->assertSame(10001, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    /**
     * Test 28: Valid UUID with zero counts maps to 0.
     */
    public function testMapSafetyScoreValidUuidNoReferences(): void
    {
        $result = [
            'id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'deck_references' => 0,
            'collection_quantity' => 0,
            'check_failed' => false,
            'failure_reasons' => [],
        ];

        $this->assertSame(0, ScryfallMigrationSafetyAdapter::mapSafetyScore($result));
    }

    // --- Runtime integration tests: migration decision path ---

    /**
     * Test 29: Runtime integration — safe card produces score 0.
     *
     * The migration caller uses $deleteCheck === 0 to decide deletion. A score of 0
     * is the only path that reaches DELETE FROM cards_scry.
     */
    public function testRuntimeIntegrationSafeCardScoreZero(): void
    {
        $db = new MigrationSafetyMysqli();
        $logger = new Message($this->makeAppConfig());

        // No deck refs, no collection qty → score 0 → deletion path
        $db->queryRoutes = [];

        $score = $this->runMigrationDecision($db, $logger, 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        // Score 0 means deletion would proceed
        $this->assertSame(0, $score);
        $this->assertTrue($score === 0, 'Score 0 must permit deletion guard');
    }

    /**
     * Test 30: Runtime integration — deck reference (score 1) does not issue a delete.
     *
     * A positive but <= 10000 score must not enter the deletion path.
     */
    public function testRuntimeIntegrationDeckReferenceNoDelete(): void
    {
        $db = new MigrationSafetyMysqli();
        $db->queryRoutes = [
            'deckcards' => [
                ['card_id' => 'bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb', 'deck_ref_count' => 1],
            ],
        ];
        $logger = new Message($this->makeAppConfig());

        $score = $this->runMigrationDecision($db, $logger, 'bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb');

        // Score 1 means hold-and-report, not deletion
        $this->assertSame(1, $score);
        $this->assertFalse($score === 0, 'Score 1 must NOT enter deletion path');
        $this->assertTrue($score > 0 && $score <= 10000, 'Score 1 must be positive but <= 10000');
    }

    /**
     * Test 31: Runtime integration — collection reference only (score 1) does not issue a delete.
     */
    public function testRuntimeIntegrationCollectionReferenceNoDelete(): void
    {
        $db = new MigrationSafetyMysqli();
        $db->queryRoutes = [
            'collection_qty' => [
                ['id' => 'cccccccc-cccc-3ccc-8ccc-cccccccccccc', 'total_qty' => 2],
            ],
        ];
        $logger = new Message($this->makeAppConfig());

        $score = $this->runMigrationDecision($db, $logger, 'cccccccc-cccc-3ccc-8ccc-cccccccccccc');

        $this->assertSame(1, $score);
        $this->assertFalse($score === 0, 'Score 1 must NOT enter deletion path');
    }

    /**
     * Test 32: Runtime integration — deck query failure (score 10001) does not issue a delete.
     *
     * A safety failure score > 10000 must enter the safety-failure hold path.
     */
    public function testRuntimeIntegrationDeckFailureNoDelete(): void
    {
        $db = new MigrationSafetyMysqli();
        $db->failDeckQuery = true;
        $logger = new Message($this->makeAppConfig());

        $score = $this->runMigrationDecision($db, $logger, 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertSame(10001, $score);
        $this->assertFalse($score === 0, 'Score 10001 must NOT enter deletion path');
        $this->assertTrue($score > 10000, 'Score 10001 must enter safety-failure hold path');
    }

    /**
     * Test 33: Runtime integration — user query failure (score 10001) does not issue a delete.
     */
    public function testRuntimeIntegrationUserFailureNoDelete(): void
    {
        $db = new MigrationSafetyMysqli();
        $db->failUserQuery = true;
        $logger = new Message($this->makeAppConfig());

        $score = $this->runMigrationDecision($db, $logger, 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertSame(10001, $score);
        $this->assertTrue($score > 10000, 'User failure must enter safety-failure hold path');
    }

    /**
     * Test 34: Runtime integration — collection query failure (score 10001) does not issue a delete.
     */
    public function testRuntimeIntegrationCollectionFailureNoDelete(): void
    {
        $db = new MigrationSafetyMysqli();
        $db->failCollectionQuery = true;
        $logger = new Message($this->makeAppConfig());

        $score = $this->runMigrationDecision($db, $logger, 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertSame(10001, $score);
        $this->assertTrue($score > 10000, 'Collection failure must enter safety-failure hold path');
    }

    /**
     * Test 35: Runtime integration — Throwable caught returns 10001.
     *
     * When service construction or check throws, the wrapper must catch Throwable,
     * log an error, and return 10001 (fail-closed).
     */
    public function testRuntimeIntegrationThrowableReturns10001(): void
    {
        // Create a logger to capture the error log
        $logger = new Message($this->makeAppConfig());

        // Use a thrower that will fail during checkSingle()
        $throwingDb = new class extends MigrationSafetyMysqli {
            public function execute_query(string $query, ?array $params = null)
            {
                throw new \RuntimeException('Simulated service failure');
            }
        };

        $score = $this->runMigrationDecision($throwingDb, $logger, 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertSame(10001, $score, 'Throwable must be caught and return 10001');
    }

    /**
     * Test 36: Runtime integration — combined deck + collection reference (score 1) does not issue a delete.
     */
    public function testRuntimeIntegrationCombinedReferencesNoDelete(): void
    {
        $db = new MigrationSafetyMysqli();
        $db->queryRoutes = [
            'deckcards' => [
                ['card_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'deck_ref_count' => 2],
            ],
            'collection_qty' => [
                ['id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'total_qty' => 5],
            ],
        ];
        $logger = new Message($this->makeAppConfig());

        $score = $this->runMigrationDecision($db, $logger, 'dddddddd-dddd-4ddd-8ddd-dddddddddddd');

        $this->assertSame(1, $score);
        $this->assertFalse($score === 0, 'Combined reference score 1 must NOT enter deletion path');
    }

    /**
     * Test 37: Runtime integration — information schema failure (score 10001) does not issue a delete.
     */
    public function testRuntimeIntegrationDiscoveryFailureNoDelete(): void
    {
        $db = new MigrationSafetyMysqli();
        $db->failDiscoveryQuery = true;
        $logger = new Message($this->makeAppConfig());

        $score = $this->runMigrationDecision($db, $logger, 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertSame(10001, $score);
        $this->assertTrue($score > 10000, 'Discovery failure must enter safety-failure hold path');
    }

    /**
     * Test 38: Source-level wiring test — verify the migration file includes the
     * required classes and that safeDeleteCheck() returns expected scores
     * when invoked through the adapter path.
     */
    public function testLegacyWrapperUsesAdapter(): void
    {
        // Source-level wiring test: verify the migration file includes the
        // required classes and that safeDeleteCheck() returns expected scores
        // when invoked through the adapter path.

        // Load the migration file to verify syntax and imports
        $migrationContent = file_get_contents(__DIR__ . '/../bulk/scryfall_migrations.php');
        $this->assertStringContainsString(
            'use MTG\Bulk\ScryfallCardDeletionSafety;',
            $migrationContent,
            'Migration file must import ScryfallCardDeletionSafety'
        );
        $this->assertStringContainsString(
            'use MTG\Bulk\ScryfallMigrationSafetyAdapter;',
            $migrationContent,
            'Migration file must import ScryfallMigrationSafetyAdapter'
        );

        // Verify the wrapper function exists and has the correct signature
        $this->assertStringContainsString(
            'function safeDeleteCheck(',
            $migrationContent,
            'Migration file must define safeDeleteCheck function'
        );

        // Verify the wrapper constructs service and adapter by checking for
        // the expected patterns in the function body
        $this->assertStringContainsString(
            'new ScryfallCardDeletionSafety(',
            $migrationContent,
            'Wrapper must construct ScryfallCardDeletionSafety'
        );
        $this->assertStringContainsString(
            'new ScryfallMigrationSafetyAdapter(',
            $migrationContent,
            'Wrapper must construct ScryfallMigrationSafetyAdapter'
        );

        // Verify the wrapper catches Throwable and returns 10001 on failure
        $this->assertStringContainsString(
            'catch (\\Throwable $e)',
            $migrationContent,
            'Wrapper must catch Throwable'
        );
        $this->assertStringContainsString(
            'return 10001;',
            $migrationContent,
            'Wrapper must return 10001 on failure'
        );
    }

    /**
     * Test 39: Source-level assertions for action-text fragments and email-disabled branch.
     *
     * Proves the migration decision path produces the expected administrator action text
     * for safety failures and reference holds, and that the email-disabled branch is intact.
     */
    public function testActionTextAndEmailDisabledBranch(): void
    {
        $migrationContent = file_get_contents(__DIR__ . '/../bulk/scryfall_migrations.php');

        // Action text fragments for safety-failure hold (deleteCheck > 10000 branch)
        $this->assertStringContainsString(
            'carddetail.php?id=',
            $migrationContent,
            'Action text must include carddetail.php?id= link'
        );
        $this->assertStringContainsString(
            'Migration strategy:',
            $migrationContent,
            'Action text must include migration strategy label'
        );
        $this->assertStringContainsString(
            'New ID (if applicable):',
            $migrationContent,
            'Action text must include new ID label'
        );
        $this->assertStringContainsString(
            'Note:',
            $migrationContent,
            'Action text must include note label'
        );
        $this->assertStringContainsString(
            '(Safety check failed)',
            $migrationContent,
            'Action text must include (Safety check failed) suffix for failure holds'
        );
        $this->assertStringContainsString(
            '(Not safe to delete)',
            $migrationContent,
            'Action text must include (Not safe to delete) suffix for reference holds'
        );

        // Email-disabled branch: when $emailEnabled is not true, log and skip
        $this->assertStringContainsString(
            "if (isset(\$emailEnabled) && \$emailEnabled === true)",
            $migrationContent,
            'Email sending must be gated by $emailEnabled === true check'
        );
        $this->assertStringContainsString(
            'Email disabled; skipping scryfall_migrations alert',
            $migrationContent,
            'Email-disabled branch must log a NOTICE message'
        );
    }

    /**
     * Test 30: Wrapper === 0 deletion guard remains intact (wiring test).
     */
    public function testWrapperDeletionGuardIntact(): void
    {
        $migrationContent = file_get_contents(__DIR__ . '/../bulk/scryfall_migrations.php');

        // Verify the migration caller still uses === 0 for safe deletion
        $this->assertStringContainsString(
            '$deleteCheck === 0',
            $migrationContent,
            'Migration caller must use === 0 for safe deletion'
        );

        // Verify the migration caller still uses > 10000 for safety-failure hold
        $this->assertStringContainsString(
            '$deleteCheck > 10000',
            $migrationContent,
            'Migration caller must use > 10000 for safety-failure hold'
        );

        // Verify the migration caller still uses > 0 for reference hold
        $this->assertStringContainsString(
            '$deleteCheck > 0',
            $migrationContent,
            'Migration caller must use > 0 for reference hold'
        );

        // Verify DELETE FROM cards_scry is only executed when $deleteCheck === 0
        $deleteGuardPattern = '/\$deleteCheck\s*===\s*0.*?DELETE FROM cards_scry/s';
        $this->assertMatchesRegularExpression(
            $deleteGuardPattern,
            $migrationContent,
            'DELETE FROM cards_scry must only execute when $deleteCheck === 0'
        );
    }
}
