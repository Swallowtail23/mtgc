<?php

/*
Version:     1.12
Date:        11/01/26
Name:        ajaxcardnotes.php
Purpose:     PHP script to save card notes
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Core\Message;

if (file_exists('../includes/sessionname.local.php')) :
    require('../includes/sessionname.local.php');
else :
    require('../includes/sessionname_template.php');
endif;
startCustomSession();
require('../includes/ini.php');
require('../includes/error_handling.php');
require('../includes/functions.php');
$msg = new Message($logfile);

$expectedReferringPages = [
    $myURL . '/carddetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxcardnotes.php');
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
    ajaxRespondText("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];
    $newnotes = isset($_POST['newnotes']) ? trim($_POST['newnotes']) : '';
    $cardUUID = isset($_POST['cardid']) ? validUUID($_POST['cardid']) : false;

    if ($cardUUID === false) :
        $msg->logMessage('[ERROR]', "Invalid UUID provided");
        ajaxRespondJson(['error' => 'Invalid UUID provided'], 400);
    endif;

    $msg->logMessage('[NOTICE]', "Called with: Notes: $newnotes, Card ID: $cardUUID");

    try {
        $query = "INSERT INTO `$mytable` (notes,id)
                    VALUES (?,?)
                    ON DUPLICATE KEY UPDATE notes = ? ";
        $result = $db->execute_query($query, [$newnotes, $cardUUID, $newnotes]);

        if ($result) {
            ajaxRespondJson(['success' => true]);
        } else {
            ajaxRespondJson(['error' => 'No rows updated or SQL error occurred'], 400);
        }
    } catch (Exception $e) {
        throw new Exception(
            "[ERROR] ajaxcardnotes.php: " . $e->getMessage() . " SQLSTATE: " . $db->error
        );
        ajaxRespondJson(['error' => 'Database error'], 400);
    }
endif;
