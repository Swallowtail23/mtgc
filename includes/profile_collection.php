<?php
/*
Version:     1.0
Date:        07/12/25
Name:        profile_collection.php
Purpose:     Shared collection value display for profile/collection pages.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0 07/12/25 Initial version
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

if (!isset($msg) || !($msg instanceof Message)) :
    $msg = new Message($logfile ?? null);
endif;

// Get card totals
if (
    $totalcount = $db->query(
        "SELECT sum(IFNULL(normal, 0)) + sum(IFNULL(foil, 0)) + sum(IFNULL(etched, 0)) AS TOTAL
         FROM `$mytable`"
    )
) :
    $rowcount = $totalcount->fetch_array(MYSQLI_ASSOC);
else :
    trigger_error('[ERROR] profile_collection.php: Error: ' . $db->error, E_USER_ERROR);
endif;
if (is_null($rowcount['TOTAL'])) :
    $totalcardcount = 0;
else :
    $totalcardcount = $rowcount['TOTAL'];
endif;
$msg->logMessage('[DEBUG]', "Total card count = $totalcardcount");

if (
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
    )
) :
    $rowmrcount = $totalmrcount->fetch_array(MYSQLI_ASSOC);
else :
    trigger_error('[ERROR] profile_collection.php: Error: ' . $db->error, E_USER_ERROR);
endif;
if (is_null($rowmrcount['TOTALMR'])) :
    $totalmrcardcount = 0;
else :
    $totalmrcardcount = $rowmrcount['TOTALMR'];
endif;
$msg->logMessage('[DEBUG]', "Total mythics and rares count = $totalmrcardcount");

// Get total values
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
if ($totalvalue = $db->query($sqlvalue)) :
    $rowvalue = $totalvalue->fetch_assoc();
    $unformatted_value = $rowvalue['TOTAL'];
    $msg->logMessage('[DEBUG]', "Unformatted value = $unformatted_value");
else :
    trigger_error('[ERROR] profile_collection.php: Error: ' . $db->error, E_USER_ERROR);
endif;

$a = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
$collectionmoney = $a->format($unformatted_value);
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
        <span aria-live="polite">Refreshing</span>
    </div>
    <div id="collection-content">
        <?php
        if (isset($rate) and $rate > 0) :
            $b = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
            $b->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $targetCurrency);
            $currencySymbol = $b->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
            $localvalue = $b->format($unformatted_value * $rate);
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
