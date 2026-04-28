<?php

/*
Version:     1.0
Date:        28/04/26
Name:        CollectionStatsTest.php
Purpose:     Tests collection statistics query result mapping.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\CollectionStats;
use PHPUnit\Framework\TestCase;

class CollectionStatsResultStub
{
    private array $row;

    public function __construct(array $row)
    {
        $this->row = $row;
    }

    public function fetch_array(int $mode): array
    {
        unset($mode);
        return $this->row;
    }

    public function fetch_assoc(): array
    {
        return $this->row;
    }
}

class CollectionStatsDbStub
{
    public string $error = '';

    public function query(string $query): CollectionStatsResultStub
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

        $result = $stats->getStats('collection');

        $this->assertSame(7, $result['card_count']);
        $this->assertSame(3, $result['mr_count']);
        $this->assertSame(12.5, $result['value_usd']);
        $this->assertNull($result['value_local']);
    }
}
