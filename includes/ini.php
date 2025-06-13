<?php
/* Version:     6.0
    Date:       13/06/25
    Name:       ini.php
    Purpose:    PHP script to manage error routines, logging and setup global variables/arrays
    Notes:      {none}
 *
    1.0         Initial version
 * 
 *  2.0
 *              Add card variable types for centralisation of card types.
 *  2.1
 *              27/11/23
 *              Added fx variables from ini file
 *  
 *  3.0         17/12/23
 *              Added local fx currency array
 * 
 *  4.0         02/01/24
 *              Add language arrays
 * 
 *  5.0         13/01/24
 *              Add PHPMailer variables
 * 
 *  5.1         07/07/24
 *              Add array for cards with brackets in names
 * 
 *  5.2         09/12/24
 *              Move tribal here from index page
 * 
 *  6.0         13/06/25
 *              Bring ini defaults into here, and add over-ride capability from mtg_new.ini
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
);

// override the default if needed
if ($isHttps) {
    ini_set('session.cookie_secure', '1');
} else {
    ini_set('session.cookie_secure', '0');
}

$status = session_status();
if($status == PHP_SESSION_NONE):
    //There is no active session
    if (file_exists('sessionname.local.php')):
        require('sessionname.local.php');
    else:
        require('sessionname_template.php');
    endif;
    startCustomSession();
endif;

//Disable MTGPrice functionality
$mtgprice = false;
                                
//  Class autoloading
/// Composer
$root = realpath($_SERVER["DOCUMENT_ROOT"]);
require_once "$root/vendor/autoload.php";
/// Other classes
function autoLoader($class_name)
{
    $class_name_lwr = strtolower($class_name);
    if (file_exists($_SERVER["DOCUMENT_ROOT"].'/classes/'.$class_name_lwr.'.class.php')):
        include $_SERVER["DOCUMENT_ROOT"].'/classes/'.$class_name_lwr.'.class.php';
    endif;
};
spl_autoload_register('autoLoader');

use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\PHPMailer;

// DEFAULT SETTINGS
require __DIR__ . '/configdefaults.php';

//Set error reporting based on ini file's dev setting
$ini = new INI("/opt/mtg/mtg_new.ini");
$ini_array = $ini->data;

// merge defaults
foreach ($defaults as $section => $kv) :
    foreach ($kv as $key => $val) :
        if (
            ! isset($ini_array[$section][$key])
            || $ini_array[$section][$key] === ''
        ) :
            $ini_array[$section][$key] = $val;
        endif;
    endforeach;
endforeach;

$myURL = $ini_array['general']['URL'];
$siteTitle = $ini_array['general']['title'];
$fxAPI = $ini_array['fx']['FreecurrencyAPI'];
$fxLocal = $ini_array['fx']['TargetCurrency'];
if($ini_array['general']['tier'] === 'dev'):
    $tier = 'dev';
    error_reporting(E_ALL);
    // Dummy Turnstile test keys:
    
    // Client side:

    // $turnstile_site_key = '1x00000000000000000000AA';  // Always pass visible
       $turnstile_site_key = '1x00000000000000000000BB';  // Always pass invisible
    // $turnstile_site_key = '2x00000000000000000000AB';  // Always block visible
    // $turnstile_site_key = '2x00000000000000000000BB';  // Always block invisible
    // $turnstile_site_key = '3x00000000000000000000FF';  // Use to simulate interactive request

    // Server side:
       
       $turnstile_secret_key='1x0000000000000000000000000000000AA'; // Always pass
    // $turnstile_secret_key='2x0000000000000000000000000000000AA'; // Always fail
    // $turnstile_secret_key='3x0000000000000000000000000000000AA'; // Generates token spent error
elseif($ini_array['general']['tier'] === 'prod'):
    $tier = 'prod';
    error_reporting(E_ALL & ~E_NOTICE);    
    $turnstile_site_key = $ini_array['security']['Turnstile_site_key'];
    $turnstile_secret_key = $ini_array['security']['Turnstile_secret_key'];
else:
    $tier = 'prod';
    error_reporting(E_ALL & ~E_NOTICE); 
    $turnstile_site_key = $ini_array['security']['Turnstile_site_key'];
    $turnstile_secret_key = $ini_array['security']['Turnstile_secret_key'];
endif;

// Enable Turnstile
if($ini_array['security']['Turnstile'] !== 'enabled'):
    $turnstile = 0;
else:
    $turnstile = 1;
endif;

// How long to trust trusted devices (in days)
$trustDuration = $ini_array['security']['TrustDuration'];

// Enable Disqus card commenting
if($ini_array['comments']['Disqus'] !== 'enabled'):
    $disqus = 0;
    $disqusDev = '';
    $disqusProd = '';
else:
    $disqus = 1;
    $disqusDev = $ini_array['comments']['DisqusDevURL'];
    $disqusProd = $ini_array['comments']['DisqusProdURL'];
endif;

//Admin IP
if($ini_array['security']['AdminIP'] === ''):
    $adminip = 1;
else:
    $adminip = $ini_array['security']['AdminIP'];
endif;

//Logging levels
$loglevelini = $ini_array['general']['Loglevel'];

//Email settings (PHPMailer, see https://github.com/PHPMailer/PHPMailer
//Note, Debug settings other than SMTP::DEBUG_OFF will have no effect without $ini_array['general']['Loglevel'] = 3
$smtpParameters =   [
                    'SMTPDebug' => $ini_array['email']['SMTPDebug'],
                    'SMTPHost' => $ini_array['email']['Host'],
                    'SMTPAuth' => $ini_array['email']['SMTPAuth'],
                    'SMTPUsername' => $ini_array['email']['Username'],
                    'SMTPPassword' => $ini_array['email']['Password'],
                    'SMTPSecure' => $ini_array['email']['SMTPSecure'],
                    'SMTPPort' => $ini_array['email']['Port'],
                    'globalDebug' => $loglevelini
                    ];

//Email addresses
$adminemail = $ini_array['email']['AdminEmail'];
$serveremail = $ini_array['email']['ServerEmail'];

//Set password parameters
$Badloglimit = $ini_array['security']['Badloginlimit'];

//Card image location
$ImgLocation = $ini_array['general']['ImgLocation'];

//Location settings
date_default_timezone_set($ini_array['general']['Timezone']);
$localeini = $ini_array['general']['Locale'];
setlocale(LC_MONETARY,$localeini);  //used to display $ values

//Logfile check
$logfile = $ini_array['general']['Logfile'];
if (($fd = fopen($logfile, "a")) === false):
    openlog("MTG", LOG_NDELAY, LOG_USER);
    syslog(LOG_ERR, "[MTG-DEBUG] Ini.php: Can't write to MTG log file ($logfile) - check path and permissions. Falling back to syslog.");
    closelog();
    $logfile = 0;
elseif($loglevelini === '3' AND ($fd = fopen($logfile, "a")) !== false):
    $msg = "[DEBUG] Ini.php (direct write to logfile) ({$_SERVER['PHP_SELF']}): Successfully checked logfile access to $logfile";
    $str = "[" . date("Y/m/d H:i:s", time()) . "] ".$msg;
    fclose($fd); 
endif;
$msg = new Message($logfile);

//Copyright string
$copyright = $ini_array['general']['Copyright'];

//DB connect
define('DB_HOST', $ini_array['database']['DBServer']);  //host
define('DB_USER', $ini_array['database']['DBUser']);    // db username
define('DB_PASS', $ini_array['database']['DBPass']);    // db password 
define('DB_NAME', $ini_array['database']['DBName']);    // db name

$dbname = $ini_array['database']['DBName'];

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error):
        throw new Exception('Failed to connect to MySQL Database <br /> Error Info : ' . $db->connect_error);
    endif;
    $db->set_charset('utf8mb4');

//try {
//    $db = new Mysqli_Manager();
//    $db->conn(); // connect DB
//    $db->set_charset("utf8mb4");

} catch (Exception $err) {
    if(($fd = fopen($logfile, "a")) !== false):
        $msg = "[ERROR] Fatal database exception: {$err->getMessage()}";
        $str = "[" . date("Y/m/d H:i:s", time()) . "] ".$msg;
        fwrite($fd, $str . "\n");
        fclose($fd); 
    else:
        openlog("MTG", LOG_NDELAY, LOG_USER);
        syslog(LOG_ERR, "[MTG-DEBUG] Fatal database exception: {$err->getMessage()}");
        closelog();
    endif;
    $databaseaccess = 0;
    $from = "From: ".$serveremail;
    $subject = "Fatal database exception on MTGCollection";
    $message = wordwrap($err->getMessage(),70);
    mail($adminemail, $subject, $message, $from);
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
                            'db' => NULL
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
$two_card_detail_sections = array('transform',
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
$valid_commander_text = array("can be your commander"); // Check for abilities which allow a card to be used as a commander

$second_commander_text = array("Partner",
                               "Friends forever",
                               "Doctor's companion");   // Check for abilities which allow a card to be used as a second commander

$second_commander_only_type = array("Background");      // Check for "Type" which are valid ONLY in second commander slot

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

// Cards with brackets contents in names (not currently needed or used, see input_interpreter())
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
