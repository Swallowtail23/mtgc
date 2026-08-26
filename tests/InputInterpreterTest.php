<?php

/*
Version:     1.1
Date:        26/08/26
Name:        InputInterpreterTest.php
Purpose:     Tests import input interpretation.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\ImportExport;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class InputInterpreterTest extends TestCase
{
    private AppConfig $appConfig;
    private GameRules $gameRules;

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

    public function testEnrichedMtgcExportMetadataAndHeaderAreIgnored(): void
    {
        $metadata = 'exported_at,2026-08-26 18:42:10,timezone,Australia/Hobart,currency,USD,'
            . 'pricing_source,"TCGplayer Near Mint market price, via Scryfall"';
        $header = 'setcode,number_import,name,lang,normal,foil,etched,scryfall_id,rarity,type_line,'
            . 'normal_price_usd,normal_value_usd,foil_price_usd,foil_value_usd,etched_price_usd,'
            . 'etched_value_usd,row_value_usd';

        $this->assertSame('header', ImportExport::inputInterpreter($metadata, $this->appConfig, $this->gameRules));
        $this->assertSame('header', ImportExport::inputInterpreter($header, $this->appConfig, $this->gameRules));
    }

    public function testEnrichedMtgcExportRowUsesOriginalImportColumns(): void
    {
        $line = 'MH3,304,Plains,en,1,2,3,123e4567-e89b-12d3-a456-426614174000,common,'
            . 'Basic Land - Plains,1.00,1.00,2.00,4.00,3.00,9.00,14.00';

        $result = ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules);

        $this->assertIsArray($result);
        $this->assertSame('MH3', $result['set']);
        $this->assertSame('304', $result['number']);
        $this->assertSame('Plains', $result['name']);
        $this->assertSame('en', $result['lang']);
        $this->assertSame(1, $result['normal']);
        $this->assertSame(2, $result['foil']);
        $this->assertSame(3, $result['etched']);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result['uuid']);
    }

    public function testInvalidEnrichedMtgcExportRowIsNotTreatedAsManaBox(): void
    {
        $line = 'MH3,304,Plains,en,not-a-quantity,2,3,123e4567-e89b-12d3-a456-426614174000,common,'
            . 'Basic Land - Plains,1.00,1.00,2.00,4.00,3.00,9.00,14.00';

        $this->assertFalse(ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules));
    }

    public function testManaBoxCsvHeader()
    {
        $line = 'Name,Set code,Set name,Collector number,Foil,Rarity,Quantity,ManaBox ID,'
            . 'Scryfall ID,Purchase price,Misprint,Altered,Condition,Language,Purchase price currency';
        $this->assertSame('header', ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules));
    }

    public function testManaBoxCsvNormalFoilAndEtchedMapping()
    {
        $normalLine = 'Academy Manufacturer,SLD,Secret Lair Drop,7094,normal,rare,2,111636,'
            . 'c88eb33d-efba-4ad9-87bf-f051079c9bce,13.13,false,false,near_mint,en,AUD';
        $foilLine = 'Academy Manufacturer,SLD,Secret Lair Drop,7094,foil,rare,3,111636,'
            . 'c88eb33d-efba-4ad9-87bf-f051079c9bce,13.13,false,false,near_mint,en,AUD';
        $etchedLine = 'Academy Manufacturer,SLD,Secret Lair Drop,7094,etched,rare,4,111636,'
            . 'c88eb33d-efba-4ad9-87bf-f051079c9bce,13.13,false,false,near_mint,en,AUD';

        $normalResult = ImportExport::inputInterpreter($normalLine, $this->appConfig, $this->gameRules);
        $foilResult = ImportExport::inputInterpreter($foilLine, $this->appConfig, $this->gameRules);
        $etchedResult = ImportExport::inputInterpreter($etchedLine, $this->appConfig, $this->gameRules);

        $this->assertSame(2, $normalResult['normal']);
        $this->assertSame(0, $normalResult['foil']);
        $this->assertSame(0, $normalResult['etched']);

        $this->assertSame(0, $foilResult['normal']);
        $this->assertSame(3, $foilResult['foil']);
        $this->assertSame(0, $foilResult['etched']);

        $this->assertSame(0, $etchedResult['normal']);
        $this->assertSame(0, $etchedResult['foil']);
        $this->assertSame(4, $etchedResult['etched']);
    }

    public function testManaBoxCsvUnknownFinishReturnsFalse()
    {
        $line = 'Academy Manufacturer,SLD,Secret Lair Drop,7094,glossy,rare,2,111636,'
            . 'c88eb33d-efba-4ad9-87bf-f051079c9bce,13.13,false,false,near_mint,en,AUD';
        $this->assertFalse(ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules));
    }

    public function testManaBoxCsvValidUuidParses()
    {
        $line = 'Academy Manufacturer,SLD,Secret Lair Drop,7094,normal,rare,1,111636,'
            . 'c88eb33d-efba-4ad9-87bf-f051079c9bce,13.13,false,false,near_mint,en,AUD';
        $result = ImportExport::inputInterpreter($line, $this->appConfig, $this->gameRules);

        $this->assertSame('SLD', strtoupper($result['set']));
        $this->assertSame('7094', $result['number']);
        $this->assertSame('c88eb33d-efba-4ad9-87bf-f051079c9bce', $result['uuid']);
        $this->assertSame(1, $result['qty']);
        $this->assertSame('en', $result['lang']);
    }
}
