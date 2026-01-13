<?php

/*
Version:     1.22
Date:        13/01/26
Name:        ajaxsetimg.php
Purpose:     Trigger reload all images for a set
Notes:       -
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
    $myURL . '/sets.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxsetimg.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token for ajaxsetimg");
        AjaxResponse::json(["status" => "error", "message" => "Invalid request token"], 403);
    else :
        $msg->logMessage('[ERROR]', "Not called from valid page");
        AjaxResponse::json(["status" => "error", "message" => "Access forbidden"], 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    header("Refresh: 2; url=login.php"); // redirect if not logged in
    // Return an error in JSON format
    AjaxResponse::json(["status" => "error", "message" => "You are not logged in."]);
else :
    // AJAX session context
    require_once APP_ROOT . '/ajax/ajax_session.php';
    $sessionUser                = requireAjaxSessionUser($db, $appConfig, $msg);
    $ctx                        = $ctx->withSessionUser($sessionUser);
    $user                       = $ctx->sessionUser()->id();
    $mytable                    = $ctx->sessionUser()->table();
    $userEmail                  = $ctx->sessionUser()->email();

    if (isset($_POST['setcode'])) :
        $setcode = $_POST['setcode'];
        if (!is_string($setcode) || !preg_match('/^[A-Za-z0-9_]+$/', $setcode)) :
            $msg->logMessage('[ERROR]', "Invalid setcode supplied: '$setcode'");
            AjaxResponse::json(["status" => "error", "message" => "Invalid set code supplied"], 400);
        endif;
        $root = $_SERVER['DOCUMENT_ROOT'];
        $msg->logMessage('[NOTICE]', "Called with set '$setcode'");
        $safeRoot = escapeshellarg($root . '/bulk/setimgreload.php');
        $safeSetcode = escapeshellarg($setcode);
        $cmd = "php $safeRoot $safeSetcode > /dev/null 2>&1 &";
        $msg->logMessage('[NOTICE]', "Running '$cmd'");
        exec($cmd);
        AjaxResponse::json(
            [
                "status" => "success",
                "message" => "Image reloading started for set '$setcode' - result will be emailed to server admin"
            ]
        );
    else :
        $msg->logMessage('[ERROR]', "No setcode supplied");
        AjaxResponse::json(["status" => "error", "message" => "No setcode supplied"]);
    endif;
endif;
