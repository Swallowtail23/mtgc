<?php

/*
Version:     1.0
Date:        28/04/26
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
        $fields = ['setcode', 'number_import', 'name', 'lang', 'normal', 'foil', 'etched', 'scryfall_id'];
        $rows = [
            ['MH3', '304', 'Plains', 'en', 1, 0, 0, 'uuid-1'],
            ['MH3', '305', 'Island "Alt"', 'en', 0, 2, 0, 'uuid-2'],
        ];
        $db = new ImportExportDbStub($fields, $rows);
        $manager = new ImportExport(
            $db,
            $GLOBALS['appConfig'],
            new GameRules([]),
            'user@example.test'
        );

        $csv = $manager->buildCollectionCsv('collection');

        $this->assertStringContainsString(
            '"setcode","number_import","name","lang","normal","foil","etched","scryfall_id"',
            $csv
        );
        $this->assertStringContainsString('"MH3","304","Plains","en","1","0","0","uuid-1"', $csv);
        $this->assertStringContainsString('"MH3","305","Island \\"Alt\\"","en","0","2","0","uuid-2"', $csv);
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
