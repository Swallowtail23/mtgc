<?php

/*
Version:     1.79
Date:        12/01/26
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

$versionFile = APP_ROOT . '/VERSION';
$serviceWorkerVersion = 'v6';
if (file_exists($versionFile)) :
    $serviceWorkerVersion = trim((string) file_get_contents($versionFile));
    if ($serviceWorkerVersion === '') :
        $serviceWorkerVersion = 'v6';
    endif;
endif;

$tierValue = (string) $appConfig->general('tier', 'prod');
if ($tierValue === 'dev') :
    error_reporting(E_ALL);
else :
    error_reporting(E_ALL & ~E_NOTICE);
endif;

$logFile = (string) $appConfig->general('logFile', '');
$logLevel = (string) $appConfig->general('logLevel', '');
if (($fd = fopen($logFile, "a")) === false) :
    openlog("MTG", LOG_NDELAY, LOG_USER);
    syslog(
        LOG_ERR,
        "[MTG-DEBUG] bootstrap.php: Can't write to MTG log file ($logFile) "
        . "- check path and permissions. Falling back to syslog."
    );
    closelog();
    $logFile = 0;
elseif ($logLevel === '3' and ($fd = fopen($logFile, "a")) !== false) :
    $logMessage = "[DEBUG] bootstrap.php (direct write to logfile) ({$_SERVER['PHP_SELF']}): "
                . "Successfully checked logfile access to $logFile";
    $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $logMessage;
    fclose($fd);
endif;

if ($logFile === 0) :
    $configOverrides = $appConfig->toArrayRaw();
    $configOverrides['general']['logFile'] = $logFile;
    $appConfig = AppConfig::fromIni($iniArray, $configOverrides);
    $msg = new Message($appConfig);
    $ctx = new AppContext($db, $appConfig, $gameRules, $iniArray, $msg);
endif;

date_default_timezone_set((string) $appConfig->general('timezone', 'UTC'));
$localeini = (string) $appConfig->general('locale', '');
setlocale(LC_MONETARY, $localeini);



if (PHP_SAPI !== 'cli') :
    $errorHandler = new ErrorHandler($appConfig);
    $errorHandler->register();
endif;

return $ctx;
