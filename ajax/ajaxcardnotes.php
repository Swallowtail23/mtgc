<?php

/*
Version:     1.18
Date:        11/01/26
Name:        ajaxcardnotes.php
Purpose:     PHP script to save card notes
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Core\Validation;
use MTG\Core\Http\AjaxResponse;

// Bootstrap
if (!defined('APP_ROOT')) :
    define('APP_ROOT', dirname(__DIR__));
endif;

$appContext = require APP_ROOT . '/bootstrap.php';

// Content
$expectedReferringPages = [
    $myURL . '/carddetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxcardnotes.php');
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
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];
    $newnotes = isset($_POST['newnotes']) ? trim($_POST['newnotes']) : '';
    $cardUUID = isset($_POST['cardid']) ? Validation::validUUID($_POST['cardid'], $appConfig) : false;

    if ($cardUUID === false) :
        $msg->logMessage('[ERROR]', "Invalid UUID provided");
        AjaxResponse::json(['error' => 'Invalid UUID provided'], 400);
    endif;

    $msg->logMessage('[NOTICE]', "Called with: Notes: $newnotes, Card ID: $cardUUID");

    try {
        $query = "INSERT INTO `$mytable` (notes,id)
                    VALUES (?,?)
                    ON DUPLICATE KEY UPDATE notes = ? ";
        $result = $db->execute_query($query, [$newnotes, $cardUUID, $newnotes]);

        if ($result) {
            AjaxResponse::json(['success' => true]);
        } else {
            AjaxResponse::json(['error' => 'No rows updated or SQL error occurred'], 400);
        }
    } catch (Exception $e) {
        throw new Exception(
            "[ERROR] ajaxcardnotes.php: " . $e->getMessage() . " SQLSTATE: " . $db->error
        );
        AjaxResponse::json(['error' => 'Database error'], 400);
    }
endif;
