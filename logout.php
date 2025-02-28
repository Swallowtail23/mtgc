<?php
/* Version:     1.0
    Date:       17/10/16
    Name:       logout.php
    Purpose:    Destroy the session, log it, and head to login.php
    Notes:      {none}
    To do:      -
    
    1.0
                Initial version
*/
if (file_exists('includes/sessionname.local.php')):
    require('includes/sessionname.local.php');
else:
    require('includes/sessionname_template.php');
endif;
startCustomSession();
session_regenerate_id();
$userEmail = isset($_SESSION['useremail']) ? $_SESSION['useremail'] : 'Unknown User';
$userId = isset($_SESSION['user']) ? $_SESSION['user'] : 0;

// Remove trusted device token if it exists
require_once('includes/ini.php');
require_once('classes/trusteddevicemanager.class.php');

// Initialize the message object for logging
$msg_text = "User $userEmail logged out from ".$_SERVER['REMOTE_ADDR']."";
$logfile = "/var/log/mtg/mtgapp.log";
date_default_timezone_set('Australia/Brisbane');

// Log using the Message class if possible
if (class_exists('Message')):
    $msg_obj = new Message($logfile);
    $msg_obj->logMessage('[NOTICE]', $msg_text);
else:
    // Fallback to direct file logging
    if (($fd = fopen($logfile, "a")) !== false):
        $str = "[" . date("Y/m/d H:i:s", time()) . "] [NOTICE] logout.php: ".$msg_text;
        fwrite($fd, $str . "\n");
        fclose($fd); 
    else:
        openlog("MTG", LOG_NDELAY, LOG_USER);
        syslog(LOG_ERR, "Can't write to MTG log file - check path and permissions. Falling back to syslog.");
        syslog(LOG_ERR, $msg_text);
        closelog();
    endif;
endif;

// Remove trusted device token
if ($db && $userId > 0) {
    $deviceManager = new TrustedDeviceManager($db, $logfile);
    
    // First try to remove the current device token
    $deviceManager->removeTrustedDevice();
    
    // Check for explicit "remove all" parameter
    if (isset($_GET['remove_all']) && $_GET['remove_all'] == 1) {
        $deviceManager->removeAllUserDevices($userId);
        if (isset($msg_obj)) {
            $msg_obj->logMessage('[NOTICE]', "Removed all trusted devices for user $userEmail");
        }
    }
}

// Finally destroy the session
session_destroy();
header('Location: login.php');
exit;