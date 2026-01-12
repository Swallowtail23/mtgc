<?php

/*
Version:     1.32
Date:        12/01/26
Name:        deckdetail_colour_identity.php
Purpose:     Deck detail colour identity fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

$rulesCommanderDeckTypes = $gameRules->getArray('commander_decktypes');

?>
<div id="deck-colour-identity-fragment">
    <?php
    $hasCommanderColours = false;
    if (isset($cdrSet) && $cdrSet === true) :
        $hasCommanderColours = true;
    elseif (isset($cdr_colours) && $cdr_colours !== '') :
        $hasCommanderColours = true;
    endif;
    if (isset($msg)) :
        $msg->logMessage(
            '[DEBUG]',
            'Colour identity fragment commander colours ' . ($hasCommanderColours ? 'set' : 'not set')
        );
    endif;

    if (in_array($decktype, $rulesCommanderDeckTypes) && $hasCommanderColours === true) :
        if ($cdr_colours == 'five') :
            $identity_title = 'All';
        else :
            $identity_title = ucfirst($cdr_colours);
        endif;
        echo "Colour identity: <img alt='image' src=images/" . $cdr_colours . "_s.png> ($identity_title)<br>";
    endif; ?>
</div>
