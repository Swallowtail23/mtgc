<?php

/*
Version:     1.0
Date:        18/09/26
Name:        ScryfallCardDeletionSafetyTest.php
Purpose:     Unit tests for ScryfallCardDeletionSafety service.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallCardDeletionSafety;
use MTG\Core\AppConfig;
use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

/**
 * Duck-typed stub for a result set.
 *
 * @mixin array<int, array<string, mixed>>
 */
class ScryfallSafetyMysqliResult
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
interface ScryfallSafetyDb
{
    /**
     * @param array<int, string>|null $params
     * @return ScryfallSafetyMysqliResult|false
     */
    public function execute_query(string $query, ?array $params = null);

    public function set_charset(string $charset): bool;
}

/**
 * Test double for mysqli that supports query routing for ScryfallCardDeletionSafety tests.
 *
 * Uses composition instead of inheritance to avoid PHP 8.2+ read-only property
 * conflicts with mysqli::execute_query() return type.
 */
class ScryfallSafetyMysqli implements ScryfallSafetyDb
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

    /** @var array<int, array{query: string, params?: array<int, string>|null}> */
    public array $recordedCalls = [];

    public function set_charset(string $charset): bool
    {
        return true;
    }

    /**
     * @param array<int, string>|null $params
     * @return ScryfallSafetyMysqliResult|false
     */
    public function execute_query(string $query, ?array $params = null)
    {
        // Record every call for batch-splitting verification
        $this->recordedCalls[] = ['query' => $query, 'params' => $params];

        $lowerQuery = strtolower($query);

        // User discovery query
        if (
            strpos($lowerQuery, 'select usernumber') !== false
            && strpos($lowerQuery, 'from users') !== false
        ) {
            if ($this->failUserQuery) {
                return false;
            }
            $routes = $this->queryRoutes;
            $users = $routes['users'] ?? [
                ['usernumber' => 1, 'username' => 'testuser'],
            ];
            return new ScryfallSafetyMysqliResult($users);
        }

        // Deck reference query
        if (
            strpos($lowerQuery, 'from deckcards') !== false
            && strpos($lowerQuery, 'cardnumber') !== false
        ) {
            if ($this->failDeckQuery) {
                return false;
            }
            $routes = $this->queryRoutes;
            $deckRows = $routes['deckcards'] ?? [];
            return new ScryfallSafetyMysqliResult($deckRows);
        }

        // Collection quantity query (contains SUM(COALESCE and GROUP BY id)
        if (
            strpos($lowerQuery, 'sum(coalesce') !== false
            && strpos($lowerQuery, 'group by id') !== false
        ) {
            if ($this->failCollectionQuery) {
                return false;
            }
            $routes = $this->queryRoutes;
            $collectionRows = $routes['collection_qty'] ?? [];
            return new ScryfallSafetyMysqliResult($collectionRows);
        }

        // Information schema existence query
        if (strpos($lowerQuery, 'information_schema.tables') !== false) {
            if ($this->failDiscoveryQuery) {
                return false;
            }
            $routes = $this->queryRoutes;
            $discoveryExists = $routes['discovery_exists'] ?? true;
            if ($discoveryExists) {
                return new ScryfallSafetyMysqliResult([['1' => 1]]);
            }
            return new ScryfallSafetyMysqliResult([]);
        }

        // Default: no rows
        return new ScryfallSafetyMysqliResult([]);
    }
}

