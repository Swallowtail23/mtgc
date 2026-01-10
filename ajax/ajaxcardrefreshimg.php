<?php

/*
Version:     1.7
Date:        10/01/26
Name:        ajaxcardrefreshimg.php
Purpose:     PHP script to refresh card image
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
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
include '../includes/colour.php';
$msg = new \MTG\Core\Message($logfile);

$expectedReferringPages = [
    $myURL . '/carddetail.php'
];
$ajaxValidation = validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxcardrefreshimg.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        http_response_code(403);
        echo json_encode(['error' => 'Invalid request token']);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from valid page");
        http_response_code(403);
        echo 'Access forbidden';
    endif;
    exit();
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    echo "<meta http-equiv='refresh' content='2;url=/login.php'>"; // redirect if not logged in
    exit();
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];
    $cardUUID = isset($_POST['cardid']) ? validUUID($_POST['cardid']) : false;

    if ($cardUUID === false) :
        $msg->logMessage('[ERROR]', "Invalid UUID provided");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid UUID provided']);
        exit();
    endif;

    $msg->logMessage('[NOTICE]', "Image refresh called for $cardUUID by $userEmail");

    try {
        $obj = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
        $newImage = $obj->refreshImage($cardUUID);

        if ($newImage === 'success') :
            echo json_encode(['success' => true]);
        else :
            http_response_code(400);
            echo json_encode(['success' => false]);
        endif;
    } catch (Exception $e) {
        throw new Exception("[ERROR] ajaxcardrefreshimg.php: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => 'Unknown error']);
    }
endif;
