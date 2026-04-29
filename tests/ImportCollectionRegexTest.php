<?php

/*
Version:     1.6
Date:        29/04/26
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

    public function testMtgcUuidNameWithEscapedQuotesIsAccepted()
    {
        $uuid = '5432b863-21cc-4898-9463-29049f939e51';
        $db = new ImportCollectionRegexDbStub(
            [
                $uuid => [
                    'id' => $uuid,
                    'finishes' => '["nonfoil","foil"]',
                    'name' => 'Kongming, "Sleeping Dragon"',
                    'f1_name' => '',
                    'f2_name' => '',
                    'printed_name' => '',
                    'f1_printed_name' => '',
                    'f2_printed_name' => '',
                    'flavor_name' => '',
                    'f1_flavor_name' => '',
                    'f2_flavor_name' => '',
                    'setcode' => 'C13',
                    'number_import' => '16'
                ]
            ],
            []
        );
        $manager = new ImportExportImportStub($db, $GLOBALS['appConfig'], new GameRules([]), 'user@example.test');
        $file = $this->writeTempImportFile([
            '"c13","16","Kongming, \\"Sleeping Dragon\\"","en","1","0","0","' . $uuid . '"'
        ]);

        ob_start();
        $manager->importCollectionRegex($file, 'collection', 'replace', 'user@example.test');
        ob_end_clean();

        @unlink($file);
        $this->assertCount(1, $manager->capturedBatch);
        $this->assertSame($uuid, $manager->capturedBatch[0]['id']);
        $this->assertSame(1, $manager->capturedBatch[0]['normal']);
    }

    public function testMtgcUuidNameWithEscapedOuterQuotesIsAccepted()
    {
        $uuid = 'cb3587b9-e727-4f37-b4d6-1baa7316262f';
        $db = new ImportCollectionRegexDbStub(
            [
                $uuid => [
                    'id' => $uuid,
                    'finishes' => '["nonfoil","foil"]',
                    'name' => '"Rumors of My Death . . ."',
                    'f1_name' => '',
                    'f2_name' => '',
                    'printed_name' => '',
                    'f1_printed_name' => '',
                    'f2_printed_name' => '',
                    'flavor_name' => '',
                    'f1_flavor_name' => '',
                    'f2_flavor_name' => '',
                    'setcode' => 'UST',
                    'number_import' => '65'
                ]
            ],
            []
        );
        $manager = new ImportExportImportStub($db, $GLOBALS['appConfig'], new GameRules([]), 'user@example.test');
        $file = $this->writeTempImportFile([
            '"ust","65","\\"Rumors of My Death . . .\\"","en","2","0","0","' . $uuid . '"'
        ]);

        ob_start();
        $manager->importCollectionRegex($file, 'collection', 'replace', 'user@example.test');
        ob_end_clean();

        @unlink($file);
        $this->assertCount(1, $manager->capturedBatch);
        $this->assertSame($uuid, $manager->capturedBatch[0]['id']);
        $this->assertSame(2, $manager->capturedBatch[0]['normal']);
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

    public function testBatchFailureStopsFlowBeforeOrphanCleanup()
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
        $manager = new ImportExportBatchFailStub($db, $GLOBALS['appConfig'], new GameRules([]), 'user@example.test');
        $file = $this->writeTempImportFile([
            'Academy Manufacturer,SLD,Secret Lair Drop,7094,foil,rare,2,111636,'
                . $uuid . ',13.13,false,false,near_mint,en,AUD'
        ]);

        $startingBufferLevel = ob_get_level();
        try {
            ob_start();
            $manager->importCollectionRegex($file, 'collection', 'add', 'user@example.test');
            ob_end_clean();
            $this->fail('Expected batch failure exception was not thrown.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('Simulated batch failure', $exception->getMessage());
        } finally {
            while (ob_get_level() > $startingBufferLevel) :
                ob_end_clean();
            endwhile;
        }

        @unlink($file);
        $this->assertSame(0, $db->executeQueryCount);
    }

    private function writeTempImportFile(array $lines): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mtg_import_');
        file_put_contents($file, implode("\n", $lines));
        return $file;
    }
}

class ImportExportImportStub extends ImportExport
{
    public array $capturedBatch = [];

    /**
     * @param string $mytable
     * @param string $importType
     * @param int $count
     * @param int $total
     * @param array $batchedCardIds
     */
    public function addCardsBatch(
        string $mytable,
        string $importType,
        int $count,
        int $total,
        array $batchedCardIds
    ): array {
        $this->capturedBatch = array_values($batchedCardIds);
        return ['warnings' => 'none', 'total' => $total, 'batchRows' => count($batchedCardIds)];
    }
}

class ImportExportBatchFailStub extends ImportExport
{
    /**
     * @param string $mytable
     * @param string $importType
     * @param int $count
     * @param int $total
     * @param array $batchedCardIds
     */
    public function addCardsBatch(
        string $mytable,
        string $importType,
        int $count,
        int $total,
        array $batchedCardIds
    ): array {
        unset($mytable, $importType, $count, $total, $batchedCardIds);
        throw new \Exception('Simulated batch failure');
    }
}

class ImportCollectionRegexDbStub
{
    private array $cardsById;
    private array $cardsBySetNumber;
    public string $error = '';
    public int $affected_rows = 0;
    public int $executeQueryCount = 0;

    public function __construct(array $cardsById, array $cardsBySetNumber)
    {
        $this->cardsById = $cardsById;
        $this->cardsBySetNumber = $cardsBySetNumber;
    }

    public function prepare(string $query): ImportCollectionRegexStmtStub
    {
        return new ImportCollectionRegexStmtStub($query, $this);
    }

    public function execute_query(string $query): bool
    {
        unset($query);
        $this->executeQueryCount++;
        return true;
    }

    public function findById(string $id): ?array
    {
        if (isset($this->cardsById[$id])) :
            return $this->cardsById[$id];
        endif;
        return null;
    }

    public function findBySetAndNumber(string $set, string $number): ?array
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

    public function bind_param(string $types, mixed &...$params): bool
    {
        unset($types);
        $this->params = $params;
        return true;
    }

    public function execute(): bool
    {
        return true;
    }

    public function get_result(): ImportCollectionRegexResultStub
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

    public function close(): bool
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

    public function fetch_assoc(): ?array
    {
        return $this->row;
    }
}
