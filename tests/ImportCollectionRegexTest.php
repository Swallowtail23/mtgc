<?php

/*
Version:     1.1
Date:        24/03/26
Name:        ImportCollectionRegexTest.php
Purpose:     Tests for collection import flow with ManaBox parsing and UUID cross-checking.
Notes:       -
Author:      Codex
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\ImportExport;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class ImportCollectionRegexTest extends TestCase
{
    public function testManaBoxUuidMatchImportsCard()
    {
        $uuid = 'c88eb33d-efba-4ad9-87bf-f051079c9bce';
        $db = new ImportCollectionRegexDbStub(
            [
                $uuid => [
                    'id' => $uuid,
                    'finishes' => '["nonfoil","foil","etched"]',
                    'name' => 'Academy Manufacturer',
                    'f1_name' => '',
                    'f2_name' => '',
                    'printed_name' => '',
                    'f1_printed_name' => '',
                    'f2_printed_name' => '',
                    'flavor_name' => '',
                    'f1_flavor_name' => '',
                    'f2_flavor_name' => '',
                    'setcode' => 'SLD',
                    'number_import' => '7094'
                ]
            ],
            []
        );
        $manager = new ImportExportImportStub($db, $GLOBALS['appConfig'], new GameRules([]), 'user@example.test');
        $file = $this->writeTempImportFile([
            'Academy Manufacturer,SLD,Secret Lair Drop,7094,foil,rare,2,111636,'
                . $uuid . ',13.13,false,false,near_mint,en,AUD'
        ]);

        ob_start();
        $manager->importCollectionRegex($file, 'collection', 'add', 'user@example.test');
        ob_end_clean();

        @unlink($file);
        $this->assertCount(1, $manager->capturedBatch);
        $this->assertSame($uuid, $manager->capturedBatch[0]['id']);
        $this->assertSame(2, $manager->capturedBatch[0]['foil']);
    }

    public function testManaBoxUuidSetMismatchStillImports()
    {
        $uuid = 'c88eb33d-efba-4ad9-87bf-f051079c9bce';
        $db = new ImportCollectionRegexDbStub(
            [
                $uuid => [
                    'id' => $uuid,
                    'finishes' => '["nonfoil","foil","etched"]',
                    'name' => 'Academy Manufacturer',
                    'f1_name' => '',
                    'f2_name' => '',
                    'printed_name' => '',
                    'f1_printed_name' => '',
                    'f2_printed_name' => '',
                    'flavor_name' => '',
                    'f1_flavor_name' => '',
                    'f2_flavor_name' => '',
                    'setcode' => 'SLD',
                    'number_import' => '7094'
                ]
            ],
            []
        );
        $manager = new ImportExportImportStub($db, $GLOBALS['appConfig'], new GameRules([]), 'user@example.test');
        $file = $this->writeTempImportFile([
            'Academy Manufacturer,MH3,Secret Lair Drop,7094,normal,rare,1,111636,'
                . $uuid . ',13.13,false,false,near_mint,en,AUD'
        ]);

        ob_start();
        $manager->importCollectionRegex($file, 'collection', 'add', 'user@example.test');
        ob_end_clean();

        @unlink($file);
        $this->assertCount(1, $manager->capturedBatch);
        $this->assertSame($uuid, $manager->capturedBatch[0]['id']);
    }

    public function testManaBoxUuidNameMismatchIsSkipped()
    {
        $uuid = 'c88eb33d-efba-4ad9-87bf-f051079c9bce';
        $db = new ImportCollectionRegexDbStub(
            [
                $uuid => [
                    'id' => $uuid,
                    'finishes' => '["nonfoil","foil","etched"]',
                    'name' => 'Academy Manufacturer',
                    'f1_name' => '',
                    'f2_name' => '',
                    'printed_name' => '',
                    'f1_printed_name' => '',
                    'f2_printed_name' => '',
                    'flavor_name' => '',
                    'f1_flavor_name' => '',
                    'f2_flavor_name' => '',
                    'setcode' => 'SLD',
                    'number_import' => '7094'
                ]
            ],
            []
        );
        $manager = new ImportExportImportStub($db, $GLOBALS['appConfig'], new GameRules([]), 'user@example.test');
        $file = $this->writeTempImportFile([
            'Wrong Card Name,SLD,Secret Lair Drop,7094,normal,rare,1,111636,'
                . $uuid . ',13.13,false,false,near_mint,en,AUD'
        ]);

        ob_start();
        $manager->importCollectionRegex($file, 'collection', 'add', 'user@example.test');
        ob_end_clean();

        @unlink($file);
        $this->assertCount(0, $manager->capturedBatch);
    }

    public function testManaBoxFallbackSetAndNumberWhenUuidEmpty()
    {
        $row = [
            'id' => 'fallback-id-1',
            'finishes' => '["nonfoil","foil"]'
        ];
        $db = new ImportCollectionRegexDbStub([], ['SLD|7094' => $row]);
        $manager = new ImportExportImportStub($db, $GLOBALS['appConfig'], new GameRules([]), 'user@example.test');
        $file = $this->writeTempImportFile([
            ',SLD,Secret Lair Drop,7094,normal,rare,3,111636,,13.13,false,false,near_mint,en,AUD'
        ]);

        ob_start();
        $manager->importCollectionRegex($file, 'collection', 'add', 'user@example.test');
        ob_end_clean();

        @unlink($file);
        $this->assertCount(1, $manager->capturedBatch);
        $this->assertSame('fallback-id-1', $manager->capturedBatch[0]['id']);
        $this->assertSame(3, $manager->capturedBatch[0]['normal']);
    }

    private function writeTempImportFile(array $lines)
    {
        $file = tempnam(sys_get_temp_dir(), 'mtg_import_');
        file_put_contents($file, implode("\n", $lines));
        return $file;
    }
}

class ImportExportImportStub extends ImportExport
{
    public array $capturedBatch = [];

    public function addCardsBatch($mytable, $importType, $count, $total, $batchedCardIds)
    {
        $this->capturedBatch = array_values($batchedCardIds);
        return ['warnings' => 'none', 'total' => $total, 'batchRows' => count($batchedCardIds)];
    }
}

class ImportCollectionRegexDbStub
{
    private array $cardsById;
    private array $cardsBySetNumber;
    public string $error = '';
    public int $affected_rows = 0;

    public function __construct(array $cardsById, array $cardsBySetNumber)
    {
        $this->cardsById = $cardsById;
        $this->cardsBySetNumber = $cardsBySetNumber;
    }

    public function prepare($query)
    {
        return new ImportCollectionRegexStmtStub($query, $this);
    }

    public function execute_query($query)
    {
        return true;
    }

    public function findById($id)
    {
        if (isset($this->cardsById[$id])) :
            return $this->cardsById[$id];
        endif;
        return null;
    }

    public function findBySetAndNumber($set, $number)
    {
        $key = strtoupper($set) . '|' . $number;
        if (isset($this->cardsBySetNumber[$key])) :
            return $this->cardsBySetNumber[$key];
        endif;
        return null;
    }
}

class ImportCollectionRegexStmtStub
{
    private string $query;
    private ImportCollectionRegexDbStub $db;
    private array $params = [];
    public string $error = '';

    public function __construct(string $query, ImportCollectionRegexDbStub $db)
    {
        $this->query = $query;
        $this->db = $db;
    }

    public function bind_param($types, &...$params)
    {
        $this->params = $params;
        return true;
    }

    public function execute()
    {
        return true;
    }

    public function get_result()
    {
        if (stripos($this->query, 'FROM cards_scry WHERE id = ?') !== false) :
            $row = $this->db->findById((string) ($this->params[0] ?? ''));
            return new ImportCollectionRegexResultStub($row);
        endif;
        if (stripos($this->query, 'WHERE setcode = ? AND number_import = ?') !== false) :
            $set = (string) ($this->params[0] ?? '');
            $number = (string) ($this->params[1] ?? '');
            $row = $this->db->findBySetAndNumber($set, $number);
            return new ImportCollectionRegexResultStub($row);
        endif;
        return new ImportCollectionRegexResultStub(null);
    }

    public function close()
    {
        return true;
    }
}

class ImportCollectionRegexResultStub
{
    private ?array $row;
    public int $num_rows;

    public function __construct(?array $row)
    {
        $this->row = $row;
        $this->num_rows = $row === null ? 0 : 1;
    }

    public function fetch_assoc()
    {
        return $this->row;
    }
}