class ScryfallCardDeletionSafetyTest extends TestCase
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
     * Create a service with a test mysqli, message logger, and optional validator.
     *
     * @param array<string, mixed>|null $queryRoutes
     * @param callable(mixed): (string|false)|null $validator
     */
    private function createService(
        ?ScryfallSafetyMysqli $mysqli = null,
        ?callable $validator = null,
        ?int $batchSize = null,
        ?array $queryRoutes = null
    ): ScryfallCardDeletionSafety {
        $db = $mysqli ?? new ScryfallSafetyMysqli();
        if ($queryRoutes !== null) {
            $db->queryRoutes = $queryRoutes;
        }
        $logger = new Message($this->makeAppConfig());
        $bs = $batchSize ?? 500;
        return new ScryfallCardDeletionSafety($db, $logger, $validator, $bs);
    }

    /**
     * Test 1: A candidate with no deck or collection references is deletable.
     */
    public function testCheckSingleNoReferences(): void
    {
        $service = $this->createService();
        $result = $service->checkSingle('aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertFalse($result['check_failed']);
        $this->assertSame(0, $result['deck_references']);
        $this->assertSame(0, $result['collection_quantity']);
        $this->assertSame([], $result['failure_reasons']);
    }

    /**
     * Test 2: A candidate with one or more deck rows is retained.
     */
    public function testCheckSingleDeckReference(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [
            'deckcards' => [
                ['card_id' => 'bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb', 'deck_ref_count' => 3],
            ],
        ];
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb');

        $this->assertFalse($result['check_failed']);
        $this->assertSame(3, $result['deck_references']);
        $this->assertSame(0, $result['collection_quantity']);
    }

    /**
     * Test 3: A candidate with positive normal, foil, or etched collection quantity is retained.
     */
    public function testCheckSinglePositiveCollectionQuantity(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [
            'collection_qty' => [
                ['id' => 'cccccccc-cccc-3ccc-8ccc-cccccccccccc', 'total_qty' => 3],
            ],
        ];
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('cccccccc-cccc-3ccc-8ccc-cccccccccccc');

        $this->assertFalse($result['check_failed']);
        $this->assertSame(0, $result['deck_references']);
        $this->assertSame(3, $result['collection_quantity']);
    }

    /**
     * Test 4: Collection rows whose total quantity is zero do not block deletion.
     */
    public function testCheckSingleZeroCollectionQuantity(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [
            'collection_qty' => [
                ['id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'total_qty' => 0],
            ],
        ];
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('dddddddd-dddd-4ddd-8ddd-dddddddddddd');

        $this->assertFalse($result['check_failed']);
        $this->assertSame(0, $result['collection_quantity']);
    }

    /**
     * Test 5: Missing collection tables are handled as the current migration process handles them.
     */
    public function testCheckSingleMissingCollectionTable(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [
            'discovery_exists' => false,
        ];
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('eeeeeeee-eeee-5eee-8eee-eeeeeeeeeeee');

        $this->assertFalse($result['check_failed']);
        $this->assertSame(0, $result['collection_quantity']);
    }

    /**
     * Test 6: A failed deck query blocks deletion and is reported as a safety-check failure.
     */
    public function testCheckSingleDeckQueryFailure(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->failDeckQuery = true;
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('ffffffff-ffff-4fff-8fff-ffffffffffff');

        $this->assertTrue($result['check_failed']);
        $this->assertContains('DECK_QUERY_FAILED', $result['failure_reasons']);
    }

    /**
     * Test 7: A failed user lookup or collection query blocks deletion and is reported as a safety-check failure.
     */
    public function testCheckSingleUserQueryFailure(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->failUserQuery = true;
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('11111111-1111-1111-8111-111111111111');

        $this->assertTrue($result['check_failed']);
        $this->assertContains('USER_QUERY_FAILED', $result['failure_reasons']);
    }

    /**
     * Test 8: A failed collection query blocks deletion and is reported as a safety-check failure.
     */
    public function testCheckSingleCollectionQueryFailure(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->failCollectionQuery = true;
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('22222222-2222-2222-8222-222222222222');

        $this->assertTrue($result['check_failed']);
        $this->assertContains('COLLECTION_QUERY_FAILED', $result['failure_reasons']);
    }

    /**
     * Test 9: Multiple candidates are processed in batches without cross-contaminating results.
     */
    public function testCheckBatchNoCrossContamination(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [
            'deckcards' => [
                ['card_id' => '33333333-3333-3333-8333-333333333333', 'deck_ref_count' => 2],
            ],
        ];
        $service = $this->createService($mysqli);
        $results = $service->checkBatch([
            '33333333-3333-3333-8333-333333333333',
            '44444444-4444-4444-8444-444444444444',
        ]);

        $this->assertSame(2, $results['33333333-3333-3333-8333-333333333333']['deck_references']);
        $this->assertSame(0, $results['33333333-3333-3333-8333-333333333333']['collection_quantity']);
        $this->assertFalse($results['33333333-3333-3333-8333-333333333333']['check_failed']);

        $this->assertSame(0, $results['44444444-4444-4444-8444-444444444444']['deck_references']);
        $this->assertFalse($results['44444444-4444-4444-8444-444444444444']['check_failed']);
    }

    /**
     * Test 10: Invalid UUIDs are rejected or counted without entering the staging table.
     */
    public function testCheckSingleInvalidUuid(): void
    {
        $service = $this->createService();
        $result = $service->checkSingle('not-a-uuid');

        $this->assertTrue($result['check_failed']);
        $this->assertSame(['INVALID_UUID'], $result['failure_reasons']);
    }

    /**
     * Test 11: Empty input returns an empty array, no queries issued.
     */
    public function testCheckBatchEmptyInput(): void
    {
        $service = $this->createService();
        $results = $service->checkBatch([]);

        $this->assertSame([], $results);
    }

    /**
     * Test 12: Three candidates, mixed results, all returned correctly.
     */
    public function testCheckBatchMultipleCandidates(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [
            'deckcards' => [
                ['card_id' => '55555555-5555-5555-8555-555555555555', 'deck_ref_count' => 1],
            ],
            'collection_qty' => [
                ['id' => '66666666-6666-5666-8666-666666666666', 'total_qty' => 2],
            ],
        ];
        $service = $this->createService($mysqli);
        $results = $service->checkBatch([
            '55555555-5555-5555-8555-555555555555',
            '66666666-6666-5666-8666-666666666666',
            '77777777-7777-4777-8777-777777777777',
        ]);

        $this->assertSame(1, $results['55555555-5555-5555-8555-555555555555']['deck_references']);
        $this->assertSame(2, $results['66666666-6666-5666-8666-666666666666']['collection_quantity']);
        $this->assertFalse($results['77777777-7777-4777-8777-777777777777']['check_failed']);
        $this->assertSame(0, $results['77777777-7777-4777-8777-777777777777']['deck_references']);
        $this->assertSame(0, $results['77777777-7777-4777-8777-777777777777']['collection_quantity']);
    }

    /**
     * Test 13: Dynamic collection table names are validated before SQL identifiers are quoted.
     */
    public function testTableNameValidationCalled(): void
    {
        $recordedNames = [];
        $validator = function ($input) use (&$recordedNames) {
            $recordedNames[] = $input;
            // Same logic as Validation::validTableName
            if (is_string($input) && preg_match('/^\d+collection$/', $input)) {
                return $input;
            }
            return false;
        };

        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [];
        $service = $this->createService($mysqli, $validator);
        $service->checkSingle('aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertContains('1collection', $recordedNames);
    }

    /**
     * Test 14: A candidate with positive etched collection quantity is retained.
     */
    public function testCheckSinglePositiveEtchedQuantity(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [
            'collection_qty' => [
                ['id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa', 'total_qty' => 5],
            ],
        ];
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertFalse($result['check_failed']);
        $this->assertSame(5, $result['collection_quantity']);
    }

    /**
     * Test 15: A card referenced by both decks and collections produces one consolidated report entry.
     */
    public function testCheckSingleCombinedReferences(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [
            'deckcards' => [
                ['card_id' => 'bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb', 'deck_ref_count' => 1],
            ],
            'collection_qty' => [
                ['id' => 'bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb', 'total_qty' => 3],
            ],
        ];
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb');

        $this->assertFalse($result['check_failed']);
        $this->assertGreaterThan(0, $result['deck_references']);
        $this->assertGreaterThan(0, $result['collection_quantity']);
    }

    /**
     * Test 16: Table-existence query returns false -> COLLECTION_TABLE_DISCOVERY_FAILED.
     */
    public function testCheckSingleInformationSchemaFailure(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->failDiscoveryQuery = true;
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertTrue($result['check_failed']);
        $this->assertContains('COLLECTION_TABLE_DISCOVERY_FAILED', $result['failure_reasons']);
    }

    /**
     * Test 17: Orphaned deckcards rows are treated as references rather than ignored.
     */
    public function testCheckSingleOrphanedDeckcardReference(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        // deckcards query does NOT join decks; it counts all deckcards rows
        $mysqli->queryRoutes = [
            'deckcards' => [
                ['card_id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa', 'deck_ref_count' => 1],
            ],
        ];
        $service = $this->createService($mysqli);
        $result = $service->checkSingle('aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertFalse($result['check_failed']);
        $this->assertGreaterThan(0, $result['deck_references']);
    }

    /**
     * Test 18: Construct with batch size 0 throws InvalidArgumentException.
     */
    public function testConstructorRejectsInvalidBatchSize(): void
    {
        $logger = new Message($this->makeAppConfig());
        $db = new ScryfallSafetyMysqli();

        $this->expectException(\InvalidArgumentException::class);
        new ScryfallCardDeletionSafety($db, $logger, null, 0);
    }

    /**
     * Test 19: Three valid UUIDs with batch size 2 -> two batches, all results returned.
     */
    public function testCheckBatchSplitsAtConfiguredBatchSize(): void
    {
        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [
            'deckcards' => [
                ['card_id' => 'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa', 'deck_ref_count' => 1],
            ],
        ];
        $service = $this->createService($mysqli, null, 2);
        $results = $service->checkBatch([
            'aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa',
            'bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb',
            'cccccccc-cccc-3ccc-8ccc-cccccccccccc',
        ]);

        $this->assertCount(3, $results);
        $this->assertSame(1, $results['aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa']['deck_references']);
        $this->assertFalse($results['bbbbbbbb-bbbb-2bbb-8bbb-bbbbbbbbbbbb']['check_failed']);
        $this->assertFalse($results['cccccccc-cccc-3ccc-8ccc-cccccccccccc']['check_failed']);

        // Verify batch splitting: count deck reference queries (excluding user discovery)
        $deckQueries = array_values(array_filter(
            $mysqli->recordedCalls,
            fn (array $call): bool => strpos(strtolower($call['query']), 'from deckcards') !== false
        ));

        // With batch size 2 and 3 valid UUIDs, we expect 2 deck reference queries
        $this->assertCount(2, $deckQueries, 'Expected two batch queries for 3 UUIDs with batch size 2');

        // No single deck query should contain more than 2 parameters
        foreach ($deckQueries as $deckQuery) {
            $paramCount = count($deckQuery['params'] ?? []);
            $this->assertLessThanOrEqual(
                2,
                $paramCount,
                "A batch query contained $paramCount parameters, exceeding batch size of 2"
            );
        }

        // Verify the first batch had 2 UUIDs and the second had 1
        $this->assertCount(2, $deckQueries[0]['params']);
        $this->assertCount(1, $deckQueries[1]['params']);
    }

    /**
     * Test 20: Validator returns false for a derived table name -> COLLECTION_TABLE_NAME_INVALID.
     */
    public function testInvalidCollectionTableNameFailsClosed(): void
    {
        // Validator that always returns false (simulates invalid table name for user 0)
        $validator = function () {
            return false;
        };

        $mysqli = new ScryfallSafetyMysqli();
        $mysqli->queryRoutes = [
            'users' => [
                ['usernumber' => 0, 'username' => 'baduser'],
            ],
        ];
        $service = $this->createService($mysqli, $validator);
        $result = $service->checkSingle('aaaaaaaa-aaaa-1aaa-8aaa-aaaaaaaaaaaa');

        $this->assertTrue($result['check_failed']);
        $this->assertContains('COLLECTION_TABLE_NAME_INVALID', $result['failure_reasons']);
    }
}
