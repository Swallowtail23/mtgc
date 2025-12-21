<?php

/*
Version:     5.11
Date:        21/12/25
Name:        ini.php
Purpose:     PHP script to manage error routines, logging and setup global variables/arrays
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

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
$ini = new \MTG\Core\INI("/opt/mtg/mtg_new.ini");
$iniArray = $ini->data;
$myURL = $iniArray['general']['URL'];
$siteTitle = $iniArray['general']['title'];
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
        $fallbackMsg = new \MTG\Core\Message($logfile);
        $fallbackMsg->logMessage(
            '[NOTICE]',
            "Email disabled; fatal DB alert not sent to admin ({$err->getMessage()})"
        );
    endif;
    echo "<meta http-equiv='refresh' content='0;url=/error.php'>";
    die();
}

/** How old must card data be to trigger automatic refresh, in hours **/
$max_data_age_in_hours = 0.25; // Set age in hours here

$seconds_in_hour = 3600;
$max_card_data_age = $seconds_in_hour * $max_data_age_in_hours;

/** Define card types and variables which require special treatment **/

// Valid tribes
$valid_tribe = array(
    "merfolk",
    "spider",
    "goblin",
    "treefolk",
    "sliver",
    "human",
    "zombie",
    "vampire",
    "elf"
                    );

// Valid search languages
$search_langs = array(
                        array(
                            'code' => 'en',
                            'pretty' => 'English'
                            ),
                        array(
                            'code' => 'es',
                            'pretty' => 'Spanish'
                            ),
                        array(
                            'code' => 'fr',
                            'pretty' => 'French'
                            ),
                        array(
                            'code' => 'de',
                            'pretty' => 'German'
                            ),
                        array(
                            'code' => 'it',
                            'pretty' => 'Italian'
                            ),
                        array(
                            'code' => 'pt',
                            'pretty' => 'Portuguese'
                            ),
                        array(
                            'code' => 'ja',
                            'pretty' => 'Japanese'
                            ),
                        array(
                            'code' => 'ko',
                            'pretty' => 'Korean'
                            ),
                        array(
                            'code' => 'ru',
                            'pretty' => 'Russian'
                            ),
                        array(
                            'code' => 'zhs',
                            'pretty' => 'Chinese (simplified)'
                            ),
                        array(
                            'code' => 'zht',
                            'pretty' => 'Chinese (traditional)'
                            ),
                        array(
                            'code' => 'he',
                            'pretty' => 'Hebrew'
                            ),
                        array(
                            'code' => 'la',
                            'pretty' => 'Latin'
                            ),
                        array(
                            'code' => 'grc',
                            'pretty' => 'Ancient Greek'
                            ),
                        array(
                            'code' => 'ar',
                            'pretty' => 'Arabic'
                            ),
                        array(
                            'code' => 'sa',
                            'pretty' => 'Sanskrit'
                            ),
                        array(
                            'code' => 'ph',
                            'pretty' => 'Phyrexian'
                            )
                      );
$search_langs_codes = array_column($search_langs, 'code');

// Selectable currencies
$currencies = array(
                        array(
                            'code' => 'zzz',
                            'pretty' => 'None',
                            'db' => null
                            ),
                        array(
                            'code' => 'aud',
                            'pretty' => 'Australian $',
                            'db' => 'aud'
                            ),
                        array(
                            'code' => 'cad',
                            'pretty' => 'Canadian $',
                            'db' => 'cad'
                            ),
                        array(
                            'code' => 'eur',
                            'pretty' => 'Euro €',
                            'db' => 'eur'
                            ),
                        array(
                            'code' => 'gbp',
                            'pretty' => 'British £',
                            'db' => 'gbp'
                            ),
                        array(
                            'code' => 'jpy',
                            'pretty' => 'Japanese ¥',
                            'db' => 'jpy'
                            ),
                        array(
                            'code' => 'nzd',
                            'pretty' => 'New Zealand $',
                            'db' => 'nzd'
                            )
);

