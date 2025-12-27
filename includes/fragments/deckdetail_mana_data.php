<?php

/*
Version:     1.4
Date:        26/12/25
Name:        deckdetail_mana_data.php
Purpose:     Deck detail mana/deck value calculations for fragments.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$show_mana_block = false;
$avgcmc = null;
$main_total = isset($total) ? $total : ($total_cards ?? 0);
$side_total = isset($sidetotal) ? $sidetotal : ($side_total_cards ?? 0);
if (($main_total + $side_total > 0) and $decktype != 'Wishlist') :
    $show_mana_block = true;
    if (($main_total - $lands) != 0) :
        $avgcmc = round(($cmctotal / ($main_total - $lands)), 2);
    endif;

    $w = (float) $w;
    $u = (float) $u;
    $b = (float) $b;
    $r = (float) $r;
    $g = (float) $g;
    $c = (float) $c;
    $gw = (float) $gw;
    $gu = (float) $gu;
    $gb = (float) $gb;
    $gr = (float) $gr;
    $gg = (float) $gg;
    $gc = (float) $gc;

    $w_percent = $u_percent = $b_percent = $r_percent = $g_percent
        = $gw_percent = $gu_percent = $gb_percent = $gr_percent
        = $gg_percent = $c_percent = $gc_percent = 0;
    if ($w + $u + $b + $r + $g + $c + $gw + $gu + $gb + $gr + $gg + $gc > 0) :
        $totalpips = $w + $u + $b + $r + $g + $c;
        if ($w > 0) :
            $w_percent = number_format($w / $totalpips * 100, 0);
        endif;
        if ($u > 0) :
            $u_percent = number_format($u / $totalpips * 100, 0);
        endif;
        if ($b > 0) :
            $b_percent = number_format($b / $totalpips * 100, 0);
        endif;
        if ($r > 0) :
            $r_percent = number_format($r / $totalpips * 100, 0);
        endif;
        if ($g > 0) :
            $g_percent = number_format($g / $totalpips * 100, 0);
        endif;
        if ($c > 0) :
            $c_percent = number_format($c / $totalpips * 100, 0);
        endif;
    endif;
    if ($gw + $gu + $gb + $gr + $gg + $gc > 0) :
        $totalmana = $gw + $gu + $gb + $gr + $gg + $gc;
        if ($gw > 0) :
            $gw_percent = number_format($gw / $totalmana * 100, 0);
        endif;
        if ($gu > 0) :
            $gu_percent = number_format($gu / $totalmana * 100, 0);
        endif;
        if ($gb > 0) :
            $gb_percent = number_format($gb / $totalmana * 100, 0);
        endif;
        if ($gr > 0) :
            $gr_percent = number_format($gr / $totalmana * 100, 0);
        endif;
        if ($gg > 0) :
            $gg_percent = number_format($gg / $totalmana * 100, 0);
        endif;
        if ($gc > 0) :
            $gc_percent = number_format($gc / $totalmana * 100, 0);
        endif;
    endif;
endif;
