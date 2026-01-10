<?php

/*
Version:     1.8
Date:        09/01/26
Name:        ajaxcurrency.php
Purpose:     PHP script to set user's local currency
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
    $myURL . '/profile.php'
];
$ajaxValidation = validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxcurrency.php');
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
    ajaxRespondText("<meta http-equiv='refresh' content='2;url=/login.php'>");
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
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
            ajaxRespondJson(['success' => 'User currency changed to: ' . $usercurrency]);
        endif;
    else :  // Error handling
        $msg->logMessage('[ERROR]', "Not correctly called");
        ajaxRespondJson(['error' => 'Offset not in range'], 400);
    endif;
endif;
