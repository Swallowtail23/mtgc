<?php

/*
Version:     1.3
Date:        09/01/26
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
$ajaxValidation = validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxcardprice.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', "Invalid CSRF token");
        http_response_code(403);
        echo json_encode(['error' => 'Invalid request token']);
    else :
        //Otherwise forbid access
        $msg->logMessage('[ERROR]', "Not called from valid page");
        http_response_code(403);
        echo 'Access forbidden';
    endif;
    exit();
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    $msg->logMessage('[DEBUG]', "Unauthenticated ajax price request - redirecting to login");
    echo "<meta http-equiv='refresh' content='2;url=/login.php'>"; // redirect if not logged in
    exit();
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
        http_response_code(400);
        echo json_encode(['error' => 'Invalid UUID provided']);
        exit();
    endif;

    $msg->logMessage('[DEBUG]', "Async price refresh for card $cardUUID");

    $priceManager = new \MTG\Cards\PriceManager($db, $logfile, $userEmail);
    $scryfallresult = $priceManager->scryfall($cardUUID);
    $msg->logMessage('[DEBUG]', "Scryfall refresh action '{$scryfallresult['action']}' for $cardUUID");

    if ($scryfallresult['action'] === 'nocard') :
        $msg->logMessage('[ERROR]', "Scryfall refresh failed - no card for $cardUUID");
        http_response_code(404);
        echo json_encode(['error' => 'Card not found']);
        exit();
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
        http_response_code(404);
        echo json_encode(['error' => 'Card not found']);
        exit();
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

    header('Content-Type: application/json');
    echo json_encode(\MTG\Cards\PriceDisplay::buildAjaxResponse($priceHtml, $tcg_buy_uri));
endif;
