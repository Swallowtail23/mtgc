<?php

use MTG\Cards\DeckManager;
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

    private function buildDeckManager($db)
    {
        $logfile = $GLOBALS['logfile'] ?? sys_get_temp_dir() . '/phpunit.log';
        return new DeckManager(
            $db,
            $logfile,
            'user@example.test',
            'server@example.test',
            [],
            [],
            'Test Site'
        );
    }
}

class DeckOwnerAssertTestDb
{
    public $error = 'stub error';
    private $row;
    private $shouldFail;

    public function __construct($row, $shouldFail = false)
    {
        $this->row = $row;
        $this->shouldFail = $shouldFail;
    }

    public function execute_query($sql, $params)
    {
        if ($this->shouldFail) {
            return false;
        }
        return new DeckOwnerAssertTestResult($this->row);
    }
}

class DeckOwnerAssertTestResult
{
    private $row;

    public function __construct($row)
    {
        $this->row = $row;
    }

    public function fetch_assoc()
    {
        return $this->row;
    }
}
