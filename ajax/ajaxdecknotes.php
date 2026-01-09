<?php

/*
Version:     1.7
Date:        09/01/26
Name:        ajaxdecknotes.php
Purpose:     PHP script to save deck notes
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

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
$msg = new \MTG\Core\Message($logfile);

$expectedReferringPages = [
    $myURL . '/deckdetail.php'
];
$ajaxValidation = validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxdecknotes.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        http_response_code(403);
        echo json_encode(['error' => 'Invalid request token']);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from valid page");
        http_response_code(403);
        echo 'Access forbidden';
    endif;
    exit();
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    echo "<meta http-equiv='refresh' content='2;url=/login.php'>"; // redirect if not logged in
    exit();
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
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

    try {
        $query = "UPDATE decks SET notes = ?, sidenotes = ? WHERE decknumber = ?";
        $result = $db->execute_query($query, [$newnotes, $newsidenotes, $deckNumber]);

        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'No rows updated or SQL error occurred']);
        }
    } catch (Exception $e) {
        throw new Exception(
            "[ERROR] ajaxdecknotes.php: " . $e->getMessage() . " SQLSTATE: " . $db->error
        );
        http_response_code(400);
        echo json_encode(['error' => 'Database error']);
    }
endif;
