<?php

/*
Version:     1.23
Date:        11/01/26
Name:        ajaxcardrefreshimg.php
Purpose:     PHP script to refresh card image
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\ImageManager;
use MTG\Core\Validation;
use MTG\Core\Http\AjaxResponse;

// Bootstrap
if (!defined('APP_ROOT')) :
    define('APP_ROOT', dirname(__DIR__));
endif;

$appContext = require APP_ROOT . '/bootstrap.php';

// Content
$expectedReferringPages = [
    $myURL . '/carddetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest(
    $expectedReferringPages,
    $appConfig,
    'ajaxcardrefreshimg.php'
);
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        AjaxResponse::json(['error' => 'Invalid request token'], 403);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from valid page");
        AjaxResponse::text('Access forbidden', 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    AjaxResponse::text("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];
    $cardUUID = isset($_POST['cardid']) ? Validation::validUUID($_POST['cardid'], $appConfig) : false;

    if ($cardUUID === false) :
        $msg->logMessage('[ERROR]', "Invalid UUID provided");
        AjaxResponse::json(['error' => 'Invalid UUID provided'], 400);
    endif;

    $msg->logMessage('[NOTICE]', "Image refresh called for $cardUUID by $userEmail");

    try {
        $obj = new ImageManager($db, $appConfig, $gameRules);
        $newImage = $obj->refreshImage($cardUUID);

        if ($newImage === 'success') :
            AjaxResponse::json(['success' => true]);
        else :
            AjaxResponse::json(['success' => false], 400);
        endif;
    } catch (Exception $e) {
        throw new Exception("[ERROR] ajaxcardrefreshimg.php: " . $e->getMessage());
        AjaxResponse::json(['error' => 'Unknown error'], 400);
    }
endif;
