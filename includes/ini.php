<?php
/* Version:     1.0
    Date:       13/01/2020
    Name:       ini.php
    Purpose:    PHP script to manage error routines and logging
    Notes:      {none}
 *
    1.0         Initial version
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
elseif($ini_array['general']['tier'] === 'prod'):
    $tier = 'prod';
    error_reporting(E_ALL & ~E_NOTICE);
else:
    $tier = 'prod';
    error_reporting(E_ALL & ~E_NOTICE);
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
