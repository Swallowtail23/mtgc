<?php

/*
Version:     1.0
Date:        28/04/26
Name:        PriceManagerUpdateTest.php
Purpose:     Tests price manager collection value update transactions.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use PHPUnit\Framework\TestCase;

function getRealPriceManagerClassForUpdate(): string
{
    if (class_exists('PriceManagerRealUpdate', false)) :
        return 'PriceManagerRealUpdate';
    endif;

    $source = file_get_contents(__DIR__ . '/../src/MTG/Cards/PriceManager.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/^\\s*namespace\\s+MTG\\\\Cards;\\s*/m', '', $source, 1);
    $source = preg_replace('/class\\s+PriceManager\\b/', 'class PriceManagerRealUpdate', $source, 1);
    eval($source);
    return 'PriceManagerRealUpdate';
}

class PriceManagerUpdateTest extends TestCase
{
    public function testUpdateCollectionValuesBulkSuccess()
    {
        $db = new PriceManagerUpdateDbStub([
            'affected_rows' => 7
        ]);
        $class = getRealPriceManagerClassForUpdate();
        $manager = new $class($db, $GLOBALS['appConfig'], 'user@example.test');

        $result = $manager->updateCollectionValues('collection');

        $this->assertSame(7, $result);
        $this->assertSame(1, $db->beginCalled);
        $this->assertSame(1, $db->commitCalled);
    }

    public function testUpdateCollectionValuesBulkQueryFailureRollsBack()
    {
        $db = new PriceManagerUpdateDbStub([
            'queryReturn' => false
        ]);
        $class = getRealPriceManagerClassForUpdate();
        $manager = new $class($db, $GLOBALS['appConfig'], 'user@example.test');

        try {
            $manager->updateCollectionValues('collection');
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertSame(1, $db->rollbackCalled);
        }
    }

    public function testUpdateCollectionValuesBulkCommitFailureThrows()
    {
        $db = new PriceManagerUpdateDbStub([
            'commitReturn' => false
        ]);
        $class = getRealPriceManagerClassForUpdate();
        $manager = new $class($db, $GLOBALS['appConfig'], 'user@example.test');

        $this->expectException(Exception::class);
        $manager->updateCollectionValues('collection');
    }

    public function testUpdateCollectionValuesSingleSuccess()
    {
        $db = new PriceManagerUpdateDbStub([
            'affected_rows' => 2
        ]);
        $class = getRealPriceManagerClassForUpdate();
        $manager = new $class($db, $GLOBALS['appConfig'], 'user@example.test');

        $result = $manager->updateCollectionValues('collection', 'card-1');

        $this->assertSame(2, $result);
        $this->assertSame(1, $db->beginCalled);
        $this->assertSame(1, $db->commitCalled);
    }

    public function testUpdateCollectionValuesSinglePrepareFailureRollsBack()
    {
        $db = new PriceManagerUpdateDbStub([
            'prepareReturn' => false
        ]);
        $class = getRealPriceManagerClassForUpdate();
        $manager = new $class($db, $GLOBALS['appConfig'], 'user@example.test');

        try {
            $manager->updateCollectionValues('collection', 'card-1');
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertSame(1, $db->rollbackCalled);
        }
    }

    public function testUpdateCollectionValuesSingleExecuteFailureRollsBack()
    {
        $db = new PriceManagerUpdateDbStub([
            'executeReturn' => false
        ]);
        $class = getRealPriceManagerClassForUpdate();
        $manager = new $class($db, $GLOBALS['appConfig'], 'user@example.test');

        try {
            $manager->updateCollectionValues('collection', 'card-1');
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            $this->assertSame(1, $db->rollbackCalled);
        }
    }
}

class PriceManagerUpdateDbStub
{
    public string $error = 'stub error';
    public int $affected_rows = 0;
    public int $beginCalled = 0;
    public int $commitCalled = 0;
    public int $rollbackCalled = 0;
    private bool $queryReturn = true;
    private bool $commitReturn = true;
    private bool $prepareReturn = true;
    private bool $executeReturn = true;

    public function __construct(array $overrides = [])
    {
        foreach ($overrides as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function begin_transaction(): bool
    {
        $this->beginCalled++;
        return true;
    }

    public function query(string $sql): bool
    {
        unset($sql);
        return $this->queryReturn;
    }

    public function commit(): bool
    {
        $this->commitCalled++;
        return $this->commitReturn;
    }

    public function rollback(): bool
    {
        $this->rollbackCalled++;
        return true;
    }

    public function prepare(string $sql): PriceManagerUpdateStmtStub|false
    {
        unset($sql);
        if ($this->prepareReturn === false) {
            return false;
        }
        return new PriceManagerUpdateStmtStub($this->executeReturn);
    }
}

class PriceManagerUpdateStmtStub
{
    private bool $executeReturn;

    public function __construct(bool $executeReturn)
    {
        $this->executeReturn = $executeReturn;
    }

    public function bind_param(string $types, mixed &...$values): bool
    {
        unset($types, $values);
        return true;
    }

    public function execute(): bool
    {
        return $this->executeReturn;
    }

    public function close(): void
    {
    }
}
