<?php

/*
Version:     1.12
Date:        10/01/26
Name:        ajaxdecknotes.php
Purpose:     PHP script to save deck notes
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\DeckManager;
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
include '../includes/colour.php';
$msg = new Message($logfile);

$expectedReferringPages = [
    $myURL . '/deckdetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxdecknotes.php');
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
    $sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];
    $newnotes = isset($_POST['newnotes']) ? trim($_POST['newnotes']) : '';
    $newsidenotes = isset($_POST['newsidenotes']) ? trim($_POST['newsidenotes']) : '';
    $deckNumber = isset($_POST['decknumber']) ? intval($_POST['decknumber']) : 0;

    $msg->logMessage(
        '[NOTICE]',
        "Called with: Notes: $newnotes, Side notes: $newsidenotes, Deck number: $deckNumber"
    );

    $deckManager = new DeckManager(
        $db,
        $logfile,
        $userEmail,
        $serverEmail,
        $importLinestoIgnore,
        $nonPreferredSetCodes
    );
    if ($deckManager->assertDeckOwner($deckNumber, $user, 'ajaxdecknotes.php') === false) :
        ajaxRespondJson(['error' => 'Access forbidden'], 403);
    endif;

    try {
        $query = "UPDATE decks SET notes = ?, sidenotes = ? WHERE decknumber = ?";
        $result = $db->execute_query($query, [$newnotes, $newsidenotes, $deckNumber]);

        if ($result) {
            ajaxRespondJson(['success' => true]);
        } else {
            ajaxRespondJson(['error' => 'No rows updated or SQL error occurred'], 400);
        }
    } catch (Exception $e) {
        throw new Exception(
            "[ERROR] ajaxdecknotes.php: " . $e->getMessage() . " SQLSTATE: " . $db->error
        );
        ajaxRespondJson(['error' => 'Database error'], 400);
    }
endif;
