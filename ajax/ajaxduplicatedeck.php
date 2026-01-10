<?php

/*
Version:     1.7
Date:        10/01/26
Name:        ajaxduplicatedeck.php
Purpose:     PHP script to duplicate deck
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

$expectedReferringPages = [$myURL . '/deckdetail.php'];
$response = ['success' => false, 'error' => ''];
$ajaxValidation = validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxduplicatedeck.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        http_response_code(403);
        $response['error'] = 'Invalid request token';
    else :
        $msg->logMessage('[ERROR]', "Not called from a valid page");
        http_response_code(403);
        $response['error'] = 'Access forbidden';
    endif;
    returnResponse();
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $response['success'] = false;
    $response['error'] = 'User not logged in';
    returnResponse();
else :
    // Need to run these as secpagesetup is not run (see page notes)
    $sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];

    if (
        isset($_POST['user'])
        && isset($_POST['deckname'])
        && isset($_POST['decknumber'])
        && isset($_POST['decktype'])
    ) :
        $user = filter_input(INPUT_POST, 'user', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $deckName = filter_input(INPUT_POST, 'deckname', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $deckNumber = filter_input(INPUT_POST, 'decknumber', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $decktype = filter_input(INPUT_POST, 'decktype', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $msg->logMessage(
            '[ERROR]',
            "Call to duplicate user $user's deck number $deckNumber, $deckName ($decktype)"
        );
        $counter = 1;
        $newdeckname = $deckName . "_$counter";

        do {
            // Check if the deck name already exists
            $decknamechecksql = "SELECT decknumber FROM decks WHERE owner = ? and deckname = ? LIMIT 1";
            $decknameparams = [$user, $newdeckname];
            $result = $db->execute_query($decknamechecksql, $decknameparams);

            if ($result !== false && $result->num_rows > 0) :
                // Increment the counter and create a new name
                $counter++;
                $newdeckname = $deckName . "_$counter";  // Ensure that only one counter is appended
            endif;
        } while ($result !== false && $result->num_rows > 0);

            // Instantiate the DeckManager
            $obj = new \MTG\Cards\DeckManager(
                $db,
                $logfile,
                $userEmail,
                $serverEmail,
                $importLinestoIgnore,
                $nonPreferredSetCodes
            );

            //Create the new deck shell
            $decksuccess = $obj->addDeck($user, $newdeckname);
            $msg->logMessage('[DEBUG]', "Created deck number {$decksuccess['decknumber']}");

            //get the cardlist from the source deck
            $cardlist = $obj->exportDeck($deckNumber, "variable");
            $msg->logMessage('[DEBUG]', "Cardlist: $cardlist");

            //Set the decktype the same as the source deck
            $setdecktype = $obj->setDeckType($decksuccess['decknumber'], $decktype);
        if ($setdecktype !== 0) :
            $response['success'] = false;
            $response['error'] = 'Deck type set failed';
            returnResponse();
        endif;

            //import the card list to the new deck
            $obj->processInput($decksuccess['decknumber'], $cardlist);
            $msg->logMessage(
                '[DEBUG]',
                "Duplicate deck import completed for deck {$decksuccess['decknumber']}, retaining commander flags"
            );

        if ($decksuccess['flag'] === 1 && $cardlist !== '' && $setdecktype === 0) :
            $response['success'] = true;
            $response['decknumber'] = $decksuccess['decknumber'];
            returnResponse();
        else :
                $response['success'] = false;
                $response['error'] = 'Failed to duplicate deck';
                returnResponse();
        endif;
    else :
        $response['success'] = false;
        $response['error'] = 'Invalid input';
        returnResponse();
    endif;
endif;

// Function to echo JSON response and exit
function returnResponse()
{
    global $response;
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
