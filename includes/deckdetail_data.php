<?php

/*
Version:     2.0
Date:        01/01/26
Name:        deckdetail_data.php
Purpose:     Deck detail data calculations for fragments and page rendering.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

// Get deck details from database
if (
    $deckinfoqry = $db->execute_query(
        "SELECT deckname,notes,sidenotes,type,deck_updated_at,
                (UNIX_TIMESTAMP(deck_updated_at) * 1000000 + MICROSECOND(deck_updated_at)) AS deck_version
            FROM decks WHERE decknumber = ? LIMIT 1",
        [$deckNumber]
    )
) :
    $deckinfo = $deckinfoqry->fetch_assoc();
    $deckName   = $deckinfo['deckname'];
    $notes      = $deckinfo['notes'];
    $sidenotes  = $deckinfo['sidenotes'];
    $decktype   = $deckinfo['type'];
    $deck_updated_at = $deckinfo['deck_updated_at'] ?? null;
    $deck_version = isset($deckinfo['deck_version']) ? (int) $deckinfo['deck_version'] : null;
else :
    throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error);
endif;

// Get relevant db_field with legality
if ($decktype != '') :
    $db_field = cardLegalDBField($decktype);
else :
    $db_field = '';
endif;
$msg->logMessage('[DEBUG]', "Legality db-field for this deck is '$db_field'");

// Get deck legalities
if ($db_field != '') :
    $deck_legality_list = deckLegalList($deckNumber, $decktype, $db_field);
else :
    $deck_legality_list = '';
endif;

//Get card list
$mainquery = ("SELECT *,cards_scry.id AS cardsid
                        FROM deckcards
                    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id
                    LEFT JOIN $mytable ON cards_scry.id = $mytable.id
                    WHERE decknumber = ? AND cardqty > 0 ORDER BY name");
$msg->logMessage('[DEBUG]', "$mainquery");
$result = $db->execute_query($mainquery, [$deckNumber]);
if ($result != true) :
    throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error);
endif;

$sidequery = ("SELECT *,cards_scry.id AS cardsid
                        FROM deckcards
                    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id
                    LEFT JOIN $mytable ON cards_scry.id = $mytable.id
                    WHERE decknumber = ? AND sideqty > 0 ORDER BY name");
$sideresult = $db->execute_query($sidequery, [$deckNumber]);
if ($sideresult != true) :
    throw new Exception("[ERROR] deckdetail.php: " . __LINE__ . ": SQL failure: Error: " . $db->error);
endif;

// Totals used by derived fragments when decklist rendering is skipped
$total_cards = 0;
$side_total_cards = 0;
$totalQuery = "SELECT SUM(IFNULL(cardqty, 0)) AS totalqty,
        SUM(IFNULL(sideqty, 0)) AS sidetotal
    FROM deckcards
    WHERE decknumber = ? LIMIT 1";
$totalResult = $db->execute_query($totalQuery, [$deckNumber]);
if ($totalResult !== false && $totalResult->num_rows > 0) :
    $totalRow = $totalResult->fetch_assoc();
    $total_cards = (int) ($totalRow['totalqty'] ?? 0);
    $side_total_cards = (int) ($totalRow['sidetotal'] ?? 0);
endif;

//Initialise variables to 0
$cdr = $creatures = $instantsorcery = $other = $lands = $deckvalue = $planes = $side = 0;
$cmctotal = 0;
$deck_colour_mismatch = $illegal_cards = '';

//Illegal card style tags
$red_font_tag = "style='color: OrangeRed; font-weight: bold'";
$firebrick_font_tag = "style='color: FireBrick; font-weight: bold'";

//This section works out which cards the user DOES NOT have, for later linking
// in a text file to download
$resultnames = [];
$rowNumber = 0;
while ($row = $result->fetch_assoc()) :
    $rowNumber++;
    $qty = $row['cardqty'];

    $found = false;
    foreach ($resultnames as &$entry) :
        if ($entry['name'] === $row['name']) :
            if (isset($entry['qty'])) :
                $entry['qty'] += $qty;
            else :
                $entry['qty'] = $qty;
            endif;
            $found = true;
            break;
        endif;
    endforeach;
    unset($entry); // break the reference with the last element

    if (!$found) :
        $resultnames[$rowNumber] = ['name' => $row['name'], 'flavor_name' => $row['flavor_name'], 'qty' => $qty];
    endif;
endwhile;

while ($row = $sideresult->fetch_assoc()) :
    $qty = $row['sideqty'];

    $found = false;
    foreach ($resultnames as &$entry) :
        if ($entry['name'] === $row['name']) :
            if (isset($entry['qty'])) :
                $entry['qty'] += $qty;
            else :
                $entry['qty'] = $qty;
            endif;
            $found = true;
            break;
        endif;
    endforeach;
    unset($entry); // break the reference with the last element

    if (!$found) :
        $resultnames[] = ['name' => $row['name'], 'flavor_name' => $row['flavor_name'], 'qty' => $qty];
    endif;
endwhile;
$uniquecardscount = count($resultnames);
$msg->logMessage('[DEBUG]', "Cards in deck: $uniquecardscount");
$msg->logMessage('[DEBUG]', "Cards in deck: " . print_r($resultnames, true));
$resultNameTotals = [];
$totalsQuery = "SELECT cards_scry.name,
        SUM(IFNULL(deckcards.cardqty, 0) + IFNULL(deckcards.sideqty, 0)) AS totalqty
    FROM deckcards
    LEFT JOIN cards_scry ON deckcards.cardnumber = cards_scry.id
    WHERE deckcards.decknumber = ?
    GROUP BY cards_scry.name";
$totalsResult = $db->execute_query($totalsQuery, [$deckNumber]);
if ($totalsResult === false) :
    $msg->logMessage('[DEBUG]', "Total copy query failed: " . $db->error);
else :
    while ($totalsRow = $totalsResult->fetch_assoc()) :
        if (isset($totalsRow['name'])) :
            $resultNameTotals[$totalsRow['name']] = (int) ($totalsRow['totalqty'] ?? 0);
        endif;
    endwhile;
    $msg->logMessage('[DEBUG]', "Copy totals: " . print_r($resultNameTotals, true));
endif;
$requiredlist = '';
$requiredbuy = '';
if ($uniquecardscount > 0) :
        $shortqty = array_fill(0, $uniquecardscount, '0'); //create an array the right size, all '0'
        $placeholderCount = count($resultnames) * 2; // 2 placeholders per card in the result list
        // Extract names from the subarrays
        $names = array_map(function ($entry) {
                return $entry['name'];
        }, $resultnames);
        $msg->logMessage('[DEBUG]', "Missing check on " . count($resultnames) . " cards");
        $placeholders = implode(',', array_fill(0, count($resultnames), '?'));
        // create placeholders for prepared statement

        $msg->logMessage('[DEBUG]', "Missing check on cards: " . implode(', ', $names));

        // Duplicate the $resultnames array to match the number of placeholders
        $params = array_merge($names, $names);

        $query = "
            SELECT name, flavor_name,
                   SUM(IFNULL(`$mytable`.etched, 0))
                       + SUM(IFNULL(`$mytable`.foil, 0))
                       + SUM(IFNULL(`$mytable`.normal, 0)) AS allcopies
            FROM $mytable
            LEFT JOIN cards_scry
            ON $mytable.id = cards_scry.id
            WHERE
                cards_scry.name IN ($placeholders) OR
                cards_scry.flavor_name IN ($placeholders)
            GROUP BY name
        ";

    if ($totalresult = $db->execute_query($query, $params)) :
        // $totalresult will be an array of qties of cards in collection
        $cardCopies = [];
        $rowNumber = 0;

        while ($totalrow = $totalresult->fetch_assoc()) :
            $rowNumber++;
            $msg->logMessage('[DEBUG]', print_r($totalrow['name'], true));

            if (!isset($cardCopies[$rowNumber])) :
                $cardCopies[$rowNumber] = [];
            endif;

            if (isset($totalrow['name']) && !empty($totalrow['name'])) :
                $cardCopies[$rowNumber]['name'] = $totalrow['name'];
            endif;
            if (isset($totalrow['flavor_name']) && !empty($totalrow['flavor_name'])) :
                $cardCopies[$rowNumber]['flavor_name'] = $totalrow['flavor_name'];
            endif;
            if (isset($totalrow['allcopies']) && !empty($totalrow['allcopies'])) :
                $cardCopies[$rowNumber]['qty'] = $totalrow['allcopies'];
            else :
                    $cardCopies[$rowNumber]['qty'] = 0;
            endif;
        endwhile;
        $msg->logMessage('[DEBUG]', print_r($cardCopies, true));

        foreach ($resultnames as $resultEntry) :
            $found = false;
            foreach ($cardCopies as &$cardEntry) :
                if ($resultEntry['name'] === $cardEntry['name']) : // We have some of this card name
                    if ($resultEntry['qty'] > $cardEntry['qty']) : // but not enough
                        $shortqty = $resultEntry['qty'] - $cardEntry['qty'];
                        $requiredlist .= $resultEntry['name'] . " x " . $shortqty . "\r\n";
                        $requiredbuy .= $resultEntry['name'] . " " . $shortqty . "||";
                    endif;
                    $found = true;
                    break;
                endif;
            endforeach;
            unset($cardEntry); // Break the reference with the last element
            if ($found === false) :
                $requiredlist .= $resultEntry['name'] . " x " . $resultEntry['qty'] . "\r\n";
                $requiredbuy .= $resultEntry['name'] . " " . $resultEntry['qty'] . "||";
            endif;
        endforeach;

        $msg->logMessage('[DEBUG]', "Cards required list: $requiredlist");
        $msg->logMessage('[DEBUG]', "Cards required buy: $requiredbuy");
    else :
            $msg->logMessage('[ERROR]', "Database query failed");
    endif;
endif;

//This section builds hidden divs for each card with the image and a link,
// and increments type and value counters
// for main and side
// It also builds the legal Colour identity for Commander decks
mysqli_data_seek($result, 0);
$cdrSet = false;
$cdr_colours = array();
$w = 0;
$u = 0;
$b = 0;
$r = 0;
$g = 0;
$c = 0;
$gw = 0;
$gu = 0;
$gb = 0;
$gr = 0;
$gg = 0;
$gc = 0;
$i = 0;
while ($row = $result->fetch_assoc()) :
    $baseCardName = $row['name'];
    if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
        $row['name'] = $row['flavor_name'];
    endif;
    if ($row['commander'] != 0 and $row['commander'] != null) :
        $msg->logMessage('[DEBUG]', "Checking card, colour identity {$row['color_identity']}");
        //card is a commander, get its colour identity
        $cdrSet = true;
        $cdr_colours[$i] = $row['color_identity'];
        $i = $i + 1;
    endif;
    $cardset = strtolower($row['setcode']);
    $msg->logMessage('[DEBUG]', "Checking manacost for colour quantities");
    if (
        isset($row['manacost'])
        && is_string($row['manacost'])
        && isset($row['cardqty'])
        && $row['cardqty'] !== null
    ) :
        $w = $w + (substr_count($row['manacost'], "W") * $row['cardqty']);
        $u = $u + (substr_count($row['manacost'], "U") * $row['cardqty']);
        $b = $b + (substr_count($row['manacost'], "B") * $row['cardqty']);
        $r = $r + (substr_count($row['manacost'], "R") * $row['cardqty']);
        $g = $g + (substr_count($row['manacost'], "G") * $row['cardqty']);
        $c = $c + (substr_count($row['manacost'], "C") * $row['cardqty']);
    else :
        $msg->logMessage('[DEBUG]', "Manacost not a string");
    endif;
    $msg->logMessage('[DEBUG]', "Checking for generated mana");
    if (
        isset($row['generatedmana'])
        && is_string($row['generatedmana'])
        && isset($row['cardqty'])
        && $row['cardqty'] !== null
    ) :
        $msg->logMessage('[DEBUG]', "Generated mana ({$row['name']}) is {$row['generatedmana']}");
        $gw = $gw + (substr_count($row['generatedmana'], "W") * $row['cardqty']);
        $gu = $gu + (substr_count($row['generatedmana'], "U") * $row['cardqty']);
        $gb = $gb + (substr_count($row['generatedmana'], "B") * $row['cardqty']);
        $gr = $gr + (substr_count($row['generatedmana'], "R") * $row['cardqty']);
        $gg = $gg + (substr_count($row['generatedmana'], "G") * $row['cardqty']);
        $gc = $gc + (substr_count($row['generatedmana'], "C") * $row['cardqty']);
    else :
        $msg->logMessage('[DEBUG]', "Generated mana not a string");
    endif;
    $cardcmc = null;
    // For SLD cards and REX cards with empty "Type", use the f1 definition instead
    if ($row['type'] !== null) :
        $card_type = $row['type'];
        $cardcmc = $row['cmc'];
    elseif ($row['type'] === null and isset($row['f1_type'])) :
        $card_type = $row['f1_type'];
        $cardcmc = $row['f1_cmc'];
    endif;

    if ($cardcmc !== null && $row['cardqty'] !== null) :
        $cmctotal = $cmctotal + ($cardcmc * $row['cardqty']);
    endif;

    if (strpos($card_type, ' //') !== false) :
        $len = strpos($card_type, ' //');
        $card_type = substr($card_type, 0, $len);
    endif;
    $isPlanePhenomenon = (
        (preg_match('/\\bPlane\\b/i', $card_type) === 1 && stripos($card_type, 'Planeswalker') === false)
        || preg_match('/\\bPhenomenon\\b/i', $card_type) === 1
    );

    $isTokenLike = (
        (strpos($card_type, 'Token') !== false)
        || (strpos($card_type, 'Emblem') !== false)
    );

    if ((strpos($card_type, 'Creature') !== false) and ($row['commander'] == 0) and !$isTokenLike) :
        $creatures = $creatures + $row['cardqty'];
    elseif (
        ((strpos($card_type, 'Sorcery') !== false) or (strpos($card_type, 'Instant') !== false))
        and !$isTokenLike
    ) :
        $instantsorcery = $instantsorcery + $row['cardqty'];
    elseif (
        (strpos($card_type, 'Sorcery') === false)
        and (strpos($card_type, 'Instant') === false)
        and (strpos($card_type, 'Creature') === false)
        and (strpos($card_type, 'Land') === false)
        and !$isTokenLike
        and !$isPlanePhenomenon
        and ($row['commander'] == 0)
    ) :
        $other = $other + $row['cardqty'];
    elseif ((strpos($card_type, 'Land') !== false) and !$isTokenLike) :
        $lands = $lands + $row['cardqty'];
    elseif ($isPlanePhenomenon) :
        $planes = $planes + $row['cardqty'];
    endif;
    $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
    $imageFunction = $imageManager->getImage(
        $cardset,
        $row['cardsid'],
        $imgLocation,
        $row['layout'],
        $twoCardDetailSections,
        false
    );
    if ($imageFunction['front'] == 'error') :
        $imageUrl = '/images/back.jpg';
    else :
        $imageUrl = $imageFunction['front'];
    endif;
    $deckcardname = str_replace("'", '&#39;', $row["name"]);
    $deckvalue = $deckvalue + ($row['price_sort'] * $row['cardqty']);
    $cardref = str_replace('.', '-', $row['cardsid']);
endwhile;
$msg->logMessage('[DEBUG]', "Colours: W: $w, U: $u, B: $b, R: $r, G: $g, C: $c");
$msg->logMessage('[DEBUG]', "Gen mana: W: $gw, U: $gu, B: $gb, R: $gr, G: $gg, C: $gc");

if (isset($cdrSet) and $cdrSet === true) :
    // Finalise allowable colour identity for Commander decks
    $cdr_colours_raw = $cdr_colours = '["' . count_chars(
        str_replace(array('"', '[', ']', ',', ' '), '', implode(",", $cdr_colours)),
        3
    ) . '"]';
    $msg->logMessage('[DEBUG]', "Commander value (variable i) is $i, Colour identity to check is $cdr_colours");

    if ($i > 0 and $cdr_colours == '[""]') :
        $cdr_colours = '["C"]';
    endif;
    $cdr_colours = colourFunction($cdr_colours);
else :
    $cdr_colours_raw = $cdr_colours = "";
endif;

mysqli_data_seek($sideresult, 0);
while ($row = $sideresult->fetch_assoc()) :
    if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
        $row['name'] = $row['flavor_name'];
    endif;
    $cardset = strtolower($row["setcode"]);
    $imageManager = new \MTG\Cards\ImageManager($db, $logfile, $serverEmail, $adminEmail);
    $imageFunction = $imageManager->getImage(
        $cardset,
        $row['cardsid'],
        $imgLocation,
        $row['layout'],
        $twoCardDetailSections,
        false
    );
    if ($imageFunction['front'] == 'error') :
        $imageUrl = '/images/back.jpg';
    else :
        $imageUrl = $imageFunction['front'];
    endif;
    $side = $side + $row['sideqty'];
    $deckvalue = $deckvalue + ($row['price_sort'] * $row['sideqty']);
    $cardref = str_replace('.', '-', $row['cardsid']);
endwhile;
