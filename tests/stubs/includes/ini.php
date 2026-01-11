<?php

// Stub ini for index tests to avoid real config and DB connections.
require_once __DIR__ . '/../../../src/MTG/Core/AppConfig.php';

$iniArray = [
    'general' => [
        'URL' => 'http://localhost',
        'title' => 'MTG Collection',
        'tier' => 'dev',
        'Loglevel' => 0,
        'ImgLocation' => '/cardimg/',
        'Timezone' => 'UTC',
        'Locale' => 'en_US',
        'Logfile' => sys_get_temp_dir() . '/mtg_test.log',
        'Copyright' => 'Test Copyright'
    ],
    'fx' => [
        'FreecurrencyAPI' => '',
        'TargetCurrency' => 'USD'
    ],
    'security' => [
        'Turnstile' => 'disabled',
        'Turnstile_site_key' => '',
        'Turnstile_secret_key' => '',
        'AdminIP' => '',
        'Badloginlimit' => 3,
        'TrustDuration' => 30
    ],
    'comments' => [
        'Disqus' => 'disabled',
        'DisqusDevURL' => '',
        'DisqusProdURL' => ''
    ],
    'database' => [
        'DBServer' => 'localhost',
        'DBUser' => 'user',
        'DBPass' => 'pass',
        'DBName' => 'db'
    ],
    'email' => [
        'Email' => 'disabled',
        'SMTPDebug' => 0,
        'Host' => '',
        'SMTPAuth' => false,
        'Username' => '',
        'Password' => '',
        'SMTPSecure' => '',
        'Port' => 0,
        'SMTPHelo' => '',
        'SMTPVerifySSL' => 1,
        'AdminEmail' => 'admin@example.com',
        'ServerEmail' => 'server@example.com'
    ]
];

$myURL = $iniArray['general']['URL'];
$siteTitle = $iniArray['general']['title'];
$fxAPI = $iniArray['fx']['FreecurrencyAPI'];
$fxLocal = $iniArray['fx']['TargetCurrency'];
$adminip = 1;
$logLevelIni = $iniArray['general']['Loglevel'];
$adminEmail = $iniArray['email']['AdminEmail'];
$serverEmail = $iniArray['email']['ServerEmail'];
$Badloglimit = $iniArray['security']['Badloginlimit'];
$imgLocation = $iniArray['general']['ImgLocation'];
$copyright = $iniArray['general']['Copyright'];
$trustDuration = $iniArray['security']['TrustDuration'];
$turnstile_site_key = '';
$turnstile_secret_key = '';
$turnstile = 0;
$valid_tribe = [];
$search_langs_codes = ['en'];

$logfile = $iniArray['general']['Logfile'];
$appConfig = \MTG\Core\AppConfig::fromIni($iniArray, [
    'general' => [
        'logLevel' => $iniArray['general']['Loglevel'] ?? 0,
        'logFile' => $logfile,
    ],
    'email' => [
        'enabled' => false,
    ],
]);

if (!isset($GLOBALS['db'])) {
    $GLOBALS['db'] = new class {
        public $error = '';

        public function set_charset($charset)
        {
            return true;
        }

        public function execute_query()
        {
            return new class {
                public $num_rows = 0;

                public function fetch_row()
                {
                    return [0];
                }

                public function fetch_array()
                {
                    return false;
                }

                public function fetch_assoc()
                {
                    return ['usemin' => 0];
                }
            };
        }

        public function query()
        {
            return new class {
                public $num_rows = 0;

                public function fetch_assoc()
                {
                    return false;
                }
            };
        }

        public function real_escape_string($str)
        {
            return $str;
        }
    };
}

$db = $GLOBALS['db'];
