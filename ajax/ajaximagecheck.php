<?php

/*
Version:     1.4
Date:        21/12/25
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

// Validate referrer to limit abuse
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages = [
    $myURL . '/carddetail.php',
    $myURL . '/deckdetail.php',
    $myURL . '/index.php',
];
$msg->logMessage('[DEBUG]', "Called from $referringPage");
$msg->logMessage('[DEBUG]', "My URL is $myURL");

$normalizedReferringPage = str_replace('www.', '', $referringPage);
$isValidReferrer = false;
foreach ($expectedReferringPages as $page) :
    $normalizedPage = str_replace('www.', '', $page);
    if (strpos($normalizedReferringPage, $normalizedPage) !== false) :
        $isValidReferrer = true;
        break;
    endif;
endforeach;

if (!$isValidReferrer) :
    $msg->logMessage('[ERROR]', "Not called from valid page");
    http_response_code(403);
    echo 'Access forbidden';
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

    echo json_encode([
        'success' => true,
        'front' => $result['front'],
        'back' => $result['back'],
    ]);
} catch (Exception $e) {
    throw new Exception("[ERROR] ajaximagecheck.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Unknown error']);
}
