<?php

/*
Version:     1.7
Date:        10/01/26
Name:        ajaxcollectionvalue.php
Purpose:     Recalculate collection values asynchronously for the profile page.
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

if (file_exists('../includes/sessionname.local.php')) :
    require '../includes/sessionname.local.php';
else :
    require '../includes/sessionname_template.php';
endif;
startCustomSession();
require '../includes/ini.php';
require '../includes/error_handling.php';
require '../includes/functions.php';
$msg = new \MTG\Core\Message($logfile);

$expectedReferringPages = [
    $myURL . '/profile.php',
    $myURL . '/collection.php',
];
$ajaxValidation = \MTG\Auth\SessionManager::validateAjaxRequest($expectedReferringPages, $logfile, 'ajaxcollectionvalue.php');
if ($ajaxValidation['valid'] === false) :
    if ($ajaxValidation['reason'] === 'csrf') :
        $msg->logMessage('[ERROR]', 'ajaxcollectionvalue.php: Invalid CSRF token');
        ajaxRespondJson(['error' => 'Invalid request token'], 403);
    else :
        $msg->logMessage('[ERROR]', 'ajaxcollectionvalue.php: Not called from valid page');
        ajaxRespondJson(['error' => 'Access forbidden'], 403);
    endif;
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    ajaxRespondText("<meta http-equiv='refresh' content='2;url=/login.php'>"); // redirect if not logged in
endif;

// Need to run these as secpagesetup not run (see page notes)
$sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
$userArray = $sessionManager->getUserInfo();
if ($userArray === false) :
    $msg->logMessage('[ERROR]', 'ajaxcollectionvalue.php: User array returned false');
    ajaxRespondJson(['error' => 'User not found'], 500);
endif;

$user = $userArray['usernumber'];
$mytable = $userArray['table'];
$fx = $userArray['fx'];
$targetCurrency = $userArray['currency'];
$rate = $userArray['rate'];
$userEmail = $_SESSION['useremail'];

$msg->logMessage('[DEBUG]', "ajaxcollectionvalue.php called by $userEmail for table $mytable");

$priceManager = new \MTG\Cards\PriceManager($db, $logfile, $userEmail);
$updatedRows = $priceManager->updateCollectionValues($mytable);

$statsHelper = new \MTG\Cards\CollectionStats($db, $logfile, $fxAPI ?? '', $fxLocal ?? '', $adminip ?? 1);
$stats = $statsHelper->getStats($user, $mytable, $targetCurrency);

$unformatted_value = $stats['value_usd'];
$rowcount = $stats['card_count'];
$totalmrcardcount_fmt = number_format($stats['mr_count']);
$localValueStr = '';
$localCurrency = $stats['local_currency'];
$rateUsed = $stats['rate_used'];

$a = new \NumberFormatter('en-US', \NumberFormatter::CURRENCY);
$collectionmoney = $a->format($unformatted_value);
$collectionvalue = "Collection tcgplayer market value <br>US " . $collectionmoney;
$rowcounttotal = number_format($rowcount);

if ($localCurrency !== null && $rateUsed !== null && $stats['value_local'] !== null) :
    $b = new \NumberFormatter('en-US', \NumberFormatter::CURRENCY);
    $b->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $localCurrency);
    $localValueStr = $b->format($stats['value_local']);
endif;

ob_start();
if ($localValueStr !== '') :
    echo "$collectionvalue ($localValueStr) <br>over $rowcounttotal cards ($totalmrcardcount_fmt M/R).<br>";
else :
    echo "$collectionvalue over $rowcounttotal cards.<br>";
endif;
echo "(Pricing via <a href='https://www.scryfall.com/' target='_blank'>";
echo "scryfall.com</a>.)<br>";
$rowcounttotal = number_format($rowcount);
$html = ob_get_clean();

ajaxRespondJson(
    [
        'success' => true,
        'html' => $html,
        'updated_rows' => $updatedRows,
    ]
);
