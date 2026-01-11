<?php

/*
Version:     1.72
Date:        11/01/26
Name:        bootstrap.php
Purpose:     Bootstrap entrypoint returning the app context.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Core\AppConfig;
use MTG\Core\AppContext;
use MTG\Core\ErrorHandler;
use MTG\Core\INI;
use MTG\Core\Message;
use MTG\Core\MyPHPMailer;
use MTG\Admin\AdminSettings;

if (!defined('APP_ROOT')) :
    define('APP_ROOT', __DIR__);
endif;

require_once APP_ROOT . '/vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') :
    if (file_exists(APP_ROOT . '/includes/sessionname.local.php')) :
        require APP_ROOT . '/includes/sessionname.local.php';
    else :
        require APP_ROOT . '/includes/sessionname_template.php';
    endif;
    startCustomSession();
endif;

$iniPath = getenv('MTG_INI_PATH');
if ($iniPath === false || $iniPath === '') :
    $iniPath = '/opt/mtg/mtg_new.ini';
endif;

$dbOverride = null;
if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof \mysqli) :
    $dbOverride = $GLOBALS['db'];
endif;

try {
    $ctx = AppContext::fromIniPath($iniPath, $dbOverride);
} catch (Exception $err) {
    $iniArray = [];
    try {
        $ini = new INI($iniPath);
        $iniArray = is_array($ini->data) ? $ini->data : [];
    } catch (Exception $ignored) {
        $iniArray = [];
    }

    $appConfig = AppConfig::fromIni($iniArray);
    $adminEmail = $iniArray['email']['AdminEmail'] ?? '';
    $emailEnabled = (($iniArray['email']['Email'] ?? 'enabled') === 'enabled');
    $logfile = $iniArray['general']['Logfile'] ?? '';

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

    $subject = "Fatal database exception on MTGCollection";
    $message = wordwrap($err->getMessage(), 70);
    if ($emailEnabled) :
        $mail = new MyPHPMailer(true, $appConfig);
        $mail->sendEmail($adminEmail, false, $subject, $message);
    else :
        $fallbackMsg = new Message($appConfig);
        $fallbackMsg->logMessage(
            '[NOTICE]',
            "Email disabled; fatal DB alert not sent to admin ({$err->getMessage()})"
        );
    endif;
    if (PHP_SAPI === 'cli') :
        fwrite(STDERR, "Fatal database exception: {$err->getMessage()}\n");
        exit(1);
    endif;
    echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
    die();
}

$iniArray = $ctx->iniArray();
$appConfig = $ctx->config();
$gameRules = $ctx->rules();
$db = $ctx->db();
$msg = $ctx->message();

$cssver = AdminSettings::getCssVersionSuffix($db, $appConfig);

$myURL = $iniArray['general']['URL'] ?? '';
$siteTitle = $iniArray['general']['title'] ?? '';

$versionFile = APP_ROOT . '/VERSION';
$serviceWorkerVersion = 'v6';
if (file_exists($versionFile)) :
    $serviceWorkerVersion = trim((string) file_get_contents($versionFile));
    if ($serviceWorkerVersion === '') :
        $serviceWorkerVersion = 'v6';
    endif;
endif;

$fxAPI = $iniArray['fx']['FreecurrencyAPI'] ?? '';
$fxLocal = $iniArray['fx']['TargetCurrency'] ?? '';

$tier = (string) $appConfig->general('tier', 'prod');
if ($tier === 'dev') :
    error_reporting(E_ALL);
else :
    error_reporting(E_ALL & ~E_NOTICE);
endif;

$turnstileEnabled = (bool) $appConfig->security('turnstileEnabled', false);
$turnstile_site_key = (string) $appConfig->security('turnstileSiteKey', '');
$turnstile_secret_key = (string) $appConfig->security('turnstileSecretKey', '');
$turnstile = $turnstileEnabled ? 1 : 0;

$trustDuration = $appConfig->security('trustDuration', 0);

$emailEnabled = (bool) $appConfig->email('enabled', false);

$disqusEnabled = (bool) $appConfig->comments('disqusEnabled', false);
$disqus = $disqusEnabled ? 1 : 0;
$disqusDev = $disqusEnabled ? (string) $appConfig->comments('disqusDevUrl', '') : '';
$disqusProd = $disqusEnabled ? (string) $appConfig->comments('disqusProdUrl', '') : '';

$adminip = $appConfig->security('adminIp', '');
if ($adminip === '') :
    $adminip = 1;
endif;

$logfile = (string) $appConfig->general('logFile', '');
$logLevelIni = (string) $appConfig->general('logLevel', '');
if (($fd = fopen($logfile, "a")) === false) :
    openlog("MTG", LOG_NDELAY, LOG_USER);
    syslog(
        LOG_ERR,
        "[MTG-DEBUG] bootstrap.php: Can't write to MTG log file ($logfile) "
        . "- check path and permissions. Falling back to syslog."
    );
    closelog();
    $logfile = 0;
elseif ($logLevelIni === '3' and ($fd = fopen($logfile, "a")) !== false) :
    $logMessage = "[DEBUG] bootstrap.php (direct write to logfile) ({$_SERVER['PHP_SELF']}): "
                . "Successfully checked logfile access to $logfile";
    $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $logMessage;
    fclose($fd);
endif;

if ($logfile === 0) :
    $configOverrides = $appConfig->toArrayRaw();
    $configOverrides['general']['logFile'] = $logfile;
    $appConfig = AppConfig::fromIni($iniArray, $configOverrides);
    $msg = new Message($appConfig);
    $ctx = new AppContext($db, $appConfig, $gameRules, $iniArray, $msg);
endif;

$smtpParameters = $appConfig->getSmtpParameters();

$adminEmail = (string) $appConfig->email('adminEmail', '');
$serverEmail = (string) $appConfig->email('serverEmail', '');

$Badloglimit = $appConfig->security('badLoginLimit', 0);
$imgLocation = (string) $appConfig->general('imageBaseDir', '');

date_default_timezone_set((string) $appConfig->general('timezone', 'UTC'));
$localeini = (string) $appConfig->general('locale', '');
setlocale(LC_MONETARY, $localeini);

$copyright = $iniArray['general']['Copyright'] ?? '';
$max_card_data_age = $gameRules->get('max_card_data_age', 0);

if (!defined('DB_HOST')) :
    define('DB_HOST', $iniArray['database']['DBServer'] ?? '');
endif;
if (!defined('DB_USER')) :
    define('DB_USER', $iniArray['database']['DBUser'] ?? '');
endif;
if (!defined('DB_PASS')) :
    define('DB_PASS', $iniArray['database']['DBPass'] ?? '');
endif;
if (!defined('DB_NAME')) :
    define('DB_NAME', $iniArray['database']['DBName'] ?? '');
endif;

$dbname = $iniArray['database']['DBName'] ?? '';

$gameRulesData = $gameRules->all();
foreach ($gameRulesData as $ruleName => $ruleValue) :
    $$ruleName = $ruleValue;
endforeach;

if (PHP_SAPI !== 'cli') :
    $errorHandler = new ErrorHandler($appConfig);
    $errorHandler->register();
endif;

return $ctx;
