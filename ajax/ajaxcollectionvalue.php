<?php

/*
Version:     1.5
Date:        08/12/25
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

// Check if the request is coming from valid page
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages = [
    $myURL . '/profile.php',
    $myURL . '/collection.php',
];

// Normalize the referring page URL
$normalizedReferringPage = str_replace('www.', '', $referringPage);

$isValidReferrer = false;
foreach ($expectedReferringPages as $page) :
    // Normalize each expected referring page URL
    $normalizedPage = str_replace('www.', '', $page);
    if (strpos($normalizedReferringPage, $normalizedPage) !== false) :
        $isValidReferrer = true;
        break;
    endif;
endforeach;

if (!$isValidReferrer) :
    $msg->logMessage('[ERROR]', 'ajaxcollectionvalue.php: Not called from valid page');
    http_response_code(403);
    echo json_encode(['error' => 'Access forbidden']);
    exit();
endif;

if (!isset($_SESSION["logged"], $_SESSION['user']) || $_SESSION["logged"] !== true) :
    echo "<meta http-equiv='refresh' content='2;url=/login.php'>"; // redirect if not logged in
    exit();
endif;

// Need to run these as secpagesetup not run (see page notes)
$sessionManager = new \MTG\Auth\SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
$userArray = $sessionManager->getUserInfo();
if ($userArray === false) :
    $msg->logMessage('[ERROR]', 'ajaxcollectionvalue.php: User array returned false');
    http_response_code(500);
    echo json_encode(['error' => 'User not found']);
    exit();
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

echo json_encode(
    [
        'success' => true,
        'html' => $html,
        'updated_rows' => $updatedRows,
    ]
);
exit();
