<?php

use MTG\Cards\ImportExport;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class InputInterpreterTest extends TestCase
{
    private $appConfig;
    private $gameRules;

    protected function setUp(): void
    {
        $this->appConfig = $GLOBALS['appConfig'];
        $this->gameRules = new GameRules([
            'bracketsInNames' => [],
            'importLinestoIgnore' => [],
        ]);
    }

    public function testCsvHeader()
    {
        $line = 'set,number,name,lang,normal,foil,etched,uuid';
        $this->assertSame('header', ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules));
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
        $this->assertSame($expected, ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules));
    }

    public function testDelverCsvRow()
    {
        $line = 'MH3,304,Plains,1,2,123e4567-e89b-12d3-a456-426614174000';
        $expected = [
            'set' => 'MH3',
            'number' => '304',
            'name' => 'Plains',
            'lang' => 'unspecified',
            'qty' => 3,
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'normal' => 1,
            'foil' => 2,
            'etched' => 0
        ];
        $this->assertSame($expected, ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules));
    }

    public function testMtgcCsvRowWithEtched()
    {
        $line = 'MH3,304,Plains,en,1,2,3,123e4567-e89b-12d3-a456-426614174000';
        $expected = [
            'set' => 'MH3',
            'number' => '304',
            'name' => 'Plains',
            'lang' => 'en',
            'qty' => 6,
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'normal' => 1,
            'foil' => 2,
            'etched' => 3
        ];
        $this->assertSame($expected, ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules));
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
        $this->assertEquals($expected, ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules));
    }

    public function testInvalidCsvRowReturnsFalse()
    {
        $line = 'MH3,304,Plains,1,0';
        $this->assertFalse(ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules));
    }

    public function testNoPatternMatches()
    {
        $expected = [
            'set' => '',
            'number' => '',
            'name' => '@@@',
            'lang' => '',
            'qty' => 1,
            'uuid' => '',
            'normal' => 1,
            'foil' => 0,
            'etched' => 0
        ];
        $this->assertEquals(
            $expected,
            ImportExport::inputInterpreter('@@@', $this->appConfig, $this->gameRules)
        );
    }
}
