<?php

/*
Version:     1.15
Date:        11/01/26
Name:        ajaxcurrency.php
Purpose:     PHP script to set user's local currency
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Core\Message;
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
    $myURL . '/profile.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxcurrency.php');
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
    AjaxResponse::text("<meta http-equiv='refresh' content='2;url=/login.php'>");
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $fx = $userArray['fx'];
    $userEmail = $_SESSION['useremail'];

    if (isset($_GET['currency'])) :  //Update GET details
        $usercurrency = $db->real_escape_string($_GET['currency']);
        if ($usercurrency === 'zzz' || !in_array($usercurrency, array_column($currencies, 'code'))) :
            $usercurrency = null;
        endif;
        $msg->logMessage('[DEBUG]', "Called with user currency '$usercurrency'");
        $query = "UPDATE users SET currency = ? WHERE usernumber = ?";
        $params = [$usercurrency, $user];
        $result = $db->execute_query($query, $params);
        if ($result === false) :
            throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
        else :
            // Set string to NULL to provide feedback in success message if $usercurrency is NULL
            if ($usercurrency === null) :
                $usercurrency = 'NULL';
            endif;
            $msg->logMessage('[NOTICE]', "User currency change for $userEmail");
            AjaxResponse::json(['success' => 'User currency changed to: ' . $usercurrency]);
        endif;
    else :  // Error handling
        $msg->logMessage('[ERROR]', "Not correctly called");
        AjaxResponse::json(['error' => 'Offset not in range'], 400);
    endif;
endif;
