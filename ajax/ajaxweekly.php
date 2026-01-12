<?php

/*
Version:     1.22
Date:        12/01/26
Name:        ajaxweekly.php
Purpose:     PHP script to turn weekly export on/off
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:      -
*/

use MTG\Auth\SessionManager;
use MTG\Core\Http\AjaxResponse;

// Bootstrap
$ctx                        = require dirname(__DIR__) . '/bootstrap.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();

$myURL                      = (string) $appConfig->general('url', '');

// Content
$expectedReferringPages = [
    $myURL . '/profile.php',
    $myURL . '/collection.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxweekly.php');
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
    // AJAX session context
    require_once APP_ROOT . '/ajax/ajax_session.php';
    $sessionUser                = requireAjaxSessionUser($db, $appConfig, $msg);
    $ctx                        = $ctx->withSessionUser($sessionUser);
    $user                       = $ctx->sessionUser()->id();
    $mytable                    = $ctx->sessionUser()->table();
    $userEmail                  = $ctx->sessionUser()->email();

    if (isset($_POST['weekly']) && $_POST['weekly'] === 'TURN OFF') :
        $msg->logMessage('[ERROR]', "Call to turn off weekly export");
        $query = "UPDATE users SET weeklyexport = ? WHERE usernumber = ?";
        $params = ['0', $user];
        $result = $db->execute_query($query, $params);
        if ($result === false) :
            throw new Exception('[ERROR] ajaxweekly.php: Error: ' . $db->error);
        else :
            $msg->logMessage('[ERROR]', "Call to turn off weekly export run for $userEmail");
        endif;
    elseif (isset($_POST['weekly']) && $_POST['weekly'] === 'TURN ON') :
        $msg->logMessage('[ERROR]', "Call to turn on weekly export");
        $query = "UPDATE users SET weeklyexport = ? WHERE usernumber = ?";
        $params = ['1', $user];
        $result = $db->execute_query($query, $params);
        if ($result === false) :
            throw new Exception('[ERROR] ajaxweekly.php: Error: ' . $db->error);
        else :
            $msg->logMessage('[ERROR]', "Call to turn on weekly export run for $userEmail");
        endif;
    else :
        $msg->logMessage('[ERROR]', "Called with invalid input");
        AjaxResponse::json(['error' => 'Called with invalid input'], 400);
    endif;
endif;
