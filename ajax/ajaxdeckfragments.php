<?php

/*
Version:     1.7
Date:        24/12/25
Name:        ajaxdeckfragments.php
Purpose:     AJAX fragment updates for deck detail derived sections.
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
require_once 'ajaxdeckfragments_lib.php';
$msg = new \MTG\Core\Message($logfile);

$response = [
    'success' => false,
    'error' => '',
    'fragments' => []
];

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $response['error'] = 'User not logged in';
    returnResponse($response);
endif;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') :
    $response['error'] = 'Invalid request method';
    returnResponse($response);
endif;

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) :
    $response['error'] = 'Invalid request token';
    returnResponse($response);
endif;

$deckNumber = filter_input(INPUT_POST, 'decknumber', FILTER_SANITIZE_NUMBER_INT);
$requestedFragments = isset($_POST['fragments']) ? (array) $_POST['fragments'] : [];

if ($deckNumber === null) :
    $response['error'] = 'Missing required parameters';
    returnResponse($response);
endif;

$msg->logMessage('[DEBUG]', "Deck fragment refresh requested for deck $deckNumber");
$msg->logMessage(
    '[DEBUG]',
    "Deck fragment request fragments: " . implode(', ', array_map('strval', $requestedFragments))
);

$sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
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

$skip_deckdetail_actions = true;
include '../includes/deckdetail_data.php';
include '../includes/fragments/deckdetail_mana_data.php';
$msg->logMessage(
    '[DEBUG]',
    "Deck fragment data loaded for deck $deckNumber, type $decktype, total $total_cards, side $side_total_cards"
);

$response = deckdetailBuildFragmentResponse($requestedFragments);
$response['version'] = isset($deck_version) ? (int) $deck_version : 0;
returnResponse($response);

function returnResponse($response)
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
