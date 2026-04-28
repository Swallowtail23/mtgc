<?php

/*
Version:     1.0
Date:        28/04/26
Name:        DeckManagerActionsTest.php
Purpose:     Tests deck manager action helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\DeckManager;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class DeckManagerActionsTest extends TestCase
{
    private function buildManager(mixed $db): DeckManager
    {
        $gameRules = new GameRules([
            'any_quantity' => [],
            'commander_decktypes' => [],
            'commander_multiples' => [],
            'deck_legality_map' => [],
            'noQuickAddLayouts' => [],
        ]);

        return new DeckManager(
            $db,
            $GLOBALS['appConfig'],
            $gameRules,
            'user@example.test'
        );
    }

    public function testBumpDeckUpdatedAtSkipsWhenNoExecuteQuery()
    {
        $db = new class {
        };
        $manager = $this->buildManager($db);
        $manager->bumpDeckUpdatedAt(1);
        $this->assertTrue(true);
    }

    public function testDeckCardCheckReturnsRows()
    {
        $rows = [
            ['decknumber' => 1, 'cardqty' => 2, 'sideqty' => 0, 'deckname' => 'Test 1'],
            ['decknumber' => 2, 'cardqty' => 1, 'sideqty' => 1, 'deckname' => 'Test 2'],
        ];
        $db = new class ($rows) {
            private array $rows;

            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            public function execute_query(string $sql, array $params): object
            {
                unset($sql, $params);
                return new class ($this->rows) {
                    private array $rows;
                    private int $index = 0;

                    public function __construct(array $rows)
                    {
                        $this->rows = $rows;
                    }

                    public function fetch_assoc(): ?array
                    {
                        if ($this->index >= count($this->rows)) {
                            return null;
                        }
                        $row = $this->rows[$this->index];
                        $this->index++;
                        return $row;
                    }
                };
            }
        };

        $manager = $this->buildManager($db);
        $result = $manager->deckCardCheck('card-1', 5);

        $this->assertSame(
            [
                ['decknumber' => 1, 'qty' => 2, 'sideqty' => 0, 'deckname' => 'Test 1'],
                ['decknumber' => 2, 'qty' => 1, 'sideqty' => 1, 'deckname' => 'Test 2'],
            ],
            $result
        );
    }

    public function testSubtractDeckCardMainDecrements()
    {
        $db = new class {
            public array $queries = [];

            public function execute_query(string $sql, array $params): object|bool
            {
                unset($params);
                $this->queries[] = $sql;
                if (strpos($sql, 'SELECT cardqty') !== false) {
                    return new class {
                        public int $num_rows = 1;

                        public function fetch_assoc(): array
                        {
                            return ['cardqty' => 2];
                        }
                    };
                }
                return true;
            }
        };

        $manager = $this->buildManager($db);
        $status = $manager->subtractDeckCard(1, 'card-1', 'main', 1);

        $this->assertSame('-1main', $status);
    }

    public function testSubtractDeckCardAllSideCleansUp()
    {
        $db = new class {
            public array $queries = [];

            public function execute_query(string $sql, array $params): bool
            {
                unset($params);
                $this->queries[] = $sql;
                return true;
            }
        };

        $manager = $this->buildManager($db);
        $status = $manager->subtractDeckCard(1, 'card-1', 'side', 'all');

        $this->assertSame('allside', $status);
        $this->assertTrue($this->containsQuery($db->queries, 'DELETE FROM deckcards'));
    }

    public function testAddCommanderReplacesExisting()
    {
        $db = new DeckManagerCommanderDbStub(1);
        $manager = $this->buildManager($db);

        $status = $manager->addCommander(1, 'card-1');

        $this->assertSame('+cdr', $status);
    }

    public function testAddPartnerReplacesExisting()
    {
        $db = new class {
            public function execute_query(string $sql, array $params): object|bool
            {
                unset($params);
                if (strpos($sql, 'SELECT commander') !== false) {
                    return new class {
                        public int $num_rows = 1;
                    };
                }
                return true;
            }
        };

        $manager = $this->buildManager($db);
        $status = $manager->addPartner(1, 'card-2');

        $this->assertSame('+ptnr', $status);
    }

    private function containsQuery(array $queries, string $needle): bool
    {
        foreach ($queries as $query) {
            if (strpos($query, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}

class DeckManagerCommanderDbStub
{
    private int $existing;

    public function __construct(int $existingCount)
    {
        $this->existing = $existingCount;
    }

    public function prepare(string $query): DeckManagerCommanderStmtStub
    {
        if (strpos($query, 'SELECT commander') !== false) {
            $result = new class ($this->existing) {
                public int $num_rows;

                public function __construct(int $count)
                {
                    $this->num_rows = $count;
                }
            };
            return new DeckManagerCommanderStmtStub($result, true);
        }

        return new DeckManagerCommanderStmtStub(null, true);
    }
}

class DeckManagerCommanderStmtStub
{
    private ?object $result;
    private bool $executeResult;

    public function __construct(?object $result, bool $executeResult)
    {
        $this->result = $result;
        $this->executeResult = $executeResult;
    }

    public function bind_param(string $types, mixed &...$values): bool
    {
        unset($types, $values);
        return true;
    }

    public function execute(): bool
    {
        return $this->executeResult;
    }

    public function get_result(): ?object
    {
        return $this->result;
    }

    public function close(): void
    {
    }
}
