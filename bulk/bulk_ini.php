<?php

/*
Version:     3.2
Date:        25/11/25
Name:        bulk_ini.php
Purpose:     Ini settings for bulk files
Notes:       {none}

History:
    1.0         Ini settings for bulk files
    2.0 02/01/24 Bring card game types variable into here; also bulk file location URLs and languages to import
    3.0 13/01/24 Move to PHPMailer for email output
    3.1 25/11/25 Formatting clean-up
    3.2 25/11/25 Wrapped long log strings
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

//  Class autoloading
/// Composer
require_once "../vendor/autoload.php";

/// Other classes
function autoLoader($class_name)
{
    $class_name_lwr = strtolower($class_name);
    if (file_exists('../classes/' . $class_name_lwr . '.class.php')) :
        include '../classes/' . $class_name_lwr . '.class.php';
    endif;
}

spl_autoload_register('autoLoader');

//Set error reporting based on ini file's dev setting
$ini = new INI("/opt/mtg/mtg_new.ini");
$iniArray = $ini->data;
if ($iniArray['general']['tier'] === 'dev') :
    $tier = 'dev';
    error_reporting(E_ALL);
elseif ($iniArray['general']['tier'] === 'prod') :
    $tier = 'prod';
    error_reporting(E_ALL & ~E_NOTICE);
else :
    $tier = 'prod';
    error_reporting(E_ALL & ~E_NOTICE);
endif;

//Logging levels
$logLevelIni = $iniArray['general']['Loglevel'];

//Email settings (PHPMailer, see https://github.com/PHPMailer/PHPMailer
//Note, Debug settings other than SMTP::DEBUG_OFF will have no effect without $iniArray['general']['Loglevel'] = 3
$smtpParameters =   [
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
        "[MTG-DEBUG] Ini.php: Can't write to MTG log file ($logfile) - check path and permissions. "
        . "Falling back to syslog."
    );
    closelog();
    $logfile = 0;
elseif ($logLevelIni === '3' and ($fd = fopen($logfile, "a")) !== false) :
    $msg = "[DEBUG] Ini.php (direct write to logfile) ({$_SERVER['PHP_SELF']}): Successfully checked logfile "
        . "access to $logfile";
    $str = "[" . date("Y/m/d H:i:s", time()) . "] " . $msg;
    fclose($fd);
endif;

//Web root URL and site title
$myURL = $iniArray['general']['URL'];
$siteTitle = $iniArray['general']['title'];

//DB connect
define('DB_HOST', $iniArray['database']['DBServer']);  //host
define('DB_USER', $iniArray['database']['DBUser']);    // db username
define('DB_PASS', $iniArray['database']['DBPass']);    // db password
define('DB_NAME', $iniArray['database']['DBName']);    // db name

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) :
        throw new Exception('Failed to connect to MySQL Database <br /> Error Info : ' . $db->connect_error);
    endif;
    $db->set_charset('utf8mb4');
//
//try {
//    $db = new Mysqli_Manager();
//    $db->conn(); // connect DB
//    $db->set_charset("utf8mb4");
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
    if (isset($emailEnabled) && $emailEnabled === true) :
        mail($adminEmail, $subject, $message, $from);
    endif;
    echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
    die();
}

//Primary definition is in ini.php - used here for image retrieval for new cards
$twoCardDetailSections = array('transform',
                                  'modal_dfc',
                                  'reversible_card',
                                  'double_faced_token',
                                  'battle',
                                  'art_series');

// Langs and layouts (used in card bulk)

/// Languages to ignore in Default cards download (currently importing all)
// $langs_to_skip = ['fr','es','it','zhs','sa','he','de','ru','ar','grc','la','zht','ko','pt'];
$langs_to_skip = [];

/// Languages to ignore in All cards download (currently importing all)
// $langs_to_skip_all = ['fr','es','it','zhs','sa','he','de','ru','ar','grc','la','zht','ko','pt'];
$langs_to_skip_all = [];

/// Layouts to skip (currently empty, so all layouts are imported)
$layouts_to_skip = [];

// Which type of cards to include
$games_to_include = ['paper','arena'];

// Where to get URL of latest bulk downloads
$default_cards_url = "https://api.scryfall.com/bulk-data/default-cards";
$all_cards_url = "https://api.scryfall.com/bulk-data/all-cards";

$importLinestoIgnore = array(
                    "Creatures",
                    "Instants and Sorceries",
                    "Other",
                    "Lands",
                    "Sideboard",
                    "Notes",
                    "Sideboard notes",
                    "Planes and Phenomena"
);

$commander_decktypes = array('Commander',
                             'Tiny Leader');

// Setcodes to not include by default when card-adding (i.e. excluding plst in favour of originals)
$nonPreferredSetCodes = array('plst','sld','spg');
