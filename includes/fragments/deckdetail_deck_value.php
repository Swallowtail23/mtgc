<?php

/*
Version:     1.1
Date:        24/12/25
Name:        deckdetail_deck_value.php
Purpose:     Deck detail deck value fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/
?>
<div id="deck-value-fragment">
    <?php
    if ($show_mana_block) :
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
            echo "<b>Deck value</b><br>" . $formattedDeckValue . " ($localvalue)";
        else :
            echo "<b>Deck value</b><br>" . $formattedDeckValue;
        endif;
    endif;
    ?>
</div>
