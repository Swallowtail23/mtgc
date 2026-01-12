<?php

/*
Version:     1.22
Date:        12/01/26
Name:        ajaxcview.php
Purpose:     PHP script to turn ajax collection view on/off
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
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
    $myURL . '/index.php',
    $myURL . '/profile.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxcview.php');
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

    if (isset($_POST['collection_view']) && $_POST['collection_view'] === 'TURN OFF') :
        $msg->logMessage('[ERROR]', "Call to turn off collection view");
        $query = "UPDATE users SET collection_view = ? WHERE usernumber = ?";
        $params = ['0', $user];
        $result = $db->execute_query($query, $params);
        if ($result === false) :
            throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
        else :
            $msg->logMessage('[ERROR]', "Call to turn off collection view run for $userEmail");
        endif;
    elseif (isset($_POST['collection_view']) && $_POST['collection_view'] === 'TURN ON') :
        $msg->logMessage('[ERROR]', "Call to turn on collection view");
        $query = "UPDATE users SET collection_view = ? WHERE usernumber = ?";
        $params = ['1', $user];
        $result = $db->execute_query($query, $params);
        if ($result === false) :
            throw new Exception('[ERROR] profile.php: Error: ' . $db->error);
        else :
            $msg->logMessage('[ERROR]', "Call to turn on collection view run for $userEmail");
        endif;
    else :
        $msg->logMessage('[ERROR]', "Called with invalid input");
        AjaxResponse::json(['error' => 'Called with invalid input'], 400);
    endif;
endif;
