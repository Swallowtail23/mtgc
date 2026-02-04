<?php

/*
Version:     1.35
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
        $colourIdentityMeta = CardUtils::colourIdentityMeta($cdr_colours_raw ?? '', $msg ?? null);
        $identity_title = $colourIdentityMeta['name'];
        if ($identity_title === 'five') :
            $identity_title = 'All';
        elseif ($identity_title === 'colourless' || $identity_title === '') :
            $identity_title = 'Colourless';
        else :
            $identity_title = ucfirst($identity_title);
        endif;
        $colourIdentityIcon = $colourIdentityMeta['icon'];
        if ($colourIdentityIcon !== '') :
            $colourIdentityIcon = str_replace('class="ms ', 'class="ms ms-2x ', $colourIdentityIcon);
        endif;
        echo "Colour identity: " . $colourIdentityIcon . " ($identity_title)<br>";
    endif; ?>
</div>
