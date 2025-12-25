<?php

/*
Version:     1.0
Date:        24/12/25
Name:        ajaxdeckimport.php
Purpose:     AJAX text/CSV deck import for deck detail.
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
    'status' => ''
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
if ($deckNumber === null) :
    $response['error'] = 'Missing required parameters';
    returnResponse($response);
endif;

if (!isset($_FILES['filename']) || !is_uploaded_file($_FILES['filename']['tmp_name'])) :
    $response['error'] = 'Import file missing';
    returnResponse($response);
endif;

$filePath = $_FILES['filename']['tmp_name'];
$fileContent = file_get_contents($filePath);
if ($fileContent === false || $fileContent === '') :
    $response['error'] = 'Import file empty';
    returnResponse($response);
endif;

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

$msg->logMessage('[DEBUG]', "Import file received for deck $deckNumber");
$result = $deckManager->processInput($deckNumber, $fileContent);
$response['success'] = true;
$response['status'] = $result;
$response['deck_version'] = getDeckVersion($db, $deckNumber);

$requestedFragments = isset($_POST['fragments']) ? (array) $_POST['fragments'] : [];
if (count($requestedFragments) === 0) :
    $requestedFragments = deckdetailDefaultFragments();
endif;

$skip_deckdetail_actions = true;
include '../includes/deckdetail_data.php';
include '../includes/fragments/deckdetail_mana_data.php';
$msg->logMessage(
    '[DEBUG]',
    "Deck import fragments requested for deck $deckNumber: " . implode(', ', array_map('strval', $requestedFragments))
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
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
