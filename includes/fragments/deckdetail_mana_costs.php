<?php

/*
Version:     1.4
Date:        28/04/26
Name:        deckdetail_mana_costs.php
Purpose:     Deck detail mana costs and sources fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\CardUtils;

$show_mana_block = $show_mana_block ?? false;
$decktype = $decktype ?? '';
$w = $w ?? 0;
$u = $u ?? 0;
$b = $b ?? 0;
$r = $r ?? 0;
$g = $g ?? 0;
$c = $c ?? 0;
$gw = $gw ?? 0;
$gu = $gu ?? 0;
$gb = $gb ?? 0;
$gr = $gr ?? 0;
$gg = $gg ?? 0;
$gc = $gc ?? 0;
$w_percent = $w_percent ?? 0;
$u_percent = $u_percent ?? 0;
$b_percent = $b_percent ?? 0;
$r_percent = $r_percent ?? 0;
$g_percent = $g_percent ?? 0;
$c_percent = $c_percent ?? 0;
$gw_percent = $gw_percent ?? 0;
$gu_percent = $gu_percent ?? 0;
$gb_percent = $gb_percent ?? 0;
$gr_percent = $gr_percent ?? 0;
$gg_percent = $gg_percent ?? 0;
$gc_percent = $gc_percent ?? 0;
$hasManaCosts = $show_mana_block && $decktype != 'Wishlist';
?>
<div id="deck-mana-costs-fragment" data-has-content="<?php echo $hasManaCosts ? '1' : '0'; ?>">
    <?php
    if ($hasManaCosts) :
        ?>
        <h4>Mana distribution</h4>
        <table style="width: 95%;">
            <tr>
                <td style="text-align: center; width: 20%;">&nbsp;</td>
                <td style="text-align: center;"><i>Costs</i></td>
                <td style="text-align: center;"><i>Sources</i></td>
            </tr>
            <?php
            if ($w + $gw > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;">
                    <?php echo CardUtils::symbolReplaceFont("{W}"); ?>
                </td>
                <td style="text-align: center;"><?php echo $w === 0 ? '-' : "$w ($w_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gw === 0 ? '-' : "$gw ($gw_percent%)"; ?> </td>
            </tr><?php
            endif;
            if ($u + $gu > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;">
                    <?php echo CardUtils::symbolReplaceFont("{U}"); ?>
                </td>
                <td style="text-align: center;"><?php echo $u === 0 ? '-' : "$u ($u_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gu === 0 ? '-' : "$gu ($gu_percent%)"; ?> </td>
            </tr><?php
            endif;
            if ($b + $gb > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;">
                    <?php echo CardUtils::symbolReplaceFont("{B}"); ?>
                </td>
                <td style="text-align: center;"><?php echo $b === 0 ? '-' : "$b ($b_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gb === 0 ? '-' : "$gb ($gb_percent%)"; ?> </td>
            </tr><?php
            endif;
            if ($r + $gr > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;">
                    <?php echo CardUtils::symbolReplaceFont("{R}"); ?>
                </td>
                <td style="text-align: center;"><?php echo $r === 0 ? '-' : "$r ($r_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gr === 0 ? '-' : "$gr ($gr_percent%)"; ?> </td>
            </tr><?php
            endif;
            if ($g + $gg > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;">
                    <?php echo CardUtils::symbolReplaceFont("{G}"); ?>
                </td>
                <td style="text-align: center;"><?php echo $g === 0 ? '-' : "$g ($g_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gg === 0 ? '-' : "$gg ($gg_percent%)"; ?> </td>
            </tr><?php
            endif;
            if ($c + $gc > 0) : ?>
            <tr>
                <td style="text-align: center; width: 20%;">
                    <?php echo CardUtils::symbolReplaceFont("{C}"); ?>
                </td>
                <td style="text-align: center;"><?php echo $c === 0 ? '-' : "$c ($c_percent%)"; ?> </td>
                <td style="text-align: center;"><?php echo $gc === 0 ? '-' : "$gc ($gc_percent%)"; ?> </td>
            </tr><?php
            endif; ?>
        </table>
        <?php
    endif;
    ?>
</div>
