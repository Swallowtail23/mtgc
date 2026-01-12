<?php

/*
Version:     1.19
Date:        12/01/26
Name:        ajaxcardprice.php
Purpose:     Async card price refresh for card detail.
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;
use MTG\Cards\CardUtils;
use MTG\Cards\PriceDisplay;
use MTG\Cards\PriceManager;
use MTG\Core\Validation;
use MTG\Core\Http\AjaxResponse;

// Bootstrap
$ctx                        = require dirname(__DIR__) . '/bootstrap.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();

$myURL                      = (string) $appConfig->general('url', '');

// Content
$expectedReferringPages = [
    $myURL . '/carddetail.php'
];
$ajaxValidation = SessionManager::validateAjaxRequest($expectedReferringPages, $appConfig, 'ajaxcardprice.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        AjaxResponse::json(['error' => 'Invalid request token'], 403);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from valid page");
        AjaxResponse::text('Access forbidden', 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $msg->logMessage('[DEBUG]', "Unauthenticated ajax price request - redirecting to login");
    AjaxResponse::text("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
else :
    // AJAX session context
    require_once APP_ROOT . '/ajax/ajax_session.php';
    $sessionUser                = requireAjaxSessionUser($db, $appConfig, $msg);
    $ctx                        = $ctx->withSessionUser($sessionUser);
    $user                       = $ctx->sessionUser()->id();
    $mytable                    = $ctx->sessionUser()->table();
    $userEmail                  = $ctx->sessionUser()->email();
    $fx                         = $ctx->sessionUser()->fxEnabled();
    $targetCurrency             = $ctx->sessionUser()->currency();
    $rate                       = $ctx->sessionUser()->rate();

    $cardUUID = isset($_POST['cardid']) ? Validation::validUUID($_POST['cardid'], $appConfig) : false;
    if ($cardUUID === false) :
        $msg->logMessage('[ERROR]', "Invalid card UUID provided");
        AjaxResponse::json(['error' => 'Invalid UUID provided'], 400);
    endif;

    $msg->logMessage('[DEBUG]', "Async price refresh for card $cardUUID");

    $priceManager = new PriceManager($db, $appConfig, $userEmail);
    $scryfallresult = $priceManager->scryfall($cardUUID);
    $msg->logMessage('[DEBUG]', "Scryfall refresh action '{$scryfallresult['action']}' for $cardUUID");

    if ($scryfallresult['action'] === 'nocard') :
        $msg->logMessage('[ERROR]', "Scryfall refresh failed - no card for $cardUUID");
        AjaxResponse::json(['error' => 'Card not found'], 404);
    endif;

    if ($scryfallresult['action'] === 'update' or $scryfallresult['action'] === 'get') :
        $msg->logMessage('[DEBUG]', "Scryfall updated - refreshing collection values for $cardUUID");
        $priceManager->updateCollectionValues($mytable, $cardUUID);
    else :
        $msg->logMessage('[DEBUG]', "Scryfall cache read - no collection value refresh");
    endif;

    $query = "SELECT cards_scry.finishes,
                    cards_scry.price,
                    cards_scry.price_foil,
                    cards_scry.price_etched,
                    scryfalljson.tcg_buy_uri
                FROM cards_scry
                LEFT JOIN scryfalljson ON cards_scry.id = scryfalljson.id
                WHERE cards_scry.id = ?
                LIMIT 1";
    $result = $db->execute_query($query, [$cardUUID]);
    if ($result === false or $result->num_rows < 1) :
        $msg->logMessage('[ERROR]', "Price lookup failed for $cardUUID");
        AjaxResponse::json(['error' => 'Card not found'], 404);
    endif;

    $row = $result->fetch_assoc();
    $finishes = array();
    if (!empty($row['finishes'])) :
        $decodedFinishes = json_decode($row['finishes'], true);
        if (is_array($decodedFinishes)) :
            $finishes = $decodedFinishes;
        else :
            $msg->logMessage('[DEBUG]', "Finishes decode failed for $cardUUID");
        endif;
    endif;

    if (!empty($finishes)) :
        $cardtypes = CardUtils::cardTypes($finishes);
    else :
        $msg->logMessage('[DEBUG]', "Finishes empty for $cardUUID, defaulting cardtypes to none");
        $cardtypes = 'none';
    endif;

    $tcg_buy_uri = $scryfallresult['tcg_uri'] ?? ($row['tcg_buy_uri'] ?? null);

    $priceData = PriceDisplay::computePrices(
        $scryfallresult,
        $row,
        $cardtypes,
        $rate,
        $appConfig
    );
    $priceHtml = PriceDisplay::renderTable($priceData, $fx, $targetCurrency);
    $msg->logMessage('[DEBUG]', "Price HTML built for $cardUUID");

    AjaxResponse::json(PriceDisplay::buildAjaxResponse($priceHtml, $tcg_buy_uri));
endif;
