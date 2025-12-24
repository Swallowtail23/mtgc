<?php

/*
Version:     1.0
Date:        24/12/25
Name:        ajaxdecktype.php
Purpose:     AJAX deck type updates for deck detail.
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
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$normalizedReferringPage = str_replace('www.', '', $referringPage);
$isValidReferrer = false;
foreach ($expectedReferringPages as $page) :
    $normalizedPage = str_replace('www.', '', $page);
    if (strpos($normalizedReferringPage, $normalizedPage) !== false) :
        $isValidReferrer = true;
        break;
    endif;
endforeach;

$response = [
    'success' => false,
    'error' => ''
];

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $response['error'] = 'User not logged in';
    returnResponse($response);
endif;

if ($isValidReferrer !== true) :
    $msg->logMessage('[ERROR]', "Not called from a valid page");
    http_response_code(403);
    $response['error'] = 'Access forbidden';
    returnResponse($response);
endif;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') :
    $response['error'] = 'Invalid request method';
    returnResponse($response);
endif;

$deckNumber = filter_input(INPUT_POST, 'decknumber', FILTER_SANITIZE_NUMBER_INT);
$updateType = filter_input(INPUT_POST, 'updatetype', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if ($deckNumber === null || $updateType === null) :
    $response['error'] = 'Missing required parameters';
    returnResponse($response);
endif;

if (!in_array($updateType, $validtypes, true)) :
    $response['error'] = 'Invalid deck type';
    returnResponse($response);
endif;

$sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
$userArray = $sessionManager->getUserInfo();
$user = $userArray['usernumber'];
$userEmail = $_SESSION['useremail'];

$deckManager = new \MTG\Cards\DeckManager(
    $db,
    $logfile,
    $userEmail,
    $serverEmail,
    $importLinestoIgnore,
    $nonPreferredSetCodes
);

$deckOwnerCheck = $deckManager->deckOwnerCheck($deckNumber, $user);
if ($deckOwnerCheck === false) :
    $msg->logMessage('[ERROR]', "Deck ownership check failed for deck $deckNumber");
    $response['error'] = 'Deck ownership check failed';
    returnResponse($response);
endif;

$msg->logMessage('[DEBUG]', "Updating deck type to '$updateType'");
$setDeckType = $deckManager->setDeckType($deckNumber, $updateType);
if ($setDeckType !== 0) :
    $response['error'] = 'Deck type change failed';
    returnResponse($response);
endif;

if (!in_array($updateType, $commander_decktypes, true)) :
    if (
        $db->execute_query(
            "UPDATE deckcards SET commander = 0 WHERE decknumber = ?",
            [$deckNumber]
        ) === false
    ) :
        $response['error'] = 'Commander reset failed';
        returnResponse($response);
    endif;
endif;

if (in_array($updateType, $commander_decktypes, true)) :
    $query = "UPDATE deckcards LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id SET cardqty=?
        WHERE deckcards.decknumber = ? AND (deckcards.sideqty IS NULL or sideqty = 0)
        AND cards_scry.type NOT LIKE 'Basic Land%'";
    $msg->logMessage('[DEBUG]', "Updating deck type to a Commander type, setting quantities to 1");
    if ($db->execute_query($query, [1, $deckNumber]) != true) :
        $response['error'] = 'Commander quantity update failed';
        returnResponse($response);
    endif;
    $query = 'UPDATE deckcards SET sideqty=? WHERE (decknumber = ? AND (cardqty IS NULL or cardqty = 0) )';
    if ($db->execute_query($query, [1, $deckNumber]) != true) :
        $response['error'] = 'Commander side quantities update failed';
        returnResponse($response);
    endif;
    $query = 'UPDATE deckcards SET sideqty = NULL WHERE (decknumber = ? AND cardqty > 0)';
    if ($db->execute_query($query, [$deckNumber]) != true) :
        $response['error'] = 'Commander side cleanup failed';
        returnResponse($response);
    endif;
endif;

if ($updateType === 'Wishlist') :
    $query = 'UPDATE deckcards SET sideqty = NULL WHERE (decknumber = ? AND cardqty > 0)';
    $msg->logMessage('[DEBUG]', "Updating deck type to a Wishlist, deleting sideboard cards");
    if ($db->execute_query($query, [$deckNumber]) != true) :
        $response['error'] = 'Wishlist side cleanup failed';
        returnResponse($response);
    endif;
endif;

$response['success'] = true;
$response['decktype'] = $updateType;
$response['is_commander'] = in_array($updateType, $commander_decktypes, true);
returnResponse($response);

function returnResponse($response)
{
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
