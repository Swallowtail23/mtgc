<?php

/*
Version:     1.13
Date:        11/01/26
Name:        ajaxsetimg.php
Purpose:     Trigger reload all images for a set
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
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
$msg = new Message($appConfig);
$expectedReferringPages = [
    $myURL . '/sets.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxsetimg.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token for ajaxsetimg");
        ajaxRespondJson(["status" => "error", "message" => "Invalid request token"], 403);
    else :
        $msg->logMessage('[ERROR]', "Not called from valid page");
        ajaxRespondJson(["status" => "error", "message" => "Access forbidden"], 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    header("Refresh: 2; url=login.php"); // redirect if not logged in
    // Return an error in JSON format
    ajaxRespondJson(["status" => "error", "message" => "You are not logged in."]);
else :
    // Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];

    if (isset($_POST['setcode'])) :
        $setcode = $_POST['setcode'];
        if (!is_string($setcode) || !preg_match('/^[A-Za-z0-9_]+$/', $setcode)) :
            $msg->logMessage('[ERROR]', "Invalid setcode supplied: '$setcode'");
            ajaxRespondJson(["status" => "error", "message" => "Invalid set code supplied"], 400);
        endif;
        $root = $_SERVER['DOCUMENT_ROOT'];
        $msg->logMessage('[NOTICE]', "Called with set '$setcode'");
        $safeRoot = escapeshellarg($root . '/bulk/setimgreload.php');
        $safeSetcode = escapeshellarg($setcode);
        $cmd = "php $safeRoot $safeSetcode > /dev/null 2>&1 &";
        $msg->logMessage('[NOTICE]', "Running '$cmd'");
        exec($cmd);
        ajaxRespondJson(
            [
                "status" => "success",
                "message" => "Image reloading started for set '$setcode' - result will be emailed to server admin"
            ]
        );
    else :
        $msg->logMessage('[ERROR]', "No setcode supplied");
        ajaxRespondJson(["status" => "error", "message" => "No setcode supplied"]);
    endif;
endif;
