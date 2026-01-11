<?php

/*
Version:     2.11
Date:        11/01/26
Name:        deckdetail_decklist.php
Purpose:     Deck detail main/sideboard list fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\DeckManager;
use MTG\Cards\ImageManager;

?>
<?php
$planeTypeRegex = '/\\bPlane\\b/i';
$phenomenonTypeRegex = '/\\bPhenomenon\\b/i';
$detectPlanePhenomenon = function ($cardType) use ($planeTypeRegex, $phenomenonTypeRegex) {
    $cardType = (string) $cardType;

    return (
        (preg_match($planeTypeRegex, $cardType) === 1 && stripos($cardType, 'Planeswalker') === false)
        || preg_match($phenomenonTypeRegex, $cardType) === 1
    );
};
if (!isset($deckManager) || !($deckManager instanceof DeckManager)) :
    $deckManager = new DeckManager(
        $db,
        $appConfig,
        $userEmail,
        $importLinestoIgnore,
        $nonPreferredSetCodes,
        $any_quantity,
    );
endif;
?>
<div id="decklist-fragment">
<table class='deckcardlist'>
                <tr class='deckcardlisthead'>
                    <td class='deckcardlisthead1'>
                        <span
                            class="material-symbols-outlined noprint js-decksection-toggle-all decksection-toggle-all"
                            title="Fold/Unfold all"
                            style="cursor: pointer;">
                            unfold_more
                        </span>
                        <span class="noprint">Card</span>
                    </td>
                    <?php
                    if (in_array($decktype, $commander_decktypes)) : ?>
                        <td class="deckcardlisthead3">
                            <span class="noprint">Cdr</span>
                        </td> <?php
                    endif;
                    ?>
                    <td class="deckcardlisthead3">
                        <span class="noprint">Del</span>
                    </td>
                    <?php
                    if ($decktype != 'Wishlist') : ?>
                        <td class='deckcardlisthead3'>
                            <span class="noprint">Side</span>
                        </td> <?php
                    endif;
                    if (!in_array($decktype, $commander_decktypes)) : ?>
                        <td class='deckcardlisthead3 deckcardlistright'>
                            <span class="noprint">- &nbsp;</span>
                        </td>
                        <td class='deckcardlisthead3'>
                            <span class="noprint">Qty</span>
                        </td>
                        <td class='deckcardlisthead3 deckcardlistleft'>
                            <span class="noprint">&nbsp;+</span>
                        </td> <?php
                    endif; ?>
                </tr>
                <?php
                // Only show this row if the decktype is Commander style
                if (in_array($decktype, $commander_decktypes)) :
                    $msg->logMessage('[DEBUG]', "This is a '$decktype' deck, adding commander row");
                    ?>
                    <tr>
                        <td colspan='4'>
                            <i><b>Commander</b></i>
                        </td>
                    </tr>
                    <?php
                    $total    = 0;
                    $cmc[0]   = 0;
                    $cmc[1]   = 0;
                    $cmc[2]   = 0;
                    $cmc[3]   = 0;
                    $cmc[4]   = 0;
                    $cmc[5]   = 0;
                    $cmc[6]   = 0;
                    $cmctotal = 0;
                    if (mysqli_num_rows($result) > 0) :
                        mysqli_data_seek($result, 0);
                        $commandercount = 0;
                        while ($row = $result->fetch_assoc()) :
                            $baseCardName = $row['name'];
                            if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                $row['name'] = $row['flavor_name'];
                            endif;

                            // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                            if ($row['type'] !== null) :
                                $card_type = $row['type'];
                                $cardcmc = $row['cmc'];
                            elseif ($row['type'] === null and isset($row['f1_type'])) :
                                $card_type = $row['f1_type'];
                                $cardcmc = $row['f1_cmc'];
                            endif;

                            if ($row['commander'] == 1) :
                                $cardname = $row["name"];
                                $rarity = $row["rarity"];
                                $quantity = $row["cardqty"];
                                $cardset = strtolower($row["setcode"]);
                                $cardref = str_replace('.', '-', $row['cardsid']);
                                $cardId = $row['cardsid'];
                                $cardnumber = $row["number_import"];
                                $layout = $row['layout'];
                                $imageManager = new ImageManager($db, $appConfig);
                                $imageFunction = $imageManager->getImage(
                                    $cardset,
                                    $cardId,
                                    $imgLocation,
                                    $layout,
                                    $twoCardDetailSections,
                                    false
                                );
                                if ($imageFunction['front'] == 'error') :
                                    $imageUrl = '/images/back.jpg';
                                else :
                                    $imageUrl = $imageFunction['front'];
                                endif;
                                $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                                if ($deck_legality_list != '') :
                                    $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                    $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                    if ($index !== false) :
                                        $card_legal = $deck_legality_list[$index]['legality'];
                                        if ($card_legal === 'legal' or $card_legal === null) :
                                            $illegal_tag = '';
                                        else :
                                            $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                            $illegal_cards = true;
                                        endif;
                                    else :
                                        $illegal_tag = '';
                                    endif;
                                else :
                                    $illegal_tag = '';
                                endif;

                                $cardcmc = round($cardcmc);
                                $cmctotal = $cmctotal + ($cardcmc * $quantity);
                                if ($cardcmc > 5) :
                                    $cardcmc = 6;
                                endif;
                                $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity;
                                $commandername = $cardname;
                                ?>
                                <tr class='deckrow' data-section='commander' data-qty='<?php echo $quantity; ?>'>
                                <?php $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}"; ?>
                                <td class="deckcardname hoverTD">
                                    <?php echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a>";
                                    echo "<td class='deckcardlistcenter noprint'>";
                                        $validpartner = false;
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "This is a '$decktype' deck, checking if $cardname is a valid partner "
                                            . "or background"
                                        );
                                        $i = 0;
                                    while ($i < count($second_commander_text)) :
                                        if (
                                            isset($row['ability'])
                                            and str_contains($row['ability'], $second_commander_text[$i]) == true
                                        ) :
                                            $validpartner = true;
                                        endif;
                                        $i++;
                                    endwhile;
                                    if ($validpartner == true) : ?>
                                            <span
                                                onmouseover=""
                                                title="Move to Partner"
                                                style="cursor: pointer;"
                                                class='material-symbols-outlined js-partner-add'
                                                data-cardid="<?php echo $cardId; ?>"
                                                data-cardref="<?php echo $cardref; ?>">
                                                south_east
                                            </span>
                                            <?php
                                    else : ?>
                                            <span
                                                onmouseover=""
                                                title="Move to main deck"
                                                style="cursor: pointer;"
                                                class='material-symbols-outlined js-commander-remove'
                                                data-cardid="<?php echo $cardId; ?>"
                                                data-cardref="<?php echo $cardref; ?>">
                                                arrow_downward
                                            </span>
                                            <?php
                                    endif;
                                        echo "</td>";
                                        echo "</td>";
                                    if (
                                        in_array($row['layout'], $image90rotate)
                                        or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                                    ) :
                                        $hoverclass = 'deckcardimgdiv splitfloat';
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Hover image rotated for deckdetail card '$cardname'"
                                        );
                                    else :
                                        $hoverclass = 'deckcardimgdiv';
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Hover image not rotated for deckdetail card '$cardname'"
                                        );
                                    endif;
                                    ?>
                                <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                    <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                    <img
                                        alt='<?php echo $deckcardname;?>'
                                        class='deckcardimg'
                                        data-cardid="<?php echo $row['cardsid']; ?>"
                                        data-front-src="<?php echo $imageUrl; ?>"
                                        src='<?php echo $imageUrl;?>'
                                    ></a>
                                </div> <?php
                                $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Delete"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-deletemain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    delete_forever
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Move to sideboard"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-maintoside'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                                if (!in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistcenter js-qty-main' id='qty-main-$cardref'>";
                                    echo $quantity;
                                    echo "</td>";
                                endif;
                                echo "</tr>";
                                $total = $total + $quantity;
                                $commandercount = $commandercount + 1;
                            endif;
                        endwhile;
                    endif;
                    if (in_array($decktype, $commander_decktypes)) :
                        ?>
                        <tr>
                            <td colspan='4'>
                                <i><b>Partner / Background</b></i>
                            </td>
                        </tr>
                        <?php
                        if (mysqli_num_rows($result) > 0) :
                            mysqli_data_seek($result, 0);
                            while ($row = $result->fetch_assoc()) :
                                $baseCardName = $row['name'];
                                if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                    $row['name'] = $row['flavor_name'];
                                endif;

                                // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                                if ($row['type'] !== null) :
                                    $card_type = $row['type'];
                                    $cardcmc = $row['cmc'];
                                elseif ($row['type'] === null and isset($row['f1_type'])) :
                                    $card_type = $row['f1_type'];
                                    $cardcmc = $row['f1_cmc'];
                                endif;

                                if ($row['commander'] == 2) :
                                    $cardname = $row["name"];
                                    $rarity = $row["rarity"];
                                    $quantity = $row["cardqty"];
                                    $cardset = strtolower($row["setcode"]);
                                    $cardref = str_replace('.', '-', $row['cardsid']);
                                    $cardId = $row['cardsid'];
                                    $cardnumber = $row["number_import"];
                                    $layout = $row['layout'];
                                    $imageManager = new ImageManager($db, $appConfig);
                                    $imageFunction = $imageManager->getImage(
                                        $cardset,
                                        $cardId,
                                        $imgLocation,
                                        $layout,
                                        $twoCardDetailSections,
                                        false
                                    );
                                    if ($imageFunction['front'] == 'error') :
                                        $imageUrl = '/images/back.jpg';
                                    else :
                                        $imageUrl = $imageFunction['front'];
                                    endif;
                                    $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                                    if ($deck_legality_list != '') :
                                        $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                        $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                        if ($index !== false) :
                                            $card_legal = $deck_legality_list[$index]['legality'];
                                            if ($card_legal === 'legal' or $card_legal === null) :
                                                $illegal_tag = '';
                                            else :
                                                $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                                $illegal_cards = true;
                                            endif;
                                        else :
                                            $illegal_tag = '';
                                        endif;
                                    else :
                                        $illegal_tag = '';
                                    endif;
                                    $cardcmc = round($cardcmc);
                                    $cmctotal = $cmctotal + ($cardcmc * $quantity);
                                    if ($cardcmc > 5) :
                                        $cardcmc = 6;
                                    endif;
                                    $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity;
                                    $secondcommandername = $cardname;
                                    $warnings = true;
                                    ?>
                                    <tr class='deckrow' data-section='commander' data-qty='<?php echo $quantity; ?>'>
                                    <?php $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}"; ?>
                                    <td class="deckcardname hoverTD">
                                        <?php echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                            . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                            . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)"
                                            . "</a></a>";
                                        echo "<td class='deckcardlistcenter noprint'>";
                                        ?>
                                    <span
                                        onmouseover=""
                                        title="Move to main deck"
                                        style="cursor: pointer;"
                                        class='material-symbols-outlined js-commander-remove'
                                        data-cardid="<?php echo $cardId; ?>"
                                        data-cardref="<?php echo $cardref; ?>">
                                        arrow_downward
                                    </span>
                                    <?php
                                    echo "</td>";
                                    if (
                                        in_array($row['layout'], $image90rotate)
                                        or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                                    ) :
                                        $hoverclass = 'deckcardimgdiv splitfloat';
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Hover image rotated for deckdetail card '$cardname'"
                                        );
                                    else :
                                        $hoverclass = 'deckcardimgdiv';
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Hover image not rotated for deckdetail card '$cardname'"
                                        );
                                    endif;
                                    ?>
                                    <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                        <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                        <img
                                            alt='<?php echo $deckcardname;?>'
                                            class='deckcardimg'
                                            data-cardid="<?php echo $row['cardsid']; ?>"
                                            data-front-src="<?php echo $imageUrl; ?>"
                                            src='<?php echo $imageUrl;?>'
                                        ></a>
                                    </div> <?php
                                    echo "</td>";
                                    echo "<td class='deckcardlistcenter noprint'>";
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Delete"
                                        style="cursor: pointer;"
                                        class='material-symbols-outlined js-deletemain'
                                        data-cardid="<?php echo $cardId; ?>"
                                        data-cardref="<?php echo $cardref; ?>">
                                        delete_forever
                                    </span>
                                    <?php
                                    echo "</td>";
                                    echo "<td class='deckcardlistcenter noprint'>";
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Move to sideboard"
                                        style="cursor: pointer;"
                                        class='material-symbols-outlined js-maintoside'
                                        data-cardid="<?php echo $cardId; ?>"
                                        data-cardref="<?php echo $cardref; ?>">
                                        arrow_downward
                                    </span>
                                    <?php
                                    echo "</td>";
                                    if (!in_array($decktype, $commander_decktypes)) :
                                        echo "<td class='deckcardlistcenter'>";
                                        echo $quantity;
                                        echo "</td>";
                                    endif;
                                    echo "</tr>";
                                    $total = $total + $quantity;
                                endif;
                            endwhile;
                        endif;
                    endif;?>
                    <tr class="deck-section-header" data-section="creatures">
                        <td colspan='4'>
                            <i><b>
                                <span
                                    class="material-symbols-outlined noprint
                                        js-decksection-toggle decksection-toggle-icon"
                                    data-section="creatures"
                                    title="Fold/Unfold"
                                    style="cursor: pointer;">expand_more</span>
                                Creatures (<span id='total-creatures'><?php echo $creatures; ?></span>)
                            </b></i>
                        </td>
                    </tr>
                    <?php
                else :
                    ?>
                    <tr class="deck-section-header" data-section="creatures">
                        <?php
                        if (in_array($decktype, $commander_decktypes)) : ?>
                            <td colspan='4'> <?php
                        elseif ($decktype == 'Wishlist') : ?>
                            <td colspan='5'> <?php
                        else : ?>
                            <td colspan='6'> <?php
                        endif; ?>
                            <i><b>
                                <span
                                    class="material-symbols-outlined noprint
                                        js-decksection-toggle decksection-toggle-icon"
                                    data-section="creatures"
                                    title="Fold/Unfold"
                                    style="cursor: pointer;">expand_more</span>
                                Creatures (<span id='total-creatures'><?php echo $creatures; ?></span>)
                            </b></i>
                        </td>
                    </tr>
                    <?php
                    $total    = 0;
                    $cmc[0]   = 0;
                    $cmc[1]   = 0;
                    $cmc[2]   = 0;
                    $cmc[3]   = 0;
                    $cmc[4]   = 0;
                    $cmc[5]   = 0;
                    $cmc[6]   = 0;
                    $cmctotal = 0;
                endif;
                $deckcard_no = 1; // Initialise card count for random draw
                if (mysqli_num_rows($result) > 0) :
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()) :
                        $baseCardName = $row['name'];
                        if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;

                        // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                            $cardcmc = $row['cmc'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                            $cardcmc = $row['f1_cmc'];
                        endif;

                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        if (
                            (strpos($card_type, 'Creature') !== false)
                            and ($row['commander'] < 1)
                            and (strpos($card_type, 'Token') === false)
                            and (strpos($card_type, 'Emblem') === false)
                        ) :
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $cardnumber = $row["number_import"];
                            $layout = $row['layout'];
                            $imageManager = new ImageManager($db, $appConfig);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            while ($rowqty < $quantity) :
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $uniquecard_ref["$deckcard_no"]['cardref'] = $cardref;
                                $uniquecard_ref["$deckcard_no"]['cardid'] = $cardId;
                                $uniquecard_ref["$deckcard_no"]['imageurl'] = $imageUrl;
                                $uniquecard_ref["$deckcard_no"]['cardurl'] = '/carddetail.php?id=' . $cardId;
                                $uniquecard_ref["$deckcard_no"]['layout'] = $row['layout'];
                                $uniquecard_ref["$deckcard_no"]['f1_type'] = $row['f1_type'] ?? null;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                            if ($deck_legality_list != '') :
                                $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                if ($index !== false) :
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if ($card_legal === 'legal' or $card_legal === null) :
                                        $illegal_tag = '';
                                    else :
                                        $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                        $illegal_cards = true;
                                    endif;
                                else :
                                    $illegal_tag = '';
                                endif;
                            else :
                                $illegal_tag = '';
                            endif;
                            if (in_array($decktype, $commander_decktypes) and $illegal_tag == '') :
                                $colour_id = count_chars(
                                    str_replace(array('"', '[', ']', ',', ' '), '', $row['color_identity']),
                                    3
                                );
                                $msg->logMessage('[DEBUG]', "Card's colour identity is $colour_id");
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach ($colour_id_array as $value) :
                                    if (strpos($cdr_colours_raw, $value) == false) :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity not OK with Commander(s)"
                                        );
                                        $card_colour_mismatch = true;
                                    else :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity is OK with Commander(s)"
                                        );
                                    endif;
                                endforeach;
                                if ($card_colour_mismatch == '' or $colour_id == '') :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity is OK with Commander(s)");
                                    $wrong_colour_tag = '';
                                else :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity not OK with Commander(s)");
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = true;
                                endif;
                            endif;
                            $cardcmc = round($cardcmc);
                            $cardlegendary = $card_type;
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5) :
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow' data-section='creatures' data-qty='<?php echo $quantity; ?>'>
                            <td class="deckcardname hoverTD">
                                <?php
                                $i = 0;
                                $cdr_1_plus = false;
                                while ($i < count($commander_multiples)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $commander_multiples[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while ($i < count($any_quantity)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $any_quantity[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$quantity x $cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                else :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                endif;
                                $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                                if (in_array($decktype, $commander_decktypes)) :
                                    $validcommander = false;
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "This is a '$decktype' deck, checking if $cardname is a valid commander"
                                    );
                                    if (
                                        (strpos($cardlegendary, "Legendary") !== false)
                                        and (strpos($cardlegendary, "Creature") !== false)
                                    ) :
                                        $validcommander = true;
                                    endif;
                                    $i = 0;
                                    while ($i < count($valid_commander_text)) :
                                        if (
                                            isset($row['ability'])
                                            and str_contains($row['ability'], $valid_commander_text[$i]) == true
                                        ) :
                                            $validcommander = true;
                                        endif;
                                        $i++;
                                    endwhile;
                                    echo "<td class='deckcardlistcenter noprint'>";
                                    if ($validcommander == true) :
                                        ?>
                                        <span
                                            onmouseover=""
                                            title="Move to Commander"
                                            style="cursor: pointer;"
                                            class='material-symbols-outlined js-commander-add'
                                            data-cardid="<?php echo $cardId; ?>"
                                            data-cardref="<?php echo $cardref; ?>">
                                            person
                                        </span>
                                        <?php
                                    endif;
                                    echo "</td>";
                                endif;
                                echo "</td>";
                                if (
                                    in_array($row['layout'], $image90rotate)
                                    or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                                ) :
                                    $hoverclass = 'deckcardimgdiv splitfloat';
                                    $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                                else :
                                    $hoverclass = 'deckcardimgdiv';
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Hover image not rotated for deckdetail card '$cardname'"
                                    );
                                endif;
                                ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div> <?php
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span
                                onmouseover=""
                                title="Delete"
                                style="cursor: pointer;"
                                class='material-symbols-outlined js-deletemain'
                                data-cardid="<?php echo $cardId; ?>"
                                data-cardref="<?php echo $cardref; ?>">
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            if ($decktype != 'Wishlist') :
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Move to sideboard"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-maintoside'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if (!in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Remove one"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-minusmain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>"
                                    data-qty-target="qty-main-<?php echo $cardref; ?>">
                                    remove
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter js-qty-main' id='qty-main-$cardref'>";
                                echo $quantity;
                                echo "</td>";
                                $maxCopies = $deckManager->mtgCardCopyLimit(
                                    $card_type,
                                    $row['ability'] ?? null,
                                    $row['f1_ability'] ?? null,
                                    $row['f2_ability'] ?? null,
                                    $decktype
                                );
                                $canAddMore = true;
                                if ($maxCopies !== null) :
                                    $currentCopies = $resultNameTotals[$baseCardName] ?? 0;
                                    if ($currentCopies >= $maxCopies) :
                                        $canAddMore = false;
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Copy limit reached for '$baseCardName' ($currentCopies/$maxCopies)"
                                        );
                                    endif;
                                endif;
                                $addStyle = $canAddMore ? '' : ' display: none;';
                                echo "<td class='deckcardlistleft noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Add one"
                                    style="cursor: pointer;<?php echo $addStyle; ?>"
                                    class='material-symbols-outlined js-plusmain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-qty-target="qty-main-<?php echo $cardref; ?>"
                                    data-maxcopies="<?php echo $maxCopies !== null ? $maxCopies : ''; ?>">
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity;
                        endif;
                    endwhile;
                endif; ?>
                <tr class="deck-section-header" data-section="instantsorcery">
                    <?php
                    if (in_array($decktype, $commander_decktypes)) : ?>
                        <td colspan='4'> <?php
                    elseif ($decktype == 'Wishlist') : ?>
                        <td colspan='5'> <?php
                    else : ?>
                        <td colspan='6'> <?php
                    endif; ?>
                    <i><b>
                        <span
                            class="material-symbols-outlined noprint js-decksection-toggle decksection-toggle-icon"
                            data-section="instantsorcery"
                            title="Fold/Unfold"
                            style="cursor: pointer;">expand_more</span>
                        Instants and Sorceries
                        (<span id='total-instantsorcery'><?php echo $instantsorcery; ?></span>)</b></i>
                    </td>
                </tr>
                <?php
                if (mysqli_num_rows($result) > 0) :
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()) :
                        $baseCardName = $row['name'];
                        if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;

                        // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                            $cardcmc = $row['cmc'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                            $cardcmc = $row['f1_cmc'];
                        endif;

                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        if (
                            ((strpos($card_type, 'Sorcery') !== false) or (strpos($card_type, 'Instant') !== false))
                            and (strpos($card_type, 'Token') === false)
                            and (strpos($card_type, 'Emblem') === false)
                        ) :
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $cardnumber = $row["number_import"];
                            $layout = $row['layout'];
                            $imageManager = new ImageManager($db, $appConfig);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            while ($rowqty < $quantity) :
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $uniquecard_ref["$deckcard_no"]['cardref'] = $cardref;
                                $uniquecard_ref["$deckcard_no"]['cardid'] = $cardId;
                                $uniquecard_ref["$deckcard_no"]['imageurl'] = $imageUrl;
                                $uniquecard_ref["$deckcard_no"]['cardurl'] = '/carddetail.php?id=' . $cardId;
                                $uniquecard_ref["$deckcard_no"]['layout'] = $row['layout'];
                                $uniquecard_ref["$deckcard_no"]['f1_type'] = $row['f1_type'] ?? null;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                            if ($deck_legality_list != '') :
                                $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                if ($index !== false) :
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if ($card_legal === 'legal' or $card_legal === null) :
                                        $illegal_tag = '';
                                    else :
                                        $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                        $illegal_cards = true;
                                    endif;
                                else :
                                    $illegal_tag = '';
                                endif;
                            else :
                                $illegal_tag = '';
                            endif;
                            if (in_array($decktype, $commander_decktypes) and $illegal_tag == '') :
                                $colour_id = count_chars(
                                    str_replace(array('"', '[', ']', ',', ' '), '', $row['color_identity']),
                                    3
                                );
                                $msg->logMessage('[DEBUG]', "Card's colour identity is $colour_id");
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach ($colour_id_array as $value) :
                                    if (strpos($cdr_colours_raw, $value) == false) :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity not OK with Commander(s)"
                                        );
                                        $card_colour_mismatch = true;
                                    else :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity is OK with Commander(s)"
                                        );
                                    endif;
                                endforeach;
                                if ($card_colour_mismatch == '' or $colour_id == '') :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity is OK with Commander(s)");
                                    $wrong_colour_tag = '';
                                else :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity not OK with Commander(s)");
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = true;
                                endif;
                            endif;
                            $cardcmc = round($cardcmc);
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5) :
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow' data-section='instantsorcery' data-qty='<?php echo $quantity; ?>'>
                            <td class="deckcardname hoverTD">
                                <?php
                                $i = 0;
                                $cdr_1_plus = false;
                                while ($i < count($commander_multiples)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $commander_multiples[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while ($i < count($any_quantity)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $any_quantity[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$quantity x $cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                else :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                endif;
                                echo "</td>";
                                if (
                                    in_array($row['layout'], $image90rotate)
                                    or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                                ) :
                                    $hoverclass = 'deckcardimgdiv splitfloat';
                                    $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                                else :
                                    $hoverclass = 'deckcardimgdiv';
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Hover image not rotated for deckdetail card '$cardname'"
                                    );
                                endif;
                                ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div> <?php
                            $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                            if (in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span
                                onmouseover=""
                                title="Delete"
                                style="cursor: pointer;"
                                class='material-symbols-outlined js-deletemain'
                                data-cardid="<?php echo $cardId; ?>"
                                data-cardref="<?php echo $cardref; ?>">
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            if ($decktype != 'Wishlist') :
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Move to sideboard"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-maintoside'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if (!in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Remove one"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-minusmain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>"
                                    data-qty-target="qty-main-<?php echo $cardref; ?>">
                                    remove
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter js-qty-main' id='qty-main-$cardref'>";
                                echo $quantity;
                                echo "</td>";
                                $maxCopies = $deckManager->mtgCardCopyLimit(
                                    $card_type,
                                    $row['ability'] ?? null,
                                    $row['f1_ability'] ?? null,
                                    $row['f2_ability'] ?? null,
                                    $decktype
                                );
                                $canAddMore = true;
                                if ($maxCopies !== null) :
                                    $currentCopies = $resultNameTotals[$baseCardName] ?? 0;
                                    if ($currentCopies >= $maxCopies) :
                                        $canAddMore = false;
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Copy limit reached for '$baseCardName' ($currentCopies/$maxCopies)"
                                        );
                                    endif;
                                endif;
                                $addStyle = $canAddMore ? '' : ' display: none;';
                                echo "<td class='deckcardlistleft noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Add one"
                                    style="cursor: pointer;<?php echo $addStyle; ?>"
                                    class='material-symbols-outlined js-plusmain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-qty-target="qty-main-<?php echo $cardref; ?>"
                                    data-maxcopies="<?php echo $maxCopies !== null ? $maxCopies : ''; ?>">
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity;
                        endif;
                    endwhile;
                endif; ?>
                <tr class="deck-section-header" data-section="other">
                    <?php
                    if (in_array($decktype, $commander_decktypes)) : ?>
                        <td colspan='4'> <?php
                    elseif ($decktype == 'Wishlist') : ?>
                        <td colspan='5'> <?php
                    else : ?>
                        <td colspan='6'> <?php
                    endif; ?>
                    <i><b>
                        <span
                            class="material-symbols-outlined noprint js-decksection-toggle decksection-toggle-icon"
                            data-section="other"
                            title="Fold/Unfold"
                            style="cursor: pointer;">expand_more</span>
                        Other (<span id='total-other'><?php echo $other; ?></span>)</b></i>
                    </td>
                </tr>
                <?php
                if (mysqli_num_rows($result) > 0) :
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()) :
                        $baseCardName = $row['name'];
                        if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;

                        // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                            $cardcmc = $row['cmc'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                            $cardcmc = $row['f1_cmc'];
                        endif;

                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        if (
                            (strpos($card_type, 'Sorcery') === false)
                            and (strpos($card_type, 'Instant') === false)
                            and (strpos($card_type, 'Creature') === false)
                            and (strpos($card_type, 'Land') === false)
                            and (strpos($card_type, 'Token') === false)
                            and (strpos($card_type, 'Emblem') === false)
                            and !$detectPlanePhenomenon($card_type)
                            and ($row['commander'] < 1)
                        ) :
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $cardnumber = $row["number_import"];
                            $layout = $row['layout'];
                            $imageManager = new ImageManager($db, $appConfig);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            while ($rowqty < $quantity) :
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $uniquecard_ref["$deckcard_no"]['cardref'] = $cardref;
                                $uniquecard_ref["$deckcard_no"]['cardid'] = $cardId;
                                $uniquecard_ref["$deckcard_no"]['imageurl'] = $imageUrl;
                                $uniquecard_ref["$deckcard_no"]['cardurl'] = '/carddetail.php?id=' . $cardId;
                                $uniquecard_ref["$deckcard_no"]['layout'] = $row['layout'];
                                $uniquecard_ref["$deckcard_no"]['f1_type'] = $row['f1_type'] ?? null;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                            if ($deck_legality_list != '') :
                                $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                if ($index !== false) :
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if ($card_legal === 'legal' or $card_legal === null) :
                                        $illegal_tag = '';
                                    else :
                                        $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                        $illegal_cards = true;
                                    endif;
                                else :
                                    $illegal_tag = '';
                                endif;
                            else :
                                $illegal_tag = '';
                            endif;
                            if (in_array($decktype, $commander_decktypes) and $illegal_tag == '') :
                                $colour_id = count_chars(
                                    str_replace(array('"', '[', ']', ',', ' '), '', $row['color_identity']),
                                    3
                                );
                                $msg->logMessage('[DEBUG]', "Card's colour identity is $colour_id");
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach ($colour_id_array as $value) :
                                    if (strpos($cdr_colours_raw, $value) == false) :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity not OK with Commander(s)"
                                        );
                                        $card_colour_mismatch = true;
                                    else :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity is OK with Commander(s)"
                                        );
                                    endif;
                                endforeach;
                                if ($card_colour_mismatch == '' or $colour_id == '') :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity is OK with Commander(s)");
                                    $wrong_colour_tag = '';
                                else :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity not OK with Commander(s)");
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = true;
                                endif;
                            endif;
                            $cardcmc = round($cardcmc);
                            $cmctotal = $cmctotal + ($cardcmc * $quantity);
                            if ($cardcmc > 5) :
                                $cardcmc = 6;
                            endif;
                            $cmc[$cardcmc] = $cmc[$cardcmc] + $quantity; ?>
                            <tr class='deckrow' data-section='other' data-qty='<?php echo $quantity; ?>'>
                            <td class="deckcardname hoverTD">
                                <?php
                                $i = 0;
                                $cdr_1_plus = false;
                                while ($i < count($commander_multiples)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $commander_multiples[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while ($i < count($any_quantity)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $any_quantity[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$quantity x $cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                else :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                endif;
                                echo "</td>";
                                if (
                                    in_array($row['layout'], $image90rotate)
                                    or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                                ) :
                                    $hoverclass = 'deckcardimgdiv splitfloat';
                                    $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                                else :
                                    $hoverclass = 'deckcardimgdiv';
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Hover image not rotated for deckdetail card '$cardname'"
                                    );
                                endif;
                                ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div> <?php
                            $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                            if (in_array($decktype, $commander_decktypes)) :
                                $validcommander = false;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "This is a '$decktype' deck, checking if $cardname is valid as a commander"
                                );
                                $i = 0;
                                while ($i < count($valid_commander_text)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $valid_commander_text[$i]) == true
                                    ) :
                                        $validcommander = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $secondcommander = false;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "This is a '$decktype' deck, checking if $cardname is valid as a 2nd commander"
                                );
                                $i = 0;
                                while ($i < count($second_commander_text)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $second_commander_text[$i]) == true
                                    ) :
                                        $secondcommander = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $secondcommanderonly = false;
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "This is a '$decktype' deck, checking if $cardname is valid as a 2nd commander only"
                                );
                                $i = 0;
                                while ($i < count($second_commander_only_type)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $second_commander_only_type[$i]) == true
                                    ) :
                                        $secondcommanderonly = true;
                                    endif;
                                    $i++;
                                endwhile;
                                echo "<td class='deckcardlistcenter noprint'>";
                                if ($validcommander == true) :
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Move to Commander"
                                        style="cursor: pointer;"
                                        class='material-symbols-outlined js-commander-add'
                                        data-cardid="<?php echo $cardId; ?>"
                                        data-cardref="<?php echo $cardref; ?>">
                                        person
                                    </span>
                                    <?php
                                elseif ($secondcommanderonly == true) :
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Move to Background"
                                        style="cursor: pointer;"
                                        class='material-symbols-outlined js-partner-add'
                                        data-cardid="<?php echo $cardId; ?>"
                                        data-cardref="<?php echo $cardref; ?>">
                                        north_west
                                    </span>
                                    <?php
                                endif;
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                                <span
                                    onmouseover=""
                                    title="Delete"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-deletemain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    delete_forever
                                </span>
                            <?php
                            echo "</td>";
                            if ($decktype != 'Wishlist') :
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Move to sideboard"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-maintoside'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if (!in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Remove one"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-minusmain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>"
                                    data-qty-target="qty-main-<?php echo $cardref; ?>">
                                    remove
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter js-qty-main' id='qty-main-$cardref'>";
                                echo $quantity;
                                echo "</td>";
                                $maxCopies = $deckManager->mtgCardCopyLimit(
                                    $card_type,
                                    $row['ability'] ?? null,
                                    $row['f1_ability'] ?? null,
                                    $row['f2_ability'] ?? null,
                                    $decktype
                                );
                                $canAddMore = true;
                                if ($maxCopies !== null) :
                                    $currentCopies = $resultNameTotals[$baseCardName] ?? 0;
                                    if ($currentCopies >= $maxCopies) :
                                        $canAddMore = false;
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Copy limit reached for '$baseCardName' ($currentCopies/$maxCopies)"
                                        );
                                    endif;
                                endif;
                                $addStyle = $canAddMore ? '' : ' display: none;';
                                echo "<td class='deckcardlistleft noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Add one"
                                    style="cursor: pointer;<?php echo $addStyle; ?>"
                                    class='material-symbols-outlined js-plusmain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-qty-target="qty-main-<?php echo $cardref; ?>"
                                    data-maxcopies="<?php echo $maxCopies !== null ? $maxCopies : ''; ?>">
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity;
                        endif;
                    endwhile;
                endif;
                ?>
                <tr class="deck-section-header" data-section="lands">
                    <?php
                    if (in_array($decktype, $commander_decktypes)) : ?>
                        <td colspan='4'> <?php
                    elseif ($decktype == 'Wishlist') : ?>
                        <td colspan='5'> <?php
                    else : ?>
                        <td colspan='6'> <?php
                    endif; ?>
                    <i><b>
                        <span
                            class="material-symbols-outlined noprint js-decksection-toggle decksection-toggle-icon"
                            data-section="lands"
                            title="Fold/Unfold"
                            style="cursor: pointer;">expand_more</span>
                        Lands (<span id='total-lands'><?php echo $lands; ?></span>)</b></i>
                    </td>
                </tr>
                <?php
                if (mysqli_num_rows($result) > 0) :
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()) :
                        $baseCardName = $row['name'];
                        if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                            $row['name'] = $row['flavor_name'];
                        endif;
                        $illegal_tag = $red_font_tag;
                        $wrong_colour_tag = $firebrick_font_tag;

                        // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                            $cardcmc = $row['cmc'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                            $cardcmc = $row['f1_cmc'];
                        endif;

                        // Check if it's a land, unless it's a Land Creature (Dryad Arbor)
                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        if (
                            (strpos($card_type, 'Land') !== false)
                            and (strpos($card_type, 'Land Creature') === false)
                            and (strpos($card_type, 'Token') === false)
                            and (strpos($card_type, 'Emblem') === false)
                        ) :
                            $quantity = $row["cardqty"];
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $rowqty = 0;
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $cardnumber = $row["number_import"];
                            $layout = $row['layout'];
                            $imageManager = new ImageManager($db, $appConfig);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            while ($rowqty < $quantity) :
                                $uniquecard_ref["$deckcard_no"]['name'] = $cardname;
                                $uniquecard_ref["$deckcard_no"]['cardref'] = $cardref;
                                $uniquecard_ref["$deckcard_no"]['cardid'] = $cardId;
                                $uniquecard_ref["$deckcard_no"]['imageurl'] = $imageUrl;
                                $uniquecard_ref["$deckcard_no"]['cardurl'] = '/carddetail.php?id=' . $cardId;
                                $deckcard_no = $deckcard_no + 1;
                                $rowqty = $rowqty + 1;
                            endwhile;
                            $msg->logMessage('[DEBUG]', "Main deck card '$cardname ($cardset $cardnumber)'");
                            if ($deck_legality_list != '') :
                                $msg->logMessage('[DEBUG]', "Checking legality for main deck card '$cardname'");
                                $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                if ($index !== false) :
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if ($card_legal === 'legal' or $card_legal === null) :
                                        $illegal_tag = '';
                                    else :
                                        $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                        $illegal_cards = true;
                                    endif;
                                else :
                                    $illegal_tag = '';
                                endif;
                            else :
                                $illegal_tag = '';
                            endif;
                            if (in_array($decktype, $commander_decktypes) and $illegal_tag == '') :
                                $colour_id = count_chars(
                                    str_replace(array('"', '[', ']', ',', ' '), '', $row['color_identity']),
                                    3
                                );
                                $msg->logMessage('[DEBUG]', "Card's colour identity is $colour_id");
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach ($colour_id_array as $value) :
                                    if (strpos($cdr_colours_raw, $value) == false) :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity not OK with Commander(s)"
                                        );
                                        $card_colour_mismatch = true;
                                    else :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity is OK with Commander(s)"
                                        );
                                    endif;
                                endforeach;
                                if ($card_colour_mismatch == '' or $colour_id == '') :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity is OK with Commander(s)");
                                    $wrong_colour_tag = '';
                                else :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity not OK with Commander(s)");
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = true;
                                endif;
                            endif; ?>
                            <tr class='deckrow' data-section='lands' data-qty='<?php echo $quantity; ?>'>
                            <td class="deckcardname hoverTD">
                                <?php
                                $i = 0;
                                $cdr_1_plus = false;
                                while ($i < count($commander_multiples)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $commander_multiples[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$quantity x $cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                else :
                                    echo "<a class='taphover' $illegal_tag id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)</a></a>";
                                endif;
                                echo "</td>";
                                if (
                                    in_array($row['layout'], $image90rotate)
                                    or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                                ) :
                                    $hoverclass = 'deckcardimgdiv splitfloat';
                                    $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                                else :
                                    $hoverclass = 'deckcardimgdiv';
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Hover image not rotated for deckdetail card '$cardname'"
                                    );
                                endif;
                                ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div> <?php
                            $cardActionBase = "deckdetail.php?deck={$deckNumber}&amp;card={$cardId}";
                            if (in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                            echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                            <span
                                onmouseover=""
                                title="Delete"
                                style="cursor: pointer;"
                                class='material-symbols-outlined js-deletemain'
                                data-cardid="<?php echo $cardId; ?>"
                                data-cardref="<?php echo $cardref; ?>">
                                delete_forever
                            </span>
                            <?php
                            echo "</td>";
                            if ($decktype != 'Wishlist') :
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Move to sideboard"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-maintoside'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    arrow_downward
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            if (!in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistright noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Remove one"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-minusmain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>"
                                    data-qty-target="qty-main-<?php echo $cardref; ?>">
                                    remove
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter js-qty-main' id='qty-main-$cardref'>";
                                echo $quantity;
                                echo "</td>";
                                $maxCopies = $deckManager->mtgCardCopyLimit(
                                    $card_type,
                                    $row['ability'] ?? null,
                                    $row['f1_ability'] ?? null,
                                    $row['f2_ability'] ?? null,
                                    $decktype
                                );
                                $canAddMore = true;
                                if ($maxCopies !== null) :
                                    $currentCopies = $resultNameTotals[$baseCardName] ?? 0;
                                    if ($currentCopies >= $maxCopies) :
                                        $canAddMore = false;
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Copy limit reached for '$baseCardName' ($currentCopies/$maxCopies)"
                                        );
                                    endif;
                                endif;
                                $addStyle = $canAddMore ? '' : ' display: none;';
                                echo "<td class='deckcardlistleft noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Add one"
                                    style="cursor: pointer;<?php echo $addStyle; ?>"
                                    class='material-symbols-outlined js-plusmain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-qty-target="qty-main-<?php echo $cardref; ?>"
                                    data-maxcopies="<?php echo $maxCopies !== null ? $maxCopies : ''; ?>">
                                    add
                                </span>
                                <?php
                                echo "</td>";
                            endif;
                            echo "</tr>";
                            $total = $total + $quantity;
                        endif;
                    endwhile;
                endif;
                $msg->logMessage('[DEBUG]', "Decktype: $decktype");
                if ($decktype !== 'Wishlist') :
                    $msg->logMessage('[DEBUG]', "Not wishlist, adding a total row");?>
                    <tr
                        style="border-bottom: 1pt solid black; border-top: 1pt solid black;"
                        id="main-total-row"> <?php
                        if (in_array($decktype, $commander_decktypes)) :
                            $msg->logMessage('[DEBUG]', "Commander type colspan 2");
                            echo "<td colspan='2'><i><b>Total</b></i></td>";
                        else :
                            $msg->logMessage('[DEBUG]', "Not Commander type colspan 4");
                            echo "<td colspan='4'><i><b>Total</b></i></td>";
                        endif;?>
                        <td class='deckcardlistcenter'>
                            <i><b><span id='total-main'><?php echo $total; ?></span></b></i>
                        </td>
                        <td>&nbsp;</td>
                    </tr> <?php
                endif;
// SIDEBOARD
                $sideboardOtherRows = [];
                $sideboardPlaneRows = [];
                $sideboardTokenRows = [];
                $sideboardOtherTotal = 0;
                $sideboardPlaneTotal = 0;
                $sideboardTokenTotal = 0;
                if (mysqli_num_rows($sideresult) > 0) :
                    mysqli_data_seek($sideresult, 0);
                    while ($row = $sideresult->fetch_assoc()) :
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                        else :
                            $card_type = '';
                        endif;
                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        $isPlane = $detectPlanePhenomenon($card_type);
                        $isToken = (
                            (strpos($card_type, 'Token') !== false)
                            || (strpos($card_type, 'Emblem') !== false)
                        );
                        if ($isPlane) :
                            $sideboardPlaneRows[] = $row;
                            $sideboardPlaneTotal += (int) $row['sideqty'];
                        elseif ($isToken) :
                            $sideboardTokenRows[] = $row;
                            $sideboardTokenTotal += (int) $row['sideqty'];
                        else :
                            $sideboardOtherRows[] = $row;
                            $sideboardOtherTotal += (int) $row['sideqty'];
                        endif;
                    endwhile;
                endif;
                $msg->logMessage(
                    '[DEBUG]',
                    "Sideboard split totals - base: {$sideboardOtherTotal}, planes: {$sideboardPlaneTotal}, "
                    . "tokens: {$sideboardTokenTotal}"
                );

                if ($decktype != 'Wishlist' && $sideboardOtherTotal > 0) :?>
                    <tr style="border-top: 1pt solid black;" id="sideboard-start" class="deck-section-header"
                        data-section="sideboard">
                        <?php
                        if (in_array($decktype, $commander_decktypes)) :
                            ?>
                            <td colspan='4'>
                            <?php
                        else :
                            ?>
                            <td colspan='6'>
                            <?php
                        endif;

                        ?>
                        <i><b>
                            <span
                                class="material-symbols-outlined noprint js-decksection-toggle decksection-toggle-icon"
                                data-section="sideboard"
                                title="Fold/Unfold"
                                style="cursor: pointer;">expand_more</span>
                            Sideboard</b></i>
                        </td>
                    </tr>
                    <?php
                    $sidetotal = $sideboardOtherTotal;
                    if (!empty($sideboardOtherRows)) :
                        foreach ($sideboardOtherRows as $row) :
                            $baseCardName = $row['name'];
                            if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                $row['name'] = $row['flavor_name'];
                            endif;
                            if ($row['type'] !== null) :
                                $card_type = $row['type'];
                                $cardcmc = $row['cmc'];
                            elseif ($row['type'] === null and isset($row['f1_type'])) :
                                $card_type = $row['f1_type'];
                                $cardcmc = $row['f1_cmc'];
                            endif;
                            $illegal_tag = $red_font_tag;
                            $wrong_colour_tag = $firebrick_font_tag;
                            $cardname = $row["name"];
                            $rarity = $row["rarity"];
                            $quantity = $row["sideqty"];
                            $cardset = strtolower($row["setcode"]);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $cardnumber = $row["number_import"];
                            $layout = $row['layout'];
                            $imageManager = new ImageManager($db, $appConfig);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            $msg->logMessage('[DEBUG]', "Sideboard card '$cardname ($cardset $cardnumber)'");
                            $isPlanePhenomenon = $detectPlanePhenomenon($card_type);
                            if (
                                $deck_legality_list != ''
                                and !$isPlanePhenomenon
                            ) :
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Checking legality for sideboard card '$cardname' ('$card_type')"
                                );
                                $index = array_search("$cardId", array_column($deck_legality_list, 'id'));
                                if ($index !== false) :
                                    $card_legal = $deck_legality_list[$index]['legality'];
                                    if ($card_legal === 'legal' or $card_legal === null) :
                                        $msg->logMessage('[DEBUG]', "Card legality is 'legal' or null");
                                        $illegal_tag = '';
                                    else :
                                        $msg->logMessage('[DEBUG]', "Card not legal in this format");
                                        $illegal_cards = true;
                                    endif;
                                else :
                                    $msg->logMessage('[DEBUG]', "Card legality is unknown");
                                    $illegal_tag = '';
                                endif;
                            else :
                                $msg->logMessage('[DEBUG]', "Card legality is not needed");
                                $illegal_tag = '';
                            endif;
                            if (
                                in_array($decktype, $commander_decktypes)
                                and $illegal_tag == ''
                                and !$isPlanePhenomenon
                            ) :
                                $colour_id = count_chars(
                                    str_replace(array('"', '[', ']', ',', ' '), '', $row['color_identity']),
                                    3
                                );
                                $msg->logMessage('[DEBUG]', "Card's colour identity is $colour_id");
                                $colour_id_array = str_split($colour_id);
                                $card_colour_mismatch = '';
                                foreach ($colour_id_array as $value) :
                                    if (strpos($cdr_colours_raw, $value) == false) :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity not OK with Commander(s)"
                                        );
                                        $card_colour_mismatch = true;
                                    else :
                                        $msg->logMessage(
                                            '[DEBUG]',
                                            "Colour $value in card's colour identity is OK with Commander(s)"
                                        );
                                    endif;
                                endforeach;
                                if ($card_colour_mismatch == '' or $colour_id == '') :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity is OK with Commander(s)");
                                    $wrong_colour_tag = '';
                                else :
                                    $msg->logMessage('[DEBUG]', "Card's colour identity not OK with Commander(s)");
                                    $illegal_tag = $wrong_colour_tag;
                                    $deck_colour_mismatch = $card_colour_mismatch = true;
                                endif;
                            endif;

                            // For SLD cards and REX cards with empty "Type", use the f1 definition instead
                            if ($row['type'] !== null) :
                                $card_type = $row['type'];
                                $cardcmc = $row['cmc'];
                            elseif ($row['type'] === null and isset($row['f1_type'])) :
                                $card_type = $row['f1_type'];
                                $cardcmc = $row['f1_cmc'];
                            endif;?>

                            <tr
                                class='deckrow'
                                data-section='sideboard'
                                data-cardid='<?php echo $cardId; ?>'
                                data-cardref='<?php echo $cardref; ?>'
                                data-qty='<?php echo $quantity; ?>'>
                                <?php
                                $i = 0;
                                $cdr_1_plus = false;
                                while ($i < count($commander_multiples)) :
                                    if (
                                        isset($card_type)
                                        and str_contains($card_type, $commander_multiples[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $i = 0;
                                while ($i < count($any_quantity)) :
                                    if (
                                        isset($row['ability'])
                                        and str_contains($row['ability'], $any_quantity[$i]) == true
                                    ) :
                                        $cdr_1_plus = true;
                                    endif;
                                    $i++;
                                endwhile;
                                $deckcardname = str_replace("'", '&#39;', $cardname);
                                ?>
                                <td class="deckcardname hoverTD">
                                <?php
                                if (in_array($decktype, $commander_decktypes) and $cdr_1_plus == true) :
                                    echo "<a class='taphover' $illegal_tag id='listside-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$quantity x $cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)"
                                        . "</a>";
                                else :
                                    echo "<a class='taphover' $illegal_tag id='listside-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>$cardname "
                                        . "($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'></i>)"
                                        . "</a>";
                                endif;
                                ?>
                            </td>
                            <?php
                            if (in_array($decktype, $commander_decktypes)) :
                                echo "<td class='deckcardlistcenter noprint'>";
                                echo "</td>";
                            endif;
                                echo "<td class='deckcardlistcenter noprint'>";
                            ?>
                                <span
                                    onmouseover=""
                                    title="Delete"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-deleteside'
                                    data-cardid='<?php echo $cardId; ?>'
                                    data-cardref='<?php echo $cardref; ?>'>
                                    delete_forever
                                </span>
                                <?php
                                echo "</td>";
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Move to main deck"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-sidetomain'
                                    data-cardid='<?php echo $cardId; ?>'
                                    data-cardref='<?php echo $cardref; ?>'>
                                    arrow_upward
                                </span>
                                <?php
                                echo "</td>";
                                if (!in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistright noprint'>";
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Remove one"
                                        style="cursor: pointer;"
                                        class='material-symbols-outlined js-minusside'
                                        data-cardid='<?php echo $cardId; ?>'
                                        data-cardref='<?php echo $cardref; ?>'>
                                        remove
                                    </span>
                                    <?php
                                    echo "</td>";
                                    echo "<td class='deckcardlistcenter js-qty-side' id='qty-side-$cardref'>";
                                    echo $quantity;
                                    echo "</td>";
                                    $maxCopies = $deckManager->mtgCardCopyLimit(
                                        $card_type,
                                        $row['ability'] ?? null,
                                        $row['f1_ability'] ?? null,
                                        $row['f2_ability'] ?? null,
                                        $decktype
                                    );
                                    $canAddMore = true;
                                    if ($maxCopies !== null) :
                                        $currentCopies = $resultNameTotals[$baseCardName] ?? 0;
                                        if ($currentCopies >= $maxCopies) :
                                            $canAddMore = false;
                                            $msg->logMessage(
                                                '[DEBUG]',
                                                "Copy limit reached for '$baseCardName' ($currentCopies/$maxCopies)"
                                            );
                                        endif;
                                    endif;
                                    $addStyle = $canAddMore ? '' : ' display: none;';
                                    echo "<td class='deckcardlistleft noprint'>";
                                    ?>
                                <span
                                    onmouseover=""
                                    title="Add one"
                                    style="cursor: pointer;<?php echo $addStyle; ?>"
                                    class='material-symbols-outlined js-plusside'
                                    data-cardid='<?php echo $cardId; ?>'
                                    data-cardref='<?php echo $cardref; ?>'
                                    data-maxcopies='<?php echo $maxCopies !== null ? (int) $maxCopies : ''; ?>'>
                                    add
                                </span>
                                    <?php
                                    echo "</td>";
                                endif;
                                echo "</tr>";
                                if (
                                    in_array($row['layout'], $image90rotate)
                                    or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                                ) :
                                    $hoverclass = 'deckcardimgdiv splitfloat';
                                    $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                                else :
                                    $hoverclass = 'deckcardimgdiv';
                                    $msg->logMessage(
                                        '[DEBUG]',
                                        "Hover image not rotated for deckdetail card '$cardname'"
                                    );
                                endif;
                                ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "listside-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div>
                            <?php
                        endforeach;
                    endif;?>
                    <tr
                        style="border-bottom: 1pt solid black; border-top: 1pt solid black;"
                        id="sideboard-total-row">
                        <?php
                        if (in_array($decktype, $commander_decktypes)) :
                            ?>
                            <td colspan="2">
                            <?php
                        else :
                            ?>
                            <td colspan='4'>
                            <?php
                        endif;?>
                            <i><b>Total sideboard</b></i>
                        </td>

                        <td colspan="1" class='deckcardlistcenter'>
                            <i><b><span id='total-sideboard'><?php echo $sidetotal; ?></span></b></i>
                        </td>
                        <td colspan="1">&nbsp;</td>
                    </tr> <?php
                else :
                    $sidetotal = 0;
                endif;

                $planesMainRows = [];
                $planesMainTotal = 0;
                if (mysqli_num_rows($result) > 0) :
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()) :
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                        else :
                            $card_type = '';
                        endif;
                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        if ($detectPlanePhenomenon($card_type)) :
                            $planesMainRows[] = $row;
                            $planesMainTotal += (int) $row['cardqty'];
                        endif;
                    endwhile;
                endif;
                $planesTotal = $planesMainTotal + $sideboardPlaneTotal;
                if ($planesTotal > 0) :?>
                    <tr class="deck-section-header" data-section="planes">
                        <?php
                        if (in_array($decktype, $commander_decktypes)) : ?>
                            <td colspan='4'> <?php
                        elseif ($decktype == 'Wishlist') : ?>
                            <td colspan='5'> <?php
                        else : ?>
                            <td colspan='6'> <?php
                        endif; ?>
                        <i><b>
                            <span
                                class="material-symbols-outlined noprint js-decksection-toggle decksection-toggle-icon"
                                data-section="planes"
                                title="Fold/Unfold"
                                style="cursor: pointer;">expand_more</span>
                            Planes and Phenomena (<span id='total-planes'><?php echo $planesTotal; ?></span>)</b></i>
                        </td>
                    </tr>
                    <?php
                    $msg->logMessage('[DEBUG]', "Rendering planes/phenomena section");
                    if (!empty($planesMainRows)) :
                        foreach ($planesMainRows as $row) :
                            $quantity = (int) $row['cardqty'];
                            $cardname = $row['name'];
                            if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                $cardname = $row['flavor_name'];
                            endif;
                            $displayName = $cardname;
                            $rarity = $row['rarity'];
                            $cardset = strtolower($row['setcode']);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $layout = $row['layout'];
                            $imageManager = new ImageManager($db, $appConfig);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            $deckcardname = str_replace("'", '&#39;', $cardname);
                            ?>
                            <tr class='deckrow' data-section='planes' data-qty='<?php echo $quantity; ?>'>
                                <td class="deckcardname hoverTD">
                                    <?php
                                    echo "<a class='taphover' id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>"
                                        . "$displayName ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'>"
                                        . "</i>)</a>";
                                    ?>
                                </td>
                                <?php
                                if (in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistcenter noprint'></td>";
                                endif;
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Delete"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-deletemain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    delete_forever
                                </span>
                                <?php
                                echo "</td>";
                                if ($decktype != 'Wishlist') :
                                    echo "<td class='deckcardlistcenter noprint'>&nbsp;</td>";
                                endif;
                                if (!in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistright noprint'>&nbsp;</td>";
                                    echo "<td class='deckcardlistcenter'>$quantity</td>";
                                    echo "<td class='deckcardlistleft noprint'>&nbsp;</td>";
                                endif;
                                ?>
                            </tr>
                            <?php
                            if (
                                in_array($row['layout'], $image90rotate)
                                or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                            ) :
                                $hoverclass = 'deckcardimgdiv splitfloat';
                                $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                            else :
                                $hoverclass = 'deckcardimgdiv';
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Hover image not rotated for deckdetail card '$cardname'"
                                );
                            endif;
                            ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div>
                            <?php
                        endforeach;
                    endif;
                    if (!empty($sideboardPlaneRows)) :
                        foreach ($sideboardPlaneRows as $row) :
                            $quantity = (int) $row['sideqty'];
                            $cardname = $row['name'];
                            if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                $cardname = $row['flavor_name'];
                            endif;
                            $displayName = $cardname;
                            $rarity = $row['rarity'];
                            $cardset = strtolower($row['setcode']);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $layout = $row['layout'];
                            $imageManager = new ImageManager($db, $appConfig);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            $deckcardname = str_replace("'", '&#39;', $cardname);
                            ?>
                            <tr class='deckrow' data-section='planes' data-qty='<?php echo $quantity; ?>'>
                                <td class="deckcardname hoverTD">
                                    <?php
                                    echo "<a class='taphover' id='listside-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>"
                                        . "$displayName ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'>"
                                        . "</i>)</a>";
                                    ?>
                                </td>
                                <?php
                                if (in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistcenter noprint'></td>";
                                endif;
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Delete"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-deleteside'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    delete_forever
                                </span>
                                <?php
                                echo "</td>";
                                if ($decktype != 'Wishlist') :
                                    echo "<td class='deckcardlistcenter noprint'>&nbsp;</td>";
                                endif;
                                if (!in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistright noprint'>&nbsp;</td>";
                                    echo "<td class='deckcardlistcenter'>$quantity</td>";
                                    echo "<td class='deckcardlistleft noprint'>&nbsp;</td>";
                                endif;
                                ?>
                            </tr>
                            <?php
                            if (
                                in_array($row['layout'], $image90rotate)
                                or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                            ) :
                                $hoverclass = 'deckcardimgdiv splitfloat';
                                $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                            else :
                                $hoverclass = 'deckcardimgdiv';
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Hover image not rotated for deckdetail card '$cardname'"
                                );
                            endif;
                            ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "listside-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div>
                            <?php
                        endforeach;
                    endif;
                endif;

                $tokenMainRows = [];
                $tokenMainTotal = 0;
                if (mysqli_num_rows($result) > 0) :
                    mysqli_data_seek($result, 0);
                    while ($row = $result->fetch_assoc()) :
                        if ($row['type'] !== null) :
                            $card_type = $row['type'];
                        elseif ($row['type'] === null and isset($row['f1_type'])) :
                            $card_type = $row['f1_type'];
                        else :
                            $card_type = '';
                        endif;
                        if (strpos($card_type, ' //') !== false) :
                            $len = strpos($card_type, ' //');
                            $card_type = substr($card_type, 0, $len);
                        endif;
                        if (
                            (strpos($card_type, 'Token') !== false)
                            || (strpos($card_type, 'Emblem') !== false)
                        ) :
                            $tokenMainRows[] = $row;
                            $tokenMainTotal += (int) $row['cardqty'];
                        endif;
                    endwhile;
                endif;
                $tokensTotal = $tokenMainTotal + $sideboardTokenTotal;
                if ($tokensTotal > 0) :?>
                    <tr class="deck-section-header" data-section="tokens">
                        <?php
                        if (in_array($decktype, $commander_decktypes)) : ?>
                            <td colspan='4'> <?php
                        elseif ($decktype == 'Wishlist') : ?>
                            <td colspan='5'> <?php
                        else : ?>
                            <td colspan='6'> <?php
                        endif; ?>
                        <i><b>
                            <span
                                class="material-symbols-outlined noprint js-decksection-toggle decksection-toggle-icon"
                                data-section="tokens"
                                title="Fold/Unfold"
                                style="cursor: pointer;">expand_more</span>
                            Tokens (<span id='total-tokens'><?php echo $tokensTotal; ?></span>)</b></i>
                        </td>
                    </tr>
                    <?php
                    $msg->logMessage('[DEBUG]', "Rendering tokens section");
                    if (!empty($tokenMainRows)) :
                        foreach ($tokenMainRows as $row) :
                            $quantity = (int) $row['cardqty'];
                            $cardname = $row['name'];
                            if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                $cardname = $row['flavor_name'];
                            endif;
                            $card_type = $row['type'] ?? '';
                            if ($card_type === '' && isset($row['f1_type'])) :
                                $card_type = $row['f1_type'];
                            endif;
                            $isEmblem = (strpos($card_type, 'Emblem') !== false);
                            $displayName = (in_array($decktype, $commander_decktypes) && !$isEmblem)
                                ? "{$quantity} x {$cardname}"
                                : $cardname;
                            $rarity = $row['rarity'];
                            $cardset = strtolower($row['setcode']);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $layout = $row['layout'];
                            $imageManager = new ImageManager($db, $appConfig);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            $deckcardname = str_replace("'", '&#39;', $cardname);
                            ?>
                            <tr class='deckrow' data-section='tokens' data-qty='<?php echo $quantity; ?>'>
                                <td class="deckcardname hoverTD">
                                    <?php
                                    echo "<a class='taphover' id='list-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>"
                                        . "$displayName ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'>"
                                        . "</i>)</a>";
                                    ?>
                                </td>
                                <?php
                                if (in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistcenter noprint'></td>";
                                endif;
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Delete"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-deletemain'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    delete_forever
                                </span>
                                <?php
                                echo "</td>";
                                if ($decktype != 'Wishlist') :
                                    echo "<td class='deckcardlistcenter noprint'>&nbsp;</td>";
                                endif;
                                if (!in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistright noprint'>";
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Remove one"
                                        style="cursor: pointer;"
                                        class='material-symbols-outlined js-minusmain'
                                        data-cardid="<?php echo $cardId; ?>"
                                        data-cardref="<?php echo $cardref; ?>"
                                        data-qty-target="qty-main-<?php echo $cardref; ?>">
                                        remove
                                    </span>
                                    <?php
                                    echo "</td>";
                                    echo "<td class='deckcardlistcenter js-qty-main' id='qty-main-$cardref'>";
                                    echo $quantity;
                                    echo "</td>";
                                    echo "<td class='deckcardlistleft noprint'>";
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Add one"
                                        style="cursor: pointer;"
                                        class='material-symbols-outlined js-plusmain'
                                        data-cardid="<?php echo $cardId; ?>"
                                        data-cardref="<?php echo $cardref; ?>"
                                        data-qty-target="qty-main-<?php echo $cardref; ?>">
                                        add
                                    </span>
                                    <?php
                                    echo "</td>";
                                endif;
                                ?>
                            </tr>
                            <?php
                            if (
                                in_array($row['layout'], $image90rotate)
                                or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                            ) :
                                $hoverclass = 'deckcardimgdiv splitfloat';
                                $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                            else :
                                $hoverclass = 'deckcardimgdiv';
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Hover image not rotated for deckdetail card '$cardname'"
                                );
                            endif;
                            ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "list-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div>
                            <?php
                        endforeach;
                    endif;
                    if (!empty($sideboardTokenRows)) :
                        foreach ($sideboardTokenRows as $row) :
                            $quantity = (int) $row['sideqty'];
                            $cardname = $row['name'];
                            if (isset($row['flavor_name']) and !empty($row['flavor_name'])) :
                                $cardname = $row['flavor_name'];
                            endif;
                            $card_type = $row['type'] ?? '';
                            if ($card_type === '' && isset($row['f1_type'])) :
                                $card_type = $row['f1_type'];
                            endif;
                            $isEmblem = (strpos($card_type, 'Emblem') !== false);
                            $displayName = (in_array($decktype, $commander_decktypes) && !$isEmblem)
                                ? "{$quantity} x {$cardname}"
                                : $cardname;
                            $rarity = $row['rarity'];
                            $cardset = strtolower($row['setcode']);
                            $cardref = str_replace('.', '-', $row['cardsid']);
                            $cardId = $row['cardsid'];
                            $layout = $row['layout'];
                            $imageManager = new ImageManager($db, $appConfig);
                            $imageFunction = $imageManager->getImage(
                                $cardset,
                                $cardId,
                                $imgLocation,
                                $layout,
                                $twoCardDetailSections,
                                false
                            );
                            if ($imageFunction['front'] == 'error') :
                                $imageUrl = '/images/back.jpg';
                            else :
                                $imageUrl = $imageFunction['front'];
                            endif;
                            $deckcardname = str_replace("'", '&#39;', $cardname);
                            ?>
                            <tr class='deckrow' data-section='tokens' data-qty='<?php echo $quantity; ?>'>
                                <td class="deckcardname hoverTD">
                                    <?php
                                    echo "<a class='taphover' id='listside-$cardref-taphover' "
                                        . "href='carddetail.php?id={$row['cardsid']}'>"
                                        . "$displayName ($cardset <i class='ss ss-$cardset ss-$rarity ss-grad ss-fw'>"
                                        . "</i>)</a>";
                                    ?>
                                </td>
                                <?php
                                if (in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistcenter noprint'></td>";
                                endif;
                                echo "<td class='deckcardlistcenter noprint'>";
                                ?>
                                <span
                                    onmouseover=""
                                    title="Delete"
                                    style="cursor: pointer;"
                                    class='material-symbols-outlined js-deleteside'
                                    data-cardid="<?php echo $cardId; ?>"
                                    data-cardref="<?php echo $cardref; ?>">
                                    delete_forever
                                </span>
                                <?php
                                echo "</td>";
                                if ($decktype != 'Wishlist') :
                                    echo "<td class='deckcardlistcenter noprint'>&nbsp;</td>";
                                endif;
                                if (!in_array($decktype, $commander_decktypes)) :
                                    echo "<td class='deckcardlistright noprint'>";
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Remove one"
                                        style="cursor: pointer;"
                                        class='material-symbols-outlined js-minusside'
                                        data-cardid="<?php echo $cardId; ?>"
                                        data-cardref="<?php echo $cardref; ?>">
                                        remove
                                    </span>
                                    <?php
                                    echo "</td>";
                                    echo "<td class='deckcardlistcenter js-qty-side' id='qty-side-$cardref'>";
                                    echo $quantity;
                                    echo "</td>";
                                    echo "<td class='deckcardlistleft noprint'>";
                                    ?>
                                    <span
                                        onmouseover=""
                                        title="Add one"
                                        style="cursor: pointer;"
                                        class='material-symbols-outlined js-plusside'
                                        data-cardid="<?php echo $cardId; ?>"
                                        data-cardref="<?php echo $cardref; ?>">
                                        add
                                    </span>
                                    <?php
                                    echo "</td>";
                                endif;
                                ?>
                            </tr>
                            <?php
                            if (
                                in_array($row['layout'], $image90rotate)
                                or (isset($row['f1_type']) and in_array($row['f1_type'], $image90rotate))
                            ) :
                                $hoverclass = 'deckcardimgdiv splitfloat';
                                $msg->logMessage('[DEBUG]', "Hover image rotated for deckdetail card '$cardname'");
                            else :
                                $hoverclass = 'deckcardimgdiv';
                                $msg->logMessage(
                                    '[DEBUG]',
                                    "Hover image not rotated for deckdetail card '$cardname'"
                                );
                            endif;
                            ?>
                            <div class='<?php echo $hoverclass; ?>' id='<?php echo "listside-$cardref";?>'>
                                <a href='carddetail.php?id=<?php echo $row['cardsid'] ?>'>
                                <img
                                    alt='<?php echo $deckcardname;?>'
                                    class='deckcardimg'
                                    data-cardid="<?php echo $row['cardsid']; ?>"
                                    data-front-src="<?php echo $imageUrl; ?>"
                                    src='<?php echo $imageUrl;?>'
                                ></a>
                            </div>
                            <?php
                        endforeach;
                    endif;
                endif; ?>
            </table>
</div>
