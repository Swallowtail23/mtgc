<?php

/*
Version:     2.8
Date:        11/01/26
Name:        logout.php
Purpose:     Destroy the session, log it, and head to login.php.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\TrustedDeviceManager;
use MTG\Core\Message;

if (file_exists('includes/sessionname.local.php')) :
    require 'includes/sessionname.local.php';
else :
    require 'includes/sessionname_template.php';
endif;

startCustomSession();
session_regenerate_id();

$userEmail = $_SESSION['useremail'] ?? 'Unknown User';
$userId = $_SESSION['user'] ?? 0;
$removeTrusted = 1;

require 'includes/ini.php';
require 'includes/error_handling.php';

$msg = new Message($appConfig);
$msg->logMessage('[NOTICE]', "User $userEmail logging out from {$_SERVER['REMOTE_ADDR']}");

// Remove trusted device token
if ($db && $userId > 0 && $removeTrusted === 1) :
    try {
        $deviceManager = new TrustedDeviceManager($db, $appConfig);

        $msg->logMessage('[DEBUG]', 'Attempting to remove trusted device');
        $deviceManager->removeTrustedDevice();

        if (isset($_GET['remove_all']) && $_GET['remove_all'] == 1) :
            $deviceManager->removeAllUserDevices($userId);
            $msg->logMessage('[NOTICE]', "Removed all trusted devices for user $userEmail");
        endif;
    } catch (Exception $e) {
        $msg->logMessage('[ERROR]', 'Error removing trusted device: ' . $e->getMessage());
    }
endif;

session_destroy();
header('Location: loggedout.php');
exit;
