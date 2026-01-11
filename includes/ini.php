<?php

/*
Version:     5.19
Date:        11/01/26
Name:        ini.php
Purpose:     PHP script to manage error routines, logging and setup global variables/arrays
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Core\AppConfig;
use MTG\Core\GameRules;
use MTG\Core\INI;
use MTG\Core\Message;

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

$status = session_status();
if ($status == PHP_SESSION_NONE) :
    //There is no active session
    if (file_exists('sessionname.local.php')) :
        require 'sessionname.local.php';
    else :
        require 'sessionname_template.php';
    endif;
    startCustomSession();
endif;

// Class autoloading
// Composer
$root = realpath($_SERVER["DOCUMENT_ROOT"]);
require_once "$root/vendor/autoload.php";

// Set error reporting based on ini file's dev setting
$ini = new INI("/opt/mtg/mtg_new.ini");
$iniArray = $ini->data;
$myURL = $iniArray['general']['URL'];
$siteTitle = $iniArray['general']['title'];
$versionFile = dirname(__DIR__) . '/VERSION';
$serviceWorkerVersion = 'v6';
if (file_exists($versionFile)) :
    $serviceWorkerVersion = trim((string) file_get_contents($versionFile));
    if ($serviceWorkerVersion === '') :
        $serviceWorkerVersion = 'v6';
    endif;
endif;
$fxAPI = $iniArray['fx']['FreecurrencyAPI'];
$fxLocal = $iniArray['fx']['TargetCurrency'];
if ($iniArray['general']['tier'] === 'dev') :
    $tier = 'dev';
    error_reporting(E_ALL);
    // Dummy Turnstile test keys:

    // Client side:

       $turnstile_site_key = '1x00000000000000000000AA';  // Always pass visible
    // $turnstile_site_key = '1x00000000000000000000BB';  // Always pass invisible
    // $turnstile_site_key = '2x00000000000000000000AB';  // Always block visible
    // $turnstile_site_key = '2x00000000000000000000BB';  // Always block invisible
    // $turnstile_site_key = '3x00000000000000000000FF';  // Use to simulate interactive request

    // Server side:

    $turnstile_secret_key = '1x0000000000000000000000000000000AA'; // Always pass
    // $turnstile_secret_key='2x0000000000000000000000000000000AA'; // Always fail
    // $turnstile_secret_key='3x0000000000000000000000000000000AA'; // Generates token spent error
elseif ($iniArray['general']['tier'] === 'prod') :
    $tier = 'prod';
    error_reporting(E_ALL & ~E_NOTICE);
    $turnstile_site_key = $iniArray['security']['Turnstile_site_key'];
    $turnstile_secret_key = $iniArray['security']['Turnstile_secret_key'];
else :
    $tier = 'prod';
    error_reporting(E_ALL & ~E_NOTICE);
    $turnstile_site_key = $iniArray['security']['Turnstile_site_key'];
    $turnstile_secret_key = $iniArray['security']['Turnstile_secret_key'];
endif;

// Enable Turnstile
if ($iniArray['security']['Turnstile'] !== 'enabled') :
    $turnstile = 0;
else :
    $turnstile = 1;
endif;

// How long to trust trusted devices (in days)
$trustDuration = $iniArray['security']['TrustDuration'];

// Email enable/disable
$emailEnabled = (($iniArray['email']['Email'] ?? 'enabled') === 'enabled');

// Enable Disqus card commenting
if ($iniArray['comments']['Disqus'] !== 'enabled') :
    $disqus = 0;
    $disqusDev = '';
    $disqusProd = '';
else :
    $disqus = 1;
    $disqusDev = $iniArray['comments']['DisqusDevURL'];
    $disqusProd = $iniArray['comments']['DisqusProdURL'];
endif;

//Admin IP
if ($iniArray['security']['AdminIP'] === '') :
    $adminip = 1;
else :
    $adminip = $iniArray['security']['AdminIP'];
endif;

//Logging levels
$logLevelIni = $iniArray['general']['Loglevel'];

//Email settings (PHPMailer, see https://github.com/PHPMailer/PHPMailer
//Note, Debug settings other than SMTP::DEBUG_OFF will have no effect without $iniArray['general']['Loglevel'] = 3
$smtpParameters = [
    'SMTPDebug' => $iniArray['email']['SMTPDebug'],
    'SMTPHost' => $iniArray['email']['Host'],
    'SMTPAuth' => $iniArray['email']['SMTPAuth'],
    'SMTPUsername' => $iniArray['email']['Username'],
    'SMTPPassword' => $iniArray['email']['Password'],
    'SMTPSecure' => $iniArray['email']['SMTPSecure'],
    'SMTPPort' => $iniArray['email']['Port'],
    'SMTPHelo' => $iniArray['email']['SMTPHelo'] ?? gethostname(),
    'SMTPVerifySSL' => $iniArray['email']['SMTPVerifySSL'] ?? 1,
    'globalDebug' => $logLevelIni
];

//Email addresses
$adminEmail = $iniArray['email']['AdminEmail'];
$serverEmail = $iniArray['email']['ServerEmail'];

//Set password parameters
$Badloglimit = $iniArray['security']['Badloginlimit'];

//Card image location
$imgLocation = $iniArray['general']['ImgLocation'];

//Location settings
date_default_timezone_set($iniArray['general']['Timezone']);
$localeini = $iniArray['general']['Locale'];
setlocale(LC_MONETARY, $localeini);  //used to display $ values

//Logfile check
$logfile = $iniArray['general']['Logfile'];
if (($fd = fopen($logfile, "a")) === false) :
    openlog("MTG", LOG_NDELAY, LOG_USER);
    syslog(
        LOG_ERR,
        "[MTG-DEBUG] Ini.php: Can't write to MTG log file ($logfile) "
        . "- check path and permissions. Falling back to syslog."
    );
    closelog();
    $logfile = 0;
elseif ($logLevelIni === '3' and ($fd = fopen($logfile, "a")) !== false) :
    $msg = "[DEBUG] Ini.php (direct write to logfile) ({$_SERVER['PHP_SELF']}): "
         . "Successfully checked logfile access to $logfile";
    $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $msg;
    fclose($fd);
endif;

//Copyright string
$copyright = $iniArray['general']['Copyright'];

$appConfig = AppConfig::fromIni($iniArray, [
    'general' => [
        'tier' => $tier,
        'logLevel' => $logLevelIni,
        'logFile' => $logfile,
        'maxCardDataAge' => $max_card_data_age,
    ],
    'security' => [
        'turnstileEnabled' => ($turnstile === 1),
        'turnstileSiteKey' => $turnstile_site_key,
        'turnstileSecretKey' => $turnstile_secret_key,
        'adminIp' => $adminip,
    ],
    'email' => [
        'enabled' => $emailEnabled,
        'adminEmail' => $adminEmail,
        'serverEmail' => $serverEmail,
        'smtp' => $smtpParameters,
    ],
    'comments' => [
        'disqusEnabled' => ($disqus === 1),
        'disqusDevUrl' => $disqusDev,
        'disqusProdUrl' => $disqusProd,
    ],
]);

//DB connect
define('DB_HOST', $iniArray['database']['DBServer']);  //host
define('DB_USER', $iniArray['database']['DBUser']);    // db username
define('DB_PASS', $iniArray['database']['DBPass']);    // db password
define('DB_NAME', $iniArray['database']['DBName']);    // db name

$dbname = $iniArray['database']['DBName'];

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) :
        throw new Exception(
            'Failed to connect to MySQL Database <br /> Error Info : ' . $db->connect_error
        );
    endif;
    $db->set_charset('utf8mb4');
} catch (Exception $err) {
    if (($fd = fopen($logfile, "a")) !== false) :
        $msg = "[ERROR] Fatal database exception: {$err->getMessage()}";
        $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $msg;
        fwrite($fd, $str . "\n");
        fclose($fd);
    else :
        openlog("MTG", LOG_NDELAY, LOG_USER);
        syslog(LOG_ERR, "[MTG-DEBUG] Fatal database exception: {$err->getMessage()}");
        closelog();
    endif;
    $databaseaccess = 0;
    $from = "From: " . $serverEmail;
    $subject = "Fatal database exception on MTGCollection";
    $message = wordwrap($err->getMessage(), 70);
    if ($emailEnabled) :
        mail($adminEmail, $subject, $message, $from);
    else :
        $fallbackMsg = new Message($appConfig);
        $fallbackMsg->logMessage(
            '[NOTICE]',
            "Email disabled; fatal DB alert not sent to admin ({$err->getMessage()})"
        );
    endif;
    echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
    die();
}


$gameRules = GameRules::fromFile(__DIR__ . '/game_rules.php');
$gameRulesData = $gameRules->all();
foreach ($gameRulesData as $ruleName => $ruleValue) :
    $$ruleName = $ruleValue;
endforeach;
