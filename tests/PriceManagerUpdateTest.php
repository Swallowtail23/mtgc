<?php

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
    public $error = 'stub error';
    public $affected_rows = 0;
    public $beginCalled = 0;
    public $commitCalled = 0;
    public $rollbackCalled = 0;
    private $queryReturn = true;
    private $commitReturn = true;
    private $prepareReturn = true;
    private $executeReturn = true;

    public function __construct(array $overrides = [])
    {
        foreach ($overrides as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function begin_transaction()
    {
        $this->beginCalled++;
        return true;
    }

    public function query($sql)
    {
        return $this->queryReturn;
    }

    public function commit()
    {
        $this->commitCalled++;
        return $this->commitReturn;
    }

    public function rollback()
    {
        $this->rollbackCalled++;
        return true;
    }

    public function prepare($sql)
    {
        if ($this->prepareReturn === false) {
            return false;
        }
        return new PriceManagerUpdateStmtStub($this->executeReturn);
    }
}

class PriceManagerUpdateStmtStub
{
    private $executeReturn;

    public function __construct($executeReturn)
    {
        $this->executeReturn = $executeReturn;
    }

    public function bind_param($types, &...$values)
    {
        return true;
    }

    public function execute()
    {
        return $this->executeReturn;
    }

    public function close()
    {
    }
}
