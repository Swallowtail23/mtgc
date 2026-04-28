<?php

/*
Version:     1.27
Date:        28/04/26
Name:        ajaxdeckadd.php
Purpose:     AJAX quick add for deck detail.
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

// Content
require_once APP_ROOT . '/ajax/ajaxdeckfragments_lib.php';

$response = [
    'success' => false,
    'error' => '',
    'status' => ''
];

$expectedReferringPages = [
    $myURL . '/deckdetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxdeckadd.php');
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
$quickadd = filter_input(INPUT_POST, 'quickadd', FILTER_UNSAFE_RAW);

if ($deckNumber === null || $quickadd === null) :
    $response['error'] = 'Missing required parameters';
    returnResponse($response);
endif;

$quickadd = trim($quickadd);
if ($quickadd === '') :
    $response['error'] = 'Empty input';
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

$deckOwnerCheck = $deckManager->assertDeckOwner($deckNumber, $user, 'ajaxdeckadd.php');
if ($deckOwnerCheck === false) :
    $msg->logMessage('[ERROR]', "Deck ownership check failed for deck $deckNumber");
    $response['error'] = 'Deck ownership check failed';
    returnResponse($response);
endif;

$result = $deckManager->processInput($deckNumber, $quickadd);
$response['success'] = true;
$response['status'] = $result;
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
    "Deck add fragments requested for deck $deckNumber: " . implode(', ', array_map('strval', $requestedFragments))
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
