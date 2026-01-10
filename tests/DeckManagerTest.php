<?php

use MTG\Cards\DeckManager;
use PHPUnit\Framework\TestCase;

class DeckStmtStub
{
    public $types;
    public $values;
    public $executed = false;
    public $error = '';

    public function bind_param($types, &...$values)
    {
        $this->types = $types;
        $this->values = $values;
        return true;
    }

    public function execute()
    {
        $this->executed = true;
        return true;
    }

    public function close()
    {
        return true;
    }
}

class DeckDbStub
{
    public $query;
    public $stmt;

    public function __construct($stmt)
    {
        $this->stmt = $stmt;
    }

    public function prepare($query)
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
        $anyQuantity = $GLOBALS['any_quantity'] ?? [];
        $manager = new DeckManager(
            $db,
            $GLOBALS['logfile'],
            'user@example.com',
            'server@example.com',
            [],
            [],
            $anyQuantity
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
