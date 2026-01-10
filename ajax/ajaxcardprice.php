<?php

/*
Version:     1.4
Date:        10/01/26
Name:        ajaxcardprice.php
Purpose:     Async card price refresh for card detail.
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

$expectedReferringPages = [
    $myURL . '/carddetail.php'
];
$ajaxValidation = \MTG\Auth\SessionManager::validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxcardprice.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        ajaxRespondJson(['error' => 'Invalid request token'], 403);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from valid page");
        ajaxRespondText('Access forbidden', 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $msg->logMessage('[DEBUG]', "Unauthenticated ajax price request - redirecting to login");
    ajaxRespondText("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
else :
    //Need to run these as secpagesetup not run (see page notes)
    $sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
    $userArray = $sessionManager->getUserInfo();
    $user = $userArray['usernumber'];
    $mytable = $userArray['table'];
    $userEmail = $_SESSION['useremail'];
    $fx = $userArray['fx'];
    $targetCurrency = $userArray['currency'];
    $rate = $userArray['rate'];

    $cardUUID = isset($_POST['cardid']) ? validUUID($_POST['cardid']) : false;
    if ($cardUUID === false) :
        $msg->logMessage('[ERROR]', "Invalid card UUID provided");
        ajaxRespondJson(['error' => 'Invalid UUID provided'], 400);
    endif;

    $msg->logMessage('[DEBUG]', "Async price refresh for card $cardUUID");

    $priceManager = new \MTG\Cards\PriceManager($db, $logfile, $userEmail);
    $scryfallresult = $priceManager->scryfall($cardUUID);
    $msg->logMessage('[DEBUG]', "Scryfall refresh action '{$scryfallresult['action']}' for $cardUUID");

    if ($scryfallresult['action'] === 'nocard') :
        $msg->logMessage('[ERROR]', "Scryfall refresh failed - no card for $cardUUID");
        ajaxRespondJson(['error' => 'Card not found'], 404);
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
        ajaxRespondJson(['error' => 'Card not found'], 404);
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
        $cardtypes = cardTypes($finishes);
    else :
        $msg->logMessage('[DEBUG]', "Finishes empty for $cardUUID, defaulting cardtypes to none");
        $cardtypes = 'none';
    endif;

    $tcg_buy_uri = $scryfallresult['tcg_uri'] ?? ($row['tcg_buy_uri'] ?? null);

    $priceData = \MTG\Cards\PriceDisplay::computePrices(
        $scryfallresult,
        $row,
        $cardtypes,
        $rate,
        $logfile
    );
    $priceHtml = \MTG\Cards\PriceDisplay::renderTable($priceData, $fx, $targetCurrency);
    $msg->logMessage('[DEBUG]', "Price HTML built for $cardUUID");

    ajaxRespondJson(\MTG\Cards\PriceDisplay::buildAjaxResponse($priceHtml, $tcg_buy_uri));
endif;
