<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use MTG\Cards\ImportExport;
use MTG\Core\AppConfig;
use MTG\Core\MyPHPMailer;

require_once __DIR__ . '/bootstrap.php';

class ImportExportEmailTest extends TestCase
{
    private function buildConfig(string $logfile, bool $emailEnabled): AppConfig
    {
        $iniArray = [
            'general' => [
                'URL' => 'https://test.example',
                'title' => 'Test',
                'tier' => 'dev',
                'Loglevel' => 0,
                'Logfile' => $logfile,
                'ImgLocation' => '',
                'Timezone' => 'UTC',
                'Locale' => 'en_US',
                'Copyright' => ''
            ],
            'security' => [
                'Turnstile' => 'disabled',
                'Turnstile_site_key' => '',
                'Turnstile_secret_key' => '',
                'TrustDuration' => 0,
                'Badloginlimit' => 0,
                'AdminIP' => ''
            ],
            'email' => [
                'Email' => $emailEnabled ? 'enabled' : 'disabled',
                'AdminEmail' => 'admin@example.test',
                'ServerEmail' => 'server@example.test',
                'SMTPDebug' => 'SMTP::DEBUG_OFF',
                'Host' => 'smtp.example.com',
                'SMTPAuth' => true,
                'Username' => 'user',
                'Password' => 'pass',
                'SMTPSecure' => 'tls',
                'Port' => 2525,
                'SMTPHelo' => 'helo.example.com',
                'SMTPVerifySSL' => 1
            ],
            'fx' => [
                'FreecurrencyAPI' => '',
                'TargetCurrency' => ''
            ],
            'comments' => [
                'Disqus' => 'disabled',
                'DisqusDevURL' => '',
                'DisqusProdURL' => ''
            ],
        ];

        return AppConfig::fromIni($iniArray, [
            'general' => [
                'logLevel' => 0,
                'logFile' => $logfile,
            ],
            'email' => [
                'enabled' => $emailEnabled,
            ],
        ]);
    }
    #[RunInSeparateProcess]
    public function testExportEmailReturnsFalseWhenSendFails()
    {
        $logfile = tempnam(sys_get_temp_dir(), 'impexp_');
        $appConfig = $this->buildConfig($logfile, true);

        if (!class_exists(MyPHPMailer::class, false)) {
            eval(
                'namespace MTG\Core;
                class MyPHPMailer
                {
                    public static $calls = [];

                    public function __construct($exceptions, $appConfig)
                    {
                        self::$calls[] = [
                            "logfile" => $appConfig ? $appConfig->general("logFile", "") : ""
                        ];
                    }

                    public function sendEmail(...$args)
                    {
                        self::$calls[] = ["sendEmail" => $args];
                        return false;
                    }
                }'
            );
        }

        $db = new class {
            public function real_escape_string($table)
            {
                return $table;
            }

            public function query($sql)
            {
                $fields = [
                    'setcode',
                    'number_import',
                    'name',
                    'lang',
                    'normal',
                    'foil',
                    'etched',
                    'scryfall_id'
                ];
                $rows = [
                    ['SET', '123', 'Card Name', 'EN', 1, 0, 0, 'abcd1234']
                ];

                return new class ($fields, $rows) {
                    private $fields;
                    private $rows;
                    private $index = 0;
                    public $field_count;

                    public function __construct($fields, $rows)
                    {
                        $this->fields = $fields;
                        $this->rows = $rows;
                        $this->field_count = count($fields);
                    }

                    public function fetch_fields()
                    {
                        return array_map(
                            function ($name) {
                                $field = new stdClass();
                                $field->name = $name;
                                return $field;
                            },
                            $this->fields
                        );
                    }

                    public function fetch_row()
                    {
                        if ($this->index >= count($this->rows)) {
                            return null;
                        }
                        return array_values($this->rows[$this->index++]);
                    }
                };
            }
        };

        $exporter = new ImportExport(
            $db,
            $appConfig,
            'user@example.com'
        );

        $extraAttachments = [
            ['path' => '/tmp/extra.csv', 'name' => 'extra.csv']
        ];
        $result = $exporter->exportCollectionToCsv(
            'mytable',
            'http://example.com',
            'email',
            'export.csv',
            '',
            '',
            $extraAttachments
        );

        $this->assertFalse($result);
        $this->assertNotEmpty(MyPHPMailer::$calls);
        $lastCall = end(MyPHPMailer::$calls);
        $this->assertArrayHasKey('sendEmail', $lastCall);
        $sendArgs = $lastCall['sendEmail'];
        $this->assertSame($extraAttachments, $sendArgs[7] ?? null);

        if ($logfile && file_exists($logfile)) {
            unlink($logfile);
        }
    }

    public function testBuildCollectionCsvReturnsCsvString()
    {
        $db = new class {
            public function real_escape_string($table)
            {
                return $table;
            }

            public function query($sql)
            {
                $fields = [
                    'setcode',
                    'number_import',
                    'name',
                    'lang',
                    'normal',
                    'foil',
                    'etched',
                    'scryfall_id'
                ];
                $rows = [
                    ['SET', '123', 'Card Name', 'EN', 1, 0, 0, 'abcd1234']
                ];

                return new class ($fields, $rows) {
                    private $fields;
                    private $rows;
                    private $index = 0;
                    public $field_count;

                    public function __construct($fields, $rows)
                    {
                        $this->fields = $fields;
                        $this->rows = $rows;
                        $this->field_count = count($fields);
                    }

                    public function fetch_fields()
                    {
                        return array_map(
                            function ($name) {
                                $field = new stdClass();
                                $field->name = $name;
                                return $field;
                            },
                            $this->fields
                        );
                    }

                    public function fetch_row()
                    {
                        if ($this->index >= count($this->rows)) {
                            return null;
                        }
                        return array_values($this->rows[$this->index++]);
                    }
                };
            }
        };

        $logfile = tempnam(sys_get_temp_dir(), 'impexp_');
        $exporter = new ImportExport(
            $db,
            $this->buildConfig($logfile, false),
            'user@example.com'
        );

        $csv = $exporter->buildCollectionCsv('mytable');

        $this->assertIsString($csv);
        $this->assertStringContainsString(
            '"setcode","number_import","name","lang","normal","foil","etched","scryfall_id"',
            $csv
        );
        $this->assertStringContainsString('"SET","123","Card Name","EN","1","0","0","abcd1234"', $csv);

        if ($logfile && file_exists($logfile)) {
            unlink($logfile);
        }
    }
}
