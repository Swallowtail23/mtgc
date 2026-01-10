<?php

/*
Version:     1.10
Date:        10/01/26
Name:        ajaximagecheck.php
Purpose:     Check and refresh card images asynchronously.
Notes:       Lightweight head/refresh; relies on ImageManager.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\ImageManager;
use MTG\Core\Message;

if (file_exists('../includes/sessionname.local.php')) :
    require('../includes/sessionname.local.php');
else :
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require('../includes/ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
$msg = new Message($logfile);

$expectedReferringPages = [
    $myURL . '/carddetail.php',
    $myURL . '/deckdetail.php',
    $myURL . '/index.php',
];
$ajaxValidation = SessionManager::validateAjaxRequest(
    $expectedReferringPages,
    $logfile,
    'ajaximagecheck.php'
);
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        ajaxRespondJson(['error' => 'Invalid request token'], 403);
    else :
        $msg->logMessage('[ERROR]', "Not called from valid page");
        ajaxRespondText('Access forbidden', 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    ajaxRespondJson(['error' => 'Not authenticated'], 401);
endif;

$cardUUID = isset($_POST['cardid']) ? validUUID($_POST['cardid']) : false;

if ($cardUUID === false) :
    $msg->logMessage('[ERROR]', "Invalid UUID provided");
    ajaxRespondJson(['error' => 'Invalid UUID provided'], 400);
endif;

$msg->logMessage('[DEBUG]', "Async image check for $cardUUID");

try {
    $obj = new ImageManager($db, $logfile, $serverEmail, $adminEmail);
    $result = $obj->checkAndRefreshImage($cardUUID);
    $msg->logMessage(
        '[DEBUG]',
        "Async image refresh: $cardUUID front_changed="
            . ($result['front_changed'] ? 'yes' : 'no')
            . " back_changed="
            . ($result['back_changed'] ? 'yes' : 'no')
    );

    ajaxRespondJson([
        'success' => true,
        'front' => $result['front'],
        'front_changed' => $result['front_changed'],
        'back' => $result['back'],
        'back_changed' => $result['back_changed'],
    ]);
} catch (Exception $e) {
    throw new Exception("[ERROR] ajaximagecheck.php: " . $e->getMessage());
    ajaxRespondJson(['error' => 'Unknown error'], 400);
}
