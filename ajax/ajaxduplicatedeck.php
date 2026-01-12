<?php

/*
Version:     1.23
Date:        12/01/26
Name:        ajaxduplicatedeck.php
Purpose:     PHP script to duplicate deck
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\DeckManager;
use MTG\Core\Http\AjaxResponse;

// Bootstrap

$appContext = require dirname(__DIR__) . '/bootstrap.php';

// Content
$expectedReferringPages = [$myURL . '/deckdetail.php'];
$response = ['success' => false, 'error' => ''];
$ajaxValidation = SessionManager::validateAjaxRequest(
    $expectedReferringPages,
    $appConfig,
    'ajaxduplicatedeck.php'
);
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
    returnResponse($response);
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $response['success'] = false;
    $response['error'] = 'User not logged in';
    returnResponse($response);
else :
    // Need to run these as secpagesetup is not run (see page notes)
    $sessionManager = new SessionManager($db, $_SESSION, $appConfig);
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
            $obj = new DeckManager(
                $db,
                $appConfig,
                $gameRules,
                $userEmail
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
            returnResponse($response);
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
            returnResponse($response);
        else :
                $response['success'] = false;
                $response['error'] = 'Failed to duplicate deck';
                returnResponse($response);
        endif;
    else :
        $response['success'] = false;
        $response['error'] = 'Invalid input';
        returnResponse($response);
    endif;
endif;

// Function to echo JSON response and exit
function returnResponse(array $response)
{
    AjaxResponse::json($response, http_response_code());
}
