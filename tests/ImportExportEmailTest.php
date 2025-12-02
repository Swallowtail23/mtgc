<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../classes/importexport.class.php';

class ImportExportEmailTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testExportEmailReturnsFalseWhenSendFails()
    {
        global $emailEnabled, $siteTitle, $logfile;
        $emailEnabled = true;
        $siteTitle = 'MTG Test';
        $logfile = tempnam(sys_get_temp_dir(), 'impexp_');

        if (!class_exists('MyPHPMailer')) {
            eval(
                'class MyPHPMailer
                {
                    public static $calls = [];

                    public function __construct($exceptions, $smtpParameters, $serverEmail, $logfile, $siteTitle = null)
                    {
                        self::$calls[] = [
                            "params" => $smtpParameters,
                            "serverEmail" => $serverEmail,
                            "logfile" => $logfile,
                            "siteTitle" => $siteTitle
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
            $logfile,
            'user@example.com',
            'server@example.com',
            $siteTitle
        );

        $result = $exporter->exportCollectionToCsv(
            'mytable',
            'http://example.com',
            [
                'SMTPHost' => 'smtp.example.com',
                'SMTPHelo' => 'helo.example.com',
                'SMTPPort' => 2525,
                'SMTPAuth' => true,
                'SMTPUsername' => 'user',
                'SMTPPassword' => 'pass',
                'SMTPSecure' => 'tls',
                'SMTPDebug' => 'SMTP::DEBUG_OFF',
                'globalDebug' => 3
            ],
            'email'
        );

        $this->assertFalse($result);
        $this->assertNotEmpty(MyPHPMailer::$calls);

        if ($logfile && file_exists($logfile)) {
            unlink($logfile);
        }
    }
}
