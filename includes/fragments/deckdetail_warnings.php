<?php

/*
Version:     1.32
Date:        12/01/26
Name:        deckdetail_warnings.php
Purpose:     Deck detail warnings fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$rulesFiftyCardDecks = $gameRules->getArray('fiftycarddecks');
$rulesHundredCardDecks = $gameRules->getArray('hundredcarddecks');
$rulesSixtyCardDecks = $gameRules->getArray('sixtycarddecks');

?>
<?php
$hasWarnings = false;
if ((in_array($decktype, $rulesHundredCardDecks) and $total < 100)) :
    $warnings = true;
    $hundred_not_enough = true;
endif;
if ((in_array($decktype, $rulesSixtyCardDecks) and $total < 60)) :
    $warnings = true;
    $sixty_not_enough = true;
endif;
if ((in_array($decktype, $rulesFiftyCardDecks) and $total < 50)) :
    $warnings = true;
    $fifty_not_enough = true;
endif;
if ($illegal_cards == true) :
    $warnings = true;
endif;
if ($deck_colour_mismatch == true) :
    $warnings = true;
endif;

if (isset($warnings)) :
    $hasWarnings = true;
endif;
?>
<div id="deck-warnings-fragment" data-has-content="<?php echo $hasWarnings ? '1' : '0'; ?>">
    <?php
    if ($hasWarnings) :
        echo "<h4>Warnings</h4>";
        echo "<ul style='margin-right: 20px;'>";
        if (isset($secondcommandername)) :
            echo "<li>You have a second commander ('<i>$secondcommandername</i>') - check rules and "
                . "validity with your primary commander</li>";
        endif;
        if (isset($hundred_not_enough)) :
            echo "<li>Your commander deck doesn't have enough cards for legal play</li>";
        endif;
        if (isset($sixty_not_enough)) :
            echo "<li>Your deck doesn't have enough cards for legal play</li>";
        endif;
        if (isset($fifty_not_enough)) :
            echo "<li>Your deck doesn't have enough cards for legal play</li>";
        endif;
        if (isset($illegal_cards) and $illegal_cards == true) :
            echo "<li>Your deck contains <span $red_font_tag>cards </span> not legal in this format</li>";
        endif;
        if (isset($deck_colour_mismatch) and $deck_colour_mismatch == true) :
            echo "<li>Your deck contains <span $firebrick_font_tag>cards </span> not in your Commander(s) "
                . "colour identity</li>";
        endif;
        echo "</ul>";
    endif;
    ?>
</div>
