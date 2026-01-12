<?php

/*
Version:     1.29
Date:        12/01/26
Name:        ajaxdeckfragments.php
Purpose:     AJAX fragment updates for deck detail derived sections.
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
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
    'fragments' => []
];

$expectedReferringPages = [
    $myURL . '/deckdetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest(
    $expectedReferringPages,
    $appConfig,
    'ajaxdeckfragments.php'
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

$deckOwnerCheck = $deckManager->assertDeckOwner($deckNumber, $user, 'ajaxdeckfragments.php');
if ($deckOwnerCheck === false) :
    $msg->logMessage('[ERROR]', "Deck ownership check failed for deck $deckNumber");
    $response['error'] = 'Deck ownership check failed';
    returnResponse($response);
endif;

$skip_deckdetail_actions = true;
include APP_ROOT . '/includes/deckdetail_data.php';
include APP_ROOT . '/includes/fragments/deckdetail_mana_data.php';
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
    AjaxResponse::json($response, http_response_code());
}
