<?php

use MTG\Cards\CollectionStats;
use PHPUnit\Framework\TestCase;

class CollectionStatsResultStub
{
    private $row;

    public function __construct($row)
    {
        $this->row = $row;
    }

    public function fetch_array($mode)
    {
        return $this->row;
    }

    public function fetch_assoc()
    {
        return $this->row;
    }
}

class CollectionStatsDbStub
{
    public $error = '';

    public function query($query)
    {
        if (strpos($query, 'TOTALMR') !== false) :
            return new CollectionStatsResultStub(['TOTALMR' => 3]);
        endif;
        if (strpos($query, 'AS TOTAL') !== false && strpos($query, 'price') !== false) :
            return new CollectionStatsResultStub(['TOTAL' => 12.5]);
        endif;
        return new CollectionStatsResultStub(['TOTAL' => 7]);
    }
}

class CollectionStatsTest extends TestCase
{
    public function testGetStatsReturnsExpectedTotals()
    {
        $db = new CollectionStatsDbStub();
        $stats = new CollectionStats($db, $GLOBALS['appConfig']);

        $result = $stats->getStats(1, 'collection');

        $this->assertSame(7, $result['card_count']);
        $this->assertSame(3, $result['mr_count']);
        $this->assertSame(12.5, $result['value_usd']);
        $this->assertNull($result['value_local']);
    }
}
