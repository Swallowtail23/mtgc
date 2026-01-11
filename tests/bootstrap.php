<?php

// Basic bootstrap for tests
if (!defined('APP_ROOT')) :
    define('APP_ROOT', realpath(__DIR__ . '/..'));
endif;

$GLOBALS['logfile'] = sys_get_temp_dir() . '/phpunit.log';
$GLOBALS['loglevelini'] = 0;
$GLOBALS['logLevelIni'] = 0;

$iniPath = sys_get_temp_dir() . '/mtg_test.ini';
if (!file_exists($iniPath)) :
    $iniContents = <<<INI
[general]
URL = "https://test.example"
title = "Test"
tier = "dev"
Loglevel = 0
Logfile = "/tmp/mtg_test.log"
ImgLocation = "/tmp/cardimg/"
Timezone = "UTC"
Locale = "en_US"
Copyright = ""

[security]
Turnstile = "disabled"
Turnstile_site_key = ""
Turnstile_secret_key = ""
AdminIP = ""
Badloginlimit = 0
TrustDuration = 0

[email]
Email = "disabled"
AdminEmail = "admin@example.test"
ServerEmail = "server@example.test"
SMTPDebug = 0
Host = ""
SMTPAuth = ""
Username = ""
Password = ""
SMTPSecure = ""
Port = 25

[fx]
FreecurrencyAPI = ""
TargetCurrency = ""

[comments]
Disqus = "disabled"
DisqusDevURL = ""
DisqusProdURL = ""

[database]
DBServer = "localhost"
DBUser = "user"
DBPass = "pass"
DBName = "db"
INI;
    file_put_contents($iniPath, $iniContents);
endif;
putenv("MTG_INI_PATH=$iniPath");

if (!class_exists('DummyMysqli')) :
    class DummyMysqli extends \mysqli
    {
        public function __construct()
        {
        }

        public function set_charset(string $charset): bool
        {
            return true;
        }

        public function execute_query(string $query, ?array $params = null): \mysqli_result|bool
        {
            $lowerQuery = strtolower($query);
            $row = [];
            if (strpos($lowerQuery, 'select usemin') !== false) :
                $row = ['usemin' => 0];
            elseif (strpos($lowerQuery, 'select mtce') !== false) :
                $row = ['mtce' => 0];
            elseif (strpos($lowerQuery, 'select admin') !== false && strpos($lowerQuery, 'from users') !== false) :
                $row = ['admin' => 0];
            elseif (strpos($lowerQuery, 'count(') !== false) :
                $row = ['count' => 0];
            endif;

            return new class ($row) extends \mysqli_result {
                private array $row;

                public function __construct(array $row)
                {
                    $this->row = $row;
                }

                public int|string $num_rows = 0;

                public function fetch_row(): array|null
                {
                    if (!empty($this->row)) :
                        return [reset($this->row)];
                    endif;
                    return [0];
                }

                public function fetch_array(int $mode = MYSQLI_BOTH): array|false|null
                {
                    if (!empty($this->row)) :
                        return $this->row;
                    endif;
                    return false;
                }

                public function fetch_assoc(): array|null
                {
                    if (!empty($this->row)) :
                        return $this->row;
                    endif;
                    return [];
                }

                public function free(): void
                {
                }
            };
        }

        public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): \mysqli_result|bool
        {
            $lowerQuery = strtolower($query);
            if (strpos($lowerQuery, 'create table') !== false) :
                return true;
            endif;

            $row = [];
            if (strpos($lowerQuery, 'show tables like') !== false) :
                $row = ['table' => 'collectionTemplate'];
            endif;

            return new class ($row) extends \mysqli_result {
                private array $row;

                public function __construct(array $row)
                {
                    $this->row = $row;
                }

                public int|string $num_rows = 0;

                public function fetch_assoc(): array|null
                {
                    if (!empty($this->row)) :
                        return $this->row;
                    endif;
                    return [];
                }

                public function free(): void
                {
                }
            };
        }

        // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
        public function real_escape_string(string $str): string
        {
            return $str;
        }
    }
endif;

$GLOBALS['db'] = new DummyMysqli();

$bracketsInNames = [];
$importLinestoIgnore = [];

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) :
    require_once $autoload;
endif;
if (class_exists('MTG\\Core\\AppConfig')) :
    $iniArray = [
        'general' => [
            'URL' => 'https://test.example',
            'title' => 'Test',
            'tier' => 'dev',
            'Loglevel' => 0,
            'Logfile' => $GLOBALS['logfile'],
            'ImgLocation' => sys_get_temp_dir() . '/cardimg/',
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
            'Email' => 'disabled',
            'AdminEmail' => 'admin@example.test',
            'ServerEmail' => 'server@example.test',
            'SMTPDebug' => 'SMTP::DEBUG_OFF',
            'Host' => 'localhost',
            'SMTPAuth' => '',
            'Username' => '',
            'Password' => '',
            'SMTPSecure' => '',
            'Port' => 25,
            'SMTPHelo' => 'localhost',
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
    $GLOBALS['appConfig'] = \MTG\Core\AppConfig::fromIni($iniArray, [
        'general' => [
            'logLevel' => 0,
            'logFile' => $GLOBALS['logfile'],
        ],
        'email' => [
            'enabled' => false,
        ],
    ]);
endif;
