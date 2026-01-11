<?php

/*
Version:     1.15
Date:        11/01/26
Name:        ajaxgroup.php
Purpose:     PHP script to turn ajax group on/off
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Core\Http\AjaxResponse;

// Bootstrap
$appContext = require '../bootstrap.php';

// Content
$expectedReferringPages = [
    $myURL . '/profile.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxgroup.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        AjaxResponse::json(['error' => 'Invalid request token'], 403);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from profile.php");
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

    if (isset($_POST['group']) && $_POST['group'] === 'OPT OUT') :
        $msg->logMessage('[ERROR]', "Call to opt out of groups");
        $query = "UPDATE users SET grpinout = ? WHERE usernumber = ?";
        $params = ['0', $user];
        $result = $db->execute_query($query, $params);
        if ($result === false) :
            throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
        else :
            $msg->logMessage('[ERROR]', "Group opt-out run for $userEmail");
        endif;
    elseif (isset($_POST['group']) && $_POST['group'] === 'OPT IN') :
        $msg->logMessage('[ERROR]', "Call to opt into groups");
        $query = "UPDATE users SET grpinout = ? WHERE usernumber = ?";
        $params = ['1', $user];
        $result = $db->execute_query($query, $params);
        if ($result === false) :
            throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
        else :
            $msg->logMessage('[ERROR]', "Group opt-in run for $userEmail");
        endif;
    else :
        $msg->logMessage('[ERROR]', "Called with invalid input");
        AjaxResponse::json(['error' => 'Called with invalid input'], 400);
    endif;
endif;
