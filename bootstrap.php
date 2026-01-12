<?php

/*
Version:     1.95
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

if (!function_exists('mtgOpenLogFile')) :
    function mtgOpenLogFile(string $path)
    {
        if ($path === '') :
            return false;
        endif;
        return fopen($path, 'ab');
    }
endif;

$iniPath = getenv('MTG_INI_PATH');
if ($iniPath === false || $iniPath === '') :
    $iniPath = '/opt/mtg/mtg_new.ini';
endif;

$dbOverride = null;
$allowDbOverride = (PHP_SAPI === 'cli')
    || (defined('ALLOW_DB_OVERRIDE') && constant('ALLOW_DB_OVERRIDE') === true);
if ($allowDbOverride && isset($GLOBALS['db']) && $GLOBALS['db'] instanceof \mysqli) :
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
    $adminEmail = (string) $appConfig->email('adminEmail', '');
    $emailEnabled = (bool) $appConfig->email('enabled', false);
    $logFile = (string) $appConfig->general('logFile', '');

    date_default_timezone_set((string) $appConfig->general('timezone', 'UTC'));

    $fd = mtgOpenLogFile($logFile);
    if ($fd !== false) :
        $msg = "[ERROR] Fatal database exception: {$err->getMessage()}";
        $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $msg;
        fwrite($fd, $str . "\n");
        fclose($fd);
    else :
        openlog("MTG", LOG_NDELAY, LOG_USER);

        if ($logFile !== '') :
            syslog(
                LOG_ERR,
                "[MTG-DEBUG] bootstrap.php: Can't write to MTG log file ($logFile) "
                . "- check path and permissions. Falling back to syslog."
            );
        endif;

        syslog(LOG_ERR, "[MTG-DEBUG] Fatal database exception: {$err->getMessage()}");
        closelog();
    endif;

    $subject = "Fatal database exception on MTGCollection";
    $tier = (string) $appConfig->general('tier', 'prod');
    $host = gethostname() ?: 'unknown';
    $detail = $err->getMessage()
        . "\n\nContext:"
        . "\n- iniPath: {$iniPath}"
        . "\n- sapi: " . PHP_SAPI
        . "\n- host: {$host}"
        . "\n- tier: {$tier}";
    $message = wordwrap($detail, 70);

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

    if (!headers_sent()) :
        header('Location: /error.php', true, 302);
    else :
        echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
    endif;
    die();
}

// Locals (also currently exported implicitly to callers because this file is included)
$iniArray = $ctx->iniArray();
$appConfig = $ctx->config();
$gameRules = $ctx->rules();
$db = $ctx->db();
$msg = $ctx->message();

// Dev-only deprecation warning
$tierValue = (string) $appConfig->general('tier', 'prod');
$warnDeprecatedGlobals = ($tierValue === 'dev')
    && (defined('MTG_WARN_DEPRECATED_BOOTSTRAP_GLOBALS') ? constant('MTG_WARN_DEPRECATED_BOOTSTRAP_GLOBALS') : true);

if ($warnDeprecatedGlobals) :
    $msg->logMessage(
        '[DEBUG]',
        'bootstrap.php: deprecated ambient variables ($appConfig/$db/$msg/$gameRules/$iniArray). Use $ctx->...'
    );
endif;

$tierValue = (string) $appConfig->general('tier', 'prod');
if ($tierValue === 'dev') :
    error_reporting(E_ALL);
else :
    error_reporting(E_ALL & ~E_NOTICE);
endif;

$logFile = (string) $appConfig->general('logFile', '');
$logLevel = (string) $appConfig->general('logLevel', '');
$fd = mtgOpenLogFile($logFile);
if ($fd === false) :
    if ($logFile !== '') :
        openlog("MTG", LOG_NDELAY, LOG_USER);
        syslog(
            LOG_ERR,
            "[MTG-DEBUG] bootstrap.php: Can't write to MTG log file ($logFile) "
            . "- check path and permissions. Falling back to syslog."
        );
        closelog();
    endif;
    $logFile = '';
else :
    if ($logLevel === '3') :
        $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? 'unknown');
        $logMessage = "[DEBUG] bootstrap.php (direct write to logfile) ({$script}): "
                    . "Successfully checked logfile access to $logFile";
        $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $logMessage;
        fwrite($fd, $str . "\n");
    endif;
    fclose($fd);
endif;

if ($logFile === '') :
    $configOverrides = $appConfig->toArrayRaw();
    $configOverrides['general']['logFile'] = $logFile;
    $appConfig = AppConfig::fromIni($iniArray, $configOverrides);
    $msg = new Message($appConfig);
    $ctx = new AppContext($db, $appConfig, $gameRules, $iniArray, $msg, $ctx->metaAll());
endif;

// Build meta variables into $ctx
$versionFile = APP_ROOT . '/VERSION';
$serviceWorkerVersion = 'v6';
if (file_exists($versionFile)) :
    $serviceWorkerVersion = trim((string) file_get_contents($versionFile));
    if ($serviceWorkerVersion === '') :
        $serviceWorkerVersion = 'v6';
    endif;
endif;
$cssverMeta = AdminSettings::getCssVersionSuffix($db, $appConfig);
$ctx = $ctx->withMeta([
    'serviceWorkerVersion' => $serviceWorkerVersion,
    'cssver' => $cssverMeta
]);

date_default_timezone_set((string) $appConfig->general('timezone', 'UTC'));
$localeini = (string) $appConfig->general('locale', '');
if (setlocale(LC_MONETARY, $localeini) === false) :
    $msg->logMessage('[DEBUG]', "Locale not available for LC_MONETARY: $localeini");
endif;

if (PHP_SAPI !== 'cli') :
    $errorHandler = new ErrorHandler($appConfig);
    $errorHandler->register();
endif;

return $ctx;
