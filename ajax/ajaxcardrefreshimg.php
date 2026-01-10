<?php

/*
Version:     1.11
Date:        10/01/26
Name:        ajaxcardrefreshimg.php
Purpose:     PHP script to refresh card image
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
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
include '../includes/colour.php';
$msg = new Message($logfile);

$expectedReferringPages = [
    $myURL . '/carddetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest(
    $expectedReferringPages,
    $logfile,
    'ajaxcardrefreshimg.php'
);
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        ajaxRespondJson(['error' => 'Invalid request token'], 403);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from valid page");
        ajaxRespondText('Access forbidden', 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    ajaxRespondText("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];
    $cardUUID = isset($_POST['cardid']) ? validUUID($_POST['cardid']) : false;

    if ($cardUUID === false) :
        $msg->logMessage('[ERROR]', "Invalid UUID provided");
        ajaxRespondJson(['error' => 'Invalid UUID provided'], 400);
    endif;

    $msg->logMessage('[NOTICE]', "Image refresh called for $cardUUID by $userEmail");

    try {
        $obj = new ImageManager($db, $logfile, $serverEmail, $adminEmail);
        $newImage = $obj->refreshImage($cardUUID);

        if ($newImage === 'success') :
            ajaxRespondJson(['success' => true]);
        else :
            ajaxRespondJson(['success' => false], 400);
        endif;
    } catch (Exception $e) {
        throw new Exception("[ERROR] ajaxcardrefreshimg.php: " . $e->getMessage());
        ajaxRespondJson(['error' => 'Unknown error'], 400);
    }
endif;
