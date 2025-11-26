<?php

// Stub ini for index tests to avoid real config and DB connections.

$ini_array = [
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
        'SMTPDebug' => 0,
        'Host' => '',
        'SMTPAuth' => false,
        'Username' => '',
        'Password' => '',
        'SMTPSecure' => '',
        'Port' => 0,
        'AdminEmail' => 'admin@example.com',
        'ServerEmail' => 'server@example.com'
    ]
];

$myURL = $ini_array['general']['URL'];
$siteTitle = $ini_array['general']['title'];
$fxAPI = $ini_array['fx']['FreecurrencyAPI'];
$fxLocal = $ini_array['fx']['TargetCurrency'];
$adminip = 1;
$loglevelini = $ini_array['general']['Loglevel'];
$adminemail = $ini_array['email']['AdminEmail'];
$serveremail = $ini_array['email']['ServerEmail'];
$Badloglimit = $ini_array['security']['Badloginlimit'];
$ImgLocation = $ini_array['general']['ImgLocation'];
$copyright = $ini_array['general']['Copyright'];
$trustDuration = $ini_array['security']['TrustDuration'];
$turnstile_site_key = '';
$turnstile_secret_key = '';
$turnstile = 0;
$valid_tribe = [];
$search_langs_codes = ['en'];

$logfile = $ini_array['general']['Logfile'];

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
