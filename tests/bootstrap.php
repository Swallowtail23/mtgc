<?php

// Basic bootstrap for tests
$GLOBALS['logfile'] = sys_get_temp_dir() . '/phpunit.log';
$GLOBALS['loglevelini'] = 0;
$GLOBALS['logLevelIni'] = 0;

$db = new class {
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function real_escape_string($str)
    {
        return $str;
    }
};
$GLOBALS['db'] = $db;

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
