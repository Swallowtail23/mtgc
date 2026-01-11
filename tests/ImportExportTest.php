<?php

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
}

class ImportExportDbStub
{
    private $fields;
    private $rows;
    public $query;

    public function __construct(array $fields, array $rows)
    {
        $this->fields = $fields;
        $this->rows = $rows;
    }

    public function real_escape_string($table)
    {
        return $table;
    }

    public function query($sql)
    {
        $this->query = $sql;
        return new ImportExportCsvResult($this->fields, $this->rows);
    }
}

class ImportExportCsvResult
{
    private $fields;
    private $rows;
    private $index = 0;
    public $field_count;

    public function __construct(array $fields, array $rows)
    {
        $this->fields = $fields;
        $this->rows = $rows;
        $this->field_count = count($fields);
    }

    public function fetch_fields()
    {
        $objects = [];
        foreach ($this->fields as $field) {
            $objects[] = (object) ['name' => $field];
        }
        return $objects;
    }

    public function fetch_assoc()
    {
        if ($this->index >= count($this->rows)) {
            return null;
        }
        $row = $this->rows[$this->index];
        $this->index++;
        return $row;
    }

    public function fetch_row()
    {
        if ($this->index >= count($this->rows)) {
            return null;
        }
        $row = $this->rows[$this->index];
        $this->index++;
        return array_values($row);
    }
}
