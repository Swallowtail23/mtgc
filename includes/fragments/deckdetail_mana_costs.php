<?php

/*
Version:     1.0
Date:        24/12/25
Name:        deckdetail_mana_costs.php
Purpose:     Deck detail mana costs and sources fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/
?>
<div id="deck-mana-costs-fragment">
    <?php
    if ($show_mana_block and $decktype != 'Wishlist') :
        ?>
        <table style="width: 95%;">
            <tr>
                <td style="text-align: center; width: 20%;"><b>Mana:</b></td>
                <td style="text-align: center;"><b>Costs</b></td>
                <td style="text-align: center;"><b>Sources</b></td>
            </tr>
            <?php
            if ($w + $gw > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{W}"); ?> </td>
                <td style="text-align: center;"><?php echo $w === 0 ? '-' : "$w ($w_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gw === 0 ? '-' : "$gw ($gw_percent%)"; ?> </td>
            </tr><?php
            endif;
            if ($u + $gu > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{U}"); ?> </td>
                <td style="text-align: center;"><?php echo $u === 0 ? '-' : "$u ($u_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gu === 0 ? '-' : "$gu ($gu_percent%)"; ?> </td>
            </tr><?php
            endif;
            if ($b + $gb > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{B}"); ?> </td>
                <td style="text-align: center;"><?php echo $b === 0 ? '-' : "$b ($b_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gb === 0 ? '-' : "$gb ($gb_percent%)"; ?> </td>
            </tr><?php
            endif;
            if ($r + $gr > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{R}"); ?> </td>
                <td style="text-align: center;"><?php echo $r === 0 ? '-' : "$r ($r_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gr === 0 ? '-' : "$gr ($gr_percent%)"; ?> </td>
            </tr><?php
            endif;
            if ($g + $gg > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{G}"); ?> </td>
                <td style="text-align: center;"><?php echo $g === 0 ? '-' : "$g ($g_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gg === 0 ? '-' : "$gg ($gg_percent%)"; ?> </td>
            </tr><?php
            endif;
            if ($c + $gc > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;"><?php echo symbolReplace("{C}"); ?> </td>
                <td style="text-align: center;"><?php echo $c === 0 ? '-' : "$c ($c_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gc === 0 ? '-' : "$gc ($gc_percent%)"; ?> </td>
            </tr><?php
            endif; ?>
        </table>
        <?php
    endif;
    ?>
</div>
