<?php

/*
Version:     1.6
Date:        28/04/26
Name:        deckdetail_deck_value.php
Purpose:     Deck detail deck value fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$show_mana_block = $show_mana_block ?? false;
$deckvalue = $deckvalue ?? 0;
$targetCurrency = $targetCurrency ?? 'USD';
$rate = $rate ?? 0;
$localvalue = '';
$hasDeckValue = $show_mana_block;
?>
<div id="deck-value-fragment" data-has-content="<?php echo $hasDeckValue ? '1' : '0'; ?>">
    <?php
    if ($hasDeckValue) :
        $currencyFormatter = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
        $formattedDeckValue = $currencyFormatter->format($deckvalue);
        $msg->logMessage('[DEBUG]', "Formatted value = $formattedDeckValue");
        $fxUpdating = (isset($fxPending) && $fxPending === true && isset($fxMissing) && $fxMissing === true);
        $fxUpdatingLabel = '<span class="fx-pending">Updating</span>';

        if (isset($rate) and $rate > 0) :
            $localFormatter = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
            $localFormatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $targetCurrency);
            $currencySymbol = $localFormatter->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
            $localvalue = $localFormatter->format($deckvalue * $rate);
        endif;
        if (isset($rate) and $rate > 0) :
            echo "<h4>Deck value</h4>" . $formattedDeckValue . " ($localvalue)";
        elseif ($fxUpdating === true) :
            echo "<h4>Deck value</h4>" . $formattedDeckValue . " ($fxUpdatingLabel)";
        else :
            echo "<h4>Deck value</h4>" . $formattedDeckValue;
        endif;
    endif;
    ?>
</div>
