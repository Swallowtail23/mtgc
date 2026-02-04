<?php

/*
Version:     2.7
Date:        04/02/26
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
    public static function symbolReplaceFont(?string $str, ?Message $msg = null): ?string
    {
        if ($msg !== null) :
            $msg->logMessage('[DEBUG]', 'symbolReplaceFont called');
        endif;

        if ($str === null) :
            if ($msg !== null) :
                $msg->logMessage('[DEBUG]', 'symbolReplaceFont received null input');
            endif;
            return null;
        endif;

        static $symbols = [
            '{E}'      => '<i class="ms ms-e ms-cost" aria-label="{E}" role="img"></i>',
            '{T}'      => '<i class="ms ms-tap" aria-label="{T}" role="img"></i>',
            '{Q}'      => '<i class="ms ms-untap" aria-label="{Q}" role="img"></i>',
            '{P}'      => '<i class="ms ms-paw ms-cost" aria-label="{P}" role="img" title="pawprint"></i>',
            '{H}'      => '<i class="ms ms-h ms-cost" aria-label="{H}" role="img"></i>',
            '{W}'      => '<i class="ms ms-w ms-cost" aria-label="{W}" role="img"></i>',
            '{U}'      => '<i class="ms ms-u ms-cost" aria-label="{U}" role="img"></i>',
            '{B}'      => '<i class="ms ms-b ms-cost" aria-label="{B}" role="img"></i>',
            '{R}'      => '<i class="ms ms-r ms-cost" aria-label="{R}" role="img"></i>',
            '{G}'      => '<i class="ms ms-g ms-cost" aria-label="{G}" role="img"></i>',
            '{S}'      => '<i class="ms ms-s ms-cost" aria-label="{S}" role="img"></i>',
            '{C}'      => '<i class="ms ms-c ms-cost" aria-label="{C}" role="img"></i>',
            '{HR}'     => '<i class="ms ms-r ms-half ms-cost" aria-label="{HR}" role="img"></i>',
            '{∞}'    => '<i class="ms ms-infinity ms-cost" aria-label="{+oo}" role="img"></i>',
            '{100}'    => '<i class="ms ms-100 ms-cost" aria-label="{100}" role="img"></i>',
            '{1000000}' => '<i class="ms ms-1000000 ms-cost" aria-label="{1000000}" role="img"></i>',
            '{WU}'     => '<i class="ms ms-wu ms-cost" aria-label="{WU}" role="img"></i>',
            '{W/U}'    => '<i class="ms ms-wu ms-cost" aria-label="{W/U}" role="img"></i>',
            '{WB}'     => '<i class="ms ms-wb ms-cost" aria-label="{WB}" role="img"></i>',
            '{W/B}'    => '<i class="ms ms-wb ms-cost" aria-label="{W/B}" role="img"></i>',
            '{UB}'     => '<i class="ms ms-ub ms-cost" aria-label="{UB}" role="img"></i>',
            '{U/B}'    => '<i class="ms ms-ub ms-cost" aria-label="{U/B}" role="img"></i>',
            '{UR}'     => '<i class="ms ms-ur ms-cost" aria-label="{UR}" role="img"></i>',
            '{U/R}'    => '<i class="ms ms-ur ms-cost" aria-label="{U/R}" role="img"></i>',
            '{BR}'     => '<i class="ms ms-br ms-cost" aria-label="{BR}" role="img"></i>',
            '{B/R}'    => '<i class="ms ms-br ms-cost" aria-label="{B/R}" role="img"></i>',
            '{BG}'     => '<i class="ms ms-bg ms-cost" aria-label="{BG}" role="img"></i>',
            '{B/G}'    => '<i class="ms ms-bg ms-cost" aria-label="{B/G}" role="img"></i>',
            '{RW}'     => '<i class="ms ms-rw ms-cost" aria-label="{RW}" role="img"></i>',
            '{R/W}'    => '<i class="ms ms-rw ms-cost" aria-label="{R/W}" role="img"></i>',
            '{RG}'     => '<i class="ms ms-rg ms-cost" aria-label="{RG}" role="img"></i>',
            '{R/G}'    => '<i class="ms ms-rg ms-cost" aria-label="{R/G}" role="img"></i>',
            '{GW}'     => '<i class="ms ms-gw ms-cost" aria-label="{GW}" role="img"></i>',
            '{G/W}'    => '<i class="ms ms-gw ms-cost" aria-label="{G/W}" role="img"></i>',
            '{GU}'     => '<i class="ms ms-gu ms-cost" aria-label="{GU}" role="img"></i>',
            '{G/U}'    => '<i class="ms ms-gu ms-cost" aria-label="{G/U}" role="img"></i>',
            '{C/W}'    => '<i class="ms ms-cw ms-cost" aria-label="{C/W}" role="img"></i>',
            '{C/U}'    => '<i class="ms ms-cu ms-cost" aria-label="{C/U}" role="img"></i>',
            '{C/B}'    => '<i class="ms ms-cb ms-cost" aria-label="{C/B}" role="img"></i>',
            '{C/R}'    => '<i class="ms ms-cr ms-cost" aria-label="{C/R}" role="img"></i>',
            '{C/G}'    => '<i class="ms ms-cg ms-cost" aria-label="{C/G}" role="img"></i>',
            '{2W}'     => '<i class="ms ms-2w ms-cost" aria-label="{2W}" role="img"></i>',
            '{2U}'     => '<i class="ms ms-2u ms-cost" aria-label="{2U}" role="img"></i>',
            '{2B}'     => '<i class="ms ms-2b ms-cost" aria-label="{2B}" role="img"></i>',
            '{2R}'     => '<i class="ms ms-2r ms-cost" aria-label="{2R}" role="img"></i>',
            '{2G}'     => '<i class="ms ms-2g ms-cost" aria-label="{2G}" role="img"></i>',
            '{2/W}'    => '<i class="ms ms-2w ms-cost" aria-label="{2/W}" role="img"></i>',
            '{2/B}'    => '<i class="ms ms-2b ms-cost" aria-label="{2/B}" role="img"></i>',
            '{2/G}'    => '<i class="ms ms-2g ms-cost" aria-label="{2/G}" role="img"></i>',
            '{2/U}'    => '<i class="ms ms-2u ms-cost" aria-label="{2/U}" role="img"></i>',
            '{2/R}'    => '<i class="ms ms-2r ms-cost" aria-label="{2/R}" role="img"></i>',
            '{X}'      => '<i class="ms ms-x ms-cost" aria-label="{X}" role="img"></i>',
            '{Y}'      => '<i class="ms ms-y ms-cost" aria-label="{Y}" role="img"></i>',
            '{Z}'      => '<i class="ms ms-z ms-cost" aria-label="{Z}" role="img"></i>',
            '{1/2}'    => '<i class="ms ms-1-2 ms-cost" aria-label="{1/2}" role="img"></i>',
            '{0}'      => '<i class="ms ms-0 ms-cost" aria-label="{0}" role="img"></i>',
            '{1}'      => '<i class="ms ms-1 ms-cost" aria-label="{1}" role="img"></i>',
            '{2}'      => '<i class="ms ms-2 ms-cost" aria-label="{2}" role="img"></i>',
            '{3}'      => '<i class="ms ms-3 ms-cost" aria-label="{3}" role="img"></i>',
            '{4}'      => '<i class="ms ms-4 ms-cost" aria-label="{4}" role="img"></i>',
            '{5}'      => '<i class="ms ms-5 ms-cost" aria-label="{5}" role="img"></i>',
            '{6}'      => '<i class="ms ms-6 ms-cost" aria-label="{6}" role="img"></i>',
            '{7}'      => '<i class="ms ms-7 ms-cost" aria-label="{7}" role="img"></i>',
            '{8}'      => '<i class="ms ms-8 ms-cost" aria-label="{8}" role="img"></i>',
            '{9}'      => '<i class="ms ms-9 ms-cost" aria-label="{9}" role="img"></i>',
            '{10}'     => '<i class="ms ms-10 ms-cost" aria-label="{10}" role="img"></i>',
            '{11}'     => '<i class="ms ms-11 ms-cost" aria-label="{11}" role="img"></i>',
            '{12}'     => '<i class="ms ms-12 ms-cost" aria-label="{12}" role="img"></i>',
            '{13}'     => '<i class="ms ms-13 ms-cost" aria-label="{13}" role="img"></i>',
            '{14}'     => '<i class="ms ms-14 ms-cost" aria-label="{14}" role="img"></i>',
            '{15}'     => '<i class="ms ms-15 ms-cost" aria-label="{15}" role="img"></i>',
            '{16}'     => '<i class="ms ms-16 ms-cost" aria-label="{16}" role="img"></i>',
            '{17}'     => '<i class="ms ms-17 ms-cost" aria-label="{17}" role="img"></i>',
            '{18}'     => '<i class="ms ms-18 ms-cost" aria-label="{18}" role="img"></i>',
            '{19}'     => '<i class="ms ms-19 ms-cost" aria-label="{19}" role="img"></i>',
            '{20}'     => '<i class="ms ms-20 ms-cost" aria-label="{20}" role="img"></i>',
            '{PW}'     => '<i class="ms ms-wp ms-cost" aria-label="{PW}" role="img"></i>',
            '{W/P}'    => '<i class="ms ms-wp ms-cost" aria-label="{W/P}" role="img"></i>',
            '{PU}'     => '<i class="ms ms-up ms-cost" aria-label="{PU}" role="img"></i>',
            '{U/P}'    => '<i class="ms ms-up ms-cost" aria-label="{U/P}" role="img"></i>',
            '{PB}'     => '<i class="ms ms-bp ms-cost" aria-label="{PB}" role="img"></i>',
            '{B/P}'    => '<i class="ms ms-bp ms-cost" aria-label="{B/P}" role="img"></i>',
            '{PR}'     => '<i class="ms ms-rp ms-cost" aria-label="{PR}" role="img"></i>',
            '{R/P}'    => '<i class="ms ms-rp ms-cost" aria-label="{R/P}" role="img"></i>',
            '{PG}'     => '<i class="ms ms-gp ms-cost" aria-label="{PG}" role="img"></i>',
            '{G/P}'    => '<i class="ms ms-gp ms-cost" aria-label="{G/P}" role="img"></i>',
            '{CHAOS}'  => '<i class="ms ms-chaos" aria-label="{CHAOS}" role="img"></i>',
            '{G/U/P}'  => '<i class="ms ms-gup ms-cost" aria-label="{G/U/P}" role="img"></i>',
            '{G/W/P}'  => '<i class="ms ms-gwp ms-cost" aria-label="{G/W/P}" role="img"></i>',
            '{R/G/P}'  => '<i class="ms ms-rgp ms-cost" aria-label="{R/G/P}" role="img"></i>',
            '{R/W/P}'  => '<i class="ms ms-rwp ms-cost" aria-label="{R/W/P}" role="img"></i>',
            '{PWk}'    => '<i class="ms ms-planeswalker ms-fw" aria-label="{PWk}" role="img"></i>',
            '{Ch}'     => '<i class="ms ms-chaos" aria-label="{Ch}" role="img"></i>',
            "\n"       => '<br>',
            '?'        => '-',
            '£'        => '<br>',
            '#'        => '',
        ];

        $output = strtr($str, $symbols);

        if ($msg !== null) :
            $msg->logMessage('[DEBUG]', 'symbolReplaceFont output generated');
        endif;

        return $output;
    }

    public static function cardTypes(array $finishes): string
    {
        $cardtypes = 'none';
        $card_normal = 0;
        $card_foil = 0;
        $card_etched = 0;
        foreach ($finishes as $value) :
            if ($value === 'nonfoil') :
                $card_normal = 1;
            elseif ($value === 'foil') :
                $card_foil = 1;
            elseif ($value === 'etched') :
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

    public static function colourIdentity($colourIdentity, ?Message $msg = null): string
    {
        if ($msg !== null) :
            $msg->logMessage('[DEBUG]', 'colourIdentity called');
        endif;

        if ($colourIdentity === null) :
            if ($msg !== null) :
                $msg->logMessage('[DEBUG]', 'colourIdentity received null input');
            endif;
            return '';
        endif;

        $decoded = json_decode($colourIdentity);
        $colours = [];
        if (is_array($decoded)) :
            foreach ($decoded as $value) :
                if (is_string($value)) :
                    $colours[] = $value;
                endif;
            endforeach;
        elseif (is_string($decoded)) :
            $colours[] = $decoded;
        else :
            if (is_string($colourIdentity)) :
                $colours[] = $colourIdentity;
            endif;
        endif;

        $raw = implode('', $colours);
        if ($msg !== null) :
            $msg->logMessage('[DEBUG]', "colourIdentity raw: $raw");
        endif;

        $matches = [];
        preg_match_all('/[WUBRG]/', strtoupper($raw), $matches);
        $unique = array_values(array_unique($matches[0] ?? []));

        if ($unique === []) :
            if ($msg !== null) :
                $msg->logMessage('[DEBUG]', 'colourIdentity no colours found');
            endif;
            return '<i class="ms ms-c" aria-label="C" role="img"></i>';
        endif;

        $order = ['W' => 0, 'U' => 1, 'B' => 2, 'R' => 3, 'G' => 4];
        usort($unique, static function (string $a, string $b) use ($order): int {
            return $order[$a] <=> $order[$b];
        });

        $count = count($unique);
        $key = strtolower(implode('', $unique));
        $class = $key;

        if ($count === 2) :
            $pairMap = [
                'wu' => 'wu',
                'ub' => 'ub',
                'br' => 'br',
                'rg' => 'rg',
                'wg' => 'gw',
                'wb' => 'wb',
                'bg' => 'bg',
                'ug' => 'gu',
                'ur' => 'ur',
                'rw' => 'rw',
            ];
            $class = $pairMap[$key] ?? $key;
        elseif ($count === 3) :
            $triMap = [
                'wug' => 'wug',
                'wub' => 'wub',
                'ubr' => 'ubr',
                'brg' => 'brg',
                'wrg' => 'rgw',
                'wbg' => 'wbg',
                'wur' => 'urw',
                'ubg' => 'bgu',
                'wbr' => 'brw',
                'urg' => 'gur',
            ];
            $class = $triMap[$key] ?? $key;
        endif;

        $label = strtoupper($class);
        $output = '<i class="ms ms-ci ms-ci-' . $count . ' ms-ci-' . $class
            . '" aria-label="' . $label . '" role="img"></i>';

        if ($msg !== null) :
            $msg->logMessage('[DEBUG]', "colourIdentity output: $output");
        endif;

        return $output;
    }

    public static function colourFunction($colourcode, ?Message $msg = null): string
    {
        $originalColourcode = $colourcode;
        if ($msg !== null) :
            $msg->logMessage('[DEBUG]', "run with input: $colourcode");
        endif;
        if ($colourcode === null) :
            $decoded = null;
        else :
            $decoded = json_decode($colourcode);
        endif;
        $colourcode = '';
        if ($decoded !== null) :
            if (is_array($decoded)) :
                foreach ($decoded as $value) :
                    $colourcode .= $value;
                endforeach;
            else :
                $colourcode = (string) $decoded;
            endif;
        else :
            $colourcode = (string) $originalColourcode;
        endif;

        // Normalize split cards (e.g., "B // W") to "BW"
        $colourcode = str_replace(' ', '', str_replace('//', '', $colourcode));
        if ($msg !== null) :
            $msg->logMessage('[DEBUG]', "Checking card, colour identity $colourcode");
        endif;

        $singles = array(
            'B' => 'black',
            'U' => 'blue',
            'G' => 'green',
            'R' => 'red',
            'W' => 'white',
            'A' => 'artifact',
            'L' => 'land',
            'C' => 'colourless'
        );

        $pairs = array(
            'GL' => 'dryad',
            'AU' => 'blueartifact',
            'UA' => 'blueartifact',
            'AR' => 'redartifact',
            'RA' => 'redartifact',
            'AG' => 'greenartifact',
            'GA' => 'greenartifact',
            'AW' => 'whiteartifact',
            'WA' => 'whiteartifact',
            'AB' => 'blackartifact',
            'BA' => 'blackartifact',
            'AL' => 'landartifact',
            'LA' => 'landartifact',
            'WB' => 'orzhov',
            'BW' => 'orzhov',
            'GW' => 'selesnya',
            'WG' => 'selesnya',
            'RG' => 'gruul',
            'GR' => 'gruul',
            'RB' => 'rakdos',
            'BR' => 'rakdos',
            'GB' => 'golgari',
            'BG' => 'golgari',
            'RW' => 'boros',
            'WR' => 'boros',
            'UW' => 'azorius',
            'WU' => 'azorius',
            'UB' => 'dimir',
            'BU' => 'dimir',
            'UR' => 'izzet',
            'RU' => 'izzet',
            'UG' => 'simic',
            'GU' => 'simic'
        );

        $trios = array(
            'WUB' => 'esper',
            'BUW' => 'esper',
            'UWB' => 'esper',
            'UBW' => 'esper',
            'WBU' => 'esper',
            'BWU' => 'esper',
            'WUG' => 'bant',
            'GUW' => 'bant',
            'UWG' => 'bant',
            'UGW' => 'bant',
            'WGU' => 'bant',
            'GWU' => 'bant',
            'RUB' => 'grixis',
            'RBU' => 'grixis',
            'URB' => 'grixis',
            'UBR' => 'grixis',
            'BRU' => 'grixis',
            'BUR' => 'grixis',
            'RGW' => 'naya',
            'RWG' => 'naya',
            'WGR' => 'naya',
            'WRG' => 'naya',
            'GRW' => 'naya',
            'GWR' => 'naya',
            'BGR' => 'jund',
            'BRG' => 'jund',
            'RGB' => 'jund',
            'RBG' => 'jund',
            'GBR' => 'jund',
            'GRB' => 'jund',
            'BGW' => 'abzan',
            'BWG' => 'abzan',
            'WGB' => 'abzan',
            'WBG' => 'abzan',
            'GBW' => 'abzan',
            'GWB' => 'abzan',
            'UGR' => 'temur',
            'URG' => 'temur',
            'RGU' => 'temur',
            'RUG' => 'temur',
            'GUR' => 'temur',
            'GRU' => 'temur',
            'RWU' => 'jeskai',
            'RUW' => 'jeskai',
            'WUR' => 'jeskai',
            'WRU' => 'jeskai',
            'URW' => 'jeskai',
            'UWR' => 'jeskai',
            'WRB' => 'mardu',
            'WBR' => 'mardu',
            'BRW' => 'mardu',
            'BWR' => 'mardu',
            'RBW' => 'mardu',
            'RWB' => 'mardu',
            'BGU' => 'sultai',
            'BUG' => 'sultai',
            'UGB' => 'sultai',
            'UBG' => 'sultai',
            'GBU' => 'sultai',
            'GUB' => 'sultai',
            'AUR' => 'blueredartifact',
            'ARU' => 'blueredartifact',
            'RAU' => 'blueredartifact',
            'RUA' => 'blueredartifact',
            'UAR' => 'blueredartifact',
            'URA' => 'blueredartifact',
            'AWU' => 'bluewhiteartifact',
            'AUW' => 'bluewhiteartifact',
            'WUA' => 'bluewhiteartifact',
            'WAU' => 'bluewhiteartifact',
            'UAW' => 'bluewhiteartifact',
            'UWA' => 'bluewhiteartifact'
        );

        $fourSets = array(
            'BGRU' => 'glint',
            'BGRW' => 'dune',
            'WRGU' => 'ink',
            'BWGU' => 'witch',
            'BRWU' => 'yore'
        );

        $splitPairs = array(
            'B//B' => 'black',
            'U//U' => 'blue',
            'G//G' => 'green',
            'R//R' => 'red',
            'W//W' => 'white',
            'B//W' => 'orzhov',
            'W//B' => 'orzhov',
            'G//W' => 'selesnya',
            'W//G' => 'selesnya',
            'R//G' => 'gruul',
            'G//R' => 'gruul',
            'B//R' => 'rakdos',
            'R//B' => 'rakdos',
            'B//G' => 'golgari',
            'G//B' => 'golgari',
            'W//R' => 'boros',
            'R//W' => 'boros',
            'W//U' => 'azorius',
            'U//W' => 'azorius',
            'B//U' => 'dimir',
            'U//B' => 'dimir',
            'R//U' => 'izzet',
            'U//R' => 'izzet',
            'G//U' => 'simic',
            'U//G' => 'simic',
            'WU//UB' => 'esper',
            'GW//WU' => 'bant',
            'GU//WU' => 'bant',
            'UB//RB' => 'grixis',
            'GR//GW' => 'naya',
            'GB//GR' => 'jund',
            'GR//GB' => 'jund',
            'RB//GR' => 'jund'
        );

        $length = strlen($colourcode);
        $colour = 'other';

        if ($length === 1 && isset($singles[$colourcode])) :
            $colour = $singles[$colourcode];
        elseif ($length === 2 && isset($pairs[$colourcode])) :
            $colour = $pairs[$colourcode];
        elseif ($length === 3 && isset($trios[$colourcode])) :
            $colour = $trios[$colourcode];
        elseif ($length === 4) :
            $sorted = implode('', array_unique(str_split($colourcode)));
            if (isset($fourSets[$sorted])) :
                $colour = $fourSets[$sorted];
            endif;
        elseif ($length === 5 && count(array_unique(str_split($colourcode))) === 5) :
            $colour = 'five';
        elseif ($length === 6 && strpos($colourcode, 'A') !== false) :
            $colour = 'artifactfive';
        elseif ($length === 6 && isset($splitPairs[$colourcode])) :
            $colour = $splitPairs[$colourcode];
        elseif ($length === 8 && isset($splitPairs[$colourcode])) :
            $colour = $splitPairs[$colourcode];
        endif;

        if ($msg !== null) :
            $msg->logMessage('[DEBUG]', "Returning colour: $colour");
        endif;

        return $colour;
    }

    public static function promoLookup(string $promo_type, array $promosToShow, ?Message $msg = null): string
    {
        if ($msg !== null) :
            $msg->logMessage('[DEBUG]', "Looking up promo description for '$promo_type'");
        endif;

        $index = array_search($promo_type, array_column($promosToShow, 'promotype'), true);
        if ($index !== false) :
            $promo_description = $promosToShow[$index]['display'];
        else :
            $promo_description = 'skip';
        endif;

        if ($msg !== null) :
            $msg->logMessage('[DEBUG]', "Promo description for '$promo_type' is '$promo_description'");
        endif;

        return $promo_description;
    }

    public static function escapeCardNotesForTextarea($notes): string
    {
        return htmlspecialchars((string) $notes, ENT_QUOTES, 'UTF-8');
    }
}