// Card layouts which get a flip button
$flip_button_cards = array('transform',
                           'modal_dfc',
                           'reversible_card',
                           'double_faced_token',
                           'battle');

// Card layouts which need two detail sections on card detail page
// Also needs to be defined in bulk_ini.php
$twoCardDetailSections = array('transform',
                                  'modal_dfc',
                                  'reversible_card',
                                  'double_faced_token',
                                  'battle',
                                  'art_series');

// Two layouts, array to drive looking for face 1 content for primary card info on card detail page
$layouts_double = array('transform',
                        'modal_dfc',
                        'reversible_card',
                        'double_faced_token',
                        'battle',
                        'adventure',
                        'split',
                        'flip');

// Token layouts
$token_layouts = array('double_faced_token',
                       'token',
                       'emblem');

// Layouts needing rotation
$image90rotate = array('split',
                       'planar',
                       'Battle — Siege');

// Commander deck types (also in bulk_ini)
$commander_decktypes = array('Commander',
                             'Tiny Leader');

// Cards legal for multiples in Commander
$commander_multiples = array("Basic Land",
                             "Basic Snow Land");

$any_quantity = array("A deck can have any number of cards named"); // E.g. Relentless Rats

//Commander variations
$valid_commander_text = array("can be your commander"); // Check for abilities which allow card to be a commander

$second_commander_text = array("Partner",
                               "Friends forever",
                               "Doctor's companion");   // Check for abilities which allow card to be second commander

$second_commander_only_type = array("Background");      // Check for "Type" valid ONLY in second commander slot

// Selectable deck types on deck detail page
$validtypes = array('Commander',
                    'Casual',
                    'Tiny Leader',
                    'Standard',
                    'Modern',
                    'Wishlist');

// Card layouts to NOT import in deck quick add routine
$noQuickAddLayouts = array(
                    'token',
                    'double_faced_token',
                    'emblem',
                    'meld',
                    'art_series');

// Cards with brackets contents in names (not currently needed or used, see inputInterpreter())
$bracketsInNames = array(
                    "cont'd",
                    'Front Card',
                    '2000',
                    "Not the Urza's Legacy One",
                    'minigame',
                    'Bevy of Beebles',
                    'Big Furry Monster',
                    '1999',
                    '2000',
                    '2001',
                    'Used',
                    'Theme');

// This def also in bulk_ini
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

// Cards required per deck type for legal play
$hundredcarddecks = array('Commander');

$sixtycarddecks = array('Casual',
                        'Standard',
                        'Modern');

$fiftycarddecks = array('Tiny Leader');

// Setcodes to not include by default when card-adding (i.e. excluding plst in favour of originals)
$nonPreferredSetCodes = array('plst','sld','spg');

// Which database field holds information about card legality in the deck types
$deck_legality_map = array(
                        array(
                            'decktype' => 'Commander',
                            'db_field' => 'legalitycommander'
                            ),
                        array(
                            'decktype' => 'Standard',
                            'db_field' => 'legalitystandard'
                            ),
                        array(
                            'decktype' => 'Tiny Leader',
                            'db_field' => 'legalitytinyleaderscommander'
                            ),
                        array(
                            'decktype' => 'Modern',
                            'db_field' => 'legalitymodern'
                            ),
                        array(
                            'decktype' => 'Casual',
                            'db_field' => ''
                            ),
                        array(
                            'decktype' => 'Wishlist',
                            'db_field' => ''
                            )
);

