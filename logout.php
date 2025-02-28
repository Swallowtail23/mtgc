<?php
/* Version:     2.0
    Date:       28/02/2025
    Name:       logout.php
    Purpose:    Destroy the session, log it, and head to login.php
    Notes:      {none}
    To do:      Clean up messaging added by Claude - feels clunky
    
    1.0
                Initial version
 *  2.0
 *              Add trusted device handling
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

// Define writelog function for Message class to use
function writelog($string, $file = '')
{
    global $loglevel, $logfile;
    
    $file = $file ?: $logfile;
    list($msglevel) = explode(']', $string);
    $msglevel = substr($msglevel, 1);
    
    switch (strtoupper($msglevel)) {
        case "DEBUG":
            if (strtoupper($loglevel) !== "DEBUG"):
                return; // Don't log debug messages unless debug mode is on
            endif;
            break;
        case "NOTICE":
        case "WARNING":
        case "ERROR":
            break; // Always log important messages
        default:
            return; // Unknown log level, don't log
    }
    $fh = fopen($file, 'a');
    if ($fh) {
        $timestamp = date("[d/m/Y:H:i:s]");
        fwrite($fh, "$timestamp $string\n");
        fclose($fh);
    }
    return;
}

require_once('classes/trusteddevicemanager.class.php');

// Initialize the message object for logging
$msg_text = "User $userEmail logged out from ".$_SERVER['REMOTE_ADDR']."";
$logfile = "/var/log/mtg/mtgapp.log";
date_default_timezone_set('Australia/Brisbane');

// Skip error-prone Message class and directly log to file
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

// Remove trusted device token
if ($db && $userId > 0) {
    try {
        $deviceManager = new TrustedDeviceManager($db, $logfile);
        
        // First try to remove the current device token
        if (($fd = fopen($logfile, "a")) !== false):
            $str = "[" . date("Y/m/d H:i:s", time()) . "] [DEBUG] logout.php: Attempting to remove trusted device";
            fwrite($fd, $str . "\n");
            fclose($fd); 
        endif;
        
        $deviceManager->removeTrustedDevice();
        
        // Check for explicit "remove all" parameter
        if (isset($_GET['remove_all']) && $_GET['remove_all'] == 1) {
            $deviceManager->removeAllUserDevices($userId);
            // Log directly without using Message class
            if (($fd = fopen($logfile, "a")) !== false):
                $str = "[" . date("Y/m/d H:i:s", time()) . "] [NOTICE] logout.php: Removed all trusted devices for user $userEmail";
                fwrite($fd, $str . "\n");
                fclose($fd);
            endif;
        }
    } catch (Exception $e) {
        // Log any errors
        if (($fd = fopen($logfile, "a")) !== false):
            $str = "[" . date("Y/m/d H:i:s", time()) . "] [ERROR] logout.php: Error removing trusted device: " . $e->getMessage();
            fwrite($fd, $str . "\n");
            fclose($fd); 
        endif;
    }
}

// Finally destroy the session
session_destroy();
header('Location: login.php');
exit;