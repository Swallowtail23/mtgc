<?php

/*
Version:     1.3
Date:        26/12/25
Name:        deckdetail_deck_value.php
Purpose:     Deck detail deck value fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$hasDeckValue = $show_mana_block;
?>
<div id="deck-value-fragment" data-has-content="<?php echo $hasDeckValue ? '1' : '0'; ?>">
    <?php
    if ($hasDeckValue) :
        $currencyFormatter = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
        $formattedDeckValue = $currencyFormatter->format($deckvalue);
        $msg->logMessage('[DEBUG]', "Formatted value = $formattedDeckValue");
        if (isset($rate) and $rate > 0) :
            $localFormatter = new \NumberFormatter("en-US", \NumberFormatter::CURRENCY);
            $localFormatter->setTextAttribute(\NumberFormatter::CURRENCY_CODE, $targetCurrency);
            $currencySymbol = $localFormatter->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
            $localvalue = $localFormatter->format($deckvalue * $rate);
        endif;
        if (isset($rate) and $rate > 0) :
            echo "<h4>Deck value</h4>" . $formattedDeckValue . " ($localvalue)";
        else :
            echo "<h4>Deck value</h4>" . $formattedDeckValue;
        endif;
    endif;
    ?>
</div>
