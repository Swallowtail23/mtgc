<?php

/*
Version:     2.2
Date:        11/01/26
Name:        ajaxdeckrename.php
Purpose:     AJAX deck rename for deck detail.
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
require_once 'ajaxdeckfragments_lib.php';
$msg = new Message($logfile);

$response = [
    'success' => false,
    'error' => '',
    'status' => ''
];

$expectedReferringPages = [
    $myURL . '/deckdetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest(
    $expectedReferringPages,
    $logfile,
    'ajaxdeckrename.php'
);
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $response['error'] = 'Invalid request token';
    else :
        $response['error'] = 'Access forbidden';
    endif;
    http_response_code(403);
    returnResponse($response);
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $response['error'] = 'User not logged in';
    returnResponse($response);
endif;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') :
    $response['error'] = 'Invalid request method';
    returnResponse($response);
endif;

$csrfToken = $_POST['csrf_token'] ?? '';
if (!SessionManager::validateCsrfToken($csrfToken)) :
    $response['error'] = 'Invalid request token';
    returnResponse($response);
endif;

$deckNumber = filter_input(INPUT_POST, 'decknumber', FILTER_SANITIZE_NUMBER_INT);
$newname = filter_input(INPUT_POST, 'newname', FILTER_UNSAFE_RAW);

if ($deckNumber === null || $newname === null) :
    $response['error'] = 'Missing required parameters';
    returnResponse($response);
endif;

$newname = trim($newname);
if ($newname === '') :
    $response['error'] = 'Empty name';
    returnResponse($response);
endif;

$sessionManager = new SessionManager($db, $_SESSION, $appConfig);
$userArray = $sessionManager->getUserInfo();
$user = $userArray['usernumber'];
$userEmail = $_SESSION['useremail'];
$rate = $userArray['rate'] ?? null;
$targetCurrency = $userArray['currency'] ?? null;
$mytable = $userArray['table'] ?? '';
if ($mytable === '') :
    $msg->logMessage('[ERROR]', "Missing collection table for user $user");
    $response['error'] = 'Missing collection table';
    returnResponse($response);
endif;

$deckManager = new DeckManager(
    $db,
    $logfile,
    $userEmail,
    $serverEmail,
    $importLinestoIgnore,
    $nonPreferredSetCodes,
    $any_quantity,
);

$deckOwnerCheck = $deckManager->assertDeckOwner($deckNumber, $user, 'ajaxdeckrename.php');
if ($deckOwnerCheck === false) :
    $msg->logMessage('[ERROR]', "Deck ownership check failed for deck $deckNumber");
    $response['error'] = 'Deck ownership check failed';
    returnResponse($response);
endif;

$msg->logMessage('[DEBUG]', "Renaming deck $deckNumber to '$newname'");
$renameResult = $deckManager->renameDeck($deckNumber, $newname, $user);
$msg->logMessage('[DEBUG]', "Rename result: $renameResult");
if ($renameResult == 2) :
    $response['error'] = 'Deck name exists already';
    $response['status'] = 'nameexists';
    returnResponse($response);
elseif ($renameResult > 0) :
    $response['error'] = 'Unknown error';
    $response['status'] = 'renameerror';
    returnResponse($response);
endif;

$deckManager->bumpDeckUpdatedAt($deckNumber);
$response['success'] = true;
$response['deckname'] = $newname;
$response['deckname_text'] = $newname;
if (strlen($newname) > 17) :
    $response['deckname_text'] = $newname . "\n\n";
endif;
$response['deck_version'] = getDeckVersion($db, $deckNumber);

$requestedFragments = isset($_POST['fragments']) ? (array) $_POST['fragments'] : [];
if (count($requestedFragments) === 0) :
    $requestedFragments = ['export_list'];
endif;

$skip_deckdetail_actions = true;
include '../includes/deckdetail_data.php';
include '../includes/fragments/deckdetail_mana_data.php';
$msg->logMessage(
    '[DEBUG]',
    "Deck rename fragments requested for deck $deckNumber: " . implode(', ', array_map('strval', $requestedFragments))
);
$response['fragments'] = deckdetailRenderFragments($requestedFragments);
if (isset($deck_version)) :
    $response['deck_version'] = (int) $deck_version;
    $response['version'] = (int) $deck_version;
endif;

returnResponse($response);

function getDeckVersion($db, $deckNumber)
{
    $versionQuery = "SELECT (UNIX_TIMESTAMP(deck_updated_at) * 1000000 + MICROSECOND(deck_updated_at)) AS deck_version
        FROM decks WHERE decknumber = ? LIMIT 1";
    $versionResult = $db->execute_query($versionQuery, [$deckNumber]);
    if ($versionResult !== false && $versionResult->num_rows > 0) :
        $versionRow = $versionResult->fetch_assoc();
        return (int) ($versionRow['deck_version'] ?? 0);
    endif;
    return 0;
}

function returnResponse($response)
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    ajaxRespondJson($response, http_response_code());
}
