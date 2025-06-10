<?php
use PHPUnit\Framework\TestCase;

class InputInterpreterTest extends TestCase
{
    public function testCsvHeader()
    {
        $line = 'set,number,name,lang,normal,foil,etched,uuid';
        $this->assertSame('header', input_interpreter($line));
    }

    public function testValidCsvRow()
    {
        $line = 'MH3,304,Plains,en,1,0,0,123e4567-e89b-12d3-a456-426614174000';
        $expected = [
            'set' => 'MH3',
            'number' => '304',
            'name' => 'Plains',
            'lang' => 'en',
            'qty' => 1,
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'normal' => 1,
            'foil' => 0,
            'etched' => 0
        ];
        $this->assertSame($expected, input_interpreter($line));
    }

    public function testNonCsvText()
    {
        $line = '2 Plains (MH3) 304';
        $expected = [
            'set' => 'MH3',
            'number' => '304',
            'name' => 'Plains',
            'lang' => '',
            'qty' => 2,
            'uuid' => '',
            'normal' => 2,
            'foil' => 0,
            'etched' => 0
        ];
        $this->assertSame($expected, input_interpreter($line));
    }

    public function testNoPatternMatches()
    {
        $this->assertFalse(input_interpreter('@@@'));
    }
}
