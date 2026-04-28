<?php

/*
Version:     1.2
Date:        28/04/26
Name:        deckdetail_buy_missing.php
Purpose:     Deck detail buy missing fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$total = $total ?? 0;
$sidetotal = $sidetotal ?? 0;
$requiredlist = $requiredlist ?? '';
$requiredbuy = $requiredbuy ?? '';

?>
<tbody id="deck-buy-fragment">
    <?php
    if ($total + $sidetotal > 0 and $requiredlist != '') :
        $tcgUrl = "https://store.tcgplayer.com/list/selectproductmagic.aspx"
            . "?partner=MTGCOLLECT&c={$requiredbuy}";
        ?>
        <tr style="height:36px;">
            <td>Buy missing:</td>
            <td>
                <a
                    href="<?php echo $tcgUrl; ?>"
                    target="_blank"
                    class="profilebutton tcgbuybutton"
                >
                    TCGPLAYER
                </a>
            </td>
        </tr>
        <?php
    endif;
    ?>
</tbody>
