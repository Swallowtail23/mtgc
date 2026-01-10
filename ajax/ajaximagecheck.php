<?php

/*
Version:     1.6
Date:        10/01/26
Name:        ajaximagecheck.php
Purpose:     Check and refresh card images asynchronously.
Notes:       Lightweight head/refresh; relies on ImageManager.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (file_exists('../includes/sessionname.local.php')) :
    require('../includes/sessionname.local.php');
else :
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require('../includes/ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
$msg = new \MTG\Core\Message($logfile);

$expectedReferringPages = [
    $myURL . '/carddetail.php',
    $myURL . '/deckdetail.php',
    $myURL . '/index.php',
];
$ajaxValidation = validateAjaxRequest($expectedReferringPages, $logfile, 'ajaximagecheck.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        http_response_code(403);
        echo json_encode(['error' => 'Invalid request token']);
    else :
        $msg->logMessage('[ERROR]', "Not called from valid page");
        http_response_code(403);
        echo 'Access forbidden';
    endif;
    exit();
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
endif;

$cardUUID = isset($_POST['cardid']) ? validUUID($_POST['cardid']) : false;

if ($cardUUID === false) :
    $msg->logMessage('[ERROR]', "Invalid UUID provided");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid UUID provided']);
    exit();
endif;

$msg->logMessage('[DEBUG]', "Async image check for $cardUUID");

try {
    $obj = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
    $result = $obj->checkAndRefreshImage($cardUUID);
    $msg->logMessage(
        '[DEBUG]',
        "Async image refresh: $cardUUID front_changed="
            . ($result['front_changed'] ? 'yes' : 'no')
            . " back_changed="
            . ($result['back_changed'] ? 'yes' : 'no')
    );

    echo json_encode([
        'success' => true,
        'front' => $result['front'],
        'front_changed' => $result['front_changed'],
        'back' => $result['back'],
        'back_changed' => $result['back_changed'],
    ]);
} catch (Exception $e) {
    throw new Exception("[ERROR] ajaximagecheck.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Unknown error']);
}
