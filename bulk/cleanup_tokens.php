<?php

/*
Version:     1.12
Date:        11/01/26
Name:        cleanup_tokens.php
Purpose:     Cleanup expired trusted device tokens
Notes:       To be run via cron, e.g. daily
Author:      Simon Wilson <simon@simonandkate.net>
Copyright:   2025 Simon Wilson
To do:       -
*/

use MTG\Auth\TrustedDeviceManager;

// Load required files
$appContext = require dirname(__DIR__) . '/bootstrap.php';
$msg->logMessage('[NOTICE]', "Starting trusted device token cleanup");

// Initialize the device manager
$deviceManager = new TrustedDeviceManager($db, $appConfig);

// Perform cleanup
$cleanedCount = $deviceManager->cleanupExpiredTokens();

$msg->logMessage('[NOTICE]', "Trusted device token cleanup complete. Removed $cleanedCount expired tokens");

// If running from CLI, output result
if (php_sapi_name() == 'cli') :
    echo "Trusted device token cleanup complete. Removed $cleanedCount expired tokens\n";
endif;
