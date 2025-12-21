<?php

/*
Version:     1.3
Date:        28/11/25
Name:        cleanup_tokens.php
Purpose:     Cleanup expired trusted device tokens
Notes:       To be run via cron, e.g. daily
Author:      Simon Wilson <simon@simonandkate.net>
Copyright:   2025 Simon Wilson
To do:       -
*/

// Load required files
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);  // point to web root
require_once(dirname(__FILE__) . '/../includes/ini.php');
require_once(dirname(__FILE__) . '/../classes/trusteddevicemanager.class.php');

$msg = new Message($logfile);
$msg->logMessage('[NOTICE]', "Starting trusted device token cleanup");

// Initialize the device manager
$deviceManager = new TrustedDeviceManager($db, $logfile);

// Perform cleanup
$cleanedCount = $deviceManager->cleanupExpiredTokens();

$msg->logMessage('[NOTICE]', "Trusted device token cleanup complete. Removed $cleanedCount expired tokens");

// If running from CLI, output result
if (php_sapi_name() == 'cli') :
    echo "Trusted device token cleanup complete. Removed $cleanedCount expired tokens\n";
endif;