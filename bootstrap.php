<?php

/*
Version:     1.99
Date:        13/01/26
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

$_iniPath = getenv('MTG_INI_PATH');
if ($_iniPath === false || $_iniPath === '') :
    $_iniPath = '/opt/mtg/mtg_new.ini';
endif;

// If defined, mtgDbOverride(): ?mysqli returns a mysqli handle for tests/CLI
$_dbOverride = null;
$_allowDbOverride = (PHP_SAPI === 'cli')
    || (defined('ALLOW_DB_OVERRIDE') && constant('ALLOW_DB_OVERRIDE') === true);
if ($_allowDbOverride && function_exists('mtgDbOverride')) :
    $_candidate = mtgDbOverride();
    if ($_candidate instanceof \mysqli) :
        $_dbOverride = $_candidate;
    endif;
endif;

try {
    $ctx = AppContext::fromIniPath($_iniPath, $_dbOverride);
} catch (Exception $err) {
    $iniArray = [];
    try {
        $ini = new INI($_iniPath);
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
        . "\n- iniPath: {$_iniPath}"
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

// Internal locals for bootstrap-only use.
$_iniArray      = $ctx->iniArray();
$_appConfig     = $ctx->config();
$_gameRules     = $ctx->rules();
$_db            = $ctx->db();
$_msg           = $ctx->message();

$_tierValue     = (string) $_appConfig->general('tier', 'prod');

if ($_tierValue === 'dev') :
    error_reporting(E_ALL);
else :
    error_reporting(E_ALL & ~E_NOTICE);
endif;

date_default_timezone_set((string) $_appConfig->general('timezone', 'UTC'));
$_localeini = (string) $_appConfig->general('locale', '');
if (setlocale(LC_MONETARY, $_localeini) === false) :
    $_msg->logMessage('[DEBUG]', "Locale not available for LC_MONETARY: $_localeini");
endif;

$_logFile       = (string) $_appConfig->general('logFile', '');
$_logLevel      = (string) $_appConfig->general('logLevel', '');
$_fd = mtgOpenLogFile($_logFile);
if ($_fd === false) :
    if ($_logFile !== '') :
        openlog("MTG", LOG_NDELAY, LOG_USER);
        syslog(
            LOG_ERR,
            "[MTG-DEBUG] bootstrap.php: Can't write to MTG log file ($_logFile) "
            . "- check path and permissions. Falling back to syslog."
        );
        closelog();
    endif;
    $_logFile = '';
else :
    if ($_logLevel === '3') :
        $_script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? 'unknown');
        $_logMessage = "[DEBUG] bootstrap.php (direct write to logfile) ({$_script}): "
                    . "Successfully checked logfile access to $_logFile";
        $_str = "[" . date("Y/m/d H:i:s", time()) . "] " . $_logMessage;
        fwrite($_fd, $_str . "\n");
    endif;
    fclose($_fd);
endif;

if ($_logFile === '') :
    $_configOverrides = $_appConfig->toArrayRaw();
    $_configOverrides['general']['logFile'] = $_logFile;
    $_appConfig = AppConfig::fromIni($_iniArray, $_configOverrides);
    $_msg = new Message($_appConfig);
    $ctx = new AppContext($_db, $_appConfig, $_gameRules, $_iniArray, $_msg, $ctx->metaAll());
endif;

// Build meta variables into $ctx
$_versionFile = APP_ROOT . '/VERSION';
$_serviceWorkerVersion = 'v6';
if (file_exists($_versionFile)) :
    $_serviceWorkerVersion = trim((string) file_get_contents($_versionFile));
    if ($_serviceWorkerVersion === '') :
        $_serviceWorkerVersion = 'v6';
    endif;
endif;
$_cssverMeta = AdminSettings::getCssVersionSuffix($_db, $_appConfig);
$ctx = $ctx->withMeta([
    'serviceWorkerVersion' => $_serviceWorkerVersion,
    'cssver' => $_cssverMeta
]);

if (PHP_SAPI !== 'cli') :
    $_errorHandler = new ErrorHandler($_appConfig);
    $_errorHandler->register();
endif;

return $ctx;
