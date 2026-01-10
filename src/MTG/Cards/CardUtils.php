<?php

/*
Version:     1.2
Date:        10/01/26
Name:        CardUtils.php
Purpose:     Card utility helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Cards;

use MTG\Core\Message;

class CardUtils
{
    public static function symbolReplace($str)
    {
        if ($str === null) :
            return null;
        endif;

        static $symbols = [
            '{E}'      => '<img src="images/e.png" alt="{E}" class="manaimg">',
            '{T}'      => '<img src="images/t.png" alt="{T}" class="manaimg">',
            '{Q}'      => '<img src="images/q.png" alt="{Q}" class="manaimg">',
            '{P}'      => '<img src="images/paw.png" alt="{Q}" class="manaimg" title="pawprint">',
            '{H}'      => 'Phyrexian mana ',
            '{W}'      => '<img src="images/w.png" alt="{W}" class="manaimg">',
            '{U}'      => '<img src="images/u.png" alt="{U}" class="manaimg">',
            '{B}'      => '<img src="images/b.png" alt="{B}" class="manaimg">',
            '{R}'      => '<img src="images/r.png" alt="{R}" class="manaimg">',
            '{G}'      => '<img src="images/g.png" alt="{G}" class="manaimg">',
            '{S}'      => '<img src="images/s.png" alt="{S}" class="manaimg">',
            '{C}'      => '<img src="images/colourless_mana.png" alt="{C}" class="manaimg">',
            '{HR}'     => '<img src="images/hr.png" alt="{HR}" class="manaimg">',
            '{+oo}'    => '<img src="images/inf.png" alt="{+oo}" class="manaimg">',
            '{100}'    => '<img src="images/100.png" alt="{100}" class="manaimg">',
            '{1000000}' => '<img src="images/1m.png" alt="{1000000}" class="manaimg">',
            '{WU}'     => '<img src="images/wu.png" alt="{WU}" class="manaimg">',
            '{W/U}'    => '<img src="images/wu.png" alt="{WU}" class="manaimg">',
            '{WB}'     => '<img src="images/wb.png" alt="{WB}" class="manaimg">',
            '{W/B}'    => '<img src="images/wb.png" alt="{WB}" class="manaimg">',
            '{UB}'     => '<img src="images/ub.png" alt="{UB}" class="manaimg">',
            '{U/B}'    => '<img src="images/ub.png" alt="{UB}" class="manaimg">',
            '{UR}'     => '<img src="images/ur.png" alt="{UR}" class="manaimg">',
            '{U/R}'    => '<img src="images/ur.png" alt="{UR}" class="manaimg">',
            '{BR}'     => '<img src="images/br.png" alt="{BR}" class="manaimg">',
            '{B/R}'    => '<img src="images/br.png" alt="{BR}" class="manaimg">',
            '{BG}'     => '<img src="images/bg.png" alt="{BG}" class="manaimg">',
            '{B/G}'    => '<img src="images/bg.png" alt="{BG}" class="manaimg">',
            '{RW}'     => '<img src="images/rw.png" alt="{RW}" class="manaimg">',
            '{R/W}'    => '<img src="images/rw.png" alt="{RW}" class="manaimg">',
            '{RG}'     => '<img src="images/rg.png" alt="{RG}" class="manaimg">',
            '{R/G}'    => '<img src="images/rg.png" alt="{RG}" class="manaimg">',
            '{GW}'     => '<img src="images/gw.png" alt="{GW}" class="manaimg">',
            '{G/W}'    => '<img src="images/gw.png" alt="{GW}" class="manaimg">',
            '{GU}'     => '<img src="images/gu.png" alt="{GU}" class="manaimg">',
            '{G/U}'    => '<img src="images/gu.png" alt="{GU}" class="manaimg">',
            '{C/W}'    => '<img src="images/cw.png" alt="{C/W}" class="manaimg">',
            '{C/U}'    => '<img src="images/cu.png" alt="{C/U}" class="manaimg">',
            '{C/B}'    => '<img src="images/cb.png" alt="{C/B}" class="manaimg">',
            '{C/R}'    => '<img src="images/cr.png" alt="{C/R}" class="manaimg">',
            '{C/G}'    => '<img src="images/cg.png" alt="{C/G}" class="manaimg">',
            '{2W}'     => '<img src="images/2w.png" alt="{2W}" class="manaimg">',
            '{2U}'     => '<img src="images/2u.png" alt="{2U}" class="manaimg">',
            '{2B}'     => '<img src="images/2b.png" alt="{2B}" class="manaimg">',
            '{2R}'     => '<img src="images/2r.png" alt="{2R}" class="manaimg">',
            '{2G}'     => '<img src="images/2g.png" alt="{2G}" class="manaimg">',
            '{2/W}'    => '<img src="images/2w.png" alt="{2/W}" class="manaimg">',
            '{2/B}'    => '<img src="images/2b.png" alt="{2/B}" class="manaimg">',
            '{2/G}'    => '<img src="images/2g.png" alt="{2/G}" class="manaimg">',
            '{2/U}'    => '<img src="images/2u.png" alt="{2/U}" class="manaimg">',
            '{2/R}'    => '<img src="images/2r.png" alt="{2/R}" class="manaimg">',
            '{X}'      => '<img src="images/x.png" alt="{X}" class="manaimg">',
            '{Y}'      => '<img src="images/y.png" alt="{Y}" class="manaimg">',
            '{Z}'      => '<img src="images/z.png" alt="{Z}" class="manaimg">',
            '{1/2}'    => '<img src="images/half.png" alt="{1/2}" class="manaimg">',
            '{0}'      => '<img src="images/0.png" alt="{0}" class="manaimg">',
            '{1}'      => '<img src="images/1.png" alt="{1}" class="manaimg">',
            '{2}'      => '<img src="images/2.png" alt="{2}" class="manaimg">',
            '{3}'      => '<img src="images/3.png" alt="{3}" class="manaimg">',
            '{4}'      => '<img src="images/4.png" alt="{4}" class="manaimg">',
            '{5}'      => '<img src="images/5.png" alt="{5}" class="manaimg">',
            '{6}'      => '<img src="images/6.png" alt="{6}" class="manaimg">',
            '{7}'      => '<img src="images/7.png" alt="{7}" class="manaimg">',
            '{8}'      => '<img src="images/8.png" alt="{8}" class="manaimg">',
            '{9}'      => '<img src="images/9.png" alt="{9}" class="manaimg">',
            '{10}'     => '<img src="images/10.png" alt="{10}" class="manaimg">',
            '{11}'     => '<img src="images/11.png" alt="{11}" class="manaimg">',
            '{12}'     => '<img src="images/12.png" alt="{12}" class="manaimg">',
            '{13}'     => '<img src="images/13.png" alt="{13}" class="manaimg">',
            '{14}'     => '<img src="images/14.png" alt="{14}" class="manaimg">',
            '{15}'     => '<img src="images/15.png" alt="{15}" class="manaimg">',
            '{16}'     => '<img src="images/16.png" alt="{16}" class="manaimg">',
            '{17}'     => '<img src="images/17.png" alt="{17}" class="manaimg">',
            '{18}'     => '<img src="images/18.png" alt="{18}" class="manaimg">',
            '{19}'     => '<img src="images/19.png" alt="{19}" class="manaimg">',
            '{20}'     => '<img src="images/20.png" alt="{20}" class="manaimg">',
            '{PW}'     => '<img src="images/pw.png" alt="{PW}" class="manaimg">',
            '{W/P}'    => '<img src="images/pw.png" alt="{W/P}" class="manaimg">',
            '{PU}'     => '<img src="images/pu.png" alt="{PU}" class="manaimg">',
            '{U/P}'    => '<img src="images/pu.png" alt="{U/P}" class="manaimg">',
            '{PB}'     => '<img src="images/pb.png" alt="{PB}" class="manaimg">',
            '{B/P}'    => '<img src="images/pb.png" alt="{B/P}" class="manaimg">',
            '{PR}'     => '<img src="images/pr.png" alt="{PR}" class="manaimg">',
            '{R/P}'    => '<img src="images/pr.png" alt="{R/P}" class="manaimg">',
            '{PG}'     => '<img src="images/pg.png" alt="{PG}" class="manaimg">',
            '{G/P}'    => '<img src="images/pg.png" alt="{G/P}" class="manaimg">',
            '{CHAOS}'  => '<img src="images/chaos.png" alt="{PG}" class="manaimg">',
            '{G/U/P}'  => '<img src="images/gup.png" alt="{G/U/P}" class="manaimg">',
            '{G/W/P}'  => '<img src="images/gwp.png" alt="{G/W/P}" class="manaimg">',
            '{R/G/P}'  => '<img src="images/rgp.png" alt="{R/G/P}" class="manaimg">',
            '{R/W/P}'  => '<img src="images/rwp.png" alt="{R/W/P}" class="manaimg">',
            '{PWk}'    => 'Planeswalk',
            '{Ch}'     => 'Chaos',
            "\n"       => '<br>',
            '?'        => '-',
            '£'        => '<br>',
            '#'        => '',
        ];

        return strtr($str, $symbols);
    }

    public static function cardTypes($finishes)
    {
        global $db, $logfile;
        $cardtypes = 'none';
        $card_normal = 0;
        $card_foil = 0;
        $card_etched = 0;
        foreach ($finishes as $key => $value) :
            if ($value == 'nonfoil') :
                $card_normal = 1;
            elseif ($value == 'foil') :
                $card_foil = 1;
            elseif ($value == 'etched') :
                $card_etched = 1;
            endif;
        endforeach;
        if ($card_normal == 1 and $card_foil == 1 and $card_etched == 1) :
            $cardtypes = 'normalfoiletched';
        elseif ($card_normal == 1 and $card_foil == 1 and $card_etched == 0) :
            $cardtypes = 'normalfoil';
        elseif ($card_normal == 1 and $card_foil == 0 and $card_etched == 1) :
            $cardtypes = 'normaletched';
        elseif ($card_normal == 0 and $card_foil == 1 and $card_etched == 1) :
            $cardtypes = 'foiletched';
        elseif ($card_normal == 0 and $card_foil == 0 and $card_etched == 1) :
            $cardtypes = 'etchedonly';
        elseif ($card_normal == 0 and $card_foil == 1 and $card_etched == 0) :
            $cardtypes = 'foilonly';
        elseif ($card_normal == 1 and $card_foil == 0 and $card_etched == 0) :
            $cardtypes = 'normalonly';
        endif;
        return $cardtypes;
    }

    public static function promoLookup($promo_type)
    {
        global $promos_to_show, $logfile;
        $msg = new Message($logfile);

        $msg->logMessage('[DEBUG]', "Looking up promo description for '$promo_type'");
        $index = array_search($promo_type, array_column($promos_to_show, 'promotype'));
        if ($index !== false) :
            $promo_description = $promos_to_show[$index]['display'];
        else :
            $promo_description = 'skip';
        endif;
        $msg->logMessage('[DEBUG]', "Promo description for '$promo_type' is '$promo_description'");
        return $promo_description;
    }
}
