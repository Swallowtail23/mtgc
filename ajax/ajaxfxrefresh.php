<?php

/*
Version:     1.1
Date:        05/02/26
Name:        ajaxfxrefresh.php
Purpose:     Async FX refresh for cached currency pairs.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Core\Http\AjaxResponse;

// Bootstrap
$ctx                        = require dirname(__DIR__) . '/bootstrap.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();

$myURL                      = (string) $appConfig->general('url', '');

// Content
$expectedReferringPages = [
    $myURL
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxfxrefresh.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', 'ajaxfxrefresh.php: Invalid CSRF token');
        AjaxResponse::json(['error' => 'Invalid request token'], 403);
    else :
        $msg->logMessage('[ERROR]', 'ajaxfxrefresh.php: Not called from valid page');
        AjaxResponse::json(['error' => 'Access forbidden'], 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    AjaxResponse::text("<meta http-equiv='refresh' content='2;url=/login.php'>");
endif;

require_once APP_ROOT . '/ajax/ajax_session.php';
$sessionUser                = requireAjaxSessionUser($db, $appConfig, $msg);
$ctx                        = $ctx->withSessionUser($sessionUser);
$targetCurrency             = $ctx->sessionUser()->currency();

$baseCurrency = 'USD';
$targetCurrency = strtoupper((string) $targetCurrency);
if ($targetCurrency === '' || $targetCurrency === $baseCurrency) :
    AjaxResponse::json(['error' => 'Invalid currency target'], 400);
endif;

$currencies = strtolower($baseCurrency . '_' . $targetCurrency);
if (session_status() === PHP_SESSION_ACTIVE) :
    session_write_close();
    $msg->logMessage('[DEBUG]', 'ajaxfxrefresh.php: Session closed before FX refresh');
endif;

$sessionManager = new SessionManager($db, $_SESSION, $appConfig);
$rate = $sessionManager->refreshFxRate($currencies);

if ($rate === null) :
    AjaxResponse::json(['error' => 'FX refresh failed'], 502);
endif;

AjaxResponse::json(
    [
        'success' => true,
        'rate' => $rate,
        'currency' => $targetCurrency,
        'currencies' => $currencies
    ]
);
