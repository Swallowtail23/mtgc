<?php

/*
Version:     1.16
Date:        11/01/26
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
use MTG\Core\Validation;
use MTG\Core\Http\AjaxResponse;

if (file_exists('../includes/sessionname.local.php')) :
    require('../includes/sessionname.local.php');
else :
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require('../includes/ini.php');
require('../includes/error_handling.php');
$msg = new Message($appConfig);

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
        AjaxResponse::json(['error' => 'Invalid request token'], 403);
    else :
        $msg->logMessage('[ERROR]', "Not called from valid page");
        AjaxResponse::text('Access forbidden', 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    AjaxResponse::json(['error' => 'Not authenticated'], 401);
endif;

$cardUUID = isset($_POST['cardid']) ? Validation::validUUID($_POST['cardid'], $appConfig) : false;

if ($cardUUID === false) :
    $msg->logMessage('[ERROR]', "Invalid UUID provided");
    AjaxResponse::json(['error' => 'Invalid UUID provided'], 400);
endif;

$msg->logMessage('[DEBUG]', "Async image check for $cardUUID");

try {
    $obj = new ImageManager($db, $appConfig, $gameRules);
    $result = $obj->checkAndRefreshImage($cardUUID);
    $msg->logMessage(
        '[DEBUG]',
        "Async image refresh: $cardUUID front_changed="
            . ($result['front_changed'] ? 'yes' : 'no')
            . " back_changed="
            . ($result['back_changed'] ? 'yes' : 'no')
    );

    AjaxResponse::json([
        'success' => true,
        'front' => $result['front'],
        'front_changed' => $result['front_changed'],
        'back' => $result['back'],
        'back_changed' => $result['back_changed'],
    ]);
} catch (Exception $e) {
    throw new Exception("[ERROR] ajaximagecheck.php: " . $e->getMessage());
    AjaxResponse::json(['error' => 'Unknown error'], 400);
}
