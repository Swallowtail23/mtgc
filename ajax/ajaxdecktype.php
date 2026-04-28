<?php

/*
Version:     1.30
Date:        28/04/26
Name:        ajaxdecktype.php
Purpose:     AJAX deck type updates for deck detail.
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

$rulesCommanderDeckTypes = $gameRules->getArray('commander_decktypes');
$rulesValidTypes = $gameRules->getArray('validtypes');

// Content
require_once APP_ROOT . '/ajax/ajaxdeckfragments_lib.php';

$response = [
    'success' => false,
    'error' => ''
];

$expectedReferringPages = [
    $myURL . '/deckdetail.php',
    $myURL . '/decks.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxdecktype.php');
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
$updateType = filter_input(INPUT_POST, 'updatetype', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if ($deckNumber === null || $updateType === null) :
    $response['error'] = 'Missing required parameters';
    returnResponse($response);
endif;

if (!in_array($updateType, $rulesValidTypes, true)) :
    $response['error'] = 'Invalid deck type';
    returnResponse($response);
endif;

// AJAX session context
require_once APP_ROOT . '/ajax/ajax_session.php';
$sessionUser                = requireAjaxSessionUser($db, $appConfig, $msg);
$ctx                        = $ctx->withSessionUser($sessionUser);
$user                       = $ctx->sessionUser()->id();
$mytable                    = $ctx->sessionUser()->table();
$userEmail                  = $ctx->sessionUser()->email();
$targetCurrency             = $ctx->sessionUser()->currency();
$rate                       = $ctx->sessionUser()->rate();
if ($mytable === '') :
    $msg->logMessage('[ERROR]', "Missing collection table for user $user");
    $response['error'] = 'Missing collection table';
    returnResponse($response);
endif;

$deckManager = new DeckManager(
    $db,
    $appConfig,
    $gameRules,
    $userEmail
);

$deckOwnerCheck = $deckManager->assertDeckOwner($deckNumber, $user, 'ajaxdecktype.php');
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

if (!in_array($updateType, $rulesCommanderDeckTypes, true)) :
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

if (in_array($updateType, $rulesCommanderDeckTypes, true)) :
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

$deckManager->bumpDeckUpdatedAt($deckNumber);

$response['success'] = true;
$response['decktype'] = $updateType;
$response['is_commander'] = in_array($updateType, $rulesCommanderDeckTypes, true);
$response['deck_version'] = getDeckVersion($db, $deckNumber);

$requestedFragments = isset($_POST['fragments']) ? (array) $_POST['fragments'] : [];
if (count($requestedFragments) === 0) :
    $requestedFragments = deckdetailDefaultFragments();
endif;

$skip_deckdetail_actions = true;
include APP_ROOT . '/includes/deckdetail_data.php';
include APP_ROOT . '/includes/fragments/deckdetail_mana_data.php';
$msg->logMessage(
    '[DEBUG]',
    "Deck type fragments requested for deck $deckNumber: " . implode(', ', array_map('strval', $requestedFragments))
);
$response['fragments'] = deckdetailRenderFragments($requestedFragments);
if (isset($deck_version)) :
    $response['deck_version'] = (int) $deck_version;
    $response['version'] = (int) $deck_version;
endif;

returnResponse($response);

function getDeckVersion(\mysqli $db, int|string $deckNumber): int
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

function returnResponse(array $response): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    AjaxResponse::json($response, http_response_code());
}
