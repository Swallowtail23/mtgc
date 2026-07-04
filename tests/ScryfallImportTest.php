<?php

/*
Version:     1.1
Date:        04/07/26
Name:        ScryfallImportTest.php
Purpose:     Tests Scryfall bulk metadata helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Bulk\ScryfallImport;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\Message;
use PHPUnit\Framework\TestCase;

class ScryfallImportStub extends ScryfallImport
{
    public static array $fetchMap = [];
    public static int $downloadCalls = 0;
    public static array $downloadResults = [];

    public static function fetchJson(string $url, Message $msg, string $context, AppConfig $appConfig): array|false
    {
        unset($msg, $context, $appConfig);
        return self::$fetchMap[$url] ?? false;
    }

    public static function downloadBulk(
        string $url,
        string $dest,
        Message $msg,
        AppConfig $appConfig,
        string $context = 'downloadBulk',
        bool $debug = false
    ): bool {
        unset($url, $msg, $appConfig, $context, $debug);
        self::$downloadCalls++;
        if (!empty(self::$downloadResults)) :
            $next = array_shift(self::$downloadResults);
            if ($next === false) :
                return false;
            endif;
        endif;
        $dir = dirname($dest);
        if (!is_dir($dir)) :
            mkdir($dir, 0777, true);
        endif;
        file_put_contents($dest, 'test');
        return true;
    }
}

class BulkQueryStub
{
    public int $num_rows;

    public function __construct(int $numRows)
    {
        $this->num_rows = $numRows;
    }

    public function free(): void
    {
    }
}

class BulkDbStub
{
    public string $error = '';
    private array $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function query(string $sql): mixed
    {
        unset($sql);
        return array_shift($this->responses);
    }
}

class ScryfallImportTest extends TestCase
{
    protected function setUp(): void
    {
        ScryfallImportStub::$fetchMap = [];
        ScryfallImportStub::$downloadCalls = 0;
        ScryfallImportStub::$downloadResults = [];
    }

    private function buildAppConfig(array $overrides = []): AppConfig
    {
        $ini = [
            'general' => [
                'URL' => '',
                'title' => '',
                'tier' => 'dev',
                'Loglevel' => '',
                'Logfile' => '',
                'ImgLocation' => '',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => '',
                'MaxCardDataAge' => 0,
            ],
            'security' => [],
            'email' => [
                'Email' => 'enabled',
                'AdminEmail' => '',
                'ServerEmail' => '',
                'SMTPDebug' => '',
                'Host' => '',
                'SMTPAuth' => '',
                'Username' => '',
                'Password' => '',
                'SMTPSecure' => '',
                'Port' => 0,
                'SMTPVerifySSL' => 1,
            ],
            'fx' => [],
            'comments' => [],
        ];

        return AppConfig::fromIni($ini, $overrides);
    }

    public function testGetBulkInfoDefault()
    {
        $defaultUrl = 'https://api.example/default';
        $allUrl = 'https://api.example/all';
        $imgLocation = sys_get_temp_dir() . '/mtg/';
        $appConfig = $this->buildAppConfig([
            'general' => ['imageBaseDir' => $imgLocation],
        ]);
        $gameRules = new GameRules([
            'defaultCardsUrl' => $defaultUrl,
            'allCardsUrl' => $allUrl,
        ]);
        ScryfallImportStub::$fetchMap = [
            $defaultUrl => [
                'type' => 'default_cards',
                'jsonl_download_uri' => 'https://download.example/default.jsonl.gz'
            ]
        ];

        $result = ScryfallImportStub::getBulkInfo('default', $appConfig, $gameRules);

        $this->assertSame(
            [
                'bulkUrl' => 'https://download.example/default.jsonl.gz',
                'fileLocation' => $imgLocation . 'json/bulk.jsonl.gz'
            ],
            $result
        );
    }

    public function testGetBulkInfoRejectsMissingJsonlDownloadUri()
    {
        $defaultUrl = 'https://api.example/default';
        $allUrl = 'https://api.example/all';
        $imgLocation = sys_get_temp_dir() . '/mtg/';
        $appConfig = $this->buildAppConfig([
            'general' => ['imageBaseDir' => $imgLocation],
        ]);
        $gameRules = new GameRules([
            'defaultCardsUrl' => $defaultUrl,
            'allCardsUrl' => $allUrl,
        ]);
        ScryfallImportStub::$fetchMap = [
            $defaultUrl => [
                'type' => 'default_cards',
                'download_uri' => 'https://download.example/default'
            ]
        ];

        $this->assertFalse(ScryfallImportStub::getBulkInfo('default', $appConfig, $gameRules));
    }

    public function testGetBulkInfoRefresh()
    {
        $defaultUrl = 'https://api.example/default';
        $allUrl = 'https://api.example/all';
        $imgLocation = sys_get_temp_dir() . '/mtg/';
        $appConfig = $this->buildAppConfig([
            'general' => ['imageBaseDir' => $imgLocation],
        ]);
        $gameRules = new GameRules([
            'defaultCardsUrl' => $defaultUrl,
            'allCardsUrl' => $allUrl,
        ]);
        ScryfallImportStub::$fetchMap = [
            $defaultUrl => [
                'type' => 'default_cards',
                'jsonl_download_uri' => 'https://download.example/default.jsonl.gz'
            ],
            $allUrl => [
                'type' => 'all_cards',
                'jsonl_download_uri' => 'https://download.example/all.jsonl.gz'
            ]
        ];

        $result = ScryfallImportStub::getBulkInfo('refresh', $appConfig, $gameRules);

        $this->assertSame(
            [
                'bulkUrlDefault' => 'https://download.example/default.jsonl.gz',
                'fileLocationDefault' => $imgLocation . 'json/bulk.jsonl.gz',
                'bulkUrlAll' => 'https://download.example/all.jsonl.gz',
                'fileLocationAll' => $imgLocation . 'json/bulk_all.jsonl.gz',
            ],
            $result
        );
    }

    public function testGetBulkDataFileSkipsFreshFile()
    {
        $appConfig = $this->buildAppConfig();
        $file = tempnam(sys_get_temp_dir(), 'bulk_');
        file_put_contents($file, 'data');
        touch($file, time());

        $result = ScryfallImportStub::getBulkDataFile('https://download.example/file', $file, 3600, $appConfig);

        $this->assertSame('Skipped', $result);
        $this->assertSame(0, ScryfallImportStub::$downloadCalls);
        unlink($file);
    }

    public function testGetBulkDataFileDownloadsWhenMissing()
    {
        $appConfig = $this->buildAppConfig();
        $file = sys_get_temp_dir() . '/bulk_missing.json';
        if (file_exists($file)) :
            unlink($file);
        endif;

        $result = ScryfallImportStub::getBulkDataFile('https://download.example/file', $file, 3600, $appConfig);

        $this->assertSame('Success', $result);
        $this->assertSame(1, ScryfallImportStub::$downloadCalls);
        $this->assertTrue(is_file($file));
        unlink($file);
    }

    public function testGetBulkInfoRejectsMismatchedType()
    {
        $defaultUrl = 'https://api.example/default';
        $imgLocation = sys_get_temp_dir() . '/mtg/';
        $appConfig = $this->buildAppConfig([
            'general' => ['imageBaseDir' => $imgLocation],
        ]);
        $gameRules = new GameRules([
            'defaultCardsUrl' => $defaultUrl,
            'allCardsUrl' => ''
        ]);
        ScryfallImportStub::$fetchMap = [
            $defaultUrl => [
                'type' => 'all_cards',
                'download_uri' => 'https://download.example/all'
            ]
        ];

        $this->assertFalse(ScryfallImportStub::getBulkInfo('default', $appConfig, $gameRules));
    }

    public function testGetBulkInfoRejectsMissingJsonlDownloadUriOnly()
    {
        $defaultUrl = 'https://api.example/default';
        $imgLocation = sys_get_temp_dir() . '/mtg/';
        $appConfig = $this->buildAppConfig([
            'general' => ['imageBaseDir' => $imgLocation],
        ]);
        $gameRules = new GameRules([
            'defaultCardsUrl' => $defaultUrl,
            'allCardsUrl' => ''
        ]);
        ScryfallImportStub::$fetchMap = [
            $defaultUrl => [
                'type' => 'default_cards'
            ]
        ];

        $this->assertFalse(ScryfallImportStub::getBulkInfo('default', $appConfig, $gameRules));
    }

    public function testGetBulkInfoReturnsFalseOnFetchFailure()
    {
        $defaultUrl = 'https://api.example/default';
        $appConfig = $this->buildAppConfig([
            'general' => ['imageBaseDir' => sys_get_temp_dir() . '/mtg/'],
        ]);
        $gameRules = new GameRules([
            'defaultCardsUrl' => $defaultUrl,
            'allCardsUrl' => ''
        ]);

        $this->assertFalse(ScryfallImportStub::getBulkInfo('default', $appConfig, $gameRules));
    }

    public function testGetBulkDataFileDownloadsWhenStale()
    {
        $appConfig = $this->buildAppConfig();
        $file = tempnam(sys_get_temp_dir(), 'bulk_');
        file_put_contents($file, 'data');
        touch($file, time() - 7200);

        ScryfallImportStub::$downloadResults = [true];
        $result = ScryfallImportStub::getBulkDataFile('https://download.example/file', $file, 3600, $appConfig);

        $this->assertSame('Success', $result);
        $this->assertSame(1, ScryfallImportStub::$downloadCalls);
        unlink($file);
    }

    public function testIterateBulkRecordsReadsJsonlGzip()
    {
        $file = tempnam(sys_get_temp_dir(), 'bulk_jsonl_') . '.jsonl.gz';
        $handle = gzopen($file, 'wb');
        $this->assertNotFalse($handle);
        gzwrite($handle, "{\"id\":\"one\",\"name\":\"First\"}\n\n{\"id\":\"two\",\"name\":\"Second\"}\n");
        gzclose($handle);

        $records = iterator_to_array(ScryfallImport::iterateBulkRecords($file), false);

        unlink($file);
        $this->assertSame(
            [
                ['id' => 'one', 'name' => 'First'],
                ['id' => 'two', 'name' => 'Second'],
            ],
            $records
        );
    }

    public function testIterateBulkRecordsReportsJsonlLineError()
    {
        $file = tempnam(sys_get_temp_dir(), 'bulk_jsonl_') . '.jsonl';
        file_put_contents($file, "{\"id\":\"one\"}\n{bad json}\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('line 2');

        try {
            iterator_to_array(ScryfallImport::iterateBulkRecords($file), false);
        } finally {
            unlink($file);
        }
    }

    public function testScryfallImportRejectsInvalidTableName()
    {
        $appConfig = $this->buildAppConfig();
        $gameRules = new GameRules([
            'games_to_include' => ['paper'],
            'langs_to_skip' => [],
            'langs_to_skip_all' => [],
            'layouts_to_skip' => [],
        ]);
        $db = new BulkDbStub([]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid cards table name supplied');

        ScryfallImportStub::scryfallImport('file.json', 'default', 'bad_table', $db, $appConfig, $gameRules);
    }

    public function testScryfallImportRejectsMissingContentHash()
    {
        $appConfig = $this->buildAppConfig();
        $gameRules = new GameRules([
            'games_to_include' => ['paper'],
            'langs_to_skip' => [],
            'langs_to_skip_all' => [],
            'layouts_to_skip' => [],
        ]);
        $db = new BulkDbStub([new BulkQueryStub(0)]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('content_hash column missing');

        ScryfallImportStub::scryfallImport('file.json', 'default', 'cards_scry', $db, $appConfig, $gameRules);
    }

    public function testScryfallImportRejectsMissingPriceHash()
    {
        $appConfig = $this->buildAppConfig();
        $gameRules = new GameRules([
            'games_to_include' => ['paper'],
            'langs_to_skip' => [],
            'langs_to_skip_all' => [],
            'layouts_to_skip' => [],
        ]);
        $db = new BulkDbStub([
            new BulkQueryStub(1),
            new BulkQueryStub(0)
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('price_hash column missing');

        ScryfallImportStub::scryfallImport('file.json', 'default', 'cards_scry', $db, $appConfig, $gameRules);
    }
}
