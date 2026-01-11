<?php

use MTG\Core\DateYMD;
use PHPUnit\Framework\TestCase;

class DateYMDTest extends TestCase
{
    public function testGetTodayFormat()
    {
        $date = new DateYMD();
        $today = $date->getToday();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $today);
        $this->assertSame($today, (string) $date);
    }
}
