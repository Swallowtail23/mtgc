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
            echo json_encode(['success' => 'User currency changed to: ' . $usercurrency]);
            exit();
        endif;
    else :  // Error handling
        http_response_code(400);
        $msg->logMessage('[ERROR]', "Not correctly called");
        echo json_encode(['error' => 'Offset not in range']);
        exit();
    endif;
endif;
