<?php

/*
Version:     1.19
Date:        12/01/26
Name:        ajaxtemplate.php
Purpose:     PHP script to...
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Core\Http\AjaxResponse;

// Bootstrap

$appContext = require dirname(__DIR__) . '/bootstrap.php';

$myURL = (string) $appConfig->general('url', '');

// Content
$expectedReferringPages = [
    $myURL . '/sets.php',
    $myURL . '/index.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxtemplate.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        AjaxResponse::json(['error' => 'Invalid request token'], 403);
    else :
        $msg->logMessage('[ERROR]', "Not called from valid page");
        AjaxResponse::json(['error' => 'Access forbidden'], 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    AjaxResponse::text("<meta http-equiv='refresh' content='2;url=/login.php'>");
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];

    if (isset($_GET['filter'], $_GET['setsPerPage'], $_GET['offset'])) :  //Update GET details
        $filter = $_GET['filter'];
        $setsPerPage = intval($_GET['setsPerPage']);
        $offset = intval($_GET['offset']);

        $msg->logMessage('[DEBUG]', "Called with filter '$filter', setsPerPage '$setsPerPage', offset '$offset'");
    else :  // Error handling
        $msg->logMessage('[ERROR]', "Offset not in range");
        AjaxResponse::json(['error' => 'Offset not in range'], 400);
    endif;
endif;
