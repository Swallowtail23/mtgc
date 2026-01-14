<?php

/*
Version:     1.5
Date:        15/01/26
Name:        ajaxdecksimport.php
Purpose:     AJAX deck import for deck list page.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\DeckManager;
use MTG\Core\Http\AjaxResponse;

// Bootstrap
$ctx                        = require dirname(__DIR__) . '/bootstrap.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();

$myURL                      = (string) $appConfig->general('url', '');

$response = [
    'success' => false,
    'error' => '',
    'decknumber' => null,
    'deckname' => ''
];

$expectedReferringPages = [
    $myURL . '/decks.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest(
    $expectedReferringPages,
    $appConfig,
    'ajaxdecksimport.php'
);
if ($ajaxValidation['valid'] === false) :
    $msg->logMessage('[DEBUG]', "Decks import failed referrer/CSRF validation");
    if ($ajaxValidation['reason'] === 'csrf') :
        $response['error'] = 'Invalid request token';
    else :
        $response['error'] = 'Access forbidden';
    endif;
    http_response_code(403);
    returnResponse($response);
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $msg->logMessage('[DEBUG]', "Decks import blocked: user not logged in");
    $response['error'] = 'User not logged in';
    returnResponse($response);
endif;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') :
    $msg->logMessage('[DEBUG]', "Decks import blocked: invalid request method");
    $response['error'] = 'Invalid request method';
    returnResponse($response);
endif;

$csrfToken = $_POST['csrf_token'] ?? '';
if (!SessionManager::validateCsrfToken($csrfToken)) :
    $msg->logMessage('[DEBUG]', "Decks import blocked: invalid CSRF token");
    $response['error'] = 'Invalid request token';
    returnResponse($response);
endif;

$fileContent = '';
if (isset($_FILES['filename']) && is_uploaded_file($_FILES['filename']['tmp_name'])) :
    $filePath = $_FILES['filename']['tmp_name'];
    $fileContent = file_get_contents($filePath);
    $msg->logMessage('[DEBUG]', "Decks import file received");
elseif (isset($_POST['paste'])) :
    $fileContent = (string) $_POST['paste'];
    $msg->logMessage('[DEBUG]', "Decks import paste received");
endif;

$fileContent = trim($fileContent);
if ($fileContent === '') :
    $msg->logMessage('[DEBUG]', "Decks import blocked: file or paste empty");
    $response['error'] = 'Import content empty';
    returnResponse($response);
endif;

// AJAX session context
require_once APP_ROOT . '/ajax/ajax_session.php';
$sessionUser                = requireAjaxSessionUser($db, $appConfig, $msg);
$ctx                        = $ctx->withSessionUser($sessionUser);
$user                       = $ctx->sessionUser()->id();
$userEmail                  = $ctx->sessionUser()->email();

$deckManager = new DeckManager(
    $db,
    $appConfig,
    $gameRules,
    $userEmail
);

$headerData = $deckManager->extractDeckHeader($fileContent);
$deckName = $headerData['name'];
if ($deckName !== '') :
    $msg->logMessage('[DEBUG]', "Decks import detected deck name: '$deckName'");
else :
    $deckName = date("j F Y, g:i:sa");
    $msg->logMessage('[DEBUG]', "Decks import using fallback deck name: '$deckName'");
endif;
$originalDeckName = $deckName;
$deckName = resolveDeckNameConflict($db, $user, $deckName, $msg);
if ($deckName !== $originalDeckName) :
    $msg->logMessage('[DEBUG]', "Decks import adjusted deck name to '$deckName' due to name conflict");
endif;

$msg->logMessage('[DEBUG]', "Creating deck '$deckName' for user $user");
$decksuccess = $deckManager->addDeck($user, $deckName);
if (!isset($decksuccess['flag']) || $decksuccess['flag'] !== 1) :
    $msg->logMessage('[DEBUG]', "Decks import failed to create deck for user $user");
    $response['error'] = 'Deck creation failed';
    returnResponse($response);
endif;

$deckNumber = $decksuccess['decknumber'];
$deckType = $headerData['type'];
if ($deckType !== '') :
    $msg->logMessage('[DEBUG]', "Decks import applying deck type '$deckType' to deck $deckNumber");
    $setTypeResult = $deckManager->setDeckType($deckNumber, $deckType);
    $msg->logMessage('[DEBUG]', "Decks import set deck type result: {$setTypeResult}");
endif;
$msg->logMessage('[DEBUG]', "Decks import created deck $deckNumber, importing cards");
$result = $deckManager->processInput($deckNumber, $fileContent);
$msg->logMessage('[DEBUG]', "Decks import completed for deck $deckNumber with status '$result'");
$cardCount = getDeckCardCount($db, $deckNumber, $msg);
if ($cardCount === null) :
    $msg->logMessage(
        '[DEBUG]',
        "Decks import aborted: unable to validate card count for deck $deckNumber"
    );
    $response['error'] = 'Import validation failed';
    returnResponse($response);
endif;
if ($cardCount === 0) :
    $msg->logMessage(
        '[DEBUG]',
        "Decks import aborted: no cards were added for deck $deckNumber, deleting empty deck"
    );
    deleteDeckByNumber($db, $deckNumber, $msg);
    $response['error'] = 'Import produced no cards';
    returnResponse($response);
endif;

$response['success'] = true;
$response['decknumber'] = (int) $deckNumber;
$response['deckname'] = $deckName;
$response['decktype'] = $deckType;
$response['status'] = $result;
returnResponse($response);

function resolveDeckNameConflict($db, $user, $deckName, $msg)
{
    $candidate = $deckName;
    $counter = 1;
    while (deckNameExists($db, $user, $candidate)) :
        $counter++;
        $suffix = " ($counter)";
        $maxLength = 150 - mb_strlen($suffix);
        if ($maxLength < 1) :
            $maxLength = 150;
        endif;
        $baseName = mb_substr($deckName, 0, $maxLength);
        $candidate = $baseName . $suffix;
        $msg->logMessage('[DEBUG]', "Decks import name conflict, trying '$candidate'");
    endwhile;
    return $candidate;
}

function deckNameExists($db, $user, $deckName)
{
    $query = "SELECT decknumber FROM decks WHERE owner = ? AND deckname = ? LIMIT 1";
    $result = $db->execute_query($query, [$user, $deckName]);
    if ($result === false) :
        return false;
    endif;
    return $result->num_rows > 0;
}

function returnResponse($response)
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    AjaxResponse::json($response, http_response_code());
}

function getDeckCardCount($db, $deckNumber, $msg)
{
    $sql = "SELECT COUNT(*) AS total FROM deckcards WHERE decknumber = ? AND (cardqty > 0 OR sideqty > 0)";
    $msg->logMessage('[DEBUG]', "Decks import card count query for deck $deckNumber");
    $result = $db->execute_query($sql, [$deckNumber]);
    if ($result === false) :
        $msg->logMessage('[ERROR]', "Decks import card count failed for deck $deckNumber");
        return null;
    endif;
    $row = $result->fetch_assoc();
    return (int) ($row['total'] ?? 0);
}

function deleteDeckByNumber($db, $deckNumber, $msg)
{
    $msg->logMessage('[DEBUG]', "Decks import removing empty deck $deckNumber");
    $deleteCardsSql = "DELETE FROM deckcards WHERE decknumber = ?";
    $deleteDeckSql = "DELETE FROM decks WHERE decknumber = ?";
    $deleteCardsResult = $db->execute_query($deleteCardsSql, [$deckNumber]);
    if ($deleteCardsResult === false) :
        $msg->logMessage('[ERROR]', "Decks import failed to delete deckcards for deck $deckNumber");
    endif;
    $deleteDeckResult = $db->execute_query($deleteDeckSql, [$deckNumber]);
    if ($deleteDeckResult === false) :
        $msg->logMessage('[ERROR]', "Decks import failed to delete deck $deckNumber");
    endif;
}