//Promo types to show on Card Detail page
$promos_to_show = array(
                        array(
                            'promotype' => 'thick',
                            'display' => 'Thick card (commander proxy)'
                            ),
                        array(
                            'promotype' => 'serialized',
                            'display' => 'Serialised card'
                            ),
                        array(
                            'promotype' => 'godzillaseries',
                            'display' => 'Godzilla card'
                            ),
                        array(
                            'promotype' => 'buyabox',
                            'display' => 'Buy-a-box card'
                            ),
                        array(
                            'promotype' => 'oilslick',
                            'display' => 'Oil slick foil'
                            ),
                        array(
                            'promotype' => 'ripplefoil',
                            'display' => 'Ripple foil'
                            ),
                        array(
                            'promotype' => 'surgefoil',
                            'display' => 'Surge foil'
                            ),
                        array(
                            'promotype' => 'doublerainbow',
                            'display' => 'Double rainbow foil'
                            ),
                        array(
                            'promotype' => 'boosterfun',
                            'display' => 'Booster fun'
                            ),
                        array(
                            'promotype' => 'stepandcompleat',
                            'display' => 'Step-and-Compleat Phyrexian foil'
                            ),
                        array(
                            'promotype' => 'datestamped',
                            'display' => 'Date stamped'
                            ),
                        array(
                            'promotype' => 'fnm',
                            'display' => 'Friday Night Magic'
                            ),
                        array(
                            'promotype' => 'arenaleague',
                            'display' => 'Arena League'
                            ),
                        array(
                            'promotype' => 'storechampionship',
                            'display' => 'Store Championship'
                            ),
                        array(
                            'promotype' => 'prerelease',
                            'display' => 'Prelease'
                            ),
                        array(
                            'promotype' => 'mediainsert',
                            'display' => 'Media Insert'
                            ),
                        array(
                            'promotype' => 'starterdeck',
                            'display' => 'Starter Deck'
                            ),
                        array(
                            'promotype' => 'promopack',
                            'display' => 'Promo pack'
                            ),
                        array(
                            'promotype' => 'stamped',
                            'display' => 'Stamped'
                            ),
                        array(
                            'promotype' => 'setpromo',
                            'display' => 'Set promo'
                            ),
                        array(
                            'promotype' => 'silverfoil',
                            'display' => 'Silver foil'
                            ),
                        array(
                            'promotype' => 'galaxyfoil',
                            'display' => 'Galaxy foil'
                            ),
                        array(
                            'promotype' => 'tourney',
                            'display' => 'Tournament promo'
                            ),
                        array(
                            'promotype' => 'planeswalkerdeck',
                            'display' => 'Planeswalker deck card'
                            ),
                        array(
                            'promotype' => 'instore',
                            'display' => 'In-store promo card'
                            ),
                        array(
                            'promotype' => 'judgegift',
                            'display' => 'Judge gift program card'
                            ),
                        array(
                            'promotype' => 'halofoil',
                            'display' => 'Halo foil'
                            ),
                        array(
                            'promotype' => 'boxtopper',
                            'display' => 'Box topper card'
                            ),
                        array(
                            'promotype' => 'embossed',
                            'display' => 'Embossed card'
                            ),
                        array(
                            'promotype' => 'textured',
                            'display' => 'Textured card'
                            ),
                        array(
                            'promotype' => 'neonink',
                            'display' => 'Neon ink'
                            ),
                        array(
                            'promotype' => 'confettifoil',
                            'display' => 'Confetti foil'
                            ),
                        array(
                            'promotype' => 'wizardsplaynetwork',
                            'display' => 'WPN'
                            ),
                        array(
                            'promotype' => 'draftweekend',
                            'display' => 'Draft weekend'
                            ),
                        array(
                            'promotype' => 'concept',
                            'display' => 'Concept card'
                            ),
                        array(
                            'promotype' => 'gameday',
                            'display' => 'Game Day card'
                            ),
                        array(
                            'promotype' => 'release',
                            'display' => 'Release card'
                            ),
                        array(
                            'promotype' => 'convention',
                            'display' => 'Convention promo card'
                            ),
                        array(
                            'promotype' => 'event',
                            'display' => 'Event promo card'
                            ),
                        array(
                            'promotype' => 'datestamped',
                            'display' => 'Date stamped'
                            )
);
