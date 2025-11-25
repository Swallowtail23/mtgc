<?php

/*
Version:     4.0
Date:        25/11/25
Name:        colour.php
Purpose:     Return colour name for a colour code.
Notes:       {none}
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -

History:
    1.0         Initial version
    2.0         Moved to Message class from writelog
    3.0         Fixes for cards_scry database
    3.1 20/01/24 Move to logMessage
    3.2 25/11/25 Standard tidy-up
    4.0 25/11/25 Refactor to lookup-based mapping (no behaviour change)
*/

if (__FILE__ == $_SERVER['PHP_SELF']) :
    die('Direct access prohibited');
endif;

function colourfunction($colourcode)
{
    global $logfile;
    $msg = new Message($logfile);
    $msg->logMessage('[DEBUG]', "run with input: $colourcode");
    $decoded = json_decode($colourcode);
    $colourcode = '';
    if ($decoded !== null) :
        foreach ($decoded as $value) :
            $colourcode .= $value;
        endforeach;
    endif;

    // Normalize split cards (e.g., "B // W") to "BW"
    $colourcode = str_replace(' ', '', str_replace('//', '', $colourcode));

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
    $colour = '';

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

    return $colour;
}
