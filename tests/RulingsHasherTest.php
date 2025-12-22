<?php

use MTG\Bulk\RulingsHasher;
use PHPUnit\Framework\TestCase;

class RulingsHasherTest extends TestCase
{
    public function testHashIsStableForSameInput()
    {
        $hasher = new RulingsHasher();
        $hashA = $hasher->buildContentHash('oracle-1', 'wotc', '2024-01-01', 'Ruling text');
        $hashB = $hasher->buildContentHash('oracle-1', 'wotc', '2024-01-01', 'Ruling text');

        $this->assertSame($hashA, $hashB);
    }

    public function testHashChangesWhenContentChanges()
    {
        $hasher = new RulingsHasher();
        $hashA = $hasher->buildContentHash('oracle-1', 'wotc', '2024-01-01', 'Ruling text');
        $hashB = $hasher->buildContentHash('oracle-1', 'wotc', '2024-01-01', 'Ruling text updated');

        $this->assertNotSame($hashA, $hashB);
    }

    public function testHashHandlesNulls()
    {
        $hasher = new RulingsHasher();
        $hash = $hasher->buildContentHash(null, null, null, null);

        $this->assertNotEmpty($hash);
    }
}
