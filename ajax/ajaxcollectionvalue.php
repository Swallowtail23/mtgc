<?php

/*
Version:     1.0
Date:        06/12/25
Name:        ajaxcollectionvalue.php
Purpose:     Recalculate collection values asynchronously for the profile page.
Notes:       The page does not run standard secpagesetup as it breaks the ajax login catch.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0 06/12/25 Initial version
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
$msg = new Message($logfile);

// Check if the request is coming from valid page
$referringPage = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$expectedReferringPages = [
    $myURL . '/profile.php',
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
$sessionManager = new SessionManager($db, $adminip, $_SESSION, $fxAPI, $fxLocal, $logfile);
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

$priceManager = new PriceManager($db, $logfile, $userEmail);
$updatedRows = $priceManager->updateCollectionValues($mytable);

// Get card totals
$rowcount = 0;
$totalmrcardcount = 0;
$unformatted_value = 0;

$totalcount = $db->query(
    "SELECT sum(IFNULL(normal, 0)) + sum(IFNULL(foil, 0)) + sum(IFNULL(etched, 0)) AS TOTAL
     FROM `$mytable`"
);
if ($totalcount !== false) :
    $rowcountdata = $totalcount->fetch_array(MYSQLI_ASSOC);
    $rowcount = (int) ($rowcountdata['TOTAL'] ?? 0);
else :
    trigger_error('[ERROR] ajaxcollectionvalue.php: Error: ' . $db->error, E_USER_ERROR);
endif;

$totalmrcount = $db->query(
    "SELECT
      SUM(IFNULL(`$mytable`.normal, 0))
      +
      SUM(IFNULL(`$mytable`.foil, 0))
      +
      SUM(IFNULL(`$mytable`.etched, 0))
     AS TOTALMR
     FROM `$mytable`
     LEFT JOIN cards_scry
     ON `$mytable`.id = cards_scry.id
     WHERE rarity IN ('mythic', 'rare');"
);
if ($totalmrcount !== false) :
    $rowmrcount = $totalmrcount->fetch_array(MYSQLI_ASSOC);
    $totalmrcardcount = (int) ($rowmrcount['TOTALMR'] ?? 0);
else :
    trigger_error('[ERROR] ajaxcollectionvalue.php: Error: ' . $db->error, E_USER_ERROR);
endif;

$sqlvalue = "SELECT (
                COALESCE(SUM(`$mytable`.normal * price),0)
                +
                COALESCE(SUM(`$mytable`.foil *
                    CASE
                        WHEN price_foil IS NOT NULL AND price_foil > 0 THEN price_foil
                        WHEN price IS NOT NULL AND price > 0 THEN price
                        ELSE 0
                    END), 0)
                +
                COALESCE(SUM(`$mytable`.etched *
                    CASE
                        WHEN price_etched IS NOT NULL AND price_etched > 0 THEN price_etched
                        WHEN price IS NOT NULL AND price > 0 THEN price
                        ELSE 0
                    END), 0)
                )
                as TOTAL FROM `$mytable` LEFT JOIN cards_scry ON `$mytable`.id = cards_scry.id";
$totalvalue = $db->query($sqlvalue);
if ($totalvalue !== false) :
    $rowvalue = $totalvalue->fetch_assoc();
    $unformatted_value = $rowvalue['TOTAL'] ?? 0;
else :
    trigger_error('[ERROR] ajaxcollectionvalue.php: Error: ' . $db->error, E_USER_ERROR);
endif;

$a = new \NumberFormatter('en-US', \NumberFormatter::CURRENCY);
$collectionmoney = $a->format($unformatted_value);
$collectionvalue = "Collection tcgplayer market value <br>US " . $collectionmoney;
$rowcounttotal = number_format($rowcount);
$totalmrcardcount_fmt = number_format($totalmrcardcount);
$localValueStr = '';

if (isset($rate) && $rate > 0) :
    $b = new \NumberFormatter('en-US', \NumberFormatter::CURRENCY);
    $b->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $targetCurrency);
    $localValueStr = $b->format($unformatted_value * $rate);
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
