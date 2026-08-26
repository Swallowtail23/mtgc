<?php

/*
Version:     1.26
Date:        26/08/26
Name:        ajaximagecheck.php
Purpose:     Resolve card images asynchronously and populate missing cache entries.
Notes:       Existing WebP and JPEG cache entries are never replaced by this endpoint.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\ImageManager;
use MTG\Core\Validation;
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
    $myURL . '/carddetail.php',
    $myURL . '/deckdetail.php',
    $myURL . '/index.php',
];
$ajaxValidation = SessionManager::validateAjaxRequest(
    $expectedReferringPages,
    $appConfig,
    'ajaximagecheck.php'
);
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        AjaxResponse::json(['error' => 'Invalid request token'], 403);
    else :
        $msg->logMessage('[ERROR]', "Not called from valid page");
        AjaxResponse::text('Access forbidden', 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    AjaxResponse::json(['error' => 'Not authenticated'], 401);
endif;

$cardUUID = isset($_POST['cardid']) ? Validation::validUUID($_POST['cardid'], $appConfig) : false;

if ($cardUUID === false) :
    $msg->logMessage('[ERROR]', "Invalid UUID provided");
    AjaxResponse::json(['error' => 'Invalid UUID provided'], 400);
endif;

$msg->logMessage('[DEBUG]', "Async missing-image cache resolution for $cardUUID");

if (session_status() === PHP_SESSION_ACTIVE) :
    session_write_close();
    $msg->logMessage('[DEBUG]', 'ajaximagecheck.php: Session closed before image resolution');
endif;

try {
    $obj = new ImageManager($db, $appConfig, $gameRules);
    $result = $obj->checkAndRefreshImage($cardUUID);
    $msg->logMessage(
        '[DEBUG]',
        "Async image resolution: $cardUUID front_changed="
            . ($result['front_changed'] ? 'yes' : 'no')
            . " back_changed="
            . ($result['back_changed'] ? 'yes' : 'no')
    );
    $msg->logMessage(
        '[DEBUG]',
        "Async image resolution paths for $cardUUID: front={$result['front']} back={$result['back']}"
    );

    AjaxResponse::json([
        'success' => true,
        'front' => $result['front'],
        'front_changed' => $result['front_changed'],
        'back' => $result['back'],
        'back_changed' => $result['back_changed'],
    ]);
} catch (Exception $e) {
    throw new Exception("[ERROR] ajaximagecheck.php: " . $e->getMessage());
    AjaxResponse::json(['error' => 'Unknown error'], 400);
}
