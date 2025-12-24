<?php

/*
Version:     1.1
Date:        24/12/25
Name:        deckdetail_colour_identity.php
Purpose:     Deck detail colour identity fragment.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/
?>
<div id="deck-colour-identity-fragment">
    <?php
    if (in_array($decktype, $commander_decktypes) && isset($cdrSet) && $cdrSet === true) :
        if ($cdr_colours == 'five') :
            $identity_title = 'All';
        else :
            $identity_title = ucfirst($cdr_colours);
        endif;
        echo "Colour identity: <img alt='image' src=images/" . $cdr_colours . "_s.png> ($identity_title)<br>";
    endif; ?>
</div>
