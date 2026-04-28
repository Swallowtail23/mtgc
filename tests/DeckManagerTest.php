<?php

/*
Version:     1.0
Date:        28/04/26
Name:        DeckManagerTest.php
Purpose:     Tests deck manager batch insert behavior.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\DeckManager;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class DeckStmtStub
{
    public string $types = '';
    public array $values = [];
    public bool $executed = false;
    public string $error = '';

    public function bind_param(string $types, mixed &...$values): bool
    {
        $this->types = $types;
        $this->values = $values;
        return true;
    }

    public function execute(): bool
    {
        $this->executed = true;
        return true;
    }

    public function close(): bool
    {
        return true;
    }
}

class DeckDbStub
{
    public string $query = '';
    public DeckStmtStub $stmt;

    public function __construct(DeckStmtStub $stmt)
    {
        $this->stmt = $stmt;
    }

    public function prepare(string $query): DeckStmtStub
    {
        $this->query = $query;
        return $this->stmt;
    }
}

class DeckManagerTest extends TestCase
{
    public function testAddDeckCardsBatchBindsValues()
    {
        $stmt = new DeckStmtStub();
        $db = new DeckDbStub($stmt);
        $GLOBALS['siteTitle'] = 'MTG';
        $gameRules = new GameRules([
            'commander_decktypes' => []
        ]);
        $manager = new DeckManager(
            $db,
            $GLOBALS['appConfig'],
            $gameRules,
            'user@example.com'
        );

        $batch = [
            ['id' => 'card-1', 'mainqty' => 2, 'sideqty' => 0],
            ['id' => 'card-2', 'mainqty' => 1, 'sideqty' => 1],
        ];

        $manager->addDeckCardsBatch(5, $batch);

        $this->assertTrue($stmt->executed);
        $this->assertSame('isiiisii', $stmt->types);
        $this->assertSame([5, 'card-1', 2, 0, 5, 'card-2', 1, 1], $stmt->values);
        $this->assertStringContainsString('INSERT INTO deckcards', $db->query);
    }
}
