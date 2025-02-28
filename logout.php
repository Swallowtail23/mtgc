<?php
/* Version:     2.0
    Date:       28/02/2025
    Name:       logout.php
    Purpose:    Destroy the session, log it, and head to login.php
    Notes:      {none}
    To do:      Clean up messaging added by Claude - feels clunky
    
    @author     Simon Wilson <simon@simonandkate.net>
    @copyright  2025 Simon Wilson

    1.0
                Initial version
 
 *  2.0         28/02/25
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

require_once('includes/ini.php');
require_once('includes/error_handling.php');
require_once('classes/trusteddevicemanager.class.php');

$msg = new Message($logfile);
$msg->logMessage('[NOTICE]', "User $userEmail logging out from ".$_SERVER['REMOTE_ADDR']."");

// Remove trusted device token
if ($db && $userId > 0):
    try {
        $deviceManager = new TrustedDeviceManager($db, $logfile);
        
        // First try to remove the current device token
        $msg->logMessage('[DEBUG]', "Attempting to remove trusted device");
        $deviceManager->removeTrustedDevice();
        
        // Check for explicit "remove all" parameter
        if (isset($_GET['remove_all']) && $_GET['remove_all'] == 1):
            $deviceManager->removeAllUserDevices($userId);
            $msg->logMessage('[NOTICE]', "Removed all trusted devices for user $userEmail");
        endif;
    } catch (Exception $e) {
        // Log any errors
        $msg->logMessage('[ERROR]', "Error removing trusted device: " . $e->getMessage());
    }
endif;

// Finally destroy the session
session_destroy();
header('Location: login.php');
exit;