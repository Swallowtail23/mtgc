<?php

/*
Version:     1.1
Date:        26/08/26
Name:        ImportExportTest.php
Purpose:     Tests import/export CSV formatting and input interpretation.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Cards\ImportExport;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class ImportExportTest extends TestCase
{
    public function testBuildCollectionCsvFormatsRows()
    {
        $fields = [
            'setcode',
            'number_import',
            'name',
            'lang',
            'normal',
            'foil',
            'etched',
            'scryfall_id',
            'rarity',
            'type_line',
            'normal_price_usd',
            'normal_value_usd',
            'foil_price_usd',
            'foil_value_usd',
            'etched_price_usd',
            'etched_value_usd',
            'row_value_usd'
        ];
        $rows = [
            [
                'MH3',
                '304',
                'Plains',
                'en',
                2,
                0,
                1,
                '123e4567-e89b-12d3-a456-426614174000',
                'common',
                'Basic Land - Plains',
                '1.25',
                '2.50',
                '4.00',
                '0.00',
                null,
                null,
                null
            ],
            [
                'MH3',
                '305',
                'Island "Alt"',
                'en',
                0,
                2,
                0,
                '123e4567-e89b-12d3-a456-426614174001',
                'common',
                'Basic Land - Island',
                '0.50',
                '0.00',
                '3.00',
                '6.00',
                null,
                null,
                '6.00'
            ],
        ];
        $db = new ImportExportDbStub($fields, $rows);
        $manager = new ImportExport(
            $db,
            $GLOBALS['appConfig'],
            new GameRules([]),
            'user@example.test'
        );

        $csv = $manager->buildCollectionCsv(
            'collection',
            new DateTimeImmutable('2026-08-26 18:42:10', new DateTimeZone('UTC'))
        );

        $this->assertStringContainsString(
            '"exported_at","2026-08-26 18:42:10","timezone","UTC","currency","USD",'
                . '"pricing_source","TCGplayer Near Mint market price, via Scryfall"',
            $csv
        );
        $this->assertStringContainsString(
            '"setcode","number_import","name","lang","normal","foil","etched","scryfall_id","rarity",'
                . '"type_line","normal_price_usd","normal_value_usd","foil_price_usd","foil_value_usd",'
                . '"etched_price_usd","etched_value_usd","row_value_usd"',
            $csv
        );
        $this->assertStringContainsString(
            '"MH3","304","Plains","en","2","0","1","123e4567-e89b-12d3-a456-426614174000",'
                . '"common","Basic Land - Plains","1.25","2.50","4.00","0.00",,,',
            $csv
        );
        $this->assertStringContainsString(
            '"MH3","305","Island \\"Alt\\"","en","0","2","0",'
                . '"123e4567-e89b-12d3-a456-426614174001","common","Basic Land - Island","0.50",'
                . '"0.00","3.00","6.00",,,"6.00"',
            $csv
        );
        $this->assertStringContainsString('cards_scry.price_foil > 0', $db->query);
        $this->assertStringContainsString('cards_scry.price_etched > 0', $db->query);
        $this->assertStringContainsString('COALESCE(cards_scry.price_foil, 0) <= 0', $db->query);
        $this->assertStringContainsString('COALESCE(cards_scry.price_etched, 0) <= 0', $db->query);
        $this->assertStringNotContainsString('WHEN price_foil IS NULL', $db->query);
    }

    public function testInputInterpreterDetectsHeader()
    {
        $gameRules = new GameRules([]);
        $input = 'set,number,name,lang,normal,foil,etched,uuid';

        $result = ImportExport::inputInterpreter($input, $GLOBALS['appConfig'], $gameRules);

        $this->assertSame('header', $result);
    }

    public function testInputInterpreterRejectsInvalidCsv()
    {
        $gameRules = new GameRules([]);
        $input = 'mh3,304,Plains,en,1,0,0,not-a-uuid';

        $result = ImportExport::inputInterpreter($input, $GLOBALS['appConfig'], $gameRules);

        $this->assertFalse($result);
    }

    public function testInputInterpreterHandlesIgnoredLine()
    {
        $gameRules = new GameRules([
            'importLinestoIgnore' => ['Sideboard']
        ]);

        $result = ImportExport::inputInterpreter('Sideboard', $GLOBALS['appConfig'], $gameRules);

        $this->assertSame('empty line', $result);
    }

    public function testInputInterpreterParsesShortcutInput()
    {
        $gameRules = new GameRules([]);

        $result = ImportExport::inputInterpreter('(mh3) 304', $GLOBALS['appConfig'], $gameRules);

        $this->assertSame('MH3', $result['set']);
        $this->assertSame('304', $result['number']);
        $this->assertSame('', $result['name']);
    }
}

class ImportExportDbStub
{
    private array $fields;
    private array $rows;
    public string $query = '';

    public function __construct(array $fields, array $rows)
    {
        $this->fields = $fields;
        $this->rows = $rows;
    }

    public function real_escape_string(string $table): string
    {
        return $table;
    }

    public function query(string $sql): ImportExportCsvResult
    {
        $this->query = $sql;
        return new ImportExportCsvResult($this->fields, $this->rows);
    }
}

class ImportExportCsvResult
{
    private array $fields;
    private array $rows;
    private int $index = 0;
    public int $field_count;

    public function __construct(array $fields, array $rows)
    {
        $this->fields = $fields;
        $this->rows = $rows;
        $this->field_count = count($fields);
    }

    public function fetch_fields(): array
    {
        $objects = [];
        foreach ($this->fields as $field) :
            $objects[] = (object) ['name' => $field];
        endforeach;
        return $objects;
    }

    public function fetch_assoc(): ?array
    {
        if ($this->index >= count($this->rows)) {
            return null;
        }
        $row = $this->rows[$this->index];
        $this->index++;
        return $row;
    }

    public function fetch_row(): ?array
    {
        if ($this->index >= count($this->rows)) {
            return null;
        }
        $row = $this->rows[$this->index];
        $this->index++;
        return array_values($row);
    }
}
