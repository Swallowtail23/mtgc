<?php

/*
Version:     1.10
Date:        11/01/26
Name:        profile_collection.php
Purpose:     Shared collection value display for profile/collection pages.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\CollectionStats;
use MTG\Core\Message;

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

$msg = new Message($appConfig);

$statsHelper = new CollectionStats($db, $appConfig);
$stats = $statsHelper->getStats($mytable, $targetCurrency ?? null);

$valueUsd = $stats['value_usd'];
$valueLocal = $stats['value_local'];
$localCurrency = $stats['local_currency'];
$rateUsed = $stats['rate_used'];
$totalcardcount = $stats['card_count'];
$totalmrcardcount = $stats['mr_count'];

$a = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
$collectionmoney = $a->format($valueUsd);
$msg->logMessage('[DEBUG]', "Formatted value = $collectionmoney");
$collectionvalue = "Collection tcgplayer market value <br>US " . $collectionmoney;
$rowcounttotal = number_format($totalcardcount);
$totalmrcardcount = number_format($totalmrcardcount);
?>

<div id="mycollection">
    <h2 class='h2pad'>My Collection</h2>
    <div id="collection-refresh-overlay">
        <span class="material-symbols-outlined refresh" id="collection-refresh-icon">
            refresh
        </span>
        <span>Refreshing</span>
    </div>
    <div id="collection-content">
        <?php
        if (isset($valueLocal) && $valueLocal !== null && $rateUsed !== null && $localCurrency !== null) :
            $b = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
            $b->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $localCurrency);
            $currencySymbol = $b->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
            $localvalue = $b->format($valueLocal);
            echo "$collectionvalue ($localvalue) <br>over $rowcounttotal cards "
                . "($totalmrcardcount M/R).<br>";
        else :
            echo "$collectionvalue over $rowcounttotal cards.<br>";
        endif;

        echo "(Pricing via <a href='https://www.scryfall.com/' target='_blank'>";
        echo "scryfall.com</a>.)<br>";
        ?>
    </div>
</div>
