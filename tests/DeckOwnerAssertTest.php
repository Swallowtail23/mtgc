<?php

/*
Version:     1.0
Date:        28/04/26
Name:        DeckOwnerAssertTest.php
Purpose:     Tests deck ownership assertion behavior.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\DeckManager;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class DeckOwnerAssertTest extends TestCase
{
    public function testAssertDeckOwnerReturnsTrueForOwner()
    {
        $db = new DeckOwnerAssertTestDb(['deckname' => 'Test Deck', 'owner' => 5]);
        $manager = $this->buildDeckManager($db);

        $this->assertTrue($manager->assertDeckOwner(10, 5, 'unit-test'));
    }

    public function testAssertDeckOwnerReturnsFalseForNonOwner()
    {
        $db = new DeckOwnerAssertTestDb(['deckname' => 'Test Deck', 'owner' => 5]);
        $manager = $this->buildDeckManager($db);

        $this->assertFalse($manager->assertDeckOwner(10, 3, 'unit-test'));
    }

    public function testAssertDeckOwnerReturnsFalseWhenDeckMissing()
    {
        $db = new DeckOwnerAssertTestDb(null);
        $manager = $this->buildDeckManager($db);

        $this->assertFalse($manager->assertDeckOwner(99, 1, 'unit-test'));
    }

    public function testAssertDeckOwnerThrowsOnQueryFailure()
    {
        $db = new DeckOwnerAssertTestDb(null, true);
        $manager = $this->buildDeckManager($db);

        $this->expectException(Exception::class);
        $manager->assertDeckOwner(10, 1, 'unit-test');
    }

    private function buildDeckManager(mixed $db): DeckManager
    {
        $anyQuantity = $GLOBALS['any_quantity'] ?? [];
        $gameRules = new GameRules([
            'any_quantity' => $anyQuantity,
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
}

class DeckOwnerAssertTestDb
{
    public string $error = 'stub error';
    private ?array $row;
    private bool $shouldFail;

    public function __construct(?array $row, bool $shouldFail = false)
    {
        $this->row = $row;
        $this->shouldFail = $shouldFail;
    }

    public function execute_query(string $sql, array $params): DeckOwnerAssertTestResult|false
    {
        unset($sql, $params);
        if ($this->shouldFail) {
            return false;
        }
        return new DeckOwnerAssertTestResult($this->row);
    }
}

class DeckOwnerAssertTestResult
{
    private ?array $row;

    public function __construct(?array $row)
    {
        $this->row = $row;
    }

    public function fetch_assoc(): ?array
    {
        return $this->row;
    }
}
