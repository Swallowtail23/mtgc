<?php

/*
Version:     1.34
Date:        04/02/26
Name:        deckdetail_colour_identity.php
Purpose:     Deck detail colour identity fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Cards\CardUtils;

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
        $colourIdentityIcon = '';
        if (isset($cdr_colours_raw) && $cdr_colours_raw !== '') :
            $colourIdentityIcon = CardUtils::colourIdentity($cdr_colours_raw);
        endif;
        if ($colourIdentityIcon !== '') :
            $colourIdentityIcon = str_replace('class="ms ', 'class="ms ms-2x ', $colourIdentityIcon);
        endif;
        echo "Colour identity: " . $colourIdentityIcon . " ($identity_title)<br>";
    endif; ?>
</div>
