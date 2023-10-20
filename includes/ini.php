<?php
/* Version:     1.0
    Date:       24/04/2023
    Name:       ini.php
    Purpose:    PHP script to manage error routines and logging
    Notes:      {none}
 *
    1.0         Initial version
 * 
 *  2.0
 *              Add card variable types for centralisation of card types
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;

$status = session_status();
if($status == PHP_SESSION_NONE):
    //There is no active session
    session_start();
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

//Set error reporting based on ini file's dev setting
$ini = new INI("/opt/mtg/mtg_new.ini");
$ini_array = $ini->data;
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

//Admin IP
if($ini_array['security']['AdminIP'] === ''):
    $adminip = 1;
else:
    $adminip = $ini_array['security']['AdminIP'];
endif;

//Logging levels
$loglevelini = $ini_array['general']['Loglevel'];

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

//Email addresses
$adminemail = $ini_array['security']['AdminEmail'];
$serveremail = $ini_array['security']['ServerEmail'];

//Copyright string
$copyright = $ini_array['general']['Copyright'];

//DB connect
define('DB_HOST', $ini_array['database']['DBServer']);  //host
define('DB_USER', $ini_array['database']['DBUser']);    // db username
define('DB_PASS', $ini_array['database']['DBPass']);    // db password 
define('DB_NAME', $ini_array['database']['DBName']);    // db name

$dbname = $ini_array['database']['DBName'];

try {
    $db = new Mysqli_Manager();
    $db->conn(); // connect DB
    $db->set_charset("utf8mb4");

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

// Commander deck types
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
                    'Modern');                     // Deck types

// Cards required per deck type for legal play
$hundredcarddecks = array('Commander');

$sixtycarddecks = array('Casual',
                        'Standard',
                        'Modern');

$fiftycarddecks = array('Tiny Leader');

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
                            )
);