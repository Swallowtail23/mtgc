<?php

use MTG\Bulk\ScryfallImport;
use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use PHPUnit\Framework\TestCase;

class ScryfallImportStub extends ScryfallImport
{
    public static $fetchMap = [];
    public static $downloadCalls = 0;

    public static function fetchJson($url, $msg, $context, AppConfig $appConfig)
    {
        return self::$fetchMap[$url] ?? false;
    }

    public static function downloadBulk(
        $url,
        $dest,
        $msg,
        AppConfig $appConfig,
        $context = 'downloadBulk',
        $debug = false
    ) {
        self::$downloadCalls++;
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
    protected function setUp(): void
    {
        ScryfallImportStub::$fetchMap = [];
        ScryfallImportStub::$downloadCalls = 0;
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
                'download_uri' => 'https://download.example/default'
            ]
        ];

        $result = ScryfallImportStub::getBulkInfo('default', $appConfig, $gameRules);

        $this->assertSame(
            [
                'bulkUrl' => 'https://download.example/default',
                'fileLocation' => $imgLocation . 'json/bulk.json'
            ],
            $result
        );
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
                'download_uri' => 'https://download.example/default'
            ],
            $allUrl => [
                'type' => 'all_cards',
                'download_uri' => 'https://download.example/all'
            ]
        ];

        $result = ScryfallImportStub::getBulkInfo('refresh', $appConfig, $gameRules);

        $this->assertSame(
            [
                'bulkUrlDefault' => 'https://download.example/default',
                'fileLocationDefault' => $imgLocation . 'json/bulk.json',
                'bulkUrlAll' => 'https://download.example/all',
                'fileLocationAll' => $imgLocation . 'json/bulk_all.json',
            ],
            $result
        );
    }

    public function testGetBulkJsonSkipsFreshFile()
    {
        $appConfig = $this->buildAppConfig();
        $file = tempnam(sys_get_temp_dir(), 'bulk_');
        file_put_contents($file, 'data');
        touch($file, time());

        $result = ScryfallImportStub::getBulkJson('https://download.example/file', $file, 3600, $appConfig);

        $this->assertSame('Skipped', $result);
        $this->assertSame(0, ScryfallImportStub::$downloadCalls);
        unlink($file);
    }

    public function testGetBulkJsonDownloadsWhenMissing()
    {
        $appConfig = $this->buildAppConfig();
        $file = sys_get_temp_dir() . '/bulk_missing.json';
        if (file_exists($file)) :
            unlink($file);
        endif;

        $result = ScryfallImportStub::getBulkJson('https://download.example/file', $file, 3600, $appConfig);

        $this->assertSame('Success', $result);
        $this->assertSame(1, ScryfallImportStub::$downloadCalls);
        $this->assertTrue(is_file($file));
        unlink($file);
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
}
