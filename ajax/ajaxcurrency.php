<?php

/*
Version:     1.26
Date:        13/01/26
Name:        ajaxcurrency.php
Purpose:     PHP script to set user's local currency
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Core\Http\AjaxResponse;
use MTG\Profile\ProfilePreferences;

// Bootstrap
$ctx                        = require dirname(__DIR__) . '/bootstrap.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();

$myURL                      = (string) $appConfig->general('url', '');

$rulesCurrencies = $gameRules->getArray('currencies');

// Content
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
    // AJAX session context
    require_once APP_ROOT . '/ajax/ajax_session.php';
    $sessionUser                = requireAjaxSessionUser($db, $appConfig, $msg);
    $ctx                        = $ctx->withSessionUser($sessionUser);
    $user                       = $ctx->sessionUser()->id();
    $mytable                    = $ctx->sessionUser()->table();
    $userEmail                  = $ctx->sessionUser()->email();
    $fx                         = $ctx->sessionUser()->fxEnabled();

    if (isset($_GET['currency'])) :  //Update GET details
        $usercurrency = (string) $_GET['currency'];
        $normalizedCurrency = ProfilePreferences::updateCurrency(
            $db,
            $rulesCurrencies,
            $user,
            $usercurrency,
            $msg
        );
        $displayCurrency = $normalizedCurrency === null ? 'NULL' : $normalizedCurrency;
        $msg->logMessage('[NOTICE]', "User currency change for $userEmail");
        AjaxResponse::json(['success' => 'User currency changed to: ' . $displayCurrency]);
    else :  // Error handling
        $msg->logMessage('[ERROR]', "Not correctly called");
        AjaxResponse::json(['error' => 'Offset not in range'], 400);
    endif;
endif;
