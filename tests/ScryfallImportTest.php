<?php

namespace MTG\Bulk {
    function fetchJson($url, $msg, $context)
    {
        $map = $GLOBALS['scryfallFetchMap'] ?? [];
        return $map[$url] ?? false;
    }

    function downloadBulk($url, $dest, $msg, $context = 'downloadBulk', $debug = false)
    {
        $GLOBALS['scryfallDownloadCalls'] = ($GLOBALS['scryfallDownloadCalls'] ?? 0) + 1;
        $dir = dirname($dest);
        if (!is_dir($dir)) :
            mkdir($dir, 0777, true);
        endif;
        file_put_contents($dest, 'test');
        return true;
    }
}

namespace {
    use MTG\Bulk\ScryfallImport;
    use PHPUnit\Framework\TestCase;

    class BulkQueryStub
    {
        public $num_rows;

        public function __construct($numRows)
        {
            $this->num_rows = $numRows;
        }

        public function free()
        {
        }
    }

    class BulkDbStub
    {
        public $error = '';
        private $responses;

        public function __construct(array $responses)
        {
            $this->responses = $responses;
        }

        public function query($sql)
        {
            return array_shift($this->responses);
        }
    }

    class ScryfallImportTest extends TestCase
    {
        private $originalDb;

        protected function setUp(): void
        {
            $GLOBALS['scryfallFetchMap'] = [];
            $GLOBALS['scryfallDownloadCalls'] = 0;
            $this->originalDb = $GLOBALS['db'] ?? null;
        }

        protected function tearDown(): void
        {
            $GLOBALS['db'] = $this->originalDb;
        }

        public function testGetBulkInfoDefault()
        {
            $GLOBALS['defaultCardsUrl'] = 'https://api.example/default';
            $GLOBALS['allCardsUrl'] = 'https://api.example/all';
            $GLOBALS['imgLocation'] = sys_get_temp_dir() . '/mtg/';
            $GLOBALS['scryfallFetchMap'] = [
                $GLOBALS['defaultCardsUrl'] => [
                    'type' => 'default_cards',
                    'download_uri' => 'https://download.example/default'
                ]
            ];

            $result = ScryfallImport::getBulkInfo('default');

            $this->assertSame(
                [
                    'bulkUrl' => 'https://download.example/default',
                    'fileLocation' => $GLOBALS['imgLocation'] . 'json/bulk.json'
                ],
                $result
            );
        }

        public function testGetBulkInfoRefresh()
        {
            $GLOBALS['defaultCardsUrl'] = 'https://api.example/default';
            $GLOBALS['allCardsUrl'] = 'https://api.example/all';
            $GLOBALS['imgLocation'] = sys_get_temp_dir() . '/mtg/';
            $GLOBALS['scryfallFetchMap'] = [
                $GLOBALS['defaultCardsUrl'] => [
                    'type' => 'default_cards',
                    'download_uri' => 'https://download.example/default'
                ],
                $GLOBALS['allCardsUrl'] => [
                    'type' => 'all_cards',
                    'download_uri' => 'https://download.example/all'
                ]
            ];

            $result = ScryfallImport::getBulkInfo('refresh');

            $this->assertSame(
                [
                    'bulkUrlDefault' => 'https://download.example/default',
                    'fileLocationDefault' => $GLOBALS['imgLocation'] . 'json/bulk.json',
                    'bulkUrlAll' => 'https://download.example/all',
                    'fileLocationAll' => $GLOBALS['imgLocation'] . 'json/bulk_all.json',
                ],
                $result
            );
        }

        public function testGetBulkJsonSkipsFreshFile()
        {
            $file = tempnam(sys_get_temp_dir(), 'bulk_');
            file_put_contents($file, 'data');
            touch($file, time());

            $result = ScryfallImport::getBulkJson('https://download.example/file', $file, 3600);

            $this->assertSame('Skipped', $result);
            $this->assertSame(0, $GLOBALS['scryfallDownloadCalls']);
            unlink($file);
        }

        public function testGetBulkJsonDownloadsWhenMissing()
        {
            $file = sys_get_temp_dir() . '/bulk_missing.json';
            if (file_exists($file)) :
                unlink($file);
            endif;

            $result = ScryfallImport::getBulkJson('https://download.example/file', $file, 3600);

            $this->assertSame('Success', $result);
            $this->assertSame(1, $GLOBALS['scryfallDownloadCalls']);
            $this->assertTrue(is_file($file));
            unlink($file);
        }

        public function testScryfallImportRejectsInvalidTableName()
        {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Invalid cards table name supplied');

            ScryfallImport::scryfallImport('file.json', 'default', 'bad_table');
        }

        public function testScryfallImportRejectsMissingContentHash()
        {
            $GLOBALS['db'] = new BulkDbStub([new BulkQueryStub(0)]);

            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('content_hash column missing');

            ScryfallImport::scryfallImport('file.json', 'default', 'cards_scry');
        }
    }
}
