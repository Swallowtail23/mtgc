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

    public function testEmptyLineAndIgnoredLine()
    {
        $rules = new GameRules([
            'bracketsInNames' => [],
            'importLinestoIgnore' => ['Sideboard'],
        ]);

        $this->assertSame('empty line', ImportExport::inputInterpreter(' ', $this->appConfig, $rules));
        $this->assertSame('empty line', ImportExport::inputInterpreter('Sideboard', $this->appConfig, $rules));
    }

    public function testBracketNameIsPreserved()
    {
        $rules = new GameRules([
            'bracketsInNames' => ['A'],
            'importLinestoIgnore' => [],
        ]);
        $result = ImportExport::inputInterpreter('2 Card (A)', $this->appConfig, $rules);
        $this->assertSame('Card (A)', $result['name']);
        $this->assertSame('2', $result['qty']);
    }

    public function testShortcutAndFoilFullFormats()
    {
        $shortcut = [
            'set' => 'MH3',
            'number' => '304',
            'name' => '',
            'lang' => '',
            'qty' => '',
            'uuid' => '',
            'normal' => 0,
            'foil' => 0,
            'etched' => 0
        ];
        $this->assertSame(
            $shortcut,
            ImportExport::inputInterpreter('(mh3) 304', $this->appConfig, $this->gameRules)
        );

        $foilExpected = [
            'set' => 'MH3',
            'number' => '304',
            'name' => 'Plains',
            'lang' => '',
            'qty' => '2',
            'uuid' => '',
            'normal' => 0,
            'foil' => '2',
            'etched' => 0
        ];
        $this->assertSame(
            $foilExpected,
            ImportExport::inputInterpreter('2 Plains (mh3) 304 *F*', $this->appConfig, $this->gameRules)
        );
    }

    public function testMtgcCsvAllowsEmptyUuid()
    {
        $line = 'MH3,304,Plains,en,1,0,0,';
        $result = ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules);

        $this->assertSame('', $result['uuid']);
        $this->assertSame(1, $result['normal']);
    }
}
